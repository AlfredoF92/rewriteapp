<?php
/**
 * Stringhe interfaccia front-end per lingua conosciuta (it / pl / es).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Codici lingua supportati per l’interfaccia (la lingua che l’utente conosce).
 *
 * @return string[]
 */
function lls_known_lang_codes() {
	return [ 'it', 'pl', 'es' ];
}

/**
 * User meta: lingua che l’utente conosce (interfaccia it / pl / es).
 *
 * @return string
 */
function lls_user_known_lang_meta_key() {
	return '_lls_user_known_lang';
}

/**
 * Lingua conosciuta salvata sul profilo utente (default it se assente o non valida).
 *
 * @param int|null $user_id ID utente o null per l’utente corrente.
 * @return string Codice it|pl|es.
 */
function lls_get_user_known_lang( $user_id = null ) {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return 'it';
	}
	$raw = get_user_meta( $user_id, lls_user_known_lang_meta_key(), true );
	if ( ! is_string( $raw ) || ! in_array( $raw, lls_known_lang_codes(), true ) ) {
		return 'it';
	}
	return $raw;
}

/**
 * Etichette per select «lingua che conosci» (stessi codici di {@see lls_known_lang_codes()}).
 *
 * @return array<string, string>
 */
function lls_get_known_lang_choice_labels() {
	return [
		'it' => __( 'Italian', 'language-learning-stories' ),
		'pl' => __( 'Polish', 'language-learning-stories' ),
		'es' => __( 'Spanish', 'language-learning-stories' ),
	];
}

/**
 * Catalogo raggruppato (etichette admin + default italiano).
 *
 * @return array<string, array{title: string, strings: array<string, array{label: string, default: string}>}>
 */
function lls_get_ui_string_catalog() {
	return [
		'header' => [
			'title'   => __( 'Intestazione e progresso', 'language-learning-stories' ),
			'strings' => [
				'progress_label'       => [
					'label'   => __( 'Etichetta progresso', 'language-learning-stories' ),
					'default' => 'Progresso della storia',
				],
				'progress_phrases_done' => [
					'label'   => __( 'Testo «frasi completate» (dopo X / Y)', 'language-learning-stories' ),
					'default' => 'frasi completate',
				],
				'restart_header_link'    => [
					'label'   => __( 'Link «Ricomincia la storia» (header)', 'language-learning-stories' ),
					'default' => 'Ricomincia la storia',
				],
				'restart_header_aria'    => [
					'label'   => __( 'Etichetta accessibilità link ricomincia (header)', 'language-learning-stories' ),
					'default' => 'Ricomincia la storia',
				],
			],
		],
		'story'  => [
			'title'   => __( 'Area storia', 'language-learning-stories' ),
			'strings' => [
				'story_empty' => [
					'label'   => __( 'Messaggio quando la storia è ancora vuota', 'language-learning-stories' ),
					'default' => 'La storia apparirà qui man mano che completi le frasi.',
				],
				'loading'     => [
					'label'   => __( 'Caricamento iniziale', 'language-learning-stories' ),
					'default' => 'Caricamento…',
				],
			],
		],
		'complete' => [
			'title'   => __( 'Fine storia', 'language-learning-stories' ),
			'strings' => [
				'story_complete_title'   => [
					'label'   => __( 'Titolo completamento', 'language-learning-stories' ),
					'default' => 'Storia completata!',
				],
				'story_complete_message' => [
					'label'   => __( 'Messaggio completamento (usa %d per il numero di frasi)', 'language-learning-stories' ),
					'default' => 'Hai tradotto tutte le %d frasi. Complimenti!',
				],
				'restart_story_button'   => [
					'label'   => __( 'Pulsante ricomincia (fine storia)', 'language-learning-stories' ),
					'default' => 'Ricominciare la storia',
				],
				'confirm_restart_1'      => [
					'label'   => __( 'Prima conferma ricomincia', 'language-learning-stories' ),
					'default' => 'Vuoi ricominciare la storia? Il tuo progresso verrà perso.',
				],
				'confirm_restart_2'      => [
					'label'   => __( 'Seconda conferma ricomincia', 'language-learning-stories' ),
					'default' => 'Confermi? Dovrai ripartire dalla prima frase.',
				],
			],
		],
		'next_phrase' => [
			'title'   => __( 'Prossima frase (primo tentativo)', 'language-learning-stories' ),
			'strings' => [
				'next_phrase_prefix'     => [
					'label'   => __( 'Prefisso prima della frase (es. «Prossima frase: »)', 'language-learning-stories' ),
					'default' => 'Prossima frase: ',
				],
				'translation_placeholder' => [
					'label'   => __( 'Placeholder area traduzione', 'language-learning-stories' ),
					'default' => 'Scrivi o pronuncia la traduzione della prossima frase della storia',
				],
				'hear_aria_label'        => [
					'label'   => __( 'Icona volume: aria-label', 'language-learning-stories' ),
					'default' => 'Ascolta la traduzione in inglese',
				],
				'hear_title'             => [
					'label'   => __( 'Icona volume: title (tooltip)', 'language-learning-stories' ),
					'default' => 'Ascolta la traduzione in inglese (puoi cliccare anche sulla frase)',
				],
			],
		],
		'mic' => [
			'title'   => __( 'Microfono', 'language-learning-stories' ),
			'strings' => [
				'mic_label'              => [
					'label'   => __( 'Testo principale pulsante microfono', 'language-learning-stories' ),
					'default' => 'Tieni premuto per pronunciare la frase…',
				],
				'mic_hint'               => [
					'label'   => __( 'Suggerimento sotto il microfono', 'language-learning-stories' ),
					'default' => '(È più efficace per imparare l\'inglese)',
				],
				'mic_aria_label'         => [
					'label'   => __( 'Etichetta accessibilità microfono', 'language-learning-stories' ),
					'default' => 'Mantieni premuto per pronunciare la frase',
				],
				'mic_feedback_listening' => [
					'label'   => __( 'Feedback «In ascolto»', 'language-learning-stories' ),
					'default' => 'In ascolto…',
				],
				'mic_unavailable_title'  => [
					'label'   => __( 'Tooltip microfono non disponibile', 'language-learning-stories' ),
					'default' => 'Riconoscimento vocale non supportato',
				],
				'mic_err_not_allowed'    => [
					'label'   => __( 'Errore: microfono non consentito', 'language-learning-stories' ),
					'default' => 'Microfono non consentito: controlla i permessi del sito nel browser.',
				],
				'mic_err_network'        => [
					'label'   => __( 'Errore: rete', 'language-learning-stories' ),
					'default' => 'Errore di rete nel riconoscimento vocale. Riprova.',
				],
				'mic_err_audio'          => [
					'label'   => __( 'Errore: nessuna sorgente audio', 'language-learning-stories' ),
					'default' => 'Impossibile usare il microfono (nessuna sorgente audio).',
				],
				'mic_err_start'          => [
					'label'   => __( 'Errore: impossibile avviare ascolto', 'language-learning-stories' ),
					'default' => 'Impossibile avviare l’ascolto. Riprova tra un attimo.',
				],
			],
		],
		'buttons_feedback' => [
			'title'   => __( 'Pulsanti Continua e messaggi', 'language-learning-stories' ),
			'strings' => [
				'continue_btn'           => [
					'label'   => __( 'Testo pulsante Continua', 'language-learning-stories' ),
					'default' => 'Continua',
				],
				'continue_first_hint'    => [
					'label'   => __( 'Messaggio primo Continua (overlap parole)', 'language-learning-stories' ),
					'default' => 'Per continuare prova a scrivere almeno un paio di parole in inglese corrette…',
				],
				'continue_rewrite_hint' => [
					'label'   => __( 'Messaggio Continua riscrittura (traduzione non corretta)', 'language-learning-stories' ),
					'default' => 'Prima di continuare, scrivi o pronuncia correttamente una delle traduzioni proposte…',
				],
				'rewrite_success_html'   => [
					'label'   => __( 'Messaggio successo dopo riscrittura (HTML consentito: &lt;p&gt;, &lt;strong&gt;, …)', 'language-learning-stories' ),
					'default' => '<p>Bravo! Ottimo lavoro… Continuiamo la storia…</p>',
				],
			],
		],
		'feedback_titles' => [
			'title'   => __( 'Titoli blocchi feedback', 'language-learning-stories' ),
			'strings' => [
				'your_answer_title'       => [
					'label'   => __( '«La tua risposta»', 'language-learning-stories' ),
					'default' => 'La tua risposta',
				],
				'bravo_intro_title'       => [
					'label'   => __( 'Intro consigli (titolo)', 'language-learning-stories' ),
					'default' => 'Bravo, per questa frase ti consiglio di: ',
				],
				'main_translation_title'  => [
					'label'   => __( '«Traduzione principale»', 'language-learning-stories' ),
					'default' => 'Traduzione principale',
				],
				'alternatives_title'      => [
					'label'   => __( '«Traduzioni alternative»', 'language-learning-stories' ),
					'default' => 'Traduzioni alternative',
				],
				'rewrite_title'           => [
					'label'   => __( 'Titolo blocco riscrittura', 'language-learning-stories' ),
					'default' => 'Ora riscrivi la frase dopo aver letto i consigli e le possibili varianti:',
				],
				'rewrite_placeholder'       => [
					'label'   => __( 'Placeholder textarea riscrittura', 'language-learning-stories' ),
					'default' => 'Riscrivi o pronuncia la frase utilizzando una delle traduzioni consigliate',
				],
			],
		],
		'misc' => [
			'title'   => __( 'Varie', 'language-learning-stories' ),
			'strings' => [
				'thinking_aria_title' => [
					'label'   => __( 'Titolo accessibilità cursore «in elaborazione»', 'language-learning-stories' ),
					'default' => 'In elaborazione',
				],
			],
		],
	];
}

/**
 * Array associativo chiave => default (italiano).
 *
 * @return array<string, string>
 */
function lls_get_default_ui_strings_flat() {
	$flat = [];
	foreach ( lls_get_ui_string_catalog() as $section ) {
		foreach ( $section['strings'] as $key => $def ) {
			$flat[ $key ] = $def['default'];
		}
	}
	return $flat;
}

/**
 * Traduzioni integrate — polacco (interfaccia).
 *
 * @return array<string, string>
 */
function lls_get_builtin_ui_strings_pl() {
	return [
		'progress_label'           => 'Postęp opowieści',
		'progress_phrases_done'    => 'ukończone zdania',
		'restart_header_link'      => 'Zacznij opowieść od nowa',
		'restart_header_aria'      => 'Zacznij opowieść od nowa',
		'story_empty'              => 'Opowieść pojawi się tutaj w miarę ukończenia zdań.',
		'loading'                  => 'Wczytywanie…',
		'story_complete_title'     => 'Opowieść ukończona!',
		'story_complete_message'   => 'Przetłumaczono wszystkie %d zdań. Gratulacje!',
		'restart_story_button'     => 'Zacząć opowieść od nowa',
		'confirm_restart_1'        => 'Czy chcesz zacząć opowieść od nowa? Twój postęp zostanie utracony.',
		'confirm_restart_2'        => 'Potwierdzasz? Zaczniesz od pierwszego zdania.',
		'next_phrase_prefix'       => 'Następne zdanie: ',
		'translation_placeholder'  => 'Napisz lub wymów tłumaczenie następnego zdania opowieści',
		'hear_aria_label'          => 'Posłuchaj tłumaczenia po angielsku',
		'hear_title'               => 'Posłuchaj tłumaczenia po angielsku (możesz też kliknąć na zdanie)',
		'mic_label'                => 'Przytrzymaj, aby wymówić zdanie…',
		'mic_hint'                 => '(To bardziej skuteczne w nauce angielskiego)',
		'mic_aria_label'           => 'Przytrzymaj, aby wymówić zdanie',
		'mic_feedback_listening'   => 'Nasłuchiwanie…',
		'mic_unavailable_title'    => 'Rozpoznawanie mowy nie jest obsługiwane',
		'mic_err_not_allowed'      => 'Mikrofon niedozwolony: sprawdź uprawnienia witryny w przeglądarce.',
		'mic_err_network'          => 'Błąd sieci w rozpoznawaniu mowy. Spróbuj ponownie.',
		'mic_err_audio'            => 'Nie można użyć mikrofonu (brak źródła dźwięku).',
		'mic_err_start'            => 'Nie można rozpocząć nasłuchiwania. Spróbuj ponownie za chwilę.',
		'continue_btn'             => 'Dalej',
		'continue_first_hint'      => 'Aby kontynuować, spróbuj napisać co najmniej kilka poprawnych angielskich słów…',
		'continue_rewrite_hint'    => 'Zanim przejdziesz dalej, napisz lub wymów poprawnie jedno z proponowanych tłumaczeń…',
		'rewrite_success_html'     => '<p>Świetnie! Świetna robota… Kontynuujemy opowieść…</p>',
		'your_answer_title'        => 'Twoja odpowiedź',
		'bravo_intro_title'        => 'Brawo, dla tego zdania polecam: ',
		'main_translation_title'   => 'Tłumaczenie główne',
		'alternatives_title'       => 'Tłumaczenia alternatywne',
		'rewrite_title'            => 'Teraz przepisz zdanie po przeczytaniu porad i możliwych wariantów:',
		'rewrite_placeholder'      => 'Przepisz lub wymów zdanie, używając jednego z polecanych tłumaczeń',
		'thinking_aria_title'      => 'Przetwarzanie',
	];
}

/**
 * Traduzioni integrate — spagnolo (interfaccia).
 *
 * @return array<string, string>
 */
function lls_get_builtin_ui_strings_es() {
	return [
		'progress_label'           => 'Progreso de la historia',
		'progress_phrases_done'    => 'frases completadas',
		'restart_header_link'      => 'Reiniciar la historia',
		'restart_header_aria'      => 'Reiniciar la historia',
		'story_empty'              => 'La historia aparecerá aquí a medida que completes las frases.',
		'loading'                  => 'Cargando…',
		'story_complete_title'     => '¡Historia completada!',
		'story_complete_message'   => 'Has traducido todas las %d frases. ¡Enhorabuena!',
		'restart_story_button'     => 'Volver a empezar la historia',
		'confirm_restart_1'        => '¿Quieres reiniciar la historia? Perderás tu progreso.',
		'confirm_restart_2'        => '¿Confirmas? Tendrás que empezar desde la primera frase.',
		'next_phrase_prefix'       => 'Siguiente frase: ',
		'translation_placeholder'  => 'Escribe o pronuncia la traducción de la siguiente frase de la historia',
		'hear_aria_label'          => 'Escuchar la traducción al inglés',
		'hear_title'               => 'Escuchar la traducción al inglés (también puedes hacer clic en la frase)',
		'mic_label'                => 'Mantén pulsado para decir la frase en voz alta…',
		'mic_hint'                 => '(Es más eficaz para aprender inglés)',
		'mic_aria_label'           => 'Mantén pulsado para pronunciar la frase',
		'mic_feedback_listening'   => 'Escuchando…',
		'mic_unavailable_title'    => 'Reconocimiento de voz no compatible',
		'mic_err_not_allowed'      => 'Micrófono no permitido: revisa los permisos del sitio en el navegador.',
		'mic_err_network'          => 'Error de red en el reconocimiento de voz. Inténtalo de nuevo.',
		'mic_err_audio'            => 'No se puede usar el micrófono (no hay fuente de audio).',
		'mic_err_start'            => 'No se puede iniciar la escucha. Inténtalo de nuevo en un momento.',
		'continue_btn'             => 'Continuar',
		'continue_first_hint'      => 'Para continuar, intenta escribir al menos un par de palabras correctas en inglés…',
		'continue_rewrite_hint'    => 'Antes de continuar, escribe o pronuncia correctamente una de las traducciones propuestas…',
		'rewrite_success_html'     => '<p>¡Bravo! ¡Buen trabajo… Continuemos la historia…</p>',
		'your_answer_title'        => 'Tu respuesta',
		'bravo_intro_title'        => 'Bien, para esta frase te aconsejo: ',
		'main_translation_title'   => 'Traducción principal',
		'alternatives_title'       => 'Traducciones alternativas',
		'rewrite_title'            => 'Ahora reescribe la frase después de leer los consejos y las posibles variantes:',
		'rewrite_placeholder'      => 'Reescribe o pronuncia la frase usando una de las traducciones recomendadas',
		'thinking_aria_title'      => 'En proceso',
	];
}

/**
 * Stringhe unite per una lingua conosciuta (default it + builtin pl/es + override da opzione).
 *
 * @param string $lang it|pl|es
 * @return array<string, string>
 */
function lls_get_merged_ui_strings( $lang ) {
	$lang     = in_array( $lang, lls_known_lang_codes(), true ) ? $lang : 'it';
	$defaults = lls_get_default_ui_strings_flat();
	if ( 'pl' === $lang ) {
		$out = array_merge( $defaults, lls_get_builtin_ui_strings_pl() );
	} elseif ( 'es' === $lang ) {
		$out = array_merge( $defaults, lls_get_builtin_ui_strings_es() );
	} else {
		$out = $defaults;
	}
	$saved = get_option( 'lls_ui_strings', [] );
	if ( ! is_array( $saved ) ) {
		$saved = [];
	}
	$lang_strings = isset( $saved[ $lang ] ) && is_array( $saved[ $lang ] ) ? $saved[ $lang ] : [];
	foreach ( $lang_strings as $key => $val ) {
		if ( ! is_string( $key ) || ! array_key_exists( $key, $out ) ) {
			continue;
		}
		$val = is_string( $val ) ? trim( $val ) : '';
		if ( '' !== $val ) {
			$out[ $key ] = $val;
		}
	}
	return $out;
}

/**
 * Unisce le traduzioni integrate nell’opzione salvata (per il form Traduzioni), una tantum.
 */
function lls_maybe_seed_ui_strings_pl_es() {
	if ( get_option( 'lls_ui_strings_pl_es_seeded_v1' ) ) {
		return;
	}
	$opt = get_option( 'lls_ui_strings', [] );
	if ( ! is_array( $opt ) ) {
		$opt = [];
	}
	$pl_saved = isset( $opt['pl'] ) && is_array( $opt['pl'] ) ? $opt['pl'] : [];
	$es_saved = isset( $opt['es'] ) && is_array( $opt['es'] ) ? $opt['es'] : [];
	$opt['pl'] = array_merge( lls_get_builtin_ui_strings_pl(), $pl_saved );
	$opt['es'] = array_merge( lls_get_builtin_ui_strings_es(), $es_saved );
	update_option( 'lls_ui_strings', $opt );
	update_option( 'lls_ui_strings_pl_es_seeded_v1', 1 );
}

/**
 * Sanifica l’opzione salvata dal form Traduzioni.
 *
 * @param mixed $input Dati inviati.
 * @return array<string, array<string, string>>
 */
function lls_sanitize_ui_strings_option( $input ) {
	$defaults = lls_get_default_ui_strings_flat();
	$out      = [];
	if ( ! is_array( $input ) ) {
		return $out;
	}
	foreach ( lls_known_lang_codes() as $lang ) {
		if ( ! isset( $input[ $lang ] ) || ! is_array( $input[ $lang ] ) ) {
			continue;
		}
		$out[ $lang ] = [];
		foreach ( $defaults as $key => $_def ) {
			if ( ! isset( $input[ $lang ][ $key ] ) ) {
				continue;
			}
			$raw = $input[ $lang ][ $key ];
			if ( 'rewrite_success_html' === $key ) {
				$out[ $lang ][ $key ] = wp_kses_post( wp_unslash( $raw ) );
			} else {
				$out[ $lang ][ $key ] = sanitize_textarea_field( wp_unslash( $raw ) );
			}
		}
	}
	return $out;
}
