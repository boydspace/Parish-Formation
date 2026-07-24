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

		if ( ! self::enrollments_table_exists() || ! self::progress_table_exists() || ! self::assessment_tables_exist() ) {
			return;
		}

		update_option(
			self::DATABASE_VERSION_OPTION,
			PARISH_FORMATION_DB_VERSION,
			false
		);
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
			UNIQUE KEY enrollment_assessment_attempt (enrollment_id, assessment_id, attempt_number),
			KEY user_assessment (user_id, assessment_id),
			KEY enrollment_status (enrollment_id, status)
		) {$charset_collate};" );
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
			enrolled_at datetime NOT NULL,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			completion_override_by bigint(20) unsigned DEFAULT NULL,
			completion_override_at datetime DEFAULT NULL,
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
}
