<?php
/**
 * Provides grading settings for assessments.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Assessment settings persistence. */
final class Parish_Formation_Assessment_Settings {

	public const COURSE_META_KEY      = '_pf_course_id';
	public const GRADING_META_KEY     = '_pf_assessment_grading_timing';
	public const PROGRESSION_META_KEY = '_pf_assessment_progression';
	public const PASSING_RULE_META_KEY = '_pf_assessment_passing_rule';
	public const PASSING_VALUE_META_KEY = '_pf_assessment_passing_value';
	public const MAX_ATTEMPTS_META_KEY = '_pf_assessment_max_attempts';

	private const NONCE_ACTION = 'pf_save_assessment_settings';
	private const NONCE_NAME   = 'pf_assessment_settings_nonce';
	private const QUICK_EDIT_NONCE_NAME = 'pf_assessment_quick_edit_nonce';

	/** Register the assessment settings meta box. */
	public static function register_meta_box() {
		add_meta_box(
			'pf-assessment-settings',
			__( 'Assessment Settings', 'parish-formation' ),
			array( self::class, 'render_meta_box' ),
			Parish_Formation_Assessment_Post_Type::POST_TYPE,
			'side',
			'high'
		);
	}

	/** Render grading behavior settings. */
	public static function render_meta_box( $post ) {
		$courses = get_posts(
			array(
				'post_type'      => Parish_Formation_Course_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$course_id   = absint( get_post_meta( $post->ID, self::COURSE_META_KEY, true ) );
		$grading     = get_post_meta( $post->ID, self::GRADING_META_KEY, true );
		$progression = get_post_meta( $post->ID, self::PROGRESSION_META_KEY, true );
		$passing_rule = get_post_meta( $post->ID, self::PASSING_RULE_META_KEY, true );
		$passing_value = get_post_meta( $post->ID, self::PASSING_VALUE_META_KEY, true );
		$max_attempts = absint( get_post_meta( $post->ID, self::MAX_ATTEMPTS_META_KEY, true ) );
		$grading     = self::valid_grading( $grading ) ? $grading : 'immediate';
		$progression = self::valid_progression( $progression ) ? $progression : 'pass_to_continue';
		$passing_rule = self::valid_passing_rule( $passing_rule ) ? $passing_rule : 'percentage';
		$passing_value = is_numeric( $passing_value ) ? max( 0, (float) $passing_value ) : 100;
		$max_attempts = max( 1, $max_attempts );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<p><label for="pf-assessment-course"><strong><?php esc_html_e( 'Course', 'parish-formation' ); ?></strong></label></p>
		<select id="pf-assessment-course" name="pf_assessment_course" class="widefat">
			<option value="0"><?php esc_html_e( 'Not assigned', 'parish-formation' ); ?></option>
			<?php foreach ( $courses as $course ) : ?>
				<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_id, $course->ID ); ?>><?php echo esc_html( $course->post_title ); ?></option>
			<?php endforeach; ?>
		</select>

		<p><label for="pf-assessment-passing-rule"><strong><?php esc_html_e( 'Passing score based on', 'parish-formation' ); ?></strong></label></p>
		<select id="pf-assessment-passing-rule" name="pf_assessment_passing_rule" class="widefat">
			<option value="percentage" <?php selected( $passing_rule, 'percentage' ); ?>><?php esc_html_e( 'Percentage', 'parish-formation' ); ?></option>
			<option value="correct_count" <?php selected( $passing_rule, 'correct_count' ); ?>><?php esc_html_e( 'Number correct', 'parish-formation' ); ?></option>
			<option value="points" <?php selected( $passing_rule, 'points' ); ?>><?php esc_html_e( 'Points', 'parish-formation' ); ?></option>
		</select>
		<p><label for="pf-assessment-passing-value"><strong><?php esc_html_e( 'Passing value', 'parish-formation' ); ?></strong></label></p>
		<input id="pf-assessment-passing-value" name="pf_assessment_passing_value" type="number" min="0" step="0.01" value="<?php echo esc_attr( $passing_value ); ?>" class="widefat" />

		<p><label for="pf-assessment-max-attempts"><strong><?php esc_html_e( 'Maximum attempts', 'parish-formation' ); ?></strong></label></p>
		<input id="pf-assessment-max-attempts" name="pf_assessment_max_attempts" type="number" min="1" step="1" value="<?php echo esc_attr( $max_attempts ); ?>" class="widefat" />
		<p class="description"><?php esc_html_e( 'Set to 1 to disable multiple attempts.', 'parish-formation' ); ?></p>

		<p><label for="pf-assessment-grading"><strong><?php esc_html_e( 'Grading', 'parish-formation' ); ?></strong></label></p>
		<select id="pf-assessment-grading" name="pf_assessment_grading" class="widefat">
			<option value="immediate" <?php selected( $grading, 'immediate' ); ?>><?php esc_html_e( 'Grade this assessment immediately', 'parish-formation' ); ?></option>
			<option value="course_end" <?php selected( $grading, 'course_end' ); ?>><?php esc_html_e( 'Include in course grade at the end', 'parish-formation' ); ?></option>
		</select>

		<p><label for="pf-assessment-progression"><strong><?php esc_html_e( 'Progression', 'parish-formation' ); ?></strong></label></p>
		<select id="pf-assessment-progression" name="pf_assessment_progression" class="widefat">
			<option value="pass_to_continue" <?php selected( $progression, 'pass_to_continue' ); ?>><?php esc_html_e( 'Require passing to continue', 'parish-formation' ); ?></option>
			<option value="submit_to_continue" <?php selected( $progression, 'submit_to_continue' ); ?>><?php esc_html_e( 'Require submission to continue', 'parish-formation' ); ?></option>
			<option value="no_gate" <?php selected( $progression, 'no_gate' ); ?>><?php esc_html_e( 'Do not block progression', 'parish-formation' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Add questions with the block inserter, then arrange this assessment with the lessons from the course editor.', 'parish-formation' ); ?></p>
		<?php
	}

	/** Save assessment-wide settings. */
	public static function save( $post_id ) {
		$full_edit  = self::verify_nonce( self::NONCE_NAME );
		$quick_edit = self::verify_nonce( self::QUICK_EDIT_NONCE_NAME );
		if ( ( ! $full_edit && ! $quick_edit ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'pf_manage_assessments' ) ) {
			return;
		}

		$old_course_id = absint( get_post_meta( $post_id, self::COURSE_META_KEY, true ) );
		$course_id     = isset( $_POST['pf_assessment_course'] ) ? absint( $_POST['pf_assessment_course'] ) : 0;
		if ( $course_id && Parish_Formation_Course_Post_Type::POST_TYPE === get_post_type( $course_id ) ) {
			update_post_meta( $post_id, self::COURSE_META_KEY, $course_id );
		} else {
			delete_post_meta( $post_id, self::COURSE_META_KEY );
			$course_id = 0;
		}
		if ( $old_course_id !== $course_id ) {
			delete_post_meta( $post_id, Parish_Formation_Course_Settings::CURRICULUM_ORDER_META_KEY );
		}

		if ( $full_edit ) {
			$grading = isset( $_POST['pf_assessment_grading'] ) ? sanitize_key( wp_unslash( $_POST['pf_assessment_grading'] ) ) : '';
			update_post_meta( $post_id, self::GRADING_META_KEY, self::valid_grading( $grading ) ? $grading : 'immediate' );
			$progression = isset( $_POST['pf_assessment_progression'] ) ? sanitize_key( wp_unslash( $_POST['pf_assessment_progression'] ) ) : '';
			update_post_meta( $post_id, self::PROGRESSION_META_KEY, self::valid_progression( $progression ) ? $progression : 'pass_to_continue' );
			$passing_rule = isset( $_POST['pf_assessment_passing_rule'] ) ? sanitize_key( wp_unslash( $_POST['pf_assessment_passing_rule'] ) ) : '';
			update_post_meta( $post_id, self::PASSING_RULE_META_KEY, self::valid_passing_rule( $passing_rule ) ? $passing_rule : 'percentage' );
			$passing_value = isset( $_POST['pf_assessment_passing_value'] ) ? (float) wp_unslash( $_POST['pf_assessment_passing_value'] ) : 100;
			update_post_meta( $post_id, self::PASSING_VALUE_META_KEY, max( 0, $passing_value ) );
			$max_attempts = isset( $_POST['pf_assessment_max_attempts'] ) ? absint( $_POST['pf_assessment_max_attempts'] ) : 1;
			update_post_meta( $post_id, self::MAX_ATTEMPTS_META_KEY, max( 1, $max_attempts ) );
		}
	}

	/** Add the course column to the Assessments list. */
	public static function add_list_columns( $columns ) {
		$columns['pf_assessment_course'] = __( 'Course', 'parish-formation' );
		return $columns;
	}

	/** Render the assessment course column and Quick Edit data. */
	public static function render_list_column( $column_name, $post_id ) {
		if ( 'pf_assessment_course' !== $column_name ) {
			return;
		}
		$course_id = absint( get_post_meta( $post_id, self::COURSE_META_KEY, true ) );
		?>
		<span class="pf-assessment-quick-edit-data" data-course-id="<?php echo esc_attr( $course_id ); ?>">
			<?php echo $course_id ? esc_html( get_the_title( $course_id ) ) : '&mdash;'; ?>
		</span>
		<?php
	}

	/** Render the course selector in Assessment Quick Edit. */
	public static function render_quick_edit_fields( $column_name, $post_type ) {
		if ( 'pf_assessment_course' !== $column_name || Parish_Formation_Assessment_Post_Type::POST_TYPE !== $post_type ) {
			return;
		}
		$courses = get_posts(
			array(
				'post_type'      => Parish_Formation_Course_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		wp_nonce_field( self::NONCE_ACTION, self::QUICK_EDIT_NONCE_NAME );
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php esc_html_e( 'Course', 'parish-formation' ); ?></span>
					<select name="pf_assessment_course">
						<option value="0"><?php esc_html_e( 'Not assigned', 'parish-formation' ); ?></option>
						<?php foreach ( $courses as $course ) : ?>
							<option value="<?php echo esc_attr( $course->ID ); ?>"><?php echo esc_html( $course->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/** Load the Quick Edit helper on the Assessments list. */
	public static function enqueue_quick_edit_script( $hook_suffix ) {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || Parish_Formation_Assessment_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script( 'parish-formation-assessment-quick-edit', PARISH_FORMATION_PLUGIN_URL . 'assets/js/assessment-quick-edit.js', array( 'jquery', 'inline-edit-post' ), PARISH_FORMATION_VERSION, true );
	}

	/** Verify a submitted settings nonce. */
	private static function verify_nonce( $field_name ) {
		if ( ! isset( $_POST[ $field_name ] ) ) {
			return false;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) );
		return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	private static function valid_grading( $value ) {
		return in_array( $value, array( 'immediate', 'course_end' ), true );
	}

	private static function valid_progression( $value ) {
		return in_array( $value, array( 'pass_to_continue', 'submit_to_continue', 'no_gate' ), true );
	}

	private static function valid_passing_rule( $value ) {
		return in_array( $value, array( 'percentage', 'correct_count', 'points' ), true );
	}
}
