<?php

define('ABSPATH', __DIR__ . '/');

$options = array();
$term_meta = array();
$updated_posts = array();
function update_option($key, $value, $autoload = null) { global $options; $options[$key] = $value; return true; }
function get_option($key, $default = false) { global $options; return array_key_exists($key, $options) ? $options[$key] : $default; }
function get_category_by_slug($slug) { return 'cover' === $slug ? (object) array('term_id' => 16, 'name' => 'The Cover', 'slug' => 'cover', 'count' => 2, 'parent' => 0) : false; }
function get_term_meta($term_id, $key, $single = false) { global $term_meta; return isset($term_meta[$term_id][$key]) ? $term_meta[$term_id][$key] : ''; }
function update_term_meta($term_id, $key, $value) { global $term_meta; $term_meta[$term_id][$key] = $value; return true; }
function get_posts($args = array()) { return isset($args['category']) && 16 === $args['category'] ? array(101, 102) : array(); }
function wp_update_post($post) { global $updated_posts; $updated_posts[] = $post; return $post['ID']; }
function wp_generate_password($length = 12) { return str_repeat('x', $length); }
function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, is_array($args) ? $args : array()); }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function esc_url_raw($value) { return filter_var((string) $value, FILTER_SANITIZE_URL); }
function wp_kses_post($value) { return (string) $value; }
function term_description($term_id, $taxonomy) { return 'Cover archive'; }

require_once dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-publication.php';

function publication_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

publication_assert('0' === EasyHeadless_Publication::sanitize_enabled(''), 'Publication stays disabled for an empty setting.');
publication_assert('1' === EasyHeadless_Publication::sanitize_enabled('1'), 'Publication can be enabled explicitly.');
publication_assert(EasyHeadless_Publication::enabled(), 'Publication defaults to enabled.');
$options[EasyHeadless_Publication::OPTION_ENABLED] = '0';
$options[EasyHeadless_Publication::OPTION_SCHEMA_VERSION] = '0.6.1';
$options[EasyHeadless_Publication::OPTION_CURATION] = array('hero_id' => 7615);
EasyHeadless_Publication::maybe_upgrade();
publication_assert(EasyHeadless_Publication::enabled(), 'Upgrade enables the previously disabled module.');
publication_assert(7615 === $options[EasyHeadless_Publication::OPTION_CURATION]['hero_id'], 'Upgrade preserves curation.');
$options[EasyHeadless_Publication::OPTION_ENABLED] = '0';
EasyHeadless_Publication::maybe_upgrade();
publication_assert(!EasyHeadless_Publication::enabled(), 'Explicit opt-out after upgrade remains respected.');
$options[EasyHeadless_Publication::OPTION_SCHEMA_VERSION] = '0.6.4';
EasyHeadless_Publication::maybe_upgrade();
publication_assert(!EasyHeadless_Publication::enabled(), 'A 0.6.5 schema upgrade does not re-enable an explicitly disabled Publication module.');
$options[EasyHeadless_Publication::OPTION_ENABLED] = '1';
$options[EasyHeadless_Publication::OPTION_SCHEMA_VERSION] = '0.6.3';
$options[EasyHeadless_Publication::OPTION_CURATION] = array('ideas' => array('category_id' => 12, 'post_ids' => array(20)));
EasyHeadless_Publication::maybe_upgrade();
publication_assert('category' === $options[EasyHeadless_Publication::OPTION_CURATION]['ideas']['source'], 'Legacy category curation migrates to the category source.');
publication_assert('editorial-rail' === $options[EasyHeadless_Publication::OPTION_CURATION]['ideas']['layout'], 'Legacy Ideas curation receives its slider presentation.');
publication_assert(array(4, 9) === EasyHeadless_Publication::sanitize_ids('4, 9, 4, invalid'), 'Post IDs are normalized and deduplicated.');

$curation = EasyHeadless_Publication::sanitize_curation(array(
    'hero_id' => '-17',
    'support_ids' => '2, 3, 3',
    'ideas' => array('title' => '<b>Analysis</b>', 'category_id' => '12', 'post_ids' => '20 21', 'source' => 'category', 'layout' => 'editorial-rail', 'count' => '8', 'enabled' => '1', 'order' => '4', 'href' => '/category/ideas'),
    'festivals' => array('category_id' => '15', 'post_ids' => '30, 31'),
    'the-cover' => array('category_id' => '16', 'post_ids' => '40, 41, 42'),
    'spotlight' => array('title' => '<b>AMVCA Week</b>', 'start_date' => '2026-05-01', 'end_date' => '2026-05-08'),
));
publication_assert(17 === $curation['hero_id'], 'Hero post ID is sanitized.');
publication_assert(array(2, 3) === $curation['support_ids'], 'Supporting story IDs are sanitized.');
publication_assert(12 === $curation['ideas']['category_id'] && array(20, 21) === $curation['ideas']['post_ids'], 'Section category and manual stories are retained.');
publication_assert('Analysis' === $curation['ideas']['title'], 'Section display titles are sanitized.');
publication_assert('category' === $curation['ideas']['source'] && 'editorial-rail' === $curation['ideas']['layout'], 'Section source and presentation style are retained.');
publication_assert(8 === $curation['ideas']['count'] && '1' === $curation['ideas']['enabled'] && 4 === $curation['ideas']['order'], 'Section count, visibility and order are normalized.');
publication_assert('/category/ideas' === $curation['ideas']['href'], 'Section links are retained.');
publication_assert(15 === $curation['festivals']['category_id'] && array(30, 31) === $curation['festivals']['post_ids'] && 'category' === $curation['festivals']['source'], 'Legacy section input retains its category source and manual stories.');
publication_assert(16 === $curation['the-cover']['category_id'] && array(40, 41, 42) === $curation['the-cover']['post_ids'], 'The Cover curation is retained.');
publication_assert('AMVCA Week' === $curation['spotlight']['title'], 'Seasonal title is sanitized.');

$definitions = new ReflectionMethod('EasyHeadless_Publication', 'section_definitions');
$sections = $definitions->invoke(null);
publication_assert(3 === $sections['columns']['count'], 'Columns is limited to three stories.');
publication_assert('Cinema' === $sections['hot-hot-latest']['title'], 'The first feed is labelled Cinema.');
publication_assert(5 === $sections['festivals']['count'] && 5 === $sections['biz']['count'], 'Festivals and Biz each expose five stories.');
publication_assert('editorial-rail' === $sections['ideas']['layout'], 'Ideas uses the editorial slider layout.');
publication_assert('portrait-rail' === $sections['the-cover']['layout'], 'The Cover uses the portrait slider layout.');
publication_assert(5 === $sections['ideas']['count'] && 5 === $sections['the-cover']['count'], 'Ideas and The Cover expose five-story editorial rails.');
publication_assert(!isset($sections['archive']) && !isset($sections['cover']), 'Legacy section keys are no longer exposed.');

$presentation = new ReflectionMethod('EasyHeadless_Publication', 'sanitize_presentation_style');
publication_assert('new-yorker' === $presentation->invoke(null, 'new-yorker', 'nation'), 'New Yorker presentation style is accepted.');
publication_assert('nation' === $presentation->invoke(null, 'unsupported', 'nation'), 'Unsupported presentation styles use the safe fallback.');
$archive_mode = new ReflectionMethod('EasyHeadless_Publication', 'sanitize_archive_mode');
publication_assert('issues' === $archive_mode->invoke(null, 'issues'), 'Issue archive behaviour is accepted.');
publication_assert('stories' === $archive_mode->invoke(null, 'unsupported'), 'Unsupported archive behaviour uses the story-list fallback.');
$term_meta[16][EasyHeadless_Publication::TERM_META_ARCHIVE_MODE] = 'issues';
$category = EasyHeadless_Publication::normalize_category(get_category_by_slug('cover'));
publication_assert('issues' === $category['archiveMode'], 'Category responses expose the configured issue archive behaviour.');
$migrate_posts = new ReflectionMethod('EasyHeadless_Publication', 'migrate_category_posts_to_issues');
$migrate_posts->invoke(null, 16);
publication_assert(array(array('ID' => 101, 'post_type' => EasyHeadless_Publication::ISSUE_TYPE), array('ID' => 102, 'post_type' => EasyHeadless_Publication::ISSUE_TYPE)) === $updated_posts, 'Legacy category posts can be converted to issue records without replacing their IDs.');
publication_assert('0.6.6' === EasyHeadless_Publication::SCHEMA_VERSION, 'Dynamic issue archive schema is current.');
publication_assert('_easyheadless_issue_id' === EasyHeadless_Publication::META_ISSUE_ID, 'Stories use a dedicated issue assignment field.');
publication_assert('_easyheadless_issue_volume' === EasyHeadless_Publication::ISSUE_META_VOLUME, 'Issues use a dedicated volume label field.');

echo "EasyHeadless Publication configuration tests passed.\n";
