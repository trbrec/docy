# Portale Artisti TRB rec — Docy

Repository privato del tema Docy e dei moduli proprietari del portale `artist.trbrec.com`.

## Contenuto

- tema e moduli del portale in `functions.php`, `inc/` e `assets/`;
- integrazioni CRM, onboarding, fogli e Site Studio in `integrations/`;
- verifiche e strumenti amministrativi in `tests/` e `tools/`;
- controlli prima del merge e deploy su SiteGround in `.github/workflows/`.

Ogni push su `main` avvia il deploy di produzione. Le correzioni in collaudo devono restare su un ramo e in una PR in bozza fino alla revisione finale e alle prove funzionali con dati fittizi. Un controllo statico o un test con servizi simulati non dimostra il funzionamento delle integrazioni reali.

La matrice di collaudo deve comprendere salvataggio del profilo, residenza e nascita estere, identificativi fiscali e documenti, caricamenti multipart e a blocchi, sostituzioni, interruzioni, invii ripetuti e simultanei, accessi autorizzati e respinti. Email, contratti, distribuzione e servizi esterni richiedono destinazioni di test isolate durante il collaudo.
