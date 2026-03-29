<?php
/**
 * Shortcode: filtri categoria/tag (etichette) per la griglia Elementor con **Query ID = library**.
 *
 * Il filtro è lato server tramite `elementor/query/library` e parametri URL `lls_lib_cat` / `lls_lib_tag`.
 * Nel widget Loop / Post: Query → **Query ID** = `library` (non è l’ID CSS).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode [lls_library_grid_filters].
 *
 * Attributi:
 * - show_clear: 1/0 pulsante reset (default 1).
 *
 * @param array|string $atts Attributi.
 * @return string
 */
function lls_shortcode_library_grid_filters( $atts ) {
	$atts = shortcode_atts(
		[
			'show_clear' => '1',
		],
		is_array( $atts ) ? $atts : [],
		'lls_library_grid_filters'
	);

	$show_clear = ( '1' === $atts['show_clear'] || 'true' === strtolower( (string) $atts['show_clear'] ) );

	$cat_terms = [];
	if ( taxonomy_exists( 'lls_story_category' ) ) {
		$t = get_terms(
			[
				'taxonomy'   => 'lls_story_category',
				'hide_empty' => true,
			]
		);
		if ( ! is_wp_error( $t ) && is_array( $t ) ) {
			$cat_terms = $t;
		}
	}
	$tag_terms = [];
	if ( taxonomy_exists( 'lls_story_tag' ) ) {
		$t = get_terms(
			[
				'taxonomy'   => 'lls_story_tag',
				'hide_empty' => true,
			]
		);
		if ( ! is_wp_error( $t ) && is_array( $t ) ) {
			$tag_terms = $t;
		}
	}

	if ( $cat_terms === [] && $tag_terms === [] ) {
		$inner = '<p class="lls-lib-grid-filters__empty">' .
			esc_html__( 'No categories or tags to filter yet.', 'language-learning-stories' ) .
			'</p>';
		return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
	}

	$param_cat = function_exists( 'lls_lib_query_param_category' ) ? lls_lib_query_param_category() : 'lls_lib_cat';
	$param_tag = function_exists( 'lls_lib_query_param_tag' ) ? lls_lib_query_param_tag() : 'lls_lib_tag';

	$active_cats = function_exists( 'lls_lib_query_validate_term_slugs' )
		? lls_lib_query_validate_term_slugs( lls_lib_query_parse_slugs( $param_cat ), 'lls_story_category' )
		: [];
	$active_tags = function_exists( 'lls_lib_query_validate_term_slugs' )
		? lls_lib_query_validate_term_slugs( lls_lib_query_parse_slugs( $param_tag ), 'lls_story_tag' )
		: [];

	$has_filters = ( $active_cats !== [] || $active_tags !== [] );

	$plugin_main = dirname( __DIR__ ) . '/language-learning-stories.php';
	$plugin_url  = plugin_dir_url( $plugin_main );
	$ver         = defined( 'LLS_PLUGIN_VERSION' ) ? LLS_PLUGIN_VERSION : '0.2.3';

	wp_enqueue_style(
		'lls-frontend-font',
		'https://fonts.googleapis.com/css2?family=Manrope:wght@300;500;600&display=swap',
		[],
		null
	);
	wp_enqueue_style( 'lls-shortcodes-shared', $plugin_url . 'assets/lls-shortcodes-shared.css', [ 'lls-frontend-font' ], $ver );
	wp_enqueue_style(
		'lls-library-grid-filters',
		$plugin_url . 'assets/lls-library-grid-filters.css',
		[ 'lls-shortcodes-shared' ],
		$ver
	);
	wp_enqueue_script(
		'lls-library-grid-filters',
		$plugin_url . 'assets/lls-library-grid-filters.js',
		[],
		$ver,
		true
	);

	wp_localize_script(
		'lls-library-grid-filters',
		'llsLibraryGridFilters',
		[
			'paramCat' => $param_cat,
			'paramTag' => $param_tag,
		]
	);

	ob_start();
	?>
	<div
		class="lls-lib-grid-filters"
		data-lls-lib-grid-filters
		role="region"
		aria-label="<?php echo esc_attr__( 'Filter stories in the grid', 'language-learning-stories' ); ?>"
	>
		<?php if ( $cat_terms !== [] ) : ?>
			<div class="lls-lib-grid-filters__block">
				<span class="lls-lib-grid-filters__heading"><?php esc_html_e( 'Categories', 'language-learning-stories' ); ?></span>
				<div class="lls-lib-grid-filters__chips" role="group">
					<?php foreach ( $cat_terms as $term ) : ?>
						<?php
						if ( ! $term instanceof WP_Term ) {
							continue;
						}
						$on    = in_array( $term->slug, $active_cats, true );
						$class = 'lls-lib-grid-chip' . ( $on ? ' is-active' : '' );
						?>
						<button type="button" class="<?php echo esc_attr( $class ); ?>" data-lls-filter="cat" data-lls-slug="<?php echo esc_attr( $term->slug ); ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>">
							<?php echo esc_html( $term->name ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $tag_terms !== [] ) : ?>
			<div class="lls-lib-grid-filters__block">
				<span class="lls-lib-grid-filters__heading"><?php esc_html_e( 'Tags', 'language-learning-stories' ); ?></span>
				<div class="lls-lib-grid-filters__chips" role="group">
					<?php foreach ( $tag_terms as $term ) : ?>
						<?php
						if ( ! $term instanceof WP_Term ) {
							continue;
						}
						$on    = in_array( $term->slug, $active_tags, true );
						$class = 'lls-lib-grid-chip' . ( $on ? ' is-active' : '' );
						?>
						<button type="button" class="<?php echo esc_attr( $class ); ?>" data-lls-filter="tag" data-lls-slug="<?php echo esc_attr( $term->slug ); ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>">
							<?php echo esc_html( $term->name ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $show_clear ) : ?>
			<p class="lls-lib-grid-filters__clear-wrap" <?php echo $has_filters ? '' : 'hidden'; ?> data-lls-lib-grid-clear-wrap>
				<button type="button" class="lls-lib-grid-clear"><?php esc_html_e( 'Clear filters', 'language-learning-stories' ); ?></button>
			</p>
		<?php endif; ?>
	</div>
	<?php
	$inner = (string) ob_get_clean();
	return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
}

add_action(
	'init',
	static function () {
		add_shortcode( 'lls_library_grid_filters', 'lls_shortcode_library_grid_filters' );
	}
);
