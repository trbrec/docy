<?php
/** CLI-only fixed-path hashes. Never exports source, customer rows or credentials. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors','0');
set_exception_handler(static function(){fwrite(STDERR,"Scoped activation source readback unconfirmed.\n");exit(1);});
$portalDeferralRoot='/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy';
define('SHORTINIT',true); define('DISABLE_WP_CRON',true);
ob_start(); require '/home/customer/www/store.trbrec.com/public_html/wp-load.php'; ob_end_clean();
global $wpdb;
$portalDeferralSlug=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",'stylesheet'));
if ($wpdb->last_error || !is_string($portalDeferralSlug) || !preg_match('/^[A-Za-z0-9_-]+$/D',$portalDeferralSlug)) throw new RuntimeException('Stylesheet unavailable');
$portalDeferralStore='/home/customer/www/store.trbrec.com/public_html/wp-content/themes/'.$portalDeferralSlug;
$portalDeferralPaths=[
 'portal/inc/trb-candidate-onboarding.php'=>$portalDeferralRoot.'/inc/trb-candidate-onboarding.php',
 'portal/inc/trb-candidate-onboarding-page.php'=>$portalDeferralRoot.'/inc/trb-candidate-onboarding-page.php',
 'portal/assets/js/onboarding/onboarding.js'=>$portalDeferralRoot.'/assets/js/onboarding/onboarding.js',
 'store/inc/trb-onboarding-payments.php'=>$portalDeferralStore.'/inc/trb-onboarding-payments.php',
];
if (file_exists($portalDeferralStore.'/inc/trb-onboarding-deferral.php')) $portalDeferralPaths['store/inc/trb-onboarding-deferral.php']=$portalDeferralStore.'/inc/trb-onboarding-deferral.php';
$portalDeferralOutput=['read_only'=>true,'customer_rows_read'=>false,'network_requests'=>0,'store_stylesheet'=>$portalDeferralSlug,'sources'=>[],'flags'=>[]];
foreach ($portalDeferralPaths as $portalDeferralLabel=>$portalDeferralPath) {
 for($portalDeferralParent=$portalDeferralPath;strlen($portalDeferralParent)>strlen('/home/customer/www');$portalDeferralParent=dirname($portalDeferralParent)) if(is_link($portalDeferralParent)) throw new RuntimeException('Source link refused');
 if(!is_file($portalDeferralPath)||filesize($portalDeferralPath)>524288) throw new RuntimeException('Source unavailable');
 $portalDeferralContent=file_get_contents($portalDeferralPath);
 $portalDeferralOutput['sources'][$portalDeferralLabel]=['sha256'=>hash('sha256',$portalDeferralContent),'normalized_lf_sha256'=>hash('sha256',str_replace("\r\n","\n",$portalDeferralContent)),'bytes'=>strlen($portalDeferralContent)];
}
$portalDeferralOutput['source_code_exported']=false;
foreach(['trb_onboarding_payments_enabled','trb_onboarding_deferral_payments_enabled'] as $portalDeferralOption){
 $portalDeferralValue=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$portalDeferralOption));
 if($wpdb->last_error)throw new RuntimeException('Flag read failed');
 $portalDeferralOutput['flags'][$portalDeferralOption]=in_array($portalDeferralValue,['1',1,true],true);
}
echo json_encode($portalDeferralOutput,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
