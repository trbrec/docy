<?php
namespace TRB\Studio;
if (!defined('ABSPATH')) exit;
function portal_get($path){
 if(get_option('trb_studio_transport')==='private-bundle')return bundle_read($path);
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
 $ids=[];$release_ids=[];$upcs=[];
 foreach($s['artists'] as $a){if(!is_int($a['id']??null)||$a['id']<1||isset($ids[$a['id']])||!is_string($a['name']??null)||!trim($a['name'])||!is_string($a['bio']??null)||!is_array($a['links']??null))return false;$ids[$a['id']]=true;}
 foreach($s['releases'] as $r){if(!is_int($r['id']??null)||$r['id']<1||isset($release_ids[$r['id']])||!isset($ids[$r['artist_id']??0])||!is_string($r['title']??null)||!trim($r['title'])||!valid_date($r['date']??'')||!is_string($r['presentation']??null))return false;$release_ids[$r['id']]=true;
  if(!empty($r['upc'])){if(valid_upc($r['upc'])!==$r['upc']||isset($upcs[$r['upc']]))return false;$upcs[$r['upc']]=true;}
 }
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
  $before=count($snapshot['releases']);$snapshot['releases']=public_releases($snapshot['releases']);
  if(count($snapshot['releases'])!==$before)$snapshot['warnings'][]='Release escluse dal manifesto rimozioni: '.($before-count($snapshot['releases']));
  $old=get_option('trb_studio_directory',[]);$cache=get_option('trb_studio_images',[]);
  foreach($snapshot['artists'] as &$a){$image=import_image($a['photo']??null,$a['name'],$cache);if(is_wp_error($image))return sync_failed($image);$a['image_id']=$image['id']??0;if($image){$cache[$image['key']]=$image['id'];update_option('trb_studio_images',$cache,false);}unset($a['photo']);}unset($a);
  foreach($snapshot['releases'] as &$r){$image=import_image($r['cover']??null,$r['title'],$cache);if(is_wp_error($image))return sync_failed($image);$r['image_id']=$image['id']??0;if($image){$cache[$image['key']]=$image['id'];update_option('trb_studio_images',$cache,false);}unset($r['cover']);}unset($r);
  foreach($snapshot['artists'] as &$a){
   $a['catalog_term_id']=0;
   // Only contractual artist names may resolve the existing immutable artist key.
   if(($a['name_source']??'')!=='artist_profile')continue;
   $key=class_exists('Normalizer')?\Normalizer::normalize($a['name'],\Normalizer::FORM_C):$a['name'];
   $key=preg_replace('/\s+/u',' ',trim($key));$key=function_exists('mb_strtolower')?mb_strtolower($key,'UTF-8'):strtolower($key);
   $terms=get_terms(['taxonomy'=>'trb_catalog_artist','hide_empty'=>false,'number'=>2,'meta_query'=>[['key'=>'_trb_promo_artist_key','value'=>$key]]]);
   if(!is_wp_error($terms)&&count($terms)===1&&$terms[0]->name===$a['name'])$a['catalog_term_id']=(int)$terms[0]->term_id;
  }unset($a);
  foreach($snapshot['releases'] as &$r){
   $r['catalog_id']=0;$r['links']=[];
   if(empty($r['upc']))continue;
   $existing=get_posts(['post_type'=>'trb_release','post_status'=>['publish','future','draft','private','pending','trash'],'posts_per_page'=>2,'fields'=>'ids','meta_key'=>'_trb_promo_upc','meta_value'=>$r['upc']]);
   if(count($existing)>1)return sync_failed(new \WP_Error('upc_ambiguous','UPC duplicato: collegamento sospeso.'));
   if($existing){$binding=(int)get_post_meta($existing[0],'_trb_studio_portal_release_id',true);if($binding&&$binding!==$r['id'])return sync_failed(new \WP_Error('source_identity_conflict','Identità della release da verificare.'));}
   $created=false;
   if(!empty($r['catalog'])&&is_callable(['TRB_Promo_Ecosystem','sync_from_provider'])){
    $result=\TRB_Promo_Ecosystem::sync_from_provider($r['catalog']);
    if(is_wp_error($result))return sync_failed($result);$created=$result==='created';
   }
   unset($r['catalog']);
   $matches=get_posts(['post_type'=>'trb_release','post_status'=>['publish','future'],'posts_per_page'=>2,'fields'=>'ids','meta_key'=>'_trb_promo_upc','meta_value'=>$r['upc']]);
   if(count($matches)!==1){$snapshot['warnings'][]='Collegamento UPC non disponibile: '.$r['upc'];continue;}
   $r['catalog_id']=(int)$matches[0];$bound=(int)get_post_meta($r['catalog_id'],'_trb_studio_portal_release_id',true);
   if($bound&&$bound!==$r['id'])return sync_failed(new \WP_Error('source_identity_conflict','Identità della release da verificare.'));
   update_post_meta($r['catalog_id'],'_trb_studio_portal_release_id',$r['id']);
   if($created)update_post_meta($r['catalog_id'],'_trb_studio_created_catalog','1');
   $url=get_permalink($r['catalog_id']);$slug=get_post_field('post_name',$r['catalog_id']);
   $r['links']=get_post_status($r['catalog_id'])==='publish'?[['label'=>'Scheda release','url'=>$url],['label'=>'Smartlink','url'=>home_url('/smartlink/'.$slug.'/')],['label'=>'Press kit','url'=>trailingslashit($url).'press-kit/']]:[];
   $meta=get_post_meta($r['catalog_id'],'_trb_promo_release',true);
   foreach($snapshot['artists'] as &$artist)if($artist['id']===$r['artist_id']&&($meta['artist']??'')===$artist['name'])$artist['catalog_term_id']=(int)($meta['artist_term_id']??0);unset($artist);
  }unset($r);
  // Publish the entire validated generation in one option update. Failed batches preserve the prior directory.
  update_option('trb_studio_directory_previous',$old,false);update_option('trb_studio_directory',$snapshot,false);
  $activeIds=array_column($snapshot['releases'],'id');
  $owned=get_posts(['post_type'=>'trb_release','post_status'=>['publish','future'],'posts_per_page'=>2001,'fields'=>'ids','meta_key'=>'_trb_studio_created_catalog','meta_value'=>'1']);
  foreach($owned as $id)if(!in_array((int)get_post_meta($id,'_trb_studio_portal_release_id',true),$activeIds,true))wp_update_post(['ID'=>$id,'post_status'=>'draft']);
  update_option('trb_studio_last_sync',['ok'=>true,'at'=>gmdate('c'),'artists'=>count($snapshot['artists']),'releases'=>count($snapshot['releases']),'warnings'=>$snapshot['warnings']??[]],false);
  if(function_exists('sg_cachepress_purge_cache'))sg_cachepress_purge_cache();return true;
 }finally{delete_option('trb_studio_sync_lock');}
}
function sync_failed($e){update_option('trb_studio_last_sync',['ok'=>false,'at'=>gmdate('c'),'code'=>$e->get_error_code(),'message'=>$e->get_error_message()],false);return $e;}
function public_image($id,$label){return $id?wp_get_attachment_image($id,'large',false,['alt'=>$label,'loading'=>'lazy','class'=>'trb-directory-image']):'<div class="trb-directory-image trb-directory-monogram" aria-hidden="true">'.esc_html(function_exists('mb_substr')?mb_substr($label,0,1):substr($label,0,1)).'</div>';}
function public_releases($releases){
 $removed=(array)get_option('trb_promo_takedowns',[]);
 return array_values(array_filter($releases,fn($r)=>!in_array($r['upc']??'',$removed,true)));
}
function directory_data(){
 $d=get_option('trb_studio_directory',['artists'=>[],'releases'=>[]]);
 $d['releases']=public_releases($d['releases']);
 $at=strtotime($d['generated_at']??'');if(!$at||time()-$at>10800)$d['releases']=[];
 return $d;
}
function release_links($r){
 $out='<nav class="trb-artist-links" aria-label="Materiali della release">';
 foreach($r['links']??[] as $link){$url=esc_url($link['url'],['https']);if($url)$out.='<a href="'.$url.'">'.esc_html($link['label']).'</a>';}
 return $out.'</nav>';
}

function plain_paragraphs($text){return wpautop(esc_html($text));}
/** A display-only excerpt: the original biography remains available in full. */
function artist_bio_preview($text,$name=''){
 $text=trim(wp_strip_all_tags($text));
 $lines=preg_split('/\R/u',$text);
 if(count($lines)>1){
  $first=trim($lines[0]);$same=function_exists('mb_strtolower')?mb_strtolower($first,'UTF-8')===mb_strtolower(trim($name),'UTF-8'):strcasecmp($first,trim($name))===0;
  if($same||preg_match('/^Bio\s*[–—-]/u',$first))array_shift($lines);
 }
 $plain=trim(preg_replace('/\s+/u',' ',implode(' ',$lines)));
 if(!preg_match('/^(.{0,239})(.)/us',$plain,$match))return $plain;
 $excerpt=preg_replace('/\s+\S*$/u','',$match[1]);
 return rtrim($excerpt?:$match[1]).'…';
}
function artist_biography($artist){
 $bio=trim($artist['bio']);
 if($bio==='')return '<div class="trb-artist-bio"><p class="trb-artist-bio-preview trb-artist-pending">Presentazione in aggiornamento.</p><p class="trb-artist-bio-unavailable">Biografia in preparazione</p></div>';
 $preview=artist_bio_preview($bio,$artist['name']);
 return '<div class="trb-artist-bio"><p class="trb-artist-bio-preview">'.esc_html($preview).'</p><details class="trb-artist-biography"><summary><span class="trb-bio-closed">Leggi la biografia</span><span class="trb-bio-open">Riduci biografia</span><span class="screen-reader-text"> di '.esc_html($artist['name']).'</span></summary><div class="trb-artist-bio-full">'.plain_paragraphs($bio).'</div></details></div>';
}
function release_card($r,$artists,$prefix='trb-release-'){
 $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$r['date'],new \DateTimeZone('Europe/Rome'));
 $future=$r['date']>(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Rome')))->format('Y-m-d');
 return '<article class="trb-release-profile" id="'.esc_attr($prefix.($r['card_key']??$r['id'])).'">'.release_image($r).'<div><p class="trb-directory-kicker">'.($future?'In uscita':'Pubblicazione').' · <time datetime="'.esc_attr($r['date']).'">'.esc_html($date->format('d/m/Y')).'</time></p><h3>'.esc_html($r['title']).'</h3><p>'.esc_html($artists[$r['artist_id']]['name']??'').'</p>'.plain_paragraphs($r['presentation']).release_links($r).'</div></article>';
}
function roster(){
 $d=directory_data();$artists=$d['artists'];usort($artists,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
 if(!$artists)return admin_permission()?'<p class="trb-studio-admin-note">Directory non ancora sincronizzata. Configura TRB Site Studio prima di pubblicare questa pagina.</p>':'';
 $byid=array_column($artists,null,'id');$out='<section class="trb-directory trb-directory-roster"><h2>Gli artisti TRB rec</h2><details class="trb-roster-index"><summary>Trova un artista <span>'.count($artists).'</span></summary><nav class="trb-artist-index" aria-label="Indice degli artisti">';
 foreach($artists as $a)$out.='<a href="#trb-artista-'.$a['id'].'">'.esc_html($a['name']).'</a>';$out.='</nav></details>';
 foreach($artists as $a){$out.='<article class="trb-artist-profile" id="trb-artista-'.$a['id'].'"><div class="trb-artist-identity">'.public_image($a['image_id'],$a['name']).'</div><div class="trb-artist-content"><h3>'.esc_html($a['name']).'</h3>'.artist_biography($a).'<div class="trb-artist-actions">';
  if($a['links']){$out.='<details class="trb-artist-socials"><summary>Profili ufficiali<span class="screen-reader-text"> di '.esc_html($a['name']).'</span></summary><nav class="trb-artist-links" aria-label="Profili ufficiali di '.esc_attr($a['name']).'">';foreach($a['links'] as $link){$url=esc_url($link['url'],['https']);if($url)$out.='<a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.esc_html($link['label']).'</a>';}$out.='</nav></details>';}
  $releases=artist_releases($a,$d);usort($releases,fn($a,$b)=>strcmp($b['date'],$a['date']));
  if($releases){$out.='<details class="trb-artist-discography"><summary>Release <span>('.count($releases).')</span><span class="screen-reader-text"> e presentazioni di '.esc_html($a['name']).'</span></summary>';foreach($releases as $r)$out.=release_card($r,$byid);$out.='</details>';}
  $out.='</div></div></article>';
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

function release_image($r){
 if(!empty($r['image_id']))return public_image($r['image_id'],$r['title']);
 $url=esc_url($r['cover_url']??'', ['https']);
 if($url&&!wp_parse_url($url,PHP_URL_USER)&&!wp_parse_url($url,PHP_URL_PASS)&&!in_array(wp_parse_url($url,PHP_URL_HOST),['webdav.pcloud.com','ewebdav.pcloud.com'],true))return '<img class="trb-directory-image" src="'.$url.'" alt="'.esc_attr('Copertina '.$r['title']).'" width="600" height="600" loading="lazy" decoding="async">';
 return public_image(0,$r['title']);
}
function artist_releases($artist,$d){
 $list=array_values(array_filter($d['releases'],fn($r)=>$r['artist_id']===$artist['id']));$upcs=array_column($list,'upc');
 if(empty($artist['catalog_term_id'])||!class_exists('TRB_Promo_Ecosystem'))return $list;
 $ids=get_posts(['post_type'=>'trb_release','post_status'=>'publish','posts_per_page'=>500,'fields'=>'ids','tax_query'=>[['taxonomy'=>'trb_catalog_artist','field'=>'term_id','terms'=>$artist['catalog_term_id']]]]);
 foreach($ids as $id){
  $r=\TRB_Promo_Ecosystem::data($id);
  if(empty($r['upc'])||in_array($r['upc'],$upcs,true)||($r['state']??'')!=='published'||!valid_date($r['date']??'')||!in_array($r['label']??'',\TRB_Promo_Ecosystem::LABELS,true))continue;
  $assets=apply_filters('trb_promo_release_assets',[],$r,$id);$manual=get_post_meta($id,'_trb_promo_assets',true);if(is_array($manual))$assets=array_merge($assets,$manual);
  $url=get_permalink($id);$slug=get_post_field('post_name',$id);
  $list[]=['id'=>$id,'card_key'=>'catalog-'.$id,'artist_id'=>$artist['id'],'title'=>$r['title'],'date'=>$r['date'],'presentation'=>wp_strip_all_tags($assets['press_text']??''),'image_id'=>(int)($assets['cover_id']??0),'cover_url'=>$assets['cover_url']??'','upc'=>$r['upc'],'links'=>[['label'=>'Scheda release','url'=>$url],['label'=>'Smartlink','url'=>home_url('/smartlink/'.$slug.'/')],['label'=>'Press kit','url'=>trailingslashit($url).'press-kit/']]];
 }
 return $list;
}
add_filter('trb_promo_release_assets',function($assets,$release,$id){
 if(!destination_site())return $assets;$d=directory_data();
 foreach($d['releases'] as $r)if(($r['catalog_id']??0)===(int)$id){
  if(empty($assets['cover_id'])&&empty($assets['cover_url'])&&!empty($r['image_id']))$assets['cover_id']=$r['image_id'];
  if(empty($assets['press_text']))$assets['press_text']=plain_paragraphs($r['presentation']);
  break;
 }
 return $assets;
},20,3);
add_filter('trb_promo_artist_profile',function($profile,$term){
 if(!destination_site())return $profile;$d=directory_data();
 foreach($d['artists'] as $a)if(!empty($a['catalog_term_id'])&&$a['catalog_term_id']===(int)$term->term_id){
  if(empty($profile['bio']))$profile['bio']=plain_paragraphs($a['bio']);
  if(empty($profile['photo_id']))$profile['photo_id']=$a['image_id'];
  if(empty($profile['links']))$profile['links']=$a['links'];
  break;
 }
 return $profile;
},20,2);
