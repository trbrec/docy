<?php
define('ABSPATH', __DIR__);
$meta=[41=>'Edmondo Romano'];$caps=true;$targetCap=true;$owner=0;$exists=true;$nonce=true;$actions=[];
class WP_Error { public function __construct(public $code,public $message,public $data=[]) {} public function get_error_data(){return $this->data;} public function get_error_message(){return $this->message;} }
function absint($v){return abs((int)$v);} function current_user_can($cap,...$args){return $cap==='edit_user'?$GLOBALS['targetCap']:$GLOBALS['caps'];}
function get_userdata($id){return $GLOBALS['exists']?(object)['ID'=>$id,'display_name'=>'Edmondo Romano']:false;}
function trb_portal_user_profile($u){return 'trb';} function get_user_meta($id,$key,$single){return $GLOBALS['meta'][$id]??'';}
function update_user_meta($id,$key,$value){$GLOBALS['meta'][$id]=$value;} function sanitize_text_field($s){return strip_tags($s);}
function trb_portal_artist_name_owner($name,$id){return $GLOBALS['owner'];} function do_action(...$args){}
function add_filter(...$args){} function add_action($hook,$callback,...$args){$GLOBALS['actions'][$hook]=$callback;}
function check_ajax_referer(...$args){return $GLOBALS['nonce'];} function wp_unslash($s){return $s;}
function is_wp_error($v){return $v instanceof WP_Error;} function wp_send_json_error($d,$status){throw new RuntimeException((string)$status);}
function wp_send_json_success($d){throw new RuntimeException('200');}
require dirname(__DIR__).'/inc/trb-artist-admin-name.php';
$checks=0;function check($v){if(!$v)throw new RuntimeException('Regression failed');++$GLOBALS['checks'];}
$caps=false;check(trb_artist_admin_save_name(41,'Duo','Edmondo Romano')->code==='forbidden');check($meta[41]==='Edmondo Romano');
$caps=true;$targetCap=false;check(trb_artist_admin_save_name(41,'Duo','Edmondo Romano')->code==='forbidden');$targetCap=true;
check(trb_artist_admin_save_name(41,'Duo','Stale')->code==='conflict');
check(trb_artist_admin_save_name(41,'  ','Edmondo Romano')->code==='invalid_name');
$owner=90;check(trb_artist_admin_save_name(41,'Duo','Edmondo Romano')->code==='name_taken');$owner=0;
$exists=false;check(trb_artist_admin_save_name(41,'Duo','Edmondo Romano')->code==='not_artist');$exists=true;
$nonce=false;$_POST=['user_id'=>41,'name'=>'Changed','expected'=>'Edmondo Romano'];
try{$actions['wp_ajax_trb_artist_admin_name']();check(false);}catch(RuntimeException $e){check($e->getMessage()==='403');}
check($meta[41]==='Edmondo Romano');
$result=trb_artist_admin_save_name(41,'Edmondo Romano e Simona Fasano','Edmondo Romano');
check($result['name']==='Edmondo Romano e Simona Fasano');check($meta[41]===$result['name']);
echo "Artist stage-name checks passed: $checks\n";
