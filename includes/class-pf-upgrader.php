<?php
/**
 * Handles plugin database upgrades.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs versioned, repeatable plugin upgrades.
 */
final class Parish_Formation_Upgrader {

	/**
	 * Option containing the installed database version.
	 */
	private const DATABASE_VERSION_OPTION = 'parish_formation_db_version';

	/**
	 * Upgrade the database when its stored version is behind the code version.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( self::DATABASE_VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installed_version, PARISH_FORMATION_DB_VERSION, '>=' ) ) {
			return;
		}

		if ( version_compare( $installed_version, '0.2.0', '<' ) ) {
			self::install_enrollments_table();
		}

		if ( version_compare( $installed_version, '0.3.0', '<' ) ) {
			self::install_progress_table();
		}

		if ( version_compare( $installed_version, '0.4.0', '<' ) ) {
			self::install_enrollments_table();
		}

		if ( version_compare( $installed_version, '0.6.0', '<' ) ) {
			self::install_assessment_tables();
		}

		if ( version_compare( $installed_version, '0.7.0', '<' ) ) {
			self::install_enrollments_table();
			self::install_assessment_tables();
			self::install_enrollment_runs_table();
		}

		if ( version_compare( $installed_version, '0.8.0', '<' ) ) {
			self::install_certificates_table();
		}
		if ( version_compare( $installed_version, '0.8.1', '<' ) ) {
			self::install_certificates_table();
		}
		if ( version_compare( $installed_version, '0.9.0', '<' ) ) {
			self::install_notification_log_table();
		}
		if ( version_compare( $installed_version, '0.9.1', '<' ) ) {
			self::install_notification_log_table();
		}
		if ( version_compare( $installed_version, '0.9.2', '<' ) ) {
			self::discard_successful_notification_bodies();
		}
		if ( version_compare( $installed_version, '0.9.3', '<' ) ) {
			self::install_invitations_table();
		}
		if ( version_compare( $installed_version, '0.9.4', '<' ) ) {
			self::install_invitations_table();
		}
		if ( version_compare( $installed_version, '1.0.0', '<' ) ) {
			self::install_participant_notes_tables();
		}
		if ( version_compare( $installed_version, '1.0.1', '<' ) ) {
			self::install_notification_log_table();
		}
		if ( version_compare( $installed_version, '1.0.2', '<' ) ) {
			self::install_certificates_table();
		}

		if ( ! self::enrollments_table_exists() || ! self::progress_table_exists() || ! self::assessment_tables_exist() || ! self::enrollment_runs_table_exists() || ! self::certificates_table_exists() || ! self::notification_log_table_exists() || ! self::invitations_table_exists() || ! self::participant_notes_tables_exist() ) {
			return;
		}

		update_option(
			self::DATABASE_VERSION_OPTION,
			PARISH_FORMATION_DB_VERSION,
			false
		);
	}

	/** Create private participant notes and their immutable audit events. */
	private static function install_participant_notes_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$notes  = $wpdb->prefix . 'pf_participant_notes';
		$events = $wpdb->prefix . 'pf_participant_note_events';
		dbDelta( "CREATE TABLE {$notes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			participant_user_id bigint(20) unsigned NOT NULL,
			note_body longtext NOT NULL,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			updated_by bigint(20) unsigned NOT NULL,
			updated_at datetime NOT NULL,
			deleted_by bigint(20) unsigned DEFAULT NULL,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY participant_active (participant_user_id, deleted_at),
			KEY updated_at (updated_at)
		) {$charset_collate};" );
		dbDelta( "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			note_id bigint(20) unsigned NOT NULL,
			participant_user_id bigint(20) unsigned NOT NULL,
			event_type varchar(20) NOT NULL,
			note_body longtext NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY participant_created (participant_user_id, created_at),
			KEY note_created (note_id, created_at)
		) {$charset_collate};" );
	}

	/** Create or update secure course invitation records. */
	private static function install_invitations_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'pf_invitations';
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL,
			token_hash char(64) NOT NULL,
			token_encrypted longtext DEFAULT NULL,
			token_hint varchar(12) NOT NULL,
			restricted_email varchar(255) DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			max_uses int unsigned NOT NULL DEFAULT 0,
			use_count int unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			revoked_by bigint(20) unsigned DEFAULT NULL,
			revoked_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY course_status (course_id, status),
			KEY expires_at (expires_at)
		) {$charset_collate};" );
	}

	/** Create or update the notification delivery audit table. */
	private static function install_notification_log_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'pf_notification_log';
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			notification_type varchar(80) NOT NULL,
			recipient varchar(255) NOT NULL,
			subject varchar(255) NOT NULL,
			message_body longtext DEFAULT NULL,
			status varchar(20) NOT NULL,
			context_key varchar(64) NOT NULL,
			error_message longtext DEFAULT NULL,
			sent_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			initiated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			participant_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY notification_recipient_context (notification_type, recipient(120), context_key),
			KEY status_created (status, created_at),
			KEY participant_type_created (participant_user_id, notification_type, created_at)
		) {$charset_collate};" );
	}

	/** Successful messages do not need full content retained for retry. */
	private static function discard_successful_notification_bodies() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'pf_notification_log';
		if ( $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) ) {
			$wpdb->query( "UPDATE {$table_name} SET message_body = NULL WHERE status = 'sent'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/** Create or update immutable assessment attempt and answer tables. */
	private static function install_assessment_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$attempts = $wpdb->prefix . 'pf_assessment_attempts';
		$answers  = $wpdb->prefix . 'pf_assessment_answers';
		dbDelta( "CREATE TABLE {$attempts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			enrollment_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			assessment_id bigint(20) unsigned NOT NULL,
			course_run int unsigned NOT NULL DEFAULT 1,
			attempt_number int unsigned NOT NULL,
			status varchar(20) NOT NULL,
			score_points decimal(10,2) NOT NULL DEFAULT 0,
			max_points decimal(10,2) NOT NULL DEFAULT 0,
			correct_count int unsigned NOT NULL DEFAULT 0,
			total_graded int unsigned NOT NULL DEFAULT 0,
			passing_rule varchar(20) NOT NULL,
			passing_value decimal(10,2) NOT NULL DEFAULT 0,
			passed tinyint(1) DEFAULT NULL,
			reviewed_by bigint(20) unsigned DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			review_note longtext DEFAULT NULL,
			submitted_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY enrollment_assessment_run_attempt (enrollment_id, assessment_id, course_run, attempt_number),
			KEY user_assessment (user_id, assessment_id),
			KEY enrollment_status (enrollment_id, status)
		) {$charset_collate};" );
		$legacy_index = $wpdb->get_var( "SHOW INDEX FROM {$attempts} WHERE Key_name = 'enrollment_assessment_attempt'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $legacy_index ) {
			$wpdb->query( "ALTER TABLE {$attempts} DROP INDEX enrollment_assessment_attempt" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		dbDelta( "CREATE TABLE {$answers} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attempt_id bigint(20) unsigned NOT NULL,
			question_id bigint(20) unsigned NOT NULL,
			question_snapshot longtext NOT NULL,
			answer longtext NOT NULL,
			points_awarded decimal(10,2) NOT NULL DEFAULT 0,
			is_correct tinyint(1) DEFAULT NULL,
			requires_review tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY attempt_question (attempt_id, question_id),
			KEY question_id (question_id)
		) {$charset_collate};" );
	}

	/**
	 * Create or update the enrollments table.
	 *
	 * @return void
	 */
	private static function install_enrollments_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'pf_enrollments';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'enrolled',
			current_run int unsigned NOT NULL DEFAULT 1,
			enrolled_at datetime NOT NULL,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			completion_override_by bigint(20) unsigned DEFAULT NULL,
			completion_override_at datetime DEFAULT NULL,
			last_reset_by bigint(20) unsigned DEFAULT NULL,
			last_reset_at datetime DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			enrollment_source varchar(30) NOT NULL DEFAULT 'manual',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_course (user_id, course_id),
			KEY course_status (course_id, status),
			KEY user_status (user_id, status),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/** Create the immutable enrollment-run archive table. */
	private static function install_enrollment_runs_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'pf_enrollment_runs';
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			enrollment_id bigint(20) unsigned NOT NULL,
			run_number int unsigned NOT NULL,
			status varchar(20) NOT NULL,
			enrolled_at datetime NOT NULL,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			reset_by bigint(20) unsigned NOT NULL DEFAULT 0,
			reset_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY enrollment_run (enrollment_id, run_number),
			KEY reset_at (reset_at)
		) {$charset_collate};" );
	}

	/** Create the immutable completion-certificate record table. */
	private static function install_certificates_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'pf_certificates';
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			certificate_uuid char(36) NOT NULL,
			verification_code varchar(64) NOT NULL,
			enrollment_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			course_run int unsigned NOT NULL,
			issue_number int unsigned NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'issued',
			participant_name varchar(255) NOT NULL,
			course_title varchar(255) NOT NULL,
			certificate_title varchar(255) NOT NULL,
			issuer_name varchar(255) NOT NULL,
			signatory_name varchar(255) DEFAULT NULL,
			signatory_title varchar(255) DEFAULT NULL,
			design_snapshot longtext DEFAULT NULL,
			completed_at datetime NOT NULL,
			issued_at datetime NOT NULL,
			expires_at datetime DEFAULT NULL,
			issued_by bigint(20) unsigned NOT NULL DEFAULT 0,
			revoked_at datetime DEFAULT NULL,
			revoked_by bigint(20) unsigned DEFAULT NULL,
			revocation_reason longtext DEFAULT NULL,
			reissue_of bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY certificate_uuid (certificate_uuid),
			UNIQUE KEY verification_code (verification_code),
			UNIQUE KEY enrollment_run_issue (enrollment_id, course_run, issue_number),
			KEY enrollment_run (enrollment_id, course_run),
			KEY user_course (user_id, course_id),
			KEY status_expires (status, expires_at)
		) {$charset_collate};" );
	}

	/**
	 * Create or update the lesson progress table.
	 *
	 * @return void
	 */
	private static function install_progress_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'pf_progress';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			enrollment_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			lesson_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'started',
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY enrollment_lesson (enrollment_id, lesson_id),
			KEY user_course (user_id, course_id),
			KEY course_lesson (course_id, lesson_id),
			KEY enrollment_status (enrollment_id, status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Determine whether the enrollments table exists.
	 *
	 * @return bool
	 */
	private static function enrollments_table_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pf_enrollments';
		$table_like = $wpdb->esc_like( $table_name );

		$found_table = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_like )
		);

		return $table_name === $found_table;
	}

	/**
	 * Determine whether the lesson progress table exists.
	 *
	 * @return bool
	 */
	private static function progress_table_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pf_progress';
		$table_like = $wpdb->esc_like( $table_name );

		$found_table = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_like )
		);

		return $table_name === $found_table;
	}

	/** Determine whether both assessment tables exist. */
	private static function assessment_tables_exist() {
		global $wpdb;
		foreach ( array( $wpdb->prefix . 'pf_assessment_attempts', $wpdb->prefix . 'pf_assessment_answers' ) as $table_name ) {
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) );
			if ( $table_name !== $found ) {
				return false;
			}
		}
		return true;
	}

	/** Determine whether the enrollment-run archive table exists. */
	private static function enrollment_runs_table_exists() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'pf_enrollment_runs';
		return $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) );
	}

	/** Determine whether the certificate table exists. */
	private static function certificates_table_exists() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'pf_certificates';
		return $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) );
	}

	/** Determine whether the notification log table exists. */
	private static function notification_log_table_exists() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'pf_notification_log';
		return $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) );
	}

	/** Determine whether the invitations table exists. */
	private static function invitations_table_exists() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'pf_invitations';
		return $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) );
	}

	/** Determine whether both participant-note tables exist. */
	private static function participant_notes_tables_exist() {
		global $wpdb;
		foreach ( array( $wpdb->prefix . 'pf_participant_notes', $wpdb->prefix . 'pf_participant_note_events' ) as $table_name ) {
			if ( $table_name !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) ) {
				return false;
			}
		}
		return true;
	}
}
