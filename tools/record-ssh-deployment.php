<?php
/** Record the verified SSH revision so the WordPress safety net does not redeploy it. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
set_exception_handler(static function($error){exit(1);});
$sshRevision=$argv[1]??'';$sshTheme=dirname(__DIR__);
if(!preg_match('/^[a-f0-9]{40}$/D',$sshRevision)||trim((string)@file_get_contents($sshTheme.'/.trb-deployed-sha'))!==$sshRevision)exit(2);
$_SERVER['HTTP_HOST']='artist.trbrec.com';$_SERVER['REQUEST_URI']='/';$_SERVER['HTTPS']='on';
define('WP_USE_THEMES',false);define('DISABLE_WP_CRON',true);ob_start();
require dirname(__DIR__,4).'/wp-load.php';
if(realpath(get_template_directory())!==realpath($sshTheme)||!defined('TRB_DOCY_DEPLOYED_SHA_OPTION')||!function_exists('trb_docy_store_deploy_status'))exit(1);
update_option(TRB_DOCY_DEPLOYED_SHA_OPTION,$sshRevision,false);
if(get_option(TRB_DOCY_DEPLOYED_SHA_OPTION)!==$sshRevision)exit(1);
trb_docy_store_deploy_status('success','Tema pubblicato e verificato tramite SSH.',$sshRevision);
ob_end_clean();
