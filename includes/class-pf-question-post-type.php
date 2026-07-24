<?php
/**
 * Registers the Parish Formation question post type.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Question post type registration.
 */
final class Parish_Formation_Question_Post_Type {

	/**
	 * Question post type identifier.
	 */
	public const POST_TYPE = 'pf_question';

	/**
	 * Register the question post type.
	 *
	 * @return void
	 */
	public static function register() {
		$labels = array(
			'name'               => _x( 'Questions', 'Post type general name', 'parish-formation' ),
			'singular_name'      => _x( 'Question', 'Post type singular name', 'parish-formation' ),
			'menu_name'          => _x( 'Questions', 'Admin menu text', 'parish-formation' ),
			'name_admin_bar'     => _x( 'Question', 'Add New toolbar text', 'parish-formation' ),
			'add_new'            => __( 'Add New', 'parish-formation' ),
			'add_new_item'       => __( 'Add New Question', 'parish-formation' ),
			'edit_item'          => __( 'Edit Question', 'parish-formation' ),
			'all_items'          => __( 'All Questions', 'parish-formation' ),
			'search_items'       => __( 'Search Questions', 'parish-formation' ),
			'not_found'          => __( 'No questions found.', 'parish-formation' ),
			'not_found_in_trash' => __( 'No questions found in Trash.', 'parish-formation' ),
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
			'pf_manage_assessments'
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'capabilities'        => $capabilities,
				'map_meta_cap'        => false,
				'supports'            => array( 'title', 'editor' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'exclude_from_search' => true,
			)
		);
	}
}
