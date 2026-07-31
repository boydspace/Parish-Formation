<?php
/**
 * Provides course-assignment settings for lessons.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lesson settings meta box and persistence.
 */
final class Parish_Formation_Lesson_Settings {

	/**
	 * Lesson course meta key.
	 */
	private const COURSE_META_KEY = '_pf_course_id';

	/**
	 * Lesson order meta key.
	 */
	private const ORDER_META_KEY = '_pf_lesson_order';

	/**
	 * Lesson requirement meta key.
	 */
	private const REQUIRED_META_KEY = '_pf_is_required';

	/**
	 * Nonce action.
	 */
	private const NONCE_ACTION = 'pf_save_lesson_settings';

	/**
	 * Nonce field name.
	 */
	private const NONCE_NAME = 'pf_lesson_settings_nonce';

	/**
	 * Quick Edit nonce field name.
	 */
	private const QUICK_EDIT_NONCE_NAME = 'pf_lesson_quick_edit_nonce';

	/**
	 * Register the lesson settings meta box.
	 *
	 * @return void
	 */
	public static function register_meta_box() {
		add_meta_box(
			'pf-lesson-settings',
			__( 'Lesson Settings', 'parish-formation' ),
			array( self::class, 'render_meta_box' ),
			Parish_Formation_Lesson_Post_Type::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Render the lesson settings fields.
	 *
	 * @param WP_Post $post Current lesson.
	 * @return void
	 */
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
		$is_required  = self::is_required( $post->ID );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<p>
			<label for="pf-course-id"><strong><?php echo esc_html__( 'Course', 'parish-formation' ); ?></strong></label>
		</p>
		<select id="pf-course-id" name="pf_course_id" class="widefat">
			<option value="0"><?php echo esc_html__( 'Not assigned', 'parish-formation' ); ?></option>
			<?php foreach ( $courses as $course ) : ?>
				<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_id, $course->ID ); ?>>
					<?php echo esc_html( $course->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<p>
			<label for="pf-is-required">
				<input
					type="checkbox"
					id="pf-is-required"
					name="pf_is_required"
					value="1"
					<?php checked( $is_required ); ?>
				/>
				<?php echo esc_html__( 'Required lesson', 'parish-formation' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Save lesson settings.
	 *
	 * @param int $post_id Lesson post ID.
	 * @return void
	 */
	public static function save( $post_id ) {
		$nonce_is_valid = self::verify_submitted_nonce( self::NONCE_NAME )
			|| self::verify_submitted_nonce( self::QUICK_EDIT_NONCE_NAME );

		if ( ! $nonce_is_valid ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'pf_manage_courses' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The lesson full-edit or quick-edit nonce was verified earlier in this save handler.

		$old_course_id = absint( get_post_meta( $post_id, self::COURSE_META_KEY, true ) );
		$course_id     = isset( $_POST['pf_course_id'] ) ? absint( $_POST['pf_course_id'] ) : 0;

		if ( $course_id && Parish_Formation_Course_Post_Type::POST_TYPE === get_post_type( $course_id ) ) {
			update_post_meta( $post_id, self::COURSE_META_KEY, $course_id );
		} else {
			delete_post_meta( $post_id, self::COURSE_META_KEY );
			$course_id = 0;
		}

		if ( $old_course_id !== $course_id ) {
			delete_post_meta( $post_id, Parish_Formation_Course_Settings::CURRICULUM_ORDER_META_KEY );
			if ( $course_id ) {
				update_post_meta( $post_id, self::ORDER_META_KEY, self::next_lesson_order( $course_id, $post_id ) );
			} else {
				delete_post_meta( $post_id, self::ORDER_META_KEY );
			}
		}

		$is_required = isset( $_POST['pf_is_required'] ) ? 1 : 0;
		update_post_meta( $post_id, self::REQUIRED_META_KEY, $is_required );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Add course and requirement columns to the Lessons list.
	 *
	 * @param array $columns Existing list-table columns.
	 * @return array
	 */
	public static function add_list_columns( $columns ) {
		$columns['pf_course']       = __( 'Course', 'parish-formation' );
		$columns['pf_is_required']  = __( 'Requirement', 'parish-formation' );

		return $columns;
	}

	/**
	 * Render lesson list-table column content.
	 *
	 * @param string $column_name Column identifier.
	 * @param int    $post_id     Lesson post ID.
	 * @return void
	 */
	public static function render_list_column( $column_name, $post_id ) {
		$course_id   = absint( get_post_meta( $post_id, self::COURSE_META_KEY, true ) );
		$is_required  = self::is_required( $post_id );

		if ( 'pf_course' === $column_name ) {
			$course_title = $course_id ? get_the_title( $course_id ) : '';
			?>
			<span
				class="pf-lesson-quick-edit-data"
				data-course-id="<?php echo esc_attr( $course_id ); ?>"
				data-is-required="<?php echo esc_attr( $is_required ? '1' : '0' ); ?>"
			>
				<?php echo $course_title ? esc_html( $course_title ) : '&mdash;'; ?>
			</span>
			<?php
		}

		if ( 'pf_is_required' === $column_name ) {
			echo $is_required
				? esc_html__( 'Required', 'parish-formation' )
				: esc_html__( 'Optional', 'parish-formation' );
		}
	}

	/**
	 * Render course and requirement fields in Quick Edit.
	 *
	 * @param string $column_name Current custom column.
	 * @param string $post_type   Current post type.
	 * @return void
	 */
	public static function render_quick_edit_fields( $column_name, $post_type ) {
		if ( 'pf_course' !== $column_name || Parish_Formation_Lesson_Post_Type::POST_TYPE !== $post_type ) {
			return;
		}

		$courses = self::get_courses();
		wp_nonce_field( self::NONCE_ACTION, self::QUICK_EDIT_NONCE_NAME );
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php echo esc_html__( 'Course', 'parish-formation' ); ?></span>
					<select name="pf_course_id">
						<option value="0"><?php echo esc_html__( 'Not assigned', 'parish-formation' ); ?></option>
						<?php foreach ( $courses as $course ) : ?>
							<option value="<?php echo esc_attr( $course->ID ); ?>">
								<?php echo esc_html( $course->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="alignleft">
					<input type="checkbox" name="pf_is_required" value="1" />
					<span class="checkbox-title"><?php echo esc_html__( 'Required lesson', 'parish-formation' ); ?></span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Load the Quick Edit helper only on the Lessons list screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_quick_edit_script( $hook_suffix ) {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || Parish_Formation_Lesson_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'parish-formation-lesson-quick-edit',
			PARISH_FORMATION_PLUGIN_URL . 'assets/js/lesson-quick-edit.js',
			array( 'jquery', 'inline-edit-post' ),
			PARISH_FORMATION_VERSION,
			true
		);
	}

	/**
	 * Verify a submitted lesson-settings nonce.
	 *
	 * @param string $field_name Nonce field name.
	 * @return bool
	 */
	private static function verify_submitted_nonce( $field_name ) {
		if ( ! isset( $_POST[ $field_name ] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) );
		$is_valid = wp_verify_nonce( $nonce, self::NONCE_ACTION );
		if ( ! $is_valid ) {
			return false;
		}
		return true;
	}

	/**
	 * Retrieve courses available for lesson assignment.
	 *
	 * @return WP_Post[]
	 */
	private static function get_courses() {
		return get_posts(
			array(
				'post_type'      => Parish_Formation_Course_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/** Get the next automatic lesson position for a course. */
	private static function next_lesson_order( $course_id, $excluded_lesson_id ) {
		$lesson_ids = get_posts(
			array(
				'post_type'      => Parish_Formation_Lesson_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array( absint( $excluded_lesson_id ) ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Excludes one currently saved lesson from a bounded course-specific administrative query.
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Course-lesson relationships are stored as post meta by the established data model.
					array(
						'key'     => self::COURSE_META_KEY,
						'value'   => absint( $course_id ),
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		$maximum = 0;
		foreach ( $lesson_ids as $lesson_id ) {
			$maximum = max( $maximum, self::get_lesson_order( $lesson_id ) );
		}
		return $maximum + 1;
	}

	/**
	 * Determine whether a lesson is required.
	 *
	 * Lessons without saved requirement metadata default to required.
	 *
	 * @param int $post_id Lesson post ID.
	 * @return bool
	 */
	public static function is_required( $post_id ) {
		if ( ! metadata_exists( 'post', $post_id, self::REQUIRED_META_KEY ) ) {
			return true;
		}

		return (bool) get_post_meta( $post_id, self::REQUIRED_META_KEY, true );
	}

	/**
	 * Get a lesson's saved order number.
	 *
	 * @param int $post_id Lesson post ID.
	 * @return int
	 */
	public static function get_lesson_order( $post_id ) {
		return absint( get_post_meta( $post_id, self::ORDER_META_KEY, true ) );
	}

	/**
	 * Retrieve lessons assigned to a course in lesson-number order.
	 *
	 * @param int $course_id Course post ID.
	 * @return WP_Post[]
	 */
	public static function get_course_lessons( $course_id ) {
		$lessons = get_posts(
			array(
				'post_type'      => Parish_Formation_Lesson_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Course-lesson relationships are stored as post meta by the established data model.
					array(
						'key'     => self::COURSE_META_KEY,
						'value'   => absint( $course_id ),
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		usort(
			$lessons,
			static function ( $first_lesson, $second_lesson ) {
				$first_order  = self::get_lesson_order( $first_lesson->ID );
				$second_order = self::get_lesson_order( $second_lesson->ID );

				if ( $first_order === $second_order ) {
					return strcasecmp( $first_lesson->post_title, $second_lesson->post_title );
				}

				return $first_order <=> $second_order;
			}
		);

		return $lessons;
	}
}
