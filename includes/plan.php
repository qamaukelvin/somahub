<?php
// Plan enforcement — the "kill switch" for unpaid premium features.
//
// Design choice: this is checked LIVE on every page load, not by a cron job.
// Shared hosting cron isn't always reliable, and a lazy/live check means the
// switch takes effect immediately the moment a deadline passes, with zero
// dependency on a background job actually running.

define('PLAN_GRACE_DAYS', 7); // days past promo_ends_at before features actually lock

/**
 * Returns the school's REAL, currently-active plan — 'paid' or 'free' — after
 * accounting for expired promo periods. Always use this instead of trusting
 * $school['plan'] directly when deciding whether to show/allow a premium feature.
 */
function get_effective_plan(array $school): string {
    if ($school['plan'] === 'paid') {
        return 'paid'; // Manually confirmed real payment — never auto-expires
    }

    if ($school['plan'] === 'promo_paid') {
        if (empty($school['promo_ends_at'])) {
            return 'paid'; // No end date set — treat as active rather than guessing
        }
        $deadline = date('Y-m-d', strtotime($school['promo_ends_at'] . ' +' . PLAN_GRACE_DAYS . ' days'));
        if (date('Y-m-d') <= $deadline) {
            return 'paid'; // Still within the promo period or grace window
        }
        return 'free'; // Grace period has passed with no payment — kill switch engaged
    }

    return 'free'; // plan === 'free'
}

/** True if this school's premium features (results, enrollment, fees) should be locked right now. */
function is_premium_locked(array $school): bool {
    return get_effective_plan($school) !== 'paid';
}

/**
 * Days remaining before the kill switch engages, or null if not on a promo
 * plan / already locked / no deadline set. Useful for warning banners.
 */
function days_until_lockout(array $school): ?int {
    if ($school['plan'] !== 'promo_paid' || empty($school['promo_ends_at'])) {
        return null;
    }
    $deadline = strtotime($school['promo_ends_at'] . ' +' . PLAN_GRACE_DAYS . ' days');
    $daysLeft = (int) ceil(($deadline - time()) / 86400);
    return $daysLeft > 0 ? $daysLeft : null;
}
