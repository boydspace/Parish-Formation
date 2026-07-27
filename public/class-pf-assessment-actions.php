<?php
/** Handles participant assessment submissions. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Parish_Formation_Assessment_Actions {

	/** Register the AJAX assessment submission endpoint. */
	public static function register_rest_route() {
		register_rest_route(
			'parish-formation/v1',
			'/assessment-attempts',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'submit_rest' ),
				'permission_callback' => static function () { return is_user_logged_in(); },
			)
		);
	}

	/** Validate, grade, persist, and redirect an assessment submission. */
	public static function submit() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		$enrollment_id = isset( $_POST['enrollment_id'] ) ? absint( $_POST['enrollment_id'] ) : 0;
		$course_id     = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$assessment_id = isset( $_POST['assessment_id'] ) ? absint( $_POST['assessment_id'] ) : 0;
		check_admin_referer( 'pf_submit_assessment_' . $enrollment_id . '_' . $assessment_id );

		$enrollment = self::validate_submission( $enrollment_id, $course_id, $assessment_id );
		if ( is_wp_error( $enrollment ) ) {
			wp_die( esc_html( $enrollment->get_error_message() ) );
		}

		$answers = isset( $_POST['pf_answers'] ) && is_array( $_POST['pf_answers'] ) ? wp_unslash( $_POST['pf_answers'] ) : array();
		$result  = Parish_Formation_Assessment_Repository::submit( $enrollment, $assessment_id, $answers );
		$return_url = isset( $_POST['return_url'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_url'] ) ), home_url( '/' ) ) : home_url( '/' );
		if ( is_wp_error( $result ) ) {
			$return_url = add_query_arg( 'pf_assessment_error', $result->get_error_message(), $return_url );
		} else {
			Parish_Formation_Notifications::send_assessment_submission( $enrollment_id, $assessment_id, $result );
			Parish_Formation_Progress_Repository::sync_course_completion( $enrollment, Parish_Formation_Course_Repository::get_published_lessons( $course_id ) );
			$return_url = add_query_arg( 'pf_assessment_result', sanitize_key( $result->status ), $return_url );
		}
		wp_safe_redirect( $return_url );
		exit;
	}

	/** Handle an enhanced assessment submission over the REST API. */
	public static function submit_rest( WP_REST_Request $request ) {
		$enrollment_id = absint( $request->get_param( 'enrollment_id' ) );
		$course_id     = absint( $request->get_param( 'course_id' ) );
		$assessment_id = absint( $request->get_param( 'assessment_id' ) );
		$answers       = $request->get_param( 'answers' );
		$answers       = is_array( $answers ) ? $answers : array();
		$enrollment    = self::validate_submission( $enrollment_id, $course_id, $assessment_id );
		if ( is_wp_error( $enrollment ) ) {
			return $enrollment;
		}
		$result = Parish_Formation_Assessment_Repository::submit( $enrollment, $assessment_id, $answers );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		Parish_Formation_Notifications::send_assessment_submission( $enrollment_id, $assessment_id, $result );
		$lessons = Parish_Formation_Course_Repository::get_published_lessons( $course_id );
		Parish_Formation_Progress_Repository::sync_course_completion( $enrollment, $lessons );
		$progress = Parish_Formation_Progress_Repository::get_summary( $enrollment_id, $lessons, $course_id );
		$curriculum = Parish_Formation_Course_Repository::get_published_curriculum( $course_id );
		$next_item = null;
		foreach ( $curriculum as $index => $item ) {
			if ( $assessment_id === $item['post']->ID ) {
				$next_item = $curriculum[ $index + 1 ] ?? null;
				break;
			}
		}
		$base_url = esc_url_raw( $request->get_param( 'base_url' ) );
		$base_url = $base_url ? trailingslashit( $base_url ) : home_url( '/' );
		$course_slug = get_post_field( 'post_name', $course_id );
		if ( $next_item ) {
			$next_url = $base_url . 'course/' . rawurlencode( $course_slug ) . '/' . rawurlencode( $next_item['type'] ) . '/' . rawurlencode( $next_item['post']->post_name ) . '/';
		} else {
			$next_url = $base_url . 'course/' . rawurlencode( $course_slug ) . '/';
		}
		$acknowledgement_mode = Parish_Formation_Assessment_Settings::is_acknowledgement_mode( $assessment_id );
		$max_attempts = $acknowledgement_mode ? 1 : max( 1, absint( get_post_meta( $assessment_id, Parish_Formation_Assessment_Settings::MAX_ATTEMPTS_META_KEY, true ) ) );
		$question_feedback = Parish_Formation_Question_Feedback_Service::for_attempt( $result->id );
		return rest_ensure_response(
			array(
				'status'       => sanitize_key( $result->status ),
				'statusLabel'  => $acknowledgement_mode ? ( 'pending_review' === $result->status ? __( 'Submitted — awaiting review', 'parish-formation' ) : __( 'Submitted', 'parish-formation' ) ) : ucwords( str_replace( '_', ' ', $result->status ) ),
				'assessmentMode' => $acknowledgement_mode ? 'acknowledgement' : 'standard',
				'score'        => (float) $result->score_points,
				'maximum'      => (float) $result->max_points,
				'correct'      => absint( $result->correct_count ),
				'totalGraded'  => absint( $result->total_graded ),
				'scoreLabel'   => Parish_Formation_Assessment_Repository::format_score_summary( $result ),
				'attempt'      => absint( $result->attempt_number ),
				'maxAttempts'  => $max_attempts,
				'progress'     => $progress['percentage'],
				'nextUrl'      => esc_url_raw( $next_url ),
				'nextLabel'    => $next_item ? __( 'Continue to Next Section', 'parish-formation' ) : __( 'Finish Course', 'parish-formation' ),
				'questionFeedback' => $question_feedback,
			)
		);
	}

	/** Validate enrollment, access, and preceding curriculum gates. */
	public static function validate_submission( $enrollment_id, $course_id, $assessment_id ) {
		$enrollment = Parish_Formation_Enrollment_Repository::get_for_user_course( get_current_user_id(), $course_id );
		if ( ! $enrollment || absint( $enrollment->id ) !== absint( $enrollment_id ) ) {
			return new WP_Error( 'no_access', __( 'You do not have access to this enrollment.', 'parish-formation' ), array( 'status' => 403 ) );
		}
		$enrollment->user_id = get_current_user_id();
		if ( $enrollment->expires_at && strtotime( $enrollment->expires_at . ' UTC' ) < time() ) {
			return new WP_Error( 'expired', __( 'Your access to this course has expired.', 'parish-formation' ), array( 'status' => 403 ) );
		}
		$assessment = get_post( $assessment_id );
		if ( ! $assessment || Parish_Formation_Assessment_Post_Type::POST_TYPE !== $assessment->post_type || 'publish' !== $assessment->post_status || $course_id !== absint( get_post_meta( $assessment_id, Parish_Formation_Assessment_Settings::COURSE_META_KEY, true ) ) ) {
			return new WP_Error( 'invalid_assessment', __( 'This assessment is not available in your course.', 'parish-formation' ), array( 'status' => 404 ) );
		}
		$statuses = Parish_Formation_Progress_Repository::get_statuses( $enrollment_id );
		foreach ( Parish_Formation_Course_Repository::get_published_curriculum( $course_id ) as $item ) {
			if ( $assessment_id === $item['post']->ID ) {
				return $enrollment;
			}
			if ( 'lesson' === $item['type'] && ! in_array( $statuses[ $item['post']->ID ] ?? '', array( 'completed', 'skipped' ), true ) ) {
				return new WP_Error( 'locked', __( 'Complete the preceding lessons before submitting this assessment.', 'parish-formation' ), array( 'status' => 403 ) );
			}
			if ( 'assessment' === $item['type'] ) {
				$progression = get_post_meta( $item['post']->ID, Parish_Formation_Assessment_Settings::PROGRESSION_META_KEY, true );
				$attempt = Parish_Formation_Assessment_Repository::get_latest_attempt( $enrollment_id, $item['post']->ID );
				if ( 'no_gate' !== $progression && ( ! $attempt || ( 'submit_to_continue' !== $progression && ! (bool) $attempt->passed ) ) ) {
					return new WP_Error( 'locked', __( 'Complete the preceding assessment before continuing.', 'parish-formation' ), array( 'status' => 403 ) );
				}
			}
		}
		return new WP_Error( 'invalid_assessment', __( 'This assessment is not in the course curriculum.', 'parish-formation' ), array( 'status' => 404 ) );
	}
}
