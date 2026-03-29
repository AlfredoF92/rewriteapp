<?php
/**
 * Shortcode: card storie in fascia orizzontale responsive con filtri categoria/tag (client-side).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug termini separati da virgola per attributi data (filtri JS).
 *
 * @param int    $post_id  ID post.
 * @param string $taxonomy Slug tassonomia.
 * @return string
 */
function lls_story_rail_term_slugs_csv( $post_id, $taxonomy ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 || ! taxonomy_exists( $taxonomy ) ) {
		return '';
	}
	$terms = get_the_terms( $post_id, $taxonomy );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	$slugs = [];
	foreach ( $terms as $term ) {
		if ( $term instanceof WP_Term ) {
			$slugs[] = $term->slug;
		}
	}
	return implode( ',', $slugs );
}

/**
 * HTML card compatta per la rail.
 *
 * @param WP_Post $post      Post storia.
 * @param int     $words     Parole trama.
 * @param int     $completed Frasi completate.
 * @param int     $total     Frasi totali.
 * @return string
 */
function lls_story_rail_card_html( WP_Post $post, $words, $completed, $total ) {
	$words     = (int) $words;
	$completed = max( 0, (int) $completed );
	$total     = max( 0, (int) $total );
	$summary   = lls_get_story_summary_text( $post, $words );
	$url       = get_permalink( $post );

	$coin_gate = function_exists( 'lls_user_can_access_story' );
	$user_id   = is_user_logged_in() ? get_current_user_id() : 0;
	$cost      = $coin_gate ? lls_get_story_coin_cost( $post->ID ) : 0;
	$can_access = ! $coin_gate || lls_user_can_access_story( $user_id, $post->ID );
	$balance   = ( $coin_gate && $user_id > 0 && function_exists( 'lls_get_user_coin_balance' ) ) ? lls_get_user_coin_balance( $user_id ) : 0;
	$can_afford = $cost <= 0 || $balance >= $cost;

	$cats_csv = taxonomy_exists( 'lls_story_category' ) ? lls_story_rail_term_slugs_csv( $post->ID, 'lls_story_category' ) : '';
	$tags_csv = taxonomy_exists( 'lls_story_tag' ) ? lls_story_rail_term_slugs_csv( $post->ID, 'lls_story_tag' ) : '';

	$cat_line = '';
	if ( taxonomy_exists( 'lls_story_category' ) ) {
		$terms = get_the_terms( $post->ID, 'lls_story_category' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$names = array_map(
				static function ( $t ) {
					return $t instanceof WP_Term ? $t->name : '';
				},
				$terms
			);
			$names = array_filter( $names );
			if ( $names !== [] ) {
				$cat_line = implode( ', ', array_map( 'esc_html', $names ) );
			}
		}
	}
	$tag_line = '';
	if ( taxonomy_exists( 'lls_story_tag' ) ) {
		$terms = get_the_terms( $post->ID, 'lls_story_tag' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$names = array_map(
				static function ( $t ) {
					return $t instanceof WP_Term ? $t->name : '';
				},
				$terms
			);
			$names = array_filter( $names );
			if ( $names !== [] ) {
				$tag_line = implode( ', ', array_map( 'esc_html', $names ) );
			}
		}
	}

	$title_main = esc_html( get_the_title( $post ) );
	$title_sub  = function_exists( 'lls_get_story_title_in_target_lang' ) ? trim( (string) lls_get_story_title_in_target_lang( $post->ID ) ) : '';
	$tlang      = function_exists( 'lls_get_story_target_lang' ) ? lls_get_story_target_lang( $post->ID ) : 'en';

	$img_id = (int) get_post_meta( $post->ID, '_lls_opening_image_id', true );
	$thumb  = '';
	if ( $img_id > 0 ) {
		$thumb = wp_get_attachment_image(
			$img_id,
			'medium_large',
			false,
			[
				'class'   => 'lls-story-rail__img',
				'loading' => 'lazy',
				'alt'     => wp_strip_all_tags( get_the_title( $post ) ),
			]
		);
	}

	$locked_class = ( $coin_gate && $cost > 0 && ! $can_access ) ? ' lls-profile-continue__item--locked' : '';

	ob_start();
	?>
	<article class="lls-story-rail__card lls-profile-continue__item<?php echo esc_attr( $locked_class ); ?>"
		role="listitem"
		data-lls-story-id="<?php echo esc_attr( (string) (int) $post->ID ); ?>"
		data-lls-rail-cats="<?php echo esc_attr( $cats_csv ); ?>"
		data-lls-rail-tags="<?php echo esc_attr( $tags_csv ); ?>">
		<div class="lls-story-rail__media">
			<?php if ( $thumb !== '' ) : ?>
				<?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<div class="lls-story-rail__media-placeholder" aria-hidden="true"></div>
			<?php endif; ?>
		</div>
		<div class="lls-story-rail__body">
			<h3 class="lls-story-title">
				<?php if ( $can_access ) : ?>
					<a href="<?php echo esc_url( $url ); ?>"><?php echo $title_main; ?></a>
				<?php else : ?>
					<span class="lls-story-title__text"><?php echo $title_main; ?></span>
				<?php endif; ?>
			</h3>
			<?php if ( $title_sub !== '' ) : ?>
				<p class="lls-story-rail__title-target" lang="<?php echo esc_attr( $tlang ); ?>"><?php echo esc_html( $title_sub ); ?></p>
			<?php endif; ?>
			<?php if ( $cat_line !== '' || $tag_line !== '' ) : ?>
				<div class="lls-story-rail__tax">
					<?php if ( $cat_line !== '' ) : ?>
						<span class="lls-story-rail__tax-line"><span class="lls-story-rail__tax-label"><?php esc_html_e( 'Categories', 'language-learning-stories' ); ?></span> <?php echo $cat_line; ?></span>
					<?php endif; ?>
					<?php if ( $cat_line !== '' && $tag_line !== '' ) : ?>
						<span class="lls-story-rail__tax-sep" aria-hidden="true"> · </span>
					<?php endif; ?>
					<?php if ( $tag_line !== '' ) : ?>
						<span class="lls-story-rail__tax-line"><span class="lls-story-rail__tax-label"><?php esc_html_e( 'Tags', 'language-learning-stories' ); ?></span> <?php echo $tag_line; ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $summary !== '' ) : ?>
				<p class="lls-story-rail__summary"><?php echo esc_html( $summary ); ?></p>
			<?php endif; ?>
			<div class="lls-story-rail__foot">
				<?php if ( $coin_gate && $cost > 0 ) : ?>
					<span class="lls-story-rail__cost"><?php echo esc_html( sprintf( /* translators: %d: coin cost */ __( '%d coins', 'language-learning-stories' ), $cost ) ); ?></span>
				<?php elseif ( $coin_gate && $cost <= 0 ) : ?>
					<span class="lls-story-rail__cost lls-story-rail__cost--free"><?php esc_html_e( 'Free', 'language-learning-stories' ); ?></span>
				<?php endif; ?>
				<div class="lls-continua-wrap">
					<?php if ( $can_access ) : ?>
						<a class="lls-btn lls-btn-continua lls-btn--sm" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( __( 'Enter the story', 'language-learning-stories' ) ); ?></a>
					<?php elseif ( $coin_gate && $cost > 0 ) : ?>
						<?php if ( ! is_user_logged_in() ) : ?>
							<a class="lls-btn lls-btn-continua lls-btn--sm lls-btn--unlock-login" href="<?php echo esc_url( wp_login_url( function_exists( 'lls_get_frontend_request_url' ) ? lls_get_frontend_request_url() : home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log in to unlock', 'language-learning-stories' ); ?></a>
						<?php elseif ( ! $can_afford ) : ?>
							<button type="button" class="lls-btn lls-btn-continua lls-btn--sm" disabled>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: coin cost */
										__( 'Unlock this story for %d coins', 'language-learning-stories' ),
										$cost
									)
								);
								?>
							</button>
							<span class="lls-unlock-feedback lls-unlock-feedback--error"><?php esc_html_e( 'Not enough coins.', 'language-learning-stories' ); ?></span>
						<?php else : ?>
							<button type="button" class="lls-btn lls-btn-continua lls-btn--sm lls-unlock-story-btn" data-lls-unlock-story="<?php echo esc_attr( (string) (int) $post->ID ); ?>">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: coin cost */
										__( 'Unlock this story for %d coins', 'language-learning-stories' ),
										$cost
									)
								);
								?>
							</button>
							<span class="lls-unlock-feedback lls-unlock-feedback--msg" hidden></span>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</article>
	<?php
	return (string) ob_get_clean();
}

/**
 * Filtri pill (toggle client-side).
 *
 * @param WP_Term[] $cat_terms Termini categoria.
 * @param WP_Term[] $tag_terms Termini tag.
 * @return string
 */
function lls_story_rail_filters_html( array $cat_terms, array $tag_terms ) {
	if ( $cat_terms === [] && $tag_terms === [] ) {
		return '';
	}
	ob_start();
	?>
	<div class="lls-story-rail__filters" role="region" aria-label="<?php echo esc_attr__( 'Filter stories', 'language-learning-stories' ); ?>">
		<?php if ( $cat_terms !== [] ) : ?>
			<div class="lls-story-rail__filter-block">
				<span class="lls-story-rail__filter-heading"><?php esc_html_e( 'Categories', 'language-learning-stories' ); ?></span>
				<div class="lls-story-rail__filter-chips" role="group">
					<?php foreach ( $cat_terms as $term ) : ?>
						<?php
						if ( ! $term instanceof WP_Term ) {
							continue;
						}
						?>
						<button type="button" class="lls-story-rail__chip" data-lls-rail-filter="cat" data-lls-slug="<?php echo esc_attr( $term->slug ); ?>" aria-pressed="false"><?php echo esc_html( $term->name ); ?></button>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $tag_terms !== [] ) : ?>
			<div class="lls-story-rail__filter-block">
				<span class="lls-story-rail__filter-heading"><?php esc_html_e( 'Tags', 'language-learning-stories' ); ?></span>
				<div class="lls-story-rail__filter-chips" role="group">
					<?php foreach ( $tag_terms as $term ) : ?>
						<?php
						if ( ! $term instanceof WP_Term ) {
							continue;
						}
						?>
						<button type="button" class="lls-story-rail__chip" data-lls-rail-filter="tag" data-lls-slug="<?php echo esc_attr( $term->slug ); ?>" aria-pressed="false"><?php echo esc_html( $term->name ); ?></button>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		<p class="lls-story-rail__filter-clear-wrap" hidden data-lls-rail-clear-wrap>
			<button type="button" class="lls-story-rail__clear"><?php esc_html_e( 'Clear filters', 'language-learning-stories' ); ?></button>
		</p>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Shortcode [lls_story_cards_rail].
 *
 * @param array|string $atts Attributi.
 * @return string
 */
function lls_shortcode_story_cards_rail( $atts ) {
	$atts = shortcode_atts(
		[
			'limit'         => '24',
			'words'         => '28',
			'lang'          => '',
			'learn_lang'    => '',
			'show_lang'     => '0',
			'show_filters'  => '1',
		],
		is_array( $atts ) ? $atts : [],
		'lls_story_cards_rail'
	);

	wp_enqueue_style( 'lls-story-cards-rail' );
	wp_enqueue_script( 'lls-story-cards-rail' );

	$limit = max( 1, min( 100, (int) $atts['limit'] ) );
	$words = max( 5, min( 80, (int) $atts['words'] ) );

	$lang = trim( (string) $atts['lang'] );
	if ( $lang === '' ) {
		$lang = function_exists( 'lls_get_user_known_lang' ) ? lls_get_user_known_lang() : 'it';
	} elseif ( function_exists( 'lls_known_lang_codes' ) && ! in_array( $lang, lls_known_lang_codes(), true ) ) {
		$lang = 'it';
	}

	$learn = trim( (string) $atts['learn_lang'] );
	if ( $learn === '' ) {
		$learn = function_exists( 'lls_get_user_learn_target_lang' ) ? lls_get_user_learn_target_lang() : 'en';
	} elseif ( function_exists( 'lls_story_target_lang_codes' ) && ! in_array( $learn, lls_story_target_lang_codes(), true ) ) {
		$learn = 'en';
	}

	$meta_query = function_exists( 'lls_meta_query_stories_for_library' ) ? lls_meta_query_stories_for_library( $lang, $learn ) : [];
	$query_args = [
		'post_type'              => 'lls_story',
		'post_status'            => 'publish',
		'posts_per_page'         => $limit,
		'orderby'                => 'modified',
		'order'                  => 'DESC',
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => true,
	];
	if ( ! empty( $meta_query ) ) {
		$query_args['meta_query'] = $meta_query;
	}

	$cat_terms = [];
	if ( taxonomy_exists( 'lls_story_category' ) ) {
		$t = get_terms( [ 'taxonomy' => 'lls_story_category', 'hide_empty' => true ] );
		if ( ! is_wp_error( $t ) && is_array( $t ) ) {
			$cat_terms = $t;
		}
	}
	$tag_terms = [];
	if ( taxonomy_exists( 'lls_story_tag' ) ) {
		$t = get_terms( [ 'taxonomy' => 'lls_story_tag', 'hide_empty' => true ] );
		if ( ! is_wp_error( $t ) && is_array( $t ) ) {
			$tag_terms = $t;
		}
	}

	$show_filters = ( '1' === $atts['show_filters'] || 'true' === strtolower( (string) $atts['show_filters'] ) );
	$filters_html = ( $show_filters && ( $cat_terms !== [] || $tag_terms !== [] ) )
		? lls_story_rail_filters_html( $cat_terms, $tag_terms )
		: '';

	$q       = new WP_Query( $query_args );
	$user_id = is_user_logged_in() ? get_current_user_id() : 0;
	$cards   = '';

	if ( $q->have_posts() ) {
		while ( $q->have_posts() ) {
			$q->the_post();
			$post = get_post();
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$sentences = get_post_meta( $post->ID, '_lls_sentences', true );
			$total     = is_array( $sentences ) ? count( $sentences ) : 0;
			if ( $total < 1 ) {
				continue;
			}
			$completed = 0;
			if ( $user_id > 0 ) {
				$saved = get_user_meta( $user_id, '_lls_progress_' . $post->ID, true );
				if ( is_array( $saved ) && isset( $saved['completed'] ) ) {
					$completed = (int) $saved['completed'];
				}
			}
			$cards .= lls_story_rail_card_html( $post, $words, $completed, $total );
		}
		wp_reset_postdata();
	}

	$intro = '';
	if ( '1' === $atts['show_lang'] || 'true' === strtolower( (string) $atts['show_lang'] ) ) {
		$labels_iface = function_exists( 'lls_get_known_lang_choice_labels' ) ? lls_get_known_lang_choice_labels() : [];
		$labels_learn = function_exists( 'lls_get_story_target_lang_choice_labels' ) ? lls_get_story_target_lang_choice_labels() : [];
		$iface_label  = isset( $labels_iface[ $lang ] ) ? $labels_iface[ $lang ] : $lang;
		$learn_label  = isset( $labels_learn[ $learn ] ) ? $labels_learn[ $learn ] : $learn;
		$intro        = '<p class="lls-story-rail__lang-note">' . sprintf(
			/* translators: 1: interface language, 2: language to learn */
			esc_html__( 'Stories — interface: %1$s · learn: %2$s', 'language-learning-stories' ),
			esc_html( $iface_label ),
			esc_html( $learn_label )
		) . '</p>';
	}

	$root_class = 'lls-story-rail' . ( $filters_html !== '' ? ' lls-story-rail--has-filters' : '' );
	$inner      = '<div class="' . esc_attr( $root_class ) . '" data-lls-story-rail="1">';
	$inner     .= $intro;
	$inner     .= $filters_html;
	if ( $cards === '' ) {
		$inner .= '<p class="lls-story-rail__empty lls-story-rail__empty--static">' . esc_html__( 'No stories match these filters yet.', 'language-learning-stories' ) . '</p>';
	} else {
		$inner .= '<div class="lls-story-rail__track" role="list">';
		$inner .= $cards;
		$inner .= '</div>';
		$inner .= '<p class="lls-story-rail__empty" hidden data-lls-rail-empty>' . esc_html__( 'No story matches the selected filters.', 'language-learning-stories' ) . '</p>';
	}
	$inner .= '</div>';

	return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
}

add_action(
	'init',
	static function () {
		add_shortcode( 'lls_story_cards_rail', 'lls_shortcode_story_cards_rail' );
	},
	12
);
