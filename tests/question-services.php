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
	$assert( Parish_Formation_Question_Type_Registry::implemented( 'multiple_choice' ) && Parish_Formation_Question_Type_Registry::implemented( 'multiple_select' ) && Parish_Formation_Question_Type_Registry::implemented( 'short_answer' ) && Parish_Formation_Question_Type_Registry::implemented( 'fill_blank' ) && Parish_Formation_Question_Type_Registry::implemented( 'matching' ) && Parish_Formation_Question_Type_Registry::implemented( 'ordering' ), 'Phase availability is incorrect.' );

	$choice = $create_question( 'multiple_choice', 'Choose the first answer.', 'Correct', array( 'Correct', 'Incorrect' ), 2 );
	$config = Parish_Formation_Question_Config::get( $choice->ID );
	$assert( 'legacy-choice-1' === $config['choices'][0]['id'], 'Legacy answer choices do not have stable compatibility IDs.' );
	$correct = Parish_Formation_Question_Grading_Service::grade( $choice, '1' );
	$wrong   = Parish_Formation_Question_Grading_Service::grade( $choice, '2' );
	$missing = Parish_Formation_Question_Grading_Service::grade( $choice, '' );
	$assert( $correct['valid'] && $correct['is_correct'] && 2.0 === $correct['earned_points'], 'Multiple-choice correct-answer grading failed.' );
	$assert( $wrong['valid'] && false === $wrong['is_correct'] && 0.0 === (float) $wrong['earned_points'], 'Multiple-choice incorrect-answer grading failed.' );
	$assert( ! $missing['valid'] && 'required_answer' === $missing['error_code'], 'Required response validation failed.' );
	$phase_one_config = $config;
	$phase_one_config['choices'] = array(
		array( 'id' => 'choice-1', 'label' => 'Correct', 'order' => 1 ),
		array( 'id' => 'choice-2', 'label' => 'Incorrect', 'order' => 2 ),
	);
	$phase_one_config['correct_answer'] = '1';
	update_post_meta( $choice->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $phase_one_config, 'multiple_choice' ) );
	$stable_legacy = Parish_Formation_Question_Grading_Service::grade( $choice, 'choice-1' );
	$assert( $stable_legacy['is_correct'] && 2.0 === (float) $stable_legacy['earned_points'], 'Stable choice ID did not match a legacy numeric correct answer.' );

	$reflection = $create_question( 'reflection', 'Describe one practical application.', '', array(), 3 );
	$reflection_config = Parish_Formation_Question_Config::get( $reflection->ID );
	$reflection_grade  = Parish_Formation_Question_Grading_Service::grade( $reflection, 'My original response.' );
	$assert( $reflection_config['graded'], 'Legacy reflection did not retain its point-bearing behavior.' );
	$assert( $reflection_config['manual_review'], 'Legacy reflection did not retain required staff review.' );
	$assert( $reflection_grade['requires_review'] && 'pending_review' === $reflection_grade['status'] && 3.0 === $reflection_grade['maximum_points'], 'Legacy reflection review grading failed.' );
	$assert( 'My original response.' === $reflection_grade['stored_response'], 'Original text response was not preserved.' );

	$ack = $create_question( 'acknowledgement', 'I have reviewed the policy.' );
	$ack_grade = Parish_Formation_Question_Grading_Service::grade( $ack, 'acknowledged' );
	$assert( $ack_grade['completed'] && null === $ack_grade['is_correct'] && 0.0 === (float) $ack_grade['maximum_points'], 'Acknowledgment completion semantics failed.' );

	$snapshot = Parish_Formation_Question_Snapshot::create( $choice, $config );
	$assert( 2 === $snapshot['snapshot_version'] && 'Choose the first answer.' === $snapshot['prompt'] && isset( $snapshot['choices'][0]['id'] ), 'Historical question snapshot is incomplete.' );
	$rendered = Parish_Formation_Question_Renderer::render( $choice, 'pf_answers[' . $choice->ID . ']', false );
	$assert( false !== strpos( $rendered, 'type="radio"' ) && false === strpos( $rendered, 'correct_answer' ), 'Learner renderer is inaccessible or exposes grading configuration.' );
	$true_false = $create_question( 'true_false', 'This statement is true.', 'true' );
	$true_false_html = Parish_Formation_Question_Renderer::render( $true_false, 'pf_answers[' . $true_false->ID . ']', false );
	$assert( 2 === substr_count( $true_false_html, 'type="radio"' ), 'True/False did not render exactly two radio controls.' );
	$assert( false === strpos( $true_false_html, 'elseif' ) && false === strpos( $true_false_html, 'textarea' ) && false === strpos( $true_false_html, 'acknowledge' ), 'Renderer control-flow source or unrelated controls leaked into True/False output.' );
	$ack_html = Parish_Formation_Question_Renderer::render( $ack, 'pf_answers[' . $ack->ID . ']', false );
	$assert( 1 === substr_count( $ack_html, 'type="checkbox"' ) && false === strpos( $ack_html, 'textarea' ), 'Acknowledgment did not render exactly one checkbox.' );
	$reflection_html = Parish_Formation_Question_Renderer::render( $reflection, 'pf_answers[' . $reflection->ID . ']', false );
	$assert( 1 === substr_count( $reflection_html, '<textarea' ) && false === strpos( $reflection_html, 'type="checkbox"' ), 'Reflection did not render exactly one text response.' );

	$formation_reflection = $create_question( 'reflection', 'Where did you encounter grace this week?', '', array(), 5 );
	$formation_config = Parish_Formation_Question_Config::get( $formation_reflection->ID );
	$formation_config['graded'] = true;
	$formation_config['manual_review'] = false;
	$formation_config['type_config'] = array( 'minimum_characters' => 10, 'maximum_characters' => 30, 'completion_credit' => true, 'private_notice' => 'Only formation staff can view this response.', 'sample_prompt' => 'Consider one concrete moment.' );
	update_post_meta( $formation_reflection->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $formation_config, 'reflection' ) );
	$reflection_short = Parish_Formation_Question_Grading_Service::grade( $formation_reflection, 'Too short' );
	$reflection_long = Parish_Formation_Question_Grading_Service::grade( $formation_reflection, str_repeat( 'a', 31 ) );
	$reflection_credit = Parish_Formation_Question_Grading_Service::grade( $formation_reflection, 'Grace was present today.' );
	$assert( ! $reflection_short['valid'] && 'response_too_short' === $reflection_short['error_code'], 'Reflection minimum-character validation failed.' );
	$assert( ! $reflection_long['valid'] && 'response_too_long' === $reflection_long['error_code'], 'Reflection maximum-character validation failed.' );
	$assert( $reflection_credit['valid'] && $reflection_credit['completed'] && null === $reflection_credit['is_correct'] && 5.0 === (float) $reflection_credit['earned_points'] && 'completed' === $reflection_credit['status'], 'Reflection completion credit or non-correctness semantics failed.' );
	$formation_config['manual_review'] = true;
	update_post_meta( $formation_reflection->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $formation_config, 'reflection' ) );
	$reflection_review = Parish_Formation_Question_Grading_Service::grade( $formation_reflection, 'Grace was present today.' );
	$assert( $reflection_review['requires_review'] && 'pending_review' === $reflection_review['status'], 'Optional Reflection staff review failed.' );
	$formation_html = Parish_Formation_Question_Renderer::render( $formation_reflection, 'pf_answers[' . $formation_reflection->ID . ']', false );
	$assert( false === strpos( $formation_html, 'minlength=' ) && false === strpos( $formation_html, 'maxlength=' ) && false !== strpos( $formation_html, 'data-min-characters="10"' ) && false !== strpos( $formation_html, '10 more required' ) && false !== strpos( $formation_html, 'Only formation staff' ) && false !== strpos( $formation_html, 'Consider one concrete moment.' ), 'Reflection learner guidance, counter, or constraints are incomplete.' );
	$formation_snapshot = Parish_Formation_Question_Snapshot::create( $formation_reflection, Parish_Formation_Question_Config::get( $formation_reflection->ID ) );
	$assert( 10 === $formation_snapshot['type_config']['minimum_characters'] && 'Only formation staff can view this response.' === $formation_snapshot['type_config']['private_notice'], 'Reflection snapshot lost historical settings.' );

	$multiple_select = $create_question( 'multiple_select', 'Select the two sacraments.', '', array(), 6 );
	$multi_base = Parish_Formation_Question_Config::get( $multiple_select->ID );
	$multi_base['choices'] = array(
		array( 'id' => 'baptism', 'label' => 'Baptism', 'correct' => true, 'order' => 1 ),
		array( 'id' => 'confirmation', 'label' => 'Confirmation', 'correct' => true, 'order' => 2 ),
		array( 'id' => 'picnic', 'label' => 'Parish picnic', 'correct' => false, 'order' => 3 ),
	);
	$multi_base['type_config'] = array( 'grading_mode' => 'all_or_nothing' );
	update_post_meta( $multiple_select->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $multi_base, 'multiple_select' ) );
	$multi_full = Parish_Formation_Question_Grading_Service::grade( $multiple_select, array( 'confirmation', 'baptism' ) );
	$multi_wrong = Parish_Formation_Question_Grading_Service::grade( $multiple_select, array( 'baptism', 'picnic' ) );
	$assert( $multi_full['is_correct'] && 6.0 === (float) $multi_full['earned_points'], 'Multiple Select all-or-nothing correct grading failed.' );
	$assert( false === $multi_wrong['is_correct'] && 0.0 === (float) $multi_wrong['earned_points'], 'Multiple Select all-or-nothing incorrect grading failed.' );
	$multi_base['type_config']['grading_mode'] = 'partial';
	update_post_meta( $multiple_select->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $multi_base, 'multiple_select' ) );
	$multi_partial = Parish_Formation_Question_Grading_Service::grade( $multiple_select, array( 'baptism' ) );
	$assert( 3.0 === (float) $multi_partial['earned_points'], 'Multiple Select partial-credit grading failed.' );
	$multi_base['type_config']['grading_mode'] = 'partial_penalty';
	update_post_meta( $multiple_select->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $multi_base, 'multiple_select' ) );
	$multi_penalty = Parish_Formation_Question_Grading_Service::grade( $multiple_select, array( 'baptism', 'picnic' ) );
	$assert( 0.0 === (float) $multi_penalty['earned_points'], 'Multiple Select penalty grading did not clamp the score at zero.' );
	$multi_invalid = Parish_Formation_Question_Grading_Service::grade( $multiple_select, array( 'forged-choice' ) );
	$assert( ! $multi_invalid['valid'] && 'invalid_answer' === $multi_invalid['error_code'], 'Multiple Select accepted an unknown choice ID.' );
	$multi_html = Parish_Formation_Question_Renderer::render( $multiple_select, 'pf_answers[' . $multiple_select->ID . ']', false );
	$assert( 3 === substr_count( $multi_html, 'type="checkbox"' ) && false !== strpos( $multi_html, 'Select all that apply.' ) && false !== strpos( $multi_html, 'value="baptism"' ), 'Multiple Select learner controls are incomplete.' );
	$feedback_snapshot = Parish_Formation_Question_Snapshot::create( $multiple_select, Parish_Formation_Question_Config::get( $multiple_select->ID ) );
	$feedback_snapshot['choices'][0]['feedback'] = 'Selected Baptism feedback.';
	$feedback_snapshot['choices'][1]['feedback'] = 'Unselected Confirmation feedback.';
	$feedback_snapshot['feedback'] = array( 'correct' => 'Overall correct.', 'incorrect' => 'Overall incorrect.', 'explanation' => 'General explanation.', 'timing' => 'assessment' );
	$feedback_answer = (object) array( 'question_snapshot' => wp_json_encode( $feedback_snapshot ), 'answer' => wp_json_encode( array( 'baptism' ) ), 'is_correct' => 1, 'requires_review' => 0 );
	$learner_feedback = Parish_Formation_Question_Feedback_Service::for_answer( $feedback_answer );
	$assert( 'correct' === $learner_feedback['status'] && 'Overall correct.' === $learner_feedback['messages'][0] && 'General explanation.' === $learner_feedback['messages'][1], 'General learner feedback was not built correctly.' );
	$assert( 1 === count( $learner_feedback['choice_feedback'] ) && 'Baptism' === $learner_feedback['choice_feedback'][0]['label'] && 'Selected Baptism feedback.' === $learner_feedback['choice_feedback'][0]['message'], 'Answer-specific feedback included an unselected choice or omitted the selected choice.' );
	$assert( false === strpos( wp_json_encode( $learner_feedback ), 'Unselected Confirmation feedback' ) && false === strpos( wp_json_encode( $learner_feedback ), 'correct_answer' ), 'Learner feedback exposed an unselected answer or grading key.' );

	$short = $create_question( 'short_answer', 'Name the gateway sacrament.', '', array(), 4 );
	$short_config = Parish_Formation_Question_Config::get( $short->ID );
	$short_config['type_config'] = array( 'accepted_answers' => array( 'Baptism', 'Holy Baptism' ), 'case_sensitive' => false, 'trim_spaces' => true, 'normalize_spaces' => true, 'ignore_punctuation' => true, 'match_mode' => 'exact' );
	update_post_meta( $short->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $short_config, 'short_answer' ) );
	$short_correct = Parish_Formation_Question_Grading_Service::grade( $short, '  BAPTISM!!!  ' );
	$short_wrong = Parish_Formation_Question_Grading_Service::grade( $short, 'Confirmation' );
	$assert( $short_correct['is_correct'] && 4.0 === (float) $short_correct['earned_points'] && '  BAPTISM!!!  ' === $short_correct['stored_response'], 'Short Answer normalization or original-response preservation failed.' );
	$assert( false === $short_wrong['is_correct'] && 0.0 === (float) $short_wrong['earned_points'], 'Short Answer incorrect grading failed.' );
	$short_config['type_config']['match_mode'] = 'contains';
	update_post_meta( $short->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $short_config, 'short_answer' ) );
	$short_contains = Parish_Formation_Question_Grading_Service::grade( $short, 'The answer is Baptism.' );
	$assert( $short_contains['is_correct'], 'Short Answer contains matching failed.' );
	$short_html = Parish_Formation_Question_Renderer::render( $short, 'pf_answers[' . $short->ID . ']', false );
	$assert( 1 === substr_count( $short_html, 'type="text"' ) && false === strpos( $short_html, 'Baptism' ), 'Short Answer renderer is missing or exposes accepted answers.' );

	$fill = $create_question( 'fill_blank', 'The sacrament of [blank] is celebrated with [blank].', '', array(), 4 );
	$fill_config = Parish_Formation_Question_Config::get( $fill->ID );
	$fill_config['type_config'] = array(
		'point_mode' => 'equal',
		'blanks' => array(
			array( 'id' => 'sacrament', 'accepted_answers' => array( 'Baptism', 'Holy Baptism' ), 'case_sensitive' => false, 'match_mode' => 'normalized', 'points' => 1 ),
			array( 'id' => 'matter', 'accepted_answers' => array( 'water' ), 'case_sensitive' => false, 'match_mode' => 'normalized', 'points' => 3 ),
		),
	);
	update_post_meta( $fill->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $fill_config, 'fill_blank' ) );
	$fill_partial = Parish_Formation_Question_Grading_Service::grade( $fill, array( 'sacrament' => '  BAPTISM ', 'matter' => 'oil' ) );
	$assert( $fill_partial['valid'] && false === $fill_partial['is_correct'] && 2.0 === (float) $fill_partial['earned_points'] && 4.0 === (float) $fill_partial['maximum_points'], 'Fill in the Blank equal partial-credit grading failed.' );
	$fill_config['type_config']['point_mode'] = 'custom';
	update_post_meta( $fill->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $fill_config, 'fill_blank' ) );
	$fill_custom = Parish_Formation_Question_Grading_Service::grade( $fill, array( 'sacrament' => 'wrong', 'matter' => 'Water' ) );
	$assert( 3.0 === (float) $fill_custom['earned_points'] && 4.0 === (float) $fill_custom['maximum_points'], 'Fill in the Blank custom-point grading failed.' );
	$fill_complete = Parish_Formation_Question_Grading_Service::grade( $fill, array( 'sacrament' => 'Holy Baptism', 'matter' => 'water' ) );
	$assert( $fill_complete['is_correct'] && 4.0 === (float) $fill_complete['earned_points'] && array( 'sacrament' => 'Holy Baptism', 'matter' => 'water' ) === json_decode( $fill_complete['stored_response'], true ), 'Fill in the Blank full grading or response storage failed.' );
	$fill_missing = Parish_Formation_Question_Grading_Service::grade( $fill, array( 'sacrament' => 'Baptism', 'matter' => '' ) );
	$assert( ! $fill_missing['valid'] && 'required_answer' === $fill_missing['error_code'], 'Fill in the Blank accepted an unanswered required blank.' );
	$fill_forged = Parish_Formation_Question_Grading_Service::grade( $fill, array( 'sacrament' => 'Baptism', 'matter' => 'water', 'forged' => 'value' ) );
	$assert( ! $fill_forged['valid'] && 'invalid_answer' === $fill_forged['error_code'], 'Fill in the Blank accepted an unknown blank ID.' );
	$fill_html = Parish_Formation_Question_Renderer::render( $fill, 'pf_answers[' . $fill->ID . ']', false );
	$assert( 2 === substr_count( $fill_html, 'type="text"' ) && false !== strpos( $fill_html, '[sacrament]' ) && false !== strpos( $fill_html, '[matter]' ) && false === strpos( $fill_html, 'Holy Baptism' ), 'Fill in the Blank inline renderer is incomplete or exposes accepted answers.' );

	$matching = $create_question( 'matching', 'Match each sacrament to its description.', '', array(), 6 );
	$matching_config = Parish_Formation_Question_Config::get( $matching->ID );
	$matching_config['randomize_choices'] = true;
	$matching_config['type_config'] = array(
		'point_mode' => 'equal',
		'pairs' => array(
			array( 'id' => 'baptism-pair', 'answer_id' => 'gateway-answer', 'prompt' => 'Baptism', 'answer' => 'Gateway to Christian life', 'points' => 2 ),
			array( 'id' => 'confirmation-pair', 'answer_id' => 'grace-answer', 'prompt' => 'Confirmation', 'answer' => 'Strengthens baptismal grace', 'points' => 4 ),
		),
	);
	update_post_meta( $matching->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $matching_config, 'matching' ) );
	$matching_partial = Parish_Formation_Question_Grading_Service::grade( $matching, array( 'baptism-pair' => 'gateway-answer', 'confirmation-pair' => 'gateway-answer' ) );
	$assert( $matching_partial['valid'] && false === $matching_partial['is_correct'] && 3.0 === (float) $matching_partial['earned_points'], 'Matching equal partial-credit grading failed.' );
	$matching_config['type_config']['point_mode'] = 'custom';
	update_post_meta( $matching->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $matching_config, 'matching' ) );
	$assert( 6.0 === Parish_Formation_Question_Config::maximum_points( Parish_Formation_Question_Config::get( $matching->ID ) ), 'Matching effective maximum did not use custom pair points.' );
	$matching_full = Parish_Formation_Question_Grading_Service::grade( $matching, array( 'baptism-pair' => 'gateway-answer', 'confirmation-pair' => 'grace-answer' ) );
	$assert( $matching_full['is_correct'] && 6.0 === (float) $matching_full['earned_points'] && 6.0 === (float) $matching_full['maximum_points'], 'Matching custom-point full grading failed.' );
	$matching_missing = Parish_Formation_Question_Grading_Service::grade( $matching, array( 'baptism-pair' => 'gateway-answer', 'confirmation-pair' => '' ) );
	$assert( ! $matching_missing['valid'] && 'required_answer' === $matching_missing['error_code'], 'Matching accepted an unanswered required pair.' );
	$matching_forged = Parish_Formation_Question_Grading_Service::grade( $matching, array( 'baptism-pair' => 'forged-pair', 'confirmation-pair' => 'grace-answer' ) );
	$assert( ! $matching_forged['valid'] && 'invalid_answer' === $matching_forged['error_code'], 'Matching accepted an unknown answer ID.' );
	$matching_html = Parish_Formation_Question_Renderer::render( $matching, 'pf_answers[' . $matching->ID . ']', false );
	$assert( 2 === substr_count( $matching_html, '<select' ) && false !== strpos( $matching_html, '[baptism-pair]' ) && false === strpos( $matching_html, 'correct_answer' ), 'Matching learner controls are incomplete or expose grading configuration.' );
	$assert( strpos( $matching_html, 'value="grace-answer"' ) < strpos( $matching_html, 'value="gateway-answer"' ), 'Matching randomization returned the authored answer order.' );
	$matching_snapshot = Parish_Formation_Question_Snapshot::create( $matching, Parish_Formation_Question_Config::get( $matching->ID ) );
	$assert( 'baptism-pair' === $matching_snapshot['type_config']['pairs'][0]['id'] && 'gateway-answer' === $matching_snapshot['type_config']['pairs'][0]['answer_id'] && 'Gateway to Christian life' === $matching_snapshot['type_config']['pairs'][0]['answer'], 'Matching snapshot lost the original pair configuration.' );

	$ordering = $create_question( 'ordering', 'Put these steps in order.', '', array(), 6 );
	$ordering_config = Parish_Formation_Question_Config::get( $ordering->ID );
	$ordering_config['type_config'] = array(
		'point_mode' => 'equal', 'grading_mode' => 'all_or_nothing',
		'items' => array(
			array( 'id' => 'prepare', 'label' => 'Prepare', 'points' => 1 ),
			array( 'id' => 'celebrate', 'label' => 'Celebrate', 'points' => 2 ),
			array( 'id' => 'reflect', 'label' => 'Reflect', 'points' => 3 ),
		),
	);
	update_post_meta( $ordering->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $ordering_config, 'ordering' ) );
	$ordering_wrong = Parish_Formation_Question_Grading_Service::grade( $ordering, array( 'prepare', 'reflect', 'celebrate' ) );
	$assert( $ordering_wrong['valid'] && false === $ordering_wrong['is_correct'] && 0.0 === (float) $ordering_wrong['earned_points'], 'Ordering all-or-nothing grading failed.' );
	$ordering_config['type_config']['grading_mode'] = 'partial';
	update_post_meta( $ordering->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $ordering_config, 'ordering' ) );
	$ordering_partial = Parish_Formation_Question_Grading_Service::grade( $ordering, array( 'prepare', 'reflect', 'celebrate' ) );
	$assert( 2.0 === (float) $ordering_partial['earned_points'], 'Ordering equal position-based partial credit failed.' );
	$ordering_config['type_config']['point_mode'] = 'custom';
	update_post_meta( $ordering->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $ordering_config, 'ordering' ) );
	$ordering_custom = Parish_Formation_Question_Grading_Service::grade( $ordering, array( 'prepare', 'reflect', 'celebrate' ) );
	$assert( 1.0 === (float) $ordering_custom['earned_points'] && 6.0 === (float) $ordering_custom['maximum_points'], 'Ordering custom position points failed.' );
	$ordering_correct = Parish_Formation_Question_Grading_Service::grade( $ordering, array( 'prepare', 'celebrate', 'reflect' ) );
	$assert( $ordering_correct['is_correct'] && 6.0 === (float) $ordering_correct['earned_points'], 'Ordering correct sequence grading failed.' );
	$ordering_forged = Parish_Formation_Question_Grading_Service::grade( $ordering, array( 'prepare', 'celebrate', 'forged' ) );
	$assert( ! $ordering_forged['valid'] && 'invalid_answer' === $ordering_forged['error_code'], 'Ordering accepted a forged item ID.' );
	$ordering_duplicate = Parish_Formation_Question_Grading_Service::grade( $ordering, array( 'prepare', 'prepare', 'reflect' ) );
	$assert( ! $ordering_duplicate['valid'], 'Ordering accepted a duplicated item ID.' );
	$ordering_html = Parish_Formation_Question_Renderer::render( $ordering, 'pf_answers[' . $ordering->ID . ']', false );
	$assert( 3 === substr_count( $ordering_html, 'type="hidden"' ) && 3 === substr_count( $ordering_html, 'draggable="true"' ) && 3 === substr_count( $ordering_html, 'tabindex="0"' ) && false === strpos( $ordering_html, 'pf-ordering-up' ) && false === strpos( $ordering_html, 'correct_answer' ), 'Ordering learner controls are incomplete or expose grading configuration.' );
	$ordering_closed_html = Parish_Formation_Question_Renderer::render( $ordering, 'pf_answers[' . $ordering->ID . ']', true );
	$assert( false === strpos( $ordering_closed_html, 'tabindex="0"' ) && false === strpos( $ordering_closed_html, 'draggable="true"' ) && false !== strpos( $ordering_closed_html, 'can no longer be changed' ), 'Closed Ordering controls appear interactive.' );
	$ordering_rendered_ids = array();
	preg_match_all( '/type="hidden"[^>]+value="([^"]+)"/', $ordering_html, $ordering_rendered_ids );
	$assert( array( 'prepare', 'celebrate', 'reflect' ) !== ( $ordering_rendered_ids[1] ?? array() ), 'Ordering randomization returned the authored sequence.' );
	$ordering_snapshot = Parish_Formation_Question_Snapshot::create( $ordering, Parish_Formation_Question_Config::get( $ordering->ID ) );
	$assert( 'prepare' === $ordering_snapshot['type_config']['items'][0]['id'] && 'Prepare' === $ordering_snapshot['type_config']['items'][0]['label'], 'Ordering snapshot lost its historical configuration.' );
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
