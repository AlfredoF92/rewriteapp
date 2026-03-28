<?php
/**
 * Economia Coin: costo sblocco storia, ricompensa al completamento, saldo utente.
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var string */
function lls_story_coin_cost_meta_key() {
	return '_lls_story_coin_cost';
}

/** @var string */
function lls_story_coin_reward_meta_key() {
	return '_lls_story_coin_reward';
}

/** @var string */
function lls_user_coin_wallet_meta_key() {
	return '_lls_coin_wallet';
}

/** @var string */
function lls_user_unlocked_stories_meta_key() {
	return '_lls_unlocked_stories';
}

/** @var string */
function lls_user_story_rewards_claimed_meta_key() {
	return '_lls_story_rewards_claimed';
}

/**
 * Costo in coin per sbloccare la storia (0 = gratuita).
 *
 * @param int $post_id ID post storia.
 * @return int
 */
function lls_get_story_coin_cost( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return 0;
	}
	$v = get_post_meta( $post_id, lls_story_coin_cost_meta_key(), true );
	return max( 0, (int) $v );
}

/**
 * Coin guadagnati una tantum al completamento (100% frasi).
 *
 * @param int $post_id ID post storia.
 * @return int
 */
function lls_get_story_coin_reward( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return 0;
	}
	$v = get_post_meta( $post_id, lls_story_coin_reward_meta_key(), true );
	return max( 0, (int) $v );
}

/**
 * Saldo spendibile (wallet).
 *
 * @param int $user_id ID utente.
 * @return int
 */
function lls_get_user_coin_balance( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return 0;
	}
	$v = get_user_meta( $user_id, lls_user_coin_wallet_meta_key(), true );
	return max( 0, (int) $v );
}

/**
 * @param int   $user_id ID utente.
 * @param int   $delta   Positivo o negativo.
 * @param int   $floor   Saldo minimo (non scende sotto).
 * @return int Nuovo saldo.
 */
function lls_adjust_user_coin_balance( $user_id, $delta, $floor = 0 ) {
	$user_id = (int) $user_id;
	$delta   = (int) $delta;
	$floor   = max( 0, (int) $floor );
	if ( $user_id <= 0 ) {
		return 0;
	}
	$cur = lls_get_user_coin_balance( $user_id );
	$new = max( $floor, $cur + $delta );
	update_user_meta( $user_id, lls_user_coin_wallet_meta_key(), $new );
	return $new;
}

/**
 * User meta: cronologia movimenti coin (sblocco, ricompense).
 *
 * @return string
 */
function lls_coin_ledger_meta_key() {
	return '_lls_coin_ledger';
}

/**
 * Aggiunge una voce al registro coin (dopo ogni modifica saldo tracciata).
 *
 * @param int   $user_id ID utente.
 * @param array $entry   Chiavi: type (unlock|reward|phrase), delta, balance_after, story_id; per phrase anche phrase_count; opzionali at, ts, day (Y-m-d fuso orario sito).
 */
function lls_append_coin_ledger_entry( $user_id, array $entry ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return;
	}
	$row = array_merge(
		[
			'type'          => '',
			'delta'         => 0,
			'balance_after' => 0,
			'story_id'      => 0,
			'at'            => current_time( 'mysql' ),
			'ts'            => current_time( 'timestamp' ),
			'day'           => current_time( 'Y-m-d' ),
		],
		$entry
	);
	$row['delta']         = (int) $row['delta'];
	$row['balance_after'] = (int) $row['balance_after'];
	$row['story_id']      = (int) $row['story_id'];
	$row['ts']            = isset( $row['ts'] ) ? (int) $row['ts'] : current_time( 'timestamp' );
	if ( empty( $row['day'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $row['day'] ) ) {
		$row['day'] = wp_date( 'Y-m-d', $row['ts'] );
	}

	$log = get_user_meta( $user_id, lls_coin_ledger_meta_key(), true );
	if ( ! is_array( $log ) ) {
		$log = [];
	}
	$log[] = $row;
	/**
	 * Numero massimo di movimenti coin conservati (le più vecchie vengono rimosse).
	 *
	 * @param int $max Predefinito 500.
	 */
	$max = (int) apply_filters( 'lls_max_coin_ledger_entries', 500 );
	if ( $max > 0 && count( $log ) > $max ) {
		$log = array_slice( $log, -$max );
	}
	update_user_meta( $user_id, lls_coin_ledger_meta_key(), $log );
}

/**
 * @param int $user_id ID utente.
 * @return array<int, array<string, mixed>>
 */
function lls_get_user_coin_ledger( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return [];
	}
	$log = get_user_meta( $user_id, lls_coin_ledger_meta_key(), true );
	return is_array( $log ) ? $log : [];
}

/**
 * Giorno di calendario (Y-m-d, fuso orario del sito) per una voce registro.
 *
 * @param array<string, mixed> $row Voce ledger.
 * @return string
 */
function lls_coin_ledger_entry_day_key( array $row ) {
	$day = isset( $row['day'] ) ? (string) $row['day'] : '';
	if ( $day !== '' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
		return $day;
	}
	$ts = isset( $row['ts'] ) ? (int) $row['ts'] : 0;
	if ( $ts > 0 ) {
		return wp_date( 'Y-m-d', $ts );
	}
	if ( ! empty( $row['at'] ) ) {
		$u = mysql2date( 'U', (string) $row['at'], false );
		if ( $u ) {
			return wp_date( 'Y-m-d', (int) $u );
		}
	}
	return wp_date( 'Y-m-d', current_time( 'timestamp' ) );
}

/**
 * Somma frasi completate e coin da tipo phrase, raggruppate per giorno (più recenti prima).
 *
 * @param int $user_id ID utente.
 * @return array<string, array{phrases:int, coins:int}> Chiave Y-m-d.
 */
function lls_get_phrase_coin_totals_by_day( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return [];
	}
	$log = lls_get_user_coin_ledger( $user_id );
	$by  = [];
	foreach ( $log as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		if ( ( isset( $row['type'] ) ? (string) $row['type'] : '' ) !== 'phrase' ) {
			continue;
		}
		$d = lls_coin_ledger_entry_day_key( $row );
		if ( ! isset( $by[ $d ] ) ) {
			$by[ $d ] = [
				'phrases' => 0,
				'coins'   => 0,
			];
		}
		$n = isset( $row['phrase_count'] ) ? max( 0, (int) $row['phrase_count'] ) : 0;
		if ( $n <= 0 ) {
			$n = 1;
		}
		$by[ $d ]['phrases'] += $n;
		$by[ $d ]['coins']   += isset( $row['delta'] ) ? (int) $row['delta'] : 0;
	}
	krsort( $by, SORT_STRING );
	return $by;
}

/**
 * Aggiunge coin per ogni nuova frase completata (predefinito: 1 coin per frase).
 *
 * @param int $user_id     ID utente.
 * @param int $story_id    ID storia.
 * @param int $new_phrases Quante frasi in più rispetto al salvataggio precedente.
 * @return int Coin aggiunti in questa chiamata (0 se nulla).
 */
function lls_grant_coins_for_completed_phrases( $user_id, $story_id, $new_phrases ) {
	$user_id     = (int) $user_id;
	$story_id    = (int) $story_id;
	$new_phrases = max( 0, (int) $new_phrases );
	if ( $user_id <= 0 || $story_id <= 0 || $new_phrases <= 0 ) {
		return 0;
	}
	/**
	 * Coin guadagnati per ogni singola frase appena completata. Usa 0 per disattivare.
	 *
	 * @param int $coins_per Predefinito 1.
	 * @param int $user_id   ID utente.
	 * @param int $story_id  ID storia.
	 */
	$per = (int) apply_filters( 'lls_coins_per_completed_phrase', 1, $user_id, $story_id );
	if ( $per <= 0 ) {
		return 0;
	}
	$coins       = $per * $new_phrases;
	$new_balance = lls_adjust_user_coin_balance( $user_id, $coins, 0 );
	lls_append_coin_ledger_entry(
		$user_id,
		[
			'type'          => 'phrase',
			'delta'         => $coins,
			'balance_after' => $new_balance,
			'story_id'      => $story_id,
			'phrase_count'  => $new_phrases,
		]
	);
	return $coins;
}

/**
 * @param int $user_id ID utente.
 * @return int[]
 */
function lls_get_user_unlocked_story_ids( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return [];
	}
	$raw = get_user_meta( $user_id, lls_user_unlocked_stories_meta_key(), true );
	if ( ! is_array( $raw ) ) {
		return [];
	}
	$ids = array_map( 'intval', $raw );
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * @param int $user_id  ID utente.
 * @param int $story_id ID storia.
 */
function lls_user_has_unlocked_story( $user_id, $story_id ) {
	$user_id  = (int) $user_id;
	$story_id = (int) $story_id;
	if ( $user_id <= 0 || $story_id <= 0 ) {
		return false;
	}
	return in_array( $story_id, lls_get_user_unlocked_story_ids( $user_id ), true );
}

/**
 * @param int $user_id  ID utente (0 = ospite).
 * @param int $story_id ID storia.
 */
function lls_user_can_access_story( $user_id, $story_id ) {
	$story_id = (int) $story_id;
	if ( $story_id <= 0 || get_post_type( $story_id ) !== 'lls_story' ) {
		return false;
	}
	$cost = lls_get_story_coin_cost( $story_id );
	if ( $cost <= 0 ) {
		return true;
	}
	if ( $user_id <= 0 ) {
		return false;
	}
	return lls_user_has_unlocked_story( $user_id, $story_id );
}

/**
 * Sblocca la storia per l’utente (scala i coin).
 *
 * @param int $user_id  ID utente.
 * @param int $story_id ID storia.
 * @return true|WP_Error
 */
function lls_unlock_story_for_user( $user_id, $story_id ) {
	$user_id  = (int) $user_id;
	$story_id = (int) $story_id;
	if ( $user_id <= 0 ) {
		return new WP_Error( 'lls_not_logged_in', __( 'You must be logged in.', 'language-learning-stories' ) );
	}
	if ( $story_id <= 0 || get_post_type( $story_id ) !== 'lls_story' || 'publish' !== get_post_status( $story_id ) ) {
		return new WP_Error( 'lls_invalid_story', __( 'Story not found.', 'language-learning-stories' ) );
	}
	$cost = lls_get_story_coin_cost( $story_id );
	if ( $cost <= 0 ) {
		return true;
	}
	if ( lls_user_has_unlocked_story( $user_id, $story_id ) ) {
		return true;
	}
	$balance = lls_get_user_coin_balance( $user_id );
	if ( $balance < $cost ) {
		return new WP_Error( 'lls_insufficient_coins', __( 'Not enough coins.', 'language-learning-stories' ) );
	}
	$new_balance = lls_adjust_user_coin_balance( $user_id, -$cost, 0 );
	lls_append_coin_ledger_entry(
		$user_id,
		[
			'type'          => 'unlock',
			'delta'         => -$cost,
			'balance_after' => $new_balance,
			'story_id'      => $story_id,
		]
	);
	$unlocked = lls_get_user_unlocked_story_ids( $user_id );
	$unlocked[] = $story_id;
	$unlocked   = array_values( array_unique( array_map( 'intval', $unlocked ) ) );
	update_user_meta( $user_id, lls_user_unlocked_stories_meta_key(), $unlocked );
	return true;
}

/**
 * @param int $user_id  ID utente.
 * @param int $story_id ID storia.
 * @return bool True se la ricompensa era già stata assegnata.
 */
function lls_user_has_claimed_story_reward( $user_id, $story_id ) {
	$user_id  = (int) $user_id;
	$story_id = (int) $story_id;
	if ( $user_id <= 0 || $story_id <= 0 ) {
		return false;
	}
	$raw = get_user_meta( $user_id, lls_user_story_rewards_claimed_meta_key(), true );
	if ( ! is_array( $raw ) ) {
		return false;
	}
	return in_array( $story_id, array_map( 'intval', $raw ), true );
}

/**
 * Assegna la ricompensa al completamento se applicabile (una tantum per storia).
 *
 * @param int $user_id   ID utente.
 * @param int $story_id  ID storia.
 * @param int $completed Frasi completate salvate.
 * @param int $total     Frasi totali storia.
 * @return int Coin aggiunti (0 se niente).
 */
function lls_maybe_grant_story_completion_reward( $user_id, $story_id, $completed, $total ) {
	$user_id   = (int) $user_id;
	$story_id  = (int) $story_id;
	$completed = (int) $completed;
	$total     = (int) $total;
	if ( $user_id <= 0 || $story_id <= 0 || $total <= 0 ) {
		return 0;
	}
	if ( $completed < $total ) {
		return 0;
	}
	if ( lls_user_has_claimed_story_reward( $user_id, $story_id ) ) {
		return 0;
	}
	$reward = lls_get_story_coin_reward( $story_id );
	if ( $reward <= 0 ) {
		return 0;
	}
	$claimed = get_user_meta( $user_id, lls_user_story_rewards_claimed_meta_key(), true );
	if ( ! is_array( $claimed ) ) {
		$claimed = [];
	}
	$claimed[] = $story_id;
	$claimed   = array_values( array_unique( array_map( 'intval', $claimed ) ) );
	update_user_meta( $user_id, lls_user_story_rewards_claimed_meta_key(), $claimed );
	$new_balance = lls_adjust_user_coin_balance( $user_id, $reward, 0 );
	lls_append_coin_ledger_entry(
		$user_id,
		[
			'type'          => 'reward',
			'delta'         => $reward,
			'balance_after' => $new_balance,
			'story_id'      => $story_id,
		]
	);
	return $reward;
}

/**
 * @param int $story_id ID storia.
 * @return int
 */
function lls_count_story_sentences( $story_id ) {
	$sentences = get_post_meta( (int) $story_id, '_lls_sentences', true );
	return is_array( $sentences ) ? count( $sentences ) : 0;
}

/**
 * URL della richiesta corrente (es. pagina Libreria) per redirect dopo login.
 *
 * @return string
 */
function lls_get_frontend_request_url() {
	if ( function_exists( 'wp_get_current_url' ) ) {
		return wp_get_current_url();
	}
	return home_url( '/' );
}

/**
 * Redirect se la storia è a pagamento e non sbloccata.
 */
function lls_template_redirect_story_coin_gate() {
	if ( ! is_singular( 'lls_story' ) ) {
		return;
	}
	$story_id = (int) get_queried_object_id();
	if ( $story_id <= 0 ) {
		return;
	}
	$user_id = is_user_logged_in() ? get_current_user_id() : 0;
	if ( lls_user_can_access_story( $user_id, $story_id ) ) {
		return;
	}
	if ( $user_id <= 0 ) {
		wp_safe_redirect( wp_login_url( get_permalink( $story_id ) ) );
		exit;
	}
	$ref = wp_get_referer();
	if ( $ref && wp_validate_redirect( $ref, false ) ) {
		wp_safe_redirect( $ref );
		exit;
	}
	wp_safe_redirect(
		(string) apply_filters(
			'lls_locked_story_redirect_url',
			home_url( '/' ),
			$story_id,
			$user_id
		)
	);
	exit;
}

add_action( 'template_redirect', 'lls_template_redirect_story_coin_gate', 5 );

/**
 * AJAX: sblocca storia con coin.
 */
function lls_ajax_unlock_story() {
	check_ajax_referer( 'lls_unlock_story', 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'language-learning-stories' ) ], 403 );
	}
	$story_id = isset( $_POST['story_id'] ) ? (int) $_POST['story_id'] : 0;
	$user_id  = get_current_user_id();
	$result   = lls_unlock_story_for_user( $user_id, $story_id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			[
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			],
			400
		);
	}
	wp_send_json_success(
		[
			'coin_total' => lls_get_user_coin_balance( $user_id ),
			'unlocked'   => true,
			'story_id'   => $story_id,
			'permalink'  => get_permalink( $story_id ),
		]
	);
}

add_action( 'wp_ajax_lls_unlock_story', 'lls_ajax_unlock_story' );

/**
 * Script unlock + localize (pagine con shortcode libreria / elenchi).
 */
function lls_enqueue_coin_economy_script() {
	if ( is_admin() ) {
		return;
	}
	$plugin_main = dirname( __DIR__ ) . '/language-learning-stories.php';
	$plugin_url  = plugin_dir_url( $plugin_main );

	wp_enqueue_script(
		'lls-coin-economy',
		$plugin_url . 'assets/lls-coin-economy.js',
		[ 'jquery' ],
		defined( 'LLS_PLUGIN_VERSION' ) ? LLS_PLUGIN_VERSION : '0.2.1',
		true
	);
	wp_localize_script(
		'lls-coin-economy',
		'llsCoinEconomy',
		[
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'lls_unlock_story' ),
			'i18n'    => [
				'unlocking'  => __( 'Unlocking…', 'language-learning-stories' ),
				'error'      => __( 'Something went wrong. Please try again.', 'language-learning-stories' ),
				'enterStory' => __( 'Enter the story', 'language-learning-stories' ),
			],
		]
	);
}

add_action( 'wp_enqueue_scripts', 'lls_enqueue_coin_economy_script', 25 );
