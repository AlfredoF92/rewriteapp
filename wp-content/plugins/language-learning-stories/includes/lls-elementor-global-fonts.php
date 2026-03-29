<?php
/**
 * Allinea i Font globali Elementor (Site Kit) al font delle storie LLS (Manrope) e
 * rende più leggibili le etichette dei preset “System Fonts” nell’editor.
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stack font come in assets/lls-frontend.css (--lls-font).
 */
function lls_elementor_story_font_stack() {
	return '"Manrope", "Segoe UI", Arial, sans-serif';
}

/**
 * Font family value (nome primario) per i controlli Elementor.
 */
function lls_elementor_story_font_family_name() {
	return 'Manrope';
}

/**
 * Famiglie di default Elementor da sostituire con Manrope quando si vuole coerenza con le storie.
 *
 * @return string[]
 */
function lls_elementor_default_fonts_to_replace() {
	return [ 'Roboto', 'Roboto Slab' ];
}

/**
 * Etichette descrittive per i preset System (ID → titolo mostrato).
 *
 * @return array<string, string>
 */
function lls_elementor_system_typography_labels() {
	return [
		'primary'   => __( 'Titoli principali (hero, H1): stesso stile della storia LLS', 'language-learning-stories' ),
		'secondary' => __( 'Sottotitoli e titoli di sezione (H2–H3)', 'language-learning-stories' ),
		'text'      => __( 'Testo corpo: paragrafi e lettura lunga (Manrope, peso leggero)', 'language-learning-stories' ),
		'accent'    => __( 'Accenti: link, etichette, testo in evidenza', 'language-learning-stories' ),
	];
}

/**
 * Applica etichette e font Manrope alle voci system_typography del kit.
 *
 * @param array<string, mixed> $settings Impostazioni kit (_elementor_page_settings).
 * @return array<string, mixed>
 */
function lls_elementor_merge_kit_typography_settings( array $settings ) {
	$labels = lls_elementor_system_typography_labels();
	$man    = lls_elementor_story_font_family_name();
	$swap   = lls_elementor_default_fonts_to_replace();

	if ( ! isset( $settings['system_typography'] ) || ! is_array( $settings['system_typography'] ) ) {
		return $settings;
	}

	foreach ( $settings['system_typography'] as $i => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$id = isset( $row['_id'] ) ? (string) $row['_id'] : '';
		if ( $id !== '' && isset( $labels[ $id ] ) ) {
			$settings['system_typography'][ $i ]['title'] = $labels[ $id ];
		}
		$fam = isset( $row['typography_font_family'] ) ? (string) $row['typography_font_family'] : '';
		if ( $fam === '' || in_array( $fam, $swap, true ) ) {
			$settings['system_typography'][ $i ]['typography_font_family'] = $man;
		}
	}

	/**
	 * Fallback generico (stack) in coda al font scelto, come in pagina storia.
	 *
	 * @param string $fallback Valore attuale (es. "Sans-serif").
	 */
	$fallback = apply_filters(
		'lls_elementor_kit_fallback_generic_fonts',
		'"Segoe UI", Arial, sans-serif'
	);
	if ( is_string( $fallback ) && $fallback !== '' ) {
		$settings['default_generic_fonts'] = $fallback;
	}

	return $settings;
}

/**
 * Short-circuit get_metadata: restituisce [ $settings ] così con $single=true WordPress espone l’array completo.
 *
 * @param mixed  $value     Short-circuit.
 * @param int    $object_id Post ID.
 * @param string $meta_key  Meta key.
 * @param bool   $single    Single.
 * @param string $meta_type Meta type.
 * @return mixed|null
 */
function lls_elementor_filter_kit_page_settings_meta( $value, $object_id, $meta_key, $single, $meta_type ) {
	if ( 'post' !== $meta_type || '_elementor_page_settings' !== $meta_key || ! $single ) {
		return $value;
	}

	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		return $value;
	}

	$kit_id = (int) get_option( 'elementor_active_kit' );
	if ( ! $kit_id || (int) $object_id !== $kit_id ) {
		return $value;
	}

	remove_filter( 'get_post_metadata', 'lls_elementor_filter_kit_page_settings_meta', 10 );
	$settings = get_post_meta( $object_id, $meta_key, true );
	add_filter( 'get_post_metadata', 'lls_elementor_filter_kit_page_settings_meta', 10, 5 );

	if ( ! is_array( $settings ) ) {
		return $value;
	}

	$settings = lls_elementor_merge_kit_typography_settings( $settings );

	// WordPress: se $single && is_array( $check ) → return $check[0].
	return [ $settings ];
}

/**
 * Carica Manrope (stesso URL delle storie) e un piccolo CSS di riferimento per il frontend.
 */
function lls_elementor_enqueue_story_font_stack() {
	$ver = defined( 'LLS_PLUGIN_VERSION' ) ? LLS_PLUGIN_VERSION : '0.2.3';
	wp_enqueue_style(
		'lls-manrope-for-elementor',
		'https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700&display=swap',
		[],
		null
	);
	wp_register_style( 'lls-elementor-font-bridge', false, [ 'lls-manrope-for-elementor' ], $ver );
	wp_enqueue_style( 'lls-elementor-font-bridge' );
	$stack = lls_elementor_story_font_stack();
	wp_add_inline_style(
		'lls-elementor-font-bridge',
		'/* LLS: coerenza con .lls-story-page — font di fallback se il Kit non carica variabili */'
		. "\nbody.elementor-page, .elementor { font-family: {$stack}; }\n"
	);
}

add_filter( 'get_post_metadata', 'lls_elementor_filter_kit_page_settings_meta', 10, 5 );
add_action( 'wp_enqueue_scripts', 'lls_elementor_enqueue_story_font_stack', 20 );
add_action( 'elementor/editor/after_enqueue_styles', 'lls_elementor_enqueue_story_font_stack', 20 );
