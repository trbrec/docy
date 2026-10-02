'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const markup=fs.readFileSync('inc/trb-candidate-onboarding-page.php','utf8');
assert.match(markup,/connect-src 'self' https:\/\/www\.googleapis\.com;/,'Drive uploads allowed by the page CSP');
assert.doesNotMatch(markup.match(/<footer>[\s\S]*?<\/footer>/)[0],/accedi|segnalazione|versamenti/i,'candidate footer contains only relevant support');
assert.match(markup,/<form id="details-form">[\s\S]*?id="upload-list"[\s\S]*?id="save-and-continue"[\s\S]*?<\/form>/,'one form contains data and documents');
const nodes=new Map(),all=[];
class El{
 constructor(tag){this.tag=tag;this.children=[];this.events={};this.value='';this.files=[];this.dataset={};this.hidden=false;all.push(this);}
 set id(v){this._id=v;nodes.set(v,this);}get id(){return this._id;}
 append(...els){this.children.push(...els);}replaceChildren(...els){this.children=[...els];}
 addEventListener(type,fn){this.events[type]=fn;}setAttribute(k,v){this[k]=v;}removeAttribute(k){delete this[k];}scrollIntoView(){}
 classList={toggle(){}};
}
for(const [,id] of markup.matchAll(/id="([^"]+)"/g)){const n=new El('div');n.id=id;}
const fields={};for(const name of ['address_1','city','postcode','country','state','phone','tax_code','vat_number','sdi_code','pec'])fields[name]=new El('input');
const values={address_1:'Via QA 1',city:'Roma',postcode:'00100',country:'IT',state:'RM',phone:'+393330000000',tax_code:'RSSMRA90A01H501W',vat_number:'',sdi_code:'',pec:'',privacy_acknowledged:'on'};
Object.entries(values).forEach(([k,v])=>{if(fields[k])fields[k].value=v;});
nodes.get('details-form').elements={namedItem:k=>fields[k]};
let practice={id:'qa-practice',state:'invited',group_code:'TRB',contract_number:'QA-NONVALIDO',first_name:'Mario',last_name:'Rossi',files:{},details:{},plans:{A:{kind:'free',label:'Nessun versamento',amounts_cents:[],total_cents:0}},installments:[]};
let failTax=true,identityRelease=null,delayIdentity=false;const calls=[],puts=[],progressValues=[];
class XHR{constructor(){this.upload={addEventListener:(type,fn)=>this.progress=fn};}open(method,url){this.method=method;this.url=url;}setRequestHeader(){}send(file){assert.equal(this.method,'PUT');assert.equal(this.withCredentials,false);puts.push(file);setImmediate(()=>{this.progress({lengthComputable:true,loaded:file.size/2,total:file.size});progressValues.push(nodes.get('upload-progress-bar').value);assert.equal(nodes.get('upload-progress').hidden,false);assert.match(nodes.get('upload-progress-label').textContent,/Caricamento in corso/);this.status=200;this.onload();});}}
const ctx={XMLHttpRequest:XHR,console,URL,URLSearchParams,Intl,Date,setTimeout,window:{addEventListener(){},TRBOnboarding:{enabled:true,account:false,endpoint:'https://artist.trbrec.com/api',csrf:'qa'}},location:{hash:'',pathname:'/adesione/',search:''},history:{replaceState(){}},document:{getElementById:id=>nodes.get(id),createElement:tag=>new El(tag),createTextNode:text=>({text}),querySelectorAll:()=>[]},FormData:class{constructor(){this.values={...values,...Object.fromEntries(Object.entries(fields).map(([k,v])=>[k,v.value]))};}[Symbol.iterator](){return Object.entries(this.values)[Symbol.iterator]();}},fetch:async(url,opts)=>{
 if(url.startsWith('https://www.googleapis.com/')){puts.push(opts.body);return {ok:true};}
 const data=JSON.parse(opts.body);calls.push(data);
 if(data.action==='details')practice={...practice,details:{billing:data.billing,tax_code:data.tax_code,privacy_acknowledged_at:'today'}};
 if(data.action==='upload')return {ok:true,json:async()=>({provider:'google_drive',upload_endpoint:'https://www.googleapis.com/upload/drive/v3/files/file123?upload_id=qa'})};
 if(data.action==='uploaded'){
  if(data.slot==='tax_front'&&failTax){failTax=false;return {ok:false,json:async()=>({error:'synthetic lost archive response'})};}
  practice={...practice,files:{...practice.files,[data.slot]:{name:data.slot+'.jpg'}}};
 }
 if(data.action==='identity'){if(delayIdentity)await new Promise(resolve=>identityRelease=resolve);practice={...practice,state:'owner_review',selected_plan:practice.plans.A};return {ok:true,json:async()=>({practice,identity:{status:'matched'}})};}
 return {ok:true,json:async()=>JSON.parse(JSON.stringify(practice))};
}};
const settle=async()=>{for(let i=0;i<20;i++)await new Promise(r=>setImmediate(r));};
(async()=>{
 vm.createContext(ctx);vm.runInContext(fs.readFileSync('assets/js/onboarding/onboarding.js','utf8'),ctx);await settle();
 assert.equal(nodes.get('details-step').hidden,false,'form available before details have been saved');
 assert.ok(nodes.has('document-identity_front')&&nodes.has('document-tax_front'));assert.equal(nodes.has('document-identity_back'),false);assert.equal(nodes.has('document-tax_back'),false);
 assert.equal(nodes.get('plan-schedule-note').hidden,true,'free contract does not explain installment rules');
 const idFile={name:"Carta d'identità (fronte).jpg",size:100},taxFile={name:'Tessera sanitaria (fronte).jpg',size:101};
 nodes.get('document-identity_front').files=[idFile];nodes.get('document-tax_front').files=[taxFile];
 fields.address_1.value='Via modificata 2';
 const submit=()=>nodes.get('details-form').events.submit({preventDefault(){},target:nodes.get('details-form')});
 submit();await settle();
 assert.equal(calls.some(c=>c.action==='identity'),false,'failed upload cannot start OCR');
 assert.equal(fields.address_1.value,'Via modificata 2','saved and unsaved values survive rendering');
 assert.equal(nodes.get('document-tax_front').files[0],taxFile,'selected files survive failed upload');
 assert.match(nodes.get('status-identity_front').textContent,/confermato/);
 delayIdentity=true;submit();await settle();
 assert.equal(nodes.get('upload-progress-bar').value,undefined,'verification stays indeterminate instead of presenting a false 100% completion');assert.match(nodes.get('upload-progress-label').textContent,/Verifica/);
 identityRelease();await settle();
 assert.equal(calls.filter(c=>c.action==='upload'&&c.slot==='identity_front').length,1,'confirmed identity file is not uploaded twice after tax failure');
 assert.equal(calls.filter(c=>c.action==='identity').length,1);
 assert.equal(calls.filter(c=>c.action==='upload').every(c=>['identity_front','tax_front'].includes(c.slot)),true);
 assert.equal(nodes.get('details-step').hidden,true);assert.equal(nodes.get('plans-step').hidden,true,'TRB skips the commercial choice');assert.equal(nodes.get('register-step').hidden,true,'registration remains after both signatures and archive');
 assert.ok(progressValues.every(v=>v>0&&v<100),'actual upload events drive the combined byte progress');assert.equal(nodes.get('upload-progress-bar').value,100);assert.doesNotMatch(markup,/OpenAI|automatizz/);
 practice={...practice,id:'qa-paid',group_code:'DDB',state:'identity_matched',selected_plan:null,plans:{C:{label:'Opzione C',kind:'single',amounts_cents:[54000],total_cents:54000,discount_basis_points:1000},B:{label:'Opzione B',kind:'two_installments',amounts_cents:[28500,28500],total_cents:57000,discount_basis_points:500},A:{label:'Opzione A',kind:'monthly',amounts_cents:[15000,15000,15000,15000],total_cents:60000,discount_basis_points:0}}};
 nodes.get('refresh').events.click();await settle();assert.equal(nodes.get('plans-step').hidden,false);assert.equal(nodes.get('plan-list').children.length,3);const texts=nodes.get('plan-list').children.map(p=>p.children[2].textContent);assert.match(texts[0],/10%/);assert.match(texts[1],/5%/);assert.match(texts[2],/senza riduzioni/);
 console.log('Unified form, two fronts, CSP, free-plan copy, saved input preservation, partial-upload recovery and activation gates verified.');
})().catch(e=>{console.error(e);process.exit(1);});
