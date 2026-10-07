<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$revision=$argv[1]??'';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision)exit(2);
define('WP_USE_THEMES',false);require '/home/customer/www/new1.trbrec.com/public_html/wp-load.php';
if(!\TRB\Studio\destination_site())exit(3);
foreach(\TRB\Studio\directory_data()['artists'] as $a){$url=\TRB\Studio\artist_page_url($a);$path=wp_parse_url($url,PHP_URL_PATH);if(preg_match('~^/artisti/[a-z0-9-]+/$~D',(string)$path))echo $path."\n";}
