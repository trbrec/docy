<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ABSPATH',__DIR__.'/');
define('WPINC', 'wp-includes');
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
require_once $wpTestRoot.'/wp-includes/compat.php';
require_once $wpTestRoot.'/wp-includes/html-api/class-wp-html-tag-processor.php';
foreach(glob($wpTestRoot.'/wp-includes/html-api/class-wp-html-*.php') as $dependency)require_once $dependency;
require $wpTestRoot.'/wp-includes/kses.php';
require __DIR__.'/trb-site-studio/editor.php';
require __DIR__.'/trb-site-studio/portal.php';
require __DIR__.'/trb-site-studio/bundle.php';
require __DIR__.'/trb-site-studio/directory.php';
eval('namespace TRB\\Studio; function destination_site(){return true;} function admin_permission(){return \\current_user_can("manage_options");}');
$count=0;function check($v,$message){global $count;if(!$v){fwrite(STDERR,"FAIL: $message\n");exit(1);}++$count;echo "PASS: $message\n";}
$long = '<!-- wp:html --><style>'.str_repeat('.card{margin:0}',6000).'</style><h1>Long page</h1><p>Editable text</p><!-- /wp:html -->';
check(count(TRB\Studio\editable_items($long))===2,'Large real-world HTML blocks remain editable without PCRE stack exhaustion');
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
$posts=[10=>(object)['ID'=>10,'post_author'=>1,'post_type'=>'trb_release','post_status'=>'private','post_content'=>$raw,'meta'=>['_trb_contract_state'=>'signed','_trb_release_intake_phase'=>'complete','_trb_crm_workflow_status'=>'ready','_trb_release_pipeline_status'=>'approved']]];
$options=['trb_studio_distribution_states'=>['distributed']];
check(!TRB\Studio\portal_release_allowed($posts[10]),'Technical approval and commercial ready do not publish');$posts[10]->meta['_trb_crm_workflow_status']='distributed';check(TRB\Studio\portal_release_allowed($posts[10]),'Only configured distribution state publishes');$posts[10]->meta['inactive']=true;check(!TRB\Studio\portal_release_allowed($posts[10]),'Cancelled release excluded');unset($posts[10]->meta['inactive']);
check(TRB\Studio\valid_date('2028-02-29')&&!TRB\Studio\valid_date('2026-02-29'),'Calendar dates validated including leap years');
$s=['schema'=>1,'source'=>'artist.trbrec.com','complete'=>true,'artists'=>[['id'=>1,'name'=>'Artist','bio'=>'Bio','links'=>[]]],'releases'=>[['id'=>10,'artist_id'=>1,'title'=>'Release','date'=>'2026-11-01','presentation'=>'Story']]];
check(TRB\Studio\validate_snapshot($s),'Coherent snapshot accepted');$s['releases'][0]['artist_id']=9;check(!TRB\Studio\validate_snapshot($s),'Orphan release rejected');$s['releases'][0]['artist_id']=1;$s['artists'][]=$s['artists'][0];check(!TRB\Studio\validate_snapshot($s),'Duplicate source IDs rejected');
check(!TRB\Studio\editor_permission(['id'=>10]),'Anonymous edits rejected');
$allowed=true;$posts[10]->post_type='page';check(TRB\Studio\editor_permission(['id'=>10]),'Authorized page editing allowed');$posts[10]->post_type='trb_release';check(!TRB\Studio\editor_permission(['id'=>10]),'Managed catalog records cannot be overwritten by visual editor');
class Request implements ArrayAccess{function __construct(public $data,public $nonce){}function get_header($name){return $this->nonce;}function get_json_params(){return $this->data;}function offsetGet($k):mixed{return 10;}function offsetExists($k):bool{return true;}function offsetSet($k,$v):void{}function offsetUnset($k):void{}}
check(TRB\Studio\editor_save(new Request([],'bad'))->get_error_code()==='nonce_invalid','Invalid nonce rejected');check(TRB\Studio\editor_save(new Request(['version'=>'stale'],'valid'))->get_error_code()==='edit_conflict','Concurrent version conflict rejected before write');

check(TRB\Studio\valid_upc('888608943932')==='888608943932'&&TRB\Studio\valid_upc('888608943933')==='','UPC check digits protect exact catalog associations');
$options['trb_studio_distribution_states']=['processed'];$posts[10]->post_type='trb_release';
$posts[10]->meta['_trb_crm_workflow_status']='processed';unset($posts[10]->meta['_trb_release_intake_phase']);
check(TRB\Studio\portal_release_allowed($posts[10]),'Signed CRM-processed legacy releases do not require a newer intake marker');
$posts[10]->meta['_trb_contract_state']='contract_sent';check(!TRB\Studio\portal_release_allowed($posts[10]),'Commercial processing without a signed contract cannot publish');
check(TRB\Studio\rtf_text('{\rtf1\ansi{\fonttbl{\f0 Hidden;}}La musica \u232? qui.\par Seconda riga.}')==="La musica è qui.\nSeconda riga.",'RTF biography extracts Unicode and paragraphs and removes private metadata groups');
$root=TRB\Studio\bundle_root();@mkdir($root,0700,true);$gen='generation-'.str_repeat('a',32);@mkdir($root.'/'.$gen,0700);
$s=['schema'=>1,'source'=>'artist.trbrec.com','complete'=>true,'generated_at'=>gmdate('c'),'artists'=>[],'releases'=>[]];
file_put_contents($root.'/'.$gen.'/snapshot.json',json_encode($s));
file_put_contents($root.'/current.json',json_encode(['generation'=>$gen,'sha256'=>hash_file('sha256',$root.'/'.$gen.'/snapshot.json')]));
check(TRB\Studio\bundle_read('snapshot')===$s,'Private bundle reads only the hash-verified generation');
file_put_contents($root.'/'.$gen.'/snapshot.json','{}');check(is_wp_error(TRB\Studio\bundle_read('snapshot')),'A corrupt bundle preserves the previous public generation');
$s['generated_at']=gmdate('c',time()-10801);file_put_contents($root.'/'.$gen.'/snapshot.json',json_encode($s));
file_put_contents($root.'/current.json',json_encode(['generation'=>$gen,'sha256'=>hash_file('sha256',$root.'/'.$gen.'/snapshot.json')]));
check(TRB\Studio\bundle_read('snapshot')->get_error_code()==='bundle_stale','Expired export cannot republish old commercial approval');
file_put_contents($root.'/current.json',json_encode(['generation'=>'../elsewhere','sha256'=>'']));
check(is_wp_error(TRB\Studio\bundle_read('snapshot')),'Bundle pointer cannot escape the dedicated private directory');
unlink($root.'/current.json');unlink($root.'/'.$gen.'/snapshot.json');rmdir($root.'/'.$gen);rmdir($root);@rmdir(dirname($root));


$users[5]=(object)['ID'=>5,'display_name'=>'Nome pubblico','meta'=>[]];
check(TRB\Studio\portal_artist_name($users[5])==='Nome pubblico','Legacy TRB account uses its existing WordPress public name');
$users[5]->display_name='private@example.test';check(TRB\Studio\portal_artist_name($users[5])==='','An email address cannot become an artist public name');
$users[5]->meta['_trb_artist_artist_name']='Nome d’arte';check(TRB\Studio\portal_artist_name($users[5])==='Nome d’arte','Contractual artist name takes precedence over a legacy display name');
$posts[10]->post_title='Release';
$posts[10]->meta['_trb_release_tracks']=[['title'=>'Brano','isrc'=>'ITV242600003','primary_genre'=>'Pop','duration'=>'03:12','rights_reference'=>'PRIVATE','credits'=>['writers'=>[['tax_code'=>'PRIVATE','share'=>100]]]]];
$catalog=TRB\Studio\catalogue_payload($posts[10],'Artista','2026-11-01','888608943932');
check($catalog['tracks'][0]['isrc']==='ITV242600003'&&!str_contains(json_encode($catalog),'PRIVATE'),'Catalog payload exports public track metadata and excludes rights and administrative fields');


$options['trb_promo_takedowns']=['888608943932'];
$public=TRB\Studio\public_releases([['upc'=>'888608943932'],['upc'=>'824296527887'],['upc'=>'']]);
check(count($public)===2&&!in_array('888608943932',array_column($public,'upc'),true),'An approved portal release cannot override the authoritative catalog takedown manifest');
$options['trb_studio_directory']=['generated_at'=>gmdate('c'),'artists'=>[['id'=>1]],'releases'=>[['upc'=>'888608943932']]];
check(count(TRB\Studio\directory_data()['artists'])===1&&TRB\Studio\directory_data()['releases']===[],'Takedowns apply immediately while artist profiles remain available');

echo "TOTAL: $count passed\n";

// The complete editor must preserve identities, repeatable navigation and form workflows.
function wp_json_encode($v){return json_encode($v);}
function sanitize_text_field($s){return trim(strip_tags($s));}
function sanitize_textarea_field($s){return trim(strip_tags($s));}
function esc_url_raw($s,$protocols=[]){return preg_match('~^(?:https?://|mailto:|tel:)~',$s)?$s:'';}
function get_post_type($id){return $id===501?'attachment':($GLOBALS['posts'][$id]->post_type??false);}
function get_post_mime_type($id){return $id===501?'image/jpeg':'application/pdf';}
function wp_get_attachment_image_src($id,$size){return $id===501?['https://example.test/photo-new.jpg',1200,800]:false;}
$navigation='<nav><a href="/artisti/">Artisti</a><a href="/artisti/">Artisti</a></nav>';
$visual=TRB\Studio\studio_items($navigation);$anchors=array_values(array_filter($visual,fn($i)=>$i['tag']==='a'));
check(count($anchors)===2&&$anchors[0]['key']!==$anchors[1]['key'],'Repeated desktop and mobile navigation has distinct editable identities');
$new=TRB\Studio\studio_apply($navigation,[['key'=>$anchors[1]['key'],'html'=>'Roster','href'=>'/artisti/']]);
check(substr_count($new,'>Artisti</a>')===1&&substr_count($new,'>Roster</a>')===1,'Changing one repeated link leaves the other untouched');
$img='<img class="brand-logo" src="/old.jpg" srcset="/old-small.jpg 300w" alt="TRB" width="100" height="50">';
$images=array_values(TRB\Studio\studio_items($img));
$new=TRB\Studio\studio_apply($img,[['key'=>$images[0]['key'],'image_id'=>501,'alt'=>'Logo TRB']]);
check(!is_wp_error($new)&&str_contains($new,'photo-new.jpg')&&!str_contains($new,'srcset')&&str_contains($new,'class="brand-logo"'),'Media replacement preserves layout classes and removes stale responsive sources');
check(is_wp_error(TRB\Studio\studio_apply($img,[['key'=>$images[0]['key'],'image_id'=>99]])),'Non-image attachments cannot become public photos');
check(is_wp_error(TRB\Studio\studio_styles(['filter'=>'grayscale(1)']))&&is_wp_error(TRB\Studio\studio_styles(['background-color'=>'url(javascript:evil)'])),'Unrequested photo filters and executable styling are rejected');
$body='<p>Biografia</p>';$i=TRB\Studio\studio_items($body)[0];
$new=TRB\Studio\studio_apply($body,[['key'=>$i['key'],'html'=>'Musica <strong>nuova</strong><script>alert(1)</script><img onerror=evil src=x>']]);
check(!is_wp_error($new)&&!str_contains($new,'<script')&&!str_contains($new,'onerror')&&str_contains($new,'<strong>nuova</strong>'),'Complete editor sanitizes rich text with real WordPress KSES');
$blueprint=['fields'=>[['element'=>'input_text','attributes'=>['name'=>'artist','placeholder'=>'Nome'],'settings'=>['label'=>'Artista','validation_rules'=>['required'=>['value'=>true]],'conditional_logics'=>['status'=>true,'conditions'=>[['field'=>'type','value'=>'solo']]]]]],'submitButton'=>['element'=>'button','settings'=>['button_ui'=>['text'=>'Invia']]]];
$formItems=TRB\Studio\studio_form_items($blueprint);$label=array_values(array_filter($formItems,fn($i)=>$i['property']==='label'))[0];
$patched=TRB\Studio\studio_form_patch(json_encode($blueprint),[['key'=>$label['key'],'value'=>'Nome del progetto']]);$result=json_decode($patched,true);
check($result['fields'][0]['settings']['validation_rules']===$blueprint['fields'][0]['settings']['validation_rules']&&$result['fields'][0]['settings']['conditional_logics']===$blueprint['fields'][0]['settings']['conditional_logics'],'Editing form labels preserves required fields and conditional workflow');
check($result['fields'][0]['attributes']['name']==='artist'&&$result['fields'][0]['settings']['label']==='Nome del progetto','Form editing keeps the CRM field identity');
check(is_wp_error(TRB\Studio\studio_form_patch(json_encode($blueprint),[['key'=>'validation_rules','value'=>'false']])),'Form constraints are not writable through the text editor');
$options['trb_studio_override_artist_45']=['name'=>'Arkell','bio'=>'Correzione manuale','image_id'=>501];
$imported=['id'=>45,'name'=>'Nome importato','bio'=>'Bio nuova dal portale','image_id'=>1];
check(TRB\Studio\studio_effective('artist',$imported)['bio']==='Correzione manuale'&&TRB\Studio\studio_effective('artist',$imported)['image_id']===501,'Manual artist presentation survives a subsequent portal generation');
check(is_wp_error(TRB\Studio\studio_override_validate('artist',['contract_state'=>'signed'])),'Public editing cannot change contractual or administrative artist data');
echo "COMPLETE EDITOR TOTAL: $count passed\n";


// A misleading image MIME/header must not block the next usable official photograph.
function trb_artist_promo_local_photo($file){return $file['test_path']??'';}
function wp_get_image_mime($path){return 'image/jpeg';}
function wp_get_image_editor($path){return new stdClass();}
$good=tempnam(sys_get_temp_dir(),'trb-valid-photo-');$bad=tempnam(sys_get_temp_dir(),'trb-bad-photo-');
try{
 file_put_contents($good,base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAACAAIDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwC3RRRWZxn/2Q=='));
 file_put_contents($bad,"JPEG header without readable image dimensions");
 $users[99]=(object)['meta'=>['_trb_artist_private_files'=>[['group'=>'photo','test_path'=>$bad],['group'=>'photo','test_path'=>$good],['group'=>'photo','test_path'=>$good]]]];
 $photos=TRB\Studio\material_photos(99);
 check(count($photos)===1&&$photos[0]['hash']===hash_file('sha256',$good),'An unreadable primary photograph is excluded while the next genuine photo is retained');
 check(count(array_unique(array_column($photos,'hash')))===count($photos),'Duplicate source photographs cannot create a fake three-photo gallery');
}finally{unlink($good);unlink($bad);unset($users[99]);}
echo "PHOTO VALIDATION TOTAL: $count passed\n";

// Small genuine source photographs must remain exportable without enlargement.
$small=new class {public $calls=0;function get_size(){return ['width'=>800,'height'=>600];}function resize(...$a){++$this->calls;return new WP_Error('error_getting_dimensions');}};
check(TRB\Studio\public_image_resize($small)===true&&$small->calls===0,'Small official photographs bypass the unnecessary resize and retain their pixels');
$large=new class {public $args;function get_size(){return ['width'=>600,'height'=>2400];}function resize(...$a){$this->args=$a;return true;}};
check(TRB\Studio\public_image_resize($large)===true&&$large->args===[1200,1200,false],'Oversized portrait photographs still scale proportionally within the public limit');
$failed=new class {function get_size(){return ['width'=>2400,'height'=>1800];}function resize(...$a){return new WP_Error('resize_failed');}};
check(is_wp_error(TRB\Studio\public_image_resize($failed)),'Genuine resize failures are preserved rather than silently ignored');
