<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Authenticated EasyHeadless-only release updater.
 *
 * Trust configuration is deliberately separate from content settings and the
 * REST API. Package replacement is delegated to WordPress core so its backup,
 * rollback, maintenance-mode, and reactivation safeguards remain in control.
 */
final class EasyHeadless_Updater
{
    const OPTION_CONFIG = 'easyheadless_updater_config';
    const OPTION_STATUS = 'easyheadless_updater_status';
    const OPTION_LOCK = 'easyheadless_updater_lock';
    const TRANSIENT_MANIFEST = 'easyheadless_updater_manifest';
    const PLUGIN_SLUG = 'easyheadless-bridge';
    const MANIFEST_SCHEMA = 1;
    const MINIMUM_SAFE_WORDPRESS = '6.3';
    const LOCK_TTL = 900;

    private static $instance = null;
    private $registered = false;
    private $verified_manifest = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function register_hooks()
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;
        add_filter('pre_set_site_transient_update_plugins', array($this, 'inject_update'));
        add_filter('plugins_api', array($this, 'plugin_information'), 20, 3);
        add_filter('upgrader_pre_download', array($this, 'verify_download'), 10, 4);
    }

    public static function sanitize_config($value)
    {
        $value = is_array($value) ? $value : array();
        $url = isset($value['manifest_url']) ? trim((string) $value['manifest_url']) : '';
        $key = isset($value['public_key']) ? trim((string) $value['public_key']) : '';

        return array(
            'manifest_url' => self::is_https_url($url) ? esc_url_raw($url) : '',
            'public_key' => self::valid_public_key($key) ? $key : '',
        );
    }

    public function config()
    {
        $saved = get_option(self::OPTION_CONFIG, array());
        $saved = is_array($saved) ? $saved : array();
        $url = defined('EASYHEADLESS_UPDATE_MANIFEST_URL')
            ? (string) EASYHEADLESS_UPDATE_MANIFEST_URL
            : (isset($saved['manifest_url']) ? (string) $saved['manifest_url'] : '');
        $key = defined('EASYHEADLESS_UPDATE_PUBLIC_KEY')
            ? (string) EASYHEADLESS_UPDATE_PUBLIC_KEY
            : (isset($saved['public_key']) ? (string) $saved['public_key'] : '');

        return array(
            'manifest_url' => self::is_https_url($url) ? $url : '',
            'public_key' => self::valid_public_key($key) ? $key : '',
        );
    }

    public function capability_status()
    {
        $status = $this->public_status();

        return array(
            'enabled' => true,
            'available' => 'ready' === $status['status'],
            'status' => 'ready' === $status['status'] ? 'ready' : 'degraded',
            'message' => $status['message'],
        );
    }

    public function public_status($manifest = null)
    {
        $config = $this->config();
        $reasons = $this->environment_reasons($config);
        if (null === $manifest && !$reasons) {
            $manifest = get_transient(self::TRANSIENT_MANIFEST);
        }
        $manifest = is_array($manifest) ? $manifest : array();
        $latest = isset($manifest['version']) ? (string) $manifest['version'] : '';
        $last = get_option(self::OPTION_STATUS, array());
        $last = is_array($last) ? $last : array();
        $configured = (bool) ($config['manifest_url'] && $config['public_key']);
        $ready = !$reasons;
        $message = $ready
            ? 'Signed EasyHeadless updates are available for explicit installation.'
            : implode(' ', $reasons);

        return array(
            'enabled' => true,
            'configured' => $configured,
            'available' => $ready,
            'status' => $ready ? 'ready' : 'degraded',
            'message' => $message,
            'currentVersion' => EASYHEADLESS_VERSION,
            'latestVersion' => $latest,
            'updateAvailable' => $latest ? version_compare($latest, EASYHEADLESS_VERSION, '>') : false,
            'lastAttempt' => array(
                'timestamp' => isset($last['timestamp']) ? (string) $last['timestamp'] : '',
                'requestedVersion' => isset($last['requestedVersion']) ? (string) $last['requestedVersion'] : '',
                'installedVersion' => isset($last['installedVersion']) ? (string) $last['installedVersion'] : '',
                'success' => isset($last['success']) ? (bool) $last['success'] : null,
                'message' => isset($last['message']) ? (string) $last['message'] : '',
            ),
        );
    }

    public function check_release($force = false)
    {
        $config = $this->config();
        $reasons = $this->environment_reasons($config, false);
        if ($reasons) {
            return new WP_Error('easyheadless_update_unavailable', implode(' ', $reasons), array('status' => 409));
        }

        if (!$force) {
            $cached = get_transient(self::TRANSIENT_MANIFEST);
            if (is_array($cached)) {
                $this->verified_manifest = $cached;
                return $cached;
            }
        }

        $response = wp_safe_remote_get($config['manifest_url'], array(
            'timeout' => 15,
            'redirection' => 2,
            'limit_response_size' => 131072,
            'headers' => array('Accept' => 'application/json'),
            'user-agent' => 'EasyHeadless/' . EASYHEADLESS_VERSION . '; ' . home_url('/'),
        ));
        if (is_wp_error($response)) {
            return $this->record_error('easyheadless_manifest_request_failed', 'The signed release manifest could not be downloaded.', '', $response);
        }

        if (200 !== (int) wp_remote_retrieve_response_code($response)) {
            return $this->record_error('easyheadless_manifest_http_error', 'The signed release manifest returned an unexpected HTTP status.', '');
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $manifest = self::validate_manifest($data, $config['public_key']);
        if (is_wp_error($manifest)) {
            return $this->record_error($manifest->get_error_code(), $manifest->get_error_message(), '', $manifest);
        }

        set_transient(self::TRANSIENT_MANIFEST, $manifest, 15 * MINUTE_IN_SECONDS);
        $this->verified_manifest = $manifest;

        return $manifest;
    }

    public static function validate_manifest($data, $public_key)
    {
        if (!is_array($data)) {
            return new WP_Error('easyheadless_manifest_invalid', 'The release manifest is not valid JSON.');
        }

        $required = array('schemaVersion', 'slug', 'version', 'packageUrl', 'sha256', 'signature', 'requiresWordPress', 'requiresPhp', 'releasedAt');
        foreach ($required as $field) {
            if (!isset($data[$field]) || '' === trim((string) $data[$field])) {
                return new WP_Error('easyheadless_manifest_invalid', 'The release manifest is missing a required field.');
            }
            if (false !== strpos((string) $data[$field], "\n") || false !== strpos((string) $data[$field], "\r")) {
                return new WP_Error('easyheadless_manifest_invalid', 'The release manifest contains an invalid field value.');
            }
        }

        if (self::MANIFEST_SCHEMA !== (int) $data['schemaVersion'] || self::PLUGIN_SLUG !== (string) $data['slug']) {
            return new WP_Error('easyheadless_manifest_wrong_plugin', 'The release manifest does not describe EasyHeadless.');
        }
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', (string) $data['version'])) {
            return new WP_Error('easyheadless_manifest_invalid_version', 'The release version is invalid.');
        }
        if (!preg_match('/^\d+\.\d+(?:\.\d+)?$/', (string) $data['requiresWordPress']) || !preg_match('/^\d+\.\d+(?:\.\d+)?$/', (string) $data['requiresPhp'])) {
            return new WP_Error('easyheadless_manifest_invalid_requirements', 'The release compatibility requirements are invalid.');
        }
        $released = strtotime((string) $data['releasedAt']);
        if (false === $released || gmdate('Y-m-d\TH:i:s\Z', $released) !== (string) $data['releasedAt']) {
            return new WP_Error('easyheadless_manifest_invalid_timestamp', 'The release timestamp must be canonical UTC.');
        }
        if (!self::is_https_url($data['packageUrl']) || !preg_match('/^[a-f0-9]{64}$/i', (string) $data['sha256'])) {
            return new WP_Error('easyheadless_manifest_invalid_package', 'The release package metadata is invalid.');
        }
        if (!self::valid_public_key($public_key) || !function_exists('sodium_crypto_sign_verify_detached')) {
            return new WP_Error('easyheadless_signature_unavailable', 'Ed25519 signature verification is unavailable.');
        }

        $signature = base64_decode((string) $data['signature'], true);
        $key = base64_decode((string) $public_key, true);
        if (false === $signature || SODIUM_CRYPTO_SIGN_BYTES !== strlen($signature)) {
            return new WP_Error('easyheadless_manifest_invalid_signature', 'The release signature is malformed.');
        }
        if (!sodium_crypto_sign_verify_detached($signature, self::canonical_payload($data), $key)) {
            return new WP_Error('easyheadless_manifest_untrusted', 'The release manifest signature is not trusted.');
        }

        return array(
            'schemaVersion' => self::MANIFEST_SCHEMA,
            'slug' => self::PLUGIN_SLUG,
            'version' => (string) $data['version'],
            'packageUrl' => (string) $data['packageUrl'],
            'sha256' => strtolower((string) $data['sha256']),
            'signature' => (string) $data['signature'],
            'requiresWordPress' => (string) $data['requiresWordPress'],
            'requiresPhp' => (string) $data['requiresPhp'],
            'releasedAt' => (string) $data['releasedAt'],
            'detailsUrl' => isset($data['detailsUrl']) && self::is_https_url($data['detailsUrl']) ? (string) $data['detailsUrl'] : '',
            'testedWordPress' => isset($data['testedWordPress']) ? sanitize_text_field($data['testedWordPress']) : '',
        );
    }

    public static function canonical_payload($manifest)
    {
        return implode("\n", array(
            'schemaVersion=' . (int) $manifest['schemaVersion'],
            'slug=' . (string) $manifest['slug'],
            'version=' . (string) $manifest['version'],
            'packageUrl=' . (string) $manifest['packageUrl'],
            'sha256=' . strtolower((string) $manifest['sha256']),
            'requiresWordPress=' . (string) $manifest['requiresWordPress'],
            'requiresPhp=' . (string) $manifest['requiresPhp'],
            'releasedAt=' . (string) $manifest['releasedAt'],
        ));
    }

    public function inject_update($transient)
    {
        if (!is_object($transient)) {
            $transient = new stdClass();
        }
        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = array();
        }

        $manifest = $this->check_release(false);
        if (is_wp_error($manifest) || !version_compare($manifest['version'], EASYHEADLESS_VERSION, '>')) {
            return $transient;
        }

        $transient->response[EASYHEADLESS_PLUGIN_BASENAME] = $this->update_object($manifest);
        return $transient;
    }

    public function plugin_information($result, $action, $args)
    {
        if ('plugin_information' !== $action || empty($args->slug) || self::PLUGIN_SLUG !== $args->slug) {
            return $result;
        }

        $manifest = $this->check_release(false);
        if (is_wp_error($manifest)) {
            return $result;
        }

        return (object) array(
            'name' => 'EasyHeadless Bridge',
            'slug' => self::PLUGIN_SLUG,
            'version' => $manifest['version'],
            'requires' => $manifest['requiresWordPress'],
            'requires_php' => $manifest['requiresPhp'],
            'tested' => $manifest['testedWordPress'],
            'download_link' => $manifest['packageUrl'],
            'homepage' => $manifest['detailsUrl'],
            'sections' => array('description' => 'Signed EasyHeadless Bridge release.'),
        );
    }

    public function verify_download($reply, $package, $upgrader, $hook_extra)
    {
        if (false !== $reply) {
            return $reply;
        }

        $plugin = isset($hook_extra['plugin']) ? (string) $hook_extra['plugin'] : '';
        if (EASYHEADLESS_PLUGIN_BASENAME !== $plugin) {
            return $reply;
        }

        $manifest = $this->verified_manifest;
        if (!is_array($manifest)) {
            $manifest = $this->check_release(false);
        }
        if (is_wp_error($manifest) || !hash_equals($manifest['packageUrl'], (string) $package)) {
            return new WP_Error('easyheadless_package_untrusted', 'WordPress refused an untrusted EasyHeadless package URL.');
        }

        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $file = download_url($package, 300, false);
        if (is_wp_error($file)) {
            return $this->record_error('easyheadless_package_download_failed', 'The signed EasyHeadless package could not be downloaded.', $manifest['version'], $file);
        }

        if (!self::verify_package_hash($file, $manifest['sha256'])) {
            @unlink($file);
            return $this->record_error('easyheadless_package_hash_mismatch', 'The downloaded EasyHeadless package failed its SHA-256 integrity check.', $manifest['version']);
        }

        return $file;
    }

    public static function verify_package_hash($file, $expected)
    {
        if (!is_string($file) || !is_readable($file) || !preg_match('/^[a-f0-9]{64}$/i', (string) $expected)) {
            return false;
        }
        $actual = hash_file('sha256', $file);
        return is_string($actual) && hash_equals(strtolower((string) $expected), strtolower($actual));
    }

    public function install_release()
    {
        if (!$this->acquire_lock()) {
            return new WP_Error('easyheadless_update_locked', 'Another EasyHeadless update is already running.', array('status' => 409));
        }

        try {
            $manifest = $this->check_release(true);
            if (is_wp_error($manifest)) {
                return $manifest;
            }
            if (!version_compare($manifest['version'], EASYHEADLESS_VERSION, '>')) {
                return $this->record_error('easyheadless_update_not_newer', 'The trusted release is not newer than the installed EasyHeadless version.', $manifest['version']);
            }

            $preflight = $this->preflight($manifest);
            if (is_wp_error($preflight)) {
                return $this->record_error($preflight->get_error_code(), $preflight->get_error_message(), $manifest['version'], $preflight);
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';

            $updates = get_site_transient('update_plugins');
            $updates = $this->inject_update($updates);
            set_site_transient('update_plugins', $updates);

            $this->verified_manifest = $manifest;
            $skin = class_exists('Automatic_Upgrader_Skin') ? new Automatic_Upgrader_Skin() : new WP_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);
            $result = $upgrader->upgrade(EASYHEADLESS_PLUGIN_BASENAME, array('clear_update_cache' => true));
            if (is_wp_error($result) || true !== $result) {
                $error = is_wp_error($result) ? $result : new WP_Error('easyheadless_update_failed', 'WordPress did not complete the EasyHeadless update.');
                return $this->record_error($error->get_error_code(), $error->get_error_message(), $manifest['version'], $error);
            }

            wp_clean_plugins_cache(true);
            $data = get_plugin_data(EASYHEADLESS_PLUGIN_FILE, false, false);
            $installed = isset($data['Version']) ? (string) $data['Version'] : '';
            if ($installed !== $manifest['version']) {
                return $this->record_error('easyheadless_update_version_mismatch', 'WordPress completed the update but the installed version did not match the signed manifest.', $manifest['version']);
            }

            $status = array(
                'timestamp' => gmdate('c'),
                'requestedVersion' => $manifest['version'],
                'installedVersion' => $installed,
                'success' => true,
                'message' => 'EasyHeadless was updated through the WordPress upgrader.',
            );
            update_option(self::OPTION_STATUS, $status, false);

            $public = $this->public_status($manifest);
            $public['currentVersion'] = $installed;
            $public['updateAvailable'] = false;

            return array('success' => true, 'status' => $status, 'updater' => $public);
        } finally {
            $this->release_lock();
        }
    }

    public function preflight($manifest)
    {
        $reasons = $this->environment_reasons($this->config());
        if ($reasons) {
            return new WP_Error('easyheadless_update_preflight_failed', implode(' ', $reasons));
        }
        if (version_compare(get_bloginfo('version'), $manifest['requiresWordPress'], '<')) {
            return new WP_Error('easyheadless_wordpress_incompatible', 'This release requires a newer WordPress version.');
        }
        if (version_compare(PHP_VERSION, $manifest['requiresPhp'], '<')) {
            return new WP_Error('easyheadless_php_incompatible', 'This release requires a newer PHP version.');
        }

        if (!function_exists('get_filesystem_method')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ('direct' !== get_filesystem_method(array(), WP_PLUGIN_DIR)) {
            return new WP_Error('easyheadless_filesystem_credentials_required', 'This site requires interactive filesystem credentials; use the WordPress Plugins screen to update safely.');
        }
        if (!wp_is_writable(WP_PLUGIN_DIR) || !wp_is_writable(WP_CONTENT_DIR)) {
            return new WP_Error('easyheadless_filesystem_read_only', 'The WordPress plugin or content directory is not writable.');
        }

        return true;
    }

    private function environment_reasons($config, $include_wordpress = true)
    {
        $reasons = array();
        if (empty($config['manifest_url']) || empty($config['public_key'])) {
            $reasons[] = 'A trusted HTTPS manifest and Ed25519 public key have not been configured.';
        }
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            $reasons[] = 'PHP Sodium is unavailable, so release signatures cannot be verified.';
        }
        if ($include_wordpress && version_compare(get_bloginfo('version'), self::MINIMUM_SAFE_WORDPRESS, '<')) {
            $reasons[] = 'WordPress ' . self::MINIMUM_SAFE_WORDPRESS . ' or newer is required for core plugin rollback safeguards.';
        }
        return $reasons;
    }

    private function update_object($manifest)
    {
        return (object) array(
            'slug' => self::PLUGIN_SLUG,
            'plugin' => EASYHEADLESS_PLUGIN_BASENAME,
            'new_version' => $manifest['version'],
            'url' => $manifest['detailsUrl'],
            'package' => $manifest['packageUrl'],
            'tested' => $manifest['testedWordPress'],
            'requires_php' => $manifest['requiresPhp'],
        );
    }

    private function acquire_lock()
    {
        $lock = get_option(self::OPTION_LOCK, array());
        if (is_array($lock) && !empty($lock['timestamp']) && (time() - (int) $lock['timestamp']) > self::LOCK_TTL) {
            delete_option(self::OPTION_LOCK);
        }

        return add_option(self::OPTION_LOCK, array('timestamp' => time()), '', false);
    }

    private function release_lock()
    {
        delete_option(self::OPTION_LOCK);
    }

    private function record_error($code, $message, $version = '', $error = null)
    {
        $safe = sanitize_text_field((string) $message);
        $safe = function_exists('mb_substr') ? mb_substr($safe, 0, 500) : substr($safe, 0, 500);
        $status = array(
            'timestamp' => gmdate('c'),
            'requestedVersion' => (string) $version,
            'installedVersion' => EASYHEADLESS_VERSION,
            'success' => false,
            'message' => $safe,
        );
        update_option(self::OPTION_STATUS, $status, false);
        $data = array('status' => 409);
        if (is_wp_error($error) && $error->get_error_data()) {
            $existing = $error->get_error_data();
            if (is_array($existing) && isset($existing['status'])) {
                $data['status'] = absint($existing['status']);
            }
        }

        return new WP_Error(sanitize_key($code), $safe, $data);
    }

    private static function is_https_url($url)
    {
        return is_string($url) && 'https' === strtolower((string) parse_url($url, PHP_URL_SCHEME)) && (bool) parse_url($url, PHP_URL_HOST);
    }

    private static function valid_public_key($key)
    {
        if (!is_string($key) || '' === $key) {
            return false;
        }
        $decoded = base64_decode($key, true);
        return false !== $decoded && 32 === strlen($decoded);
    }
}
