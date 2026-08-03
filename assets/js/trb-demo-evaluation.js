document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-demo-form]').forEach(function (form) {
    var text = form.querySelector('[data-demo-text]');
    var audio = form.querySelector('[data-demo-audio]');
    var noLyrics = form.querySelector('[data-demo-no-lyrics]');
    var textOnly = form.querySelector('[data-demo-text-only]');
    var error = form.querySelector('[data-demo-error]');
    var submit = form.querySelector('[data-demo-submit]');
    var progress = form.querySelector('[data-demo-progress]');
    var progressBar = form.querySelector('[data-demo-progress-bar]');
    var progressValue = form.querySelector('[data-demo-progress-value]');
    var progressText = form.querySelector('[data-demo-progress-text]');
    var submitting = false;

    function sync() {
      if (noLyrics.checked) {
        text.value = '';
        text.disabled = true;
        textOnly.checked = false;
      } else {
        text.disabled = false;
      }
      if (textOnly.checked) {
        audio.value = '';
        audio.disabled = true;
        noLyrics.checked = false;
      } else {
        audio.disabled = false;
      }
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

    noLyrics.addEventListener('change', sync);
    textOnly.addEventListener('change', sync);
    sync();

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (submitting) return;

      sync();
      var hasText = !text.disabled && text.files.length === 1;
      var hasAudio = !audio.disabled && audio.files.length === 1;
      var message = '';
      if (!hasText && !hasAudio) message = 'Carica almeno il testo autoriale oppure il provino audio.';
      else if (!hasText && !noLyrics.checked) message = 'Se non alleghi il testo, dichiara che il provino non contiene testo.';
      else if (!hasAudio && !textOnly.checked) message = 'Se non alleghi l’audio, dichiara che il provino è soltanto un testo autoriale.';

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
      request.open((form.method || 'POST').toUpperCase(), form.action, true);
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
        window.trbDemoLastResponse = {
          status: request.status,
          url: request.responseURL,
          contentType: request.getResponseHeader('Content-Type') || '',
          body: request.responseText.slice(0, 1000)
        };
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
