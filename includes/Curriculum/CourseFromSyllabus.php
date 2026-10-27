<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Independent draft courses seeded from selected syllabus topics, groups and skills.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\Learning\Catalog;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/** Saves authors from re-typing the structure a syllabus already has. */
final class CourseFromSyllabus {

	/**
	 * Read a source selection without opening or synchronizing a mirrored course.
	 *
	 * @param int $syllabus_id Source syllabus.
	 * @return array|\WP_Error
	 */
	public static function preview( $syllabus_id ) {
		$outline = Syllabus::outline( $syllabus_id );
		if ( is_wp_error( $outline ) ) {
			return $outline;
		}
		$topics = array();
		foreach ( $outline['contents'] as $content ) {
			$lessons = array();
			foreach ( $content['groups'] as $group ) {
				$skills = array_map(
					static function ( $skill ) {
						return array_intersect_key( $skill, array_flip( array( 'term_id', 'name', 'code', 'objective' ) ) );
					},
					$group['skills']
				);
				if ( $skills ) {
					$lessons[] = array(
						'id'     => $group['id'],
						'title'  => \OhMyLMS\Learning\ProgramGroups::title( SyllabusCourse::chapter_title( $group ) ),
						'skills' => $skills,
					);
				}
			}
			if ( $lessons ) {
				$topics[] = array(
					'id'      => $content['id'],
					'name'    => $content['name'],
					'depth'   => $content['depth'],
					'lessons' => $lessons,
				);
			}
		}
		$name = Syllabus::text( $outline['syllabus']['name'] );
		return array(
			'name'     => $name,
			'topics'   => $topics,
			'revision' => md5( wp_json_encode( array( $name, $topics ) ) ),
		);
	}

	/**
	 * Create the course.
	 *
	 * Each selected folder becomes a unit, its groups become lessons and selected skills become optional
	 * outcomes linked to those lessons. The course also references the original syllabus objectives.
	 * Existing callers that omit a selection retain whole-syllabus creation. No activity content is created.
	 *
	 * @param int    $syllabus_id Syllabus ID.
	 * @param string $title       Course title; the syllabus name when empty.
	 * @param array  $selection   Optional skill_ids and reviewed source revision.
	 * @return array|\WP_Error Course ID, name, units and the number of objectives.
	 */
	public static function create( $syllabus_id, $title = '', array $selection = array() ) {
		return Items::exclusive(
			static function () use ( $syllabus_id, $title, $selection ) {
				return self::generate( $syllabus_id, $title, $selection );
			}
		);
	}

	/**
	 * Build while the source curriculum is locked; failures reach the outer transaction.
	 *
	 * @param int    $syllabus_id Source ID.
	 * @param string $title Course title.
	 * @param array  $selection Reviewed selection.
	 * @return array|\WP_Error
	 * @throws \Throwable When any part of generation fails.
	 */
	private static function generate( $syllabus_id, $title, array $selection ) {
		$syllabus_id = (int) $syllabus_id;
		$known       = array_column( CourseObjectives::syllabuses(), null, 'id' );
		if ( ! isset( $known[ $syllabus_id ] ) ) {
			return Catalog::error( __( 'That syllabus does not exist.', 'ohmylms' ), 404 );
		}
		$outline = Syllabus::outline( $syllabus_id );
		if ( is_wp_error( $outline ) ) {
			return $outline;
		}
		$preview = self::preview( $syllabus_id );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}
		$available = array();
		foreach ( $preview['topics'] as $topic ) {
			foreach ( $topic['lessons'] as $lesson ) {
				$available = array_merge( $available, array_column( $lesson['skills'], 'term_id' ) );
			}
		}
		$objectives = array_values( array_unique( $available ) );
		if ( array_key_exists( 'skill_ids', $selection ) ) {
			$ids = $selection['skill_ids'];
			if ( ! is_array( $ids ) || array_values( $ids ) !== $ids || count( $ids ) > CourseObjectives::MAX_TARGETS || array_filter(
				$ids,
				static function ( $id ) {
					return ! is_int( $id ) || $id <= 0; }
			) || array_diff( $ids, $available ) || count( $ids ) !== count( array_unique( $ids ) ) ) {
				return Catalog::error( __( 'Choose skills from this syllabus once each.', 'ohmylms' ) );
			}
			if ( ( $selection['revision'] ?? '' ) !== $preview['revision'] ) {
				return Catalog::error( __( 'The syllabus changed. Reload the selection before creating the course.', 'ohmylms' ), 409 );
			}
			$objectives = $ids;
		}
		foreach ( $outline['contents'] as &$content ) {
			foreach ( $content['groups'] as &$group ) {
				$group['skills'] = array_values(
					array_filter(
						$group['skills'],
						static function ( $skill ) use ( $objectives ) {
							return in_array( (int) $skill['term_id'], $objectives, true );
						}
					)
				);
			}
			unset( $group );
		}
		unset( $content );
		if ( ! $objectives || count( $objectives ) > CourseObjectives::MAX_TARGETS ) {
			return Catalog::error( __( 'Choose between 1 and 200 syllabus skills for this course.', 'ohmylms' ) );
		}
		$name = trim( wp_strip_all_tags( (string) $title ) );
		if ( '' === $name ) {
			$name = $known[ $syllabus_id ]['name'];
		}
		if ( mb_strlen( $name ) > 200 ) {
			return Catalog::error( __( 'Keep the course title within 200 characters.', 'ohmylms' ) );
		}
		$result = array();
		Transaction::run(
			static function () use ( $syllabus_id, $outline, $objectives, $name, &$result ) {
				$route = new \WP_REST_Request( 'POST', '/ohmylms/v1/courses' );
				$route->set_body_params(
					array(
						'name'              => $name,
						'status'            => 'draft',
						'course_type'       => 'self-paced',
						'creation_method'   => 'syllabus',
						'isCommunityEnable' => 'no',
					)
				);
				$response = rest_do_request( $route );
				$course   = $response->get_status() < 300 ? (int) ( $response->get_data()['id'] ?? 0 ) : 0;
				if ( ! $course ) {
					throw new \RuntimeException( 'Course creation failed.' );
				}
				// A new course starts with one unnamed unit; the first domain reuses it.
				$existing = Catalog::chapters( $course );
				$units    = array();
				// Only folders that hold skills become units; the syllabus's own root folder is skipped.
				$folders = array_values(
					array_filter(
						$outline['contents'],
						static function ( $content ) {
							foreach ( $content['groups'] as $group ) {
								if ( ! empty( $group['skills'] ) ) {
									return true;
								}
							}
							return false;
						}
					)
				);
				foreach ( $folders as $position => $content ) {
					$label = trim( wp_strip_all_tags( Syllabus::text( $content['name'] ) ) );
					$label = '' === $label ? __( 'Unit', 'ohmylms' ) . ' ' . ( $position + 1 ) : $label;
					if ( 0 === $position && $existing ) {
						$chapter    = Catalog::update_chapter( $course, (int) $existing[0]['id'], array( 'name' => $label ) );
						$chapter_id = (int) $existing[0]['id'];
					} else {
						$chapter    = Catalog::add_chapter( $course, $label );
						$chapter_id = is_wp_error( $chapter ) ? 0 : (int) ( is_array( $chapter ) ? ( $chapter['id'] ?? 0 ) : $chapter );
					}
					if ( is_wp_error( $chapter ) || ! $chapter_id ) {
						throw new \RuntimeException( 'Unit creation failed.' );
					}
					$units[ $position ] = array(
						'id'      => $chapter_id,
						'name'    => $label,
						'content' => $content,
					);
				}
				$mappings = array();
				$outcomes = array();
				$lessons  = array();
				foreach ( $units as $unit ) {
					foreach ( $unit['content']['groups'] as $group ) {
						if ( ! $group['skills'] ) {
							continue;
						}
						$lesson_id = wp_generate_uuid4();
						$lessons[] = array(
							'id'         => $lesson_id,
							'chapter_id' => $unit['id'],
							'title'      => \OhMyLMS\Learning\ProgramGroups::title( SyllabusCourse::chapter_title( $group ) ),
							'skill_ids'  => array_values( array_unique( array_map( 'intval', array_column( $group['skills'], 'term_id' ) ) ) ),
						);
						foreach ( $group['skills'] as $skill ) {
							$term_id = (int) $skill['term_id'];
							if ( isset( $mappings[ $term_id ] ) ) {
								continue;
							}
							$mappings[ $term_id ] = array(
								'syllabus_id' => $syllabus_id,
								'term_id'     => $term_id,
							);
							$outcomes[]           = array(
								'term_id'    => $term_id,
								'target'     => 'proficient',
								'required'   => false,
								'chapter_id' => $unit['id'],
								'group_id'   => $lesson_id,
							);
						}
					}
				}
				$saved = CourseObjectives::save(
					$course,
					array(
						'mappings' => array_values( $mappings ),
						'revision' => CourseObjectives::payload( $course )['revision'],
					)
				);
				if ( is_wp_error( $saved ) ) {
					throw new \RuntimeException( 'Objectives could not be linked.' );
				}
				$draft = Catalog::save_draft(
					$course,
					array(
						'mode'     => 'traditional',
						'outcomes' => $outcomes,
						'groups'   => $lessons,
					)
				);
				if ( is_wp_error( $draft ) ) {
					throw new \RuntimeException( 'Skills could not be placed.' );
				}
				$result = array(
					'id'         => $course,
					'name'       => $name,
					'units'      => array_map(
						static function ( $unit ) {
							return array(
								'id'   => $unit['id'],
								'name' => $unit['name'],
							);
						},
						$units
					),
					'objectives' => count( $mappings ),
					'lessons'    => count( $lessons ),
				);
			}
		);

		return $result;
	}
}
