<?php
require_once __DIR__ . '/settings.php';

/**
 * Normalizes a phone number to E.164-ish digits only (no +), assuming the
 * configured default country code for local-format numbers (e.g. 07XX...).
 */
function normalize_phone($phone, $defaultCountryCode = '254') {
    $digits = preg_replace('/\D/', '', (string)$phone);
    if ($digits === '') return '';
    if (strpos($digits, '0') === 0) {
        $digits = $defaultCountryCode . substr($digits, 1);
    } elseif (strpos($digits, $defaultCountryCode) !== 0 && strlen($digits) <= 10) {
        $digits = $defaultCountryCode . $digits;
    }
    return $digits;
}

/**
 * Sends a WhatsApp text message via Meta's WhatsApp Cloud API.
 * Requires 'whatsapp_access_token' and 'whatsapp_phone_number_id' to be set
 * in the settings table (Meta Business > WhatsApp > API Setup). Fails
 * silently (returns false) if not configured or the request errors, so a
 * missing/expired token never blocks a booking from completing.
 */
function send_whatsapp_message($toPhone, $message) {
    $settings = getSettings();
    $token = $settings['whatsapp_access_token'] ?? '';
    $phoneId = $settings['whatsapp_phone_number_id'] ?? '';
    if (!$token || !$phoneId) {
        return false; // WhatsApp API not configured yet. see admin settings panel
    }

    $to = normalize_phone($toPhone, $settings['default_country_code'] ?? '254');
    if (!$to) return false;

    $url = "https://graph.facebook.com/v20.0/{$phoneId}/messages";
    $payload = json_encode([
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'text',
        'text' => ['body' => $message],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json",
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
    ]);
    $result = @curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Renders a simple branded HTML email: a header, a message body (plain text,
 * newlines converted to <br>), and an optional CTA button.
 */
function render_email_html($heading, $bodyText, $ctaText = null, $ctaUrl = null) {
    $settings = getSettings();
    $bodyHtml = nl2br(htmlspecialchars($bodyText));
    $ctaBlock = '';
    if ($ctaText && $ctaUrl) {
        $ctaBlock = '<div style="text-align:center;margin:28px 0 8px;">'
            . '<a href="' . htmlspecialchars($ctaUrl) . '" style="background:#1c3626;color:#f6f0e2;text-decoration:none;'
            . 'padding:13px 28px;border-radius:4px;font-family:sans-serif;font-size:15px;font-weight:600;display:inline-block;">'
            . htmlspecialchars($ctaText) . '</a></div>';
    }
    $whatsappNumber = htmlspecialchars($settings['whatsapp_number'] ?? '');
    return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#ece2cb;font-family:Georgia,serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#ece2cb;padding:30px 0;">
    <tr><td align="center">
      <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:6px;overflow:hidden;">
        <tr><td style="background:#1c3626;padding:22px 30px;">
          <span style="color:#e3c78c;font-size:20px;font-weight:bold;font-family:Georgia,serif;">Quattro Homes</span>
        </td></tr>
        <tr><td style="padding:30px 30px 10px;">
          <h2 style="color:#1c3626;font-family:Georgia,serif;margin:0 0 16px;">{$heading}</h2>
          <div style="color:#333;font-family:sans-serif;font-size:15px;line-height:1.6;">{$bodyHtml}</div>
          {$ctaBlock}
        </td></tr>
        <tr><td style="padding:20px 30px 28px;border-top:1px solid #ece2cb;margin-top:20px;">
          <p style="color:#999;font-family:sans-serif;font-size:12px;margin:0;">
            Quattro Homes &middot; Bungoma, Kenya &middot;
            <a href="https://wa.me/{$whatsappNumber}" style="color:#c8a15c;">WhatsApp us</a>
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;
}

/**
 * Sends an email. Uses PHPMailer over SMTP if 'smtp_host' is configured in
 * settings (recommended, e.g. your vendor's SMTP for info@quattrohomes.co.ke).
 * Falls back to PHP's built-in mail() if SMTP isn't configured. Never throws;
 * returns true/false so a failure here never blocks a booking from completing.
 */
function send_email_message($to, $subject, $bodyText, $ctaText = null, $ctaUrl = null, $heading = null) {
    if (!$to) return false;
    $settings = getSettings();
    $html = render_email_html($heading ?? $subject, $bodyText, $ctaText, $ctaUrl);

    if (!empty($settings['smtp_host'])) {
        $phpMailerPath = __DIR__ . '/PHPMailer/PHPMailer.php';
        if (file_exists($phpMailerPath)) {
            require_once $phpMailerPath;
            require_once __DIR__ . '/PHPMailer/SMTP.php';
            require_once __DIR__ . '/PHPMailer/Exception.php';
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $settings['smtp_host'];
                $mail->Port = (int)($settings['smtp_port'] ?? 587);
                $mail->SMTPAuth = true;
                $mail->Username = $settings['smtp_username'] ?? '';
                $mail->Password = $settings['smtp_password'] ?? '';
                $mail->SMTPSecure = ((int)($settings['smtp_port'] ?? 587) === 465) ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->setFrom(
                    $settings['smtp_from_email'] ?? 'info@quattrohomes.co.ke',
                    $settings['smtp_from_name'] ?? 'Quattro Homes'
                );
                $mail->addAddress($to);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $html;
                $mail->AltBody = $bodyText;
                $mail->send();
                return true;
            } catch (Exception $e) {
                // Log error for admin diagnosis
                $logDir = __DIR__ . '/../logs';
                if (!is_dir($logDir)) @mkdir($logDir, 0750, true);
                @file_put_contents(
                    $logDir . '/mail_errors.log',
                    date('[Y-m-d H:i:s] ') . 'SMTP error to ' . $to . ': ' . $e->getMessage() . "\n",
                    FILE_APPEND | LOCK_EX
                );
                return false;
            }
        }
    }

    // Fallback: PHP's built-in mail() with HTML headers
    $fromEmail = $settings['smtp_from_email'] ?? 'info@quattrohomes.co.ke';
    $fromName = $settings['smtp_from_name'] ?? 'Quattro Homes';
    $headers = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "From: {$fromName} <{$fromEmail}>\r\n";
    return @mail($to, $subject, $html, $headers);
}

/**
 * Sends an SMS via Africa's Talking. Requires 'at_username' and 'at_api_key'
 * in settings (from africastalking.com dashboard). Fails silently if not
 * configured, same pattern as WhatsApp. never blocks the booking flow.
 */
function send_sms($toPhone, $message) {
    $settings = getSettings();
    $username = $settings['at_username'] ?? '';
    $apiKey = $settings['at_api_key'] ?? '';
    if (!$username || !$apiKey) return false;

    $to = '+' . normalize_phone($toPhone, $settings['default_country_code'] ?? '254');
    $url = $username === 'sandbox'
        ? 'https://api.sandbox.africastalking.com/version1/messaging'
        : 'https://api.africastalking.com/version1/messaging';

    $fields = [
        'username' => $username,
        'to' => $to,
        'message' => $message,
    ];
    if (!empty($settings['at_sender_id'])) {
        $fields['from'] = $settings['at_sender_id'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HTTPHEADER => [
            "apiKey: {$apiKey}",
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: application/json",
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
    ]);
    $result = @curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Triggers an automated voice call via Africa's Talking Voice API. Intended
 * only for genuinely urgent, time-sensitive cases (e.g. a same-day
 * cancellation). not for routine updates, since calls are more intrusive.
 * NOTE: Voice requires a callback URL configured in your Africa's Talking
 * account to serve the call's XML response (what the caller hears); this
 * function only triggers the outbound call itself.
 */
function trigger_voice_call($toPhone) {
    $settings = getSettings();
    $username = $settings['at_username'] ?? '';
    $apiKey = $settings['at_api_key'] ?? '';
    if (!$username || !$apiKey || empty($settings['at_sender_id'])) return false;

    $to = '+' . normalize_phone($toPhone, $settings['default_country_code'] ?? '254');
    $url = $username === 'sandbox'
        ? 'https://voice.sandbox.africastalking.com/call'
        : 'https://voice.africastalking.com/call';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'username' => $username,
            'from' => $settings['at_sender_id'],
            'to' => $to,
        ]),
        CURLOPT_HTTPHEADER => [
            "apiKey: {$apiKey}",
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: application/json",
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
    ]);
    $result = @curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Sends a booking lifecycle notification to the guest (WhatsApp preferred,
 * email as backup/always-on) and a short internal alert to the owner.
 * $event is one of: created, confirmed, cancelled, checked_out
 */
function notify_booking_event(array $booking, string $event) {
    $settings = getSettings();
    $currency = $settings['currency'] ?? 'KES';
    $channel = $settings['notifications_channel'] ?? 'whatsapp'; // whatsapp | email | both
    $name = $booking['full_name'] ?? 'Guest';
    $checkin = $booking['checkin_date'] ?? '';
    $checkout = $booking['checkout_date'] ?? '';
    $total = isset($booking['total_price']) ? number_format((float)$booking['total_price']) : null;
    $bookingRef = 'QH' . (int)($booking['id'] ?? 0);

    $siteUrl = rtrim($settings['site_url'] ?? '', '/');
    $reportLink = $siteUrl ? "{$siteUrl}/report-issue.php?booking={$booking['id']}" : '';
    $wifiLine = '';
    if (!empty($settings['wifi_ssid'])) {
        $wifiLine = "\n\nWiFi: {$settings['wifi_ssid']}" . (!empty($settings['wifi_password']) ? " / Password: {$settings['wifi_password']}" : '');
    }

    $whatsappLink = "https://wa.me/{$settings['whatsapp_number']}";
    $bookLink = $siteUrl ? "{$siteUrl}/book.php" : '';
    $referralLink = $siteUrl ? "{$siteUrl}/book.php?ref={$bookingRef}" : '';
    $testimonialsLink = $siteUrl ? "{$siteUrl}/testimonials.php" : '';

    $templates = [
        'created' => [
            'subject' => 'Booking request received: Quattro Homes',
            'heading' => 'Thanks for your request!',
            'guest' => "Hi {$name}, thank you for your booking request with Quattro Homes.\n\n"
                . "Dates: {$checkin} to {$checkout}\n"
                . ($total ? "Estimated total: {$currency} {$total}\n" : "")
                . "Booking ref: {$bookingRef}\n\n"
                . "We'll confirm shortly. Reply here on WhatsApp if you have any questions.",
            'cta_text' => 'Chat with us on WhatsApp',
            'cta_url' => $whatsappLink,
        ],
        'confirmed' => [
            'subject' => 'Your stay is confirmed: Quattro Homes',
            'heading' => 'Your stay is confirmed',
            'guest' => "Good news, {$name}! Your stay at Quattro Homes is confirmed.\n\n"
                . "Dates: {$checkin} to {$checkout}\n"
                . ($total ? "Total: {$currency} {$total}\n" : "")
                . "Booking ref: {$bookingRef}"
                . $wifiLine
                . "\n\nWe look forward to hosting you. If anything comes up during your stay, use the button below to report it, or message us on WhatsApp anytime.",
            'cta_text' => $reportLink ? 'Report an Issue During Your Stay' : 'Message Us on WhatsApp',
            'cta_url' => $reportLink ?: $whatsappLink,
        ],
        'cancelled' => [
            'subject' => 'Your booking was cancelled: Quattro Homes',
            'heading' => 'Your booking was cancelled',
            'guest' => "Hi {$name}, your booking (ref {$bookingRef}) for {$checkin} to {$checkout} has been cancelled.\n\n"
                . "If this wasn't expected, or you'd like to rebook different dates, use the button below or reply here on WhatsApp.",
            'cta_text' => 'Rebook New Dates',
            'cta_url' => $bookLink ?: $whatsappLink,
        ],
        'checked_out' => [
            'subject' => 'Thank you for staying with Quattro Homes',
            'heading' => 'Thanks for staying with us!',
            'guest' => "Thank you for staying with us, {$name}! We hope you had a great time.\n\n"
                . "Your booking ref {$bookingRef} is now marked as checked out. We'd love a quick review on our site.\n\n"
                . "Your personal referral link is below. Share it with friends and you'll both save on your next stays:\n"
                . ($referralLink ?: "{$bookingRef} (mention this code when they book)"),
            'cta_text' => 'Leave a Review',
            'cta_url' => $testimonialsLink ?: $whatsappLink,
        ],
    ];

    if (!isset($templates[$event])) return;
    $t = $templates[$event];

    // Guest notification
    if (!empty($booking['phone']) && ($channel === 'whatsapp' || $channel === 'both')) {
        $sentWhatsapp = send_whatsapp_message($booking['phone'], $t['guest']);
        // If WhatsApp isn't configured/delivered, SMS is the next-best "phone-first" fallback
        if (!$sentWhatsapp) {
            send_sms($booking['phone'], $t['guest']);
        }
    }
    if (!empty($booking['email'])) {
        // Always send email — it's the most reliable fallback regardless of channel setting
        send_email_message($booking['email'], $t['subject'], $t['guest'], $t['cta_text'] ?? null, $t['cta_url'] ?? null, $t['heading'] ?? null);
    }

    // Urgent escalation: a cancellation within 24 hours of check-in is time-critical,
    // so place an automated call in addition to the message (if voice is configured).
    if ($event === 'cancelled' && !empty($booking['phone']) && !empty($checkin)) {
        $hoursToCheckin = (strtotime($checkin) - time()) / 3600;
        if ($hoursToCheckin >= 0 && $hoursToCheckin <= 24) {
            trigger_voice_call($booking['phone']);
        }
    }

    // Internal owner alert (short, WhatsApp-first)
    $ownerMsg = "[{$event}] {$name}, {$checkin} to {$checkout}, ref {$bookingRef}"
        . ($total ? ", {$currency} {$total}" : '');
    if (!empty($settings['owner_whatsapp_number'])) {
        send_whatsapp_message($settings['owner_whatsapp_number'], $ownerMsg);
    }
    if (!empty($settings['notify_email'])) {
        $dashboardLink = $siteUrl ? "{$siteUrl}/admin/dashboard.php" : '';
        send_email_message(
            $settings['notify_email'],
            "[Quattro Homes] Booking {$event}: {$bookingRef}",
            $ownerMsg,
            $dashboardLink ? 'View in Dashboard' : null,
            $dashboardLink ?: null,
            'Booking update: ' . ucfirst(str_replace('_', ' ', $event))
        );
    }
}
