#!/usr/bin/env python3
"""Dependency-free release/permissions regression audit for the TRB portal."""

from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
PORTAL = (ROOT / "inc/trb-artist-portal.php").read_text(encoding="utf-8")
RESOURCE = (ROOT / "inc/trb-resource-monitor.php").read_text(encoding="utf-8")
ANALYSIS = (ROOT / "inc/trb-release-analysis.php").read_text(encoding="utf-8")
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
check("account QA spotify4 forzato sul profilo TRB", "'spotify4'" in PORTAL and "return 'trb';" in PORTAL)
check("ruolo canonico prioritario rispetto agli alias", "A current canonical role always wins" in PORTAL)
check("normalizzazione dei ruoli al salvataggio amministrativo", "trb_portal_normalize_artist_role_after_admin_save" in PORTAL)
check("DDB-TRB aggiorna il profilo canonico al passaggio TRB", "'_trb_artist_contract_profile', 'trb'" in BRIDGE)
check("creazione release protetta da nonce", "wp_verify_nonce( $release_nonce" in PORTAL)
check("creazione release protetta da idempotency token", "_trb_release_submission_token" in PORTAL)
check("limite mensile applicato solo a DDS/DDB12", "function trb_portal_monthly_release_profile" in PORTAL)
check("upload WAV validato lato server", "trb_portal_validate_release_upload( $audio, 'audio' )" in PORTAL)
check("durata WAV verificata con tolleranza di un secondo", "audio_duration_mismatch" in PORTAL and "> 1.0" in PORTAL)
check("trasferimento pCloud verificato prima dell'analisi", "archived_pending_analysis" in PCLOUD and "verified" in PCLOUD)
check("retry pCloud schedulato", "trb_release_pcloud_retry" in PCLOUD)
recovery_function = RESOURCE.split("function trb_resource_recover_release_pipeline()", 1)[1].split("add_action( 'trb_resource_recover_release_pipeline'", 1)[0]
check("recupero pipeline esteso a tutti gli artisti", "author__in" not in recovery_function and "meta_value'   => 'Ruggia'" not in recovery_function)
check("recupero pipeline limitato e temporizzato", "'posts_per_page' => 20" in RESOURCE and "15 * MINUTE_IN_SECONDS" in RESOURCE and "2 * MINUTE_IN_SECONDS" in RESOURCE)
check("recupero riattiva release ferme dopo il ripristino ACR", "analysis_waiting_configuration" in recovery_function and "trb_resource_start_release_analysis( $release_id )" in recovery_function)
check("recupero visibile nel portale", "_trb_pipeline_recovery_notice_at" in PORTAL and "Elaborazione ripresa automaticamente" in PORTAL)
check("email artista quando una pratica viene sbloccata", "trb_resource_notify_artist_pipeline_recovery" in RESOURCE and "La problematica dipendeva dal portale" in RESOURCE)
check("copia amministratore per ogni email di sblocco", "[Copia artista]" in RESOURCE and "-admin-copy" in RESOURCE)
check("email di sblocco una tantum per la pratica Ruggia", "trb_resource_notify_ruggia_recovery_backfill" in RESOURCE and "manual-resend-20260824-2" in RESOURCE and "20260824.8" in RESOURCE)
check("ricerca Ruggia robusta e senza finestra temporale", "u.user_login LIKE" in RESOURCE and "um.meta_value LIKE" in RESOURCE and "pm.meta_value LIKE" in RESOURCE and "45 days ago" not in RESOURCE)
check("notifica Ruggia anche se il caricamento non ha creato la pratica", "trb_resource_notify_artist_recovery_without_release" in RESOURCE and "artist_found_without_release" in RESOURCE)
check("ricevuta tecnica degli invii di sblocco", "trb_resource_recovery_mail_receipts" in RESOURCE)
check("ACR idempotente per hash", "idempotency_key" in RESOURCE and "acrcloud|' . $hash" in RESOURCE)
check("errore ACR identico isolato per nuova release", "retry-release:" in RESOURCE and "provider_name_suffix" in RESOURCE)
check("fingerprint e cover detection richiesti insieme", "3 !== $reported_engine" in RESOURCE)
check("job ACR eseguiti col vecchio motore vengono riscansionati", "trb_resource_rescan_acr_file" in RESOURCE and "ACR_ENGINE_MISMATCH_" in RESOURCE and "/rescan" in RESOURCE)
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
check("cleanup demo elimina copie locali e remote", "trb_demo_cleanup_request" in DEMO and "trb_demo_webdav_request( 'DELETE'" in DEMO)
check("health check demo accessibile al monitor", "'/trb/v1/demo-health'" in DEPLOY)
check("release caricate a blocchi e finalizzate con sessione idempotente", "trb_portal_stage_release_chunk" in PORTAL and "trb_release_submission_token" in RELEASE_JS and "trb_staged_uploads_json" in RELEASE_JS)
check("audit produzione include demo pagine e permessi", "demo_problems" in RESOURCE and "Pagina pubblica mancante" in RESOURCE and "20260824.7" in RESOURCE)
check("audit produzione rileva anche limiti ACR e pCloud maiuscoli", "acr_budget_limit_reached" in RESOURCE and "pcloud_quota_limit_reached" in RESOURCE)

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(f"{'PASS' if ok else 'FAIL'}  {name}")
print(f"\n{len(checks) - len(failed)}/{len(checks)} controlli superati")
if failed:
    sys.exit(1)
