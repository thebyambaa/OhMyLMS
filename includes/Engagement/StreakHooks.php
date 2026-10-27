<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Persistence events carry their owner; the logged-in grader is never the award target.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Engagement;

/** Persistence events carry their owner; the logged-in grader is never the award target. */
final class StreakHooks {
	/**
	 * Init for Streak hooks.
	 */
	public static function init() {
		add_action(
			'init',
			static function () {
				if ( StreakSettings::enabled() ) {
					StreakSchema::install();
				} },
			7
		);
		add_action( 'ohmylms_verified_lesson_completed', array( self::class, 'lesson' ), 10, 4 );
		add_action( 'ohmylms_attempt_submitted', array( self::class, 'quiz' ) );
		add_action( 'ohmylms_practice_completed', array( self::class, 'practice' ) );
		add_action(
			'ohmylms_course_completed',
			static function ( $user ) {
				( new \OhMyLMS\Hooks\EngagementHook() )->after_point_added( $user, 0 );
			},
			20
		);
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Lesson for Streak hooks.
	 *
	 * @param mixed $progress_id Progress identifier.
	 * @param mixed $lesson Lesson.
	 * @param mixed $course Course.
	 * @param mixed $user User.
	 */
	public static function lesson( $progress_id, $lesson, $course, $user ) {
		global $wpdb;
		if ( get_post_type( $lesson ) !== OHMYLMS_LESSON_CPT ) {
			return; }
		$verified = $wpdb->get_var( $wpdb->prepare( 'SELECT p.id FROM %i p JOIN %i e ON e.id=p.enrollment_id WHERE p.id=%d AND p.content_id=%d AND p.status=\'completed\' AND e.user_id=%d AND e.course_id=%d', $wpdb->prefix . 'ohmylms_user_progress', $wpdb->prefix . 'ohmylms_user_enrollment', $progress_id, $lesson, $user, $course ) );
		if ( ! $verified ) {
			return; }
		Streak::record( $user, 'lesson', $verified );
		// Lesson/course conditions must still work when point rewards are switched off.
		( new \OhMyLMS\Hooks\EngagementHook() )->after_point_added( $user, 0 );
	}

	/**
	 * Quiz for Streak hooks.
	 *
	 * @param mixed $event Event.
	 */
	public static function quiz( $event ) {
		if ( ! is_array( $event ) || empty( $event['attempt_id'] ) || ( $event['reason'] ?? '' ) === 'exit' ) {
			return; }
		global $wpdb;
		$id      = (int) $event['attempt_id'];
		$attempt = $wpdb->get_row( $wpdb->prepare( 'SELECT student_id,status FROM %i WHERE id=%d', $wpdb->prefix . 'ohmylms_quiz_attempts', $id ), ARRAY_A );
		if ( ! $attempt || ! in_array( $attempt['status'], array( 'completed', 'in-review' ), true ) ) {
			return; }
		if ( \OhMyLMS\Assessment\Schema::ready() && \OhMyLMS\Assessment\AttemptItems::is_versioned( $id ) ) {
			$answers = $wpdb->get_col( $wpdb->prepare( 'SELECT response FROM %i WHERE attempt_id=%d', \OhMyLMS\Assessment\Schema::table( 'attempt_items' ), $id ) );
			$answers = array_map(
				static function ( $answer ) {
					return json_decode( $answer ?? 'null', true );
				},
				$answers
			);
		} else {
			$answers = $wpdb->get_col( $wpdb->prepare( 'SELECT given_answer FROM %i WHERE quiz_attempt_id=%d', $wpdb->prefix . 'ohmylms_quiz_attempts_answers', $id ) );
			$answers = array_map( 'maybe_unserialize', $answers );
		}
		if ( StreakCalendar::meaningful( $answers ) ) {
			Streak::record( (int) $attempt['student_id'], 'quiz', $id ); }
	}

	/**
	 * Practice for Streak hooks.
	 *
	 * @param mixed $uuid Uuid.
	 */
	public static function practice( $uuid ) {
		if ( ! \OhMyLMS\Assessment\Schema::ready() ) {
			return; }
		$session = \OhMyLMS\Practice\Sessions::get( $uuid );
		if ( ! $session || 'skill' !== $session['mode'] || 'complete' !== $session['status'] || ! (int) $session['student_id'] ) {
			return; }
		$answered = array_filter(
			\OhMyLMS\Practice\Sessions::items( $session['id'] ),
			static function ( $item ) {
				return null !== $item['answered_at'] && StreakCalendar::meaningful( $item['response'] );
			}
		);
		if ( count( $answered ) >= StreakSettings::get()['practice_minimum'] ) {
			Streak::record( (int) $session['student_id'], 'practice', $session['uuid'] ); }
	}

	/**
	 * Routes for Streak hooks.
	 */
	public static function routes() {
		register_rest_route(
			'ohmylms/v1',
			'/engagement/streak',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => static function () {
						return is_user_logged_in(); },
					'callback'            => static function ( $request ) {
						return rest_ensure_response( Streak::snapshot( get_current_user_id(), $request->get_param( 'history_days' ) ?: 7 ) ); },
				),
				array(
					'methods'             => 'PUT',
					'permission_callback' => static function () {
						return is_user_logged_in(); },
					'callback'            => static function ( $request ) {
						return rest_ensure_response( Streak::set_timezone( get_current_user_id(), $request->get_param( 'timezone' ) ) ); },
				),
			)
		);
	}
}
