TRB Site Studio 0.1.10

Complete administrator editor: literal page/header/footer content, images, links, dimensions, colors, Fluent Forms labels/placeholders/help, artist and release presentation overrides, revision restore. Artist photo galleries can contain zero to three verified media images. Source identities and contractual workflow are immutable.

Artist directory displays photograph and name only, four columns on wide desktop, three on smaller desktop and two on mobile. Each approved roster artist owns one native child page under /artisti/, bound by immutable portal ID. URLs survive name changes. Pages include real photographs only, complete biography, official social/store links, dated releases with covers, presentations, smartlinks and press kits. Gallery imports up to three distinct real photographs; missing photographs are never invented or duplicated. Pages removed from the eligible roster are drafted automatically. Overrides remain outside the imported generation.

ProfilePage/MusicGroup schema and social metadata are generated from verified public data. Artist pages are included by the WordPress native page sitemap. No promise of search engine ranking or index timing.

TRB Site Studio 0.1.5

Compact responsive roster: four artists on wide displays, three on desktop, two on tablets and one on phones. Display-only Unicode excerpts are at most 240 characters, with five equal-height lines. Native keyboard-accessible disclosures reveal the complete original biography, official profiles and release presentations. Missing materials retain consistent slots without fabricated content. No source biography is shortened or rewritten.

TRB Site Studio 0.1.4

The existing catalog takedown manifest is authoritative across directory, Coming soon and import. A removed UPC is excluded before asset import and cannot block unrelated artist-profile updates. Public reads also enforce the manifest immediately.

TRB Site Studio 0.1.3

Legacy roster: existing WordPress public display names are used only when the contractual artist field is empty; email addresses are excluded. No biography or image is invented. Official profiles map the immutable catalog artist key with exact name agreement.

Catalog synchronization uses the original TRB_Promo_Ecosystem public import API, preserving its UPC, takedown, label, tracklist and curated-asset checks. Public payloads exclude rights references and private credit fields. Only catalog posts created by this adapter can be paused automatically when approval is withdrawn. Existing historical catalog posts remain owned by the original importer. Future-release links appear when their catalog post is public.

Public artist profiles, release covers and presentations are supplied through the original renderer filters. Discography includes existing catalog releases through verified artist keys and removes duplicate UPCs.

TRB Site Studio 0.1.2

Server transport: CLI export/import over the established SiteGround SSH deployment. No new WordPress user, application password or public authorization route is created. A private immutable bundle contains only approved TRB roster, artistic biographies and derived JPEGs. No contracts, contact records, original documents or audio are exported. Source eligibility requires account approval, excludes QA, and publishes only non-inactive CRM processed releases with signed contracts. Legacy processed releases may have no newer intake marker. Commercial ready or technical approved alone cannot publish.

Automatic exporter runs every 15 minutes (GitHub scheduling may be delayed). WordPress imports the current bundle every 15 minutes and supports manual import in Settings > TRB Site Studio. Failed transfers retain the previous complete generation; release cards are hidden after three hours without refreshed approval.

Historical association uses a valid unique UPC equal to _trb_promo_upc on exactly one published release. No name matching. Linked records retain the existing release, smartlink and press-kit routes. Artist biographies and presentations support TXT, DOCX, ODT and RTF. Image hashes bind derivatives to the verified generation.

TRB Site Studio — 0.1.0, candidata al collaudo
Data: 6 ottobre 2026

STATO
Codice preparato e verificato localmente. NON installato né attivato sui siti.
La pagina Artisti su new1 è la bozza WordPress 936, template page-no-title.
Non pubblicare la bozza finché il primo import reale non è stato verificato.
Questa versione non modifica trbrec.com e non invia email o post social.

EDITOR
Su new1, gli amministratori vedranno “Modifica visiva” nella barra WordPress.
Modifica i testi HTML di pagine/articoli e delle parti intestazione/footer.
Anteprima immediata, margini verticali, padding e larghezza massima; Salva/Annulla.
Il testo sostituito assume formattazione semplice; il solo cambio di spaziatura
conserva la formattazione esistente. Le variazioni valgono per tutti i dispositivi.
Il salvataggio conserva revisioni WordPress, controlla permessi, nonce e versione
della pagina. Moduli, catalogo gestito e shortcode non vengono riscritti.
Elementi ambigui/ripetuti o dinamici restano gestibili nell'editor originale.
Sono richiesti PHP DOM e un collaudo browser dopo l'installazione.

COLLEGAMENTO
Installare il plugin con il normale flusso amministrativo WordPress nei due siti.
Non è necessario disattivare la protezione dell'editor dei file del tema.
Il collegamento persistente al portale deve prima essere autorizzato dall'utente.
Non inserire password o chiavi nella chat, nel repository o in file pubblici.
Configurazione: Impostazioni > TRB Site Studio.

Sul portale artist.trbrec.com: verificare, tramite il ponte CRM live, i valori
effettivi di _trb_crm_workflow_status che attestano la decisione di distribuzione.
L'elenco è VUOTO per impostazione iniziale: l'approvazione tecnica non autorizza
la pubblicazione. Verificare anche che _trb_release_date rifletta l'ultima data
concordata nel CRM; in caso contrario adattare il campo prima dell'attivazione.
Non sono stati inventati valori commerciali (“ready” non è approvazione).

Su new1: configurare l'utente autorizzato del portale e una password applicativa
WordPress, esclusivamente nel modulo HTTPS dell'amministrazione. L'API sorgente
richiede attualmente manage_options: prima dell'uso definitivo valutare un utente
tecnico con una capacità di lettura specifica, senza riutilizzare login personali.
Le credenziali sono conservate nelle opzioni WordPress non autoload e usate solo
dal server. L'host sorgente è fisso, le redirezioni HTTP non sono consentite.
Non sono previsti aggiramenti dei controlli REST o Wordfence.

DATI
Gruppo canonico TRB, account approvato; esclusi DDB, DDB12, DDB-TRB e account QA.
I nuovi ingressi TRB vengono rilevati a ogni importazione completa.
Solo nome d'arte, biografia artistica, foto ufficiale, profili pubblici, release
approvate per distribuzione, data, copertina e presentazione.
Nessun documento d'identità, indirizzo privato, contratto, compenso o audio.
Le foto e le cover sono ridimensionate a JPEG pubblico sul sito; gli originali
privati non vengono resi pubblici. Il primo file artistico selezionato è usato
come foto principale: verificare questa scelta per gli artisti con più foto.
Gli allegati testuali TXT, DOCX e ODT sono supportati. Un RTF sospende l'import,
senza alterare la directory esistente, e richiede conversione o adapter dedicato.
Nessuna biografia o presentazione viene generata dall'IA.

DIRECTORY E COMING SOON
[trb_public_roster] mostra gli artisti e la discografia del portale.
[trb_public_coming_soon] mostra le uscite approvate con data futura in Europe/Rome.
I due shortcode sono già nella bozza 936. Inserire il secondo anche nella home
e nel catalogo solo dopo il collaudo e il primo import corretto.
L'import conserva gli ID sorgente e sostituisce una generazione completa in modo
atomico; gli errori conservano l'ultimo stato valido. Un'uscita ritirata o un
artista uscito dal gruppo scompare al prossimo import completato.
Le immagini sono riutilizzate tramite hash; la generazione precedente è salvata.
Non vengono sovrascritte le 308 release storiche del catalogo esistente: il
ricongiungimento con esse tramite UPC e ID verificati resta un passo successivo
al primo collaudo sul portale, per evitare duplicati o associazioni per nome.
Un'importazione completa ha limite protettivo di 1000 utenti/2000 release;
oltre questi limiti richiede paginazione prima di procedere.
La sincronizzazione automatica è disattivata inizialmente. Dopo il collaudo può
essere attivata ogni 15 minuti; WP-Cron richiede traffico o cron dell'hosting.

COLLAUDO DI ATTIVAZIONE ANCORA NECESSARIO
1. Confronto roster TRB live, esclusione account test/DDB/pending e controllo foto.
2. Confronto stato di distribuzione e data definitivi con CRM; release futura,
   posticipata, annullata e già uscita; verifica assenza di duplicati.
3. Import iniziale manuale con anteprima privata; ripetizione senza duplicati.
4. Errore sorgente/media: verifica mantenimento della generazione precedente.
5. Editor: modifica, annullamento, salvataggio, revisione, secondo admin e logout.
6. Test grafico reale dell'editor e della directory a 390/768/1366 px.
7. Pubblicare Artisti e aggiungere la navigazione solo dopo questi controlli.

PROVENIENZA ADAPTER
Codice portale letto dal repository trbrec/docy, branch main, commit
56269a314bcdb702df600ec748a4b45250367b47. Nessuna modifica al repository.
I componenti del ponte CRM fuori dal tema non erano disponibili in questo repo.

VERIFICHE LOCALI
PHP: sintassi dei quattro file valida. JavaScript: node --check valido.
26 controlli mirati superati: XSS con KSES WordPress reale, shortcode, limiti CSS,
conflitti, nonce, permessi, esclusione QA/DDB/pending, gate distribuzione,
annullamento, date e coerenza degli ID. Harness con funzioni WordPress simulate;
non sostituisce un test end-to-end nella specifica installazione.



0.1.10 — Correct page selection for WordPress-localized numeric IDs; initialize brightness before range clamping; preview artist links and galleries immediately. Native hosting cron refreshes the full source and destination every fifteen minutes when the host exposes crontab, preserving other jobs. WordPress fallback refreshes the source as well; the admin panel distinguishes source freshness from import time and reports the hosting scheduler explicitly. Disabling automatic updates is respected by all schedulers.
