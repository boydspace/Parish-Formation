<?php
/**
 * Plugin Name:       Parish Formation
 * Description:       Provides focused online formation tools for parishes.
 * Version:           0.9.0
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

define( 'PARISH_FORMATION_VERSION', '0.9.0' );
define( 'PARISH_FORMATION_DB_VERSION', '0.9.2' );
define( 'PARISH_FORMATION_UIKIT_VERSION', '3.25.20' );
define( 'PARISH_FORMATION_PLUGIN_FILE', __FILE__ );
define( 'PARISH_FORMATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PARISH_FORMATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( PARISH_FORMATION_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once PARISH_FORMATION_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-upgrader.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-capabilities.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-assessment-post-type.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-question-post-type.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-question-block.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-course-repository.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-course-post-type.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-lesson-post-type.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-enrollment-repository.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-progress-repository.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-assessment-repository.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-certificate-repository.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'includes/class-pf-notifications.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-shortcodes.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-enrollment-actions.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-progress-actions.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-assessment-actions.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-certificate-actions.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-certificate-verification.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-admin.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-course-settings.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-enrollments-admin.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-certificates-admin.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-notifications-admin.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-lesson-settings.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-assessment-settings.php';

register_activation_hook(
	PARISH_FORMATION_PLUGIN_FILE,
	array( 'Parish_Formation_Upgrader', 'maybe_upgrade' )
);
register_deactivation_hook(
	PARISH_FORMATION_PLUGIN_FILE,
	array( 'Parish_Formation_Notifications', 'clear_scheduled_events' )
);

register_activation_hook(
	PARISH_FORMATION_PLUGIN_FILE,
	array( 'Parish_Formation_Capabilities', 'maybe_install' )
);

add_action(
	'plugins_loaded',
	array( 'Parish_Formation_Upgrader', 'maybe_upgrade' )
);
add_action(
	'init',
	array( 'Parish_Formation_Notifications', 'schedule_events' )
);
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
	'template_redirect',
	array( 'Parish_Formation_Certificate_Verification', 'maybe_render' )
);

add_action(
	'admin_menu',
	array( 'Parish_Formation_Admin', 'register_menu' )
);

add_action(
	'admin_menu',
	array( 'Parish_Formation_Enrollments_Admin', 'register_menu' )
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
