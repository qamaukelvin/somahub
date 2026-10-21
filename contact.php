<?php
require_once __DIR__ . '/includes/consent.php';
$pageTitle = 'Contact Somahub';
$pageDesc = 'Questions about getting your school online? Message the Somahub team.';
$pagePath = '/contact.php';
include __DIR__ . '/_public_head.php';
?>
<div class="page-top">
<section class="bg-white" id="contact">
  <div class="wrap">
    <div class="section-head center">
      <span class="kicker">Get In Touch</span>
      <h1>Have a question?</h1>
      <p style="margin-top:14px;font-size:0.95rem;color:var(--muted);">We'll get back to you as soon as we can.</p>
    </div>
    <form method="POST" action="contact-submit.php" class="contact-form loader-on-submit">
      <div class="form-row">
        <div>
          <label>Email</label>
          <input type="email" name="email" required>
        </div>
        <div>
          <label>Phone (optional)</label>
          <input type="tel" name="phone">
        </div>
      </div>
      <label>Subject</label>
      <input type="text" name="subject" required>
      <label>Message</label>
      <textarea name="message" rows="5" required></textarea>
      <label class="consent-check">
        <input type="checkbox" name="agreed_to_terms" required>
        I agree to Somahub's <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>.
      </label>
      <button type="submit" class="btn-primary" style="width:100%;margin-top:10px;">Send Message</button>
    </form>
  </div>
</section>

</div>
<div class="cta-band">
  <div class="wrap">
    <h2>Prefer to reach us directly</h2>
    <p>Call, message, or email and we will set up your school personally.</p>
    <a href="mailto:hello@somahub.top" class="btn-primary" style="display:inline-block;">Email hello@somahub.top</a>
  </div>
</div>

<?php include __DIR__ . '/_public_foot.php'; ?>
