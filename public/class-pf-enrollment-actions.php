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
}
