document.addEventListener('DOMContentLoaded', function () {
  // Top loading bar shown briefly while any admin form submits (these are
  // classic POST + redirect forms, not AJAX, so this just gives visual
  // feedback during the round trip rather than replacing the page reload).
  const bar = document.createElement('div');
  bar.className = 'admin-loading-bar';
  document.body.appendChild(bar);

  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function (e) {
      // Respect any custom confirm()-based onsubmit handlers (e.g. delete buttons)
      // that may have already returned false and prevented submission.
      if (e.defaultPrevented) return;

      bar.classList.add('active');

      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn && !submitBtn.disabled) {
        submitBtn.dataset.originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner"></span>' + submitBtn.textContent.trim();
        submitBtn.disabled = true;
      }
    });
  });
});
