<?php
if (!defined('ABSPATH')) { exit; }

final class EasyHeadless_Curation_Editor
{
    public static function render($curation, $defaults, $sections, $posts, $categories, $option)
    {
        $values = array();
        foreach ($sections as $key => $definition) {
            $values[$key] = wp_parse_args(isset($curation[$key]) ? $curation[$key] : array(), $defaults[$key]);
        }
        ?>
        <form method="post" action="options.php" class="eh-curation-editor" data-eh-curation-editor>
            <?php settings_fields('easyheadless_publication_curation'); ?>
            <div class="eh-curation-workspace">
                <nav class="eh-curation-tabs" aria-label="Homepage section editor">
                    <button type="button" id="eh-tab-opening" data-eh-section-tab="opening" aria-controls="eh-panel-opening">Opening stories</button>
                    <?php foreach ($values as $key => $value) : ?>
                    <button type="button" id="eh-tab-<?php echo esc_attr($key); ?>" data-eh-section-tab="<?php echo esc_attr($key); ?>" aria-controls="eh-panel-<?php echo esc_attr($key); ?>"><?php echo esc_html($value['title'] ?: $sections[$key]['title']); ?></button>
                    <?php endforeach; ?>
                </nav>
                <div class="eh-curation-panels">
                    <section class="eh-curation-panel" id="eh-panel-opening" data-eh-section-panel="opening" aria-labelledby="eh-tab-opening">
                        <h2>Opening stories</h2>
                        <div class="eh-curation-fields">
                            <label><span>Hero story</span><select name="<?php echo esc_attr($option); ?>[hero_id]"><option value="0">Latest story automatically</option><?php foreach ($posts as $post) : ?><option value="<?php echo esc_attr($post->ID); ?>" <?php selected($curation['hero_id'], $post->ID); ?>><?php echo esc_html($post->post_title); ?></option><?php endforeach; ?></select></label>
                            <label><span>Supporting stories</span><input name="<?php echo esc_attr($option); ?>[support_ids]" value="<?php echo esc_attr(implode(', ', $curation['support_ids'])); ?>"><small>Up to three post IDs, comma separated. Missing positions use the latest stories.</small></label>
                        </div>
                    </section>
                    <?php foreach ($values as $key => $value) : $prefix = $option . '[' . $key . ']'; ?>
                    <section class="eh-curation-panel" id="eh-panel-<?php echo esc_attr($key); ?>" data-eh-section-panel="<?php echo esc_attr($key); ?>" aria-labelledby="eh-tab-<?php echo esc_attr($key); ?>">
                        <h2 data-eh-panel-title><?php echo esc_html($value['title'] ?: $sections[$key]['title']); ?></h2>
                        <label class="eh-curation-display"><input type="hidden" name="<?php echo esc_attr($prefix); ?>[enabled]" value="0"><input type="checkbox" name="<?php echo esc_attr($prefix); ?>[enabled]" value="1" <?php checked('1', $value['enabled']); ?>> Show this section</label>
                        <div class="eh-curation-fields">
                            <div class="eh-curation-column">
                                <label><span>Displayed title</span><input name="<?php echo esc_attr($prefix); ?>[title]" value="<?php echo esc_attr($value['title']); ?>" data-eh-section-title><small>Leave blank to use the selected category name or default section name.</small></label>
                                <label><span>Content source</span><?php self::select($prefix . '[source]', $value['source'], array('latest' => 'Latest stories', 'popular' => 'Popular stories', 'category' => 'Selected category')); ?></label>
                                <label><span>WordPress category</span><select name="<?php echo esc_attr($prefix); ?>[category_id]"><option value="0">Choose a category</option><?php foreach ($categories as $category) : ?><option value="<?php echo esc_attr($category->term_id); ?>" <?php selected($value['category_id'], $category->term_id); ?>><?php echo esc_html($category->name); ?></option><?php endforeach; ?></select></label>
                                <label><span>Presentation style</span><?php self::select($prefix . '[layout]', $value['layout'], array('rail' => 'Card row', 'lead' => 'Lead story + list', 'feature' => 'Feature grid', 'portrait' => 'Portrait cards', 'editorial-rail' => 'Editorial slider', 'portrait-rail' => 'Portrait slider')); ?></label>
                            </div>
                            <div class="eh-curation-column">
                                <label><span>Number of stories</span><input type="number" min="1" max="12" name="<?php echo esc_attr($prefix); ?>[count]" value="<?php echo esc_attr($value['count']); ?>"></label>
                                <label><span>Homepage position</span><input type="number" min="0" max="100" name="<?php echo esc_attr($prefix); ?>[order]" value="<?php echo esc_attr($value['order']); ?>"></label>
                                <label><span>Custom link</span><input name="<?php echo esc_attr($prefix); ?>[href]" value="<?php echo esc_attr($value['href']); ?>" placeholder="/a-page or https://example.com"></label>
                                <label><span>Selected stories</span><input name="<?php echo esc_attr($prefix); ?>[post_ids]" value="<?php echo esc_attr(implode(', ', $value['post_ids'])); ?>"><small>Post IDs, comma separated. Leave blank for automatic selection.</small></label>
                            </div>
                            <?php if ('spotlight' === $key) : ?><div class="eh-curation-dates"><label><span>Active from</span><input type="date" name="<?php echo esc_attr($prefix); ?>[start_date]" value="<?php echo esc_attr($value['start_date']); ?>"></label><label><span>Active until</span><input type="date" name="<?php echo esc_attr($prefix); ?>[end_date]" value="<?php echo esc_attr($value['end_date']); ?>"></label></div><?php endif; ?>
                        </div>
                    </section>
                    <?php endforeach; ?>
                    <div class="eh-curation-save"><button type="submit" class="button button-primary">Save homepage curation</button><span data-eh-save-status role="status" aria-live="polite"></span></div>
                </div>
            </div>
        </form>
        <?php
    }

    private static function select($name, $value, $options)
    {
        echo '<select name="' . esc_attr($name) . '">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"';
            selected($value, $key);
            echo '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }
}
