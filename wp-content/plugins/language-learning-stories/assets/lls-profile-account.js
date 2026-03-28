/**
 * Area account: mostra/nascondi form modifica.
 */
(function () {
	'use strict';

	document.addEventListener('click', function (e) {
		var t = e.target;
		if (!t || !t.closest) {
			return;
		}
		var root = t.closest('[data-lls-profile-account]');
		if (!root) {
			return;
		}
		if (t.classList.contains('lls-profile-account__btn-edit')) {
			root.classList.add('lls-profile-account--editing');
		}
		if (t.classList.contains('lls-profile-account__btn-cancel')) {
			e.preventDefault();
			root.classList.remove('lls-profile-account--editing');
			var form = root.querySelector('.lls-profile-account__form');
			if (form && typeof form.reset === 'function') {
				form.reset();
			}
		}
	});
})();
