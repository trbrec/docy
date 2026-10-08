<?php
declare(strict_types=1);
namespace TrbCrm;
final class Env
{
    public static array $settings=[];
    public static function get(string $key,?string $default=null):?string{return self::$settings[$key]??$default;}
}
require dirname(__DIR__).'/integrations/onboarding/crm/OnboardingPcloud.php';
function archive_check(bool $ok,string $name):void{if(!$ok)throw new \RuntimeException($name);}
function archive_reject(callable $call,string $name):void{try{$call();}catch(\RuntimeException){return;}throw new \RuntimeException($name);}
$fixture=tempnam(sys_get_temp_dir(),'onboarding-archive-');
try{
    file_put_contents($fixture,json_encode(['enabled'=>true,'access_token'=>'synthetic-demo-token','hostname'=>'api.pcloud.com']));
    Env::$settings=['PCLOUD_ACCESS_TOKEN'=>'synthetic-contract-token','PCLOUD_API_BASE'=>'https://eapi.pcloud.com/'];
    $archive=new OnboardingPcloud($fixture);
    $type=new \ReflectionClass($archive);
    archive_check($type->getProperty('base')->getValue($archive)==='https://eapi.pcloud.com','Contract archive region retained');
    archive_check($type->getProperty('token')->getValue($archive)==='synthetic-contract-token','Contract archive used before demo account');
    foreach(['http://eapi.pcloud.com','https://example.invalid','https://eapi.pcloud.com@evil.invalid','https://user@eapi.pcloud.com','https://eapi.pcloud.com:444','https://eapi.pcloud.com/path','https://eapi.pcloud.com?key=x','https://eapi.pcloud.com#fragment'] as $endpoint){
        Env::$settings['PCLOUD_API_BASE']=$endpoint;
        archive_reject(fn()=>new OnboardingPcloud($fixture),'Unsafe archive destination rejected');
    }
    Env::$settings=[];$archive=new OnboardingPcloud($fixture);
    archive_check($type->getProperty('token')->getValue($archive)==='synthetic-demo-token','Existing enabled OAuth connection remains supported');
    file_put_contents($fixture,json_encode(['enabled'=>false,'access_token'=>'synthetic-demo-token','hostname'=>'api.pcloud.com']));
    archive_reject(fn()=>new OnboardingPcloud($fixture),'Disabled OAuth connection remains disabled');
    Env::$settings=['PCLOUD_ACCESS_TOKEN'=>'synthetic-contract-token'];$archive=new OnboardingPcloud($fixture);
    archive_check($type->getProperty('base')->getValue($archive)==='https://eapi.pcloud.com','Established default contract region used');
    echo "Contract archive selection, OAuth fallback and credential destination guards verified.\n";
}finally{unlink($fixture);}
