<?php
define('ABSPATH', __DIR__);
$options = array(); $scheduled = array(); $requests = array(); $responses = array(); $hooks = array();
class WP_Error {
    private $code; private $message; private $data;
    public function __construct($code, $message, $data = null) { $this->code=$code; $this->message=$message; $this->data=$data; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function get_option($k,$d=false) { return $GLOBALS['options'][$k] ?? $d; }
function update_option($k,$v,$autoload=null) { $GLOBALS['options'][$k]=$v; check(false === $autoload, 'Private options must not autoload.'); }
function delete_option($k) { unset($GLOBALS['options'][$k]); }
function add_action($hook,$callback,$priority=10) { $GLOBALS['hooks'][$hook]=$callback; }
function sanitize_email($s) { return trim($s); }
function is_email($s) { return filter_var($s,FILTER_VALIDATE_EMAIL); }
function wp_json_encode($v) { return json_encode($v); }
function wp_next_scheduled($h,$args) { return $GLOBALS['scheduled'][$args[0]] ?? false; }
function wp_schedule_single_event($t,$h,$args) { $GLOBALS['scheduled'][$args[0]]=$t; }
function wp_clear_scheduled_hook($h,$args) { unset($GLOBALS['scheduled'][$args[0]]); }
function wp_remote_request($url,$args) {
    $GLOBALS['requests'][]=array($url,$args);
    if (!$GLOBALS['responses']) { throw new Exception('Unexpected HTTP request.'); }
    return array_shift($GLOBALS['responses']);
}
function wp_remote_retrieve_response_code($r) { return $r['status']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
class EO_DB {
    public $prefix='wp_'; public $contact;
    public function prepare($sql,$email) { return $sql; }
    public function get_row($sql) { return $this->contact; }
}
$wpdb = new EO_DB();
require dirname(__DIR__,2).'/easyheadless-bridge/includes/class-easyheadless-emailoctopus.php';
function check($v,$message) { if (!$v) { throw new Exception($message); } }
function response($status,$body) { return array('status'=>$status,'body'=>json_encode($body)); }
function setup_eo($consent=1) {
    $GLOBALS['options']=array(EasyHeadless_EmailOctopus::OPTION=>array('enabled'=>true,'api_key'=>'eo_fixture_only','list_id'=>'list-fixture'));
    $GLOBALS['requests']=array(); $GLOBALS['responses']=array(); $GLOBALS['scheduled']=array();
    $GLOBALS['wpdb']->contact=(object)array('newsletter_consent'=>$consent);
}
function job_id() { return EasyHeadless_EmailOctopus::JOB_PREFIX.hash('sha256','list-fixture:reader@example.com'); }
EasyHeadless_EmailOctopus::register_hooks();
check(isset($hooks['easyheadless_publication_newsletter_subscribed'],$hooks['easyheadless_publication_newsletter_unsubscribed']), 'Both explicit consent hooks supported.');
check(!isset($hooks['user_register'],$hooks['wp_login']), 'Sign-in never subscribes.');
setup_eo(); $options[EasyHeadless_EmailOctopus::OPTION]['enabled']=false;
EasyHeadless_EmailOctopus::enqueue('reader@example.com'); check(!$requests && !$scheduled,'Disabled adapter does nothing.');
setup_eo();
$responses=array(response(200,array('double_opt_in'=>true)),response(201,array('id'=>'contact-fixture','status'=>'pending')));
EasyHeadless_EmailOctopus::enqueue('READER@example.com');
check(count($requests)===2,'Subscription syncs immediately.');
check(json_decode($requests[1][1]['body'],true)===array('email_address'=>'reader@example.com','status'=>'pending'),'Double opt-in preserved.');
check($requests[1][1]['method']==='POST' && $requests[1][1]['redirection']===0,'Create-only subscription and no credential redirects.');
check($requests[1][1]['headers']['Authorization']==='Bearer eo_fixture_only','v2 Bearer authentication.');
check(!isset($options[job_id()]) && !$scheduled,'Success clears durable job and retry.');
setup_eo(); $responses=array(response(200,array('double_opt_in'=>false)),response(409,array('detail'=>'Already exists')));
EasyHeadless_EmailOctopus::enqueue('reader@example.com');
check(count($requests)===2 && !isset($options[job_id()]),'Duplicate is preserved, never PUT back to subscribed.');
check(json_decode($requests[1][1]['body'],true)['status']==='subscribed','Single opt-in uses subscribed for a new contact.');
setup_eo(); $responses=array(response(503,array('detail'=>'do not expose eo_fixture_only reader@example.com')));
EasyHeadless_EmailOctopus::enqueue('reader@example.com');
check($options[job_id()]['state']==='pending' && isset($scheduled[job_id()]),'503 persists job and schedules retry.');
check(strpos($options['easyheadless_eo_last_result'],'eo_fixture')===false,'Status never echoes key/provider body.');
$wpdb->contact->newsletter_consent=0;
$responses=array(response(200,array('status'=>'unsubscribed')));
EasyHeadless_EmailOctopus::sync(job_id());
$last=end($requests);
check($last[1]['method']==='PUT' && json_decode($last[1]['body'],true)['status']==='unsubscribed','Retry re-reads consent and sends opt-out instead of stale subscribe.');
setup_eo(); $responses=array(response(401,array()));
EasyHeadless_EmailOctopus::enqueue('reader@example.com');
check($options[job_id()]['state']==='failed' && !$scheduled,'Invalid credentials retained as failed, no endless retry.');
setup_eo(); $responses=array(new WP_Error('timeout','secret raw error'));
EasyHeadless_EmailOctopus::enqueue('reader@example.com');
check($options[job_id()]['state']==='pending','Transport failures retry.');
check(strpos($options[job_id()]['message'],'secret')===false,'Raw transport errors not saved.');
$wpdb->contact=null; $responses=array(); EasyHeadless_EmailOctopus::sync(job_id());
check(!isset($options[job_id()]) && !$scheduled,'Deleted contacts cannot be recreated by retries.');
setup_eo(); $responses=array(response(429,array())); EasyHeadless_EmailOctopus::enqueue('reader@example.com');
for($i=1;$i<8;$i++) { $responses=array(response(503,array())); EasyHeadless_EmailOctopus::sync(job_id()); }
check($options[job_id()]['attempts']===8 && $options[job_id()]['state']==='failed' && !$scheduled,'Retries bounded to eight attempts.');
setup_eo(); $responses=array(response(200,array('name'=>'missing policy'))); EasyHeadless_EmailOctopus::enqueue('reader@example.com');
check(count($requests)===1 && isset($options[job_id()]),'Unknown opt-in policy fails closed.');
setup_eo(); $responses=array(array('status'=>200,'body'=>'<html>unavailable</html>')); EasyHeadless_EmailOctopus::enqueue('reader@example.com');
check($options[job_id()]['state']==='pending','Malformed success responses cannot count as delivery.');
$options[EasyHeadless_EmailOctopus::OPTION]['list_id']='another-list'; $responses=array(); EasyHeadless_EmailOctopus::sync(job_id());
check(!isset($options[job_id()]),'Old destination jobs are discarded, not moved silently.');
$source=file_get_contents(dirname(__DIR__,2).'/easyheadless-bridge/includes/class-easyheadless-emailoctopus.php');
check(strpos($source,"check_admin_referer('easyheadless_emailoctopus')")!==false && strpos($source,"current_user_can('manage_options')")!==false,'Admin mutations protected by capability and nonce.');
check(strpos($source,'value="" class="regular-text"')!==false,'Key never prefilled into admin HTML.');
echo "EmailOctopus consent, v2 requests, durable retries, suppression and secrecy tests passed.\n";
