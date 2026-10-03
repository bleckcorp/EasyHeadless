<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Server-rendered editorial content; no Elementor browser runtime required. */
final class EasyHeadless_Publication_Content
{
    public static function render($post)
    {
        $html = '';
        $builder = 'builder' === get_post_meta($post->ID, '_elementor_edit_mode', true);
        if ($builder && class_exists('\\Elementor\\Plugin')) {
            try {
                // The display helper can return empty for the current post to avoid recursion.
                $html = (string) \Elementor\Plugin::instance()->frontend->get_builder_content($post->ID, false);
            } catch (\Throwable $error) {
                // Preserve the stored editorial content when Elementor cannot render.
            }
        }
        if ('' === trim($html)) {
            $html = (string) apply_filters('the_content', $post->post_content);
        }
        if ($builder && '' === trim(strip_tags($html, '<img><iframe><video>'))) {
            $data = get_post_meta($post->ID, '_elementor_data', true);
            $data = is_string($data) ? json_decode($data, true) : $data;
            $html = self::stored_elements(is_array($data) ? $data : array()) ?: $html;
        }
        return self::prepare($html);
    }

    /** Fallback for basic native editorial widgets, not interactive Elementor layouts. */
    private static function stored_elements($elements)
    {
        $html = '';
        foreach ($elements as $element) {
            $settings = isset($element['settings']) ? $element['settings'] : array();
            $type = isset($element['widgetType']) ? $element['widgetType'] : '';
            if ('text-editor' === $type) {
                $class = isset($settings['drop_cap']) && 'yes' === $settings['drop_cap'] ? 'elementor-drop-cap-yes' : '';
                $html .= '<div class="' . $class . '">' . wp_kses_post(isset($settings['editor']) ? $settings['editor'] : '') . '</div>';
            } elseif ('heading' === $type) {
                $tag = isset($settings['header_size']) && in_array($settings['header_size'], array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div'), true) ? $settings['header_size'] : 'h2';
                $html .= '<' . $tag . '>' . wp_kses_post(isset($settings['title']) ? $settings['title'] : '') . '</' . $tag . '>';
            } elseif ('image' === $type && !empty($settings['image']['url'])) {
                $html .= '<figure><img src="' . esc_url($settings['image']['url']) . '" alt="' . esc_attr(isset($settings['image']['alt']) ? $settings['image']['alt'] : '') . '" loading="lazy">';
                if (!empty($settings['caption'])) {
                    $html .= '<figcaption>' . wp_kses_post($settings['caption']) . '</figcaption>';
                }
                $html .= '</figure>';
            }
            if (!empty($element['elements']) && is_array($element['elements'])) {
                $html .= self::stored_elements($element['elements']);
            }
        }
        return $html;
    }

    public static function prepare($html)
    {
        if ('' === trim($html) || !class_exists('DOMDocument')) {
            return $html;
        }
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="UTF-8"><html><body><div id="eh-content-root">' . $html . '</div></body></html>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return $html;
        }
        $xpath = new \DOMXPath($document);
        $root = $document->getElementById('eh-content-root');
        if (!$root) {
            return $html;
        }
        // Elementor normally reveals animated widgets using JS; that runtime isn't on the frontend.
        foreach ($xpath->query('.//*[@class]', $root) as $node) {
            $classes = preg_split('/\s+/', trim($node->getAttribute('class')));
            $node->setAttribute('class', implode(' ', array_diff($classes, array('elementor-invisible'))));
            $settings = json_decode($node->getAttribute('data-settings'), true);
            $drop_cap = in_array('elementor-drop-cap-yes', $classes, true) || in_array('has-drop-cap', $classes, true) || (is_array($settings) && isset($settings['drop_cap']) && 'yes' === $settings['drop_cap']);
            if ($drop_cap && 'p' !== strtolower($node->nodeName)) {
                $paragraph = $xpath->query('.//p[normalize-space(.) != ""]', $node)->item(0);
                if ($paragraph) {
                    self::mark_paragraph($paragraph);
                }
            }
        }
        $first = $xpath->query('.//p[normalize-space(.) != ""]', $root)->item(0);
        if ($first) {
            self::mark_paragraph($first);
        }
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }
        return $result;
    }

    private static function mark_paragraph($paragraph)
    {
        // Avoid a second decorative letter if a widget already emitted its own span.
        if ($paragraph->getElementsByTagName('span')->length) {
            foreach ($paragraph->getElementsByTagName('span') as $span) {
                if (preg_match('/(?:^|\s)elementor-drop-cap(?:\s|$)/', $span->getAttribute('class'))) {
                    return;
                }
            }
        }
        $classes = preg_split('/\s+/', trim($paragraph->getAttribute('class')));
        $classes[] = 'has-drop-cap';
        $paragraph->setAttribute('class', trim(implode(' ', array_unique($classes))));
    }
}
