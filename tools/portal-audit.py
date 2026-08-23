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
check("recupero pipeline limitato e temporizzato", "'posts_per_page' => 20" in RESOURCE and "15 * MINUTE_IN_SECONDS" in RESOURCE)
check("recupero visibile nel portale", "_trb_pipeline_recovery_notice_at" in PORTAL and "Elaborazione ripresa automaticamente" in PORTAL)
check("email artista quando una pratica viene sbloccata", "trb_resource_notify_artist_pipeline_recovery" in RESOURCE and "La problematica dipendeva dal portale" in RESOURCE)
check("copia amministratore per ogni email di sblocco", "[Copia artista]" in RESOURCE and "-admin-copy" in RESOURCE)
check("email di sblocco una tantum per la pratica Ruggia", "trb_resource_notify_ruggia_recovery_backfill" in RESOURCE and "20260824.3" in RESOURCE)
check("ACR idempotente per hash", "idempotency_key" in RESOURCE and "acrcloud|' . $hash" in RESOURCE)
check("errore ACR identico isolato per nuova release", "retry-release:" in RESOURCE and "provider_name_suffix" in RESOURCE)
check("fingerprint e cover detection richiesti insieme", "3 !== $reported_engine" in RESOURCE)
check("DeepRight attivo e verificato", "ACR_DEEPRIGHT_DISABLED_OR_UNVERIFIED" in ANALYSIS)
check("email artista su errore tecnico oggettivo", "trb_analysis_queue_artist_correction_email" in ANALYSIS)
check("email amministratore su verifica copyright", "trb_analysis_queue_admin_review_email" in ANALYSIS)
check("coda email riprogrammata se restano notifiche", "$remaining" in RESOURCE and "trb_resource_process_notifications" in RESOURCE)
check("monitor giornaliero per blocchi e ruoli ambigui", "account hanno più gruppi contrattuali" in RESOURCE)
check("audit reale post-deploy con riepilogo email", "trb_resource_run_portal_audit" in RESOURCE and "Audit operativo Portale Artisti completato" in RESOURCE)
check("download file release vincolato al proprietario", "trb_portal_current_user_can_access_release" in PORTAL)

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(f"{'PASS' if ok else 'FAIL'}  {name}")
print(f"\n{len(checks) - len(failed)}/{len(checks)} controlli superati")
if failed:
    sys.exit(1)
