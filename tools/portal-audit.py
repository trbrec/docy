#!/usr/bin/env python3
"""Dependency-free release/permissions regression audit for the TRB portal."""

from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
PORTAL = (ROOT / "inc/trb-artist-portal.php").read_text(encoding="utf-8")
RESOURCE = (ROOT / "inc/trb-resource-monitor.php").read_text(encoding="utf-8")
ANALYSIS = (ROOT / "inc/trb-release-analysis.php").read_text(encoding="utf-8")
OWNER = (ROOT / "inc/trb-owner-dashboard.php").read_text(encoding="utf-8")
PCLOUD = (ROOT / "inc/trb-release-pcloud-archive.php").read_text(encoding="utf-8")
BRIDGE = (ROOT / "inc/trb-release-spreadsheet-bridge.php").read_text(encoding="utf-8")
DEMO = (ROOT / "inc/trb-demo-automation.php").read_text(encoding="utf-8")
DEPLOY = (ROOT / "inc/trb-auto-deploy.php").read_text(encoding="utf-8")
PASSWORD = (ROOT / "template-artist-password.php").read_text(encoding="utf-8")
LOGIN = (ROOT / "template-artist-login.php").read_text(encoding="utf-8")
REGISTRATION = (ROOT / "template-artist-registration.php").read_text(encoding="utf-8")
SUPPORT = (ROOT / "template-artist-support.php").read_text(encoding="utf-8")
DEMO_JS = (ROOT / "assets/js/trb-demo-evaluation.js").read_text(encoding="utf-8")
RELEASE_JS = (ROOT / "assets/js/trb-release-upload.js").read_text(encoding="utf-8")

checks: list[tuple[str, bool]] = []


def check(name: str, condition: bool) -> None:
    checks.append((name, bool(condition)))


for profile, role in {
    "dds": "artista_a",
    "ddb12": "artista_ddb12",
    "ddb": "artista_b",
    "ddb_trb": "artista_c",
    "trb": "artista_d",
}.items():
    check(f"profilo {profile} mappato al ruolo canonico", bool(re.search(
        rf"'{profile}'\s*=>\s*array\(.*?'role'\s*=>\s*'{re.escape(role)}'",
        PORTAL,
        re.S,
    )))

for profile in ("ddb", "ddb_trb", "trb"):
    check(f"{profile} con release illimitate", bool(re.search(
        rf"'{profile}'\s*=>\s*array\([^\n]+?'release_limit'\s*=>\s*'unlimited'",
        PORTAL,
    )))

check("DDS e DDB12 limitati esclusivamente dal profilo mensile", "array( 'dds', 'ddb12' )" in PORTAL)
check("DDS escluso da pitching e playlist incluse", "'editorial_pitching'    => $service( 'Pitching editoriale', $development )" in PORTAL and "'owned_playlists'       => $service( 'Inserimento nelle playlist proprietarie', $development )" in PORTAL)
check("DDS escluso da formazione mentoring e attestato", "'training'              => $service( 'Formazione e Knowledge Hub', array( 'ddb12', 'ddb', 'ddb_trb' ) )" in PORTAL and "'dds' => array( 'duration_months' => 1, 'release_limit' => 'one_per_month', 'training_level' => 'not_applicable'" in PORTAL)
check("solo account QA spotify4 forzato sul profilo TRB", "'spotify4@trbrec.com' === $qa_identity" in PORTAL and "array( 'spotify2', 'spotify3', 'spotify4' )" not in PORTAL)
check("spotify4 ha fixture contrattuale e modulo release verificabile", "trb_release_bridge_seed_spotify4_qa_contract" in BRIDGE and "TRB-QA-SPOTIFY4" in BRIDGE and "trb_portal_release_qa_health_payload" in PORTAL)
check("spotify4 usa conferma collaudo senza perdere il flusso TRB", "trb_portal_is_release_qa_account" in PORTAL and "current_user_can( 'manage_options' ) || trb_portal_is_release_qa_account()" in PORTAL)
check("matrice QA copre tutti i cinque gruppi release", "trb_portal_release_group_health_payload" in PORTAL and all(f"'{login}' => '{profile}'" in PORTAL for login, profile in (("spotify1", "dds"), ("spotify6", "ddb12"), ("spotify2", "ddb"), ("spotify3", "ddb_trb"), ("spotify4", "trb"))))
check("fixture contrattuali QA coprono tutti i cinque gruppi", "trb_release_bridge_seed_release_group_qa_contracts" in BRIDGE and all(f"'{login}' => array( 'profile' => '{profile}'" in BRIDGE for login, profile in (("spotify1", "dds"), ("spotify6", "ddb12"), ("spotify2", "ddb"), ("spotify3", "ddb_trb"), ("spotify4", "trb"))))
check("ruolo canonico prioritario rispetto agli alias", "A current canonical role always wins" in PORTAL)
check("normalizzazione dei ruoli al salvataggio amministrativo", "trb_portal_normalize_artist_role_after_admin_save" in PORTAL)
check("DDB-TRB aggiorna il profilo canonico al passaggio TRB", "'_trb_artist_contract_profile', 'trb'" in BRIDGE)
check("creazione release protetta da nonce", "wp_verify_nonce( $release_nonce" in PORTAL)
check("creazione release protetta da idempotency token", "_trb_release_submission_token" in PORTAL)
check("numero e periodo contrattuale bloccano coerentemente interfaccia e server", PORTAL.count("trb_release_bridge_contract_term_dates") >= 3 and "Dati contrattuali da verificare" in PORTAL)
check("limite mensile applicato solo a DDS/DDB12", "function trb_portal_monthly_release_profile" in PORTAL)
check("contatori mensile e annuale includono solo contratti firmati", "trb_portal_signed_release_ids" in PORTAL and "_trb_contract_state', 'value' => 'signed'" in PORTAL and PORTAL.count("trb_portal_release_contract_signed_timestamp") >= 3)
check("quota attribuita alla data effettiva di firma", "_trb_contract_signed_at" in PORTAL and "first day of this month 00:00:00" in PORTAL)
check("richieste non firmate non bloccano nuove release", "signed_contracts_only" in PORTAL and "non impediscono di inviare una nuova release" in PORTAL)
check("marcatore mensile usato solo come lock temporaneo", "update_user_meta( $user_id, $monthly_reservation_key, (string) $release_id )" not in PORTAL and "trb_portal_migrate_signed_contract_release_limits" in PORTAL)
check("avviso mensile mostra firma conteggiata e data di riapertura", PORTAL.count("Raggiunto limite mensile di release contrattualizzate") >= 2 and "Potrai contrattualizzare una nuova release dal" in PORTAL and "trb_portal_monthly_next_reset_label" in PORTAL)
check("avviso annuale distingue le dodici release", PORTAL.count("Raggiunto il limite annuale di 12 release") >= 2 and "annual_limit_reached" in PORTAL)
check("copertina inclusa esclusivamente per DDB-TRB e TRB", "'cover_artwork'         => $service( 'Copertina grafica professionale', $press_roster" in PORTAL)
check("release con copertina inclusa offre caricamento o richiesta", "trb_portal_render_release_cover_input" in PORTAL and "trb_release_cover_mode" in PORTAL and "Richiedo la realizzazione della copertina inclusa" in PORTAL)
check("richiesta copertina valida brief e reference lato server", "invalid_cover_request" in PORTAL and "strlen( $cover_brief ) < 40" in PORTAL and "cover_reference" in PORTAL)
check("richiesta copertina crea pratica collegata e avvisa TRB", "_trb_cover_release_id" in PORTAL and "cover-request-" in PORTAL and "Nuova richiesta copertina dalla release" in PORTAL)
check("copertina definitiva collegabile alla stessa release", "trb_portal_store_final_release_cover" in PORTAL and "trb_portal_attach_release_cover" in PORTAL and "_trb_release_cover_status" in PORTAL)
check("approvazione bloccata finche manca la copertina definitiva", "approve_blocked_cover" in RESOURCE and "cover_creation_pending" in RESOURCE and "trb_portal_release_has_final_cover" in RESOURCE)
check("reference copertina archiviata correttamente su pCloud", "00)_Reference_copertina" in PCLOUD)
check("upload WAV validato lato server", "trb_portal_validate_release_upload( $audio, 'audio' )" in PORTAL)
check("durata WAV verificata con tolleranza di un secondo", "audio_duration_mismatch" in PORTAL and "> 1.0" in PORTAL)
check("trasferimento pCloud verificato prima dell'analisi", "archived_pending_analysis" in PCLOUD and "verified" in PCLOUD)
check("retry pCloud schedulato", "trb_release_pcloud_retry" in PCLOUD)
recovery_function = RESOURCE.split("function trb_resource_recover_release_pipeline()", 1)[1].split("add_action( 'trb_resource_recover_release_pipeline'", 1)[0]
check("recupero pipeline esteso a tutti gli artisti", "author__in" not in recovery_function and "meta_value'   => 'Ruggia'" not in recovery_function)
check("recupero pipeline limitato e temporizzato", "'posts_per_page' => 20" in RESOURCE and "15 * MINUTE_IN_SECONDS" in RESOURCE and "2 * MINUTE_IN_SECONDS" in RESOURCE)
check("recupero riattiva release ferme dopo il ripristino ACR", "analysis_waiting_configuration" in recovery_function and "trb_resource_start_release_analysis( $release_id )" in recovery_function)
check("recupero ordinario silenzioso verso l'artista", "trb_resource_notify_artist_pipeline_recovery" not in recovery_function and "_trb_pipeline_recovery_notice_at" not in recovery_function and "_trb_pipeline_last_recovered_at" in recovery_function)
check("recupero confermato visibile nel portale", "_trb_pipeline_recovery_notice_at" in PORTAL and "Elaborazione ripresa automaticamente" in PORTAL and "update_post_meta( $release_id, '_trb_pipeline_recovery_notice_at'" in RESOURCE)
check("email artista solo per incidente confermato", "trb_resource_notify_artist_pipeline_recovery" in RESOURCE and "confirmed-incident-" in RESOURCE and "if ( ! $confirmed_incident" in RESOURCE and "La problematica tecnica è stata risolta" in RESOURCE)
check("Andrea in CC nella stessa email artista", "Cc: andrea.tognassi@trbrec.com" in RESOURCE and "[Copia artista]" not in RESOURCE and "-admin-copy" not in RESOURCE)
check("email di recupero usa nome anagrafico e tono informale", "trb_resource_artist_legal_greeting_name" in RESOURCE and "$user->first_name" in RESOURCE and "Gentile ' . esc_html( $name )" in RESOURCE and "da parte tua" in RESOURCE)
check("email di recupero include firma aziendale e privacy", "Sezione Contratti e Distribuzione" in RESOURCE and "P. IVA 02846170989" in RESOURCE and "REA BS-483571" in RESOURCE and "SDI 095EI9R" in RESOURCE and "Privacy notice" in RESOURCE)
check("coda email conserva CC e Reply-To validati", "headers longtext NULL" in RESOURCE and "Cc|Reply-To" in RESOURCE and "TRB_RESOURCE_MONITOR_VERSION', '1.2.1" in RESOURCE)
check("notifiche automatiche non confermate eliminate dalla coda", "trb_resource_cancel_unconfirmed_recovery_notifications" in RESOURCE and "cancelled_unconfirmed_recovery_notice" in RESOURCE and "20260825.1" in RESOURCE)
check("email di sblocco una tantum per la pratica Ruggia", "trb_resource_notify_ruggia_recovery_backfill" in RESOURCE and "manual-resend-20260824-2" in RESOURCE and "20260824.9" in RESOURCE)
check("ricerca Ruggia robusta e senza finestra temporale", "u.user_login LIKE" in RESOURCE and "um.meta_value LIKE" in RESOURCE and "pm.meta_value LIKE" in RESOURCE and "45 days ago" not in RESOURCE)
check("notifica senza pratica solo per incidente confermato", "trb_resource_notify_artist_recovery_without_release" in RESOURCE and RESOURCE.count("if ( ! $confirmed_incident" ) >= 2 and "artist_found_without_release" in RESOURCE)
check("ricevuta tecnica degli invii di sblocco", "trb_resource_recovery_mail_receipts" in RESOURCE)
check("ACR idempotente per hash", "idempotency_key" in RESOURCE and "acrcloud|' . $hash" in RESOURCE)
check("errore ACR identico isolato per nuova release", "retry-release:" in RESOURCE and "provider_name_suffix" in RESOURCE)
check("fingerprint e cover detection richiesti insieme", "3 !== $reported_engine" in RESOURCE)
check("job ACR eseguiti col vecchio motore vengono riscansionati", "trb_resource_rescan_acr_file" in RESOURCE and "ACR_ENGINE_MISMATCH_" in RESOURCE and "/rescan" in RESOURCE)
check("fallback ACR ricrea una sola volta il file col motore combinato", "trb_resource_acr_engine_recovery_stage" in RESOURCE and "provider_name_suffix .= '-engine3'" in RESOURCE and "engine_replacement_stage = 2" in RESOURCE)
check("nuovo oggetto ACR cancella il vecchio errore diagnostico", "'attempts' => 1, 'last_error' => '', 'payload' => wp_json_encode( $result )" in RESOURCE)
check("riallineamento ACR invalida oggetti della configurazione precedente", "trb_acr_configuration_generation" in ANALYSIS and "trb_resource_acr_configuration_generation" in RESOURCE and "'generation' => trb_resource_acr_configuration_generation()" in RESOURCE)
check("riallineamento ACR riavvia le pratiche in attesa", "'analysis_waiting_configuration'" in ANALYSIS and "trb_resource_start_release_analysis_manual" in ANALYSIS and "time() + 5" in ANALYSIS)
check("container ACR sostitutivo creato direttamente col motore combinato", "function trb_analysis_replace_acr_container" in ANALYSIS and "wp_remote_post( 'https://api-v2.acrcloud.com/api/fs-containers'" in ANALYSIS and "'engine'          => 3" in ANALYSIS)
check("switch ACR sostitutivo avviene solo dopo verifica", "ACR_CONTAINER_REPLACEMENT_INVALID" in ANALYSIS and "trb_analysis_verify_acr_container()" in ANALYSIS and "ACR_CONTAINER_REPLACEMENT_UNVERIFIED" in ANALYSIS)
check("container ACR precedente conservato per rollback", "trb_acr_previous_container_id" in ANALYSIS and "Il container precedente non viene eliminato" in ANALYSIS)
check("riferimenti ACR del vecchio container non entrano in polling infinito", "ACR_FILE_NOT_FOUND_IN_CONTAINER" in RESOURCE and "successful empty response is terminal" in RESOURCE and "trb_resource_start_release_analysis_manual" in RESOURCE)
check("fallback ACR esegue fingerprinting e cover in container indipendenti", "fingerprinting_exact" in RESOURCE and "cover_song_scan" in RESOURCE and "trb_resource_poll_dual_acr_job" in RESOURCE)
check("fallback ACR verifica il motore reale di entrambe le scansioni", "trb_expected_engine" in RESOURCE and "ACR_DUAL_ENGINE_MISMATCH_" in RESOURCE)
check("fallback ACR unisce i risultati solo dopo entrambi gli esiti", "trb_resource_finalize_dual_acr_track" in RESOURCE and "trb_dual_engine" in RESOURCE and "acrcloud-dual-merged" in RESOURCE)
check("eventi ACR risolti dopo il risultato duale completo", "trb_resource_resolve_acr_track_events( $release_id, $track )" in RESOURCE and "'status' => 'resolved'" in RESOURCE)
check("eventi ACR storici riconciliati una sola volta", "trb_resource_reconcile_completed_dual_acr_events" in RESOURCE and "trb_resource_event_reconciliation_version" in RESOURCE and "20260825.1" in RESOURCE)
check("container fingerprinting dedicato creato e verificato", "trb_analysis_enable_dual_acr_containers" in ANALYSIS and "ACR_FINGERPRINT_CONTAINER_INVALID" in ANALYSIS and "'engine' => 1" in ANALYSIS)
check("fallback ACR persistente passa a revisione senza cicli di spesa", "acr-engine-persistent-" in RESOURCE and "Il file sostitutivo ACRCloud non ha applicato" in RESOURCE and "Nessun contratto è stato inviato automaticamente" in RESOURCE)
check("copyright pulito approva e avvia sempre il contratto", "'yellow' === $semaphore ? 'manual_review' : 'approved'" in ANALYSIS and "do_action( 'trb_release_analysis_approved'" in ANALYSIS)
check("contratto automatico solo dopo esito ACR completo per ogni traccia", "array_diff_key( $current_hashes, $normalized )" in ANALYSIS and "acr-incomplete-result-" in ANALYSIS)
check("avvisi tecnici non vengono classificati come copyright", "if ( 'warning' === ( $technical['status'] ?? '' ) ) $yellow = true" not in ANALYSIS)
check("backend descrive la politica automatica senza falso blocco benchmark", "Politica contratti" in ANALYSIS and "non blocca i contratti con copyright verde" in ANALYSIS and "approvazione automatica bloccata" not in ANALYSIS)
check("problema copyright avvisa artista con Andrea in CC", "trb_analysis_queue_artist_copyright_email" in ANALYSIS and "artist-copyright-" in ANALYSIS and "trb_resource_artist_recovery_cc_headers" in ANALYSIS)
check("ISRC già dichiarato distingue la redistribuzione dal plagio", "trb_analysis_is_declared_catalogue_match" in ANALYSIS and "hash_equals( $declared_isrc, $matched_isrc )" in ANALYSIS and "registrazione già pubblicata coerente con l’ISRC dichiarato" in ANALYSIS)
check("corrispondenze diverse dall’ISRC dichiarato restano bloccanti", "$unresolved_match = true" in ANALYSIS and "$yellow = true" in ANALYSIS and "DEEPRIGHT_COVER_CHECK_COHERENT_WITH_DECLARED_ISRC" in ANALYSIS)
check("email copyright usa nome anagrafico firma e portale", "Gentile ' . esc_html( $name )" in ANALYSIS and "trb_resource_artist_email_signature" in ANALYSIS and "Verifica dei diritti per la release" in ANALYSIS)
check("verifica ACR temporaneamente indisponibile usa retry e alert", "trb_resource_schedule_analysis_configuration_retry" in RESOURCE and "_trb_acr_configuration_retry_attempts" in RESOURCE and "Verifica ACRCloud ancora in attesa" in RESOURCE)
check("DeepRight attivo e verificato", "ACR_DEEPRIGHT_DISABLED_OR_UNVERIFIED" in ANALYSIS)
check("email artista su errore tecnico oggettivo", "trb_analysis_queue_artist_correction_email" in ANALYSIS)
check("email amministratore su verifica copyright", "trb_analysis_queue_admin_review_email" in ANALYSIS)
check("coda email riprogrammata se restano notifiche", "$remaining" in RESOURCE and "trb_resource_process_notifications" in RESOURCE)
check("monitor giornaliero per blocchi e ruoli ambigui", "account hanno più gruppi contrattuali" in RESOURCE)
check("audit reale post-deploy con riepilogo email", "trb_resource_run_portal_audit" in RESOURCE and "Audit completo Portale Artisti completato" in RESOURCE)
check("download file release vincolato al proprietario", "trb_portal_current_user_can_access_release" in PORTAL)
check("file privati profilo vincolati all'utente e a un nonce", "check_admin_referer( 'trb_portal_private_file_'" in PORTAL and "trb_portal_private_profile_files()" in PORTAL)
check("percorsi privati confinati con realpath", PORTAL.count("0 !== strpos( $target, $private_dir . DIRECTORY_SEPARATOR )") >= 2)
check("risorse Knowledge Hub filtrate per gruppo anche sui link diretti", "trb_portal_protect_tagged_resource" in PORTAL and "trb_portal_user_can_access( $profiles )" in PORTAL)
check("callback contratto protetta da segreto condiviso", "trb_release_bridge_public_callback" in BRIDGE and "hash_equals" in BRIDGE and "shared_secret" in BRIDGE)
check("stato contratti restituisce solo le release dell'utente", "'author'=>get_current_user_id()" in BRIDGE and "release-contract-status" in BRIDGE)
check("cruscotto direzione incluso dal tema", "inc/trb-owner-dashboard.php" in (ROOT / "functions.php").read_text(encoding="utf-8"))
check("cruscotto direzione usa ruolo e capability separati", "trb_owner_viewer" in OWNER and "trb_view_owner_dashboard" in OWNER and "'read'" in OWNER)
check("cruscotto copre artisti release provini attività e comunicazioni", all(token in OWNER for token in ("trb_owner_dashboard_artist_users", "Release e contratti", "Provini e valutazioni", "Anomalie aperte", "Ultime comunicazioni automatiche")))
check("cruscotto mostra i profili artista canonici e legacy", all(role in OWNER for role in ("artista_a", "artista_b", "artista_c", "artista_d", "artista_ddb12", "artista_dds", "artista_ddb", "artista_ddb-trb", "artista_trb")))
check("cruscotto separa lettura e gestione operativa", "TRB_OWNER_DASHBOARD_MANAGE_CAPABILITY" in OWNER and "trb_owner_manager" in OWNER and "Direzione TRB · operativa" in OWNER)
check("cruscotto dispone di navigazione ricerca filtri e dettagli release", all(token in OWNER for token in ("TRB Control Room", "trb-owner-nav", "trb_owner_dashboard_filter_releases", "release_status", "Cerca pratica, artista, ISRC, contratto", "Brani, ISRC e analisi")))
check("cestino release è recuperabile protetto e tracciato", "wp_trash_post" in OWNER and "wp_untrash_post" in OWNER and "check_admin_referer( 'trb_owner_dashboard_trash_'" in OWNER and "trb_resource_event( 'owner-trash-release-'" in OWNER and "wp_delete_post" not in OWNER)
check("firma contratto genera riepilogo direzione idempotente", "trb_owner_dashboard_watch_contract_state" in OWNER and "owner-contract-signed-" in OWNER and "andrea.tognassi@trbrec.com" in OWNER)
check("pratica Ruggia firmata dispone di backfill riepilogativo", "trb_owner_dashboard_backfill_ruggia_summary" in OWNER and "$release_id = 12283" in OWNER)
check("recupero live Greta ritenta il contratto una sola volta", "trb_owner_dashboard_retry_feel_contract" in OWNER and "12275" in OWNER and "_trb_owner_live_recovery_20260825" in OWNER and "trb_release_bridge_dispatch( $release_id )" in OWNER)
check("recupero live Ruggia riconcilia firma e foglio una sola volta", "trb_owner_dashboard_reconcile_ruggia_contract" in OWNER and "DDB20260031" in OWNER and "1061056" in OWNER and "trb_release_bridge_apply_callback" in OWNER)

# Authentication, public pages and general security.
check("reset password usa una pagina pubblica dedicata", "trb_portal_password_reset_url" in PORTAL and "/recupera-password/" in PORTAL)
check("email WordPress manuali riscrivono il link di reset", "retrieve_password_message" in PORTAL and "trb_portal_use_branded_password_reset_link" in PORTAL)
check("reset password verifica chiave e nonce", "check_password_reset_key" in PORTAL and "trb_password_nonce" in PASSWORD)
check("password nuova con lunghezza minima", 'minlength="10"' in PASSWORD and "strlen( $pass1 ) < 10" in PORTAL)
check("login protetto da nonce", "trb_portal_login_nonce" in PORTAL and "wp_signon" in PORTAL)
check("login rimanda alla dashboard canonica", "trb_portal_handle_login" in PORTAL and "home_url( '/area-artisti/' )" in PORTAL and "trb_portal_action" in LOGIN)
check("XML-RPC disabilitato", "add_filter( 'xmlrpc_enabled', '__return_false' )" in PORTAL)
check("azioni protette gestiscono la sessione scaduta", "trb_portal_protected_action_unauthenticated" in PORTAL)
check("registrazione protetta da captcha monouso e honeypot", "trb_portal_registration_captcha_markup" in PORTAL and "delete_transient" in PORTAL and "trb_registration_website" in REGISTRATION + PORTAL)
check("registrazioni restano in approvazione e vengono ripulite", "pw_new_user_approve" in PORTAL and "trb_portal_cleanup_pending_accounts" in PORTAL and "30 * DAY_IN_SECONDS" in PORTAL)
check("segnalazioni protette da nonce honeypot tempo minimo e rate limit", "trb_support_nonce" in SUPPORT + PORTAL and "trb_support_website" in SUPPORT + PORTAL and "time() - $started < 3" in PORTAL and "trb_support_rate_" in PORTAL)
check("segnalazioni archiviate e recapitate alla casella TRB", "wp_insert_post" in PORTAL and "wp_mail( 'info@trbrec.com'" in PORTAL and "admin_post_nopriv_trb_portal_submit_support" in PORTAL)

# Demo evaluation pipeline.
check("demo esclusa per DDS lato interfaccia e server", "if ( 'dds' === trb_portal_user_profile() ) return;" in PORTAL and "'forbidden'" in PORTAL)
check("demo protetta da autenticazione e nonce", "trb_portal_submit_demo" in PORTAL and "wp_verify_nonce( $nonce, 'trb_portal_submit_demo' )" in PORTAL)
check("demo applica limite settimanale", "_trb_demo_last_submission" in PORTAL and "WEEK_IN_SECONDS" in PORTAL)
check("demo protegge da doppi invii", "_trb_demo_submission_lock" in PORTAL and "_trb_demo_last_fingerprint" in PORTAL)
check("demo valida TXT DOCX e MP3 lato server", "application/vnd.openxmlformats-officedocument.wordprocessingml.document" in PORTAL and "array( 'mp3' => 'audio/mpeg' )" in PORTAL)
check("upload demo asincrono interpreta errori HTTP senza creare duplicati", "X-TRB-Upload" in DEMO_JS and "if (submitting) return" in DEMO_JS and "Nessun provino è stato acquisito" in DEMO_JS)
check("demo archivia in cartella privata", "trb-demo-private" in PORTAL and "Require all denied" in PORTAL)
check("demo trasferisce i materiali su pCloud", "trb_demo_upload_to_pcloud" in DEMO and "webdav_upload_failed" in DEMO)
check("valutazione demo separa analisi audio e testo", "trb_demo_review_prompt" in DEMO and "Non formulare osservazioni" in DEMO)
check("valutazione demo respinge risposte troncate", "openai_truncated" in DEMO)
check("Google Sheet usa firma HMAC", "hash_hmac( 'sha256'" in DEMO and "sheet_webhook_secret" in DEMO)
check("Google Sheet dispone di retry e alert", "trb_demo_sync_sheet_retry" in DEMO and "demo-sheet-failed-" in DEMO)
check("email valutazione dispone di retry limitato e alert", "_trb_demo_email_attempts" in DEMO and "email_failed" in DEMO and "$attempts < 5" in DEMO)
check("stati demo visibili all'artista", "trb_portal_recent_demo_requests" in PORTAL and "Stato delle valutazioni demo" in PORTAL)
check("watchdog recupera valutazioni demo ferme", "trb_demo_recover_stalled_requests" in DEMO and "wp_schedule_event" in DEMO)
check("consegna demo dopo tre ore lavorative lunedi-sabato 08:30-18:30", "'hours'          => 3" in PORTAL and "'last_weekday'   => 6" in PORTAL and "'opening_hour'   => 8" in PORTAL and "'opening_minute' => 30" in PORTAL and "'closing_hour'   => 18" in PORTAL and "'closing_minute' => 30" in PORTAL)
check("finestra demo usa il fuso Europe/Rome con ora legale", "trb_portal_demo_delivery_timezone" in PORTAL and "new DateTimeZone( 'Europe/Rome' )" in PORTAL and "trb_portal_demo_delivery_timezone()->getName()" in DEMO)
check("nuova finestra applicata anche alle demo non ancora inviate", "trb_demo_migrate_delivery_window" in DEMO and "wp_clear_scheduled_hook( 'trb_portal_send_demo_review'" in DEMO)
check("invii e retry demo restano sempre nella finestra consentita", "trb_portal_demo_next_delivery_time" in PORTAL and DEMO.count("trb_portal_demo_next_delivery_time") >= 4)
check("cleanup demo elimina copie locali e remote", "trb_demo_cleanup_request" in DEMO and "trb_demo_webdav_request( 'DELETE'" in DEMO)
check("health check demo accessibile al monitor", "'/trb/v1/demo-health'" in DEPLOY)
check("release caricate a blocchi e finalizzate con sessione idempotente", "trb_portal_stage_release_chunk" in PORTAL and "trb_release_submission_token" in RELEASE_JS and "trb_staged_uploads_json" in RELEASE_JS)
check("audit produzione include demo pagine permessi matrice release e copertine", "demo_problems" in RESOURCE and "Pagina pubblica mancante" in RESOURCE and "release_qa" in RESOURCE and "release_matrix" in RESOURCE and "cover_workflow" in RESOURCE and "20260825.1" in RESOURCE)
check("audit produzione verifica contatori contratti firmati", "Contatore release non limitato ai contratti firmati" in RESOURCE)
check("audit produzione rileva anche limiti ACR e pCloud maiuscoli", "acr_budget_limit_reached" in RESOURCE and "pcloud_quota_limit_reached" in RESOURCE)
check("audit produzione include anomalie risorsa ancora aperte", "open_resource_events" in RESOURCE and "severity IN ('warning','critical')" in RESOURCE and "'resource_events' => $open_resource_events" in RESOURCE)
check("monitor distingue lo spazio hosting dallo staging", "Spazio hosting (filesystem condiviso)" in RESOURCE and "Filesystem hosting utilizzato" in RESOURCE)
check("deploy valida PHP e regressioni prima della produzione", "Validate PHP and portal regressions" in (ROOT / ".github/workflows/deploy.yml").read_text(encoding="utf-8") and "php -l" in (ROOT / ".github/workflows/deploy.yml").read_text(encoding="utf-8"))
check("deploy SiteGround non dichiara successo prima della verifica", "timeout-minutes: 12" in (ROOT / ".github/workflows/deploy.yml").read_text(encoding="utf-8") and "for attempt in $(seq 1 36)" in (ROOT / ".github/workflows/deploy.yml").read_text(encoding="utf-8") and "Deployment remains queued through the five-minute internal WordPress safety net.\"\n          else" in (ROOT / ".github/workflows/deploy.yml").read_text(encoding="utf-8"))

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(f"{'PASS' if ok else 'FAIL'}  {name}")
print(f"\n{len(checks) - len(failed)}/{len(checks)} controlli superati")
if failed:
    sys.exit(1)
