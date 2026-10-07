<?php
namespace TRB\Studio;
if (!defined('ABSPATH')) exit;
function portal_artist_allowed($user){
 if(!$user||!function_exists('trb_portal_user_profile')||trb_portal_user_profile($user)!=='trb')return false;
 if(function_exists('trb_portal_is_release_qa_account')&&trb_portal_is_release_qa_account($user))return false;
 if(get_user_meta($user->ID,'_trb_onboarding_qa',true)==='1')return false;
 $status=function_exists('trb_release_bridge_access_status')?trb_release_bridge_access_status($user->ID):(string)get_user_meta($user->ID,'pw_user_status',true);
 if(!function_exists('trb_release_bridge_access_status')&&function_exists('pw_new_user_approve')){
  $approval=pw_new_user_approve();if(is_object($approval)&&is_callable([$approval,'get_user_status']))$status=$approval->get_user_status($user->ID);
 }
 if(!in_array($status,['approved','approve'],true))return false;
 return true;
}
function portal_release_allowed($p){
 if(!$p||$p->post_type!=='trb_release'||!in_array($p->post_status,['publish','private'],true)||!portal_artist_allowed(get_userdata($p->post_author)))return false;
 if(get_post_meta($p->ID,'_trb_release_qa_mode',true)==='1')return false;
 $phase=(string)get_post_meta($p->ID,'_trb_release_intake_phase',true);
 // Legacy CRM-processed imports predate the intake marker. Nonempty incomplete markers still block.
 if($phase!==''&&$phase!=='complete')return false;
 if(get_post_meta($p->ID,'_trb_contract_state',true)!=='signed')return false;
 if(function_exists('trb_release_is_inactive')&&trb_release_is_inactive($p->ID))return false;
 $states=get_option('trb_studio_distribution_states',[]);
 return $states && in_array((string)get_post_meta($p->ID,'_trb_crm_workflow_status',true),$states,true);
}
function portal_artist_name($user){
 $name=trim((string)get_user_meta($user->ID,'_trb_artist_artist_name',true));
 if($name!=='')return $name;
 // WordPress's designated public display name is the only legacy fallback.
 $name=trim((string)($user->display_name??''));
 return $name!==''&&!str_contains($name,'@')?$name:'';
}
function catalogue_payload($p,$artist,$date,$upc){
 if(!$upc)return null;$tracks=[];
 foreach((array)get_post_meta($p->ID,'_trb_release_tracks',true) as $i=>$t){
  if(!is_array($t)||!preg_match('/^[A-Z]{2}[A-Z0-9]{3}[0-9]{7}$/D',(string)($t['isrc']??''))||empty($t['title']))return null;
  $tracks[]=['number'=>count($tracks)+1,'isrc'=>$t['isrc'],'title'=>$t['title'],'version'=>(string)($t['version']??''),'primary_artist'=>$artist,'featuring'=>(string)($t['featuring']??''),'remixers'=>'','duration'=>(string)($t['duration']??''),'genre'=>(string)($t['primary_genre']??''),'subgenre'=>(string)($t['secondary_genre']??'')];
 }
 if(!$tracks)return null;
 $key=class_exists('Normalizer')?\Normalizer::normalize($artist,\Normalizer::FORM_C):$artist;
 $key=preg_replace('/\s+/u',' ',trim($key));$key=function_exists('mb_strtolower')?mb_strtolower($key,'UTF-8'):strtolower($key);
 return ['upc'=>$upc,'title'=>$p->post_title,'version'=>'','artist'=>$artist,'artist_key'=>$key,'date'=>$date,'label'=>'TRB rec – Music Publishing','catalog_number'=>'','genre'=>$tracks[0]['genre'],'subgenre'=>$tracks[0]['subgenre'],'tracks'=>$tracks];
}
function material($kind,$id,$type){
 $files=$kind==='artist'?get_user_meta($id,'_trb_artist_private_files',true):get_post_meta($id,'_trb_release_files',true);
 $files=is_array($files)?$files:[];
 $selection=$kind==='artist'&&$type==='photo'?(string)get_user_meta($id,'_trb_studio_selected_photo_hash',true):'';
 $fallback=null;
 foreach($files as $file){
  if(($file[$kind==='artist'?'group':'kind']??'')!==$type)continue;
  $path=$kind==='artist'?trb_artist_promo_local_photo($file):trb_release_pcloud_local_file($file);
  if(!$path||!is_file($path))continue;
  $asset=['path'=>$path,'name'=>$file['name']??basename($path),'hash'=>hash_file('sha256',$path)];
  if(!$selection||hash_equals($selection,$asset['hash']))return $asset;
  if(!$fallback)$fallback=$asset;
 }
 return $fallback;
}
function material_photos($id){
 $primary=material('artist',$id,'photo');$out=[];
 if($primary&&@getimagesize($primary['path'])){$reader=wp_get_image_editor($primary['path']);if(!is_wp_error($reader))$out[$primary['hash']]=$primary;unset($reader);}
 foreach((array)get_user_meta($id,'_trb_artist_private_files',true) as $file){
  if(!is_array($file)||($file['group']??'')!=='photo')continue;
  $path=trb_artist_promo_local_photo($file);
  if(!$path||!is_file($path)||!in_array(wp_get_image_mime($path),['image/jpeg','image/png','image/webp'],true))continue;
  $dimensions=@getimagesize($path);if(!$dimensions||$dimensions[0]<1||$dimensions[1]<1)continue;
  $reader=wp_get_image_editor($path);if(is_wp_error($reader))continue;unset($reader);
  $hash=hash_file('sha256',$path);$out[$hash]=['path'=>$path,'name'=>$file['name']??basename($path),'hash'=>$hash];
  if(count($out)>=3)break;
 }
 return array_values($out);
}
function rtf_text($raw){
 if(!str_starts_with(ltrim($raw),'{\\rtf'))return new \WP_Error('rtf_invalid','Documento RTF non valido.');
 $stack=[];$skip=false;$uc=1;$fallback=0;$out='';$length=strlen($raw);
 for($i=0;$i<$length;){
  $ch=$raw[$i++];
  if($ch==='{'){if(count($stack)>128)return new \WP_Error('rtf_depth','Documento RTF non valido.');$stack[]=[$skip,$uc];continue;}
  if($ch==='}'){if($stack){[$skip,$uc]=array_pop($stack);}continue;}
  if($ch!=='\\'){
   if($fallback>0){--$fallback;continue;}
   if(!$skip&&$ch!=="\r"&&$ch!=="\n")$out.=iconv('Windows-1252','UTF-8//IGNORE',$ch);
   continue;
  }
  if($i>=$length)break;$next=$raw[$i];
  if(in_array($next,['\\','{','}'],true)){++$i;if($fallback>0)--$fallback;elseif(!$skip)$out.=$next;continue;}
  if($next==='*'){++$i;$skip=true;continue;}
  if($next==="'"){
   $hex=substr($raw,$i+1,2);$i+=3;
   if($fallback>0)--$fallback;elseif(!$skip&&ctype_xdigit($hex))$out.=iconv('Windows-1252','UTF-8//IGNORE',chr(hexdec($hex)));continue;
  }
  if(!preg_match('/\\G([a-zA-Z]+)(-?[0-9]+)? ?/',$raw,$m,0,$i)){++$i;if(!$skip&&$next==='~')$out.=' ';continue;}
  $i+=strlen($m[0]);$word=$m[1];$n=isset($m[2])?(int)$m[2]:0;
  if(in_array($word,['fonttbl','colortbl','stylesheet','info','pict','object','fldinst','header','footer','datastore','xmlnstbl'],true)){$skip=true;continue;}
  if($word==='bin'){$i+=max(0,$n);continue;}
  if($word==='uc'){$uc=max(0,min(16,$n));continue;}
  if($skip)continue;
  if($word==='u'){$code=$n<0?$n+65536:$n;$out.=html_entity_decode('&#'.$code.';',ENT_QUOTES,'UTF-8');$fallback=$uc;continue;}
  if(in_array($word,['par','line'],true))$out.="\n";
  elseif($word==='tab')$out.=' ';
  elseif(isset(['emdash'=>1,'endash'=>1,'lquote'=>1,'rquote'=>1,'ldblquote'=>1,'rdblquote'=>1][$word]))$out.=['emdash'=>'—','endash'=>'–','lquote'=>'‘','rquote'=>'’','ldblquote'=>'“','rdblquote'=>'”'][$word];
  if(strlen($out)>60000)return new \WP_Error('text_too_long','Materiale testuale oltre il limite di pubblicazione.');
 }
 return trim($out);
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
 }elseif($ext==='rtf'){$text=rtf_text((string)file_get_contents($path));if(is_wp_error($text))return $text;}
 else return new \WP_Error('document_format','Formato testuale non supportato.');
 $text=trim(wp_check_invalid_utf8((string)$text));
 if(strlen($text)>60000)return new \WP_Error('text_too_long','Materiale testuale oltre il limite di pubblicazione.');
 return $text;
}
function asset_reference($kind,$id,$file){return $file?['kind'=>$kind,'id'=>(int)$id,'hash'=>$file['hash']]:null;}
function valid_upc($value){
 $value=trim($value);if(!preg_match('/^\\d{12,14}$/D',$value))return '';
 $sum=0;$weight=3;for($i=strlen($value)-2;$i>=0;$i--){$sum+=(int)$value[$i]*$weight;$weight=$weight===3?1:3;}
 return (10-$sum%10)%10===(int)substr($value,-1)?$value:'';
}
function valid_date($date){$d=\DateTimeImmutable::createFromFormat('!Y-m-d',(string)$date,new \DateTimeZone('Europe/Rome'));return $d&&$d->format('Y-m-d')===$date;}
function portal_snapshot(){
 foreach(['trb_portal_user_profile','trb_artist_promo_local_photo','trb_release_pcloud_local_file'] as $fn)if(!function_exists($fn))return new \WP_Error('portal_adapter_missing','Le funzioni del portale richieste non sono disponibili.',['status'=>503]);
 if(!get_option('trb_studio_distribution_states'))return new \WP_Error('distribution_mapping_required','Configurare gli stati commerciali di distribuzione verificati.',['status'=>409]);
 $users=get_users(['number'=>1001,'orderby'=>'ID','order'=>'ASC']);
 if(count($users)>1000)return new \WP_Error('snapshot_limit','Più di 1000 utenti: aggiungere paginazione prima della sincronizzazione.',['status'=>409]);
 $artists=[];$ids=[];$warnings=[];
 foreach($users as $user){
  if(!portal_artist_allowed($user))continue;
  $name=portal_artist_name($user);if(!$name){$warnings[]='Nome artista mancante: '.$user->ID;continue;}
  $biofile=material('artist',$user->ID,'biography');$bio=$biofile?material_text($biofile):(string)get_user_meta($user->ID,'_trb_artist_bio',true);
  if(is_wp_error($bio))return $bio;
  if(str_starts_with($bio,'Biografia allegata:'))$bio='';
  if(!$bio&&!$biofile)$bio=(string)get_user_meta($user->ID,'description',true);
  $links=[];foreach(['spotify'=>'Spotify','apple_music'=>'Apple Music','youtube'=>'YouTube','soundcloud'=>'SoundCloud','instagram'=>'Instagram','facebook'=>'Facebook','tiktok'=>'TikTok','threads'=>'Threads','x'=>'X','twitch'=>'Twitch','linkedin'=>'LinkedIn','discord'=>'Discord','snapchat'=>'Snapchat'] as $key=>$label){$url=esc_url_raw(get_user_meta($user->ID,'_trb_artist_'.$key.'_url',true),['https']);if($url)$links[]=['label'=>$label,'url'=>$url];}
  $official=trim((string)get_user_meta($user->ID,'_trb_artist_artist_name',true));
  $website=esc_url_raw((string)($user->user_url??''),['https']);if($website)$links[]=['label'=>'Sito ufficiale','url'=>$website];
  $photos=material_photos($user->ID);
  $artists[]=['id'=>(int)$user->ID,'name_source'=>$official?'artist_profile':'wordpress_public_name','name'=>$name,'bio'=>$bio,'links'=>$links,'photo'=>asset_reference('artist',$user->ID,($photos[0]??null)),'photos'=>array_map(fn($file)=>asset_reference('artist',$user->ID,$file),$photos)];$ids[]=(int)$user->ID;
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
   $upc=valid_upc((string)get_post_meta($p->ID,'_trb_release_upc',true));$artist=portal_artist_name(get_userdata($p->post_author));$catalog=catalogue_payload($p,$artist,$date,$upc);
   if(!$catalog)$warnings[]='Metadati catalogo incompleti: '.$p->ID;
   $releases[]=['catalog'=>$catalog,'id'=>(int)$p->ID,'artist_id'=>(int)$p->post_author,'title'=>$p->post_title,'date'=>$date,'presentation'=>$presentation,'upc'=>valid_upc((string)get_post_meta($p->ID,'_trb_release_upc',true)),'cover'=>asset_reference('release',$p->ID,material('release',$p->ID,'cover'))];
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
 if(!empty($request['hash'])){ $match=null;foreach($kind==='artist'?material_photos($id):[$file] as $candidate)if($candidate&&hash_equals($candidate['hash'],(string)$request['hash']))$match=$candidate;$file=$match; }
 if(!$file||!in_array(wp_get_image_mime($file['path']),['image/jpeg','image/png','image/webp'],true))return new \WP_Error('image_missing','Immagine non disponibile.',['status'=>404]);
 $editor=wp_get_image_editor($file['path']);if(is_wp_error($editor))return $editor;
 $res=$editor->resize(1200,1200,false);if(is_wp_error($res))return $res;$editor->set_quality(88);
 $tmp=wp_tempnam('trb-public-photo');$result=$editor->save($tmp,'image/jpeg');
 if(is_wp_error($result)){if(is_file($tmp))unlink($tmp);return $result;}
 $bytes=file_get_contents($result['path']);unlink($result['path']);if(is_file($tmp))unlink($tmp);
 if(strlen($bytes)>5*1024*1024)return new \WP_Error('asset_large','Immagine oltre il limite.',['status'=>422]);
 $response=rest_ensure_response(['mime'=>'image/jpeg','hash'=>$file['hash'],'bytes_sha256'=>hash('sha256',$bytes),'base64'=>base64_encode($bytes)]);$response->header('Cache-Control','private, no-store');return $response;
}
