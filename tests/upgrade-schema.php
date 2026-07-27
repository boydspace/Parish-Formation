<?php
/** Verify every schema installer against isolated temporary tables. */

$test_url = getenv( 'PF_TEST_URL' ) ?: 'http://parish-formation.test';
$url      = parse_url( $test_url );
$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? ( $url['scheme'] ?? 'http' );
$_SERVER['HTTP_HOST']      = $_SERVER['HTTP_HOST'] ?? ( $url['host'] ?? 'parish-formation.test' );
require_once dirname( __DIR__, 4 ) . '/wp-load.php';

global $wpdb;
$original_prefix = $wpdb->prefix;
$test_prefix     = $original_prefix . 'pf_upgrade_test_' . strtolower( wp_generate_password( 6, false, false ) ) . '_';
$failures        = array();
$checks          = 0;
$tables          = array(
	'pf_enrollments', 'pf_progress', 'pf_assessment_attempts', 'pf_assessment_answers',
	'pf_enrollment_runs', 'pf_certificates', 'pf_notification_log', 'pf_invitations',
	'pf_participant_notes', 'pf_participant_note_events', 'pf_security_events',
);
$assert = static function ( $condition, $message ) use ( &$failures, &$checks ) {
	++$checks;
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

try {
	$wpdb->prefix = $test_prefix;
	foreach ( array( 'install_enrollments_table', 'install_progress_table', 'install_assessment_tables', 'install_enrollment_runs_table', 'install_certificates_table', 'install_notification_log_table', 'install_invitations_table', 'install_participant_notes_tables', 'install_security_events_table' ) as $method_name ) {
		$method = new ReflectionMethod( Parish_Formation_Upgrader::class, $method_name );
		$method->invoke( null );
	}

	foreach ( $tables as $suffix ) {
		$table = $test_prefix . $suffix;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		$assert( $table === $found, "Schema installer did not create {$suffix}." );
	}

	$critical_columns = array(
		'pf_enrollments'          => array( 'current_run', 'enrollment_source', 'completion_override_by' ),
		'pf_assessment_attempts'  => array( 'course_run', 'passing_rule', 'reviewed_by' ),
		'pf_assessment_answers'   => array( 'automatic_points', 'review_status', 'reviewer_user_id', 'learner_feedback' ),
		'pf_certificates'         => array( 'verification_code', 'design_snapshot', 'reissue_of' ),
		'pf_notification_log'     => array( 'participant_user_id', 'initiated_by', 'message_body' ),
		'pf_invitations'          => array( 'token_hash', 'token_encrypted', 'restricted_email' ),
		'pf_security_events'      => array( 'previous_hash', 'event_hash', 'context_json' ),
	);
	foreach ( $critical_columns as $suffix => $columns ) {
		$available = $wpdb->get_col( "SHOW COLUMNS FROM {$test_prefix}{$suffix}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $columns as $column ) {
			$assert( in_array( $column, $available, true ), "{$suffix} is missing {$column}." );
		}
	}

	$critical_indexes = array(
		'pf_enrollments'         => 'user_course',
		'pf_assessment_attempts' => 'enrollment_assessment_run_attempt',
		'pf_certificates'        => 'verification_code',
		'pf_security_events'     => 'event_hash',
	);
	foreach ( $critical_indexes as $suffix => $index_name ) {
		$found = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$test_prefix}{$suffix} WHERE Key_name = %s", $index_name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$assert( null !== $found, "{$suffix} is missing index {$index_name}." );
	}
} catch ( Throwable $error ) {
	$failures[] = 'Upgrade test failed to run: ' . $error->getMessage();
} finally {
	foreach ( $tables as $suffix ) {
		$table = $test_prefix . $suffix;
		if ( str_starts_with( $table, $original_prefix . 'pf_upgrade_test_' ) ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
	$wpdb->prefix = $original_prefix;
}
$leftovers = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $test_prefix ) . '%' ) );
$assert( empty( $leftovers ), 'Temporary upgrade tables were not completely removed.' );

if ( $failures ) {
	fwrite( STDERR, sprintf( "Upgrade schema test failed (%d/%d checks):\n- %s\n", count( $failures ), $checks, implode( "\n- ", $failures ) ) );
	exit( 1 );
}
fwrite( STDOUT, sprintf( "Upgrade schema tests passed: %d checks.\n", $checks ) );
