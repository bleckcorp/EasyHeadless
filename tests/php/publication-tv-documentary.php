<?php
define('ABSPATH', __DIR__);
function absint($value) { return abs((int) $value); }
function get_post($id) { return null; }
class WP_Query {
    public $posts = array();
    public function __construct($args) { $GLOBALS['queries'][] = $args; }
}
require dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-publication.php';
function screen_assert($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
$feed = new ReflectionMethod('EasyHeadless_Publication', 'tv_documentary_stories');
$used = array(7, 9);
$GLOBALS['queries'] = array();
$stories = $feed->invokeArgs(null, array(array(7, 999), 4, &$used));
$query = $GLOBALS['queries'][0];
screen_assert(array('tv', 'documentary') === $query['tax_query'][0]['terms'], 'Both categories share one feed.');
screen_assert('category' === $query['tax_query'][0]['taxonomy'] && 'slug' === $query['tax_query'][0]['field'] && 'IN' === $query['tax_query'][0]['operator'], 'Automatic fill is category OR, not all posts or category intersection.');
screen_assert(array(7, 9) === $query['post__not_in'], 'Previously used posts remain excluded.');
screen_assert('publish' === $query['post_status'] && 4 === $query['posts_per_page'], 'Only published posts within the configured count.');
screen_assert('date' === $query['orderby'] && 'DESC' === $query['order'], 'Newest stories first.');
screen_assert(array() === $stories && 1 === count($GLOBALS['queries']), 'Missing categories/stories never trigger an unrelated fallback.');
echo "TV & Documentary combined feed tests passed.\n";
