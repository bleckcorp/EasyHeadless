<?php
define('ABSPATH', __DIR__);
define('EASYHEADLESS_EDITION', 'film-efiko');
define('EASYHEADLESS_VERSION', 'test');
define('EASYHEADLESS_PLUGIN_URL', '/easyheadless-bridge/');
$GLOBALS['media_enqueues'] = 0;
$GLOBALS['scripts'] = array();
function wp_enqueue_style($handle, $src, $deps, $version) {}
function wp_enqueue_script($handle, $src, $deps, $version, $footer) { $GLOBALS['scripts'][$handle] = array($src, $deps, $footer); }
function wp_enqueue_media() { $GLOBALS['media_enqueues']++; }
require dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-admin.php';
foreach (array('easyheadless_page_easyheadless-publication', 'film-efiko-custom_page_easyheadless-publication') as $hook) {
    $GLOBALS['scripts'] = array();
    $before = $GLOBALS['media_enqueues'];
    EasyHeadless_Admin::enqueue_assets($hook);
    if ($GLOBALS['media_enqueues'] !== $before + 1 || !isset($GLOBALS['scripts']['film-efiko-email-branding'])) {
        throw new Exception('Logo picker and WordPress Media Library must enqueue on ' . $hook);
    }
    if ($GLOBALS['scripts']['film-efiko-email-branding'][1] !== array('media-editor')) {
        throw new Exception('The picker must wait for WordPress media dependencies.');
    }
}
foreach (array('film-efiko-custom_page_easyheadless-publication-curation', 'film-efiko-custom_page_easyheadless-publication-contacts', 'settings_page_unrelated') as $hook) {
    $GLOBALS['scripts'] = array();
    $before = $GLOBALS['media_enqueues'];
    EasyHeadless_Admin::enqueue_assets($hook);
    if ($GLOBALS['media_enqueues'] !== $before || isset($GLOBALS['scripts']['film-efiko-email-branding'])) {
        throw new Exception('Logo picker must not enqueue on unrelated screen ' . $hook);
    }
}
echo "Film Efiko branded Media Library asset regression tests passed.\n";
