<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Server-side certificate rendering.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Certificates;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a canonical template to self-contained HTML.
 *
 * Output safety: text is escaped with esc_html() after placeholders are replaced, URLs come only from
 * media-library attachments and go through esc_url(), every number is formatted by number_format(), and
 * colors and enumerations are re-checked against allow-lists. No user value reaches the markup unescaped,
 * and no script, external stylesheet or font is emitted.
 *
 * @since 1.0.0
 */
final class TemplateRenderer {

	/**
	 * Millimetres per typographic point.
	 */
	const MM_PER_POINT = 0.352778;

	/**
	 * Placeholder values that leave every token unchanged (used for the stored template HTML).
	 *
	 * @return array<string,string>
	 */
	public static function identity_values() {
		$values = array();
		foreach ( array_keys( Template::placeholders() ) as $token ) {
			$values[ $token ] = $token;
		}

		return $values;
	}

	/**
	 * Sample values for every registered placeholder.
	 *
	 * @return array<string,string>
	 */
	public static function sample_values() {
		$values = array();
		foreach ( Template::placeholders() as $token => $definition ) {
			$values[ $token ] = $definition['sample'];
		}

		return $values;
	}

	/**
	 * Render the certificate page as an HTML fragment.
	 *
	 * The page fills its container width and keeps the paper aspect ratio; type sizes use container
	 * query units so the layout scales identically in the preview, in print and when saved to PDF.
	 *
	 * @param array                $template Canonical template.
	 * @param array<string,string> $values   Token => replacement text.
	 * @return string HTML fragment.
	 */
	public static function fragment( array $template, array $values ) {
		$dimensions = Template::dimensions( $template );
		$fonts      = Template::font_families();
		$family     = isset( $fonts[ $template['font_family'] ] ) ? $template['font_family'] : 'serif';
		$stack      = 'serif' === $family ? 'Georgia, "Times New Roman", serif' : 'system-ui, -apple-system, "Segoe UI", Arial, sans-serif';
		$background = preg_match( '/^#[0-9a-f]{6}$/', $template['background_color'] ) ? $template['background_color'] : '#ffffff';
		$replace    = array();
		foreach ( Template::placeholders() as $token => $definition ) {
			$replace[ $token ] = isset( $values[ $token ] ) ? (string) $values[ $token ] : $token;
		}

		$html = sprintf(
			'<div class="ohmylms-certificate" style="%s">',
			esc_attr(
				sprintf(
					'position:relative;overflow:hidden;box-sizing:border-box;container-type:inline-size;width:100%%;aspect-ratio:%s / %s;background-color:%s;font-family:%s;',
					self::number( $dimensions['width'] ),
					self::number( $dimensions['height'] ),
					$background,
					$stack
				)
			)
		);

		$background_url = self::image_url( (int) $template['background_id'] );
		if ( '' !== $background_url ) {
			$html .= sprintf(
				'<img src="%s" alt="" style="%s">',
				esc_url( $background_url ),
				esc_attr( 'position:absolute;left:0;top:0;width:100%;height:100%;object-fit:cover;' )
			);
		}

		foreach ( $template['blocks'] as $block ) {
			$align = in_array( $block['align'], array( 'left', 'center', 'right' ), true ) ? $block['align'] : 'center';
			$color = preg_match( '/^#[0-9a-f]{6}$/', $block['color'] ) ? $block['color'] : '#000000';
			$size  = ( (float) $block['font_size'] * self::MM_PER_POINT / $dimensions['width'] ) * 100;
			$html .= sprintf(
				'<div data-block="%s" style="%s">%s</div>',
				esc_attr( $block['id'] ),
				esc_attr(
					sprintf(
						'position:absolute;margin:0;left:%s%%;top:%s%%;width:%s%%;color:%s;font-size:%scqw;font-weight:%s;text-align:%s;line-height:1.2;white-space:pre-wrap;overflow-wrap:anywhere;',
						self::number( $block['x'] ),
						self::number( $block['y'] ),
						self::number( $block['width'] ),
						$color,
						self::number( $size ),
						! empty( $block['bold'] ) ? '700' : '400',
						$align
					)
				),
				esc_html( strtr( (string) $block['text'], $replace ) )
			);
		}

		$signature_url = self::image_url( (int) $template['signature']['image_id'] );
		if ( '' !== $signature_url ) {
			$html .= sprintf(
				'<img src="%s" alt="" style="%s">',
				esc_url( $signature_url ),
				esc_attr(
					sprintf(
						'position:absolute;left:%s%%;top:%s%%;width:%s%%;height:auto;',
						self::number( $template['signature']['x'] ),
						self::number( $template['signature']['y'] ),
						self::number( $template['signature']['width'] )
					)
				)
			);
		}

		return $html . '</div>';
	}

	/**
	 * Render a complete document for display in a sandboxed frame.
	 *
	 * The content security policy blocks scripts, forms, frames and every network request except images.
	 *
	 * @param array                $template Canonical template.
	 * @param array<string,string> $values   Token => replacement text.
	 * @return string HTML document.
	 */
	public static function document( array $template, array $values ) {
		return '<!DOCTYPE html><html><head><meta charset="utf-8">'
			. '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src http: https: data:; style-src \'unsafe-inline\'">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<style>html,body{margin:0;padding:0;}</style></head><body>'
			. self::fragment( $template, $values )
			. '</body></html>';
	}

	/**
	 * Attachment URL for an id, or an empty string.
	 *
	 * @param int $attachment_id Attachment id.
	 * @return string
	 */
	private static function image_url( $attachment_id ) {
		if ( $attachment_id < 1 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return '';
		}

		$url = wp_get_attachment_url( $attachment_id );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Format a number for CSS: dot decimal, at most four decimals, no locale.
	 *
	 * @param float|int $value Number.
	 * @return string
	 */
	private static function number( $value ) {
		return rtrim( rtrim( number_format( (float) $value, 4, '.', '' ), '0' ), '.' );
	}
}
