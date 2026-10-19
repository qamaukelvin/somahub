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
        'id' => (int)$s['id'], 'key' => $key, 'label' => $s['label'], 'photos' => count($photoFields) > 1 ? array_map(fn($pf) => [$pf, (string)($content[$pf] ?? '')], $photoFields) : [], 'visible' => (int)$s['is_visible'], 'locked' => !empty($s['__locked']),
        'variant' => $curVariant, 'options' => $opts, 'item' => $item, 'fill' => $fill, 'missing' => $missing, 'needLabel' => $cfg['options'][$curVariant]['label'] ?? '',
        'text' => $text, 'imgs' => $imgs, 'editFile' => $__editorFiles[$key] ?? 'section-edit.php',
    ];
}
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
  #seGrid{position:fixed;z-index:99970;display:none;grid-template-columns:repeat(3,1fr);gap:10px;padding:12px;box-sizing:border-box;background:rgba(15,25,25,.72);border-radius:8px;align-content:center;}
  #seGrid.show{display:grid;} #seGrid .t{position:relative;aspect-ratio:4/3;border-radius:10px;background:#222 center/cover no-repeat;overflow:hidden;display:flex;align-items:flex-end;justify-content:center;gap:6px;padding:8px;box-sizing:border-box;}
  #seGrid .t.empty{background:rgba(255,255,255,.12);border:2px dashed #fff;align-items:center;flex-direction:column;color:#fff;font:800 .8rem Arial,sans-serif;cursor:pointer;} #seGrid .t.empty small{font-weight:500;opacity:.8;}
  #seGrid .t button{background:#1C1C16;color:#fff;border:2px solid #fff;border-radius:999px;padding:5px 10px;font:700 .72rem Arial,sans-serif;cursor:pointer;display:inline-flex;gap:4px;align-items:center;} #seGrid .t button:hover{background:#0F5257;} #seGrid .t button.del:hover{background:#B3261E;} #seGrid .se-i{font-size:15px;}
  .se-photoslot{display:flex;flex-direction:column;gap:4px;align-items:center;justify-content:center;width:100%;min-height:120px;margin:0 0 10px;box-sizing:border-box;border:2px dashed #0F5257;border-radius:12px;background:rgba(244,241,230,.9);color:#0F5257;font:800 .8rem Arial,sans-serif;cursor:pointer;}
  .se-photoslot:hover{background:#E6F0EE;} .se-photoslot small{font-weight:500;color:#5a5a52;}
  #seImgBar{position:fixed;z-index:99980;display:none;gap:6px;} #seImgBar.show{display:flex;}
  #seImgBar button{background:#1C1C16;color:#fff;border:2px solid #fff;border-radius:999px;padding:6px 12px;font:700 .74rem Arial,sans-serif;cursor:pointer;display:inline-flex;align-items:center;gap:5px;box-shadow:0 2px 8px rgba(0,0,0,.4);} #seImgBar button:hover{background:#0F5257;} #seImgBar button.del:hover{background:#B3261E;} #seImgBar .se-i{font-size:16px;}
  .se-phbtn{position:absolute;transform:translate(-50%,-50%);z-index:6;background:none;border:none;display:flex;flex-direction:column;align-items:center;gap:10px;cursor:pointer;padding:0;}
  .se-phbtn .ic{font-size:46px;color:#9a968a;} .se-phbtn .pill{background:#5a1a1a;background:var(--primary,#0F5257);color:#fff;border-radius:8px;padding:11px 20px;font:800 .88rem Arial,sans-serif;white-space:nowrap;}
  [data-se-ph]{cursor:pointer;}
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
      CUR_PAL = <?= (int)($school['palette_id'] ?? 0) ?>, FEES = <?= json_encode($__fees, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
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
    if (sec.key === 'fees') rp.appendChild(menuBtn('Manage fees', 'payments', function(){ openFees(); }));
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
      b.onclick = function(e){ e.preventDefault(); e.stopPropagation(); pickPhoto(sec.id, field); };
      var host = img ? el.parentElement : el; if (getComputedStyle(host).position === 'static') host.style.position = 'relative';
      host.appendChild(b);
      function place(){ if (img) { b.style.left = (el.offsetLeft + el.offsetWidth / 2) + 'px'; b.style.top = (el.offsetTop + el.offsetHeight / 2) + 'px'; } else { b.style.left = '50%'; b.style.top = '86px'; } }
      place(); window.addEventListener('load', place); window.addEventListener('resize', place); if (img) el.addEventListener('load', place);
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
      tools(sec, el); bindText(sec, el); bindPh(sec, el); el.querySelectorAll('details').forEach(function(d){ d.open = true; });
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
    if (!u.el || !u.sec.fill || !u.sec.fill.length || u.el.classList.contains('se-placeholder')) return;
    var row = document.createElement('div'); row.className = 'se-fill'; row.appendChild(document.createTextNode('Add to this section:'));
    u.sec.fill.slice(0, 5).forEach(function(pair){
      var b = document.createElement('button'); b.type = 'button'; b.textContent = '+ ' + pair[1];
      b.onclick = function(e){ e.stopPropagation(); flush().then(function(){ return ops('field_fill', {section_id: u.sec.id, field: pair[0]}); }).then(function(d){ if (d.ok) { try { sessionStorage.setItem('seScroll', String(window.scrollY)); sessionStorage.setItem('seFill', u.sec.id + '|' + pair[0]); } catch(x){} location.reload(); } else toast(d.error || 'Could not add', true); }); };
      row.appendChild(b);
    });
    (u.el.querySelector(':scope > .wrap') || u.el).appendChild(row);
  });
  units.forEach(function(u){
    if (!u.el || !u.sec.item) return;
    if (u.el.classList.contains('se-placeholder')) { var ab = addButton(u.sec, 'inline'); if (ab) { ab.style.marginLeft = 'auto'; u.el.appendChild(ab); } return; }
    bindItems(u.sec, u.el);
    var last = u.sec._last;
    if (last && last.parentElement && u.sec.item.count > 0) {
      var add = addButton(u.sec, 'card');
      if (add) { var h = last.offsetHeight; if (h) { add.style.height = Math.max(70, Math.min(h, 460)) + 'px'; add.style.minHeight = '0'; } last.parentElement.insertBefore(add, last.nextSibling); }
    } else {
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
    if (!sec.missing || !sec.missing.length) return;
    var u = null; units.forEach(function(x){ if (x.sec.id === sec.id) u = x; });
    if (!u || !u.el || u.el.querySelector('[data-se-ph]')) return;
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

  // photo controls: replace and remove appear over any photo you hover
  var imgBar = document.createElement('div'); imgBar.id = 'seImgBar'; document.body.appendChild(imgBar);
  var imgHideT = null, imgOver = null;
  function imgBarBtn(icon, label, cls, fn){ var b = document.createElement('button'); b.type = 'button'; if (cls) b.className = cls; b.innerHTML = '<span class="se-i">' + icon + '</span>' + label; b.onclick = function(e){ e.preventDefault(); e.stopPropagation(); fn(); }; imgBar.appendChild(b); }
  imgBarBtn('photo_camera', 'Replace', '', function(){ if (imgOver) pickPhoto(imgOver.dataset.seSid, imgOver.getAttribute('data-se-img')); });
  imgBarBtn('delete', 'Remove', 'del', function(){
    if (!imgOver) return; var fd = new FormData(); fd.append('section_id', imgOver.dataset.seSid); fd.append('remove_' + imgOver.getAttribute('data-se-img'), '1');
    imgBar.classList.remove('show'); toast('Removing...');
    post(SAVE, fd).then(function(d){ if (d.ok) { toast('Photo removed. Undo brings it back.'); setTimeout(reload, 700); } else toast('Could not remove the photo', true); }).catch(function(){ toast('Could not remove the photo', true); });
  });
  var grid = document.createElement('div'); grid.id = 'seGrid'; document.body.appendChild(grid);
  var gridT = null;
  function hideGrid(){ clearTimeout(gridT); gridT = setTimeout(function(){ grid.classList.remove('show'); }, 300); }
  grid.addEventListener('mouseenter', function(){ clearTimeout(gridT); }); grid.addEventListener('mouseleave', hideGrid);
  function showGrid(im){
    var sec = byId[parseInt(im.dataset.seSid, 10)]; if (!sec || !sec.photos || sec.photos.length < 2 || !/carousel/.test(sec.variant)) return false;
    clearTimeout(gridT); clearTimeout(imgHideT); imgBar.classList.remove('show');
    var host = im.closest('section') || document.body, els = host.querySelectorAll('[data-se-img],[data-se-ph]'), box = im;
    while (box.parentElement && box.parentElement !== host && !Array.prototype.every.call(els, function(x){ return box.contains(x); })) box = box.parentElement;
    var r = box.getBoundingClientRect(); if (r.width < 200) r = im.getBoundingClientRect();
    grid.innerHTML = '';
    sec.photos.forEach(function(pr){
      var t = document.createElement('div'), el = host.querySelector('[data-se-img="' + pr[0] + '"]');
      if (pr[1] && el) {
        var url = el.currentSrc || el.src || ((getComputedStyle(el).backgroundImage.match(/url\(["']?(.*?)["']?\)/) || [])[1]) || '';
        t.className = 't'; if (url) t.style.backgroundImage = 'url("' + url + '")';
        var rb = document.createElement('button'); rb.type = 'button'; rb.innerHTML = '<span class="se-i">photo_camera</span>Replace'; rb.onclick = function(e){ e.stopPropagation(); pickPhoto(sec.id, pr[0]); };
        var db = document.createElement('button'); db.type = 'button'; db.className = 'del'; db.innerHTML = '<span class="se-i">delete</span>Remove';
        db.onclick = function(e){ e.stopPropagation(); var fd = new FormData(); fd.append('section_id', sec.id); fd.append('remove_' + pr[0], '1'); grid.classList.remove('show'); toast('Removing...'); post(SAVE, fd).then(function(d){ if (d.ok) { toast('Photo removed. Undo brings it back.'); setTimeout(reload, 700); } else toast('Could not remove the photo', true); }); };
        t.appendChild(rb); t.appendChild(db);
      } else {
        t.className = 't empty'; t.innerHTML = '<span class="se-i" style="font-size:26px">add_a_photo</span>Add photo<small>Click to upload</small>'; t.onclick = function(e){ e.stopPropagation(); pickPhoto(sec.id, pr[0]); };
      }
      grid.appendChild(t);
    });
    grid.style.left = r.left + 'px'; grid.style.top = Math.max(r.top, 0) + 'px'; grid.style.width = r.width + 'px'; grid.style.height = Math.min(r.height, window.innerHeight - Math.max(r.top, 0)) + 'px';
    grid.classList.add('show'); return true;
  }
  function showImgBar(im){
    if (showGrid(im)) return;
    clearTimeout(imgHideT); imgOver = im; var r = im.getBoundingClientRect(); if (r.width < 60 || r.height < 50) return;
    imgBar.classList.add('show');
    var top = Math.min(r.bottom, window.innerHeight) - 46, left = Math.max(r.left, 0) + (Math.min(r.right, window.innerWidth) - Math.max(r.left, 0)) / 2 - imgBar.offsetWidth / 2;
    imgBar.style.top = Math.max(top, r.top + 6) + 'px'; imgBar.style.left = Math.max(6, left) + 'px';
  }
  function hideImgBar(){ clearTimeout(imgHideT); imgHideT = setTimeout(function(){ imgBar.classList.remove('show'); }, 350); }
  document.addEventListener('mouseover', function(e){ var im = e.target.closest && e.target.closest('[data-se-img],[data-se-ph],.se-phbtn'); if (im && im.hasAttribute('data-se-ph') || im && im.classList.contains('se-phbtn')) { showGrid(im); } else if (im) showImgBar(im); else if (e.target.closest && (e.target.closest('#seImgBar') || e.target.closest('#seGrid'))) clearTimeout(imgHideT); else if (imgBar.classList.contains('show')) hideImgBar(); });
  window.addEventListener('scroll', function(){ imgBar.classList.remove('show'); grid.classList.remove('show'); }, {passive:true});

  // colour pencil: one site-wide control, told which section a text belongs to
  window.somahubColorSid = function(el){ var h = el.closest('[data-se-sid]'); return h ? h.dataset.seSid : 0; };
  window.somahubColorUrl = '/dashboard/section-style-save-ajax.php';
  window.somahubShowSaved = function(ok){ toast(ok ? 'Colour saved' : 'Could not save colour', !ok); };

  // images: click to replace
  var imgTarget = null;
  function pickPhoto(sid, field){ imgTarget = {sid: sid, field: field}; galTarget = null; $('seFile').multiple = false; $('seFile').click(); }
  document.addEventListener('click', function(e){
    var ph = e.target.closest('[data-se-ph]');
    if (ph && !e.target.closest('.se-tools,#seBar,#seGrid')) { e.preventDefault(); e.stopPropagation(); pickPhoto(ph.dataset.seSid, ph.getAttribute('data-se-ph')); return; }
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
    if (multi && galTarget) {
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
