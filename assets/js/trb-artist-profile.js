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

  document.addEventListener('DOMContentLoaded', function () {
    initAddress();
    initPlatforms();
  });
}());
