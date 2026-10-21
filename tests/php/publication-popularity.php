<?php
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
$options = array('easyheadless_view_events_schema' => '1');
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; }
function absint($value) { return abs((int) $value); }
function get_term_children($id, $taxonomy) { return $id === 9 ? array(10) : array(); }
function is_wp_error($value) { return false; }
function wp_list_pluck($items, $field) { return array_column($items, $field); }
function expect_popularity($condition, $message) { if (!$condition) { throw new Exception($message); } }
class Popularity_DB {
    public $prefix = 'wp_';
    public $posts = 'wp_posts';
    public $term_relationships = 'wp_term_relationships';
    public $term_taxonomy = 'wp_term_taxonomy';
    public $db;
    public $fail_insert = false;
    public function __construct() {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('CREATE TABLE wp_posts (ID INTEGER PRIMARY KEY, post_type TEXT, post_status TEXT, post_date_gmt TEXT);
          CREATE TABLE wp_eh_publication_view_events (id INTEGER PRIMARY KEY, post_id INTEGER, viewed_at TEXT);
          CREATE TABLE wp_term_relationships (object_id INTEGER, term_taxonomy_id INTEGER);
          CREATE TABLE wp_term_taxonomy (term_taxonomy_id INTEGER, term_id INTEGER, taxonomy TEXT)');
    }
    public function prepare($sql, ...$args) {
        if (is_array($args[0])) { $args = $args[0]; }
        $index = 0;
        return preg_replace_callback('/%[sd]/', function ($match) use (&$index, $args) {
            $value = $args[$index++];
            return $match[0] === '%d' ? (string) (int) $value : $this->db->quote($value);
        }, $sql);
    }
    public function insert($table, $row, $formats) {
        if ($this->fail_insert) { return false; }
        $stmt = $this->db->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')');
        $stmt->execute(array_values($row));
        return $stmt->rowCount();
    }
    public function query($sql) { return $this->db->exec($sql); }
    public function get_col($sql) { return $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN); }
}
$wpdb = new Popularity_DB();
require dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-publication-popularity.php';
function view_event($post, $seconds_ago, $count) {
    for ($i = 0; $i < $count; $i++) {
        $GLOBALS['wpdb']->insert('wp_eh_publication_view_events', array('post_id' => $post, 'viewed_at' => gmdate('Y-m-d H:i:s', time() - $seconds_ago)), array());
    }
}
$wpdb->query("INSERT INTO wp_posts VALUES (1,'post','publish','2020-01-01'),(2,'post','publish','2026-10-20'),(3,'post','publish','2026-10-19'),(4,'post','draft','2026-10-21'),(5,'page','publish','2026-10-21')");
view_event(1, 60, 5); // Old article is most read now.
view_event(2, 60, 2);
view_event(2, 8 * DAY_IN_SECONDS, 100); // Historic popularity must not count.
view_event(3, 7 * DAY_IN_SECONDS - 60, 1); // Just inside the window.
view_event(3, 7 * DAY_IN_SECONDS + 60, 100); // Just outside the window.
view_event(4, 60, 50); view_event(5, 60, 50); view_event(999, 60, 50);
view_event(2, -DAY_IN_SECONDS, 100); // Future timestamps must not count.
expect_popularity(EasyHeadless_Publication_Popularity::ranked_ids(6) === array(1, 2, 3), 'Rank recent view events, not publication age or lifetime totals; exclude drafts, pages and missing posts.');
expect_popularity(EasyHeadless_Publication_Popularity::ranked_ids(1, array(1)) === array(2), 'Respect prior stories and requested limit.');
$wpdb->query("INSERT INTO wp_term_taxonomy VALUES (100,10,'category'),(101,9,'post_tag'); INSERT INTO wp_term_relationships VALUES (1,100),(1,100),(2,101)");
expect_popularity(EasyHeadless_Publication_Popularity::ranked_ids(6, array(), 9) === array(1), 'Category includes descendants without double-counting views or accepting tags.');
expect_popularity(EasyHeadless_Publication_Popularity::ranked_ids(6, array(1,2,3)) === array(), 'Empty ranking remains empty for random fallback.');
expect_popularity(EasyHeadless_Publication_Popularity::record(3), 'Accepted view persists.');
expect_popularity((int) $wpdb->db->query('SELECT COUNT(*) FROM wp_eh_publication_view_events WHERE post_id=3')->fetchColumn() === 102, 'New event stored independently.');
expect_popularity((int) $wpdb->db->query("SELECT COUNT(*) FROM wp_eh_publication_view_events WHERE viewed_at < '" . gmdate('Y-m-d H:i:s', time()-8*DAY_IN_SECONDS) . "'")->fetchColumn() === 0, 'Expired view data is pruned.');
$wpdb->fail_insert = true;
expect_popularity(!EasyHeadless_Publication_Popularity::record(3), 'Storage failure cannot report success.');

// Exercise the actual feed assembler: empty rankings must never become an unfiltered
// WP_Query (WordPress treats an empty post__in as all posts).
class WP_Query {
    public $posts = array();
    public function __construct($args) { $GLOBALS['feed_queries'][] = $args; }
}
require dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-publication.php';
$feed = new ReflectionMethod('EasyHeadless_Publication', 'curated_stories');
$used = array();
$GLOBALS['feed_queries'] = array();
$feed->invokeArgs(null, array(array(), 0, 6, &$used, true));
expect_popularity($GLOBALS['feed_queries'][0]['post__in'] === array(1,2,3) && $GLOBALS['feed_queries'][0]['orderby'] === 'post__in', 'Feed preserves seven-day popularity order.');
expect_popularity(!isset($GLOBALS['feed_queries'][0]['date_query'], $GLOBALS['feed_queries'][0]['meta_key']), 'Feed no longer uses publication date or lifetime metadata.');
expect_popularity($GLOBALS['feed_queries'][1]['orderby'] === 'rand', 'Random stories fill remaining positions.');
$used = array(1,2,3);
$GLOBALS['feed_queries'] = array();
$feed->invokeArgs(null, array(array(), 0, 6, &$used, true));
expect_popularity(count($GLOBALS['feed_queries']) === 1 && $GLOBALS['feed_queries'][0]['orderby'] === 'rand', 'No recent views goes straight to random fallback, not latest or lifetime rank.');
expect_popularity($GLOBALS['feed_queries'][0]['post__not_in'] === array(1,2,3), 'Random fallback preserves exclusions.');
echo "Rolling seven-day popularity SQL and persistence tests passed.\n";
