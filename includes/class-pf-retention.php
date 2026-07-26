<?php
/** Scheduled privacy-conscious retention cleanup. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Stores retention policy and removes eligible operational records. */
final class Parish_Formation_Retention {
	public const SETTINGS_OPTION = 'parish_formation_retention_settings';
	public const CRON_HOOK = 'pf_daily_retention_cleanup';

	public static function settings() {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		return array(
			'sent_email_days' => self::days( $saved['sent_email_days'] ?? 365 ),
			'failed_email_days' => self::days( $saved['failed_email_days'] ?? 90 ),
			'inactive_invitation_days' => self::days( $saved['inactive_invitation_days'] ?? 365 ),
		);
	}

	public static function save_settings( $values ) {
		update_option( self::SETTINGS_OPTION, array(
			'sent_email_days' => self::days( $values['sent_email_days'] ?? 365 ),
			'failed_email_days' => self::days( $values['failed_email_days'] ?? 90 ),
			'inactive_invitation_days' => self::days( $values['inactive_invitation_days'] ?? 365 ),
		), false );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK ); }
	}

	public static function unschedule() { wp_clear_scheduled_hook( self::CRON_HOOK ); }

	/** Run cleanup and return deletion counts for diagnostics. */
	public static function cleanup() {
		global $wpdb;
		$settings = self::settings();
		$counts = array( 'sent_emails' => 0, 'failed_emails' => 0, 'invitations' => 0 );
		if ( $settings['sent_email_days'] ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $settings['sent_email_days'] );
			$counts['sent_emails'] = absint( $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}pf_notification_log WHERE status='sent' AND created_at < %s", $cutoff ) ) );
		}
		if ( $settings['failed_email_days'] ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $settings['failed_email_days'] );
			$counts['failed_emails'] = absint( $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}pf_notification_log WHERE status='failed' AND created_at < %s", $cutoff ) ) );
		}
		if ( $settings['inactive_invitation_days'] ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $settings['inactive_invitation_days'] );
			$counts['invitations'] = absint( $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}pf_invitations WHERE (status='revoked' AND revoked_at IS NOT NULL AND revoked_at < %s) OR (expires_at IS NOT NULL AND expires_at < %s) OR (max_uses > 0 AND use_count >= max_uses AND updated_at < %s)", $cutoff, $cutoff, $cutoff ) ) );
		}
		update_option( 'parish_formation_last_retention_cleanup', array( 'ran_at' => current_time( 'mysql', true ), 'counts' => $counts ), false );
		return $counts;
	}

	private static function days( $value ) { return min( 36500, max( 0, absint( $value ) ) ); }
}
