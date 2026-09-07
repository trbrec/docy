<?php
/** Owner-only presentation QA from an already reviewed evaluation. No new model call. Includes explicit table-cell font verification. */
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
$revision=$argv[1]??'';if(!preg_match('/^[a-f0-9]{40}$/D',$revision))exit(2);
$_SERVER['HTTP_HOST']='artist.trbrec.com';$_SERVER['REQUEST_URI']='/';$_SERVER['HTTPS']='on';define('DISABLE_WP_CRON',true);
require dirname(__DIR__,4).'/wp-load.php';
add_filter('pre_wp_mail',function($pre,$args){if($args['to']!=='andrea.tognassi@trbrec.com')throw new RuntimeException('QA recipient guard');return $pre;},PHP_INT_MAX,2);
$source=12314;$payload=get_post_meta($source,'_trb_demo_payload',true);
if(!is_array($payload)||empty($payload['owner_qa'])||$payload['email']!=='andrea.tognassi@trbrec.com')throw new RuntimeException('Not an owner QA source');
$key='trb_footer_preview_'.$revision;$id=(int)get_option($key);
if(!$id) {
 $payload['status']='ready';unset($payload['sent_at']);
 $id=wp_insert_post(array('post_type'=>'trb_request','post_status'=>'private','post_title'=>'[QA EMAIL] '.$payload['title'],'post_author'=>get_post_field('post_author',$source)),true);
 if(is_wp_error($id))throw new RuntimeException('Cannot create preview');update_option($key,$id,false);
 update_post_meta($id,'_trb_demo_payload',$payload);update_post_meta($id,'_trb_demo_review',get_post_meta($source,'_trb_demo_review',true));
 update_post_meta($id,'_trb_demo_revision_comparison',get_post_meta($source,'_trb_demo_revision_comparison',true));
 $selection=get_post_meta($source,'_trb_demo_service_selection',true);
 if(($selection['id']??'')!=='lyrics_revision')throw new RuntimeException('Unexpected service');
 $selection['reason']='Possiamo aiutarti a rendere più chiare le frasi sulla luce e sul telefono, mantenendo il significato che vuoi dare al brano.';
 update_post_meta($id,'_trb_demo_service_selection',$selection);update_post_meta($id,'_trb_demo_service_decision',array('status'=>'selected','diagnostic'=>'Owner-reviewed presentation preview'));
 update_post_meta($id,'_trb_demo_qa_source',$source);update_post_meta($id,'_trb_demo_presentation_only',true);
}
add_filter('wp_mail',function($args){$args['subject']='[ANTEPRIMA EMAIL] '.$args['subject'];return $args;});
trb_demo_send_review($id);
if((get_post_meta($id,'_trb_demo_payload',true)['status']??'')!=='sent')throw new RuntimeException('Preview not sent: '.get_post_meta($id,'_trb_demo_last_error',true));
echo 'PASS owner-only presentation email #'.$id."; no model call\n";
