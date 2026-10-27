<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * Authored course order within an existing subject curriculum item.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

defined( 'ABSPATH' ) || exit;

/** Course placements stay curriculum links; only their subject display order is stored here. */
final class SubjectCourses {

	const OPTION_PREFIX = 'ohmylms_subject_order_';

	/**
	 * Remove order storage when curriculum items are explicitly deleted.
	 *
	 * @param array $ids Deleted curriculum item IDs.
	 */
	public static function deleted( array $ids ) {
		foreach ( $ids as $id ) {
			delete_option( self::OPTION_PREFIX . (int) $id );
		}
	}

	/**
	 * Order members once, appending new links in title/ID order.
	 *
	 * @param int   $subject_id Subject ID.
	 * @param array $ids Actual member IDs, including unpublished courses for authoring.
	 * @return array
	 */
	public static function ordered( $subject_id, array $ids ) {
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		usort(
			$ids,
			static function ( $left, $right ) {
				$comparison = strnatcasecmp( get_the_title( $left ), get_the_title( $right ) );
				return $comparison ? $comparison : $left <=> $right;
			}
		);
		$stored = get_option( self::OPTION_PREFIX . (int) $subject_id, array() );
		$stored = is_array( $stored ) ? array_map( 'intval', $stored ) : array();
		$kept   = array_values( array_unique( array_intersect( $stored, $ids ) ) );
		return array_merge( $kept, array_values( array_diff( $ids, $kept ) ) );
	}

	/**
	 * Read a subject's complete authoring course list, including descendant placements.
	 *
	 * @param int $subject_id Subject ID.
	 * @return array|\WP_Error
	 */
	public static function view( $subject_id ) {
		$item = Items::get( $subject_id );
		if ( ! $item || 'subject' !== $item['item_type'] ) {
			return Access::error( 'ohmylms_subject_missing', __( 'Subject not found.', 'ohmylms' ), 404 );
		}
		$ids     = array_values(
			array_filter(
				Placement::course_ids_for_items( array( $subject_id ) ),
				static function ( $id ) {
					return Links::target_exists( 'course', $id );
				}
			)
		);
		$ids     = self::ordered( $subject_id, $ids );
		$courses = array();
		foreach ( $ids as $id ) {
			$courses[] = array(
				'id'     => $id,
				'title'  => get_the_title( $id ),
				'status' => get_post_status( $id ),
			);
		}
		return array(
			'subject_id' => (int) $subject_id,
			'url'        => add_query_arg( 'ohmylms_subject', (int) $subject_id, home_url( '/' ) ),
			'name'       => $item['name'],
			'courses'    => $courses,
			'revision'   => md5( wp_json_encode( array( $item['updated_at'], $ids ) ) ),
		);
	}

	/**
	 * Save a complete order with optimistic concurrency under the curriculum transaction.
	 *
	 * @param int    $subject_id Subject ID.
	 * @param mixed  $ids Submitted course list.
	 * @param string $revision Read revision.
	 * @return array|\WP_Error
	 */
	public static function save( $subject_id, $ids, $revision ) {
		return Items::exclusive(
			static function () use ( $subject_id, $ids, $revision ) {
				wp_cache_delete( self::OPTION_PREFIX . (int) $subject_id, 'options' );
				wp_cache_delete( 'notoptions', 'options' );
				$view = self::view( $subject_id );
				if ( is_wp_error( $view ) ) {
					return $view;
				}
				if ( ! is_string( $revision ) || ! hash_equals( $view['revision'], $revision ) ) {
					return Access::error( 'ohmylms_subject_stale', __( 'Subject courses changed. Reload before reordering.', 'ohmylms' ), 409 );
				}
				$current = array_column( $view['courses'], 'id' );
				if ( ! is_array( $ids ) || array_values( $ids ) !== $ids || count( $ids ) !== count( $current ) || array_filter(
					$ids,
					static function ( $id ) {
						return ! is_int( $id ) || $id <= 0; }
				) || count( $ids ) !== count( array_unique( $ids ) ) || array_diff( $ids, $current ) || array_diff( $current, $ids ) ) {
					return Access::error( 'ohmylms_subject_invalid', __( 'List every course in this subject exactly once.', 'ohmylms' ), 400 );
				}
				$key = self::OPTION_PREFIX . (int) $subject_id;
				if ( ! update_option( $key, $ids, false ) && get_option( $key ) !== $ids ) {
					throw new \RuntimeException( 'Subject order could not be saved.' );
				}
				Placement::changed();
				return self::view( $subject_id );
			}
		);
	}
}
