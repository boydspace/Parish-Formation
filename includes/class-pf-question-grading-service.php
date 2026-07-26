<?php
/** Central validation and grading for assessment responses. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Parish_Formation_Question_Grading_Service {
	/** Grade one response without mutating storage. */
	public static function grade( $question, $response ) {
		$config   = Parish_Formation_Question_Config::get( $question->ID );
		$type     = $config['type'];
		$original = is_array( $response ) ? $response : (string) $response;
		$empty    = self::is_empty( $response );
		if ( $config['required'] && $empty ) {
			return self::result( false, 'required_answer', $original, 0, $config['graded'] ? $config['points'] : 0, false, false, false, $config, __( 'This question is required.', 'parish-formation' ) );
		}
		if ( $empty ) {
			return self::result( true, '', $original, 0, $config['graded'] ? $config['points'] : 0, false, false, false, $config );
		}

		if ( 'multiple_choice' === $type || 'true_false' === $type ) {
			$answer  = sanitize_text_field( (string) $response );
			$correct = strtolower( sanitize_text_field( $config['correct_answer'] ) );
			if ( 'multiple_choice' === $type && ! ctype_digit( $correct ) && ! str_starts_with( $correct, 'legacy-choice-' ) ) {
				foreach ( $config['choices'] as $index => $choice ) {
					if ( strtolower( $choice['label'] ) === $correct ) { $correct = (string) ( $index + 1 ); break; }
				}
			}
			$normalized_answer = str_starts_with( $answer, 'legacy-choice-' ) ? substr( $answer, 14 ) : $answer;
			$normalized_correct = str_starts_with( $correct, 'legacy-choice-' ) ? substr( $correct, 14 ) : $correct;
			$is_correct = strtolower( $normalized_answer ) === strtolower( $normalized_correct );
			$points = $config['graded'] && $is_correct ? $config['points'] : 0;
			return self::result( true, '', $original, $points, $config['graded'] ? $config['points'] : 0, true, $is_correct, false, $config );
		}

		if ( 'acknowledgement' === $type ) {
			$completed = in_array( strtolower( sanitize_text_field( (string) $response ) ), array( 'acknowledged', '1', 'yes', 'true' ), true );
			if ( $config['required'] && ! $completed ) {
				return self::result( false, 'required_acknowledgement', $original, 0, 0, false, null, false, $config, __( 'This acknowledgment is required.', 'parish-formation' ) );
			}
			return self::result( true, '', $original, 0, 0, $completed, null, false, $config );
		}

		if ( in_array( $type, array( 'reflection', 'paragraph' ), true ) ) {
			$requires_review = $config['manual_review'] || 'paragraph' === $type;
			$maximum = $config['graded'] ? $config['points'] : 0;
			return self::result( true, '', $original, 0, $maximum, true, null, $requires_review, $config );
		}

		return self::result( false, 'unsupported_question_type', $original, 0, 0, false, null, false, $config, __( 'This question type is not available yet.', 'parish-formation' ) );
	}

	private static function result( $valid, $error_code, $original, $earned, $maximum, $completed, $correct, $requires_review, $config, $message = '' ) {
		$earned  = max( 0, min( (float) $earned, (float) $maximum ) );
		$feedback = null === $correct ? $config['explanation'] : ( $correct ? $config['correct_feedback'] : $config['incorrect_feedback'] );
		return array(
			'valid' => (bool) $valid, 'error_code' => $error_code, 'message' => $message,
			'original_response' => $original, 'stored_response' => is_array( $original ) ? wp_json_encode( $original ) : (string) $original,
			'earned_points' => $earned, 'maximum_points' => max( 0, (float) $maximum ), 'completed' => (bool) $completed,
			'is_correct' => $correct, 'requires_review' => (bool) $requires_review,
			'status' => $requires_review ? 'pending_review' : ( null === $correct ? ( $completed ? 'completed' : 'incomplete' ) : ( $correct ? 'correct' : 'incorrect' ) ),
			'feedback' => $feedback, 'feedback_timing' => $config['feedback_timing'],
		);
	}

	private static function is_empty( $response ) {
		if ( is_array( $response ) ) { return empty( array_filter( $response, static fn( $value ) => '' !== trim( (string) $value ) ) ); }
		return '' === trim( (string) $response );
	}
}
