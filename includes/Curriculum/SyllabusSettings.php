<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * A grade/subject skill collection has its own profile, independent of commercial course settings.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

use OhMyLMS\Learning\Catalog;

defined( 'ABSPATH' ) || exit;

/** A grade/subject skill collection has its own profile, independent of commercial course settings. */
final class SyllabusSettings {
	const META = '_ohmylms_syllabus_settings';

	/**
	 * Defaults for Syllabus settings.
	 */
	public static function defaults() {
		return array(
			'grade'           => '',
			'subject'         => '',
			'language'        => '',
			'categories'      => array( 'Core', 'Extended', 'Advanced' ),
			'category_styles' => array(),
		);
	}

	/**
	 * Category allowed for Syllabus settings.
	 *
	 * @param mixed $category Category.
	 * @param array $categories Categories.
	 */
	public static function category_allowed( $category, array $categories ) {
		return '' === $category || in_array( $category, $categories, true );
	}

	/**
	 * Check category for Syllabus settings.
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param array $fields Fields.
	 */
	public static function check_category( $syllabus_id, array $fields ) {
		if ( ! isset( $fields['category'] ) || self::category_allowed( $fields['category'], self::get( $syllabus_id )['categories'] ) ) {
			return true; }
		return Access::error( 'ohmylms_syllabus_category_unknown', __( 'Choose a category created on the syllabus home.', 'ohmylms' ) );
	}

	/**
	 * Clean for Syllabus settings.
	 *
	 * @param array $data Input data.
	 */
	public static function clean( array $data ) {
		$clean = array();
		foreach ( array( 'grade', 'subject', 'language' ) as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue; }
			if ( ! is_string( $data[ $field ] ) ) {
				return Access::error( 'ohmylms_syllabus_settings_invalid', __( 'Syllabus profile fields must be text.', 'ohmylms' ) ); }
			$value = SyllabusRows::line( $data[ $field ] );
			if ( mb_strlen( $value ) > 100 ) {
				return Access::error( 'ohmylms_syllabus_settings_invalid', __( 'Syllabus profile fields can have at most 100 characters.', 'ohmylms' ) ); }
			$clean[ $field ] = sanitize_text_field( $value );
		}
		if ( array_key_exists( 'categories', $data ) ) {
			if ( ! is_array( $data['categories'] ) || count( $data['categories'] ) > 50 ) {
				return Access::error( 'ohmylms_syllabus_settings_invalid', __( 'Use at most 50 skill categories.', 'ohmylms' ) ); }
			$clean['categories'] = array();
			foreach ( $data['categories'] as $category ) {
				if ( ! is_string( $category ) ) {
					return Access::error( 'ohmylms_syllabus_settings_invalid', __( 'Skill categories must be text.', 'ohmylms' ) ); }
				$value = sanitize_text_field( SyllabusRows::line( $category ) );
				if ( mb_strlen( $value ) > 60 ) {
					return Access::error( 'ohmylms_syllabus_settings_invalid', __( 'A skill category can have at most 60 characters.', 'ohmylms' ) ); }
				if ( '' !== $value && ! in_array( $value, $clean['categories'], true ) ) {
					$clean['categories'][] = $value; }
			}
		}
		if ( array_key_exists( 'category_styles', $data ) ) {
			if ( ! is_array( $data['category_styles'] ) || count( $data['category_styles'] ) > 50 ) {
				return Access::error( 'ohmylms_syllabus_settings_invalid', __( 'Use at most 50 category styles.', 'ohmylms' ) );
			}
			$clean['category_styles'] = array();
			foreach ( $data['category_styles'] as $category => $style ) {
				if ( ! is_string( $category ) || '' === $category || mb_strlen( $category ) > 60 || ! is_array( $style ) ) {
					return Access::error( 'ohmylms_syllabus_settings_invalid', __( 'Invalid category style.', 'ohmylms' ) );
				}
				$icon = Icons::clean( $style['icon'] ?? '' );
				if ( is_wp_error( $icon ) ) {
					return $icon; }
				$color = $style['color'] ?? '#6e42d3';
				if ( ! is_string( $color ) || ! preg_match( '/^#[0-9a-f]{6}$/i', $color ) ) {
					return Access::error( 'ohmylms_syllabus_settings_invalid', __( 'Choose a valid category icon color.', 'ohmylms' ) );
				}
				$clean['category_styles'][ $category ] = array(
					'icon'  => $icon,
					'color' => strtolower( $color ),
				);
			}
		}
		return $clean;
	}

	/**
	 * Get for Syllabus settings.
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 */
	public static function get( $syllabus_id ) {
		$course = SyllabusCourse::course_id( $syllabus_id );
		$saved  = $course ? get_post_meta( $course, self::META, true ) : array();
		return ( is_array( $saved ) ? $saved : array() ) + self::defaults();
	}

	/**
	 * Save for Syllabus settings.
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param array $data Input data.
	 */
	public static function save( $syllabus_id, array $data ) {
		$clean = self::clean( $data );
		if ( is_wp_error( $clean ) ) {
			return $clean; }
		$course = SyllabusCourse::course_id( $syllabus_id );
		if ( ! $course ) {
			return Access::error( 'ohmylms_syllabus_missing', __( 'Open the syllabus before saving its settings.', 'ohmylms' ), 404 ); }
		$next = $clean + self::get( $syllabus_id );
		if ( isset( $clean['category_styles'] ) ) {
			foreach ( array_keys( $clean['category_styles'] ) as $category ) {
				if ( ! in_array( $category, $next['categories'], true ) ) {
					return Access::error( 'ohmylms_syllabus_category_unknown', __( 'Choose a category created on the syllabus home.', 'ohmylms' ) );
				}
			}
		}
		$next['category_styles'] = array_intersect_key( $next['category_styles'], array_fill_keys( $next['categories'], true ) );
		if ( isset( $clean['categories'] ) ) {
			$removed = array_diff( self::get( $syllabus_id )['categories'], $clean['categories'] );
			if ( $removed ) {
				$outline = Syllabus::outline( $syllabus_id );
				if ( is_wp_error( $outline ) ) {
					return $outline; }
				foreach ( $outline['contents'] as $content ) {
					foreach ( $content['groups'] as $group ) {
						foreach ( $group['skills'] as $skill ) {
							$category = $skill['category'] ?? '';
							if ( empty( $skill['category_assigned'] ) && in_array( $group['name'], self::get( $syllabus_id )['categories'], true ) ) {
								$category = $group['name']; }
							if ( in_array( $category, $removed, true ) ) {
								return Access::error( 'ohmylms_syllabus_category_used', sprintf( /* translators: %s: text value. */
								__( 'The category “%s” is used by skills. Change their category before removing it.', 'ohmylms' ), $category ), 409 );
							}
						}
					}
				}
			}
		}
		if ( $next !== self::get( $syllabus_id ) && ! update_post_meta( $course, self::META, $next ) ) {
			return Access::error( 'ohmylms_syllabus_settings_failed', __( 'The syllabus settings could not be saved.', 'ohmylms' ), 500 );
		}
		return true;
	}

	/**
	 * Publish for Syllabus settings.
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 */
	public static function publish( $syllabus_id ) {
		$course = SyllabusCourse::course_id( $syllabus_id );
		if ( ! $course ) {
			return Access::error( 'ohmylms_syllabus_missing', __( 'This syllabus is unavailable.', 'ohmylms' ), 404 ); }
		$synced = SyllabusCourse::sync( $syllabus_id );
		if ( is_wp_error( $synced ) ) {
			return $synced; }
		$published = Catalog::publish( $course );
		if ( is_wp_error( $published ) ) {
			return $published; }
		$saved = wp_update_post(
			array(
				'ID'          => $course,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $saved ) ) {
			return $saved; }
		if ( ! $saved ) {
			return Access::error( 'ohmylms_syllabus_publish_failed', __( 'The skill structure was published, but syllabus visibility could not be updated. Retry publishing.', 'ohmylms' ), 500 ); }
		return true;
	}
}
