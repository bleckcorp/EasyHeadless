<?php
define('ABSPATH', __DIR__);

class WP_Error {
    public $code;
    public $data;
    public function __construct($code, $message, $data = array()) { $this->code = $code; $this->data = $data; }
}
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; const EDITABLE = 'POST,PUT,PATCH'; }
class WP_REST_Request {
    private $params;
    private $body;
    private $auth;
    public function __construct($body = array(), $params = array(), $auth = '') { $this->body = $body; $this->params = $params; $this->auth = $auth; }
    public function get_param($key) { return isset($this->params[$key]) ? $this->params[$key] : null; }
    public function get_json_params() { return $this->body; }
    public function get_header($key) { return $this->auth; }
}
class EasyHeadless_Forms {}
class EasyHeadless_Plugin { public static function verify_api_key($key) { return 'test-key' === $key; } }
class EasyHeadless_Publication {
    const ISSUE_TYPE = 'eh_issue';
    const META_ISSUE_ID = '_easyheadless_issue_id';
    const ISSUE_META_DATE = '_easyheadless_issue_date';
    const ISSUE_META_VOLUME = '_easyheadless_issue_volume';
    const ISSUE_META_FEATURED_STORY = '_easyheadless_featured_story';
    const ISSUE_META_RELATED_STORIES = '_easyheadless_related_stories';
    public static $enabled = true;
    public static function public_permission() { return self::$enabled ? true : new WP_Error('publication_disabled', '', array('status' => 404)); }
    public static function sanitize_ids($ids) { return array_values(array_unique(array_map('intval', $ids ?: array()))); }
    public static function normalize_issue($post) { return array('id' => $post->ID, 'title' => $post->post_title, 'slug' => $post->post_name); }
}
class WP_Query {
    public $posts;
    public $found_posts = 1;
    public $max_num_pages = 1;
    public function __construct($args) { $GLOBALS['search_args'] = $args; $this->posts = array($GLOBALS['posts'][10]); }
}
$posts = array(
    10 => (object) array('ID' => 10, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Article', 'post_name' => 'article', 'post_content' => 'Original content'),
    11 => (object) array('ID' => 11, 'post_type' => 'post', 'post_status' => 'draft'),
    12 => (object) array('ID' => 12, 'post_type' => 'page', 'post_status' => 'publish'),
);
$meta = array(); $thumbs = array(10 => 99); $writes = array(); $routes = array(); $events = array();
function is_wp_error($value) { return $value instanceof WP_Error; }
function rest_ensure_response($value) { return $value; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_title($value) { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-'); }
function wp_kses_post($value) { return preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $value); }
function wp_slash($value) { return $value; }
function get_post($id) { return is_object($id) ? $id : (isset($GLOBALS['posts'][$id]) ? $GLOBALS['posts'][$id] : null); }
function get_post_type($id) { $post = get_post($id); return $post ? $post->post_type : false; }
function get_post_status($id) { $post = get_post($id); return $post ? $post->post_status : false; }
function get_posts($args) { return array_values(array_filter($GLOBALS['posts'], function ($post) use ($args) { return $post->post_type === $args['post_type'] && $post->post_name === $args['name']; })); }
function get_the_title($post) { return get_post($post)->post_title; }
function get_post_thumbnail_id($post) { $id = get_post($post)->ID; return isset($GLOBALS['thumbs'][$id]) ? $GLOBALS['thumbs'][$id] : 0; }
function get_the_post_thumbnail_url($post, $size) { return get_post_thumbnail_id($post) ? 'https://cms.test/cover.jpg' : false; }
function get_post_meta($id, $key, $single) { return isset($GLOBALS['meta'][$id][$key]) ? $GLOBALS['meta'][$id][$key] : ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; $GLOBALS['writes'][] = array('meta', $id); }
function wp_attachment_is_image($id) { return 99 === $id; }
function set_post_thumbnail($id, $attachment) { $GLOBALS['thumbs'][$id] = $attachment; }
function delete_post_thumbnail($id) { unset($GLOBALS['thumbs'][$id]); }
function wp_insert_post($input, $error = false) {
    $id = 100 + count($GLOBALS['posts']);
    $GLOBALS['posts'][$id] = (object) array_merge(array('ID' => $id, 'post_content' => ''), $input);
    $GLOBALS['writes'][] = array('insert', $id); return $id;
}
function wp_update_post($input, $error = false) {
    $id = $input['ID']; foreach ($input as $key => $value) { $GLOBALS['posts'][$id]->$key = $value; }
    $GLOBALS['writes'][] = array('update', $id); return $id;
}
function register_rest_route($namespace, $path, $args) { $GLOBALS['routes'][] = array($path, $args); }
function do_action($event, $id, $type) { $GLOBALS['events'][] = array($event, $id, $type); }
require_once dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-rest.php';
require_once dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-publication-issues.php';
function check($condition, $message) { if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); } }

EasyHeadless_Publication_Issues::register_routes();
check(4 === count($routes), 'Four additive editorial routes');
foreach ($routes as $route) { check('permission' === $route[1]['permission_callback'][1], 'Every editorial route requires auth'); }
check('easyheadless_auth_required' === EasyHeadless_Publication_Issues::permission(new WP_REST_Request())->code, 'No key rejected');
check('easyheadless_auth_invalid' === EasyHeadless_Publication_Issues::permission(new WP_REST_Request(array(), array(), 'Bearer wrong'))->code, 'Bad key rejected');
check(true === EasyHeadless_Publication_Issues::permission(new WP_REST_Request(array(), array(), 'Bearer test-key')), 'Valid key accepted');
EasyHeadless_Publication::$enabled = false;
check('publication_disabled' === EasyHeadless_Publication_Issues::permission(new WP_REST_Request(array(), array(), 'Bearer test-key'))->code, 'Disabled module rejected');
EasyHeadless_Publication::$enabled = true;
$original = clone $posts[10];
$result = EasyHeadless_Publication_Issues::create(new WP_REST_Request(array(
    'title' => 'Volume 1, Issue 1', 'date' => '2025-04-08', 'volume' => '<b>Volume 1</b>',
    'featuredImageId' => 99, 'featuredStoryId' => 10, 'storyIds' => array(10, 10),
    'description' => '<p>Edition</p><script>alert(1)</script>',
    'excerpt' => '<b>Our editorial summary.</b>',
)));
$id = $result['id'];
check('draft' === $result['status'], 'Default is draft');
check('volume-1-issue-1' === $result['slug'], 'Stable slug');
check(array(10) === $result['storyIds'] && 99 === $result['featuredImageId'], 'Links deduplicated and cover saved');
check($original == $posts[10] && !isset($meta[10]), 'Original article untouched');
check('Volume 1' === $meta[$id][EasyHeadless_Publication::ISSUE_META_VOLUME], 'Volume sanitized');
check('<p>Edition</p>' === $posts[$id]->post_content, 'Description sanitized');
check('Our editorial summary.' === $posts[$id]->post_excerpt, 'Excerpt saved separately and sanitized');
check('2025-04-08' === $meta[$id][EasyHeadless_Publication::ISSUE_META_DATE], 'Edition date saved');
check(count($events) === 1, 'Completed metadata triggers revalidation');
$before = count($writes);
$duplicate = EasyHeadless_Publication_Issues::create(new WP_REST_Request(array('title' => 'Volume 1, Issue 1')));
check('issue_slug_exists' === $duplicate->code && 409 === $duplicate->data['status'], 'Duplicate draft rejected');
foreach (array(
    array('title' => ''), array('title' => null), array('excerpt' => null), array('excerpt' => array()), array('date' => '2025-02-30'), array('status' => 'trash'),
    array('featuredImageId' => 1), array('storyIds' => array(11)), array('storyIds' => array(12)),
    array('storyIds' => array('10')), array('storyIds' => null), array('storyIds' => range(1, 101)), array('post_type' => 'post'),
) as $bad) {
    check(is_wp_error(EasyHeadless_Publication_Issues::update(new WP_REST_Request($bad, array('id' => $id)))), 'Invalid input rejected before writes');
}
check(count($writes) === $before, 'Invalid requests never mutate');
$updated = EasyHeadless_Publication_Issues::update(new WP_REST_Request(array('status' => 'publish'), array('id' => $id)));
check('publish' === $updated['status'] && array(10) === $updated['storyIds'] && 99 === $updated['featuredImageId'], 'Partial update preserves links and cover');
check('Our editorial summary.' === $posts[$id]->post_excerpt, 'Partial update preserves excerpt');
EasyHeadless_Publication_Issues::update(new WP_REST_Request(array('excerpt' => ''), array('id' => $id)));
check('' === $posts[$id]->post_excerpt, 'Excerpt can be explicitly cleared');
$cleared = EasyHeadless_Publication_Issues::update(new WP_REST_Request(array('storyIds' => array(), 'featuredStoryId' => 0, 'featuredImageId' => 0), array('id' => $id)));
check(array() === $cleared['storyIds'] && 0 === $cleared['featuredImageId'] && 0 === $cleared['featuredStoryId'], 'Explicit clearing supported');
check('publication_issue_not_found' === EasyHeadless_Publication_Issues::update(new WP_REST_Request(array('title' => 'No'), array('id' => 10)))->code, 'Cannot update regular posts through issue routes');
check('publication_issue_not_found' === EasyHeadless_Publication_Issues::inspect(new WP_REST_Request(array(), array('id' => 999)))->code, 'Missing issue returns 404');
check('publish' === EasyHeadless_Publication_Issues::inspect(new WP_REST_Request(array(), array('id' => $id)))['status'], 'Inspect reads editorial status');
$search = EasyHeadless_Publication_Issues::search(new WP_REST_Request(array(), array('search' => 'Article', 'page' => 2, 'perPage' => 100)));
check(array('post_title') === $search_args['search_columns'] && 'publish' === $search_args['post_status'], 'Title search published only');
check(50 === $search['pagination']['perPage'] && 99 === $search['items'][0]['featuredImageId'], 'Search capped and includes existing image ID');
check(!isset($search['items'][0]['content']), 'No story body returned');
check('issue_search_required' === EasyHeadless_Publication_Issues::search(new WP_REST_Request())->code, 'Empty search rejected');
echo "EasyHeadless issue editorial API tests passed.\n";
