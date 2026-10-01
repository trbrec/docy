<?php
declare(strict_types=1);
namespace TrbCrm;
use RuntimeException;

/** Private Drive archive via the existing authenticated Apps Script deployment. */
final class OnboardingDrive
{
    public function __construct(private \Closure $script) {}
    public function health(): array{return ($this->script)(['action'=>'crm_onboarding_drive_health']);}
    public function artistFolder(string $group,string|int $owner): array
    {
        return ($this->script)(['action'=>'crm_onboarding_drive_folder','practice_id'=>str_repeat('0',32),'group'=>$group,'owner_key'=>(string)$owner]);
    }
    public function createUpload(string|int $owner,string $practice,string $slot,array $file=[]): array
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$practice)||!in_array($slot,['identity_front','identity_back','tax_front','tax_back','proposal','final_pdf','signed_pdf','signature_audit'],true))throw new RuntimeException('Destinazione documento non valida');
        return ($this->script)(['action'=>'crm_onboarding_drive_grant','practice_id'=>$practice,'artist_folder_id'=>(string)$owner,'slot'=>$slot,'file'=>$file]);
    }
    public function verifyUpload(string|int $folder,string|int $code): array
    {
        return ($this->script)(['action'=>'crm_onboarding_drive_verify','practice_id'=>str_repeat('0',32),'folder_id'=>(string)$folder,'code'=>(string)$code]);
    }
    public function verifyArtifact(array $grant,string $sha,string|int $owner): array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$sha))throw new RuntimeException('Impronta documento mancante');
        $file=$this->verifyUpload($grant['folder_id'],$grant['code']);
        if((string)($file['artist_folder_id']??'')!==(string)$owner||!hash_equals($sha,(string)($file['sha256']??'')))throw new RuntimeException('Documento non associato alla pratica o modificato');
        return $file;
    }
    public function read(string|int $file,string|int $folder,string $sha): array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$sha))throw new RuntimeException('Impronta documento non valida');
        $data=($this->script)(['action'=>'crm_onboarding_drive_read','practice_id'=>str_repeat('0',32),'file_id'=>(string)$file,'folder_id'=>(string)$folder,'sha256'=>$sha]);
        $bytes=base64_decode((string)($data['data']??''),true);
        if($bytes===false||strlen($bytes)<1||strlen($bytes)>10485760||strlen($bytes)!==($data['size']??0)||!hash_equals($sha,hash('sha256',$bytes))||!in_array($data['mime']??'',['application/pdf','image/jpeg','image/png','application/xml'],true))throw new RuntimeException('Documento Drive non verificato');
        return array_intersect_key($data,array_flip(['data','size','name','mime','sha256']));
    }
    public function identityDocument(array $file): array
    {
        $data=$this->read($file['file_id'],$file['folder_id'],$file['hash']);
        if(!in_array($data['mime'],['application/pdf','image/jpeg','image/png'],true))throw new RuntimeException('Documento identità non ammesso');
        return ['name'=>$data['name'],'mime'=>$data['mime'],'data'=>$data['data']];
    }
}
