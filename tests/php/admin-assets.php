<?php

define('ABSPATH', __DIR__);

$GLOBALS['eh_admin_styles'] = array();
$GLOBALS['eh_admin_scripts'] = array();
$GLOBALS['eh_admin_actions'] = array();

function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return 'https://example.test/wp-content/plugins/' . basename(dirname($file)) . '/'; }
function plugin_basename($file) { return basename(dirname($file)) . '/' . basename($file); }
function register_activation_hook($file, $callback) { return true; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['eh_admin_actions'][] = $hook; return true; }
function wp_enqueue_style($handle, $source, $dependencies = array(), $version = false) { $GLOBALS['eh_admin_styles'][$handle] = $source; }
function wp_enqueue_script($handle, $source = '', $dependencies = array(), $version = false, $in_footer = false) { $GLOBALS['eh_admin_scripts'][$handle] = $source; }
function wp_enqueue_media() { return true; }

require dirname(__DIR__, 2) . '/easyheadless-bridge/easyheadless-bridge.php';

$failures = array();

function assert_admin_same($expected, $actual, $label)
{
    global $failures;
    if ($expected !== $actual) {
        $failures[] = $label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
    }
}

assert_admin_same(true, defined('EASYHEADLESS_PLUGIN_URL'), 'plugin URL constant is defined');
assert_admin_same('https://example.test/wp-content/plugins/easyheadless-bridge/', EASYHEADLESS_PLUGIN_URL, 'plugin URL constant uses plugin directory URL');

EasyHeadless_Admin::enqueue_assets('easyheadless_page_easyheadless-forms');
assert_admin_same(
    EASYHEADLESS_PLUGIN_URL . 'assets/admin.css',
    $GLOBALS['eh_admin_styles']['easyheadless-admin'],
    'admin stylesheet URL resolves'
);
assert_admin_same(
    EASYHEADLESS_PLUGIN_URL . 'assets/admin.js',
    $GLOBALS['eh_admin_scripts']['easyheadless-admin'],
    'admin script URL resolves'
);

$style_count = count($GLOBALS['eh_admin_styles']);
EasyHeadless_Admin::enqueue_assets('settings_page_unrelated');
assert_admin_same($style_count, count($GLOBALS['eh_admin_styles']), 'unrelated admin pages do not load assets');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "EasyHeadless admin asset tests passed." . PHP_EOL;
