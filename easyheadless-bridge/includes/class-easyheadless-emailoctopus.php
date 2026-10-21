<?php
if (!defined('ABSPATH')) { exit; }

/** Opt-in newsletter adapter. No credentials or contact data are exposed through REST. */
final class EasyHeadless_EmailOctopus
{
    const OPTION = 'easyheadless_emailoctopus';
    const JOB_PREFIX = 'easyheadless_eo_job_';
    const CRON = 'easyheadless_emailoctopus_retry';

    public static function register_hooks()
    {
        add_action('easyheadless_publication_newsletter_subscribed', array(__CLASS__, 'enqueue'));
        add_action('easyheadless_publication_newsletter_unsubscribed', array(__CLASS__, 'enqueue'));
        add_action(self::CRON, array(__CLASS__, 'sync'));
        add_action('admin_menu', array(__CLASS__, 'menu'), 40);
        add_action('admin_post_easyheadless_emailoctopus', array(__CLASS__, 'save'));
    }

    public static function settings()
    {
        $settings = get_option(self::OPTION, array());
        return array_merge(array('enabled' => false, 'api_key' => '', 'list_id' => ''), is_array($settings) ? $settings : array());
    }

    private static function key()
    {
        return defined('EASYHEADLESS_EMAILOCTOPUS_API_KEY') ? EASYHEADLESS_EMAILOCTOPUS_API_KEY : self::settings()['api_key'];
    }

    /** Fixed host, no redirects, generic errors: never echo provider bodies or credentials. */
    public static function request($method, $path, $body = null)
    {
        if (!self::key()) { return new WP_Error('eo_config', 'Configure an EmailOctopus API key first.'); }
        $args = array('method' => $method, 'timeout' => 8, 'redirection' => 0, 'headers' => array('Authorization' => 'Bearer ' . self::key(), 'Accept' => 'application/json', 'Content-Type' => 'application/json'));
        if (null !== $body) { $args['body'] = wp_json_encode($body); }
        $response = wp_remote_request('https://api.emailoctopus.com' . $path, $args);
        if (is_wp_error($response)) { return new WP_Error('eo_transport', 'EmailOctopus is unreachable.'); }
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('eo_http', 'EmailOctopus returned HTTP ' . $code . '.', array('status' => $code));
        }
        if (!is_array($data)) { return new WP_Error('eo_response', 'EmailOctopus returned an unexpected response.'); }
        return $data;
    }

    public static function enqueue($email)
    {
        $settings = self::settings();
        $email = strtolower(sanitize_email($email));
        if (!$settings['enabled'] || !is_email($email)) { return; }
        $id = self::JOB_PREFIX . hash('sha256', $settings['list_id'] . ':' . $email);
        // Store before calling the provider, so a timeout cannot lose the subscription.
        update_option($id, array('email' => $email, 'list_id' => $settings['list_id'], 'attempts' => 0, 'state' => 'pending', 'message' => ''), false);
        if (!wp_next_scheduled(self::CRON, array($id))) { wp_schedule_single_event(time() + 60, self::CRON, array($id)); }
        self::sync($id);
    }

    public static function sync($id)
    {
        global $wpdb;
        if (0 !== strpos($id, self::JOB_PREFIX)) { return; }
        $job = get_option($id);
        $settings = self::settings();
        if (!is_array($job) || !$settings['enabled']) { return; }
        // Re-read consent on every attempt: stale subscribe jobs must not undo opt-outs.
        $contact = $wpdb->get_row($wpdb->prepare("SELECT newsletter_consent FROM {$wpdb->prefix}eh_publication_contacts WHERE email = %s", $job['email']));
        if (!$contact || $job['list_id'] !== $settings['list_id']) {
            delete_option($id);
            wp_clear_scheduled_hook(self::CRON, array($id));
            return;
        }
        if (!$settings['list_id']) {
            $result = new WP_Error('eo_config', 'Select a destination list.');
        } elseif ($contact->newsletter_consent) {
            $list = self::request('GET', '/lists/' . rawurlencode($settings['list_id']));
            if (is_wp_error($list)) { $result = $list; }
            elseif (!array_key_exists('double_opt_in', $list) || !is_bool($list['double_opt_in'])) { $result = new WP_Error('eo_response', 'The list opt-in policy could not be verified.'); }
            else {
                // Create only. A duplicate never changes an existing contact's status.
                $result = self::request('POST', '/lists/' . rawurlencode($settings['list_id']) . '/contacts', array('email_address' => $job['email'], 'status' => $list['double_opt_in'] ? 'pending' : 'subscribed'));
                if (is_wp_error($result) && 409 === (int) ($result->get_error_data()['status'] ?? 0)) { $result = array('status' => 'existing_contact_preserved'); }
            }
        } else {
            $result = self::request('PUT', '/lists/' . rawurlencode($settings['list_id']) . '/contacts', array('email_address' => $job['email'], 'status' => 'unsubscribed'));
        }
        $job['attempts']++;
        if (!is_wp_error($result)) {
            delete_option($id);
            wp_clear_scheduled_hook(self::CRON, array($id));
            update_option('easyheadless_eo_last_result', 'Last sync succeeded at ' . gmdate('Y-m-d H:i:s') . ' UTC. Existing contacts retain their provider subscription status.', false);
            return;
        }
        $code = (int) ($result->get_error_data()['status'] ?? 0);
        $retryable = !$code || 429 === $code || $code >= 500;
        $job['state'] = $retryable && $job['attempts'] < 8 ? 'pending' : 'failed';
        $job['message'] = $result->get_error_message();
        update_option($id, $job, false);
        wp_clear_scheduled_hook(self::CRON, array($id));
        if ('pending' === $job['state']) { wp_schedule_single_event(time() + min(21600, 60 * pow(2, $job['attempts'])), self::CRON, array($id)); }
        update_option('easyheadless_eo_last_result', $job['message'] . ('pending' === $job['state'] ? ' Saved locally; retry scheduled.' : ' Saved locally; correct settings and use Retry saved jobs.'), false);
    }

    public static function menu()
    {
        if (EasyHeadless_Publication::enabled()) {
            add_submenu_page('easyheadless', 'EmailOctopus', 'EmailOctopus', 'manage_options', 'easyheadless-emailoctopus', array(__CLASS__, 'render'));
        }
    }

    public static function save()
    {
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        check_admin_referer('easyheadless_emailoctopus');
        $settings = self::settings();
        $operation = isset($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : 'save';
        $message = 'Settings saved.';
        if ('save' === $operation) {
            $settings['enabled'] = !empty($_POST['enabled']);
            $settings['list_id'] = sanitize_text_field(wp_unslash($_POST['list_id'] ?? ''));
            $key = trim(wp_unslash($_POST['api_key'] ?? ''));
            if ($key && preg_match('/^eo_[a-zA-Z0-9]+$/', $key)) { $settings['api_key'] = $key; }
            elseif ($key) { $message = 'API key was not changed: expected an EmailOctopus v2 key starting eo_.'; }
            if (!empty($_POST['clear_key'])) { $settings['api_key'] = ''; }
            update_option(self::OPTION, $settings, false);
        } elseif ('lists' === $operation) {
            $result = self::request('GET', '/lists?limit=100');
            if (is_wp_error($result)) { $message = $result->get_error_message(); }
            elseif (!isset($result['data']) || !is_array($result['data'])) { $message = 'Unexpected list response.'; }
            else {
                $lists = array();
                foreach ($result['data'] as $list) { if (!empty($list['id']) && isset($list['name'])) { $lists[] = array('id' => sanitize_text_field($list['id']), 'name' => sanitize_text_field($list['name'])); } }
                update_option('easyheadless_eo_lists', $lists, false);
                $message = 'Fetched up to 100 lists. Paste a list ID manually if yours is not shown.';
            }
        } elseif ('create' === $operation) {
            $name = sanitize_text_field(wp_unslash($_POST['list_name'] ?? ''));
            $result = $name ? self::request('POST', '/lists', array('name' => $name)) : new WP_Error('eo_name', 'Enter a list name.');
            if (is_wp_error($result)) { $message = $result->get_error_message() . ' Check EmailOctopus before retrying creation to avoid duplicates.'; }
            elseif (empty($result['id'])) { $message = 'List creation was not confirmed. Check EmailOctopus before retrying.'; }
            else {
                $settings['list_id'] = sanitize_text_field($result['id']);
                update_option(self::OPTION, $settings, false);
                $message = 'List created and selected. Check its double opt-in settings in EmailOctopus before enabling sync.';
            }
        } elseif ('retry' === $operation) {
            global $wpdb;
            $ids = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 100", $wpdb->esc_like(self::JOB_PREFIX) . '%'));
            foreach ($ids as $id) {
                $job = get_option($id);
                if (!is_array($job)) { continue; }
                $job['attempts'] = 0;
                $job['state'] = 'pending';
                update_option($id, $job, false);
                if (!wp_next_scheduled(self::CRON, array($id))) { wp_schedule_single_event(time() + 1, self::CRON, array($id)); }
            }
            $message = 'Up to 100 saved jobs queued for WP-Cron. Enable sync and ensure WordPress cron is running.';
        }
        set_transient('easyheadless_eo_notice_' . get_current_user_id(), $message, 60);
        wp_safe_redirect(admin_url('admin.php?page=easyheadless-emailoctopus'));
        exit;
    }

    public static function render()
    {
        if (!current_user_can('manage_options')) { return; }
        $settings = self::settings();
        $notice = get_transient('easyheadless_eo_notice_' . get_current_user_id());
        delete_transient('easyheadless_eo_notice_' . get_current_user_id());
        echo '<div class="wrap"><h1>EmailOctopus newsletter</h1><p>WordPress keeps the contact and consent. Only explicit newsletter opt-in is synced; signing in never subscribes a reader.</p>';
        if ($notice) { echo '<div class="notice notice-info"><p>' . esc_html($notice) . '</p></div>'; }
        echo '<p>' . esc_html(get_option('easyheadless_eo_last_result', 'No sync attempted yet.')) . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('easyheadless_emailoctopus');
        echo '<input type="hidden" name="action" value="easyheadless_emailoctopus"><table class="form-table"><tr><th>Sync enabled</th><td><label><input type="checkbox" name="enabled" value="1" ' . checked($settings['enabled'], true, false) . '> Send newsletter consent changes to EmailOctopus</label></td></tr>';
        echo '<tr><th><label for="eo-key">API v2 key</label></th><td><input id="eo-key" name="api_key" type="password" autocomplete="new-password" value="" class="regular-text"><p>Leave blank to keep the saved key. ' . (self::key() ? 'A key is configured.' : 'No key configured.') . ' You may instead define EASYHEADLESS_EMAILOCTOPUS_API_KEY in wp-config.php.</p><label><input type="checkbox" name="clear_key"> Remove stored key (does not remove a wp-config.php constant)</label></td></tr>';
        echo '<tr><th><label for="eo-list">Destination list ID</label></th><td><input id="eo-list" name="list_id" class="regular-text" value="' . esc_attr($settings['list_id']) . '" list="eo-known-lists"><datalist id="eo-known-lists">';
        foreach (get_option('easyheadless_eo_lists', array()) as $list) { echo '<option value="' . esc_attr($list['id']) . '">' . esc_html($list['name']) . '</option>'; }
        echo '</datalist><p>Save key and list before enabling. Fetch lists to populate suggestions, or paste a list ID.</p></td></tr></table><button class="button button-primary" name="operation" value="save">Save settings</button> <button class="button" name="operation" value="lists">Fetch lists using saved key</button> <button class="button" name="operation" value="retry">Retry saved jobs</button><hr><label for="eo-name">New list name</label> <input id="eo-name" name="list_name" value="Film Efiko Newsletter"> <button class="button" name="operation" value="create">Create and select list</button><p>Creation uses the saved key. It is never retried automatically. Check existing lists first. Existing contacts are not re-subscribed by this adapter. New contacts follow the destination list’s double opt-in policy. Jobs retry up to eight times; permanent failures remain saved for manual retry. WP-Cron must run.</p></form></div>';
    }
}
