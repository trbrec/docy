<?php
// Contract and HMAC regressions; no network, no real customer records.
define('ABSPATH', __DIR__);
function add_action(...$x){} function add_filter(...$x){}
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function add_option($k,$v,...$x){if(isset($GLOBALS['options'][$k]))return false;$GLOBALS['options'][$k]=$v;return true;}
function wp_next_scheduled(...$x){return true;}
function trb_portal_dds_store_secret(){return $GLOBALS['secret'];}
function trb_portal_user_profile($u){return $u->profile;}
function trb_release_bridge_is_contract_access_expired($id){return $GLOBALS['expired'];}
function pw_new_user_approve(){return new class{function get_user_status($id){return $GLOBALS['approval'];}};}
class WP_Error{function __construct(...$x){}}
function check($ok,$label){if(!$ok)throw new RuntimeException($label);$GLOBALS['checks']++;}
require __DIR__.'/../inc/trb-store-benefits.php';
$checks=0;$secret=str_repeat('s',40);$expired=false;$approval='approved';
$user=new class{public $ID=42,$profile='ddb';function exists(){return true;}};
foreach(['dds','ddb12','ddb','ddb_trb'] as $p){$user->profile=$p;check(trb_store_benefits_eligible($user),$p.' eligible');}
foreach(['trb','subscriber','administrator',false] as $p){$user->profile=$p;check(!trb_store_benefits_eligible($user),'no profile bypass');}
$user->profile='ddb';$expired=true;check(!trb_store_benefits_eligible($user),'expired excluded');$expired=false;
foreach(['pending','denied'] as $approval)check(!trb_store_benefits_eligible($user),'unapproved excluded');$approval='approved';
check(!trb_store_benefits_live(),'no premature announcement');
$request=new class{public $headers=[],$body='{"email":"artist@example.test"}';function get_header($k){return $this->headers[$k]??'';}function get_body(){return $this->body;}};
function sign_request($r,$time=null,$purpose='artist-benefits-v1'){$ts=(string)($time??time());$nonce=bin2hex(random_bytes(16));$r->headers=['x-trb-timestamp'=>$ts,'x-trb-nonce'=>$nonce,'x-trb-signature'=>hash_hmac('sha256',$purpose.'.'.$ts.'.'.$nonce.'.'.$r->body,$GLOBALS['secret'])];}
sign_request($request);check(true===trb_store_benefits_permission($request),'valid signed request');check(trb_store_benefits_permission($request) instanceof WP_Error,'replay rejected');
sign_request($request,time()-121);check(trb_store_benefits_permission($request) instanceof WP_Error,'expired signature');
sign_request($request,time()+121);check(trb_store_benefits_permission($request) instanceof WP_Error,'future signature');
sign_request($request,null,'dds-activation');check(trb_store_benefits_permission($request) instanceof WP_Error,'cross purpose rejected');
sign_request($request);$request->body='{"email":"another@example.test"}';check(trb_store_benefits_permission($request) instanceof WP_Error,'tampered body rejected');
$secret='';check(trb_store_benefits_permission($request) instanceof WP_Error,'missing key fail closed');
echo "PASS $checks portal benefit checks\n";
