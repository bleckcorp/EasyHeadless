<?php

define('ABSPATH', __DIR__);
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
require_once dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-admin.php';
ob_start();
EasyHeadless_Admin::render_social_fields(array('social_links' => array(
    array('label' => 'Twitter', 'url' => 'https://x.com/example'),
    array('label' => 'Other network', 'url' => 'https://example.test/?a="&b=2'),
)));
$html = ob_get_clean();
foreach (array('Twitter', 'LinkedIn', 'Instagram', 'YouTube', 'TikTok', 'Other network') as $label) {
    if (false === strpos($html, 'value="' . $label . '"')) throw new Exception('Missing field: ' . $label);
}
if (false !== strpos($html, 'value="X / Twitter"')) throw new Exception('Duplicate Twitter field');
if (false === strpos($html, 'https://example.test/?a=&quot;&amp;b=2')) throw new Exception('Unsafe URL attribute');
if (false === strpos($html, 'profile[social_links][0][url]')) throw new Exception('Incorrect save contract');
echo "Social settings fields preserve existing networks, escape values, and use the site-profile save contract.\n";
