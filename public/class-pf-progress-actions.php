<?php
/**
 * Handles participant lesson-progress actions.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and processes front-end progress changes.
 */
final class Parish_Formation_Progress_Actions {

	/**
	 * Mark the current lesson complete.
	 *
	 * @return void
	 */
	public static function complete_lesson() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		$enrollment_id = isset( $_POST['enrollment_id'] ) ? absint( $_POST['enrollment_id'] ) : 0;
		$course_id     = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$lesson_id     = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0;
		$progress_action = isset( $_POST['progress_action'] ) ? sanitize_key( wp_unslash( $_POST['progress_action'] ) ) : 'completed';

		check_admin_referer( 'pf_complete_lesson_' . $enrollment_id . '_' . $lesson_id );

		$enrollment = Parish_Formation_Enrollment_Repository::get_for_user_course(
			get_current_user_id(),
			$course_id
		);

		if ( ! $enrollment || absint( $enrollment->id ) !== $enrollment_id ) {
			wp_die( esc_html__( 'You do not have access to this enrollment.', 'parish-formation' ) );
		}

		if ( $enrollment->expires_at && strtotime( $enrollment->expires_at . ' UTC' ) < time() ) {
			wp_die( esc_html__( 'Your access to this course has expired.', 'parish-formation' ) );
		}

		$lesson = Parish_Formation_Course_Repository::get_published_lesson( $course_id, $lesson_id );
		$lessons = Parish_Formation_Course_Repository::get_published_lessons( $course_id );

		if ( ! $lesson || $lesson_id !== Parish_Formation_Progress_Repository::get_current_lesson_id( $enrollment_id, $lessons ) ) {
			wp_die( esc_html__( 'Only the current lesson can be completed.', 'parish-formation' ) );
		}

		if ( 'skipped' === $progress_action && Parish_Formation_Course_Repository::is_lesson_required( $lesson_id ) ) {
			wp_die( esc_html__( 'Required lessons cannot be skipped.', 'parish-formation' ) );
		}

		if ( ! in_array( $progress_action, array( 'completed', 'skipped' ), true ) ) {
			wp_die( esc_html__( 'That lesson action is not valid.', 'parish-formation' ) );
		}

		$enrollment->user_id = get_current_user_id();
		$result              = Parish_Formation_Progress_Repository::finish_lesson( $enrollment, $lesson_id, $progress_action );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$completion_result = Parish_Formation_Progress_Repository::sync_course_completion( $enrollment, $lessons );

		if ( is_wp_error( $completion_result ) ) {
			wp_die( esc_html( $completion_result->get_error_message() ) );
		}

		$return_url = isset( $_POST['return_url'] )
			? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_url'] ) ), home_url( '/' ) )
			: home_url( '/' );

		wp_safe_redirect( $return_url );
		exit;
	}
}
