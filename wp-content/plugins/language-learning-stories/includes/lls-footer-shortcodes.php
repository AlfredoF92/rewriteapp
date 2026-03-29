<?php
/**
 * Shortcode navigazione footer stile app (prima lettera grande + resto piccolo).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifica se il percorso richiesto coincide con lo slug del menu (evidenzia voce corrente).
 *
 * @param string $slug Slug percorso senza slash, es. library.
 * @return bool
 */
function lls_footer_app_nav_is_current_path( $slug ) {
	$slug = trim( (string) $slug, '/' );
	if ( $slug === '' ) {
		return false;
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path = wp_parse_url( $uri, PHP_URL_PATH );
	if ( ! is_string( $path ) ) {
		return false;
	}
	$path = trim( $path, '/' );

	$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( is_string( $home_path ) ) {
		$home_path = trim( $home_path, '/' );
		if ( $home_path !== '' && strpos( $path . '/', $home_path . '/' ) === 0 ) {
			$path = trim( substr( $path, strlen( $home_path ) ), '/' );
		}
	}

	if ( $path === $slug ) {
		return true;
	}
	$prefix = $slug . '/';
	return strlen( $path ) > strlen( $slug ) && strpos( $path, $prefix ) === 0;
}

/**
 * URL sicuro da slug percorso (solo caratteri consentiti in segmenti URL).
 *
 * @param string $path Slug o percorso relativo alla home.
 * @return string
 */
function lls_footer_app_nav_url_from_path( $path ) {
	$path = trim( (string) $path, '/' );
	$path = preg_replace( '#[^a-zA-Z0-9\-/]#', '', $path );
	$path = trim( $path, '/' );
	if ( $path === '' ) {
		return home_url( '/' );
	}
	return home_url( '/' . $path . '/' );
}

/**
 * Shortcode: menu footer tipo app (es. L + ibrary, C + ommunity).
 *
 * Uso: [lls_footer_app_nav]
 *
 * @param string[]|string $atts Attributi shortcode.
 * @return string
 */
function lls_shortcode_footer_app_nav( $atts ) {
	$atts = shortcode_atts(
		[
			'library_path'   => 'library',
			'community_path' => 'community',
			'play_path'      => 'play',
			'profile_path'   => 'area-personale',
		],
		is_array( $atts ) ? $atts : [],
		'lls_footer_app_nav'
	);

	$lib   = trim( (string) $atts['library_path'], '/' );
	$com   = trim( (string) $atts['community_path'], '/' );
	$play  = trim( (string) $atts['play_path'], '/' );
	$prof  = trim( (string) $atts['profile_path'], '/' );

	$items = [
		[
			'id'           => 'library',
			'path_segment' => $lib,
			'url'          => lls_footer_app_nav_url_from_path( $atts['library_path'] ),
			'label'        => __( 'Library', 'language-learning-stories' ),
			'big'          => 'L',
			'rest'         => 'ibrary',
		],
		[
			'id'           => 'community',
			'path_segment' => $com,
			'url'          => lls_footer_app_nav_url_from_path( $atts['community_path'] ),
			'label'        => __( 'Community', 'language-learning-stories' ),
			'big'          => 'C',
			'rest'         => 'ommunity',
		],
		[
			'id'           => 'play',
			'path_segment' => $play,
			'url'          => lls_footer_app_nav_url_from_path( $atts['play_path'] ),
			'label'        => __( 'Play', 'language-learning-stories' ),
			'big'          => 'P',
			'rest'         => 'lay',
		],
		[
			'id'           => 'profile',
			'path_segment' => $prof,
			'url'          => lls_footer_app_nav_url_from_path( $atts['profile_path'] ),
			'label'        => __( 'Profile', 'language-learning-stories' ),
			'big'          => 'P',
			'rest'         => 'rofile',
		],
	];

	if ( function_exists( 'lls_footer_app_nav_apply_saved_to_items' ) ) {
		$items = lls_footer_app_nav_apply_saved_to_items( $items );
	}

	/**
	 * Modifica le voci del menu footer app (url, label, big, rest, path_segment, id).
	 * Ha priorità sulle impostazioni salvate in bacheca. Se mancano big/rest ma c’è label, si può derivare la prima lettera e il resto.
	 *
	 * @param array<int, array<string, string>> $items Voci menu.
	 * @param array<string, string>             $atts  Attributi shortcode.
	 */
	$items = apply_filters( 'lls_footer_app_nav_items', $items, $atts );

	foreach ( $items as $i => $item ) {
		$has_big  = array_key_exists( 'big', $item );
		$has_rest = array_key_exists( 'rest', $item );
		if ( $has_big && $has_rest ) {
			continue;
		}
		$lbl = isset( $item['label'] ) ? (string) $item['label'] : '';
		if ( $lbl === '' ) {
			continue;
		}
		if ( ! $has_big ) {
			if ( function_exists( 'mb_substr' ) ) {
				$items[ $i ]['big'] = mb_strtoupper( mb_substr( $lbl, 0, 1 ) );
			} else {
				$items[ $i ]['big'] = strtoupper( substr( $lbl, 0, 1 ) );
			}
		}
		if ( ! $has_rest ) {
			$items[ $i ]['rest'] = function_exists( 'mb_substr' ) ? mb_substr( $lbl, 1 ) : substr( $lbl, 1 );
		}
	}

	$nav_label  = __( 'App navigation', 'language-learning-stories' );
	$nav_inline = [ 'background:transparent', 'background-color:transparent' ];
	$style_decl = function_exists( 'lls_footer_app_nav_inline_style_declarations' ) ? lls_footer_app_nav_inline_style_declarations() : '';
	if ( $style_decl !== '' ) {
		$nav_inline[] = $style_decl;
	}
	$nav_style = ' style="' . esc_attr( implode( ';', $nav_inline ) ) . '"';

	ob_start();
	?>
	<nav class="lls-shortcodes lls-app-footer-nav" role="navigation" aria-label="<?php echo esc_attr( $nav_label ); ?>"<?php echo $nav_style; ?>>
		<?php foreach ( $items as $item ) : ?>
			<?php
			$url     = isset( $item['url'] ) ? esc_url( $item['url'] ) : '#';
			$label   = isset( $item['label'] ) ? (string) $item['label'] : '';
			$big     = isset( $item['big'] ) ? (string) $item['big'] : '';
			$rest    = isset( $item['rest'] ) ? (string) $item['rest'] : '';
			$slug    = isset( $item['path_segment'] ) ? trim( (string) $item['path_segment'], '/' ) : '';
			$current = $slug !== '' && lls_footer_app_nav_is_current_path( $slug );
			$licls   = 'lls-app-footer-nav__item' . ( $current ? ' lls-app-footer-nav__item--current' : '' );
			?>
			<a class="<?php echo esc_attr( $licls ); ?>" href="<?php echo $url; ?>"<?php echo $label !== '' ? ' aria-label="' . esc_attr( $label ) . '"' : ''; ?>>
				<span class="lls-app-footer-nav__wordmark" aria-hidden="true">
					<?php if ( $big !== '' ) : ?>
						<span class="lls-app-footer-nav__big"><?php echo esc_html( $big ); ?></span>
					<?php endif; ?>
					<?php if ( $rest !== '' ) : ?>
						<span class="lls-app-footer-nav__rest"><?php echo esc_html( $rest ); ?></span>
					<?php endif; ?>
				</span>
			</a>
		<?php endforeach; ?>
	</nav>
	<?php
	$html = (string) ob_get_clean();
	return $html;
}

/**
 * Shortcode footer: solo nomi lingua interfaccia → lingua da imparare.
 *
 * Uso: [lls_footer_lang_summary] oppure [lls_footer_lang_summary sep="-->"]
 *
 * Ospiti: italiano → inglese (stessi default di libreria e profilo).
 *
 * @param string[]|string $atts Attributi shortcode.
 * @return string
 */
function lls_shortcode_footer_lang_summary( $atts ) {
	$atts = shortcode_atts(
		[
			'sep' => '→',
		],
		is_array( $atts ) ? $atts : [],
		'lls_footer_lang_summary'
	);

	$iface_code = function_exists( 'lls_get_user_known_lang' ) ? lls_get_user_known_lang() : 'it';
	$learn_code = function_exists( 'lls_get_user_learn_target_lang' ) ? lls_get_user_learn_target_lang() : 'en';

	$labels_iface = function_exists( 'lls_get_known_lang_choice_labels' ) ? lls_get_known_lang_choice_labels() : [];
	$labels_learn = function_exists( 'lls_get_story_target_lang_choice_labels' ) ? lls_get_story_target_lang_choice_labels() : [];

	$iface_name = isset( $labels_iface[ $iface_code ] ) ? $labels_iface[ $iface_code ] : $iface_code;
	$learn_name = isset( $labels_learn[ $learn_code ] ) ? $labels_learn[ $learn_code ] : $learn_code;

	$sep = (string) $atts['sep'];
	if ( $sep === '' ) {
		$sep = '→';
	}

	$aria_label = sprintf(
		/* translators: 1: interface language name, 2: language to learn name */
		__( 'Interface language %1$s, language to learn %2$s', 'language-learning-stories' ),
		$iface_name,
		$learn_name
	);

	ob_start();
	?>
	<div class="lls-shortcodes lls-footer-lang-summary" role="status" aria-label="<?php echo esc_attr( $aria_label ); ?>">
		<span class="lls-footer-lang-summary__lang"><?php echo esc_html( $iface_name ); ?></span>
		<span class="lls-footer-lang-summary__arrow" aria-hidden="true"><?php echo esc_html( $sep ); ?></span>
		<span class="lls-footer-lang-summary__lang"><?php echo esc_html( $learn_name ); ?></span>
	</div>
	<?php
	return (string) ob_get_clean();
}

add_action(
	'init',
	static function () {
		add_shortcode( 'lls_footer_app_nav', 'lls_shortcode_footer_app_nav' );
		add_shortcode( 'lls_footer_lang_summary', 'lls_shortcode_footer_lang_summary' );
	},
	12
);
