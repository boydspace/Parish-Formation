<?php
/** Public participant login and registration actions. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Secure form handlers for account shortcodes. */
final class Parish_Formation_Account_Actions {
	public static function login() {
		check_admin_referer( 'pf_account_login', 'pf_account_nonce' );
		$return_url = self::return_url( 'login' );
		if ( is_user_logged_in() ) { wp_safe_redirect( Parish_Formation_Account_Service::settings()['login_redirect'] ); exit; }
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
		wp_safe_redirect( Parish_Formation_Account_Service::settings()['login_redirect'] ); exit;
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

	private static function return_url( $type ) {
		$fallback = Parish_Formation_Shortcodes::get_course_catalog_url();
		$url = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : $fallback;
		return wp_validate_redirect( $url, $fallback );
	}

	private static function redirect_error( $url, $code ) { wp_safe_redirect( add_query_arg( 'pf_account_notice', sanitize_key( $code ), $url ) ); exit; }
	private static function rate_key( $type ) { $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'; return 'pf_' . sanitize_key( $type ) . '_' . substr( hash( 'sha256', $ip ), 0, 24 ); }
}
