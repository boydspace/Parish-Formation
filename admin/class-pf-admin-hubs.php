<?php
/** Consolidated AJAX-loaded administration hubs. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Groups related administration screens behind a shorter menu. */
final class Parish_Formation_Admin_Hubs {
	public static function register_menu() {
		add_submenu_page( 'parish-formation', __( 'People', 'parish-formation' ), __( 'People', 'parish-formation' ), 'pf_manage_enrollments', 'parish-formation-people', array( self::class, 'render_people' ), 29 );
		add_submenu_page( 'parish-formation', __( 'Reports', 'parish-formation' ), __( 'Reports', 'parish-formation' ), 'pf_view_reports', 'parish-formation-reports', array( self::class, 'render_reports' ), 30 );
		add_submenu_page( 'parish-formation', __( 'Settings', 'parish-formation' ), __( 'Settings', 'parish-formation' ), 'pf_manage_settings', 'parish-formation-settings', array( self::class, 'render_settings' ), 31 );
	}

	public static function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'parish-formation_page_parish-formation-people', 'parish-formation_page_parish-formation-reports', 'parish-formation_page_parish-formation-settings' ), true ) ) { return; }
		wp_enqueue_script( 'pf-admin-hubs', PARISH_FORMATION_PLUGIN_URL . 'assets/js/pf-admin-hubs.js', array(), (string) filemtime( PARISH_FORMATION_PLUGIN_DIR . 'assets/js/pf-admin-hubs.js' ), true );
		wp_localize_script( 'pf-admin-hubs', 'pfAdminHubs', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'pf_load_admin_hub_tab' ), 'errorMessage' => __( 'This section could not be loaded. Please try again.', 'parish-formation' ) ) );
	}

	public static function render_people() { self::render_hub( 'people' ); }
	public static function render_reports() { self::render_hub( 'reports' ); }
	public static function render_settings() { self::render_hub( 'settings' ); }

	public static function redirect_legacy_pages() {
		if ( wp_doing_ajax() || 'GET' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) || empty( $_GET['page'] ) ) { return; }
		$page = sanitize_key( wp_unslash( $_GET['page'] ) );
		foreach ( self::configuration() as $hub_key => $hub ) {
			foreach ( $hub['tabs'] as $tab_key => $tab ) {
				if ( $page !== $tab['legacy_slug'] ) { continue; }
				$args = array();
				foreach ( wp_unslash( $_GET ) as $key => $value ) { if ( is_scalar( $value ) ) { $args[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value ); } }
				$args['page'] = $hub['slug']; $args['hub_tab'] = $tab_key;
				wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) ); exit;
			}
		}
	}

	public static function ajax_load_tab() {
		check_ajax_referer( 'pf_load_admin_hub_tab', 'nonce' );
		$hub = isset( $_GET['hub'] ) ? sanitize_key( wp_unslash( $_GET['hub'] ) ) : '';
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$config = self::configuration();
		if ( ! isset( $config[ $hub ]['tabs'][ $tab ] ) ) { wp_send_json_error( array( 'message' => __( 'Unknown administration section.', 'parish-formation' ) ), 404 ); }
		$definition = $config[ $hub ]['tabs'][ $tab ];
		if ( ! current_user_can( $definition['capability'] ) ) { wp_send_json_error( array( 'message' => __( 'You do not have permission to view this section.', 'parish-formation' ) ), 403 ); }
		$_GET['page'] = $definition['legacy_slug'];
		ob_start(); call_user_func( $definition['callback'] ); $html = ob_get_clean();
		wp_send_json_success( array( 'html' => $html, 'title' => $definition['label'] ) );
	}

	private static function render_hub( $hub ) {
		$config = self::configuration();
		if ( ! isset( $config[ $hub ] ) || ! current_user_can( $config[ $hub ]['capability'] ) ) { wp_die( esc_html__( 'You do not have permission to access this section.', 'parish-formation' ) ); }
		$requested = isset( $_GET['hub_tab'] ) ? sanitize_key( wp_unslash( $_GET['hub_tab'] ) ) : '';
		$tab = isset( $config[ $hub ]['tabs'][ $requested ] ) && current_user_can( $config[ $hub ]['tabs'][ $requested ]['capability'] ) ? $requested : self::first_allowed_tab( $config[ $hub ]['tabs'] );
		$definition = $config[ $hub ]['tabs'][ $tab];
		?>
		<div class="wrap pf-admin-hub" data-hub="<?php echo esc_attr( $hub ); ?>"><h1><?php echo esc_html( $config[ $hub ]['label'] ); ?></h1><nav class="nav-tab-wrapper" aria-label="<?php echo esc_attr( $config[ $hub ]['label'] ); ?>"><?php foreach ( $config[ $hub ]['tabs'] as $key => $item ) : if ( ! current_user_can( $item['capability'] ) ) { continue; } $url = add_query_arg( array( 'page' => $config[ $hub ]['slug'], 'hub_tab' => $key ), admin_url( 'admin.php' ) ); ?><a class="nav-tab pf-admin-hub-tab <?php echo $key === $tab ? 'nav-tab-active' : ''; ?>" data-tab="<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $item['label'] ); ?></a><?php endforeach; ?></nav><div class="pf-admin-hub-status" aria-live="polite"></div><div class="pf-admin-hub-content"><?php $_GET['page'] = $definition['legacy_slug']; call_user_func( $definition['callback'] ); ?></div></div>
		<?php
	}

	private static function first_allowed_tab( $tabs ) { foreach ( $tabs as $key => $tab ) { if ( current_user_can( $tab['capability'] ) ) { return $key; } } return array_key_first( $tabs ); }

	private static function configuration() {
		return array(
			'people' => array( 'label' => __( 'People', 'parish-formation' ), 'slug' => 'parish-formation-people', 'capability' => 'pf_manage_enrollments', 'tabs' => array(
				'participants' => array( 'label' => __( 'Participants', 'parish-formation' ), 'capability' => 'pf_manage_enrollments', 'legacy_slug' => 'parish-formation-participants', 'callback' => array( 'Parish_Formation_Participants_Admin', 'render_page' ) ),
				'enrollments' => array( 'label' => __( 'Enrollments', 'parish-formation' ), 'capability' => 'pf_manage_enrollments', 'legacy_slug' => 'parish-formation-enrollments', 'callback' => array( 'Parish_Formation_Enrollments_Admin', 'render_page' ) ),
				'invitations' => array( 'label' => __( 'Course Invitations', 'parish-formation' ), 'capability' => 'pf_manage_enrollments', 'legacy_slug' => 'parish-formation-invitations', 'callback' => array( 'Parish_Formation_Invitations_Admin', 'render_page' ) ),
			) ),
			'reports' => array( 'label' => __( 'Reports', 'parish-formation' ), 'slug' => 'parish-formation-reports', 'capability' => 'pf_view_reports', 'tabs' => array(
				'reviews' => array( 'label' => __( 'Assessment Reviews', 'parish-formation' ), 'capability' => 'pf_grade_assessments', 'legacy_slug' => 'parish-formation-assessment-reviews', 'callback' => array( 'Parish_Formation_Enrollments_Admin', 'render_reviews_page' ) ),
				'courses' => array( 'label' => __( 'Course Reports', 'parish-formation' ), 'capability' => 'pf_view_reports', 'legacy_slug' => 'parish-formation-course-reports', 'callback' => array( 'Parish_Formation_Enrollments_Admin', 'render_course_reports_page' ) ),
				'certificates' => array( 'label' => __( 'Certificates', 'parish-formation' ), 'capability' => 'pf_view_reports', 'legacy_slug' => 'parish-formation-certificates', 'callback' => array( 'Parish_Formation_Certificates_Admin', 'render_page' ) ),
			) ),
			'settings' => array( 'label' => __( 'Settings', 'parish-formation' ), 'slug' => 'parish-formation-settings', 'capability' => 'pf_manage_settings', 'tabs' => array(
				'accounts' => array( 'label' => __( 'Account Settings', 'parish-formation' ), 'capability' => 'pf_manage_settings', 'legacy_slug' => 'parish-formation-account-settings', 'callback' => array( 'Parish_Formation_Account_Settings', 'render_page' ) ),
				'emails' => array( 'label' => __( 'Email Notifications', 'parish-formation' ), 'capability' => 'pf_manage_settings', 'legacy_slug' => 'parish-formation-notifications', 'callback' => array( 'Parish_Formation_Notifications_Admin', 'render_page' ) ),
			) ),
		);
	}
}
