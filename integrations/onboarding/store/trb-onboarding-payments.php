<?php
if(!defined('ABSPATH'))exit;
/** Use the dedicated HMAC permissions on precisely these Store POST routes. */
function trb_onboarding_store_protocol_authentication($result){
    $path=rtrim((string)parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),'/');
    if(($_SERVER['REQUEST_METHOD']??'')==='POST'&&in_array($path,array('/wp-json/trb/v1/onboarding/order','/wp-json/trb/v1/onboarding/payment-status'),true))return null;
    return $result;
}
add_filter('rest_authentication_errors','trb_onboarding_store_protocol_authentication',PHP_INT_MAX);
/** Purpose-bound WooCommerce checkout for NEW contract installments only. */

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
        if($order->has_status(array('cancelled','refunded'))||($order->has_status('on-hold')&&$order->get_payment_method()!=='bacs'))throw new RuntimeException('Ordine con esito da verificare: richiedi assistenza prima di un nuovo versamento.');
        return array('order_id'=>$order->get_id(),'checkout_url'=>$order->has_status('on-hold')&&$order->get_payment_method()==='bacs'?$order->get_checkout_order_received_url():$order->get_checkout_payment_url(),'paid'=>trb_onboarding_store_capture($order)['captured'],'awaiting_bank_transfer'=>$order->has_status('on-hold')&&$order->get_payment_method()==='bacs','amount_cents'=>$amount,'currency'=>'EUR');
    }catch(Throwable $e){return new WP_Error('onboarding_checkout',$e->getMessage(),array('status'=>409));}
    finally{global $wpdb;$wpdb->delete($wpdb->options,array('option_name'=>$lock,'option_value'=>$lockValue));wp_cache_delete($lock,'options');}
}

function trb_onboarding_store_capture($order){
    $amount=(int)$order->get_meta('_trb_onboarding_amount_cents');$gateway=(string)$order->get_payment_method();$date=$order->get_date_paid();$transaction=(string)$order->get_transaction_id();
    $captured=$date&&$transaction!==''&&(in_array($gateway,trb_onboarding_instant_gateways(),true)||trb_onboarding_bank_receipt_matches($order))&&$order->get_currency()==='EUR'&&(int)round((float)$order->get_total()*100)===$amount;
    return array('captured'=>(bool)$captured,'amount_cents'=>$amount,'date'=>$date,'transaction'=>$transaction);
}

function trb_onboarding_store_payment_status($request){
    $p=$request->get_json_params();$order=wc_get_order((int)($p['order_id']??0));
    if(!$order||!$order->get_meta('_trb_onboarding_practice_id')||!hash_equals((string)$order->get_meta('_trb_onboarding_practice_id'),(string)($p['practice_id']??'')))return new WP_Error('onboarding_order','Ordine non disponibile.',array('status'=>404));
    $amount=(int)$order->get_meta('_trb_onboarding_amount_cents');$refunded=(int)round((float)$order->get_total_refunded()*100);
    // A bank transfer requires its separate, immutable administrative receipt.
    $capture=trb_onboarding_store_capture($order);$date=$capture['date'];$transaction=$capture['transaction'];
    $verified=$order->is_paid()&&$capture['captured'];
    return array('practice_id'=>(string)$order->get_meta('_trb_onboarding_practice_id'),'number'=>(int)$order->get_meta('_trb_onboarding_number'),'amount_cents'=>$amount,'refunded_cents'=>$refunded,'currency'=>$order->get_currency(),'snapshot_sha256'=>(string)$order->get_meta('_trb_onboarding_snapshot_sha256'),'email'=>strtolower($order->get_billing_email()),'provider'=>'woocommerce','transaction_id'=>'order:'.$order->get_id().':'.hash('sha256',$transaction),'paid_on'=>$date?wp_date('Y-m-d',$date->getTimestamp(),new DateTimeZone('Europe/Rome')):null,'captured'=>$capture['captured'],'awaiting_bank_transfer'=>$order->get_payment_method()==='bacs'&&$order->has_status('on-hold')&&!$capture['captured'],'status'=>$refunded>0&&$capture['captured']?'reversed':($verified?'confirmed':($capture['captured']?'review':'pending')));
}

add_action('rest_api_init',static function(){
    register_rest_route('trb/v1','/onboarding/order',array('methods'=>'POST','permission_callback'=>'trb_onboarding_store_permission','callback'=>'trb_onboarding_store_order'));
    register_rest_route('trb/v1','/onboarding/payment-status',array('methods'=>'POST','permission_callback'=>'trb_onboarding_store_permission','callback'=>'trb_onboarding_store_payment_status'));
});
// Clean expired replay protection entries, never order/contract records.
add_action('trb_onboarding_nonce_cleanup',static function(){global $wpdb;$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED)<%d",$wpdb->esc_like('trb_onboarding_nonce_').'%',time()));});
add_action('init',static function(){if(get_option('trb_onboarding_payments_enabled',false)&&!wp_next_scheduled('trb_onboarding_nonce_cleanup'))wp_schedule_event(time()+3600,'daily','trb_onboarding_nonce_cleanup');});
// Offer configured card gateways, and bank transfer only with complete bank details.
add_filter('woocommerce_available_payment_gateways',static function($gateways){
    $id=absint(get_query_var('order-pay'));$order=$id?wc_get_order($id):false;
    if(!$order||!$order->get_meta('_trb_onboarding_practice_id'))return $gateways;
    $allowed=trb_onboarding_instant_gateways();if(trb_onboarding_bank_configured())$allowed[]='bacs';
    $preferred=array_merge(array('ppcp-gateway','stripe','ppcp-credit-card-gateway','ppcp-card-button-gateway','paypal','woocommerce_payments'),array('bacs'));$ordered=array();
    foreach($preferred as $id)if(in_array($id,$allowed,true)&&isset($gateways[$id]))$ordered[$id]=$gateways[$id];return $ordered;
},100);

/** These gateways provide transaction IDs; enabling one remains a Store setting. */
function trb_onboarding_instant_gateways(){return array('ppcp-gateway','ppcp-credit-card-gateway','ppcp-card-button-gateway','stripe','paypal','woocommerce_payments');}
function trb_onboarding_bank_configured(){
    foreach((array)get_option('woocommerce_bacs_accounts',array()) as $account){
        $iban=preg_replace('/\s+/','',strtoupper((string)($account['iban']??'')));
        if(trim((string)($account['account_name']??''))===''||!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/D',$iban))continue;
        $digits='';foreach(str_split(substr($iban,4).substr($iban,0,4)) as $char)$digits.=ctype_alpha($char)?(string)(ord($char)-55):$char;
        $mod=0;foreach(str_split($digits) as $digit)$mod=($mod*10+(int)$digit)%97;if($mod===1)return true;
    }return false;
}
function trb_onboarding_bank_receipt_matches($order){
    $receipt=$order->get_meta('_trb_onboarding_bank_receipt');$date=$order->get_date_paid();
    return $order->get_payment_method()==='bacs'&&is_array($receipt)&&($receipt['order_id']??0)===$order->get_id()
        &&($receipt['amount_cents']??0)===(int)$order->get_meta('_trb_onboarding_amount_cents')
        &&($receipt['reference']??'')!==''&&hash_equals($receipt['reference'],(string)$order->get_transaction_id())
        &&!empty($receipt['confirmed_by'])&&$date&&($receipt['paid_on']??'')===wp_date('Y-m-d',$date->getTimestamp(),new DateTimeZone('Europe/Rome'));
}
/** No customer self-confirmation, payment-status toggle or uploaded receipt unlocks the practice. */
function trb_onboarding_confirm_bank_receipt($order,$reference,$paidOn,$confirmedBy){
    if($confirmedBy&&$order&&trb_onboarding_bank_receipt_matches($order)&&hash_equals((string)$order->get_transaction_id(),trim((string)$reference)))return true;
    if(!$confirmedBy||!$order||!$order->get_meta('_trb_onboarding_practice_id')||$order->get_payment_method()!=='bacs'||!$order->has_status('on-hold'))return new WP_Error('onboarding_bank_state','Ordine bonifico non in attesa di accredito.');
    $reference=trim((string)$reference);$date=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$paidOn,new DateTimeZone('Europe/Rome'));$today=new DateTimeImmutable('today',new DateTimeZone('Europe/Rome'));
    if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9 .:_\/-]{5,79}$/D',$reference)||!$date||$date->format('Y-m-d')!==$paidOn||$date>$today)return new WP_Error('onboarding_bank_proof','Inserisci il riferimento bancario e la data effettiva di accredito.');
    $amount=(int)$order->get_meta('_trb_onboarding_amount_cents');
    if($order->get_currency()!=='EUR'||$amount<1||(int)round((float)$order->get_total()*100)!==$amount||(float)$order->get_total_refunded()>0)return new WP_Error('onboarding_bank_amount','Importo o storico bonifico da verificare.');
    $existing=$order->get_meta('_trb_onboarding_bank_receipt');if($existing&&(!is_array($existing)||($existing['order_id']??0)!==$order->get_id()||($existing['amount_cents']??0)!==$amount||($existing['reference']??'')!==$reference||($existing['paid_on']??'')!==$paidOn||empty($existing['confirmed_by'])))return new WP_Error('onboarding_bank_receipt','Conferma precedente discordante: verifica amministrativa necessaria.');
    $key='trb_onboarding_bank_ref_'.hash('sha256',strtoupper($reference));
    if(!add_option($key,$order->get_id(),'','no')&&(int)get_option($key)!==$order->get_id())return new WP_Error('onboarding_bank_duplicate','Riferimento bancario già associato a un altro ordine.');
    $receipt=$existing?:array('order_id'=>$order->get_id(),'amount_cents'=>$amount,'reference'=>$reference,'paid_on'=>$paidOn,'confirmed_by'=>(int)$confirmedBy,'confirmed_at'=>gmdate('c'));
    $order->update_meta_data('_trb_onboarding_bank_receipt',$receipt);$order->set_date_paid(new WC_DateTime($paidOn.' 12:00:00',new DateTimeZone('Europe/Rome')));$order->save();$order->payment_complete($reference);
    $order->add_order_note('Accredito bonifico verificato per adesione TRB: '.$reference.'; data '.$paidOn.'.');return true;
}
add_action('add_meta_boxes',static function(){
    foreach(array('shop_order','woocommerce_page_wc-orders') as $screen)add_meta_box('trb-onboarding-bank','Accredito bonifico · adesione TRB',static function($object){
        $order=is_a($object,'WC_Order')?$object:wc_get_order($object->ID??0);if(!$order||!$order->get_meta('_trb_onboarding_practice_id')||$order->get_payment_method()!=='bacs')return;
        if(trb_onboarding_bank_receipt_matches($order)){echo '<p>Accredito già verificato e registrato.</p>';return;}
        echo '<p>Verifica sul conto l’accredito dell’intero importo dovuto. Poi seleziona «Conferma accredito bonifico TRB» nelle azioni ordine.</p><p><label>Riferimento bancario (TRN/CRO)<input name="trb_bank_reference" maxlength="80" type="text"></label></p><p><label>Data di accredito<input name="trb_bank_paid_on" type="date"></label></p><p><label><input name="trb_bank_verified" value="1" type="checkbox"> Confermo l’accredito effettivo sul conto, per l’importo completo dell’ordine.</label></p>';
    },$screen,'side');
});
add_filter('woocommerce_order_actions',static function($actions,$order){if($order&&$order->get_meta('_trb_onboarding_practice_id')&&$order->get_payment_method()==='bacs'&&$order->has_status('on-hold'))$actions['trb_confirm_bank']='Conferma accredito bonifico TRB';return $actions;},10,2);
add_action('woocommerce_order_action_trb_confirm_bank',static function($order){
    // WooCommerce also checks its order editor nonce before dispatching an order action.
    if(!current_user_can('manage_woocommerce')||empty($_POST['trb_bank_verified'])||!isset($_POST['woocommerce_meta_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])),'woocommerce_save_data'))return;
    $result=trb_onboarding_confirm_bank_receipt($order,sanitize_text_field(wp_unslash($_POST['trb_bank_reference']??'')),sanitize_text_field(wp_unslash($_POST['trb_bank_paid_on']??'')),get_current_user_id());
    if(is_wp_error($result)){if(class_exists('WC_Admin_Meta_Boxes'))WC_Admin_Meta_Boxes::add_error($result->get_error_message());$order->add_order_note('Accredito non confermato: '.$result->get_error_message());}
});

// Name the actual card alternative clearly before an artist has an account.
add_filter('woocommerce_gateway_title',static function($title,$gateway){
    $id=absint(get_query_var('order-pay'));$order=$id?wc_get_order($id):false;if(!$order||!$order->get_meta('_trb_onboarding_practice_id'))return $title;
    return array('ppcp-gateway'=>'PayPal (consigliato)','stripe'=>'Carta di credito o debito · senza conto PayPal','ppcp-credit-card-gateway'=>'Carta di credito o debito · tramite PayPal','ppcp-card-button-gateway'=>'Carta di credito o debito · tramite PayPal','bacs'=>'Bonifico bancario')[$gateway]??$title;
},100,2);

// Stripe UPE rebuilds its label in JavaScript from its localized title.
add_filter('wc_stripe_upe_params',static function($params){
    $id=absint(get_query_var('order-pay'));$order=$id?wc_get_order($id):false;
    if(!is_array($params)||!$order||!$order->get_meta('_trb_onboarding_practice_id'))return $params;
    $params['title']='Carta di credito o debito · senza conto PayPal';
    $params['optimizedCheckoutClassicTitle']=$params['title'];
    if(isset($params['paymentMethodsConfig']['card']))$params['paymentMethodsConfig']['card']['title']=$params['title'];
    return $params;
},100);

// Keep the accessible label explicit on older optimized-checkout builds too.
add_action('wp_footer',static function(){
    $id=absint(get_query_var('order-pay'));$order=$id?wc_get_order($id):false;
    if(!$order||!$order->get_meta('_trb_onboarding_practice_id'))return;
    echo '<script>(function(){var title="Carta di credito o debito · senza conto PayPal";function label(){var e=document.querySelector("label[for=payment_method_stripe]");if(e&&e.textContent.trim()!==title)e.textContent=title;}label();if(window.MutationObserver){new MutationObserver(label).observe(document.body,{childList:true,subtree:true,characterData:true});}})();</script>';
},100);
