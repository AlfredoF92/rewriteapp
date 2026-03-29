(function () {
	'use strict';

	function getCfg() {
		return typeof llsLibraryGridFilters === 'object' && llsLibraryGridFilters !== null
			? llsLibraryGridFilters
			: { paramCat: 'lls_lib_cat', paramTag: 'lls_lib_tag' };
	}

	function parseSlugList(url, param) {
		var v = url.searchParams.get(param);
		if (!v) {
			return [];
		}
		return v
			.split(',')
			.map(function (s) {
				return s.trim();
			})
			.filter(Boolean);
	}

	function toggleSlug(list, slug) {
		var i = list.indexOf(slug);
		if (i === -1) {
			list.push(slug);
		} else {
			list.splice(i, 1);
		}
		return list;
	}

	function navigateWithFilters(kind, slug) {
		var cfg = getCfg();
		var paramCat = cfg.paramCat || 'lls_lib_cat';
		var paramTag = cfg.paramTag || 'lls_lib_tag';
		var param = kind === 'cat' ? paramCat : paramTag;

		var u = new URL(window.location.href);
		var cats = parseSlugList(u, paramCat);
		var tags = parseSlugList(u, paramTag);

		if (kind === 'cat') {
			cats = toggleSlug(cats.slice(), slug);
		} else {
			tags = toggleSlug(tags.slice(), slug);
		}

		if (cats.length > 0) {
			u.searchParams.set(paramCat, cats.join(','));
		} else {
			u.searchParams.delete(paramCat);
		}
		if (tags.length > 0) {
			u.searchParams.set(paramTag, tags.join(','));
		} else {
			u.searchParams.delete(paramTag);
		}

		u.searchParams.delete('paged');
		u.searchParams.delete('page');

		window.location.assign(u.toString());
	}

	function clearFilters() {
		var cfg = getCfg();
		var paramCat = cfg.paramCat || 'lls_lib_cat';
		var paramTag = cfg.paramTag || 'lls_lib_tag';
		var u = new URL(window.location.href);
		u.searchParams.delete(paramCat);
		u.searchParams.delete(paramTag);
		u.searchParams.delete('paged');
		u.searchParams.delete('page');
		window.location.assign(u.toString());
	}

	document.addEventListener('click', function (e) {
		var t = e.target;
		if (!t || typeof t.closest !== 'function') {
			return;
		}
		var root = t.closest('[data-lls-lib-grid-filters]');
		if (!root) {
			return;
		}

		if (t.closest('.lls-lib-grid-clear')) {
			e.preventDefault();
			clearFilters();
			return;
		}

		var chip = t.closest('.lls-lib-grid-chip');
		if (!chip || chip.tagName !== 'BUTTON') {
			return;
		}
		e.preventDefault();
		var kind = chip.getAttribute('data-lls-filter');
		var slug = chip.getAttribute('data-lls-slug');
		if (!kind || !slug) {
			return;
		}
		navigateWithFilters(kind, slug);
	});
})();
