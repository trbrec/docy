<?php
// Real PCM fixtures; email queue is intercepted and never sends.
define('ABSPATH',__DIR__);define('DAY_IN_SECONDS',86400);
class WP_Error {function __construct(public $code, public $message='', public $data=null){} function get_error_code(){return $this->code;}}
function is_wp_error($x){return $x instanceof WP_Error;}
function get_transient($k){return $GLOBALS['cache'][$k]??false;} function set_transient($k,$v,$ttl){$GLOBALS['cache'][$k]=$v;}
function trb_analysis_binary($x){return '/usr/bin/ffmpeg';}
function trb_analysis_exec($cmd){exec($cmd.' 2>&1',$lines,$code);return ['code'=>$code,'output'=>implode("\n",$lines)];}
function trb_portal_wav_spec($p){return ['sample_rate'=>44100,'channels'=>2];}
function wp_get_current_user(){return new WP_User();}
function is_email($e){return filter_var($e,FILTER_VALIDATE_EMAIL);}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES);}
function trb_resource_queue_recipient_email($key,$to,$subject,$body,$priority,$headers){$GLOBALS['mail'][$key]=compact('to','subject','body','headers');}
function trb_portal_user_profile($u){return $GLOBALS['profile']??'ddb';}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
$portalSource=file_get_contents(dirname(__DIR__).'/inc/trb-artist-portal.php');
foreach(['trb_portal_service_catalogue','trb_portal_service_status','trb_portal_profile_has_service'] as $fn){
 $start=strpos($portalSource,'function '.$fn.'(');$end=strpos($portalSource,"\n}\n",$start)+3;eval(substr($portalSource,$start,$end-$start));
}
class WP_User {public $ID=71;public $first_name='Artista';public $last_name='Rossi';public $user_email='artist@example.test';}
function trb_portal_artist_profile_value($field,$id){return 'Maria Luisa Rossi';}
$resourceSource=file_get_contents(dirname(__DIR__).'/inc/trb-resource-monitor.php');
$start=strpos($resourceSource,'function trb_resource_artist_legal_greeting_name(');$end=strpos($resourceSource,"\n}\n",$start)+3;eval(substr($resourceSource,$start,$end-$start));
require dirname(__DIR__).'/inc/trb-master-acceptance.php';
function verify_master($ok,$s){if(!$ok)throw new RuntimeException($s);}
$peak=['verified'=>true,'complete_file'=>true,'maximum_dbtp'=>-.1];
verify_master(trb_master_acceptance_errors($peak,['verified'=>true,'full_scale_samples'=>1])===['MASTER_SAMPLE_PEAK_AT_ZERO'],'Full-scale sample passed with subzero true peak');
verify_master(in_array('PCM_MEASUREMENT_UNAVAILABLE',trb_master_acceptance_errors($peak,[]),true),'Unverified PCM passed');
$path=tempnam(sys_get_temp_dir(),'master-qa');
try {
 foreach([.5,1,.5] as $amplitude){
  $pcm='';for($i=0;$i<44100;$i++){ $value=(int)round(32767*$amplitude*sin(2*M_PI*$i/4));$pcm.=pack('vv',$value&65535,$value&65535); }
  file_put_contents($path,'RIFF'.pack('V',36+strlen($pcm)).'WAVEfmt '.pack('VvvVVvv',16,1,2,44100,176400,4,16).'data'.pack('V',strlen($pcm)).$pcm);
  $hash=hash_file('sha256',$path);$result=trb_master_upload_check($path,'master.wav');
  verify_master($amplitude<1 ? $result===true : is_wp_error($result),'Acceptance boundary wrong');
  verify_master($hash===hash_file('sha256',$path),'Source modified');
  if($amplitude===1){
   trb_master_upload_check($path,'master.wav');verify_master(count($GLOBALS['mail'])===1,'Repeated file queued duplicate notifications');
   $mail=array_values($GLOBALS['mail'])[0];verify_master(str_starts_with($mail['body'],'<p>Gentile Artista,</p>'),'Greeting incorrect');verify_master(str_contains($mail['body'],'vero pre-master'),'Missing included mastering route');$GLOBALS['profile']='dds';verify_master(trb_master_premaster_email_guidance(wp_get_current_user())==='','DDS offered included mastering');$GLOBALS['profile']='ddb';verify_master($mail['to']==='artist@example.test' && $mail['headers']===['Cc: andrea.tognassi@trbrec.com'],'Wrong artist/CC');
   verify_master(str_contains($mail['body'],'dBFS')&&str_contains($mail['body'],'dBTP'),'Missing measurement units');
   verify_master(trb_master_upload_check($path,'premaster.wav','mastering')===true,'Master-only rule unexpectedly blocks premaster');
  }
 }
} finally {unlink($path);}
echo "PASS master acceptance: real subzero/rail WAV, independent PCM gate, original integrity, artist+CC, queue deduplication and premaster scope\n";

foreach(['dds'=>false,'ddb12'=>true,'ddb'=>true,'ddb_trb'=>true,'trb'=>true,'unknown'=>false] as $profile=>$included){
 $GLOBALS['profile']=$profile;
 $text=trb_master_premaster_email_guidance(wp_get_current_user());
 verify_master(($text!=='')===$included,'Incorrect mastering guidance for '.$profile);
 if($included) verify_master(str_contains($text,'Invio un pre-master e richiedo il mastering del brano')&&str_contains($text,'Sostituisci il WAV')&&str_contains($text,'44.100 Hz / 16 bit'),'Incomplete next steps for '.$profile);
}
$user=new WP_User();$user->first_name='Maria Luisa';verify_master(trb_resource_artist_legal_greeting_name($user)==='Maria Luisa','Compound given name altered');
$user->first_name='';verify_master(trb_resource_artist_legal_greeting_name($user)==='Maria Luisa','Surname retained in fallback greeting');
echo "PASS real five-profile service catalogue, unknown-profile exclusion and first-name greetings\n";
