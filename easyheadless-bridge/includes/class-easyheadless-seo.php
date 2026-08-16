<?php

if (!defined('ABSPATH')) {
    exit;
}

final class EasyHeadless_SEO
{
    public static function is_yoast_available()
    {
        return defined('WPSEO_VERSION') || class_exists('WPSEO_Frontend');
    }

    public static function for_post($post_id)
    {
        $post = get_post($post_id);

        if (!$post) {
            return new stdClass();
        }

        $payload = array(
            'title' => html_entity_decode(get_the_title($post), ENT_QUOTES),
            'description' => wp_strip_all_tags(get_the_excerpt($post)),
            'canonical' => get_permalink($post),
            'yoastHead' => null,
            'raw' => new stdClass(),
        );

        if (self::is_yoast_available() && class_exists('YoastSEO')) {
            try {
                $presentation = YoastSEO()->meta->for_post($post_id);
                $payload['title'] = $presentation->title ? $presentation->title : $payload['title'];
                $payload['description'] = $presentation->meta_description ? $presentation->meta_description : $payload['description'];
                $payload['canonical'] = $presentation->canonical ? $presentation->canonical : $payload['canonical'];
                $payload['raw'] = $presentation;
            } catch (Throwable $error) {
                $payload['raw'] = array('error' => $error->getMessage());
            }
        }

        if (self::is_yoast_available() && function_exists('wpseo_frontend_head_init')) {
            $payload['yoastHead'] = self::capture_yoast_head();
        }

        $profile = class_exists('EasyHeadless_Modules') ? EasyHeadless_Modules::site_profile() : array();
        if (
            !empty($profile['frontend_url'])
            && $post instanceof WP_Post
            && in_array($post->post_type, array('page', 'post'), true)
        ) {
            $path = 'post' === $post->post_type
                ? '/insights/' . $post->post_name
                : '/' . trim(get_page_uri($post), '/');
            $payload['canonical'] = rtrim($profile['frontend_url'], '/') . ($path ? $path : '/');
        }

        return $payload;
    }

    private static function capture_yoast_head()
    {
        ob_start();
        do_action('wpseo_head');
        $head = ob_get_clean();

        return $head ? trim($head) : null;
    }
}
