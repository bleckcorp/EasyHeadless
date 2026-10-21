<?php
require __DIR__ . '/publication.php';
function get_post_meta($id, $key, $single = true) { return isset($GLOBALS['presentation_post_meta'][$key]) ? $GLOBALS['presentation_post_meta'][$key] : ''; }
function get_the_terms($post, $taxonomy) { return array((object) array('term_id' => 16)); }
$resolved = new ReflectionMethod('EasyHeadless_Publication', 'resolved_presentation_style');
$term_meta[16][EasyHeadless_Publication::TERM_META_PRESENTATION_STYLE] = 'new-yorker-white';
publication_assert('new-yorker-white' === $resolved->invoke(null, (object) array('ID' => 1)), 'Stories inherit White from their category.');
foreach (array('nation', 'new-yorker', 'new-yorker-white') as $style) {
    $GLOBALS['presentation_post_meta'][EasyHeadless_Publication::META_PRESENTATION_STYLE] = $style;
    publication_assert($style === $resolved->invoke(null, (object) array('ID' => 1)), 'Explicit article style overrides its category: ' . $style);
}
$GLOBALS['presentation_post_meta'] = array();
$term_meta[16][EasyHeadless_Publication::TERM_META_PRESENTATION_STYLE] = 'new-yorker';
publication_assert('new-yorker' === $resolved->invoke(null, (object) array('ID' => 1)), 'Existing Black category value stays compatible.');
$term_meta[17][EasyHeadless_Publication::TERM_META_PRESENTATION_STYLE] = 'new-yorker-white';
publication_assert('new-yorker-white' === EasyHeadless_Publication::normalize_category((object) array('term_id' => 17, 'name' => 'White', 'slug' => 'white', 'count' => 1, 'parent' => 0))['presentationStyle'], 'Category responses preserve White.');
echo "EasyHeadless article presentation tests passed.\n";
