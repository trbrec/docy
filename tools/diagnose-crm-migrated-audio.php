<?php
/** Read-only audit of all retained migrated media. No emails or media copies. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');$stage='bootstrap';
set_exception_handler(static function()use(&$stage){echo json_encode(['audit'=>'unconfirmed','stage'=>$stage])."\n";exit(1);});
$crm='/home/customer/www/crm.trbrec.com/public_html';
require_once $crm.'/app/Core.php';\TrbCrm\Env::load($crm.'/.env');
$db=\TrbCrm\Database::connection();
$all=$db->query("SELECT m.*,a.submission_id current_submission,a.pcloud_path current_path,a.filename current_filename,a.content_sha256 current_sha,a.byte_size current_size,a.source_url,a.kind current_kind,a.access_status current_status,l.demo_upload legacy_source FROM pcloud_material_associations m LEFT JOIN assets a ON a.id=m.asset_id LEFT JOIN legacy_demo_archive l ON l.id=m.legacy_id WHERE m.retention_state='retained'")->fetchAll(PDO::FETCH_ASSOC);
$assets=$db->query("SELECT a.*,s.received_at FROM assets a JOIN submissions s ON s.id=a.submission_id WHERE a.access_status='secured' AND a.pcloud_path IS NOT NULL AND a.pcloud_path<>'' AND s.received_at>='2025-11-01'")->fetchAll(PDO::FETCH_ASSOC);
$r=['audit'=>'retained-migrated-media','association_count'=>count($all),'active_asset_count'=>count($assets),'targets'=>[]];
$q=$db->query('SELECT id,status,contract_type FROM submissions WHERE id IN(20,21)');$r['targets']=$q->fetchAll(PDO::FETCH_ASSOC);
define('DISABLE_WP_CRON',true);define('WP_USE_THEMES',false);
ob_start();require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';ob_end_clean();
$settings=trb_demo_settings();$endpoint=rtrim($settings['webdav_endpoint'],'/');
if(!in_array($endpoint,['https://ewebdav.pcloud.com','https://webdav.pcloud.com'],true))throw new RuntimeException('Endpoint');
$options=[CURLOPT_USERPWD=>$settings['pcloud_user'].':'.$settings['pcloud_pass'],CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_RETURNTRANSFER=>true];
$paths=[];foreach($all as $a)$paths[$a['pcloud_path']]=['size'=>(int)$a['byte_size'],'ext'=>strtolower(pathinfo($a['filename'],PATHINFO_EXTENSION))];foreach($assets as $a)$paths[$a['pcloud_path']]=['size'=>(int)$a['byte_size'],'ext'=>strtolower(pathinfo($a['filename'],PATHINFO_EXTENSION))];
function audit_requests(array $tasks,array $options):array{
$multi=curl_multi_init();$pending=array_keys($tasks);$active=[];$results=[];
$add=static function($key)use($tasks,$options,$multi,&$active){$t=$tasks[$key];$ch=curl_init($t['url']);$op=$options;if(isset($t['range']))$op[CURLOPT_RANGE]=$t['range'];else $op[CURLOPT_NOBODY]=true;curl_setopt_array($ch,$op);curl_multi_add_handle($multi,$ch);$active[spl_object_id($ch)]=[$key,$ch];};
while($pending||$active){
while(count($active)<8&&$pending)$add(array_shift($pending));
do{$m=curl_multi_exec($multi,$running);}while($m===CURLM_CALL_MULTI_PERFORM);
while($info=curl_multi_info_read($multi)){
$ch=$info['handle'];[$key]=$active[spl_object_id($ch)];
$results[$key]=['status'=>(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE),'length'=>(int)curl_getinfo($ch,CURLINFO_CONTENT_LENGTH_DOWNLOAD),'mime'=>(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE),'error'=>(int)$info['result'],'body'=>curl_multi_getcontent($ch)];
curl_multi_remove_handle($multi,$ch);unset($active[spl_object_id($ch)]);curl_close($ch);
}
if($active)curl_multi_select($multi,0.5);
}
curl_multi_close($multi);return $results;
}
$url=static fn($path)=>$endpoint.'/'.implode('/',array_map('rawurlencode',explode('/',ltrim($path,'/'))));
$stage='remote-head';$tasks=[];foreach($paths as $p=>$v)$tasks[$p]=['url'=>$url($p)];
$head=audit_requests($tasks,$options);$r['remote_paths']=count($head);$r['head_failures']=[];
foreach($head as $p=>$v)if($v['error']!==0||$v['status']!==200||$v['length']!==$paths[$p]['size'])$r['head_failures'][]=['path_key'=>hash('sha256',$p),'status'=>$v['status'],'remote_size'=>$v['length'],'expected_size'=>$paths[$p]['size'],'error'=>$v['error']];
echo json_encode(['progress'=>'remote-head-complete','checked'=>$r['remote_paths'],'failures'=>count($r['head_failures'])])."\n";
$stage='remote-ranges';$tasks=[];
foreach($paths as $p=>$v){
if(in_array($v['ext'],['mp3','wav','m4a','aac','ogg','flac','opus','aif','aiff','zip'],true)&&$v['size']>0&&($head[$p]['status']??0)===200){
$start=$v['ext']==='zip'?max(0,$v['size']-70000):0;$end=$v['ext']==='zip'?$v['size']-1:min(4095,$v['size']-1);
$tasks[$p]=['url'=>$url($p),'range'=>$start.'-'.$end,'start'=>$start,'length'=>$end-$start+1];
}
}
$reads=audit_requests($tasks,$options);$r['range_paths']=count($reads);$r['range_failures']=[];$zip=[];$r['audio_types']=[];
foreach($reads as $p=>$v){
$b=(string)$v['body'];$ext=$paths[$p]['ext'];
if($v['error']!==0||$v['status']!==206||strlen($b)!==$tasks[$p]['length']){$r['range_failures'][]=['path_key'=>hash('sha256',$p),'status'=>$v['status'],'bytes'=>strlen($b),'error'=>$v['error']];continue;}
if($ext==='zip'){
$pos=strrpos($b,"PK\x05\x06");if($pos===false||strlen($b)-$pos<22){$zip[$p]=null;continue;}
$e=unpack('vdisk/vcd_disk/ventries_disk/ventries/Vcd_size/Vcd_offset/vcomment',substr($b,$pos+4,18));$offset=$e['cd_offset']-$tasks[$p]['start'];
if($offset<0||$offset+$e['cd_size']>strlen($b)){$zip[$p]=null;continue;}
$cd=substr($b,$offset,$e['cd_size']);$entries=[];$at=0;
while(substr($cd,$at,4)==="PK\x01\x02"){
if(strlen($cd)-$at<46)break;$lens=unpack('vname/vextra/vcomment',substr($cd,$at+28,6));$data=unpack('Vcrc/Vcompressed/Vsize',substr($cd,$at+16,12));$name=substr($cd,$at+46,$lens['name']);$entries[]=[$name,$data['crc'],$data['size']];$at+=46+$lens['name']+$lens['extra']+$lens['comment'];
}
sort($entries);$zip[$p]=count($entries)===$e['entries']?hash('sha256',serialize($entries)):null;
}else{
$kind=substr($b,0,3)==='ID3'||(strlen($b)>1&&ord($b[0])===255&&(ord($b[1])&224)===224)?'mp3':(substr($b,0,4)==='RIFF'?'wav':(substr($b,0,4)==='fLaC'?'flac':(substr($b,0,4)==='OggS'?'ogg':(substr($b,4,4)==='ftyp'?'mp4':'unconfirmed'))));
$r['audio_types'][$kind]=($r['audio_types'][$kind]??0)+1;
if($kind==='unconfirmed')$r['range_failures'][]=['path_key'=>hash('sha256',$p),'type'=>'audio-signature-unconfirmed'];
}
}
$stage='association-check';
$r['associations']=['verified'=>0,'unconfirmed'=>[],'alternate_identical'=>0,'zip_equivalent'=>0,'owner_mismatch'=>0];
foreach($all as $a){
$p=$a['pcloud_path'];$ev=json_decode($a['evidence_json'],true)?:[];
$remoteOk=($head[$p]['status']??0)===200&&($head[$p]['length']??0)===(int)$a['byte_size'];
if($a['asset_id']!==null){
$owner=(int)$a['submission_id']===(int)$a['current_submission'];if(!$owner)$r['associations']['owner_mismatch']++;
$hash=$a['content_sha256']===$a['current_sha']&&preg_match('/^[a-f0-9]{64}$/',$a['content_sha256']);
$equiv=isset($zip[$p],$zip[$a['current_path']])&&$zip[$p]===$zip[$a['current_path']];
$source=(string)($ev['source_url']??'');
$sourceOk=$source!==''&&($source===$a['source_url']||$source===$a['current_path']);
$nameOk=basename(rawurldecode((string)parse_url((string)$a['source_url'],PHP_URL_PATH)))===$a['filename']||$a['current_filename']===$a['filename'];
$proof=$sourceOk||($hash&&$nameOk);
$ok=$remoteOk&&$owner&&($hash||$equiv)&&$proof;
if($ok&&$a['pcloud_path']!==$a['current_path']){if($hash)$r['associations']['alternate_identical']++;elseif($equiv)$r['associations']['zip_equivalent']++;}
}else{
$raw=(string)($a['legacy_source']??'');$name=basename(rawurldecode((string)parse_url($raw,PHP_URL_PATH)));
$ok=$remoteOk&&$a['legacy_id']!==null&&($name===$a['filename']||str_contains(rawurldecode($raw),$a['filename']));
}
if($ok)$r['associations']['verified']++;else $r['associations']['unconfirmed'][]=['path_key'=>$a['path_key'],'asset_id'=>$a['asset_id'],'legacy_id'=>$a['legacy_id'],'kind'=>$a['source_kind'],'remote'=>$remoteOk,'owner'=>$owner??null,'hash'=>$hash??null,'zip_equivalent'=>$equiv??null,'source'=>$sourceOk??null,'name'=>$nameOk??null];
}
$r['live_assets_without_manifest']=[];
foreach($assets as $a){$matches=array_filter($all,static fn($m)=>(int)($m['asset_id']??0)===(int)$a['id']&&$m['content_sha256']===$a['content_sha256']);if(!$matches)$r['live_assets_without_manifest'][]=(int)$a['id'];}
$r['unlisted_assets_detail']=[];
foreach($assets as $a)if(in_array((int)$a['id'],$r['live_assets_without_manifest'],true)){
 $sameOwner=array_values(array_filter($all,static fn($m)=>(int)($m['asset_id']??0)===(int)$a['id']));
 $equiv=array_filter($sameOwner,static fn($m)=>isset($zip[$m['pcloud_path']],$zip[$a['pcloud_path']])&&$zip[$m['pcloud_path']]===$zip[$a['pcloud_path']]);
 $parsed=parse_url((string)$a['source_url']);$srcName=basename(rawurldecode($parsed['path']??''));
 $r['unlisted_assets_detail'][]=['id'=>(int)$a['id'],'submission'=>(int)$a['submission_id'],'provider'=>$a['provider'],'kind'=>$a['kind'],'mode'=>$a['archive_mode'],'ext'=>strtolower(pathinfo($a['filename'],PATHINFO_EXTENSION)),'source_host'=>$parsed['host']??null,'source_filename_matches'=>$srcName===$a['filename'],'source_path_matches'=>$a['source_url']===$a['pcloud_path'],'sha_valid'=>(bool)preg_match('/^[a-f0-9]{64}$/',(string)$a['content_sha256']),'manifest_same_asset'=>count($sameOwner),'manifest_zip_equivalent'=>count($equiv),'created'=>$a['created_at'],'received'=>$a['received_at'],'path_submission_match'=>str_contains($a['pcloud_path'],'/'.(string)$a['submission_id'].'/')];
}
$r['unknown_audio_details']=[];
foreach($r['range_failures'] as $f)if(($f['type']??'')==='audio-signature-unconfirmed'){
 foreach($paths as $p=>$v)if(hash('sha256',$p)===$f['path_key']){
  $extra=audit_requests([$p=>['url'=>$url($p),'range'=>'0-'.min(65535,$v['size']-1)]],$options)[$p];$b=(string)$extra['body'];
  $found=[];foreach(['ID3','RIFF','RIFX','RF64','fLaC','OggS','ftyp','FORM','ADIF'] as $sig){$pos=strpos($b,$sig);if($pos!==false)$found[$sig]=$pos;}
  $frame=null;for($i=0;$i<strlen($b)-1;$i++)if(ord($b[$i])===255&&(ord($b[$i+1])&224)===224){$frame=$i;break;}
  $r['unknown_audio_details'][]=['path_key'=>$f['path_key'],'ext'=>$v['ext'],'prefix'=>bin2hex(substr($b,0,32)),'signatures'=>$found,'frame_offset'=>$frame,'asset_ids'=>array_values(array_map(static fn($a)=>(int)$a['id'],array_filter($assets,static fn($a)=>$a['pcloud_path']===$p)))];
 }
}
$r['runtime_shapes']=[];$states=$db->query("SELECT state_key,state_value FROM crm_runtime_state WHERE state_key LIKE 'materials_recovery_%' OR state_key LIKE 'pcloud_%'")->fetchAll(PDO::FETCH_ASSOC);
foreach($states as $st){$v=json_decode($st['state_value'],true);$r['runtime_shapes'][]=['key'=>$st['state_key'],'fields'=>is_array($v)?array_keys($v):[]];}
$r['complete']=!$r['head_failures']&&!$r['range_failures']&&!$r['associations']['unconfirmed']&&!$r['live_assets_without_manifest'];
$r['checked_at']=gmdate('c');
$db->prepare("INSERT INTO crm_runtime_state(state_key,state_value,updated_at) VALUES('pcloud_audio_audit_20261003',?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=UTC_TIMESTAMP()")->execute([json_encode($r,JSON_UNESCAPED_SLASHES)]);
echo json_encode($r,JSON_UNESCAPED_SLASHES)."\n";
