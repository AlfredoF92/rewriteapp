(function () {
	'use strict';

	function getPreferredVoiceForLocale(locale) {
		if (typeof window.speechSynthesis === 'undefined') {
			return null;
		}
		var voices = window.speechSynthesis.getVoices();
		var base = (locale || 'en-US').split('-')[0].toLowerCase();
		var langVoices = voices.filter(function (v) {
			if (!v.lang) return false;
			var l = v.lang.toLowerCase();
			return l === base || l.indexOf(base + '-') === 0;
		});
		if (!langVoices.length) return null;
		var natural = langVoices.filter(function (v) {
			var n = (v.name || '').toLowerCase();
			return n.indexOf('natural') !== -1 || n.indexOf('google') !== -1 || n.indexOf('premium') !== -1 ||
				n.indexOf('neural') !== -1 || n.indexOf('samantha') !== -1 || n.indexOf('karen') !== -1;
		});
		return natural.length ? natural[0] : langVoices[0];
	}

	function speakPhrase(text, locale) {
		var t = (text || '').trim();
		if (!t || typeof window.speechSynthesis === 'undefined') {
			return;
		}
		var loc = (locale && String(locale).trim()) ? String(locale).trim() : 'en-US';
		window.speechSynthesis.cancel();
		var u = new window.SpeechSynthesisUtterance(t);
		u.lang = loc;
		u.rate = 0.55;
		u.pitch = 1;
		var voice = getPreferredVoiceForLocale(loc);
		if (voice) {
			u.voice = voice;
		}
		window.speechSynthesis.speak(u);
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.lls-completed-phrases__hear');
		if (!btn) {
			return;
		}
		var raw = btn.getAttribute('data-lls-speak-en');
		if (!raw) {
			return;
		}
		e.preventDefault();
		var loc = btn.getAttribute('data-lls-speak-locale') || 'en-US';
		speakPhrase(raw, loc);
	});

	if (typeof window.speechSynthesis !== 'undefined') {
		window.speechSynthesis.onvoiceschanged = function () {};
	}
})();
