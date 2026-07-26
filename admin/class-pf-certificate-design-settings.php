<?php
/** Administrative fields for reusable certificate designs. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Certificate design settings and preview. */
final class Parish_Formation_Certificate_Design_Settings {
	public const TITLE_META_KEY = '_pf_design_certificate_title';
	public const ISSUER_META_KEY = '_pf_design_issuer';
	public const SIGNATORY_NAME_META_KEY = '_pf_design_signatory_name';
	public const SIGNATORY_TITLE_META_KEY = '_pf_design_signatory_title';
	public const LOGO_ID_META_KEY = '_pf_design_logo_id';
	public const LOGO_WIDTH_META_KEY = '_pf_design_logo_width';
	public const SIGNATURE_ID_META_KEY = '_pf_design_signature_id';
	public const SIGNATURE_DATA_META_KEY = '_pf_design_signature_data';
	public const HEADING_META_KEY = '_pf_design_heading';
	public const COMPLETION_TEXT_META_KEY = '_pf_design_completion_text';
	public const ACCENT_COLOR_META_KEY = '_pf_design_accent_color';
	public const BORDER_COLOR_META_KEY = '_pf_design_border_color';
	public const ORIENTATION_META_KEY = '_pf_design_orientation';
	private const NONCE_ACTION = 'pf_save_certificate_design';
	private const NONCE_NAME = 'pf_certificate_design_nonce';

	/** Register the design editor. */
	public static function register_meta_box() {
		add_meta_box( 'pf-certificate-design', __( 'Certificate Appearance', 'parish-formation' ), array( self::class, 'render_meta_box' ), Parish_Formation_Certificate_Design_Post_Type::POST_TYPE, 'normal', 'high' );
	}

	/** Render design fields and the live preview. */
	public static function render_meta_box( $post ) {
		$values = self::get_values( $post->ID );
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div class="pf-certificate-branding-fields">
			<p class="description"><?php esc_html_e( 'This reusable design can be assigned to any number of courses. Changes affect future certificates; issued certificates will retain their saved design snapshot.', 'parish-formation' ); ?></p>
			<div class="pf-certificate-design-grid">
				<div>
					<p><label for="pf-certificate-title"><strong><?php esc_html_e( 'Certificate title', 'parish-formation' ); ?></strong></label><br><input id="pf-certificate-title" name="pf_certificate_title" class="widefat" value="<?php echo esc_attr( $values['title'] ); ?>" placeholder="<?php esc_attr_e( 'Certificate of Completion', 'parish-formation' ); ?>"></p>
					<p><label for="pf-certificate-issuer"><strong><?php esc_html_e( 'Issuing organization', 'parish-formation' ); ?></strong></label><br><input id="pf-certificate-issuer" name="pf_certificate_issuer" class="widefat" value="<?php echo esc_attr( $values['issuer'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></p>
					<p><label for="pf-certificate-heading"><strong><?php esc_html_e( 'Certification heading', 'parish-formation' ); ?></strong></label><br><input id="pf-certificate-heading" name="pf_certificate_heading" class="widefat" value="<?php echo esc_attr( $values['heading'] ); ?>" placeholder="<?php esc_attr_e( 'This certifies that', 'parish-formation' ); ?>"></p>
					<p><label for="pf-certificate-completion-text"><strong><?php esc_html_e( 'Completion text', 'parish-formation' ); ?></strong></label><br><input id="pf-certificate-completion-text" name="pf_certificate_completion_text" class="widefat" value="<?php echo esc_attr( $values['completion_text'] ); ?>" placeholder="<?php esc_attr_e( 'has successfully completed', 'parish-formation' ); ?>"></p>
					<p><label for="pf-certificate-signatory-name"><strong><?php esc_html_e( 'Signatory name', 'parish-formation' ); ?></strong></label><br><input id="pf-certificate-signatory-name" name="pf_certificate_signatory_name" class="widefat" value="<?php echo esc_attr( $values['signatory_name'] ); ?>"></p>
					<p><label for="pf-certificate-signatory-title"><strong><?php esc_html_e( 'Signatory title', 'parish-formation' ); ?></strong></label><br><input id="pf-certificate-signatory-title" name="pf_certificate_signatory_title" class="widefat" value="<?php echo esc_attr( $values['signatory_title'] ); ?>"></p>
					<?php self::render_media_field( 'logo', __( 'Parish logo or seal', 'parish-formation' ), $values['logo_id'], $values['logo_url'] ); ?>
					<p><label for="pf-certificate-logo-width"><strong><?php esc_html_e( 'Logo width', 'parish-formation' ); ?></strong></label><br><input id="pf-certificate-logo-width" name="pf_certificate_logo_width" type="range" min="60" max="320" step="10" value="<?php echo esc_attr( $values['logo_width'] ); ?>"> <output id="pf-certificate-logo-width-output"><?php echo esc_html( $values['logo_width'] ); ?> px</output></p>
					<?php self::render_signature_field( $values ); ?>
					<p><label for="pf-certificate-accent-color"><strong><?php esc_html_e( 'Accent color', 'parish-formation' ); ?></strong></label><br><input id="pf-certificate-accent-color" name="pf_certificate_accent_color" type="color" value="<?php echo esc_attr( $values['accent_color'] ); ?>"></p>
					<p><label for="pf-certificate-border-color"><strong><?php esc_html_e( 'Border color', 'parish-formation' ); ?></strong></label><br><input id="pf-certificate-border-color" name="pf_certificate_border_color" type="color" value="<?php echo esc_attr( $values['border_color'] ); ?>"></p>
					<p><label for="pf-certificate-orientation"><strong><?php esc_html_e( 'Paper orientation', 'parish-formation' ); ?></strong></label><br><select id="pf-certificate-orientation" name="pf_certificate_orientation"><option value="landscape" <?php selected( $values['orientation'], 'landscape' ); ?>><?php esc_html_e( 'Landscape Letter', 'parish-formation' ); ?></option><option value="portrait" <?php selected( $values['orientation'], 'portrait' ); ?>><?php esc_html_e( 'Portrait Letter', 'parish-formation' ); ?></option></select></p>
				</div>
				<div><h3><?php esc_html_e( 'Live Preview', 'parish-formation' ); ?></h3><?php self::render_preview( $values ); ?></div>
			</div>
		</div>
		<?php
	}

	/** Save a design. */
	public static function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'pf_manage_settings' ) ) {
			return;
		}
		$text_fields = array( self::TITLE_META_KEY => 'pf_certificate_title', self::ISSUER_META_KEY => 'pf_certificate_issuer', self::HEADING_META_KEY => 'pf_certificate_heading', self::COMPLETION_TEXT_META_KEY => 'pf_certificate_completion_text', self::SIGNATORY_NAME_META_KEY => 'pf_certificate_signatory_name', self::SIGNATORY_TITLE_META_KEY => 'pf_certificate_signatory_title' );
		foreach ( $text_fields as $meta_key => $field ) {
			$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			$value ? update_post_meta( $post_id, $meta_key, $value ) : delete_post_meta( $post_id, $meta_key );
		}
		$logo_id = isset( $_POST['pf_certificate_logo_id'] ) ? absint( $_POST['pf_certificate_logo_id'] ) : 0;
		$logo_id && wp_attachment_is_image( $logo_id ) ? update_post_meta( $post_id, self::LOGO_ID_META_KEY, $logo_id ) : delete_post_meta( $post_id, self::LOGO_ID_META_KEY );
		$logo_width = min( 320, max( 60, absint( $_POST['pf_certificate_logo_width'] ?? 140 ) ) );
		update_post_meta( $post_id, self::LOGO_WIDTH_META_KEY, $logo_width );
		$signature_id = isset( $_POST['pf_certificate_signature_id'] ) ? absint( $_POST['pf_certificate_signature_id'] ) : 0;
		$remove_signature = ! empty( $_POST['pf_certificate_signature_remove'] );
		if ( $signature_id && wp_attachment_is_image( $signature_id ) ) {
			$signature_data = self::make_private_signature( $signature_id );
			if ( $signature_data ) {
				update_post_meta( $post_id, self::SIGNATURE_DATA_META_KEY, $signature_data );
				delete_post_meta( $post_id, self::SIGNATURE_ID_META_KEY );
			}
		} elseif ( $remove_signature ) {
			delete_post_meta( $post_id, self::SIGNATURE_DATA_META_KEY );
			delete_post_meta( $post_id, self::SIGNATURE_ID_META_KEY );
		}
		update_post_meta( $post_id, self::ACCENT_COLOR_META_KEY, sanitize_hex_color( wp_unslash( $_POST['pf_certificate_accent_color'] ?? '' ) ) ?: '#1c5b8f' );
		update_post_meta( $post_id, self::BORDER_COLOR_META_KEY, sanitize_hex_color( wp_unslash( $_POST['pf_certificate_border_color'] ?? '' ) ) ?: '#b58d18' );
		$orientation = sanitize_key( wp_unslash( $_POST['pf_certificate_orientation'] ?? 'landscape' ) );
		update_post_meta( $post_id, self::ORIENTATION_META_KEY, in_array( $orientation, array( 'landscape', 'portrait' ), true ) ? $orientation : 'landscape' );
	}

	/** Load media and preview assets on design editors. */
	public static function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) { return; }
		$screen = get_current_screen();
		if ( ! $screen || Parish_Formation_Certificate_Design_Post_Type::POST_TYPE !== $screen->post_type ) { return; }
		wp_enqueue_media();
		wp_enqueue_script( 'parish-formation-certificate-branding', PARISH_FORMATION_PLUGIN_URL . 'assets/js/certificate-branding.js', array(), PARISH_FORMATION_VERSION, true );
		wp_enqueue_style( 'parish-formation-certificate-branding', PARISH_FORMATION_PLUGIN_URL . 'assets/css/certificate-branding-admin.css', array(), PARISH_FORMATION_VERSION );
	}

	/** Return normalized values for a design. */
	public static function get_values( $design_id ) {
		$get = static function ( $key ) use ( $design_id ) { return sanitize_text_field( get_post_meta( $design_id, $key, true ) ); };
		$logo_id = absint( get_post_meta( $design_id, self::LOGO_ID_META_KEY, true ) );
		$logo_width = min( 320, max( 60, absint( get_post_meta( $design_id, self::LOGO_WIDTH_META_KEY, true ) ) ?: 140 ) );
		$signature_id = absint( get_post_meta( $design_id, self::SIGNATURE_ID_META_KEY, true ) );
		$signature_data = self::sanitize_signature_data( get_post_meta( $design_id, self::SIGNATURE_DATA_META_KEY, true ) );
		$orientation = $get( self::ORIENTATION_META_KEY );
		return array( 'title' => $get( self::TITLE_META_KEY ), 'issuer' => $get( self::ISSUER_META_KEY ), 'heading' => $get( self::HEADING_META_KEY ), 'completion_text' => $get( self::COMPLETION_TEXT_META_KEY ), 'signatory_name' => $get( self::SIGNATORY_NAME_META_KEY ), 'signatory_title' => $get( self::SIGNATORY_TITLE_META_KEY ), 'logo_id' => $logo_id, 'logo_url' => $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '', 'logo_width' => $logo_width, 'signature_id' => $signature_id, 'signature_data' => $signature_data, 'signature_url' => '', 'accent_color' => sanitize_hex_color( get_post_meta( $design_id, self::ACCENT_COLOR_META_KEY, true ) ) ?: '#1c5b8f', 'border_color' => sanitize_hex_color( get_post_meta( $design_id, self::BORDER_COLOR_META_KEY, true ) ) ?: '#b58d18', 'orientation' => in_array( $orientation, array( 'landscape', 'portrait' ), true ) ? $orientation : 'landscape' );
	}

	/** Move legacy Media Library signatures into private database storage. */
	public static function migrate_existing_signatures() {
		if ( '1' === get_option( 'parish_formation_private_signatures_102', '0' ) ) { return; }
		$designs = get_posts( array( 'post_type' => Parish_Formation_Certificate_Design_Post_Type::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$complete = true;
		foreach ( $designs as $design_id ) {
			$attachment_id = absint( get_post_meta( $design_id, self::SIGNATURE_ID_META_KEY, true ) );
			$data = self::sanitize_signature_data( get_post_meta( $design_id, self::SIGNATURE_DATA_META_KEY, true ) );
			if ( ! $data && $attachment_id ) { $data = self::make_private_signature( $attachment_id ); }
			if ( ! $data ) {
				if ( $attachment_id ) { $complete = false; }
				continue;
			}
			update_post_meta( $design_id, self::SIGNATURE_DATA_META_KEY, $data );
			delete_post_meta( $design_id, self::SIGNATURE_ID_META_KEY );
			self::add_signature_to_existing_snapshots( $design_id, $attachment_id, $data );
		}
		if ( $complete ) { update_option( 'parish_formation_private_signatures_102', '1', false ); }
	}

	private static function render_media_field( $type, $label, $id, $url ) {
		?><p><strong><?php echo esc_html( $label ); ?></strong></p><div class="pf-certificate-media-field" data-media-title="<?php echo esc_attr( $label ); ?>"><input type="hidden" id="pf-certificate-<?php echo esc_attr( $type ); ?>-id" name="pf_certificate_<?php echo esc_attr( $type ); ?>_id" value="<?php echo esc_attr( $id ); ?>"><div class="pf-certificate-media-preview" data-placeholder="<?php esc_attr_e( 'No image selected', 'parish-formation' ); ?>"><?php echo $url ? '<img src="' . esc_url( $url ) . '" alt="">' : esc_html__( 'No image selected', 'parish-formation' ); ?></div><p><button type="button" class="button pf-certificate-select-media"><?php esc_html_e( 'Select Image', 'parish-formation' ); ?></button> <button type="button" class="button-link-delete pf-certificate-remove-media"<?php echo $id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'parish-formation' ); ?></button></p></div><?php
	}

	private static function render_signature_field( $values ) {
		$has_signature = ! empty( $values['signature_data'] ) || ! empty( $values['signature_id'] );
		?><p><strong><?php esc_html_e( 'Private signature image', 'parish-formation' ); ?></strong></p><div class="pf-certificate-media-field" data-media-title="<?php esc_attr_e( 'Private signature image', 'parish-formation' ); ?>"><input type="hidden" id="pf-certificate-signature-id" name="pf_certificate_signature_id" value=""><input type="hidden" class="pf-certificate-signature-remove" name="pf_certificate_signature_remove" value="0"><div class="pf-certificate-media-preview" data-placeholder="<?php esc_attr_e( 'No private signature saved', 'parish-formation' ); ?>"><?php echo $has_signature ? esc_html__( 'Private signature saved. It will appear only in generated PDFs.', 'parish-formation' ) : esc_html__( 'No private signature saved', 'parish-formation' ); ?></div><p><button type="button" class="button pf-certificate-select-media"><?php esc_html_e( 'Select New Signature', 'parish-formation' ); ?></button> <button type="button" class="button-link-delete pf-certificate-remove-media"<?php echo $has_signature ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'parish-formation' ); ?></button></p><p class="description"><?php esc_html_e( 'The selected Media Library file is reduced, stored privately, and deleted from public uploads when this design is saved.', 'parish-formation' ); ?></p></div><?php
	}

	/** Create a reduced private data URI and delete the public Media Library source. */
	private static function make_private_signature( $attachment_id ) {
		$path = get_attached_file( $attachment_id );
		$mime = get_post_mime_type( $attachment_id );
		if ( ! $path || ! is_readable( $path ) || ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) { return ''; }
		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) { return ''; }
		$editor->resize( 600, 240, false );
		$temp = tempnam( get_temp_dir(), 'pf-signature-' );
		if ( false === $temp ) { return ''; }
		$saved = $editor->save( $temp, 'image/png' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_readable( $saved['path'] ) ) { @unlink( $temp ); return ''; }
		$bytes = file_get_contents( $saved['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		@unlink( $saved['path'] );
		if ( false === $bytes ) { return ''; }
		$data = 'data:image/png;base64,' . base64_encode( $bytes );
		wp_delete_attachment( $attachment_id, true );
		return $data;
	}

	private static function sanitize_signature_data( $data ) {
		return is_string( $data ) && preg_match( '#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $data ) ? $data : '';
	}

	private static function add_signature_to_existing_snapshots( $design_id, $attachment_id, $data ) {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT id, course_id, design_snapshot FROM {$wpdb->prefix}pf_certificates WHERE design_snapshot IS NOT NULL AND design_snapshot <> ''" );
		foreach ( $rows as $row ) {
			$snapshot = json_decode( $row->design_snapshot, true );
			if ( ! is_array( $snapshot ) || ! empty( $snapshot['signature_data'] ) ) { continue; }
			$uses_attachment = $attachment_id && absint( $snapshot['signature_id'] ?? 0 ) === $attachment_id;
			$uses_design = absint( get_post_meta( $row->course_id, Parish_Formation_Course_Settings::CERTIFICATE_DESIGN_ID_META_KEY, true ) ) === absint( $design_id );
			if ( ! $uses_attachment && ! $uses_design ) { continue; }
			$snapshot['signature_id'] = 0;
			$snapshot['signature_data'] = $data;
			$wpdb->update( $wpdb->prefix . 'pf_certificates', array( 'design_snapshot' => wp_json_encode( $snapshot ) ), array( 'id' => absint( $row->id ) ), array( '%s' ), array( '%d' ) );
		}
	}

	private static function render_preview( $values ) {
		?><div id="pf-certificate-branding-preview" class="pf-certificate-branding-preview <?php echo 'portrait' === $values['orientation'] ? 'is-portrait' : ''; ?>" style="--pf-certificate-accent:<?php echo esc_attr( $values['accent_color'] ); ?>;--pf-certificate-border:<?php echo esc_attr( $values['border_color'] ); ?>;--pf-certificate-logo-width:<?php echo esc_attr( $values['logo_width'] ); ?>px"><div class="pf-preview-logo"><?php if ( $values['logo_url'] ) : ?><img src="<?php echo esc_url( $values['logo_url'] ); ?>" alt=""><?php endif; ?></div><strong class="pf-preview-issuer"><?php echo esc_html( $values['issuer'] ?: get_bloginfo( 'name' ) ); ?></strong><h2 class="pf-preview-title"><?php echo esc_html( $values['title'] ?: __( 'Certificate of Completion', 'parish-formation' ) ); ?></h2><p class="pf-preview-heading"><?php echo esc_html( $values['heading'] ?: __( 'This certifies that', 'parish-formation' ) ); ?></p><h3><?php esc_html_e( 'Sample Participant', 'parish-formation' ); ?></h3><p class="pf-preview-completion"><?php echo esc_html( $values['completion_text'] ?: __( 'has successfully completed', 'parish-formation' ) ); ?></p><h4><?php esc_html_e( 'Sample Course', 'parish-formation' ); ?></h4><div class="pf-preview-signer"><span><?php echo esc_html( $values['signatory_name'] ?: __( 'Signatory Name', 'parish-formation' ) ); ?></span><small><?php echo esc_html( $values['signatory_title'] ?: __( 'Title', 'parish-formation' ) ); ?></small></div></div><?php
	}
}
