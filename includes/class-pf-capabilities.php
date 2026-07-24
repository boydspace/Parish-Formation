<?php
/**
 * Defines Parish Formation roles and capabilities.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installs and updates plugin roles and capabilities.
 */
final class Parish_Formation_Capabilities {

	/**
	 * Current role and capability configuration version.
	 */
	private const CONFIGURATION_VERSION = '0.6.0';

	/**
	 * Option containing the installed capability configuration version.
	 */
	private const VERSION_OPTION = 'parish_formation_capabilities_version';

	/**
	 * Install roles and capabilities when their configuration changes.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		$installed_version = get_option( self::VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installed_version, self::CONFIGURATION_VERSION, '>=' ) ) {
			return;
		}

		self::install();
		update_option( self::VERSION_OPTION, self::CONFIGURATION_VERSION, false );
	}

	/**
	 * Add the plugin roles and reconcile their capabilities.
	 *
	 * @return void
	 */
	private static function install() {
		$participant_capabilities = array(
			'read'                => true,
			'pf_access_formation' => true,
		);

		$coordinator_capabilities = array_merge(
			$participant_capabilities,
			array(
				'pf_manage_courses'     => true,
				'pf_manage_enrollments' => true,
				'pf_manage_assessments' => true,
				'pf_grade_assessments'  => true,
				'pf_view_reports'       => true,
			)
		);

		$administrator_capabilities = array_merge(
			$coordinator_capabilities,
			array(
				'pf_manage_settings'             => true,
				'pf_manage_roles'                => true,
				'pf_override_assessment_attempts' => true,
			)
		);

		self::add_or_update_role(
			'parish_formation_participant',
			__( 'Formation Participant', 'parish-formation' ),
			$participant_capabilities
		);

		self::add_or_update_role(
			'parish_formation_coordinator',
			__( 'Formation Coordinator', 'parish-formation' ),
			$coordinator_capabilities
		);

		self::add_or_update_role(
			'parish_formation_administrator',
			__( 'Formation Administrator', 'parish-formation' ),
			$administrator_capabilities
		);

		self::add_capabilities_to_role( 'administrator', $administrator_capabilities );
	}

	/**
	 * Create a role when needed and ensure it has the configured capabilities.
	 *
	 * @param string $role_slug    Role identifier.
	 * @param string $display_name Translated role name.
	 * @param array  $capabilities Role capabilities.
	 * @return void
	 */
	private static function add_or_update_role( $role_slug, $display_name, $capabilities ) {
		if ( ! get_role( $role_slug ) ) {
			add_role( $role_slug, $display_name, $capabilities );
		}

		self::add_capabilities_to_role( $role_slug, $capabilities );
	}

	/**
	 * Add capabilities to an existing role.
	 *
	 * @param string $role_slug    Role identifier.
	 * @param array  $capabilities Capabilities to add.
	 * @return void
	 */
	private static function add_capabilities_to_role( $role_slug, $capabilities ) {
		$role = get_role( $role_slug );

		if ( ! $role ) {
			return;
		}

		foreach ( array_keys( $capabilities ) as $capability ) {
			$role->add_cap( $capability );
		}
	}
}
