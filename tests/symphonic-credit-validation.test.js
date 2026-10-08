'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const src=fs.readFileSync('assets/js/trb-symphonic-credits.js','utf8');
const context={};vm.createContext(context);vm.runInContext(src.slice(src.indexOf('function validate('),src.indexOf("document.addEventListener('DOMContentLoaded'")),context);
function track(advisory,roles){const groups=Object.entries(roles).map(([key,list])=>{const first={value:'Test Name',setCustomValidity(v){this.error=v;}},error={textContent:''};return {dataset:{contributorGroup:key},first,error,querySelector(s){return s==='[data-credit-error]'?error:first;},querySelectorAll(){return list.map(role=>({querySelector(s){return s==='select'?{value:role}:first;},querySelectorAll(){return [{value:role}];}}));}};});return {groups,querySelector(){return{value:advisory};},querySelectorAll(){return groups;}};}
const valid={writers:['Composer','Lyricist'],performers:['Vocals'],engineering:['Producer']};
let t=track('non_explicit',valid);context.validate(t);assert(t.groups.every(g=>!g.first.error));
for(const [key,replacement] of Object.entries({writers:['Composer'],performers:['Piano'],engineering:['Mixing Engineer']})){t=track('non_explicit',{...valid,[key]:replacement});context.validate(t);assert(t.groups.find(g=>g.dataset.contributorGroup===key).first.error);}
t=track('no_lyrics',{writers:['Composer'],performers:['Piano'],engineering:['Producer']});context.validate(t);assert(t.groups.every(g=>!g.first.error));
t=track('non_explicit',valid);context.validate(t);assert(t.groups.every(g=>!g.first.error));
context.validate({querySelectorAll(){return [];} });
const catalog=JSON.parse(fs.readFileSync('assets/data/symphonic-credit-roles.json','utf8'));assert.equal(catalog.writers.length,43);assert.equal(catalog.performers.length,818);assert.equal(catalog.engineering.length,115);for(const rows of Object.values(catalog)){assert.equal(new Set(rows.map(r=>r.name)).size,rows.length);assert.equal(new Set(rows.map(r=>r.id)).size,rows.length);}
console.log('PASS: vocal requirements, instrumental exception, legacy form exclusion and complete role catalogue');
