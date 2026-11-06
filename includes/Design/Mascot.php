<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Site mascot: a bundled drawing or a media-library image, with a name, greeting and placement toggles.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Design;

use OhMyLMS\Schools\Settings as SchoolSettings;

defined( 'ABSPATH' ) || exit;

/**
 * One site-wide mascot. A school may store its own copy the same way it stores an accent or logo.
 * Drawings use currentColor and the --oml-* tokens, so they follow the palette instead of a fixed brand colour.
 */
final class Mascot {

	const OPTION = 'ohmylms_mascot';

	/** Built-in drawings, plus `none` and a media-library `upload`. */
	const KINDS = array( 'none', 'owl', 'robot', 'horse', 'star', 'upload' );

	/** Places a mascot may appear. Each is independent. */
	const PLACEMENTS = array( 'profile', 'empty', 'celebrate', 'quiz' );

	/** Register the option sanitizer. */
	public static function init() {
		add_filter( 'sanitize_option_' . self::OPTION, array( __CLASS__, 'sanitize' ) );
	}

	/**
	 * Values used when nothing has been saved.
	 *
	 * @return array{kind:string,attachment_id:int,name:string,greeting:string,placements:array<string,bool>}
	 */
	public static function defaults() {
		return array(
			'kind'          => 'none',
			'attachment_id' => 0,
			'name'          => '',
			'greeting'      => '',
			'placements'    => array(
				'profile'   => true,
				'empty'     => true,
				'celebrate' => true,
				'quiz'      => false,
			),
		);
	}

	/**
	 * Labels for the built-in drawings.
	 *
	 * @return array<string,string>
	 */
	public static function labels() {
		return array(
			'owl'   => __( 'Owl', 'ohmylms' ),
			'robot' => __( 'Robot', 'ohmylms' ),
			'horse' => __( 'Horse', 'ohmylms' ),
			'star'  => __( 'Star', 'ohmylms' ),
		);
	}

	/**
	 * Check a submitted mascot without changing it.
	 *
	 * @param mixed $input Submitted mascot.
	 * @return array<string,string> Field name to message; empty when valid.
	 */
	public static function validate( $input ) {
		$errors = array();
		if ( ! is_array( $input ) ) {
			return array( 'mascot' => __( 'The mascot settings must be an object.', 'ohmylms' ) );
		}
		$kind = isset( $input['kind'] ) && is_string( $input['kind'] ) ? sanitize_key( $input['kind'] ) : 'none';
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			$errors['mascot.kind'] = __( 'Choose a bundled mascot, an upload, or none.', 'ohmylms' );
		}
		$attachment = isset( $input['attachment_id'] ) ? absint( $input['attachment_id'] ) : 0;
		if ( 'upload' === $kind && ( ! $attachment || ! wp_attachment_is_image( $attachment ) ) ) {
			$errors['mascot.attachment_id'] = __( 'Choose an image from the media library.', 'ohmylms' );
		}
		$name = isset( $input['name'] ) && is_string( $input['name'] ) ? $input['name'] : '';
		if ( self::length( $name ) > 40 ) {
			$errors['mascot.name'] = __( 'The mascot name must be 40 characters or fewer.', 'ohmylms' );
		}
		$greeting = isset( $input['greeting'] ) && is_string( $input['greeting'] ) ? $input['greeting'] : '';
		if ( self::length( $greeting ) > 160 ) {
			$errors['mascot.greeting'] = __( 'The greeting must be 160 characters or fewer.', 'ohmylms' );
		}
		if ( isset( $input['placements'] ) && ! is_array( $input['placements'] ) ) {
			$errors['mascot.placements'] = __( 'Placements must be an object.', 'ohmylms' );
		}
		return $errors;
	}

	/**
	 * Clean a saved or submitted mascot. Invalid uploads fall back to none.
	 *
	 * @param mixed $input Raw mascot.
	 * @return array{kind:string,attachment_id:int,name:string,greeting:string,placements:array<string,bool>}
	 */
	public static function sanitize( $input ) {
		$defaults   = self::defaults();
		$input      = is_array( $input ) ? $input : array();
		$kind       = isset( $input['kind'] ) && is_string( $input['kind'] ) ? sanitize_key( $input['kind'] ) : $defaults['kind'];
		$kind       = in_array( $kind, self::KINDS, true ) ? $kind : 'none';
		$attachment = isset( $input['attachment_id'] ) ? absint( $input['attachment_id'] ) : 0;
		if ( 'upload' === $kind ) {
			if ( ! $attachment || ! wp_attachment_is_image( $attachment ) ) {
				$kind       = 'none';
				$attachment = 0;
			}
		} else {
			$attachment = 0;
		}
		$placements = $defaults['placements'];
		$raw        = isset( $input['placements'] ) && is_array( $input['placements'] ) ? $input['placements'] : array();
		foreach ( self::PLACEMENTS as $place ) {
			if ( array_key_exists( $place, $raw ) ) {
				$placements[ $place ] = in_array( $raw[ $place ], array( true, 1, '1' ), true );
			}
		}
		return array(
			'kind'          => $kind,
			'attachment_id' => $attachment,
			'name'          => self::clip( isset( $input['name'] ) ? $input['name'] : '', 40 ),
			'greeting'      => self::clip( isset( $input['greeting'] ) ? $input['greeting'] : '', 160 ),
			'placements'    => $placements,
		);
	}

	/**
	 * The saved site mascot.
	 *
	 * @return array{kind:string,attachment_id:int,name:string,greeting:string,placements:array<string,bool>}
	 */
	public static function get() {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Store a site mascot after sanitizing it.
	 *
	 * @param array $input Raw mascot.
	 * @return array The stored mascot.
	 */
	public static function save( $input ) {
		$clean = self::sanitize( $input );
		update_option( self::OPTION, $clean, false );
		return self::get();
	}

	/**
	 * School override when one is stored, otherwise the site mascot.
	 *
	 * @param int|null $school School ID; null uses the signed-in user's school.
	 * @return array{kind:string,attachment_id:int,name:string,greeting:string,placements:array<string,bool>}
	 */
	public static function resolve( $school = null ) {
		$school = null === $school ? SchoolSettings::school_for_user() : absint( $school );
		$own    = $school ? SchoolSettings::overrides( $school ) : array();
		if ( isset( $own['mascot'] ) && is_array( $own['mascot'] ) ) {
			return self::sanitize( $own['mascot'] );
		}
		return self::get();
	}

	/**
	 * Whether this mascot should be drawn in a placement.
	 *
	 * @param array  $mascot    Clean mascot.
	 * @param string $placement One of PLACEMENTS.
	 * @return bool
	 */
	public static function visible( array $mascot, $placement ) {
		if ( ! in_array( $placement, self::PLACEMENTS, true ) || empty( $mascot['placements'][ $placement ] ) ) {
			return false;
		}
		if ( 'upload' === ( $mascot['kind'] ?? '' ) ) {
			return ! empty( $mascot['attachment_id'] );
		}
		return isset( self::labels()[ $mascot['kind'] ?? '' ] );
	}

	/**
	 * Print the mascot partial, or nothing when this placement is off or no mascot is chosen.
	 *
	 * @param string $placement One of PLACEMENTS.
	 * @param int    $school    School ID; 0 resolves from the signed-in user.
	 */
	public static function render( $placement, $school = 0 ) {
		$mascot = self::resolve( $school ? absint( $school ) : null );
		if ( ! self::visible( $mascot, $placement ) ) {
			return;
		}
		if ( false === apply_filters( 'ohmylms_mascot_render', true, $placement, $mascot ) ) {
			return;
		}
		/**
		 * Fires before the mascot partial, so a theme can add something beside it.
		 *
		 * @param string $placement Placement key.
		 * @param array  $mascot    Clean mascot.
		 */
		do_action( 'ohmylms_mascot', $placement, $mascot );
		if ( function_exists( 'ohmylms_get_template' ) ) {
			ohmylms_get_template(
				'mascot.php',
				array(
					'mascot'    => $mascot,
					'placement' => $placement,
				)
			);
		}
	}

	/**
	 * Bundled drawing as SVG. Fills use currentColor and --oml-* tokens.
	 *
	 * @param string $kind Drawing key.
	 * @return string Safe SVG, or '' for none/upload/unknown.
	 */
	public static function svg( $kind ) {
		$drawings = array(
			'owl'   => '<svg class="oml-mascot-svg" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><ellipse cx="32" cy="38" rx="20" ry="22" fill="currentColor"/><path d="M14 22l6 8M50 22l-6 8" stroke="currentColor" stroke-width="6" stroke-linecap="round"/><circle cx="24" cy="32" r="8" fill="var(--oml-surface)"/><circle cx="40" cy="32" r="8" fill="var(--oml-surface)"/><circle cx="24" cy="33" r="4" fill="var(--oml-heading)"/><circle cx="40" cy="33" r="4" fill="var(--oml-heading)"/><path d="M29 40l3 5 3-5z" fill="var(--oml-warning)"/></svg>',
			'robot' => '<svg class="oml-mascot-svg" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><line x1="32" y1="6" x2="32" y2="14" stroke="currentColor" stroke-width="3"/><circle cx="32" cy="6" r="3" fill="var(--oml-warning)"/><rect x="12" y="14" width="40" height="32" rx="8" fill="currentColor"/><rect x="18" y="22" width="28" height="14" rx="5" fill="var(--oml-surface)"/><circle cx="26" cy="29" r="3" fill="var(--oml-heading)"/><circle cx="38" cy="29" r="3" fill="var(--oml-heading)"/><rect x="20" y="48" width="24" height="10" rx="3" fill="currentColor" opacity="0.7"/></svg>',
			'horse' => '<svg class="oml-mascot-svg" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><path d="M20 58V34c0-14 8-24 22-26l4 8-4 4 6 14-6 4-8-6-4 26z" fill="currentColor"/><path d="M28 10c-6 4-10 12-10 20" stroke="var(--oml-heading)" stroke-width="4" fill="none" stroke-linecap="round"/><circle cx="40" cy="20" r="2.5" fill="var(--oml-surface)"/></svg>',
			'star'  => '<svg class="oml-mascot-svg" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><path d="M32 4l8 18 20 2-15 13 5 20-18-11-18 11 5-20L4 24l20-2z" fill="currentColor"/><circle cx="26" cy="30" r="3" fill="var(--oml-heading)"/><circle cx="38" cy="30" r="3" fill="var(--oml-heading)"/><path d="M26 38q6 5 12 0" stroke="var(--oml-heading)" stroke-width="2.5" fill="none" stroke-linecap="round"/></svg>',
		);
		if ( ! isset( $drawings[ $kind ] ) ) {
			return '';
		}
		return wp_kses( $drawings[ $kind ], self::svg_allowed() );
	}

	/**
	 * Image URL for an uploaded mascot.
	 *
	 * @param array $mascot Clean mascot.
	 * @return string
	 */
	public static function image_url( array $mascot ) {
		if ( 'upload' !== ( $mascot['kind'] ?? '' ) || empty( $mascot['attachment_id'] ) ) {
			return '';
		}
		$url = wp_get_attachment_image_url( (int) $mascot['attachment_id'], 'medium' );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Drawings the settings screen can offer, with their SVG.
	 *
	 * @return array<int,array{kind:string,label:string,svg:string}>
	 */
	public static function catalog() {
		$out = array();
		foreach ( self::labels() as $kind => $label ) {
			$out[] = array(
				'kind'  => $kind,
				'label' => $label,
				'svg'   => self::svg( $kind ),
			);
		}
		return $out;
	}

	/**
	 * Tags and attributes used by the bundled drawings.
	 *
	 * @return array<string,array<string,bool>>
	 */
	private static function svg_allowed() {
		$paint = array(
			'fill'           => true,
			'stroke'         => true,
			'stroke-width'   => true,
			'stroke-linecap' => true,
			'opacity'        => true,
		);
		return array(
			'svg'     => array(
				'class'       => true,
				'viewbox'     => true,
				'aria-hidden' => true,
				'focusable'   => true,
			),
			'ellipse' => array(
				'cx' => true,
				'cy' => true,
				'rx' => true,
				'ry' => true,
			) + $paint,
			'circle'  => array(
				'cx' => true,
				'cy' => true,
				'r'  => true,
			) + $paint,
			'path'    => array( 'd' => true ) + $paint,
			'rect'    => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
			) + $paint,
			'line'    => array(
				'x1' => true,
				'y1' => true,
				'x2' => true,
				'y2' => true,
			) + $paint,
		);
	}

	/**
	 * Character length, counting multibyte letters when possible.
	 *
	 * @param string $value Text.
	 * @return int
	 */
	private static function length( $value ) {
		return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $value ) : strlen( $value );
	}

	/**
	 * Plain text clipped to a maximum length.
	 *
	 * @param mixed $value Raw text.
	 * @param int   $max   Maximum characters.
	 * @return string
	 */
	private static function clip( $value, $max ) {
		$value = is_string( $value ) ? sanitize_text_field( $value ) : '';
		if ( self::length( $value ) <= $max ) {
			return $value;
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}
}
