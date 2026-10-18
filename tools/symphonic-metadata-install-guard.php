<?php
/** Narrow read-only drift check around the existing Portal release workflow. */
function trb_symphonic_metadata_install_state($theme, array $baseline) {
    $expected = array('inc/trb-artist-portal.php','inc/trb-release-intake.php','inc/trb-symphonic-credits.php','assets/js/trb-release-upload.js','assets/js/trb-symphonic-credits.js','inc/trb-release-metadata.php','assets/data/symphonic-metadata-options.json');
    if (is_link($theme) || realpath($theme) !== $theme || !preg_match('/^[a-f0-9]{40}$/D', $baseline['revision'] ?? '') || !is_array($baseline['files'] ?? null)) throw new RuntimeException('Metadata baseline invalid.');
    $paths = array_keys($baseline['files']); sort($paths); sort($expected);
    if ($paths !== $expected) throw new RuntimeException('Metadata baseline scope invalid.');
    $marker = $theme . '/.trb-deployed-sha';
    if (is_link($marker) || !is_file($marker) || trim(file_get_contents($marker)) !== $baseline['revision']) throw new RuntimeException('Metadata baseline revision changed.');
    $state = array('revision'=>$baseline['revision'], 'marker_sha256'=>hash_file('sha256',$marker), 'files'=>array());
    foreach ($baseline['files'] as $path=>$hash) {
        $target = $theme . '/' . $path;
        for ($parent=$target; strlen($parent)>=strlen($theme); $parent=dirname($parent)) if (is_link($parent)) throw new RuntimeException('Metadata source link refused.');
        if (null === $hash) {
            if (file_exists($target)) throw new RuntimeException('Metadata new path already exists.');
            $state['files'][$path] = null; continue;
        }
        if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D',$hash) || !is_file($target) || filesize($target)>2097152) throw new RuntimeException('Metadata source unavailable.');
        $bytes = file_get_contents($target);
        if (!hash_equals($hash,hash('sha256',str_replace("\r\n","\n",$bytes)))) throw new RuntimeException('Metadata source drifted from reviewed baseline.');
        $state['files'][$path] = hash('sha256',$bytes);
    }
    return $state;
}

function trb_symphonic_metadata_install_check(array $current, array $captured) {
    if ($current !== $captured) throw new RuntimeException('Metadata source changed after preflight.');
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    ini_set('display_errors','0');
    set_exception_handler(static function() { fwrite(STDERR,"Symphonic metadata drift guard unconfirmed.\n"); exit(1); });
    $mode=$argv[1]??''; $revision=$argv[2]??'';
    if (!in_array($mode,array('capture','check'),true) || !preg_match('/^[a-f0-9]{40}$/D',$revision) || isset($argv[3])) exit(2);
    $site='/home/customer/www/artist.trbrec.com';
    $release=$site.'/private/portal-release-'.$revision; $candidate=$release.'/candidate';
    if (realpath(dirname(__DIR__))!==$candidate || is_link($release) || trim((string)@file_get_contents($candidate.'/.trb-deployed-sha'))!==$revision) throw new RuntimeException('Candidate revision mismatch.');
    $baseline=json_decode(file_get_contents(__DIR__.'/symphonic-metadata-install-baseline.json'),true,16,JSON_THROW_ON_ERROR);
    $state=trb_symphonic_metadata_install_state($site.'/public_html/wp-content/themes/docy',$baseline);
    if ('check'===$mode) {
        $receipt=$release.'/metadata-before.json';
        if (is_link($receipt) || !is_file($receipt) || filesize($receipt)>8192) throw new RuntimeException('Metadata preflight receipt unavailable.');
        trb_symphonic_metadata_install_check($state,json_decode(file_get_contents($receipt),true,16,JSON_THROW_ON_ERROR));
    }
    echo json_encode($state,JSON_THROW_ON_ERROR)."\n";
}
