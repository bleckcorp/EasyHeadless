<?php
// Local rendering only: the inherited mail harness never sends real email.
ob_start();
require __DIR__ . '/magic-link.php';
ob_end_clean();
define('EASYHEADLESS_EDITION', 'film-efiko');
require dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-edition.php';
function wp_strip_all_tags($text) { return strip_tags(preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $text)); }
$settings = EasyHeadless_Publication::sanitize_publication_settings(array());
$settings['auth_email_message'] = '<p>Hello {email}.</p><p style="color:red">Welcome to {site_name}.</p><img src="https://invalid.example/image.png"><script>alert(1)</script>';
$settings['auth_logo_url'] = 'https://media.example/logo.png';
$html = EasyHeadless_Publication::render_magic_link_email($settings, 'reader@example.com', 'https://film.example/login/magic#demo');
publication_assert(strpos($html, 'https://media.example/logo.png') !== false, 'Selected Media Library URL renders in the fixed header.');
publication_assert(strpos($html, 'color:red') === false && strpos($html, 'invalid.example') === false && strpos($html, '<script>') === false, 'Message cannot introduce custom styling, images or scripts.');
publication_assert(strpos($html, 'Hello reader@example.com.') !== false && strpos($html, 'Welcome to Film Efiko.') !== false, 'Legacy formatted copy and placeholders survive as plain text.');
publication_assert(strpos(EasyHeadless_Email_Branding::plain_message('<p>One</p><p>Two</p>'), "\n") !== false, 'Legacy paragraph breaks are retained.');
ob_start(); EasyHeadless_Email_Branding::logo_field($settings, EasyHeadless_Publication::OPTION_SETTINGS); $field = ob_get_clean();
publication_assert(strpos($field, 'Choose from Media Library') !== false && strpos($field, '[auth_logo_url]') !== false, 'Media picker retains the existing settings key.');
$settings['auth_logo_url'] = '';
$settings['auth_email_message'] = "Welcome to {site_name}.\n\nClick below to sign in and continue reading.";
$html = EasyHeadless_Publication::render_magic_link_email($settings, 'reader@example.com', 'https://film.example/login/magic#demo');
publication_assert(strpos($html, 'bold italic 34px') !== false && strpos($html, 'works once') !== false, 'Missing logo falls back to publication branding with secure link notices intact.');
if (isset($_GET['preview'])) { echo $html; exit; }
echo "Film Efiko fixed email and Media Library field tests passed.\n";
