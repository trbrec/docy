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
$stage = 'copy';
if (is_dir($destination)) {
    foreach ($files as $file) {
        if (!is_file($destination . '/' . $file) || !hash_equals(hash_file('sha256', $source . '/' . $file), hash_file('sha256', $destination . '/' . $file))) throw new RuntimeException('existing plugin differs; no overwrite performed');
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
$stage = 'activation';
if (!$already) {
    $result = activate_plugin($slug, '', false, true);
    if (is_wp_error($result)) throw new RuntimeException('WordPress activation failed');
}
if (!is_plugin_active($slug)) throw new RuntimeException('plugin activation unconfirmed');
$stage = 'load-plugin';
if (!function_exists('TRB\\Studio\\editor_apply')) require_once $destination . '/trb-site-studio.php';
if (\TRB\Studio\VERSION !== '0.1.0') throw new RuntimeException('version check');
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
