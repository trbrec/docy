<?php
/** Fixed boolean source diagnostics only; never boots CRM or reads runtime data. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$revision=$argv[1]??'';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision)exit(2);
ini_set('display_errors','0');ob_start();
$allowed=['source_loaded','client_sends_material_url','client_sends_material_source_url','metadata_reads_material_url','metadata_reads_material_source_url','find_aggregates_links','find_uses_primary_only','anchor_0','anchor_1','anchor_2','anchor_3','anchor_4','anchor_5'];
$clean=array_fill_keys($allowed,false);
register_shutdown_function(static function()use(&$clean){while(ob_get_level())ob_end_clean();echo json_encode($clean)."\n";});
$root='/home/customer/www/crm.trbrec.com/public_html';
$repository=(string)@file_get_contents($root.'/app/SubmissionRepository.php');
$js=(string)@file_get_contents($root.'/assets/app-20260831-r27.js');
if($repository===''||$js==='')exit(1);
$checks=[];
$checks['source_loaded']=true;
$checks['client_sends_material_url']=strpos($js,"material_url:document.getElementById('detailMaterialUrl').value")!==false;
$checks['client_sends_material_source_url']=strpos($js,'material_source_url:')!==false;
$start=strpos($repository,'function updateMetadata(');$end=$start!==false?strpos($repository,'function assignContractNumber(',$start):false;
$metadata=$start!==false&&$end!==false?substr($repository,$start,$end-$start):'';
$checks['metadata_reads_material_url']=strpos($metadata,"['material_url']")!==false;
$checks['metadata_reads_material_source_url']=strpos($metadata,"['material_source_url']")!==false;
$checks['find_aggregates_links']=strpos($repository,'array_map(static fn(array $row):string=>trim((string)($row[\'source_url\']??\'\')),$submission[\'assets\'])')!==false;
$checks['find_uses_primary_only']=strpos($repository,'$submission[\'material_source_url\'] = is_array($primaryAsset)')!==false||strpos($repository,'$submission[\'material_source_url\'] = (string)($primaryAsset[\'source_url\']')!==false;
$anchors=[
'anchor_0'=> <<<'SOURCE'
$social = trim($this->payloadValue($data, ['socialnetwork_demo','social_url','social']));
SOURCE,
'anchor_1'=> <<<'SOURCE'
$materialChanged = array_key_exists('material_url', $input);
SOURCE,
'anchor_2'=> <<<'SOURCE'
                $this->db->prepare("UPDATE assets SET source_url='',access_status='unknown',last_checked_at=NULL,error_message=NULL WHERE submission_id=? AND access_status<>'secured' AND COALESCE(pcloud_file_id,'')='' AND TRIM(COALESCE(source_url,''))<>''")->execute([$id]);
SOURCE,
'anchor_3'=> <<<'SOURCE'
                    $existing=$this->db->prepare('SELECT id FROM assets WHERE submission_id=? AND (source_url=? OR canonical_url=?) LIMIT 1');$existing->execute([$id,$url,$url]);$assetId=(int)($existing->fetchColumn()?:0);
SOURCE,
'anchor_4'=> <<<'SOURCE'
                    if($assetId>0)$this->db->prepare("UPDATE assets SET kind=?,provider=?,source_url=?,access_status=IF(access_status='secured','secured',?),last_checked_at=NULL,error_message=NULL,is_primary=? WHERE id=?")->execute([$kind,$provider,$url,$access,$index===0?1:0,$assetId]);
SOURCE,
'anchor_5'=> <<<'SOURCE'
                    $this->queueUniqueDiscoveryJob('verify_external_asset',['asset_id'=>$assetId,'submission_id'=>$id],$assetId);
SOURCE,
];
foreach($anchors as $key=>$anchor)$checks[$key]=substr_count($repository,$anchor)===1;
foreach($allowed as $key)$clean[$key]=($checks[$key]??false)===true;
