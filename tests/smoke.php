<?php
/**
 * Read-only integration smoke test for a local WordPress installation.
 *
 * Run with: composer test
 */

$test_url = getenv( 'PF_TEST_URL' ) ?: 'http://parish-formation.test';
$url      = parse_url( $test_url );
if ( empty( $_SERVER['REQUEST_SCHEME'] ) ) {
	$_SERVER['REQUEST_SCHEME'] = $url['scheme'] ?? 'http';
}
if ( empty( $_SERVER['HTTP_HOST'] ) ) {
	$_SERVER['HTTP_HOST'] = $url['host'] ?? 'parish-formation.test';
}

$wordpress_loader = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! file_exists( $wordpress_loader ) ) {
	fwrite( STDERR, "WordPress could not be found relative to the plugin directory.\n" );
	exit( 2 );
}

require_once $wordpress_loader;

$failures = array();
$checks   = 0;

$assert = static function ( $condition, $message ) use ( &$failures, &$checks ) {
	++$checks;
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$assert( defined( 'PARISH_FORMATION_VERSION' ), 'Plugin version constant is not defined.' );
$assert( defined( 'PARISH_FORMATION_DB_VERSION' ), 'Database version constant is not defined.' );
$assert( get_option( 'parish_formation_db_version' ) === PARISH_FORMATION_DB_VERSION, 'Installed database version does not match the plugin.' );
$assert( has_action( 'wp_initialize_site', array( 'Parish_Formation_Multisite', 'initialize_new_site' ) ) !== false, 'New multisite sites are not registered for initialization.' );
$assert( has_filter( 'wp_privacy_personal_data_exporters', array( 'Parish_Formation_Privacy', 'register_exporter' ) ) !== false, 'Privacy exporter is not registered.' );
$assert( has_filter( 'wp_privacy_personal_data_erasers', array( 'Parish_Formation_Privacy', 'register_eraser' ) ) !== false, 'Privacy eraser is not registered.' );
$assert( wp_next_scheduled( 'pf_daily_notification_events' ) !== false, 'Daily notification event is not scheduled.' );
$assert( wp_next_scheduled( Parish_Formation_Retention::CRON_HOOK ) !== false, 'Daily retention event is not scheduled.' );
$assert( class_exists( 'Parish_Formation_Assessment_File_Service' ), 'Private assessment file service is not loaded.' );
$assert( has_action( 'admin_post_pf_download_assessment_file', array( 'Parish_Formation_Assessment_File_Service', 'download' ) ) !== false, 'Protected assessment download action is not registered.' );
$assert( has_action( 'admin_post_pf_preview_assessment_file', array( 'Parish_Formation_Assessment_File_Service', 'preview' ) ) !== false, 'Protected assessment image preview action is not registered.' );

foreach ( array( 'pf_course', 'pf_lesson', 'pf_assessment', 'pf_question', 'pf_cert_design' ) as $post_type ) {
	$assert( post_type_exists( $post_type ), "Post type {$post_type} is not registered." );
}

foreach ( array( 'parish_formation_login', 'parish_formation_registration', 'parish_formation_courses', 'formation-certificate' ) as $shortcode ) {
	$assert( shortcode_exists( $shortcode ), "Shortcode {$shortcode} is not registered." );
}

foreach ( array( 'parish_formation_participant', 'parish_formation_coordinator', 'parish_formation_administrator' ) as $role_name ) {
	$assert( get_role( $role_name ) instanceof WP_Role, "Role {$role_name} is not installed." );
}

global $wpdb;
foreach (
	array(
		'pf_enrollments',
		'pf_progress',
		'pf_assessment_attempts',
		'pf_assessment_answers',
		'pf_enrollment_runs',
		'pf_certificates',
		'pf_notification_log',
		'pf_invitations',
		'pf_participant_notes',
		'pf_participant_note_events',
		'pf_security_events',
	) as $suffix
) {
	$table = $wpdb->prefix . $suffix;
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	$assert( $found === $table, "Database table {$table} is missing." );
}

if ( $failures ) {
	fwrite( STDERR, sprintf( "Parish Formation smoke test failed (%d/%d checks):\n- %s\n", count( $failures ), $checks, implode( "\n- ", $failures ) ) );
	exit( 1 );
}

fwrite( STDOUT, sprintf( "Parish Formation smoke test passed: %d checks.\n", $checks ) );
