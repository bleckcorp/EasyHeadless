<?php
define('ABSPATH', __DIR__ . '/');
define('EASYHEADLESS_EDITION', 'film-efiko');
define('EASYHEADLESS_VERSION', '0.6.11');
define('EASYHEADLESS_PLUGIN_URL', '/easyheadless-bridge/');
$GLOBALS['test_menu'] = array();
$GLOBALS['test_actions'] = array();
$GLOBALS['test_removed'] = array();
$GLOBALS['test_options'] = array();
class EasyHeadless_Plugin { const ADMIN_MENU_SLUG = 'easyheadless'; const OPTION_ALLOWED_ORIGINS = 'origins'; public static function instance() { return new self(); } public function render_admin_notices() {} }
class EasyHeadless_ACF { public static function get_option_fields() { return array(); } public static function is_available() { return false; } }
function add_menu_page($title, $label, $cap, $slug, $callback, $icon, $position) { $GLOBALS['test_brand'] = $label; }
function add_submenu_page($parent, $title, $label, $cap, $slug, $callback, $position = null) {
    $GLOBALS['test_menu'][$slug] = $label;
    $GLOBALS['submenu'][$parent][] = array($label, $cap, $slug, $title);
}
function add_action($name, $callback, $priority = 10) { $GLOBALS['test_actions'][] = $name; }
function remove_all_actions($name) { $GLOBALS['test_removed'][] = $name; }
function get_option($key, $default = false) { return isset($GLOBALS['test_options'][$key]) ? $GLOBALS['test_options'][$key] : $default; }
function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, $args); }
function get_bloginfo($field) { return 'Film Efiko'; }
function current_user_can($cap) { return true; }
function wp_get_environment_type() { return 'production'; }
function get_categories($args) { return array((object) array('term_id' => 33, 'name' => 'Cinema'), (object) array('term_id' => 16, 'name' => 'Business')); }
function get_posts($args) { return array((object) array('ID' => 7615, 'post_title' => 'Ifeyinwa Arinze Won A Locarno Residency')); }
function get_category_by_slug($slug) { return false; }
function admin_url($path) { return '/wp-admin/' . $path; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return esc_attr($value); }
function esc_url($value) { return esc_attr($value); }
function selected($a, $b) { if ((string) $a === (string) $b) echo ' selected'; }
function checked($a, $b = true) { if ((string) $a === (string) $b) echo ' checked'; }
function settings_fields($group) { echo '<input type="hidden" name="option_page" value="' . esc_attr($group) . '"><input type="hidden" name="_wpnonce" value="fixture-nonce">'; }
function wp_generate_password($length) { return str_repeat('x', $length); }
require_once dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-modules.php';
require_once dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-admin.php';
require_once dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-publication.php';

$default_method = new ReflectionMethod('EasyHeadless_Publication', 'default_curation');
if (PHP_VERSION_ID < 80100) { $default_method->setAccessible(true); }
$curation = $default_method->invoke(null);
$curation['hero_id'] = 7615;
$curation['support_ids'] = array(7716, 7717);
$curation['hot-hot-latest']['title'] = 'Cinema';
$curation['hot-hot-latest']['category_id'] = 33;
$curation['hot-hot-latest']['href'] = '/category/cinema';
$curation['big-interview']['title'] = 'Interview';
$curation['spotlight']['title'] = 'Spotlight';
$curation['biz']['title'] = 'Business';
$curation['festivals']['post_ids'] = array(99, 100);
$curation['reviews']['enabled'] = '0';
$GLOBALS['test_options'][EasyHeadless_Publication::OPTION_CURATION] = $curation;
EasyHeadless_Admin::register_menu();
EasyHeadless_Publication::register_admin_pages();
add_submenu_page('easyheadless', 'Issues', 'Issues', 'edit_posts', 'edit.php?post_type=eh_issue', null);
EasyHeadless_Edition::order_submenus();
$GLOBALS['test_menu'] = array_column($GLOBALS['submenu']['easyheadless'], 0, 2);
ob_start(); EasyHeadless_Publication::render_curation_page(); $html = ob_get_clean();
if (isset($_GET['preview'])) {
    echo '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Film Efiko Custom — Homepage Sections</title><link rel="stylesheet" href="/easyheadless-bridge/assets/admin.css"><link rel="stylesheet" href="/easyheadless-bridge/assets/film-efiko-admin.css"><style>body{margin:0;background:#f6f7f7;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#101820}.wp-toolbar{height:32px;background:#1d2327;color:white;font-size:13px;padding:8px 16px;box-sizing:border-box}.wp-native{position:absolute;top:32px;bottom:0;width:160px;background:#1d2327;color:#fff;padding-top:24px}.wp-native>a,.wp-native .submenu a{display:block;color:white;padding:8px 16px;text-decoration:none;font-size:13px}.wp-native .submenu{background:#2c3338}.wp-content{margin-left:160px;padding:1px 20px}.eh-admin{margin-right:0}.button{display:inline-block;text-decoration:none;cursor:pointer}.dashicons{display:none}button,input,select{font-family:inherit}.eh-side-nav .eh-nav-group a{display:flex;gap:6px}@media(max-width:782px){.wp-native{display:none}.wp-content{margin-left:0;padding:1px 8px}}</style></head><body><div class="wp-toolbar">Film Efiko Custom</div><aside class="wp-native"><a href="#">Dashboard</a><a href="#">Posts</a><a href="#">Media</a><a href="#">Pages</a><a href="#">Film Efiko Custom</a><div class="submenu">';
    foreach ($GLOBALS['test_menu'] as $slug => $label) echo '<a href="#">' . esc_html($label) . '</a>';
    echo '</div></aside><div class="wp-content">' . $html . '</div><script src="/easyheadless-bridge/assets/curation-editor.js"></script></body></html>';
    exit;
}
function film_assert($condition, $message) { if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); } }
film_assert('Film Efiko Custom' === $GLOBALS['test_brand'], 'Native menu branding');
film_assert(!in_array('admin_head', $GLOBALS['test_actions'], true), 'Native submenus are not hidden in custom build');
film_assert(isset($GLOBALS['test_menu']['edit.php?post_type=eh_issue']), 'Issues remain in native submenu');
film_assert(isset($GLOBALS['test_menu']['easyheadless-publication-curation']), 'Homepage section submenu exists');
$document = new DOMDocument(); @$document->loadHTML($html); $xpath = new DOMXPath($document);
foreach (array_keys($curation) as $key) {
    if (in_array($key, array('hero_id', 'support_ids'), true)) continue;
    foreach (array('title', 'source', 'category_id', 'count', 'layout', 'order', 'href', 'post_ids') as $field) {
        film_assert(1 === $xpath->query('//*[@name="' . EasyHeadless_Publication::OPTION_CURATION . '[' . $key . '][' . $field . ']"]')->length, 'Field preserved: ' . $key . '.' . $field);
    }
}
film_assert(false !== strpos($html, 'value="99, 100"'), 'Selected IDs preserved');
film_assert(14 === $xpath->query('//*[@data-eh-section-tab]')->length, 'Opening plus all 13 sections rendered');
film_assert(0 === $xpath->query('//*[@disabled]')->length, 'Unselected panel inputs remain submitted');
$other = (object) array('id' => 'dashboard', 'post_type' => '');
EasyHeadless_Edition::quiet_notices($other);
film_assert(!$GLOBALS['test_removed'], 'Other WordPress screens retain notices');
EasyHeadless_Edition::quiet_notices((object) array('id' => 'easyheadless_page_easyheadless-publication-curation', 'post_type' => ''));
film_assert(3 === count($GLOBALS['test_removed']), 'Third-party notice hooks suppressed only on custom screens');
// Avoid constructing the real plugin in the lightweight fixture.
film_assert(EasyHeadless_Edition::is_admin_screen((object) array('id' => 'edit-eh_issue', 'post_type' => 'eh_issue')), 'Issue screens identified');
echo "Film Efiko custom admin and curation tests passed.\n";
