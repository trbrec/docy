<?php
require __DIR__ . '/site-studio-cron-lib.php';
use function TRB\Studio\Cron\plan;
$line = "*/15 * * * * '/usr/bin/php' '/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy/tools/run-site-studio-cron.php' > /dev/null 2>&1 # TRB_SITE_STUDIO_15MIN";
$other = "MAILTO=owner@example.test\n0 4 * * * /usr/bin/backup\n# retain this comment\n";
if (plan('', $line) !== $line . "\n") throw new RuntimeException('initial schedule');
$installed = plan($other, $line);
if ($installed !== $other . $line . "\n") throw new RuntimeException('unrelated jobs preserved');
if (plan($installed, $line) !== $installed) throw new RuntimeException('idempotent schedule');
$replacement = str_replace('/usr/bin/php', '/usr/local/bin/php', $line);
if (plan($installed, $replacement) !== $other . $replacement . "\n") throw new RuntimeException('managed binary update');
foreach ([$line . "\n" . $line . "\n", '# TRB_SITE_STUDIO_15MIN unexpected' . "\n"] as $invalid) {
    try { plan($invalid, $line); throw new RuntimeException('unsafe plan accepted'); }
    catch (RuntimeException $e) { if (!in_array($e->getMessage(), ['duplicate_managed_job', 'unknown_managed_job'], true)) throw $e; }
}
echo "Site-native cron preservation and conflict checks passed.\n";
