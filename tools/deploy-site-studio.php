<?php
/** CLI-only installer. Uses existing SSH deployment; never prints credentials or artist data. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
$stage = 'initial';
set_exception_handler(static function ($e) { fwrite(STDERR, "TRB Studio installation failed: " . $e->getMessage() . "\n"); exit(1); });
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
$files = ['trb-site-studio.php', 'editor.php', 'portal.php', 'directory.php', 'editor.js', 'editor.css', 'directory.css', 'readme.txt'];
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
$stage = 'copy';
if (is_dir($destination)) {
    $same = true;
    foreach ($files as $file) {
        if (!is_file($destination . '/' . $file) || !hash_equals(hash_file('sha256', $source . '/' . $file), hash_file('sha256', $destination . '/' . $file))) $same = false;
    }
    if (!$same) {
        if (($argv[3] ?? '') === '--verify') throw new RuntimeException('verification file mismatch');
        foreach ($files as $file) if (!is_file($destination . '/' . $file) || !hash_equals($oldHashes[$file], hash_file('sha256', $destination . '/' . $file))) throw new RuntimeException('existing plugin differs from approved original; no overwrite performed');
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
if (\TRB\Studio\VERSION !== '0.1.1') throw new RuntimeException('version check');
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
