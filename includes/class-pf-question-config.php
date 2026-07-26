<?php
/** Normalized question configuration and legacy adapters. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Parish_Formation_Question_Config {
	public const META_KEY = '_pf_question_config';
	public const VERSION  = 1;

	/** Read normalized configuration while adapting legacy question meta. */
	public static function get( $question_id ) {
		$type       = Parish_Formation_Question_Type_Registry::normalize( get_post_meta( $question_id, '_pf_question_type', true ) );
		$definition = Parish_Formation_Question_Type_Registry::get( $type );
		$stored     = get_post_meta( $question_id, self::META_KEY, true );
		$stored     = is_array( $stored ) ? $stored : array();
		$is_legacy  = empty( $stored );
		$options    = get_post_meta( $question_id, '_pf_question_options', true );
		$options    = is_array( $options ) ? array_values( $options ) : array();
		$choices    = array();
		foreach ( $options as $index => $label ) {
			$choices[] = array( 'id' => 'legacy-choice-' . ( $index + 1 ), 'label' => sanitize_text_field( $label ), 'feedback' => '' );
		}
		$defaults = array(
			'version' => self::VERSION, 'type' => $type, 'instructions' => '',
			'required' => ! metadata_exists( 'post', $question_id, '_pf_question_required' ) || (bool) get_post_meta( $question_id, '_pf_question_required', true ),
			// Legacy reflection questions were point-bearing and manually reviewed.
			'graded' => $is_legacy && 'reflection' === $type ? true : $definition['graded_default'], 'points' => max( 1, absint( get_post_meta( $question_id, '_pf_question_points', true ) ) ),
			'explanation' => '', 'correct_feedback' => '', 'incorrect_feedback' => '', 'feedback_timing' => 'assessment',
			'allow_retry' => false, 'max_attempts' => 1, 'randomize_choices' => false, 'manual_review' => $definition['manual_review_default'],
			'featured_media_id' => 0, 'admin_notes' => '', 'presentation' => 'standard', 'scenario' => array(),
			'choices' => $choices, 'correct_answer' => sanitize_text_field( get_post_meta( $question_id, '_pf_question_correct_answer', true ) ),
		);
		return self::sanitize( array_replace_recursive( $defaults, $stored ), $type );
	}

	/** Sanitize shared configuration while retaining type-specific arrays. */
	public static function sanitize( $config, $type = '' ) {
		$config = is_array( $config ) ? $config : array();
		$type   = Parish_Formation_Question_Type_Registry::normalize( $type ?: ( $config['type'] ?? '' ) );
		$definition = Parish_Formation_Question_Type_Registry::get( $type );
		$timing = sanitize_key( $config['feedback_timing'] ?? 'assessment' );
		$presentation = sanitize_key( $config['presentation'] ?? 'standard' );
		return array(
			'version' => self::VERSION, 'type' => $type,
			'instructions' => wp_kses_post( $config['instructions'] ?? '' ),
			'required' => ! empty( $config['required'] ), 'graded' => array_key_exists( 'graded', $config ) ? (bool) $config['graded'] : $definition['graded_default'],
			'points' => max( 0, (float) ( $config['points'] ?? 1 ) ),
			'explanation' => wp_kses_post( $config['explanation'] ?? '' ), 'correct_feedback' => wp_kses_post( $config['correct_feedback'] ?? '' ), 'incorrect_feedback' => wp_kses_post( $config['incorrect_feedback'] ?? '' ),
			'feedback_timing' => in_array( $timing, array( 'immediate', 'assessment' ), true ) ? $timing : 'assessment',
			'allow_retry' => ! empty( $config['allow_retry'] ), 'max_attempts' => max( 1, absint( $config['max_attempts'] ?? 1 ) ),
			'randomize_choices' => ! empty( $config['randomize_choices'] ), 'manual_review' => ! empty( $config['manual_review'] ),
			'featured_media_id' => absint( $config['featured_media_id'] ?? 0 ), 'admin_notes' => sanitize_textarea_field( $config['admin_notes'] ?? '' ),
			'presentation' => in_array( $presentation, array( 'standard', 'scenario' ), true ) ? $presentation : 'standard',
			'scenario' => self::sanitize_nested( $config['scenario'] ?? array() ), 'choices' => self::sanitize_choices( $config['choices'] ?? array() ),
			'correct_answer' => sanitize_text_field( $config['correct_answer'] ?? '' ),
			'type_config' => self::sanitize_type_config( $config['type_config'] ?? array(), $type ),
		);
	}

	private static function sanitize_choices( $choices ) {
		$safe = array();
		foreach ( is_array( $choices ) ? $choices : array() as $index => $choice ) {
			if ( ! is_array( $choice ) ) { $choice = array( 'label' => $choice ); }
			$label = sanitize_text_field( $choice['label'] ?? '' );
			if ( '' === $label ) { continue; }
			$safe[] = array( 'id' => sanitize_key( $choice['id'] ?? '' ) ?: 'choice-' . ( $index + 1 ), 'label' => $label, 'feedback' => wp_kses_post( $choice['feedback'] ?? '' ), 'correct' => ! empty( $choice['correct'] ), 'order' => absint( $choice['order'] ?? $index + 1 ) );
		}
		return $safe;
	}

	private static function sanitize_nested( $values ) {
		$safe = array();
		foreach ( is_array( $values ) ? $values : array() as $key => $value ) {
			$key = sanitize_key( $key );
			$safe[ $key ] = is_array( $value ) ? self::sanitize_nested( $value ) : sanitize_text_field( (string) $value );
		}
		return $safe;
	}

	/** Sanitize the fields owned by a specific question type. */
	private static function sanitize_type_config( $values, $type ) {
		$values = is_array( $values ) ? $values : array();
		if ( 'multiple_select' === $type ) {
			$mode = sanitize_key( $values['grading_mode'] ?? 'all_or_nothing' );
			return array(
				'grading_mode' => in_array( $mode, array( 'all_or_nothing', 'partial', 'partial_penalty' ), true ) ? $mode : 'all_or_nothing',
			);
		}
		if ( 'short_answer' === $type ) {
			$answers = array_values( array_filter( array_map( 'sanitize_text_field', is_array( $values['accepted_answers'] ?? null ) ? $values['accepted_answers'] : array() ), static fn( $answer ) => '' !== $answer ) );
			$mode = sanitize_key( $values['match_mode'] ?? 'exact' );
			return array(
				'accepted_answers' => $answers,
				'case_sensitive' => ! empty( $values['case_sensitive'] ),
				'trim_spaces' => ! array_key_exists( 'trim_spaces', $values ) || ! empty( $values['trim_spaces'] ),
				'normalize_spaces' => ! empty( $values['normalize_spaces'] ),
				'ignore_punctuation' => ! empty( $values['ignore_punctuation'] ),
				'match_mode' => in_array( $mode, array( 'exact', 'contains' ), true ) ? $mode : 'exact',
			);
		}
		return self::sanitize_nested( $values );
	}
}
