<?php
/** Privacy and retention administration. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Renders and saves conservative operational-data retention settings. */
final class Parish_Formation_Retention_Settings {
	public static function register_menu() {
		add_submenu_page( null, __( 'Privacy & Retention', 'parish-formation' ), __( 'Privacy & Retention', 'parish-formation' ), 'pf_manage_settings', 'parish-formation-retention', array( self::class, 'render_page' ) );
	}

	public static function render_page() {
		self::require_access();
		$settings = Parish_Formation_Retention::settings();
		$last = get_option( 'parish_formation_last_retention_cleanup', array() );
		$next = wp_next_scheduled( Parish_Formation_Retention::CRON_HOOK );
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Privacy & Retention', 'parish-formation' ); ?></h1>
		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter input, or a request independently authorized by its one-time token; no nonce-protected form mutation occurs here. ?>
		<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success"><p><?php esc_html_e( 'Retention settings saved.', 'parish-formation' ); ?></p></div><?php endif; ?>
		<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- This success notice only reads cleanup-result counters set after a nonce-protected action. ?>
		<?php /* translators: 1: Number of sent email records removed, 2: number of failed email records removed, 3: number of inactive invitations removed. */ ?>
		<?php if ( isset( $_GET['cleaned'] ) ) : ?><div class="notice notice-success"><p><?php echo esc_html( sprintf( __( 'Cleanup completed: %1$d sent email records, %2$d failed email records, and %3$d inactive invitations removed.', 'parish-formation' ), absint( $_GET['sent'] ?? 0 ), absint( $_GET['failed'] ?? 0 ), absint( $_GET['invitations'] ?? 0 ) ) ); ?></p></div><?php endif; ?>
		<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>
		<p><?php esc_html_e( 'These controls remove old operational logs that are no longer needed. Enter 0 to retain a category indefinitely.', 'parish-formation' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_save_retention_settings"><?php wp_nonce_field( 'pf_save_retention_settings' ); ?>
		<table class="form-table"><tbody>
		<tr><th><label for="pf-sent-email-days"><?php esc_html_e( 'Successful email activity', 'parish-formation' ); ?></label></th><td><input id="pf-sent-email-days" name="sent_email_days" type="number" min="0" max="36500" value="<?php echo esc_attr( $settings['sent_email_days'] ); ?>"> <?php esc_html_e( 'days', 'parish-formation' ); ?><p class="description"><?php esc_html_e( 'Successful message bodies are already discarded immediately. This controls how long delivery metadata is retained.', 'parish-formation' ); ?></p></td></tr>
		<tr><th><label for="pf-failed-email-days"><?php esc_html_e( 'Failed email activity', 'parish-formation' ); ?></label></th><td><input id="pf-failed-email-days" name="failed_email_days" type="number" min="0" max="36500" value="<?php echo esc_attr( $settings['failed_email_days'] ); ?>"> <?php esc_html_e( 'days', 'parish-formation' ); ?><p class="description"><?php esc_html_e( 'Failed records may contain message content for staff-initiated retries.', 'parish-formation' ); ?></p></td></tr>
		<tr><th><label for="pf-invitation-days"><?php esc_html_e( 'Inactive invitations', 'parish-formation' ); ?></label></th><td><input id="pf-invitation-days" name="inactive_invitation_days" type="number" min="0" max="36500" value="<?php echo esc_attr( $settings['inactive_invitation_days'] ); ?>"> <?php esc_html_e( 'days', 'parish-formation' ); ?><p class="description"><?php esc_html_e( 'Applies after an invitation is revoked, expired, or has used all permitted enrollments.', 'parish-formation' ); ?></p></td></tr>
		</tbody></table><?php submit_button( __( 'Save Retention Settings', 'parish-formation' ) ); ?></form>
		<hr><h2><?php esc_html_e( 'Protected records', 'parish-formation' ); ?></h2><p><?php esc_html_e( 'Enrollment history, lesson progress, assessment results, certificates, and audit events are retained indefinitely. Approved WordPress privacy erasure requests anonymize these records rather than deleting operational history.', 'parish-formation' ); ?></p>
		<?php /* translators: Placeholder values are replaced with the contextual count, name, date, status, or label described by the message. */ ?>
		<h2><?php esc_html_e( 'Cleanup status', 'parish-formation' ); ?></h2><p><?php echo $next ? esc_html( sprintf( __( 'Next scheduled cleanup: %s', 'parish-formation' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) ) ) : esc_html__( 'Cleanup has not been scheduled yet.', 'parish-formation' ); ?></p>
		<?php /* translators: Placeholder values are replaced with the contextual count, name, date, status, or label described by the message. */ ?>
		<?php if ( ! empty( $last['ran_at'] ) ) : ?><p><?php echo esc_html( sprintf( __( 'Last cleanup: %s UTC', 'parish-formation' ), $last['ran_at'] ) ); ?></p><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_run_retention_cleanup"><?php wp_nonce_field( 'pf_run_retention_cleanup' ); ?><?php submit_button( __( 'Run Cleanup Now', 'parish-formation' ), 'secondary' ); ?></form></div>
		<?php
	}

	public static function handle_save() {
		self::require_access(); check_admin_referer( 'pf_save_retention_settings' );
		Parish_Formation_Retention::save_settings( wp_unslash( $_POST ) );
		self::redirect( array( 'updated' => 1 ) );
	}

	public static function handle_cleanup() {
		self::require_access(); check_admin_referer( 'pf_run_retention_cleanup' );
		$counts = Parish_Formation_Retention::cleanup();
		self::redirect( array( 'cleaned' => 1, 'sent' => $counts['sent_emails'], 'failed' => $counts['failed_emails'], 'invitations' => $counts['invitations'] ) );
	}

	private static function redirect( $args ) { wp_safe_redirect( add_query_arg( array_merge( array( 'page' => 'parish-formation-settings', 'hub_tab' => 'privacy' ), $args ), admin_url( 'admin.php' ) ) ); exit; }
	private static function require_access() { if ( ! current_user_can( 'pf_manage_settings' ) ) { wp_die( esc_html__( 'You do not have permission to manage privacy settings.', 'parish-formation' ) ); } }
}
