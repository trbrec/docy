<?php
/** Site-native CLI heartbeat, independent of web visits and external CI queues. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
$theme = dirname(__DIR__);
$revision = trim((string) @file_get_contents($theme . '/.trb-deployed-sha'));
if (!preg_match('/^[a-f0-9]{40}$/D', $revision)) exit(2);
require_once __DIR__ . '/site-studio-cron-lib.php';
define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
require '/home/customer/www/new1.trbrec.com/public_html/wp-load.php';
if (!\TRB\Studio\destination_site()) exit(3);
$manual = ($argv[1] ?? '') === '--manual';
if (!get_option('trb_studio_enabled') && !$manual) exit;
$result = \TRB\Studio\Cron\process([PHP_BINARY, __DIR__ . '/sync-site-studio.php', $revision, 'transfer', $manual ? '--manual' : '']);
if ($result['status'] === 5) exit; // An approved transfer already holds the shared lock.
update_option('trb_studio_cron_report', ['at' => gmdate('c'), 'ok' => $result['status'] === 0], false);
exit($result['status'] === 0 ? 0 : 1);
