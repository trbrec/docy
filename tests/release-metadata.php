<?php
/** Pure application tests: synthetic metadata only, no WordPress, DB or network. */
define('ABSPATH', __DIR__ . '/');
function add_action(...$args) {}
function add_filter(...$args) {}
function add_shortcode(...$args) {}
function wp_date($format) { return date($format); }
function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$value)); }
function absint($value) { return abs((int)$value); }
function get_template_directory() { return dirname(__DIR__); }
function esc_attr($value) { return htmlspecialchars((string)$value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES); }
$meta = array(); $writes = array();
function get_post_meta($id, $key, $single=true) { global $meta; return $meta[$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { global $meta, $writes; $meta[$id][$key]=$value; $writes[]=array($id,$key); }
class WP_Error { public function __construct(public $code, public $message) {} }
class WP_User { public function exists() { return false; } }
function get_userdata($id) { return new WP_User(); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_json_encode($value) { return json_encode($value); }
function wp_upload_dir() { return array('basedir'=>sys_get_temp_dir().'/trb-metadata-test-'.getmypid()); }
function wp_mkdir_p($dir) { return is_dir($dir) || mkdir($dir,0700,true); }
$posts=array();
function get_post($id) { global $posts; return $posts[$id] ?? null; }
function get_posts($args) {
	global $posts;
	$rows=array_filter($posts,static function($post) use ($args) { return !isset($args['meta_key']) || get_post_meta($post->ID,$args['meta_key'],true) === $args['meta_value']; });
	return isset($args['fields']) ? array_keys($rows) : array_values($rows);
}
function wp_insert_post($args,$return_error=false) {
	global $posts,$meta; $id=count($posts)+1000;
	$posts[$id]=(object)array('ID'=>$id,'post_title'=>$args['post_title'],'post_type'=>$args['post_type'],'post_status'=>$args['post_status'],'post_author'=>$args['post_author']);
	$meta[$id]=$args['meta_input'];return $id;
}
function wp_update_post($args) { global $posts; if(isset($args['post_title'])) $posts[$args['ID']]->post_title=$args['post_title']; }
require dirname(__DIR__) . '/inc/trb-artist-portal.php';
$checks=0;
function check($condition, $message) { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
check(count(trb_release_metadata_options()['countries'])===249, 'Country domain incomplete');
check(count(trb_release_metadata_options()['languages'])===184, 'Language domain incomplete');
check(trb_release_metadata_code('it','countries')==='IT', 'Country code normalization');
check(trb_release_metadata_code('IT','languages')==='it', 'Language code normalization');
check(trb_release_metadata_code('ZZ','countries')==='', 'Unknown country accepted');
check(trb_release_metadata_code('Italiano','languages')==='', 'Free text language accepted');
$post=array('trb_release_metadata_language'=>'it','trb_release_version'=>'Live','trb_tracks'=>array(array('audio_style'=>'Vocal','vocal_language'=>'it','recording_year'=>(int)date('Y'),'recording_country'=>'IT')));
check(!trb_release_metadata_errors($post), 'Valid Vocal rejected');
$bad=$post;unset($bad['trb_tracks'][0]['vocal_language']);check((bool)trb_release_metadata_errors($bad),'Vocal language missing accepted');
$bad['trb_tracks'][0]['audio_style']='Instrumental';check(!trb_release_metadata_errors($bad),'Instrumental needs a vocal language');
$bad=$post;$bad['trb_tracks'][0]['recording_year']=date('Y')+1;check((bool)trb_release_metadata_errors($bad),'Future recording accepted');
$bad=$post;$bad['trb_tracks'][0]['recording_country']='Italia';check((bool)trb_release_metadata_errors($bad),'Free text country accepted');
$bad=$post;$bad['trb_tracks'][0]['recording_year']=array(2026);check((bool)trb_release_metadata_errors($bad),'Array year accepted');
check(trb_release_audio_style(array('advisory'=>'no_lyrics'))==='Instrumental','Legacy instrumental fallback');
check(trb_release_audio_style(array('audio_style'=>'Vocal','advisory'=>'no_lyrics'))==='Vocal','Explicit style lost');
$legacy=array('title'=>'Old','signature_digest'=>'untouched','tracks'=>array(array('title'=>'Old','advisory'=>'no_lyrics')));
$meta[42]=$legacy;$writes=array();trb_release_metadata_options();trb_release_metadata_errors($post);check($meta[42]===$legacy&&!$writes,'Read validation mutated historical metadata');
trb_release_metadata_store(99,$post);check($meta[99]['_trb_release_metadata_language']==='it','Release language not persisted');check($meta[99]['_trb_release_version']==='Live','Version not persisted');check(!isset($meta[42]['_trb_release_metadata_schema']),'Historical schema migrated');
$track=array('title'=>'Synthetic track','duration'=>'03:10','advisory'=>'non_explicit','audio_style'=>'Vocal','vocal_language'=>'it','recording_year'=>2026,'recording_country'=>'IT','primary_genre'=>'Rock','secondary_genre'=>'Classic Rock','content_nature'=>'original','rights_basis'=>'owned','audio_status'=>'mastered','credits'=>array('writers'=>array(array('name'=>'Synthetic Writer','roles'=>array('Composer','Lyricist'))),'performers'=>array(array('name'=>'Synthetic Singer','role'=>'Vocals')),'engineering'=>array(array('name'=>'Synthetic Producer','role'=>'Producer'))));
check(!trb_symphonic_credit_errors(array($track)),'Existing valid credits rejected');
$san=trb_portal_sanitize_release_tracks(array($track),true,true);check(count($san)===1,'Valid synthetic track dropped');
check($san[0]['audio_style']==='Vocal'&&$san[0]['vocal_language']==='it'&&$san[0]['recording_country']==='IT'&&$san[0]['recording_year']===2026,'Metadata lost during sanitization');
check($san[0]['ai_usage']==='None'&&$san[0]['ai_usage_provenance']==='owner_declared_default_2026-10-18','Owner default provenance missing');
$track['audio_style']='Instrumental';$track['advisory']='no_lyrics';$track['credits']['writers'][0]['roles']=array('Composer');$track['credits']['performers'][0]['role']='Piano';
check(!trb_symphonic_credit_errors(array($track)),'Instrumental requires lyricist or vocals');
$san=trb_portal_sanitize_release_tracks(array($track),true,true);check(count($san)===1&&$san[0]['vocal_language']===null,'Instrumental retained stale vocal language');
$track['audio_style']='Vocal';check((bool)trb_symphonic_credit_errors(array($track)),'Vocal bypasses roles with no_lyrics advisory');
$preserved=trb_release_preserve_ai_declarations(array(array('title'=>'Synthetic A','ai_usage'=>'None')),array(array('title'=>'Synthetic A','ai_usage'=>'Some')));check($preserved[0]['ai_usage']==='Some','Specific declaration erased on retry');
$preserved=trb_release_preserve_ai_declarations(array(array('title'=>'Synthetic B','ai_usage'=>'None')),array(array('title'=>'Synthetic A','ai_usage'=>'Some')));check($preserved[0]['ai_usage']==='None','Specific declaration copied to unrelated replacement');
$preserved=trb_release_preserve_ai_declarations(array(array('title'=>'Synthetic B','ai_usage'=>'None'),array('title'=>'Synthetic A','ai_usage'=>'None')),array(array('title'=>'Synthetic A','ai_usage'=>'Some'),array('title'=>'Synthetic B','ai_usage'=>'None')));check($preserved[0]['ai_usage']==='None'&&$preserved[1]['ai_usage']==='Some','Reordering transferred declaration by index');
check((bool)trb_release_ai_declaration_conflicts(array(array('title'=>'Duplicate')),array(array('title'=>'Duplicate','ai_usage'=>'Some'),array('title'=>'Duplicate','ai_usage'=>'All'))),'Ambiguous specific declarations not blocked');
check((bool)trb_release_ai_declaration_conflicts(array(array('title'=>'Duplicate'),array('title'=>'Duplicate')),array(array('title'=>'Duplicate','ai_usage'=>'Some'))),'One disclosure copied to multiple new tracks');
check($meta[42]===$legacy,'Historical release changed');
$track['audio_style']='Vocal';$track['advisory']='non_explicit';$track['credits']['writers'][0]['roles']=array('Composer','Lyricist');$track['credits']['performers'][0]['role']='Vocals';
$intake=$post;$intake['trb_release_title']='Synthetic release';$intake['trb_release_type']='single';$intake['trb_tracks']=array($track);
$bad=$intake;unset($bad['trb_release_metadata_language']);$result=trb_intake_record(1,'11111111-1111-4111-8111-111111111111',$bad);check(is_wp_error($result)&&$result->code==='invalid_metadata'&&!$posts,'Invalid new intake created a receipt');
$id=trb_intake_record(1,'11111111-1111-4111-8111-111111111111',$intake);check(is_int($id)&&count($posts)===1,'Valid intake not recorded');
check($meta[$id]['_trb_release_metadata_language']==='it'&&$meta[$id]['_trb_release_tracks'][0]['recording_country']==='IT','Intake lost metadata');
$meta[$id]['_trb_release_tracks'][0]['ai_usage']='Some';$intake['trb_release_metadata_language']='en';$intake['trb_tracks'][0]['recording_country']='GB';$intake['trb_tracks'][0]['audio_style']='Instrumental';$intake['trb_tracks'][0]['advisory']='no_lyrics';
$retried=trb_intake_record(1,'11111111-1111-4111-8111-111111111111',$intake);check($retried===$id&&count($posts)===1,'Retry duplicated receipt');
check($meta[$id]['_trb_release_metadata_language']==='en'&&$meta[$id]['_trb_release_tracks'][0]['recording_country']==='GB'&&$meta[$id]['_trb_release_tracks'][0]['vocal_language']===null,'Retry did not persist explicit corrections');
check($meta[$id]['_trb_release_tracks'][0]['ai_usage']==='Some','Retry erased specific AI declaration');
$meta[$id]['_trb_release_tracks'][]=$meta[$id]['_trb_release_tracks'][0];$before_ambiguous=$meta[$id];$ambiguous=trb_intake_record(1,'11111111-1111-4111-8111-111111111111',$intake);check(is_wp_error($ambiguous)&&$ambiguous->code==='metadata_association_conflict'&&$meta[$id]===$before_ambiguous,'Ambiguous retry overwrote a specific declaration');array_pop($meta[$id]['_trb_release_tracks']);
$meta[$id]['_trb_release_intake_phase']='complete';$meta[$id]['signature_digest']='synthetic immutable digest';$before=$meta[$id];$writes=array();unset($intake['trb_release_metadata_language']);unset($intake['trb_tracks'][0]['audio_style'],$intake['trb_tracks'][0]['vocal_language'],$intake['trb_tracks'][0]['recording_year'],$intake['trb_tracks'][0]['recording_country']);
$completed=trb_intake_record(1,'11111111-1111-4111-8111-111111111111',$intake);check($completed===$id&&$meta[$id]===$before&&!$writes,'Completed legacy receipt mutated or required new fields');
echo json_encode(array('passed'=>true,'checks'=>$checks,'no_network'=>true,'no_database'=>true),JSON_PRETTY_PRINT)."\n";
