<?php
// Shared footer for Somahub's own public pages. Self-contained styles.
$__wa = '254707306888'; $__em = 'admin@somahub.top';
try {
    if (!function_exists('get_db')) @require_once __DIR__ . '/config/db.php';
    if (!function_exists('get_setting')) @require_once __DIR__ . '/includes/settings.php';
    if (function_exists('get_db')) { $__d = get_db(); $__wa = preg_replace('/[^0-9]/', '', get_setting($__d, 'support_whatsapp', $__wa)); $__em = get_setting($__d, 'support_email', $__em); }
} catch (\Throwable $e) {}
?>
<style>
.shf,.shf *{box-sizing:border-box;}
footer.shf{background:#0A3A3E;color:#9FC0C1;font-family:'Manrope',sans-serif;margin:0;padding:0;width:100%;text-align:left;font-size:16px;line-height:1.5;border-top:0;display:block;}
.shf a{color:#CFE3E3;text-decoration:none;} .shf a:hover{color:#F5B83D;}
.shf-in{max-width:1100px;width:100%;margin:0 auto;padding:56px 24px 30px;display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr) minmax(0,1fr) minmax(0,1.3fr);gap:36px;align-items:start;}
.shf-in>div{min-width:0;}
.shf-brand{font-weight:800;font-size:1.3rem;color:#fff;display:flex;align-items:center;gap:8px;margin-bottom:12px;}
.shf-brand i{width:11px;height:11px;border-radius:50%;background:#F5B83D;display:inline-block;}
.shf p{text-align:left;font-size:.88rem;line-height:1.65;margin:0 0 16px;}
.shf h4{color:#fff;font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;margin:0 0 14px;}
.shf ul{list-style:none;margin:0;padding:0;} .shf li{margin-bottom:9px;font-size:.9rem;word-break:break-word;}
.shf-cta{display:inline-block;background:#F5B83D;color:#0A3A3E!important;font-weight:800;padding:11px 20px;border-radius:24px;font-size:.88rem;}
.shf-cta:hover{background:#fff;color:#0A3A3E!important;}
.shf-contact a{display:flex;align-items:center;gap:8px;}
.shf-bar{border-top:1px solid #164447;}
.shf-bar div{width:100%;max-width:1100px;margin:0 auto;padding:18px 24px;display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px 20px;font-size:.8rem;color:#7FA5A6;}
.shf-bar span a{margin-left:16px;}
@media(max-width:860px){.shf-in{grid-template-columns:1fr 1fr;padding-top:44px;} .shf-in>div:first-child{grid-column:1/-1;}}
@media(max-width:480px){.shf-in{grid-template-columns:1fr;gap:26px;} .shf-bar div{flex-direction:column;} .shf-bar span a{margin:0 16px 0 0;}}
</style>
<footer class="shf">
  <div class="shf-in">
    <div>
      <div class="shf-brand"><i></i> somahub</div>
      <p>Free websites for Kenyan schools. We build and host it, you edit it. Your core site stays online for free.</p>
      <a class="shf-cta" href="/get-started.php">Create my school site</a>
    </div>
    <div>
      <h4>Product</h4>
      <ul>
        <li><a href="/index.php#try">Try the editor</a></li>
        <li><a href="/features.php">What is included</a></li>
        <li><a href="/pricing.php">Plans and pricing</a></li>
        <li><a href="/dashboard/login.php">School login</a></li>
      </ul>
    </div>
    <div>
      <h4>Explore</h4>
      <ul>
        <li><a href="/schools.php">Schools on Somahub</a></li>
        <li><a href="/results-portal.php">Check results</a></li>
        <li><a href="/reviews.php">Reviews</a></li>
        <li><a href="/docs.php">Guide</a></li>
        <li><a href="/blog/">Blog</a></li>
      </ul>
    </div>
    <div class="shf-contact">
      <h4>Talk to us</h4>
      <ul>
        <li><a href="https://wa.me/<?= htmlspecialchars($__wa) ?>" target="_blank" rel="noopener">WhatsApp us</a></li>
        <li><a href="mailto:<?= htmlspecialchars($__em) ?>"><?= htmlspecialchars($__em) ?></a></li>
        <li><a href="/contact.php">Send a message</a></li>
        <li><a href="https://www.instagram.com/somahub.top/" target="_blank" rel="noopener">Instagram @somahub.top</a></li>
      </ul>
    </div>
  </div>
  <div class="shf-bar"><div>
    <span>&copy; <?= date('Y') ?> Somahub. Made in Kenya.</span>
    <span><a href="/terms.php">Terms</a><a href="/privacy.php">Privacy</a><a href="/privacy.php#cookies">Cookies</a></span>
  </div></div>
</footer>
