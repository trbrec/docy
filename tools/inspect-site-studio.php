<?php
/** Read-only review of approved artists' official photographs. Originals stay private. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
$revision = $argv[1] ?? '';
if (!preg_match('/^[a-f0-9]{40}$/D', $revision) || trim((string) @file_get_contents(dirname(__DIR__) . '/.trb-deployed-sha')) !== $revision) exit(2);
$mode = $argv[2] ?? '';
$private = '/home/customer/www/artist.trbrec.com/private';
$report = $private . '/trb-studio-photo-review.json';
if ($mode === 'source') {
 define('WP_USE_THEMES', false);
 require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
 $review = ['revision' => $revision, 'generated_at' => gmdate('c'), 'artists' => [], 'registry' => []];
 $add = function ($artist, $path, $origin, $label) use (&$review) {
  if (!is_file($path) || filesize($path) > 20 * 1024 * 1024) return;
  $size = @getimagesize($path);
  if (!$size || !in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) return;
  $hash = hash_file('sha256', $path);
  $key = substr(hash('sha256', $artist . ':' . $hash), 0, 20);
  if (isset($review['registry'][$key])) return;
  $editor = wp_get_image_editor($path);
  if (is_wp_error($editor)) return;
  $editor->resize(600, 600, false);
  $editor->set_quality(76);
  $tmp = wp_tempnam('trb-photo-review');
  $result = $editor->save($tmp, 'image/jpeg');
  if (is_wp_error($result)) { @unlink($tmp); return; }
  $bytes = file_get_contents($result['path']);
  @unlink($result['path']);
  if ($result['path'] !== $tmp) @unlink($tmp);
  $review['registry'][$key] = ['artist_id' => $artist, 'hash' => $hash, 'origin' => $origin, 'path' => $origin === 'portal' ? $path : ''];
  $review['artists'][$artist]['photos'][] = ['key' => $key, 'label' => $label, 'origin' => $origin === 'portal' ? 'Portale Artisti' : 'Archivio ufficiale', 'width' => $size[0], 'height' => $size[1], 'hash' => $hash, 'base64' => base64_encode($bytes)];
 };
 foreach (get_users(['number' => 1001, 'orderby' => 'ID']) as $user) {
  if (!\TRB\Studio\portal_artist_allowed($user)) continue;
  $id = (int) $user->ID;
  $review['artists'][$id] = ['name' => \TRB\Studio\portal_artist_name($user), 'photos' => []];
  $number = 0;
  foreach ((array) get_user_meta($id, '_trb_artist_private_files', true) as $file) {
   if (!is_array($file) || ($file['group'] ?? '') !== 'photo' || ++$number > 6) continue;
   $path = trb_artist_promo_local_photo($file);
   if ($path) $add($id, $path, 'portal', 'Foto caricata ' . $number);
  }
 }
 // Exact folders already assigned to these artists in the reviewed archive inventory.
 $folders = [
  128 => ['/Discografia - TRB rec/FaDe - Alessio de Fanzoni/01. Materiale aggiornato - foto e bio'],
  73 => ['/Discografia - TRB rec/Solidoro/Bestemmiare/Photo', '/Discografia - TRB rec/Solidoro/Proverò a descriverti/PROMO/INPUT_ARTISTA'],
  127 => ['/Discografia - TRB rec/Eres!a/#Media'],
  41 => ['/Discografia - DDB/Edmondo Romano  Simona Fasano/Enfado/PROMO/INPUT_ARTISTA', '/Discografia - DDB/Edmondo Romano  Simona Fasano/ES SÉ female side (EP)/PROMO/INPUT_ARTISTA']
 ];
 $old = json_decode((string) @file_get_contents($private . '/trb-studio-inspection.json'), true);
 $requests = 0;
 foreach ($folders as $id => $paths) {
  if (!isset($review['artists'][$id])) continue;
  foreach ($paths as $folder) foreach (($old['archive_inventory'][$folder]['entries'] ?? []) as $file) {
   if ($requests >= 32) break 3;
   $name = $file['name'];
   if (!empty($file['directory']) || !preg_match('/\.(jpe?g|png|webp)$/i', $name) || ($file['bytes'] ?? 0) > 20 * 1024 * 1024) continue;
   if (preg_match('/banner|post|stor(y|ia)|spotify|promo|release|header|c92180|^[a-f0-9]{8}-/i', $name)) continue;
   ++$requests;
   $remote = $folder . '/' . $name;
   $r = trb_demo_webdav_request('GET', $remote);
   if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) continue;
   $bytes = wp_remote_retrieve_body($r);
   if (strlen($bytes) > 20 * 1024 * 1024) continue;
   $tmp = wp_tempnam('trb-review-archive');
   if (file_put_contents($tmp, $bytes) === false) continue;
   $add($id, $tmp, $remote, $name);
   @unlink($tmp);
  }
 }
 if (!is_dir($private) && !mkdir($private, 0700, true)) exit(3);
 if (file_put_contents($report, wp_json_encode($review)) === false) exit(4);
 chmod($report, 0600);
 exit;
}
if ($mode === 'destination') {
 define('WP_USE_THEMES', false);
 require '/home/customer/www/new1.trbrec.com/public_html/wp-load.php';
 $review = json_decode((string) @file_get_contents($report), true);
 if (!is_array($review) || ($review['revision'] ?? '') !== $revision) exit(5);
 $summary = [];
 foreach ($review['artists'] as $id => $artist) {
  update_option('wpvibe_task_trb_photo_review_' . (int) $id, $artist, false);
  $summary[] = ['id' => (int) $id, 'name' => $artist['name'], 'photos' => array_map(fn($photo) => array_diff_key($photo, ['base64' => true]), $artist['photos'])];
 }
 update_option('trb_studio_photo_review', ['revision' => $revision, 'generated_at' => $review['generated_at'], 'artists' => $summary], false);
 exit;
}
exit(6);
