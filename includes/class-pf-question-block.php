<?php
/**
 * Registers the assessment question block and synchronizes question records.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Assessment question block integration. */
final class Parish_Formation_Question_Block {

	public const BLOCK_NAME = 'parish-formation/question';

	/** Register block assets and the dynamic block. */
	public static function register() {
		wp_register_script(
			'parish-formation-question-block-editor',
			PARISH_FORMATION_PLUGIN_URL . 'assets/js/question-block.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
			(string) filemtime( PARISH_FORMATION_PLUGIN_DIR . 'assets/js/question-block.js' ),
			true
		);
		wp_register_style(
			'parish-formation-question-block-editor',
			PARISH_FORMATION_PLUGIN_URL . 'assets/css/question-block.css',
			array( 'wp-edit-blocks' ),
			(string) filemtime( PARISH_FORMATION_PLUGIN_DIR . 'assets/css/question-block.css' )
		);

		register_block_type(
			self::BLOCK_NAME,
			array(
				'api_version'   => 3,
				'editor_script' => 'parish-formation-question-block-editor',
				'editor_style'  => 'parish-formation-question-block-editor',
				'render_callback' => '__return_empty_string',
				'attributes'    => array(
					'questionId' => array( 'type' => 'integer', 'default' => 0 ),
					'prompt'     => array( 'type' => 'string', 'default' => '' ),
					'type'       => array( 'type' => 'string', 'default' => 'multiple_choice' ),
					'options'    => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'string' ) ),
					'choices'    => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'object' ) ),
					'answer'     => array( 'type' => 'string', 'default' => '' ),
					'acceptedAnswers' => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'string' ) ),
					'gradingMode' => array( 'type' => 'string', 'default' => 'all_or_nothing' ),
					'caseSensitive' => array( 'type' => 'boolean', 'default' => false ),
					'trimSpaces' => array( 'type' => 'boolean', 'default' => true ),
					'normalizeSpaces' => array( 'type' => 'boolean', 'default' => false ),
					'ignorePunctuation' => array( 'type' => 'boolean', 'default' => false ),
					'matchMode' => array( 'type' => 'string', 'default' => 'exact' ),
					'blanks' => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'object' ) ),
					'blankPointMode' => array( 'type' => 'string', 'default' => 'equal' ),
					'matchingPairs' => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'object' ) ),
					'matchingPointMode' => array( 'type' => 'string', 'default' => 'equal' ),
					'orderingItems' => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'object' ) ),
					'orderingPointMode' => array( 'type' => 'string', 'default' => 'equal' ),
					'orderingGradingMode' => array( 'type' => 'string', 'default' => 'all_or_nothing' ),
					'reflectionMinCharacters' => array( 'type' => 'integer', 'default' => 0 ),
					'reflectionMaxCharacters' => array( 'type' => 'integer', 'default' => 0 ),
					'reflectionCompletionCredit' => array( 'type' => 'boolean', 'default' => false ),
					'reflectionPrivateNotice' => array( 'type' => 'string', 'default' => '' ),
					'reflectionSamplePrompt' => array( 'type' => 'string', 'default' => '' ),
					'acknowledgementCheckboxLabel' => array( 'type' => 'string', 'default' => 'I acknowledge this statement.' ),
					'acknowledgementPolicyUrl' => array( 'type' => 'string', 'default' => '' ),
					'acknowledgementRequireOpen' => array( 'type' => 'boolean', 'default' => false ),
					'acknowledgementCompletionCredit' => array( 'type' => 'boolean', 'default' => false ),
					'ratingMinimum' => array( 'type' => 'integer', 'default' => 1 ),
					'ratingMaximum' => array( 'type' => 'integer', 'default' => 5 ),
					'ratingFirstLabel' => array( 'type' => 'string', 'default' => 'Lowest' ),
					'ratingLastLabel' => array( 'type' => 'string', 'default' => 'Highest' ),
					'ratingValueLabels' => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'string' ) ),
					'ratingOrientation' => array( 'type' => 'string', 'default' => 'horizontal' ),
					'yesNoYesLabel' => array( 'type' => 'string', 'default' => 'Yes' ),
					'yesNoNoLabel' => array( 'type' => 'string', 'default' => 'No' ),
					'yesNoCorrectAnswer' => array( 'type' => 'string', 'default' => '' ),
					'yesNoYesMessage' => array( 'type' => 'string', 'default' => '' ),
					'yesNoNoMessage' => array( 'type' => 'string', 'default' => '' ),
					'imageChoices' => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'object' ) ),
					'imageSelectionMode' => array( 'type' => 'string', 'default' => 'single' ),
					'imageGradingMode' => array( 'type' => 'string', 'default' => 'all_or_nothing' ),
					'numericAnswerMode' => array( 'type' => 'string', 'default' => 'exact' ),
					'numericExpected' => array( 'type' => 'string', 'default' => '' ),
					'numericMinimum' => array( 'type' => 'string', 'default' => '' ),
					'numericMaximum' => array( 'type' => 'string', 'default' => '' ),
					'numericTolerance' => array( 'type' => 'string', 'default' => '0' ),
					'numericIntegerOnly' => array( 'type' => 'boolean', 'default' => false ),
					'numericDecimalPrecision' => array( 'type' => 'integer', 'default' => 2 ),
					'numericUnitLabel' => array( 'type' => 'string', 'default' => '' ),
					'numericRequireUnit' => array( 'type' => 'boolean', 'default' => false ),
					'fileAllowedExtensions' => array( 'type' => 'string', 'default' => 'pdf, doc, docx, jpg, jpeg, png' ),
					'fileAllowedMimeTypes' => array( 'type' => 'string', 'default' => '' ),
					'fileMaxSizeMb' => array( 'type' => 'string', 'default' => '5' ),
					'fileMinimumCount' => array( 'type' => 'integer', 'default' => 1 ),
					'fileMaximumCount' => array( 'type' => 'integer', 'default' => 1 ),
					'fileSubmissionInstructions' => array( 'type' => 'string', 'default' => '' ),
					'points'     => array( 'type' => 'integer', 'default' => 1 ),
					'required'   => array( 'type' => 'boolean', 'default' => true ),
					'instructions' => array( 'type' => 'string', 'default' => '' ),
					'graded' => array( 'type' => 'boolean', 'default' => true ),
					'explanation' => array( 'type' => 'string', 'default' => '' ),
					'correctFeedback' => array( 'type' => 'string', 'default' => '' ),
					'incorrectFeedback' => array( 'type' => 'string', 'default' => '' ),
					'feedbackTiming' => array( 'type' => 'string', 'default' => 'assessment' ),
					'manualReview' => array( 'type' => 'boolean', 'default' => false ),
					'adminNotes' => array( 'type' => 'string', 'default' => '' ),
					'randomizeChoices' => array( 'type' => 'boolean', 'default' => false ),
				),
			)
		);
	}

	/**
	 * Synchronize internal question records from saved assessment blocks.
	 *
	 * @param int     $post_id Assessment ID.
	 * @param WP_Post $post    Assessment post.
	 */
	public static function synchronize( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		if ( ! current_user_can( 'pf_manage_assessments' ) ) {
			return;
		}

		$blocks   = parse_blocks( $post->post_content );
		$question_blocks = self::find_question_blocks( $blocks );
		$existing_ids = self::get_question_ids( $post_id );
		$kept_ids = array();

		foreach ( $question_blocks as $index => $block ) {
			$attributes  = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$question_id = isset( $attributes['questionId'] ) ? absint( $attributes['questionId'] ) : 0;
			if ( $question_id && absint( get_post_meta( $question_id, '_pf_assessment_id', true ) ) !== $post_id ) {
				$question_id = 0;
			}
			if ( ! $question_id && isset( $existing_ids[ $index ] ) ) {
				$question_id = absint( $existing_ids[ $index ] );
			}
			$prompt = isset( $attributes['prompt'] ) ? wp_kses_post( $attributes['prompt'] ) : '';
			if ( '' === trim( wp_strip_all_tags( $prompt ) ) ) {
				continue;
			}
			$question_id = wp_insert_post(
				array(
					'ID'           => $question_id,
					'post_type'    => Parish_Formation_Question_Post_Type::POST_TYPE,
					'post_status'  => 'publish',
					/* translators: Placeholder values are replaced with the contextual count, name, date, status, or label described by the message. */
					'post_title'   => sprintf( __( 'Question %d', 'parish-formation' ), $index + 1 ),
					'post_content' => $prompt,
				),
				true
			);
			if ( is_wp_error( $question_id ) ) {
				continue;
			}

			$type = Parish_Formation_Question_Type_Registry::normalize( $attributes['type'] ?? '' );
			if ( ! Parish_Formation_Question_Type_Registry::implemented( $type ) ) { $type = 'multiple_choice'; }
			$options = isset( $attributes['options'] ) && is_array( $attributes['options'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $attributes['options'] ) ) ) : array();
			$choices = self::sanitize_block_choices( $attributes['choices'] ?? array(), $options, $attributes['answer'] ?? '' );
			$options = array_column( $choices, 'label' );
			$correct_answer = isset( $attributes['answer'] ) ? sanitize_text_field( $attributes['answer'] ) : '';
			if ( 'multiple_choice' === $type ) {
				foreach ( $choices as $choice ) { if ( ! empty( $choice['correct'] ) ) { $correct_answer = $choice['id']; break; } }
			}
			$question_points = max( 1, isset( $attributes['points'] ) ? absint( $attributes['points'] ) : 1 );
			if ( 'fill_blank' === $type && 'custom' === sanitize_key( $attributes['blankPointMode'] ?? '' ) ) {
				$question_points = 0;
				foreach ( is_array( $attributes['blanks'] ?? null ) ? $attributes['blanks'] : array() as $blank ) { $question_points += max( 0, (float) ( $blank['points'] ?? 0 ) ); }
				$question_points = max( 1, $question_points );
			}
			if ( 'matching' === $type && 'custom' === sanitize_key( $attributes['matchingPointMode'] ?? '' ) ) {
				$question_points = 0;
				foreach ( is_array( $attributes['matchingPairs'] ?? null ) ? $attributes['matchingPairs'] : array() as $pair ) { $question_points += max( 0, (float) ( $pair['points'] ?? 0 ) ); }
				$question_points = max( 1, $question_points );
			}
			if ( 'ordering' === $type && 'custom' === sanitize_key( $attributes['orderingPointMode'] ?? '' ) ) {
				$question_points = 0;
				foreach ( is_array( $attributes['orderingItems'] ?? null ) ? $attributes['orderingItems'] : array() as $item ) { $question_points += max( 0, (float) ( $item['points'] ?? 0 ) ); }
				$question_points = max( 1, $question_points );
			}
			update_post_meta( $question_id, '_pf_assessment_id', $post_id );
			update_post_meta( $question_id, '_pf_question_type', $type );
			update_post_meta( $question_id, '_pf_question_order', $index + 1 );
			update_post_meta( $question_id, '_pf_question_points', $question_points );
			update_post_meta( $question_id, '_pf_question_required', ! isset( $attributes['required'] ) || $attributes['required'] ? 1 : 0 );
			update_post_meta( $question_id, '_pf_question_options', $options );
			update_post_meta( $question_id, '_pf_question_correct_answer', strtolower( $correct_answer ) );
			$config = Parish_Formation_Question_Config::sanitize(
				array(
					'type' => $type, 'instructions' => $attributes['instructions'] ?? '', 'required' => ! isset( $attributes['required'] ) || $attributes['required'],
					'graded' => 'rating_scale' === $type ? false : ( 'reflection' === $type ? ! empty( $attributes['reflectionCompletionCredit'] ) : ( 'acknowledgement' === $type ? ! empty( $attributes['acknowledgementCompletionCredit'] ) : ( array_key_exists( 'graded', $attributes ) ? (bool) $attributes['graded'] : true ) ) ),
					'points' => $question_points, 'explanation' => $attributes['explanation'] ?? '',
					'correct_feedback' => $attributes['correctFeedback'] ?? '', 'incorrect_feedback' => $attributes['incorrectFeedback'] ?? '', 'feedback_timing' => $attributes['feedbackTiming'] ?? 'assessment',
					'manual_review' => 'file_upload' === $type ? true : ( array_key_exists( 'manualReview', $attributes ) ? (bool) $attributes['manualReview'] : false ),
					'randomize_choices' => ! empty( $attributes['randomizeChoices'] ),
					'admin_notes' => $attributes['adminNotes'] ?? '', 'choices' => $choices,
					'correct_answer' => $correct_answer,
					'type_config' => array(
						'accepted_answers' => $attributes['acceptedAnswers'] ?? array(),
						'case_sensitive' => ! empty( $attributes['caseSensitive'] ),
						'trim_spaces' => ! array_key_exists( 'trimSpaces', $attributes ) || ! empty( $attributes['trimSpaces'] ),
						'normalize_spaces' => ! empty( $attributes['normalizeSpaces'] ),
						'ignore_punctuation' => ! empty( $attributes['ignorePunctuation'] ),
						'match_mode' => $attributes['matchMode'] ?? 'exact',
						'point_mode' => 'matching' === $type ? ( $attributes['matchingPointMode'] ?? 'equal' ) : ( 'ordering' === $type ? ( $attributes['orderingPointMode'] ?? 'equal' ) : ( $attributes['blankPointMode'] ?? 'equal' ) ),
						'blanks' => self::normalize_block_blanks( $attributes['blanks'] ?? array() ),
						'pairs' => self::normalize_block_pairs( $attributes['matchingPairs'] ?? array() ),
						'items' => self::normalize_block_items( $attributes['orderingItems'] ?? array() ),
						'minimum_characters' => $attributes['reflectionMinCharacters'] ?? 0,
						'maximum_characters' => $attributes['reflectionMaxCharacters'] ?? 0,
						'private_notice' => $attributes['reflectionPrivateNotice'] ?? '',
						'sample_prompt' => $attributes['reflectionSamplePrompt'] ?? '',
						'checkbox_label' => $attributes['acknowledgementCheckboxLabel'] ?? '',
						'policy_url' => $attributes['acknowledgementPolicyUrl'] ?? '',
						'require_policy_open' => ! empty( $attributes['acknowledgementRequireOpen'] ),
						'completion_credit' => 'acknowledgement' === $type ? ! empty( $attributes['acknowledgementCompletionCredit'] ) : ! empty( $attributes['reflectionCompletionCredit'] ),
						'minimum' => 'numeric' === $type ? ( $attributes['numericMinimum'] ?? '' ) : ( $attributes['ratingMinimum'] ?? 1 ),
						'maximum' => 'numeric' === $type ? ( $attributes['numericMaximum'] ?? '' ) : ( $attributes['ratingMaximum'] ?? 5 ),
						'first_label' => $attributes['ratingFirstLabel'] ?? 'Lowest',
						'last_label' => $attributes['ratingLastLabel'] ?? 'Highest',
						'value_labels' => self::rating_value_labels( $attributes ),
						'orientation' => $attributes['ratingOrientation'] ?? 'horizontal',
						'yes_label' => $attributes['yesNoYesLabel'] ?? 'Yes',
						'no_label' => $attributes['yesNoNoLabel'] ?? 'No',
						'correct_answer' => $attributes['yesNoCorrectAnswer'] ?? '',
						'yes_message' => $attributes['yesNoYesMessage'] ?? '',
						'no_message' => $attributes['yesNoNoMessage'] ?? '',
						'images' => $attributes['imageChoices'] ?? array(),
						'selection_mode' => $attributes['imageSelectionMode'] ?? 'single',
						'grading_mode' => 'image_selection' === $type ? ( $attributes['imageGradingMode'] ?? 'all_or_nothing' ) : ( 'ordering' === $type ? ( $attributes['orderingGradingMode'] ?? 'all_or_nothing' ) : ( $attributes['gradingMode'] ?? 'all_or_nothing' ) ),
						'answer_mode' => $attributes['numericAnswerMode'] ?? 'exact',
						'expected' => $attributes['numericExpected'] ?? '',
						'tolerance' => $attributes['numericTolerance'] ?? '0',
						'integer_only' => ! empty( $attributes['numericIntegerOnly'] ),
						'decimal_precision' => $attributes['numericDecimalPrecision'] ?? 2,
						'unit_label' => $attributes['numericUnitLabel'] ?? '',
						'require_unit' => ! empty( $attributes['numericRequireUnit'] ),
						'allowed_extensions' => $attributes['fileAllowedExtensions'] ?? '',
						'allowed_mime_types' => $attributes['fileAllowedMimeTypes'] ?? '',
						'max_file_size' => max( 0.001, (float) ( $attributes['fileMaxSizeMb'] ?? 5 ) ) * MB_IN_BYTES,
						'minimum_files' => $attributes['fileMinimumCount'] ?? 1,
						'maximum_files' => $attributes['fileMaximumCount'] ?? 1,
						'submission_instructions' => $attributes['fileSubmissionInstructions'] ?? '',
					),
				),
				$type
			);
			update_post_meta( $question_id, Parish_Formation_Question_Config::META_KEY, $config );
			$kept_ids[] = $question_id;
		}

		foreach ( self::get_question_ids( $post_id ) as $question_id ) {
			if ( ! in_array( $question_id, $kept_ids, true ) ) {
				wp_trash_post( $question_id );
			}
		}
	}

	private static function rating_value_labels( $attributes ) {
		$minimum = (int) ( $attributes['ratingMinimum'] ?? 1 );
		$labels = array();
		foreach ( is_array( $attributes['ratingValueLabels'] ?? null ) ? $attributes['ratingValueLabels'] : array() as $index => $label ) { if ( '' !== trim( (string) $label ) ) { $labels[ $minimum + $index ] = $label; } }
		return $labels;
	}

	/** Recursively find question blocks. */
	private static function find_question_blocks( $blocks ) {
		$questions = array();
		foreach ( $blocks as $block ) {
			if ( self::BLOCK_NAME === $block['blockName'] ) {
				$questions[] = $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$questions = array_merge( $questions, self::find_question_blocks( $block['innerBlocks'] ) );
			}
		}
		return $questions;
	}

	/** Get active internal question IDs for an assessment. */
	private static function get_question_ids( $assessment_id ) {
		return get_posts(
			array(
				'post_type'      => Parish_Formation_Question_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_pf_question_order', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Question ordering is part of the existing post-meta model.
				'orderby'        => array( 'meta_value_num' => 'ASC', 'ID' => 'ASC' ),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Assessment-question relationships are stored as post meta by the established data model.
					array(
						'key'     => '_pf_assessment_id',
						'value'   => absint( $assessment_id ),
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
	}

	/** Normalize repeatable choice rows while preserving their stable IDs. */
	private static function sanitize_block_choices( $rows, $legacy_options, $legacy_answer ) {
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			$rows = array_map(
				static fn( $label, $index ) => array( 'id' => 'choice-' . ( $index + 1 ), 'label' => $label, 'correct' => (string) ( $index + 1 ) === (string) $legacy_answer ),
				$legacy_options,
				array_keys( $legacy_options )
			);
		}
		$choices = array();
		$used_ids = array();
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$label = sanitize_text_field( $row['label'] ?? '' );
			if ( '' === $label ) { continue; }
			$id = sanitize_key( $row['id'] ?? '' ) ?: wp_unique_id( 'choice-' );
			if ( isset( $used_ids[ $id ] ) ) { $id .= '-' . ( $index + 1 ); }
			$used_ids[ $id ] = true;
			$choices[] = array( 'id' => $id, 'label' => $label, 'correct' => ! empty( $row['correct'] ), 'feedback' => wp_kses_post( $row['feedback'] ?? '' ), 'order' => count( $choices ) + 1 );
		}
		return $choices;
	}

	private static function normalize_block_blanks( $rows ) {
		$blanks = array();
		foreach ( is_array( $rows ) ? $rows : array() as $index => $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$blanks[] = array(
				'id' => $row['id'] ?? 'blank-' . ( $index + 1 ),
				'accepted_answers' => $row['acceptedAnswers'] ?? ( $row['accepted_answers'] ?? array() ),
				'case_sensitive' => ! empty( $row['caseSensitive'] ) || ! empty( $row['case_sensitive'] ),
				'match_mode' => $row['matchMode'] ?? ( $row['match_mode'] ?? 'normalized' ),
				'points' => $row['points'] ?? 1,
			);
		}
		return $blanks;
	}

	private static function normalize_block_pairs( $rows ) {
		$pairs = array();
		foreach ( is_array( $rows ) ? $rows : array() as $index => $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$pairs[] = array( 'id' => $row['id'] ?? 'pair-' . ( $index + 1 ), 'answer_id' => $row['answerId'] ?? ( $row['answer_id'] ?? 'answer-' . ( $index + 1 ) ), 'prompt' => $row['prompt'] ?? '', 'answer' => $row['answer'] ?? '', 'points' => $row['points'] ?? 1 );
		}
		return $pairs;
	}

	private static function normalize_block_items( $rows ) {
		$items = array();
		foreach ( is_array( $rows ) ? $rows : array() as $index => $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$items[] = array( 'id' => $row['id'] ?? 'item-' . ( $index + 1 ), 'label' => $row['label'] ?? '', 'points' => $row['points'] ?? 1 );
		}
		return $items;
	}

}
