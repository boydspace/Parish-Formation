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

	/** Certificate configuration metadata. */
	public const CERTIFICATE_ENABLED_META_KEY = '_pf_certificate_enabled';
	public const CERTIFICATE_TITLE_META_KEY = '_pf_certificate_title';
	public const CERTIFICATE_ISSUER_META_KEY = '_pf_certificate_issuer';
	public const CERTIFICATE_VALIDITY_DAYS_META_KEY = '_pf_certificate_validity_days';
	public const CERTIFICATE_SIGNATORY_NAME_META_KEY = '_pf_certificate_signatory_name';
	public const CERTIFICATE_SIGNATORY_TITLE_META_KEY = '_pf_certificate_signatory_title';
	public const CERTIFICATE_LOGO_ID_META_KEY = '_pf_certificate_logo_id';
	public const CERTIFICATE_SIGNATURE_ID_META_KEY = '_pf_certificate_signature_id';
	public const CERTIFICATE_HEADING_META_KEY = '_pf_certificate_heading';
	public const CERTIFICATE_COMPLETION_TEXT_META_KEY = '_pf_certificate_completion_text';
	public const CERTIFICATE_ACCENT_COLOR_META_KEY = '_pf_certificate_accent_color';
	public const CERTIFICATE_BORDER_COLOR_META_KEY = '_pf_certificate_border_color';
	public const CERTIFICATE_ORIENTATION_META_KEY = '_pf_certificate_orientation';
	public const CERTIFICATE_DESIGN_ID_META_KEY = '_pf_certificate_design_id';
	public const NOTIFICATION_DISABLED_META_KEY = '_pf_notification_disabled';
	public const NOTIFICATION_STAFF_EMAILS_META_KEY = '_pf_notification_staff_emails';
	public const OPEN_ENROLLMENT_META_KEY = '_pf_open_enrollment';
	public const ACCESS_CODE_ENABLED_META_KEY = '_pf_access_code_enabled';
	public const ACCESS_CODE_HASH_META_KEY = '_pf_access_code_hash';
	public const ACCESS_CODE_ENCRYPTED_META_KEY = '_pf_access_code_encrypted';
	public const ACCESS_CODE_EXPIRES_META_KEY = '_pf_access_code_expires';
	public const ACCESS_CODE_LIMIT_META_KEY = '_pf_access_code_limit';
	public const ACCESS_CODE_USES_META_KEY = '_pf_access_code_uses';
	public const CATALOG_VISIBLE_META_KEY = '_pf_catalog_visible';

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
		$certificate_enabled = (bool) get_post_meta( $post->ID, self::CERTIFICATE_ENABLED_META_KEY, true );
		$validity_days = absint( get_post_meta( $post->ID, self::CERTIFICATE_VALIDITY_DAYS_META_KEY, true ) );
		$certificate_design_id = absint( get_post_meta( $post->ID, self::CERTIFICATE_DESIGN_ID_META_KEY, true ) );
		$certificate_designs = get_posts( array( 'post_type' => Parish_Formation_Certificate_Design_Post_Type::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$notification_disabled = (array) get_post_meta( $post->ID, self::NOTIFICATION_DISABLED_META_KEY, true );
		$notification_staff_emails = get_post_meta( $post->ID, self::NOTIFICATION_STAFF_EMAILS_META_KEY, true );
		$open_enrollment = (bool) get_post_meta( $post->ID, self::OPEN_ENROLLMENT_META_KEY, true );
		$access_code_enabled = (bool) get_post_meta( $post->ID, self::ACCESS_CODE_ENABLED_META_KEY, true );
		$access_code_saved = (bool) get_post_meta( $post->ID, self::ACCESS_CODE_HASH_META_KEY, true );
		$access_code_value = self::decrypt_access_code( get_post_meta( $post->ID, self::ACCESS_CODE_ENCRYPTED_META_KEY, true ) );
		$access_code_expires = sanitize_text_field( get_post_meta( $post->ID, self::ACCESS_CODE_EXPIRES_META_KEY, true ) );
		$access_code_limit = absint( get_post_meta( $post->ID, self::ACCESS_CODE_LIMIT_META_KEY, true ) );
		$access_code_uses = absint( get_post_meta( $post->ID, self::ACCESS_CODE_USES_META_KEY, true ) );
		$catalog_visible = ! metadata_exists( 'post', $post->ID, self::CATALOG_VISIBLE_META_KEY ) || (bool) get_post_meta( $post->ID, self::CATALOG_VISIBLE_META_KEY, true );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<h3><?php esc_html_e( 'Enrollment Access', 'parish-formation' ); ?></h3>
		<p><label><input type="checkbox" name="pf_catalog_visible" value="1" <?php checked( $catalog_visible ); ?>> <?php esc_html_e( 'Show this course in the public course catalog', 'parish-formation' ); ?></label></p>
		<p class="description"><?php esc_html_e( 'Hidden courses are not advertised in the catalog. Participants can still enroll using a valid access code.', 'parish-formation' ); ?></p>
		<p><label><input type="checkbox" name="pf_open_enrollment" value="1" <?php checked( $open_enrollment ); ?>> <?php esc_html_e( 'Allow signed-in users to enroll themselves in this course', 'parish-formation' ); ?></label></p>
		<p class="description"><?php esc_html_e( 'When enabled, this published course appears in the public formation catalog. Visitors must sign in or register before enrolling.', 'parish-formation' ); ?></p>
		<h4><?php esc_html_e( 'Access Code', 'parish-formation' ); ?></h4>
		<p><label><input type="checkbox" name="pf_access_code_enabled" value="1" <?php checked( $access_code_enabled ); ?>> <?php esc_html_e( 'Allow enrollment with a course access code', 'parish-formation' ); ?></label></p>
		<p><label for="pf-access-code"><strong><?php esc_html_e( 'Access code', 'parish-formation' ); ?></strong></label><br><input id="pf-access-code" name="pf_access_code" type="text" class="regular-text" autocomplete="off" value="<?php echo esc_attr( $access_code_value ); ?>" placeholder="<?php echo esc_attr( $access_code_saved ? __( 'Enter the code once more to make it viewable', 'parish-formation' ) : __( 'Enter an access code', 'parish-formation' ) ); ?>"></p>
		<p class="description"><?php esc_html_e( 'The code is encrypted for authorized administrators and separately hashed for participant validation. Changing it resets the usage count.', 'parish-formation' ); ?></p>
		<?php if ( $access_code_saved && ! $access_code_value ) : ?><p class="description"><strong><?php esc_html_e( 'This code was saved before viewable codes were supported. Enter it once more, or enter a replacement, and update the course.', 'parish-formation' ); ?></strong></p><?php endif; ?>
		<?php if ( $access_code_saved ) : ?><p><label><input type="checkbox" name="pf_access_code_clear" value="1"> <?php esc_html_e( 'Remove the saved access code', 'parish-formation' ); ?></label></p><?php endif; ?>
		<p><label for="pf-access-code-expires"><strong><?php esc_html_e( 'Code expiration date', 'parish-formation' ); ?></strong></label><br><input id="pf-access-code-expires" name="pf_access_code_expires" type="date" value="<?php echo esc_attr( $access_code_expires ); ?>"> <span class="description"><?php esc_html_e( 'Optional. The code remains valid through this date in the site timezone.', 'parish-formation' ); ?></span></p>
		<p><label for="pf-access-code-limit"><strong><?php esc_html_e( 'Maximum successful uses', 'parish-formation' ); ?></strong></label><br><input id="pf-access-code-limit" name="pf_access_code_limit" type="number" min="0" max="1000000" step="1" value="<?php echo esc_attr( $access_code_limit ); ?>" class="small-text"> <span class="description"><?php esc_html_e( 'Use 0 for unlimited. A new code resets the usage count.', 'parish-formation' ); ?></span></p>
		<?php if ( $access_code_saved ) : ?><p class="description"><?php echo esc_html( sprintf( __( 'Successful uses: %d', 'parish-formation' ), $access_code_uses ) ); ?></p><?php endif; ?>
		<hr>
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
		<hr>
		<h3><?php esc_html_e( 'Completion Certificate', 'parish-formation' ); ?></h3>
		<p><label><input type="checkbox" name="pf_certificate_enabled" value="1" <?php checked( $certificate_enabled ); ?>> <?php esc_html_e( 'Issue a certificate when an eligible participant completes this course', 'parish-formation' ); ?></label></p>
		<p><label for="pf-certificate-design"><strong><?php esc_html_e( 'Certificate design', 'parish-formation' ); ?></strong></label><br><select id="pf-certificate-design" name="pf_certificate_design_id"><option value="0"><?php esc_html_e( 'Select a design', 'parish-formation' ); ?></option><?php foreach ( $certificate_designs as $design ) : ?><option value="<?php echo esc_attr( $design->ID ); ?>" <?php selected( $certificate_design_id, $design->ID ); ?>><?php echo esc_html( $design->post_title ); ?></option><?php endforeach; ?></select></p>
		<?php if ( ! $certificate_designs ) : ?><p class="description"><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Parish_Formation_Certificate_Design_Post_Type::POST_TYPE ) ); ?>"><?php esc_html_e( 'Create the first certificate design', 'parish-formation' ); ?></a></p><?php else : ?><p class="description"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Parish_Formation_Certificate_Design_Post_Type::POST_TYPE ) ); ?>"><?php esc_html_e( 'Manage certificate designs', 'parish-formation' ); ?></a></p><?php endif; ?>
		<p><label for="pf-certificate-validity"><strong><?php esc_html_e( 'Validity period in days', 'parish-formation' ); ?></strong></label><br>
		<input id="pf-certificate-validity" name="pf_certificate_validity_days" type="number" min="0" max="36500" step="1" value="<?php echo esc_attr( $validity_days ); ?>" class="small-text"> <span class="description"><?php esc_html_e( 'Use 0 for no expiration.', 'parish-formation' ); ?></span></p>
		<hr>
		<h3><?php esc_html_e( 'Course Email Notifications', 'parish-formation' ); ?></h3>
		<p><?php esc_html_e( 'These settings override the global notification settings for this course.', 'parish-formation' ); ?></p>
		<fieldset><legend class="screen-reader-text"><?php esc_html_e( 'Enabled course emails', 'parish-formation' ); ?></legend>
		<?php foreach ( Parish_Formation_Notifications::types() as $type => $definition ) : if ( 'account' === $definition[0] ) { continue; } ?><label style="display:block;margin:6px 0"><input type="checkbox" name="pf_notification_enabled[<?php echo esc_attr( $type ); ?>]" value="1" <?php checked( ! in_array( $type, $notification_disabled, true ) ); ?>> <?php echo esc_html( $definition[1] ); ?></label><?php endforeach; ?>
		</fieldset>
		<p><label for="pf-notification-staff-emails"><strong><?php esc_html_e( 'Course-specific staff recipients', 'parish-formation' ); ?></strong></label><br><textarea id="pf-notification-staff-emails" name="pf_notification_staff_emails" class="widefat" rows="3"><?php echo esc_textarea( $notification_staff_emails ); ?></textarea><span class="description"><?php esc_html_e( 'Leave blank to use the global staff recipients. Separate addresses with commas or new lines.', 'parish-formation' ); ?></span></p>
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

		update_post_meta( $post_id, self::CERTIFICATE_ENABLED_META_KEY, isset( $_POST['pf_certificate_enabled'] ) ? 1 : 0 );
		update_post_meta( $post_id, self::OPEN_ENROLLMENT_META_KEY, isset( $_POST['pf_open_enrollment'] ) ? 1 : 0 );
		update_post_meta( $post_id, self::CATALOG_VISIBLE_META_KEY, isset( $_POST['pf_catalog_visible'] ) ? 1 : 0 );
		update_post_meta( $post_id, self::ACCESS_CODE_ENABLED_META_KEY, isset( $_POST['pf_access_code_enabled'] ) ? 1 : 0 );
		$access_code = isset( $_POST['pf_access_code'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['pf_access_code'] ) ) ) : '';
		if ( isset( $_POST['pf_access_code_clear'] ) ) {
			delete_post_meta( $post_id, self::ACCESS_CODE_HASH_META_KEY );
			delete_post_meta( $post_id, self::ACCESS_CODE_ENCRYPTED_META_KEY );
			delete_post_meta( $post_id, self::ACCESS_CODE_USES_META_KEY );
		} elseif ( '' !== $access_code ) {
			$existing_code = self::decrypt_access_code( get_post_meta( $post_id, self::ACCESS_CODE_ENCRYPTED_META_KEY, true ) );
			if ( ! hash_equals( $existing_code, $access_code ) ) {
				update_post_meta( $post_id, self::ACCESS_CODE_HASH_META_KEY, wp_hash_password( $access_code ) );
				update_post_meta( $post_id, self::ACCESS_CODE_ENCRYPTED_META_KEY, self::encrypt_access_code( $access_code ) );
				update_post_meta( $post_id, self::ACCESS_CODE_USES_META_KEY, 0 );
			}
		}
		$access_code_expires = isset( $_POST['pf_access_code_expires'] ) ? sanitize_text_field( wp_unslash( $_POST['pf_access_code_expires'] ) ) : '';
		if ( $access_code_expires && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $access_code_expires ) ) {
			update_post_meta( $post_id, self::ACCESS_CODE_EXPIRES_META_KEY, $access_code_expires );
		} else {
			delete_post_meta( $post_id, self::ACCESS_CODE_EXPIRES_META_KEY );
		}
		update_post_meta( $post_id, self::ACCESS_CODE_LIMIT_META_KEY, isset( $_POST['pf_access_code_limit'] ) ? min( 1000000, absint( $_POST['pf_access_code_limit'] ) ) : 0 );
		$validity_days = isset( $_POST['pf_certificate_validity_days'] ) ? min( 36500, absint( $_POST['pf_certificate_validity_days'] ) ) : 0;
		update_post_meta( $post_id, self::CERTIFICATE_VALIDITY_DAYS_META_KEY, $validity_days );
		$design_id = isset( $_POST['pf_certificate_design_id'] ) ? absint( $_POST['pf_certificate_design_id'] ) : 0;
		if ( $design_id && Parish_Formation_Certificate_Design_Post_Type::POST_TYPE === get_post_type( $design_id ) && 'publish' === get_post_status( $design_id ) ) {
			update_post_meta( $post_id, self::CERTIFICATE_DESIGN_ID_META_KEY, $design_id );
		} else {
			delete_post_meta( $post_id, self::CERTIFICATE_DESIGN_ID_META_KEY );
		}
		$posted_notifications = isset( $_POST['pf_notification_enabled'] ) && is_array( $_POST['pf_notification_enabled'] ) ? wp_unslash( $_POST['pf_notification_enabled'] ) : array();
		$disabled_notifications = array();
		foreach ( Parish_Formation_Notifications::types() as $type => $definition ) {
			if ( 'account' === $definition[0] ) {
				continue;
			}
			if ( ! isset( $posted_notifications[ $type ] ) ) {
				$disabled_notifications[] = $type;
			}
		}
		update_post_meta( $post_id, self::NOTIFICATION_DISABLED_META_KEY, $disabled_notifications );
		$staff_emails = isset( $_POST['pf_notification_staff_emails'] ) ? Parish_Formation_Notifications::sanitize_email_list( wp_unslash( $_POST['pf_notification_staff_emails'] ) ) : '';
		if ( $staff_emails ) {
			update_post_meta( $post_id, self::NOTIFICATION_STAFF_EMAILS_META_KEY, $staff_emails );
		} else {
			delete_post_meta( $post_id, self::NOTIFICATION_STAFF_EMAILS_META_KEY );
		}

	}

	/** Encrypt a viewable administrative copy without changing participant validation. */
	private static function encrypt_access_code( $code ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}
		$key        = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv         = random_bytes( 12 );
		$tag        = '';
		$ciphertext = openssl_encrypt( $code, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return false === $ciphertext ? '' : base64_encode( $iv . $tag . $ciphertext );
	}

	/** Decrypt the administrative access-code copy for authorized editing screens. */
	private static function decrypt_access_code( $stored ) {
		if ( ! $stored || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$payload = base64_decode( $stored, true );
		if ( false === $payload || strlen( $payload ) < 29 ) {
			return '';
		}
		$iv         = substr( $payload, 0, 12 );
		$tag        = substr( $payload, 12, 16 );
		$ciphertext = substr( $payload, 28 );
		$plain      = openssl_decrypt( $ciphertext, 'aes-256-gcm', hash( 'sha256', wp_salt( 'auth' ), true ), OPENSSL_RAW_DATA, $iv, $tag );
		return false === $plain ? '' : $plain;
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
