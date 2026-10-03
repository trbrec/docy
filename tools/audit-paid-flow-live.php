<?php
/** Read-only post-deployment checks. No sends, payments or account changes. */
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();fwrite(STDERR,"Final residual audit unconfirmed.\n");exit(1);});
$theme='/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy';
$lines=[];exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($theme.'/tools/onboarding-readiness.php').' f87f532df994867e3a259581cc7d20038fc0cbfc 2>/dev/null',$lines,$readyExit);
$out=['connections'=>$readyExit===0?json_decode(implode("\n",$lines),true):null];
$lines=[];exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($theme.'/tools/finish-onboarding-privacy.php').' f87f532df994867e3a259581cc7d20038fc0cbfc --verify 2>/dev/null',$lines,$privacyExit);
$out['privacy']=$privacyExit===0?json_decode(implode("\n",$lines),true):null;
require '/home/customer/www/crm.trbrec.com/public_html/app/Core.php';\TrbCrm\Env::load('/home/customer/www/crm.trbrec.com/public_html/.env');
require '/home/customer/www/crm.trbrec.com/public_html/app/OnboardingContractCatalog.php';
$out['privacy_version_current']=str_contains(file_get_contents('/home/customer/www/crm.trbrec.com/public_html/app/OnboardingService.php'),'2026.2-documents-20261002c');
$db=\TrbCrm\Database::connection();$out['pending_cases']=[];$out['non_qa_old_sources']=0;
foreach($db->query('SELECT p.id,p.state,p.snapshot,c.status,c.sent_at,c.metadata FROM onboarding_practices p JOIN contracts c ON c.id=p.contract_id WHERE p.signed_at IS NULL AND p.cancelled_at IS NULL')->fetchAll() as $p){
$s=json_decode($p['snapshot'],true);$m=\TrbCrm\OnboardingContractCatalog::model($s['template_key']);$email=strtolower($s['email']??'');$label=strtoupper(implode(' ',[$s['contract_number']??'',$s['artist_name']??'',$p['metadata']??'']));
$qa=str_contains($label,'QA-')||str_contains($label,'NONVALIDO')||str_contains($label,'COLLAUDO')||in_array($email,['a.tognassi@gmail.com','spotify4@trbrec.com'],true);
$current=hash_equals($m['source_sha256'],$s['source_sha256']);if(!$qa&&!$current)$out['non_qa_old_sources']++;
$out['pending_cases'][]=['case'=>substr(hash('sha256',$p['id']),0,8),'qa'=>$qa,'group'=>$s['group_code'],'source_current'=>$current,'state'=>$p['state'],'contract_status'=>$p['status'],'sent'=>(bool)$p['sent_at']];
}
$out['uncertain_overdue_emails']=(int)$db->query("SELECT COUNT(*) FROM onboarding_events WHERE kind='overdue_reminder' AND status='uncertain'")->fetchColumn();
$out['pending_signature_dispatches']=(int)$db->query("SELECT COUNT(*) FROM onboarding_signatures WHERE state<>'completed'")->fetchColumn();
$out['payments_audit']=[
'confirmed_payments'=>(int)$db->query("SELECT COUNT(*) FROM onboarding_payments WHERE status='confirmed'")->fetchColumn(),
'held_practices'=>(int)$db->query("SELECT COUNT(DISTINCT practice_id) FROM onboarding_holds")->fetchColumn(),
'paid_waiting_owner'=>(int)$db->query("SELECT COUNT(*) FROM onboarding_practices WHERE first_payment_date IS NOT NULL AND owner_approved_at IS NULL AND cancelled_at IS NULL")->fetchColumn(),
'due_installments'=>(int)$db->query("SELECT COUNT(*) FROM onboarding_installments")->fetchColumn(),
'paid_without_signature_record'=>(int)$db->query("SELECT COUNT(*) FROM onboarding_practices p WHERE p.first_payment_date IS NOT NULL AND p.cancelled_at IS NULL AND NOT EXISTS(SELECT 1 FROM onboarding_signatures s WHERE s.practice_id=p.id)")->fetchColumn()
];
$out['signature_audit']=[
'created_dossiers'=>(int)$db->query("SELECT COUNT(*) FROM onboarding_signatures WHERE dossier_id IS NOT NULL")->fetchColumn(),
'completed_dossiers'=>(int)$db->query("SELECT COUNT(*) FROM onboarding_signatures WHERE state='completed'")->fetchColumn(),
'signed_pdfs'=>(int)$db->query("SELECT COUNT(*) FROM onboarding_artifacts WHERE slot='signed_pdf'")->fetchColumn(),
'signature_proofs'=>(int)$db->query("SELECT COUNT(*) FROM onboarding_artifacts WHERE slot='signature_audit'")->fetchColumn(),
'final_pdfs'=>(int)$db->query("SELECT COUNT(*) FROM onboarding_artifacts WHERE slot='final_pdf'")->fetchColumn(),
'owner_approvals'=>(int)$db->query("SELECT COUNT(*) FROM onboarding_practices WHERE owner_approved_at IS NOT NULL AND cancelled_at IS NULL")->fetchColumn(),
'activated_without_signed_pdf'=>(int)$db->query("SELECT COUNT(*) FROM onboarding_practices p WHERE p.portal_activated_at IS NOT NULL AND NOT EXISTS (SELECT 1 FROM onboarding_artifacts a WHERE a.practice_id=p.id AND a.slot='signed_pdf')")->fetchColumn(),
'activated_without_signature_proof'=>(int)$db->query("SELECT COUNT(*) FROM onboarding_practices p WHERE p.portal_activated_at IS NOT NULL AND NOT EXISTS (SELECT 1 FROM onboarding_artifacts a WHERE a.practice_id=p.id AND a.slot='signature_audit')")->fetchColumn()
];
$store= <<<'STORE'
$_SERVER['HTTP_HOST']='store.trbrec.com';$_SERVER['REQUEST_URI']='/';$_SERVER['HTTPS']='on';define('WP_USE_THEMES',false);define('DISABLE_WP_CRON',true);ob_start();require '/home/customer/www/store.trbrec.com/public_html/wp-load.php';
$g=WC()->payment_gateways()->payment_gateways();$s=(array)get_option('woocommerce_stripe_settings',[]);
$storeSource=file_get_contents(get_stylesheet_directory().'/inc/trb-onboarding-payments.php');
$r=['instant_only_checkout'=>!str_contains($storeSource,'if(trb_onboarding_bank_configured())'),'paypal_enabled'=>isset($g['ppcp-gateway'])&&$g['ppcp-gateway']->enabled==='yes','stripe_enabled'=>isset($g['stripe'])&&$g['stripe']->enabled==='yes','stripe_live'=>($s['testmode']??'no')!=='yes','direct_bank_disabled'=>!isset($g['bacs'])||$g['bacs']->enabled!=='yes','bank_accounts_empty'=>!(array)get_option('woocommerce_bacs_accounts',[]),'onboarding_enabled'=>(bool)get_option('trb_onboarding_payments_enabled')];
if(class_exists('WC_Stripe_API')&&$r['stripe_live']){$a=WC_Stripe_API::request([],'account','GET');$r['stripe_account_read']=!is_wp_error($a)&&is_object($a)&&($a->object??'')==='account';if($r['stripe_account_read']){$r['charges_enabled']=(bool)$a->charges_enabled;$r['payouts_enabled']=(bool)$a->payouts_enabled;$r['payouts_daily']=($a->settings->payouts->schedule->interval??null)==='daily';}}
while(ob_get_level())ob_end_clean();echo json_encode($r);
STORE;
$artist= <<<'ARTIST'
$_SERVER['HTTP_HOST']='artist.trbrec.com';$_SERVER['REQUEST_URI']='/';$_SERVER['HTTPS']='on';define('WP_USE_THEMES',false);define('DISABLE_WP_CRON',true);ob_start();require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
$profiles=trb_portal_profiles();$profileChecks=[];foreach(['trb','dds','ddb12','ddb','ddb_trb'] as $key)$profileChecks[$key]=isset($profiles[$key]['role'])&&get_role($profiles[$key]['role'])!==null;
$r=['all_contract_profiles_ready'=>!in_array(false,$profileChecks,true),'worker_scheduled'=>(bool)wp_next_scheduled('trb_onboarding_worker'),'identity_sweep_scheduled'=>(bool)wp_next_scheduled('trb_onboarding_identity_sweep'),'onboarding_enabled'=>function_exists('trb_onboarding_enabled')&&trb_onboarding_enabled(),'privacy_url_present'=>get_privacy_policy_url()!==''];
while(ob_get_level())ob_end_clean();echo json_encode($r);
ARTIST;
foreach(['store'=>$store,'artist'=>$artist] as $key=>$code){$lines=[];exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>/dev/null',$lines,$exit);$out[$key]=$exit===0?json_decode(implode("\n",$lines),true):null;}
$connectionsReady=is_array($out['connections']);foreach(['revision','archive_connection','portal_secret','identity_key','signature_secret','invitation_key','signature_adapter','mail_adapter','contract_sources','portal_adapter','portal_loaded','portal_route_loaded','portal_key_matches','portal_store_configured','portal_approval_plugin','portal_http_2xx'] as $key)$connectionsReady=$connectionsReady&&($out['connections'][$key]??false)===true;
foreach(['portal_http_403','portal_http_404','portal_http_5xx'] as $key)$connectionsReady=$connectionsReady&&($out['connections'][$key]??true)===false;
$runtimeSource=file_get_contents('/home/customer/www/crm.trbrec.com/public_html/app/OnboardingRuntime.php');$ledgerSource=file_get_contents('/home/customer/www/crm.trbrec.com/public_html/app/OnboardingLedger.php');$serviceSource=file_get_contents('/home/customer/www/crm.trbrec.com/public_html/app/OnboardingService.php');$workflowSource=file_get_contents('/home/customer/www/crm.trbrec.com/public_html/app/OnboardingContractWorkflow.php');
$out['automatic_handoff_installed']=str_contains($runtimeSource,'advanceSignature');
$out['handoff_checks']=['confirmed_owner_delivery'=>str_contains($workflowSource,"'automatic_signature'=>true")&&str_contains($ledgerSource,'automaticSignatureOwner'),'exact_capture_required'=>str_contains($serviceSource,'Incasso non confermato dal circuito'),'automatic_dispatch'=>str_contains($serviceSource,'function advanceSignature'),'serialized_preparation'=>str_contains($runtimeSource,'trb_onboarding_signature_')];
$good=$out['non_qa_old_sources']===0&&$out['privacy_version_current']&&$connectionsReady&&($out['privacy']['success']??false)===true
&&is_array($out['store'])&&!in_array(false,$out['store'],true)&&is_array($out['artist'])&&!in_array(false,$out['artist'],true);
$out['success']=$good&&$out['automatic_handoff_installed']&&!in_array(false,$out['handoff_checks'],true);$good=$out['success'];while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_SLASHES)."\n";exit($good?0:1);
