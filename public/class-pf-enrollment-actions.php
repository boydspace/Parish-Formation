<?php
/**
 * Handles participant-initiated enrollment requests.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Public enrollment actions. */
final class Parish_Formation_Enrollment_Actions {

	/** Register the authenticated access-code enrollment endpoint. */
	public static function register_rest_route() {
		register_rest_route(
			'parish-formation/v1',
			'/access-code-enrollment',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_access_code_enroll' ),
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
				'args'                => array(
					'course_id'   => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'access_code' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			'parish-formation/v1',
			'/invitation-enrollment',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_invitation_enroll' ),
				'permission_callback' => static function () { return is_user_logged_in(); },
				'args'                => array( 'token' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ),
			)
		);
	}

	/** Accept an invitation through the non-JavaScript fallback. */
	public static function invitation_enroll() {
		$token      = isset( $_POST['invitation_token'] ) ? sanitize_text_field( wp_unslash( $_POST['invitation_token'] ) ) : '';
		$return_url = add_query_arg( 'pf_invitation', rawurlencode( $token ), Parish_Formation_Shortcodes::get_course_catalog_url() );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( $return_url ) );
			exit;
		}
		check_admin_referer( 'pf_accept_invitation', 'pf_invitation_nonce' );
		$result = Parish_Formation_Invitation_Repository::accept( $token, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'pf_invitation_notice', $result->get_error_code(), $return_url ) );
			exit;
		}
		wp_safe_redirect( Parish_Formation_Shortcodes::get_my_formation_url() );
		exit;
	}

	/** Accept an invitation over AJAX/REST. */
	public static function rest_invitation_enroll( WP_REST_Request $request ) {
		$result = Parish_Formation_Invitation_Repository::accept( sanitize_text_field( $request->get_param( 'token' ) ), get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}
		$enrollment = Parish_Formation_Enrollment_Repository::get_details( $result );
		$url = trailingslashit( Parish_Formation_Shortcodes::get_my_formation_url() ) . 'course/' . rawurlencode( get_post_field( 'post_name', $enrollment->course_id ) ) . '/';
		return rest_ensure_response( array( 'message' => __( 'Invitation accepted. You are now enrolled.', 'parish-formation' ), 'course_url' => $url ) );
	}

	/** Register a new participant account from an email-restricted invitation. */
	public static function register_from_invitation() {
		$token = isset( $_POST['invitation_token'] ) ? sanitize_text_field( wp_unslash( $_POST['invitation_token'] ) ) : '';
		check_admin_referer( 'pf_register_invitation', 'pf_invitation_registration_nonce' );
		$invitation = Parish_Formation_Invitation_Repository::get_by_token( $token );
		$base_url   = add_query_arg( 'pf_invitation', rawurlencode( $token ), Parish_Formation_Shortcodes::get_course_catalog_url() );
		if ( ! $invitation ) {
			self::registration_redirect( $base_url, 'invitation-unavailable' );
		}
		$email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		$login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ), true ) : '';
		$name  = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$pass  = isset( $_POST['user_password'] ) ? (string) wp_unslash( $_POST['user_password'] ) : '';
		if ( ! is_email( $email ) || ! $login || strlen( $pass ) < 8 ) {
			self::registration_redirect( $base_url, 'registration-invalid' );
		}
		if ( $invitation->restricted_email && strtolower( $email ) !== strtolower( $invitation->restricted_email ) ) {
			self::registration_redirect( $base_url, 'invitation-email-mismatch' );
		}
		$valid = Parish_Formation_Invitation_Repository::validate_for_user( $invitation, (object) array( 'user_email' => $email ) );
		if ( is_wp_error( $valid ) ) {
			self::registration_redirect( $base_url, $valid->get_error_code() );
		}
		if ( email_exists( $email ) || username_exists( $login ) ) {
			self::registration_redirect( $base_url, 'account-exists' );
		}
		$user_id = wp_insert_user( array( 'user_login' => $login, 'user_email' => $email, 'display_name' => $name ?: $login, 'user_pass' => $pass, 'role' => 'parish_formation_participant' ) );
		if ( is_wp_error( $user_id ) ) {
			self::registration_redirect( $base_url, 'registration-invalid' );
		}
		$result = Parish_Formation_Invitation_Repository::accept( $token, $user_id );
		if ( is_wp_error( $result ) ) {
			self::registration_redirect( $base_url, $result->get_error_code() );
		}
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		wp_safe_redirect( Parish_Formation_Shortcodes::get_my_formation_url() );
		exit;
	}

	private static function registration_redirect( $url, $notice ) {
		wp_safe_redirect( add_query_arg( 'pf_invitation_notice', sanitize_key( $notice ), $url ) );
		exit;
	}

	/** Enroll the current user in an open course. */
	public static function self_enroll() {
		$course_id  = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$return_url = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : home_url( '/' );
		$return_url = wp_validate_redirect( $return_url, home_url( '/' ) );

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( $return_url ) );
			exit;
		}

		check_admin_referer( 'pf_self_enroll_' . $course_id, 'pf_self_enroll_nonce' );
		$result = Parish_Formation_Enrollment_Repository::create_self_enrollment( get_current_user_id(), $course_id );

		if ( is_wp_error( $result ) ) {
			$status = 'duplicate_enrollment' === $result->get_error_code() ? 'already-enrolled' : 'error';
			wp_safe_redirect( add_query_arg( 'pf_enrollment', $status, $return_url ) );
			exit;
		}

		$user = wp_get_current_user();
		if ( ! $user->has_cap( 'pf_access_formation' ) ) {
			$user->add_cap( 'pf_access_formation' );
		}

		Parish_Formation_Notifications::send_participant_event( 'enrollment_confirmation', $result, array(), 'self_enrolled' );
		wp_safe_redirect( add_query_arg( 'pf_enrollment', 'success', $return_url ) );
		exit;
	}

	/** Enroll the current user after validating a course access code. */
	public static function access_code_enroll() {
		$course_id  = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$return_url = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : home_url( '/' );
		$return_url = wp_validate_redirect( $return_url, home_url( '/' ) );

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( $return_url ) );
			exit;
		}

		check_admin_referer( 'pf_access_code_enroll_' . $course_id, 'pf_access_code_nonce' );
		$code   = isset( $_POST['access_code'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['access_code'] ) ) ) : '';
		$result = self::enroll_with_access_code( get_current_user_id(), $course_id, $code );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'pf_enrollment', $result->get_error_code(), $return_url ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'pf_enrollment', 'success', $return_url ) );
		exit;
	}

	/** Process an access-code enrollment request over REST. */
	public static function rest_access_code_enroll( WP_REST_Request $request ) {
		$course_id = absint( $request->get_param( 'course_id' ) );
		$code      = trim( sanitize_text_field( $request->get_param( 'access_code' ) ) );
		$result    = self::enroll_with_access_code( get_current_user_id(), $course_id, $code );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				self::error_message( $result->get_error_code() ),
				array( 'status' => 'already-enrolled' === $result->get_error_code() ? 409 : 400 )
			);
		}

		$enrollment   = Parish_Formation_Enrollment_Repository::get_details( $result );
		$formation_url = Parish_Formation_Shortcodes::get_my_formation_url();
		return rest_ensure_response(
			array(
				'enrollment_id' => absint( $result ),
				'message'       => __( 'You are now enrolled.', 'parish-formation' ),
				'status_label'  => __( 'Enrolled', 'parish-formation' ),
				'course_url'    => $enrollment ? trailingslashit( $formation_url ) . 'course/' . rawurlencode( get_post_field( 'post_name', $enrollment->course_id ) ) . '/' : $formation_url,
			)
		);
	}

	/** Validate a code, create the enrollment, and run enrollment side effects. */
	private static function enroll_with_access_code( $user_id, $course_id, $code ) {
		if ( ! $course_id ) {
			$course_id = self::find_course_for_code( $code );
			if ( is_wp_error( $course_id ) ) {
				return $course_id;
			}
		}

		$status = self::validate_access_code( $course_id, $code );
		if ( is_wp_error( $status ) ) {
			return $status;
		}

		$result = Parish_Formation_Enrollment_Repository::create_access_code_enrollment( $user_id, $course_id );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'duplicate_enrollment' === $result->get_error_code() ? 'already-enrolled' : 'error' );
		}

		$uses = absint( get_post_meta( $course_id, Parish_Formation_Course_Settings::ACCESS_CODE_USES_META_KEY, true ) );
		update_post_meta( $course_id, Parish_Formation_Course_Settings::ACCESS_CODE_USES_META_KEY, $uses + 1 );
		$user = get_userdata( $user_id );
		if ( $user && ! $user->has_cap( 'pf_access_formation' ) ) {
			$user->add_cap( 'pf_access_formation' );
		}

		Parish_Formation_Notifications::send_participant_event( 'enrollment_confirmation', $result, array(), 'access_code_enrolled' );
		return $result;
	}

	/** Find the one published course matching a submitted access code. */
	private static function find_course_for_code( $code ) {
		if ( '' === $code ) {
			return new WP_Error( 'invalid-code' );
		}

		$course_ids = get_posts(
			array(
				'post_type'      => Parish_Formation_Course_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => Parish_Formation_Course_Settings::ACCESS_CODE_ENABLED_META_KEY,
						'value' => '1',
					),
					array(
						'key'     => Parish_Formation_Course_Settings::ACCESS_CODE_HASH_META_KEY,
						'compare' => 'EXISTS',
					),
				),
			)
		);
		$matches = array();
		foreach ( $course_ids as $candidate_id ) {
			$hash = get_post_meta( $candidate_id, Parish_Formation_Course_Settings::ACCESS_CODE_HASH_META_KEY, true );
			if ( $hash && wp_check_password( $code, $hash ) ) {
				$matches[] = absint( $candidate_id );
			}
		}

		if ( 1 < count( $matches ) ) {
			return new WP_Error( 'ambiguous-code' );
		}

		return $matches ? $matches[0] : new WP_Error( 'invalid-code' );
	}

	/** Validate an access code and its availability without exposing its value. */
	private static function validate_access_code( $course_id, $code ) {
		if ( Parish_Formation_Course_Post_Type::POST_TYPE !== get_post_type( $course_id ) || 'publish' !== get_post_status( $course_id ) || ! get_post_meta( $course_id, Parish_Formation_Course_Settings::ACCESS_CODE_ENABLED_META_KEY, true ) ) {
			return new WP_Error( 'code-unavailable' );
		}

		$hash = get_post_meta( $course_id, Parish_Formation_Course_Settings::ACCESS_CODE_HASH_META_KEY, true );
		if ( ! $code || ! $hash || ! wp_check_password( $code, $hash ) ) {
			return new WP_Error( 'invalid-code' );
		}

		$expires = sanitize_text_field( get_post_meta( $course_id, Parish_Formation_Course_Settings::ACCESS_CODE_EXPIRES_META_KEY, true ) );
		if ( $expires && $expires < current_time( 'Y-m-d' ) ) {
			return new WP_Error( 'code-expired' );
		}

		$limit = absint( get_post_meta( $course_id, Parish_Formation_Course_Settings::ACCESS_CODE_LIMIT_META_KEY, true ) );
		$uses  = absint( get_post_meta( $course_id, Parish_Formation_Course_Settings::ACCESS_CODE_USES_META_KEY, true ) );
		if ( $limit && $uses >= $limit ) {
			return new WP_Error( 'code-exhausted' );
		}

		return true;
	}

	/** Return a participant-facing message for a known enrollment error. */
	private static function error_message( $code ) {
		$messages = array(
			'invalid-code'     => __( 'That access code is not valid.', 'parish-formation' ),
			'code-expired'     => __( 'That access code has expired.', 'parish-formation' ),
			'code-exhausted'   => __( 'That access code has reached its usage limit.', 'parish-formation' ),
			'code-unavailable' => __( 'Access-code enrollment is no longer available for this course.', 'parish-formation' ),
			'ambiguous-code'   => __( 'That code matches more than one course. Please contact the parish for assistance.', 'parish-formation' ),
			'already-enrolled' => __( 'You are already enrolled in this course.', 'parish-formation' ),
			'error'            => __( 'The course enrollment could not be completed.', 'parish-formation' ),
		);

		return isset( $messages[ $code ] ) ? $messages[ $code ] : $messages['error'];
	}
}
