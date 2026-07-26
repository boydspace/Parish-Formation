<?php
/** Formation participant administration. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Search and manage formation participant profiles. */
final class Parish_Formation_Participants_Admin {
	public static function register_menu() {
		add_submenu_page( null, __( 'Participants', 'parish-formation' ), __( 'Participants', 'parish-formation' ), 'pf_manage_enrollments', 'parish-formation-participants', array( self::class, 'render_page' ) );
	}

	public static function render_page() {
		self::require_access();
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		if ( $user_id ) { self::render_detail( $user_id ); return; }
		self::render_list();
	}

	private static function render_list() {
		$search = isset( $_GET['pf_search'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['pf_search'] ) ) ) : '';
		$users  = get_users( array( 'orderby' => 'registered', 'order' => 'DESC', 'number' => 500 ) );
		$users  = array_filter( $users, static function ( $user ) use ( $search ) {
			$is_formation = $user->has_cap( 'pf_access_formation' ) || get_user_meta( $user->ID, Parish_Formation_Account_Service::SOURCE_META_KEY, true );
			$haystack = strtolower( implode( ' ', array( $user->display_name, $user->first_name, $user->last_name, $user->user_email, $user->user_login, get_user_meta( $user->ID, Parish_Formation_Account_Service::PHONE_META_KEY, true ) ) ) );
			return $is_formation && ( ! $search || false !== strpos( $haystack, $search ) );
		} );
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Participants', 'parish-formation' ); ?></h1><?php self::notice(); ?><p><?php esc_html_e( 'Search registered formation participants and open a profile for contact, enrollment, and account actions.', 'parish-formation' ); ?></p>
		<form method="get"><input type="hidden" name="page" value="parish-formation-participants"><input type="search" name="pf_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, email, username, or phone', 'parish-formation' ); ?>"> <?php submit_button( __( 'Search', 'parish-formation' ), 'secondary', '', false ); ?> <a class="button" href="<?php echo esc_url( add_query_arg( 'page', 'parish-formation-participants', admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Clear', 'parish-formation' ); ?></a></form>
		<?php if ( ! $users ) : ?><p><?php esc_html_e( 'No participants match this search.', 'parish-formation' ); ?></p><?php else : ?><table class="widefat striped" style="margin-top:16px"><thead><tr><th><?php esc_html_e( 'Participant', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Email', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Cell phone', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Account source', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Registered', 'parish-formation' ); ?></th></tr></thead><tbody><?php foreach ( $users as $user ) : $url = add_query_arg( array( 'page' => 'parish-formation-participants', 'user_id' => $user->ID ), admin_url( 'admin.php' ) ); ?><tr><td><a href="<?php echo esc_url( $url ); ?>"><strong><?php echo esc_html( $user->display_name ?: $user->user_email ); ?></strong></a><br><code><?php echo esc_html( $user->user_login ); ?></code></td><td><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td><td><?php echo esc_html( get_user_meta( $user->ID, Parish_Formation_Account_Service::PHONE_META_KEY, true ) ?: '—' ); ?></td><td><?php echo esc_html( self::source_label( get_user_meta( $user->ID, Parish_Formation_Account_Service::SOURCE_META_KEY, true ) ) ); ?></td><td><?php echo esc_html( self::format_date( $user->user_registered ) ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
		<?php
	}

	private static function render_detail( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user || ( ! $user->has_cap( 'pf_access_formation' ) && ! get_user_meta( $user_id, Parish_Formation_Account_Service::SOURCE_META_KEY, true ) ) ) { wp_die( esc_html__( 'Participant not found.', 'parish-formation' ) ); }
		$enrollments = Parish_Formation_Enrollment_Repository::get_for_user( $user_id );
		$last_login  = get_user_meta( $user_id, Parish_Formation_Account_Service::LAST_LOGIN_META_KEY, true );
		$notes       = Parish_Formation_Participant_Note_Repository::get_active( $user_id );
		$history     = Parish_Formation_Participant_Note_Repository::get_history( $user_id );
		$reminders   = self::get_reminder_history( $user_id );
		?>
		<div class="wrap"><h1><?php echo esc_html( $user->display_name ?: $user->user_email ); ?></h1><?php self::notice(); ?><p><a href="<?php echo esc_url( add_query_arg( 'page', 'parish-formation-participants', admin_url( 'admin.php' ) ) ); ?>">&larr; <?php esc_html_e( 'All Participants', 'parish-formation' ); ?></a></p>
		<div class="card" style="max-width:900px"><h2><?php esc_html_e( 'Participant Profile', 'parish-formation' ); ?></h2><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_update_participant"><input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>"><?php wp_nonce_field( 'pf_update_participant_' . $user_id ); ?><table class="form-table"><tbody><tr><th><label for="pf-first-name"><?php esc_html_e( 'First name', 'parish-formation' ); ?></label></th><td><input id="pf-first-name" name="first_name" class="regular-text" value="<?php echo esc_attr( $user->first_name ); ?>"></td></tr><tr><th><label for="pf-last-name"><?php esc_html_e( 'Last name', 'parish-formation' ); ?></label></th><td><input id="pf-last-name" name="last_name" class="regular-text" value="<?php echo esc_attr( $user->last_name ); ?>"></td></tr><tr><th><label for="pf-email"><?php esc_html_e( 'Email', 'parish-formation' ); ?></label></th><td><input id="pf-email" name="user_email" type="email" class="regular-text" required value="<?php echo esc_attr( $user->user_email ); ?>"></td></tr><tr><th><label for="pf-phone"><?php esc_html_e( 'Cell phone', 'parish-formation' ); ?></label></th><td><input id="pf-phone" name="cell_phone" type="tel" class="regular-text" value="<?php echo esc_attr( get_user_meta( $user_id, Parish_Formation_Account_Service::PHONE_META_KEY, true ) ); ?>"></td></tr></tbody></table><?php submit_button( __( 'Update Profile', 'parish-formation' ) ); ?></form>
		<table class="widefat striped"><tbody><tr><th><?php esc_html_e( 'Username', 'parish-formation' ); ?></th><td><code><?php echo esc_html( $user->user_login ); ?></code></td></tr><tr><th><?php esc_html_e( 'Account source', 'parish-formation' ); ?></th><td><?php echo esc_html( self::source_label( get_user_meta( $user_id, Parish_Formation_Account_Service::SOURCE_META_KEY, true ) ) ); ?></td></tr><tr><th><?php esc_html_e( 'Registered', 'parish-formation' ); ?></th><td><?php echo esc_html( self::format_date( $user->user_registered ) ); ?></td></tr><tr><th><?php esc_html_e( 'Last login', 'parish-formation' ); ?></th><td><?php echo $last_login ? esc_html( self::format_date( $last_login ) ) : '—'; ?></td></tr></tbody></table>
		<h3><?php esc_html_e( 'Account Security', 'parish-formation' ); ?></h3><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_send_participant_password_reset"><input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>"><?php wp_nonce_field( 'pf_send_participant_password_reset_' . $user_id ); ?><button class="button" type="submit"><?php esc_html_e( 'Email Password Reset Link', 'parish-formation' ); ?></button></form></div>
		<?php self::render_notes( $user_id, $notes, $history ); ?>
		<?php self::render_reminders( $user_id, $enrollments, $reminders ); ?>
		<h2><?php esc_html_e( 'Active Enrollments', 'parish-formation' ); ?></h2><?php if ( ! $enrollments ) : ?><p><?php esc_html_e( 'This participant has no active course enrollments.', 'parish-formation' ); ?></p><?php else : ?><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Course', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Status', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Enrolled', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Completed', 'parish-formation' ); ?></th></tr></thead><tbody><?php foreach ( $enrollments as $enrollment ) : ?><tr><td><?php echo esc_html( $enrollment->course_title ); ?></td><td><?php echo esc_html( ucwords( str_replace( '_', ' ', $enrollment->status ) ) ); ?></td><td><?php echo esc_html( self::format_date( $enrollment->enrolled_at ) ); ?></td><td><?php echo $enrollment->completed_at ? esc_html( self::format_date( $enrollment->completed_at ) ) : '—'; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
		<?php
	}

	/** Render the manual reminder composer and its delivery audit history. */
	private static function render_reminders( $user_id, $enrollments, $reminders ) {
		?>
		<div class="card" style="max-width:900px"><h2><?php esc_html_e( 'Participant Reminders', 'parish-formation' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Send a course reminder using the editable Manual participant reminder email template.', 'parish-formation' ); ?></p>
		<?php if ( ! $enrollments ) : ?><p><?php esc_html_e( 'An active enrollment is required before a course reminder can be sent.', 'parish-formation' ); ?></p><?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_send_participant_reminder"><input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>"><?php wp_nonce_field( 'pf_send_participant_reminder_' . $user_id ); ?>
		<p><label for="pf-reminder-enrollment"><strong><?php esc_html_e( 'Course', 'parish-formation' ); ?></strong></label><br><select id="pf-reminder-enrollment" name="enrollment_id" required><option value=""><?php esc_html_e( 'Select an active course', 'parish-formation' ); ?></option><?php foreach ( $enrollments as $enrollment ) : ?><option value="<?php echo esc_attr( $enrollment->id ); ?>"><?php echo esc_html( $enrollment->course_title ); ?></option><?php endforeach; ?></select></p>
		<p><label for="pf-reminder-message"><strong><?php esc_html_e( 'Personal message', 'parish-formation' ); ?></strong></label><br><textarea id="pf-reminder-message" name="reminder_message" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Optional message inserted into the reminder template', 'parish-formation' ); ?>"></textarea></p>
		<?php submit_button( __( 'Send Reminder', 'parish-formation' ), 'primary', '', false ); ?></form><?php endif; ?>
		<?php if ( $reminders ) : ?><h3><?php esc_html_e( 'Reminder History', 'parish-formation' ); ?></h3><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Date', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Subject', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Sent by', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Status', 'parish-formation' ); ?></th></tr></thead><tbody><?php foreach ( $reminders as $reminder ) : ?><tr><td><?php echo esc_html( self::format_date( $reminder->created_at ) ); ?></td><td><?php echo esc_html( $reminder->subject ); ?></td><td><?php echo esc_html( $reminder->actor_name ?: __( 'Unknown staff member', 'parish-formation' ) ); ?></td><td><?php echo esc_html( 'sent' === $reminder->status ? __( 'Accepted by mailer', 'parish-formation' ) : __( 'Failed', 'parish-formation' ) ); ?><?php if ( $reminder->error_message ) : ?><br><small><?php echo esc_html( $reminder->error_message ); ?></small><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
		<?php
	}

	private static function render_notes( $user_id, $notes, $history ) {
		?>
		<div class="card" style="max-width:900px"><h2><?php esc_html_e( 'Private Staff Notes', 'parish-formation' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Visible only to authorized formation staff. Do not record counseling, medical, tribunal, or other highly sensitive pastoral information here.', 'parish-formation' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_add_participant_note"><input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>"><?php wp_nonce_field( 'pf_add_participant_note_' . $user_id ); ?><p><label for="pf-new-note"><strong><?php esc_html_e( 'Add note', 'parish-formation' ); ?></strong></label></p><textarea id="pf-new-note" name="note_body" rows="4" class="large-text" required></textarea><?php submit_button( __( 'Add Private Note', 'parish-formation' ), 'primary', '', false ); ?></form><hr>
		<?php if ( ! $notes ) : ?><p><?php esc_html_e( 'No active staff notes have been added.', 'parish-formation' ); ?></p><?php endif; ?>
		<?php foreach ( $notes as $note ) : ?><div style="padding:14px 0;border-bottom:1px solid #dcdcde"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_update_participant_note"><input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>"><input type="hidden" name="note_id" value="<?php echo esc_attr( $note->id ); ?>"><?php wp_nonce_field( 'pf_update_participant_note_' . $note->id ); ?><textarea name="note_body" rows="4" class="large-text" required><?php echo esc_textarea( $note->note_body ); ?></textarea><p class="description"><?php echo esc_html( sprintf( __( 'Added by %1$s on %2$s. Last updated by %3$s on %4$s.', 'parish-formation' ), $note->created_by_name ?: __( 'Unknown staff member', 'parish-formation' ), self::format_date( $note->created_at ), $note->updated_by_name ?: __( 'Unknown staff member', 'parish-formation' ), self::format_date( $note->updated_at ) ) ); ?></p><?php submit_button( __( 'Save Note', 'parish-formation' ), 'secondary', '', false ); ?></form><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:8px" onsubmit="return window.confirm('<?php echo esc_js( __( 'Remove this note from the active list? Its audit history will be retained.', 'parish-formation' ) ); ?>');"><input type="hidden" name="action" value="pf_delete_participant_note"><input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>"><input type="hidden" name="note_id" value="<?php echo esc_attr( $note->id ); ?>"><?php wp_nonce_field( 'pf_delete_participant_note_' . $note->id ); ?><button class="button-link-delete" type="submit"><?php esc_html_e( 'Remove Note', 'parish-formation' ); ?></button></form></div><?php endforeach; ?>
		<?php if ( $history ) : ?><details style="margin-top:18px"><summary><strong><?php esc_html_e( 'View Note Audit History', 'parish-formation' ); ?></strong></summary><table class="widefat striped" style="margin-top:12px"><thead><tr><th><?php esc_html_e( 'Action', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Note snapshot', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Staff member', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Date', 'parish-formation' ); ?></th></tr></thead><tbody><?php foreach ( $history as $event ) : ?><tr><td><?php echo esc_html( ucfirst( $event->event_type ) ); ?></td><td><?php echo wp_kses_post( nl2br( esc_html( $event->note_body ) ) ); ?></td><td><?php echo esc_html( $event->actor_name ?: __( 'Unknown staff member', 'parish-formation' ) ); ?></td><td><?php echo esc_html( self::format_date( $event->created_at ) ); ?></td></tr><?php endforeach; ?></tbody></table></details><?php endif; ?></div>
		<?php
	}

	public static function handle_update() {
		self::require_access(); $id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0; check_admin_referer( 'pf_update_participant_' . $id );
		$email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : ''; $existing = email_exists( $email );
		if ( ! is_email( $email ) || ( $existing && absint( $existing ) !== $id ) ) { self::redirect( $id, 'invalid_email' ); }
		$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : ''; $last = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : ''; $display_name = trim( $first . ' ' . $last );
		$result = wp_update_user( array( 'ID' => $id, 'first_name' => $first, 'last_name' => $last, 'display_name' => $display_name ?: $email, 'user_email' => $email ) );
		if ( is_wp_error( $result ) ) { self::redirect( $id, 'update_failed' ); }
		update_user_meta( $id, Parish_Formation_Account_Service::PHONE_META_KEY, isset( $_POST['cell_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['cell_phone'] ) ) : '' ); self::redirect( $id, 'updated' );
	}

	public static function handle_password_reset() {
		self::require_access(); $id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0; check_admin_referer( 'pf_send_participant_password_reset_' . $id ); $user = get_userdata( $id );
		if ( ! $user ) { self::redirect( $id, 'reset_failed' ); }
		$result = retrieve_password( $user->user_login ); self::redirect( $id, is_wp_error( $result ) ? 'reset_failed' : 'reset_sent' );
	}

	public static function handle_add_note() {
		self::require_access(); $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0; check_admin_referer( 'pf_add_participant_note_' . $user_id );
		$body = isset( $_POST['note_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note_body'] ) ) : '';
		if ( ! $body || ! get_userdata( $user_id ) ) { self::redirect( $user_id, 'note_failed' ); }
		self::redirect( $user_id, Parish_Formation_Participant_Note_Repository::create( $user_id, $body, get_current_user_id() ) ? 'note_added' : 'note_failed' );
	}

	public static function handle_update_note() {
		self::require_access(); $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0; $note_id = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0; check_admin_referer( 'pf_update_participant_note_' . $note_id );
		$body = isset( $_POST['note_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note_body'] ) ) : ''; $result = $body && Parish_Formation_Participant_Note_Repository::update( $note_id, $user_id, $body, get_current_user_id() );
		self::redirect( $user_id, $result ? 'note_updated' : 'note_failed' );
	}

	public static function handle_delete_note() {
		self::require_access(); $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0; $note_id = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0; check_admin_referer( 'pf_delete_participant_note_' . $note_id );
		$result = Parish_Formation_Participant_Note_Repository::delete( $note_id, $user_id, get_current_user_id() ); self::redirect( $user_id, $result ? 'note_deleted' : 'note_failed' );
	}

	public static function handle_send_reminder() {
		self::require_access(); $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0; check_admin_referer( 'pf_send_participant_reminder_' . $user_id );
		$enrollment_id = isset( $_POST['enrollment_id'] ) ? absint( $_POST['enrollment_id'] ) : 0; $enrollment = Parish_Formation_Enrollment_Repository::get_details( $enrollment_id );
		if ( ! $enrollment || absint( $enrollment->user_id ) !== $user_id ) { self::redirect( $user_id, 'reminder_failed' ); }
		$message = isset( $_POST['reminder_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reminder_message'] ) ) : '';
		$result = Parish_Formation_Notifications::send_manual_participant_reminder( $enrollment_id, $message, get_current_user_id() ); self::redirect( $user_id, $result ? 'reminder_sent' : 'reminder_failed' );
	}

	private static function get_reminder_history( $user_id ) {
		global $wpdb;
		$logs = $wpdb->prefix . 'pf_notification_log'; $users = $wpdb->users;
		return $wpdb->get_results( $wpdb->prepare( "SELECT l.*, u.display_name actor_name FROM {$logs} l LEFT JOIN {$users} u ON u.ID=l.initiated_by WHERE l.participant_user_id=%d AND l.notification_type='manual_participant_reminder' ORDER BY l.created_at DESC, l.id DESC LIMIT 50", absint( $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function require_access() { if ( ! current_user_can( 'pf_manage_enrollments' ) ) { wp_die( esc_html__( 'You do not have permission to manage participants.', 'parish-formation' ) ); } }
	private static function redirect( $id, $notice ) { wp_safe_redirect( add_query_arg( array( 'page' => 'parish-formation-participants', 'user_id' => absint( $id ), 'pf_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) ); exit; }
	private static function notice() { $code = isset( $_GET['pf_notice'] ) ? sanitize_key( wp_unslash( $_GET['pf_notice'] ) ) : ''; $messages = array( 'updated' => __( 'Participant profile updated.', 'parish-formation' ), 'reset_sent' => __( 'Password reset email sent.', 'parish-formation' ), 'note_added' => __( 'Private note added.', 'parish-formation' ), 'note_updated' => __( 'Private note updated.', 'parish-formation' ), 'note_deleted' => __( 'Private note removed. Its audit history was retained.', 'parish-formation' ), 'note_failed' => __( 'The private note could not be saved.', 'parish-formation' ), 'reminder_sent' => __( 'WordPress accepted the participant reminder for delivery.', 'parish-formation' ), 'reminder_failed' => __( 'The participant reminder could not be sent. Check the email activity log and notification settings.', 'parish-formation' ), 'invalid_email' => __( 'Enter a valid email address that is not already in use.', 'parish-formation' ), 'update_failed' => __( 'The participant profile could not be updated.', 'parish-formation' ), 'reset_failed' => __( 'The password reset email could not be sent.', 'parish-formation' ) ); if ( isset( $messages[ $code ] ) ) { echo '<div class="notice ' . esc_attr( in_array( $code, array( 'updated', 'reset_sent', 'note_added', 'note_updated', 'note_deleted', 'reminder_sent' ), true ) ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $messages[ $code ] ) . '</p></div>'; } }
	private static function source_label( $source ) { $labels = array( 'public_registration' => __( 'Public registration', 'parish-formation' ), 'invitation' => __( 'Invitation', 'parish-formation' ), 'administrator' => __( 'Administrator', 'parish-formation' ) ); return isset( $labels[ $source ] ) ? $labels[ $source ] : __( 'Existing WordPress account', 'parish-formation' ); }
	private static function format_date( $utc ) { return get_date_from_gmt( $utc, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ); }
}
