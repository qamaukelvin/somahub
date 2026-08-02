# Somahub — School Website Platform (PHP/MySQL)

## Folder structure
Public facing files sit at the root, alongside the panel folders:
```
/                       <- put this whole folder in htdocs (XAMPP) or your hosting's web root
  index.php             <- somahub.top homepage (portfolio, plans, contact form)
  site.php              <- renders a school's live website (?school=slug)
  results-check.php     <- public results lookup
  enrollment-apply.php  <- public enrollment form
  contact-submit.php    <- handles the homepage contact form
  .htaccess             <- subdomain routing rules
  admin/                <- YOUR platform admin panel
  dashboard/             <- SCHOOL panel (editing, enrollment, results, fees)
  config/db.php          <- database credentials (blocked from direct web access)
  includes/auth.php      <- shared login/session logic (blocked from direct web access)
  uploads/                <- school photos and result CSVs land here
```

## Running locally on XAMPP
1. Copy this whole folder into `C:\xampp\htdocs\somahub-app` (Windows) or `/Applications/XAMPP/htdocs/somahub-app` (Mac).
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open `http://localhost/phpmyadmin`, create a new database named `soma_platform`.
4. In phpMyAdmin, open the **Import** tab, import `schema.sql`, then import `seed.sql`.
5. `config/db.php` is already set to XAMPP's defaults (`root` user, empty password) — no changes needed locally.
6. Visit:
   - `http://localhost/somahub-app/index.php` — the Somahub homepage
   - `http://localhost/somahub-app/admin/login.php` — your admin panel
   - `http://localhost/somahub-app/dashboard/login.php` — school panel
   - `http://localhost/somahub-app/site.php?school=SLUG` — a school's live site (once one exists)
7. Log into `/admin`, add your first test school, then check its site with `site.php?school=theslug`.

Note: on `localhost`, subdomain style URLs (`kinangoppride.somahub.top`) won't work — use the
`?school=slug` query string locally instead. Wildcard subdomain routing only becomes relevant once
you deploy to real hosting with a real domain.

## Moving to live hosting later
1. Upload this same folder structure to your hosting's public web root (e.g. `public_html`).
2. Create the real MySQL database and user in cPanel, then update `config/db.php` with those
   credentials (comments inside the file explain what changes).
3. Import `schema.sql` then `seed.sql` via phpMyAdmin on the live server, same as locally.
4. Generate your own admin password hash and update the seed row (see comment in `seed.sql`).
5. Set up wildcard DNS (`*.somahub.top` → your hosting) so subdomain routing in `.htaccess` works.

## Flow: onboarding a new school
1. Log into `/admin`, click "Add School".
2. Fill in school name, subdomain, theme, and the owner's email/phone.
3. This creates: the school row, an owner login (temp password shown once), and 6 default
   empty sections (hero, about, academics, admissions, gallery, contact).
4. Send the login + temp password to the school directly (WhatsApp/SMS).
5. They log into `/dashboard`, fill in each section, upload photos, and their site is live at
   `site.php?school=theirslug` (or their real subdomain once wildcard DNS is set up).

## Flow: school editing their site
`/dashboard/sections.php` is the core screen — drag to reorder, click to edit, duplicate,
hide, or remove. Each section's editable fields are defined in `section_types.schema_json`
and rendered dynamically in both `section-edit.php` (the editor) and `site.php` (the live
renderer), so adding a new section type later doesn't require new PHP code for basic fields,
just a new schema_json row plus one rendering block in `site.php`.

## Flow: results checking (paid tier)
School uploads a CSV via `/dashboard/results.php`. Parsed rows land in `result_rows`.
Parents check via `results-check.php?school=slug`, requiring admission number **and** name
or date of birth together, so no one can browse by guessing sequential numbers.

## NOT yet built (next steps)
- CSRF protection on POST forms.
- Rate limiting on public forms (results check, contact form, enrollment).
- Real MIME-type validation on uploaded files (currently trusts file extension).
- Password reset flow for school owners.
- Email/SMS notification when a new enrollment application or lead comes in.
- Plan upgrade/payment page (`upgrade.php`, referenced but not built).
- Privacy policy as a live page (currently only exists as a downloadable document).

## Security notes already handled
- All dashboard queries filter by `school_id` from the session, not from user input — a school
  can never see or edit another school's data even by guessing IDs in a URL.
- Results lookup always requires two factors together (admission_no + name/DOB), never one alone.
- Passwords are hashed with `password_hash()` / verified with `password_verify()`, never stored plain.
- `config/` and `includes/` are blocked from direct browser access via `.htaccess`.
