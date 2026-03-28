<?php
/**
 * Shortcode header: saluto utente e box frasi completate (ultimi 7 giorni).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Chiave user meta: array associativo data Y-m-d => numero frasi completate quel giorno.
 */
function lls_daily_phrases_meta_key() {
	return '_lls_daily_phrases';
}

/**
 * Incrementa il contatore frasi completate per il giorno corrente (fuso orario del sito).
 *
 * @param int $user_id ID utente.
 * @param int $delta   Quante frasi in più rispetto al salvataggio precedente (solo positivo).
 */
function lls_increment_user_daily_phrases( $user_id, $delta ) {
	$user_id = (int) $user_id;
	$delta   = (int) $delta;
	if ( $user_id <= 0 || $delta <= 0 ) {
		return;
	}

	$today = current_time( 'Y-m-d' );
	$data  = get_user_meta( $user_id, lls_daily_phrases_meta_key(), true );
	if ( ! is_array( $data ) ) {
		$data = [];
	}

	$data[ $today ] = isset( $data[ $today ] ) ? (int) $data[ $today ] + $delta : $delta;

	// Mantiene solo gli ultimi ~40 giorni per non far crescere il meta all'infinito.
	$tz     = wp_timezone();
	$cutoff = ( new DateTimeImmutable( $today, $tz ) )->modify( '-40 days' )->format( 'Y-m-d' );
	foreach ( array_keys( $data ) as $day ) {
		if ( strcmp( $day, $cutoff ) < 0 ) {
			unset( $data[ $day ] );
		}
	}

	update_user_meta( $user_id, lls_daily_phrases_meta_key(), $data );
}

/**
 * Restituisce i conteggi per gli ultimi 7 giorni (dal più vecchio al più recente, oggi ultimo).
 *
 * @param int $user_id ID utente.
 * @return int[] Array di 7 interi.
 */
function lls_get_user_daily_phrases_last_7_days( $user_id ) {
	$user_id = (int) $user_id;
	$counts  = array_fill( 0, 7, 0 );
	if ( $user_id <= 0 ) {
		return $counts;
	}

	$data = get_user_meta( $user_id, lls_daily_phrases_meta_key(), true );
	if ( ! is_array( $data ) ) {
		return $counts;
	}

	$tz = wp_timezone();
	for ( $i = 0; $i < 7; $i++ ) {
		$d = ( new DateTimeImmutable( 'now', $tz ) )->modify( '-' . ( 6 - $i ) . ' days' )->format( 'Y-m-d' );
		if ( isset( $data[ $d ] ) ) {
			$counts[ $i ] = (int) $data[ $d ];
		}
	}

	return $counts;
}

/**
 * Shortcode: saluto o invito al login.
 *
 * Uso: [lls_header_greeting]
 *
 * @return string
 */
function lls_shortcode_header_greeting() {
	if ( is_user_logged_in() ) {
		$user = wp_get_current_user();
		$name = $user->display_name ? $user->display_name : $user->user_login;
		return '<span class="lls-sc-greeting">' . sprintf(
			/* translators: %s: display name */
			esc_html__( 'Ciao, %s.', 'language-learning-stories' ),
			esc_html( $name )
		) . '</span>';
	}

	$login_url = wp_login_url( get_permalink() ?: home_url( '/' ) );
	return '<span class="lls-sc-greeting lls-sc-greeting--guest">' . sprintf(
		'<a href="%1$s">%2$s</a>',
		esc_url( $login_url ),
		esc_html__( 'Fai il login', 'language-learning-stories' )
	) . '</span>';
}

/**
 * Shortcode: 7 box con numero frasi per giorno.
 *
 * Uso: [lls_header_daily_phrases]
 *
 * @return string
 */
function lls_shortcode_header_daily_phrases() {
	$user_id = is_user_logged_in() ? get_current_user_id() : 0;
	$counts  = lls_get_user_daily_phrases_last_7_days( $user_id );

	$tz = wp_timezone();
	$days_html = '';
	for ( $i = 0; $i < 7; $i++ ) {
		$date = ( new DateTimeImmutable( 'now', $tz ) )->modify( '-' . ( 6 - $i ) . ' days' );
		$label = date_i18n( 'D j', $date->getTimestamp() );
		$n     = isset( $counts[ $i ] ) ? (int) $counts[ $i ] : 0;

		$days_html .= sprintf(
			'<div class="lls-sc-day" title="%1$s"><span class="lls-sc-day__label">%2$s</span><span class="lls-sc-day__count">%3$d</span></div>',
			esc_attr( $date->format( 'Y-m-d' ) ),
			esc_html( $label ),
			$n
		);
	}

	return '<div class="lls-sc-week">' . $days_html . '</div>';
}

add_action(
	'init',
	static function () {
		add_shortcode( 'lls_header_greeting', 'lls_shortcode_header_greeting' );
		add_shortcode( 'lls_header_daily_phrases', 'lls_shortcode_header_daily_phrases' );
	}
);
