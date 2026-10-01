<?php
declare(strict_types=1);
namespace TrbCrm;
require_once __DIR__.'/OnboardingRuntime.php';

/** The existing candidate preview/save/send commands own proposal delivery. */
final class OnboardingContractWorkflow
{
    public const PREVIEW_URL='https://artist.trbrec.com/adesione/#invite=PENDING';
    public static function candidate(array $s): bool
    {
        return OnboardingRuntime::enabled() && !in_array(strtoupper((string)($s['source_tab']??'')),['PORTALE_ARTISTI','PORTALE_DEMO'],true)
            && !in_array((string)($s['status']??''),['archived','merged_duplicate','rejected','accepted'],true);
    }
    public static function handles(array $s,string $key): bool
    {
        if(!self::candidate($s))return false;
        try{OnboardingContractCatalog::model($key);return true;}catch(\InvalidArgumentException $e){return false;}
    }
    public static function preview(\PDO $db,array $s,string $key): array
    {
        $q=$db->prepare('SELECT display_name,email_subject,email_body,followup_subject,followup_body,document_source_url FROM contract_templates WHERE template_key=? AND is_active=1 LIMIT 1');$q->execute([$key]);$t=$q->fetch();
        if(!$t)throw new \RuntimeException('Modello contrattuale non disponibile');
        $url=self::PREVIEW_URL;$replace=['{nome_contatto}'=>$s['first_name']??'','{nome_artista}'=>$s['artist_name']??'','{nome}'=>$s['first_name']??'','{cognome}'=>$s['last_name']??'','{numero_contratto}'=>$s['contract_number']??'','{link_contratto}'=>$url,'{email_artista}'=>$s['email']??'','{email}'=>$s['email']??'','{data_candidatura}'=>empty($s['received_at'])?'':(new \DateTimeImmutable($s['received_at'],new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Rome'))->format('d/m/Y')];
        $personalize=static fn($text)=>strtr((string)$text,$replace);
        $subject=$personalize($t['email_subject']);$body=self::wording($personalize($t['email_body']));
        if(!str_contains($body,$url))$body.="\n\nPer avviare l’attivazione, apri il collegamento personale riportato nel contratto allegato.";
        $model=OnboardingContractCatalog::model($key);
        return ['template_key'=>$key,'display_name'=>$t['display_name'],'document_source_url'=>$t['document_source_url'],'document_url'=>$url,'recipient'=>$s['email'],'artist_name'=>$s['artist_name'],'contract_number'=>$s['contract_number'],'subject'=>$subject,'body'=>$body,'followup_subject'=>$personalize($t['followup_subject']),'followup_body'=>self::wording($personalize($t['followup_body'])),'mail_mode'=>'production','recipient_allowed'=>filter_var($s['email'],FILTER_VALIDATE_EMAIL)!==false,'attachment_ready'=>false,'pdf_generation_available'=>true,'plugin_dispatch_available'=>true,'source_verified'=>true,'onboarding'=>true,'attachment_message'=>'Il PDF con il collegamento personale al Portale Artisti sarà preparato e allegato automaticamente alla conferma dell’invio. Nessuna richiesta di firma viene inviata in questa fase.','preview_token'=>self::hash((int)$s['id'],$key,$s['email'],$url,$subject,$body)];
    }
    public static function hash(int $id,string $key,string $email,string $url,string $subject,string $body): string
    {
        return hash_hmac('sha256',implode("\n",[$id,$key,mb_strtolower(trim($email)),trim($url),trim($subject),trim($body)]),(string)Env::get('APP_KEY','change-me'));
    }
    public static function wording(string $text): string
    {
        return str_ireplace(['Carica qui il contratto firmato','Carica il contratto firmato','caricare il contratto firmato'],['Completa l’attivazione sul Portale Artisti','Completa l’attivazione sul Portale Artisti','completare l’attivazione sul Portale Artisti'],$text);
    }
    public static function send(\PDO $db,array $s,int $userId): void
    {
        $owner=Security::requireUser();if((int)$owner['id']!==$userId||($owner['role']??'')!=='admin'||strcasecmp($owner['email'],'andrea.tognassi@trbrec.com')!==0)throw new \RuntimeException('Invio riservato al titolare');
        $c=$s['contract'];$m=is_array($c['metadata']??null)?$c['metadata']:[];
        if(in_array($c['status'],['sent','opened','otp_pending','accepted'],true))throw new \RuntimeException('Questo contratto risulta già inviato');
        $url=(string)($c['document_url']??'');$subject=trim((string)($m['subject']??''));$body=trim((string)($m['body']??''));
        if($subject===''||$body===''||!hash_equals(self::hash((int)$s['id'],$c['template_key'],$s['email'],$url,$subject,$body),(string)($m['preview_hash']??'')))throw new \RuntimeException('Genera e conferma una nuova anteprima prima dell’invio');
        $runtime=new OnboardingRuntime($db);$prepared=$runtime->prepare((int)$c['id'],0,$c['template_key']);$ledger=new OnboardingLedger($db);$p=$ledger->practice($prepared['id']);
        if(strcasecmp($p['email'],$s['email'])!==0||$p['snapshot']['first_name']!==$s['first_name']||$p['snapshot']['last_name']!==$s['last_name']||$p['snapshot']['artist_name']!==$s['artist_name'])throw new \RuntimeException('I dati anagrafici sono cambiati dopo la preparazione: verifica la pratica prima dell’invio');
        $artifact=$ledger->artifact($p['id'],'proposal');$archive=new OnboardingDrive(static fn($payload)=>OnboardingTransport::script((string)Env::get('CONTRACT_APPS_SCRIPT_URL',''),(string)Env::get('CONTRACT_APPS_SCRIPT_SECRET',''),$payload));$file=$archive->read($artifact['file_id'],$artifact['folder_id'],(string)$artifact['hash']);
        $bytes=base64_decode((string)($file['data']??''),true);if($bytes===false||!str_starts_with($bytes,'%PDF-')||!hash_equals($artifact['sha256'],hash('sha256',$bytes)))throw new \RuntimeException('Allegato privato non verificato');
        $body=self::wording(str_replace($url,$prepared['invite_url'],$body));$subject=str_replace($url,$prepared['invite_url'],$subject);
        $event='onboarding:'.$p['id'].':proposal-email';$messageId='<trbproposal.'.$p['id'].'@crm.trbrec.com>';
        $raw=self::mime($p['email'],$subject,$body,$bytes,'Contratto-'.$c['contract_number'].'.pdf',$messageId,$p['created_at']);$sha=hash('sha256',$raw);
        $q=$db->prepare('SELECT status,payload FROM onboarding_events WHERE event_key=?');$q->execute([$event]);$previous=$q->fetch();
        if($previous)throw new \RuntimeException($previous['status']==='completed'?'Proposta già inviata: aggiorna la scheda.':'Esito del precedente invio da verificare: nessuna nuova email è stata inviata.');
        $db->prepare('INSERT INTO onboarding_events(event_key,practice_id,kind,payload,status,created_at) VALUES(?,?,?,?,?,?)')->execute([$event,$p['id'],'proposal_email',json_encode(['message_id'=>$messageId,'mime_sha256'=>$sha,'document_sha256'=>$artifact['sha256']]),'dispatching',gmdate('c')]);
        $receipt=OutboundMail::candidateBridge(['action'=>'crm_candidate_mail_send','confirm'=>true,'operator_confirmed'=>true,'recipient'=>$p['email'],'message_id'=>$messageId,'raw_base64'=>base64_encode($raw),'mime_sha256'=>$sha]);
        if(($receipt['sent']??false)!==true||empty($receipt['gmail_message_id'])||empty($receipt['sent_copy']))throw new \RuntimeException('Invio non confermato: verifica la posta inviata prima di riprovare');
        $m['onboarding']=['practice_id'=>$p['id'],'archive_provider'=>'google_drive','proposal_sha256'=>$artifact['sha256'],'gmail_message_id'=>$receipt['gmail_message_id']];
        $db->beginTransaction();try{
            $db->prepare("UPDATE contracts SET status='sent',document_url=?,sent_at=CURRENT_TIMESTAMP,metadata=? WHERE id=?")->execute([$prepared['invite_url'],json_encode($m,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$c['id']]);
            $db->prepare("UPDATE submissions SET status='contract_sent',contract_sent_at=CURRENT_TIMESTAMP WHERE id=?")->execute([(int)$s['id']]);
            $db->prepare("UPDATE onboarding_events SET status='completed',payload=?,completed_at=? WHERE event_key=?")->execute([json_encode(['message_id'=>$messageId,'mime_sha256'=>$sha,'document_sha256'=>$artifact['sha256'],'gmail_message_id'=>$receipt['gmail_message_id']]),gmdate('c'),$event]);
            $threadProvider='contract-'.(int)$c['id'];
            $db->prepare("INSERT INTO mail_threads(submission_id,contract_id,provider,provider_thread_id,normalized_subject,status,last_outbound_at) VALUES(?,?,'gmail',?,?,'waiting_artist',CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE normalized_subject=VALUES(normalized_subject),status='waiting_artist',last_outbound_at=CURRENT_TIMESTAMP")->execute([(int)$s['id'],(int)$c['id'],$threadProvider,mb_strtolower($subject)]);
            $thread=$db->prepare("SELECT id FROM mail_threads WHERE provider='gmail' AND provider_thread_id=? LIMIT 1");$thread->execute([$threadProvider]);$threadId=(int)$thread->fetchColumn();if(!$threadId)throw new \RuntimeException('Conversazione email non registrata');
            $db->prepare("INSERT INTO mail_messages(thread_id,provider_message_id,direction,from_address,to_addresses,subject,body_text,attachments,delivery_status,occurred_at) VALUES(?,?,'outbound',?,?,?,?,?,'sent',CURRENT_TIMESTAMP)")->execute([$threadId,$messageId,'andrea.tognassi@trbrec.com',json_encode([$p['email']]),$subject,$body,json_encode(['provider'=>'google_drive','sha256'=>$artifact['sha256'],'name'=>'Contratto-'.$c['contract_number'].'.pdf'])]);
            foreach([1,2] as $sequence){
                $days=max(1,(int)($m['followup_'.$sequence.'_days']??($sequence===1?7:14)));$followup=self::wording(str_replace($url,$prepared['invite_url'],(string)($m['followup_'.$sequence.'_body']??'')));
                if($followup==='')$followup='Ciao '.$s['first_name'].",\n\nhai avuto modo di visionare la proposta ".$c['contract_number'].'? '.$prepared['invite_url'];
                $followupSubject=(string)($m['followup_subject']??'Re: '.$subject);
                $db->prepare("INSERT INTO followups(submission_id,contract_id,sequence_no,due_at,status,template_key,subject,parent_message_id,personalized_body,attachment_metadata) VALUES(?,?,?,DATE_ADD(CURRENT_TIMESTAMP,INTERVAL ? DAY),'scheduled','contract_reminder',?,?,?,?) ON DUPLICATE KEY UPDATE contract_id=VALUES(contract_id),due_at=VALUES(due_at),status='scheduled',subject=VALUES(subject),parent_message_id=VALUES(parent_message_id),personalized_body=VALUES(personalized_body),attachment_metadata=VALUES(attachment_metadata),sent_at=NULL")->execute([(int)$s['id'],(int)$c['id'],$sequence,$days,$followupSubject,$messageId,$followup,json_encode(['provider'=>'google_drive','sha256'=>$artifact['sha256']])]);
            }
            $db->prepare("INSERT INTO interactions(submission_id,user_id,type,subject,body,metadata) VALUES(?,?,'email_out',?,?,?)")->execute([(int)$s['id'],$userId,$subject,$body,json_encode($m['onboarding'])]);
            $db->prepare('INSERT INTO audit_log(user_id,entity_type,entity_id,action,before_json,after_json,ip_hash) VALUES(?,?,?,?,?,?,?)')->execute([$userId,'contract',(string)$c['id'],'contract_proposal_sent',json_encode(['status'=>$c['status']]),json_encode($m['onboarding']),Security::ipHash()]);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw new \RuntimeException('Email inviata; storico da verificare. Un nuovo invio è bloccato per evitare duplicazioni.');}
    }
    public static function mime(string $email,string $subject,string $body,string $pdf,string $filename,string $messageId,string $createdAt): string
    {
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email.$messageId.$filename))throw new \RuntimeException('Destinatario o allegato non valido');
        $mixed='=_trbproposal_'.hash('sha256',$messageId);$alt=$mixed.'_alt';$subject=mb_encode_mimeheader(preg_replace('/[\r\n]+/',' ',$subject),'UTF-8','B',"\r\n");
        return 'To: '.$email."\r\nFrom: Andrea Tognassi - TRB rec <andrea.tognassi@trbrec.com>\r\nReply-To: andrea.tognassi@trbrec.com\r\nSubject: ".$subject."\r\nDate: ".gmdate('D, d M Y H:i:s O',strtotime($createdAt))."\r\nMessage-ID: ".$messageId."\r\nMIME-Version: 1.0\r\nX-Auto-Response-Suppress: All\r\nContent-Type: multipart/mixed; boundary=\"".$mixed."\"\r\n\r\n--".$mixed."\r\nContent-Type: multipart/alternative; boundary=\"".$alt."\"\r\n\r\n".OutboundMail::alternativePayload(OutboundMail::withSignature($body),$alt)."\r\n--".$mixed."\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename=\"".$filename."\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($pdf),76,"\r\n").'--'.$mixed."--\r\n";
    }
}
