<?php
/** Simple pagination helpers. page_info(25) -> [page, perPage, offset]. */
function page_info(int $perPage = 25): array {
    $page = max(1, (int)($_GET['page'] ?? 1));
    return [$page, $perPage, ($page - 1) * $perPage];
}

/** Prev / "Page x of y" / Next links that keep the other query params. */
function pager_html(int $total, int $perPage, int $page): string {
    $pages = max(1, (int)ceil($total / $perPage));
    if ($pages <= 1) return '';
    $q = $_GET; 
    $link = function (int $p, string $label, bool $on) use ($q) {
        if (!$on) return '<span style="padding:7px 14px;color:#aaa;border:1px solid #e2e2e2;border-radius:8px;">' . $label . '</span>';
        $q['page'] = $p;
        return '<a href="?' . htmlspecialchars(http_build_query($q)) . '" style="padding:7px 14px;border:1px solid #cfd8d8;border-radius:8px;text-decoration:none;color:#0F5257;font-weight:700;">' . $label . '</a>';
    };
    return '<div style="display:flex;gap:10px;align-items:center;justify-content:center;margin:18px 0;font-size:.9rem;">'
        . $link($page - 1, '&larr; Newer', $page > 1)
        . '<span>Page ' . $page . ' of ' . $pages . ' · ' . $total . ' total</span>'
        . $link($page + 1, 'Older &rarr;', $page < $pages) . '</div>';
}
