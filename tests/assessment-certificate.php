<?php
/** Isolated assessment grading and certificate lifecycle integration tests. */

$test_url = getenv( 'PF_TEST_URL' ) ?: 'http://parish-formation.test';
$url      = parse_url( $test_url );
$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? ( $url['scheme'] ?? 'http' );
$_SERVER['HTTP_HOST']      = $_SERVER['HTTP_HOST'] ?? ( $url['host'] ?? 'parish-formation.test' );

require_once dirname( __DIR__, 4 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$failures      = array();
$checks        = 0;
$created_posts = array();
$user_id       = 0;
$staff_id      = 0;
$enrollment_id = 0;
global $wpdb;
$security_baseline = absint( $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}pf_security_events" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$assert = static function ( $condition, $message ) use ( &$failures, &$checks ) {
	++$checks;
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

try {
	$suffix = strtolower( wp_generate_password( 8, false, false ) );
	$user_id = wp_insert_user( array( 'user_login' => 'pf_grade_participant_' . $suffix, 'user_email' => 'pf_grade_participant_' . $suffix . '@example.invalid', 'user_pass' => wp_generate_password( 24 ), 'display_name' => 'PF Test Participant', 'role' => 'parish_formation_participant' ) );
	$staff_id = wp_insert_user( array( 'user_login' => 'pf_grade_staff_' . $suffix, 'user_email' => 'pf_grade_staff_' . $suffix . '@example.invalid', 'user_pass' => wp_generate_password( 24 ), 'role' => 'parish_formation_administrator' ) );
	if ( is_wp_error( $user_id ) || is_wp_error( $staff_id ) ) {
		throw new RuntimeException( 'Test users could not be created.' );
	}

	$course_id = wp_insert_post( array( 'post_type' => Parish_Formation_Course_Post_Type::POST_TYPE, 'post_status' => 'publish', 'post_title' => 'PF grading test course ' . $suffix ) );
	if ( is_wp_error( $course_id ) || ! $course_id ) {
		throw new RuntimeException( 'Test course could not be created.' );
	}
	$created_posts[] = $course_id;
	update_post_meta( $course_id, Parish_Formation_Course_Settings::CERTIFICATE_ENABLED_META_KEY, 1 );
	update_post_meta( $course_id, Parish_Formation_Course_Settings::CERTIFICATE_TITLE_META_KEY, 'Test Certificate' );
	update_post_meta( $course_id, Parish_Formation_Course_Settings::CERTIFICATE_ISSUER_META_KEY, 'Test Parish' );
	update_post_meta( $course_id, Parish_Formation_Course_Settings::NOTIFICATION_DISABLED_META_KEY, 1 );

	$enrollment_id = Parish_Formation_Enrollment_Repository::create_manual( $user_id, $course_id, $staff_id );
	if ( is_wp_error( $enrollment_id ) ) {
		throw new RuntimeException( $enrollment_id->get_error_message() );
	}
	$enrollment = Parish_Formation_Enrollment_Repository::get_details( $enrollment_id );

	$create_assessment = static function ( $title, $rule, $value, $attempts, $progression = 'pass_to_continue' ) use ( $course_id, &$created_posts, $suffix ) {
		$id = wp_insert_post( array( 'post_type' => Parish_Formation_Assessment_Post_Type::POST_TYPE, 'post_status' => 'publish', 'post_title' => $title . ' ' . $suffix ) );
		if ( is_wp_error( $id ) || ! $id ) {
			throw new RuntimeException( 'Test assessment could not be created.' );
		}
		$created_posts[] = $id;
		update_post_meta( $id, Parish_Formation_Assessment_Settings::COURSE_META_KEY, $course_id );
		update_post_meta( $id, Parish_Formation_Assessment_Settings::PASSING_RULE_META_KEY, $rule );
		update_post_meta( $id, Parish_Formation_Assessment_Settings::PASSING_VALUE_META_KEY, $value );
		update_post_meta( $id, Parish_Formation_Assessment_Settings::MAX_ATTEMPTS_META_KEY, $attempts );
		update_post_meta( $id, Parish_Formation_Assessment_Settings::PROGRESSION_META_KEY, $progression );
		return $id;
	};
	$create_question = static function ( $assessment_id, $title, $type, $correct, $points = 1, $order = 1 ) use ( &$created_posts, $suffix ) {
		$id = wp_insert_post( array( 'post_type' => Parish_Formation_Question_Post_Type::POST_TYPE, 'post_status' => 'publish', 'post_title' => $title . ' ' . $suffix, 'post_content' => $title ) );
		if ( is_wp_error( $id ) || ! $id ) {
			throw new RuntimeException( 'Test question could not be created.' );
		}
		$created_posts[] = $id;
		update_post_meta( $id, '_pf_assessment_id', $assessment_id );
		update_post_meta( $id, '_pf_question_type', $type );
		update_post_meta( $id, '_pf_question_required', 1 );
		update_post_meta( $id, '_pf_question_points', $points );
		update_post_meta( $id, '_pf_question_order', $order );
		if ( 'multiple_choice' === $type ) {
			update_post_meta( $id, '_pf_question_options', array( 'Correct', 'Incorrect' ) );
		}
		if ( null !== $correct ) {
			update_post_meta( $id, '_pf_question_correct_answer', $correct );
		}
		return $id;
	};

	$graded_id = $create_assessment( 'Percentage assessment', 'percentage', 50, 2 );
	$q1 = $create_question( $graded_id, 'Question one', 'multiple_choice', '1', 1, 1 );
	$q2 = $create_question( $graded_id, 'Question two', 'multiple_choice', '1', 1, 2 );
	$missing = Parish_Formation_Assessment_Repository::submit( $enrollment, $graded_id, array( $q1 => '2' ) );
	$assert( is_wp_error( $missing ) && 'required_answer' === $missing->get_error_code(), 'Required unanswered question was accepted.' );
	$failed = Parish_Formation_Assessment_Repository::submit( $enrollment, $graded_id, array( $q1 => '2', $q2 => '2' ) );
	$assert( ! is_wp_error( $failed ) && 'failed' === $failed->status && 0 === (int) $failed->correct_count && 1 === (int) $failed->attempt_number, 'Incorrect percentage attempt was not failed.' );
	$passed = Parish_Formation_Assessment_Repository::submit( $enrollment, $graded_id, array( $q1 => 'choice-1', $q2 => '2' ) );
	$assert( ! is_wp_error( $passed ) && 'passed' === $passed->status && 1 === (int) $passed->correct_count && 2 === (int) $passed->attempt_number, '50 percent attempt did not pass.' );
	$closed = Parish_Formation_Assessment_Repository::submit( $enrollment, $graded_id, array( $q1 => '1', $q2 => '1' ) );
	$assert( is_wp_error( $closed ) && 'attempt_closed' === $closed->get_error_code(), 'Passed assessment accepted another attempt.' );
	$answers = Parish_Formation_Assessment_Repository::get_attempt_answers( $passed->id );
	$assert( 2 === count( $answers ) && ! empty( $answers[0]->question_snapshot ), 'Immutable answer snapshots were not saved.' );

	$limited_id = $create_assessment( 'Limited assessment', 'correct_count', 1, 1, 'no_gate' );
	$lq = $create_question( $limited_id, 'Limited question', 'true_false', 'true' );
	$limited_fail = Parish_Formation_Assessment_Repository::submit( $enrollment, $limited_id, array( $lq => 'false' ) );
	$assert( ! is_wp_error( $limited_fail ) && 'failed' === $limited_fail->status, 'Incorrect correct-count assessment did not fail.' );
	$limit = Parish_Formation_Assessment_Repository::submit( $enrollment, $limited_id, array( $lq => 'true' ) );
	$assert( is_wp_error( $limit ) && 'attempt_limit' === $limit->get_error_code(), 'Single-attempt limit was not enforced.' );

	$approval_id = $create_assessment( 'Staff approval assessment', 'percentage', 100, 1, 'pass_to_continue' );
	update_post_meta( $approval_id, Parish_Formation_Assessment_Settings::MANUAL_APPROVAL_META_KEY, 1 );
	$approval_q = $create_question( $approval_id, 'Automatically correct but staff approved', 'true_false', 'true' );
	$approval_pending = Parish_Formation_Assessment_Repository::submit( $enrollment, $approval_id, array( $approval_q => 'true' ) );
	$assert( ! is_wp_error( $approval_pending ) && 'pending_review' === $approval_pending->status && null === $approval_pending->passed && 1.0 === (float) $approval_pending->score_points, 'Assessment-wide staff approval did not hold an automatically correct submission for review.' );
	$approval_reviewed = Parish_Formation_Assessment_Repository::review( $approval_pending->id, $enrollment_id, 'passed', array(), '', $staff_id, array(), array(), 'Approved after staff verification.' );
	$approval_attempt = Parish_Formation_Assessment_Repository::get_attempt( $approval_pending->id );
	$assert( true === $approval_reviewed && 'passed' === $approval_attempt->status && 1 === (int) $approval_attempt->passed, 'Staff could not approve an assessment-wide manual-approval submission.' );

	$phase_two_id = $create_assessment( 'Structured response assessment', 'points', 5, 1, 'no_gate' );
	$multi_q = $create_question( $phase_two_id, 'Select both correct choices', 'multiple_select', null, 4, 1 );
	$multi_config = Parish_Formation_Question_Config::get( $multi_q );
	$multi_config['choices'] = array(
		array( 'id' => 'choice-a', 'label' => 'Choice A', 'correct' => true, 'order' => 1 ),
		array( 'id' => 'choice-b', 'label' => 'Choice B', 'correct' => true, 'order' => 2 ),
		array( 'id' => 'choice-c', 'label' => 'Choice C', 'correct' => false, 'order' => 3 ),
	);
	$multi_config['type_config'] = array( 'grading_mode' => 'partial' );
	update_post_meta( $multi_q, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $multi_config, 'multiple_select' ) );
	$short_q = $create_question( $phase_two_id, 'Enter the accepted response', 'short_answer', null, 3, 2 );
	$short_config = Parish_Formation_Question_Config::get( $short_q );
	$short_config['type_config'] = array( 'accepted_answers' => array( 'Accepted' ), 'case_sensitive' => false, 'trim_spaces' => true, 'normalize_spaces' => false, 'ignore_punctuation' => false, 'match_mode' => 'exact' );
	update_post_meta( $short_q, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $short_config, 'short_answer' ) );
	$structured = Parish_Formation_Assessment_Repository::submit( $enrollment, $phase_two_id, array( $multi_q => array( 'choice-a' ), $short_q => '  ACCEPTED  ' ) );
	$assert( ! is_wp_error( $structured ) && 'passed' === $structured->status && 5.0 === (float) $structured->score_points && 7.0 === (float) $structured->max_points, 'Structured Multiple Select and Short Answer submission was not graded correctly: ' . ( is_wp_error( $structured ) ? $structured->get_error_message() : sprintf( 'status=%s score=%s max=%s', $structured->status, $structured->score_points, $structured->max_points ) ) );
	$structured_answers = Parish_Formation_Assessment_Repository::get_attempt_answers( $structured->id );
	$assert( 2 === count( $structured_answers ) && array( 'choice-a' ) === json_decode( $structured_answers[0]->answer, true ) && '  ACCEPTED  ' === $structured_answers[1]->answer, 'Structured or original learner responses were not stored correctly.' );
	$structured_snapshot = json_decode( $structured_answers[0]->question_snapshot, true );
	$assert( 'multiple_select' === $structured_snapshot['type'] && 'choice-a' === $structured_snapshot['choices'][0]['id'], 'Structured question snapshot lost stable choice IDs.' );
	$impossible_id = $create_assessment( 'Impossible threshold assessment', 'points', 2, 2, 'no_gate' );
	$impossible_q = $create_question( $impossible_id, 'One point cannot satisfy two', 'true_false', 'true', 1, 1 );
	$impossible = Parish_Formation_Assessment_Repository::submit( $enrollment, $impossible_id, array( $impossible_q => 'true' ) );
	$assert( is_wp_error( $impossible ) && 'invalid_passing_configuration' === $impossible->get_error_code() && null === Parish_Formation_Assessment_Repository::get_latest_attempt( $enrollment->id, $impossible_id ), 'Impossible passing threshold consumed an attempt instead of returning a configuration error.' );
	$fill_id = $create_assessment( 'Fill blank assessment', 'points', 2, 1, 'no_gate' );
	$fill_q = $create_question( $fill_id, 'The sacrament of [blank] uses [blank].', 'fill_blank', null, 4, 1 );
	$fill_config = Parish_Formation_Question_Config::get( $fill_q );
	$fill_config['type_config'] = array( 'point_mode' => 'equal', 'blanks' => array(
		array( 'id' => 'sacrament', 'accepted_answers' => array( 'Baptism' ), 'case_sensitive' => false, 'match_mode' => 'normalized', 'points' => 1 ),
		array( 'id' => 'matter', 'accepted_answers' => array( 'water' ), 'case_sensitive' => false, 'match_mode' => 'normalized', 'points' => 1 ),
	) );
	update_post_meta( $fill_q, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $fill_config, 'fill_blank' ) );
	$fill_attempt = Parish_Formation_Assessment_Repository::submit( $enrollment, $fill_id, array( $fill_q => array( 'sacrament' => '  BAPTISM ', 'matter' => 'oil' ) ) );
	$assert( ! is_wp_error( $fill_attempt ) && 'passed' === $fill_attempt->status && 2.0 === (float) $fill_attempt->score_points && 4.0 === (float) $fill_attempt->max_points, 'Fill in the Blank repository lifecycle did not preserve partial credit.' );
	$fill_answers = Parish_Formation_Assessment_Repository::get_attempt_answers( $fill_attempt->id );
	$assert( array( 'sacrament' => '  BAPTISM ', 'matter' => 'oil' ) === json_decode( $fill_answers[0]->answer, true ), 'Fill in the Blank responses were not stored separately by stable blank ID.' );

	$review_id = $create_assessment( 'Manual assessment', 'points', 1, 1, 'submit_to_continue' );
	$rq = $create_question( $review_id, 'Reflection question', 'reflection', null, 3 );
	$pending = Parish_Formation_Assessment_Repository::submit( $enrollment, $review_id, array( $rq => 'A thoughtful response.' ) );
	$assert( ! is_wp_error( $pending ) && 'pending_review' === $pending->status && null === $pending->passed, 'Reflection response was not held for review.' );
	$pending_answers = Parish_Formation_Assessment_Repository::get_attempt_answers( $pending->id );
	$invalid_review = Parish_Formation_Assessment_Repository::review( $pending->id, $enrollment_id, 'maybe', array(), '', $staff_id );
	$assert( is_wp_error( $invalid_review ) && 'invalid_decision' === $invalid_review->get_error_code(), 'Invalid review decision was accepted.' );
	$reviewed = Parish_Formation_Assessment_Repository::review( $pending->id, $enrollment_id, 'passed', array( $pending_answers[0]->id => 3 ), 'Approved for testing.', $staff_id, array( $pending_answers[0]->id => 'A thoughtful reflection.' ), array( $pending_answers[0]->id => 'Private rubric note.' ), 'Thank you for completing this reflection.' );
	$assert( true === $reviewed, 'Manual review could not be completed.' );
	$reviewed_attempt = Parish_Formation_Assessment_Repository::get_attempt( $pending->id );
	$assert( 'passed' === $reviewed_attempt->status && 1 === (int) $reviewed_attempt->passed && 3.0 === (float) $reviewed_attempt->score_points && $staff_id === (int) $reviewed_attempt->reviewed_by, 'Manual review audit fields are incorrect.' );
	$reviewed_answer = Parish_Formation_Assessment_Repository::get_attempt_answers( $pending->id )[0];
	$assert( 'reviewed' === $reviewed_answer->review_status && $staff_id === (int) $reviewed_answer->reviewer_user_id && 'A thoughtful reflection.' === $reviewed_answer->learner_feedback && 'Private rubric note.' === $reviewed_answer->private_note, 'Per-response review feedback or audit fields were not saved.' );
	$assert( 'Thank you for completing this reflection.' === $reviewed_attempt->learner_feedback, 'Overall learner-visible review feedback was not saved.' );

	$resubmit_id = $create_assessment( 'Resubmission assessment', 'points', 1, 1, 'no_gate' );
	$resubmit_q = $create_question( $resubmit_id, 'Revise this response', 'reflection', null, 1 );
	$resubmit_pending = Parish_Formation_Assessment_Repository::submit( $enrollment, $resubmit_id, array( $resubmit_q => 'First response.' ) );
	$resubmit_answer = Parish_Formation_Assessment_Repository::get_attempt_answers( $resubmit_pending->id )[0];
	$resubmit_review = Parish_Formation_Assessment_Repository::review( $resubmit_pending->id, $enrollment_id, 'needs_resubmission', array( $resubmit_answer->id => 0 ), '', $staff_id, array( $resubmit_answer->id => 'Please add more detail.' ) );
	$assert( true === $resubmit_review && 'needs_resubmission' === Parish_Formation_Assessment_Repository::get_attempt( $resubmit_pending->id )->status, 'Staff could not request assessment resubmission.' );
	$resubmitted = Parish_Formation_Assessment_Repository::submit( $enrollment, $resubmit_id, array( $resubmit_q => 'A revised response with more detail.' ) );
	$assert( ! is_wp_error( $resubmitted ) && 2 === (int) $resubmitted->attempt_number && 'pending_review' === $resubmitted->status, 'Requested resubmission did not override the normal attempt limit.' );

	$assert( ! Parish_Formation_Certificate_Repository::is_eligible( $enrollment ), 'Incomplete enrollment was certificate-eligible.' );
	global $wpdb;
	$completed_at = current_time( 'mysql', true );
	$wpdb->update( $wpdb->prefix . 'pf_enrollments', array( 'status' => 'completed', 'completed_at' => $completed_at, 'updated_at' => $completed_at ), array( 'id' => $enrollment_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
	$enrollment = Parish_Formation_Enrollment_Repository::get_details( $enrollment_id );
	$assert( Parish_Formation_Certificate_Repository::is_eligible( $enrollment ), 'Completed enrollment with passed gate is not certificate-eligible.' );
	$certificate = Parish_Formation_Certificate_Repository::maybe_issue( $enrollment, $staff_id );
	$assert( is_object( $certificate ) && 'issued' === $certificate->status && 1 === (int) $certificate->issue_number && 20 === strlen( $certificate->verification_code ), 'Certificate was not issued correctly.' );
	$same_certificate = Parish_Formation_Certificate_Repository::maybe_issue( $enrollment, $staff_id );
	$assert( (int) $certificate->id === (int) $same_certificate->id, 'Duplicate certificate was issued for the same course run.' );
	$assert( (int) $certificate->id === (int) Parish_Formation_Certificate_Repository::get_by_verification_code( strtolower( $certificate->verification_code ) )->id, 'Verification-code lookup failed.' );
	$reason_required = Parish_Formation_Certificate_Repository::revoke( $certificate->id, '', $staff_id );
	$assert( is_wp_error( $reason_required ) && 'certificate_reason_required' === $reason_required->get_error_code(), 'Certificate was revoked without a reason.' );
	$assert( true === Parish_Formation_Certificate_Repository::revoke( $certificate->id, 'Automated lifecycle test', $staff_id ), 'Certificate revocation failed.' );
	$revoked = Parish_Formation_Certificate_Repository::get_by_id( $certificate->id );
	$assert( 'revoked' === $revoked->status && $staff_id === (int) $revoked->revoked_by && 'Automated lifecycle test' === $revoked->revocation_reason, 'Certificate revocation audit fields are incorrect.' );
	$replacement = Parish_Formation_Certificate_Repository::reissue( $certificate->id, $staff_id );
	$assert( is_object( $replacement ) && 'issued' === $replacement->status && 2 === (int) $replacement->issue_number && (int) $certificate->id === (int) $replacement->reissue_of, 'Certificate reissue history is incorrect.' );
	$assert( $replacement->verification_code !== $certificate->verification_code, 'Replacement certificate reused the verification code.' );
	$second_reissue = Parish_Formation_Certificate_Repository::reissue( $certificate->id, $staff_id );
	$assert( is_wp_error( $second_reissue ) && 'certificate_replacement_exists' === $second_reissue->get_error_code(), 'A second replacement was issued from the same revoked certificate.' );
	$recorded_types = $wpdb->get_col( $wpdb->prepare( "SELECT event_type FROM {$wpdb->prefix}pf_security_events WHERE participant_user_id=%d", $user_id ) );
	foreach ( array( 'assessment_reviewed', 'certificate_issued', 'certificate_revoked', 'certificate_reissued' ) as $event_type ) {
		$assert( in_array( $event_type, $recorded_types, true ), "Security ledger is missing {$event_type}." );
	}
} catch ( Throwable $error ) {
	$failures[] = 'Test setup failed: ' . $error->getMessage();
} finally {
	global $wpdb;
	if ( $enrollment_id ) {
		$attempt_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}pf_assessment_attempts WHERE enrollment_id = %d", $enrollment_id ) );
		if ( $attempt_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $attempt_ids ), '%d' ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}pf_assessment_answers WHERE attempt_id IN ({$placeholders})", ...$attempt_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$wpdb->delete( $wpdb->prefix . 'pf_assessment_attempts', array( 'enrollment_id' => $enrollment_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'pf_certificates', array( 'enrollment_id' => $enrollment_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'pf_notification_log', array( 'participant_user_id' => $user_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'pf_enrollments', array( 'id' => $enrollment_id ), array( '%d' ) );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}pf_security_events WHERE id > %d", $security_baseline ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( array_reverse( $created_posts ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( $user_id && ! is_wp_error( $user_id ) ) {
		wp_delete_user( $user_id );
	}
	if ( $staff_id && ! is_wp_error( $staff_id ) ) {
		wp_delete_user( $staff_id );
	}
}

if ( $failures ) {
	fwrite( STDERR, sprintf( "Assessment and certificate test failed (%d/%d checks):\n- %s\n", count( $failures ), $checks, implode( "\n- ", $failures ) ) );
	exit( 1 );
}
fwrite( STDOUT, sprintf( "Assessment and certificate tests passed: %d checks.\n", $checks ) );
