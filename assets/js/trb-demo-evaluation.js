document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-demo-form]').forEach(function (form) {
    var text = form.querySelector('[data-demo-text]');
    var audio = form.querySelector('[data-demo-audio]');
    var noLyrics = form.querySelector('[data-demo-no-lyrics]');
    var textOnly = form.querySelector('[data-demo-text-only]');
    var error = form.querySelector('[data-demo-error]');

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

    noLyrics.addEventListener('change', sync);
    textOnly.addEventListener('change', sync);
    sync();

    form.addEventListener('submit', function (event) {
      sync();
      var hasText = !text.disabled && text.files.length === 1;
      var hasAudio = !audio.disabled && audio.files.length === 1;
      var message = '';
      if (!hasText && !hasAudio) message = 'Carica almeno il testo autoriale oppure il provino audio.';
      else if (!hasText && !noLyrics.checked) message = 'Se non alleghi il testo, dichiara che il provino non contiene testo.';
      else if (!hasAudio && !textOnly.checked) message = 'Se non alleghi l’audio, dichiara che il provino è soltanto un testo autoriale.';
      if (message) {
        event.preventDefault();
        error.textContent = message;
        error.hidden = false;
        error.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else {
        error.hidden = true;
      }
    });
  });
});
