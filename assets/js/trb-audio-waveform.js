(function () {
	'use strict';

	var players = [];

	function formatTime(seconds) {
		seconds = Number.isFinite(seconds) ? Math.max(0, Math.round(seconds)) : 0;
		var hours = Math.floor(seconds / 3600);
		var minutes = Math.floor((seconds % 3600) / 60);
		var remainder = seconds % 60;
		return (hours ? String(hours).padStart(2, '0') + ':' : '') + String(minutes).padStart(2, '0') + ':' + String(remainder).padStart(2, '0');
	}

	function normalizePeaks(value) {
		try {
			var parsed = typeof value === 'string' ? JSON.parse(value || '[]') : value;
			return Array.isArray(parsed) ? parsed.map(function (peak) { return Math.min(1, Math.max(0, Number(peak) || 0)); }) : [];
		} catch (error) {
			return [];
		}
	}

	function reducedPeaks(peaks, count) {
		if (!peaks.length || count <= 0) return [];
		var output = [];
		for (var index = 0; index < count; index++) {
			var start = Math.floor(index * peaks.length / count);
			var end = Math.max(start + 1, Math.floor((index + 1) * peaks.length / count));
			var maximum = 0;
			for (var sample = start; sample < end && sample < peaks.length; sample++) maximum = Math.max(maximum, peaks[sample]);
			output.push(maximum);
		}
		return output;
	}

	function draw(player) {
		var canvas = player.canvas;
		var bounds = player.seek.getBoundingClientRect();
		if (!bounds.width) return;
		var ratio = window.devicePixelRatio || 1;
		var width = Math.max(1, Math.round(bounds.width * ratio));
		var height = Math.max(1, Math.round(bounds.height * ratio));
		if (canvas.width !== width || canvas.height !== height) {
			canvas.width = width;
			canvas.height = height;
		}
		var context = canvas.getContext('2d');
		context.clearRect(0, 0, width, height);
		var barWidth = Math.max(2 * ratio, Math.round(2.2 * ratio));
		var gap = Math.max(1 * ratio, Math.round(1.3 * ratio));
		var bars = Math.max(1, Math.floor(width / (barWidth + gap)));
		var peaks = reducedPeaks(player.peaks, bars);
		var progress = player.audio.duration ? player.audio.currentTime / player.audio.duration : 0;
		var center = height / 2;
		context.fillStyle = '#d7dde5';
		context.fillRect(0, center - ratio / 2, width, ratio);
		for (var index = 0; index < bars; index++) {
			var amplitude = peaks.length ? peaks[index] : 0.08 + (index % 5) * 0.015;
			var barHeight = Math.max(3 * ratio, amplitude * (height - 8 * ratio));
			context.fillStyle = index / bars <= progress ? '#0b376b' : '#8995a3';
			context.fillRect(index * (barWidth + gap), center - barHeight / 2, barWidth, barHeight);
		}
	}

	function ensurePeaks(player) {
		if (player.peaks.length || player.loading || !player.root.dataset.waveformUrl) {
			draw(player);
			return;
		}
		player.loading = true;
		player.root.classList.add('is-loading');
		fetch(player.root.dataset.waveformUrl, { credentials: 'same-origin' })
			.then(function (response) { return response.ok ? response.json() : Promise.reject(new Error('waveform')); })
			.then(function (payload) {
				if (!payload || !payload.success || !payload.data) throw new Error('waveform');
				player.peaks = normalizePeaks(payload.data.peaks);
				if (payload.data.duration && !player.audio.duration) player.duration.textContent = formatTime(Number(payload.data.duration));
			})
			.catch(function () { player.root.classList.add('is-waveform-unavailable'); })
			.finally(function () {
				player.loading = false;
				player.root.classList.remove('is-loading');
				draw(player);
			});
	}

	function setButtonState(player) {
		var playing = !player.audio.paused && !player.audio.ended;
		player.play.querySelector('span').textContent = playing ? '❚❚' : '▶';
		player.play.setAttribute('aria-label', playing ? 'Metti in pausa' : 'Riproduci il master WAV');
		player.root.classList.toggle('is-playing', playing);
	}

	function init(root) {
		var player = {
			root: root,
			audio: root.querySelector('.trb-waveform-player__audio'),
			play: root.querySelector('.trb-waveform-player__play'),
			seek: root.querySelector('.trb-waveform-player__seek'),
			canvas: root.querySelector('canvas'),
			current: root.querySelector('[data-waveform-current]'),
			duration: root.querySelector('[data-waveform-duration]'),
			peaks: normalizePeaks(root.dataset.peaks),
			loading: false
		};
		if (!player.audio || !player.play || !player.seek || !player.canvas) return;
		players.push(player);

		player.play.addEventListener('click', function () {
			if (player.audio.paused) {
				players.forEach(function (other) { if (other !== player) other.audio.pause(); });
				player.audio.play().catch(function () {});
			} else {
				player.audio.pause();
			}
		});
		player.seek.addEventListener('click', function (event) {
			if (!player.audio.duration) return;
			var bounds = player.seek.getBoundingClientRect();
			player.audio.currentTime = Math.min(player.audio.duration, Math.max(0, (event.clientX - bounds.left) / bounds.width * player.audio.duration));
		});
		player.audio.addEventListener('loadedmetadata', function () {
			player.duration.textContent = formatTime(player.audio.duration);
			draw(player);
		});
		player.audio.addEventListener('timeupdate', function () {
			player.current.textContent = formatTime(player.audio.currentTime);
			draw(player);
		});
		['play', 'pause', 'ended'].forEach(function (eventName) {
			player.audio.addEventListener(eventName, function () { setButtonState(player); });
		});

		var details = root.closest('details');
		if (!details || details.open) ensurePeaks(player);
		else details.addEventListener('toggle', function () { if (details.open) ensurePeaks(player); });
		if ('ResizeObserver' in window) new ResizeObserver(function () { draw(player); }).observe(player.seek);
		setButtonState(player);
		draw(player);
	}

	document.querySelectorAll('[data-trb-waveform-player]').forEach(init);
}());
