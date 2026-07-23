<?php
/**
 * Parish Formation uninstall handler.
 *
 * Participant data, plugin options, roles, and capabilities are intentionally
 * preserved. A future release may add an explicit administrator-controlled data
 * deletion setting, but uninstalling the plugin must not silently remove parish
 * formation records.
 *
 * @package ParishFormation
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Intentionally preserve all Parish Formation data.
