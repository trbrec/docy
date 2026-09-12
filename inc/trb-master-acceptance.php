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
    if (is_array($result) && !empty($result['errors']) && !array_intersect($result['errors'],array('MASTER_TRUE_PEAK_AT_ZERO','MASTER_SAMPLE_PEAK_AT_ZERO'))) $result=false;
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
    if (!array_intersect($result['errors'],array('MASTER_TRUE_PEAK_AT_ZERO','MASTER_SAMPLE_PEAK_AT_ZERO'))) return new WP_Error('MASTER_CHECK_UNAVAILABLE','La verifica del master non è temporaneamente disponibile. Il file è conservato: riprova dalla stessa pratica.');
    $message='Master rifiutato: '.$name.'. Il picco campione deve restare sotto 0 dBFS e il true peak sotto 0 dBTP. Esporta un nuovo master con margine sotto zero e sostituisci il WAV. Non basta rinominare il file.';
    $values='Picco campione: '.number_format((float)$result['sample_peak'],6,',','').' dBFS; true peak: '.number_format((float)$result['true_peak'],6,',','').' dBTP.';
    $user=wp_get_current_user();
    if ($user->ID && is_email($user->user_email) && function_exists('trb_resource_queue_recipient_email')) {
        $body='<p>Gentile '.esc_html(function_exists('trb_resource_artist_legal_greeting_name') ? trb_resource_artist_legal_greeting_name($user) : ($user->first_name ?: 'Artista')).',</p><p>non possiamo accettare il master <strong>'.esc_html($name).'</strong>: raggiunge o supera il limite di picco consentito.</p><p>'.esc_html($values).'</p><p>Il picco campione deve restare sotto 0 dBFS e il true peak sotto 0 dBTP, anche per brani già pubblicati. Prepara un nuovo master con margine sotto zero e sostituisci il file prima di completare l’invio. I dati della bozza restano conservati.</p>';
        $body .= trb_master_premaster_email_guidance($user);
        if(function_exists('trb_resource_artist_email_signature'))$body.=trb_resource_artist_email_signature();
        $headers=strcasecmp($user->user_email,'andrea.tognassi@trbrec.com')===0?array():array('Cc: andrea.tognassi@trbrec.com');
        trb_resource_queue_recipient_email('master-rejected-'.$user->ID.'-'.$hash,$user->user_email,'Master da correggere: '.$name,$body,false,$headers);
    }
    return new WP_Error('MASTER_PEAK_REJECTED',$message.' '.$values,$result);
}

/** Explain the included mastering route only to eligible artist profiles. */
function trb_master_premaster_email_guidance($user) {
    if (!function_exists('trb_portal_user_profile') || !function_exists('trb_portal_profile_has_service')) return '';
    $profile=trb_portal_user_profile($user);
    if ($profile==='dds' || !trb_portal_profile_has_service('mastering',$profile)) return '';
    $settings=function_exists('trb_analysis_settings') ? trb_analysis_settings() : array();
    $peak=(float)($settings['premaster_peak_max'] ?? -6);
    return '<p><strong>Puoi affidare il mastering a noi: è incluso nel tuo contratto.</strong> In alternativa al master corretto, esporta dal progetto un vero pre-master e, nello stato del file audio, seleziona «Invio un pre-master e richiedo il mastering del brano».</p><p>Consegna un WAV stereo, minimo 44.100 Hz / 16 bit, preferibilmente 48.000 Hz / 24 bit se disponibili nella sessione originale. Il pre-master deve essere privo di clipping, normalizzazione automatica e limiter aggressivi; consigliamo un true peak non superiore a '.esc_html(number_format($peak,1,',','')).' dBTP. Non occorre raggiungere un valore LUFS prestabilito.</p><p>Non basta rinominare il WAV rifiutato, abbassare il volume di un file già distorto o cambiare soltanto la selezione nel modulo: occorre esportare nuovamente il mix senza il trattamento che ha provocato il clipping. Sostituisci il WAV nel modulo e ripeti «Verifica e continua», poi conferma il riepilogo. Se hai riaperto la pagina, usa «Riprendi il caricamento di questa pratica» dal catalogo e aggiungi solo gli allegati indicati come mancanti. Ci occuperemo noi del mastering dopo i controlli sul materiale.</p>';
}
