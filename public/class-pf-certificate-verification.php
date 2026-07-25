<?php
/** Public completion-certificate verification. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Provides a public, read-only verification route. */
final class Parish_Formation_Certificate_Verification {

	/** Register public verification rewrite rules. */
	public static function register_routes() {
		add_shortcode( 'formation-certificate', array( self::class, 'render_shortcode' ) );
		add_shortcode( 'parish_formation_certificate_verification', array( self::class, 'render_shortcode' ) );
		add_rewrite_tag( '%pf_certificate_verify%', '([^&]+)' );
		add_rewrite_tag( '%certificate_code%', '([A-Za-z0-9]+)' );
		$page_id = self::get_shortcode_page_id();
		if ( $page_id ) {
			$page_path = trim( get_page_uri( $page_id ), '/' );
			add_rewrite_rule( '^' . preg_quote( $page_path, '/' ) . '/([A-Za-z0-9]+)/?$', 'index.php?page_id=' . $page_id . '&certificate_code=$matches[1]', 'top' );
			$route_signature = $page_id . ':' . $page_path;
		} else {
			add_rewrite_rule( '^formation-certificate/?$', 'index.php?pf_certificate_verify=lookup', 'top' );
			add_rewrite_rule( '^formation-certificate/([A-Za-z0-9]+)/?$', 'index.php?pf_certificate_verify=$matches[1]', 'top' );
			$route_signature = 'legacy';
		}
		if ( $route_signature !== get_option( 'parish_formation_certificate_route_signature', '' ) ) {
			flush_rewrite_rules( false );
			update_option( 'parish_formation_certificate_route_signature', $route_signature, false );
		}
	}

	/** Render the public verification page through the active theme. */
	public static function maybe_render() {
		$route = get_query_var( 'pf_certificate_verify', '' );
		if ( ! $route ) {
			return;
		}
		$shortcode_page_url = self::get_shortcode_page_url();
		if ( $shortcode_page_url ) {
			$code = 'lookup' === $route ? '' : strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $route ) );
			wp_safe_redirect( $code ? add_query_arg( 'certificate_code', $code, $shortcode_page_url ) : $shortcode_page_url );
			exit;
		}
		$submitted_code = isset( $_GET['certificate_code'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/i', '', wp_unslash( $_GET['certificate_code'] ) ) ) : '';
		if ( $submitted_code ) {
			wp_safe_redirect( home_url( '/formation-certificate/' . rawurlencode( $submitted_code ) . '/' ) );
			exit;
		}
		$code        = 'lookup' === $route ? '' : strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $route ) );
		$certificate = $code ? Parish_Formation_Certificate_Repository::get_by_verification_code( $code ) : null;
		status_header( $code && ! $certificate ? 404 : 200 );
		nocache_headers();
		wp_enqueue_style( 'parish-formation-uikit', PARISH_FORMATION_PLUGIN_URL . 'assets/vendor/uikit/uikit.min.css', array(), PARISH_FORMATION_UIKIT_VERSION );
		wp_enqueue_style( 'parish-formation-frontend', PARISH_FORMATION_PLUGIN_URL . 'assets/css/parish-formation-frontend.css', array( 'parish-formation-uikit' ), (string) filemtime( PARISH_FORMATION_PLUGIN_DIR . 'assets/css/parish-formation-frontend.css' ) );
		get_header();
		self::render_page( $code, $certificate );
		get_footer();
		exit;
	}

	/** Render certificate verification inside a normal WordPress page. */
	public static function render_shortcode() {
		$route_code  = get_query_var( 'certificate_code', '' );
		$code        = isset( $_GET['certificate_code'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/i', '', wp_unslash( $_GET['certificate_code'] ) ) ) : strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $route_code ) );
		$certificate = $code ? Parish_Formation_Certificate_Repository::get_by_verification_code( $code ) : null;
		ob_start();
		self::render_page( $code, $certificate, false );
		return (string) ob_get_clean();
	}

	/** Locate a published page containing the verification shortcode. */
	public static function get_shortcode_page_url() {
		$page_id = self::get_shortcode_page_id();
		return $page_id ? get_permalink( $page_id ) : '';
	}

	/** Locate the published WordPress page containing the shortcode. */
	private static function get_shortcode_page_id() {
		global $wpdb;
		return absint(
			$wpdb->get_var(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND (post_content LIKE '%[formation-certificate%' OR post_content LIKE '%[parish\\_formation\\_certificate\\_verification%') ORDER BY ID ASC LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			)
		);
	}

	/** Return the preferred public URL for a verification code. */
	public static function get_verification_url( $code ) {
		$page_url = self::get_shortcode_page_url();
		return $page_url
			? add_query_arg( 'certificate_code', rawurlencode( $code ), $page_url )
			: home_url( '/formation-certificate/' . rawurlencode( $code ) . '/' );
	}

	/** Output the lookup form and a safe verification result. */
	private static function render_page( $code, $certificate, $include_page_wrapper = true ) {
		$form_action = $include_page_wrapper ? home_url( '/formation-certificate/' ) : remove_query_arg( 'certificate_code' );
		?>
		<div class="pf-verification-page <?php echo $include_page_wrapper ? 'uk-container uk-container-small uk-section' : ''; ?>">
			<div class="uk-card uk-card-default uk-card-body">
				<?php if ( $include_page_wrapper ) : ?><h1><?php esc_html_e( 'Verify a Formation Certificate', 'parish-formation' ); ?></h1><?php else : ?><h2><?php esc_html_e( 'Verify a Formation Certificate', 'parish-formation' ); ?></h2><?php endif; ?>
				<p><?php esc_html_e( 'Enter the verification code printed on the certificate.', 'parish-formation' ); ?></p>
				<form method="get" action="<?php echo esc_url( $form_action ); ?>" class="pf-verification-form">
					<div class="pf-verification-field"><label class="uk-form-label" for="pf-certificate-code"><?php esc_html_e( 'Verification code', 'parish-formation' ); ?></label><input id="pf-certificate-code" class="uk-input" type="text" name="certificate_code" value="<?php echo esc_attr( $code ); ?>" maxlength="20" pattern="[A-Za-z0-9]{20}" required autocomplete="off"></div>
					<button class="uk-button uk-button-primary" type="submit"><?php esc_html_e( 'Verify', 'parish-formation' ); ?></button>
				</form>
				<?php if ( $code && ! $certificate ) : ?>
					<div class="uk-alert-danger uk-margin-top" uk-alert><p><strong><?php esc_html_e( 'Certificate not found.', 'parish-formation' ); ?></strong> <?php esc_html_e( 'Check the code and try again.', 'parish-formation' ); ?></p></div>
				<?php elseif ( $certificate ) : ?>
					<?php
					$expired = $certificate->expires_at && strtotime( $certificate->expires_at . ' UTC' ) < current_time( 'timestamp', true );
					$valid   = 'issued' === $certificate->status && ! $expired;
					$pdf_base_url = add_query_arg(
						array(
							'action' => 'pf_public_certificate_pdf',
							'code'   => $certificate->verification_code,
						),
						admin_url( 'admin-post.php' )
					);
					?>
					<section class="pf-verification-result uk-margin-top <?php echo $valid ? 'is-valid' : 'is-invalid'; ?>">
						<h2><?php echo $valid ? esc_html__( 'Valid Certificate', 'parish-formation' ) : ( $expired ? esc_html__( 'Expired Certificate', 'parish-formation' ) : esc_html__( 'Revoked Certificate', 'parish-formation' ) ); ?></h2>
						<div class="pf-verification-details">
							<div><span><?php esc_html_e( 'Participant', 'parish-formation' ); ?></span><strong><?php echo esc_html( $certificate->participant_name ); ?></strong></div>
							<div><span><?php esc_html_e( 'Course', 'parish-formation' ); ?></span><strong><?php echo esc_html( $certificate->course_title ); ?></strong></div>
							<div><span><?php esc_html_e( 'Issuer', 'parish-formation' ); ?></span><strong><?php echo esc_html( $certificate->issuer_name ); ?></strong></div>
							<div><span><?php esc_html_e( 'Completed', 'parish-formation' ); ?></span><strong><?php echo esc_html( self::format_utc_date( $certificate->completed_at ) ); ?></strong></div>
							<div><span><?php esc_html_e( 'Issued', 'parish-formation' ); ?></span><strong><?php echo esc_html( self::format_utc_date( $certificate->issued_at ) ); ?></strong></div>
							<?php if ( $certificate->expires_at ) : ?><div><span><?php esc_html_e( 'Expires', 'parish-formation' ); ?></span><strong><?php echo esc_html( self::format_utc_date( $certificate->expires_at ) ); ?></strong></div><?php endif; ?>
							<div class="pf-verification-code"><span><?php esc_html_e( 'Verification code', 'parish-formation' ); ?></span><strong><code><?php echo esc_html( $certificate->verification_code ); ?></code></strong></div>
						</div>
						<p class="pf-verification-actions">
							<a class="uk-button uk-button-primary" href="<?php echo esc_url( $pdf_base_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Print Certificate', 'parish-formation' ); ?></a>
							<a class="uk-button uk-button-secondary" href="<?php echo esc_url( add_query_arg( 'download', '1', $pdf_base_url ) ); ?>"><?php esc_html_e( 'Download PDF', 'parish-formation' ); ?></a>
						</p>
					</section>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/** Format a stored UTC datetime in the site timezone. */
	private static function format_utc_date( $datetime ) {
		$timestamp = strtotime( $datetime . ' UTC' );
		return $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp, wp_timezone() ) : '';
	}
}
