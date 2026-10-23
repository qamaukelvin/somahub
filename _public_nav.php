<?php
// Shared public navbar (Somahub's own pages, including the homepage).
// Expects $navRoot = relative path back to the app root ('.' or '..').
$navRoot = $navRoot ?? '.';
$spnGroups = [
    ['Product', [
        ['How it works', '/index.php#how', 'play_circle'],
        ["What's included", '/features.php', 'checklist'],
        ['Plans and pricing', '/pricing.php', 'sell'],
        ['Try the editor', '/index.php#try', 'edit'],
    ]],
    ['Schools', [
        ['Schools on Somahub', '/schools.php', 'school'],
        ['Check results', '/results-portal.php', 'grading'],
        ['Search schools', '/search.php', 'search'],
        ['Reviews', '/reviews.php', 'reviews'],
        ['Contact us', '/contact.php', 'mail'],
    ]],
    ['Learn', [
        ['Guide', '/docs.php', 'menu_book'],
        ['Blog', '/blog/', 'article'],
    ]],
];
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<header class="somahub-public-header">
  <div class="somahub-public-navbar">
    <a href="<?= $navRoot ?>/index.php" class="somahub-public-brand"><span class="somahub-public-dot"></span> somahub</a>
    <nav><ul id="somahubPublicNavlinks">
      <?php foreach ($spnGroups as $g): ?>
        <li class="spn-group">
          <button type="button" class="spn-label"><?= htmlspecialchars($g[0]) ?><span class="material-symbols-outlined spn-caret">expand_more</span></button>
          <div class="spn-menu">
            <?php foreach ($g[1] as $l): ?>
              <a href="<?= $navRoot . $l[1] ?>"><span class="material-symbols-outlined"><?= $l[2] ?></span><?= htmlspecialchars($l[0]) ?></a>
            <?php endforeach; ?>
          </div>
        </li>
      <?php endforeach; ?>
      <li><a href="<?= $navRoot ?>/dashboard/login.php" class="spn-login">School login</a></li>
      <li><a href="<?= $navRoot ?>/get-started.php" class="somahub-public-navcta">Get started</a></li>
    </ul></nav>
    <button type="button" class="somahub-public-menu-toggle" id="somahubPublicMenuToggle" aria-label="Menu" aria-expanded="false"><span class="material-symbols-outlined">menu</span></button>
  </div>
</header>
<style>
  *{box-sizing:border-box;}
  body{margin:0;}
  .somahub-public-header{position:sticky;top:0;left:0;right:0;width:100%;z-index:1000;background:rgba(247,242,231,0.96);backdrop-filter:blur(6px);border-bottom:1px solid var(--line,#E5DFCC);}
  .somahub-public-navbar{display:flex;align-items:center;justify-content:space-between;padding:12px 24px;max-width:1120px;margin:0 auto;width:100%;min-height:62px;}
  .somahub-public-brand{display:flex;align-items:center;gap:8px;font-weight:800;font-size:1.15rem;color:var(--teal-deep,#0A3A3E);text-decoration:none;flex-shrink:0;}
  .somahub-public-dot{width:9px;height:9px;background:var(--amber,#F2A65A);border-radius:50%;}
  .somahub-public-navbar nav ul{list-style:none;display:flex;gap:6px;align-items:center;margin:0;padding:0;}
  .somahub-public-navbar nav a,.spn-label{font:inherit;font-size:.88rem;font-weight:700;color:var(--ink,#1C1C16);text-decoration:none;}
  .spn-label{background:none;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:2px;padding:9px 12px;border-radius:10px;}
  .spn-login{padding:9px 12px;border-radius:10px;display:inline-block;}
  .spn-label:hover,.spn-group:hover .spn-label,.spn-group:focus-within .spn-label,.spn-login:hover{background:rgba(15,82,87,.08);color:var(--teal,#0F5257);}
  .spn-caret{font-size:18px;opacity:.6;}
  .spn-group{position:relative;}
  .spn-menu{display:none;position:absolute;top:calc(100% - 2px);left:0;min-width:220px;background:#fff;border:1px solid var(--line,#E5DFCC);border-radius:14px;padding:6px;box-shadow:0 16px 36px rgba(10,58,62,.16);z-index:50;}
  .spn-group:hover .spn-menu,.spn-group:focus-within .spn-menu{display:block;}
  .spn-menu a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;font-weight:600 !important;}
  .spn-menu a:hover{background:#F0F6F4;color:var(--teal,#0F5257);} .spn-menu .material-symbols-outlined{font-size:19px;color:var(--teal,#0F5257);}
  .somahub-public-navcta{background:var(--teal,#0F5257);color:var(--sand,#F7F2E7)!important;padding:10px 20px;border-radius:24px;font-weight:700;display:inline-block;margin-left:6px;}
  .somahub-public-menu-toggle{display:none;background:none;border:none;cursor:pointer;color:var(--teal-deep,#0A3A3E);flex-shrink:0;line-height:1;padding:6px;}
  /* Pages with a global button{width:100%...} rule (login, signup) must not restyle the navbar buttons */
  .somahub-public-header button{width:auto;margin:0;box-shadow:none;}
  .somahub-public-header .somahub-public-menu-toggle{background:none;border-radius:8px;padding:6px;color:var(--teal-deep,#0A3A3E);}
  .somahub-public-header .somahub-public-menu-toggle:hover{background:rgba(15,82,87,.08);}
  .somahub-public-header .spn-label{background:none;border-radius:10px;padding:9px 12px;color:var(--ink,#1C1C16);font-size:.88rem;}
  .somahub-public-header .spn-label:hover{background:rgba(15,82,87,.08);color:var(--teal,#0F5257);}
  .somahub-public-header .somahub-public-menu-toggle .material-symbols-outlined{font-size:28px;}
  @media(max-width:900px){.somahub-public-header .spn-label{width:100%;background:none;padding:12px 8px 4px;font-size:.72rem;color:var(--muted,#6E6A5C);}.somahub-public-header .spn-label:hover{background:none;}}
  @media(max-width:900px){
    .somahub-public-navbar nav ul{display:none;}
    .somahub-public-menu-toggle{display:block;}
    .somahub-public-navbar nav ul.open{display:flex;flex-direction:column;align-items:stretch;gap:2px;position:absolute;top:100%;left:0;right:0;background:var(--sand,#F7F2E7);padding:12px 18px 20px;border-bottom:1px solid var(--line,#E5DFCC);max-height:calc(100vh - 62px);overflow-y:auto;z-index:2000;box-shadow:0 16px 30px rgba(0,0,0,.1);}
    .spn-label{width:100%;justify-content:flex-start;cursor:default;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted,#6E6A5C);padding:12px 8px 4px;} .spn-caret{display:none;}
    .spn-menu,.spn-group:hover .spn-menu{display:block;position:static;border:none;box-shadow:none;background:transparent;padding:0;min-width:0;}
    .somahub-public-navcta{text-align:center;margin:10px 0 0;} .spn-login{padding:12px 8px;}
  }
</style>
<script>
(function() {
  var toggleBtn = document.getElementById('somahubPublicMenuToggle');
  var menu = document.getElementById('somahubPublicNavlinks');
  if (!toggleBtn || !menu) return;
  var icon = toggleBtn.querySelector('.material-symbols-outlined');
  function setOpen(o) { menu.classList.toggle('open', o); icon.textContent = o ? 'close' : 'menu'; toggleBtn.setAttribute('aria-expanded', o ? 'true' : 'false'); }
  toggleBtn.addEventListener('click', function(e) { e.stopPropagation(); setOpen(!menu.classList.contains('open')); });
  document.addEventListener('click', function(e) { if (menu.classList.contains('open') && !menu.contains(e.target) && !toggleBtn.contains(e.target)) setOpen(false); });
  menu.querySelectorAll('a').forEach(function(link) { link.addEventListener('click', function() { setOpen(false); }); });
})();
</script>
<?php include __DIR__ . '/includes/cookie-consent.php'; ?>
