<?php
/**
 * Testi pagina login (opzioni bacheca) + pagina «Pagina Login».
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LLS_LOGIN_INTRO_OPTION', 'lls_login_intro_settings' );

/**
 * Codici lingua supportati dall’intro login.
 *
 * @return string[]
 */
function lls_login_intro_lang_codes() {
	return [ 'en', 'es', 'it', 'pl' ];
}

/**
 * Chiavi testo per ogni lingua.
 *
 * @return string[]
 */
function lls_login_intro_string_keys() {
	return [ 'greeting', 'body', 'regBefore', 'regLink', 'regAfter', 'regPlain', 'langLabel' ];
}

/**
 * Default (fallback se i campi in bacheca sono vuoti).
 *
 * @return array<string, array<string, string>>
 */
function lls_login_intro_default_strings() {
	return [
		'en' => [
			'greeting'  => 'Hello,',
			'body'      => 'With login, you will have your own personal area where you can save your story progress, keep track of how many phrases you complete each day, and see your list of active stories… No ads and no hidden costs in this app :)',
			'regBefore' => 'If you don\'t have an account, ',
			'regLink'   => 'register here',
			'regAfter'  => '',
			'regPlain'  => 'Registration is not available at the moment.',
			'langLabel' => 'Language',
		],
		'es' => [
			'greeting'  => 'Hola,',
			'body'      => 'Al iniciar sesión tendrás tu área personal donde podrás guardar el progreso de tus historias, llevar un registro de cuántas frases completas cada día y ver la lista de tus historias activas… Sin publicidad ni costes ocultos en esta app :)',
			'regBefore' => 'Si no tienes una cuenta, ',
			'regLink'   => 'regístrate aquí',
			'regAfter'  => '',
			'regPlain'  => 'El registro no está disponible en este momento.',
			'langLabel' => 'Idioma',
		],
		'it' => [
			'greeting'  => 'Ciao,',
			'body'      => 'Con il login, avrai una tua area personale dove potrai salvare i tuoi progressi della storia, avere traccia di quante frasi riesci a completare ogni giorno e avere l\'elenco delle tue storie attive… Nessuna pubblicità e nessun costo nascosto in questa app :)',
			'regBefore' => 'Se non hai un profilo, ',
			'regLink'   => 'registrati da qui',
			'regAfter'  => '',
			'regPlain'  => 'Al momento non è possibile registrarsi.',
			'langLabel' => 'Lingua',
		],
		'pl' => [
			'greeting'  => 'Cześć,',
			'body'      => 'Po zalogowaniu otrzymasz własną strefę osobistą, w której zapiszesz postępy w czytaniu historii, zobaczysz ile zdań dziennie udało Ci się dokończyć oraz listę aktywnych historii… Bez reklam i ukrytych opłat w tej aplikacji :)',
			'regBefore' => 'Jeśli nie masz konta, ',
			'regLink'   => 'zarejestruj się tutaj',
			'regAfter'  => '',
			'regPlain'  => 'Rejestracja jest obecnie niedostępna.',
			'langLabel' => 'Język',
		],
	];
}

/**
 * Sanifica le impostazioni dal form.
 *
 * @param mixed $input POST.
 * @return array{register_url: string, copy: array<string, array<string, string>>}
 */
function lls_login_intro_sanitize_settings( $input ) {
	$out = [
		'register_url' => '/registrati/',
		'copy'         => [],
	];

	if ( ! is_array( $input ) ) {
		foreach ( lls_login_intro_lang_codes() as $lc ) {
			$out['copy'][ $lc ] = array_fill_keys( lls_login_intro_string_keys(), '' );
		}
		return $out;
	}

	$ru = isset( $input['register_url'] ) ? trim( (string) wp_unslash( $input['register_url'] ) ) : '';
	if ( $ru !== '' ) {
		if ( preg_match( '#^https?://#i', $ru ) ) {
			$out['register_url'] = esc_url_raw( $ru );
		} else {
			$ru = trim( $ru, '/' );
			$ru = preg_replace( '#[^a-zA-Z0-9\-/]#', '', $ru );
			$out['register_url'] = $ru !== '' ? '/' . $ru . '/' : '/registrati/';
		}
	}

	$keys = lls_login_intro_string_keys();
	$copy = isset( $input['copy'] ) && is_array( $input['copy'] ) ? $input['copy'] : [];

	foreach ( lls_login_intro_lang_codes() as $lc ) {
		$out['copy'][ $lc ] = [];
		$row                = isset( $copy[ $lc ] ) && is_array( $copy[ $lc ] ) ? $copy[ $lc ] : [];
		foreach ( $keys as $k ) {
			$raw = isset( $row[ $k ] ) ? (string) wp_unslash( $row[ $k ] ) : '';
			if ( 'body' === $k ) {
				$out['copy'][ $lc ][ $k ] = sanitize_textarea_field( $raw );
			} else {
				$out['copy'][ $lc ][ $k ] = sanitize_text_field( $raw );
			}
		}
	}

	return $out;
}

/**
 * Opzioni salvate (sanificate).
 *
 * @return array{register_url: string, copy: array<string, array<string, string>>}
 */
function lls_login_intro_get_saved_settings() {
	$raw = get_option( LLS_LOGIN_INTRO_OPTION, [] );
	if ( ! is_array( $raw ) ) {
		$raw = [];
	}
	$san = lls_login_intro_sanitize_settings( $raw );
	if ( empty( $raw ) ) {
		$san['register_url'] = '/registrati/';
	}
	return $san;
}

/**
 * Testi mostrati sul sito: default + campi compilati in bacheca.
 *
 * @return array<string, array<string, string>>
 */
function lls_login_intro_get_merged_strings() {
	$defaults = lls_login_intro_default_strings();
	$saved    = lls_login_intro_get_saved_settings();
	$copy     = isset( $saved['copy'] ) && is_array( $saved['copy'] ) ? $saved['copy'] : [];
	$keys     = lls_login_intro_string_keys();
	$out      = [];

	foreach ( lls_login_intro_lang_codes() as $lc ) {
		$out[ $lc ] = isset( $defaults[ $lc ] ) ? $defaults[ $lc ] : $defaults['en'];
		foreach ( $keys as $k ) {
			if ( isset( $copy[ $lc ][ $k ] ) && (string) $copy[ $lc ][ $k ] !== '' ) {
				$out[ $lc ][ $k ] = (string) $copy[ $lc ][ $k ];
			}
		}
	}

	return apply_filters( 'lls_login_intro_strings', $out );
}

/**
 * URL assoluto per il link «registrati» (opzione bacheca o attributo shortcode).
 *
 * @param string $shortcode_attr URL/path dall’attributo register_url dello shortcode; vuoto = solo opzioni.
 * @return string URL assoluto o stringa vuota se disabilitato.
 */
function lls_login_intro_get_register_url( $shortcode_attr = '' ) {
	$shortcode_attr = trim( (string) $shortcode_attr );
	if ( $shortcode_attr !== '' ) {
		if ( preg_match( '#^https?://#i', $shortcode_attr ) ) {
			return esc_url_raw( $shortcode_attr );
		}
		$path = trim( $shortcode_attr, '/' );
		$path = preg_replace( '#[^a-zA-Z0-9\-/]#', '', $path );
		return $path !== '' ? home_url( '/' . $path . '/' ) : '';
	}

	$saved = lls_login_intro_get_saved_settings();
	$ru    = isset( $saved['register_url'] ) ? trim( (string) $saved['register_url'] ) : '';
	if ( $ru === '' ) {
		$ru = '/registrati/';
	}
	if ( preg_match( '#^https?://#i', $ru ) ) {
		return esc_url_raw( $ru );
	}
	$path = trim( $ru, '/' );
	return $path !== '' ? home_url( '/' . $path . '/' ) : home_url( '/registrati/' );
}

/**
 * Etichette admin per le lingue.
 *
 * @return array<string, string>
 */
function lls_login_intro_admin_lang_labels() {
	return [
		'en' => 'English',
		'es' => 'Español',
		'it' => 'Italiano',
		'pl' => 'Polski',
	];
}

/**
 * Rendering pagina bacheca.
 */
function lls_login_intro_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$saved = lls_login_intro_get_saved_settings();
	$copy  = $saved['copy'];
	$keys  = lls_login_intro_string_keys();

	$labels = [
		'greeting'  => __( 'Saluto (es. Hello, / Ciao,)', 'language-learning-stories' ),
		'body'      => __( 'Testo principale', 'language-learning-stories' ),
		'regBefore' => __( 'Testo prima del link di iscrizione', 'language-learning-stories' ),
		'regLink'   => __( 'Testo del link di iscrizione', 'language-learning-stories' ),
		'regAfter'  => __( 'Testo dopo il link (di solito vuoto)', 'language-learning-stories' ),
		'regPlain'  => __( 'Messaggio se non c’è pagina iscrizione (registrazione chiusa)', 'language-learning-stories' ),
		'langLabel' => __( 'Etichetta accessibilità del gruppo lingue (aria-label)', 'language-learning-stories' ),
	];

	$lang_labels = lls_login_intro_admin_lang_labels();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Pagina Login', 'language-learning-stories' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Modifica i testi mostrati dallo shortcode [lls_login_intro]. Campo vuoto = usa il testo predefinito del plugin. Sul sito, i pulsanti lingua (English, Español, Italiano, Polski) cambiano solo la visualizzazione.', 'language-learning-stories' ); ?>
		</p>
		<?php
		if ( isset( $_GET['lls_login_intro_saved'] ) && '1' === $_GET['lls_login_intro_saved'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Impostazioni salvate.', 'language-learning-stories' ) . '</p></div>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lls_save_login_intro_settings" />
			<?php wp_nonce_field( 'lls_save_login_intro_settings', 'lls_login_intro_nonce' ); ?>
			<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ); ?>" />

			<h2 class="title" style="margin-top:1.25em;"><?php esc_html_e( 'Link iscrizione', 'language-learning-stories' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Percorso sotto la home (es. registrati) o URL completo. Predefinito: /registrati/', 'language-learning-stories' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lls_li_register_url"><?php esc_html_e( 'URL o slug pagina registrazione', 'language-learning-stories' ); ?></label></th>
					<td>
						<input name="lls_login_intro[register_url]" id="lls_li_register_url" type="text" class="regular-text" value="<?php echo esc_attr( $saved['register_url'] ); ?>" placeholder="registrati" />
					</td>
				</tr>
			</table>

			<h2 class="title" style="margin-top:1.5em;"><?php esc_html_e( 'Testi per lingua', 'language-learning-stories' ); ?></h2>

			<?php foreach ( lls_login_intro_lang_codes() as $lc ) : ?>
				<?php $row = isset( $copy[ $lc ] ) && is_array( $copy[ $lc ] ) ? $copy[ $lc ] : []; ?>
				<div class="postbox" style="margin-top:16px;padding:12px 16px;">
					<h3 style="margin-top:0;"><?php echo esc_html( $lang_labels[ $lc ] ?? $lc ); ?></h3>
					<table class="form-table" role="presentation">
						<?php foreach ( $keys as $k ) : ?>
							<?php
							$val = isset( $row[ $k ] ) ? (string) $row[ $k ] : '';
							$nm  = 'lls_login_intro[copy][' . esc_attr( $lc ) . '][' . esc_attr( $k ) . ']';
							?>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( 'lls_li_' . $lc . '_' . $k ); ?>"><?php echo esc_html( $labels[ $k ] ); ?></label></th>
								<td>
									<?php if ( 'body' === $k ) : ?>
										<textarea name="<?php echo esc_attr( $nm ); ?>" id="<?php echo esc_attr( 'lls_li_' . $lc . '_' . $k ); ?>" class="large-text" rows="4"><?php echo esc_textarea( $val ); ?></textarea>
									<?php else : ?>
										<input type="text" name="<?php echo esc_attr( $nm ); ?>" id="<?php echo esc_attr( 'lls_li_' . $lc . '_' . $k ); ?>" class="large-text" value="<?php echo esc_attr( $val ); ?>" />
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>
			<?php endforeach; ?>

			<?php submit_button( __( 'Salva impostazioni', 'language-learning-stories' ) ); ?>
		</form>
	</div>
	<?php
}
