#!/usr/bin/env python3
import json, time, urllib.parse, urllib.request
samples = [
"New project", "Open project...", "Save project", "Save project as...", "Save new version of project", "Save all projects", "Project templates", "Recent projects", "Project settings...", "Render...", "Save live output to disk (bounce)...", "Consolidate/export tracks...", "Quit", "Undo", "Redo", "Cut", "Copy", "Time selection", "Dynamic split items", "Tempo envelope", "Track panels", "Floating mixer master", "Monitoring FX", "Transport (play/record/stop...)", "Track wiring", "Project Media/FX Bay", "Show/hide all floating windows", "Cascade all floating windows", "Time unit for ruler", "Zoom in", "Zoom out", "Go to edit cursor", "Go to play cursor", "Move cursor forward one measure", "Always on top", "Media file...", "New MIDI item", "SMPTE LTC/MTC timecode generator", "New subproject", "Marker (prompt for name)", "Region (from time selection)", "Measure from time selection (detect tempo)", "Virtual instrument on new track...", "Track from template", "Empty space in time selection", "Copy loop of selected area of items", "Nudge/set items...", "Take channel mode: Reverse stereo", "Take channel mode: Mono (downmix)", "Peaks", "Build peaks", "Rebuild peaks for selected items", "Set all media online", "Set selected media offline", "Envelope points", "Overlapping recording behavior", "Add lanes (new lanes play exclusively)", "Split existing items and add takes (default)", "Ripple edit settings", "Ripple per-track affects each track separately", "Fixed item lanes", "Track Manager...", "Lane Manager...", "Set point value...", "Delete point", "Select envelope", "Slow start/end", "Fast start", "Fast end", "Automation items", "Create pooled duplicate", "Remove from pool", "Glue", "Automation item options for this envelope", "Global automation override", "Trim/Read", "Touch (record fader movements to armed envelopes)", "Latch", "Write (record fader positions to armed envelopes)", "Record mode: time selection auto-punch", "Close all projects but current", "Exclusive solo", "Unsolo all", "Delete lane (including media items)", "Duplicate items to new lane"
]
for s in samples:
    q=urllib.parse.urlencode({'client':'gtx','sl':'en','tl':'it','dt':'t','q':s})
    req=urllib.request.Request('https://translate.googleapis.com/translate_a/single?'+q, headers={'User-Agent':'Mozilla/5.0'})
    try:
        with urllib.request.urlopen(req, timeout=20) as r: data=json.load(r)
        out=''.join(x[0] for x in data[0] if x and x[0])
    except Exception as e: out='ERROR '+repr(e)
    print(f'{s}\t=>\t{out}', flush=True)
    time.sleep(.05)
