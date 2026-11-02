<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * PDF output of a certificate.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Certificates;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a template to PDF with Dompdf (a Composer dependency of the plugin).
 *
 * The layout is the one {@see TemplateRenderer} draws, expressed in millimetres and points because
 * Dompdf has no container query units. The same escaping rules apply: text through esc_html() after the
 * placeholders are replaced, numbers through number_format(), colours re-checked. Dompdf never fetches a
 * URL (`isRemoteEnabled` off): images are read from the media library's files, inside the uploads folder.
 */
final class PdfRenderer {

	/**
	 * Whether PDF output is available.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( '\Dompdf\Dompdf' );
	}

	/**
	 * HTML that Dompdf lays out.
	 *
	 * @param array                $template Canonical template.
	 * @param array<string,string> $values   Token => text.
	 * @return string
	 */
	public static function html( array $template, array $values ) {
		$page       = Template::dimensions( $template );
		$family     = 'sans-serif' === $template['font_family'] ? 'DejaVu Sans' : 'DejaVu Serif';
		$background = preg_match( '/^#[0-9a-f]{6}$/', (string) $template['background_color'] ) ? $template['background_color'] : '#ffffff';
		$replace    = array();
		foreach ( array_keys( Template::placeholders() ) as $token ) {
			$replace[ $token ] = isset( $values[ $token ] ) ? (string) $values[ $token ] : $token;
		}
		$width  = self::number( $page['width'] );
		$height = self::number( $page['height'] );
		// Slightly shorter than the page so rounding never spills onto a second page.
		$inner = self::number( $page['height'] - 0.5 );

		$html  = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>';
		$html .= sprintf( '@page{size:%smm %smm;margin:0;}html,body{margin:0;padding:0;}', $width, $height );
		$html .= sprintf( '.page{position:relative;overflow:hidden;width:%smm;height:%smm;background-color:%s;font-family:"%s";}', $width, $inner, $background, $family );
		$html .= '.page img{position:absolute;}.block{position:absolute;margin:0;line-height:1.2;}';
		$html .= '</style></head><body><div class="page">';

		$background_path = self::image_path( (int) $template['background_id'] );
		if ( '' !== $background_path ) {
			$html .= sprintf( '<img src="%s" alt="" style="left:0;top:0;width:%smm;height:%smm;">', esc_attr( $background_path ), $width, $inner );
		}
		foreach ( $template['blocks'] as $block ) {
			$align = in_array( $block['align'], array( 'left', 'center', 'right' ), true ) ? $block['align'] : 'center';
			$color = preg_match( '/^#[0-9a-f]{6}$/', (string) $block['color'] ) ? $block['color'] : '#000000';
			$text  = esc_html( strtr( (string) $block['text'], $replace ) );
			$html .= sprintf(
				'<div class="block" style="%s">%s</div>',
				esc_attr(
					sprintf(
						'left:%smm;top:%smm;width:%smm;color:%s;font-size:%spt;font-weight:%s;text-align:%s;',
						self::number( $block['x'] * $page['width'] / 100 ),
						self::number( $block['y'] * $page['height'] / 100 ),
						self::number( $block['width'] * $page['width'] / 100 ),
						$color,
						self::number( $block['font_size'] ),
						! empty( $block['bold'] ) ? 'bold' : 'normal',
						$align
					)
				),
				nl2br( $text, false )
			);
		}
		$signature_path = self::image_path( (int) $template['signature']['image_id'] );
		if ( '' !== $signature_path ) {
			$html .= sprintf(
				'<img src="%s" alt="" style="%s">',
				esc_attr( $signature_path ),
				esc_attr(
					sprintf(
						'left:%smm;top:%smm;width:%smm;',
						self::number( $template['signature']['x'] * $page['width'] / 100 ),
						self::number( $template['signature']['y'] * $page['height'] / 100 ),
						self::number( $template['signature']['width'] * $page['width'] / 100 )
					)
				)
			);
		}

		return $html . '</div></body></html>';
	}

	/**
	 * Render the PDF bytes.
	 *
	 * @param array                $template Canonical template.
	 * @param array<string,string> $values   Token => text.
	 * @return string|\WP_Error
	 */
	public static function render( array $template, array $values ) {
		if ( ! self::available() ) {
			return new \WP_Error( 'ohmylms_certificate_pdf_unavailable', __( 'PDF output is not available on this site.', 'ohmylms' ), array( 'status' => 501 ) );
		}
		$uploads = wp_get_upload_dir();
		$roots   = array_filter( array( realpath( (string) $uploads['basedir'] ), realpath( dirname( ( new \ReflectionClass( '\Dompdf\Dompdf' ) )->getFileName(), 2 ) ) ) );
		try {
			$options = new \Dompdf\Options();
			$options->set( 'isRemoteEnabled', false );
			$options->set( 'isPhpEnabled', false );
			$options->set( 'isJavascriptEnabled', false );
			$options->set( 'chroot', array_values( $roots ) );
			$options->set( 'tempDir', get_temp_dir() );
			$options->set( 'fontCache', get_temp_dir() );
			$options->set( 'defaultFont', 'DejaVu Serif' );
			$pdf = new \Dompdf\Dompdf( $options );
			$pdf->loadHtml( self::html( $template, $values ), 'UTF-8' );
			$pdf->render();

			return (string) $pdf->output();
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'ohmylms_certificate_pdf_failed', __( 'The PDF could not be created.', 'ohmylms' ), array( 'status' => 500 ) );
		}
	}

	/**
	 * Local file of an image attachment of a supported type inside the uploads folder, or ''.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private static function image_path( $attachment_id ) {
		if ( $attachment_id < 1 || 'attachment' !== get_post_type( $attachment_id ) || ! in_array( get_post_mime_type( $attachment_id ), Template::IMAGE_MIME_TYPES, true ) ) {
			return '';
		}
		$file    = get_attached_file( $attachment_id );
		$real    = $file ? realpath( $file ) : false;
		$uploads = realpath( (string) wp_get_upload_dir()['basedir'] );
		if ( ! $real || ! $uploads || 0 !== strpos( wp_normalize_path( $real ), trailingslashit( wp_normalize_path( $uploads ) ) ) ) {
			return '';
		}

		return $real;
	}

	/**
	 * Format a number for CSS: dot decimal, at most four decimals.
	 *
	 * @param float|int $value Number.
	 * @return string
	 */
	private static function number( $value ) {
		return rtrim( rtrim( number_format( (float) $value, 4, '.', '' ), '0' ), '.' );
	}
}
