SOMAHUB — "NOT AVAILABLE" PAGE + FREE-TIER GATING BUG FIXES
Paste each file to the path shown below. No SQL needed.

WHAT YOU ASKED FOR:
  includes/feature-locked.php -> /includes/feature-locked.php (NEW)
    Shared branded page shown when a premium feature is accessed on a
    school that doesn't have it — replaces bare die() text with a proper
    styled page (Somahub branding, explanation, link back to the school's
    site). One function, reused everywhere instead of copy-pasted text.

  Wired into:
    report-card.php       -> Full Report (genuinely Premium)
    enrollment-apply.php  -> Online Enrollment (genuinely Premium)

REAL BUGS FOUND AND FIXED WHILE DOING THIS:
  Checking which features actually deserved this gate turned up three
  files gating things that are supposed to be Free-tier, per your own
  pricing page:

  results-check.php (public results lookup)
    Was blocking ALL locked schools entirely — meaning once a school's
    grace period or trial ran out, parents couldn't check results even
    though results checking is explicitly listed as Free-tier on
    pricing.php. Gate removed entirely — this file is Free-tier only.

  dashboard/fees.php (uploading/publishing fee structure)
    Same bug — Free-plan schools were blocked from even publishing their
    fee structure, which pricing.php explicitly promises for free. Gate
    removed.

  dashboard/results.php (uploading term results)
    Same bug — Free-plan schools couldn't upload results at all, making
    "term-by-term results checking" on the Free tier meaningless since
    there'd never be anything to check. Gate removed.

STILL CORRECTLY GATED (unchanged, verified correct):
  enrollment-apply.php, dashboard/attendance.php, report-card.php — all
  genuinely Premium features, all still blocked for Free-plan schools,
  now with the proper branded page instead of plain text.

WORTH CHECKING ON YOUR END:
  Since results_lookup and fees section types render fine either way in
  site.php (that gating is separate, per-section is_premium flags in the
  database), it's worth a quick look in phpMyAdmin at the section_types
  table to confirm results_lookup and fees aren't ALSO flagged
  is_premium=1 there — if they are, they'd still vanish from a Free
  school's public site nav even though the underlying pages now work
  correctly. Enrollment_form should be the one still flagged premium there.
