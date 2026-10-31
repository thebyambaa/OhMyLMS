<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Learner download and public verification pages of issued certificates.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Certificates;

use OhMyLMS\Student\Hub;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end addresses on the home URL, answered on `template_redirect` before the theme renders:
 *
 * - `?ohmylms-download-certificate=<code>&format=pdf|print`: the certificate as a PDF file or a printable page,
 *   for its learner and for administrators only.
 * - `?ohmylms-verify-certificate=<code>`: public verification (status, learner name, course, dates).
 * - `?ohmylms-certificate-data=…`: links from older completion emails; they lead to the learner's
 *   certificates instead of decrypting the payload.
 */
final class Download {
	const QUERY_CODE   = 'ohmylms-download-certificate';
	const QUERY_VERIFY = 'ohmylms-verify-certificate';
	const QUERY_LEGACY = 'ohmylms-certificate-data';

	/**
	 * Failed verification lookups allowed per address in {@see THROTTLE_WINDOW} seconds.
	 */
	const THROTTLE_LIMIT  = 30;
	const THROTTLE_WINDOW = 600;

	/**
	 * Download address of a certificate.
	 *
	 * @param string $code   Verification code.
	 * @param string $format `pdf` or `print`.
	 * @return string
	 */
	public static function url( $code, $format = 'pdf' ) {
		return add_query_arg(
			array(
				self::QUERY_CODE => rawurlencode( $code ),
				'format'         => 'print' === $format ? 'print' : 'pdf',
			),
			home_url( '/' )
		);
	}

	/**
	 * Public verification address, with or without a code.
	 *
	 * @param string $code Verification code.
	 * @return string
	 */
	public static function verify_url( $code = '' ) {
		return add_query_arg( self::QUERY_VERIFY, rawurlencode( $code ), home_url( '/' ) );
	}

	/**
	 * Where a learner finds their certificates: the student hub's achievements, else the site.
	 *
	 * @return string
	 */
	public static function learner_url() {
		return class_exists( Hub::class ) && Hub::base_url() ? Hub::url( 'achievements' ) : home_url( '/' );
	}

	/**
	 * Answer the addresses above.
	 */
	public static function route() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only pages; access is checked per certificate.
		if ( isset( $_GET[ self::QUERY_VERIFY ] ) ) {
			self::verify( sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VERIFY ] ) ) );
		} elseif ( isset( $_GET[ self::QUERY_CODE ] ) ) {
			$format = isset( $_GET['format'] ) && 'print' === $_GET['format'] ? 'print' : 'pdf';
			self::download( sanitize_text_field( wp_unslash( $_GET[ self::QUERY_CODE ] ) ), $format );
		} elseif ( isset( $_GET[ self::QUERY_LEGACY ] ) ) {
			wp_safe_redirect( is_user_logged_in() ? self::learner_url() : wp_login_url( self::learner_url() ) );
			exit;
		}
		// phpcs:enable
	}

	/**
	 * Whether the current user may download a certificate.
	 *
	 * @param array $record Issued certificate.
	 * @return bool
	 */
	public static function can_download( array $record ) {
		$user = get_current_user_id();

		return $user && ( $user === $record['user_id'] || current_user_can( Issuer::CAPABILITY ) );
	}

	/**
	 * What verification reveals: no IDs, e-mail or template.
	 *
	 * @param array $record Issued certificate.
	 * @return array{code:string,status:string,student_name:string,course_title:string,completed_on:string,issued_on:string,revoked_on:string}
	 */
	public static function public_view( array $record ) {
		return array(
			'code'         => $record['code'],
			'status'       => $record['status'],
			'student_name' => $record['student_name'],
			'course_title' => $record['course_title'],
			'completed_on' => $record['completed_at'] ? Issuer::local_date( $record['completed_at'] ) : '',
			'issued_on'    => Issuer::local_date( $record['issued_at'] ),
			'revoked_on'   => $record['revoked_at'] ? Issuer::local_date( $record['revoked_at'] ) : '',
		);
	}

	/**
	 * Count a failed lookup from this address; true when the address is over the limit.
	 *
	 * @param bool $failed Whether this lookup found nothing.
	 * @return bool
	 */
	public static function throttled( $failed ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'ohmylms_cert_verify_' . md5( $ip );
		$hit = (int) get_transient( $key );
		if ( $hit >= self::THROTTLE_LIMIT ) {
			return true;
		}
		if ( $failed ) {
			set_transient( $key, $hit + 1, self::THROTTLE_WINDOW );
		}

		return false;
	}

	/**
	 * Send the PDF or the printable page.
	 *
	 * @param string $code   Code from the address.
	 * @param string $format `pdf` or `print`.
	 */
	private static function download( $code, $format ) {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::url( Issuer::normalize_code( $code ), $format ) ) );
			exit;
		}
		$record = Issuer::find_by_code( $code );
		if ( ! $record || ! self::can_download( $record ) ) {
			self::page( 404, __( 'Certificate not found', 'ohmylms' ), '<p>' . esc_html__( 'There is no certificate with this code in your account.', 'ohmylms' ) . '</p>' );
		}
		if ( 'revoked' === $record['status'] ) {
			self::page( 410, __( 'Certificate revoked', 'ohmylms' ), '<p>' . esc_html__( 'This certificate was revoked and can no longer be downloaded.', 'ohmylms' ) . '</p>' );
		}
		$template = Issuer::template( $record );
		$values   = Issuer::values( $record );
		if ( 'pdf' === $format && PdfRenderer::available() ) {
			$pdf = PdfRenderer::render( $template, $values );
			if ( ! is_wp_error( $pdf ) ) {
				status_header( 200 );
				nocache_headers();
				header( 'Content-Type: application/pdf' );
				header( 'Content-Disposition: attachment; filename="certificate-' . strtolower( $record['code'] ) . '.pdf"' );
				header( 'Content-Length: ' . strlen( $pdf ) );
				header( 'X-Content-Type-Options: nosniff' );
				header( 'X-Robots-Tag: noindex, nofollow' );
				header( 'Referrer-Policy: no-referrer' );
				echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PDF.
				exit;
			}
		}
		self::print_page( $record, $template, $values );
	}

	/**
	 * Printable page: the certificate at paper size, with a print button hidden when printing.
	 *
	 * @param array $record   Issued certificate.
	 * @param array $template Canonical template.
	 * @param array $values   Placeholder values.
	 */
	private static function print_page( array $record, array $template, array $values ) {
		$page  = Template::dimensions( $template );
		$nonce = wp_generate_password( 16, false, false );
		$size  = sprintf( '%smm %smm', self::number( $page['width'] ), self::number( $page['height'] ) );
		self::headers( 200, "default-src 'none'; img-src 'self' http: https: data:; style-src 'unsafe-inline'; script-src 'nonce-" . $nonce . "'; base-uri 'none'; form-action 'none'" );
		echo '<!DOCTYPE html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">';
		echo '<title>' . esc_html( sprintf( /* translators: %s: course title. */ __( 'Certificate: %s', 'ohmylms' ), $record['course_title'] ) ) . '</title>';
		echo '<style>@page{size:' . esc_html( $size ) . ';margin:0}html,body{margin:0;padding:0}body{font-family:system-ui,sans-serif}.oml-cert-bar{display:flex;gap:12px;align-items:center;justify-content:center;padding:12px}.oml-cert-sheet{width:' . esc_html( self::number( $page['width'] ) ) . 'mm;max-width:100%;margin:0 auto}@media print{.oml-cert-bar{display:none}.oml-cert-sheet{width:' . esc_html( self::number( $page['width'] ) ) . 'mm;max-width:none}}</style></head><body>';
		echo '<div class="oml-cert-bar"><button type="button" id="oml-cert-print">' . esc_html__( 'Print or save as PDF', 'ohmylms' ) . '</button>';
		if ( PdfRenderer::available() ) {
			echo '<a href="' . esc_url( self::url( $record['code'], 'pdf' ) ) . '">' . esc_html__( 'Download PDF', 'ohmylms' ) . '</a>';
		}
		echo '<a href="' . esc_url( self::verify_url( $record['code'] ) ) . '">' . esc_html__( 'Verification page', 'ohmylms' ) . '</a></div>';
		echo '<main class="oml-cert-sheet">' . TemplateRenderer::fragment( $template, $values ) . '</main>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- TemplateRenderer escapes every value.
		echo '<script nonce="' . esc_attr( $nonce ) . '">document.getElementById("oml-cert-print").addEventListener("click",function(){window.print();});</script>';
		echo '</body></html>';
		exit;
	}

	/**
	 * Public verification page.
	 *
	 * @param string $code Code from the address; empty shows the form only.
	 */
	private static function verify( $code ) {
		$status = 200;
		$body   = '';
		if ( '' !== trim( $code ) ) {
			$record = Issuer::find_by_code( $code );
			if ( self::throttled( ! $record ) ) {
				$status = 429;
				$body   = '<p role="alert">' . esc_html__( 'Too many codes were checked from your network. Try again in a few minutes.', 'ohmylms' ) . '</p>';
			} elseif ( ! $record ) {
				$status = 404;
				$body   = '<p role="alert"><strong>' . esc_html__( 'No certificate has this code.', 'ohmylms' ) . '</strong> ' . esc_html__( 'Check the code and try again.', 'ohmylms' ) . '</p>';
			} else {
				$view  = self::public_view( $record );
				$valid = 'issued' === $view['status'];
				$body  = '<p role="status"><strong>' . ( $valid ? esc_html__( 'This certificate is valid.', 'ohmylms' ) : esc_html( sprintf( /* translators: %s: date. */ __( 'This certificate was revoked on %s.', 'ohmylms' ), $view['revoked_on'] ) ) ) . '</strong></p><dl>';
				foreach ( array(
					__( 'Awarded to', 'ohmylms' )       => $view['student_name'],
					__( 'Course', 'ohmylms' )           => $view['course_title'],
					__( 'Completed on', 'ohmylms' )     => $view['completed_on'],
					__( 'Issued on', 'ohmylms' )        => $view['issued_on'],
					__( 'Certificate code', 'ohmylms' ) => $view['code'],
				) as $label => $value ) {
					if ( '' !== $value ) {
						$body .= '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
					}
				}
				$body .= '</dl>';
			}
		}
		$form = '<form method="get" action="' . esc_url( home_url( '/' ) ) . '"><label for="oml-verify-code">' . esc_html__( 'Certificate code', 'ohmylms' ) . '</label> <input id="oml-verify-code" name="' . esc_attr( self::QUERY_VERIFY ) . '" value="' . esc_attr( $code ) . '" maxlength="32" autocomplete="off" spellcheck="false" required> <button type="submit">' . esc_html__( 'Verify', 'ohmylms' ) . '</button></form>';
		self::page( $status, __( 'Verify a certificate', 'ohmylms' ), $body . $form, "form-action 'self'" );
	}

	/**
	 * A small standalone page.
	 *
	 * @param int    $status HTTP status.
	 * @param string $title  Plain title.
	 * @param string $body   Escaped HTML.
	 * @param string $extra  Extra CSP directives.
	 */
	private static function page( $status, $title, $body, $extra = "form-action 'none'" ) {
		self::headers( $status, "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; " . $extra );
		echo '<!DOCTYPE html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">';
		echo '<title>' . esc_html( $title . ' – ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) . '</title>';
		echo '<style>body{font-family:system-ui,sans-serif;max-width:40rem;margin:3rem auto;padding:0 1rem;line-height:1.5}dt{font-weight:600}dd{margin:0 0 .75rem}input{font:inherit;padding:.25rem .5rem}button{font:inherit}</style></head><body><main>';
		echo '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) . '</a></p>';
		echo '<h1>' . esc_html( $title ) . '</h1>' . $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
		echo '</main></body></html>';
		exit;
	}

	/**
	 * Common headers of the pages.
	 *
	 * @param int    $status HTTP status.
	 * @param string $csp    Content security policy.
	 */
	private static function headers( $status, $csp ) {
		status_header( $status );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Content-Security-Policy: ' . $csp . "; frame-ancestors 'self'" );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );
	}

	/**
	 * Format a number for CSS.
	 *
	 * @param float $value Number.
	 * @return string
	 */
	private static function number( $value ) {
		return rtrim( rtrim( number_format( (float) $value, 4, '.', '' ), '0' ), '.' );
	}
}
