<?php
define('ABSPATH', __DIR__);
$meta = array(); $events = array(); $routes = array(); $can_edit = true; $revision = false; $fail_storage = false;
class WP_Error { public $code; public $message; public $data; public function __construct($code, $message, $data = array()) { $this->code=$code; $this->message=$message; $this->data=$data; } }
class WP_REST_Server { const READABLE='GET'; const EDITABLE='POST, PUT, PATCH'; }
class Review_Request {
    public $id; public $body; public $auth;
    public function __construct($id=1, $body=array(), $auth='') { $this->id=$id; $this->body=$body; $this->auth=$auth; }
    public function get_param($key) { return $key === 'id' ? $this->id : null; }
    public function get_json_params() { return $this->body; }
}
class EasyHeadless_Publication {
    public static function enabled() { return true; }
    public static function public_permission() { return true; }
    public static function notify_frontend($id, $type) { $GLOBALS['events'][] = array('publication', $id, $type); }
}
class EasyHeadless_REST { public function can_write($request) { return $request->auth === 'editor-api-key' ? true : new WP_Error('forbidden','No API key',array('status'=>403)); } }
function get_post_meta($id, $key, $single=true) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { if($GLOBALS['fail_storage']) return false; $GLOBALS['meta'][$id][$key]=$value; return true; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function do_action(...$args) { $GLOBALS['events'][]=$args; }
function get_post($id) { return $id === 1 || $id === 2 ? (object) array('ID'=>$id,'post_type'=>'post','post_status'=>'publish') : ($id === 3 ? (object) array('ID'=>3,'post_type'=>'page','post_status'=>'publish') : null); }
function absint($value) { return abs((int)$value); }
function rest_ensure_response($value) { return $value; }
function register_rest_route($namespace,$path,$options) { $GLOBALS['routes'][]=array($namespace,$path,$options); }
function current_user_can(...$args) { return $GLOBALS['can_edit']; }
function wp_is_post_revision($id) { return $GLOBALS['revision']; }
function wp_unslash($value) { return $value; }
function wp_verify_nonce($nonce,$action) { return $nonce==='valid'; }
function wp_nonce_field($action,$field) { echo '<input name="'.$field.'" value="valid">'; }
function esc_attr($value) { return htmlspecialchars((string)$value,ENT_QUOTES); }
function esc_textarea($value) { return esc_attr($value); }
function review_assert($condition,$message) { if(!$condition) throw new Exception($message); }
require dirname(__DIR__,2).'/easyheadless-bridge/includes/class-easyheadless-publication-review.php';
$class = 'EasyHeadless_Publication_Review';
review_assert(null === $class::public_data(1), 'Unconfigured posts expose null, not a default score.');
$result=$class::write(1,array('description'=>'Verdict first'));
review_assert(!is_wp_error($result) && null === $class::public_data(1), 'Description alone does not enable a rating.');
$result=$class::write(1,array('score'=>'5.1'));
review_assert($result===array('score'=>5.1,'description'=>'Verdict first'), 'Partial score update preserves verdict.');
review_assert(null === $class::public_data(2), 'Scores are scoped to their own post.');
foreach(array(-1,10.1,5.12,'banana',true,array(),INF,NAN) as $invalid) {
    $before=$meta;
    review_assert(is_wp_error($class::write(1,array('score'=>$invalid))), 'Invalid score rejected.');
    review_assert($meta===$before, 'Rejected payload cannot mutate score or verdict.');
}
review_assert(is_wp_error($class::write(1,array('unknown'=>'x'))), 'Unknown fields rejected.');
review_assert(is_wp_error($class::write(1,array('description'=>array()))), 'Non-text verdict rejected.');
foreach(array(0,10,7.5) as $valid) {
    review_assert(!is_wp_error($class::write(1,array('score'=>$valid))), 'Boundary and decimal scores accepted.');
    review_assert($class::public_data(1)['score']===(float)$valid, 'Zero remains visible; it is not missing.');
}
$class::write(1,array('score'=>null));
review_assert(null===$class::public_data(1), 'Clearing score hides all score elements.');
$class::write(1,array('score'=>5.1,'description'=>'<b>Plain verdict</b>'));
review_assert($class::public_data(1)['description']==='Plain verdict', 'Verdict is plain text.');
$before=$meta; $_POST=array(); $class::save(1,get_post(1));
review_assert($meta===$before, 'Omitted controls never clear existing data.');
$_POST=array('easyheadless_review_nonce'=>'invalid','easyheadless_review_score'=>'8.2'); $class::save(1,get_post(1));
review_assert($meta===$before, 'Invalid nonce cannot write.');
$_POST['easyheadless_review_nonce']='valid'; $can_edit=false; $class::save(1,get_post(1));
review_assert($meta===$before, 'User without post edit capability cannot write.');
$can_edit=true; $revision=true; $class::save(1,get_post(1));
review_assert($meta===$before, 'Revision save cannot overwrite score.');
$revision=false; $class::save(1,get_post(1));
review_assert($class::public_data(1)['score']===8.2, 'Valid editor form save persists.');
$routes=array(); $class::routes();
review_assert(count($routes)===2 && $routes[1][2]['permission_callback']===array($class,'permission'), 'Read/write editorial routes are protected.');
review_assert(is_wp_error($class::permission(new Review_Request())), 'Anonymous metadata writes denied.');
review_assert(true===$class::permission(new Review_Request(1,array(),'editor-api-key')), 'Editor API credential accepted.');
review_assert(is_wp_error($class::update(new Review_Request(3,array('score'=>4)))), 'Pages cannot receive post score updates.');
review_assert(is_wp_error($class::inspect(new Review_Request(999))), 'Missing posts return an error.');
$result=$class::update(new Review_Request(2,array('score'=>0,'description'=>'A separate verdict')));
review_assert($result['review']['score']===0.0 && $result['postId']===2, 'Editorial API supports zero and independent articles.');
$fail_storage=true;
review_assert(is_wp_error($class::write(1,array('score'=>9))), 'Storage failure cannot report success.');
ob_start(); $class::render(get_post(1)); $html=ob_get_clean();
review_assert(strpos($html,'step="0.1"')!==false && strpos($html,'easyheadless_review_description')!==false && strpos($html,'easyheadless_review_nonce')!==false, 'Editor exposes score, description and nonce.');
echo "Efiko Score validation, storage, per-post editor and API tests passed.\n";
