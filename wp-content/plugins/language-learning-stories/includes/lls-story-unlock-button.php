<?php
/**
 * Pulsante sblocco storia (coin) per shortcode, Elementor e template.
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Risolve l’ID della storia per shortcode / widget (Loop, singola, global $post).
 *
 * @param int $atts_id ID da attributo shortcode (0 = auto).
 * @return int
 */
function lls_story_unlock_resolve_post_id( $atts_id = 0 ) {
	$post_id = (int) $atts_id;
	if ( $post_id > 0 ) {
		return $post_id;
	}

	global $post;
	if ( $post instanceof WP_Post && 'lls_story' === $post->post_type ) {
		return (int) $post->ID;
	}

	if ( function_exists( 'is_singular' ) && is_singular( 'lls_story' ) ) {
		$qid = (int) get_queried_object_id();
		if ( $qid > 0 ) {
			return $qid;
		}
	}

	$post_id = (int) get_the_ID();
	/**
	 * Filtra l’ID storia usato dal pulsante sblocco (es. builder che non imposta $post).
	 *
	 * @param int $post_id ID risolto o 0.
	 */
	return (int) apply_filters( 'lls_story_unlock_button_post_id', $post_id );
}

/**
 * Markup del pulsante «Entra» / sblocco coin (stessa logica di elenco profilo/libreria).
 *
 * @param int   $post_id ID post `lls_story`.
 * @param array $args    Opzioni: enter_label, wrapper_class, coin_gate (bool, default true come elenco profilo).
 * @return string HTML o stringa vuota se post non valido.
 */
function lls_get_story_unlock_button_html( $post_id, $args = [] ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return '';
	}
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || 'lls_story' !== $post->post_type || 'publish' !== $post->post_status ) {
		return '';
	}

	$args += [
		'enter_label'   => __( 'Enter the story', 'language-learning-stories' ),
		'wrapper_class' => '',
		'coin_gate'     => true,
	];

	// Allineato a lls_get_profile_story_list_item_html: gate attivo solo se economia + flag.
	$coin_gate = (bool) $args['coin_gate'] && function_exists( 'lls_user_can_access_story' );
	$user_id   = is_user_logged_in() ? get_current_user_id() : 0;
	$cost      = $coin_gate ? lls_get_story_coin_cost( $post->ID ) : 0;
	$can_access = ! $coin_gate || ! function_exists( 'lls_user_can_access_story' ) || lls_user_can_access_story( $user_id, $post->ID );
	$balance    = ( $coin_gate && $user_id > 0 && function_exists( 'lls_get_user_coin_balance' ) ) ? lls_get_user_coin_balance( $user_id ) : 0;
	$can_afford = $cost <= 0 || $balance >= $cost;
	$url        = get_permalink( $post );

	$locked_class = ( $coin_gate && $cost > 0 && ! $can_access ) ? ' lls-profile-continue__item--locked' : '';
	$wrap_extra   = trim( (string) $args['wrapper_class'] );

	ob_start();
	?>
	<div class="lls-story-unlock<?php echo esc_attr( $locked_class ); ?><?php echo $wrap_extra !== '' ? ' ' . esc_attr( $wrap_extra ) : ''; ?>" data-lls-story-id="<?php echo esc_attr( (string) (int) $post->ID ); ?>">
		<p class="lls-continua-wrap">
			<?php if ( $can_access ) : ?>
				<a class="lls-btn lls-btn-continua" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( (string) $args['enter_label'] ); ?></a>
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
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Shortcode: pulsante sblocco / entra nella storia.
 *
 * Uso: [lls_story_unlock_button] nella pagina storia o nel Loop (post corrente).
 *      [lls_story_unlock_button id="123"] per forzare una storia.
 *
 * @param array|string $atts Attributi.
 * @return string
 */
function lls_shortcode_story_unlock_button( $atts ) {
	$atts = shortcode_atts(
		[
			'id'        => '0',
			'coin_gate' => '1',
		],
		is_array( $atts ) ? $atts : [],
		'lls_story_unlock_button'
	);

	$post_id = lls_story_unlock_resolve_post_id( (int) $atts['id'] );

	$use_gate = ( '1' === $atts['coin_gate'] || 'true' === strtolower( (string) $atts['coin_gate'] ) );

	$html = lls_get_story_unlock_button_html(
		$post_id,
		[
			'coin_gate' => $use_gate,
		]
	);
	if ( $html === '' ) {
		return '';
	}
	return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $html, 'block' ) : $html;
}

add_action(
	'init',
	static function () {
		add_shortcode( 'lls_story_unlock_button', 'lls_shortcode_story_unlock_button' );
	},
	12
);

/**
 * @param \Elementor\Widgets_Manager $widgets_manager Manager widget.
 */
function lls_register_elementor_story_unlock_widget( $widgets_manager ) {
	if ( ! $widgets_manager instanceof \Elementor\Widgets_Manager ) {
		return;
	}
	if ( ! class_exists( 'LLS_Elementor_Widget_Story_Unlock' ) ) {
		require_once __DIR__ . '/lls-elementor-widget-story-unlock.php';
	}
	if ( ! class_exists( 'LLS_Elementor_Widget_Story_Unlock' ) ) {
		return;
	}
	$widgets_manager->register( new LLS_Elementor_Widget_Story_Unlock() );
}

add_action( 'elementor/widgets/register', 'lls_register_elementor_story_unlock_widget', 20 );
