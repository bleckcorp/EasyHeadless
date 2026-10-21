<?php
define('ABSPATH', __DIR__);
define('EASYHEADLESS_EDITION', 'film-efiko');
function get_option($key, $default = false) { return array('church' => true, 'tutor' => true, 'portfolio' => true, 'forms' => false); }
function post_type_exists($name) { return true; }
class EasyHeadless_ACF { public static function is_available() { return true; } }
class EasyHeadless_Forms { public function health() { return array(); } }
class WP_REST_Server { const READABLE = 'GET'; const EDITABLE = 'POST,PUT,PATCH'; const CREATABLE = 'POST'; const DELETABLE = 'DELETE'; }
class WP_Error { public $code; public function __construct($code, $message, $data = array()) { $this->code = $code; } }
$GLOBALS['film_routes'] = array();
function register_rest_route($namespace, $route, $args) { $GLOBALS['film_routes'][] = $route; }
require_once dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-modules.php';
function module_assert($condition, $message) { if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); } }
$modules = EasyHeadless_Modules::enabled_modules();
module_assert(!isset($modules['church'], $modules['tutor']), 'Unsupported modules removed despite old enabled options');
module_assert(!EasyHeadless_Modules::is_enabled('church') && !EasyHeadless_Modules::is_enabled('tutor'), 'Unsupported modules cannot register runtime features');
$sanitized = EasyHeadless_Modules::sanitize_enabled_modules(array('church' => 1, 'tutor' => 1, 'portfolio' => 1));
module_assert(!array_key_exists('church', $sanitized) && !array_key_exists('tutor', $sanitized), 'Cannot enable unsupported modules through settings');
$capabilities = EasyHeadless_Modules::capabilities();
module_assert(!isset($capabilities['church'], $capabilities['tutor']), 'Unsupported modules absent from capability API');
module_assert(isset($capabilities['core'], $capabilities['portfolio'], $capabilities['forms']), 'Core and needed modules retained');
require_once dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-rest.php';
$rest = new EasyHeadless_REST(); $rest->register_routes();
module_assert(in_array('/posts', $GLOBALS['film_routes'], true), 'Core post route preserved');
module_assert(!in_array('/courses', $GLOBALS['film_routes'], true) && !in_array('/church', $GLOBALS['film_routes'], true), 'Unsupported module read routes removed');
require_once dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-updater.php';
$error = EasyHeadless_Updater::instance()->install_release();
module_assert($error instanceof WP_Error && 'easyheadless_custom_update_required' === $error->code, 'Generic remote update cannot replace private edition');
echo "Film Efiko custom module tests passed.\n";
