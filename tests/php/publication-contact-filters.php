<?php
define('ABSPATH', __DIR__);
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function wp_unslash($value) { return $value; }
function current_user_can($cap) { return $GLOBALS['can_manage']; }
function esc_attr($v) { return htmlspecialchars($v, ENT_QUOTES); }
function esc_html($v) { return htmlspecialchars($v, ENT_QUOTES); }
function esc_url($v) { return htmlspecialchars($v, ENT_QUOTES); }
function selected($a,$b) { if ($a===$b) { echo 'selected="selected"'; } }
function admin_url($path) { return '/wp-admin/'.$path; }
function wp_nonce_url($url,$action) { return $url.'&_wpnonce=test-nonce'; }
function add_query_arg($args,$url) { return $url.'&'.http_build_query($args); }
function absint($v) { return abs((int)$v); }
class EasyHeadless_Admin {
    public static function open_module_page($a,$b,$c) { echo '<h1>'.esc_html($a).'</h1>'; }
    public static function close_module_page() {}
}
class Filter_DB {
    public $prefix='wp_'; public $db; public $last_query='';
    public function __construct() {
        $this->db=new PDO('sqlite::memory:');
        $this->db->exec('CREATE TABLE wp_eh_publication_contacts (id INTEGER, email TEXT, verified_at TEXT, newsletter_consent INTEGER, source_article TEXT, updated_at TEXT)');
        $insert=$this->db->prepare('INSERT INTO wp_eh_publication_contacts VALUES (?,?,?,?,?,?)');
        foreach(array(array(1,'both@example.com','2026-10-21',1),array(2,'newsletter@example.com',null,1),array(3,'member@example.com','2026-10-21',0),array(4,'contact@example.com',null,0),array(5,'empty@example.com','',0)) as $row) { $insert->execute(array_merge($row,array('','2026-10-21'))); }
    }
    public function esc_like($s) { return addcslashes($s,'_%\\'); }
    public function prepare($sql,$value) { return str_replace('%s',$this->db->quote($value),$sql); }
    public function get_results($sql) { $this->last_query=$sql; return $this->db->query($sql)->fetchAll(PDO::FETCH_OBJ); }
}
$wpdb=new Filter_DB(); $can_manage=true;
require dirname(__DIR__,2).'/easyheadless-bridge/includes/class-easyheadless-publication.php';
function contact_check($condition,$message) { if (!$condition) { throw new Exception($message); } }
foreach(array('all'=>array(1,2,3,4,5),'newsletter'=>array(1,2),'members'=>array(1,3),'both'=>array(1),'newsletter_only'=>array(2),'members_only'=>array(3),'other'=>array(4,5)) as $group=>$expected) {
    $_GET=array('contact_group'=>$group);
    $filters=EasyHeadless_Publication::contact_filters();
    $rows=$wpdb->get_results('SELECT * FROM wp_eh_publication_contacts'.$filters['where'].' ORDER BY id');
    contact_check(array_map(function($row){return (int)$row->id;},$rows)===$expected,'Correct membership/consent set for '.$group);
}
$_GET=array('contact_group'=>'newsletter','s'=>'both@');
$filters=EasyHeadless_Publication::contact_filters();
contact_check(count($wpdb->get_results('SELECT * FROM wp_eh_publication_contacts'.$filters['where']))===1,'Search combines with group filter.');
ob_start(); EasyHeadless_Publication::render_contacts_page(); $html=ob_get_clean();
contact_check(strpos($html,'value="newsletter" selected="selected"')!==false,'Selected filter persists.');
contact_check(strpos($html,'both@example.com')!==false && strpos($html,'newsletter@example.com')===false,'Actual table uses combined filter.');
contact_check(strpos($html,'contact_group=newsletter&amp;s=both%40')!==false,'Export link preserves current filters.');
contact_check(strpos($html,'Member verified at')!==false && strpos($html,'Newsletter consent')!==false,'Distinct column labels.');
$_GET=array('contact_group'=>'injection OR 1=1','s'=>"' OR 1=1 --");
$filters=EasyHeadless_Publication::contact_filters();
contact_check($filters['group']==='all' && !$wpdb->get_results('SELECT * FROM wp_eh_publication_contacts'.$filters['where']),'Group allowlist and prepared search reject SQL injection.');
$_GET=array('contact_group'=>array('members'),'s'=>array('invalid'));
$filters=EasyHeadless_Publication::contact_filters();
contact_check($filters['group']==='all' && $filters['search']==='','Malformed query values safely default.');
$_GET=array('contact_group'=>'newsletter','s'=>'nothing-found');
ob_start(); EasyHeadless_Publication::render_contacts_page(); $html=ob_get_clean();
contact_check(strpos($html,'No contacts match these filters.')!==false,'Empty state shown.');
$can_manage=false; $wpdb->last_query='';
ob_start(); EasyHeadless_Publication::render_contacts_page(); $html=ob_get_clean();
contact_check($html==='' && $wpdb->last_query==='','Unauthorized users cannot inspect contacts.');
$source=file_get_contents(dirname(__DIR__,2).'/easyheadless-bridge/includes/class-easyheadless-publication.php');
$export=substr($source,strpos($source,'public static function export_contacts()'),1500);
contact_check(strpos($export,'self::contact_filters()')!==false && strpos($export,'contacts{$where}')!==false,'CSV uses the same shared filter.');
echo "Contact filter data sets, overlap, search, rendered table/export, empty state and permissions passed.\n";
