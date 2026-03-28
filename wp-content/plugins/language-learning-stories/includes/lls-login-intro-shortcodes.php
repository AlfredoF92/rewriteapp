<?php
/**
 * Shortcode intestazione pagina login (testi da bacheca + commutatore lingua).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode: blocco introduttivo login.
 *
 * Uso: [lls_login_intro] — register_url opzionale (sovrascrive l’URL impostato in Storie → Pagina Login).
 *
 * @param string[]|string $atts Attributi shortcode.
 * @return string
 */
function lls_shortcode_login_intro( $atts ) {
	if ( ! function_exists( 'lls_login_intro_get_merged_strings' ) ) {
		return '';
	}

	$atts = shortcode_atts(
		[
			'register_url' => '',
		],
		is_array( $atts ) ? $atts : [],
		'lls_login_intro'
	);

	$register_url = lls_login_intro_get_register_url( trim( (string) $atts['register_url'] ) );

	$strings = lls_login_intro_get_merged_strings();

	$config = [
		'defaultLang' => 'en',
		'registerUrl' => $register_url,
		'strings'     => [],
	];

	foreach ( lls_login_intro_lang_codes() as $lc ) {
		$config['strings'][ $lc ] = isset( $strings[ $lc ] ) ? $strings[ $lc ] : $strings['en'];
	}

	$config_json = wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( ! is_string( $config_json ) ) {
		$config_json = '{}';
	}

	$lang_label_en = isset( $strings['en']['langLabel'] ) ? (string) $strings['en']['langLabel'] : 'Language';
	$en            = $strings['en'];

	ob_start();
	?>
	<div class="lls-login-intro" data-lls-login-intro data-config="<?php echo esc_attr( $config_json ); ?>">
		<div class="lls-login-intro__lang" role="group" aria-label="<?php echo esc_attr( $lang_label_en ); ?>">
			<button type="button" class="lls-login-intro__lang-btn lls-login-intro__lang-btn--active" data-lang="en" aria-pressed="true">English</button>
			<button type="button" class="lls-login-intro__lang-btn" data-lang="es" aria-pressed="false">Español</button>
			<button type="button" class="lls-login-intro__lang-btn" data-lang="it" aria-pressed="false">Italiano</button>
			<button type="button" class="lls-login-intro__lang-btn" data-lang="pl" aria-pressed="false">Polski</button>
		</div>
		<p class="lls-login-intro__greeting" data-lls-intro-greeting><?php echo esc_html( $en['greeting'] ); ?></p>
		<p class="lls-login-intro__body" data-lls-intro-body><?php echo esc_html( $en['body'] ); ?></p>
		<p class="lls-login-intro__register" data-lls-intro-register>
			<?php if ( $register_url !== '' ) : ?>
				<?php echo esc_html( $en['regBefore'] ); ?>
				<a class="lls-login-intro__register-link" href="<?php echo esc_url( $register_url ); ?>"><?php echo esc_html( $en['regLink'] ); ?></a><?php echo esc_html( $en['regAfter'] ); ?>
			<?php else : ?>
				<?php echo esc_html( $en['regPlain'] ); ?>
			<?php endif; ?>
		</p>
	</div>
	<?php
	$inner = (string) ob_get_clean();

	wp_enqueue_script( 'lls-login-intro' );

	return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
}

add_action(
	'init',
	static function () {
		add_shortcode( 'lls_login_intro', 'lls_shortcode_login_intro' );
	},
	12
);
