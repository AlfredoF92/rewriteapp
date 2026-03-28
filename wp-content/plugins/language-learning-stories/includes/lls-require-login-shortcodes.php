<?php
/**
 * Shortcode: reindirizza gli ospiti verso la pagina di login (prima dell’output).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Estrae gli attributi dalla prima occorrenza di [lls_require_login] nel contenuto.
 *
 * @param string $content Contenuto post.
 * @return array<string, string>
 */
function lls_require_login_parse_atts_from_content( $content ) {
	if ( ! is_string( $content ) || $content === '' ) {
		return [];
	}
	if ( ! preg_match( '/\[lls_require_login(\s[^\]]*)?\]/', $content, $m ) ) {
		return [];
	}
	$raw = isset( $m[1] ) ? trim( $m[1] ) : '';
	if ( $raw === '' ) {
		return [];
	}
	$parsed = shortcode_parse_atts( $raw );
	return is_array( $parsed ) ? $parsed : [];
}

/**
 * Costruisce l’URL della pagina di login con redirect di ritorno alla pagina richiesta.
 *
 * @param string $login_attr URL assoluto o percorso sotto la home (es. accedi o /accedi/).
 * @param string $return_url URL dove tornare dopo l’accesso.
 * @return string
 */
function lls_require_login_build_target_url( $login_attr, $return_url ) {
	$return_url = wp_validate_redirect( $return_url, home_url( '/' ) );

	$login_attr = trim( (string) $login_attr );
	if ( $login_attr === '' ) {
		return wp_login_url( $return_url );
	}

	if ( preg_match( '#^https?://#i', $login_attr ) ) {
		$base = esc_url_raw( $login_attr );
	} else {
		$path = '/' . ltrim( $login_attr, '/' );
		$base = home_url( $path );
	}

	return add_query_arg( 'redirect_to', $return_url, $base );
}

/**
 * Redirect ospiti se il contenuto include [lls_require_login].
 */
function lls_require_login_template_redirect() {
	if ( is_user_logged_in() ) {
		return;
	}

	if ( ! is_singular() ) {
		return;
	}

	if ( is_preview() || is_customize_preview() ) {
		return;
	}

	if ( apply_filters( 'lls_require_login_skip_redirect', false ) ) {
		return;
	}

	global $post;
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	if ( ! has_shortcode( $post->post_content, 'lls_require_login' ) ) {
		return;
	}

	$parsed = lls_require_login_parse_atts_from_content( $post->post_content );
	$login    = isset( $parsed['login'] ) ? (string) $parsed['login'] : '';

	$return_url = get_permalink( $post );
	if ( ! is_string( $return_url ) || $return_url === '' ) {
		$return_url = home_url( '/' );
	}

	$target = lls_require_login_build_target_url( $login, $return_url );
	$target = apply_filters( 'lls_require_login_target_url', $target, $post, $parsed );

	wp_safe_redirect( $target, 302 );
	exit;
}

add_action( 'template_redirect', 'lls_require_login_template_redirect', 5 );

/**
 * Shortcode segnaposto (il redirect avviene in template_redirect). Output vuoto.
 *
 * Uso: [lls_require_login login="/accedi/"] in cima alla pagina.
 *
 * @param string[]|string $atts Attributi shortcode.
 * @return string
 */
function lls_shortcode_require_login( $atts ) {
	return '';
}

add_action(
	'init',
	static function () {
		add_shortcode( 'lls_require_login', 'lls_shortcode_require_login' );
	},
	12
);
