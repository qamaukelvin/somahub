<?php
// Shared public navbar. Include on any public-facing page outside the
// homepage itself. Expects $navRoot to already be set to the correct
// relative path back to the app root ('.' or '..' depending on depth).
$navRoot = $navRoot ?? '.';
?>
<header class="somahub-public-header">
  <div class="somahub-public-navbar">
    <a href="<?= $navRoot ?>/index.php" class="somahub-public-brand"><span class="somahub-public-dot"></span> somahub</a>
    <nav><ul id="somahubPublicNavlinks">
      <li><a href="<?= $navRoot ?>/index.php#how">How it works</a></li>
      <li><a href="<?= $navRoot ?>/pricing.php">Pricing</a></li>
      <li><a href="<?= $navRoot ?>/index.php#portfolio">Schools</a></li>
      <li><a href="<?= $navRoot ?>/results-portal.php">Check Results</a></li>
      <li><a href="<?= $navRoot ?>/dashboard/login.php">School Login</a></li>
      <li><a href="<?= $navRoot ?>/get-started.php" class="somahub-public-navcta">Get Started</a></li>
    </ul></nav>
    <button class="somahub-public-menu-toggle" onclick="document.getElementById('somahubPublicNavlinks').classList.toggle('open')">☰</button>
  </div>
</header>
<style>
  .somahub-public-header{position:sticky;top:0;z-index:50;background:rgba(247,242,231,0.96);backdrop-filter:blur(6px);border-bottom:1px solid var(--line,#E5DFCC);}
  .somahub-public-navbar{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;max-width:1120px;margin:0 auto;}
  .somahub-public-brand{display:flex;align-items:center;gap:8px;font-weight:800;font-size:1.15rem;color:var(--teal-deep,#0A3A3E);text-decoration:none;}
  .somahub-public-dot{width:9px;height:9px;background:var(--amber,#F2A65A);border-radius:50%;}
  .somahub-public-navbar nav ul{list-style:none;display:flex;gap:24px;align-items:center;margin:0;padding:0;}
  .somahub-public-navbar nav a{font-size:0.87rem;font-weight:600;color:var(--ink,#1C1C16);text-decoration:none;}
  .somahub-public-navbar nav a:hover{color:var(--teal,#0F5257);}
  .somahub-public-navcta{background:var(--teal,#0F5257);color:var(--sand,#F7F2E7)!important;padding:9px 18px;border-radius:20px;font-weight:700;}
  .somahub-public-menu-toggle{display:none;background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--teal-deep,#0A3A3E);}
  @media(max-width:820px){
    .somahub-public-navbar nav ul{display:none;}
    .somahub-public-menu-toggle{display:block;}
    .somahub-public-navbar nav ul.open{display:flex;flex-direction:column;position:absolute;top:60px;left:0;right:0;background:var(--sand,#F7F2E7);padding:20px 24px;border-bottom:1px solid var(--line,#E5DFCC);gap:16px;align-items:flex-start;}
  }
</style>
