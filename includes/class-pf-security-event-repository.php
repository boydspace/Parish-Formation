<?php
/** Tamper-evident security event storage. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Parish_Formation_Security_Event_Repository {
	/** Append one event to the site-specific hash chain. */
	public static function record( $event_type, $object_type, $object_id = 0, $context = array(), $actor_user_id = null, $participant_user_id = 0, $course_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pf_security_events';
		$actor_user_id = null === $actor_user_id ? get_current_user_id() : absint( $actor_user_id );
		$event_type     = sanitize_key( $event_type );
		$object_type    = sanitize_key( $object_type );
		$created_at     = current_time( 'mysql', true );
		$context_json   = wp_json_encode( self::sanitize_context( $context ), JSON_UNESCAPED_SLASHES );
		$lock_name      = 'pf_security_events_' . get_current_blog_id();
		$lock_acquired  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) );
		if ( 1 !== $lock_acquired ) {
			return new WP_Error( 'security_event_lock_timeout', __( 'The security event ledger is busy. Please try again.', 'parish-formation' ) );
		}
		$previous_hash  = (string) $wpdb->get_var( "SELECT event_hash FROM {$table} ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$payload        = implode( '|', array( $previous_hash, $event_type, $object_type, absint( $object_id ), $actor_user_id, absint( $participant_user_id ), absint( $course_id ), $context_json, $created_at ) );
		$event_hash     = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		$saved          = $wpdb->insert(
			$table,
			array(
				'event_type'         => $event_type,
				'object_type'        => $object_type,
				'object_id'          => absint( $object_id ),
				'actor_user_id'      => $actor_user_id,
				'participant_user_id'=> absint( $participant_user_id ),
				'course_id'          => absint( $course_id ),
				'context_json'       => $context_json,
				'previous_hash'      => $previous_hash,
				'event_hash'         => $event_hash,
				'created_at'         => $created_at,
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		$event_id = absint( $wpdb->insert_id );
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		return false === $saved ? new WP_Error( 'security_event_database_error', __( 'The security event could not be recorded.', 'parish-formation' ) ) : $event_id;
	}

	/** Return recent events newest first. */
	public static function get_recent( $limit = 200 ) {
		global $wpdb;
		$limit = min( 500, max( 1, absint( $limit ) ) );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_security_events ORDER BY id DESC LIMIT %d", $limit ) );
	}

	/** Verify every stored event against its predecessor and signed payload. */
	public static function verify_chain() {
		global $wpdb;
		$events        = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}pf_security_events ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$previous_hash = '';
		foreach ( $events as $event ) {
			$payload  = implode( '|', array( $previous_hash, $event->event_type, $event->object_type, absint( $event->object_id ), absint( $event->actor_user_id ), absint( $event->participant_user_id ), absint( $event->course_id ), $event->context_json, $event->created_at ) );
			$expected = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
			if ( ! hash_equals( (string) $event->previous_hash, $previous_hash ) || ! hash_equals( (string) $event->event_hash, $expected ) ) {
				return new WP_Error( 'security_event_chain_invalid', sprintf( __( 'Security event %d failed integrity verification.', 'parish-formation' ), $event->id ) );
			}
			$previous_hash = $event->event_hash;
		}
		return true;
	}

	/** Recursively retain only safe scalar context values. */
	private static function sanitize_context( $context ) {
		if ( ! is_array( $context ) ) {
			return array();
		}
		$safe = array();
		foreach ( $context as $key => $value ) {
			$key = sanitize_key( $key );
			if ( preg_match( '/(?:password|passwd|secret|token|access_code|email)/', $key ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$safe[ $key ] = self::sanitize_context( $value );
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$safe[ $key ] = $value;
			} elseif ( is_scalar( $value ) ) {
				$safe[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $safe;
	}
}
