<?php
/**
 * Gathers everything worth keeping about a school before it's deleted.
 * Returns a plain array, ready for json_encode() into archived_schools.
 */
function build_school_export(PDO $db, int $schoolId): array {
    $school = $db->prepare("SELECT * FROM schools WHERE id = ?");
    $school->execute([$schoolId]);
    $school = $school->fetch();

    $users = $db->prepare("SELECT id, name, email, phone, role, created_at FROM users WHERE school_id = ?");
    $users->execute([$schoolId]);
    $users = $users->fetchAll();

    $sections = $db->prepare("
        SELECT st.key_name, st.label, ss.content_json, ss.is_visible, ss.position
        FROM site_sections ss JOIN section_types st ON st.id = ss.section_type_id
        WHERE ss.school_id = ? ORDER BY ss.position
    ");
    $sections->execute([$schoolId]);
    $sections = $sections->fetchAll();

    $fees = $db->prepare("SELECT grade, term_label, amount FROM fee_structures WHERE school_id = ?");
    $fees->execute([$schoolId]);
    $fees = $fees->fetchAll();

    $enrollments = $db->prepare("SELECT child_name, grade_applying_for, parent_name, parent_phone, status, submitted_at FROM enrollment_applications WHERE school_id = ?");
    $enrollments->execute([$schoolId]);
    $enrollments = $enrollments->fetchAll();

    $resultUploads = $db->prepare("SELECT term_label, original_filename, row_count, uploaded_at FROM result_uploads WHERE school_id = ?");
    $resultUploads->execute([$schoolId]);
    $resultUploads = $resultUploads->fetchAll();

    $orders = $db->prepare("SELECT reference_code, total_amount, status, payment_method, paid_at, created_at FROM orders WHERE school_id = ?");
    $orders->execute([$schoolId]);
    $orders = $orders->fetchAll();

    $reviews = $db->prepare("SELECT reviewer_name, rating, comment, status, created_at FROM reviews WHERE reviewable_type = 'school' AND reviewable_id = ?");
    $reviews->execute([$schoolId]);
    $reviews = $reviews->fetchAll();

    return [
        'exported_at' => date('c'),
        'school' => $school,
        'users' => $users,
        'site_sections' => $sections,
        'fee_structures' => $fees,
        'enrollment_applications' => $enrollments,
        'result_upload_history' => $resultUploads,
        'orders' => $orders,
        'reviews' => $reviews,
    ];
}

/**
 * Human-readable summary (not the full JSON) for a courtesy email to the
 * school, if they have a real email on file. Deliberately short — the
 * full export stays with the platform admin for internal record-keeping.
 */
function build_school_export_summary_html(array $export): string {
    $s = $export['school'];
    $sectionCount = count($export['site_sections']);
    $enrollCount = count($export['enrollment_applications']);
    $resultTerms = count($export['result_upload_history']);

    return "
        <h2 style='color:#0F5257;margin-top:0;'>Your Somahub Account Has Been Removed</h2>
        <p>This confirms that <strong>" . htmlspecialchars($s['name']) . "</strong>'s Somahub account and website have been removed, as requested.</p>
        <p>For your records, here's a summary of what was on your account:</p>
        <ul style='padding-left:20px;'>
            <li>{$sectionCount} website section(s) of content</li>
            <li>{$enrollCount} enrollment application(s) received</li>
            <li>{$resultTerms} term(s) of results uploaded</li>
        </ul>
        <p>If you'd like a full copy of your data, reply to this email and we'll send it over.</p>
        <p>Thank you for using Somahub.</p>
    ";
}
