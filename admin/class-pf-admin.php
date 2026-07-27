<?php
/**
 * Provides the Parish Formation administration interface.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin administration pages.
 */
final class Parish_Formation_Admin {

	/**
	 * Register the plugin admin menu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		$pending_count = current_user_can( 'pf_grade_assessments' ) ? Parish_Formation_Assessment_Repository::get_pending_review_count() : 0;
		$menu_title = esc_html__( 'Parish Formation', 'parish-formation' );
		if ( $pending_count ) { $menu_title .= ' <span class="awaiting-mod"><span class="pending-count">' . esc_html( number_format_i18n( $pending_count ) ) . '</span></span>'; }
		add_menu_page(
			esc_html__( 'Parish Formation', 'parish-formation' ),
			$menu_title,
			'pf_manage_courses',
			'parish-formation',
			array( self::class, 'render_dashboard' ),
			'dashicons-welcome-learn-more',
			25
		);

		add_submenu_page(
			'parish-formation',
			esc_html__( 'Parish Formation Dashboard', 'parish-formation' ),
			esc_html__( 'Dashboard', 'parish-formation' ),
			'pf_manage_courses',
			'parish-formation',
			array( self::class, 'render_dashboard' ),
			0
		);
	}

	/**
	 * Render the plugin dashboard.
	 *
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'pf_manage_courses' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'parish-formation' ) );
		}

		?>
		<?php $data = self::dashboard_data(); ?>
		<div class="wrap pf-dashboard"><div class="pf-dashboard-heading"><div><h1><?php esc_html_e( 'Parish Formation Dashboard', 'parish-formation' ); ?></h1><p><?php esc_html_e( 'Monitor participation, outstanding work, and recent formation activity.', 'parish-formation' ); ?></p></div><button id="pf-dashboard-refresh" class="button" type="button"><?php esc_html_e( 'Refresh Dashboard', 'parish-formation' ); ?></button></div><?php if ( current_user_can( 'pf_grade_assessments' ) && $data['attention']['pending_reviews'] ) : ?><div class="notice notice-warning inline"><p><strong><?php echo esc_html( sprintf( _n( '%d assessment is awaiting review.', '%d assessments are awaiting review.', $data['attention']['pending_reviews'], 'parish-formation' ), $data['attention']['pending_reviews'] ) ); ?></strong> <a href="<?php echo esc_url( self::review_queue_url() ); ?>"><?php esc_html_e( 'Open the review queue', 'parish-formation' ); ?></a></p></div><?php endif; ?><div id="pf-dashboard-status" aria-live="polite"></div><div id="pf-dashboard-content"><?php self::render_dashboard_content( $data ); ?></div></div>
		<?php
	}

	public static function register_wordpress_dashboard_widget() {
		if ( current_user_can( 'pf_grade_assessments' ) ) { wp_add_dashboard_widget( 'pf_pending_assessment_reviews', __( 'Formation Assessments Awaiting Review', 'parish-formation' ), array( self::class, 'render_wordpress_dashboard_widget' ) ); }
	}

	public static function render_wordpress_dashboard_widget() {
		$count = Parish_Formation_Assessment_Repository::get_pending_review_count();
		if ( ! $count ) { echo '<p>' . esc_html__( 'No assessment submissions are awaiting review.', 'parish-formation' ) . '</p>'; return; }
		echo '<p><strong class="pf-dashboard-review-count">' . esc_html( number_format_i18n( $count ) ) . '</strong> ' . esc_html( _n( 'assessment needs review.', 'assessments need review.', $count, 'parish-formation' ) ) . '</p><p><a class="button button-primary" href="' . esc_url( self::review_queue_url() ) . '">' . esc_html__( 'Review Assessments', 'parish-formation' ) . '</a></p>';
	}

	private static function review_queue_url() {
		return add_query_arg( array( 'page' => 'parish-formation-reports', 'hub_tab' => 'reviews', 'pf_review_status' => 'pending_review' ), admin_url( 'admin.php' ) );
	}

	public static function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_parish-formation' !== $hook_suffix ) { return; }
		wp_enqueue_style( 'pf-admin-dashboard', PARISH_FORMATION_PLUGIN_URL . 'assets/css/pf-admin-dashboard.css', array(), (string) filemtime( PARISH_FORMATION_PLUGIN_DIR . 'assets/css/pf-admin-dashboard.css' ) );
		wp_enqueue_script( 'pf-admin-dashboard', PARISH_FORMATION_PLUGIN_URL . 'assets/js/pf-admin-dashboard.js', array(), (string) filemtime( PARISH_FORMATION_PLUGIN_DIR . 'assets/js/pf-admin-dashboard.js' ), true );
		wp_localize_script( 'pf-admin-dashboard', 'pfAdminDashboard', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'pf_refresh_dashboard' ), 'errorMessage' => __( 'The dashboard could not be refreshed.', 'parish-formation' ), 'refreshingMessage' => __( 'Refreshing dashboard…', 'parish-formation' ) ) );
	}

	public static function ajax_refresh_dashboard() {
		if ( ! current_user_can( 'pf_manage_courses' ) ) { wp_send_json_error( array( 'message' => __( 'You do not have permission to view this dashboard.', 'parish-formation' ) ), 403 ); }
		check_ajax_referer( 'pf_refresh_dashboard', 'nonce' );
		ob_start(); self::render_dashboard_content( self::dashboard_data() ); $html = ob_get_clean();
		wp_send_json_success( array( 'html' => $html, 'refreshed' => wp_date( get_option( 'time_format' ) ) ) );
	}

	private static function dashboard_data() {
		global $wpdb;
		$enrollments = $wpdb->prefix . 'pf_enrollments';
		$attempts    = $wpdb->prefix . 'pf_assessment_attempts';
		$certificates = $wpdb->prefix . 'pf_certificates';
		$logs         = $wpdb->prefix . 'pf_notification_log';
		$now          = current_time( 'mysql', true );
		$soon         = gmdate( 'Y-m-d H:i:s', strtotime( $now . ' UTC +14 days' ) );
		$certificate_soon = gmdate( 'Y-m-d H:i:s', strtotime( $now . ' UTC +30 days' ) );
		$total = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$enrollments} WHERE status IN ('enrolled','in_progress','completed')" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$completed = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$enrollments} WHERE status = 'completed'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$data = array(
			'metrics' => array(
				'participants' => absint( $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$enrollments} WHERE status IN ('enrolled','in_progress')" ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'enrollments' => absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$enrollments} WHERE status IN ('enrolled','in_progress')" ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'courses' => absint( wp_count_posts( Parish_Formation_Course_Post_Type::POST_TYPE )->publish ?? 0 ),
				'completion_rate' => $total ? (int) round( ( $completed / $total ) * 100 ) : 0,
			),
			'attention' => array(
				'pending_reviews' => Parish_Formation_Assessment_Repository::get_pending_review_count(),
				'expiring_enrollments' => absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$enrollments} WHERE status IN ('enrolled','in_progress') AND expires_at IS NOT NULL AND expires_at >= %s AND expires_at <= %s", $now, $soon ) ) ),
				'failed_emails' => absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$logs} WHERE status = 'failed'" ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'expiring_certificates' => absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$certificates} WHERE status = 'issued' AND expires_at IS NOT NULL AND expires_at >= %s AND expires_at <= %s", $now, $certificate_soon ) ) ),
			),
			'activity' => self::recent_activity(),
		);
		return $data;
	}

	private static function recent_activity() {
		global $wpdb;
		$enrollments = $wpdb->prefix . 'pf_enrollments'; $attempts = $wpdb->prefix . 'pf_assessment_attempts'; $certificates = $wpdb->prefix . 'pf_certificates';
		$sql = "(SELECT 'enrollment' activity_type, e.id object_id, e.enrolled_at activity_at, u.display_name participant_name, p.post_title item_name FROM {$enrollments} e INNER JOIN {$wpdb->users} u ON u.ID=e.user_id INNER JOIN {$wpdb->posts} p ON p.ID=e.course_id) UNION ALL (SELECT 'completion', e.id, e.completed_at, u.display_name, p.post_title FROM {$enrollments} e INNER JOIN {$wpdb->users} u ON u.ID=e.user_id INNER JOIN {$wpdb->posts} p ON p.ID=e.course_id WHERE e.completed_at IS NOT NULL) UNION ALL (SELECT 'assessment', a.id, a.submitted_at, u.display_name, p.post_title FROM {$attempts} a INNER JOIN {$wpdb->users} u ON u.ID=a.user_id INNER JOIN {$wpdb->posts} p ON p.ID=a.assessment_id) UNION ALL (SELECT 'certificate', c.id, c.issued_at, u.display_name, p.post_title FROM {$certificates} c INNER JOIN {$wpdb->users} u ON u.ID=c.user_id INNER JOIN {$wpdb->posts} p ON p.ID=c.course_id) ORDER BY activity_at DESC LIMIT 8";
		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private static function render_dashboard_content( $data ) {
		$people_url = add_query_arg( array( 'page' => 'parish-formation-people', 'hub_tab' => 'participants' ), admin_url( 'admin.php' ) );
		$enrollment_url = add_query_arg( array( 'page' => 'parish-formation-people', 'hub_tab' => 'enrollments' ), admin_url( 'admin.php' ) );
		$review_url = add_query_arg( array( 'page' => 'parish-formation-reports', 'hub_tab' => 'reviews' ), admin_url( 'admin.php' ) );
		$email_url = add_query_arg( array( 'page' => 'parish-formation-settings', 'hub_tab' => 'emails', 'log_status' => 'failed' ), admin_url( 'admin.php' ) );
		$certificate_url = add_query_arg( array( 'page' => 'parish-formation-reports', 'hub_tab' => 'certificates', 'pf_status_filter' => 'active' ), admin_url( 'admin.php' ) );
		$metric_labels = array( 'participants' => __( 'Active Participants', 'parish-formation' ), 'enrollments' => __( 'Active Enrollments', 'parish-formation' ), 'courses' => __( 'Published Courses', 'parish-formation' ), 'completion_rate' => __( 'Completion Rate', 'parish-formation' ) );
		?><div class="pf-dashboard-metrics"><?php foreach ( $data['metrics'] as $key => $value ) : ?><div class="pf-dashboard-card"><span><?php echo esc_html( $metric_labels[ $key ] ); ?></span><strong><?php echo esc_html( number_format_i18n( $value ) . ( 'completion_rate' === $key ? '%' : '' ) ); ?></strong></div><?php endforeach; ?></div>
		<div class="pf-dashboard-columns"><section class="pf-dashboard-panel"><h2><?php esc_html_e( 'Needs Attention', 'parish-formation' ); ?></h2><div class="pf-dashboard-attention"><a href="<?php echo esc_url( $review_url ); ?>"><strong><?php echo esc_html( $data['attention']['pending_reviews'] ); ?></strong><span><?php esc_html_e( 'Assessments awaiting review', 'parish-formation' ); ?></span></a><a href="<?php echo esc_url( $enrollment_url ); ?>"><strong><?php echo esc_html( $data['attention']['expiring_enrollments'] ); ?></strong><span><?php esc_html_e( 'Enrollments expiring within 14 days', 'parish-formation' ); ?></span></a><?php if ( current_user_can( 'pf_manage_settings' ) ) : ?><a href="<?php echo esc_url( $email_url ); ?>"><strong><?php echo esc_html( $data['attention']['failed_emails'] ); ?></strong><span><?php esc_html_e( 'Failed email deliveries', 'parish-formation' ); ?></span></a><?php endif; ?><a href="<?php echo esc_url( $certificate_url ); ?>"><strong><?php echo esc_html( $data['attention']['expiring_certificates'] ); ?></strong><span><?php esc_html_e( 'Certificates expiring within 30 days', 'parish-formation' ); ?></span></a></div></section>
		<section class="pf-dashboard-panel"><h2><?php esc_html_e( 'Quick Actions', 'parish-formation' ); ?></h2><div class="pf-dashboard-actions"><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Parish_Formation_Course_Post_Type::POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Course', 'parish-formation' ); ?></a><a class="button" href="<?php echo esc_url( $enrollment_url ); ?>"><?php esc_html_e( 'Enroll Participant', 'parish-formation' ); ?></a><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'parish-formation-people', 'hub_tab' => 'invitations' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Create Invitation', 'parish-formation' ); ?></a><a class="button" href="<?php echo esc_url( $review_url ); ?>"><?php esc_html_e( 'Review Assessments', 'parish-formation' ); ?></a><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'parish-formation-reports', 'hub_tab' => 'courses' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Open Reports', 'parish-formation' ); ?></a><a class="button" href="<?php echo esc_url( $people_url ); ?>"><?php esc_html_e( 'Find Participant', 'parish-formation' ); ?></a></div></section></div>
		<section class="pf-dashboard-panel"><h2><?php esc_html_e( 'Recent Activity', 'parish-formation' ); ?></h2><?php if ( ! $data['activity'] ) : ?><p><?php esc_html_e( 'No formation activity has been recorded yet.', 'parish-formation' ); ?></p><?php else : ?><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Activity', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Participant', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Course or assessment', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Date', 'parish-formation' ); ?></th></tr></thead><tbody><?php $activity_labels = array( 'enrollment' => __( 'Enrolled', 'parish-formation' ), 'completion' => __( 'Completed course', 'parish-formation' ), 'assessment' => __( 'Submitted assessment', 'parish-formation' ), 'certificate' => __( 'Certificate issued', 'parish-formation' ) ); foreach ( $data['activity'] as $activity ) : ?><tr><td><?php echo esc_html( $activity_labels[ $activity->activity_type ] ?? $activity->activity_type ); ?></td><td><?php echo esc_html( $activity->participant_name ); ?></td><td><?php echo esc_html( $activity->item_name ); ?></td><td><?php echo esc_html( get_date_from_gmt( $activity->activity_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
		<section class="pf-dashboard-system"><span><?php esc_html_e( 'Plugin', 'parish-formation' ); ?> <strong><?php echo esc_html( PARISH_FORMATION_VERSION ); ?></strong></span><span><?php esc_html_e( 'Database', 'parish-formation' ); ?> <strong><?php echo esc_html( get_option( 'parish_formation_db_version', __( 'Not installed', 'parish-formation' ) ) ); ?></strong></span><span><?php esc_html_e( 'Email', 'parish-formation' ); ?> <strong><?php echo is_email( Parish_Formation_Notifications::settings()['from_email'] ) ? esc_html__( 'Configured', 'parish-formation' ) : esc_html__( 'Needs configuration', 'parish-formation' ); ?></strong></span></section><?php
	}
}
