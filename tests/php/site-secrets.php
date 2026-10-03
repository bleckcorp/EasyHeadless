<?php

define('ABSPATH', __DIR__);
define('EASYHEADLESS_VERSION', 'test');

class Test_Response
{
    private $data;
    public function __construct($data) { $this->data = $data; }
    public function get_data() { return $this->data; }
}
class EasyHeadless_Forms
{
    public function health() { return array(); }
}
class EasyHeadless_ACF
{
    public static function is_available() { return false; }
}
class EasyHeadless_SEO
{
    public static function is_yoast_available() { return false; }
}
class EasyHeadless_Updater
{
    public static function instance() { return new self(); }
    public function public_status() { return array(); }
}
class EasyHeadless_Modules
{
    public static function site_profile()
    {
        return array(
            'company_name' => 'CareFlow360', 'short_description' => 'Hospital software',
            'frontend_url' => 'https://careflow360.ng', 'lms_url' => '',
            'phone' => '', 'email' => 'support@careflow360.ng', 'address' => '',
            'social_links' => array(), 'revalidation_url' => 'https://secret.example/hook',
            'revalidation_secret' => 'private-test-value',
        );
    }
    public static function is_enabled($module) { return false; }
    public static function capabilities($forms) { return array(); }
    public static function portal_links() { return array(); }
}
function rest_ensure_response($data) { return new Test_Response($data); }
function get_posts($args) { return array(); }
function get_bloginfo($key) { return ''; }
function home_url($path) { return 'https://cms.example' . $path; }
function get_option($key) { return ''; }

require_once dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-rest.php';
$site = (new EasyHeadless_REST())->site()->get_data();
if (isset($site['settings']['revalidation_url']) || isset($site['settings']['revalidation_secret'])) {
    fwrite(STDERR, "Public site response exposed the rebuild hook.\n");
    exit(1);
}
if ($site['settings']['company_name'] !== 'CareFlow360') {
    fwrite(STDERR, "Public company setting was lost.\n");
    exit(1);
}
echo "EasyHeadless public site secret redaction passed.\n";
