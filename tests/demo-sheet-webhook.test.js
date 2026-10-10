'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const crypto = require('node:crypto');
const secret = 's'.repeat(64);
class Range {
  constructor(sheet, row, column, rows, columns) { Object.assign(this, { sheet, row, column, rows, columns }); }
  getDisplayValues() { return Array.from({length: this.rows}, (_,r) => Array.from({length: this.columns}, (_,c) => String(this.sheet.cells[this.row-1+r]?.[this.column-1+c] ?? ''))); }
  setNumberFormat() { return this; }
  setFontWeight() { return this; }
  setValues(values) {
    if (this.sheet.failWrite) throw new Error('Synthetic write failure');
    values.forEach((row,r) => row.forEach((value,c) => {
      this.sheet.cells[this.row-1+r] ??= [];
      this.sheet.cells[this.row-1+r][this.column-1+c] = String(value).replace(/^'/, '');
    }));
    return this;
  }
}
class Sheet {
  cells = []; maxRows = 1000;
  constructor(name) { this.name = name; }
  getRange(...args) { return new Range(this, ...args); }
  getLastRow() { return this.cells.length; }
  getMaxRows() { return this.maxRows; }
  insertRowsAfter(last, count) { this.maxRows += count; }
  setFrozenRows() {}
  hideSheet() { this.hidden = true; }
}
const sheets = new Map([['2026 NEW', new Sheet('2026 NEW')]]);
const book = {getSheetByName: name => sheets.get(name) ?? null, insertSheet(name) { const s = new Sheet(name); sheets.set(name,s); return s; }, deleteSheet(sheet) { sheets.delete(sheet.name); }};
const context = vm.createContext({
  console: {error() {},log() {}},
  PropertiesService: {getScriptProperties: () => ({getProperty: () => secret})},
  Utilities: {Charset:{UTF_8:'UTF-8'},base64Encode:text=>Buffer.from(text).toString('base64'),base64Decode: text => Buffer.from(text,'base64'), newBlob: bytes => ({getDataAsString: () => bytes.toString('utf8')}), computeHmacSha256Signature: (text,key) => [...crypto.createHmac('sha256',key).update(text).digest()]},
  LockService: {getScriptLock: () => ({waitLock() {},releaseLock() {}})},
  SpreadsheetApp: {openById: () => book, flush() {}},
  ContentService: {MimeType: {JSON:'json'}, createTextOutput: text => ({text,getContent(){return text;},setMimeType() { return this; }})}
});
vm.runInContext(fs.readFileSync(require('node:path').join(__dirname,'../integrations/google-apps-script/demo-sheet-webhook.gs'),'utf8'),context);
function post(data, valid = true) {
  const payload = JSON.stringify(data);
  const envelope = {payload_base64: Buffer.from(payload).toString('base64'), signature: crypto.createHmac('sha256',secret).update(payload).digest('hex')};
  if (!valid) envelope.signature = '0'.repeat(64);
  return JSON.parse(context.doPost({postData:{contents:JSON.stringify(envelope)}}).text);
}
const run = '1234567890abcdef';
const beforeHealth = JSON.stringify([...sheets].map(([name,s])=>[name,s.cells]));
assert.equal(context.testReadOnlyDemoSheetHealth().authenticated_read_only,true);
assert.equal(post({action:'health'},false).error,'unauthorized');
assert.deepEqual(post({action:'health'}),{success:true,protocol:'trb-demo-sheet-health-v1',sheet_available:true,read_only:true});
assert.equal(JSON.stringify([...sheets].map(([name,s])=>[name,s.cells])),beforeHealth,'Health never creates a row or changes headers');
const normalSheet=sheets.get('2026 NEW');sheets.delete('2026 NEW');
assert.equal(post({action:'health'}).success,false,'A missing sheet is not healthy');
sheets.set('2026 NEW',normalSheet);
const row = {informazioni_cronologiche:'10/10/2026 18:00',nome:'Artista',cognome:'Fittizio',nome_arte:'=Artista تونس',email:'qa-'+run+'@example.invalid',titolo:'Collaudo con apostrofo: l’onda',link_provino:'https://example.invalid/demo',request_id:'QA-AUDIT-'+run,qa_run:run,qa_action:'write'};
assert.equal(post(row,false).success,false);
assert.equal(sheets.size,1);
assert.equal(post(row).success,true);
assert.equal(sheets.get('2026 NEW').getLastRow(),0);
assert.equal(sheets.get('TRB_AUDIT_'+run).hidden,true);
assert.equal(post(row).duplicate,true);
assert.equal(sheets.get('TRB_AUDIT_'+run).getLastRow(),2);
assert.equal(post({...row,titolo:'Differente'}).success,false);
const read = post({...row,qa_action:'read'});
assert.equal(read.values[3],row.nome_arte);
assert.equal(read.values[5],row.titolo);
assert.equal(post({...row,email:'artist@example.com'}).success,false);
sheets.get('TRB_AUDIT_'+run).cells.push(['Estraneo']);
assert.equal(post({...row,qa_action:'cleanup'}).success,false);
sheets.get('TRB_AUDIT_'+run).cells.pop();
assert.equal(post({...row,qa_action:'cleanup'}).cleaned,true);
assert.equal(post({...row,qa_action:'cleanup'}).cleaned,true);
assert.equal(sheets.size,1);
const production = {...row,request_id:'100'}; delete production.qa_run; delete production.qa_action;
sheets.get('2026 NEW').failWrite=true;
assert.equal(post(production).success,false);
sheets.get('2026 NEW').failWrite=false;
assert.equal(post(production).success,true);
assert.equal(post(production).duplicate,true);
assert.equal(sheets.get('2026 NEW').cells[1][3],row.nome_arte);
console.log('Signed Sheets writes, literal formula-like text, readback, idempotency and isolated QA cleanup verified.');
