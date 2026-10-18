'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const php=fs.readFileSync('inc/trb-artist-portal.php','utf8');
const source=php.slice(php.indexOf('function updateLyrics(track)'),php.indexOf('\n',php.indexOf('function updateLyrics(track)')));
const ctx={};vm.createContext(ctx);vm.runInContext(source,ctx);
function fixture(style='Vocal',advisory='non_explicit'){
 const label={hidden:false},a={value:advisory,closest:()=>label},v={value:style},file={value:'',required:false,disabled:false},w={hidden:true,querySelector:()=>file},language={value:'it',required:true,disabled:false},lw={hidden:false,querySelector:()=>language};
 return {a,v,file,w,language,lw,label,querySelector(s){return {'[data-track-advisory]':a,'[data-track-vocal]':v,'[data-track-lyrics]':w,'[data-vocal-language]':lw}[s];}};
}
let t=fixture();ctx.updateLyrics(t);assert(t.language.required&&!t.language.disabled&&!t.lw.hidden);assert(t.file.required&&!t.w.hidden);assert.equal(t.a.value,'non_explicit');
t.v.value='Instrumental';ctx.updateLyrics(t);assert(t.language.disabled&&!t.language.required&&t.lw.hidden);assert.equal(t.language.value,'');assert.equal(t.a.value,'no_lyrics');assert(t.file.disabled&&!t.file.required&&t.w.hidden);
t.v.value='Vocal';ctx.updateLyrics(t);assert(!t.language.disabled&&t.language.required&&!t.lw.hidden);assert.equal(t.language.value,'');assert.equal(t.a.value,'');assert(t.file.required);
t.file._trbRetained={sha256:'synthetic'};ctx.updateLyrics(t);assert.equal(t.file.required,false);
// Real draft restore path: preserve declared style/language, derive style only for a historical draft.
const upload=fs.readFileSync('assets/js/trb-release-upload.js','utf8');
function restore(pairs){
 const fields={'trb_tracks[0][audio_style]':{name:'trb_tracks[0][audio_style]',value:'Vocal',type:'select'},'trb_tracks[0][advisory]':{name:'trb_tracks[0][advisory]',value:'',type:'select'},'trb_tracks[0][vocal_language]':{name:'trb_tracks[0][vocal_language]',value:'',type:'select'},'trb_tracks[0][recording_year]':{name:'trb_tracks[0][recording_year]',value:'',type:'number'},'trb_tracks[0][recording_country]':{name:'trb_tracks[0][recording_country]',value:'',type:'select'},'trb_release_metadata_language':{name:'trb_release_metadata_language',value:'',type:'select'}};
 const track={querySelector(s){return s==='[data-track-vocal]'?fields['trb_tracks[0][audio_style]']:s==='[data-track-advisory]'?fields['trb_tracks[0][advisory]']:null;}};
 fields['trb_tracks[0][advisory]'].dispatchEvent=()=>{if(fields['trb_tracks[0][audio_style]'].value==='Instrumental')fields['trb_tracks[0][vocal_language]'].value='';};
 const form={dataset:{draftKey:'synthetic'},querySelector:()=>null,querySelectorAll(s){if(s==='[name]')return Object.values(fields);if(s==='[data-track]')return[track];if(s==='[data-track-advisory]')return[fields['trb_tracks[0][advisory]']];return[];},insertBefore(){}};
 const context={module:{exports:{}},console,Event:function(){},localStorage:{getItem:k=>k==='synthetic'?JSON.stringify({version:1,pairs}):null,setItem(){}},document:{addEventListener(){},querySelector(){return null;},createElement(){return{};}}};
 vm.runInNewContext(upload.replace('module.exports={draftShape:','module.exports={restoreDraft,draftShape:'),context);assert(context.module.exports.restoreDraft(form));return fields;
}
let f=restore([['trb_tracks[0][advisory]','no_lyrics'],['trb_tracks[0][recording_country]','IT'],['trb_tracks[0][recording_year]','2026'],['trb_release_metadata_language','it']]);assert.equal(f['trb_tracks[0][audio_style]'].value,'Instrumental');assert.equal(f['trb_tracks[0][recording_country]'].value,'IT');assert.equal(f['trb_release_metadata_language'].value,'it');
f=restore([['trb_tracks[0][audio_style]','Vocal'],['trb_tracks[0][advisory]','non_explicit'],['trb_tracks[0][vocal_language]','it']]);assert.equal(f['trb_tracks[0][vocal_language]'].value,'it');
f=restore([['trb_tracks[0][audio_style]','Instrumental'],['trb_tracks[0][advisory]','no_lyrics'],['trb_tracks[0][vocal_language]','it']]);assert.equal(f['trb_tracks[0][vocal_language]'].value,'');
assert(!php.includes('<label>Uso di IA nel brano'));
console.log('PASS Vocal/Instrumental transitions, retained lyrics, actual draft restoration and absent AI question');
