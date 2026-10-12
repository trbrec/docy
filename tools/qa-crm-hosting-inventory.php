<?php
/** Independent public audit program: emit metadata, never CRM source or record values. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$buffer=ob_get_level();ob_start();
$report=['application'=>'crm.trbrec.com','success'=>false,'read_only'=>true,'production_modified'=>false,'source_code_disclosed'=>false,'configuration_values_disclosed'=>false,'record_values_disclosed'=>false,'provider_calls_made'=>false,'stage'=>'paths'];
register_shutdown_function(static function()use(&$report,$buffer){while(ob_get_level()>$buffer)ob_end_clean();echo json_encode($report,JSON_UNESCAPED_SLASHES)."\n";});
set_exception_handler(static function(){exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';
if(realpath($root)!==$root||is_link($root))throw new RuntimeException();
$report['source_files']=[];
$sourceFiles=[];
foreach(['app','bin','config','assets'] as $name){
    $directory=$root.'/'.$name;
    if(!is_dir($directory)||is_link($directory))continue;
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS)) as $file){
        if(!$file->isFile()||$file->isLink()||!in_array(strtolower($file->getExtension()),['php','css','js'],true))continue;
        $sourceFiles[]=$file->getPathname();
    }
}
foreach(['index.php','.htaccess'] as $name)if(is_file($root.'/'.$name)&&!is_link($root.'/'.$name))$sourceFiles[]=$root.'/'.$name;
sort($sourceFiles);
foreach($sourceFiles as $file)$report['source_files'][]=['path'=>substr($file,strlen($root)+1),'bytes'=>filesize($file),'sha256'=>hash_file('sha256',$file)];
$report['stage']='configuration-presence';
// Only class definitions and the established environment parser; no application bootstrap.
require_once $root.'/app/Core.php';
\TrbCrm\Env::load($root.'/.env');
$report['configuration_present']=[];
foreach(['APP_KEY','ARTIST_PORTAL_SYNC_SECRET','CONTRACT_APPS_SCRIPT_URL','CONTRACT_APPS_SCRIPT_SECRET','OPENAI_API_KEY'] as $key)$report['configuration_present'][$key]=strlen((string)\TrbCrm\Env::get($key,''))>0;
$report['stage']='database-metadata';
$db=\TrbCrm\Database::connection();
$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
try{
    $report['database']=['record_values_read'=>false,'tables'=>$db->query("SELECT TABLE_NAME name,ENGINE engine,TABLE_COLLATION collation FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC)];
    foreach(['TRIGGERS'=>'TRIGGER_SCHEMA','EVENTS'=>'EVENT_SCHEMA','ROUTINES'=>'ROUTINE_SCHEMA'] as $table=>$schema)$report['database'][strtolower($table).'_count']=(int)$db->query('SELECT COUNT(*) FROM information_schema.'.$table.' WHERE '.$schema.'=DATABASE()')->fetchColumn();
}finally{$db->rollBack();}
$report['stage']='hosting-metadata';
$report['logs']=[];
foreach([$root.'/php_errorlog',dirname($root).'/private/php_errorlog'] as $file)if(is_file($file)&&!is_link($file))$report['logs'][]=['path'=>substr($file,strlen(dirname($root))+1),'bytes'=>filesize($file),'modified_at'=>gmdate('c',filemtime($file))];
$report['software_archive_candidates']=[];
foreach(new DirectoryIterator($root) as $file){
    if($file->isDot()||$file->isLink()||!preg_match('/^(?:crm[-_](?:backup|candidate|stage)|backup[-_]crm)[a-zA-Z0-9_.-]*$/D',$file->getFilename()))continue;
    $report['software_archive_candidates'][]=['name'=>$file->getFilename(),'type'=>$file->isDir()?'directory':'file','bytes'=>$file->isFile()?$file->getSize():null,'removal_authorized'=>false];
}
$cron=[];$cronExit=0;exec('crontab -l 2>/dev/null',$cron,$cronExit);
$report['account_cron']=['available'=>$cronExit===0,'scope'=>'current-hosting-account-only','platform_cron_inspected'=>false,'entries'=>[]];
if($cronExit===0)foreach($cron as $line){$line=trim($line);if($line===''||$line[0]==='#')continue;$report['account_cron']['entries'][]=['command_sha256'=>hash('sha256',$line),'references_crm'=>str_contains($line,'crm.trbrec.com'),'references_portal'=>str_contains($line,'artist.trbrec.com')];}
$report['success']=true;$report['stage']='complete';
