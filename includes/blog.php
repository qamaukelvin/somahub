<?php
/**
 * Blog auto-generation helpers.
 * All auto-generated posts are created as DRAFTS — never auto-published.
 * Call these from wherever the trigger event happens (e.g. after a new
 * school is inserted); they're safe to call repeatedly (each checks
 * for duplicates before creating a new draft).
 */

function slugify(string $text): string {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

/**
 * Creates a draft blog post. Returns the new post ID, or null if a post
 * with the same trigger_ref_id + post_type already exists (avoids duplicates
 * if a hook accidentally fires twice).
 */
function create_blog_draft(
    PDO $db,
    string $postType,
    string $title,
    string $bodyHtml,
    string $excerpt,
    ?int $triggerRefId = null,
    ?string $coverImage = null,
    ?string $ctaText = null,
    ?string $ctaLink = null,
    ?string $metaDescription = null
): ?int {
    if ($triggerRefId !== null) {
        $check = $db->prepare("SELECT id FROM blog_posts WHERE post_type = ? AND trigger_ref_id = ?");
        $check->execute([$postType, $triggerRefId]);
        if ($check->fetch()) {
            return null; // already exists, don't duplicate
        }
    }

    $baseSlug = slugify($title);
    $slug = $baseSlug;
    $i = 2;
    $slugCheck = $db->prepare("SELECT id FROM blog_posts WHERE slug = ?");
    while (true) {
        $slugCheck->execute([$slug]);
        if (!$slugCheck->fetch()) break;
        $slug = $baseSlug . '-' . $i++;
    }

    $insert = $db->prepare("
        INSERT INTO blog_posts (title, slug, body_html, excerpt, meta_description, cover_image, cta_text, cta_link, post_type, status, trigger_ref_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
    ");
    $insert->execute([
        $title, $slug, $bodyHtml, $excerpt,
        $metaDescription ?? $excerpt,
        $coverImage, $ctaText, $ctaLink,
        $postType, $triggerRefId,
    ]);
    return (int)$db->lastInsertId();
}

/**
 * Pulls a school's hero photo and About text directly from their own site content
 * (site_sections table), so auto-posts can use real content instead of generic filler.
 * Falls back gracefully to null/empty if the school hasn't filled those sections in yet.
 */
function get_school_section_content(PDO $db, int $schoolId, string $sectionKey): ?array {
    $stmt = $db->prepare("
        SELECT s.content_json
        FROM site_sections s
        JOIN section_types t ON t.id = s.section_type_id
        WHERE s.school_id = ? AND t.key_name = ?
        LIMIT 1
    ");
    $stmt->execute([$schoolId, $sectionKey]);
    $row = $stmt->fetch();
    return $row ? json_decode($row['content_json'], true) : null;
}

/**
 * Default image used when a school has no hero photo set yet.
 * Swap this path for whatever generic "school building" placeholder you prefer.
 */
const BLOG_FALLBACK_IMAGE = '/assets/og-share-image.png';

/**
 * Call this right after a new school row is inserted.
 * $school should have at least: id, name, slug, county (or similar location field).
 * Pulls hero photo + About text directly from the school's own site content,
 * falling back to generic text/image if those sections are still empty.
 */
function generate_school_joined_post(PDO $db, array $school): ?int {
    $name = htmlspecialchars($school['name']);
    $county = htmlspecialchars($school['county'] ?? '');
    $locationLine = $county ? " in {$county}" : "";
    $slug = $school['slug'] ?? null;
    $siteUrl = $slug ? "https://{$slug}.somahub.top" : null;

    $hero = get_school_section_content($db, (int)$school['id'], 'hero');
    $about = get_school_section_content($db, (int)$school['id'], 'about');

    // Cover image: school's own hero photo if they've set one, otherwise the generic fallback
    $coverImage = (!empty($hero['hero_photo'])) ? $hero['hero_photo'] : BLOG_FALLBACK_IMAGE;

    // About text: use the school's own words if they've written any, otherwise generic copy
    $aboutText = trim($about['body'] ?? '');
    $aboutParagraph = $aboutText
        ? '<p>' . nl2br(htmlspecialchars($aboutText)) . '</p>'
        : "<p>{$name} now has a free website to help parents and the community find them online, learn about their programs, and get in touch directly.</p>";

    $title = "Welcome, {$school['name']}!";
    $excerpt = "{$school['name']} just joined Somahub{$locationLine}.";
    $body = "
        <p>We're excited to welcome <strong>{$name}</strong>{$locationLine} to the Somahub family! 🎉</p>
        {$aboutParagraph}
        <p>If you know a school that could use a free website too, <a href=\"/index.php#contact\">let them know about Somahub</a>.</p>
    ";

    return create_blog_draft(
        $db, 'auto_school_joined', $title, $body, $excerpt, $school['id'],
        coverImage: $coverImage,
        ctaText: $siteUrl ? "Visit {$school['name']}'s Site" : null,
        ctaLink: $siteUrl
    );
}

/**
 * Call this after any new school is inserted, once you know the new total count.
 * Only creates a post if the count exactly matches one of the milestone thresholds.
 * Add more numbers to $milestones as you grow.
 */
function maybe_generate_milestone_post(PDO $db, int $totalSchoolCount): ?int {
    $milestones = [10, 25, 50, 100, 200, 500, 1000];
    if (!in_array($totalSchoolCount, $milestones, true)) {
        return null;
    }

    $title = "{$totalSchoolCount} Schools Strong! 🎉";
    $excerpt = "Somahub now powers free websites for {$totalSchoolCount} schools across Kenya.";
    $body = "
        <p>A big milestone today — <strong>{$totalSchoolCount} schools</strong> now have free websites through Somahub.</p>
        <p>What started as a small project has grown into a real community of schools getting online, reaching parents, and building trust in their communities.</p>
        <p>Thank you to every school that's joined so far. Here's to the next {$totalSchoolCount}!</p>
    ";

    // Use the milestone number itself as the dedupe key, since there's no school_id to reference here
    return create_blog_draft(
        $db, 'auto_milestone', $title, $body, $excerpt, $totalSchoolCount,
        ctaText: 'See All Schools',
        ctaLink: '/index.php#portfolio'
    );
}
