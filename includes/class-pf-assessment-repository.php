<?php
/** Assessment attempt persistence and grading. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Parish_Formation_Assessment_Repository {
	/** Count current-course attempts awaiting staff review. */
	public static function get_pending_review_count() {
		global $wpdb;
		return absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}pf_assessment_attempts AS attempt INNER JOIN {$wpdb->prefix}pf_enrollments AS enrollment ON enrollment.id = attempt.enrollment_id WHERE attempt.status = 'pending_review' AND attempt.course_run = enrollment.current_run" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
	/** Build a learner-facing score summary that distinguishes completion responses. */
	public static function format_score_summary( $attempt ) {
		$completion_count = max( 0, count( self::get_attempt_answers( $attempt->id ) ) - absint( $attempt->total_graded ) );
		$summary = sprintf(
			__( 'Score: %1$s of %2$s points; %3$d of %4$d automatically graded questions correct.', 'parish-formation' ),
			$attempt->score_points,
			$attempt->max_points,
			absint( $attempt->correct_count ),
			absint( $attempt->total_graded )
		);
		if ( $completion_count ) {
			$summary .= ' ' . sprintf(
				_n( '%d completion-based response submitted.', '%d completion-based responses submitted.', $completion_count, 'parish-formation' ),
				$completion_count
			);
		}
		return $summary;
	}

	/** Get one immutable assessment attempt. */
	public static function get_attempt( $attempt_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_assessment_attempts WHERE id = %d LIMIT 1", absint( $attempt_id ) ) );
	}

	/** Get published internal questions in authored order. */
	public static function get_questions( $assessment_id ) {
		return get_posts(
			array(
				'post_type'      => Parish_Formation_Question_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_pf_question_order',
				'orderby'        => array( 'meta_value_num' => 'ASC', 'ID' => 'ASC' ),
				'meta_query'     => array( array( 'key' => '_pf_assessment_id', 'value' => absint( $assessment_id ), 'compare' => '=', 'type' => 'NUMERIC' ) ),
			)
		);
	}

	/** Get the most recent attempt. */
	public static function get_latest_attempt( $enrollment_id, $assessment_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT attempt.* FROM {$wpdb->prefix}pf_assessment_attempts AS attempt
				INNER JOIN {$wpdb->prefix}pf_enrollments AS enrollment ON enrollment.id = attempt.enrollment_id
				WHERE attempt.enrollment_id = %d AND attempt.assessment_id = %d AND attempt.course_run = enrollment.current_run
				ORDER BY attempt.attempt_number DESC LIMIT 1",
				absint( $enrollment_id ),
				absint( $assessment_id )
			)
		);
	}

	/** Get all attempts for an enrollment, newest first. */
	public static function get_attempts_for_enrollment( $enrollment_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_assessment_attempts WHERE enrollment_id = %d ORDER BY submitted_at DESC, id DESC", absint( $enrollment_id ) ) );
	}

	/** Get immutable answer snapshots for an attempt. */
	public static function get_attempt_answers( $attempt_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_assessment_answers WHERE attempt_id = %d ORDER BY id ASC", absint( $attempt_id ) ) );
	}

	/** Grade and save an immutable submitted attempt. */
	public static function submit( $enrollment, $assessment_id, $submitted_answers ) {
		global $wpdb;
		$questions     = self::get_questions( $assessment_id );
		$latest        = self::get_latest_attempt( $enrollment->id, $assessment_id );
		$acknowledgement_mode = Parish_Formation_Assessment_Settings::is_acknowledgement_mode( $assessment_id );
		$manual_approval = ! $acknowledgement_mode && (bool) get_post_meta( $assessment_id, Parish_Formation_Assessment_Settings::MANUAL_APPROVAL_META_KEY, true );
		$max_attempts  = $acknowledgement_mode ? 1 : max( 1, absint( get_post_meta( $assessment_id, Parish_Formation_Assessment_Settings::MAX_ATTEMPTS_META_KEY, true ) ) );
		$attempt_number = $latest ? absint( $latest->attempt_number ) + 1 : 1;
		if ( $latest && ( 'pending_review' === $latest->status || (bool) $latest->passed ) ) {
			return new WP_Error( 'attempt_closed', __( 'This assessment has already been submitted successfully.', 'parish-formation' ) );
		}
		if ( $attempt_number > $max_attempts && ( ! $latest || 'needs_resubmission' !== $latest->status ) ) {
			return new WP_Error( 'attempt_limit', __( 'No assessment attempts remain.', 'parish-formation' ) );
		}
		if ( ! $questions ) {
			return new WP_Error( 'no_questions', __( 'This assessment has no available questions.', 'parish-formation' ) );
		}

		$answer_rows = array();
		$score = 0;
		$maximum = 0;
		$correct_count = 0;
		$total_graded = 0;
		$needs_review = false;
		foreach ( $questions as $question ) {
			$response = $submitted_answers[ $question->ID ] ?? '';
			$grade    = Parish_Formation_Question_Grading_Service::grade( $question, $response );
			if ( ! $grade['valid'] ) {
				$message = $grade['message'] ?: __( 'This response is not valid.', 'parish-formation' );
				return new WP_Error( $grade['error_code'] ?: 'invalid_answer', sprintf( __( 'Question %1$d: %2$s', 'parish-formation' ), count( $answer_rows ) + 1, $message ) );
			}
			$config          = Parish_Formation_Question_Config::get( $question->ID );
			$score          += $grade['earned_points'];
			$maximum        += $grade['maximum_points'];
			$needs_review    = $needs_review || $grade['requires_review'];
			if ( null !== $grade['is_correct'] ) {
				++$total_graded;
				if ( $grade['is_correct'] ) { ++$correct_count; }
			}
			$answer_rows[] = array(
				'question' => $question,
				'answer' => $grade['stored_response'],
				'points' => $grade['maximum_points'],
				'awarded' => $grade['earned_points'],
				'is_correct' => $grade['is_correct'],
				'requires_review' => $grade['requires_review'],
				'type' => $config['type'],
				'config' => $config,
			);
		}

		$rule  = sanitize_key( get_post_meta( $assessment_id, Parish_Formation_Assessment_Settings::PASSING_RULE_META_KEY, true ) );
		$rule  = in_array( $rule, array( 'percentage', 'correct_count', 'points' ), true ) ? $rule : 'percentage';
		$value = metadata_exists( 'post', $assessment_id, Parish_Formation_Assessment_Settings::PASSING_VALUE_META_KEY )
			? max( 0, (float) get_post_meta( $assessment_id, Parish_Formation_Assessment_Settings::PASSING_VALUE_META_KEY, true ) )
			: 100;
		if ( $acknowledgement_mode ) {
			$rule  = 'submission';
			$value = 1;
		}
		if ( ! $acknowledgement_mode ) {
			$available = 'correct_count' === $rule ? $total_graded : ( 'points' === $rule ? $maximum : 100 );
			if ( $value > $available ) {
				return new WP_Error(
					'invalid_passing_configuration',
					sprintf(
						__( 'This assessment requires %1$s %2$s to pass, but only %3$s are available. Please ask a course administrator to correct the passing value.', 'parish-formation' ),
						$value,
						'correct_count' === $rule ? __( 'correct answers', 'parish-formation' ) : ( 'points' === $rule ? __( 'points', 'parish-formation' ) : __( 'percent', 'parish-formation' ) ),
						$available
					)
				);
			}
		}
		$metric = 'correct_count' === $rule ? $correct_count : ( 'points' === $rule ? $score : ( $maximum > 0 ? ( $score / $maximum ) * 100 : 100 ) );
		$needs_review = $needs_review || $manual_approval;
		$passed = ! $needs_review && ( $acknowledgement_mode || $metric >= $value );
		$status = $needs_review ? 'pending_review' : ( $passed ? 'passed' : 'failed' );
		$now = current_time( 'mysql', true );

		$wpdb->query( 'START TRANSACTION' );
		$saved = $wpdb->insert(
			$wpdb->prefix . 'pf_assessment_attempts',
			array( 'enrollment_id' => $enrollment->id, 'user_id' => $enrollment->user_id, 'course_id' => $enrollment->course_id, 'assessment_id' => $assessment_id, 'course_run' => max( 1, absint( $enrollment->current_run ) ), 'attempt_number' => $attempt_number, 'status' => $status, 'score_points' => $score, 'max_points' => $maximum, 'correct_count' => $correct_count, 'total_graded' => $total_graded, 'passing_rule' => $rule, 'passing_value' => $value, 'passed' => $needs_review ? null : ( $passed ? 1 : 0 ), 'submitted_at' => $now, 'created_at' => $now ),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%f', '%f', '%d', '%d', '%s', '%f', '%d', '%s', '%s' )
		);
		if ( false === $saved ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'database_error', __( 'The assessment attempt could not be saved.', 'parish-formation' ) );
		}
		$attempt_id = $wpdb->insert_id;
		foreach ( $answer_rows as $row ) {
			$snapshot = wp_json_encode( Parish_Formation_Question_Snapshot::create( $row['question'], $row['config'] ) );
			$saved = $wpdb->insert( $wpdb->prefix . 'pf_assessment_answers', array( 'attempt_id' => $attempt_id, 'question_id' => $row['question']->ID, 'question_snapshot' => $snapshot, 'answer' => $row['answer'], 'points_awarded' => $row['awarded'], 'automatic_points' => $row['awarded'], 'is_correct' => $row['is_correct'], 'requires_review' => $row['requires_review'] ? 1 : 0, 'review_status' => $row['requires_review'] ? 'pending_review' : 'not_required', 'created_at' => $now ), array( '%d', '%d', '%s', '%s', '%f', '%f', '%d', '%d', '%s', '%s' ) );
			if ( false === $saved ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'database_error', __( 'The assessment answers could not be saved.', 'parish-formation' ) );
			}
		}
		$wpdb->query( 'COMMIT' );
		return self::get_latest_attempt( $enrollment->id, $assessment_id );
	}

	/** Finalize a pending manual review with audited staff fields. */
	public static function review( $attempt_id, $enrollment_id, $decision, $manual_points, $note, $reviewer_id, $answer_feedback = array(), $answer_notes = array(), $learner_feedback = '' ) {
		global $wpdb;
		$attempt = $wpdb->get_row( $wpdb->prepare( "SELECT attempt.* FROM {$wpdb->prefix}pf_assessment_attempts AS attempt INNER JOIN {$wpdb->prefix}pf_enrollments AS enrollment ON enrollment.id = attempt.enrollment_id WHERE attempt.id = %d AND attempt.enrollment_id = %d AND attempt.course_run = enrollment.current_run", absint( $attempt_id ), absint( $enrollment_id ) ) );
		if ( ! $attempt || 'pending_review' !== $attempt->status ) {
			return new WP_Error( 'invalid_attempt', __( 'That pending assessment attempt could not be found.', 'parish-formation' ) );
		}
		if ( ! in_array( $decision, array( 'passed', 'failed', 'needs_resubmission' ), true ) ) {
			return new WP_Error( 'invalid_decision', __( 'Select Pass or Fail for this review.', 'parish-formation' ) );
		}
		$answers = self::get_attempt_answers( $attempt_id );
		$score = 0;
		$maximum = (float) $attempt->max_points;
		$wpdb->query( 'START TRANSACTION' );
		foreach ( $answers as $answer ) {
			$snapshot = json_decode( $answer->question_snapshot, true );
			$question_points = max( 1, absint( $snapshot['points'] ?? 1 ) );
			$awarded = (bool) $answer->requires_review
				? min( $question_points, max( 0, isset( $manual_points[ $answer->id ] ) ? (float) $manual_points[ $answer->id ] : 0 ) )
				: (float) $answer->points_awarded;
			$score += $awarded;
			if ( $answer->requires_review ) {
				$answer_data = array(
					'points_awarded' => $awarded,
					'review_status' => 'needs_resubmission' === $decision ? 'needs_resubmission' : 'reviewed',
					'reviewer_user_id' => absint( $reviewer_id ),
					'reviewed_at' => current_time( 'mysql', true ),
					'private_note' => sanitize_textarea_field( $answer_notes[ $answer->id ] ?? '' ),
					'learner_feedback' => sanitize_textarea_field( $answer_feedback[ $answer->id ] ?? '' ),
				);
				$saved = $wpdb->update( $wpdb->prefix . 'pf_assessment_answers', $answer_data, array( 'id' => $answer->id ), array( '%f', '%s', '%d', '%s', '%s', '%s' ), array( '%d' ) );
				if ( false === $saved ) {
					$wpdb->query( 'ROLLBACK' );
					return new WP_Error( 'database_error', __( 'The manual question score could not be saved.', 'parish-formation' ) );
				}
			}
		}
		$now = current_time( 'mysql', true );
		$saved = $wpdb->update(
			$wpdb->prefix . 'pf_assessment_attempts',
			array( 'status' => $decision, 'score_points' => $score, 'max_points' => $maximum, 'passed' => 'needs_resubmission' === $decision ? null : ( 'passed' === $decision ? 1 : 0 ), 'reviewed_by' => absint( $reviewer_id ), 'reviewed_at' => $now, 'review_note' => sanitize_textarea_field( $note ), 'learner_feedback' => sanitize_textarea_field( $learner_feedback ) ),
			array( 'id' => absint( $attempt_id ) ),
			array( '%s', '%f', '%f', '%d', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $saved ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'database_error', __( 'The assessment review could not be saved.', 'parish-formation' ) );
		}
		$wpdb->query( 'COMMIT' );
		Parish_Formation_Security_Event_Repository::record( 'assessment_reviewed', 'assessment_attempt', $attempt_id, array( 'decision' => $decision, 'score_points' => $score, 'max_points' => $maximum ), $reviewer_id, $attempt->user_id, $attempt->course_id );
		return true;
	}
}
