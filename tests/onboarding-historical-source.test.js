'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const root='1ziePo6xoURvWw0o2IDwjEaO_tz6pE9bn';
let privateAccess=true,editors=[],viewers=[],trashed=false,parentIds=[root],opened=[],copies=0;
const privateNode={isTrashed:()=>trashed,getSharingAccess:()=>privateAccess?'PRIVATE':'ANYONE',getEditors:()=>editors,getViewers:()=>viewers};
const ctx={DriveApp:{Access:{PRIVATE:'PRIVATE'},getFolderById:id=>{assert.equal(id,root);return privateNode;},getFileById:id=>({...privateNode,getParents(){let i=0;return {hasNext:()=>i<parentIds.length,next:()=>({getId:()=>parentIds[i++]})};},makeCopy(){copies++;throw Error('unexpected copy');}})},DocumentApp:{openById:id=>{opened.push(id);return {getBody:()=>({getText:()=> 'modified historical source'}),getHeader:()=>null,getFooter:()=>null};}}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync('integrations/onboarding/apps-script/CRM_Onboarding.gs','utf8'),ctx);vm.runInContext(fs.readFileSync('integrations/onboarding/apps-script/CRM_OnboardingDrive.gs','utf8'),ctx);
ctx.TRBONB_sha_=()=> '0'.repeat(64);
let checks=0;
for(const [key,hashes] of Object.entries(ctx.TRBONB_LEGACY_MODELS))for(const [hash,id] of Object.entries(hashes)){
 const snapshot={template_key:key,template_document_id:ctx.TRBONB_MODELS[key],source_sha256:hash},before=JSON.stringify(snapshot);
 assert.equal(ctx.TRBONB_sourceModel_(snapshot),id);checks++;
 assert.equal(JSON.stringify(snapshot),before,'historical snapshot remains immutable');checks++;
 assert.throws(()=>ctx.TRBONB_sourceModel_({...snapshot,template_document_id:id}),/non autorizzato/);checks++;
 assert.throws(()=>ctx.TRBONB_sourceModel_({...snapshot,template_key:'trb_ccde'}),/non autorizzato/);checks++;
 assert.equal(ctx.TRBONB_sourceModel_({...snapshot,source_sha256:'f'.repeat(64)}),snapshot.template_document_id,'unknown historical fingerprints never select a private legacy copy');checks++;
 for(const phase of ['proposal','final']){assert.throws(()=>ctx.TRBONB_document_({snapshot,phase}),/modello è cambiato/);assert.equal(opened.at(-1),id);assert.equal(copies,0);checks++;}
 for(const invalidate of [()=>privateAccess=false,()=>editors=[{}],()=>viewers=[{}],()=>trashed=true,()=>parentIds=['other'],()=>parentIds=[root,'other'],()=>parentIds=[]]){
  invalidate();assert.throws(()=>ctx.TRBONB_sourceModel_(snapshot));checks++;
  privateAccess=true;editors=[];viewers=[];trashed=false;parentIds=[root];
 }
}
assert.equal(ctx.TRBONB_sourceModel_({template_key:'trb_ccde',template_document_id:ctx.TRBONB_MODELS.trb_ccde,source_sha256:'f'.repeat(64)}),ctx.TRBONB_MODELS.trb_ccde);checks++;
assert.throws(()=>ctx.TRBONB_legacyHealth_(),/Versione storica del contratto modificata/,'readiness rejects historical source drift before a deployment');checks++;
console.log('Historical proposal compatibility: '+checks+' strict source, privacy, immutable snapshot and drift checks passed.');
