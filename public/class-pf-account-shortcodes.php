<?php
/** Login and registration shortcodes. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Theme-compatible participant account forms. */
final class Parish_Formation_Account_Shortcodes {
	public static function register() {
		add_shortcode( 'parish_formation_login', array( self::class, 'render_login' ) );
		add_shortcode( 'parish_formation_registration', array( self::class, 'render_registration' ) );
		add_shortcode( 'parish_formation_account_button', array( self::class, 'render_account_button' ) );
	}

	public static function enqueue_assets() {
		if ( ! is_singular() ) { return; }
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || ( ! has_shortcode( $post->post_content, 'parish_formation_login' ) && ! has_shortcode( $post->post_content, 'parish_formation_registration' ) && ! has_shortcode( $post->post_content, 'parish_formation_account_button' ) ) ) { return; }
		wp_enqueue_style( 'parish-formation-uikit', PARISH_FORMATION_PLUGIN_URL . 'assets/vendor/uikit/uikit.min.css', array(), PARISH_FORMATION_UIKIT_VERSION );
		wp_enqueue_style( 'parish-formation-frontend', PARISH_FORMATION_PLUGIN_URL . 'assets/css/parish-formation-frontend.css', array( 'parish-formation-uikit' ), (string) filemtime( PARISH_FORMATION_PLUGIN_DIR . 'assets/css/parish-formation-frontend.css' ) );
		if ( has_shortcode( $post->post_content, 'parish_formation_login' ) ) {
			wp_enqueue_script( 'parish-formation-passwordless', PARISH_FORMATION_PLUGIN_URL . 'assets/js/pf-passwordless-login.js', array(), (string) filemtime( PARISH_FORMATION_PLUGIN_DIR . 'assets/js/pf-passwordless-login.js' ), true );
			wp_localize_script( 'parish-formation-passwordless', 'pfPasswordlessLogin', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'requestNonce' => wp_create_nonce( 'pf_account_login' ), 'verifyNonce' => wp_create_nonce( 'pf_verify_passwordless_code' ), 'sentMessage' => __( 'If an account matches that email, a secure login link and code have been sent.', 'parish-formation' ), 'errorMessage' => __( 'The request could not be completed. Please try again.', 'parish-formation' ), 'codeLabel' => __( 'One-time code', 'parish-formation' ), 'loginLabel' => __( 'Log In With Code', 'parish-formation' ) ) );
		}
	}

	public static function render_login() {
		$settings = Parish_Formation_Account_Service::settings();
		if ( isset( $_GET['pf_reset_key'], $_GET['pf_reset_login'] ) ) { return self::render_password_reset(); }
		if ( is_user_logged_in() ) {
			return '<div class="pf-account-card uk-card uk-card-default uk-card-body"><p>' . esc_html__( 'You are already logged in.', 'parish-formation' ) . '</p><p><a class="uk-button uk-button-primary" href="' . esc_url( $settings['login_redirect'] ) . '">' . esc_html__( 'Open My Formation', 'parish-formation' ) . '</a> ' . self::render_account_button() . '</p></div>';
		}
		$notice = isset( $_GET['pf_account_notice'] ) ? sanitize_key( wp_unslash( $_GET['pf_account_notice'] ) ) : '';
		$passwordless_request = isset( $_GET['pf_passwordless_request'] ) ? sanitize_key( wp_unslash( $_GET['pf_passwordless_request'] ) ) : '';
		$return_url = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ), $settings['login_redirect'] ) : $settings['login_redirect'];
		ob_start(); ?>
		<div class="pf-account-card uk-card uk-card-default uk-card-body"><h2 class="uk-card-title"><?php esc_html_e( 'Log In', 'parish-formation' ); ?></h2><?php echo self::notice( $notice ); ?>
		<form class="pf-account-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_account_login"><input type="hidden" name="return_url" value="<?php echo esc_attr( $return_url ); ?>"><?php wp_nonce_field( 'pf_account_login', 'pf_account_nonce' ); ?>
		<label><?php esc_html_e( 'Email address', 'parish-formation' ); ?><input class="uk-input" name="log" type="email" required autocomplete="email"></label><label><?php esc_html_e( 'Password', 'parish-formation' ); ?><input class="uk-input" name="pwd" type="password" autocomplete="current-password"></label><label class="pf-account-check"><input class="uk-checkbox" name="rememberme" type="checkbox" value="1"> <?php esc_html_e( 'Remember me', 'parish-formation' ); ?></label><div class="pf-account-actions"><button class="uk-button uk-button-primary" type="submit" name="login_method" value="password"><?php esc_html_e( 'Log In', 'parish-formation' ); ?></button><?php if ( $settings['passwordless_login'] ) : ?><button class="uk-button uk-button-default" type="submit" name="login_method" value="passwordless"><?php esc_html_e( 'Email One-Time Code', 'parish-formation' ); ?></button><?php endif; ?></div></form>
		<?php if ( $settings['passwordless_login'] && 'passwordless-sent' === $notice && $passwordless_request ) : ?>
		<form class="pf-account-form pf-account-code-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_verify_passwordless_code"><input type="hidden" name="return_url" value="<?php echo esc_attr( self::current_url() ); ?>"><input type="hidden" name="passwordless_request" value="<?php echo esc_attr( $passwordless_request ); ?>"><?php wp_nonce_field( 'pf_verify_passwordless_code', 'pf_passwordless_code_nonce' ); ?><label><?php esc_html_e( 'One-time code', 'parish-formation' ); ?><input class="uk-input" name="login_code" type="text" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus></label><button class="uk-button uk-button-primary" type="submit"><?php esc_html_e( 'Log In With Code', 'parish-formation' ); ?></button></form>
		<?php endif; ?>
		<div class="pf-passwordless-response" role="status" aria-live="polite" aria-atomic="true"></div>
		<?php if ( $settings['public_registration'] ) : ?><p><?php esc_html_e( 'Need an account?', 'parish-formation' ); ?> <a href="<?php echo esc_url( self::get_registration_url() ); ?>"><?php esc_html_e( 'Register here', 'parish-formation' ); ?></a></p><?php endif; ?></div>
		<?php return ob_get_clean();
	}

	public static function render_account_button() {
		if ( is_user_logged_in() ) {
			$url = wp_nonce_url( add_query_arg( 'action', 'pf_account_logout', admin_url( 'admin-post.php' ) ), 'pf_account_logout' );
			return '<a class="uk-button uk-button-default pf-account-button" href="' . esc_url( $url ) . '">' . esc_html__( 'Log Out', 'parish-formation' ) . '</a>';
		}
		return '<a class="uk-button uk-button-primary pf-account-button" href="' . esc_url( self::get_login_url() ) . '">' . esc_html__( 'Log In', 'parish-formation' ) . '</a>';
	}

	private static function render_password_reset() {
		$key   = sanitize_text_field( wp_unslash( $_GET['pf_reset_key'] ) );
		$login = sanitize_user( wp_unslash( $_GET['pf_reset_login'] ), true );
		$user  = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) { return '<div class="pf-account-card uk-card uk-card-default uk-card-body"><div class="uk-alert uk-alert-danger"><p>' . esc_html__( 'That password setup link is invalid or has expired.', 'parish-formation' ) . '</p></div><a class="uk-button uk-button-primary" href="' . esc_url( self::get_login_url() ) . '">' . esc_html__( 'Return to Login', 'parish-formation' ) . '</a></div>'; }
		ob_start(); ?>
		<div class="pf-account-card uk-card uk-card-default uk-card-body"><h2 class="uk-card-title"><?php esc_html_e( 'Set Your Password', 'parish-formation' ); ?></h2><form class="pf-account-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_account_reset_password"><input type="hidden" name="reset_key" value="<?php echo esc_attr( $key ); ?>"><input type="hidden" name="reset_login" value="<?php echo esc_attr( $login ); ?>"><?php wp_nonce_field( 'pf_account_reset_password', 'pf_reset_nonce' ); ?><label><?php esc_html_e( 'New password', 'parish-formation' ); ?><input class="uk-input" name="new_password" type="password" minlength="8" required autocomplete="new-password"></label><label><?php esc_html_e( 'Verify new password', 'parish-formation' ); ?><input class="uk-input" name="verify_password" type="password" minlength="8" required autocomplete="new-password"></label><button class="uk-button uk-button-primary" type="submit"><?php esc_html_e( 'Save Password', 'parish-formation' ); ?></button></form></div>
		<?php return ob_get_clean();
	}

	public static function render_registration() {
		$settings = Parish_Formation_Account_Service::settings();
		if ( is_user_logged_in() ) { return '<div class="uk-alert uk-alert-primary"><p>' . esc_html__( 'You already have an account and are logged in.', 'parish-formation' ) . '</p></div>'; }
		if ( ! $settings['public_registration'] ) { return '<div class="uk-alert uk-alert-warning"><p>' . esc_html__( 'Public account registration is currently closed.', 'parish-formation' ) . '</p></div>'; }
		$notice = isset( $_GET['pf_account_notice'] ) ? sanitize_key( wp_unslash( $_GET['pf_account_notice'] ) ) : '';
		ob_start(); ?>
		<div class="pf-account-card uk-card uk-card-default uk-card-body"><h2 class="uk-card-title"><?php esc_html_e( 'Create an Account', 'parish-formation' ); ?></h2><?php echo self::notice( $notice ); ?>
		<form class="pf-account-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_account_register"><input type="hidden" name="return_url" value="<?php echo esc_attr( self::current_url() ); ?>"><?php wp_nonce_field( 'pf_account_register', 'pf_registration_nonce' ); ?><div class="pf-account-name-grid"><label><?php esc_html_e( 'First name', 'parish-formation' ); ?><input class="uk-input" name="first_name" type="text" <?php echo $settings['require_first_name'] ? 'required' : ''; ?> autocomplete="given-name"></label><label><?php esc_html_e( 'Last name', 'parish-formation' ); ?><input class="uk-input" name="last_name" type="text" <?php echo $settings['require_last_name'] ? 'required' : ''; ?> autocomplete="family-name"></label></div><label><?php esc_html_e( 'Email address', 'parish-formation' ); ?><input class="uk-input" name="user_email" type="email" required autocomplete="email"></label><label><?php esc_html_e( 'Cell phone number', 'parish-formation' ); ?><input class="uk-input" name="cell_phone" type="tel" <?php echo $settings['require_phone'] ? 'required' : ''; ?> autocomplete="tel"></label>
		<?php if ( 'required' === $settings['password_mode'] ) : ?><div class="pf-account-name-grid"><label><?php esc_html_e( 'Password', 'parish-formation' ); ?><input class="uk-input" name="user_password" type="password" minlength="8" required autocomplete="new-password"></label><label><?php esc_html_e( 'Verify password', 'parish-formation' ); ?><input class="uk-input" name="verify_password" type="password" minlength="8" required autocomplete="new-password"></label></div><?php else : ?><p><?php esc_html_e( 'We will email you a secure link to set up your password.', 'parish-formation' ); ?></p><?php endif; ?>
		<label class="pf-account-honeypot" aria-hidden="true">Website<input name="website" type="text" tabindex="-1" autocomplete="off"></label><button class="uk-button uk-button-primary" type="submit"><?php esc_html_e( 'Create Account', 'parish-formation' ); ?></button></form><p><?php esc_html_e( 'Already have an account?', 'parish-formation' ); ?> <a href="<?php echo esc_url( self::get_login_url() ); ?>"><?php esc_html_e( 'Log in here', 'parish-formation' ); ?></a></p></div>
		<?php return ob_get_clean();
	}

	private static function current_url() { return get_permalink( get_queried_object_id() ); }
	public static function get_registration_url() {
		$page_ids = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
		foreach ( $page_ids as $page_id ) {
			if ( has_shortcode( get_post_field( 'post_content', $page_id ), 'parish_formation_registration' ) ) { return get_permalink( $page_id ); }
		}
		return home_url( '/' );
	}
	public static function get_login_url() {
		$page_ids = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
		foreach ( $page_ids as $page_id ) {
			if ( has_shortcode( get_post_field( 'post_content', $page_id ), 'parish_formation_login' ) ) { return get_permalink( $page_id ); }
		}
		return home_url( '/' );
	}
	public static function filter_login_url( $login_url, $redirect ) { $url = self::get_login_url(); return $redirect ? add_query_arg( 'redirect_to', esc_url_raw( $redirect ), $url ) : $url; }
	public static function filter_register_url() { return self::get_registration_url(); }
	public static function filter_lostpassword_url() { return self::get_login_url(); }
	public static function get_password_reset_url( $key, $login ) { return add_query_arg( array( 'pf_reset_key' => $key, 'pf_reset_login' => $login ), self::get_login_url() ); }
	private static function notice( $code ) {
		$messages = array( 'login-failed' => __( 'The login information was not correct.', 'parish-formation' ), 'login-rate-limited' => __( 'Too many login attempts. Please wait ten minutes and try again.', 'parish-formation' ), 'passwordless-sent' => __( 'If an account matches that email, a secure login link and code have been sent.', 'parish-formation' ), 'passwordless-invalid' => __( 'That login link or code is invalid or has expired. Request a new one and try again.', 'parish-formation' ), 'passwordless-rate-limited' => __( 'Too many passwordless login attempts. Please wait ten minutes and try again.', 'parish-formation' ), 'registration-disabled' => __( 'Public account registration is currently closed.', 'parish-formation' ), 'registration-rate-limited' => __( 'Too many registration attempts. Please wait and try again.', 'parish-formation' ), 'invalid-email' => __( 'Enter a valid email address.', 'parish-formation' ), 'account-exists' => __( 'An account already exists for that email address. Please log in instead.', 'parish-formation' ), 'first-name-required' => __( 'First name is required.', 'parish-formation' ), 'last-name-required' => __( 'Last name is required.', 'parish-formation' ), 'phone-required' => __( 'Cell phone number is required.', 'parish-formation' ), 'password-invalid' => __( 'Your password must contain at least eight characters.', 'parish-formation' ), 'password-mismatch' => __( 'The passwords do not match.', 'parish-formation' ), 'check-email' => __( 'Your account was created. Check your email to set your password.', 'parish-formation' ) );
		$messages['logged-out'] = __( 'You have been logged out.', 'parish-formation' );
		$messages['password-reset'] = __( 'Your password has been updated. You can now log in.', 'parish-formation' );
		if ( ! isset( $messages[ $code ] ) ) { return ''; }
		$class = in_array( $code, array( 'check-email', 'passwordless-sent', 'logged-out', 'password-reset' ), true ) ? 'uk-alert-success' : 'uk-alert-danger';
		return '<div class="uk-alert ' . esc_attr( $class ) . '"><p>' . esc_html( $messages[ $code ] ) . '</p></div>';
	}
}
