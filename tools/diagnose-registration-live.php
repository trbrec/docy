<?php
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();fwrite(STDERR,"Registration state diagnostic unconfirmed.\n");exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');require $root.'/app/OnboardingRuntime.php';
$db=\TrbCrm\Database::connection();$ledger=new \TrbCrm\OnboardingLedger($db);$p=$ledger->forContract(26);
if(!$p||$p['email']!=='a.tognassi@gmail.com'||$p['snapshot']['contract_number']!=='QA-TRB-NONVALIDO-20261002-2235')throw new RuntimeException('Scope');
$q=$db->prepare('SELECT payload FROM onboarding_events WHERE event_key=?');$q->execute(['onboarding:'.$p['id'].':portal-active']);$bound=(int)(json_decode((string)$q->fetchColumn(),true)['portal_user_id']??0);
$code='define("WP_USE_THEMES",false);define("DISABLE_WP_CRON",true);require "/home/customer/www/artist.trbrec.com/public_html/wp-load.php";$source=[];$a=pw_new_user_approve();foreach(["update_user_status","admin_approval_email"] as $name){$m=new ReflectionMethod($a,$name);$source[$name]=implode("",array_slice(file($m->getFileName()),$m->getStartLine()-1,$m->getEndLine()-$m->getStartLine()+1));}$u=get_user_by("email","a.tognassi@gmail.com");$source["wpdm_status"]=$u?get_user_meta($u->ID,"__wpdm_user_status",true):null;$source["wpdm_requires_approval"]=function_exists("WPDM")?WPDM()->user->requiresApproval():null;$source["wpdm_methods"]=function_exists("WPDM")?array_values(array_filter(get_class_methods(WPDM()->user),static fn($n)=>preg_match("/approv|status|auth|login/i",$n))):[];echo json_encode($source);';
exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>/dev/null',$lines,$status);$wp=json_decode(implode("\n",$lines),true);if($status!==0||!is_array($wp))throw new RuntimeException('WP');
$out=['approval_source'=>$wp];while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_SLASHES)."\n";
