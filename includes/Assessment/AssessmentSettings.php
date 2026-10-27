<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Assessment-level settings kept separately from the legacy quiz settings blob, so the
 * existing quiz editor cannot overwrite them: kind preset (quiz / exam / practice),
 * feedback release, availability window, grace period, sections with slot marks and
 * per-student accommodations (extra time). They are frozen into published revisions.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/**
 * Assessment-level settings kept separately from the legacy quiz settings blob, so the
 * existing quiz editor cannot overwrite them: kind preset (quiz / exam / practice),
 * feedback release, availability window, grace period, sections with slot marks and
 * per-student accommodations (extra time). They are frozen into published revisions.
 */
final class AssessmentSettings {
	const META = '_ohmylms_assessment_settings';
	// How the assessment is presented: a short quiz, a unit test, or a course challenge (mixed-skill checks).
	const STYLES   = array( 'quiz', 'unit-test', 'course-challenge' );
	const FEEDBACK = array( 'immediate', 'after_close', 'manual' );

	/**
	 * Defaults for Assessment settings.
	 */
	public static function defaults() {
		return array(
			'kind'             => 'quiz',
			'style'            => 'quiz',
			'feedback_release' => 'immediate',
			'released'         => false,
			'available_from'   => '',
			'available_until'  => '',
			'grace_seconds'    => Deadlines::DEFAULT_GRACE,
			'sections'         => array(),
			'accommodations'   => array(),
		);
	}

	/** Defaults applied when a teacher picks the exam preset. */
	public static function exam_preset() {
		return array(
			'kind'             => 'exam',
			'feedback_release' => 'after_close',
			'released'         => false,
			'grace_seconds'    => 30,
		);
	}

	/**
	 * Get for Assessment settings.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 */
	public static function get( $quiz_id ) {
		$saved = get_post_meta( (int) $quiz_id, self::META, true );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/** Validate input against the quiz; returns the full normalized settings or WP_Error.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param array $input Input.
	 */
	public static function validate( $quiz_id, array $input ) {
		$settings = array_merge( self::get( $quiz_id ), array_intersect_key( $input, self::defaults() ) );
		if ( ! empty( $input['preset'] ) && 'exam' === $input['preset'] ) {
			$settings = array_merge( $settings, self::exam_preset() ); }
		if ( ! in_array( $settings['kind'], Profiles::kinds(), true ) ) {
			return self::invalid( __( 'Unknown assessment kind.', 'ohmylms' ) ); }
		if ( ! in_array( $settings['style'], self::STYLES, true ) ) {
			return self::invalid( __( 'Unknown assessment style.', 'ohmylms' ) ); }
		if ( ! in_array( $settings['feedback_release'], self::FEEDBACK, true ) ) {
			return self::invalid( __( 'Unknown feedback release policy.', 'ohmylms' ) ); }
		$settings['released'] = rest_sanitize_boolean( $settings['released'] );
		foreach ( array( 'available_from', 'available_until' ) as $key ) {
			$value = trim( (string) $settings[ $key ] );
			if ( '' !== $value ) {
				$time = strtotime( $value . ( preg_match( '/(Z|[+-]\d\d:?\d\d|UTC)$/i', $value ) ? '' : ' UTC' ) );
				if ( ! $time ) {
					return self::invalid(
						sprintf( /* translators: %s: text value. */
							__( '%s is not a valid date and time.', 'ohmylms' ),
							$key
						)
					); }
				$value = gmdate( 'Y-m-d H:i:s', $time );
			}
			$settings[ $key ] = $value;
		}
		if ( $settings['available_from'] && $settings['available_until'] && strtotime( $settings['available_until'] ) <= strtotime( $settings['available_from'] ) ) {
			return self::invalid( __( 'The closing time must be after the opening time.', 'ohmylms' ) );
		}
		$settings['grace_seconds'] = max( 0, min( 600, (int) $settings['grace_seconds'] ) );
		$linked                    = array_map( 'intval', array_column( ohmylms_get_quiz( (int) $quiz_id )->get_questions(), 'id' ) );
		$sections                  = array();
		$seen                      = array();
		foreach ( (array) $settings['sections'] as $section ) {
			if ( ! is_array( $section ) ) {
				return self::invalid( __( 'Each section must be an object.', 'ohmylms' ) ); }
			$title     = mb_substr( sanitize_text_field( (string) ( $section['title'] ?? '' ) ), 0, 190 );
			$questions = array();
			foreach ( (array) ( $section['questions'] ?? array() ) as $question_id ) {
				$question_id = (int) $question_id;
				if ( ! in_array( $question_id, $linked, true ) ) {
					return self::invalid( __( 'A section contains a question that is not in this quiz.', 'ohmylms' ) ); }
				if ( isset( $seen[ $question_id ] ) ) {
					return self::invalid( __( 'A question can belong to only one section.', 'ohmylms' ) ); }
				$seen[ $question_id ] = true;
				$questions[]          = $question_id;
			}
			$marks = array();
			foreach ( (array) ( $section['marks'] ?? array() ) as $question_id => $value ) {
				if ( ! in_array( (int) $question_id, $questions, true ) ) {
					continue; }
				if ( ! is_numeric( $value ) || (float) $value < 0 || ! is_finite( (float) $value ) ) {
					return self::invalid( __( 'Slot marks must be zero or more.', 'ohmylms' ) ); }
				$marks[ (int) $question_id ] = round( (float) $value, 4 );
			}
			$sections[] = array(
				'title'     => $title,
				'questions' => $questions,
				'marks'     => $marks,
				'new_page'  => ! empty( $section['new_page'] ),
			);
		}
		$settings['sections'] = $sections;
		$accommodations       = array();
		foreach ( (array) $settings['accommodations'] as $user_id => $seconds ) {
			if ( ! get_user_by( 'id', (int) $user_id ) ) {
				return self::invalid( __( 'An accommodation refers to an unknown learner.', 'ohmylms' ) ); }
			$accommodations[ (int) $user_id ] = max( 0, min( 86400, (int) $seconds ) );
		}
		$settings['accommodations'] = array_filter( $accommodations );
		return $settings;
	}

	/**
	 * Save for Assessment settings.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param array $input Input.
	 */
	public static function save( $quiz_id, array $input ) {
		$settings = self::validate( $quiz_id, $input );
		if ( is_wp_error( $settings ) ) {
			return $settings; }
		update_post_meta( (int) $quiz_id, self::META, $settings );
		return $settings;
	}

	/** Values frozen into a revision (merged over legacy quiz settings).
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 */
	public static function frozen( $quiz_id ) {
		$settings = self::get( $quiz_id );
		return array(
			'assessment_kind'  => $settings['kind'],
			'feedback_release' => $settings['feedback_release'],
			'grace_seconds'    => $settings['grace_seconds'],
			'sections'         => $settings['sections'],
			'available_until'  => $settings['available_until'],
		);
	}

	/** Is the quiz open for a new attempt now?
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param mixed $now Now.
	 */
	public static function availability( $quiz_id, $now = null ) {
		$settings = self::get( $quiz_id );
		$now      = $now ?? time();
		if ( $settings['available_from'] && $now < strtotime( $settings['available_from'] . ' UTC' ) ) {
			return new \WP_Error(
				'quiz_not_open',
				sprintf( /* translators: %s: text value. */
					__( 'This assessment opens at %s.', 'ohmylms' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $settings['available_from'] . ' UTC' ) )
				),
				array( 'status' => 403 )
			);
		}
		if ( $settings['available_until'] && $now >= strtotime( $settings['available_until'] . ' UTC' ) ) {
			return new \WP_Error( 'quiz_closed', __( 'This assessment is closed.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/** When the assessment closes, as a UTC timestamp; null when it never closes.
	 *
	 * The closing time is a hard deadline: an attempt started late ends at the close,
	 * not after its full time limit.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 */
	public static function closes_ts( $quiz_id ) {
		$settings = self::get( $quiz_id );
		return $settings['available_until'] ? strtotime( $settings['available_until'] . ' UTC' ) : null;
	}

	/** Extra time for the learner of a new attempt (filter: ohmylms_attempt_extra_seconds).
	 *
	 * @param mixed $seconds Seconds.
	 * @param mixed $attempt_id Attempt identifier.
	 * @param mixed $revision Revision.
	 */
	public static function extra_seconds( $seconds, $attempt_id, $revision ) {
		global $wpdb;
		$student  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT student_id FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d", (int) $attempt_id ) );
		$settings = self::get( (int) $revision['quiz_id'] );
		return (int) $seconds + (int) ( $settings['accommodations'][ $student ] ?? 0 );
	}

	/** Whether the learner may see marks and correctness for this attempt yet.
	 *
	 * @param mixed $attempt_id Attempt identifier.
	 */
	public static function feedback_visible( $attempt_id ) {
		$context = AttemptItems::context( $attempt_id );
		if ( ! $context ) {
			return true; }
		$revision = RevisionPublisher::revision( (int) $context['revision_id'] );
		$policy   = $revision['settings']['feedback_release'] ?? 'immediate';
		if ( 'immediate' === $policy ) {
			return true; }
		$current = self::get( (int) $revision['quiz_id'] );
		if ( 'manual' === $policy ) {
			return ! empty( $current['released'] ); }
		// after_close: once the assessment closes (or a teacher releases early).
		return ! empty( $current['released'] ) || ( $current['available_until'] && time() >= strtotime( $current['available_until'] . ' UTC' ) );
	}

	/**
	 * Invalid for Assessment settings.
	 *
	 * @param mixed $message Message.
	 */
	private static function invalid( $message ) {
		return new \WP_Error( 'ohmylms_assessment_settings_invalid', $message, array( 'status' => 400 ) );
	}
}
