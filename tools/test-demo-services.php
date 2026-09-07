<?php
define('ABSPATH',__DIR__);require __DIR__.'/../inc/trb-demo-services.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);$GLOBALS['checks']++;}
$checks=0;$review='Il verso sul telefono rende poco chiara l’azione e può essere precisato.';
$data=['id'=>'lyrics_revision','reason'=>'Se desideri un confronto sul verso del telefono, puoi lavorarci con il team dopo aver preparato il riferimento melodico.','evidence'=>$review];
function output_for($r,$d){return $r."\nTRB_SERVICE_JSON: ".json_encode($d,JSON_UNESCAPED_UNICODE);}
$r=trb_demo_extract_service_selection(output_for($review,$data),false);check($r['review']===$review&&!empty($r['selection']),'grounded suggestion accepted, metadata stripped');
$data['id']='stereo_mastering';check(!trb_demo_extract_service_selection(output_for($review,$data),false)['selection'],'no sonic sale for text only');
$data['id']='invented';check(!trb_demo_extract_service_selection(output_for($review,$data),true)['selection'],'unknown offer excluded');
$data['id']='lyrics_revision';$data['evidence']='Invented sentence about a bad recording';check(!trb_demo_extract_service_selection(output_for($review,$data),true)['selection'],'evidence must occur in final review');
$data['evidence']=$review;$data['reason']='Acquista per ottenere il 50% di sconto';check(!trb_demo_extract_service_selection(output_for($review,$data),false)['selection'],'model cannot generate discount claims');
check(!trb_demo_extract_service_selection($review."\nTRB_SERVICE_JSON: broken",false)['selection'],'invalid JSON discarded');
check(!str_contains(trb_demo_extract_service_selection($review."\n  TRB_SERVICE_JSON: broken",false)['review'],'TRB_SERVICE'),'indented metadata never mailed');
check(!trb_demo_extract_service_selection(output_for($review,['id'=>'']),false)['selection'],'no service is valid');
echo "PASS $checks service selection checks\n";
$data=['id'=>'','reason'=>'Per questo progetto non proponiamo un servizio: il passaggio sul telefono può essere chiarito con la revisione indicata.','evidence'=>$review];
$r=trb_demo_extract_service_selection(output_for($review,$data),false);check($r['status']==='none'&&!empty($r['selection']['reason']),'explicit no-service decision retains project reason');
check(trb_demo_extract_service_selection($review,false)['status']==='missing','missing decision distinguishable from no offer');
check(trb_demo_extract_service_selection($review."\nTRB_SERVICE_JSON: broken",false)['status']==='invalid','broken decision is observable');
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}function esc_url($s){return $s;}
check(str_contains(trb_demo_service_recommendation_html($r['selection']),'telefono'),'no recommendation is explained, not silently omitted');
echo "PASS decision observability and honest no-service explanation\n";
