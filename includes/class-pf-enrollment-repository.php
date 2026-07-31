<?php
/**
 * Provides enrollment data access.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This component intentionally reads and writes plugin-owned custom tables; identifiers derive from $wpdb->prefix and mutable values are prepared.

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Repository table identifiers are plugin-owned names derived from $wpdb->prefix; all data values use placeholders.

/**
 * Enrollment persistence operations.
 */
final class Parish_Formation_Enrollment_Repository {

	/**
	 * Retrieve one enrollment with participant and course details for staff.
	 *
	 * @param int $enrollment_id Enrollment ID.
	 * @return object|null
	 */
	public static function get_details( $enrollment_id ) {
		global $wpdb;

		$table_name  = $wpdb->prefix . 'pf_enrollments';
		$users_table = $wpdb->users;
		$posts_table = $wpdb->posts;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT enrollment.*, user.display_name, user.user_email,
					course.post_title AS course_title
				FROM {$table_name} AS enrollment
				INNER JOIN {$users_table} AS user ON user.ID = enrollment.user_id
				INNER JOIN {$posts_table} AS course ON course.ID = enrollment.course_id
				WHERE enrollment.id = %d
				LIMIT 1",
				absint( $enrollment_id )
			)
		);
	}

	/**
	 * Retrieve active enrollments for a participant.
	 *
	 * @param int $user_id Participant user ID.
	 * @return object[]
	 */
	public static function get_for_user( $user_id ) {
		global $wpdb;

		$table_name  = $wpdb->prefix . 'pf_enrollments';
		$posts_table = $wpdb->posts;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT enrollment.id, enrollment.user_id, enrollment.course_id, enrollment.status, enrollment.current_run,
					enrollment.enrolled_at, enrollment.completed_at, enrollment.expires_at,
					course.post_title AS course_title
				FROM {$table_name} AS enrollment
				INNER JOIN {$posts_table} AS course ON course.ID = enrollment.course_id
				WHERE enrollment.user_id = %d
					AND enrollment.status <> 'unenrolled'
					AND course.post_type = %s
					AND course.post_status = 'publish'
				ORDER BY enrollment.enrolled_at DESC, enrollment.id DESC",
				absint( $user_id ),
				Parish_Formation_Course_Post_Type::POST_TYPE
			)
		);
	}

	/**
	 * Retrieve one active enrollment and its published course.
	 *
	 * @param int $user_id   Participant user ID.
	 * @param int $course_id Course post ID.
	 * @return object|null
	 */
	public static function get_for_user_course( $user_id, $course_id ) {
		global $wpdb;

		$table_name  = $wpdb->prefix . 'pf_enrollments';
		$posts_table = $wpdb->posts;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT enrollment.id, enrollment.user_id, enrollment.course_id, enrollment.status, enrollment.current_run,
					enrollment.enrolled_at, enrollment.completed_at, enrollment.expires_at,
					course.post_title AS course_title, course.post_content AS course_content
				FROM {$table_name} AS enrollment
				INNER JOIN {$posts_table} AS course ON course.ID = enrollment.course_id
				WHERE enrollment.user_id = %d
					AND enrollment.course_id = %d
					AND enrollment.status <> 'unenrolled'
					AND course.post_type = %s
					AND course.post_status = 'publish'
				LIMIT 1",
				absint( $user_id ),
				absint( $course_id ),
				Parish_Formation_Course_Post_Type::POST_TYPE
			)
		);
	}

	/**
	 * Create a manual enrollment.
	 *
	 * @param int $user_id    Participant user ID.
	 * @param int $course_id  Course post ID.
	 * @param int         $created_by Staff user ID.
	 * @param string|null $expires_at UTC expiration datetime, or null.
	 * @return int|WP_Error Enrollment ID or error.
	 */
	public static function create_manual( $user_id, $course_id, $created_by, $expires_at = null ) {
		return self::create( $user_id, $course_id, 'manual', $created_by, $expires_at );
	}

	/**
	 * Create an enrollment initiated by the participant.
	 *
	 * @param int $user_id   Participant user ID.
	 * @param int $course_id Course post ID.
	 * @return int|WP_Error Enrollment ID or error.
	 */
	public static function create_self_enrollment( $user_id, $course_id ) {
		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'invalid_user', __( 'Your user account could not be found.', 'parish-formation' ) );
		}

		if ( Parish_Formation_Course_Post_Type::POST_TYPE !== get_post_type( $course_id ) || 'publish' !== get_post_status( $course_id ) ) {
			return new WP_Error( 'invalid_course', __( 'This course is not available for enrollment.', 'parish-formation' ) );
		}

		if ( ! get_post_meta( $course_id, Parish_Formation_Course_Settings::OPEN_ENROLLMENT_META_KEY, true ) ) {
			return new WP_Error( 'enrollment_closed', __( 'Open enrollment is not available for this course.', 'parish-formation' ) );
		}

		return self::create( $user_id, $course_id, 'self', 0 );
	}

	/**
	 * Create an enrollment authorized by a course access code.
	 *
	 * @param int $user_id   Participant user ID.
	 * @param int $course_id Course post ID.
	 * @return int|WP_Error Enrollment ID or error.
	 */
	public static function create_access_code_enrollment( $user_id, $course_id ) {
		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'invalid_user', __( 'Your user account could not be found.', 'parish-formation' ) );
		}

		if ( Parish_Formation_Course_Post_Type::POST_TYPE !== get_post_type( $course_id ) || 'publish' !== get_post_status( $course_id ) ) {
			return new WP_Error( 'invalid_course', __( 'This course is not available for enrollment.', 'parish-formation' ) );
		}

		return self::create( $user_id, $course_id, 'access_code', 0 );
	}

	/** Create an enrollment authorized by a secure invitation. */
	public static function create_invitation_enrollment( $user_id, $course_id, $created_by = 0 ) {
		if ( ! get_userdata( $user_id ) || Parish_Formation_Course_Post_Type::POST_TYPE !== get_post_type( $course_id ) || 'publish' !== get_post_status( $course_id ) ) {
			return new WP_Error( 'invalid_invitation', __( 'This course invitation is not available.', 'parish-formation' ) );
		}
		return self::create( $user_id, $course_id, 'invitation', $created_by );
	}

	/** Create or reactivate an enrollment from a validated source. */
	private static function create( $user_id, $course_id, $source, $created_by, $expires_at = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pf_enrollments';
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE user_id = %d AND course_id = %d LIMIT 1",
				$user_id,
				$course_id
			)
		);

		if ( $existing_id ) {
			$existing_status = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT status FROM {$table_name} WHERE id = %d",
					$existing_id
				)
			);

			if ( 'unenrolled' === $existing_status ) {
				$now       = current_time( 'mysql', true );
				$reactivated = $wpdb->update(
					$table_name,
					array(
						'status'            => 'enrolled',
						'enrolled_at'       => $now,
						'started_at'        => null,
						'completed_at'      => null,
						'completion_override_by' => null,
						'completion_override_at' => null,
						'expires_at'        => $expires_at,
						'enrollment_source' => sanitize_key( $source ),
						'created_by'        => absint( $created_by ),
						'updated_at'        => $now,
					),
					array( 'id' => absint( $existing_id ) ),
					array( '%s', '%s', null, null, null, null, '%s', '%s', '%d', '%s' ),
					array( '%d' )
				);

				if ( false === $reactivated ) {
					return new WP_Error( 'database_error', __( 'The enrollment could not be saved.', 'parish-formation' ) );
				}
				Parish_Formation_Security_Event_Repository::record( 'enrollment_reactivated', 'enrollment', $existing_id, array( 'source' => $source ), $created_by ?: $user_id, $user_id, $course_id );

				return absint( $existing_id );
			}

			return new WP_Error( 'duplicate_enrollment', __( 'That user is already enrolled in this course.', 'parish-formation' ) );
		}

		$now = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'user_id'          => absint( $user_id ),
				'course_id'        => absint( $course_id ),
				'status'           => 'enrolled',
				'enrolled_at'      => $now,
				'enrollment_source' => sanitize_key( $source ),
				'expires_at'        => $expires_at,
				'created_by'       => absint( $created_by ),
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'database_error', __( 'The enrollment could not be saved.', 'parish-formation' ) );
		}
		$enrollment_id = absint( $wpdb->insert_id );
		Parish_Formation_Security_Event_Repository::record( 'enrollment_created', 'enrollment', $enrollment_id, array( 'source' => $source ), $created_by ?: $user_id, $user_id, $course_id );

		return $enrollment_id;
	}

	/**
	 * Mark an enrollment as unenrolled without deleting its history.
	 *
	 * @param int $enrollment_id Enrollment ID.
	 * @return true|WP_Error True on success or error.
	 */
	public static function unenroll( $enrollment_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pf_enrollments';
		$enrollment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status FROM {$table_name} WHERE id = %d",
				$enrollment_id
			)
		);

		if ( ! $enrollment ) {
			return new WP_Error( 'invalid_enrollment', __( 'The enrollment could not be found.', 'parish-formation' ) );
		}

		if ( 'unenrolled' === $enrollment->status ) {
			return true;
		}

		$updated = $wpdb->update(
			$table_name,
			array(
				'status'     => 'unenrolled',
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $enrollment_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'database_error', __( 'The enrollment could not be updated.', 'parish-formation' ) );
		}
		$details = self::get_details( $enrollment_id );
		Parish_Formation_Security_Event_Repository::record( 'enrollment_unenrolled', 'enrollment', $enrollment_id, array(), null, $details ? $details->user_id : 0, $details ? $details->course_id : 0 );

		return true;
	}

	/**
	 * Reset an enrollment while preserving an immutable archive of its assessment activity.
	 *
	 * @param int $enrollment_id Enrollment ID.
	 * @param int $staff_user_id Staff user performing the reset.
	 * @return true|WP_Error True on success or error.
	 */
	public static function reset_course( $enrollment_id, $staff_user_id = 0 ) {
		global $wpdb;

		$enrollments_table = $wpdb->prefix . 'pf_enrollments';
		$progress_table    = $wpdb->prefix . 'pf_progress';
		$runs_table        = $wpdb->prefix . 'pf_enrollment_runs';
		$enrollment        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$enrollments_table} WHERE id = %d AND status <> 'unenrolled'",
				absint( $enrollment_id )
			)
		);

		if ( ! $enrollment ) {
			return new WP_Error( 'invalid_enrollment', __( 'The enrollment could not be found.', 'parish-formation' ) );
		}

		$now = current_time( 'mysql', true );
		$wpdb->query( 'START TRANSACTION' );
		$run_number = max( 1, absint( $enrollment->current_run ) );
		$run_archived = $wpdb->insert(
			$runs_table,
			array(
				'enrollment_id' => absint( $enrollment_id ),
				'run_number'    => $run_number,
				'status'        => sanitize_key( $enrollment->status ),
				'enrolled_at'   => $enrollment->enrolled_at,
				'started_at'    => $enrollment->started_at,
				'completed_at'  => $enrollment->completed_at,
				'reset_by'      => absint( $staff_user_id ),
				'reset_at'      => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		$progress_deleted = $wpdb->delete(
			$progress_table,
			array( 'enrollment_id' => absint( $enrollment_id ) ),
			array( '%d' )
		);
		$enrollment_reset = $wpdb->update(
			$enrollments_table,
			array(
				'status'       => 'enrolled',
				'current_run'  => $run_number + 1,
				'enrolled_at'  => $now,
				'started_at'   => null,
				'completed_at' => null,
				'completion_override_by' => null,
				'completion_override_at' => null,
				'last_reset_by' => absint( $staff_user_id ),
				'last_reset_at' => $now,
				'updated_at'   => $now,
			),
			array( 'id' => absint( $enrollment_id ) ),
			array( '%s', '%d', '%s', null, null, null, null, '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $run_archived || false === $progress_deleted || false === $enrollment_reset ) {
			$wpdb->query( 'ROLLBACK' );

			return new WP_Error( 'database_error', __( 'The course could not be reset.', 'parish-formation' ) );
		}

		$wpdb->query( 'COMMIT' );
		Parish_Formation_Security_Event_Repository::record( 'enrollment_reset', 'enrollment', $enrollment_id, array( 'archived_run' => $run_number, 'new_run' => $run_number + 1 ), $staff_user_id, $enrollment->user_id, $enrollment->course_id );

		return true;
	}

	/**
	 * Complete a course on behalf of a participant and record the staff override.
	 *
	 * @param int $enrollment_id Enrollment ID.
	 * @param int $staff_user_id Staff WordPress user ID.
	 * @return true|WP_Error True on success or error.
	 */
	public static function mark_complete_by_staff( $enrollment_id, $staff_user_id ) {
		global $wpdb;

		$enrollment = self::get_details( $enrollment_id );

		if ( ! $enrollment || 'unenrolled' === $enrollment->status ) {
			return new WP_Error( 'invalid_enrollment', __( 'The enrollment could not be found.', 'parish-formation' ) );
		}

		$lessons = Parish_Formation_Course_Repository::get_published_lessons( $enrollment->course_id );

		if ( ! $lessons ) {
			return new WP_Error( 'no_lessons', __( 'Publish at least one lesson before completing this course.', 'parish-formation' ) );
		}

		$wpdb->query( 'START TRANSACTION' );

		foreach ( $lessons as $lesson ) {
			$result = Parish_Formation_Progress_Repository::finish_lesson( $enrollment, $lesson->ID, 'completed' );

			if ( is_wp_error( $result ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $result;
			}
		}

		$now        = current_time( 'mysql', true );
		$table_name = $wpdb->prefix . 'pf_enrollments';
		$updated    = $wpdb->update(
			$table_name,
			array(
				'status'                 => 'completed',
				'completed_at'           => $now,
				'completion_override_by' => absint( $staff_user_id ),
				'completion_override_at' => $now,
				'updated_at'             => $now,
			),
			array( 'id' => absint( $enrollment_id ) ),
			array( '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'database_error', __( 'The completion override could not be saved.', 'parish-formation' ) );
		}

		$wpdb->query( 'COMMIT' );
		Parish_Formation_Security_Event_Repository::record( 'enrollment_completion_overridden', 'enrollment', $enrollment_id, array(), $staff_user_id, $enrollment->user_id, $enrollment->course_id );
		$completed_enrollment = self::get_details( $enrollment_id );
		$certificate          = Parish_Formation_Certificate_Repository::maybe_issue( $completed_enrollment, $staff_user_id );
		if ( is_wp_error( $certificate ) && 'certificate_not_eligible' !== $certificate->get_error_code() ) {
			return $certificate;
		}
		Parish_Formation_Notifications::send_course_completed( $enrollment_id );

		return true;
	}
}
