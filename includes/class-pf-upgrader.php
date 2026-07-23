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

		if ( ! self::enrollments_table_exists() ) {
			return;
		}

		update_option(
			self::DATABASE_VERSION_OPTION,
			PARISH_FORMATION_DB_VERSION,
			false
		);
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
}
