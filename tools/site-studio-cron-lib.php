<?php
/** Pure cron planning and process helpers; never discard unrelated jobs. */
namespace TRB\Studio\Cron;
function plan($current, $line) {
    $tag = '# TRB_SITE_STUDIO_15MIN';
    $rows = explode("\n", rtrim($current, "\n"));
    $matches = array_filter($rows, fn($row) => str_contains($row, $tag));
    if (count($matches) > 1) throw new \RuntimeException('duplicate_managed_job');
    if ($matches) {
        $old = reset($matches);
        if (!preg_match('~^\*/15 \* \* \* \* .+/tools/run-site-studio-cron\.php\x27 > /dev/null 2>&1 # TRB_SITE_STUDIO_15MIN$~D', $old)) throw new \RuntimeException('unknown_managed_job');
        return str_replace($old, $line, $current);
    }
    return ($current === '' ? '' : rtrim($current, "\n") . "\n") . $line . "\n";
}
function process(array $command, $stdin = '') {
    $pipes = [];
    $handle = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($handle)) return ['status' => 127, 'out' => '', 'err' => ''];
    fwrite($pipes[0], $stdin); fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
    return ['status' => proc_close($handle), 'out' => $out, 'err' => $err];
}
function current() {
    $result = process(['crontab', '-l']);
    if ($result['status'] === 0) return $result['out'];
    if ($result['status'] === 1 && preg_match('/^no crontab for [a-zA-Z0-9_-]+\s*$/D', trim($result['err']))) return '';
    throw new \RuntimeException('scheduler_unavailable');
}
