<?php
/** Certificate administration screens and actions. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This component intentionally reads and writes plugin-owned custom tables; identifiers derive from $wpdb->prefix and mutable values are prepared.

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET values in this screen are read-only search and display filters; all mutations use nonce-protected actions.

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET values in this screen are read-only search and display filters; all mutations use nonce-protected actions.

/** Provides certificate search, audit detail, revocation, and reissuance. */
final class Parish_Formation_Certificates_Admin {
	/** Register the certificate submenu. */
	public static function register_menu() {
		add_submenu_page(
			null,
			esc_html__( 'Certificates', 'parish-formation' ),
			esc_html__( 'Certificates', 'parish-formation' ),
			'pf_view_reports',
			'parish-formation-certificates',
			array( self::class, 'render_page' ),
			33
		);
	}

	/** Render the certificate list or a single certificate audit record. */
	public static function render_page() {
		if ( ! current_user_can( 'pf_view_reports' ) ) {
			wp_die( esc_html__( 'You do not have permission to view certificates.', 'parish-formation' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter input, or a request independently authorized by its one-time token; no nonce-protected form mutation occurs here.
		$certificate_id = isset( $_GET['certificate_id'] ) ? absint( $_GET['certificate_id'] ) : 0;
		if ( $certificate_id ) {
			self::render_detail( $certificate_id );
			return;
		}
		self::render_list();
	}

	/** Process an audited certificate revocation. */
	public static function handle_revoke() {
		self::require_management_access();
		$certificate_id = isset( $_POST['certificate_id'] ) ? absint( $_POST['certificate_id'] ) : 0;
		check_admin_referer( 'pf_revoke_certificate_' . $certificate_id );
		$reason = isset( $_POST['revocation_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['revocation_reason'] ) ) : '';
		$result = Parish_Formation_Certificate_Repository::revoke( $certificate_id, $reason, get_current_user_id() );
		self::redirect_to_detail( $certificate_id, is_wp_error( $result ) ? $result->get_error_code() : 'certificate_revoked' );
	}

	/** Create a replacement for the latest revoked certificate. */
	public static function handle_reissue() {
		self::require_management_access();
		$certificate_id = isset( $_POST['certificate_id'] ) ? absint( $_POST['certificate_id'] ) : 0;
		check_admin_referer( 'pf_reissue_certificate_' . $certificate_id );
		$result = Parish_Formation_Certificate_Repository::reissue( $certificate_id, get_current_user_id() );
		self::redirect_to_detail( is_wp_error( $result ) ? $certificate_id : $result->id, is_wp_error( $result ) ? $result->get_error_code() : 'certificate_reissued' );
	}

	/** Download the filtered certificate audit report. */
	public static function handle_export() {
		if ( ! current_user_can( 'pf_view_reports' ) ) {
			wp_die( esc_html__( 'You do not have permission to export certificates.', 'parish-formation' ) );
		}
		check_admin_referer( 'pf_export_certificates' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter input, or a request independently authorized by its one-time token; no nonce-protected form mutation occurs here.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filter; it does not mutate data.
		$search    = isset( $_GET['pf_search'] ) ? sanitize_text_field( wp_unslash( $_GET['pf_search'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter input, or a request independently authorized by its one-time token; no nonce-protected form mutation occurs here.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filter; it does not mutate data.
		$course_id = isset( $_GET['pf_course_filter'] ) ? absint( $_GET['pf_course_filter'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter input, or a request independently authorized by its one-time token; no nonce-protected form mutation occurs here.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filter; it does not mutate data.
		$status    = isset( $_GET['pf_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['pf_status_filter'] ) ) : 'all';
		$rows      = self::get_filtered_certificates( $search, $course_id, $status, 0 );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="parish-formation-certificates-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Participant', 'Course', 'Course Run', 'Issue Number', 'Status', 'Verification Code', 'Completed', 'Issued', 'Expires', 'Revoked', 'Revocation Reason' ) );
		foreach ( $rows as $row ) {
			fputcsv( $output, array( $row->participant_name, $row->course_title, $row->course_run, $row->issue_number, self::status_label( $row ), $row->verification_code, $row->completed_at, $row->issued_at, $row->expires_at, $row->revoked_at, $row->revocation_reason ) );
		}
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the generated CSV response stream.
		exit;
	}

	/** Render searchable certificate history. */
	private static function render_list() {
		global $wpdb;
		$search    = isset( $_GET['pf_search'] ) ? sanitize_text_field( wp_unslash( $_GET['pf_search'] ) ) : '';
		$course_id = isset( $_GET['pf_course_filter'] ) ? absint( $_GET['pf_course_filter'] ) : 0;
		$status    = isset( $_GET['pf_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['pf_status_filter'] ) ) : 'all';
		if ( ! in_array( $status, array( 'all', 'active', 'expired', 'revoked' ), true ) ) {
			$status = 'all';
		}
		$rows = self::get_filtered_certificates( $search, $course_id, $status, 200 );
		$courses = get_posts( array( 'post_type' => Parish_Formation_Course_Post_Type::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$export_url = wp_nonce_url( add_query_arg( array( 'action' => 'pf_export_certificates', 'pf_search' => $search, 'pf_course_filter' => $course_id, 'pf_status_filter' => $status ), admin_url( 'admin-post.php' ) ), 'pf_export_certificates' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Certificates', 'parish-formation' ); ?></h1>
			<?php self::render_notice(); ?>
			<p><?php esc_html_e( 'Search issued, expired, and revoked certificate records. Certificate snapshots remain unchanged if a participant or course is later edited.', 'parish-formation' ); ?></p>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="parish-formation-certificates">
				<input type="search" name="pf_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Participant, course, or code', 'parish-formation' ); ?>">
				<select name="pf_course_filter"><option value="0"><?php esc_html_e( 'All courses', 'parish-formation' ); ?></option><?php foreach ( $courses as $course ) : ?><option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_id, $course->ID ); ?>><?php echo esc_html( $course->post_title ); ?></option><?php endforeach; ?></select>
				<select name="pf_status_filter">
					<option value="all" <?php selected( $status, 'all' ); ?>><?php esc_html_e( 'All statuses', 'parish-formation' ); ?></option>
					<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'parish-formation' ); ?></option>
					<option value="expired" <?php selected( $status, 'expired' ); ?>><?php esc_html_e( 'Expired', 'parish-formation' ); ?></option>
					<option value="revoked" <?php selected( $status, 'revoked' ); ?>><?php esc_html_e( 'Revoked', 'parish-formation' ); ?></option>
				</select>
				<?php submit_button( __( 'Filter', 'parish-formation' ), 'secondary', 'submit', false ); ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'page', 'parish-formation-certificates', admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Clear', 'parish-formation' ); ?></a>
				<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Download Filtered CSV', 'parish-formation' ); ?></a>
			</form>
			<?php if ( ! $rows ) : ?><p><?php esc_html_e( 'No certificates match these filters.', 'parish-formation' ); ?></p><?php else : ?>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Participant', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Course', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Run / Issue', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Status', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Verification Code', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Issued', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Expires', 'parish-formation' ); ?></th><th><?php esc_html_e( 'Actions', 'parish-formation' ); ?></th></tr></thead><tbody>
			<?php foreach ( $rows as $row ) : $detail_url = add_query_arg( array( 'page' => 'parish-formation-certificates', 'certificate_id' => $row->id ), admin_url( 'admin.php' ) ); ?>
				<?php /* translators: Placeholder values are replaced with the contextual count, name, date, status, or label described by the message. */ ?>
				<tr><td><a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $row->participant_name ); ?></a></td><td><?php echo esc_html( $row->course_title ); ?></td><td><?php echo esc_html( $row->course_run . ' / ' . $row->issue_number ); ?></td><td><?php echo esc_html( self::status_label( $row ) ); ?></td><td><a href="<?php echo esc_url( Parish_Formation_Certificate_Verification::get_verification_url( $row->verification_code ) ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( sprintf( __( 'Verify certificate %s', 'parish-formation' ), $row->verification_code ) ); ?>"><code><?php echo esc_html( $row->verification_code ); ?></code></a></td><td><?php echo esc_html( self::format_utc_date( $row->issued_at ) ); ?></td><td><?php echo $row->expires_at ? esc_html( self::format_utc_date( $row->expires_at ) ) : '&mdash;'; ?></td><td><a class="button button-small" href="<?php echo esc_url( self::pdf_download_url( $row ) ); ?>"><?php esc_html_e( 'Download PDF', 'parish-formation' ); ?></a></td></tr>
			<?php endforeach; ?>
			</tbody></table><?php endif; ?>
		</div>
		<?php
	}

	/** Render the immutable snapshot and its audit actions. */
	private static function render_detail( $certificate_id ) {
		$certificate = Parish_Formation_Certificate_Repository::get_by_id( $certificate_id );
		if ( ! $certificate ) {
			wp_die( esc_html__( 'Certificate not found.', 'parish-formation' ) );
		}
		$issued_by  = $certificate->issued_by ? get_userdata( $certificate->issued_by ) : null;
		$revoked_by = $certificate->revoked_by ? get_userdata( $certificate->revoked_by ) : null;
		$latest     = Parish_Formation_Certificate_Repository::get_for_enrollment_run( $certificate->enrollment_id, $certificate->course_run );
		$is_latest  = $latest && absint( $latest->id ) === absint( $certificate->id );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Certificate Details', 'parish-formation' ); ?></h1>
			<?php self::render_notice(); ?>
			<p><a href="<?php echo esc_url( add_query_arg( 'page', 'parish-formation-certificates', admin_url( 'admin.php' ) ) ); ?>">&larr; <?php esc_html_e( 'All Certificates', 'parish-formation' ); ?></a></p>
			<table class="widefat striped" style="max-width:900px"><tbody>
			<?php
			$fields = array(
				__( 'Status', 'parish-formation' ) => self::status_label( $certificate ),
				__( 'Participant', 'parish-formation' ) => $certificate->participant_name,
				__( 'Course', 'parish-formation' ) => $certificate->course_title,
				__( 'Course run / issue', 'parish-formation' ) => $certificate->course_run . ' / ' . $certificate->issue_number,
				__( 'Certificate title', 'parish-formation' ) => $certificate->certificate_title,
				__( 'Issuer', 'parish-formation' ) => $certificate->issuer_name,
				__( 'Signatory', 'parish-formation' ) => trim( $certificate->signatory_name . ( $certificate->signatory_title ? ' — ' . $certificate->signatory_title : '' ) ),
				__( 'Completed', 'parish-formation' ) => self::format_utc_date( $certificate->completed_at ),
				__( 'Issued', 'parish-formation' ) => self::format_utc_date( $certificate->issued_at ),
				__( 'Issued by', 'parish-formation' ) => $issued_by ? $issued_by->display_name : __( 'System', 'parish-formation' ),
				__( 'Expires', 'parish-formation' ) => $certificate->expires_at ? self::format_utc_date( $certificate->expires_at ) : __( 'Never', 'parish-formation' ),
			);
			foreach ( $fields as $label => $value ) : ?><tr><th style="width:210px"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $value ); ?></td></tr><?php endforeach;
			?><tr><th style="width:210px"><?php esc_html_e( 'Verification code', 'parish-formation' ); ?></th><td><a href="<?php echo esc_url( Parish_Formation_Certificate_Verification::get_verification_url( $certificate->verification_code ) ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( $certificate->verification_code ); ?></code></a></td></tr><?php
			if ( 'revoked' === $certificate->status ) : ?>
			<tr><th><?php esc_html_e( 'Revoked', 'parish-formation' ); ?></th><td><?php echo esc_html( self::format_utc_date( $certificate->revoked_at ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Revoked by', 'parish-formation' ); ?></th><td><?php echo esc_html( $revoked_by ? $revoked_by->display_name : __( 'Unknown user', 'parish-formation' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Reason', 'parish-formation' ); ?></th><td><?php echo nl2br( esc_html( $certificate->revocation_reason ) ); ?></td></tr>
			<?php endif; ?>
			</tbody></table>
			<p><a class="button button-primary" href="<?php echo esc_url( self::pdf_download_url( $certificate ) ); ?>"><?php esc_html_e( 'Download Certificate PDF', 'parish-formation' ); ?></a> <a class="button" href="<?php echo esc_url( Parish_Formation_Certificate_Verification::get_verification_url( $certificate->verification_code ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Verification Page', 'parish-formation' ); ?></a></p>
			<?php if ( current_user_can( 'pf_manage_enrollments' ) && 'issued' === $certificate->status ) : ?>
			<h2><?php esc_html_e( 'Revoke Certificate', 'parish-formation' ); ?></h2>
			<p><?php esc_html_e( 'Revocation is permanent. The public verification page and PDF will identify this code as revoked.', 'parish-formation' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:700px"><input type="hidden" name="action" value="pf_revoke_certificate"><input type="hidden" name="certificate_id" value="<?php echo esc_attr( $certificate->id ); ?>"><?php wp_nonce_field( 'pf_revoke_certificate_' . $certificate->id ); ?><textarea name="revocation_reason" class="large-text" rows="4" required placeholder="<?php esc_attr_e( 'Required reason for revocation', 'parish-formation' ); ?>"></textarea><?php submit_button( __( 'Revoke Certificate', 'parish-formation' ), 'delete' ); ?></form>
			<?php elseif ( current_user_can( 'pf_manage_enrollments' ) && 'revoked' === $certificate->status && $is_latest ) : ?>
			<h2><?php esc_html_e( 'Issue Replacement', 'parish-formation' ); ?></h2><p><?php esc_html_e( 'The replacement will receive a new verification code. This revoked record will remain in the audit history.', 'parish-formation' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pf_reissue_certificate"><input type="hidden" name="certificate_id" value="<?php echo esc_attr( $certificate->id ); ?>"><?php wp_nonce_field( 'pf_reissue_certificate_' . $certificate->id ); ?><?php submit_button( __( 'Issue Replacement Certificate', 'parish-formation' ), 'primary' ); ?></form>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Require both report and enrollment-management access. */
	private static function require_management_access() {
		if ( ! current_user_can( 'pf_view_reports' ) || ! current_user_can( 'pf_manage_enrollments' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage certificates.', 'parish-formation' ) );
		}
	}

	/** Build the existing access-controlled PDF URL for a report viewer. */
	private static function pdf_download_url( $certificate ) {
		return wp_nonce_url(
			add_query_arg( array( 'action' => 'pf_download_certificate', 'certificate' => $certificate->certificate_uuid ), admin_url( 'admin-post.php' ) ),
			'pf_download_certificate_' . $certificate->certificate_uuid
		);
	}

	/** Redirect back to a certificate record with a whitelisted notice code. */
	private static function redirect_to_detail( $certificate_id, $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'parish-formation-certificates', 'certificate_id' => absint( $certificate_id ), 'pf_certificate_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Show action feedback without reflecting arbitrary query text. */
	private static function render_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter input, or a request independently authorized by its one-time token; no nonce-protected form mutation occurs here.
		$code = isset( $_GET['pf_certificate_notice'] ) ? sanitize_key( wp_unslash( $_GET['pf_certificate_notice'] ) ) : '';
		$notices = array(
			'certificate_revoked' => array( 'success', __( 'The certificate was revoked.', 'parish-formation' ) ),
			'certificate_reissued' => array( 'success', __( 'A replacement certificate was issued with a new verification code.', 'parish-formation' ) ),
			'certificate_reason_required' => array( 'error', __( 'Enter a reason for revoking the certificate.', 'parish-formation' ) ),
			'certificate_not_issued' => array( 'error', __( 'Only an issued certificate can be revoked.', 'parish-formation' ) ),
			'certificate_not_revoked' => array( 'error', __( 'Only a revoked certificate can be reissued.', 'parish-formation' ) ),
			'certificate_replacement_exists' => array( 'error', __( 'A replacement certificate has already been issued.', 'parish-formation' ) ),
			'certificate_database_error' => array( 'error', __( 'The certificate action could not be saved.', 'parish-formation' ) ),
		);
		if ( isset( $notices[ $code ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notices[ $code ][0] ), esc_html( $notices[ $code ][1] ) );
		}
	}

	/** Resolve the effective public status. */
	private static function status_label( $certificate ) {
		if ( 'revoked' === $certificate->status ) {
			return __( 'Revoked', 'parish-formation' );
		}
		if ( $certificate->expires_at && strtotime( $certificate->expires_at . ' UTC' ) < time() ) {
			return __( 'Expired', 'parish-formation' );
		}
		return __( 'Active', 'parish-formation' );
	}

	/** Retrieve filtered certificate rows for the screen or CSV. */
	private static function get_filtered_certificates( $search, $course_id, $status, $limit ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'all', 'active', 'expired', 'revoked' ), true ) ) {
			$status = 'all';
		}
		$where = array( '1=1' );
		$args  = array();
		if ( $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = '(participant_name LIKE %s OR verification_code LIKE %s OR course_title LIKE %s)';
			array_push( $args, $like, $like, $like );
		}
		if ( $course_id ) {
			$where[] = 'course_id = %d';
			$args[]  = absint( $course_id );
		}
		$now = current_time( 'mysql', true );
		if ( 'active' === $status ) {
			$where[] = "status = 'issued' AND (expires_at IS NULL OR expires_at >= %s)";
			$args[]  = $now;
		} elseif ( 'expired' === $status ) {
			$where[] = "status = 'issued' AND expires_at IS NOT NULL AND expires_at < %s";
			$args[]  = $now;
		} elseif ( 'revoked' === $status ) {
			$where[] = "status = 'revoked'";
		}
		$sql = "SELECT * FROM {$wpdb->prefix}pf_certificates WHERE " . implode( ' AND ', $where ) . ' ORDER BY issued_at DESC, id DESC';
		if ( $limit ) {
			$sql .= ' LIMIT ' . absint( $limit );
		}
		return $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Format a stored UTC timestamp in the configured site timezone. */
	private static function format_utc_date( $date ) {
		return get_date_from_gmt( $date, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
	}
}
