(function () {
  'use strict';

  function readFourCC(view, offset) {
    return String.fromCharCode(view.getUint8(offset), view.getUint8(offset + 1), view.getUint8(offset + 2), view.getUint8(offset + 3));
  }

  function parseWav(file) {
    return file.slice(0, Math.min(file.size, 2 * 1024 * 1024)).arrayBuffer().then(function (buffer) {
      var view = new DataView(buffer);
      if (view.byteLength < 20 || readFourCC(view, 0) !== 'RIFF' || readFourCC(view, 8) !== 'WAVE') throw new Error('invalid_wav');
      var offset = 12;
      var spec = null;
      var dataSize = null;
      while (offset + 8 <= view.byteLength) {
        var id = readFourCC(view, offset);
        var size = view.getUint32(offset + 4, true);
        offset += 8;
        if (id === 'fmt ' && size >= 16 && offset + 16 <= view.byteLength) {
          spec = {
            format: view.getUint16(offset, true),
            channels: view.getUint16(offset + 2, true),
            sampleRate: view.getUint32(offset + 4, true),
            byteRate: view.getUint32(offset + 8, true),
            bitDepth: view.getUint16(offset + 14, true)
          };
        } else if (id === 'data') {
          dataSize = size;
          if (spec) break;
        }
        var next = offset + size + (size % 2);
        if (next <= offset || next > view.byteLength) break;
        offset = next;
      }
      if (!spec || dataSize === null || spec.byteRate <= 0 || [1, 65534].indexOf(spec.format) === -1) throw new Error('invalid_wav');
      spec.duration = dataSize / spec.byteRate;
      return spec;
    });
  }

  function durationLabel(seconds) {
    var rounded = Math.round(seconds);
    return String(Math.floor(rounded / 60)).padStart(2, '0') + ':' + String(rounded % 60).padStart(2, '0');
  }

  function validateTrackAudio(track) {
    var input = track.querySelector('input[name^="trb_track_audio"]');
    var message = track.querySelector('[data-audio-duration-check]');
    var minutes = track.querySelector('[name$="[duration_minutes]"]');
    var seconds = track.querySelector('[name$="[duration_seconds]"]');
    if (!input || !input.files.length) return Promise.resolve(true);

    var inspect = input._trbWavFile === input.files[0] && input._trbWavSpec
      ? Promise.resolve(input._trbWavSpec)
      : parseWav(input.files[0]).then(function (spec) { input._trbWavFile = input.files[0]; input._trbWavSpec = spec; return spec; });

    return inspect.then(function (spec) {
      var declared = minutes.value !== '' && seconds.value !== '' ? Number(minutes.value) * 60 + Number(seconds.value) : null;
      var technical = spec.sampleRate >= 44100 && spec.sampleRate <= 96000 && spec.bitDepth >= 16 && spec.bitDepth <= 24;
      var matches = declared === null || Math.abs(spec.duration - declared) <= 1;
      var error = !technical ? 'Il WAV non rispetta i requisiti tecnici minimi.' : (!matches ? 'La durata indicata differisce dal WAV di oltre 1 secondo.' : '');
      input.setCustomValidity(error);
      message.classList.toggle('is-error', !!error);
      message.textContent = error || ('Durata WAV rilevata: ' + durationLabel(spec.duration) + (declared === null ? '. Indica la stessa durata nei campi minuti e secondi.' : ' · corrispondenza verificata.'));
      return !error;
    }).catch(function () {
      input.setCustomValidity('Il file non è un WAV PCM valido o non è leggibile.');
      message.classList.add('is-error');
      message.textContent = 'Impossibile leggere le caratteristiche e la durata del WAV.';
      return false;
    });
  }

  function createProgress(form, button, title) {
    var panel = document.createElement('div');
    panel.className = 'trb-portal__upload-progress';
    panel.setAttribute('role', 'status');
    panel.innerHTML = '<div><strong>' + title + '</strong><span data-trb-upload-percent>0%</span></div><div class="trb-portal__upload-progress-track"><span></span></div><small data-trb-upload-status>Preparazione dei file… Non chiudere la pagina e non inviare nuovamente il modulo.</small>';
    form.insertBefore(panel, button || null);
    return panel;
  }

  function sendWithProgress(form, options) {
    if (form.dataset.trbSubmitting === '1') return;
    form.dataset.trbSubmitting = '1';
    var button = form.querySelector('button[type="submit"]');
    var originalLabel = button ? button.textContent : '';
    var panel = createProgress(form, button, options.title);
    var percent = panel.querySelector('[data-trb-upload-percent]');
    var bar = panel.querySelector('.trb-portal__upload-progress-track span');
    var status = panel.querySelector('[data-trb-upload-status]');
    if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); button.textContent = 'Caricamento in corso…'; }

    function restore(message) {
      form.dataset.trbSubmitting = '0';
      panel.classList.add('is-error');
      status.textContent = message;
      if (button) { button.disabled = false; button.removeAttribute('aria-busy'); button.textContent = originalLabel; }
    }

    var xhr = new XMLHttpRequest();
    xhr.open((form.method || 'POST').toUpperCase(), form.action, true);
    xhr.withCredentials = true;
    xhr.timeout = 2 * 60 * 60 * 1000;
    xhr.upload.addEventListener('progress', function (event) {
      if (!event.lengthComputable) { status.textContent = 'Caricamento dei file in corso…'; return; }
      var value = Math.min(99, Math.round(event.loaded / event.total * 100));
      percent.textContent = value + '%';
      bar.style.width = value + '%';
      status.textContent = value < 99 ? 'Caricamento dei file in corso…' : 'File caricati. Verifica e registrazione della pratica…';
    });
    xhr.addEventListener('load', function () {
      if (xhr.status >= 200 && xhr.status < 400) {
        percent.textContent = '100%';
        bar.style.width = '100%';
        status.textContent = 'Caricamento completato. Aggiornamento della pratica…';
        window.location.assign(xhr.responseURL || window.location.href);
        return;
      }
      restore('Il server non ha completato il caricamento. Nessun secondo invio è stato avviato: controlla i file e riprova una sola volta.');
    });
    xhr.addEventListener('error', function () { restore('Connessione interrotta durante il caricamento. Controlla la rete prima di riprovare.'); });
    xhr.addEventListener('timeout', function () { restore('Il caricamento ha superato il tempo massimo. Verifica la connessione prima di riprovare.'); });
    xhr.send(new FormData(form));
  }

  document.addEventListener('DOMContentLoaded', function () {
    var releaseForm = document.querySelector('[data-release-form]');
    if (releaseForm) {
      releaseForm.addEventListener('change', function (event) {
        var track = event.target.closest('[data-track]');
        if (track && (event.target.matches('input[name^="trb_track_audio"]') || event.target.matches('[name$="[duration_minutes]"]') || event.target.matches('[name$="[duration_seconds]"]'))) validateTrackAudio(track);
      });
      releaseForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (releaseForm.dataset.trbSubmitting === '1') return;
        Promise.all(Array.prototype.map.call(releaseForm.querySelectorAll('[data-track]'), validateTrackAudio)).then(function (results) {
          if (results.indexOf(false) !== -1 || !releaseForm.reportValidity()) {
            var invalid = releaseForm.querySelector(':invalid');
            if (invalid) invalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
          }
          sendWithProgress(releaseForm, { title: 'Caricamento della release' });
        });
      });
    }

    document.querySelectorAll('.trb-release-file form').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!form.reportValidity()) return;
        sendWithProgress(form, { title: 'Sostituzione del file' });
      });
    });
  });
}());
