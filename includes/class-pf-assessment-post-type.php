<?php
/**
 * Registers the Parish Formation assessment post type.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assessment post type registration.
 */
final class Parish_Formation_Assessment_Post_Type {

	/**
	 * Assessment post type identifier.
	 */
	public const POST_TYPE = 'pf_assessment';

	/**
	 * Register the assessment post type.
	 *
	 * @return void
	 */
	public static function register() {
		$labels = array(
			'name'                   => _x( 'Assessments', 'Post type general name', 'parish-formation' ),
			'singular_name'          => _x( 'Assessment', 'Post type singular name', 'parish-formation' ),
			'menu_name'              => _x( 'Assessments', 'Admin menu text', 'parish-formation' ),
			'name_admin_bar'         => _x( 'Assessment', 'Add New toolbar text', 'parish-formation' ),
			'add_new'                => __( 'Add New', 'parish-formation' ),
			'add_new_item'           => __( 'Add New Assessment', 'parish-formation' ),
			'new_item'               => __( 'New Assessment', 'parish-formation' ),
			'edit_item'              => __( 'Edit Assessment', 'parish-formation' ),
			'all_items'              => __( 'All Assessments', 'parish-formation' ),
			'search_items'           => __( 'Search Assessments', 'parish-formation' ),
			'not_found'              => __( 'No assessments found.', 'parish-formation' ),
			'not_found_in_trash'     => __( 'No assessments found in Trash.', 'parish-formation' ),
			'item_published'         => __( 'Assessment published.', 'parish-formation' ),
			'item_updated'           => __( 'Assessment updated.', 'parish-formation' ),
			'item_trashed'           => __( 'Assessment moved to the Trash.', 'parish-formation' ),
			'item_reverted_to_draft' => __( 'Assessment reverted to draft.', 'parish-formation' ),
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
				'show_ui'             => true,
				'show_in_menu'        => 'parish-formation',
				'show_in_rest'        => true,
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
