<?php
/** Real filesystem failures and, in CI, Apache HTTP enforcement of private rules. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = sys_get_temp_dir() . '/trb-private-storage-' . bin2hex(random_bytes(8));
mkdir($root, 0755);
function wp_mkdir_p($path) { return is_dir($path) || (!file_exists($path) && mkdir($path, 0755, true)); }
function trailingslashit($path) { return rtrim($path, '/') . '/'; }
function storage_check($ok, $message) { if (!$ok) throw new RuntimeException($message); $GLOBALS['storage_checks']++; }
function storage_remove($path) {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') storage_remove($path . '/' . $entry);
    rmdir($path);
}
$source = file_get_contents(__DIR__ . '/../inc/trb-artist-portal.php');
$start = strpos($source, 'function trb_portal_prepare_private_directory(');
$end = strpos($source, "\n}\n", $start) + 3;
eval(substr($source, $start, $end - $start));
$GLOBALS['storage_checks'] = 0; $server = null; $pipes = [];
try {
    $directory = $root . '/www/private';
    storage_check(trb_portal_prepare_private_directory($directory), 'New private directory protection failed.');
    $rules = file_get_contents($directory . '/.htaccess');
    storage_check(trb_portal_prepare_private_directory($directory), 'Repeated protection changed its behavior.');
    file_put_contents($directory . '/.htaccess', "# Require all denied\nRequire all granted\n");
    storage_check(!trb_portal_prepare_private_directory($directory), 'A comment was mistaken for a deny rule.');
    storage_check(str_contains(file_get_contents($directory . '/.htaccess'), 'Require all granted'), 'Unexpected existing rules were overwritten.');
    unlink($directory . '/.htaccess'); mkdir($directory . '/.htaccess');
    storage_check(!trb_portal_prepare_private_directory($directory), 'A blocked rules file was accepted.');
    rmdir($directory . '/.htaccess'); file_put_contents($root . '/outside', $rules);
    symlink($root . '/outside', $directory . '/.htaccess');
    storage_check(!trb_portal_prepare_private_directory($directory), 'A linked rules file was accepted.');
    unlink($directory . '/.htaccess'); file_put_contents($directory . '/.htaccess', str_replace("\n", "\r\n", $rules));
    storage_check(trb_portal_prepare_private_directory($directory), 'Valid CRLF deny rules were rejected.');
    file_put_contents($root . '/blocked', 'not a directory');
    storage_check(!trb_portal_prepare_private_directory($root . '/blocked'), 'Unavailable private storage was accepted.');

    $apache = getenv('TRB_APACHE_BINARY');
    if ($apache) {
        storage_check(is_executable($apache), 'The required Apache test binary is unavailable.');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false); fclose($socket);
        $user = posix_geteuid() === 0 ? 'www-data' : posix_getpwuid(posix_geteuid())['name'];
        $group = posix_geteuid() === 0 ? 'www-data' : posix_getgrgid(posix_getegid())['name'];
        $configuration = "ServerRoot /etc/apache2\nServerName localhost\nPidFile $root/apache.pid\nListen $address\nUser $user\nGroup $group\nDocumentRoot $root/www\nErrorLog $root/apache.log\n";
        foreach (['mpm_event', 'authz_core', 'access_compat'] as $module) $configuration .= "LoadModule {$module}_module /usr/lib/apache2/modules/mod_$module.so\n";
        $configuration .= "<Directory $root/www>\nAllowOverride All\nRequire all granted\n</Directory>\n";
        file_put_contents($root . '/apache.conf', $configuration);
        file_put_contents($root . '/www/control.txt', 'public fixture');
        foreach (['trb-artist-private', 'trb-release-private', 'trb-release-staging', 'trb-demo-private'] as $name) {
            storage_check(trb_portal_prepare_private_directory($root . '/www/' . $name), 'Private fixture protection failed.');
            file_put_contents($root . '/www/' . $name . '/synthetic.txt', 'private fixture');
        }
        mkdir($root . '/www/tools'); copy(__DIR__ . '/.htaccess', $root . '/www/tools/.htaccess');
        file_put_contents($root . '/www/tools/synthetic.txt', 'diagnostic fixture');
        $server = proc_open([$apache, '-f', $root . '/apache.conf', '-DFOREGROUND'], [0=>['pipe','r'], 1=>['file',$root . '/server.log','a'], 2=>['file',$root . '/server.log','a']], $pipes);
        storage_check(is_resource($server), 'Apache fixture did not start.');
        for ($attempt=0; $attempt<40; $attempt++) {
            $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
            if ($ready) { fclose($ready); break; }
            usleep(50000);
        }
        foreach (['/control.txt'=>200, '/trb-artist-private/synthetic.txt'=>403, '/trb-release-private/synthetic.txt'=>403, '/trb-release-staging/synthetic.txt'=>403, '/trb-demo-private/synthetic.txt'=>403, '/tools/synthetic.txt'=>403] as $path=>$expected) {
            $curl = curl_init('http://' . $address . $path);
            curl_setopt_array($curl, [CURLOPT_NOBODY=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>5]);
            $response = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
            storage_check($response !== false && $status === $expected, 'Apache access control failed: ' . $path . ' returned ' . $status);
        }
    }
    echo $GLOBALS['storage_checks'] . ' private storage checks passed' . ($apache ? ' including actual Apache HTTP 200/403 responses' : ' on the filesystem') . ".\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); fclose($pipes[0]); proc_close($server); }
    storage_remove($root);
}
