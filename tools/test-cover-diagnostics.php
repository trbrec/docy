<?php
// Real PNGs, no GD dependency. Exercise production artwork validation and error formatting.
define('MB_IN_BYTES',1048576);
class WP_Error {function __construct(public $code,public $message='',public $data=null){}function get_error_code(){return $this->code;}function get_error_message(){return $this->message;}function get_error_data(){return $this->data;}}
function is_wp_error($e){return $e instanceof WP_Error;}function sanitize_file_name($s){return $s;}
$source=file_get_contents(__DIR__.'/../inc/trb-artist-portal.php');
foreach (array('trb_portal_cover_upload_check','trb_portal_release_upload_error_message') as $fn) {$start=strpos($source,'function '.$fn.'(');$end=strpos($source,"\n}\n",$start)+3;eval(substr($source,$start,$end-$start));}
function check($v,$message){if(!$v)throw new RuntimeException($message);}
function png_chunk($type,$data){return pack('N',strlen($data)).$type.$data.pack('N',crc32($type.$data));}
$path=tempnam(sys_get_temp_dir(),'trb-cover-');
try {
 foreach ([[1024,1024,false,'1024×1024'],[1536,1024,false,'non è quadrata'],[1253,1253,false,'1253×1253'],[1254,1254,true,''],[1500,1500,true,''],[3000,3000,true,'']] as [$w,$h,$accepted,$message]) {
  $png="\x89PNG\r\n\x1a\n".png_chunk('IHDR',pack('NNCCCCC',$w,$h,8,2,0,0,0)).png_chunk('IDAT',gzcompress(str_repeat("\0".str_repeat("\0",$w*3),$h))).png_chunk('IEND','');
  file_put_contents($path,$png);clearstatcache(true,$path);
  $result=trb_portal_cover_upload_check(['name'=>'Cover.png','tmp_name'=>$path,'size'=>strlen($png)]);
  check($accepted?$result===true:is_wp_error($result),'Wrong dimension decision');
  if(!$accepted)check(str_contains(trb_portal_release_upload_error_message($result),$message),'Measured dimensions lost in response');
 }
 $error=trb_portal_cover_upload_check(['name'=>'Cover.pdf','tmp_name'=>$path,'size'=>100]);check(str_contains($error->get_error_message(),'JPG o PNG'),'Unsupported format not explained');
 $error=trb_portal_cover_upload_check(['name'=>'Cover.png','tmp_name'=>$path,'size'=>21*MB_IN_BYTES]);check(str_contains($error->get_error_message(),'20 MB'),'Size limit not explained');
 file_put_contents($path,'not an image');$error=trb_portal_cover_upload_check(['name'=>'Cover.png','tmp_name'=>$path,'size'=>12]);check(str_contains($error->get_error_message(),'leggibile'),'Unreadable image not explained');
 $message=trb_portal_release_upload_error_message(new WP_Error('cover_confirmation_missing'));check(str_contains($message,'casella')&&str_contains($message,'Non occorre sostituire'),'Confirmation confused with rejected artwork');
 check(substr_count($source,"new WP_Error( 'cover_confirmation_missing' )")===3,'A submission/replacement/final cover confirmation still mislabels the image');
 echo "PASS artwork dimensions, square shape, format, size, unreadable image and separate confirmation across all routes\n";
} finally {unlink($path);}
