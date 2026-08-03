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
        if (request.status >= 200 && request.status < 400) {
          setProgress(100, 'Invio completato. Apertura della conferma…');
          window.location.assign(request.responseURL || form.action);
          return;
        }
        restore('Invio non completato. Controlla la connessione e riprova.');
      });

      request.addEventListener('error', function () {
        restore('Connessione interrotta durante il caricamento. Nessun nuovo tentativo è stato inviato: riprova una sola volta.');
      });

      request.addEventListener('timeout', function () {
        restore('Il caricamento sta impiegando troppo tempo. Verifica la connessione prima di riprovare.');
      });

      request.send(new FormData(form));
    });
  });
});
