<?php
/** Staff invitation-link management. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Create, list, and revoke secure course invitations. */
final class Parish_Formation_Invitations_Admin {

	/** Register the invitation submenu. */
	public static function register_menu() {
		add_submenu_page( null, __( 'Course Invitations', 'parish-formation' ), __( 'Course Invitations', 'parish-formation' ), 'pf_manage_enrollments', 'parish-formation-invitations', array( self::class, 'render_page' ) );
	}

	/** Render invitation creation and recent activity. */
	public static function render_page() {
		self::require_access();
		$courses = get_posts( array( 'post_type' => Parish_Formation_Course_Post_Type::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$rows    = Parish_Formation_Invitation_Repository::get_recent();
		$created = get_transient( 'pf_invitation_created_' . get_current_user_id() );
		if ( $created ) {
			delete_transient( 'pf_invitation_created_' . get_current_user_id() );
		}
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Course Invitations', 'parish-formation' ); ?></h1>
		<?php self::render_notice(); ?>
		<?php if ( $created && ! empty( $created['token'] ) ) : $invite_url = add_query_arg( 'pf_invitation', rawurlencode( $created['token'] ), Parish_Formation_Shortcodes::get_course_catalog_url() ); ?>
			<div class="notice notice-success"><p><strong><?php esc_html_e( 'Invitation created. Copy this link now; the usable token is not stored and cannot be shown again.', 'parish-formation' ); ?></strong></p><p><input type="text" class="large-text code" readonly value="<?php echo esc_attr( $invite_url ); ?>" onclick="this.select();"></p></div>
		<?php endif; ?>
		<h2><?php esc_html_e( 'Create Invitation', 'parish-formation' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:760px">
			<input type="hidden" name="action" value="pf_create_invitation"><?php wp_nonce_field( 'pf_create_invitation' ); ?>
			<table class="form-table"><tbody>
			<tr><th><label for="pf-invitation-course"><?php esc_html_e( 'Course', 'parish-formation' ); ?></label></th><td><select id="pf-invitation-course" name="course_id" required><option value=""><?php esc_html_e( 'Select a course', 'parish-formation' ); ?></option><?php foreach ( $courses as $course ) : ?><option value="<?php echo esc_attr( $course->ID ); ?>"><?php echo esc_html( $course->post_title . ( 'publish' === $course->post_status ? '' : ' — ' . ucfirst( $course->post_status ) ) ); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><label for="pf-invitation-email"><?php esc_html_e( 'Restricted email', 'parish-formation' ); ?></label></th><td><input id="pf-invitation-email" name="restricted_email" type="email" class="regular-text"><p class="description"><?php esc_html_e( 'Optional. Only a signed-in user with this email address may accept the invitation.', 'parish-formation' ); ?></p></td></tr>
			<tr><th><label for="pf-invitation-expires"><?php esc_html_e( 'Expires', 'parish-formation' ); ?></label></th><td><input id="pf-invitation-expires" name="expires_at" type="datetime-local"><p class="description"><?php esc_html_e( 'Optional. Uses the site timezone.', 'parish-formation' ); ?></p></td></tr>
			<tr><th><label for="pf-invitation-limit"><?php esc_html_e( 'Maximum uses', 'parish-formation' ); ?></label></th><td><input id="pf-invitation-limit" name="max_uses" type="number" min="0" max="1000000" value="0" class="small-text"><p class="description"><?php esc_html_e( 'Use 0 for unlimited.', 'parish-formation' ); ?></p></td></tr>
			</tbody></table><?php submit_button( __( 'Create Invitation Link', 'parish-formation' ) ); ?>
		</form>
		<h2><?php esc_html_e( 'Recent Invitations', 'parish-formation' ); ?></h2>
		<?php if ( ! $rows ) : ?><p><?php esc_html_e( 'No course invitations have been created yet.', 'parish-formation' ); ?></p><?php else : ?>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Course', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Email restriction', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Status', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Uses', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Expires', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Token hint', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Actions', 'parish-formation' ); ?></th></tr></thead><tbody>
		<?php foreach ( $rows as $row ) : $can_resend = 'active' === $row->status && $row->restricted_email && ( ! $row->expires_at || strtotime( $row->expires_at . ' UTC' ) >= time() ) && ( ! $row->max_uses || $row->use_count < $row->max_uses ); ?><tr><td><?php echo esc_html( $row->course_title ); ?></td><td><?php echo $row->restricted_email ? esc_html( $row->restricted_email ) : '&mdash;'; ?></td><td><?php echo esc_html( self::status_label( $row ) ); ?></td><td><?php echo esc_html( $row->use_count . ( $row->max_uses ? ' / ' . $row->max_uses : ' / ∞' ) ); ?></td><td><?php echo $row->expires_at ? esc_html( get_date_from_gmt( $row->expires_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) : '&mdash;'; ?></td><td><code>…<?php echo esc_html( $row->token_hint ); ?></code></td><td><?php if ( 'active' === $row->status ) : ?><?php if ( $can_resend ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:6px"><input type="hidden" name="action" value="pf_resend_invitation"><input type="hidden" name="invitation_id" value="<?php echo esc_attr( $row->id ); ?>"><?php wp_nonce_field( 'pf_resend_invitation_' . $row->id ); ?><button class="button button-primary" type="submit"><?php esc_html_e( 'Resend Email', 'parish-formation' ); ?></button></form><?php endif; ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block"><input type="hidden" name="action" value="pf_revoke_invitation"><input type="hidden" name="invitation_id" value="<?php echo esc_attr( $row->id ); ?>"><?php wp_nonce_field( 'pf_revoke_invitation_' . $row->id ); ?><button class="button" type="submit"><?php esc_html_e( 'Revoke', 'parish-formation' ); ?></button></form><?php else : ?>&mdash;<?php endif; ?></td></tr><?php endforeach; ?>
		</tbody></table><?php endif; ?></div>
		<?php
	}

	/** Create an invitation from validated staff input. */
	public static function handle_create() {
		self::require_access();
		check_admin_referer( 'pf_create_invitation' );
		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$email     = isset( $_POST['restricted_email'] ) ? sanitize_email( wp_unslash( $_POST['restricted_email'] ) ) : '';
		$expires   = isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '';
		$expires_at = null;
		if ( $expires ) {
			try {
				$local      = new DateTimeImmutable( $expires, wp_timezone() );
				$expires_at = $local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			} catch ( Exception $exception ) {
				self::redirect( 'invalid_expiration' );
			}
		}
		$result = Parish_Formation_Invitation_Repository::create( $course_id, $email, $expires_at, isset( $_POST['max_uses'] ) ? absint( $_POST['max_uses'] ) : 0, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			self::redirect( $result->get_error_code() );
		}
		set_transient( 'pf_invitation_created_' . get_current_user_id(), array( 'id' => $result['invitation']->id, 'token' => $result['token'] ), 10 * MINUTE_IN_SECONDS );
		Parish_Formation_Notifications::send_course_invitation( $result['invitation'], $result['token'] );
		self::redirect( 'created' );
	}

	/** Revoke one invitation. */
	public static function handle_revoke() {
		self::require_access();
		$id = isset( $_POST['invitation_id'] ) ? absint( $_POST['invitation_id'] ) : 0;
		check_admin_referer( 'pf_revoke_invitation_' . $id );
		$result = Parish_Formation_Invitation_Repository::revoke( $id, get_current_user_id() );
		self::redirect( is_wp_error( $result ) ? $result->get_error_code() : 'revoked' );
	}

	/** Resend an active email-restricted invitation. */
	public static function handle_resend() {
		self::require_access();
		$id = isset( $_POST['invitation_id'] ) ? absint( $_POST['invitation_id'] ) : 0;
		check_admin_referer( 'pf_resend_invitation_' . $id );
		$token = Parish_Formation_Invitation_Repository::token_for_resend( $id );
		if ( is_wp_error( $token ) ) {
			self::redirect( $token->get_error_code() );
		}
		$invitation = Parish_Formation_Invitation_Repository::get( $id );
		$sent = Parish_Formation_Notifications::send_course_invitation( $invitation, $token, 'resend_' . gmdate( 'YmdHis' ) );
		self::redirect( $sent ? 'resent' : 'resend_failed' );
	}

	private static function require_access() {
		if ( ! current_user_can( 'pf_manage_enrollments' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage course invitations.', 'parish-formation' ) );
		}
	}

	private static function redirect( $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'parish-formation-invitations', 'pf_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function render_notice() {
		$notice = isset( $_GET['pf_notice'] ) ? sanitize_key( wp_unslash( $_GET['pf_notice'] ) ) : '';
		$messages = array( 'created' => __( 'Invitation created.', 'parish-formation' ), 'revoked' => __( 'Invitation revoked.', 'parish-formation' ), 'resent' => __( 'Invitation email resent.', 'parish-formation' ), 'resend_failed' => __( 'The invitation email could not be sent. Review the email activity log for details.', 'parish-formation' ), 'invitation_not_resendable' => __( 'This invitation cannot be resent.', 'parish-formation' ), 'invalid_course' => __( 'Select a valid course.', 'parish-formation' ), 'invalid_email' => __( 'Enter a valid restricted email address.', 'parish-formation' ), 'invalid_expiration' => __( 'Enter a valid expiration date and time.', 'parish-formation' ), 'database_error' => __( 'The invitation could not be saved.', 'parish-formation' ) );
		if ( isset( $messages[ $notice ] ) ) {
			echo '<div class="notice ' . esc_attr( in_array( $notice, array( 'created', 'revoked', 'resent' ), true ) ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
	}

	private static function status_label( $row ) {
		if ( 'revoked' === $row->status ) { return __( 'Revoked', 'parish-formation' ); }
		if ( $row->expires_at && strtotime( $row->expires_at . ' UTC' ) < time() ) { return __( 'Expired', 'parish-formation' ); }
		if ( $row->max_uses && $row->use_count >= $row->max_uses ) { return __( 'Used', 'parish-formation' ); }
		return __( 'Active', 'parish-formation' );
	}
}
