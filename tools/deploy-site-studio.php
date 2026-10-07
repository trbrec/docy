<?php
/** CLI-only installer. Uses existing SSH deployment; never prints credentials or artist data. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
$stage = 'initial';
set_exception_handler(static function ($e) use (&$stage) {
 if (function_exists('update_option')) update_option('wpvibe_task_trb_studio_install', ['stage' => $stage, 'error' => $e->getMessage(), 'at' => gmdate('c')], false);
 fwrite(STDERR, "TRB Studio installation failed: " . $e->getMessage() . "\n"); exit(1);
});
$revision = $argv[1] ?? '';
$site = $argv[2] ?? '';
if (!preg_match('/^[a-f0-9]{40}$/D', $revision) || trim((string) @file_get_contents(dirname(__DIR__) . '/.trb-deployed-sha')) !== $revision) throw new RuntimeException('revision guard');
if (!in_array($site, ['new1.trbrec.com', 'artist.trbrec.com'], true)) throw new RuntimeException('site guard');
$root = '/home/customer/www/' . $site . '/public_html';
$marker = dirname(__DIR__) . '/.trb-studio-install-' . $site;
register_shutdown_function(static function () use (&$stage, $marker) { file_put_contents($marker, $stage); });
$stage = 'preflight';
$source = dirname(__DIR__) . '/integrations/site-studio/trb-site-studio';
$destination = $root . '/wp-content/plugins/trb-site-studio';
if (!is_file($root . '/wp-load.php') || !is_dir($root . '/wp-content/plugins')) throw new RuntimeException('WordPress path guard');
$files = ['trb-site-studio.php', 'editor.php', 'portal.php', 'directory.php', 'editor.js', 'editor.css', 'directory.css', 'readme.txt', 'bundle.php'];
foreach ($files as $file) {
    if (!is_file($source . '/' . $file)) throw new RuntimeException('source package incomplete');
    if (str_ends_with($file, '.php')) {
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($source . '/' . $file) . ' 2>&1', $lint, $status);
        if ($status !== 0) throw new RuntimeException('source syntax check');
    }
}
define('WP_USE_THEMES', false);
$stage = 'bootstrap';
require $root . '/wp-load.php';
$stage = 'wordpress-guards';
if (strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== $site) throw new RuntimeException('WordPress hostname guard');
if (!class_exists('DOMDocument')) throw new RuntimeException('PHP DOM extension required');
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$slug = 'trb-site-studio/trb-site-studio.php';
$already = is_plugin_active($slug);
$upgraded = false;
$oldHashes = json_decode('{"directory.css":"4d7a75ccb0553499ccf21145a1bd641c3cf571ba7157fbbff69957cd2f1ca196","directory.php":"4f0f34d72620f3b089fdadf5c2f907a46d01220b1042517c31d89b7c980348f3","trb-site-studio.php":"5498d8a73cf2cfe97c4995b1f071e03e00f71dc27e2baea1650bbf3943d7528c","editor.js":"ebd85e50d29967c45c29eb57fcb21e415fa3e87f38ad82ef0fa8ac51e28f6557","portal.php":"82b501a1b3e767eb6718bc621e58b43b65e104891b78e09efe66afa5b073d1c7","editor.php":"234f93e35fd159ae2b7b385f634c2582cf2422a9339cc4410e17c91451327c69","editor.css":"42fce36927ce3b88cbf213c0049f3574a87888d8fe427d83a2ee9bc65ad40cec","readme.txt":"5a88b415e8758c8da5dc23df2377691d493fcfbdb592e714cf917cf6a3b5bf09"}', true);
$approvedPackages = [json_decode('{"trb-site-studio.php":"f10ebd1ee33bf600eba29d3eb9957f2e20a9ad4d89f5c289b4fc400259397a5b","editor.php":"b0868610e332cb422bd1ec130522faf6b891983bc57a5531ba2bbf367cfa966d","portal.php":"a91183979aa99fb48c067f29b882743b120dc6b774bbeda24ba6185fd8548691","directory.php":"9bec04f2dc6cad28216c072cbe5fb25ca194f599af705cc89e4c3ca06519af52","editor.js":"ebd85e50d29967c45c29eb57fcb21e415fa3e87f38ad82ef0fa8ac51e28f6557","editor.css":"42fce36927ce3b88cbf213c0049f3574a87888d8fe427d83a2ee9bc65ad40cec","directory.css":"4d7a75ccb0553499ccf21145a1bd641c3cf571ba7157fbbff69957cd2f1ca196","readme.txt":"98ddb8976efd6fde34d0b0924284a60e9d1c92cd4df426c3c0ce483921f2565b","bundle.php":"963f6b83d973425904f5e31cf0d7faa7138e545c6b6edfb723dd19a764e22789"}', true), json_decode('{"trb-site-studio.php":"b14914dc7676b3eaa31bf621679011a80902ba4ca43591286f445f387d27b612","editor.php":"b0868610e332cb422bd1ec130522faf6b891983bc57a5531ba2bbf367cfa966d","portal.php":"ed71ad8e63f4e94bdec1c42678b9f3ad9974a1e6648de80a244907568e0da056","directory.php":"f7181ebc8dccd2fa08d0d24247df2edbe3ad704e9a21fc09e99cc5f4855b8d4d","editor.js":"ebd85e50d29967c45c29eb57fcb21e415fa3e87f38ad82ef0fa8ac51e28f6557","editor.css":"42fce36927ce3b88cbf213c0049f3574a87888d8fe427d83a2ee9bc65ad40cec","directory.css":"4d7a75ccb0553499ccf21145a1bd641c3cf571ba7157fbbff69957cd2f1ca196","readme.txt":"97049587cfcefcb56a8907886a01a8dd78ef6ccdd3efdb9a36a2a673a0e87cf7","bundle.php":"963f6b83d973425904f5e31cf0d7faa7138e545c6b6edfb723dd19a764e22789"}', true), $oldHashes, json_decode('{"trb-site-studio.php":"fe8c5eda806989b5321700b63025d934dd0f02be0866297bac8404b8ba271f74","editor.php":"b0868610e332cb422bd1ec130522faf6b891983bc57a5531ba2bbf367cfa966d","portal.php":"82b501a1b3e767eb6718bc621e58b43b65e104891b78e09efe66afa5b073d1c7","directory.php":"4f0f34d72620f3b089fdadf5c2f907a46d01220b1042517c31d89b7c980348f3","editor.js":"ebd85e50d29967c45c29eb57fcb21e415fa3e87f38ad82ef0fa8ac51e28f6557","editor.css":"42fce36927ce3b88cbf213c0049f3574a87888d8fe427d83a2ee9bc65ad40cec","directory.css":"4d7a75ccb0553499ccf21145a1bd641c3cf571ba7157fbbff69957cd2f1ca196","readme.txt":"5a88b415e8758c8da5dc23df2377691d493fcfbdb592e714cf917cf6a3b5bf09"}', true)];
$approvedPackages[] = json_decode('{"trb-site-studio.php":"d53f3d1fe7cf8778556ea00f24c7f8d6850812c2d360ee26d8290887474dd5d0","editor.php":"b0868610e332cb422bd1ec130522faf6b891983bc57a5531ba2bbf367cfa966d","portal.php":"a91183979aa99fb48c067f29b882743b120dc6b774bbeda24ba6185fd8548691","directory.php":"e71b87a712d88a8285cfe4d05562edcc59d36a2746e08d0a98ef14a349db6109","editor.js":"ebd85e50d29967c45c29eb57fcb21e415fa3e87f38ad82ef0fa8ac51e28f6557","editor.css":"42fce36927ce3b88cbf213c0049f3574a87888d8fe427d83a2ee9bc65ad40cec","directory.css":"4d7a75ccb0553499ccf21145a1bd641c3cf571ba7157fbbff69957cd2f1ca196","readme.txt":"f0bc5138040581885c3cc2130f0398c82016309cfc0fe85ca328f4822eee4041","bundle.php":"963f6b83d973425904f5e31cf0d7faa7138e545c6b6edfb723dd19a764e22789"}', true);
$approvedPackages[] = json_decode('{"trb-site-studio.php":"e508a762c7185ddcf57f86a0825dd0d66ae0b48c604458d14b108b47b99f9c4f","editor.php":"b0868610e332cb422bd1ec130522faf6b891983bc57a5531ba2bbf367cfa966d","portal.php":"a91183979aa99fb48c067f29b882743b120dc6b774bbeda24ba6185fd8548691","directory.php":"d570b086758e4c73b0f9ed8d97ac277b2ad05839106d9deaa8e9f18becfed7bf","editor.js":"ebd85e50d29967c45c29eb57fcb21e415fa3e87f38ad82ef0fa8ac51e28f6557","editor.css":"42fce36927ce3b88cbf213c0049f3574a87888d8fe427d83a2ee9bc65ad40cec","directory.css":"12730de5cf60f294bcf581bc899cd1b8d641d2e499c881d938c2352dde9682c4","readme.txt":"631562913e2ad6e6bee04dcdc8cab59388137ae3f1b927809ce3642372487e78","bundle.php":"963f6b83d973425904f5e31cf0d7faa7138e545c6b6edfb723dd19a764e22789"}', true);
// Verified 0.1.6 package deployed before photograph curation.
$approvedPackages[] = json_decode('{"trb-site-studio.php":"915ae7308955c323c2236103792c6c085352cdaadf8c958b0dad44c970e7b9b4","editor.php":"b0868610e332cb422bd1ec130522faf6b891983bc57a5531ba2bbf367cfa966d","portal.php":"a91183979aa99fb48c067f29b882743b120dc6b774bbeda24ba6185fd8548691","directory.php":"d570b086758e4c73b0f9ed8d97ac277b2ad05839106d9deaa8e9f18becfed7bf","editor.js":"ebd85e50d29967c45c29eb57fcb21e415fa3e87f38ad82ef0fa8ac51e28f6557","editor.css":"42fce36927ce3b88cbf213c0049f3574a87888d8fe427d83a2ee9bc65ad40cec","directory.css":"2c05cd47e6bbe870fe9483025517fa8b7ed1bc9f6d849face3a44cffc0f9aad2","readme.txt":"631562913e2ad6e6bee04dcdc8cab59388137ae3f1b927809ce3642372487e78","bundle.php":"963f6b83d973425904f5e31cf0d7faa7138e545c6b6edfb723dd19a764e22789"}', true);
$approvedPackages[] = json_decode('{"trb-site-studio.php":"d38e33d1bee9ada26324a666c77ffb20b5ae4f11d731464a813c55d091e246d3","editor.php":"b0868610e332cb422bd1ec130522faf6b891983bc57a5531ba2bbf367cfa966d","portal.php":"5d3fb02baa6b5de65422671dd86c9df31ef4bb3f3e60e22cd2d14a1bb3593a32","directory.php":"d570b086758e4c73b0f9ed8d97ac277b2ad05839106d9deaa8e9f18becfed7bf","editor.js":"ebd85e50d29967c45c29eb57fcb21e415fa3e87f38ad82ef0fa8ac51e28f6557","editor.css":"42fce36927ce3b88cbf213c0049f3574a87888d8fe427d83a2ee9bc65ad40cec","directory.css":"27909bde8a72c6e5ddfda48f27fe13c8afc42f9517af79dafed06c714a40f164","readme.txt":"631562913e2ad6e6bee04dcdc8cab59388137ae3f1b927809ce3642372487e78","bundle.php":"963f6b83d973425904f5e31cf0d7faa7138e545c6b6edfb723dd19a764e22789"}', true);
// Verified current package before the requested colour-photo correction.
$approvedPackages[] = json_decode('{"bundle.php":"963f6b83d973425904f5e31cf0d7faa7138e545c6b6edfb723dd19a764e22789","directory.css":"aa14b03a33b9162d3eddf057e8ab73881ec11c8ecd9950bcc455e090dad36868","directory.php":"aa98eb4cb9c53407a9576abdb4c7fd9a7f8043f7bf97ccea56dbbb53d66fa02b","editor.css":"42fce36927ce3b88cbf213c0049f3574a87888d8fe427d83a2ee9bc65ad40cec","editor.js":"ebd85e50d29967c45c29eb57fcb21e415fa3e87f38ad82ef0fa8ac51e28f6557","editor.php":"b0868610e332cb422bd1ec130522faf6b891983bc57a5531ba2bbf367cfa966d","portal.php":"5d3fb02baa6b5de65422671dd86c9df31ef4bb3f3e60e22cd2d14a1bb3593a32","readme.txt":"631562913e2ad6e6bee04dcdc8cab59388137ae3f1b927809ce3642372487e78","trb-site-studio.php":"d38e33d1bee9ada26324a666c77ffb20b5ae4f11d731464a813c55d091e246d3"}', true);
$stage = 'copy';
if (is_dir($destination)) {
    $same = true;
    foreach ($files as $file) {
        if (!is_file($destination . '/' . $file) || !hash_equals(hash_file('sha256', $source . '/' . $file), hash_file('sha256', $destination . '/' . $file))) $same = false;
    }
    if (!$same) {
        if (($argv[3] ?? '') === '--verify') throw new RuntimeException('verification file mismatch');
        $known = false;
        foreach ($approvedPackages as $package) {
            $match = true;
            foreach ($package as $file => $hash) if (!is_file($destination . '/' . $file) || !hash_equals($hash, hash_file('sha256', $destination . '/' . $file))) $match = false;
            foreach ($files as $file) if (!isset($package[$file]) && file_exists($destination . '/' . $file)) $match = false;
            if ($match) $known = true;
        }
        if (!$known) {
 $hashes = []; foreach ($files as $file) $hashes[$file] = is_file($destination . '/' . $file) ? hash_file('sha256', $destination . '/' . $file) : '';
 update_option('wpvibe_task_trb_studio_package_hashes', $hashes, false);
 throw new RuntimeException('existing plugin differs from an approved package; no overwrite performed');
}
        $private = dirname($root) . '/private';
        if (!is_dir($private) && !mkdir($private,0700,true)) throw new RuntimeException('backup directory');
        $backup = $private . '/trb-studio-before-' . $revision;
        if (file_exists($backup)) throw new RuntimeException('backup already exists');
        $temp = $root . '/wp-content/plugins/.trb-studio-' . $revision;
        if (file_exists($temp) || !mkdir($temp,0755)) throw new RuntimeException('upgrade staging');
        foreach ($files as $file) { if (!copy($source . '/' . $file, $temp . '/' . $file)) throw new RuntimeException('upgrade copy'); chmod($temp . '/' . $file,0644); }
        if (!rename($destination,$backup)) throw new RuntimeException('backup move');
        chmod($backup,0700);
        if (!rename($temp,$destination)) { chmod($backup,0755); rename($backup,$destination); throw new RuntimeException('upgrade install'); }
        if(function_exists('opcache_invalidate'))foreach($files as $file)if(str_ends_with($file,'.php'))opcache_invalidate($destination.'/'.$file,true);
        $upgraded = true;
    }
} else {
    $temp = $root . '/wp-content/plugins/.trb-studio-' . $revision;
    if (file_exists($temp)) throw new RuntimeException('staging directory exists');
    if (!mkdir($temp, 0755)) throw new RuntimeException('cannot stage package');
    foreach ($files as $file) {
        if (!copy($source . '/' . $file, $temp . '/' . $file)) throw new RuntimeException('package copy failed');
        chmod($temp . '/' . $file, 0644);
    }
    if (!rename($temp, $destination)) throw new RuntimeException('atomic installation failed');
}
if ($upgraded) {
    $stage = 'verification';
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($revision) . ' ' . escapeshellarg($site) . ' --verify', $status);
    if ($status !== 0) { rename($destination, $private . '/trb-studio-failed-' . $revision); chmod($backup,0755); rename($backup,$destination); throw new RuntimeException('upgrade verification failed and previous files restored'); }
    $stage = 'complete';
    exit(0);
}
$stage = 'activation';
if (!$already) {
    $result = activate_plugin($slug, '', false, true);
    if (is_wp_error($result)) throw new RuntimeException('WordPress activation failed');
}
if (!is_plugin_active($slug)) throw new RuntimeException('plugin activation unconfirmed');
$stage = 'load-plugin';
if (!function_exists('TRB\\Studio\\editor_apply')) require_once $destination . '/trb-site-studio.php';
if (\TRB\Studio\VERSION !== '0.1.7') throw new RuntimeException('version check');
$stage = 'verification';
if ($site === 'new1.trbrec.com') {
    $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'TRB Studio verifica installazione', 'post_content' => '<!-- wp:html --><p>Verifica editor</p><!-- /wp:html -->'], true);
    if (is_wp_error($id)) throw new RuntimeException('test draft creation');
    try {
        $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
        if (!$admins) throw new RuntimeException('administrator missing');
        wp_set_current_user((int) $admins[0]);
        $request = new WP_REST_Request('GET'); $request['id'] = $id;
        if (!\TRB\Studio\editor_permission($request)) throw new RuntimeException('editor permission check');
        $manifest = \TRB\Studio\editor_manifest($request)->get_data();
        if (count($manifest['items']) !== 1) throw new RuntimeException('editor manifest check');
        $request = new WP_REST_Request('POST'); $request['id'] = $id;
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode(['version' => $manifest['version'], 'changes' => [['key' => $manifest['items'][0]['key'], 'html' => 'Verifica completata', 'spacing' => ['margin-top' => 12]]]]));
        $saved = \TRB\Studio\editor_save($request);
        if (is_wp_error($saved) || !str_contains(get_post($id)->post_content, 'Verifica completata')) throw new RuntimeException('editor save check');
        $conflict = \TRB\Studio\editor_save($request);
        if (!is_wp_error($conflict) || $conflict->get_error_code() !== 'edit_conflict') throw new RuntimeException('editor conflict check');
        wp_set_current_user(0);
        if (\TRB\Studio\editor_permission($request)) throw new RuntimeException('anonymous permission check');
    } finally { wp_set_current_user(0); wp_trash_post($id); }
    echo "New1: editor installed; real WordPress manifest, save, conflict and anonymous rejection verified.\n";
} else {
    echo "Portal: plugin activation and version verified.\n";
}
echo 'Automatic sync enabled=' . (get_option('trb_studio_enabled') ? 'yes' : 'no') . ".\n";

$stage = 'complete';

