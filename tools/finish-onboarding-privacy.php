<?php
/** Revision-bound CLI publication. No payment, email or signature dispatch. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();fwrite(STDERR,"Artist privacy configuration unconfirmed.\n");exit(1);});
$revision=$argv[1]??'';$theme=dirname(__DIR__);$verify=in_array('--verify',$argv,true);
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents($theme.'/.trb-deployed-sha'))!==$revision)throw new RuntimeException('Revision guard');
$notice= <<<'NOTICE'
<div id="trb-onboarding-privacy">
<p><strong>Ultimo aggiornamento: 2 ottobre 2026</strong></p>
<p>Questa informativa riguarda l’adesione alle proposte contrattuali di TRB rec e l’utilizzo del Portale Artisti su artist.trbrec.com, ai sensi del Regolamento (UE) 2016/679 (GDPR).</p>
<h2>1. Titolare e contatti</h2>
<p>Il titolare del trattamento è <strong>TRB rec di Tognassi Andrea</strong>, Via San Giorgio 11/A, 25038 Rovato (BS), Italia, P. IVA IT02846170989, REA BS 483571-2008. Per informazioni e richieste sui tuoi dati: <a href="mailto:info@trbrec.com">info@trbrec.com</a>.</p>
<h2>2. Quali dati trattiamo</h2>
<p>Trattiamo nome, cognome, nome d’arte, email, telefono, indirizzo, data di nascita, codice fiscale e gli eventuali dati di fatturazione; il fronte della carta d’identità e della tessera sanitaria o del tesserino del codice fiscale; dati della proposta, della formula scelta, dei versamenti e delle scadenze; contratto, firme e relative prove; dati dell’account, materiali e richieste inviati per i servizi contrattuali. Per il funzionamento e la sicurezza sono trattati anche dati tecnici di sessione, accesso e navigazione.</p>
<p>La tessera sanitaria è richiesta per riscontrare i dati identificativi e il codice fiscale. Non sono richiesti referti, diagnosi o informazioni sulle prestazioni sanitarie. Carica soltanto i documenti richiesti, completi e leggibili.</p>
<h2>3. Finalità e basi giuridiche</h2>
<ul><li><strong>Adesione e contratto:</strong> verificare la corrispondenza tra documenti e dati contrattuali, la maggiore età e la scadenza della carta d’identità, predisporre e sottoscrivere il contratto, gestire l’account e fornire i servizi. La base giuridica è l’esecuzione del contratto o delle misure precontrattuali richieste dall’interessato (art. 6, par. 1, lett. b GDPR).</li>
<li><strong>Amministrazione e fiscalità:</strong> gestire versamenti, fatturazione e obblighi amministrativi e fiscali, sulla base degli obblighi di legge (art. 6, par. 1, lett. c).</li>
<li><strong>Sicurezza e tutela:</strong> prevenire abusi e accessi non autorizzati, gestire contestazioni e tutelare i diritti delle parti, sulla base del legittimo interesse del titolare (art. 6, par. 1, lett. f).</li></ul>
<p>La verifica dei dati supporta la gestione della pratica. L’approvazione dell’adesione e l’invio del documento definitivo alla firma richiedono la verifica e l’approvazione di TRB rec. In caso di dati discordanti puoi chiedere chiarimenti o una revisione scrivendo ai contatti indicati. L’accettazione definitiva del contratto è decisa da TRB rec dopo la revisione della pratica.</p>
<h2>4. Dati necessari e presa visione</h2>
<p>I campi e i documenti indicati come obbligatori sono necessari per completare questa procedura. Senza di essi non è possibile proseguire. Partita IVA, SDI e PEC sono richiesti solo quando applicabili o indicati come facoltativi. La casella di presa visione registra la lettura dell’informativa e non costituisce un consenso a comunicazioni promozionali. Questa procedura non richiede il consenso al marketing.</p>
<h2>5. Destinatari, archiviazione e pagamenti</h2>
<p>I dati sono accessibili al personale autorizzato di TRB rec e, per quanto necessario alle rispettive attività, ai fornitori di hosting e manutenzione, gestione delle comunicazioni, lettura e confronto dei dati presenti nei documenti, archiviazione privata, firma elettronica e pagamenti. I documenti dell’adesione sono conservati nell’archivio contrattuale privato su Google Drive; il servizio di firma è OTPService. PayPal e Stripe gestiscono i pagamenti secondo il circuito scelto e le proprie informative. TRB rec non riceve i dati completi della carta dai campi protetti del fornitore di pagamento.</p>
<p>I fornitori operano come responsabili del trattamento o autonomi titolari secondo il ruolo effettivo. I dati necessari possono essere comunicati anche a consulenti amministrativi e legali o autorità competenti nei casi previsti dalla legge o per la tutela dei diritti. Puoi richiedere informazioni sui destinatari dei tuoi dati ai contatti indicati.</p>
<h2>6. Trasferimenti internazionali</h2>
<p>Alcuni fornitori possono trattare dati fuori dallo Spazio Economico Europeo. Come descritto nelle informative aziendali, tali trasferimenti sono disciplinati mediante decisioni di adeguatezza, clausole contrattuali standard o altri strumenti previsti dagli articoli 44 e seguenti del GDPR, secondo il servizio utilizzato. Per informazioni sulle garanzie applicabili ai tuoi dati puoi scrivere a info@trbrec.com.</p>
<h2>7. Conservazione</h2>
<p>I dati sono conservati per il tempo necessario alle finalità indicate. La documentazione amministrativa, fiscale e contrattuale è normalmente conservata per dieci anni o per il diverso termine imposto dalla legge. Le copie dei documenti identificativi e i dati di verifica sono conservati per il tempo necessario alla gestione della pratica e del rapporto e alle eventuali esigenze di tutela, senza estendere automaticamente a ogni copia i termini dei documenti fiscali. I dati dell’account e i materiali seguono la durata del rapporto e le esigenze dei servizi richiesti; i log tecnici sono conservati per periodi proporzionati alla sicurezza. Le richieste di cancellazione sono valutate tenendo conto di obblighi di legge, rapporti in corso e tutela dei diritti.</p>
<h2>8. Diritti e assistenza</h2>
<p>Nei casi previsti dal GDPR puoi chiedere accesso, rettifica, cancellazione, limitazione, portabilità e opposizione al trattamento. Puoi revocare eventuali consensi senza pregiudicare i trattamenti già effettuati. Scrivi a <a href="mailto:info@trbrec.com">info@trbrec.com</a>: potremo chiederti le informazioni necessarie a verificare la tua identità. Puoi proporre reclamo al <a href="https://www.garanteprivacy.it/" rel="noopener">Garante per la protezione dei dati personali</a>.</p>
<h2>9. Sessioni e altre informative</h2>
<p>La procedura utilizza cookie tecnici per la sessione e la sicurezza. Le informative del <a href="https://trbrec.com/privacy-policy/" rel="noopener">sito aziendale</a> e dello <a href="https://store.trbrec.com/privacy-policy/" rel="noopener">store</a> descrivono i rispettivi servizi. Questa informativa integra tali testi per il percorso di adesione e il Portale Artisti. Le modifiche successive riportano una nuova data di aggiornamento.</p>
</div>
NOTICE;
$operations= <<<'OPERATIONS'
$id=(int)get_option('wp_page_for_privacy_policy');$page=$id?get_post($id):get_page_by_path('privacy-policy');
if(!$verify){
    // Adopt only the stock draft or a notice previously installed by this command.
    if($page&&get_post_meta($page->ID,'_trb_onboarding_privacy_version',true)!=='20261002c'
        &&!($page->post_status==='draft'&&str_contains($page->post_content,'privacy-policy-tutorial')))throw new RuntimeException('Existing authored policy requires review');
    $data=['post_title'=>'Informativa privacy · Adesione e Portale Artisti','post_name'=>'privacy-policy','post_type'=>'page','post_status'=>'publish','post_content'=>$notice,'comment_status'=>'closed','ping_status'=>'closed'];
    if($page)$data['ID']=$page->ID;
    $id=wp_insert_post(wp_slash($data),true);if(is_wp_error($id)||!$id)throw new RuntimeException('Privacy publication failed');
    update_post_meta($id,'_trb_onboarding_privacy_version','20261002c');
    update_option('wp_page_for_privacy_policy',$id);
    clean_post_cache($id);
}
$page=$id?get_post($id):null;$url=get_privacy_policy_url();
$result=['privacy_published'=>$page&&$page->post_status==='publish',
    'privacy_content_matches'=>$page&&hash_equals(hash('sha256',$notice),hash('sha256',$page->post_content)),
    'privacy_option_matches'=>$page&&(int)get_option('wp_page_for_privacy_policy')===$page->ID,
    'privacy_url_present'=>$url!==''];
$response=$url!==''?wp_remote_get(add_query_arg('trb_privacy_check','20261002c',$url),['timeout'=>30,'redirection'=>0,'headers'=>['Cache-Control'=>'no-cache']]):new WP_Error('missing_url');
$result['privacy_public_http_200']=!is_wp_error($response)&&wp_remote_retrieve_response_code($response)===200;
$result['privacy_public_content_present']=!is_wp_error($response)&&str_contains(wp_remote_retrieve_body($response),'trb-onboarding-privacy');
$response=wp_remote_get('https://artist.trbrec.com/adesione/?trb_privacy_check=20261002c',['timeout'=>30,'redirection'=>0,'headers'=>['Cache-Control'=>'no-cache']]);
$html=is_wp_error($response)?'':wp_remote_retrieve_body($response);
$result['candidate_public_http_200']=!is_wp_error($response)&&wp_remote_retrieve_response_code($response)===200;
$result['candidate_privacy_link']=$url!==''&&str_contains($html,esc_url($url));
$result['candidate_instant_payment_copy']=str_contains($html,'PayPal o una carta di credito/debito')&&!str_contains($html,'Per il bonifico');
while(ob_get_level())ob_end_clean();echo json_encode($result);
OPERATIONS;
$code='$_SERVER["HTTP_HOST"]="artist.trbrec.com";$_SERVER["REQUEST_URI"]="/";$_SERVER["HTTPS"]="on";define("WP_USE_THEMES",false);define("DISABLE_WP_CRON",true);ob_start();require "/home/customer/www/artist.trbrec.com/public_html/wp-load.php";$notice='.var_export($notice,true).';$verify='.($verify?'true':'false').';'.$operations;
exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>/dev/null',$lines,$status);
$result=$status===0?json_decode(implode("\n",$lines),true):null;
if(!is_array($result)||!$result||in_array(false,$result,true))throw new RuntimeException('Privacy verification failed');
while(ob_get_level())ob_end_clean();echo json_encode(['success'=>true,'checks'=>$result])."\n";
