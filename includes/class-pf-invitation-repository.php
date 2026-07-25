<?php
/** Secure course invitation persistence. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Invitation records never retain their usable raw tokens. */
final class Parish_Formation_Invitation_Repository {

	/** Create an invitation and return its record plus its one-time raw token. */
	public static function create( $course_id, $restricted_email, $expires_at, $max_uses, $created_by ) {
		global $wpdb;
		if ( Parish_Formation_Course_Post_Type::POST_TYPE !== get_post_type( $course_id ) ) {
			return new WP_Error( 'invalid_course', __( 'Select a valid course.', 'parish-formation' ) );
		}
		if ( $restricted_email && ! is_email( $restricted_email ) ) {
			return new WP_Error( 'invalid_email', __( 'Enter a valid restricted email address.', 'parish-formation' ) );
		}

		$token = self::generate_token();
		$now   = current_time( 'mysql', true );
		$saved = $wpdb->insert(
			$wpdb->prefix . 'pf_invitations',
			array(
				'course_id'       => absint( $course_id ),
				'token_hash'      => hash( 'sha256', $token ),
				'token_encrypted' => self::encrypt_token( $token ),
				'token_hint'      => substr( $token, -8 ),
				'restricted_email' => $restricted_email ? strtolower( sanitize_email( $restricted_email ) ) : null,
				'expires_at'      => $expires_at,
				'max_uses'        => min( 1000000, absint( $max_uses ) ),
				'use_count'       => 0,
				'status'          => 'active',
				'created_by'      => absint( $created_by ),
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s' )
		);
		if ( false === $saved ) {
			return new WP_Error( 'database_error', __( 'The invitation could not be created.', 'parish-formation' ) );
		}

		return array( 'invitation' => self::get( $wpdb->insert_id ), 'token' => $token );
	}

	/** Return a resendable token, rotating legacy hash-only invitations. */
	public static function token_for_resend( $invitation_id ) {
		global $wpdb;
		$invitation = self::get( $invitation_id );
		if ( ! $invitation || 'active' !== $invitation->status || ! $invitation->restricted_email || ( $invitation->expires_at && strtotime( $invitation->expires_at . ' UTC' ) < time() ) || ( $invitation->max_uses && $invitation->use_count >= $invitation->max_uses ) ) {
			return new WP_Error( 'invitation_not_resendable', __( 'This invitation cannot be resent.', 'parish-formation' ) );
		}
		$token = self::decrypt_token( $invitation->token_encrypted );
		if ( $token ) {
			return $token;
		}
		$token = self::generate_token();
		$updated = $wpdb->update(
			$wpdb->prefix . 'pf_invitations',
			array( 'token_hash' => hash( 'sha256', $token ), 'token_encrypted' => self::encrypt_token( $token ), 'token_hint' => substr( $token, -8 ), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => absint( $invitation_id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		return false === $updated ? new WP_Error( 'database_error', __( 'The invitation link could not be regenerated.', 'parish-formation' ) ) : $token;
	}

	/** Retrieve one invitation with its course and creator labels. */
	public static function get( $invitation_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT invitation.*, course.post_title AS course_title, user.display_name AS creator_name
				FROM {$wpdb->prefix}pf_invitations invitation
				INNER JOIN {$wpdb->posts} course ON course.ID = invitation.course_id
				LEFT JOIN {$wpdb->users} user ON user.ID = invitation.created_by
				WHERE invitation.id = %d LIMIT 1",
				absint( $invitation_id )
			)
		);
	}

	/** Retrieve recent invitations for staff. */
	public static function get_recent( $limit = 200 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT invitation.*, course.post_title AS course_title, user.display_name AS creator_name
				FROM {$wpdb->prefix}pf_invitations invitation
				INNER JOIN {$wpdb->posts} course ON course.ID = invitation.course_id
				LEFT JOIN {$wpdb->users} user ON user.ID = invitation.created_by
				ORDER BY invitation.created_at DESC, invitation.id DESC LIMIT %d",
				absint( $limit )
			)
		);
	}

	/** Retrieve an invitation using its raw URL token. */
	public static function get_by_token( $token ) {
		global $wpdb;
		if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9_-]{40,80}$/', $token ) ) {
			return null;
		}
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}pf_invitations WHERE token_hash = %s LIMIT 1", hash( 'sha256', $token ) ) );
		return $id ? self::get( $id ) : null;
	}

	/** Validate an invitation for a user without consuming it. */
	public static function validate_for_user( $invitation, $user ) {
		if ( ! $invitation || 'active' !== $invitation->status ) {
			return new WP_Error( 'invitation-unavailable', __( 'This invitation is no longer available.', 'parish-formation' ) );
		}
		if ( $invitation->expires_at && strtotime( $invitation->expires_at . ' UTC' ) < time() ) {
			return new WP_Error( 'invitation-expired', __( 'This invitation has expired.', 'parish-formation' ) );
		}
		if ( $invitation->max_uses && $invitation->use_count >= $invitation->max_uses ) {
			return new WP_Error( 'invitation-used', __( 'This invitation has already reached its usage limit.', 'parish-formation' ) );
		}
		if ( $invitation->restricted_email && ( ! $user || strtolower( $user->user_email ) !== strtolower( $invitation->restricted_email ) ) ) {
			return new WP_Error( 'invitation-email-mismatch', __( 'This invitation is restricted to a different email address.', 'parish-formation' ) );
		}
		return true;
	}

	/** Consume an invitation and enroll the user. */
	public static function accept( $token, $user_id ) {
		global $wpdb;
		$invitation = self::get_by_token( $token );
		$user       = get_userdata( $user_id );
		$valid      = self::validate_for_user( $invitation, $user );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$result = Parish_Formation_Enrollment_Repository::create_invitation_enrollment( $user_id, $invitation->course_id, $invitation->created_by );
		if ( is_wp_error( $result ) ) {
			return 'duplicate_enrollment' === $result->get_error_code() ? new WP_Error( 'already-enrolled', __( 'You are already enrolled in this course.', 'parish-formation' ) ) : $result;
		}
		$now = current_time( 'mysql', true );
		$wpdb->update( $wpdb->prefix . 'pf_invitations', array( 'use_count' => absint( $invitation->use_count ) + 1, 'updated_at' => $now ), array( 'id' => absint( $invitation->id ) ), array( '%d', '%s' ), array( '%d' ) );
		if ( ! $user->has_cap( 'pf_access_formation' ) ) {
			$user->add_cap( 'pf_access_formation' );
		}
		Parish_Formation_Notifications::send_participant_event( 'enrollment_confirmation', $result, array(), 'invitation_enrolled' );
		return $result;
	}

	/** Revoke an active invitation. */
	public static function revoke( $invitation_id, $revoked_by ) {
		global $wpdb;
		$invitation = self::get( $invitation_id );
		if ( ! $invitation ) {
			return new WP_Error( 'invalid_invitation', __( 'The invitation could not be found.', 'parish-formation' ) );
		}
		if ( 'revoked' === $invitation->status ) {
			return true;
		}
		$now = current_time( 'mysql', true );
		$updated = $wpdb->update( $wpdb->prefix . 'pf_invitations', array( 'status' => 'revoked', 'revoked_by' => absint( $revoked_by ), 'revoked_at' => $now, 'updated_at' => $now ), array( 'id' => absint( $invitation_id ) ), array( '%s', '%d', '%s', '%s' ), array( '%d' ) );
		return false === $updated ? new WP_Error( 'database_error', __( 'The invitation could not be revoked.', 'parish-formation' ) ) : true;
	}

	/** Generate a URL-safe token with 256 bits of entropy. */
	private static function generate_token() {
		return rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
	}

	/** Encrypt the raw invitation token for authorized resend operations. */
	private static function encrypt_token( $token ) {
		$key = hash( 'sha256', wp_salt( 'secure_auth' ), true );
		$iv  = random_bytes( 12 );
		$tag = '';
		$ciphertext = openssl_encrypt( $token, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return false === $ciphertext ? null : base64_encode( $iv . $tag . $ciphertext );
	}

	/** Decrypt an invitation token retained for authorized resends. */
	private static function decrypt_token( $stored ) {
		$payload = $stored ? base64_decode( $stored, true ) : false;
		if ( false === $payload || strlen( $payload ) < 29 ) {
			return '';
		}
		$plain = openssl_decrypt( substr( $payload, 28 ), 'aes-256-gcm', hash( 'sha256', wp_salt( 'secure_auth' ), true ), OPENSSL_RAW_DATA, substr( $payload, 0, 12 ), substr( $payload, 12, 16 ) );
		return false === $plain ? '' : $plain;
	}
}
