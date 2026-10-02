<?php
declare(strict_types=1);
namespace TrbCrm;
require_once __DIR__.'/OnboardingLedger.php';
require_once __DIR__.'/OnboardingContractCatalog.php';

/** Adapters are injected so the complete orchestration can run with synthetic data. */
final class OnboardingService
{
    public function __construct(private OnboardingLedger $ledger,private object $archive,private object $reader,private \Closure $portal,private \Closure $script) {}
    private function today(): string {return (new \DateTimeImmutable('now',new \DateTimeZone('Europe/Rome')))->format('Y-m-d');}
    public function view(array $p): array
    {
        // The only TRB route has no commercial choice. Resume existing verified
        // invitations too; owner approval and both signatures remain mandatory.
        $plans=$p['snapshot']['plans'];
        if($p['state']==='identity_matched'&&$p['snapshot']['group_code']==='TRB'&&count($plans)===1&&reset($plans)['kind']==='free')$p=$this->ledger->choosePlan($p['id'],(string)array_key_first($plans));
        $files=[];foreach($this->ledger->files($p['id']) as $slot=>$file)$files[$slot]=['name'=>$file['name']??$slot,'uploaded'=>true];
        return ['id'=>$p['id'],'state'=>$p['state'],'version'=>$p['onboarding_version'],'first_name'=>$p['snapshot']['first_name'],'last_name'=>$p['snapshot']['last_name'],'artist_name'=>$p['snapshot']['artist_name']??'','email'=>$p['email'],'group_code'=>$p['snapshot']['group_code'],'contract_number'=>$p['snapshot']['contract_number']??'','proposal_sha256'=>$p['snapshot']['unsigned_document_sha256'],'plans'=>$p['snapshot']['plans'],'selected_plan'=>$p['selected_plan'],'files'=>$files,'details'=>$this->ledger->details($p['id']),'installments'=>$this->ledger->installments($p['id']),'access'=>$this->ledger->access($p['id'],$this->today())];
    }
    public function upload(array $p,string $slot,array $fileInfo=[]): array
    {
        if(!in_array($slot,['identity_front','identity_back','tax_front','tax_back'],true)||!in_array($p['state'],['invited','identity_review'],true))throw new \RuntimeException('Caricamento documenti non disponibile');
        $details=$this->ledger->details($p['id']);if(empty($details['privacy_acknowledged_at']))throw new \RuntimeException('Leggi prima le informazioni sul trattamento dei documenti');
        $this->archive->artistFolder($p['snapshot']['group_code'],$p['snapshot']['artist_folder_id']);
        $grant=$this->ledger->upload($p['id'],$slot);
        $files=$this->ledger->files($p['id']);$completed=$grant&&isset($files[$slot])&&(string)$files[$slot]['folder_id']===(string)$grant['folder_id'];
        if(!$grant||$completed||strtotime($grant['expires_at'])<=time()||($grant['file']??null)!==$fileInfo){$grant=$this->archive->createUpload($p['snapshot']['artist_folder_id'],$p['id'],$slot,$fileInfo);$grant['file']=$fileInfo;$this->ledger->saveUpload($p['id'],$slot,$grant);}
        return array_intersect_key($grant,array_flip(['provider','code','upload_endpoint','upload_method','expires_at','max_bytes']));
    }
    public function uploaded(array $p,string $slot): array
    {
        if(!in_array($slot,['identity_front','identity_back','tax_front','tax_back'],true))throw new \RuntimeException('Documento non valido');
        $grant=$this->ledger->upload($p['id'],$slot);if(!$grant)throw new \RuntimeException('Caricamento non avviato');
        // Retry after a lost response returns existing verified metadata without another write.
        $files=$this->ledger->files($p['id']);if(isset($files[$slot])&&(string)$files[$slot]['folder_id']===(string)$grant['folder_id'])return $this->view($this->ledger->practice($p['id']));
        $file=$this->archive->verifyUpload($grant['folder_id'],$grant['upload_link_id']);$this->ledger->recordFile($p['id'],$slot,$file);
        return $this->view($this->ledger->practice($p['id']));
    }
    public function details(array $p,array $input): array
    {
        if(($input['privacy_acknowledged']??false)!==true)throw new \RuntimeException('Informazioni sul trattamento dei documenti da leggere');
        $billing=[];foreach(['address_1','address_2','street','street_number','city','postcode','country','state','phone'] as $field){$value=trim((string)($input['billing'][$field]??''));if(mb_strlen($value)>200||preg_match('/[\x00-\x1f]/',$value))throw new \RuntimeException('Dati amministrativi non validi');$billing[$field]=$value;}
        foreach(['address_1','city','postcode','country','phone'] as $required)if($billing[$required]==='')throw new \RuntimeException('Completa domicilio, paese e telefono');
        $billing['country']=strtoupper($billing['country']);if(!preg_match('/^[A-Z]{2}$/D',$billing['country']))throw new \RuntimeException('Paese non valido');
        $phone=preg_replace('/[\s.()\-]+/','',$billing['phone']);if(str_starts_with($phone,'0039'))$phone='+39'.substr($phone,4);
        if(!preg_match('/^(?:\+39)?3\d{9}$/D',$phone))throw new \RuntimeException('Inserisci un cellulare italiano valido per ricevere gli SMS di firma');$billing['phone']=$phone;
        $billing['first_name']=$p['snapshot']['first_name'];$billing['last_name']=$p['snapshot']['last_name'];
        $tax=mb_strtoupper(trim((string)($input['tax_code']??'')));if(!preg_match('/^[A-Z0-9 -]{3,32}$/D',$tax))throw new \RuntimeException('Identificativo fiscale non valido');
        $invoice=[];foreach(['company_name','company_address','vat_number','sdi_code','pec'] as $field)$invoice[$field]=trim((string)($input['invoice'][$field]??''));
        if($invoice['sdi_code']!==''&&!preg_match('/^[A-Za-z0-9]{7}$/D',$invoice['sdi_code']))throw new \RuntimeException('Il codice SDI deve contenere sette caratteri');
        if($invoice['pec']!==''&&!filter_var($invoice['pec'],FILTER_VALIDATE_EMAIL))throw new \RuntimeException('PEC non valida');
        foreach($invoice as $value)if(mb_strlen($value)>255||preg_match('/[\x00-\x1f]/',$value))throw new \RuntimeException('Dato fiscale troppo lungo');
        $profile=[];foreach(['birth_date','birth_place','birth_province','document_number','document_expiry'] as $field){$value=trim((string)($input['profile'][$field]??''));if(mb_strlen($value)>200||preg_match('/[\x00-\x1f]/',$value))throw new \RuntimeException('Dati anagrafici non validi');$profile[$field]=$value;}
        $details=['billing'=>$billing,'profile'=>$profile,'tax_code'=>$tax,'invoice'=>$invoice,'privacy_acknowledged_at'=>gmdate('c'),'privacy_version'=>'2026.2-documents-20261002c'];
        $this->ledger->saveDetails($p['id'],$details);return $this->view($this->ledger->practice($p['id']));
    }
    public function identity(array $p): array
    {
        $files=$this->ledger->files($p['id']);$documents=[];
        foreach(['identity_front','tax_front'] as $slot){$file=$files[$slot]??null;if(!$file)throw new \RuntimeException('Carica il fronte della carta d’identità e della tessera sanitaria o codice fiscale');$documents[]=['slot'=>$slot]+(method_exists($this->archive,'identityDocument')?$this->archive->identityDocument($file):['url'=>$this->archive->url($file['file_id'],$file['folder_id'],(string)$file['hash']),'name'=>$file['name']]);}
        $fields=$this->reader->extract($documents);$decision=$this->ledger->recordIdentity($p['id'],$fields,$this->today());
        return ['identity'=>$decision,'practice'=>$this->view($this->ledger->practice($p['id']))];
    }
    public function choose(array $p,string $planKey,string $proposalSha,bool $read): array
    {
        if(!$read||!hash_equals($p['snapshot']['unsigned_document_sha256'],$proposalSha))throw new \RuntimeException('Leggi e conferma la proposta contrattuale aggiornata');
        if($p['selected_plan'])return $this->view($this->ledger->choosePlan($p['id'],$planKey));
        $details=$this->ledger->details($p['id']);if(empty($details['billing'])||empty($details['privacy_acknowledged_at']))throw new \RuntimeException('Completa prima i dati amministrativi');
        $details['proposal_read_at']=gmdate('c');$details['proposal_read_sha256']=$proposalSha;$details['plan_key']=$planKey;$this->ledger->saveDetails($p['id'],$details);
        return $this->view($this->ledger->choosePlan($p['id'],$planKey));
    }
    public function checkout(array $p): array
    {
        if(!$p['selected_plan']||$p['cancelled_at']||$p['selected_plan']['kind']==='free')throw new \RuntimeException('Versamento non previsto');
        // Verify every known attempt before asking the circuit for another payment.
        $this->refreshPayments($p['id']);$p=$this->ledger->practice($p['id']);$gate=$this->ledger->access($p['id'],$this->today());if(($gate['reason']??'')==='payment_review')throw new \RuntimeException('Versamento da verificare: contatta TRB rec prima di un nuovo tentativo');
        $rows=$this->ledger->installments($p['id']);$due=null;
        if(!$rows)$due=['number'=>1,'amount_cents'=>$p['selected_plan']['amounts_cents'][0],'confirmed_cents'=>0];
        else foreach($rows as $row)if((int)$row['amount_cents']>(int)$row['confirmed_cents']){$due=$row;break;}
        if(!$due)return ['paid'=>true,'practice'=>$this->view($p)];
        // No early request for future service periods or unsolicited early installments.
        if(isset($due['due_date'])&&$due['due_date']>$this->today())return ['paid'=>true,'next_due_date'=>$due['due_date'],'practice'=>$this->view($p)];
        $details=$this->ledger->details($p['id']);$payload=['action'=>'store_order','practice_id'=>$p['id'],'number'=>(int)$due['number'],'amount_cents'=>(int)$due['amount_cents']-(int)$due['confirmed_cents'],'installment_total_cents'=>(int)$due['amount_cents'],'currency'=>'EUR','snapshot_sha256'=>hash('sha256',json_encode($p['snapshot'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),'email'=>$p['email'],'billing'=>$details['billing'],'tax_code'=>$details['tax_code'],'invoice'=>$details['invoice']??[]];
        $order=($this->portal)($payload);if(!is_int($order['order_id']??null)||$order['order_id']<1)throw new \RuntimeException('Ordine non confermato');
        $this->ledger->rememberOrder($p['id'],(int)$due['number'],$order['order_id']);
        $url=$order['checkout_url']??'';if($url!==''&&(parse_url($url,PHP_URL_SCHEME)!=='https'||parse_url($url,PHP_URL_HOST)!=='store.trbrec.com'))throw new \RuntimeException('Collegamento pagamento non valido');
        if($order['paid']??false)$this->refreshPayments($p['id']);
        return ['checkout_url'=>$url,'paid'=>(bool)($order['paid']??false),'practice'=>$this->view($this->ledger->practice($p['id']))];
    }
    public function refreshPayments(?string $id=null): array
    {
        $errors=[];
        foreach($this->ledger->orders($id) as $order){$p=$this->ledger->practice($order['practice_id']);try{
            $proof=($this->portal)(['action'=>'store_status','practice_id'=>$p['id'],'order_id'=>(int)$order['order_id']]);
            $sha=hash('sha256',json_encode($p['snapshot'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            if(($proof['practice_id']??'')!==$p['id']||(int)($proof['number']??0)!==(int)$order['installment_number']||strcasecmp((string)($proof['email']??''),$p['email'])!==0||!hash_equals($sha,(string)($proof['snapshot_sha256']??'')))throw new \RuntimeException('Conferma versamento non associata alla pratica');
            $payment=isset($proof['provider'],$proof['transaction_id'])?$this->ledger->payment($proof['provider'],$proof['transaction_id']):null;
            if(($proof['status']??'')==='confirmed'){
                if($payment&&$payment['status']!=='confirmed')throw new \RuntimeException('Esito precedente discordante: verifica amministrativa necessaria');
                $this->ledger->confirmedPayment($p['id'],(int)$order['installment_number'],$proof);$this->ledger->paymentHold($p['id'],'order:'.$order['order_id'],false);
            }elseif(($proof['status']??'')==='reversed'&&($proof['captured']??false)===true){
                if(!$payment)$this->ledger->confirmedPayment($p['id'],(int)$order['installment_number'],array_replace($proof,['status'=>'confirmed']));
                if(!is_int($proof['refunded_cents']??null))throw new \RuntimeException('Storno non verificato');
                $this->ledger->reversePayment($proof['provider'],$proof['transaction_id'],$proof['refunded_cents']);
                // This is provider accounting, not an artist-facing refund facility.
                $this->ledger->paymentHold($p['id'],'order:'.$order['order_id'],false);
            }elseif(($proof['status']??'')==='review'||$payment)$this->ledger->paymentHold($p['id'],'order:'.$order['order_id'],true);
            else $this->ledger->paymentHold($p['id'],'order:'.$order['order_id'],false);
        }catch(\Throwable $e){$this->ledger->paymentHold($p['id'],'order:'.$order['order_id'],true);$errors[]=['practice_id'=>$p['id'],'order_id'=>(int)$order['order_id']];if($id!==null)throw $e;}}
        return $errors;
    }
    public function document(array $p,string $slot): array
    {
        if(in_array($slot,['contract','proposal','final_pdf','signature_audit'],true)){
            $file=$this->ledger->artifact($p['id'],$slot==='contract'?'signed_pdf':$slot);
        }else{$files=$this->ledger->files($p['id']);$file=$files[$slot]??[];}
        if(!$file)throw new \RuntimeException('Documento non disponibile');
        return method_exists($this->archive,'read')?['file'=>$this->archive->read($file['file_id'],$file['folder_id'],(string)$file['hash'])]:['url'=>$this->archive->url($file['file_id'],$file['folder_id'],(string)$file['hash'])];
    }
    public function prepareFinal(array $p): array
    {
        if(!$p['owner_approved_at']||!in_array($p['state'],['signature_ready','signature_pending'],true))throw new \RuntimeException('Approvazione del titolare necessaria');
        $old=$this->ledger->artifact($p['id'],'final_pdf');if($old)return $old;
        $appendix=OnboardingContractCatalog::appendix($p);
        $grant=$this->ledger->upload($p['id'],'final_pdf');
        if(!$grant||strtotime($grant['expires_at'])<=time()){$grant=$this->archive->createUpload($p['snapshot']['artist_folder_id'],$p['id'],'final_pdf');$this->ledger->saveUpload($p['id'],'final_pdf',$grant);}
        $payload=['action'=>'crm_onboarding_document','phase'=>'final','practice_id'=>$p['id'],'snapshot'=>$p['snapshot'],'details'=>$this->ledger->details($p['id']),'appendix'=>$appendix,'upload'=>$grant];
        try{$result=($this->script)($payload);}catch(\Throwable $e){$result=($this->script)($payload+['metadata_only'=>true]);}
        $file=$this->archive->verifyArtifact($grant,(string)($result['sha256']??''),$p['snapshot']['artist_folder_id']);
        if(!preg_match('/^[A-Za-z0-9_-]+$/D',(string)($result['drive_pdf_id']??'')))throw new \RuntimeException('Documento di firma non associato a Drive');
        $file['drive_pdf_id']=$result['drive_pdf_id'];$file['appendix_sha256']=$appendix['sha256'];
        $this->ledger->saveArtifact($p['id'],'final_pdf',$file);return $file;
    }
    /** This is invoked only by the owner's explicit CRM action, never by a cron. */
    public function dispatchSignature(array $p): array
    {
        $this->refreshPayments($p['id']);$p=$this->ledger->practice($p['id']);$file=$this->prepareFinal($p);
        $reservation=$this->ledger->reserveSignature($p['id'],$file['sha256'],$file['appendix_sha256'],$this->today());
        if(!$reservation['dispatch'])return ['already_reserved'=>true,'dossier_id'=>$reservation['dossier_id']??null];
        $result=($this->script)(['action'=>'crm_onboarding_signature','practice_id'=>$p['id'],'request_key'=>$reservation['request_key'],'document_sha256'=>$file['sha256'],'drive_pdf_id'=>$file['drive_pdf_id'],'snapshot'=>$p['snapshot'],'details'=>$this->ledger->details($p['id']),'owner_approved'=>true]);
        if(!is_string($result['dossier_id']??null))throw new \RuntimeException('Esito OTP da verificare: non ripetere l’invio');
        $this->ledger->signatureDispatched($p['id'],$reservation['request_key'],$result['dossier_id']);return ['dossier_id'=>$result['dossier_id']];
    }
    public function refreshSignature(array $p): array
    {
        $signature=$this->ledger->signature($p['id']);if(!$signature||$signature['state']==='completed')return $p;
        // Reconcile an uncertain dispatch through its durable receipt, never create again.
        $result=($this->script)(['action'=>'crm_onboarding_signature_status','practice_id'=>$p['id'],'request_key'=>$signature['request_key'],'dossier_id'=>$signature['dossier_id']]);
        if(!empty($result['dossier_id'])&&!$signature['dossier_id']){$this->ledger->signatureDispatched($p['id'],$signature['request_key'],(string)$result['dossier_id']);$signature=$this->ledger->signature($p['id']);}
        if(($result['status']??'')!=='completed')return $this->ledger->practice($p['id']);
        foreach(['artist_signed','company_signed'] as $flag)if(($result[$flag]??false)!==true)throw new \RuntimeException('Entrambe le firme devono essere confermate');
        $grants=[];foreach(['signed_pdf','signature_audit'] as $slot){if($this->ledger->artifact($p['id'],$slot))continue;$grants[$slot]=$this->ledger->upload($p['id'],$slot);if(!$grants[$slot]||strtotime($grants[$slot]['expires_at'])<time()){$grants[$slot]=$this->archive->createUpload($p['snapshot']['artist_folder_id'],$p['id'],$slot);$this->ledger->saveUpload($p['id'],$slot,$grants[$slot]);}}
        $proof=$grants?($this->script)(['action'=>'crm_onboarding_signed_archive','practice_id'=>$p['id'],'request_key'=>$signature['request_key'],'dossier_id'=>$signature['dossier_id'],'uploads'=>$grants]):[];
        foreach($grants as $slot=>$grant){$file=$this->ledger->artifact($p['id'],$slot);if(!$file){$file=$this->archive->verifyArtifact($grant,(string)($proof[$slot.'_sha256']??''),$p['snapshot']['artist_folder_id']);$this->ledger->saveArtifact($p['id'],$slot,$file);}}
        $this->ledger->completeSignature($p['id'],['status'=>'completed','artist_signed'=>true,'company_signed'=>true,'request_key'=>$signature['request_key'],'document_sha256'=>$signature['document_sha256'],'dossier_id'=>$signature['dossier_id'],'file'=>$this->ledger->artifact($p['id'],'signed_pdf')]);
        return $this->ledger->practice($p['id']);
    }
    public function registrationAuthorization(array $p): array
    {
        if(!in_array($p['state'],['activation_ready','active'],true)||!$p['owner_approved_at']||!$p['signed_at']||!$p['signed_pcloud_file_id']||$p['cancelled_at'])throw new \RuntimeException('Registrazione disponibile dopo approvazione, firme e archiviazione');
        return array_intersect_key($p['snapshot'],array_flip(['first_name','last_name','artist_name']))+['id'=>$p['id'],'email'=>$p['email']];
    }
    public function register(array $p,int $userId): array
    {
        $this->registrationAuthorization($p);
        $this->ledger->assertActivationUser($p['id'],$userId);
        $start=OnboardingPolicy::date($this->ledger->activationStart($p['id'],$this->today()));$group=$p['snapshot']['group_code'];
        $months=in_array($group,['DDB12','DDB'],true)?12:($group==='DDB-TRB'?24:0);
        $term=$start->format('d/m/y').' - '.($months?OnboardingPolicy::date(OnboardingPolicy::dueDate($start->format('Y-m-d'),$months))->modify('-1 day')->format('d/m/y'):'INFINITO');
        $result=($this->portal)(['action'=>'activate_account','practice_id'=>$p['id'],'portal_user_id'=>$userId,'email'=>$p['email'],'group_code'=>$group,'owner_approved'=>true,'signed'=>true,'signed_pcloud_file_id'=>(string)$p['signed_pcloud_file_id'],'archive_provider'=>$p['snapshot']['archive_provider']??'pcloud','contract_number'=>$p['snapshot']['contract_number'],'contract_term'=>$term,'details'=>$this->ledger->details($p['id']),'birth_date'=>$this->ledger->identityResult($p['id'])['birth_date']??'','files'=>$this->ledger->files($p['id'])]);
        if(($result['activated']??false)!==true||($result['practice_id']??'')!==$p['id']||(int)($result['portal_user_id']??0)!==$userId)throw new \RuntimeException('Attivazione account non confermata');
        $this->ledger->markActivated($p['id'],$userId);return ['registered'=>true];
    }
}
