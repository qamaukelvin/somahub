-- ------------------------------------------------------------------
-- Icon-based nav templates, in 3 position variants, built on the real
-- Sidebar Nav concept from earlier. Uses Material Symbols Outlined
-- (Google Fonts, same @import pattern as our other custom-font
-- templates) - it supports ligatures, so writing the plain-English
-- icon name as CSS `content` renders a clean, consistent monochrome
-- icon glyph. No emoji (inconsistent across platforms/devices), no
-- HTML changes needed - nav links already carry stable, predictable
-- href="#key_name" values from site.php's real $navSequence, so every
-- icon mapping below targets an exact, real section key.
--
-- The link TEXT is visually hidden (font-size:0 on the <a>, with the
-- icon pseudo-element setting its own font-size explicitly - a
-- standard, safe CSS technique) rather than removed, so screen readers
-- and search engines still see the real label.
--
-- Safe to re-run.
-- ------------------------------------------------------------------

DELETE FROM templates WHERE name IN ('Icon Sidebar (Left)','Icon Sidebar (Right)','Icon Tab Bar (Bottom)');

INSERT INTO templates (name, custom_css, is_premium, is_active)
SELECT 'Icon Sidebar (Left)', '
  @import url(''https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined'');

  header{position:fixed;top:0;left:0;bottom:0;width:78px;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:22px 0;background:var(--primary);z-index:50;}
  body{padding-left:78px;}
  .navbar{display:flex;flex-direction:column;align-items:center;gap:30px;height:100%;}
  .brand{writing-mode:vertical-rl;transform:rotate(180deg);color:var(--bg);font-size:0.82rem;font-weight:700;letter-spacing:0.04em;margin-bottom:auto;max-height:200px;overflow:hidden;}
  nav ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:22px;align-items:center;}
  nav li{position:static;}
  nav a, nav summary{font-size:0;color:var(--bg);display:flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:10px;cursor:pointer;}
  nav a:hover, nav summary:hover{background:rgba(255,255,255,0.12);}
  nav a::before, nav summary::before{font-family:''Material Symbols Outlined'';font-size:22px;line-height:1;}
  .nav-dropdown{position:fixed;left:78px;background:var(--primary);border-radius:0 10px 10px 0;padding:10px;display:flex;flex-direction:column;gap:4px;}
  .nav-dropdown a{width:auto;height:auto;font-size:0.8rem !important;padding:8px 14px;border-radius:6px;justify-content:flex-start;}
  .nav-dropdown a::before{margin-right:8px;}
  .menu-toggle{display:none;}
  summary{list-style:none;}
  summary::-webkit-details-marker{display:none;}

  nav a[href="#about"]::before, nav summary::before{content:"info";}
  nav a[href="#academics"]::before{content:"school";}
  nav a[href="#admissions"]::before{content:"edit_document";}
  nav a[href="#staff"]::before{content:"groups";}
  nav a[href="#gallery"]::before{content:"photo_library";}
  nav a[href="#contact"]::before{content:"mail";}
  nav a[href="#testimonials"]::before{content:"format_quote";}
  nav a[href="#reviews"]::before{content:"star";}
  nav a[href="#faq"]::before{content:"help";}
  nav a[href="#stats"]::before{content:"bar_chart";}
  nav a[href="#fees"]::before{content:"payments";}
  nav a[href="#results_lookup"]::before{content:"grade";}
  nav a[href="#enrollment_form"]::before{content:"how_to_reg";}
  nav a[href="#blog"]::before{content:"article";}
  nav a[href="#cta_banner"]::before{content:"campaign";}

  @media(max-width:760px){
    header{width:100%;height:64px;flex-direction:row;justify-content:space-between;padding:0 16px;bottom:auto;}
    body{padding-left:0;padding-top:64px;}
    .navbar{flex-direction:row;}
    .brand{writing-mode:horizontal-tb;transform:none;max-height:none;margin-bottom:0;}
    nav ul{flex-direction:row;}
    .nav-dropdown{left:auto;right:0;top:64px;border-radius:0 0 10px 10px;}
  }
', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM templates WHERE name = 'Icon Sidebar (Left)');

INSERT INTO templates (name, custom_css, is_premium, is_active)
SELECT 'Icon Sidebar (Right)', '
  @import url(''https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined'');

  header{position:fixed;top:0;right:0;left:auto;bottom:0;width:78px;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:22px 0;background:var(--primary);z-index:50;}
  body{padding-right:78px;padding-left:0;}
  .navbar{display:flex;flex-direction:column;align-items:center;gap:30px;height:100%;}
  .brand{writing-mode:vertical-rl;color:var(--bg);font-size:0.82rem;font-weight:700;letter-spacing:0.04em;margin-bottom:auto;max-height:200px;overflow:hidden;}
  nav ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:22px;align-items:center;}
  nav a, nav summary{font-size:0;color:var(--bg);display:flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:10px;cursor:pointer;}
  nav a:hover, nav summary:hover{background:rgba(255,255,255,0.12);}
  nav a::before, nav summary::before{font-family:''Material Symbols Outlined'';font-size:22px;line-height:1;}
  .nav-dropdown{position:fixed;right:78px;left:auto;background:var(--primary);border-radius:10px 0 0 10px;padding:10px;display:flex;flex-direction:column;gap:4px;}
  .nav-dropdown a{width:auto;height:auto;font-size:0.8rem !important;padding:8px 14px;border-radius:6px;justify-content:flex-start;}
  .nav-dropdown a::before{margin-right:8px;}
  .menu-toggle{display:none;}
  summary{list-style:none;}
  summary::-webkit-details-marker{display:none;}

  nav a[href="#about"]::before, nav summary::before{content:"info";}
  nav a[href="#academics"]::before{content:"school";}
  nav a[href="#admissions"]::before{content:"edit_document";}
  nav a[href="#staff"]::before{content:"groups";}
  nav a[href="#gallery"]::before{content:"photo_library";}
  nav a[href="#contact"]::before{content:"mail";}
  nav a[href="#testimonials"]::before{content:"format_quote";}
  nav a[href="#reviews"]::before{content:"star";}
  nav a[href="#faq"]::before{content:"help";}
  nav a[href="#stats"]::before{content:"bar_chart";}
  nav a[href="#fees"]::before{content:"payments";}
  nav a[href="#results_lookup"]::before{content:"grade";}
  nav a[href="#enrollment_form"]::before{content:"how_to_reg";}
  nav a[href="#blog"]::before{content:"article";}
  nav a[href="#cta_banner"]::before{content:"campaign";}

  @media(max-width:760px){
    header{width:100%;height:64px;flex-direction:row;justify-content:space-between;padding:0 16px;bottom:auto;right:0;left:0;}
    body{padding-right:0;padding-top:64px;}
    .navbar{flex-direction:row;}
    .brand{writing-mode:horizontal-tb;max-height:none;margin-bottom:0;}
    nav ul{flex-direction:row;}
    .nav-dropdown{right:0;left:auto;top:64px;border-radius:0 0 10px 10px;}
  }
', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM templates WHERE name = 'Icon Sidebar (Right)');

INSERT INTO templates (name, custom_css, is_premium, is_active)
SELECT 'Icon Tab Bar (Bottom)', '
  @import url(''https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined'');

  header{position:static;background:var(--bg);border-bottom:1px solid rgba(0,0,0,0.08);}
  .navbar{display:flex;justify-content:space-between;align-items:center;padding:16px 6%;}
  .brand{color:var(--primary);font-weight:700;}
  body{padding-bottom:76px;}

  header nav{position:fixed;bottom:0;left:0;right:0;width:100%;background:var(--primary);z-index:50;box-shadow:0 -4px 16px rgba(0,0,0,0.15);}
  header nav ul{list-style:none;margin:0;padding:0;display:flex;justify-content:space-around;align-items:center;overflow-x:auto;}
  header nav li{flex:1;min-width:56px;}
  header nav a, header nav summary{font-size:0;color:var(--bg);display:flex;align-items:center;justify-content:center;flex-direction:column;padding:10px 4px;cursor:pointer;}
  header nav a::before, header nav summary::before{font-family:''Material Symbols Outlined'';font-size:22px;line-height:1;}
  header nav a::after, header nav summary::after{content:attr(data-label);font-size:0.58rem !important;margin-top:3px;font-family:var(--font-body,inherit);}
  .nav-dropdown{position:fixed;bottom:64px;left:0;right:0;background:var(--primary);padding:10px;display:flex;justify-content:center;gap:16px;}
  .nav-dropdown a{width:auto;height:auto;font-size:0.7rem !important;flex-direction:column;}
  .menu-toggle{display:none;}
  summary{list-style:none;}
  summary::-webkit-details-marker{display:none;}

  nav a[href="#about"]::before, nav summary::before{content:"info";}
  nav a[href="#academics"]::before{content:"school";}
  nav a[href="#admissions"]::before{content:"edit_document";}
  nav a[href="#staff"]::before{content:"groups";}
  nav a[href="#gallery"]::before{content:"photo_library";}
  nav a[href="#contact"]::before{content:"mail";}
  nav a[href="#testimonials"]::before{content:"format_quote";}
  nav a[href="#reviews"]::before{content:"star";}
  nav a[href="#faq"]::before{content:"help";}
  nav a[href="#stats"]::before{content:"bar_chart";}
  nav a[href="#fees"]::before{content:"payments";}
  nav a[href="#results_lookup"]::before{content:"grade";}
  nav a[href="#enrollment_form"]::before{content:"how_to_reg";}
  nav a[href="#blog"]::before{content:"article";}
  nav a[href="#cta_banner"]::before{content:"campaign";}
', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM templates WHERE name = 'Icon Tab Bar (Bottom)');

