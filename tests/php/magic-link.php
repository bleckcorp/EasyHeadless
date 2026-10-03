<?php
// Authentication contract tests with an in-memory WordPress/database harness. No email is sent.
require __DIR__ . '/publication.php';
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
class WP_Error { public $code; public function __construct($code, $message, $data = array()) { $this->code = $code; } }
class EasyHeadless_Modules { public static function site_profile() { return array('frontend_url' => 'https://film.example'); } }
class MagicRequest { private $params; public function __construct($params) { $this->params = $params; } public function get_param($key) { return $this->params[$key] ?? null; } }
function sanitize_email($value) { return filter_var((string) $value, FILTER_SANITIZE_EMAIL); }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function wp_parse_url($url) { return parse_url($url); }
function wp_hash_password($value) { return password_hash($value, PASSWORD_DEFAULT); }
function wp_check_password($value, $hash) { return password_verify($value, $hash); }
function current_time($type, $gmt = false) { return gmdate('Y-m-d H:i:s'); }
function wp_mail($email, $subject, $html, $headers) { global $mail, $mail_ok; $mail = compact('email', 'subject', 'html', 'headers'); return $mail_ok; }
function rest_ensure_response($value) { return $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function wpautop($value) { return '<p>' . $value . '</p>'; }
function sanitize_title($value) { return sanitize_key((string) $value); }
function get_user_by($field, $email) { global $new_reader; return empty($new_reader) ? (object) array('ID' => 7) : false; }
function username_exists($login) { return false; }
function sanitize_user($name, $strict = false) { return sanitize_key($name); }
function wp_insert_user($data) { global $created_user; $created_user = $data; return 8; }
function get_userdata($id) { return (object) array('ID' => $id); }
function is_wp_error($value) { return $value instanceof WP_Error; }
class MagicDB {
    public $prefix = 'wp_'; public $insert_id = 0; public $rows = array(); public $sessions = array(); public $recent = 0; public $race = false;
    public function prepare($sql, ...$args) { return array($sql, $args); }
    public function get_var($query) { return strpos($query[0], 'COUNT') !== false ? $this->recent : null; }
    public function insert($table, $row, $formats = array()) {
        $this->insert_id++;
        if (strpos($table, '_codes') !== false) { $row['id'] = $this->insert_id; $this->rows[$this->insert_id] = (object) $row; }
        elseif (strpos($table, '_sessions') !== false) { $this->sessions[] = $row; }
        return 1;
    }
    public function get_row($query) {
        if (strpos($query[0], '_contacts') !== false) return null;
        $matching = array_filter($this->rows, function($row) use ($query) { return $row->email === $query[1][0]; });
        return $matching ? end($matching) : null;
    }
    public function delete($table, $where, $formats = array()) { unset($this->rows[$where['id']]); return 1; }
    public function update($table, $data, $where, $formats = array(), $where_formats = array()) { foreach ($data as $key => $value) $this->rows[$where['id']]->$key = $value; return 1; }
    public function query($query) {
        if ($this->race) return 0;
        $id = $query[1][0];
        if (!isset($this->rows[$id])) return 0;
        unset($this->rows[$id]); return 1;
    }
}
$wpdb = new MagicDB(); $mail_ok = true;
$options[EasyHeadless_Publication::OPTION_SETTINGS] = array('auth_email_subject' => 'Welcome to {site_name}', 'auth_email_message' => 'Hello {email}. Your link lasts {expires_minutes} minutes.');
$request = new MagicRequest(array('email' => 'reader@example.com', 'request_ip' => '127.0.0.1', 'redirect' => '/stories/test-story'));
$result = EasyHeadless_Publication::request_magic_link($request);
publication_assert($result['method'] === 'magic-link' && !isset($result['debugCode']), 'Request sends only a link and does not expose credentials.');
publication_assert($mail['headers'][0] === 'Content-Type: text/html; charset=UTF-8', 'Mail has HTML content type.');
publication_assert($mail['subject'] === 'Welcome to Film Efiko' && strpos($mail['html'], 'Hello reader@example.com') !== false, 'Admin message and subject placeholders render.');
publication_assert(strpos($mail['html'], '#ff8b1a') !== false && strpos($mail['html'], 'works once') !== false, 'Branded email includes expiry and single-use notice.');
preg_match('~https://film.example/login/magic#[^"<]+~', html_entity_decode($mail['html']), $match);
parse_str(parse_url($match[0], PHP_URL_FRAGMENT), $params);
publication_assert($params['redirect'] === '/stories/test-story' && strlen($params['token']) === 64, 'Link retains the article and has a strong opaque token.');
$row = end($wpdb->rows);
publication_assert($row->code_hash !== $params['token'] && wp_check_password($params['token'], $row->code_hash), 'Only the token hash is stored.');
$bad = EasyHeadless_Publication::verify_magic_link(new MagicRequest(array('email' => $params['email'], 'token' => str_repeat('z', 64))));
publication_assert($bad->code === 'publication_link_invalid', 'Wrong token cannot authenticate.');
$verified = EasyHeadless_Publication::verify_magic_link(new MagicRequest($params));
publication_assert(isset($verified['session_token']) && count($wpdb->sessions) === 1, 'Valid link establishes the existing reader session.');
$replay = EasyHeadless_Publication::verify_magic_link(new MagicRequest($params));
publication_assert($replay->code === 'publication_link_expired', 'Used link cannot be replayed.');
EasyHeadless_Publication::request_magic_link($request);
$row = end($wpdb->rows); $row->expires_at = gmdate('Y-m-d H:i:s', time() - 1);
$expired = EasyHeadless_Publication::verify_magic_link(new MagicRequest($params));
publication_assert($expired->code === 'publication_link_expired', 'Expired link is rejected.');
EasyHeadless_Publication::request_magic_link($request); $wpdb->race = true;
$race = EasyHeadless_Publication::verify_magic_link(new MagicRequest($params));
publication_assert($race->code === 'publication_link_expired' && count($wpdb->sessions) === 1, 'Concurrent token consumption cannot create a second session.');
$wpdb->race = false; $wpdb->recent = 5;
publication_assert(EasyHeadless_Publication::request_magic_link($request)->code === 'publication_rate_limited', 'Requests are rate limited.');
$wpdb->recent = 0; $mail_ok = false; $before = count($wpdb->rows);
publication_assert(EasyHeadless_Publication::request_magic_link($request)->code === 'publication_email_failed' && count($wpdb->rows) === $before, 'Failed delivery removes the new token.');
publication_assert(EasyHeadless_Publication::safe_reader_redirect('//evil.example') === '/account', 'Off-site redirect is rejected.');
publication_assert(EasyHeadless_Publication::safe_reader_redirect('/stories/test-story') === '/stories/test-story', 'Article return path is allowed.');
$settings = EasyHeadless_Publication::sanitize_publication_settings(array('auth_email_heading' => '<b>Hello</b>', 'auth_email_subject' => '<b>Welcome</b>'));
publication_assert($settings['auth_email_heading'] === 'Hello' && $settings['auth_email_subject'] === 'Welcome', 'Admin subject and heading are sanitized.');
$mail_ok = true; $new_reader = true;
EasyHeadless_Publication::request_magic_link($request);
$joined = EasyHeadless_Publication::verify_magic_link(new MagicRequest($params));
publication_assert(isset($joined['session_token']) && $created_user['user_email'] === 'reader@example.com' && $created_user['role'] === 'subscriber', 'Joining creates a reader subscriber only after valid verification.');
publication_assert(EasyHeadless_Publication::verify_code(new MagicRequest(array('email' => 'reader@example.com', 'code' => '123456')))->code === 'publication_link_invalid', 'Legacy numeric OTP submissions are rejected.');
$options[EasyHeadless_Publication::OPTION_SETTINGS]['auth_frontend_url'] = 'javascript:alert(1)';
publication_assert(EasyHeadless_Publication::request_magic_link($request)->code === 'publication_frontend_missing', 'Unsafe frontend callback schemes are rejected.');
echo "Magic-link authentication and branded email tests passed.\n";
