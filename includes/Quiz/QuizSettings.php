<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Typed quiz settings of the `quizzes` REST API.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Quiz;

use OhMyLMS\Assessment\AssessmentSettings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Typed quiz settings of the `quizzes` REST API.
 *
 * The learner flow, the quiz player, grading and the frozen revisions read two stored values: the
 * `_quiz_settings` blob (nested legacy keys such as `time_limit.value`) and the assessment settings
 * (`_ohmylms_assessment_settings`: feedback release and availability window). This class is the only
 * place that maps them to and from the flat, validated API shape. It never renames a stored key and never
 * drops a key it does not know: unknown keys written by the question editor or add-ons are preserved.
 */
final class QuizSettings {

	/**
	 * Post meta key of the settings blob.
	 */
	const META = '_quiz_settings';

	/**
	 * Allowed page layouts, as read by the quiz player.
	 */
	const LAYOUTS = array( 'one_question_per_page', 'all_questions_in_one_page', 'number_of_questions_per_page' );

	/**
	 * Largest time limit in minutes (one week).
	 */
	const MAX_TIME_LIMIT = 10080;

	/**
	 * Largest number of attempts.
	 */
	const MAX_ATTEMPTS = 1000;

	/**
	 * Largest number of questions on one page.
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Settings of a quiz that stored nothing.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'time_limit_minutes'   => 0,
			'passing_score'        => 0,
			'attempts_allowed'     => 1,
			'randomize_questions'  => false,
			'show_correct_answers' => true,
			'layout'               => 'one_question_per_page',
			'questions_per_page'   => 1,
			'feedback_mode'        => 'immediate',
			'available_from'       => null,
			'available_until'      => null,
		);
	}

	/**
	 * Read the settings of a quiz in API shape.
	 *
	 * @param int $quiz_id Quiz post ID.
	 * @return array<string,mixed>
	 */
	public static function read( $quiz_id ) {
		$raw = get_post_meta( absint( $quiz_id ), self::META, true );
		$raw = is_array( $raw ) ? $raw : array();
		$out = self::defaults();

		$limit = isset( $raw['time_limit'] ) && is_array( $raw['time_limit'] ) ? $raw['time_limit'] : array();
		$value = isset( $limit['value'] ) && is_numeric( $limit['value'] ) ? (float) $limit['value'] : 0.0;
		$unit  = isset( $limit['type'] ) ? (string) $limit['type'] : 'minutes';
		$scale = array(
			'hours' => 60,
			'days'  => 1440,
		);

		$out['time_limit_minutes'] = (int) round( max( 0, $value ) * ( isset( $scale[ $unit ] ) ? $scale[ $unit ] : 1 ) );

		$passing = isset( $raw['passing_grade'] ) && is_array( $raw['passing_grade'] ) ? $raw['passing_grade'] : array();
		if ( ! empty( $passing['enabled'] ) && isset( $passing['value'] ) && is_numeric( $passing['value'] ) ) {
			$out['passing_score'] = round( (float) $passing['value'], 2 );
		}

		if ( isset( $raw['allow_attempts'] ) && is_numeric( $raw['allow_attempts'] ) ) {
			$out['attempts_allowed'] = max( 0, (int) $raw['allow_attempts'] );
		}
		$out['randomize_questions'] = ! empty( $raw['randomize_questions'] );

		$show                        = array_key_exists( 'show_correct_answers', $raw ) ? (bool) $raw['show_correct_answers'] : true;
		$out['show_correct_answers'] = $show && empty( $raw['hide_answers'] );
		$out['layout']               = isset( $raw['layout'] ) && in_array( $raw['layout'], self::LAYOUTS, true ) ? $raw['layout'] : 'one_question_per_page';
		$per_page                    = isset( $raw['question_in_one_page'] ) && is_numeric( $raw['question_in_one_page'] ) ? (int) $raw['question_in_one_page'] : 1;
		$out['questions_per_page']   = max( 1, min( self::MAX_PER_PAGE, $per_page ) );
		$assessment                  = AssessmentSettings::get( $quiz_id );
		$out['feedback_mode']        = in_array( $assessment['feedback_release'], AssessmentSettings::FEEDBACK, true ) ? $assessment['feedback_release'] : 'immediate';
		$out['available_from']       = self::to_iso( $assessment['available_from'] );
		$out['available_until']      = self::to_iso( $assessment['available_until'] );

		return $out;
	}

	/**
	 * Validate submitted settings against the current ones.
	 *
	 * Only submitted keys change. The merged result is validated as a whole, so a lone `available_until`
	 * is checked against the stored `available_from`. The REST schema has already checked types and ranges;
	 * this is the second line of defence and the home of the cross-field rules.
	 *
	 * @param array<string,mixed> $input   Submitted settings.
	 * @param array<string,mixed> $current Current settings in API shape.
	 * @return array<string,mixed>|WP_Error The complete merged settings, or a 422 error.
	 */
	public static function validate( array $input, array $current ) {
		$known = self::defaults();
		foreach ( array_keys( $input ) as $key ) {
			if ( ! array_key_exists( $key, $known ) ) {
				return self::invalid( 'ohmylms_quiz_unknown_setting', sprintf( /* translators: %s: setting name. */ __( 'Unknown quiz setting "%s".', 'ohmylms' ), $key ) );
			}
		}
		$merged = array_merge( $current, $input );

		if ( ! self::is_int_between( $merged['time_limit_minutes'], 0, self::MAX_TIME_LIMIT ) ) {
			return self::invalid( 'ohmylms_quiz_invalid_time_limit', __( 'The time limit must be a whole number of minutes from 0 (no limit) to 10080.', 'ohmylms' ) );
		}
		if ( ( ! is_int( $merged['passing_score'] ) && ! is_float( $merged['passing_score'] ) ) || $merged['passing_score'] < 0 || $merged['passing_score'] > 100 || ! is_finite( (float) $merged['passing_score'] ) ) {
			return self::invalid( 'ohmylms_quiz_invalid_passing_score', __( 'The passing score must be a number from 0 to 100.', 'ohmylms' ) );
		}
		if ( ! self::is_int_between( $merged['attempts_allowed'], 0, self::MAX_ATTEMPTS ) ) {
			return self::invalid( 'ohmylms_quiz_invalid_attempts', __( 'Attempts allowed must be a whole number from 0 to 1000.', 'ohmylms' ) );
		}
		if ( ! is_bool( $merged['randomize_questions'] ) || ! is_bool( $merged['show_correct_answers'] ) ) {
			return self::invalid( 'ohmylms_quiz_invalid_flag', __( 'Randomisation and answer review settings must be true or false.', 'ohmylms' ) );
		}
		if ( ! in_array( $merged['layout'], self::LAYOUTS, true ) ) {
			return self::invalid( 'ohmylms_quiz_invalid_layout', __( 'Unknown question layout.', 'ohmylms' ) );
		}
		if ( ! self::is_int_between( $merged['questions_per_page'], 1, self::MAX_PER_PAGE ) ) {
			return self::invalid( 'ohmylms_quiz_invalid_questions_per_page', __( 'Questions per page must be a whole number from 1 to 100.', 'ohmylms' ) );
		}
		if ( ! in_array( $merged['feedback_mode'], AssessmentSettings::FEEDBACK, true ) ) {
			return self::invalid( 'ohmylms_quiz_invalid_feedback_mode', __( 'Unknown feedback mode.', 'ohmylms' ) );
		}
		$from  = self::parse_time( $merged['available_from'] );
		$until = self::parse_time( $merged['available_until'] );
		if ( false === $from || false === $until ) {
			return self::invalid( 'ohmylms_quiz_invalid_availability', __( 'Availability dates must be valid date and time values.', 'ohmylms' ) );
		}
		if ( null !== $from && null !== $until && $until <= $from ) {
			return self::invalid( 'ohmylms_quiz_invalid_availability', __( 'The closing time must be after the opening time.', 'ohmylms' ) );
		}
		$merged['passing_score']   = round( (float) $merged['passing_score'], 2 );
		$merged['available_from']  = null === $from ? null : gmdate( 'Y-m-d\TH:i:s\Z', $from );
		$merged['available_until'] = null === $until ? null : gmdate( 'Y-m-d\TH:i:s\Z', $until );

		return $merged;
	}

	/**
	 * Store validated settings. Keys of the blob that are not part of the API are kept.
	 *
	 * @param int                 $quiz_id  Quiz post ID.
	 * @param array<string,mixed> $settings Complete settings returned by {@see self::validate()}.
	 * @return true|WP_Error
	 */
	public static function save( $quiz_id, array $settings ) {
		$quiz_id = absint( $quiz_id );
		$raw     = get_post_meta( $quiz_id, self::META, true );
		$raw     = is_array( $raw ) ? $raw : array();

		$limit             = isset( $raw['time_limit'] ) && is_array( $raw['time_limit'] ) ? $raw['time_limit'] : array();
		$raw['time_limit'] = array_merge(
			$limit,
			array(
				'value' => (int) $settings['time_limit_minutes'],
				'type'  => 'minutes',
			)
		);

		$passing              = isset( $raw['passing_grade'] ) && is_array( $raw['passing_grade'] ) ? $raw['passing_grade'] : array();
		$raw['passing_grade'] = array_merge(
			$passing,
			array(
				'enabled' => $settings['passing_score'] > 0,
				'value'   => $settings['passing_score'],
			)
		);

		$raw['allow_attempts']       = (int) $settings['attempts_allowed'];
		$raw['randomize_questions']  = (bool) $settings['randomize_questions'];
		$raw['show_correct_answers'] = (bool) $settings['show_correct_answers'];
		if ( $settings['show_correct_answers'] ) {
			// `hide_answers` is the older switch and wins over `show_correct_answers`; keep them in agreement.
			$raw['hide_answers'] = false;
		}
		$raw['layout']               = $settings['layout'];
		$raw['question_in_one_page'] = (int) $settings['questions_per_page'];

		$assessment = AssessmentSettings::validate(
			$quiz_id,
			array(
				'feedback_release' => $settings['feedback_mode'],
				'available_from'   => null === $settings['available_from'] ? '' : gmdate( 'Y-m-d H:i:s', (int) strtotime( $settings['available_from'] ) ),
				'available_until'  => null === $settings['available_until'] ? '' : gmdate( 'Y-m-d H:i:s', (int) strtotime( $settings['available_until'] ) ),
			)
		);
		if ( is_wp_error( $assessment ) ) {
			return self::invalid( 'ohmylms_quiz_invalid_availability', $assessment->get_error_message() );
		}

		update_post_meta( $quiz_id, self::META, $raw );
		update_post_meta( $quiz_id, AssessmentSettings::META, $assessment );

		return true;
	}

	/**
	 * Convert a stored UTC `Y-m-d H:i:s` value to ISO 8601, or null.
	 *
	 * @param mixed $value Stored value.
	 * @return string|null
	 */
	private static function to_iso( $value ) {
		$time = self::parse_time( is_string( $value ) && '' !== trim( $value ) ? $value : null );

		return $time ? gmdate( 'Y-m-d\TH:i:s\Z', $time ) : null;
	}

	/**
	 * Parse an API or stored date-time (UTC unless it carries an offset).
	 *
	 * @param mixed $value Value.
	 * @return int|null|false Timestamp, null when empty, false when invalid.
	 */
	private static function parse_time( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_string( $value ) ) {
			return false;
		}
		$time = strtotime( $value . ( preg_match( '/(Z|[+-]\d\d:?\d\d|UTC)$/i', $value ) ? '' : ' UTC' ) );

		return $time ? $time : false;
	}

	/**
	 * Whether a value is an integer inside a range (booleans and numeric strings do not count).
	 *
	 * @param mixed $value Value.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 * @return bool
	 */
	private static function is_int_between( $value, $min, $max ) {
		return is_int( $value ) && $value >= $min && $value <= $max;
	}

	/**
	 * Build a validation error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private static function invalid( $code, $message ) {
		return new WP_Error( $code, $message, array( 'status' => 422 ) );
	}
}
