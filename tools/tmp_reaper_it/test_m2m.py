#!/usr/bin/env python3
from huggingface_hub import snapshot_download
import ctranslate2
from transformers import AutoTokenizer

samples = [
"New project", "Open project...", "Save project", "Save project as...", "Save new version of project", "Save all projects", "Project templates", "Recent projects", "Project settings...", "Render...", "Save live output to disk (bounce)...", "Consolidate/export tracks...", "Quit", "Undo", "Redo", "Cut", "Copy", "Time selection", "Dynamic split items", "Tempo envelope", "Track panels", "Floating mixer master", "Monitoring FX", "Transport (play/record/stop...)", "Track wiring", "Project Media/FX Bay", "Show/hide all floating windows", "Cascade all floating windows", "Time unit for ruler", "Zoom in", "Zoom out", "Go to edit cursor", "Go to play cursor", "Move cursor forward one measure", "Always on top", "Media file...", "New MIDI item", "SMPTE LTC/MTC timecode generator", "New subproject", "Marker (prompt for name)", "Region (from time selection)", "Measure from time selection (detect tempo)", "Virtual instrument on new track...", "Track from template", "Empty space in time selection", "Copy loop of selected area of items", "Nudge/set items...", "Take channel mode: Reverse stereo", "Take channel mode: Mono (downmix)", "Peaks", "Build peaks", "Rebuild peaks for selected items", "Set all media online", "Set selected media offline", "Envelope points", "Overlapping recording behavior", "Add lanes (new lanes play exclusively)", "Split existing items and add takes (default)", "Ripple edit settings", "Ripple per-track affects each track separately", "Fixed item lanes", "Track Manager...", "Lane Manager...", "Set point value...", "Delete point", "Select envelope", "Slow start/end", "Fast start", "Fast end", "Automation items", "Create pooled duplicate", "Remove from pool", "Glue", "Automation item options for this envelope", "Global automation override", "Trim/Read", "Touch (record fader movements to armed envelopes)", "Latch", "Write (record fader positions to armed envelopes)", "Record mode: time selection auto-punch", "Close all projects but current", "Exclusive solo", "Unsolo all", "Delete lane (including media items)", "Duplicate items to new lane"
]

path = snapshot_download(repo_id="entai2965/m2m100-418M-ctranslate2")
translator = ctranslate2.Translator(path, device="cpu", compute_type="int8", inter_threads=4, intra_threads=1)
tok = AutoTokenizer.from_pretrained(path, clean_up_tokenization_spaces=True)
tok.src_lang = "en"
encoded = [tok.convert_ids_to_tokens(tok.encode(s)) for s in samples]
prefix = [[tok.lang_code_to_token["it"]]] * len(encoded)
res = translator.translate_batch(encoded, target_prefix=prefix, beam_size=4, max_batch_size=32)
for src, item in zip(samples, res):
    out = tok.decode(tok.convert_tokens_to_ids(item.hypotheses[0][1:]), skip_special_tokens=True)
    print(f"{src}\t=>\t{out}")
