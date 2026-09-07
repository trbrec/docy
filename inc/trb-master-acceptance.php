<?php
/** Server-side acceptance rule, independent of copyright and catalogue status. */
if (!defined('ABSPATH')) exit;
require_once __DIR__.'/trb-true-peak.php';
require_once __DIR__.'/trb-pcm-full-scale.php';
function trb_master_acceptance_errors($measurement, $pcm) {
    $errors=trb_true_peak_findings($measurement)['errors'];
    if (empty($pcm['verified'])) $errors[]='PCM_MEASUREMENT_UNAVAILABLE';
    elseif (($pcm['full_scale_samples']??0)>0 || (isset($pcm['peak_level_dbfs']) && $pcm['peak_level_dbfs']>=0)) $errors[]='MASTER_SAMPLE_PEAK_AT_ZERO';
    return array_values(array_unique($errors));
}
function trb_master_upload_check($path, $name, $status='mastered') {
    if ($status==='mastering') return true;
    $hash=hash_file('sha256',$path);
    if (!$hash) return new WP_Error('MASTER_CHECK_UNAVAILABLE');
    $key='trb_master_v1_'.$hash;
    $result=get_transient($key);
    if (!is_array($result)) {
        $spec=trb_portal_wav_spec($path);
        if (is_wp_error($spec)) return new WP_Error('MASTER_CHECK_UNAVAILABLE');
        try {
            $pcm=trb_pcm_full_scale($path);
            $ffmpeg=trb_analysis_binary('ffmpeg');
            if (!$ffmpeg) throw new RuntimeException('FFmpeg unavailable');
            $peak=trb_true_peak_measure($path,$ffmpeg,(int)$spec['sample_rate'],(int)$spec['channels'],'trb_analysis_exec');
            $result=array('errors'=>trb_master_acceptance_errors($peak,$pcm),'true_peak'=>$peak['maximum_dbtp'],'sample_peak'=>$pcm['peak_level_dbfs'],'hash'=>$hash);
            set_transient($key,$result,DAY_IN_SECONDS);
        } catch (RuntimeException $e) { return new WP_Error('MASTER_CHECK_UNAVAILABLE'); }
    }
    if (!$result['errors']) return true;
    $message='Master rifiutato: '.$name.'. Il picco campione deve restare sotto 0 dBFS e il true peak sotto 0 dBTP. Esporta un nuovo master con margine sotto zero e sostituisci il WAV. Non basta rinominare il file.';
    $values='Picco campione: '.number_format((float)$result['sample_peak'],6,',','').' dBFS; true peak: '.number_format((float)$result['true_peak'],6,',','').' dBTP.';
    $user=wp_get_current_user();
    if ($user->ID && is_email($user->user_email) && function_exists('trb_resource_queue_recipient_email')) {
        $body='<p>Ciao '.esc_html($user->first_name?:$user->display_name).',</p><p>non possiamo accettare il master <strong>'.esc_html($name).'</strong>: raggiunge o supera il limite di picco consentito.</p><p>'.esc_html($values).'</p><p>Il picco campione deve restare sotto 0 dBFS e il true peak sotto 0 dBTP, anche per brani già pubblicati. Prepara un nuovo master con margine sotto zero e sostituisci il file prima di completare l’invio. I dati della bozza restano conservati.</p>';
        if(function_exists('trb_resource_artist_email_signature'))$body.=trb_resource_artist_email_signature();
        $headers=strcasecmp($user->user_email,'andrea.tognassi@trbrec.com')===0?array():array('Cc: andrea.tognassi@trbrec.com');
        trb_resource_queue_recipient_email('master-rejected-'.$user->ID.'-'.$hash,$user->user_email,'Master da correggere: '.$name,$body,false,$headers);
    }
    return new WP_Error('MASTER_PEAK_REJECTED',$message.' '.$values,$result);
}
