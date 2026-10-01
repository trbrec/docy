<?php
declare(strict_types=1);
namespace TrbCrm;

/** Purpose-bound JSON only; caller files are never uploaded through this transport. */
final class OnboardingTransport
{
    public static function signed(string $url,string $secret,array $payload,string $scope='onboarding-v1'): array
    {
        $parts=parse_url($url);
        if(($parts['scheme']??'')!=='https'||!in_array($parts['host']??'',['artist.trbrec.com','crm.trbrec.com','store.trbrec.com'],true)||isset($parts['user'])||isset($parts['pass'])||strlen($secret)<32)throw new \RuntimeException('Collegamento adesioni non configurato');
        $body=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$time=(string)time();$nonce=bin2hex(random_bytes(16));
        $headers=['Content-Type: application/json','Accept: application/json','X-TRB-Timestamp: '.$time,'X-TRB-Nonce: '.$nonce,'X-TRB-Signature: sha256='.hash_hmac('sha256',$scope.'|'.$time.'|'.$nonce.'|'.$body,$secret)];
        return self::post($url,$body,$headers,false);
    }
    public static function script(string $url,string $secret,array $payload): array
    {
        $parts=parse_url($url);
        if(($parts['scheme']??'')!=='https'||($parts['host']??'')!=='script.google.com'||!preg_match('#^/macros/s/[A-Za-z0-9_-]+/exec$#',$parts['path']??'')||$secret==='')throw new \RuntimeException('Collegamento contratti non configurato');
        $payload['secret']=$secret;$payload['confirm']=true;
        return self::post($url,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),['Content-Type: application/json','Accept: application/json'],true);
    }
    private static function post(string $url,string $body,array $headers,bool $follow): array
    {
        $curl=curl_init($url);curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>55,CURLOPT_FOLLOWLOCATION=>$follow,CURLOPT_MAXREDIRS=>3,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>$headers]);
        $raw=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);$result=is_string($raw)?json_decode($raw,true):null;
        if($status<200||$status>=300||!is_array($result)||isset($result['error'])||($result['success']??true)!==true)throw new \RuntimeException('Il sistema remoto non ha confermato l’operazione. La pratica resta recuperabile; nessuna attivazione automatica.');
        return $result;
    }
    public static function verify(string $body,string $time,string $nonce,string $signature,string $secret,string $scope='onboarding-v1'): bool
    {
        if(strlen($secret)<32||!ctype_digit($time)||abs(time()-(int)$time)>300||!preg_match('/^[a-f0-9]{32}$/D',$nonce)||strlen($body)>100000)return false;
        return hash_equals('sha256='.hash_hmac('sha256',$scope.'|'.$time.'|'.$nonce.'|'.$body,$secret),$signature);
    }
}
