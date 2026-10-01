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
        if(str_contains($source,'// TRB candidate onboarding workflow v1'))return $source;
        $source=self::once($source,'final class SubmissionRepository',"require_once __DIR__.'/OnboardingContractWorkflow.php';\n\nfinal class SubmissionRepository");
        $source=self::wrap($source,'previewContract', <<<'PHP'
    public function previewContract(int $id,string $templateKey): array
    {
        $s=$this->find($id);
        if($s&&OnboardingContractWorkflow::handles($s,trim($templateKey)))return OnboardingContractWorkflow::preview($this->db,$s,trim($templateKey));
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
        try{ $s=$this->find($id);OnboardingContractWorkflow::send($this->db,$s,$userId);return $this->find($id)??[]; }
        finally{$release=$this->db->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$name]);}
    }
PHP);
        $source=self::wrap($source,'sendContractBatch', <<<'PHP'
    public function sendContractBatch(array $input,int $userId): array
    {
        $items=is_array($input['items']??null)?$input['items']:[];$new=0;
        foreach($items as $item){$s=$this->find((int)($item['submission_id']??0));if($s&&OnboardingContractWorkflow::handles($s,(string)($item['template_key']??'')))$new++;}
        if($new===count($items)&&$new>0)return OnboardingContractWorkflow::batch($this,$this->db,$input,$userId);
        if($new>0)throw new RuntimeException('Separa le nuove proposte dai contratti storici prima dell’invio');
        return $this->trbLegacySendContractBatch($input,$userId);
    }
PHP);
        // The send permission is scoped to candidate proposals, not accounting or legacy changes.
        $source=preg_replace('/(\\$submission\\[\\x27contract_draft_writable\\x27\\]\\s*=\\s*true;)/','$1'."\n        \$submission['contract_send_writable']=OnboardingContractWorkflow::candidate(\$submission);",$source,1,$count);
        if($count!==1)throw new \RuntimeException('CRM candidate detail changed');
        $source=preg_replace('/(\\$item\\[\\x27contract_batch_eligible\\x27\\]\\s*=)([^;]+);/', '$1($2)||OnboardingContractWorkflow::candidate($item);', $source);
        return str_replace('final class SubmissionRepository',"// TRB candidate onboarding workflow v1\nfinal class SubmissionRepository",$source);
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
