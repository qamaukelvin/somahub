<?php
/**
 * CSRF protection for every page that loads includes/auth.php.
 *
 *  - Verifies a per-session token on every POST/PUT/PATCH/DELETE (form field
 *    `_csrf` or header `X-CSRF-Token`).
 *  - Injects the token automatically: a hidden field into every POST <form>,
 *    and a small script that adds the header to fetch()/XMLHttpRequest calls.
 *    Existing pages need no edits.
 *
 * Needs an active session (auth.php starts it before including this file).
 */
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify_request(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return;

    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $good = $_SESSION['_csrf'] ?? '';
    if ($good !== '' && is_string($sent) && hash_equals($good, $sent)) return;

    http_response_code(419);
    $wantsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'json') !== false
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'success' => false, 'error' => 'Your session expired. Refresh the page and try again.']);
    } else {
        echo '<!DOCTYPE html><meta name="viewport" content="width=device-width,initial-scale=1"><title>Session expired</title>'
           . '<body style="font-family:system-ui,sans-serif;max-width:420px;margin:15vh auto;padding:0 20px;color:#222">'
           . '<h2>Your session expired</h2><p>For your security this request was not processed. '
           . 'Go back, refresh the page, and try again.</p><p><a href="javascript:history.back()">&larr; Go back</a></p></body>';
    }
    exit;
}

function csrf_inject(string $html): string {
    if (stripos($html, '<form') === false && stripos($html, 'fetch(') === false
        && stripos($html, 'XMLHttpRequest') === false) {
        return $html;
    }
    $token = csrf_token();
    $field = '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    $html = preg_replace_callback('/<form\b[^>]*>/i', function ($m) use ($field) {
        $tag = $m[0];
        if (!preg_match('/\bmethod\s*=\s*["\']?post/i', $tag)) return $tag;
        return $tag . $field;
    }, $html);

    $js = '<script>(function(){var T=' . json_encode($token) . ';'
        . 'var f=window.fetch;if(f){window.fetch=function(u,o){o=o||{};var m=(o.method||(u&&u.method)||"GET").toUpperCase();'
        . 'if(m!=="GET"&&m!=="HEAD"){var h=new Headers(o.headers||(u&&u.headers)||{});if(!h.has("X-CSRF-Token"))h.set("X-CSRF-Token",T);o.headers=h;}return f.call(this,u,o);};}'
        . 'var x=XMLHttpRequest.prototype,op=x.open,sd=x.send;x.open=function(m){this._m=(m||"GET").toUpperCase();return op.apply(this,arguments);};'
        . 'x.send=function(){if(this._m&&this._m!=="GET"&&this._m!=="HEAD"){try{this.setRequestHeader("X-CSRF-Token",T);}catch(e){}}return sd.apply(this,arguments);};'
        . 'document.addEventListener("submit",function(e){var fm=e.target;if(fm&&fm.tagName==="FORM"&&(fm.method||"").toLowerCase()==="post"&&!fm.querySelector("input[name=_csrf]")){var i=document.createElement("input");i.type="hidden";i.name="_csrf";i.value=T;fm.appendChild(i);}},true);'
        . '})();</script>';
    $pos = strripos($html, '</body>');
    return $pos === false ? $html . $js : substr($html, 0, $pos) . $js . substr($html, $pos);
}

csrf_verify_request();
if (PHP_SAPI !== 'cli') {
    ob_start('csrf_inject');
}
