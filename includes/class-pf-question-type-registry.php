<?php
/** Central assessment question-type registry. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Parish_Formation_Question_Type_Registry {
	/** Return registered categories and question definitions. */
	public static function all() {
		return array(
			'multiple_choice' => self::type( 'multiple_choice', __( 'Multiple Choice', 'parish-formation' ), 'automatic', true, true, false ),
			'multiple_select' => self::type( 'multiple_select', __( 'Multiple Select', 'parish-formation' ), 'automatic', true, true, false ),
			'true_false'      => self::type( 'true_false', __( 'True/False', 'parish-formation' ), 'automatic', true, true, false ),
			'short_answer'    => self::type( 'short_answer', __( 'Short Answer', 'parish-formation' ), 'automatic', true, true, false ),
			'fill_blank'      => self::type( 'fill_blank', __( 'Fill in the Blank', 'parish-formation' ), 'automatic', true, true, false ),
			'matching'        => self::type( 'matching', __( 'Matching', 'parish-formation' ), 'automatic', true, true, false ),
			'ordering'        => self::type( 'ordering', __( 'Ordering', 'parish-formation' ), 'automatic', true, true, false ),
			'numeric'         => self::type( 'numeric', __( 'Numeric Response', 'parish-formation' ), 'automatic', true, true, false ),
			'paragraph'       => self::type( 'paragraph', __( 'Paragraph Response', 'parish-formation' ), 'review', false, true, true ),
			'file_upload'     => self::type( 'file_upload', __( 'File Upload', 'parish-formation' ), 'review', true, true, true ),
			'reflection'      => self::type( 'reflection', __( 'Reflection Response', 'parish-formation' ), 'formation', true, false, false ),
			'rating_scale'    => self::type( 'rating_scale', __( 'Rating Scale', 'parish-formation' ), 'formation', true, false, false ),
			'yes_no'          => self::type( 'yes_no', __( 'Yes/No', 'parish-formation' ), 'formation', true, false, false ),
			'acknowledgement' => self::type( 'acknowledgement', __( 'Acknowledgment', 'parish-formation' ), 'formation', true, false, false ),
			'image_selection' => self::type( 'image_selection', __( 'Image Selection', 'parish-formation' ), 'formation', true, true, false ),
		);
	}

	/** Category labels in requested display order. */
	public static function categories() {
		return array(
			'automatic' => __( 'Automatically Graded', 'parish-formation' ),
			'review'    => __( 'Instructor Reviewed', 'parish-formation' ),
			'formation' => __( 'Formation and Feedback', 'parish-formation' ),
		);
	}

	/** Normalize aliases without rewriting historical rows. */
	public static function normalize( $type ) {
		$type = sanitize_key( $type );
		$aliases = array( 'reflection_response' => 'reflection', 'acknowledgment' => 'acknowledgement', 'paragraph_text' => 'paragraph', 'numeric_response' => 'numeric', 'fill_in_the_blank' => 'fill_blank' );
		$type = $aliases[ $type ] ?? $type;
		return isset( self::all()[ $type ] ) ? $type : 'multiple_choice';
	}

	public static function get( $type ) {
		$types = self::all();
		return $types[ self::normalize( $type ) ];
	}

	public static function implemented( $type ) {
		return (bool) self::get( $type )['implemented'];
	}

	private static function type( $id, $label, $category, $implemented, $graded_default, $manual_review_default ) {
		return array( 'id' => $id, 'label' => $label, 'category' => $category, 'implemented' => $implemented, 'graded_default' => $graded_default, 'manual_review_default' => $manual_review_default );
	}
}
