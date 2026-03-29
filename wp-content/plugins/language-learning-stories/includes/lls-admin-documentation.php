<?php
/**
 * Gruppi di documentazione tecnica (admin: Storie → Documentazione).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sezioni «reference» per sviluppatori (panoramica, DB, API, file).
 *
 * @return array<int, array<string, mixed>>
 */
function lls_get_admin_documentation_developer_groups() {
	return [
		[
			'group_title' => __( 'Panoramica tecnica (per sviluppatori)', 'language-learning-stories' ),
			'group_intro' => __( 'Questa parte descrive architettura, dati persistenti, flussi e punti di estensione. Sotto trovi ancora la documentazione dettagliata di ogni shortcode.', 'language-learning-stories' ),
			'sections'    => [
				[
					'reference_only' => true,
					'title'          => __( 'Scopo e stack', 'language-learning-stories' ),
					'intro'          => __( 'Il plugin registra il custom post type `lls_story` (storie con frasi, traduzioni, immagini) e due tassonomie dedicate (`lls_story_category`, `lls_story_tag`). Il front-end combina shortcode WordPress, asset JS/CSS, AJAX per salvare il progresso, economia a «coin» opzionale, e integrazioni Elementor Pro (Loop Grid con Query ID personalizzati, tag dinamici, widget). La logica PHP è organizzata in `includes/*.php` caricati da `language-learning-stories.php`; la classe `LLS_Plugin` centralizza CPT, tassonomie, salvataggio meta in admin, AJAX e redirect.', 'language-learning-stories' ),
				],
				[
					'reference_only' => true,
					'title'          => __( 'Modello dati: WordPress', 'language-learning-stories' ),
					'intro'          => __( 'Le storie sono righe in `wp_posts` con `post_type = lls_story`. Termini e relazioni sono in `wp_terms`, `wp_term_taxonomy`, `wp_term_relationships` per le tassonomie registrate sul CPT. Le opzioni del plugin (stringhe UI, menu footer, testi login intro) vivono in `wp_options`.', 'language-learning-stories' ),
					'code_blocks'    => [
						[
							'label' => __( 'Post meta principali (tabella wp_postmeta)', 'language-learning-stories' ),
							'code'  => "_lls_known_lang          (it|pl|es) Lingua dell’interfaccia della storia\n"
								. "_lls_target_lang         (en|pl|it|es) Lingua che la storia insegna — vedi lls_story_target_lang_meta_key()\n"
								. "_lls_title_in_target_lang Titolo mostrato nella lingua obiettivo (opzionale)\n"
								. "_lls_sentences           Array di frasi (struttura editor)\n"
								. "_lls_images               Immagini associate alle posizioni frase\n"
								. "_lls_opening_image_id     ID attachment immagine apertura\n"
								. "_lls_story_coin_cost / _lls_story_coin_reward  Costo sblocco e ricompensa (coin)\n",
						],
						[
							'label' => __( 'User meta principali (tabella wp_usermeta)', 'language-learning-stories' ),
							'code'  => "_lls_user_known_lang        Lingua interfaccia profilo (it|pl|es) — lls_user_known_lang_meta_key()\n"
								. "_lls_user_learn_target_lang Lingua da imparare scelta (en|pl|it|es)\n"
								. "_lls_progress_{post_id}     Progresso: array [ 'completed' => int, 'story_text' => string ]\n"
								. "_lls_recent_stories         Array di story_id in ordine di accesso recente\n"
								. "_lls_daily_phrases_{Y-m-d}  Conteggio frasi completate per giorno (header)\n"
								. "_lls_coin_wallet / ledger / unlocked / rewards  Economia coin (vedi lls-coin-economy.php)\n"
								. "_lls_completed_phrases_log  Cronologia frasi completate\n",
						],
						[
							'label' => __( 'Opzioni (wp_options)', 'language-learning-stories' ),
							'code'  => "lls_ui_strings              Stringhe UI per lingua (catalogo in lls-ui-strings.php)\n"
								. "lls_footer_app_nav_*        Impostazioni menu footer\n"
								. "lls_login_intro_*           Testi pagina login multilingua\n",
						],
					],
				],
				[
					'reference_only' => true,
					'title'          => __( 'Lingue: convenzioni', 'language-learning-stories' ),
					'intro'          => __( 'Lingua «conosciuta» (interfaccia utente e meta storia `_lls_known_lang`): codici `it`, `pl`, `es`. Lingua «da imparare» / obiettivo storia (`_lls_target_lang` e profilo `_lls_user_learn_target_lang`): `en`, `pl`, `it`, `es`. Le funzioni `lls_get_user_known_lang()`, `lls_get_user_learn_target_lang()`, `lls_get_story_target_lang()` applicano default per ospiti e validazione. Le query libreria combinano meta con `lls_meta_query_stories_for_library()` (AND tra filtro interfaccia e filtro obiettivo).', 'language-learning-stories' ),
				],
				[
					'reference_only' => true,
					'title'          => __( 'Flusso progresso e AJAX', 'language-learning-stories' ),
					'intro'          => __( 'Il front-end della singola storia (`templates/single-lls_story.php`, JS `assets/lls-frontend.js`) invia il progresso con nonce `lls_save_progress`. L’handler in `LLS_Plugin` aggiorna `_lls_progress_{story_id}`, aggiorna `_lls_recent_stories`, può incrementare statistiche giornaliere, assegnare coin e premio completamento. Solo utenti loggati persistono il progresso.', 'language-learning-stories' ),
				],
				[
					'reference_only' => true,
					'title'          => __( 'Elementor Pro: query e integrazioni', 'language-learning-stories' ),
					'intro'          => __( 'Nel widget Loop Grid / Post, il campo «Query ID» (prefisso `post_query_` nel salvataggio) determina l’azione `do_action( "elementor/query/{id}", $wp_query, $widget )` in `pre_get_posts`. Il plugin intercetta:', 'language-learning-stories' ),
					'code_blocks'    => [
						[
							'label' => 'library',
							'code'  => "Filtro meta: storie coerenti con profilo (lls_meta_query_stories_for_library).\n"
								. "Filtri URL opzionali: ?lls_lib_cat=slug&lls_lib_tag=slug (shortcode [lls_library_grid_filters]).\n"
								. "File: includes/lls-elementor-library-query-filter.php",
						],
						[
							'label' => 'library_user',
							'code'  => "Solo storie «in corso» per l’utente loggato: post__in da lls_get_user_in_progress_story_ids().\n"
								. "Ospite: nessun risultato (post__in = 0).\n"
								. "File: includes/lls-elementor-library-query-filter.php + includes/lls-profile-shortcodes.php",
						],
						[
							'label' => __( 'Altri', 'language-learning-stories' ),
							'code'  => "Tag dinamici e gruppo «Language Learning Stories»: includes/lls-elementor-dynamic-tags.php\n"
								. "Font globali kit Elementor: includes/lls-elementor-global-fonts.php\n"
								. "Widget sblocco storia: includes/lls-elementor-widget-story-unlock.php",
						],
					],
				],
				[
					'reference_only' => true,
					'title'          => __( 'Moduli PHP (file in includes/)', 'language-learning-stories' ),
					'intro'          => __( 'Il file principale `language-learning-stories.php` carica questi include e istanzia la classe `LLS_Plugin` (CPT, tassonomie, salvataggio meta, AJAX, pagine admin).', 'language-learning-stories' ),
					'code_blocks'    => [
						[
							'label' => __( 'Mappa file → responsabilità', 'language-learning-stories' ),
							'code'  => "lls-ui-strings.php           — Stringhe UI multilingua, catalogo, filtri lls_get_merged_ui_strings\n"
								. "lls-header-shortcodes.php     — Shortcode header, lls_wrap_shortcode_html(), frasi giornaliere\n"
								. "lls-app-logo-shortcodes.php — Logo ReWrite / link library\n"
								. "lls-footer-app-nav-settings.php / lls-footer-shortcodes.php — Menu footer app\n"
								. "lls-profile-shortcodes.php  — Profilo, libreria, meta query, progresso, shortcode area utente\n"
								. "lls-library-grid-filters-shortcode.php + assets/lls-library-grid-filters.js — Filtri griglia\n"
								. "lls-elementor-library-query-filter.php — elementor/query/library e library_user\n"
								. "lls-coin-economy.php          — Coin, wallet, ledger, sblocco, template_redirect gate\n"
								. "lls-coin-shortcodes.php       — [coin], storico, data sblocco\n"
								. "lls-story-unlock-button.php   — [lls_story_unlock_button], widget Elementor unlock\n"
								. "lls-story-cards-rail-shortcode.php — Fascia orizzontale storie\n"
								. "lls-elementor-dynamic-tags.php — Tag dinamici per Loop/singole\n"
								. "lls-elementor-global-fonts.php — Tipografia kit Elementor (caricato su elementor/loaded)\n"
								. "lls-login-*.php, lls-register-*.php, lls-require-login-*.php, lls-login-intro-*.php — Auth front-end\n",
						],
					],
					'ex'             => [],
				],
				[
					'reference_only' => true,
					'title'          => __( 'Funzioni globali `lls_*` (indice)', 'language-learning-stories' ),
					'intro'          => __( 'Le API pubbliche sono funzioni prefissate `lls_*` nei file sopracitati. Esempi ricorrenti: lettura lingua utente (`lls_get_user_known_lang`, `lls_get_user_learn_target_lang`), meta storia (`lls_get_story_target_lang`, `lls_count_story_sentences`), query libreria (`lls_meta_query_stories_for_library`), progresso (`lls_collect_user_progress_story_ids`, `lls_get_user_in_progress_story_ids`), coin (`lls_user_can_access_story`, `lls_get_user_coin_balance`). Per l’elenco completo usa ricerca nel progetto su `function lls_`.', 'language-learning-stories' ),
					'code_blocks'    => [
						[
							'label' => __( 'Shortcode registrati (tag)', 'language-learning-stories' ),
							'code'  => "lls_app_logo, lls_header_greeting, lls_header_daily_phrases, lls_header_learn_language\n"
								. "lls_footer_app_nav, lls_footer_lang_summary\n"
								. "lls_profile_greeting, lls_profile_continue_stories, lls_library_stories, lls_profile_learn_language\n"
								. "lls_profile_account, lls_completed_phrases, lls_total_phrases, total_phrases\n"
								. "lls_library_grid_filters\n"
								. "lls_login, lls_login_intro, lls_require_login, lls_register\n"
								. "lls_coin, coin, lls_coin_history, coin_history, lls_story_unlock_date\n"
								. "lls_story_unlock_button, lls_story_cards_rail\n",
						],
					],
					'ex'             => [],
				],
			],
		],
	];
}
