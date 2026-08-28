<?php
/**
 * Run once daily via cPanel Cron Jobs, e.g.:
 *   php /home/YOURCPANELUSER/somahub.top/cron-daily-reminders.php
 * or as a wget/curl hit if your host only supports URL-based cron:
 *   wget -q -O /dev/null "https://somahub.top/cron-daily-reminders.php?key=YOUR_SECRET"
 *
 * Sends plan-expiry and verification-deadline reminders at fixed thresholds,
 * marking each threshold as sent so a school never gets the same warning twice.
 * Safe to run more than once a day by accident — already-sent flags prevent
 * duplicate emails regardless of how often this fires.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/plan.php';
require_once __DIR__ . '/includes/mailer.php';

// If accessed over the web (URL-based cron), require a shared secret so this
// can't be triggered by anyone who finds the URL. Skip this check entirely
// when run from the command line (php-cli has no $_SERVER['HTTP_HOST']).
if (php_sapi_name() !== 'cli') {
    define('CRON_SECRET', '675ytdydytdyt76r');
    if (($_GET['key'] ?? '') !== CRON_SECRET) {
        http_response_code(403);
        die('Forbidden');
    }
}

$db = get_db();
$sentCount = 0;

$schools = $db->query("SELECT * FROM schools")->fetchAll();

foreach ($schools as $school) {
    // ---------- Plan expiry reminders ----------
    $planDaysLeft = days_until_lockout($school);
    $planLocked = is_premium_locked($school);

    if ($planDaysLeft !== null) {
        if ($planDaysLeft <= 7 && $planDaysLeft > 1 && !$school['plan_reminder_sent_7d']) {
            send_plan_reminder($db, $school, $planDaysLeft, 'plan_reminder_sent_7d');
            $sentCount++;
        } elseif ($planDaysLeft <= 1 && !$school['plan_reminder_sent_1d']) {
            send_plan_reminder($db, $school, $planDaysLeft, 'plan_reminder_sent_1d');
            $sentCount++;
        }
    } elseif ($planLocked && $school['plan'] === 'promo_paid' && !$school['plan_reminder_sent_expired']) {
        send_plan_expired_notice($db, $school);
        $sentCount++;
    }

    // ---------- Verification deadline reminders ----------
    $verifyDaysLeft = days_until_verification_deadline($school);
    $isVerified = ($school['verification_status'] ?? '') === 'verified';

    if ($verifyDaysLeft !== null) {
        if ($verifyDaysLeft <= 3 && $verifyDaysLeft > 1 && !$school['verify_reminder_sent_3d']) {
            send_verification_reminder($db, $school, $verifyDaysLeft, 'verify_reminder_sent_3d');
            $sentCount++;
        } elseif ($verifyDaysLeft <= 1 && !$school['verify_reminder_sent_1d']) {
            send_verification_reminder($db, $school, $verifyDaysLeft, 'verify_reminder_sent_1d');
            $sentCount++;
        }
    } elseif (!$isVerified && !$school['verify_reminder_sent_offline'] && !empty($school['first_login_at'])) {
        // Deadline has actually passed and site is now offline. Schools that
        // have never logged in have no countdown running yet, so they're
        // correctly excluded here rather than flagged as "offline."
        $daysSinceFirstLogin = (time() - strtotime($school['first_login_at'])) / 86400;
        if ($daysSinceFirstLogin > VERIFICATION_GRACE_PERIOD_DAYS) {
            send_verification_offline_notice($db, $school);
            $sentCount++;
        }
    }
}

echo "Done. $sentCount reminder(s) sent.\n";


// ============================================================
// Email senders — one per reminder type, each marks its own
// tracking column so it fires exactly once.
// ============================================================

function get_school_owner_email(PDO $db, int $schoolId): ?string {
    $stmt = $db->prepare("SELECT email FROM users WHERE school_id = ? AND role = 'school_owner' LIMIT 1");
    $stmt->execute([$schoolId]);
    $email = $stmt->fetchColumn();
    return $email ?: null;
}

function send_plan_reminder(PDO $db, array $school, int $daysLeft, string $column): void {
    $email = get_school_owner_email($db, $school['id']);
    if ($email) {
        $body = "
            <h2 style='color:#0F5257;margin-top:0;'>Your Trial Ends in {$daysLeft} Day" . ($daysLeft == 1 ? '' : 's') . "</h2>
            <p><strong>" . htmlspecialchars($school['name']) . "</strong>'s premium features (enrollment, results, fees) will be paused once your trial ends, unless you upgrade to the paid plan.</p>
            <p><a href='https://somahub.top/dashboard/checkout.php' style='color:#0F5257;font-weight:700;'>Upgrade now &rarr;</a></p>
        ";
        send_somahub_email($email, "Your Somahub trial ends in {$daysLeft} day" . ($daysLeft == 1 ? '' : 's'), $body);
    }
    $db->prepare("UPDATE schools SET {$column} = 1 WHERE id = ?")->execute([$school['id']]);
}

function send_plan_expired_notice(PDO $db, array $school): void {
    $email = get_school_owner_email($db, $school['id']);
    if ($email) {
        $body = "
            <h2 style='color:#8C3B2E;margin-top:0;'>Your Trial Has Ended</h2>
            <p><strong>" . htmlspecialchars($school['name']) . "</strong>'s premium features are now paused. Your website itself is still online on the Free plan.</p>
            <p><a href='https://somahub.top/dashboard/checkout.php' style='color:#0F5257;font-weight:700;'>Upgrade to restore them &rarr;</a></p>
        ";
        send_somahub_email($email, 'Your Somahub trial has ended', $body);
    }
    $db->prepare("UPDATE schools SET plan_reminder_sent_expired = 1 WHERE id = ?")->execute([$school['id']]);
}

function send_verification_reminder(PDO $db, array $school, int $daysLeft, string $column): void {
    $email = get_school_owner_email($db, $school['id']);
    if ($email) {
        $body = "
            <h2 style='color:#8C6D1F;margin-top:0;'>Verify Within {$daysLeft} Day" . ($daysLeft == 1 ? '' : 's') . "</h2>
            <p><strong>" . htmlspecialchars($school['name']) . "</strong>'s website will be taken offline in {$daysLeft} day" . ($daysLeft == 1 ? '' : 's') . " unless verification is completed.</p>
            <p><a href='https://somahub.top/dashboard/verify.php' style='color:#0F5257;font-weight:700;'>Complete verification now &rarr;</a></p>
        ";
        send_somahub_email($email, "Verify your school within {$daysLeft} day" . ($daysLeft == 1 ? '' : 's'), $body);
    }
    $db->prepare("UPDATE schools SET {$column} = 1 WHERE id = ?")->execute([$school['id']]);
}

function send_verification_offline_notice(PDO $db, array $school): void {
    $email = get_school_owner_email($db, $school['id']);
    if ($email) {
        $body = "
            <h2 style='color:#8C3B2E;margin-top:0;'>Your Website Is Now Offline</h2>
            <p><strong>" . htmlspecialchars($school['name']) . "</strong>'s preview period ended without verification, so the site is now offline.</p>
            <p>Complete verification to bring it back online immediately.</p>
            <p><a href='https://somahub.top/dashboard/verify.php' style='color:#0F5257;font-weight:700;'>Verify now &rarr;</a></p>
        ";
        send_somahub_email($email, 'Your Somahub website is offline - verify to restore it', $body);
    }
    $db->prepare("UPDATE schools SET verify_reminder_sent_offline = 1 WHERE id = ?")->execute([$school['id']]);
}
