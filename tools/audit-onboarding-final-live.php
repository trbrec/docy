<?php
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();
$code= <<<'CODE'
ini_set('display_errors','0');ob_start();$stage='before-bootstrap';register_shutdown_function(static function()use(&$stage){$e=error_get_last();while(ob_get_level())ob_end_clean();echo json_encode(['stage'=>$stage,'fatal'=>$e?['type'=>$e['type'],'file'=>basename($e['file']),'line'=>$e['line']]:null]);});
$_SERVER['HTTP_HOST']='artist.trbrec.com';$_SERVER['REQUEST_URI']='/';$_SERVER['HTTPS']='on';define('WP_USE_THEMES',false);define('DISABLE_WP_CRON',true);require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
$stage='bootstrapped';$id=(int)get_option('wp_page_for_privacy_policy');$stage='read-option';$p=get_page_by_path('privacy-policy');$stage='read-page';$r=['id'=>$id,'page'=>$p?['id'=>$p->ID,'status'=>$p->post_status,'content'=>$p->post_content]:null,'privacy_url'=>get_privacy_policy_url()];while(ob_get_level())ob_end_clean();echo json_encode(['result'=>$r]);$stage='done';
CODE;
exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>/dev/null',$lines,$status);while(ob_get_level())ob_end_clean();echo json_encode(['artist_diagnostic'=>implode("\n",$lines),'exit'=>$status])."\n";
