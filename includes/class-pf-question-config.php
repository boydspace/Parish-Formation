<?php
/** Normalized question configuration and legacy adapters. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Parish_Formation_Question_Config {
	public const META_KEY = '_pf_question_config';
	public const VERSION  = 1;

	/** Return the effective maximum after applying type-specific point allocation. */
	public static function maximum_points( $config ) {
		$config = is_array( $config ) ? $config : array();
		if ( empty( $config['graded'] ) ) { return 0.0; }
		$type = Parish_Formation_Question_Type_Registry::normalize( $config['type'] ?? '' );
		$type_config = is_array( $config['type_config'] ?? null ) ? $config['type_config'] : array();
		if ( 'custom' === ( $type_config['point_mode'] ?? 'equal' ) ) {
			$items = 'fill_blank' === $type ? ( $type_config['blanks'] ?? array() ) : ( 'matching' === $type ? ( $type_config['pairs'] ?? array() ) : ( 'ordering' === $type ? ( $type_config['items'] ?? array() ) : array() ) );
			if ( $items ) { return max( 0.0, (float) array_sum( array_map( static fn( $item ) => (float) ( $item['points'] ?? 0 ), $items ) ) ); }
		}
		return max( 0.0, (float) ( $config['points'] ?? 0 ) );
	}

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
			'allow_retry' => false, 'max_attempts' => 1, 'randomize_choices' => false, 'manual_review' => $is_legacy && 'reflection' === $type ? true : $definition['manual_review_default'],
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
		if ( 'fill_blank' === $type ) {
			$mode = sanitize_key( $values['point_mode'] ?? 'equal' );
			$blanks = array();
			$used_ids = array();
			foreach ( is_array( $values['blanks'] ?? null ) ? $values['blanks'] : array() as $index => $blank ) {
				if ( ! is_array( $blank ) ) { continue; }
				$answers = array_values( array_filter( array_map( 'sanitize_text_field', is_array( $blank['accepted_answers'] ?? null ) ? $blank['accepted_answers'] : array() ), static fn( $answer ) => '' !== $answer ) );
				$id = sanitize_key( $blank['id'] ?? '' ) ?: 'blank-' . ( $index + 1 );
				if ( isset( $used_ids[ $id ] ) ) { $id .= '-' . ( $index + 1 ); }
				$used_ids[ $id ] = true;
				$blanks[] = array(
					'id' => $id,
					'accepted_answers' => $answers,
					'case_sensitive' => ! empty( $blank['case_sensitive'] ),
					'match_mode' => 'exact' === sanitize_key( $blank['match_mode'] ?? 'normalized' ) ? 'exact' : 'normalized',
					'points' => max( 0, (float) ( $blank['points'] ?? 1 ) ),
				);
			}
			return array( 'point_mode' => 'custom' === $mode ? 'custom' : 'equal', 'blanks' => $blanks );
		}
		if ( 'matching' === $type ) {
			$mode = sanitize_key( $values['point_mode'] ?? 'equal' );
			$pairs = array();
			$used_ids = array();
			foreach ( is_array( $values['pairs'] ?? null ) ? $values['pairs'] : array() as $index => $pair ) {
				if ( ! is_array( $pair ) ) { continue; }
				$prompt = sanitize_text_field( $pair['prompt'] ?? '' );
				$answer = sanitize_text_field( $pair['answer'] ?? '' );
				if ( '' === $prompt || '' === $answer ) { continue; }
				$id = sanitize_key( $pair['id'] ?? '' ) ?: 'pair-' . ( $index + 1 );
				if ( isset( $used_ids[ $id ] ) ) { $id .= '-' . ( $index + 1 ); }
				$used_ids[ $id ] = true;
				$answer_id = sanitize_key( $pair['answer_id'] ?? '' ) ?: 'answer-' . ( $index + 1 );
				$pairs[] = array( 'id' => $id, 'answer_id' => $answer_id, 'prompt' => $prompt, 'answer' => $answer, 'points' => max( 0, (float) ( $pair['points'] ?? 1 ) ), 'order' => count( $pairs ) + 1 );
			}
			return array( 'point_mode' => 'custom' === $mode ? 'custom' : 'equal', 'pairs' => $pairs );
		}
		if ( 'ordering' === $type ) {
			$point_mode = sanitize_key( $values['point_mode'] ?? 'equal' );
			$grading_mode = sanitize_key( $values['grading_mode'] ?? 'all_or_nothing' );
			$items = array();
			$used_ids = array();
			foreach ( is_array( $values['items'] ?? null ) ? $values['items'] : array() as $index => $item ) {
				if ( ! is_array( $item ) ) { $item = array( 'label' => $item ); }
				$label = sanitize_text_field( $item['label'] ?? '' );
				if ( '' === $label ) { continue; }
				$id = sanitize_key( $item['id'] ?? '' ) ?: 'item-' . ( $index + 1 );
				if ( isset( $used_ids[ $id ] ) ) { $id .= '-' . ( $index + 1 ); }
				$used_ids[ $id ] = true;
				$items[] = array( 'id' => $id, 'label' => $label, 'points' => max( 0, (float) ( $item['points'] ?? 1 ) ), 'order' => count( $items ) + 1 );
			}
			return array(
				'point_mode' => 'custom' === $point_mode ? 'custom' : 'equal',
				'grading_mode' => 'partial' === $grading_mode ? 'partial' : 'all_or_nothing',
				'items' => $items,
			);
		}
		if ( 'reflection' === $type ) {
			$minimum = absint( $values['minimum_characters'] ?? 0 );
			$maximum = absint( $values['maximum_characters'] ?? 0 );
			if ( $maximum && $maximum < $minimum ) { $maximum = $minimum; }
			return array(
				'minimum_characters' => $minimum,
				'maximum_characters' => $maximum,
				'completion_credit' => ! empty( $values['completion_credit'] ),
				'private_notice' => wp_kses_post( $values['private_notice'] ?? '' ),
				'sample_prompt' => wp_kses_post( $values['sample_prompt'] ?? '' ),
			);
		}
		if ( 'acknowledgement' === $type ) {
			return array(
				'checkbox_label' => sanitize_text_field( $values['checkbox_label'] ?? '' ) ?: __( 'I acknowledge this statement.', 'parish-formation' ),
				'policy_url' => esc_url_raw( $values['policy_url'] ?? '' ),
				'require_policy_open' => ! empty( $values['require_policy_open'] ),
				'completion_credit' => ! empty( $values['completion_credit'] ),
			);
		}
		if ( 'rating_scale' === $type ) {
			$minimum = (int) ( $values['minimum'] ?? 1 );
			$maximum = (int) ( $values['maximum'] ?? 5 );
			if ( $maximum <= $minimum ) { $maximum = $minimum + 1; }
			if ( $maximum - $minimum > 20 ) { $maximum = $minimum + 20; }
			$labels = array();
			foreach ( is_array( $values['value_labels'] ?? null ) ? $values['value_labels'] : array() as $value => $label ) {
				$value = (int) $value;
				if ( $value >= $minimum && $value <= $maximum && '' !== trim( (string) $label ) ) { $labels[ $value ] = sanitize_text_field( $label ); }
			}
			return array(
				'minimum' => $minimum,
				'maximum' => $maximum,
				'first_label' => sanitize_text_field( $values['first_label'] ?? '' ) ?: __( 'Lowest', 'parish-formation' ),
				'last_label' => sanitize_text_field( $values['last_label'] ?? '' ) ?: __( 'Highest', 'parish-formation' ),
				'value_labels' => $labels,
				'orientation' => 'vertical' === sanitize_key( $values['orientation'] ?? 'horizontal' ) ? 'vertical' : 'horizontal',
			);
		}
		if ( 'yes_no' === $type ) {
			$correct = sanitize_key( $values['correct_answer'] ?? '' );
			return array(
				'yes_label' => sanitize_text_field( $values['yes_label'] ?? '' ) ?: __( 'Yes', 'parish-formation' ),
				'no_label' => sanitize_text_field( $values['no_label'] ?? '' ) ?: __( 'No', 'parish-formation' ),
				'correct_answer' => in_array( $correct, array( 'yes', 'no' ), true ) ? $correct : '',
				'yes_message' => wp_kses_post( $values['yes_message'] ?? '' ),
				'no_message' => wp_kses_post( $values['no_message'] ?? '' ),
			);
		}
		if ( 'image_selection' === $type ) {
			$mode = 'multiple' === sanitize_key( $values['selection_mode'] ?? 'single' ) ? 'multiple' : 'single';
			$grading_mode = sanitize_key( $values['grading_mode'] ?? 'all_or_nothing' );
			$images = array();
			$used_ids = array();
			foreach ( is_array( $values['images'] ?? null ) ? $values['images'] : array() as $index => $image ) {
				if ( ! is_array( $image ) ) { continue; }
				$attachment_id = absint( $image['attachment_id'] ?? ( $image['attachmentId'] ?? 0 ) );
				$alt = sanitize_text_field( $image['alt'] ?? '' );
				$attachment = $attachment_id ? get_post( $attachment_id ) : null;
				$mime_type = $attachment ? (string) get_post_mime_type( $attachment ) : '';
				if ( ! $attachment || 'attachment' !== $attachment->post_type || '' === $alt || ! str_starts_with( $mime_type, 'image/' ) ) { continue; }
				$id = sanitize_key( $image['id'] ?? '' ) ?: 'image-' . ( $index + 1 );
				if ( isset( $used_ids[ $id ] ) ) { $id .= '-' . ( $index + 1 ); }
				$used_ids[ $id ] = true;
				$images[] = array( 'id' => $id, 'attachment_id' => $attachment_id, 'label' => sanitize_text_field( $image['label'] ?? '' ), 'alt' => $alt, 'correct' => ! empty( $image['correct'] ), 'order' => count( $images ) + 1 );
			}
			return array(
				'selection_mode' => $mode,
				'grading_mode' => in_array( $grading_mode, array( 'all_or_nothing', 'partial', 'partial_penalty' ), true ) ? $grading_mode : 'all_or_nothing',
				'images' => $images,
			);
		}
		if ( 'numeric' === $type ) {
			$mode = 'range' === sanitize_key( $values['answer_mode'] ?? 'exact' ) ? 'range' : 'exact';
			return array(
				'answer_mode' => $mode,
				'expected' => self::optional_number( $values['expected'] ?? null ),
				'minimum' => self::optional_number( $values['minimum'] ?? null ),
				'maximum' => self::optional_number( $values['maximum'] ?? null ),
				'tolerance' => max( 0, (float) ( $values['tolerance'] ?? 0 ) ),
				'integer_only' => ! empty( $values['integer_only'] ),
				'decimal_precision' => min( 10, absint( $values['decimal_precision'] ?? 2 ) ),
				'unit_label' => sanitize_text_field( $values['unit_label'] ?? '' ),
				'require_unit' => ! empty( $values['require_unit'] ),
			);
		}
		if ( 'file_upload' === $type ) {
			$blocked = array( 'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe', 'com', 'bat', 'cmd', 'sh', 'js', 'html', 'htm', 'svg' );
			$extensions = is_array( $values['allowed_extensions'] ?? null ) ? $values['allowed_extensions'] : preg_split( '/[\s,]+/', (string) ( $values['allowed_extensions'] ?? '' ) );
			$extensions = array_values( array_diff( array_unique( array_filter( array_map( static fn( $value ) => sanitize_key( ltrim( (string) $value, '.' ) ), $extensions ) ) ), $blocked ) );
			if ( ! $extensions ) { $extensions = array( 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png' ); }
			$mime_types = is_array( $values['allowed_mime_types'] ?? null ) ? $values['allowed_mime_types'] : preg_split( '/[\r\n,]+/', (string) ( $values['allowed_mime_types'] ?? '' ) );
			$mime_types = array_values( array_filter( array_map( 'sanitize_mime_type', $mime_types ) ) );
			$minimum = max( 0, absint( $values['minimum_files'] ?? 1 ) );
			$maximum = min( 10, max( 1, absint( $values['maximum_files'] ?? 1 ) ) );
			if ( $minimum > $maximum ) { $minimum = $maximum; }
			return array(
				'allowed_extensions' => $extensions,
				'allowed_mime_types' => $mime_types,
				'max_file_size' => min( wp_max_upload_size(), max( 1024, absint( $values['max_file_size'] ?? 5 * MB_IN_BYTES ) ) ),
				'minimum_files' => $minimum,
				'maximum_files' => $maximum,
				'submission_instructions' => wp_kses_post( $values['submission_instructions'] ?? '' ),
			);
		}
		return self::sanitize_nested( $values );
	}

	private static function optional_number( $value ) {
		if ( null === $value || '' === trim( (string) $value ) || ! is_numeric( $value ) ) { return null; }
		return (float) $value;
	}
}
