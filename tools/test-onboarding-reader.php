<?php
declare(strict_types=1);
/** Synthetic HTTP boundary: no credentials, network or real documents. */
namespace {
    foreach(['CURLOPT_POST','CURLOPT_POSTFIELDS','CURLOPT_RETURNTRANSFER','CURLOPT_CONNECTTIMEOUT','CURLOPT_TIMEOUT','CURLOPT_FOLLOWLOCATION','CURLOPT_HTTPHEADER','CURLINFO_RESPONSE_CODE'] as $i=>$constant)if(!defined($constant))define($constant,1000+$i);
    if(!defined('CURLM_OK'))define('CURLM_OK',0);
}
namespace TrbCrm {
    function curl_init($url){return (object)['url'=>$url,'options'=>[],'response'=>null,'status'=>200];}
    function curl_setopt_array($handle,$options){$handle->options=$options;return true;}
    function curl_multi_init(){return (object)['handles'=>[]];}
    function curl_multi_add_handle($multi,$handle){$multi->handles[]=$handle;return CURLM_OK;}
    function curl_multi_exec($multi,&$running){
        $GLOBALS['reader_batches'][]=count($multi->handles);
        foreach($multi->handles as $handle){
            $payload=json_decode($handle->options[CURLOPT_POSTFIELDS],true,512,JSON_THROW_ON_ERROR);
            $GLOBALS['reader_payloads'][]=$payload;$schema=$payload['text']['format']['schema'];
            $slot=str_starts_with($payload['text']['format']['name'],'tax_')?'tax_front':'identity_front';
            $fields=$GLOBALS['reader_fixture'][$slot];if($GLOBALS['reader_extra']??false)$fields['unexpected']='cross-document field';
            $handle->status=($GLOBALS['reader_fail']??'')===$slot?503:200;
            $handle->response=json_encode(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($fields)]]]]]);
        }
        $running=0;return CURLM_OK;
    }
    function curl_multi_select($multi,$timeout){return 1;}
    function curl_multi_getcontent($handle){return $handle->response;}
    function curl_getinfo($handle,$option){return $handle->status;}
    function curl_multi_remove_handle($multi,$handle){$GLOBALS['reader_cleaned']=($GLOBALS['reader_cleaned']??0)+1;return CURLM_OK;}
    function curl_close($handle){}
    function curl_multi_close($multi){}
}
namespace {
    require_once __DIR__.'/../integrations/onboarding/crm/OnboardingIdentity.php';
    function reader_check(bool $ok,string $message):void{if(!$ok)throw new \RuntimeException($message);}
    function reader_reject(callable $fn,string $message):void{try{$fn();}catch(\Throwable $e){return;}throw new \RuntimeException($message);}
    $reader_fixture=[
        'identity_front'=>['identity_document'=>true,'legible'=>true,'first_name'=>'Mario','last_name'=>'Rossi','birth_date'=>'1990-01-01','expiry_date'=>'2030-01-01'],
        'tax_front'=>['tax_document'=>true,'tax_legible'=>true,'tax_first_name'=>'Mario','tax_last_name'=>'Rossi','tax_birth_date'=>'1990-01-01','tax_code'=>'RSSMRA90A01H501W']
    ];
    $reader_payloads=[];$reader_batches=[];$reader_cleaned=0;
    $documents=[['slot'=>'identity_front','mime'=>'image/jpeg','data'=>base64_encode('synthetic-front-identity')],['slot'=>'tax_front','mime'=>'image/png','data'=>base64_encode('synthetic-front-health')]];
    $reader=new \TrbCrm\OnboardingIdentity('synthetic-key');$fields=$reader->extract($documents);
    reader_check($reader_batches===[2],'Both independent requests are started before awaiting either result');
    reader_check(count($reader_payloads)===2&&$reader_cleaned===2,'Both requests are bounded and cleaned');
    foreach($reader_payloads as $payload){
        reader_check($payload['store']===false,'Document responses are not stored by the reader');
        reader_check(count(array_filter($payload['input'][0]['content'],fn($part)=>$part['type']==='input_image'))===1,'Each read sees exactly its own front');
        $properties=array_keys($payload['text']['format']['schema']['properties']);
        reader_check(in_array('tax_code',$properties,true)!==in_array('first_name',$properties,true),'Fiscal and identity schemas cannot mix fields');
    }
    reader_check(\TrbCrm\OnboardingPolicy::documents(['first_name'=>'Mario','last_name'=>'Rossi'],$fields,'RSSMRA90A01H501W','2026-10-03')['status']==='matched','Independent real-document responses pass the ordinary identity and tax checks');
    $reader_fixture['tax_front']['tax_first_name']='Luigi';$fields=$reader->extract(array_reverse($documents));
    reader_check($fields['first_name']==='Mario'&&$fields['tax_first_name']==='Luigi','Reversed request order never borrows a name from the other front');
    reader_check(\TrbCrm\OnboardingPolicy::documents(['first_name'=>'Mario','last_name'=>'Rossi'],$fields,'RSSMRA90A01H501W','2026-10-03')['reason']==='tax_identity_mismatch','Foreign fiscal identity still rejects despite a valid card type');
    $reader_fixture['tax_front']['tax_document']=false;$fields=$reader->extract($documents);
    reader_check($fields['identity_document']===true&&$fields['tax_document']===false,'An unrelated fiscal image cannot falsify the identity card result');
    reader_check(\TrbCrm\OnboardingPolicy::documents(['first_name'=>'Mario','last_name'=>'Rossi'],$fields,'RSSMRA90A01H501W','2026-10-03')['reason']==='tax_document_required','Document type remains mandatory');
    $reader_fixture['identity_front']['identity_document']=false;$fields=$reader->extract($documents);
    reader_check(\TrbCrm\OnboardingPolicy::documents(['first_name'=>'Mario','last_name'=>'Rossi'],$fields,'RSSMRA90A01H501W','2026-10-03')['reason']==='documents_required','Two unrelated screenshots still cannot pass');
    $reader_fail='tax_front';reader_reject(fn()=>$reader->extract($documents),'One unavailable request cannot yield partial approval');$reader_fail='';
    $reader_extra=true;reader_reject(fn()=>$reader->extract($documents),'Unexpected cross-document fields are rejected');$reader_extra=false;
    $reader_fixture['tax_front']['tax_document']='true';reader_reject(fn()=>$reader->extract($documents),'String truth cannot replace a boolean document classification');$reader_fixture['tax_front']['tax_document']=true;
    $before=count($reader_payloads);reader_reject(fn()=>$reader->extract([$documents[0],$documents[0]]),'Duplicated front labels are rejected');reader_check(count($reader_payloads)===$before,'Invalid labels never issue a provider request');
    reader_reject(fn()=>$reader->extract([['slot'=>'tax_front','url'=>'https://untrusted.invalid/card.jpg','name'=>'card.jpg']]),'Untrusted document host is blocked');
    $single=$reader->extract([$documents[0]]);reader_check($single['tax_document']===false&&$single['tax_code']==='','Missing fiscal document cannot be inferred from the identity front');
    echo "Isolated parallel reader verified: independent images, strict schemas, mismatched tax identity, unrelated screenshots, partial failures and cleanup.\n";
}
