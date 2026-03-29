(function () {
	'use strict';

	function toggle(root, showEdit) {
		var view = root.querySelector('.lls-profile-learn-lang__view');
		var edit = root.querySelector('.lls-profile-learn-lang__edit');
		var btnEdit = root.querySelector('.lls-profile-learn-lang__btn-edit');
		if (!view || !edit) {
			return;
		}
		if (showEdit) {
			view.setAttribute('hidden', 'hidden');
			edit.removeAttribute('hidden');
			var sel = edit.querySelector('select');
			if (sel) {
				sel.focus();
			}
		} else {
			edit.setAttribute('hidden', 'hidden');
			view.removeAttribute('hidden');
			if (btnEdit) {
				btnEdit.focus();
			}
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-lls-profile-learn-lang]').forEach(function (root) {
			var btnEdit = root.querySelector('.lls-profile-learn-lang__btn-edit');
			var btnCancel = root.querySelector('.lls-profile-learn-lang__btn-cancel');
			if (btnEdit) {
				btnEdit.addEventListener('click', function () {
					toggle(root, true);
				});
			}
			if (btnCancel) {
				btnCancel.addEventListener('click', function (e) {
					e.preventDefault();
					toggle(root, false);
				});
			}
		});
	});
})();
