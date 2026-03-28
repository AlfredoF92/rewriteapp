<?php
/**
 * Impostazioni admin per il menu footer app ([lls_footer_app_nav]).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LLS_FOOTER_APP_NAV_OPTION', 'lls_footer_app_nav_settings' );

/**
 * Sanifica il segmento di percorso (slug / sotto-percorsi).
 *
 * @param string $path Percorso grezzo.
 * @return string
 */
function lls_footer_app_nav_sanitize_path_segment( $path ) {
	$path = trim( (string) $path, '/' );
	$path = preg_replace( '#[^a-zA-Z0-9\-/]#', '', $path );
	return trim( $path, '/' );
}

/**
 * Sanifica l’array impostazioni salvate.
 *
 * @param mixed $input Input dal form o get_option.
 * @return array{big_rem: string, rest_rem: string, items: array<string, array<string, string>>}
 */
function lls_footer_app_nav_sanitize_settings( $input ) {
	$ids = [ 'library', 'community', 'play', 'profile' ];
	$out = [
		'big_rem'  => '',
		'rest_rem' => '',
		'items'    => [],
	];

	foreach ( $ids as $id ) {
		$out['items'][ $id ] = [
			'label'      => '',
			'big'        => '',
			'rest'       => '',
			'path'       => '',
			'custom_url' => '',
		];
	}

	if ( ! is_array( $input ) ) {
		return $out;
	}

	if ( isset( $input['big_rem'] ) && (string) $input['big_rem'] !== '' ) {
		$raw = str_replace( ',', '.', (string) $input['big_rem'] );
		if ( is_numeric( $raw ) ) {
			$v = (float) $raw;
			if ( $v > 0 && $v <= 12 ) {
				$out['big_rem'] = (string) $v;
			}
		}
	}

	if ( isset( $input['rest_rem'] ) && (string) $input['rest_rem'] !== '' ) {
		$raw = str_replace( ',', '.', (string) $input['rest_rem'] );
		if ( is_numeric( $raw ) ) {
			$v = (float) $raw;
			if ( $v > 0 && $v <= 4 ) {
				$out['rest_rem'] = (string) $v;
			}
		}
	}

	$in_items = isset( $input['items'] ) && is_array( $input['items'] ) ? $input['items'] : [];

	foreach ( $ids as $id ) {
		$row = isset( $in_items[ $id ] ) && is_array( $in_items[ $id ] ) ? $in_items[ $id ] : [];
		$out['items'][ $id ] = [
			'label'      => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
			'big'        => isset( $row['big'] ) ? sanitize_text_field( (string) $row['big'] ) : '',
			'rest'       => isset( $row['rest'] ) ? sanitize_text_field( (string) $row['rest'] ) : '',
			'path'       => isset( $row['path'] ) ? lls_footer_app_nav_sanitize_path_segment( (string) $row['path'] ) : '',
			'custom_url' => isset( $row['custom_url'] ) ? esc_url_raw( (string) $row['custom_url'] ) : '',
		];
	}

	return $out;
}

/**
 * Impostazioni salvate (sanificate).
 *
 * @return array{big_rem: string, rest_rem: string, items: array<string, array<string, string>>}
 */
function lls_footer_app_nav_get_saved_settings() {
	$raw = get_option( LLS_FOOTER_APP_NAV_OPTION, [] );
	return lls_footer_app_nav_sanitize_settings( $raw );
}

/**
 * Applica le opzioni salvate alle voci (dopo i default, prima del filtro).
 *
 * @param array<int, array<string, string>> $items Voci menu.
 * @return array<int, array<string, string>>
 */
function lls_footer_app_nav_apply_saved_to_items( $items ) {
	$saved = lls_footer_app_nav_get_saved_settings();

	foreach ( $items as $i => $item ) {
		$id = isset( $item['id'] ) ? (string) $item['id'] : '';
		if ( $id === '' || ! isset( $saved['items'][ $id ] ) ) {
			continue;
		}
		$row = $saved['items'][ $id ];

		if ( $row['label'] !== '' ) {
			$items[ $i ]['label'] = $row['label'];
		}
		if ( $row['big'] !== '' ) {
			$items[ $i ]['big'] = $row['big'];
		}
		if ( $row['rest'] !== '' ) {
			$items[ $i ]['rest'] = $row['rest'];
		}
		if ( $row['path'] !== '' ) {
			$items[ $i ]['path_segment'] = $row['path'];
			if ( $row['custom_url'] === '' ) {
				$items[ $i ]['url'] = lls_footer_app_nav_url_from_path( $row['path'] );
			}
		}
		if ( $row['custom_url'] !== '' ) {
			$items[ $i ]['url'] = $row['custom_url'];
		}
	}

	return $items;
}

/**
 * Stile inline per variabili CSS sul nav (dimensioni font).
 *
 * @return string Attributo style senza tag (solo dichiarazioni).
 */
function lls_footer_app_nav_inline_style_declarations() {
	$saved = lls_footer_app_nav_get_saved_settings();
	$parts = [];
	if ( $saved['big_rem'] !== '' ) {
		$parts[] = '--lls-app-nav-big:' . (float) $saved['big_rem'] . 'rem';
	}
	if ( $saved['rest_rem'] !== '' ) {
		$parts[] = '--lls-app-nav-rest:' . (float) $saved['rest_rem'] . 'rem';
	}
	return implode( ';', $parts );
}

/**
 * Rendering pagina Impostazioni (richiede manage_options).
 */
function lls_footer_app_nav_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$saved = lls_footer_app_nav_get_saved_settings();

	$ids = [
		'library'   => __( 'Library', 'language-learning-stories' ),
		'community' => __( 'Community', 'language-learning-stories' ),
		'play'      => __( 'Play', 'language-learning-stories' ),
		'profile'   => __( 'Profile', 'language-learning-stories' ),
	];

	$default_paths = [
		'library'   => 'library',
		'community' => 'community',
		'play'      => 'play',
		'profile'   => 'area-personale',
	];

	$default_wordmarks = [
		'library'   => [ 'big' => 'L', 'rest' => 'ibrary' ],
		'community' => [ 'big' => 'C', 'rest' => 'ommunity' ],
		'play'      => [ 'big' => 'P', 'rest' => 'lay' ],
		'profile'   => [ 'big' => 'P', 'rest' => 'rofile' ],
	];

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Menu footer (navigazione app)', 'language-learning-stories' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Controlla testi, percorsi e dimensioni del menu generato dallo shortcode [lls_footer_app_nav]. Lascia un campo vuoto per usare il valore predefinito del plugin.', 'language-learning-stories' ); ?>
		</p>
		<?php
		if ( isset( $_GET['lls_footer_nav_saved'] ) && '1' === $_GET['lls_footer_nav_saved'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Impostazioni salvate.', 'language-learning-stories' ) . '</p></div>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lls_save_footer_app_nav_settings" />
			<?php wp_nonce_field( 'lls_save_footer_app_nav_settings', 'lls_footer_app_nav_nonce' ); ?>
			<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ); ?>" />

			<h2 class="title" style="margin-top:1.5em;"><?php esc_html_e( 'Dimensioni carattere', 'language-learning-stories' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Valori in rem sul front-end. Vuoto = usa gli stili predefiniti (responsive).', 'language-learning-stories' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lls_fan_big_rem"><?php esc_html_e( 'Lettera grande', 'language-learning-stories' ); ?></label></th>
					<td>
						<input name="lls_footer_app_nav[big_rem]" id="lls_fan_big_rem" type="text" inputmode="decimal" class="small-text" value="<?php echo esc_attr( $saved['big_rem'] ); ?>" placeholder="2.1" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lls_fan_rest_rem"><?php esc_html_e( 'Testo piccolo', 'language-learning-stories' ); ?></label></th>
					<td>
						<input name="lls_footer_app_nav[rest_rem]" id="lls_fan_rest_rem" type="text" inputmode="decimal" class="small-text" value="<?php echo esc_attr( $saved['rest_rem'] ); ?>" placeholder="0.65" />
					</td>
				</tr>
			</table>

			<h2 class="title" style="margin-top:1.5em;"><?php esc_html_e( 'Voci del menu', 'language-learning-stories' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Voce', 'language-learning-stories' ); ?></th>
						<th><?php esc_html_e( 'Etichetta accessibilità', 'language-learning-stories' ); ?></th>
						<th><?php esc_html_e( 'Lettera grande', 'language-learning-stories' ); ?></th>
						<th><?php esc_html_e( 'Testo piccolo', 'language-learning-stories' ); ?></th>
						<th><?php esc_html_e( 'Percorso (slug)', 'language-learning-stories' ); ?></th>
						<th><?php esc_html_e( 'URL completo (opzionale)', 'language-learning-stories' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $ids as $id => $title ) : ?>
						<?php
						$row = $saved['items'][ $id ];
						$ph  = $default_wordmarks[ $id ];
						?>
						<tr>
							<td><strong><?php echo esc_html( $title ); ?></strong></td>
							<td>
								<input type="text" class="regular-text" name="lls_footer_app_nav[items][<?php echo esc_attr( $id ); ?>][label]" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php echo esc_attr( $title ); ?>" />
							</td>
							<td>
								<input type="text" class="small-text" name="lls_footer_app_nav[items][<?php echo esc_attr( $id ); ?>][big]" value="<?php echo esc_attr( $row['big'] ); ?>" placeholder="<?php echo esc_attr( $ph['big'] ); ?>" maxlength="20" />
							</td>
							<td>
								<input type="text" class="regular-text" name="lls_footer_app_nav[items][<?php echo esc_attr( $id ); ?>][rest]" value="<?php echo esc_attr( $row['rest'] ); ?>" placeholder="<?php echo esc_attr( $ph['rest'] ); ?>" />
							</td>
							<td>
								<input type="text" class="regular-text" name="lls_footer_app_nav[items][<?php echo esc_attr( $id ); ?>][path]" value="<?php echo esc_attr( $row['path'] ); ?>" placeholder="<?php echo esc_attr( $default_paths[ $id ] ); ?>" />
							</td>
							<td>
								<input type="url" class="regular-text" name="lls_footer_app_nav[items][<?php echo esc_attr( $id ); ?>][custom_url]" value="<?php echo esc_attr( $row['custom_url'] ); ?>" placeholder="https://…" />
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description" style="margin-top:12px;">
				<?php esc_html_e( 'Se imposti un URL completo, il link userà quell’indirizzo; il percorso (slug) serve comunque per evidenziare la voce corrente quando coincide con l’URL del sito.', 'language-learning-stories' ); ?>
			</p>

			<?php submit_button( __( 'Salva impostazioni', 'language-learning-stories' ) ); ?>
		</form>
	</div>
	<?php
}
