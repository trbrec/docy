'use strict';
const assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
const source=fs.readFileSync('assets/js/trb-demo-evaluation.js','utf8');
const helper=source.slice(0,source.indexOf("document.addEventListener('DOMContentLoaded'"));
const requests=[];let interrupt=true;
class FormData { constructor(form){this.data=form&&form.data?{...form.data}:{ };} append(key,value){this.data[key]=value;} delete(key){delete this.data[key];} }
class XHR {
  constructor(){this.events={};this.upload={addEventListener(){}};}
  open(){} addEventListener(name,callback){this.events[name]=callback;}
  send(body){
    requests.push(body.data);
    if(interrupt && requests.length===2){interrupt=false;this.events.error();return;}
    this.status=200;
    this.responseText=JSON.stringify({success:true,data:{next_chunk:Number(body.data.chunk_index)+1}});
    this.events.load();
  }
}
const context={FormData,XMLHttpRequest:XHR,crypto:{randomUUID:()=> 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'}};
vm.createContext(context);vm.runInContext(helper,context);
const size=3*1024*1024, file={name:'demo.mp3',type:'audio/mpeg',size,lastModified:123,slice:(start,end)=>({start,end})};
const input={name:'trb_demo_audio'};
const form={data:{action:'trb_portal_submit_demo',trb_demo_audio:file,trb_demo_text:'text-file',trb_demo_title:'Track'},getAttribute:()=>'/admin-post.php',querySelector:selector=>({value:selector.includes('stage_nonce')?'nonce':'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'})};
(async()=>{
  await assert.rejects(context.trbDemoStageFiles(form,[{input,file,key:'f2001'}],()=>{}),/Connessione interrotta/);
  const progress=[];
  const manifest=await context.trbDemoStageFiles(form,[{input,file,key:'f2001'}],(done,total)=>progress.push([done,total]));
  assert.equal(requests.length,4);
  assert.deepEqual(requests.map(r=>r.chunk_index),['0','1','0','1']);
  assert.equal(requests[0].upload_id,requests[3].upload_id,'same file must retain its upload identity across retries');
  assert.equal(requests[0].trb_release_chunk.end,2*1024*1024,'requests are bounded to 2 MB');
  assert.equal(requests[1].trb_release_chunk.end,size);
  assert.equal(JSON.stringify(manifest),JSON.stringify({trb_demo_audio:{key:'f2001',upload_id:input._trbDemoUploadId,session:'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'}}));
  assert.equal(progress.at(-1)[0],size);
  const final=context.trbDemoFinalData(form,manifest).data;
  assert.equal(final.trb_demo_audio,undefined);
  assert.equal(final.trb_demo_text,undefined);
  assert.equal(final.trb_demo_title,'Track');
  assert.equal(JSON.parse(final.trb_staged_uploads_json).trb_demo_audio.key,'f2001');
  console.log('PASS demo MP3 stages in bounded chunks and retries with the same upload identity');
})().catch(error=>{console.error(error);process.exitCode=1;});
