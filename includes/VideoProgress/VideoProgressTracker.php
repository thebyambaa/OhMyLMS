<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Video Progress Tracker
 *
 * Handles video watch progress tracking and persistence.
 *
 * @package OhMyLMS\VideoProgress
 * @since 1.1.0
 */

namespace OhMyLMS\VideoProgress;

defined( 'ABSPATH' ) || exit;

/**
 * Class VideoProgressTracker
 */
class VideoProgressTracker {

	/**
	 * Table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Minimum watch percentage required for completion
	 *
	 * @var float
	 */
	private $completion_threshold;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'ohmylms_video_progress';

		// Get threshold from settings, default to 90.
		$threshold = (int) get_option( 'ohmylms_video_completion_threshold', 90 );

		// Ensure threshold is within valid range.
		$threshold = max( 1, min( 100, $threshold ) );

		$this->completion_threshold = apply_filters( 'ohmylms_video_completion_threshold', $threshold );
	}

	/**
	 * Save video progress
	 *
	 * @param int   $user_id User ID.
	 * @param int   $lesson_id Lesson ID.
	 * @param int   $course_id Course ID.
	 * @param float $watched_duration Watched duration in seconds.
	 * @param float $total_duration Total video duration in seconds.
	 * @param float $last_position Last watched position in seconds.
	 *
	 * @return bool|int False on failure, inserted/updated record ID on success.
	 */
	public function save_progress( $user_id, $lesson_id, $course_id, $watched_duration, $total_duration, $last_position ) {
		global $wpdb;
		// Validate inputs.
		if ( ! $this->validate_inputs( $user_id, $lesson_id, $course_id ) ) {
			return false;
		}

		// A lesson's declared duration and watched-percentage rule take precedence over what the player reports.
		$total_duration   = \OhMyLMS\Learning\VideoCompletion::total( $lesson_id, $total_duration );
		$watched_duration = \OhMyLMS\Learning\VideoCompletion::bounded_watch( $user_id, $lesson_id, $watched_duration );
		$threshold        = \OhMyLMS\Learning\VideoCompletion::threshold( $lesson_id, $this->completion_threshold );

		// SECURITY: Validate and clamp duration values from client to prevent manipulation
		// Reject invalid total_duration (must be positive).
		if ( $total_duration <= 0 ) {
			return false;
		}

		// Clamp all duration values to valid ranges.
		$watched_duration = max( 0, min( $watched_duration, $total_duration ) );
		$last_position    = max( 0, min( $last_position, $total_duration ) );
		$total_duration   = max( 0, $total_duration );

		// Calculate watch percentage.
		$watch_percentage = $total_duration > 0 ? ( $watched_duration / $total_duration ) * 100 : 0;
		$watch_percentage = min( $watch_percentage, 100 ); // Cap at 100%.

		// Determine if video is completed.
		$is_completed   = $watch_percentage >= $threshold;
		$completed_date = $is_completed ? current_time( 'mysql' ) : null;

		// Check if record exists.
		$existing = $this->get_progress( $user_id, $lesson_id );

		$data = array(
			'watched_duration' => round( $watched_duration, 2 ),
			'total_duration'   => round( $total_duration, 2 ),
			'watch_percentage' => round( $watch_percentage, 2 ),
			'last_position'    => round( $last_position, 2 ),
			'is_completed'     => $is_completed ? 1 : 0,
		);

		// Only update completed_date if newly completed.
		if ( $is_completed && ( ! $existing || ! $existing->is_completed ) ) {
			$data['completed_date'] = $completed_date;
		}

		if ( $existing ) {
			// Update existing record
			// Build format array dynamically to match $data keys.
			$formats = array(
				'%f', // watched_duration.
				'%f', // total_duration.
				'%f', // watch_percentage.
				'%f', // last_position.
				'%d', // is_completed.
			);

			// Add format for completed_date if it exists in $data.
			if ( isset( $data['completed_date'] ) ) {
				$formats[] = '%s'; // completed_date is a datetime string.
			}

			$result = $wpdb->update(
				$this->table_name,
				$data,
				array(
					'user_id'   => $user_id,
					'lesson_id' => $lesson_id,
				),
				$formats,
				array( '%d', '%d' )
			);

			return false !== $result ? $existing->id : false;
		} else {
			// Insert new record.
			$data['user_id']   = $user_id;
			$data['lesson_id'] = $lesson_id;
			$data['course_id'] = $course_id;

			// Build format array dynamically to match $data keys.
			$formats = array(
				'%d', // user_id.
				'%d', // lesson_id.
				'%d', // course_id.
				'%f', // watched_duration.
				'%f', // total_duration.
				'%f', // watch_percentage.
				'%f', // last_position.
				'%d', // is_completed.
			);

			// Add format for completed_date if it exists in $data.
			if ( isset( $data['completed_date'] ) ) {
				$formats[] = '%s'; // completed_date is a datetime string.
			}

			$result = $wpdb->insert(
				$this->table_name,
				$data,
				$formats
			);

			return $result ? $wpdb->insert_id : false;
		}
	}

	/**
	 * Get video progress for a specific user and lesson
	 *
	 * @param int $user_id User ID.
	 * @param int $lesson_id Lesson ID.
	 *
	 * @return object|null Progress record or null.
	 */
	public function get_progress( $user_id, $lesson_id ) {
		global $wpdb;

		if ( ! $this->validate_inputs( $user_id, $lesson_id, 0, false ) ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT id, watched_duration, total_duration, watch_percentage, last_position, is_completed FROM %i WHERE user_id = %d AND lesson_id = %d', $this->table_name, $user_id, $lesson_id )
		);
	}

	/**
	 * Get all video progress for a user in a course
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 *
	 * @return array Array of progress records.
	 */
	public function get_course_progress( $user_id, $course_id ) {
		global $wpdb;

		if ( ! $this->validate_inputs( $user_id, 0, $course_id, false ) ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT id, watched_duration, total_duration, watch_percentage, last_position, is_completed FROM %i WHERE user_id = %d AND course_id = %d', $this->table_name, $user_id, $course_id )
		);
	}

	/**
	 * Check if user is enrolled in the course containing this lesson
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 *
	 * @return bool True if enrolled, false otherwise.
	 */
	public function is_user_enrolled( $user_id, $course_id ) {
		global $wpdb;

		$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';
		$count            = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d AND status = %s', $enrollment_table, $user_id, $course_id, 'enrolled' )
		);

		return $count > 0;
	}

	/**
	 * Mark lesson as completed based on video progress
	 *
	 * @param int $user_id User ID.
	 * @param int $lesson_id Lesson ID.
	 * @param int $course_id Course ID.
	 *
	 * @return bool True if lesson was marked complete, false otherwise.
	 */
	public function mark_lesson_complete( $user_id, $lesson_id, $course_id ) {
		$progress = $this->get_progress( $user_id, $lesson_id );

		if ( ! $progress || ! $progress->is_completed ) {
			return false;
		}

		// Use existing lesson completion logic.
		$student = new \OhMyLMS\Data\Student( $user_id );
		$result  = $student->complete_lesson( $lesson_id, $course_id );

		if ( $result ) {
			do_action( 'ohmylms_video_lesson_completed', $lesson_id, $course_id, $user_id, $progress );
		}

		return (bool) $result;
	}

	/**
	 * Reset video progress for a lesson
	 *
	 * @param int $user_id User ID.
	 * @param int $lesson_id Lesson ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function reset_progress( $user_id, $lesson_id ) {
		global $wpdb;

		return (bool) $wpdb->delete(
			$this->table_name,
			array(
				'user_id'   => $user_id,
				'lesson_id' => $lesson_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Get completion statistics for a course
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 *
	 * @return array Statistics array.
	 */
	public function get_completion_stats( $user_id, $course_id ) {
		global $wpdb;

		$total = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d', $this->table_name, $user_id, $course_id )
		);

		$completed = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d AND is_completed = 1', $this->table_name, $user_id, $course_id )
		);

		$completion_percentage = $total > 0 ? ( $completed / $total ) * 100 : 0;

		return array(
			'total_videos'          => (int) $total,
			'completed_videos'      => (int) $completed,
			'completion_percentage' => round( $completion_percentage, 2 ),
		);
	}

	/**
	 * Validate input parameters
	 *
	 * @param int  $user_id User ID.
	 * @param int  $lesson_id Lesson ID.
	 * @param int  $course_id Course ID.
	 * @param bool $check_enrollment Whether to check enrollment.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	private function validate_inputs( $user_id, $lesson_id, $course_id, $check_enrollment = true ) {
		// Validate user.
		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			return false;
		}

		// Validate lesson if provided.
		if ( $lesson_id > 0 && ! get_post( $lesson_id ) ) {
			return false;
		}

		// Validate course if provided.
		if ( $course_id > 0 && ! get_post( $course_id ) ) {
			return false;
		}

		// Check enrollment if required.
		if ( $check_enrollment && $course_id > 0 && ! $this->is_user_enrolled( $user_id, $course_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get completion threshold
	 *
	 * @return float Completion threshold percentage.
	 */
	public function get_completion_threshold() {
		return $this->completion_threshold;
	}
}
