<?php
require dirname(__DIR__).'/inc/trb-true-peak.php';
function tp_assert($ok,$message){if(!$ok)throw new RuntimeException($message);}
function tp_exec($command){exec($command.' 2>&1',$out,$code);return ['code'=>$code,'output'=>implode("\n",$out)];}
$ffmpeg=trim((string)shell_exec('command -v ffmpeg'));
tp_assert($ffmpeg!=='','FFmpeg is required for the real signal tests');
$path=tempnam(sys_get_temp_dir(),'trb-tp-');
try{
    foreach([44100,48000,96000] as $rate){
        $pcm='';
        for($n=0;$n<$rate;$n++){
            $envelope=min(1,$n/($rate*.1),($rate-1-$n)/($rate*.1));
            foreach([-.2,.2] as $db){
                $value=(int)round(pow(10,$db/20)*$envelope*sin(M_PI*$n/2+M_PI/4)*8388607);
                $pcm.=substr(pack('V',$value&0xffffff),0,3);
            }
        }
        file_put_contents($path,'RIFF'.pack('V',36+strlen($pcm)).'WAVEfmt '.pack('VvvVVvv',16,1,2,$rate,$rate*6,6,24).'data'.pack('V',strlen($pcm)).$pcm);
        $hash=hash_file('sha256',$path);
        $result=trb_true_peak_measure($path,$ffmpeg,$rate,2,'tp_exec');
        tp_assert(abs($result['channels_dbtp'][0]+.2)<.002,'Below-zero intersample peak was mismeasured');
        tp_assert(abs($result['channels_dbtp'][1]-.2)<.002,'Above-zero intersample peak was missed');
        tp_assert($result['maximum_dbtp']===$result['channels_dbtp'][1],'Wrong global/channel maximum');
        tp_assert($hash===hash_file('sha256',$path),'Original was modified');
        tp_assert(trb_true_peak_findings($result)['errors']===['MASTER_TRUE_PEAK_AT_ZERO'],'Above-zero true peak passed acceptance');
        echo $rate.' Hz true peaks: '.json_encode($result['channels_dbtp'])."\n";
    }
    foreach([-.2,-.000001] as $peak){
        tp_assert(trb_true_peak_findings(['verified'=>true,'complete_file'=>true,'maximum_dbtp'=>$peak])['errors']===[],'Negative true peak rejected');
    }
    foreach([0,.2,1.468] as $peak) tp_assert(trb_true_peak_findings(['verified'=>true,'complete_file'=>true,'maximum_dbtp'=>$peak])['errors']===['MASTER_TRUE_PEAK_AT_ZERO'],'Zero/positive true peak accepted');
    tp_assert(trb_true_peak_findings([])['errors']===['TRUE_PEAK_MEASUREMENT_UNAVAILABLE'],'Missing measurement passed');
    $silence=trb_true_peak_parse("[Parsed_astats_1 @ x] Channel: 1\n[Parsed_astats_1 @ x] Peak level dB: -inf\n",1);
    tp_assert($silence['silent']&&$silence['maximum_dbtp']===null,'Silence became zero dBTP');
    foreach(['','[Parsed_astats_1 @ x] Channel: 1'."\n".'[Parsed_astats_1 @ x] Peak level dB: nan'] as $invalid){
        $thrown=false;try{trb_true_peak_parse($invalid,2);}catch(RuntimeException $e){$thrown=true;}
        tp_assert($thrown,'Incomplete or invalid output accepted');
    }
    $thrown=false;try{trb_true_peak_measure($path,$ffmpeg,44100,2,static fn($command)=>['code'=>1,'output'=>'']);}catch(RuntimeException $e){$thrown=true;}
    tp_assert($thrown,'Failed full-file scan accepted');
}finally{unlink($path);}
echo "True peak signal and boundary tests passed.\n";
