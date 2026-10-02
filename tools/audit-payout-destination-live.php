<?php
/** Read-only gateway and payout destination audit; no payment or account change. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();fwrite(STDERR,"Payment destination audit unconfirmed.\n");exit(1);});
$_SERVER['HTTP_HOST']='store.trbrec.com';$_SERVER['REQUEST_URI']='/';$_SERVER['HTTPS']='on';define('WP_USE_THEMES',false);define('DISABLE_WP_CRON',true);
require '/home/customer/www/store.trbrec.com/public_html/wp-load.php';
$gateways=WC()->payment_gateways()->payment_gateways();$out=['gateways'=>[]];
foreach(['stripe','ppcp-gateway','ppcp-card-button-gateway','ppcp-credit-card-gateway','bacs'] as $id){$g=$gateways[$id]??null;$out['gateways'][$id]=['installed'=>(bool)$g,'enabled'=>$g&&$g->enabled==='yes'];}
$settings=(array)get_option('woocommerce_stripe_settings',[]);$out['stripe_live_mode']=($settings['testmode']??'no')!=='yes';$out['bank_transfer_configured']=trb_onboarding_bank_configured();
if(class_exists('WC_Stripe_API')&&$out['stripe_live_mode']){
    $account=WC_Stripe_API::request([],'account','GET');
    $out['stripe_account_read']=!is_wp_error($account)&&is_object($account)&&($account->object??'')==='account';
    if($out['stripe_account_read']){
        $out['stripe_charges_enabled']=(bool)($account->charges_enabled??false);$out['stripe_payouts_enabled']=(bool)($account->payouts_enabled??false);$out['stripe_eur_bank']=[];
        foreach(($account->external_accounts->data??[]) as $bank)if(($bank->object??'')==='bank_account'&&($bank->currency??'')==='eur')$out['stripe_eur_bank'][]=['bank_name'=>$bank->bank_name??'','last4'=>$bank->last4??'','matches_authorized_hype_suffix'=>($bank->last4??'')==='7943','default_for_currency'=>(bool)($bank->default_for_currency??false),'status'=>$bank->status??''];
        $out['stripe_payout_schedule']=$account->settings->payouts->schedule->interval??null;
    }
}
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_SLASHES)."\n";
