<?php
// Exercise real key hashing/verification and the admin credential renderer.
define('ABSPATH', __DIR__);
define('EASYHEADLESS_PLUGIN_DIR', dirname(__DIR__, 2) . '/easyheadless-bridge/');
$GLOBALS['options'] = array();
$GLOBALS['can_manage'] = true;
$GLOBALS['valid_nonce'] = true;
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value) { $GLOBALS['options'][$key] = $value; }
function current_user_can($capability) { return $GLOBALS['can_manage']; }
function wp_die($message, $code = 0) { throw new RuntimeException($message); }
function esc_html__($value, $domain) { return $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function rest_url($path) { return 'https://example.test/wp-json/' . $path; }
function wp_nonce_field($action) { echo '<input name="_wpnonce" value="test">'; }
function check_admin_referer($action) { if (!$GLOBALS['valid_nonce'] || 'easyheadless_regenerate_api_key' !== $action) throw new RuntimeException('Invalid nonce'); }
function wp_generate_password($length, $special, $extra) { return substr(bin2hex(random_bytes($length)), 0, $length); }
function wp_hash_password($password) { return password_hash($password, PASSWORD_DEFAULT); }
function wp_check_password($password, $hash) { return password_verify($password, $hash); }
require EASYHEADLESS_PLUGIN_DIR . 'includes/class-easyheadless-plugin.php';
$reflection = new ReflectionClass('EasyHeadless_Plugin');
$instance = $reflection->newInstanceWithoutConstructor();
$property = $reflection->getProperty('instance');
if (PHP_VERSION_ID < 80100) { $property->setAccessible(true); }
$property->setValue(null, $instance);
function render_credentials_test() {
    ob_start();
    try { EasyHeadless_Admin::render_credentials(); return ob_get_contents(); }
    finally { ob_end_clean(); }
}
function expect_credentials($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = array('easyheadless_regenerate_api_key' => '1');
expect_credentials(false !== strpos(render_credentials_test(), 'Generate agent key'), 'GET must not generate a credential');
expect_credentials(!get_option(EasyHeadless_Plugin::OPTION_API_KEY_HASH), 'GET must not mutate key');
$_SERVER['REQUEST_METHOD'] = 'POST';
$GLOBALS['valid_nonce'] = false;
try { render_credentials_test(); throw new LogicException('Invalid nonce accepted'); } catch (RuntimeException $e) {}
expect_credentials(!get_option(EasyHeadless_Plugin::OPTION_API_KEY_HASH), 'Invalid nonce must not mutate key');
$GLOBALS['valid_nonce'] = true;
$GLOBALS['can_manage'] = false;
try { render_credentials_test(); throw new LogicException('Non-admin accepted'); } catch (RuntimeException $e) {}
$GLOBALS['can_manage'] = true;
$html = render_credentials_test();
preg_match('/id="eh-new-api-key"[^>]*value="([^"]+)"/', $html, $match);
$key = $match[1] ?? '';
expect_credentials(EasyHeadless_Plugin::verify_api_key($key), 'Displayed key authenticates');
expect_credentials(!in_array($key, $GLOBALS['options'], true), 'Plaintext key is not persisted');
$hash = get_option(EasyHeadless_Plugin::OPTION_API_KEY_HASH);
try { render_credentials_test(); throw new LogicException('Unconfirmed rotation accepted'); } catch (RuntimeException $e) {}
expect_credentials($hash === get_option(EasyHeadless_Plugin::OPTION_API_KEY_HASH), 'Unconfirmed rotation preserves key');
$_POST['confirm_rotation'] = '1';
$html = render_credentials_test();
preg_match('/id="eh-new-api-key"[^>]*value="([^"]+)"/', $html, $match);
expect_credentials(!EasyHeadless_Plugin::verify_api_key($key), 'Old key is invalid after rotation');
expect_credentials(EasyHeadless_Plugin::verify_api_key($match[1]), 'Replacement authenticates');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = array();
expect_credentials(false === strpos(render_credentials_test(), $match[1]), 'Normal reload does not reveal key');
echo "EasyHeadless admin credential tests passed.\n";
