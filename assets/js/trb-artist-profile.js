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
      function sync() {
        url.disabled = false;
        url.readOnly = choice.checked;
        url.required = !choice.checked;
        if (choice.checked) url.value = '';
      }
      choice.addEventListener('change', sync);
      sync();
    });
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

  document.addEventListener('DOMContentLoaded', function () {
    initAddress();
    initBirthplace();
    initIdentityValidation();
    initPlatforms();
  });
}());
