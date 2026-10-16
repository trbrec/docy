<?php
/** Read-only inventory of the new1 public website. No workers or provider calls. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
$root='/home/customer/www/new1.trbrec.com/public_html';
define('SHORTINIT',true); define('DISABLE_WP_CRON',true);
require $root.'/wp-load.php';
global $wpdb;
$wpdb->query('START TRANSACTION READ ONLY');
$option=static function($name)use($wpdb){return @unserialize((string)$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$name)),['allowed_classes'=>false]);};
$raw=static function($name)use($wpdb){return (string)$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$name));};
if(rtrim($raw('home'),'/')!=='https://new1.trbrec.com')exit(2);
$result=['read_only'=>true,'contains_artist_rows'=>false,'at'=>gmdate('c'),'php'=>PHP_VERSION,'site'=>$raw('home'),'stylesheet'=>$raw('stylesheet'),'sources'=>[],'forms'=>[]];
$paths=[];
foreach(glob($root.'/wp-content/mu-plugins/*.php') as $p)if(!is_link($p))$paths['mu-plugins/'.basename($p)]=$p;
$theme=$raw('stylesheet');
if(!preg_match('/^[a-zA-Z0-9_-]+$/D',$theme))exit(3);
foreach(['functions.php','style.css'] as $name){$p=$root.'/wp-content/themes/'.$theme.'/'.$name;if(is_file($p)&&!is_link($p))$paths['theme/'.$name]=$p;}
foreach(['trb-promo','trb-rec-ecosistema-promozionale','trb-promo-autonomy'] as $plugin){
 $dir=$root.'/wp-content/plugins/'.$plugin;if(!is_dir($dir)||is_link($dir))continue;
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS)) as $entry){
  if(!$entry->isFile()||$entry->isLink()||$entry->getSize()>512000)continue;
  $rel=substr($entry->getPathname(),strlen($dir)+1);
  if(!preg_match('/\.(php|js|css)$/D',$rel)||preg_match('~(^|/)(vendor|node_modules)/~',$rel))continue;
  $paths['plugins/'.$plugin.'/'.$rel]=$entry->getPathname();
 }
}
foreach($paths as $name=>$path){
 $content=file_get_contents($path);$redactions=0;
 $content=preg_replace('/-----BEGIN (?:[A-Z ]+ )?PRIVATE KEY-----.*?-----END (?:[A-Z ]+ )?PRIVATE KEY-----/s','[REDACTED PRIVATE KEY]',$content);
 $content=preg_replace_callback('~((?:[\'\"]|\$)?[a-z0-9_]*(?:password|passphrase|secret|access_token|refresh_token|api_key|private_key)[a-z0-9_]*(?:[\'\"])?\s*(?:=>|=)\s*)([\'\"])([^\'\"\r\n]{8,})\2~i',static function($m){return $m[1].$m[2].'[REDACTED INLINE SECRET]'.$m[2];},$content,-1,$redactions);
 $content=preg_replace('~\b(?:sk-(?:proj-)?[a-zA-Z0-9_-]{20,}|AIza[a-zA-Z0-9_-]{20,})\b~','[REDACTED KEY]',$content);
 $result['sources'][$name]=['sha256'=>hash_file('sha256',$path),'inline_redactions'=>$redactions,'content'=>$content];
}
$tables=$wpdb->get_col('SHOW TABLES');
$forms=$wpdb->prefix.'fluentform_forms';$meta=$wpdb->prefix.'fluentform_form_meta';
if(in_array($forms,$tables,true)&&in_array($meta,$tables,true)){
 foreach([5,6,7] as $id){
  $f=$wpdb->get_row($wpdb->prepare("SELECT title,status FROM `$forms` WHERE id=%d",$id),ARRAY_A);
  $f['feeds']=[];
  foreach($wpdb->get_results($wpdb->prepare("SELECT meta_key,value FROM `$meta` WHERE form_id=%d",$id),ARRAY_A) as $row){
   if(!preg_match('/notification|webhook|integration/i',$row['meta_key']))continue;
   $v=json_decode($row['value'],true);if(!is_array($v))continue;
   $f['feeds'][]=['key'=>$row['meta_key'],'enabled'=>$v['enabled']??$v['status']??null,'name'=>$v['name']??null];
  }
  $result['forms'][(string)$id]=$f;
 }
}
$result['demo_option_names']=$wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'trb_demo%' OR option_name LIKE 'trb%pcloud%' ORDER BY option_name");
$result['scheduler']=$option('trb_studio_scheduler');
$result['sync']=$option('trb_studio_last_sync');
$wpdb->query('ROLLBACK');
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
