<?php
define('ABSPATH',__DIR__.'/');
function add_action(...$a){} function add_filter(...$a){} function add_shortcode(...$a){}
function apply_filters($name,$value,...$args){return $value;}
function wp_allowed_protocols(){return ['http','https','mailto'];}
function wp_parse_url(...$args){return parse_url(...$args);}
function wp_strip_all_tags($s){return strip_tags($s);}
function esc_attr($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function wp_check_invalid_utf8($s){return $s;}
class WP_Error {public function __construct(public $code,public $message='',public $data=[]){} function get_error_code(){return $this->code;} function get_error_message(){return $this->message;}}
function is_wp_error($v){return $v instanceof WP_Error;}
function get_option($k,$default=false){return $GLOBALS['options'][$k]??$default;}
function get_user_meta($id,$k,$single=true){return $GLOBALS['users'][$id]->meta[$k]??'';}
function get_post_meta($id,$k,$single=true){return $GLOBALS['posts'][$id]->meta[$k]??'';}
function get_userdata($id){return $GLOBALS['users'][$id]??false;}
function get_post($id){return $GLOBALS['posts'][$id]??false;}
function trb_portal_user_profile($user){return $user->profile;}
function trb_portal_is_release_qa_account($u){return $u->qa??false;}
function trb_release_is_inactive($id){return get_post_meta($id,'inactive',true);}
function pw_new_user_approve(){return new class{function get_user_status($id){return get_userdata($id)->approval;}};}
function current_user_can($cap,...$args){return $GLOBALS['allowed']??false;}
function wp_verify_nonce($n,$action){return $n==='valid';}
$wpTestRoot = getenv('TRB_WP_TEST_ROOT');
if (!$wpTestRoot || !is_file($wpTestRoot.'/wp-includes/kses.php')) throw new RuntimeException('WordPress test core missing');
require_once $wpTestRoot.'/wp-includes/html-api/class-wp-html-tag-processor.php';
foreach(glob($wpTestRoot.'/wp-includes/html-api/class-wp-html-*.php') as $dependency)require_once $dependency;
require $wpTestRoot.'/wp-includes/kses.php';
require __DIR__.'/trb-site-studio/editor.php';
require __DIR__.'/trb-site-studio/portal.php';
require __DIR__.'/trb-site-studio/directory.php';
eval('namespace TRB\\Studio; function destination_site(){return true;} function admin_permission(){return \\current_user_can("manage_options");}');
$count=0;function check($v,$message){global $count;if(!$v){fwrite(STDERR,"FAIL: $message\n");exit(1);}++$count;echo "PASS: $message\n";}
$raw='<!-- wp:html --><style>p{color:red}</style><script>var x="<p>hidden</p>";</script><h1 class="title">Musica &amp; persone</h1><p id="bio">La nostra <strong>storia</strong>.</p><p>[fluentform id="7"]</p><!-- /wp:html --><!-- wp:paragraph --><p>Native</p><!-- /wp:paragraph -->';
$items=TRB\Studio\editable_items($raw);check(count($items)===2,'Manifest excludes scripts, shortcodes and native blocks');$keys=array_keys($items);
$new=TRB\Studio\editor_apply($raw,[['key'=>$keys[1],'html'=>'L’artista <strong>nuovo</strong><img src=x onerror=alert(1)><a href="javascript:alert(1)">link</a>']]);
check(!is_wp_error($new)&&!str_contains($new,'onerror')&&!str_contains($new,'javascript:'),'Real WordPress KSES removes executable HTML and protocols');
check(str_contains($new,'id="bio"')&&str_contains($new,'L’artista <strong>nuovo</strong>'),'Keeps element identity and Italian text');
$new=TRB\Studio\editor_apply($raw,[['key'=>$keys[1],'spacing'=>['margin-top'=>24,'max-width'=>960]]]);check(str_contains($new,'24px !important')&&str_contains($new,'<strong>storia</strong>'),'Spacing preserves existing rich text');
check(is_wp_error(TRB\Studio\editor_apply($raw,[['key'=>$keys[0],'spacing'=>['margin-top'=>-100]]])),'Negative margin rejected');
check(is_wp_error(TRB\Studio\editor_apply($raw,[['key'=>$keys[0],'spacing'=>['position'=>'fixed']]])),'Arbitrary CSS rejected');
check(is_wp_error(TRB\Studio\editor_apply($raw,[['key'=>'unknown','html'=>'x']])),'Stale element rejected');
check(is_wp_error(TRB\Studio\editor_apply($raw,[['key'=>$keys[0],'html'=>'[dangerous_shortcode]']])),'New shortcode rejected');
check(is_wp_error(TRB\Studio\editor_apply($raw,[['key'=>$keys[0]],['key'=>$keys[0]]])),'Duplicate patch rejected');
check(count(TRB\Studio\editable_items('<!-- wp:html --><p>Same</p><p>Same</p><!-- /wp:html -->'))===0,'Ambiguous source elements are excluded');
$users=[1=>(object)['ID'=>1,'profile'=>'trb','approval'=>'approved','qa'=>false,'meta'=>[]],2=>(object)['ID'=>2,'profile'=>'ddb','approval'=>'approved','meta'=>[]],3=>(object)['ID'=>3,'profile'=>'trb','approval'=>'pending','meta'=>[]],4=>(object)['ID'=>4,'profile'=>'trb','approval'=>'approved','qa'=>true,'meta'=>[]]];
check(TRB\Studio\portal_artist_allowed($users[1]),'Approved TRB artist included');check(!TRB\Studio\portal_artist_allowed($users[2]),'DDB excluded');check(!TRB\Studio\portal_artist_allowed($users[3]),'Pending signup excluded');check(!TRB\Studio\portal_artist_allowed($users[4]),'QA account excluded');
$posts=[10=>(object)['ID'=>10,'post_author'=>1,'post_type'=>'trb_release','post_status'=>'private','post_content'=>$raw,'meta'=>['_trb_release_intake_phase'=>'complete','_trb_crm_workflow_status'=>'ready','_trb_release_pipeline_status'=>'approved']]];
$options=['trb_studio_distribution_states'=>['distributed']];
check(!TRB\Studio\portal_release_allowed($posts[10]),'Technical approval and commercial ready do not publish');$posts[10]->meta['_trb_crm_workflow_status']='distributed';check(TRB\Studio\portal_release_allowed($posts[10]),'Only configured distribution state publishes');$posts[10]->meta['inactive']=true;check(!TRB\Studio\portal_release_allowed($posts[10]),'Cancelled release excluded');unset($posts[10]->meta['inactive']);
check(TRB\Studio\valid_date('2028-02-29')&&!TRB\Studio\valid_date('2026-02-29'),'Calendar dates validated including leap years');
$s=['schema'=>1,'source'=>'artist.trbrec.com','complete'=>true,'artists'=>[['id'=>1,'name'=>'Artist','bio'=>'Bio','links'=>[]]],'releases'=>[['id'=>10,'artist_id'=>1,'title'=>'Release','date'=>'2026-11-01','presentation'=>'Story']]];
check(TRB\Studio\validate_snapshot($s),'Coherent snapshot accepted');$s['releases'][0]['artist_id']=9;check(!TRB\Studio\validate_snapshot($s),'Orphan release rejected');$s['releases'][0]['artist_id']=1;$s['artists'][]=$s['artists'][0];check(!TRB\Studio\validate_snapshot($s),'Duplicate source IDs rejected');
check(!TRB\Studio\editor_permission(['id'=>10]),'Anonymous edits rejected');
$allowed=true;$posts[10]->post_type='page';check(TRB\Studio\editor_permission(['id'=>10]),'Authorized page editing allowed');$posts[10]->post_type='trb_release';check(!TRB\Studio\editor_permission(['id'=>10]),'Managed catalog records cannot be overwritten by visual editor');
class Request implements ArrayAccess{function __construct(public $data,public $nonce){}function get_header($name){return $this->nonce;}function get_json_params(){return $this->data;}function offsetGet($k):mixed{return 10;}function offsetExists($k):bool{return true;}function offsetSet($k,$v):void{}function offsetUnset($k):void{}}
check(TRB\Studio\editor_save(new Request([],'bad'))->get_error_code()==='nonce_invalid','Invalid nonce rejected');check(TRB\Studio\editor_save(new Request(['version'=>'stale'],'valid'))->get_error_code()==='edit_conflict','Concurrent version conflict rejected before write');
echo "TOTAL: $count passed\n";
