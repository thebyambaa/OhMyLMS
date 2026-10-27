<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Proposes the skills a course lesson teaches, from its own content.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

use OhMyLMS\AI\Client;
use OhMyLMS\AI\Settings as AiSettings;
use OhMyLMS\Curriculum\AiAnalysis;
use OhMyLMS\Curriculum\AiAnalysisModel;
use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;

defined( 'ABSPATH' ) || exit;

/**
 * Reads a lesson (or a unit's activities outside lessons): its title, its activities' names and the
 * text of its readings, and asks the AI connection for the skills it teaches, preferring skills the
 * subject's library already has. Only curriculum text is sent; no learner data.
 */
final class CourseContentSource implements SkillSource {

	const MAX_CANDIDATES = 30;

	/** {@inheritDoc} */
	public function id() {
		return 'course-content';
	}

	/** {@inheritDoc} */
	public function label() {
		return __( 'Course content (AI)', 'ohmylms' );
	}

	/** {@inheritDoc} */
	public function available() {
		return AiAnalysis::available();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $request course_id, unit_id, group_id (lesson UUID, or empty for the unit), subject.
	 */
	public function propose( array $request ) {
		$context = self::context( (int) ( $request['course_id'] ?? 0 ), (int) ( $request['unit_id'] ?? 0 ), (string) ( $request['group_id'] ?? '' ) );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$subject    = (string) ( $request['subject'] ?? '' );
		$profile    = SubjectProfiles::get( $subject );
		$domains    = Comparison::domains( $subject );
		$library    = AiAnalysis::catalog( $subject );
		$candidates = self::candidates( implode( ' ', array_merge( array( $context['lesson'], $context['unit'] ), $context['content'] ) ), $library );
		$reply      = Client::complete(
			CourseSkillModel::prompt( $context, $profile, $domains, $candidates ) + array(
				'max_tokens' => 2500,
				'long'       => true,
			)
		);
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		return array(
			'proposals' => CourseSkillModel::parse( (string) $reply['text'], array_column( $candidates, 'id' ), $domains, AiAnalysisModel::max_level( $profile ) ),
			'meta'      => array(
				'provider' => (string) ( AiSettings::get()['provider'] ?? '' ),
				'model'    => (string) ( AiSettings::get()['model'] ?? '' ),
				'prompt'   => CourseSkillModel::PROMPT_VERSION,
			),
		);
	}

	/**
	 * The course, unit and lesson titles and the lesson's text.
	 *
	 * @param int    $course_id Course.
	 * @param int    $unit_id   Unit (chapter) ID.
	 * @param string $group_id  Lesson UUID, or empty for the unit's activities outside lessons.
	 * @return array|\WP_Error course, unit, lesson, content (lines).
	 */
	public static function context( $course_id, $unit_id, $group_id ) {
		$unit = null;
		foreach ( Catalog::chapters( $course_id ) as $chapter ) {
			if ( (int) $chapter['id'] === $unit_id ) {
				$unit = $chapter;
			}
		}
		if ( ! $unit ) {
			return new \WP_Error( 'ohmylms_course_skill_unit', __( 'Choose a unit of this course.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$program = CourseProgram::editor( $course_id )['draft'];
		$lesson  = null;
		foreach ( (array) ( $program['groups'] ?? array() ) as $group ) {
			if ( '' !== $group_id && $group['id'] === $group_id && (int) $group['chapter_id'] === $unit_id ) {
				$lesson = $group;
			}
		}
		if ( '' !== $group_id && ! $lesson ) {
			return new \WP_Error( 'ohmylms_course_skill_lesson', __( 'Choose a lesson of this unit.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$content     = array();
		$description = wp_strip_all_tags( (string) get_post_field( 'post_content', $unit_id ) );
		if ( '' !== trim( $description ) ) {
			$content[] = 'Unit description: ' . $description;
		}
		foreach ( (array) $program['items'] as $item ) {
			if ( (int) ( $item['chapter_id'] ?? 0 ) !== $unit_id || (string) ( $item['group_id'] ?? '' ) !== $group_id || 'practice' === $item['type'] ) {
				continue;
			}
			$title     = (string) ( $item['title'] ?? '' ) ? (string) $item['title'] : get_the_title( (int) $item['content_id'] );
			$content[] = ucfirst( (string) $item['type'] ) . ': ' . $title;
			if ( 'lesson' === $item['type'] ) {
				$body = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', (int) $item['content_id'] ) ) );
				if ( '' !== $body ) {
					$content[] = $body;
				}
			}
		}
		return array(
			'course'  => get_the_title( $course_id ),
			'unit'    => (string) $unit['name'],
			'lesson'  => $lesson ? (string) $lesson['title'] : __( 'Activities outside lessons', 'ohmylms' ),
			'content' => $content,
		);
	}

	/**
	 * Library skills most likely to be taught by the text, best first.
	 *
	 * @param string $text    Lesson text.
	 * @param array  $library Skill ID => name, description, topic, domain, grade_from, grade_to.
	 * @return array[] id, name, domain, grades.
	 */
	public static function candidates( $text, array $library ) {
		$docs = array();
		foreach ( $library as $id => $skill ) {
			$docs[ $id ] = trim( $skill['name'] . ' ' . $skill['name'] . ' ' . ( $skill['topic'] ?? '' ) . ' ' . ( $skill['description'] ?? '' ) );
		}
		$result = array();
		foreach ( array_keys( AiAnalysisModel::rank( $text, $docs, self::MAX_CANDIDATES ) ) as $id ) {
			$skill    = $library[ $id ];
			$result[] = array(
				'id'     => (int) $id,
				'name'   => $skill['name'],
				'domain' => $skill['domain'],
				'grades' => $skill['grade_from'] ? $skill['grade_from'] . ( $skill['grade_to'] > $skill['grade_from'] ? '-' . $skill['grade_to'] : '' ) : '',
			);
		}
		return $result;
	}
}
