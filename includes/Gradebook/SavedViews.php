<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Saved gradebook views per user.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * User meta `ohmylms_gradebook_views`: at most 50 named views, each a section and the address query of
 * that section (only the gradebook's own parameters are kept). A view stores filters, not data, so opening
 * one is always scoped again for the user.
 */
final class SavedViews {

	const META     = 'ohmylms_gradebook_views';
	const MAX      = 50;
	const SECTIONS = array( 'course', 'roster', 'student', 'mastery', 'rollup' );

	/**
	 * Address parameters a view may keep.
	 */
	const KEYS = array( 'scope', 'course', 'audience', 'status', 'type', 'module', 's', 'orderby', 'order', 'class', 'learner', 'paged' );

	/**
	 * The user's views, newest first.
	 *
	 * @param int $user_id User.
	 * @return array[]
	 */
	public static function all( $user_id ) {
		$views = get_user_meta( (int) $user_id, self::META, true );
		return is_array( $views ) ? array_values( $views ) : array();
	}

	/**
	 * Save a view (a view with the same name and section is replaced).
	 *
	 * @param int    $user_id User.
	 * @param string $name    Name (1–80 characters).
	 * @param string $section Section.
	 * @param string $query   Address query (`scope=class:3&course=12`).
	 * @return array|WP_Error The saved view.
	 */
	public static function save( $user_id, $name, $section, $query ) {
		$name = trim( sanitize_text_field( (string) $name ) );
		if ( '' === $name || mb_strlen( $name ) > 80 ) {
			return self::invalid( 'name', __( 'Name the view (at most 80 characters).', 'ohmylms' ) );
		}
		if ( ! in_array( $section, self::SECTIONS, true ) ) {
			return self::invalid( 'section', __( 'Unknown gradebook view.', 'ohmylms' ) );
		}
		if ( strlen( (string) $query ) > 1000 ) {
			return self::invalid( 'query', __( 'The view has too many filters.', 'ohmylms' ) );
		}
		$view  = array(
			'id'         => substr( md5( $section . '|' . $name . '|' . microtime() . wp_rand() ), 0, 12 ),
			'name'       => $name,
			'section'    => $section,
			'query'      => self::clean_query( (string) $query ),
			'created_at' => gmdate( DATE_ATOM ),
		);
		$views = array_values(
			array_filter(
				self::all( $user_id ),
				static function ( $existing ) use ( $view ) {
					return ! ( $existing['name'] === $view['name'] && $existing['section'] === $view['section'] );
				}
			)
		);
		array_unshift( $views, $view );
		if ( count( $views ) > self::MAX ) {
			return self::invalid( 'name', __( 'You have 50 saved views; delete one first.', 'ohmylms' ) );
		}
		update_user_meta( (int) $user_id, self::META, $views );
		return $view;
	}

	/**
	 * Delete a view.
	 *
	 * @param int    $user_id User.
	 * @param string $id      View id.
	 * @return bool Whether it existed.
	 */
	public static function delete( $user_id, $id ) {
		$views = self::all( $user_id );
		$kept  = array_values(
			array_filter(
				$views,
				static function ( $view ) use ( $id ) {
					return $view['id'] !== $id;
				}
			)
		);
		if ( count( $kept ) === count( $views ) ) {
			return false;
		}
		update_user_meta( (int) $user_id, self::META, $kept );
		return true;
	}

	/**
	 * Keep only the gradebook's own parameters, as plain strings.
	 *
	 * @param string $query Query string.
	 * @return string
	 */
	public static function clean_query( $query ) {
		parse_str( ltrim( $query, '?' ), $params );
		$clean = array();
		foreach ( self::KEYS as $key ) {
			if ( isset( $params[ $key ] ) && is_string( $params[ $key ] ) && '' !== $params[ $key ] ) {
				$clean[ $key ] = sanitize_text_field( $params[ $key ] );
			}
		}
		return http_build_query( $clean, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * 422 for one field.
	 *
	 * @param string $field   Field.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private static function invalid( $field, $message ) {
		return new WP_Error(
			'ohmylms_gradebook_invalid_view',
			$message,
			array(
				'status' => 422,
				'params' => array( $field => $message ),
			)
		);
	}
}
