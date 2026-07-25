<?php
/** Secure certificate PDF downloads. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

/** Generates immutable completion certificates as PDFs. */
final class Parish_Formation_Certificate_Actions {

	/** Stream an access-controlled certificate PDF. */
	public static function download_pdf() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		$certificate_uuid = isset( $_GET['certificate'] ) ? sanitize_text_field( wp_unslash( $_GET['certificate'] ) ) : '';
		check_admin_referer( 'pf_download_certificate_' . $certificate_uuid );
		$certificate = Parish_Formation_Certificate_Repository::get_by_uuid( $certificate_uuid );
		$can_view     = $certificate && ( absint( $certificate->user_id ) === get_current_user_id() || current_user_can( 'pf_view_reports' ) );
		if ( ! $can_view ) {
			wp_die( esc_html__( 'This certificate could not be found or you do not have access to it.', 'parish-formation' ), '', array( 'response' => 403 ) );
		}
		self::stream_pdf( $certificate, true );
	}

	/** Stream a public certificate identified by its high-entropy verification code. */
	public static function public_pdf() {
		$code        = isset( $_GET['code'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/i', '', wp_unslash( $_GET['code'] ) ) ) : '';
		$certificate = Parish_Formation_Certificate_Repository::get_by_verification_code( $code );
		if ( ! $certificate ) {
			wp_die( esc_html__( 'This certificate could not be found.', 'parish-formation' ), '', array( 'response' => 404 ) );
		}
		$download = isset( $_GET['download'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['download'] ) );
		self::stream_pdf( $certificate, $download );
	}

	/** Render and stream a certificate PDF. */
	private static function stream_pdf( $certificate, $attachment ) {
		if ( ! class_exists( Dompdf::class ) ) {
			wp_die( esc_html__( 'The PDF renderer is unavailable.', 'parish-formation' ), '', array( 'response' => 500 ) );
		}
		$options = new Options();
		$options->set( 'isRemoteEnabled', false );
		$options->set( 'isPhpEnabled', false );
		$options->set( 'isJavascriptEnabled', false );
		$options->setChroot( PARISH_FORMATION_PLUGIN_DIR );
		$dompdf = new Dompdf( $options );
		$dompdf->setPaper( 'letter', 'landscape' );
		$dompdf->loadHtml( self::get_pdf_html( $certificate ), 'UTF-8' );
		$dompdf->render();
		$filename = sanitize_file_name( $certificate->course_title . '-' . $certificate->participant_name . '-certificate.pdf' );
		$dompdf->stream( $filename, array( 'Attachment' => (bool) $attachment ) );
		exit;
	}

	/** Build standalone certificate HTML using escaped issuance snapshots. */
	private static function get_pdf_html( $certificate ) {
		$completed = self::format_utc_date( $certificate->completed_at );
		$expires   = $certificate->expires_at ? self::format_utc_date( $certificate->expires_at ) : '';
		$verification_url = Parish_Formation_Certificate_Verification::get_verification_url( $certificate->verification_code );
		$expired   = $certificate->expires_at && strtotime( $certificate->expires_at . ' UTC' ) < current_time( 'timestamp', true );
		$status    = '';
		if ( 'issued' !== $certificate->status ) {
			$status = '<div class="invalid">' . esc_html__( 'REVOKED - NOT VALID', 'parish-formation' ) . '</div>';
		} elseif ( $expired ) {
			$status = '<div class="invalid">' . esc_html__( 'EXPIRED - NOT VALID', 'parish-formation' ) . '</div>';
		}
		$signature = '';
		if ( $certificate->signatory_name ) {
			$signature = '<div class="signature"><strong>' . esc_html( $certificate->signatory_name ) . '</strong>';
			if ( $certificate->signatory_title ) {
				$signature .= '<span>' . esc_html( $certificate->signatory_title ) . '</span>';
			}
			$signature .= '</div>';
		}
		$expiration = $expires ? '<span class="expiry">' . esc_html( sprintf( __( 'Valid through: %s', 'parish-formation' ), $expires ) ) . '</span>' : '';
		return '<!doctype html><html><head><meta charset="UTF-8"><style>
			@page { size: letter landscape; margin: 0.25in; }
			html, body { margin: 0; }
			body { color: #252525; font-family: DejaVu Sans, sans-serif; }
			.certificate { position: absolute; top: 0.49in; left: 0.54in; width: 8.45in; height: 6.2in; padding: 0.58in 0.65in; border: 8px double #b58d18; background: #fffdf7; text-align: center; }
			.issuer { margin: 0; font-size: 13pt; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
			h1 { margin: 0.28in 0 0.46in; font-family: DejaVu Serif, serif; font-size: 31pt; color: #2d3448; }
			.lead { margin: 0.1in 0; font-size: 12pt; }
			h2 { margin: 0.14in 0; font-family: DejaVu Serif, serif; font-size: 25pt; color: #1c5b8f; }
			h3 { margin: 0.16in 0 0.3in; font-size: 18pt; }
			.signature { width: 2.8in; margin: 0.38in auto 0.18in; padding-top: 6px; border-top: 1px solid #333; font-size: 11pt; }
			.signature span { display: block; margin-top: 3px; font-size: 9pt; }
			.footer { position: absolute; right: 0.65in; bottom: 0.34in; left: 0.65in; font-size: 8pt; text-align: center; }
			.footer span { display: block; } .footer .code { line-height: 1.45; } .footer .expiry { margin-top: 4px; }
			.invalid { position: absolute; top: 3.1in; left: 2.6in; color: #b32d2e; font-size: 28pt; font-weight: bold; transform: rotate(-18deg); opacity: 0.75; }
		</style></head><body><div class="certificate">' . $status .
		'<p class="issuer">' . esc_html( $certificate->issuer_name ) . '</p>' .
		'<h1>' . esc_html( $certificate->certificate_title ) . '</h1>' .
		'<p class="lead">' . esc_html__( 'This certifies that', 'parish-formation' ) . '</p>' .
		'<h2>' . esc_html( $certificate->participant_name ) . '</h2>' .
		'<p class="lead">' . esc_html__( 'has successfully completed', 'parish-formation' ) . '</p>' .
		'<h3>' . esc_html( $certificate->course_title ) . '</h3>' .
		'<p>' . esc_html( sprintf( __( 'Completed %s', 'parish-formation' ), $completed ) ) . '</p>' . $signature .
		'<div class="footer"><span class="code">' . esc_html( sprintf( __( 'Verification code: %s', 'parish-formation' ), $certificate->verification_code ) ) . '<br>' . esc_html( $verification_url ) . '</span>' . $expiration . '</div>' .
		'</div></body></html>';
	}

	/** Format a UTC database datetime using the site date setting. */
	private static function format_utc_date( $datetime ) {
		$timestamp = strtotime( $datetime . ' UTC' );
		return $timestamp ? wp_date( get_option( 'date_format' ), $timestamp, wp_timezone() ) : '';
	}
}
