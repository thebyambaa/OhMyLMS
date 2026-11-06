<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Real learner data for the Khan-style profile home. Missing sources stay empty; nothing here is invented.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Engagement\Badge;
use OhMyLMS\Engagement\Level;
use OhMyLMS\Engagement\Point;
use OhMyLMS\Engagement\Rules;
use OhMyLMS\Engagement\Streak;
use OhMyLMS\Engagement\StreakSettings;
use OhMyLMS\Engagement\Xp;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Performance\Snapshot;

defined( 'ABSPATH' ) || exit;

/**
 * Assembles the profile home from mastery, points, streaks, badges, courses and school assignments.
 */
final class ProfileHome {

	const SHOWCASE = 'ohmylms_badge_showcase';
	const TABS     = array( 'progress', 'courses', 'assignments', 'badges', 'settings' );

	/** Register the showcase save handler. */
	public static function init() {
		add_action( 'admin_post_ohmylms_badge_showcase', array( __CLASS__, 'save_showcase' ) );
	}

	/**
	 * Profile tab from the query string, defaulting to Progress.
	 *
	 * @return string
	 */
	public static function tab() {
		$tab = get_query_var( 'profile_tab' );
		$tab = is_string( $tab ) ? sanitize_key( $tab ) : '';
		return in_array( $tab, self::TABS, true ) ? $tab : 'progress';
	}

	/**
	 * Everything the profile template prints for one learner.
	 *
	 * @param int $user_id Learner.
	 * @return array
	 */
	public static function gather( $user_id ) {
		$user_id = absint( $user_id );
		$monday  = self::week_start();
		return array(
			'minutes'     => array(
				'available' => false,
				'note'      => __( 'Minutes learned are not recorded yet.', 'ohmylms' ),
			),
			'skills'      => self::skills( $user_id, $monday ),
			'exercises'   => self::exercises( $user_id, $monday ),
			'streak'      => self::streak( $user_id ),
			'points'      => self::points( $user_id ),
			'level'       => self::level( $user_id ),
			'courses'     => self::courses( $user_id ),
			'assignments' => self::assignments( $user_id ),
			'badges'      => self::badges( $user_id ),
		);
	}

	/**
	 * Save up to five earned badges for the showcase.
	 */
	public static function save_showcase() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( Hub::url() );
			exit;
		}
		check_admin_referer( 'ohmylms_badge_showcase' );
		$user   = get_current_user_id();
		$earned = array_column( self::earned_badges( $user ), 'slug' );
		$chosen = isset( $_POST['badges'] ) && is_array( $_POST['badges'] ) ? map_deep( wp_unslash( $_POST['badges'] ), 'sanitize_text_field' ) : array();
		$clean  = array();
		foreach ( $chosen as $slug ) {
			$slug = sanitize_key( is_string( $slug ) ? $slug : '' );
			if ( '' !== $slug && in_array( $slug, $earned, true ) && ! in_array( $slug, $clean, true ) && count( $clean ) < 5 ) {
				$clean[] = $slug;
			}
		}
		update_user_meta( $user, self::SHOWCASE, $clean );
		wp_safe_redirect( add_query_arg( 'profile_tab', 'badges', Hub::url() ) );
		exit;
	}

	/**
	 * Monday 00:00 in the site timezone.
	 *
	 * @return int Unix timestamp.
	 */
	private static function week_start() {
		$now = current_datetime();
		$now = $now->setTime( 0, 0, 0 )->modify( '-' . ( (int) $now->format( 'N' ) - 1 ) . ' days' );
		return $now->getTimestamp();
	}

	/**
	 * Proficient and mastered counts, plus how many of those were last practised this week.
	 *
	 * @param int $user_id Learner.
	 * @param int $monday  Week start timestamp.
	 * @return array
	 */
	private static function skills( $user_id, $monday ) {
		$counts = array(
			'available'  => true,
			'proficient' => 0,
			'mastered'   => 0,
			'week'       => 0,
			'total'      => 0,
		);
		try {
			$rows = Snapshot::skills( $user_id );
		} catch ( \Throwable $error ) {
			$counts['available'] = false;
			$counts['note']      = __( 'Skill mastery could not be read.', 'ohmylms' );
			return $counts;
		}
		foreach ( $rows as $row ) {
			$level = (string) ( $row['level'] ?? '' );
			if ( ! in_array( $level, array( 'proficient', 'mastered' ), true ) ) {
				continue;
			}
			++$counts[ $level ];
			++$counts['total'];
			$at = isset( $row['last_evidence_at'] ) ? strtotime( (string) $row['last_evidence_at'] ) : false;
			if ( $at && $at >= $monday ) {
				++$counts['week'];
			}
		}
		return $counts;
	}

	/**
	 * Finished quiz attempts plus completed practice sessions.
	 *
	 * @param int $user_id Learner.
	 * @param int $monday  Week start timestamp.
	 * @return array
	 */
	private static function exercises( $user_id, $monday ) {
		global $wpdb;
		$out   = array(
			'available' => false,
			'total'     => 0,
			'week'      => 0,
			'note'      => __( 'Exercise history is not available.', 'ohmylms' ),
		);
		$since = gmdate( 'Y-m-d H:i:s', $monday );
		$quiz  = $wpdb->prefix . 'ohmylms_quiz_attempts';
		$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE student_id=%d AND status<>'in-progress'", $quiz, $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The learner's own attempt count, read fresh for the profile.
		if ( null !== $total && '' === $wpdb->last_error ) {
			$out['available'] = true;
			$out['total']    += (int) $total;
			$week             = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE student_id=%d AND status<>'in-progress' AND end_date>=%s", $quiz, $user_id, $since ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The learner's own attempts this week.
			$out['week']     += (int) $week;
			$out['note']      = '';
		}
		if ( class_exists( '\OhMyLMS\Assessment\Schema' ) && \OhMyLMS\Assessment\Schema::ready() ) {
			$table            = \OhMyLMS\Assessment\Schema::table( 'practice_sessions' );
			$practice         = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE student_id=%d AND status='complete'", $table, $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The learner's own completed practice.
			$out['available'] = true;
			$out['total']    += (int) $practice;
			$week             = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE student_id=%d AND status='complete' AND completed_at>=%s", $table, $user_id, $since ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Completed practice this week.
			$out['week']     += (int) $week;
			$out['note']      = '';
		}
		return $out;
	}

	/**
	 * Current streak when the streak feature is on.
	 *
	 * @param int $user_id Learner.
	 * @return array
	 */
	private static function streak( $user_id ) {
		if ( ! StreakSettings::enabled() ) {
			return array(
				'available' => false,
				'days'      => 0,
				'note'      => __( 'Streaks are turned off.', 'ohmylms' ),
			);
		}
		try {
			$snapshot = Streak::snapshot( $user_id, 7 );
		} catch ( \Throwable $error ) {
			$snapshot = false;
		}
		if ( ! is_array( $snapshot ) ) {
			return array(
				'available' => false,
				'days'      => 0,
				'note'      => __( 'No streak has been recorded yet.', 'ohmylms' ),
			);
		}
		return array(
			'available' => true,
			'days'      => (int) ( $snapshot['current_streak'] ?? 0 ),
			'note'      => '',
		);
	}

	/**
	 * Spendable points when the points feature is on.
	 *
	 * @param int $user_id Learner.
	 * @return array
	 */
	private static function points( $user_id ) {
		if ( ! Rules::enabled( 'point' ) ) {
			return array(
				'available' => false,
				'total'     => 0,
				'note'      => __( 'Energy points are turned off.', 'ohmylms' ),
			);
		}
		return array(
			'available' => true,
			'total'     => (int) Point::get_total_points( $user_id ),
			'note'      => '',
		);
	}

	/**
	 * Level progress from the gamification levels, or XP when only XP is on.
	 *
	 * @param int $user_id Learner.
	 * @return array
	 */
	private static function level( $user_id ) {
		$empty = array(
			'available' => false,
			'mode'      => '',
			'name'      => '',
			'current'   => 0,
			'goal'      => 0,
			'percent'   => 0,
			'note'      => __( 'Levels are turned off.', 'ohmylms' ),
		);
		if ( Level::maybe_enable() ) {
			$next    = Level::get_next_level_of_a_user( $user_id );
			$current = Level::get_current_level_of_a_user( $user_id );
			$points  = (int) Point::get_total_points( $user_id );
			$goal    = is_array( $next ) ? (int) ( $next['rules'][0]['required'] ?? $points ) : max( 1, $points );
			$name    = is_array( $current ) ? (string) $current['level_name'] : __( 'No level yet', 'ohmylms' );
			return array(
				'available' => true,
				'mode'      => 'level',
				'name'      => $name,
				'current'   => $points,
				'goal'      => $goal,
				'percent'   => is_array( $next ) ? (int) $next['progress_percentage'] : 100,
				'note'      => '',
			);
		}
		if ( Xp::enabled() ) {
			try {
				$summary = Xp::summary( $user_id, 7 );
			} catch ( \Throwable $error ) {
				return $empty;
			}
			$goal  = max( 1, (int) ( $summary['goal'] ?? 1 ) );
			$today = (int) ( $summary['today'] ?? 0 );
			return array(
				'available' => true,
				'mode'      => 'xp',
				'name'      => __( 'XP today', 'ohmylms' ),
				'current'   => $today,
				'goal'      => $goal,
				'total'     => (int) ( $summary['total'] ?? 0 ),
				'percent'   => (int) min( 100, floor( 100 * $today / $goal ) ),
				'note'      => '',
			);
		}
		return $empty;
	}

	/**
	 * Enrolled courses with a mastery split when the course has skills.
	 *
	 * @param int $user_id Learner.
	 * @return array<int,array<string,mixed>>
	 */
	private static function courses( $user_id ) {
		if ( ! function_exists( 'ohmylms_get_student' ) && ! class_exists( '\OhMyLMS\Data\Student' ) ) {
			return array();
		}
		$student = class_exists( '\OhMyLMS\Data\Student' ) ? new \OhMyLMS\Data\Student( $user_id ) : null;
		if ( ! $student ) {
			return array();
		}
		$courses = $student->get_enrolled_courses();
		$out     = array();
		foreach ( array_slice( (array) $courses, 0, 12 ) as $course ) {
			if ( ! is_object( $course ) || ! method_exists( $course, 'get_id' ) ) {
				continue;
			}
			$id       = (int) $course->get_id();
			$segments = self::segments( $user_id, $id );
			$percent  = (float) $student->get_course_progress_percentage( $id );
			if ( $segments ) {
				$percent = $segments['proficient'] + $segments['mastered'];
			}
			$out[] = array(
				'id'       => $id,
				'name'     => method_exists( $course, 'get_name' ) ? (string) $course->get_name() : get_the_title( $id ),
				'url'      => (string) $student->get_course_resume_url( $id ),
				'percent'  => max( 0, min( 100, $percent ) ),
				'segments' => $segments,
			);
		}
		return $out;
	}

	/**
	 * Share of a course's skills at each mastery level, or null when the course has no skills.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id Course.
	 * @return array<string,float>|null Percents of all skills, including not-started as the empty part of the bar.
	 */
	private static function segments( $user_id, $course_id ) {
		try {
			$program = CourseProgram::current( $course_id );
		} catch ( \Throwable $error ) {
			return null;
		}
		$terms = array();
		foreach ( (array) ( is_array( $program ) ? ( $program['outcomes'] ?? array() ) : array() ) as $outcome ) {
			$term = absint( is_array( $outcome ) ? ( $outcome['term_id'] ?? 0 ) : 0 );
			if ( $term ) {
				$terms[] = $term;
			}
		}
		$terms = array_values( array_unique( $terms ) );
		if ( ! $terms ) {
			return null;
		}
		$counts = array(
			'attempted'   => 0,
			'familiar'    => 0,
			'proficient'  => 0,
			'mastered'    => 0,
			'not-started' => 0,
		);
		try {
			$states = Snapshot::student( $user_id, $terms );
		} catch ( \Throwable $error ) {
			return null;
		}
		foreach ( $terms as $term ) {
			$key = (string) ( $states[ $term ]['key'] ?? 'not-started' );
			if ( ! isset( $counts[ $key ] ) ) {
				$key = 'not-started';
			}
			++$counts[ $key ];
		}
		$total = count( $terms );
		$share = array();
		foreach ( array( 'attempted', 'familiar', 'proficient', 'mastered' ) as $level ) {
			$share[ $level ] = round( 100 * $counts[ $level ] / $total, 1 );
		}
		return $share;
	}

	/**
	 * School assignments for this learner. Empty when schools or assignments are not set up.
	 *
	 * @param int $user_id Learner.
	 * @return array{available:bool,items:array<int,array<string,mixed>>,note:string}
	 */
	private static function assignments( $user_id ) {
		$empty = array(
			'available' => false,
			'items'     => array(),
			'note'      => __( 'No assignments yet.', 'ohmylms' ),
		);
		if ( \OhMyLMS\Schools\Schema::VERSION !== get_option( 'ohmylms_school_schema' ) ) {
			$empty['note'] = __( 'Class assignments are not set up on this site.', 'ohmylms' );
			return $empty;
		}
		try {
			$rows = \OhMyLMS\Schools\Service::assignments( 0, $user_id );
		} catch ( \Throwable $error ) {
			return $empty;
		}
		$items = array();
		$now   = time();
		foreach ( (array) $rows as $row ) {
			if ( ! empty( $row['completed_at'] ) ) {
				continue;
			}
			$due     = isset( $row['due_at'] ) ? strtotime( (string) $row['due_at'] ) : false;
			$soon    = $due && $due <= $now + 2 * DAY_IN_SECONDS;
			$items[] = array(
				'title'   => isset( $row['title'] ) ? (string) $row['title'] : '',
				'context' => isset( $row['class_name'] ) ? (string) $row['class_name'] : '',
				'url'     => isset( $row['url'] ) ? (string) $row['url'] : '',
				'due'     => $due ? date_i18n( get_option( 'date_format' ), $due ) : '',
				'soon'    => $soon,
			);
		}
		return array(
			'available' => true,
			'items'     => $items,
			'note'      => $items ? '' : __( 'No assignments yet.', 'ohmylms' ),
		);
	}

	/**
	 * Earned and locked badges, and the learner's showcase (at most five).
	 *
	 * @param int $user_id Learner.
	 * @return array
	 */
	private static function badges( $user_id ) {
		if ( ! Badge::maybe_enable() ) {
			return array(
				'available' => false,
				'items'     => array(),
				'showcase'  => array(),
				'note'      => __( 'Badges are turned off.', 'ohmylms' ),
			);
		}
		$all    = Badge::get_badges( true );
		$earned = self::earned_badges( $user_id );
		$have   = array();
		foreach ( $earned as $badge ) {
			if ( ! empty( $badge['slug'] ) ) {
				$have[ (string) $badge['slug'] ] = true;
			}
		}
		$items = array();
		foreach ( (array) $all as $badge ) {
			$slug = isset( $badge['slug'] ) ? (string) $badge['slug'] : '';
			if ( '' === $slug ) {
				continue;
			}
			$items[] = array(
				'slug'   => $slug,
				'name'   => isset( $badge['name'] ) ? (string) $badge['name'] : $slug,
				'image'  => isset( $badge['image'] ) ? (string) $badge['image'] : '',
				'earned' => isset( $have[ $slug ] ),
			);
		}
		$saved = get_user_meta( $user_id, self::SHOWCASE, true );
		$slugs = array();
		if ( is_array( $saved ) ) {
			foreach ( $saved as $slug ) {
				$slug = sanitize_key( is_string( $slug ) ? $slug : '' );
				if ( isset( $have[ $slug ] ) && count( $slugs ) < 5 ) {
					$slugs[] = $slug;
				}
			}
		} else {
			foreach ( $items as $item ) {
				if ( $item['earned'] && count( $slugs ) < 5 ) {
					$slugs[] = $item['slug'];
				}
			}
		}
		$by = array();
		foreach ( $items as $item ) {
			$by[ $item['slug'] ] = $item;
		}
		$showcase = array();
		foreach ( $slugs as $slug ) {
			if ( isset( $by[ $slug ] ) ) {
				$showcase[] = $by[ $slug ];
			}
		}
		return array(
			'available' => true,
			'items'     => $items,
			'showcase'  => $showcase,
			'note'      => $items ? '' : __( 'No badges have been created yet.', 'ohmylms' ),
		);
	}

	/**
	 * Badges the learner holds.
	 *
	 * @param int $user_id Learner.
	 * @return array<int,array<string,mixed>>
	 */
	private static function earned_badges( $user_id ) {
		if ( ! Badge::maybe_enable() ) {
			return array();
		}
		$earned = Badge::get_all_badges_of_a_user( $user_id );
		return is_array( $earned ) ? $earned : array();
	}
}
