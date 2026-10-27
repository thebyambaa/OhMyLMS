<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Learning;

use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\QuestionBank\Banks;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin practice access policy implementation.
 */
final class PracticeAccessPolicy {
	/**
	 * Check for Practice access policy.
	 *
	 * @param array $owner Owner.
	 * @param mixed $skill Skill.
	 * @param int   $course_id Course identifier.
	 */
	public static function check( array $owner, $skill, $course_id = 0 ) {
		if ( ! $course_id ) {
			return true; }
		if ( get_post_type( $course_id ) !== OHMYLMS_COURSE_CPT || get_post_status( $course_id ) !== 'publish' ) {
			return CourseProgram::error( __( 'This course is not available.', 'ohmylms' ), 403 ); }
		$enrollment = CourseProgram::enrollment( (int) ( $owner['student_id'] ?? 0 ), $course_id );
		if ( ! $enrollment ) {
			return CourseProgram::error( __( 'You cannot practise in this course.', 'ohmylms' ), 403 ); }
		$program = CourseProgram::for_enrollment( $enrollment );
		if ( ! $program || ! in_array( (int) $skill, array_column( $program['outcomes'], 'term_id' ), true ) ) {
			return CourseProgram::error( __( 'This skill is not an outcome of your course.', 'ohmylms' ), 403 ); }
		return true;
	}

	/**
	 * Pool for Practice access policy.
	 *
	 * @param array $pool Pool.
	 * @param array $owner Owner.
	 * @param mixed $skill Skill.
	 * @param int   $course_id Course identifier.
	 */
	public static function pool( array $pool, array $owner, $skill, $course_id = 0 ) {
		global $wpdb;
		$check = self::check( $owner, $skill, $course_id );
		if ( is_wp_error( $check ) ) {
			return array(); }
		if ( $course_id ) {
			$program = CourseProgram::for_enrollment( CourseProgram::enrollment( $owner['student_id'], $course_id ) );
			$allowed = array_column( CourseProgram::pool( $program, $skill ), 'question_id' );
			return array_values(
				array_filter(
					$pool,
					static function ( $row ) use ( $allowed ) {
						return in_array( $row['question_id'], $allowed, true );
					}
				)
			);
		}
		// Catalog practice never exposes course banks to guests or unrelated learners.
		$public  = (bool) get_term_meta( $skill, '_ohmylms_public_practice', true );
		$student = (int) ( $owner['student_id'] ?? 0 );
		$courses = $student ? array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT course_id FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id=%d AND status='enrolled'", $student ) ) ) : array();
		return array_values(
			array_filter(
				$pool,
				static function ( $row ) use ( $public, $student, $courses ) {
					$bank_id = (int) $row['bank_id'];
					if ( ! $bank_id ) {
						return $public || ( $student && (int) $row['author_id'] === $student ); }
					$bank = Banks::get( $bank_id );
					return $bank && ( ( $public && 'site' === $bank['visibility'] ) || ( $student && Banks::can( $bank_id, 'use', $student ) ) || ( (int) $bank['course_id'] && in_array( (int) $bank['course_id'], $courses, true ) ) );
				}
			)
		);
	}

	/**
	 * Session for Practice access policy.
	 *
	 * @param array $session Session.
	 */
	public static function session( array $session ) {
		if ( 'skill' !== $session['mode'] ) {
			return true; }
		$owner  = array(
			'student_id' => (int) $session['student_id'],
			'guest_id'   => (int) $session['guest_id'],
		);
		$access = self::check( $owner, (int) $session['term_id'], (int) $session['course_id'] );
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( 'active' !== $session['status'] ) {
			return true; }
		$allowed = array_map( 'intval', array_column( self::pool( \OhMyLMS\Practice\Selector::pool( (int) $session['term_id'] ), $owner, (int) $session['term_id'], (int) $session['course_id'] ), 'question_id' ) );
		foreach ( \OhMyLMS\Practice\Sessions::items( $session['id'] ) as $item ) {
			if ( null === $item['answered_at'] && ! in_array( (int) $item['question_id'], $allowed, true ) ) {
				return CourseProgram::error( __( 'This practice question is no longer available. Start a new session.', 'ohmylms' ), 403 ); }
		}
		return true;
	}
}
