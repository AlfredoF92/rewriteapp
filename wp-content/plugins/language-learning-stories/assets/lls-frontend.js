(function ($) {
	'use strict';

	if (typeof llsStory === 'undefined') return;

	var data = llsStory;
	var S = data.strings && typeof data.strings === 'object' ? data.strings : {};
	function llsT(key) {
		var v = S[key];
		return (typeof v === 'string' && v !== '') ? v : '';
	}
	var sentences = data.sentences || [];
	var images = data.images || [];
	var total = sentences.length;

	var state = {
		completedIndex: data.progress.completed || 0,
		storyHtml: data.progress.story_text || '',
		showFeedback: false,
		userTranslation: ''
	};

	// Assicura che completedIndex non superi il numero di frasi (es. dati corrotti)
	if (state.completedIndex > total) state.completedIndex = total;

	function escapeHtml(s) {
		if (!s) return '';
		var div = document.createElement('div');
		div.textContent = s;
		return div.innerHTML;
	}

	function escapeAttr(s) {
		if (!s) return '';
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	/** Pulsante volume (stesso markup per «prossima frase» e blocco riscrittura) */
	function llsHearMainTranslationButtonHtml(englishForListen) {
		var t = (englishForListen || '').trim();
		if (!t) return '';
		return (
			'<button type="button" class="lls-hear-main-translation lls-hear-main--pending" aria-label="' + escapeAttr(llsT('hear_aria_label')) + '" title="' + escapeAttr(llsT('hear_title')) + '" aria-hidden="true" tabindex="-1">' +
				'<span class="lls-hear-main-icon" aria-hidden="true">' +
					'<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
						'<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>' +
						'<path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>' +
						'<path d="M19.07 4.93a9 9 0 0 1 0 14.14"/>' +
					'</svg>' +
				'</span>' +
			'</button>'
		);
	}

	var LLS_REWRITE_SUCCESS_DELAY_MS = 3000;
	/** Pausa prima che appaia il box «Traduzioni alternative» (dopo la traduzione principale) */
	var LLS_ALTERNATIVES_APPEAR_DELAY_MS = 3000;

	function llsHasAtLeastOneWord(s) {
		if (!s || typeof s !== 'string') return false;
		return s.trim().split(/\s+/).filter(Boolean).length > 0;
	}

	function llsUpdateTranslationPeekProtect($root) {
		var obscure = llsHasAtLeastOneWord($('#lls-rewrite-input').val() || '');
		$root.find('.lls-main-translation, .lls-alternatives').toggleClass('lls-peek-obscured', obscure);
	}

	/** Come per l\'ex pulsante «Bravo»: confronto esatto ignorando maiuscole e punteggiatura */
	function normalizeForMatch(s) {
		if (!s || typeof s !== 'string') return '';
		return s.toLowerCase()
			.replace(/[.,;:!?'"()\-–—…]/g, ' ')
			.replace(/\s+/g, ' ')
			.trim();
	}

	function userTextMatchesOneTranslation(userText, translationStrings) {
		var userNorm = normalizeForMatch(userText);
		if (!userNorm) return false;
		for (var i = 0; i < translationStrings.length; i++) {
			var ref = translationStrings[i];
			if (!ref) continue;
			if (normalizeForMatch(ref) === userNorm) return true;
		}
		return false;
	}

	function llsTokenizeWords(s) {
		if (!s || typeof s !== 'string') return [];
		return s.toLowerCase()
			.replace(/[.,;:!?'"()\-–—…]/g, ' ')
			.replace(/\s+/g, ' ')
			.trim()
			.split(' ')
			.filter(Boolean);
	}

	function llsUniqueWords(tokens) {
		var seen = {};
		var out = [];
		for (var i = 0; i < tokens.length; i++) {
			var w = tokens[i];
			if (!seen[w]) {
				seen[w] = true;
				out.push(w);
			}
		}
		return out;
	}

	/** Primo tentativo di traduzione: almeno il 20% delle parole uniche di una traduzione nel testo */
	function llsUserWordOverlapAtLeast(userText, translationStrings, ratio) {
		ratio = typeof ratio === 'number' ? ratio : 0.2;
		var userTokens = llsTokenizeWords(userText);
		if (!userTokens.length) return false;
		var userMap = {};
		for (var u = 0; u < userTokens.length; u++) userMap[userTokens[u]] = true;
		for (var r = 0; r < translationStrings.length; r++) {
			var refStr = (translationStrings[r] || '').trim();
			if (!refStr) continue;
			var refUnique = llsUniqueWords(llsTokenizeWords(refStr));
			if (!refUnique.length) continue;
			var matched = 0;
			for (var j = 0; j < refUnique.length; j++) {
				if (userMap[refUnique[j]]) matched++;
			}
			if (matched / refUnique.length >= ratio - 1e-9) return true;
		}
		return false;
	}

	/** Typewriter IA: carattere per carattere (stesso ritmo del feedback) */
	var LLS_AI_CHAR_DELAY_MS = 30;
	/** Traduzioni alternative: stesso effetto ma più rapido */
	var LLS_ALT_TYPEWRITER_CHAR_DELAY_MS = 10;
	var LLS_AI_THINKING_PAUSE_MS = 2000;

	/** Contenuto interno del pulsante microfono (traduzione + riscrittura) */
	function llsMicBtnInner() {
		return (
			'<span class="lls-btn-mic-icon" aria-hidden="true">' +
				'<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
					'<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>' +
					'<path d="M19 10v2a7 7 0 0 1-14 0v-2"/>' +
					'<line x1="12" y1="19" x2="12" y2="23"/>' +
					'<line x1="8" y1="23" x2="16" y2="23"/>' +
				'</svg>' +
			'</span>' +
			'<span class="lls-btn-mic-text">' +
				'<span class="lls-btn-mic-label">' + escapeHtml(llsT('mic_label')) + '</span>' +
				'<span class="lls-mic-hint">' + escapeHtml(llsT('mic_hint')) + '</span>' +
			'</span>'
		);
	}

	function tokenizeHtml(html) {
		var tokens = [];
		var parts = html.split(/(<[^>]+>)/g);
		for (var i = 0; i < parts.length; i++) {
			var p = parts[i];
			if (!p) continue;
			if (p.charAt(0) === '<') {
				tokens.push({ type: 'tag', value: p });
			} else {
				tokens.push({ type: 'text', value: p });
			}
		}
		return tokens;
	}

	function runTypewriterCharsWithFormatting($container, html, charDelayMs, onDone) {
		if (!$container.length) {
			if (onDone) onDone();
			return;
		}
		var tokens = tokenizeHtml(html || '');
		var outputHtml = '';
		var tokenIndex = 0;

		function updateDom() {
			$container[0].innerHTML = outputHtml;
		}

		function nextToken() {
			if (tokenIndex >= tokens.length) {
				if (onDone) onDone();
				return;
			}
			var tok = tokens[tokenIndex];
			if (tok.type === 'tag') {
				outputHtml += tok.value;
				updateDom();
				tokenIndex++;
				setTimeout(nextToken, 0);
			} else {
				var chars = Array.from(tok.value);
				var charIdx = 0;
				function nextChar() {
					if (charIdx < chars.length) {
						outputHtml += escapeHtml(chars[charIdx]);
						updateDom();
						charIdx++;
						setTimeout(nextChar, charDelayMs);
					} else {
						tokenIndex++;
						setTimeout(nextToken, 0);
					}
				}
				nextChar();
			}
		}

		nextToken();
	}

	function llsHideThinking($root) {
		$root.find('.lls-ai-thinking').remove();
	}

	/** Cursore nella stessa area in cui partirà il testo animato (non sopra la textarea). */
	function llsShowThinkingInContainer($container) {
		if (!$container || !$container.length) return;
		$container.find('.lls-ai-thinking').remove();
		var html =
			'<div class="lls-ai-thinking lls-ai-thinking--in-stream" aria-live="polite" title="' + escapeAttr(llsT('thinking_aria_title')) + '">' +
				'<span class="lls-ai-thinking-cursor" aria-hidden="true"></span>' +
			'</div>';
		$container.append($(html));
	}

	function llsAfterThinkingIn($root, $container, fn) {
		llsShowThinkingInContainer($container);
		setTimeout(function () {
			llsHideThinking($root);
			fn();
		}, LLS_AI_THINKING_PAUSE_MS);
	}

	/** Pausa cursore tra etichetta "Prossima frase:" e testo della frase */
	function llsAfterThinkingThenNextPhrase($root, fn) {
		var $text = $root.find('.lls-next-phrase-text');
		if (!$text.length) {
			fn();
			return;
		}
		$text.empty();
		llsShowThinkingInContainer($text);
		setTimeout(function () {
			llsHideThinking($root);
			fn();
		}, LLS_AI_THINKING_PAUSE_MS);
	}

	function getImagesAfterPosition(pos) {
		return images.filter(function (img) { return img.position === pos && img.url; });
	}

	/** Evita che un click breve o un reflow layout chiuda l’ascolto prima che parta il motore vocale */
	var LLS_MIC_MIN_HOLD_MS = 320;

	// Microfono: mantieni premuto per dettare (Web Speech API)
	function setupMicButton($btn, $textarea) {
		if (!$btn.length || !$textarea.length) return;
		var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
		if (!SpeechRecognition) {
			$btn.addClass('lls-mic-unavailable').prop('disabled', true).attr('title', llsT('mic_unavailable_title'));
			return;
		}

		var recognition = null;
		var textBeforeSession = '';
		var sessionStartTs = 0;
		var stopAfterMinHoldTimer = null;
		var startRetryCount = 0;
		var $feedback = $btn.siblings('.lls-mic-feedback');

		function clearStopTimer() {
			if (stopAfterMinHoldTimer) {
				clearTimeout(stopAfterMinHoldTimer);
				stopAfterMinHoldTimer = null;
			}
		}

		function resetFeedbackText() {
			$feedback.text(llsT('mic_feedback_listening'));
		}

		function applyMicUi(active) {
			$btn.toggleClass('lls-mic-active', !!active);
			$feedback.toggleClass('lls-mic-feedback-visible', !!active);
		}

		function tearDownRecognitionInstance() {
			recognition = null;
			startRetryCount = 0;
			clearStopTimer();
			applyMicUi(false);
		}

		function startRecognition() {
			if (recognition) return;
			clearStopTimer();
			resetFeedbackText();
			textBeforeSession = $textarea.val();
			sessionStartTs = Date.now();
			applyMicUi(true);

			var rec = new SpeechRecognition();
			recognition = rec;
			rec.continuous = true;
			rec.interimResults = true;
			rec.lang = 'en-US';

			rec.onresult = function (e) {
				var sessionText = '';
				for (var i = 0; i < e.results.length; i++) {
					sessionText += e.results[i][0].transcript;
				}
				var newVal = (textBeforeSession.trim() ? textBeforeSession.trim() + ' ' : '') + sessionText.trim();
				$textarea.val(newVal);
				$textarea.trigger('input');
			};

			rec.onstart = function () {
				resetFeedbackText();
				applyMicUi(true);
			};

			rec.onerror = function (e) {
				var err = e && e.error ? e.error : '';
				if (err === 'aborted') return;
				if (err === 'no-speech') return;
				var msg = null;
				if (err === 'not-allowed' || err === 'service-not-allowed') {
					msg = llsT('mic_err_not_allowed');
				} else if (err === 'network') {
					msg = llsT('mic_err_network');
				} else if (err === 'audio-capture') {
					msg = llsT('mic_err_audio');
				}
				if (recognition === rec) {
					recognition = null;
				}
				clearStopTimer();
				$btn.removeClass('lls-mic-active');
				if (msg) {
					$feedback.text(msg).addClass('lls-mic-feedback-visible');
					setTimeout(function () {
						$feedback.removeClass('lls-mic-feedback-visible');
						resetFeedbackText();
					}, 4500);
				} else {
					applyMicUi(false);
					resetFeedbackText();
				}
			};

			rec.onend = function () {
				if (recognition !== rec) return;
				tearDownRecognitionInstance();
			};

			try {
				rec.start();
			} catch (err) {
				if (err && err.name === 'InvalidStateError' && startRetryCount < 1) {
					startRetryCount++;
					recognition = null;
					setTimeout(function () {
						startRecognition();
					}, 120);
					return;
				}
				tearDownRecognitionInstance();
				$feedback.text(llsT('mic_err_start')).addClass('lls-mic-feedback-visible');
				setTimeout(function () {
					$feedback.removeClass('lls-mic-feedback-visible');
					resetFeedbackText();
				}, 3500);
			}
		}

		function stopRecognition() {
			clearStopTimer();
			var elapsed = sessionStartTs ? Date.now() - sessionStartTs : LLS_MIC_MIN_HOLD_MS;
			if (elapsed < LLS_MIC_MIN_HOLD_MS) {
				stopAfterMinHoldTimer = setTimeout(function () {
					stopAfterMinHoldTimer = null;
					stopRecognition();
				}, LLS_MIC_MIN_HOLD_MS - elapsed);
				return;
			}
			if (recognition) {
				try {
					recognition.abort();
				} catch (e) { /* ignore */ }
				recognition = null;
			}
			applyMicUi(false);
			resetFeedbackText();
		}

		$btn.off('.llsMic');

		if (window.PointerEvent) {
			$btn.on('pointerdown.llsMic', function (e) {
				if (e.button !== undefined && e.button !== 0) return;
				e.preventDefault();
				try {
					e.currentTarget.setPointerCapture(e.pointerId);
				} catch (ignore) { /* ignore */ }
				startRecognition();
			});
			$btn.on('pointerup.llsMic pointercancel.llsMic lostpointercapture.llsMic', function () {
				stopRecognition();
			});
		} else {
			$btn.on('mousedown.llsMic touchstart.llsMic', function (e) {
				e.preventDefault();
				startRecognition();
			});
			$btn.on('mouseup.llsMic touchend.llsMic', function () {
				stopRecognition();
			});
			$(document).off('mouseup.llsMicGlobal touchend.llsMicGlobal').on('mouseup.llsMicGlobal touchend.llsMicGlobal', function () {
				stopRecognition();
			});
		}
	}

	function transitionThenRender(done) {
		var $root = $('#lls-story-root');
		$root.addClass('lls-fade-out');
		setTimeout(function () {
			$root.removeClass('lls-fade-out');
			if (done) done();
		}, 420);
	}

	function restartStory() {
		state.completedIndex = 0;
		state.storyHtml = '';
		state.showFeedback = false;
		state.userTranslation = '';
		saveProgress();
		transitionThenRender(function () { render(); });
	}

	function confirmAndRestart() {
		if (!confirm(llsT('confirm_restart_1'))) return;
		if (!confirm(llsT('confirm_restart_2'))) return;
		restartStory();
	}

	function render() {
		var $root = $('#lls-story-root');
		$root.empty();

		// Titolo e progresso
		var pct = total ? Math.round((state.completedIndex / total) * 100) : 0;
		var restartBtnHtml = state.completedIndex > 0
			? '<button type="button" class="lls-restart-link" id="lls-btn-restart-header" aria-label="' + escapeAttr(llsT('restart_header_aria')) + '">' + escapeHtml(llsT('restart_header_link')) + '</button>'
			: '';
		var headerHtml =
			'<div class="lls-header">' +
				'<h1 class="lls-story-title">' + escapeHtml(data.title) + '</h1>' +
				'<div class="lls-progress-label">' + escapeHtml(llsT('progress_label')) + '</div>' +
				'<div class="lls-progress-bar-wrap">' +
					'<div class="lls-progress-bar" style="width:' + pct + '%"></div>' +
				'</div>' +
				'<div class="lls-progress-row">' +
					'<span class="lls-progress-counter">' + state.completedIndex + ' / ' + total + ' ' + escapeHtml(llsT('progress_phrases_done')) + (total ? ' - ' + pct + '%' : '') + '</span>' +
					(restartBtnHtml ? '<span class="lls-header-actions">' + restartBtnHtml + '</span>' : '') +
				'</div>' +
			'</div>';
		$root.append(headerHtml);
		$root.find('#lls-btn-restart-header').on('click', confirmAndRestart);

		// Immagine di apertura (solo se presente e non abbiamo ancora completato nulla, o sempre in cima)
		if (data.openingImageUrl) {
			$root.append(
				'<div class="lls-opening-image">' +
					'<img src="' + escapeAttr(data.openingImageUrl) + '" alt="">' +
				'</div>'
			);
		}

		// Storia costruita
		var builtContent = state.storyHtml;
		if (!builtContent.trim()) {
			builtContent = '<p class="lls-story-built-empty">' + escapeHtml(llsT('story_empty')) + '</p>';
		}
		$root.append(
			'<div class="lls-story-built">' + builtContent + '</div>'
		);

		var storyDividerHtml = '<hr class="lls-story-divider" role="presentation" />';

		if (state.completedIndex >= total && total > 0) {
			$root.append(storyDividerHtml);
			$root.append(
				'<div class="lls-story-complete">' +
					'<h2>' + escapeHtml(llsT('story_complete_title')) + '</h2>' +
					'<p>' + escapeHtml(llsT('story_complete_message').replace('%d', String(total))) + '</p>' +
					'<div class="lls-restart-wrap"><button type="button" class="lls-restart-btn" id="lls-btn-restart">' + escapeHtml(llsT('restart_story_button')) + '</button></div>' +
				'</div>'
			);
			$('#lls-btn-restart').on('click', confirmAndRestart);
			return;
		}

		var current = sentences[state.completedIndex];
		if (!current) return;

		if (!state.showFeedback) {
			// Traduzione inglese da riprodurre al clic (principale o prima alternativa disponibile)
			var englishMainForListen = (current.main_translation || current.alt1 || current.alt2 || '').trim();
			var hearMainBtnHtml = llsHearMainTranslationButtonHtml(englishMainForListen);
			// Prossima frase + primo input (frase italiana con typewriter lento)
			var nextHtml =
				'<div class="lls-next-phrase">' +
					'<div class="lls-next-phrase-label lls-next-phrase-label-stream"></div>' +
					'<div class="lls-next-phrase-listenable-wrap">' +
						'<div class="lls-next-phrase-text lls-typewriter-target"></div>' +
						hearMainBtnHtml +
					'</div>' +
				'</div>' +
				'<div class="lls-input-section">' +
					'<div class="lls-input-wrap">' +
						'<div class="lls-input-with-mic">' +
							'<textarea id="lls-translation-input" placeholder="' + escapeAttr(llsT('translation_placeholder')) + '" rows="4"></textarea>' +
							'<div class="lls-mic-wrap">' +
								'<button type="button" class="lls-btn-mic" id="lls-btn-mic" aria-label="' + escapeAttr(llsT('mic_aria_label')) + '">' + llsMicBtnInner() + '</button>' +
								'<span class="lls-mic-feedback" aria-live="polite">' + escapeHtml(llsT('mic_feedback_listening')) + '</span>' +
							'</div>' +
						'</div>' +
						'<div class="lls-continua-wrap">' +
							'<button type="button" class="lls-btn lls-btn-continua" id="lls-btn-continua">' + escapeHtml(llsT('continue_btn')) + '</button>' +
							'<div class="lls-continue-feedback" aria-live="polite"></div>' +
						'</div>' +
					'</div>' +
				'</div>';
			$root.append(storyDividerHtml);
			$root.append(nextHtml);
			if (englishMainForListen) {
				$root.find('.lls-next-phrase-listenable-wrap').first().data('llsSpeakEn', englishMainForListen);
			}
			setupMicButton($root.find('#lls-btn-mic'), $root.find('#lls-translation-input'));
			// Prima "Prossima frase:" (carattere per carattere), poi 3s cursore, poi la frase italiana animata
			var dNext = LLS_AI_CHAR_DELAY_MS;
			var htmlPhrase = '<p>' + escapeHtml(current.text_it) + '</p>';
			runTypewriterCharsWithFormatting($root.find('.lls-next-phrase-label-stream'), llsT('next_phrase_prefix'), dNext, function () {
				llsAfterThinkingThenNextPhrase($root, function () {
					runTypewriterCharsWithFormatting($root.find('.lls-next-phrase-text'), htmlPhrase, dNext, function () {
						var $wrap = $root.find('.lls-next-phrase-listenable-wrap').first();
						var $hear = $wrap.find('.lls-hear-main-translation');
						if ($hear.length) {
							$wrap.addClass('lls-next-phrase-can-hear');
							$hear.removeClass('lls-hear-main--pending').addClass('lls-hear-main--reveal').attr('aria-hidden', 'false').removeAttr('tabindex');
						}
					});
				});
			});

			var possibleTranslations = [current.main_translation, current.alt1, current.alt2].filter(Boolean);

			$('#lls-translation-input').on('input', function () {
				$root.find('.lls-continue-feedback').removeClass('lls-continue-feedback-visible').text('');
			});
			$('#lls-btn-continua').on('click', function () {
				var val = $('#lls-translation-input').val().trim();
				var $fb = $root.find('.lls-continue-feedback');
				$fb.removeClass('lls-continue-feedback-visible').text('');
				if (!val) {
					$fb.text(llsT('continue_first_hint')).addClass('lls-continue-feedback-visible');
					return;
				}
				if (possibleTranslations.length && !llsUserWordOverlapAtLeast(val, possibleTranslations, 0.2)) {
					$fb.text(llsT('continue_first_hint')).addClass('lls-continue-feedback-visible');
					return;
				}
				state.userTranslation = val;
				state.showFeedback = true;
				render();
			});
			return;
		}

		// Feedback + secondo input
		var grammarRaw = (current.grammar || '').trim();
		var grammarHtml = grammarRaw ? '<div class="lls-grammar-content">' + grammarRaw + '</div>' : '';

		var mainTranslation = (current.main_translation || '').trim();
		var altsOnly = [current.alt1, current.alt2].filter(Boolean);

		var grammarBoxHtml = grammarRaw
			? '<div class="lls-feedback-box lls-grammar-tips"><div class="lls-ai-stream lls-grammar-stream lls-grammar-content"></div></div>'
			: '';

		var mainTranslationBoxHtml = mainTranslation
			? '<div class="lls-feedback-box lls-main-translation"><div class="lls-ai-stream lls-main-stream"></div></div>'
			: '';

		var alternativesBoxHtml = altsOnly.length
			? '<div class="lls-feedback-box lls-alternatives"><div class="lls-ai-stream lls-alternatives-stream"></div></div>'
			: '';

		var rewritePromptHtml = '<div class="lls-rewrite-prompt lls-initially-hidden">' +
			'<div class="lls-ai-stream lls-rewrite-stream"></div>' +
			'</div>';

		var feedbackHtml =
			'<div class="lls-feedback">' +
				'<div class="lls-feedback-box lls-your-answer">' +
					'<div class="lls-ai-stream lls-your-answer-stream"></div>' +
				'</div>' +
				'<div class="lls-feedback-box lls-bravo-intro">' +
					'<div class="lls-ai-stream lls-bravo-intro-stream"></div>' +
				'</div>' +
				grammarBoxHtml +
				mainTranslationBoxHtml +
				alternativesBoxHtml +
				rewritePromptHtml +
				'<div class="lls-input-wrap lls-initially-hidden">' +
					'<div class="lls-input-with-mic">' +
						'<textarea id="lls-rewrite-input" placeholder="' + escapeAttr(llsT('rewrite_placeholder')) + '" rows="3"></textarea>' +
						'<div class="lls-mic-wrap">' +
							'<button type="button" class="lls-btn-mic" id="lls-btn-mic-rewrite" aria-label="' + escapeAttr(llsT('mic_aria_label')) + '">' + llsMicBtnInner() + '</button>' +
							'<span class="lls-mic-feedback" aria-live="polite">' + escapeHtml(llsT('mic_feedback_listening')) + '</span>' +
						'</div>' +
					'</div>' +
					'<div class="lls-rewrite-actions">' +
						'<button type="button" class="lls-btn lls-btn-continua" id="lls-btn-continua-rewrite">' + escapeHtml(llsT('continue_btn')) + '</button>' +
						'<div class="lls-continue-feedback" aria-live="polite"></div>' +
						'<div class="lls-rewrite-success-box lls-initially-hidden">' +
							'<div class="lls-ai-stream lls-rewrite-success-stream"></div>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>';
		$root.append(storyDividerHtml);
		$root.append(feedbackHtml);
		setupMicButton($root.find('#lls-btn-mic-rewrite'), $root.find('#lls-rewrite-input'));

		var possibleRewriteTranslations = [current.main_translation, current.alt1, current.alt2].filter(Boolean);

		$('#lls-rewrite-input').on('input', function () {
			$root.find('.lls-rewrite-actions .lls-continue-feedback').removeClass('lls-continue-feedback-visible').text('');
			llsUpdateTranslationPeekProtect($root);
		});
		llsUpdateTranslationPeekProtect($root);

		function advanceStoryAfterRewrite() {
			var main = (current.main_translation || current.alt1 || current.alt2 || '').trim();
			var imgsAfter = getImagesAfterPosition(state.completedIndex + 1);

			state.userTranslation = '';

			var $feedback = $root.find('.lls-feedback');
			$feedback.addClass('lls-fade-out');

			setTimeout(function () {
				$feedback.remove();

				var $built = $root.find('.lls-story-built');
				$built.find('.lls-story-built-empty').remove();

				if (!main) {
					imgsAfter.forEach(function (img) {
						$built.append('<div class="lls-story-image"><img src="' + escapeAttr(img.url) + '" alt=""></div>');
					});
					imgsAfter.forEach(function (img) {
						state.storyHtml += '<div class="lls-story-image"><img src="' + escapeAttr(img.url) + '" alt=""></div>';
					});
					state.completedIndex++;
					saveProgress();
					render();
					return;
				}

				var wordDelayMs = 130;
				var $newP = $('<p class="lls-story-new-sentence"></p>');
				$built.append($newP);

				var words = main.split(/\s+/).filter(Boolean);
				var wordIdx = 0;

				function typeNext() {
					if (wordIdx < words.length) {
						var span = document.createElement('span');
						span.textContent = words[wordIdx] + (wordIdx < words.length - 1 ? ' ' : '');
						$newP[0].appendChild(span);
						wordIdx++;
						setTimeout(typeNext, wordDelayMs);
					} else {
						$newP.removeClass('lls-story-new-sentence');
						state.storyHtml += '<p>' + escapeHtml(main) + '</p>';
						imgsAfter.forEach(function (img) {
							state.storyHtml += '<div class="lls-story-image"><img src="' + escapeAttr(img.url) + '" alt=""></div>';
						});
						imgsAfter.forEach(function (img) {
							$built.append('<div class="lls-story-image"><img src="' + escapeAttr(img.url) + '" alt=""></div>');
						});
						state.completedIndex++;
						state.showFeedback = false;
						saveProgress();
						render();
					}
				}

				typeNext();
			}, 420);
		}

		// Sequenza: La tua risposta → titolo "Bravo, per questa frase ti consiglio di:" → pausa con cursore → consigli e resto
		function runFeedbackAiSequence() {
			var d = LLS_AI_CHAR_DELAY_MS;
			var htmlYour = '<h4>' + escapeHtml(llsT('your_answer_title')) + '</h4><p>' + escapeHtml(state.userTranslation) + '</p>';
			var htmlBravoIntro = '<h4 class="lls-bravo-intro-title">' + escapeHtml(llsT('bravo_intro_title')) + '</h4>';

			$root.find('.lls-your-answer').addClass('lls-ai-box-active');
			runTypewriterCharsWithFormatting($root.find('.lls-your-answer-stream'), htmlYour, d, function () {
				stepBravoIntro();
			});

			function stepBravoIntro() {
				$root.find('.lls-bravo-intro').addClass('lls-ai-box-active');
				runTypewriterCharsWithFormatting($root.find('.lls-bravo-intro-stream'), htmlBravoIntro, d, function () {
					if (grammarRaw) {
						$root.find('.lls-grammar-tips').addClass('lls-ai-box-active');
						var $gStream = $root.find('.lls-grammar-stream');
						$gStream.empty();
						llsAfterThinkingIn($root, $gStream, function () {
							runTypewriterCharsWithFormatting($gStream, grammarRaw, d, function () {
								stepMain();
							});
						});
					} else {
						stepMain();
					}
				});
			}

			function stepMain() {
				if (mainTranslation) {
					$root.find('.lls-main-translation').addClass('lls-ai-box-active');
					var $mainStream = $root.find('.lls-main-stream');
					// Box + titolo subito; cursore nel corpo dove parte il paragrafo
					$mainStream.html('<h4>' + escapeHtml(llsT('main_translation_title')) + '</h4><div class="lls-main-body-stream"></div>');
					var htmlMainBody = '<p>' + escapeHtml(mainTranslation) + '</p>';
					var $mainBody = $mainStream.find('.lls-main-body-stream');
					llsAfterThinkingIn($root, $mainBody, function () {
						runTypewriterCharsWithFormatting($mainBody, htmlMainBody, d, function () {
							stepAlts();
						});
					});
				} else {
					stepAlts();
				}
			}

			function stepAlts() {
				if (altsOnly.length) {
					setTimeout(function () {
						var $altBox = $root.find('.lls-alternatives');
						var $altStream = $root.find('.lls-alternatives-stream');
						var htmlAltsBody = altsOnly.map(function (a) {
							return '<p class="lls-alt-line">' + escapeHtml(a) + '</p>';
						}).join('');
						var dAlts = LLS_ALT_TYPEWRITER_CHAR_DELAY_MS;
						$altStream.html(
							'<h4>' + escapeHtml(llsT('alternatives_title')) + '</h4>' +
								'<div class="lls-alternatives-body-stream"></div>'
						);
						var $altBody = $altStream.find('.lls-alternatives-body-stream');
						$altBox.addClass('lls-ai-box-active');
						llsAfterThinkingIn($root, $altBody, function () {
							runTypewriterCharsWithFormatting($altBody, htmlAltsBody, dAlts, function () {
								setTimeout(showRewriteBlock, 400);
							});
						});
					}, LLS_ALTERNATIVES_APPEAR_DELAY_MS);
				} else {
					showRewriteBlock();
				}
			}

			function showRewriteBlock() {
				var $rp = $root.find('.lls-rewrite-prompt');
				var $inputWrap = $root.find('.lls-feedback .lls-input-wrap');
				var $rwStream = $root.find('.lls-rewrite-stream');
				var englishListenRw = (current.main_translation || current.alt1 || current.alt2 || '').trim();
				var hearBtnRw = llsHearMainTranslationButtonHtml(englishListenRw);
				$rp.removeClass('lls-initially-hidden').addClass('lls-ai-box-active');
				$rwStream.html(
					'<h4>' + escapeHtml(llsT('rewrite_title')) + '</h4>' +
						'<div class="lls-rewrite-body-stream"></div>'
				);
				var $rwBody = $rwStream.find('.lls-rewrite-body-stream');
				var htmlPhraseRw = '<p>' + escapeHtml(current.text_it) + '</p>';
				llsAfterThinkingIn($root, $rwBody, function () {
					$rwBody.html(
						'<div class="lls-next-phrase-listenable-wrap">' +
							'<div class="lls-next-phrase-text lls-typewriter-target"></div>' +
							hearBtnRw +
							'</div>'
					);
					var $wrapRw = $rwBody.find('.lls-next-phrase-listenable-wrap').first();
					var $phraseRw = $wrapRw.find('.lls-next-phrase-text');
					if (englishListenRw) {
						$wrapRw.data('llsSpeakEn', englishListenRw);
					}
					runTypewriterCharsWithFormatting($phraseRw, htmlPhraseRw, d, function () {
						var $hearRw = $wrapRw.find('.lls-hear-main-translation');
						if ($hearRw.length) {
							$wrapRw.addClass('lls-next-phrase-can-hear');
							$hearRw.removeClass('lls-hear-main--pending').addClass('lls-hear-main--reveal').attr('aria-hidden', 'false').removeAttr('tabindex');
						}
						$inputWrap.removeClass('lls-initially-hidden').addClass('lls-just-visible');
					});
				});
			}
		}

		runFeedbackAiSequence();

		$('#lls-btn-continua-rewrite').on('click', function () {
			var val = $('#lls-rewrite-input').val().trim();
			var $actions = $root.find('.lls-rewrite-actions');
			var $fb = $actions.find('.lls-continue-feedback');
			var $successBox = $root.find('.lls-rewrite-success-box');
			var $successStream = $root.find('.lls-rewrite-success-stream');
			$fb.removeClass('lls-continue-feedback-visible').text('');
			if (!val) {
				$fb.text(llsT('continue_rewrite_hint')).addClass('lls-continue-feedback-visible');
					return;
				}
				if (possibleRewriteTranslations.length && !userTextMatchesOneTranslation(val, possibleRewriteTranslations)) {
					$fb.text(llsT('continue_rewrite_hint')).addClass('lls-continue-feedback-visible');
				return;
			}
			if ($successBox.hasClass('lls-rewrite-success-started')) return;
			$('#lls-btn-continua-rewrite').prop('disabled', true);
			$successBox.addClass('lls-rewrite-success-started').removeClass('lls-initially-hidden').addClass('lls-just-visible');
			var d = LLS_AI_CHAR_DELAY_MS;
			runTypewriterCharsWithFormatting($successStream, llsT('rewrite_success_html'), d, function () {
				setTimeout(function () {
					advanceStoryAfterRewrite();
				}, LLS_REWRITE_SUCCESS_DELAY_MS);
			});
		});
	}

	function saveProgress() {
		$.post(data.ajaxUrl, {
			action: 'lls_save_progress',
			nonce: data.nonce,
			story_id: data.storyId,
			completed: state.completedIndex,
			story_text: state.storyHtml
		});
	}

	// Sceglie una voce inglese più naturale quando disponibile
	function getPreferredEnglishVoice() {
		var voices = window.speechSynthesis.getVoices();
		var en = voices.filter(function (v) { return v.lang.startsWith('en'); });
		if (!en.length) return null;
		var natural = en.filter(function (v) {
			var n = (v.name || '').toLowerCase();
			return n.indexOf('natural') !== -1 || n.indexOf('google') !== -1 || n.indexOf('premium') !== -1 || n.indexOf('neural') !== -1 || n.indexOf('samantha') !== -1 || n.indexOf('karen') !== -1;
		});
		return natural.length ? natural[0] : en[0];
	}

	// Lettura ad alta voce della frase (inglese, lento e voce più naturale se disponibile)
	function speakStorySentence(text) {
		var t = (text || '').trim();
		if (!t) return;
		if (typeof window.speechSynthesis === 'undefined') return;
		window.speechSynthesis.cancel();
		var u = new window.SpeechSynthesisUtterance(t);
		u.lang = 'en-US';
		u.rate = 0.55;
		u.pitch = 1;
		var voice = getPreferredEnglishVoice();
		if (voice) u.voice = voice;
		window.speechSynthesis.speak(u);
	}

	$(function () {
		render();
		$('#lls-story-root').on('click', '.lls-story-built p:not(.lls-story-built-empty)', function () {
			speakStorySentence($(this).text());
		});
		$('#lls-story-root').on('click', '.lls-next-phrase-listenable-wrap', function (e) {
			var $wrap = $(this);
			if (!$wrap.hasClass('lls-next-phrase-can-hear')) return;
			var en = $wrap.data('llsSpeakEn');
			if (!en) return;
			var $t = $(e.target);
			if ($t.closest('.lls-hear-main-translation').length || $t.closest('.lls-next-phrase-text').length) {
				speakStorySentence(en);
			}
		});
	});
})(jQuery);
