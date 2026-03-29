jQuery(function ($) {

	var mediaFrameOpeningImage = null;

	function llsGetSentenceFieldLabels() {
		var L = (typeof llsAdmin !== 'undefined' && llsAdmin.sentenceFieldLabels) ? llsAdmin.sentenceFieldLabels : {};
		return {
			textIt: L.textIt || 'Frase in italiano',
			mainTranslation: L.mainTranslation || 'Traduzione principale (appare nella storia)',
			alt1: L.alt1 || 'Traduzione alternativa 1',
			alt2: L.alt2 || 'Traduzione alternativa 2'
		};
	}

	function llsGetCsvExportHeaders() {
		if (typeof llsAdmin !== 'undefined' && Array.isArray(llsAdmin.csvExportHeaders) && llsAdmin.csvExportHeaders.length) {
			return llsAdmin.csvExportHeaders;
		}
		return ['Numero', 'Frase in italiano', 'Traduzione principale', 'Traduzione alternativa 1', 'Traduzione alternativa 2', 'Consigli grammaticali'];
	}

	function llsCsvPreviewHeaderRowHtml() {
		var H = (typeof llsAdmin !== 'undefined' && Array.isArray(llsAdmin.csvPreviewHeaders) && llsAdmin.csvPreviewHeaders.length >= 4)
			? llsAdmin.csvPreviewHeaders
			: ['N°', 'Frase (italiano)', 'Traduzione principale', 'Azione'];
		return '<th>' + H[0] + '</th><th>' + H[1] + '</th><th>' + H[2] + '</th><th>' + H[3] + '</th>';
	}



	function initOpeningImage() {

		$('.lls-opening-image-upload').on('click', function (e) {

			e.preventDefault();



			if (mediaFrameOpeningImage) {

				mediaFrameOpeningImage.open();

				return;

			}



			mediaFrameOpeningImage = wp.media({

				title: 'Seleziona immagine di apertura',

				button: { text: 'Usa questa immagine' },

				multiple: false,

			});



			mediaFrameOpeningImage.on('select', function () {

				var attachment = mediaFrameOpeningImage.state().get('selection').first().toJSON();

				$('#lls_opening_image_id').val(attachment.id);



				var $preview = $('.lls-opening-image-preview');

				$preview.empty();

				$preview.append($('<img />').attr('src', attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url));



				$('.lls-opening-image-remove').show();

			});



			mediaFrameOpeningImage.open();

		});



		$('.lls-opening-image-remove').on('click', function (e) {

			e.preventDefault();

			$('#lls_opening_image_id').val('');

			$('.lls-opening-image-preview').html(

				'<span class="lls-opening-image-placeholder">Nessuna immagine selezionata</span>'

			);

			$(this).hide();

		});

	}



	function renumberSentences() {

		$('#lls-sentences-list .lls-sentence-card').each(function (index) {

			var number = index + 1;

			$(this)

				.find('.lls-sentence-title')

				.text('Frase #' + number);

		});

	}



	function updateSentencePreview($card) {

		var text = $card

			.find('textarea[name="lls_sentences[text_it][]"]')

			.val() || '';

		text = text.replace(/\s+/g, ' ').trim();

		if (text.length > 80) {

			text = text.substring(0, 77) + '…';

		}

		$card.find('.lls-sentence-preview').text(text);

	}



	function initSentenceCard($card) {

		$card.find('.lls-toggle-sentence').off('click').on('click', function (e) {

			e.preventDefault();

			$card.toggleClass('is-open');

			if ($card.hasClass('is-open')) {

				$(this).text('Chiudi');

			} else {

				$(this).text('Modifica');

			}

		});



		$card.find('.lls-delete-sentence').off('click').on('click', function (e) {

			e.preventDefault();

			if (!window.confirm(llsAdmin.i18n.confirmDelete)) return;

			$card.remove();

			renumberSentences();

		});



		$card.find('textarea[name="lls_sentences[text_it][]"]')

			.off('input')

			.on('input', function () {

				updateSentencePreview($card);

			});

		updateSentencePreview($card);



		$card.find('.lls-grammar-text').each(function () {

			updateGrammarCounter($(this));

		});



		$card.find('.lls-grammar-text')

			.off('input')

			.on('input', function () {

				updateGrammarCounter($(this));

			});



		$card.find('.lls-insert-image-here')

			.off('click')

			.on('click', function (e) {

				e.preventDefault();

				var index = $('#lls-sentences-list .lls-sentence-card').index($card) + 1;

				createImageCard(index);

			});



		$card.find('.lls-save-sentence')

			.off('click')

			.on('click', function (e) {

				e.preventDefault();

				// Salva il post intero, come se cliccassi Aggiorna.

				if (window.wp && wp.data && wp.data.dispatch) {

					try {

						wp.data.dispatch('core/editor').savePost();

						return;

					} catch (err) {}

				}

				var $publish = $('#publish');

				if ($publish.length) {

					$publish.trigger('click');

				} else {

					$('#post').trigger('submit');

				}

			});

	}



	function updateGrammarCounter($textarea) {

		var text = $textarea.val() || '';

		$textarea

			.closest('p')

			.find('.lls-grammar-counter')

			.text(text.length + ' caratteri');

	}



	function createSentenceCard(data) {

		data = data || {};



		var lab = llsGetSentenceFieldLabels();

		var $card = $(

			'<div class="lls-sentence-card is-open">' +

				'<div class="lls-sentence-header">' +

					'<span class="lls-sentence-handle">↕</span>' +

					'<span class="lls-sentence-title"></span>' +

					'<span class="lls-sentence-preview"></span>' +

					'<button type="button" class="button-link lls-toggle-sentence">Modifica</button>' +

					'<button type="button" class="button-link-delete lls-delete-sentence">Elimina</button>' +

				'</div>' +

				'<div class="lls-sentence-body">' +

					'<p>' +

						'<label><strong>' + $('<div>').text(lab.textIt).html() + '</strong><br>' +

						'<textarea name="lls_sentences[text_it][]" class="widefat" rows="2"></textarea>' +

						'</label>' +

					'</p>' +

					'<p>' +

						'<label><strong>' + $('<div>').text(lab.mainTranslation).html() + '</strong><br>' +

						'<textarea name="lls_sentences[main_translation][]" class="widefat" rows="2"></textarea>' +

						'</label>' +

					'</p>' +

					'<p>' +

						'<label><strong>' + $('<div>').text(lab.alt1).html() + '</strong><br>' +

						'<input type="text" name="lls_sentences[alt1][]" class="widefat" />' +

						'</label>' +

					'</p>' +

					'<p>' +

						'<label><strong>' + $('<div>').text(lab.alt2).html() + '</strong><br>' +

						'<input type="text" name="lls_sentences[alt2][]" class="widefat" />' +

						'</label>' +

					'</p>' +

					'<p>' +

						'<label><strong>Consigli grammaticali</strong><br>' +

						'<textarea name="lls_sentences[grammar][]" class="widefat lls-grammar-text" rows="8"></textarea>' +

						'</label>' +

						'<span class="lls-grammar-counter"></span>' +

					'</p>' +

					'<p class="lls-insert-image-position">' +

						'<button type="button" class="button button-secondary lls-insert-image-here">Inserisci un box immagine in questa posizione</button>' +

						'<button type="button" class="button button-secondary lls-save-sentence">Salva</button>' +

					'</p>' +

				'</div>' +

			'</div>'

		);



		$card.find('textarea[name="lls_sentences[text_it][]"]').val(data.text_it || '');

		$card.find('textarea[name="lls_sentences[main_translation][]"]').val(data.main_translation || '');

		$card.find('input[name="lls_sentences[alt1][]"]').val(data.alt1 || '');

		$card.find('input[name="lls_sentences[alt2][]"]').val(data.alt2 || '');

		$card.find('textarea[name="lls_sentences[grammar][]"]').val(data.grammar || '');



		$('#lls-sentences-list').append($card);

		initSentenceCard($card);

		renumberSentences();

	}



	function createImageCard(position, data) {

		data = data || {};

		var attachmentId = data.attachment_id || 0;

		var thumbHtml = data.thumbHtml || '';



		var $card = $(

			'<div class="lls-image-card">' +

				'<div class="lls-image-inner">' +

					'<div class="lls-image-position">' +

						'<label>Posizione (dopo frase #)<br>' +

						'<input type="number" name="lls_images[position][]" min="1" step="1" style="width:80px;" />' +

						'</label>' +

					'</div>' +

					'<div class="lls-image-thumb"></div>' +

					'<div class="lls-image-actions">' +

						'<input type="hidden" name="lls_images[attachment_id][]" class="lls-image-id" />' +

						'<button type="button" class="button lls-image-upload">Carica Immagine</button>' +

						'<button type="button" class="button-link-delete lls-image-delete">Elimina</button>' +

					'</div>' +

				'</div>' +

			'</div>'

		);



		$card.find('input[name="lls_images[position][]"]').val(position || 1);

		$card.find('.lls-image-id').val(attachmentId);

		if (thumbHtml) {

			$card.find('.lls-image-thumb').html(thumbHtml);

		} else {

			$card.find('.lls-image-thumb').html(

				'<span class="lls-image-placeholder">Nessuna immagine</span>'

			);

		}



		$('#lls-images-list').append($card);

		initImageCard($card);

	}



	function initImageCard($card) {

		var frame = null;



		$card.find('.lls-image-upload')

			.off('click')

			.on('click', function (e) {

				e.preventDefault();



				if (frame) {

					frame.open();

					return;

				}



				frame = wp.media({

					title: 'Seleziona immagine',

					button: { text: 'Usa questa immagine' },

					multiple: false,

				});



				frame.on('select', function () {

					var attachment = frame.state().get('selection').first().toJSON();

					$card.find('.lls-image-id').val(attachment.id);



					var url =

						attachment.sizes && attachment.sizes.thumbnail

							? attachment.sizes.thumbnail.url

							: attachment.url;



					$card.find('.lls-image-thumb').html(

						$('<img />').attr('src', url).attr('alt', attachment.alt || '')

					);

				});



				frame.open();

			});



		$card.find('.lls-image-delete')

			.off('click')

			.on('click', function (e) {

				e.preventDefault();

				if (!window.confirm(llsAdmin.i18n.confirmDeleteImage)) return;

				$card.remove();

			});

	}



	function initSortable() {

		$('#lls-sentences-list').sortable({

			handle: '.lls-sentence-handle',

			items: '.lls-sentence-card',

			axis: 'y',

			update: function () {

				renumberSentences();

			},

		});

	}



	function initButtons() {

		$('.lls-add-sentence').on('click', function (e) {

			e.preventDefault();

			createSentenceCard({});

		});



		$('.lls-export-csv').on('click', function (e) {

			e.preventDefault();

			exportCsv();

		});



		$('.lls-import-csv').on('click', function (e) {

			e.preventDefault();

			importCsv();

		});

	}



	function escapeCsvCell(value) {

		if (value === null || value === undefined) return '""';

		var str = String(value);

		str = str.replace(/\r?\n/g, ' ').replace(/"/g, '""');

		return '"' + str + '"';

	}



	function exportCsv() {

		var rows = [];

		$('#lls-sentences-list .lls-sentence-card').each(function (index) {

			var $card = $(this);

			var num = index + 1;

			var textIt = $card.find('textarea[name="lls_sentences[text_it][]"]').val() || '';

			var mainTr = $card.find('textarea[name="lls_sentences[main_translation][]"]').val() || '';

			var alt1 = $card.find('input[name="lls_sentences[alt1][]"]').val() || '';

			var alt2 = $card.find('input[name="lls_sentences[alt2][]"]').val() || '';

			var grammar = $card.find('textarea[name="lls_sentences[grammar][]"]').val() || '';



			rows.push([num, textIt, mainTr, alt1, alt2, grammar]);

		});



		var header = llsGetCsvExportHeaders();

		var headerLine = header.map(escapeCsvCell).join(';');

		var csvRows = [headerLine].concat(

			rows.map(function (cols) {

				return cols.map(escapeCsvCell).join(';');

			})

		);

		var csv = '\uFEFF' + csvRows.join('\r\n');



		var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });

		var url = URL.createObjectURL(blob);

		var a = document.createElement('a');

		a.href = url;

		a.download = 'storia-frasi.csv';

		document.body.appendChild(a);

		a.click();

		document.body.removeChild(a);

		URL.revokeObjectURL(url);

	}



	function importCsv() {

		var $input = $('<input type="file" accept=".csv" style="display:none;" />');

		$('body').append($input);



		$input.on('change', function () {

			var file = this.files[0];

			if (!file) {

				$input.remove();

				return;

			}



			var reader = new FileReader();

			reader.onload = function (e) {

				var text = e.target.result;

				var rows = parseCsvToRows(text);

				$input.remove();

				if (rows && rows.length) {

					showImportPreviewModal(rows);

				} else {

					alert('Nessuna frase valida trovata nel file CSV.');

				}

			};

			reader.readAsText(file, 'utf-8');

		});



		$input.trigger('click');

	}



	function parseCsvToRows(text) {

		var lines = text.split(/\r?\n/).filter(function (l) {

			return l.trim().length > 0;

		});

		if (!lines.length) return [];



		var startIndex = 0;

		if (/numero/i.test(lines[0])) {

			startIndex = 1;

		}

		var rows = [];

		for (var i = startIndex; i < lines.length; i++) {

			var cols = splitCsvLine(lines[i], ';');

			if (!cols.length) continue;



			var num = parseInt(cols[0], 10) || (rows.length + 1);

			var grammarCol = cols.length >= 7 ? 6 : 5;

			rows.push({

				num: num,

				text_it: (cols[1] || '').trim(),

				main_translation: (cols[2] || '').trim(),

				alt1: (cols[3] || '').trim(),

				alt2: (cols[4] || '').trim(),

				grammar: (cols[grammarCol] || '').trim(),

			});

		}

		return rows;

	}



	function showImportPreviewModal(rows) {

		var currentCount = $('#lls-sentences-list .lls-sentence-card').length;

		var preview = rows.map(function (r) {

			var action = r.num >= 1 && r.num <= currentCount ? 'sostituita' : 'aggiunta';

			return {

				num: r.num,

				text_it: r.text_it,

				main_translation: r.main_translation,

				action: action,

			};

		});



		var $modal = $('#lls-import-preview-modal');

		if (!$modal.length) {

			$modal = $(

				'<div id="lls-import-preview-modal" class="lls-modal-overlay" style="display:none;">' +

					'<div class="lls-modal-box">' +

						'<div class="lls-modal-header">' +

							'<h2 class="lls-modal-title">Anteprima importazione CSV</h2>' +

							'<button type="button" class="lls-modal-close" aria-label="Chiudi">&times;</button>' +

						'</div>' +

						'<div class="lls-modal-body">' +

							'<p class="lls-modal-description">Le frasi con numero già presente saranno sostituite, le altre aggiunte.</p>' +

							'<p class="lls-modal-summary"></p>' +

							'<div class="lls-import-preview-box">' +

								'<div class="lls-modal-table-wrap"></div>' +

							'</div>' +

						'</div>' +

						'<div class="lls-modal-footer">' +

							'<button type="button" class="button lls-modal-cancel">Annulla</button>' +

							'<button type="button" class="button button-primary lls-modal-confirm">Carica le frasi</button>' +

						'</div>' +

					'</div>' +

				'</div>'

			);

			$('body').append($modal);



			$modal.find('.lls-modal-close, .lls-modal-cancel').on('click', function () {

				$modal.hide();

			});

			$modal.on('click', function (e) {

				if (e.target === $modal[0]) $modal.hide();

			});

		}



		var tableHtml =

			'<table class="lls-import-preview-table">' +

			'<thead><tr>' +

			llsCsvPreviewHeaderRowHtml() +

			'</tr></thead><tbody>';



		preview.forEach(function (p) {

			var textItShort = p.text_it.length > 60 ? p.text_it.substring(0, 57) + '…' : p.text_it;

			var mainShort = p.main_translation.length > 50 ? p.main_translation.substring(0, 47) + '…' : p.main_translation;

			var actionLabel = p.action === 'sostituita' ? 'Sostituita' : 'Aggiunta';

			var rowClass = p.action === 'sostituita' ? 'lls-row-replace' : 'lls-row-add';

			tableHtml +=

				'<tr class="' +

				rowClass +

				'">' +

				'<td>' +

				p.num +

				'</td>' +

				'<td>' +

				$('<div>').text(textItShort).html() +

				'</td>' +

				'<td>' +

				$('<div>').text(mainShort).html() +

				'</td>' +

				'<td><span class="lls-action-badge lls-action-' +

				p.action +

				'">' +

				actionLabel +

				'</span></td>' +

				'</tr>';

		});



		tableHtml += '</tbody></table>';



		$modal.find('.lls-modal-table-wrap').html(tableHtml);



		var numSostituite = preview.filter(function (p) { return p.action === 'sostituita'; }).length;

		var numAggiunte = preview.filter(function (p) { return p.action === 'aggiunta'; }).length;

		var summaryText = 'Riepilogo: ' + numSostituite + ' sostituite, ' + numAggiunte + ' aggiunte.';

		$modal.find('.lls-modal-summary').text(summaryText).toggle(numSostituite + numAggiunte > 0);

		$modal.find('.lls-import-warn-dup').remove();

		var uniqueIt = {};

		rows.forEach(function (r) {

			var k = (r.text_it || '').trim();

			if (k) {

				uniqueIt[k] = true;

			}

		});

		var nUnique = Object.keys(uniqueIt).length;

		if (rows.length >= 2 && nUnique < rows.length) {

			var i18n = (typeof llsAdmin !== 'undefined' && llsAdmin.i18n) ? llsAdmin.i18n : {};

			var dupTitle = i18n.csvDupWarnTitle || 'Frasi duplicate nel file:';

			var dupTpl = i18n.csvDupWarnBody || ('risultano %1$d frasi distinte nella colonna «%2$s» su %3$d righe. Il CSV ripete spesso la stessa riga: non è un errore di import. Rigenera il file (es. con ChatGPT) chiedendo una frase diversa per ogni riga, in ordine, che formino un’unica storia.');

			var knownDisp = (typeof llsAdmin !== 'undefined' && llsAdmin.knownLangDisplay) ? llsAdmin.knownLangDisplay : 'italiano';

			var dupBody = dupTpl.split('%1$d').join(String(nUnique)).split('%2$s').join(knownDisp).split('%3$d').join(String(rows.length));

			$modal.find('.lls-modal-summary').after(

				'<div class="lls-import-warn-dup notice notice-warning" style="margin-top:10px;"><p><strong>' + $('<div>').text(dupTitle).html() + '</strong> ' +

				$('<div>').text(dupBody).html() + '</p></div>'

			);

		}



		$modal.find('.lls-modal-confirm').off('click').on('click', function () {

			applyImport(rows);

			$modal.hide();

		});



		$modal.show();

	}



	function applyImport(rows) {

		var currentCount = $('#lls-sentences-list .lls-sentence-card').length;

		var $cards = $('#lls-sentences-list .lls-sentence-card');



		rows.forEach(function (r) {

			var data = {

				text_it: r.text_it,

				main_translation: r.main_translation,

				alt1: r.alt1,

				alt2: r.alt2,

				grammar: r.grammar,

			};

			if (r.num >= 1 && r.num <= currentCount) {

				var $card = $cards.eq(r.num - 1);

				if ($card.length) {

					$card.find('textarea[name="lls_sentences[text_it][]"]').val(data.text_it);

					$card.find('textarea[name="lls_sentences[main_translation][]"]').val(data.main_translation);

					$card.find('input[name="lls_sentences[alt1][]"]').val(data.alt1);

					$card.find('input[name="lls_sentences[alt2][]"]').val(data.alt2);

					$card.find('textarea[name="lls_sentences[grammar][]"]').val(data.grammar);

					updateGrammarCounter($card.find('.lls-grammar-text'));

					updateSentencePreview($card);

				}

			} else {

				createSentenceCard(data);

			}

		});



		renumberSentences();

	}



	function splitCsvLine(line, delimiter) {

		delimiter = delimiter || ';';

		var result = [];

		var current = '';

		var inQuotes = false;



		for (var i = 0; i < line.length; i++) {

			var char = line[i];



			if (inQuotes) {

				if (char === '"') {

					if (i + 1 < line.length && line[i + 1] === '"') {

						current += '"';

						i++;

					} else {

						inQuotes = false;

					}

				} else {

					current += char;

				}

			} else {

				if (char === '"') {

					inQuotes = true;

				} else if (char === delimiter) {

					result.push(current);

					current = '';

				} else {

					current += char;

				}

			}

		}



		result.push(current);

		return result;

	}



	function bootstrapExisting() {

		$('#lls-sentences-list .lls-sentence-card').each(function () {

			initSentenceCard($(this));

		});



		$('#lls-images-list .lls-image-card').each(function () {

			initImageCard($(this));

		});



		renumberSentences();

	}



	$(document).ready(function () {

		initOpeningImage();

		initButtons();

		initSortable();

		bootstrapExisting();

	});

});



