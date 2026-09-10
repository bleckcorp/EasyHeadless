<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optional publication features for editorial sites.
 *
 * This module owns its routes, storage, admin screens, and post normalization so
 * enabling it cannot change the established EasyHeadless core contracts.
 */
final class EasyHeadless_Publication
{
    const OPTION_ENABLED = 'easyheadless_publication_enabled';
    const OPTION_SETTINGS = 'easyheadless_publication_settings';
    const OPTION_CURATION = 'easyheadless_publication_curation';
    const OPTION_SCHEMA_VERSION = 'easyheadless_publication_schema_version';
    const META_ACCESS = '_easyheadless_access';
    const META_PREVIEW_PARAGRAPHS = '_easyheadless_preview_paragraphs';
    const META_GATE_MESSAGE = '_easyheadless_gate_message';
    const META_VIEWS = '_easyheadless_publication_views';
    const META_PRESENTATION_STYLE = '_easyheadless_presentation_style';
    const META_ISSUE_ID = '_easyheadless_issue_id';
    const TERM_META_PRESENTATION_STYLE = '_easyheadless_presentation_style';
    const ISSUE_META_DATE = '_easyheadless_issue_date';
    const ISSUE_META_VOLUME = '_easyheadless_issue_volume';
    const ISSUE_META_FEATURED_STORY = '_easyheadless_featured_story';
    const ISSUE_META_RELATED_STORIES = '_easyheadless_related_stories';
    const SCHEMA_VERSION = '0.6.3';
    const ISSUE_TYPE = 'eh_issue';
    const SESSION_DAYS = 30;
    const CODE_MINUTES = 10;

    public static function register_hooks()
    {
        add_action('init', array(__CLASS__, 'register_issue_type'));
        add_action('init', array(__CLASS__, 'register_linked_story_block'));
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('admin_menu', array(__CLASS__, 'register_admin_pages'), 30);
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_init', array(__CLASS__, 'maybe_upgrade'));
        add_action('add_meta_boxes_post', array(__CLASS__, 'register_post_meta_box'));
        add_action('save_post_post', array(__CLASS__, 'save_post_access'), 10, 2);
        add_action('add_meta_boxes_' . self::ISSUE_TYPE, array(__CLASS__, 'register_issue_meta_box'));
        add_action('save_post_' . self::ISSUE_TYPE, array(__CLASS__, 'save_issue_meta'), 10, 2);
        add_action('admin_post_easyheadless_publication_export', array(__CLASS__, 'export_contacts'));
        add_action('admin_post_easyheadless_publication_delete_contact', array(__CLASS__, 'delete_contact'));
        add_action('easyheadless_content_changed', array(__CLASS__, 'notify_frontend'), 20, 2);
        add_action('category_add_form_fields', array(__CLASS__, 'render_category_style_add'));
        add_action('category_edit_form_fields', array(__CLASS__, 'render_category_style_edit'));
        add_action('created_category', array(__CLASS__, 'save_category_style'));
        add_action('edited_category', array(__CLASS__, 'save_category_style'));
    }

    public static function activate()
    {
        global $wpdb;

        if (false === get_option(self::OPTION_ENABLED, false)) {
            add_option(self::OPTION_ENABLED, '1');
        }
        if (false === get_option(self::OPTION_SETTINGS, false)) {
            add_option(self::OPTION_SETTINGS, self::default_settings());
        }
        if (false === get_option(self::OPTION_CURATION, false)) {
            add_option(self::OPTION_CURATION, self::default_curation());
        }

        self::maybe_upgrade();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$wpdb->prefix}eh_publication_contacts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(190) NOT NULL,
            verified_at datetime NULL,
            newsletter_consent tinyint(1) NOT NULL DEFAULT 0,
            source_article varchar(200) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email)
        ) {$charset};");
        dbDelta("CREATE TABLE {$wpdb->prefix}eh_publication_codes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(190) NOT NULL,
            code_hash varchar(255) NOT NULL,
            request_ip varchar(100) NOT NULL DEFAULT '',
            attempts smallint(5) unsigned NOT NULL DEFAULT 0,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY email_created (email,created_at),
            KEY request_ip (request_ip)
        ) {$charset};");
        dbDelta("CREATE TABLE {$wpdb->prefix}eh_publication_sessions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            token_hash char(64) NOT NULL,
            expires_at datetime NOT NULL,
            revoked_at datetime NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_hash (token_hash),
            KEY user_id (user_id)
        ) {$charset};");
    }

    public static function maybe_upgrade()
    {
        if (version_compare((string) get_option(self::OPTION_SCHEMA_VERSION, '0'), self::SCHEMA_VERSION, '<')) {
            self::migrate_curation();
            // Apply the new default once; later explicit opt-outs remain respected.
            update_option(self::OPTION_ENABLED, '1');
            update_option(self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false);
        }
    }

    public static function enabled()
    {
        return '1' === (string) get_option(self::OPTION_ENABLED, '1');
    }

    private static function default_settings()
    {
        return array(
            'donation_url' => '',
            'newsletter_provider' => 'wordpress',
            'newsletter_list_id' => '',
            'frontend_secret' => wp_generate_password(48, false, false),
            'revalidation_url' => '',
            'revalidation_secret' => '',
            'social_facebook' => '',
            'social_instagram' => '',
            'social_x' => '',
        );
    }

    private static function default_curation()
    {
        $sections = array('hot-hot-latest', 'hot-hot', 'columns', 'big-interview', 'ideas', 'festivals', 'reviews', 'tv-documentary', 'spotlight', 'biz', 'features', 'the-cover');
        $curation = array('hero_id' => 0, 'support_ids' => array());
        foreach ($sections as $section) {
            $curation[$section] = array('category_id' => 0, 'post_ids' => array());
        }
        $curation['spotlight']['title'] = 'Seasonal Spotlight';
        $curation['spotlight']['start_date'] = '';
        $curation['spotlight']['end_date'] = '';
        return $curation;
    }

    private static function migrate_curation()
    {
        $stored = get_option(self::OPTION_CURATION, array());
        if (!is_array($stored)) {
            $stored = array();
        }
        $migrated = wp_parse_args($stored, self::default_curation());
        if (isset($stored['archive']) && !isset($stored['festivals'])) {
            $migrated['ideas'] = $stored['archive'];
            $migrated['festivals'] = isset($stored['ideas']) ? $stored['ideas'] : self::default_curation()['festivals'];
        }
        if (isset($stored['cover']) && !isset($stored['biz'])) {
            $migrated['biz'] = $stored['cover'];
        }
        unset($migrated['archive'], $migrated['cover']);
        update_option(self::OPTION_CURATION, $migrated, false);
    }

    public static function register_issue_type()
    {
        if (!self::enabled()) {
            return;
        }
        register_post_type(self::ISSUE_TYPE, array(
            'labels' => array('name' => 'Issues', 'singular_name' => 'Issue', 'add_new_item' => 'Add New Issue', 'edit_item' => 'Edit Issue'),
            'public' => true,
            'show_in_rest' => true,
            'show_in_menu' => 'easyheadless',
            'menu_icon' => 'dashicons-book-alt',
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'),
            'has_archive' => false,
            'rewrite' => array('slug' => 'issues'),
        ));
        register_post_meta(self::ISSUE_TYPE, '_easyheadless_issue_date', array('type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => function () { return current_user_can('edit_posts'); }));
        register_post_meta(self::ISSUE_TYPE, self::ISSUE_META_VOLUME, array('type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => function () { return current_user_can('edit_posts'); }));
        register_post_meta(self::ISSUE_TYPE, self::ISSUE_META_FEATURED_STORY, array('type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => function () { return current_user_can('edit_posts'); }));
        register_post_meta(self::ISSUE_TYPE, self::ISSUE_META_RELATED_STORIES, array('type' => 'array', 'single' => true, 'show_in_rest' => array('schema' => array('type' => 'array', 'items' => array('type' => 'integer'))), 'sanitize_callback' => array(__CLASS__, 'sanitize_ids'), 'auth_callback' => function () { return current_user_can('edit_posts'); }));
        register_post_meta('post', self::META_ISSUE_ID, array('type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => function () { return current_user_can('edit_posts'); }));
    }

    public static function register_linked_story_block()
    {
        if (!self::enabled() || !function_exists('register_block_type')) {
            return;
        }
        wp_register_script(
            'easyheadless-linked-story-editor',
            plugins_url('../assets/publication-linked-story.js', __FILE__),
            array('wp-blocks', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-element'),
            EASYHEADLESS_VERSION,
            true
        );
        register_block_type('easyheadless/linked-story', array(
            'api_version' => 2,
            'editor_script' => 'easyheadless-linked-story-editor',
            'attributes' => array(
                'postId' => array('type' => 'integer', 'default' => 0),
                'variant' => array('type' => 'string', 'default' => 'card'),
            ),
            'render_callback' => array(__CLASS__, 'render_linked_story_block'),
        ));
    }

    public static function render_linked_story_block($attributes)
    {
        $post_id = absint(isset($attributes['postId']) ? $attributes['postId'] : 0);
        $variant = isset($attributes['variant']) && 'image' === $attributes['variant'] ? 'image' : 'card';
        $post = $post_id ? get_post($post_id) : null;
        if (!$post || 'post' !== $post->post_type || 'publish' !== $post->post_status || !has_post_thumbnail($post)) {
            return '';
        }
        $image = get_the_post_thumbnail($post, 'large', array('loading' => 'lazy'));
        $headline = 'card' === $variant ? '<figcaption>' . esc_html(get_the_title($post)) . '</figcaption>' : '';
        return '<figure class="eh-linked-story eh-linked-story--' . esc_attr($variant) . '"><a href="' . esc_url('/stories/' . $post->post_name) . '">' . $image . $headline . '</a></figure>';
    }

    public static function render_category_style_add()
    {
        ?>
        <div class="form-field"><label for="easyheadless-presentation-style">Presentation style</label>
            <select id="easyheadless-presentation-style" name="easyheadless_presentation_style"><option value="nation">Nation / standard</option><option value="new-yorker">New Yorker inspired</option></select>
            <p>Controls the category page and the default presentation of stories in this category.</p></div>
        <?php
    }

    public static function render_category_style_edit($term)
    {
        $style = self::sanitize_presentation_style(get_term_meta($term->term_id, self::TERM_META_PRESENTATION_STYLE, true), 'nation');
        ?>
        <tr class="form-field"><th scope="row"><label for="easyheadless-presentation-style">Presentation style</label></th><td>
            <select id="easyheadless-presentation-style" name="easyheadless_presentation_style"><option value="nation" <?php selected($style, 'nation'); ?>>Nation / standard</option><option value="new-yorker" <?php selected($style, 'new-yorker'); ?>>New Yorker inspired</option></select>
            <p class="description">Controls the category page and the default presentation of stories in this category.</p></td></tr>
        <?php
    }

    public static function save_category_style($term_id)
    {
        if (!current_user_can('manage_categories')) {
            return;
        }
        $value = isset($_POST['easyheadless_presentation_style']) ? sanitize_key(wp_unslash($_POST['easyheadless_presentation_style'])) : 'nation';
        update_term_meta($term_id, self::TERM_META_PRESENTATION_STYLE, self::sanitize_presentation_style($value, 'nation'));
    }

    private static function sanitize_presentation_style($value, $fallback = 'nation')
    {
        return in_array($value, array('inherit', 'nation', 'new-yorker'), true) ? $value : $fallback;
    }

    public static function register_settings()
    {
        register_setting('easyheadless_publication', self::OPTION_ENABLED, array('type' => 'string', 'sanitize_callback' => array(__CLASS__, 'sanitize_enabled'), 'default' => '1'));
        register_setting('easyheadless_publication', self::OPTION_SETTINGS, array('type' => 'array', 'sanitize_callback' => array(__CLASS__, 'sanitize_publication_settings'), 'default' => self::default_settings()));
        register_setting('easyheadless_publication_curation', self::OPTION_CURATION, array('type' => 'array', 'sanitize_callback' => array(__CLASS__, 'sanitize_curation'), 'default' => self::default_curation()));
    }

    public static function sanitize_enabled($value)
    {
        return empty($value) ? '0' : '1';
    }

    public static function sanitize_ids($value)
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value);
        }
        return array_values(array_unique(array_filter(array_map('absint', (array) $value))));
    }

    public static function sanitize_publication_settings($input)
    {
        $existing = wp_parse_args(get_option(self::OPTION_SETTINGS, array()), self::default_settings());
        return array(
            'donation_url' => esc_url_raw(isset($input['donation_url']) ? $input['donation_url'] : ''),
            'newsletter_provider' => sanitize_key(isset($input['newsletter_provider']) ? $input['newsletter_provider'] : 'wordpress'),
            'newsletter_list_id' => sanitize_text_field(isset($input['newsletter_list_id']) ? $input['newsletter_list_id'] : ''),
            'frontend_secret' => sanitize_text_field(isset($input['frontend_secret']) ? $input['frontend_secret'] : $existing['frontend_secret']),
            'revalidation_url' => esc_url_raw(isset($input['revalidation_url']) ? $input['revalidation_url'] : ''),
            'revalidation_secret' => sanitize_text_field(isset($input['revalidation_secret']) ? $input['revalidation_secret'] : ''),
            'social_facebook' => esc_url_raw(isset($input['social_facebook']) ? $input['social_facebook'] : ''),
            'social_instagram' => esc_url_raw(isset($input['social_instagram']) ? $input['social_instagram'] : ''),
            'social_x' => esc_url_raw(isset($input['social_x']) ? $input['social_x'] : ''),
        );
    }

    public static function sanitize_curation($input)
    {
        $output = self::default_curation();
        $output['hero_id'] = absint(isset($input['hero_id']) ? $input['hero_id'] : 0);
        $output['support_ids'] = self::sanitize_ids(isset($input['support_ids']) ? $input['support_ids'] : array());
        foreach (array('hot-hot-latest', 'hot-hot', 'columns', 'big-interview', 'ideas', 'festivals', 'reviews', 'tv-documentary', 'spotlight', 'biz', 'features', 'the-cover') as $key) {
            $section = isset($input[$key]) ? (array) $input[$key] : array();
            $output[$key]['category_id'] = absint(isset($section['category_id']) ? $section['category_id'] : 0);
            $output[$key]['post_ids'] = self::sanitize_ids(isset($section['post_ids']) ? $section['post_ids'] : array());
        }
        $spotlight = isset($input['spotlight']) ? (array) $input['spotlight'] : array();
        $output['spotlight']['title'] = sanitize_text_field(isset($spotlight['title']) ? $spotlight['title'] : 'Seasonal Spotlight');
        $output['spotlight']['start_date'] = sanitize_text_field(isset($spotlight['start_date']) ? $spotlight['start_date'] : '');
        $output['spotlight']['end_date'] = sanitize_text_field(isset($spotlight['end_date']) ? $spotlight['end_date'] : '');
        return $output;
    }

    public static function register_post_meta_box()
    {
        if (self::enabled()) {
            add_meta_box('easyheadless-publication-access', 'Film Efiko Reader Access', array(__CLASS__, 'render_post_meta_box'), 'post', 'side', 'high');
        }
    }

    public static function render_post_meta_box($post)
    {
        wp_nonce_field('easyheadless_publication_access', 'easyheadless_publication_nonce');
        $access = get_post_meta($post->ID, self::META_ACCESS, true) ?: 'public';
        $paragraphs = absint(get_post_meta($post->ID, self::META_PREVIEW_PARAGRAPHS, true)) ?: 2;
        $message = get_post_meta($post->ID, self::META_GATE_MESSAGE, true);
        $presentation_style = self::sanitize_presentation_style(get_post_meta($post->ID, self::META_PRESENTATION_STYLE, true), 'inherit');
        $issue_id = absint(get_post_meta($post->ID, self::META_ISSUE_ID, true));
        $issues = get_posts(array('post_type' => self::ISSUE_TYPE, 'post_status' => array('publish', 'draft', 'future', 'private'), 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC'));
        ?>
        <p><label for="eh-access"><strong>Who can read the full story?</strong></label><br>
            <select id="eh-access" name="easyheadless_access" style="width:100%"><option value="public" <?php selected($access, 'public'); ?>>Everyone</option><option value="members" <?php selected($access, 'members'); ?>>Verified email readers</option></select></p>
        <p><label for="eh-preview">Preview paragraphs</label><br><input id="eh-preview" type="number" min="0" max="20" name="easyheadless_preview_paragraphs" value="<?php echo esc_attr($paragraphs); ?>" style="width:100%"></p>
        <p><label for="eh-gate-message">Gate message</label><br><textarea id="eh-gate-message" name="easyheadless_gate_message" rows="4" style="width:100%"><?php echo esc_textarea($message); ?></textarea></p>
        <p><label for="eh-presentation-style"><strong>Story presentation</strong></label><br><select id="eh-presentation-style" name="easyheadless_presentation_style" style="width:100%"><option value="inherit" <?php selected($presentation_style, 'inherit'); ?>>Inherit from category</option><option value="nation" <?php selected($presentation_style, 'nation'); ?>>Nation / standard</option><option value="new-yorker" <?php selected($presentation_style, 'new-yorker'); ?>>New Yorker inspired</option></select></p>
        <p><label for="eh-issue"><strong>Cover / issue</strong></label><br><select id="eh-issue" name="easyheadless_issue_id" style="width:100%"><option value="0">Not assigned to an issue</option><?php foreach ($issues as $issue) : ?><option value="<?php echo esc_attr($issue->ID); ?>" <?php selected($issue_id, $issue->ID); ?>><?php echo esc_html(get_the_title($issue)); ?></option><?php endforeach; ?></select><span class="description">Assigned stories appear in this cover's table of contents.</span></p>
        <?php
    }

    public static function save_post_access($post_id, $post)
    {
        if (!isset($_POST['easyheadless_publication_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['easyheadless_publication_nonce'])), 'easyheadless_publication_access') || !current_user_can('edit_post', $post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        $access = isset($_POST['easyheadless_access']) && 'members' === $_POST['easyheadless_access'] ? 'members' : 'public';
        update_post_meta($post_id, self::META_ACCESS, $access);
        update_post_meta($post_id, self::META_PREVIEW_PARAGRAPHS, min(20, absint(isset($_POST['easyheadless_preview_paragraphs']) ? $_POST['easyheadless_preview_paragraphs'] : 2)));
        update_post_meta($post_id, self::META_GATE_MESSAGE, sanitize_textarea_field(isset($_POST['easyheadless_gate_message']) ? wp_unslash($_POST['easyheadless_gate_message']) : ''));
        $presentation_style = isset($_POST['easyheadless_presentation_style']) ? sanitize_key(wp_unslash($_POST['easyheadless_presentation_style'])) : 'inherit';
        update_post_meta($post_id, self::META_PRESENTATION_STYLE, self::sanitize_presentation_style($presentation_style, 'inherit'));
        $issue_id = absint(isset($_POST['easyheadless_issue_id']) ? $_POST['easyheadless_issue_id'] : 0);
        if ($issue_id && self::ISSUE_TYPE !== get_post_type($issue_id)) {
            $issue_id = 0;
        }
        update_post_meta($post_id, self::META_ISSUE_ID, $issue_id);
    }

    public static function register_issue_meta_box()
    {
        add_meta_box('easyheadless-publication-issue', 'Issue Details', array(__CLASS__, 'render_issue_meta_box'), self::ISSUE_TYPE, 'side', 'high');
    }

    public static function render_issue_meta_box($post)
    {
        wp_nonce_field('easyheadless_publication_issue', 'easyheadless_publication_issue_nonce');
        $date = get_post_meta($post->ID, self::ISSUE_META_DATE, true);
        $volume = get_post_meta($post->ID, self::ISSUE_META_VOLUME, true);
        $featured = absint(get_post_meta($post->ID, self::ISSUE_META_FEATURED_STORY, true));
        $related = self::sanitize_ids(get_post_meta($post->ID, self::ISSUE_META_RELATED_STORIES, true));
        ?>
        <p><label for="eh-issue-date"><strong>Issue date</strong></label><br><input id="eh-issue-date" name="easyheadless_issue_date" type="date" value="<?php echo esc_attr($date); ?>" style="width:100%"></p>
        <p><label for="eh-issue-volume"><strong>Volume label</strong></label><br><input id="eh-issue-volume" name="easyheadless_issue_volume" type="text" value="<?php echo esc_attr($volume); ?>" placeholder="Volume 1" style="width:100%"><span class="description">Shown beneath the cover in the public archive.</span></p>
        <p><label for="eh-featured-story"><strong>Featured story ID</strong></label><br><input id="eh-featured-story" name="easyheadless_featured_story" type="number" min="0" value="<?php echo esc_attr($featured); ?>" style="width:100%"></p>
        <p><label for="eh-related-stories"><strong>Related story IDs</strong></label><br><textarea id="eh-related-stories" name="easyheadless_related_stories" rows="4" style="width:100%"><?php echo esc_textarea(implode(', ', $related)); ?></textarea><span class="description">Comma-separated post IDs. Set the issue cover using Featured Image.</span></p>
        <?php
    }

    public static function save_issue_meta($post_id, $post)
    {
        if (!isset($_POST['easyheadless_publication_issue_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['easyheadless_publication_issue_nonce'])), 'easyheadless_publication_issue') || !current_user_can('edit_post', $post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        update_post_meta($post_id, self::ISSUE_META_DATE, sanitize_text_field(isset($_POST['easyheadless_issue_date']) ? wp_unslash($_POST['easyheadless_issue_date']) : ''));
        update_post_meta($post_id, self::ISSUE_META_VOLUME, sanitize_text_field(isset($_POST['easyheadless_issue_volume']) ? wp_unslash($_POST['easyheadless_issue_volume']) : ''));
        update_post_meta($post_id, self::ISSUE_META_FEATURED_STORY, absint(isset($_POST['easyheadless_featured_story']) ? $_POST['easyheadless_featured_story'] : 0));
        update_post_meta($post_id, self::ISSUE_META_RELATED_STORIES, self::sanitize_ids(isset($_POST['easyheadless_related_stories']) ? wp_unslash($_POST['easyheadless_related_stories']) : array()));
    }

    public static function register_admin_pages()
    {
        add_submenu_page(EasyHeadless_Plugin::ADMIN_MENU_SLUG, 'Publication', 'Publication', 'manage_options', 'easyheadless-publication', array(__CLASS__, 'render_settings_page'));
        if (self::enabled()) {
            add_submenu_page(EasyHeadless_Plugin::ADMIN_MENU_SLUG, 'Homepage Curation', 'Homepage Curation', 'edit_posts', 'easyheadless-publication-curation', array(__CLASS__, 'render_curation_page'));
            add_submenu_page(EasyHeadless_Plugin::ADMIN_MENU_SLUG, 'Readers & Contacts', 'Readers & Contacts', 'manage_options', 'easyheadless-publication-contacts', array(__CLASS__, 'render_contacts_page'));
        }
    }

    public static function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = wp_parse_args(get_option(self::OPTION_SETTINGS, array()), self::default_settings());
        ?>
        <div class="wrap"><h1>EasyHeadless Publication</h1><p>Turn WordPress into the publishing and reader-management centre for an editorial website. Existing EasyHeadless APIs are not changed.</p>
        <form method="post" action="options.php"><?php settings_fields('easyheadless_publication'); ?>
        <table class="form-table" role="presentation">
            <tr><th scope="row">Publication module</th><td><label><input type="hidden" name="<?php echo esc_attr(self::OPTION_ENABLED); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr(self::OPTION_ENABLED); ?>" value="1" <?php checked(self::enabled()); ?>> Enabled</label><p class="description">Enabled by default. Switch off if publication features are not needed.</p></td></tr>
            <?php self::settings_input('donation_url', 'Donate URL', $settings, 'url'); ?>
            <?php self::settings_input('frontend_secret', 'Frontend secret', $settings, 'text'); ?>
            <?php self::settings_input('revalidation_url', 'Next.js revalidation URL', $settings, 'url'); ?>
            <?php self::settings_input('revalidation_secret', 'Revalidation secret', $settings, 'text'); ?>
            <?php self::settings_input('newsletter_list_id', 'Newsletter list ID', $settings, 'text'); ?>
            <?php self::settings_input('social_facebook', 'Facebook URL', $settings, 'url'); ?>
            <?php self::settings_input('social_instagram', 'Instagram URL', $settings, 'url'); ?>
            <?php self::settings_input('social_x', 'X / Twitter URL', $settings, 'url'); ?>
            <tr><th scope="row"><label for="eh-newsletter-provider">Newsletter provider</label></th><td><select id="eh-newsletter-provider" name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[newsletter_provider]"><option value="wordpress" <?php selected($settings['newsletter_provider'], 'wordpress'); ?>>WordPress storage</option><option value="mailchimp" <?php selected($settings['newsletter_provider'], 'mailchimp'); ?>>Mailchimp adapter</option><option value="brevo" <?php selected($settings['newsletter_provider'], 'brevo'); ?>>Brevo adapter</option></select><p class="description">Contacts are always stored in WordPress; a provider can be connected later.</p></td></tr>
        </table><?php submit_button('Save publication settings'); ?></form></div>
        <?php
    }

    private static function settings_input($key, $label, $settings, $type)
    {
        ?>
        <tr><th scope="row"><label for="eh-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input class="regular-text" id="eh-<?php echo esc_attr($key); ?>" type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr(isset($settings[$key]) ? $settings[$key] : ''); ?>"></td></tr>
        <?php
    }

    public static function render_curation_page()
    {
        if (!current_user_can('edit_posts')) {
            return;
        }
        $curation = wp_parse_args(get_option(self::OPTION_CURATION, array()), self::default_curation());
        $posts = get_posts(array('post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 100, 'orderby' => 'date', 'order' => 'DESC'));
        $categories = get_categories(array('hide_empty' => false));
        $sections = self::section_definitions();
        ?>
        <div class="wrap"><h1>Homepage Curation</h1><p>Choose exact stories where needed, or choose a category and let the latest stories fill the section automatically.</p>
        <form method="post" action="options.php"><?php settings_fields('easyheadless_publication_curation'); ?>
        <table class="form-table" role="presentation"><tr><th scope="row">Hero story</th><td><select name="<?php echo esc_attr(self::OPTION_CURATION); ?>[hero_id]"><option value="0">Latest story automatically</option><?php foreach ($posts as $post) : ?><option value="<?php echo esc_attr($post->ID); ?>" <?php selected($curation['hero_id'], $post->ID); ?>><?php echo esc_html($post->post_title); ?></option><?php endforeach; ?></select></td></tr>
        <tr><th scope="row">Supporting stories</th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION_CURATION); ?>[support_ids]" value="<?php echo esc_attr(implode(', ', $curation['support_ids'])); ?>"><p class="description">Up to three post IDs, comma separated. Missing positions use the latest stories.</p></td></tr></table>
        <?php foreach ($sections as $key => $definition) : $value = isset($curation[$key]) ? $curation[$key] : array(); ?>
            <h2><?php echo esc_html($definition['title']); ?> <small>(<?php echo esc_html($definition['count']); ?> stories)</small></h2><table class="form-table" role="presentation">
            <?php if ('spotlight' === $key) : ?><tr><th>Displayed title</th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION_CURATION); ?>[spotlight][title]" value="<?php echo esc_attr($value['title']); ?>"></td></tr><tr><th>Active dates</th><td><input type="date" name="<?php echo esc_attr(self::OPTION_CURATION); ?>[spotlight][start_date]" value="<?php echo esc_attr($value['start_date']); ?>"> to <input type="date" name="<?php echo esc_attr(self::OPTION_CURATION); ?>[spotlight][end_date]" value="<?php echo esc_attr($value['end_date']); ?>"></td></tr><?php endif; ?>
            <tr><th>Automatic category</th><td><select name="<?php echo esc_attr(self::OPTION_CURATION); ?>[<?php echo esc_attr($key); ?>][category_id]"><option value="0">Latest stories</option><?php foreach ($categories as $category) : ?><option value="<?php echo esc_attr($category->term_id); ?>" <?php selected(isset($value['category_id']) ? $value['category_id'] : 0, $category->term_id); ?>><?php echo esc_html($category->name); ?></option><?php endforeach; ?></select></td></tr>
            <tr><th>Selected stories</th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION_CURATION); ?>[<?php echo esc_attr($key); ?>][post_ids]" value="<?php echo esc_attr(implode(', ', isset($value['post_ids']) ? $value['post_ids'] : array())); ?>"><p class="description">Post IDs, comma separated. Selected stories come first; automatic stories fill the rest.</p></td></tr></table>
        <?php endforeach; ?><?php submit_button('Save homepage curation'); ?></form></div>
        <?php
    }

    private static function section_definitions()
    {
        return array(
            'hot-hot-latest' => array('title' => 'Cinema', 'count' => 4, 'layout' => 'rail'),
            'hot-hot' => array('title' => 'Hot-Hot', 'count' => 6, 'layout' => 'rail'),
            'columns' => array('title' => 'Columns', 'count' => 3, 'layout' => 'rail'),
            'big-interview' => array('title' => 'Big Interview', 'count' => 3, 'layout' => 'feature'),
            'ideas' => array('title' => 'Ideas', 'count' => 5, 'layout' => 'feature'),
            'festivals' => array('title' => 'Festivals', 'count' => 5, 'layout' => 'lead'),
            'reviews' => array('title' => 'Reviews', 'count' => 4, 'layout' => 'rail'),
            'tv-documentary' => array('title' => 'TV & Documentary', 'count' => 4, 'layout' => 'rail'),
            'spotlight' => array('title' => 'Seasonal Spotlight', 'count' => 3, 'layout' => 'feature'),
            'biz' => array('title' => 'Biz', 'count' => 5, 'layout' => 'lead'),
            'features' => array('title' => 'Features', 'count' => 4, 'layout' => 'rail'),
            'the-cover' => array('title' => 'The Cover', 'count' => 5, 'layout' => 'portrait'),
        );
    }

    public static function render_contacts_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        global $wpdb;
        $search = isset($_GET['s']) ? sanitize_email(wp_unslash($_GET['s'])) : '';
        $where = $search ? $wpdb->prepare(' WHERE email LIKE %s', '%' . $wpdb->esc_like($search) . '%') : '';
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}eh_publication_contacts{$where} ORDER BY updated_at DESC LIMIT 500"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        ?>
        <div class="wrap"><h1>Readers & Contacts</h1><form method="get"><input type="hidden" name="page" value="easyheadless-publication-contacts"><p class="search-box"><label class="screen-reader-text" for="contact-search">Search contacts</label><input id="contact-search" name="s" value="<?php echo esc_attr($search); ?>"><button class="button">Search</button></p></form>
        <p><a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=easyheadless_publication_export'), 'easyheadless_publication_export')); ?>">Export CSV</a></p>
        <table class="widefat striped"><thead><tr><th>Email</th><th>Verified</th><th>Newsletter</th><th>Source story</th><th>Updated</th><th></th></tr></thead><tbody><?php if (!$rows) : ?><tr><td colspan="6">No contacts found.</td></tr><?php endif; foreach ($rows as $row) : ?><tr><td><?php echo esc_html($row->email); ?></td><td><?php echo esc_html($row->verified_at ?: '—'); ?></td><td><?php echo $row->newsletter_consent ? 'Yes' : 'No'; ?></td><td><?php echo esc_html($row->source_article ?: '—'); ?></td><td><?php echo esc_html($row->updated_at); ?></td><td><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=easyheadless_publication_delete_contact&id=' . absint($row->id)), 'easyheadless_publication_delete_contact_' . absint($row->id))); ?>" onclick="return confirm('Delete this contact and revoke their sessions?')">Delete</a></td></tr><?php endforeach; ?></tbody></table></div>
        <?php
    }

    public static function export_contacts()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('easyheadless_publication_export')) {
            wp_die('You are not allowed to export contacts.');
        }
        global $wpdb;
        $rows = $wpdb->get_results("SELECT email, verified_at, newsletter_consent, source_article, created_at, updated_at FROM {$wpdb->prefix}eh_publication_contacts ORDER BY updated_at DESC", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=film-efiko-contacts-' . gmdate('Y-m-d') . '.csv');
        $stream = fopen('php://output', 'w');
        fputcsv($stream, array('Email', 'Verified at', 'Newsletter consent', 'Source article', 'Created at', 'Updated at'));
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        fclose($stream);
        exit;
    }

    public static function delete_contact()
    {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if (!$id || !current_user_can('manage_options') || !check_admin_referer('easyheadless_publication_delete_contact_' . $id)) {
            wp_die('You are not allowed to delete this contact.');
        }
        global $wpdb;
        $email = $wpdb->get_var($wpdb->prepare("SELECT email FROM {$wpdb->prefix}eh_publication_contacts WHERE id = %d", $id));
        $wpdb->delete($wpdb->prefix . 'eh_publication_contacts', array('id' => $id), array('%d'));
        if ($email) {
            $user = get_user_by('email', $email);
            if ($user) {
                $wpdb->update($wpdb->prefix . 'eh_publication_sessions', array('revoked_at' => current_time('mysql', true)), array('user_id' => $user->ID, 'revoked_at' => null));
                if (array('subscriber') === array_values($user->roles)) {
                    require_once ABSPATH . 'wp-admin/includes/user.php';
                    wp_delete_user($user->ID);
                }
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=easyheadless-publication-contacts&deleted=1'));
        exit;
    }

    public static function register_routes()
    {
        $namespace = 'easyheadless/v1/publication';
        register_rest_route($namespace, '/homepage', array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'get_homepage'), 'permission_callback' => array(__CLASS__, 'public_permission')));
        register_rest_route($namespace, '/settings', array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'get_public_settings'), 'permission_callback' => array(__CLASS__, 'public_permission')));
        register_rest_route($namespace, '/posts', array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'get_posts'), 'permission_callback' => array(__CLASS__, 'public_permission')));
        register_rest_route($namespace, '/posts/(?P<slug>[a-zA-Z0-9-]+)', array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'get_post'), 'permission_callback' => array(__CLASS__, 'public_permission')));
        register_rest_route($namespace, '/categories', array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'get_categories'), 'permission_callback' => array(__CLASS__, 'public_permission')));
        register_rest_route($namespace, '/categories/(?P<slug>[a-zA-Z0-9-]+)', array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'get_category'), 'permission_callback' => array(__CLASS__, 'public_permission')));
        register_rest_route($namespace, '/authors', array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'get_authors'), 'permission_callback' => array(__CLASS__, 'public_permission')));
        register_rest_route($namespace, '/issues', array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'get_issues'), 'permission_callback' => array(__CLASS__, 'public_permission')));
        register_rest_route($namespace, '/issues/(?P<slug>[a-zA-Z0-9-]+)', array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'get_issue'), 'permission_callback' => array(__CLASS__, 'public_permission')));
        register_rest_route($namespace, '/views', array('methods' => WP_REST_Server::CREATABLE, 'callback' => array(__CLASS__, 'record_view'), 'permission_callback' => array(__CLASS__, 'frontend_permission')));
        register_rest_route($namespace, '/auth/request-code', array('methods' => WP_REST_Server::CREATABLE, 'callback' => array(__CLASS__, 'request_code'), 'permission_callback' => array(__CLASS__, 'frontend_permission')));
        register_rest_route($namespace, '/auth/verify-code', array('methods' => WP_REST_Server::CREATABLE, 'callback' => array(__CLASS__, 'verify_code'), 'permission_callback' => array(__CLASS__, 'frontend_permission')));
        register_rest_route($namespace, '/auth/logout', array('methods' => WP_REST_Server::CREATABLE, 'callback' => array(__CLASS__, 'logout'), 'permission_callback' => array(__CLASS__, 'session_permission')));
        register_rest_route($namespace, '/account', array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'get_account'), 'permission_callback' => array(__CLASS__, 'session_permission')));
        register_rest_route($namespace, '/account', array('methods' => WP_REST_Server::EDITABLE, 'callback' => array(__CLASS__, 'update_account'), 'permission_callback' => array(__CLASS__, 'session_permission')));
        register_rest_route($namespace, '/newsletter', array('methods' => WP_REST_Server::CREATABLE, 'callback' => array(__CLASS__, 'newsletter'), 'permission_callback' => array(__CLASS__, 'frontend_permission')));
        register_rest_route($namespace, '/contact', array('methods' => WP_REST_Server::CREATABLE, 'callback' => array(__CLASS__, 'contact'), 'permission_callback' => array(__CLASS__, 'frontend_permission')));
    }

    public static function public_permission()
    {
        return self::enabled() ? true : new WP_Error('publication_disabled', 'The Publication module is disabled.', array('status' => 404));
    }

    public static function frontend_permission($request)
    {
        if (!self::enabled()) {
            return new WP_Error('publication_disabled', 'The Publication module is disabled.', array('status' => 404));
        }
        $settings = wp_parse_args(get_option(self::OPTION_SETTINGS, array()), self::default_settings());
        $provided = (string) $request->get_header('X-EasyHeadless-Frontend-Key');
        if (!$provided || !hash_equals((string) $settings['frontend_secret'], $provided)) {
            return new WP_Error('publication_forbidden', 'Invalid frontend credentials.', array('status' => 403));
        }
        return true;
    }

    public static function session_permission($request)
    {
        $frontend = self::frontend_permission($request);
        if (true !== $frontend) {
            return $frontend;
        }
        $session = self::session_from_request($request);
        return $session ? true : new WP_Error('publication_session_invalid', 'Your reader session is invalid or has expired.', array('status' => 401));
    }

    private static function session_from_request($request)
    {
        global $wpdb;
        $authorization = (string) $request->get_header('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return null;
        }
        $hash = hash('sha256', $matches[1]);
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}eh_publication_sessions WHERE token_hash = %s AND revoked_at IS NULL AND expires_at > %s", $hash, current_time('mysql', true)));
    }

    public static function get_homepage()
    {
        $curation = wp_parse_args(get_option(self::OPTION_CURATION, array()), self::default_curation());
        $used = array();
        $hero = self::story_by_id(absint($curation['hero_id']));
        if (!$hero) {
            $latest = self::query_stories(array(), 1, $used);
            $hero = isset($latest[0]) ? $latest[0] : null;
        }
        if (!$hero) {
            return new WP_Error('publication_empty', 'No published stories are available.', array('status' => 404));
        }
        $used[] = $hero['id'];
        $supporting = self::curated_stories($curation['support_ids'], 0, 3, $used);
        $sections = array();
        foreach (self::section_definitions() as $key => $definition) {
            $value = isset($curation[$key]) ? $curation[$key] : array();
            $title = ('spotlight' === $key && !empty($value['title'])) ? $value['title'] : $definition['title'];
            if ('spotlight' === $key && !self::spotlight_active($value)) {
                continue;
            }
            $category_id = isset($value['category_id']) ? absint($value['category_id']) : 0;
            $stories = self::curated_stories(isset($value['post_ids']) ? $value['post_ids'] : array(), $category_id, $definition['count'], $used, 'hot-hot' === $key);
            $sections[] = array('key' => $key, 'title' => $title, 'stories' => $stories, 'layout' => $definition['layout'], 'href' => self::section_href($key, $category_id));
        }
        $settings = wp_parse_args(get_option(self::OPTION_SETTINGS, array()), self::default_settings());
        return rest_ensure_response(array('hero' => $hero, 'supporting' => $supporting, 'sections' => $sections, 'donationUrl' => $settings['donation_url'], 'social' => array('facebook' => $settings['social_facebook'], 'instagram' => $settings['social_instagram'], 'x' => $settings['social_x'])));
    }

    public static function get_public_settings()
    {
        $settings = wp_parse_args(get_option(self::OPTION_SETTINGS, array()), self::default_settings());
        return rest_ensure_response(array('donationUrl' => $settings['donation_url'], 'social' => array('facebook' => $settings['social_facebook'], 'instagram' => $settings['social_instagram'], 'x' => $settings['social_x'])));
    }

    private static function spotlight_active($value)
    {
        $today = gmdate('Y-m-d');
        return (empty($value['start_date']) || $today >= $value['start_date']) && (empty($value['end_date']) || $today <= $value['end_date']);
    }

    private static function section_href($key, $category_id)
    {
        if ($category_id) {
            $term = get_term($category_id, 'category');
            if ($term && !is_wp_error($term)) {
                return '/category/' . $term->slug;
            }
        }
        if ('hot-hot-latest' === $key) {
            return '/category/cinema';
        }
        if ('hot-hot' === $key) {
            return '/latest';
        }
        return '/category/' . $key;
    }

    private static function curated_stories($ids, $category_id, $count, &$used, $popular = false)
    {
        $stories = array();
        foreach (self::sanitize_ids($ids) as $id) {
            if (count($stories) >= $count || in_array($id, $used, true)) {
                continue;
            }
            $story = self::story_by_id($id);
            if ($story) {
                $stories[] = $story;
                $used[] = $id;
            }
        }
        if (count($stories) < $count) {
            $args = $category_id ? array('cat' => $category_id) : array();
            if ($popular) {
                $args += array('meta_key' => self::META_VIEWS, 'orderby' => array('meta_value_num' => 'DESC', 'date' => 'DESC'));
            }
            $fill = self::query_stories($args, $count - count($stories), $used);
            foreach ($fill as $story) {
                $stories[] = $story;
                $used[] = $story['id'];
            }
        }
        return $stories;
    }

    private static function query_stories($args, $count, $exclude = array())
    {
        $query = new WP_Query(array_merge(array('post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => $count, 'post__not_in' => $exclude, 'ignore_sticky_posts' => true), $args));
        return array_values(array_filter(array_map(array(__CLASS__, 'normalize_story'), $query->posts)));
    }

    private static function story_by_id($id, $include_content = false)
    {
        if (!$id) {
            return null;
        }
        $post = get_post($id);
        return $post && 'post' === $post->post_type && 'publish' === $post->post_status ? self::normalize_story($post, $include_content) : null;
    }

    public static function normalize_story($post, $include_content = false)
    {
        $post = get_post($post);
        if (!$post) {
            return null;
        }
        $author = get_userdata($post->post_author);
        $terms = get_the_terms($post, 'category');
        $categories = array();
        if (is_array($terms)) {
            foreach ($terms as $term) {
                $categories[] = array('id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug);
            }
        }
        $access = get_post_meta($post->ID, self::META_ACCESS, true) ?: 'public';
        $content = apply_filters('the_content', $post->post_content);
        $preview_count = absint(get_post_meta($post->ID, self::META_PREVIEW_PARAGRAPHS, true)) ?: 2;
        $preview = self::preview_html($content, $preview_count);
        $payload = array(
            'id' => $post->ID,
            'slug' => $post->post_name,
            'title' => get_the_title($post),
            'excerpt' => wp_strip_all_tags(has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words($post->post_content, 35)),
            'date' => get_post_time(DATE_ATOM, true, $post),
            'modified' => get_post_modified_time(DATE_ATOM, true, $post),
            'image' => get_the_post_thumbnail_url($post, 'full') ?: '',
            'imageAlt' => get_post_meta(get_post_thumbnail_id($post), '_wp_attachment_image_alt', true) ?: get_the_title($post),
            'imageCaption' => wp_strip_all_tags((string) wp_get_attachment_caption(get_post_thumbnail_id($post))),
            'author' => array('id' => $author ? $author->ID : 0, 'name' => $author ? $author->display_name : 'Film Efiko', 'slug' => $author ? $author->user_nicename : 'film-efiko'),
            'categories' => $categories,
            'access' => 'members' === $access ? 'members' : 'public',
            'preview' => $preview,
            'gateMessage' => get_post_meta($post->ID, self::META_GATE_MESSAGE, true),
            'presentationStyle' => self::resolved_presentation_style($post, $terms),
        );
        if ('public' === $payload['access'] || $include_content) {
            $payload['content'] = $content;
        }
        return $payload;
    }

    private static function resolved_presentation_style($post, $terms = null)
    {
        $override = self::sanitize_presentation_style(get_post_meta($post->ID, self::META_PRESENTATION_STYLE, true), 'inherit');
        if ('inherit' !== $override) {
            return $override;
        }
        if (!is_array($terms)) {
            $terms = get_the_terms($post, 'category');
        }
        if (is_array($terms)) {
            foreach ($terms as $term) {
                if ('new-yorker' === self::sanitize_presentation_style(get_term_meta($term->term_id, self::TERM_META_PRESENTATION_STYLE, true), 'nation')) {
                    return 'new-yorker';
                }
            }
        }
        return 'nation';
    }

    private static function preview_html($html, $paragraphs)
    {
        if (!$paragraphs) {
            return '';
        }
        if (preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $html, $matches)) {
            return wp_kses_post(implode('', array_slice($matches[0], 0, $paragraphs)));
        }
        return wpautop(wp_trim_words(wp_strip_all_tags($html), $paragraphs * 80));
    }

    public static function get_posts($request)
    {
        $page = max(1, absint($request->get_param('page')));
        $per_page = min(50, max(1, absint($request->get_param('per_page')) ?: 12));
        $args = array('post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => $per_page, 'paged' => $page, 'ignore_sticky_posts' => true);
        if ($request->get_param('search')) {
            $args['s'] = sanitize_text_field($request->get_param('search'));
        }
        if ($request->get_param('category')) {
            $args['category_name'] = sanitize_title($request->get_param('category'));
        }
        if ($request->get_param('author')) {
            $user = get_user_by('slug', sanitize_title($request->get_param('author')));
            $args['author'] = $user ? $user->ID : -1;
        }
        $query = new WP_Query($args);
        return rest_ensure_response(array('items' => array_values(array_filter(array_map(array(__CLASS__, 'normalize_story'), $query->posts))), 'total' => absint($query->found_posts), 'totalPages' => absint($query->max_num_pages), 'page' => $page));
    }

    public static function get_post($request)
    {
        $posts = get_posts(array('name' => sanitize_title($request['slug']), 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1));
        if (!$posts) {
            return new WP_Error('publication_story_not_found', 'Story not found.', array('status' => 404));
        }
        $access = get_post_meta($posts[0]->ID, self::META_ACCESS, true) ?: 'public';
        $include_content = 'public' === $access;
        if ('members' === $access && $request->get_param('full')) {
            $include_content = (bool) self::session_from_request($request);
        }
        return rest_ensure_response(self::normalize_story($posts[0], $include_content));
    }

    public static function get_categories()
    {
        return rest_ensure_response(array_map(array(__CLASS__, 'normalize_category'), get_categories(array('hide_empty' => false))));
    }

    public static function get_category($request)
    {
        $term = get_category_by_slug(sanitize_title($request['slug']));
        return $term ? rest_ensure_response(self::normalize_category($term)) : new WP_Error('publication_category_not_found', 'Category not found.', array('status' => 404));
    }

    public static function normalize_category($term)
    {
        return array(
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'count' => $term->count,
            'description' => wp_kses_post(term_description($term->term_id, 'category')),
            'presentationStyle' => self::sanitize_presentation_style(get_term_meta($term->term_id, self::TERM_META_PRESENTATION_STYLE, true), 'nation'),
        );
    }

    public static function get_authors()
    {
        return rest_ensure_response(array_map(function ($user) {
            return array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'slug' => $user->user_nicename,
                'description' => wp_kses_post(get_the_author_meta('description', $user->ID)),
            );
        }, get_users(array('who' => 'authors', 'has_published_posts' => array('post')))));
    }

    public static function get_issues($request)
    {
        $posts = get_posts(array('post_type' => self::ISSUE_TYPE, 'post_status' => 'publish', 'numberposts' => min(50, absint($request->get_param('per_page')) ?: 20), 'orderby' => 'date', 'order' => 'DESC'));
        return rest_ensure_response(array_values(array_map(array(__CLASS__, 'normalize_issue'), $posts)));
    }

    public static function get_issue($request)
    {
        $posts = get_posts(array('name' => sanitize_title($request['slug']), 'post_type' => self::ISSUE_TYPE, 'post_status' => 'publish', 'numberposts' => 1));
        return $posts ? rest_ensure_response(self::normalize_issue($posts[0])) : new WP_Error('publication_issue_not_found', 'Issue not found.', array('status' => 404));
    }

    public static function normalize_issue($post)
    {
        $post = get_post($post);
        $ids = self::sanitize_ids(get_post_meta($post->ID, self::ISSUE_META_RELATED_STORIES, true));
        $assigned = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'meta_key' => self::META_ISSUE_ID,
            'meta_value' => $post->ID,
        ));
        $ids = array_merge($ids, self::sanitize_ids($assigned));
        $featured = absint(get_post_meta($post->ID, self::ISSUE_META_FEATURED_STORY, true));
        if ($featured) {
            array_unshift($ids, $featured);
        }
        return array(
            'id' => $post->ID,
            'slug' => $post->post_name,
            'title' => get_the_title($post),
            'date' => get_post_meta($post->ID, self::ISSUE_META_DATE, true) ?: get_post_time(DATE_ATOM, true, $post),
            'volume' => sanitize_text_field(get_post_meta($post->ID, self::ISSUE_META_VOLUME, true)),
            'cover' => get_the_post_thumbnail_url($post, 'full') ?: '',
            'description' => apply_filters('the_content', $post->post_content),
            'href' => '/issues/' . $post->post_name,
            'stories' => array_values(array_filter(array_map(array(__CLASS__, 'story_by_id'), array_unique($ids)))),
        );
    }

    public static function record_view($request)
    {
        $post_id = absint($request->get_param('post_id'));
        if ('post' !== get_post_type($post_id) || 'publish' !== get_post_status($post_id)) {
            return new WP_Error('publication_story_not_found', 'Story not found.', array('status' => 404));
        }
        $views = absint(get_post_meta($post_id, self::META_VIEWS, true)) + 1;
        update_post_meta($post_id, self::META_VIEWS, $views);
        return rest_ensure_response(array('recorded' => true));
    }

    public static function request_code($request)
    {
        global $wpdb;
        $email = sanitize_email($request->get_param('email'));
        if (!$email || !is_email($email)) {
            return new WP_Error('publication_email_invalid', 'Enter a valid email address.', array('status' => 400));
        }
        $ip = sanitize_text_field($request->get_param('request_ip')) ?: self::request_ip();
        $ip = substr($ip, 0, 100);
        $since = gmdate('Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS);
        $recent = absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eh_publication_codes WHERE (email = %s OR request_ip = %s) AND created_at >= %s", $email, $ip, $since)));
        if ($recent >= 5) {
            return new WP_Error('publication_rate_limited', 'Too many code requests. Try again in 15 minutes.', array('status' => 429));
        }
        $code = (string) wp_rand(100000, 999999);
        $wpdb->insert($wpdb->prefix . 'eh_publication_codes', array('email' => $email, 'code_hash' => wp_hash_password($code), 'request_ip' => $ip, 'attempts' => 0, 'expires_at' => gmdate('Y-m-d H:i:s', time() + self::CODE_MINUTES * MINUTE_IN_SECONDS), 'created_at' => current_time('mysql', true)), array('%s', '%s', '%s', '%d', '%s', '%s'));
        $sent = wp_mail($email, 'Your Film Efiko sign-in code', "Your Film Efiko verification code is {$code}. It expires in 10 minutes.\n\nIf you did not request this code, you can ignore this message.");
        if (!$sent) {
            return new WP_Error('publication_email_failed', 'The sign-in email could not be sent. Please try again.', array('status' => 503));
        }
        $response = array('sent' => true, 'expiresIn' => self::CODE_MINUTES * MINUTE_IN_SECONDS);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $response['debugCode'] = $code;
        }
        return rest_ensure_response($response);
    }

    public static function verify_code($request)
    {
        global $wpdb;
        $email = sanitize_email($request->get_param('email'));
        $code = preg_replace('/\D+/', '', (string) $request->get_param('code'));
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}eh_publication_codes WHERE email = %s ORDER BY created_at DESC LIMIT 1", $email));
        if (!$row || strtotime($row->expires_at . ' UTC') < time() || absint($row->attempts) >= 5) {
            return new WP_Error('publication_code_expired', 'That code has expired. Request a new one.', array('status' => 400));
        }
        if (!wp_check_password($code, $row->code_hash)) {
            $wpdb->update($wpdb->prefix . 'eh_publication_codes', array('attempts' => absint($row->attempts) + 1), array('id' => $row->id), array('%d'), array('%d'));
            return new WP_Error('publication_code_invalid', 'That code is not valid.', array('status' => 400));
        }
        $user = get_user_by('email', $email);
        if (!$user) {
            $base = sanitize_user(strstr($email, '@', true), true) ?: 'reader';
            $login = $base;
            for ($index = 1; username_exists($login); $index++) {
                $login = $base . $index;
            }
            $user_id = wp_insert_user(array('user_login' => $login, 'user_email' => $email, 'user_pass' => wp_generate_password(40, true, true), 'role' => 'subscriber', 'display_name' => $base));
            if (is_wp_error($user_id)) {
                return $user_id;
            }
            $user = get_userdata($user_id);
        }
        $now = current_time('mysql', true);
        $source = sanitize_title($request->get_param('source_article'));
        self::upsert_contact($email, array('verified_at' => $now, 'source_article' => $source));
        $wpdb->delete($wpdb->prefix . 'eh_publication_codes', array('email' => $email), array('%s'));
        $token = wp_generate_password(64, false, false);
        $wpdb->insert($wpdb->prefix . 'eh_publication_sessions', array('user_id' => $user->ID, 'token_hash' => hash('sha256', $token), 'expires_at' => gmdate('Y-m-d H:i:s', time() + self::SESSION_DAYS * DAY_IN_SECONDS), 'created_at' => $now), array('%d', '%s', '%s', '%s'));
        return rest_ensure_response(array('session_token' => $token, 'expiresIn' => self::SESSION_DAYS * DAY_IN_SECONDS, 'account' => array('email' => $email)));
    }

    public static function logout($request)
    {
        global $wpdb;
        $session = self::session_from_request($request);
        $wpdb->update($wpdb->prefix . 'eh_publication_sessions', array('revoked_at' => current_time('mysql', true)), array('id' => $session->id), array('%s'), array('%d'));
        return rest_ensure_response(array('loggedOut' => true));
    }

    public static function get_account($request)
    {
        global $wpdb;
        $session = self::session_from_request($request);
        $user = get_userdata($session->user_id);
        $contact = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}eh_publication_contacts WHERE email = %s", $user->user_email));
        return rest_ensure_response(array('email' => $user->user_email, 'name' => $user->display_name, 'verified_at' => $contact ? $contact->verified_at : null, 'newsletter_consent' => $contact ? (bool) $contact->newsletter_consent : false));
    }

    public static function update_account($request)
    {
        $session = self::session_from_request($request);
        $user = get_userdata($session->user_id);
        $consent = rest_sanitize_boolean($request->get_param('newsletter_consent'));
        self::upsert_contact($user->user_email, array('newsletter_consent' => $consent ? 1 : 0));
        if ($consent) {
            do_action('easyheadless_publication_newsletter_subscribed', $user->user_email, get_option(self::OPTION_SETTINGS, array()));
        } else {
            do_action('easyheadless_publication_newsletter_unsubscribed', $user->user_email, get_option(self::OPTION_SETTINGS, array()));
        }
        return self::get_account($request);
    }

    public static function newsletter($request)
    {
        $email = sanitize_email($request->get_param('email'));
        if (!$email || !is_email($email)) {
            return new WP_Error('publication_email_invalid', 'Enter a valid email address.', array('status' => 400));
        }
        self::upsert_contact($email, array('newsletter_consent' => 1, 'source_article' => sanitize_text_field($request->get_param('source'))));
        do_action('easyheadless_publication_newsletter_subscribed', $email, get_option(self::OPTION_SETTINGS, array()));
        return rest_ensure_response(array('subscribed' => true));
    }

    public static function contact($request)
    {
        $email = sanitize_email($request->get_param('email'));
        $name = sanitize_text_field($request->get_param('name'));
        $message = sanitize_textarea_field($request->get_param('message'));
        if (!$email || !is_email($email) || !$message) {
            return new WP_Error('publication_contact_invalid', 'Name, email and message are required.', array('status' => 400));
        }
        self::upsert_contact($email, array('source_article' => 'contact'));
        $recipient = get_option('admin_email');
        $sent = wp_mail($recipient, 'Film Efiko website message from ' . $name, "From: {$name} <{$email}>\n\n{$message}", array('Reply-To: ' . $name . ' <' . $email . '>'));
        return $sent ? rest_ensure_response(array('sent' => true)) : new WP_Error('publication_contact_failed', 'The message could not be sent.', array('status' => 503));
    }

    private static function upsert_contact($email, $changes)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'eh_publication_contacts';
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE email = %s", $email));
        $now = current_time('mysql', true);
        if ($existing) {
            $data = array('updated_at' => $now);
            foreach (array('verified_at', 'newsletter_consent', 'source_article') as $key) {
                if (array_key_exists($key, $changes)) {
                    $data[$key] = $changes[$key];
                }
            }
            $wpdb->update($table, $data, array('id' => $existing->id));
        } else {
            $wpdb->insert($table, array('email' => $email, 'verified_at' => isset($changes['verified_at']) ? $changes['verified_at'] : null, 'newsletter_consent' => isset($changes['newsletter_consent']) ? absint($changes['newsletter_consent']) : 0, 'source_article' => isset($changes['source_article']) ? $changes['source_article'] : '', 'created_at' => $now, 'updated_at' => $now));
        }
    }

    private static function request_ip()
    {
        $value = isset($_SERVER['REMOTE_ADDR']) ? wp_unslash($_SERVER['REMOTE_ADDR']) : '';
        return substr(sanitize_text_field($value), 0, 100);
    }

    public static function notify_frontend($post_id, $post_type)
    {
        if (!self::enabled() || !in_array($post_type, array('post', 'page', self::ISSUE_TYPE), true)) {
            return;
        }
        $settings = wp_parse_args(get_option(self::OPTION_SETTINGS, array()), self::default_settings());
        if (!$settings['revalidation_url']) {
            return;
        }
        wp_remote_post($settings['revalidation_url'], array('timeout' => 5, 'headers' => array('X-EasyHeadless-Secret' => $settings['revalidation_secret']), 'body' => wp_json_encode(array('postId' => absint($post_id), 'postType' => sanitize_key($post_type))), 'data_format' => 'body'));
    }
}
