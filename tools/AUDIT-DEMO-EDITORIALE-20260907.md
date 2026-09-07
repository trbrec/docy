# Audit del percorso di valutazione demo — 7 settembre 2026

## Evidenza e perimetro

Email QA #12309, log #925, protocollo 20260907.5, allegata dal titolare.
Esaminati: dichiarazioni/provenienza, prompt iniziale, controllo editoriale, estrazione del servizio, conservazione dei risultati, recupero dopo errore, composizione HTML, destinatari e blocco di invio. Il controllo automatico non equivale a una garanzia di validità di ogni giudizio artistico.

| Rilievo | Causa riscontrata | Correzione 20260907.6 |
| --- | --- | --- |
| Voce singolare dentro una email del team | Il prompt assegnava il ruolo di consulente singolo; non prescriveva il plurale e non lo validava | Ruolo del team in entrambi i passaggi; controllo della prosa finale e controllo aggiuntivo prima dell’invio |
| “Valuto”, “analizzo”, “non posso” nell’email consegnata | I test verificavano struttura e recapito, senza questi casi linguistici | Regressioni basate sulle frasi effettivamente osservate; invio bloccato quando ricompaiono |
| Servizi completamente assenti | Il selettore poteva restituire nessuna proposta; mancata risposta, JSON invalido e scelta intenzionale diventavano tutti un array vuoto | Decisione obbligatoria con stato selected/none/missing/invalid; motivazione ed evidenza conservate; omissioni tecniche bloccano l’invio |
| Assenza non spiegata di una proposta | La possibilità di lavorare da soli era motivo automatico di esclusione | Supporto facoltativo ammesso se pertinente; se non utile, motivazione specifica visibile nel messaggio |
| Istruzioni in conflitto | Divieto assoluto di vendita/servizi e richiesta “SOLO” della valutazione, seguiti dalla richiesta di metadati commerciali | Distinzione esplicita tra corpo editoriale e decisione separata sul supporto |
| Vecchie valutazioni recuperabili senza nuova verifica | Il recupero considerava sufficiente la presenza di testo e consumi API | Il riuso richiede voce conforme e decisione valida; il mittente finale ricontrolla anche il supporto conservato |
| Diagnostica incapace di spiegare il problema | Nessuno stato separato della decisione sui servizi | Stati e motivazioni nella diagnostica QA; i dati precedenti sono marcati non tracciati, senza inventare la causa |
| 50% e banner assenti | Attivazione Store ancora disabilitata, come da gate precedente | Stato non attivo reso esplicito nel pannello; il servizio consigliato è indipendente dal gate economico |

## Risultati preservati

- Il destinatario riceve il tu; il team usa noi o forme impersonali.
- I versi citati e le alternative non vengono convertiti al plurale. Non viene eseguita una sostituzione indiscriminata del testo.
- La scelta del provino precedente resta esplicita e limitata allo stesso proprietario.
- Le dichiarazioni su testo, composizione ed esecuzione restano distinte. Le copie QA non dimostrano la paternità del titolare dei materiali di altri artisti.
- Senza audio non sono ammesse proposte di mastering/produzione né certezze su cantabilità e misure sonore.
- Una proposta deve riferirsi a una frase presente nella valutazione definitiva. I dati invalidi non sono trasformati in un footer generico.
- Non vengono inventati prezzi, sconti o servizi. Il gruppo TRB è indirizzato al referente.
- Il collaudo invia solo al titolare; le email reali preservano la copia al titolare.

## Limiti e attività ancora bloccate

Il vecchio record non conserva una decisione grezza: non è possibile stabilire retroattivamente se l’assenza del servizio in #12309 fosse una scelta del modello oppure un risultato scartato dal parser. È precisamente la lacuna di osservabilità corretta.

Il riconoscimento automatico Store, il 50% e il banner non sono ancora attivi. Il CAPTCHA SiteGround rimane visibile nella sessione Store: il collaudo commerciale end-to-end resta distinto da questo audit editoriale. Non sono state annunciate condizioni automatiche come funzionanti né eseguiti pagamenti.

Il catalogo editoriale è una selezione verificata di tre servizi (revisione testo, mastering stereo, produzione Essential), non l’intero Store. Non viene aggiunto un servizio dal solo nome o da una ricerca incompleta. Per un bisogno non coperto deve comparire una motivazione di mancata proposta, non un’offerta inventata.

Il controllo lessicale della voce intercetta le regressioni note e numerose formule editoriali singolari, ma non è un analizzatore linguistico universale. La revisione reale dell’email finale resta necessaria nel QA.

## Estensione emersa dal collaudo audio — protocollo 20260907.7

La prima risposta audio di collaudo è stata bloccata dalla decisione sui servizi non valida. Esaminando il testo respinto sono emerse anche citazioni di versi senza testo allegato e localizzazioni numeriche non verificate. Il precedente gate verificava le citazioni soltanto quando esisteva un testo sorgente: questo lasciava scoperto il ramo senza testo.

Ora il ramo audio rifiuta citazioni di versi senza sorgente testuale e timestamp numerici privi di una localizzazione strumentale verificata. Il controllo è ripetuto prima dell’invio e prima del riuso di risultati conservati. Le citazioni di un testo effettivamente allegato restano ammesse, con il relativo controllo di corrispondenza.

Il controllo finale audio usa `gpt-audio`, mantenendo `gpt-audio-mini` per la prima lettura e fornendo nuovamente l’audio originale al controllo. Non si affida a un modello solo testuale per decidere fatti sonori. Sono registrati separatamente i consumi di ogni passaggio, compresi i tentativi respinti. Compatibilità Chat Completions, input audio e tariffe verificate nella [documentazione ufficiale OpenAI](https://developers.openai.com/api/docs/models/gpt-audio). Il cambio di modello non costituisce da solo una prova di maggiore correttezza: l’esito reale va letto.

Le decisioni respinte conservano anche il contenuto candidato e un errore specifico (schema, lunghezza, catalogo/materiali, evidenza, condizioni economiche o voce), per evitare una nuova diagnosi basata soltanto su un array vuoto. Il parser elimina le delimitazioni tecniche dei metadati; l’anteprima amministrativa usa una valutazione QA effettivamente inviata.
