<?php
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();echo json_encode(['success'=>false,'inspection_unconfirmed'=>true])."\n";exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';$theme='/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy';
if(trim((string)file_get_contents($theme.'/.trb-deployed-sha'))!=='f87f532df994867e3a259581cc7d20038fc0cbfc')exit(2);
require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');require_once $root.'/app/OnboardingRuntime.php';
$db=\TrbCrm\Database::connection();$l=new \TrbCrm\OnboardingLedger($db);$p=$l->forContract(27);
if(!$p||$p['email']!=='a.tognassi@gmail.com'||$p['snapshot']['contract_number']!=='TRB-QA-NONVALIDO-DDB600-20261003'||$p['snapshot']['template_key']!=='ddb_ccad_600')throw new RuntimeException();

$id=$p['id'];$q=$db->prepare('SELECT checked_at,document_fingerprint FROM onboarding_identity_checks WHERE practice_id=?');$q->execute([$id]);$check=$q->fetch(\PDO::FETCH_ASSOC);
$files=$l->files($id);$decision=$l->identityResult($id);
if(!$check||$check['checked_at']!=='2026-10-03T10:41:31+00:00'||($decision['reason']??'')!=='tax_document_required'||!hash_equals($check['document_fingerprint'],hash('sha256',json_encode($files,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))))throw new RuntimeException();
$bridge=static fn(array $x):array=>\TrbCrm\OnboardingTransport::script((string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_URL',''),(string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_SECRET',''),$x);
$archive=new \TrbCrm\OnboardingDrive($bridge);$start=microtime(true);$file=$archive->identityDocument($files['tax_front']);$archiveMs=(int)round((microtime(true)-$start)*1000);
$content=[['type'=>'input_text','text'=>'Classifica solo il tipo del documento fiscale visibile. Non trascrivere né restituire nomi, codici, date, numeri o qualunque dato personale. Una foto, scansione o screenshot può contenere un documento valido: considera il documento visibile anche con bordi, interfacce o altri elementi intorno. Distingui tessera sanitaria italiana, tesserino del codice fiscale, certificato ufficiale attribuzione codice fiscale, carta identità, retro tessera europea TEAM, altro documento, contenuto estraneo o immagine illeggibile. has_tax_code/name/birth_date significa campo stampato visibile, senza estrarne il valore. reason deve descrivere esclusivamente la categoria e la qualità, senza dati personali.'],
['type'=>'input_image','image_url'=>'data:'.$file['mime'].';base64,'.$file['data'],'detail'=>'high']];
$properties=['kind'=>['type'=>'string','enum'=>['italian_health_card','tax_code_card','official_tax_code_certificate','identity_card','european_health_card_back','other_document','unrelated_content','unreadable']],'has_tax_code'=>['type'=>'boolean'],'has_name'=>['type'=>'boolean'],'has_birth_date'=>['type'=>'boolean'],'fully_visible'=>['type'=>'boolean'],'has_surrounding_interface'=>['type'=>'boolean'],'reason'=>['type'=>'string','enum'=>['document_visible_and_legible','wrong_side','wrong_document_type','missing_printed_fields','cropped','blurred','no_document_visible']]];
$payload=['model'=>'gpt-4.1-mini','store'=>false,'input'=>[['role'=>'user','content'=>$content]],'max_output_tokens'=>500,'text'=>['format'=>['type'=>'json_schema','name'=>'document_kind_only','strict'=>true,'schema'=>['type'=>'object','properties'=>$properties,'required'=>array_keys($properties),'additionalProperties'=>false]]]];
$start=microtime(true);$ch=curl_init('https://api.openai.com/v1/responses');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>60,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.\TrbCrm\Env::get('OPENAI_API_KEY','')]]);
$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$data=is_string($raw)?json_decode($raw,true):null;
if($status!==200||!is_array($data)||($data['status']??'')!=='completed')throw new RuntimeException();
$text='';foreach($data['output']??[] as $item)foreach($item['content']??[] as $part)if(($part['type']??'')==='output_text')$text.=$part['text'];
$fields=json_decode($text,true);if(!is_array($fields)||array_diff(array_keys($properties),array_keys($fields)))throw new RuntimeException();
foreach($properties as $key=>$property){if(isset($property['enum'])&&!in_array($fields[$key],$property['enum'],true))throw new RuntimeException();if($property['type']==='boolean'&&!is_bool($fields[$key]))throw new RuntimeException();}
$out=['success'=>true,'contract_id'=>27,'document_type_only'=>$fields,'archive_ms'=>$archiveMs,'classification_ms'=>(int)round((microtime(true)-$start)*1000),'no_practice_changes'=>true];
while(ob_get_level())ob_end_clean();echo json_encode($out)."\n";
