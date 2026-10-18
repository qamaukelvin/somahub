<?php
/**
 * Shared JS for every live-edit page. Call this from inside a <script> tag
 * after the page's own markup, passing the section id and the current
 * layout_variant/layout_size (size only matters for sections with
 * has_size, e.g. Hero - pass '' for sections without it).
 */
function render_live_edit_script(int $sectionId, string $currentVariant, string $currentSize = ''): void { ?>
<input type="file" id="livePhotoInput" accept="image/*" style="display:none;">
<input type="file" id="bulkPhotoInput" accept="image/*" multiple style="display:none;">
<script>
(function(){
  var sectionId = <?= $sectionId ?>;
  var currentVariant = <?= json_encode($currentVariant) ?>;
  var currentSize = <?= json_encode($currentSize) ?>;
  var pendingField = null;
  var pendingReload = false;
  var pendingBulkBox = null;
  var toast = document.getElementById('saveToast');
  var toastMsg = document.getElementById('saveToastMsg');

  function showSaved(ok, msg) {
    toastMsg.textContent = msg || (ok ? 'Saved' : 'Could not save');
    toast.classList.toggle('err', !ok);
    toast.querySelector('.material-symbols-outlined').textContent = ok ? 'check_circle' : 'error';
    toast.classList.add('show');
    clearTimeout(showSaved._t);
    showSaved._t = setTimeout(function(){ toast.classList.remove('show'); }, 2600);
  }

  window.somahubShowSaved = showSaved;

  // A notice can be queued just before a reload (e.g. after switching
  // layout) so it still shows up once the fresh page has loaded.
  var queuedNotice = sessionStorage.getItem('somahubLiveEditNotice');
  if (queuedNotice) {
    sessionStorage.removeItem('somahubLiveEditNotice');
    setTimeout(function(){ showSaved(true, queuedNotice); }, 300);
  }

  function saveField(field, value) {
    var fd = new FormData();
    fd.append('section_id', sectionId);
    fd.append(field, value);
    fetch('section-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){ showSaved(!!data.ok, data.ok ? 'Saved' : (Object.values(data.errors||{})[0] || 'Could not save')); })
      .catch(function(){ showSaved(false); });
  }
  window.somahubSaveField = saveField;

  // Plain contenteditable fields - save on blur, not on every keystroke,
  // same as a normal form field would.
  document.querySelectorAll('[contenteditable="true"][data-field]').forEach(function(el){
    if (el.dataset.field === '_label_readonly') return;
    el.addEventListener('blur', function(){
      var value = el.innerText.replace(/\n{3,}/g, '\n\n').trim();
      saveField(el.dataset.field, value);
    });
  });

  // List-based fields (e.g. About's "list"/"timeline") - every item is one
  // line of the underlying textarea field, newline-joined. Generic over
  // any element with id="liveListItems" and data-field.
  var listWrap = document.getElementById('liveListItems');
  function serializeList() {
    if (!listWrap) return;
    var lines = Array.prototype.map.call(listWrap.querySelectorAll('.live-list-item span'), function(span){
      return span.innerText.trim();
    }).filter(function(line){ return line.length > 0; });
    saveField(listWrap.dataset.field, lines.join('\n'));
  }
  if (listWrap) {
    listWrap.addEventListener('focusout', function(e){
      if (e.target.matches('span[contenteditable]')) serializeList();
    });
  }
  window.somahubRemoveListItem = function(btn) {
    btn.closest('.live-list-item').remove();
    serializeList();
  };
  window.somahubAddListItem = function() {
    if (!listWrap) return;
    var tag = listWrap.dataset.itemTag || 'li';
    var extraClass = listWrap.dataset.itemClass || '';
    var item = document.createElement(tag);
    item.className = ('live-list-item ' + extraClass).trim();
    var span = document.createElement('span');
    span.setAttribute('contenteditable', 'true');
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'live-list-remove';
    btn.innerHTML = '&times;';
    btn.onclick = function(){ somahubRemoveListItem(btn); };
    item.appendChild(span);
    item.appendChild(btn);
    listWrap.appendChild(item);
    span.addEventListener('focusout', serializeList);
    span.focus();
  };

  // Single photo upload (used by every photo slot, including each box's
  // grid slot) - returns a Promise so bulk upload can chain calls.
  function uploadPhoto(field, file) {
    var fd = new FormData();
    fd.append('section_id', sectionId);
    fd.append(field, file);
    var wrap = document.querySelector('.live-photo-wrap[data-photo-field="' + field + '"]');
    return fetch('section-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok && data.content && data.content[field]) {
          if (!wrap) return true; // no slot on screen yet (e.g. an "+ Add photo" tile) - caller reloads
          var newSrc = '../' + data.content[field] + '?t=' + Date.now();
          var img = wrap.querySelector('img');
          if (!img) { img = document.createElement('img'); wrap.insertBefore(img, wrap.firstChild); }
          img.src = newSrc;
          wrap.classList.remove('empty');
          return true;
        }
        showSaved(false, (data.errors && Object.values(data.errors)[0]) || 'Upload failed');
        return false;
      })
      .catch(function(){ showSaved(false); return false; });
  }

  // Click-to-replace on a single photo slot, or a multi-photo box's "+ Add
  // photo" tile (forceReload=true - that tile has no slot element yet, so
  // after a successful upload we reload to rebuild the box around it).
  var fileInput = document.getElementById('livePhotoInput');
  window.somahubTriggerPhotoUpload = function(field, forceReload) {
    pendingField = field;
    pendingReload = !!forceReload;
    fileInput.click();
  };
  fileInput.addEventListener('change', function(){
    if (!fileInput.files.length || !pendingField) return;
    var shouldReload = pendingReload;
    uploadPhoto(pendingField, fileInput.files[0]).then(function(ok){
      if (ok && shouldReload) {
        sessionStorage.setItem('somahubLiveEditNotice', 'Photo added');
        window.location.reload();
      } else {
        showSaved(ok, ok ? 'Photo updated' : undefined);
      }
    });
    fileInput.value = '';
  });

  // Delete a single photo - the little x in the corner of any filled slot.
  // Inside a multi-photo box, removing one photo changes how many slides/
  // grid cells there are, so we reload to rebuild it; a standalone slot
  // just updates in place.
  window.somahubDeletePhoto = function(field) {
    if (!window.confirm('Remove this photo?')) return;
    var fd = new FormData();
    fd.append('section_id', sectionId);
    fd.append('remove_' + field, '1');
    var wrap = document.querySelector('.live-photo-wrap[data-photo-field="' + field + '"]');
    var inBox = wrap && wrap.closest('.live-photo-box, .bulk-box');
    fetch('section-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) {
          if (inBox) {
            sessionStorage.setItem('somahubLiveEditNotice', 'Photo removed');
            window.location.reload();
            return;
          }
          var img = wrap.querySelector('img');
          if (img) img.remove();
          wrap.classList.add('empty');
          showSaved(true, 'Photo removed');
        } else {
          showSaved(false, (data.errors && Object.values(data.errors)[0]) || 'Could not remove photo');
        }
      })
      .catch(function(){ showSaved(false); });
  };

  // Bulk upload for a multi-photo box - used by both the empty-state
  // "Upload multiple photos" CTA and "Replace all". Either way it fills
  // the box's fields (from its data-fields attribute) in that fixed order,
  // overwriting whatever was already in those slots. Reloads after, since
  // the box's preview/grid is built by the PHP render.
  var bulkInput = document.getElementById('bulkPhotoInput');
  window.somahubTriggerBulkUpload = function(triggerEl) {
    pendingBulkBox = triggerEl.closest('.live-photo-box, .bulk-box');
    bulkInput.click();
  };
  bulkInput.addEventListener('change', function(){
    if (!bulkInput.files.length || !pendingBulkBox) return;
    // A .bulk-box with data-empty-fields only fills those still-empty slots
    // (Gallery's "Add several"); a .live-photo-box fills/overwrites in order.
    var fields = (pendingBulkBox.dataset.emptyFields !== undefined ? pendingBulkBox.dataset.emptyFields : pendingBulkBox.dataset.fields).split(',').filter(Boolean);
    var files = Array.prototype.slice.call(bulkInput.files);
    var chain = Promise.resolve();
    var filled = 0;
    files.forEach(function(file, i){
      if (i >= fields.length) return;
      chain = chain.then(function(){ return uploadPhoto(fields[i], file).then(function(ok){ if (ok) filled++; }); });
    });
    chain.then(function(){
      if (filled > 0) {
        sessionStorage.setItem('somahubLiveEditNotice', filled + ' photo' + (filled > 1 ? 's' : '') + ' saved');
        window.location.reload();
      } else {
        showSaved(false, 'Could not add photos');
      }
    });
    bulkInput.value = '';
  });

  // "Remove all" in a multi-photo box's hover toolbar.
  window.somahubRemoveAllPhotos = function(triggerEl) {
    var box = triggerEl.closest('.live-photo-box, .bulk-box');
    var fields = box.dataset.fields.split(',');
    if (!window.confirm('Remove all photos here?')) return;
    var chain = Promise.resolve();
    var removed = 0;
    fields.forEach(function(field){
      chain = chain.then(function(){
        var fd = new FormData();
        fd.append('section_id', sectionId);
        fd.append('remove_' + field, '1');
        return fetch('section-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(data){ if (data.ok) removed++; });
      });
    });
    chain.then(function(){
      sessionStorage.setItem('somahubLiveEditNotice', removed > 0 ? 'All photos removed' : 'Nothing to remove');
      window.location.reload();
    });
  };

  // Rotate every multi-photo box's carousel preview the same way the live
  // site does, so the editor shows what visitors will actually see.
  document.querySelectorAll('.carousel-rotator').forEach(function(rotator){
    var slides = rotator.querySelectorAll('.carousel-slide');
    if (slides.length < 2) return;
    var i = 0;
    setInterval(function(){
      slides[i].classList.remove('active');
      i = (i + 1) % slides.length;
      slides[i].classList.add('active');
    }, 4500);
  });

  // Variant switching - same save endpoint the old Design tab used. Always
  // allowed, even without the needed photos yet: the editor shows the empty
  // upload slots right where the photos go, and the public site falls back
  // to the default layout until they're filled in. We reload after a
  // successful switch since a different variant can need markup that isn't
  // on screen yet. The current height setting (if any) always rides along,
  // so switching layout never silently resets it back to "auto".
  window.somahubSwitchVariant = function(variant) {
    var fd = new FormData();
    fd.append('section_id', sectionId);
    fd.append('layout_variant', variant);
    if (currentSize) fd.append('layout_size', currentSize);
    fetch('section-design-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) {
          sessionStorage.setItem('somahubLiveEditNotice', data.notice || 'Layout updated');
          window.location.reload();
        } else {
          showSaved(false, (data.errors && Object.values(data.errors)[0]) || 'Could not switch layout');
        }
      })
      .catch(function(){ showSaved(false); });
  };

  // Height switching (Hero only) - same endpoint, current variant rides
  // along so it's never reset.
  window.somahubSetSize = function(size) {
    var fd = new FormData();
    fd.append('section_id', sectionId);
    fd.append('layout_variant', currentVariant);
    fd.append('layout_size', size);
    fetch('section-design-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) {
          sessionStorage.setItem('somahubLiveEditNotice', 'Height updated');
          window.location.reload();
        } else {
          showSaved(false, (data.errors && Object.values(data.errors)[0]) || 'Could not change height');
        }
      })
      .catch(function(){ showSaved(false); });
  };

  // Font pairing - site-wide, so this just saves and reloads to pick up the
  // new Google Fonts link and CSS variables.
  window.somahubSetFontPair = function(pairKey) {
    var fd = new FormData();
    fd.append('font_pair', pairKey);
    fetch('font-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) {
          sessionStorage.setItem('somahubLiveEditNotice', 'Font updated');
          window.location.reload();
        } else {
          showSaved(false, (data.errors && Object.values(data.errors)[0]) || 'Could not change font');
        }
      })
      .catch(function(){ showSaved(false); });
  };

  // Close the font picker panel when clicking outside it.
  document.addEventListener('click', function(e){
    var panel = document.getElementById('fontPickerPanel');
    if (!panel.classList.contains('open')) return;
    if (!e.target.closest('.font-picker')) panel.classList.remove('open');
  });

  // Move up / down arrows on repeatable items (staff, testimonials, FAQ, blog).
  // Piggybacks on each item's red remove button to find the item and its slot number.
  (function(){
    var removes = document.querySelectorAll('.it-remove, .staff-remove, .tm-remove');
    if (removes.length < 2) return;
    var st = document.createElement('style');
    st.textContent = '.it-move{position:absolute;top:-8px;left:-8px;z-index:6;display:flex;flex-direction:column;gap:2px;opacity:0;transition:opacity .15s;}'
      + '.it-move button{width:22px;height:20px;border-radius:6px;border:2px solid #fff;background:#1C1C16;color:#fff;cursor:pointer;padding:0;display:flex;align-items:center;justify-content:center;font:700 11px Arial;line-height:1;}'
      + '.it-move button:disabled{opacity:.3;cursor:default;}'
      + '.it-move-host:hover > .it-move{opacity:1;}';
    document.head.appendChild(st);
    var list = Array.prototype.slice.call(removes);
    list.forEach(function(btn, idx){
      var item = btn.parentElement; if (!item) return;
      var f = item.querySelector('[data-field]'); if (!f) return;
      var m = /_(\d+)$/.exec(f.getAttribute('data-field') || ''); if (!m) return;
      var slot = m[1];
      item.classList.add('it-move-host');
      var box = document.createElement('div'); box.className = 'it-move';
      function mk(label, title, dir, disabled){
        var b = document.createElement('button'); b.type = 'button'; b.textContent = label; b.title = title; b.disabled = disabled;
        b.addEventListener('click', function(e){
          e.preventDefault(); e.stopPropagation();
          var fd = new FormData(); fd.append('section_id', sectionId); fd.append('slot', slot); fd.append('dir', dir);
          fetch('section-item-move-ajax.php', { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d){ if (d.ok) { sessionStorage.setItem('somahubLiveEditNotice', 'Order updated'); window.location.reload(); } else showSaved(false, d.error || 'Could not move'); })
            .catch(function(){ showSaved(false); });
        });
        return b;
      }
      box.appendChild(mk('\u25B2', 'Move up', 'up', idx === 0));
      box.appendChild(mk('\u25BC', 'Move down', 'down', idx === list.length - 1));
      item.appendChild(box);
    });
  })();
})();
</script>
<?php }
