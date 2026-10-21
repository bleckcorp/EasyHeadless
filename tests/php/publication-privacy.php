<?php
define('ABSPATH', __DIR__);
$privacy_events = array();
function nocache_headers() { $GLOBALS['privacy_events'][] = 'headers'; }
function do_action($hook, $reason) { $GLOBALS['privacy_events'][] = $hook; }
require dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-publication-privacy.php';
class Privacy_Request {
    private $route;
    public function __construct($route) { $this->route = $route; }
    public function get_route() { return $this->route; }
}
class Privacy_Response {
    public $data = array('email' => 'reader@example.com');
    public $status = 401;
    public $headers = array('Vary' => 'Origin', 'ETag' => 'old', 'Last-Modified' => 'old', 'Access-Control-Allow-Origin' => 'https://film.example');
    public function get_headers() { return $this->headers; }
    public function set_headers($headers) { $this->headers = $headers; }
    public function header($name, $value) { $this->headers[$name] = $value; }
}
function privacy_assert($condition, $message) { if (!$condition) { throw new Exception($message); } }
foreach (array('/easyheadless/v1/publication/account', '/easyheadless/v1/publication/posts/locked', '/easyheadless/v1/preview', '/easyheadless/v2/publication/account') as $route) {
    $request = new Privacy_Request($route);
    $result = new stdClass();
    privacy_assert(EasyHeadless_Publication_Privacy::before_dispatch($result, null, $request) === $result, 'Pre-dispatch must not bypass permissions.');
    $response = new Privacy_Response();
    privacy_assert(EasyHeadless_Publication_Privacy::after_dispatch($response, null, $request) === $response, 'Response identity retained.');
    privacy_assert($response->data['email'] === 'reader@example.com' && $response->status === 401, 'Body and status unchanged.');
    privacy_assert(strpos($response->headers['Cache-Control'], 'private, no-store') === 0, 'No shared response caching.');
    privacy_assert($response->headers['CDN-Cache-Control'] === 'no-store', 'CDN no-store.');
    privacy_assert($response->headers['Vary'] === 'Authorization, Cookie, Origin', 'Existing Vary preserved.');
    privacy_assert(!isset($response->headers['ETag'], $response->headers['Last-Modified']), 'Validators removed.');
    privacy_assert(isset($response->headers['Access-Control-Allow-Origin']), 'CORS unchanged.');
}
privacy_assert(in_array('litespeed_control_set_nocache', $privacy_events, true), 'LiteSpeed REST cache bypass signalled.');
foreach (array('/wp/v2/posts', '/easyheadless/v1other', '/other/easyheadless/v1') as $route) {
    $request = new Privacy_Request($route);
    $response = new Privacy_Response();
    $before = $response->headers;
    $event_count = count($privacy_events);
    EasyHeadless_Publication_Privacy::before_dispatch(null, null, $request);
    EasyHeadless_Publication_Privacy::after_dispatch($response, null, $request);
    privacy_assert($response->headers === $before && count($privacy_events) === $event_count, 'Unrelated APIs unchanged.');
}
echo "Publication privacy cache tests passed.\n";
