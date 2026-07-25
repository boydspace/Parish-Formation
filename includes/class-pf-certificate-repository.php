<?php
/** Certificate issuance and persistence. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Manages immutable completion credentials. */
final class Parish_Formation_Certificate_Repository {
	/** Retrieve one certificate by its internal ID. */
	public static function get_by_id( $certificate_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_certificates WHERE id = %d LIMIT 1", absint( $certificate_id ) ) );
	}
	/** Retrieve one certificate by its public UUID. */
	public static function get_by_uuid( $certificate_uuid ) {
		global $wpdb;
		if ( ! preg_match( '/^[a-f0-9-]{36}$/i', (string) $certificate_uuid ) ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_certificates WHERE certificate_uuid = %s LIMIT 1", strtolower( $certificate_uuid ) ) );
	}

	/** Retrieve one certificate by its public verification code. */
	public static function get_by_verification_code( $verification_code ) {
		global $wpdb;
		$verification_code = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $verification_code ) );
		if ( 20 !== strlen( $verification_code ) ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pf_certificates WHERE verification_code = %s LIMIT 1", $verification_code ) );
	}

	/** Retrieve the newest certificate for one enrollment run. */
	public static function get_for_enrollment_run( $enrollment_id, $course_run ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}pf_certificates WHERE enrollment_id = %d AND course_run = %d ORDER BY id DESC LIMIT 1",
				absint( $enrollment_id ),
				max( 1, absint( $course_run ) )
			)
		);
	}

	/** Determine whether all pass-required assessments have been passed. */
	public static function is_eligible( $enrollment ) {
		if ( ! $enrollment || 'completed' !== $enrollment->status || empty( $enrollment->completed_at ) ) {
			return false;
		}
		foreach ( Parish_Formation_Course_Repository::get_published_assessments( $enrollment->course_id ) as $assessment ) {
			$progression = get_post_meta( $assessment->ID, Parish_Formation_Assessment_Settings::PROGRESSION_META_KEY, true );
			if ( 'pass_to_continue' !== $progression ) {
				continue;
			}
			$attempt = Parish_Formation_Assessment_Repository::get_latest_attempt( $enrollment->id, $assessment->ID );
			if ( ! $attempt || ! $attempt->passed ) {
				return false;
			}
		}
		return true;
	}

	/** Issue a certificate once for an eligible enrollment run. */
	public static function maybe_issue( $enrollment, $issued_by = 0 ) {
		global $wpdb;
		if ( ! $enrollment || ! get_post_meta( $enrollment->course_id, Parish_Formation_Course_Settings::CERTIFICATE_ENABLED_META_KEY, true ) ) {
			return null;
		}
		if ( ! self::is_eligible( $enrollment ) ) {
			return new WP_Error( 'certificate_not_eligible', __( 'The participant is not eligible for a certificate.', 'parish-formation' ) );
		}
		$course_run = max( 1, absint( $enrollment->current_run ) );
		$existing   = self::get_for_enrollment_run( $enrollment->id, $course_run );
		if ( $existing ) {
			return $existing;
		}
		$user = get_userdata( $enrollment->user_id );
		if ( ! $user ) {
			return new WP_Error( 'certificate_invalid_user', __( 'The certificate participant could not be found.', 'parish-formation' ) );
		}
		$now           = current_time( 'mysql', true );
		$validity_days = min( 36500, absint( get_post_meta( $enrollment->course_id, Parish_Formation_Course_Settings::CERTIFICATE_VALIDITY_DAYS_META_KEY, true ) ) );
		$expires_at    = $validity_days ? gmdate( 'Y-m-d H:i:s', strtotime( $now . ' UTC +' . $validity_days . ' days' ) ) : null;
		$code          = self::generate_verification_code();
		if ( ! $code ) {
			return new WP_Error( 'certificate_code_error', __( 'A unique certificate verification code could not be created.', 'parish-formation' ) );
		}
		$certificate_title = sanitize_text_field( get_post_meta( $enrollment->course_id, Parish_Formation_Course_Settings::CERTIFICATE_TITLE_META_KEY, true ) );
		$issuer_name       = sanitize_text_field( get_post_meta( $enrollment->course_id, Parish_Formation_Course_Settings::CERTIFICATE_ISSUER_META_KEY, true ) );
		$saved = $wpdb->insert(
			$wpdb->prefix . 'pf_certificates',
			array(
				'certificate_uuid' => wp_generate_uuid4(),
				'verification_code' => $code,
				'enrollment_id' => absint( $enrollment->id ),
				'user_id' => absint( $enrollment->user_id ),
				'course_id' => absint( $enrollment->course_id ),
				'course_run' => $course_run,
				'issue_number' => 1,
				'status' => 'issued',
				'participant_name' => sanitize_text_field( $user->display_name ),
				'course_title' => sanitize_text_field( get_the_title( $enrollment->course_id ) ),
				'certificate_title' => $certificate_title ?: __( 'Certificate of Completion', 'parish-formation' ),
				'issuer_name' => $issuer_name ?: sanitize_text_field( get_bloginfo( 'name' ) ),
				'signatory_name' => sanitize_text_field( get_post_meta( $enrollment->course_id, Parish_Formation_Course_Settings::CERTIFICATE_SIGNATORY_NAME_META_KEY, true ) ),
				'signatory_title' => sanitize_text_field( get_post_meta( $enrollment->course_id, Parish_Formation_Course_Settings::CERTIFICATE_SIGNATORY_TITLE_META_KEY, true ) ),
				'completed_at' => $enrollment->completed_at,
				'issued_at' => $now,
				'expires_at' => $expires_at,
				'issued_by' => absint( $issued_by ),
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		if ( false === $saved ) {
			$existing = self::get_for_enrollment_run( $enrollment->id, $course_run );
			if ( $existing ) {
				return $existing;
			}
			return new WP_Error( 'certificate_database_error', __( 'The certificate record could not be issued.', 'parish-formation' ) );
		}
		$issued = self::get_for_enrollment_run( $enrollment->id, $course_run );
		Parish_Formation_Notifications::send_certificate_event( 'certificate_issued', $issued );
		return $issued;
	}

	/** Revoke an issued certificate with an audited reason. */
	public static function revoke( $certificate_id, $reason, $staff_user_id ) {
		global $wpdb;
		$certificate = self::get_by_id( $certificate_id );
		$reason      = sanitize_textarea_field( $reason );
		if ( ! $certificate || 'issued' !== $certificate->status ) {
			return new WP_Error( 'certificate_not_issued', __( 'Only an issued certificate can be revoked.', 'parish-formation' ) );
		}
		if ( '' === trim( $reason ) ) {
			return new WP_Error( 'certificate_reason_required', __( 'Enter a reason for revoking the certificate.', 'parish-formation' ) );
		}
		$updated = $wpdb->update(
			$wpdb->prefix . 'pf_certificates',
			array( 'status' => 'revoked', 'revoked_at' => current_time( 'mysql', true ), 'revoked_by' => absint( $staff_user_id ), 'revocation_reason' => $reason ),
			array( 'id' => absint( $certificate_id ), 'status' => 'issued' ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d', '%s' )
		);
		if ( ! $updated ) {
			return new WP_Error( 'certificate_database_error', __( 'The certificate could not be revoked.', 'parish-formation' ) );
		}
		Parish_Formation_Notifications::send_certificate_event( 'certificate_revoked', self::get_by_id( $certificate_id ), $reason );
		return true;
	}

	/** Reissue a revoked certificate as a new immutable record. */
	public static function reissue( $certificate_id, $staff_user_id ) {
		global $wpdb;
		$source = self::get_by_id( $certificate_id );
		if ( ! $source || 'revoked' !== $source->status ) {
			return new WP_Error( 'certificate_not_revoked', __( 'Only a revoked certificate can be reissued.', 'parish-formation' ) );
		}
		$latest = self::get_for_enrollment_run( $source->enrollment_id, $source->course_run );
		if ( ! $latest || absint( $latest->id ) !== absint( $source->id ) ) {
			return new WP_Error( 'certificate_replacement_exists', __( 'A replacement certificate has already been issued.', 'parish-formation' ) );
		}
		$code = self::generate_verification_code();
		if ( ! $code ) {
			return new WP_Error( 'certificate_code_error', __( 'A unique certificate verification code could not be created.', 'parish-formation' ) );
		}
		$now = current_time( 'mysql', true );
		$saved = $wpdb->insert(
			$wpdb->prefix . 'pf_certificates',
			array(
				'certificate_uuid' => wp_generate_uuid4(), 'verification_code' => $code, 'enrollment_id' => $source->enrollment_id,
				'user_id' => $source->user_id, 'course_id' => $source->course_id, 'course_run' => $source->course_run,
				'issue_number' => absint( $latest->issue_number ) + 1, 'status' => 'issued', 'participant_name' => $source->participant_name,
				'course_title' => $source->course_title, 'certificate_title' => $source->certificate_title, 'issuer_name' => $source->issuer_name,
				'signatory_name' => $source->signatory_name, 'signatory_title' => $source->signatory_title, 'completed_at' => $source->completed_at,
				'issued_at' => $now, 'expires_at' => $source->expires_at, 'issued_by' => absint( $staff_user_id ), 'reissue_of' => $source->id,
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);
		if ( ! $saved ) {
			return new WP_Error( 'certificate_database_error', __( 'The replacement certificate could not be issued.', 'parish-formation' ) );
		}
		$replacement = self::get_by_id( $wpdb->insert_id );
		Parish_Formation_Notifications::send_certificate_event( 'certificate_reissued', $replacement );
		return $replacement;
	}

	/** Generate a collision-resistant human-readable verification code. */
	private static function generate_verification_code() {
		global $wpdb;
		for ( $attempt = 0; $attempt < 5; ++$attempt ) {
			$code = strtoupper( wp_generate_password( 20, false, false ) );
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}pf_certificates WHERE verification_code = %s", $code ) );
			if ( ! $exists ) {
				return $code;
			}
		}
		return '';
	}
}
