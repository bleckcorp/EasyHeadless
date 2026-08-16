<?php

define('ABSPATH', __DIR__ . '/');
define('EASYHEADLESS_VERSION', '0.4.0-test');
define('EASYHEADLESS_PLUGIN_DIR', dirname(__DIR__, 2) . '/easyheadless-bridge/');
define('EASYHEADLESS_PLUGIN_BASENAME', 'easyheadless-bridge/easyheadless-bridge.php');
define('WP_PLUGIN_DIR', sys_get_temp_dir());
define('WP_CONTENT_DIR', sys_get_temp_dir());

$GLOBALS['eh_updater_options'] = array();

class WP_Error
{
    private $code;
    private $message;
    private $data;
    public function __construct($code, $message, $data = array()) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}

function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function esc_url_raw($value) { return filter_var((string) $value, FILTER_SANITIZE_URL); }
function absint($value) { return abs((int) $value); }
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['eh_updater_options']) ? $GLOBALS['eh_updater_options'][$key] : $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['eh_updater_options'][$key] = $value; return true; }
function add_option($key, $value, $deprecated = '', $autoload = true) { if (array_key_exists($key, $GLOBALS['eh_updater_options'])) return false; $GLOBALS['eh_updater_options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['eh_updater_options'][$key]); return true; }
function get_bloginfo($field) { return 'version' === $field ? '6.2' : ''; }
function get_filesystem_method($args = array(), $context = '') { return 'direct'; }
function wp_is_writable($path) { return is_writable($path); }

require_once EASYHEADLESS_PLUGIN_DIR . 'includes/class-easyheadless-updater.php';

$failures = array();
function updater_assert($condition, $label)
{
    global $failures;
    if (!$condition) $failures[] = $label;
}

$pair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($pair);
$public = base64_encode(sodium_crypto_sign_publickey($pair));
$manifest = array(
    'schemaVersion' => 1,
    'slug' => 'easyheadless-bridge',
    'version' => '0.4.1',
    'packageUrl' => 'https://releases.example.test/easyheadless-bridge-0.4.1.zip',
    'sha256' => str_repeat('a', 64),
    'signature' => '',
    'requiresWordPress' => '6.2',
    'requiresPhp' => '7.4',
    'releasedAt' => '2026-08-15T08:00:00Z',
);
$manifest['signature'] = base64_encode(sodium_crypto_sign_detached(EasyHeadless_Updater::canonical_payload($manifest), $secret));
$valid = EasyHeadless_Updater::validate_manifest($manifest, $public);
updater_assert(!is_wp_error($valid) && '0.4.1' === $valid['version'], 'a valid signed manifest is accepted');

$tampered = $manifest;
$tampered['packageUrl'] = 'https://attacker.example.test/plugin.zip';
$untrusted = EasyHeadless_Updater::validate_manifest($tampered, $public);
updater_assert(is_wp_error($untrusted) && 'easyheadless_manifest_untrusted' === $untrusted->get_error_code(), 'tampered signed metadata is rejected');

$wrong_slug = $manifest;
$wrong_slug['slug'] = 'another-plugin';
updater_assert(is_wp_error(EasyHeadless_Updater::validate_manifest($wrong_slug, $public)), 'a manifest for another plugin is rejected');

$temporary = tempnam(sys_get_temp_dir(), 'eh-hash-');
file_put_contents($temporary, 'signed package fixture');
$digest = hash_file('sha256', $temporary);
updater_assert(EasyHeadless_Updater::verify_package_hash($temporary, $digest), 'matching package hash is accepted');
updater_assert(!EasyHeadless_Updater::verify_package_hash($temporary, str_repeat('0', 64)), 'package hash mismatch is rejected');
unlink($temporary);

$GLOBALS['eh_updater_options'][EasyHeadless_Updater::OPTION_CONFIG] = array(
    'manifest_url' => 'https://releases.example.test/stable.json',
    'public_key' => $public,
);
$preflight = EasyHeadless_Updater::instance()->preflight($valid);
updater_assert(is_wp_error($preflight) && 'easyheadless_update_preflight_failed' === $preflight->get_error_code(), 'WordPress without core rollback safeguards fails preflight');

$lock = new ReflectionMethod('EasyHeadless_Updater', 'acquire_lock');
$unlock = new ReflectionMethod('EasyHeadless_Updater', 'release_lock');
updater_assert(true === $lock->invoke(EasyHeadless_Updater::instance()), 'first update lock is acquired');
updater_assert(false === $lock->invoke(EasyHeadless_Updater::instance()), 'overlapping update lock is rejected');
$unlock->invoke(EasyHeadless_Updater::instance());
updater_assert(true === $lock->invoke(EasyHeadless_Updater::instance()), 'update lock is reusable after release');
$unlock->invoke(EasyHeadless_Updater::instance());

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "EasyHeadless updater tests passed." . PHP_EOL;
