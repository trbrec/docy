<?php
/** Full-file true-peak estimate. No gain, normalization, limiting or channel mix. */
function trb_true_peak_parse($text, $channels) {
    preg_match_all('/\[Parsed_astats_[^\]]+\]\s*Channel:\s*(\d+)\s*\R\[Parsed_astats_[^\]]+\]\s*Peak level dB:\s*([-+0-9.eE]+|-inf)\s*(?:\R|$)/', $text, $matches, PREG_SET_ORDER);
    $peaks=[];
    foreach($matches as $match){
        $channel=(int)$match[1];
        if($channel<1||$channel>$channels||array_key_exists($channel,$peaks))throw new RuntimeException('Canali true peak ambigui.');
        if($match[2]==='-inf')$peaks[$channel]=null;
        elseif(is_numeric($match[2])&&is_finite((float)$match[2]))$peaks[$channel]=(float)$match[2];
        else throw new RuntimeException('Misura true peak non valida.');
    }
    if(count($peaks)!==$channels)throw new RuntimeException('Misura true peak incompleta.');
    ksort($peaks);$finite=array_filter($peaks,static fn($v)=>$v!==null);
    return ['channels_dbtp'=>array_values($peaks),'maximum_dbtp'=>$finite?max($finite):null,'silent'=>!$finite];
}

function trb_true_peak_measure($path, $ffmpeg, $sample_rate, $channels, callable $execute) {
    if($sample_rate<8000||$sample_rate>192000||$channels<1||$channels>64)throw new RuntimeException('Formato non supportato per il true peak.');
    $filter='aresample='.($sample_rate*16).':resampler=swr:filter_size=128:phase_shift=10:cutoff=1:osf=dbl:dither_method=none,astats=reset=0:measure_perchannel=Peak_level:measure_overall=none';
    $command='LC_ALL=C '.escapeshellarg($ffmpeg).' -hide_banner -nostats -nostdin -xerror -i '.escapeshellarg($path).' -map 0:a:0 -af '.escapeshellarg($filter).' -c:a pcm_f64le -f null -';
    $result=$execute($command);
    if(($result['code']??1)!==0)throw new RuntimeException('Scansione true peak non completata.');
    $measurement=trb_true_peak_parse($result['output']??'',(int)$channels);
    return $measurement+['verified'=>true,'method'=>'ffmpeg-swr-f64-16x-128tap-v1','oversampling'=>16,'sample_rate'=>(int)$sample_rate,'analysis_sample_rate'=>$sample_rate*16,'reported_resolution_db'=>0.000001,'complete_file'=>true];
}

/** Exact zero cannot be distinguished from sub-micro-dB rounding: review it. */
function trb_true_peak_findings($measurement) {
    if(empty($measurement['verified'])||empty($measurement['complete_file']))return ['errors'=>['TRUE_PEAK_MEASUREMENT_UNAVAILABLE'],'warnings'=>[]];
    $peak=$measurement['maximum_dbtp']??null;
    if($peak===null)return ['errors'=>empty($measurement['silent'])?['TRUE_PEAK_MEASUREMENT_UNAVAILABLE']:[],'warnings'=>[]];
    if(!is_numeric($peak)||!is_finite((float)$peak))return ['errors'=>['TRUE_PEAK_MEASUREMENT_UNAVAILABLE'],'warnings'=>[]];
    if($peak>0.0000005)return ['errors'=>['TRUE_PEAK_ABOVE_ZERO'],'warnings'=>[]];
    return ['errors'=>[],'warnings'=>abs($peak)<=0.0000005?['TRUE_PEAK_ZERO_REVIEW']:[]];
}
