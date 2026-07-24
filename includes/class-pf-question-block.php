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
					'answer'     => array( 'type' => 'string', 'default' => '' ),
					'points'     => array( 'type' => 'integer', 'default' => 1 ),
					'required'   => array( 'type' => 'boolean', 'default' => true ),
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
					'post_title'   => sprintf( __( 'Question %d', 'parish-formation' ), $index + 1 ),
					'post_content' => $prompt,
				),
				true
			);
			if ( is_wp_error( $question_id ) ) {
				continue;
			}

			$type = isset( $attributes['type'] ) ? sanitize_key( $attributes['type'] ) : '';
			$type = self::valid_type( $type ) ? $type : 'multiple_choice';
			$options = isset( $attributes['options'] ) && is_array( $attributes['options'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $attributes['options'] ) ) ) : array();
			update_post_meta( $question_id, '_pf_assessment_id', $post_id );
			update_post_meta( $question_id, '_pf_question_type', $type );
			update_post_meta( $question_id, '_pf_question_order', $index + 1 );
			update_post_meta( $question_id, '_pf_question_points', max( 1, isset( $attributes['points'] ) ? absint( $attributes['points'] ) : 1 ) );
			update_post_meta( $question_id, '_pf_question_required', ! isset( $attributes['required'] ) || $attributes['required'] ? 1 : 0 );
			update_post_meta( $question_id, '_pf_question_options', $options );
			update_post_meta( $question_id, '_pf_question_correct_answer', isset( $attributes['answer'] ) ? strtolower( sanitize_text_field( $attributes['answer'] ) ) : '' );
			$kept_ids[] = $question_id;
		}

		foreach ( self::get_question_ids( $post_id ) as $question_id ) {
			if ( ! in_array( $question_id, $kept_ids, true ) ) {
				wp_trash_post( $question_id );
			}
		}
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
				'meta_key'       => '_pf_question_order',
				'orderby'        => array( 'meta_value_num' => 'ASC', 'ID' => 'ASC' ),
				'meta_query'     => array(
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

	private static function valid_type( $value ) {
		return in_array( $value, array( 'multiple_choice', 'true_false', 'acknowledgement', 'reflection' ), true );
	}
}
