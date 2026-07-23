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
		add_menu_page(
			esc_html__( 'Parish Formation', 'parish-formation' ),
			esc_html__( 'Parish Formation', 'parish-formation' ),
			'pf_manage_courses',
			'parish-formation',
			array( self::class, 'render_dashboard' ),
			'dashicons-welcome-learn-more',
			25
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

		$database_version = get_option( 'parish_formation_db_version', 'Not installed' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Parish Formation', 'parish-formation' ); ?></h1>
			<p><?php echo esc_html__( 'The Parish Formation plugin is active.', 'parish-formation' ); ?></p>
			<table class="widefat striped" style="max-width: 600px;">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Plugin version', 'parish-formation' ); ?></th>
						<td><?php echo esc_html( PARISH_FORMATION_VERSION ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Database version', 'parish-formation' ); ?></th>
						<td><?php echo esc_html( $database_version ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Status', 'parish-formation' ); ?></th>
						<td><?php echo esc_html__( 'Active', 'parish-formation' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}
