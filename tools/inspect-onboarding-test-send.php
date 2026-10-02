<?php
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();fwrite(STDERR,"QA preparation inspection unconfirmed.\n");exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');$db=\TrbCrm\Database::connection();
$out=[];foreach(['submissions','contracts','outbound_batches','outbound_batch_items'] as $t)$out['columns'][$t]=$db->query("SHOW COLUMNS FROM ".$t)->fetchAll(PDO::FETCH_COLUMN);
$q=$db->prepare("SELECT s.id,s.contact_id,s.source_tab,s.status,s.contract_number,c.first_name,c.last_name,c.email,p.snapshot FROM onboarding_practices p JOIN contracts ct ON ct.id=p.contract_id JOIN submissions s ON s.id=ct.submission_id JOIN contacts c ON c.id=s.contact_id WHERE p.email=? AND ct.template_key='trb_ccde' ORDER BY ct.id DESC LIMIT 1");$q->execute(['a.tognassi@gmail.com']);$s=$q->fetch();
$out['source']=$s?['id'=>(int)$s['id'],'contact_id'=>(int)$s['contact_id'],'source_tab'=>$s['source_tab'],'status'=>$s['status'],'known_test'=>str_contains(strtoupper($s['snapshot']),'QA-'),'recipient_matches'=>$s['email']==='a.tognassi@gmail.com','name_matches'=>$s['first_name']==='Andrea'&&$s['last_name']==='Tognassi']:null;
foreach(glob($root.'/app/*Repository*.php') as $file){$text=file_get_contents($file);preg_match_all('/public function ([A-Za-z0-9_]+)\(([^\n]*)\)/',$text,$m,PREG_SET_ORDER);$out['repository'][basename($file)]=array_map(static fn($x)=>$x[1].'('.$x[2].')',$m);}
while(ob_get_level())ob_end_clean();echo json_encode($out)."\n";
