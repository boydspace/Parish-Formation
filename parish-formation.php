<?php
/**
 * Plugin Name:       Parish Formation
 * Description:       Provides focused online formation tools for parishes.
 * Version:           1.3.0
 * Requires PHP:      8.3
 * Author:            Father Andrew M. Boyd
 * Author URI:        https://fatherboyd.com
 * Plugin URI:        https://fatherboyd.com/plugins
 * License:           GPL-2.0-or-later
 * Text Domain:       parish-formation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PARISH_FORMATION_VERSION', '1.3.0' );
define( 'PARISH_FORMATION_DB_VERSION', '1.0.2' );
define( 'PARISH_FORMATION_UIKIT_VERSION', '3.25.20' );
define( 'PARISH_FORMATION_PLUGIN_FILE', __FILE__ );
define( 'PARISH_FORMATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PARISH_FORMATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( PARISH_FORMATION_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once PARISH_FORMATION_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-upgrader.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-capabilities.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-account-service.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-participant-note-repository.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-privacy.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-retention.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-multisite.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-assessment-post-type.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-question-post-type.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-question-block.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-course-repository.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-course-post-type.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-lesson-post-type.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-enrollment-repository.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-invitation-repository.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-progress-repository.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-assessment-repository.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-certificate-repository.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-certificate-design-post-type.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-notifications.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-shortcodes.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-account-shortcodes.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-account-actions.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-enrollment-actions.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-progress-actions.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-assessment-actions.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-certificate-actions.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-certificate-verification.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-admin.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-admin-hubs.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-account-settings.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-participants-admin.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-retention-settings.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-system-status.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-course-settings.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-enrollments-admin.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-invitations-admin.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-certificates-admin.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-certificate-design-settings.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-notifications-admin.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-lesson-settings.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-assessment-settings.php';

register_activation_hook( PARISH_FORMATION_PLUGIN_FILE, array( 'Parish_Formation_Multisite', 'activate' ) );
register_deactivation_hook( PARISH_FORMATION_PLUGIN_FILE, array( 'Parish_Formation_Multisite', 'deactivate' ) );
add_action( 'wp_initialize_site', array( 'Parish_Formation_Multisite', 'initialize_new_site' ), 200 );

add_action(
	'plugins_loaded',
	array( 'Parish_Formation_Upgrader', 'maybe_upgrade' )
);
add_filter( 'wp_privacy_personal_data_exporters', array( 'Parish_Formation_Privacy', 'register_exporter' ) );
add_filter( 'wp_privacy_personal_data_erasers', array( 'Parish_Formation_Privacy', 'register_eraser' ) );
add_action(
	'init',
	array( 'Parish_Formation_Notifications', 'schedule_events' )
);
add_action( 'init', array( 'Parish_Formation_Retention', 'schedule' ) );
add_action( Parish_Formation_Retention::CRON_HOOK, array( 'Parish_Formation_Retention', 'cleanup' ) );
add_action(
	'pf_daily_notification_events',
	array( 'Parish_Formation_Notifications', 'process_expiration_notifications' )
);
add_filter(
	'wp_new_user_notification_email',
	array( 'Parish_Formation_Notifications', 'filter_new_user_email' ),
	10,
	3
);
add_action(
	'wp_mail_succeeded',
	array( 'Parish_Formation_Notifications', 'log_wp_mail_succeeded' )
);
add_action(
	'wp_mail_failed',
	array( 'Parish_Formation_Notifications', 'log_wp_mail_failed' )
);

add_action(
	'plugins_loaded',
	array( 'Parish_Formation_Capabilities', 'maybe_install' )
);
add_action(
	'init',
	array( 'Parish_Formation_Certificate_Verification', 'register_routes' )
);
add_action(
	'init',
	array( 'Parish_Formation_Certificate_Design_Post_Type', 'register' )
);
add_action(
	'admin_init',
	array( 'Parish_Formation_Certificate_Design_Settings', 'migrate_existing_signatures' )
);
add_action(
	'template_redirect',
	array( 'Parish_Formation_Certificate_Verification', 'maybe_render' )
);

add_action(
	'admin_menu',
	array( 'Parish_Formation_Admin', 'register_menu' )
);

add_action(
	'admin_enqueue_scripts',
	array( 'Parish_Formation_Admin', 'enqueue_assets' )
);

add_action(
	'wp_ajax_pf_refresh_dashboard',
	array( 'Parish_Formation_Admin', 'ajax_refresh_dashboard' )
);

add_action(
	'admin_menu',
	array( 'Parish_Formation_Admin_Hubs', 'register_menu' ),
	20
);

add_action(
	'admin_enqueue_scripts',
	array( 'Parish_Formation_Admin_Hubs', 'enqueue_assets' )
);

add_action(
	'wp_ajax_pf_load_admin_hub_tab',
	array( 'Parish_Formation_Admin_Hubs', 'ajax_load_tab' )
);

add_action(
	'admin_init',
	array( 'Parish_Formation_Admin_Hubs', 'redirect_legacy_pages' )
);

add_action(
	'admin_menu',
	array( 'Parish_Formation_Account_Settings', 'register_menu' )
);

add_action(
	'admin_menu',
	array( 'Parish_Formation_Participants_Admin', 'register_menu' )
);
add_action( 'admin_menu', array( 'Parish_Formation_Retention_Settings', 'register_menu' ) );
add_action( 'admin_menu', array( 'Parish_Formation_System_Status', 'register_menu' ) );

add_action(
	'wp_login',
	array( 'Parish_Formation_Account_Service', 'record_login' ),
	10,
	2
);

add_filter(
	'show_admin_bar',
	static function ( $show ) { return current_user_can( 'manage_options' ) ? $show : false; }
);

add_filter(
	'login_url',
	array( 'Parish_Formation_Account_Shortcodes', 'filter_login_url' ),
	10,
	2
);

add_filter(
	'register_url',
	array( 'Parish_Formation_Account_Shortcodes', 'filter_register_url' )
);

add_filter(
	'lostpassword_url',
	array( 'Parish_Formation_Account_Shortcodes', 'filter_lostpassword_url' )
);

add_filter(
	'retrieve_password_message',
	array( 'Parish_Formation_Account_Actions', 'filter_password_reset_message' ),
	10,
	3
);

add_action(
	'login_init',
	array( 'Parish_Formation_Account_Actions', 'redirect_core_login' )
);

add_action(
	'admin_menu',
	array( 'Parish_Formation_Enrollments_Admin', 'register_menu' )
);

add_action(
	'admin_menu',
	array( 'Parish_Formation_Invitations_Admin', 'register_menu' )
);

add_action(
	'admin_menu',
	array( 'Parish_Formation_Certificates_Admin', 'register_menu' )
);

add_action(
	'admin_menu',
	array( 'Parish_Formation_Notifications_Admin', 'register_menu' )
);
add_action(
	'admin_enqueue_scripts',
	array( 'Parish_Formation_Notifications_Admin', 'enqueue_assets' )
);
add_action(
	'wp_ajax_pf_load_notification_template',
	array( 'Parish_Formation_Notifications_Admin', 'ajax_load_template' )
);

add_action(
	'admin_post_pf_create_enrollment',
	array( 'Parish_Formation_Enrollments_Admin', 'handle_create' )
);

add_action(
	'admin_post_pf_save_account_settings',
	array( 'Parish_Formation_Account_Settings', 'handle_save' )
);
add_action( 'admin_post_pf_save_retention_settings', array( 'Parish_Formation_Retention_Settings', 'handle_save' ) );
add_action( 'admin_post_pf_run_retention_cleanup', array( 'Parish_Formation_Retention_Settings', 'handle_cleanup' ) );

add_action(
	'admin_post_pf_update_participant',
	array( 'Parish_Formation_Participants_Admin', 'handle_update' )
);

add_action(
	'admin_post_pf_send_participant_password_reset',
	array( 'Parish_Formation_Participants_Admin', 'handle_password_reset' )
);

add_action( 'admin_post_pf_add_participant_note', array( 'Parish_Formation_Participants_Admin', 'handle_add_note' ) );
add_action( 'admin_post_pf_update_participant_note', array( 'Parish_Formation_Participants_Admin', 'handle_update_note' ) );
add_action( 'admin_post_pf_delete_participant_note', array( 'Parish_Formation_Participants_Admin', 'handle_delete_note' ) );
add_action( 'admin_post_pf_send_participant_reminder', array( 'Parish_Formation_Participants_Admin', 'handle_send_reminder' ) );

add_action(
	'admin_post_nopriv_pf_account_login',
	array( 'Parish_Formation_Account_Actions', 'login' )
);

add_action(
	'admin_post_pf_account_login',
	array( 'Parish_Formation_Account_Actions', 'login' )
);

add_action(
	'admin_post_pf_account_logout',
	array( 'Parish_Formation_Account_Actions', 'logout' )
);

add_action(
	'admin_post_nopriv_pf_account_reset_password',
	array( 'Parish_Formation_Account_Actions', 'reset_password' )
);

add_action(
	'admin_post_nopriv_pf_account_register',
	array( 'Parish_Formation_Account_Actions', 'register' )
);

add_action(
	'admin_post_nopriv_pf_request_passwordless_login',
	array( 'Parish_Formation_Account_Actions', 'request_passwordless' )
);

add_action(
	'admin_post_nopriv_pf_verify_passwordless_code',
	array( 'Parish_Formation_Account_Actions', 'verify_passwordless_code' )
);

add_action(
	'admin_post_nopriv_pf_passwordless_magic',
	array( 'Parish_Formation_Account_Actions', 'magic_login' )
);

add_action(
	'admin_post_pf_passwordless_magic',
	array( 'Parish_Formation_Account_Actions', 'magic_login' )
);

add_action(
	'wp_ajax_nopriv_pf_ajax_request_passwordless_login',
	array( 'Parish_Formation_Account_Actions', 'ajax_request_passwordless' )
);

add_action(
	'wp_ajax_pf_ajax_request_passwordless_login',
	array( 'Parish_Formation_Account_Actions', 'ajax_request_passwordless' )
);

add_action(
	'wp_ajax_nopriv_pf_ajax_verify_passwordless_code',
	array( 'Parish_Formation_Account_Actions', 'ajax_verify_passwordless_code' )
);

add_action(
	'wp_ajax_pf_ajax_verify_passwordless_code',
	array( 'Parish_Formation_Account_Actions', 'ajax_verify_passwordless_code' )
);

add_action(
	'admin_post_pf_create_invitation',
	array( 'Parish_Formation_Invitations_Admin', 'handle_create' )
);

add_action(
	'admin_post_pf_revoke_invitation',
	array( 'Parish_Formation_Invitations_Admin', 'handle_revoke' )
);

add_action(
	'admin_post_pf_resend_invitation',
	array( 'Parish_Formation_Invitations_Admin', 'handle_resend' )
);

add_action(
	'admin_post_pf_self_enroll',
	array( 'Parish_Formation_Enrollment_Actions', 'self_enroll' )
);

add_action(
	'admin_post_nopriv_pf_self_enroll',
	array( 'Parish_Formation_Enrollment_Actions', 'self_enroll' )
);

add_action(
	'admin_post_pf_access_code_enroll',
	array( 'Parish_Formation_Enrollment_Actions', 'access_code_enroll' )
);

add_action(
	'admin_post_nopriv_pf_access_code_enroll',
	array( 'Parish_Formation_Enrollment_Actions', 'access_code_enroll' )
);

add_action(
	'admin_post_pf_accept_invitation',
	array( 'Parish_Formation_Enrollment_Actions', 'invitation_enroll' )
);

add_action(
	'admin_post_nopriv_pf_accept_invitation',
	array( 'Parish_Formation_Enrollment_Actions', 'invitation_enroll' )
);

add_action(
	'admin_post_nopriv_pf_register_invitation',
	array( 'Parish_Formation_Enrollment_Actions', 'register_from_invitation' )
);

add_action(
	'admin_post_pf_unenroll_participant',
	array( 'Parish_Formation_Enrollments_Admin', 'handle_unenroll' )
);

add_action(
	'admin_post_pf_reset_enrollment',
	array( 'Parish_Formation_Enrollments_Admin', 'handle_reset' )
);

add_action(
	'admin_post_pf_override_completion',
	array( 'Parish_Formation_Enrollments_Admin', 'handle_completion_override' )
);

add_action(
	'admin_post_pf_review_assessment',
	array( 'Parish_Formation_Enrollments_Admin', 'handle_assessment_review' )
);
add_action(
	'admin_post_pf_export_assessment_reviews',
	array( 'Parish_Formation_Enrollments_Admin', 'handle_reviews_export' )
);
add_action(
	'admin_post_pf_export_course_reports',
	array( 'Parish_Formation_Enrollments_Admin', 'handle_course_reports_export' )
);
add_action(
	'admin_post_pf_issue_certificate',
	array( 'Parish_Formation_Enrollments_Admin', 'handle_issue_certificate' )
);
add_action(
	'admin_post_pf_revoke_certificate',
	array( 'Parish_Formation_Certificates_Admin', 'handle_revoke' )
);
add_action(
	'admin_post_pf_reissue_certificate',
	array( 'Parish_Formation_Certificates_Admin', 'handle_reissue' )
);
add_action(
	'admin_post_pf_export_certificates',
	array( 'Parish_Formation_Certificates_Admin', 'handle_export' )
);
add_action(
	'admin_post_pf_save_notification_settings',
	array( 'Parish_Formation_Notifications_Admin', 'handle_save' )
);
add_action(
	'admin_post_pf_send_test_notification',
	array( 'Parish_Formation_Notifications_Admin', 'handle_test' )
);
add_action(
	'admin_post_pf_save_notification_template',
	array( 'Parish_Formation_Notifications_Admin', 'handle_template_save' )
);
add_action(
	'admin_post_pf_reset_notification_template',
	array( 'Parish_Formation_Notifications_Admin', 'handle_template_reset' )
);
add_action(
	'admin_post_pf_test_notification_template',
	array( 'Parish_Formation_Notifications_Admin', 'handle_template_test' )
);
add_action(
	'admin_post_pf_save_notification_design',
	array( 'Parish_Formation_Notifications_Admin', 'handle_design_save' )
);
add_action(
	'admin_post_pf_retry_notification',
	array( 'Parish_Formation_Notifications_Admin', 'handle_retry' )
);
add_action(
	'admin_post_pf_download_certificate',
	array( 'Parish_Formation_Certificate_Actions', 'download_pdf' )
);
add_action(
	'admin_post_pf_public_certificate_pdf',
	array( 'Parish_Formation_Certificate_Actions', 'public_pdf' )
);
add_action(
	'admin_post_nopriv_pf_public_certificate_pdf',
	array( 'Parish_Formation_Certificate_Actions', 'public_pdf' )
);

add_action(
	'wp_ajax_pf_save_curriculum_order',
	array( 'Parish_Formation_Course_Settings', 'save_curriculum_order' )
);

add_action(
	'admin_post_pf_complete_lesson',
	array( 'Parish_Formation_Progress_Actions', 'complete_lesson' )
);

add_action(
	'admin_post_pf_submit_assessment',
	array( 'Parish_Formation_Assessment_Actions', 'submit' )
);

add_action(
	'init',
	array( 'Parish_Formation_Course_Post_Type', 'register' )
);

add_action(
	'init',
	array( 'Parish_Formation_Assessment_Post_Type', 'register' )
);

add_action(
	'init',
	array( 'Parish_Formation_Question_Post_Type', 'register' )
);

add_action(
	'init',
	array( 'Parish_Formation_Question_Block', 'register' )
);

add_action(
	'init',
	array( 'Parish_Formation_Lesson_Post_Type', 'register' )
);

add_action(
	'init',
	array( 'Parish_Formation_Shortcodes', 'register' )
);

add_action(
	'init',
	array( 'Parish_Formation_Account_Shortcodes', 'register' )
);

add_action(
	'wp_enqueue_scripts',
	array( 'Parish_Formation_Account_Shortcodes', 'enqueue_assets' )
);

add_action(
	'rest_api_init',
	array( 'Parish_Formation_Assessment_Actions', 'register_rest_route' )
);

add_action(
	'rest_api_init',
	array( 'Parish_Formation_Enrollment_Actions', 'register_rest_route' )
);

add_action(
	'rest_api_init',
	array( 'Parish_Formation_Shortcodes', 'register_rest_route' )
);

add_action(
	'rest_api_init',
	array( 'Parish_Formation_Progress_Actions', 'register_rest_route' )
);

add_action(
	'wp_enqueue_scripts',
	array( 'Parish_Formation_Shortcodes', 'enqueue_assets' )
);

add_action(
	'add_meta_boxes',
	array( 'Parish_Formation_Lesson_Settings', 'register_meta_box' )
);

add_action(
	'add_meta_boxes',
	array( 'Parish_Formation_Course_Settings', 'register_meta_box' )
);

add_action(
	'add_meta_boxes',
	array( 'Parish_Formation_Assessment_Settings', 'register_meta_box' )
);
add_action(
	'add_meta_boxes',
	array( 'Parish_Formation_Certificate_Design_Settings', 'register_meta_box' )
);

add_action(
	'save_post_pf_lesson',
	array( 'Parish_Formation_Lesson_Settings', 'save' )
);

add_action(
	'save_post_pf_course',
	array( 'Parish_Formation_Course_Settings', 'save' )
);

add_action(
	'save_post_pf_assessment',
	array( 'Parish_Formation_Assessment_Settings', 'save' )
);
add_action(
	'save_post_pf_cert_design',
	array( 'Parish_Formation_Certificate_Design_Settings', 'save' )
);

add_action(
	'save_post_pf_assessment',
	array( 'Parish_Formation_Question_Block', 'synchronize' ),
	20,
	2
);

add_filter(
	'manage_pf_lesson_posts_columns',
	array( 'Parish_Formation_Lesson_Settings', 'add_list_columns' )
);

add_filter(
	'manage_pf_assessment_posts_columns',
	array( 'Parish_Formation_Assessment_Settings', 'add_list_columns' )
);

add_action(
	'manage_pf_assessment_posts_custom_column',
	array( 'Parish_Formation_Assessment_Settings', 'render_list_column' ),
	10,
	2
);

add_action(
	'manage_pf_lesson_posts_custom_column',
	array( 'Parish_Formation_Lesson_Settings', 'render_list_column' ),
	10,
	2
);

add_action(
	'quick_edit_custom_box',
	array( 'Parish_Formation_Lesson_Settings', 'render_quick_edit_fields' ),
	10,
	2
);

add_action(
	'quick_edit_custom_box',
	array( 'Parish_Formation_Assessment_Settings', 'render_quick_edit_fields' ),
	10,
	2
);

add_action(
	'admin_enqueue_scripts',
	array( 'Parish_Formation_Lesson_Settings', 'enqueue_quick_edit_script' )
);

add_action(
	'admin_enqueue_scripts',
	array( 'Parish_Formation_Assessment_Settings', 'enqueue_quick_edit_script' )
);

add_action(
	'admin_enqueue_scripts',
	array( 'Parish_Formation_Course_Settings', 'enqueue_curriculum_assets' )
);
add_action(
	'admin_enqueue_scripts',
	array( 'Parish_Formation_Certificate_Design_Settings', 'enqueue_assets' )
);
