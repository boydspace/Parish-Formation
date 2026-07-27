<?php
/** Builds learner-safe feedback from immutable submitted-answer snapshots. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Parish_Formation_Question_Feedback_Service {
	/** Return learner-safe feedback for every answer in an attempt. */
	public static function for_attempt( $attempt_id ) {
		$feedback = array();
		foreach ( Parish_Formation_Assessment_Repository::get_attempt_answers( $attempt_id ) as $answer ) {
			$item = self::for_answer( $answer );
			if ( $item['messages'] || $item['choice_feedback'] ) {
				$feedback[ absint( $answer->question_id ) ] = $item;
			}
		}
		return $feedback;
	}

	/** Build feedback without returning correct-answer configuration. */
	public static function for_answer( $answer ) {
		$snapshot = json_decode( (string) $answer->question_snapshot, true );
		$snapshot = is_array( $snapshot ) ? $snapshot : array();
		$feedback = is_array( $snapshot['feedback'] ?? null ) ? $snapshot['feedback'] : array();
		$messages = array();
		if ( isset( $answer->learner_feedback ) && '' !== trim( (string) $answer->learner_feedback ) ) { $messages[] = nl2br( esc_html( $answer->learner_feedback ) ); }
		if ( null !== $answer->is_correct ) {
			$message = (bool) $answer->is_correct ? ( $feedback['correct'] ?? '' ) : ( $feedback['incorrect'] ?? '' );
			if ( '' !== trim( wp_strip_all_tags( (string) $message ) ) ) { $messages[] = wp_kses_post( $message ); }
		}
		if ( '' !== trim( wp_strip_all_tags( (string) ( $feedback['explanation'] ?? '' ) ) ) ) { $messages[] = wp_kses_post( $feedback['explanation'] ); }

		$choice_feedback = array();
		$type = sanitize_key( $snapshot['type'] ?? '' );
		if ( in_array( $type, array( 'multiple_choice', 'multiple_select' ), true ) ) {
			$selected = self::selected_choice_ids( $answer->answer, $snapshot['choices'] ?? array(), 'multiple_select' === $type );
			foreach ( is_array( $snapshot['choices'] ?? null ) ? $snapshot['choices'] : array() as $choice ) {
				if ( in_array( $choice['id'] ?? '', $selected, true ) && '' !== trim( wp_strip_all_tags( (string) ( $choice['feedback'] ?? '' ) ) ) ) {
					$choice_feedback[] = array( 'label' => sanitize_text_field( $choice['label'] ?? '' ), 'message' => wp_kses_post( $choice['feedback'] ) );
				}
			}
		}
		return array(
			'status' => (bool) $answer->requires_review ? 'pending_review' : ( null === $answer->is_correct ? 'completed' : ( (bool) $answer->is_correct ? 'correct' : 'incorrect' ) ),
			'messages' => array_values( array_unique( $messages ) ),
			'choice_feedback' => $choice_feedback,
		);
	}

	private static function selected_choice_ids( $stored_answer, $choices, $multiple ) {
		$references = $multiple ? json_decode( (string) $stored_answer, true ) : array( (string) $stored_answer );
		$references = is_array( $references ) ? $references : array();
		$ids = array();
		foreach ( $references as $reference ) {
			$reference = sanitize_text_field( (string) $reference );
			if ( ctype_digit( $reference ) && isset( $choices[ (int) $reference - 1 ] ) ) { $reference = $choices[ (int) $reference - 1 ]['id']; }
			if ( preg_match( '/^legacy-choice-(\d+)$/', $reference, $matches ) && isset( $choices[ (int) $matches[1] - 1 ] ) ) { $reference = $choices[ (int) $matches[1] - 1 ]['id']; }
			$ids[] = $reference;
		}
		return array_values( array_unique( $ids ) );
	}
}
