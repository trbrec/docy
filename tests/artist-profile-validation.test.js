'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
class Field{
 constructor(tag='input'){this.tag=tag;this._value='';this.children=[];this.dataset={};this.events={};this.validity='';this.classList={toggle(){},remove(){}};}
 set value(v){this._value=v;}get value(){return this.tag==='select'&&!this._value&&this.children.length?this.children[0].value:this._value;}
 set innerHTML(v){this.children=[];this._value='';if(v.includes('<option')){const option=new Field('option');option.value='';this.children.push(option);}}
 get options(){return this.children;}get selectedIndex(){return this.children.findIndex(x=>x.value===this.value);}
 appendChild(n){this.children.push(n);if(n.selected)this._value=n.value;}
 addEventListener(type,fn){(this.events[type]??=[]).push(fn);}dispatchEvent(e){for(const fn of this.events[e.type]||[])fn(e);}setCustomValidity(v){this.validity=v;}
}
const selectors=['data-trb-postcode','data-trb-city','data-trb-province','data-trb-country','data-trb-postcode-status','data-trb-birthplace','data-trb-birth-province','data-trb-birthplace-status','data-trb-tax-code','data-trb-document-number','data-trb-document-expiry'];
const fields=Object.fromEntries(selectors.map(s=>[s,new Field(s==='data-trb-city'?'select':'input')]));
const phone=new Field(),list=new Field(),pending=[],municipal=[],timers=[];
const ctx={console,Date,Event:class{constructor(type){this.type=type;}},setTimeout:fn=>{timers.push(fn);return timers.length;},clearTimeout(){},window:{trbArtistProfile:{lookupPostcode:value=>new Promise((resolve,reject)=>pending.push({value,resolve,reject})),lookupMunicipalities:search=>new Promise(resolve=>municipal.push({search,resolve}))}},document:{createElement:tag=>new Field(tag),querySelector:selector=>selector.includes('trb_artist_phone')?phone:fields[selector.slice(1,-1)]||null,querySelectorAll:()=>[],getElementById:()=>list,addEventListener:(event,fn)=>ctx.ready=fn}};
const input=(key,value,event='input')=>{const field=fields[key];field.value=value;field.dispatchEvent({type:event});return field;};
const settle=async()=>{for(let i=0;i<5;i++)await new Promise(r=>setImmediate(r));};
(async()=>{
 vm.createContext(ctx);vm.runInContext(fs.readFileSync('assets/js/trb-artist-profile.js','utf8'),ctx);ctx.ready();
 assert.equal(input('data-trb-tax-code','rss mra90a01h501w').value,'RSSMRA90A01H501W');assert.equal(fields['data-trb-tax-code'].validity,'');
 for(const value of ['RSSMRA90A01H501A','RSSMRA90001H501W','RSSMRA90'])assert.notEqual(input('data-trb-tax-code',value).validity,'','wrong checksum, wrong character position or length rejected');
 phone.value='0039 333 0000000';phone.dispatchEvent({type:'input'});assert.equal(phone.validity,'');phone.dispatchEvent({type:'blur'});assert.equal(phone.value,'+393330000000');phone.value='123';phone.dispatchEvent({type:'input'});assert.notEqual(phone.validity,'');
 assert.equal(input('data-trb-document-number','ca 12345 ab').value,'CA12345AB');assert.equal(fields['data-trb-document-number'].validity,'');assert.notEqual(input('data-trb-document-number','123456789').validity,'');
 const today=new Date(),year=today.getFullYear();assert.equal(input('data-trb-document-expiry',`${year+1}-01-01`,'change').validity,'');assert.notEqual(input('data-trb-document-expiry','2020-01-01','change').validity,'');
 input('data-trb-postcode','25038');input('data-trb-postcode','20121');assert.notEqual(fields['data-trb-postcode'].validity,'','pending CAP blocks submit');
 pending[1].resolve({places:[{city:'Milano',province:'MI'}],country:'Italia'});await settle();pending[0].resolve({places:[{city:'Rovato',province:'BS'}]});await settle();assert.equal(fields['data-trb-city'].value,'Milano','late result for old CAP cannot overwrite current city');assert.equal(fields['data-trb-province'].value,'MI');
 input('data-trb-postcode','20a');assert.equal(fields['data-trb-postcode'].value,'20');assert.equal(fields['data-trb-province'].value,'');assert.notEqual(fields['data-trb-postcode'].validity,'');
 input('data-trb-postcode','20121');assert.equal(pending.length,3,'returning to the previous CAP reloads after incomplete input');pending[2].resolve({places:[{city:'Milano',province:'MI'},{city:'Other',province:'MI'}]});await settle();assert.equal(fields['data-trb-city'].value,'','shared CAP requires choosing a municipality');fields['data-trb-city'].value='Milano';fields['data-trb-city'].dispatchEvent({type:'change'});assert.equal(fields['data-trb-province'].value,'MI');
 input('data-trb-birthplace','Roma');timers.pop()();input('data-trb-birthplace','Rovato');timers.pop()();municipal[1].resolve({places:[{city:'Rovato',province:'BS'}]});await settle();municipal[0].resolve({places:[{city:'Roma',province:'RM'}]});await settle();assert.equal(fields['data-trb-birth-province'].value,'BS','stale birthplace result cannot overwrite current selection');assert.equal(fields['data-trb-birthplace'].validity,'');
 console.log('Shared portal identity validation, CAP selection and lookup races verified.');
})().catch(e=>{console.error(e);process.exit(1);});
