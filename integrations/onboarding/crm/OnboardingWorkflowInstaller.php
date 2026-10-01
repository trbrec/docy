<?php
declare(strict_types=1);
namespace TrbCrm;

/** Small, guarded edits to the current production CRM; no repository snapshot replacement. */
final class OnboardingWorkflowInstaller
{
    private static function once(string $source,string $old,string $new): string
    {
        if(str_contains($source,$new))return $source;
        if(substr_count($source,$old)!==1)throw new \RuntimeException('CRM workflow anchor changed');
        return str_replace($old,$new,$source);
    }
    private static function wrap(string $source,string $name,string $wrapper): string
    {
        $pattern='~public\\s+function\\s+'.preg_quote($name,'~').'\\s*\\([^)]*\\)\\s*:\\s*array\\s*\\{~';
        if(preg_match_all($pattern,$source,$matches)!==1)throw new \RuntimeException('CRM workflow method changed');
        $original=$matches[0][0];$renamed=preg_replace('/function\\s+'.preg_quote($name,'/').'/','function trbLegacy'.ucfirst($name),$original);
        return str_replace($original,$wrapper."\n".$renamed,$source);
    }
    public static function repository(string $source): string
    {
        if(str_contains($source,'// TRB candidate onboarding workflow v2'))return $source;
        if(str_contains($source,'// TRB candidate onboarding workflow v1')){
            foreach(['previewContract','sendContract','sendContractBatch'] as $method){
                $pattern='~public\\s+function\\s+'.preg_quote($method,'~').'\\s*\\([^)]*\\)\\s*:\\s*array\\s*\\{.*?(?=public function trbLegacy'.ucfirst($method).'\\()~s';
                $source=preg_replace($pattern,'',$source,1,$count);if($count!==1)throw new \RuntimeException('CRM v1 upgrade anchor changed');
                $source=str_replace('function trbLegacy'.ucfirst($method).'(','function '.$method.'(',$source);
            }
            $source=str_replace('// TRB candidate onboarding workflow v1','',$source);
        }
        $source=self::once($source,'final class SubmissionRepository',"require_once __DIR__.'/OnboardingContractWorkflow.php';\n\nfinal class SubmissionRepository");
        $source=self::wrap($source,'previewContract', <<<'PHP'
    public function previewContract(int $id,string $templateKey): array
    {
        $s=$this->find($id);
        if($s&&OnboardingContractWorkflow::handles($s,trim($templateKey)))return OnboardingContractWorkflow::preview($this->db,$s,trim($templateKey),$this->trbLegacyPreviewContract($id,$templateKey));
        return $this->trbLegacyPreviewContract($id,$templateKey);
    }
PHP);
        $source=self::wrap($source,'sendContract', <<<'PHP'
    public function sendContract(int $id,int $userId): array
    {
        $s=$this->find($id);
        if(!$s||!$s['contract']||!OnboardingContractWorkflow::handles($s,(string)$s['contract']['template_key']))return $this->trbLegacySendContract($id,$userId);
        $name='trb_contract_send_'.$id;$lock=$this->db->prepare('SELECT GET_LOCK(?,5)');$lock->execute([$name]);
        if((int)$lock->fetchColumn()!==1)throw new RuntimeException('Invio già in corso, riprova tra poco');
        try{ $s=$this->find($id);OnboardingContractWorkflow::send($this->db,$s,$userId);$sentAt=(string)$this->db->query('SELECT sent_at FROM contracts WHERE id='.(int)$s['contract']['id'])->fetchColumn();$this->recordCandidateDeadline((int)$s['contract']['id'],$sentAt);return $this->find($id)??[]; }
        finally{$release=$this->db->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$name]);}
    }
PHP);
        if(str_contains($source,'use CandidateContractReview;')){
            $source=self::once($source,'use CandidateContractReview;','use CandidateContractReview { prepareCandidateContractReview as trbLegacyPrepareCandidateContractReview; }');
            $source=self::once($source,'public function trbLegacyPreviewContract(',<<<'PHP'
    public function prepareCandidateContractReview(int $id,array $input,int $userId): array
    {
        $key=trim((string)($input['template_key']??''));$s=$this->find($id);
        if(!$s||!OnboardingContractWorkflow::handles($s,$key))return $this->trbLegacyPrepareCandidateContractReview($id,$input,$userId);
        $name='trb_contract_send_'.$id;$lock=$this->db->prepare('SELECT GET_LOCK(?,5)');$lock->execute([$name]);
        if((int)$lock->fetchColumn()!==1)throw new RuntimeException('Preparazione già in corso');
        try{
            $s=$this->candidateDraft($id,$key);$p=$this->previewContract($id,$key);
            if($p['attachment_ready'])return ['preview'=>$p,'duplicate'=>true,'send_performed'=>false];
            if(!empty($s['contract']['metadata']['candidate_editable_doc'])||!empty($s['contract']['metadata']['candidate_review']['reason']))throw new RuntimeException('La proposta contiene personalizzazioni: verifica le condizioni prima di attivare il nuovo percorso.');
            $saved=$this->saveContract($id,['template_key'=>$key,'document_url'=>$p['document_url'],'subject'=>$p['subject'],'body'=>$p['body'],'followup_subject'=>$p['followup_subject'],'preview_token'=>$p['preview_token']],$userId);
            $this->ensureLifecycleFromTemplate($id,$key,$userId);
            $result=(new OnboardingRuntime($this->db))->prepare((int)$saved['contract']['id'],0,$key);
            $ledger=new OnboardingLedger($this->db);$artifact=$ledger->artifact($result['id'],'proposal');
            $this->db->prepare('UPDATE contracts SET document_url=?,document_sha256=?,document_mime=?,generated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$result['invite_url'],$artifact['sha256'],'application/pdf',(int)$saved['contract']['id']]);
            return ['preview'=>$this->previewContract($id,$key),'send_performed'=>false];
        }finally{$this->db->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]);}
    }
public function trbLegacyPreviewContract(
PHP);
        }
        if(str_contains($source,'$this->contractHistoryPath($contract);')){
            $source=self::once($source,'$file=$this->contractHistoryPath($contract);',<<<'PHP'
$file=$this->contractHistoryPath($contract);
        $privatePractice=(new OnboardingLedger($this->db))->forContract((int)$contract['id']);if($privatePractice&&(new OnboardingLedger($this->db))->artifact($privatePractice['id'],'proposal'))$file='private-google-drive';
PHP);
        }
        $source=str_replace("['candidate_review','candidate_editable_doc','pdf_upload_link_v1','otp_service']","['candidate_review','candidate_editable_doc','pdf_upload_link_v1','otp_service','onboarding']",$source);
        $sendAnchor=<<<'PHP'
$mail=OutboundMail::sendMessage((string)$item['email'],$subject,$body,['purpose'=>'candidate_contract','message_id'=>$messageId,'in_reply_to'=>(string)($item['parent_message_id']??''),'references'=>(string)($item['parent_message_id']??''),'attachment'=>$attachment]);
PHP;
        $driveSend=<<<'PHP'
$mail=($attachment['provider']??'')==='google_drive'?OnboardingContractWorkflow::followup($this->db,$item,$subject,$body,$messageId):OutboundMail::sendMessage(
PHP;
        if(str_contains($source,$sendAnchor))$source=self::once($source,$sendAnchor,str_replace('$mail=OutboundMail::sendMessage(',$driveSend,$sendAnchor));
        // The send permission is scoped to candidate proposals, not accounting or legacy changes.
        if(!str_contains($source,"\$submission['contract_send_writable']"))$source=preg_replace('/(\\$submission\\[\\x27contract_draft_writable\\x27\\]\\s*=\\s*true;)/','$1'."\n        \$submission['contract_send_writable']=OnboardingContractWorkflow::candidate(\$submission);",$source,1,$count);
        if(!str_contains($source,"\$submission['contract_send_writable']"))throw new \RuntimeException('CRM candidate detail changed');
        $source=preg_replace('/(\\$item\\[\\x27contract_batch_eligible\\x27\\]\\s*=)([^;]+);/', '$1($2)||OnboardingContractWorkflow::candidate($item);', $source);
        return str_replace('final class SubmissionRepository',"// TRB candidate onboarding workflow v2\nfinal class SubmissionRepository",$source);
    }
    public static function javascript(string $source): string
    {
        // Keep unrelated legacy editing and accounting guards intact.
        $source=str_replace("document.getElementById('sendContract').disabled=true}","document.getElementById('sendContract').disabled=!item.contract_send_writable}",$source);
        $source=str_replace("document.getElementById('sendContract').disabled=!item.writable||!dispatchReady", "document.getElementById('sendContract').disabled=!(item.contract_send_writable||item.writable)||!dispatchReady",$source);
        $source=str_replace('il plugin genererà il PDF e avvierà OTPService dopo la conferma','il sistema genererà il PDF con il collegamento personale al Portale Artisti dopo la conferma',$source);
        $source=str_replace('Plugin foglio + OTPService pronto','PDF con collegamento al Portale Artisti pronto',$source);
        $source=str_replace(' · Invio test: solo TEST-0001, TEST-0002 e TEST-0003',' · Anteprima e conferma prima dell’invio',$source);
        $anchor="installCommunicationEditor(item,id);";
        $extra="\nif(contract.metadata?.onboarding?.practice_id){document.querySelector('#detail .contract-editor')?.insertAdjacentHTML('beforeend',`<a class=\"section-button\" href=\"/onboarding?contract_id=\${Number(contract.id)}\">Verifica documenti e attivazione</a>`);}";
        if(!str_contains($source,$extra))$source=self::once($source,$anchor,$anchor.$extra);
        if(!str_contains($source,'contract_send_writable'))throw new \RuntimeException('CRM send UI anchor changed');
        return $source;
    }
}
