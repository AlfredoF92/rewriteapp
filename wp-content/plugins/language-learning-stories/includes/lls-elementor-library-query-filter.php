<?php
/**
 * Filtro lato server per il Loop / Griglia Elementor con Query ID = `library`.
 *
 * Elementor Pro esegue: do_action( "elementor/query/{$query_id}", $wp_query, $widget );
 * @see ElementorPro\Modules\QueryControl\Classes\Elementor_Post_Query::pre_get_posts_query_filter()
 *
 * Comportamento (post type `lls_story`):
 * - Di default: solo storie con `_lls_known_lang` e `_lls_target_lang` allineate al profilo
 *   ({@see lls_get_user_known_lang()}, {@see lls_get_user_learn_target_lang()}), come {@see lls_meta_query_stories_for_library()}.
 * - Parametri URL (GET) per affinare:
 *   - lls_lib_cat: slug categorie (lls_story_category), separati da virgola
 *   - lls_lib_tag: slug tag (lls_story_tag), separati da virgola
 *
 * Query ID `library_user`: solo storie con progresso salvato e non completate al 100%
 * ({@see lls_get_user_in_progress_story_ids()}). Ospiti: nessun risultato.
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var string Param GET categorie */
function lls_lib_query_param_category() {
	return 'lls_lib_cat';
}

/** @var string Param GET tag */
function lls_lib_query_param_tag() {
	return 'lls_lib_tag';
}

/**
 * Slug validati dalla richiesta.
 *
 * @param string $param Nome parametro GET.
 * @return string[]
 */
function lls_lib_query_parse_slugs( $param ) {
	if ( empty( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return [];
	}
	$raw = wp_unslash( $_GET[ $param ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( is_array( $raw ) ) {
		$parts = $raw;
	} else {
		$parts = explode( ',', (string) $raw );
	}
	$out = [];
	foreach ( $parts as $p ) {
		$s = sanitize_title( trim( (string) $p ) );
		if ( $s !== '' ) {
			$out[] = $s;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Tiene solo slug che esistono nella tassonomia.
 *
 * @param string[] $slugs  Slug.
 * @param string   $taxonomy Slug tassonomia.
 * @return string[]
 */
function lls_lib_query_validate_term_slugs( array $slugs, $taxonomy ) {
	if ( $slugs === [] || ! taxonomy_exists( $taxonomy ) ) {
		return [];
	}
	$ok = [];
	foreach ( $slugs as $slug ) {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term instanceof WP_Term ) {
			$ok[] = $term->slug;
		}
	}
	return array_values( array_unique( $ok ) );
}

/**
 * La query Elementor è per il post type lls_story (solo o tra altri).
 *
 * @param \WP_Query $query Query.
 * @return bool
 */
function lls_elementor_query_library_targets_lls_story( $query ) {
	if ( ! $query instanceof \WP_Query ) {
		return false;
	}
	$pt = $query->get( 'post_type' );
	if ( 'lls_story' === $pt ) {
		return true;
	}
	if ( is_array( $pt ) && in_array( 'lls_story', $pt, true ) ) {
		return true;
	}
	return false;
}

/**
 * Unisce un meta_query alla query (AND con l’eventuale meta già impostato da Elementor).
 *
 * @param \WP_Query $query Query.
 * @param array     $append Meta query da aggiungere (struttura WP_Meta_Query).
 */
function lls_elementor_query_library_merge_meta_query( $query, array $append ) {
	if ( $append === [] ) {
		return;
	}
	$existing = $query->get( 'meta_query' );
	if ( ! is_array( $existing ) || empty( $existing ) ) {
		$query->set( 'meta_query', $append );
		return;
	}
	$query->set(
		'meta_query',
		[
			'relation' => 'AND',
			$existing,
			$append,
		]
	);
}

/**
 * Unisce tax_query (stesso schema di Elementor / WP_Tax_Query).
 *
 * @param \WP_Query $query Query.
 * @param array     $append Elenco di clausole taxonomy.
 */
function lls_elementor_query_library_merge_tax_query( $query, array $append ) {
	if ( $append === [] ) {
		return;
	}
	$existing = $query->get( 'tax_query' );
	if ( ! is_array( $existing ) || empty( $existing ) ) {
		if ( count( $append ) > 1 ) {
			$query->set( 'tax_query', array_merge( [ 'relation' => 'AND' ], $append ) );
		} else {
			// WP_Tax_Query richiede un array di clausole, non una singola clausola come array associativo.
			$query->set( 'tax_query', [ $append[0] ] );
		}
		return;
	}
	$merged   = [ 'relation' => 'AND' ];
	$merged[] = $existing;
	foreach ( $append as $clause ) {
		$merged[] = $clause;
	}
	$query->set( 'tax_query', $merged );
}

/**
 * Applica meta lingua libreria + filtri tassonomia URL alla query Elementor (Query ID = library).
 *
 * @param \WP_Query              $query  Query in costruzione.
 * @param \Elementor\Widget_Base $widget Widget (non usato, ma richiesto da Elementor).
 */
function lls_elementor_query_library_apply_filters( $query, $widget ) {
	if ( ! $query instanceof \WP_Query ) {
		return;
	}

	if ( lls_elementor_query_library_targets_lls_story( $query ) ) {
		if ( function_exists( 'lls_meta_query_stories_for_library' )
			&& function_exists( 'lls_get_user_known_lang' )
			&& function_exists( 'lls_get_user_learn_target_lang' ) ) {
			$lib_meta = lls_meta_query_stories_for_library(
				lls_get_user_known_lang(),
				lls_get_user_learn_target_lang()
			);
			if ( ! empty( $lib_meta ) ) {
				lls_elementor_query_library_merge_meta_query( $query, $lib_meta );
			}
		}
	}

	$cats = lls_lib_query_validate_term_slugs(
		lls_lib_query_parse_slugs( lls_lib_query_param_category() ),
		'lls_story_category'
	);
	$tags = lls_lib_query_validate_term_slugs(
		lls_lib_query_parse_slugs( lls_lib_query_param_tag() ),
		'lls_story_tag'
	);

	$tax_append = [];
	if ( $cats !== [] ) {
		$tax_append[] = [
			'taxonomy' => 'lls_story_category',
			'field'    => 'slug',
			'terms'    => $cats,
			'operator' => 'IN',
		];
	}
	if ( $tags !== [] ) {
		$tax_append[] = [
			'taxonomy' => 'lls_story_tag',
			'field'    => 'slug',
			'terms'    => $tags,
			'operator' => 'IN',
		];
	}
	if ( $tax_append !== [] ) {
		lls_elementor_query_library_merge_tax_query( $query, $tax_append );
	}
}

/**
 * Loop Grid con Query ID = `library_user`: storie in corso per l’utente corrente.
 *
 * @param \WP_Query              $query  Query in costruzione.
 * @param \Elementor\Widget_Base $widget Widget Elementor.
 */
function lls_elementor_query_library_user_apply_filters( $query, $widget ) {
	if ( ! $query instanceof \WP_Query ) {
		return;
	}
	if ( ! lls_elementor_query_library_targets_lls_story( $query ) ) {
		return;
	}

	$ids = [];
	if ( is_user_logged_in() && function_exists( 'lls_get_user_in_progress_story_ids' ) ) {
		$ids = lls_get_user_in_progress_story_ids( get_current_user_id() );
	}

	if ( $ids === [] ) {
		$ids = [ 0 ];
	}

	$query->set( 'post__in', $ids );
	$query->set( 'orderby', 'post__in' );
}

add_action( 'elementor/query/library', 'lls_elementor_query_library_apply_filters', 10, 2 );
add_action( 'elementor/query/library_user', 'lls_elementor_query_library_user_apply_filters', 10, 2 );
