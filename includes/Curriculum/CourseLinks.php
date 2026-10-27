<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Advisory course-to-course links: sub-courses, prerequisites and "get ready for" suggestions.
 * They never gate access or enrollment; they only describe how courses relate on the hub.
 *
 * The rules (types, limits, self-link and cycle checks) are pure so they can be checked without
 * a database; the storage functions sit below them.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/** Rules and storage for the course_links table. */
final class CourseLinks {
	const TYPES = array( 'subcourse', 'prerequisite', 'get_ready' );
	/** Links of these types form a hierarchy and may not loop. "get_ready" is only a suggestion. */
	const ACYCLIC        = array( 'subcourse', 'prerequisite' );
	const MAX_PER_COURSE = 50;
	/** Named database lock held while links are validated and written. */
	const LOCK = 'ohmylms-course-links';

	/**
	 * Would adding course -> related close a loop in the sub-course/prerequisite graph?
	 *
	 * @param array<int,int[]> $edges   course => related courses already linked (acyclic types only).
	 * @param int              $course  The course that gets the link.
	 * @param int              $related The linked course.
	 */
	public static function creates_cycle( array $edges, $course, $related ) {
		$course  = (int) $course;
		$related = (int) $related;
		if ( $course === $related ) {
			return true; }
		$seen  = array();
		$stack = array( $related );
		while ( $stack ) {
			$node = array_pop( $stack );
			if ( $node === $course ) {
				return true; }
			if ( isset( $seen[ $node ] ) ) {
				continue; }
			$seen[ $node ] = true;
			foreach ( $edges[ $node ] ?? array() as $next ) {
				$stack[] = (int) $next; }
		}
		return false;
	}

	/**
	 * Validate a replacement list for one course.
	 *
	 * @param int                            $course Course that owns the links.
	 * @param mixed                          $links  Submitted [{course_id, type}], array order is the order shown.
	 * @param array<string,array<int,int[]>> $others Acyclic edges of every OTHER course, one graph per link type
	 *                                               (this course's own are being replaced). Sub-courses and
	 *                                               prerequisites are different relations, so each is checked on its own.
	 * @return array|string Clean [{course_id,type,position}] or an error code: invalid|too_many|type|self|duplicate|cycle.
	 */
	public static function validate( $course, $links, array $others ) {
		$course = (int) $course;
		if ( ! is_array( $links ) ) {
			return 'invalid'; }
		if ( count( $links ) > self::MAX_PER_COURSE ) {
			return 'too_many'; }
		$edges = array();
		foreach ( self::ACYCLIC as $acyclic ) {
			$edges[ $acyclic ] = (array) ( $others[ $acyclic ] ?? array() );
			unset( $edges[ $acyclic ][ $course ] );
		}
		$clean = array();
		$seen  = array();
		foreach ( array_values( $links ) as $position => $link ) {
			if ( ! is_array( $link ) ) {
				return 'invalid'; }
			$type    = $link['type'] ?? '';
			$related = (int) ( $link['course_id'] ?? 0 );
			if ( ! in_array( $type, self::TYPES, true ) ) {
				return 'type'; }
			if ( $related <= 0 ) {
				return 'invalid'; }
			if ( $related === $course ) {
				return 'self'; }
			$key = $related . ':' . $type;
			if ( isset( $seen[ $key ] ) ) {
				return 'duplicate'; }
			$seen[ $key ] = true;
			if ( in_array( $type, self::ACYCLIC, true ) ) {
				if ( self::creates_cycle( $edges[ $type ], $course, $related ) ) {
					return 'cycle'; }
				$edges[ $type ][ $course ][] = $related;
			}
			$clean[] = array(
				'course_id' => $related,
				'type'      => $type,
				'position'  => $position,
			);
		}
		return $clean;
	}

	/**
	 * Human message for a validate() error code.
	 *
	 * @param string $code Error code from validate().
	 * @return string
	 */
	public static function message( $code ) {
		$messages = array(
			'invalid'   => __( 'Invalid course link.', 'ohmylms' ),
			'too_many'  => __( 'Too many course links.', 'ohmylms' ),
			'type'      => __( 'Choose a valid link type.', 'ohmylms' ),
			'self'      => __( 'A course cannot link to itself.', 'ohmylms' ),
			'duplicate' => __( 'This course is already linked that way.', 'ohmylms' ),
			'cycle'     => __( 'These links would make a course contain or require itself.', 'ohmylms' ),
		);
		return $messages[ $code ] ?? $messages['invalid'];
	}

	// ---- Storage -----------------------------------------------------------------------------------------

	/**
	 * Whether the course_links table is installed.
	 *
	 * @return bool
	 */
	public static function ready() {
		return Schema::ready();
	}

	/**
	 * Links of one course, in display order.
	 *
	 * @param int $course_id Course ID.
	 * @return array[] [{course_id,type,position}].
	 */
	public static function get( $course_id ) {
		global $wpdb;
		if ( ! self::ready() ) {
			return array(); }
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT related_course_id, link_type, position FROM %i WHERE course_id=%d ORDER BY position, id', Schema::table( 'course_links' ), (int) $course_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh read of editable link data; never cached.
		return array_map(
			static function ( $row ) {
				return array(
					'course_id' => (int) $row['related_course_id'],
					'type'      => $row['link_type'],
					'position'  => (int) $row['position'],
				);
			},
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Acyclic-type edges of every course except one.
	 *
	 * @param int $except Course whose own links are being replaced.
	 * @return array [link type => [course => related courses]].
	 */
	private static function edges( $except ) {
		global $wpdb;
		$holders = implode( ',', array_fill( 0, count( self::ACYCLIC ), '%s' ) );
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT course_id, related_course_id, link_type FROM %i WHERE link_type IN ($holders) AND course_id<>%d", array_merge( array( Schema::table( 'course_links' ) ), self::ACYCLIC, array( (int) $except ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The IN list is only %s placeholders; read under the save lock, never cached.
		$edges   = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$edges[ $row['link_type'] ][ (int) $row['course_id'] ][] = (int) $row['related_course_id']; }
		return $edges;
	}

	/**
	 * Replace all links of a course. The edge read, the cycle check and the write happen under one named
	 * lock and one transaction, so two saves cannot each pass the check and then together close a loop.
	 *
	 * @param int   $course_id Course that owns the links.
	 * @param mixed $links     Submitted [{course_id, type}], array order is the order shown.
	 * @return array[]|\WP_Error The stored links, or an error naming the first rejected one.
	 */
	public static function replace( $course_id, $links ) {
		global $wpdb;
		$course_id = (int) $course_id;
		if ( ! self::ready() ) {
			return new \WP_Error( 'ohmylms_course_links_unavailable', __( 'Course links are not installed yet.', 'ohmylms' ), array( 'status' => 503 ) ); }
		$table = Schema::table( 'course_links' );
		$lock  = self::LOCK . '-' . md5( $wpdb->prefix );
		if ( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) !== '1' ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Named lock; never cached.
			return new \WP_Error( 'ohmylms_course_link_busy', __( 'Another change is being saved. Try again in a moment.', 'ohmylms' ), array( 'status' => 409 ) ); }
		try {
			$rejected = Transaction::run(
				static function () use ( $wpdb, $table, $course_id, $links ) {
					$clean = CourseLinks::validate( $course_id, $links, CourseLinks::edges( $course_id ) );
					if ( is_string( $clean ) ) {
						return new \WP_Error( 'ohmylms_course_link_invalid', CourseLinks::message( $clean ), array( 'status' => 400 ) ); }
					foreach ( $clean as $link ) {
						if ( get_post_type( $link['course_id'] ) !== OHMYLMS_COURSE_CPT || ! current_user_can( 'read_post', $link['course_id'] ) ) {
							return new \WP_Error( 'ohmylms_course_link_invalid', __( 'Choose an existing course you can view.', 'ohmylms' ), array( 'status' => 400 ) ); }
					}
					if ( false === $wpdb->delete( $table, array( 'course_id' => $course_id ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Replaces the course's links inside the save transaction.
						throw new \RuntimeException( 'Link clear failed' ); }
					foreach ( $clean as $link ) {
						if ( ! $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writes inside the save transaction.
							$table,
							array(
								'course_id'         => $course_id,
								'related_course_id' => $link['course_id'],
								'link_type'         => $link['type'],
								'position'          => $link['position'],
								'created_by'        => get_current_user_id(),
								'created_at'        => current_time( 'mysql', true ),
							)
						) ) {
							throw new \RuntimeException( 'Link write failed' ); }
					}
					return null;
				}
			);
			if ( $rejected instanceof \WP_Error ) {
				return $rejected; }
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'ohmylms_course_link_failed', __( 'Could not save the course links.', 'ohmylms' ), array( 'status' => 500 ) );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Releases the named lock.
		}
		return self::get( $course_id );
	}

	/**
	 * Copy the links of one course to another (duplicate); targets stay the same.
	 *
	 * @param int $from_course Course whose links are copied.
	 * @param int $to_course   The duplicate.
	 */
	public static function copy( $from_course, $to_course ) {
		global $wpdb;
		if ( ! self::ready() ) {
			return; }
		$table = Schema::table( 'course_links' );
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (course_id,related_course_id,link_type,position,created_by,created_at) SELECT %d,related_course_id,link_type,position,created_by,created_at FROM %i WHERE course_id=%d AND related_course_id<>%d', $table, (int) $to_course, $table, (int) $from_course, (int) $to_course ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off copy while duplicating a course.
	}

	/**
	 * Links in export form: the related course is named by slug, since IDs differ between sites.
	 *
	 * @param int $course_id Course ID.
	 * @return array[] [{slug, type}].
	 */
	public static function export( $course_id ) {
		$out = array();
		foreach ( self::get( $course_id ) as $link ) {
			$slug = get_post_field( 'post_name', $link['course_id'] );
			if ( $slug ) {
				$out[] = array(
					'slug' => $slug,
					'type' => $link['type'],
				); }
		}
		return $out;
	}

	/**
	 * Restore exported links where a course with that slug exists; the rest are skipped, never created.
	 *
	 * @param int   $course_id Imported course.
	 * @param mixed $exported  Exported [{slug, type}] list.
	 * @return array[]|\WP_Error The stored links, or an error.
	 */
	public static function import( $course_id, $exported ) {
		$links = array();
		foreach ( is_array( $exported ) ? $exported : array() as $link ) {
			$related = is_array( $link ) && ! empty( $link['slug'] ) ? get_page_by_path( sanitize_title( $link['slug'] ), OBJECT, OHMYLMS_COURSE_CPT ) : null;
			if ( $related && (int) $related->ID !== (int) $course_id ) {
				$links[] = array(
					'course_id' => (int) $related->ID,
					'type'      => $link['type'] ?? '',
				); }
		}
		return $links ? self::replace( $course_id, $links ) : array();
	}

	/**
	 * A deleted course leaves no links to or from it.
	 *
	 * @param int $course_id Deleted course ID.
	 */
	public static function remove_course( $course_id ) {
		global $wpdb;
		if ( ! self::ready() ) {
			return; }
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE course_id=%d OR related_course_id=%d', Schema::table( 'course_links' ), (int) $course_id, (int) $course_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup of a deleted course.
	}
}
