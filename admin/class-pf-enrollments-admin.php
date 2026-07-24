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

		$enrollment_id = isset( $_GET['enrollment_id'] ) ? absint( $_GET['enrollment_id'] ) : 0;

		if ( $enrollment_id ) {
			self::render_detail_page( $enrollment_id );
			return;
		}

		$search_term   = isset( $_GET['pf_search'] ) ? sanitize_text_field( wp_unslash( $_GET['pf_search'] ) ) : '';
		$course_filter = isset( $_GET['pf_course_filter'] ) ? absint( $_GET['pf_course_filter'] ) : 0;
		$status_filter = isset( $_GET['pf_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['pf_status_filter'] ) ) : '';
		$valid_statuses = array( 'enrolled', 'in_progress', 'completed' );

		if ( ! in_array( $status_filter, $valid_statuses, true ) ) {
			$status_filter = '';
		}

		$enrollments = self::get_recent_enrollments( $search_term, $course_filter, $status_filter );
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
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="search-form">
				<input type="hidden" name="page" value="parish-formation-enrollments" />
				<label class="screen-reader-text" for="pf-enrollment-search"><?php echo esc_html__( 'Search participants', 'parish-formation' ); ?></label>
				<input type="search" id="pf-enrollment-search" name="pf_search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="<?php echo esc_attr__( 'Name or email', 'parish-formation' ); ?>" />

				<label class="screen-reader-text" for="pf-course-filter"><?php echo esc_html__( 'Filter by course', 'parish-formation' ); ?></label>
				<select id="pf-course-filter" name="pf_course_filter">
					<option value="0"><?php echo esc_html__( 'All courses', 'parish-formation' ); ?></option>
					<?php foreach ( $courses as $course ) : ?>
						<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_filter, $course->ID ); ?>><?php echo esc_html( $course->post_title ); ?></option>
					<?php endforeach; ?>
				</select>

				<label class="screen-reader-text" for="pf-status-filter"><?php echo esc_html__( 'Filter by status', 'parish-formation' ); ?></label>
				<select id="pf-status-filter" name="pf_status_filter">
					<option value=""><?php echo esc_html__( 'All statuses', 'parish-formation' ); ?></option>
					<option value="enrolled" <?php selected( $status_filter, 'enrolled' ); ?>><?php echo esc_html__( 'Enrolled', 'parish-formation' ); ?></option>
					<option value="in_progress" <?php selected( $status_filter, 'in_progress' ); ?>><?php echo esc_html__( 'In Progress', 'parish-formation' ); ?></option>
					<option value="completed" <?php selected( $status_filter, 'completed' ); ?>><?php echo esc_html__( 'Completed', 'parish-formation' ); ?></option>
				</select>

				<?php submit_button( __( 'Filter', 'parish-formation' ), 'secondary', 'submit', false ); ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'page', 'parish-formation-enrollments', admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Clear', 'parish-formation' ); ?></a>
			</form>

			<?php if ( ! $enrollments ) : ?>
				<p><?php echo esc_html__( 'No enrollments match the current filters.', 'parish-formation' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Participant', 'parish-formation' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Course', 'parish-formation' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Progress', 'parish-formation' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Current lesson', 'parish-formation' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Status', 'parish-formation' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Enrolled', 'parish-formation' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Completed', 'parish-formation' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Expires', 'parish-formation' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Actions', 'parish-formation' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $enrollments as $enrollment ) : ?>
							<?php
							$lessons          = Parish_Formation_Course_Repository::get_published_lessons( $enrollment->course_id );
							$progress         = Parish_Formation_Progress_Repository::get_summary( $enrollment->id, $lessons, $enrollment->course_id );
							$current_lesson_id = Parish_Formation_Progress_Repository::get_current_lesson_id( $enrollment->id, $lessons );
							$current_lesson    = $current_lesson_id ? get_post( $current_lesson_id ) : null;
							?>
							<tr>
								<td>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'parish-formation-enrollments', 'enrollment_id' => $enrollment->id ), admin_url( 'admin.php' ) ) ); ?>">
										<?php echo esc_html( $enrollment->display_name ); ?>
									</a>
								</td>
								<td><?php echo esc_html( $enrollment->course_title ); ?></td>
								<td>
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: finished lesson count, 2: total lesson count, 3: percentage. */
											__( '%1$d of %2$d (%3$d%%)', 'parish-formation' ),
											$progress['finished'],
											$progress['total'],
											$progress['percentage']
										)
									);
									?>
								</td>
								<td>
									<?php
									if ( $current_lesson ) {
										echo esc_html( $current_lesson->post_title );
									} elseif ( $progress['is_complete'] ) {
										echo esc_html__( 'Course complete', 'parish-formation' );
									} else {
										echo esc_html__( 'No published lessons', 'parish-formation' );
									}
									?>
								</td>
								<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $enrollment->status ) ) ); ?></td>
								<td><?php echo esc_html( self::format_utc_date( $enrollment->enrolled_at ) ); ?></td>
								<td>
									<?php echo $enrollment->completed_at ? esc_html( self::format_utc_date( $enrollment->completed_at ) ) : '&mdash;'; ?>
								</td>
								<td>
									<?php echo $enrollment->expires_at ? esc_html( self::format_utc_date( $enrollment->expires_at ) ) : '&mdash;'; ?>
								</td>
								<td>
									<div class="pf-enrollment-actions">
									<?php if ( in_array( $enrollment->status, array( 'in_progress', 'completed' ), true ) ) : ?>
										<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return window.confirm('<?php echo esc_js( __( 'Reset this course? All lesson progress and the completion date will be removed.', 'parish-formation' ) ); ?>');">
											<input type="hidden" name="action" value="pf_reset_enrollment" />
											<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( $enrollment->id ); ?>" />
											<?php wp_nonce_field( 'pf_reset_enrollment_' . $enrollment->id ); ?>
											<?php submit_button( __( 'Reset Course', 'parish-formation' ), 'secondary small', 'submit', false ); ?>
										</form>
									<?php endif; ?>
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
									</div>
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
	 * Render detailed progress for one enrollment.
	 *
	 * @param int $enrollment_id Enrollment ID.
	 * @return void
	 */
	private static function render_detail_page( $enrollment_id ) {
		$enrollment = Parish_Formation_Enrollment_Repository::get_details( $enrollment_id );

		if ( ! $enrollment ) {
			wp_die( esc_html__( 'The enrollment could not be found.', 'parish-formation' ) );
		}

		$lessons          = Parish_Formation_Course_Repository::get_published_lessons( $enrollment->course_id );
		$progress         = Parish_Formation_Progress_Repository::get_summary( $enrollment->id, $lessons, $enrollment->course_id );
		$records          = Parish_Formation_Progress_Repository::get_records( $enrollment->id );
		$current_lesson_id = Parish_Formation_Progress_Repository::get_current_lesson_id( $enrollment->id, $lessons );
		$list_url         = add_query_arg( 'page', 'parish-formation-enrollments', admin_url( 'admin.php' ) );
		?>
		<div class="wrap">
			<p><a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php echo esc_html__( 'Back to Enrollments', 'parish-formation' ); ?></a></p>
			<?php self::render_notice(); ?>
			<h1><?php echo esc_html( $enrollment->display_name ); ?></h1>
			<h2><?php echo esc_html( $enrollment->course_title ); ?></h2>

			<table class="widefat striped" style="max-width: 900px; margin-bottom: 24px;">
				<tbody>
					<tr><th scope="row"><?php echo esc_html__( 'Email', 'parish-formation' ); ?></th><td><?php echo esc_html( $enrollment->user_email ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Status', 'parish-formation' ); ?></th><td><?php echo esc_html( ucwords( str_replace( '_', ' ', $enrollment->status ) ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Progress', 'parish-formation' ); ?></th><td><?php echo esc_html( $progress['percentage'] . '%' ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Enrolled', 'parish-formation' ); ?></th><td><?php echo esc_html( self::format_utc_date( $enrollment->enrolled_at ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Started', 'parish-formation' ); ?></th><td><?php echo $enrollment->started_at ? esc_html( self::format_utc_date( $enrollment->started_at ) ) : '&mdash;'; ?></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Completed', 'parish-formation' ); ?></th><td><?php echo $enrollment->completed_at ? esc_html( self::format_utc_date( $enrollment->completed_at ) ) : '&mdash;'; ?></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Expires', 'parish-formation' ); ?></th><td><?php echo $enrollment->expires_at ? esc_html( self::format_utc_date( $enrollment->expires_at ) ) : '&mdash;'; ?></td></tr>
					<?php if ( $enrollment->completion_override_by ) : ?>
						<?php $override_user = get_userdata( $enrollment->completion_override_by ); ?>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Completion override', 'parish-formation' ); ?></th>
							<td>
								<?php echo $override_user ? esc_html( $override_user->display_name ) : esc_html__( 'Unknown staff user', 'parish-formation' ); ?>
								<?php if ( $enrollment->completion_override_at ) : ?>
									&mdash; <?php echo esc_html( self::format_utc_date( $enrollment->completion_override_at ) ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( 'completed' !== $enrollment->status ) : ?>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return window.confirm('<?php echo esc_js( __( 'Mark this entire course complete for the participant?', 'parish-formation' ) ); ?>');">
					<input type="hidden" name="action" value="pf_override_completion" />
					<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( $enrollment->id ); ?>" />
					<?php wp_nonce_field( 'pf_override_completion_' . $enrollment->id ); ?>
					<?php submit_button( __( 'Mark Course Complete', 'parish-formation' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Lesson Progress', 'parish-formation' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Number', 'parish-formation' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Lesson', 'parish-formation' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Requirement', 'parish-formation' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'State', 'parish-formation' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Started', 'parish-formation' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Finished', 'parish-formation' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $lessons as $lesson ) : ?>
						<?php
						$record = $records[ $lesson->ID ] ?? null;
						$state  = $record ? sanitize_key( $record->status ) : ( $current_lesson_id === $lesson->ID ? 'current' : 'locked' );
						?>
						<tr>
							<td><?php echo esc_html( Parish_Formation_Course_Repository::get_lesson_number( $lesson->ID ) ); ?></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $lesson->ID ) ); ?>"><?php echo esc_html( $lesson->post_title ); ?></a></td>
							<td><?php echo Parish_Formation_Course_Repository::is_lesson_required( $lesson->ID ) ? esc_html__( 'Required', 'parish-formation' ) : esc_html__( 'Optional', 'parish-formation' ); ?></td>
							<td><?php echo esc_html( ucfirst( $state ) ); ?></td>
							<td><?php echo $record && $record->started_at ? esc_html( self::format_utc_date( $record->started_at ) ) : '&mdash;'; ?></td>
							<td><?php echo $record && $record->completed_at ? esc_html( self::format_utc_date( $record->completed_at ) ) : '&mdash;'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php self::render_assessment_attempts( $enrollment ); ?>
		</div>
		<?php
	}

	/** Render submitted assessment attempts and pending review controls. */
	private static function render_assessment_attempts( $enrollment ) {
		$attempts = Parish_Formation_Assessment_Repository::get_attempts_for_enrollment( $enrollment->id );
		?>
		<h2><?php esc_html_e( 'Assessment Attempts', 'parish-formation' ); ?></h2>
		<?php if ( ! $attempts ) : ?><p><?php esc_html_e( 'No assessments have been submitted.', 'parish-formation' ); ?></p><?php return; endif; ?>
		<?php foreach ( $attempts as $attempt ) : ?>
			<?php $answers = Parish_Formation_Assessment_Repository::get_attempt_answers( $attempt->id ); ?>
			<div class="postbox" style="padding: 16px; max-width: 1000px;">
				<h3><?php echo esc_html( get_the_title( $attempt->assessment_id ) ); ?> &mdash; <?php echo esc_html( sprintf( __( 'Attempt %d', 'parish-formation' ), $attempt->attempt_number ) ); ?></h3>
				<p><strong><?php esc_html_e( 'Status:', 'parish-formation' ); ?></strong> <?php echo esc_html( ucwords( str_replace( '_', ' ', $attempt->status ) ) ); ?> &nbsp; <strong><?php esc_html_e( 'Submitted:', 'parish-formation' ); ?></strong> <?php echo esc_html( self::format_utc_date( $attempt->submitted_at ) ); ?></p>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="pf_review_assessment" />
					<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( $enrollment->id ); ?>" />
					<input type="hidden" name="attempt_id" value="<?php echo esc_attr( $attempt->id ); ?>" />
					<?php wp_nonce_field( 'pf_review_assessment_' . $attempt->id ); ?>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Question', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Response', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Result / Points', 'parish-formation' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $answers as $answer ) : ?>
							<?php $snapshot = json_decode( $answer->question_snapshot, true ); $question_points = max( 1, absint( $snapshot['points'] ?? 1 ) ); ?>
							<tr>
								<td><?php echo wp_kses_post( $snapshot['prompt'] ?? '' ); ?></td>
								<td><?php echo nl2br( esc_html( $answer->answer ) ); ?></td>
								<td>
									<?php if ( $answer->requires_review && 'pending_review' === $attempt->status ) : ?>
										<label><?php esc_html_e( 'Points', 'parish-formation' ); ?> <input type="number" name="manual_points[<?php echo esc_attr( $answer->id ); ?>]" min="0" max="<?php echo esc_attr( $question_points ); ?>" step="0.01" value="<?php echo esc_attr( $answer->points_awarded ); ?>" class="small-text" /> / <?php echo esc_html( $question_points ); ?></label>
									<?php elseif ( null !== $answer->is_correct ) : ?>
										<?php echo $answer->is_correct ? esc_html__( 'Correct', 'parish-formation' ) : esc_html__( 'Incorrect', 'parish-formation' ); ?> (<?php echo esc_html( $answer->points_awarded ); ?>)
									<?php else : ?><?php echo esc_html( $answer->points_awarded ); ?><?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( 'pending_review' === $attempt->status && current_user_can( 'pf_grade_assessments' ) ) : ?>
						<p><label for="pf-review-note-<?php echo esc_attr( $attempt->id ); ?>"><strong><?php esc_html_e( 'Private review note', 'parish-formation' ); ?></strong></label></p>
						<textarea id="pf-review-note-<?php echo esc_attr( $attempt->id ); ?>" name="review_note" rows="3" class="large-text"></textarea>
						<p><button class="button button-primary" type="submit" name="review_decision" value="passed"><?php esc_html_e( 'Approve / Pass', 'parish-formation' ); ?></button> <button class="button" type="submit" name="review_decision" value="failed"><?php esc_html_e( 'Fail', 'parish-formation' ); ?></button></p>
					<?php elseif ( $attempt->reviewed_by ) : ?>
						<?php $reviewer = get_userdata( $attempt->reviewed_by ); ?><p><strong><?php esc_html_e( 'Reviewed by:', 'parish-formation' ); ?></strong> <?php echo esc_html( $reviewer ? $reviewer->display_name : __( 'Unknown staff user', 'parish-formation' ) ); ?><?php if ( $attempt->reviewed_at ) : ?> &mdash; <?php echo esc_html( self::format_utc_date( $attempt->reviewed_at ) ); ?><?php endif; ?></p>
						<?php if ( $attempt->review_note ) : ?><p><strong><?php esc_html_e( 'Private note:', 'parish-formation' ); ?></strong> <?php echo nl2br( esc_html( $attempt->review_note ) ); ?></p><?php endif; ?>
					<?php endif; ?>
				</form>
			</div>
		<?php endforeach; ?>
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
	 * Process a course-reset request.
	 *
	 * @return void
	 */
	public static function handle_reset() {
		if ( ! current_user_can( 'pf_manage_enrollments' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage enrollments.', 'parish-formation' ) );
		}

		$enrollment_id = isset( $_POST['enrollment_id'] ) ? absint( $_POST['enrollment_id'] ) : 0;
		check_admin_referer( 'pf_reset_enrollment_' . $enrollment_id );

		$result = Parish_Formation_Enrollment_Repository::reset_course( $enrollment_id );

		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice( $result->get_error_code() );
		}

		self::redirect_with_notice( 'reset' );
	}

	/**
	 * Process a staff course-completion override.
	 *
	 * @return void
	 */
	public static function handle_completion_override() {
		if ( ! current_user_can( 'pf_manage_enrollments' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage enrollments.', 'parish-formation' ) );
		}

		$enrollment_id = isset( $_POST['enrollment_id'] ) ? absint( $_POST['enrollment_id'] ) : 0;
		check_admin_referer( 'pf_override_completion_' . $enrollment_id );

		$result = Parish_Formation_Enrollment_Repository::mark_complete_by_staff(
			$enrollment_id,
			get_current_user_id()
		);

		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice( $result->get_error_code() );
		}

		self::redirect_with_notice( 'completion_overridden' );
	}

	/** Process a staff assessment review. */
	public static function handle_assessment_review() {
		if ( ! current_user_can( 'pf_grade_assessments' ) ) {
			wp_die( esc_html__( 'You do not have permission to grade assessments.', 'parish-formation' ) );
		}
		$enrollment_id = isset( $_POST['enrollment_id'] ) ? absint( $_POST['enrollment_id'] ) : 0;
		$attempt_id    = isset( $_POST['attempt_id'] ) ? absint( $_POST['attempt_id'] ) : 0;
		$decision      = isset( $_POST['review_decision'] ) ? sanitize_key( wp_unslash( $_POST['review_decision'] ) ) : '';
		$note          = isset( $_POST['review_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['review_note'] ) ) : '';
		$manual_points = isset( $_POST['manual_points'] ) && is_array( $_POST['manual_points'] ) ? wp_unslash( $_POST['manual_points'] ) : array();
		check_admin_referer( 'pf_review_assessment_' . $attempt_id );

		$result = Parish_Formation_Assessment_Repository::review( $attempt_id, $enrollment_id, $decision, $manual_points, $note, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice( $result->get_error_code(), $enrollment_id );
		}
		$enrollment = Parish_Formation_Enrollment_Repository::get_details( $enrollment_id );
		if ( $enrollment ) {
			Parish_Formation_Progress_Repository::sync_course_completion( $enrollment, Parish_Formation_Course_Repository::get_published_lessons( $enrollment->course_id ) );
		}
		self::redirect_with_notice( 'assessment_reviewed', $enrollment_id );
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
			'reset'                => array( 'success', __( 'The course was reset successfully.', 'parish-formation' ) ),
			'completion_overridden' => array( 'success', __( 'The course was marked complete successfully.', 'parish-formation' ) ),
			'assessment_reviewed'  => array( 'success', __( 'The assessment review was saved successfully.', 'parish-formation' ) ),
			'invalid_attempt'      => array( 'error', __( 'That pending assessment attempt could not be found.', 'parish-formation' ) ),
			'invalid_decision'     => array( 'error', __( 'Select Pass or Fail for the assessment review.', 'parish-formation' ) ),
			'duplicate_enrollment' => array( 'warning', __( 'That user is already enrolled in this course.', 'parish-formation' ) ),
			'invalid_user'         => array( 'error', __( 'Select a valid WordPress user.', 'parish-formation' ) ),
			'invalid_course'       => array( 'error', __( 'Select a valid published course.', 'parish-formation' ) ),
			'invalid_expiration'   => array( 'error', __( 'Enter a valid expiration date.', 'parish-formation' ) ),
			'invalid_enrollment'   => array( 'error', __( 'The enrollment could not be found.', 'parish-formation' ) ),
			'no_lessons'           => array( 'error', __( 'Publish at least one lesson before completing this course.', 'parish-formation' ) ),
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
	private static function redirect_with_notice( $notice_code, $enrollment_id = 0 ) {
		$args = array(
				'page'      => 'parish-formation-enrollments',
				'pf_notice' => sanitize_key( $notice_code ),
			);
		if ( $enrollment_id ) {
			$args['enrollment_id'] = absint( $enrollment_id );
		}
		$url = add_query_arg(
			$args,
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
	private static function get_recent_enrollments( $search_term = '', $course_id = 0, $status = '' ) {
		global $wpdb;

		$enrollments_table = $wpdb->prefix . 'pf_enrollments';
		$users_table       = $wpdb->users;
		$posts_table       = $wpdb->posts;

		$query = "SELECT enrollment.id, enrollment.user_id, enrollment.course_id, enrollment.status,
			enrollment.enrolled_at, enrollment.completed_at, enrollment.expires_at,
			user.display_name, course.post_title AS course_title
			FROM {$enrollments_table} AS enrollment
			INNER JOIN {$users_table} AS user ON user.ID = enrollment.user_id
			INNER JOIN {$posts_table} AS course ON course.ID = enrollment.course_id
			WHERE enrollment.status <> 'unenrolled'";
		$query_parameters = array();

		if ( $search_term ) {
			$search_like        = '%' . $wpdb->esc_like( $search_term ) . '%';
			$query             .= ' AND (user.display_name LIKE %s OR user.user_email LIKE %s)';
			$query_parameters[] = $search_like;
			$query_parameters[] = $search_like;
		}

		if ( $course_id ) {
			$query             .= ' AND enrollment.course_id = %d';
			$query_parameters[] = absint( $course_id );
		}

		if ( $status ) {
			$query             .= ' AND enrollment.status = %s';
			$query_parameters[] = $status;
		}

		$query .= ' ORDER BY enrollment.enrolled_at DESC, enrollment.id DESC LIMIT 50';

		if ( $query_parameters ) {
			$query = $wpdb->prepare( $query, $query_parameters );
		}

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
