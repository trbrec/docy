<?php
/** Read only source diagnostics for the website Demo bridge; never boots CRM or reads its database. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$revision=$argv[1]??'';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision)exit(2);
$root='/home/customer/www/crm.trbrec.com/public_html';
$repository=(string)@file_get_contents($root.'/app/SubmissionRepository.php');
$js=(string)@file_get_contents($root.'/assets/app-20260831-r27.js');
if($repository===''||$js===''){echo "CRM_DEMO_SOURCE_UNAVAILABLE\n";exit(1);}
$sections=[];
foreach(['updateMetadata'=>'assignContractNumber','find'=>'updateStatus','ingestFluentForm'=>'ensureInboundEventsTable'] as $name=>$next){
 $start=strpos($repository,'function '.$name.'(');
 if($start===false)continue;
 $end=strpos($repository,'function '.$next.'(',$start+20);
 if($end===false)$end=strpos($repository,"\n    public function ",$start+20);
 $body=substr($repository,$start,min(18000,($end===false?strlen($repository):$end)-$start));
 $lines=explode("\n",$body);$selected=[];
 foreach($lines as $i=>$line)if(preg_match('/material_source_url|material_url|materialChanged|materialRaw|materialUrls|source_url=|is_primary|social_reference|socialnetwork_demo/',$line)){
  for($j=max(0,$i-1);$j<=min(count($lines)-1,$i+1);$j++)$selected[$j]=$lines[$j];
 }
 ksort($selected);$sections[$name]=array_values($selected);
}
$snippets=[];
foreach(['material_url:','material_source_url:','detailMaterialUrl','saveWorkflow'] as $needle){
 $offset=0;$found=0;
 while(($at=strpos($js,$needle,$offset))!==false&&$found++<4){$snippets[]=substr($js,max(0,$at-200),750);$offset=$at+strlen($needle);}
}
echo json_encode(['repository_sha256'=>hash('sha256',$repository),'js_sha256'=>hash('sha256',$js),'sections'=>$sections,'client'=>$snippets],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
