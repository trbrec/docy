<?php
/** Explicit CLI-only integration QA. Creates and deletes only its own marked fixtures. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$mode=$argv[1] ?? ''; $run=$argv[2] ?? '';
if (!preg_match('/^[a-f0-9]{16}$/D',$run) || !in_array($mode,array('portal','store','cleanup'),true)) exit(2);
$site=$mode==='portal' ? 'artist' : ($argv[3] ?? 'store');
if (!in_array($site,array('artist','store'),true)) exit(2);
$_SERVER['HTTP_HOST']=$site.'.trbrec.com'; $_SERVER['SERVER_NAME']=$_SERVER['HTTP_HOST']; $_SERVER['REQUEST_URI']='/'; $_SERVER['HTTPS']='on';
define('DISABLE_WP_CRON',true);
ob_start(); require '/home/customer/www/'.$site.'.trbrec.com/public_html/wp-load.php'; ob_end_clean();
// Isolate fixture lifecycle from notifications and CRM/background synchronization.
foreach(array('user_register','profile_update','set_user_role','add_user_role','remove_user_role','delete_user','deleted_user','added_user_meta','updated_user_meta','deleted_user_meta') as $hook) remove_all_actions($hook);
$mail=array(); add_filter('pre_wp_mail',function($pre,$args)use(&$mail){$mail[]=$args;return true;},PHP_INT_MAX,2);
$login='trb_benefit_qa_'.$run; $email=$login.'@example.invalid';
function qa_check($ok,$label) { if(!$ok) throw new RuntimeException($label); echo 'PASS '.$label."\n"; }
function qa_user($login,$email,$create=false) {
 $u=get_user_by('login',$login);
 if($u && ($u->user_email!==$email || get_user_meta($u->ID,'_trb_benefit_qa',true)!==$login)) throw new RuntimeException('Fixture identity collision');
 if(!$u && $create) { $id=wp_insert_user(array('user_login'=>$login,'user_pass'=>wp_generate_password(48,true,true),'user_email'=>$email,'display_name'=>'QA condizioni artista','role'=>'subscriber')); if(is_wp_error($id))throw new RuntimeException($id->get_error_code()); update_user_meta($id,'_trb_benefit_qa',$login); $u=get_userdata($id); }
 return $u;
}
function qa_portal_state($run,$profile) {
 $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' portal '.escapeshellarg($run).' '.escapeshellarg($profile);
 exec($command,$lines,$status); if($status!==0)throw new RuntimeException('Portal fixture update failed: '.implode(' ',$lines));
}
try {
 if($mode==='cleanup') {
  $u=qa_user($login,$email); if($u) { require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($u->ID); global $wpdb; if($site==='store') $wpdb->delete($wpdb->prefix.'woocommerce_sessions',array('session_key'=>(string)$u->ID)); }
  qa_check(!get_user_by('login',$login),'cleanup '.$site); exit;
 }
 if($mode==='portal') {
  $profile=$argv[3] ?? 'ddb'; $u=qa_user($login,$email,true); $profiles=trb_portal_profiles();
  $u->set_role($profiles[$profile]['role'] ?? $profiles['ddb']['role']);
  update_user_meta($u->ID,'pw_user_status',$profile==='pending'?'pending':'approved');
  update_user_meta($u->ID,'_trb_artist_contract_term',$profile==='expired'?'01/01/2020 - 01/01/2021':'01/01/2026 - 01/01/2099');
  if($profile==='none')$u->set_role('subscriber');
  if($profile==='ddb') { ob_start();trb_store_benefits_panel(get_userdata($u->ID));$panel=ob_get_clean();qa_check(strpos($panel,'50%')!==false && strpos($panel,$email)!==false,'portal panel same email and 50%'); }
  exit;
 }
 qa_check(function_exists('WC') && trb_artist_benefits_enabled(),'Store live integration enabled');
 $u=qa_user($login,$email,true);$u->set_role('customer');wp_set_current_user($u->ID); $u=wp_get_current_user();
 WC()->initialize_session(); WC()->customer=new WC_Customer($u->ID);WC()->cart=new WC_Cart();
 $first=trb_artist_portal_request($email);qa_check(!is_wp_error($first)&&$first['eligible']&&$first['live'],'real signed HTTP lookup');
 qa_check(!trb_artist_has_discount(),'unverified email receives no discount');
 $sent=trb_artist_send_verification($u);qa_check($sent===true && count($mail)>0,'verification message generated without delivery');
 $message=end($mail);preg_match('/trb_artist_verify=([a-f0-9]{64})/',$message['message'],$m);$token=$m[1]??'';$record=get_user_meta($u->ID,'_trb_artist_verify_token',true);
 qa_check(trb_artist_token_valid($u,$token,$record),'issued verification token validates');
 qa_check(!trb_artist_token_valid($u,str_repeat('0',64),$record),'invalid token rejected');
 $expired=$record;$expired['expires']=time()-1;qa_check(!trb_artist_token_valid($u,$token,$expired),'expired token rejected');
 update_user_meta($u->ID,'_trb_verified_artist_email',trb_artist_email_hash($u));delete_user_meta($u->ID,'_trb_artist_verify_token');
 qa_check(!trb_artist_token_valid($u,$token,get_user_meta($u->ID,'_trb_artist_verify_token',true)),'consumed token cannot replay');
 $products=wc_get_products(array('status'=>'publish','type'=>'simple','limit'=>-1));$normal=null;$sale=null;
 foreach($products as $p) {if(!$p->is_purchasable()||!$p->is_in_stock()||$p->get_price()<=0)continue;if($p->is_on_sale())$sale=$sale?:$p;else $normal=$normal?:$p;}
 qa_check($normal&&$sale,'real full-price and sale-price services available');
 foreach(array('dds','ddb12','ddb','ddb_trb','trb','none','pending','expired') as $profile) {
  qa_portal_state($run,$profile);unset($GLOBALS['trb_artist_entitlements']);WC()->cart->empty_cart();
  $data=trb_artist_entitlement(true);qa_check(!is_wp_error($data),'lookup '.$profile);
  $eligible=in_array($profile,array('dds','ddb12','ddb','ddb_trb'),true);qa_check((bool)$data['eligible']===$eligible,'entitlement '.$profile);
  WC()->cart->add_to_cart($normal->get_id(),2);WC()->cart->add_to_cart($sale->get_id(),1);WC()->cart->calculate_totals();
  $sub=(float)WC()->cart->get_subtotal();$discount=(float)WC()->cart->get_discount_total();
  qa_check($sub>0 && abs($discount-($eligible?$sub*.5:0))<.02,'WooCommerce totals '.$profile.' subtotal='.$sub.' discount='.$discount);
  if($profile==='trb')qa_check($data['included']===true && (float)WC()->cart->get_total('edit')>0,'TRB guidance without free checkout');
  if($eligible) {qa_check(trb_artist_checkout_error()==='','fresh checkout validation '.$profile);$old=new WC_Coupon();$old->set_code('promo50artistirostertrb');qa_check(apply_filters('woocommerce_coupon_is_valid',true,$old)===false,'retired coupon rejected '.$profile);}
 }
 qa_portal_state($run,'ddb');unset($GLOBALS['trb_artist_entitlements']);WC()->session->set('trb_artist_discount_seen',false);WC()->cart->calculate_totals();qa_check(WC()->cart->has_discount(TRB_ARTIST_COUPON),'discount restored for active artist');
 qa_portal_state($run,'pending');qa_check(trb_artist_checkout_error()!=='' && !WC()->cart->has_discount(TRB_ARTIST_COUPON),'revocation blocks checkout and removes stale discount');
 $order=new WC_Order();$order->set_customer_id($u->ID);$order->update_meta_data('_trb_artist_benefit',array('percent'=>50));$rejected=false;try{trb_artist_existing_order_payment($order);}catch(Exception $e){$rejected=true;}qa_check($rejected,'pending order payment blocked after revocation; no order saved');
 $old_email=$u->user_email;$u->user_email='changed@example.invalid';qa_check(!trb_artist_email_verified($u),'changed email invalidates verification');$u->user_email=$old_email;
 echo "LIVE QA COMPLETE; no orders or payments; all fixture mail intercepted\n";
} catch(Throwable $e) {fwrite(STDERR,'FAIL '.$e->getMessage()."\n");exit(1);}
