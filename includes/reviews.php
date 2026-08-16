<?php
/**
 * Shared reviews system. A single `reviews` table serves two contexts:
 *   - reviewable_type = 'platform', reviewable_id = NULL  → reviews of Somahub itself
 *   - reviewable_type = 'school',   reviewable_id = school_id → reviews of that school
 * All reviews are held as 'pending' until approved in admin/reviews.php —
 * nothing public-facing shows an unmoderated review.
 */

function get_approved_reviews(PDO $db, string $type, ?int $id = null, int $limit = 50): array {
    if ($id === null) {
        $stmt = $db->prepare("
            SELECT * FROM reviews
            WHERE reviewable_type = ? AND reviewable_id IS NULL AND status = 'approved'
            ORDER BY created_at DESC LIMIT ?
        ");
        $stmt->bindValue(1, $type);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    } else {
        $stmt = $db->prepare("
            SELECT * FROM reviews
            WHERE reviewable_type = ? AND reviewable_id = ? AND status = 'approved'
            ORDER BY created_at DESC LIMIT ?
        ");
        $stmt->bindValue(1, $type);
        $stmt->bindValue(2, $id, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_average_rating(array $reviews): ?float {
    if (!$reviews) return null;
    return round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1);
}

/**
 * Handles a review submission POST. Returns true on success (review saved as
 * pending), false with $error set on validation failure. Includes a honeypot
 * field for basic bot protection, matching the pattern used in blog comments.
 */
function submit_review(PDO $db, array $post, string &$error): bool {
    $type = $post['reviewable_type'] ?? '';
    $id = !empty($post['reviewable_id']) ? (int)$post['reviewable_id'] : null;
    $name = trim($post['reviewer_name'] ?? '');
    $role = trim($post['reviewer_role'] ?? '');
    $rating = (int)($post['rating'] ?? 0);
    $comment = trim($post['comment'] ?? '');
    $honeypot = trim($post['website'] ?? '');

    if ($honeypot !== '') {
        return true; // silently drop suspected bot submissions without tipping them off
    }
    if (!in_array($type, ['platform', 'school'], true)) {
        $error = 'Invalid review target.';
        return false;
    }
    if (!$name || !$comment || $rating < 1 || $rating > 5) {
        $error = 'Please fill in your name, a rating, and a comment.';
        return false;
    }

    $stmt = $db->prepare("
        INSERT INTO reviews (reviewable_type, reviewable_id, reviewer_name, reviewer_role, rating, comment, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$type, $id, $name, $role ?: null, $rating, $comment]);
    return true;
}

/** Renders a row of star characters for a given rating, plain-text/HTML safe. */
function render_stars(int $rating): string {
    $rating = max(0, min(5, $rating));
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}
