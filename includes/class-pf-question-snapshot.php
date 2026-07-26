<?php
/** Immutable question snapshots for historical assessment responses. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Parish_Formation_Question_Snapshot {
	public const VERSION = 2;

	public static function create( $question, $config = null ) {
		$config = is_array( $config ) ? $config : Parish_Formation_Question_Config::get( $question->ID );
		return array(
			'snapshot_version' => self::VERSION,
			'question_id'      => absint( $question->ID ),
			'question_version' => $question->post_modified_gmt ?: $question->post_modified,
			'prompt'           => wp_kses_post( $question->post_content ),
			'type'             => $config['type'],
			'instructions'     => $config['instructions'],
			'points'           => Parish_Formation_Question_Config::maximum_points( $config ),
			'required'         => $config['required'],
			'graded'           => $config['graded'],
			'manual_review'    => $config['manual_review'],
			'choices'          => $config['choices'],
			'correct_answer'   => $config['correct_answer'],
			'type_config'      => $config['type_config'],
			'feedback'         => array( 'explanation' => $config['explanation'], 'correct' => $config['correct_feedback'], 'incorrect' => $config['incorrect_feedback'], 'timing' => $config['feedback_timing'] ),
			'presentation'     => $config['presentation'],
			'scenario'         => $config['scenario'],
		);
	}
}
