<?php
/** CLI-only review of named artistic biographies; never reads administrative folders. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$revision=$argv[1]??'';$mode=$argv[2]??'review';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision)exit(2);
$trbReviewRoot='/home/customer/www/new1.trbrec.com/private/trb-site-studio';
$trbReviewFile=$trbReviewRoot.'/legacy-artistic-review.json';
define('WP_USE_THEMES',false);
if($mode==='import'){
 require '/home/customer/www/new1.trbrec.com/public_html/wp-load.php';
 $trbReviewData=json_decode((string)@file_get_contents($trbReviewFile),true);
 if(!is_array($trbReviewData)||($trbReviewData['revision']??'')!==$revision)exit(3);
 update_option('trb_studio_legacy_material_review',$trbReviewData,false);exit;
}
require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
$trbReviewInventory=json_decode((string)@file_get_contents($trbReviewRoot.'/legacy-artistic-inventory.json'),true);
if(!is_array($trbReviewInventory))exit(4);
// Exact artistic biography found in Carmine's official historical media kit.
$trbCarmineBio='/Discografia - DDB/Carmine Granato/Quello che resta/promo/Bio Carmine Granato.pdf';
$trbReviewInventory['matches'][28][$trbCarmineBio]=['path'=>$trbCarmineBio,'extension'=>'pdf','size'=>1418823];
$trbReviewData=['revision'=>$revision,'at'=>gmdate('c'),'artists'=>[]];
foreach([28,46,70,90,94,181] as $trbReviewId){
 $u=get_userdata($trbReviewId);if(!$u||!\TRB\Studio\portal_artist_allowed($u))continue;
 $entry=['display_name'=>$u->display_name,'official_name'=>(string)get_user_meta($trbReviewId,'_trb_artist_artist_name',true),'given_name'=>$u->first_name,'family_name'=>$u->last_name,'owned_release_titles'=>array_map(static fn($p)=>$p->post_title,get_posts(['post_type'=>'trb_release','post_status'=>['publish','private','draft','pending','future'],'author'=>$trbReviewId,'posts_per_page'=>100,'orderby'=>'ID','order'=>'DESC'])),'biographies'=>[]];
 foreach($trbReviewInventory['matches'][$trbReviewId]??[] as $match){
  $path=$match['path'];if((!str_starts_with($path,'/Upload files - TRB rec/Media/Biographies/')&&$path!==$trbCarmineBio)||preg_match('/contract|identity|passport|contratt|identit|passaporto/i',$path))continue;
  if(!in_array($match['extension'],['docx','txt','rtf','pdf'],true)||$match['size']>2*1024*1024)continue;
  $res=trb_demo_webdav_request('GET',$path);if(is_wp_error($res)||wp_remote_retrieve_response_code($res)!==200){$entry['biographies'][]=['source'=>$path,'code'=>'read_failed'];continue;}
  $bytes=wp_remote_retrieve_body($res);if(strlen($bytes)>2*1024*1024)continue;
  $tmp=wp_tempnam('trb-artistic-review');if(file_put_contents($tmp,$bytes)!==strlen($bytes))exit(5);
  $item=['source'=>$path,'sha256'=>hash('sha256',$bytes),'extension'=>$match['extension']];
  if($match['extension']==='pdf'){
   if(is_executable('/usr/bin/pdftotext')){$lines=[];$code=1;exec('/usr/bin/pdftotext -layout '.escapeshellarg($tmp).' - 2>/dev/null',$lines,$code);$text=$code===0?implode("\n",$lines):'';}
   else {$text='';$item['code']='pdf_reader_unavailable';$item['base64']=base64_encode($bytes);}
  }else{$text=\TRB\Studio\material_text(['path'=>$tmp,'name'=>basename($path)]);if(is_wp_error($text)){$item['code']=$text->get_error_code();$text='';}}
  unlink($tmp);$text=trim(wp_check_invalid_utf8((string)$text));
  if($text!==''&&strlen($text)<=60000)$item['text']=$text;
  $entry['biographies'][]=$item;if(count($entry['biographies'])>=3)break;
 }
 $trbReviewData['artists'][$trbReviewId]=$entry;
}
$json=wp_json_encode($trbReviewData);$temp=$trbReviewFile.'.tmp';if(file_put_contents($temp,$json)!==strlen($json))exit(6);chmod($temp,0600);if(!rename($temp,$trbReviewFile))exit(7);

