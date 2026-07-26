<?php
/** WordPress personal-data export and erasure integration. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Exports participant formation records and anonymizes them on approved erasure. */
final class Parish_Formation_Privacy {
	/** Register this plugin with WordPress privacy tools. */
	public static function register_exporter( $exporters ) {
		$exporters['parish-formation'] = array(
			'exporter_friendly_name' => __( 'Parish Formation', 'parish-formation' ),
			'callback' => array( self::class, 'export_personal_data' ),
		);
		return $exporters;
	}

	/** Register the privacy eraser. */
	public static function register_eraser( $erasers ) {
		$erasers['parish-formation'] = array(
			'eraser_friendly_name' => __( 'Parish Formation', 'parish-formation' ),
			'callback' => array( self::class, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/** Export all participant-facing and staff-held personal formation data. */
	public static function export_personal_data( $email_address, $page = 1 ) {
		if ( 1 < absint( $page ) ) { return array( 'data' => array(), 'done' => true ); }
		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user ) { return array( 'data' => array(), 'done' => true ); }
		global $wpdb;
		$user_id = absint( $user->ID );
		$data = array();
		self::add_item( $data, 'profile-' . $user_id, __( 'Formation participant profile', 'parish-formation' ), array(
			__( 'Cell phone', 'parish-formation' ) => get_user_meta( $user_id, Parish_Formation_Account_Service::PHONE_META_KEY, true ),
			__( 'Account source', 'parish-formation' ) => get_user_meta( $user_id, Parish_Formation_Account_Service::SOURCE_META_KEY, true ),
			__( 'Last formation login', 'parish-formation' ) => get_user_meta( $user_id, Parish_Formation_Account_Service::LAST_LOGIN_META_KEY, true ),
		) );

		$enrollments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_enrollments WHERE user_id=%d ORDER BY id", $user_id ) );
		foreach ( $enrollments as $row ) {
			self::add_item( $data, 'enrollment-' . $row->id, __( 'Formation enrollment', 'parish-formation' ), array(
				__( 'Course', 'parish-formation' ) => get_the_title( $row->course_id ), __( 'Status', 'parish-formation' ) => $row->status,
				__( 'Course run', 'parish-formation' ) => $row->current_run, __( 'Enrollment source', 'parish-formation' ) => $row->enrollment_source,
				__( 'Enrolled', 'parish-formation' ) => $row->enrolled_at, __( 'Started', 'parish-formation' ) => $row->started_at,
				__( 'Completed', 'parish-formation' ) => $row->completed_at, __( 'Expires', 'parish-formation' ) => $row->expires_at,
			) );
		}

		$progress = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_progress WHERE user_id=%d ORDER BY id", $user_id ) );
		foreach ( $progress as $row ) {
			self::add_item( $data, 'progress-' . $row->id, __( 'Formation lesson progress', 'parish-formation' ), array(
				__( 'Course', 'parish-formation' ) => get_the_title( $row->course_id ), __( 'Lesson', 'parish-formation' ) => get_the_title( $row->lesson_id ),
				__( 'Status', 'parish-formation' ) => $row->status, __( 'Started', 'parish-formation' ) => $row->started_at, __( 'Completed', 'parish-formation' ) => $row->completed_at,
			) );
		}

		$attempts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_assessment_attempts WHERE user_id=%d ORDER BY id", $user_id ) );
		foreach ( $attempts as $attempt ) {
			self::add_item( $data, 'assessment-' . $attempt->id, __( 'Formation assessment attempt', 'parish-formation' ), array(
				__( 'Course', 'parish-formation' ) => get_the_title( $attempt->course_id ), __( 'Assessment', 'parish-formation' ) => get_the_title( $attempt->assessment_id ),
				__( 'Attempt', 'parish-formation' ) => $attempt->attempt_number, __( 'Status', 'parish-formation' ) => $attempt->status,
				__( 'Score', 'parish-formation' ) => $attempt->score_points . ' / ' . $attempt->max_points, __( 'Review note', 'parish-formation' ) => $attempt->review_note,
				__( 'Submitted', 'parish-formation' ) => $attempt->submitted_at,
			) );
			$answers = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_assessment_answers WHERE attempt_id=%d ORDER BY id", $attempt->id ) );
			foreach ( $answers as $answer ) {
				self::add_item( $data, 'answer-' . $answer->id, __( 'Formation assessment response', 'parish-formation' ), array(
					__( 'Assessment', 'parish-formation' ) => get_the_title( $attempt->assessment_id ), __( 'Question record', 'parish-formation' ) => $answer->question_snapshot,
					__( 'Response', 'parish-formation' ) => $answer->answer, __( 'Points awarded', 'parish-formation' ) => $answer->points_awarded,
				) );
			}
		}

		$certificates = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_certificates WHERE user_id=%d ORDER BY id", $user_id ) );
		foreach ( $certificates as $row ) {
			self::add_item( $data, 'certificate-' . $row->id, __( 'Formation certificate', 'parish-formation' ), array(
				__( 'Participant name', 'parish-formation' ) => $row->participant_name, __( 'Course', 'parish-formation' ) => $row->course_title,
				__( 'Status', 'parish-formation' ) => $row->status, __( 'Verification code', 'parish-formation' ) => $row->verification_code,
				__( 'Completed', 'parish-formation' ) => $row->completed_at, __( 'Issued', 'parish-formation' ) => $row->issued_at, __( 'Expires', 'parish-formation' ) => $row->expires_at,
			) );
		}

		$notes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_participant_notes WHERE participant_user_id=%d ORDER BY id", $user_id ) );
		foreach ( $notes as $row ) {
			self::add_item( $data, 'staff-note-' . $row->id, __( 'Private formation staff note', 'parish-formation' ), array( __( 'Note', 'parish-formation' ) => $row->note_body, __( 'Created', 'parish-formation' ) => $row->created_at, __( 'Updated', 'parish-formation' ) => $row->updated_at ) );
		}
		$note_events = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_participant_note_events WHERE participant_user_id=%d ORDER BY id", $user_id ) );
		foreach ( $note_events as $row ) {
			self::add_item( $data, 'staff-note-event-' . $row->id, __( 'Private formation staff-note history', 'parish-formation' ), array( __( 'Event', 'parish-formation' ) => $row->event_type, __( 'Note snapshot', 'parish-formation' ) => $row->note_body, __( 'Recorded', 'parish-formation' ) => $row->created_at ) );
		}
		$notifications = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_notification_log WHERE participant_user_id=%d OR recipient=%s ORDER BY id", $user_id, $user->user_email ) );
		foreach ( $notifications as $row ) {
			self::add_item( $data, 'notification-' . $row->id, __( 'Formation email activity', 'parish-formation' ), array( __( 'Recipient', 'parish-formation' ) => $row->recipient, __( 'Message type', 'parish-formation' ) => $row->notification_type, __( 'Subject', 'parish-formation' ) => $row->subject, __( 'Status', 'parish-formation' ) => $row->status, __( 'Created', 'parish-formation' ) => $row->created_at, __( 'Sent', 'parish-formation' ) => $row->sent_at ) );
		}
		$invitations = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_invitations WHERE restricted_email=%s ORDER BY id", $user->user_email ) );
		foreach ( $invitations as $row ) {
			self::add_item( $data, 'invitation-' . $row->id, __( 'Formation course invitation', 'parish-formation' ), array( __( 'Course', 'parish-formation' ) => get_the_title( $row->course_id ), __( 'Email restriction', 'parish-formation' ) => $row->restricted_email, __( 'Status', 'parish-formation' ) => $row->status, __( 'Created', 'parish-formation' ) => $row->created_at, __( 'Expires', 'parish-formation' ) => $row->expires_at ) );
		}
		return array( 'data' => $data, 'done' => true );
	}

	/** Anonymize content while retaining non-identifying operational history. */
	public static function erase_personal_data( $email_address, $page = 1 ) {
		if ( 1 < absint( $page ) ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		global $wpdb;
		$user_id = absint( $user->ID );
		$pseudonym = self::pseudonymous_user_id( $user_id );
		$now = current_time( 'mysql', true );
		$wpdb->query( 'START TRANSACTION' );
		$attempt_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}pf_assessment_attempts WHERE user_id=%d", $user_id ) );
		if ( $attempt_ids ) {
			$ids = implode( ',', array_map( 'absint', $attempt_ids ) );
			$wpdb->query( "UPDATE {$wpdb->prefix}pf_assessment_answers SET answer='', question_snapshot='[Question retained; personal response erased]' WHERE attempt_id IN ({$ids})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$wpdb->update( $wpdb->prefix . 'pf_assessment_attempts', array( 'user_id' => $pseudonym, 'review_note' => null ), array( 'user_id' => $user_id ), array( '%d', '%s' ), array( '%d' ) );
		$wpdb->update( $wpdb->prefix . 'pf_progress', array( 'user_id' => $pseudonym ), array( 'user_id' => $user_id ), array( '%d' ), array( '%d' ) );
		$wpdb->update( $wpdb->prefix . 'pf_enrollments', array( 'user_id' => $pseudonym ), array( 'user_id' => $user_id ), array( '%d' ), array( '%d' ) );
		$wpdb->update( $wpdb->prefix . 'pf_certificates', array( 'user_id' => $pseudonym, 'participant_name' => __( 'Former participant', 'parish-formation' ), 'status' => 'revoked', 'revoked_at' => $now, 'revocation_reason' => __( 'Participant personal data was erased.', 'parish-formation' ) ), array( 'user_id' => $user_id ), array( '%d', '%s', '%s', '%s', '%s' ), array( '%d' ) );
		$wpdb->update( $wpdb->prefix . 'pf_participant_notes', array( 'participant_user_id' => $pseudonym, 'note_body' => '' ), array( 'participant_user_id' => $user_id ), array( '%d', '%s' ), array( '%d' ) );
		$wpdb->update( $wpdb->prefix . 'pf_participant_note_events', array( 'participant_user_id' => $pseudonym, 'note_body' => '' ), array( 'participant_user_id' => $user_id ), array( '%d', '%s' ), array( '%d' ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}pf_notification_log SET recipient=%s, subject=%s, message_body=NULL, error_message=NULL, participant_user_id=%d WHERE participant_user_id=%d OR recipient=%s", 'erased-' . $pseudonym . '@invalid.local', __( 'Personal data erased', 'parish-formation' ), $pseudonym, $user_id, $user->user_email ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}pf_invitations SET restricted_email=NULL WHERE restricted_email=%s", $user->user_email ) );
		$wpdb->query( 'COMMIT' );
		delete_user_meta( $user_id, Parish_Formation_Account_Service::PHONE_META_KEY );
		delete_user_meta( $user_id, Parish_Formation_Account_Service::SOURCE_META_KEY );
		delete_user_meta( $user_id, Parish_Formation_Account_Service::LAST_LOGIN_META_KEY );
		return array(
			'items_removed' => true,
			'items_retained' => true,
			'messages' => array( __( 'Personal responses, staff-note content, notification details, certificate identity, and formation profile metadata were erased. Anonymous enrollment, progress, scoring, and audit records were retained for operational reporting.', 'parish-formation' ) ),
			'done' => true,
		);
	}

	private static function add_item( &$data, $item_id, $record_type, $values ) {
		$item_data = array( array( 'name' => __( 'Record type', 'parish-formation' ), 'value' => $record_type ) );
		foreach ( $values as $name => $value ) { if ( null !== $value && '' !== (string) $value ) { $item_data[] = array( 'name' => $name, 'value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ); } }
		$data[] = array( 'group_id' => 'parish-formation', 'group_label' => __( 'Parish Formation', 'parish-formation' ), 'item_id' => sanitize_key( $item_id ), 'data' => $item_data );
	}

	/** Produce a stable non-WordPress identifier that cannot be reversed without site salts. */
	private static function pseudonymous_user_id( $user_id ) {
		$hex = substr( hash_hmac( 'sha256', 'privacy-user-' . absint( $user_id ), wp_salt( 'auth' ) ), 0, 12 );
		return 1000000000000 + hexdec( $hex );
	}
}
