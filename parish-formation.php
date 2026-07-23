<?php
/**
 * Plugin Name:       Parish Formation
 * Description:       Provides focused online formation tools for parishes.
 * Version:           0.1.0
 * Requires PHP:      8.3
 * Author:            Father Andrew M. Boyd
 * Author URI:        https://fatherboyd.com
 * Plugin URI:        https://fatherboyd.com/plugins
 * License:           GPL-2.0-or-later
 * Text Domain:       parish-formation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PARISH_FORMATION_VERSION', '0.1.0' );
define( 'PARISH_FORMATION_DB_VERSION', '0.1.0' );
define( 'PARISH_FORMATION_PLUGIN_FILE', __FILE__ );
define( 'PARISH_FORMATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-upgrader.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-capabilities.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-admin.php';

register_activation_hook(
	PARISH_FORMATION_PLUGIN_FILE,
	array( 'Parish_Formation_Upgrader', 'maybe_upgrade' )
);

register_activation_hook(
	PARISH_FORMATION_PLUGIN_FILE,
	array( 'Parish_Formation_Capabilities', 'maybe_install' )
);

add_action(
	'plugins_loaded',
	array( 'Parish_Formation_Upgrader', 'maybe_upgrade' )
);

add_action(
	'plugins_loaded',
	array( 'Parish_Formation_Capabilities', 'maybe_install' )
);

add_action(
	'admin_menu',
	array( 'Parish_Formation_Admin', 'register_menu' )
);
