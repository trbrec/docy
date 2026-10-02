<?php
/** One isolated pending checkout. No charge, signature, real identity or email. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ob_start();set_exception_handler(static function(){while(ob_get_level())ob_end_clean();fwrite(STDERR,"Isolated checkout QA unconfirmed.\n");exit(1);});
$_SERVER['HTTP_HOST']='store.trbrec.com';$_SERVER['REQUEST_URI']='/';$_SERVER['HTTPS']='on';define('WP_USE_THEMES',false);define('DISABLE_WP_CRON',true);require '/home/customer/www/store.trbrec.com/public_html/wp-load.php';
foreach(array('new_order','cancelled_order','customer_on_hold_order','customer_processing_order','customer_completed_order','customer_invoice') as $emailType)add_filter('woocommerce_email_enabled_'.$emailType,'__return_false',PHP_INT_MAX);
$key='trb_onboarding_checkout_qa_20261002_versamento';$order=wc_get_order((int)get_option($key));
if((getenv('TRB_CHECKOUT_QA_ACTION')?:'create')==='close'){
    if(!$order||$order->get_meta('_trb_onboarding_checkout_qa')!=='20261002'||$order->get_billing_email()!=='qa-checkout@example.invalid'||$order->get_transaction_id()!==''||$order->is_paid())throw new RuntimeException('QA cleanup guard');
    $order->update_status('cancelled','Collaudo della pagina di pagamento concluso senza addebiti.');while(ob_get_level())ob_end_clean();echo json_encode(['qa_cancelled'=>$order->has_status('cancelled'),'no_payment'=>!$order->is_paid()])."\n";exit;
}
if(!function_exists('trb_onboarding_checkout_button_text')||!function_exists('trb_onboarding_instant_gateways')||!in_array('ppcp-card-button-gateway',trb_onboarding_instant_gateways(),true))throw new RuntimeException('New card route not deployed');
if(!$order){
    $request=new WP_REST_Request('POST');$request->set_body_params(['practice_id'=>hash('md5','TRB-CHECKOUT-QA-20261002-VERSAMENTO'),'number'=>1,'amount_cents'=>100,'installment_total_cents'=>100,'currency'=>'EUR','snapshot_sha256'=>hash('sha256','TRB-CHECKOUT-QA-20261002-VERSAMENTO'),'email'=>'qa-checkout@example.invalid','billing'=>['first_name'=>'COLLAUDO','last_name'=>'NON PAGARE','address_1'=>'Via Collaudo 1','city'=>'Roma','postcode'=>'00100','country'=>'IT','state'=>'RM','phone'=>'+393330000000'],'tax_code'=>'RSSMRA90A01H501W']);
    // Use the normal internal order adapter; request decoding is deliberately JSON.
    $request->set_header('Content-Type','application/json');$request->set_body(wp_json_encode($request->get_body_params()));$result=trb_onboarding_store_order($request);if(is_wp_error($result))throw new RuntimeException('QA order unavailable');
    $order=wc_get_order($result['order_id']);$order->update_meta_data('_trb_onboarding_checkout_qa','20261002');foreach($order->get_items('fee') as $fee){$fee->set_name('COLLAUDO TRB — NON PAGARE');$fee->save();}$order->save();update_option($key,$order->get_id(),false);
}
if($order->get_meta('_trb_onboarding_checkout_qa')!=='20261002'||!$order->has_status('pending')||$order->get_transaction_id()!=='')throw new RuntimeException('QA order state');
$settings=(array)get_option('woocommerce_stripe_settings',[]);$guest=new WP_User(0);$cap=wc_customer_has_capability([],['pay_for_order'],[0,0,$order->get_id()],$guest);
while(ob_get_level())ob_end_clean();echo json_encode(['checkout_url'=>$order->get_checkout_payment_url(),'guest_order_permission'=>($cap['pay_for_order']??false)===true,'pending'=>true,'no_payment'=>true])."\n";
