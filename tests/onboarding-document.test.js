'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),crypto=require('node:crypto');
const source=fs.readFileSync('integrations/onboarding/apps-script/CRM_Onboarding.gs','utf8');
const sha=x=>crypto.createHash('sha256').update(x).digest('hex');
for(const phase of ['proposal','final']){
  const receipts=new Map();let copies=0,uploads=0,pdfText='';
  const template='@colB@ @colC@ @colD@ @colE@ @colF@\n@onboarding_plan@\n#otp_firma_artista_1# #otp_firma_artista_2# #otp_firma_trb_1# #otp_firma_trb_2#';
  let text=template;
  const body={getText:()=>text,replaceText:(key,value)=>{text=text.split(key).join(value);},findText:()=>null,appendPageBreak(){},appendParagraph(value){text+='\n'+value;return {setHeading(){}};},appendTable(rows){text+='\n'+rows.map(r=>r.join(' ')).join('\n');}};
  const blob={getBytes:()=>[...Buffer.from('%PDF-'+pdfText)],getName:()=> 'fixture.pdf'};
  const document={getBody:()=>body,getHeader:()=>null,getFooter:()=>null,saveAndClose(){}};
  const original={getBody:()=>({getText:()=>template}),getHeader:()=>null,getFooter:()=>null};
  const ctx={console,Utilities:{DigestAlgorithm:{SHA_256:'sha256'},computeDigest:(type,data)=>[...crypto.createHash('sha256').update(typeof data==='string'?data:Buffer.from(data)).digest()],formatDate:()=> '01/10/2026'},PropertiesService:{getScriptProperties:()=>({getProperty:k=>receipts.get(k)||null,setProperty:(k,v)=>receipts.set(k,v)})},LockService:{getScriptLock:()=>({waitLock(){},releaseLock(){}})},DocumentApp:{ParagraphHeading:{HEADING1:1,HEADING2:2},openById:id=>id==='copy'?document:original},DriveApp:{getFileById:id=>id==='pdf'?{getBlob:()=>blob,getId:()=> 'pdf'}:{makeCopy(){copies++;return {getId:()=> 'copy',getAs(){pdfText=text;return {setName:()=>blob};}};}},createFile:()=>({getId:()=> 'pdf',getBlob:()=>blob})}};
  vm.createContext(ctx);vm.runInContext(source,ctx);ctx.TRBONB_upload_=()=>{uploads++;};
  const input={practice_id:'a'.repeat(32),phase,invite_url:'https://artist.trbrec.com/adesione/#invite='+'b'.repeat(64),snapshot:{template_key:'ddb_ccad_600',template_document_id:ctx.TRBONB_MODELS.ddb_ccad_600,source_sha256:sha(template),contract_number:'TEST-1234',artist_name:'Artista QA',first_name:'Mario',last_name:'Rossi'},details:{billing:{address_1:'Via QA',city:'Roma'},tax_code:'QA'},appendix:{practice_id:'a'.repeat(32),sha256:'c'.repeat(64),plan:{kind:'single',label:'Opzione C – unica soluzione'},first_payment_date:'2026-10-01',schedule:[{number:1,due_date:'2026-10-01',amount_cents:54000}]}};
  const first=ctx.TRBONB_document_(input),retry=ctx.TRBONB_document_(input);
  assert.equal(copies,1,'retry reuses the same Drive document');assert.equal(first.sha256,retry.sha256);
  assert.doesNotMatch(pdfText,/@col[A-Za-z]+@|@onboarding_plan@/);
  if(phase==='final'){assert.match(pdfText,/Opzione C – unica soluzione/);assert.match(pdfText,/540,00 €/);assert.match(pdfText,/2026-10-01/);}
  else assert.match(pdfText,/Da selezionare nel portale/);
  assert.throws(()=>ctx.TRBONB_document_({...input,details:{tax_code:'changed'}}));assert.equal(copies,1);
}
console.log('Proposal and final PDF merge, chosen formula, exact schedule and document retry verified.');
