<?php
require __DIR__ . '/publication-content.php';
function wp_get_post_terms($id, $taxonomy) { return array(); }
function is_wp_error($value) { return false; }
$post->post_content = '<p>Issue body, not the summary.</p>';
$post->post_excerpt = 'The <em>editor-selected</em> summary.';
$issue = EasyHeadless_Publication::normalize_issue($post);
publication_assert('The editor-selected summary.' === $issue['excerpt'], 'Issue response uses the authored excerpt.');
publication_assert(false !== strpos($issue['description'], 'Issue body'), 'Full description stays separate.');
$post->post_excerpt = '';
publication_assert('' === EasyHeadless_Publication::normalize_issue($post)['excerpt'], 'An empty excerpt is not replaced by generated body/category text.');
echo "Issue excerpt response tests passed.\n";
