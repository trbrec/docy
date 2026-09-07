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

      error.hidden = true;
      submitting = true;
      submit.disabled = true;
      submit.setAttribute('aria-busy', 'true');
      submit.textContent = 'Caricamento in corso…';
      setProgress(0, 'Preparazione dei file…');

      var request = new XMLHttpRequest();
      request.open((form.method || 'POST').toUpperCase(), form.getAttribute('action'), true);
      request.setRequestHeader('X-TRB-Upload', '1');
      request.setRequestHeader('Accept', 'application/json');
      request.timeout = 5 * 60 * 1000;

      request.upload.addEventListener('progress', function (uploadEvent) {
        if (!uploadEvent.lengthComputable) {
          progressText.textContent = 'Caricamento dei file in corso…';
          return;
        }
        var percent = uploadEvent.loaded / uploadEvent.total * 100;
        setProgress(percent, percent < 100 ? 'Caricamento dei file in corso…' : 'File caricati. Registrazione della richiesta…');
      });

      request.addEventListener('load', function () {
        var payload = null;
        try { payload = JSON.parse(request.responseText); } catch (parseError) {}
        if (!payload) {
          var start = request.responseText.indexOf('{');
          var end = request.responseText.lastIndexOf('}');
          if (start !== -1 && end > start) {
            try { payload = JSON.parse(request.responseText.slice(start, end + 1)); } catch (embeddedParseError) {}
          }
        }
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
        restore(payload && messages[payload.status] ? messages[payload.status] : 'Il server ha interrotto la registrazione (' + diagnostic + '). Nessun provino è stato acquisito.');
      });

      request.addEventListener('error', function () {
        restore('Connessione interrotta durante il caricamento. Nessun nuovo tentativo è stato inviato: riprova una sola volta.');
      });

      request.addEventListener('timeout', function () {
        restore('Il caricamento sta impiegando troppo tempo. Verifica la connessione prima di riprovare.');
      });

      var formData = new FormData(form);
      formData.append('trb_demo_async', '1');
      request.send(formData);
    });
  });
});

