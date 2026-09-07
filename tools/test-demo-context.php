<?php
define('ABSPATH', __DIR__);
require __DIR__ . '/../inc/trb-demo-context.php';
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function check($v,$message){if(!$v)throw new RuntimeException($message);}
$author=['focus'=>'lyrics','lyrics'=>'own','music'=>'ai_generated','performance'=>'ai_generated','notes'=>'Voglio migliorare immagini e metrica'];
check(''===trb_demo_context_error($author,true,true,false,false),'author with AI demo accepted');
$composer=['focus'=>'composition','lyrics'=>'third_party','music'=>'own','performance'=>'third_party'];
check(''===trb_demo_context_error($composer,true,true,false,false),'composer with third-party performance accepted');
$bad=$author;$bad['focus']='performance';
check(''!==trb_demo_context_error($bad,true,true,false,false),'AI cannot be a human performance assessment');
check(''!==trb_demo_context_error([],true,true,false,false),'missing scope rejected');
$bad=$composer;$bad['focus']='injected';
check(''!==trb_demo_context_error($bad,true,true,false,false),'unknown scope rejected');
$only=$author;$only['music']='absent';$only['performance']='absent';
check(''===trb_demo_context_error($only,true,false,false,true),'text-only assessment accepted');
check(''!==trb_demo_context_error($composer,true,false,false,true),'composition without audio rejected');
$instrumental=$composer;$instrumental['lyrics']='absent';
check(''===trb_demo_context_error($instrumental,false,true,true,false),'instrumental composer accepted');
check(''!==trb_demo_context_error($author,false,true,true,false),'lyrics without original text rejected');
$prompt=trb_demo_review_prompt(['review_context'=>$author,'title'=>'Example','genre'=>'Pop'],false,true);
check(str_contains($prompt,'Non hai audio da ascoltare'),'text route cannot claim listening');
check(str_contains($prompt,'Generato con IA'),'provenance reaches model');
check(str_contains($prompt,'Non dichiarato')===false,'provided provenance retained');
check(str_contains(trb_demo_review_prompt([],true,false),'Non dichiarato'),'legacy payload does not invent role');
$titles=['Obiettivo e materiale','Punti riusciti','Analisi approfondita','Proposte di revisione','Piano di lavoro','Limiti della valutazione'];
$complete=implode("\n",array_map(fn($t)=>"## ".$t."\nContenuto di prova sufficientemente dettagliato.",$titles));
check(trb_demo_review_structure_valid($complete),'complete structure accepted');
check(!trb_demo_review_structure_valid('Recensione generica.'),'unstructured answer rejected');
check(!trb_demo_review_structure_valid(str_replace('## Piano di lavoro','## Altro',$complete)),'missing section rejected');
class WP_Error {public $code;function __construct($code,$message=''){$this->code=$code;}}
function is_wp_error($v){return $v instanceof WP_Error;}
function trb_demo_settings(){return ['openai_key'=>'TEST','text_model'=>'text-test','audio_model'=>'audio-test'];}
function trb_demo_extract_text($v){return $GLOBALS['fixture_text'];}
function trb_demo_local_path($v){return __FILE__;}
function wp_kses_post($v){return strip_tags($v);}
function sanitize_text_field($v){return strip_tags($v);}
function wp_remote_post($url,$args){$GLOBALS['api_request']=json_decode($args['body'],true);return ['code'=>200,'body'=>json_encode(['choices'=>[['message'=>['content'=>$GLOBALS['answer']],'finish_reason'=>$GLOBALS['finish']]],'usage'=>[]])];}
function wp_remote_retrieve_body($r){return $r['body'];}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function trb_demo_usage_and_cost($m,$u){return ['model'=>$m];}
$source=file_get_contents(__DIR__.'/../inc/trb-demo-automation.php');
check(1===preg_match('/function trb_demo_openai_review\(.*?(?=\nfunction )/s',$source,$match),'extract actual API routine');
eval($match[0]);
$fixture_text='Un testo originale da analizzare';$answer=$complete;$finish='stop';
$payload=['review_context'=>$author,'text_file'=>['name'=>'lyrics.txt'],'audio_file'=>['name'=>'demo.mp3'],'title'=>'Example','genre'=>'Pop'];
$r=trb_demo_openai_review($payload);
check(!is_wp_error($r),'author request succeeds');
check($api_request['model']==='text-test','author uses text model');
check(count($api_request['messages'][1]['content'])===1,'AI audio not submitted for lyrics-only focus');
check($api_request['messages'][0]['role']==='system','instructions separated from source text');
check(str_contains($api_request['messages'][1]['content'][0]['text'],$fixture_text),'original text present');
$payload['review_context']=$composer;
$r=trb_demo_openai_review($payload);
check(!is_wp_error($r)&&$api_request['model']==='audio-test','composer uses audio model');
check($api_request['messages'][1]['content'][1]['type']==='input_audio','composition includes audio');
$finish='length';check(is_wp_error(trb_demo_openai_review($payload)),'truncation prevents email');
$finish='stop';$answer='Una valutazione incompleta';check(is_wp_error(trb_demo_openai_review($payload)),'incomplete response rejected');
$answer=$complete;$fixture_text='';check(is_wp_error(trb_demo_openai_review($payload)),'unreadable text rejected');
echo "PASS demo scope, provenance, evidence, model routing and completeness\n";
