<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$stage='bootstrap';
set_exception_handler(static function()use(&$stage){echo json_encode(['audit'=>'unconfirmed','stage'=>$stage]);exit(1);});
$crm='/home/customer/www/crm.trbrec.com/public_html';
require_once $crm.'/app/Core.php';
\TrbCrm\Env::load($crm.'/.env');
$db=\TrbCrm\Database::connection();
$rows=$db->query('SELECT * FROM pcloud_material_associations WHERE asset_id IN (27,29)')->fetchAll(PDO::FETCH_ASSOC);
$r=['audit'=>'read-only','targets'=>[]];
foreach($rows as $a){
$asset=$db->query('SELECT * FROM assets WHERE id='.(int)$a['asset_id'])->fetch(PDO::FETCH_ASSOC);
$e=json_decode($a['evidence_json'],true);
$r['targets'][]=['asset_id'=>(int)$a['asset_id'],'submission_matches'=>(int)$a['submission_id']===(int)$asset['submission_id'],'path_matches'=>$a['pcloud_path']===$asset['pcloud_path'],'hash_matches'=>$a['content_sha256']===$asset['content_sha256'],'size_matches'=>(int)$a['byte_size']===(int)$asset['byte_size'],'source_kind'=>$a['source_kind'],'evidence_fields'=>is_array($e)?array_keys($e):[],'evidence_shape'=>is_array($e)?array_map(static fn($v)=>is_array($v)?array_keys($v):(is_bool($v)?$v:(is_numeric($v)?$v:strlen((string)$v).' chars')),$e):null];
}
$stage='pcloud-settings';
define('DISABLE_WP_CRON',true);define('WP_USE_THEMES',false);
ob_start();require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';ob_end_clean();
$settings=trb_demo_settings();$endpoint=rtrim($settings['webdav_endpoint'],'/');
$options=[CURLOPT_USERPWD=>$settings['pcloud_user'].':'.$settings['pcloud_pass'],CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30];
$stage='read-range';
foreach($rows as $a){
$url=$endpoint.'/'.implode('/',array_map('rawurlencode',explode('/',ltrim($a['pcloud_path'],'/'))));
$ch=curl_init($url);$head=[];curl_setopt_array($ch,$options+[CURLOPT_RANGE=>'0-1023',CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADERFUNCTION=>static function($ch,$line)use(&$head){if(preg_match('/^(Content-Type|Content-Length|Content-Range|Accept-Ranges):/i',$line))$head[]=trim($line);return strlen($line);}]);
$raw=curl_exec($ch);$r['range_checks'][]=['asset_id'=>(int)$a['asset_id'],'status'=>(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE),'bytes'=>is_string($raw)?strlen($raw):0,'prefix'=>is_string($raw)?bin2hex(substr($raw,0,10)):null,'headers'=>$head,'curl_error'=>curl_errno($ch)];curl_close($ch);
}
$stage='all-associations';
$all=$db->query("SELECT m.*,a.submission_id current_submission,a.pcloud_path current_path,a.filename current_filename,a.content_sha256 current_sha,a.byte_size current_size,a.source_url, s.received_at current_received,l.demo_upload legacy_source,l.raw_payload legacy_metadata FROM pcloud_material_associations m LEFT JOIN assets a ON a.id=m.asset_id LEFT JOIN submissions s ON s.id=a.submission_id LEFT JOIN legacy_demo_archive l ON l.id=m.legacy_id")->fetchAll(PDO::FETCH_ASSOC);
$r['counts']=['total'=>count($all)];$r['mismatches']=[];
foreach($all as $a){
if($a['asset_id']!==null){
$ok=(int)$a['submission_id']===(int)$a['current_submission']&&$a['pcloud_path']===$a['current_path']&&$a['content_sha256']===$a['current_sha']&&(int)$a['byte_size']===(int)$a['current_size'];
if(!$ok)$r['mismatches'][]=['asset_id'=>(int)$a['asset_id'],'submission'=>(int)$a['submission_id']];
}
}
echo json_encode($r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
