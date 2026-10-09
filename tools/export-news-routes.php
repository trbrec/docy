<?php
/** Read-only export of published editorial routes for anonymous responsive QA. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$revision=$argv[1]??'';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision)exit(2);
ob_start();define('WP_USE_THEMES',false);require '/home/customer/www/new1.trbrec.com/public_html/wp-load.php';ob_end_clean();
if(!\TRB\Studio\destination_site())exit(3);
$posts=get_posts(['post_type'=>'post','post_status'=>'publish','posts_per_page'=>100,'orderby'=>'ID','order'=>'ASC']);
foreach($posts as $post){$path=wp_parse_url(get_permalink($post),PHP_URL_PATH);if(preg_match('~^/[a-z0-9-]+/$~D',(string)$path))echo $path."\n";}
