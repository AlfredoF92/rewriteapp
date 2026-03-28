<?php
/**
 * Shortcode Coin: totale frasi completate dall’utente (tutte le storie).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode [coin] / [lls_coin]: etichetta + numero (si aggiorna in pagina storia dopo ogni salvataggio progresso).
 *
 * Attributi: label (predefinito "Coin"), vuoto per nascondere l’etichetta.
 *
 * @param string[]|string $atts    Attributi shortcode.
 * @param string          $content Contenuto (non usato).
 * @param string          $tag     Nome shortcode (`coin` o `lls_coin`).
 * @return string
 */
function lls_shortcode_coin( $atts, $content = '', $tag = '' ) {
	if ( ! function_exists( 'lls_wrap_shortcode_html' ) || ! function_exists( 'lls_get_user_total_completed_sentences' ) ) {
		return '';
	}

	$atts = shortcode_atts(
		[
			'label' => __( 'Coin', 'language-learning-stories' ),
		],
		$atts,
		$tag !== '' ? $tag : 'coin'
	);

	$user_id = get_current_user_id();
	$total   = $user_id ? lls_get_user_total_completed_sentences( $user_id ) : 0;

	$label = trim( (string) $atts['label'] );
	$label_html = '' !== $label
		? '<span class="lls-coin__label">' . esc_html( $label ) . '</span>'
		: '';

	return lls_wrap_shortcode_html(
		'<span class="lls-coin" role="status" aria-live="polite" aria-atomic="true">'
		. $label_html
		. '<span class="lls-coin__value" data-lls-coin-value>' . esc_html( (string) (int) $total ) . '</span>'
		. '</span>',
		'contents'
	);
}

add_action(
	'init',
	static function () {
		add_shortcode( 'coin', 'lls_shortcode_coin' );
		add_shortcode( 'lls_coin', 'lls_shortcode_coin' );
	}
);
