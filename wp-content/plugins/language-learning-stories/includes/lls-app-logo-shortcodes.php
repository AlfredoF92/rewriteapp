<?php
/**
 * Shortcode logo / nome app (wordmark stile footer).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug predefinito per il link del logo (pagina library).
 *
 * @return string Slug senza slash.
 */
function lls_app_logo_default_path_slug() {
	return apply_filters( 'lls_app_logo_default_path', 'library' );
}

/**
 * URL assoluto del link logo (predefinito: /library/ sotto la home).
 *
 * @return string
 */
function lls_app_logo_default_url() {
	$slug = trim( lls_app_logo_default_path_slug(), '/' );
	$slug = preg_replace( '#[^a-zA-Z0-9\-/]#', '', $slug );
	if ( $slug === '' ) {
		$slug = 'library';
	}
	return home_url( '/' . $slug . '/' );
}

/**
 * Normalizza url/path dallo shortcode in URL assoluto del sito.
 *
 * @param string $raw Attributo url o path.
 * @return string URL sicuro per href.
 */
function lls_app_logo_resolve_href( $raw ) {
	$raw = trim( (string) $raw );
	if ( $raw === '' ) {
		return lls_app_logo_default_url();
	}
	if ( preg_match( '#^https?://#i', $raw ) ) {
		$u = esc_url_raw( $raw );
		return $u !== '' ? esc_url( $u ) : lls_app_logo_default_url();
	}
	if ( strpos( $raw, '/' ) === 0 ) {
		$path = preg_replace( '#[^a-zA-Z0-9\-/]#', '', $raw );
		return esc_url( home_url( $path === '' ? '/' : $path ) );
	}
	$path = trim( $raw, '/' );
	$path = preg_replace( '#[^a-zA-Z0-9\-/]#', '', $path );
	if ( $path === '' ) {
		return lls_app_logo_default_url();
	}
	return esc_url( home_url( '/' . $path . '/' ) );
}

/**
 * Shortcode: wordmark «ReWrite» (R e W grandi, resto piccolo).
 *
 * Uso: [lls_app_logo] — url, path, link.
 *
 * @param string[]|string $atts Attributi shortcode.
 * @return string
 */
function lls_shortcode_app_logo( $atts ) {
	$atts = shortcode_atts(
		[
			'url'  => '',
			'path' => '',
			'link' => '1',
		],
		is_array( $atts ) ? $atts : [],
		'lls_app_logo'
	);

	$url_attr = trim( (string) $atts['url'] );
	$path_attr = trim( (string) $atts['path'] );
	if ( $path_attr !== '' && $url_attr === '' ) {
		$url = lls_app_logo_resolve_href( $path_attr );
	} elseif ( $url_attr !== '' ) {
		$url = lls_app_logo_resolve_href( $url_attr );
	} else {
		$url = lls_app_logo_default_url();
	}

	$wrap_link = ! in_array( strtolower( trim( (string) $atts['link'] ) ), [ '0', 'false', 'no', 'off' ], true );

	wp_enqueue_style( 'lls-app-logo' );

	$label = __( 'ReWrite', 'language-learning-stories' );

	$wordmark = '<span class="lls-app-logo__wordmark" aria-label="' . esc_attr( $label ) . '">' .
		'<span class="lls-app-logo__big" aria-hidden="true">R</span><span class="lls-app-logo__rest" aria-hidden="true">e</span>' .
		'<span class="lls-app-logo__big" aria-hidden="true">W</span><span class="lls-app-logo__rest" aria-hidden="true">rite</span>' .
		'</span>';

	if ( $wrap_link ) {
		$inner = '<a class="lls-app-logo__link" href="' . esc_url( $url ) . '">' . $wordmark . '</a>';
	} else {
		$inner = $wordmark;
	}

	$html = '<div class="lls-app-logo">' . $inner . '</div>';

	$html = apply_filters( 'lls_app_logo_html', $html, $atts );

	return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $html, 'block' ) : $html;
}

add_action(
	'init',
	static function () {
		add_shortcode( 'lls_app_logo', 'lls_shortcode_app_logo' );
	},
	12
);
