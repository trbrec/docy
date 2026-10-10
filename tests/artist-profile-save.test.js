const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('assets/js/trb-artist-profile.js', 'utf8');
const start = source.indexOf('  function initProfileUploadProgress()');
const end = source.indexOf('\n  var initialized', start);
assert(start >= 0 && end > start);
function fixture() {
  const state = { value: 'Profilo fittizio Tunisia', redirect: '', events: {} };
  const button = { textContent: 'Salva', disabled: false };
  const status = { textContent: '' };
  const panel = { classList: { add() {} }, setAttribute() {}, querySelector(selector) {
    if (selector === '[data-trb-upload-status]') return status;
    return { style: {}, textContent: '' };
  }};
  const form = { action: 'https://portal.example.invalid/admin-post.php', reportValidity: () => true,
    querySelector: () => button, querySelectorAll: () => [], insertBefore() {},
    addEventListener: (name, fn) => { state.events[name] = fn; } };
  class XHR {
    constructor() { state.xhr = this; this.events = {}; this.upload = { addEventListener() {} }; }
    open() {} send() {} addEventListener(name, fn) { this.events[name] = fn; }
  }
  const context = { document: { querySelectorAll: () => [form], createElement: () => panel },
    window: { location: { href: 'https://portal.example.invalid/area-artisti/', assign: url => { state.redirect = url; } } },
    XMLHttpRequest: XHR, FormData: class {}, URL };
  vm.runInNewContext(source.slice(start, end) + '\ninitProfileUploadProgress();', context);
  state.events.submit({ preventDefault() {} });
  return { state, button, status };
}
for (const result of ['file_upload_failed', 'profile_busy', 'storage_waiting', 'bio_invalid', 'bio_required', 'invalid_address', 'invalid_birth_date', 'invalid_phone', 'invalid_tax_code', 'invalid_document_number', 'invalid_document_expiry', 'artist_name_taken']) {
  const { state, button, status } = fixture();
  state.xhr.status = 200;
  state.xhr.responseURL = 'https://portal.example.invalid/area-artisti/?trb_profile=' + result;
  state.xhr.events.load();
  assert.equal(state.redirect, '', result + ' discarded the filled form');
  assert.equal(button.disabled, false);
  assert.equal(state.value, 'Profilo fittizio Tunisia');
  assert(!status.textContent.includes('Salvataggio completato'), result + ' reported success');
}
for (const event of ['error', 'timeout']) {
  const { state, button, status } = fixture();
  state.xhr.events[event]();
  assert.equal(state.redirect, '');
  assert.equal(button.disabled, false);
  assert(status.textContent.includes('non è possibile confermare'));
}
const { state, status } = fixture();
state.xhr.status = 200;
state.xhr.responseURL = 'https://portal.example.invalid/area-artisti/?trb_profile=saved#profilo';
state.xhr.events.load();
assert.equal(state.redirect, state.xhr.responseURL);
assert(status.textContent.includes('Salvataggio completato'));
console.log('Profile save: validation/upload failures preserve the form; only confirmed saves report success.');
