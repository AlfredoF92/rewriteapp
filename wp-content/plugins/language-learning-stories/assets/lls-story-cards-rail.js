(function () {
	'use strict';

	function slugList(csv) {
		if (!csv || typeof csv !== 'string') {
			return [];
		}
		return csv
			.split(',')
			.map(function (s) {
				return s.trim();
			})
			.filter(Boolean);
	}

	function applyRailFilters(root) {
		var activeCats = [];
		var activeTags = [];
		root.querySelectorAll('.lls-story-rail__chip.is-active').forEach(function (btn) {
			var kind = btn.getAttribute('data-lls-rail-filter');
			var slug = btn.getAttribute('data-lls-slug');
			if (!slug) {
				return;
			}
			if (kind === 'cat') {
				activeCats.push(slug);
			}
			if (kind === 'tag') {
				activeTags.push(slug);
			}
		});

		var cards = root.querySelectorAll('.lls-story-rail__card');
		var anyVisible = false;
		cards.forEach(function (card) {
			var cats = slugList(card.getAttribute('data-lls-rail-cats'));
			var tags = slugList(card.getAttribute('data-lls-rail-tags'));
			var okCat =
				activeCats.length === 0 ||
				activeCats.some(function (s) {
					return cats.indexOf(s) !== -1;
				});
			var okTag =
				activeTags.length === 0 ||
				activeTags.some(function (s) {
					return tags.indexOf(s) !== -1;
				});
			var show = okCat && okTag;
			if (show) {
				anyVisible = true;
			}
			card.toggleAttribute('hidden', !show);
		});

		var empty = root.querySelector('[data-lls-rail-empty]');
		if (empty) {
			empty.toggleAttribute('hidden', anyVisible);
		}
	}

	function updateClear(root) {
		var wrap = root.querySelector('[data-lls-rail-clear-wrap]');
		if (!wrap) {
			return;
		}
		var any = !!root.querySelector('.lls-story-rail__chip.is-active');
		wrap.toggleAttribute('hidden', !any);
	}

	function normalizeClickTarget(t) {
		if (!t) {
			return null;
		}
		if (t.nodeType === Node.TEXT_NODE) {
			return t.parentElement;
		}
		return t;
	}

	document.addEventListener('click', function (e) {
		var t = normalizeClickTarget(e.target);
		if (!t || typeof t.closest !== 'function') {
			return;
		}
		var root = t.closest('[data-lls-story-rail]');
		if (!root) {
			return;
		}

		var clearBtn = t.closest('.lls-story-rail__clear');
		if (clearBtn) {
			e.preventDefault();
			root.querySelectorAll('.lls-story-rail__chip.is-active').forEach(function (b) {
				b.classList.remove('is-active');
				b.setAttribute('aria-pressed', 'false');
			});
			updateClear(root);
			applyRailFilters(root);
			return;
		}

		var chip = t.closest('.lls-story-rail__chip');
		if (!chip || chip.tagName !== 'BUTTON') {
			return;
		}
		e.preventDefault();
		chip.classList.toggle('is-active');
		chip.setAttribute('aria-pressed', chip.classList.contains('is-active') ? 'true' : 'false');
		updateClear(root);
		applyRailFilters(root);
	});
})();
