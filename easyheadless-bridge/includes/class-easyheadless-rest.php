<?php

if (!defined('ABSPATH')) {
    exit;
}

final class EasyHeadless_REST
{
    const NAMESPACE = 'easyheadless/v1';

    private $forms;

    public function __construct()
    {
        $this->forms = new EasyHeadless_Forms();
    }

    public function register_routes()
    {
        register_rest_route(self::NAMESPACE, '/health', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'health'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE, '/site', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'site'),
            'permission_callback' => '__return_true',
        ));

        foreach (array(
            '/capabilities' => 'capabilities',
            '/navigation' => 'navigation',
            '/portfolio' => 'portfolio',
            '/courses' => 'courses',
        ) as $route => $handler) {
            register_rest_route(self::NAMESPACE, $route, array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, $handler),
                'permission_callback' => '__return_true',
            ));
        }

        register_rest_route(self::NAMESPACE, '/courses/(?P<slug>[a-zA-Z0-9-]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'course'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE, '/portfolio', array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => array($this, 'update_portfolio'),
            'permission_callback' => array($this, 'can_write'),
        ));

        register_rest_route(self::NAMESPACE, '/updater', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'updater_status'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE, '/updater/check', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'check_update'),
            'permission_callback' => array($this, 'can_write'),
        ));

        register_rest_route(self::NAMESPACE, '/updater/install', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'install_update'),
            'permission_callback' => array($this, 'can_write'),
        ));

        if (EasyHeadless_Modules::is_enabled('church')) {
            foreach ($this->church_read_routes() as $route => $handler) {
                register_rest_route(self::NAMESPACE, $route, array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, $handler),
                    'permission_callback' => '__return_true',
                ));
            }
        }

        register_rest_route(self::NAMESPACE, '/settings', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_settings'),
            'permission_callback' => array($this, 'can_write'),
        ));

        register_rest_route(self::NAMESPACE, '/modules', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_modules'),
            'permission_callback' => array($this, 'can_write'),
        ));

        register_rest_route(self::NAMESPACE, '/routes', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'routes'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE, '/page', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'page'),
            'permission_callback' => '__return_true',
            'args' => array(
                'slug' => array('type' => 'string', 'required' => true),
                'preview' => array('type' => 'boolean', 'required' => false),
                'previewToken' => array('type' => 'string', 'required' => false),
            ),
        ));

        register_rest_route(self::NAMESPACE, '/posts', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'posts'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE, '/posts', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'create_post'),
            'permission_callback' => array($this, 'can_write'),
        ));

        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => array($this, 'update_post'),
            'permission_callback' => array($this, 'can_write'),
        ));

        register_rest_route(self::NAMESPACE, '/pages/(?P<id>\d+)/acf', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_page_acf'),
            'permission_callback' => array($this, 'can_write'),
        ));

        register_rest_route(self::NAMESPACE, '/collections/(?P<type>[a-zA-Z_]+)', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'create_collection_item'),
            'permission_callback' => array($this, 'can_write'),
        ));

        register_rest_route(self::NAMESPACE, '/collections/(?P<type>[a-zA-Z_]+)/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => array($this, 'update_collection_item'),
            'permission_callback' => array($this, 'can_write'),
        ));

        if (EasyHeadless_Modules::is_enabled('forms')) {
            register_rest_route(self::NAMESPACE, '/forms', array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'forms'),
                'permission_callback' => '__return_true',
            ));

            register_rest_route(self::NAMESPACE, '/forms/(?P<id>[\w:-]+)', array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'form'),
                'permission_callback' => '__return_true',
            ));

            register_rest_route(self::NAMESPACE, '/forms/(?P<id>[\w:-]+)/submit', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'submit_form'),
                'permission_callback' => '__return_true',
            ));

            register_rest_route(self::NAMESPACE, '/forms/approved', array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_approved_forms'),
                'permission_callback' => array($this, 'can_write'),
            ));
        }
    }

    public function health()
    {
        return rest_ensure_response(array(
            'version' => EASYHEADLESS_VERSION,
            'plugins' => array(
                'acf' => EasyHeadless_ACF::is_available(),
                'wpGraphql' => class_exists('WPGraphQL'),
                'yoast' => EasyHeadless_SEO::is_yoast_available(),
                'forms' => $this->forms->health(),
            ),
            'modules' => EasyHeadless_Modules::capabilities($this->forms),
            'updater' => EasyHeadless_Updater::instance()->public_status(),
        ));
    }

    public function site()
    {
        $settings = EasyHeadless_Modules::site_profile();
        $church_enabled = EasyHeadless_Modules::is_enabled('church');
        $church = $church_enabled ? $this->church_data() : array();
        $capabilities = EasyHeadless_Modules::capabilities($this->forms);
        $collections = array(
            'services' => $this->collection('eh_service'),
            'testimonials' => $this->collection('eh_testimonial'),
            'faqs' => $this->collection('eh_faq'),
            'teamMembers' => $this->collection('eh_team_member'),
        );

        if ($church_enabled) {
            $collections = array_merge($collections, array(
                'churchSettings' => $this->collection('eh_church_settings'),
                'sermons' => $this->collection('eh_sermon'),
                'events' => $this->collection('eh_event'),
                'ministries' => $this->collection('eh_ministry'),
                'leaders' => $this->collection('eh_leader'),
                'serviceTimes' => $this->collection('eh_service_time'),
                'policies' => $this->collection('eh_policy'),
            ));
        }

        return rest_ensure_response(array(
            'name' => $settings['company_name'] ? $settings['company_name'] : get_bloginfo('name'),
            'description' => $settings['short_description'] ? $settings['short_description'] : get_bloginfo('description'),
            'url' => home_url('/'),
            'frontendUrl' => $settings['frontend_url'],
            'lmsUrl' => $settings['lms_url'] ? $settings['lms_url'] : home_url('/'),
            'portalLinks' => EasyHeadless_Modules::portal_links(),
            'settings' => $settings,
            'church' => $church,
            'contact' => array(
                'companyName' => isset($settings['company_name']) && $settings['company_name'] ? $settings['company_name'] : get_bloginfo('name'),
                'phone' => isset($settings['phone']) ? $settings['phone'] : '',
                'email' => isset($settings['email']) && $settings['email'] ? $settings['email'] : get_option('admin_email'),
                'address' => isset($settings['address']) ? $settings['address'] : '',
            ),
            'social' => isset($settings['social_links']) && is_array($settings['social_links']) ? $settings['social_links'] : array(),
            'collections' => $collections,
            'capabilities' => $capabilities,
            'health' => $this->health()->get_data(),
        ));
    }

    public function capabilities()
    {
        return rest_ensure_response(EasyHeadless_Modules::capabilities($this->forms));
    }

    public function navigation()
    {
        return rest_ensure_response(array('items' => EasyHeadless_Modules::navigation()));
    }

    public function portfolio()
    {
        return rest_ensure_response(array('items' => EasyHeadless_Modules::portfolio()));
    }

    public function update_portfolio(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        $items = isset($body['items']) ? EasyHeadless_Modules::sanitize_portfolio($body['items']) : array();
        update_option(EasyHeadless_Modules::OPTION_PORTFOLIO, $items);

        return rest_ensure_response(array(
            'success' => true,
            'items' => EasyHeadless_Modules::portfolio(),
        ));
    }

    public function updater_status()
    {
        return rest_ensure_response(EasyHeadless_Updater::instance()->public_status());
    }

    public function check_update()
    {
        $result = EasyHeadless_Updater::instance()->check_release(true);
        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(EasyHeadless_Updater::instance()->public_status($result));
    }

    public function install_update()
    {
        $result = EasyHeadless_Updater::instance()->install_release();
        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public function courses(WP_REST_Request $request)
    {
        $selected = $request->get_param('selected');
        if (is_string($selected)) {
            $selected = preg_split('/\s*,\s*/', $selected);
        }

        return rest_ensure_response(EasyHeadless_Modules::courses(array(
            'selected' => is_array($selected) ? $selected : null,
            'category' => $request->get_param('category'),
            'page' => $request->get_param('page'),
            'perPage' => $request->get_param('perPage'),
        )));
    }

    public function course(WP_REST_Request $request)
    {
        $course = EasyHeadless_Modules::course_by_slug($request->get_param('slug'));
        if (!$course) {
            return new WP_Error('course_not_found', 'Course not found.', array('status' => 404));
        }

        return rest_ensure_response($course);
    }

    public function church(WP_REST_Request $request)
    {
        return rest_ensure_response($this->church_data());
    }

    public function sermons(WP_REST_Request $request)
    {
        return $this->collection_response($request, 'eh_sermon', 'sermon_not_found');
    }

    public function events(WP_REST_Request $request)
    {
        return $this->collection_response($request, 'eh_event', 'event_not_found');
    }

    public function ministries(WP_REST_Request $request)
    {
        return $this->collection_response($request, 'eh_ministry', 'ministry_not_found');
    }

    public function leaders(WP_REST_Request $request)
    {
        return $this->collection_response($request, 'eh_leader', 'leader_not_found');
    }

    public function service_times(WP_REST_Request $request)
    {
        return $this->collection_response($request, 'eh_service_time', 'service_time_not_found');
    }

    public function policies(WP_REST_Request $request)
    {
        return $this->collection_response($request, 'eh_policy', 'policy_not_found');
    }

    public function update_settings(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        $settings = isset($body['settings']) && is_array($body['settings']) ? $body['settings'] : array();
        $current = get_option(EasyHeadless_Modules::OPTION_SITE_PROFILE, array());
        $updated = EasyHeadless_Modules::sanitize_site_profile(array_merge(
            is_array($current) ? $current : array(),
            $settings
        ));
        update_option(EasyHeadless_Modules::OPTION_SITE_PROFILE, $updated);

        return rest_ensure_response(array(
            'success' => true,
            'updated' => $updated,
            'site' => $this->site()->get_data(),
        ));
    }

    public function update_approved_forms(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        $ids = isset($body['ids']) ? EasyHeadless_Modules::sanitize_allowed_forms($body['ids']) : array();
        update_option(EasyHeadless_Modules::OPTION_ALLOWED_FORMS, $ids);

        return rest_ensure_response(array(
            'success' => true,
            'ids' => EasyHeadless_Modules::allowed_form_ids(),
            'forms' => $this->forms->list_forms(),
        ));
    }

    public function update_modules(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        $requested = isset($body['modules']) && is_array($body['modules']) ? $body['modules'] : array();
        $current = EasyHeadless_Modules::enabled_modules();
        $updated = EasyHeadless_Modules::sanitize_enabled_modules(array_merge($current, $requested));
        update_option(EasyHeadless_Modules::OPTION_ENABLED_MODULES, $updated);

        return rest_ensure_response(array(
            'success' => true,
            'modules' => $updated,
            'capabilities' => EasyHeadless_Modules::capabilities($this->forms),
        ));
    }

    public function routes()
    {
        $items = array();

        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ));

        foreach ($pages as $page) {
            $items[] = array(
                'type' => 'page',
                'slug' => $page->post_name,
                'path' => '/' . trim(get_page_uri($page), '/'),
                'title' => html_entity_decode(get_the_title($page), ENT_QUOTES),
                'modified' => get_post_modified_time(DATE_ATOM, true, $page),
            );
        }

        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
        ));

        foreach ($posts as $post) {
            $items[] = array(
                'type' => 'post',
                'slug' => $post->post_name,
                'path' => '/insights/' . $post->post_name,
                'title' => html_entity_decode(get_the_title($post), ENT_QUOTES),
                'modified' => get_post_modified_time(DATE_ATOM, true, $post),
            );
        }

        foreach ($this->church_route_types() as $post_type => $config) {
            $posts = get_posts(array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'numberposts' => -1,
            ));

            foreach ($posts as $post) {
                $items[] = array(
                    'type' => $post_type,
                    'slug' => $post->post_name,
                    'path' => $this->path_for_post($post),
                    'title' => html_entity_decode(get_the_title($post), ENT_QUOTES),
                    'modified' => get_post_modified_time(DATE_ATOM, true, $post),
                );
            }
        }

        return rest_ensure_response($items);
    }

    public function page(WP_REST_Request $request)
    {
        $slug = trim((string) $request->get_param('slug'), '/');
        $preview = (bool) $request->get_param('preview');
        $statuses = array('publish');

        if ($preview) {
            $token = (string) $request->get_param('previewToken');
            if (!$this->is_valid_preview_token($token)) {
                return new WP_Error('invalid_preview_token', 'Invalid preview token.', array('status' => 401));
            }
            $statuses = array('publish', 'draft', 'pending', 'private');
        }

        $page = $slug ? get_page_by_path($slug, OBJECT, 'page') : get_option('page_on_front');

        if (is_numeric($page)) {
            $page = get_post((int) $page);
        }

        if (!$page || !in_array($page->post_status, $statuses, true)) {
            return new WP_Error('page_not_found', 'Page not found.', array('status' => 404));
        }

        return rest_ensure_response($this->normalize_post($page));
    }

    public function posts(WP_REST_Request $request)
    {
        $slug = sanitize_title((string) $request->get_param('slug'));

        if ($slug) {
            $post = get_page_by_path($slug, OBJECT, 'post');

            if (!$post || 'publish' !== $post->post_status) {
                return new WP_Error('post_not_found', 'Post not found.', array('status' => 404));
            }

            return rest_ensure_response($this->normalize_post($post));
        }

        $page = max(1, absint($request->get_param('page')));
        $per_page = min(50, max(1, absint($request->get_param('perPage')) ?: 10));

        $query = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'paged' => $page,
            'posts_per_page' => $per_page,
        ));

        return rest_ensure_response(array(
            'items' => array_map(array($this, 'normalize_post'), $query->posts),
            'pagination' => array(
                'page' => $page,
                'perPage' => $per_page,
                'total' => (int) $query->found_posts,
                'totalPages' => (int) $query->max_num_pages,
            ),
        ));
    }

    public function create_post(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        $post_id = wp_insert_post(array(
            'post_type' => 'post',
            'post_status' => $this->sanitize_status(isset($body['status']) ? $body['status'] : 'draft'),
            'post_title' => sanitize_text_field(isset($body['title']) ? $body['title'] : ''),
            'post_content' => wp_kses_post(isset($body['content']) ? $body['content'] : ''),
            'post_excerpt' => sanitize_textarea_field(isset($body['excerpt']) ? $body['excerpt'] : ''),
        ), true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $this->update_acf_fields($post_id, isset($body['acf']) && is_array($body['acf']) ? $body['acf'] : array());

        return rest_ensure_response($this->normalize_post(get_post($post_id)));
    }

    public function update_post(WP_REST_Request $request)
    {
        $post_id = absint($request->get_param('id'));
        $post = get_post($post_id);

        if (!$post || 'post' !== $post->post_type) {
            return new WP_Error('post_not_found', 'Post not found.', array('status' => 404));
        }

        $body = $request->get_json_params();
        $update = array('ID' => $post_id);

        if (isset($body['title'])) {
            $update['post_title'] = sanitize_text_field($body['title']);
        }

        if (isset($body['content'])) {
            $update['post_content'] = wp_kses_post($body['content']);
        }

        if (isset($body['excerpt'])) {
            $update['post_excerpt'] = sanitize_textarea_field($body['excerpt']);
        }

        if (isset($body['status'])) {
            $update['post_status'] = $this->sanitize_status($body['status']);
        }

        $updated = wp_update_post($update, true);

        if (is_wp_error($updated)) {
            return $updated;
        }

        $this->update_acf_fields($post_id, isset($body['acf']) && is_array($body['acf']) ? $body['acf'] : array());

        return rest_ensure_response($this->normalize_post(get_post($post_id)));
    }

    public function update_page_acf(WP_REST_Request $request)
    {
        $page_id = absint($request->get_param('id'));
        $page = get_post($page_id);

        if (!$page || 'page' !== $page->post_type) {
            return new WP_Error('page_not_found', 'Page not found.', array('status' => 404));
        }

        $body = $request->get_json_params();
        $acf = isset($body['acf']) && is_array($body['acf']) ? $body['acf'] : array();

        if (isset($body['previewOnly']) && $body['previewOnly']) {
            return rest_ensure_response(array(
                'success' => true,
                'previewOnly' => true,
                'current' => EasyHeadless_ACF::get_fields($page_id),
                'proposed' => $acf,
            ));
        }

        $this->update_acf_fields($page_id, $acf, array('sections'));

        return rest_ensure_response($this->normalize_post(get_post($page_id)));
    }

    public function create_collection_item(WP_REST_Request $request)
    {
        $post_type = $this->collection_type_to_post_type($request->get_param('type'));

        if (!$post_type) {
            return new WP_Error('invalid_collection_type', 'Collection type is not supported.', array('status' => 400));
        }

        $body = $request->get_json_params();
        $post_id = wp_insert_post(array(
            'post_type' => $post_type,
            'post_status' => $this->sanitize_status(isset($body['status']) ? $body['status'] : 'publish'),
            'post_title' => sanitize_text_field(isset($body['title']) ? $body['title'] : ''),
            'post_content' => wp_kses_post(isset($body['content']) ? $body['content'] : ''),
            'post_excerpt' => sanitize_textarea_field(isset($body['excerpt']) ? $body['excerpt'] : ''),
        ), true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $this->update_acf_fields($post_id, isset($body['acf']) && is_array($body['acf']) ? $body['acf'] : array());

        return rest_ensure_response($this->normalize_post(get_post($post_id)));
    }

    public function update_collection_item(WP_REST_Request $request)
    {
        $post_type = $this->collection_type_to_post_type($request->get_param('type'));

        if (!$post_type) {
            return new WP_Error('invalid_collection_type', 'Collection type is not supported.', array('status' => 400));
        }

        $post_id = absint($request->get_param('id'));
        $post = get_post($post_id);

        if (!$post || $post_type !== $post->post_type) {
            return new WP_Error('collection_item_not_found', 'Collection item not found.', array('status' => 404));
        }

        $body = $request->get_json_params();
        $update = array('ID' => $post_id);

        if (isset($body['title'])) {
            $update['post_title'] = sanitize_text_field($body['title']);
        }

        if (isset($body['content'])) {
            $update['post_content'] = wp_kses_post($body['content']);
        }

        if (isset($body['excerpt'])) {
            $update['post_excerpt'] = sanitize_textarea_field($body['excerpt']);
        }

        if (isset($body['status'])) {
            $update['post_status'] = $this->sanitize_status($body['status']);
        }

        $updated = wp_update_post($update, true);

        if (is_wp_error($updated)) {
            return $updated;
        }

        $this->update_acf_fields($post_id, isset($body['acf']) && is_array($body['acf']) ? $body['acf'] : array());

        return rest_ensure_response($this->normalize_post(get_post($post_id)));
    }

    public function forms()
    {
        return rest_ensure_response($this->forms->list_forms());
    }

    public function form(WP_REST_Request $request)
    {
        $form = $this->forms->get_form($request->get_param('id'));

        if (is_wp_error($form)) {
            return $form;
        }

        return rest_ensure_response($form);
    }

    public function submit_form(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        $fields = isset($body['fields']) && is_array($body['fields']) ? $body['fields'] : array();
        $submitted = $this->forms->submit($request->get_param('id'), $fields);

        if (is_wp_error($submitted)) {
            return $submitted;
        }

        return rest_ensure_response($submitted);
    }

    public function can_write(WP_REST_Request $request)
    {
        $header = $request->get_header('authorization');

        if (!$header || !preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return new WP_Error('easyheadless_auth_required', 'EasyHeadless API key is required.', array('status' => 401));
        }

        if (!EasyHeadless_Plugin::verify_api_key(trim($matches[1]))) {
            return new WP_Error('easyheadless_auth_invalid', 'EasyHeadless API key is invalid.', array('status' => 403));
        }

        return true;
    }

    private function normalize_post($post)
    {
        $featured_id = get_post_thumbnail_id($post);
        $acf = EasyHeadless_ACF::get_fields($post->ID);
        $acf = $this->normalize_acf_fields(is_array($acf) ? $acf : array());

        return array(
            'id' => $post->ID,
            'slug' => $post->post_name,
            'path' => $this->path_for_post($post),
            'type' => $post->post_type,
            'title' => html_entity_decode(get_the_title($post), ENT_QUOTES),
            'content' => apply_filters('the_content', $post->post_content),
            'excerpt' => wp_strip_all_tags(get_the_excerpt($post)),
            'featuredImage' => $featured_id ? array(
                'id' => $featured_id,
                'url' => wp_get_attachment_image_url($featured_id, 'full'),
                'alt' => get_post_meta($featured_id, '_wp_attachment_image_alt', true),
            ) : null,
            'acf' => $acf,
            'seo' => EasyHeadless_SEO::for_post($post->ID),
            'modified' => get_post_modified_time(DATE_ATOM, true, $post),
        );
    }

    private function collection($post_type)
    {
        $items = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ));

        return array_map(array($this, 'normalize_post'), $items);
    }

    private function collection_response(WP_REST_Request $request, $post_type, $not_found_code)
    {
        $slug = sanitize_title((string) $request->get_param('slug'));

        if ($slug) {
            $post = get_page_by_path($slug, OBJECT, $post_type);

            if (!$post || 'publish' !== $post->post_status) {
                return new WP_Error($not_found_code, 'Item not found.', array('status' => 404));
            }

            return rest_ensure_response($this->normalize_post($post));
        }

        return rest_ensure_response($this->collection($post_type));
    }

    private function church_data()
    {
        $settings = EasyHeadless_ACF::get_option_fields();
        $settings_post = $this->first_published_post('eh_church_settings');
        $church_fields = $settings_post ? EasyHeadless_ACF::get_fields($settings_post->ID) : array();

        if (!is_array($church_fields)) {
            $church_fields = array();
        }

        $church_fields = $this->normalize_acf_fields($church_fields);
        $merged = array_merge($settings, $church_fields);

        if (empty($merged['church_name'])) {
            $merged['church_name'] = isset($settings['company_name']) && $settings['company_name'] ? $settings['company_name'] : get_bloginfo('name');
        }

        if (empty($merged['email'])) {
            $merged['email'] = isset($settings['email']) && $settings['email'] ? $settings['email'] : get_option('admin_email');
        }

        $merged['entry'] = $settings_post ? $this->normalize_post($settings_post) : null;
        $merged['serviceTimes'] = $this->collection('eh_service_time');
        $merged['leaders'] = $this->collection('eh_leader');
        $merged['policies'] = $this->collection('eh_policy');

        return $merged;
    }

    private function first_published_post($post_type)
    {
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => 1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ));

        return count($posts) ? $posts[0] : null;
    }

    private function church_read_routes()
    {
        return array(
            '/church' => 'church',
            '/sermons' => 'sermons',
            '/events' => 'events',
            '/ministries' => 'ministries',
            '/leaders' => 'leaders',
            '/service-times' => 'service_times',
            '/policies' => 'policies',
        );
    }

    private function church_route_types()
    {
        return array(
            'eh_sermon' => 'sermons',
            'eh_event' => 'events',
            'eh_ministry' => 'ministries',
            'eh_leader' => 'leaders',
            'eh_policy' => 'policies',
        );
    }

    private function path_for_post($post)
    {
        if ('page' === $post->post_type) {
            return '/' . trim(get_page_uri($post), '/');
        }

        if ('post' === $post->post_type) {
            return '/blog/' . $post->post_name;
        }

        $church_routes = $this->church_route_types();
        if (isset($church_routes[$post->post_type])) {
            if ('eh_policy' === $post->post_type && 'safeguarding' === $post->post_name) {
                return '/safeguarding';
            }

            return '/' . $church_routes[$post->post_type] . '/' . $post->post_name;
        }

        return '/' . $post->post_name;
    }

    private function collection_type_to_post_type($type)
    {
        $map = array(
            'services' => 'eh_service',
            'testimonials' => 'eh_testimonial',
            'faqs' => 'eh_faq',
            'teamMembers' => 'eh_team_member',
            'team_members' => 'eh_team_member',
            'churchSettings' => 'eh_church_settings',
            'church_settings' => 'eh_church_settings',
            'sermons' => 'eh_sermon',
            'events' => 'eh_event',
            'ministries' => 'eh_ministry',
            'leaders' => 'eh_leader',
            'serviceTimes' => 'eh_service_time',
            'service_times' => 'eh_service_time',
            'policies' => 'eh_policy',
        );

        return isset($map[$type]) ? $map[$type] : null;
    }

    private function normalize_acf_fields($fields)
    {
        if (!is_array($fields)) {
            return array();
        }

        $media_fields = array(
            'image_url',
            'thumbnail_url',
            'primary_pastor_image_url',
            'audio_url',
            'video_url',
            'download_url',
        );

        foreach ($fields as $key => $value) {
            if (is_array($value) && in_array($key, $media_fields, true) && $this->is_media_value($value)) {
                $media = $this->normalize_media_value($value);
                $fields[$key] = isset($media['url']) ? $media['url'] : '';
                $fields[$key . '_media'] = $media;
                continue;
            }

            if (is_array($value)) {
                $fields[$key] = $this->normalize_nested_acf_value($value);
            }
        }

        if (empty($fields['download_url']) && !empty($fields['audio_url'])) {
            $fields['download_url'] = $fields['audio_url'];
            if (!empty($fields['audio_url_media'])) {
                $fields['download_url_media'] = $fields['audio_url_media'];
            }
        }

        if (empty($fields['file_size'])) {
            if (!empty($fields['download_url_media']['file_size_display'])) {
                $fields['file_size'] = $fields['download_url_media']['file_size_display'];
            } elseif (!empty($fields['audio_url_media']['file_size_display'])) {
                $fields['file_size'] = $fields['audio_url_media']['file_size_display'];
            }
        }

        return $fields;
    }

    private function normalize_nested_acf_value($value)
    {
        if ($this->is_media_value($value)) {
            return $this->normalize_media_value($value);
        }

        $normalized = array();
        foreach ($value as $key => $item) {
            $normalized[$key] = is_array($item) ? $this->normalize_nested_acf_value($item) : $item;
        }

        return $normalized;
    }

    private function is_media_value($value)
    {
        return is_array($value) && (isset($value['url']) || isset($value['ID']) || isset($value['id'])) && (isset($value['mime_type']) || isset($value['filename']) || isset($value['sizes']));
    }

    private function normalize_media_value($value)
    {
        $id = isset($value['ID']) ? absint($value['ID']) : (isset($value['id']) ? absint($value['id']) : 0);
        $url = isset($value['url']) ? esc_url_raw($value['url']) : ($id ? wp_get_attachment_url($id) : '');
        $file_size = isset($value['filesize']) ? absint($value['filesize']) : 0;

        if (!$file_size && $id) {
            $file = get_attached_file($id);
            if ($file && file_exists($file)) {
                $file_size = filesize($file);
            }
        }

        return array(
            'id' => $id,
            'url' => $url,
            'filename' => isset($value['filename']) ? sanitize_file_name($value['filename']) : ($id ? basename(get_attached_file($id)) : ''),
            'title' => isset($value['title']) ? sanitize_text_field($value['title']) : ($id ? get_the_title($id) : ''),
            'alt' => isset($value['alt']) ? sanitize_text_field($value['alt']) : ($id ? get_post_meta($id, '_wp_attachment_image_alt', true) : ''),
            'mime_type' => isset($value['mime_type']) ? sanitize_text_field($value['mime_type']) : ($id ? get_post_mime_type($id) : ''),
            'file_size' => $file_size,
            'file_size_display' => $file_size ? size_format($file_size, 2) : (isset($value['filesize_pretty']) ? sanitize_text_field($value['filesize_pretty']) : ''),
            'width' => isset($value['width']) ? absint($value['width']) : 0,
            'height' => isset($value['height']) ? absint($value['height']) : 0,
            'sizes' => isset($value['sizes']) && is_array($value['sizes']) ? $value['sizes'] : array(),
        );
    }

    private function sanitize_status($status)
    {
        $status = sanitize_key((string) $status);
        $allowed = array('draft', 'pending', 'publish', 'private');

        return in_array($status, $allowed, true) ? $status : 'draft';
    }

    private function update_acf_fields($post_id, $fields, $allowed = null)
    {
        if (!$fields) {
            return;
        }

        if (!function_exists('update_field')) {
            return;
        }

        foreach ($fields as $key => $value) {
            if ($allowed && !in_array($key, $allowed, true)) {
                continue;
            }

            update_field($key, $this->sanitize_acf_value($value), $post_id);
        }
    }

    private function sanitize_acf_value($value)
    {
        if (is_array($value)) {
            $sanitized = array();

            foreach ($value as $key => $item) {
                $safe_key = is_string($key) ? sanitize_key($key) : $key;
                $sanitized[$safe_key] = $this->sanitize_acf_value($item);
            }

            return $sanitized;
        }

        if (is_bool($value) || is_numeric($value) || null === $value) {
            return $value;
        }

        return wp_kses_post((string) $value);
    }

    private function is_valid_preview_token($token)
    {
        $expected = (string) get_option(EasyHeadless_Plugin::OPTION_PREVIEW_TOKEN, '');
        return $expected && hash_equals($expected, (string) $token);
    }
}
