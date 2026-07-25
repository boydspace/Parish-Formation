<?php
/** Notification settings and delivery log administration. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Provides notification configuration, testing, and delivery history. */
final class Parish_Formation_Notifications_Admin {
	/** Register the notifications submenu. */
	public static function register_menu() {
		add_submenu_page( null, esc_html__( 'Email Notifications', 'parish-formation' ), esc_html__( 'Email Notifications', 'parish-formation' ), 'pf_manage_settings', 'parish-formation-notifications', array( self::class, 'render_page' ) );
	}

	/** Load the AJAX tab controller only on this settings screen. */
	public static function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'parish-formation-notifications' ) && 'parish-formation_page_parish-formation-settings' !== $hook_suffix ) {
			return;
		}
		$action_nonces = array();
		foreach ( Parish_Formation_Notifications::types() as $type => $definition ) {
			$action_nonces[ $type ] = array(
				'pf-notification-template-form' => wp_create_nonce( 'pf_save_notification_template_' . $type ),
				'pf-notification-reset-form'    => wp_create_nonce( 'pf_reset_notification_template_' . $type ),
				'pf-notification-test-form'     => wp_create_nonce( 'pf_test_notification_template_' . $type ),
			);
		}
		wp_enqueue_media();
		wp_enqueue_script( 'pf-notification-admin', PARISH_FORMATION_PLUGIN_URL . 'assets/js/pf-notifications-admin.js', array( 'jquery', 'wp-util', 'editor' ), PARISH_FORMATION_VERSION, true );
		wp_localize_script( 'pf-notification-admin', 'pfNotificationAdmin', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'pf_load_notification_template' ), 'actionNonces' => $action_nonces, 'errorMessage' => __( 'The email template could not be loaded. Please try again.', 'parish-formation' ) ) );
	}

	/** Return one template for an in-place tab switch. */
	public static function ajax_load_template() {
		self::require_access();
		check_ajax_referer( 'pf_load_notification_template' );
		$type = isset( $_GET['template_type'] ) ? sanitize_key( wp_unslash( $_GET['template_type'] ) ) : '';
		self::require_template_type( $type );
		$types       = Parish_Formation_Notifications::types();
		$template    = Parish_Formation_Notifications::template( $type );
		$preview     = Parish_Formation_Notifications::resolve_template( $type, Parish_Formation_Notifications::sample_values() );
		$placeholder = array_map( static function ( $item ) { return '{' . $item . '}'; }, Parish_Formation_Notifications::placeholders( $type ) );
		wp_send_json_success( array( 'type' => $type, 'label' => $types[ $type ][1], 'subject' => $template[0], 'body' => $template[1], 'placeholders' => implode( ', ', $placeholder ), 'preview' => wp_kses_post( Parish_Formation_Notifications::preview_html( $preview[0], $preview[1] ) ) ) );
	}

	/** Render settings, the test tool, and recent delivery outcomes. */
	public static function render_page() {
		self::require_access();
		global $wpdb;
		$settings = Parish_Formation_Notifications::settings();
		$types    = Parish_Formation_Notifications::types();
		$requested_tab  = isset( $_GET['template_type'] ) ? sanitize_key( wp_unslash( $_GET['template_type'] ) ) : 'enrollment_confirmation';
		$is_design      = 'email_design' === $requested_tab;
		$template_type  = $is_design ? 'enrollment_confirmation' : $requested_tab;
		if ( ! isset( $types[ $template_type ] ) ) {
			$template_type = 'enrollment_confirmation';
		}
		$template = Parish_Formation_Notifications::template( $template_type );
		$preview  = Parish_Formation_Notifications::resolve_template( $template_type, Parish_Formation_Notifications::sample_values() );
		$design   = Parish_Formation_Notifications::design();
		$log_status = isset( $_GET['log_status'] ) ? sanitize_key( wp_unslash( $_GET['log_status'] ) ) : 'all';
		$log_type   = isset( $_GET['log_type'] ) ? sanitize_key( wp_unslash( $_GET['log_type'] ) ) : 'all';
		$log_search = isset( $_GET['log_search'] ) ? sanitize_text_field( wp_unslash( $_GET['log_search'] ) ) : '';
		$where      = array( '1=1' );
		$query_args = array();
		if ( in_array( $log_status, array( 'sent', 'failed' ), true ) ) { $where[] = 'status = %s'; $query_args[] = $log_status; } else { $log_status = 'all'; }
		if ( isset( $types[ $log_type ] ) ) { $where[] = 'notification_type = %s'; $query_args[] = $log_type; } else { $log_type = 'all'; }
		if ( $log_search ) { $like = '%' . $wpdb->esc_like( $log_search ) . '%'; $where[] = '(recipient LIKE %s OR subject LIKE %s)'; array_push( $query_args, $like, $like ); }
		$log_sql = "SELECT * FROM {$wpdb->prefix}pf_notification_log WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT 100';
		$logs    = $query_args ? $wpdb->get_results( $wpdb->prepare( $log_sql, $query_args ) ) : $wpdb->get_results( $log_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Email Notifications', 'parish-formation' ); ?></h1>
			<?php self::render_notice(); ?>
			<p><?php esc_html_e( 'Configure formation email delivery. Each event can be disabled independently, and every attempted message is recorded below.', 'parish-formation' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pf_save_notification_settings"><?php wp_nonce_field( 'pf_save_notification_settings' ); ?>
				<h2><?php esc_html_e( 'Sender and Staff Recipients', 'parish-formation' ); ?></h2>
				<table class="form-table"><tbody>
				<tr><th><label for="pf-from-name"><?php esc_html_e( 'From name', 'parish-formation' ); ?></label></th><td><input id="pf-from-name" name="from_name" type="text" class="regular-text" required value="<?php echo esc_attr( $settings['from_name'] ); ?>"></td></tr>
				<tr><th><label for="pf-from-email"><?php esc_html_e( 'From email', 'parish-formation' ); ?></label></th><td><input id="pf-from-email" name="from_email" type="email" class="regular-text" required value="<?php echo esc_attr( $settings['from_email'] ); ?>"><p class="description"><?php esc_html_e( 'Use an address authorized by your website’s mail provider.', 'parish-formation' ); ?></p></td></tr>
				<tr><th><label for="pf-reply-to"><?php esc_html_e( 'Reply-to email', 'parish-formation' ); ?></label></th><td><input id="pf-reply-to" name="reply_to" type="email" class="regular-text" required value="<?php echo esc_attr( $settings['reply_to'] ); ?>"><p class="description"><?php esc_html_e( 'Participant replies will be directed to this address.', 'parish-formation' ); ?></p></td></tr>
				<tr><th><label for="pf-staff-emails"><?php esc_html_e( 'Staff notification emails', 'parish-formation' ); ?></label></th><td><textarea id="pf-staff-emails" name="staff_emails" class="large-text" rows="3"><?php echo esc_textarea( $settings['staff_emails'] ); ?></textarea><p class="description"><?php esc_html_e( 'Separate multiple addresses with commas or new lines.', 'parish-formation' ); ?></p></td></tr>
				<tr><th><label for="pf-reminder-days"><?php esc_html_e( 'Expiration reminder days', 'parish-formation' ); ?></label></th><td><input id="pf-reminder-days" name="reminder_days" type="text" class="regular-text" value="<?php echo esc_attr( $settings['reminder_days'] ); ?>"><p class="description"><?php esc_html_e( 'Days before expiration, separated by commas—for example: 30, 14, 7, 1.', 'parish-formation' ); ?></p></td></tr>
				</tbody></table>
				<h2><?php esc_html_e( 'Enabled Messages', 'parish-formation' ); ?></h2>
				<table class="widefat striped" style="max-width:900px"><thead><tr><th><?php esc_html_e( 'Send', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Message', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Recipient', 'parish-formation' ); ?></th></tr></thead><tbody>
				<?php foreach ( $types as $type => $definition ) : $recipient_label = 'staff' === $definition[0] ? __( 'Staff', 'parish-formation' ) : ( 'account' === $definition[0] ? __( 'All new users', 'parish-formation' ) : __( 'Participant', 'parish-formation' ) ); ?><tr><td><input type="checkbox" name="enabled[<?php echo esc_attr( $type ); ?>]" value="1" <?php checked( ! empty( $settings['enabled'][ $type ] ) ); ?>></td><td><?php echo esc_html( $definition[1] ); ?></td><td><?php echo esc_html( $recipient_label ); ?></td></tr><?php endforeach; ?>
				</tbody></table>
				<?php submit_button( __( 'Save Email Settings', 'parish-formation' ) ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Email Template Editor', 'parish-formation' ); ?></h2>
			<p><?php esc_html_e( 'Choose an email tab to edit its subject, message, placeholders, and test delivery.', 'parish-formation' ); ?></p>
			<h3><?php esc_html_e( 'Branding', 'parish-formation' ); ?></h3>
			<nav class="nav-tab-wrapper" style="display:flex;flex-wrap:wrap;gap:0;margin-bottom:18px" aria-label="<?php esc_attr_e( 'Email branding', 'parish-formation' ); ?>"><a id="pf-notification-design-tab" class="nav-tab <?php echo $is_design ? 'nav-tab-active' : ''; ?>" data-template-type="email_design" href="<?php echo esc_url( add_query_arg( array( 'page' => 'parish-formation-notifications', 'template_type' => 'email_design' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Email Design', 'parish-formation' ); ?></a></nav>
			<h3><?php esc_html_e( 'Account Emails', 'parish-formation' ); ?></h3>
			<nav class="nav-tab-wrapper" style="display:flex;flex-wrap:wrap;gap:0;margin-bottom:18px" aria-label="<?php esc_attr_e( 'WordPress account email templates', 'parish-formation' ); ?>">
			<?php foreach ( $types as $type => $definition ) : if ( 'account' !== $definition[0] ) { continue; } $tab_url = add_query_arg( array( 'page' => 'parish-formation-notifications', 'template_type' => $type ), admin_url( 'admin.php' ) ); ?><a class="nav-tab pf-notification-template-tab <?php echo ! $is_design && $template_type === $type ? 'nav-tab-active' : ''; ?>" data-template-type="<?php echo esc_attr( $type ); ?>" href="<?php echo esc_url( $tab_url ); ?>"><?php echo esc_html( $definition[1] ); ?></a><?php endforeach; ?>
			</nav>
			<h3><?php esc_html_e( 'Participant Emails', 'parish-formation' ); ?></h3>
			<nav class="nav-tab-wrapper" style="display:flex;flex-wrap:wrap;gap:0;margin-bottom:18px" aria-label="<?php esc_attr_e( 'Participant email templates', 'parish-formation' ); ?>">
			<?php foreach ( $types as $type => $definition ) : if ( 'participant' !== $definition[0] ) { continue; } $tab_url = add_query_arg( array( 'page' => 'parish-formation-notifications', 'template_type' => $type ), admin_url( 'admin.php' ) ); ?><a class="nav-tab pf-notification-template-tab <?php echo ! $is_design && $template_type === $type ? 'nav-tab-active' : ''; ?>" data-template-type="<?php echo esc_attr( $type ); ?>" href="<?php echo esc_url( $tab_url ); ?>"><?php echo esc_html( $definition[1] ); ?></a><?php endforeach; ?>
			</nav>
			<h3><?php esc_html_e( 'Staff Emails', 'parish-formation' ); ?></h3>
			<nav class="nav-tab-wrapper" style="display:flex;flex-wrap:wrap;gap:0;margin-bottom:24px" aria-label="<?php esc_attr_e( 'Staff email templates', 'parish-formation' ); ?>">
			<?php foreach ( $types as $type => $definition ) : if ( 'staff' !== $definition[0] ) { continue; } $tab_url = add_query_arg( array( 'page' => 'parish-formation-notifications', 'template_type' => $type ), admin_url( 'admin.php' ) ); ?><a class="nav-tab pf-notification-template-tab <?php echo ! $is_design && $template_type === $type ? 'nav-tab-active' : ''; ?>" data-template-type="<?php echo esc_attr( $type ); ?>" href="<?php echo esc_url( $tab_url ); ?>"><?php echo esc_html( $definition[1] ); ?></a><?php endforeach; ?>
			</nav>
			<div id="pf-notification-design-panel" style="<?php echo $is_design ? '' : 'display:none;'; ?>max-width:1100px" aria-live="polite">
				<h3><?php esc_html_e( 'Email Design', 'parish-formation' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="pf_save_notification_design"><?php wp_nonce_field( 'pf_save_notification_design' ); ?>
					<table class="form-table"><tbody>
					<tr><th><label for="pf-design-header-name"><?php esc_html_e( 'Header name', 'parish-formation' ); ?></label></th><td><input id="pf-design-header-name" name="header_name" type="text" class="regular-text pf-design-control" data-preview="header-name" required value="<?php echo esc_attr( $design['header_name'] ); ?>"></td></tr>
					<tr><th><label for="pf-design-logo-url"><?php esc_html_e( 'Logo', 'parish-formation' ); ?></label></th><td><input id="pf-design-logo-url" name="logo_url" type="url" class="regular-text pf-design-control" data-preview="logo" value="<?php echo esc_attr( $design['logo_url'] ); ?>"> <button id="pf-design-select-logo" type="button" class="button"><?php esc_html_e( 'Select Logo', 'parish-formation' ); ?></button><p class="description"><?php esc_html_e( 'Use a compact logo that remains readable in email clients.', 'parish-formation' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Colors', 'parish-formation' ); ?></th><td>
					<?php $colors = array( 'page_color' => __( 'Page background', 'parish-formation' ), 'header_color' => __( 'Header background', 'parish-formation' ), 'header_text_color' => __( 'Header text', 'parish-formation' ), 'content_color' => __( 'Content background', 'parish-formation' ), 'text_color' => __( 'Main text', 'parish-formation' ), 'link_color' => __( 'Links', 'parish-formation' ), 'footer_color' => __( 'Footer background', 'parish-formation' ) ); foreach ( $colors as $key => $label ) : ?><label style="display:inline-block;margin:0 20px 14px 0"><?php echo esc_html( $label ); ?><br><input name="<?php echo esc_attr( $key ); ?>" type="color" class="pf-design-control" data-preview="<?php echo esc_attr( str_replace( '_', '-', $key ) ); ?>" value="<?php echo esc_attr( $design[ $key ] ); ?>"></label><?php endforeach; ?>
					</td></tr>
					<tr><th><label for="pf-design-width"><?php esc_html_e( 'Email width', 'parish-formation' ); ?></label></th><td><input id="pf-design-width" name="container_width" type="range" min="520" max="760" step="20" class="pf-design-control" data-preview="width" value="<?php echo esc_attr( $design['container_width'] ); ?>"> <output id="pf-design-width-output"><?php echo esc_html( $design['container_width'] ); ?>px</output></td></tr>
					<tr><th><label for="pf-design-footer-text"><?php esc_html_e( 'Footer text', 'parish-formation' ); ?></label></th><td><input id="pf-design-footer-text" name="footer_text" type="text" class="large-text pf-design-control" data-preview="footer-text" value="<?php echo esc_attr( $design['footer_text'] ); ?>"></td></tr>
					<tr><th><label for="pf-design-contact-text"><?php esc_html_e( 'Contact details', 'parish-formation' ); ?></label></th><td><textarea id="pf-design-contact-text" name="contact_text" class="large-text pf-design-control" data-preview="contact-text" rows="3"><?php echo esc_textarea( $design['contact_text'] ); ?></textarea></td></tr>
					</tbody></table>
					<?php submit_button( __( 'Save Email Design', 'parish-formation' ) ); ?>
				</form>
				<h3><?php esc_html_e( 'Live Design Preview', 'parish-formation' ); ?></h3>
				<div id="pf-design-preview-page" style="padding:24px;background:<?php echo esc_attr( $design['page_color'] ); ?>;overflow:auto"><div id="pf-design-preview-container" style="max-width:<?php echo esc_attr( $design['container_width'] ); ?>px;margin:auto;border:1px solid #dcdcde;background:<?php echo esc_attr( $design['content_color'] ); ?>"><div id="pf-design-preview-header" style="padding:22px 28px;background:<?php echo esc_attr( $design['header_color'] ); ?>;color:<?php echo esc_attr( $design['header_text_color'] ); ?>;font-size:20px;font-weight:700"><img id="pf-design-preview-logo" src="<?php echo esc_url( $design['logo_url'] ); ?>" alt="" style="<?php echo $design['logo_url'] ? 'display:block;' : 'display:none;'; ?>max-height:54px;max-width:220px;margin:0 0 12px"><span id="pf-design-preview-header-name"><?php echo esc_html( $design['header_name'] ); ?></span></div><div id="pf-design-preview-content" style="padding:30px 28px;background:<?php echo esc_attr( $design['content_color'] ); ?>;color:<?php echo esc_attr( $design['text_color'] ); ?>"><h2 style="color:inherit"><?php esc_html_e( 'Sample formation email', 'parish-formation' ); ?></h2><p><?php esc_html_e( 'This preview shows how your parish branding will appear around every notification.', 'parish-formation' ); ?> <a id="pf-design-preview-link" href="#" style="color:<?php echo esc_attr( $design['link_color'] ); ?>"><?php esc_html_e( 'Sample link', 'parish-formation' ); ?></a></p></div><div id="pf-design-preview-footer" style="padding:18px 28px;background:<?php echo esc_attr( $design['footer_color'] ); ?>;color:#646970;font-size:13px"><span id="pf-design-preview-footer-text"><?php echo esc_html( $design['footer_text'] ); ?></span><span id="pf-design-preview-contact"><?php echo $design['contact_text'] ? '<br>' . nl2br( esc_html( $design['contact_text'] ) ) : ''; ?></span></div></div></div>
			</div>
			<div id="pf-notification-template-panel" style="<?php echo $is_design ? 'display:none;' : ''; ?>" aria-live="polite">
			<h3 id="pf-notification-template-title"><?php echo esc_html( $types[ $template_type ][1] ); ?></h3>
			<form id="pf-notification-template-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:900px">
				<input type="hidden" name="action" value="pf_save_notification_template"><input type="hidden" name="template_type" value="<?php echo esc_attr( $template_type ); ?>"><?php wp_nonce_field( 'pf_save_notification_template_' . $template_type ); ?>
				<p><label for="pf-template-subject"><strong><?php esc_html_e( 'Subject', 'parish-formation' ); ?></strong></label><br><input id="pf-template-subject" name="template_subject" type="text" class="large-text" required value="<?php echo esc_attr( $template[0] ); ?>"></p>
				<p><strong><?php esc_html_e( 'Message', 'parish-formation' ); ?></strong></p>
				<input id="pf-notification-initial-body" type="hidden" value="<?php echo esc_attr( $template[1] ); ?>">
				<?php wp_editor( $template[1], 'pf_notification_template_body', array( 'textarea_name' => 'template_body', 'textarea_rows' => 12, 'media_buttons' => false, 'teeny' => false ) ); ?>
				<p><strong><?php esc_html_e( 'Available placeholders:', 'parish-formation' ); ?></strong> <span id="pf-notification-placeholders"><?php echo esc_html( implode( ', ', array_map( static function ( $placeholder ) { return '{' . $placeholder . '}'; }, Parish_Formation_Notifications::placeholders( $template_type ) ) ) ); ?></span></p>
				<?php submit_button( __( 'Save Template', 'parish-formation' ) ); ?>
			</form>
			<form id="pf-notification-reset-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:12px"><input type="hidden" name="action" value="pf_reset_notification_template"><input type="hidden" name="template_type" value="<?php echo esc_attr( $template_type ); ?>"><?php wp_nonce_field( 'pf_reset_notification_template_' . $template_type ); ?><button type="submit" class="button"><?php esc_html_e( 'Restore Default', 'parish-formation' ); ?></button></form>
			<form id="pf-notification-test-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block"><input type="hidden" name="action" value="pf_test_notification_template"><input type="hidden" name="template_type" value="<?php echo esc_attr( $template_type ); ?>"><?php wp_nonce_field( 'pf_test_notification_template_' . $template_type ); ?><label for="pf-template-test-email"><?php esc_html_e( 'Send preview to', 'parish-formation' ); ?></label> <input id="pf-template-test-email" name="test_email" type="email" required value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>"> <button type="submit" class="button"><?php esc_html_e( 'Send Template Test', 'parish-formation' ); ?></button></form>
			<h3><?php esc_html_e( 'Sample Preview', 'parish-formation' ); ?></h3>
			<div id="pf-notification-template-preview" style="max-width:900px;border:1px solid #c3c4c7;background:#fff;padding:12px;overflow:auto"><?php echo wp_kses_post( Parish_Formation_Notifications::preview_html( $preview[0], $preview[1] ) ); ?></div>
			</div>

			<h2><?php esc_html_e( 'Send Test Email', 'parish-formation' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pf_send_test_notification"><?php wp_nonce_field( 'pf_send_test_notification' ); ?>
				<label for="pf-test-email"><?php esc_html_e( 'Recipient', 'parish-formation' ); ?></label>
				<input id="pf-test-email" name="test_email" type="email" class="regular-text" required value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
				<?php submit_button( __( 'Send Test Email', 'parish-formation' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Recent Email Activity', 'parish-formation' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Accepted by Mailer means WordPress handed the message to the configured mail system. Use FluentSMTP or your SMTP provider logs to confirm transport and final delivery.', 'parish-formation' ); ?></p>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom:12px"><input type="hidden" name="page" value="parish-formation-notifications"><input type="search" name="log_search" value="<?php echo esc_attr( $log_search ); ?>" placeholder="<?php esc_attr_e( 'Recipient or subject', 'parish-formation' ); ?>"><select name="log_type"><option value="all"><?php esc_html_e( 'All messages', 'parish-formation' ); ?></option><?php foreach ( $types as $type => $definition ) : ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $log_type, $type ); ?>><?php echo esc_html( $definition[1] ); ?></option><?php endforeach; ?></select><select name="log_status"><option value="all"><?php esc_html_e( 'All statuses', 'parish-formation' ); ?></option><option value="sent" <?php selected( $log_status, 'sent' ); ?>><?php esc_html_e( 'Accepted by Mailer', 'parish-formation' ); ?></option><option value="failed" <?php selected( $log_status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'parish-formation' ); ?></option></select> <?php submit_button( __( 'Filter Activity', 'parish-formation' ), 'secondary', 'submit', false ); ?> <a class="button" href="<?php echo esc_url( add_query_arg( 'page', 'parish-formation-notifications', admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Clear', 'parish-formation' ); ?></a></form>
			<?php if ( ! $logs ) : ?><p><?php esc_html_e( 'No email delivery attempts have been recorded yet.', 'parish-formation' ); ?></p><?php else : ?>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Date', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Message', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Recipient', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Subject', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Status', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Action', 'parish-formation' ); ?></th></tr></thead><tbody>
			<?php foreach ( $logs as $log ) : $status_label = 'sent' === $log->status ? __( 'Accepted by Mailer', 'parish-formation' ) : __( 'Failed', 'parish-formation' ); ?><tr><td><?php echo esc_html( get_date_from_gmt( $log->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td><td><?php echo esc_html( isset( $types[ $log->notification_type ] ) ? $types[ $log->notification_type ][1] : $log->notification_type ); ?></td><td><?php echo esc_html( $log->recipient ); ?></td><td><?php echo esc_html( $log->subject ); ?></td><td><strong><?php echo esc_html( $status_label ); ?></strong><?php if ( $log->error_message ) : ?><br><small><?php echo esc_html( $log->error_message ); ?></small><?php endif; ?></td><td><?php if ( 'failed' === $log->status && ! empty( $log->message_body ) ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_retry_notification"><input type="hidden" name="log_id" value="<?php echo esc_attr( $log->id ); ?>"><?php wp_nonce_field( 'pf_retry_notification_' . $log->id ); ?><button class="button button-small" type="submit"><?php esc_html_e( 'Retry', 'parish-formation' ); ?></button></form><?php else : ?>&mdash;<?php endif; ?></td></tr><?php endforeach; ?>
			</tbody></table><?php endif; ?>
		</div>
		<?php
	}

	/** Persist sanitized notification settings. */
	public static function handle_save() {
		self::require_access();
		check_admin_referer( 'pf_save_notification_settings' );
		$enabled = array();
		$posted_enabled = isset( $_POST['enabled'] ) && is_array( $_POST['enabled'] ) ? wp_unslash( $_POST['enabled'] ) : array();
		foreach ( Parish_Formation_Notifications::types() as $type => $definition ) {
			$enabled[ $type ] = isset( $posted_enabled[ $type ] );
		}
		$from_email = isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '';
		if ( ! $from_email ) {
			self::redirect( 'invalid_sender' );
		}
		$reply_to = isset( $_POST['reply_to'] ) ? sanitize_email( wp_unslash( $_POST['reply_to'] ) ) : '';
		if ( ! $reply_to ) {
			self::redirect( 'invalid_reply_to' );
		}
		$current = get_option( Parish_Formation_Notifications::SETTINGS_OPTION, array() );
		update_option( Parish_Formation_Notifications::SETTINGS_OPTION, array( 'from_name' => isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '', 'from_email' => $from_email, 'reply_to' => $reply_to, 'staff_emails' => isset( $_POST['staff_emails'] ) ? Parish_Formation_Notifications::sanitize_email_list( wp_unslash( $_POST['staff_emails'] ) ) : '', 'reminder_days' => isset( $_POST['reminder_days'] ) ? Parish_Formation_Notifications::sanitize_reminder_days( wp_unslash( $_POST['reminder_days'] ) ) : '', 'enabled' => $enabled, 'templates' => isset( $current['templates'] ) ? $current['templates'] : array(), 'design' => isset( $current['design'] ) ? $current['design'] : array() ), false );
		self::redirect( 'settings_saved' );
	}

	/** Save one customized subject and body after validating placeholders. */
	public static function handle_template_save() {
		self::require_access();
		$type = isset( $_POST['template_type'] ) ? sanitize_key( wp_unslash( $_POST['template_type'] ) ) : '';
		self::require_template_type( $type );
		check_admin_referer( 'pf_save_notification_template_' . $type );
		$subject = isset( $_POST['template_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['template_subject'] ) ) : '';
		$body    = isset( $_POST['template_body'] ) ? wp_kses_post( wp_unslash( $_POST['template_body'] ) ) : '';
		preg_match_all( '/\{([a-z0-9_]+)\}/', $subject . ' ' . $body, $matches );
		$unknown = array_diff( array_unique( $matches[1] ), Parish_Formation_Notifications::placeholders( $type ) );
		if ( $unknown ) {
			set_transient( 'pf_notification_template_error_' . get_current_user_id(), sprintf( __( 'Unknown placeholders: %s', 'parish-formation' ), implode( ', ', array_map( static function ( $item ) { return '{' . $item . '}'; }, $unknown ) ) ), 60 );
			self::redirect( 'template_invalid', $type );
		}
		$settings = get_option( Parish_Formation_Notifications::SETTINGS_OPTION, array() );
		$settings['templates'][ $type ] = array( 'subject' => $subject, 'body' => $body );
		update_option( Parish_Formation_Notifications::SETTINGS_OPTION, $settings, false );
		self::redirect( 'template_saved', $type );
	}

	/** Restore one template to its packaged default. */
	public static function handle_template_reset() {
		self::require_access();
		$type = isset( $_POST['template_type'] ) ? sanitize_key( wp_unslash( $_POST['template_type'] ) ) : '';
		self::require_template_type( $type );
		check_admin_referer( 'pf_reset_notification_template_' . $type );
		$settings = get_option( Parish_Formation_Notifications::SETTINGS_OPTION, array() );
		unset( $settings['templates'][ $type ] );
		update_option( Parish_Formation_Notifications::SETTINGS_OPTION, $settings, false );
		self::redirect( 'template_reset', $type );
	}

	/** Send the selected template populated with sample values. */
	public static function handle_template_test() {
		self::require_access();
		$type = isset( $_POST['template_type'] ) ? sanitize_key( wp_unslash( $_POST['template_type'] ) ) : '';
		self::require_template_type( $type );
		check_admin_referer( 'pf_test_notification_template_' . $type );
		$email = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';
		if ( ! $email ) {
			self::redirect( 'invalid_test_recipient', $type );
		}
		$content = Parish_Formation_Notifications::resolve_template( $type, Parish_Formation_Notifications::sample_values() );
		$sent = Parish_Formation_Notifications::send( $type, $email, $content[0], Parish_Formation_Notifications::types()[ $type ][1], $content[1], 'template_test_' . wp_generate_password( 20, false, false ), true );
		self::redirect( $sent ? 'template_test_sent' : 'test_failed', $type );
	}

	/** Save the shared email branding design. */
	public static function handle_design_save() {
		self::require_access();
		check_admin_referer( 'pf_save_notification_design' );
		$color_keys = array( 'page_color', 'header_color', 'header_text_color', 'content_color', 'text_color', 'link_color', 'footer_color' );
		$design = array(
			'header_name' => isset( $_POST['header_name'] ) ? sanitize_text_field( wp_unslash( $_POST['header_name'] ) ) : '',
			'logo_url' => isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '',
			'footer_text' => isset( $_POST['footer_text'] ) ? sanitize_text_field( wp_unslash( $_POST['footer_text'] ) ) : '',
			'contact_text' => isset( $_POST['contact_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['contact_text'] ) ) : '',
			'container_width' => isset( $_POST['container_width'] ) ? min( 760, max( 520, absint( $_POST['container_width'] ) ) ) : 640,
		);
		foreach ( $color_keys as $key ) {
			$design[ $key ] = isset( $_POST[ $key ] ) ? sanitize_hex_color( wp_unslash( $_POST[ $key ] ) ) : '';
		}
		$settings = get_option( Parish_Formation_Notifications::SETTINGS_OPTION, array() );
		$settings['design'] = $design;
		update_option( Parish_Formation_Notifications::SETTINGS_OPTION, $settings, false );
		self::redirect( 'design_saved', 'email_design' );
	}

	/** Retry one failed logged email. */
	public static function handle_retry() {
		self::require_access();
		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
		check_admin_referer( 'pf_retry_notification_' . $log_id );
		$result = Parish_Formation_Notifications::retry( $log_id );
		self::redirect( is_wp_error( $result ) ? $result->get_error_code() : ( $result ? 'retry_sent' : 'retry_failed' ) );
	}

	/** Send a uniquely logged test notification. */
	public static function handle_test() {
		self::require_access();
		check_admin_referer( 'pf_send_test_notification' );
		$email = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';
		if ( ! $email ) {
			self::redirect( 'invalid_test_recipient' );
		}
		$sent = Parish_Formation_Notifications::send( 'enrollment_confirmation', $email, sprintf( __( '[%s] Parish Formation test email', 'parish-formation' ), get_bloginfo( 'name' ) ), __( 'Your email settings are working', 'parish-formation' ), __( 'This is a test of the Parish Formation notification system. Future course messages will use this layout and sender configuration.', 'parish-formation' ), 'test_' . wp_generate_password( 20, false, false ), true );
		self::redirect( $sent ? 'test_sent' : 'test_failed' );
	}

	/** Require formation settings access. */
	private static function require_access() {
		if ( ! current_user_can( 'pf_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage email notifications.', 'parish-formation' ) );
		}
	}

	/** Redirect to the settings page with a whitelisted notice code. */
	private static function redirect( $notice, $template_type = '' ) {
		$args = array( 'page' => 'parish-formation-notifications', 'pf_notification_notice' => sanitize_key( $notice ) );
		if ( $template_type ) {
			$args['template_type'] = sanitize_key( $template_type );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Reject unknown template identifiers. */
	private static function require_template_type( $type ) {
		if ( ! isset( Parish_Formation_Notifications::types()[ $type ] ) ) {
			wp_die( esc_html__( 'The selected email template is invalid.', 'parish-formation' ) );
		}
	}

	/** Render action feedback. */
	private static function render_notice() {
		$code = isset( $_GET['pf_notification_notice'] ) ? sanitize_key( wp_unslash( $_GET['pf_notification_notice'] ) ) : '';
		$template_error = get_transient( 'pf_notification_template_error_' . get_current_user_id() );
		if ( $template_error ) {
			delete_transient( 'pf_notification_template_error_' . get_current_user_id() );
		}
		$notices = array(
			'settings_saved' => array( 'success', __( 'Email notification settings were saved.', 'parish-formation' ) ),
			'test_sent' => array( 'success', __( 'WordPress accepted the test email for delivery.', 'parish-formation' ) ),
			'test_failed' => array( 'error', __( 'WordPress could not send the test email. Check the activity log and your site mail configuration.', 'parish-formation' ) ),
			'invalid_sender' => array( 'error', __( 'Enter a valid sender email address.', 'parish-formation' ) ),
			'invalid_reply_to' => array( 'error', __( 'Enter a valid reply-to email address.', 'parish-formation' ) ),
			'invalid_test_recipient' => array( 'error', __( 'Enter a valid test recipient email address.', 'parish-formation' ) ),
			'template_saved' => array( 'success', __( 'The email template was saved.', 'parish-formation' ) ),
			'template_reset' => array( 'success', __( 'The email template was restored to its default.', 'parish-formation' ) ),
			'template_test_sent' => array( 'success', __( 'WordPress accepted the template test for delivery.', 'parish-formation' ) ),
			'design_saved' => array( 'success', __( 'The email design was saved.', 'parish-formation' ) ),
			'retry_sent' => array( 'success', __( 'WordPress accepted the retried email for delivery.', 'parish-formation' ) ),
			'retry_failed' => array( 'error', __( 'The retried email still could not be sent.', 'parish-formation' ) ),
			'notification_not_retryable' => array( 'error', __( 'That email is not available for retry.', 'parish-formation' ) ),
			'template_invalid' => array( 'error', $template_error ?: __( 'The template contains an unknown placeholder.', 'parish-formation' ) ),
		);
		if ( isset( $notices[ $code ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notices[ $code ][0] ), esc_html( $notices[ $code ][1] ) );
		}
	}
}
