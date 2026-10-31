<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Video settings of a lesson and the watched-percentage completion rule.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/**
 * Reads `_video_settings` (`platform`, `duration`, `completion_rule`, `watch_percent`, `poster_id`,
 * `autoplay`, `loop`, `controls`, `aspect_ratio` and the player's display keys). A video lesson whose rule is
 * `watch_percent` completes only when the learner watched that share of the video: the progress tracker
 * uses the lesson's percentage and the declared duration, and "Mark as complete" is refused until then.
 */
final class VideoCompletion {

	const PROVIDERS = array( 'self-hosted', 'youtube', 'vimeo', 'bunny' );

	const RULES = array( 'none', 'watch_percent' );

	const MAX_DURATION = 86400;

	/**
	 * Normalized video settings of a lesson.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return array{provider:string,duration:int,completion:string,watch_percent:int,poster_id:int,autoplay:bool,loop:bool,controls:bool,aspect_ratio:string}
	 */
	public static function settings( $lesson_id ) {
		$stored  = get_post_meta( (int) $lesson_id, '_video_settings', true );
		$stored  = is_array( $stored ) ? $stored : array();
		$percent = (int) ( $stored['watch_percent'] ?? 0 );
		$ratio   = (string) ( $stored['aspect_ratio'] ?? '' );
		return array(
			'provider'      => in_array( $stored['platform'] ?? '', self::PROVIDERS, true ) ? $stored['platform'] : 'self-hosted',
			'duration'      => max( 0, min( self::MAX_DURATION, (int) ( $stored['duration'] ?? 0 ) ) ),
			'completion'    => 'watch_percent' === ( $stored['completion_rule'] ?? '' ) ? 'watch_percent' : 'none',
			'watch_percent' => $percent >= 1 && $percent <= 100 ? $percent : 90,
			'poster_id'     => absint( $stored['poster_id'] ?? 0 ),
			'autoplay'      => ! empty( $stored['autoplay'] ),
			'loop'          => ! empty( $stored['loop'] ),
			'controls'      => ! array_key_exists( 'controls', $stored ) || ! empty( $stored['controls'] ),
			'aspect_ratio'  => preg_match( '#^\d{1,2}/\d{1,2}$#', $ratio ) ? $ratio : '16/9',
		);
	}

	/**
	 * Whether the lesson plays a video (an uploaded file or an external URL on a video lesson).
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return bool
	 */
	public static function has_video( $lesson_id ) {
		$type = (string) get_post_meta( (int) $lesson_id, '_type', true );
		return OHMYLMS_LESSON_CPT === get_post_type( (int) $lesson_id ) && ( absint( get_post_meta( (int) $lesson_id, '_video_id', true ) ) > 0 || ( 'video' === $type && '' !== (string) get_post_meta( (int) $lesson_id, '_external_url', true ) ) );
	}

	/**
	 * The completion rule in force: required only for a lesson that plays a video and asks for it.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return array{required:bool,percent:int,duration:int}
	 */
	public static function rule( $lesson_id ) {
		$settings = self::settings( $lesson_id );
		return array(
			'required' => 'watch_percent' === $settings['completion'] && self::has_video( $lesson_id ),
			'percent'  => $settings['watch_percent'],
			'duration' => $settings['duration'],
		);
	}

	/**
	 * Watched percentage that completes the video: the lesson's when its rule is on, else the site setting.
	 *
	 * @param int       $lesson_id Lesson ID.
	 * @param int|float $fallback  Site threshold.
	 * @return int|float
	 */
	public static function threshold( $lesson_id, $fallback ) {
		$rule = self::rule( $lesson_id );
		return $rule['required'] ? $rule['percent'] : $fallback;
	}

	/**
	 * Duration used to compute the watched share: the declared duration when it is longer than what the
	 * player reported, so a shortened duration sent by a client never completes the video early.
	 *
	 * @param int   $lesson_id Lesson ID.
	 * @param float $reported  Duration the player reported, in seconds.
	 * @return float
	 */
	public static function total( $lesson_id, $reported ) {
		return max( (float) $reported, (float) self::settings( $lesson_id )['duration'] );
	}

	/**
	 * Bound a reported watched duration by elapsed time when the lesson requires watching: a first report
	 * counts at most two minutes, and each later one adds at most twice the time since the previous report
	 * (fast playback) plus 30 seconds. A single forged request therefore cannot complete the video.
	 *
	 * @param int   $user_id   Learner.
	 * @param int   $lesson_id Lesson ID.
	 * @param float $watched   Reported watched seconds.
	 * @return float
	 */
	public static function bounded_watch( $user_id, $lesson_id, $watched ) {
		if ( ! self::rule( $lesson_id )['required'] ) {
			return (float) $watched;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh read; elapsed time is measured by the database clock that wrote last_updated.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT watched_duration, TIMESTAMPDIFF(SECOND, last_updated, NOW()) AS elapsed FROM %i WHERE user_id = %d AND lesson_id = %d', $wpdb->prefix . 'ohmylms_video_progress', (int) $user_id, (int) $lesson_id ) );
		$cap = $row ? (float) $row->watched_duration + 2 * max( 0, (int) $row->elapsed ) + 30 : 120.0;
		return min( (float) $watched, $cap );
	}

	/**
	 * Whether a learner may complete the lesson: no rule, the lesson is already completed, or the tracked
	 * watch reached the lesson's percentage.
	 *
	 * @param int $user_id   Learner.
	 * @param int $lesson_id Lesson ID.
	 * @return bool
	 */
	public static function satisfied( $user_id, $lesson_id ) {
		$rule = self::rule( $lesson_id );
		if ( ! $rule['required'] || \OhMyLMS\Assignment\Delivery::completed( $user_id, $lesson_id ) ) {
			return true;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh read of the learner's tracked watch.
		$watched = $wpdb->get_var( $wpdb->prepare( 'SELECT watch_percentage FROM %i WHERE user_id = %d AND lesson_id = %d', $wpdb->prefix . 'ohmylms_video_progress', (int) $user_id, (int) $lesson_id ) );
		return null !== $watched && (float) $watched >= $rule['percent'];
	}
}
