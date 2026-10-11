<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$root='/home/customer/www/crm.trbrec.com/public_html';
require_once $root.'/app/Core.php';
\TrbCrm\Env::load($root.'/.env');
require_once $root.'/app/GiuliaSupport.php';
require_once $root.'/app/GiuliaReport.php';
try{$result=\TrbCrm\GiuliaReport::run();echo 'GIULIA_REPORT_STATUS '.$result['status']."\n";}
catch(Throwable $e){fwrite(STDERR,"GIULIA_REPORT_DELIVERY_UNCONFIRMED\n");exit(1);}
