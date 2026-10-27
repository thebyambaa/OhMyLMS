<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Admin screen for building a mixed-skill quiz, unit test or course challenge.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assessment;

use OhMyLMS\QuestionBank\SkillCatalogue;

defined( 'ABSPATH' ) || exit;

/** OhMyLMS -> Skill assessments. */
final class SkillAssessmentPage {

	const SLUG = 'ohmylms-skill-assessments';

	/** Register the menu entry. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 33 );
	}

	/** Add the page under the OhMyLMS menu. */
	public static function menu() {
		add_submenu_page( 'ohmylms', __( 'Skill assessments', 'ohmylms' ), __( 'Skill assessments', 'ohmylms' ), 'edit_posts', self::SLUG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Courses the current user can edit, newest first.
	 *
	 * @return array<int,string> Course ID to title.
	 */
	private static function courses() {
		$courses = array();
		foreach ( get_posts(
			array(
				'post_type'   => OHMYLMS_COURSE_CPT,
				'post_status' => array( 'publish', 'draft', 'private' ),
				'numberposts' => 200,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		) as $course ) {
			if ( current_user_can( 'edit_post', $course->ID ) ) {
				$courses[ (int) $course->ID ] = $course->post_title;
			}
		}
		return $courses;
	}

	/**
	 * Units of a course.
	 *
	 * @param int $course_id Course.
	 * @return array<int,string> Unit ID to title.
	 */
	private static function units( $course_id ) {
		$units = array();
		if ( $course_id && current_user_can( 'edit_post', $course_id ) ) {
			foreach ( (array) ohmylms_get_course( $course_id )->get_chapters() as $chapter ) {
				$units[ (int) $chapter['id'] ] = (string) $chapter['name'];
			}
		}
		return $units;
	}

	/** Render the page and handle its form. */
	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading a filter only; nothing is written from these.
		$course_id = isset( $_GET['course'] ) ? absint( $_GET['course'] ) : 0;
		$chapter   = isset( $_GET['chapter'] ) ? absint( $_GET['chapter'] ) : 0;
		$search    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		// phpcs:enable
		$created = null;
		$message = '';
		$form    = array(
			'title'      => '',
			'style'      => 'quiz',
			'per_skill'  => 1,
			'time_limit' => 0,
			'publish'    => false,
			'place'      => true,
			'skills'     => array(),
		);
		if ( isset( $_POST['ohmylms_skill_assessment'] ) && check_admin_referer( 'ohmylms_skill_assessment' ) ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by check_admin_referer() above.
			$form      = array(
				'title'      => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'style'      => isset( $_POST['style'] ) ? sanitize_key( wp_unslash( $_POST['style'] ) ) : 'quiz',
				'per_skill'  => isset( $_POST['per_skill'] ) ? absint( $_POST['per_skill'] ) : 1,
				'time_limit' => isset( $_POST['time_limit'] ) ? absint( $_POST['time_limit'] ) : 0,
				'publish'    => ! empty( $_POST['publish'] ),
				'place'      => ! empty( $_POST['place'] ),
				'skills'     => isset( $_POST['skills'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['skills'] ) ) : array(),
			);
			$course_id = isset( $_POST['course'] ) ? absint( $_POST['course'] ) : $course_id;
			$chapter   = isset( $_POST['chapter'] ) ? absint( $_POST['chapter'] ) : $chapter;
			// phpcs:enable
			$result = SkillAssessment::create(
				array(
					'title'      => $form['title'],
					'style'      => $form['style'],
					'skills'     => $form['skills'],
					'per_skill'  => $form['per_skill'],
					'time_limit' => $form['time_limit'],
					'publish'    => $form['publish'],
					'chapter_id' => $form['place'] ? $chapter : 0,
				)
			);
			if ( is_wp_error( $result ) ) {
				$message = $result->get_error_message();
			} else {
				$created = $result;
			}
		}
		$course_report = null;
		if ( isset( $_POST['ohmylms_course_assessments'] ) && check_admin_referer( 'ohmylms_course_assessments' ) ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by check_admin_referer() above.
			$course_id     = isset( $_POST['course'] ) ? absint( $_POST['course'] ) : 0;
			$course_report = SkillAssessment::create_for_course(
				$course_id,
				array(
					'per_skill' => isset( $_POST['per_skill'] ) ? absint( $_POST['per_skill'] ) : 1,
					'challenge' => ! empty( $_POST['challenge'] ),
					'publish'   => ! empty( $_POST['publish'] ),
				)
			);
			// phpcs:enable
			if ( is_wp_error( $course_report ) ) {
				$message       = $course_report->get_error_message();
				$course_report = null;
			}
		}
		$courses = self::courses();
		if ( ! isset( $courses[ $course_id ] ) ) {
			$course_id = 0;
		}
		$units = self::units( $course_id );
		if ( ! isset( $units[ $chapter ] ) ) {
			$chapter = 0;
		}
		$unit_skills = $chapter ? SkillAssessment::unit_skills( $chapter ) : array();
		$found       = array();
		if ( '' !== $search ) {
			$known = array_column( $unit_skills, 'id' );
			foreach ( SkillCatalogue::search( $search, 1, 20 )['items'] as $skill ) {
				if ( in_array( $skill['id'], $known, true ) ) {
					continue;
				}
				$skill['available'] = Pools::available( $skill['id'] );
				$found[]            = $skill;
			}
		}
		ohmylms_get_template(
			'admin/skill-assessments.php',
			array(
				'courses'       => $courses,
				'units'         => $units,
				'course_id'     => $course_id,
				'chapter'       => $chapter,
				'unit_skills'   => $unit_skills,
				'search'        => $search,
				'found'         => $found,
				'form'          => $form,
				'created'       => $created,
				'course_report' => $course_report,
				'message'       => $message,
				'styles'        => array(
					'quiz'             => array( SkillAssessment::label( 'quiz' ), __( 'A short check of the skills so far.', 'ohmylms' ) ),
					'unit-test'        => array( SkillAssessment::label( 'unit-test' ), __( 'A check of every skill in the unit.', 'ohmylms' ) ),
					'course-challenge' => array( SkillAssessment::label( 'course-challenge' ), __( 'A check of the skills across the course.', 'ohmylms' ) ),
				),
			)
		);
	}
}
