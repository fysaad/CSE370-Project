<?php
/**
 * lang.php
 * Simple bilingual (English / Bangla) text helper for KrishiDirect.
 * Included automatically by config.php, so every page that already
 * does `require_once 'config.php';` gets $LANG and t() for free.
 *
 * Usage in a page:
 *   <?= t('Log in', 'লগইন') ?>
 *
 * The chosen language is stored in $_SESSION['lang'] so it stays the
 * same as the farmer/buyer/etc. moves between pages, until they tap
 * the other toggle button.
 */

// Switch language when ?lang=en or ?lang=bn is present on any page
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'bn'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

$LANG = $_SESSION['lang'];

/**
 * t($english, $bangla) - returns whichever string matches the current
 * session language.
 */
function t($english, $bangla) {
    global $LANG;
    return $LANG === 'bn' ? $bangla : $english;
}

/**
 * lang_toggle_url($targetLang) - builds a link that switches language
 * but keeps the visitor on the current page (and keeps existing query
 * params, e.g. product_id).
 */
function lang_toggle_url($targetLang) {
    $params = $_GET;
    $params['lang'] = $targetLang;
    return '?' . http_build_query($params);
}

/**
 * lang_toggle_html() - ready-made "En | বাংলা" toggle for the navbar.
 */
function lang_toggle_html() {
    global $LANG;
    $enActive = $LANG === 'en' ? ' active' : '';
    $bnActive = $LANG === 'bn' ? ' active' : '';
    return '<span class="lang-toggle">'
         . '<a href="' . htmlspecialchars(lang_toggle_url('en')) . '" class="lang-link' . $enActive . '">En</a>'
         . '<a href="' . htmlspecialchars(lang_toggle_url('bn')) . '" class="lang-link' . $bnActive . '">বাংলা</a>'
         . '</span>';
}
