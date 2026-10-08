<?php
declare(strict_types=1);
namespace TrbCrm;

use PDO;
use RuntimeException;

require_once __DIR__.'/OnboardingPolicy.php';

/** Separate ledger. Never rewrites legacy contracts, releases or user permissions. */
final class OnboardingLedger
{
    public const IDENTITY_READER_VERSION='20261003-isolated-fronts';
    public function __construct(private PDO $db) {}

    public function install(): void
    {
        foreach ([
            'CREATE TABLE IF NOT EXISTS onboarding_practices (id VARCHAR(64) PRIMARY KEY, contract_id BIGINT NOT NULL UNIQUE, onboarding_version VARCHAR(16) NOT NULL, email VARCHAR(255) NOT NULL, snapshot TEXT NOT NULL, state VARCHAR(40) NOT NULL, token_hash VARCHAR(64) NOT NULL UNIQUE, expires_at VARCHAR(32) NOT NULL, selected_plan TEXT NULL, first_payment_date VARCHAR(10) NULL, owner_approved_at VARCHAR(32) NULL, signed_at VARCHAR(32) NULL, signed_pcloud_file_id VARCHAR(128) NULL, portal_activated_at VARCHAR(32) NULL, cancelled_at VARCHAR(32) NULL, created_at VARCHAR(32) NOT NULL)',
            'CREATE TABLE IF NOT EXISTS onboarding_installments (practice_id VARCHAR(64) NOT NULL, number INT NOT NULL, due_date VARCHAR(10) NOT NULL, amount_cents BIGINT NOT NULL, PRIMARY KEY(practice_id,number))',
            'CREATE TABLE IF NOT EXISTS onboarding_payments (provider VARCHAR(32) NOT NULL, transaction_id VARCHAR(128) NOT NULL, practice_id VARCHAR(64) NOT NULL, installment_number INT NOT NULL, amount_cents BIGINT NOT NULL, refunded_cents BIGINT NOT NULL DEFAULT 0, currency VARCHAR(3) NOT NULL, status VARCHAR(24) NOT NULL, confirmed_at VARCHAR(32) NOT NULL, PRIMARY KEY(provider,transaction_id))',
            'CREATE TABLE IF NOT EXISTS onboarding_events (event_key VARCHAR(190) PRIMARY KEY, practice_id VARCHAR(64) NOT NULL, kind VARCHAR(40) NOT NULL, payload TEXT NOT NULL, status VARCHAR(24) NOT NULL, created_at VARCHAR(32) NOT NULL, completed_at VARCHAR(32) NULL)',
            'CREATE TABLE IF NOT EXISTS onboarding_files (practice_id VARCHAR(64) NOT NULL, slot VARCHAR(40) NOT NULL, folder_id VARCHAR(128) NOT NULL, pcloud_file_id VARCHAR(128) NULL, upload_link_id VARCHAR(128) NULL, metadata TEXT NOT NULL, PRIMARY KEY(practice_id,slot))',
            'CREATE TABLE IF NOT EXISTS onboarding_identity_checks (practice_id VARCHAR(64) PRIMARY KEY, result TEXT NOT NULL, document_fingerprint VARCHAR(64) NOT NULL, checked_at VARCHAR(32) NOT NULL)',
            'CREATE TABLE IF NOT EXISTS onboarding_identity_jobs (practice_id VARCHAR(64) PRIMARY KEY, input_fingerprint VARCHAR(64) NOT NULL, state VARCHAR(24) NOT NULL, attempts INT NOT NULL DEFAULT 0, claim_token VARCHAR(64) NULL, lease_until BIGINT NOT NULL DEFAULT 0, next_retry_at BIGINT NOT NULL DEFAULT 0, reason VARCHAR(64) NOT NULL DEFAULT \'\', queued_at VARCHAR(32) NOT NULL, started_at VARCHAR(32) NULL, finished_at VARCHAR(32) NULL)',
            'CREATE TABLE IF NOT EXISTS onboarding_email_challenges (practice_id VARCHAR(64) PRIMARY KEY, code_hash VARCHAR(64) NOT NULL, expires_at BIGINT NOT NULL, attempts INT NOT NULL DEFAULT 0, issued_at BIGINT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS onboarding_sessions (token_hash VARCHAR(64) PRIMARY KEY, practice_id VARCHAR(64) NOT NULL, expires_at BIGINT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS onboarding_signatures (practice_id VARCHAR(64) PRIMARY KEY, request_key VARCHAR(64) NOT NULL UNIQUE, document_sha256 VARCHAR(64) NOT NULL, appendix_sha256 VARCHAR(64) NOT NULL, owner_id BIGINT NOT NULL, dossier_id VARCHAR(128) NULL, state VARCHAR(24) NOT NULL, signed_file_sha256 VARCHAR(64) NULL, created_at VARCHAR(32) NOT NULL)',
            'CREATE TABLE IF NOT EXISTS onboarding_holds (practice_id VARCHAR(64) NOT NULL, reference VARCHAR(128) NOT NULL, reason VARCHAR(40) NOT NULL, created_at VARCHAR(32) NOT NULL, PRIMARY KEY(practice_id,reference))',
            'CREATE TABLE IF NOT EXISTS onboarding_nonces (nonce VARCHAR(32) PRIMARY KEY, expires_at BIGINT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS onboarding_uploads (practice_id VARCHAR(64) NOT NULL, slot VARCHAR(40) NOT NULL, grant_data TEXT NOT NULL, PRIMARY KEY(practice_id,slot))',
            'CREATE TABLE IF NOT EXISTS onboarding_details (practice_id VARCHAR(64) PRIMARY KEY, details TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS onboarding_orders (practice_id VARCHAR(64) NOT NULL, order_id BIGINT NOT NULL UNIQUE, installment_number INT NOT NULL, created_at VARCHAR(32) NOT NULL, PRIMARY KEY(practice_id,order_id))',
            'CREATE TABLE IF NOT EXISTS onboarding_artifacts (practice_id VARCHAR(64) NOT NULL, slot VARCHAR(40) NOT NULL, metadata TEXT NOT NULL, PRIMARY KEY(practice_id,slot))',
            'CREATE TABLE IF NOT EXISTS onboarding_preparations (contract_id BIGINT PRIMARY KEY, metadata TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS onboarding_worker_checks (practice_id VARCHAR(64) PRIMARY KEY, checked_at VARCHAR(32) NOT NULL)',
            'CREATE TABLE IF NOT EXISTS onboarding_welcomes (practice_id VARCHAR(64) PRIMARY KEY, state VARCHAR(24) NOT NULL, created_at VARCHAR(32) NOT NULL, sent_at VARCHAR(32) NULL, gmail_message_id VARCHAR(128) NULL)',
        ] as $sql) $this->db->exec($sql);
        if($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
            foreach(['onboarding_practices'=>['signed_pcloud_file_id'],'onboarding_files'=>['folder_id','pcloud_file_id','upload_link_id']] as $table=>$columns)foreach($columns as $column){
                $info=$this->db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'")->fetch(PDO::FETCH_ASSOC);
                if(!str_starts_with(strtolower($info['Type']??''),'varchar'))$this->db->exec("ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR(128) ".($column==='folder_id'?'NOT NULL':'NULL'));
            }
        }
    }

    /** Reserve before delivery: concurrent refreshes and lost replies cannot send twice. */
    public function reserveWelcome(string $id): bool
    {
        return $this->locked(function()use($id){$p=$this->practice($id,true);
            if($p['state']!=='activation_ready'||!$p['owner_approved_at']||!$p['signed_at']||!$p['signed_pcloud_file_id']||$p['cancelled_at']||!$this->artifact($id,'signed_pdf')||!$this->artifact($id,'signature_audit'))return false;
            if($this->query('SELECT 1 FROM onboarding_welcomes WHERE practice_id=?',[$id])->fetchColumn())return false;
            $this->query('INSERT INTO onboarding_welcomes(practice_id,state,created_at) VALUES(?,?,?)',[$id,'dispatching',gmdate('c')]);return true;
        });
    }
    public function finishWelcome(string $id,?string $receipt): void
    {
        $this->query("UPDATE onboarding_welcomes SET state=?,sent_at=?,gmail_message_id=? WHERE practice_id=? AND state='dispatching'",[$receipt?'sent':'uncertain',$receipt?gmdate('c'):null,$receipt,$id]);
    }
    public function welcomeStatus(string $id): ?array
    {
        return $this->query('SELECT state,sent_at,gmail_message_id FROM onboarding_welcomes WHERE practice_id=?',[$id])->fetch(PDO::FETCH_ASSOC)?:null;
    }
    private function json(array $value): string { return json_encode($value, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    private function query(string $sql,array $params=[]): \PDOStatement {$s=$this->db->prepare($sql);$s->execute($params);return $s;}
    private function ownerReviewEvent(string $id): void
    {
        $key='onboarding:'.$id.':owner-review';if($this->query('SELECT 1 FROM onboarding_events WHERE event_key=?',[$key])->fetchColumn())return;
        $this->query('INSERT INTO onboarding_events(event_key,practice_id,kind,payload,status,created_at) VALUES(?,?,?,?,?,?)',[$key,$id,'owner_review','{}','queued',gmdate('c')]);
    }
    private function locked(callable $fn): mixed
    {
        if ($this->db->inTransaction()) throw new RuntimeException('Transazione annidata non consentita');
        $this->db->beginTransaction();
        try {$value=$fn();$this->db->commit();return $value;}
        catch (\Throwable $e) {if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function practice(string $id,bool $lock=false): array
    {
        $suffix=$lock&&$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite'?' FOR UPDATE':'';
        $p=$this->query('SELECT * FROM onboarding_practices WHERE id=?'.$suffix,[$id])->fetch(PDO::FETCH_ASSOC);
        if (!$p) throw new RuntimeException('Adesione non disponibile');
        $p['snapshot']=json_decode($p['snapshot'],true,512,JSON_THROW_ON_ERROR);
        $p['selected_plan']=$p['selected_plan']?json_decode($p['selected_plan'],true,512,JSON_THROW_ON_ERROR):null;
        return $p;
    }
    public function practices(int $limit=100): array
    {
        $limit=max(1,min(500,$limit));
        return array_map(fn($id)=>$this->practice($id),$this->query('SELECT id FROM onboarding_practices ORDER BY created_at DESC,id DESC LIMIT '.$limit)->fetchAll(PDO::FETCH_COLUMN));
    }
    public function artifact(string $id,string $slot): ?array
    {
        $raw=$this->query('SELECT metadata FROM onboarding_artifacts WHERE practice_id=? AND slot=?',[$id,$slot])->fetchColumn();
        return $raw?json_decode($raw,true,512,JSON_THROW_ON_ERROR):null;
    }
    public function saveArtifact(string $id,string $slot,array $file): void
    {
        if(!in_array($slot,['proposal','final_pdf','signed_pdf','signature_audit'],true)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',(string)($file['file_id']??''))||!preg_match('/^[a-f0-9]{64}$/D',(string)($file['sha256']??'')))throw new RuntimeException('Documento contrattuale non verificato');
        $this->locked(function()use($id,$slot,$file){$p=$this->practice($id,true);$this->open($p);
            $old=$this->artifact($id,$slot);
            if($old){if($old!==$file)throw new RuntimeException('Documento contrattuale già fissato');return;}
            $this->query('INSERT INTO onboarding_artifacts(practice_id,slot,metadata) VALUES(?,?,?)',[$id,$slot,$this->json($file)]);
        });
    }
    public function recordEvent(string $id,string $kind,array $payload): void
    {
        $this->practice($id);
        $this->query('INSERT INTO onboarding_events(event_key,practice_id,kind,payload,status,created_at) VALUES(?,?,?,?,?,?)',['onboarding:'.$id.':'.$kind.':'.bin2hex(random_bytes(8)),$id,$kind,$this->json($payload),'recorded',gmdate('c')]);
    }
    public function activationStart(string $id,string $today): string
    {
        OnboardingPolicy::date($today);
        return $this->locked(function()use($id,$today){$this->practice($id,true);$key='onboarding:'.$id.':activation-start';
            $old=$this->query('SELECT payload FROM onboarding_events WHERE event_key=?',[$key])->fetchColumn();
            if($old)return json_decode($old,true,512,JSON_THROW_ON_ERROR)['date'];
            $this->query('INSERT INTO onboarding_events(event_key,practice_id,kind,payload,status,created_at) VALUES(?,?,?,?,?,?)',[$key,$id,'activation_start',$this->json(['date'=>$today]),'recorded',gmdate('c')]);return $today;
        });
    }
    public function identityResult(string $id): array
    {
        $raw=$this->query('SELECT result FROM onboarding_identity_checks WHERE practice_id=?',[$id])->fetchColumn();return $raw?json_decode($raw,true,512,JSON_THROW_ON_ERROR):[];
    }
    /** Bound to immutable contract, the two actual fronts and declared identity fields. */
    private function identityFingerprint(string $id): string
    {
        $p=$this->practice($id);$details=$this->details($id);$files=$this->files($id);$fronts=[];
        foreach(['identity_front','tax_front'] as $slot){$f=$files[$slot]??[];$fronts[$slot]=['file_id'=>(string)($f['file_id']??''),'folder_id'=>(string)($f['folder_id']??''),'hash'=>(string)($f['hash']??'')];}
        return hash('sha256',$this->json(['reader'=>self::IDENTITY_READER_VERSION,'contract'=>$p['snapshot'],'fronts'=>$fronts,'tax_code'=>$details['tax_code']??'','birth_date'=>$details['profile']['birth_date']??'','document_expiry'=>$details['profile']['document_expiry']??'']));
    }
    private function identityJob(string $id): ?array
    {
        return $this->query('SELECT * FROM onboarding_identity_jobs WHERE practice_id=?',[$id])->fetch(PDO::FETCH_ASSOC)?:null;
    }
    /** This path performs no archive/provider calls and acknowledges a durable job. */
    public function queueIdentity(string $id,bool $retry=false,?int $now=null): array
    {
        $now??=time();return $this->locked(function()use($id,$retry,$now){$p=$this->practice($id,true);$this->open($p);
            if(!in_array($p['state'],['invited','identity_review','identity_matched','minor_blocked'],true))return $this->identityVerification($id,$now);
            $files=$this->files($id);foreach(['identity_front','tax_front'] as $slot)if(empty($files[$slot]))throw new RuntimeException('Carica il fronte della carta d’identità e della tessera sanitaria o codice fiscale');
            $details=$this->details($id);if(empty($details['privacy_acknowledged_at']))throw new RuntimeException('Completa prima i dati e le informazioni sul trattamento');
            if(in_array($p['state'],['identity_matched','minor_blocked'],true))return $this->identityVerification($id,$now);
            $fingerprint=$this->identityFingerprint($id);$job=$this->identityJob($id);
            if($job&&hash_equals($job['input_fingerprint'],$fingerprint)){
                if(in_array($job['state'],['complete','rejected','queued'],true)||($job['state']==='processing'&&(int)$job['lease_until']>$now))return $this->identityVerification($id,$now);
                if(in_array($job['state'],['retry_wait','error'],true)&&(!$retry||(int)$job['next_retry_at']>$now))return $this->identityVerification($id,$now);
                $this->query("UPDATE onboarding_identity_jobs SET state='queued',claim_token=NULL,lease_until=0,next_retry_at=0,reason='',queued_at=?,finished_at=NULL WHERE practice_id=?",[gmdate('c',$now),$id]);
            }else{
                $this->query('DELETE FROM onboarding_identity_jobs WHERE practice_id=?',[$id]);
                $this->query('INSERT INTO onboarding_identity_jobs(practice_id,input_fingerprint,state,queued_at) VALUES(?,?,?,?)',[$id,$fingerprint,'queued',gmdate('c',$now)]);
            }
            return $this->identityVerification($id,$now);
        });
    }
    public function identityVerification(string $id,?int $now=null): ?array
    {
        $now??=time();$p=$this->practice($id);if($p['cancelled_at'])return null;$job=$this->identityJob($id);$result=$this->identityResult($id);
        if($job&&!hash_equals($job['input_fingerprint'],$this->identityFingerprint($id))){
            if(in_array($result['status']??'',['matched','blocked'],true))$job=null;
            else return ['status'=>'error','reason'=>'verification_updated','message'=>'La verifica è stata aggiornata. I dati e i documenti sono salvati: premi «Salva e continua» per riprenderla.','retryable'=>true,'replace_slots'=>[],'result_status'=>null,'queued_at'=>null,'started_at'=>null,'next_retry_at'=>null,'retry_after_seconds'=>0];
        }
        if(!$job&&!$result)return null;
        $state=$job['state']??(($result['status']??'')==='matched'?'complete':'rejected');
        if($state==='processing'&&(int)$job['lease_until']<=$now)$state='queued';
        $reason=$job['reason']??($result['reason']??'');$replace=[];$retryable=in_array($state,['retry_wait','error'],true)&&$reason!=='proposal_expired';
        $messages=[
            'identity_document_required'=>'Il file della carta d’identità non mostra il documento richiesto. Sostituiscilo con una foto completa e leggibile del fronte.',
            'tax_document_required'=>'Il file fiscale non mostra il documento richiesto. Sostituiscilo con una foto completa e leggibile del fronte della tessera sanitaria o del tesserino codice fiscale.',
            'documents_required'=>'I file caricati non mostrano i documenti richiesti. Carica il fronte della carta d’identità e il fronte della tessera sanitaria o del tesserino codice fiscale.',
            'document_unreadable'=>'Non riusciamo a leggere il fronte della carta d’identità. Carica una foto completa, nitida e senza riflessi.',
            'birth_date_unreadable'=>'La data di nascita sulla carta d’identità non è leggibile. Carica una foto più nitida del fronte.',
            'birth_date_invalid'=>'La data di nascita sul documento non è verificabile. Controlla di aver caricato il fronte completo della carta d’identità.',
            'identity_expiry_unreadable'=>'La scadenza della carta d’identità non è leggibile. Carica una foto completa e nitida del fronte.',
            'identity_expired'=>'La carta d’identità risulta scaduta. Sostituiscila con il fronte di un documento valido.',
            'name_mismatch'=>'Nome e cognome della carta d’identità non coincidono con la proposta. Controlla il documento caricato; se la proposta contiene un errore, rispondi all’email di TRB rec.',
            'tax_document_unreadable'=>'Non riusciamo a leggere il documento fiscale. Carica una foto completa e nitida del fronte della tessera sanitaria o del tesserino codice fiscale.',
            'tax_identity_mismatch'=>'I dati del documento fiscale non coincidono con quelli della carta d’identità. Controlla di aver caricato i tuoi due documenti.',
            'tax_code_mismatch'=>'Il codice fiscale inserito non coincide con quello del documento. Controlla il dato nel modulo e il fronte del documento fiscale caricato.',
            'declared_birth_date_mismatch'=>'La data di nascita inserita non coincide con quella della carta d’identità. Correggila nel modulo e continua.',
            'declared_document_expiry_mismatch'=>'La scadenza inserita non coincide con quella della carta d’identità. Correggila nel modulo e continua.',
            'minor'=>'La sottoscrizione di questo contratto è riservata ai maggiorenni. Per assistenza, rispondi all’email di TRB rec.',
            'verification_unavailable'=>'La verifica non è riuscita per un problema temporaneo. I dati e i documenti caricati sono conservati.',
            'proposal_expired'=>'La proposta è scaduta. Per ricevere una nuova proposta, rispondi all’email di TRB rec. I dati e i documenti già caricati restano conservati.',
        ];
        if(in_array($reason,['identity_document_required','document_unreadable','birth_date_unreadable','birth_date_invalid','identity_expiry_unreadable','identity_expired','name_mismatch'],true))$replace=['identity_front'];
        if(in_array($reason,['tax_document_required','tax_document_unreadable','tax_identity_mismatch','tax_code_mismatch'],true))$replace=['tax_front'];
        if($reason==='documents_required')$replace=['identity_front','tax_front'];
        $message=$messages[$reason]??'Controlla i dati e i documenti richiesti per continuare.';
        if($state==='queued')$message='Documenti ricevuti. La verifica inizierà tra pochi istanti: non occorre inviarli di nuovo.';
        if($state==='processing')$message='Stiamo verificando che i dati dei documenti coincidano con quelli della proposta. Puoi attendere qui; dati e documenti sono già salvati.';
        if($state==='retry_wait')$message='La verifica ha incontrato un problema temporaneo e verrà ripetuta a breve. I tuoi dati e documenti sono già salvati.';
        if($state==='error'&&$retryable)$message.=' Quando il pulsante sarà disponibile, premi “Riprova la verifica”. Non occorre ricaricare i documenti.';
        if($state==='complete')$message='Dati verificati. Puoi continuare con la fase successiva.';
        $next=(int)($job['next_retry_at']??0);$after=max(0,$next-$now);if($state==='queued')$after=1;
        if($state==='processing')$after=max(1,(int)$job['lease_until']-$now);
        return ['status'=>$state,'reason'=>$reason,'message'=>$message,'retryable'=>$retryable,'replace_slots'=>$replace,'result_status'=>$state==='complete'?'matched':($state==='rejected'?($result['status']??'review'):null),'queued_at'=>$job['queued_at']??null,'started_at'=>$job['started_at']??null,'next_retry_at'=>$next?gmdate('c',$next):null,'retry_after_seconds'=>$after];
    }
    /** A lease and random claim make simultaneous workers and stale completions harmless. */
    public function claimIdentity(?string $onlyId=null,?int $now=null): ?array
    {
        $now??=time();$where=$onlyId?' AND p.id=?':'';$args=[$now,$now];if($onlyId)$args[]=$onlyId;
        $ids=$this->query("SELECT j.practice_id FROM onboarding_identity_jobs j JOIN onboarding_practices p ON p.id=j.practice_id WHERE p.cancelled_at IS NULL AND p.state IN ('invited','identity_review') AND (j.state='queued' OR (j.state='processing' AND j.lease_until<=?) OR (j.state='retry_wait' AND j.next_retry_at<=?))".$where." ORDER BY j.queued_at,j.practice_id LIMIT 20",$args)->fetchAll(PDO::FETCH_COLUMN);
        foreach($ids as $id){$claimed=$this->locked(function()use($id,$now){$p=$this->practice($id,true);$job=$this->identityJob($id);if(!$job||$p['cancelled_at']||!in_array($p['state'],['invited','identity_review'],true))return null;
                if(strtotime($p['expires_at'])<=$now){$this->query("UPDATE onboarding_identity_jobs SET state='error',reason='proposal_expired',claim_token=NULL,lease_until=0,next_retry_at=0,finished_at=? WHERE practice_id=?",[gmdate('c',$now),$id]);return null;}
                $due=$job['state']==='queued'||($job['state']==='processing'&&(int)$job['lease_until']<=$now)||($job['state']==='retry_wait'&&(int)$job['next_retry_at']<=$now);
                if(!$due||!hash_equals($job['input_fingerprint'],$this->identityFingerprint($id)))return null;
                if((int)$job['attempts']>=3&&$job['state']==='processing'){$this->query("UPDATE onboarding_identity_jobs SET state='error',reason='verification_unavailable',claim_token=NULL,lease_until=0,next_retry_at=? WHERE practice_id=?",[$now+60,$id]);return null;}
                $token=bin2hex(random_bytes(32));$this->query("UPDATE onboarding_identity_jobs SET state='processing',attempts=attempts+1,claim_token=?,lease_until=?,next_retry_at=0,started_at=?,finished_at=NULL WHERE practice_id=?",[$token,$now+240,gmdate('c',$now),$id]);
                return ['practice_id'=>$id,'claim_token'=>$token,'input_fingerprint'=>$job['input_fingerprint'],'files'=>$this->files($id)];
            });if($claimed)return $claimed;
        }return null;
    }
    public function finishIdentity(array $claim,array $fields,string $today,?int $now=null): bool
    {
        $now??=time();return $this->locked(function()use($claim,$fields,$today,$now){$id=$claim['practice_id'];$p=$this->practice($id,true);$job=$this->identityJob($id);
            if(!$this->validIdentityClaim($p,$job,$claim))return false;
            $result=$this->recordIdentityUnlocked($p,$fields,$today);
            $this->query('UPDATE onboarding_identity_jobs SET state=?,reason=?,claim_token=NULL,lease_until=0,next_retry_at=0,finished_at=? WHERE practice_id=?',[$result['status']==='matched'?'complete':'rejected',$result['reason'],gmdate('c',$now),$id]);return true;
        });
    }
    public function failIdentity(array $claim,?int $now=null): bool
    {
        $now??=time();return $this->locked(function()use($claim,$now){$p=$this->practice($claim['practice_id'],true);$job=$this->identityJob($claim['practice_id']);if(!$this->validIdentityClaim($p,$job,$claim))return false;
            $automatic=(int)$job['attempts']<3;$delay=$automatic?min(120,15*(2**max(0,(int)$job['attempts']-1))):60;
            $this->query("UPDATE onboarding_identity_jobs SET state=?,reason='verification_unavailable',claim_token=NULL,lease_until=0,next_retry_at=?,finished_at=? WHERE practice_id=?",[$automatic?'retry_wait':'error',$now+$delay,gmdate('c',$now),$claim['practice_id']]);return true;
        });
    }
    private function validIdentityClaim(array $p,?array $job,array $claim): bool
    {
        return !$p['cancelled_at']&&in_array($p['state'],['invited','identity_review'],true)&&$job&&$job['state']==='processing'&&is_string($job['claim_token'])&&hash_equals($job['claim_token'],(string)($claim['claim_token']??''))&&hash_equals($job['input_fingerprint'],(string)($claim['input_fingerprint']??''))&&hash_equals($job['input_fingerprint'],$this->identityFingerprint($p['id']));
    }
    private function invalidateIdentity(string $id): void
    {
        $this->query('DELETE FROM onboarding_identity_jobs WHERE practice_id=?',[$id]);$this->query('DELETE FROM onboarding_identity_checks WHERE practice_id=?',[$id]);
    }
    public function cancel(string $id,array $owner): void
    {
        if(($owner['role']??'')!=='admin'||empty($owner['id']))throw new RuntimeException('Permessi insufficienti');
        $this->locked(function()use($id,$owner){$p=$this->practice($id,true);if($p['cancelled_at'])return;
            $this->query("UPDATE onboarding_practices SET cancelled_at=?,state='cancelled' WHERE id=?",[gmdate('c'),$id]);
            $this->recordEvent($id,'cancelled',['owner_id'=>(int)$owner['id']]);
        });
    }
    public function expireSessions(): void
    {
        $this->query('DELETE FROM onboarding_sessions WHERE expires_at<?',[time()]);
        $this->query('DELETE FROM onboarding_email_challenges WHERE expires_at<?',[time()]);
    }
    public function workerCandidates(): array
    {
        return array_map(fn($id)=>$this->practice($id),$this->query("SELECT p.id FROM onboarding_practices p LEFT JOIN onboarding_worker_checks w ON w.practice_id=p.id WHERE p.cancelled_at IS NULL ORDER BY COALESCE(w.checked_at,''),p.id LIMIT 20")->fetchAll(PDO::FETCH_COLUMN));
    }
    public function checked(string $id): void
    {
        $this->locked(function()use($id){$this->practice($id,true);$this->query('DELETE FROM onboarding_worker_checks WHERE practice_id=?',[$id]);$this->query('INSERT INTO onboarding_worker_checks(practice_id,checked_at) VALUES(?,?)',[$id,gmdate('c')]);});
    }
    public function preparation(int $contractId): ?array
    {
        $raw=$this->query('SELECT metadata FROM onboarding_preparations WHERE contract_id=?',[$contractId])->fetchColumn();return $raw?json_decode($raw,true,512,JSON_THROW_ON_ERROR):null;
    }
    public function savePreparation(int $contractId,array $data): void
    {
        $this->query('INSERT INTO onboarding_preparations(contract_id,metadata) VALUES(?,?)',[$contractId,$this->json($data)]);
    }
    public function renewPreparationUpload(int $contractId,array $grant): void
    {
        $data=$this->preparation($contractId);if(!$data)throw new RuntimeException('Preparazione mancante');$data['grant']=$grant;
        $this->query('UPDATE onboarding_preparations SET metadata=? WHERE contract_id=?',[$this->json($data),$contractId]);
    }
    public function forContract(int $contractId): ?array
    {
        $id=$this->query('SELECT id FROM onboarding_practices WHERE contract_id=?',[$contractId])->fetchColumn();return $id?$this->practice((string)$id):null;
    }
    public function create(int $contractId,array $snapshot,string $expiresAt,?string $preparedToken=null,?string $preparedId=null,?array $proposal=null): array
    {
        if ($contractId<1 || ($snapshot['onboarding_version']??'')!==OnboardingPolicy::VERSION || !preg_match('/^[a-f0-9]{64}$/D',(string)($snapshot['unsigned_document_sha256']??'')) || empty($snapshot['first_name']) || empty($snapshot['last_name']) || !filter_var($snapshot['email']??'',FILTER_VALIDATE_EMAIL) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D',(string)($snapshot['artist_folder_id']??''))) throw new RuntimeException('Proposta incompleta');
        if (!in_array($snapshot['group_code']??'', ['DDS','DDB','DDB12','DDB-TRB','TRB'], true)) throw new RuntimeException('Tipologia contratto non valida');
        foreach($snapshot['plans']??[] as $plan) OnboardingPolicy::validatePlan($plan);
        if(empty($snapshot['plans'])) throw new RuntimeException('Formule di versamento mancanti');
        if(strtotime($expiresAt)===false||strtotime($expiresAt)<=time()) throw new RuntimeException('Scadenza proposta non valida');
        $token=$preparedToken??bin2hex(random_bytes(32));if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('Invito non valido');$id=$preparedId??bin2hex(random_bytes(16));if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new RuntimeException('Identificativo non valido');
        if($proposal&&(!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',(string)($proposal['file_id']??''))||!hash_equals($snapshot['unsigned_document_sha256'],(string)($proposal['sha256']??''))))throw new RuntimeException('Proposta non verificata');
        $this->locked(function()use($id,$contractId,$snapshot,$token,$expiresAt,$proposal){
            $this->query('INSERT INTO onboarding_practices(id,contract_id,onboarding_version,email,snapshot,state,token_hash,expires_at,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$id,$contractId,OnboardingPolicy::VERSION,$snapshot['email'],$this->json($snapshot),'invited',hash('sha256',$token),$expiresAt,gmdate('c')]);
            if($proposal)$this->query('INSERT INTO onboarding_artifacts(practice_id,slot,metadata) VALUES(?,?,?)',[$id,'proposal',$this->json($proposal)]);
        });
        return ['id'=>$id,'token'=>$token]; // Persist only its hash. Never put this result into logs.
    }
    public function fromToken(string $token): array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('Collegamento non valido');
        $id=$this->query('SELECT id FROM onboarding_practices WHERE token_hash=?',[hash('sha256',$token)])->fetchColumn();
        if(!$id)throw new RuntimeException('Collegamento non valido');$p=$this->practice($id);
        $this->open($p);
        return $p;
    }
    private function open(array $p): void
    {
        if($p['cancelled_at'] || (!$p['first_payment_date'] && !$p['signed_at'] && strtotime($p['expires_at'])<time()))throw new RuntimeException('Proposta scaduta o annullata');
    }
    /** The invite alone never discloses personal data. Mail delivery receives the code once. */
    public function challenge(string $token): array
    {
        $p=$this->fromToken($token);
        return $this->locked(function()use($p){$this->practice($p['id'],true);
            $old=$this->query('SELECT issued_at FROM onboarding_email_challenges WHERE practice_id=?',[$p['id']])->fetchColumn();
            if($old!==false && (int)$old>time()-120)throw new RuntimeException('Attendi prima di richiedere un nuovo codice');
            $code=(string)random_int(100000,999999);$salt=bin2hex(random_bytes(16));
            $this->query('DELETE FROM onboarding_email_challenges WHERE practice_id=?',[$p['id']]);
            $this->query('INSERT INTO onboarding_email_challenges(practice_id,code_hash,expires_at,issued_at) VALUES(?,?,?,?)',[$p['id'],hash('sha256',$salt.'|'.$code),time()+600,time()]);
            return ['practice_id'=>$p['id'],'email'=>$p['email'],'code'=>$code,'salt'=>$salt];
        });
    }
    /** Failed attempts commit their counter, instead of being undone by an exception. */
    public function verifyEmail(string $token,string $code,string $salt): string
    {
        $p=$this->fromToken($token);
        $session=$this->locked(function()use($p,$code,$salt){$this->practice($p['id'],true);
            $row=$this->query('SELECT * FROM onboarding_email_challenges WHERE practice_id=?',[$p['id']])->fetch(PDO::FETCH_ASSOC);
            if(!$row || (int)$row['expires_at']<time() || (int)$row['attempts']>=5)return null;
            $this->query('UPDATE onboarding_email_challenges SET attempts=attempts+1 WHERE practice_id=?',[$p['id']]);
            if(!preg_match('/^[0-9]{6}$/D',$code)||!preg_match('/^[a-f0-9]{32}$/D',$salt)||!hash_equals($row['code_hash'],hash('sha256',$salt.'|'.$code)))return null;
            $session=bin2hex(random_bytes(32));
            $this->query('DELETE FROM onboarding_email_challenges WHERE practice_id=?',[$p['id']]);
            $this->query('INSERT INTO onboarding_sessions(token_hash,practice_id,expires_at) VALUES(?,?,?)',[hash('sha256',$session),$p['id'],time()+28800]);
            return $session;
        });
        if(!$session)throw new RuntimeException('Codice non valido o scaduto');
        return $session;
    }
    public function fromSession(string $session): array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$session))throw new RuntimeException('Verifica email necessaria');
        $id=$this->query('SELECT practice_id FROM onboarding_sessions WHERE token_hash=? AND expires_at>=?',[hash('sha256',$session),time()])->fetchColumn();
        if(!$id)throw new RuntimeException('Verifica email necessaria');$p=$this->practice((string)$id);$this->open($p);return $p;
    }
    public function recordFile(string $id,string $slot,array $file): void
    {
        if(!in_array($slot,['identity_front','identity_back','tax_front','tax_back'],true)||empty($file['file_id'])||empty($file['folder_id'])||empty($file['hash']))throw new RuntimeException('Documento archivio incompleto');
        $this->locked(function()use($id,$slot,$file){$p=$this->practice($id,true);$this->open($p);
            if(!in_array($p['state'],['invited','identity_review'],true))throw new RuntimeException('Documenti già confermati: modifica soggetta a revisione');
            $previous=$this->files($id)[$slot]??null;
            $this->query('DELETE FROM onboarding_files WHERE practice_id=? AND slot=?',[$id,$slot]);
            $this->query('INSERT INTO onboarding_files(practice_id,slot,folder_id,pcloud_file_id,metadata) VALUES(?,?,?,?,?)',[$id,$slot,$file['folder_id'],$file['file_id'],$this->json($file)]);
            if(in_array($slot,['identity_front','tax_front'],true)&&(!$previous||(string)$previous['file_id']!==(string)$file['file_id']||(string)$previous['folder_id']!==(string)$file['folder_id']||(string)$previous['hash']!==(string)$file['hash']))$this->invalidateIdentity($id);
        });
    }
    public function files(string $id): array
    {
        $out=[];foreach($this->query('SELECT slot,metadata FROM onboarding_files WHERE practice_id=?',[$id])->fetchAll(PDO::FETCH_ASSOC) as $row)$out[$row['slot']]=json_decode($row['metadata'],true,512,JSON_THROW_ON_ERROR);return $out;
    }
    public function nonce(string $value): void
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$value))throw new RuntimeException('Richiesta non valida');
        $this->query('DELETE FROM onboarding_nonces WHERE expires_at<?',[time()]);
        try{$this->query('INSERT INTO onboarding_nonces(nonce,expires_at) VALUES(?,?)',[$value,time()+600]);}
        catch(\PDOException $e){throw new RuntimeException('Richiesta già utilizzata');}
    }
    public function upload(string $id,string $slot): ?array
    {
        $raw=$this->query('SELECT grant_data FROM onboarding_uploads WHERE practice_id=? AND slot=?',[$id,$slot])->fetchColumn();return $raw?json_decode($raw,true,512,JSON_THROW_ON_ERROR):null;
    }
    public function saveUpload(string $id,string $slot,array $grant): void
    {
        $this->locked(function()use($id,$slot,$grant){$p=$this->practice($id,true);$this->open($p);
            $signatureSlot=in_array($slot,['signed_pdf','signature_audit','final_pdf'],true);
            if(!in_array($slot,['identity_front','identity_back','tax_front','tax_back','signed_pdf','signature_audit','final_pdf'],true)||!in_array($p['state'],$signatureSlot?($slot==='final_pdf'?['signature_ready','signature_pending']:['signature_pending']):['invited','identity_review'],true))throw new RuntimeException('Caricamento non disponibile');
            $this->query('DELETE FROM onboarding_uploads WHERE practice_id=? AND slot=?',[$id,$slot]);
            $this->query('INSERT INTO onboarding_uploads(practice_id,slot,grant_data) VALUES(?,?,?)',[$id,$slot,$this->json($grant)]);
        });
    }
    public function details(string $id): array
    {
        $raw=$this->query('SELECT details FROM onboarding_details WHERE practice_id=?',[$id])->fetchColumn();return $raw?json_decode($raw,true,512,JSON_THROW_ON_ERROR):[];
    }
    public function saveDetails(string $id,array $details): void
    {
        $this->locked(function()use($id,$details){$p=$this->practice($id,true);$this->open($p);
            if(!in_array($p['state'],['invited','identity_review','identity_matched'],true))throw new RuntimeException('Dati amministrativi già confermati');
            if($p['state']==='identity_matched'){
                $verified=$this->details($id);
                foreach(['billing','profile','tax_code','invoice','privacy_acknowledged_at','privacy_version'] as $field)if(($verified[$field]??null)!==($details[$field]??null))throw new RuntimeException('Dati verificati: modifica soggetta a revisione');
            }
            $before=$this->identityFingerprint($id);
            $this->query('DELETE FROM onboarding_details WHERE practice_id=?',[$id]);$this->query('INSERT INTO onboarding_details(practice_id,details) VALUES(?,?)',[$id,$this->json($details)]);
            if(!hash_equals($before,$this->identityFingerprint($id)))$this->invalidateIdentity($id);
        });
    }
    public function rememberOrder(string $id,int $number,int $orderId): void
    {
        if($number<1||$orderId<1)throw new RuntimeException('Ordine non valido');
        $this->locked(function()use($id,$number,$orderId){$this->practice($id,true);$old=$this->query('SELECT practice_id,installment_number FROM onboarding_orders WHERE order_id=?',[$orderId])->fetch(PDO::FETCH_ASSOC);
            if($old){if($old['practice_id']!==$id || (int)$old['installment_number']!==$number)throw new RuntimeException('Ordine associato a una pratica diversa');return;}
            $this->query('INSERT INTO onboarding_orders(practice_id,order_id,installment_number,created_at) VALUES(?,?,?,?)',[$id,$orderId,$number,gmdate('c')]);
        });
    }
    public function orders(?string $id=null): array
    {
        return $this->query('SELECT o.* FROM onboarding_orders o JOIN onboarding_practices p ON p.id=o.practice_id WHERE p.cancelled_at IS NULL'.($id?' AND o.practice_id=?':'').' ORDER BY o.created_at,o.order_id',$id?[$id]:[])->fetchAll(PDO::FETCH_ASSOC);
    }
    public function signature(string $id): ?array
    {
        return $this->query('SELECT * FROM onboarding_signatures WHERE practice_id=?',[$id])->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public function payment(string $provider,string $transaction): ?array
    {
        return $this->query('SELECT * FROM onboarding_payments WHERE provider=? AND transaction_id=?',[$provider,$transaction])->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public function portalPractice(int $userId,string $email): array
    {
        $id=$this->query("SELECT p.id FROM onboarding_practices p JOIN onboarding_events e ON e.practice_id=p.id WHERE e.kind='portal_activated' AND e.payload=? AND LOWER(p.email)=?",[$this->json(['portal_user_id'=>$userId]),mb_strtolower($email)])->fetchColumn();
        if(!$id)throw new RuntimeException('Account non associato all’adesione');return $this->practice((string)$id);
    }
    public function recordIdentity(string $id,array $fields,string $today): array
    {
        return $this->locked(function()use($id,$fields,$today){$p=$this->practice($id,true);$this->open($p);
            $result=$this->recordIdentityUnlocked($p,$fields,$today);$this->query('DELETE FROM onboarding_identity_jobs WHERE practice_id=?',[$id]);return $result;
        });
    }
    private function recordIdentityUnlocked(array $p,array $fields,string $today): array
    {
            $id=$p['id'];$this->open($p);
            if(!in_array($p['state'],['invited','identity_review'],true))throw new RuntimeException('Verifica documenti non disponibile');
            $files=$this->files($id);foreach(['identity_front','tax_front'] as $slot)if(empty($files[$slot]))throw new RuntimeException('Completa il fronte della carta d’identità e della tessera sanitaria o codice fiscale');
            $details=$this->details($id);if(empty($details['privacy_acknowledged_at']))throw new RuntimeException('Completa prima i dati e le informazioni sul trattamento');
            $result=OnboardingPolicy::documents($p['snapshot'],$fields,(string)($details['tax_code']??''),$today);
            if($result['status']==='matched')foreach(['birth_date'=>'birth_date','document_expiry'=>'expiry_date'] as $declared=>$read){
                if(!empty($details['profile'][$declared])&&$details['profile'][$declared]!==($fields[$read]??'')){$result=['status'=>'review','reason'=>'declared_'.$declared.'_mismatch'];break;}
            }
            $this->query('DELETE FROM onboarding_identity_checks WHERE practice_id=?',[$id]);
            $this->query('INSERT INTO onboarding_identity_checks(practice_id,result,document_fingerprint,checked_at) VALUES(?,?,?,?)',[$id,$this->json($result),hash('sha256',$this->json($files)),gmdate('c')]);
            $state=['matched'=>'identity_matched','blocked'=>'minor_blocked','review'=>'identity_review'][$result['status']];
            $this->query('UPDATE onboarding_practices SET state=? WHERE id=?',[$state,$id]);return $result;
    }
    /** The merchant call is inside this lock, including its durable dispatch barrier. */
    public function paymentOperation(string $id,callable $operation): array
    {
        if($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return $operation();
        $key='trb_onboarding_payment_'.substr(hash('sha256',$id),0,32);
        if((int)$this->query('SELECT GET_LOCK(?,3)',[$key])->fetchColumn()!==1)throw new RuntimeException('Operazione sul versamento in corso. Attendi qualche secondo e riprova.');
        try{return $operation();}finally{$this->query('SELECT RELEASE_LOCK(?)',[$key]);}
    }
    public function canChangePlan(array $p): bool
    {
        if($p['state']!=='payment_pending'||!$p['selected_plan']||$p['selected_plan']['kind']==='free'||count($p['snapshot']['plans'])<2||$p['cancelled_at']||$p['first_payment_date']||$p['owner_approved_at']||$p['signed_at']||$p['portal_activated_at'])return false;
        foreach(['onboarding_orders','onboarding_payments','onboarding_installments','onboarding_signatures','onboarding_holds'] as $table)if($this->query('SELECT 1 FROM '.$table.' WHERE practice_id=? LIMIT 1',[$p['id']])->fetchColumn())return false;
        if($this->query("SELECT 1 FROM onboarding_events WHERE practice_id=? AND kind='checkout_started' LIMIT 1",[$p['id']])->fetchColumn())return false;
        return !$this->artifact($p['id'],'final_pdf')&&!$this->artifact($p['id'],'signed_pdf');
    }
    public function choosePlan(string $id,string $key,?array $acknowledgement=null): array
    {
        return $this->locked(function()use($id,$key,$acknowledgement){$p=$this->practice($id,true);
            $this->open($p);
            $plan=$p['snapshot']['plans'][$key]??null;if(!is_array($plan))throw new RuntimeException('Formula non prevista dalla proposta');
            OnboardingPolicy::validatePlan($plan);
            if($p['selected_plan']===$plan)return $p;
            $changing=$p['selected_plan']!==null;
            if($changing&&!$this->canChangePlan($p))throw new RuntimeException('Il pagamento è già stato avviato. La formula resta confermata per evitare versamenti con importi diversi.');
            if(!$changing&&$p['state']!=='identity_matched')throw new RuntimeException('Verifica documenti necessaria prima del versamento');
            $this->query('UPDATE onboarding_practices SET selected_plan=?,state=? WHERE id=?',[$this->json($plan),$plan['kind']==='free'?'owner_review':'payment_pending',$id]);
            if($acknowledgement!==null){
                if(($acknowledgement['plan_key']??'')!==$key||!hash_equals($p['snapshot']['unsigned_document_sha256'],(string)($acknowledgement['proposal_read_sha256']??'')))throw new RuntimeException('Conferma della formula non valida');
                $details=array_replace($this->details($id),array_intersect_key($acknowledgement,array_flip(['proposal_read_at','proposal_read_sha256','plan_key'])));
                $this->query('DELETE FROM onboarding_details WHERE practice_id=?',[$id]);$this->query('INSERT INTO onboarding_details(practice_id,details) VALUES(?,?)',[$id,$this->json($details)]);
            }
            if($changing)$this->recordEvent($id,'plan_changed',['previous_kind'=>$p['selected_plan']['kind'],'previous_total_cents'=>$p['selected_plan']['total_cents'],'plan_key'=>$key,'kind'=>$plan['kind'],'total_cents'=>$plan['total_cents']]);
            if($plan['kind']==='free')$this->ownerReviewEvent($id);
            return $this->practice($id);
        });
    }
    public function installments(string $id): array
    {
        return $this->query("SELECT i.*,COALESCE((SELECT SUM(p.amount_cents-p.refunded_cents) FROM onboarding_payments p WHERE p.practice_id=i.practice_id AND p.installment_number=i.number AND p.status IN ('confirmed','partially_refunded')),0) confirmed_cents FROM onboarding_installments i WHERE i.practice_id=? ORDER BY i.number",[$id])->fetchAll(PDO::FETCH_ASSOC);
    }
    /** Caller verifies provider on its server; no public browser confirmation is accepted. */
    public function confirmedPayment(string $id,int $number,array $proof): array
    {
        return $this->locked(function()use($id,$number,$proof){$p=$this->practice($id,true);
            if($p['cancelled_at']||!$p['selected_plan'])throw new RuntimeException('Adesione annullata o formula non selezionata');
            foreach(['provider','transaction_id','amount_cents','currency','paid_on','status'] as $key)if(!isset($proof[$key]))throw new RuntimeException('Conferma pagamento incompleta');
            if(($proof['status']!=='confirmed')||$proof['currency']!=='EUR'||!is_int($proof['amount_cents'])||$number<1)throw new RuntimeException('Pagamento non confermato');
            OnboardingPolicy::date($proof['paid_on']);
            if(!in_array($proof['provider'],['woocommerce'],true)||!preg_match('/^[A-Za-z0-9:_-]{1,128}$/D',$proof['transaction_id']))throw new RuntimeException('Riferimento provider non valido');
            $old=$this->query('SELECT * FROM onboarding_payments WHERE provider=? AND transaction_id=?',[$proof['provider'],$proof['transaction_id']])->fetch(PDO::FETCH_ASSOC);
            if($old){if($old['practice_id']!==$id||(int)$old['installment_number']!==$number||(int)$old['amount_cents']!==$proof['amount_cents']||$old['status']!=='confirmed')throw new RuntimeException('Conferma già associata o stornata');return $this->practice($id);}
            if(!$p['first_payment_date']){
                if($number!==1||$p['state']!=='payment_pending')throw new RuntimeException('Prima rata non disponibile');
                $schedule=OnboardingPolicy::schedule($p['selected_plan'],$proof['paid_on']);
                foreach($schedule as $row)$this->query('INSERT INTO onboarding_installments(practice_id,number,due_date,amount_cents) VALUES(?,?,?,?)',[$id,$row['number'],$row['due_date'],$row['amount_cents']]);
            }
            $rows=$this->installments($id);$row=null;foreach($rows as $item)if((int)$item['number']===$number)$row=$item;
            if(!$row||$proof['amount_cents']!==(int)$row['amount_cents']-(int)$row['confirmed_cents'])throw new RuntimeException('Importo diverso dalla rata dovuta');
            // Cannot pay an unrelated later installment while earlier dues remain unpaid.
            foreach($rows as $item)if((int)$item['number']<$number&&(int)$item['confirmed_cents']<(int)$item['amount_cents'])throw new RuntimeException('Salda prima la rata precedente');
            $this->query('INSERT INTO onboarding_payments(provider,transaction_id,practice_id,installment_number,amount_cents,currency,status,confirmed_at) VALUES(?,?,?,?,?,?,?,?)',[$proof['provider'],$proof['transaction_id'],$id,$number,$proof['amount_cents'],'EUR','confirmed',gmdate('c')]);
            if(!$p['first_payment_date']){$this->query('UPDATE onboarding_practices SET first_payment_date=?,state=? WHERE id=?',[$proof['paid_on'],'owner_review',$id]);$this->ownerReviewEvent($id);}
            return $this->practice($id);
        });
    }
    /** Cumulative refund/reversal reopens only the refunded debt; never moves a due date. */
    public function reversePayment(string $provider,string $transactionId,?int $cumulativeRefundCents=null): void
    {
        $this->locked(function()use($provider,$transactionId,$cumulativeRefundCents){$payment=$this->query('SELECT * FROM onboarding_payments WHERE provider=? AND transaction_id=?',[$provider,$transactionId])->fetch(PDO::FETCH_ASSOC);
            if(!$payment)throw new RuntimeException('Pagamento non presente');$this->practice($payment['practice_id'],true);
            $refund=$cumulativeRefundCents??(int)$payment['amount_cents'];
            if($refund<1 || $refund>(int)$payment['amount_cents'] || $refund<(int)$payment['refunded_cents'])throw new RuntimeException('Storno incoerente con il pagamento');
            $this->query('UPDATE onboarding_payments SET status=?,refunded_cents=? WHERE provider=? AND transaction_id=?',[$refund===(int)$payment['amount_cents']?'reversed':'partially_refunded',$refund,$provider,$transactionId]);
        });
    }
    private function readyForSignature(array $p,string $today): void
    {
        $this->open($p);
        if($this->query('SELECT 1 FROM onboarding_holds WHERE practice_id=?',[$p['id']])->fetchColumn())throw new RuntimeException('Pratica sospesa per verifica amministrativa');
        if(!$p['selected_plan'] || !in_array($p['state'],['owner_review','signature_ready','signature_pending'],true))throw new RuntimeException('Pratica non pronta per approvazione e firma');
        $check=$this->query('SELECT result,document_fingerprint FROM onboarding_identity_checks WHERE practice_id=?',[$p['id']])->fetch(PDO::FETCH_ASSOC);
        if(!$check || (json_decode($check['result'],true)['status']??'')!=='matched' || !hash_equals($check['document_fingerprint'],hash('sha256',$this->json($this->files($p['id'])))))throw new RuntimeException('Documenti da verificare');
        if($p['selected_plan']['kind']!=='free'){
            $this->extendRecurring($p['id'],$today);$rows=$this->installments($p['id']);
            if(!$rows || (int)$rows[0]['confirmed_cents']<(int)$rows[0]['amount_cents'])throw new RuntimeException('Primo versamento non confermato');
            foreach($rows as $row)if($row['due_date']<$today && (int)$row['confirmed_cents']<(int)$row['amount_cents'])throw new RuntimeException('Quota scaduta: regolarizza prima della firma');
        }
    }
    /** Resolve only the owner's confirmed, reviewed proposal delivery. No browser fields. */
    public function automaticSignatureOwner(string $id): ?array
    {
        $p=$this->practice($id);
        if($p['cancelled_at'])return null;
        $event=$this->query("SELECT payload FROM onboarding_events WHERE event_key=? AND practice_id=? AND kind='proposal_email' AND status='completed'",['onboarding:'.$id.':proposal-email',$id])->fetchColumn();
        if(!$event)return null;
        $proof=json_decode((string)$event,true,512,JSON_THROW_ON_ERROR);$artifact=$this->artifact($id,'proposal');
        if(($proof['automatic_signature']??false)!==true||empty($proof['owner_id'])||(int)($proof['contract_id']??0)!==(int)$p['contract_id']||empty($proof['gmail_message_id'])||empty($proof['message_id'])||!$artifact
            ||!hash_equals($p['snapshot']['unsigned_document_sha256'],(string)($proof['document_sha256']??''))||!hash_equals($artifact['sha256'],(string)($proof['document_sha256']??''))
            ||!hash_equals(hash('sha256',$this->json($p['snapshot'])),(string)($proof['snapshot_sha256']??'')))return null;
        $owner=$this->query('SELECT id,role,email,is_active FROM users WHERE id=?',[(int)$proof['owner_id']])->fetch(PDO::FETCH_ASSOC);
        return $owner&&(int)$owner['is_active']===1&&$owner['role']==='admin'&&strcasecmp($owner['email'],'andrea.tognassi@trbrec.com')===0?$owner:null;
    }
    /** Caller supplies the authenticated owner or the bound server-side delivery authorization. */
    public function approve(string $id,array $owner,string $ownerEmail,string $today): void
    {
        if(($owner['role']??'')!=='admin'||empty($owner['id'])||!filter_var($ownerEmail,FILTER_VALIDATE_EMAIL)||strcasecmp((string)($owner['email']??''),$ownerEmail)!==0)throw new RuntimeException('Approvazione riservata al titolare');
        $this->locked(function()use($id,$owner,$today){$p=$this->practice($id,true);$this->readyForSignature($p,$today);
            if($p['owner_approved_at'])return;
            $this->query("UPDATE onboarding_practices SET owner_approved_at=?,state='signature_ready' WHERE id=?",[gmdate('c'),$id]);
            $this->query('INSERT INTO onboarding_events(event_key,practice_id,kind,payload,status,created_at) VALUES(?,?,?,?,?,?)',['onboarding:'.$id.':owner-approved',$id,'owner_approved',$this->json(['owner_id'=>(int)$owner['id']]),'recorded',gmdate('c')]);
        });
    }
    /** Reserve BEFORE the chargeable provider call. A crash is uncertain, never auto-retried. */
    public function reserveSignature(string $id,string $documentSha,string $appendixSha,string $today): array
    {
        foreach([$documentSha,$appendixSha] as $sha)if(!preg_match('/^[a-f0-9]{64}$/D',$sha))throw new RuntimeException('Documento definitivo non verificato');
        return $this->locked(function()use($id,$documentSha,$appendixSha,$today){$p=$this->practice($id,true);$this->readyForSignature($p,$today);
            if(!$p['owner_approved_at'])throw new RuntimeException('Approvazione del titolare necessaria prima di OTPService');
            $old=$this->query('SELECT * FROM onboarding_signatures WHERE practice_id=?',[$id])->fetch(PDO::FETCH_ASSOC);
            if($old){if(!hash_equals($old['document_sha256'],$documentSha)||!hash_equals($old['appendix_sha256'],$appendixSha))throw new RuntimeException('Documento firma già fissato');return array_merge($old,['dispatch'=>false]);}
            $event=$this->query("SELECT payload FROM onboarding_events WHERE event_key=?",['onboarding:'.$id.':owner-approved'])->fetchColumn();$owner=json_decode((string)$event,true);
            $key=bin2hex(random_bytes(32));
            $this->query('INSERT INTO onboarding_signatures(practice_id,request_key,document_sha256,appendix_sha256,owner_id,state,created_at) VALUES(?,?,?,?,?,?,?)',[$id,$key,$documentSha,$appendixSha,$owner['owner_id'],'reserved',gmdate('c')]);
            $this->query("UPDATE onboarding_practices SET state='signature_pending' WHERE id=?",[$id]);
            return ['request_key'=>$key,'document_sha256'=>$documentSha,'appendix_sha256'=>$appendixSha,'dispatch'=>true];
        });
    }
    public function signatureDispatched(string $id,string $requestKey,string $dossierId): void
    {
        if(!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$dossierId))throw new RuntimeException('Pratica OTP non valida');
        $this->locked(function()use($id,$requestKey,$dossierId){$this->practice($id,true);$s=$this->query('SELECT * FROM onboarding_signatures WHERE practice_id=?',[$id])->fetch(PDO::FETCH_ASSOC);
            if(!$s||!hash_equals($s['request_key'],$requestKey)||($s['dossier_id'] && $s['dossier_id']!==$dossierId))throw new RuntimeException('Firma non associata alla pratica');
            if($s['state']==='completed')return;
            $this->query("UPDATE onboarding_signatures SET dossier_id=?,state='dispatched' WHERE practice_id=?",[$dossierId,$id]);
        });
    }
    /** A trusted provider adapter supplies both signatures and verified pCloud metadata. */
    public function completeSignature(string $id,array $proof): void
    {
        $this->locked(function()use($id,$proof){$p=$this->practice($id,true);$s=$this->query('SELECT * FROM onboarding_signatures WHERE practice_id=?',[$id])->fetch(PDO::FETCH_ASSOC);
            if(!$s||!$p['owner_approved_at']||$p['cancelled_at']||($proof['status']??'')!=='completed'||($proof['artist_signed']??false)!==true||($proof['company_signed']??false)!==true||!hash_equals((string)$s['request_key'],(string)($proof['request_key']??''))||!hash_equals((string)$s['document_sha256'],(string)($proof['document_sha256']??''))||!$s['dossier_id']||$s['dossier_id']!==($proof['dossier_id']??''))throw new RuntimeException('Completamento firma non verificato');
            $file=$proof['file']??[];
            if(!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',(string)($file['file_id']??''))||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',(string)($file['artist_folder_id']??''))||(string)$file['artist_folder_id']!==(string)$p['snapshot']['artist_folder_id']||!preg_match('/^[a-f0-9]{64}$/D',(string)($file['sha256']??'')))throw new RuntimeException('Archiviazione contratto firmato non verificata');
            if($s['state']==='completed'){if(!hash_equals($s['signed_file_sha256'],$file['sha256'])||(string)$p['signed_pcloud_file_id']!==(string)$file['file_id'])throw new RuntimeException('Callback firma discordante');return;}
            $this->query("UPDATE onboarding_signatures SET state='completed',signed_file_sha256=? WHERE practice_id=?",[$file['sha256'],$id]);
            $this->query("UPDATE onboarding_practices SET signed_at=?,signed_pcloud_file_id=?,state='activation_ready' WHERE id=?",[gmdate('c'),$file['file_id'],$id]);
        });
    }
    public function markActivated(string $id,int $portalUserId): void
    {
        if($portalUserId<1)throw new RuntimeException('Account portale non valido');
        $this->locked(function()use($id,$portalUserId){$p=$this->practice($id,true);
            if($p['portal_activated_at']){
                $old=$this->query('SELECT payload FROM onboarding_events WHERE event_key=?',['onboarding:'.$id.':portal-active'])->fetchColumn();
                if((int)(json_decode((string)$old,true)['portal_user_id']??0)!==$portalUserId)throw new RuntimeException('Adesione già associata a un altro account');
                return;
            }
            if($p['state']!=='activation_ready'||!$p['signed_at']||!$p['signed_pcloud_file_id']||!$p['owner_approved_at']||$p['cancelled_at'])throw new RuntimeException('Attivazione non autorizzata');
            $this->query("UPDATE onboarding_practices SET portal_activated_at=?,state='active' WHERE id=?",[gmdate('c'),$id]);
            $this->query('INSERT INTO onboarding_events(event_key,practice_id,kind,payload,status,created_at) VALUES(?,?,?,?,?,?)',['onboarding:'.$id.':portal-active',$id,'portal_activated',$this->json(['portal_user_id'=>$portalUserId]),'recorded',gmdate('c')]);
        });
    }
    public function assertActivationUser(string $id,int $userId): void
    {
        if($userId<1)throw new RuntimeException('Account portale non valido');
        $p=$this->practice($id);if(!$p['portal_activated_at'])return;
        $old=$this->query('SELECT payload FROM onboarding_events WHERE event_key=?',['onboarding:'.$id.':portal-active'])->fetchColumn();
        if((int)(json_decode((string)$old,true)['portal_user_id']??0)!==$userId)throw new RuntimeException('Adesione già associata a un altro account');
    }
    private function extendRecurring(string $id,string $today): void
    {
        $p=$this->practice($id);
        if($p['cancelled_at']||!$p['first_payment_date']||($p['selected_plan']['kind']??'')!=='recurring')return;
        $anchor=OnboardingPolicy::date($p['first_payment_date']);$now=OnboardingPolicy::date($today);
        $months=max(0,((int)$now->format('Y')-(int)$anchor->format('Y'))*12+(int)$now->format('m')-(int)$anchor->format('m'));
        if($months>1198)throw new RuntimeException('Durata mensile da verificare');
        // Always materialize through next month; no fixed one-year limit on DDS.
        $current=(int)$this->query('SELECT COALESCE(MAX(number),0) FROM onboarding_installments WHERE practice_id=?',[$id])->fetchColumn();
        for($i=$current;$i<$months+2;$i++){
            try{$this->query('INSERT INTO onboarding_installments(practice_id,number,due_date,amount_cents) VALUES(?,?,?,?)',[$id,$i+1,OnboardingPolicy::dueDate($p['first_payment_date'],$i),$p['selected_plan']['amounts_cents'][0]]);}
            catch(\PDOException $e){if((string)$e->getCode()!=='23000')throw $e;}
        }
    }
    public function paymentHold(string $id,string $reference,bool $hold): void
    {
        if(!preg_match('/^[A-Za-z0-9:_-]{1,128}$/D',$reference))throw new RuntimeException('Riferimento amministrativo non valido');
        $this->locked(function()use($id,$reference,$hold){$this->practice($id,true);
            $this->query('DELETE FROM onboarding_holds WHERE practice_id=? AND reference=?',[$id,$reference]);
            if($hold)$this->query('INSERT INTO onboarding_holds(practice_id,reference,reason,created_at) VALUES(?,?,?,?)',[$id,$reference,'provider_review',gmdate('c')]);
        });
    }
    public function access(string $id,string $today): array
    {
        $this->extendRecurring($id,$today);$result=OnboardingPolicy::services($this->practice($id),$this->installments($id),$today);
        if($this->query('SELECT 1 FROM onboarding_holds WHERE practice_id=?',[$id])->fetchColumn())return ['managed'=>true,'allowed'=>false,'reason'=>'payment_review','overdue'=>$result['overdue']??[]];
        return $result;
    }
    public function queueOverdueReminders(string $today): int
    {
        OnboardingPolicy::date($today);$count=0;
        $ids=$this->query("SELECT id FROM onboarding_practices WHERE onboarding_version=? AND portal_activated_at IS NOT NULL AND cancelled_at IS NULL",[OnboardingPolicy::VERSION])->fetchAll(PDO::FETCH_COLUMN);
        foreach($ids as $id)$count+=$this->locked(function()use($id,$today){$p=$this->practice($id,true);$added=0;
            if($p['cancelled_at'])return 0;
            foreach($this->access($id,$today)['overdue']??[] as $row){$key=OnboardingPolicy::reminderKey($id,(int)$row['number']);
                if($this->query('SELECT 1 FROM onboarding_events WHERE event_key=?',[$key])->fetchColumn())continue;
                $this->query('INSERT INTO onboarding_events(event_key,practice_id,kind,payload,status,created_at) VALUES(?,?,?,?,?,?)',[$key,$id,'overdue_reminder',$this->json(['number'=>(int)$row['number'],'due_date'=>$row['due_date']]),'queued',gmdate('c')]);$added++;
            }return $added;
        });return $count;
    }
    /** Worker reserves before sending. An uncertain transport NEVER gets an automatic resend. */
    public function takeReminder(?string $today=null): ?array
    {
        $today??=(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Rome')))->format('Y-m-d');OnboardingPolicy::date($today);
        return $this->locked(function()use($today){
            $suffix=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite'?' FOR UPDATE':'';
            // A cleared debt must not stop delivery of later queued reminders.
            for($attempt=0;$attempt<100;$attempt++){
            $row=$this->query("SELECT * FROM onboarding_events WHERE kind='overdue_reminder' AND status='queued' ORDER BY created_at,event_key LIMIT 1".$suffix)->fetch(PDO::FETCH_ASSOC);
            if(!$row)return null;$p=$this->practice($row['practice_id'],true);$payload=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$debt=null;
            foreach($this->access($p['id'],$today)['overdue']??[] as $item)if((int)$item['number']===$payload['number'])$debt=$item;
            $this->query('UPDATE onboarding_events SET status=? WHERE event_key=?',[$debt?'sending':'cancelled',$row['event_key']]);
            if($debt)return array_merge($row,['email'=>$p['email'],'debt'=>$debt]);
            }return null;
        });
    }
    public function finishReminder(string $key,bool $confirmed): void
    {
        $this->query("UPDATE onboarding_events SET status=?,completed_at=? WHERE event_key=? AND status='sending'",[$confirmed?'sent':'uncertain',gmdate('c'),$key]);
    }
}
