#!/usr/bin/env python3
import json, urllib.parse, urllib.request
samples = ["New project","Open project...","Save project as...","Quit","Undo","Time selection","Tempo envelope","Track panels","Monitoring FX","Track wiring","Go to edit cursor","New MIDI item","Marker (prompt for name)","Take channel mode: Reverse stereo","Ripple edit settings","Fixed item lanes","Lane Manager...","Automation items","Remove from pool","Glue"]
for style, text in {
  'lines': '\n'.join(f'ZXQ{i:04d}QXZ {s}' for i,s in enumerate(samples)),
  'xml': ''.join(f'<x id="{i}">{s}</x>' for i,s in enumerate(samples)),
  'pipes': ' ZXSEP '.join(samples),
}.items():
    data=urllib.parse.urlencode({'client':'gtx','sl':'en','tl':'it','dt':'t','q':text}).encode()
    req=urllib.request.Request('https://translate.googleapis.com/translate_a/single',data=data,headers={'User-Agent':'Mozilla/5.0','Content-Type':'application/x-www-form-urlencoded'})
    with urllib.request.urlopen(req,timeout=60) as r: response=json.load(r)
    out=''.join(x[0] for x in response[0] if x and x[0])
    print('\n### '+style+'\n'+out,flush=True)
