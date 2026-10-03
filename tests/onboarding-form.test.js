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
const fields={};for(const name of ['street','street_number','city','postcode','country','state','phone','tax_code','vat_number','sdi_code','pec'])fields[name]=new El('input');
const values={street:'Via QA',street_number:'1',city:'Roma',postcode:'00100',country:'IT',state:'RM',phone:'+393330000000',tax_code:'RSSMRA90A01H501W',vat_number:'',sdi_code:'',pec:'',privacy_acknowledged:'on'};
Object.entries(values).forEach(([k,v])=>{if(fields[k])fields[k].value=v;});
nodes.get('details-form').elements={namedItem:k=>fields[k]};
const proposalAck=new El('input');nodes.get('plans-form').elements={namedItem:k=>k==='proposal_read'?proposalAck:null};
let practice={id:'qa-practice',state:'invited',group_code:'TRB',contract_number:'QA-NONVALIDO',first_name:'Mario',last_name:'Rossi',files:{},details:{},plans:{A:{kind:'free',label:'Nessun versamento',amounts_cents:[],total_cents:0}},installments:[]};
let failTax=true,identityRelease=null,delayIdentity=false,delayRefresh=false,refreshRelease=null,identityMode='legacy',networkDown=false,failAction=null,sessionFault=null,timeoutRequest=false;const calls=[],puts=[],progressValues=[];
class XHR{constructor(){this.upload={addEventListener:(type,fn)=>this.progress=fn};}open(method,url){this.method=method;this.url=url;}setRequestHeader(){}send(file){assert.equal(fields.street.disabled,true,'entered data are locked while their captured values are uploaded');assert.equal(nodes.get('save-and-continue').disabled,true,'rendered Save remains disabled throughout uploads');assert.equal(this.method,'PUT');assert.equal(this.withCredentials,false);puts.push(file);setImmediate(()=>{this.progress({lengthComputable:true,loaded:file.size/2,total:file.size});progressValues.push(nodes.get('upload-progress-bar').value);assert.equal(nodes.get('upload-progress').hidden,false);assert.match(nodes.get('upload-progress-label').textContent,/Caricamento in corso/);this.status=200;this.onload();});}}
const windowEvents={},documentEvents={};let time=Date.now();const TestDate=class extends Date{static now(){return time;}};
const ctx={XMLHttpRequest:XHR,AbortController,clearTimeout,console,URL,URLSearchParams,Intl,Date:TestDate,setTimeout:(fn,ms)=>timeoutRequest&&ms===30000?setImmediate(fn):setTimeout(fn,ms),setInterval:fn=>{ctx.poll=async()=>{time+=45000;return fn();};},navigator:{onLine:true},window:{addEventListener:(name,fn)=>windowEvents[name]=fn,TRBOnboarding:{enabled:true,account:false,endpoint:'https://artist.trbrec.com/api',csrf:'qa'}},location:{hash:'',pathname:'/adesione/',search:''},history:{replaceState(){}},document:{addEventListener:(name,fn)=>documentEvents[name]=fn,getElementById:id=>nodes.get(id),createElement:tag=>new El(tag),createTextNode:text=>({text}),querySelector:()=>nodes.get('plan-list').children.map(label=>label.children[0]).find(input=>input.checked),querySelectorAll:selector=>selector.startsWith('#details-form')?Object.values(fields):all.filter(el=>el.tag==='button'||el.type==='file'||el.id==='save-and-continue')},FormData:class{constructor(){this.values={...values,...Object.fromEntries(Object.entries(fields).map(([k,v])=>[k,v.value]))};}[Symbol.iterator](){return Object.entries(this.values)[Symbol.iterator]();}},fetch:async(url,opts)=>{
 if(url.startsWith('https://www.googleapis.com/')){puts.push(opts.body);return {ok:true};}
 const data=JSON.parse(opts.body);calls.push(data);if(sessionFault===data.action){sessionFault=null;return {ok:false,status:401,json:async()=>({code:'onboarding_session',message:'Sessione scaduta'})};}if(networkDown||failAction===data.action){failAction=null;throw new TypeError('Failed to fetch');}if(data.action==='refresh'&&delayRefresh)await new Promise(resolve=>refreshRelease=resolve);
 if(data.action==='details')practice={...practice,details:{billing:data.billing,tax_code:data.tax_code,privacy_acknowledged_at:'today'}};
 if(data.action==='upload')return {ok:true,json:async()=>({provider:'google_drive',upload_endpoint:'https://www.googleapis.com/upload/drive/v3/files/file123?upload_id=qa'})};
 if(data.action==='uploaded'){
  if(data.slot==='tax_front'&&failTax){failTax=false;return {ok:false,json:async()=>({error:'synthetic lost archive response'})};}
  practice={...practice,identity_verification:null,files:{...practice.files,[data.slot]:{name:data.slot+'.jpg'}}};
 }
 if(data.action==='identity'){
  if(delayIdentity)await new Promise(resolve=>identityRelease=resolve);
  if(identityMode!=='legacy'){
   practice={...practice,identity_verification:{status:'queued',message:'I documenti sono salvati. La verifica può richiedere qualche minuto. Non devi ripetere l’invio.',replace_slots:[]}};
   if(identityMode==='queued-lost')throw new TypeError('Failed to fetch');
   if(identityMode==='timeout'){timeoutRequest=false;return new Promise((resolve,reject)=>opts.signal.addEventListener('abort',()=>reject(new Error('AbortError'))));}
   return {ok:true,json:async()=>({practice,identity:{status:'pending'}})};
  }
  practice={...practice,state:'owner_review',selected_plan:practice.plans.A};return {ok:true,json:async()=>({practice,identity:{status:'matched'}})};
 }
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
 fields.street.value='Via modificata';fields.street_number.value='11/A';
 const submit=()=>nodes.get('details-form').events.submit({preventDefault(){},target:nodes.get('details-form')});
 submit();await settle();
 assert.equal(calls.some(c=>c.action==='identity'),false,'failed upload cannot start OCR');
 assert.equal(fields.street.value,'Via modificata','saved and unsaved values survive rendering');
 assert.equal(nodes.get('document-tax_front').files[0],taxFile,'selected files survive failed upload');
 assert.match(nodes.get('status-identity_front').textContent,/confermato/);
 delayIdentity=true;submit();await settle();
 assert.equal(nodes.get('upload-progress-bar').value,undefined,'verification stays indeterminate instead of presenting a false 100% completion');assert.match(nodes.get('upload-progress-label').textContent,/Verifica/);
 identityRelease();await settle();
 assert.equal(calls.filter(c=>c.action==='upload'&&c.slot==='identity_front').length,1,'confirmed identity file is not uploaded twice after tax failure');
 assert.equal(calls.filter(c=>c.action==='identity').length,1);
 assert.equal(calls.filter(c=>c.action==='upload').every(c=>['identity_front','tax_front'].includes(c.slot)),true);
 assert.equal(calls.find(c=>c.action==='details').billing.street_number,'11/A','civic stored separately');assert.equal(calls.find(c=>c.action==='details').billing.address_1,'Via modificata 11/A','downstream contract and payment address retains civic');assert.equal(nodes.get('refresh').hidden,true,'no technical refresh action during ordinary waiting');assert.equal(nodes.get('read-proposal').hidden,true,'waiting screen focuses on the signature');assert.match(nodes.get('next-step-help').textContent,/non devi fare altro/);
 assert.equal(nodes.get('details-step').hidden,true);assert.equal(nodes.get('plans-step').hidden,true,'TRB skips the commercial choice');assert.equal(nodes.get('register-step').hidden,true,'registration remains after both signatures and archive');
 assert.ok(progressValues.every(v=>v>0&&v<100),'actual upload events drive the combined byte progress');assert.equal(nodes.get('upload-progress').hidden,true,'upload progress leaves the screen after verification');assert.doesNotMatch(markup,/OpenAI|automatizz/);
 // The real incident: queued verification followed by a lost HTTP response and
 // a second Save. Recovery must observe the durable job, never request it again.
 delayIdentity=false;identityMode='queued-lost';practice={...practice,id:'qa-recovery',state:'invited',selected_plan:null,files:{},identity_verification:null};nodes.get('refresh').events.click();await settle();
 nodes.get('document-identity_front').files=[idFile];nodes.get('document-tax_front').files=[taxFile];
 const beforeQueued=calls.filter(c=>c.action==='identity').length;submit();await settle();
 assert.equal(calls.filter(c=>c.action==='identity').length,beforeQueued+1);assert.equal(calls.at(-1).action,'view','lost queue response reconciles through a read-only endpoint');
 assert.equal(nodes.get('save-and-continue').disabled,true);assert.equal(nodes.get('document-tax_front').disabled,true,'rendered file inputs stay locked while verification is queued');assert.equal(nodes.get('details-form')['aria-busy'],'true');assert.equal(nodes.get('upload-progress-bar').value,undefined);assert.match(nodes.get('upload-progress-value').textContent,/documenti.*salvati/i);assert.doesNotMatch(nodes.get('notice').textContent,/Failed to fetch/);
 submit();await settle();assert.equal(calls.filter(c=>c.action==='identity').length,beforeQueued+1,'a repeated Submit cannot queue another check');
 const beforePoll=calls.length;await ctx.poll();assert.equal(calls.at(-1).action,'view');assert.equal(calls.length,beforePoll+1,'verification poll performs one read without external refresh work');
 networkDown=true;ctx.navigator.onLine=false;await ctx.poll();assert.match(nodes.get('upload-progress-value').textContent,/connessione internet.*assente/i);assert.equal(nodes.get('save-and-continue').disabled,true,'temporary offline state does not unlock duplicate verification');assert.equal(fields.street.value,'Via modificata');assert.equal(nodes.get('document-tax_front').files[0],taxFile);
 networkDown=false;ctx.navigator.onLine=true;await windowEvents.online();assert.equal(calls.at(-1).action,'view','reconnection resumes status observation without resubmitting');
 practice={...practice,state:'identity_review',identity_verification:{status:'rejected',reason:'tax_document_unreadable',message:'Non riusciamo a leggere la tessera sanitaria o il tesserino del codice fiscale. Sostituisci il file con una foto completa e leggibile del fronte.',replace_slots:['tax_front']}};await ctx.poll();
 assert.equal(nodes.get('save-and-continue').disabled,false,'rejected documents allow correction');assert.equal(nodes.get('document-tax_front').disabled,false);assert.match(nodes.get('status-tax_front').textContent,/da sostituire/);assert.match(nodes.get('status-identity_front').textContent,/confermato/,'the other valid front stays confirmed');assert.match(nodes.get('next-step-help').textContent,/Sostituisci/);assert.equal(nodes.get('plans-step').hidden,true,'rejected screenshots cannot open payment selection');
 const replacedTax={name:'Tessera corretta.jpg',size:102};nodes.get('document-tax_front').files=[replacedTax];nodes.get('document-tax_front').events.change();assert.match(nodes.get('status-tax_front').textContent,/Nuovo file/);assert.equal(nodes.get('upload-progress').hidden,true,'selecting a replacement clears the stale operation bar');
 identityMode='queued';const identityUploads=calls.filter(c=>c.action==='upload'&&c.slot==='identity_front').length;submit();await settle();assert.equal(calls.filter(c=>c.action==='upload'&&c.slot==='identity_front').length,identityUploads,'replacing tax front does not upload identity again');assert.equal(calls.filter(c=>c.action==='upload'&&c.slot==='tax_front').at(-1).file.size,102);
 practice={...practice,identity_verification:{status:'retry_wait',message:'Il servizio di verifica è temporaneamente occupato. I documenti sono salvati: riprenderemo la verifica senza un nuovo invio.',replace_slots:[]}};await ctx.poll();assert.equal(nodes.get('save-and-continue').disabled,true);assert.match(nodes.get('upload-progress-label').textContent,/ripresa/);
 practice={...practice,identity_verification:{status:'error',retryable:true,message:'La verifica non è stata completata. I documenti restano salvati. Premi «Salva e continua» per riprovare.',replace_slots:[]}};await ctx.poll();assert.equal(nodes.get('save-and-continue').disabled,false);submit();await settle();assert.equal(calls.filter(c=>c.action==='identity').at(-1).retry,true,'only the explicit retry action asks for another provider attempt');
 practice={...practice,identity_verification:{status:'error',retryable:true,message:'La verifica non è stata completata. Riprova.',replace_slots:[]}};await ctx.poll();identityMode='timeout';timeoutRequest=true;submit();await settle();assert.equal(nodes.get('save-and-continue').disabled,true,'a timeout reconciles the existing queue instead of unlocking a new request');assert.equal(calls.at(-1).action,'view');assert.doesNotMatch(nodes.get('notice').textContent,/AbortError|Failed to fetch/);
 practice={...practice,state:'owner_review',identity_verification:{status:'complete'}};await ctx.poll();assert.equal(nodes.get('details-step').hidden,true,'confirmed verification advances only when the durable state changes');
 practice={...practice,state:'identity_review',identity_verification:{status:'error',retryable:true,message:'La verifica deve essere ripresa.',replace_slots:[]}};await ctx.poll();sessionFault='details';const savedTax=nodes.get('document-tax_front').files[0];submit();await settle();
 assert.equal(nodes.get('email-step').hidden,false,'an expired session offers email reconfirmation');assert.equal(nodes.get('save-and-continue').disabled,true);assert.match(nodes.get('notice').textContent,/non devi ricominciare/);assert.equal(nodes.get('document-tax_front').files[0],savedTax);assert.equal(fields.street.value,'Via modificata');nodes.get('send-code').events.click();await settle();assert.equal(calls.filter(c=>c.action==='challenge').at(-1).token,undefined,'the stored invitation is reused without an empty token');
 nodes.get('email-form').elements={code:{value:'123456'}};nodes.get('email-form').events.submit({preventDefault(){},target:nodes.get('email-form')});await settle();assert.equal(nodes.get('save-and-continue').disabled,false);assert.equal(nodes.get('document-tax_front').files[0],savedTax,'email reconfirmation preserves selected documents');assert.equal(fields.street.value,'Via modificata');
 practice={...practice,id:'qa-paid',group_code:'DDB',state:'identity_matched',selected_plan:null,identity_verification:null,plans:{C:{label:'Opzione C',kind:'single',amounts_cents:[54000],total_cents:54000,discount_basis_points:1000},B:{label:'Opzione B',kind:'two_installments',amounts_cents:[28500,28500],total_cents:57000,discount_basis_points:500},A:{label:'Opzione A',kind:'monthly',amounts_cents:[15000,15000,15000,15000],total_cents:60000,discount_basis_points:0}}};
 nodes.get('refresh').events.click();await settle();assert.equal(nodes.get('plans-step').hidden,false);assert.equal(nodes.get('plan-list').children.length,3);const texts=nodes.get('plan-list').children.map(p=>p.children[2].textContent);assert.match(texts[0],/10%/);assert.match(texts[1],/5%/);assert.match(texts[2],/senza riduzioni/);nodes.get('plan-list').children[1].children[0].checked=true;proposalAck.checked=true;nodes.get('refresh').events.click();await settle();assert.equal(nodes.get('plan-list').children[1].children[0].checked,true,'an unconfirmed formula survives status rendering');assert.equal(proposalAck.checked,true,'the proposal acknowledgement survives status rendering');
 practice={...practice,state:'payment_pending',selected_plan:practice.plans.B,installments:[]};nodes.get('refresh').events.click();await settle();
 assert.equal(nodes.get('plans-step').hidden,true,'checkout follows a confirmed formula');assert.match(nodes.get('selected-plan-summary').textContent,/Opzione B/);assert.match(nodes.get('selected-plan-summary').textContent,/2 versamenti/);
 const firstTable=nodes.get('installments').children[0];assert.deepEqual(firstTable.children[0].children[0].children.map(c=>c.textContent),['Quota','Scadenza','Importo','Confermato','Da versare']);assert.equal(firstTable.children[1].children.length,1,'first quota is visible before its confirmation');
 practice={...practice,installments:[{number:1,due_date:'2026-10-02',amount_cents:28500,confirmed_cents:28500},{number:2,due_date:'2026-11-02',amount_cents:28500,confirmed_cents:0}]};nodes.get('refresh').events.click();await settle();
 const history=nodes.get('installments').children[0].children[1].children;const euro=cents=>new Intl.NumberFormat('it-IT',{style:'currency',currency:'EUR'}).format(cents/100);
 assert.equal(history[0].children[3].textContent,euro(28500),'previously confirmed amount stays visible');assert.equal(history[0].children[4].textContent,euro(0),'paid quota has no residual');assert.equal(history[1].children[4].textContent,euro(28500),'next quota retains its exact balance');
 delayRefresh=true;const background=ctx.poll();await settle();nodes.get('checkout').events.click();await settle();assert.equal(calls.some(c=>c.action==='checkout'),true,'background polling cannot swallow a payment click');delayRefresh=false;refreshRelease();await background;
 practice={...practice,state:'signature_pending',signature_email:null};await ctx.poll();assert.equal(nodes.get('signature-email').hidden,true,'uncertain dispatch does not imply an email was sent');assert.match(nodes.get('next-step-title').textContent,/Attendi la conferma/);practice={...practice,signature_email:{sender:'OTP service <please-do-not-reply@otpservice.io>',subject:'Il documento 98765 da firmare per TRB rec di Andrea Tognassi - Music Publishing'}};await ctx.poll();assert.match(nodes.get('next-step-title').textContent,/Firma il contratto/);assert.equal(nodes.get('signature-email').hidden,false);assert.equal(nodes.get('signature-subject').textContent,practice.signature_email.subject);assert.equal(nodes.get('signature-recipient').textContent,practice.email||'');assert.equal(nodes.get('register-step').hidden,true,'signature in progress never exposes account creation');practice={...practice,state:'activation_ready'};await ctx.poll();assert.equal(nodes.get('register-step').hidden,false,'signed/archive transition opens account creation automatically');
 assert.equal(nodes.get('practice-summary').hidden,true,'signed welcome hides the old proposal and technical buttons');assert.equal(nodes.get('signed-contract').hidden,true);assert.equal(nodes.get('signature-audit').hidden,true);assert.equal(nodes.get('page-title').textContent,'Benvenuto in Digital Distribution Bundle');
 practice={...practice,group_code:'TRB'};nodes.get('refresh').events.click();await settle();assert.equal(nodes.get('page-title').textContent,'Benvenuto in TRB rec');
 practice={...practice,state:'active'};nodes.get('refresh').events.click();await settle();assert.equal(nodes.get('register-step').hidden,true);assert.equal(nodes.get('ready-access').hidden,false,'already active account opens login instead of another password setup');assert.equal(nodes.get('practice-summary').hidden,true);
 console.log('Unified form, lost-response/timeout/offline verification recovery, rejected document replacement, formula selection and confirmed installment history verified.');
})().catch(e=>{console.error(e);process.exit(1);});
