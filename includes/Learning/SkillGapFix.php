<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * One-step fixes for the gaps the skill coverage report finds.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Studio\CourseLayout;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Fixes a "no lesson teaches this skill" gap: creates a draft lesson, links it to the skill and places it in a
 * unit of the course, as one change to the course's draft program.
 *
 * It runs under the same revision check and lock as every other draft write, so it cannot overwrite an outline
 * someone else changed since the author read the report. The lesson is only a draft with the skill linked; the
 * author writes it, and nothing is published.
 */
final class SkillGapFix {

	/**
	 * Most characters in a lesson title.
	 */
	const MAX_TITLE = 200;

	/**
	 * Add a draft lesson for a skill to a unit.
	 *
	 * @param int    $course_id Course ID.
	 * @param int    $skill_id  Skill term ID.
	 * @param int    $unit_id   Unit (chapter) ID in the course.
	 * @param string $title     Lesson title; the skill's name when empty.
	 * @param string $revision  Revision from the coverage report.
	 * @return array{lesson:array,revision:string,report:array}|WP_Error
	 */
	public static function add_lesson( $course_id, $skill_id, $unit_id, $title, $revision ) {
		$course_id = (int) $course_id;
		$skill_id  = (int) $skill_id;
		$unit_id   = (int) $unit_id;
		$term      = $skill_id ? get_term( $skill_id, Taxonomy::NAME ) : null;
		if ( ! $term || is_wp_error( $term ) ) {
			return Catalog::error( __( 'Choose a skill that exists.', 'ohmylms' ), 400, 'ohmylms_skill_missing' );
		}
		if ( ! $unit_id || ! CourseLayout::chapter_in_course( $unit_id, $course_id ) ) {
			return Catalog::error( __( 'Choose a unit of this course.', 'ohmylms' ), 400, 'ohmylms_unit_missing' );
		}
		$title = trim( sanitize_text_field( (string) $title ) );
		if ( '' === $title ) {
			$title = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
		}
		if ( mb_strlen( $title ) > self::MAX_TITLE ) {
			return Catalog::error( __( 'A lesson title can have at most 200 characters.', 'ohmylms' ), 400, 'ohmylms_title_long' );
		}
		return Catalog::with_revision(
			$course_id,
			$revision,
			static function () use ( $course_id, $skill_id, $unit_id, $title ) {
				$lesson = ActivityDrafts::create(
					$course_id,
					array(
						'kind'       => 'reading',
						'title'      => $title,
						'request_id' => wp_generate_uuid4(),
					)
				);
				if ( is_wp_error( $lesson ) ) {
					return $lesson;
				}
				$lesson_id = (int) $lesson['id'];
				$fail      = static function ( WP_Error $error ) use ( $lesson_id ) {
					wp_delete_post( $lesson_id, true );
					return $error;
				};
				$linked    = Catalog::set_lesson_skills( $lesson_id, array( $skill_id ) );
				if ( is_wp_error( $linked ) ) {
					return $fail( $linked );
				}
				$attachments   = array_values(
					array_filter(
						CourseProgram::editor( $course_id )['draft']['items'],
						static function ( $item ) {
							return 'practice' !== $item['type'];
						}
					)
				);
				$attachments[] = array(
					'id'         => wp_generate_uuid4(),
					'type'       => 'lesson',
					'content_id' => $lesson_id,
					'chapter_id' => $unit_id,
					'required'   => true,
				);
				$saved         = Catalog::save_draft( $course_id, array( 'attachments' => $attachments ) );
				if ( is_wp_error( $saved ) ) {
					return $fail( $saved );
				}
				return array(
					'lesson'   => array(
						'id'       => $lesson_id,
						'title'    => (string) $lesson['title'],
						'unit_id'  => $unit_id,
						'edit_url' => (string) $lesson['edit_url'],
					),
					'revision' => Catalog::revision( $course_id ),
					'report'   => SkillCoverage::report( $course_id ),
				);
			}
		);
	}
}
