<?php
/**
 * Shortcode iscrizione (username, email, password): stile LLS, controlli minimi.
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL della pagina col form iscrizione (senza parametri di errore).
 *
 * @return string
 */
function lls_register_get_form_return_url() {
	$url = function_exists( 'lls_login_get_form_return_url' ) ? lls_login_get_form_return_url() : home_url( '/' );
	return remove_query_arg( 'lls_register', $url );
}

/**
 * Messaggio di errore (?lls_register=…).
 *
 * @param string $code Codice errore.
 * @return string HTML o vuoto.
 */
function lls_register_error_notice_html( $code ) {
	$code = sanitize_key( $code );
	$msg  = '';
	switch ( $code ) {
		case 'email_exists':
			$msg = __( 'This email address is already registered.', 'language-learning-stories' );
			break;
		case 'user_exists':
			$msg = __( 'This username is already taken. Please choose another.', 'language-learning-stories' );
			break;
		case 'invalid_email':
			$msg = __( 'Please enter a valid email address.', 'language-learning-stories' );
			break;
		case 'empty':
			$msg = __( 'Please fill in username, email and password.', 'language-learning-stories' );
			break;
		case 'nonce':
			$msg = __( 'Something went wrong. Please try again.', 'language-learning-stories' );
			break;
		case 'closed':
			$msg = __( 'New user registration is not available.', 'language-learning-stories' );
			break;
		case 'failed':
			$msg = __( 'Registration could not be completed. Please try again.', 'language-learning-stories' );
			break;
		default:
			return '';
	}
	return '<p class="lls-profile-account__notice lls-profile-account__notice--err lls-register__notice" role="alert">' . esc_html( $msg ) . '</p>';
}

/**
 * POST admin-post: crea utente, niente email di benvenuto all’utente, accesso automatico.
 */
function lls_handle_frontend_register() {
	$referer_raw = isset( $_POST['_wp_http_referer'] ) ? wp_unslash( $_POST['_wp_http_referer'] ) : '';
	$referer     = '';
	if ( $referer_raw !== '' ) {
		$referer = wp_validate_redirect( esc_url_raw( urldecode( $referer_raw ) ), '' );
	}
	if ( $referer === '' ) {
		$referer = home_url( '/' );
	}
	$referer = remove_query_arg( 'lls_register', $referer );

	$redirect_to_raw = isset( $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : '';
	$redirect_to     = $redirect_to_raw !== '' ? esc_url_raw( urldecode( $redirect_to_raw ) ) : home_url( '/' );
	$redirect_to     = wp_validate_redirect( $redirect_to, home_url( '/' ) );

	if ( is_user_logged_in() ) {
		wp_safe_redirect( $redirect_to );
		exit;
	}

	if ( ! get_option( 'users_can_register' ) ) {
		wp_safe_redirect( add_query_arg( 'lls_register', 'closed', $referer ) );
		exit;
	}

	if ( ! isset( $_POST['lls_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lls_register_nonce'] ) ), 'lls_frontend_register' ) ) {
		wp_safe_redirect( add_query_arg( 'lls_register', 'nonce', $referer ) );
		exit;
	}

	$username = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( (string) $_POST['user_login'] ), false ) : '';
	$email    = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['user_email'] ) ) : '';
	$password = isset( $_POST['user_pass'] ) ? (string) wp_unslash( $_POST['user_pass'] ) : '';

	if ( $username === '' || $email === '' || $password === '' ) {
		wp_safe_redirect( add_query_arg( 'lls_register', 'empty', $referer ) );
		exit;
	}

	if ( ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'lls_register', 'invalid_email', $referer ) );
		exit;
	}

	if ( email_exists( $email ) ) {
		wp_safe_redirect( add_query_arg( 'lls_register', 'email_exists', $referer ) );
		exit;
	}

	add_filter( 'wp_send_new_user_notification_to_user', '__return_false' );

	$user_id = wp_insert_user(
		[
			'user_login' => $username,
			'user_pass'  => $password,
			'user_email' => $email,
			'role'       => get_option( 'default_role', 'subscriber' ),
		]
	);

	remove_filter( 'wp_send_new_user_notification_to_user', '__return_false' );

	if ( is_wp_error( $user_id ) ) {
		$code = $user_id->get_error_code();
		if ( in_array( $code, [ 'existing_user_login', 'username_exists' ], true ) ) {
			wp_safe_redirect( add_query_arg( 'lls_register', 'user_exists', $referer ) );
			exit;
		}
		if ( in_array( $code, [ 'existing_user_email', 'email_exists' ], true ) ) {
			wp_safe_redirect( add_query_arg( 'lls_register', 'email_exists', $referer ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'lls_register', 'failed', $referer ) );
		exit;
	}

	$credentials = [
		'user_login'    => $username,
		'user_password' => $password,
		'remember'      => true,
	];

	$signon = wp_signon( $credentials, is_ssl() );

	if ( is_wp_error( $signon ) ) {
		wp_safe_redirect( add_query_arg( 'lls_register', 'failed', $referer ) );
		exit;
	}

	wp_safe_redirect( $redirect_to );
	exit;
}

add_action( 'admin_post_nopriv_lls_frontend_register', 'lls_handle_frontend_register' );
add_action( 'admin_post_lls_frontend_register', 'lls_handle_frontend_register' );

/**
 * Shortcode: modulo iscrizione.
 *
 * Uso: [lls_register] — redirect, title, logged_in.
 *
 * @param string[]|string $atts Attributi shortcode.
 * @return string
 */
function lls_shortcode_register( $atts ) {
	$atts = shortcode_atts(
		[
			'redirect'  => '',
			'title'     => '',
			'logged_in' => 'message',
		],
		is_array( $atts ) ? $atts : [],
		'lls_register'
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
		$inner  = '<div class="lls-register lls-register--logged-in">' .
			'<p class="lls-register__logged-msg">' . esc_html__( 'You are logged in.', 'language-learning-stories' ) . '</p>' .
			'<p class="lls-register__logged-actions">' .
			'<a class="lls-btn" href="' . esc_url( $logout ) . '">' . esc_html__( 'Log out', 'language-learning-stories' ) . '</a>' .
			'</p></div>';
		return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
	}

	if ( ! get_option( 'users_can_register' ) ) {
		$inner = '<div class="lls-register lls-register--closed">' .
			'<p class="lls-profile-account__notice lls-profile-account__notice--err lls-register__notice" role="status">' .
			esc_html__( 'New user registration is not available.', 'language-learning-stories' ) .
			'</p></div>';
		return function_exists( 'lls_wrap_shortcode_html' ) ? lls_wrap_shortcode_html( $inner, 'block' ) : $inner;
	}

	$redirect = trim( (string) $atts['redirect'] );
	if ( $redirect !== '' ) {
		$redirect = esc_url_raw( $redirect );
		$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );
	} else {
		$redirect = ( is_singular() && get_permalink() ) ? get_permalink() : home_url( '/' );
		$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );
	}

	$redirect = apply_filters( 'lls_register_form_redirect', $redirect, $atts );

	$form_return = lls_register_get_form_return_url();

	$title = trim( (string) $atts['title'] );

	$err_code = isset( $_GET['lls_register'] ) ? sanitize_key( wp_unslash( (string) $_GET['lls_register'] ) ) : '';
	$notice   = $err_code !== '' ? lls_register_error_notice_html( $err_code ) : '';

	ob_start();
	?>
	<div class="lls-register">
		<?php if ( $title !== '' ) : ?>
			<h2 class="lls-story-title lls-register__title"><?php echo esc_html( $title ); ?></h2>
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
		<form class="lls-register__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="on">
			<input type="hidden" name="action" value="lls_frontend_register" />
			<?php wp_nonce_field( 'lls_frontend_register', 'lls_register_nonce', false, true ); ?>
			<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( $form_return ); ?>" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>" />

			<p class="lls-profile-account__field">
				<label for="lls_reg_user"><?php esc_html_e( 'Username', 'language-learning-stories' ); ?></label>
				<input type="text" name="user_login" id="lls_reg_user" class="lls-profile-account__input" value="" autocomplete="username" required />
			</p>
			<p class="lls-profile-account__field">
				<label for="lls_reg_email"><?php esc_html_e( 'Email', 'language-learning-stories' ); ?></label>
				<input type="email" name="user_email" id="lls_reg_email" class="lls-profile-account__input" value="" autocomplete="email" required />
			</p>
			<p class="lls-profile-account__field">
				<label for="lls_reg_pass"><?php esc_html_e( 'Password', 'language-learning-stories' ); ?></label>
				<input type="password" name="user_pass" id="lls_reg_pass" class="lls-profile-account__input" value="" autocomplete="new-password" required />
			</p>
			<p class="lls-profile-account__actions">
				<input type="submit" name="lls_register_submit" class="lls-btn lls-register__submit" value="<?php echo esc_attr( __( 'Sign up', 'language-learning-stories' ) ); ?>" />
			</p>
			<p class="lls-register__links">
				<a href="<?php echo esc_url( wp_login_url( $form_return ) ); ?>"><?php esc_html_e( 'Already have an account? Log in', 'language-learning-stories' ); ?></a>
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
		add_shortcode( 'lls_register', 'lls_shortcode_register' );
	},
	12
);
