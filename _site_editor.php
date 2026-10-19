<?php
// On-page site editor. Loaded by site.php only when the signed-in owner (or an
// admin who is impersonating this school) opens their site with ?edit=1.
// Expects $db, $school, $sections, $theme in scope.
require_once __DIR__ . '/includes/appearance.php';
require_once __DIR__ . '/includes/section_variants.php';
require_once __DIR__ . '/includes/edit-history.php';
require_once __DIR__ . '/dashboard/_live_editor_map.php';

$__registry = get_section_variant_registry();
$__editorFiles = get_live_editor_files();
$__schemas = [];
foreach ($db->query("SELECT id, key_name, label, is_premium, schema_json FROM section_types ORDER BY id")->fetchAll() as $t) $__schemas[$t['key_name']] = $t;

$__skipField = '/(_color$|^cta_|destination|_url$|_link$|^rating|_icon$)/';
$__data = [];
foreach ($sections as $s) {
    $key = $s['key_name'];
    $schema = json_decode($__schemas[$key]['schema_json'] ?? '[]', true) ?: [];
    $content = json_decode($s['content_json'], true) ?: [];
    $text = []; $imgs = [];
    foreach ($schema as $f => $type) {
        $v = $content[$f] ?? '';
        if (!is_string($v) || $v === '') continue;
        if ($type === 'image') $imgs[] = [$f, $v];
        elseif (!preg_match($__skipField, $f)) { $ck = section_color_key($key, $f); if ($ck === '' && isset($schema[$f . '_color'])) $ck = $f . '_color'; $text[] = [$f, $v, $ck]; }
    }
    $cfg = $__registry[$key] ?? [];
    $photoFields = $cfg['photo_fields'] ?? [];
    $have = 0; $emptyFields = [];
    foreach ($photoFields as $pf) { if (!empty($content[$pf])) $have++; else $emptyFields[] = $pf; }
    $opts = [];
    foreach (($cfg['options'] ?? []) as $k => $o) $opts[] = ['k' => $k, 'l' => $o['label'], 'i' => $o['icon'], 'n' => max(0, (int)($o['photos_needed'] ?? 0) - $have)];
    $curVariant = $s['layout_variant'] ?? ($cfg['default'] ?? '');
    $item = null;
    $fill = [];
    if (empty($cfg['repeatable']) && $key !== 'contact') {
        $grp = $cfg['field_groups'][$s['layout_variant'] ?? ($cfg['default'] ?? '')] ?? null;
        foreach ($schema as $f => $type) {
            if ($type === 'image' || preg_match('/(_color$|^cta_|destination|_url$|_link$|^rating|_icon$|phone|email|map|whatsapp|address|office_hours|date|amount|price|^layout)/i', $f)) continue;
            if ($grp !== null && !in_array($f, $grp, true)) continue;
            if (!empty($content[$f])) continue;
            $fill[] = [$f, ucfirst(str_replace('_', ' ', $f))];
        }
    }
    if (!empty($cfg['repeatable']) && !empty($cfg['item_fields'])) {
        $fmt = $cfg['field_name_format'] ?? '{stem}_{index}';
        $fnm = fn($st, $i) => str_replace(['{stem}', '{index}'], [$st, $i], $fmt);
        $maxI = (int)($cfg['max_items'] ?? 10); $filled = 0; $emptySlots = [];
        for ($i = 1; $i <= $maxI; $i++) {
            $used = false;
            foreach ($cfg['item_fields'] as $st) { if (!empty($content[$fnm($st, $i)])) { $used = true; break; } }
            if ($used) $filled++; else $emptySlots[] = $i;
        }
        $grp = $cfg['item_field_groups'][$curVariant] ?? $cfg['item_fields'];
        $item = ['label' => $cfg['item_label'] ?? 'Item', 'max' => $maxI, 'count' => $filled, 'empty' => $emptySlots,
                 'rx' => ($fmt === '{stem}_{index}') ? '_(\\d+)$' : '^stat_(\\d+)_',
                 'photoField' => in_array('photo', $grp, true) && in_array('photo', $cfg['item_fields'], true) ? 'photo' : '',
                 'fmt' => $fmt, 'gallery' => $s['key_name'] === 'gallery', 'canAdd' => in_array($s['key_name'], ['staff','testimonials','faq','blog','stats','gallery'], true)];
    }
    $needNow = (int)(($cfg['options'][$curVariant]['photos_needed'] ?? 0));
    $missing = $needNow > $have ? array_slice($emptyFields, 0, $needNow - $have) : [];
    $__data[] = [
        'id' => (int)$s['id'], 'key' => $key, 'label' => $s['label'], 'hasSize' => !empty($cfg['has_size']), 'size' => $s['layout_size'] ?? 'auto', 'photos' => count($photoFields) > 1 ? array_map(fn($pf) => [$pf, (string)($content[$pf] ?? '')], $photoFields) : [], 'framed' => isset($__editorFiles[$key]), 'visible' => (int)$s['is_visible'], 'locked' => !empty($s['__locked']),
        'variant' => $curVariant, 'options' => $opts, 'item' => $item, 'fill' => $fill, 'missing' => $missing, 'needLabel' => $cfg['options'][$curVariant]['label'] ?? '',
        'text' => $text, 'imgs' => $imgs, 'editFile' => $__editorFiles[$key] ?? 'section-edit.php',
    ];
}
$__ctaOpts = ['contact' => 'Contact Us', 'enroll' => 'Request Admission', 'results' => 'Check Results', 'fees' => 'School Fees'];
$__ctaSec = ['contact' => ['contact', 'Contact'], 'enroll' => ['enrollment_form', 'Enrollment Form'], 'results' => ['results_lookup', 'Results Lookup'], 'fees' => ['fees', 'Fees']];
$__lockedPlan = is_premium_locked($school); $__live = [];
foreach ($sections as $__x) { $__live[$__x['key_name']] = ($__live[$__x['key_name']] ?? false) || ((int)$__x['is_visible'] === 1 && !($__lockedPlan && (int)$__x['is_premium'] === 1)); }
$__ctaUnmet = [];
foreach ($__ctaSec as $k => [$sk, $nm]) { if (empty($__live[$sk])) $__ctaUnmet[$k] = "The $nm section isn't visible on your site, so this button won't show to visitors until you add or show it."; }
$__types = [];
foreach ($__schemas as $t) $__types[] = ['id' => (int)$t['id'], 'l' => $t['label'], 'k' => $t['key_name'], 'p' => (int)$t['is_premium']];
$__pal = [];
foreach (get_active_palettes($db) as $p) {
    $v = json_decode($p['css_variables_json'], true) ?: [];
    $__pal[] = ['id' => (int)$p['id'], 'n' => $p['name'], 'p' => $v['primary'] ?? '#0F5257', 'a' => $v['accent'] ?? '#F2A65A', 'b' => $v['bg'] ?? '#FBF8F2'];
}
$__fonts = [];
foreach (get_font_pairs() as $k => $f) $__fonts[] = ['k' => $k, 'l' => $f['label']];
$__hist = history_status($db, (int)$school['id']);
$__fees = [];
try {
    $fq = $db->prepare("SELECT id, grade, term_label, amount FROM fee_structures WHERE school_id = ? ORDER BY grade, term_label");
    $fq->execute([(int)$school['id']]);
    $__fees = array_map(fn($r) => ['id' => (int)$r['id'], 'grade' => $r['grade'], 'term' => $r['term_label'], 'amount' => (float)$r['amount']], $fq->fetchAll());
} catch (\Throwable $e) {}
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<style>
  .se-i{font-family:'Material Symbols Outlined';font-weight:400;font-style:normal;line-height:1;display:inline-block;vertical-align:middle;font-size:20px;}
  body.se-on{padding-bottom:84px;}
  body.se-on section[id], body.se-on .se-placeholder{position:relative;}
  body.se-on [data-se-edit]{outline:1.5px dashed transparent;outline-offset:3px;cursor:text;transition:outline-color .12s;}
  body.se-on [data-se-edit]:hover{outline-color:#F2A65A;} body.se-on [data-se-edit]:focus{outline:2px solid #F2A65A;}
  body.se-on [data-se-img]{cursor:pointer;outline:2px dashed transparent;outline-offset:-2px;} body.se-on [data-se-img]:hover{outline-color:#F2A65A;}
  body.se-on section[id]:hover{box-shadow:inset 0 0 0 2px rgba(242,166,90,.55);}
  section.se-hidden{opacity:.45;background-image:repeating-linear-gradient(45deg,transparent,transparent 12px,rgba(0,0,0,.04) 12px,rgba(0,0,0,.04) 24px);}
  .se-tools{position:absolute;top:10px;left:10px;right:10px;z-index:60;display:flex;justify-content:space-between;pointer-events:none;}
  .se-tools > *{pointer-events:auto;}
  .se-btn{background:#1C1C16;color:#fff;border:2px solid #fff;border-radius:999px;padding:7px 13px;font:700 .76rem Arial,sans-serif;cursor:pointer;display:inline-flex;gap:6px;align-items:center;box-shadow:0 3px 12px rgba(0,0,0,.3);opacity:.94;}
  .se-btn:hover{background:#0F5257;opacity:1;} .se-btn.round{padding:7px;width:36px;height:36px;justify-content:center;}
  .se-pop{position:absolute;z-index:70;background:#fff;color:#1C1C16;border-radius:14px;box-shadow:0 14px 38px rgba(0,0,0,.32);padding:6px;min-width:220px;display:none;font:600 .86rem Arial,sans-serif;text-align:left;}
  .se-pop.open{display:block;} .se-tools .se-pop{top:44px;} .se-tools .l .se-pop{left:0;min-width:270px;} .se-tools .r .se-pop{right:0;}
  .se-pop button{display:flex;width:100%;gap:10px;align-items:center;background:none;border:none;border-radius:9px;padding:10px 12px;font:inherit;color:inherit;cursor:pointer;text-align:left;}
  .se-pop button:hover{background:#F4F1E6;} .se-pop button.on{background:#E6F0EE;color:#0F5257;} .se-pop button.danger{color:#B3261E;}
  .se-pop hr{border:none;border-top:1px solid #eee;margin:4px 0;} .se-tools .l,.se-tools .r{position:relative;}
  .se-placeholder{margin:0;padding:18px 20px;background:repeating-linear-gradient(45deg,#f7f4ea,#f7f4ea 12px,#efe9d6 12px,#efe9d6 24px);color:#5a5a52;font:600 .9rem Arial,sans-serif;min-height:70px;display:flex;align-items:center;gap:10px;}
  .se-placeholder .se-tools{position:absolute;}
  #seBar{position:fixed;left:50%;bottom:14px;transform:translateX(-50%);z-index:99990;background:#1C1C16;color:#fff;border-radius:18px;padding:8px 10px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:center;box-shadow:0 12px 34px rgba(0,0,0,.4);font:700 .8rem Arial,sans-serif;max-width:calc(100vw - 16px);}
  #seBar button,#seBar a.se-done{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:11px;padding:9px 12px;font:inherit;cursor:pointer;display:inline-flex;gap:6px;align-items:center;text-decoration:none;}
  #seBar button:hover:not(:disabled){background:rgba(255,255,255,.24);} #seBar button:disabled{opacity:.35;cursor:default;}
  #seBar .sep{width:1px;height:26px;background:rgba(255,255,255,.2);margin:0 2px;} #seBar a.se-done{background:#F2A65A;border-color:#F2A65A;color:#0A3A3E;}
  #seBar .wrapb{position:relative;} #seBar .se-pop{bottom:calc(100% + 12px);left:50%;transform:translateX(-50%);top:auto;width:300px;padding:12px;}
  .se-pal{display:grid;grid-template-columns:1fr 1fr;gap:6px;max-height:230px;overflow:auto;} .se-pal button{border:1.5px solid #E2DCC6;padding:7px 8px;font-size:.74rem;}
  .se-pal button.on{border-color:#0F5257;background:#E6F0EE;} .se-dots span{display:inline-block;width:13px;height:13px;border-radius:50%;margin-right:-4px;border:1.5px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.14);}
  .se-pop h5{margin:2px 4px 8px;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#777;} .se-pop select{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;font:inherit;}
  #seToast{position:fixed;top:14px;right:14px;z-index:99999;background:#0F5257;color:#fff;border-radius:999px;padding:9px 16px;font:700 .8rem Arial,sans-serif;opacity:0;transform:translateY(-8px);transition:.2s;pointer-events:none;}
  #seToast.show{opacity:1;transform:none;} #seToast.err{background:#B3261E;}
  #seModal{position:fixed;inset:0;z-index:99998;background:rgba(10,30,32,.6);display:none;align-items:center;justify-content:center;padding:16px;} #seModal.open{display:flex;}
  #seModal .card{background:#fff;color:#1C1C16;border-radius:18px;max-width:420px;width:100%;padding:22px;font-family:Arial,sans-serif;max-height:80vh;overflow:auto;}
  #seModal h3{margin:0 0 6px;} #seModal p{margin:0 0 14px;color:#666;font-size:.88rem;} #seModal .acts{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;}
  #seModal .acts button{border-radius:999px;padding:10px 18px;font:800 .84rem Arial,sans-serif;cursor:pointer;border:2px solid #0F5257;background:#fff;color:#0F5257;} #seModal .acts .go{background:#0F5257;color:#fff;} #seModal .acts .bad{background:#B3261E;border-color:#B3261E;color:#fff;}
  #seModal .list button{display:flex;width:100%;gap:10px;align-items:center;background:none;border:1px solid #E2DCC6;border-radius:10px;padding:11px 12px;margin-bottom:6px;font:700 .86rem Arial,sans-serif;cursor:pointer;text-align:left;} #seModal .list button:hover{border-color:#0F5257;background:#F4F1E6;}
  .se-photos{position:relative;z-index:6;margin:62px auto 14px;max-width:760px;background:#fff;color:#1C1C16;border:2px dashed #F2A65A;border-radius:16px;padding:14px 16px;font:600 .86rem Arial,sans-serif;box-shadow:0 6px 22px rgba(0,0,0,.12);}
  .se-photos b{display:block;margin-bottom:4px;font-size:.9rem;} .se-photos p{margin:0 0 10px;color:#5a5a52;font-weight:500;}
  .se-slots{display:flex;gap:10px;flex-wrap:wrap;} .se-slot{flex:1 1 150px;min-height:92px;border:2px dashed #0F5257;border-radius:12px;background:#F4F1E6;color:#0F5257;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;font:700 .78rem Arial,sans-serif;padding:10px;transition:background .12s;}
  .se-slot:hover{background:#E6F0EE;} .se-slot .se-i{font-size:28px;}
  .se-pop button .need{margin-left:auto;background:#FBE8C8;color:#8a5a00;border-radius:999px;padding:2px 8px;font-size:.68rem;font-weight:800;white-space:nowrap;}
  .se-item{position:relative;} .se-itemtools{position:absolute;top:6px;right:6px;z-index:40;display:flex;gap:4px;opacity:0;transition:opacity .12s;}
  .se-item:hover > .se-itemtools,.se-itemtools:focus-within{opacity:1;} .se-itemtools button{width:26px;height:26px;border-radius:50%;border:2px solid #fff;background:#1C1C16;color:#fff;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;padding:0;box-shadow:0 2px 8px rgba(0,0,0,.35);}
  .se-itemtools button:hover{background:#0F5257;} .se-itemtools button.del:hover{background:#B3261E;} .se-itemtools button:disabled{opacity:.3;cursor:default;} .se-itemtools .se-i{font-size:15px;}
  .se-additem{display:flex;justify-content:center;margin:26px auto 6px;} .se-additem button{background:#fff;color:#0F5257;border:2px dashed #0F5257;border-radius:14px;padding:13px 22px;font:800 .86rem Arial,sans-serif;cursor:pointer;display:inline-flex;gap:8px;align-items:center;}
  .se-additem button:hover{background:#E6F0EE;} .se-additem small{font-weight:600;color:#5a5a52;} .se-placeholder .se-additem{margin:0 0 0 auto;}
  .se-fill{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;align-items:center;margin:14px auto 4px;font:700 .78rem Arial,sans-serif;color:#5a5a52;} .se-fill button{background:#fff;color:#0F5257;border:1.5px dashed #0F5257;border-radius:999px;padding:7px 14px;font:800 .78rem Arial,sans-serif;cursor:pointer;} .se-fill button:hover{background:#E6F0EE;}
  .se-flash{animation:seFlash 1.6s ease-out;} @keyframes seFlash{0%{box-shadow:inset 0 0 0 6px #F2A65A;}100%{box-shadow:inset 0 0 0 6px rgba(242,166,90,0);}}
  .se-fees{width:100%;border-collapse:collapse;font-size:.85rem;margin-bottom:12px;} .se-fees th{text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;color:#777;padding:4px 6px;} .se-fees td{padding:4px 3px;} .se-fees input{width:100%;padding:8px;border:1px solid #ccc;border-radius:8px;font:inherit;box-sizing:border-box;} .se-fees .rm{background:none;border:none;color:#B3261E;cursor:pointer;font-size:1.1rem;padding:6px;}
  #seModal .card.wide{max-width:640px;}
  .se-lock{background:#F2A65A;color:#3b2500;border-radius:999px;padding:6px 12px;font:800 .72rem Arial,sans-serif;white-space:nowrap;align-self:center;}
  .se-addcard{display:flex;} .se-addcard button{flex:1;min-height:inherit;border:2px dashed #0F5257;border-radius:14px;background:rgba(255,255,255,.65);color:#0F5257;font:800 .9rem Arial,sans-serif;display:flex;flex-direction:column;gap:6px;align-items:center;justify-content:center;cursor:pointer;padding:18px;text-align:center;}
  .se-addcard button:hover{background:#E6F0EE;} .se-addcard small{font-weight:600;color:#5a5a52;} .se-addcard .se-i{font-size:30px;}
  .se-addcard.first{margin:26px 0;max-width:260px;min-height:190px;} .se-addcard.card{min-height:90px;align-self:start;}
  .se-photoslot.circle{width:100%;max-width:190px;aspect-ratio:1;min-height:0;border-radius:50%;margin:0 auto 12px;} .se-photoslot.rect{aspect-ratio:4/3;min-height:0;}
  .se-avatarslot{outline:2px dashed #0F5257;outline-offset:-2px;cursor:pointer;position:relative;} .se-avatarslot:hover{background:#cfe3df !important;}
  .se-avatarslot .se-cam{position:absolute;right:6%;bottom:6%;width:34px;height:34px;border-radius:50%;background:#0F5257;color:#fff;border:2px solid #fff;display:flex;align-items:center;justify-content:center;} .se-avatarslot .se-cam .se-i{font-size:18px;}
  #seGrid{position:fixed;z-index:99970;display:none;flex-direction:column;padding:14px;box-sizing:border-box;background:#20201a;border-radius:8px;}
  #seGrid.show{display:flex;} #seGrid .tb{display:flex;gap:8px;} #seGrid .mini{border:1.5px solid rgba(255,255,255,.5);background:rgba(255,255,255,.08);color:#fff;border-radius:6px;padding:6px 11px;font:600 .74rem Arial,sans-serif;cursor:pointer;} #seGrid .mini:hover{background:rgba(255,255,255,.2);} #seGrid .mini.danger{border-color:rgba(244,160,160,.6);color:#F4A0A0;}
  #seGrid .items{flex:1;min-height:0;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);grid-auto-rows:minmax(0,1fr);gap:10px;margin-top:12px;}
  #seGrid .slot{position:relative;border-radius:6px;overflow:hidden;background:#444 center/cover no-repeat;min-height:0;} #seGrid .slot.full,#seGrid .add.full{grid-column:1/-1;}
  #seGrid .cv{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;opacity:0;background:rgba(0,0,0,.35);cursor:pointer;transition:opacity .15s;} #seGrid .slot:hover .cv{opacity:1;} #seGrid .cv .pill{padding:5px 8px;font-size:.68rem;}
  #seGrid .slot .x{width:22px;height:22px;top:4px;right:4px;opacity:0;} #seGrid .slot:hover .x{opacity:1;} #seGrid .slot .x .se-i{font-size:13px;}
  #seGrid .add{border:1.5px dashed rgba(255,255,255,.4);border-radius:6px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;cursor:pointer;color:#D8D4C4;min-height:0;} #seGrid .add:hover{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.7);}
  .se-photoslot{display:flex;flex-direction:column;gap:4px;align-items:center;justify-content:center;width:100%;min-height:120px;margin:0 0 10px;box-sizing:border-box;border:2px dashed #0F5257;border-radius:12px;background:rgba(244,241,230,.9);color:#0F5257;font:800 .8rem Arial,sans-serif;cursor:pointer;}
  .se-photoslot:hover{background:#E6F0EE;} .se-photoslot small{font-weight:500;color:#5a5a52;}
  .se-phbtn{position:absolute;transform:translate(-50%,-50%);z-index:6;background:none;border:none;display:flex;flex-direction:column;align-items:center;gap:10px;cursor:pointer;padding:0;}
  .se-phbtn .ic{font-size:46px;color:#9a968a;} .se-phbtn .pill{background:#5a1a1a;background:var(--primary,#0F5257);color:#fff;border-radius:8px;padding:11px 20px;font:800 .88rem Arial,sans-serif;white-space:nowrap;}
  [data-se-ph]{cursor:pointer;}
  #seImgOv{position:fixed;z-index:99980;display:none;background:rgba(0,0,0,.35);align-items:center;justify-content:center;} #seImgOv.show{display:flex;}
  #seImgOv .pill,#seGrid .pill{background:#fff;color:#1C1C16;border:none;border-radius:6px;padding:8px 14px;font:700 .78rem Arial,sans-serif;display:inline-flex;align-items:center;gap:6px;cursor:pointer;} #seImgOv .pill .se-i,#seGrid .pill .se-i{font-size:16px;}
  #seImgOv .x,#seGrid .x{position:absolute;top:8px;right:8px;width:28px;height:28px;border-radius:50%;background:rgba(20,20,16,.7);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;} #seImgOv .x:hover,#seGrid .x:hover{background:#B3382C;} #seImgOv .x .se-i,#seGrid .x .se-i{font-size:16px;}
  #seImgOv.corner{background:none;pointer-events:none;align-items:flex-start;justify-content:flex-end;} #seImgOv.corner .pill{pointer-events:auto;margin:64px 58px 0 0;box-shadow:0 2px 8px rgba(0,0,0,.35);} #seImgOv.corner .x{pointer-events:auto;top:64px;right:16px;}
  #seCta{position:fixed;z-index:99985;display:none;background:#fff;color:#1C1C16;border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.3);padding:6px;min-width:230px;font-family:Arial,sans-serif;} #seCta.show{display:block;}
  #seCta h5{margin:6px 10px 6px;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#777;} #seCta button{display:flex;width:100%;align-items:center;gap:8px;background:none;border:none;border-radius:8px;padding:9px 10px;font:600 .84rem Arial,sans-serif;cursor:pointer;text-align:left;} #seCta button:hover{background:#F0F6F4;} #seCta button.on{color:#0F5257;font-weight:800;} #seCta button.rm{color:#B3261E;} #seCta .se-i{font-size:18px;color:#0F5257;} #seCta .warn{margin-left:auto;color:#d98a00;} #seCta .rm .se-i{color:#B3261E;}
  .se-ctawarn{margin-top:10px;background:rgba(255,255,255,.92);color:#5a3b00;border-radius:8px;padding:7px 11px;font:600 .76rem Arial,sans-serif;max-width:46ch;} .se-ctawarn b{color:#8a5a00;}
  .se-size{display:flex;gap:6px;flex-wrap:wrap;padding:2px 10px 8px;} .se-size button{width:auto !important;border:1.5px solid #ccc !important;border-radius:16px !important;padding:5px 11px !important;font-size:.74rem !important;} .se-size button.on{background:#0F5257 !important;color:#fff !important;border-color:#0F5257 !important;}
  [data-se-stars] button{background:none;border:none;padding:0 1px;font:inherit;font-size:1.15rem;color:var(--accent,#F2A65A);cursor:pointer;line-height:1;} [data-se-stars] button:hover{transform:scale(1.2);}
  .se-listx{display:inline-block;margin-left:8px;border:none;background:none;color:#b33;cursor:pointer;font-size:1.05rem;line-height:1;vertical-align:middle;} .se-listadd{border:1.5px dashed #999;background:none;border-radius:6px;padding:8px 14px;font:600 .8rem Arial,sans-serif;color:#555;cursor:pointer;margin-top:8px;}
  .se-newtext{min-height:1.5em;outline:1.5px dashed #bbb;outline-offset:2px;border-radius:4px;cursor:text;} .se-newtext:empty::before,[data-n]:empty::before{content:attr(data-ph);color:#999;font-style:italic;font-weight:400;}
  [data-se-fee],[data-se-fgroup],[data-n]{outline:1.5px dashed transparent;outline-offset:2px;min-width:2ch;display:inline-block;cursor:text;} [data-se-fee]:hover,[data-se-fgroup]:hover,[data-n]:hover{outline-color:#F2A65A;} [data-se-fee]:focus,[data-se-fgroup]:focus,[data-n]:focus{outline:2px solid #F2A65A;}
  tr.se-feenew td{border-top:2px dashed #0F5257 !important;} .fee-group-card.se-feenew,.fee-compact-row.se-feenew{outline:2px dashed #0F5257;outline-offset:-2px;border-radius:10px;}
  .se-rowx{position:relative;} .se-rowx > .se-listx{opacity:0;margin-left:10px;} .se-rowx:hover > .se-listx{opacity:1;}
  .se-grip{cursor:grab;} .se-grip:active{cursor:grabbing;}
  .se-gap{position:relative;height:30px;z-index:55;transition:height .12s;background:#f6f3ea;} .se-gap::before{content:'';position:absolute;left:0;right:0;top:50%;height:0;border-top:2px dashed rgba(15,82,87,.18);}
  .se-gap .se-gapbtn{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:24px;height:24px;padding:0;background:#fff;color:#0F5257;border:2px solid #0F5257;border-radius:50%;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.18);transition:transform .12s,background .12s;}
  .se-gap .se-gapbtn .se-i{font-size:17px;} .se-gap:hover .se-gapbtn{background:#0F5257;color:#fff;transform:translate(-50%,-50%) scale(1.18);} .se-gap:hover::before{border-top-color:rgba(15,82,87,.55);}
  body.se-dragging .se-gap{height:54px;background:rgba(242,166,90,.12);} body.se-dragging .se-gap::before{border-top-color:rgba(15,82,87,.35);}
  body.se-dragging .se-gap .se-gapbtn{opacity:0;pointer-events:none;} body.se-dragging .se-gap.over{background:rgba(15,82,87,.2);} body.se-dragging .se-gap.over::before{border-top-color:#0F5257;}
  body.se-dragging .se-gap::after{content:'Drop here';position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);font:700 .72rem Arial,sans-serif;color:#0F5257;opacity:.75;}
  section.se-dragsrc,.se-placeholder.se-dragsrc{opacity:.35;} #seModal.se-away{opacity:0;pointer-events:none;}
  @media(max-width:640px){#seBar .lbl{display:none;} .se-btn .lbl{display:none;} .se-btn{padding:7px 9px;}}
</style>
<div id="seToast"></div>
<div id="seModal"><div class="card"><h3 id="seMT"></h3><p id="seMP"></p><div id="seMB"></div><div class="acts" id="seMA"></div></div></div>
<div id="seBar">
  <button type="button" id="seUndo" title="Undo" <?= $__hist['canUndo'] ? '' : 'disabled' ?>><span class="se-i">undo</span><span class="lbl">Undo</span></button>
  <button type="button" id="seRedo" title="Redo" <?= $__hist['canRedo'] ? '' : 'disabled' ?>><span class="se-i">redo</span><span class="lbl">Redo</span></button>
  <span class="sep"></span>
  <div class="wrapb"><button type="button" data-pop="seCol"><span class="se-i">palette</span><span class="lbl">Colours</span></button>
    <div class="se-pop" id="seCol"><h5>Colour schemes</h5><div class="se-pal" id="sePal"></div>
      <h5 style="margin-top:12px;">Your own colours</h5>
      <div style="display:flex;gap:10px;align-items:center;font-size:.76rem;color:#555;flex-wrap:wrap;">Main <input type="color" id="seC1" value="#0F5257"> Accent <input type="color" id="seC2" value="#F2A65A"> Background <input type="color" id="seC3" value="#FBF8F2"></div>
      <button type="button" id="seCApply" style="margin-top:10px;background:#0F5257;color:#fff;justify-content:center;">Apply my colours</button></div></div>
  <div class="wrapb"><button type="button" data-pop="seFont"><span class="se-i">text_fields</span><span class="lbl">Fonts</span></button>
    <div class="se-pop" id="seFont"><h5>Font pairing</h5><select id="seFontSel"><option value="default">Somahub default</option></select></div></div>
  <button type="button" id="seAdd"><span class="se-i">add_circle</span><span class="lbl">Add section</span></button>
  <span class="sep"></span>
  <a class="se-done" href="https://somahub.top/dashboard/sections.php"><span class="se-i">check</span><span class="lbl">Done</span></a>
</div>
<input type="file" id="seFile" accept="image/*" style="display:none">
<?php if (isset($_GET['welcome'])): ?>
<style>.se-wm li{display:flex;gap:12px;align-items:flex-start;font-size:.9rem;line-height:1.4;margin-bottom:12px;} .se-wm .se-i{color:#0F5257;background:#e3f1f2;border-radius:10px;padding:7px;font-size:20px;flex:0 0 auto;} .se-wm ul{list-style:none;margin:0 0 18px;padding:0;}</style>
<div id="seWelcome" style="position:fixed;inset:0;z-index:99999;background:rgba(15,30,32,.55);display:flex;align-items:center;justify-content:center;padding:16px;">
  <div class="se-wm" style="background:#fff;color:#1C1C16;border-radius:18px;max-width:480px;width:100%;padding:26px;font-family:Arial,sans-serif;box-shadow:0 20px 60px rgba(0,0,0,.3);max-height:90vh;overflow:auto;">
    <h2 style="margin:0 0 6px;font-size:1.35rem;">Welcome to Somahub<?= !empty($viewer['name']) ? ', ' . htmlspecialchars(explode(' ', trim($viewer['name']))[0]) : '' ?>!</h2>
    <p style="margin:0 0 16px;color:#4a5a5c;font-size:.92rem;line-height:1.5;">This is your real website, and it is already live. Here is how to make it yours:</p>
    <ul>
      <li><span class="se-i">edit</span><div><strong>Click any text or photo</strong> to change it. It saves by itself.</div></li>
      <li><span class="se-i">dashboard_customize</span><div><strong>Layout</strong> (top left of each section) switches its design. The <strong>three dots</strong> (top right) move, duplicate, hide or remove it.</div></li>
      <li><span class="se-i">add_circle</span><div>The little <strong>+</strong> between sections adds a new one right there.</div></li>
      <li><span class="se-i">palette</span><div>The bar at the bottom has your <strong>colours, fonts and undo</strong>.</div></li>
      <li><span class="se-i">verified</span><div><a href="https://somahub.top/dashboard/verify.php" style="color:#0F5257;font-weight:700;">Verify your school</a> within 7 days to keep the site online.</div></li>
    </ul>
    <button type="button" id="seWelcomeBtn" style="width:100%;background:#0F5257;color:#fff;border:none;border-radius:999px;padding:13px;font-weight:800;font-size:.95rem;cursor:pointer;">Got it, let's start</button>
  </div>
</div>
<script>document.getElementById('seWelcomeBtn').onclick=function(){document.getElementById('seWelcome').remove();try{history.replaceState(null,'',location.pathname+'?edit=1');}catch(e){}};</script>
<?php endif; ?>
<script>
(function(){
  var DATA = <?= json_encode($__data, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>, TYPES = <?= json_encode($__types, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
      PAL = <?= json_encode($__pal, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>, FONTS = <?= json_encode($__fonts, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
      CTA = <?= json_encode(['opts' => $__ctaOpts, 'unmet' => (object)$__ctaUnmet], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>, CUR_PAL = <?= (int)($school['palette_id'] ?? 0) ?>, FEES = <?= json_encode($__fees, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  var OPS = '/dashboard/editor-ops-ajax.php', SAVE = '/dashboard/section-save-ajax.php';
  var body = document.body; body.classList.add('se-on');
  function $(id){ return document.getElementById(id); }
  function esc(t){ var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
  function norm(t){ return (t || '').replace(/ /g,' ').replace(/\s+/g,' ').trim(); }
  var toastT; function toast(msg, err){ var t = $('seToast'); t.textContent = msg; t.className = 'show' + (err ? ' err' : ''); clearTimeout(toastT); toastT = setTimeout(function(){ t.className = ''; }, 2200); }

  // ---------- saving ----------
  var pending = {}, timers = {};
  function post(url, fd){ return fetch(url, { method:'POST', body:fd, credentials:'same-origin' }).then(function(r){ return r.json(); }); }
  function ops(action, extra){ var fd = new FormData(); fd.append('action', action); for (var k in (extra||{})) fd.append(k, extra[k]); return post(OPS, fd); }
  function saveField(sid, field, value){
    var fd = new FormData(); fd.append('section_id', sid); fd.append(field, value);
    return post(SAVE, fd).then(function(d){ if (d.ok) { toast('Saved'); refreshHist(); } else toast((d.errors && Object.values(d.errors)[0]) || 'Could not save', true); return d; }).catch(function(){ toast('Could not save', true); });
  }
  function queue(sid, field, el){
    var k = sid + ':' + field; pending[k] = function(){ var v = el.innerText.replace(/ /g,' ').replace(/\n{3,}/g,'\n\n').trim(); delete pending[k]; return saveField(sid, field, v); };
    clearTimeout(timers[k]); timers[k] = setTimeout(function(){ if (pending[k]) pending[k](); }, 900);
  }
  function flush(){ var ps = []; Object.keys(pending).forEach(function(k){ clearTimeout(timers[k]); ps.push(pending[k]()); }); return Promise.all(ps); }
  function reload(){ flush().then(function(){ try { sessionStorage.setItem('seScroll', String(window.scrollY)); } catch(e){} location.reload(); }); }
  try { var sc = sessionStorage.getItem('seScroll'); if (sc) { sessionStorage.removeItem('seScroll'); window.addEventListener('load', function(){ window.scrollTo(0, parseInt(sc, 10) || 0); }); } } catch(e){}
  window.addEventListener('beforeunload', function(){ Object.keys(pending).forEach(function(k){ pending[k](); }); });

  // ---------- history ----------
  function setHist(d){ $('seUndo').disabled = !d.canUndo; $('seRedo').disabled = !d.canRedo; }
  function refreshHist(){ ops('status').then(setHist).catch(function(){}); }
  $('seUndo').onclick = function(){ flush().then(function(){ return ops('undo'); }).then(function(d){ if (d.ok) { toast('Undone: ' + d.label); reloadSoon(); } else toast(d.error, true); }); };
  $('seRedo').onclick = function(){ flush().then(function(){ return ops('redo'); }).then(function(d){ if (d.ok) { toast('Redone: ' + d.label); reloadSoon(); } else toast(d.error, true); }); };
  function reloadSoon(){ setTimeout(reload, 350); }

  // ---------- modal ----------
  function modal(title, text, buttons, bodyHtml){
    $('seMT').textContent = title; $('seMP').textContent = text || ''; $('seMB').innerHTML = bodyHtml || '';
    var a = $('seMA'); a.innerHTML = '';
    buttons.forEach(function(b){ var x = document.createElement('button'); x.type = 'button'; x.textContent = b.t; if (b.c) x.className = b.c; x.onclick = function(){ $('seModal').classList.remove('open'); b.f && b.f(); }; a.appendChild(x); });
    $('seModal').classList.add('open');
  }
  $('seModal').addEventListener('click', function(e){ if (e.target === this) this.classList.remove('open'); });

  // ---------- popovers ----------
  function closePops(except){ document.querySelectorAll('.se-pop.open').forEach(function(p){ if (p !== except) p.classList.remove('open'); }); }
  document.addEventListener('click', function(e){ if (!e.target.closest('.se-pop') && !e.target.closest('[data-pop]') && !e.target.closest('.se-btn')) closePops(); });
  document.querySelectorAll('#seBar [data-pop]').forEach(function(b){ b.addEventListener('click', function(e){ e.stopPropagation(); var p = $(b.dataset.pop), was = p.classList.contains('open'); closePops(); if (!was) p.classList.add('open'); }); });

  // ---------- site-wide: colours, fonts, add section ----------
  var pal = $('sePal');
  PAL.forEach(function(x){
    var b = document.createElement('button'); b.type = 'button'; if (x.id === CUR_PAL) b.className = 'on';
    b.innerHTML = '<span class="se-dots"><span style="background:' + x.p + '"></span><span style="background:' + x.a + '"></span><span style="background:' + x.b + '"></span></span>' + esc(x.n);
    b.onclick = function(){ var fd = new FormData(); fd.append('color_mode','preset'); fd.append('palette_id', x.id); flush().then(function(){ return post('/dashboard/colors-save-ajax.php', fd); }).then(function(d){ d.ok ? reload() : toast(d.error || 'Could not save colours', true); }); };
    pal.appendChild(b);
  });
  $('seCApply').onclick = function(){ var fd = new FormData(); fd.append('color_mode','custom'); fd.append('primary',$('seC1').value); fd.append('secondary',$('seC1').value); fd.append('accent',$('seC2').value); fd.append('bg',$('seC3').value);
    flush().then(function(){ return post('/dashboard/colors-save-ajax.php', fd); }).then(function(d){ d.ok ? reload() : toast(d.error || 'Could not save colours', true); }); };
  var fs = $('seFontSel'); FONTS.forEach(function(f){ var o = document.createElement('option'); o.value = f.k; o.textContent = f.l; fs.appendChild(o); });
  fs.onchange = function(){ var fd = new FormData(); fd.append('font_pair', fs.value); flush().then(function(){ return post('/dashboard/font-save-ajax.php', fd); }).then(function(d){ d.ok ? reload() : toast('Could not save font', true); }); };
  var addAfter = '';   // '' = bottom, 'first' = top, or a section id
  function startAdd(after){
    addAfter = after;
    var h = '<div class="list">' + TYPES.map(function(t){ return '<button type="button" data-t="' + t.id + '">' + esc(t.l) + (t.p ? ' <small style="color:#8a5a00">paid plan</small>' : '') + '</button>'; }).join('') + '</div>';
    modal('Add a section', addAfter === '' ? 'Click one to add it at the bottom, or drag it to the exact spot on your page.' : 'It will be added right where you clicked.', [{t:'Cancel'}], h);
    $('seMB').querySelectorAll('button').forEach(function(b){
      b.draggable = true;
      b.addEventListener('dragstart', function(e){ e.dataTransfer.setData('text/plain', 'new:' + b.dataset.t); e.dataTransfer.effectAllowed = 'copy'; body.classList.add('se-dragging'); setTimeout(function(){ $('seModal').classList.add('se-away'); }, 0); });
      b.addEventListener('dragend', endDrag);
      b.onclick = function(){ $('seModal').classList.remove('open'); addType(b.dataset.t, addAfter); };
    });
  }
  function addType(typeId, after){
    var extra = {section_type_id: typeId}; if (after !== '') extra.after_id = after;
    flush().then(function(){ return ops('add', extra); }).then(function(d){
      if (!d.ok) return toast(d.error || 'Could not add', true);
      location.href = location.pathname + '?edit=1&focus=' + d.new_id;
    });
  }
  $('seAdd').onclick = function(){ startAdd(''); };

  // ---------- fees manager ----------
  function openFees(){
    var rows = FEES.slice(), h = '<table class="se-fees"><thead><tr><th>Grade / class</th><th>Term</th><th>Amount (KES)</th><th></th></tr></thead><tbody id="seFeeBody"></tbody></table>'
      + '<button type="button" id="seFeeAdd" style="background:#fff;color:#0F5257;border:2px dashed #0F5257;border-radius:12px;padding:10px 16px;font:800 .84rem Arial,sans-serif;cursor:pointer;">+ Add a fee</button>';
    modal('Your fees', 'One row per class and term. Changes save when you leave a box. Parents see these on your site.', [{t:'Done', c:'go', f:function(){ reload(); }}], h);
    $('seModal').querySelector('.card').classList.add('wide');
    var body = $('seFeeBody');
    function row(r){
      var tr = document.createElement('tr'); tr.dataset.id = r.id || '';
      tr.innerHTML = '<td><input class="g" value="' + esc(r.grade || '').replace(/"/g,'&quot;') + '" placeholder="Grade 1"></td><td><input class="t" value="' + esc(r.term || '').replace(/"/g,'&quot;') + '" placeholder="Term 1 2026"></td><td><input class="a" inputmode="decimal" value="' + (r.amount || r.amount === 0 ? r.amount : '') + '" placeholder="15000"></td><td><button type="button" class="rm" title="Remove">&times;</button></td>';
      function save(){
        var g = tr.querySelector('.g').value.trim(), t = tr.querySelector('.t').value.trim(), a = tr.querySelector('.a').value.trim();
        if (!g || !t || !a) return;
        var fd = new FormData(); fd.append('action','save'); fd.append('grade',g); fd.append('term_label',t); fd.append('amount',a); if (tr.dataset.id) fd.append('id', tr.dataset.id);
        post('/dashboard/fee-save-ajax.php', fd).then(function(d){ if (d.ok) { toast('Saved'); if (!tr.dataset.id) tr.dataset.needsId = '1'; } else toast(d.error || 'Could not save', true); }).catch(function(){ toast('Could not save', true); });
      }
      tr.querySelectorAll('input').forEach(function(i){ i.addEventListener('change', save); });
      tr.querySelector('.rm').onclick = function(){
        if (!tr.dataset.id) { tr.remove(); return; }
        var fd = new FormData(); fd.append('action','delete'); fd.append('id', tr.dataset.id);
        post('/dashboard/fee-save-ajax.php', fd).then(function(d){ if (d.ok) { tr.remove(); toast('Removed'); } else toast(d.error || 'Could not remove', true); });
      };
      body.appendChild(tr);
    }
    rows.forEach(row);
    if (!rows.length) row({});
    $('seFeeAdd').onclick = function(){ row({}); };
  }

  // ---------- per-section chrome ----------
  function menuBtn(label, icon, fn, cls){ var b = document.createElement('button'); b.type = 'button'; if (cls) b.className = cls; b.innerHTML = '<span class="se-i">' + icon + '</span>' + esc(label); b.onclick = function(e){ e.stopPropagation(); closePops(); fn(); }; return b; }
  function doOp(action, sec, extra){ var p = {section_id: sec.id}; for (var k in (extra||{})) p[k] = extra[k]; return flush().then(function(){ return ops(action, p); }).then(function(d){ if (d.ok) reload(); else toast(d.error || 'Could not do that', true); }); }
  var dragSrc = null;
  function endDrag(){ body.classList.remove('se-dragging'); $('seModal').classList.remove('se-away'); document.querySelectorAll('.se-dragsrc').forEach(function(x){ x.classList.remove('se-dragsrc'); }); document.querySelectorAll('.se-gap.over').forEach(function(x){ x.classList.remove('over'); }); dragSrc = null; }
  function tools(sec, host){
    var wrap = document.createElement('div'); wrap.className = 'se-tools';
    var l = document.createElement('div'); l.className = 'l';
    if (sec.options.length) {
      var lb = document.createElement('button'); lb.type = 'button'; lb.className = 'se-btn'; lb.innerHTML = '<span class="se-i" style="font-size:17px">dashboard_customize</span><span class="lbl">Layout</span>';
      var lp = document.createElement('div'); lp.className = 'se-pop';
      sec.options.forEach(function(o){ var b = menuBtn(o.l, o.i, function(){ if (o.k !== sec.variant) doOp('variant', sec, {variant:o.k}); }, o.k === sec.variant ? 'on' : ''); if (o.n > 0) { var nb = document.createElement('span'); nb.className = 'need'; nb.textContent = '+' + o.n + ' photo' + (o.n > 1 ? 's' : ''); b.appendChild(nb); } lp.appendChild(b); });
      if (sec.hasSize) {
        var hh = document.createElement('h5'); hh.textContent = 'Height'; lp.appendChild(hh);
        var sz = document.createElement('div'); sz.className = 'se-size';
        [['auto','Fits content'],['half','Half page'],['full','Full page']].forEach(function(o){
          var b = document.createElement('button'); b.type = 'button'; b.textContent = o[1]; if (sec.size === o[0]) b.className = 'on';
          b.onclick = function(e){ e.stopPropagation(); var fd = new FormData(); fd.append('section_id', sec.id); fd.append('layout_variant', sec.variant); fd.append('layout_size', o[0]);
            flush().then(function(){ return post('/dashboard/section-design-save-ajax.php', fd); }).then(function(d){ if (d.ok) reload(); else toast((d.errors && Object.values(d.errors)[0]) || 'Could not change height', true); }); };
          sz.appendChild(b);
        });
        lp.appendChild(sz);
      }
      lb.onclick = function(e){ e.stopPropagation(); var was = lp.classList.contains('open'); closePops(); if (!was) lp.classList.add('open'); };
      l.appendChild(lb); l.appendChild(lp);
    }
    if (sec.locked) { var lk = document.createElement('span'); lk.className = 'se-lock'; lk.textContent = 'Paid plan: visitors cannot see this yet'; l.appendChild(lk); }
    var grip = document.createElement('button'); grip.type = 'button'; grip.className = 'se-btn round se-grip'; grip.title = 'Drag to move this section'; grip.draggable = true;
    grip.innerHTML = '<span class="se-i">drag_indicator</span>'; l.appendChild(grip);
    grip.addEventListener('dragstart', function(e){ e.dataTransfer.setData('text/plain', 'sec:' + sec.id); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setDragImage(grip, 18, 18); } catch(x){} dragSrc = host; host.classList.add('se-dragsrc'); body.classList.add('se-dragging'); closePops(); });
    grip.addEventListener('dragend', endDrag);
    var r = document.createElement('div'); r.className = 'r';
    var rb = document.createElement('button'); rb.type = 'button'; rb.className = 'se-btn round'; rb.title = 'Section options'; rb.innerHTML = '<span class="se-i">more_vert</span>';
    var rp = document.createElement('div'); rp.className = 'se-pop';
    rp.appendChild(menuBtn('Move up', 'arrow_upward', function(){ doOp('move', sec, {dir:'up'}); }));
    rp.appendChild(menuBtn('Move down', 'arrow_downward', function(){ doOp('move', sec, {dir:'down'}); }));
    rp.appendChild(menuBtn('Duplicate', 'content_copy', function(){ doOp('duplicate', sec); }));
    rp.appendChild(menuBtn(sec.visible ? 'Hide from site' : 'Show on site', sec.visible ? 'visibility_off' : 'visibility', function(){ doOp('toggle', sec); }));
    rp.appendChild(menuBtn('Edit all details', 'tune', function(){ flush().then(function(){ location.href = '/dashboard/' + sec.editFile + '?id=' + sec.id; }); }));
    rp.appendChild(document.createElement('hr'));
    rp.appendChild(menuBtn('Remove section', 'delete', function(){ modal('Remove "' + sec.label + '"?', 'It is taken off your site. You can bring it back with Undo.', [{t:'Keep it'}, {t:'Remove', c:'bad', f:function(){ doOp('remove', sec); }}]); }, 'danger'));
    rb.onclick = function(e){ e.stopPropagation(); var was = rp.classList.contains('open'); closePops(); if (!was) rp.classList.add('open'); };
    r.appendChild(rb); r.appendChild(rp);
    wrap.appendChild(l); wrap.appendChild(r); host.insertBefore(wrap, host.firstChild);
  }

  // ---------- bind sections to the page ----------
  var byId = {}; DATA.forEach(function(s){ byId[s.id] = s; });
  function targetFor(marker){
    var n = marker.nextElementSibling;
    while (n && !(n.classList && n.classList.contains('se-marker'))) {
      if (n.tagName === 'SECTION') return n;
      n = n.nextElementSibling;
    }
    return null;
  }
  function bindPh(sec, root){
    root.querySelectorAll('img[src*="#ph="], [style*="#ph="]').forEach(function(el){
      var m = /#ph=([a-z0-9_]+)/.exec(el.getAttribute('src') || el.getAttribute('style') || ''); if (!m) return;
      var field = m[1], img = el.tagName === 'IMG'; el.setAttribute('data-se-ph', field); el.dataset.seSid = sec.id;
      var b = document.createElement('button'); b.type = 'button'; b.className = 'se-phbtn'; b.dataset.seSid = sec.id;
      b.innerHTML = '<span class="se-i ic">add_photo_alternate</span><span class="pill">Upload photo(s)</span>';
      b.onclick = function(e){ e.preventDefault(); e.stopPropagation(); if (isMulti(sec)) bulkPick(sec); else pickPhoto(sec.id, field); };
      var host = img ? el.parentElement : el; if (getComputedStyle(host).position === 'static') host.style.position = 'relative';
      host.appendChild(b);
      function place(){ if (img) { b.style.left = (el.offsetLeft + el.offsetWidth / 2) + 'px'; b.style.top = (el.offsetTop + el.offsetHeight / 2) + 'px'; } else { b.style.left = '50%'; b.style.top = '86px'; } }
      place(); window.addEventListener('load', place); window.addEventListener('resize', place); if (img) el.addEventListener('load', place);
    });
  }
  function bindList(sec, root){
    if (!sec.text.some(function(p){ return p[0] === 'list_items'; })) return;
    var items = root.querySelectorAll('.about-list li, .about-timeline-item'); if (!items.length) return;
    var parent = items[0].parentElement;
    function itemText(li){ var t = ''; li.childNodes.forEach(function(n){ if (!(n.classList && n.classList.contains('se-listx'))) t += n.textContent; }); return t.replace(/\s+/g, ' ').trim(); }
    function save(){ var fd = new FormData(); fd.append('section_id', sec.id); fd.append('list_items', Array.prototype.map.call(parent.children, itemText).filter(Boolean).join('\n')); post(SAVE, fd).then(function(d){ toast(d.ok ? 'Saved' : 'Could not save', !d.ok); }); }
    function wire(li){
      li.contentEditable = 'plaintext-only'; if (li.contentEditable !== 'plaintext-only') li.contentEditable = 'true';
      li.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); li.blur(); } });
      li.addEventListener('blur', save);
      var x = document.createElement('button'); x.type = 'button'; x.className = 'se-listx'; x.contentEditable = 'false'; x.title = 'Remove'; x.innerHTML = '&times;';
      x.onclick = function(e){ e.preventDefault(); e.stopPropagation(); li.remove(); save(); }; li.appendChild(x);
    }
    Array.prototype.forEach.call(items, wire);
    var add = document.createElement('button'); add.type = 'button'; add.className = 'se-listadd'; add.textContent = '+ Add item';
    add.onclick = function(e){ e.stopPropagation(); var last = parent.lastElementChild, li = last.cloneNode(false); li.className = last.className; parent.appendChild(li); wire(li); li.focus(); };
    parent.parentElement.insertBefore(add, parent.nextSibling);
  }
  function bindFees(sec, root){
    if (sec.key !== 'fees') return;
    var FEE = '/dashboard/fee-save-ajax.php';
    function done(d, fail){ if (d.ok) setTimeout(reload, 250); else { toast(d.error || 'Could not save', true); if (fail) fail(); } }
    function edit(el){ el.contentEditable = 'plaintext-only'; if (el.contentEditable !== 'plaintext-only') el.contentEditable = 'true'; el.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); el.blur(); } }); el.addEventListener('paste', function(e){ e.preventDefault(); document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text').replace(/\s+/g, ' ')); }); }
    root.querySelectorAll('[data-se-fee]').forEach(function(el){
      edit(el); var disp = el.textContent, ff = el.dataset.ff;
      if (ff === 'amount') el.addEventListener('focus', function(){ el.textContent = el.dataset.a; });
      el.addEventListener('blur', function(){
        var v = {grade: el.dataset.g, term: el.dataset.t, amount: el.dataset.a}, now = el.textContent.trim();
        var same = ff === 'amount' ? parseFloat(now.replace(/[^0-9.]/g, '')) === parseFloat(el.dataset.a) : now === v[ff];
        if (same || !now) { el.textContent = disp; return; }
        v[ff] = now; var fd = new FormData(); fd.append('action', 'save'); fd.append('id', el.dataset.seFee); fd.append('grade', v.grade); fd.append('term_label', v.term); fd.append('amount', v.amount);
        post(FEE, fd).then(function(d){ done(d, function(){ el.textContent = disp; }); }).catch(function(){ toast('Could not save', true); });
      });
      var row = el.closest('tr, .fee-compact-row, .fee-group-row');
      if (row && !row.querySelector(':scope > .se-listx') && !row.querySelector(':scope td > .se-listx') && el.dataset.ff === 'amount') {
        var x = document.createElement('button'); x.type = 'button'; x.className = 'se-listx'; x.contentEditable = 'false'; x.title = 'Remove this fee'; x.innerHTML = '&times;';
        x.onclick = function(e){ e.preventDefault(); e.stopPropagation(); if (!window.confirm('Remove this fee?')) return; var fd = new FormData(); fd.append('action', 'delete'); fd.append('id', el.dataset.seFee); post(FEE, fd).then(done); };
        (row.tagName === 'TR' ? row.lastElementChild : row).appendChild(x); row.classList.add('se-rowx'); if (row.tagName === 'TR') row.lastElementChild.classList.add('se-rowx');
      }
    });
    root.querySelectorAll('[data-se-fgroup]').forEach(function(h){
      edit(h); h.addEventListener('blur', function(){ var to = h.textContent.trim(), from = h.dataset.orig; if (!to) { h.textContent = from; return; } if (to === from) return;
        var fd = new FormData(); fd.append('action', 'rename_group'); fd.append('by', h.dataset.seFgroup); fd.append('from', from); fd.append('to', to); post(FEE, fd).then(function(d){ done(d, function(){ h.textContent = from; }); }); });
    });
    // the blank row at the end of the fees is where the next fee is typed
    var host = root.querySelector('[data-se-feetable]'); if (!host) return;
    var kind = host.dataset.seFeetable, ph = {g: 'Grade', t: 'Term', a: 'Amount'};
    function cell(tag, k){ var e = document.createElement(tag); e.setAttribute('data-n', k); e.setAttribute('data-ph', ph[k]); edit(e); return e; }
    var rowEl;
    if (kind === 'table') { rowEl = document.createElement('tr'); ['g','t','a'].forEach(function(k){ var td = document.createElement('td'); td.appendChild(cell('span', k)); rowEl.appendChild(td); }); (host.tBodies[0] || host).appendChild(rowEl); }
    else if (kind === 'compact_list') { rowEl = document.createElement('div'); rowEl.className = 'fee-compact-row'; var sp = document.createElement('span'); sp.appendChild(cell('span','g')); sp.appendChild(document.createTextNode(' \u2014 ')); sp.appendChild(cell('span','t')); var st = document.createElement('strong'); st.appendChild(document.createTextNode('KSh ')); st.appendChild(cell('span','a')); rowEl.appendChild(sp); rowEl.appendChild(st); host.appendChild(rowEl); }
    else { var first = kind === 'by_grade' ? 'g' : 't', second = kind === 'by_grade' ? 't' : 'g'; rowEl = document.createElement('div'); rowEl.className = 'fee-group-card'; var h3 = document.createElement('h3'); h3.appendChild(cell('span', first)); rowEl.appendChild(h3);
      var r2 = document.createElement('div'); r2.className = 'fee-group-row'; var s2 = document.createElement('span'); s2.appendChild(cell('span', second)); var s3 = document.createElement('strong'); s3.appendChild(document.createTextNode('KSh ')); s3.appendChild(cell('span','a')); r2.appendChild(s2); r2.appendChild(s3); rowEl.appendChild(r2); host.appendChild(rowEl); }
    rowEl.classList.add('se-feenew');
    rowEl.addEventListener('focusout', function(){
      setTimeout(function(){
        if (rowEl.contains(document.activeElement)) return;
        var v = {}; rowEl.querySelectorAll('[data-n]').forEach(function(e){ v[e.getAttribute('data-n')] = e.textContent.trim(); });
        if (!v.g || !v.t || !v.a) return;
        var fd = new FormData(); fd.append('action', 'save'); fd.append('grade', v.g); fd.append('term_label', v.t); fd.append('amount', v.a); post(FEE, fd).then(done).catch(function(){ toast('Could not save', true); });
      }, 80);
    });
  }
  function bindText(sec, root){
    root.querySelectorAll('[data-countup-target]').forEach(function(el){ el.textContent = (el.getAttribute('data-countup-target') || '') + (el.getAttribute('data-countup-suffix') || ''); });
    var claimed = new Set();
    var all = Array.prototype.slice.call(root.querySelectorAll('*')).filter(function(el){ return !el.closest('.se-tools,form,script,style'); });
    sec.text.forEach(function(pair){
      var f = pair[0], want = norm(pair[1]); if (!want) return;
      var multi = /\n/.test(pair[1]);
      var hit = null;
      for (var i = 0; i < all.length; i++) {
        var el = all[i]; if (claimed.has(el)) continue;
        if (norm(el.innerText || el.textContent) !== want) continue;
        // deepest element carrying exactly this text
        var deeper = Array.prototype.some.call(el.children, function(ch){ return norm(ch.innerText || ch.textContent) === want; });
        if (deeper) continue;
        hit = el; break;
      }
      if (!hit) return;
      claimed.add(hit); hit.dataset.seSid = sec.id; if (pair[2]) hit.setAttribute('data-color-field', pair[2]); hit.setAttribute('data-se-edit', f); hit.contentEditable = 'plaintext-only'; if (hit.contentEditable !== 'plaintext-only') hit.contentEditable = 'true'; hit.spellcheck = true;
      hit.addEventListener('input', function(){ queue(sec.id, f, hit); });
      hit.addEventListener('blur', function(){ var k = sec.id + ':' + f; if (pending[k]) { clearTimeout(timers[k]); pending[k](); } });
      hit.addEventListener('keydown', function(e){
        if (e.key === 'Enter' && !multi) { e.preventDefault(); hit.blur(); }
        if (e.key === ' ' && hit.tagName === 'SUMMARY') { e.preventDefault(); document.execCommand('insertText', false, ' '); }
      });
      hit.addEventListener('paste', function(e){ e.preventDefault(); var t = (e.clipboardData || window.clipboardData).getData('text'); document.execCommand('insertText', false, multi ? t : t.replace(/\s*\n\s*/g,' ')); });
    });
    sec.imgs.forEach(function(pair){
      var f = pair[0], path = pair[1];
      var els = root.querySelectorAll('img[src*="' + path.replace(/"/g,'') + '"], [style*="' + path.replace(/"/g,'') + '"]');
      els.forEach(function(im){ im.setAttribute('data-se-img', f); im.dataset.seSid = sec.id; im.title = 'Click to replace this photo'; });
    });
  }
  // accordions stay open while editing, so every answer is reachable
  document.addEventListener('click', function(e){ if (e.target.closest('summary')) e.preventDefault(); }, true);
  document.querySelectorAll('.se-marker').forEach(function(m){
    var sec = byId[parseInt(m.dataset.sid, 10)]; if (!sec) return;
    var el = targetFor(m);
    if (el) {
      if (!sec.visible) el.classList.add('se-hidden');
      tools(sec, el); if (!sec.framed) { bindText(sec, el); bindPh(sec, el); bindList(sec, el); bindFees(sec, el); el.querySelectorAll('details').forEach(function(d){ d.open = true; }); }
    } else {
      var ph = document.createElement('div'); ph.className = 'se-placeholder';
      ph.innerHTML = '<span class="se-i">' + (sec.visible ? 'edit_note' : 'visibility_off') + '</span><span style="flex:1;padding-left:4px"><b>' + esc(sec.label) + '</b> &mdash; ' + (sec.visible ? 'nothing to show yet. Use the menu to fill it in.' : 'hidden from your site.') + '</span>';
      m.parentNode.insertBefore(ph, m.nextSibling);
      tools(sec, ph);
    }
  });

  // ---------- gaps between sections: "+ add here" and drop targets ----------
  var units = [];
  document.querySelectorAll('.se-marker').forEach(function(m){
    var sec = byId[parseInt(m.dataset.sid, 10)]; if (!sec) return;
    var el = targetFor(m) || m.nextElementSibling; if (el && el.classList && el.classList.contains('se-placeholder') === false && el.tagName !== 'SECTION') el = null;
    units.push({sec: sec, el: el});
  });
  // placeholders were inserted right after their markers, so recompute their element
  document.querySelectorAll('.se-marker').forEach(function(m, i){ var n = m.nextElementSibling; if (n && n.classList.contains('se-placeholder') && units[i]) units[i].el = n; });
  function makeGap(afterId){
    var g = document.createElement('div'); g.className = 'se-gap'; g.dataset.after = afterId;
    var b = document.createElement('button'); b.type = 'button'; b.className = 'se-gapbtn'; b.title = 'Add a section here'; b.innerHTML = '<span class="se-i">add</span>';
    b.onclick = function(e){ e.stopPropagation(); startAdd(afterId); }; g.appendChild(b);
    g.addEventListener('dragover', function(e){ e.preventDefault(); e.dataTransfer.dropEffect = dragSrc ? 'move' : 'copy'; g.classList.add('over'); });
    g.addEventListener('dragleave', function(){ g.classList.remove('over'); });
    g.addEventListener('drop', function(e){
      e.preventDefault(); g.classList.remove('over');
      var t = e.dataTransfer.getData('text/plain') || '';
      endDrag();
      if (t.indexOf('new:') === 0) { $('seModal').classList.remove('open'); addType(t.slice(4), afterId); return; }
      if (t.indexOf('sec:') === 0) {
        var id = parseInt(t.slice(4), 10);
        var order = units.map(function(u){ return u.sec.id; }).filter(function(x){ return x !== id; });
        var at = afterId === 'first' ? 0 : order.indexOf(parseInt(afterId, 10)) + 1;
        order.splice(at, 0, id);
        flush().then(function(){ return ops('reorder', {ids: JSON.stringify(order)}); }).then(function(d){ d.ok ? reload() : toast(d.error || 'Could not move', true); });
      }
    });
    return g;
  }
  if (units.length) {
    var firstEl = units[0].el; if (firstEl) firstEl.parentNode.insertBefore(makeGap('first'), firstEl);
    units.forEach(function(u){ if (u.el) u.el.parentNode.insertBefore(makeGap(String(u.sec.id)), u.el.nextSibling); });
  }
  // scroll while dragging near the top or bottom edge, so long pages are easy to rearrange
  document.addEventListener('dragover', function(e){ if (!body.classList.contains('se-dragging')) return; if (e.clientY < 90) window.scrollBy(0, -22); else if (e.clientY > window.innerHeight - 90) window.scrollBy(0, 22); });

  // ---------- repeatable items: per-item tools + "Add" button ----------
  function lca(els){ var a = els[0]; while (a && !els.every(function(e){ return a.contains(e); })) a = a.parentElement; return a; }
  function itemPhotos(sec, slot){ return null; }
  function bindItems(sec, root){
    var it = sec.item; if (!it) return;
    var rx = new RegExp(it.rx), slots = {};
    root.querySelectorAll('[data-se-edit],[data-se-img]').forEach(function(el){
      var f = el.getAttribute('data-se-edit') || el.getAttribute('data-se-img'); var m = rx.exec(f || ''); if (!m) return;
      (slots[m[1]] = slots[m[1]] || []).push(el);
    });
    var keys = Object.keys(slots).map(Number).sort(function(a, b){ return a - b; });
    var wrapEl = root.querySelector(':scope > .wrap, :scope > div') || root;
    keys.forEach(function(k, idx){
      var els = slots[k], c = lca(els) || els[0];
      while (c.parentElement && c.parentElement !== root && c.parentElement !== wrapEl && c.parentElement.offsetWidth <= c.offsetWidth * 1.35 + 24 && !keys.some(function(o){ return o !== k && slots[o].some(function(e){ return c.parentElement.contains(e); }); })) c = c.parentElement;
      if (c === root || c === wrapEl) return;
      sec._last = c;
      c.classList.add('se-item');
      var bar = document.createElement('div'); bar.className = 'se-itemtools';
      function btn(icon, title, cls, fn, dis){ var b = document.createElement('button'); b.type = 'button'; b.title = title; if (cls) b.className = cls; b.disabled = !!dis; b.innerHTML = '<span class="se-i">' + icon + '</span>'; b.onclick = function(e){ e.preventDefault(); e.stopPropagation(); fn(); }; bar.appendChild(b); }
      if (keys.length > 1) {
        btn('arrow_back', 'Move earlier', '', function(){ moveItem(sec, k, 'up'); }, idx === 0);
        btn('arrow_forward', 'Move later', '', function(){ moveItem(sec, k, 'down'); }, idx === keys.length - 1);
      }
      if (it.photoField && !els.some(function(e){ return e.hasAttribute('data-se-img'); }) && !it.gallery) {
        var up = function(e){ e.preventDefault(); e.stopPropagation(); pickPhoto(sec.id, it.photoField + '_' + k); };
        var av = c.querySelector('.staff-avatar');
        if (av) {
          av.classList.add('se-avatarslot'); av.title = 'Click to add a photo'; av.onclick = up;
          var cam = document.createElement('span'); cam.className = 'se-cam'; cam.innerHTML = '<span class="se-i">add_a_photo</span>'; av.appendChild(cam);
        } else {
          var ps = document.createElement('button'); ps.type = 'button'; ps.className = 'se-photoslot ' + (sec.key === 'staff' ? 'circle' : 'rect');
          ps.innerHTML = '<span class="se-i" style="font-size:26px">add_a_photo</span>' + (sec.key === 'staff' ? '' : '<small>Photo</small>');
          ps.title = 'Click to add a photo'; ps.onclick = up; c.insertBefore(ps, c.firstChild);
        }
      }
      btn('delete', 'Remove this ' + it.label.toLowerCase(), 'del', function(){ itemOp('item_remove', sec, {slot: k}, 'Removed. Undo brings it back.'); });
      c.appendChild(bar);
    });
  }
  function moveItem(sec, slot, dir){
    var fd = new FormData(); fd.append('section_id', sec.id); fd.append('slot', slot); fd.append('dir', dir);
    flush().then(function(){ return post('/dashboard/section-item-move-ajax.php', fd); }).then(function(d){ d.ok ? reload() : toast(d.error || 'Could not move', true); });
  }
  function itemOp(action, sec, extra, msg){
    flush().then(function(){ var p = {section_id: sec.id}; for (var k in (extra||{})) p[k] = extra[k]; return ops(action, p); })
      .then(function(d){ if (d.ok) { if (msg) toast(msg); setTimeout(reload, msg ? 500 : 0); } else toast(d.error || 'Could not do that', true); });
  }
  function addButton(sec, mode){
    var it = sec.item; if (!it || !it.canAdd) return null;
    var left = it.max - it.count; if (left <= 0) return null;
    var w = document.createElement('div'); w.className = 'se-addcard ' + (mode || 'first');
    var b = document.createElement('button'); b.type = 'button';
    var cnt = mode === 'inline' ? '' : ' <small>' + it.count + ' of ' + it.max + '</small>';
    if (it.gallery) {
      b.innerHTML = '<span class="se-i">add_photo_alternate</span>' + (it.count ? 'Add photos' : 'Add your first photos') + cnt;
      b.onclick = function(e){ e.stopPropagation(); galleryPick(sec); };
    } else {
      b.innerHTML = '<span class="se-i">add_circle</span>' + (it.count ? 'Add ' : 'Add your first ') + esc(it.label.toLowerCase()) + cnt;
      b.onclick = function(e){ e.stopPropagation(); itemOp('item_add', sec, {}, ''); };
    }
    w.appendChild(b); return w;
  }
  var galTarget = null;
  function galleryPick(sec){ galTarget = sec; var f = $('seFile'); f.multiple = true; f.click(); }

  units.forEach(function(u){
    if (u.sec.framed) return;
    if (!u.el || !u.sec.fill || !u.sec.fill.length || u.el.classList.contains('se-placeholder')) return;
    var head = u.el.querySelector('.section-head'), form = u.el.querySelector('.enroll-inline-form'), wrapEl = u.el.querySelector(':scope > .wrap') || u.el;
    u.sec.fill.slice(0, 5).forEach(function(pair){
      var f = pair[0], p = document.createElement('p'); p.className = 'se-newtext'; p.setAttribute('data-ph', pair[1] + ' \u2013 click to write');
      p.setAttribute('data-se-edit', f); p.dataset.seSid = u.sec.id; p.contentEditable = 'plaintext-only'; if (p.contentEditable !== 'plaintext-only') p.contentEditable = 'true';
      if (form) form.insertBefore(p, form.firstChild); else if (head && /intro|caption|subheading|body|headline|subtext/.test(f)) head.appendChild(p); else wrapEl.appendChild(p);
      p.addEventListener('input', function(){ queue(u.sec.id, f, p); });
      p.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); p.blur(); } });
      p.addEventListener('blur', function(){ var k = u.sec.id + ':' + f; if (pending[k]) { clearTimeout(timers[k]); pending[k](); } if (p.textContent.trim()) flush().then(function(){ setTimeout(reload, 300); }); });
    });
  });
  units.forEach(function(u){
    if (u.sec.framed) return;
    if (!u.el || !u.sec.item) return;
    if (u.el.classList.contains('se-placeholder')) { var ab = addButton(u.sec, 'inline'); if (ab) { ab.style.marginLeft = 'auto'; u.el.appendChild(ab); } return; }
    bindItems(u.sec, u.el);
    var last = u.sec._last;
    if (last && last.parentElement && u.sec.item.count > 0) {
      var add = addButton(u.sec, 'card');
      if (add) { var h = last.offsetHeight; if (h) { add.style.height = Math.max(70, Math.min(h, 460)) + 'px'; add.style.minHeight = '0'; } last.parentElement.insertBefore(add, last.nextSibling); }
    } else {
      var emptyHost = u.el.querySelector('[data-se-empty]');
      if (emptyHost && !u.sec.item.count) {
        var ec = addButton(u.sec, 'card');
        if (ec) { var hts = {staff: 230, testimonials: 190, blog: 150, faq: 64, stats: 110, gallery: 170}; ec.style.height = (hts[u.sec.key] || 170) + 'px'; ec.style.minHeight = '0'; if (u.sec.key === 'stats') ec.style.minWidth = '220px'; emptyHost.appendChild(ec); return; }
      }
      var first = addButton(u.sec, 'first');
      if (first) {
        var ref = u.sec.item.count > 0 ? Array.prototype.filter.call(u.el.querySelectorAll('.wrap img'), function(x){ return x.offsetHeight > 40; })[0] : null;
        if (ref) { first.style.height = ref.offsetHeight + 'px'; first.style.minHeight = '0'; first.style.maxWidth = ref.offsetWidth + 'px'; first.style.margin = '10px 0'; }
        (u.el.querySelector(':scope > .wrap') || u.el).appendChild(first);
      }
    }
  });

  // photo slots: shown wherever the chosen layout needs a photo that has not been added yet
  function slotLabel(f, i){ return i === 0 ? 'Photo' : 'Photo ' + (i + 1); }
  DATA.forEach(function(sec){
    if (sec.framed || !sec.missing || !sec.missing.length) return;
    var u = null; units.forEach(function(x){ if (x.sec.id === sec.id) u = x; });
    if (!u || !u.el || u.el.querySelector('[data-se-ph],.se-addcard')) return;
    var box = document.createElement('div'); box.className = 'se-photos';
    box.innerHTML = '<b>Photo needed for this layout</b><p>' + esc(sec.needLabel || 'This layout') + ' shows photos. Until you add ' + (sec.missing.length > 1 ? 'them' : 'it') + ', your visitors see the default layout instead.</p>';
    var row = document.createElement('div'); row.className = 'se-slots';
    sec.missing.forEach(function(f, i){
      var t = document.createElement('button'); t.type = 'button'; t.className = 'se-slot';
      t.innerHTML = '<span class="se-i">add_a_photo</span>' + esc(slotLabel(f, i)) + '<small style="font-weight:500;color:#5a5a52">Click to upload</small>';
      t.onclick = function(e){ e.stopPropagation(); pickPhoto(sec.id, f); };
      row.appendChild(t);
    });
    box.appendChild(row);
    var tl = u.el.querySelector(':scope > .se-tools');
    u.el.insertBefore(box, tl ? tl.nextSibling : u.el.firstChild);
  });

  // after adding a section or a field: scroll to it, flash it, and for a new text field select its text ready to type over
  try {
    var qs = new URLSearchParams(location.search), fid = parseInt(qs.get('focus') || '0', 10);
    var fillInfo = sessionStorage.getItem('seFill'); if (fillInfo) sessionStorage.removeItem('seFill');
    window.addEventListener('load', function(){
      if (fid) { var u = units.filter(function(x){ return x.sec.id === fid; })[0]; if (u && u.el) { u.el.scrollIntoView({behavior:'smooth', block:'start'}); u.el.classList.add('se-flash'); } 
        history.replaceState(null, '', location.pathname + '?edit=1'); }
      if (fillInfo) { var pr = fillInfo.split('|'), ff = document.querySelector('[data-se-edit="' + pr[1] + '"]'); if (ff) { ff.scrollIntoView({block:'center'}); ff.focus(); var rg = document.createRange(); rg.selectNodeContents(ff); var sl = window.getSelection(); sl.removeAllRanges(); sl.addRange(rg); } }
    });
  } catch(e){}

  // photo controls, as in the section editors: hover a photo for a dark cover with "Replace photo" and a round x to remove it
  function removePhotos(sid, fields, msg){
    var fd = new FormData(); fd.append('section_id', sid); fields.forEach(function(f){ fd.append('remove_' + f, '1'); });
    toast('Removing...');
    post(SAVE, fd).then(function(d){ if (d.ok) { toast(msg || 'Photo removed. Undo brings it back.'); setTimeout(reload, 700); } else toast('Could not remove the photo', true); }).catch(function(){ toast('Could not remove the photo', true); });
  }
  var ov = document.createElement('div'); ov.id = 'seImgOv'; document.body.appendChild(ov);
  var imgHideT = null, imgOver = null;
  ov.innerHTML = '<button type="button" class="pill"><span class="se-i">photo_camera</span><span class="t">Replace photo</span></button><button type="button" class="x" title="Remove photo"><span class="se-i">close</span></button>';
  ov.querySelector('.pill').onclick = function(e){ e.preventDefault(); e.stopPropagation(); if (imgOver) pickPhoto(imgOver.dataset.seSid, imgOver.getAttribute('data-se-img')); };
  ov.querySelector('.x').onclick = function(e){ e.preventDefault(); e.stopPropagation(); if (!imgOver || !window.confirm('Remove this photo?')) return; ov.classList.remove('show'); removePhotos(imgOver.dataset.seSid, [imgOver.getAttribute('data-se-img')]); };
  ov.addEventListener('mouseenter', function(){ clearTimeout(imgHideT); }); ov.addEventListener('mouseleave', function(){ hideImgBar(); });
  function isMulti(sec){ return !!(sec && sec.photos && sec.photos.length > 1 && ((sec.key === 'hero' && ['split','split_cta','carousel'].indexOf(sec.variant) > -1) || (sec.key === 'about' && sec.variant === 'carousel_left'))); }
  var bulkSec = null;
  function bulkPick(sec){ bulkSec = sec; imgTarget = null; galTarget = null; var f = $('seFile'); f.multiple = true; f.click(); }

  // several photos in one box (hero, rotating About): hover shows every photo in a grid, with Replace all / Remove all
  var grid = document.createElement('div'); grid.id = 'seGrid'; document.body.appendChild(grid);
  var gridT = null;
  function hideGrid(){ clearTimeout(gridT); gridT = setTimeout(function(){ grid.classList.remove('show'); }, 300); }
  grid.addEventListener('mouseenter', function(){ clearTimeout(gridT); }); grid.addEventListener('mouseleave', hideGrid);
  function showGrid(im){
    var sec = byId[parseInt(im.dataset.seSid, 10)]; if (!isMulti(sec)) return false;
    clearTimeout(gridT); clearTimeout(imgHideT); ov.classList.remove('show');
    var host = im.closest('section') || document.body, els = host.querySelectorAll('[data-se-img],[data-se-ph]'), box = im;
    while (box.parentElement && box.parentElement !== host && !Array.prototype.every.call(els, function(x){ return box.contains(x); })) box = box.parentElement;
    var r = box.getBoundingClientRect(); if (r.width < 200) r = im.getBoundingClientRect();
    var filled = sec.photos.filter(function(p){ return p[1]; }), nextEmpty = sec.photos.filter(function(p){ return !p[1]; })[0];
    grid.innerHTML = '';
    var tb = document.createElement('div'); tb.className = 'tb';
    var ra = document.createElement('button'); ra.type = 'button'; ra.className = 'mini'; ra.textContent = 'Replace all'; ra.onclick = function(e){ e.stopPropagation(); grid.classList.remove('show'); bulkPick(sec); };
    var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'mini danger'; rm.textContent = 'Remove all'; rm.onclick = function(e){ e.stopPropagation(); if (!window.confirm('Remove all photos here?')) return; grid.classList.remove('show'); removePhotos(sec.id, sec.photos.map(function(p){ return p[0]; }), 'All photos removed. Undo brings them back.'); };
    tb.appendChild(ra); tb.appendChild(rm); grid.appendChild(tb);
    var items = document.createElement('div'); items.className = 'items'; grid.appendChild(items);
    var cells = filled.length + (nextEmpty ? 1 : 0);
    filled.forEach(function(pr, i){
      var el = host.querySelector('[data-se-img="' + pr[0] + '"]'), url = '';
      if (el) url = el.currentSrc || el.src || ((getComputedStyle(el).backgroundImage.match(/url\(["']?(.*?)["']?\)/) || [])[1]) || '';
      var t = document.createElement('div'); t.className = 'slot' + (i === filled.length - 1 && !nextEmpty && cells % 2 === 1 ? ' full' : ''); if (url) t.style.backgroundImage = 'url("' + url + '")';
      var cover = document.createElement('div'); cover.className = 'cv'; cover.onclick = function(e){ e.stopPropagation(); pickPhoto(sec.id, pr[0]); };
      cover.innerHTML = '<span class="pill"><span class="se-i">photo_camera</span>Replace photo</span>';
      var x = document.createElement('button'); x.type = 'button'; x.className = 'x'; x.title = 'Remove photo'; x.innerHTML = '<span class="se-i">close</span>';
      x.onclick = function(e){ e.stopPropagation(); if (window.confirm('Remove this photo?')) { grid.classList.remove('show'); removePhotos(sec.id, [pr[0]]); } };
      t.appendChild(cover); t.appendChild(x); items.appendChild(t);
    });
    if (nextEmpty) {
      var a = document.createElement('div'); a.className = 'add' + (cells % 2 === 1 ? ' full' : ''); a.innerHTML = '<span class="se-i" style="font-size:26px">add</span><span style="font-size:.72rem;font-weight:600">Add photo</span>';
      a.onclick = function(e){ e.stopPropagation(); pickPhoto(sec.id, nextEmpty[0]); }; items.appendChild(a);
    }
    grid.style.left = Math.max(r.left, 0) + 'px'; grid.style.top = Math.max(r.top, 0) + 'px'; grid.style.width = Math.min(r.width, window.innerWidth) + 'px';
    grid.style.height = Math.min(r.height, window.innerHeight - Math.max(r.top, 0)) + 'px';
    grid.classList.add('show'); return true;
  }
  function showImgBar(im){
    if (showGrid(im)) return;
    clearTimeout(imgHideT); imgOver = im; var r = im.getBoundingClientRect(); if (r.width < 60 || r.height < 50) return;
    var corner = im.tagName === 'SECTION' || r.height > 480 || r.width > window.innerWidth * 0.9;
    ov.classList.toggle('corner', corner);
    ov.querySelector('.t').textContent = 'Replace photo';
    var l = Math.max(r.left, 0), t = Math.max(r.top, 0), w = Math.min(r.right, window.innerWidth) - l, h = Math.min(r.bottom, window.innerHeight) - t;
    ov.style.left = l + 'px'; ov.style.top = t + 'px'; ov.style.width = w + 'px'; ov.style.height = h + 'px'; ov.classList.add('show');
  }
  function hideImgBar(){ clearTimeout(imgHideT); imgHideT = setTimeout(function(){ ov.classList.remove('show'); }, 250); }
  document.addEventListener('mouseover', function(e){
    var im = e.target.closest && e.target.closest('[data-se-img],[data-se-ph],.se-phbtn');
    if (im && (im.hasAttribute('data-se-ph') || im.classList.contains('se-phbtn'))) { showGrid(im); }
    else if (im) showImgBar(im);
    else if (e.target.closest && (e.target.closest('#seImgOv') || e.target.closest('#seGrid'))) clearTimeout(imgHideT);
    else if (ov.classList.contains('show')) hideImgBar();
  });
  window.addEventListener('scroll', function(){ ov.classList.remove('show'); grid.classList.remove('show'); }, {passive:true});

  // button destinations (hero, banner): hover-free menu on click, same choices and warnings as the section editor
  units.forEach(function(u){
    if (u.sec.framed) return;
    if (!u.el) return; var seen = {};
    u.el.querySelectorAll('[data-se-cta]').forEach(function(a){
      a.dataset.seSid = u.sec.id; var cur = a.getAttribute('data-se-cur');
      if (cur && CTA.unmet[cur] && !seen[cur]) {
        seen[cur] = 1; var w = document.createElement('div'); w.className = 'se-ctawarn'; w.innerHTML = '<b>' + esc(CTA.opts[cur]) + ':</b> ' + esc(CTA.unmet[cur]);
        var row = a.parentElement; row.parentElement.insertBefore(w, row.nextSibling);
      }
    });
  });
  var ctaPop = document.createElement('div'); ctaPop.id = 'seCta'; document.body.appendChild(ctaPop);
  document.addEventListener('mousedown', function(e){ if (!e.target.closest('#seCta') && !e.target.closest('[data-se-cta]')) ctaPop.classList.remove('show'); });
  function openCta(a){
    var field = a.getAttribute('data-se-cta'), cur = a.getAttribute('data-se-cur') || '', sid = a.dataset.seSid, slot2 = /2$/.test(field);
    function save(v){ var fd = new FormData(); fd.append('section_id', sid); fd.append(field, v); ctaPop.classList.remove('show'); flush().then(function(){ return post(SAVE, fd); }).then(function(d){ if (d.ok) reload(); else toast('Could not change the button', true); }); }
    ctaPop.innerHTML = '<h5>Button ' + (slot2 ? 2 : 1) + ' goes to</h5>';
    Object.keys(CTA.opts).forEach(function(k){
      var b = document.createElement('button'); b.type = 'button'; if (cur === k) b.className = 'on'; if (CTA.unmet[k]) b.title = CTA.unmet[k];
      b.innerHTML = '<span class="se-i">' + (cur === k ? 'check' : 'chevron_right') + '</span>' + esc(CTA.opts[k]) + (CTA.unmet[k] ? '<span class="se-i warn">warning</span>' : '');
      b.onclick = function(e){ e.stopPropagation(); save(k); }; ctaPop.appendChild(b);
    });
    if (slot2 && cur) { var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'rm'; rm.innerHTML = '<span class="se-i">close</span>Remove this button'; rm.onclick = function(e){ e.stopPropagation(); save(''); }; ctaPop.appendChild(rm); }
    var r = a.getBoundingClientRect(); ctaPop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 250)) + 'px'; ctaPop.style.top = Math.min(r.bottom + 6, window.innerHeight - 260) + 'px'; ctaPop.classList.add('show');
  }
  // testimonial stars: click a star to set the rating, click the same one again to clear it
  document.querySelectorAll('[data-se-stars]').forEach(function(box){
    var u = units.filter(function(x){ return x.el && x.el.contains(box); })[0]; if (!u) return;
    var i = box.getAttribute('data-se-stars'), r = parseInt(box.getAttribute('data-rating'), 10) || 0;
    function draw(){ box.innerHTML = ''; for (var n = 1; n <= 5; n++) (function(n){ var b = document.createElement('button'); b.type = 'button'; b.textContent = n <= r ? '\u2605' : '\u2606';
      b.onclick = function(e){ e.preventDefault(); e.stopPropagation(); r = (r === n) ? 0 : n; draw(); var fd = new FormData(); fd.append('section_id', u.sec.id); fd.append('rating_' + i, r ? String(r) : ''); post(SAVE, fd).then(function(d){ toast(d.ok ? 'Saved' : 'Could not save', !d.ok); }); };
      box.appendChild(b); })(n); }
    draw();
  });

  // each section is edited by its own section editor, shown right where the section sits on the site
  var frameLoads = 0;
  document.querySelectorAll('iframe.se-fr').forEach(function(fr){
    function fit(){ try { var d = fr.contentDocument; if (!d || !d.body) return; var h = d.body.offsetHeight; if (h && Math.abs(h - fr.offsetHeight) > 2) fr.style.height = h + 'px'; } catch (e) {} }
    fr.addEventListener('load', function(){
      fit(); try { var w = fr.contentWindow; if (w.ResizeObserver) new w.ResizeObserver(fit).observe(w.document.body); w.addEventListener('load', fit); } catch (e) {}
      setTimeout(fit, 300); setTimeout(fit, 1000); if (++frameLoads > DATA.filter(function(x){ return x.framed; }).length) refreshHist();
    });
    fr.src = fr.dataset.src + '&vh=' + window.innerHeight;
  });
  window.addEventListener('message', function(e){ if (e.origin !== location.origin) return; var m = e.data && e.data.seToast; if (m) toast(m.msg || (m.ok ? 'Saved' : 'Could not save'), !m.ok); });

  // colour pencil: one site-wide control, told which section a text belongs to
  window.somahubColorSid = function(el){ var h = el.closest('[data-se-sid]'); return h ? h.dataset.seSid : 0; };
  window.somahubColorUrl = '/dashboard/section-style-save-ajax.php';
  window.somahubShowSaved = function(ok){ toast(ok ? 'Colour saved' : 'Could not save colour', !ok); };

  // images: click to replace
  var imgTarget = null;
  function pickPhoto(sid, field){ imgTarget = {sid: sid, field: field}; galTarget = null; bulkSec = null; $('seFile').multiple = false; $('seFile').click(); }
  document.addEventListener('click', function(e){
    var ca = e.target.closest('[data-se-cta]');
    if (ca) { e.preventDefault(); e.stopPropagation(); openCta(ca); return; }
    var ph = e.target.closest('[data-se-ph]');
    if (ph && !e.target.closest('.se-tools,#seBar,#seGrid')) { e.preventDefault(); e.stopPropagation(); var psec = byId[parseInt(ph.dataset.seSid, 10)]; if (isMulti(psec)) bulkPick(psec); else pickPhoto(ph.dataset.seSid, ph.getAttribute('data-se-ph')); return; }
    var im = e.target.closest('[data-se-img]');
    if (im && !e.target.closest('.se-tools,#seBar')) { e.preventDefault(); e.stopPropagation(); pickPhoto(im.dataset.seSid, im.getAttribute('data-se-img')); return; }
    // keep links and buttons inside sections from navigating away while editing
    var a = e.target.closest('body > section a, body > section button, body > header a');
    if (a && !a.closest('.se-tools') && !a.closest('#seBar')) e.preventDefault();
  }, true);
  $('seFile').addEventListener('change', function(){
    var files = Array.prototype.slice.call(this.files || []); this.value = '';
    var multi = this.multiple; this.multiple = false;
    if (!files.length) return;
    var fd = new FormData();
    if (multi && bulkSec) {
      var fields = bulkSec.photos.map(function(p){ return p[0]; });
      fd.append('section_id', bulkSec.id);
      files.slice(0, fields.length).forEach(function(f, i){ fd.append(fields[i], f); });
      if (files.length > fields.length) toast('Only ' + fields.length + ' fit. Uploading those.');
      bulkSec = null;
    } else if (multi && galTarget) {
      var it = galTarget.item, free = it.empty.slice(0, files.length);
      if (!free.length) { toast('This gallery is full.', true); return; }
      fd.append('section_id', galTarget.id);
      free.forEach(function(slot, i){ fd.append(it.fmt.replace('{stem}', 'photo').replace('{index}', slot), files[i]); });
      if (files.length > free.length) toast('Only ' + free.length + ' fit. Uploading those.');
      galTarget = null;
    } else {
      if (!imgTarget) return;
      fd.append('section_id', imgTarget.sid); fd.append(imgTarget.field, files[0]);
    }
    toast('Uploading...');
    post(SAVE, fd).then(function(d){ if (d.ok) reload(); else toast((d.errors && Object.values(d.errors)[0]) || 'Could not upload', true); }).catch(function(){ toast('Could not upload', true); });
  });
  window.addEventListener('scroll', function(){ closePops(); }, {passive:true});
})();
</script>
<?php require_once __DIR__ . '/dashboard/_live_edit_color.php'; render_live_edit_color_tools(0, $theme); ?>
