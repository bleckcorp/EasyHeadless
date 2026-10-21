<?php
if (!defined('ABSPATH')) { exit; }

/** Optional editorial rating, independent of categories and article HTML. */
final class EasyHeadless_Publication_Review
{
    const META = '_easyheadless_review';

    public static function register_hooks()
    {
        add_action('add_meta_boxes_post', array(__CLASS__, 'meta_box'));
        add_action('save_post_post', array(__CLASS__, 'save'), 20, 2);
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('admin_notices', array(__CLASS__, 'notice'));
    }

    public static function fields($post_id)
    {
        $stored = get_post_meta($post_id, self::META, true);
        $result = self::validate(is_array($stored) ? $stored : array(), array('score' => null, 'description' => ''));
        return is_wp_error($result) ? array('score' => null, 'description' => '') : $result;
    }

    public static function public_data($post_id)
    {
        $fields = self::fields($post_id);
        return null === $fields['score'] ? null : $fields;
    }

    public static function validate($input, $current)
    {
        if (!is_array($input) || array_diff(array_keys($input), array('score', 'description'))) {
            return new WP_Error('review_invalid_fields', 'Use only score and description.', array('status' => 400));
        }
        if (array_key_exists('score', $input)) {
            $score = $input['score'];
            if (is_string($score)) { $score = trim($score); }
            if (null === $score || '' === $score) {
                $current['score'] = null;
            } elseif ((!is_int($score) && !is_float($score) && !is_string($score)) || !is_numeric($score) || !is_finite((float) $score) || (float) $score < 0 || (float) $score > 10 || abs((float) $score - round((float) $score, 1)) > 0.000001) {
                return new WP_Error('review_invalid_score', 'Enter a score from 0 to 10, with at most one decimal place, or leave it blank.', array('status' => 400));
            } else {
                $current['score'] = round((float) $score, 1);
            }
        }
        if (array_key_exists('description', $input)) {
            if (!is_string($input['description'])) {
                return new WP_Error('review_invalid_description', 'The verdict must be plain text.', array('status' => 400));
            }
            $current['description'] = sanitize_textarea_field($input['description']);
        }
        return $current;
    }

    public static function write($post_id, $input)
    {
        $next = self::validate($input, self::fields($post_id));
        if (is_wp_error($next)) { return $next; }
        update_post_meta($post_id, self::META, $next);
        if (self::fields($post_id) !== $next) {
            return new WP_Error('review_save_failed', 'The review score could not be saved.', array('status' => 500));
        }
        do_action('easyheadless_content_changed', $post_id, get_post($post_id));
        EasyHeadless_Publication::notify_frontend($post_id, 'post');
        return $next;
    }

    public static function meta_box()
    {
        if (EasyHeadless_Publication::enabled()) {
            add_meta_box('easyheadless-review', 'Efiko Score', array(__CLASS__, 'render'), 'post', 'side', 'default');
        }
    }

    public static function render($post)
    {
        $fields = self::fields($post->ID);
        wp_nonce_field('easyheadless_review', 'easyheadless_review_nonce');
        echo '<p><label for="eh-review-score">Score out of 10</label></p><input style="width:100%" id="eh-review-score" name="easyheadless_review_score" type="number" min="0" max="10" step="0.1" value="' . esc_attr(null === $fields['score'] ? '' : $fields['score']) . '">';
        echo '<p><label for="eh-review-description">Verdict / description</label></p><textarea style="width:100%" rows="5" id="eh-review-description" name="easyheadless_review_description">' . esc_textarea($fields['description']) . '</textarea>';
        echo '<p class="description">Optional for every article. The score and verdict appear below the article, before sharing. Leave the score blank to hide the card. A score of 0 is valid. Save or update the post to apply.</p>';
    }

    public static function save($post_id, $post)
    {
        if ('post' !== $post->post_type || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id) || !isset($_POST['easyheadless_review_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['easyheadless_review_nonce'])), 'easyheadless_review')) { return; }
        $input = array();
        if (array_key_exists('easyheadless_review_score', $_POST)) { $input['score'] = wp_unslash($_POST['easyheadless_review_score']); }
        if (array_key_exists('easyheadless_review_description', $_POST)) { $input['description'] = wp_unslash($_POST['easyheadless_review_description']); }
        if (!$input) { return; }
        $result = self::write($post_id, $input);
        if (is_wp_error($result)) {
            add_filter('redirect_post_location', function ($location) { return add_query_arg('eh_review_error', '1', $location); });
        }
    }

    public static function notice()
    {
        if (!empty($_GET['eh_review_error']) && current_user_can('edit_posts')) {
            echo '<div class="notice notice-error"><p>Efiko Score was not changed. Enter a score from 0 to 10 with at most one decimal place, then update the post again.</p></div>';
        }
    }

    public static function routes()
    {
        foreach (array(WP_REST_Server::READABLE => 'inspect', WP_REST_Server::EDITABLE => 'update') as $methods => $callback) {
            register_rest_route('easyheadless/v1/publication', '/posts/id/(?P<id>\d+)/review', array('methods' => $methods, 'callback' => array(__CLASS__, $callback), 'permission_callback' => array(__CLASS__, 'permission')));
        }
    }

    public static function permission($request)
    {
        $enabled = EasyHeadless_Publication::public_permission();
        return is_wp_error($enabled) ? $enabled : (new EasyHeadless_REST())->can_write($request);
    }

    public static function find($request)
    {
        $post = get_post(absint($request->get_param('id')));
        return $post && 'post' === $post->post_type && 'trash' !== $post->post_status ? $post : new WP_Error('review_post_not_found', 'Article not found.', array('status' => 404));
    }

    public static function inspect($request)
    {
        $post = self::find($request);
        return is_wp_error($post) ? $post : rest_ensure_response(array('postId' => (int) $post->ID, 'review' => self::fields($post->ID)));
    }

    public static function update($request)
    {
        $post = self::find($request);
        if (is_wp_error($post)) { return $post; }
        $result = self::write($post->ID, $request->get_json_params());
        return is_wp_error($result) ? $result : rest_ensure_response(array('postId' => (int) $post->ID, 'review' => $result));
    }
}
