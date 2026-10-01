<?php
declare(strict_types=1);
namespace TrbCrm;
use RuntimeException;

/** Metadata API only: document bytes travel browser -> pCloud -> OpenAI. */
final class OnboardingPcloud
{
    private string $token;
    private string $base;
    public function __construct(string $oauthFile)
    {
        // Contract documents use the CRM's established contract archive connection.
        $contractToken=class_exists(Env::class)?trim((string)Env::get('PCLOUD_ACCESS_TOKEN','')):'';
        if($contractToken!==''){
            $endpoint=parse_url((string)Env::get('PCLOUD_API_BASE','https://eapi.pcloud.com'));
            if(!is_array($endpoint)||($endpoint['scheme']??'')!=='https'||!in_array($endpoint['host']??'',['api.pcloud.com','eapi.pcloud.com'],true)||isset($endpoint['user'])||isset($endpoint['pass'])||isset($endpoint['query'])||isset($endpoint['fragment'])||($endpoint['port']??443)!==443||!in_array($endpoint['path']??'',['','/'],true))throw new RuntimeException('Endpoint archivio pCloud non valido');
            $this->base='https://'.$endpoint['host'];$this->token=$contractToken;return;
        }
        $config=is_file($oauthFile)?json_decode((string)file_get_contents($oauthFile),true):[];
        if(empty($config['enabled'])||empty($config['access_token'])||!in_array($config['hostname']??'', ['api.pcloud.com','eapi.pcloud.com'],true))throw new RuntimeException('Archivio pCloud non abilitato');
        $this->base='https://'.$config['hostname'];$this->token=$config['access_token'];
    }
    public function api(string $method,array $params): array
    {
        if(!in_array($method,['listfolder','createfolder','createuploadlink','deleteuploadlink','stat','getfilelink'],true))throw new RuntimeException('Operazione archivio non consentita');
        $params['access_token']=$this->token;$ch=curl_init($this->base.'/'.$method);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($params),CURLOPT_RETURNTRANSFER=>true]);
        // Keep credentials out of URLs, redirect chains and exceptions.
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        $data=is_string($raw)?json_decode($raw,true):null;
        if($status!==200||!is_array($data)||($data['result']??-1)!==0)throw new RuntimeException('Operazione pCloud non confermata');
        return $data;
    }
    public function artistFolder(string $group,int $artistFolderId): array
    {
        $root=$this->api('listfolder',['path'=>$group==='TRB'?'/Discografia - TRB rec':'/Discografia - DDB','recursive'=>0])['metadata'];
        $artist=$this->api('listfolder',['folderid'=>$artistFolderId,'recursive'=>0])['metadata'];
        if(empty($artist['isfolder'])||(int)($artist['parentfolderid']??-1)!==(int)$root['folderid'])throw new RuntimeException('Cartella artista non associata alla discografia contrattuale');
        return $artist;
    }
    public function createUpload(int $artistFolderId,string $practiceId,string $slot): array
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$practiceId)||!in_array($slot,['identity_front','identity_back','tax_front','tax_back','proposal','final_pdf','signed_pdf','signature_audit'],true))throw new RuntimeException('Destinazione caricamento non valida');
        // One private folder per attempt makes remote verification unambiguous.
        $name='Adesione-'.$practiceId.'-'.$slot.'-'.bin2hex(random_bytes(6));
        $folder=$this->api('createfolder',['folderid'=>$artistFolderId,'name'=>$name])['metadata'];
        $upload=$this->api('createuploadlink',['folderid'=>$folder['folderid'],'expire'=>date('Y-m-d H:i:s',time()+7200),'maxfiles'=>1,'maxspace'=>10485760,'comment'=>'Documento adesione TRB rec']);
        return ['folder_id'=>(int)$folder['folderid'],'upload_link_id'=>(int)$upload['uploadlinkid'],'code'=>$upload['code'],'upload_endpoint'=>$this->base.'/uploadtolink','expires_at'=>gmdate('c',time()+7200),'max_bytes'=>10485760];
    }
    public function verifyUpload(int $folderId,int $uploadLinkId,bool $close=true,bool $allowXml=false): array
    {
        $folder=$this->api('listfolder',['folderid'=>$folderId,'recursive'=>0])['metadata'];
        $files=array_values(array_filter($folder['contents']??[],static fn($x)=>empty($x['isfolder'])));
        if(count($files)!==1)throw new RuntimeException('Caricamento non completato o ambiguo');
        $file=$files[0];$size=(int)($file['size']??0);$name=(string)($file['name']??'');
        if($size<1||$size>10485760||!preg_match($allowXml?'/\.(pdf|xml)$/i':'/\.(jpe?g|png|pdf)$/i',$name))throw new RuntimeException('Documento non ammesso');
        if($close)$this->closeUpload($uploadLinkId);
        return ['file_id'=>(int)$file['fileid'],'folder_id'=>$folderId,'hash'=>(string)$file['hash'],'size'=>$size,'name'=>$name];
    }
    public function url(int $fileId,int $folderId,string $hash): string
    {
        $file=$this->api('stat',['fileid'=>$fileId])['metadata'];
        if((int)$file['parentfolderid']!==$folderId||!hash_equals($hash,(string)$file['hash']))throw new RuntimeException('Documento archivio modificato');
        $link=$this->api('getfilelink',['fileid'=>$fileId]);$host=(string)($link['hosts'][0]??'');$path=(string)($link['path']??'');
        if(!preg_match('/^[a-z0-9.-]+\.pcloud\.com$/D',$host)||!str_starts_with($path,'/')||str_starts_with($path,'//'))throw new RuntimeException('Collegamento pCloud non valido');
        return 'https://'.$host.$path;
    }
    /** Stream remote bytes only for hash verification; nothing is saved on hosting. */
    public function verifyArtifact(array $grant,string $sha,int $artistFolderId): array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$sha))throw new RuntimeException('Impronta documento mancante');
        $folder=$this->api('stat',['folderid'=>(int)$grant['folder_id']])['metadata'];
        if((int)($folder['parentfolderid']??0)!==$artistFolderId)throw new RuntimeException('Archivio contratto non associato all’artista');
        $file=$this->verifyUpload((int)$grant['folder_id'],(int)$grant['upload_link_id'],false,true);
        $hash=hash_init('sha256');$bytes=0;$curl=curl_init($this->url($file['file_id'],$file['folder_id'],$file['hash']));
        curl_setopt_array($curl,[CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>45,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_WRITEFUNCTION=>static function($handle,$chunk)use($hash,&$bytes){$bytes+=strlen($chunk);if($bytes>10485760)return 0;hash_update($hash,$chunk);return strlen($chunk);}]);
        $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        if(!$ok||$status!==200||$bytes!==$file['size']||!hash_equals($sha,hash_final($hash)))throw new RuntimeException('Archiviazione remota non verificata');
        $this->closeUpload((int)$grant['upload_link_id']);
        return $file+['sha256'=>$sha,'artist_folder_id'=>$artistFolderId];
    }
    private function closeUpload(int $id): void
    {
        // A lost delete response must not invalidate verified bytes on a retry.
        // Every grant permits one file only and expires after two hours.
        try{$this->api('deleteuploadlink',['uploadlinkid'=>$id]);}catch(RuntimeException $e){}
    }
}
