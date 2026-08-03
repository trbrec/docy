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

function setWebhookSecret(secret) {
  if (!secret || String(secret).length < 24) {
    throw new Error('Il segreto deve contenere almeno 24 caratteri.');
  }
  PropertiesService.getScriptProperties().setProperty('TRB_WEBHOOK_SECRET', String(secret));
}

function doPost(e) {
  try {
    const raw = e && e.postData && e.postData.contents ? e.postData.contents : '';
    const headers = (e && e.headers) || {};
    const supplied = String(headers['X-TRB-Signature'] || headers['x-trb-signature'] || '');
    const secret = PropertiesService.getScriptProperties().getProperty('TRB_WEBHOOK_SECRET');

    if (!raw || !secret || !supplied || !safeEquals_(supplied, hmacHex_(raw, secret))) {
      return json_({ success: false, error: 'unauthorized' });
    }

    const data = JSON.parse(raw);
    const required = [
      'informazioni_cronologiche', 'nome', 'cognome', 'nome_arte',
      'email', 'titolo', 'link_provino', 'request_id'
    ];
    required.forEach(function (key) {
      if (!(key in data)) throw new Error('Campo mancante: ' + key);
    });

    const lock = LockService.getScriptLock();
    lock.waitLock(20000);
    try {
      const sheet = SpreadsheetApp.openById(TRB_SPREADSHEET_ID).getSheetByName(TRB_SHEET_NAME);
      if (!sheet) throw new Error('Scheda non trovata: ' + TRB_SHEET_NAME);

      const requestId = String(data.request_id);
      const lastRow = sheet.getLastRow();
      if (lastRow > 1) {
        const ids = sheet.getRange(2, 8, lastRow - 1, 1).getDisplayValues().flat();
        if (ids.indexOf(requestId) !== -1) {
          return json_({ success: true, duplicate: true, request_id: requestId });
        }
      }

      ensureHeaders_(sheet);
      sheet.appendRow([
        clean_(data.informazioni_cronologiche),
        clean_(data.nome),
        clean_(data.cognome),
        clean_(data.nome_arte),
        clean_(data.email),
        clean_(data.titolo),
        clean_(data.link_provino),
        requestId
      ]);
      SpreadsheetApp.flush();
      return json_({ success: true, request_id: requestId });
    } finally {
      lock.releaseLock();
    }
  } catch (error) {
    console.error(error);
    return json_({ success: false, error: String(error && error.message ? error.message : error) });
  }
}

function ensureHeaders_(sheet) {
  const expected = [
    'Informazioni cronologiche', 'Nome', 'Cognome', 'Nome d’arte',
    'E-mail', 'Titolo del provino', 'Link al provino', 'ID richiesta'
  ];
  const current = sheet.getRange(1, 1, 1, expected.length).getDisplayValues()[0];
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
