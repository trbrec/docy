const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const requests=[];let rejectNext=false;
class FormData{constructor(){this.values={};}append(k,v){this.values[k]=v;}}
class XHR{constructor(){this.events={};this.upload={addEventListener(){}};}open(){}addEventListener(n,f){this.events[n]=f;}send(data){requests.push(data.values);this.status=rejectNext?422:200;this.responseText=JSON.stringify(rejectNext?{success:false,data:{discarded:true,message:'File rifiutato'}}:{success:true});rejectNext=false;this.events.load();}}
const c={crypto:require('node:crypto'),module:{exports:{}},FormData,XMLHttpRequest:XHR};
vm.runInNewContext(fs.readFileSync('assets/js/trb-release-upload.js','utf8').replace('module.exports={draftShape:','module.exports={stageFiles,discardResponseFields,uploadSlotKey,retainedUploads,draftShape:'),c);
const api=c.module.exports;
function input(name,file){return {name,files:file?[file]:[],required:true,set value(v){if(v==='')this.files=[];},setCustomValidity(v){this.validity=v;}};}
const wav={name:'corrected.wav',size:10,lastModified:1,type:'audio/wav',slice(){return 'bytes';}};
const cover=input('trb_release_cover');cover._trbRetained={retained:'cover-hash',name:'valid.png'};
const audio=input('trb_track_audio[0]',wav),lyrics=input('trb_track_lyrics[0]');lyrics._trbRetained={session:'token',key:'f1101',name:'lyrics.txt'};
const form={dataset:{},action:'/upload',querySelectorAll(){return [cover,audio,lyrics];},querySelector(s){return {value:s.includes('submission_token')?'token':s.includes('stage_nonce')?'nonce':'mastered'};}};
(async()=>{
 rejectNext=true;await assert.rejects(api.stageFiles(form,()=>{}),/rifiutato/);
 assert.equal(audio.files.length,0);assert.equal(audio.required,true);assert.equal(cover._trbRetained.name,'valid.png');assert.equal(lyrics._trbRetained.name,'lyrics.txt');
 audio.files=[{...wav}];const map=await api.stageFiles(form,()=>{});
 assert.equal(map.trb_release_cover.retained,'cover-hash');assert.equal(map['trb_track_lyrics[0]'].key,'f1101');assert.equal(requests[1].chunk_index,'0');assert.equal(requests[1].field_name,'trb_track_audio[0]');assert.equal(requests[0].file_key,requests[1].file_key);assert.notEqual(requests[0].upload_id,requests[1].upload_id);
 assert.equal(api.uploadSlotKey(audio,0),api.uploadSlotKey(audio,9));assert.notEqual(api.uploadSlotKey(audio,0),api.uploadSlotKey(lyrics,0));
 api.discardResponseFields(form,{discarded_fields:['trb_track_lyrics[0]']});assert.equal(lyrics._trbRetained,null);assert.equal(cover._trbRetained.name,'valid.png');assert.equal(audio.files.length,1);
 const replacement=input('trb_release_replacement',{...wav});const replacementForm={...form,querySelectorAll(){return [replacement];},querySelector(s){return {value:s.includes('submission_token')?'token':s.includes('stage_nonce')?'nonce':'mastering'};}};
 await api.stageFiles(replacementForm,()=>{});assert.equal(requests.at(-1).audio_status,'mastering');
 console.log('PASS only rejected input cleared, corrected file retry, valid attachment reuse, stable staging slots and field-specific final errors');
})().catch(e=>{console.error(e);process.exit(1);});
