<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course targets referencing existing syllabus objectives.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/** Target mappings are independent of the course's completion policy. */
final class CourseObjectives {

	const META        = '_ohmylms_course_objectives';
	const MAX_TARGETS = 200;

	/**
	 * Stored stable references, including references whose source was removed.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function references( $course_id ) {
		$stored = get_post_meta( $course_id, self::META, true );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * List syllabuses without opening or synchronizing their courses.
	 *
	 * @return array
	 */
	public static function syllabuses() {
		$rows = array();
		foreach ( Items::all() as $item ) {
			if ( empty( $item['is_syllabus'] ) ) {
				continue;
			}
			$profile = SyllabusSettings::get( $item['id'] );
			$rows[]  = array(
				'id'      => (int) $item['id'],
				'name'    => Syllabus::text( $item['name'] ),
				'code'    => $item['code'],
				'version' => $item['version'],
				'grade'   => $profile['grade'],
				'subject' => $profile['subject'],
			);
		}
		return $rows;
	}

	/**
	 * Objective records in a syllabus, indexed by their existing skill IDs.
	 *
	 * @param int $syllabus_id Syllabus ID.
	 * @return array|\WP_Error
	 */
	public static function objectives( $syllabus_id ) {
		$outline = Syllabus::outline( $syllabus_id );
		if ( is_wp_error( $outline ) ) {
			return $outline;
		}
		$rows = array();
		foreach ( $outline['contents'] as $content ) {
			foreach ( $content['groups'] as $group ) {
				foreach ( $group['skills'] as $skill ) {
					$id          = (int) $skill['term_id'];
					$rows[ $id ] = array(
						'syllabus_id' => (int) $syllabus_id,
						'term_id'     => $id,
						'name'        => $skill['name'],
						'code'        => $skill['code'],
						'objective'   => $skill['objective'],
						'topic'       => $content['name'] . ' / ' . $group['name'],
					);
				}
			}
		}
		return $rows;
	}

	/**
	 * Existing course content explicitly connected to skills.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	private static function coverage( $course_id ) {
		global $wpdb;
		$coverage          = array();
		$native            = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT cr.content_id FROM {$wpdb->prefix}ohmylms_content_relationship cr INNER JOIN {$wpdb->prefix}ohmylms_chapter_relationship ch ON ch.chapter_id = cr.chapter_id WHERE ch.course_id = %d",
				$course_id
			)
		);
		$program           = CourseProgram::editor( $course_id );
		$items             = array_values(
			array_filter(
				$program['draft']['items'],
				static function ( $item ) {
					return 'practice' !== $item['type'];
				}
			)
		);
		$ids               = array_unique( array_merge( array_map( 'intval', $native ), array_map( 'intval', array_column( $items, 'content_id' ) ) ) );
		$attachment_skills = array();
		foreach ( $items as $item ) {
			$key                       = $item['type'] . ':' . (int) $item['content_id'];
			$attachment_skills[ $key ] = array_merge( $attachment_skills[ $key ] ?? array(), $item['skill_ids'] ?? array() );
		}
		$tagged_skills = array();
		$terms         = $ids ? wp_get_object_terms( $ids, Taxonomy::NAME, array( 'fields' => 'all_with_object_id' ) ) : array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$tagged_skills[ (int) $term->object_id ][] = (int) $term->term_id;
			}
		}
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
				continue;
			}
			$type = array_search(
				$post->post_type,
				array(
					'lesson'     => OHMYLMS_LESSON_CPT,
					'quiz'       => OHMYLMS_QUIZ_CPT,
					'assignment' => 'ohmylms-assignment',
				),
				true
			);
			if ( false === $type ) {
				continue;
			}
			$skills = array_merge( $tagged_skills[ $id ] ?? array(), $attachment_skills[ $type . ':' . $id ] ?? array() );
			foreach ( array_unique( array_map( 'intval', $skills ) ) as $skill_id ) {
				$coverage[ $skill_id ][ $id ] = array(
					'id'    => $id,
					'type'  => $type,
					'title' => Syllabus::text( $post->post_title ),
				);
			}
		}
		return $coverage;
	}

	/**
	 * Saved mappings with live objective text and course-scoped coverage.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function payload( $course_id ) {
		$references = self::references( $course_id );
		$coverage   = self::coverage( $course_id );
		$cached     = array();
		$rows       = array();
		foreach ( $references as $reference ) {
			$syllabus_id = $reference['syllabus_id'];
			if ( ! isset( $cached[ $syllabus_id ] ) ) {
				$found                  = self::objectives( $syllabus_id );
				$cached[ $syllabus_id ] = is_wp_error( $found ) ? array() : $found;
			}
			$objective = $cached[ $syllabus_id ][ $reference['term_id'] ] ?? null;
			$rows[]    = ( $objective ?? $reference ) + array(
				'missing' => null === $objective,
				'content' => array_values( $coverage[ $reference['term_id'] ] ?? array() ),
			);
		}
		return array(
			'mappings'   => $rows,
			'revision'   => md5( wp_json_encode( $references ) ),
			'syllabuses' => self::syllabuses(),
			'limit'      => self::MAX_TARGETS,
		);
	}

	/**
	 * Replace references after checking membership and concurrent changes.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $data      Mapping list and expected revision.
	 * @return true|\WP_Error
	 */
	public static function save( $course_id, array $data ) {
		if ( ! isset( $data['mappings'], $data['revision'] ) || ! is_array( $data['mappings'] ) || array_values( $data['mappings'] ) !== $data['mappings'] || count( $data['mappings'] ) > self::MAX_TARGETS || ! is_string( $data['revision'] ) ) {
			return Catalog::error( __( 'Provide a mapping list of at most 200 objectives and its revision.', 'ohmylms' ) );
		}
		return Items::exclusive(
			static function () use ( $course_id, $data ) {
				wp_cache_delete( $course_id, 'post_meta' );
				$current = self::references( $course_id );
				if ( md5( wp_json_encode( $current ) ) !== $data['revision'] ) {
					return Catalog::error( __( 'These mappings changed in another session. Reload objectives before saving.', 'ohmylms' ), 409 );
				}
				$next   = array();
				$cached = array();
				foreach ( $data['mappings'] as $mapping ) {
					if ( ! is_array( $mapping ) || ! isset( $mapping['syllabus_id'], $mapping['term_id'] ) || ! is_int( $mapping['syllabus_id'] ) || ! is_int( $mapping['term_id'] ) || $mapping['syllabus_id'] < 1 || $mapping['term_id'] < 1 ) {
						return Catalog::error( __( 'Each objective needs a valid syllabus ID and skill ID.', 'ohmylms' ) );
					}
					$reference = array(
						'syllabus_id' => $mapping['syllabus_id'],
						'term_id'     => $mapping['term_id'],
					);
					if ( ! in_array( $reference, $current, true ) ) {
						$id = $reference['syllabus_id'];
						if ( ! isset( $cached[ $id ] ) ) {
							$cached[ $id ] = self::objectives( $id );
						}
						if ( is_wp_error( $cached[ $id ] ) || ! isset( $cached[ $id ][ $reference['term_id'] ] ) ) {
							return Catalog::error( __( 'An objective no longer belongs to the selected syllabus. Reload the picker.', 'ohmylms' ), 409 );
						}
					}
					$next[ $reference['syllabus_id'] . ':' . $reference['term_id'] ] = $reference;
				}
				$next = array_values( $next );
				if ( $next !== $current && ! update_post_meta( $course_id, self::META, $next ) ) {
					return Catalog::error( __( 'The objective mappings could not be saved.', 'ohmylms' ), 500 );
				}
				return true;
			}
		);
	}
}
