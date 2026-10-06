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
