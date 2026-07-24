<?php
/** Assessment attempt persistence and grading. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Parish_Formation_Assessment_Repository {

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
				"SELECT * FROM {$wpdb->prefix}pf_assessment_attempts WHERE enrollment_id = %d AND assessment_id = %d ORDER BY attempt_number DESC LIMIT 1",
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
		$max_attempts  = max( 1, absint( get_post_meta( $assessment_id, Parish_Formation_Assessment_Settings::MAX_ATTEMPTS_META_KEY, true ) ) );
		$attempt_number = $latest ? absint( $latest->attempt_number ) + 1 : 1;
		if ( $latest && ( 'pending_review' === $latest->status || (bool) $latest->passed ) ) {
			return new WP_Error( 'attempt_closed', __( 'This assessment has already been submitted successfully.', 'parish-formation' ) );
		}
		if ( $attempt_number > $max_attempts ) {
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
			$type     = sanitize_key( get_post_meta( $question->ID, '_pf_question_type', true ) );
			$required = ! metadata_exists( 'post', $question->ID, '_pf_question_required' ) || (bool) get_post_meta( $question->ID, '_pf_question_required', true );
			$answer   = isset( $submitted_answers[ $question->ID ] ) ? sanitize_textarea_field( $submitted_answers[ $question->ID ] ) : '';
			if ( $required && '' === trim( $answer ) ) {
				return new WP_Error( 'required_answer', sprintf( __( 'Please answer Question %d.', 'parish-formation' ), count( $answer_rows ) + 1 ) );
			}
			$points = max( 1, absint( get_post_meta( $question->ID, '_pf_question_points', true ) ) );
			$is_correct = null;
			$awarded = 0;
			$requires_review = 'reflection' === $type;
			if ( $requires_review ) {
				$maximum += $points;
			}
			if ( in_array( $type, array( 'multiple_choice', 'true_false' ), true ) ) {
				++$total_graded;
				$maximum += $points;
				$correct = strtolower( sanitize_text_field( get_post_meta( $question->ID, '_pf_question_correct_answer', true ) ) );
				if ( 'multiple_choice' === $type && ! ctype_digit( $correct ) ) {
					$options = get_post_meta( $question->ID, '_pf_question_options', true );
					$options = is_array( $options ) ? $options : array();
					foreach ( $options as $option_index => $option ) {
						if ( strtolower( sanitize_text_field( $option ) ) === $correct ) {
							$correct = (string) ( $option_index + 1 );
							break;
						}
					}
				}
				$is_correct = '' !== $answer && strtolower( $answer ) === $correct;
				if ( $is_correct ) {
					++$correct_count;
					$score += $points;
					$awarded = $points;
				}
			}
			$needs_review = $needs_review || $requires_review;
			$answer_rows[] = array(
				'question' => $question,
				'answer' => $answer,
				'points' => $points,
				'awarded' => $awarded,
				'is_correct' => $is_correct,
				'requires_review' => $requires_review,
				'type' => $type,
			);
		}

		$rule  = sanitize_key( get_post_meta( $assessment_id, Parish_Formation_Assessment_Settings::PASSING_RULE_META_KEY, true ) );
		$rule  = in_array( $rule, array( 'percentage', 'correct_count', 'points' ), true ) ? $rule : 'percentage';
		$value = metadata_exists( 'post', $assessment_id, Parish_Formation_Assessment_Settings::PASSING_VALUE_META_KEY )
			? max( 0, (float) get_post_meta( $assessment_id, Parish_Formation_Assessment_Settings::PASSING_VALUE_META_KEY, true ) )
			: 100;
		$metric = 'correct_count' === $rule ? $correct_count : ( 'points' === $rule ? $score : ( $maximum > 0 ? ( $score / $maximum ) * 100 : 100 ) );
		$passed = ! $needs_review && $metric >= $value;
		$status = $needs_review ? 'pending_review' : ( $passed ? 'passed' : 'failed' );
		$now = current_time( 'mysql', true );

		$wpdb->query( 'START TRANSACTION' );
		$saved = $wpdb->insert(
			$wpdb->prefix . 'pf_assessment_attempts',
			array( 'enrollment_id' => $enrollment->id, 'user_id' => $enrollment->user_id, 'course_id' => $enrollment->course_id, 'assessment_id' => $assessment_id, 'attempt_number' => $attempt_number, 'status' => $status, 'score_points' => $score, 'max_points' => $maximum, 'correct_count' => $correct_count, 'total_graded' => $total_graded, 'passing_rule' => $rule, 'passing_value' => $value, 'passed' => $needs_review ? null : ( $passed ? 1 : 0 ), 'submitted_at' => $now, 'created_at' => $now ),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%f', '%f', '%d', '%d', '%s', '%f', '%d', '%s', '%s' )
		);
		if ( false === $saved ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'database_error', __( 'The assessment attempt could not be saved.', 'parish-formation' ) );
		}
		$attempt_id = $wpdb->insert_id;
		foreach ( $answer_rows as $row ) {
			$snapshot = wp_json_encode( array( 'prompt' => $row['question']->post_content, 'type' => $row['type'], 'options' => get_post_meta( $row['question']->ID, '_pf_question_options', true ), 'points' => $row['points'], 'correct_answer' => get_post_meta( $row['question']->ID, '_pf_question_correct_answer', true ) ) );
			$saved = $wpdb->insert( $wpdb->prefix . 'pf_assessment_answers', array( 'attempt_id' => $attempt_id, 'question_id' => $row['question']->ID, 'question_snapshot' => $snapshot, 'answer' => $row['answer'], 'points_awarded' => $row['awarded'], 'is_correct' => $row['is_correct'], 'requires_review' => $row['requires_review'] ? 1 : 0, 'created_at' => $now ), array( '%d', '%d', '%s', '%s', '%f', '%d', '%d', '%s' ) );
			if ( false === $saved ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'database_error', __( 'The assessment answers could not be saved.', 'parish-formation' ) );
			}
		}
		$wpdb->query( 'COMMIT' );
		return self::get_latest_attempt( $enrollment->id, $assessment_id );
	}

	/** Finalize a pending manual review with audited staff fields. */
	public static function review( $attempt_id, $enrollment_id, $decision, $manual_points, $note, $reviewer_id ) {
		global $wpdb;
		$attempt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_assessment_attempts WHERE id = %d AND enrollment_id = %d", absint( $attempt_id ), absint( $enrollment_id ) ) );
		if ( ! $attempt || 'pending_review' !== $attempt->status ) {
			return new WP_Error( 'invalid_attempt', __( 'That pending assessment attempt could not be found.', 'parish-formation' ) );
		}
		if ( ! in_array( $decision, array( 'passed', 'failed' ), true ) ) {
			return new WP_Error( 'invalid_decision', __( 'Select Pass or Fail for this review.', 'parish-formation' ) );
		}
		$answers = self::get_attempt_answers( $attempt_id );
		$score = 0;
		$maximum = 0;
		$wpdb->query( 'START TRANSACTION' );
		foreach ( $answers as $answer ) {
			$snapshot = json_decode( $answer->question_snapshot, true );
			$question_points = max( 1, absint( $snapshot['points'] ?? 1 ) );
			if ( in_array( $snapshot['type'] ?? '', array( 'multiple_choice', 'true_false', 'reflection' ), true ) ) {
				$maximum += $question_points;
			}
			$awarded = (bool) $answer->requires_review
				? min( $question_points, max( 0, isset( $manual_points[ $answer->id ] ) ? (float) $manual_points[ $answer->id ] : 0 ) )
				: (float) $answer->points_awarded;
			$score += $awarded;
			if ( $answer->requires_review ) {
				$saved = $wpdb->update( $wpdb->prefix . 'pf_assessment_answers', array( 'points_awarded' => $awarded ), array( 'id' => $answer->id ), array( '%f' ), array( '%d' ) );
				if ( false === $saved ) {
					$wpdb->query( 'ROLLBACK' );
					return new WP_Error( 'database_error', __( 'The manual question score could not be saved.', 'parish-formation' ) );
				}
			}
		}
		$now = current_time( 'mysql', true );
		$saved = $wpdb->update(
			$wpdb->prefix . 'pf_assessment_attempts',
			array( 'status' => $decision, 'score_points' => $score, 'max_points' => $maximum, 'passed' => 'passed' === $decision ? 1 : 0, 'reviewed_by' => absint( $reviewer_id ), 'reviewed_at' => $now, 'review_note' => sanitize_textarea_field( $note ) ),
			array( 'id' => absint( $attempt_id ) ),
			array( '%s', '%f', '%f', '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $saved ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'database_error', __( 'The assessment review could not be saved.', 'parish-formation' ) );
		}
		$wpdb->query( 'COMMIT' );
		return true;
	}
}
