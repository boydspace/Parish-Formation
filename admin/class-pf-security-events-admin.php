<?php
/** Administrative security event viewer. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Parish_Formation_Security_Events_Admin {
	/** Register the hidden legacy-compatible page used by the Settings hub. */
	public static function register_menu() {
		add_submenu_page( null, __( 'Security Events', 'parish-formation' ), __( 'Security Events', 'parish-formation' ), 'pf_manage_settings', 'parish-formation-security-events', array( self::class, 'render_page' ) );
	}

	/** Render the latest append-only events and chain integrity result. */
	public static function render_page() {
		if ( ! current_user_can( 'pf_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to view security events.', 'parish-formation' ) );
		}
		$events    = Parish_Formation_Security_Event_Repository::get_recent( 200 );
		$integrity = Parish_Formation_Security_Event_Repository::verify_chain();
		$user_ids  = array();
		$course_ids = array();
		foreach ( $events as $event ) {
			if ( $event->actor_user_id ) { $user_ids[] = absint( $event->actor_user_id ); }
			if ( $event->participant_user_id ) { $user_ids[] = absint( $event->participant_user_id ); }
			if ( $event->course_id ) { $course_ids[] = absint( $event->course_id ); }
		}
		if ( $user_ids ) { cache_users( array_values( array_unique( $user_ids ) ) ); }
		if ( $course_ids ) { _prime_post_caches( array_values( array_unique( $course_ids ) ), false, false ); }
		?>
		<div class="wrap pf-security-events">
			<h2><?php esc_html_e( 'Security Events', 'parish-formation' ); ?></h2>
			<p><?php esc_html_e( 'This append-only ledger records sensitive enrollment, review, and certificate actions. Event hashes are chained so later alteration can be detected.', 'parish-formation' ); ?></p>
			<?php if ( is_wp_error( $integrity ) ) : ?>
				<div class="notice notice-error inline"><p><strong><?php esc_html_e( 'Integrity check failed:', 'parish-formation' ); ?></strong> <?php echo esc_html( $integrity->get_error_message() ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'The security event chain passed integrity verification.', 'parish-formation' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! $events ) : ?>
				<p><?php esc_html_e( 'No security events have been recorded yet.', 'parish-formation' ); ?></p>
			<?php else : ?>
				<div class="table-responsive"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Date', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Event', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Actor', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Participant', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Course', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Object', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Details', 'parish-formation' ); ?></th></tr></thead><tbody>
				<?php foreach ( $events as $event ) : $actor = get_userdata( $event->actor_user_id ); $participant = get_userdata( $event->participant_user_id ); ?>
					<?php /* translators: Placeholder values are replaced with the contextual count, name, date, status, or label described by the message. */ ?>
					<tr><td><?php echo esc_html( get_date_from_gmt( $event->created_at, 'M j, Y g:i a' ) ); ?></td><td><code><?php echo esc_html( $event->event_type ); ?></code></td><td><?php echo esc_html( $actor ? $actor->display_name : ( $event->actor_user_id ? sprintf( __( 'User #%d', 'parish-formation' ), $event->actor_user_id ) : __( 'System', 'parish-formation' ) ) ); ?></td><td><?php echo esc_html( $participant ? $participant->display_name : ( $event->participant_user_id ? sprintf( __( 'User #%d', 'parish-formation' ), $event->participant_user_id ) : '—' ) ); ?></td><td><?php echo esc_html( $event->course_id ? get_the_title( $event->course_id ) ?: sprintf( __( 'Course #%d', 'parish-formation' ), $event->course_id ) : '—' ); ?></td><td><?php echo esc_html( $event->object_type . ' #' . $event->object_id ); ?></td><td><code><?php echo esc_html( $event->context_json ); ?></code></td></tr>
				<?php endforeach; ?>
				</tbody></table></div>
			<?php endif; ?>
		</div>
		<?php
	}
}
