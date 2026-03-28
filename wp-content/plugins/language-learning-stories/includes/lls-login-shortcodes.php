<?php
/**
 * Shortcode form di accesso (stile LLS): autenticazione in situ, senza wp-login.php in caso di errore.
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL della pagina col form (per tornare qui dopo errori di login).
 *
 * @return string
 */
function lls_login_get_form_return_url() {
	if ( is_singular() ) {
		$url = get_permalink();
	} else {
		$url = home_url( '/' );
	}
	if ( ! is_string( $url ) || $url === '' ) {
		$url = home_url( '/' );
	}
	return remove_query_arg( 'lls_login', $url );
}

/**
 * Messaggio di errore in base al codice in query (?lls_login=…).
 *
 * @param string $code Codice (failed|empty|nonce).
 * @return string HTML sicuro (paragrafo) o stringa vuota.
 */
function lls_login_error_notice_html( $code ) {
	$code = sanitize_key( $code );
	$msg  = '';
	switch ( $code ) {
		case 'failed':
			$msg = __( 'Invalid username or password.', 'language-learning-stories' );
			break;
		case 'empty':
			$msg = __( 'Please enter both username and password.', 'language-learning-stories' );
			break;
		case 'nonce':
			$msg = __( 'Something went wrong. Please try again.', 'language-learning-stories' );
			break;
		default:
			return '';
	}
	return '<p class="lls-profile-account__notice lls-profile-account__notice--err lls-login__notice" role="alert">' . esc_html( $msg ) . '</p>';
}

/**
 * Gestisce POST da admin-post.php: wp_signon e redirect alla stessa pagina se fallisce.
 */
function lls_handle_frontend_login() {
	$referer_raw = isset( $_POST['_wp_http_referer'] ) ? wp_unslash( $_POST['_wp_http_referer'] ) : '';
	$referer     = '';
	if ( $referer_raw !== '' ) {
		$referer = wp_validate_redirect( esc_url_raw( urldecode( $referer_raw ) ), '' );
	}
	if ( $referer === '' ) {
		$referer = home_url( '/' );
	}
	$referer = remove_query_arg( 'lls_login', $referer );

	$redirect_to_raw = isset( $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : '';
	$redirect_to     = $redirect_to_raw !== '' ? esc_url_raw( urldecode( $redirect_to_raw ) ) : home_url( '/' );
	$redirect_to     = wp_validate_redirect( $redirect_to, home_url( '/' ) );

	if ( is_user_logged_in() ) {
		wp_safe_redirect( $redirect_to );
		exit;
	}

	if ( ! isset( $_POST['lls_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lls_login_nonce'] ) ), 'lls_frontend_login' ) ) {
		wp_safe_redirect( add_query_arg( 'lls_login', 'nonce', $referer ) );
		exit;
	}

	$log = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( (string) $_POST['log'] ) ) : '';
	$pwd = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';
	$remember = ! empty( $_POST['rememberme'] );

	if ( $log === '' || $pwd === '' ) {
		wp_safe_redirect( add_query_arg( 'lls_login', 'empty', $referer ) );
		exit;
	}

	$credentials = [
		'user_login'    => $log,
		'user_password' => $pwd,
		'remember'      => $remember,
	];

	$user = wp_signon( $credentials, is_ssl() );

	if ( is_wp_error( $user ) ) {
		wp_safe_redirect( add_query_arg( 'lls_login', 'failed', $referer ) );
		exit;
	}

	wp_safe_redirect( $redirect_to );
	exit;
}

add_action( 'admin_post_nopriv_lls_frontend_login', 'lls_handle_frontend_login' );
add_action( 'admin_post_lls_frontend_login', 'lls_handle_frontend_login' );

/**
 * Shortcode: modulo login (POST tramite admin-post + wp_signon).
 *
 * Uso: [lls_login] — attributi: redirect, title, logged_in (message|hide).
 *
 * @param string[]|string $atts Attributi shortcode.
 * @return string
 */
function lls_shortcode_login( $atts ) {
	$atts = shortcode_atts(
		[
			'redirect'  => '',
			'title'     => '',
			'logged_in' => 'message',
		],
		is_array( $atts ) ? $atts : [],
		'lls_login'
	);

	$logged_in_mode = strtolower( trim( (string) $atts['logged_in'] ) );
	if ( ! in_array( $logged_in_mode, [ 'message', 'hide' ], true ) ) {
		$logged_in_mode = 'message';
	}

	if ( is_user_logged_in() ) {
		if ( 'hide' === $logged_in_mode ) {
			return '';
		}
		$logout = wp_logout_url( get_permalink() ?: home_url( '/' ) );
		$inner  = '<div class="lls-login lls-login--logged-in">' .
			'<p class="lls-login__logged-msg">' . esc_html__( 'You are logged in.', 'language-learning-stories' ) . '</p>' .
			'<p class="lls-login__logged-actions">' .
			'<a class="lls-btn" href="' . esc_url( $logout ) . '">' . esc_html__( 'Log out', 'language-learning-stories' ) . '</a>' .
			'</p></div>';
		return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
	}

	$redirect = trim( (string) $atts['redirect'] );
	if ( $redirect !== '' ) {
		$redirect = esc_url_raw( $redirect );
		$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );
	} else {
		$from_get = '';
		if ( isset( $_GET['redirect_to'] ) && is_string( $_GET['redirect_to'] ) ) {
			$from_get = wp_validate_redirect( esc_url_raw( urldecode( wp_unslash( $_GET['redirect_to'] ) ) ), '' );
		}
		if ( $from_get === '' && isset( $_GET['lls_redirect_to'] ) && is_string( $_GET['lls_redirect_to'] ) ) {
			$from_get = wp_validate_redirect( esc_url_raw( urldecode( wp_unslash( $_GET['lls_redirect_to'] ) ) ), '' );
		}
		if ( $from_get !== '' ) {
			$redirect = $from_get;
		} else {
			$redirect = ( is_singular() && get_permalink() ) ? get_permalink() : home_url( '/' );
			$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );
		}
	}

	$redirect = apply_filters( 'lls_login_form_redirect', $redirect, $atts );

	$form_return = lls_login_get_form_return_url();

	$title = trim( (string) $atts['title'] );

	$login_err = isset( $_GET['lls_login'] ) ? sanitize_key( wp_unslash( (string) $_GET['lls_login'] ) ) : '';
	$notice    = $login_err !== '' ? lls_login_error_notice_html( $login_err ) : '';

	$show_register = (bool) get_option( 'users_can_register' );
	$register_url  = $show_register ? wp_registration_url() : '';

	ob_start();
	?>
	<div class="lls-login">
		<?php if ( $title !== '' ) : ?>
			<h2 class="lls-story-title lls-login__title"><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>
		<?php
		if ( $notice !== '' ) {
			echo wp_kses(
				$notice,
				[
					'p' => [
						'class' => true,
						'role'  => true,
					],
				]
			);
		}
		?>
		<form class="lls-login__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="on">
			<input type="hidden" name="action" value="lls_frontend_login" />
			<?php wp_nonce_field( 'lls_frontend_login', 'lls_login_nonce', false, true ); ?>
			<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( $form_return ); ?>" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>" />

			<p class="lls-profile-account__field">
				<label for="lls_login_user"><?php esc_html_e( 'Username or email', 'language-learning-stories' ); ?></label>
				<input type="text" name="log" id="lls_login_user" class="lls-profile-account__input" value="" autocomplete="username" required />
			</p>
			<p class="lls-profile-account__field">
				<label for="lls_login_pass"><?php esc_html_e( 'Password', 'language-learning-stories' ); ?></label>
				<input type="password" name="pwd" id="lls_login_pass" class="lls-profile-account__input" value="" autocomplete="current-password" required />
			</p>
			<p class="lls-profile-account__field lls-login__remember">
				<label class="lls-login__remember-label">
					<input type="checkbox" name="rememberme" value="forever" />
					<?php esc_html_e( 'Remember me', 'language-learning-stories' ); ?>
				</label>
			</p>
			<p class="lls-profile-account__actions">
				<input type="submit" name="lls_login_submit" class="lls-btn lls-login__submit" value="<?php echo esc_attr( __( 'Log in', 'language-learning-stories' ) ); ?>" />
			</p>
			<p class="lls-login__links">
				<a href="<?php echo esc_url( wp_lostpassword_url( $redirect ) ); ?>"><?php esc_html_e( 'Lost your password?', 'language-learning-stories' ); ?></a>
				<?php if ( $register_url ) : ?>
					<span class="lls-login__links-sep" aria-hidden="true"> · </span>
					<a href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Register', 'language-learning-stories' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
	</div>
	<?php
	$inner = (string) ob_get_clean();
	return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
}

add_action(
	'init',
	static function () {
		add_shortcode( 'lls_login', 'lls_shortcode_login' );
	},
	12
);
