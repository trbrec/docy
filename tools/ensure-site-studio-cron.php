<?php
/** Idempotent installation under the existing deployment user; retains every other job. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
$theme = dirname(__DIR__);
$revision = $argv[1] ?? '';
if (!preg_match('/^[a-f0-9]{40}$/D', $revision) || trim((string) @file_get_contents($theme . '/.trb-deployed-sha')) !== $revision) exit(2);
require_once __DIR__ . '/site-studio-cron-lib.php';
define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
require '/home/customer/www/new1.trbrec.com/public_html/wp-load.php';
if (!\TRB\Studio\destination_site()) exit(3);
$state = ['at' => gmdate('c'), 'interval_seconds' => 900, 'installed' => false, 'code' => 'scheduler_unavailable'];
$private = '/home/customer/www/new1.trbrec.com/private/trb-site-studio';
$runner = __DIR__ . '/run-site-studio-cron.php';
$binary = realpath(PHP_BINARY);
if (!$binary || !is_file($runner) || is_link($runner) || !is_dir($private) || is_link($private)) exit(4);
update_option('trb_studio_cli_binary', $binary, false);
try {
    if (!function_exists('proc_open')) throw new RuntimeException('scheduler_unavailable');
    $lock = fopen($private . '/scheduler.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('scheduler_busy');
    $before = \TRB\Studio\Cron\current();
    $line = '*/15 * * * * ' . escapeshellarg($binary) . ' ' . escapeshellarg($runner) . ' > /dev/null 2>&1 # TRB_SITE_STUDIO_15MIN';
    $after = \TRB\Studio\Cron\plan($before, $line);
    if ($after !== $before) {
        if (\TRB\Studio\Cron\current() !== $before) throw new RuntimeException('scheduler_changed');
        $written = \TRB\Studio\Cron\process(['crontab', '-'], $after);
        if ($written['status'] !== 0) throw new RuntimeException('scheduler_write_failed');
    }
    if (\TRB\Studio\Cron\current() !== $after) throw new RuntimeException('scheduler_verify_failed');
    $state['installed'] = true; $state['code'] = '';
} catch (Throwable $error) {
    $allowed = ['scheduler_unavailable', 'scheduler_busy', 'scheduler_changed', 'scheduler_write_failed', 'scheduler_verify_failed', 'unknown_managed_job', 'duplicate_managed_job'];
    $state['code'] = in_array($error->getMessage(), $allowed, true) ? $error->getMessage() : 'scheduler_unavailable';
}
update_option('trb_studio_scheduler', $state, false);
echo $state['installed'] ? "TRB site-native 15-minute schedule verified.\n" : "TRB hosting schedule unavailable; WordPress and external fallback retained.\n";
