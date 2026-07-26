<?php
/** Registers reusable certificate designs. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Certificate design post type registration. */
final class Parish_Formation_Certificate_Design_Post_Type {
	public const POST_TYPE = 'pf_cert_design';

	/** Register the private administrative post type. */
	public static function register() {
		$capabilities = array_fill_keys(
			array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'delete_posts', 'publish_posts', 'read_private_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ),
			'pf_manage_settings'
		);
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name' => __( 'Certificate Designs', 'parish-formation' ),
					'singular_name' => __( 'Certificate Design', 'parish-formation' ),
					'menu_name' => __( 'Certificate Designs', 'parish-formation' ),
					'add_new_item' => __( 'Add Certificate Design', 'parish-formation' ),
					'edit_item' => __( 'Edit Certificate Design', 'parish-formation' ),
					'all_items' => __( 'Certificate Designs', 'parish-formation' ),
					'not_found' => __( 'No certificate designs found.', 'parish-formation' ),
				),
				'public' => false,
				'show_ui' => true,
				'show_in_menu' => 'parish-formation',
				'show_in_rest' => false,
				'capabilities' => $capabilities,
				'map_meta_cap' => false,
				'supports' => array( 'title' ),
			)
		);
	}
}
