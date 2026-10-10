# Portale Artisti TRB rec — Docy

Repository pubblico del tema Docy e dei moduli del portale `artist.trbrec.com`. Non inserire credenziali, dati degli artisti o sorgenti privati del CRM.

## Contenuto

- tema e moduli del portale in `functions.php`, `inc/` e `assets/`;
- integrazioni CRM, onboarding, fogli e Site Studio in `integrations/`;
- verifiche e strumenti amministrativi in `tests/` e `tools/`;
- controlli prima del merge e deploy su SiteGround in `.github/workflows/`.

Ogni push su `main` avvia il deploy di produzione. Le correzioni in collaudo devono restare su un ramo e in una PR in bozza fino alla revisione finale e alle prove funzionali con dati fittizi. Un controllo statico o un test con servizi simulati non dimostra il funzionamento delle integrazioni reali.

La matrice di collaudo deve comprendere salvataggio del profilo, residenza e nascita estere, identificativi fiscali e documenti, caricamenti multipart e a blocchi, sostituzioni, interruzioni, invii ripetuti e simultanei, accessi autorizzati e respinti. Email, contratti, distribuzione e servizi esterni richiedono destinazioni di test isolate durante il collaudo.

Il task e il repository privato del CRM gestiscono il proprio codice, le migrazioni e il rilascio. Gli installatori del portale non sostituiscono i moduli CRM: verificano sul server la ricevuta privata `canonical-release.json`, con revisione verificata e hash dei file installati. Una ricevuta assente, un file divergente o un modulo obbligatorio mancante bloccano il rilascio. Le copie CRM già presenti in `integrations/` servono alle prove di compatibilità; non sono più fonti da installare sul CRM e non devono essere aggiornate pubblicando correzioni private.

Il rilascio richiede controlli PHP, browser, MySQL e integrazioni effettive riusciti. I file di tema, snippet, componente di firma e collegamento Store dispongono di copie private per il ripristino, con verifica dei byte e rifiuto di sovrascrivere modifiche concorrenti. La ricevuta finale viene scritta solo dopo la verifica dell'intero rilascio; un errore mantiene il rilascio non confermato.

Le prove HTTP con i plugin installati usano un processo PHP temporaneo sull'hosting, raggiungibile solo da `127.0.0.1`, con database separato, artista ordinario fittizio, password e cookie reali, caricamenti multipart e messaggi bloccati. Il processo viene terminato e la fixture viene rimossa anche in caso di errore. Questo percorso automatizzato non richiede un accesso manuale. Durante il ramo di audit si aggiunge la stessa prova attraverso il browser locale e il PHP del sito pubblico: l'endpoint ha token casuale e scadenza, la sola credenziale sintetica di avvio è cifrata per la sessione locale e nessuna password di utenti esistenti viene esportata. La chiave privata di collaudo resta fuori dal repository.

La manutenzione dei log può essere installata separatamente solo dopo le verifiche del candidato e dello stack installato: conserva in privato gli originali, verifica i file e la pianificazione quotidiana nativa di WordPress, e prevede il ripristino dei soli file modificati. Il suo esito non certifica il rilascio completo del portale o il funzionamento dei servizi esterni.
