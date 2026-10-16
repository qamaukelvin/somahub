<?php
/**
 * One consent checkbox for every form that collects personal details.
 * Always links to the platform-wide Terms and Privacy Policy (absolute URLs,
 * so it also works on school subdomains). Server side: call consent_given()
 * before saving anything.
 */
function consent_css(): string {
    return '<style>.consent-check{display:flex !important;width:100%;box-sizing:border-box;grid-column:1/-1;gap:9px;align-items:flex-start;font-size:.82rem;line-height:1.45;font-weight:500;margin:14px 0 4px;cursor:pointer;text-align:left;}'
         . '.consent-check span{flex:1 1 auto;min-width:0;}.consent-check input{width:auto !important;margin:3px 0 0 !important;flex:0 0 auto;}'
         . '.consent-check a{color:inherit;font-weight:700;text-decoration:underline;}</style>';
}

/**
 * $schoolName: when set, the wording also says the details go to that school
 * (public forms on a school's site); empty for Somahub's own forms.
 */
function consent_checkbox(string $schoolName = '', string $name = 'agree_terms'): string {
    static $cssDone = false;
    $css = $cssDone ? '' : consent_css();
    $cssDone = true;
    $terms = 'https://somahub.top/terms.php';
    $privacy = 'https://somahub.top/privacy.php';
    $who = $schoolName !== ''
        ? ' and agree that my details are shared with ' . htmlspecialchars($schoolName) . ' to handle this request'
        : '';
    return $css . '<label class="consent-check"><input type="checkbox" name="' . htmlspecialchars($name) . '" value="1" required>'
         . '<span>I agree to the <a href="' . $terms . '" target="_blank" rel="noopener">Terms of Service</a> and '
         . '<a href="' . $privacy . '" target="_blank" rel="noopener">Privacy Policy</a>' . $who . '.</span></label>';
}

function consent_given(string $name = 'agree_terms'): bool {
    return !empty($_POST[$name]);
}

const CONSENT_ERROR = 'Please tick the box to agree to the Terms of Service and Privacy Policy.';
