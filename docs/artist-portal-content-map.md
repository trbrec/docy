# Area Artisti TRB rec — mappa contenuti

Questo documento guida la migrazione dal precedente archivio FAQ al nuovo
portale. Non contiene prezzi, condizioni economiche o credenziali.

## Regola di base

Una release e' una pratica distinta. L'artista non richiede servizi in modo
isolato: ogni passaggio e ogni materiale appartengono alla release indicata.

1. Dati contrattuali di distribuzione della release.
2. File audio.
3. Copertina o brief grafico, secondo contratto.
4. Dati editoriali e promozionali.
5. Verifica TRB e programmazione.

La valutazione demo resta esterna e facoltativa.

## Matrice contrattuale centralizzata

La fonte unica dei servizi e' `trb_portal_service_catalogue()` in
`inc/trb-artist-portal.php`. Ogni nuova pagina deve interrogare questa matrice
e non ricostruire le regole confrontando direttamente i nomi dei profili.

Gli stati ammessi sono:

- `included`: servizio compreso nel contratto;
- `store_50`: servizio acquistabile nello Store con sconto riservato del 50%;
- `unavailable`: servizio non compreso e non proposto separatamente.

Durata, quote di pubblicazione, formazione e passaggio al roster sono raccolti
in `trb_portal_contract_rules()`.

## Visibilita' per profilo

| Area | DDS | DDB12 | DDB | DDB-TRB | TRB |
| --- | --- | --- | --- | --- | --- |
| Procedura release e distribuzione | Si | Si | Si | Si | Si |
| Mastering incluso | Store -50% | Si | Si | Si | Si |
| Formazione | Base | Completa + attestato | Completa + attestato | Completa + attestato | Non prevista |
| Copertina inclusa | Store -50% | Store -50% | Store -50% | Si | Si |
| Ottimizzazione Spotify/Apple | Store -50% | Si | Si | Si | Si |
| Smartlink, Promo Cards e pitching | Si | Si | Si | Si | Si |
| Playlist proprietarie | Si | Si | Si | Si | Si |
| Campagne curatori/blogger/influencer | Store -50% | Si | Si | Si | Si |
| Comunicato stampa e Radio Date | Store -50% | Store -50% | Store -50% | Si | Si |
| Landing page e booking | No | Si | Si | Si | Si |

La tabella e' una guida di interfaccia: ogni singola azione deve essere
verificata contro il contratto vigente e non puo' essere inferita per
gerarchia.

## FAQ da consolidare

L'archivio storico contiene molte copie quasi identiche, spesso con differenze
solo nel nome del contratto o nel vecchio percorso del sito. Ogni gruppo verra'
ridotto a una risposta canonica, con testo comune e blocchi di visibilita' per
profilo quando necessario.

Priorita' iniziali:

1. Procedura di pubblicazione di una nuova release.
2. Formati audio: 48 kHz / 24 bit per pre-master e master, WAV o AIFF, limiti
   di picco e consegna dei file.
3. Tempistiche: consegna, mastering, distribuzione e periodi festivi.
4. Metadati, titolarita', featuring e collaborazioni.
5. Copertine: requisiti tecnici, upload e richiesta grafica inclusa.
6. Pitching, Spotify e Apple Music, senza promesse di risultato.
7. Materiali promozionali, profili artista e post-release.
8. Download ed e-book avanzati per DDB, DDB-TRB e TRB.

## Esperienza di ricerca

La ricerca deve restituire titoli e risposte nella dashboard, con contenuti
espandibili nella stessa pagina. Le pagine separate restano riservate ai casi
realmente complessi, non sono il percorso normale per trovare una risposta.
