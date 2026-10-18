<?php
require __DIR__.'/symphonic-metadata-install-guard.php';
$temp=sys_get_temp_dir().'/trb-metadata-guard-'.bin2hex(random_bytes(8));
if (!mkdir($temp,0700)) throw new RuntimeException('Fixture unavailable.');
$temp=realpath($temp); $checks=0; $symlink_checked=false;
function metadata_check($ok,$why) { global $checks; if (!$ok) throw new RuntimeException($why); $checks++; }
function metadata_reject($call,$why) { try { $call(); } catch (RuntimeException $e) { metadata_check(true,$why); return; } throw new RuntimeException($why); }
try {
    $baseline=json_decode(file_get_contents(__DIR__.'/symphonic-metadata-install-baseline.json'),true,16,JSON_THROW_ON_ERROR);
    file_put_contents($temp.'/.trb-deployed-sha',$baseline['revision']."\n");
    foreach ($baseline['files'] as $path=>&$hash) {
        if (!is_dir(dirname($temp.'/'.$path))) mkdir(dirname($temp.'/'.$path),0700,true);
        if (null === $hash) continue;
        $bytes="synthetic fixture: ".$path."\n"; file_put_contents($temp.'/'.$path,$bytes); $hash=hash('sha256',$bytes);
    }
    unset($hash);
    $captured=trb_symphonic_metadata_install_state($temp,$baseline);
    metadata_check(count($captured['files'])===7,'Guard did not inspect all7 paths.');
    trb_symphonic_metadata_install_check($captured,$captured); metadata_check(true,'Unchanged raw state rejected.');
    $path='inc/trb-artist-portal.php'; $original=file_get_contents($temp.'/'.$path);
    file_put_contents($temp.'/'.$path,str_replace("\n","\r\n",$original));
    $crlf=trb_symphonic_metadata_install_state($temp,$baseline);
    metadata_check($crlf['files'][$path]!==$captured['files'][$path],'Raw hash did not distinguish EOL drift.');
    metadata_reject(fn()=>trb_symphonic_metadata_install_check($crlf,$captured),'Raw EOL drift accepted after capture.');
    file_put_contents($temp.'/'.$path,$original.'changed');
    metadata_reject(fn()=>trb_symphonic_metadata_install_state($temp,$baseline),'Changed before hash accepted.');
    file_put_contents($temp.'/'.$path,$original);
    file_put_contents($temp.'/inc/trb-release-metadata.php','unexpected existing file');
    metadata_reject(fn()=>trb_symphonic_metadata_install_state($temp,$baseline),'New-path collision accepted.'); unlink($temp.'/inc/trb-release-metadata.php');
    file_put_contents($temp.'/.trb-deployed-sha',str_repeat('a',40));
    metadata_reject(fn()=>trb_symphonic_metadata_install_state($temp,$baseline),'Changed marker accepted.'); file_put_contents($temp.'/.trb-deployed-sha',$baseline['revision']."\n");
    $bad=$baseline; $bad['files']['../outside.php']=hash('sha256','x');
    metadata_reject(fn()=>trb_symphonic_metadata_install_state($temp,$bad),'Path traversal or extra scope accepted.');
    $bad=$baseline; $bad['files'][$path]='bad';metadata_reject(fn()=>trb_symphonic_metadata_install_state($temp,$bad),'Malformed hash accepted.');
    $backup=$temp.'/source-copy'; rename($temp.'/'.$path,$backup);
    if (@symlink($backup,$temp.'/'.$path)) {
        metadata_reject(fn()=>trb_symphonic_metadata_install_state($temp,$baseline),'Source symlink accepted.'); unlink($temp.'/'.$path); $symlink_checked=true;
    } elseif (PHP_OS_FAMILY !== 'Windows') throw new RuntimeException('CI must exercise actual symlink rejection.');
    rename($backup,$temp.'/'.$path);
    metadata_check(trb_symphonic_metadata_install_state($temp,$baseline)===$captured,'Read-only guard altered sources.');
    echo json_encode(array('passed'=>true,'checks'=>$checks,'symlink_checked'=>$symlink_checked,'network'=>false,'installed_runtime_mutated'=>false))."\n";
} finally {
    // Only this random synthetic fixture is removed; never follow symlinks.
    $entries=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) { if ($entry->isLink() || $entry->isFile()) unlink($entry->getPathname()); else rmdir($entry->getPathname()); }
    rmdir($temp);
}
