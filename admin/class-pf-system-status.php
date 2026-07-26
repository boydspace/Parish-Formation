<?php
/** Read-only production diagnostics. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Displays environment and Parish Formation readiness checks. */
final class Parish_Formation_System_Status {
	public static function register_menu() {
		add_submenu_page( null, __( 'System Status', 'parish-formation' ), __( 'System Status', 'parish-formation' ), 'pf_manage_settings', 'parish-formation-system-status', array( self::class, 'render_page' ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'pf_manage_settings' ) ) { wp_die( esc_html__( 'You do not have permission to view system status.', 'parish-formation' ) ); }
		$checks = self::checks();
		$counts = array_count_values( wp_list_pluck( $checks, 'status' ) );
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Parish Formation System Status', 'parish-formation' ); ?></h1>
		<p><?php echo esc_html( sprintf( __( '%1$d checks passed, %2$d warnings, %3$d failures.', 'parish-formation' ), absint( $counts['good'] ?? 0 ), absint( $counts['warning'] ?? 0 ), absint( $counts['bad'] ?? 0 ) ) ); ?></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Check', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Status', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Details', 'parish-formation' ); ?></th></tr></thead><tbody>
		<?php foreach ( $checks as $check ) : ?><tr><td><strong><?php echo esc_html( $check['label'] ); ?></strong></td><td><span style="font-weight:700;color:<?php echo esc_attr( 'good' === $check['status'] ? '#147d43' : ( 'warning' === $check['status'] ? '#996800' : '#b42318' ) ); ?>"><?php echo esc_html( 'good' === $check['status'] ? __( 'Ready', 'parish-formation' ) : ( 'warning' === $check['status'] ? __( 'Review', 'parish-formation' ) : __( 'Problem', 'parish-formation' ) ) ); ?></span></td><td><?php echo esc_html( $check['details'] ); ?></td></tr><?php endforeach; ?>
		</tbody></table><p><a class="button button-secondary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'parish-formation-settings', 'hub_tab' => 'status', 'refreshed' => time() ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Refresh Status', 'parish-formation' ); ?></a></p></div>
		<?php
	}

	/** Return normalized status checks for UI and future support exports. */
	public static function checks() {
		global $wpdb;
		$checks = array();
		self::add( $checks, __( 'Plugin version', 'parish-formation' ), 'good', PARISH_FORMATION_VERSION );
		$installed_db = get_option( 'parish_formation_db_version', 'Not installed' );
		self::add( $checks, __( 'Database schema', 'parish-formation' ), version_compare( $installed_db, PARISH_FORMATION_DB_VERSION, '>=' ) ? 'good' : 'bad', sprintf( __( 'Installed %1$s; required %2$s', 'parish-formation' ), $installed_db, PARISH_FORMATION_DB_VERSION ) );
		self::add( $checks, __( 'PHP version', 'parish-formation' ), version_compare( PHP_VERSION, '8.3', '>=' ) ? 'good' : 'bad', PHP_VERSION );
		self::add( $checks, __( 'WordPress version', 'parish-formation' ), version_compare( get_bloginfo( 'version' ), '6.0', '>=' ) ? 'good' : 'bad', get_bloginfo( 'version' ) );

		$tables = array( 'pf_enrollments', 'pf_progress', 'pf_enrollment_runs', 'pf_assessment_attempts', 'pf_assessment_answers', 'pf_certificates', 'pf_notification_log', 'pf_invitations', 'pf_participant_notes', 'pf_participant_note_events' );
		$missing = array();
		foreach ( $tables as $suffix ) { $table = $wpdb->prefix . $suffix; if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) { $missing[] = $suffix; } }
		self::add( $checks, __( 'Database tables', 'parish-formation' ), $missing ? 'bad' : 'good', $missing ? sprintf( __( 'Missing: %s', 'parish-formation' ), implode( ', ', $missing ) ) : sprintf( __( 'All %d required tables found', 'parish-formation' ), count( $tables ) ) );

		self::shortcode_check( $checks, __( 'My Formation page', 'parish-formation' ), 'parish_formation_my_courses' );
		self::shortcode_check( $checks, __( 'Course catalog page', 'parish-formation' ), 'parish_formation_courses' );
		self::shortcode_check( $checks, __( 'Login page', 'parish-formation' ), 'parish_formation_login' );
		self::shortcode_check( $checks, __( 'Registration page', 'parish-formation' ), 'parish_formation_registration' );
		self::shortcode_check( $checks, __( 'Certificate verification page', 'parish-formation' ), 'formation-certificate', 'parish_formation_certificate_verification' );

		$permalinks = get_option( 'permalink_structure' );
		self::add( $checks, __( 'Pretty permalinks', 'parish-formation' ), $permalinks ? 'good' : 'bad', $permalinks ?: __( 'Plain permalinks are active; course routes require pretty permalinks.', 'parish-formation' ) );
		self::cron_check( $checks, __( 'Notification schedule', 'parish-formation' ), 'pf_daily_notification_events' );
		self::cron_check( $checks, __( 'Retention schedule', 'parish-formation' ), Parish_Formation_Retention::CRON_HOOK );
		self::add( $checks, __( 'WordPress cron', 'parish-formation' ), defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'warning' : 'good', defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'WP-Cron is disabled. Confirm a server cron invokes wp-cron.php.', 'parish-formation' ) : __( 'WP-Cron is enabled.', 'parish-formation' ) );
		self::add( $checks, __( 'PDF renderer', 'parish-formation' ), class_exists( '\\Dompdf\\Dompdf' ) ? 'good' : 'bad', class_exists( '\\Dompdf\\Dompdf' ) ? __( 'Dompdf is available.', 'parish-formation' ) : __( 'Dompdf could not be loaded.', 'parish-formation' ) );
		self::add( $checks, __( 'OpenSSL', 'parish-formation' ), extension_loaded( 'openssl' ) ? 'good' : 'bad', extension_loaded( 'openssl' ) ? __( 'Available for invitation-token protection.', 'parish-formation' ) : __( 'Required for invitation-token protection.', 'parish-formation' ) );
		self::add( $checks, __( 'GD image support', 'parish-formation' ), extension_loaded( 'gd' ) ? 'good' : 'warning', extension_loaded( 'gd' ) ? __( 'Available for certificate signature stamping.', 'parish-formation' ) : __( 'Signature verification-code stamping will be unavailable.', 'parish-formation' ) );
		$uploads = wp_upload_dir();
		$uploads_ready = empty( $uploads['error'] ) && is_dir( $uploads['basedir'] ) && is_writable( $uploads['basedir'] );
		self::add( $checks, __( 'Uploads directory', 'parish-formation' ), $uploads_ready ? 'good' : 'bad', $uploads_ready ? $uploads['basedir'] : ( $uploads['error'] ?: __( 'Directory is not writable.', 'parish-formation' ) ) );
		$mailer = self::detected_mailer();
		self::add( $checks, __( 'Email transport', 'parish-formation' ), $mailer ? 'good' : 'warning', $mailer ?: __( 'No recognized SMTP plugin detected; WordPress wp_mail() will use the server default.', 'parish-formation' ) );
		return $checks;
	}

	private static function shortcode_check( &$checks, $label, ...$shortcodes ) {
		global $wpdb;
		$clauses = array(); $args = array();
		foreach ( $shortcodes as $shortcode ) { $clauses[] = 'post_content LIKE %s'; $args[] = '%[' . $wpdb->esc_like( $shortcode ) . '%'; }
		$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish' AND (" . implode( ' OR ', $clauses ) . ') ORDER BY ID LIMIT 1';
		$page_id = absint( $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		self::add( $checks, $label, $page_id ? 'good' : 'bad', $page_id ? get_permalink( $page_id ) : sprintf( __( 'Publish a page containing [%s].', 'parish-formation' ), $shortcodes[0] ) );
	}

	private static function cron_check( &$checks, $label, $hook ) {
		$next = wp_next_scheduled( $hook );
		self::add( $checks, $label, $next ? 'good' : 'bad', $next ? sprintf( __( 'Next run: %s', 'parish-formation' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) ) : __( 'Not scheduled.', 'parish-formation' ) );
	}

	private static function detected_mailer() {
		$plugins = (array) get_option( 'active_plugins', array() );
		$known = array( 'fluent-smtp' => 'FluentSMTP', 'wp-mail-smtp' => 'WP Mail SMTP', 'post-smtp' => 'Post SMTP', 'easy-wp-smtp' => 'Easy WP SMTP', 'smtp-mailer' => 'SMTP Mailer' );
		foreach ( $plugins as $plugin ) { foreach ( $known as $needle => $label ) { if ( false !== stripos( $plugin, $needle ) ) { return $label; } } }
		return '';
	}

	private static function add( &$checks, $label, $status, $details ) { $checks[] = array( 'label' => $label, 'status' => $status, 'details' => (string) $details ); }
}
