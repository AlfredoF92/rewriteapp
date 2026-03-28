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
 * HTML di un elemento elenco storia (stesso markup di [lls_profile_continue_stories]).
 *
 * @param WP_Post $post         Post storia.
 * @param int     $words        Parole riassunto.
 * @param int     $completed    Frasi completate.
 * @param int     $total        Frasi totali.
 * @param string  $button_label Testo pulsante (già tradotto).
 * @return string
 */
function lls_get_profile_story_list_item_html( WP_Post $post, $words, $completed, $total, $button_label ) {
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

	ob_start();
	?>
	<li class="lls-profile-continue__item">
		<h3 class="lls-story-title">
			<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
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
		<div class="lls-profile-continue__progress-stack">
			<div class="lls-progress-bar-wrap" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $total ); ?>" aria-valuenow="<?php echo esc_attr( (string) $completed ); ?>" aria-label="<?php echo esc_attr( $aria_pb ); ?>">
				<div class="lls-progress-bar" style="width: <?php echo esc_attr( (string) $pct ); ?>%;"></div>
			</div>
			<div class="lls-progress-row">
				<span class="lls-progress-counter"><?php echo esc_html( sprintf( /* translators: 1: completed, 2: total */ __( '%1$d / %2$d sentences', 'language-learning-stories' ), $completed, $total ) ); ?></span>
			</div>
		</div>
		<p class="lls-continua-wrap">
			<a class="lls-btn lls-btn-continua" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $button_label ); ?></a>
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
 * Shortcode: libreria — tutte le storie pubblicate per la lingua interfaccia scelta dall’utente (o attributo lang).
 *
 * Uso: [lls_library_stories limit="50" words="40"]
 *
 * @param string[]|string $atts Attributi shortcode.
 * @return string
 */
function lls_shortcode_library_stories( $atts ) {
	$atts = shortcode_atts(
		[
			'limit'     => '50',
			'words'     => '40',
			'lang'      => '',
			'show_lang' => '1',
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

	$meta_query = lls_meta_query_stories_for_interface_lang( $lang );
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
			esc_html__( 'No published stories match this interface language yet.', 'language-learning-stories' ) .
			'</p></div>';
		return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
	}

	$intro = '';
	if ( '1' === $atts['show_lang'] || 'true' === $atts['show_lang'] ) {
		$labels = function_exists( 'lls_get_known_lang_choice_labels' ) ? lls_get_known_lang_choice_labels() : [];
		$label  = isset( $labels[ $lang ] ) ? $labels[ $lang ] : $lang;
		$intro  = '<p class="lls-library-stories__lang-note">' . sprintf(
			/* translators: %s: language name, e.g. Italian */
			esc_html__( 'Stories for: %s', 'language-learning-stories' ),
			esc_html( $label )
		) . '</p>';
	}

	$out = '<div class="lls-profile-continue lls-library-stories">' . $intro . '<ul class="lls-profile-continue__list">' . $items_html . '</ul></div>';
	return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $out, 'block' ) : $out;
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

add_action(
	'init',
	static function () {
		add_shortcode( 'lls_profile_greeting', 'lls_shortcode_profile_greeting' );
		add_shortcode( 'lls_profile_continue_stories', 'lls_shortcode_profile_continue_stories' );
		add_shortcode( 'lls_library_stories', 'lls_shortcode_library_stories' );
		add_shortcode( 'lls_profile_account', 'lls_shortcode_profile_account' );
	},
	12
);
