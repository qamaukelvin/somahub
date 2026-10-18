<?php if (!isset($settings)) $settings = getSettings(); ?>
<footer class="site-footer">
  <div class="container footer-grid">

    <!-- Brand col -->
    <div class="footer-brand">
      <a href="index.php" class="footer-logo">
        <div class="mark">Q</div>
        <div>
          <span class="footer-name">Quattro Homes</span>
          <small>Exclusive Stays &middot; Exceptional Comfort</small>
        </div>
      </a>
      <p class="footer-tagline" data-en="Feel at home. Stay in comfort. Return with a smile." data-sw="Jihisi nyumbani. Kaa vizuri. Rudi na tabasamu.">Feel at home. Stay in comfort. Return with a smile.</p>
      <!-- Social icons -->
      <div class="footer-socials">
        <a href="#" aria-label="Facebook" title="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
        <a href="#" aria-label="Instagram" title="Instagram"><i class="fa-brands fa-instagram"></i></a>
        

<a href="https://wa.me/<?php echo $settings['whatsapp_number']; ?>" target="_blank" aria-label="WhatsApp" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
        <a href="https://twitter.com/" target="_blank" aria-label="Twitter / X" title="Twitter / X"><i class="fa-brands fa-x-twitter"></i></a>
      </div>
    </div>

    <!-- Quick links -->
    <div class="footer-col">
      <h5 data-en="Quick Links" data-sw="Viungo vya Haraka">Quick Links</h5>
      <a href="index.php" data-en="Home" data-sw="Nyumbani">Home</a>
      <a href="about.php" data-en="About Us" data-sw="Kuhusu Sisi">About Us</a>
      <a href="gallery.php" data-en="Gallery" data-sw="Picha">Gallery</a>
      <a href="pricing.php" data-en="Pricing" data-sw="Bei">Pricing</a>
      <a href="book.php" data-en="Book a Stay" data-sw="Weka Nafasi">Book a Stay</a>
    </div>

    <!-- Info links -->
    <div class="footer-col">
      <h5 data-en="More" data-sw="Zaidi">More</h5>
      <a href="testimonials.php" data-en="Reviews" data-sw="Maoni">Reviews</a>
      <a href="blog.php" data-en="Blog" data-sw="Blogu">Blog</a>
      <a href="faq.php" data-en="FAQ" data-sw="Maswali">FAQ</a>
      <a href="contact.php" data-en="Contact" data-sw="Wasiliana">Contact</a>
      <a href="report-issue.php" data-en="Report an Issue" data-sw="Ripoti Tatizo">Report an Issue</a>
    </div>

    <!-- Contact + Share -->
    <div class="footer-col">
      <h5 data-en="Contact" data-sw="Wasiliana">Contact</h5>
      

<a href="https://wa.me/<?php echo $settings['whatsapp_number']; ?>" target="_blank"><i class="fa-brands fa-whatsapp" style="width:16px;"></i> WhatsApp</a>
      <a href="mailto:<?php echo htmlspecialchars($settings['contact_email'] ?? 'info@quattrohomes.co.ke'); ?>"><i class="fa-regular fa-envelope" style="width:16px;"></i> Email</a>
      <a href="contact.php"><i class="fa-solid fa-location-dot" style="width:16px;"></i> Bungoma, Kenya</a>

      <h5 style="margin-top:22px;" data-en="Share" data-sw="Shiriki">Share</h5>
      <div class="footer-share">
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($settings['site_url'] ?? ''); ?>" target="_blank" aria-label="Share on Facebook"><i class="fa-brands fa-facebook-f"></i></a>
        <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Quattro Homes, Exclusive 2-bedroom stays in Bungoma, Kenya. '); ?>&url=<?php echo urlencode($settings['site_url'] ?? ''); ?>" target="_blank" aria-label="Share on X"><i class="fa-brands fa-x-twitter"></i></a>
        

<a href="https://wa.me/?text=<?php echo urlencode('Check out Quattro Homes, exclusive stays in Bungoma, Kenya: ' . ($settings['site_url'] ?? '')); ?>" target="_blank" aria-label="Share on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
        <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode($settings['site_url'] ?? ''); ?>&title=<?php echo urlencode('Quattro Homes Bungoma'); ?>" target="_blank" aria-label="Share on LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
      </div>
    </div>

  </div>

  <div class="footer-bottom">
    <div class="container footer-bottom-inner">
      <span>&copy; <?php echo date('Y'); ?> Quattro Homes. All rights reserved.</span>
      <span class="footer-bottom-links">
        <a href="faq.php" data-en="FAQ" data-sw="Maswali">FAQ</a>
        <a href="contact.php" data-en="Contact" data-sw="Wasiliana">Contact</a>
      </span>
    </div>
  </div>
</footer>



<a href="https://wa.me/<?php echo $settings['whatsapp_number']; ?>?text=<?php echo urlencode('Hi Quattro Homes, I have a question about booking a stay.'); ?>"
   target="_blank" class="whatsapp-float" aria-label="Chat with us on WhatsApp">
  <i class="fa-brands fa-whatsapp"></i>
</a>

<script>
  window.QUATTRO_PRICE_PER_NIGHT = <?php echo json_encode((float)$settings['price_per_night']); ?>;
  window.QUATTRO_CURRENCY = <?php echo json_encode($settings['currency']); ?>;
  window.QUATTRO_MIN_STAY = <?php echo json_encode((int)$settings['min_stay_nights']); ?>;
  window.QUATTRO_DISCOUNT_PERCENT = <?php echo json_encode((float)$settings['discount_percent']); ?>;
  window.QUATTRO_DISCOUNT_MIN_NIGHTS = <?php echo json_encode((int)$settings['discount_min_nights']); ?>;
  window.QUATTRO_INCLUDED_GUESTS = <?php echo json_encode((int)$settings['included_guests']); ?>;
  window.QUATTRO_EXTRA_GUEST_FEE = <?php echo json_encode((float)$settings['extra_guest_fee']); ?>;
</script>

<!-- COOKIE CONSENT BANNER -->
<div id="cookie-banner" role="dialog" aria-live="polite" aria-label="Cookie consent">
  <p>We use essential cookies to keep the site working. See our <a href="terms.php#cookies">Cookie Policy</a> for details.</p>
  <div class="cookie-actions">
    <button class="cookie-accept" id="cookie-accept">Accept</button>
    <button class="cookie-decline" id="cookie-decline">Decline</button>
  </div>
</div>

<?php $jsVer = file_exists(__DIR__.'/../js/script.js') ? substr(md5(filemtime(__DIR__.'/../js/script.js')),0,8) : '1'; ?>
<script src="js/script.js?v=<?php echo $jsVer; ?>"></script>
</body>
</html>
