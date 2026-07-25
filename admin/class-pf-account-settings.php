<?php
/** Participant account configuration. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Admin settings for public participant accounts. */
final class Parish_Formation_Account_Settings {
	public static function register_menu() {
		add_submenu_page( 'parish-formation', __( 'Account Settings', 'parish-formation' ), __( 'Account Settings', 'parish-formation' ), 'pf_manage_settings', 'parish-formation-account-settings', array( self::class, 'render_page' ), 35 );
	}

	public static function render_page() {
		self::require_access();
		$settings = Parish_Formation_Account_Service::settings();
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Participant Account Settings', 'parish-formation' ); ?></h1>
		<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success"><p><?php esc_html_e( 'Account settings saved.', 'parish-formation' ); ?></p></div><?php endif; ?>
		<p><?php esc_html_e( 'Control public registration and the fields required when participants create accounts.', 'parish-formation' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_save_account_settings"><?php wp_nonce_field( 'pf_save_account_settings' ); ?>
		<table class="form-table"><tbody>
		<tr><th><?php esc_html_e( 'Public registration', 'parish-formation' ); ?></th><td><label><input type="checkbox" name="public_registration" value="1" <?php checked( $settings['public_registration'] ); ?>> <?php esc_html_e( 'Allow account creation through the registration shortcode', 'parish-formation' ); ?></label><p class="description"><?php esc_html_e( 'Secure invitation registration remains available even when public registration is disabled.', 'parish-formation' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'Required profile fields', 'parish-formation' ); ?></th><td><label style="display:block;margin-bottom:8px"><input type="checkbox" name="require_first_name" value="1" <?php checked( $settings['require_first_name'] ); ?>> <?php esc_html_e( 'First name', 'parish-formation' ); ?></label><label style="display:block;margin-bottom:8px"><input type="checkbox" name="require_last_name" value="1" <?php checked( $settings['require_last_name'] ); ?>> <?php esc_html_e( 'Last name', 'parish-formation' ); ?></label><label style="display:block"><input type="checkbox" name="require_phone" value="1" <?php checked( $settings['require_phone'] ); ?>> <?php esc_html_e( 'Cell phone number', 'parish-formation' ); ?></label><p class="description"><?php esc_html_e( 'Email is always required. Username is generated automatically from the email address.', 'parish-formation' ); ?></p></td></tr>
		<tr><th><label for="pf-password-mode"><?php esc_html_e( 'Password', 'parish-formation' ); ?></label></th><td><select id="pf-password-mode" name="password_mode"><option value="required" <?php selected( $settings['password_mode'], 'required' ); ?>><?php esc_html_e( 'Participant creates a password', 'parish-formation' ); ?></option><option value="generated" <?php selected( $settings['password_mode'], 'generated' ); ?>><?php esc_html_e( 'Generate password and email account setup', 'parish-formation' ); ?></option></select></td></tr>
		<tr><th><label for="pf-login-redirect"><?php esc_html_e( 'After login', 'parish-formation' ); ?></label></th><td><input id="pf-login-redirect" name="login_redirect" type="url" class="large-text" value="<?php echo esc_attr( $settings['login_redirect'] ); ?>"></td></tr>
		<tr><th><label for="pf-registration-redirect"><?php esc_html_e( 'After registration', 'parish-formation' ); ?></label></th><td><input id="pf-registration-redirect" name="registration_redirect" type="url" class="large-text" value="<?php echo esc_attr( $settings['registration_redirect'] ); ?>"></td></tr>
		</tbody></table><?php submit_button( __( 'Save Account Settings', 'parish-formation' ) ); ?></form>
		<hr><h2><?php esc_html_e( 'Shortcodes', 'parish-formation' ); ?></h2><p><code>[parish_formation_login]</code> &nbsp; <code>[parish_formation_registration]</code></p></div>
		<?php
	}

	public static function handle_save() {
		self::require_access();
		check_admin_referer( 'pf_save_account_settings' );
		update_option( Parish_Formation_Account_Service::SETTINGS_OPTION, array( 'public_registration' => isset( $_POST['public_registration'] ) ? 1 : 0, 'require_first_name' => isset( $_POST['require_first_name'] ) ? 1 : 0, 'require_last_name' => isset( $_POST['require_last_name'] ) ? 1 : 0, 'require_phone' => isset( $_POST['require_phone'] ) ? 1 : 0, 'password_mode' => isset( $_POST['password_mode'] ) && 'generated' === sanitize_key( $_POST['password_mode'] ) ? 'generated' : 'required', 'login_redirect' => isset( $_POST['login_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['login_redirect'] ) ) : '', 'registration_redirect' => isset( $_POST['registration_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['registration_redirect'] ) ) : '' ), false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'parish-formation-account-settings', 'updated' => '1' ), admin_url( 'admin.php' ) ) ); exit;
	}

	private static function require_access() { if ( ! current_user_can( 'pf_manage_settings' ) ) { wp_die( esc_html__( 'You do not have permission to manage participant account settings.', 'parish-formation' ) ); } }
}
