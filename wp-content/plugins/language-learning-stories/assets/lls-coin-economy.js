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

		function renderUnlockedState($wrap, $block, permalink, noticeText) {
			if (!$wrap.length || !permalink) {
				return false;
			}
			if (!$block.length) {
				$block = $wrap.closest('.lls-story-unlock, .lls-story-rail__card, li.lls-profile-continue__item');
			}
			$block.removeClass('lls-profile-continue__item--locked');
			$block.find('.lls-unlock-success-notice').remove();

			var $title = $block.find('.lls-story-title').first();
			if ($title.length) {
				var titleText = $title.find('.lls-story-title__text').text() || $title.text();
				$title.empty().append($('<a>', { href: permalink, text: $.trim(titleText) }));
			}

			if (noticeText && String(noticeText).trim() !== '') {
				$wrap.before(
					$('<div>', {
						class: 'lls-unlock-success-notice',
						role: 'status',
						text: String(noticeText).trim()
					})
				);
			}

			var isRail = $wrap.closest('.lls-story-rail__foot').length > 0;
			var btnClass = isRail ? 'lls-btn lls-btn-continua lls-btn--sm' : 'lls-btn lls-btn-continua';
			$wrap.empty().append(
				$('<a>', {
					class: btnClass,
					href: permalink,
					text: cfg.i18n.enterStory || 'Enter the story'
				})
			);
			return true;
		}

		function unlockByStoryId(storyId, permalink, noticeText) {
			var $row = $(
				'li.lls-profile-continue__item[data-lls-story-id="' +
					storyId +
					'"], .lls-story-rail__card[data-lls-story-id="' +
					storyId +
					'"], .lls-story-unlock[data-lls-story-id="' +
					storyId +
					'"]'
			).first();
			if (!$row.length) {
				return false;
			}
			var $w = $row.find('.lls-continua-wrap').first();
			return renderUnlockedState($w, $row, permalink, noticeText);
		}

		$(document).on('click', '.lls-unlock-story-btn', function (e) {
			var $btn = $(this);
			var sid = parseInt($btn.attr('data-lls-unlock-story'), 10);
			if (!sid) {
				return;
			}

			var $wrap = $btn.closest('.lls-continua-wrap');
			if (!$wrap.length) {
				$wrap = $btn.parent();
			}
			var $block = $btn.closest('.lls-story-unlock, .lls-story-rail__card, li.lls-profile-continue__item');
			var backupHtml = $wrap.html();

			if ($wrap.attr('data-lls-unlock-request') === '1') {
				e.preventDefault();
				return;
			}
			$wrap.attr('data-lls-unlock-request', '1');

			// Nessun pulsante "Unlocking": solo testo di attesa (non cliccabile).
			$wrap.html(
				$('<span>', {
					class: 'lls-unlock-pending',
					'aria-busy': 'true',
					text: cfg.i18n.unlockPending || '…'
				})
			);

			function restoreOriginal() {
				$wrap.removeAttr('data-lls-unlock-request');
				$wrap.html(backupHtml);
			}

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
					try {
						if (!resp || !resp.success || !resp.data) {
							restoreOriginal();
							var $fb = $wrap.find('.lls-unlock-feedback--msg');
							if ($fb.length) {
								$fb.prop('hidden', false).text(cfg.i18n.error || 'Error');
							}
							return;
						}

						var d = resp.data;
						if (d.coin_total !== undefined && d.coin_total !== null && d.coin_total !== '') {
							var bal = parseInt(d.coin_total, 10);
							if (!isNaN(bal)) {
								updateCoinDisplay(bal);
							}
						}

						var permalink = d.permalink ? String(d.permalink) : '';
						var notice =
							typeof d.unlocked_notice === 'string' && d.unlocked_notice.trim() !== ''
								? d.unlocked_notice.trim()
								: '';

						if (d.unlocked && permalink) {
							var ok = renderUnlockedState($wrap, $block, permalink, notice);
							if (!ok) {
								ok = unlockByStoryId(sid, permalink, notice);
							}
							if (ok) {
								$wrap.removeAttr('data-lls-unlock-request');
							} else {
								restoreOriginal();
								var $fb2 = $wrap.find('.lls-unlock-feedback--msg');
								if ($fb2.length) {
									$fb2.prop('hidden', false).text(cfg.i18n.error || 'Error');
								}
							}
							return;
						}

						restoreOriginal();
						var $fb3 = $wrap.find('.lls-unlock-feedback--msg');
						if ($fb3.length) {
							$fb3.prop('hidden', false).text(cfg.i18n.error || 'Error');
						}
					} catch (err) {
						restoreOriginal();
					}
				})
				.fail(function (xhr) {
					restoreOriginal();
					var msg = cfg.i18n.error || 'Error';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						msg = xhr.responseJSON.data.message;
					}
					var $fb = $wrap.find('.lls-unlock-feedback--msg');
					if ($fb.length) {
						$fb.prop('hidden', false).text(msg);
					}
				});
		});
	});
})(jQuery);
