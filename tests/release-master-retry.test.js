const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('assets/js/trb-release-upload.js','utf8');
const requests=[];
class FormData {constructor(){this.values={};}append(k,v){this.values[k]=v;}}
class XHR {constructor(){this.events={};this.upload={addEventListener(){}};}open(){}addEventListener(n,f){this.events[n]=f;}send(data){requests.push(data.values);this.status=requests.length===1?422:200;this.responseText=JSON.stringify(this.status===422?{success:false,data:{message:'Master rifiutato'}}:{success:true});this.events.load();}}
const context={crypto:require('node:crypto'),module:{exports:{}},FormData,XMLHttpRequest:XHR,console};
vm.runInNewContext(source.replace('module.exports={draftShape:', 'module.exports={stageFiles:stageFiles,draftShape:'),context);
let selected='mastered';
const file={name:'same.wav',size:100,lastModified:1,type:'audio/wav',slice(){return 'bytes';}};
const input={name:'trb_track_audio[0]',files:[file]};
const form={dataset:{},action:'/upload',querySelectorAll(){return [input];},querySelector(s){if(s.includes('submission_token'))return {value:'session'};if(s.includes('stage_nonce'))return {value:'nonce'};if(s.endsWith(':checked'))return {value:selected};if(s.includes('type="hidden"'))return null;return {value:'mastered'};}};
(async()=>{
 await assert.rejects(context.module.exports.stageFiles(form,()=>{}),/Master rifiutato/);
 selected='mastering';
 await context.module.exports.stageFiles(form,()=>{});
 assert.equal(requests[0].audio_status,'mastered');assert.equal(requests[1].audio_status,'mastering');
 assert.equal(requests[1].chunk_index,'0');
 await context.module.exports.stageFiles(form,()=>{});assert.equal(requests[1].upload_id,requests[2].upload_id);
 input.files=[{...file}];await context.module.exports.stageFiles(form,()=>{});assert.notEqual(requests[2].upload_id,requests[3].upload_id);
 console.log('PASS rejected upload retries from first chunk and uses selected pre-master status');
})().catch(e=>{console.error(e);process.exit(1);});
