<?php
/**
 * Plugin Name: Language Learning Stories
 * Description: Gestione storie con frasi, traduzioni e immagini per esercizi di traduzione.
 * Version:     0.2.1
 * Author:      ReadWrite
 * Text Domain: language-learning-stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/lls-ui-strings.php';
require_once __DIR__ . '/includes/lls-header-shortcodes.php';
require_once __DIR__ . '/includes/lls-app-logo-shortcodes.php';
require_once __DIR__ . '/includes/lls-footer-app-nav-settings.php';
require_once __DIR__ . '/includes/lls-footer-shortcodes.php';
require_once __DIR__ . '/includes/lls-profile-shortcodes.php';
require_once __DIR__ . '/includes/lls-coin-shortcodes.php';
require_once __DIR__ . '/includes/lls-login-shortcodes.php';
require_once __DIR__ . '/includes/lls-login-intro-settings.php';
require_once __DIR__ . '/includes/lls-login-intro-shortcodes.php';
require_once __DIR__ . '/includes/lls-require-login-shortcodes.php';
require_once __DIR__ . '/includes/lls-register-shortcodes.php';

define( 'LLS_PLUGIN_VERSION', '0.2.1' );

class LLS_Plugin {

	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ], 5 );
		add_action( 'init', [ $this, 'register_story_taxonomies' ], 6 );
		add_action( 'init', [ $this, 'maybe_flush_rewrite_rules' ], 20 );
		add_action( 'init', [ $this, 'maybe_migrate_remove_alt3' ], 25 );
		add_action( 'init', [ $this, 'maybe_seed_ui_strings_pl_es' ], 26 );
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_story_meta' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_init', [ $this, 'maybe_create_sample_story' ] );

		add_filter( 'template_include', [ $this, 'template_include_story' ], 99 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_header_shortcode_assets' ], 20 );
		add_action( 'wp_ajax_lls_save_progress', [ $this, 'ajax_save_progress' ] );
		add_action( 'wp_ajax_nopriv_lls_save_progress', [ $this, 'ajax_save_progress' ] );

		add_action( 'admin_menu', [ $this, 'add_story_lang_submenus' ], 11 );
		add_action( 'admin_menu', [ $this, 'mark_story_lang_submenu_classes' ], 999 );
		add_action( 'admin_menu', [ $this, 'add_translations_submenu' ], 25 );
		add_action( 'admin_menu', [ $this, 'add_documentation_submenu' ], 26 );
		add_action( 'admin_menu', [ $this, 'add_footer_app_nav_settings_submenu' ], 27 );
		add_action( 'admin_menu', [ $this, 'add_login_intro_settings_submenu' ], 28 );
		add_filter( 'submenu_file', [ $this, 'submenu_file_for_lls_lang_list' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_menu_lang_style' ] );
		add_action( 'admin_post_lls_save_ui_strings', [ $this, 'handle_save_ui_strings' ] );
		add_action( 'admin_post_lls_save_footer_app_nav_settings', [ $this, 'handle_save_footer_app_nav_settings' ] );
		add_action( 'admin_post_lls_save_login_intro_settings', [ $this, 'handle_save_login_intro_settings' ] );
		add_action( 'admin_post_lls_update_user_profile', [ $this, 'handle_update_user_profile' ] );

		add_filter( 'manage_lls_story_posts_columns', [ $this, 'story_list_columns' ] );
		add_action( 'manage_lls_story_posts_custom_column', [ $this, 'story_list_column_content' ], 10, 2 );

		add_action( 'restrict_manage_posts', [ $this, 'stories_admin_list_filters' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'filter_stories_admin_list_query' ] );
	}

	/**
	 * Colonne extra nell’elenco storie: lingua interfaccia e numero frasi.
	 *
	 * @param string[] $columns Colonne esistenti.
	 * @return string[]
	 */
	public function story_list_columns( $columns ) {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['lls_lang']   = __( 'Lingua', 'language-learning-stories' );
				$new['lls_frasi'] = __( 'Frasi', 'language-learning-stories' );
			}
		}
		return $new;
	}

	/**
	 * Contenuto colonne Lingua e Frasi.
	 *
	 * @param string $column Nome colonna.
	 * @param int    $post_id ID post.
	 */
	public function story_list_column_content( $column, $post_id ) {
		if ( 'lls_lang' === $column ) {
			$lang = get_post_meta( $post_id, '_lls_known_lang', true );
			if ( ! in_array( $lang, lls_known_lang_codes(), true ) ) {
				$lang = 'it';
			}
			$labels = [
				'it' => __( 'Italiano', 'language-learning-stories' ),
				'pl' => __( 'Polacco', 'language-learning-stories' ),
				'es' => __( 'Spagnolo', 'language-learning-stories' ),
			];
			echo esc_html( isset( $labels[ $lang ] ) ? $labels[ $lang ] : $lang );
			return;
		}
		if ( 'lls_frasi' === $column ) {
			$sentences = get_post_meta( $post_id, '_lls_sentences', true );
			$n         = is_array( $sentences ) ? count( $sentences ) : 0;
			echo esc_html( (string) (int) $n );
			return;
		}
	}

	/**
	 * Voci lingua subito dopo «Storie in inglese» (pos. 1–2). WP non ha un vero sottomenu annidato:
	 * l’indentazione è data da .lls-story-lang-sub in CSS (vedi mark_story_lang_submenu_classes).
	 */
	public function add_story_lang_submenus() {
		$parent = 'edit.php?post_type=lls_story';
		add_submenu_page(
			$parent,
			__( '...per gli italiani', 'language-learning-stories' ),
			__( '...per gli italiani', 'language-learning-stories' ),
			'edit_posts',
			'edit.php?post_type=lls_story&lls_admin_lang=it',
			'',
			1
		);
		add_submenu_page(
			$parent,
			__( '...per i polacchi', 'language-learning-stories' ),
			__( '...per i polacchi', 'language-learning-stories' ),
			'edit_posts',
			'edit.php?post_type=lls_story&lls_admin_lang=pl',
			'',
			2
		);
	}

	/**
	 * Aggiunge la classe CSS sulle voci lingua (indentazione come “figlie” della prima voce elenco).
	 */
	public function mark_story_lang_submenu_classes() {
		global $submenu;
		$parent = 'edit.php?post_type=lls_story';
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}
		foreach ( $submenu[ $parent ] as $key => $item ) {
			if ( ! isset( $item[2] ) || ! is_string( $item[2] ) ) {
				continue;
			}
			if ( false === strpos( $item[2], 'lls_admin_lang=' ) ) {
				continue;
			}
			$submenu[ $parent ][ $key ][4] = 'lls-story-lang-sub';
		}
	}

	/**
	 * Stili menu admin: indentazione voci lingua (tutte le pagine admin per chi vede il menu Storie).
	 */
	public function enqueue_admin_menu_lang_style() {
		if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$pto = get_post_type_object( 'lls_story' );
		if ( ! $pto || ! $pto->show_ui ) {
			return;
		}
		$handle = 'lls-admin-menu-lang';
		wp_register_style( $handle, false, [], LLS_PLUGIN_VERSION );
		wp_enqueue_style( $handle );
		$css = '
#adminmenu .wp-submenu li.lls-story-lang-sub > a {
	padding-left: 24px;
	position: relative;
	font-size: 12px;
	opacity: 0.92;
}
#adminmenu .wp-submenu li.lls-story-lang-sub > a::before {
	content: "";
	position: absolute;
	left: 10px;
	top: 50%;
	width: 4px;
	height: 4px;
	margin-top: -2px;
	border-radius: 50%;
	background: currentColor;
	opacity: 0.45;
}
#adminmenu .wp-submenu li.lls-story-lang-sub.current > a::before {
	opacity: 0.85;
}
';
		wp_add_inline_style( $handle, $css );
	}

	/**
	 * Evidenzia nel menu la voce corretta quando l’elenco è filtrato per lingua.
	 *
	 * @param string|null $submenu_file Valore impostato da edit.php.
	 * @param string      $parent_file  File genitore.
	 * @return string|null
	 */
	public function submenu_file_for_lls_lang_list( $submenu_file, $parent_file ) {
		if ( 'edit.php?post_type=lls_story' !== $parent_file ) {
			return $submenu_file;
		}
		$lang = isset( $_GET['lls_admin_lang'] ) ? sanitize_key( wp_unslash( $_GET['lls_admin_lang'] ) ) : '';
		if ( 'it' === $lang ) {
			return 'edit.php?post_type=lls_story&lls_admin_lang=it';
		}
		if ( 'pl' === $lang ) {
			return 'edit.php?post_type=lls_story&lls_admin_lang=pl';
		}
		return $submenu_file;
	}

	/**
	 * Filtri (lingua, categoria, tag) sopra l’elenco storie in admin.
	 *
	 * @param string $post_type Post type della schermata.
	 * @param string $which     'top' o 'bottom'.
	 */
	public function stories_admin_list_filters( $post_type, $which = 'top' ) {
		if ( 'lls_story' !== $post_type ) {
			return;
		}
		if ( 'bottom' === $which ) {
			return;
		}

		$lang_sel = isset( $_GET['lls_admin_lang'] ) ? sanitize_key( wp_unslash( $_GET['lls_admin_lang'] ) ) : '';
		$cat_sel  = isset( $_GET['lls_admin_cat'] ) ? (int) $_GET['lls_admin_cat'] : 0;
		$tag_sel  = isset( $_GET['lls_admin_tag'] ) ? (int) $_GET['lls_admin_tag'] : 0;

		?>
		<select name="lls_admin_lang" id="lls_admin_lang" class="postform" style="float:none;display:inline-block;max-width:14rem;margin-right:8px;">
			<option value=""><?php esc_html_e( 'Tutte le lingue', 'language-learning-stories' ); ?></option>
			<option value="it" <?php selected( $lang_sel, 'it' ); ?>><?php esc_html_e( 'Italiano', 'language-learning-stories' ); ?></option>
			<option value="pl" <?php selected( $lang_sel, 'pl' ); ?>><?php esc_html_e( 'Polacco', 'language-learning-stories' ); ?></option>
			<option value="es" <?php selected( $lang_sel, 'es' ); ?>><?php esc_html_e( 'Spagnolo', 'language-learning-stories' ); ?></option>
		</select>
		<?php
		wp_dropdown_categories(
			[
				'show_option_all' => __( 'Tutte le categorie', 'language-learning-stories' ),
				'taxonomy'        => 'lls_story_category',
				'name'            => 'lls_admin_cat',
				'id'              => 'lls_admin_cat',
				'orderby'         => 'name',
				'hierarchical'    => true,
				'depth'           => 0,
				'show_count'      => false,
				'hide_empty'      => false,
				'selected'        => $cat_sel,
				'value_field'     => 'term_id',
				'class'           => 'postform',
			]
		);
		wp_dropdown_categories(
			[
				'show_option_all' => __( 'Tutti i tag', 'language-learning-stories' ),
				'taxonomy'        => 'lls_story_tag',
				'name'            => 'lls_admin_tag',
				'id'              => 'lls_admin_tag',
				'orderby'         => 'name',
				'hierarchical'    => false,
				'show_count'      => false,
				'hide_empty'      => false,
				'selected'        => $tag_sel,
				'value_field'     => 'term_id',
				'class'           => 'postform',
			]
		);
	}

	/**
	 * Applica filtri lingua / categoria / tag alla query dell’elenco storie in admin.
	 *
	 * @param WP_Query $query Query principale.
	 */
	public function filter_stories_admin_list_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		global $pagenow;
		if ( 'edit.php' !== $pagenow ) {
			return;
		}
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		if ( 'lls_story' !== $post_type ) {
			return;
		}

		$lang = isset( $_GET['lls_admin_lang'] ) ? sanitize_key( wp_unslash( $_GET['lls_admin_lang'] ) ) : '';
		if ( $lang && function_exists( 'lls_known_lang_codes' ) && in_array( $lang, lls_known_lang_codes(), true ) ) {
			$meta_query   = $query->get( 'meta_query' );
			$meta_query   = is_array( $meta_query ) ? $meta_query : [];

			// Italiano è il default anche senza meta salvato (come in elenco e editor).
			if ( 'it' === $lang ) {
				$meta_query[] = [
					'relation' => 'OR',
					[
						'key'     => '_lls_known_lang',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => '_lls_known_lang',
						'value'   => '',
						'compare' => '=',
					],
					[
						'key'     => '_lls_known_lang',
						'value'   => 'it',
						'compare' => '=',
					],
				];
			} else {
				$meta_query[] = [
					'key'   => '_lls_known_lang',
					'value' => $lang,
				];
			}
			$query->set( 'meta_query', $meta_query );
		}

		$cat_id = isset( $_GET['lls_admin_cat'] ) ? (int) $_GET['lls_admin_cat'] : 0;
		$tag_id = isset( $_GET['lls_admin_tag'] ) ? (int) $_GET['lls_admin_tag'] : 0;

		$tax_query = [];
		if ( $cat_id > 0 ) {
			$tax_query[] = [
				'taxonomy' => 'lls_story_category',
				'field'    => 'term_id',
				'terms'    => [ $cat_id ],
			];
		}
		if ( $tag_id > 0 ) {
			$tax_query[] = [
				'taxonomy' => 'lls_story_tag',
				'field'    => 'term_id',
				'terms'    => [ $tag_id ],
			];
		}
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		if ( ! empty( $tax_query ) ) {
			$query->set( 'tax_query', $tax_query );
		}
	}

	/**
	 * Sottomenu Traduzioni (stringhe interfaccia per it / pl / es).
	 */
	public function add_translations_submenu() {
		add_submenu_page(
			'edit.php?post_type=lls_story',
			__( 'Traduzioni interfaccia', 'language-learning-stories' ),
			__( 'Traduzioni', 'language-learning-stories' ),
			'manage_options',
			'lls-translations',
			[ $this, 'render_translations_page' ]
		);
	}

	/**
	 * Pagina Documentazione: shortcode del plugin con spiegazioni e attributi.
	 */
	public function add_documentation_submenu() {
		add_submenu_page(
			'edit.php?post_type=lls_story',
			__( 'Documentazione shortcode', 'language-learning-stories' ),
			__( 'Documentazione', 'language-learning-stories' ),
			'edit_posts',
			'lls-documentation',
			[ $this, 'render_documentation_page' ]
		);
	}

	/**
	 * Impostazioni testi, URL e dimensioni del menu [lls_footer_app_nav].
	 */
	public function add_footer_app_nav_settings_submenu() {
		add_submenu_page(
			'edit.php?post_type=lls_story',
			__( 'Menu footer (navigazione app)', 'language-learning-stories' ),
			__( 'Menu footer app', 'language-learning-stories' ),
			'manage_options',
			'lls-footer-app-nav',
			[ $this, 'render_footer_app_nav_settings_page' ]
		);
	}

	/**
	 * Pagina impostazioni menu footer app.
	 */
	public function render_footer_app_nav_settings_page() {
		if ( function_exists( 'lls_footer_app_nav_render_settings_page' ) ) {
			lls_footer_app_nav_render_settings_page();
		}
	}

	/**
	 * Testi e link iscrizione per [lls_login_intro].
	 */
	public function add_login_intro_settings_submenu() {
		add_submenu_page(
			'edit.php?post_type=lls_story',
			__( 'Pagina Login', 'language-learning-stories' ),
			__( 'Pagina Login', 'language-learning-stories' ),
			'manage_options',
			'lls-login-intro',
			[ $this, 'render_login_intro_settings_page' ]
		);
	}

	/**
	 * Pagina impostazioni testi login.
	 */
	public function render_login_intro_settings_page() {
		if ( function_exists( 'lls_login_intro_render_settings_page' ) ) {
			lls_login_intro_render_settings_page();
		}
	}

	/**
	 * Salva opzione lls_ui_strings dal form Traduzioni.
	 */
	public function handle_save_ui_strings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'language-learning-stories' ) );
		}
		check_admin_referer( 'lls_save_ui_strings', 'lls_ui_strings_nonce' );
		$raw = isset( $_POST['lls_ui_strings'] ) ? wp_unslash( $_POST['lls_ui_strings'] ) : [];
		update_option( 'lls_ui_strings', lls_sanitize_ui_strings_option( $raw ) );
		$redirect = isset( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : '';
		if ( ! $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=lls_story&page=lls-translations' );
		}
		wp_safe_redirect( add_query_arg( 'lls_ui_saved', '1', $redirect ) );
		exit;
	}

	/**
	 * Salva opzione menu footer app (shortcode lls_footer_app_nav).
	 */
	public function handle_save_footer_app_nav_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'language-learning-stories' ) );
		}
		check_admin_referer( 'lls_save_footer_app_nav_settings', 'lls_footer_app_nav_nonce' );
		$raw = isset( $_POST['lls_footer_app_nav'] ) ? wp_unslash( $_POST['lls_footer_app_nav'] ) : [];
		update_option( LLS_FOOTER_APP_NAV_OPTION, lls_footer_app_nav_sanitize_settings( $raw ) );
		$redirect = isset( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : '';
		if ( ! $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=lls_story&page=lls-footer-app-nav' );
		}
		wp_safe_redirect( add_query_arg( 'lls_footer_nav_saved', '1', $redirect ) );
		exit;
	}

	/**
	 * Salva testi pagina login (shortcode lls_login_intro).
	 */
	public function handle_save_login_intro_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'language-learning-stories' ) );
		}
		check_admin_referer( 'lls_save_login_intro_settings', 'lls_login_intro_nonce' );
		$raw = isset( $_POST['lls_login_intro'] ) ? wp_unslash( $_POST['lls_login_intro'] ) : [];
		update_option( LLS_LOGIN_INTRO_OPTION, lls_login_intro_sanitize_settings( $raw ) );
		$redirect = isset( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : '';
		if ( ! $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=lls_story&page=lls-login-intro' );
		}
		wp_safe_redirect( add_query_arg( 'lls_login_intro_saved', '1', $redirect ) );
		exit;
	}

	/**
	 * Salva da front-end nome visualizzato, email e (opzionale) nuova password dell’utente collegato.
	 */
	public function handle_update_user_profile() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'language-learning-stories' ) );
		}

		$user_id = get_current_user_id();
		$referer = isset( $_POST['_wp_http_referer'] ) ? wp_unslash( $_POST['_wp_http_referer'] ) : '';
		$redirect = $referer ? wp_validate_redirect( esc_url_raw( $referer ), home_url( '/' ) ) : home_url( '/' );
		$redirect = remove_query_arg( 'lls_account', $redirect );

		$nonce = isset( $_POST['lls_profile_nonce'] ) ? wp_unslash( $_POST['lls_profile_nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'lls_update_user_profile' ) ) {
			wp_safe_redirect( add_query_arg( 'lls_account', 'err_nonce', $redirect ) );
			exit;
		}

		$display_name = isset( $_POST['lls_display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lls_display_name'] ) ) : '';
		$email        = isset( $_POST['lls_user_email'] ) ? sanitize_email( wp_unslash( $_POST['lls_user_email'] ) ) : '';
		$pass1        = isset( $_POST['lls_pass1'] ) ? (string) wp_unslash( $_POST['lls_pass1'] ) : '';
		$pass2        = isset( $_POST['lls_pass2'] ) ? (string) wp_unslash( $_POST['lls_pass2'] ) : '';
		$new_login    = isset( $_POST['lls_user_login'] ) ? sanitize_user( wp_unslash( $_POST['lls_user_login'] ), true ) : '';
		$known_lang   = isset( $_POST['lls_user_known_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['lls_user_known_lang'] ) ) : 'it';
		if ( function_exists( 'lls_known_lang_codes' ) && ! in_array( $known_lang, lls_known_lang_codes(), true ) ) {
			$known_lang = 'it';
		}

		if ( ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( 'lls_account', 'err_email', $redirect ) );
			exit;
		}

		$current = get_userdata( $user_id );
		if ( ! $current ) {
			wp_safe_redirect( add_query_arg( 'lls_account', 'err_update', $redirect ) );
			exit;
		}

		if ( function_exists( 'lls_profile_maybe_update_user_login' ) ) {
			$login_res = lls_profile_maybe_update_user_login( $user_id, $new_login, $current->user_login );
			if ( is_wp_error( $login_res ) ) {
				$code = $login_res->get_error_code();
				$err  = 'err_login_invalid';
				if ( 'existing_user_login' === $code ) {
					$err = 'err_login_taken';
				}
				wp_safe_redirect( add_query_arg( 'lls_account', $err, $redirect ) );
				exit;
			}
		}

		$current = get_userdata( $user_id );
		if ( ! $current ) {
			wp_safe_redirect( add_query_arg( 'lls_account', 'err_update', $redirect ) );
			exit;
		}

		if ( $display_name === '' ) {
			$display_name = $current->display_name !== '' ? $current->display_name : $current->user_login;
		}

		$args = [
			'ID'           => $user_id,
			'display_name' => $display_name,
			'user_email'   => $email,
		];

		if ( $pass1 !== '' || $pass2 !== '' ) {
			if ( $pass1 !== $pass2 ) {
				wp_safe_redirect( add_query_arg( 'lls_account', 'err_pass_match', $redirect ) );
				exit;
			}
			$min_len = (int) apply_filters( 'lls_profile_account_min_password_length', 8 );
			if ( $min_len > 0 && strlen( $pass1 ) < $min_len ) {
				wp_safe_redirect( add_query_arg( 'lls_account', 'err_pass_short', $redirect ) );
				exit;
			}
			$args['user_pass'] = $pass1;
		}

		$result = wp_update_user( $args );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			$flag = 'err_update';
			if ( 'existing_user_email' === $code ) {
				$flag = 'err_email_taken';
			}
			if ( 'existing_user_login' === $code ) {
				$flag = 'err_login_taken';
			}
			wp_safe_redirect( add_query_arg( 'lls_account', $flag, $redirect ) );
			exit;
		}

		if ( function_exists( 'lls_user_known_lang_meta_key' ) ) {
			update_user_meta( $user_id, lls_user_known_lang_meta_key(), $known_lang );
		}

		wp_safe_redirect( add_query_arg( 'lls_account', 'ok', $redirect ) );
		exit;
	}

	/**
	 * Pagina amministrativa: tutte le stringhe per lingua.
	 */
	public function render_translations_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$catalog = lls_get_ui_string_catalog();
		$langs   = lls_known_lang_codes();
		$labels  = [
			'it' => __( 'Italiano (predefinito)', 'language-learning-stories' ),
			'pl' => __( 'Polacco', 'language-learning-stories' ),
			'es' => __( 'Spagnolo', 'language-learning-stories' ),
		];

		$saved = get_option( 'lls_ui_strings', [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		?>
		<div class="wrap lls-translations-wrap">
			<h1><?php esc_html_e( 'Traduzioni interfaccia (front-end)', 'language-learning-stories' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Il sito è sempre per imparare l’inglese: qui modifichi solo i testi dell’interfaccia in base alla «lingua che conosci» (italiano, polacco, spagnolo). Le frasi delle storie le inserisci tu nell’editor della singola storia.', 'language-learning-stories' ); ?>
			</p>
			<?php
			if ( isset( $_GET['lls_ui_saved'] ) && '1' === $_GET['lls_ui_saved'] ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Traduzioni salvate.', 'language-learning-stories' ) . '</p></div>';
			}
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lls-translations-form">
				<input type="hidden" name="action" value="lls_save_ui_strings" />
				<?php wp_nonce_field( 'lls_save_ui_strings', 'lls_ui_strings_nonce' ); ?>
				<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ); ?>" />

				<?php foreach ( $catalog as $group_key => $group ) : ?>
					<div class="lls-translations-group postbox" style="margin-top:18px;padding:12px 16px;">
						<h2 style="margin-top:0;"><?php echo esc_html( $group['title'] ); ?></h2>
						<table class="widefat striped" style="table-layout:fixed;">
							<thead>
								<tr>
									<th style="width:22%;"><?php esc_html_e( 'Voce', 'language-learning-stories' ); ?></th>
									<?php foreach ( $langs as $lc ) : ?>
										<th><?php echo esc_html( $labels[ $lc ] ); ?></th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $group['strings'] as $key => $def ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $def['label'] ); ?></strong>
											<?php if ( ! empty( $def['default'] ) ) : ?>
												<br><span class="description"><?php esc_html_e( 'Predefinito:', 'language-learning-stories' ); ?> <code><?php echo esc_html( wp_strip_all_tags( (string) $def['default'] ) ); ?></code></span>
											<?php endif; ?>
										</td>
										<?php foreach ( $langs as $lc ) : ?>
											<td>
												<?php
												$val = '';
												if ( isset( $saved[ $lc ][ $key ] ) && is_string( $saved[ $lc ][ $key ] ) ) {
													$val = $saved[ $lc ][ $key ];
												}
												$name = 'lls_ui_strings[' . $lc . '][' . esc_attr( $key ) . ']';
												if ( 'rewrite_success_html' === $key ) {
													?>
													<textarea name="<?php echo esc_attr( 'lls_ui_strings[' . $lc . '][' . $key . ']' ); ?>" class="large-text code" rows="4"><?php echo esc_textarea( $val ); ?></textarea>
													<?php
												} else {
													?>
													<textarea name="<?php echo esc_attr( 'lls_ui_strings[' . $lc . '][' . $key . ']' ); ?>" class="large-text" rows="3"><?php echo esc_textarea( $val ); ?></textarea>
													<?php
												}
												?>
											</td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Salva traduzioni', 'language-learning-stories' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Documentazione shortcode (menu Storie → Documentazione).
	 */
	public function render_documentation_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'language-learning-stories' ) );
		}

		$documentation_groups = [
			[
				'group_title' => __( 'Shortcode per intestazione (header)', 'language-learning-stories' ),
				'group_intro' => __( 'Da usare nel tema o nel contenuto dell’header: logo app, saluto con link all’area personale e riepilogo giornaliero delle frasi completate.', 'language-learning-stories' ),
				'sections'    => [
					[
						'tag'    => 'lls_app_logo',
						'title'  => __( 'Logo / nome app (ReWrite)', 'language-learning-stories' ),
						'intro'  => __( 'Wordmark «ReWrite» con R e W grandi e e / rite piccoli, stessa gerarchia tipografica del menu footer (CSS dedicato lls-app-logo.css, variabili --lls-app-nav-big / --lls-app-nav-rest). Link predefinito: pagina library (/library/ sotto la home; filtro lls_app_logo_default_path per lo slug).', 'language-learning-stories' ),
						'where'  => __( 'Header, barra superiore o inizio contenuto.', 'language-learning-stories' ),
						'attrs'  => [
							[
								'name'     => 'path',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'Slug percorso sotto la home, es. library. Se vuoto e anche url vuoto → library. Ha priorità se url è vuoto.', 'language-learning-stories' ),
								'help'     => '',
							],
							[
								'name'     => 'url',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'URL completo o percorso che inizia con /. Se vuoto si usa path o il default library.', 'language-learning-stories' ),
								'help'     => '',
							],
							[
								'name'     => 'link',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => '1 (predefinito), 0 o false per solo testo senza link.',
								'help'     => '',
							],
						],
						'ex'     => [ '[lls_app_logo]', '[lls_app_logo path="library"]', '[lls_app_logo url="/community/"]', '[lls_app_logo link="0"]' ],
					],
					[
						'tag'    => 'lls_header_greeting',
						'title'  => __( 'Saluto (header)', 'language-learning-stories' ),
						'intro'  => __( 'Per utenti connessi mostra «Ciao, [nome].» come link verso l’area personale (predefinito: /area-personale/ rispetto alla home). Per ospiti: link al login. URL area: filtro lls_account_area_url o attributo path.', 'language-learning-stories' ),
						'where'  => __( 'Adatto all’header o al top bar: inserisci lo shortcode nel contenuto di un blocco Shortcode, in un widget HTML o nel file del tema con do_shortcode().', 'language-learning-stories' ),
						'attrs'  => [
							[
								'name'     => 'path',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'Slug percorso senza slash, es. area-personale. Se vuoto si usa /area-personale/ o il filtro lls_account_area_url.', 'language-learning-stories' ),
								'help'     => __( 'Sovrascrive solo il percorso sotto la home (non l’URL completo).', 'language-learning-stories' ),
							],
						],
						'ex'     => [ '[lls_header_greeting]', '[lls_header_greeting path="area-personale"]' ],
					],
					[
						'tag'    => 'lls_header_daily_phrases',
						'title'  => __( 'Frasi completate ultimi 7 giorni (header)', 'language-learning-stories' ),
						'intro'  => __( 'Mostra sette caselle (un giorno ciascuna) con il numero di frasi completate in quel giorno per l’utente collegato. Per gli ospiti i valori sono zero. I dati si aggiornano quando il progresso viene salvato dalle storie.', 'language-learning-stories' ),
						'where'  => __( 'Stesso contesto del saluto header.', 'language-learning-stories' ),
						'attrs'  => [],
						'ex'     => [ '[lls_header_daily_phrases]' ],
					],
				],
			],
			[
				'group_title' => __( 'Shortcode per libreria', 'language-learning-stories' ),
				'group_intro' => __( 'Pagina dove l’utente sfoglia tutte le storie disponibili nella sua lingua interfaccia (meta _lls_known_lang allineata al profilo o all’attributo lang).', 'language-learning-stories' ),
				'sections'    => [
					[
						'tag'    => 'lls_library_stories',
						'title'  => __( 'Elenco storie (lingua interfaccia)', 'language-learning-stories' ),
						'intro'  => __( 'Elenco delle storie pubblicate il cui _lls_known_lang coincide con la «lingua che conosci» del profilo (funzione lls_get_user_known_lang): per ospiti si assume italiano. Include anche storie senza meta o con valore vuoto se la lingua è italiano (come nel resto del plugin). Stesso layout di [lls_profile_continue_stories]: titolo, categorie e tag, trama, barra progresso se l’utente è connesso, pulsante «Continue story». Ordine: ultima modifica decrescente. Filtro opzionale: lls_meta_query_stories_for_interface_lang.', 'language-learning-stories' ),
						'where'  => __( 'Pagina dedicata alla libreria (es. slug /libreria/) o qualsiasi contenuto dove vuoi l’elenco completo filtrato per lingua.', 'language-learning-stories' ),
						'attrs'  => [
							[
								'name'     => 'limit',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'Intero 1–100. Predefinito: 50.', 'language-learning-stories' ),
								'help'     => __( 'Numero massimo di storie nella pagina.', 'language-learning-stories' ),
							],
							[
								'name'     => 'words',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'Intero 5–80. Predefinito: 40.', 'language-learning-stories' ),
								'help'     => __( 'Lunghezza trama in parole.', 'language-learning-stories' ),
							],
							[
								'name'     => 'lang',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => 'it, pl, es',
								'help'     => __( 'Forza una lingua invece di usare il profilo. Vuoto = profilo (o it per ospiti).', 'language-learning-stories' ),
							],
							[
								'name'     => 'show_lang',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => '1 (predefinito), 0 o false per nascondere la riga «Stories for: …».',
								'help'     => __( 'Mostra sopra l’elenco la lingua usata per il filtro.', 'language-learning-stories' ),
							],
						],
						'ex'     => [ '[lls_library_stories]', '[lls_library_stories limit="5" words="30"]', '[lls_library_stories limit="30" words="30" show_lang="0"]' ],
					],
				],
			],
			[
				'group_title' => __( 'Shortcode per area utente (personale)', 'language-learning-stories' ),
				'group_intro' => __( 'Da usare sulla pagina area personale (es. /area-personale/): saluto, storie in corso, modifica account, accesso e iscrizione. Richiedono in genere utente connesso, salvo i messaggi per ospiti o i moduli login/iscrizione dove indicato.', 'language-learning-stories' ),
				'sections'    => [
					[
						'tag'    => 'lls_login',
						'title'  => __( 'Accesso (login)', 'language-learning-stories' ),
						'intro'  => __( 'Modulo username/email e password con lo stesso aspetto dell’area profilo (Manrope, campi e pulsante .lls-btn). L’accesso avviene con wp_signon sul server: in caso di errore si resta sulla stessa pagina con un messaggio (nessun redirect a wp-login.php). Dopo il login corretto si va all’URL in redirect, oppure a redirect_to / lls_redirect_to nella query string se l’attributo redirect è vuoto (utile con [lls_require_login]). Link a password dimenticata e, se le registrazioni sono attive, a iscrizione.', 'language-learning-stories' ),
						'where'  => __( 'Pagina «Accedi», sidebar, o area personale per gli ospiti.', 'language-learning-stories' ),
						'attrs'  => [
							[
								'name'     => 'redirect',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'URL assoluto dopo il login. Vuoto = parametro GET redirect_to o lls_redirect_to se presente, altrimenti pagina corrente (o home).', 'language-learning-stories' ),
								'help'     => __( 'Validato con wp_validate_redirect rispetto alla home del sito. Usato da [lls_require_login] tramite redirect_to nell’URL.', 'language-learning-stories' ),
							],
							[
								'name'     => 'title',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'Testo del titolo sopra il modulo. Vuoto = nessun titolo.', 'language-learning-stories' ),
								'help'     => __( 'Usa la classe titolo storia (.lls-story-title) per coerenza visiva.', 'language-learning-stories' ),
							],
							[
								'name'     => 'logged_in',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => 'message (predefinito), hide',
								'help'     => __( 'Se l’utente è già connesso: mostra messaggio e pulsante «Esci», oppure non mostra nulla.', 'language-learning-stories' ),
							],
						],
						'ex'     => [ '[lls_login]', '[lls_login title="Accedi" redirect="/area-personale/"]', '[lls_login logged_in="hide"]' ],
					],
					[
						'tag'    => 'lls_login_intro',
						'title'  => __( 'Intestazione pagina login (testi multilingua)', 'language-learning-stories' ),
						'intro'  => __( 'Blocco da mettere sopra [lls_login]. Quattro lingue (English, Español, Italiano, Polski): al clic si aggiornano saluto, testo e riga iscrizione; la scelta resta in localStorage. Lingua iniziale predefinita: inglese. I testi si modificano da Storie → Pagina Login (campo vuoto = default del plugin). Link iscrizione predefinito /registrati/ (configurabile in quella pagina). Filtro PHP lls_login_intro_strings per override da codice.', 'language-learning-stories' ),
						'where'  => __( 'Pagina di accesso, sopra al modulo login.', 'language-learning-stories' ),
						'attrs'  => [
							[
								'name'     => 'register_url',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'Sovrascrive l’URL del link iscrizione rispetto a Storie → Pagina Login.', 'language-learning-stories' ),
								'help'     => '',
							],
						],
						'ex'     => [ '[lls_login_intro]', '[lls_login_intro register_url="/altra-pagina/"]' ],
					],
					[
						'tag'    => 'lls_require_login',
						'title'  => __( 'Obbligo accesso (redirect ospiti)', 'language-learning-stories' ),
						'intro'  => __( 'Mettilo nel contenuto della pagina (es. area personale): se l’utente non è loggato, viene reindirizzato subito alla pagina di login, senza mostrare il resto della pagina. L’URL di ritorno dopo il login viene passato come redirect_to (compatibile con [lls_login]). Se l’attributo login è vuoto si usa wp-login.php di WordPress.', 'language-learning-stories' ),
						'where'  => __( 'In cima al contenuto delle pagine riservate agli utenti registrati.', 'language-learning-stories' ),
						'attrs'  => [
							[
								'name'     => 'login',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'URL assoluto o percorso sotto la home della pagina con [lls_login], es. /accedi/. Vuoto = wp-login.php.', 'language-learning-stories' ),
								'help'     => __( 'Il redirect di ritorno è la pagina che contiene questo shortcode.', 'language-learning-stories' ),
							],
						],
						'ex'     => [ '[lls_require_login]', '[lls_require_login login="/accedi/"]' ],
					],
					[
						'tag'    => 'lls_register',
						'title'  => __( 'Iscrizione (registrazione)', 'language-learning-stories' ),
						'intro'  => __( 'Username, email e una sola password: controlli essenziali (campi non vuoti, email formalmente valida). Se l’email è già registrata l’iscrizione viene rifiutata. Se lo username è già usato WordPress lo segnala. Dopo la registrazione l’utente viene collegato automaticamente e reindirizzato. Non viene inviata email di conferma all’utente (filtro wp_send_new_user_notification_to_user). Richiede «Chiunque può registrarsi» in Impostazioni → Generale.', 'language-learning-stories' ),
						'where'  => __( 'Pagina «Iscriviti» o accanto al modulo login.', 'language-learning-stories' ),
						'attrs'  => [
							[
								'name'     => 'redirect',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'URL dopo l’iscrizione (e login automatico). Vuoto = pagina corrente.', 'language-learning-stories' ),
								'help'     => __( 'Validato con wp_validate_redirect.', 'language-learning-stories' ),
							],
							[
								'name'     => 'title',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'Titolo sopra il modulo. Vuoto = nessun titolo.', 'language-learning-stories' ),
								'help'     => '',
							],
							[
								'name'     => 'logged_in',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => 'message (predefinito), hide',
								'help'     => __( 'Se l’utente è già connesso: messaggio e «Esci» oppure nulla.', 'language-learning-stories' ),
							],
						],
						'ex'     => [ '[lls_register]', '[lls_register title="Iscriviti" redirect="/area-personale/"]', '[lls_register logged_in="hide"]' ],
					],
					[
						'tag'    => 'lls_profile_greeting',
						'title'  => __( 'Saluto area personale', 'language-learning-stories' ),
						'intro'  => __( 'Versione per pagine «Area personale»: saluto con nome; per ospiti messaggio e link «Accedi». Opzionalmente puoi mostrare il link per uscire dall’account.', 'language-learning-stories' ),
						'where'  => __( 'Pagina dedicata al profilo o dashboard utente.', 'language-learning-stories' ),
						'attrs'  => [
							[
								'name'     => 'logout',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => '0 (predefinito), 1 oppure true',
								'help'     => __( 'Se impostato a 1 o true, sotto al saluto viene aggiunto il link «Esci» (logout) con ritorno alla pagina corrente.', 'language-learning-stories' ),
							],
						],
						'ex'     => [ '[lls_profile_greeting]', '[lls_profile_greeting logout="1"]' ],
					],
					[
						'tag'    => 'lls_profile_continue_stories',
						'title'  => __( 'Storie in corso', 'language-learning-stories' ),
						'intro'  => __( 'Elenco delle storie che l’utente ha iniziato ma non completato: titolo con link, categorie e tag (tassonomie lls_story_category e lls_story_tag, con link agli archivi), trama (estratto o inizio contenuto), barra di avanzamento frasi completate / totali e pulsante «Continue story». Ordine: ultime storie con salvataggio progresso, poi le altre. Le storie completate al 100% non compaiono.', 'language-learning-stories' ),
						'where'  => __( 'Pagina area personale insieme al saluto profilo.', 'language-learning-stories' ),
						'attrs'  => [
							[
								'name'     => 'limit',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'Numero intero da 1 a 50. Predefinito: 10.', 'language-learning-stories' ),
								'help'     => __( 'Quante storie mostrare al massimo.', 'language-learning-stories' ),
							],
							[
								'name'     => 'words',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'Numero intero da 5 a 80. Predefinito: 40.', 'language-learning-stories' ),
								'help'     => __( 'Lunghezza della trama in parole (estratto o contenuto tagliato).', 'language-learning-stories' ),
							],
						],
						'ex'     => [ '[lls_profile_continue_stories]', '[lls_profile_continue_stories limit="5" words="30"]' ],
					],
					[
						'tag'    => 'lls_profile_account',
						'title'  => __( 'Dati account', 'language-learning-stories' ),
						'intro'  => __( 'Vista riepilogo con pulsante «Modifica»: nome accesso, nome visualizzato, email, lingua che conosci (select Italiano / Polacco / Spagnolo, salvata in user meta), password. La password in chiaro in vista è una copia salvata con wp_set_password (vedi filtro lls_store_plain_password_for_profile_display). Per leggere la lingua da codice: lls_get_user_known_lang().', 'language-learning-stories' ),
						'where'  => __( 'Pagina area personale accanto agli altri shortcode profilo.', 'language-learning-stories' ),
						'attrs'  => [],
						'ex'     => [ '[lls_profile_account]' ],
					],
				],
			],
			[
				'group_title' => __( 'Shortcode per footer (navigazione app)', 'language-learning-stories' ),
				'group_intro' => __( 'Barra orizzontale da widget footer o contenuto pagina: quattro voci con lettera grande (L, C, simbolo riproduzione per Play, P) e sotto l’etichetta. Link predefiniti: /library/, /community/, /play/, /area-personale/. Evidenziazione voce corrente in base all’URL.', 'language-learning-stories' ),
				'sections'    => [
					[
						'tag'    => 'lls_footer_app_nav',
						'title'  => __( 'Menu footer stile app', 'language-learning-stories' ),
						'intro'  => __( 'Navigazione responsive con Manrope e colori LLS: prima lettera grande e resto del nome in piccolo (es. L+ibrary). Sottolineatura solo su hover o sulla pagina corrente. Da Storie → Menu footer app puoi modificare testi, URL e dimensioni senza codice. In alternativa: filtro lls_footer_app_nav_items o attributi *_path nello shortcode.', 'language-learning-stories' ),
						'where'  => __( 'Widget footer, blocco HTML, o file footer.php con do_shortcode().', 'language-learning-stories' ),
						'attrs'  => [
							[
								'name'     => 'library_path',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'Slug percorso sotto la home. Predefinito: library.', 'language-learning-stories' ),
								'help'     => __( 'Solo caratteri sicuri per URL; slash iniziale/finale ignorati.', 'language-learning-stories' ),
							],
							[
								'name'     => 'community_path',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'Predefinito: community.', 'language-learning-stories' ),
								'help'     => __( 'Percorso della pagina Community sotto la home.', 'language-learning-stories' ),
							],
							[
								'name'     => 'play_path',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'Predefinito: play.', 'language-learning-stories' ),
								'help'     => __( 'Percorso della pagina Play sotto la home.', 'language-learning-stories' ),
							],
							[
								'name'     => 'profile_path',
								'required' => __( 'No', 'language-learning-stories' ),
								'values'   => __( 'Predefinito: area-personale.', 'language-learning-stories' ),
								'help'     => __( 'Percorso dell’area personale (profilo).', 'language-learning-stories' ),
							],
						],
						'ex'     => [ '[lls_footer_app_nav]', '[lls_footer_app_nav profile_path="area-personale"]' ],
					],
				],
			],
		];

		?>
		<div class="wrap lls-documentation-wrap">
			<h1><?php esc_html_e( 'Documentazione shortcode', 'language-learning-stories' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Shortcode del plugin Language Learning Stories, raggruppati per tipo di pagina (intestazione, libreria, area utente, footer). Incollali nel contenuto di una pagina (blocco Shortcode), in un widget o nel tema; WordPress deve elaborarli (il tema elabora di solito il contenuto delle pagine automaticamente).', 'language-learning-stories' ); ?>
			</p>
			<style>
				.lls-documentation-wrap .lls-doc-code {
					background: #f6f7f7;
					border: 1px solid #c3c4c7;
					padding: 12px 14px;
					overflow: auto;
					font-size: 13px;
					line-height: 1.5;
					margin: 0.5em 0 0;
				}
				.lls-documentation-wrap .lls-doc-section h2 code {
					font-size: 1.05em;
				}
				.lls-documentation-wrap table.lls-doc-attrs th {
					text-align: left;
				}
				.lls-documentation-wrap .lls-doc-group {
					margin-top: 2.25rem;
					padding-top: 0.25rem;
					border-top: 1px solid #c3c4c7;
				}
				.lls-documentation-wrap .lls-doc-group:first-of-type {
					margin-top: 1.25rem;
					border-top: none;
					padding-top: 0;
				}
				.lls-documentation-wrap .lls-doc-group__title {
					margin: 0 0 0.35rem;
					font-size: 1.15em;
				}
				.lls-documentation-wrap .lls-doc-group__intro {
					margin: 0 0 1rem;
					color: #646970;
					max-width: 920px;
				}
			</style>

			<?php foreach ( $documentation_groups as $lls_doc_group_index => $group ) : ?>
				<?php
				$lls_doc_group_id = 'lls-doc-group-' . (int) $lls_doc_group_index;
				?>
				<section class="lls-doc-group" aria-labelledby="<?php echo esc_attr( $lls_doc_group_id ); ?>">
					<h2 class="lls-doc-group__title" id="<?php echo esc_attr( $lls_doc_group_id ); ?>">
						<?php echo esc_html( $group['group_title'] ); ?>
					</h2>
					<?php if ( ! empty( $group['group_intro'] ) ) : ?>
						<p class="lls-doc-group__intro"><?php echo esc_html( $group['group_intro'] ); ?></p>
					<?php endif; ?>

					<?php foreach ( $group['sections'] as $sec ) : ?>
						<div class="postbox lls-doc-section" style="margin-top:18px;padding:16px 20px;">
							<h3 style="margin-top:0;font-size:1.1em;">
								<code>[<?php echo esc_html( $sec['tag'] ); ?>]</code>
								— <?php echo esc_html( $sec['title'] ); ?>
							</h3>
							<p><?php echo esc_html( $sec['intro'] ); ?></p>
							<?php if ( ! empty( $sec['where'] ) ) : ?>
								<p class="description"><?php echo esc_html( $sec['where'] ); ?></p>
							<?php endif; ?>

							<h4 style="font-size:14px;margin:1.25em 0 0.5em;"><?php esc_html_e( 'Campi / attributi', 'language-learning-stories' ); ?></h4>
							<?php if ( empty( $sec['attrs'] ) ) : ?>
								<p><em><?php esc_html_e( 'Nessun attributo: usa solo il tag così com’è.', 'language-learning-stories' ); ?></em></p>
							<?php else : ?>
								<table class="widefat striped lls-doc-attrs" style="max-width:920px;">
									<thead>
										<tr>
											<th scope="col"><?php esc_html_e( 'Nome', 'language-learning-stories' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Obbligatorio', 'language-learning-stories' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Valori', 'language-learning-stories' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Descrizione', 'language-learning-stories' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $sec['attrs'] as $row ) : ?>
											<tr>
												<td><code><?php echo esc_html( $row['name'] ); ?></code></td>
												<td><?php echo esc_html( $row['required'] ); ?></td>
												<td><?php echo esc_html( is_string( $row['values'] ) ? $row['values'] : '' ); ?></td>
												<td><?php echo esc_html( isset( $row['help'] ) ? $row['help'] : '' ); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>

							<h4 style="font-size:14px;margin:1.25em 0 0.5em;"><?php esc_html_e( 'Esempi', 'language-learning-stories' ); ?></h4>
							<?php foreach ( $sec['ex'] as $example ) : ?>
								<pre class="lls-doc-code"><?php echo esc_html( $example ); ?></pre>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public function register_post_type() {
		$labels = [
			'name'               => __( 'Storie', 'language-learning-stories' ),
			'singular_name'      => __( 'Storia', 'language-learning-stories' ),
			'menu_name'          => __( 'Storie', 'language-learning-stories' ),
			'name_admin_bar'     => __( 'Storia', 'language-learning-stories' ),
			'add_new'            => __( 'Aggiungi nuova', 'language-learning-stories' ),
			'add_new_item'       => __( 'Aggiungi nuova storia', 'language-learning-stories' ),
			'edit_item'          => __( 'Modifica storia', 'language-learning-stories' ),
			'new_item'           => __( 'Nuova storia', 'language-learning-stories' ),
			'all_items'          => __( 'Storie in inglese', 'language-learning-stories' ),
			'view_item'          => __( 'Vedi storia', 'language-learning-stories' ),
			'search_items'       => __( 'Cerca storie', 'language-learning-stories' ),
			'not_found'          => __( 'Nessuna storia trovata', 'language-learning-stories' ),
			'not_found_in_trash' => __( 'Nessuna storia nel cestino', 'language-learning-stories' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_admin_bar'  => true,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => true,
			'supports'           => [ 'title', 'editor', 'thumbnail' ],
			'has_archive'        => false,
			'rewrite'            => [ 'slug' => 'storie' ],
			'capability_type'    => 'post',
			'menu_icon'          => 'dashicons-book-alt',
		];

		register_post_type( 'lls_story', $args );
	}

	/**
	 * Categorie e tag dedicati alle storie (non condivisi con gli articoli).
	 */
	public function register_story_taxonomies() {
		$cat_labels = [
			'name'              => __( 'Categorie storia', 'language-learning-stories' ),
			'singular_name'     => __( 'Categoria storia', 'language-learning-stories' ),
			'search_items'      => __( 'Cerca categorie', 'language-learning-stories' ),
			'all_items'         => __( 'Tutte le categorie', 'language-learning-stories' ),
			'parent_item'       => __( 'Categoria genitore', 'language-learning-stories' ),
			'parent_item_colon' => __( 'Categoria genitore:', 'language-learning-stories' ),
			'edit_item'         => __( 'Modifica categoria', 'language-learning-stories' ),
			'update_item'       => __( 'Aggiorna categoria', 'language-learning-stories' ),
			'add_new_item'      => __( 'Aggiungi nuova categoria', 'language-learning-stories' ),
			'new_item_name'     => __( 'Nome nuova categoria', 'language-learning-stories' ),
			'menu_name'         => __( 'Categorie', 'language-learning-stories' ),
		];

		register_taxonomy(
			'lls_story_category',
			[ 'lls_story' ],
			[
				'labels'            => $cat_labels,
				'public'            => true,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_in_rest'      => true,
				'rewrite'           => [
					'slug'         => 'storia-categoria',
					'hierarchical' => true,
					'with_front'   => false,
				],
			]
		);

		$tag_labels = [
			'name'                       => __( 'Tag storia', 'language-learning-stories' ),
			'singular_name'              => __( 'Tag storia', 'language-learning-stories' ),
			'search_items'               => __( 'Cerca tag', 'language-learning-stories' ),
			'popular_items'              => __( 'Tag più usati', 'language-learning-stories' ),
			'all_items'                  => __( 'Tutti i tag', 'language-learning-stories' ),
			'edit_item'                  => __( 'Modifica tag', 'language-learning-stories' ),
			'update_item'                => __( 'Aggiorna tag', 'language-learning-stories' ),
			'add_new_item'               => __( 'Aggiungi nuovo tag', 'language-learning-stories' ),
			'new_item_name'              => __( 'Nome nuovo tag', 'language-learning-stories' ),
			'separate_items_with_commas' => __( 'Separa i tag con una virgola', 'language-learning-stories' ),
			'add_or_remove_items'        => __( 'Aggiungi o rimuovi tag', 'language-learning-stories' ),
			'choose_from_most_used'      => __( 'Scegli tra i più usati', 'language-learning-stories' ),
			'menu_name'                  => __( 'Tag', 'language-learning-stories' ),
		];

		register_taxonomy(
			'lls_story_tag',
			[ 'lls_story' ],
			[
				'labels'            => $tag_labels,
				'public'            => true,
				'hierarchical'      => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_in_rest'      => true,
				'rewrite'           => [
					'slug'       => 'storia-tag',
					'with_front' => false,
				],
			]
		);
	}

	/**
	 * Flush rewrite rules quando la versione del plugin cambia, così /storie/slug/ funziona.
	 */
	public function maybe_flush_rewrite_rules() {
		if ( get_option( 'lls_plugin_version' ) === LLS_PLUGIN_VERSION ) {
			return;
		}
		flush_rewrite_rules();
		update_option( 'lls_plugin_version', LLS_PLUGIN_VERSION );
	}

	/**
	 * Rimuove la chiave alt3 da _lls_sentences in tutte le storie (una tantum).
	 */
	public function maybe_migrate_remove_alt3() {
		if ( get_option( 'lls_meta_removed_alt3_v1' ) ) {
			return;
		}
		$posts = get_posts(
			[
				'post_type'      => 'lls_story',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);
		foreach ( $posts as $post_id ) {
			$sentences = get_post_meta( $post_id, '_lls_sentences', true );
			if ( ! is_array( $sentences ) ) {
				continue;
			}
			$changed = false;
			foreach ( $sentences as $i => $row ) {
				if ( ! is_array( $row ) || ! isset( $row['alt3'] ) ) {
					continue;
				}
				unset( $sentences[ $i ]['alt3'] );
				$changed = true;
			}
			if ( $changed ) {
				update_post_meta( $post_id, '_lls_sentences', array_values( $sentences ) );
			}
		}
		update_option( 'lls_meta_removed_alt3_v1', 1 );
	}

	/**
	 * Precompila l’opzione Traduzioni con polacco e spagnolo (una tantum).
	 */
	public function maybe_seed_ui_strings_pl_es() {
		lls_maybe_seed_ui_strings_pl_es();
	}

	public function register_meta_boxes() {
		add_meta_box(
			'lls_story_info',
			__( 'Informazioni Storia', 'language-learning-stories' ),
			[ $this, 'render_story_info_box' ],
			'lls_story',
			'normal',
			'high'
		);

		add_meta_box(
			'lls_story_sentences',
			__( 'Frasi della Storia', 'language-learning-stories' ),
			[ $this, 'render_sentences_box' ],
			'lls_story',
			'normal',
			'default'
		);
	}

	public function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'lls_story' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		$plugin_url = plugin_dir_url( __FILE__ );

		wp_enqueue_style(
			'lls-admin-style',
			$plugin_url . 'assets/lls-admin.css',
			[],
			'0.1.25'
		);

		wp_enqueue_script(
			'jquery-ui-sortable'
		);

		wp_enqueue_script(
			'lls-admin-script',
			$plugin_url . 'assets/lls-admin.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			'0.1.25',
			true
		);

		$sentences = get_post_meta( get_the_ID(), '_lls_sentences', true );
		$images    = get_post_meta( get_the_ID(), '_lls_images', true );
		$sentences = is_array( $sentences ) ? $sentences : [];
		foreach ( $sentences as $sk => $srow ) {
			if ( is_array( $srow ) && isset( $srow['alt3'] ) ) {
				unset( $sentences[ $sk ]['alt3'] );
			}
		}
		$sentences = array_values( $sentences );

		wp_localize_script(
			'lls-admin-script',
			'llsAdmin',
			[
				'sentences'      => $sentences,
				'images'         => is_array( $images ) ? $images : [],
				'i18n'           => [
					'addSentence'         => __( 'Aggiungi Frase', 'language-learning-stories' ),
					'edit'                => __( 'Modifica', 'language-learning-stories' ),
					'delete'              => __( 'Elimina', 'language-learning-stories' ),
					'sentence'            => __( 'Frase', 'language-learning-stories' ),
					'imageBox'            => __( 'Box immagine', 'language-learning-stories' ),
					'insertImageHere'     => __( 'Inserisci un box immagine in questa posizione', 'language-learning-stories' ),
					'uploadImage'         => __( 'Carica Immagine', 'language-learning-stories' ),
					'removeImage'         => __( 'Rimuovi immagine', 'language-learning-stories' ),
					'confirmDelete'       => __( 'Sei sicuro di voler eliminare questa frase?', 'language-learning-stories' ),
					'confirmDeleteImage'  => __( 'Sei sicuro di voler eliminare questo box immagine?', 'language-learning-stories' ),
				],
				'nonce'          => wp_create_nonce( 'lls_admin_nonce' ),
			]
		);
	}

	public function render_story_info_box( $post ) {
		wp_nonce_field( 'lls_save_story', 'lls_story_nonce' );

		$opening_image_id = (int) get_post_meta( $post->ID, '_lls_opening_image_id', true );
		$opening_image    = $opening_image_id ? wp_get_attachment_image( $opening_image_id, 'medium' ) : '';
		$known_lang       = get_post_meta( $post->ID, '_lls_known_lang', true );
		if ( ! in_array( $known_lang, lls_known_lang_codes(), true ) ) {
			$known_lang = 'it';
		}
		?>
		<div class="lls-story-info">
			<p>
				<label for="lls_known_lang"><strong><?php esc_html_e( 'Lingua che conosci (interfaccia)', 'language-learning-stories' ); ?></strong></label><br>
				<select name="lls_known_lang" id="lls_known_lang" style="max-width:100%;">
					<option value="it" <?php selected( $known_lang, 'it' ); ?>><?php esc_html_e( 'Italiano', 'language-learning-stories' ); ?></option>
					<option value="pl" <?php selected( $known_lang, 'pl' ); ?>><?php esc_html_e( 'Polacco', 'language-learning-stories' ); ?></option>
					<option value="es" <?php selected( $known_lang, 'es' ); ?>><?php esc_html_e( 'Spagnolo', 'language-learning-stories' ); ?></option>
				</select><br>
				<small class="description"><?php esc_html_e( 'Testi del front-end (pulsanti, titoli, messaggi) per questa storia. La lingua da imparare resta sempre l’inglese.', 'language-learning-stories' ); ?></small>
			</p>
			<p>
				<strong><?php esc_html_e( 'Immagine di apertura', 'language-learning-stories' ); ?></strong><br>
				<small><?php esc_html_e( 'Questa immagine apparirà prima della prima frase.', 'language-learning-stories' ); ?></small>
			</p>
			<div class="lls-opening-image-wrapper">
				<div class="lls-opening-image-preview">
					<?php
					if ( $opening_image ) {
						echo $opening_image;
					} else {
						echo '<span class="lls-opening-image-placeholder">' . esc_html__( 'Nessuna immagine selezionata', 'language-learning-stories' ) . '</span>';
					}
					?>
				</div>
				<input type="hidden" id="lls_opening_image_id" name="lls_opening_image_id" value="<?php echo esc_attr( $opening_image_id ); ?>">
				<p>
					<button type="button" class="button lls-opening-image-upload">
						<?php esc_html_e( 'Carica Immagine', 'language-learning-stories' ); ?>
					</button>
					<button type="button" class="button-link-delete lls-opening-image-remove"<?php echo $opening_image_id ? '' : ' style="display:none;"'; ?>>
						<?php esc_html_e( 'Rimuovi immagine', 'language-learning-stories' ); ?>
					</button>
				</p>
			</div>
		</div>
		<?php
	}

	public function render_sentences_box( $post ) {
		$sentences = get_post_meta( $post->ID, '_lls_sentences', true );
		$images    = get_post_meta( $post->ID, '_lls_images', true );

		$sentences = is_array( $sentences ) ? $sentences : [];
		$images    = is_array( $images ) ? $images : [];
		?>
		<div class="lls-sentences-wrapper">
			<div class="lls-sentences-header">
				<div class="lls-sentences-header-buttons">
					<button type="button" class="button button-primary lls-add-sentence"><?php esc_html_e( '➕ Aggiungi Frase', 'language-learning-stories' ); ?></button>
					<button type="button" class="button lls-import-csv" title="<?php esc_attr_e( 'Solo file CSV con colonne separate da punto e virgola (;).', 'language-learning-stories' ); ?>"><?php esc_html_e( '📤 Importa CSV', 'language-learning-stories' ); ?></button>
					<button type="button" class="button lls-export-csv" title="<?php esc_attr_e( 'Scarica un CSV con colonne separate da punto e virgola (;).', 'language-learning-stories' ); ?>"><?php esc_html_e( '💾 Esporta CSV', 'language-learning-stories' ); ?></button>
				</div>
				<p class="description lls-csv-hint"><?php esc_html_e( 'CSV: usa solo il punto e virgola (;) tra una colonna e l’altra. Import ed esportazione usano lo stesso formato.', 'language-learning-stories' ); ?></p>
			</div>

			<div class="lls-sentences-list" id="lls-sentences-list">
				<?php
				$index = 1;
				foreach ( $sentences as $sentence ) {
					$this->render_sentence_card( $index, $sentence );
					$index++;
				}
				?>
			</div>

			<div class="lls-images-wrapper">
				<h4><?php esc_html_e( 'Box Immagini', 'language-learning-stories' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Ogni immagine sarà mostrata dopo la frase con il numero di posizione indicato.', 'language-learning-stories' ); ?></p>
				<div class="lls-images-list" id="lls-images-list">
					<?php
					foreach ( $images as $image ) {
						$this->render_image_card( $image );
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_sentence_card( $index, $sentence ) {
		$text_it        = isset( $sentence['text_it'] ) ? (string) $sentence['text_it'] : '';
		$main_trans     = isset( $sentence['main_translation'] ) ? (string) $sentence['main_translation'] : '';
		$alt1           = isset( $sentence['alt1'] ) ? (string) $sentence['alt1'] : '';
		$alt2           = isset( $sentence['alt2'] ) ? (string) $sentence['alt2'] : '';
		$grammar        = isset( $sentence['grammar'] ) ? (string) $sentence['grammar'] : '';
		?>
		<div class="lls-sentence-card">
			<div class="lls-sentence-header">
				<span class="lls-sentence-handle">↕</span>
				<span class="lls-sentence-title"><?php echo esc_html( sprintf( __( 'Frase #%d', 'language-learning-stories' ), $index ) ); ?></span>
				<span class="lls-sentence-preview"><?php echo esc_html( wp_html_excerpt( wp_strip_all_tags( $text_it ), 80, '…' ) ); ?></span>
				<button type="button" class="button-link lls-toggle-sentence"><?php esc_html_e( 'Modifica', 'language-learning-stories' ); ?></button>
				<button type="button" class="button-link-delete lls-delete-sentence"><?php esc_html_e( 'Elimina', 'language-learning-stories' ); ?></button>
			</div>
			<div class="lls-sentence-body">
				<p>
					<label>
						<strong><?php esc_html_e( 'Frase in italiano', 'language-learning-stories' ); ?></strong><br>
						<textarea name="lls_sentences[text_it][]" class="widefat" rows="2"><?php echo esc_textarea( $text_it ); ?></textarea>
					</label>
				</p>
				<p>
					<label>
						<strong><?php esc_html_e( 'Traduzione principale (appare nella storia)', 'language-learning-stories' ); ?></strong><br>
						<textarea name="lls_sentences[main_translation][]" class="widefat" rows="2"><?php echo esc_textarea( $main_trans ); ?></textarea>
					</label>
				</p>
				<p>
					<label>
						<strong><?php esc_html_e( 'Traduzione alternativa 1', 'language-learning-stories' ); ?></strong><br>
						<input type="text" name="lls_sentences[alt1][]" class="widefat" value="<?php echo esc_attr( $alt1 ); ?>">
					</label>
				</p>
				<p>
					<label>
						<strong><?php esc_html_e( 'Traduzione alternativa 2', 'language-learning-stories' ); ?></strong><br>
						<input type="text" name="lls_sentences[alt2][]" class="widefat" value="<?php echo esc_attr( $alt2 ); ?>">
					</label>
				</p>
				<p>
					<label>
						<strong><?php esc_html_e( 'Consigli grammaticali', 'language-learning-stories' ); ?></strong><br>
						<textarea name="lls_sentences[grammar][]" class="widefat lls-grammar-text" rows="8"><?php echo esc_textarea( $grammar ); ?></textarea>
					</label>
					<em class="description"><?php esc_html_e( 'Puoi usare tag HTML (es. &lt;strong&gt;, &lt;ul&gt;, &lt;li&gt;). Nel CSV la colonna va scritta in codice HTML.', 'language-learning-stories' ); ?></em>
					<span class="lls-grammar-counter"></span>
				</p>
				<p class="lls-insert-image-position">
					<button type="button" class="button button-secondary lls-insert-image-here">
						<?php esc_html_e( 'Inserisci un box immagine in questa posizione', 'language-learning-stories' ); ?>
					</button>
					<button type="button" class="button button-secondary lls-save-sentence">
						<?php esc_html_e( 'Salva', 'language-learning-stories' ); ?>
					</button>
				</p>
			</div>
		</div>
		<?php
	}

	private function render_image_card( $image ) {
		$position = isset( $image['position'] ) ? (int) $image['position'] : 0;
		$id       = isset( $image['attachment_id'] ) ? (int) $image['attachment_id'] : 0;
		$thumb    = $id ? wp_get_attachment_image( $id, 'thumbnail' ) : '';
		?>
		<div class="lls-image-card">
			<div class="lls-image-inner">
				<div class="lls-image-position">
					<label>
						<?php esc_html_e( 'Posizione (dopo frase #)', 'language-learning-stories' ); ?><br>
						<input type="number" name="lls_images[position][]" value="<?php echo esc_attr( $position ); ?>" min="1" step="1" style="width:80px;">
					</label>
				</div>
				<div class="lls-image-thumb">
					<?php
					if ( $thumb ) {
						echo $thumb;
					} else {
						echo '<span class="lls-image-placeholder">' . esc_html__( 'Nessuna immagine', 'language-learning-stories' ) . '</span>';
					}
					?>
				</div>
				<div class="lls-image-actions">
					<input type="hidden" name="lls_images[attachment_id][]" value="<?php echo esc_attr( $id ); ?>" class="lls-image-id">
					<button type="button" class="button lls-image-upload"><?php esc_html_e( 'Carica Immagine', 'language-learning-stories' ); ?></button>
					<button type="button" class="button-link-delete lls-image-delete"><?php esc_html_e( 'Elimina', 'language-learning-stories' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	public function save_story_meta( $post_id, $post ) {
		if ( 'lls_story' !== $post->post_type ) {
			return;
		}

		if ( ! isset( $_POST['lls_story_nonce'] ) || ! wp_verify_nonce( $_POST['lls_story_nonce'], 'lls_save_story' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$opening_image_id = isset( $_POST['lls_opening_image_id'] ) ? (int) $_POST['lls_opening_image_id'] : 0;
		update_post_meta( $post_id, '_lls_opening_image_id', $opening_image_id );

		$known_lang = isset( $_POST['lls_known_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['lls_known_lang'] ) ) : 'it';
		if ( ! in_array( $known_lang, lls_known_lang_codes(), true ) ) {
			$known_lang = 'it';
		}
		update_post_meta( $post_id, '_lls_known_lang', $known_lang );

		$sentences_data = isset( $_POST['lls_sentences'] ) && is_array( $_POST['lls_sentences'] ) ? $_POST['lls_sentences'] : [];
		$sentences      = [];

		if ( ! empty( $sentences_data ) ) {
			$count = isset( $sentences_data['text_it'] ) && is_array( $sentences_data['text_it'] ) ? count( $sentences_data['text_it'] ) : 0;
			for ( $i = 0; $i < $count; $i++ ) {
				$text_it    = isset( $sentences_data['text_it'][ $i ] ) ? wp_kses_post( $sentences_data['text_it'][ $i ] ) : '';
				$main_trans = isset( $sentences_data['main_translation'][ $i ] ) ? wp_kses_post( $sentences_data['main_translation'][ $i ] ) : '';
				$alt1       = isset( $sentences_data['alt1'][ $i ] ) ? sanitize_text_field( $sentences_data['alt1'][ $i ] ) : '';
				$alt2       = isset( $sentences_data['alt2'][ $i ] ) ? sanitize_text_field( $sentences_data['alt2'][ $i ] ) : '';
				$grammar    = isset( $sentences_data['grammar'][ $i ] ) ? wp_kses_post( $sentences_data['grammar'][ $i ] ) : '';

				if ( '' === trim( $text_it ) && '' === trim( $main_trans ) ) {
					continue;
				}

				$sentences[] = [
					'text_it'          => $text_it,
					'main_translation' => $main_trans,
					'alt1'             => $alt1,
					'alt2'             => $alt2,
					'grammar'          => $grammar,
				];
			}
		}

		if ( ! empty( $sentences ) ) {
			update_post_meta( $post_id, '_lls_sentences', $sentences );
		} else {
			delete_post_meta( $post_id, '_lls_sentences' );
		}

		$images_positions = isset( $_POST['lls_images']['position'] ) ? (array) $_POST['lls_images']['position'] : [];
		$images_ids       = isset( $_POST['lls_images']['attachment_id'] ) ? (array) $_POST['lls_images']['attachment_id'] : [];
		$images           = [];

		$total_images = max( count( $images_positions ), count( $images_ids ) );
		for ( $i = 0; $i < $total_images; $i++ ) {
			$pos = isset( $images_positions[ $i ] ) ? (int) $images_positions[ $i ] : 0;
			$id  = isset( $images_ids[ $i ] ) ? (int) $images_ids[ $i ] : 0;

			if ( $pos <= 0 || $id <= 0 ) {
				continue;
			}

			$images[] = [
				'position'      => $pos,
				'attachment_id' => $id,
			];
		}

		if ( ! empty( $images ) ) {
			update_post_meta( $post_id, '_lls_images', $images );
		} else {
			delete_post_meta( $post_id, '_lls_images' );
		}
	}

	public function template_include_story( $template ) {
		if ( is_singular( 'lls_story' ) ) {
			$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/single-lls_story.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		return $template;
	}

	public function enqueue_frontend_assets() {
		if ( ! is_singular( 'lls_story' ) ) {
			return;
		}

		$post_id = get_the_ID();
		$sentences = get_post_meta( $post_id, '_lls_sentences', true );
		$images    = get_post_meta( $post_id, '_lls_images', true );
		$opening_image_id = (int) get_post_meta( $post_id, '_lls_opening_image_id', true );

		$sentences = is_array( $sentences ) ? $sentences : [];
		$images    = is_array( $images ) ? $images : [];

		foreach ( $sentences as $k => $row ) {
			if ( is_array( $row ) && isset( $row['alt3'] ) ) {
				unset( $sentences[ $k ]['alt3'] );
			}
		}
		$sentences = array_values( $sentences );

		$progress = [ 'completed' => 0, 'story_text' => '' ];
		if ( is_user_logged_in() ) {
			$saved = get_user_meta( get_current_user_id(), '_lls_progress_' . $post_id, true );
			if ( is_array( $saved ) && isset( $saved['completed'] ) ) {
				$progress['completed'] = (int) $saved['completed'];
				$progress['story_text'] = isset( $saved['story_text'] ) ? (string) $saved['story_text'] : '';
			}
		}

		$known_lang = get_post_meta( $post_id, '_lls_known_lang', true );
		if ( ! in_array( $known_lang, lls_known_lang_codes(), true ) ) {
			$known_lang = 'it';
		}
		$ui_strings = lls_get_merged_ui_strings( $known_lang );

		$opening_image_url = '';
		if ( $opening_image_id ) {
			$opening_image_url = wp_get_attachment_image_url( $opening_image_id, 'large' );
		}
		$images_with_urls = [];
		foreach ( $images as $img ) {
			$aid = isset( $img['attachment_id'] ) ? (int) $img['attachment_id'] : 0;
			$images_with_urls[] = [
				'position'      => isset( $img['position'] ) ? (int) $img['position'] : 0,
				'attachment_id'  => $aid,
				'url'            => $aid ? wp_get_attachment_image_url( $aid, 'large' ) : '',
			];
		}

		$plugin_url = plugin_dir_url( __FILE__ );

		wp_enqueue_style(
			'lls-frontend-font',
			'https://fonts.googleapis.com/css2?family=Manrope:wght@300;600&display=swap',
			[],
			null
		);

		wp_enqueue_style(
			'lls-frontend-style',
			$plugin_url . 'assets/lls-frontend.css',
			[ 'lls-frontend-font' ],
			'0.2.0'
		);

		$story_bg_abs = content_url( 'uploads/2026/03/5.jpg' );
		$story_bg_rel = wp_make_link_relative( $story_bg_abs );
		if ( ! is_string( $story_bg_rel ) || $story_bg_rel === '' ) {
			$story_bg_rel = wp_parse_url( $story_bg_abs, PHP_URL_PATH );
		}
		if ( is_string( $story_bg_rel ) && $story_bg_rel !== '' && '/' !== $story_bg_rel[0] ) {
			$story_bg_rel = '/' . ltrim( $story_bg_rel, '/' );
		}
		if ( ! is_string( $story_bg_rel ) || $story_bg_rel === '' ) {
			$story_bg_rel = '/wp-content/uploads/2026/03/5.jpg';
		}
		wp_add_inline_style(
			'lls-frontend-style',
			sprintf(
				'body.single-lls_story #content>.ast-container{background-color:transparent;background-image:url(%s);background-size:cover;background-position:center;background-repeat:no-repeat;}#lls-story-page.lls-story-page,.lls-story-page{background-color:transparent;background-image:none;}',
				esc_url( $story_bg_rel )
			)
		);

		wp_enqueue_script(
			'lls-frontend-script',
			$plugin_url . 'assets/lls-frontend.js',
			[ 'jquery' ],
			'0.2.0',
			true
		);

		wp_localize_script(
			'lls-frontend-script',
			'llsStory',
			[
				'storyId'         => $post_id,
				'title'           => get_the_title(),
				'knownLang'       => $known_lang,
				'strings'         => $ui_strings,
				'sentences'       => $sentences,
				'images'          => $images_with_urls,
				'openingImageUrl' => $opening_image_url,
				'progress'        => $progress,
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'lls_save_progress' ),
			]
		);
	}

	public function ajax_save_progress() {
		check_ajax_referer( 'lls_save_progress', 'nonce' );

		$story_id = isset( $_POST['story_id'] ) ? (int) $_POST['story_id'] : 0;
		$completed = isset( $_POST['completed'] ) ? (int) $_POST['completed'] : 0;
		$story_text = isset( $_POST['story_text'] ) ? wp_kses_post( wp_unslash( $_POST['story_text'] ) ) : '';

		if ( ! $story_id || get_post_type( $story_id ) !== 'lls_story' ) {
			wp_send_json_error();
		}

		$coin_total = null;
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$old_completed = 0;
			$saved = get_user_meta( $user_id, '_lls_progress_' . $story_id, true );
			if ( is_array( $saved ) && isset( $saved['completed'] ) ) {
				$old_completed = (int) $saved['completed'];
			}

			update_user_meta(
				$user_id,
				'_lls_progress_' . $story_id,
				[ 'completed' => $completed, 'story_text' => $story_text ]
			);

			$delta = $completed - $old_completed;
			if ( $delta > 0 && function_exists( 'lls_increment_user_daily_phrases' ) ) {
				lls_increment_user_daily_phrases( $user_id, $delta );
			}
			if ( function_exists( 'lls_touch_user_recent_story' ) ) {
				lls_touch_user_recent_story( $user_id, $story_id );
			}
			if ( function_exists( 'lls_get_user_total_completed_sentences' ) ) {
				$coin_total = lls_get_user_total_completed_sentences( $user_id );
			}
		}

		$payload = [];
		if ( null !== $coin_total ) {
			$payload['coin_total'] = (int) $coin_total;
		}
		wp_send_json_success( $payload );
	}

	/**
	 * Stili per shortcode header, footer app-nav e area profilo.
	 */
	public function enqueue_header_shortcode_assets() {
		if ( is_admin() ) {
			return;
		}
		$plugin_url = plugin_dir_url( __FILE__ );

		wp_enqueue_style(
			'lls-frontend-font',
			'https://fonts.googleapis.com/css2?family=Manrope:wght@300;600&display=swap',
			[],
			null
		);

		wp_enqueue_style(
			'lls-shortcodes-shared',
			$plugin_url . 'assets/lls-shortcodes-shared.css',
			[ 'lls-frontend-font' ],
			LLS_PLUGIN_VERSION
		);

		wp_enqueue_style(
			'lls-header-shortcodes',
			$plugin_url . 'assets/lls-header-shortcodes.css',
			[ 'lls-shortcodes-shared' ],
			LLS_PLUGIN_VERSION
		);

		wp_enqueue_style(
			'lls-coin',
			$plugin_url . 'assets/lls-coin.css',
			[ 'lls-shortcodes-shared' ],
			LLS_PLUGIN_VERSION
		);

		wp_enqueue_style(
			'lls-app-logo',
			$plugin_url . 'assets/lls-app-logo.css',
			[ 'lls-shortcodes-shared' ],
			LLS_PLUGIN_VERSION
		);

		wp_enqueue_style(
			'lls-footer-app-nav',
			$plugin_url . 'assets/lls-footer-app-nav.css',
			[ 'lls-shortcodes-shared' ],
			LLS_PLUGIN_VERSION
		);

		wp_enqueue_style(
			'lls-profile-shortcodes',
			$plugin_url . 'assets/lls-profile-shortcodes.css',
			[ 'lls-shortcodes-shared' ],
			LLS_PLUGIN_VERSION
		);

		wp_enqueue_style(
			'lls-login-shortcodes',
			$plugin_url . 'assets/lls-login-shortcodes.css',
			[ 'lls-profile-shortcodes' ],
			LLS_PLUGIN_VERSION
		);

		wp_register_script(
			'lls-profile-account',
			$plugin_url . 'assets/lls-profile-account.js',
			[],
			LLS_PLUGIN_VERSION,
			true
		);

		wp_register_script(
			'lls-login-intro',
			$plugin_url . 'assets/lls-login-intro.js',
			[],
			LLS_PLUGIN_VERSION,
			true
		);
	}

	public function maybe_create_sample_story() {
		if ( get_option( 'lls_sample_story_created' ) ) {
			return;
		}

		$existing = get_page_by_title( 'La Cicala e la Formica', OBJECT, 'lls_story' );
		if ( $existing ) {
			update_option( 'lls_sample_story_created', 1 );
			return;
		}

		$post_id = wp_insert_post(
			[
				'post_title'   => 'La Cicala e la Formica',
				'post_type'    => 'lls_story',
				'post_status'  => 'publish',
				'post_content' => "Una breve storia educativa sulla cicala e la formica per esercizi di traduzione dall'italiano all'inglese.",
			]
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return;
		}

		$grammar1 = "Per questa frase ti consiglio di:\n\n• \"C'era una volta\" → usa \"Once upon a time\"\n  È la formula classica per iniziare le fiabe in inglese. Alternativa: \"There once was\"\n\n• \"una cicala\" → \"a cicada\"\n  Usa \"a\" perché \"cicada\" inizia con consonante (non vocale)\n\n• \"che viveva\" → \"that lived\" oppure \"who lived\"\n  Nelle fiabe puoi usare \"who\" anche per gli animali! Entrambi corretti.\n\n• \"in un grande prato verde\" → \"in a large green meadow\"\n  ORDINE IMPORTANTE: in inglese l'aggettivo va PRIMA del nome\n  Corretto: \"large green meadow\"\n  Sbagliato: \"meadow large green\"\n\n• \"grande\" → puoi dire \"large\", \"big\", o \"vast\"\n  Tutte e tre vanno bene, ma \"large\" è più formale\n\nRicorda: questa è una frase al passato, quindi usa \"was\" e \"lived\"!";

		$grammar2 = "Per questa frase ti consiglio di:\n\n• \"La cicala\" → \"The cicada\"\n  Usa \"The\" perché è LA cicala specifica della storia (non una qualsiasi)\n\n• \"amava\" → \"loved\"\n  Passato del verbo \"to love\". È un verbo regolare quindi aggiungi -ed\n\n• \"cantare\" → due opzioni corrette:\n  Opzione 1: \"to sing\" (infinito con \"to\")\n  Opzione 2: \"singing\" (forma -ing, gerundio)\n\n  Puoi dire: \"loved to sing\" oppure \"loved singing\"\n  Entrambi significano la stessa cosa!\n\nStruttura semplice: Soggetto + verbo passato + infinito/gerundio\n\nAltri verbi simili che funzionano così:\n\"like to do\" / \"like doing\"\n\"enjoy to do\" / \"enjoy doing\" (ma \"enjoy\" preferisce il gerundio!)";

		$grammar3 = "Per questa frase ti consiglio di:\n\n• \"Cantava\" → \"sang\" oppure \"would sing\"\n  \"Sang\" = passato semplice di \"to sing\" (verbo irregolare!)\n  \"Would sing\" = azione abituale nel passato (faceva sempre così)\n\n• \"al mattino\" → \"in the morning\"\n  Preposizione: IN per le parti del giorno (in the morning, in the evening)\n\n• \"a mezzogiorno\" → \"at noon\" oppure \"at midday\"\n  Preposizione: AT per momenti specifici del giorno\n\n• \"al tramonto\" → \"at sunset\"\n  Anche qui AT perché è un momento preciso\n\n• \"anche\" → \"also\" oppure \"even\"\n  \"Also\" va dopo il soggetto: \"she also sang\"\n  \"Even\" è più enfatico: \"she even sang\" (enfatizza che cantava PERSINO al tramonto)\n\n• Ripetizione stilistica:\n  Puoi ripetere \"she sang\" tre volte per dare ritmo\n  Oppure dire solo una volta e elencare: \"She sang in the morning, at noon, and at sunset\"\n\nNota la virgola prima di \"and\" nelle liste di 3+ elementi (stile americano)!";

		$grammar4 = "Per questa frase ti consiglio di:\n\n• \"Il suo\" → \"Her\"\n  Possessivo femminile perché la cicala è femmina nella storia\n  Attenzione: in inglese \"her\" non cambia se il nome è maschile o femminile\n\n• \"canto\" → puoi dire:\n  \"Song\" = il canto come risultato/prodotto\n  \"Singing\" = l'azione del cantare\n  Entrambi corretti, ma \"song\" è più poetico\n\n• \"era allegro\" → \"was cheerful\"\n  \"Was\" = passato di \"to be\"\n  \"Cheerful\" = allegro, gioioso\n  Alternative: \"joyful\", \"happy\", \"merry\"\n\n• \"riempiva l'aria di musica\" → \"filled the air with music\"\n  ATTENZIONE: usa \"WITH\" non \"OF\"!\n  Corretto: \"filled... WITH music\"\n  Sbagliato: \"filled... OF music\"\n\n  Struttura: \"to fill\" qualcosa \"WITH\" qualcos'altro\n\n• Due frasi coordinate con \"and\"\n  Stesso soggetto (il canto), due verbi: \"was\" e \"filled\"\n\nAlternativa più creativa: \"made the air musical\" (rese l'aria musicale)";

		$grammar5 = "Per questa frase ti consiglio di:\n\n• \"Ogni giorno\" → tre opzioni:\n  \"Every day\" = più comune, uso quotidiano\n  \"Each day\" = più formale, enfatizza il singolo giorno\n  \"Daily\" = avverbio, molto conciso\n  Tutti e tre corretti!\n\n• \"si svegliava\" → \"woke up\" PHRASAL VERB!\n  Questo è importante: \"wake up\" è un verbo separato in due parti\n  Non puoi dire solo \"wake\" in questo contesto\n  Il complemento va DOPO \"up\": \"woke up without hurry\"\n\n  Alternative:\n  \"Rose\" (letterario, poetico - si alzava)\n  \"Awakened\" (molto formale, quasi mai usato nel parlato)\n\n• \"senza fretta\" → puoi dire:\n  \"Without hurry\" (senza fretta)\n  \"Without rush\" (senza fretta/urgenza)\n  \"At her leisure\" (con calma, a suo piacimento)\n  \"Unhurriedly\" (avverbio - senza fretta)\n\n• \"without\" + nome (NO articolo)\n  Corretto: \"without hurry\"\n  Sbagliato: \"without a hurry\"\n\n• Virgola dopo espressione di tempo iniziale:\n  \"Every day,\" ← virgola obbligatoria!\n  Separa l'espressione temporale dal resto della frase\n\nPhrasal verbs comuni simili: wake up, get up, stand up, sit down, lie down";

		$sentences = [
			[
                'text_it'          => "C'era una volta una cicala che viveva in un grande prato verde.",
				'main_translation' => 'Once upon a time there was a cicada that lived in a large green meadow.',
				'alt1'             => 'Once upon a time there was a cicada that lived in a large green meadow.',
				'alt2'             => 'There once was a cicada living in a big green field.',
				'grammar'          => $grammar1,
			],
			[
				'text_it'          => 'La cicala amava cantare.',
				'main_translation' => 'The cicada loved to sing.',
				'alt1'             => 'The cicada loved to sing.',
				'alt2'             => 'The cicada loved singing.',
				'grammar'          => $grammar2,
			],
			[
				'text_it'          => 'Cantava al mattino, cantava a mezzogiorno e cantava anche al tramonto.',
				'main_translation' => 'She sang in the morning, she sang at noon and she also sang at sunset.',
				'alt1'             => 'She sang in the morning, she sang at noon and she also sang at sunset.',
				'alt2'             => 'She would sing in the morning, at midday, and even at sunset.',
				'grammar'          => $grammar3,
			],
			[
				'text_it'          => "Il suo canto era allegro e riempiva l'aria di musica.",
				'main_translation' => 'Her song was cheerful and filled the air with music.',
				'alt1'             => 'Her song was cheerful and filled the air with music.',
				'alt2'             => 'Her singing was joyful and it filled the air with music.',
				'grammar'          => $grammar4,
			],
			[
				'text_it'          => 'Ogni giorno, la cicala si svegliava senza fretta.',
				'main_translation' => 'Every day, the cicada woke up without hurry.',
				'alt1'             => 'Every day, the cicada woke up without hurry.',
				'alt2'             => 'Each morning, the cicada rose without any rush.',
				'grammar'          => $grammar5,
			],
		];

		update_post_meta( $post_id, '_lls_known_lang', 'it' );
		update_post_meta( $post_id, '_lls_sentences', $sentences );

		update_option( 'lls_sample_story_created', 1 );
	}
}

new LLS_Plugin();

register_activation_hook( __FILE__, function () {
	$labels = [
		'name' => 'Storie',
		'singular_name' => 'Storia',
	];
	register_post_type( 'lls_story', [
		'labels'       => $labels,
		'public'       => true,
		'publicly_queryable' => true,
		'rewrite'      => [ 'slug' => 'storie' ],
		'has_archive'  => false,
	] );
	register_taxonomy( 'lls_story_category', 'lls_story', [
		'public'       => true,
		'hierarchical' => true,
		'show_in_rest' => true,
		'rewrite'      => [ 'slug' => 'storia-categoria', 'hierarchical' => true, 'with_front' => false ],
	] );
	register_taxonomy( 'lls_story_tag', 'lls_story', [
		'public'       => true,
		'hierarchical' => false,
		'show_in_rest' => true,
		'rewrite'      => [ 'slug' => 'storia-tag', 'with_front' => false ],
	] );
	flush_rewrite_rules();
} );

