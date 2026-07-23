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

		// Future version-specific schema changes will run here.

		update_option(
			self::DATABASE_VERSION_OPTION,
			PARISH_FORMATION_DB_VERSION,
			false
		);
	}
}
