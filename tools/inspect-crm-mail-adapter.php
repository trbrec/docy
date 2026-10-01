<?php
if (PHP_SAPI !== 'cli') exit;
$crm='/home/customer/www/crm.trbrec.com/public_html';
require_once $crm.'/app/Core.php';
\TrbCrm\Env::load($crm.'/.env');
$class=new ReflectionClass(\TrbCrm\OutboundMail::class);
echo "ADAPTER SOURCE\n";
echo file_get_contents($class->getFileName());
echo "\nADAPTER FILES\n";
foreach (glob($crm.'/app/*Mail*') as $file) echo basename($file)."\n";
