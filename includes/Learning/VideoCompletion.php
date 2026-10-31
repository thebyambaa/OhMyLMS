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

	/** Most watched spans accepted in one report (assets/interactivity/video/progress.js MAX_INTERVALS). */
	const MAX_INTERVALS = 500;

	/** Gap in seconds under which two watched spans are joined (progress.js JOIN_GAP). */
	const JOIN_GAP = 0.25;

	/** User meta prefix of the spans a learner watched of a lesson's video (`<prefix><lesson ID>`). */
	const SEEN_META = '_ohmylms_video_seen_';

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
	 * Whether the lesson's video can be tracked: an uploaded file, or an external URL the player has an
	 * adapter for (YouTube, Vimeo, Bunny, Wistia, a video file). Any other iframe embed reports nothing,
	 * so requiring a watched share there would lock the learner out.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return bool
	 */
	public static function trackable( $lesson_id ) {
		if ( absint( get_post_meta( (int) $lesson_id, '_video_id', true ) ) > 0 ) {
			return true;
		}
		$custom = 'yes' === get_option( 'ohmylms_use_custom_video_player', 'yes' );
		return VideoSource::trackable( (string) get_post_meta( (int) $lesson_id, '_external_url', true ), $custom );
	}

	/**
	 * The completion rule in force: required only for a lesson that plays a trackable video and asks for it.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return array{required:bool,percent:int,duration:int}
	 */
	public static function rule( $lesson_id ) {
		$settings = self::settings( $lesson_id );
		return array(
			'required' => 'watch_percent' === $settings['completion'] && self::has_video( $lesson_id ) && self::trackable( $lesson_id ),
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
	 * Duration used to compute the watched share: the longest of what the player reported, the declared
	 * duration and the length WordPress read from an uploaded file, so a shortened duration sent by a client
	 * never completes the video early. When the lesson requires watching, the duration stored by an
	 * earlier report is a floor too (a later report cannot shrink it).
	 *
	 * @param int   $lesson_id Lesson ID.
	 * @param float $reported  Duration the player reported, in seconds.
	 * @param float $stored    Duration stored by the learner's previous report.
	 * @return float
	 */
	public static function total( $lesson_id, $reported, $stored = 0.0 ) {
		$length = 0;
		$file   = absint( get_post_meta( (int) $lesson_id, '_video_id', true ) );
		if ( $file ) {
			$meta   = wp_get_attachment_metadata( $file );
			$length = is_array( $meta ) ? min( self::MAX_DURATION, absint( $meta['length'] ?? 0 ) ) : 0;
		}
		$floor = self::rule( $lesson_id )['required'] ? (float) $stored : 0.0;
		return max( (float) $reported, (float) self::settings( $lesson_id )['duration'], (float) $length, $floor );
	}

	/**
	 * Watched spans posted by the player (`segments`, JSON `[[start, end], …]`).
	 *
	 * @param mixed $raw Posted value.
	 * @return array<int, array{0: float, 1: float}>|null Merged spans; null when none were posted or the value is not a list of pairs.
	 */
	public static function parse_intervals( $raw ) {
		if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > 40000 ) {
			return null;
		}
		$list = json_decode( $raw, true );
		if ( ! is_array( $list ) || count( $list ) > self::MAX_INTERVALS ) {
			return null;
		}
		foreach ( $list as $span ) {
			if ( ! is_array( $span ) || 2 !== count( $span ) || ! is_numeric( $span[0] ?? null ) || ! is_numeric( $span[1] ?? null ) ) {
				return null;
			}
		}
		return self::merge_intervals( $list );
	}

	/**
	 * Sorted, disjoint spans: invalid ones dropped, overlapping or nearly touching ones joined.
	 *
	 * @param array $intervals Spans (`[start, end]` pairs).
	 * @return array<int, array{0: float, 1: float}>
	 */
	public static function merge_intervals( array $intervals ) {
		$spans = array();
		foreach ( $intervals as $span ) {
			if ( ! is_array( $span ) || ! isset( $span[0], $span[1] ) || ! is_numeric( $span[0] ) || ! is_numeric( $span[1] ) ) {
				continue;
			}
			$start = max( 0.0, (float) $span[0] );
			$end   = min( (float) self::MAX_DURATION, (float) $span[1] );
			if ( is_finite( $start ) && is_finite( $end ) && $end > $start ) {
				$spans[] = array( $start, $end );
			}
		}
		usort(
			$spans,
			static function ( $a, $b ) {
				$order = $a[0] <=> $b[0];
				return 0 !== $order ? $order : $a[1] <=> $b[1];
			}
		);
		$merged = array();
		foreach ( $spans as $span ) {
			$last = count( $merged ) - 1;
			if ( $last >= 0 && $span[0] <= $merged[ $last ][1] + self::JOIN_GAP ) {
				$merged[ $last ][1] = max( $merged[ $last ][1], $span[1] );
			} else {
				$merged[] = $span;
			}
		}
		return $merged;
	}

	/**
	 * Spans limited to `[0, total]`.
	 *
	 * @param array $intervals Merged spans.
	 * @param float $total     Duration.
	 * @return array<int, array{0: float, 1: float}>
	 */
	public static function clamp_intervals( array $intervals, $total ) {
		$total = (float) $total;
		if ( $total <= 0 ) {
			return self::merge_intervals( $intervals );
		}
		return self::merge_intervals(
			array_map(
				static function ( $span ) use ( $total ) {
					return array( min( (float) $span[0], $total ), min( (float) $span[1], $total ) );
				},
				$intervals
			)
		);
	}

	/**
	 * Seconds covered by merged spans.
	 *
	 * @param array $intervals Merged spans.
	 * @return float
	 */
	public static function measure( array $intervals ) {
		$total = 0.0;
		foreach ( $intervals as $span ) {
			$total += (float) $span[1] - (float) $span[0];
		}
		return $total;
	}

	/**
	 * Spans a learner watched of a lesson's video, as stored by earlier reports.
	 *
	 * @param int $user_id   Learner.
	 * @param int $lesson_id Lesson ID.
	 * @return array<int, array{0: float, 1: float}>
	 */
	public static function seen( $user_id, $lesson_id ) {
		$stored = get_user_meta( (int) $user_id, self::SEEN_META . (int) $lesson_id, true );
		return is_array( $stored ) ? self::merge_intervals( $stored ) : array();
	}

	/**
	 * Store the spans a learner watched.
	 *
	 * @param int   $user_id   Learner.
	 * @param int   $lesson_id Lesson ID.
	 * @param array $intervals Merged spans.
	 */
	public static function remember( $user_id, $lesson_id, array $intervals ) {
		$rounded = array_map(
			static function ( $span ) {
				return array( round( (float) $span[0], 2 ), round( (float) $span[1], 2 ) );
			},
			array_slice( self::merge_intervals( $intervals ), 0, self::MAX_INTERVALS )
		);
		update_user_meta( (int) $user_id, self::SEEN_META . (int) $lesson_id, $rounded );
	}

	/**
	 * Forget the spans (progress reset).
	 *
	 * @param int $user_id   Learner.
	 * @param int $lesson_id Lesson ID.
	 */
	public static function forget( $user_id, $lesson_id ) {
		delete_user_meta( (int) $user_id, self::SEEN_META . (int) $lesson_id );
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
