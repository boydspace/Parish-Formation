<?php
/**
 * Provides enrollment administration screens.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders enrollment administration.
 */
final class Parish_Formation_Enrollments_Admin {

	/**
	 * Register the enrollments submenu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'parish-formation',
			esc_html__( 'Enrollments', 'parish-formation' ),
			esc_html__( 'Enrollments', 'parish-formation' ),
			'pf_manage_enrollments',
			'parish-formation-enrollments',
			array( self::class, 'render_page' ),
			30
		);
	}

	/**
	 * Render the enrollments page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'pf_manage_enrollments' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'parish-formation' ) );
		}

		$enrollments = self::get_recent_enrollments();
		$courses    = get_posts(
			array(
				'post_type'      => Parish_Formation_Course_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$submit_attributes = $courses ? array() : array( 'disabled' => 'disabled' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Enrollments', 'parish-formation' ); ?></h1>

			<?php self::render_notice(); ?>

			<h2><?php echo esc_html__( 'Manually Enroll a Participant', 'parish-formation' ); ?></h2>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="pf_create_enrollment" />
				<?php wp_nonce_field( 'pf_create_enrollment' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pf-user-id"><?php echo esc_html__( 'Participant', 'parish-formation' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_users(
								array(
									'name'             => 'user_id',
									'id'               => 'pf-user-id',
									'show_option_none' => __( 'Select a user', 'parish-formation' ),
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pf-course-id"><?php echo esc_html__( 'Course', 'parish-formation' ); ?></label></th>
						<td>
							<select id="pf-course-id" name="course_id" required>
								<option value=""><?php echo esc_html__( 'Select a published course', 'parish-formation' ); ?></option>
								<?php foreach ( $courses as $course ) : ?>
									<option value="<?php echo esc_attr( $course->ID ); ?>"><?php echo esc_html( $course->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php if ( ! $courses ) : ?>
								<p class="description"><?php echo esc_html__( 'Publish a course before creating an enrollment.', 'parish-formation' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pf-expires-on"><?php echo esc_html__( 'Expiration date', 'parish-formation' ); ?></label></th>
						<td>
							<input type="date" id="pf-expires-on" name="expires_on" />
							<p class="description"><?php echo esc_html__( 'Optional. Access expires at the end of this date in the site timezone.', 'parish-formation' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Enroll Participant', 'parish-formation' ), 'primary', 'submit', true, $submit_attributes ); ?>
			</form>

			<hr />
			<h2><?php echo esc_html__( 'Recent Enrollments', 'parish-formation' ); ?></h2>

			<?php if ( ! $enrollments ) : ?>
				<p><?php echo esc_html__( 'No enrollments have been created yet.', 'parish-formation' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Participant', 'parish-formation' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Course', 'parish-formation' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Status', 'parish-formation' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Enrolled', 'parish-formation' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Expires', 'parish-formation' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Actions', 'parish-formation' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $enrollments as $enrollment ) : ?>
							<tr>
								<td><?php echo esc_html( $enrollment->display_name ); ?></td>
								<td><?php echo esc_html( $enrollment->course_title ); ?></td>
								<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $enrollment->status ) ) ); ?></td>
								<td><?php echo esc_html( self::format_utc_date( $enrollment->enrolled_at ) ); ?></td>
								<td>
									<?php echo $enrollment->expires_at ? esc_html( self::format_utc_date( $enrollment->expires_at ) ) : '&mdash;'; ?>
								</td>
								<td>
									<?php if ( 'unenrolled' !== $enrollment->status ) : ?>
										<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
											<input type="hidden" name="action" value="pf_unenroll_participant" />
											<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( $enrollment->id ); ?>" />
											<?php wp_nonce_field( 'pf_unenroll_' . $enrollment->id ); ?>
											<?php submit_button( __( 'Unenroll', 'parish-formation' ), 'delete small', 'submit', false ); ?>
										</form>
										<?php else : ?>
											&mdash;
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Process a manual-enrollment request.
	 *
	 * @return void
	 */
	public static function handle_create() {
		if ( ! current_user_can( 'pf_manage_enrollments' ) ) {
			wp_die( esc_html__( 'You do not have permission to create enrollments.', 'parish-formation' ) );
		}

		check_admin_referer( 'pf_create_enrollment' );

		$user_id   = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$expires_on = isset( $_POST['expires_on'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_on'] ) ) : '';

		if ( ! get_userdata( $user_id ) ) {
			self::redirect_with_notice( 'invalid_user' );
		}

		$course = get_post( $course_id );

		if ( ! $course || Parish_Formation_Course_Post_Type::POST_TYPE !== $course->post_type || 'publish' !== $course->post_status ) {
			self::redirect_with_notice( 'invalid_course' );
		}

		$expires_at = self::expiration_to_utc( $expires_on );

		if ( '' !== $expires_on && ! $expires_at ) {
			self::redirect_with_notice( 'invalid_expiration' );
		}

		$result = Parish_Formation_Enrollment_Repository::create_manual(
			$user_id,
			$course_id,
			get_current_user_id(),
			$expires_at
		);

		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice( $result->get_error_code() );
		}

		self::redirect_with_notice( 'created' );
	}

	/**
	 * Process an unenrollment request.
	 *
	 * @return void
	 */
	public static function handle_unenroll() {
		if ( ! current_user_can( 'pf_manage_enrollments' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage enrollments.', 'parish-formation' ) );
		}

		$enrollment_id = isset( $_POST['enrollment_id'] ) ? absint( $_POST['enrollment_id'] ) : 0;
		check_admin_referer( 'pf_unenroll_' . $enrollment_id );

		$result = Parish_Formation_Enrollment_Repository::unenroll( $enrollment_id );

		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice( $result->get_error_code() );
		}

		self::redirect_with_notice( 'unenrolled' );
	}

	/**
	 * Render a whitelisted enrollment result notice.
	 *
	 * @return void
	 */
	private static function render_notice() {
		$notice_code = isset( $_GET['pf_notice'] ) ? sanitize_key( wp_unslash( $_GET['pf_notice'] ) ) : '';
		$notices     = array(
			'created'              => array( 'success', __( 'The participant was enrolled successfully.', 'parish-formation' ) ),
			'unenrolled'           => array( 'success', __( 'The participant was unenrolled successfully.', 'parish-formation' ) ),
			'duplicate_enrollment' => array( 'warning', __( 'That user is already enrolled in this course.', 'parish-formation' ) ),
			'invalid_user'         => array( 'error', __( 'Select a valid WordPress user.', 'parish-formation' ) ),
			'invalid_course'       => array( 'error', __( 'Select a valid published course.', 'parish-formation' ) ),
			'invalid_expiration'   => array( 'error', __( 'Enter a valid expiration date.', 'parish-formation' ) ),
			'invalid_enrollment'   => array( 'error', __( 'The enrollment could not be found.', 'parish-formation' ) ),
			'database_error'       => array( 'error', __( 'The enrollment could not be saved.', 'parish-formation' ) ),
		);

		if ( ! isset( $notices[ $notice_code ] ) ) {
			return;
		}

		list( $notice_type, $message ) = $notices[ $notice_code ];
		?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Redirect back to the enrollment page with a result code.
	 *
	 * @param string $notice_code Whitelisted notice code.
	 * @return void
	 */
	private static function redirect_with_notice( $notice_code ) {
		$url = add_query_arg(
			array(
				'page'      => 'parish-formation-enrollments',
				'pf_notice' => sanitize_key( $notice_code ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Retrieve recent enrollments with participant and course names.
	 *
	 * @return object[]
	 */
	private static function get_recent_enrollments() {
		global $wpdb;

		$enrollments_table = $wpdb->prefix . 'pf_enrollments';
		$users_table       = $wpdb->users;
		$posts_table       = $wpdb->posts;

		$query = "SELECT enrollment.id, enrollment.status, enrollment.enrolled_at, enrollment.expires_at,
			user.display_name, course.post_title AS course_title
			FROM {$enrollments_table} AS enrollment
			INNER JOIN {$users_table} AS user ON user.ID = enrollment.user_id
			INNER JOIN {$posts_table} AS course ON course.ID = enrollment.course_id
			WHERE enrollment.status <> 'unenrolled'
			ORDER BY enrollment.enrolled_at DESC, enrollment.id DESC
			LIMIT 50";

		return $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Format a stored UTC datetime using the site's date and time settings.
	 *
	 * @param string $utc_date UTC MySQL datetime.
	 * @return string
	 */
	private static function format_utc_date( $utc_date ) {
		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		return get_date_from_gmt( $utc_date, $date_format );
	}

	/**
	 * Convert a site-local expiration date to an end-of-day UTC datetime.
	 *
	 * @param string $date_string Date formatted as Y-m-d, or an empty string.
	 * @return string|null UTC MySQL datetime, or null when empty or invalid.
	 */
	private static function expiration_to_utc( $date_string ) {
		if ( '' === $date_string ) {
			return null;
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $date_string, wp_timezone() );

		if ( ! $date || $date->format( 'Y-m-d' ) !== $date_string ) {
			return null;
		}

		return $date
			->setTime( 23, 59, 59 )
			->setTimezone( new DateTimeZone( 'UTC' ) )
			->format( 'Y-m-d H:i:s' );
	}
}
