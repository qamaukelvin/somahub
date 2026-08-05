<?php
/**
 * Sends an HTML email using PHP's built-in mail() function, which works out
 * of the box on cPanel/shared hosting since it's tied to your domain's mail
 * service (Exim) — no external service or API key needed.
 *
 * NOTE on deliverability: plain mail() can land in spam without proper SPF/DKIM
 * DNS records for somahub.top. Since you already created real mailboxes
 * (info@, hello@, no-reply@) through cPanel, SPF/DKIM are usually set up
 * automatically by cPanel's Email Deliverability tool — worth checking that
 * tool once (cPanel → Email → Email Deliverability) to confirm somahub.top
 * shows as fully configured, since that's what actually keeps you out of spam.
 *
 * @param string $to        Recipient email
 * @param string $subject
 * @param string $bodyHtml  HTML email body
 * @param string $replyTo   Reply-To address (defaults to hello@somahub.top)
 * @return bool             True if the mail server accepted it for delivery
 */
function send_somahub_email(string $to, string $subject, string $bodyHtml, string $replyTo = 'hello@somahub.top'): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false; // never attempt to send to a malformed address
    }

    $fromName = 'Somahub';
    $fromAddress = 'no-reply@somahub.top';

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        "From: {$fromName} <{$fromAddress}>",
        "Reply-To: {$replyTo}",
        'X-Mailer: PHP/' . phpversion(),
    ];

    $wrappedBody = email_wrapper($bodyHtml);

    return @mail($to, $subject, $wrappedBody, implode("\r\n", $headers));
}

/**
 * Wraps email content in simple, brand-consistent HTML styling.
 * Kept minimal and table-free issues aside, this is plain enough
 * to render correctly across most email clients including Gmail's app.
 */
function email_wrapper(string $innerHtml): string {
    return '
    <div style="font-family: Arial, sans-serif; background:#F7F2E7; padding:30px 20px;">
      <div style="max-width:480px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden;">
        <div style="background:#0F5257; padding:20px 24px;">
          <span style="color:#F2A65A; font-weight:800; font-size:18px;">●</span>
          <span style="color:#F7F2E7; font-weight:800; font-size:18px; margin-left:6px;">somahub</span>
        </div>
        <div style="padding:24px; color:#1C1C16; font-size:14px; line-height:1.6;">
          ' . $innerHtml . '
        </div>
        <div style="padding:16px 24px; background:#F7F2E7; color:#6E6A5C; font-size:12px; text-align:center;">
          Somahub · Websites for Kenyan schools · somahub.top
        </div>
      </div>
    </div>';
}
