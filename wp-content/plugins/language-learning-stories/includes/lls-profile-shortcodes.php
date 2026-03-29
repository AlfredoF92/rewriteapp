<?php
/**
 * Shortcode area personale: saluto e storie in corso.
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User meta: elenco ID storia in ordine di accesso recente (salvataggio progresso).
 */
function lls_recent_stories_meta_key() {
	return '_lls_recent_stories';
}

/**
 * Aggiorna l’ordine «ultime storie» dopo un salvataggio progresso.
 *
 * @param int $user_id  ID utente.
 * @param int $story_id ID storia.
 */
function lls_touch_user_recent_story( $user_id, $story_id ) {
	$user_id  = (int) $user_id;
	$story_id = (int) $story_id;
	if ( $user_id <= 0 || $story_id <= 0 ) {
		return;
	}
	$recent = get_user_meta( $user_id, lls_recent_stories_meta_key(), true );
	if ( ! is_array( $recent ) ) {
		$recent = [];
	}
	$recent = array_values( array_diff( array_map( 'intval', $recent ), [ $story_id ] ) );
	array_unshift( $recent, $story_id );
	$recent = array_slice( $recent, 0, 40 );
	update_user_meta( $user_id, lls_recent_stories_meta_key(), $recent );
}

/**
 * ID storie per cui esiste meta _lls_progress_{id}.
 *
 * @param int $user_id ID utente.
 * @return int[]
 */
function lls_collect_user_progress_story_ids( $user_id ) {
	global $wpdb;
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return [];
	}
	$like = $wpdb->esc_like( '_lls_progress_' ) . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$keys = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
			$user_id,
			$like
		)
	);
	$ids = [];
	foreach ( $keys as $key ) {
		if ( preg_match( '/^_lls_progress_(\d+)$/', $key, $m ) ) {
			$ids[] = (int) $m[1];
		}
	}
	return array_values( array_unique( $ids ) );
}

/**
 * Somma delle frasi completate su tutte le storie (valore `completed` nei meta `_lls_progress_{id}`).
 *
 * @param int $user_id ID utente.
 * @return int
 */
function lls_get_user_total_completed_sentences( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return 0;
	}
	$total = 0;
	foreach ( lls_collect_user_progress_story_ids( $user_id ) as $story_id ) {
		$saved = get_user_meta( $user_id, '_lls_progress_' . $story_id, true );
		if ( is_array( $saved ) && isset( $saved['completed'] ) ) {
			$total += max( 0, (int) $saved['completed'] );
		}
	}
	return $total;
}

/**
 * User meta: log frasi completate (text_it, text_en, story_id, sentence_index, ts).
 *
 * @return string
 */
function lls_completed_phrases_log_meta_key() {
	return '_lls_completed_phrases_log';
}

/**
 * Aggiunge al log le frasi completate tra due indici (escluso il vecchio, incluso il nuovo-1).
 *
 * @param int $user_id        ID utente.
 * @param int $story_id       ID storia.
 * @param int $old_completed  Valore precedente di `completed`.
 * @param int $new_completed  Nuovo valore di `completed`.
 */
function lls_log_completed_phrases_range( $user_id, $story_id, $old_completed, $new_completed ) {
	$user_id       = (int) $user_id;
	$story_id      = (int) $story_id;
	$old_completed = max( 0, (int) $old_completed );
	$new_completed = max( 0, (int) $new_completed );
	if ( $user_id <= 0 || $story_id <= 0 || $new_completed <= $old_completed ) {
		return;
	}
	$sentences = get_post_meta( $story_id, '_lls_sentences', true );
	if ( ! is_array( $sentences ) ) {
		return;
	}
	$log = get_user_meta( $user_id, lls_completed_phrases_log_meta_key(), true );
	if ( ! is_array( $log ) ) {
		$log = [];
	}
	$now = current_time( 'mysql' );
	$ts  = current_time( 'timestamp' );
	for ( $i = $old_completed; $i < $new_completed; $i++ ) {
		if ( ! isset( $sentences[ $i ] ) || ! is_array( $sentences[ $i ] ) ) {
			continue;
		}
		$row   = $sentences[ $i ];
		$text_it = isset( $row['text_it'] ) ? wp_strip_all_tags( (string) $row['text_it'] ) : '';
		$text_en = isset( $row['main_translation'] ) ? wp_strip_all_tags( (string) $row['main_translation'] ) : '';
		if ( $text_it === '' && $text_en !== '' ) {
			$text_it = $text_en;
		}
		if ( $text_en === '' && $text_it !== '' ) {
			$text_en = $text_it;
		}
		$log[] = [
			'story_id'         => $story_id,
			'sentence_index'   => $i,
			'text_it'          => $text_it,
			'text_en'          => $text_en,
			'text'             => $text_it,
			'at'               => $now,
			'ts'               => $ts,
			'backfill'         => false,
		];
	}
	/**
	 * Numero massimo di voci nel log frasi (le più vecchie vengono rimosse).
	 *
	 * @param int $max Predefinito 2000.
	 */
	$max = (int) apply_filters( 'lls_max_completed_phrases_log', 2000 );
	if ( $max > 0 && count( $log ) > $max ) {
		$log = array_slice( $log, -$max );
	}
	update_user_meta( $user_id, lls_completed_phrases_log_meta_key(), $log );
}

/**
 * Una tantum: se il log è vuoto ma c’è progresso salvato, ricostruisce le voci senza data precisa.
 *
 * @param int $user_id ID utente.
 */
function lls_backfill_completed_phrases_log_if_needed( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return;
	}
	if ( get_user_meta( $user_id, '_lls_completed_phrases_backfilled', true ) === '1' ) {
		return;
	}
	$log = get_user_meta( $user_id, lls_completed_phrases_log_meta_key(), true );
	if ( is_array( $log ) && count( $log ) > 0 ) {
		update_user_meta( $user_id, '_lls_completed_phrases_backfilled', '1' );
		return;
	}
	$built = [];
	foreach ( lls_collect_user_progress_story_ids( $user_id ) as $story_id ) {
		$saved     = get_user_meta( $user_id, '_lls_progress_' . $story_id, true );
		$completed = ( is_array( $saved ) && isset( $saved['completed'] ) ) ? max( 0, (int) $saved['completed'] ) : 0;
		$sentences = get_post_meta( $story_id, '_lls_sentences', true );
		if ( ! is_array( $sentences ) ) {
			continue;
		}
		$n = count( $sentences );
		for ( $i = 0; $i < $completed && $i < $n; $i++ ) {
			if ( ! isset( $sentences[ $i ] ) || ! is_array( $sentences[ $i ] ) ) {
				continue;
			}
			$row     = $sentences[ $i ];
			$text_it = isset( $row['text_it'] ) ? wp_strip_all_tags( (string) $row['text_it'] ) : '';
			$text_en = isset( $row['main_translation'] ) ? wp_strip_all_tags( (string) $row['main_translation'] ) : '';
			if ( $text_it === '' && $text_en !== '' ) {
				$text_it = $text_en;
			}
			if ( $text_en === '' && $text_it !== '' ) {
				$text_en = $text_it;
			}
			$built[] = [
				'story_id'       => $story_id,
				'sentence_index' => $i,
				'text_it'        => $text_it,
				'text_en'        => $text_en,
				'text'           => $text_it,
				'at'             => '',
				'ts'             => 0,
				'backfill'       => true,
			];
		}
	}
	if ( count( $built ) > 0 ) {
		update_user_meta( $user_id, lls_completed_phrases_log_meta_key(), $built );
	}
	update_user_meta( $user_id, '_lls_completed_phrases_backfilled', '1' );
}

/**
 * Risolve italiano / inglese per una riga del log (supporta vecchie voci solo con `text`).
 *
 * @param array $row Voce log.
 * @return array{ text_it: string, text_en: string }
 */
function lls_completed_phrase_resolve_texts( array $row ) {
	$text_it = isset( $row['text_it'] ) ? (string) $row['text_it'] : '';
	$text_en = isset( $row['text_en'] ) ? (string) $row['text_en'] : '';
	if ( $text_it === '' && isset( $row['text'] ) ) {
		$text_it = (string) $row['text'];
	}
	$story_id = isset( $row['story_id'] ) ? (int) $row['story_id'] : 0;
	$idx      = isset( $row['sentence_index'] ) ? (int) $row['sentence_index'] : -1;
	if ( ( $text_it === '' || $text_en === '' ) && $story_id > 0 && $idx >= 0 ) {
		$sentences = get_post_meta( $story_id, '_lls_sentences', true );
		if ( is_array( $sentences ) && isset( $sentences[ $idx ] ) && is_array( $sentences[ $idx ] ) ) {
			$s = $sentences[ $idx ];
			if ( $text_it === '' && isset( $s['text_it'] ) ) {
				$text_it = wp_strip_all_tags( (string) $s['text_it'] );
			}
			if ( $text_en === '' && isset( $s['main_translation'] ) ) {
				$text_en = wp_strip_all_tags( (string) $s['main_translation'] );
			}
		}
	}
	if ( $text_en === '' && $text_it !== '' ) {
		$text_en = $text_it;
	}
	if ( $text_it === '' && $text_en !== '' ) {
		$text_it = $text_en;
	}
	return [
		'text_it' => $text_it,
		'text_en' => $text_en,
	];
}

/**
 * Ordina gli ID: prima _lls_recent_stories, poi gli altri per data modifica post decrescente.
 *
 * @param int   $user_id ID utente.
 * @param int[] $ids     ID storie candidati.
 * @return int[]
 */
function lls_order_story_ids_by_recent( $user_id, array $ids ) {
	$user_id = (int) $user_id;
	$ids     = array_values( array_unique( array_map( 'intval', $ids ) ) );
	if ( empty( $ids ) ) {
		return [];
	}
	$recent = get_user_meta( $user_id, lls_recent_stories_meta_key(), true );
	if ( ! is_array( $recent ) ) {
		$recent = [];
	}
	$recent    = array_map( 'intval', $recent );
	$seen      = [];
	$ordered   = [];
	foreach ( $recent as $sid ) {
		if ( $sid > 0 && in_array( $sid, $ids, true ) && ! isset( $seen[ $sid ] ) ) {
			$ordered[]     = $sid;
			$seen[ $sid ] = true;
		}
	}
	$rest = [];
	foreach ( $ids as $sid ) {
		if ( ! isset( $seen[ $sid ] ) ) {
			$rest[] = $sid;
		}
	}
	usort(
		$rest,
		static function ( $a, $b ) {
			$ta = (int) get_post_modified_time( 'U', true, $a );
			$tb = (int) get_post_modified_time( 'U', true, $b );
			return $tb <=> $ta;
		}
	);
	return array_merge( $ordered, $rest );
}

/**
 * Testo trama (estratto o inizio contenuto).
 *
 * @param WP_Post $post Post storia.
 * @param int     $words Max parole.
 * @return string
 */
function lls_get_story_summary_text( $post, $words = 40 ) {
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	if ( has_excerpt( $post ) ) {
		return wp_strip_all_tags( get_the_excerpt( $post ) );
	}
	$raw = wp_strip_all_tags( (string) $post->post_content );
	return wp_trim_words( $raw, $words, '…' );
}

/**
 * Meta query: storie pubblicate per lingua interfaccia (_lls_known_lang).
 * Per «it» include anche storie senza meta o vuote (comportamento legacy del plugin).
 *
 * @param string $lang Codice it|pl|es.
 * @return array<int, array<string, mixed>>
 */
function lls_meta_query_stories_for_interface_lang( $lang ) {
	if ( ! function_exists( 'lls_known_lang_codes' ) ) {
		return [];
	}
	$lang = in_array( $lang, lls_known_lang_codes(), true ) ? $lang : 'it';
	if ( 'it' === $lang ) {
		$query = [
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
				'key'   => '_lls_known_lang',
				'value' => 'it',
			],
		];
	} else {
		$query = [
			[
				'key'   => '_lls_known_lang',
				'value' => $lang,
			],
		];
	}
	/**
	 * Filtro: meta_query per elenco storie per lingua interfaccia (es. libreria).
	 *
	 * @param array  $query Meta query costruita.
	 * @param string $lang  Codice lingua.
	 */
	return apply_filters( 'lls_meta_query_stories_for_interface_lang', $query, $lang );
}

/**
 * Normalizza un frammento meta_query (gruppo con relation o singola clausola) per usarlo come figlio di un AND.
 *
 * @param array<string, mixed> $fragment Output di {@see lls_meta_query_stories_for_interface_lang()} o learn target.
 * @return array<string, mixed>
 */
function lls_meta_query_fragment_as_clause( $fragment ) {
	if ( ! is_array( $fragment ) ) {
		return [];
	}
	if ( isset( $fragment['relation'] ) && is_string( $fragment['relation'] ) ) {
		return $fragment;
	}
	if ( isset( $fragment[0] ) && is_array( $fragment[0] ) && count( $fragment ) === 1 ) {
		return $fragment[0];
	}
	return $fragment;
}

/**
 * Meta query: storie per lingua da imparare (_lls_target_lang).
 * Per «en» include anche storie senza meta o vuote (come {@see lls_get_story_target_lang()}).
 *
 * @param string $code Codice en|pl|it|es.
 * @return array<string, mixed>
 */
function lls_meta_query_stories_for_learn_target_lang( $code ) {
	if ( ! function_exists( 'lls_story_target_lang_codes' ) ) {
		return [];
	}
	$code = in_array( $code, lls_story_target_lang_codes(), true ) ? $code : 'en';
	if ( 'en' === $code ) {
		$query = [
			'relation' => 'OR',
			[
				'key'     => '_lls_target_lang',
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => '_lls_target_lang',
				'value'   => '',
				'compare' => '=',
			],
			[
				'key'   => '_lls_target_lang',
				'value' => 'en',
			],
		];
	} else {
		$query = [
			[
				'key'   => '_lls_target_lang',
				'value' => $code,
			],
		];
	}
	/**
	 * Filtro: meta_query per elenco storie per lingua da imparare (libreria).
	 *
	 * @param array  $query Meta query costruita.
	 * @param string $code  Codice lingua obiettivo.
	 */
	return apply_filters( 'lls_meta_query_stories_for_learn_target_lang', $query, $code );
}

/**
 * Combina filtri interfaccia e lingua da imparare per {@see WP_Query} (relation AND).
 *
 * @param string $interface_lang Codice it|pl|es.
 * @param string $learn_code     Codice en|pl|it|es.
 * @return array<string, mixed>
 */
function lls_meta_query_stories_for_library( $interface_lang, $learn_code ) {
	$i = lls_meta_query_fragment_as_clause( lls_meta_query_stories_for_interface_lang( $interface_lang ) );
	$t = lls_meta_query_fragment_as_clause( lls_meta_query_stories_for_learn_target_lang( $learn_code ) );
	if ( empty( $i ) && empty( $t ) ) {
		return [];
	}
	if ( empty( $i ) ) {
		return $t;
	}
	if ( empty( $t ) ) {
		return $i;
	}
	return [
		'relation' => 'AND',
		$i,
		$t,
	];
}

/**
 * HTML di un elemento elenco storia (stesso markup di [lls_profile_continue_stories]).
 *
 * @param WP_Post          $post         Post storia.
 * @param int              $words        Parole riassunto.
 * @param int              $completed    Frasi completate.
 * @param int              $total        Frasi totali.
 * @param string           $button_label Testo pulsante «Continue» (già tradotto).
 * @param array            $args         Opzioni: coin_gate (bool), enter_label (string).
 * @return string
 */
function lls_get_profile_story_list_item_html( WP_Post $post, $words, $completed, $total, $button_label, $args = [] ) {
	$words     = (int) $words;
	$completed = max( 0, (int) $completed );
	$total     = max( 0, (int) $total );
	$pct       = $total > 0 ? (int) round( 100 * $completed / $total ) : 0;
	$summary   = lls_get_story_summary_text( $post, $words );
	$url       = get_permalink( $post );
	$aria_pb   = sprintf(
		/* translators: 1: completed, 2: total */
		__( 'Progress: %1$d of %2$d sentences', 'language-learning-stories' ),
		$completed,
		$total
	);
	$args += [
		'coin_gate'   => true,
		'enter_label' => __( 'Enter the story', 'language-learning-stories' ),
	];
	$coin_gate = (bool) $args['coin_gate'] && function_exists( 'lls_user_can_access_story' );
	$user_id   = is_user_logged_in() ? get_current_user_id() : 0;
	$cost      = $coin_gate ? lls_get_story_coin_cost( $post->ID ) : 0;
	$reward    = $coin_gate ? lls_get_story_coin_reward( $post->ID ) : 0;
	$can_access = ! $coin_gate || ! function_exists( 'lls_user_can_access_story' ) || lls_user_can_access_story( $user_id, $post->ID );
	$balance   = ( $coin_gate && $user_id > 0 && function_exists( 'lls_get_user_coin_balance' ) ) ? lls_get_user_coin_balance( $user_id ) : 0;
	$can_afford = $cost <= 0 || $balance >= $cost;

	$cat_list = taxonomy_exists( 'lls_story_category' )
		? get_the_term_list( $post->ID, 'lls_story_category', '', ', ', '' )
		: '';
	$tag_list = taxonomy_exists( 'lls_story_tag' )
		? get_the_term_list( $post->ID, 'lls_story_tag', '', ', ', '' )
		: '';
	if ( is_wp_error( $cat_list ) || false === $cat_list ) {
		$cat_list = '';
	}
	if ( is_wp_error( $tag_list ) || false === $tag_list ) {
		$tag_list = '';
	}

	$title_inner = esc_html( get_the_title( $post ) );
	ob_start();
	?>
	<li class="lls-profile-continue__item<?php echo $coin_gate && $cost > 0 && ! $can_access ? ' lls-profile-continue__item--locked' : ''; ?>" data-lls-story-id="<?php echo esc_attr( (string) (int) $post->ID ); ?>">
		<h3 class="lls-story-title">
			<?php if ( $can_access ) : ?>
				<a href="<?php echo esc_url( $url ); ?>"><?php echo $title_inner; ?></a>
			<?php else : ?>
				<span class="lls-story-title__text"><?php echo $title_inner; ?></span>
			<?php endif; ?>
		</h3>
		<?php if ( $cat_list || $tag_list ) : ?>
			<div class="lls-profile-continue__tax">
				<?php if ( $cat_list ) : ?>
					<p class="lls-profile-continue__terms lls-profile-continue__terms--categories">
						<span class="lls-profile-continue__terms-label"><?php esc_html_e( 'Categories', 'language-learning-stories' ); ?></span>
						<span class="lls-profile-continue__terms-list"><?php echo wp_kses_post( $cat_list ); ?></span>
					</p>
				<?php endif; ?>
				<?php if ( $tag_list ) : ?>
					<p class="lls-profile-continue__terms lls-profile-continue__terms--tags">
						<span class="lls-profile-continue__terms-label"><?php esc_html_e( 'Tags', 'language-learning-stories' ); ?></span>
						<span class="lls-profile-continue__terms-list"><?php echo wp_kses_post( $tag_list ); ?></span>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( $summary !== '' ) : ?>
			<p class="lls-profile-continue__summary"><?php echo esc_html( $summary ); ?></p>
		<?php endif; ?>
		<?php if ( $coin_gate && ( $cost > 0 || $reward > 0 ) ) : ?>
			<ul class="lls-story-coin-meta" role="list">
				<?php if ( $cost > 0 ) : ?>
					<li class="lls-story-coin-meta__item lls-story-coin-meta__item--cost">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: coin cost */
								__( 'Cost: %d coins', 'language-learning-stories' ),
								$cost
							)
						);
						?>
					</li>
				<?php endif; ?>
				<?php if ( $reward > 0 ) : ?>
					<li class="lls-story-coin-meta__item lls-story-coin-meta__item--reward">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: coins earned on completion */
								__( 'Reward when you finish: %d coins', 'language-learning-stories' ),
								$reward
							)
						);
						?>
					</li>
				<?php endif; ?>
			</ul>
		<?php endif; ?>
		<div class="lls-profile-continue__progress-stack">
			<div class="lls-progress-bar-wrap" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $total ); ?>" aria-valuenow="<?php echo esc_attr( (string) $completed ); ?>" aria-label="<?php echo esc_attr( $aria_pb ); ?>">
				<div class="lls-progress-bar" style="width: <?php echo esc_attr( (string) $pct ); ?>%;"></div>
			</div>
			<div class="lls-progress-row">
				<span class="lls-progress-counter"><?php echo esc_html( sprintf( /* translators: 1: completed, 2: total */ __( '%1$d / %2$d sentences', 'language-learning-stories' ), $completed, $total ) ); ?></span>
			</div>
		</div>
		<p class="lls-continua-wrap">
			<?php if ( $can_access ) : ?>
				<?php
				$cta = ( $completed > 0 ) ? $button_label : (string) $args['enter_label'];
				?>
				<a class="lls-btn lls-btn-continua" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $cta ); ?></a>
			<?php elseif ( $coin_gate && $cost > 0 ) : ?>
				<?php if ( ! is_user_logged_in() ) : ?>
					<a class="lls-btn lls-btn-continua lls-btn--unlock-login" href="<?php echo esc_url( wp_login_url( function_exists( 'lls_get_frontend_request_url' ) ? lls_get_frontend_request_url() : home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log in to unlock', 'language-learning-stories' ); ?></a>
				<?php elseif ( ! $can_afford ) : ?>
					<button type="button" class="lls-btn lls-btn-continua" disabled>
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
					<button type="button" class="lls-btn lls-btn-continua lls-unlock-story-btn" data-lls-unlock-story="<?php echo esc_attr( (string) (int) $post->ID ); ?>">
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
		</p>
	</li>
	<?php
	return (string) ob_get_clean();
}

/**
 * Shortcode: saluto area personale.
 *
 * Uso: [lls_profile_greeting]
 *
 * @param string[] $atts Attributi (logout="1" per link esci).
 * @return string
 */
function lls_shortcode_profile_greeting( $atts ) {
	$atts = shortcode_atts(
		[
			'logout' => '0',
		],
		$atts,
		'lls_profile_greeting'
	);

	if ( ! is_user_logged_in() ) {
		$login_url = wp_login_url( get_permalink() ?: home_url( '/' ) );
		$inner     = '<div class="lls-profile-greeting lls-profile-greeting--guest">' .
			sprintf(
				'<p>%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'Please log in to view your account area.', 'language-learning-stories' ),
				esc_url( $login_url ),
				esc_html__( 'Log in', 'language-learning-stories' )
			) . '</div>';
		return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
	}

	$user = wp_get_current_user();
	$name = $user->display_name ? $user->display_name : $user->user_login;
	$html = '<div class="lls-profile-greeting">';
	$html .= '<p class="lls-profile-greeting__hello">' . sprintf(
		/* translators: %s: display name */
		esc_html__( 'Hello, %s.', 'language-learning-stories' ),
		esc_html( $name )
	) . '</p>';

	if ( '1' === $atts['logout'] || 'true' === $atts['logout'] ) {
		$html .= '<p class="lls-profile-greeting__logout"><a href="' . esc_url( wp_logout_url( get_permalink() ?: home_url( '/' ) ) ) . '">' .
			esc_html__( 'Log out', 'language-learning-stories' ) . '</a></p>';
	}
	$html .= '</div>';
	return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $html, 'block' ) : $html;
}

/**
 * Shortcode: elenco storie in corso (titolo, trama, barra progresso).
 *
 * Uso: [lls_profile_continue_stories limit="10"]
 *
 * @param string[]|string $atts Attributi shortcode.
 * @return string
 */
function lls_shortcode_profile_continue_stories( $atts ) {
	$atts = shortcode_atts(
		[
			'limit' => '10',
			'words' => '40',
		],
		$atts,
		'lls_profile_continue_stories'
	);

	$limit = max( 1, min( 50, (int) $atts['limit'] ) );
	$words = max( 5, min( 80, (int) $atts['words'] ) );

	if ( ! is_user_logged_in() ) {
		$inner = '<div class="lls-profile-continue lls-profile-continue--guest"><p>' .
			esc_html__( 'Log in to see your stories in progress.', 'language-learning-stories' ) .
			'</p></div>';
		return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
	}

	$user_id = get_current_user_id();
	$candidates = lls_collect_user_progress_story_ids( $user_id );
	$ordered    = lls_order_story_ids_by_recent( $user_id, $candidates );

	$items = [];
	foreach ( $ordered as $story_id ) {
		if ( count( $items ) >= $limit ) {
			break;
		}
		$post = get_post( $story_id );
		if ( ! $post || 'lls_story' !== $post->post_type || 'publish' !== $post->post_status ) {
			continue;
		}
		$sentences = get_post_meta( $story_id, '_lls_sentences', true );
		$total     = is_array( $sentences ) ? count( $sentences ) : 0;
		if ( $total < 1 ) {
			continue;
		}
		$saved     = get_user_meta( $user_id, '_lls_progress_' . $story_id, true );
		$completed = ( is_array( $saved ) && isset( $saved['completed'] ) ) ? (int) $saved['completed'] : 0;
		if ( $completed >= $total ) {
			continue;
		}
		$items[] = [
			'post'      => $post,
			'completed' => $completed,
			'total'     => $total,
		];
	}

	if ( empty( $items ) ) {
		$inner = '<div class="lls-profile-continue lls-profile-continue--empty"><p>' .
			esc_html__( 'You have no stories in progress. Open a story and start completing sentences — it will appear here.', 'language-learning-stories' ) .
			'</p></div>';
		return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
	}

	$btn_continue = __( 'Continue story', 'language-learning-stories' );
	ob_start();
	echo '<div class="lls-profile-continue"><ul class="lls-profile-continue__list">';
	foreach ( $items as $row ) {
		echo lls_get_profile_story_list_item_html(
			$row['post'],
			$words,
			$row['completed'],
			$row['total'],
			$btn_continue
		);
	}
	echo '</ul></div>';
	$out = (string) ob_get_clean();
	return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $out, 'block' ) : $out;
}

/**
 * Shortcode: libreria — storie per lingua interfaccia (_lls_known_lang) e lingua da imparare (_lls_target_lang).
 *
 * Ospiti: interfaccia italiano, obiettivo inglese. Utenti loggati: profilo e {@see lls_get_user_learn_target_lang()}.
 *
 * Uso: [lls_library_stories limit="50" words="40"]
 *
 * @param string[]|string $atts Attributi shortcode.
 * @return string
 */
function lls_shortcode_library_stories( $atts ) {
	$atts = shortcode_atts(
		[
			'limit'      => '50',
			'words'      => '40',
			'lang'       => '',
			'learn_lang' => '',
			'show_lang'  => '1',
		],
		is_array( $atts ) ? $atts : [],
		'lls_library_stories'
	);

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

	$meta_query = lls_meta_query_stories_for_library( $lang, $learn );
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

	$q        = new WP_Query( $query_args );
	$user_id  = is_user_logged_in() ? get_current_user_id() : 0;
	$btn      = __( 'Continue story', 'language-learning-stories' );
	$items_html = '';

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
			$items_html .= lls_get_profile_story_list_item_html( $post, $words, $completed, $total, $btn );
		}
		wp_reset_postdata();
	}

	if ( $items_html === '' ) {
		$inner = '<div class="lls-profile-continue lls-library-stories lls-library-stories--empty"><p>' .
			esc_html__( 'No published stories match these filters yet (interface language and language to learn).', 'language-learning-stories' ) .
			'</p></div>';
		return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
	}

	$intro = '';
	if ( '1' === $atts['show_lang'] || 'true' === $atts['show_lang'] ) {
		$labels_iface = function_exists( 'lls_get_known_lang_choice_labels' ) ? lls_get_known_lang_choice_labels() : [];
		$labels_learn = function_exists( 'lls_get_story_target_lang_choice_labels' ) ? lls_get_story_target_lang_choice_labels() : [];
		$iface_label  = isset( $labels_iface[ $lang ] ) ? $labels_iface[ $lang ] : $lang;
		$learn_label  = isset( $labels_learn[ $learn ] ) ? $labels_learn[ $learn ] : $learn;
		$intro        = '<p class="lls-library-stories__lang-note">' . sprintf(
			/* translators: 1: interface language name, 2: target / language to learn name */
			esc_html__( 'Stories — interface: %1$s · language to learn: %2$s', 'language-learning-stories' ),
			esc_html( $iface_label ),
			esc_html( $learn_label )
		) . '</p>';
	}

	$out = '<div class="lls-profile-continue lls-library-stories">' . $intro . '<ul class="lls-profile-continue__list">' . $items_html . '</ul></div>';
	return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $out, 'block' ) : $out;
}

/**
 * Shortcode: scegli la lingua che vuoi imparare (salvata in user meta; filtra la libreria).
 *
 * Uso: [lls_profile_learn_language]
 *
 * @param string[]|string $atts Attributi shortcode.
 * @return string
 */
function lls_shortcode_profile_learn_language( $atts ) {
	$atts = shortcode_atts( [], is_array( $atts ) ? $atts : [], 'lls_profile_learn_language' );

	if ( ! is_user_logged_in() ) {
		$login_url = wp_login_url( get_permalink() ?: home_url( '/' ) );
		$inner     = '<div class="lls-profile-learn-lang lls-profile-learn-lang--guest"><p class="lls-profile-learn-lang__text">' .
			esc_html__( 'Log in to save your preferred language to learn. Until then, the story library uses Italian as the interface language and English as the language to learn.', 'language-learning-stories' ) .
			'</p><p class="lls-profile-learn-lang__actions"><a class="lls-btn lls-btn--secondary" href="' . esc_url( $login_url ) . '">' .
			esc_html__( 'Log in', 'language-learning-stories' ) . '</a></p></div>';
		return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
	}

	$user       = wp_get_current_user();
	$current    = function_exists( 'lls_get_user_learn_target_lang' ) ? lls_get_user_learn_target_lang( $user->ID ) : 'en';
	$labels     = function_exists( 'lls_get_story_target_lang_choice_labels' ) ? lls_get_story_target_lang_choice_labels() : [];
	$label_cur  = isset( $labels[ $current ] ) ? $labels[ $current ] : $current;
	$form_base  = get_permalink() ?: home_url( '/' );
	$form_base  = remove_query_arg( [ 'lls_edit_learn_lang', 'lls_learn_lang' ], $form_base );
	$form_action = admin_url( 'admin-post.php' );

	wp_enqueue_script( 'lls-profile-learn-lang' );
	$notice      = '';
	if ( isset( $_GET['lls_learn_lang'] ) ) {
		$flag = sanitize_text_field( wp_unslash( (string) $_GET['lls_learn_lang'] ) );
		if ( 'ok' === $flag ) {
			$notice = '<p class="lls-profile-learn-lang__notice lls-profile-learn-lang__notice--ok" role="status">' .
				esc_html__( 'Your preference has been saved.', 'language-learning-stories' ) . '</p>';
		} elseif ( 'err_nonce' === $flag ) {
			$notice = '<p class="lls-profile-learn-lang__notice lls-profile-learn-lang__notice--err" role="alert">' .
				esc_html__( 'Security check failed. Please try again.', 'language-learning-stories' ) . '</p>';
		}
	}

	ob_start();
	?>
	<div class="lls-profile-learn-lang" data-lls-profile-learn-lang>
		<?php echo wp_kses_post( $notice ); ?>
		<div class="lls-profile-learn-lang__view">
			<p class="lls-profile-learn-lang__line">
				<span class="lls-profile-learn-lang__label"><?php esc_html_e( 'Language I want to learn:', 'language-learning-stories' ); ?></span>
				<strong class="lls-profile-learn-lang__value"><?php echo esc_html( $label_cur ); ?></strong>
				<button type="button" class="lls-profile-learn-lang__btn-edit"><?php esc_html_e( '(Edit)', 'language-learning-stories' ); ?></button>
			</p>
		</div>
		<div class="lls-profile-learn-lang__edit" hidden>
			<form class="lls-profile-learn-lang__form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<input type="hidden" name="action" value="lls_save_user_learn_lang" />
				<?php wp_nonce_field( 'lls_save_user_learn_lang', 'lls_learn_lang_nonce' ); ?>
				<input type="hidden" name="_wp_http_referer" value="<?php echo esc_url( $form_base ); ?>" />
				<p class="lls-profile-learn-lang__field">
					<label for="lls_user_learn_target_lang" class="lls-profile-learn-lang__field-label"><?php esc_html_e( 'Language I want to learn', 'language-learning-stories' ); ?></label>
					<select id="lls_user_learn_target_lang" name="lls_user_learn_target_lang" class="lls-profile-account__input lls-profile-account__select" required>
						<?php
						$codes = function_exists( 'lls_story_target_lang_codes' ) ? lls_story_target_lang_codes() : [ 'en', 'pl', 'it', 'es' ];
						foreach ( $codes as $code ) {
							printf(
								'<option value="%1$s"%3$s>%2$s</option>',
								esc_attr( $code ),
								esc_html( isset( $labels[ $code ] ) ? $labels[ $code ] : $code ),
								selected( $current, $code, false )
							);
						}
						?>
					</select>
				</p>
				<p class="lls-profile-learn-lang__form-actions">
					<button type="submit" class="lls-btn"><?php esc_html_e( 'Save', 'language-learning-stories' ); ?></button>
					<button type="button" class="lls-profile-learn-lang__cancel lls-profile-learn-lang__btn-cancel"><?php esc_html_e( 'Cancel', 'language-learning-stories' ); ?></button>
				</p>
			</form>
		</div>
	</div>
	<?php
	$inner = (string) ob_get_clean();
	return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
}

/**
 * Aggiorna user_login (e user_nicename) in tabella users: wp_update_user non lo persiste in aggiornamento.
 *
 * @param int    $user_id       ID utente.
 * @param string $new_login     Login desiderato (già passato da sanitize_user).
 * @param string $current_login Login attuale.
 * @return true|WP_Error
 */
function lls_profile_maybe_update_user_login( $user_id, $new_login, $current_login ) {
	global $wpdb;

	$user_id        = (int) $user_id;
	$current_login  = (string) $current_login;
	$new_login      = (string) $new_login;
	$new_login_trim = trim( $new_login );

	if ( $new_login_trim === $current_login ) {
		return true;
	}

	if ( '' === $new_login_trim ) {
		return new WP_Error( 'empty_user_login', __( 'Username cannot be empty.', 'language-learning-stories' ) );
	}

	if ( mb_strlen( $new_login_trim ) > 60 ) {
		return new WP_Error( 'user_login_too_long', __( 'Username is too long.', 'language-learning-stories' ) );
	}

	$existing = username_exists( $new_login_trim );
	if ( $existing && (int) $existing !== $user_id ) {
		return new WP_Error( 'existing_user_login', __( 'This username is already taken.', 'language-learning-stories' ) );
	}

	$illegal_logins = (array) apply_filters( 'illegal_user_logins', [] );
	if ( in_array( strtolower( $new_login_trim ), array_map( 'strtolower', $illegal_logins ), true ) ) {
		return new WP_Error( 'invalid_username', __( 'This username is not allowed.', 'language-learning-stories' ) );
	}

	$user_nicename = mb_substr( $new_login_trim, 0, 50 );
	$user_nicename = sanitize_title( $user_nicename );
	if ( '' === $user_nicename ) {
		return new WP_Error( 'invalid_username', __( 'Invalid username.', 'language-learning-stories' ) );
	}

	$user_nicename_check = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->users} WHERE user_nicename = %s AND ID != %d LIMIT 1",
			$user_nicename,
			$user_id
		)
	);
	if ( $user_nicename_check ) {
		$suffix = 2;
		while ( $user_nicename_check ) {
			$base_length       = 49 - mb_strlen( (string) $suffix );
			$alt_user_nicename = mb_substr( $user_nicename, 0, $base_length ) . '-' . $suffix;
			$user_nicename_check = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->users} WHERE user_nicename = %s AND ID != %d LIMIT 1",
					$alt_user_nicename,
					$user_id
				)
			);
			if ( ! $user_nicename_check ) {
				$user_nicename = $alt_user_nicename;
				break;
			}
			++$suffix;
		}
	}

	wp_cache_delete( $current_login, 'userlogins' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$updated = $wpdb->update(
		$wpdb->users,
		[
			'user_login'    => $new_login_trim,
			'user_nicename' => $user_nicename,
		],
		[ 'ID' => $user_id ],
		[ '%s', '%s' ],
		[ '%d' ]
	);

	if ( false === $updated ) {
		return new WP_Error( 'db_error', __( 'Could not update username.', 'language-learning-stories' ) );
	}

	clean_user_cache( $user_id );
	return true;
}

/**
 * Messaggio dopo salvataggio modulo account (parametro URL lls_account).
 *
 * @param string $code Codice stato.
 * @return string HTML avviso o stringa vuota.
 */
function lls_profile_account_notice_html( $code ) {
	$map_ok = [
		'ok' => __( 'Profile updated.', 'language-learning-stories' ),
	];
	$map_err = [
		'err_nonce'         => __( 'Your session expired or the request was invalid. Reload the page and try again.', 'language-learning-stories' ),
		'err_email'         => __( 'Please enter a valid email address.', 'language-learning-stories' ),
		'err_email_taken'   => __( 'This email is already in use by another account.', 'language-learning-stories' ),
		'err_pass_match'    => __( 'The two passwords do not match.', 'language-learning-stories' ),
		'err_pass_short'    => __( 'The new password is too short.', 'language-learning-stories' ),
		'err_login_taken'   => __( 'This login username is already taken.', 'language-learning-stories' ),
		'err_login_invalid' => __( 'The login username is invalid or not allowed.', 'language-learning-stories' ),
		'err_update'        => __( 'Could not save your changes. Please try again.', 'language-learning-stories' ),
	];
	if ( isset( $map_ok[ $code ] ) ) {
		return '<p class="lls-profile-account__notice lls-profile-account__notice--ok" role="status">' . esc_html( $map_ok[ $code ] ) . '</p>';
	}
	if ( isset( $map_err[ $code ] ) ) {
		return '<p class="lls-profile-account__notice lls-profile-account__notice--err" role="alert">' . esc_html( $map_err[ $code ] ) . '</p>';
	}
	return '';
}

/**
 * Chiave user meta: copia in chiaro della password (solo se il filtro è attivo).
 *
 * @return string
 */
function lls_profile_plain_password_meta_key() {
	return '_lls_profile_plain_password';
}

/**
 * Salva la password in chiaro per mostrarla nello shortcode profilo (non è recuperabile dall’hash WP).
 *
 * Filtro `lls_store_plain_password_for_profile_display` (default true): false disattiva salvataggio e lettura in vista.
 *
 * @param string  $password Password in chiaro.
 * @param int     $user_id  ID utente.
 * @param WP_User $user     Utente.
 */
function lls_profile_on_wp_set_password( $password, $user_id, $user ) {
	if ( ! apply_filters( 'lls_store_plain_password_for_profile_display', true ) ) {
		return;
	}
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return;
	}
	$key = lls_profile_plain_password_meta_key();
	if ( '' === (string) $password ) {
		delete_user_meta( $user_id, $key );
		return;
	}
	update_user_meta( $user_id, $key, $password );
}

add_action( 'wp_set_password', 'lls_profile_on_wp_set_password', 10, 3 );

/**
 * Shortcode: modulo dati account (nome accesso, nome visualizzato, email, nuova password).
 *
 * Uso: [lls_profile_account]
 *
 * @param string[]|string $atts Attributi shortcode.
 * @return string
 */
function lls_shortcode_profile_account( $atts ) {
	$atts = shortcode_atts( [], is_array( $atts ) ? $atts : [], 'lls_profile_account' );

	if ( ! is_user_logged_in() ) {
		$login_url = wp_login_url( get_permalink() ?: home_url( '/' ) );
		$inner     = '<div class="lls-profile-account lls-profile-account--guest">' .
			sprintf(
				'<p>%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'Please log in to edit your account details.', 'language-learning-stories' ),
				esc_url( $login_url ),
				esc_html__( 'Log in', 'language-learning-stories' )
			) . '</div>';
		return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
	}

	$user = wp_get_current_user();
	$notice = '';
	if ( isset( $_GET['lls_account'] ) ) {
		$notice = lls_profile_account_notice_html( sanitize_text_field( wp_unslash( (string) $_GET['lls_account'] ) ) );
	}

	$account_status = isset( $_GET['lls_account'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['lls_account'] ) ) : '';
	$editing_class  = ( $account_status !== '' && 'ok' !== $account_status ) ? ' lls-profile-account--editing' : '';

	$form_action = admin_url( 'admin-post.php' );
	$referer     = get_permalink() ?: home_url( '/' );

	wp_enqueue_script( 'lls-profile-account' );

	$user_known_lang = function_exists( 'lls_get_user_known_lang' ) ? lls_get_user_known_lang( $user->ID ) : 'it';
	$lang_labels     = function_exists( 'lls_get_known_lang_choice_labels' ) ? lls_get_known_lang_choice_labels() : [];

	ob_start();
	?>
	<div class="lls-profile-account<?php echo esc_attr( $editing_class ); ?>" data-lls-profile-account>
		<?php
		if ( $notice !== '' ) {
			echo wp_kses_post( $notice );
		}
		?>
		<div class="lls-profile-account__view">
			<dl class="lls-profile-account__readonly-block lls-profile-account__view-dl">
				<dt><?php esc_html_e( 'Username (login)', 'language-learning-stories' ); ?></dt>
				<dd><code class="lls-profile-account__login"><?php echo esc_html( $user->user_login ); ?></code></dd>
				<dt><?php esc_html_e( 'Display name', 'language-learning-stories' ); ?></dt>
				<dd><?php echo esc_html( $user->display_name ? $user->display_name : '—' ); ?></dd>
				<dt><?php esc_html_e( 'Email', 'language-learning-stories' ); ?></dt>
				<dd><?php echo esc_html( $user->user_email ); ?></dd>
				<dt><?php esc_html_e( 'Language you know', 'language-learning-stories' ); ?></dt>
				<dd><?php echo esc_html( isset( $lang_labels[ $user_known_lang ] ) ? $lang_labels[ $user_known_lang ] : $user_known_lang ); ?></dd>
				<dt><?php esc_html_e( 'Password', 'language-learning-stories' ); ?></dt>
				<dd class="lls-profile-account__password-view">
					<?php
					$show_plain = apply_filters( 'lls_store_plain_password_for_profile_display', true );
					$plain_pw   = $show_plain ? (string) get_user_meta( $user->ID, lls_profile_plain_password_meta_key(), true ) : '';
					if ( $plain_pw !== '' ) {
						echo '<code class="lls-profile-account__password-plain">' . esc_html( $plain_pw ) . '</code>';
					} elseif ( $show_plain ) {
						echo '<span class="lls-profile-account__password-missing">' . esc_html__( '— (visible after you next change your password)', 'language-learning-stories' ) . '</span>';
					} else {
						echo '<span class="lls-profile-account__password-missing">' . esc_html__( '—', 'language-learning-stories' ) . '</span>';
					}
					?>
				</dd>
			</dl>
			<p class="lls-profile-account__view-actions">
				<button type="button" class="lls-btn lls-profile-account__btn-edit"><?php esc_html_e( 'Edit', 'language-learning-stories' ); ?></button>
			</p>
		</div>
		<div class="lls-profile-account__edit">
			<form class="lls-profile-account__form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<input type="hidden" name="action" value="lls_update_user_profile" />
				<?php wp_nonce_field( 'lls_update_user_profile', 'lls_profile_nonce' ); ?>
				<input type="hidden" name="_wp_http_referer" value="<?php echo esc_url( $referer ); ?>" />

				<p class="lls-profile-account__field">
					<label for="lls_user_login"><?php esc_html_e( 'Username (login)', 'language-learning-stories' ); ?></label>
					<input type="text" id="lls_user_login" name="lls_user_login" value="<?php echo esc_attr( $user->user_login ); ?>" autocomplete="username" required class="lls-profile-account__input" />
				</p>
				<p class="lls-profile-account__field">
					<label for="lls_display_name"><?php esc_html_e( 'Display name', 'language-learning-stories' ); ?></label>
					<input type="text" id="lls_display_name" name="lls_display_name" value="<?php echo esc_attr( $user->display_name ); ?>" autocomplete="name" class="lls-profile-account__input" />
				</p>
				<p class="lls-profile-account__field">
					<label for="lls_user_email"><?php esc_html_e( 'Email', 'language-learning-stories' ); ?></label>
					<input type="email" id="lls_user_email" name="lls_user_email" value="<?php echo esc_attr( $user->user_email ); ?>" autocomplete="email" required class="lls-profile-account__input" />
				</p>
				<p class="lls-profile-account__field">
					<label for="lls_user_known_lang"><?php esc_html_e( 'Language you know', 'language-learning-stories' ); ?></label>
					<select id="lls_user_known_lang" name="lls_user_known_lang" class="lls-profile-account__input lls-profile-account__select" required>
						<?php
						$lang_codes = function_exists( 'lls_known_lang_codes' ) ? lls_known_lang_codes() : [ 'it', 'pl', 'es' ];
						foreach ( $lang_codes as $code ) {
							printf(
								'<option value="%1$s"%3$s>%2$s</option>',
								esc_attr( $code ),
								esc_html( isset( $lang_labels[ $code ] ) ? $lang_labels[ $code ] : $code ),
								selected( $user_known_lang, $code, false )
							);
						}
						?>
					</select>
				</p>
				<fieldset class="lls-profile-account__fieldset">
					<legend><?php esc_html_e( 'Password', 'language-learning-stories' ); ?></legend>
					<p class="lls-profile-account__password-hint lls-profile-account__password-hint--top"><?php esc_html_e( 'Fields show plain text. Leave blank to keep your current password.', 'language-learning-stories' ); ?></p>
					<p class="lls-profile-account__field">
						<label for="lls_pass1"><?php esc_html_e( 'New password', 'language-learning-stories' ); ?></label>
						<input type="text" id="lls_pass1" name="lls_pass1" value="" autocomplete="off" class="lls-profile-account__input" />
					</p>
					<p class="lls-profile-account__field">
						<label for="lls_pass2"><?php esc_html_e( 'Repeat new password', 'language-learning-stories' ); ?></label>
						<input type="text" id="lls_pass2" name="lls_pass2" value="" autocomplete="off" class="lls-profile-account__input" />
					</p>
				</fieldset>
				<p class="lls-profile-account__actions lls-profile-account__actions--edit">
					<button type="submit" class="lls-btn"><?php esc_html_e( 'Save changes', 'language-learning-stories' ); ?></button>
					<button type="button" class="lls-btn lls-profile-account__btn-cancel"><?php esc_html_e( 'Cancel', 'language-learning-stories' ); ?></button>
				</p>
			</form>
		</div>
	</div>
	<?php
	$inner = (string) ob_get_clean();
	return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
}

/**
 * Shortcode [lls_total_phrases] / [total_phrases]: «Phrases: N», stesso markup di [coin]; click → /phrases (link 1/0, href).
 *
 * @param string[]|string $atts    Attributi: link, href.
 * @param string          $content Non usato.
 * @param string          $tag     Nome shortcode.
 * @return string
 */
function lls_shortcode_total_phrases( $atts, $content = '', $tag = '' ) {
	if ( ! function_exists( 'lls_wrap_shortcode_html' ) || ! function_exists( 'lls_get_user_total_completed_sentences' ) ) {
		return '';
	}

	$atts = shortcode_atts(
		[
			'link' => '1',
			'href' => '/phrases',
		],
		is_array( $atts ) ? $atts : [],
		$tag !== '' ? $tag : 'total_phrases'
	);

	$user_id = get_current_user_id();
	$total   = $user_id ? lls_get_user_total_completed_sentences( $user_id ) : 0;
	/**
	 * Totale frasi completate mostrato dallo shortcode [lls_total_phrases].
	 *
	 * @param int $total   Conteggio.
	 * @param int $user_id ID utente (0 se ospite).
	 */
	$total = (int) apply_filters( 'lls_total_phrases_shortcode_count', $total, $user_id );

	$label = __( 'Phrases:', 'language-learning-stories' );
	$inner = '<span class="lls-coin__label">' . esc_html( $label ) . '</span>'
		. '<span class="lls-coin__value" data-lls-total-phrases>' . esc_html( (string) $total ) . '</span>';

	$use_link = (string) $atts['link'] === '1' || strtolower( (string) $atts['link'] ) === 'true' || strtolower( (string) $atts['link'] ) === 'yes';
	$href_raw = trim( (string) $atts['href'] );
	if ( $href_raw === '' ) {
		$href_raw = '/phrases';
	}
	$page_url = ( strpos( $href_raw, 'http://' ) === 0 || strpos( $href_raw, 'https://' ) === 0 )
		? $href_raw
		: home_url( '/' . ltrim( $href_raw, '/' ) );
	/**
	 * URL della pagina frasi aperta dal click su [lls_total_phrases].
	 *
	 * @param string $page_url URL assoluto.
	 * @param int    $user_id  ID utente (0 se ospite).
	 */
	$page_url = (string) apply_filters( 'lls_total_phrases_shortcode_url', $page_url, $user_id );

	if ( $use_link ) {
		$aria = sprintf(
			/* translators: 1: "Phrases:" label, 2: count */
			__( '%1$s %2$s — view phrases page', 'language-learning-stories' ),
			$label,
			(string) $total
		);
		$html = '<a class="lls-coin lls-coin--link" href="' . esc_url( $page_url ) . '" aria-label="' . esc_attr( $aria ) . '">'
			. '<span class="lls-coin__live" role="status" aria-live="polite" aria-atomic="true">'
			. $inner
			. '</span>'
			. '</a>';
	} else {
		$html = '<span class="lls-coin" role="status" aria-live="polite" aria-atomic="true">'
			. $inner
			. '</span>';
	}

	return lls_wrap_shortcode_html( $html, 'contents' );
}

/**
 * Shortcode: intestazione con totale frasi (grande) + elenco (inglese + italiano, TTS, storia, tassonomie).
 *
 * Uso: [lls_completed_phrases] [lls_completed_phrases limit="100" excerpt="140"]
 *
 * @param string[]|string $atts Attributi.
 * @return string
 */
function lls_shortcode_completed_phrases( $atts ) {
	if ( ! function_exists( 'lls_wrap_shortcode_html' ) ) {
		return '';
	}
	if ( ! is_user_logged_in() ) {
		$inner = '<p class="lls-completed-phrases--guest">' .
			esc_html__( 'Log in to see the phrases you have completed.', 'language-learning-stories' ) .
			'</p>';
		return lls_wrap_shortcode_html( $inner, 'block' );
	}

	$atts = shortcode_atts(
		[
			'limit'   => '200',
			'excerpt' => '0',
		],
		is_array( $atts ) ? $atts : [],
		'lls_completed_phrases'
	);

	$limit   = max( 1, min( 500, (int) $atts['limit'] ) );
	$excerpt = max( 0, min( 400, (int) $atts['excerpt'] ) );

	$user_id = get_current_user_id();
	if ( function_exists( 'lls_backfill_completed_phrases_log_if_needed' ) ) {
		lls_backfill_completed_phrases_log_if_needed( $user_id );
	}

	$log = get_user_meta( $user_id, lls_completed_phrases_log_meta_key(), true );
	if ( ! is_array( $log ) ) {
		$log = [];
	}

	usort(
		$log,
		static function ( $a, $b ) {
			$ta = isset( $a['ts'] ) ? (int) $a['ts'] : 0;
			$tb = isset( $b['ts'] ) ? (int) $b['ts'] : 0;
			if ( $tb !== $ta ) {
				return $tb <=> $ta;
			}
			$sa = isset( $a['story_id'], $a['sentence_index'] ) ? ( (int) $a['story_id'] * 10000 + (int) $a['sentence_index'] ) : 0;
			$sb = isset( $b['story_id'], $b['sentence_index'] ) ? ( (int) $b['story_id'] * 10000 + (int) $b['sentence_index'] ) : 0;
			return $sb <=> $sa;
		}
	);

	$total_phrases = count( $log );
	if ( function_exists( 'lls_get_user_total_completed_sentences' ) ) {
		$from_progress = (int) lls_get_user_total_completed_sentences( $user_id );
		$from_progress = (int) apply_filters( 'lls_total_phrases_shortcode_count', $from_progress, $user_id );
		$total_phrases = max( $total_phrases, $from_progress );
	}

	$log = array_slice( $log, 0, $limit );

	if ( count( $log ) === 0 ) {
		$inner = '<p class="lls-completed-phrases--empty">' .
			esc_html__( 'No completed phrases yet. Finish sentences in a story — they will appear here.', 'language-learning-stories' ) .
			'</p>';
		return lls_wrap_shortcode_html( $inner, 'block' );
	}

	$icon_svg   = '<svg class="lls-completed-phrases__hear-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor"><path d="M3 10v4h4l5 5V5L7 10H3zm13.5 2c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>';

	$shown_count = count( $log );

	ob_start();
	?>
	<div class="lls-completed-phrases">
		<header
			class="lls-completed-phrases__header"
			aria-label="<?php
			echo esc_attr(
				sprintf(
					/* translators: %d: total number of completed phrases */
					_n(
						'%d phrase completed in total',
						'%d phrases completed in total',
						$total_phrases,
						'language-learning-stories'
					),
					$total_phrases
				)
			);
			?>
		">
			<p class="lls-completed-phrases__total-line">
				<span class="lls-completed-phrases__total-num" aria-hidden="true"><?php echo esc_html( (string) $total_phrases ); ?></span>
				<span class="lls-completed-phrases__total-label" aria-hidden="true">
					<?php
					echo esc_html(
						_n(
							'phrase completed in total',
							'phrases completed in total',
							$total_phrases,
							'language-learning-stories'
						)
					);
					?>
				</span>
			</p>
			<?php if ( $shown_count < $total_phrases ) : ?>
				<p class="lls-completed-phrases__header-note">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: how many phrases are listed below (limit) */
							_n(
								'Below: your latest %d phrase.',
								'Below: your latest %d phrases.',
								$shown_count,
								'language-learning-stories'
							),
							$shown_count
						)
					);
					?>
				</p>
			<?php endif; ?>
		</header>
		<ul class="lls-completed-phrases__list" role="list" aria-label="<?php echo esc_attr__( 'Completed phrases', 'language-learning-stories' ); ?>">
			<?php
			foreach ( $log as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$story_id = isset( $row['story_id'] ) ? (int) $row['story_id'] : 0;
				$tx      = lls_completed_phrase_resolve_texts( $row );
				$text_it = $tx['text_it'];
				$text_en = $tx['text_en'];
				if ( $excerpt > 0 ) {
					if ( $text_en !== '' ) {
						$text_en = wp_html_excerpt( $text_en, $excerpt, '…' );
					}
					if ( $text_it !== '' ) {
						$text_it = wp_html_excerpt( $text_it, $excerpt, '…' );
					}
				}
				$main_line = $text_en !== '' ? $text_en : $text_it;
				$sub_line  = ( $text_it !== '' && $text_it !== $text_en && $text_en !== '' ) ? $text_it : '';
				$story_title = '';
				$story_url   = '';
				if ( $story_id > 0 && get_post_type( $story_id ) === 'lls_story' && 'publish' === get_post_status( $story_id ) ) {
					$story_title = get_the_title( $story_id );
					$story_url   = get_permalink( $story_id );
				} elseif ( $story_id > 0 ) {
					$story_title = sprintf(
						/* translators: %d: story ID */
						__( 'Story #%d (unavailable)', 'language-learning-stories' ),
						$story_id
					);
				}
				$cat_list = '';
				$tag_list = '';
				if ( $story_id > 0 ) {
					$cat_list = taxonomy_exists( 'lls_story_category' )
						? get_the_term_list( $story_id, 'lls_story_category', '', ', ', '' )
						: '';
					$tag_list = taxonomy_exists( 'lls_story_tag' )
						? get_the_term_list( $story_id, 'lls_story_tag', '', ', ', '' )
						: '';
					if ( is_wp_error( $cat_list ) || false === $cat_list ) {
						$cat_list = '';
					}
					if ( is_wp_error( $tag_list ) || false === $tag_list ) {
						$tag_list = '';
					}
				}
				$speak_en = $text_en !== '' ? $text_en : '';
				$speak_locale = 'en-US';
				if ( $story_id > 0 && function_exists( 'lls_get_story_target_lang' ) && function_exists( 'lls_story_target_lang_speech_locale' ) ) {
					$speak_locale = lls_story_target_lang_speech_locale( lls_get_story_target_lang( $story_id ) );
				}
				$hear_if = function_exists( 'lls_get_user_known_lang' ) ? lls_get_user_known_lang() : 'it';
				$hear_tn = ( $story_id > 0 && function_exists( 'lls_get_target_lang_name_for_ui' ) && function_exists( 'lls_get_story_target_lang' ) )
					? lls_get_target_lang_name_for_ui( lls_get_story_target_lang( $story_id ), $hear_if )
					: '';
				$hear_label = esc_attr(
					$hear_tn !== ''
						? sprintf(
							/* translators: %s: target language name (e.g. inglese) */
							__( 'Ascolta la traduzione in %s', 'language-learning-stories' ),
							$hear_tn
						)
						: __( 'Listen to the phrase', 'language-learning-stories' )
				);
				?>
				<li class="lls-completed-phrases__item">
					<div class="lls-completed-phrases__phrase-block">
						<div class="lls-completed-phrases__en-row">
							<p class="lls-completed-phrases__en"><?php echo $main_line !== '' ? esc_html( $main_line ) : '—'; ?></p>
							<?php if ( $speak_en !== '' ) : ?>
								<button type="button" class="lls-completed-phrases__hear" data-lls-speak-en="<?php echo esc_attr( $speak_en ); ?>" data-lls-speak-locale="<?php echo esc_attr( $speak_locale ); ?>" aria-label="<?php echo esc_attr( $hear_label ); ?>">
									<?php echo $icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</button>
							<?php endif; ?>
						</div>
						<?php if ( $sub_line !== '' ) : ?>
							<p class="lls-completed-phrases__it"><?php echo esc_html( $sub_line ); ?></p>
						<?php endif; ?>
					</div>
					<div class="lls-completed-phrases__meta">
						<p class="lls-completed-phrases__story-line">
							<?php if ( $story_url !== '' && $story_title !== '' ) : ?>
								<a class="lls-completed-phrases__story-link" href="<?php echo esc_url( $story_url ); ?>"><?php echo esc_html( $story_title ); ?></a>
							<?php else : ?>
								<span class="lls-completed-phrases__story-link lls-completed-phrases__story-link--muted"><?php echo esc_html( $story_title !== '' ? $story_title : '—' ); ?></span>
							<?php endif; ?>
						</p>
						<?php if ( $cat_list || $tag_list ) : ?>
							<div class="lls-completed-phrases__tax">
								<?php if ( $cat_list ) : ?>
									<p class="lls-completed-phrases__tax-line">
										<span class="lls-completed-phrases__tax-label"><?php esc_html_e( 'Categories', 'language-learning-stories' ); ?></span>
										<span class="lls-completed-phrases__tax-list"><?php echo wp_kses_post( $cat_list ); ?></span>
									</p>
								<?php endif; ?>
								<?php if ( $tag_list ) : ?>
									<p class="lls-completed-phrases__tax-line">
										<span class="lls-completed-phrases__tax-label"><?php esc_html_e( 'Tags', 'language-learning-stories' ); ?></span>
										<span class="lls-completed-phrases__tax-list"><?php echo wp_kses_post( $tag_list ); ?></span>
									</p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</li>
				<?php
			}
			?>
		</ul>
	</div>
	<?php
	$inner = (string) ob_get_clean();
	return lls_wrap_shortcode_html( $inner, 'block' );
}

add_action(
	'init',
	static function () {
		add_shortcode( 'lls_profile_greeting', 'lls_shortcode_profile_greeting' );
		add_shortcode( 'lls_profile_continue_stories', 'lls_shortcode_profile_continue_stories' );
		add_shortcode( 'lls_library_stories', 'lls_shortcode_library_stories' );
		add_shortcode( 'lls_profile_learn_language', 'lls_shortcode_profile_learn_language' );
		add_shortcode( 'lls_profile_account', 'lls_shortcode_profile_account' );
		add_shortcode( 'lls_completed_phrases', 'lls_shortcode_completed_phrases' );
		add_shortcode( 'lls_total_phrases', 'lls_shortcode_total_phrases' );
		add_shortcode( 'total_phrases', 'lls_shortcode_total_phrases' );
	},
	12
);
