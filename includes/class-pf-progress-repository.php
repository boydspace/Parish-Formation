<?php
/**
 * Provides lesson-progress data access.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lesson-progress persistence operations.
 */
final class Parish_Formation_Progress_Repository {

	/**
	 * Retrieve lesson statuses for an enrollment.
	 *
	 * @param int $enrollment_id Enrollment ID.
	 * @return array<int,string> Status keyed by lesson ID.
	 */
	public static function get_statuses( $enrollment_id ) {
		$records  = self::get_records( $enrollment_id );
		$statuses = array();

		foreach ( $records as $lesson_id => $record ) {
			$statuses[ $lesson_id ] = sanitize_key( $record->status );
		}

		return $statuses;
	}

	/**
	 * Retrieve progress records keyed by lesson ID.
	 *
	 * @param int $enrollment_id Enrollment ID.
	 * @return array<int,object>
	 */
	public static function get_records( $enrollment_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pf_progress';
		$rows       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT lesson_id, status, started_at, completed_at
				FROM {$table_name}
				WHERE enrollment_id = %d",
				absint( $enrollment_id )
			)
		);
		$records    = array();

		foreach ( $rows as $row ) {
			$records[ absint( $row->lesson_id ) ] = $row;
		}

		return $records;
	}

	/**
	 * Get the first unfinished lesson in an ordered collection.
	 *
	 * @param int       $enrollment_id Enrollment ID.
	 * @param WP_Post[] $lessons       Ordered course lessons.
	 * @return int|null Lesson ID, or null when every lesson is finished.
	 */
	public static function get_current_lesson_id( $enrollment_id, $lessons ) {
		$statuses = self::get_statuses( $enrollment_id );

		foreach ( $lessons as $lesson ) {
			$status = $statuses[ $lesson->ID ] ?? '';

			if ( ! in_array( $status, array( 'completed', 'skipped' ), true ) ) {
				return $lesson->ID;
			}
		}

		return null;
	}

	/**
	 * Finish a lesson and start its enrollment when needed.
	 *
	 * @param object $enrollment Enrollment row.
	 * @param int    $lesson_id  Lesson post ID.
	 * @param string $status     Completed or skipped status.
	 * @return true|WP_Error True on success or error.
	 */
	public static function finish_lesson( $enrollment, $lesson_id, $status ) {
		global $wpdb;

		if ( ! in_array( $status, array( 'completed', 'skipped' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'That lesson status is not valid.', 'parish-formation' ) );
		}

		$table_name = $wpdb->prefix . 'pf_progress';
		$now        = current_time( 'mysql', true );
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE enrollment_id = %d AND lesson_id = %d",
				absint( $enrollment->id ),
				absint( $lesson_id )
			)
		);

		if ( $existing_id ) {
			$saved = $wpdb->update(
				$table_name,
				array(
					'status'       => $status,
					'completed_at' => $now,
					'updated_at'   => $now,
				),
				array( 'id' => absint( $existing_id ) ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$saved = $wpdb->insert(
				$table_name,
				array(
					'enrollment_id' => absint( $enrollment->id ),
					'user_id'       => absint( $enrollment->user_id ),
					'course_id'     => absint( $enrollment->course_id ),
					'lesson_id'     => absint( $lesson_id ),
					'status'        => $status,
					'started_at'    => $now,
					'completed_at'  => $now,
					'created_at'    => $now,
					'updated_at'    => $now,
				),
				array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		if ( false === $saved ) {
			return new WP_Error( 'database_error', __( 'Lesson completion could not be saved.', 'parish-formation' ) );
		}

		$enrollments_table = $wpdb->prefix . 'pf_enrollments';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$enrollments_table}
				SET status = CASE WHEN status = 'enrolled' THEN 'in_progress' ELSE status END,
					started_at = COALESCE(started_at, %s),
					updated_at = %s
				WHERE id = %d",
				$now,
				$now,
				absint( $enrollment->id )
			)
		);

		return true;
	}

	/**
	 * Calculate an enrollment's progress through published lessons.
	 *
	 * @param int       $enrollment_id Enrollment ID.
	 * @param WP_Post[] $lessons       Ordered published lessons.
	 * @param int       $course_id     Course ID; when provided, required assessments are included.
	 * @return array{finished:int,total:int,percentage:int,is_complete:bool}
	 */
	public static function get_summary( $enrollment_id, $lessons, $course_id = 0 ) {
		$statuses = self::get_statuses( $enrollment_id );
		$total    = count( $lessons );
		$finished = 0;

		foreach ( $lessons as $lesson ) {
			$status = $statuses[ $lesson->ID ] ?? '';

			if ( in_array( $status, array( 'completed', 'skipped' ), true ) ) {
				++$finished;
			}
		}

		if ( $course_id ) {
			foreach ( Parish_Formation_Course_Repository::get_published_assessments( $course_id ) as $assessment ) {
				$progression = get_post_meta( $assessment->ID, Parish_Formation_Assessment_Settings::PROGRESSION_META_KEY, true );
				if ( 'no_gate' === $progression ) {
					continue;
				}
				++$total;
				$attempt = Parish_Formation_Assessment_Repository::get_latest_attempt( $enrollment_id, $assessment->ID );
				if ( $attempt && ( 'submit_to_continue' === $progression || (bool) $attempt->passed ) ) {
					++$finished;
				}
			}
		}

		return array(
			'finished'    => $finished,
			'total'       => $total,
			'percentage'  => $total ? (int) round( ( $finished / $total ) * 100 ) : 0,
			'is_complete' => $total > 0 && $finished === $total,
		);
	}

	/**
	 * Synchronize course completion after a progress change.
	 *
	 * @param object    $enrollment Enrollment row.
	 * @param WP_Post[] $lessons   Ordered published lessons.
	 * @return true|WP_Error True on success or error.
	 */
	public static function sync_course_completion( $enrollment, $lessons ) {
		global $wpdb;

		$summary = self::get_summary( $enrollment->id, $lessons, $enrollment->course_id );

		if ( ! $summary['is_complete'] ) {
			return true;
		}


		if ( 'completed' === $enrollment->status && ! empty( $enrollment->completed_at ) ) {
			return true;
		}

		$now        = current_time( 'mysql', true );
		$table_name = $wpdb->prefix . 'pf_enrollments';
		$updated    = $wpdb->update(
			$table_name,
			array(
				'status'       => 'completed',
				'completed_at' => $now,
				'updated_at'   => $now,
			),
			array( 'id' => absint( $enrollment->id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'database_error', __( 'Course completion could not be saved.', 'parish-formation' ) );
		}
		$completed_enrollment = Parish_Formation_Enrollment_Repository::get_details( $enrollment->id );
		$certificate          = Parish_Formation_Certificate_Repository::maybe_issue( $completed_enrollment );
		if ( is_wp_error( $certificate ) && 'certificate_not_eligible' !== $certificate->get_error_code() ) {
			return $certificate;
		}
		Parish_Formation_Notifications::send_course_completed( $enrollment->id );

		return true;
	}
}
