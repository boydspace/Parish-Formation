<?php
/**
 * Registers the Parish Formation lesson post type.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lesson post type registration.
 */
final class Parish_Formation_Lesson_Post_Type {

	/**
	 * Lesson post type identifier.
	 */
	public const POST_TYPE = 'pf_lesson';

	/**
	 * Register the lesson post type.
	 *
	 * @return void
	 */
	public static function register() {
		$labels = array(
			'name'                   => _x( 'Lessons', 'Post type general name', 'parish-formation' ),
			'singular_name'          => _x( 'Lesson', 'Post type singular name', 'parish-formation' ),
			'menu_name'              => _x( 'Lessons', 'Admin menu text', 'parish-formation' ),
			'name_admin_bar'         => _x( 'Lesson', 'Add New toolbar text', 'parish-formation' ),
			'add_new'                => __( 'Add New', 'parish-formation' ),
			'add_new_item'           => __( 'Add New Lesson', 'parish-formation' ),
			'new_item'               => __( 'New Lesson', 'parish-formation' ),
			'edit_item'              => __( 'Edit Lesson', 'parish-formation' ),
			'view_item'              => __( 'View Lesson', 'parish-formation' ),
			'all_items'              => __( 'All Lessons', 'parish-formation' ),
			'search_items'           => __( 'Search Lessons', 'parish-formation' ),
			'not_found'              => __( 'No lessons found.', 'parish-formation' ),
			'not_found_in_trash'     => __( 'No lessons found in Trash.', 'parish-formation' ),
			'item_published'         => __( 'Lesson published.', 'parish-formation' ),
			'item_updated'           => __( 'Lesson updated.', 'parish-formation' ),
			'item_trashed'           => __( 'Lesson moved to the Trash.', 'parish-formation' ),
			'item_reverted_to_draft' => __( 'Lesson reverted to draft.', 'parish-formation' ),
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
				'menu_icon'           => 'dashicons-media-document',
			)
		);
	}
}
