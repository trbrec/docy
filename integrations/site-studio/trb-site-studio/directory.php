<?php
namespace TRB\Studio;
if (!defined('ABSPATH')) exit;
function portal_get($path){
 $user=get_option('trb_studio_user');$password=get_option('trb_studio_password');
 if(!$user||!$password)return new \WP_Error('connection_required','Collegamento al portale non configurato.');
 $r=wp_safe_remote_get('https://artist.trbrec.com/wp-json/trb-studio/v1/'.$path,['timeout'=>45,'redirection'=>0,'limit_response_size'=>20*1024*1024,'headers'=>['Authorization'=>'Basic '.base64_encode($user.':'.$password),'Accept'=>'application/json']]);
 if(is_wp_error($r))return $r;
 $data=json_decode(wp_remote_retrieve_body($r),true);
 if(wp_remote_retrieve_response_code($r)!==200)return new \WP_Error('portal_response','Il portale non ha autorizzato o completato la lettura (HTTP '.wp_remote_retrieve_response_code($r).').');
 return is_array($data)?$data:new \WP_Error('portal_json','Risposta del portale non valida.');
}
function validate_snapshot($s){
 if(!is_array($s)||($s['schema']??0)!==1||($s['source']??'')!=='artist.trbrec.com'||($s['complete']??false)!==true||!isset($s['artists'],$s['releases'])||!is_array($s['artists'])||!is_array($s['releases']))return false;
 $ids=[];$release_ids=[];
 foreach($s['artists'] as $a){if(!is_int($a['id']??null)||$a['id']<1||isset($ids[$a['id']])||!is_string($a['name']??null)||!trim($a['name'])||!is_string($a['bio']??null)||!is_array($a['links']??null))return false;$ids[$a['id']]=true;}
 foreach($s['releases'] as $r){if(!is_int($r['id']??null)||$r['id']<1||isset($release_ids[$r['id']])||!isset($ids[$r['artist_id']??0])||!is_string($r['title']??null)||!trim($r['title'])||!valid_date($r['date']??'')||!is_string($r['presentation']??null))return false;$release_ids[$r['id']]=true;}
 return true;
}
function import_image($ref,$label,$cache){
 if(!$ref)return null;
 if(!in_array($ref['kind']??'',['artist','release'],true)||!is_int($ref['id']??null)||!preg_match('/^[a-f0-9]{64}$/',$ref['hash']??''))return new \WP_Error('asset_reference','Riferimento immagine non valido.');
 $key=$ref['kind'].':'.$ref['id'].':'.$ref['hash'];
 if(isset($cache[$key])&&get_post_type($cache[$key])==='attachment'&&get_attached_file($cache[$key])&&is_file(get_attached_file($cache[$key])))return ['key'=>$key,'id'=>$cache[$key]];
 $data=portal_get('asset/'.$ref['kind'].'/'.$ref['id']);if(is_wp_error($data))return $data;
 $bytes=base64_decode($data['base64']??'',true);
 if(!$bytes||strlen($bytes)>5*1024*1024||!hash_equals($ref['hash'],(string)($data['hash']??''))||!hash_equals(hash('sha256',$bytes),(string)($data['bytes_sha256']??'')))return new \WP_Error('asset_integrity','Il materiale è cambiato durante la sincronizzazione.');
 $image=getimagesizefromstring($bytes);if(!$image||$image['mime']!=='image/jpeg')return new \WP_Error('asset_type','Immagine non valida.');
 $upload=wp_upload_bits('trb-'.$ref['kind'].'-'.$ref['id'].'-'.substr($ref['hash'],0,12).'.jpg',null,$bytes);if(!empty($upload['error']))return new \WP_Error('upload_error',$upload['error']);
 $id=wp_insert_attachment(['post_title'=>sanitize_text_field($label),'post_mime_type'=>'image/jpeg','post_status'=>'inherit'],$upload['file'],0,true);
 if(is_wp_error($id)){wp_delete_file($upload['file']);return $id;}
 require_once ABSPATH.'wp-admin/includes/image.php';
 wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$upload['file']));update_post_meta($id,'_wp_attachment_image_alt',sanitize_text_field($label));
 return ['key'=>$key,'id'=>$id];
}
function sync_directory(){
 if(!destination_site())return new \WP_Error('wrong_site','Sincronizzazione prevista solo su new1.trbrec.com.');
 // add_option is an atomic lock; a terminated run expires after 15 minutes.
 $lock=get_option('trb_studio_sync_lock');if($lock && time()-(int)$lock<900)return new \WP_Error('sync_running','Sincronizzazione già in corso.');
 if($lock)delete_option('trb_studio_sync_lock');if(!add_option('trb_studio_sync_lock',time(),'','no'))return new \WP_Error('sync_running','Sincronizzazione già in corso.');
 try{
  $snapshot=portal_get('snapshot');if(is_wp_error($snapshot))return sync_failed($snapshot);
  if(!validate_snapshot($snapshot))return sync_failed(new \WP_Error('snapshot_invalid','Importazione sospesa: dati incompleti o incoerenti.'));
  $old=get_option('trb_studio_directory',[]);$cache=get_option('trb_studio_images',[]);
  foreach($snapshot['artists'] as &$a){$image=import_image($a['photo']??null,$a['name'],$cache);if(is_wp_error($image))return sync_failed($image);$a['image_id']=$image['id']??0;if($image){$cache[$image['key']]=$image['id'];update_option('trb_studio_images',$cache,false);}unset($a['photo']);}unset($a);
  foreach($snapshot['releases'] as &$r){$image=import_image($r['cover']??null,$r['title'],$cache);if(is_wp_error($image))return sync_failed($image);$r['image_id']=$image['id']??0;if($image){$cache[$image['key']]=$image['id'];update_option('trb_studio_images',$cache,false);}unset($r['cover']);}unset($r);
  // Publish the entire validated generation in one option update. Failed batches preserve the prior directory.
  update_option('trb_studio_directory_previous',$old,false);update_option('trb_studio_directory',$snapshot,false);
  update_option('trb_studio_last_sync',['ok'=>true,'at'=>gmdate('c'),'artists'=>count($snapshot['artists']),'releases'=>count($snapshot['releases']),'warnings'=>$snapshot['warnings']??[]],false);
  if(function_exists('sg_cachepress_purge_cache'))sg_cachepress_purge_cache();return true;
 }finally{delete_option('trb_studio_sync_lock');}
}
function sync_failed($e){update_option('trb_studio_last_sync',['ok'=>false,'at'=>gmdate('c'),'code'=>$e->get_error_code(),'message'=>$e->get_error_message()],false);return $e;}
function public_image($id,$label){return $id?wp_get_attachment_image($id,'large',false,['alt'=>$label,'loading'=>'lazy','class'=>'trb-directory-image']):'<div class="trb-directory-image trb-directory-monogram" aria-hidden="true">'.esc_html(function_exists('mb_substr')?mb_substr($label,0,1):substr($label,0,1)).'</div>';}
function directory_data(){return get_option('trb_studio_directory',['artists'=>[],'releases'=>[]]);}
function plain_paragraphs($text){return wpautop(esc_html($text));}
function release_card($r,$artists,$prefix='trb-release-'){
 $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$r['date'],new \DateTimeZone('Europe/Rome'));
 $future=$r['date']>(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Rome')))->format('Y-m-d');
 return '<article class="trb-release-profile" id="'.esc_attr($prefix).(int)$r['id'].'">'.public_image($r['image_id'],$r['title']).'<div><p class="trb-directory-kicker">'.($future?'In uscita':'Pubblicazione').' · <time datetime="'.esc_attr($r['date']).'">'.esc_html($date->format('d/m/Y')).'</time></p><h3>'.esc_html($r['title']).'</h3><p>'.esc_html($artists[$r['artist_id']]['name']??'').'</p>'.plain_paragraphs($r['presentation']).'</div></article>';
}
function roster(){
 $d=directory_data();$artists=$d['artists'];usort($artists,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
 if(!$artists)return admin_permission()?'<p class="trb-studio-admin-note">Directory non ancora sincronizzata. Configura TRB Site Studio prima di pubblicare questa pagina.</p>':'';
 $byid=array_column($artists,null,'id');$out='<section class="trb-directory"><h2>Gli artisti TRB rec</h2><nav class="trb-artist-index" aria-label="Indice degli artisti">';
 foreach($artists as $a)$out.='<a href="#trb-artista-'.$a['id'].'">'.esc_html($a['name']).'</a>';$out.='</nav>';
 foreach($artists as $a){$out.='<article class="trb-artist-profile" id="trb-artista-'.$a['id'].'"><div class="trb-artist-identity">'.public_image($a['image_id'],$a['name']).'</div><div><h3>'.esc_html($a['name']).'</h3>'.plain_paragraphs($a['bio']).'<nav class="trb-artist-links" aria-label="Profili ufficiali di '.esc_attr($a['name']).'">';foreach($a['links'] as $link){$url=esc_url($link['url'],['https']);if($url)$out.='<a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.esc_html($link['label']).'</a>';}$out.='</nav>';
  $releases=array_values(array_filter($d['releases'],fn($r)=>$r['artist_id']===$a['id']));usort($releases,fn($a,$b)=>strcmp($b['date'],$a['date']));
  if($releases){$out.='<details class="trb-artist-discography"><summary>Release e presentazioni ('.count($releases).')</summary>';foreach($releases as $r)$out.=release_card($r,$byid);$out.='</details>';}
  $out.='</div></article>';
 }
 return $out.'</section>';
}
function coming_soon(){
 $d=directory_data();$today=(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Rome')))->format('Y-m-d');$releases=array_values(array_filter($d['releases'],fn($r)=>$r['date']>$today));
 if(!$releases)return '';
 usort($releases,fn($a,$b)=>strcmp($a['date'],$b['date']));$out='<section class="trb-directory trb-coming-soon"><p class="trb-directory-kicker">LE PROSSIME USCITE</p><h2>Coming soon</h2>';
 $artists=array_column($d['artists'],null,'id');foreach($releases as $r)$out.=release_card($r,$artists,'trb-coming-');return $out.'</section>';
}
add_shortcode('trb_public_roster',__NAMESPACE__.'\\roster');add_shortcode('trb_public_coming_soon',__NAMESPACE__.'\\coming_soon');
add_action('wp_enqueue_scripts',function(){if(destination_site())wp_enqueue_style('trb-directory',plugins_url('directory.css',__FILE__),[],VERSION);});
