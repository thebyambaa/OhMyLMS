<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping.
/**
 * Canonical course structure reads and transactional native dashboard writes.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\Curriculum\UnitNumber;
use OhMyLMS\DataStores\ChapterStore;
use OhMyLMS\DataStores\CourseStore;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/** Preserve native records, program placement IDs, groups and shared content. */
final class CourseStructure {
	/**
	 * Read one coherent authoring snapshot while a concurrent writer finishes.
	 *
	 * @param int      $course_id Course ID.
	 * @param callable $reader Read callback.
	 * @return mixed|\WP_Error
	 */
	public static function read( $course_id, callable $reader ) {
		global $wpdb;
		$key = 'ohmylms_program_' . (int) $course_id;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $key ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Named lock shared with program and native structure writers.
			return Catalog::error( __( 'Another author is saving this course. Try again.', 'ohmylms' ), 409 );
		}
		try {
			self::flush( $course_id );
			return UnitSnapshots::authoring( $reader );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release the named read lock.
		}
	}

	/**
	 * Resolve the unique course owning a chapter. Ambiguous legacy ownership is not guessed.
	 *
	 * @param int $chapter_id Chapter ID.
	 * @return int
	 */
	public static function owner( $chapter_id ) {
		global $wpdb;
		$owners = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT course_id FROM %i WHERE chapter_id=%d', $wpdb->prefix . 'ohmylms_chapter_relationship', $chapter_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh relationship ownership, checked for each request.
		return 1 === count( $owners ) ? (int) $owners[0] : 0;
	}

	/**
	 * First course of each content item: the lowest course ID among the chapters that contain it.
	 *
	 * @param int[] $content_ids Content post IDs.
	 * @return array<int,int> Content ID => course ID; content outside every course is absent.
	 */
	public static function first_courses( array $content_ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $content_ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated from the id count.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT content.content_id, MIN(chapter.course_id) AS course_id FROM %i content INNER JOIN %i chapter ON chapter.chapter_id = content.chapter_id WHERE content.content_id IN ({$holders}) GROUP BY content.content_id", array_merge( array( $wpdb->prefix . 'ohmylms_content_relationship', $wpdb->prefix . 'ohmylms_chapter_relationship' ), $ids ) ) );
		$map  = array();
		foreach ( (array) $rows as $row ) {
			$map[ (int) $row->content_id ] = (int) $row->course_id;
		}
		return $map;
	}

	/**
	 * Subquery selecting the content IDs placed in a course through a chapter, for `ID IN (...)` filters.
	 *
	 * @param int $course_id Course ID.
	 * @return array{0:string,1:array} SQL with two `%i` and one `%d` placeholder, and its arguments.
	 */
	public static function content_in_course_sql( $course_id ) {
		global $wpdb;
		return array(
			'SELECT content.content_id FROM %i content INNER JOIN %i chapter ON chapter.chapter_id = content.chapter_id WHERE chapter.course_id = %d',
			array( $wpdb->prefix . 'ohmylms_content_relationship', $wpdb->prefix . 'ohmylms_chapter_relationship', (int) $course_id ),
		);
	}

	/**
	 * Fresh native relationship rows for an entire course, in display order.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function native_items( $course_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT c.id,c.chapter_id,c.content_id,c.content_type,c.order_number FROM %i r JOIN %i c ON c.chapter_id=r.chapter_id WHERE r.course_id=%d ORDER BY r.order_number,r.id,c.order_number,c.id', $wpdb->prefix . 'ohmylms_chapter_relationship', $wpdb->prefix . 'ohmylms_content_relationship', $course_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Compact, uncached structure read used for concurrency checks.
	}

	/**
	 * Hash metadata omitted by the old program-only revision token.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function native_version( $course_id ) {
		$chapters = Catalog::chapters( $course_id );
		foreach ( $chapters as &$chapter ) {
			$chapter['description'] = (string) get_post_field( 'post_content', $chapter['id'] );
			$chapter['status']      = get_post_status( $chapter['id'] );
		}
		unset( $chapter );
		return array(
			'units'        => $chapters,
			'native_items' => self::native_items( $course_id ),
			'published'    => (int) get_post_meta( $course_id, CourseProgram::CURRENT, true ),
		);
	}

	/**
	 * Normalize the saved authoring program into course > unit > group > activity views.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get( $course_id ) {
		$program  = CourseProgram::editor( $course_id )['draft'];
		$chapters = Catalog::chapters( $course_id );
		$units    = array();
		foreach ( $chapters as $chapter ) {
			$units[ $chapter['id'] ] = array(
				'id'          => $chapter['id'],
				'title'       => $chapter['name'],
				'number'      => $chapter['number'],
				'description' => (string) get_post_field( 'post_content', $chapter['id'] ),
				'items'       => array(),
			);
		}
		foreach ( $program['items'] as $item ) {
			$id = (int) ( $item['chapter_id'] ?? 0 );
			if ( ! isset( $units[ $id ] ) ) {
				$units[ $id ] = array(
					'id'     => $id,
					'title'  => __( 'Unplaced activities', 'ohmylms' ),
					'number' => '',
					'items'  => array(),
				);
			}
			$units[ $id ]['items'][] = $item;
		}
		foreach ( $units as &$unit ) {
			$arranged          = ProgramGroups::arrange( array(), $unit['items'], $unit['id'] );
			$unit['groups']    = $arranged['groups'];
			$unit['ungrouped'] = $arranged['ungrouped'];
		}
		unset( $unit );
		return array(
			'course_id'    => (int) $course_id,
			'revision'     => Catalog::revision( $course_id ),
			'units'        => array_values( StructurePlan::order_units( $units, $chapters ) ),
			'outcomes'     => $program['outcomes'],
			'native_items' => self::native_items( $course_id ),
		);
	}

	/**
	 * Flush post and request-local relationship caches after commit or rollback.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $extra_ids Chapters touched before a rollback.
	 */
	public static function flush( $course_id, array $extra_ids = array() ) {
		UnitSnapshots::flush();
		CourseStore::clear_structure_cache( $course_id );
		clean_post_cache( $course_id );
		foreach ( array_unique( array_merge( $extra_ids, array_column( Catalog::chapters( $course_id ), 'id' ) ) ) as $chapter_id ) {
			ChapterStore::clear_structure_cache( $chapter_id );
			clean_post_cache( $chapter_id );
		}
	}

	/**
	 * Apply an authorized change atomically, rejecting missing/stale read tokens.
	 *
	 * @param int      $course_id Course ID.
	 * @param string   $revision Client read token.
	 * @param callable $change Validates and writes the change.
	 * @return mixed|\WP_Error
	 */
	public static function change( $course_id, $revision, callable $change ) {
		if ( ! Catalog::can_edit( $course_id ) ) {
			return Catalog::error( __( 'You cannot edit this course structure.', 'ohmylms' ), rest_authorization_required_code() );
		}
		return self::atomic( $course_id, $revision, $change );
	}

	/**
	 * Serialize trusted internal syllabus synchronizers with dashboard structure writers.
	 *
	 * Callers must already have authorized their source operation.
	 *
	 * @param int      $course_id Course ID.
	 * @param string   $revision Expected structure token.
	 * @param callable $change Internal change.
	 * @return mixed|\WP_Error
	 */
	public static function atomic( $course_id, $revision, callable $change ) {
		return Catalog::with_revision(
			$course_id,
			$revision,
			static function () use ( $course_id, $change ) {
				$error   = null;
				$touched = array_column( Catalog::chapters( $course_id ), 'id' );
				try {
					$result = Transaction::run(
						static function () use ( $course_id, $change, &$error, &$touched ) {
							UnitSnapshots::capture( $course_id );
							$result  = UnitSnapshots::authoring( $change );
							$touched = array_merge( $touched, array_column( Catalog::chapters( $course_id ), 'id' ) );
							if ( is_wp_error( $result ) || ( $result instanceof \WP_REST_Response && $result->get_status() >= 400 ) ) {
								$error = $result;
								throw new \RuntimeException( 'Structure change rejected.' );
							}
							return $result;
						}
					);
				} catch ( \Throwable $exception ) {
					self::flush( $course_id, $touched );
					return $error ? $error : Catalog::error( __( 'The structure could not be saved. No structure changes were committed.', 'ohmylms' ), 500 );
				}
				self::flush( $course_id, $touched );
				return $result;
			}
		);
	}

	/**
	 * Validate and save one chapter without permitting a cross-course reparent.
	 *
	 * @param int   $course_id Course ID.
	 * @param int   $chapter_id Existing ID, or zero for a new chapter.
	 * @param array $fields Existing dashboard fields.
	 * @return array|\WP_Error
	 */
	public static function save_unit( $course_id, $chapter_id, array $fields ) {
		if ( $chapter_id && self::owner( $chapter_id ) !== (int) $course_id ) {
			return Catalog::error( __( 'The unit does not belong exclusively to this course.', 'ohmylms' ), 400 );
		}
		if ( isset( $fields['course_id'] ) && (int) $fields['course_id'] !== (int) $course_id ) {
			return Catalog::error( __( 'Units cannot be moved between courses by this operation.', 'ohmylms' ) );
		}
		if ( isset( $fields['description'] ) && ! is_string( $fields['description'] ) ) {
			return Catalog::error( __( 'Send a valid unit description.', 'ohmylms' ) );
		}
		if ( isset( $fields['name'] ) && ! is_string( $fields['name'] ) ) {
			return Catalog::error( __( 'Send a valid unit name.', 'ohmylms' ) );
		}
		if ( isset( $fields['number'] ) && null === UnitNumber::normalize( $fields['number'] ) ) {
			return Catalog::error( __( 'Send a valid unit number.', 'ohmylms' ) );
		}
		if ( isset( $fields['status'] ) && ! in_array( $fields['status'], array( 'publish', 'draft', 'private', 'pending' ), true ) ) {
			return Catalog::error( __( 'Send a valid unit status.', 'ohmylms' ) );
		}
		if ( $chapter_id ) {
			$update = array_intersect_key(
				$fields,
				array(
					'name'   => true,
					'number' => true,
				)
			);
			$result = $update ? Catalog::update_chapter( $course_id, $chapter_id, $update ) : array( 'id' => $chapter_id );
		} else {
			$result = Catalog::add_chapter( $course_id, $fields['name'] ?? '' );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$chapter_id = $result['id'];
		$post_data  = array( 'ID' => $chapter_id );
		if ( isset( $fields['description'] ) ) {
			$post_data['post_content'] = wp_kses_post( $fields['description'] );
		}
		if ( isset( $fields['status'] ) ) {
			$post_data['post_status'] = $fields['status'];
		}
		if ( 1 < count( $post_data ) ) {
			$saved = wp_update_post( wp_slash( $post_data ), true );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		if ( isset( $fields['number'] ) ) {
			UnitNumber::set( $chapter_id, $fields['number'] );
		}
		return array(
			'id'          => $chapter_id,
			'name'        => get_the_title( $chapter_id ),
			'description' => (string) get_post_field( 'post_content', $chapter_id ),
		);
	}

	/**
	 * Save the complete legacy unit list; omission cannot silently unlink an existing unit.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $rows Legacy dashboard rows.
	 * @return array|\WP_Error
	 */
	public static function save_units( $course_id, array $rows ) {
		$ordered = StructurePlan::order_rows( $rows );
		if ( is_string( $ordered ) || count( $rows ) > 2000 ) {
			return Catalog::error( __( 'Send a complete unit list with distinct positions.', 'ohmylms' ) );
		}
		$current = array_column( Catalog::chapters( $course_id ), 'id' );
		$seen    = array();
		foreach ( $ordered as $row ) {
			$id = absint( $row['id'] ?? 0 );
			if ( $id && ( isset( $seen[ $id ] ) || ! in_array( $id, $current, true ) || self::owner( $id ) !== (int) $course_id ) ) {
				return Catalog::error( __( 'List this course’s units exactly once; foreign units are not allowed.', 'ohmylms' ) );
			}
			$seen[ $id ] = true;
		}
		if ( array_diff( $current, array_keys( $seen ) ) ) {
			return Catalog::error( __( 'Keep every existing unit. Use the explicit delete operation to remove an empty unit.', 'ohmylms' ) );
		}
		$saved = array();
		foreach ( $ordered as $row ) {
			$unit = self::save_unit( $course_id, absint( $row['id'] ?? 0 ), $row );
			if ( is_wp_error( $unit ) ) {
				return $unit;
			}
			$unit['order_number'] = count( $saved ) + 1;
			$saved[]              = $unit;
		}
		$result = Catalog::reorder_chapters( $course_id, array_column( $saved, 'id' ) );
		return is_wp_error( $result ) ? $result : $saved;
	}

	/**
	 * Place and order existing resources; never rewrite reusable content bodies or titles.
	 *
	 * @param int   $course_id Course ID.
	 * @param int   $chapter_id Unit ID.
	 * @param array $rows Legacy activity rows.
	 * @return bool|\WP_Error
	 */
	public static function save_contents( $course_id, $chapter_id, array $rows ) {
		global $wpdb;
		if ( self::owner( $chapter_id ) !== (int) $course_id ) {
			return Catalog::error( __( 'The unit does not belong exclusively to this course.', 'ohmylms' ) );
		}
		$ordered = StructurePlan::order_rows( $rows );
		if ( is_string( $ordered ) || count( $rows ) > 2000 ) {
			return Catalog::error( __( 'Send valid activities with distinct positions.', 'ohmylms' ) );
		}
		$seen  = array();
		$clean = array();
		foreach ( $ordered as $row ) {
			$id   = absint( $row['id'] ?? 0 );
			$type = (string) ( $row['content_type'] ?? $row['type'] ?? '' );
			$post = $id ? get_post( $id ) : null;
			if ( ! $post || isset( $seen[ $id ] ) || ! current_user_can( 'edit_post', $id ) || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
				return Catalog::error( __( 'Create or choose each existing activity in its editor before placing it here.', 'ohmylms' ) );
			}
			$expected = Catalog::post_type( $type );
			if ( 'session' === $type ) {
				$expected = 'ohmylms-session';
			}
			if ( '' === $expected ) {
				$expected = OHMYLMS_LESSON_CPT;
				if ( ! \OhMyLMS\Extensions\Registry::get( 'lesson', $type ) ) {
					return Catalog::error( __( 'Choose a registered activity type.', 'ohmylms' ) );
				}
			}
			if ( $expected !== $post->post_type ) {
				return Catalog::error( __( 'The activity type does not match its record.', 'ohmylms' ) );
			}
			$seen[ $id ] = true;
			$clean[]     = array(
				'chapter_id'   => $chapter_id,
				'content_id'   => $id,
				'content_type' => $type,
				'order_number' => count( $clean ) + 1,
			);
		}
		$existing = array_filter(
			self::native_items( $course_id ),
			static function ( $row ) use ( $chapter_id ) {
				return (int) $row['chapter_id'] === (int) $chapter_id;
			}
		);
		if ( array_diff( array_map( 'intval', array_column( $existing, 'content_id' ) ), array_keys( $seen ) ) ) {
			return Catalog::error( __( 'Keep existing activities or remove a placement explicitly. Student history is preserved.', 'ohmylms' ) );
		}
		foreach ( $clean as $row ) {
			$existing_id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE chapter_id=%d AND content_id=%d LIMIT 1', $wpdb->prefix . 'ohmylms_content_relationship', $chapter_id, $row['content_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read under the course save lock.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Both branches write native placements inside the save transaction.
			$ok = $existing_id ? $wpdb->update( $wpdb->prefix . 'ohmylms_content_relationship', $row, array( 'id' => (int) $existing_id ) ) : $wpdb->insert( $wpdb->prefix . 'ohmylms_content_relationship', $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Transactional placement write; no reusable content mutation.
			if ( false === $ok ) {
				return Catalog::error( __( 'Activity ordering could not be saved.', 'ohmylms' ), 500 );
			}
		}
		return true;
	}

	/**
	 * Remove only a native placement, preserving content and learner history.
	 *
	 * @param int $chapter_id Unit ID.
	 * @param int $content_id Resource ID.
	 * @return bool|\WP_Error
	 */
	public static function detach( $chapter_id, $content_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Explicit placement removal under the course transaction; no student data is changed.
		$removed = $wpdb->delete(
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id' => $chapter_id,
				'content_id' => $content_id,
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Remove a placement, never the reusable post or student progress.
		return false === $removed ? Catalog::error( __( 'The placement could not be removed.', 'ohmylms' ), 500 ) : true;
	}
}
