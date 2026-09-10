<?php
/**
 * Plugin Name: EasyHeadless Bridge
 * Description: Normalized headless REST API for agency WordPress sites using ACF, Yoast SEO, and form plugin adapters.
 * Version: 0.6.4
 * Author: EasyHeadless
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Update URI: false
 * Text Domain: easyheadless
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EASYHEADLESS_VERSION', '0.6.4');
define('EASYHEADLESS_PLUGIN_FILE', __FILE__);
define('EASYHEADLESS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EASYHEADLESS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EASYHEADLESS_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once EASYHEADLESS_PLUGIN_DIR . 'includes/class-easyheadless-plugin.php';

register_activation_hook(__FILE__, array('EasyHeadless_Plugin', 'activate'));

add_action('plugins_loaded', array('EasyHeadless_Plugin', 'instance'));
