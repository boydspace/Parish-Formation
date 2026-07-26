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
		$design = Parish_Formation_Certificate_Repository::get_design( $certificate );
		$dompdf->setPaper( 'letter', $design['orientation'] );
		$dompdf->loadHtml( self::get_pdf_html( $certificate ), 'UTF-8' );
		$dompdf->render();
		$filename = sanitize_file_name( $certificate->course_title . '-' . $certificate->participant_name . '-certificate.pdf' );
		$pdf = $dompdf->output();
		if ( headers_sent() ) {
			wp_die( esc_html__( 'The PDF could not be delivered because output had already started.', 'parish-formation' ), '', array( 'response' => 500 ) );
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: ' . ( $attachment ? 'attachment' : 'inline' ) . '; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		header( 'X-Content-Type-Options: nosniff' );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/** Build standalone certificate HTML using escaped issuance snapshots. */
	private static function get_pdf_html( $certificate ) {
		$design    = Parish_Formation_Certificate_Repository::get_design( $certificate );
		$completed = self::format_utc_date( $certificate->completed_at );
		$expires   = $certificate->expires_at ? self::format_utc_date( $certificate->expires_at ) : '';
		$expired   = $certificate->expires_at && strtotime( $certificate->expires_at . ' UTC' ) < current_time( 'timestamp', true );
		$status    = '';
		if ( 'issued' !== $certificate->status ) {
			$status = '<div class="invalid">' . esc_html__( 'REVOKED - NOT VALID', 'parish-formation' ) . '</div>';
		} elseif ( $expired ) {
			$status = '<div class="invalid">' . esc_html__( 'EXPIRED - NOT VALID', 'parish-formation' ) . '</div>';
		}
		$signature = '';
		if ( $design['signatory_name'] || $design['signature_id'] || $design['signature_data'] ) {
			$signature_image = $design['signature_data'] ?: self::attachment_data_uri( $design['signature_id'] );
			$signature_image = self::watermark_signature_data( $signature_image, $certificate->verification_code );
			$signature = '<div class="signature">' . ( $signature_image ? '<img src="' . esc_attr( $signature_image ) . '" alt="">' : '' ) . '<strong>' . esc_html( $design['signatory_name'] ) . '</strong>';
			if ( $design['signatory_title'] ) {
				$signature .= '<span>' . esc_html( $design['signatory_title'] ) . '</span>';
			}
			$signature .= '</div>';
		}
		$logo_image = self::attachment_data_uri( $design['logo_id'] );
		$logo = $logo_image ? '<img class="logo" src="' . esc_attr( $logo_image ) . '" alt="">' : '';
		$logo_width = number_format( min( 3.33, max( 0.63, $design['logo_width'] / 96 ) ), 2, '.', '' );
		$logo_height = number_format( min( 1.35, max( 0.45, ( $design['logo_width'] / 96 ) * 0.65 ) ), 2, '.', '' );
		$is_portrait = 'portrait' === $design['orientation'];
		$certificate_box = $is_portrait ? 'top:0.45in;left:0.45in;width:6.65in;height:9.2in;padding:0.65in 0.55in;' : 'top:0.49in;left:0.54in;width:8.45in;height:6.2in;padding:0.58in 0.65in;';
		$title_margin = $is_portrait ? '0.34in 0 0.62in' : '0.28in 0 0.46in';
		$expiration = $expires ? '<span class="expiry">' . esc_html( sprintf( __( 'Valid through: %s', 'parish-formation' ), $expires ) ) . '</span>' : '';
		return '<!doctype html><html><head><meta charset="UTF-8"><style>
			@page { size: letter ' . esc_html( $design['orientation'] ) . '; margin: 0.25in; }
			html, body { margin: 0; }
			body { color: #252525; font-family: DejaVu Sans, sans-serif; }
			.certificate { position: absolute; ' . $certificate_box . ' border: 8px double ' . esc_html( $design['border_color'] ) . '; background: #fffdf7; text-align: center; }
			.logo { display:block; width:' . $logo_width . 'in; max-width:80%; max-height:' . $logo_height . 'in; margin:0 auto .08in; object-fit:contain; }
			.issuer { margin: 0; font-size: 13pt; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
			h1 { margin: ' . $title_margin . '; font-family: DejaVu Serif, serif; font-size: 31pt; color: #2d3448; }
			.lead { margin: 0.1in 0; font-size: 12pt; }
			h2 { margin: 0.14in 0; font-family: DejaVu Serif, serif; font-size: 25pt; color: ' . esc_html( $design['accent_color'] ) . '; }
			h3 { margin: 0.16in 0 0.3in; font-size: 18pt; }
			.signature { width: 2.8in; margin: 0.3in auto 0.14in; font-size: 11pt; }
			.signature img { display:block; max-width:1.7in; max-height:.55in; margin:0 auto 4px; } .signature strong { display:block; padding-top:5px; border-top:1px solid #333; } .signature span { display: block; margin-top: 3px; font-size: 9pt; }
			.footer { position: absolute; right: 0.32in; bottom: 0.18in; left: 0.32in; font-size: 8pt; text-align: left; }
			.footer span { display: block; } .footer .code { line-height: 1.2; } .footer .expiry { position: absolute; right: 0; bottom: 0; text-align: right; }
			.invalid { position: absolute; top: 3.1in; left: 2.6in; color: #b32d2e; font-size: 28pt; font-weight: bold; transform: rotate(-18deg); opacity: 0.75; }
		</style></head><body><div class="certificate">' . $status . $logo .
		'<p class="issuer">' . esc_html( $design['issuer'] ) . '</p>' .
		'<h1>' . esc_html( $design['title'] ) . '</h1>' .
		'<p class="lead">' . esc_html( $design['heading'] ) . '</p>' .
		'<h2>' . esc_html( $certificate->participant_name ) . '</h2>' .
		'<p class="lead">' . esc_html( $design['completion_text'] ) . '</p>' .
		'<h3>' . esc_html( $certificate->course_title ) . '</h3>' .
		'<p>' . esc_html( sprintf( __( 'Completed %s', 'parish-formation' ), $completed ) ) . '</p>' . $signature .
		'<div class="footer"><span class="code">' . esc_html( sprintf( __( 'Verification code: %s', 'parish-formation' ), $certificate->verification_code ) ) . '</span>' . $expiration . '</div>' .
		'</div></body></html>';
	}

	/** Format a UTC database datetime using the site date setting. */
	private static function format_utc_date( $datetime ) {
		$timestamp = strtotime( $datetime . ' UTC' );
		return $timestamp ? wp_date( get_option( 'date_format' ), $timestamp, wp_timezone() ) : '';
	}

	/** Convert a local media-library image to a PDF-safe data URI. */
	private static function attachment_data_uri( $attachment_id ) {
		$path = $attachment_id ? get_attached_file( $attachment_id ) : '';
		if ( ! $path || ! is_readable( $path ) ) {
			return '';
		}
		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif' ), true ) ) {
			return '';
		}
		$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return false === $data ? '' : 'data:' . $mime . ';base64,' . base64_encode( $data );
	}

	/** Stamp the PDF-only signature pixels with this certificate's verification code. */
	private static function watermark_signature_data( $data_uri, $verification_code ) {
		if ( ! $data_uri || ! function_exists( 'imagecreatefromstring' ) || ! preg_match( '#^data:image/[^;]+;base64,(.+)$#', $data_uri, $matches ) ) { return $data_uri; }
		$bytes = base64_decode( $matches[1], true );
		$image = false === $bytes ? false : imagecreatefromstring( $bytes );
		if ( ! $image ) { return $data_uri; }
		imagesavealpha( $image, true );
		imagealphablending( $image, true );
		$label = 'CERT ' . strtoupper( sanitize_text_field( $verification_code ) );
		$font = 2;
		$x = max( 4, ( imagesx( $image ) - imagefontwidth( $font ) * strlen( $label ) ) / 2 );
		$y = max( 4, imagesy( $image ) - imagefontheight( $font ) - 5 );
		$color = imagecolorallocatealpha( $image, 40, 40, 40, 55 );
		imagestring( $image, $font, (int) $x, (int) $y, $label, $color );
		ob_start();
		imagepng( $image );
		$watermarked = ob_get_clean();
		imagedestroy( $image );
		return false === $watermarked ? $data_uri : 'data:image/png;base64,' . base64_encode( $watermarked );
	}
}
