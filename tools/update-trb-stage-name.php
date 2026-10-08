<?php
/** Apply the explicitly requested stage name, without overwriting newer changes. */
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
ini_set('display_errors','0');
$revision=$argv[1]??'';
if (!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision) exit(2);
define('WP_USE_THEMES',false);require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
$u=get_userdata(41);$wanted='Edmondo Romano e Simona Fasano';
if(!$u||!\TRB\Studio\portal_artist_allowed($u)||$u->display_name!=='Edmondo Romano')exit(3);
$old=trim((string)get_user_meta(41,'_trb_artist_artist_name',true));
if($old===$wanted)exit;
if(!in_array($old,['','Edmondo Romano'],true)||trb_portal_artist_name_owner($wanted,41))exit(4);
update_user_meta(41,'_trb_artist_artist_name',$wanted);
if(get_user_meta(41,'_trb_artist_artist_name',true)!==$wanted)exit(5);
exit;
