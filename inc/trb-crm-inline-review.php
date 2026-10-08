<?php
/** Authenticated, version-bound operator review from CRM release previews. */
if (!defined('ABSPATH')) exit;

function trb_crm_inline_permission($request) {
 $settings=trb_crm_connector_settings();$secret=(string)($settings['secret']??'');
 $timestamp=(string)$request->get_header('x-trb-timestamp');$signature=(string)$request->get_header('x-trb-signature');
 if(strlen($secret)<32||!ctype_digit($timestamp)||abs(time()-(int)$timestamp)>300)return new WP_Error('crm_auth','Autenticazione CRM non valida.',array('status'=>403));
 $expected='sha256='.hash_hmac('sha256',$timestamp.'.'.$request->get_body(),$secret);
 return hash_equals($expected,$signature)?true:new WP_Error('crm_auth','Firma CRM non valida.',array('status'=>403));
}
function trb_crm_inline_current_reviews($files,$reviews,$revision) {
 $current=array();foreach($files as $index=>$file) {
  $review=$reviews[$index]??array();
  if(!empty($review['sha256'])&&hash_equals((string)$file['sha256'],(string)$review['sha256'])&&(string)($review['analysis_revision']??'')===(string)(is_array($revision)?($revision[$index]??''):$revision))$current[$index]=$review;
 }
 return $current;
}
function trb_crm_inline_snapshot($id) {
 if(get_post_type($id)!=='trb_release'||trb_release_is_inactive($id))return new WP_Error('release_unavailable','Pratica non disponibile.',array('status'=>404));
 $decision=(array)get_post_meta($id,'_trb_release_analysis_decision',true);
 $tracks=(array)get_post_meta($id,'_trb_release_tracks',true);$files=array();
 foreach((array)get_post_meta($id,'_trb_release_files',true) as $file)if(($file['kind']??'')==='audio'&&!empty($file['sha256']))$files[(int)($file['track']??0)]=$file;
 ksort($files);
 // Read only completed provider results bound to the current file hashes, even when technical QC is blocked.
 global $wpdb;$table=trb_resource_tables()['usage'];$results=array();
 $rows=$wpdb->get_results($wpdb->prepare("SELECT track_index,file_hash,payload FROM $table WHERE release_id=%d AND provider='acrcloud' AND service IN ('fingerprinting','fingerprinting_reuse') AND status='completed' ORDER BY id",$id),ARRAY_A);
 foreach($rows as $row){$i=(int)$row['track_index'];if(isset($files[$i])&&hash_equals((string)$files[$i]['sha256'],(string)$row['file_hash'])){$payload=json_decode($row['payload'],true);$results[$i]=trb_analysis_normalize_acr_result(is_array($payload)?$payload:array());}}
 $revision=hash('sha256',wp_json_encode($results));
 $track_revisions=array();foreach($results as $i=>$result)$track_revisions[$i]=hash('sha256',wp_json_encode($result));
 $reviews=trb_crm_inline_current_reviews($files,(array)get_post_meta($id,'_trb_crm_inline_reviews',true),$track_revisions);
 $bindings=array();$out=array();
 foreach($files as $index=>$file){
  $bindings[$index]=(string)$file['sha256'];$result=(array)($results[$index]??array());
  $matches=array();foreach((array)($result['matches']??array()) as $match){$match['id']=substr(hash('sha256',wp_json_encode($match)),0,24);$matches[]=$match;}
  $out[]=array('index'=>$index,'title'=>$tracks[$index]['title']??$file['name']??'Brano '.($index+1),'sha256'=>$file['sha256'],'audio_relative'=>$file['path']??'','audio_available'=>(bool)trb_release_pcloud_local_file($file),'matches'=>$matches,'analysis_revision'=>$track_revisions[$index]??'','analysis_available'=>isset($results[$index]),'review'=>$reviews[$index]??null);
 }
 $pipeline=(string)get_post_meta($id,'_trb_release_pipeline_status',true);
 $technical=(array)get_post_meta($id,'_trb_release_technical_analysis',true);
 $archive=(array)get_post_meta($id,'_trb_release_pcloud_archive',true);
 $ready=$files&&count($results)>=count($tracks)&&trb_release_technical_is_current($id)&&in_array($technical['status']??'',array('passed','warning'),true)&&!empty($archive['verified']);
 $cover_ready=function_exists('trb_portal_release_has_final_cover')?trb_portal_release_has_final_cover($id):true;
 $contract=(string)get_post_meta($id,'_trb_contract_state',true);
 $blockers=array();foreach((array)($technical['errors']??array()) as $error)if(is_string($error))$blockers[]=$error;
 if(!trb_release_technical_is_current($id)&&!$blockers)$blockers[]='Controllo tecnico dei file da completare.';
 if(empty($archive['verified']))$blockers[]='Archiviazione dei materiali da completare.';
 if(!$cover_ready)$blockers[]='Copertina definitiva da completare.';
 if(count($files)<count($tracks))$blockers[]='Uno o più file audio devono essere caricati.';
 return array('ok'=>true,'release_id'=>$id,'version'=>hash('sha256',wp_json_encode(array($bindings,$revision,$reviews,$pipeline,$contract,$technical,$archive['verified']??false,$cover_ready))),'analysis_revision'=>$revision,'pipeline'=>$pipeline,'contract_state'=>$contract,'blockers'=>$blockers,'can_finalize'=>(bool)($ready&&$cover_ready),'can_decide'=>(bool)($results&&!in_array($contract,array('contract_sent','signed'),true)),'rejection_text'=>trb_crm_inline_rejection_text(),'unavailable_reason'=>in_array($contract,array('contract_sent','signed'),true)?($contract==='signed'?'Contratto già firmato. Questa sezione mostra i risultati storici: non occorre una nuova approvazione.':'Contratto già inviato. Questa sezione mostra i risultati storici: non occorre una nuova approvazione.'):(!$ready?'Controlli tecnici, archiviazione o analisi ancora da completare.':(!$cover_ready?'Copertina definitiva da completare.':'')),'findings'=>array_values((array)($decision['copyright_findings']??array())),'tracks'=>$out);
}
/** Preserve current operator decisions when a delayed provider callback repeats. */
function trb_crm_inline_apply_review($id,$dispatch=false) {
 $snapshot=trb_crm_inline_snapshot($id);if(is_wp_error($snapshot)||!$snapshot['tracks'])return false;
 $all=true;$rejected=false;foreach($snapshot['tracks'] as $track){$state=$track['review']['action']??'';$all=$all&&$state==='approve';$rejected=$rejected||$state==='reject';}
 if($rejected){if(!$snapshot['can_finalize'])return false;update_post_meta($id,'_trb_release_pipeline_status','copyright_documents_needed');return true;}
 if(!$all)return false;
 if(in_array($snapshot['contract_state'],array('contract_sent','signed'),true))return true;
 if(!$snapshot['can_finalize'])return false;
 update_post_meta($id,'_trb_release_pipeline_status','approved');
 if($dispatch)do_action('trb_release_analysis_approved',$id);
 return true;
}
/** Validate the complete batch before any write or notification. */
function trb_crm_inline_validate_batch($snapshot,$input) {
 if(!is_array($input)||!$input||count($input)>24)return new WP_Error('review_invalid','Seleziona almeno una decisione.');
 $tracks=array_column($snapshot['tracks'],null,'index');$seen=array();$validated=array();
 foreach($input as $item){
  $index=isset($item['track'])?(int)$item['track']:-1;$action=$item['action']??'';
  if(!isset($tracks[$index])||empty($tracks[$index]['analysis_available'])||isset($seen[$index])||!in_array($action,array('approve','reject'),true))return new WP_Error('review_invalid','Controlla brani, decisioni e conferme di ascolto.');
  $seen[$index]=true;$track=$tracks[$index];$note=sanitize_textarea_field($item['note']??'');
  $available=array_column($track['matches'],null,'id');$selected=array();
  foreach((array)($item['selected_matches']??array()) as $matchId){if(!is_string($matchId)||!isset($available[$matchId]))return new WP_Error('match_stale','Una corrispondenza non è più disponibile. Ricarica il confronto.');$selected[$matchId]=$available[$matchId];}
  if($action==='reject'&&!$selected)return new WP_Error('reason_required','Seleziona almeno una corrispondenza per ciascun brano da segnalare.');
  if($action==='approve'&&$selected)return new WP_Error('review_conflict','Un brano approvato non può avere corrispondenze indicate come problematiche.');
  $validated[]=array('track'=>$track,'action'=>$action,'note'=>$note,'selected_matches'=>array_values($selected),'other_reason'=>!empty($item['other_reason']));
 }
 return $validated;
}
function trb_crm_inline_rejection_text() {
 return 'Durante la verifica dei brani indicati qui sotto abbiamo individuato corrispondenze che richiedono un chiarimento sui diritti. Per proseguire, ti chiediamo di confermare la titolarità dei materiali e di fornire le eventuali licenze o autorizzazioni. Se il riferimento corrisponde a una tua precedente pubblicazione, indicaci il relativo ISRC o il link ufficiale.';
}
function trb_crm_inline_email_body($name,$rejected) {
 $body='<p>Gentile '.esc_html($name?:'Artista').',</p><p>'.esc_html(trb_crm_inline_rejection_text()).'</p>';
 foreach($rejected as $item){
  $body.='<h3>'.esc_html($item['track']['title']).'</h3>';
  if(!empty($item['note']))$body.='<p>'.nl2br(esc_html($item['note'])).'</p>';
  $body.='<ul>';foreach($item['selected_matches'] as $match)$body.='<li>'.esc_html(implode(', ',(array)$match['artists']).' — '.$match['title']).'</li>';$body.='</ul>';
 }
 return $body.'<p>La pratica rimane aperta. Rispondi a questa email con i chiarimenti o la documentazione richiesti; non creare una nuova release.</p>'.trb_resource_artist_email_signature();
}
function trb_crm_inline_queue_rejections($id,$rejected,$key) {
 if(function_exists('trb_portal_release_is_qa')&&trb_portal_release_is_qa($id))return 'qa_suppressed';
 $post=get_post($id);$user=get_userdata($post->post_author);if(!$user||!is_email($user->user_email))return new WP_Error('recipient_missing','Indirizzo artista non disponibile.');
 $body=trb_crm_inline_email_body(trb_resource_artist_legal_greeting_name($user),$rejected);
 trb_resource_queue_recipient_email($key,$user->user_email,'Verifica richiesta per la release '.$post->post_title,$body,true,trb_resource_artist_recovery_cc_headers());
 global $wpdb;$table=trb_resource_tables()['notifications'];
 $queued=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE event_key=%s AND recipient=%s",$key,$user->user_email));
 return $queued?'queued':new WP_Error('email_queue_failed','Comunicazione non accodata. Nessuna decisione salvata: riprova.');
}
function trb_crm_inline_handle($request) {
 $id=(int)$request['id'];$body=(array)$request->get_json_params();
 if((int)($body['release_id']??0)!==$id)return new WP_Error('wrong_receipt','Identificativo pratica non corrispondente.',array('status'=>409));
 $action=preg_replace('/^inline_review_/','',(string)($body['operation']??'snapshot'));
 if($action==='snapshot')return trb_crm_inline_snapshot($id);
 if($action==='reference')return trb_crm_inline_reference($id,$body);
 if($action!=='apply'||empty($body['reviewer']))return new WP_Error('review_invalid','Operazione non valida.',array('status'=>422));
 $lock=trb_release_process_lock('release:'.$id);if(!$lock)return new WP_Error('review_busy','Pratica in aggiornamento. Riprova.',array('status'=>409));
 try {
  $snapshot=trb_crm_inline_snapshot($id);if(is_wp_error($snapshot))return $snapshot;
  if(!hash_equals($snapshot['version'],(string)($body['version']??'')))return new WP_Error('review_stale','Il brano o la pratica sono cambiati. Ricarica il confronto prima di decidere.',array('status'=>409));
  if(!$snapshot['can_decide'])return new WP_Error('review_not_ready',$snapshot['unavailable_reason']?:'Pratica già in fase contrattuale.',array('status'=>409));
  $validated=trb_crm_inline_validate_batch($snapshot,$body['decisions']??null);if(is_wp_error($validated))return $validated;
  $key='crm-track-review-'.$id.'-'.substr(hash('sha256',$snapshot['version'].'|'.wp_json_encode($validated)),0,24);
  $rejected=array_values(array_filter($validated,static function($item){return $item['action']==='reject';}));
  global $wpdb;$wpdb->query('START TRANSACTION');$email=null;if($rejected){$email=trb_crm_inline_queue_rejections($id,$rejected,$key);if(is_wp_error($email)){$wpdb->query('ROLLBACK');return $email;}}
  $reviews=(array)get_post_meta($id,'_trb_crm_inline_reviews',true);
  foreach($validated as $item){$track=$item['track'];$reviews[$track['index']]=array('action'=>$item['action'],'sha256'=>$track['sha256'],'analysis_revision'=>$track['analysis_revision'],'reviewer'=>(int)$body['reviewer'],'at'=>time(),'note'=>$item['note'],'selected_matches'=>array_column($item['selected_matches'],'id'),'notification'=>$item['action']==='reject'?$email:null);}
  if(!update_post_meta($id,'_trb_crm_inline_reviews',$reviews)){$wpdb->query('ROLLBACK');clean_post_cache($id);return new WP_Error('review_save_failed','Decisioni non salvate. Riprova.',array('status'=>500));}
  add_post_meta($id,'_trb_crm_inline_review_audit',array('request_key'=>$key,'reviewer'=>(int)$body['reviewer'],'at'=>time(),'decisions'=>$reviews));
  $wpdb->query('COMMIT');
  trb_crm_inline_apply_review($id,true);
  return array('ok'=>true,'notification'=>$email,'snapshot'=>trb_crm_inline_snapshot($id));
 } catch(Throwable $error){global $wpdb;$wpdb->query('ROLLBACK');clean_post_cache($id);return new WP_Error('review_failed','Operazione non completata: aggiorna lo stato prima di riprovare.',array('status'=>500));} finally {trb_release_process_unlock($lock);}
}
function trb_crm_inline_reference($id,$body) {
 $snapshot=trb_crm_inline_snapshot($id);if(is_wp_error($snapshot))return $snapshot;
 $match=null;foreach($snapshot['tracks'] as $track)if($track['index']===(int)($body['track']??-1))foreach($track['matches'] as $candidate)if(hash_equals($candidate['id'],(string)($body['match']??'')))$match=$candidate;
 if(!$match)return new WP_Error('match_missing','Corrispondenza non disponibile.');
 $cache='trb_reference_'.$match['id'];$cached=get_transient($cache);if(is_array($cached))return $cached;
 $term=implode(' ',(array)$match['artists']).' '.$match['title'];
 $url='https://itunes.apple.com/search?'.http_build_query(array('term'=>$term,'entity'=>'song','media'=>'music','country'=>'US','limit'=>8));
 $response=wp_remote_get($url,array('timeout'=>6,'redirection'=>0));$found=array();
 if(!is_wp_error($response)&&wp_remote_retrieve_response_code($response)===200){
  $data=json_decode(wp_remote_retrieve_body($response),true);
  $norm=static function($v){return preg_replace('/[^a-z0-9]/','',strtolower(remove_accents($v)));};
  foreach((array)($data['results']??array()) as $row){
   if($norm($row['trackName']??'')!==$norm($match['title'])||$norm($row['artistName']??'')!==$norm(implode(' ',(array)$match['artists'])))continue;
   $preview=(string)($row['previewUrl']??'');$link=(string)($row['trackViewUrl']??'');
   if(!preg_match('~^https://[a-zA-Z0-9.-]+\.mzstatic\.com/~',$preview)||!preg_match('~^https://(?:music\.apple\.com|itunes\.apple\.com)/~',$link))continue;
   $found[(string)$row['trackId']]=array('preview_url'=>$preview,'url'=>$link,'album'=>$row['collectionName']??'','source'=>'Apple Music — anteprima del catalogo; verifica la versione');
  }
 }
 $result=array('ok'=>true,'reference'=>count($found)===1?array_values($found)[0]:null,'search_url'=>'https://open.spotify.com/search/'.rawurlencode($term));
 set_transient($cache,$result,$result['reference']?DAY_IN_SECONDS:300);return $result;
}
// Reuse the existing authenticated CRM transport; do not exempt a new route from REST security.
add_filter('rest_pre_dispatch',static function($result,$server,$request){
 if($result!==null||$request->get_method()!=='POST'||!preg_match('#^/trb-crm/v1/release/(\d+)/?$#',$request->get_route(),$m))return $result;
 $body=(array)$request->get_json_params();if(!in_array($body['operation']??'',array('inline_review_snapshot','inline_review_apply','inline_review_reference'),true))return $result;
 if(!function_exists('trb_crm_sync_verify_release_request'))return new WP_Error('crm_unavailable','Connettore firmato non disponibile.',array('status'=>503));
 $auth=trb_crm_sync_verify_release_request($request);if(is_wp_error($auth))return $auth;
 $request->set_url_params(array('id'=>(int)$m[1]));return rest_ensure_response(trb_crm_inline_handle($request));
},11,3);
