<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Certificate template model: defaults, validation and canonical form.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Certificates;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Certificate template model.
 *
 * A template is a small, versioned, fully validated structure stored in the `_contents` post meta of a
 * certificate post. Elements are positioned with numeric percentages of the page (there is no free-form
 * canvas), so the same numbers validate, render and test identically on server and client.
 *
 * @since 1.0.0
 */
final class Template {

	/**
	 * Stored structure version.
	 */
	const VERSION = 1;

	/**
	 * Maximum text blocks per template.
	 */
	const MAX_BLOCKS = 20;

	/**
	 * Maximum characters in one text block.
	 */
	const MAX_TEXT = 500;

	/**
	 * Smallest font size in points.
	 */
	const MIN_FONT_SIZE = 8;

	/**
	 * Largest font size in points.
	 */
	const MAX_FONT_SIZE = 120;

	/**
	 * Attachment MIME types accepted for the background and the signature.
	 */
	const IMAGE_MIME_TYPES = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );

	/**
	 * Orientation choices.
	 *
	 * @return array<string,string> Value => label.
	 */
	public static function orientations() {
		return array(
			'landscape' => __( 'Landscape', 'ohmylms' ),
			'portrait'  => __( 'Portrait', 'ohmylms' ),
		);
	}

	/**
	 * Page sizes in millimetres, defined in landscape.
	 *
	 * @return array<string,array{label:string,width:float,height:float}> Value => definition.
	 */
	public static function sizes() {
		return array(
			'a4'     => array(
				'label'  => __( 'A4 (297 x 210 mm)', 'ohmylms' ),
				'width'  => 297.0,
				'height' => 210.0,
			),
			'letter' => array(
				'label'  => __( 'US Letter (279.4 x 215.9 mm)', 'ohmylms' ),
				'width'  => 279.4,
				'height' => 215.9,
			),
		);
	}

	/**
	 * Font family choices (generic CSS families only; nothing is downloaded).
	 *
	 * @return array<string,string> Value => label.
	 */
	public static function font_families() {
		return array(
			'serif'      => __( 'Serif', 'ohmylms' ),
			'sans-serif' => __( 'Sans-serif', 'ohmylms' ),
		);
	}

	/**
	 * Text alignment choices.
	 *
	 * @return array<string,string> Value => label.
	 */
	public static function alignments() {
		return array(
			'left'   => __( 'Left', 'ohmylms' ),
			'center' => __( 'Center', 'ohmylms' ),
			'right'  => __( 'Right', 'ohmylms' ),
		);
	}

	/**
	 * Placeholders usable in text blocks.
	 *
	 * @return array<string,array{label:string,sample:string}> Token (with braces) => definition.
	 */
	public static function placeholders() {
		$placeholders = array(
			'{student_name}'     => array(
				'label'  => __( 'Student name', 'ohmylms' ),
				'sample' => __( 'Alex Morgan', 'ohmylms' ),
			),
			'{course_name}'      => array(
				'label'  => __( 'Course name', 'ohmylms' ),
				'sample' => __( 'Introduction to Learning', 'ohmylms' ),
			),
			'{date}'             => array(
				'label'  => __( 'Completion date', 'ohmylms' ),
				'sample' => wp_date( get_option( 'date_format' ) ),
			),
			'{site_name}'        => array(
				'label'  => __( 'Site name', 'ohmylms' ),
				'sample' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			),
			'{certificate_code}' => array(
				'label'  => __( 'Verification code', 'ohmylms' ),
				'sample' => 'ABCD-EFGH-JKMN-PQRS',
			),
		);

		/**
		 * Filters the placeholders available in certificate text blocks.
		 *
		 * @param array $placeholders Token (for example `{student_name}`) => array with `label` and `sample`.
		 *
		 * @since 1.0.0
		 */
		$filtered = apply_filters( 'ohmylms_certificate_placeholders', $placeholders );
		$valid    = array();
		if ( is_array( $filtered ) ) {
			foreach ( $filtered as $token => $definition ) {
				if ( is_string( $token ) && 1 === preg_match( '/^\{[a-z][a-z0-9_]{0,39}\}$/', $token ) && is_array( $definition ) ) {
					$valid[ $token ] = array(
						'label'  => isset( $definition['label'] ) ? (string) $definition['label'] : $token,
						'sample' => isset( $definition['sample'] ) ? (string) $definition['sample'] : '',
					);
				}
			}
		}

		return $valid;
	}

	/**
	 * Default template, with a sensible set of text blocks.
	 *
	 * @return array
	 */
	public static function defaults() {
		$block = static function ( $id, $text, $y, $size, $bold ) {
			return array(
				'id'        => $id,
				'text'      => $text,
				'x'         => 10.0,
				'y'         => $y,
				'width'     => 80.0,
				'font_size' => $size,
				'align'     => 'center',
				'color'     => '#1d2327',
				'bold'      => $bold,
			);
		};

		return array(
			'version'          => self::VERSION,
			'orientation'      => 'landscape',
			'size'             => 'a4',
			'font_family'      => 'serif',
			'background_color' => '#ffffff',
			'background_id'    => 0,
			'signature'        => array(
				'image_id' => 0,
				'x'        => 65.0,
				'y'        => 80.0,
				'width'    => 25.0,
			),
			'blocks'           => array(
				$block( 'title', __( 'Certificate of Completion', 'ohmylms' ), 18.0, 40.0, true ),
				$block( 'intro', __( 'This certifies that', 'ohmylms' ), 36.0, 18.0, false ),
				$block( 'student', '{student_name}', 44.0, 34.0, true ),
				$block( 'course', __( 'has successfully completed {course_name}', 'ohmylms' ), 60.0, 20.0, false ),
				$block( 'date', __( 'Issued on {date}', 'ohmylms' ), 76.0, 14.0, false ),
			),
		);
	}

	/**
	 * Page dimensions in millimetres for a template.
	 *
	 * @param array $template Canonical template.
	 * @return array{width:float,height:float}
	 */
	public static function dimensions( array $template ) {
		$sizes = self::sizes();
		$size  = isset( $sizes[ $template['size'] ] ) ? $sizes[ $template['size'] ] : $sizes['a4'];

		if ( 'portrait' === $template['orientation'] ) {
			return array(
				'width'  => $size['height'],
				'height' => $size['width'],
			);
		}

		return array(
			'width'  => $size['width'],
			'height' => $size['height'],
		);
	}

	/**
	 * Placeholder tokens in a text that are not registered.
	 *
	 * @param string $text Text to scan.
	 * @return string[] Unknown tokens, unique, in order of appearance.
	 */
	public static function unknown_placeholders( $text ) {
		$known   = array_keys( self::placeholders() );
		$unknown = array();
		if ( preg_match_all( '/\{[^{}]*\}/', (string) $text, $matches ) ) {
			foreach ( $matches[0] as $token ) {
				if ( ! in_array( $token, $known, true ) && ! in_array( $token, $unknown, true ) ) {
					$unknown[] = $token;
				}
			}
		}

		return $unknown;
	}

	/**
	 * Read a stored template, falling back to the defaults for anything missing or invalid.
	 *
	 * @param mixed $stored Value of the `_contents` meta.
	 * @return array Canonical template.
	 */
	public static function from_stored( $stored ) {
		if ( ! is_array( $stored ) ) {
			return self::defaults();
		}

		$template = self::normalize( $stored, self::defaults(), false );

		return is_wp_error( $template ) ? self::defaults() : $template;
	}

	/**
	 * Validate input and merge it onto a base template.
	 *
	 * Only the keys present in `$input` change. Every problem answers a 422 WP_Error before anything is
	 * written by the caller.
	 *
	 * @param array      $input        Writable template fields.
	 * @param array|null $base         Template to start from (defaults when null).
	 * @param bool       $verify_media Verify attachments (skip when reading stored data).
	 * @return array|WP_Error Canonical template or error.
	 */
	public static function normalize( array $input, $base = null, $verify_media = true ) {
		$template = is_array( $base ) ? $base : self::defaults();

		foreach ( array(
			'orientation' => self::orientations(),
			'size'        => self::sizes(),
			'font_family' => self::font_families(),
		) as $key => $choices ) {
			if ( array_key_exists( $key, $input ) ) {
				if ( ! is_string( $input[ $key ] ) || ! isset( $choices[ $input[ $key ] ] ) ) {
					return self::error( 'ohmylms_certificate_invalid_option', $key, __( 'Choose a supported orientation, page size and font family.', 'ohmylms' ) );
				}
				$template[ $key ] = $input[ $key ];
			}
		}

		if ( array_key_exists( 'background_color', $input ) ) {
			$color = self::color( $input['background_color'] );
			if ( null === $color ) {
				return self::error( 'ohmylms_certificate_invalid_color', 'background_color', __( 'Colors must be six-digit hexadecimal values such as #ffffff.', 'ohmylms' ) );
			}
			$template['background_color'] = $color;
		}

		if ( array_key_exists( 'background_id', $input ) ) {
			$checked = self::image_id( $input['background_id'], 'background_id', $verify_media );
			if ( is_wp_error( $checked ) ) {
				return $checked;
			}
			$template['background_id'] = $checked;
		}

		if ( array_key_exists( 'signature', $input ) ) {
			$signature = self::signature( $input['signature'], $template['signature'], $verify_media );
			if ( is_wp_error( $signature ) ) {
				return $signature;
			}
			$template['signature'] = $signature;
		}

		if ( array_key_exists( 'blocks', $input ) ) {
			$blocks = self::blocks( $input['blocks'] );
			if ( is_wp_error( $blocks ) ) {
				return $blocks;
			}
			$template['blocks'] = $blocks;
		}

		$template['version'] = self::VERSION;

		return $template;
	}

	/**
	 * Validate the text blocks.
	 *
	 * @param mixed $blocks Raw blocks.
	 * @return array|WP_Error Canonical blocks or error.
	 */
	private static function blocks( $blocks ) {
		if ( ! is_array( $blocks ) || count( $blocks ) > self::MAX_BLOCKS ) {
			return self::error( 'ohmylms_certificate_invalid_blocks', 'blocks', __( 'Use no more than 20 text blocks.', 'ohmylms' ) );
		}

		$result = array();
		$ids    = array();
		foreach ( array_values( $blocks ) as $block ) {
			if ( ! is_array( $block ) ) {
				return self::error( 'ohmylms_certificate_invalid_blocks', 'blocks', __( 'Each text block must be an object.', 'ohmylms' ) );
			}

			$id = isset( $block['id'] ) && is_string( $block['id'] ) ? $block['id'] : '';
			if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{1,32}$/', $id ) ) {
				return self::error( 'ohmylms_certificate_invalid_blocks', 'blocks', __( 'Each text block needs an id of 1 to 32 letters, numbers, dashes or underscores.', 'ohmylms' ) );
			}
			if ( isset( $ids[ $id ] ) ) {
				return self::error( 'ohmylms_certificate_duplicate_block', $id, __( 'Text block ids must be unique.', 'ohmylms' ), array( 'block_id' => $id ) );
			}
			$ids[ $id ] = true;

			$text = isset( $block['text'] ) && is_string( $block['text'] ) ? trim( sanitize_textarea_field( $block['text'] ) ) : '';
			if ( '' === $text || mb_strlen( $text ) > self::MAX_TEXT ) {
				return self::error( 'ohmylms_certificate_invalid_text', $id, __( 'Text blocks need 1 to 500 characters of plain text.', 'ohmylms' ), array( 'block_id' => $id ) );
			}

			$unknown = self::unknown_placeholders( $text );
			if ( $unknown ) {
				return self::error(
					'ohmylms_certificate_unknown_placeholder',
					$id,
					/* translators: %s: comma separated placeholders. */
					sprintf( __( 'Unknown placeholder: %s', 'ohmylms' ), implode( ', ', $unknown ) ),
					array(
						'block_id'     => $id,
						'placeholders' => $unknown,
					)
				);
			}

			$alignments = self::alignments();
			$align      = isset( $block['align'] ) && is_string( $block['align'] ) ? $block['align'] : 'center';
			$color      = self::color( isset( $block['color'] ) ? $block['color'] : '#1d2327' );
			if ( ! isset( $alignments[ $align ] ) || null === $color ) {
				return self::error( 'ohmylms_certificate_invalid_option', $id, __( 'Choose a supported alignment and a six-digit hexadecimal color.', 'ohmylms' ), array( 'block_id' => $id ) );
			}

			$x         = self::number( isset( $block['x'] ) ? $block['x'] : null, 0, 100 );
			$y         = self::number( isset( $block['y'] ) ? $block['y'] : null, 0, 100 );
			$width     = self::number( isset( $block['width'] ) ? $block['width'] : null, 1, 100 );
			$font_size = self::number( isset( $block['font_size'] ) ? $block['font_size'] : null, self::MIN_FONT_SIZE, self::MAX_FONT_SIZE );
			$bold      = isset( $block['bold'] ) ? filter_var( $block['bold'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) : false;
			if ( null === $x || null === $y || null === $width || null === $font_size || null === $bold ) {
				return self::error( 'ohmylms_certificate_invalid_geometry', $id, __( 'Position, width and font size must be numbers within range.', 'ohmylms' ), array( 'block_id' => $id ) );
			}
			if ( $x + $width > 100.0001 ) {
				return self::error( 'ohmylms_certificate_block_out_of_bounds', $id, __( 'A text block must stay inside the page: left position plus width cannot exceed 100%.', 'ohmylms' ), array( 'block_id' => $id ) );
			}

			$result[] = array(
				'id'        => $id,
				'text'      => $text,
				'x'         => $x,
				'y'         => $y,
				'width'     => $width,
				'font_size' => $font_size,
				'align'     => $align,
				'color'     => $color,
				'bold'      => $bold,
			);
		}

		return $result;
	}

	/**
	 * Validate the signature image placement.
	 *
	 * @param mixed $signature    Raw signature object.
	 * @param array $current      Current canonical signature.
	 * @param bool  $verify_media Verify the attachment.
	 * @return array|WP_Error Canonical signature or error.
	 */
	private static function signature( $signature, array $current, $verify_media ) {
		if ( ! is_array( $signature ) ) {
			return self::error( 'ohmylms_certificate_invalid_geometry', 'signature', __( 'The signature must be an object.', 'ohmylms' ) );
		}

		$merged = array_merge( $current, array_intersect_key( $signature, $current ) );
		$image  = self::image_id( $merged['image_id'], 'signature', $verify_media );
		if ( is_wp_error( $image ) ) {
			return $image;
		}

		$x     = self::number( $merged['x'], 0, 100 );
		$y     = self::number( $merged['y'], 0, 100 );
		$width = self::number( $merged['width'], 1, 100 );
		if ( null === $x || null === $y || null === $width ) {
			return self::error( 'ohmylms_certificate_invalid_geometry', 'signature', __( 'Position and width must be numbers within range.', 'ohmylms' ) );
		}
		if ( $x + $width > 100.0001 ) {
			return self::error( 'ohmylms_certificate_block_out_of_bounds', 'signature', __( 'The signature must stay inside the page: left position plus width cannot exceed 100%.', 'ohmylms' ) );
		}

		return array(
			'image_id' => $image,
			'x'        => $x,
			'y'        => $y,
			'width'    => $width,
		);
	}

	/**
	 * Validate an attachment id (0 means none).
	 *
	 * Only existing image attachments the current user may edit are accepted; arbitrary URLs and file
	 * paths are never read, so nothing outside the media library can be embedded.
	 *
	 * @param mixed  $value        Raw id.
	 * @param string $field        Field name for error details.
	 * @param bool   $verify_media Verify the attachment.
	 * @return int|WP_Error Attachment id or error.
	 */
	private static function image_id( $value, $field, $verify_media ) {
		if ( is_string( $value ) && ctype_digit( $value ) ) {
			$value = (int) $value;
		}
		if ( ! is_int( $value ) || $value < 0 ) {
			return self::error( 'ohmylms_certificate_invalid_image', $field, __( 'Choose an image from the media library.', 'ohmylms' ) );
		}
		if ( 0 === $value || ! $verify_media ) {
			return $value;
		}

		$attachment = get_post( $value );
		if (
			! $attachment ||
			'attachment' !== $attachment->post_type ||
			! in_array( get_post_mime_type( $attachment ), self::IMAGE_MIME_TYPES, true ) ||
			! wp_get_attachment_url( $value ) ||
			! current_user_can( 'edit_post', $value )
		) {
			return self::error( 'ohmylms_certificate_invalid_image', $field, __( 'Choose a JPEG, PNG, GIF or WebP image from the media library.', 'ohmylms' ) );
		}

		return $value;
	}

	/**
	 * Normalize a hex color.
	 *
	 * @param mixed $value Raw color.
	 * @return string|null Lowercase `#rrggbb`, or null when invalid.
	 */
	private static function color( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? strtolower( $value ) : null;
	}

	/**
	 * Normalize a bounded number.
	 *
	 * @param mixed $value Raw number (int, float or numeric string).
	 * @param float $min   Minimum.
	 * @param float $max   Maximum.
	 * @return float|null Value rounded to two decimals, or null when invalid.
	 */
	private static function number( $value, $min, $max ) {
		if ( is_string( $value ) && '' !== trim( $value ) && is_numeric( $value ) ) {
			$value = $value + 0;
		}
		if ( ( ! is_int( $value ) && ! is_float( $value ) ) || is_nan( (float) $value ) || is_infinite( (float) $value ) ) {
			return null;
		}
		$value = round( (float) $value, 2 );

		return ( $value < $min || $value > $max ) ? null : $value;
	}

	/**
	 * Build a 422 error.
	 *
	 * @param string $code    Error code.
	 * @param string $field   Field (or block id) the problem belongs to.
	 * @param string $message Message.
	 * @param array  $extra   Extra error data.
	 * @return WP_Error
	 */
	private static function error( $code, $field, $message, array $extra = array() ) {
		return new WP_Error(
			$code,
			$message,
			array_merge(
				array(
					'status' => 422,
					'field'  => $field,
				),
				$extra
			)
		);
	}
}
