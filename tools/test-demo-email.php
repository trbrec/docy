<?php
define('ABSPATH',__DIR__);
require __DIR__.'/../inc/trb-demo-context.php';
function check($ok,$why){if(!$ok)throw new RuntimeException($why);}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function wpautop($s){return $s;}
function absint($s){return abs((int)$s);}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key] ?? '';}
function update_post_meta($id,$key,$v){$GLOBALS['meta'][$id][$key]=$v;}
function delete_post_meta($id,$key){unset($GLOBALS['meta'][$id][$key]);}
function trb_demo_defer_review_if_needed($id,$payload){return false;}
function trb_store_benefits_live(){return $GLOBALS['benefits_live'] ?? true;}
function trb_demo_settings(){return ['artist_discount_code'=>'TEST50'];}
function trb_demo_is_test_payload($p){return !empty($p['owner_qa']);}
function wp_mail($to,$subject,$body,$headers){$GLOBALS['mail']=compact('to','subject','body','headers');return true;}
$src=file_get_contents(__DIR__.'/../inc/trb-demo-automation.php');
foreach(['trb_demo_review_html','trb_demo_services_note','trb_demo_send_review'] as $fn){
 preg_match('/function '.$fn.'\(.*?(?=\nfunction |\nadd_action\()/s',$src,$m);
 eval($m[0]);
}
$html=trb_demo_review_html("## Obiettivo e materiale\n*Titolo* e **forte**\n## Dettaglio\n1. Prima azione concreta\n<script>alert(1)</script>\n---");
check(str_contains($html,'<em>Titolo</em>')&&str_contains($html,'<strong>forte</strong>'),'inline formatting rendered');
check(substr_count($html,'<h2')===1&&substr_count($html,'<h3')===1,'six-root hierarchy preserved');
check(!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;'),'untrusted HTML escaped');
check(!str_contains($html,'---'),'decorative rule removed');
$payload=['status'=>'ready','email'=>'andrea.tognassi@trbrec.com','first_name'=>'Andrea','last_name'=>'Tognassi','artist_name'=>'QA TRB rec','profile'=>'ddb','owner_qa'=>true,'title'=>'[QA LYRICS] Brano','revision'=>['version'=>2,'parent_id'=>10],'review_context'=>['focus'=>'lyrics']];
$meta=[11=>['_trb_demo_payload'=>$payload,'_trb_demo_review'=>'Testo verificato.','_trb_demo_revision_comparison'=>['previous_text'=>true,'previous_audio'=>false]]];
trb_demo_send_review(11);
check($mail['to']==='andrea.tognassi@trbrec.com'&&!str_contains(implode(' ',$mail['headers']),'Cc:'),'QA remains owner-only');
check(str_contains($mail['subject'],'revisione v2'),'revision subject explicit');
check(str_contains($mail['body'],'testo precedente')&&!str_contains($mail['body'],'audio precedente'),'comparison reflects actual materials');
check(!str_contains($mail['body'],'TEST50')&&str_contains($mail['body'],'50%')&&str_contains($mail['body'],'stessa email'),'DDB receives account benefit without coupon');
$benefits_live=false;check(trb_demo_services_note('ddb','TEST50',['owner_qa'=>true])==='','no announcement before bridge activation');$benefits_live=true;
check($meta[11]['_trb_demo_payload']['status']==='sent','successful send recorded');
$mail=null;trb_demo_send_review(11);check($mail===null,'sent review cannot be sent twice');
$payload['owner_qa']=false;$payload['email']='artist@example.test';$payload['profile']='trb';unset($payload['revision']);
$meta[12]=['_trb_demo_payload'=>$payload,'_trb_demo_review'=>'Testo verificato.'];
trb_demo_send_review(12);
check(str_contains(implode(' ',$mail['headers']),'Cc: Andrea Tognassi <andrea.tognassi@trbrec.com>'),'real artist retains owner copy');
check(!str_contains($mail['body'],'TEST50'),'TRB excluded from paid services');
check(!str_contains($mail['subject'],'revisione'),'first submission not presented as revision');
$payload['profile']='admin';$meta[13]=['_trb_demo_payload'=>$payload,'_trb_demo_review'=>'Testo verificato.'];
trb_demo_send_review(13);
check(str_contains($mail['body'],'Non dichiarata')&&!str_contains($mail['body'],'Digital Distribution Bundle'),'unknown profile never labeled as DDB');
echo "PASS email escaping, hierarchy, version, comparison evidence, recipients, discounts and duplicate-send guard\n";
