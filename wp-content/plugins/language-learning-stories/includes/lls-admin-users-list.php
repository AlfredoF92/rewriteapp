<?php
/**
 * Admin: Lista utenti iscritti e scheda dettaglio (progresso, coin, lingue, frasi).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sottomenu sotto Storie.
 */
function lls_admin_users_list_register_menu() {
	add_submenu_page(
		'edit.php?post_type=lls_story',
		__( 'Lista utenti', 'language-learning-stories' ),
		__( 'Lista utenti', 'language-learning-stories' ),
		'list_users',
		'lls-users-list',
		'lls_admin_users_list_render_page'
	);
}

add_action( 'admin_menu', 'lls_admin_users_list_register_menu', 29 );

/**
 * Pagina principale: elenco o scheda utente.
 */
function lls_admin_users_list_render_page() {
	if ( ! current_user_can( 'list_users' ) ) {
		wp_die( esc_html__( 'Non hai i permessi per visualizzare questa pagina.', 'language-learning-stories' ) );
	}

	$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $user_id > 0 ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_die( esc_html__( 'Utente non trovato.', 'language-learning-stories' ) );
		}
		lls_admin_users_list_render_detail( $user );
		return;
	}

	lls_admin_users_list_render_list();
}

/**
 * Elenco utenti (ultimi iscritti).
 */
function lls_admin_users_list_render_list() {
	$per_page = 30;
	$paged    = max( 1, (int) ( isset( $_GET['paged'] ) ? $_GET['paged'] : 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$offset   = ( $paged - 1 ) * $per_page;

	$query = new WP_User_Query(
		[
			'number'  => $per_page,
			'offset'  => $offset,
			'orderby' => 'registered',
			'order'   => 'DESC',
			'fields'  => 'all',
		]
	);

	$users = $query->get_results();
	$total = (int) $query->get_total();
	$pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

	$list_url = admin_url( 'edit.php?post_type=lls_story&page=lls-users-list' );

	$labels_known = function_exists( 'lls_get_known_lang_choice_labels' ) ? lls_get_known_lang_choice_labels() : [];
	$labels_learn = function_exists( 'lls_get_story_target_lang_choice_labels' ) ? lls_get_story_target_lang_choice_labels() : [];

	?>
	<div class="wrap lls-admin-users-list">
		<h1><?php esc_html_e( 'Lista utenti', 'language-learning-stories' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Ultimi utenti registrati sulla piattaforma, con dati Language Learning Stories (frasi completate, lingue, saldo coin). Clicca su «Scheda» per il dettaglio.', 'language-learning-stories' ); ?>
		</p>

		<?php if ( $total === 0 ) : ?>
			<p><?php esc_html_e( 'Nessun utente trovato.', 'language-learning-stories' ); ?></p>
		<?php else : ?>
			<p><strong><?php echo esc_html( sprintf( /* translators: %d: user count */ __( 'Totale utenti: %d', 'language-learning-stories' ), $total ) ); ?></strong></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Utente', 'language-learning-stories' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Email', 'language-learning-stories' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Registrato', 'language-learning-stories' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Frasi completate', 'language-learning-stories' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Lingua UI', 'language-learning-stories' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Lingua da imparare', 'language-learning-stories' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Coin', 'language-learning-stories' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Azioni', 'language-learning-stories' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $users as $user ) : ?>
						<?php
						$uid      = (int) $user->ID;
						$phrases  = function_exists( 'lls_get_user_total_completed_sentences' ) ? lls_get_user_total_completed_sentences( $uid ) : 0;
						$known    = function_exists( 'lls_get_user_known_lang' ) ? lls_get_user_known_lang( $uid ) : '';
						$learn    = function_exists( 'lls_get_user_learn_target_lang' ) ? lls_get_user_learn_target_lang( $uid ) : '';
						$coins    = function_exists( 'lls_get_user_coin_balance' ) ? lls_get_user_coin_balance( $uid ) : 0;
						$known_l  = isset( $labels_known[ $known ] ) ? $labels_known[ $known ] : $known;
						$learn_l  = isset( $labels_learn[ $learn ] ) ? $labels_learn[ $learn ] : $learn;
						$detail_u = add_query_arg( [ 'user_id' => $uid ], $list_url );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $user->user_login ); ?></strong><br />
								<span class="description"><?php echo esc_html( $user->display_name ); ?></span>
							</td>
							<td><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $user->user_registered ) ) ); ?></td>
							<td><?php echo esc_html( (string) (int) $phrases ); ?></td>
							<td><?php echo esc_html( $known_l ); ?></td>
							<td><?php echo esc_html( $learn_l ); ?></td>
							<td><?php echo esc_html( (string) (int) $coins ); ?></td>
							<td>
								<a href="<?php echo esc_url( $detail_u ); ?>"><?php esc_html_e( 'Scheda', 'language-learning-stories' ); ?></a>
								<?php if ( current_user_can( 'edit_users' ) ) : ?>
									| <a href="<?php echo esc_url( get_edit_user_link( $uid ) ); ?>"><?php esc_html_e( 'Profilo WP', 'language-learning-stories' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			if ( $pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo paginate_links(
					[
						'base'      => add_query_arg( 'paged', '%#%', $list_url ),
						'format'    => '',
						'current'   => $paged,
						'total'     => $pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					]
				);
				echo '</div></div>';
			}
			?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Scheda singolo utente.
 *
 * @param WP_User $user Utente.
 */
function lls_admin_users_list_render_detail( WP_User $user ) {
	$user_id = (int) $user->ID;
	$list_url = admin_url( 'edit.php?post_type=lls_story&page=lls-users-list' );

	if ( function_exists( 'lls_backfill_completed_phrases_log_if_needed' ) ) {
		lls_backfill_completed_phrases_log_if_needed( $user_id );
	}

	$known     = function_exists( 'lls_get_user_known_lang' ) ? lls_get_user_known_lang( $user_id ) : '';
	$learn     = function_exists( 'lls_get_user_learn_target_lang' ) ? lls_get_user_learn_target_lang( $user_id ) : '';
	$labels_known = function_exists( 'lls_get_known_lang_choice_labels' ) ? lls_get_known_lang_choice_labels() : [];
	$labels_learn = function_exists( 'lls_get_story_target_lang_choice_labels' ) ? lls_get_story_target_lang_choice_labels() : [];
	$known_l   = isset( $labels_known[ $known ] ) ? $labels_known[ $known ] : $known;
	$learn_l   = isset( $labels_learn[ $learn ] ) ? $labels_learn[ $learn ] : $learn;

	$balance   = function_exists( 'lls_get_user_coin_balance' ) ? lls_get_user_coin_balance( $user_id ) : 0;
	$ledger    = function_exists( 'lls_get_user_coin_ledger' ) ? lls_get_user_coin_ledger( $user_id ) : [];
	usort(
		$ledger,
		static function ( $a, $b ) {
			$ta = isset( $a['ts'] ) ? (int) $a['ts'] : 0;
			$tb = isset( $b['ts'] ) ? (int) $b['ts'] : 0;
			return $tb <=> $ta;
		}
	);
	$ledger = array_slice( $ledger, 0, 150 );

	$unlocked_ids = function_exists( 'lls_get_user_unlocked_story_ids' ) ? lls_get_user_unlocked_story_ids( $user_id ) : [];

	$progress_ids = function_exists( 'lls_collect_user_progress_story_ids' ) ? lls_collect_user_progress_story_ids( $user_id ) : [];
	if ( function_exists( 'lls_order_story_ids_by_recent' ) ) {
		$progress_ids = lls_order_story_ids_by_recent( $user_id, $progress_ids );
	}

	$log_key = function_exists( 'lls_completed_phrases_log_meta_key' ) ? lls_completed_phrases_log_meta_key() : '_lls_completed_phrases_log';
	$phrase_log = get_user_meta( $user_id, $log_key, true );
	if ( ! is_array( $phrase_log ) ) {
		$phrase_log = [];
	}
	usort(
		$phrase_log,
		static function ( $a, $b ) {
			$ta = isset( $a['ts'] ) ? (int) $a['ts'] : 0;
			$tb = isset( $b['ts'] ) ? (int) $b['ts'] : 0;
			return $tb <=> $ta;
		}
	);
	$phrase_log = array_slice( $phrase_log, 0, 80 );

	$date_fmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	$total_phrases = function_exists( 'lls_get_user_total_completed_sentences' ) ? lls_get_user_total_completed_sentences( $user_id ) : count( $phrase_log );

	?>
	<div class="wrap lls-admin-users-list lls-admin-users-list--detail">
		<h1>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: user login */
					__( 'Utente: %s', 'language-learning-stories' ),
					$user->user_login
				)
			);
			?>
		</h1>
		<p>
			<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( '← Torna alla lista', 'language-learning-stories' ); ?></a>
			<?php if ( current_user_can( 'edit_users' ) ) : ?>
				<a href="<?php echo esc_url( get_edit_user_link( $user_id ) ); ?>" class="button"><?php esc_html_e( 'Modifica profilo WordPress', 'language-learning-stories' ); ?></a>
			<?php endif; ?>
		</p>

		<h2><?php esc_html_e( 'Riepilogo account', 'language-learning-stories' ); ?></h2>
		<table class="widefat striped" style="max-width:720px;">
			<tbody>
				<tr><th scope="row"><?php esc_html_e( 'ID', 'language-learning-stories' ); ?></th><td><?php echo esc_html( (string) $user_id ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Nome visualizzato', 'language-learning-stories' ); ?></th><td><?php echo esc_html( $user->display_name ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Email', 'language-learning-stories' ); ?></th><td><?php echo esc_html( $user->user_email ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Registrato', 'language-learning-stories' ); ?></th><td><?php echo esc_html( date_i18n( $date_fmt, strtotime( $user->user_registered ) ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Ruoli', 'language-learning-stories' ); ?></th><td><?php echo esc_html( implode( ', ', $user->roles ) ); ?></td></tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Impostazioni Language Learning Stories', 'language-learning-stories' ); ?></h2>
		<table class="widefat striped" style="max-width:720px;">
			<tbody>
				<tr><th scope="row"><?php esc_html_e( 'Lingua che conosce (interfaccia)', 'language-learning-stories' ); ?></th><td><?php echo esc_html( $known_l . ' (' . $known . ')' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Lingua che vuole imparare', 'language-learning-stories' ); ?></th><td><?php echo esc_html( $learn_l . ' (' . $learn . ')' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Frasi completate (stima da progresso)', 'language-learning-stories' ); ?></th><td><?php echo esc_html( (string) (int) $total_phrases ); ?></td></tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Coin', 'language-learning-stories' ); ?></h2>
		<p><strong><?php echo esc_html( sprintf( /* translators: %d: balance */ __( 'Saldo attuale: %d coin', 'language-learning-stories' ), (int) $balance ) ); ?></strong></p>
		<?php if ( count( $ledger ) === 0 ) : ?>
			<p class="description"><?php esc_html_e( 'Nessun movimento nel registro coin.', 'language-learning-stories' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Data', 'language-learning-stories' ); ?></th>
						<th><?php esc_html_e( 'Tipo', 'language-learning-stories' ); ?></th>
						<th><?php esc_html_e( 'Variazione', 'language-learning-stories' ); ?></th>
						<th><?php esc_html_e( 'Saldo dopo', 'language-learning-stories' ); ?></th>
						<th><?php esc_html_e( 'Storia', 'language-learning-stories' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $ledger as $row ) : ?>
						<?php
						if ( ! is_array( $row ) ) {
							continue;
						}
						$type     = isset( $row['type'] ) ? (string) $row['type'] : '';
						$delta    = isset( $row['delta'] ) ? (int) $row['delta'] : 0;
						$after    = isset( $row['balance_after'] ) ? (int) $row['balance_after'] : 0;
						$story_id = isset( $row['story_id'] ) ? (int) $row['story_id'] : 0;
						$ts       = isset( $row['ts'] ) ? (int) $row['ts'] : 0;
						if ( $ts <= 0 && ! empty( $row['at'] ) ) {
							$u = mysql2date( 'U', (string) $row['at'], false );
							if ( $u ) {
								$ts = (int) $u;
							}
						}
						$date_str = $ts > 0 ? date_i18n( $date_fmt, $ts ) : '—';
						$story_cell = '—';
						if ( $story_id > 0 ) {
							$t = get_the_title( $story_id );
							if ( $t ) {
								$edit = get_edit_post_link( $story_id, 'raw' );
								$story_cell = $edit
									? '<a href="' . esc_url( $edit ) . '">' . esc_html( $t ) . '</a>'
									: esc_html( $t );
							} else {
								$story_cell = '#' . (int) $story_id;
							}
						}
						$type_l = $type;
						if ( 'unlock' === $type ) {
							$type_l = __( 'Sblocco storia', 'language-learning-stories' );
						} elseif ( 'reward' === $type ) {
							$type_l = __( 'Ricompensa completamento', 'language-learning-stories' );
						} elseif ( 'phrase' === $type ) {
							$type_l = __( 'Frasi esercizio', 'language-learning-stories' );
						}
						?>
						<tr>
							<td><?php echo esc_html( $date_str ); ?></td>
							<td><?php echo esc_html( $type_l ); ?></td>
							<td><?php echo esc_html( (string) (int) $delta ); ?></td>
							<td><?php echo esc_html( (string) (int) $after ); ?></td>
							<td><?php echo wp_kses_post( $story_cell ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Storie sbloccate (a pagamento)', 'language-learning-stories' ); ?></h2>
		<?php if ( count( $unlocked_ids ) === 0 ) : ?>
			<p class="description"><?php esc_html_e( 'Nessuna storia sbloccata con coin (o lista vuota).', 'language-learning-stories' ); ?></p>
		<?php else : ?>
			<ul style="list-style:disc;padding-left:1.5em;">
				<?php foreach ( $unlocked_ids as $sid ) : ?>
					<?php
					$sid = (int) $sid;
					if ( $sid <= 0 ) {
						continue;
					}
					$title = get_the_title( $sid );
					$cost  = function_exists( 'lls_get_story_coin_cost' ) ? lls_get_story_coin_cost( $sid ) : 0;
					$edit  = get_edit_post_link( $sid, 'raw' );
					$ts_unlock = function_exists( 'lls_get_story_coin_unlock_timestamp' ) ? lls_get_story_coin_unlock_timestamp( $user_id, $sid ) : 0;
					$unlock_str = $ts_unlock > 0 ? date_i18n( $date_fmt, $ts_unlock ) : '—';
					?>
					<li>
						<?php if ( $edit ) : ?>
							<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $title ? $title : '#' . $sid ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $title ? $title : '#' . $sid ); ?>
						<?php endif; ?>
						<?php
						echo ' — ';
						echo esc_html(
							sprintf(
								/* translators: 1: coin cost, 2: unlock date */
								__( 'costo %1$d coin · sblocco %2$s', 'language-learning-stories' ),
								(int) $cost,
								$unlock_str
							)
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Progresso per storia', 'language-learning-stories' ); ?></h2>
		<?php if ( count( $progress_ids ) === 0 ) : ?>
			<p class="description"><?php esc_html_e( 'Nessun salvataggio progresso.', 'language-learning-stories' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Storia', 'language-learning-stories' ); ?></th>
						<th><?php esc_html_e( 'Completate / Totali', 'language-learning-stories' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $progress_ids as $sid ) : ?>
						<?php
						$sid = (int) $sid;
						if ( $sid <= 0 ) {
							continue;
						}
						$saved = get_user_meta( $user_id, '_lls_progress_' . $sid, true );
						$done  = ( is_array( $saved ) && isset( $saved['completed'] ) ) ? (int) $saved['completed'] : 0;
						$sent  = get_post_meta( $sid, '_lls_sentences', true );
						$tot   = is_array( $sent ) ? count( $sent ) : 0;
						$title = get_the_title( $sid );
						$edit  = get_edit_post_link( $sid, 'raw' );
						?>
						<tr>
							<td>
								<?php if ( $edit ) : ?>
									<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $title ? $title : '#' . $sid ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $title ? $title : '#' . $sid ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) (int) $done . ' / ' . (int) $tot ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Ultime frasi completate (cronologia)', 'language-learning-stories' ); ?></h2>
		<?php if ( count( $phrase_log ) === 0 ) : ?>
			<p class="description"><?php esc_html_e( 'Nessuna voce nel log frasi (stesso dato usato dallo shortcode [lls_completed_phrases]).', 'language-learning-stories' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Data', 'language-learning-stories' ); ?></th>
						<th><?php esc_html_e( 'Frase (traduzione)', 'language-learning-stories' ); ?></th>
						<th><?php esc_html_e( 'Italiano', 'language-learning-stories' ); ?></th>
						<th><?php esc_html_e( 'Storia', 'language-learning-stories' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $phrase_log as $row ) : ?>
						<?php
						if ( ! is_array( $row ) ) {
							continue;
						}
						$texts = function_exists( 'lls_completed_phrase_resolve_texts' ) ? lls_completed_phrase_resolve_texts( $row ) : [ 'text_it' => '', 'text_en' => '' ];
						$ti    = isset( $texts['text_it'] ) ? $texts['text_it'] : '';
						$te    = isset( $texts['text_en'] ) ? $texts['text_en'] : '';
						$story_id = isset( $row['story_id'] ) ? (int) $row['story_id'] : 0;
						$ts    = isset( $row['ts'] ) ? (int) $row['ts'] : 0;
						$date_str = $ts > 0 ? date_i18n( $date_fmt, $ts ) : '—';
						$st_title = $story_id > 0 ? get_the_title( $story_id ) : '—';
						$st_link  = $story_id > 0 ? get_edit_post_link( $story_id, 'raw' ) : '';
						?>
						<tr>
							<td><?php echo esc_html( $date_str ); ?></td>
							<td><?php echo esc_html( wp_trim_words( $te, 40, '…' ) ); ?></td>
							<td><?php echo esc_html( wp_trim_words( $ti, 40, '…' ) ); ?></td>
							<td>
								<?php if ( $st_link && $st_title ) : ?>
									<a href="<?php echo esc_url( $st_link ); ?>"><?php echo esc_html( $st_title ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $st_title ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p style="margin-top:2em;">
			<a href="<?php echo esc_url( $list_url ); ?>" class="button button-primary"><?php esc_html_e( 'Torna alla lista utenti', 'language-learning-stories' ); ?></a>
		</p>
	</div>
	<?php
}
