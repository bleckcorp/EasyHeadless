<?php

require __DIR__ . '/publication.php';

$content_meta = array();
function get_post_meta($id, $key, $single = true) { global $content_meta; return isset($content_meta[$key]) ? $content_meta[$key] : ''; }
function apply_filters($hook, $value) { return $value; }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function get_post($post) { return $post; }
function get_userdata($id) { return false; }
function get_the_terms($post, $taxonomy) { return array(); }
function get_the_title($post) { return 'Test article'; }
function has_excerpt($post) { return true; }
function get_the_excerpt($post) { return 'Public summary'; }
function wp_strip_all_tags($text) { return strip_tags($text); }
function get_post_time($format, $gmt, $post) { return '2026-10-03'; }
function get_post_modified_time($format, $gmt, $post) { return '2026-10-03'; }
function get_the_post_thumbnail_url($post, $size) { return ''; }
function get_post_thumbnail_id($post) { return 0; }
function wp_get_attachment_caption($id) { return ''; }

$post = (object) array('ID' => 1, 'post_author' => 1, 'post_name' => 'test', 'post_content' => '<p>First paragraph.</p><p>Second paragraph.</p>');
$html = EasyHeadless_Publication_Content::render($post);
publication_assert(1 === substr_count($html, 'has-drop-cap'), 'Only the opening paragraph gets an automatic drop cap.');
$html = EasyHeadless_Publication_Content::prepare('<div class="elementor-invisible"><p>Opening élan.</p></div><div class="elementor-widget-text-editor elementor-drop-cap-yes"><p><em>Middle</em> <a href="/story">link</a>.</p><p>Next paragraph.</p></div><figure><img src="/image.jpg" alt="Picture"></figure>');
publication_assert(2 === substr_count($html, 'has-drop-cap'), 'An Elementor widget can introduce another drop cap halfway through an article.');
publication_assert(false === strpos($html, 'elementor-invisible'), 'Content does not depend on Elementor animation JS to become visible.');
publication_assert(false !== strpos($html, '<em>Middle</em>') && false !== strpos($html, 'href="/story"') && false !== strpos($html, 'src="/image.jpg"') && false !== strpos($html, 'élan'), 'Inline formatting, links, images and Unicode survive.');
$html = EasyHeadless_Publication_Content::prepare('<p>First.</p><p class="has-drop-cap">Later.</p>');
publication_assert(2 === substr_count($html, 'has-drop-cap'), 'WordPress paragraph drop caps survive at any location.');
$html = EasyHeadless_Publication_Content::prepare('<div class="widget" data-settings="{&quot;drop_cap&quot;:&quot;yes&quot;}"><p>First.</p></div><div class="has-drop-cap"><p>Later.</p></div>');
publication_assert(3 === substr_count($html, 'has-drop-cap'), 'Settings and custom widget classes mark their paragraphs.');
$html = EasyHeadless_Publication_Content::prepare('<p><span class="elementor-drop-cap"><span class="elementor-drop-cap-letter">A</span></span> story.</p>');
publication_assert(false === strpos($html, 'has-drop-cap'), 'Existing drop-cap spans are not decorated twice.');

$html = EasyHeadless_Publication_Content::prepare('<div class="elementor-widget-text-editor"><p>First.</p><p>Second.</p><p>Third.</p></div><div class="elementor-widget-text-editor elementor-drop-cap-yes"><p>Fourth paragraph starts a new text widget.</p><p>Fifth stays ordinary.</p></div>');
$dom = new DOMDocument(); @$dom->loadHTML($html); $paragraphs = $dom->getElementsByTagName('p');
foreach (array(0 => true, 1 => false, 2 => false, 3 => true, 4 => false) as $index => $expected) {
    publication_assert((strpos($paragraphs->item($index)->getAttribute('class'), 'has-drop-cap') !== false) === $expected, 'Only the opening and selected fourth paragraphs receive drop caps.');
}

$content_meta['_elementor_edit_mode'] = 'builder';
$content_meta['_elementor_data'] = json_encode(array(array('elements' => array(array('widgetType' => 'heading', 'settings' => array('title' => 'Heading', 'header_size' => 'h3')), array('widgetType' => 'text-editor', 'settings' => array('editor' => '<p>Opening.</p>')), array('widgetType' => 'text-editor', 'settings' => array('editor' => '<p>Later <strong>section</strong>.</p>', 'drop_cap' => 'yes')), array('widgetType' => 'image', 'settings' => array('image' => array('url' => '/photo.jpg', 'alt' => 'Photo'), 'caption' => 'Caption'))))));
$post->post_content = '';
$html = EasyHeadless_Publication_Content::render($post);
publication_assert(false !== strpos($html, '<h3>Heading</h3>') && false !== strpos($html, '<strong>section</strong>') && false !== strpos($html, '<figcaption>Caption</figcaption>'), 'Saved basic Elementor widgets render when Elementor is unavailable.');
publication_assert(2 === substr_count($html, 'has-drop-cap'), 'Fallback retains later widget drop-cap settings.');

eval('namespace Elementor; class Plugin { public $frontend; public static $html = ""; public static function instance() { $plugin = new self(); $plugin->frontend = $plugin; return $plugin; } public function get_builder_content($id, $css) { if (self::$html === "throw") { throw new \\RuntimeException("Unavailable"); } return self::$html; } }');
\Elementor\Plugin::$html = '<div><p>Native Elementor content.</p><p>Private second paragraph.</p><p>Hidden third paragraph.</p></div>';
$html = EasyHeadless_Publication_Content::render($post);
publication_assert(false !== strpos($html, 'Native Elementor content.'), 'Native Elementor output is used when available.');
$content_meta[EasyHeadless_Publication::META_ACCESS] = 'members';
$payload = EasyHeadless_Publication::normalize_story($post);
publication_assert(!isset($payload['content']) && false === strpos($payload['preview'], 'Hidden third'), 'Gated native content is absent from the public response beyond the configured preview.');
$payload = EasyHeadless_Publication::normalize_story($post, true);
publication_assert(false !== strpos($payload['content'], 'Hidden third'), 'Authenticated content includes the complete rendered article.');
\Elementor\Plugin::$html = '';
publication_assert(false !== strpos(EasyHeadless_Publication_Content::render($post), 'Heading'), 'Empty native output falls back to saved widgets.');
\Elementor\Plugin::$html = 'throw';
publication_assert(false !== strpos(EasyHeadless_Publication_Content::render($post), 'Heading'), 'Renderer exceptions do not blank stored article content.');
echo "Publication editorial content tests passed.\n";
