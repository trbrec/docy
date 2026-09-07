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

## Verifiche reali ed estensione 20260907.8

La QA autoriale #12310, email #926 inviata esclusivamente al titolare, è stata letta integralmente: voce del team coerente, limite «non possiamo stabilire», proposta di revisione del testo motivata sui verbi della luce e sul referente della cornice, collegamento al servizio e prerequisito melodico esplicito. Nessun coupon o annuncio del 50% non attivo.

La QA audio #12311 ha ritentato automaticamente sotto il protocollo .6 e ha consegnato la email #927 prima dell'aggiornamento .7. Questa prova NON è approvata: includeva parole non documentate, timestamp e un consiglio scorretto sul vibrato. Il primo tentativo #12312 sotto .7 è invece stato bloccato: mancavano i metadati e il testo violava anche i nuovi vincoli audio.

Nel protocollo .8 viene eliminata la vecchia istruzione permissiva sui timestamp, in conflitto con il controllo finale. La seconda lettura audio è indipendente: riceve gli originali senza la bozza, per non ereditarne ancoraggi inventati. Una consegna sintetica dopo i materiali ribadisce vincoli, ruolo del team e metadati obbligatori. Sono esplicitamente esclusi il vibrato come mascheramento dell'intonazione e la modifica del tempo della base come correzione di un ingresso. I controlli bloccanti rimangono invariati; non vengono aggirati per far passare il collaudo.

## Esito finale osservato

Protocollo .8 verificato nel portale dopo deploy riuscito (commit f7f4df38ab70368f2e6c5a8be89b9f5ec1aa2cf7). La QA #12313 è stata inviata nel log #929 alle 15:02:16, orario visualizzato dal pannello. Letti integralmente corpo HTML e intestazioni: unico destinatario andrea.tognassi@trbrec.com, nessun CC/BCC, voce del team coerente, assenza di citazioni di versi e timestamp numerici, supporto non proposto con motivazione visibile riferita al progetto. Non ricompare il suggerimento di mascherare l’intonazione col vibrato. La QA #12312 è rimasta in manual_review, con avviso al solo titolare (log #928).

Il test verifica queste regressioni e il comportamento reale del percorso, non certifica ogni rilievo musicale. Le descrizioni percettive e le ipotesi tecniche nella valutazione audio non sono misurazioni strumentali; il collaudo non costituisce una verifica indipendente dell’esattezza di ogni nota, sezione o diagnosi di registro. Il riconoscimento Store e il 50% rimangono non collaudati e disattivati.

## Aggiornamento Store: collegamento attivato e collaudato

Questo aggiornamento sostituisce il precedente stato di blocco Store. Dopo il nuovo accesso reso disponibile dal titolare, mancava la chiave dedicata su entrambi i siti. È stata configurata una chiave casuale separata da quella dei contratti; la verifica autenticata ha confermato l’attivazione su entrambi i siti. Nessun segreto è incluso nel rapporto o nel repository.

La conferma dell’email dell’account Store del titolare è stata verificata tramite email realmente ricevuta e relativo pulsante nel browser. L’account amministrativo è correttamente escluso dallo sconto se non associato a un profilo artista idoneo. La comunicazione di conferma è stata poi aggiornata con mittente TRB rec, HTML e pulsante, eliminando il mittente generico WordPress e il collegamento grezzo.

Il workflow Live Store artist benefits QA, esecuzione 34132883531, job 101776974859, è riuscito sul server reale: account temporanei marcati nei due database, lookup HTTP firmati reali, carrello WooCommerce reale con due unità di un servizio a prezzo pieno e un servizio già in offerta. Subtotale imponibile 2.819,59; sconto WooCommerce 1.409,79 per DDS, DDB12, DDB e DDB-TRB, con arrotondamento monetario. Sconto zero per TRB, nessun profilo, approvazione pendente e contratto scaduto. Il profilo TRB non genera un ordine gratuito.

Verificati: email non confermata esclusa, token errato/scaduto/consumato respinto, cambio email invalida la prova, verifica aggiornata al checkout, rimozione dello sconto dopo revoca e blocco del pagamento di un ordine preesistente (oggetto non salvato), rifiuto del vecchio coupon. Il pannello del portale contiene il 50% e l’email esatta del profilo. Le fixture sono state eliminate da entrambi i siti; nessun ordine o pagamento, email delle fixture intercettate senza invio e nessuna modifica ai carrelli dei clienti.

Il collaudo con i profili è avvenuto tramite esecuzione CLI isolata sul server, non tramite login browser a ciascuno degli otto profili. La prova browser riguarda la conferma email del titolare e i contenuti del pannello. Queste evidenze sono distinte per non confondere un calcolo reale WooCommerce con un acquisto completo.

Email finale #12314, log #930, inviata esclusivamente al titolare e letta integralmente nel registro: proposta “Revisione e Adattamento Autoriale del Testo” motivata sui verbi di “Passo la luce” e del telefono; voce plurale fino ai limiti; riquadro “Le tue condizioni riservate” con 50% su qualsiasi servizio, indirizzo del destinatario, conferma email e accesso allo Store senza codice. Il primo tentativo con evidenza non letterale è stato respinto; il secondo è passato. Il profilo DDB è una simulazione della copia QA, non una modifica al profilo amministrativo del titolare.
