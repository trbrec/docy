<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$private='/home/customer/www/crm.trbrec.com/private';
$state=['installed'=>false,'at'=>gmdate('c'),'local_time'=>'19:00','timezone'=>'Europe/Rome'];
function giuliaProcess(array $args,string $input=''):array{
    $pipes=[];$p=@proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('scheduler_unavailable');
    fwrite($pipes[0],$input);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);
    return ['status'=>proc_close($p),'out'=>$out,'err'=>$err];
}
try{
    $lock=fopen($private.'/giulia-report-scheduler.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('scheduler_busy');
    $zone=giuliaProcess(['date','+%Z']);
    if($zone['status']!==0||!in_array(trim($zone['out']),['UTC','GMT'],true))throw new RuntimeException('scheduler_timezone_unverified');
    $r=giuliaProcess(['crontab','-l']);
    if($r['status']!==0&&!($r['status']===1&&preg_match('/^no crontab for [a-zA-Z0-9_-]+\s*$/D',trim($r['err']))))throw new RuntimeException('scheduler_unavailable');
    $before=$r['status']===0?$r['out']:'';
    if(preg_match('/^\s*(?:CRON_TZ|TZ)\s*=/m',$before))throw new RuntimeException('scheduler_timezone_override');
    $tag='# TRB_GIULIA_REPORT_19_ROME';
    $rows=explode("\n",rtrim($before,"\n"));$matches=array_values(array_filter($rows,static fn($v)=>str_contains($v,$tag)));
    if(count($matches)>1)throw new RuntimeException('duplicate_managed_job');
    $runner=$private.'/run-giulia-report.php';
    $line='0 17,18 * * * '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($runner).' > /dev/null 2>&1 '.$tag;
    if($matches&&!preg_match("~^0 17,18 \\* \\* \\* .+run-giulia-report\\.php' > /dev/null 2>&1 # TRB_GIULIA_REPORT_19_ROME$~D",$matches[0]))throw new RuntimeException('unknown_managed_job');
    $after=$matches?str_replace($matches[0],$line,$before):($before===''?'':rtrim($before,"\n")."\n").$line."\n";
    if($after!==$before){
        $again=giuliaProcess(['crontab','-l']);
        $current=$again['status']===0?$again['out']:'';
        if($current!==$before)throw new RuntimeException('scheduler_changed');
        if(giuliaProcess(['crontab','-'],$after)['status']!==0)throw new RuntimeException('scheduler_write_failed');
    }
    $verify=giuliaProcess(['crontab','-l']);
    if($verify['status']!==0||$verify['out']!==$after)throw new RuntimeException('scheduler_verify_failed');
    $state['installed']=true;
}catch(Throwable $e){$state['code']=$e->getMessage();}
file_put_contents($private.'/giulia-report-scheduler.json',json_encode($state,JSON_PRETTY_PRINT),LOCK_EX);chmod($private.'/giulia-report-scheduler.json',0600);
echo $state['installed']?"GIULIA_REPORT_SCHEDULE_OK 19:00 Europe/Rome\n":"GIULIA_REPORT_SCHEDULE_PENDING ".($state['code']??'')."\n";
