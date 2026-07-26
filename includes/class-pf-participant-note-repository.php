<?php
/** Participant staff-note persistence and immutable audit history. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Stores private staff notes separately from participant-facing profile data. */
final class Parish_Formation_Participant_Note_Repository {
	/** Return active notes for a participant, newest first. */
	public static function get_active( $user_id ) {
		global $wpdb;
		$notes = $wpdb->prefix . 'pf_participant_notes';
		$users = $wpdb->users;
		return $wpdb->get_results( $wpdb->prepare( "SELECT n.*, creator.display_name created_by_name, editor.display_name updated_by_name FROM {$notes} n LEFT JOIN {$users} creator ON creator.ID=n.created_by LEFT JOIN {$users} editor ON editor.ID=n.updated_by WHERE n.participant_user_id=%d AND n.deleted_at IS NULL ORDER BY n.updated_at DESC, n.id DESC", absint( $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Return all immutable note events for a participant, newest first. */
	public static function get_history( $user_id ) {
		global $wpdb;
		$events = $wpdb->prefix . 'pf_participant_note_events';
		$users  = $wpdb->users;
		return $wpdb->get_results( $wpdb->prepare( "SELECT e.*, actor.display_name actor_name FROM {$events} e LEFT JOIN {$users} actor ON actor.ID=e.actor_user_id WHERE e.participant_user_id=%d ORDER BY e.created_at DESC, e.id DESC", absint( $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Create a note and its audit event. */
	public static function create( $user_id, $body, $actor_id ) {
		global $wpdb;
		$now   = current_time( 'mysql', true );
		$table = $wpdb->prefix . 'pf_participant_notes';
		$wpdb->query( 'START TRANSACTION' );
		$ok = $wpdb->insert( $table, array( 'participant_user_id' => absint( $user_id ), 'note_body' => $body, 'created_by' => absint( $actor_id ), 'created_at' => $now, 'updated_by' => absint( $actor_id ), 'updated_at' => $now ), array( '%d', '%s', '%d', '%s', '%d', '%s' ) );
		if ( ! $ok ) { $wpdb->query( 'ROLLBACK' ); return false; }
		$note_id = absint( $wpdb->insert_id );
		if ( ! self::record_event( $note_id, $user_id, 'created', $body, $actor_id ) ) { $wpdb->query( 'ROLLBACK' ); return false; }
		$wpdb->query( 'COMMIT' );
		return $note_id;
	}

	/** Update a note and retain the resulting snapshot. */
	public static function update( $note_id, $user_id, $body, $actor_id ) {
		global $wpdb;
		$note = self::get_editable( $note_id, $user_id );
		if ( ! $note ) { return false; }
		$now = current_time( 'mysql', true );
		$wpdb->query( 'START TRANSACTION' );
		$ok = $wpdb->update( $wpdb->prefix . 'pf_participant_notes', array( 'note_body' => $body, 'updated_by' => absint( $actor_id ), 'updated_at' => $now ), array( 'id' => absint( $note_id ), 'participant_user_id' => absint( $user_id ) ), array( '%s', '%d', '%s' ), array( '%d', '%d' ) );
		if ( false === $ok || ! self::record_event( $note_id, $user_id, 'updated', $body, $actor_id ) ) { $wpdb->query( 'ROLLBACK' ); return false; }
		$wpdb->query( 'COMMIT' );
		return true;
	}

	/** Soft-delete a note while preserving its final snapshot. */
	public static function delete( $note_id, $user_id, $actor_id ) {
		global $wpdb;
		$note = self::get_editable( $note_id, $user_id );
		if ( ! $note ) { return false; }
		$now = current_time( 'mysql', true );
		$wpdb->query( 'START TRANSACTION' );
		$ok = $wpdb->update( $wpdb->prefix . 'pf_participant_notes', array( 'deleted_by' => absint( $actor_id ), 'deleted_at' => $now, 'updated_by' => absint( $actor_id ), 'updated_at' => $now ), array( 'id' => absint( $note_id ), 'participant_user_id' => absint( $user_id ) ), array( '%d', '%s', '%d', '%s' ), array( '%d', '%d' ) );
		if ( false === $ok || ! self::record_event( $note_id, $user_id, 'deleted', $note->note_body, $actor_id ) ) { $wpdb->query( 'ROLLBACK' ); return false; }
		$wpdb->query( 'COMMIT' );
		return true;
	}

	private static function get_editable( $note_id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pf_participant_notes';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d AND participant_user_id=%d AND deleted_at IS NULL", absint( $note_id ), absint( $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function record_event( $note_id, $user_id, $type, $body, $actor_id ) {
		global $wpdb;
		return (bool) $wpdb->insert( $wpdb->prefix . 'pf_participant_note_events', array( 'note_id' => absint( $note_id ), 'participant_user_id' => absint( $user_id ), 'event_type' => sanitize_key( $type ), 'note_body' => $body, 'actor_user_id' => absint( $actor_id ), 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%d', '%s', '%s', '%d', '%s' ) );
	}
}
