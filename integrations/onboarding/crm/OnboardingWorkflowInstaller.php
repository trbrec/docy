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
    public static function repository(string $source): string
    {
        if(str_contains($source,'// TRB candidate onboarding workflow v1'))return $source;
        $source=self::once($source,'final class SubmissionRepository',"require_once __DIR__.'/OnboardingContractWorkflow.php';\n\nfinal class SubmissionRepository");
        $anchor="        \$templateKey=trim(\$templateKey);\n        if(\$templateKey==='')throw new RuntimeException('Scegli un modello contrattuale');";
        $source=self::once($source,$anchor,$anchor."\n        \$onboardingSubmission=\$this->find(\$id);\n        if(\$onboardingSubmission && OnboardingContractWorkflow::handles(\$onboardingSubmission,\$templateKey))return OnboardingContractWorkflow::preview(\$this->db,\$onboardingSubmission,\$templateKey);");
        $anchor="if(!\$submission||!\$submission['contract']) throw new RuntimeException('Salva prima la bozza del contratto');";
        $source=self::once($source,$anchor,$anchor."\n            if(OnboardingContractWorkflow::handles(\$submission,(string)(\$submission['contract']['template_key']??''))){OnboardingContractWorkflow::send(\$this->db,\$submission,\$userId);return \$this->find(\$id)??[];}\n            ");
        $anchor="            \$this->contractWriteContext((int)(\$item['submission_id']??0));";
        $source=self::once($source,$anchor,"            \$candidate=\$this->find((int)(\$item['submission_id']??0));\n            if(!\$candidate||!OnboardingContractWorkflow::handles(\$candidate,(string)(\$item['template_key']??'')))\$this->contractWriteContext((int)(\$item['submission_id']??0));");
        $anchor="        \$submission['contract_draft_writable']=true;";
        $source=self::once($source,$anchor,$anchor."\n        \$submission['contract_send_writable']=OnboardingContractWorkflow::candidate(\$submission);");
        $source=preg_replace('/(\$item\[\x27contract_batch_eligible\x27\]\s*=)([^;]+);/', '$1($2)||OnboardingContractWorkflow::candidate($item);', $source);
        $source=str_replace("\$item['writable']=\$this->isWritableTestPractice(\$item);", "\$item['writable']=\$this->isWritableTestPractice(\$item);\$item['contract_send_writable']=OnboardingContractWorkflow::candidate(\$item);",$source);
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
