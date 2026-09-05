#!/usr/bin/env python3
import json
from huggingface_hub import hf_hub_download
from llama_cpp import Llama

samples = [
"New project", "Open project...", "Save project", "Save project as...", "Save new version of project", "Save all projects", "Project templates", "Recent projects", "Project settings...", "Render...", "Save live output to disk (bounce)...", "Consolidate/export tracks...", "Quit", "Undo", "Redo", "Cut", "Copy", "Time selection", "Dynamic split items", "Tempo envelope", "Track panels", "Floating mixer master", "Monitoring FX", "Transport (play/record/stop...)", "Track wiring", "Project Media/FX Bay", "Show/hide all floating windows", "Cascade all floating windows", "Time unit for ruler", "Zoom in", "Zoom out", "Go to edit cursor", "Go to play cursor", "Move cursor forward one measure", "Always on top", "Media file...", "New MIDI item", "SMPTE LTC/MTC timecode generator", "New subproject", "Marker (prompt for name)", "Region (from time selection)", "Measure from time selection (detect tempo)", "Virtual instrument on new track...", "Track from template", "Empty space in time selection", "Copy loop of selected area of items", "Nudge/set items...", "Take channel mode: Reverse stereo", "Take channel mode: Mono (downmix)", "Peaks", "Build peaks", "Rebuild peaks for selected items", "Set all media online", "Set selected media offline", "Envelope points", "Overlapping recording behavior", "Add lanes (new lanes play exclusively)", "Split existing items and add takes (default)", "Ripple edit settings", "Ripple per-track affects each track separately", "Fixed item lanes", "Track Manager...", "Lane Manager...", "Set point value...", "Delete point", "Select envelope", "Slow start/end", "Fast start", "Fast end", "Automation items", "Create pooled duplicate", "Remove from pool", "Glue", "Automation item options for this envelope", "Global automation override", "Trim/Read", "Touch (record fader movements to armed envelopes)", "Latch", "Write (record fader positions to armed envelopes)", "Record mode: time selection auto-punch", "Close all projects but current", "Exclusive solo", "Unsolo all", "Delete lane (including media items)", "Duplicate items to new lane"
]
model_path = hf_hub_download(
    repo_id="tensorblock/Qwen2.5-1.5B-Instruct-GGUF",
    filename="Qwen2.5-1.5B-Instruct-Q4_K_M.gguf",
)
llm = Llama(model_path=model_path, n_ctx=8192, n_threads=4, n_batch=512, verbose=False)
system = """Sei un traduttore tecnico senior di software audio. Traduci stringhe dell'interfaccia di REAPER DAW dall'inglese all'italiano professionale, conciso e naturale. Mantieni invariati REAPER, FX, MIDI, SMPTE, LTC, MTC, TCP, MCP, file extension e termini tecnici universalmente usati. Usa: track=traccia, item=elemento, take=take, lane=corsia, send=mandata, receive=ricezione, envelope=inviluppo, record arm=abilita registrazione, edit cursor=cursore di modifica, play cursor=cursore di riproduzione, media item=elemento multimediale, render=rendering/esegui rendering, ripple edit=modifica con propagazione, pooled duplicate=duplicato condiviso, glue=consolida. Non spiegare. Restituisci solo un array JSON di oggetti con campi id e it, nello stesso ordine e con tutti gli id."""
items = [{"id": i, "en": s} for i,s in enumerate(samples)]
resp = llm.create_chat_completion(
    messages=[{"role":"system","content":system},{"role":"user","content":json.dumps(items, ensure_ascii=False)}],
    temperature=0.0,
    max_tokens=5000,
)
print(resp["choices"][0]["message"]["content"])
