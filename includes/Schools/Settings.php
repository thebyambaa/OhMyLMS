<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Per-school overrides of the site-wide hub design. Only values a school changed are stored.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

use OhMyLMS\Design\HubIcons;
use OhMyLMS\Student\Hub;

defined( 'ABSPATH' ) || exit;

/** Overridable keys: `accent` (hex colour) and `logo` (image attachment ID). Everything else inherits the site value. */
final class Settings {

	const OPTION = 'ohmylms_school_settings_';

	/** Minimum contrast of white text on the accent, so buttons stay readable. */
	const MIN_CONTRAST = 4.5;

	/**
	 * Stored overrides for a school.
	 *
	 * @param int $school School ID.
	 * @return array{accent?:string,logo?:int}
	 */
	public static function overrides( $school ) {
		$stored = get_option( self::OPTION . absint( $school ), array() );
		return self::clean( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Resolved value: school override, else the site-wide value.
	 *
	 * @param string $key    `accent` or `logo` (logo resolves to an image URL).
	 * @param int    $school School ID; 0 means site-wide only.
	 * @param string $role   Workspace for the site-wide logo fallback.
	 * @return string
	 */
	public static function get( $key, $school = 0, $role = 'learner' ) {
		$own = $school ? self::overrides( $school ) : array();
		if ( 'accent' === $key ) {
			return $own['accent'] ?? Hub::settings()['accent'];
		}
		if ( 'logo' === $key ) {
			if ( ! empty( $own['logo'] ) ) {
				return (string) wp_get_attachment_image_url( $own['logo'], 'thumbnail' );
			}
			$url = HubIcons::url( 'logo_' . sanitize_key( $role ) );
			return '' !== $url ? $url : HubIcons::url( 'logo' );
		}
		return '';
	}

	/**
	 * Inline hub accent for a light and a dark theme, each with the text color that reads on it (the dark accent is lightened to stay visible on the dark card). The button edge and tints are derived from the accent in student-hub.css.
	 *
	 * @param string $hex Accent colour.
	 * @return string Inline custom properties for the hub root element.
	 */
	public static function style( $hex ) {
		$hex = sanitize_hex_color( $hex );
		if ( ! $hex ) {
			return '';
		}
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$dark = \OhMyLMS\Design\DesignSystem::for_dark( '#' . $hex );
		return '--hub-accent-light:#' . $hex . ';--hub-on-accent-light:' . \OhMyLMS\Design\DesignSystem::text_on( '#' . $hex )
			. ';--hub-accent-dark:' . $dark . ';--hub-on-accent-dark:' . \OhMyLMS\Design\DesignSystem::text_on( $dark );
	}

	/**
	 * Save a school's overrides after authorization. A blank accent or a removed logo returns to the site value.
	 *
	 * @param int   $school School ID.
	 * @param array $data   `accent`, `logo_id`, `remove_logo`.
	 * @return array Stored overrides.
	 */
	public static function save( $school, array $data ) {
		$school = absint( $school );
		Access::require_access( Access::school( $school ) );
		$next   = self::overrides( $school );
		$accent = isset( $data['accent'] ) ? trim( (string) $data['accent'] ) : '';
		if ( '' === $accent || ! empty( $data['reset_accent'] ) ) {
			unset( $next['accent'] );
		} else {
			$hex = sanitize_hex_color( $accent );
			Service::need( $hex, 'Choose a valid colour.' );
			Service::need( self::contrast_with_white( $hex ) >= self::MIN_CONTRAST, 'That colour is too light for white button text. Choose a darker one.' );
			$next['accent'] = $hex;
		}
		if ( ! empty( $data['remove_logo'] ) ) {
			unset( $next['logo'] );
		}
		if ( ! empty( $data['logo_id'] ) ) {
			$next['logo'] = absint( $data['logo_id'] );
		}
		$next = self::clean( $next );
		if ( $next ) {
			update_option( self::OPTION . $school, $next, false );
		} else {
			delete_option( self::OPTION . $school );
		}
		Service::audit( $school, 'school_design_updated', $school );
		return $next;
	}

	/**
	 * First active school the signed-in user belongs to, for non-admin workspaces.
	 *
	 * @param int $user User ID; defaults to the signed-in user.
	 * @return int School ID, or 0.
	 */
	public static function school_for_user( $user = 0 ) {
		global $wpdb;
		$user = $user ? absint( $user ) : get_current_user_id();
		if ( ! $user || Schema::VERSION !== get_option( 'ohmylms_school_schema' ) ) {
			return 0;
		}
		$m = Schema::table( 'school_memberships' );
		$s = Schema::table( 'schools' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT m.school_id FROM %i m JOIN %i s ON s.id=m.school_id WHERE m.user_id=%d AND m.status='active' AND s.status='active' ORDER BY m.id LIMIT 1", $m, $s, $user ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh membership lookup.
	}

	/**
	 * Drop unknown keys and invalid values.
	 *
	 * @param array $input Raw overrides.
	 * @return array
	 */
	private static function clean( array $input ) {
		$out = array();
		$hex = isset( $input['accent'] ) && is_string( $input['accent'] ) ? sanitize_hex_color( $input['accent'] ) : '';
		if ( $hex && self::contrast_with_white( $hex ) >= self::MIN_CONTRAST ) {
			$out['accent'] = $hex;
		}
		$logo = isset( $input['logo'] ) ? absint( $input['logo'] ) : 0;
		if ( $logo && wp_attachment_is_image( $logo ) ) {
			$out['logo'] = $logo;
		}
		return $out;
	}

	/**
	 * WCAG contrast ratio of white against a colour.
	 *
	 * @param string $hex #rgb or #rrggbb.
	 * @return float
	 */
	public static function contrast_with_white( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$lum = 0.0;
		foreach ( array( array( 0.2126, 0 ), array( 0.7152, 2 ), array( 0.0722, 4 ) ) as list( $weight, $at ) ) {
			$c    = hexdec( substr( $hex, $at, 2 ) ) / 255;
			$lum += $weight * ( $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 ) );
		}
		return 1.05 / ( $lum + 0.05 );
	}
}
