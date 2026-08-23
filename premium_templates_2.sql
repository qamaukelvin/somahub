-- Two more premium templates, same approach as before: pure CSS via
-- custom_css, zero changes to content schema or the editing form.

INSERT INTO themes (name, css_variables_json, custom_css, is_premium, is_active) VALUES

('Minimal Mono', '{
  "primary": "#1A1A1A",
  "accent": "#5B6E5B",
  "bg": "#FFFFFF",
  "font_display": "Space Grotesk",
  "font_body": "Inter"
}', '
  /* MINIMAL MONO — understated, monochrome, generous whitespace. Thin
     hairline borders instead of shadows, restrained single accent color,
     lighter type with wide letter-spacing. Good for schools wanting a
     quiet, international-school feel rather than bright/busy. */

  body { line-height: 1.75; }
  h1, h2, h3 { font-weight: 500; letter-spacing: -0.01em; }
  section { padding: 70px 24px; }

  .hero { background: var(--bg); color: var(--primary); padding: 120px 24px 80px; }
  .hero-inner { gap: 60px; }
  .hero h1 { font-size: clamp(2rem, 4.5vw, 3rem); font-weight: 400; }
  .hero p { color: #555; font-size: 1rem; }
  .hero-photo img, .hero-mosaic .m-main { border-radius: 0; filter: grayscale(15%); }

  .section-head { border-bottom: none; margin-bottom: 48px; }
  .section-head h2 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.18em; color: #777; font-weight: 500; }

  .about-photo, .gallery-grid img { border-radius: 0; filter: grayscale(10%); }
  .about-grid { gap: 60px; }

  .staff-grid > *, .testimonial-card {
    background: transparent; box-shadow: none; border: none; border-top: 1px solid #E5E5E0;
    padding-top: 20px; border-radius: 0;
  }

  .gallery-grid { gap: 2px; }

  .btn-primary, .plan-cta {
    background: transparent !important; color: var(--primary) !important;
    border: 1px solid var(--primary) !important; border-radius: 0 !important;
    box-shadow: none !important;
  }
  .navcta { border-radius: 0 !important; }
', 1, 1),

('Vibrant Photo', '{
  "primary": "#7A2E8E",
  "accent": "#FFC93C",
  "bg": "#FFF8EE",
  "font_display": "Baloo 2",
  "font_body": "Nunito Sans"
}', '
  /* VIBRANT PHOTO — big full-bleed hero photo with text overlaid directly
     on top, playful rounded shapes, punchy warm palette. Best for schools
     with strong photography they want front and center immediately. */

  .hero { position: relative; overflow: hidden; padding: 150px 24px 100px; min-height: 420px; display: flex; align-items: center; }
  .hero::before { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(122,46,142,0.25), rgba(20,10,25,0.65)); z-index: 1; }
  .hero-photo, .hero-mosaic { position: absolute; inset: 0; z-index: 0; margin: 0; }
  .hero-photo img, .hero-mosaic img, .hero-mosaic .m-main { width: 100%; height: 100%; object-fit: cover; border-radius: 0; border: none; box-shadow: none; }
  .hero-inner { position: relative; z-index: 2; grid-template-columns: 1fr; text-align: center; max-width: 720px; }
  .hero h1 { color: #fff; font-size: clamp(2.2rem, 6vw, 3.6rem); text-shadow: 0 2px 12px rgba(0,0,0,0.3); }
  .hero p { color: #fff; font-size: 1.1rem; text-shadow: 0 1px 6px rgba(0,0,0,0.3); }

  .section-head h2 { color: var(--primary); }
  .section-head { text-align: center; margin-left: auto; margin-right: auto; }

  .about-photo, .gallery-grid img { border-radius: 24px; }
  .about-grid { align-items: center; }

  .staff-grid > *, .testimonial-card {
    border-radius: 24px; border: none; background: #fff;
    box-shadow: 0 10px 26px rgba(122,46,142,0.1);
  }

  .gallery-grid { grid-template-columns: repeat(auto-fit,minmax(160px,1fr)); gap: 18px; }
  .gallery-grid img { aspect-ratio: 1/1; }

  .btn-primary, .plan-cta, .navcta {
    background: var(--accent) !important; color: #3D2200 !important;
    border-radius: 999px !important; font-weight: 700 !important;
  }
', 1, 1);
