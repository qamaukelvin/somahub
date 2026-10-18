# Somahub — School Website Platform (PHP/MySQL)

## Folder structure
Public facing files sit at the root, alongside the panel folders:
```
/                       <- put this whole folder in htdocs (XAMPP) or your hosting's web root
  get-started.php       <- signup (creates owner + school + starter site + 60-day trial)
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
1. The school signs up at `/get-started.php`: their name, phone, email, password, **role at the
   school** (head teacher, bursar, ...), school name, subdomain, county and map pin, plus a tick for
   the Terms and Privacy Policy.
2. `includes/school-provision.php` (`provision_school()`) creates the owner login, the school row, a
   **60-day trial of every feature** (plan `promo_paid`) and the **starter layout** from
   `get_starter_layout()`: hero, about, academics, admissions, enrollment form, gallery, staff,
   testimonials, FAQ, fees (hidden until a fee is added), results lookup, contact. Sections can be
   reordered, hidden or removed.
3. They land on `/dashboard/sections.php` with a first-time welcome modal.
4. The site is public straight away and must be verified within 7 days of first login
   (`VERIFICATION_GRACE_PERIOD_DAYS`) or it goes offline to the public.
5. Admin-created schools (`admin/school-new.php`, `leads-convert.php`) still seed the older six
   sections; they are not on the starter layout yet.

Signup tolerates missing optional columns, but run `sql/signup_consent_and_head_contact.sql` to store
consent time, authorised-person flag and head teacher contact.

## Flow: school editing their site
`/dashboard/sections.php` lists sections; "Add section" (top) opens a categorised picker and then the
new section's **live editor** (`section-live-edit-*.php`), where text, photos, colours and layouts are
edited in place on the real section. `dashboard/_live_editor_map.php` maps each section type to its
live editor. Site-wide fonts and colours (preset palettes or custom colours) are in the live-editor
top bar. Section content lives in `site_sections.content_json`; `site.php` renders it, and
`includes/section_variants.php` is the registry of layouts and text-colour roles.

## Dashboard navigation and notifications
`_school_portal_nav.php` is the navbar for school pages outside the main site (results check, enrollment
form, report card); `_public_nav.php` is Somahub's own public navbar (now with Blog).
`dashboard/_nav.php` is the school navbar (Material Symbols icons, active-page highlight, dropdowns,
mobile drawer). Live editors have a **Menu** dropdown in their top bar for jumping around the
dashboard. Dashboard-wide nudges (verify, add email, set password) go through
`nudge_notification()`: it keeps one unread copy, and once read it stays quiet for 24 hours instead
of reappearing on every page load.

## Public site visibility
`site.php` shows the public a "temporarily unavailable" page once an unverified school is past its
7 days. Logged-in owners and platform admins still see the real site, with a red banner saying it is
offline to the public, so a locked phone and a live laptop usually means the laptop is logged in.

## Performance and media
Uploads go through `includes/image-upload.php`: resized to 1600px max and saved as WebP (JPEG
fallback). `site.php` lazy-loads every image after the first. Admin > "Optimise images" shrinks
photos uploaded before this existed. Keep an eye on: very large galleries (consider paging), and
adding responsive `srcset` thumbnails if sites still feel slow on weak connections.

## Demo school and "Try the editor"
`admin/demo-school.php` creates demo.somahub.top. On that school only, `site.php` includes
`_demo_editor.php`: a browser-only sandbox (edit text, swap photos, colours, fonts, hide sections,
Review changes). It never sends anything to the server, so visitors cannot affect each other or the
real demo. The homepage embeds it (`?embed=1`) in the "Try it yourself" section.

## Forms
Enrollment and results lookup are inline on the school's own site (one layout each). Inline results
load via `results-check.php?fragment=1`. The standalone pages remain only as POST handlers and
fallbacks; `includes/school-slug.php` lets them work from the school's subdomain.

## Appearance
Schools choose a colour palette (or custom colours) and a font pair; layouts come from per-section
variants. The old templates framework (`templates`/`themes` tables, template pickers) is removed;
`sql/remove_templates_framework.sql` drops the leftovers once the new code is live.

## Account changes
Login email changes need the password and a confirmation link sent to the NEW address (the old
address is also notified): `dashboard/account.php` + `dashboard/confirm-email-change.php`, table in
`sql/email_change_requests.sql`. Role (job title) can be edited on the account page.

## Consent
`includes/consent.php` provides one checkbox (Terms + Privacy) used on every form that collects
personal details; always call `consent_given()` server side.

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
- Reordering of repeatable items (staff, FAQs, ...) inside live editors.
- Head teacher contact is stored but not yet shown on the verify page or admin screens.

## Security notes already handled
- All dashboard queries filter by `school_id` from the session, not from user input — a school
  can never see or edit another school's data even by guessing IDs in a URL.
- Results lookup always requires two factors together (admission_no + name/DOB), never one alone.
- Passwords are hashed with `password_hash()` / verified with `password_verify()`, never stored plain.
- `config/` and `includes/` are blocked from direct browser access via `.htaccess`.


## Security (hardening batch)
- **CSRF:** `includes/csrf.php` verifies a per-session token on every POST for pages that load `includes/auth.php`, and injects it into forms and fetch/XHR automatically. No per-form code is needed.
- **Rate limiting:** `includes/ratelimit.php` (table `rate_limits`, auto-created, fails open). Login: 8 failures per email / 25 per IP per 15 min. Signup, forgot/reset password, contact, reviews, enrollment, results and subdomain checks are limited too.
- **Sessions:** ID and CSRF token regenerate on every login.
- **Private files:** ID documents, signed agreements, results and attendance CSVs are stored in `storage/private/` (denied to the web) and streamed by `admin/private-file.php` / `dashboard/private-file.php` after a permission check. Run `admin/migrate-private-files.php` once to move existing files.
- **Web root:** `uploads/.htaccess` blocks script execution; `config/`, `includes/`, `sql/`, `vendor/`, `storage/` deny direct access; root `.htaccess` blocks `.sql`, `.md`, `.ini`, `.log`. The old top-level `.sql` files now live in `sql/_legacy_root/`.

## On-page site editor (phase 1)
- Open `https://<slug>.somahub.top/?edit=1` while signed in as the school owner/editor (button "Edit on your site" on the Sections page). Admins can use it while impersonating that school. Never indexed, never visible to the public.
- `_site_editor.php` is loaded by `site.php` in edit mode only. It matches each stored text field to the element on the real page showing it (by text), makes it editable, and saves through `dashboard/section-save-ajax.php`. Fields that are empty or not displayed verbatim cannot be edited in place; the section menu's "Edit all details" opens the classic per-section editor for those.
- Section toolbar: top-left **Layout** (from `includes/section_variants.php`), top-right **⋮** (move, duplicate, hide/show, edit all details, remove). Bottom bar: undo/redo, colours, fonts, add section, done.
- Structural actions live in `dashboard/editor-ops-ajax.php`.
- **Undo/redo:** `includes/edit-history.php` snapshots all sections plus colour/font settings after each change (tables `edit_history`, `edit_history_ptr`, auto-created, last 40 steps, same-field typing within 15s is one step). Editing endpoints opt in with one `history_track_request()` line.
