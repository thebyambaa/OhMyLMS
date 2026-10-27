<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Where a course sits: the curriculum items it is linked to and the Course Bundles it belongs to.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

use OhMyLMS\Bundles\Bundles;

defined( 'ABSPATH' ) || exit;

/**
 * Where a course sits: the curriculum items it is linked to and the Course Bundles it belongs to.
 *
 * This is the replacement for course categories and tags. Everything that used to read or write
 * `course_category` or `course_tag` (the editor, course lists and filters, shortcodes, membership
 * plans, importers) goes through here, so curriculum and bundles are the only way to organize courses.
 * The old taxonomies stay registered and keep their stored terms, but nothing consults them.
 *
 *  - Curriculum items play the part of hierarchical categories: filtering by an item includes
 *    everything linked under its descendants.
 *  - A Course Bundle plays the part of a tag, but is curated: its members are courses, and the
 *    courses linked under any curriculum item that is a member.
 */
final class Placement {
	/** Filter slugs are synthetic so the public filter markup and script work unchanged. */
	const ITEM_PREFIX   = 'c';
	const BUNDLE_PREFIX = 'b';

	/**
	 * Item slug for Placement.
	 *
	 * @param mixed $id Id.
	 */
	public static function item_slug( $id ) {
		return self::ITEM_PREFIX . (int) $id; }
	/**
	 * Bundle slug for Placement.
	 *
	 * @param mixed $id Id.
	 */
	public static function bundle_slug( $id ) {
		return self::BUNDLE_PREFIX . (int) $id; }

	/** Tell dependents (membership plans) that course placement changed. */
	public static function changed() {
		do_action( 'ohmylms_curriculum_changed' );
	}

	/**
	 * Ready for Placement.
	 */
	private static function ready() {
		return Schema::ready();
	}

	/** IDs from filter slugs of one kind ('item' or 'bundle'); anything else is ignored.
	 *
	 * @param mixed $slugs Slugs.
	 * @param mixed $kind Kind.
	 */
	public static function ids_from_slugs( $slugs, $kind ) {
		$prefix = 'bundle' === $kind ? self::BUNDLE_PREFIX : self::ITEM_PREFIX;
		$ids    = array();
		foreach ( (array) $slugs as $slug ) {
			if ( is_string( $slug ) && preg_match( '/^' . $prefix . '(\d+)$/D', $slug, $match ) ) {
				$ids[] = (int) $match[1]; }
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * IDs from a shortcode, block or widget value such as "12, c14" (numbers, or the c<id> / t<id>
	 * slug of that kind). A value that is set but names nothing valid yields [0] so a mistyped list
	 * shows no courses instead of silently showing all of them.
	 *
	 * @param mixed $value Value to process.
	 * @param mixed $kind Kind.
	 */
	public static function ids_from_list( $value, $kind ) {
		$prefix = 'bundle' === $kind ? self::BUNDLE_PREFIX : self::ITEM_PREFIX;
		$parts  = is_array( $value ) ? $value : preg_split( '/\s*,\s*/', trim( (string) $value ), -1, PREG_SPLIT_NO_EMPTY );
		$ids    = array();
		foreach ( $parts as $part ) {
			if ( is_scalar( $part ) && preg_match( '/^(?:' . $prefix . ')?(\d+)$/D', trim( (string) $part ), $match ) && (int) $match[1] > 0 ) {
				$ids[] = (int) $match[1]; }
		}
		$ids = array_values( array_unique( $ids ) );
		return $ids ?: ( $parts ? array( 0 ) : array() );
	}

	// ---- Reading a course's placement ------------------------------------------------------

	/**
	 * Item ids for Placement.
	 *
	 * @return int[] Curriculum items the course is directly linked to.
	 * @param mixed $course_id Course identifier.
	 */
	public static function item_ids( $course_id ) {
		global $wpdb;
		if ( ! self::ready() ) {
			return array(); }
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT item_id FROM ' . Links::table() . " WHERE object_type='course' AND object_id=%d ORDER BY item_id", (int) $course_id ) ) );
	}

	/** Linked curriculum items with their ancestry, for display.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public static function items( $course_id ) {
		$rows = array();
		foreach ( self::item_ids( $course_id ) as $id ) {
			$item = Items::get( $id );
			if ( ! $item ) {
				continue; }
			$rows[] = array(
				'id'        => (int) $item['id'],
				'name'      => $item['name'],
				'slug'      => self::item_slug( $id ),
				'item_type' => $item['item_type'],
				'code'      => $item['code'],
				'version'   => $item['version'],
				'path'      => Items::path_names( $id, false ),
			);
		}
		return $rows;
	}

	/**
	 * Bundle ids for Placement.
	 *
	 * @return int[] Bundles the course is a direct member of.
	 * @param mixed $course_id Course identifier.
	 */
	public static function bundle_ids( $course_id ) {
		global $wpdb;
		if ( ! self::ready() ) {
			return array(); }
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT bundle_id FROM ' . Bundles::members_table() . " WHERE item_type='course' AND item_id=%d ORDER BY bundle_id", (int) $course_id ) ) );
	}

	/**
	 * Bundles for Placement.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public static function bundles( $course_id ) {
		$rows = array();
		foreach ( self::bundle_ids( $course_id ) as $id ) {
			$bundle = Bundles::get( $id );
			if ( $bundle ) {
				$rows[] = array(
					'id'     => (int) $bundle['id'],
					'title'  => $bundle['title'],
					'slug'   => self::bundle_slug( $id ),
					'status' => $bundle['status'],
				); }
		}
		return $rows;
	}

	// ---- Which courses are under items or bundles -------------------------------------------

	/** Courses linked to the given items, and by default to everything beneath them.
	 *
	 * @param array $item_ids Item ids.
	 * @param bool  $descendants Descendants.
	 */
	public static function course_ids_for_items( array $item_ids, $descendants = true ) {
		$item_ids = array_values( array_unique( array_filter( array_map( 'intval', $item_ids ) ) ) );
		if ( ! $item_ids || ! self::ready() ) {
			return array(); }
		$all = $item_ids;
		if ( $descendants ) {
			$parents = Items::parents();
			foreach ( $item_ids as $id ) {
				$all = array_merge( $all, Tree::descendants( $parents, $id ) ); }
		}
		return Links::object_ids( $all, 'course' );
	}

	/** Courses a bundle refers to: direct course members plus courses linked under its curriculum members.
	 *
	 * @param array $bundle_ids Bundle ids.
	 */
	public static function course_ids_for_bundles( array $bundle_ids ) {
		global $wpdb;
		$bundle_ids = array_values( array_unique( array_filter( array_map( 'intval', $bundle_ids ) ) ) );
		if ( ! $bundle_ids || ! self::ready() ) {
			return array(); }
		$placeholders = implode( ',', array_fill( 0, count( $bundle_ids ), '%d' ) );
		$courses      = array();
		$items        = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT item_type, item_id FROM ' . Bundles::members_table() . " WHERE bundle_id IN ($placeholders)", $bundle_ids ), ARRAY_A ) as $row ) {
			if ( 'course' === $row['item_type'] ) {
				$courses[] = (int) $row['item_id'];
			} else {
				$items[] = (int) $row['item_id']; }
		}
		return array_values( array_unique( array_merge( $courses, self::course_ids_for_items( $items ) ) ) );
	}

	/** Course IDs matching filter slugs of one kind: the union of everything selected.
	 *
	 * @param mixed $slugs Slugs.
	 * @param mixed $kind Kind.
	 */
	public static function course_ids_for_slugs( $slugs, $kind ) {
		$ids = self::ids_from_slugs( $slugs, $kind );
		return 'bundle' === $kind ? self::course_ids_for_bundles( $ids ) : self::course_ids_for_items( $ids );
	}

	/**
	 * Narrow WP_Query arguments to courses matching curriculum and bundle filters. Selections in one
	 * group are alternatives; the two groups must both match. A selection that matches nothing
	 * yields no courses rather than silently showing everything.
	 *
	 * @param array $args Operation arguments.
	 * @param array $item_ids Item ids.
	 * @param array $bundle_ids Bundle ids.
	 */
	public static function narrow_query( array $args, array $item_ids = array(), array $bundle_ids = array() ) {
		$sets = array();
		if ( $item_ids ) {
			$sets[] = self::course_ids_for_items( $item_ids ); }
		if ( $bundle_ids ) {
			$sets[] = self::course_ids_for_bundles( $bundle_ids ); }
		if ( ! $sets ) {
			return $args; }
		$matches = array_shift( $sets );
		foreach ( $sets as $set ) {
			$matches = array_intersect( $matches, $set ); }
		if ( ! empty( $args['post__in'] ) ) {
			$matches = array_intersect( $matches, array_map( 'intval', (array) $args['post__in'] ) ); }
		$args['post__in'] = $matches ? array_values( $matches ) : array( 0 );
		return $args;
	}

	// ---- Public lists (only what has published courses) ------------------------------------

	/**
	 * Published links for Placement.
	 *
	 * @return array<int,int[]> item id => ids of published courses linked to it directly. */
	private static function published_links() {
		global $wpdb;
		$map = array();
		foreach ( $wpdb->get_results( 'SELECT l.item_id, l.object_id FROM ' . Links::table() . " l JOIN {$wpdb->posts} p ON p.ID=l.object_id WHERE l.object_type='course' AND p.post_type='" . esc_sql( OHMYLMS_COURSE_CPT ) . "' AND p.post_status='publish'", ARRAY_A ) as $row ) {
			$map[ (int) $row['item_id'] ][ (int) $row['object_id'] ] = true;
		}
		return $map;
	}

	/**
	 * Curriculum items that have at least one published course in their subtree, in tree order with
	 * their depth, for public filters and tabs. Courses count once per item however deep they sit.
	 */
	public static function public_items() {
		if ( ! self::ready() ) {
			return array(); }
		$links = self::published_links();
		if ( ! $links ) {
			return array(); }
		$rows    = Items::all();
		$parents = array();
		foreach ( $rows as $row ) {
			$parents[ (int) $row['id'] ] = (int) $row['parent_id']; }
		$subtree = array();
		foreach ( $links as $item_id => $courses ) {
			$chain = array_merge( array( $item_id ), Tree::ancestors( $parents, $item_id ) );
			foreach ( $chain as $id ) {
				foreach ( $courses as $course => $_ ) {
					$subtree[ $id ][ $course ] = true; }
			}
		}
		$children = array();
		foreach ( $rows as $row ) {
			$children[ (int) $row['parent_id'] ][] = $row; }
		$result = array();
		$walk   = static function ( $parent, $depth ) use ( &$walk, &$result, $children, $subtree ) {
			foreach ( $children[ $parent ] ?? array() as $row ) {
				$id = (int) $row['id'];
				if ( empty( $subtree[ $id ] ) ) {
					continue; }
				$result[] = array(
					'id'        => $id,
					'name'      => $row['name'],
					'slug'      => self::item_slug( $id ),
					'parent_id' => (int) $row['parent_id'],
					'depth'     => $depth,
					'count'     => count( $subtree[ $id ] ),
				);
				$walk( $id, $depth + 1 );
			}
		};
		$walk( 0, 0 );
		return $result;
	}

	/**
	 * A course's curriculum items, or with $bundles its published Course Bundles, as {id,name,slug} for
	 * course cards and learner-facing dashboards.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param bool  $bundles Bundles.
	 */
	public static function card_terms( $course_id, $bundles = false ) {
		$terms = array();
		if ( $bundles ) {
			foreach ( self::bundles( $course_id ) as $bundle ) {
				if ( 'published' === $bundle['status'] ) {
					$terms[] = array(
						'id'   => $bundle['id'],
						'name' => $bundle['title'],
						'slug' => $bundle['slug'],
					); }
			}
			return $terms;
		}
		foreach ( self::items( $course_id ) as $item ) {
			$terms[] = array(
				'id'   => $item['id'],
				'name' => $item['name'],
				'slug' => $item['slug'],
			); }
		return $terms;
	}

	/**
	 * Published courses by item for Placement.
	 *
	 * @return array<int,array<int,array{id:int,title:string}>> item id => published courses linked to it directly, by title. */
	public static function published_courses_by_item() {
		global $wpdb;
		$map = array();
		if ( ! self::ready() ) {
			return $map; }
		foreach ( $wpdb->get_results( 'SELECT l.item_id, p.ID AS course_id, p.post_title FROM ' . Links::table() . " l JOIN {$wpdb->posts} p ON p.ID=l.object_id WHERE l.object_type='course' AND p.post_type='" . esc_sql( OHMYLMS_COURSE_CPT ) . "' AND p.post_status='publish' ORDER BY p.post_title, p.ID", ARRAY_A ) as $row ) {
			$map[ (int) $row['item_id'] ][] = array(
				'id'    => (int) $row['course_id'],
				'title' => $row['post_title'],
			);
		}
		return $map;
	}

	/** Published bundles that have at least one published course, for public filters. */
	public static function public_bundles() {
		global $wpdb;
		if ( ! self::ready() ) {
			return array(); }
		$result = array();
		foreach ( Bundles::all( 'published' ) as $bundle ) {
			$courses = self::course_ids_for_bundles( array( (int) $bundle['id'] ) );
			if ( ! $courses ) {
				continue; }
			$placeholders = implode( ',', array_fill( 0, count( $courses ), '%d' ) );
			$count        = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status='publish' AND ID IN ($placeholders)", array_merge( array( OHMYLMS_COURSE_CPT ), $courses ) ) );
			if ( $count ) {
				$result[] = array(
					'id'    => (int) $bundle['id'],
					'title' => $bundle['title'],
					'slug'  => self::bundle_slug( (int) $bundle['id'] ),
					'count' => $count,
				); }
		}
		return $result;
	}

	/**
	 * Every curriculum item for page-builder pickers, keyed by slug (c<id>) so the tree order survives
	 * object-key sorting in the browser; names are indented by depth.
	 *
	 * @return array<string,string>
	 */
	public static function item_options() {
		if ( ! self::ready() ) {
			return array(); }
		$children = array();
		foreach ( Items::all() as $row ) {
			$children[ (int) $row['parent_id'] ][] = $row; }
		$options = array();
		$walk    = static function ( $parent, $depth ) use ( &$walk, &$options, $children ) {
			foreach ( $children[ $parent ] ?? array() as $row ) {
				$options[ self::item_slug( $row['id'] ) ] = str_repeat( '— ', $depth ) . $row['name'];
				$walk( (int) $row['id'], $depth + 1 );
			}
		};
		$walk( 0, 0 );
		return $options;
	}

	/** Published Course Bundles for page-builder pickers, keyed by slug (t<id>). @return array<string,string> */
	public static function bundle_options() {
		if ( ! self::ready() ) {
			return array(); }
		$options = array();
		foreach ( Bundles::all( 'published' ) as $bundle ) {
			$options[ self::bundle_slug( $bundle['id'] ) ] = $bundle['title']; }
		return $options;
	}

	// ---- Term-shaped views for the recovered admin app and older API clients ----------------

	/** Curriculum items shaped like the course category terms the admin app was built around. */
	public static function term_items() {
		if ( ! self::ready() ) {
			return array(); }
		$result = array();
		foreach ( Items::all() as $row ) {
			$id      = (int) $row['id'];
			$courses = array();
			foreach ( Links::object_ids( array( $id ), 'course' ) as $course_id ) {
				$courses[] = array(
					'id'    => $course_id,
					'title' => get_the_title( $course_id ),
				); }
			$result[] = array(
				'term_id'     => $id,
				'id'          => $id,
				'name'        => $row['name'],
				'slug'        => self::item_slug( $id ),
				'description' => (string) $row['description'],
				'parent'      => (int) $row['parent_id'],
				'count'       => count( $courses ),
				'courses'     => $courses,
			);
		}
		return $result;
	}

	/** Bundles shaped like course tag terms.
	 *
	 * @param string $search Search.
	 */
	public static function term_bundles( $search = '' ) {
		if ( ! self::ready() ) {
			return array(); }
		$result = array();
		foreach ( Bundles::all() as $row ) {
			if ( '' !== $search && stripos( $row['title'], $search ) === false ) {
				continue; }
			$id      = (int) $row['id'];
			$courses = array();
			foreach ( self::course_ids_for_bundles( array( $id ) ) as $course_id ) {
				$courses[] = array(
					'id'    => $course_id,
					'title' => get_the_title( $course_id ),
				); }
			$result[] = array(
				'term_id'     => $id,
				'id'          => $id,
				'name'        => $row['title'],
				'slug'        => self::bundle_slug( $id ),
				'description' => (string) $row['description'],
				'count'       => count( $courses ),
				'courses'     => $courses,
			);
		}
		return $result;
	}

	// ---- Changing a course's placement -----------------------------------------------------

	/**
	 * Valid course for Placement.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	private static function valid_course( $course_id ) {
		$status = get_post_status( (int) $course_id );
		return get_post_type( (int) $course_id ) === OHMYLMS_COURSE_CPT && $status && ! in_array( $status, array( 'trash', 'auto-draft' ), true );
	}

	/** Replace the curriculum items a course is linked to. @return true|\WP_Error
	 *
	 * @param mixed $course_id Course identifier.
	 * @param array $item_ids Item ids.
	 */
	public static function set_items( $course_id, array $item_ids ) {
		if ( ! self::ready() ) {
			return Access::error( 'ohmylms_curriculum_unavailable', __( 'Curriculum storage is not installed yet.', 'ohmylms' ), 503 ); }
		if ( ! self::valid_course( $course_id ) ) {
			return Access::error( 'ohmylms_course_missing', __( 'Course not found.', 'ohmylms' ), 404 ); }
		$wanted = array_values( array_unique( array_filter( array_map( 'intval', $item_ids ) ) ) );
		foreach ( $wanted as $id ) {
			if ( ! Items::get( $id ) ) {
				return Access::error( 'ohmylms_curriculum_invalid', __( 'A selected curriculum item no longer exists.', 'ohmylms' ) ); }
		}
		$current = self::item_ids( $course_id );
		foreach ( array_diff( $wanted, $current ) as $id ) {
			$result = Links::add( $id, 'course', $course_id );
			if ( is_wp_error( $result ) ) {
				return $result; }
		}
		foreach ( array_diff( $current, $wanted ) as $id ) {
			Links::remove( $id, 'course', $course_id ); }
		return true;
	}

	/**
	 * Replace the bundles a course belongs to. Bundles are curated, so callers should allow this only for
	 * administrators. A published bundle cannot be left with no members.
	 *
	 * @return true|\WP_Error
	 * @param mixed $course_id Course identifier.
	 * @param array $bundle_ids Bundle ids.
	 */
	public static function set_bundles( $course_id, array $bundle_ids ) {
		if ( ! self::ready() ) {
			return Access::error( 'ohmylms_curriculum_unavailable', __( 'Curriculum storage is not installed yet.', 'ohmylms' ), 503 ); }
		if ( ! self::valid_course( $course_id ) ) {
			return Access::error( 'ohmylms_course_missing', __( 'Course not found.', 'ohmylms' ), 404 ); }
		$wanted = array_values( array_unique( array_filter( array_map( 'intval', $bundle_ids ) ) ) );
		foreach ( $wanted as $id ) {
			if ( ! Bundles::get( $id ) ) {
				return Access::error( 'ohmylms_bundle_invalid', __( 'A selected course bundle no longer exists.', 'ohmylms' ) ); }
		}
		$current = self::bundle_ids( $course_id );
		foreach ( array_diff( $current, $wanted ) as $id ) {
			$result = Bundles::remove_course( $id, $course_id );
			if ( is_wp_error( $result ) ) {
				return $result; }
		}
		foreach ( array_diff( $wanted, $current ) as $id ) {
			$result = Bundles::add_course( $id, $course_id );
			if ( is_wp_error( $result ) ) {
				return $result; }
		}
		return true;
	}

	/** Give a duplicate the same curriculum placement as its original. Bundle membership is not copied.
	 *
	 * @param mixed $from_course From course.
	 * @param mixed $to_course To course.
	 */
	public static function copy_items( $from_course, $to_course ) {
		if ( ! self::ready() ) {
			return; }
		foreach ( self::item_ids( $from_course ) as $id ) {
			Links::add( $id, 'course', $to_course ); }
	}

	// ---- Importing (other LMS plugins, export files) ---------------------------------------

	/**
	 * Ensure ready for Placement.
	 */
	private static function ensure_ready() {
		return self::ready() || Schema::install();
	}

	/**
	 * Link an imported course to curriculum items, creating any that are missing. Each path lists names
	 * from the top level down, for example `['Science', 'Physics']`. Existing items with the same name
	 * and parent are reused, so repeated imports do not duplicate structure.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param array $paths Paths.
	 */
	public static function import_categories( $course_id, array $paths ) {
		if ( ! self::ensure_ready() ) {
			return; }
		foreach ( $paths as $path ) {
			$leaf = self::ensure_path( (array) $path );
			if ( $leaf ) {
				Links::add( $leaf, 'course', $course_id ); }
		}
	}

	/** Create top-level curriculum items for these names where they do not exist yet (setup wizard).
	 *
	 * @param array $names Names.
	 */
	public static function ensure_items( array $names ) {
		if ( ! self::ensure_ready() ) {
			return; }
		foreach ( $names as $name ) {
			self::ensure_path( array( $name ) ); }
	}

	/** The item at the end of a path of names, creating missing items on the way. 0 when nothing valid was given.
	 *
	 * @param array $path Path.
	 */
	private static function ensure_path( array $path ) {
		global $wpdb;
		$parent = 0;
		foreach ( array_values( array_filter( array_map( 'trim', array_map( 'strval', $path ) ), 'strlen' ) ) as $name ) {
			$found = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Items::table() . ' WHERE parent_id=%d AND name=%s ORDER BY id LIMIT 1', $parent, $name ) );
			if ( ! $found ) {
				$created = Items::create(
					array(
						'name'      => $name,
						'item_type' => 'custom',
						'parent_id' => $parent,
					)
				);
				if ( is_wp_error( $created ) ) {
					return 0; }
				$found = (int) $created['id'];
			}
			$parent = $found;
		}
		return $parent;
	}

	/** Names from the top level down for a term of another plugin's hierarchical taxonomy, e.g. ['Science', 'Physics'].
	 *
	 * @param mixed $term Term.
	 */
	public static function term_path( $term ) {
		$path   = array( $term->name );
		$parent = (int) $term->parent;
		for ( $guard = 0; $parent && $guard < 20; $guard++ ) {
			$parent_term = get_term( $parent, $term->taxonomy );
			if ( ! $parent_term || is_wp_error( $parent_term ) ) {
				break; }
			array_unshift( $path, $parent_term->name );
			$parent = (int) $parent_term->parent;
		}
		return $path;
	}
	/** Add an imported course to the bundles with these titles, creating draft bundles that do not exist yet.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param array $titles Titles.
	 */
	public static function import_tags( $course_id, array $titles ) {
		if ( ! self::ensure_ready() ) {
			return; }
		foreach ( array_unique( array_filter( array_map( 'trim', array_map( 'strval', $titles ) ), 'strlen' ) ) as $title ) {
			$bundle = Bundles::find_by_title( $title );
			if ( ! $bundle ) {
				$bundle = Bundles::create( array( 'title' => $title ) );
				if ( is_wp_error( $bundle ) ) {
					continue; }
			}
			Bundles::add_course( (int) $bundle['id'], $course_id );
		}
	}
}
