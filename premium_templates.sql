-- Two new premium templates. Both reuse the exact same content schema and
-- dashboard editing form as every other theme — nothing about how a school
-- edits their site changes. Only the CSS differs, which is enough to
-- meaningfully restructure the layout since every section (hero, about,
-- gallery, staff, testimonials) already renders inside CSS Grid/Flexbox
-- containers with class hooks.

INSERT INTO themes (name, css_variables_json, custom_css, is_premium, is_active) VALUES

('Editorial', '{
  "primary": "#1c1610",
  "accent": "#B5482A",
  "bg": "#F5EEE1",
  "font_display": "Playfair Display",
  "font_body": "Source Sans 3"
}', '
  /* EDITORIAL — magazine-style layout. Swaps hero text/photo sides, gives
     headlines a large serif treatment, restyles About as a pulled-quote
     block, and turns the gallery into an asymmetric masonry-feel grid. */

  h1 { font-size: clamp(2.1rem, 5vw, 3.4rem); line-height: 1.05; font-weight: 700; }
  .hero { background: var(--bg); color: var(--primary); padding: 100px 24px 70px; }
  .hero-inner { grid-template-columns: 0.9fr 1.1fr; }
  .hero-inner > div:first-child { order: 2; }
  .hero-photo, .hero-mosaic { order: 1; }
  .hero-photo img { aspect-ratio: 4/5; object-fit: cover; border-radius: 2px; }
  .hero p { font-size: 1.05rem; border-left: 3px solid var(--accent); padding-left: 16px; margin-top: 18px; }

  .section-head { border-bottom: 2px solid var(--accent); padding-bottom: 14px; }
  .section-head h2 { font-size: 1.6rem; text-transform: uppercase; letter-spacing: 0.04em; }

  .about-grid { grid-template-columns: 0.85fr 1.15fr; }
  .about-photo { border-radius: 2px; }
  .about-grid p:first-of-type { font-size: 1.2rem; font-style: italic; color: var(--primary); border-left: 3px solid var(--accent); padding-left: 18px; }

  .gallery-grid { grid-template-columns: repeat(6, 1fr); grid-auto-rows: 140px; gap: 10px; }
  .gallery-grid img { border-radius: 2px; }
  .gallery-grid > *:nth-child(6n+1) { grid-column: span 3; grid-row: span 2; }
  .gallery-grid > *:nth-child(6n+4) { grid-column: span 3; }
  @media(max-width:820px){ .gallery-grid { grid-template-columns: repeat(2,1fr); } .gallery-grid > * { grid-column: span 1 !important; grid-row: span 1 !important; } }

  .staff-grid, .testimonial-grid { gap: 28px; }
  .testimonial-card { border: none; border-top: 3px solid var(--accent); border-radius: 0; padding-top: 18px; background: transparent; box-shadow: none; }
', 1, 1),

('Bold Blocks', '{
  "primary": "#0B2C4D",
  "accent": "#FF6B35",
  "bg": "#F7F9FC",
  "font_display": "Poppins",
  "font_body": "Inter"
}', '
  /* BOLD BLOCKS — high-contrast, card-driven layout. Solid color-block hero
     with an overlapping photo, everything else boxed into shadowed cards
     for a punchier, more energetic feel. */

  h1, h2, h3 { font-weight: 700; }
  .hero { background: var(--primary); padding: 70px 24px 110px; position: relative; }
  .hero-inner { align-items: start; }
  .hero h1 { color: #fff; }
  .hero p { color: rgba(255,255,255,0.85); }
  .hero-photo img, .hero-mosaic .m-main {
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.25);
    border: 5px solid #fff;
    transform: translateY(40px);
  }

  section { padding: 60px 24px; }
  .section-head h2 { display: inline-block; background: var(--accent); color: #fff; padding: 6px 16px; border-radius: 8px; font-size: 1.1rem; }

  .about-grid > div, .about-photo { border-radius: 16px; }
  .about-photo { box-shadow: 0 14px 30px rgba(11,44,77,0.12); }

  .staff-grid > *, .testimonial-card {
    background: #fff; border-radius: 16px; padding: 22px;
    box-shadow: 0 8px 24px rgba(11,44,77,0.08);
    border: none; transition: transform 0.15s ease;
  }
  .staff-grid > *:hover, .testimonial-card:hover { transform: translateY(-4px); }

  .gallery-grid { grid-template-columns: repeat(auto-fit,minmax(170px,1fr)); gap: 16px; }
  .gallery-grid img { border-radius: 14px; transition: transform 0.2s ease; }
  .gallery-grid img:hover { transform: scale(1.04); }

  .btn-primary, .plan-cta, .navcta { border-radius: 10px !important; }
', 1, 1);
