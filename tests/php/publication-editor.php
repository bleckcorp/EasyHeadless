<?php
define('ABSPATH', __DIR__);
define('EASYHEADLESS_VERSION', 'test');
$GLOBALS['editor_meta'] = array();
$GLOBALS['editor_assets'] = array();
$GLOBALS['editor_box'] = array();
function get_option($key, $default = false) { return $default; }
function get_post_meta($id, $key, $single = true) {
    if ($id >= 100) {
        return '_easyheadless_issue_volume' === $key ? 'Volume 1' : ('_easyheadless_issue_date' === $key ? '2025-04-08' : '');
    }
    return isset($GLOBALS['editor_meta'][$key]) ? $GLOBALS['editor_meta'][$key] : '';
}
function get_posts($args) { return array(
    (object) array('ID' => 100, 'post_title' => 'Volume 1, Issue 1', 'post_status' => 'publish'),
    (object) array('ID' => 101, 'post_title' => 'Volume 1, Issue 2', 'post_status' => 'draft'),
); }
function absint($value) { return abs((int) $value); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function get_the_title($post) { return $post->post_title; }
function get_the_post_thumbnail_url($post, $size) { return 100 === $post->ID ? 'https://filmefiko.com/wp-content/uploads/2025/04/Film-Efiko-Cover-1.png' : ''; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return esc_attr($value); }
function esc_textarea($value) { return esc_attr($value); }
function esc_url($value) { return esc_attr($value); }
function admin_url($value) { return 'https://filmefiko.com/wp-admin/' . $value; }
function wp_nonce_field($action, $name) { echo '<input type="hidden" name="' . $name . '" value="test-nonce">'; }
function selected($first, $second) { if ((string) $first === (string) $second) echo ' selected="selected"'; }
function plugins_url($path, $file) { return '/easyheadless-bridge/assets/' . basename($path); }
function wp_enqueue_style($handle, $src, $deps, $version) { $GLOBALS['editor_assets']['style'] = $src; }
function wp_enqueue_script($handle, $src, $deps, $version, $footer) { $GLOBALS['editor_assets']['script'] = $src; }
function add_meta_box($id, $title, $callback, $screen, $context, $priority) { $GLOBALS['editor_box'] = compact('id', 'title', 'context'); }
require_once dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-publication.php';
function render_editor($meta = array()) {
    $GLOBALS['editor_meta'] = $meta;
    ob_start(); EasyHeadless_Publication::render_post_meta_box((object) array('ID' => 1)); return ob_get_clean();
}
if (isset($_GET['preview'])) {
    $html = render_editor(array(EasyHeadless_Publication::META_ISSUE_ID => 100));
    echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Publication editor preview</title><link rel="stylesheet" href="' . $GLOBALS['editor_assets']['style'] . '"><style>body{font-family:system-ui;background:#f0f0f1;margin:0;padding:32px}main{width:280px;max-width:calc(100vw - 64px);background:#fff;padding:16px;border:1px solid #ddd}h1{font-size:16px;margin:0 0 16px}.screen-reader-text{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}</style></head><body><main><h1>Publication</h1>' . $html . '</main><script src="' . $GLOBALS['editor_assets']['script'] . '"></script></body></html>';
    exit;
}
function editor_assert($condition, $message) { if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); } }
EasyHeadless_Publication::register_post_meta_box();
editor_assert('Publication' === $GLOBALS['editor_box']['title'], 'Panel has an understandable title');
editor_assert('easyheadless-publication-access' === $GLOBALS['editor_box']['id'], 'Panel ID preserved');
$html = render_editor(array(
    EasyHeadless_Publication::META_ISSUE_ID => 100,
    EasyHeadless_Publication::META_PREVIEW_PARAGRAPHS => 0,
    EasyHeadless_Publication::META_ACCESS => 'members',
    EasyHeadless_Publication::META_PRESENTATION_STYLE => 'new-yorker',
    EasyHeadless_Publication::META_GATE_MESSAGE => '</textarea><script>alert(1)</script>',
));
$document = new DOMDocument(); @$document->loadHTML($html); $xpath = new DOMXPath($document);
foreach (array('easyheadless_issue_id', 'easyheadless_access', 'easyheadless_presentation_style', 'easyheadless_preview_paragraphs', 'easyheadless_gate_message') as $name) {
    editor_assert(1 === $xpath->query('//*[@name="' . $name . '"]')->length, 'Existing field contract retained: ' . $name);
}
editor_assert('0' === $document->getElementById('eh-preview')->getAttribute('value'), 'Zero preview paragraphs preserved');
editor_assert(1 === $xpath->query('//select[@id="eh-issue"]/option[@value="100"][@selected]')->length, 'Existing issue selection preserved');
editor_assert(1 === $xpath->query('//select[@id="eh-access"]/option[@value="members"][@selected]')->length, 'Restricted access preserved');
editor_assert(1 === $xpath->query('//select[@id="eh-presentation-style"]/option[@value="new-yorker"][@selected]')->length, 'Presentation preserved');
editor_assert(0 === $document->getElementsByTagName('script')->length, 'Stored gate message escaped');
editor_assert(1 === $xpath->query('//*[@data-eh-issue-search]')->length, 'Search control rendered');
editor_assert(strpos($html, 'data-volume="Volume 1"') !== false && strpos($html, 'Issue 2 (Draft)') !== false, 'Issue metadata and draft state visible');
editor_assert(3 === $document->getElementsByTagName('fieldset')->length, 'Issue, presentation and reader access separated');
editor_assert('/easyheadless-bridge/assets/publication-editor.js' === $GLOBALS['editor_assets']['script'], 'Enhancement script loaded');
editor_assert('/easyheadless-bridge/assets/publication-editor.css' === $GLOBALS['editor_assets']['style'], 'Scoped stylesheet loaded');
echo "EasyHeadless publication editor UI tests passed.\n";
