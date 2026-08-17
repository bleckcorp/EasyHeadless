<?php

if (!defined('ABSPATH')) {
    exit;
}

final class EasyHeadless_Admin
{
    const NONCE_ACTION = 'easyheadless_admin_save';
    const PAGE_PREFIX = 'easyheadless-';

    public static function register_hooks()
    {
        foreach (array(
            'modules' => 'save_modules',
            'site' => 'save_site',
            'portfolio' => 'save_portfolio',
            'courses' => 'save_courses',
            'forms' => 'save_forms',
            'integrations' => 'save_integrations',
        ) as $action => $handler) {
            add_action('admin_post_easyheadless_' . $action, array(__CLASS__, $handler));
        }
    }

    public static function register_menu()
    {
        add_menu_page(
            'EasyHeadless Dashboard',
            'EasyHeadless',
            'edit_posts',
            EasyHeadless_Plugin::ADMIN_MENU_SLUG,
            array(__CLASS__, 'render_overview'),
            'dashicons-rest-api',
            30
        );

        self::submenu('Site & URLs', 'Site & URLs', self::PAGE_PREFIX . 'site', 'render_site');
        self::submenu('Navigation', 'Navigation', self::PAGE_PREFIX . 'navigation', 'render_navigation', 'edit_theme_options');
        self::submenu('Content Library', 'Content Library', self::PAGE_PREFIX . 'content', 'render_content', 'edit_posts');

        if (EasyHeadless_Modules::is_enabled('portfolio')) {
            self::submenu('Portfolio', 'Portfolio', self::PAGE_PREFIX . 'portfolio', 'render_portfolio');
        }
        if (EasyHeadless_Modules::is_enabled('tutor')) {
            self::submenu('Courses', 'Courses', self::PAGE_PREFIX . 'courses', 'render_courses');
        }
        if (EasyHeadless_Modules::is_enabled('forms')) {
            self::submenu('Forms', 'Forms', self::PAGE_PREFIX . 'forms', 'render_forms');
        }

        self::submenu('Modules', 'Modules', self::PAGE_PREFIX . 'modules', 'render_modules');
        self::submenu('Integrations & Updates', 'Integrations & Updates', self::PAGE_PREFIX . 'integrations', 'render_integrations');

        // WordPress must retain these submenu records to authorize direct screen
        // URLs and resolve page titles. The internal plugin rail is the visible
        // navigation, so hide the duplicate native flyout without unregistering it.
        add_action('admin_head', array(__CLASS__, 'hide_native_submenu'));
    }

    public static function hide_native_submenu()
    {
        echo '<style id="easyheadless-native-menu">#toplevel_page_easyheadless .wp-submenu{display:none!important}#toplevel_page_easyheadless.wp-has-submenu>a.wp-has-submenu:after{display:none!important}</style>';
    }

    private static function submenu($page_title, $menu_title, $slug, $callback, $capability = 'manage_options')
    {
        add_submenu_page(
            EasyHeadless_Plugin::ADMIN_MENU_SLUG,
            $page_title,
            $menu_title,
            $capability,
            $slug,
            array(__CLASS__, $callback)
        );
    }

    public static function enqueue_assets($hook)
    {
        if (false === strpos((string) $hook, 'easyheadless')) {
            return;
        }

        wp_enqueue_style(
            'easyheadless-admin',
            EASYHEADLESS_PLUGIN_URL . 'assets/admin.css',
            array(),
            EASYHEADLESS_VERSION
        );
        wp_enqueue_script(
            'easyheadless-admin',
            EASYHEADLESS_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            EASYHEADLESS_VERSION,
            true
        );

        if (false !== strpos((string) $hook, self::PAGE_PREFIX . 'portfolio')) {
            wp_enqueue_media();
        }
    }

    public static function render_overview()
    {
        self::guard('edit_posts');
        $forms = new EasyHeadless_Forms();
        $capabilities = EasyHeadless_Modules::capabilities($forms);
        $profile = EasyHeadless_Modules::site_profile();
        $navigation = EasyHeadless_Modules::navigation();
        $updater = EasyHeadless_Updater::instance()->public_status();
        $frontend_ready = !empty($profile['frontend_url']);
        $origins_ready = (bool) trim((string) get_option(EasyHeadless_Plugin::OPTION_ALLOWED_ORIGINS, ''));

        self::open_page('Dashboard', 'A clear view of what is connected, what is public, and what needs attention.', 'overview');
        ?>
        <section class="eh-connection-panel <?php echo $frontend_ready ? 'is-ready' : 'needs-setup'; ?>">
            <div class="eh-connection-summary">
                <span class="dashicons <?php echo $frontend_ready ? 'dashicons-yes-alt' : 'dashicons-admin-generic'; ?>" aria-hidden="true"></span>
                <div>
                    <h2><?php echo $frontend_ready ? 'Connection is ready' : 'Finish connecting your frontend'; ?></h2>
                    <p><?php echo $frontend_ready ? 'WordPress is ready to serve this headless website.' : 'Add the public frontend URL and allowed origin to complete setup.'; ?></p>
                </div>
            </div>
            <dl class="eh-connection-details">
                <div><dt>Public frontend</dt><dd><?php echo esc_html($frontend_ready ? $profile['frontend_url'] : 'Not configured'); ?></dd></div>
                <div><dt>REST API</dt><dd class="eh-text-success">Available</dd></div>
            </dl>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_PREFIX . 'site')); ?>"><?php echo $frontend_ready ? 'Review setup' : 'Configure site'; ?></a>
        </section>
        <section class="eh-section-block">
            <div class="eh-section-heading"><div><h2>Module health</h2><p>Only enabled capabilities appear in the working navigation.</p></div><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_PREFIX . 'modules')); ?>">Manage modules</a></div>
            <div class="eh-health-table">
                <div class="eh-table-head"><span>Module</span><span>Status</span><span>Notes</span><span></span></div>
            <?php foreach ($capabilities as $key => $status) : ?>
                <?php
                $label = self::module_label($key);
                $targets = array('core' => 'site', 'portfolio' => 'portfolio', 'church' => 'content', 'tutor' => 'courses', 'forms' => 'forms', 'updater' => 'integrations');
                $target = isset($targets[$key]) ? $targets[$key] : 'modules';
                if ('disabled' === $status['status']) {
                    $target = 'modules';
                }
                $target_slug = 'site' === $target ? self::PAGE_PREFIX . 'site' : ('overview' === $target ? EasyHeadless_Plugin::ADMIN_MENU_SLUG : self::PAGE_PREFIX . $target);
                ?>
                <a class="eh-health-row eh-status-<?php echo esc_attr($status['status']); ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . $target_slug)); ?>">
                    <strong><span class="eh-status-dot"></span><?php echo esc_html($label); ?></strong>
                    <span class="eh-state-label"><?php echo esc_html(ucfirst($status['status'])); ?></span>
                    <span><?php echo esc_html($status['message'] ? $status['message'] : self::healthy_module_message($key)); ?></span>
                    <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                </a>
            <?php endforeach; ?>
            </div>
        </section>
        <div class="eh-dashboard-columns">
            <section class="eh-panel">
                <div class="eh-section-heading"><div><h2>Finish setup</h2><p>Complete these checks before launch.</p></div></div>
                <ul class="eh-task-list">
                    <?php foreach (array(
                        array($frontend_ready, 'Set the public frontend URL', 'Tell EasyHeadless where the public site lives.', 'site'),
                        array($origins_ready, 'Allow the frontend origin', 'Permit browser requests from the public site.', 'site'),
                        array(!empty($navigation), 'Review public navigation', 'Select and verify the WordPress menu used by the frontend.', 'navigation'),
                        array(!empty($updater['configured']), 'Configure trusted updates', 'Add a signed HTTPS release manifest and public key.', 'integrations'),
                    ) as $task) : ?>
                        <li class="<?php echo $task[0] ? 'is-complete' : ''; ?>"><span class="dashicons <?php echo $task[0] ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" aria-hidden="true"></span><a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_PREFIX . $task[3])); ?>"><strong><?php echo esc_html($task[1]); ?></strong><small><?php echo esc_html($task[2]); ?></small></a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="eh-panel">
                <div class="eh-section-heading"><div><h2>Manage content</h2><p>Edit reusable content without crowding the WordPress menu.</p></div><a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_PREFIX . 'content')); ?>">View all</a></div>
                <div class="eh-action-list">
                    <?php foreach (array_slice(self::content_types(), 0, 4) as $type => $content) : ?>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . $type)); ?>"><span class="dashicons <?php echo esc_attr($content['icon']); ?>" aria-hidden="true"></span><span><strong><?php echo esc_html($content['label']); ?></strong><small><?php echo esc_html($content['description']); ?></small></span><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="eh-panel">
                <div class="eh-section-heading"><div><h2>Architecture</h2><p>Current delivery model.</p></div></div>
                <dl class="eh-definition-list">
                    <div><dt>WordPress CMS</dt><dd><?php echo esc_html(home_url('/')); ?></dd></div>
                    <div><dt>Frontend</dt><dd><?php echo esc_html($frontend_ready ? $profile['frontend_url'] : 'Not configured'); ?></dd></div>
                    <div><dt>API</dt><dd><?php echo esc_html(rest_url('easyheadless/v1')); ?></dd></div>
                    <div><dt>Headless routing</dt><dd><?php echo !empty($profile['headless_routing']) ? 'Enabled' : 'Disabled'; ?></dd></div>
                    <div><dt>Updates</dt><dd><?php echo !empty($updater['configured']) ? 'Configured' : 'Not configured'; ?></dd></div>
                </dl>
            </section>
        </div>
        <?php
        self::close_page();
    }

    public static function render_content()
    {
        self::guard('edit_posts');
        self::open_page('Content library', 'Manage reusable website content while keeping the WordPress menu focused.', 'content');
        ?>
        <section class="eh-section-block">
            <div class="eh-section-heading"><div><h2>Content types</h2><p>These native WordPress records are available to headless frontends through EasyHeadless.</p></div></div>
            <div class="eh-content-table">
                <div class="eh-table-head"><span>Content</span><span>Purpose</span><span>Items</span><span>Actions</span></div>
                <?php foreach (self::content_types() as $type => $content) : ?>
                    <div class="eh-content-row">
                        <strong><span class="eh-icon-box dashicons <?php echo esc_attr($content['icon']); ?>" aria-hidden="true"></span><?php echo esc_html($content['label']); ?></strong>
                        <span><?php echo esc_html($content['description']); ?></span>
                        <span><?php echo esc_html(self::post_type_count($type)); ?></span>
                        <span class="eh-row-actions"><a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=' . $type)); ?>">Manage</a><a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . $type)); ?>">Add new</a></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        self::close_page();
    }

    public static function render_modules()
    {
        self::guard();
        $modules = EasyHeadless_Modules::enabled_modules();
        $forms = new EasyHeadless_Forms();
        $health = EasyHeadless_Modules::capabilities($forms);
        $definitions = array(
            'core' => array('Core', 'Required foundation, normalized API, routing, and site settings.', 'dashicons-admin-generic'),
            'portfolio' => array('Portfolio', 'Create curated media galleries from WordPress attachments.', 'dashicons-format-gallery'),
            'church' => array('Church', 'Add sermons, events, ministries, leaders, services, and policies.', 'dashicons-admin-home'),
            'tutor' => array('Tutor LMS', 'Expose safe public course summaries from Tutor LMS.', 'dashicons-welcome-learn-more'),
            'forms' => array('Fluent Forms', 'Expose approved form schemas and native submission handling.', 'dashicons-feedback'),
        );

        self::open_page('Modules', 'Tailor EasyHeadless to each project without deleting saved content.', 'modules');
        self::notice();
        ?>
        <div class="eh-module-layout">
            <form class="eh-section-block" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php self::form_token('easyheadless_modules'); ?>
                <div class="eh-module-table">
                    <div class="eh-table-head"><span>Module</span><span>Purpose</span><span>Dependency status</span><span>State</span></div>
                    <?php foreach ($definitions as $key => $definition) : ?>
                        <?php $status = isset($health[$key]) ? $health[$key] : array('status' => 'ready', 'message' => ''); ?>
                        <label class="eh-module-row <?php echo 'core' === $key ? 'is-locked' : ''; ?>">
                            <strong><span class="eh-icon-box dashicons <?php echo esc_attr($definition[2]); ?>" aria-hidden="true"></span><?php echo esc_html($definition[0]); ?></strong>
                            <span><?php echo esc_html($definition[1]); ?></span>
                            <span class="eh-dependency eh-status-<?php echo esc_attr($status['status']); ?>"><span class="eh-status-dot"></span><span><strong><?php echo esc_html('ready' === $status['status'] ? 'All dependencies met' : ucfirst($status['status'])); ?></strong><small><?php echo esc_html($status['message']); ?></small></span></span>
                            <span><?php if ('core' === $key) : ?><span class="eh-locked-state"><span class="dashicons dashicons-lock" aria-hidden="true"></span>Always active</span><input type="hidden" name="modules[core]" value="1"><?php else : ?><span class="eh-switch"><input type="checkbox" name="modules[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($modules[$key])); ?>><i aria-hidden="true"></i><span class="screen-reader-text">Enable <?php echo esc_html($definition[0]); ?></span></span><?php endif; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="eh-module-note"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span>Disabling a module hides its EasyHeadless navigation and API routes. Saved content is not deleted.</p>
                <div class="eh-form-actions"><button class="button button-primary" type="submit">Save modules</button></div>
            </form>
            <aside class="eh-help-panel"><h2>What changes?</h2><h3>Enabling a module</h3><p>Adds the module’s management screen and API routes. Missing dependencies are shown as degraded until installed.</p><h3>Disabling a module</h3><p>Removes the module from the working navigation and public routes while preserving its settings and content.</p></aside>
        </div>
        <?php
        self::close_page();
    }

    public static function render_site()
    {
        self::guard();
        $profile = EasyHeadless_Modules::site_profile();
        self::open_page('Site & URLs', 'Manage site identity, frontend URLs, portal links, and routing in one place.', 'site');
        self::notice();
        ?>
        <form class="eh-card eh-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php self::form_token('easyheadless_site'); ?>
            <div class="eh-section-heading"><div><h2>Company information</h2><p>Native WordPress options remain the source of truth.</p></div></div>
            <div class="eh-form-grid">
                <?php foreach (array('company_name' => 'Company name', 'email' => 'Email', 'phone' => 'Phone', 'address' => 'Address', 'short_description' => 'Short description', 'navigation_location' => 'Menu location slug') as $key => $label) : ?>
                    <label><span><?php echo esc_html($label); ?></span><input type="text" name="profile[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr(isset($profile[$key]) ? $profile[$key] : ''); ?>"></label>
                <?php endforeach; ?>
            </div>
            <div class="eh-section-heading eh-section-divider"><div><h2>Domains and portal links</h2><p>Keep learning and checkout flows on the LMS.</p></div></div>
            <div class="eh-form-grid">
                <?php foreach (array('frontend_url' => 'Public frontend URL', 'lms_url' => 'LMS URL', 'login_url' => 'Login URL', 'registration_url' => 'Registration URL', 'dashboard_url' => 'Dashboard URL', 'courses_url' => 'Course catalogue URL', 'revalidation_url' => 'Revalidation webhook URL') as $key => $label) : ?>
                    <label><span><?php echo esc_html($label); ?></span><input type="url" name="profile[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr(isset($profile[$key]) ? $profile[$key] : ''); ?>"></label>
                <?php endforeach; ?>
                <label><span>Revalidation secret</span><input type="password" name="profile[revalidation_secret]" value="<?php echo esc_attr($profile['revalidation_secret']); ?>" autocomplete="new-password"></label>
            </div>
            <label class="eh-check"><input type="checkbox" name="profile[headless_routing]" value="1" <?php checked(!empty($profile['headless_routing'])); ?>><span><strong>Enable headless routing</strong><small>Redirect public WordPress pages and posts while preserving LMS, checkout, admin, REST, and authentication routes.</small></span></label>
            <label class="eh-field-full"><span>Allowed frontend origins</span><textarea name="origins" rows="4"><?php echo esc_textarea(get_option(EasyHeadless_Plugin::OPTION_ALLOWED_ORIGINS, '')); ?></textarea><small>One HTTPS origin per line.</small></label>
            <div class="eh-form-actions"><button class="button button-primary" type="submit">Save site settings</button></div>
        </form>
        <?php
        self::close_page();
    }

    public static function render_navigation()
    {
        self::guard('edit_theme_options');
        $items = EasyHeadless_Modules::navigation();
        self::open_page('Navigation', 'EasyHeadless normalizes your existing WordPress menu.', 'navigation');
        ?>
        <section class="eh-card">
            <div class="eh-section-heading"><div><h2>Public navigation</h2><p><?php echo esc_html(count($items)); ?> normalized menu items are currently available.</p></div><a class="button button-primary" href="<?php echo esc_url(admin_url('nav-menus.php')); ?>">Manage WordPress menus</a></div>
            <div class="eh-simple-table"><div class="eh-table-head"><span>Label</span><span>URL</span><span>Target</span></div><?php foreach ($items as $item) : ?><div><strong><?php echo esc_html($item['label']); ?></strong><code><?php echo esc_html($item['url']); ?></code><span><?php echo esc_html($item['target']); ?></span></div><?php endforeach; ?></div>
        </section>
        <?php
        self::close_page();
    }

    public static function render_portfolio()
    {
        self::guard();
        $saved = EasyHeadless_Modules::sanitize_portfolio(get_option(EasyHeadless_Modules::OPTION_PORTFOLIO, array()));
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $category = isset($_GET['category']) ? sanitize_title(wp_unslash($_GET['category'])) : '';
        $page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $per_page = 24;
        $categories = array();
        $filtered = array();

        foreach ($saved as $item) {
            $item_category = !empty($item['category']) ? $item['category'] : 'all';
            $categories[$item_category] = $item_category;
            $haystack = strtolower(trim($item['title'] . ' ' . $item['caption'] . ' ' . $item['alt']));
            if ($category && $category !== $item_category) {
                continue;
            }
            if ($search && false === strpos($haystack, strtolower($search))) {
                continue;
            }
            $filtered[] = $item;
        }

        $total = count($filtered);
        $pages = max(1, (int) ceil($total / $per_page));
        $page = min($page, $pages);
        $visible = array_slice($filtered, ($page - 1) * $per_page, $per_page);

        self::open_page('Portfolio', 'Manage media without loading your entire library.', 'portfolio');
        self::notice();
        ?>
        <section class="eh-card eh-portfolio-shell">
            <div class="eh-section-heading">
                <div><h2>Portfolio library</h2><p><?php echo esc_html($total); ?> matching items · <?php echo esc_html(count($visible)); ?> loaded on this page.</p></div>
                <div class="eh-button-row"><button type="button" class="button button-primary" id="eh-add-media"><span class="dashicons dashicons-format-gallery" aria-hidden="true"></span> Add from Media Library</button><a class="button" href="<?php echo esc_url(admin_url('upload.php')); ?>">Open Media Library</a></div>
            </div>
            <form class="eh-toolbar" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_PREFIX . 'portfolio'); ?>">
                <label class="screen-reader-text" for="eh-portfolio-search">Search portfolio</label><input id="eh-portfolio-search" type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search saved metadata…">
                <select name="category" aria-label="Filter by category"><option value="">All categories</option><?php foreach ($categories as $slug) : ?><option value="<?php echo esc_attr($slug); ?>" <?php selected($category, $slug); ?>><?php echo esc_html(ucwords(str_replace('-', ' ', $slug))); ?></option><?php endforeach; ?></select>
                <button class="button" type="submit">Filter</button>
                <?php if ($search || $category) : ?><a class="button button-link" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_PREFIX . 'portfolio')); ?>">Clear</a><?php endif; ?>
            </form>
            <form id="eh-portfolio-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php self::form_token('easyheadless_portfolio'); ?>
                <input type="hidden" name="operation" id="eh-portfolio-operation" value="update">
                <input type="hidden" name="attachment_ids" id="eh-add-attachment-ids" value="">
                <div class="eh-bulk-bar" hidden><strong><span id="eh-selected-count">0</span> selected</strong><button type="button" class="button" id="eh-edit-selected">Edit metadata</button><button type="submit" class="button eh-danger" data-operation="remove">Remove from portfolio</button></div>
                <?php if ($visible) : ?>
                    <div class="eh-portfolio-grid">
                        <?php foreach ($visible as $index => $item) : ?>
                            <?php
                            $attachment_id = absint($item['attachmentId']);
                            $image = wp_get_attachment_image_url($attachment_id, 'medium');
                            $title = $item['title'] ? $item['title'] : get_the_title($attachment_id);
                            ?>
                            <article class="eh-portfolio-card" tabindex="0" data-id="<?php echo esc_attr($attachment_id); ?>" data-title="<?php echo esc_attr($item['title']); ?>" data-caption="<?php echo esc_attr(wp_strip_all_tags($item['caption'])); ?>" data-alt="<?php echo esc_attr($item['alt']); ?>" data-category="<?php echo esc_attr($item['category']); ?>">
                                <label class="eh-select-card"><input type="checkbox" name="selected[]" value="<?php echo esc_attr($attachment_id); ?>"><span class="screen-reader-text">Select <?php echo esc_html($title); ?></span></label>
                                <?php if ($image) : ?><img src="<?php echo esc_url($image); ?>" loading="lazy" decoding="async" alt=""><?php else : ?><div class="eh-image-missing"><span class="dashicons dashicons-format-image"></span><span>Media unavailable</span></div><?php endif; ?>
                                <div class="eh-portfolio-card-body"><strong><?php echo esc_html($title ? $title : 'Untitled image'); ?></strong><span class="eh-tag"><?php echo esc_html($item['category'] ? $item['category'] : 'all'); ?></span></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <aside class="eh-inspector" id="eh-inspector" hidden>
                        <div class="eh-inspector-header"><div><span>Image metadata</span><strong id="eh-inspector-name">Selected item</strong></div><button type="button" class="button-link" id="eh-close-inspector" aria-label="Close inspector">×</button></div>
                        <input type="hidden" name="item[attachmentId]" id="eh-item-id">
                        <label><span>Title</span><input type="text" name="item[title]" id="eh-item-title"></label>
                        <label><span>Caption</span><textarea name="item[caption]" id="eh-item-caption" rows="4"></textarea></label>
                        <label><span>Alt text</span><input type="text" name="item[alt]" id="eh-item-alt"></label>
                        <label><span>Category</span><input type="text" name="item[category]" id="eh-item-category"></label>
                        <button type="submit" class="button button-primary" data-operation="update">Save changes</button>
                    </aside>
                <?php else : ?>
                    <div class="eh-empty"><span class="dashicons dashicons-format-gallery"></span><h3>No portfolio items found</h3><p>Add images from the Media Library or change your filters.</p></div>
                <?php endif; ?>
            </form>
            <?php if ($pages > 1) : ?><nav class="eh-pagination" aria-label="Portfolio pages"><?php echo wp_kses_post(paginate_links(array('base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $page, 'total' => $pages, 'type' => 'list', 'prev_text' => 'Previous', 'next_text' => 'Next'))); ?></nav><?php endif; ?>
        </section>
        <?php
        self::close_page();
    }

    public static function render_courses()
    {
        self::guard();
        $selected = EasyHeadless_Modules::featured_course_ids();
        $courses = post_type_exists('courses') ? get_posts(array('post_type' => 'courses', 'post_status' => 'publish', 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC')) : array();
        self::open_page('Courses', 'Choose which Tutor LMS courses appear on public websites.', 'courses');
        self::notice();
        ?>
        <form class="eh-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php self::form_token('easyheadless_courses'); ?>
            <div class="eh-section-heading"><div><h2>Featured courses</h2><p>Leave every course unchecked to fall back to the latest published courses.</p></div><span class="eh-badge"><?php echo post_type_exists('courses') ? 'Tutor LMS ready' : 'Tutor LMS unavailable'; ?></span></div>
            <div class="eh-choice-list"><?php foreach ($courses as $course) : ?><label><input type="checkbox" name="courses[]" value="<?php echo esc_attr($course->ID); ?>" <?php checked(in_array((int) $course->ID, $selected, true)); ?>><span><strong><?php echo esc_html(get_the_title($course)); ?></strong><small><?php echo esc_html($course->post_name); ?></small></span></label><?php endforeach; ?></div>
            <?php if (!$courses) : ?><div class="eh-empty"><h3>No published Tutor courses found</h3><p>The public API will return an empty degraded result until Tutor LMS provides published courses.</p></div><?php endif; ?>
            <div class="eh-form-actions"><button class="button button-primary" type="submit">Save featured courses</button></div>
        </form>
        <?php
        self::close_page();
    }

    public static function render_forms()
    {
        self::guard();
        $adapter = new EasyHeadless_FluentForms_Adapter();
        $diagnostics = $adapter->diagnostics();
        $forms = $adapter->list_forms();
        $allowed = EasyHeadless_Modules::allowed_form_ids();
        self::open_page('Forms', 'Choose which provider forms are public through the headless API.', 'forms');
        self::notice();
        ?>
        <section class="eh-card eh-provider-card"><div class="eh-provider-icon">F</div><div><h2>Fluent Forms <span class="eh-badge <?php echo $diagnostics['loaded'] ? 'eh-badge-success' : 'eh-badge-warning'; ?>"><?php echo $diagnostics['loaded'] ? 'Ready' : 'Needs attention'; ?></span></h2><p>Plugin version: <?php echo esc_html($diagnostics['version'] ? $diagnostics['version'] : 'Unavailable'); ?></p></div><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_PREFIX . 'forms')); ?>">Refresh forms</a></section>
        <form class="eh-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php self::form_token('easyheadless_forms'); ?>
            <div class="eh-section-heading"><div><h2>Available forms</h2><p>Only enabled forms can be read or submitted publicly.</p></div></div>
            <div class="eh-forms-table">
                <div class="eh-table-head"><span>Form</span><span>Provider ID</span><span>Fields</span><span>Public API</span><span>Status</span></div>
                <?php foreach ($forms as $form) : ?>
                    <?php $schema = $adapter->get_form(str_replace('fluentforms:', '', $form['id'])); $fields = is_wp_error($schema) ? array() : $schema['fields']; ?>
                    <label class="eh-form-row"><strong><?php echo esc_html($form['title']); ?></strong><code><?php echo esc_html($form['id']); ?></code><span><?php echo esc_html(count($fields)); ?> fields</span><span class="eh-switch"><input type="checkbox" name="forms[]" value="<?php echo esc_attr($form['id']); ?>" <?php checked(in_array($form['id'], $allowed, true)); ?>><i aria-hidden="true"></i><span class="screen-reader-text">Make <?php echo esc_html($form['title']); ?> public</span></span><span class="eh-schema-status"><span class="eh-status-dot"></span><?php echo is_wp_error($schema) ? 'Schema unavailable' : 'Schema valid'; ?></span></label>
                <?php endforeach; ?>
                <?php if (!$forms) : ?><div class="eh-empty"><h3>No Fluent Forms detected</h3><p><?php echo esc_html($diagnostics['message'] ? $diagnostics['message'] : 'Create a form in Fluent Forms, then refresh this screen.'); ?></p></div><?php endif; ?>
            </div>
            <div class="eh-form-actions"><button class="button button-primary" type="submit">Save public access</button></div>
        </form>
        <div class="eh-two-column">
            <section class="eh-card"><div class="eh-section-heading"><div><h2>Security & behavior</h2></div></div><ul class="eh-check-list"><li>Run Fluent Forms validation</li><li>Run notifications and anti-spam</li><li>Normalize field errors</li></ul><div class="eh-info">Uploads, payments, and user registration are excluded from the V1 headless form contract.</div></section>
            <section class="eh-card"><div class="eh-section-heading"><div><h2>API endpoint</h2><p>Public forms use their provider-qualified ID.</p></div></div><code class="eh-code-block">POST <?php echo esc_html(rest_url('easyheadless/v1/forms/{provider_id}/submit')); ?></code></section>
        </div>
        <?php
        self::close_page();
    }

    public static function render_integrations()
    {
        self::guard();
        $updater = EasyHeadless_Updater::instance();
        $config = $updater->config();
        $status = $updater->public_status();
        self::open_page('Integrations & Updates', 'Configure cross-origin access, API credentials, and signed releases.', 'integrations');
        self::notice();
        ?>
        <div class="eh-two-column">
            <form class="eh-card eh-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php self::form_token('easyheadless_integrations'); ?>
                <div class="eh-section-heading"><div><h2>Signed updater</h2><p>Only signature-verified releases can be installed remotely.</p></div><span class="eh-badge <?php echo $status['configured'] ? 'eh-badge-success' : 'eh-badge-warning'; ?>"><?php echo esc_html(ucfirst($status['status'])); ?></span></div>
                <label><span>Release manifest URL</span><input type="url" name="updater[manifest_url]" value="<?php echo esc_attr($config['manifest_url']); ?>" <?php disabled(defined('EASYHEADLESS_UPDATE_MANIFEST_URL')); ?>></label>
                <label><span>Ed25519 public key</span><input type="text" name="updater[public_key]" value="<?php echo esc_attr($config['public_key']); ?>" autocomplete="off" <?php disabled(defined('EASYHEADLESS_UPDATE_PUBLIC_KEY')); ?>></label>
                <div class="eh-info"><strong><?php echo esc_html($status['message']); ?></strong><br>Installed version: <?php echo esc_html($status['currentVersion']); ?></div>
                <div class="eh-form-actions"><button class="button button-primary" type="submit">Save updater configuration</button></div>
            </form>
            <section class="eh-card"><div class="eh-section-heading"><div><h2>API credentials</h2><p>Credentials are intentionally separated from public content.</p></div></div><dl class="eh-definition-list"><div><dt>MCP key</dt><dd>Ending <?php echo esc_html(get_option(EasyHeadless_Plugin::OPTION_API_KEY_HINT, 'not generated')); ?></dd></div><div><dt>Preview token</dt><dd><code><?php echo esc_html(get_option(EasyHeadless_Plugin::OPTION_PREVIEW_TOKEN, '')); ?></code></dd></div><div><dt>REST base</dt><dd><code><?php echo esc_html(rest_url('easyheadless/v1')); ?></code></dd></div></dl></section>
        </div>
        <?php
        self::close_page();
    }

    public static function save_modules()
    {
        self::authorize();
        $modules = isset($_POST['modules']) ? wp_unslash($_POST['modules']) : array();
        update_option(EasyHeadless_Modules::OPTION_ENABLED_MODULES, EasyHeadless_Modules::sanitize_enabled_modules($modules));
        self::redirect(self::PAGE_PREFIX . 'modules', 'saved');
    }

    public static function save_site()
    {
        self::authorize();
        $profile = isset($_POST['profile']) ? wp_unslash($_POST['profile']) : array();
        $current = get_option(EasyHeadless_Modules::OPTION_SITE_PROFILE, array());
        $current = is_array($current) ? $current : array();
        $profile = is_array($profile) ? $profile : array();
        $profile['headless_routing'] = !empty($profile['headless_routing']);
        update_option(EasyHeadless_Modules::OPTION_SITE_PROFILE, EasyHeadless_Modules::sanitize_site_profile(array_merge($current, $profile)));
        $plugin = EasyHeadless_Plugin::instance();
        update_option(EasyHeadless_Plugin::OPTION_ALLOWED_ORIGINS, $plugin->sanitize_origins(isset($_POST['origins']) ? wp_unslash($_POST['origins']) : ''));
        self::redirect(self::PAGE_PREFIX . 'site', 'saved');
    }

    public static function save_portfolio()
    {
        self::authorize();
        $operation = isset($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : 'update';
        $saved = EasyHeadless_Modules::sanitize_portfolio(get_option(EasyHeadless_Modules::OPTION_PORTFOLIO, array()));

        if ('add' === $operation) {
            $ids = isset($_POST['attachment_ids']) ? preg_split('/[\s,]+/', sanitize_text_field(wp_unslash($_POST['attachment_ids']))) : array();
            $known = array_map(function ($item) { return absint($item['attachmentId']); }, $saved);
            foreach (array_values(array_unique(array_filter(array_map('absint', $ids)))) as $id) {
                if ('attachment' === get_post_type($id) && !in_array($id, $known, true)) {
                    $saved[] = array('attachmentId' => $id, 'title' => '', 'caption' => '', 'alt' => '', 'category' => 'all');
                    $known[] = $id;
                }
            }
        } elseif ('remove' === $operation) {
            $selected = isset($_POST['selected']) ? array_map('absint', (array) wp_unslash($_POST['selected'])) : array();
            $saved = array_values(array_filter($saved, function ($item) use ($selected) { return !in_array(absint($item['attachmentId']), $selected, true); }));
        } else {
            $item = isset($_POST['item']) ? EasyHeadless_Modules::sanitize_portfolio(array(wp_unslash($_POST['item']))) : array();
            if ($item) {
                foreach ($saved as $index => $existing) {
                    if (absint($existing['attachmentId']) === absint($item[0]['attachmentId'])) {
                        $saved[$index] = $item[0];
                        break;
                    }
                }
            }
        }

        update_option(EasyHeadless_Modules::OPTION_PORTFOLIO, $saved);
        self::redirect(self::PAGE_PREFIX . 'portfolio', 'saved');
    }

    public static function save_courses()
    {
        self::authorize();
        $ids = isset($_POST['courses']) ? wp_unslash($_POST['courses']) : array();
        update_option(EasyHeadless_Modules::OPTION_FEATURED_COURSES, EasyHeadless_Modules::sanitize_featured_courses($ids));
        self::redirect(self::PAGE_PREFIX . 'courses', 'saved');
    }

    public static function save_forms()
    {
        self::authorize();
        $ids = isset($_POST['forms']) ? wp_unslash($_POST['forms']) : array();
        update_option(EasyHeadless_Modules::OPTION_ALLOWED_FORMS, EasyHeadless_Modules::sanitize_allowed_forms($ids));
        self::redirect(self::PAGE_PREFIX . 'forms', 'saved');
    }

    public static function save_integrations()
    {
        self::authorize();
        $config = isset($_POST['updater']) ? wp_unslash($_POST['updater']) : array();
        $current = get_option(EasyHeadless_Updater::OPTION_CONFIG, array());
        update_option(EasyHeadless_Updater::OPTION_CONFIG, EasyHeadless_Updater::sanitize_config(array_merge(is_array($current) ? $current : array(), is_array($config) ? $config : array())));
        self::redirect(self::PAGE_PREFIX . 'integrations', 'saved');
    }

    private static function authorize()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage EasyHeadless.', 'easyheadless'), 403);
        }
        check_admin_referer(self::NONCE_ACTION);
    }

    private static function form_token($action)
    {
        wp_nonce_field(self::NONCE_ACTION);
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '">';
    }

    private static function redirect($page, $notice)
    {
        wp_safe_redirect(add_query_arg(array('page' => $page, 'eh_notice' => $notice), admin_url('admin.php')));
        exit;
    }

    private static function guard($capability = 'manage_options')
    {
        if (!current_user_can($capability)) {
            wp_die(esc_html__('You do not have permission to view this EasyHeadless screen.', 'easyheadless'), 403);
        }
    }

    private static function notice()
    {
        if (isset($_GET['eh_notice']) && 'saved' === sanitize_key(wp_unslash($_GET['eh_notice']))) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Changes saved.</strong></p></div>';
        }
    }

    private static function open_page($title, $subtitle, $active)
    {
        $profile = EasyHeadless_Modules::site_profile();
        ?>
        <div class="wrap eh-admin">
            <header class="eh-admin-header">
                <div class="eh-brand"><span class="dashicons dashicons-rest-api" aria-hidden="true"></span><strong>EasyHeadless</strong><small>v<?php echo esc_html(EASYHEADLESS_VERSION); ?></small></div>
                <div class="eh-environment"><span></span><?php echo wp_get_environment_type() === 'production' ? 'Production' : esc_html(ucfirst(wp_get_environment_type())); ?><?php if (!empty($profile['frontend_url'])) : ?><a href="<?php echo esc_url($profile['frontend_url']); ?>" target="_blank" rel="noopener noreferrer">View site <span class="dashicons dashicons-external" aria-hidden="true"></span></a><?php endif; ?></div>
            </header>
            <div class="eh-shell">
                <nav class="eh-side-nav" aria-label="EasyHeadless sections">
                    <?php foreach (self::admin_navigation() as $group => $items) : ?>
                        <div class="eh-nav-group">
                            <span class="eh-nav-label"><?php echo esc_html($group); ?></span>
                            <?php foreach ($items as $key => $item) : ?>
                                <a class="<?php echo $active === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . $item['slug'])); ?>"><span class="dashicons <?php echo esc_attr($item['icon']); ?>" aria-hidden="true"></span><span><?php echo esc_html($item['label']); ?></span></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </nav>
                <main class="eh-main"><div class="eh-page-title"><h1><?php echo esc_html($title); ?></h1><p><?php echo esc_html($subtitle); ?></p></div>
        <?php
    }

    private static function close_page()
    {
        echo '</main></div></div>';
    }

    private static function admin_navigation()
    {
        $navigation = array(
            'Overview' => array(
                'overview' => array('label' => 'Dashboard', 'slug' => EasyHeadless_Plugin::ADMIN_MENU_SLUG, 'icon' => 'dashicons-admin-home'),
            ),
            'Setup' => array(
                'site' => array('label' => 'Site & URLs', 'slug' => self::PAGE_PREFIX . 'site', 'icon' => 'dashicons-admin-site-alt3'),
                'navigation' => array('label' => 'Navigation', 'slug' => self::PAGE_PREFIX . 'navigation', 'icon' => 'dashicons-menu-alt3'),
            ),
            'Content' => array(
                'content' => array('label' => 'Content library', 'slug' => self::PAGE_PREFIX . 'content', 'icon' => 'dashicons-database-view'),
            ),
            'System' => array(
                'modules' => array('label' => 'Modules', 'slug' => self::PAGE_PREFIX . 'modules', 'icon' => 'dashicons-admin-plugins'),
                'integrations' => array('label' => 'Integrations & updates', 'slug' => self::PAGE_PREFIX . 'integrations', 'icon' => 'dashicons-update'),
            ),
        );

        if (EasyHeadless_Modules::is_enabled('portfolio')) {
            $navigation['Content']['portfolio'] = array('label' => 'Portfolio', 'slug' => self::PAGE_PREFIX . 'portfolio', 'icon' => 'dashicons-format-gallery');
        }
        if (EasyHeadless_Modules::is_enabled('tutor')) {
            $navigation['Content']['courses'] = array('label' => 'Courses', 'slug' => self::PAGE_PREFIX . 'courses', 'icon' => 'dashicons-welcome-learn-more');
        }
        if (EasyHeadless_Modules::is_enabled('forms')) {
            $navigation['Content']['forms'] = array('label' => 'Forms', 'slug' => self::PAGE_PREFIX . 'forms', 'icon' => 'dashicons-feedback');
        }

        return $navigation;
    }

    private static function content_types()
    {
        $types = array(
            'eh_service' => array('label' => 'Services', 'description' => 'Reusable service and capability pages.', 'icon' => 'dashicons-hammer'),
            'eh_testimonial' => array('label' => 'Testimonials', 'description' => 'Approved customer and partner feedback.', 'icon' => 'dashicons-format-quote'),
            'eh_faq' => array('label' => 'FAQs', 'description' => 'Frequently asked questions and answers.', 'icon' => 'dashicons-editor-help'),
            'eh_team_member' => array('label' => 'Team members', 'description' => 'People, roles, biographies, and portraits.', 'icon' => 'dashicons-groups'),
        );

        if (EasyHeadless_Modules::is_enabled('church')) {
            $types = array_merge($types, array(
                'eh_church_settings' => array('label' => 'Church settings', 'description' => 'Identity, contact, giving, and online meeting details.', 'icon' => 'dashicons-admin-home'),
                'eh_sermon' => array('label' => 'Sermons', 'description' => 'Messages, speakers, media, and scripture references.', 'icon' => 'dashicons-microphone'),
                'eh_event' => array('label' => 'Events', 'description' => 'Upcoming gatherings and event information.', 'icon' => 'dashicons-calendar-alt'),
                'eh_ministry' => array('label' => 'Ministries', 'description' => 'Ministry groups, focus areas, and contacts.', 'icon' => 'dashicons-heart'),
                'eh_leader' => array('label' => 'Leaders', 'description' => 'Church leadership profiles and roles.', 'icon' => 'dashicons-businessperson'),
                'eh_service_time' => array('label' => 'Service times', 'description' => 'Meeting schedules, locations, and online links.', 'icon' => 'dashicons-clock'),
                'eh_policy' => array('label' => 'Policies', 'description' => 'Safeguarding and other public policy documents.', 'icon' => 'dashicons-shield'),
            ));
        }

        return $types;
    }

    private static function post_type_count($post_type)
    {
        $counts = wp_count_posts($post_type);
        if (!$counts) {
            return 0;
        }

        return array_sum(array_map('intval', get_object_vars($counts)));
    }

    private static function healthy_module_message($key)
    {
        $messages = array(
            'core' => 'Core API and settings are available.',
            'portfolio' => 'Portfolio management is available.',
            'church' => 'Church content tools are available.',
            'tutor' => 'Tutor LMS is connected.',
            'forms' => 'Fluent Forms is connected.',
            'updater' => 'Trusted updates are configured.',
        );

        return isset($messages[$key]) ? $messages[$key] : 'Available.';
    }

    private static function module_label($key)
    {
        $labels = array('core' => 'Core', 'portfolio' => 'Portfolio', 'church' => 'Church', 'tutor' => 'Tutor LMS', 'forms' => 'Fluent Forms', 'updater' => 'Updates');
        return isset($labels[$key]) ? $labels[$key] : ucfirst($key);
    }
}
