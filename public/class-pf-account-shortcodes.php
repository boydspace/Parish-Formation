<?php
/** Login and registration shortcodes. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Theme-compatible participant account forms. */
final class Parish_Formation_Account_Shortcodes {
	public static function register() {
		add_shortcode( 'parish_formation_login', array( self::class, 'render_login' ) );
		add_shortcode( 'parish_formation_registration', array( self::class, 'render_registration' ) );
	}

	public static function enqueue_assets() {
		if ( ! is_singular() ) { return; }
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || ( ! has_shortcode( $post->post_content, 'parish_formation_login' ) && ! has_shortcode( $post->post_content, 'parish_formation_registration' ) ) ) { return; }
		wp_enqueue_style( 'parish-formation-uikit', PARISH_FORMATION_PLUGIN_URL . 'assets/vendor/uikit/uikit.min.css', array(), PARISH_FORMATION_UIKIT_VERSION );
		wp_enqueue_style( 'parish-formation-frontend', PARISH_FORMATION_PLUGIN_URL . 'assets/css/parish-formation-frontend.css', array( 'parish-formation-uikit' ), (string) filemtime( PARISH_FORMATION_PLUGIN_DIR . 'assets/css/parish-formation-frontend.css' ) );
	}

	public static function render_login() {
		$settings = Parish_Formation_Account_Service::settings();
		if ( is_user_logged_in() ) {
			return '<div class="pf-account-card uk-card uk-card-default uk-card-body"><p>' . esc_html__( 'You are already logged in.', 'parish-formation' ) . '</p><p><a class="uk-button uk-button-primary" href="' . esc_url( $settings['login_redirect'] ) . '">' . esc_html__( 'Open My Formation', 'parish-formation' ) . '</a> <a class="uk-button uk-button-default" href="' . esc_url( wp_logout_url( self::current_url() ) ) . '">' . esc_html__( 'Log Out', 'parish-formation' ) . '</a></p></div>';
		}
		$notice = isset( $_GET['pf_account_notice'] ) ? sanitize_key( wp_unslash( $_GET['pf_account_notice'] ) ) : '';
		ob_start(); ?>
		<div class="pf-account-card uk-card uk-card-default uk-card-body"><h2 class="uk-card-title"><?php esc_html_e( 'Log In', 'parish-formation' ); ?></h2><?php echo self::notice( $notice ); ?>
		<form class="pf-account-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_account_login"><input type="hidden" name="return_url" value="<?php echo esc_attr( self::current_url() ); ?>"><?php wp_nonce_field( 'pf_account_login', 'pf_account_nonce' ); ?>
		<label><?php esc_html_e( 'Email address or username', 'parish-formation' ); ?><input class="uk-input" name="log" type="text" required autocomplete="username"></label><label><?php esc_html_e( 'Password', 'parish-formation' ); ?><input class="uk-input" name="pwd" type="password" required autocomplete="current-password"></label><label class="pf-account-check"><input class="uk-checkbox" name="rememberme" type="checkbox" value="1"> <?php esc_html_e( 'Remember me', 'parish-formation' ); ?></label><button class="uk-button uk-button-primary" type="submit"><?php esc_html_e( 'Log In', 'parish-formation' ); ?></button></form><p><a href="<?php echo esc_url( wp_lostpassword_url( self::current_url() ) ); ?>"><?php esc_html_e( 'Forgot your password?', 'parish-formation' ); ?></a></p>
		<?php if ( $settings['public_registration'] ) : ?><p><?php esc_html_e( 'Need an account?', 'parish-formation' ); ?> <a href="<?php echo esc_url( self::get_registration_url() ); ?>"><?php esc_html_e( 'Register here', 'parish-formation' ); ?></a></p><?php endif; ?></div>
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
	private static function get_registration_url() {
		$page_ids = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
		foreach ( $page_ids as $page_id ) {
			if ( has_shortcode( get_post_field( 'post_content', $page_id ), 'parish_formation_registration' ) ) { return get_permalink( $page_id ); }
		}
		return wp_registration_url();
	}
	private static function get_login_url() {
		$page_ids = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
		foreach ( $page_ids as $page_id ) {
			if ( has_shortcode( get_post_field( 'post_content', $page_id ), 'parish_formation_login' ) ) { return get_permalink( $page_id ); }
		}
		return wp_login_url();
	}
	private static function notice( $code ) {
		$messages = array( 'login-failed' => __( 'The login information was not correct.', 'parish-formation' ), 'login-rate-limited' => __( 'Too many login attempts. Please wait ten minutes and try again.', 'parish-formation' ), 'registration-disabled' => __( 'Public account registration is currently closed.', 'parish-formation' ), 'registration-rate-limited' => __( 'Too many registration attempts. Please wait and try again.', 'parish-formation' ), 'invalid-email' => __( 'Enter a valid email address.', 'parish-formation' ), 'account-exists' => __( 'An account already exists for that email address. Please log in instead.', 'parish-formation' ), 'first-name-required' => __( 'First name is required.', 'parish-formation' ), 'last-name-required' => __( 'Last name is required.', 'parish-formation' ), 'phone-required' => __( 'Cell phone number is required.', 'parish-formation' ), 'password-invalid' => __( 'Your password must contain at least eight characters.', 'parish-formation' ), 'password-mismatch' => __( 'The passwords do not match.', 'parish-formation' ), 'check-email' => __( 'Your account was created. Check your email to set your password.', 'parish-formation' ) );
		if ( ! isset( $messages[ $code ] ) ) { return ''; }
		$class = 'check-email' === $code ? 'uk-alert-success' : 'uk-alert-danger';
		return '<div class="uk-alert ' . esc_attr( $class ) . '"><p>' . esc_html( $messages[ $code ] ) . '</p></div>';
	}
}
