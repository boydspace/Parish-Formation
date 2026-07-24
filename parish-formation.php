<?php
/**
 * Plugin Name:       Parish Formation
 * Description:       Provides focused online formation tools for parishes.
 * Version:           0.6.0
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

define( 'PARISH_FORMATION_VERSION', '0.6.0' );
define( 'PARISH_FORMATION_DB_VERSION', '0.6.0' );
define( 'PARISH_FORMATION_UIKIT_VERSION', '3.25.20' );
define( 'PARISH_FORMATION_PLUGIN_FILE', __FILE__ );
define( 'PARISH_FORMATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PARISH_FORMATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

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
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-shortcodes.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-progress-actions.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'public/class-pf-assessment-actions.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-admin.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-course-settings.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-enrollments-admin.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-lesson-settings.php';
require_once PARISH_FORMATION_PLUGIN_DIR . 'admin/class-pf-assessment-settings.php';

register_activation_hook(
	PARISH_FORMATION_PLUGIN_FILE,
	array( 'Parish_Formation_Upgrader', 'maybe_upgrade' )
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
	'plugins_loaded',
	array( 'Parish_Formation_Capabilities', 'maybe_install' )
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
	'admin_post_pf_create_enrollment',
	array( 'Parish_Formation_Enrollments_Admin', 'handle_create' )
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
