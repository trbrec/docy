<?php
/** Read-only CLI export of the published roster and its real WordPress CSS for browser QA. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$theme=dirname(__DIR__);$revision=$argv[1]??'';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents($theme.'/.trb-deployed-sha'))!==$revision)exit(2);
$root='/home/customer/www/new1.trbrec.com/public_html';
define('WP_USE_THEMES',false);
require $root.'/wp-load.php';
if(strtolower((string)wp_parse_url(home_url(),PHP_URL_HOST))!=='new1.trbrec.com')exit(3);
foreach(['directory.php','directory.css','trb-site-studio.php'] as $file){
 $source=$theme.'/integrations/site-studio/trb-site-studio/'.$file;
 $installed=WP_PLUGIN_DIR.'/trb-site-studio/'.$file;
 if(!is_file($source)||!is_file($installed)||!hash_equals(hash_file('sha256',$source),hash_file('sha256',$installed)))exit(4);
}
$page=get_post(936);
if(!$page||$page->post_type!=='page'||$page->post_status!=='publish'||!has_shortcode($page->post_content,'trb_public_roster'))exit(5);
wp_set_current_user(0);
$GLOBALS['wp_query']=new WP_Query(['page_id'=>936,'post_status'=>'publish']);
$GLOBALS['post']=$page;setup_postdata($page);
ob_start();wp_head();$head=ob_get_clean();
$doc=new DOMDocument();$previous=libxml_use_internal_errors(true);
$doc->loadHTML('<!doctype html><html><head><meta charset="utf-8">'.$head.'</head><body></body></html>');
libxml_clear_errors();libxml_use_internal_errors($previous);
$css='';$bytes=0;$host=wp_parse_url(home_url(),PHP_URL_HOST);
foreach($doc->getElementsByTagName('head')->item(0)->childNodes as $node){
 if(!($node instanceof DOMElement))continue;
 if($node->tagName==='style'){$part=$node->textContent;}
 elseif($node->tagName==='link'&&strtolower($node->getAttribute('rel'))==='stylesheet'){
  $url=$node->getAttribute('href');$urlHost=wp_parse_url($url,PHP_URL_HOST);
  if($urlHost&&$urlHost!==$host)continue;
  $path=wp_parse_url($url,PHP_URL_PATH);if(!is_string($path)||!str_ends_with($path,'.css'))continue;
  $file=realpath($root.'/'.ltrim(rawurldecode($path),'/'));
  if(!$file||!str_starts_with($file,$root.'/')||!is_file($file)||filesize($file)>2*1024*1024)continue;
  $part=file_get_contents($file);
 }else continue;
 $bytes+=strlen($part);if($bytes>8*1024*1024)exit(6);
 $css.='<style>'.str_ireplace('</style','<\\/style',$part).'</style>';
}
if(!str_contains($css,'trb-directory-roster'))exit(7);
$content=apply_filters('the_content',$page->post_content);
if(!str_contains($content,'class="trb-directory trb-directory-roster"'))exit(8);
echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="https://new1.trbrec.com/artisti/">'.$css.'</head><body class="'.esc_attr(implode(' ',get_body_class())).'"><main class="wp-block-group is-layout-flow" id="main"><div class="entry-content wp-block-post-content is-layout-flow">'.$content.'</div></main></body></html>';
