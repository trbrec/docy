<?php
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();fwrite(STDERR,"QA preparation inspection unconfirmed.\n");exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');$db=\TrbCrm\Database::connection();

$source=file_get_contents($root.'/app/SubmissionRepository.php');$lines=explode("\n",$source);$out=[];
foreach(['previewContract','prepareCandidateContractReview','sendContractBatch','processContractBatchQueue','saveContract','find'] as $method){$start=null;foreach($lines as $i=>$line)if(preg_match('/public function '.preg_quote($method,'/').'\\(/',$line)){$start=$i;break;}if($start===null)continue;$chunk=[];for($i=$start;$i<count($lines);$i++){if($i>$start&&preg_match('/^    (public|private|protected) function /',$lines[$i]))break;$chunk[]=$lines[$i];}$out[$method]=implode("\n",$chunk);}
while(ob_get_level())ob_end_clean();echo json_encode($out)."\n";
