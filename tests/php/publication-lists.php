<?php
define('ABSPATH', __DIR__ . '/');
$category_available = true;
function get_category_by_slug($slug) { global $category_available; return $category_available && 'lists' === $slug ? (object) array('term_id' => 639, 'slug' => 'lists') : false; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower($value); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function esc_url_raw($value) { return $value; }
require dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-publication.php';
function lists_assert($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
$defaults = new ReflectionMethod('EasyHeadless_Publication', 'default_curation');
$curation = $defaults->invoke(null);
lists_assert(639 === $curation['lists']['category_id'] && 'category' === $curation['lists']['source'], 'Lists resolves its real WordPress category.');
lists_assert($curation['features']['layout'] === $curation['lists']['layout'] && $curation['features']['count'] === $curation['lists']['count'], 'Lists matches the Features card row.');
lists_assert('lists' === array_key_last($curation), 'Lists is the last homepage section.');
$sanitized = EasyHeadless_Publication::sanitize_curation(array('hero_id' => 7, 'features' => array('post_ids' => array(12))));
lists_assert(639 === $sanitized['lists']['category_id'], 'Saving old curation keeps the automatic Lists category.');
lists_assert(7 === $sanitized['hero_id'] && array(12) === $sanitized['features']['post_ids'], 'Existing selections survive.');
$category_available = false;
$curation = $defaults->invoke(null);
lists_assert(0 === $curation['lists']['category_id'] && 'category' === $curation['lists']['source'], 'Missing category does not become a latest-post source.');
echo "Publication Lists tests passed.\n";
