<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Authenticated editorial operations. Never convert or rewrite source posts. */
final class EasyHeadless_Publication_Issues
{
    public static function register_routes()
    {
        $namespace = 'easyheadless/v1/publication';
        foreach (array(
            array('/issues', WP_REST_Server::CREATABLE, 'create'),
            array('/issues/id/(?P<id>\d+)', WP_REST_Server::READABLE, 'inspect'),
            array('/issues/id/(?P<id>\d+)', WP_REST_Server::EDITABLE, 'update'),
            array('/story-search', WP_REST_Server::READABLE, 'search'),
        ) as $route) {
            register_rest_route($namespace, $route[0], array(
                'methods' => $route[1],
                'callback' => array(__CLASS__, $route[2]),
                'permission_callback' => array(__CLASS__, 'permission'),
            ));
        }
    }

    public static function permission($request)
    {
        $enabled = EasyHeadless_Publication::public_permission();
        return is_wp_error($enabled) ? $enabled : (new EasyHeadless_REST())->can_write($request);
    }

    public static function search($request)
    {
        $search = sanitize_text_field((string) $request->get_param('search'));
        if ('' === $search) {
            return new WP_Error('issue_search_required', 'Enter an article title to search.', array('status' => 400));
        }
        $page = max(1, (int) $request->get_param('page'));
        $limit = min(50, max(1, (int) ($request->get_param('perPage') ?: 20)));
        $query = new WP_Query(array(
            'post_type' => 'post', 'post_status' => 'publish', 's' => $search,
            'search_columns' => array('post_title'), 'paged' => $page, 'posts_per_page' => $limit,
        ));
        return rest_ensure_response(array(
            'items' => array_map(function ($post) {
                return array(
                    'id' => (int) $post->ID, 'slug' => $post->post_name,
                    'title' => get_the_title($post),
                    'featuredImageId' => (int) get_post_thumbnail_id($post),
                    'featuredImage' => get_the_post_thumbnail_url($post, 'full') ?: '',
                    'issueId' => (int) get_post_meta($post->ID, EasyHeadless_Publication::META_ISSUE_ID, true),
                );
            }, $query->posts),
            'pagination' => array('page' => $page, 'perPage' => $limit, 'total' => (int) $query->found_posts, 'totalPages' => (int) $query->max_num_pages),
        ));
    }

    private static function find($id)
    {
        $post = get_post((int) $id);
        return $post && EasyHeadless_Publication::ISSUE_TYPE === $post->post_type && 'trash' !== $post->post_status
            ? $post : new WP_Error('publication_issue_not_found', 'Issue not found.', array('status' => 404));
    }

    private static function editorial_response($post)
    {
        $result = EasyHeadless_Publication::normalize_issue($post);
        $result['status'] = $post->post_status;
        $result['featuredImageId'] = (int) get_post_thumbnail_id($post);
        $result['featuredStoryId'] = (int) get_post_meta($post->ID, EasyHeadless_Publication::ISSUE_META_FEATURED_STORY, true);
        $result['storyIds'] = EasyHeadless_Publication::sanitize_ids(get_post_meta($post->ID, EasyHeadless_Publication::ISSUE_META_RELATED_STORIES, true));
        return rest_ensure_response($result);
    }

    public static function inspect($request)
    {
        $post = self::find($request->get_param('id'));
        return is_wp_error($post) ? $post : self::editorial_response($post);
    }

    public static function create($request)
    {
        return self::write($request, 0);
    }

    public static function update($request)
    {
        $post = self::find($request->get_param('id'));
        return is_wp_error($post) ? $post : self::write($request, $post->ID);
    }

    private static function write($request, $id)
    {
        $body = $request->get_json_params();
        if (!is_array($body) || !$body) {
            return new WP_Error('issue_input_required', 'Issue fields are required.', array('status' => 400));
        }
        $allowed = array('title', 'slug', 'description', 'excerpt', 'status', 'date', 'volume', 'featuredImageId', 'featuredStoryId', 'storyIds');
        if (array_diff(array_keys($body), $allowed)) {
            return new WP_Error('issue_invalid_field', 'Unsupported issue field.', array('status' => 400));
        }
        foreach (array('title', 'slug', 'description', 'excerpt', 'status', 'date', 'volume') as $field) {
            if (array_key_exists($field, $body) && !is_string($body[$field])) {
                return new WP_Error('issue_invalid_field', 'Issue text fields must be strings.', array('status' => 400));
            }
        }
        if ((!$id && empty($body['title'])) || (array_key_exists('title', $body) && '' === sanitize_text_field($body['title']))) {
            return new WP_Error('issue_title_required', 'An issue title is required.', array('status' => 400));
        }
        if (isset($body['status']) && !in_array($body['status'], array('draft', 'pending', 'publish', 'private'), true)) {
            return new WP_Error('issue_invalid_status', 'Invalid issue status.', array('status' => 400));
        }
        if (isset($body['date']) && '' !== $body['date']) {
            $date = \DateTime::createFromFormat('!Y-m-d', $body['date']);
            if (!$date || $date->format('Y-m-d') !== $body['date']) {
                return new WP_Error('issue_invalid_date', 'Use a valid YYYY-MM-DD issue date.', array('status' => 400));
            }
        }
        $slug = isset($body['slug']) ? sanitize_title($body['slug']) : (!$id ? sanitize_title($body['title']) : null);
        if (null !== $slug) {
            if ('' === $slug) {
                return new WP_Error('issue_invalid_slug', 'An issue slug is required.', array('status' => 400));
            }
            // Also detect draft editions, whose slugs WordPress does not reserve.
            $matches = get_posts(array('post_type' => EasyHeadless_Publication::ISSUE_TYPE, 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'name' => $slug, 'numberposts' => -1));
            foreach ($matches as $match) {
                if ((int) $match->ID !== (int) $id) {
                    return new WP_Error('issue_slug_exists', 'This issue already exists. Inspect and update it instead.', array('status' => 409, 'issueId' => (int) $match->ID));
                }
            }
        }
        foreach (array('featuredImageId', 'featuredStoryId') as $field) {
            if (array_key_exists($field, $body) && (!is_int($body[$field]) || $body[$field] < 0)) {
                return new WP_Error('issue_invalid_id', 'Attachment and story IDs must be nonnegative integers.', array('status' => 400));
            }
        }
        if (!empty($body['featuredImageId']) && !wp_attachment_is_image($body['featuredImageId'])) {
            return new WP_Error('issue_invalid_cover', 'The cover must be an existing image attachment.', array('status' => 400));
        }
        if (array_key_exists('storyIds', $body) && (!is_array($body['storyIds']) || count($body['storyIds']) > 100)) {
            return new WP_Error('issue_invalid_stories', 'Supply up to 100 story IDs.', array('status' => 400));
        }
        $stories = isset($body['storyIds']) ? $body['storyIds'] : array();
        if (!empty($body['featuredStoryId'])) {
            $stories[] = $body['featuredStoryId'];
        }
        foreach ($stories as $story_id) {
            if (!is_int($story_id) || $story_id <= 0 || 'post' !== get_post_type($story_id) || 'publish' !== get_post_status($story_id)) {
                return new WP_Error('issue_invalid_story', 'Every linked story must be an existing published article.', array('status' => 400));
            }
        }
        // All validation happens before the first write. Omitted fields remain untouched.
        $post = $id ? array('ID' => $id) : array('post_type' => EasyHeadless_Publication::ISSUE_TYPE, 'post_status' => 'draft');
        foreach (array('title' => 'post_title', 'description' => 'post_content', 'excerpt' => 'post_excerpt', 'status' => 'post_status') as $field => $key) {
            if (array_key_exists($field, $body)) {
                $post[$key] = 'description' === $field ? wp_kses_post($body[$field]) : sanitize_text_field($body[$field]);
            }
        }
        if (null !== $slug) {
            $post['post_name'] = $slug;
        }
        $result = $id ? wp_update_post(wp_slash($post), true) : wp_insert_post(wp_slash($post), true);
        if (is_wp_error($result)) {
            return $result;
        }
        foreach (array('date' => EasyHeadless_Publication::ISSUE_META_DATE, 'volume' => EasyHeadless_Publication::ISSUE_META_VOLUME, 'featuredStoryId' => EasyHeadless_Publication::ISSUE_META_FEATURED_STORY, 'storyIds' => EasyHeadless_Publication::ISSUE_META_RELATED_STORIES) as $field => $key) {
            if (array_key_exists($field, $body)) {
                $value = 'storyIds' === $field ? EasyHeadless_Publication::sanitize_ids($body[$field]) : ('featuredStoryId' === $field ? $body[$field] : sanitize_text_field($body[$field]));
                update_post_meta($result, $key, $value);
            }
        }
        if (array_key_exists('featuredImageId', $body)) {
            $body['featuredImageId'] ? set_post_thumbnail($result, $body['featuredImageId']) : delete_post_thumbnail($result);
        }
        // Metadata is written after save_post; notify again with the completed issue.
        do_action('easyheadless_content_changed', $result, EasyHeadless_Publication::ISSUE_TYPE);
        return self::editorial_response(get_post($result));
    }
}
