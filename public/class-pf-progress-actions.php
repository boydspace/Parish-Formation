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

	/** Register the enhanced lesson-completion endpoint. */
	public static function register_rest_route() {
		register_rest_route(
			'parish-formation/v1',
			'/lesson-progress',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'complete_lesson_rest' ),
				'permission_callback' => static function () { return is_user_logged_in(); },
			)
		);
	}

	/** Complete a lesson over REST and return the next clean curriculum URL. */
	public static function complete_lesson_rest( WP_REST_Request $request ) {
		$enrollment_id  = absint( $request->get_param( 'enrollment_id' ) );
		$course_id      = absint( $request->get_param( 'course_id' ) );
		$lesson_id      = absint( $request->get_param( 'lesson_id' ) );
		$progress_action = sanitize_key( $request->get_param( 'progress_action' ) );
		$enrollment = Parish_Formation_Enrollment_Repository::get_for_user_course( get_current_user_id(), $course_id );
		if ( ! $enrollment || absint( $enrollment->id ) !== $enrollment_id ) {
			return new WP_Error( 'no_access', __( 'You do not have access to this enrollment.', 'parish-formation' ), array( 'status' => 403 ) );
		}
		if ( $enrollment->expires_at && strtotime( $enrollment->expires_at . ' UTC' ) < time() ) {
			return new WP_Error( 'expired', __( 'Your access to this course has expired.', 'parish-formation' ), array( 'status' => 403 ) );
		}
		$lesson  = Parish_Formation_Course_Repository::get_published_lesson( $course_id, $lesson_id );
		$lessons = Parish_Formation_Course_Repository::get_published_lessons( $course_id );
		if ( ! $lesson || $lesson_id !== Parish_Formation_Progress_Repository::get_current_lesson_id( $enrollment_id, $lessons ) ) {
			return new WP_Error( 'invalid_lesson', __( 'Only the current lesson can be completed.', 'parish-formation' ), array( 'status' => 400 ) );
		}
		if ( ! in_array( $progress_action, array( 'completed', 'skipped' ), true ) || ( 'skipped' === $progress_action && Parish_Formation_Course_Repository::is_lesson_required( $lesson_id ) ) ) {
			return new WP_Error( 'invalid_action', __( 'That lesson action is not valid.', 'parish-formation' ), array( 'status' => 400 ) );
		}
		$enrollment->user_id = get_current_user_id();
		$result = Parish_Formation_Progress_Repository::finish_lesson( $enrollment, $lesson_id, $progress_action );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		Parish_Formation_Progress_Repository::sync_course_completion( $enrollment, $lessons );
		$curriculum = Parish_Formation_Course_Repository::get_published_curriculum( $course_id );
		$next_item = null;
		foreach ( $curriculum as $index => $item ) {
			if ( $lesson_id === $item['post']->ID ) {
				$next_item = $curriculum[ $index + 1 ] ?? null;
				break;
			}
		}
		$base_url = trailingslashit( esc_url_raw( $request->get_param( 'base_url' ) ) );
		$course_slug = get_post_field( 'post_name', $course_id );
		$next_url = $next_item
			? $base_url . 'course/' . rawurlencode( $course_slug ) . '/' . rawurlencode( $next_item['type'] ) . '/' . rawurlencode( $next_item['post']->post_name ) . '/'
			: $base_url . 'course/' . rawurlencode( $course_slug ) . '/';
		return rest_ensure_response( array( 'nextUrl' => esc_url_raw( $next_url ) ) );
	}

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
