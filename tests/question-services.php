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
	$assert( Parish_Formation_Question_Type_Registry::implemented( 'multiple_choice' ) && Parish_Formation_Question_Type_Registry::implemented( 'multiple_select' ) && Parish_Formation_Question_Type_Registry::implemented( 'short_answer' ) && Parish_Formation_Question_Type_Registry::implemented( 'fill_blank' ) && Parish_Formation_Question_Type_Registry::implemented( 'matching' ) && Parish_Formation_Question_Type_Registry::implemented( 'ordering' ) && Parish_Formation_Question_Type_Registry::implemented( 'rating_scale' ) && Parish_Formation_Question_Type_Registry::implemented( 'yes_no' ) && Parish_Formation_Question_Type_Registry::implemented( 'image_selection' ), 'Phase availability is incorrect.' );

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
	$audited_ack = $create_question( 'acknowledgement', 'I understand the current godparent requirements.', '', array(), 2 );
	$audited_ack_config = Parish_Formation_Question_Config::get( $audited_ack->ID );
	$audited_ack_config['graded'] = true;
	$audited_ack_config['type_config'] = array(
		'checkbox_label' => 'I have read and accept these requirements.',
		'policy_url' => 'https://example.test/godparent-policy/',
		'require_policy_open' => true,
		'completion_credit' => true,
	);
	update_post_meta( $audited_ack->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $audited_ack_config, 'acknowledgement' ) );
	$ack_unopened = Parish_Formation_Question_Grading_Service::grade( $audited_ack, array( 'acknowledged' => 'acknowledged', 'policy_opened' => '0' ) );
	$ack_missing = Parish_Formation_Question_Grading_Service::grade( $audited_ack, array( 'policy_opened' => '1' ) );
	$ack_completed = Parish_Formation_Question_Grading_Service::grade( $audited_ack, array( 'acknowledged' => 'acknowledged', 'policy_opened' => '1' ) );
	$assert( ! $ack_unopened['valid'] && 'policy_not_opened' === $ack_unopened['error_code'], 'Acknowledgment policy-open validation failed.' );
	$assert( ! $ack_missing['valid'] && 'required_acknowledgement' === $ack_missing['error_code'], 'Required acknowledgment validation failed.' );
	$assert( $ack_completed['valid'] && $ack_completed['completed'] && null === $ack_completed['is_correct'] && 2.0 === (float) $ack_completed['earned_points'], 'Acknowledgment completion credit failed.' );
	$assert( false !== strpos( $ack_completed['stored_response'], 'policy_opened' ), 'Acknowledgment audit response was not preserved.' );
	$audited_ack_html = Parish_Formation_Question_Renderer::render( $audited_ack, 'pf_answers[' . $audited_ack->ID . ']', false );
	$assert( false !== strpos( $audited_ack_html, 'I have read and accept these requirements.' ) && false !== strpos( $audited_ack_html, 'godparent-policy' ) && false !== strpos( $audited_ack_html, 'data-policy-required="true"' ), 'Acknowledgment label, policy link, or open requirement did not render.' );
	$audited_ack_snapshot = Parish_Formation_Question_Snapshot::create( $audited_ack, Parish_Formation_Question_Config::get( $audited_ack->ID ) );
	$assert( 'I understand the current godparent requirements.' === $audited_ack_snapshot['prompt'] && 'I have read and accept these requirements.' === $audited_ack_snapshot['type_config']['checkbox_label'], 'Acknowledgment snapshot lost its historical statement or label.' );

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

	$rating = $create_question( 'rating_scale', 'How confident are you explaining Baptism?', '', array(), 1 );
	$rating_config = Parish_Formation_Question_Config::get( $rating->ID );
	$assert( 'Lowest' === $rating_config['type_config']['first_label'] && 'Highest' === $rating_config['type_config']['last_label'], 'Rating Scale neutral endpoint defaults are missing.' );
	$rating_config['type_config'] = array( 'minimum' => 1, 'maximum' => 5, 'first_label' => 'Not confident', 'last_label' => 'Very confident', 'value_labels' => array( 3 => 'Somewhat confident' ), 'orientation' => 'horizontal' );
	update_post_meta( $rating->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $rating_config, 'rating_scale' ) );
	$rating_grade = Parish_Formation_Question_Grading_Service::grade( $rating, array( 'value' => '3' ) );
	$rating_invalid = Parish_Formation_Question_Grading_Service::grade( $rating, array( 'value' => '6' ) );
	$rating_stored = json_decode( $rating_grade['stored_response'], true );
	$assert( $rating_grade['valid'] && $rating_grade['completed'] && null === $rating_grade['is_correct'] && 0.0 === (float) $rating_grade['maximum_points'], 'Rating Scale completion semantics failed.' );
	$assert( 3 === $rating_stored['value'] && 5 === $rating_stored['scale_config']['maximum'] && 'Somewhat confident' === $rating_stored['scale_config']['value_labels'][3], 'Rating Scale response did not retain the submitted value and scale configuration.' );
	$assert( ! $rating_invalid['valid'] && 'invalid_rating' === $rating_invalid['error_code'], 'Rating Scale accepted an out-of-range value.' );
	$rating_html = Parish_Formation_Question_Renderer::render( $rating, 'pf_answers[' . $rating->ID . ']', false );
	$assert( 5 === substr_count( $rating_html, 'type="radio"' ) && false !== strpos( $rating_html, 'Not confident' ) && false !== strpos( $rating_html, 'Somewhat confident' ) && false !== strpos( $rating_html, 'Very confident' ), 'Rating Scale controls or labels did not render.' );
	$rating_snapshot = Parish_Formation_Question_Snapshot::create( $rating, Parish_Formation_Question_Config::get( $rating->ID ) );
	$assert( 1 === $rating_snapshot['type_config']['minimum'] && 5 === $rating_snapshot['type_config']['maximum'], 'Rating Scale snapshot lost its historical configuration.' );

	$yes_no = $create_question( 'yes_no', 'Have you attended preparation before?', '', array(), 2 );
	$yes_no_config = Parish_Formation_Question_Config::get( $yes_no->ID );
	$yes_no_config['type_config'] = array( 'yes_label' => 'I have', 'no_label' => 'Not yet', 'correct_answer' => '', 'yes_message' => 'Thank you for letting us know.', 'no_message' => 'We will explain the next step.' );
	update_post_meta( $yes_no->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $yes_no_config, 'yes_no' ) );
	$yes_no_neutral = Parish_Formation_Question_Grading_Service::grade( $yes_no, 'no' );
	$assert( $yes_no_neutral['valid'] && $yes_no_neutral['completed'] && null === $yes_no_neutral['is_correct'] && 'We will explain the next step.' === $yes_no_neutral['feedback'], 'Non-graded Yes/No response or follow-up feedback failed.' );
	$yes_no_config['graded'] = true;
	$yes_no_config['type_config']['correct_answer'] = 'yes';
	update_post_meta( $yes_no->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $yes_no_config, 'yes_no' ) );
	$yes_no_correct = Parish_Formation_Question_Grading_Service::grade( $yes_no, 'yes' );
	$yes_no_wrong = Parish_Formation_Question_Grading_Service::grade( $yes_no, 'no' );
	$yes_no_invalid = Parish_Formation_Question_Grading_Service::grade( $yes_no, 'maybe' );
	$assert( true === $yes_no_correct['is_correct'] && 2.0 === (float) $yes_no_correct['earned_points'] && false === $yes_no_wrong['is_correct'], 'Graded Yes/No scoring failed.' );
	$assert( ! $yes_no_invalid['valid'] && 'invalid_answer' === $yes_no_invalid['error_code'], 'Yes/No accepted an unknown response.' );
	$yes_no_html = Parish_Formation_Question_Renderer::render( $yes_no, 'pf_answers[' . $yes_no->ID . ']', false );
	$assert( 2 === substr_count( $yes_no_html, 'type="radio"' ) && false !== strpos( $yes_no_html, 'I have' ) && false !== strpos( $yes_no_html, 'Not yet' ) && false === strpos( $yes_no_html, 'correct_answer' ), 'Yes/No learner controls are incomplete or expose the grading key.' );
	$yes_no_snapshot = Parish_Formation_Question_Snapshot::create( $yes_no, Parish_Formation_Question_Config::get( $yes_no->ID ) );
	$assert( 'I have' === $yes_no_snapshot['type_config']['yes_label'] && 'Not yet' === $yes_no_snapshot['type_config']['no_label'], 'Yes/No snapshot lost its historical labels.' );

	$image_one = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'Baptismal font', 'post_mime_type' => 'image/jpeg' ) );
	$image_two = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'Parish picnic', 'post_mime_type' => 'image/jpeg' ) );
	$posts[] = $image_one;
	$posts[] = $image_two;
	$image_question = $create_question( 'image_selection', 'Select the image of a baptismal font.', '', array(), 4 );
	$image_config = Parish_Formation_Question_Config::get( $image_question->ID );
	$image_config['type_config'] = array(
		'selection_mode' => 'single', 'grading_mode' => 'all_or_nothing',
		'images' => array(
			array( 'id' => 'font', 'attachment_id' => $image_one, 'label' => 'Font', 'alt' => 'Stone baptismal font', 'correct' => true ),
			array( 'id' => 'picnic', 'attachment_id' => $image_two, 'label' => 'Picnic', 'alt' => 'People eating outdoors', 'correct' => false ),
		),
	);
	update_post_meta( $image_question->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $image_config, 'image_selection' ) );
	$image_correct = Parish_Formation_Question_Grading_Service::grade( $image_question, 'font' );
	$image_wrong = Parish_Formation_Question_Grading_Service::grade( $image_question, 'picnic' );
	$image_invalid = Parish_Formation_Question_Grading_Service::grade( $image_question, 'unknown-image' );
	$assert( true === $image_correct['is_correct'] && 4.0 === (float) $image_correct['earned_points'] && false === $image_wrong['is_correct'], 'Single Image Selection grading failed.' );
	$assert( ! $image_invalid['valid'] && 'invalid_answer' === $image_invalid['error_code'], 'Image Selection accepted an unknown image ID.' );
	$image_html = Parish_Formation_Question_Renderer::render( $image_question, 'pf_answers[' . $image_question->ID . ']', false );
	$assert( 2 === substr_count( $image_html, 'type="radio"' ) && false !== strpos( $image_html, '>Font<' ) && false === strpos( $image_html, 'correct_answer' ), 'Image Selection learner grid is incomplete or exposes the grading key.' );
	$image_config['type_config']['selection_mode'] = 'multiple';
	$image_config['type_config']['grading_mode'] = 'partial';
	$image_config['type_config']['images'][1]['correct'] = true;
	update_post_meta( $image_question->ID, Parish_Formation_Question_Config::META_KEY, Parish_Formation_Question_Config::sanitize( $image_config, 'image_selection' ) );
	$image_partial = Parish_Formation_Question_Grading_Service::grade( $image_question, array( 'font' ) );
	$assert( 2.0 === (float) $image_partial['earned_points'] && false === $image_partial['is_correct'], 'Multiple Image Selection partial-credit grading failed.' );
	$image_snapshot = Parish_Formation_Question_Snapshot::create( $image_question, Parish_Formation_Question_Config::get( $image_question->ID ) );
	$assert( 'font' === $image_snapshot['type_config']['images'][0]['id'] && 'Stone baptismal font' === $image_snapshot['type_config']['images'][0]['alt'], 'Image Selection snapshot lost its stable image ID or alt text.' );

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
