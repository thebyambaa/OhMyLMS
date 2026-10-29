<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * TagController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Bundles\Bundles;
use OhMyLMS\Curriculum\Placement;
use OhMyLMS\Curriculum\Schema;
use WP_Error;
use WP_REST_Request;

/**
 * TagController class.
 *
 * Course tags were replaced by Course Bundles, so this is a read-only, paged view of the bundles in
 * the shape of the former tag terms. Writes are refused with 410: bundles are managed with
 * `/bundles` and a course's bundles with `/courses/{id}/organization`. All behaviour (permissions,
 * paging, schema, refused writes) is inherited from CategoryController; only the data source and
 * the messages differ. See docs/api/taxonomies.md.
 *
 * @since 1.0.0
 */
class TagController extends CategoryController {

	/**
	 * The base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'tags';

	/**
	 * Singular name used in error codes.
	 *
	 * @var string
	 */
	protected $singular = 'tag';

	/**
	 * Bundles have no parent.
	 *
	 * @var bool
	 */
	protected $hierarchical = false;

	/**
	 * Refuse a write: bundles are managed elsewhere.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error
	 */
	public function replaced( $request ) {
		return new WP_Error(
			'ohmylms_tags_replaced',
			__( 'Course tags were replaced by Course Bundles. Manage them under OhMyLMS > Course Bundles.', 'ohmylms' ),
			array( 'status' => 410 )
		);
	}

	/**
	 * Read every bundle as a normalised row.
	 *
	 * @return array[]
	 */
	protected function load_rows() {
		if ( ! Schema::ready() ) {
			return array();
		}

		$rows = array();
		foreach ( Bundles::all() as $row ) {
			$id     = (int) $row['id'];
			$rows[] = array(
				'id'          => $id,
				'name'        => (string) $row['title'],
				'slug'        => Placement::bundle_slug( $id ),
				'description' => (string) $row['description'],
				'parent'      => 0,
			);
		}
		return $rows;
	}

	/**
	 * Count courses of several bundles (direct members plus courses under member items).
	 *
	 * A bundle's courses come from two member kinds, so this costs a few queries per bundle on the
	 * requested page (at most `per_page`).
	 *
	 * @param int[] $ids Bundle IDs.
	 * @return array<int,int> Counts keyed by ID.
	 */
	protected function count_courses( array $ids ) {
		$counts = array();
		foreach ( array_values( array_filter( array_map( 'intval', $ids ) ) ) as $id ) {
			$counts[ $id ] = count( Placement::course_ids_for_bundles( array( $id ) ) );
		}
		return $counts;
	}

	/**
	 * IDs of the courses of one bundle.
	 *
	 * @param int $id Bundle ID.
	 * @return int[]
	 */
	protected function course_ids( $id ) {
		return Placement::course_ids_for_bundles( array( $id ) );
	}
}
