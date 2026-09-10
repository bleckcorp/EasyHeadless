<?php

define('ABSPATH', __DIR__ . '/');

$options = array();
function update_option($key, $value, $autoload = null) { global $options; $options[$key] = $value; return true; }
function get_option($key, $default = false) { global $options; return array_key_exists($key, $options) ? $options[$key] : $default; }
function wp_generate_password($length = 12) { return str_repeat('x', $length); }
function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, is_array($args) ? $args : array()); }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function esc_url_raw($value) { return filter_var((string) $value, FILTER_SANITIZE_URL); }

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
publication_assert(array(4, 9) === EasyHeadless_Publication::sanitize_ids('4, 9, 4, invalid'), 'Post IDs are normalized and deduplicated.');

$curation = EasyHeadless_Publication::sanitize_curation(array(
    'hero_id' => '-17',
    'support_ids' => '2, 3, 3',
    'ideas' => array('category_id' => '12', 'post_ids' => '20 21'),
    'festivals' => array('category_id' => '15', 'post_ids' => '30, 31'),
    'the-cover' => array('category_id' => '16', 'post_ids' => '40, 41, 42'),
    'spotlight' => array('title' => '<b>AMVCA Week</b>', 'start_date' => '2026-05-01', 'end_date' => '2026-05-08'),
));
publication_assert(17 === $curation['hero_id'], 'Hero post ID is sanitized.');
publication_assert(array(2, 3) === $curation['support_ids'], 'Supporting story IDs are sanitized.');
publication_assert(12 === $curation['ideas']['category_id'] && array(20, 21) === $curation['ideas']['post_ids'], 'Section category and manual stories are retained.');
publication_assert(15 === $curation['festivals']['category_id'] && array(30, 31) === $curation['festivals']['post_ids'], 'Festivals curation is retained.');
publication_assert(16 === $curation['the-cover']['category_id'] && array(40, 41, 42) === $curation['the-cover']['post_ids'], 'The Cover curation is retained.');
publication_assert('AMVCA Week' === $curation['spotlight']['title'], 'Seasonal title is sanitized.');

$definitions = new ReflectionMethod('EasyHeadless_Publication', 'section_definitions');
$sections = $definitions->invoke(null);
publication_assert(3 === $sections['columns']['count'], 'Columns is limited to three stories.');
publication_assert('Cinema' === $sections['hot-hot-latest']['title'], 'The first feed is labelled Cinema.');
publication_assert(5 === $sections['festivals']['count'] && 5 === $sections['biz']['count'], 'Festivals and Biz each expose five stories.');
publication_assert('portrait' === $sections['the-cover']['layout'], 'The Cover uses the portrait layout.');
publication_assert(5 === $sections['ideas']['count'] && 5 === $sections['the-cover']['count'], 'Ideas and The Cover expose five-story editorial rails.');
publication_assert(!isset($sections['archive']) && !isset($sections['cover']), 'Legacy section keys are no longer exposed.');

$presentation = new ReflectionMethod('EasyHeadless_Publication', 'sanitize_presentation_style');
publication_assert('new-yorker' === $presentation->invoke(null, 'new-yorker', 'nation'), 'New Yorker presentation style is accepted.');
publication_assert('nation' === $presentation->invoke(null, 'unsupported', 'nation'), 'Unsupported presentation styles use the safe fallback.');
publication_assert('0.6.3' === EasyHeadless_Publication::SCHEMA_VERSION, 'Issue volume and article assignment schema is current.');
publication_assert('_easyheadless_issue_id' === EasyHeadless_Publication::META_ISSUE_ID, 'Stories use a dedicated issue assignment field.');
publication_assert('_easyheadless_issue_volume' === EasyHeadless_Publication::ISSUE_META_VOLUME, 'Issues use a dedicated volume label field.');

echo "EasyHeadless Publication configuration tests passed.\n";
