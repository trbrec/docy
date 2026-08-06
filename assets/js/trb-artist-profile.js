(function () {
  'use strict';

  function initAddress() {
    var postcode = document.querySelector('[data-trb-postcode]');
    if (!postcode || !window.trbArtistProfile) return;
    var city = document.querySelector('[data-trb-city]');
    var province = document.querySelector('[data-trb-province]');
    var country = document.querySelector('[data-trb-country]');
    var status = document.querySelector('[data-trb-postcode-status]');
    var lastLoaded = '';

    function setStatus(message, error) {
      status.textContent = message;
      status.classList.toggle('is-error', !!error);
    }

    function loadPostcode() {
      var value = postcode.value.replace(/\D/g, '').slice(0, 5);
      postcode.value = value;
      if (value.length !== 5 || value === lastLoaded) return;
      city.disabled = true;
      setStatus('Verifica del CAP in corso…', false);
      fetch(window.trbArtistProfile.postcodeEndpoint + value, {
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': window.trbArtistProfile.restNonce }
      }).then(function (response) {
        if (!response.ok) throw new Error('not-found');
        return response.json();
      }).then(function (data) {
        var current = city.value;
        city.innerHTML = '';
        data.places.forEach(function (place) {
          var option = document.createElement('option');
          option.value = place.city;
          option.textContent = place.city;
          option.dataset.province = place.province;
          if (place.city === current) option.selected = true;
          city.appendChild(option);
        });
        city.disabled = false;
        country.value = data.country || 'Italia';
        updateProvince();
        lastLoaded = value;
        setStatus(data.places.length > 1 ? 'CAP valido: seleziona il Comune corretto.' : 'CAP verificato: Comune e provincia compilati automaticamente.', false);
      }).catch(function () {
        city.innerHTML = '<option value="">CAP non riconosciuto</option>';
        city.disabled = true;
        province.value = '';
        setStatus('CAP non trovato. Controlla le 5 cifre prima di continuare.', true);
      });
    }

    function updateProvince() {
      var selected = city.options[city.selectedIndex];
      if (selected && selected.dataset.province) province.value = selected.dataset.province;
    }

    postcode.addEventListener('input', loadPostcode);
    postcode.addEventListener('blur', loadPostcode);
    city.addEventListener('change', updateProvince);
    if (postcode.value.replace(/\D/g, '').length === 5) loadPostcode();
  }

  function initPlatforms() {
    document.querySelectorAll('[data-trb-platform]').forEach(function (field) {
      var url = field.querySelector('[data-trb-platform-url]');
      var choice = field.querySelector('[data-trb-platform-choice]');
      var required = field.dataset.trbRequired !== '0';
      function sync() {
        url.disabled = false;
        url.readOnly = choice.checked;
        url.required = required && !choice.checked;
        if (choice.checked) url.value = '';
      }
      choice.addEventListener('change', sync);
      sync();
    });
  }

  function initProfileFinder() {
    var finder = document.querySelector('[data-trb-profile-finder]');
    if (!finder) return;
    var input = finder.querySelector('[data-trb-profile-search]');
    var spotify = finder.querySelector('[data-trb-search-spotify]');
    var apple = finder.querySelector('[data-trb-search-apple]');
    var status = finder.querySelector('[data-trb-profile-search-status]');

    function updateLinks() {
      var name = input.value.trim();
      var valid = name.length > 0;
      spotify.href = valid ? 'https://open.spotify.com/search/' + encodeURIComponent(name) : '#';
      apple.href = valid ? 'https://music.apple.com/it/search?term=' + encodeURIComponent(name) : '#';
      spotify.setAttribute('aria-disabled', valid ? 'false' : 'true');
      apple.setAttribute('aria-disabled', valid ? 'false' : 'true');
      status.textContent = valid ? 'Apri i risultati, identifica il profilo corretto e copia il suo indirizzo.' : 'Scrivi prima il nome d’arte da cercare.';
    }

    [spotify, apple].forEach(function (link) {
      link.addEventListener('click', function (event) {
        if (!input.value.trim()) {
          event.preventDefault();
          input.focus();
        }
      });
    });
    input.addEventListener('input', updateLinks);
    updateLinks();
  }

  function initBirthplace() {
    var input = document.querySelector('[data-trb-birthplace]');
    if (!input || !window.trbArtistProfile) return;
    var province = document.querySelector('[data-trb-birth-province]');
    var list = document.getElementById('trb-birthplace-options');
    var status = document.querySelector('[data-trb-birthplace-status]');
    var places = [];
    var timer;

    function selectPlace() {
      var value = input.value.toLocaleLowerCase('it');
      var match = places.find(function (place) { return (place.city + ' (' + place.province + ')').toLocaleLowerCase('it') === value || place.city.toLocaleLowerCase('it') === value; });
      if (match) input.value = match.city;
      province.value = match ? match.province : '';
      status.textContent = match ? 'Comune verificato nell’archivio italiano.' : 'Seleziona uno dei Comuni proposti.';
      status.classList.toggle('is-error', !match && input.value.length > 1);
    }

    input.addEventListener('input', function () {
      var selected = places.find(function (place) { return (place.city + ' (' + place.province + ')').toLocaleLowerCase('it') === input.value.toLocaleLowerCase('it'); });
      if (selected) {
        input.value = selected.city;
        province.value = selected.province;
        status.textContent = 'Comune verificato nell’archivio italiano.';
        status.classList.remove('is-error');
        clearTimeout(timer);
        return;
      }
      province.value = '';
      clearTimeout(timer);
      if (input.value.trim().length < 2) return;
      timer = setTimeout(function () {
        fetch(window.trbArtistProfile.municipalityEndpoint + '?search=' + encodeURIComponent(input.value.trim()), {
          credentials: 'same-origin', headers: { 'X-WP-Nonce': window.trbArtistProfile.restNonce }
        }).then(function (response) { return response.json(); }).then(function (data) {
          places = data.places || [];
          list.innerHTML = '';
          places.forEach(function (place) {
            var option = document.createElement('option');
            option.value = place.city + ' (' + place.province + ')';
            list.appendChild(option);
          });
          selectPlace();
        });
      }, 180);
    });
    input.addEventListener('change', selectPlace);
  }

  function initIdentityValidation() {
    var phone = document.querySelector('input[name="trb_artist_phone"]');
    var taxCode = document.querySelector('[data-trb-tax-code]');

    if (phone) {
      phone.addEventListener('input', function () {
        var normalized = phone.value.replace(/[\s.\-()]/g, '').replace(/^0039/, '+39');
        phone.setCustomValidity(/^(?:\+39)?3\d{9}$/.test(normalized) ? '' : 'Inserisci un cellulare italiano valido: 10 cifre con iniziale 3; +39 è facoltativo.');
      });
      phone.addEventListener('blur', function () {
        var normalized = phone.value.replace(/[\s.\-()]/g, '').replace(/^0039/, '+39');
        if (/^(?:\+39)?3\d{9}$/.test(normalized)) phone.value = normalized.indexOf('+39') === 0 ? normalized : '+39' + normalized;
      });
      phone.dispatchEvent(new Event('input'));
    }

    function validTaxCode(value) {
      var code = value.toUpperCase().replace(/\s/g, '');
      if (!/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/.test(code)) return false;
      var odd = {0:1,1:0,2:5,3:7,4:9,5:13,6:15,7:17,8:19,9:21,A:1,B:0,C:5,D:7,E:9,F:13,G:15,H:17,I:19,J:21,K:2,L:4,M:18,N:20,O:11,P:3,Q:6,R:8,S:12,T:14,U:16,V:10,W:22,X:25,Y:24,Z:23};
      var sum = 0;
      for (var index = 0; index < 15; index += 1) {
        var character = code.charAt(index);
        sum += index % 2 === 0 ? odd[character] : (/[0-9]/.test(character) ? Number(character) : character.charCodeAt(0) - 65);
      }
      return String.fromCharCode(65 + (sum % 26)) === code.charAt(15);
    }

    if (taxCode) {
      taxCode.addEventListener('input', function () {
        taxCode.value = taxCode.value.toUpperCase().replace(/\s/g, '').slice(0, 16);
        taxCode.setCustomValidity(taxCode.value.length === 16 && validTaxCode(taxCode.value) ? '' : 'Controlla il codice fiscale: devono essere validi tutti i 16 caratteri, compresa la lettera finale.');
      });
      taxCode.dispatchEvent(new Event('input'));
    }
  }

  function initProfileUploadProgress() {
    document.querySelectorAll('.trb-portal__profile-form').forEach(function (form) {
      var submitting = false;
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (submitting || !form.reportValidity()) return;
        submitting = true;

        var button = form.querySelector('button[type="submit"]');
        var originalLabel = button ? button.textContent : '';
        var panel = document.createElement('div');
        panel.className = 'trb-portal__upload-progress';
        panel.setAttribute('role', 'status');
        panel.innerHTML = '<div><strong>Caricamento e salvataggio</strong><span data-trb-upload-percent>0%</span></div><div class="trb-portal__upload-progress-track"><span></span></div><small data-trb-upload-status>Preparazione dei file… Non chiudere la pagina e non inviare nuovamente il modulo.</small>';
        if (button) {
          button.disabled = true;
          button.textContent = 'Caricamento in corso…';
          form.insertBefore(panel, button);
        } else {
          form.appendChild(panel);
        }

        var percent = panel.querySelector('[data-trb-upload-percent]');
        var bar = panel.querySelector('.trb-portal__upload-progress-track span');
        var status = panel.querySelector('[data-trb-upload-status]');
        var xhr = new XMLHttpRequest();
        var submitUrl = window.trbArtistProfile && window.trbArtistProfile.ajaxUrl ? window.trbArtistProfile.ajaxUrl : form.action;
        xhr.open('POST', submitUrl, true);
        xhr.withCredentials = true;
        xhr.upload.addEventListener('progress', function (uploadEvent) {
          if (!uploadEvent.lengthComputable) return;
          var value = Math.min(99, Math.round((uploadEvent.loaded / uploadEvent.total) * 100));
          percent.textContent = value + '%';
          bar.style.width = value + '%';
          status.textContent = value < 100 ? 'Caricamento dei file in corso…' : 'File caricati. Salvataggio del profilo…';
        });
        xhr.addEventListener('load', function () {
          var responseUrl = xhr.responseURL || '';
          var profileResult = '';
          try {
            profileResult = new URL(responseUrl, window.location.href).searchParams.get('trb_profile') || '';
          } catch (ignored) {}
          if (xhr.status >= 200 && xhr.status < 400 && profileResult) {
            percent.textContent = '100%';
            bar.style.width = '100%';
            status.textContent = 'Salvataggio completato. Aggiornamento del profilo…';
            window.location.assign(responseUrl);
            return;
          }
          submitting = false;
          panel.classList.add('is-error');
          status.textContent = xhr.status >= 200 && xhr.status < 400
            ? 'La pratica non è stata registrata. I dati compilati sono ancora presenti nel modulo: riprova senza ricaricare la pagina.'
            : 'Il server non ha completato il salvataggio. Riprova una sola volta.';
          if (button) { button.disabled = false; button.textContent = originalLabel; }
        });
        xhr.addEventListener('error', function () {
          submitting = false;
          panel.classList.add('is-error');
          status.textContent = 'Connessione interrotta. Nessun nuovo invio è stato avviato: controlla la rete e riprova.';
          if (button) { button.disabled = false; button.textContent = originalLabel; }
        });
        xhr.send(new FormData(form));
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initAddress();
    initBirthplace();
    initIdentityValidation();
    initPlatforms();
    initProfileFinder();
    initProfileUploadProgress();
  });
}());
