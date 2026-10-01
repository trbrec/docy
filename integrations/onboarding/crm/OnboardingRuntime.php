<?php
declare(strict_types=1);
namespace TrbCrm;
require_once __DIR__.'/OnboardingService.php';
require_once __DIR__.'/OnboardingDrive.php';
require_once __DIR__.'/OnboardingIdentity.php';
require_once __DIR__.'/OnboardingTransport.php';
require_once __DIR__.'/OnboardingEntry.php';
require_once __DIR__.'/OnboardingIntake.php';

/** An isolated, authenticated extension of the existing CRM entry point. */
final class OnboardingRuntime
{
    private OnboardingLedger $ledger;
    private OnboardingService $service;
    private OnboardingDrive $archive;
    private \Closure $script;
    public function __construct(private \PDO $db)
    {
        $this->ledger=new OnboardingLedger($db);
        $portal=static fn(array $p)=>OnboardingTransport::signed('https://artist.trbrec.com/wp-json/trb/v1/onboarding/private',(string)Env::get('ARTIST_PORTAL_SYNC_SECRET',''),$p,'onboarding-portal-v1');
        $this->script=static function(array $p):array{
            if(($p['action']??'')==='crm_onboarding_document'&&empty($p['invite_url']))$p['invite_url']='https://artist.trbrec.com/adesione/#invite='.hash_hmac('sha256','onboarding-invite-v1|'.$p['practice_id'],(string)Env::get('APP_KEY',''));
            return OnboardingTransport::script((string)Env::get('CONTRACT_APPS_SCRIPT_URL',''),(string)Env::get('CONTRACT_APPS_SCRIPT_SECRET',''),$p);
        };
        $this->archive=new OnboardingDrive($this->script);
        $this->service=new OnboardingService($this->ledger,$this->archive,new OnboardingIdentity((string)Env::get('OPENAI_API_KEY','')),$portal,$this->script);
    }
    public static function enabled(): bool
    {
        $path=dirname(__DIR__,2).'/private/onboarding-enabled.json';
        $flag=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        return ($flag['version']??'')===OnboardingPolicy::VERSION&&($flag['enabled']??false)===true;
    }
    private static function today(): string{return (new \DateTimeImmutable('now',new \DateTimeZone('Europe/Rome')))->format('Y-m-d');}
    public static function dispatch(string $method,string $path): bool
    {
        if(!in_array($path,['/onboarding','/webhooks/artist-portal/onboarding'],true))return false;
        if(!self::enabled()){Response::json(['error'=>'Nuove adesioni non ancora abilitate'],503);return true;}
        try{
            if($path==='/webhooks/artist-portal/onboarding'){
                if($method!=='POST')Response::json(['error'=>'Metodo non consentito'],405);
                $body=(string)file_get_contents('php://input');$nonce=(string)($_SERVER['HTTP_X_TRB_NONCE']??'');
                if(!OnboardingTransport::verify($body,(string)($_SERVER['HTTP_X_TRB_TIMESTAMP']??''),$nonce,(string)($_SERVER['HTTP_X_TRB_SIGNATURE']??''),(string)Env::get('ARTIST_PORTAL_SYNC_SECRET',''),'onboarding-crm-v1'))Response::json(['error'=>'Richiesta non autorizzata'],403);
                $runtime=new self(Database::connection());$runtime->ledger->nonce($nonce);
                if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
                $input=json_decode($body,true,32,JSON_THROW_ON_ERROR);if(!is_array($input))throw new \RuntimeException('Richiesta non valida');
                Response::json($runtime->rpc($input));return true;
            }
            $owner=Security::requireUser();
            if(($owner['role']??'')!=='admin'||strcasecmp((string)$owner['email'],'andrea.tognassi@trbrec.com')!==0)Response::json(['error'=>'Sezione riservata al titolare'],403);
            $runtime=new self(Database::connection());
            $notice='';
            if($method==='POST'){
                Security::verifyCsrf();$action=(string)($_POST['action']??'');$id=(string)($_POST['id']??'');
                switch($action){
                    case 'invite':$notice='Collegamento personale: '.$runtime->invitation($runtime->ledger->practice($id));break;
                    case 'candidate':$contractId=(new OnboardingEntry($runtime->db))->draft((int)($_POST['submission_id']??0),(string)($_POST['template_key']??''),(int)$owner['id']);$result=$runtime->prepare($contractId,0,(string)($_POST['template_key']??''));$notice='Proposta preparata. Collegamento personale: '.$result['invite_url'];break;
                    case 'prepare':$result=$runtime->prepare((int)($_POST['contract_id']??0),(int)($_POST['folder_id']??0),(string)($_POST['template_key']??''));$notice='Proposta preparata. Collegamento personale: '.$result['invite_url'];break;
                    case 'approve':$runtime->service->refreshPayments($id);$runtime->ledger->approve($id,$owner,'andrea.tognassi@trbrec.com',self::today());$runtime->service->prepareFinal($runtime->ledger->practice($id));$notice='Approvazione registrata. Verifica il documento definitivo prima dell’invio alla firma.';break;
                    case 'signature':if(($_POST['confirm_signature']??'')!=='1')throw new \RuntimeException('Conferma l’invio alla firma');$result=$runtime->service->dispatchSignature($runtime->ledger->practice($id));$notice=!empty($result['already_reserved'])?'Invio già riservato: nessun nuovo dossier creato.':'Dossier di firma creato.';break;
                    case 'refresh':$runtime->service->refreshPayments($id);$runtime->service->refreshSignature($runtime->ledger->practice($id));$notice='Verifica completata.';break;
                    case 'cancel':$runtime->ledger->cancel($id,$owner);$notice='Adesione annullata. Nessuna nuova scadenza sarà generata.';break;
                    case 'review_identity':$runtime->ledger->recordIdentity($id,['first_name'=>(string)($_POST['first_name']??''),'last_name'=>(string)($_POST['last_name']??''),'birth_date'=>(string)($_POST['birth_date']??''),'legible'=>true],self::today());$runtime->ledger->recordEvent($id,'identity_reviewed',['owner_id'=>(int)$owner['id']]);$notice='Trascrizione revisionata con gli stessi controlli su nome e maggiore età.';break;
                    case 'document':$file=$runtime->service->document($runtime->ledger->practice($id),(string)($_POST['slot']??''))['file'];header('Content-Type: '.$file['mime']);header('Content-Disposition: inline; filename="'.preg_replace('/[^A-Za-z0-9._-]/','_',$file['name']).'"');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');echo base64_decode($file['data'],true);exit;
                    default:throw new \RuntimeException('Operazione non disponibile');
                }
            }elseif($method!=='GET')Response::json(['error'=>'Metodo non consentito'],405);
            header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');
            require __DIR__.'/OnboardingAdmin.php';return true;
        }catch(\Throwable $e){
            if($path==='/webhooks/artist-portal/onboarding'){Response::json(['error'=>$e instanceof \RuntimeException?$e->getMessage():'Operazione non confermata: riprova o contatta l’assistenza'],409);return true;}
            http_response_code(409);header('Content-Type: text/html; charset=utf-8');echo '<h1>Operazione da verificare</h1><p>'.htmlspecialchars($e instanceof \RuntimeException?$e->getMessage():'Operazione non confermata',ENT_QUOTES,'UTF-8').'</p><a href="/onboarding">Torna alle adesioni</a>';return true;
        }
    }
    public function rpc(array $input): array
    {
        $action=(string)($input['action']??'');
        if($action==='health')return ['enabled'=>self::enabled(),'version'=>OnboardingPolicy::VERSION];
        if($action==='worker')return $this->worker();
        if($action==='challenge'){$c=$this->ledger->challenge((string)($input['token']??''));$p=$this->ledger->practice($c['practice_id']);$this->mail($c['email'],'Codice per la tua adesione TRB rec','Gentile '.$p['snapshot']['first_name'].",\n\nil codice per accedere alla tua adesione è ".$c['code'].". Scade tra 10 minuti.\nSe non hai richiesto l’accesso, ignora questo messaggio.");return ['salt'=>$c['salt']];}
        if($action==='verify_email'){$session=$this->ledger->verifyEmail((string)($input['token']??''),(string)($input['code']??''),(string)($input['salt']??''));return ['session'=>$session,'practice'=>$this->service->view($this->ledger->fromSession($session))];}
        if(in_array($action,['account','account_checkout','account_document'],true)){
            $p=$this->ledger->portalPractice((int)($input['portal_user_id']??0),(string)($input['email']??''));
            if($action==='account_document')return $this->service->document($p,(string)($input['slot']??''));
            $this->service->refreshPayments($p['id']);$p=$this->ledger->practice($p['id']);
            return $action==='account_checkout'?$this->service->checkout($p):$this->service->view($p);
        }
        $p=$this->ledger->fromSession((string)($input['session']??''));
        return match($action){
            'view'=>$this->service->view($p),
            'details'=>$this->service->details($p,$input),
            'upload'=>$this->service->upload($p,(string)($input['slot']??''),(array)($input['file']??[])),
            'uploaded'=>$this->service->uploaded($p,(string)($input['slot']??'')),
            'identity'=>$this->service->identity($p),
            'choose'=>$this->service->choose($p,(string)($input['plan_key']??''),(string)($input['proposal_sha256']??''),($input['proposal_read']??false)===true),
            'checkout'=>$this->service->checkout($p),
            'refresh'=>$this->refresh($p),
            'document'=>$this->service->document($p,(string)($input['slot']??'')),
            'registration_authorization'=>$this->service->registrationAuthorization($p),
            'register'=>$this->service->register($p,(int)($input['portal_user_id']??0)),
            default=>throw new \RuntimeException('Operazione non disponibile'),
        };
    }
    private function refresh(array $p): array
    {
        $this->service->refreshPayments($p['id']);return $this->service->view($this->service->refreshSignature($this->ledger->practice($p['id'])));
    }
    private function mail(string $email,string $subject,string $text): void
    {
        if(!self::enabled()||!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email))throw new \RuntimeException('Invio adesione non autorizzato');
        $messageId='<trbonboarding.'.bin2hex(random_bytes(16)).'@crm.trbrec.com>';$boundary='=_trbonboarding_'.bin2hex(random_bytes(12));
        $subject=mb_encode_mimeheader(trim(preg_replace('/[\r\n]+/',' ',$subject)), 'UTF-8','B', "\r\n");
        $raw='To: '.$email."\r\n".'From: Andrea Tognassi - TRB rec <andrea.tognassi@trbrec.com>'."\r\n".'Reply-To: andrea.tognassi@trbrec.com'."\r\n".'Subject: '.$subject."\r\n".'Date: '.gmdate('D, d M Y H:i:s O')."\r\n".'Message-ID: '.$messageId."\r\n".'MIME-Version: 1.0'."\r\n".'X-Auto-Response-Suppress: All'."\r\n".'Content-Type: multipart/alternative; boundary="'.$boundary.'"'."\r\n\r\n".OutboundMail::alternativePayload(OutboundMail::withSignature($text),$boundary);
        $result=OutboundMail::candidateBridge(['action'=>'crm_candidate_mail_send','confirm'=>true,'operator_confirmed'=>true,'recipient'=>$email,'message_id'=>$messageId,'raw_base64'=>base64_encode($raw),'mime_sha256'=>hash('sha256',$raw)]);
        if(($result['sent']??false)!==true||empty($result['gmail_message_id'])||empty($result['sent_copy']))throw new \RuntimeException('Invio email non confermato');
    }
    private function invitation(array $p): string
    {
        $key=(string)Env::get('APP_KEY','');if(strlen($key)<32)throw new \RuntimeException('Chiave inviti non configurata');
        return 'https://artist.trbrec.com/adesione/#invite='.hash_hmac('sha256','onboarding-invite-v1|'.$p['id'],$key);
    }
    private function preparationChoices(): array
    {
        return ['contracts'=>OnboardingIntake::choices($this->db),'candidates'=>(new OnboardingEntry($this->db))->candidates()];
    }
    private function prepare(int $contractId,int $folderId,string $key): array
    {
        if(!self::enabled())throw new \RuntimeException('Nuove adesioni non ancora abilitate');
        $folderId=$contractId;
        $lockName='trb_onboarding_prepare_'.$contractId;$lock=$this->db->prepare('SELECT GET_LOCK(?,0)');$lock->execute([$lockName]);if((int)$lock->fetchColumn()!==1)throw new \RuntimeException('Proposta in preparazione');
        try{
        if($old=$this->ledger->forContract($contractId)){
            if($key!==''&&$old['snapshot']['template_key']!==$key)throw new \RuntimeException('Adesione già preparata con dati diversi');
            return ['id'=>$old['id'],'invite_url'=>$this->invitation($old)];
        }
        $q=$this->db->prepare('SELECT ct.id,ct.contract_number,ct.template_key,ct.status,ct.sent_at,ct.accepted_at,ct.metadata,s.source_tab,c.first_name,c.last_name,c.artist_name,c.email FROM contracts ct JOIN submissions s ON s.id=ct.submission_id JOIN contacts c ON c.id=s.contact_id WHERE ct.id=?');$q->execute([$contractId]);$row=$q->fetch();
        if(!$row||!OnboardingIntake::eligible($row)||($key!==''&&$row['template_key']!==$key))throw new \RuntimeException('Scegli una nuova proposta non ancora inviata, con lo stesso modello');
        $key=$row['template_key'];$model=OnboardingContractCatalog::model($key);
        $folderId=$this->archive->artistFolder($model['group_code'],$contractId)['id'];
        $preparation=$this->ledger->preparation($contractId);$id=$preparation['id']??bin2hex(random_bytes(16));$url=$this->invitation(['id'=>$id]);$token=substr($url,strpos($url,'#invite=')+8);
        $snapshot=$model+array_intersect_key($row,array_flip(['contract_number','first_name','last_name','artist_name','email']));$snapshot['artist_folder_id']=$folderId;$snapshot['archive_provider']='google_drive';
        if($preparation&&$preparation['snapshot']!==$snapshot)throw new \RuntimeException('Proposta già in preparazione con dati diversi');
        if(!$preparation){$grant=$this->archive->createUpload($folderId,$id,'proposal');$this->ledger->savePreparation($contractId,['id'=>$id,'snapshot'=>$snapshot,'grant'=>$grant]);}else $grant=$preparation['grant'];
        if(strtotime($grant['expires_at'])<=time()){$grant=$this->archive->createUpload($folderId,$id,'proposal');$this->ledger->renewPreparationUpload($contractId,$grant);}
        $payload=['action'=>'crm_onboarding_document','phase'=>'proposal','practice_id'=>$id,'snapshot'=>$snapshot,'invite_url'=>$url,'upload'=>$grant];
        try{$result=($this->script)($payload);}catch(\Throwable $e){$result=($this->script)($payload+['metadata_only'=>true]);}
        $file=$this->archive->verifyArtifact($grant,(string)($result['sha256']??''),$folderId);
        $snapshot['unsigned_document_sha256']=$file['sha256'];$snapshot['proposal_drive_pdf_id']=(string)($result['drive_pdf_id']??'');
        // No customer email is sent here. The owner reviews and delivers the personal invitation.
        $created=$this->ledger->create($contractId,$snapshot,gmdate('c',time()+30*86400),$token,$id,$file);
        return ['id'=>$created['id'],'invite_url'=>$url];
        }finally{$release=$this->db->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lockName]);}
    }
    private function worker(): array
    {
        $lock=$this->db->query("SELECT GET_LOCK('trb_onboarding_worker',0)");if((int)$lock->fetchColumn()!==1)return ['running'=>true];
        $started=microtime(true);$checked=0;$errors=0;
        try{
            foreach($this->ledger->workerCandidates() as $p){if(microtime(true)-$started>35)break;
                try{$this->service->refreshPayments($p['id']);if($p['state']==='signature_pending')$this->service->refreshSignature($p);$checked++;}catch(\Throwable $e){$errors++;}finally{$this->ledger->checked($p['id']);}
            }
            $this->ledger->queueOverdueReminders(self::today());
            for($i=0;$i<3&&microtime(true)-$started<40;$i++){$reminder=$this->ledger->takeReminder();if(!$reminder)break;$sent=false;
                try{$p=$this->ledger->practice($reminder['practice_id']);$residual=(int)$reminder['debt']['amount_cents']-(int)$reminder['debt']['confirmed_cents'];$this->mail($p['email'],'Quota contrattuale scaduta – TRB rec','Gentile '.$p['snapshot']['first_name'].",\n\nla quota n. ".$reminder['debt']['number'].' con scadenza '.$reminder['debt']['due_date'].' risulta ancora da regolarizzare per € '.number_format($residual/100,2,',','.').".\nAccedi a https://artist.trbrec.com/adesione/?account=1 per completare il versamento. I servizi riprendono dopo la conferma del circuito; accesso, documenti e assistenza restano disponibili.");$sent=true;}catch(\Throwable $e){$errors++;}
                $this->ledger->finishReminder($reminder['event_key'],$sent);
            }
            $this->ledger->expireSessions();return ['checked'=>$checked,'pending_checks'=>$errors,'completed_at'=>gmdate('c')];
        }finally{$this->db->query("SELECT RELEASE_LOCK('trb_onboarding_worker')");}
    }
}
