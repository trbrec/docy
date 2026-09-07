<?php
define('ABSPATH', __DIR__);
require __DIR__ . '/../inc/trb-demo-context.php';
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function check($v,$message){if(!$v)throw new RuntimeException($message);}
// Progressive v2 submissions require only the selected contribution.
foreach (array('lyrics'=>'lyrics','composition'=>'music','performance'=>'performance') as $scope=>$part) {
 $ctx=['version'=>2,'focus'=>$scope,$part=>'own'];
 check(''===trb_demo_context_error($ctx,$scope==='lyrics',$scope!=='lyrics',false,false),'minimal scope accepted: '.$scope);
 check(''!==trb_demo_context_error($ctx,false,false,false,false),'missing material rejected: '.$scope);
 check(''!==trb_demo_context_error($ctx,true,true,false,false),'irrelevant attachment rejected: '.$scope);
 unset($ctx[$part]);check(''!==trb_demo_context_error($ctx,true,true,false,false),'missing relevant provenance rejected');
}
check(''!==trb_demo_context_error(['version'=>2,'focus'=>'performance','performance'=>'ai_generated'],false,true,false,false),'v2 AI performance rejected');
check(''===trb_demo_context_error(['version'=>2,'focus'=>'overall','lyrics'=>'absent','music'=>'own','performance'=>'third_party'],false,true,true,false),'overall instrumental accepted');
check(''!==trb_demo_context_error(['version'=>2,'focus'=>'overall','lyrics'=>'absent','music'=>'absent','performance'=>'absent'],false,false,true,true),'empty overall rejected');
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
check(trb_demo_review_structure_valid(str_replace('## ', '### ', $complete)),'heading depth tolerated');
check(trb_demo_review_structure_valid(str_replace('Contenuto di prova', "### Dettaglio\nContenuto di prova", $complete)),'subheading is not an empty root section');
check(!trb_demo_review_structure_valid('Recensione generica.'),'unstructured answer rejected');
check(!trb_demo_review_structure_valid(str_replace('## Piano di lavoro','## Altro',$complete)),'missing section rejected');
class WP_Error {public $code;function __construct($code,$message=''){$this->code=$code;}}
function is_wp_error($v){return $v instanceof WP_Error;}
function trb_demo_settings(){return ['openai_key'=>'TEST','text_model'=>'text-test','audio_model'=>'audio-test'];}
function trb_demo_extract_text($v){return $GLOBALS['fixture_text'];}
function trb_demo_local_path($v){return __FILE__;}
function wp_kses_post($v){return strip_tags($v);}
function sanitize_text_field($v){return strip_tags($v);}
function wp_remote_post($url,$args){
 $req=json_decode($args['body'],true);
 $editor=str_contains($req['messages'][0]['content'],'CONTROLLO EDITORIALE FINALE');
 $GLOBALS[$editor ? 'editor_request' : 'api_request']=$req;
 $GLOBALS['call_count']=($GLOBALS['call_count'] ?? 0)+1;
 if($editor && !empty($GLOBALS['editor_failure'])) return new WP_Error('timeout');
 return ['code'=>200,'body'=>json_encode(['choices'=>[['message'=>['content'=>$editor ? ($GLOBALS['editor_answer'] ?? $GLOBALS['answer']) : $GLOBALS['answer']],'finish_reason'=>$editor ? ($GLOBALS['editor_finish'] ?? $GLOBALS['finish']) : $GLOBALS['finish']]],'usage'=>['prompt_tokens'=>100,'completion_tokens'=>50,'total_tokens'=>150]])];
}
function wp_remote_retrieve_body($r){return $r['body'];}
function wp_remote_retrieve_response_code($r){return $r['code'];}

function absint($v){return abs((int)$v);}
function get_post($id){return $GLOBALS['posts'][$id] ?? null;}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key] ?? '';}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;}
function trb_demo_previous_bytes($id,$key,$file){return $GLOBALS['old_bytes'][$key] ?? '';}
$source=file_get_contents(__DIR__.'/../inc/trb-demo-automation.php');
foreach(['trb_demo_model_rates','trb_demo_usage_and_cost','trb_demo_record_editorial_usage'] as $fn) {
 check(1===preg_match('/function '.$fn.'\\(.*?(?=\\nfunction )/s',$source,$part),'extract '.$fn);
 eval($part[0]);
}

check(1===preg_match('/function trb_demo_revision_materials\(.*?(?=\nfunction )/s',$source,$revision_match),'extract actual revision routine');
eval($revision_match[0]);
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
$posts=[10=>(object)['ID'=>10,'post_type'=>'trb_request','post_status'=>'private','post_author'=>7],11=>(object)['ID'=>11,'post_type'=>'trb_request','post_status'=>'private','post_author'=>7],12=>(object)['ID'=>12,'post_type'=>'trb_request','post_status'=>'private','post_author'=>8]];
$meta=[10=>['_trb_demo_payload'=>['status'=>'sent','title'=>'Originale','audio_file'=>['name'=>'old.mp3']],'_trb_demo_review'=>$complete,'_trb_demo_text_snapshot'=>'Testo precedente esatto']];
check(is_wp_error(trb_demo_revision_snapshot(10,8)),'cross-artist link rejected');
check(is_wp_error(trb_demo_revision_snapshot(999,7)),'missing parent rejected');
$meta[10]['_trb_demo_payload']['status']='queued';
check(is_wp_error(trb_demo_revision_snapshot(10,7)),'unsent review rejected');
$meta[10]['_trb_demo_payload']['status']='sent';
$revision=trb_demo_revision_snapshot(10,7,'Ho modificato il finale');
check(!is_wp_error($revision)&&$revision['version']===2&&$revision['root_id']===10,'legacy sent demo supports first revision');
$meta[11]=['_trb_demo_payload'=>['status'=>'sent','revision'=>$revision],'_trb_demo_review'=>$complete];
$third=trb_demo_revision_snapshot(11,7);
check($third['version']===3&&$third['root_id']===10,'revision chain retains root and version');
$payload=['request_id'=>11,'revision'=>$revision,'review_context'=>$author,'text_file'=>['name'=>'new.txt']];
$fixture_text='Testo nuovo con finale modificato';$finish='stop';$answer=$complete;
check(!is_wp_error(trb_demo_openai_review($payload)),'revision API request succeeds');
$content=$api_request['messages'][1]['content'];
$sent_history=json_decode(substr($content[0]['text'],strpos($content[0]['text'],"\n")+1),true);
check(($sent_history['review'] ?? '')===$complete,'previous review reaches model');
check(str_contains($content[0]['text'],'Ho modificato il finale'),'declared changes reach model');
check(str_contains($content[1]['text'],'Testo precedente esatto'),'previous text reaches model');
check(str_contains($content[2]['text'],$fixture_text),'new text remains separate');
check(str_contains($api_request['messages'][0]['content'],'non assumere che fossero tutti corretti'),'old review is not treated as unquestionable');
check($meta[11]['_trb_demo_text_snapshot']===$fixture_text,'new text retained for future revisions');
$payload['request_id']=12;
check(is_wp_error(trb_demo_openai_review($payload)),'worker rechecks ownership');
$payload['request_id']=11;$payload['review_context']=$composer;$payload['audio_file']=['name'=>'new.mp3'];
$old_bytes=['audio_file'=>'OLD AUDIO FIXTURE'];
check(!is_wp_error(trb_demo_openai_review($payload)),'audio revision request succeeds');
$audios=array_values(array_filter($api_request['messages'][1]['content'],fn($c)=>$c['type']==='input_audio'));
check(count($audios)===2&&base64_decode($audios[0]['input_audio']['data'])==='OLD AUDIO FIXTURE','old audio and new audio separately supplied');
$editor_audios=array_values(array_filter($editor_request['messages'][1]['content'],fn($c)=>$c['type']==='input_audio'));
check($editor_audios===$audios,'editor rechecks the same old and new audio evidence');
$old_bytes=[];
check(!is_wp_error(trb_demo_openai_review($payload)),'missing old audio degrades to written history');
$audios=array_filter($api_request['messages'][1]['content'],fn($c)=>$c['type']==='input_audio');
check(count($audios)===1&&!$meta[11]['_trb_demo_revision_comparison']['previous_audio'],'missing old audio explicitly recorded');
echo "PASS demo scope, provenance, evidence, model routing and completeness\n";
echo "PASS revision ownership, legacy history, version chain, text/audio comparison and unavailable-material fallback\n";

// The final text is the editor's output, not the draft; both passes see source evidence.
$payload=['request_id'=>20,'review_context'=>$author,'text_file'=>['name'=>'new.txt']];
$fixture_text='Testo attuale concreto';$answer=$complete;$editor_answer=str_replace('Contenuto di prova','Testo finale controllato',$complete);
$call_count=0;
$r=trb_demo_openai_review($payload);
check(!is_wp_error($r) && $r['review']===$editor_answer,'only final editorial output returned');
check($call_count===2 && count($r['usage']['passes'])===2,'exactly two charged passes');
check($r['usage']['total_tokens']===300,'usage includes draft and editorial pass');
check($editor_request['messages'][1]['content'][0]===$api_request['messages'][1]['content'][0],'editor receives original source');
check(str_contains(end($editor_request['messages'][1]['content'])['text'],$complete),'draft clearly separated as data');
check($meta[20]['_trb_demo_editorial_check']['status']==='completed','editor completion recorded');
$editor_failure=true;$payload['request_id']=21;
check(is_wp_error(trb_demo_openai_review($payload)),'editor network failure blocks output');
check(empty($meta[21]['_trb_demo_editorial_check']),'failed editor never marked complete');
check($meta[21]['_trb_demo_openai_usage']['total_tokens']===150,'billed draft retained after editor failure');
$editor_failure=false;$editor_finish='length';$payload['request_id']=22;
check(is_wp_error(trb_demo_openai_review($payload)),'truncated editor blocks output');
check($meta[22]['_trb_demo_openai_usage']['total_tokens']===300,'truncated billed pass counted');
$editor_finish='stop';$editor_answer='Risposta incompleta';
check(is_wp_error(trb_demo_openai_review($payload)),'incomplete editor blocks output');
check($meta[22]['_trb_demo_openai_usage']['total_tokens']===600,'retry costs accumulate');
check(trb_demo_qa_title('[QA LYRICS] [QA LYRICS] [QA] Brano','lyrics')==='[QA LYRICS] Brano','QA prefixes deduplicated');
check(trb_demo_qa_title('Brano [QA] nel titolo','lyrics')==='[QA LYRICS] Brano [QA] nel titolo','only leading QA markers removed');
echo "PASS editorial evidence, output gate, retries, billed usage and QA naming\n";
