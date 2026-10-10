'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),crypto=require('node:crypto');
const receipts=new Map();let posts=0,fail=false,lastPayload;const pdf=Buffer.from('%PDF-synthetic-owned-fixture');
const sha=x=>crypto.createHash('sha256').update(x).digest('hex');
const ctx={console,Utilities:{DigestAlgorithm:{SHA_256:'sha256'},computeDigest:(type,data)=>[...crypto.createHash('sha256').update(typeof data==='string'?data:Buffer.from(data)).digest()],base64Encode:data=>Buffer.from(data).toString('base64')},PropertiesService:{getScriptProperties:()=>({getProperty:k=>receipts.get(k)||null,setProperty:(k,v)=>receipts.set(k,v)})},LockService:{getScriptLock:()=>({waitLock(){},releaseLock(){}})},getOtpConfig_:()=>({API_URL:'https://api.otpservice.io/api/v1',DOSSIER_TYPE:'simple_sign'}),getOtpToken:()=> 'synthetic-token',DriveApp:{getFileById:()=>({getBlob:()=>({getBytes:()=>[...pdf],getName:()=> 'synthetic.pdf'})})},UrlFetchApp:{fetch:(url,options)=>{assert.equal(url,'https://api.otpservice.io/api/v1/dossiers');posts++;const payload=lastPayload=JSON.parse(options.payload);assert.equal(payload.recipients.length,2);assert.equal(payload.unsigned_document.sign_points.length,4);assert.equal(payload.unsigned_document.sign_point_permissions.length,8);for(const point of payload.unsigned_document.sign_points){const allowed=payload.unsigned_document.sign_point_permissions.filter(p=>p.sign_point_key===point.key&&p.signature_type==='always');assert.equal(allowed.length,1);assert.equal(allowed[0].recipient_seq,point.key.includes('artista')?1:2);}if(fail)throw new Error('synthetic lost provider response');return {getResponseCode:()=>201,getContentText:()=>JSON.stringify({id:98765})};}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('integrations/onboarding/apps-script/CRM_Onboarding.gs','utf8'),ctx);
const input={practice_id:'a'.repeat(32),request_key:'b'.repeat(64),owner_approved:true,drive_pdf_id:'fixture',document_sha256:sha(pdf),snapshot:{first_name:'Mario',last_name:'Rossi',email:'test@example.invalid',contract_number:'TEST-1234'},details:{billing:{phone:'+393330000000'}}};
for(const [value,prefix,number] of [
 ['+216 20 000 000','216','20000000'],['00216 (20) 000-000','216','20000000'],
 ['+39 333 0000000','39','3330000000'],['0039 333 0000000','39','3330000000'],['3330000000','39','3330000000'],
 ['+39 02 00000000','39','0200000000'],['+1 202 555 0100','1','2025550100'],['+44 7700 900000','44','7700900000']
])assert.deepEqual(JSON.parse(JSON.stringify(ctx.TRBONB_signaturePhone_(value))),{prefix,number},'Prefix and national digits preserved: '+value);
for(const value of ['',null,[],{phone:'+21620000000'},'+99920000000','+216abc20000000','+21620000000 ext 2','++21620000000','21620000000','+391234','+2161234567890123']){
 assert.throws(()=>ctx.TRBONB_signature_({...input,details:{billing:{phone:value}}}));assert.equal(posts,0,'Malformed phones cannot create a provider dossier');
}
assert.throws(()=>ctx.TRBONB_signature_({...input,owner_approved:false}));assert.equal(posts,0);
ctx.TRBONB_signature_(input);ctx.TRBONB_signature_(input);assert.equal(posts,1);
assert.equal(lastPayload.recipients[0].phone_prefix,'39');assert.equal(lastPayload.recipients[0].phone_number,'3330000000');
ctx.TRBONB_signature_({...input,request_key:'e'.repeat(64),details:{billing:{phone:'00216 20 000 000'}}});assert.equal(posts,2);
assert.equal(lastPayload.recipients[0].phone_prefix,'216');assert.equal(lastPayload.recipients[0].phone_number,'20000000');assert.equal(lastPayload.recipients[0].language,'it');assert.equal(lastPayload.recipients[1].phone_prefix,'39');
assert.throws(()=>ctx.TRBONB_signature_({...input,document_sha256:'c'.repeat(64)}));assert.equal(posts,2);
fail=true;const uncertain={...input,request_key:'d'.repeat(64)};assert.throws(()=>ctx.TRBONB_signature_(uncertain));assert.throws(()=>ctx.TRBONB_signature_(uncertain));assert.equal(posts,3);
const receipt={dossier_id:'98765',document_sha256:sha(pdf),artist_email:'test@example.invalid',company_email:'andrea.tognassi@trbrec.com'};
const dossier={id:98765,state:'completed',signed_at:'2026-10-01',documents:[{label:'unsigned_document',file_hash:sha(pdf)}],recipients:[{email:receipt.artist_email,signed_at:'2026-10-01'},{email:receipt.company_email,signed_at:null}]};
assert.equal(ctx.TRBONB_proof_(dossier,receipt).status,'pending');dossier.recipients[1].signed_at='2026-10-01';assert.equal(ctx.TRBONB_proof_(dossier,receipt).status,'completed');dossier.recipients[1].email='other@example.invalid';assert.throws(()=>ctx.TRBONB_proof_(dossier,receipt));
console.log('OTP international prefix payloads, malformed-phone rejection, owner approval, four anchor permissions, durable dispatch receipt and both signers verified without provider calls.');
