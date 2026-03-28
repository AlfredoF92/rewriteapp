/**
 * Intestazione login: selettore lingua (en/es/it/pl) e testi da data-config.
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'lls_login_intro_lang';

	function parseConfig(el) {
		var raw = el.getAttribute('data-config');
		if (!raw) {
			return null;
		}
		try {
			return JSON.parse(raw);
		} catch (e) {
			return null;
		}
	}

	function setLangButtons(root, lang) {
		root.querySelectorAll('.lls-login-intro__lang-btn').forEach(function (btn) {
			var active = btn.getAttribute('data-lang') === lang;
			btn.classList.toggle('lls-login-intro__lang-btn--active', active);
			btn.setAttribute('aria-pressed', active ? 'true' : 'false');
		});
	}

	function render(root, cfg, lang) {
		var s = cfg.strings && cfg.strings[lang];
		if (!s) {
			lang = 'en';
			s = cfg.strings.en;
		}

		var greet = root.querySelector('[data-lls-intro-greeting]');
		var body = root.querySelector('[data-lls-intro-body]');
		var reg = root.querySelector('[data-lls-intro-register]');

		if (greet) {
			greet.textContent = s.greeting || '';
		}
		if (body) {
			body.textContent = s.body || '';
		}
		if (reg) {
			reg.textContent = '';
			if (cfg.registerUrl) {
				reg.appendChild(document.createTextNode(s.regBefore || ''));
				var a = document.createElement('a');
				a.href = cfg.registerUrl;
				a.className = 'lls-login-intro__register-link';
				a.textContent = s.regLink || '';
				reg.appendChild(a);
				if (s.regAfter) {
					reg.appendChild(document.createTextNode(s.regAfter));
				}
			} else {
				reg.textContent = s.regPlain || '';
			}
		}

		setLangButtons(root, lang);
		try {
			localStorage.setItem(STORAGE_KEY, lang);
		} catch (e) {
			/* ignore */
		}
	}

	function initIntro(root) {
		var cfg = parseConfig(root);
		if (!cfg || !cfg.strings) {
			return;
		}

		var stored = '';
		try {
			stored = localStorage.getItem(STORAGE_KEY) || '';
		} catch (e) {
			stored = '';
		}

		var langs = ['en', 'es', 'it', 'pl'];
		var lang = langs.indexOf(stored) !== -1 ? stored : cfg.defaultLang || 'en';
		if (!cfg.strings[lang]) {
			lang = 'en';
		}

		render(root, cfg, lang);

		root.querySelectorAll('.lls-login-intro__lang-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var next = btn.getAttribute('data-lang');
				if (next && langs.indexOf(next) !== -1 && cfg.strings[next]) {
					render(root, cfg, next);
				}
			});
		});
	}

	function boot() {
		document.querySelectorAll('[data-lls-login-intro]').forEach(initIntro);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
