<?php
define('TRB_CANONICAL_CRM_RELEASE_LIBRARY_ONLY',true);
require __DIR__.'/qa-canonical-crm-release.php';
$base=str_replace('\\','/',sys_get_temp_dir()).'/trb-canonical-crm-'.bin2hex(random_bytes(8));$public=$base.'/public';$private=$base.'/private';$incoming=$private.'/incoming';
mkdir($public.'/app',0700,true);mkdir($incoming,0700,true);
$initial=['.htaccess'=>"Require all granted\n",'app/One.php'=>"<?php return 'old';\n",'app/Two.php'=>"<?php return 'unchanged';\n"];
foreach($initial as $p=>$bytes)file_put_contents($public.'/'.$p,$bytes);
file_put_contents($public.'/.env','TEST_ONLY=1');file_put_contents($private.'/canonical-release.json','{"prior":"retained exactly"}');
$beforeManifest=file_get_contents($private.'/canonical-release.json');$old=[];foreach($initial as $p=>$bytes)$old[]=['path'=>$p,'sha256'=>hash('sha256',$bytes)];$oldMap=trb_crm_release_map($old);
$baseline=['production_deployed'=>true,'candidate_commit'=>str_repeat('a',40),'files'=>$old];
$request=['baseline_revision'=>str_repeat('a',40),'revision'=>str_repeat('b',40),'tag'=>str_repeat('c',16),'file_count'=>3,'baseline_manifest_sha256'=>hash('sha256',json_encode($oldMap,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),'changed_paths'=>['app/One.php']];
$checks=0;
$check=static function(bool $ok)use(&$checks){if(!$ok)throw new RuntimeException('Canonical release regression failed.');$checks++;};
$reject=static function(callable $call)use(&$checks){try{$call();}catch(Throwable $e){$checks++;return;}throw new RuntimeException('Unsafe canonical release accepted.');};
$build=static function(array $entries)use($incoming,&$request){
    $zip=new ZipArchive;if($zip->open($incoming.'/candidate.zip',ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Fixture ZIP unavailable.');
    foreach($entries as $p=>$bytes)$zip->addFromString($p,$bytes);$zip->close();
    clearstatcache(true,$incoming.'/candidate.zip');
    $request['archive_sha256']=hash_file('sha256',$incoming.'/candidate.zip');$request['archive_bytes']=filesize($incoming.'/candidate.zip');
    file_put_contents($incoming.'/upload.json',json_encode(['verified'=>true,'tag'=>$request['tag'],'revision'=>$request['revision'],'sha256'=>$request['archive_sha256'],'bytes'=>$request['archive_bytes']],JSON_THROW_ON_ERROR));
};
try{
    $next=$initial;$next['app/One.php']="<?php return 'new';\n";$build($next);
    $package=trb_crm_release_package($public,$incoming,$baseline,$request);$check(count($package['changes'])===1);
    $wrong=$request;$wrong['revision']=str_repeat('d',40);$reject(fn()=>trb_crm_release_package($public,$incoming,$baseline,$wrong));
    $wrong=$baseline;$wrong['files'][0]['sha256']=str_repeat('e',64);$reject(fn()=>trb_crm_release_package($public,$incoming,$wrong,$request));
    file_put_contents($incoming.'/candidate.zip','corrupted',FILE_APPEND);clearstatcache();$reject(fn()=>trb_crm_release_package($public,$incoming,$baseline,$request));$build($next);
    $escape=$next;unset($escape['app/Two.php']);$escape['../outside.php']="<?php return true;";$build($escape);$reject(fn()=>trb_crm_release_package($public,$incoming,$baseline,$request));$check(!file_exists($base.'/outside.php'));$build($next);
    $invalid=$next;$invalid['app/One.php']="<?php ! broken syntax";$build($invalid);$reject(fn()=>trb_crm_release_package($public,$incoming,$baseline,$request));$build($next);
    $unreviewed=$next;$unreviewed['app/Two.php']="<?php return 'unreviewed';";$build($unreviewed);$reject(fn()=>trb_crm_release_package($public,$incoming,$baseline,$request));$build($next);
    file_put_contents($public.'/app/Two.php',"<?php return 'concurrent';");$reject(fn()=>trb_crm_release_apply($public,$private,$private.'/drift',$package,$request));$check(!is_dir($private.'/drift'));file_put_contents($public.'/app/Two.php',$initial['app/Two.php']);
    $reject(fn()=>trb_crm_release_apply($public,$private,$private.'/failed',$package,$request,static function(){throw new RuntimeException('Simulated post-install health failure.');}));
    trb_crm_release_verify($public,$oldMap);$check(file_get_contents($private.'/canonical-release.json')===$beforeManifest);$check(file_get_contents($public.'/.htaccess')===$initial['.htaccess']);$check(trb_crm_release_json($private.'/failed/failed.json')['rollback_verified']===true);
    $success=trb_crm_release_apply($public,$private,$private.'/success',$package,$request,static function(){});
    $check($success['production_deployed']&&$success['changed_modules']===1&&$success['database_written']===false);
    $canonical=trb_crm_release_json($private.'/canonical-release.json');$check($canonical['verified']&&$canonical['revision']===$request['revision']);trb_crm_release_verify($public,$package['next']);
    file_put_contents($public.'/app/Two.php',"<?php return 'concurrent';");$reject(fn()=>trb_crm_release_rollback($public,$private,$private.'/success',$oldMap));$check(file_get_contents($public.'/app/One.php')===$next['app/One.php']);file_put_contents($public.'/app/Two.php',$initial['app/Two.php']);
    trb_crm_release_rollback($public,$private,$private.'/success',$oldMap);trb_crm_release_verify($public,$oldMap);$check(file_get_contents($private.'/canonical-release.json')===$beforeManifest);$check(file_get_contents($public.'/.env')==='TEST_ONLY=1');$check(file_get_contents($public.'/.htaccess')===$initial['.htaccess']);$check(!glob($public.'/app/*.trb-release-*'));
    echo "PASS {$checks} canonical CRM package, authorization, drift, path, syntax, failed health rollback and exact manifest recovery checks.\n";
}finally{
    if(str_replace('\\','/',realpath($base))!==$base)throw new RuntimeException('Test cleanup scope changed.');
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f)$f->isDir()&&!$f->isLink()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($base);
}
