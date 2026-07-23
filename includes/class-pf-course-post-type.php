<?php
/**
 * Registers the Parish Formation course post type.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Course post type registration.
 */
final class Parish_Formation_Course_Post_Type {

	/**
	 * Course post type identifier.
	 */
	public const POST_TYPE = 'pf_course';

	/**
	 * Register the course post type.
	 *
	 * @return void
	 */
	public static function register() {
		$labels = array(
			'name'                  => _x( 'Courses', 'Post type general name', 'parish-formation' ),
			'singular_name'         => _x( 'Course', 'Post type singular name', 'parish-formation' ),
			'menu_name'             => _x( 'Courses', 'Admin menu text', 'parish-formation' ),
			'name_admin_bar'        => _x( 'Course', 'Add New toolbar text', 'parish-formation' ),
			'add_new'               => __( 'Add New', 'parish-formation' ),
			'add_new_item'          => __( 'Add New Course', 'parish-formation' ),
			'new_item'              => __( 'New Course', 'parish-formation' ),
			'edit_item'             => __( 'Edit Course', 'parish-formation' ),
			'view_item'             => __( 'View Course', 'parish-formation' ),
			'all_items'             => __( 'All Courses', 'parish-formation' ),
			'search_items'          => __( 'Search Courses', 'parish-formation' ),
			'not_found'             => __( 'No courses found.', 'parish-formation' ),
			'not_found_in_trash'    => __( 'No courses found in Trash.', 'parish-formation' ),
			'item_published'        => __( 'Course published.', 'parish-formation' ),
			'item_updated'          => __( 'Course updated.', 'parish-formation' ),
			'item_trashed'          => __( 'Course moved to the Trash.', 'parish-formation' ),
			'item_reverted_to_draft' => __( 'Course reverted to draft.', 'parish-formation' ),
		);

		$capabilities = array_fill_keys(
			array(
				'edit_post',
				'read_post',
				'delete_post',
				'edit_posts',
				'edit_others_posts',
				'delete_posts',
				'publish_posts',
				'read_private_posts',
				'delete_private_posts',
				'delete_published_posts',
				'delete_others_posts',
				'edit_private_posts',
				'edit_published_posts',
				'create_posts',
			),
			'pf_manage_courses'
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => 'parish-formation',
				'show_in_rest'        => true,
				'capabilities'        => $capabilities,
				'map_meta_cap'        => false,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'exclude_from_search' => true,
				'menu_icon'           => 'dashicons-welcome-learn-more',
			)
		);
	}
}
