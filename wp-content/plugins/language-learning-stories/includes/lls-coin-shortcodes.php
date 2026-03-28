<?php
/**
 * Shortcode Coin: saldo e cronologia movimenti.
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode [coin] / [lls_coin]: saldo coin spendibili (wallet; si aggiorna dopo salvataggio progresso / sblocco).
 *
 * Attributi: link (1/0, default 1) link a pagina coin; href (path o URL, default /coin). Testo fisso «Coin: N».
 *
 * @param string[]|string $atts    Attributi shortcode.
 * @param string          $content Contenuto (non usato).
 * @param string          $tag     Nome shortcode (`coin` o `lls_coin`).
 * @return string
 */
function lls_shortcode_coin( $atts, $content = '', $tag = '' ) {
	if ( ! function_exists( 'lls_wrap_shortcode_html' ) || ! function_exists( 'lls_get_user_coin_balance' ) ) {
		return '';
	}

	$atts = shortcode_atts(
		[
			'link' => '1',
			'href' => '/coin',
		],
		$atts,
		$tag !== '' ? $tag : 'coin'
	);

	$user_id = get_current_user_id();
	$total   = $user_id ? lls_get_user_coin_balance( $user_id ) : 0;

	$label = __( 'Coin:', 'language-learning-stories' );
	$inner = '<span class="lls-coin__label">' . esc_html( $label ) . '</span>'
		. '<span class="lls-coin__value" data-lls-coin-value>' . esc_html( (string) (int) $total ) . '</span>';

	$use_link = (string) $atts['link'] === '1' || strtolower( (string) $atts['link'] ) === 'true' || strtolower( (string) $atts['link'] ) === 'yes';
	$href_raw = trim( (string) $atts['href'] );
	if ( $href_raw === '' ) {
		$href_raw = '/coin';
	}
	$coin_url = ( strpos( $href_raw, 'http://' ) === 0 || strpos( $href_raw, 'https://' ) === 0 )
		? $href_raw
		: home_url( '/' . ltrim( $href_raw, '/' ) );
	/**
	 * URL della pagina coin aperta dal click sullo shortcode [coin].
	 *
	 * @param string $coin_url URL assoluto.
	 * @param int    $user_id  ID utente (0 se ospite).
	 */
	$coin_url = (string) apply_filters( 'lls_coin_shortcode_url', $coin_url, $user_id );

	if ( $use_link ) {
		$aria = sprintf(
			/* translators: 1: "Coin:" label, 2: balance number */
			__( '%1$s %2$s — view coin page', 'language-learning-stories' ),
			$label,
			(string) (int) $total
		);
		$html = '<a class="lls-coin lls-coin--link" href="' . esc_url( $coin_url ) . '" aria-label="' . esc_attr( $aria ) . '">'
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
 * Shortcode [lls_coin_history] / [coin_history]: cronologia spese e guadagni coin.
 *
 * Attributi: limit (default 100), summary (1/0: riepilogo giornaliero frasi/coin da esercizio), day_limit (max giorni nel riepilogo, default 90).
 *
 * @param string[]|string $atts Attributi.
 * @return string
 */
function lls_shortcode_coin_history( $atts ) {
	if ( ! function_exists( 'lls_wrap_shortcode_html' ) || ! function_exists( 'lls_get_user_coin_ledger' ) ) {
		return '';
	}

	if ( ! is_user_logged_in() ) {
		$inner = '<p class="lls-coin-history--guest">' .
			esc_html__( 'Log in to see your coin history.', 'language-learning-stories' ) .
			'</p>';
		return lls_wrap_shortcode_html( $inner, 'block' );
	}

	$atts = shortcode_atts(
		[
			'limit'     => '100',
			'summary'   => '1',
			'day_limit' => '90',
		],
		is_array( $atts ) ? $atts : [],
		'lls_coin_history'
	);

	$limit      = max( 1, min( 300, (int) $atts['limit'] ) );
	$day_limit  = max( 1, min( 365, (int) $atts['day_limit'] ) );
	$show_summary = (string) $atts['summary'] === '1' || strtolower( (string) $atts['summary'] ) === 'true' || strtolower( (string) $atts['summary'] ) === 'yes';
	$user_id    = get_current_user_id();
	$ledger     = lls_get_user_coin_ledger( $user_id );

	usort(
		$ledger,
		static function ( $a, $b ) {
			$ta = isset( $a['ts'] ) ? (int) $a['ts'] : 0;
			$tb = isset( $b['ts'] ) ? (int) $b['ts'] : 0;
			return $tb <=> $ta;
		}
	);

	$ledger = array_slice( $ledger, 0, $limit );
	$now_bal = lls_get_user_coin_balance( $user_id );
	$date_fmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

	$by_day = [];
	if ( $show_summary && function_exists( 'lls_get_phrase_coin_totals_by_day' ) ) {
		$by_day = lls_get_phrase_coin_totals_by_day( $user_id );
		if ( count( $by_day ) > $day_limit ) {
			$by_day = array_slice( $by_day, 0, $day_limit, true );
		}
	}

	ob_start();
	?>
	<div class="lls-coin-history">
		<p class="lls-coin-history__balance">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: current coin balance */
					__( 'Current balance: %d coins', 'language-learning-stories' ),
					$now_bal
				)
			);
			?>
		</p>
		<?php if ( $show_summary && count( $by_day ) > 0 ) : ?>
			<section class="lls-coin-history__day-summary" aria-label="<?php echo esc_attr__( 'Practice by day', 'language-learning-stories' ); ?>">
				<h3 class="lls-coin-history__day-summary-title"><?php esc_html_e( 'Phrases and coins by day', 'language-learning-stories' ); ?></h3>
				<ul class="lls-coin-history__day-summary-list" role="list">
					<?php
					foreach ( $by_day as $ymd => $tot ) {
						$phrases = (int) $tot['phrases'];
						$coins   = (int) $tot['coins'];
						if ( $phrases <= 0 && $coins <= 0 ) {
							continue;
						}
						try {
							$tz = wp_timezone();
							$dt = new DateTimeImmutable( $ymd . ' 12:00:00', $tz );
							$day_ts = $dt->getTimestamp();
						} catch ( Exception $e ) {
							$day_ts = current_time( 'timestamp' );
						}
						$heading = date_i18n( 'l j F', $day_ts );
						$phrase_part = sprintf(
							/* translators: %d: number of phrases completed that day */
							_n( 'you completed %d phrase', 'you completed %d phrases', $phrases, 'language-learning-stories' ),
							$phrases
						);
						$coin_part = sprintf(
							/* translators: %d: coins earned that day from phrases */
							_n( 'so you earned %d coin', 'so you earned %d coins', $coins, 'language-learning-stories' ),
							$coins
						);
						$line = sprintf(
							/* translators: 1: weekday and date, 2: phrase sentence fragment, 3: coin sentence fragment */
							__( '%1$s: %2$s, %3$s.', 'language-learning-stories' ),
							$heading,
							$phrase_part,
							$coin_part
						);
						?>
						<li class="lls-coin-history__day-summary-item">
							<?php echo esc_html( $line ); ?>
						</li>
						<?php
					}
					?>
				</ul>
			</section>
		<?php endif; ?>
		<?php if ( count( $ledger ) === 0 ) : ?>
			<p class="lls-coin-history--empty"><?php esc_html_e( 'No coin transactions yet. Complete phrases, finish stories with rewards, or unlock paid stories to see entries here.', 'language-learning-stories' ); ?></p>
		<?php else : ?>
			<h3 class="lls-coin-history__list-title"><?php esc_html_e( 'All transactions', 'language-learning-stories' ); ?></h3>
			<ul class="lls-coin-history__list" role="list">
				<?php
				foreach ( $ledger as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$type    = isset( $row['type'] ) ? (string) $row['type'] : '';
					$delta   = isset( $row['delta'] ) ? (int) $row['delta'] : 0;
					$after   = isset( $row['balance_after'] ) ? (int) $row['balance_after'] : 0;
					$story_id = isset( $row['story_id'] ) ? (int) $row['story_id'] : 0;
					$ts      = isset( $row['ts'] ) ? (int) $row['ts'] : 0;
					if ( $ts <= 0 && ! empty( $row['at'] ) ) {
						$u_at = mysql2date( 'U', (string) $row['at'], false );
						if ( $u_at ) {
							$ts = (int) $u_at;
						}
					}
					$day_key = function_exists( 'lls_coin_ledger_entry_day_key' ) && is_array( $row )
						? lls_coin_ledger_entry_day_key( $row )
						: '';

					$story_title = '';
					$story_url   = '';
					if ( $story_id > 0 && get_post_type( $story_id ) === 'lls_story' && 'publish' === get_post_status( $story_id ) ) {
						$story_title = get_the_title( $story_id );
						$story_url   = get_permalink( $story_id );
					} elseif ( $story_id > 0 ) {
						$story_title = sprintf(
							/* translators: %d: post ID */
							__( 'Story #%d', 'language-learning-stories' ),
							$story_id
						);
					}

					$delta_class = $delta >= 0 ? 'lls-coin-history__delta--credit' : 'lls-coin-history__delta--debit';
					$delta_label = $delta > 0 ? '+' . (string) $delta : (string) $delta;

					if ( 'unlock' === $type ) {
						$detail_lead = sprintf(
							/* translators: %d: coins spent */
							__( 'You spent %d coins to unlock', 'language-learning-stories' ),
							abs( $delta )
						);
					} elseif ( 'reward' === $type ) {
						$detail_lead = sprintf(
							/* translators: %d: coins earned */
							__( 'You earned %d coins for completing', 'language-learning-stories' ),
							$delta
						);
					} elseif ( 'phrase' === $type ) {
						$n = isset( $row['phrase_count'] ) ? max( 1, (int) $row['phrase_count'] ) : 1;
						$detail_lead = sprintf(
							/* translators: 1: coins earned, 2: number of new phrases completed */
							_n(
								'You earned %1$d coin for completing %2$d phrase while practicing',
								'You earned %1$d coins for completing %2$d phrases while practicing',
								$n,
								'language-learning-stories'
							),
							$delta,
							$n
						);
					} else {
						$detail_lead = sprintf(
							/* translators: %d: coin change */
							__( 'Balance change: %d coins', 'language-learning-stories' ),
							$delta
						);
					}

					$date_str = $ts > 0 ? date_i18n( $date_fmt, $ts ) : '—';
					$day_line = '';
					if ( $day_key !== '' ) {
						try {
							$tz_d = wp_timezone();
							$d_obj = new DateTimeImmutable( $day_key . ' 12:00:00', $tz_d );
							$day_line = date_i18n( 'l j F', $d_obj->getTimestamp() );
						} catch ( Exception $e ) {
							$day_line = $day_key;
						}
					}
					?>
					<li class="lls-coin-history__item">
						<div class="lls-coin-history__item-top">
							<time class="lls-coin-history__time" datetime="<?php echo $ts > 0 ? esc_attr( wp_date( 'c', $ts ) ) : ''; ?>"><?php echo esc_html( $date_str ); ?></time>
							<span class="lls-coin-history__delta <?php echo esc_attr( $delta_class ); ?>"><?php echo esc_html( $delta_label ); ?></span>
						</div>
						<?php if ( $day_line !== '' ) : ?>
							<p class="lls-coin-history__calendar-day">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: calendar day (weekday, day, month) */
										__( 'Day: %s', 'language-learning-stories' ),
										$day_line
									)
								);
								?>
							</p>
						<?php endif; ?>
						<p class="lls-coin-history__detail">
							<?php echo esc_html( $detail_lead ); ?>
							<?php if ( 'unlock' === $type || 'reward' === $type || 'phrase' === $type ) : ?>
								<?php if ( $story_url !== '' && $story_title !== '' ) : ?>
									<?php echo ' '; ?>
									<a class="lls-coin-history__story-link" href="<?php echo esc_url( $story_url ); ?>"><?php echo esc_html( $story_title ); ?></a>
								<?php elseif ( $story_title !== '' ) : ?>
									<?php echo ' «' . esc_html( $story_title ) . '»'; ?>
								<?php else : ?>
									<?php echo ' '; ?>
									<span class="lls-coin-history__story-muted"><?php esc_html_e( 'a story', 'language-learning-stories' ); ?></span>
								<?php endif; ?>
							<?php endif; ?>
							.
						</p>
						<p class="lls-coin-history__after">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: balance after transaction */
									__( 'Balance after: %d coins', 'language-learning-stories' ),
									$after
								)
							);
							?>
						</p>
					</li>
					<?php
				}
				?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
	$inner = (string) ob_get_clean();
	return lls_wrap_shortcode_html( $inner, 'block' );
}

add_action(
	'init',
	static function () {
		add_shortcode( 'coin', 'lls_shortcode_coin' );
		add_shortcode( 'lls_coin', 'lls_shortcode_coin' );
		add_shortcode( 'lls_coin_history', 'lls_shortcode_coin_history' );
		add_shortcode( 'coin_history', 'lls_shortcode_coin_history' );
	}
);
