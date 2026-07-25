<?php
/** Participant account settings and creation rules. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Centralizes account rules for public and invitation registration. */
final class Parish_Formation_Account_Service {
	public const SETTINGS_OPTION = 'parish_formation_account_settings';
	public const PHONE_META_KEY = '_pf_cell_phone';
	public const SOURCE_META_KEY = '_pf_account_source';

	/** Return normalized account settings. */
	public static function settings() {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		return array(
			'public_registration' => isset( $saved['public_registration'] ) ? (bool) $saved['public_registration'] : (bool) get_option( 'users_can_register' ),
			'require_first_name'  => ! empty( $saved['require_first_name'] ),
			'require_last_name'   => ! empty( $saved['require_last_name'] ),
			'require_phone'       => ! empty( $saved['require_phone'] ),
			'password_mode'       => isset( $saved['password_mode'] ) && 'generated' === $saved['password_mode'] ? 'generated' : 'required',
			'login_redirect'      => isset( $saved['login_redirect'] ) ? esc_url_raw( $saved['login_redirect'] ) : Parish_Formation_Shortcodes::get_my_formation_url(),
			'registration_redirect' => isset( $saved['registration_redirect'] ) ? esc_url_raw( $saved['registration_redirect'] ) : Parish_Formation_Shortcodes::get_my_formation_url(),
		);
	}

	/** Create a Formation Participant using normalized form values. */
	public static function create_participant( $values, $source = 'public_registration' ) {
		$settings = self::settings();
		$email = isset( $values['email'] ) ? sanitize_email( $values['email'] ) : '';
		$first = isset( $values['first_name'] ) ? sanitize_text_field( $values['first_name'] ) : '';
		$last  = isset( $values['last_name'] ) ? sanitize_text_field( $values['last_name'] ) : '';
		$phone = isset( $values['phone'] ) ? sanitize_text_field( $values['phone'] ) : '';
		$password = isset( $values['password'] ) ? (string) $values['password'] : '';
		$verify   = isset( $values['verify_password'] ) ? (string) $values['verify_password'] : '';
		if ( ! is_email( $email ) ) { return new WP_Error( 'invalid-email', __( 'Enter a valid email address.', 'parish-formation' ) ); }
		if ( email_exists( $email ) ) { return new WP_Error( 'account-exists', __( 'An account already exists for that email address.', 'parish-formation' ) ); }
		if ( $settings['require_first_name'] && ! $first ) { return new WP_Error( 'first-name-required', __( 'First name is required.', 'parish-formation' ) ); }
		if ( $settings['require_last_name'] && ! $last ) { return new WP_Error( 'last-name-required', __( 'Last name is required.', 'parish-formation' ) ); }
		if ( $settings['require_phone'] && ! $phone ) { return new WP_Error( 'phone-required', __( 'Cell phone number is required.', 'parish-formation' ) ); }
		if ( 'required' === $settings['password_mode'] ) {
			if ( strlen( $password ) < 8 ) { return new WP_Error( 'password-invalid', __( 'Your password must contain at least eight characters.', 'parish-formation' ) ); }
			if ( ! hash_equals( $password, $verify ) ) { return new WP_Error( 'password-mismatch', __( 'The passwords do not match.', 'parish-formation' ) ); }
		} else {
			$password = wp_generate_password( 24, true, true );
		}
		$user_id = wp_insert_user( array( 'user_login' => self::unique_username( $email ), 'user_email' => $email, 'user_pass' => $password, 'first_name' => $first, 'last_name' => $last, 'display_name' => trim( $first . ' ' . $last ) ?: $email, 'role' => 'parish_formation_participant' ) );
		if ( is_wp_error( $user_id ) ) { return $user_id; }
		update_user_meta( $user_id, self::PHONE_META_KEY, $phone );
		update_user_meta( $user_id, self::SOURCE_META_KEY, sanitize_key( $source ) );
		return array( 'user_id' => absint( $user_id ), 'generated_password' => 'generated' === $settings['password_mode'] );
	}

	/** Derive a unique username from the part of the email before @. */
	private static function unique_username( $email ) {
		$base = sanitize_user( strstr( $email, '@', true ), true );
		$base = $base ?: 'participant';
		$name = $base;
		$number = 2;
		while ( username_exists( $name ) ) { $name = $base . $number; ++$number; }
		return $name;
	}
}
