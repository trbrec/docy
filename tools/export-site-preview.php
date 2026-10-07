<?php
/** Anonymous, read-only HTML export for responsive visual QA. No forms are submitted. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
$revision = $argv[1] ?? '';
$route = $argv[2] ?? '';
if (!preg_match('/^[a-f0-9]{40}$/D', $revision) || trim((string) @file_get_contents(dirname(__DIR__) . '/.trb-deployed-sha')) !== $revision) exit(2);
$routes = ['/', '/artisti/', '/demo/', '/catalogo/', '/news/', '/chi-siamo/', '/cosa-facciamo/', '/booking/', '/licenze-audio/', '/contatti/', '/privacy-policy/'];
if (!in_array($route, $routes, true)) exit(3);
$root = '/home/customer/www/new1.trbrec.com/public_html';
$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'new1.trbrec.com';
$_SERVER['REQUEST_URI'] = $route;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
$_SERVER['QUERY_STRING'] = '';
$_GET = $_POST = $_COOKIE = [];
define('WP_USE_THEMES', true);
chdir($root);
ob_start();
require $root . '/wp-blog-header.php';
$html = ob_get_clean();
if (!str_contains($html, '<main') || is_user_logged_in()) exit(4);
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors();
$local = static function ($url, $suffixes) use ($root) {
 $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
 $host = wp_parse_url($url, PHP_URL_HOST);
 if ($host && $host !== 'new1.trbrec.com') return '';
 $path = rawurldecode((string) wp_parse_url($url, PHP_URL_PATH));
 if (!str_starts_with($path, '/wp-content/') && !str_starts_with($path, '/wp-includes/')) return '';
 if (str_contains($path, 'trb-artist-private') || str_contains($path, 'trb-release-private')) return '';
 $file = realpath($root . $path);
 if (!$file || !str_starts_with($file, $root . '/') || !in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $suffixes, true) || filesize($file) > 3 * 1024 * 1024) return '';
 return $file;
};
// Include only styles and public media. Remove executable scripts and submission tokens.
foreach (iterator_to_array($dom->getElementsByTagName('script')) as $node) $node->parentNode->removeChild($node);
foreach (iterator_to_array($dom->getElementsByTagName('input')) as $node) if ($node->getAttribute('type') === 'hidden') $node->parentNode->removeChild($node);
foreach (iterator_to_array($dom->getElementsByTagName('link')) as $node) {
 if ($node->getAttribute('rel') !== 'stylesheet') continue;
 $file = $local($node->getAttribute('href'), ['css']);
 if (!$file) { $node->parentNode->removeChild($node); continue; }
 $style = $dom->createElement('style');
 $style->appendChild($dom->createTextNode(file_get_contents($file)));
 $node->parentNode->replaceChild($style, $node);
}
foreach ($dom->getElementsByTagName('img') as $node) {
 $file = $local($node->getAttribute('src'), ['jpg', 'jpeg', 'png', 'webp', 'svg']);
 if ($file) {
  $mime = str_ends_with($file, '.svg') ? 'image/svg+xml' : wp_get_image_mime($file);
  if ($mime) $node->setAttribute('src', 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file)));
 }
 $node->removeAttribute('srcset'); $node->setAttribute('loading', 'eager');
}
$meta = $dom->createElement('meta'); $meta->setAttribute('name', 'trb-qa-export'); $meta->setAttribute('content', $revision . ' anonymous ' . gmdate('c'));
$dom->getElementsByTagName('head')->item(0)->appendChild($meta);
echo $dom->saveHTML();
