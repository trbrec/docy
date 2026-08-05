(function () {
  'use strict';
  var activePlayer = null;
  var saveTimer = null;

  function api(path, body) {
    return fetch(window.trbVideoAcademy.restRoot + path, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.trbVideoAcademy.restNonce },
      body: JSON.stringify(body)
    }).then(function (response) { return response.json(); });
  }

  function loadYouTubeApi() {
    if (window.YT && window.YT.Player) return Promise.resolve();
    if (window.trbYouTubeApiPromise) return window.trbYouTubeApiPromise;
    window.trbYouTubeApiPromise = new Promise(function (resolve) {
      var previous = window.onYouTubeIframeAPIReady;
      window.onYouTubeIframeAPIReady = function () { if (previous) previous(); resolve(); };
      var script = document.createElement('script');
      script.src = 'https://www.youtube.com/iframe_api';
      document.head.appendChild(script);
    });
    return window.trbYouTubeApiPromise;
  }

  function saveProgress(lesson, manual) {
    if (!activePlayer || typeof activePlayer.getDuration !== 'function') return Promise.resolve(null);
    var duration = activePlayer.getDuration() || 0;
    var position = activePlayer.getCurrentTime() || 0;
    return api('video-progress', { lesson_id: lesson.dataset.lessonId, position: position, duration: duration, percentage: duration ? position / duration * 100 : 0, manual: !!manual });
  }

  function startPlayer(lesson) {
    var holder = lesson.querySelector('[data-video-player]');
    var videoId = lesson.dataset.youtube;
    holder.innerHTML = '<div class="trb-video__iframe" id="trb-player-' + lesson.dataset.lessonId + '"></div>';
    loadYouTubeApi().then(function () {
      activePlayer = new window.YT.Player('trb-player-' + lesson.dataset.lessonId, {
        videoId: videoId,
        host: 'https://www.youtube-nocookie.com',
        playerVars: { rel: 0, playsinline: 1, start: Math.floor(Number(lesson.dataset.lastPosition || 0)) },
        events: { onStateChange: function (event) {
          if (event.data === window.YT.PlayerState.PLAYING) {
            saveProgress(lesson, false);
            clearInterval(saveTimer);
            saveTimer = setInterval(function () { saveProgress(lesson, false); }, 10000);
          } else if (event.data === window.YT.PlayerState.PAUSED || event.data === window.YT.PlayerState.ENDED) {
            saveProgress(lesson, false);
            clearInterval(saveTimer);
          }
        } }
      });
    });
  }

  function initVideos() {
    var dialog = document.querySelector('[data-video-dialog]');
    if (!dialog) return;
    var content = dialog.querySelector('[data-video-dialog-content]');
    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-video-card]'));
    var groups = Array.prototype.slice.call(document.querySelectorAll('[data-video-category-group]'));
    var search = document.querySelector('[data-video-search]');
    var state = document.querySelector('[data-video-state]');

    function markCompleted(lesson) {
      var lessonId = lesson.dataset.lessonId;
      var card = document.querySelector('[data-video-open="' + lessonId + '"]').closest('[data-video-card]');
      var cardIndex = cards.indexOf(card);
      var nextCard = cards[cardIndex + 1];
      var completion = lesson.querySelector('[data-video-completion]');
      card.dataset.state = 'Completato';
      card.querySelector('.trb-video__meta').textContent = card.querySelector('.trb-video__meta').textContent.replace(/Da iniziare|In corso/, 'Completato');
      card.querySelector('.trb-video__open > b').textContent = 'Rivedi';
      if (completion) {
        completion.textContent = nextCard ? 'Lezione completata. Prossima consigliata: ' + nextCard.querySelector('h3').textContent + '.' : 'Percorso completato: puoi rivedere qualsiasi lezione quando vuoi.';
        completion.hidden = false;
      }
      var completed = cards.filter(function (item) { return item.dataset.state === 'Completato'; }).length;
      var progressLabel = document.querySelector('.trb-video__progress strong');
      var progressBar = document.querySelector('.trb-video__progress i');
      if (progressLabel) progressLabel.textContent = completed + ' lezioni completate su ' + cards.length;
      if (progressBar) progressBar.style.width = Math.round(completed / cards.length * 100) + '%';
    }

    function filter() {
      var query = (search.value || '').toLocaleLowerCase('it');
      cards.forEach(function (card) {
        card.hidden = (!!state.value && card.dataset.state !== state.value) || (!!query && card.dataset.search.indexOf(query) === -1);
      });
      groups.forEach(function (group) {
        var hasResults = Array.prototype.some.call(group.querySelectorAll('[data-video-card]'), function (card) { return !card.hidden; });
        group.hidden = !hasResults;
        if (query || state.value) group.open = hasResults;
      });
    }

    search.addEventListener('input', filter);
    state.addEventListener('change', filter);

    document.addEventListener('click', function (event) {
      var open = event.target.closest('[data-video-open]');
      if (open) {
        var template = document.getElementById('trb-video-' + open.dataset.videoOpen);
        content.innerHTML = template.innerHTML;
        dialog.showModal();
      }
      if (event.target.closest('[data-video-close]')) dialog.close();
      var play = event.target.closest('[data-video-play]');
      if (play) startPlayer(play.closest('[data-lesson-id]'));
      var complete = event.target.closest('[data-video-complete]');
      if (complete) {
        var lesson = complete.closest('[data-lesson-id]');
        var request = activePlayer ? saveProgress(lesson, true) : api('video-progress', { lesson_id: lesson.dataset.lessonId, position: 0, duration: 0, percentage: 100, manual: true });
        complete.disabled = true;
        complete.textContent = 'Salvataggio…';
        request.then(function (response) {
          if (!response || response.success !== true) throw new Error('save-failed');
          complete.textContent = 'Lezione completata';
          markCompleted(lesson);
        }).catch(function () {
          complete.disabled = false;
          complete.textContent = 'Riprova il completamento';
        });
      }
    });
    dialog.addEventListener('close', function () { clearInterval(saveTimer); if (activePlayer && activePlayer.destroy) activePlayer.destroy(); activePlayer = null; content.innerHTML = ''; });
  }

  document.addEventListener('DOMContentLoaded', initVideos);
}());
