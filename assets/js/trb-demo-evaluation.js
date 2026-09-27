function trbDemoResponse(request) {
  try { return JSON.parse(request.responseText); } catch (parseError) {
    var body = String(request.responseText || '');
    var start = body.indexOf('{"success":');
    var end = body.lastIndexOf('}');
    if (start !== -1 && end > start) {
      try { return JSON.parse(body.slice(start, end + 1)); } catch (embeddedError) {}
    }
    return null;
  }
}

function trbDemoStageFiles(form, files, onProgress) {
  var token = form.querySelector('[name="trb_release_submission_token"]').value;
  var nonce = form.querySelector('[name="trb_release_stage_nonce"]').value;
  var chunkSize = 2 * 1024 * 1024;
  var totalBytes = files.reduce(function (sum, item) { return sum + item.file.size; }, 0);
  var completeBytes = 0, manifest = {};
  return files.reduce(function (chain, item) {
    return chain.then(function () {
      var file = item.file, input = item.input, chunks = Math.ceil(file.size / chunkSize), index = 0;
      if (input._trbDemoUploadFile !== file) {
        input._trbDemoUploadFile = file;
        input._trbDemoUploadId = crypto.randomUUID();
      }
      manifest[input.name] = {key:item.key,upload_id:input._trbDemoUploadId,session:token};
      function next() {
        if (index >= chunks) { completeBytes += file.size; onProgress(completeBytes, totalBytes); return Promise.resolve(); }
        var start = index * chunkSize, end = Math.min(file.size, start + chunkSize);
        var data = new FormData();
        [['action','trb_portal_stage_release_chunk'],['trb_release_stage_nonce',nonce],['session',token],['file_key',item.key],['upload_id',input._trbDemoUploadId],['field_name',input.name],['file_name',file.name],['file_type',file.type || ''],['file_size',String(file.size)],['last_modified',String(file.lastModified || 0)],['chunk_index',String(index)],['chunk_total',String(chunks)]].forEach(function (entry) { data.append(entry[0],entry[1]); });
        data.append('trb_release_chunk',file.slice(start,end),'chunk.part');
        return new Promise(function (resolve,reject) {
          var request = new XMLHttpRequest();
          request.open('POST',form.getAttribute('action'),true);
          request.timeout = 120000;
          request.upload.addEventListener('progress',function (event) { if (event.lengthComputable) onProgress(completeBytes+start+event.loaded,totalBytes); });
          request.addEventListener('load',function () {
            var response = trbDemoResponse(request);
            if (request.status >= 200 && request.status < 300 && response && response.success && response.data && Number(response.data.next_chunk) > index && Number(response.data.next_chunk) <= chunks) {
              index = Number(response.data.next_chunk); resolve(next()); return;
            }
            reject(Error(response && response.data && response.data.message || 'Blocco del file non acquisito (HTTP '+request.status+'). Riprova dallo stesso modulo.'));
          });
          request.addEventListener('error',function () { reject(Error('Connessione interrotta durante il caricamento. Riprova dallo stesso modulo: i blocchi confermati saranno riutilizzati.')); });
          request.addEventListener('timeout',function () { reject(Error('Caricamento del file scaduto. Riprova dallo stesso modulo.')); });
          request.send(data);
        });
      }
      return next();
    });
  },Promise.resolve()).then(function () { return manifest; });
}

function trbDemoFinalData(form, manifest) {
  var data = new FormData(form);
  data.delete('trb_demo_text');
  data.delete('trb_demo_audio');
  data.append('trb_staged_uploads_json',JSON.stringify(manifest));
  data.append('trb_demo_async','1');
  return data;
}

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-demo-form]').forEach(function (form) {
    var text = form.querySelector('[data-demo-text]');
    var audio = form.querySelector('[data-demo-audio]');
    var error = form.querySelector('[data-demo-error]');
    var submit = form.querySelector('[data-demo-submit]');
    var progress = form.querySelector('[data-demo-progress]');
    var progressBar = form.querySelector('[data-demo-progress-bar]');
    var progressValue = form.querySelector('[data-demo-progress-value]');
    var progressText = form.querySelector('[data-demo-progress-text]');
    var submitting = false;
    var focus = form.querySelector('[data-demo-focus]');
    var origin = function(part) { return form.querySelector('[name="trb_demo_origin_' + part + '"]'); };
    var scopes = {lyrics:['lyrics'], composition:['music'], performance:['performance'], overall:['lyrics','music','performance']};
    var needsText = false, needsAudio = false, ready = false;
    var submissionKind = form.querySelector('[data-demo-submission-kind]');
    var parent = form.querySelector('[data-demo-parent]');
    function syncRevision() {
      var active = submissionKind.value === 'revision';
      form.querySelector('[data-demo-revision-block]').hidden = !active;
      parent.disabled = !active; parent.required = active;
      form.querySelector('[data-demo-changes]').disabled = !active;
    }
    submissionKind.addEventListener('change', syncRevision);
    syncRevision();
    function sync() {
      var parts = scopes[focus.value] || [];
      form.querySelector('[data-demo-origins]').hidden = !parts.length;
      ['lyrics','music','performance'].forEach(function(part) {
        var input = origin(part), active = parts.includes(part);
        form.querySelector('[data-demo-origin-row="' + part + '"]').hidden = !active;
        input.disabled = !active;
        input.required = active;
        Array.from(input.options).forEach(function(option) {
          option.disabled = (option.value === 'absent' && focus.value !== 'overall') ||
            (option.value === 'ai_generated' && focus.value === 'performance');
        });
        if (input.selectedOptions[0] && input.selectedOptions[0].disabled) input.value = '';
      });
      ready = parts.length > 0 && parts.every(function(part) { return !!origin(part).value; });
      needsText = ready && (focus.value === 'lyrics' || (focus.value === 'overall' && origin('lyrics').value !== 'absent'));
      needsAudio = ready && (['composition','performance'].includes(focus.value) || (focus.value === 'overall' && (origin('music').value !== 'absent' || origin('performance').value !== 'absent')));
      [[text,needsText,'text'],[audio,needsAudio,'audio']].forEach(function(item) {
        form.querySelector('[data-demo-' + item[2] + '-block]').hidden = !item[1];
        item[0].disabled = !item[1]; item[0].required = item[1];
        if (!item[1]) item[0].value = '';
      });
      form.querySelector('[data-demo-notes-block]').hidden = !parts.length;
      form.querySelector('[data-demo-material-hint]').textContent = !parts.length ? 'Scegli il tipo di valutazione per proseguire.' :
        !ready ? 'Indica la provenienza dei contributi: compariranno i materiali da allegare.' :
        !needsText && !needsAudio ? 'Indica almeno un contributo presente.' :
        needsText && needsAudio ? 'Materiali richiesti: testo TXT o DOCX e provino MP3.' :
        needsText ? 'Materiale richiesto: testo TXT o DOCX.' : 'Materiale richiesto: provino MP3.';
      if (!submitting) submit.disabled = !ready || (!needsText && !needsAudio);
    }

    function setProgress(percent, message) {
      var value = Math.max(0, Math.min(100, Math.round(percent)));
      progress.hidden = false;
      progressBar.value = value;
      progressBar.textContent = value + '%';
      progressValue.textContent = value + '%';
      if (message) progressText.textContent = message;
    }

    function restore(message) {
      submitting = false;
      submit.disabled = false;
      submit.removeAttribute('aria-busy');
      submit.textContent = 'Invia il provino per la valutazione';
      if (message) {
        error.textContent = message;
        error.hidden = false;
      }
    }

    focus.addEventListener('change', sync);
    ['lyrics','music','performance'].forEach(function(part) { origin(part).addEventListener('change', sync); });
    sync();

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (submitting) return;

      sync();
      var hasText = !text.disabled && text.files.length === 1;
      var hasAudio = !audio.disabled && audio.files.length === 1;
      var message = '';
      if (submissionKind.value === 'revision' && !parent.value) message = 'Seleziona il provino precedente già valutato.';
      else if (!ready) message = 'Seleziona il tipo di valutazione e le provenienze richieste.';
      else if (!needsText && !needsAudio) message = 'Indica almeno un contributo presente.';
      else if (needsText !== hasText || needsAudio !== hasAudio) message = 'Allega i materiali richiesti per questa valutazione.';
      if (message) {
        error.textContent = message;
        error.hidden = false;
        error.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }

      var files = [];
      if (hasText) files.push({input:text,file:text.files[0],key:'f2000',limit:2*1024*1024,extension:/\.(txt|docx)$/i});
      if (hasAudio) files.push({input:audio,file:audio.files[0],key:'f2001',limit:25*1024*1024,extension:/\.mp3$/i});
      var invalidFile = files.find(function (item) { return !item.file.size || item.file.size > item.limit || !item.extension.test(item.file.name); });
      if (invalidFile) {
        error.textContent = 'Controlla '+invalidFile.file.name+': testo TXT/DOCX fino a 2 MB, audio MP3 fino a 25 MB.';
        error.hidden = false;
        error.scrollIntoView({ behavior:'smooth', block:'center' });
        return;
      }

      error.hidden = true;
      submitting = true;
      submit.disabled = true;
      submit.setAttribute('aria-busy', 'true');
      submit.textContent = 'Caricamento in corso…';
      setProgress(0, 'Preparazione dei file…');

      trbDemoStageFiles(form,files,function (done,total) {
        setProgress(total ? Math.min(98,done/total*98) : 0,'Caricamento dei file a blocchi…');
      }).then(function (manifest) {
        setProgress(99,'File acquisiti. Registrazione della richiesta…');
        var formData = trbDemoFinalData(form,manifest);
        var request = new XMLHttpRequest();
        request.open((form.method || 'POST').toUpperCase(),form.getAttribute('action'),true);
        request.setRequestHeader('X-TRB-Upload','1');
        request.setRequestHeader('Accept','application/json');
        request.timeout = 120000;
        request.addEventListener('load',function () {
        var payload = trbDemoResponse(request);
        if (request.status >= 200 && request.status < 300 && payload && payload.success) {
          setProgress(100, payload.status === 'duplicate' ? 'Provino già ricevuto. Apertura della conferma…' : 'Invio completato. Apertura della conferma…');
          window.location.assign(payload.redirect);
          return;
        }
        var messages = {
          invalid_revision: 'Seleziona un tuo provino con valutazione già inviata e ancora disponibile.',
          invalid: 'Controlla titolo, dichiarazioni e allegati prima di riprovare.',
          upload_error: 'Uno degli allegati non è valido. Usa TXT o DOCX per il testo e un solo file MP3 per l’audio.',
          processing: 'Un invio dello stesso account è già in corso. Attendi il completamento.',
          weekly_limit: 'Hai già utilizzato la valutazione disponibile per questa settimana.',
          forbidden: 'Questo profilo non è abilitato alla valutazione dei demo.',
          session_expired: 'La sessione del modulo non è valida. Ricarica la pagina e accedi nuovamente prima di riprovare.'
        };
        var diagnostic = 'HTTP ' + request.status;
        var contentType = request.getResponseHeader('Content-Type');
        if (contentType) diagnostic += ' · ' + contentType.split(';')[0];
        restore(payload && messages[payload.status] ? messages[payload.status] : 'Esito non confermato (' + diagnostic + '). Controlla lo stato delle valutazioni prima di riprovare.');
        });
        request.addEventListener('error',function () { restore('Esito non confermato: la connessione si è interrotta durante la registrazione. Controlla lo stato delle valutazioni prima di riprovare.'); });
        request.addEventListener('timeout',function () { restore('Esito non confermato: registrazione scaduta. Controlla lo stato delle valutazioni prima di riprovare.'); });
        request.send(formData);
      }).catch(function (problem) { restore(problem.message || 'Nessun provino è stato acquisito. Riprova dallo stesso modulo.'); });
    });
  });
});
