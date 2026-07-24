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

	/** Shared curriculum order metadata for lessons and assessments. */
	public const CURRICULUM_ORDER_META_KEY = '_pf_curriculum_order';

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
			__( 'Course Curriculum', 'parish-formation' ),
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
		$assessments = get_posts(
			array(
				'post_type'      => Parish_Formation_Assessment_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => Parish_Formation_Assessment_Settings::COURSE_META_KEY,
						'value'   => $post->ID,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		$items = array();
		foreach ( $lessons as $index => $lesson ) {
			$order   = absint( get_post_meta( $lesson->ID, self::CURRICULUM_ORDER_META_KEY, true ) );
			$items[] = array( 'post' => $lesson, 'type' => 'lesson', 'order' => $order ? $order : ( $index + 1 ) * 10 );
		}
		foreach ( $assessments as $index => $assessment ) {
			$order   = absint( get_post_meta( $assessment->ID, self::CURRICULUM_ORDER_META_KEY, true ) );
			$items[] = array( 'post' => $assessment, 'type' => 'assessment', 'order' => $order ? $order : 100000 + $index );
		}
		usort(
			$items,
			static function ( $first, $second ) {
				if ( $first['order'] === $second['order'] ) {
					return strcasecmp( $first['post']->post_title, $second['post']->post_title );
				}
				return $first['order'] <=> $second['order'];
			}
		);

		if ( ! $items ) {
			?>
			<p><?php echo esc_html__( 'No lessons or assessments are assigned to this course yet.', 'parish-formation' ); ?></p>
			<?php
			return;
		}
		?>
		<p><?php esc_html_e( 'Drag lessons and assessments into the desired participant sequence. Changes save immediately.', 'parish-formation' ); ?></p>
		<ul id="pf-course-curriculum" class="pf-course-curriculum" data-course-id="<?php echo esc_attr( $post->ID ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<li class="pf-curriculum-item" data-item-id="<?php echo esc_attr( $item['post']->ID ); ?>" data-item-type="<?php echo esc_attr( $item['type'] ); ?>">
					<span class="dashicons dashicons-menu pf-curriculum-handle" aria-hidden="true"></span>
					<span class="pf-curriculum-type"><?php echo 'lesson' === $item['type'] ? esc_html__( 'Lesson', 'parish-formation' ) : esc_html__( 'Assessment', 'parish-formation' ); ?></span>
					<a href="<?php echo esc_url( get_edit_post_link( $item['post']->ID ) ); ?>"><?php echo esc_html( $item['post']->post_title ); ?></a>
					<span class="pf-curriculum-status"><?php echo esc_html( get_post_status_object( $item['post']->post_status )->label ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<p id="pf-curriculum-save-status" class="description" aria-live="polite"></p>
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
		} else {
			update_post_meta( $post_id, self::COMPLETION_MESSAGE_META_KEY, $completion_message );
		}

	}

	/** Save a drag-and-drop curriculum order over AJAX. */
	public static function save_curriculum_order() {
		check_ajax_referer( 'pf_save_curriculum_order', 'nonce' );
		if ( ! current_user_can( 'pf_manage_courses' ) || ! current_user_can( 'pf_manage_assessments' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to arrange this curriculum.', 'parish-formation' ) ), 403 );
		}

		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$items     = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		if ( Parish_Formation_Course_Post_Type::POST_TYPE !== get_post_type( $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The course could not be found.', 'parish-formation' ) ), 404 );
		}

		$lesson_number = 0;
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$item_id   = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			$item_type = isset( $item['type'] ) ? sanitize_key( $item['type'] ) : '';
			$expected_post_type = 'lesson' === $item_type ? Parish_Formation_Lesson_Post_Type::POST_TYPE : ( 'assessment' === $item_type ? Parish_Formation_Assessment_Post_Type::POST_TYPE : '' );
			if ( ! $item_id || ! $expected_post_type || $expected_post_type !== get_post_type( $item_id ) || $course_id !== absint( get_post_meta( $item_id, '_pf_course_id', true ) ) ) {
				wp_send_json_error( array( 'message' => __( 'One or more curriculum items are invalid.', 'parish-formation' ) ), 400 );
			}
			update_post_meta( $item_id, self::CURRICULUM_ORDER_META_KEY, $index + 1 );
			if ( 'lesson' === $item_type ) {
				++$lesson_number;
				update_post_meta( $item_id, '_pf_lesson_order', $lesson_number );
			}
		}

		wp_send_json_success( array( 'message' => __( 'Curriculum order saved.', 'parish-formation' ) ) );
	}

	/** Load curriculum drag-and-drop assets on course editing screens. */
	public static function enqueue_curriculum_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || Parish_Formation_Course_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script( 'parish-formation-course-curriculum', PARISH_FORMATION_PLUGIN_URL . 'assets/js/course-curriculum.js', array( 'jquery', 'jquery-ui-sortable' ), PARISH_FORMATION_VERSION, true );
		wp_localize_script(
			'parish-formation-course-curriculum',
			'pfCourseCurriculum',
			array(
				'nonce'  => wp_create_nonce( 'pf_save_curriculum_order' ),
				'saving' => __( 'Saving curriculum order…', 'parish-formation' ),
				'error'  => __( 'The curriculum order could not be saved.', 'parish-formation' ),
			)
		);
		wp_enqueue_style( 'parish-formation-course-curriculum', PARISH_FORMATION_PLUGIN_URL . 'assets/css/course-curriculum.css', array(), PARISH_FORMATION_VERSION );
	}
}
