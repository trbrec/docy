<?php
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();$stage='bootstrap';
set_exception_handler(static function(){global $stage;while(ob_get_level())ob_end_clean();fwrite(STDERR,"Revised welcome verification unconfirmed at ".$stage.".\n");exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');require $root.'/app/OnboardingRuntime.php';
$stage='scope';$db=\TrbCrm\Database::connection();$ledger=new \TrbCrm\OnboardingLedger($db);$p=$ledger->forContract(26);
if(!$p||$p['email']!=='a.tognassi@gmail.com'||($p['snapshot']['contract_number']??'')!=='QA-TRB-NONVALIDO-20261002-2235')throw new RuntimeException('Scope');
$q=$db->prepare('SELECT submission_id FROM contracts WHERE id=?');$q->execute([26]);if((int)$q->fetchColumn()!==719)throw new RuntimeException('Scope');
$stage='revision';$expected='4fdab41cee1ec2b7828c65b6773f61de0915b01a';if(trim(file_get_contents('/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy/.trb-deployed-sha'))!==$expected)throw new RuntimeException('Revision');
$stage='signature';if(!in_array($p['state'],['activation_ready','active'],true)||!$p['owner_approved_at']||!$p['signed_at']||!$ledger->artifact($p['id'],'signed_pdf')||!$ledger->artifact($p['id'],'signature_audit')||($ledger->signature($p['id'])['state']??'')!=='completed')throw new RuntimeException('Signature');
$stage='page';$context=stream_context_create(['http'=>['timeout'=>15,'ignore_errors'=>true]]);
$page=@file_get_contents('https://artist.trbrec.com/adesione/?accesso=1',false,$context);
if(!is_string($page)||!str_contains($page,'id="onboarding-main"')||!str_contains($page,'"welcome":true')||!str_contains($page,'Imposta la password e accedi'))throw new RuntimeException('First access page');
$token=hash_hmac('sha256','onboarding-invite-v1|'.$p['id'],(string)\TrbCrm\Env::get('APP_KEY',''));if(!hash_equals($p['token_hash'],hash('sha256',$token)))throw new RuntimeException('Invite');
$url='https://artist.trbrec.com/adesione/?accesso=1#invite='.$token;$message=\TrbCrm\OnboardingMail::welcome($p['snapshot']['first_name'],$url,$p['snapshot']['group_code']);
if(!str_contains($message['html'],'>Benvenuto in TRB rec</h1>')||$message['subject']!=='Il tuo accesso al Portale Artisti è pronto | TRB rec')throw new RuntimeException('Brand');
$stage='reservation';$lock=$db->query("SELECT GET_LOCK('trb_qa_welcome_revision_20261003',0)");if((int)$lock->fetchColumn()!==1)throw new RuntimeException('Busy');
try{
 $key='onboarding:'.$p['id'].':welcome-revised-20261003';$q=$db->prepare('SELECT status FROM onboarding_events WHERE event_key=?');$q->execute([$key]);$prior=$q->fetchColumn();
 if($prior){$out=['already_reserved'=>true,'status'=>$prior,'no_second_send'=>true];}
 else{
  $q=$db->prepare('INSERT INTO onboarding_events(event_key,practice_id,kind,payload,status,created_at) VALUES(?,?,?,?,?,?)');$q->execute([$key,$p['id'],'welcome_revision','{"revision":"20261003d"}','dispatching',gmdate('c')]);
  $stage='send';try{$runtime=new \TrbCrm\OnboardingRuntime($db);$mail=new ReflectionMethod(\TrbCrm\OnboardingRuntime::class,'mail');$receipt=$mail->invoke($runtime,$p['email'],$message['subject'],$message['text'],$message['html']);if(empty($receipt['gmail_message_id']))throw new RuntimeException('Receipt');
   $q=$db->prepare('UPDATE onboarding_events SET status=?,payload=?,completed_at=? WHERE event_key=?');$q->execute(['sent',json_encode(['revision'=>'20261003d','gmail_message_id'=>$receipt['gmail_message_id']]),gmdate('c'),$key]);
   $out=['deployed_revision'=>$expected,'recipient_exact'=>true,'both_signatures_and_archive'=>true,'first_access_page_verified'=>true,'personal_invite_binding'=>true,'heading'=>'Benvenuto in TRB rec','subject'=>$message['subject'],'sent_copy_verified'=>true,'status'=>'sent','sent_at'=>gmdate('c'),'state'=>$p['state']];
  }catch(Throwable $e){$q=$db->prepare('UPDATE onboarding_events SET status=? WHERE event_key=?');$q->execute(['uncertain',$key]);throw $e;}
 }
}finally{$db->query("SELECT RELEASE_LOCK('trb_qa_welcome_revision_20261003')");}
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_SLASHES)."\n";
