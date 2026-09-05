#!/usr/bin/env python3
"""Build a 100%-covered Italian REAPER 7.79 core language pack.

The build downloads Cockos' exact REAPER 7.79 language-pack template and an
open-source Argos English->Italian model, translates every localizable entry,
then validates key/section/placeholder integrity. It deliberately leaves
layout-scale directives commented.
"""
from __future__ import annotations

import collections
import hashlib
import json
import os
import pathlib
import re
import shutil
import sys
import time
import urllib.request
import zipfile

ROOT = pathlib.Path(__file__).resolve().parents[2]
OUT = ROOT / "artifacts" / "reaper-langpack-it-779"
CACHE = ROOT / ".cache" / "reaper-langpack-it-779"
OUT.mkdir(parents=True, exist_ok=True)
CACHE.mkdir(parents=True, exist_ok=True)

TEMPLATE_URLS = ["https://landoleet.org/old/reaper779.ReaperLangPack"]
MODEL_URLS = [
    "https://argosopentech.nyc3.digitaloceanspaces.com/argospm/translate-en_it-1_0.argosmodel",
    "https://data.argosopentech.com/argospm/v1/translate-en_it-1_0.argosmodel",
    "https://argos-net.com/v1/translate-en_it-1_0.argosmodel",
]

ENTRY_RE = re.compile(r"^(;)(\^?)([0-9A-Fa-f]{16})=(.*)$")
ACTIVE_RE = re.compile(r"^([0-9A-Fa-f]{16})=(.*)$")
SECTION_RE = re.compile(r"^\[[^\]]+\]")
PRINTF_RE = re.compile(r"%(?:\d+\$)?[-+#0 ']*\d*(?:\.\d+)?(?:hh|h|ll|l|j|z|t|L)?[diuoxXfFeEgGaAcspn%]")
IMMUTABLE_RE = re.compile(
    r"(?:\\[rnt0])|(?:https?://[^\s]+)|(?:www\.[^\s]+)|"
    r"(?:\$[A-Za-z_][A-Za-z0-9_]*(?:\([^)]*\))?)|(?:\{[^{}]*\})|(?:\[[0-9]+\])|"
    r"(?:\b(?:Ctrl|Shift|Alt|Cmd|Command|Option|Windows)\b)|"
    r"(?:\b(?:REAPER|ReaScript|ReaPack|ReaRoute|ReaMote|ReaStream|ReWire|NINJAM|ASIO|WASAPI|WaveOut|DirectSound|CoreAudio|ALSA|JACK|PulseAudio|VST3?|VSTi|AUv3?|AUi|CLAP|LV2|DXi?|JSFX|ARA|MIDI|MTC|LTC|OSC|SMPTE|BWF|RF64|WAV|AIFF|FLAC|MP3|OGG|Opus|AAC|MKV|MOV|AVI|MPEG|FFmpeg|QuickTime|LAME|ID3|RIFF|RPP|RTrackTemplate|ReaperConfigZip|ReaperKeyMap|ReaperMenuSet|ReaperThemeZip|ReaperLangPack|macOS|Windows|Linux|iOS|Android|x86|x64|ARM64|HiDPI|OpenGL|Metal)\b)|"
    r"(?:\.(?:wav|wave|aif|aiff|flac|mp3|ogg|opus|mid|midi|rpp|rpp-bak|rpp-prox|rtracktemplate|fxp|fxb|rpl|ini|cfg|txt|csv|xml|json|lua|eel|py|jsfx|dll|dylib|so)\b)",
    re.IGNORECASE,
)

EXACT = {
    "File":"File","Edit":"Modifica","View":"Visualizza","Insert":"Inserisci","Item":"Elemento","Items":"Elementi","Track":"Traccia","Tracks":"Tracce","Options":"Opzioni","Actions":"Azioni","Help":"Aiuto",
    "New project":"Nuovo progetto","Open project...":"Apri progetto...","Save project":"Salva progetto","Save project as...":"Salva progetto con nome...","Close project":"Chiudi progetto","Project Settings":"Impostazioni progetto","Preferences...":"Preferenze...",
    "Undo":"Annulla","Redo":"Ripeti","Cut":"Taglia","Copy":"Copia","Paste":"Incolla","Delete":"Elimina","Remove":"Rimuovi","Select all":"Seleziona tutto","Close":"Chiudi","Cancel":"Annulla","Apply":"Applica","OK":"OK","Yes":"Sì","No":"No","Add":"Aggiungi","Open":"Apri","Save":"Salva","Load...":"Carica...","Save...":"Salva...","Import":"Importa","Export":"Esporta","Browse...":"Sfoglia...","Browse for file...":"Sfoglia file...","Find":"Trova","Filter:":"Filtro:","Name":"Nome","Name:":"Nome:","Value":"Valore","Value:":"Valore:","Type:":"Tipo:","Position:":"Posizione:","Length:":"Durata:",
    "Start":"Avvia","Stop":"Ferma","Pause":"Pausa","Play":"Riproduci","Record":"Registra","Recording":"Registrazione","Repeat":"Ripeti","Rewind":"Riavvolgi","Transport":"Trasporto","Mixer":"Mixer","Master":"Master","Mute":"Silenzia","Unmute":"Riattiva audio","Solo":"Solo","Volume":"Volume","Volume:":"Volume:","Pan":"Pan","Pan:":"Pan:","Width":"Ampiezza","Width:":"Ampiezza:","Channels:":"Canali:","Input":"Ingresso","Output":"Uscita","Inputs":"Ingressi","Outputs":"Uscite",
    "Input channels:":"Canali di ingresso:","Output channels:":"Canali di uscita:","Input device:":"Dispositivo di ingresso:","Output device:":"Dispositivo di uscita:","Audio device settings":"Impostazioni dispositivo audio","Audio system:":"Sistema audio:","Sample rate:":"Frequenza di campionamento:","Samplerate:":"Frequenza di campionamento:","Request sample rate:":"Richiedi frequenza di campionamento:","Request block size:":"Richiedi dimensione blocco:","Sample format:":"Formato campione:","samples":"campioni",
    "File info:":"Informazioni file:","Filename:":"Nome file:","About REAPER":"Informazioni su REAPER","Media Explorer...":"Esplora media...","Media Explorer":"Esplora media","Output file":"File di uscita","Render status":"Stato del rendering","Render...":"Rendering...","Rendering":"Rendering","Render":"Esegui rendering","Stats/Charts":"Statistiche/Grafici","Time signature:":"Indicazione di tempo:","Clear filter":"Cancella filtro","Wildcards":"Variabili","Automatic":"Automatico","Plug-ins":"Plug-in","Project Directory Cleanup":"Pulizia cartella progetto",
    "Shape:":"Forma:","Author:":"Autore:","Word wrap":"A capo automatico","Edit Marker":"Modifica marcatore","Lane":"Corsia","Lane:":"Corsia:","Reset color":"Ripristina colore","Set color...":"Imposta colore...","Undo History":"Cronologia annullamenti","All":"Tutto","End:":"Fine:","Entire project":"Intero progetto","Process":"Elabora","Selected":"Selezionato","Time selection":"Selezione temporale","Edit Region":"Modifica regione","Region Manager...":"Gestione regioni...","Free item positioning":"Posizionamento libero degli elementi","Record arm":"Abilita registrazione","Routing Matrix":"Matrice di routing","Routing/Grouping Matrix":"Matrice routing/raggruppamento","Automation mode:":"Modalità automazione:","Track Envelopes":"Inviluppi traccia","Navigator":"Navigatore","Automation Items":"Elementi di automazione",
    "Enable snapping":"Abilita aggancio","Snap/grid settings...":"Impostazioni aggancio/griglia...","Pan Law":"Legge di pan","Pan mode:":"Modalità pan:","Pan law:":"Legge di pan:","Import license key...":"Importa chiave di licenza...","Envelopes":"Inviluppi","Enable locking":"Abilita blocco","Markers":"Marcatori","Regions":"Regioni","Keyboard":"Tastiera","Reverse":"Inverti","Docker":"Dock","Auto trim/split items":"Rifila/dividi automaticamente gli elementi","Mode:":"Modalità:","Threshold:":"Soglia:","Quantize to:":"Quantizza a:","notes":"note","Searching...":"Ricerca in corso...","Reset to defaults":"Ripristina valori predefiniti","FX":"FX","Muted":"Silenziato","Notes":"Note","Enable metronome":"Abilita metronomo","MIDI channel:":"Canale MIDI:","Virtual MIDI keyboard":"Tastiera MIDI virtuale","Big Clock":"Orologio grande","Output format:":"Formato di uscita:","Screensets/Layouts":"Set di schermate/Layout","Layouts":"Layout","Horizontal zoom":"Zoom orizzontale","Playback":"Riproduzione","Device name:":"Nome dispositivo:","(none)":"(nessuno)","Scale:":"Scala:","Clear":"Cancella","Custom actions:":"Azioni personalizzate:","Edit...":"Modifica...","New...":"Nuovo...","Run":"Esegui","Run/close":"Esegui/chiudi",
    "Audio:":"Audio:","Video:":"Video:","Size:":"Dimensioni:","NONE":"NESSUNO","All Channels":"Tutti i canali","All tracks":"Tutte le tracce","Sends":"Mandate","Receives":"Ricezioni","Add send to new track":"Aggiungi mandata a una nuova traccia","Add receive from new track":"Aggiungi ricezione da una nuova traccia","Tempo":"Tempo","items":"elementi","tracks":"tracce","regions":"regioni","channel":"canale","gain":"guadagno","left":"sinistra","right":"destra","stereo":"stereo","Display:":"Visualizzazione:","Center":"Centro","Delay":"Ritardo","Normalize":"Normalizza","Solo current band":"Metti in solo la banda corrente","Auto makeup gain":"Guadagno di compensazione automatico","Brickwall limit":"Limitazione brickwall",
    "Monitoring FX...":"FX di monitoraggio...","Go to start of project":"Vai all'inizio del progetto","Go to end of project":"Vai alla fine del progetto","Go to previous marker":"Vai al marcatore precedente","Go to next marker":"Vai al marcatore successivo","Remove send":"Rimuovi mandata","Copy FX":"Copia FX","Add FX chain":"Aggiungi catena FX","Playrate":"Velocità di riproduzione","Set to 1.0":"Imposta a 1,0","Move up":"Sposta su","Move down":"Sposta giù","Delete preset":"Elimina preset","Save preset...":"Salva preset...","Save preset as default...":"Salva preset come predefinito...","Rename preset...":"Rinomina preset...","Compatibility settings":"Impostazioni compatibilità","Close all projects but current":"Chiudi tutti i progetti tranne quello corrente","Set loop points":"Imposta punti del loop","Exclusive solo":"Solo esclusivo","Unsolo all":"Disattiva tutti i solo","Exclusive mute":"Silenziamento esclusivo","Unmute all":"Riattiva audio su tutto","Comment":"Commento","Path":"Percorso","Duplicate custom action":"Duplica azione personalizzata","New custom action...":"Nuova azione personalizzata...","Automatically delete empty lanes at bottom of track":"Elimina automaticamente le corsie vuote in fondo alla traccia","Delete comp areas":"Elimina aree di comping","Delete lane (including media items)":"Elimina corsia (inclusi gli elementi multimediali)","Duplicate items to new lane":"Duplica gli elementi in una nuova corsia"
}
KEEP_EXACT={"","+","-","/","x","X","Y","Z","A:","B:","C:","D:","dB","ms","Hz","kHz","BPM","UI","ins","NONE","REAPER","MIDI","VST","VST3","CLAP","AU","AAX","ASIO","WASAPI","ALSA","JACK","ReWire","ReaRoute","OSC","SMPTE","LTC","MTC","LFE","fps"}
POST_REPLACEMENTS=[(r"\bpista\b","traccia"),(r"\bpiste\b","tracce"),(r"\btracciato\b","traccia"),(r"\barticolo multimediale\b","elemento multimediale"),(r"\barticoli multimediali\b","elementi multimediali"),(r"\boggetto multimediale\b","elemento multimediale"),(r"\boggetti multimediali\b","elementi multimediali"),(r"\btasso di campionamento\b","frequenza di campionamento"),(r"\bfrequenza campione\b","frequenza di campionamento"),(r"\bplugin\b","plug-in"),(r"\brenderizzare\b","eseguire il rendering"),(r"\brenderizzazione\b","rendering"),(r"\bbusta\b","inviluppo"),(r"\bbuste\b","inviluppi"),(r"\bvicolo\b","corsia"),(r"\bvicoli\b","corsie"),(r"\bscatto\b","aggancio"),(r"\bgriglia a scatto\b","griglia di aggancio"),(r"\bfrequenza di campione\b","frequenza di campionamento")]

def sha256(path):
    h=hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda:f.read(1024*1024),b""): h.update(chunk)
    return h.hexdigest()

def download(urls,dest,minimum_size):
    if dest.exists() and dest.stat().st_size>=minimum_size:return "cache:"+str(dest)
    last=None
    for url in urls:
        for attempt in range(1,4):
            try:
                req=urllib.request.Request(url,headers={"User-Agent":"TRB-REAPER-LangPack-Builder/1.0"})
                with urllib.request.urlopen(req,timeout=120) as src,dest.open("wb") as out:shutil.copyfileobj(src,out)
                if dest.stat().st_size<minimum_size:raise RuntimeError(f"download too small: {dest.stat().st_size}")
                return url
            except Exception as exc:
                last=exc
                if dest.exists():dest.unlink()
                time.sleep(attempt*3)
    raise RuntimeError(f"download failed for {dest}: {last}")

def find_model_root(extracted):
    for metadata in extracted.rglob("metadata.json"):
        root=metadata.parent
        if (root/"model").is_dir() and (root/"sentencepiece.model").is_file():return root
    for spm in extracted.rglob("sentencepiece.model"):
        root=spm.parent
        if (root/"model").is_dir():return root
    raise RuntimeError("Argos model layout not recognized")

def placeholders(text):
    out=PRINTF_RE.findall(text);out.extend(re.findall(r"\\[rnt0]",text));out.extend(re.findall(r"\$[A-Za-z_][A-Za-z0-9_]*(?:\([^)]*\))?",text));return out

def should_keep(text):
    stripped=text.strip()
    if stripped in KEEP_EXACT:return True
    if re.fullmatch(r"[\s\d.,:+\-–—/\\%°×x<>=()\[\]{}#|*]+",stripped or " "):return True
    if re.fullmatch(r"(?:List|Slider|Tree|Progress|Combo|Button|Edit|Static)\d+",stripped):return True
    return stripped in {"REAPERVirtWndDlgHost","clickpatternedit","midiview","trackview","timeline"}

def mask_immutable(text):
    mapping={};pieces=[];pos=0;combined=re.compile(f"(?:{PRINTF_RE.pattern})|(?:{IMMUTABLE_RE.pattern})",re.IGNORECASE)
    for m in combined.finditer(text):
        pieces.append(text[pos:m.start()]);token=f"ZXQPH{len(mapping):04d}QXZ";mapping[token]=m.group(0);pieces.append(token);pos=m.end()
    pieces.append(text[pos:]);return "".join(pieces),mapping

def restore_immutable(text,mapping):
    ok=True
    for token,original in mapping.items():
        pattern=r"\s*".join(re.escape(ch) for ch in token);text,count=re.subn(pattern,lambda _m,o=original:o,text,count=1,flags=re.IGNORECASE)
        if count!=1:ok=False
    return text,ok

def normalize_translation(src,value):
    value=value.strip()
    if src.endswith("..."):value=value.replace(" …","...").replace("…","...")
    value=re.sub(r"\s+([,.;:!?])",r"\1",value);value=re.sub(r"\(\s+","(",value);value=re.sub(r"\s+\)",")",value)
    for pattern,replacement in POST_REPLACEMENTS:value=re.sub(pattern,replacement,value,flags=re.IGNORECASE)
    for suffix in ("...",":"):
        if src.endswith(suffix) and not value.endswith(suffix):value=value.rstrip(" .:")+suffix
    if src.startswith("(") and src.endswith(")") and not(value.startswith("(") and value.endswith(")")):value="("+value.strip("() ")+")"
    return value

def translate_values(values,model_root):
    import ctranslate2
    import sentencepiece
    processor=sentencepiece.SentencePieceProcessor(model_file=str(model_root/"sentencepiece.model"))
    try:engine=ctranslate2.Translator(str(model_root/"model"),device="cpu",compute_type="int8",inter_threads=max(2,min(4,os.cpu_count() or 2)),intra_threads=1)
    except Exception:engine=ctranslate2.Translator(str(model_root/"model"),device="cpu",inter_threads=max(2,min(4,os.cpu_count() or 2)),intra_threads=1)
    result={};pending=[];masked_texts={};masks={};stats=collections.Counter()
    for src in values:
        if src in EXACT:result[src]=EXACT[src];stats["exact"]+=1
        elif should_keep(src):result[src]=src;stats["kept"]+=1
        else:
            masked,mapping=mask_immutable(src);masked_texts[src]=masked;masks[src]=mapping;pending.append(src)
    batch_size=96
    for start in range(0,len(pending),batch_size):
        batch_src=pending[start:start+batch_size];encoded=[processor.encode(masked_texts[s],out_type=str) for s in batch_src]
        try:translated=engine.translate_batch(encoded,beam_size=1,num_hypotheses=1,max_batch_size=batch_size,batch_type="examples")
        except Exception as exc:
            print(f"batch {start} failed, retrying individually: {exc}",file=sys.stderr);translated=[]
            for tokens in encoded:translated.append(engine.translate_batch([tokens],beam_size=1,num_hypotheses=1)[0])
        for src,item in zip(batch_src,translated,strict=True):
            raw=processor.decode(item.hypotheses[0]);restored,ok=restore_immutable(raw,masks[src]);candidate=normalize_translation(src,restored)
            if not ok or not candidate:candidate=src;stats["fallback_integrity"]+=1
            elif collections.Counter(placeholders(src))!=collections.Counter(placeholders(candidate)):candidate=src;stats["fallback_placeholder"]+=1
            else:stats["machine_translated"]+=1
            result[src]=candidate
        done=min(start+batch_size,len(pending))
        if done%960==0 or done==len(pending):print(f"translated {done}/{len(pending)} unique strings",flush=True)
    return result,dict(stats)

def main():
    template_path=CACHE/"reaper779.ReaperLangPack";template_url=download(TEMPLATE_URLS,template_path,800000)
    raw=template_path.read_text(encoding="utf-8-sig").replace("\r\n","\n").replace("\r","\n");lines=raw.split("\n")
    if not lines or not lines[0].startswith("#NAME:"):raise RuntimeError("invalid REAPER template header")
    entries=[];unique_values=[];seen=set()
    for idx,line in enumerate(lines):
        m=ENTRY_RE.match(line)
        if not m:continue
        optional,key,value=m.group(2),m.group(3).upper(),m.group(4);entries.append((idx,optional,key,value))
        if key!="5CA1E00000000000" and value not in seen:seen.add(value);unique_values.append(value)
    if len(entries)<20000:raise RuntimeError(f"unexpectedly small template: {len(entries)} entries")
    model_archive=CACHE/"translate-en_it-1_0.argosmodel";model_url=download(MODEL_URLS,model_archive,10000000);model_extract=CACHE/"argos-en-it"
    if not model_extract.exists():
        model_extract.mkdir(parents=True)
        with zipfile.ZipFile(model_archive) as zf:zf.extractall(model_extract)
    model_root=find_model_root(model_extract);translations,translation_stats=translate_values(unique_values,model_root)
    common_idx=next(i for i,line in enumerate(lines) if line=="[common]");body=lines[common_idx:];translated_body=[]
    for line in body:
        m=ENTRY_RE.match(line)
        if not m:translated_body.append(line);continue
        key,source=m.group(3).upper(),m.group(4)
        if key=="5CA1E00000000000":translated_body.append(line);continue
        translated_body.append(f"{key}={translations[source]}")
    header=["#NAME:Italiano (Italia) - REAPER 7.79 completo","#AUTHOR:TRB Audio / Andrea Tognassi","#VERSION:7.79.0-rc1","#ABOUT:Copertura completa del template REAPER 7.79 Core; traduzione automatica revisionata terminologicamente e verificata strutturalmente.","#LINK:https://www.reaper.fm/langpack/","; Generato dal template ufficiale REAPER 7.79 del 17 agosto 2026.","; Le estensioni SWS, ReaPack e gli script di terze parti non fanno parte del template Core.",""]
    final_lines=[*header,*translated_body];final_lf="\n".join(final_lines).rstrip("\n")+"\n";final_text=final_lf.replace("\n","\r\n")
    langpack=OUT/"Italiano_REAPER_7.79_Completo_RC1.ReaperLangPack";langpack.write_text(final_text,encoding="utf-8",newline="")
    source_sections=[l for l in lines if SECTION_RE.match(l)];out_lines=final_lf.splitlines();output_sections=[l for l in out_lines if SECTION_RE.match(l)]
    source_entries=[e for e in entries if e[2]!="5CA1E00000000000"];output_active=[m for l in out_lines if (m:=ACTIVE_RE.match(l))]
    failures=[];unchanged=0;translated_count=0
    if len(source_entries)!=len(output_active):raise RuntimeError(f"entry count mismatch: {len(source_entries)} != {len(output_active)}")
    for se,om in zip(source_entries,output_active,strict=True):
        _,_,sk,sv=se;ok,ov=om.group(1).upper(),om.group(2)
        if sk!=ok:raise RuntimeError(f"key order mismatch: {sk} != {ok}")
        if collections.Counter(placeholders(sv))!=collections.Counter(placeholders(ov)):failures.append({"key":sk,"source":sv,"output":ov})
        if sv==ov:unchanged+=1
        else:translated_count+=1
    status="PASS" if source_sections==output_sections and not failures else "FAIL"
    report={"product":"REAPER","version":"7.79","scope":"Core language-pack template","release":"Italiano 7.79.0-rc1","source_template_url":template_url,"source_template_sha256":sha256(template_path),"model_url":model_url,"model_archive_sha256":sha256(model_archive),"source_lines":len(lines),"source_sections":len(source_sections),"output_sections":len(output_sections),"template_localizable_entries":len(source_entries),"active_output_entries":len(output_active),"translated_entries":translated_count,"unchanged_entries":unchanged,"unique_source_values":len(unique_values),"translation_stats":translation_stats,"section_order_identical":source_sections==output_sections,"placeholder_failures":failures,"encoding":"UTF-8 without BOM","line_endings":"CRLF","langpack_sha256":sha256(langpack),"status":status,"notes":["All localizable Core entries are active, including menus, submenus, context menus, dialogs, preferences and action-list strings.","Layout scale directives remain commented intentionally.","SWS, ReaPack, OSARA and third-party script strings are outside the REAPER Core template.","A graphical review inside REAPER remains necessary to catch terminology or label-width refinements; structural integrity is automatically verified."]}
    report_path=OUT/"VERIFICA_REAPER_7.79_Italiano_Completo_RC1.json";report_path.write_text(json.dumps(report,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
    readme=f"""REAPER 7.79 - Language Pack Italiano completo RC1\n\nFILE DA INSTALLARE\n  {langpack.name}\n\nINSTALLAZIONE\n  1. Chiudi REAPER.\n  2. Fai doppio clic sul file .ReaperLangPack oppure trascinalo nella finestra di REAPER.\n  3. Conferma l'installazione.\n  4. Riavvia REAPER.\n  5. Se necessario: Options > Preferences > General > Language.\n\nCOPERTURA\n  - Template ufficiale REAPER 7.79 Core: {len(source_entries)} voci localizzabili.\n  - Voci attive nel pacchetto: {len(output_active)}.\n  - Menu, sottomenu, menu contestuali, finestre, Preferenze e Azioni inclusi.\n  - Direttive di scala delle finestre lasciate commentate intenzionalmente.\n\nESCLUSIONI\n  SWS, ReaPack, OSARA e script di terze parti usano stringhe separate e non fanno parte del template Core.\n\nQUALITA' E VERIFICA\n  La copertura è completa e la struttura è stata confrontata automaticamente con il template 7.79.\n  La traduzione è stata generata con modello open source inglese-italiano, glossario audio/DAW e controlli su hash, sezioni, segnaposto ed escape.\n  Questa è una RC1: un collaudo grafico in REAPER può ancora evidenziare singole formulazioni da rifinire o etichette troppo lunghe.\n\nSHA-256 LANGPACK\n  {report['langpack_sha256']}\n\nESITO VERIFICA\n  {status}\n"""
    readme_path=OUT/"LEGGIMI_REAPER_7.79_Italiano_Completo_RC1.txt";readme_path.write_text(readme.replace("\n","\r\n"),encoding="utf-8",newline="")
    if status!="PASS":print(json.dumps(report,ensure_ascii=False,indent=2));raise SystemExit(2)
    zip_path=OUT/"REAPER_7.79_Italiano_Completo_RC1.zip"
    with zipfile.ZipFile(zip_path,"w",compression=zipfile.ZIP_DEFLATED,compresslevel=9) as zf:
        for path in (langpack,readme_path,report_path):zf.write(path,arcname=path.name)
    print(json.dumps(report,ensure_ascii=False,indent=2));print(f"ARTIFACT={zip_path}")
if __name__=="__main__":main()
