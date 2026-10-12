<?php
/**
 * Plugin Name: TRB Login Cache Guard
 * Description: Prevent cached responses on the artist login route before normal plugins run.
 */
if (!defined('ABSPATH') || PHP_SAPI === 'cli') {
    return;
}
$trb_login_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!is_string($trb_login_path) || !preg_match('#^/accedi/?$#D', $trb_login_path)) {
    return;
}
if (!defined('DONOTCACHEPAGE')) {
    define('DONOTCACHEPAGE', true);
}
$trb_login_no_cache = static function () {
    if (!headers_sent()) {
        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
        header('Pragma: no-cache', true);
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT', true);
    }
};
$trb_login_no_cache();
add_action('send_headers', $trb_login_no_cache, PHP_INT_MAX);
unset($trb_login_path, $trb_login_no_cache);
