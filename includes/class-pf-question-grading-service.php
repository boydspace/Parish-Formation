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
			if ( 'multiple_choice' === $type ) {
				$answer  = self::resolve_choice_id( $answer, $config['choices'] );
				$correct = self::resolve_choice_id( $correct, $config['choices'] );
			}
			$is_correct = '' !== $answer && strtolower( $answer ) === strtolower( $correct );
			$points = $config['graded'] && $is_correct ? $config['points'] : 0;
			return self::result( true, '', $original, $points, $config['graded'] ? $config['points'] : 0, true, $is_correct, false, $config );
		}

		if ( 'multiple_select' === $type ) {
			$available = array_column( $config['choices'], 'id' );
			$correct   = array_values( array_column( array_filter( $config['choices'], static fn( $choice ) => ! empty( $choice['correct'] ) ), 'id' ) );
			$selected  = array_values( array_unique( array_map( 'sanitize_key', is_array( $response ) ? $response : array( $response ) ) ) );
			if ( count( $available ) < 2 || empty( $correct ) ) {
				return self::result( false, 'invalid_question_configuration', $original, 0, $config['points'], false, null, false, $config, __( 'This Multiple Select question is not configured correctly.', 'parish-formation' ) );
			}
			if ( array_diff( $selected, $available ) ) {
				return self::result( false, 'invalid_answer', $original, 0, $config['points'], false, null, false, $config, __( 'The selected answer is not available.', 'parish-formation' ) );
			}
			$correct_selected   = count( array_intersect( $selected, $correct ) );
			$incorrect_selected = count( array_diff( $selected, $correct ) );
			$is_correct         = 0 === $incorrect_selected && count( $correct ) === $correct_selected;
			$fraction           = $is_correct ? 1 : 0;
			$mode               = $config['type_config']['grading_mode'] ?? 'all_or_nothing';
			if ( 'partial' === $mode || 'partial_penalty' === $mode ) {
				$fraction = $correct_selected / count( $correct );
				if ( 'partial_penalty' === $mode && $incorrect_selected ) {
					$incorrect_total = max( 1, count( $available ) - count( $correct ) );
					$fraction -= $incorrect_selected / $incorrect_total;
				}
			}
			$points = $config['graded'] ? max( 0, $fraction ) * $config['points'] : 0;
			return self::result( true, '', $selected, $points, $config['graded'] ? $config['points'] : 0, true, $is_correct, false, $config );
		}

		if ( 'short_answer' === $type ) {
			$answer   = (string) $response;
			$accepted = $config['type_config']['accepted_answers'] ?? array();
			if ( $config['graded'] && empty( $accepted ) ) {
				return self::result( false, 'invalid_question_configuration', $original, 0, $config['points'], false, null, false, $config, __( 'This Short Answer question has no accepted answers.', 'parish-formation' ) );
			}
			$is_correct = null;
			if ( $config['graded'] ) {
				$normalized = self::normalize_short_answer( $answer, $config['type_config'] );
				$is_correct = false;
				foreach ( $accepted as $accepted_answer ) {
					$expected = self::normalize_short_answer( $accepted_answer, $config['type_config'] );
					if ( 'contains' === ( $config['type_config']['match_mode'] ?? 'exact' ) ? str_contains( $normalized, $expected ) : $normalized === $expected ) {
						$is_correct = true;
						break;
					}
				}
			}
			$requires_review = ! empty( $config['manual_review'] );
			$points = $config['graded'] && true === $is_correct ? $config['points'] : 0;
			return self::result( true, '', $original, $points, $config['graded'] ? $config['points'] : 0, true, $is_correct, $requires_review, $config );
		}

		if ( 'fill_blank' === $type ) {
			$blanks = $config['type_config']['blanks'] ?? array();
			$placeholder_count = preg_match_all( '/\[blank\]/i', (string) $question->post_content );
			if ( empty( $blanks ) || $placeholder_count !== count( $blanks ) ) {
				return self::result( false, 'invalid_question_configuration', $original, 0, $config['points'], false, null, false, $config, __( 'This Fill in the Blank question is not configured correctly.', 'parish-formation' ) );
			}
			$responses = is_array( $response ) ? $response : array();
			$blank_ids = array_column( $blanks, 'id' );
			if ( array_diff( array_keys( $responses ), $blank_ids ) ) {
				return self::result( false, 'invalid_answer', $original, 0, $config['points'], false, null, false, $config, __( 'The submitted blank response is not valid.', 'parish-formation' ) );
			}
			$point_mode = $config['type_config']['point_mode'] ?? 'equal';
			$maximum = 'custom' === $point_mode ? array_sum( array_map( static fn( $blank ) => (float) $blank['points'], $blanks ) ) : (float) $config['points'];
			$equal_points = count( $blanks ) ? $maximum / count( $blanks ) : 0;
			$earned = 0;
			$all_correct = true;
			foreach ( $blanks as $blank ) {
				if ( empty( $blank['accepted_answers'] ) ) {
					return self::result( false, 'invalid_question_configuration', $original, 0, $maximum, false, null, false, $config, __( 'Every blank must have at least one accepted answer.', 'parish-formation' ) );
				}
				$value = isset( $responses[ $blank['id'] ] ) ? (string) $responses[ $blank['id'] ] : '';
				if ( $config['required'] && '' === trim( $value ) ) {
					return self::result( false, 'required_answer', $original, 0, $maximum, false, false, false, $config, __( 'Please complete every required blank.', 'parish-formation' ) );
				}
				$matched = false;
				foreach ( $blank['accepted_answers'] as $accepted ) {
					if ( self::normalize_blank_answer( $value, $blank ) === self::normalize_blank_answer( $accepted, $blank ) ) { $matched = true; break; }
				}
				if ( $matched ) { $earned += 'custom' === $point_mode ? (float) $blank['points'] : $equal_points; } else { $all_correct = false; }
			}
			return self::result( true, '', $responses, $config['graded'] ? $earned : 0, $config['graded'] ? $maximum : 0, true, $all_correct, false, $config );
		}

		if ( 'matching' === $type ) {
			$pairs = $config['type_config']['pairs'] ?? array();
			if ( count( $pairs ) < 2 ) {
				return self::result( false, 'invalid_question_configuration', $original, 0, $config['points'], false, null, false, $config, __( 'This Matching question needs at least two complete pairs.', 'parish-formation' ) );
			}
			$responses = is_array( $response ) ? $response : array();
			$pair_ids = array_column( $pairs, 'id' );
			$answer_ids = array_column( $pairs, 'answer_id' );
			if ( array_diff( array_keys( $responses ), $pair_ids ) || array_diff( array_filter( array_values( $responses ), static fn( $value ) => '' !== (string) $value ), $answer_ids ) ) {
				return self::result( false, 'invalid_answer', $original, 0, $config['points'], false, null, false, $config, __( 'A submitted match is not available.', 'parish-formation' ) );
			}
			$point_mode = $config['type_config']['point_mode'] ?? 'equal';
			$maximum = 'custom' === $point_mode ? array_sum( array_map( static fn( $pair ) => (float) $pair['points'], $pairs ) ) : (float) $config['points'];
			$equal_points = $maximum / count( $pairs );
			$earned = 0;
			$all_correct = true;
			foreach ( $pairs as $pair ) {
				$selected = sanitize_key( $responses[ $pair['id'] ] ?? '' );
				if ( $config['required'] && '' === $selected ) {
					return self::result( false, 'required_answer', $original, 0, $maximum, false, false, false, $config, __( 'Please complete every required match.', 'parish-formation' ) );
				}
				if ( $selected === $pair['answer_id'] ) { $earned += 'custom' === $point_mode ? (float) $pair['points'] : $equal_points; } else { $all_correct = false; }
			}
			return self::result( true, '', $responses, $config['graded'] ? $earned : 0, $config['graded'] ? $maximum : 0, true, $all_correct, false, $config );
		}

		if ( 'ordering' === $type ) {
			$items = $config['type_config']['items'] ?? array();
			if ( count( $items ) < 2 ) {
				return self::result( false, 'invalid_question_configuration', $original, 0, $config['points'], false, null, false, $config, __( 'This Ordering question needs at least two complete items.', 'parish-formation' ) );
			}
			$submitted = array_values( array_map( 'sanitize_key', is_array( $response ) ? $response : array() ) );
			$correct = array_column( $items, 'id' );
			if ( count( $submitted ) !== count( $correct ) || count( array_unique( $submitted ) ) !== count( $correct ) || array_diff( $submitted, $correct ) ) {
				return self::result( false, 'invalid_answer', $original, 0, Parish_Formation_Question_Config::maximum_points( $config ), false, null, false, $config, __( 'The submitted order is not valid.', 'parish-formation' ) );
			}
			$maximum = Parish_Formation_Question_Config::maximum_points( $config );
			$is_correct = $submitted === $correct;
			$earned = 0;
			if ( $is_correct ) {
				$earned = $maximum;
			} elseif ( 'partial' === ( $config['type_config']['grading_mode'] ?? 'all_or_nothing' ) ) {
				$equal_points = $maximum / count( $items );
				foreach ( $items as $index => $item ) {
					if ( $submitted[ $index ] === $item['id'] ) { $earned += 'custom' === ( $config['type_config']['point_mode'] ?? 'equal' ) ? (float) $item['points'] : $equal_points; }
				}
			}
			return self::result( true, '', $submitted, $config['graded'] ? $earned : 0, $config['graded'] ? $maximum : 0, true, $is_correct, false, $config );
		}

		if ( 'acknowledgement' === $type ) {
			$acknowledged_value = is_array( $response ) ? ( $response['acknowledged'] ?? '' ) : $response;
			$policy_opened = is_array( $response ) && ! empty( $response['policy_opened'] );
			$completed = in_array( strtolower( sanitize_text_field( (string) $acknowledged_value ) ), array( 'acknowledged', '1', 'yes', 'true' ), true );
			if ( ! empty( $config['type_config']['require_policy_open'] ) && ! $policy_opened ) {
				return self::result( false, 'policy_not_opened', $original, 0, $config['graded'] ? $config['points'] : 0, false, null, false, $config, __( 'Please open the linked policy before acknowledging this statement.', 'parish-formation' ) );
			}
			if ( $config['required'] && ! $completed ) {
				return self::result( false, 'required_acknowledgement', $original, 0, 0, false, null, false, $config, __( 'This acknowledgment is required.', 'parish-formation' ) );
			}
			$completion_credit = ! empty( $config['type_config']['completion_credit'] );
			$maximum = $completion_credit || $config['graded'] ? $config['points'] : 0;
			return self::result( true, '', $original, $completed && $completion_credit ? $maximum : 0, $maximum, $completed, null, false, $config );
		}

		if ( 'reflection' === $type ) {
			$length = self::text_length( (string) $response );
			$minimum = absint( $config['type_config']['minimum_characters'] ?? 0 );
			$maximum_characters = absint( $config['type_config']['maximum_characters'] ?? 0 );
			if ( $minimum && $length < $minimum ) {
				return self::result( false, 'response_too_short', $original, 0, $config['graded'] ? $config['points'] : 0, false, null, false, $config, sprintf( __( 'Please enter at least %d non-space characters.', 'parish-formation' ), $minimum ) );
			}
			if ( $maximum_characters && $length > $maximum_characters ) {
				return self::result( false, 'response_too_long', $original, 0, $config['graded'] ? $config['points'] : 0, false, null, false, $config, sprintf( __( 'Please use no more than %d non-space characters.', 'parish-formation' ), $maximum_characters ) );
			}
			$completion_credit = ! empty( $config['type_config']['completion_credit'] );
			$maximum_points = $completion_credit || $config['graded'] ? $config['points'] : 0;
			return self::result( true, '', $original, $completion_credit ? $maximum_points : 0, $maximum_points, true, null, ! empty( $config['manual_review'] ), $config );
		}

		if ( 'paragraph' === $type ) {
			$maximum = $config['graded'] ? $config['points'] : 0;
			return self::result( true, '', $original, 0, $maximum, true, null, true, $config );
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

	private static function normalize_short_answer( $answer, $settings ) {
		$answer = (string) $answer;
		if ( ! empty( $settings['trim_spaces'] ) ) { $answer = trim( $answer ); }
		if ( ! empty( $settings['normalize_spaces'] ) ) { $answer = preg_replace( '/\s+/u', ' ', $answer ); }
		if ( ! empty( $settings['ignore_punctuation'] ) ) { $answer = preg_replace( '/[^\p{L}\p{N}\s]/u', '', $answer ); }
		if ( empty( $settings['case_sensitive'] ) ) { $answer = function_exists( 'mb_strtolower' ) ? mb_strtolower( $answer, 'UTF-8' ) : strtolower( $answer ); }
		return $answer;
	}

	private static function normalize_blank_answer( $answer, $settings ) {
		$answer = (string) $answer;
		if ( 'normalized' === ( $settings['match_mode'] ?? 'normalized' ) ) {
			$answer = trim( preg_replace( '/\s+/u', ' ', $answer ) );
		}
		if ( empty( $settings['case_sensitive'] ) ) { $answer = function_exists( 'mb_strtolower' ) ? mb_strtolower( $answer, 'UTF-8' ) : strtolower( $answer ); }
		return $answer;
	}

	private static function text_length( $text ) {
		$text = preg_replace( '/\s+/u', '', (string) $text );
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/** Resolve legacy positions/text and current stable IDs to one choice ID. */
	private static function resolve_choice_id( $reference, $choices ) {
		$reference = sanitize_text_field( (string) $reference );
		if ( '' === $reference ) { return ''; }
		$position = null;
		if ( ctype_digit( $reference ) ) {
			$position = (int) $reference;
		} elseif ( preg_match( '/^(?:legacy-)?choice-(\d+)$/', $reference, $matches ) ) {
			$position = (int) $matches[1];
		}
		if ( null !== $position && isset( $choices[ $position - 1 ] ) ) {
			return $choices[ $position - 1 ]['id'];
		}
		foreach ( $choices as $choice ) {
			if ( strtolower( $choice['id'] ) === strtolower( $reference ) || strtolower( $choice['label'] ) === strtolower( $reference ) ) {
				return $choice['id'];
			}
		}
		return $reference;
	}
}
