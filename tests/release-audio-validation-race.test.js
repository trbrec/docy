const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('assets/js/trb-release-upload.js','utf8');
const context={module:{exports:{}},console};
vm.runInNewContext(source.replace('module.exports={draftShape:', 'module.exports={validateAudio:validateAudio,draftShape:'),context);
function wavBuffer(){const b=new ArrayBuffer(44),v=new DataView(b);for(const [p,t] of [[0,'RIFF'],[8,'WAVE'],[12,'fmt '],[36,'data']])for(let i=0;i<t.length;i++)v.setUint8(p+i,t.charCodeAt(i));v.setUint32(16,16,true);v.setUint16(20,1,true);v.setUint32(24,44100,true);v.setUint32(28,176400,true);v.setUint16(34,16,true);v.setUint32(40,3528000,true);return b;}
let failOld;const old={size:44,slice(){return{arrayBuffer(){return new Promise((_,reject)=>{failOld=reject;});}};}},good={size:44,slice(){return{arrayBuffer(){return Promise.resolve(wavBuffer());}};}};
const input={files:[old],setCustomValidity(v){this.error=v;}},min={value:'0'},sec={value:'20'};
const track={querySelector(s){return s.includes('trb_track_audio')?input:s.includes('duration_minutes')?min:s.includes('duration_seconds')?sec:null;}};
(async()=>{const first=context.module.exports.validateAudio(track);input.files=[good];assert.equal(await context.module.exports.validateAudio(track),true);failOld(Error('unreadable old file'));await first;assert.equal(input.error,'');assert.equal(input._trbWavFile,good);input.error='stale';input.files=[];await context.module.exports.validateAudio(track);assert.equal(input.error,'');console.log('PASS old WAV response cannot overwrite replacement; empty selection clears stale custom error');})().catch(e=>{console.error(e);process.exit(1);});
