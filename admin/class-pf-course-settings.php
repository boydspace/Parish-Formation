<?php
/**
 * Provides additional course settings.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Course settings meta box and persistence.
 */
final class Parish_Formation_Course_Settings {

	/**
	 * Course completion message meta key.
	 */
	private const COMPLETION_MESSAGE_META_KEY = '_pf_completion_message';

	/**
	 * Nonce action.
	 */
	private const NONCE_ACTION = 'pf_save_course_settings';

	/**
	 * Nonce field name.
	 */
	private const NONCE_NAME = 'pf_course_settings_nonce';

	/**
	 * Register the course settings meta box.
	 *
	 * @return void
	 */
	public static function register_meta_box() {
		add_meta_box(
			'pf-course-settings',
			__( 'Course Completion', 'parish-formation' ),
			array( self::class, 'render_meta_box' ),
			Parish_Formation_Course_Post_Type::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'pf-course-lessons',
			__( 'Course Lessons', 'parish-formation' ),
			array( self::class, 'render_lessons_meta_box' ),
			Parish_Formation_Course_Post_Type::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render the course completion field.
	 *
	 * @param WP_Post $post Current course.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		$completion_message = get_post_meta( $post->ID, self::COMPLETION_MESSAGE_META_KEY, true );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<p>
			<label for="pf-completion-message">
				<?php echo esc_html__( 'Completion message', 'parish-formation' ); ?>
			</label>
		</p>
		<textarea
			id="pf-completion-message"
			name="pf_completion_message"
			rows="6"
			class="widefat"
		><?php echo esc_textarea( $completion_message ); ?></textarea>
		<p class="description">
			<?php echo esc_html__( 'Enter the parish instructions participants should see after completing this course.', 'parish-formation' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the lessons assigned to the course.
	 *
	 * @param WP_Post $post Current course.
	 * @return void
	 */
	public static function render_lessons_meta_box( $post ) {
		$lessons = Parish_Formation_Lesson_Settings::get_course_lessons( $post->ID );

		if ( ! $lessons ) {
			?>
			<p><?php echo esc_html__( 'No lessons are assigned to this course yet.', 'parish-formation' ); ?></p>
			<?php
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Lesson number', 'parish-formation' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Lesson', 'parish-formation' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Requirement', 'parish-formation' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Status', 'parish-formation' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $lessons as $lesson ) : ?>
					<tr>
						<td><?php echo esc_html( Parish_Formation_Lesson_Settings::get_lesson_order( $lesson->ID ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $lesson->ID ) ); ?>">
								<?php echo esc_html( $lesson->post_title ); ?>
							</a>
						</td>
						<td>
							<?php
							echo Parish_Formation_Lesson_Settings::is_required( $lesson->ID )
								? esc_html__( 'Required', 'parish-formation' )
								: esc_html__( 'Optional', 'parish-formation' );
							?>
						</td>
						<td><?php echo esc_html( get_post_status_object( $lesson->post_status )->label ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Save course settings.
	 *
	 * @param int $post_id Course post ID.
	 * @return void
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'pf_manage_courses' ) ) {
			return;
		}

		$completion_message = isset( $_POST['pf_completion_message'] )
			? wp_kses_post( wp_unslash( $_POST['pf_completion_message'] ) )
			: '';

		if ( '' === $completion_message ) {
			delete_post_meta( $post_id, self::COMPLETION_MESSAGE_META_KEY );
			return;
		}

		update_post_meta( $post_id, self::COMPLETION_MESSAGE_META_KEY, $completion_message );
	}
}
