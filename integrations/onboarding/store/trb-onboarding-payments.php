<?php
/** Purpose-bound WooCommerce checkout for NEW contract installments only. */
if(!defined('ABSPATH'))exit;

function trb_onboarding_store_permission($request){
    $secret=function_exists('trb_store_dds_bridge_secret')?trb_store_dds_bridge_secret():'';
    $time=(string)$request->get_header('x-trb-timestamp');$nonce=(string)$request->get_header('x-trb-nonce');
    if(strlen($secret)<32||!ctype_digit($time)||abs(time()-(int)$time)>300||!preg_match('/^[a-f0-9]{32}$/D',$nonce))return new WP_Error('onboarding_auth','Richiesta non autorizzata.',array('status'=>403));
    $expected='sha256='.hash_hmac('sha256','onboarding-v1|'.$time.'|'.$nonce.'|'.$request->get_body(),$secret);
    if(!hash_equals($expected,(string)$request->get_header('x-trb-signature')))return new WP_Error('onboarding_auth','Firma non valida.',array('status'=>403));
    if(!add_option('trb_onboarding_nonce_'.$nonce,time()+600,'','no'))return new WP_Error('onboarding_replay','Richiesta già ricevuta.',array('status'=>409));
    return true;
}

function trb_onboarding_store_order($request){
    if(!get_option('trb_onboarding_payments_enabled',false)||!function_exists('wc_create_order'))return new WP_Error('onboarding_disabled','Adesioni non ancora abilitate.',array('status'=>503));
    $p=$request->get_json_params();$id=(string)($p['practice_id']??'');$number=(int)($p['number']??0);$amount=$p['amount_cents']??null;$total=$p['installment_total_cents']??null;$email=sanitize_email($p['email']??'');
    if(!preg_match('/^[a-f0-9]{32}$/D',$id)||$number<1||!is_int($amount)||$amount<1||$amount>100000000||!is_int($total)||$total<$amount||$total>100000000||($p['currency']??'')!=='EUR'||!is_email($email)||!preg_match('/^[a-f0-9]{64}$/D',(string)($p['snapshot_sha256']??'')))return new WP_Error('onboarding_terms','Dati rata non validi.',array('status'=>422));
    $key='trb_onboarding_order_'.$id.'_'.$number;
    $lock=$key.'_lock';$lockValue=wp_json_encode(array('token'=>bin2hex(random_bytes(16)),'expires'=>time()+600));
    if(!add_option($lock,$lockValue,'','no')){
        $old=get_option($lock);$decoded=json_decode((string)$old,true);
        if(is_array($decoded)&&($decoded['expires']??PHP_INT_MAX)<time()){
            global $wpdb;$wpdb->delete($wpdb->options,array('option_name'=>$lock,'option_value'=>$old));wp_cache_delete($lock,'options');
        }
        if(!add_option($lock,$lockValue,'','no'))return new WP_Error('onboarding_busy','Pagamento in preparazione. Riprova tra poco.',array('status'=>409));
    }
    try{
        $ids=array_filter(array_map('intval',(array)get_option($key,array())));$order=false;$confirmed=0;
        foreach($ids as $orderId){
            $past=wc_get_order($orderId);if(!$past)throw new RuntimeException('Storico versamenti da verificare.');
            if((int)$past->get_meta('_trb_onboarding_installment_total_cents')!==$total||(string)$past->get_meta('_trb_onboarding_snapshot_sha256')!==$p['snapshot_sha256']||strtolower($past->get_billing_email())!==strtolower($email))throw new RuntimeException('Ordine non coerente con la proposta.');
            $capture=trb_onboarding_store_capture($past);$refund=(int)round((float)$past->get_total_refunded()*100);
            if($capture['captured'])$confirmed+=max(0,$capture['amount_cents']-$refund);
            elseif($past->has_status(array('pending','failed','on-hold'))){
                if($order)throw new RuntimeException('Più tentativi pendenti: verifica amministrativa necessaria.');
                $order=$past;
            }elseif($past->has_status(array('cancelled','refunded'))&&$past->get_transaction_id()!=='')throw new RuntimeException('Versamento con esito da verificare.');
        }
        if($confirmed===$total&&!$order){$last=wc_get_order(end($ids));return array('order_id'=>$last->get_id(),'checkout_url'=>'','paid'=>true,'amount_cents'=>$amount,'currency'=>'EUR');}
        if($amount!==$total-$confirmed)throw new RuntimeException('Saldo aggiornato: verifica nuovamente la rata prima di pagare.');
        if($order&&(int)$order->get_meta('_trb_onboarding_amount_cents')!==$amount)throw new RuntimeException('Tentativo pendente con importo diverso: richiedi assistenza.');
        if(!$order){
            $billing=(array)($p['billing']??array());
            foreach(array('first_name','last_name','address_1','city','postcode','country') as $field)if(empty($billing[$field]))throw new RuntimeException('Completa i dati amministrativi prima del pagamento.');
            if(empty($p['tax_code']))throw new RuntimeException('Codice fiscale mancante.');
            $billing=array_intersect_key($billing,array_flip(array('first_name','last_name','address_1','address_2','city','postcode','country','state','phone')));
            if(!array_key_exists(strtoupper($billing['country']),WC()->countries->get_countries()))throw new RuntimeException('Paese amministrativo non valido.');
            $order=wc_create_order(array('created_via'=>'trb-contract-onboarding'));
            if(is_wp_error($order))throw new RuntimeException('Ordine non disponibile.');
            $order->set_currency('EUR');$billing['email']=$email;$order->set_address(array_map('sanitize_text_field',$billing),'billing');
            $fee=new WC_Order_Item_Fee();$fee->set_name('Quota contrattuale – rata '.$number);
            $gross=$amount/100;$taxes=wc_tax_enabled()?WC_Tax::find_rates(array('country'=>$billing['country'],'state'=>$billing['state']??'','postcode'=>$billing['postcode'],'city'=>$billing['city'],'tax_class'=>'')):array();
            // Contract amounts include VAT. Use actual Store tax setup, never double-charge VAT.
            $inclusive=$taxes?array_sum(WC_Tax::calc_inclusive_tax($gross,$taxes)):0;
            $fee->set_amount($gross-$inclusive);$fee->set_total($gross-$inclusive);$fee->set_tax_status($taxes?'taxable':'none');$order->add_item($fee);
            $order->update_meta_data('_trb_onboarding_practice_id',$id);$order->update_meta_data('_trb_onboarding_number',$number);$order->update_meta_data('_trb_onboarding_amount_cents',$amount);$order->update_meta_data('_trb_onboarding_installment_total_cents',$total);$order->update_meta_data('_trb_onboarding_snapshot_sha256',$p['snapshot_sha256']);$order->update_meta_data('_billing_fiscal_code',sanitize_text_field($p['tax_code']));
            foreach(array('vat_number','sdi_code','pec') as $field)if(isset($p['invoice'][$field]))$order->update_meta_data('_billing_'.$field,sanitize_text_field($p['invoice'][$field]));
            $order->calculate_totals(true);
            if((int)round((float)$order->get_total()*100)!==$amount){$order->update_status('cancelled','Importo fiscale non coerente: nessun addebito richiesto.');throw new RuntimeException('Calcolo fiscale da verificare prima del pagamento.');}
            $order->save();$ids[]=$order->get_id();update_option($key,$ids,false);
        }
        if($order->has_status(array('cancelled','refunded','on-hold')))throw new RuntimeException('Ordine con esito da verificare: richiedi assistenza prima di un nuovo versamento.');
        return array('order_id'=>$order->get_id(),'checkout_url'=>$order->get_checkout_payment_url(),'paid'=>$order->is_paid(),'amount_cents'=>$amount,'currency'=>'EUR');
    }catch(Throwable $e){return new WP_Error('onboarding_checkout',$e->getMessage(),array('status'=>409));}
    finally{global $wpdb;$wpdb->delete($wpdb->options,array('option_name'=>$lock,'option_value'=>$lockValue));wp_cache_delete($lock,'options');}
}

function trb_onboarding_store_capture($order){
    $amount=(int)$order->get_meta('_trb_onboarding_amount_cents');$gateway=(string)$order->get_payment_method();$date=$order->get_date_paid();$transaction=(string)$order->get_transaction_id();
    $captured=$date&&$transaction!==''&&in_array($gateway,array('ppcp-gateway','stripe','paypal'),true)&&$order->get_currency()==='EUR'&&(int)round((float)$order->get_total()*100)===$amount;
    return array('captured'=>(bool)$captured,'amount_cents'=>$amount,'date'=>$date,'transaction'=>$transaction);
}

function trb_onboarding_store_payment_status($request){
    $p=$request->get_json_params();$order=wc_get_order((int)($p['order_id']??0));
    if(!$order||!$order->get_meta('_trb_onboarding_practice_id')||!hash_equals((string)$order->get_meta('_trb_onboarding_practice_id'),(string)($p['practice_id']??'')))return new WP_Error('onboarding_order','Ordine non disponibile.',array('status'=>404));
    $amount=(int)$order->get_meta('_trb_onboarding_amount_cents');$refunded=(int)round((float)$order->get_total_refunded()*100);
    // Manual bank-transfer order-status changes aren't provider capture evidence.
    $capture=trb_onboarding_store_capture($order);$date=$capture['date'];$transaction=$capture['transaction'];
    $verified=$order->is_paid()&&$capture['captured'];
    return array('practice_id'=>(string)$order->get_meta('_trb_onboarding_practice_id'),'number'=>(int)$order->get_meta('_trb_onboarding_number'),'amount_cents'=>$amount,'refunded_cents'=>$refunded,'currency'=>$order->get_currency(),'snapshot_sha256'=>(string)$order->get_meta('_trb_onboarding_snapshot_sha256'),'email'=>strtolower($order->get_billing_email()),'provider'=>'woocommerce','transaction_id'=>'order:'.$order->get_id().':'.hash('sha256',$transaction),'paid_on'=>$date?wp_date('Y-m-d',$date->getTimestamp(),new DateTimeZone('Europe/Rome')):null,'captured'=>$capture['captured'],'status'=>$refunded>0&&$capture['captured']?'reversed':($verified?'confirmed':($capture['captured']?'review':'pending')));
}

add_action('rest_api_init',static function(){
    register_rest_route('trb/v1','/onboarding/order',array('methods'=>'POST','permission_callback'=>'trb_onboarding_store_permission','callback'=>'trb_onboarding_store_order'));
    register_rest_route('trb/v1','/onboarding/payment-status',array('methods'=>'POST','permission_callback'=>'trb_onboarding_store_permission','callback'=>'trb_onboarding_store_payment_status'));
});
// Clean expired replay protection entries, never order/contract records.
add_action('trb_onboarding_nonce_cleanup',static function(){global $wpdb;$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED)<%d",$wpdb->esc_like('trb_onboarding_nonce_').'%',time()));});
add_action('init',static function(){if(get_option('trb_onboarding_payments_enabled',false)&&!wp_next_scheduled('trb_onboarding_nonce_cleanup'))wp_schedule_event(time()+3600,'daily','trb_onboarding_nonce_cleanup');});
// Only configured instant-payment gateways are offered for the dedicated order.
add_filter('woocommerce_available_payment_gateways',static function($gateways){
    $id=absint(get_query_var('order-pay'));$order=$id?wc_get_order($id):false;
    if(!$order||!$order->get_meta('_trb_onboarding_practice_id'))return $gateways;
    return array_intersect_key($gateways,array_flip(array('ppcp-gateway','stripe','paypal')));
},100);
