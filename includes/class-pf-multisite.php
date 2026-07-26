<?php
/**
 * Handles single-site and multisite plugin lifecycle events.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps installation state site-scoped when the plugin is network activated. */
final class Parish_Formation_Multisite {

	/** Activate the plugin for one site or every existing network site. */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			self::for_each_site( array( self::class, 'install_current_site' ) );
			return;
		}
		self::install_current_site();
	}

	/** Remove scheduled events for one site or every network site. */
	public static function deactivate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			self::for_each_site( array( self::class, 'deactivate_current_site' ) );
			return;
		}
		self::deactivate_current_site();
	}

	/**
	 * Initialize plugin storage for a site created after network activation.
	 *
	 * @param WP_Site $site Newly initialized site.
	 */
	public static function initialize_new_site( $site ) {
		if ( ! is_multisite() || ! $site instanceof WP_Site ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		try {
			self::install_current_site();
		} finally {
			restore_current_blog();
		}
	}

	/** Install database, permissions, routes, and scheduled jobs for this site. */
	public static function install_current_site() {
		Parish_Formation_Upgrader::maybe_upgrade();
		Parish_Formation_Capabilities::maybe_install();
		Parish_Formation_Course_Post_Type::register();
		Parish_Formation_Lesson_Post_Type::register();
		Parish_Formation_Assessment_Post_Type::register();
		Parish_Formation_Question_Post_Type::register();
		Parish_Formation_Certificate_Design_Post_Type::register();
		Parish_Formation_Certificate_Verification::register_routes();
		Parish_Formation_Notifications::schedule_events();
		Parish_Formation_Retention::schedule();
		flush_rewrite_rules( false );
	}

	/** Clear site-specific scheduled events and rewrite rules. */
	public static function deactivate_current_site() {
		Parish_Formation_Notifications::clear_scheduled_events();
		Parish_Formation_Retention::unschedule();
		flush_rewrite_rules( false );
	}

	/** Run a callback in the context of every site in the current network. */
	private static function for_each_site( $callback ) {
		$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			try {
				call_user_func( $callback );
			} finally {
				restore_current_blog();
			}
		}
	}
}
