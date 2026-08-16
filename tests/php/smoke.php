<?php

define('ABSPATH', __DIR__);
define('EASYHEADLESS_VERSION', '0.3.0-test');
define('EASYHEADLESS_PLUGIN_DIR', dirname(__DIR__, 2) . '/easyheadless-bridge/');

$GLOBALS['eh_test_options'] = array();
$GLOBALS['eh_test_acf'] = array();
$GLOBALS['eh_test_routes'] = array();

class WP_REST_Server
{
    const READABLE = 'GET';
    const CREATABLE = 'POST';
    const EDITABLE = 'PATCH';
}

class WP_Error
{
    public $code;
    public $message;
    public $data;

    public function __construct($code, $message, $data = array())
    {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
}

class WP_Post
{
    public $ID;
    public $post_type;
    public $post_name;

    public function __construct($id, $post_type, $post_name)
    {
        $this->ID = $id;
        $this->post_type = $post_type;
        $this->post_name = $post_name;
    }
}

class WP_REST_Request
{
    private $body;

    public function __construct($body = array())
    {
        $this->body = $body;
    }

    public function get_json_params()
    {
        return $this->body;
    }
}

function get_option($key, $default = false)
{
    return array_key_exists($key, $GLOBALS['eh_test_options']) ? $GLOBALS['eh_test_options'][$key] : $default;
}

function update_option($key, $value)
{
    $GLOBALS['eh_test_options'][$key] = $value;
    return true;
}

function rest_ensure_response($value) { return $value; }

function get_fields($target)
{
    return 'option' === $target ? $GLOBALS['eh_test_acf'] : array();
}

function get_bloginfo($key)
{
    return 'name' === $key ? 'WordPress Default' : ('description' === $key ? 'WordPress description' : '');
}

function sanitize_email($value) { return filter_var($value, FILTER_SANITIZE_EMAIL); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function sanitize_title($value) { return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $value), '-')); }
function esc_url_raw($value) { return filter_var((string) $value, FILTER_SANITIZE_URL); }
function wp_kses_post($value) { return strip_tags((string) $value, '<p><br><strong><em>'); }
function wp_unslash($value) { return $value; }
function absint($value) { return abs((int) $value); }
function post_type_exists($type) { return 'courses' === $type; }
function get_page_uri($post) { return $post->post_name; }
function wp_check_password($password, $hash) { return password_verify($password, $hash); }
function register_rest_route($namespace, $route, $args)
{
    $GLOBALS['eh_test_routes'][] = '/' . $namespace . $route;
    return true;
}

require_once EASYHEADLESS_PLUGIN_DIR . 'includes/class-easyheadless-plugin.php';

$failures = array();

function assert_same($expected, $actual, $label)
{
    global $failures;
    if ($expected !== $actual) {
        $failures[] = $label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
    }
}

$sanitized = EasyHeadless_Modules::sanitize_site_profile(array(
    'company_name' => '<b>Accel</b>',
    'frontend_url' => 'https://accelskillshub.com/',
    'headless_routing' => '1',
    'social_links' => array(array('label' => 'LinkedIn', 'url' => 'https://linkedin.com/company/accel')),
));
assert_same('Accel', $sanitized['company_name'], 'native profile sanitizes text');
assert_same('https://accelskillshub.com', $sanitized['frontend_url'], 'native profile normalizes URLs');
assert_same(true, $sanitized['headless_routing'], 'native profile normalizes routing flag');

$GLOBALS['eh_test_options'][EasyHeadless_Modules::OPTION_SITE_PROFILE] = array(
    'company_name' => 'Native Accel',
    'phone' => '',
);
$GLOBALS['eh_test_acf'] = array(
    'company_name' => 'Legacy ACF Accel',
    'phone' => '+234 800 000 0000',
);
$GLOBALS['eh_test_options']['admin_email'] = 'admin@example.com';
$profile = EasyHeadless_Modules::site_profile();
assert_same('Native Accel', $profile['company_name'], 'native setting has precedence');
assert_same('+234 800 000 0000', $profile['phone'], 'empty native setting falls back to ACF');
assert_same('admin@example.com', $profile['email'], 'missing values fall back to WordPress defaults');

$portfolio = EasyHeadless_Modules::sanitize_portfolio('[{"attachmentId":42,"title":"Launch","caption":"<p>Film</p>","alt":"Launch still","category":"Brand Film"}]');
assert_same(42, $portfolio[0]['attachmentId'], 'portfolio accepts admin JSON');
assert_same('brand-film', $portfolio[0]['category'], 'portfolio normalizes categories');
assert_same(array(8, 3), EasyHeadless_Modules::sanitize_featured_courses('8,3'), 'course ordering is preserved');
assert_same(array('fluentforms:2', 'fluentforms:4'), EasyHeadless_Modules::sanitize_allowed_forms("fluentforms:2\nfluentforms:4"), 'form allowlist is normalized');
assert_same(false, EasyHeadless_Modules::sanitize_enabled_modules(array('portfolio' => 1, 'tutor' => 1, 'forms' => 1))['church'], 'unchecked church module is disabled safely');

$GLOBALS['eh_test_options']['page_on_front'] = 24;
$path_method = new ReflectionMethod('EasyHeadless_Modules', 'frontend_content_path');
assert_same('/', $path_method->invoke(null, new WP_Post(24, 'page', 'home-01')), 'WordPress front page maps to public site root');
assert_same('/about', $path_method->invoke(null, new WP_Post(25, 'page', 'about')), 'regular pages retain their public path');
assert_same('/insights/launch-news', $path_method->invoke(null, new WP_Post(26, 'post', 'launch-news')), 'posts retain the insights path');

$GLOBALS['eh_test_options'][EasyHeadless_Modules::OPTION_ENABLED_MODULES] = array(
    'core' => true,
    'portfolio' => true,
    'church' => true,
    'tutor' => true,
    'forms' => true,
);
$controller = new EasyHeadless_REST();
$controller->register_routes();
foreach (array('/capabilities', '/navigation', '/portfolio', '/courses', '/courses/(?P<slug>[a-zA-Z0-9-]+)', '/church', '/forms', '/forms/approved', '/modules', '/updater', '/updater/check', '/updater/install') as $route) {
    assert_same(true, in_array('/easyheadless/v1' . $route, $GLOBALS['eh_test_routes'], true), 'route registered: ' . $route);
}

$module_update = $controller->update_modules(new WP_REST_Request(array(
    'modules' => array('church' => false),
)));
assert_same(true, $module_update['success'], 'module API update succeeds');
assert_same(false, $module_update['modules']['church'], 'module API disables church');
assert_same(true, $module_update['modules']['tutor'], 'module API preserves unspecified modules');

$form_update = $controller->update_approved_forms(new WP_REST_Request(array(
    'ids' => array('fluentforms:1'),
)));
assert_same(true, $form_update['success'], 'approved forms API update succeeds');
assert_same(array('fluentforms:1'), $form_update['ids'], 'approved forms API stores qualified IDs');

$form_health = (new EasyHeadless_Forms())->health();
assert_same(false, $form_health['fluentforms']['available'], 'Fluent Forms loaded state is reported');
assert_same(false, $form_health['fluentforms']['installed'], 'Fluent Forms installed state is reported');

$GLOBALS['eh_test_options'][EasyHeadless_Plugin::OPTION_API_KEY_HASH] = password_hash('eh_test_key', PASSWORD_DEFAULT);
assert_same(true, EasyHeadless_Plugin::verify_api_key('eh_test_key'), 'valid API key authenticates');
assert_same(false, EasyHeadless_Plugin::verify_api_key('wrong'), 'invalid API key is rejected');

$GLOBALS['eh_test_options'][EasyHeadless_Modules::OPTION_ALLOWED_FORMS] = array('fluentforms:4');
$forms = new EasyHeadless_Forms();
$blocked = $forms->get_form('fluentforms:5');
assert_same('form_not_found', $blocked->code, 'unapproved forms are not public');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "EasyHeadless PHP smoke tests passed." . PHP_EOL;
