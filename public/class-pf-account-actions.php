<?php
/** Public participant login and registration actions. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Secure form handlers for account shortcodes. */
final class Parish_Formation_Account_Actions {
	public static function login() {
		check_admin_referer( 'pf_account_login', 'pf_account_nonce' );
		$return_url = self::return_url( 'login' );
		if ( is_user_logged_in() ) { wp_safe_redirect( Parish_Formation_Account_Service::settings()['login_redirect'] ); exit; }
		if ( isset( $_POST['login_method'] ) && 'passwordless' === sanitize_key( wp_unslash( $_POST['login_method'] ) ) ) {
			$email = isset( $_POST['log'] ) ? sanitize_email( wp_unslash( $_POST['log'] ) ) : '';
			self::send_passwordless( $email, $return_url );
		}
		$key = self::rate_key( 'login' );
		if ( absint( get_transient( $key ) ) >= 8 ) { self::redirect_error( $return_url, 'login-rate-limited' ); }
		$login = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$password = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';
		$user = wp_signon( array( 'user_login' => $login, 'user_password' => $password, 'remember' => ! empty( $_POST['rememberme'] ) ), is_ssl() );
		if ( is_wp_error( $user ) ) {
			$count = absint( get_transient( $key ) ) + 1; set_transient( $key, $count, 10 * MINUTE_IN_SECONDS );
			self::redirect_error( $return_url, 'login-failed' );
		}
		delete_transient( $key );
		wp_safe_redirect( $return_url ); exit;
	}

	public static function register() {
		check_admin_referer( 'pf_account_register', 'pf_registration_nonce' );
		$return_url = self::return_url( 'registration' );
		$settings   = Parish_Formation_Account_Service::settings();
		if ( ! $settings['public_registration'] ) { self::redirect_error( $return_url, 'registration-disabled' ); }
		if ( ! empty( $_POST['website'] ) ) { self::redirect_error( $return_url, 'registration-failed' ); }
		$key = self::rate_key( 'registration' );
		if ( absint( get_transient( $key ) ) >= 5 ) { self::redirect_error( $return_url, 'registration-rate-limited' ); }
		$result = Parish_Formation_Account_Service::create_participant( array( 'email' => isset( $_POST['user_email'] ) ? wp_unslash( $_POST['user_email'] ) : '', 'first_name' => isset( $_POST['first_name'] ) ? wp_unslash( $_POST['first_name'] ) : '', 'last_name' => isset( $_POST['last_name'] ) ? wp_unslash( $_POST['last_name'] ) : '', 'phone' => isset( $_POST['cell_phone'] ) ? wp_unslash( $_POST['cell_phone'] ) : '', 'password' => isset( $_POST['user_password'] ) ? wp_unslash( $_POST['user_password'] ) : '', 'verify_password' => isset( $_POST['verify_password'] ) ? wp_unslash( $_POST['verify_password'] ) : '' ) );
		if ( is_wp_error( $result ) ) { set_transient( $key, absint( get_transient( $key ) ) + 1, 15 * MINUTE_IN_SECONDS ); self::redirect_error( $return_url, $result->get_error_code() ); }
		delete_transient( $key );
		if ( $result['generated_password'] ) {
			wp_send_new_user_notifications( $result['user_id'], 'user' );
			wp_safe_redirect( add_query_arg( 'pf_account_notice', 'check-email', $return_url ) ); exit;
		}
		wp_set_current_user( $result['user_id'] ); wp_set_auth_cookie( $result['user_id'], true, is_ssl() );
		wp_safe_redirect( $settings['registration_redirect'] ); exit;
	}

	public static function logout() {
		check_admin_referer( 'pf_account_logout' );
		wp_logout();
		wp_safe_redirect( add_query_arg( 'pf_account_notice', 'logged-out', Parish_Formation_Account_Shortcodes::get_login_url() ) ); exit;
	}

	public static function reset_password() {
		check_admin_referer( 'pf_account_reset_password', 'pf_reset_nonce' );
		$key      = isset( $_POST['reset_key'] ) ? sanitize_text_field( wp_unslash( $_POST['reset_key'] ) ) : '';
		$login    = isset( $_POST['reset_login'] ) ? sanitize_user( wp_unslash( $_POST['reset_login'] ), true ) : '';
		$password = isset( $_POST['new_password'] ) ? (string) wp_unslash( $_POST['new_password'] ) : '';
		$verify   = isset( $_POST['verify_password'] ) ? (string) wp_unslash( $_POST['verify_password'] ) : '';
		$user     = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) || strlen( $password ) < 8 || ! hash_equals( $password, $verify ) ) { wp_safe_redirect( add_query_arg( array( 'pf_reset_key' => $key, 'pf_reset_login' => $login ), Parish_Formation_Account_Shortcodes::get_login_url() ) ); exit; }
		reset_password( $user, $password );
		wp_safe_redirect( add_query_arg( 'pf_account_notice', 'password-reset', Parish_Formation_Account_Shortcodes::get_login_url() ) ); exit;
	}

	public static function redirect_core_login() {
		if ( 'GET' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) { return; }
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'login';
		if ( in_array( $action, array( 'login', 'register', 'lostpassword', 'retrievepassword' ), true ) ) { wp_safe_redirect( Parish_Formation_Account_Shortcodes::get_login_url() ); exit; }
		if ( in_array( $action, array( 'rp', 'resetpass' ), true ) && isset( $_GET['key'], $_GET['login'] ) ) { wp_safe_redirect( Parish_Formation_Account_Shortcodes::get_password_reset_url( sanitize_text_field( wp_unslash( $_GET['key'] ) ), sanitize_user( wp_unslash( $_GET['login'] ), true ) ) ); exit; }
		if ( 'logout' === $action && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'log-out' ) ) { wp_logout(); wp_safe_redirect( add_query_arg( 'pf_account_notice', 'logged-out', Parish_Formation_Account_Shortcodes::get_login_url() ) ); exit; }
	}

	public static function filter_password_reset_message( $message, $key, $user_login ) {
		$url = Parish_Formation_Account_Shortcodes::get_password_reset_url( $key, $user_login );
		return sprintf( __( "A password reset was requested for your account at %1\$s.\n\nSet your password here:\n%2\$s\n\nIf you did not request this, you can ignore this email.", 'parish-formation' ), get_bloginfo( 'name' ), $url );
	}

	public static function request_passwordless() {
		check_admin_referer( 'pf_request_passwordless_login', 'pf_passwordless_nonce' );
		$return_url = self::return_url( 'login' );
		$email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		self::send_passwordless( $email, $return_url );
	}

	private static function send_passwordless( $email, $return_url, $ajax = false ) {
		if ( ! Parish_Formation_Account_Service::settings()['passwordless_login'] ) {
			if ( $ajax ) { wp_send_json_error( array( 'message' => __( 'Passwordless login is not available.', 'parish-formation' ) ), 400 ); }
			self::redirect_error( $return_url, 'passwordless-invalid' );
		}
		$key = self::rate_key( 'passwordless_request' );
		if ( absint( get_transient( $key ) ) >= 5 ) {
			if ( $ajax ) { wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait ten minutes and try again.', 'parish-formation' ) ), 429 ); }
			self::redirect_error( $return_url, 'passwordless-rate-limited' );
		}
		set_transient( $key, absint( get_transient( $key ) ) + 1, 10 * MINUTE_IN_SECONDS );
		$user  = $email ? get_user_by( 'email', $email ) : false;
		$request_id = strtolower( wp_generate_password( 24, false, false ) );
		if ( $user ) {
			$request = Parish_Formation_Account_Service::create_passwordless_request( $user->ID, $return_url );
			if ( $request ) {
				$request_id = $request['request'];
				$magic_url = add_query_arg( array( 'action' => 'pf_passwordless_magic', 'request' => $request['request'], 'token' => $request['token'] ), admin_url( 'admin-post.php' ) );
				$content   = Parish_Formation_Notifications::resolve_template( 'passwordless_login', array( 'participant_name' => $user->display_name ?: $user->user_email, 'magic_login_url' => $magic_url, 'login_code' => $request['code'] ) );
				Parish_Formation_Notifications::send( 'passwordless_login', $user->user_email, $content[0], Parish_Formation_Notifications::types()['passwordless_login'][1], $content[1], 'passwordless_' . $request['request'], true );
			}
		}
		$args = array( 'pf_account_notice' => 'passwordless-sent' );
		if ( $request_id ) { $args['pf_passwordless_request'] = $request_id; }
		if ( $ajax ) { wp_send_json_success( array( 'request' => $request_id ) ); }
		wp_safe_redirect( add_query_arg( $args, $return_url ) ); exit;
	}

	public static function verify_passwordless_code() {
		check_admin_referer( 'pf_verify_passwordless_code', 'pf_passwordless_code_nonce' );
		$return_url = self::return_url( 'login' );
		$key = self::rate_key( 'passwordless_verify' );
		if ( absint( get_transient( $key ) ) >= 8 ) { self::redirect_error( $return_url, 'passwordless-rate-limited' ); }
		$request = isset( $_POST['passwordless_request'] ) ? sanitize_key( wp_unslash( $_POST['passwordless_request'] ) ) : '';
		$code  = isset( $_POST['login_code'] ) ? wp_unslash( $_POST['login_code'] ) : '';
		$login = Parish_Formation_Account_Service::settings()['passwordless_login'] ? Parish_Formation_Account_Service::consume_passwordless_request_code( $request, $code ) : false;
		if ( ! $login ) { set_transient( $key, absint( get_transient( $key ) ) + 1, 10 * MINUTE_IN_SECONDS ); self::redirect_error( $return_url, 'passwordless-invalid' ); }
		delete_transient( $key ); self::complete_passwordless_login( $login );
	}

	public static function magic_login() {
		$request = isset( $_GET['request'] ) ? sanitize_key( wp_unslash( $_GET['request'] ) ) : '';
		$token   = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$login = Parish_Formation_Account_Service::settings()['passwordless_login'] ? Parish_Formation_Account_Service::consume_passwordless_token( $request, $token ) : false;
		if ( ! $login ) { wp_safe_redirect( add_query_arg( 'pf_account_notice', 'passwordless-invalid', self::login_page_url() ) ); exit; }
		self::complete_passwordless_login( $login );
	}

	public static function ajax_request_passwordless() {
		check_ajax_referer( 'pf_account_login', 'nonce' );
		$return_url = isset( $_POST['return_url'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_url'] ) ), home_url( '/' ) ) : home_url( '/' );
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		self::send_passwordless( $email, $return_url, true );
	}

	public static function ajax_verify_passwordless_code() {
		check_ajax_referer( 'pf_verify_passwordless_code', 'nonce' );
		$key = self::rate_key( 'passwordless_verify' );
		if ( absint( get_transient( $key ) ) >= 8 ) { wp_send_json_error( array( 'message' => __( 'Too many attempts. Please wait ten minutes and try again.', 'parish-formation' ) ), 429 ); }
		$request = isset( $_POST['passwordless_request'] ) ? sanitize_key( wp_unslash( $_POST['passwordless_request'] ) ) : '';
		$code    = isset( $_POST['login_code'] ) ? wp_unslash( $_POST['login_code'] ) : '';
		$login = Parish_Formation_Account_Service::settings()['passwordless_login'] ? Parish_Formation_Account_Service::consume_passwordless_request_code( $request, $code ) : false;
		if ( ! $login ) { set_transient( $key, absint( get_transient( $key ) ) + 1, 10 * MINUTE_IN_SECONDS ); wp_send_json_error( array( 'message' => __( 'That code is invalid or has expired. Request a new one and try again.', 'parish-formation' ) ), 400 ); }
		$user = get_userdata( $login['user_id'] );
		if ( ! $user ) { wp_send_json_error( array( 'message' => __( 'The login could not be completed.', 'parish-formation' ) ), 400 ); }
		delete_transient( $key ); wp_set_current_user( $user->ID ); wp_set_auth_cookie( $user->ID, true, is_ssl() ); Parish_Formation_Account_Service::record_login( $user->user_login, $user );
		wp_send_json_success( array( 'redirect' => $login['redirect_url'] ) );
	}

	private static function complete_passwordless_login( $login ) {
		$user = is_array( $login ) ? get_userdata( absint( $login['user_id'] ?? 0 ) ) : false;
		if ( ! $user ) { wp_safe_redirect( add_query_arg( 'pf_account_notice', 'passwordless-invalid', self::login_page_url() ) ); exit; }
		wp_set_current_user( $user->ID ); wp_set_auth_cookie( $user->ID, true, is_ssl() ); Parish_Formation_Account_Service::record_login( $user->user_login, $user );
		wp_safe_redirect( wp_validate_redirect( $login['redirect_url'] ?? '', Parish_Formation_Account_Service::settings()['login_redirect'] ) ); exit;
	}

	private static function login_page_url() {
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
		foreach ( $pages as $page_id ) { if ( has_shortcode( get_post_field( 'post_content', $page_id ), 'parish_formation_login' ) ) { return get_permalink( $page_id ); } }
		return Parish_Formation_Account_Shortcodes::get_login_url();
	}

	private static function return_url( $type ) {
		$fallback = Parish_Formation_Shortcodes::get_course_catalog_url();
		$url = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : $fallback;
		return wp_validate_redirect( $url, $fallback );
	}

	private static function redirect_error( $url, $code ) { wp_safe_redirect( add_query_arg( 'pf_account_notice', sanitize_key( $code ), $url ) ); exit; }
	private static function rate_key( $type ) { $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'; return 'pf_' . sanitize_key( $type ) . '_' . substr( hash( 'sha256', $ip ), 0, 24 ); }
}
