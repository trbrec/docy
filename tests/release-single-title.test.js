'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('assets/js/trb-release-upload.js','utf8');
const start=source.indexOf('function validateSingleTitle(form){');
const end=source.indexOf('\nfunction ',start+1);
assert(start>=0&&end>start);
const ctx={};vm.createContext(ctx);vm.runInContext(source.slice(start,end),ctx);
function form(count,title,track,type='single'){
 const release={value:title,setCustomValidity(v){this.error=v;}},first={value:track,setCustomValidity(v){this.error=v;}},note={hidden:false};
 return {release,first,note,querySelector(selector){if(selector.includes('trb_release_type'))return {value:type};if(selector.includes('trb_release_title'))return release;if(selector.includes('[data-track]'))return first;return note;},querySelectorAll(){return Array.from({length:count},()=>({}));}};
let f=form(1,'Release title','Track title');assert.equal(ctx.validateSingleTitle(f),false);assert(f.release.error);
f=form(1,'Track title','Track title');assert.equal(ctx.validateSingleTitle(f),true);
f=form(3,'Release title','Track title');assert.equal(ctx.validateSingleTitle(f),true);assert.equal(f.release.error,'');assert.equal(f.note.hidden,true);
f=form(2,'Release title','Track title');assert.equal(ctx.validateSingleTitle(f),true);
f=form(4,'EP title','Track title','ep');assert.equal(ctx.validateSingleTitle(f),true);
console.log('PASS one-track title matching and independent multi-track release titles');
