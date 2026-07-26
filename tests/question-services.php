<?php
/** Integration tests for the 1.5 question registry, configuration, grading, rendering, and snapshots. */

$url = parse_url( getenv( 'PF_TEST_URL' ) ?: 'http://parish-formation.test' );
$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? ( $url['scheme'] ?? 'http' );
$_SERVER['HTTP_HOST']      = $_SERVER['HTTP_HOST'] ?? ( $url['host'] ?? 'parish-formation.test' );
require_once dirname( __DIR__, 4 ) . '/wp-load.php';

$failures = array();
$checks   = 0;
$posts    = array();
$assert   = static function ( $condition, $message ) use ( &$failures, &$checks ) {
	++$checks;
	if ( ! $condition ) { $failures[] = $message; }
};

$create_question = static function ( $type, $content, $answer = '', $options = array(), $points = 1 ) use ( &$posts ) {
	$id = wp_insert_post( array( 'post_type' => Parish_Formation_Question_Post_Type::POST_TYPE, 'post_status' => 'publish', 'post_title' => 'PF service test', 'post_content' => $content ) );
	$posts[] = $id;
	update_post_meta( $id, '_pf_question_type', $type );
	update_post_meta( $id, '_pf_question_required', 1 );
	update_post_meta( $id, '_pf_question_points', $points );
	update_post_meta( $id, '_pf_question_correct_answer', $answer );
	update_post_meta( $id, '_pf_question_options', $options );
	return get_post( $id );
};

try {
	$categories = Parish_Formation_Question_Type_Registry::categories();
	$assert( isset( $categories['automatic'], $categories['review'], $categories['formation'] ), 'Question categories are incomplete.' );
	$assert( 'reflection' === Parish_Formation_Question_Type_Registry::normalize( 'reflection_response' ), 'Reflection compatibility alias failed.' );
	$assert( 'acknowledgement' === Parish_Formation_Question_Type_Registry::normalize( 'acknowledgment' ), 'Acknowledgment compatibility alias failed.' );
	$assert( Parish_Formation_Question_Type_Registry::implemented( 'multiple_choice' ) && ! Parish_Formation_Question_Type_Registry::implemented( 'multiple_select' ), 'Phase availability is incorrect.' );

	$choice = $create_question( 'multiple_choice', 'Choose the first answer.', 'Correct', array( 'Correct', 'Incorrect' ), 2 );
	$config = Parish_Formation_Question_Config::get( $choice->ID );
	$assert( 'legacy-choice-1' === $config['choices'][0]['id'], 'Legacy answer choices do not have stable compatibility IDs.' );
	$correct = Parish_Formation_Question_Grading_Service::grade( $choice, '1' );
	$wrong   = Parish_Formation_Question_Grading_Service::grade( $choice, '2' );
	$missing = Parish_Formation_Question_Grading_Service::grade( $choice, '' );
	$assert( $correct['valid'] && $correct['is_correct'] && 2.0 === $correct['earned_points'], 'Multiple-choice correct-answer grading failed.' );
	$assert( $wrong['valid'] && false === $wrong['is_correct'] && 0.0 === (float) $wrong['earned_points'], 'Multiple-choice incorrect-answer grading failed.' );
	$assert( ! $missing['valid'] && 'required_answer' === $missing['error_code'], 'Required response validation failed.' );

	$reflection = $create_question( 'reflection', 'Describe one practical application.', '', array(), 3 );
	$reflection_config = Parish_Formation_Question_Config::get( $reflection->ID );
	$reflection_grade  = Parish_Formation_Question_Grading_Service::grade( $reflection, 'My original response.' );
	$assert( $reflection_config['graded'], 'Legacy reflection did not retain its point-bearing behavior.' );
	$assert( $reflection_grade['requires_review'] && 'pending_review' === $reflection_grade['status'] && 3.0 === $reflection_grade['maximum_points'], 'Legacy reflection review grading failed.' );
	$assert( 'My original response.' === $reflection_grade['stored_response'], 'Original text response was not preserved.' );

	$ack = $create_question( 'acknowledgement', 'I have reviewed the policy.' );
	$ack_grade = Parish_Formation_Question_Grading_Service::grade( $ack, 'acknowledged' );
	$assert( $ack_grade['completed'] && null === $ack_grade['is_correct'] && 0.0 === (float) $ack_grade['maximum_points'], 'Acknowledgment completion semantics failed.' );

	$snapshot = Parish_Formation_Question_Snapshot::create( $choice, $config );
	$assert( 2 === $snapshot['snapshot_version'] && 'Choose the first answer.' === $snapshot['prompt'] && isset( $snapshot['choices'][0]['id'] ), 'Historical question snapshot is incomplete.' );
	$rendered = Parish_Formation_Question_Renderer::render( $choice, 'pf_answers[' . $choice->ID . ']', false );
	$assert( false !== strpos( $rendered, 'type="radio"' ) && false === strpos( $rendered, 'correct_answer' ), 'Learner renderer is inaccessible or exposes grading configuration.' );
} catch ( Throwable $error ) {
	$failures[] = 'Test setup failed: ' . $error->getMessage();
} finally {
	foreach ( array_reverse( $posts ) as $post_id ) { wp_delete_post( $post_id, true ); }
}

if ( $failures ) {
	fwrite( STDERR, sprintf( "Question service tests failed (%d/%d checks):\n- %s\n", count( $failures ), $checks, implode( "\n- ", $failures ) ) );
	exit( 1 );
}
fwrite( STDOUT, sprintf( "Question service tests passed: %d checks.\n", $checks ) );
