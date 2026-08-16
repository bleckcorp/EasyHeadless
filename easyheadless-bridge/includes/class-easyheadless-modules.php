<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dependency-light feature registry and normalized data providers.
 *
 * Native WordPress options are the primary source for generic settings. Existing
 * ACF option values remain a non-destructive fallback for older installations.
 */
final class EasyHeadless_Modules
{
    const OPTION_SITE_PROFILE = 'easyheadless_site_profile';
    const OPTION_ENABLED_MODULES = 'easyheadless_enabled_modules';
    const OPTION_PORTFOLIO = 'easyheadless_portfolio_items';
    const OPTION_FEATURED_COURSES = 'easyheadless_featured_courses';
    const OPTION_ALLOWED_FORMS = 'easyheadless_allowed_forms';

    public static function defaults()
    {
        return array(
            'company_name' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'short_description' => '',
            'frontend_url' => '',
            'lms_url' => '',
            'login_url' => '',
            'registration_url' => '',
            'dashboard_url' => '',
            'courses_url' => '',
            'navigation_location' => '',
            'headless_routing' => false,
            'revalidation_url' => '',
            'revalidation_secret' => '',
            'social_links' => array(),
        );
    }

    public static function sanitize_site_profile($value)
    {
        $value = is_array($value) ? $value : array();
        $clean = self::defaults();
        $text_fields = array('company_name', 'phone', 'email', 'address', 'short_description', 'navigation_location');
        $url_fields = array(
            'frontend_url',
            'lms_url',
            'login_url',
            'registration_url',
            'dashboard_url',
            'courses_url',
            'revalidation_url',
        );

        foreach ($text_fields as $key) {
            if (isset($value[$key])) {
                $clean[$key] = 'email' === $key
                    ? sanitize_email($value[$key])
                    : sanitize_textarea_field($value[$key]);
            }
        }

        foreach ($url_fields as $key) {
            if (isset($value[$key])) {
                $clean[$key] = rtrim(esc_url_raw($value[$key]), '/');
            }
        }

        $clean['headless_routing'] = !empty($value['headless_routing']);
        $clean['revalidation_secret'] = isset($value['revalidation_secret'])
            ? sanitize_text_field($value['revalidation_secret'])
            : '';
        $clean['social_links'] = self::sanitize_social_links(
            isset($value['social_links']) ? $value['social_links'] : array()
        );

        return $clean;
    }

    public static function site_profile()
    {
        $acf = EasyHeadless_ACF::get_option_fields();
        $acf = is_array($acf) ? $acf : array();
        $native = get_option(self::OPTION_SITE_PROFILE, array());
        $native = is_array($native) ? $native : array();
        $profile = self::defaults();

        foreach ($profile as $key => $default) {
            if (array_key_exists($key, $native) && (is_bool($native[$key]) || '' !== $native[$key]) && array() !== $native[$key]) {
                $profile[$key] = $native[$key];
            } elseif (array_key_exists($key, $acf) && '' !== $acf[$key] && array() !== $acf[$key]) {
                $profile[$key] = $acf[$key];
            }
        }

        if (!$profile['company_name']) {
            $profile['company_name'] = get_bloginfo('name');
        }
        if (!$profile['short_description']) {
            $profile['short_description'] = get_bloginfo('description');
        }
        if (!$profile['email']) {
            $profile['email'] = get_option('admin_email');
        }

        return $profile;
    }

    public static function enabled_modules()
    {
        $saved = get_option(self::OPTION_ENABLED_MODULES, array());
        $saved = is_array($saved) ? $saved : array();

        return array_merge(
            array(
                'core' => true,
                'portfolio' => true,
                'church' => true,
                'tutor' => true,
                'forms' => true,
            ),
            array_map(function ($value) {
                return (bool) $value;
            }, $saved)
        );
    }

    public static function sanitize_enabled_modules($value)
    {
        $value = is_array($value) ? $value : array();
        $clean = array('core' => true);

        foreach (array('portfolio', 'church', 'tutor', 'forms') as $module) {
            $clean[$module] = !empty($value[$module]);
        }

        return $clean;
    }

    public static function is_enabled($module)
    {
        $modules = self::enabled_modules();
        return !empty($modules[$module]);
    }

    public static function capabilities($forms = null)
    {
        $enabled = self::enabled_modules();
        $tutor_available = post_type_exists('courses') || function_exists('tutor');
        $forms_health = $forms instanceof EasyHeadless_Forms ? $forms->health() : array();
        $fluent_available = !empty($forms_health['fluentforms']['available']);
        $fluent_message = isset($forms_health['fluentforms']['message'])
            ? $forms_health['fluentforms']['message']
            : 'Fluent Forms was not detected.';

        $capabilities = array(
            'core' => self::module_status(true, true),
            'portfolio' => self::module_status(!empty($enabled['portfolio']), true),
            'church' => self::module_status(!empty($enabled['church']), EasyHeadless_ACF::is_available(), EasyHeadless_ACF::is_available() ? '' : 'ACF is unavailable; core post content remains readable.'),
            'tutor' => self::module_status(!empty($enabled['tutor']), $tutor_available, $tutor_available ? '' : 'Tutor LMS course post type was not detected.'),
            'forms' => self::module_status(!empty($enabled['forms']), $fluent_available, $fluent_available ? '' : $fluent_message),
        );

        if (class_exists('EasyHeadless_Updater')) {
            $capabilities['updater'] = EasyHeadless_Updater::instance()->capability_status();
        }

        return $capabilities;
    }

    public static function portal_links()
    {
        $profile = self::site_profile();
        $lms = $profile['lms_url'] ? $profile['lms_url'] : home_url('/');

        return array(
            'login' => $profile['login_url'] ? $profile['login_url'] : rtrim($lms, '/') . '/dashboard/',
            'registration' => $profile['registration_url'] ? $profile['registration_url'] : rtrim($lms, '/') . '/student-registration/',
            'dashboard' => $profile['dashboard_url'] ? $profile['dashboard_url'] : rtrim($lms, '/') . '/dashboard/',
            'courses' => $profile['courses_url'] ? $profile['courses_url'] : rtrim($lms, '/') . '/courses/',
        );
    }

    public static function navigation()
    {
        $profile = self::site_profile();
        $locations = get_nav_menu_locations();
        $location = sanitize_key($profile['navigation_location']);

        if (!$location) {
            foreach (array('primary', 'menu-1', 'header', 'main-menu') as $candidate) {
                if (!empty($locations[$candidate])) {
                    $location = $candidate;
                    break;
                }
            }
        }

        $menu_id = $location && !empty($locations[$location]) ? absint($locations[$location]) : 0;
        if (!$menu_id) {
            $menus = wp_get_nav_menus();
            $menu_id = !empty($menus) ? absint($menus[0]->term_id) : 0;
        }

        $items = $menu_id ? wp_get_nav_menu_items($menu_id) : array();
        $normalized = array();

        foreach (is_array($items) ? $items : array() as $item) {
            if ('publish' !== $item->post_status) {
                continue;
            }

            $normalized[] = array(
                'id' => (int) $item->ID,
                'parentId' => (int) $item->menu_item_parent,
                'label' => html_entity_decode($item->title, ENT_QUOTES),
                'url' => self::frontend_navigation_url($item->url, $item->object, $item->object_id),
                'target' => $item->target ? $item->target : '_self',
                'order' => (int) $item->menu_order,
            );
        }

        return $normalized;
    }

    public static function portfolio()
    {
        if (!self::is_enabled('portfolio')) {
            return array();
        }

        $saved = get_option(self::OPTION_PORTFOLIO, array());
        $saved = is_array($saved) ? $saved : array();
        $items = array();

        foreach (array_values($saved) as $index => $item) {
            $item = is_array($item) ? $item : array();
            $attachment_id = isset($item['attachmentId']) ? absint($item['attachmentId']) : 0;
            if (!$attachment_id || 'attachment' !== get_post_type($attachment_id)) {
                continue;
            }

            $metadata = wp_get_attachment_metadata($attachment_id);
            $metadata = is_array($metadata) ? $metadata : array();
            $attachment = get_post($attachment_id);
            $items[] = array(
                'id' => $attachment_id,
                'attachmentId' => $attachment_id,
                'url' => wp_get_attachment_image_url($attachment_id, 'full'),
                'srcSet' => wp_get_attachment_image_srcset($attachment_id, 'full'),
                'sizes' => wp_get_attachment_image_sizes($attachment_id, 'full'),
                'width' => isset($metadata['width']) ? absint($metadata['width']) : 0,
                'height' => isset($metadata['height']) ? absint($metadata['height']) : 0,
                'alt' => !empty($item['alt']) ? sanitize_text_field($item['alt']) : get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                'title' => !empty($item['title']) ? sanitize_text_field($item['title']) : get_the_title($attachment_id),
                'caption' => !empty($item['caption']) ? wp_kses_post($item['caption']) : ($attachment ? wp_kses_post($attachment->post_excerpt) : ''),
                'category' => !empty($item['category']) ? sanitize_title($item['category']) : 'all',
                'order' => $index,
            );
        }

        return $items;
    }

    public static function sanitize_portfolio($value)
    {
        if (is_string($value)) {
            $decoded = json_decode(wp_unslash($value), true);
            $value = is_array($decoded) ? $decoded : array();
        }

        $value = is_array($value) ? $value : array();
        $clean = array();

        foreach ($value as $item) {
            if (!is_array($item) || empty($item['attachmentId'])) {
                continue;
            }

            $clean[] = array(
                'attachmentId' => absint($item['attachmentId']),
                'title' => isset($item['title']) ? sanitize_text_field($item['title']) : '',
                'caption' => isset($item['caption']) ? wp_kses_post($item['caption']) : '',
                'alt' => isset($item['alt']) ? sanitize_text_field($item['alt']) : '',
                'category' => isset($item['category']) ? sanitize_title($item['category']) : 'all',
            );
        }

        return $clean;
    }

    public static function courses($args = array())
    {
        if (!self::is_enabled('tutor') || !post_type_exists('courses')) {
            return array(
                'items' => array(),
                'pagination' => array('page' => 1, 'perPage' => 6, 'total' => 0, 'totalPages' => 0),
                'source' => 'unavailable',
            );
        }

        $page = max(1, isset($args['page']) ? absint($args['page']) : 1);
        $per_page = min(50, max(1, isset($args['perPage']) ? absint($args['perPage']) : 6));
        $selected = isset($args['selected']) && is_array($args['selected'])
            ? array_values(array_filter(array_map('absint', $args['selected'])))
            : self::featured_course_ids();
        $query_args = array(
            'post_type' => 'courses',
            'post_status' => 'publish',
            'paged' => $page,
            'posts_per_page' => $per_page,
        );
        $source = 'latest';

        if ($selected) {
            $query_args['post__in'] = $selected;
            $query_args['orderby'] = 'post__in';
            $query_args['posts_per_page'] = count($selected);
            $source = 'curated';
        }

        if (!empty($args['category'])) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'course-category',
                    'field' => 'slug',
                    'terms' => sanitize_title($args['category']),
                ),
            );
            $source = 'category';
        }

        $query = new WP_Query($query_args);

        return array(
            'items' => array_map(array(__CLASS__, 'normalize_course'), $query->posts),
            'pagination' => array(
                'page' => $page,
                'perPage' => $per_page,
                'total' => (int) $query->found_posts,
                'totalPages' => (int) $query->max_num_pages,
            ),
            'source' => $source,
        );
    }

    public static function course_by_slug($slug)
    {
        if (!self::is_enabled('tutor') || !post_type_exists('courses')) {
            return null;
        }

        $course = get_page_by_path(sanitize_title($slug), OBJECT, 'courses');
        if (!$course || 'publish' !== $course->post_status) {
            return null;
        }

        return self::normalize_course($course);
    }

    public static function normalize_course($course)
    {
        $course_id = (int) $course->ID;
        $thumbnail_id = get_post_thumbnail_id($course_id);
        $terms = get_the_terms($course_id, 'course-category');
        $categories = array();

        if (is_array($terms)) {
            foreach ($terms as $term) {
                $categories[] = array(
                    'id' => (int) $term->term_id,
                    'name' => html_entity_decode($term->name, ENT_QUOTES),
                    'slug' => $term->slug,
                );
            }
        }

        $price = get_post_meta($course_id, '_tutor_course_price', true);
        $price_type = get_post_meta($course_id, '_tutor_course_price_type', true);
        $duration = get_post_meta($course_id, '_course_duration', true);
        $rating = null;

        if (function_exists('tutor_utils')) {
            try {
                $rating_value = tutor_utils()->get_course_rating($course_id);
                if (is_object($rating_value)) {
                    $rating = array(
                        'average' => isset($rating_value->rating_avg) ? (float) $rating_value->rating_avg : 0,
                        'count' => isset($rating_value->rating_count) ? (int) $rating_value->rating_count : 0,
                    );
                } elseif (is_array($rating_value)) {
                    $rating = array(
                        'average' => isset($rating_value['rating_avg']) ? (float) $rating_value['rating_avg'] : 0,
                        'count' => isset($rating_value['rating_count']) ? (int) $rating_value['rating_count'] : 0,
                    );
                }
            } catch (Throwable $error) {
                $rating = null;
            }
        }

        return array(
            'id' => $course_id,
            'slug' => $course->post_name,
            'title' => html_entity_decode(get_the_title($course), ENT_QUOTES),
            'excerpt' => wp_strip_all_tags(get_the_excerpt($course)),
            'image' => $thumbnail_id ? array(
                'id' => (int) $thumbnail_id,
                'url' => wp_get_attachment_image_url($thumbnail_id, 'large'),
                'alt' => get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true),
            ) : null,
            'categories' => $categories,
            'instructor' => array(
                'id' => (int) $course->post_author,
                'name' => get_the_author_meta('display_name', $course->post_author),
            ),
            'rating' => $rating,
            'duration' => is_array($duration) ? $duration : ($duration ? (string) $duration : null),
            'price' => $price ? (string) $price : null,
            'isFree' => !$price || 'free' === $price_type,
            'lmsUrl' => get_permalink($course_id),
            'modified' => get_post_modified_time(DATE_ATOM, true, $course),
        );
    }

    public static function featured_course_ids()
    {
        $ids = get_option(self::OPTION_FEATURED_COURSES, array());
        return is_array($ids) ? array_values(array_filter(array_map('absint', $ids))) : array();
    }

    public static function sanitize_featured_courses($value)
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value);
        }

        return is_array($value) ? array_values(array_filter(array_map('absint', $value))) : array();
    }

    public static function allowed_form_ids()
    {
        $ids = get_option(self::OPTION_ALLOWED_FORMS, array());
        return is_array($ids) ? array_values(array_unique(array_map('sanitize_text_field', $ids))) : array();
    }

    public static function sanitize_allowed_forms($value)
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value);
        }

        return is_array($value)
            ? array_values(array_filter(array_unique(array_map('sanitize_text_field', $value))))
            : array();
    }

    public static function maybe_redirect_headless_request()
    {
        if (is_admin() || wp_doing_ajax() || is_feed() || is_preview() || !self::is_enabled('core')) {
            return;
        }

        if (
            (function_exists('is_cart') && is_cart())
            || (function_exists('is_checkout') && is_checkout())
            || (function_exists('is_account_page') && is_account_page())
        ) {
            return;
        }

        $request_path = isset($_SERVER['REQUEST_URI'])
            ? trim((string) wp_parse_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH), '/')
            : '';
        foreach (array('wp-json', 'wp-admin', 'wp-login.php', 'courses', 'course', 'cart', 'checkout', 'my-account', 'dashboard', 'student-registration', 'instructor-registration') as $protected_path) {
            if ($request_path === $protected_path || 0 === strpos($request_path, $protected_path . '/')) {
                return;
            }
        }

        $profile = self::site_profile();
        if (empty($profile['headless_routing']) || empty($profile['frontend_url']) || !is_singular(array('page', 'post'))) {
            return;
        }

        $post = get_queried_object();
        if (!$post instanceof WP_Post) {
            return;
        }

        $path = self::frontend_content_path($post);
        $target = rtrim($profile['frontend_url'], '/') . ($path ? $path : '/');
        wp_redirect(esc_url_raw($target), 301, 'EasyHeadless');
        exit;
    }

    public static function send_revalidation($post_id, $post = null)
    {
        $profile = self::site_profile();
        if (empty($profile['revalidation_url']) || empty($profile['revalidation_secret'])) {
            return;
        }

        $post = $post instanceof WP_Post ? $post : get_post($post_id);
        wp_remote_post(
            $profile['revalidation_url'],
            array(
                'timeout' => 5,
                'blocking' => false,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $profile['revalidation_secret'],
                ),
                'body' => wp_json_encode(array(
                    'id' => (int) $post_id,
                    'type' => $post ? $post->post_type : 'unknown',
                    'slug' => $post ? $post->post_name : '',
                )),
            )
        );
    }

    private static function module_status($enabled, $available, $message = '')
    {
        return array(
            'enabled' => (bool) $enabled,
            'available' => (bool) $available,
            'status' => !$enabled ? 'disabled' : ($available ? 'ready' : 'degraded'),
            'message' => $message,
        );
    }

    private static function sanitize_social_links($links)
    {
        $links = is_array($links) ? $links : array();
        $clean = array();

        foreach ($links as $link) {
            if (!is_array($link) || empty($link['url'])) {
                continue;
            }

            $clean[] = array(
                'label' => isset($link['label']) ? sanitize_text_field($link['label']) : '',
                'url' => esc_url_raw($link['url']),
            );
        }

        return $clean;
    }

    private static function frontend_navigation_url($url, $object, $object_id)
    {
        $profile = self::site_profile();
        if (empty($profile['frontend_url']) || !in_array($object, array('page', 'post'), true)) {
            return esc_url_raw($url);
        }

        $post = get_post($object_id);
        if (!$post) {
            return esc_url_raw($url);
        }

        $path = self::frontend_content_path($post);

        return rtrim($profile['frontend_url'], '/') . ($path ? $path : '/');
    }

    private static function frontend_content_path($post)
    {
        if ('post' === $post->post_type) {
            return '/insights/' . $post->post_name;
        }

        if ((int) get_option('page_on_front') === (int) $post->ID) {
            return '/';
        }

        return '/' . trim(get_page_uri($post), '/');
    }
}
