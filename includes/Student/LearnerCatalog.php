<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Learner-facing subjects and course bundles built from existing curriculum placement.
 *
 * Read-only adapter: it normalizes what curriculum items, bundles, their course links
 * and course status already say, so presentation can change without touching storage.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Placement;
use OhMyLMS\Curriculum\Roadmaps;
use OhMyLMS\Curriculum\Tree;
use OhMyLMS\Curriculum\Schema;

defined( 'ABSPATH' ) || exit;

/** Subjects and bundles with their published courses, in curriculum order. */
final class LearnerCatalog {

	/**
	 * True while the roadmap lookup runs, so the lookup is not filtered by its own guard.
	 *
	 * @var bool
	 */
	private static $resolving = false;

	/**
	 * Subjects with at least one published course, each holding its nested groups.
	 *
	 * Only published courses linked through curriculum placement appear. Draft,
	 * private and unlinked courses are never returned, and the legacy course
	 * category/tag taxonomies are not consulted. Roadmap courses are excluded:
	 * a roadmap is shared-skill mapping infrastructure, not something to browse.
	 *
	 * @param string   $search Optional course-title filter.
	 * @param string[] $types Curriculum item types that count as a subject. Empty means
	 *                        every top-level item. The outermost matching item wins and
	 *                        deeper items become groups inside it.
	 * @param bool     $include_empty Include subjects created from profiles before courses are added.
	 * @return array<int,array> Subject nodes: id, name, courses, children, total.
	 */
	public static function subjects( $search = '', array $types = array(), $include_empty = false ) {
		$keep = array();
		if ( $include_empty && '' === trim( (string) $search ) && Schema::ready() ) {
			foreach ( \OhMyLMS\Skills\SubjectProfiles::all() as $profile ) {
				if ( ! empty( $profile['curriculum_id'] ) ) {
					$keep[] = (int) $profile['curriculum_id'];
				}
			}
		}
		$items = $keep ? Items::all() : Placement::public_items();
		if ( ! $items ) {
			return array();
		}
		if ( $keep ) {
			// Raw rows are ordered by parent ID, which can change when a branch moves.
			$parents = array_column( $items, 'parent_id', 'id' );
			$order   = array_flip( array_column( $items, 'id' ) );
			usort(
				$items,
				static function ( $left, $right ) use ( $parents, $order ) {
					$depth = Tree::depth( $parents, $left['id'] ) <=> Tree::depth( $parents, $right['id'] );
					return 0 !== $depth ? $depth : $order[ $left['id'] ] <=> $order[ $right['id'] ];
				}
			);
		}
		$by_item = Placement::published_courses_by_item();
		$search  = trim( (string) $search );
		$hidden  = array_flip( self::roadmap_course_ids() );

		$nodes = array();
		foreach ( $items as $item ) {
			$courses = array();
			foreach ( $by_item[ $item['id'] ] ?? array() as $course ) {
				if ( isset( $hidden[ $course['id'] ] ) || self::excluded_by_search( $course['title'], $search ) ) {
					continue;
				}
				$courses[ $course['id'] ] = array(
					'id'    => (int) $course['id'],
					'title' => $course['title'],
					'url'   => Hub::course_url( $course['id'] ),
				);
			}
			$nodes[ $item['id'] ] = array(
				'id'        => (int) $item['id'],
				'name'      => $item['name'],
				'parent_id' => (int) $item['parent_id'],
				'courses'   => array_values( $courses ),
				'children'  => array(),
			);
		}
		// Parents precede children in tree order, so attach from the deepest end first.
		foreach ( array_reverse( array_keys( $nodes ) ) as $id ) {
			$parent = $nodes[ $id ]['parent_id'];
			if ( $parent && isset( $nodes[ $parent ] ) ) {
				array_unshift( $nodes[ $parent ]['children'], $nodes[ $id ] );
			}
		}
		$roots = array();
		foreach ( $nodes as $node ) {
			if ( ! $node['parent_id'] || ! isset( $nodes[ $node['parent_id'] ] ) ) {
				$roots[] = self::with_totals( $node );
			}
		}
		if ( $types ) {
			$type_of = array();
			foreach ( Items::all() as $row ) {
				$type_of[ (int) $row['id'] ] = $row['item_type'];
			}
			$flat = array();
			foreach ( $roots as $root ) {
				$flat = array_merge( $flat, self::outermost( $root, $types, $type_of ) );
			}
			$roots = $flat;
		}
		return array_values(
			array_filter(
				array_map(
					static function ( $node ) use ( $keep ) {
						return self::prune( $node, $keep );
					},
					$roots
				)
			)
		);
	}

	/**
	 * Published course bundles with their published courses.
	 *
	 * Viewing a bundle grants nothing; each course keeps its own access rules.
	 *
	 * @param string $search Optional course-title filter.
	 * @return array<int,array> Bundles: id, title, courses, total.
	 */
	public static function bundles( $search = '' ) {
		// Compatibility entry point for the retired catalog section.
		return array();
	}

	/** Register the front-end guard that keeps roadmap courses out of every course list. */
	public static function init() {
		Subjects::init();
		add_action( 'admin_init', array( \OhMyLMS\Skills\SubjectProfiles::class, 'upgrade_subjects' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'hide_roadmaps' ) );
	}

	/**
	 * Keep roadmap courses out of public course lists.
	 *
	 * A roadmap is shared-skill mapping infrastructure, not something to browse or enrol in, so the
	 * course archive, the course list shortcode and front-end course filters never include it.
	 * Admin screens, REST requests (the editors), single course pages and queries for one explicit
	 * course are left alone.
	 *
	 * @param \WP_Query $query Query being prepared.
	 */
	public static function hide_roadmaps( $query ) {
		if ( self::$resolving || ( is_admin() && ! wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$type = $query->get( 'post_type' );
		if ( OHMYLMS_COURSE_CPT !== $type && array( OHMYLMS_COURSE_CPT ) !== $type ) {
			return;
		}
		if ( $query->is_singular() || $query->get( 'p' ) || $query->get( 'name' ) || $query->get( 'post__in' ) ) {
			return;
		}
		$ids = self::roadmap_course_ids();
		if ( $ids ) {
			$query->set( 'post__not_in', array_values( array_unique( array_merge( array_map( 'intval', (array) $query->get( 'post__not_in' ) ), $ids ) ) ) );
		}
	}

	/**
	 * Courses that exist only to carry a roadmap's shared-skill sources.
	 *
	 * @return int[]
	 */
	public static function roadmap_course_ids() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		self::$resolving = true;
		try {
			$ids = get_posts(
				array(
					'post_type'   => OHMYLMS_COURSE_CPT,
					'post_status' => 'any',
					'fields'      => 'ids',
					'nopaging'    => true,
					'meta_key'    => Roadmaps::SOURCES_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Marker meta on a handful of roadmap courses.
				'meta_compare'    => 'EXISTS',
				'no_found_rows'   => true,
				)
			);
		} finally {
			self::$resolving = false;
		}
		$cache = array_values(
			array_filter(
				array_map( 'intval', $ids ),
				static function ( $id ) {
					return (bool) array_filter( (array) get_post_meta( $id, Roadmaps::SOURCES_META, true ) );
				}
			)
		);
		return $cache;
	}

	/**
	 * Every course ID in a subtree.
	 *
	 * @param array $node Subject node.
	 * @return int[]
	 */
	public static function course_ids( array $node ) {
		$ids = array_column( $node['courses'], 'id' );
		foreach ( $node['children'] as $child ) {
			$ids = array_merge( $ids, self::course_ids( $child ) );
		}
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Whether a title is filtered out by the search text.
	 *
	 * @param string $title Course title.
	 * @param string $search Search text.
	 * @return bool
	 */
	private static function excluded_by_search( $title, $search ) {
		return '' !== $search && false === mb_stripos( $title, $search );
	}

	/**
	 * Outermost nodes whose item type is allowed, searching through other nodes.
	 *
	 * @param array    $node Subject node.
	 * @param string[] $types Allowed item types.
	 * @param array    $type_of Item ID to item type.
	 * @return array[]
	 */
	private static function outermost( array $node, array $types, array $type_of ) {
		if ( in_array( $type_of[ $node['id'] ] ?? '', $types, true ) ) {
			return array( $node );
		}
		$found = array();
		foreach ( $node['children'] as $child ) {
			$found = array_merge( $found, self::outermost( $child, $types, $type_of ) );
		}
		return $found;
	}

	/**
	 * Drop branches with no matching course, so a search shows only relevant groups.
	 *
	 * @param array $node Subject node.
	 * @param int[] $keep Profile subject IDs that may be empty.
	 * @return array|null Pruned node, or null when empty.
	 */
	private static function prune( array $node, array $keep = array() ) {
		$node['children'] = array_values(
			array_filter(
				array_map(
					static function ( $child ) use ( $keep ) {
						return self::prune( $child, $keep );
					},
					$node['children']
				)
			)
		);
		return $node['total'] > 0 || $node['children'] || in_array( $node['id'], $keep, true ) ? $node : null;
	}

	/**
	 * Distinct course count for a node and everything beneath it.
	 *
	 * @param array $node Subject node.
	 * @return array Node with a recalculated total.
	 */
	private static function with_totals( array $node ) {
		$node['children'] = array_map( array( __CLASS__, 'with_totals' ), $node['children'] );
		$seen             = array_column( $node['courses'], 'id' );
		foreach ( $node['children'] as $child ) {
			$seen = array_merge( $seen, self::course_ids( $child ) );
		}
		$node['total'] = count( array_unique( $seen ) );
		return $node;
	}
}
