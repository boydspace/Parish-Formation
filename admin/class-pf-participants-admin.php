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
		?>
		<div class="wrap"><h1><?php echo esc_html( $user->display_name ?: $user->user_email ); ?></h1><?php self::notice(); ?><p><a href="<?php echo esc_url( add_query_arg( 'page', 'parish-formation-participants', admin_url( 'admin.php' ) ) ); ?>">&larr; <?php esc_html_e( 'All Participants', 'parish-formation' ); ?></a></p>
		<div class="card" style="max-width:900px"><h2><?php esc_html_e( 'Participant Profile', 'parish-formation' ); ?></h2><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_update_participant"><input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>"><?php wp_nonce_field( 'pf_update_participant_' . $user_id ); ?><table class="form-table"><tbody><tr><th><label for="pf-first-name"><?php esc_html_e( 'First name', 'parish-formation' ); ?></label></th><td><input id="pf-first-name" name="first_name" class="regular-text" value="<?php echo esc_attr( $user->first_name ); ?>"></td></tr><tr><th><label for="pf-last-name"><?php esc_html_e( 'Last name', 'parish-formation' ); ?></label></th><td><input id="pf-last-name" name="last_name" class="regular-text" value="<?php echo esc_attr( $user->last_name ); ?>"></td></tr><tr><th><label for="pf-email"><?php esc_html_e( 'Email', 'parish-formation' ); ?></label></th><td><input id="pf-email" name="user_email" type="email" class="regular-text" required value="<?php echo esc_attr( $user->user_email ); ?>"></td></tr><tr><th><label for="pf-phone"><?php esc_html_e( 'Cell phone', 'parish-formation' ); ?></label></th><td><input id="pf-phone" name="cell_phone" type="tel" class="regular-text" value="<?php echo esc_attr( get_user_meta( $user_id, Parish_Formation_Account_Service::PHONE_META_KEY, true ) ); ?>"></td></tr></tbody></table><?php submit_button( __( 'Update Profile', 'parish-formation' ) ); ?></form>
		<table class="widefat striped"><tbody><tr><th><?php esc_html_e( 'Username', 'parish-formation' ); ?></th><td><code><?php echo esc_html( $user->user_login ); ?></code></td></tr><tr><th><?php esc_html_e( 'Account source', 'parish-formation' ); ?></th><td><?php echo esc_html( self::source_label( get_user_meta( $user_id, Parish_Formation_Account_Service::SOURCE_META_KEY, true ) ) ); ?></td></tr><tr><th><?php esc_html_e( 'Registered', 'parish-formation' ); ?></th><td><?php echo esc_html( self::format_date( $user->user_registered ) ); ?></td></tr><tr><th><?php esc_html_e( 'Last login', 'parish-formation' ); ?></th><td><?php echo $last_login ? esc_html( self::format_date( $last_login ) ) : '—'; ?></td></tr></tbody></table>
		<h3><?php esc_html_e( 'Account Security', 'parish-formation' ); ?></h3><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_send_participant_password_reset"><input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>"><?php wp_nonce_field( 'pf_send_participant_password_reset_' . $user_id ); ?><button class="button" type="submit"><?php esc_html_e( 'Email Password Reset Link', 'parish-formation' ); ?></button></form></div>
		<h2><?php esc_html_e( 'Active Enrollments', 'parish-formation' ); ?></h2><?php if ( ! $enrollments ) : ?><p><?php esc_html_e( 'This participant has no active course enrollments.', 'parish-formation' ); ?></p><?php else : ?><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Course', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Status', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Enrolled', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Completed', 'parish-formation' ); ?></th></tr></thead><tbody><?php foreach ( $enrollments as $enrollment ) : ?><tr><td><?php echo esc_html( $enrollment->course_title ); ?></td><td><?php echo esc_html( ucwords( str_replace( '_', ' ', $enrollment->status ) ) ); ?></td><td><?php echo esc_html( self::format_date( $enrollment->enrolled_at ) ); ?></td><td><?php echo $enrollment->completed_at ? esc_html( self::format_date( $enrollment->completed_at ) ) : '—'; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
		<?php
	}

	public static function handle_update() {
		self::require_access(); $id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0; check_admin_referer( 'pf_update_participant_' . $id );
		$email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : ''; $existing = email_exists( $email );
		if ( ! is_email( $email ) || ( $existing && absint( $existing ) !== $id ) ) { self::redirect( $id, 'invalid_email' ); }
		$first        = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last         = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$display_name = trim( $first . ' ' . $last );
		$result       = wp_update_user( array( 'ID' => $id, 'first_name' => $first, 'last_name' => $last, 'display_name' => $display_name ?: $email, 'user_email' => $email ) );
		if ( is_wp_error( $result ) ) { self::redirect( $id, 'update_failed' ); }
		update_user_meta( $id, Parish_Formation_Account_Service::PHONE_META_KEY, isset( $_POST['cell_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['cell_phone'] ) ) : '' ); self::redirect( $id, 'updated' );
	}

	public static function handle_password_reset() {
		self::require_access(); $id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0; check_admin_referer( 'pf_send_participant_password_reset_' . $id ); $user = get_userdata( $id );
		if ( ! $user ) { self::redirect( $id, 'reset_failed' ); }
		$result = retrieve_password( $user->user_login ); self::redirect( $id, is_wp_error( $result ) ? 'reset_failed' : 'reset_sent' );
	}

	private static function require_access() { if ( ! current_user_can( 'pf_manage_enrollments' ) ) { wp_die( esc_html__( 'You do not have permission to manage participants.', 'parish-formation' ) ); } }
	private static function redirect( $id, $notice ) { wp_safe_redirect( add_query_arg( array( 'page' => 'parish-formation-participants', 'user_id' => absint( $id ), 'pf_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) ); exit; }
	private static function notice() { $code = isset( $_GET['pf_notice'] ) ? sanitize_key( wp_unslash( $_GET['pf_notice'] ) ) : ''; $messages = array( 'updated' => __( 'Participant profile updated.', 'parish-formation' ), 'reset_sent' => __( 'Password reset email sent.', 'parish-formation' ), 'invalid_email' => __( 'Enter a valid email address that is not already in use.', 'parish-formation' ), 'update_failed' => __( 'The participant profile could not be updated.', 'parish-formation' ), 'reset_failed' => __( 'The password reset email could not be sent.', 'parish-formation' ) ); if ( isset( $messages[ $code ] ) ) { echo '<div class="notice ' . esc_attr( in_array( $code, array( 'updated', 'reset_sent' ), true ) ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $messages[ $code ] ) . '</p></div>'; } }
	private static function source_label( $source ) { $labels = array( 'public_registration' => __( 'Public registration', 'parish-formation' ), 'invitation' => __( 'Invitation', 'parish-formation' ), 'administrator' => __( 'Administrator', 'parish-formation' ) ); return isset( $labels[ $source ] ) ? $labels[ $source ] : __( 'Existing WordPress account', 'parish-formation' ); }
	private static function format_date( $utc ) { return get_date_from_gmt( $utc, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ); }
}
