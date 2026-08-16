<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once EASYHEADLESS_PLUGIN_DIR . 'includes/class-easyheadless-acf.php';
require_once EASYHEADLESS_PLUGIN_DIR . 'includes/class-easyheadless-seo.php';
require_once EASYHEADLESS_PLUGIN_DIR . 'includes/class-easyheadless-forms.php';
require_once EASYHEADLESS_PLUGIN_DIR . 'includes/class-easyheadless-modules.php';
require_once EASYHEADLESS_PLUGIN_DIR . 'includes/class-easyheadless-updater.php';
require_once EASYHEADLESS_PLUGIN_DIR . 'includes/class-easyheadless-rest.php';
require_once EASYHEADLESS_PLUGIN_DIR . 'includes/class-easyheadless-admin.php';

final class EasyHeadless_Plugin
{
    const ADMIN_MENU_SLUG = 'easyheadless';
    const OPTION_ALLOWED_ORIGINS = 'easyheadless_allowed_origins';
    const OPTION_PREVIEW_TOKEN = 'easyheadless_preview_token';
    const OPTION_API_KEY_HASH = 'easyheadless_api_key_hash';
    const OPTION_API_KEY_HINT = 'easyheadless_api_key_hint';

    private static $instance = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate()
    {
        if (!get_option(self::OPTION_PREVIEW_TOKEN)) {
            update_option(self::OPTION_PREVIEW_TOKEN, wp_generate_password(48, false, false));
        }

        if (!get_option(self::OPTION_API_KEY_HASH)) {
            $api_key = self::generate_api_key();
            update_option(self::OPTION_API_KEY_HASH, wp_hash_password($api_key));
            update_option(self::OPTION_API_KEY_HINT, substr($api_key, -6));
        }

        if (false === get_option(EasyHeadless_Modules::OPTION_ENABLED_MODULES, false)) {
            update_option(EasyHeadless_Modules::OPTION_ENABLED_MODULES, EasyHeadless_Modules::enabled_modules());
        }

    }

    private function __construct()
    {
        EasyHeadless_Updater::instance()->register_hooks();
        EasyHeadless_Admin::register_hooks();
        add_action('init', array($this, 'register_content_types'));
        add_action('acf/init', array('EasyHeadless_ACF', 'register_field_groups'));
        add_action('rest_api_init', array($this, 'register_rest'));
        add_action('admin_menu', array($this, 'register_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_assets'));
        add_action('admin_notices', array($this, 'render_admin_notices'));
        add_action('rest_api_init', array($this, 'register_cors_headers'), 15);
        add_action('save_post', array($this, 'notify_content_changed'), 10, 3);
        add_action('acf/save_post', array($this, 'notify_acf_changed'), 20);
        add_action('template_redirect', array('EasyHeadless_Modules', 'maybe_redirect_headless_request'), 1);
        add_action('easyheadless_content_changed', array('EasyHeadless_Modules', 'send_revalidation'), 10, 2);
    }

    public function register_content_types()
    {
        $this->register_cpt('eh_service', 'Services', 'Service', 'dashicons-hammer');
        $this->register_cpt('eh_testimonial', 'Testimonials', 'Testimonial', 'dashicons-format-quote');
        $this->register_cpt('eh_faq', 'FAQs', 'FAQ', 'dashicons-editor-help');
        $this->register_cpt('eh_team_member', 'Team Members', 'Team Member', 'dashicons-groups');
        if (EasyHeadless_Modules::is_enabled('church')) {
            $this->register_cpt('eh_church_settings', 'Church Settings', 'Church Settings', 'dashicons-admin-home');
            $this->register_cpt('eh_sermon', 'Sermons', 'Sermon', 'dashicons-microphone');
            $this->register_cpt('eh_event', 'Events', 'Event', 'dashicons-calendar-alt');
            $this->register_cpt('eh_ministry', 'Ministries', 'Ministry', 'dashicons-heart');
            $this->register_cpt('eh_leader', 'Leaders', 'Leader', 'dashicons-businessperson');
            $this->register_cpt('eh_service_time', 'Service Times', 'Service Time', 'dashicons-clock');
            $this->register_cpt('eh_policy', 'Policies', 'Policy', 'dashicons-shield');
        }
    }

    private function register_cpt($type, $plural, $singular, $icon)
    {
        register_post_type(
            $type,
            array(
                'labels' => array(
                    'name' => $plural,
                    'singular_name' => $singular,
                    'add_new_item' => 'Add New ' . $singular,
                    'edit_item' => 'Edit ' . $singular,
                ),
                'public' => true,
                'show_in_rest' => true,
                'show_in_menu' => self::ADMIN_MENU_SLUG,
                'menu_icon' => $icon,
                'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'page-attributes'),
                'has_archive' => false,
                'rewrite' => array('slug' => sanitize_title($plural)),
            )
        );
    }

    public function register_rest()
    {
        $controller = new EasyHeadless_REST();
        $controller->register_routes();
    }

    public function register_settings_page()
    {
        EasyHeadless_Admin::register_menu();
    }

    public function render_overview_page()
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $content_links = array(
            array('label' => 'FAQs', 'url' => admin_url('edit.php?post_type=eh_faq')),
            array('label' => 'Services', 'url' => admin_url('edit.php?post_type=eh_service')),
            array('label' => 'Testimonials', 'url' => admin_url('edit.php?post_type=eh_testimonial')),
            array('label' => 'Team Members', 'url' => admin_url('edit.php?post_type=eh_team_member')),
        );

        if (EasyHeadless_Modules::is_enabled('church')) {
            $content_links = array_merge(array(
                array('label' => 'Church Settings', 'url' => admin_url('edit.php?post_type=eh_church_settings')),
                array('label' => 'Sermons', 'url' => admin_url('edit.php?post_type=eh_sermon')),
                array('label' => 'Events', 'url' => admin_url('edit.php?post_type=eh_event')),
                array('label' => 'Ministries', 'url' => admin_url('edit.php?post_type=eh_ministry')),
                array('label' => 'Leaders', 'url' => admin_url('edit.php?post_type=eh_leader')),
                array('label' => 'Service Times', 'url' => admin_url('edit.php?post_type=eh_service_time')),
                array('label' => 'Policies', 'url' => admin_url('edit.php?post_type=eh_policy')),
            ), $content_links);
        }
        ?>
        <div class="wrap">
            <h1>EasyHeadless</h1>
            <p>Manage the editable content and API settings exposed to your headless frontend.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;max-width:1100px;margin-top:20px;">
                <?php foreach ($content_links as $link) : ?>
                    <a href="<?php echo esc_url($link['url']); ?>" class="button button-secondary" style="height:auto;padding:14px 16px;text-align:left;font-weight:600;">
                        <?php echo esc_html($link['label']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if (current_user_can('manage_options')) : ?>
                <p style="margin-top:24px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::ADMIN_MENU_SLUG)); ?>" class="button button-primary">Open EasyHeadless</a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function register_settings()
    {
        register_setting(
            'easyheadless',
            self::OPTION_ALLOWED_ORIGINS,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_origins'),
                'default' => '',
            )
        );

        register_setting(
            'easyheadless',
            self::OPTION_API_KEY_HASH,
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );

        register_setting('easyheadless', EasyHeadless_Modules::OPTION_SITE_PROFILE, array(
            'type' => 'array',
            'sanitize_callback' => array('EasyHeadless_Modules', 'sanitize_site_profile'),
            'default' => EasyHeadless_Modules::defaults(),
        ));
        register_setting('easyheadless', EasyHeadless_Modules::OPTION_ENABLED_MODULES, array(
            'type' => 'array',
            'sanitize_callback' => array('EasyHeadless_Modules', 'sanitize_enabled_modules'),
            'default' => EasyHeadless_Modules::enabled_modules(),
        ));
        register_setting('easyheadless', EasyHeadless_Modules::OPTION_PORTFOLIO, array(
            'type' => 'array',
            'sanitize_callback' => array('EasyHeadless_Modules', 'sanitize_portfolio'),
            'default' => array(),
        ));
        register_setting('easyheadless', EasyHeadless_Modules::OPTION_FEATURED_COURSES, array(
            'type' => 'array',
            'sanitize_callback' => array('EasyHeadless_Modules', 'sanitize_featured_courses'),
            'default' => array(),
        ));
        register_setting('easyheadless', EasyHeadless_Modules::OPTION_ALLOWED_FORMS, array(
            'type' => 'array',
            'sanitize_callback' => array('EasyHeadless_Modules', 'sanitize_allowed_forms'),
            'default' => array(),
        ));
        register_setting('easyheadless', EasyHeadless_Updater::OPTION_CONFIG, array(
            'type' => 'array',
            'sanitize_callback' => array('EasyHeadless_Updater', 'sanitize_config'),
            'default' => array(),
        ));
    }

    public function enqueue_settings_assets($hook)
    {
        EasyHeadless_Admin::enqueue_assets($hook);
    }

    public function sanitize_origins($value)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
        $origins = array();

        foreach ($lines as $line) {
            $origin = esc_url_raw(trim($line));
            if ($origin) {
                $origins[] = rtrim($origin, '/');
            }
        }

        return implode("\n", array_unique($origins));
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $origins = esc_textarea(get_option(self::OPTION_ALLOWED_ORIGINS, ''));
        $preview_token = esc_html(get_option(self::OPTION_PREVIEW_TOKEN, ''));
        $new_key = null;

        if (isset($_POST['easyheadless_regenerate_api_key']) && check_admin_referer('easyheadless_regenerate_api_key')) {
            $new_key = $this->regenerate_api_key();
        }

        $api_key_hint = esc_html(get_option(self::OPTION_API_KEY_HINT, ''));
        $profile = EasyHeadless_Modules::site_profile();
        $modules = EasyHeadless_Modules::enabled_modules();
        $portfolio = get_option(EasyHeadless_Modules::OPTION_PORTFOLIO, array());
        $featured_courses = EasyHeadless_Modules::featured_course_ids();
        $available_courses = post_type_exists('courses') ? get_posts(array(
            'post_type' => 'courses',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        )) : array();
        $allowed_forms = EasyHeadless_Modules::allowed_form_ids();
        $updater = EasyHeadless_Updater::instance();
        $updater_config = $updater->config();
        $updater_status = $updater->public_status();
        $profile_option = EasyHeadless_Modules::OPTION_SITE_PROFILE;
        $modules_option = EasyHeadless_Modules::OPTION_ENABLED_MODULES;
        ?>
        <div class="wrap">
            <h1>EasyHeadless</h1>
            <form method="post" action="options.php">
                <?php settings_fields('easyheadless'); ?>
                <table class="form-table" role="presentation">
                    <tr><th colspan="2"><h2>Modules</h2></th></tr>
                    <tr>
                        <th scope="row">Enabled modules</th>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr($modules_option); ?>[core]" value="1">
                            <label><input type="checkbox" checked disabled> Core</label><br>
                            <?php foreach (array('portfolio' => 'Portfolio', 'church' => 'Church', 'tutor' => 'Tutor LMS', 'forms' => 'Fluent Forms') as $key => $label) : ?>
                                <label><input type="checkbox" name="<?php echo esc_attr($modules_option); ?>[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($modules[$key])); ?>> <?php echo esc_html($label); ?></label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save modules'); ?>
            </form>
            <?php if ($new_key) : ?>
                <div class="notice notice-success">
                    <p><strong>New API key generated.</strong> Copy it now; it will not be shown again.</p>
                    <p><code><?php echo esc_html($new_key); ?></code></p>
                </div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('easyheadless'); ?>
                <table class="form-table" role="presentation">
                    <tr><th colspan="2"><h2>Site and portal</h2></th></tr>
                    <?php
                    $text_fields = array(
                        'company_name' => 'Company name',
                        'phone' => 'Phone',
                        'email' => 'Email',
                        'address' => 'Address',
                        'short_description' => 'Short description',
                        'navigation_location' => 'Menu location slug',
                    );
                    foreach ($text_fields as $key => $label) :
                    ?>
                        <tr>
                            <th scope="row"><label for="easyheadless_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                            <td><input class="regular-text" id="easyheadless_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($profile_option); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr(isset($profile[$key]) ? $profile[$key] : ''); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php
                    $url_fields = array(
                        'frontend_url' => 'Public frontend URL',
                        'lms_url' => 'LMS URL',
                        'login_url' => 'Login URL',
                        'registration_url' => 'Registration URL',
                        'dashboard_url' => 'Dashboard URL',
                        'courses_url' => 'Course catalogue URL',
                        'revalidation_url' => 'Revalidation webhook URL',
                    );
                    foreach ($url_fields as $key => $label) :
                    ?>
                        <tr>
                            <th scope="row"><label for="easyheadless_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                            <td><input class="regular-text code" type="url" id="easyheadless_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($profile_option); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr(isset($profile[$key]) ? $profile[$key] : ''); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th scope="row"><label for="easyheadless_revalidation_secret">Revalidation secret</label></th>
                        <td><input class="regular-text code" type="password" id="easyheadless_revalidation_secret" name="<?php echo esc_attr($profile_option); ?>[revalidation_secret]" value="<?php echo esc_attr($profile['revalidation_secret']); ?>" autocomplete="new-password"></td>
                    </tr>
                    <tr>
                        <th scope="row">Headless routing</th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr($profile_option); ?>[headless_routing]" value="1" <?php checked(!empty($profile['headless_routing'])); ?>> Redirect public WordPress pages and posts to the frontend.</label></td>
                    </tr>
                    <tr><th colspan="2"><h2>Portfolio gallery</h2></th></tr>
                    <tr>
                        <th scope="row">Selected media</th>
                        <td>
                            <input type="hidden" id="easyheadless_portfolio_json" name="<?php echo esc_attr(EasyHeadless_Modules::OPTION_PORTFOLIO); ?>" value="<?php echo esc_attr(wp_json_encode($portfolio)); ?>">
                            <button type="button" class="button" id="easyheadless_add_portfolio">Choose images</button>
                            <p class="description">Choose from the Media Library, drag to reorder, and override public metadata below.</p>
                            <div id="easyheadless_portfolio_items"></div>
                        </td>
                    </tr>
                    <tr><th colspan="2"><h2>Tutor LMS</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="easyheadless_course_picker">Featured courses</label></th>
                        <td>
                            <?php if ($available_courses) : ?>
                                <select id="easyheadless_course_picker">
                                    <option value="">Select a published course</option>
                                    <?php foreach ($available_courses as $course) : ?>
                                        <option value="<?php echo esc_attr($course->ID); ?>"><?php echo esc_html(get_the_title($course)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button" id="easyheadless_add_course">Add course</button>
                                <ul id="easyheadless_featured_course_items" style="max-width:600px;margin-top:12px;">
                                    <?php foreach ($featured_courses as $course_id) : ?>
                                        <?php $course = get_post($course_id); ?>
                                        <?php if ($course && 'courses' === $course->post_type) : ?>
                                            <li data-id="<?php echo esc_attr($course_id); ?>" style="display:flex;align-items:center;justify-content:space-between;border:1px solid #dcdcde;background:#fff;padding:10px 12px;margin:6px 0;cursor:move;">
                                                <span><?php echo esc_html(get_the_title($course)); ?></span>
                                                <input type="hidden" name="<?php echo esc_attr(EasyHeadless_Modules::OPTION_FEATURED_COURSES); ?>[]" value="<?php echo esc_attr($course_id); ?>">
                                                <button type="button" class="button-link-delete easyheadless-remove-course">Remove</button>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="description">Drag to set public display order. Leave empty to use latest published courses.</p>
                            <?php else : ?>
                                <p>Tutor LMS has no published courses available. The public course endpoint will report a degraded or empty state.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr><th colspan="2"><h2>Fluent Forms</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="easyheadless_allowed_forms">Public form IDs</label></th>
                        <td>
                            <input class="regular-text" id="easyheadless_allowed_forms" name="<?php echo esc_attr(EasyHeadless_Modules::OPTION_ALLOWED_FORMS); ?>" value="<?php echo esc_attr(implode(',', $allowed_forms)); ?>">
                            <p class="description">Comma-separated IDs, for example fluentforms:4. An empty allowlist exposes no forms.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="easyheadless_allowed_origins">Allowed frontend origins</label></th>
                        <td>
                            <textarea id="easyheadless_allowed_origins" name="<?php echo esc_attr(self::OPTION_ALLOWED_ORIGINS); ?>" rows="6" cols="70"><?php echo $origins; ?></textarea>
                            <p class="description">One origin per line, for example https://www.clientsite.com.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Preview token</th>
                        <td><code><?php echo $preview_token; ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">MCP API key</th>
                        <td>
                            <p>Current key ending: <code><?php echo $api_key_hint ? $api_key_hint : 'not generated'; ?></code></p>
                            <p class="description">Use this key from the EasyHeadless MCP server as a bearer token for write endpoints.</p>
                        </td>
                    </tr>
                    <tr><th colspan="2"><h2>Signed updates</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="easyheadless_update_manifest_url">Release manifest URL</label></th>
                        <td>
                            <?php if (defined('EASYHEADLESS_UPDATE_MANIFEST_URL')) : ?>
                                <code>Locked by EASYHEADLESS_UPDATE_MANIFEST_URL</code>
                            <?php else : ?>
                                <input class="large-text code" type="url" id="easyheadless_update_manifest_url" name="<?php echo esc_attr(EasyHeadless_Updater::OPTION_CONFIG); ?>[manifest_url]" value="<?php echo esc_attr($updater_config['manifest_url']); ?>" placeholder="https://releases.example.com/easyheadless/stable.json">
                            <?php endif; ?>
                            <p class="description">Only an HTTPS EasyHeadless manifest signed by the trusted key can be installed.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="easyheadless_update_public_key">Ed25519 public key</label></th>
                        <td>
                            <?php if (defined('EASYHEADLESS_UPDATE_PUBLIC_KEY')) : ?>
                                <code>Locked by EASYHEADLESS_UPDATE_PUBLIC_KEY</code>
                            <?php else : ?>
                                <input class="large-text code" type="text" id="easyheadless_update_public_key" name="<?php echo esc_attr(EasyHeadless_Updater::OPTION_CONFIG); ?>[public_key]" value="<?php echo esc_attr($updater_config['public_key']); ?>" autocomplete="off" placeholder="Base64-encoded 32-byte public key">
                            <?php endif; ?>
                            <p class="description">The private signing key must never be uploaded to WordPress.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Updater status</th>
                        <td>
                            <p><strong><?php echo esc_html($updater_status['status']); ?></strong> — <?php echo esc_html($updater_status['message']); ?></p>
                            <p class="description">Installed: <?php echo esc_html($updater_status['currentVersion']); ?><?php echo $updater_status['latestVersion'] ? ' · Latest checked: ' . esc_html($updater_status['latestVersion']) : ''; ?>. Remote installation is explicit; automatic updates are not enabled.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <script>
            jQuery(function ($) {
                var $input = $('#easyheadless_portfolio_json');
                var $items = $('#easyheadless_portfolio_items');
                var items = [];
                try { items = JSON.parse($input.val() || '[]'); } catch (error) { items = []; }

                function sync() {
                    var next = [];
                    $items.children('.easyheadless-portfolio-item').each(function () {
                        var $item = $(this);
                        next.push({
                            attachmentId: parseInt($item.attr('data-id'), 10),
                            title: $item.find('[data-field="title"]').val() || '',
                            caption: $item.find('[data-field="caption"]').val() || '',
                            alt: $item.find('[data-field="alt"]').val() || '',
                            category: $item.find('[data-field="category"]').val() || 'all'
                        });
                    });
                    items = next;
                    $input.val(JSON.stringify(items));
                }

                function render() {
                    $items.empty();
                    items.forEach(function (item) {
                        var media = wp.media.attachment(item.attachmentId);
                        media.fetch().then(function () {
                            var data = media.toJSON();
                            var thumb = data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : data.url;
                            var $row = $('<div class="easyheadless-portfolio-item" style="display:grid;grid-template-columns:90px 1fr auto;gap:12px;align-items:start;border:1px solid #dcdcde;background:#fff;padding:12px;margin-top:10px;cursor:move;"></div>').attr('data-id', item.attachmentId);
                            $('<img alt="" style="width:90px;height:72px;object-fit:cover;">').attr('src', thumb).appendTo($row);
                            var $fields = $('<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;"></div>').appendTo($row);
                            [['title', 'Title'], ['alt', 'Alt text'], ['category', 'Category'], ['caption', 'Caption']].forEach(function (field) {
                                $('<input class="regular-text" style="width:100%">').attr('data-field', field[0]).attr('placeholder', field[1]).val(item[field[0]] || '').appendTo($fields);
                            });
                            $('<button type="button" class="button-link-delete">Remove</button>').on('click', function () { $row.remove(); sync(); }).appendTo($row);
                            $row.on('input change', 'input', sync);
                            $items.append($row);
                        });
                    });
                }

                $('#easyheadless_add_portfolio').on('click', function () {
                    var frame = wp.media({ title: 'Select portfolio images', library: { type: 'image' }, multiple: 'add' });
                    frame.on('select', function () {
                        frame.state().get('selection').each(function (attachment) {
                            var data = attachment.toJSON();
                            if (!items.some(function (item) { return parseInt(item.attachmentId, 10) === data.id; })) {
                                items.push({ attachmentId: data.id, title: data.title || '', caption: data.caption || '', alt: data.alt || '', category: 'all' });
                            }
                        });
                        $input.val(JSON.stringify(items));
                        render();
                    });
                    frame.open();
                });

                $items.sortable({ update: sync });
                render();

                var $courses = $('#easyheadless_featured_course_items');
                $courses.sortable();
                $('#easyheadless_add_course').on('click', function () {
                    var $picker = $('#easyheadless_course_picker');
                    var id = parseInt($picker.val(), 10);
                    if (!id || $courses.find('[data-id="' + id + '"]').length) {
                        return;
                    }
                    var $row = $('<li style="display:flex;align-items:center;justify-content:space-between;border:1px solid #dcdcde;background:#fff;padding:10px 12px;margin:6px 0;cursor:move;"></li>').attr('data-id', id);
                    $('<span></span>').text($picker.find('option:selected').text()).appendTo($row);
                    $('<input type="hidden">').attr('name', '<?php echo esc_js(EasyHeadless_Modules::OPTION_FEATURED_COURSES); ?>[]').val(id).appendTo($row);
                    $('<button type="button" class="button-link-delete easyheadless-remove-course">Remove</button>').appendTo($row);
                    $courses.append($row);
                    $picker.val('');
                });
                $courses.on('click', '.easyheadless-remove-course', function () {
                    $(this).closest('li').remove();
                });
            });
            </script>
            <form method="post">
                <?php wp_nonce_field('easyheadless_regenerate_api_key'); ?>
                <?php submit_button('Regenerate MCP API Key', 'secondary', 'easyheadless_regenerate_api_key'); ?>
            </form>
        </div>
        <?php
    }

    public function render_admin_notices()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (function_exists('acf_add_local_field_group') && !function_exists('acf_add_options_page')) {
            ?>
            <div class="notice notice-warning">
                <p><strong>EasyHeadless:</strong> ACF is active, but ACF Options Pages are not available. Use the Church Settings content type under the EasyHeadless menu for ACF Free global church settings.</p>
            </div>
            <?php
        }
    }

    public static function generate_api_key()
    {
        return 'eh_' . wp_generate_password(48, false, false);
    }

    public function regenerate_api_key()
    {
        $api_key = self::generate_api_key();
        update_option(self::OPTION_API_KEY_HASH, wp_hash_password($api_key));
        update_option(self::OPTION_API_KEY_HINT, substr($api_key, -6));

        return $api_key;
    }

    public static function verify_api_key($api_key)
    {
        $hash = (string) get_option(self::OPTION_API_KEY_HASH, '');

        if (!$hash || !$api_key) {
            return false;
        }

        return wp_check_password((string) $api_key, $hash);
    }

    public function register_cors_headers()
    {
        add_filter('rest_pre_serve_request', array($this, 'send_cors_headers'), 10, 4);
    }

    public function send_cors_headers($served, $result, $request, $server)
    {
        $route = $request instanceof WP_REST_Request ? $request->get_route() : '';

        if (0 !== strpos($route, '/easyheadless/v1')) {
            return $served;
        }

        $origin = isset($_SERVER['HTTP_ORIGIN']) ? rtrim(sanitize_text_field(wp_unslash($_SERVER['HTTP_ORIGIN'])), '/') : '';
        $allowed = $this->get_allowed_origins();

        if ($origin && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin', false);
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
        }

        return $served;
    }

    public function get_allowed_origins()
    {
        $stored = get_option(self::OPTION_ALLOWED_ORIGINS, '');
        $lines = preg_split('/\r\n|\r|\n/', (string) $stored);

        return array_values(
            array_filter(
                array_map(
                    function ($origin) {
                        return rtrim(trim($origin), '/');
                    },
                    $lines
                )
            )
        );
    }

    public function notify_content_changed($post_id, $post, $update)
    {
        if (wp_is_post_revision($post_id) || 'auto-draft' === $post->post_status) {
            return;
        }

        do_action('easyheadless_content_changed', $post_id, $post);
    }

    public function notify_acf_changed($post_id)
    {
        do_action('easyheadless_acf_changed', $post_id);
    }
}
