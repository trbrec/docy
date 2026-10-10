/**
 * TRB rec - webhook firmato per registrare i provini nel foglio.
 *
 * Configurazione (una sola volta, nell'editor Apps Script):
 * 1. Eseguire setWebhookSecret('SEGRETO_IDENTICO_A_WORDPRESS').
 * 2. Distribuire come app web: esegui come proprietario; accesso "Chiunque".
 * 3. Copiare l'URL /exec in WordPress > Strumenti > Automazione demo.
 */
const TRB_SPREADSHEET_ID = '15-A6nUDO47zxLrMJ-8xQs4AcvnjHwwQIgpEeLwS8pa4';
const TRB_SHEET_NAME = '2026 NEW';

function doGet() { return json_({ protocol: 'trb-demo-sheet-v2', isolated_qa: true }); }

function setWebhookSecret(secret) {
  if (!secret || String(secret).length < 24) {
    throw new Error('Il segreto deve contenere almeno 24 caratteri.');
  }
  PropertiesService.getScriptProperties().setProperty('TRB_WEBHOOK_SECRET', String(secret));
}

function doPost(e) {
  try {
    const raw = e && e.postData && e.postData.contents ? e.postData.contents : '';
    const secret = PropertiesService.getScriptProperties().getProperty('TRB_WEBHOOK_SECRET');
    const envelope = raw ? JSON.parse(raw) : {};
    const supplied = String(envelope.signature || '');
    const payloadBase64 = String(envelope.payload_base64 || '');
    const payloadJson = payloadBase64
      ? Utilities.newBlob(Utilities.base64Decode(payloadBase64)).getDataAsString('UTF-8')
      : '';

    if (!secret || !supplied || !payloadJson || !safeEquals_(supplied, hmacHex_(payloadJson, secret))) {
      return json_({ success: false, error: 'unauthorized' });
    }
    const data = JSON.parse(payloadJson);
    if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('Dati non validi.');
    const required = [
      'informazioni_cronologiche', 'nome', 'cognome', 'nome_arte',
      'email', 'titolo', 'link_provino', 'request_id'
    ];
    required.forEach(function (key) {
      if (!(key in data) || (typeof data[key] !== 'string' && !(key === 'request_id' && Number.isSafeInteger(data[key])))) throw new Error('Campo non valido: ' + key);
    });

    const lock = LockService.getScriptLock();
    lock.waitLock(20000);
    try {
      const book = SpreadsheetApp.openById(TRB_SPREADSHEET_ID);
      if (Object.prototype.hasOwnProperty.call(data, 'qa_run')) return demoQa_(book, data);
      const sheet = book.getSheetByName(TRB_SHEET_NAME);
      if (!sheet) throw new Error('Scheda dei provini non disponibile.');
      return json_(writeDemoRow_(sheet, data));
    } finally {
      lock.releaseLock();
    }
  } catch (error) {
    console.error(error);
    return json_({ success: false, error: String(error && error.message ? error.message : error) });
  }
}

function demoValues_(data) {
  return ['informazioni_cronologiche', 'nome', 'cognome', 'nome_arte', 'email', 'titolo', 'link_provino', 'request_id'].map(function (key) { return clean_(data[key]); });
}

function writeDemoRow_(sheet, data) {
  const row = demoValues_(data);
  const requestId = row[7];
  if (!requestId) throw new Error('ID richiesta non valido.');
  ensureHeaders_(sheet);
  const lastRow = sheet.getLastRow();
  if (lastRow > 1) {
    const ids = sheet.getRange(2, 8, lastRow - 1, 1).getDisplayValues().flat();
    const previous = ids.indexOf(requestId);
    if (previous !== -1) {
      const saved = sheet.getRange(previous + 2, 1, 1, 8).getDisplayValues()[0];
      if (JSON.stringify(saved) !== JSON.stringify(row)) throw new Error('ID richiesta associato a dati differenti.');
      return { success: true, duplicate: true, request_id: requestId };
    }
  }
  const target = lastRow + 1;
  if (target > sheet.getMaxRows()) sheet.insertRowsAfter(sheet.getMaxRows(), 1);
  const range = sheet.getRange(target, 1, 1, 8);
  range.setNumberFormat('@').setValues([row.map(function (value) { return /^[=+\-@']/.test(value) ? "'" + value : value; })]);
  SpreadsheetApp.flush();
  if (JSON.stringify(range.getDisplayValues()[0]) !== JSON.stringify(row)) throw new Error('Rilettura della riga non confermata.');
  return { success: true, request_id: requestId };
}

// Signed, isolated provider QA; it cannot select or erase a production tab.
function demoQa_(book, data) {
  const run = data.qa_run;
  if (typeof run !== 'string' || !/^[a-f0-9]{16}$/.test(run) || data.request_id !== 'QA-AUDIT-' + run || data.email !== 'qa-' + run + '@example.invalid') throw new Error('Identità di collaudo non valida.');
  const name = 'TRB_AUDIT_' + run;
  let sheet = book.getSheetByName(name);
  if (data.qa_action === 'write') {
    if (!sheet) { sheet = book.insertSheet(name); sheet.hideSheet(); }
    const result = writeDemoRow_(sheet, data);
    result.qa_run = run;
    return json_(result);
  }
  if (data.qa_action === 'read') {
    if (!sheet || sheet.getLastRow() !== 2) throw new Error('Riga di collaudo non disponibile.');
    return json_({ success: true, qa_run: run, values: sheet.getRange(2, 1, 1, 8).getDisplayValues()[0] });
  }
  if (data.qa_action === 'cleanup') {
    if (sheet) {
      if (sheet.getLastRow() > 2) throw new Error('La scheda contiene dati estranei al collaudo.');
      const headers = sheet.getRange(1, 1, 1, 8).getDisplayValues()[0];
      if (headers.join('|') !== 'Informazioni cronologiche|Nome|Cognome|Nome d’arte|E-mail|Titolo del provino|Link al provino|ID richiesta') throw new Error('Intestazioni di collaudo differenti.');
      if (sheet.getLastRow() === 2) {
        const row = sheet.getRange(2, 1, 1, 8).getDisplayValues()[0];
        if (row[7] !== data.request_id || row[4] !== data.email) throw new Error('Dati di collaudo differenti.');
      }
      book.deleteSheet(sheet);
    }
    return json_({ success: true, qa_run: run, cleaned: book.getSheetByName(name) === null });
  }
  throw new Error('Operazione di collaudo non valida.');
}

function ensureHeaders_(sheet) {
  const expected = [
    'Informazioni cronologiche', 'Nome', 'Cognome', 'Nome d’arte',
    'E-mail', 'Titolo del provino', 'Link al provino', 'ID richiesta'
  ];
  const current = sheet.getRange(1, 1, 1, expected.length).getDisplayValues()[0];
  if (current.some(function (cell) { return cell !== ''; }) && current.join('|') !== expected.join('|')) throw new Error('Intestazioni del foglio differenti.');
  if (current.join('|') !== expected.join('|')) {
    sheet.getRange(1, 1, 1, expected.length).setValues([expected]).setFontWeight('bold');
    sheet.setFrozenRows(1);
  }
}

function hmacHex_(text, secret) {
  return Utilities.computeHmacSha256Signature(text, secret)
    .map(function (value) {
      const byte = value < 0 ? value + 256 : value;
      return ('0' + byte.toString(16)).slice(-2);
    })
    .join('');
}

function safeEquals_(left, right) {
  if (left.length !== right.length) return false;
  let diff = 0;
  for (let i = 0; i < left.length; i++) {
    diff |= left.charCodeAt(i) ^ right.charCodeAt(i);
  }
  return diff === 0;
}

function clean_(value) {
  return String(value == null ? '' : value).replace(/[\u0000-\u001F\u007F]/g, ' ').trim();
}

function json_(payload) {
  return ContentService
    .createTextOutput(JSON.stringify(payload))
    .setMimeType(ContentService.MimeType.JSON);
}
