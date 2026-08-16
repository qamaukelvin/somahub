<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
require_once __DIR__ . '/../includes/payments.php';
$db = get_db();
$schoolId = $user['school_id'];

$stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$schoolId]);
$school = $stmt->fetch();

// Pre-fill from the site's own Contact section location, if already set —
// same picker, same data, no need to pick twice.
$locLat = null; $locLng = null;
$contactSection = $db->prepare("
    SELECT ss.content_json FROM site_sections ss
    JOIN section_types st ON st.id = ss.section_type_id
    WHERE ss.school_id = ? AND st.key_name = 'contact'
");
$contactSection->execute([$schoolId]);
$existing = $contactSection->fetchColumn();
if ($existing) {
    $existingContent = json_decode($existing, true);
    if (!empty($existingContent['map_location']) && strpos($existingContent['map_location'], ',') !== false) {
        [$locLat, $locLng] = explode(',', $existingContent['map_location'], 2);
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lat = trim($_POST['lat'] ?? '');
    $lng = trim($_POST['lng'] ?? '');
    $businessPhone = trim($_POST['business_phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$lat || !$lng) {
        $error = 'Please select your school\'s location on the map.';
    } else {
        $orderId = create_order($db, $schoolId, ['google_business_setup']);

        // Save the location + any notes against the order for you to use during setup.
        $db->prepare("
            INSERT INTO service_requests (order_id, service_key, details_json)
            VALUES (?, 'google_business_setup', ?)
        ")->execute([$orderId, json_encode(['lat' => $lat, 'lng' => $lng, 'business_phone' => $businessPhone, 'notes' => $notes])]);

        // Also save the location to the site's own Contact section if it
        // doesn't have one yet — one pick, two uses, as intended.
        if (!$locLat) {
            $typeStmt = $db->prepare("SELECT id FROM section_types WHERE key_name = 'contact'");
            $typeStmt->execute();
            $contactTypeId = $typeStmt->fetchColumn();
            if ($contactTypeId) {
                $sectionStmt = $db->prepare("SELECT id, content_json FROM site_sections WHERE school_id = ? AND section_type_id = ?");
                $sectionStmt->execute([$schoolId, $contactTypeId]);
                $sec = $sectionStmt->fetch();
                if ($sec) {
                    $c = json_decode($sec['content_json'], true);
                    $c['map_location'] = "{$lat},{$lng}";
                    $db->prepare("UPDATE site_sections SET content_json = ? WHERE id = ?")->execute([json_encode($c), $sec['id']]);
                }
            }
        }

        header("Location: invoice.php?id={$orderId}");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Google Business Profile Setup</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .box{background:#fff;border-radius:8px;padding:24px;max-width:520px;}
  label{display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;margin-top:16px;}
  label:first-of-type{margin-top:0;}
  input, textarea{width:100%;padding:9px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;font-family:inherit;}
  .error{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
  .price-tag{background:#F4F1E6;color:#0F5257;padding:10px 16px;border-radius:8px;font-weight:700;margin-bottom:16px;display:inline-block;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Google Business Profile Setup</h1>
  <div class="box">
    <div class="price-tag">KSh 1,200 one-time</div>
    <p style="color:#666;font-size:0.9rem;margin-bottom:16px;">We create and verify <?= htmlspecialchars($school['name']) ?> on Google Business Profile, so you show up on Google Maps and in local search results near your location.</p>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">
      <label>Your School's Location</label>
      <?php $locFieldPrefix = ''; include __DIR__ . '/../includes/_location_picker.php'; ?>

      <label>Business Contact Phone <span style="font-weight:400;color:#888;">(shown publicly on your Google listing)</span></label>
      <input type="text" name="business_phone" placeholder="07XXXXXXXX" required>

      <label>Anything else we should know? (optional)</label>
      <textarea name="notes" rows="3" placeholder="e.g. category, existing photos to use, admin who'll verify"></textarea>

      <button type="submit" class="btn" style="margin-top:20px;">Order — KSh 1,200</button>
    </form>
  </div>
</main>
</body>
</html>
