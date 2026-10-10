(function () {
  'use strict';

  function isItaly(country) {
    return ['', 'italia', 'italy', 'it', 'ita'].indexOf((country || '').trim().toLowerCase()) !== -1;
  }

  function initAddress() {
    var postcode = document.querySelector('[data-trb-postcode]');
    if (!postcode || !window.trbArtistProfile) return;
    var city = document.querySelector('[data-trb-city]');
    var province = document.querySelector('[data-trb-province]');
    var country = document.querySelector('[data-trb-country]');
    var status = document.querySelector('[data-trb-postcode-status]');
    var internationalCity = document.querySelector('[data-trb-international-city]');
    var streetNumber = document.querySelector('[data-trb-street-number]');
    var lastLoaded = '';
    var requestVersion = 0;

    function setStatus(message, error) {
      status.textContent = message;
      status.classList.toggle('is-error', !!error);
    }

    function loadPostcode() {
      if (!isItaly(country.value)) {
        ++requestVersion;
        lastLoaded = '';
        postcode.setCustomValidity('');
        setStatus('Codice postale internazionale: conserva lettere, cifre, spazi e trattini. Se non previsto, lascia vuoto.', false);
        return;
      }
      var value = postcode.value.replace(/\D/g, '').slice(0, 5);
      postcode.value = value;
      if (value === lastLoaded && value.length === 5) return;
      var version = ++requestVersion;
      lastLoaded = '';
      if (value.length !== 5) {
        city.innerHTML = '<option value="">Inserisci prima il CAP</option>';
        city.disabled = true;
        province.value = '';
        postcode.setCustomValidity('Inserisci un CAP italiano di 5 cifre.');
        setStatus('Inserisci un CAP italiano di 5 cifre.', false);
        return;
      }
      city.disabled = true;
      postcode.setCustomValidity('Attendi la verifica del CAP.');
      setStatus('Verifica del CAP in corso…', false);
      var request = window.trbArtistProfile.lookupPostcode ? window.trbArtistProfile.lookupPostcode(value) : fetch(window.trbArtistProfile.postcodeEndpoint + value, {
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': window.trbArtistProfile.restNonce }
      }).then(function (response) {
        if (!response.ok) throw new Error('not-found');
        return response.json();
      });
      request.then(function (data) {
        if (version !== requestVersion || postcode.value !== value) return;
        if (!data.places || !data.places.length) throw new Error('not-found');
        var current = city.value;
        city.innerHTML = '';
        if (data.places.length > 1 && !data.places.some(function (place) { return place.city === current; })) {
          var placeholder = document.createElement('option');
          placeholder.value = ''; placeholder.textContent = 'Seleziona il Comune'; city.appendChild(placeholder);
        }
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
        postcode.setCustomValidity('');
        setStatus(data.places.length > 1 ? 'CAP valido: seleziona il Comune corretto.' : 'CAP verificato: Comune e provincia compilati automaticamente.', false);
      }).catch(function () {
        if (version !== requestVersion || postcode.value !== value) return;
        city.innerHTML = '<option value="">CAP non riconosciuto</option>';
        city.disabled = true;
        province.value = '';
        postcode.setCustomValidity('CAP non trovato. Controlla le 5 cifre.');
        setStatus('CAP non trovato. Controlla le 5 cifre prima di continuare.', true);
      });
    }

    function updateProvince() {
      var selected = city.options[city.selectedIndex];
      province.value = selected && selected.dataset.province ? selected.dataset.province : '';
    }

    function syncCountry(changed) {
      ++requestVersion;
      lastLoaded = '';
      var italian = isItaly(country.value);
      postcode.required = italian;
      if (italian) postcode.pattern = '[0-9]{5}';
      else postcode.removeAttribute('pattern');
      postcode.maxLength = italian ? 5 : 40;
      postcode.inputMode = italian ? 'numeric' : 'text';
      city.hidden = !italian;
      city.disabled = !italian;
      city.required = italian;
      if (internationalCity) {
        internationalCity.hidden = italian;
        internationalCity.disabled = italian;
        internationalCity.required = !italian;
      }
      province.readOnly = italian;
      province.required = italian;
      if (streetNumber) streetNumber.required = italian;
      if (changed) province.value = '';
      loadPostcode();
    }
    country.addEventListener('input', function () { syncCountry(true); });
    country.addEventListener('change', function () { syncCountry(true); });
    postcode.addEventListener('input', loadPostcode);
    postcode.addEventListener('blur', loadPostcode);
    city.addEventListener('change', updateProvince);
    syncCountry(false);
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
    var country = document.querySelector('[data-trb-birth-country]');
    var list = document.getElementById('trb-birthplace-options');
    var status = document.querySelector('[data-trb-birthplace-status]');
    var places = [];
    var timer;
    var requestVersion = 0;

    function foreignBirthplace() {
      if (!country || isItaly(country.value)) return false;
      input.setCustomValidity('');
      status.textContent = 'Scrivi la località di nascita estera. Provincia o regione sono facoltative se non previste.';
      status.classList.remove('is-error');
      return true;
    }

    function syncBirthCountry(changed) {
      ++requestVersion;
      clearTimeout(timer);
      places = [];
      list.innerHTML = '';
      if (changed) province.value = '';
      var italian = !country || isItaly(country.value);
      province.readOnly = italian;
      province.required = italian;
      if (!foreignBirthplace()) input.dispatchEvent(new Event('input'));
    }

    function selectPlace() {
      if (foreignBirthplace()) return;
      var value = input.value.toLocaleLowerCase('it');
      var match = places.find(function (place) { return (place.city + ' (' + place.province + ')').toLocaleLowerCase('it') === value || place.city.toLocaleLowerCase('it') === value; });
      if (match) input.value = match.city;
      province.value = match ? match.province : '';
      status.textContent = match ? 'Comune verificato nell’archivio italiano.' : 'Seleziona uno dei Comuni proposti.';
      status.classList.toggle('is-error', !match && input.value.length > 1);
      input.setCustomValidity(match ? '' : 'Seleziona un Comune valido tra quelli proposti.');
    }

    input.addEventListener('input', function () {
      var version = ++requestVersion;
      clearTimeout(timer);
      if (foreignBirthplace()) return;
      var selected = places.find(function (place) { return (place.city + ' (' + place.province + ')').toLocaleLowerCase('it') === input.value.toLocaleLowerCase('it'); });
      if (selected) {
        input.value = selected.city;
        province.value = selected.province;
        status.textContent = 'Comune verificato nell’archivio italiano.';
        status.classList.remove('is-error');
        input.setCustomValidity('');
        clearTimeout(timer);
        return;
      }
      province.value = '';
      input.setCustomValidity('Seleziona un Comune valido tra quelli proposti.');
      clearTimeout(timer);
      if (input.value.trim().length < 2) return;
      timer = setTimeout(function () {
        var search = input.value.trim();
        var request = window.trbArtistProfile.lookupMunicipalities ? window.trbArtistProfile.lookupMunicipalities(search) : fetch(window.trbArtistProfile.municipalityEndpoint + '?search=' + encodeURIComponent(search), {
          credentials: 'same-origin', headers: { 'X-WP-Nonce': window.trbArtistProfile.restNonce }
        }).then(function (response) { if (!response.ok) throw new Error('not-found'); return response.json(); });
        request.then(function (data) {
          if (version !== requestVersion) return;
          places = data.places || [];
          list.innerHTML = '';
          places.forEach(function (place) {
            var option = document.createElement('option');
            option.value = place.city + ' (' + place.province + ')';
            list.appendChild(option);
          });
          selectPlace();
        }).catch(function () {
          if (version !== requestVersion) return;
          status.textContent = 'Verifica del Comune non disponibile. Riprova tra poco.';
        });
      }, 180);
    });
    input.addEventListener('change', selectPlace);
    if (country) {
      country.addEventListener('input', function () { syncBirthCountry(true); });
      country.addEventListener('change', function () { syncBirthCountry(true); });
      syncBirthCountry(false);
    }
  }

  function initIdentityValidation() {
    var phone = document.querySelector('input[name="trb_artist_phone"], [data-trb-mobile]');
    var taxCode = document.querySelector('[data-trb-tax-code]');
    var documentNumber = document.querySelector('[data-trb-document-number]');
    var documentExpiry = document.querySelector('[data-trb-document-expiry]');
    var taxCountry = document.querySelector('[data-trb-tax-country]');
    var documentType = document.querySelector('[data-trb-document-type]');
    var noExpiry = document.querySelector('[data-trb-no-expiry]');
    var noExpiryLabel = document.querySelector('[data-trb-no-expiry-label]');
    function type() { return documentType ? documentType.value : 'cie'; }
    function indefinite() { return type() === 'foreign_identity' && noExpiry && noExpiry.checked; }
    function smsPhone(value) {
      var normalized = value.replace(/[\s.\-()]/g, '').replace(/^00/, '+');
      if (/^3\d{9}$/.test(normalized)) normalized = '+39' + normalized;
      if (normalized.indexOf('+39') === 0 && ! /^\+393\d{9}$/.test(normalized)) return '';
      return /^\+[1-9]\d{6,14}$/.test(normalized) ? normalized : '';
    }

    if (phone) {
      phone.addEventListener('input', function () {
        phone.setCustomValidity(smsPhone(phone.value) ? '' : 'Inserisci un cellulare SMS con prefisso internazionale e da 7 a 15 cifre complessive; per l’Italia usa +39 seguito dalle 10 cifre del cellulare.');
      });
      phone.addEventListener('blur', function () {
        var normalized = smsPhone(phone.value);
        if (normalized) phone.value = normalized;
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
        var italian = !taxCountry || isItaly(taxCountry.value);
        taxCode.minLength = italian ? 16 : 1;
        taxCode.maxLength = italian ? 16 : 200;
        if (!italian) {
          taxCode.setCustomValidity(taxCode.value.trim() ? '' : 'Inserisci l’identificativo fiscale della nazione indicata.');
          return;
        }
        taxCode.value = taxCode.value.toUpperCase().replace(/\s/g, '').slice(0, 16);
        taxCode.setCustomValidity(taxCode.value.length === 16 && validTaxCode(taxCode.value) ? '' : 'Controlla il codice fiscale: devono essere validi tutti i 16 caratteri, compresa la lettera finale.');
      });
      if (taxCountry) taxCountry.addEventListener('input', function () { taxCode.dispatchEvent(new Event('input')); });
      taxCode.dispatchEvent(new Event('input'));
    }

    if (documentNumber) {
      documentNumber.addEventListener('input', function () {
        if (type() !== 'cie') {
          documentNumber.maxLength = 200;
          documentNumber.removeAttribute('pattern');
          documentNumber.setCustomValidity(documentNumber.value.trim() ? '' : 'Inserisci il numero originale del documento.');
          return;
        }
        documentNumber.maxLength = 9;
        documentNumber.pattern = '[A-Za-z]{2}[0-9]{5}[A-Za-z]{2}';
        documentNumber.value = documentNumber.value.toUpperCase().replace(/[\s-]/g, '').slice(0, 9);
        documentNumber.setCustomValidity(/^[A-Z]{2}[0-9]{5}[A-Z]{2}$/.test(documentNumber.value) ? '' : 'Inserisci il numero CIE nel formato corretto: 2 lettere, 5 cifre e 2 lettere (es. CA12345AB).');
      });
      documentNumber.dispatchEvent(new Event('input'));
    }

    function syncDocument() {
      if (noExpiry) {
        noExpiry.disabled = type() !== 'foreign_identity';
        if (noExpiry.disabled) noExpiry.checked = false;
      }
      if (noExpiryLabel) noExpiryLabel.hidden = type() !== 'foreign_identity';
      if (documentNumber) documentNumber.dispatchEvent(new Event('input'));
      if (documentExpiry) documentExpiry.dispatchEvent(new Event('change'));
    }
    if (documentType) documentType.addEventListener('change', syncDocument);
    if (noExpiry) noExpiry.addEventListener('change', syncDocument);

    if (documentExpiry) {
      documentExpiry.addEventListener('change', function () {
        var today = new Date();
        var localToday = [today.getFullYear(), String(today.getMonth() + 1).padStart(2, '0'), String(today.getDate()).padStart(2, '0')].join('-');
        var maximum = new Date(today.getFullYear() + 10, today.getMonth(), today.getDate());
        var localMaximum = [maximum.getFullYear(), String(maximum.getMonth() + 1).padStart(2, '0'), String(maximum.getDate()).padStart(2, '0')].join('-');
        documentExpiry.disabled = !!indefinite();
        documentExpiry.required = !indefinite();
        documentExpiry.max = type() === 'cie' ? localMaximum : '';
        documentExpiry.setCustomValidity(indefinite() || (documentExpiry.value && documentExpiry.value >= localToday && (type() !== 'cie' || documentExpiry.value <= localMaximum)) ? '' : 'Inserisci una scadenza non passata. Il limite di 10 anni si applica alla CIE italiana.');
      });
      documentExpiry.dispatchEvent(new Event('change'));
    }
    syncDocument();
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

        var formData = new FormData(form);
        var hasFiles = Array.prototype.some.call(form.querySelectorAll('input[type="file"]'), function (input) {
          return input.files && input.files.length > 0;
        });
        var percent = panel.querySelector('[data-trb-upload-percent]');
        var bar = panel.querySelector('.trb-portal__upload-progress-track span');
        var status = panel.querySelector('[data-trb-upload-status]');
        var xhr = new XMLHttpRequest();
        var submitUrl = window.trbArtistProfile && window.trbArtistProfile.ajaxUrl ? window.trbArtistProfile.ajaxUrl : form.action;
        xhr.open('POST', submitUrl, true);
        xhr.withCredentials = true;
        xhr.timeout = 300000;
        if (!hasFiles) {
          status.textContent = 'Salvataggio dei dati del profilo…';
        }
        xhr.upload.addEventListener('progress', function (uploadEvent) {
          if (!uploadEvent.lengthComputable) return;
          var uploadComplete = uploadEvent.loaded >= uploadEvent.total;
          var value = uploadComplete ? 99 : Math.min(98, Math.round((uploadEvent.loaded / uploadEvent.total) * 100));
          percent.textContent = value + '%';
          bar.style.width = value + '%';
          status.textContent = hasFiles
            ? (uploadComplete ? 'File caricati. Salvataggio del profilo…' : 'Caricamento dei file in corso…')
            : 'Salvataggio dei dati del profilo…';
        });
        xhr.addEventListener('load', function () {
          var responseUrl = xhr.responseURL || '';
          var profileResult = '';
          try {
            profileResult = new URL(responseUrl, window.location.href).searchParams.get('trb_profile') || '';
          } catch (ignored) {}
          if (xhr.status >= 200 && xhr.status < 400 && profileResult === 'saved') {
            percent.textContent = '100%';
            bar.style.width = '100%';
            status.textContent = 'Salvataggio completato. Aggiornamento del profilo…';
            window.location.assign(responseUrl);
            return;
          }
          submitting = false;
          panel.classList.add('is-error');
          var errors = {
            file_upload_failed: 'Caricamento non completato. I dati e i file precedenti sono conservati. Controlla formato, dimensioni e limite di sei foto, quindi riprova.',
            profile_busy: 'Un salvataggio è già in corso. Attendi il completamento e riprova.',
            profile_save_failed: 'Il server non ha confermato il salvataggio. Controlla il profilo prima di riprovare.',
            storage_waiting: 'Spazio temporaneamente insufficiente. I dati e i file precedenti sono conservati: riprova più tardi.',
            bio_invalid: 'Biografia non acquisita: usa TXT, DOCX, ODT o RTF, massimo 5 MB.',
            bio_required: 'Allega una biografia artistica prima di salvare.',
            invalid_address: 'Controlla residenza, nazione e indirizzo. Per l’Italia sono richiesti CAP, Comune e numero civico.',
            invalid_birth_date: 'Controlla la data di nascita: deve essere una data valida e non futura.',
            invalid_birthplace: 'Controlla luogo e nazione di nascita.',
            invalid_phone: 'Controlla il cellulare e il prefisso internazionale.',
            invalid_tax_code: 'Controlla identificativo fiscale e nazione fiscale.',
            invalid_document_number: 'Controlla tipo e numero del documento.',
            invalid_document_expiry: 'Controlla la scadenza del documento.',
            invalid_account_name: 'Compila nome e cognome anagrafici.',
            artist_name_taken: 'Questo nome d’arte è già associato a un altro account.'
          };
          status.textContent = errors[profileResult] || (xhr.status >= 200 && xhr.status < 400
            ? 'La pratica non è stata registrata. I dati compilati sono ancora presenti nel modulo: riprova senza ricaricare la pagina.'
            : 'Il server non ha confermato il salvataggio. Controlla il profilo prima di riprovare.');
          if (button) { button.disabled = false; button.textContent = originalLabel; }
        });
        function uncertainResult() {
          submitting = false;
          panel.classList.add('is-error');
          status.textContent = 'Connessione interrotta: non è possibile confermare il salvataggio. Controlla il profilo prima di riprovare.';
          if (button) { button.disabled = false; button.textContent = originalLabel; }
        }
        xhr.addEventListener('error', uncertainResult);
        xhr.addEventListener('timeout', uncertainResult);
        xhr.send(formData);
      });
    });
  }

  var initialized = false;
  window.trbArtistProfileFields = { refresh: function () {
    if (!initialized) return;
    ['[data-trb-postcode]', '[data-trb-birthplace]', '[data-trb-tax-code]', 'input[name="trb_artist_phone"], [data-trb-mobile]', '[data-trb-document-number]'].forEach(function (selector) {
      var input = document.querySelector(selector); if (input) input.dispatchEvent(new Event('input'));
    });
    var expiry = document.querySelector('[data-trb-document-expiry]'); if (expiry) expiry.dispatchEvent(new Event('change'));
  }};
  document.addEventListener('DOMContentLoaded', function () {
    initAddress();
    initBirthplace();
    initIdentityValidation();
    initPlatforms();
    initProfileFinder();
    initProfileUploadProgress();
    initialized = true;
    window.trbArtistProfileFields.refresh();
  });
}());
