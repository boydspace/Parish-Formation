<?php
/** Isolated tamper-evident security ledger integration test. */

$test_url = getenv( 'PF_TEST_URL' ) ?: 'http://parish-formation.test';
$url      = parse_url( $test_url );
$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? ( $url['scheme'] ?? 'http' );
$_SERVER['HTTP_HOST']      = $_SERVER['HTTP_HOST'] ?? ( $url['host'] ?? 'parish-formation.test' );
require_once dirname( __DIR__, 4 ) . '/wp-load.php';

$failures = array();
$checks   = 0;
$event_ids = array();
$assert = static function ( $condition, $message ) use ( &$failures, &$checks ) {
	++$checks;
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

global $wpdb;
$table = $wpdb->prefix . 'pf_security_events';
try {
	$first_id = Parish_Formation_Security_Event_Repository::record( 'test_security_started', 'test_fixture', wp_rand( 100000, 999999 ), array( 'result' => 'started', 'password' => 'must-not-be-stored', 'access_code' => 'must-not-be-stored' ), 0 );
	if ( is_wp_error( $first_id ) ) {
		throw new RuntimeException( $first_id->get_error_message() );
	}
	$event_ids[] = $first_id;
	$second_id = Parish_Formation_Security_Event_Repository::record( 'test_security_finished', 'test_fixture', $first_id, array( 'result' => 'passed' ), 0 );
	if ( is_wp_error( $second_id ) ) {
		throw new RuntimeException( $second_id->get_error_message() );
	}
	$event_ids[] = $second_id;

	$first = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $first_id ) );
	$second = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $second_id ) );
	$assert( $first && $second, 'Security events were not persisted.' );
	$assert( 64 === strlen( $first->event_hash ) && $second->previous_hash === $first->event_hash, 'Security events were not hash-chained.' );
	$assert( ! str_contains( $first->context_json, 'must-not-be-stored' ) && ! str_contains( $first->context_json, 'password' ) && ! str_contains( $first->context_json, 'access_code' ), 'Secret context entered the security ledger.' );
	$assert( true === Parish_Formation_Security_Event_Repository::verify_chain(), 'Valid security event chain failed verification.' );

	$original_context = $second->context_json;
	$wpdb->update( $table, array( 'context_json' => '{"result":"altered"}' ), array( 'id' => $second_id ), array( '%s' ), array( '%d' ) );
	$altered = Parish_Formation_Security_Event_Repository::verify_chain();
	$assert( is_wp_error( $altered ) && 'security_event_chain_invalid' === $altered->get_error_code(), 'Altered security event was not detected.' );
	$wpdb->update( $table, array( 'context_json' => $original_context ), array( 'id' => $second_id ), array( '%s' ), array( '%d' ) );
	$assert( true === Parish_Formation_Security_Event_Repository::verify_chain(), 'Restored security event chain did not verify.' );
} catch ( Throwable $error ) {
	$failures[] = 'Test setup failed: ' . $error->getMessage();
} finally {
	foreach ( array_reverse( $event_ids ) as $event_id ) {
		$wpdb->delete( $table, array( 'id' => $event_id ), array( '%d' ) );
	}
}

if ( $failures ) {
	fwrite( STDERR, sprintf( "Security event test failed (%d/%d checks):\n- %s\n", count( $failures ), $checks, implode( "\n- ", $failures ) ) );
	exit( 1 );
}
fwrite( STDOUT, sprintf( "Security event tests passed: %d checks.\n", $checks ) );
