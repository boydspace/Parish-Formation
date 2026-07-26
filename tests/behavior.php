<?php
/**
 * Isolated authorization and lesson-progression integration tests.
 *
 * Run with: composer test:behavior
 */

$test_url = getenv( 'PF_TEST_URL' ) ?: 'http://parish-formation.test';
$url      = parse_url( $test_url );
$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? ( $url['scheme'] ?? 'http' );
$_SERVER['HTTP_HOST']      = $_SERVER['HTTP_HOST'] ?? ( $url['host'] ?? 'parish-formation.test' );

require_once dirname( __DIR__, 4 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$failures     = array();
$checks       = 0;
$created_users = array();
$created_posts = array();
$enrollment_id = 0;
$course_id     = 0;
global $wpdb;
$security_baseline = absint( $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}pf_security_events" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$assert = static function ( $condition, $message ) use ( &$failures, &$checks ) {
	++$checks;
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

try {
	$suffix = strtolower( wp_generate_password( 8, false, false ) );
	foreach (
		array(
			'participant'   => 'parish_formation_participant',
			'coordinator'   => 'parish_formation_coordinator',
			'administrator' => 'parish_formation_administrator',
		) as $label => $role
	) {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'pf_test_' . $label . '_' . $suffix,
				'user_email' => 'pf_test_' . $label . '_' . $suffix . '@example.invalid',
				'user_pass'  => wp_generate_password( 24, true, true ),
				'role'       => $role,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			throw new RuntimeException( $user_id->get_error_message() );
		}
		$created_users[ $label ] = $user_id;
	}

	$participant = get_userdata( $created_users['participant'] );
	$coordinator = get_userdata( $created_users['coordinator'] );
	$administrator = get_userdata( $created_users['administrator'] );
	$assert( user_can( $participant, 'pf_access_formation' ), 'Participant cannot access formation.' );
	$assert( ! user_can( $participant, 'pf_manage_courses' ), 'Participant can manage courses.' );
	$assert( ! user_can( $participant, 'pf_view_reports' ), 'Participant can view staff reports.' );
	foreach ( array( 'pf_manage_courses', 'pf_manage_enrollments', 'pf_manage_assessments', 'pf_grade_assessments', 'pf_view_reports' ) as $capability ) {
		$assert( user_can( $coordinator, $capability ), "Coordinator lacks {$capability}." );
	}
	$assert( ! user_can( $coordinator, 'pf_manage_settings' ), 'Coordinator can manage plugin settings.' );
	$assert( ! user_can( $coordinator, 'pf_manage_roles' ), 'Coordinator can manage roles.' );
	foreach ( array( 'pf_manage_settings', 'pf_manage_roles', 'pf_override_assessment_attempts' ) as $capability ) {
		$assert( user_can( $administrator, $capability ), "Formation administrator lacks {$capability}." );
	}

	$course_id = wp_insert_post(
		array(
			'post_type'   => Parish_Formation_Course_Post_Type::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'PF behavior test course ' . $suffix,
		)
	);
	if ( is_wp_error( $course_id ) || ! $course_id ) {
		throw new RuntimeException( 'Test course could not be created.' );
	}
	$created_posts[] = $course_id;

	$lessons = array();
	foreach ( array( 'First', 'Second' ) as $index => $name ) {
		$lesson_id = wp_insert_post(
			array(
				'post_type'   => Parish_Formation_Lesson_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => "{$name} PF behavior lesson {$suffix}",
			)
		);
		if ( is_wp_error( $lesson_id ) || ! $lesson_id ) {
			throw new RuntimeException( 'Test lesson could not be created.' );
		}
		$created_posts[] = $lesson_id;
		$lessons[]       = get_post( $lesson_id );
		update_post_meta( $lesson_id, '_pf_course_id', $course_id );
		update_post_meta( $lesson_id, '_pf_is_required', 1 );
		update_post_meta( $lesson_id, Parish_Formation_Course_Settings::CURRICULUM_ORDER_META_KEY, $index + 1 );
	}

	$closed_result = Parish_Formation_Enrollment_Repository::create_self_enrollment( $created_users['participant'], $course_id );
	$assert( is_wp_error( $closed_result ) && 'enrollment_closed' === $closed_result->get_error_code(), 'Closed course allowed self-enrollment.' );
	update_post_meta( $course_id, Parish_Formation_Course_Settings::OPEN_ENROLLMENT_META_KEY, 1 );
	$enrollment_id = Parish_Formation_Enrollment_Repository::create_self_enrollment( $created_users['participant'], $course_id );
	$assert( is_int( $enrollment_id ) && $enrollment_id > 0, 'Open course did not create an enrollment.' );
	$created_event = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_security_events WHERE event_type=%s AND object_id=%d ORDER BY id DESC LIMIT 1", 'enrollment_created', $enrollment_id ) );
	$assert( $created_event && $created_users['participant'] === (int) $created_event->participant_user_id && $course_id === (int) $created_event->course_id, 'Enrollment creation was not recorded in the security ledger.' );
	$duplicate = Parish_Formation_Enrollment_Repository::create_self_enrollment( $created_users['participant'], $course_id );
	$assert( is_wp_error( $duplicate ) && 'duplicate_enrollment' === $duplicate->get_error_code(), 'Duplicate enrollment was not rejected.' );

	$enrollment = Parish_Formation_Enrollment_Repository::get_for_user_course( $created_users['participant'], $course_id );
	$curriculum = array(
		array( 'type' => 'lesson', 'post' => $lessons[0] ),
		array( 'type' => 'lesson', 'post' => $lessons[1] ),
	);
	$lock_method = new ReflectionMethod( Parish_Formation_Shortcodes::class, 'is_curriculum_item_locked' );
	$assert( false === $lock_method->invoke( null, $curriculum, $lessons[0]->ID, array(), $enrollment_id ), 'First lesson is locked before starting.' );
	$assert( true === $lock_method->invoke( null, $curriculum, $lessons[1]->ID, array(), $enrollment_id ), 'Second lesson is unlocked before the first is complete.' );

	$summary = Parish_Formation_Progress_Repository::get_summary( $enrollment_id, $lessons, $course_id );
	$assert( 0 === $summary['finished'] && 2 === $summary['total'] && 0 === $summary['percentage'], 'Initial progress summary is incorrect.' );
	$assert( is_wp_error( Parish_Formation_Progress_Repository::finish_lesson( $enrollment, $lessons[0]->ID, 'invalid' ) ), 'Invalid lesson status was accepted.' );
	$assert( true === Parish_Formation_Progress_Repository::finish_lesson( $enrollment, $lessons[0]->ID, 'completed' ), 'First lesson could not be completed.' );
	$statuses = Parish_Formation_Progress_Repository::get_statuses( $enrollment_id );
	$assert( false === $lock_method->invoke( null, $curriculum, $lessons[1]->ID, $statuses, $enrollment_id ), 'Second lesson stayed locked after the first was completed.' );
	$summary = Parish_Formation_Progress_Repository::get_summary( $enrollment_id, $lessons, $course_id );
	$assert( 1 === $summary['finished'] && 50 === $summary['percentage'] && ! $summary['is_complete'], 'Half-complete progress summary is incorrect.' );
	$assert( true === Parish_Formation_Progress_Repository::finish_lesson( $enrollment, $lessons[1]->ID, 'completed' ), 'Second lesson could not be completed.' );
	$summary = Parish_Formation_Progress_Repository::get_summary( $enrollment_id, $lessons, $course_id );
	$assert( 2 === $summary['finished'] && 100 === $summary['percentage'] && $summary['is_complete'], 'Completed progress summary is incorrect.' );
	$assert( true === Parish_Formation_Enrollment_Repository::unenroll( $enrollment_id ), 'Enrollment could not be removed.' );
	$assert( null === Parish_Formation_Enrollment_Repository::get_for_user_course( $created_users['participant'], $course_id ), 'Unenrolled course remains active for participant.' );
	$assert( (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}pf_security_events WHERE event_type=%s AND object_id=%d", 'enrollment_unenrolled', $enrollment_id ) ), 'Unenrollment was not recorded in the security ledger.' );
} catch ( Throwable $error ) {
	$failures[] = 'Test setup failed: ' . $error->getMessage();
} finally {
	global $wpdb;
	if ( $enrollment_id ) {
		$wpdb->delete( $wpdb->prefix . 'pf_progress', array( 'enrollment_id' => $enrollment_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'pf_enrollments', array( 'id' => $enrollment_id ), array( '%d' ) );
	}
	if ( $course_id && ! empty( $created_users['participant'] ) ) {
		$wpdb->delete( $wpdb->prefix . 'pf_enrollments', array( 'user_id' => $created_users['participant'], 'course_id' => $course_id ), array( '%d', '%d' ) );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}pf_security_events WHERE id > %d", $security_baseline ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( array_reverse( $created_posts ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( $created_users as $user_id ) {
		wp_delete_user( $user_id );
	}
	wp_set_current_user( 0 );
}

if ( $failures ) {
	fwrite( STDERR, sprintf( "Behavior test failed (%d/%d checks):\n- %s\n", count( $failures ), $checks, implode( "\n- ", $failures ) ) );
	exit( 1 );
}

fwrite( STDOUT, sprintf( "Authorization and progression tests passed: %d checks.\n", $checks ) );
