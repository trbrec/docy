<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$stage='bootstrap';
set_exception_handler(static function()use(&$stage){echo json_encode(['audit'=>'unconfirmed','stage'=>$stage]);exit(1);});
$crm='/home/customer/www/crm.trbrec.com/public_html';
require_once $crm.'/app/Core.php';
\TrbCrm\Env::load($crm.'/.env');
$db=\TrbCrm\Database::connection();
$stage='metadata';
$r=['audit'=>'read-only'];
$r['asset_columns']=$db->query('SHOW COLUMNS FROM assets')->fetchAll(PDO::FETCH_COLUMN);
$r['runtime_keys']=$db->query("SELECT state_key FROM crm_runtime_state WHERE state_key LIKE '%material%' OR state_key LIKE '%pcloud%' OR state_key LIKE '%migrat%' OR state_key LIKE '%recover%'")->fetchAll(PDO::FETCH_COLUMN);
$r['tables']=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$stage='targets';
$q=$db->prepare("SELECT s.id submission_id,c.artist_name,a.* FROM submissions s JOIN contacts c ON c.id=s.contact_id LEFT JOIN assets a ON a.submission_id=s.id WHERE c.artist_name LIKE ? OR c.artist_name LIKE ? ORDER BY s.id,a.id");
$q->execute(['%Polite%','%Tramp%']);
$r['targets']=[];
foreach($q as $a){
$r['targets'][]=['artist'=>$a['artist_name'],'submission_id'=>(int)$a['submission_id'],'asset_id'=>(int)$a['id'],'kind'=>$a['kind']??null,'status'=>$a['access_status']??null,'mime'=>$a['mime_type']??null,'size'=>$a['byte_size']??null,'extension'=>pathinfo((string)($a['filename']??''),PATHINFO_EXTENSION),'pcloud_id_present'=>!empty($a['pcloud_file_id']),'pcloud_path_present'=>!empty($a['pcloud_path']),'local_path_present'=>!empty($a['local_archive_path']),'source_host'=>parse_url((string)($a['source_url']??''),PHP_URL_HOST),'canonical_relative'=>str_starts_with((string)($a['canonical_url']??''),'/api/materials/')?$a['canonical_url']:null];
}
$stage='source';
foreach(['Controller.php','SubmissionRepository.php'] as $f){
$code=file_get_contents($crm.'/app/'.$f);
$tokens=token_get_all($code);$out=[];$capture=false;$name='';$depth=0;$started=false;$buf='';
foreach($tokens as $t){
$v=is_array($t)?$t[1]:$t;
if(!$capture&&is_array($t)&&$t[0]===T_FUNCTION){$capture=true;$name='';$depth=0;$started=false;$buf=$v;continue;}
if(!$capture)continue;
$buf.=$v;
if($name===''&&is_array($t)&&$t[0]===T_STRING)$name=$v;
if($v==='{'){$depth++;$started=true;}elseif($v==='}'){$depth--;if($started&&$depth===0){if(preg_match('/^(streamMaterial|streamFile|materialLocalFile|materialAccessUrl|legacyMaterialLocalFile|streamPcloud.*|streamRemote.*|portalWebdav.*)$/i',$name))$out[$name]=$buf;$capture=false;}}
}
$r['source'][$f]=$out;
}
echo json_encode($r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
