<!-- Reusable loading overlay. Include once per page, then call showLoader()
     before a slow operation (form submit, fetch, etc). It auto-shows on any
     form with class="loader-on-submit" without extra JS needed. -->
<style>
  #somahubLoader{
    position:fixed; inset:0; z-index:9999; background:rgba(10,58,62,0.85);
    display:none; align-items:center; justify-content:center; flex-direction:column;
    font-family:'Manrope',sans-serif;
  }
  #somahubLoader.active{ display:flex; }
  .somahub-spinner{
    width:44px; height:44px; border:4px solid rgba(242,166,90,0.25);
    border-top-color:#F2A65A; border-radius:50%;
    animation:somahub-spin 0.8s linear infinite;
  }
  #somahubLoader span{ color:#F7F2E7; margin-top:16px; font-size:0.85rem; font-weight:600; }
  @keyframes somahub-spin{ to{ transform:rotate(360deg); } }
</style>
<div id="somahubLoader">
  <div class="somahub-spinner"></div>
  <span>One moment…</span>
</div>
<script>
  function showLoader(message) {
    const el = document.getElementById('somahubLoader');
    if (message) el.querySelector('span').textContent = message;
    el.classList.add('active');
  }
  function hideLoader() {
    document.getElementById('somahubLoader').classList.remove('active');
  }
  // Every POST form shows the loader on submit by default. Add
  // class="no-loader" to opt a specific form out (e.g. instant AJAX toggles).
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[method="POST" i]:not(.no-loader), form[method="post"]:not(.no-loader)').forEach(form => {
      form.addEventListener('submit', () => showLoader());
    });
  });
</script>
