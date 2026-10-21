<?php
/**
 * Creating a school for a logged-in user - the one place that does it for
 * both get-started.php (new account) and dashboard/school-setup.php (account
 * that signed up before the forms were merged).
 */
require_once __DIR__ . '/appearance.php';
require_once __DIR__ . '/content-presets.php';
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/blog.php';
require_once __DIR__ . '/app_log.php';

/**
 * The starter layout every new school begins with. Order = page order.
 * [section key, layout variant, visible]. Variants that need a photo or a map
 * pin fall back to a simpler layout on the live site until the content exists,
 * so a brand-new site never looks broken. Fees stays hidden until the school
 * adds its first fee. Existing schools are not touched.
 */
function get_starter_layout(): array {
    return [
        ['hero', 'split_cta', 1],
        ['about', 'photo_left', 1],
        ['stats', 'strip', 1], // school highlights: safe, factual stats only (see get_safe_highlights)
        ['academics', 'text_only', 1],
        ['admissions', 'text_photo', 1],
        ['enrollment_form', 'inline_form', 1],
        ['gallery', 'grid', 1],
        ['staff', 'carousel', 1],
        ['testimonials', 'rating', 1],
        ['faq', 'accordion', 1],
        ['fees', 'by_grade', 0],
        ['results_lookup', 'inline_form', 1],
        ['contact', 'side_by_side', 1],
    ];
}


/**
 * Highlights strip content for a new school. Only facts that are true for any
 * school of that type, never invented figures (the type presets' stats are
 * deliberately NOT used for this section).
 */
function get_safe_highlights(string $presetKey, string $county): array {
    $items = [];
    if ($presetKey === 'blank') return [];
    $items[] = ['CBC', $presetKey === 'ecd_nursery' ? 'Early years curriculum' : 'National curriculum'];
    $map = [
        'primary_day' => [['Day School', 'Learning option'], ['KPSEA', 'Grade 6 national assessment']],
        'secondary' => [['Secondary', 'School level']],
        'ecd_nursery' => [['PP1 & PP2', 'Pre-Primary levels']],
        'boarding' => [['Boarding', 'Learning option']],
        'mixed_day_boarding' => [['Day & Boarding', 'Learning options']],
    ];
    foreach ($map[$presetKey] ?? [] as $m) $items[] = $m;
    if (trim($county) !== '') $items[] = [trim($county), 'County'];
    $out = [];
    foreach (array_slice($items, 0, 4) as $i => [$n, $l]) { $out['stat_' . ($i + 1) . '_number'] = $n; $out['stat_' . ($i + 1) . '_label'] = $l; }
    return $out;
}

/**
 * Seeds the starter sections for a school. The ONE place that does it, so
 * self-signup, "Convert lead" and admin "Add school" all start identically.
 * $o keys (all optional): county, phone, email, lat, lng, author_name.
 * Call inside the caller's transaction. Returns the hero section id.
 */
function seed_starter_sections(PDO $db, int $schoolId, string $schoolName, string $presetKey, array $o = []): int {
    $presets = get_school_content_presets();
    $presetContent = $presets[$presetKey]['content'] ?? [];
    $presetContent['stats'] = get_safe_highlights($presetKey, (string)($o['county'] ?? ''));
    $typeStmt = $db->prepare("SELECT id, schema_json FROM section_types WHERE key_name = ?");
    $hasVariantCol = db_has_column($db, 'site_sections', 'layout_variant');
    $ins = $db->prepare($hasVariantCol
        ? "INSERT INTO site_sections (school_id, section_type_id, position, is_visible, layout_variant, content_json) VALUES (?, ?, ?, ?, ?, ?)"
        : "INSERT INTO site_sections (school_id, section_type_id, position, is_visible, content_json) VALUES (?, ?, ?, ?, ?)");
    $pos = 0; $heroId = 0;
    foreach (get_starter_layout() as [$key, $variant, $visible]) {
        $typeStmt->execute([$key]);
        $type = $typeStmt->fetch();
        if (!$type) continue; // section type not installed on this server
        $schema = json_decode($type['schema_json'], true) ?: [];
        $content = array_fill_keys(array_keys($schema), '');
        foreach (($presetContent[$key] ?? []) as $field => $text) {
            if (array_key_exists($field, $content)) $content[$field] = str_replace('{school}', $schoolName, $text);
        }
        if ($key === 'contact') {
            $county = trim((string)($o['county'] ?? ''));
            if (array_key_exists('phone', $content)) $content['phone'] = (string)($o['phone'] ?? '');
            if (array_key_exists('email', $content)) $content['email'] = (string)($o['email'] ?? '');
            if (array_key_exists('address', $content) && $county !== '') $content['address'] = $county . ' County';
            if (array_key_exists('map_location', $content) && isset($o['lat'], $o['lng']) && $o['lat'] !== null && $o['lng'] !== null) $content['map_location'] = round((float)$o['lat'], 6) . ',' . round((float)$o['lng'], 6);
        }
        if ($key === 'about' && !empty($o['author_name']) && array_key_exists('author_name', $content)) $content['author_name'] = (string)$o['author_name'];
        $ins->execute($hasVariantCol ? [$schoolId, $type['id'], $pos++, $visible, $variant, json_encode($content)] : [$schoolId, $type['id'], $pos++, $visible, json_encode($content)]);
        if ($key === 'hero') $heroId = (int)$db->lastInsertId();
    }
    return $heroId;
}

/** True if the column exists (lets the signup work before/after optional migrations). */
function db_has_column(PDO $db, string $table, string $column): bool {
    static $cache = [];
    $k = $table . '.' . $column;
    if (!isset($cache[$k])) {
        $q = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $q->execute([$table, $column]);
        $cache[$k] = (int)$q->fetchColumn() > 0;
    }
    return $cache[$k];
}

/** Job titles offered at signup; "Other" is free text. */
function get_signup_roles(): array {
    return ['Head Teacher / Principal', 'Deputy Head Teacher', 'Proprietor / Director', 'School Administrator', 'Bursar', 'Teacher', 'IT / Communications Officer', 'Other'];
}
/** Roles that can authorise a site for the school without further confirmation. */
function role_is_authority(string $role): bool {
    return in_array($role, ['Head Teacher / Principal', 'Proprietor / Director'], true);
}

/**
 * Validates the shared "your school" fields. Returns [cleanedData, errorString].
 * $needRole: also validates the role/authority fields.
 */
function validate_school_input(PDO $db, array $post): array {
    $d = [
        'school_name' => trim($post['school_name'] ?? ''),
        'slug' => strtolower(preg_replace('/[^a-z0-9]/', '', strtolower($post['slug'] ?? ''))),
        'county' => trim($post['county'] ?? ''),
        'content_preset' => $post['content_preset'] ?? 'primary_day',
        'lat' => is_numeric($post['lat'] ?? '') ? (float)$post['lat'] : null,
        'lng' => is_numeric($post['lng'] ?? '') ? (float)$post['lng'] : null,
        'job_title' => trim($post['job_title'] ?? ''),
        'job_other' => trim($post['job_other'] ?? ''),
        'authorized' => !empty($post['authorized']),
        'head_name' => trim($post['head_name'] ?? ''),
        'head_phone' => trim($post['head_phone'] ?? ''),
    ];
    if ($d['job_title'] === 'Other' && $d['job_other'] !== '') $d['job_title'] = mb_substr($d['job_other'], 0, 60);
    $reserved = ['www', 'admin', 'dashboard', 'blog', 'api', 'mail', 'ftp', 'somahub', 'app', 'test', 'staging'];

    if ($d['school_name'] === '' || $d['slug'] === '' || $d['job_title'] === '' || $d['job_title'] === 'Other') return [$d, 'Please fill in all required fields.'];
    if (strlen($d['slug']) < 3 || in_array($d['slug'], $reserved, true)) return [$d, 'That web address is not available. Please pick another (at least 3 letters or numbers).'];
    $c = $db->prepare("SELECT id FROM schools WHERE slug = ?");
    $c->execute([$d['slug']]);
    if ($c->fetch()) return [$d, "The address \"{$d['slug']}\" is already taken. Please pick another."];
    if (!role_is_authority($d['job_title'])) {
        if (!$d['authorized']) return [$d, "Please confirm you are authorised to create this website for the school."];
        if ($d['head_name'] === '' || $d['head_phone'] === '') return [$d, "Please give your head teacher's name and phone so we can verify the school."];
    }
    return [$d, ''];
}

/**
 * Creates the school, attaches it to $user, starts the 60-day trial, seeds the
 * starter layout (with preset text) and records the role/consent details.
 * Returns ['school_id' => int, 'hero_section_id' => int]. Throws on failure
 * (nothing is saved in that case).
 */
function provision_school(PDO $db, array $user, array $d): array {
    $palettes = get_active_palettes($db);
    $paletteId = $palettes[0]['id'] ?? null;

    $presets = get_school_content_presets();
    $presetContent = $presets[$d['content_preset']]['content'] ?? [];
    $heroId = 0;

    $db->beginTransaction();
    try {
        $sCols = ['name' => $d['school_name'], 'slug' => $d['slug'], 'palette_id' => $paletteId, 'color_mode' => 'preset',
                  'plan' => 'free', 'status' => 'trial', 'verification_status' => 'pending', 'county' => $d['county'], 'phone' => $user['phone'] ?? '', 'email' => $user['email']];
        $isAuth = role_is_authority($d['job_title']);
        if (db_has_column($db, 'schools', 'head_teacher_name')) $sCols['head_teacher_name'] = $isAuth ? null : ($d['head_name'] ?: null);
        if (db_has_column($db, 'schools', 'head_teacher_phone')) $sCols['head_teacher_phone'] = $isAuth ? null : ($d['head_phone'] ?: null);
        $db->prepare('INSERT INTO schools (' . implode(',', array_keys($sCols)) . ', first_login_at) VALUES (' . implode(',', array_fill(0, count($sCols), '?')) . ', NOW())')->execute(array_values($sCols));
        $schoolId = (int)$db->lastInsertId();

        start_trial($db, $schoolId, 60); // everyone starts with full access

        $uSet = ['school_id' => $schoolId];
        if (db_has_column($db, 'users', 'job_title')) $uSet['job_title'] = $d['job_title'];
        if (db_has_column($db, 'users', 'authorized_confirmed')) $uSet['authorized_confirmed'] = ($d['authorized'] || role_is_authority($d['job_title'])) ? 1 : 0;
        $sql = 'UPDATE users SET ' . implode(', ', array_map(fn($c) => "$c = ?", array_keys($uSet)));
        if (db_has_column($db, 'users', 'terms_accepted_at')) $sql .= ', terms_accepted_at = COALESCE(terms_accepted_at, NOW())';
        $db->prepare($sql . ' WHERE id = ?')->execute(array_merge(array_values($uSet), [$user['id']]));

        $heroId = seed_starter_sections($db, $schoolId, $d['school_name'], $d['content_preset'], [
            'county' => $d['county'], 'phone' => $user['phone'] ?? '', 'email' => $user['email'] ?? '',
            'lat' => $d['lat'], 'lng' => $d['lng'],
            'author_name' => (role_is_authority($d['job_title']) && stripos($d['job_title'], 'head') !== false) ? ($user['name'] ?? '') : '',
        ]);
        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    // After commit: each step is best-effort and can never block the redirect.
    try {
        generate_school_joined_post($db, ['id' => $schoolId, 'name' => $d['school_name'], 'slug' => $d['slug'], 'county' => $d['county']]);
        maybe_generate_milestone_post($db, (int)$db->query("SELECT COUNT(*) FROM schools")->fetchColumn());
    } catch (\Throwable $e) { app_log('Auto blog draft failed for school ' . $schoolId . ': ' . $e->getMessage()); }
    try {
        require_once __DIR__ . '/notifications.php';
        create_notification($db, $schoolId, 'welcome', 'Welcome to Somahub!', 'Your site is live. Start by choosing your colours and adding a hero photo.', 'sections.php');
    } catch (\Throwable $e) { app_log('Welcome notification failed for school ' . $schoolId . ': ' . $e->getMessage()); }
    try {
        $r = $db->prepare("SELECT * FROM users WHERE id = ?");
        $r->execute([$user['id']]);
        $fresh = $r->fetch();
        unset($fresh['password_hash']);
        $_SESSION['user'] = $fresh;
    } catch (\Throwable $e) { app_log('Session refresh failed after creating school ' . $schoolId . ': ' . $e->getMessage()); }

    return ['school_id' => $schoolId, 'hero_section_id' => $heroId];
}
