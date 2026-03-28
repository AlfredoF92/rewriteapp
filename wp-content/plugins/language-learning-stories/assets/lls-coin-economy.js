(function ($) {
	'use strict';

	$(function () {
		if (typeof llsCoinEconomy === 'undefined') {
			return;
		}

		var cfg = llsCoinEconomy;

		function updateCoinDisplay(n) {
			$('.lls-coin__value[data-lls-coin-value]').text(String(n));
		}

		function unlockRowDom(storyId, permalink) {
			var $li = $('li.lls-profile-continue__item[data-lls-story-id="' + storyId + '"]');
			if (!$li.length) {
				return;
			}
			$li.removeClass('lls-profile-continue__item--locked');
			var $title = $li.find('.lls-story-title');
			var titleText = $title.find('.lls-story-title__text').text() || $title.text();
			$title.empty().append(
				$('<a>', { href: permalink, text: titleText })
			);
			var $wrap = $li.find('.lls-continua-wrap');
			$wrap.empty().append(
				$('<a>', {
					class: 'lls-btn lls-btn-continua',
					href: permalink,
					text: cfg.i18n.enterStory || 'Enter the story'
				})
			);
		}

		$(document).on('click', '.lls-unlock-story-btn', function () {
			var $btn = $(this);
			var sid = parseInt($btn.attr('data-lls-unlock-story'), 10);
			if (!sid) {
				return;
			}
			var label = $btn.text();
			$btn.prop('disabled', true);
			$btn.text(cfg.i18n.unlocking || '…');
			var $fb = $btn.closest('.lls-continua-wrap').find('.lls-unlock-feedback--msg');
			$fb.prop('hidden', false).text('');

			$.ajax({
				url: cfg.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'lls_unlock_story',
					nonce: cfg.nonce,
					story_id: sid
				}
			})
				.done(function (resp) {
					if (resp && resp.success && resp.data) {
						if (typeof resp.data.coin_total === 'number') {
							updateCoinDisplay(resp.data.coin_total);
						}
						if (resp.data.unlocked && resp.data.permalink) {
							unlockRowDom(sid, resp.data.permalink);
							return;
						}
					}
					$btn.prop('disabled', false).text(label);
					$fb.text(cfg.i18n.error || 'Error');
				})
				.fail(function (xhr) {
					$btn.prop('disabled', false).text(label);
					var msg = cfg.i18n.error || 'Error';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						msg = xhr.responseJSON.data.message;
					}
					$fb.prop('hidden', false).text(msg);
				});
		});
	});
})(jQuery);
