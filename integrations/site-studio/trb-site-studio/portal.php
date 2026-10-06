<?php
namespace TRB\Studio;
if (!defined('ABSPATH')) exit;
function portal_artist_allowed($user){
 if(!$user||!function_exists('trb_portal_user_profile')||trb_portal_user_profile($user)!=='trb')return false;
 if(function_exists('trb_portal_is_release_qa_account')&&trb_portal_is_release_qa_account($user))return false;
 if(get_user_meta($user->ID,'_trb_onboarding_qa',true)==='1')return false;
 if(function_exists('pw_new_user_approve')&&pw_new_user_approve()->get_user_status($user->ID)!=='approved')return false;
 return true;
}
function portal_release_allowed($p){
 if(!$p||$p->post_type!=='trb_release'||!in_array($p->post_status,['publish','private'],true)||!portal_artist_allowed(get_userdata($p->post_author)))return false;
 if(get_post_meta($p->ID,'_trb_release_qa_mode',true)==='1')return false;
 if(get_post_meta($p->ID,'_trb_release_intake_phase',true)!=='complete')return false;
 if(function_exists('trb_release_is_inactive')&&trb_release_is_inactive($p->ID))return false;
 $states=get_option('trb_studio_distribution_states',[]);
 return $states && in_array((string)get_post_meta($p->ID,'_trb_crm_workflow_status',true),$states,true);
}
function material($kind,$id,$type){
 $files=$kind==='artist'?get_user_meta($id,'_trb_artist_private_files',true):get_post_meta($id,'_trb_release_files',true);
 $files=is_array($files)?$files:[];
 foreach($files as $file){
  if(($file[$kind==='artist'?'group':'kind']??'')!==$type)continue;
  $path=$kind==='artist'?trb_artist_promo_local_photo($file):trb_release_pcloud_local_file($file);
  if($path && is_file($path))return ['path'=>$path,'name'=>$file['name']??basename($path),'hash'=>hash_file('sha256',$path)];
 }
 return null;
}
function material_text($file){
 if(!$file)return '';
 $path=$file['path'];if(filesize($path)>5*1024*1024)return new \WP_Error('text_too_large','Materiale testuale oltre 5 MB.');
 $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
 if($ext==='txt')$text=file_get_contents($path);
 elseif(in_array($ext,['docx','odt'],true)&&class_exists('ZipArchive')){
  $zip=new \ZipArchive();if($zip->open($path)!==true)return new \WP_Error('document_unreadable','Documento artistico non leggibile.');
  $entry=$ext==='docx'?'word/document.xml':'content.xml';$stat=$zip->statName($entry);
  if(!$stat||$stat['size']>8*1024*1024){$zip->close();return new \WP_Error('document_unreadable','Documento artistico non leggibile.');}
  $xml=$zip->getFromName($entry);$zip->close();
  $xml=preg_replace('~</(?:w:p|text:p|text:h)>~',"\n",$xml);
  $text=html_entity_decode(strip_tags($xml),ENT_QUOTES|ENT_XML1,'UTF-8');
 }elseif($ext==='rtf')return new \WP_Error('rtf_review','Biografia/presentazione RTF: esportare in TXT, DOCX o ODT prima della pubblicazione automatica.');
 else return new \WP_Error('document_format','Formato testuale non supportato.');
 $text=trim(wp_check_invalid_utf8((string)$text));
 if(strlen($text)>60000)return new \WP_Error('text_too_long','Materiale testuale oltre il limite di pubblicazione.');
 return $text;
}
function asset_reference($kind,$id,$file){return $file?['kind'=>$kind,'id'=>(int)$id,'hash'=>$file['hash']]:null;}
function valid_date($date){$d=\DateTimeImmutable::createFromFormat('!Y-m-d',(string)$date,new \DateTimeZone('Europe/Rome'));return $d&&$d->format('Y-m-d')===$date;}
function portal_snapshot(){
 foreach(['trb_portal_user_profile','trb_artist_promo_local_photo','trb_release_pcloud_local_file'] as $fn)if(!function_exists($fn))return new \WP_Error('portal_adapter_missing','Le funzioni del portale richieste non sono disponibili.',['status'=>503]);
 if(!get_option('trb_studio_distribution_states'))return new \WP_Error('distribution_mapping_required','Configurare gli stati commerciali di distribuzione verificati.',['status'=>409]);
 $users=get_users(['number'=>1001,'orderby'=>'ID','order'=>'ASC']);
 if(count($users)>1000)return new \WP_Error('snapshot_limit','Più di 1000 utenti: aggiungere paginazione prima della sincronizzazione.',['status'=>409]);
 $artists=[];$ids=[];$warnings=[];
 foreach($users as $user){
  if(!portal_artist_allowed($user))continue;
  $name=trim((string)get_user_meta($user->ID,'_trb_artist_artist_name',true));if(!$name){$warnings[]='Nome artista mancante: '.$user->ID;continue;}
  $biofile=material('artist',$user->ID,'biography');$bio=$biofile?material_text($biofile):(string)get_user_meta($user->ID,'_trb_artist_bio',true);
  if(is_wp_error($bio))return $bio;
  if(str_starts_with($bio,'Biografia allegata:'))$bio='';
  $links=[];foreach(['spotify'=>'Spotify','apple_music'=>'Apple Music','youtube'=>'YouTube','soundcloud'=>'SoundCloud','instagram'=>'Instagram','facebook'=>'Facebook','tiktok'=>'TikTok','threads'=>'Threads','x'=>'X','twitch'=>'Twitch','linkedin'=>'LinkedIn','discord'=>'Discord','snapchat'=>'Snapchat'] as $key=>$label){$url=esc_url_raw(get_user_meta($user->ID,'_trb_artist_'.$key.'_url',true),['https']);if($url)$links[]=['label'=>$label,'url'=>$url];}
  $artists[]=['id'=>(int)$user->ID,'name'=>$name,'bio'=>$bio,'links'=>$links,'photo'=>asset_reference('artist',$user->ID,material('artist',$user->ID,'photo'))];$ids[]=(int)$user->ID;
 }
 $releases=[];
 if($ids){
  $posts=get_posts(['post_type'=>'trb_release','post_status'=>['publish','private'],'author__in'=>$ids,'posts_per_page'=>2001,'orderby'=>'ID','order'=>'ASC','suppress_filters'=>false]);
  if(count($posts)>2000)return new \WP_Error('snapshot_limit','Più di 2000 release: aggiungere paginazione prima della sincronizzazione.',['status'=>409]);
  foreach($posts as $p){
   if(!portal_release_allowed($p))continue;
   $date=(string)get_post_meta($p->ID,'_trb_release_date',true);if(!$date)$date=(string)get_post_meta($p->ID,'_trb_release_original_date',true);
   if(!valid_date($date)){$warnings[]='Data non valida: '.$p->ID;continue;}
   $presentation=material_text(material('release',$p->ID,'presentation'));if(is_wp_error($presentation))return $presentation;
   $releases[]=['id'=>(int)$p->ID,'artist_id'=>(int)$p->post_author,'title'=>$p->post_title,'date'=>$date,'presentation'=>$presentation,'cover'=>asset_reference('release',$p->ID,material('release',$p->ID,'cover'))];
  }
 }
 $payload=['schema'=>1,'source'=>'artist.trbrec.com','generated_at'=>gmdate('c'),'artists'=>$artists,'releases'=>$releases,'warnings'=>$warnings,'complete'=>true];
 $payload['revision']=hash('sha256',wp_json_encode([$artists,$releases]));
 $response=rest_ensure_response($payload);$response->header('Cache-Control','private, no-store');return $response;
}
function portal_asset($request){
 $kind=$request['kind'];$id=(int)$request['id'];
 if($kind==='artist'?!portal_artist_allowed(get_userdata($id)):!portal_release_allowed(get_post($id)))return new \WP_Error('asset_forbidden','Materiale non pubblicabile.',['status'=>404]);
 $file=material($kind,$id,$kind==='artist'?'photo':'cover');
 if(!$file||!in_array(wp_get_image_mime($file['path']),['image/jpeg','image/png','image/webp'],true))return new \WP_Error('image_missing','Immagine non disponibile.',['status'=>404]);
 $editor=wp_get_image_editor($file['path']);if(is_wp_error($editor))return $editor;
 $res=$editor->resize(1200,1200,false);if(is_wp_error($res))return $res;$editor->set_quality(88);
 $tmp=wp_tempnam('trb-public-photo');$result=$editor->save($tmp,'image/jpeg');
 if(is_wp_error($result)){if(is_file($tmp))unlink($tmp);return $result;}
 $bytes=file_get_contents($result['path']);unlink($result['path']);if(is_file($tmp))unlink($tmp);
 if(strlen($bytes)>5*1024*1024)return new \WP_Error('asset_large','Immagine oltre il limite.',['status'=>422]);
 $response=rest_ensure_response(['mime'=>'image/jpeg','hash'=>$file['hash'],'bytes_sha256'=>hash('sha256',$bytes),'base64'=>base64_encode($bytes)]);$response->header('Cache-Control','private, no-store');return $response;
}
