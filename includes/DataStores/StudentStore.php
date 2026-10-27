<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\DataStores;

use OhMyLMS\Abstracts\Data;
use OhMyLMS\Abstracts\DataStore;
use OhMyLMS\Data\Student;

/**
 * Plugin student store implementation.
 */
class StudentStore extends DataStore {
	/**
	 * Clear learning cache for Student store.
	 *
	 * @param mixed $student_id Student identifier.
	 * @param mixed $course_id Course identifier.
	 */
	public static function clear_learning_cache( $student_id, $course_id ) {
		$key = $student_id . '_' . $course_id;
		unset( self::$course_completed_cache[ $key ], self::$course_in_progress_cache[ $key ], self::$completed_content_count_cache[ $key ] );
		self::$maybe_completed_cache = array();
	}

	/**
	 * Cache for enrollment checks to prevent duplicate queries.
	 *
	 * @var array
	 */
	private static $enrollment_cache = array();

	/**
	 * Cache for course in progress checks.
	 *
	 * @var array
	 */
	private static $course_in_progress_cache = array();

	/**
	 * Cache for course completed checks.
	 *
	 * @var array
	 */
	private static $course_completed_cache = array();

	/**
	 * Cache for enrolled memberships.
	 *
	 * @var array
	 */
	private static $enrolled_memberships_cache = array();

	/**
	 * Cache for completed content count.
	 *
	 * @var array
	 */
	private static $completed_content_count_cache = array();

	/**
	 * Cache for maybe completed checks.
	 *
	 * @var array
	 */
	private static $maybe_completed_cache = array();

	/**
	 * Create new record
	 *
	 * @param mixed $student Student.
	 * @return mixed
	 * @since 1.0.0
	 */
	public function create( &$student ) {
		$student_id = ohmylms_create_new_student( $student->get_email(), $student->get_username(), $student->get_password() );
		$student->set_id( $student_id );

		$this->update_user_meta( $student );
	}

	/**
	 * Read new record
	 *
	 * @param mixed $student Data.
	 * @return mixed
	 * @since 1.0.0
	 */
	public function read( &$student ) {
		$student_id  = $student->get_id();
		$user_object = $student_id ? get_user_by( 'id', $student->get_id() ) : false;
		$student->set_props(
			array(
				'email'         => $user_object->user_email,
				'first_name'    => get_user_meta( $student_id, 'first_name', true ),
				'last_name'     => get_user_meta( $student_id, 'last_name', true ),
				'address'       => get_user_meta( $student_id, 'address', true ),
				'country'       => get_user_meta( $student_id, 'country', true ),
				'city'          => get_user_meta( $student_id, 'city', true ),
				'postcode'      => get_user_meta( $student_id, 'postcode', true ),
				'state'         => get_user_meta( $student_id, 'state', true ),
				'phone'         => get_user_meta( $student_id, 'billing_phone', true ),
				'whatsapp'      => get_user_meta( $student_id, 'whatsapp', true ),
				'timezone'      => get_user_meta( $student_id, 'timezone', true ),
				'bio'           => get_user_meta( $student_id, 'bio', true ),
				'interest'      => get_user_meta( $student_id, 'interest', true ),
				'skills'        => get_user_meta( $student_id, 'skills', true ),
				'social_links'  => get_user_meta( $student_id, 'social_links', true ),
				'cover_image'   => get_user_meta( $student_id, 'cover_image', true ),
				'profile_image' => get_user_meta( $student_id, 'profile_image', true ),
				'display_name'  => $user_object->display_name,
			)
		);
		$this->get_user_meta( $student );
	}

	/**
	 * Get user meta data for the given student.
	 *
	 * @param mixed $student Student.
	 * @return void
	 * @since 1.0.0
	 */
	public function get_user_meta( $student ) {
		$meta_key_to_props = array(
			'_notification_chapter'            => 'notification_chapter',
			'_notification_direct_messages'    => 'notification_direct_messages',
			'_notification_new_course_content' => 'notification_new_course_content',
			'_notification_comments_replies'   => 'notification_comments_replies',
		);

		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			$student->{"set_$prop"}( get_user_meta( $student->get_id(), $meta_key, true ) );
		}
	}

	/**
	 * Update new record
	 *
	 * @param mixed $student Student.
	 * @return mixed
	 * @since 1.0.0
	 */
	public function update( &$student ) {
		wp_update_user(
			array(
				'ID'         => $student->get_id(),
				'user_email' => $student->get_email(),
			)
		);
		$this->update_user_meta( $student );
	}

	/**
	 * Delete new record
	 *
	 * @param mixed $student Student.
	 * @param array $args Operation arguments.
	 * @return mixed
	 * @since 1.0.0
	 */
	public function delete( &$student, $args = array() ) {
		if ( ! $student->get_id() ) {
			return;
		}
		$student_id = $student->get_id();
		wp_delete_user( $student_id );
	}

	/**
	 * Update user meta data for the given student.
	 *
	 * @param Student $student The student object containing user meta data.
	 */
	protected function update_user_meta( $student ) {
		$meta_key_to_props = array(
			'first_name'                       => 'first_name',
			'last_name'                        => 'last_name',
			'bio'                              => 'bio',
			'address'                          => 'address',
			'country'                          => 'country',
			'city'                             => 'city',
			'postcode'                         => 'postcode',
			'state'                            => 'state',
			'billing_phone'                    => 'phone',
			'whatsapp'                         => 'whatsapp',
			'timezone'                         => 'timezone',
			'interest'                         => 'interest',
			'skills'                           => 'skills',
			'social_links'                     => 'social_links',
			'cover_image'                      => 'cover_image',
			'profile_image'                    => 'profile_image',
			'_notification_chapter'            => 'notification_chapter',
			'_notification_direct_messages'    => 'notification_direct_messages',
			'_notification_new_course_content' => 'notification_new_course_content',
			'_notification_comments_replies'   => 'notification_comments_replies',
		);
		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			update_user_meta( $student->get_id(), $meta_key, $student->{"get_$prop"}( 'edit' ) );
		}
	}

	/**
	 * Get course count for Student store.
	 *
	 * @param mixed $student Student.
	 */
	public function get_course_count( $student ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		return $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', $table_name, $student->get_id() ) );
	}

	/**
	 * Get enrolled course count for Student store.
	 *
	 * @param mixed $student Student.
	 */
	public function get_enrolled_course_count( $student ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		return $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT course_id) FROM %i WHERE user_id = %d AND status = %s', $table_name, $student->get_id(), 'enrolled' ) );
	}

	/**
	 * Get memebership count for Student store.
	 *
	 * @param mixed $student Student.
	 */
	public function get_memebership_count( $student ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		return $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT course_id) FROM %i WHERE user_id = %d AND status = %s', $table_name, $student->get_id(), 'enrolled' ) );
	}

	/**
	 * Get completed course count for Student store.
	 *
	 * @param mixed $student Student.
	 */
	public function get_completed_course_count( $student ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		return $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND progress = %s', $table_name, $student->get_id(), 'completed' ) );
	}
	/**
	 * Get progress course count for Student store.
	 *
	 * @param mixed $student Student.
	 */
	public function get_progress_course_count( $student ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		return $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT course_id) FROM %i WHERE user_id = %d AND status = %s AND progress = %s', $table_name, $student->get_id(), 'enrolled', 'running' ) );
	}

	/**
	 * Get courses for Student store.
	 *
	 * @param mixed $student Student.
	 */
	public function get_courses( $student ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		$post_table = $wpdb->prefix . 'posts';
		$query      = $wpdb->prepare(
			'
			SELECT DISTINCT e.course_id
			FROM %i e
			JOIN %i p ON e.course_id = p.ID
			WHERE e.user_id = %d AND e.status = %s AND p.post_type = %s
		',
			$table_name,
			$post_table,
			$student->get_id(),
			'enrolled',
			'ohmylms-course'
		);

		$course_ids = $wpdb->get_results( $query, ARRAY_A );

		$courses = array();
		foreach ( $course_ids as $course_id ) {
			$courses[] = ohmylms_get_course( $course_id['course_id'] );
		}

		return $courses;
	}


	/**
	 * Get memberships for Student store.
	 *
	 * @param mixed $student Student.
	 */
	public function get_memberships( $student ) {
		if ( ! ohmylms_is_pro() ) {
			return array();
		}

		global $wpdb;
		$table_name     = $wpdb->prefix . 'ohmylms_user_membership';
		$post_table     = $wpdb->prefix . 'posts';
		$query          = $wpdb->prepare(
			'
			SELECT e.membership_id
			FROM %i e
			JOIN %i p ON e.membership_id = p.ID
			WHERE e.user_id = %d AND p.post_type = %s
		',
			$table_name,
			$post_table,
			$student->get_id(),
			'ohmylms-membership'
		);
		$membership_ids = $wpdb->get_results( $query, ARRAY_A );

		$memberships = array();
		foreach ( $membership_ids as $membership_id ) {
			$memberships[] = ohmylms_get_course( $membership_id['membership_id'] );
		}

		return $memberships;
	}

	/**
	 * Get enrolled memberships for Student store.
	 *
	 * @param mixed $student Student.
	 */
	public function get_enrolled_memberships( $student ) {
		if ( ! ohmylms_is_pro() ) {
			return array();
		}

		$cache_key = $student->get_id();

		if ( isset( self::$enrolled_memberships_cache[ $cache_key ] ) ) {
			return self::$enrolled_memberships_cache[ $cache_key ];
		}

		global $wpdb;
		$table_name      = $wpdb->prefix . 'ohmylms_user_membership';
		$post_table      = $wpdb->prefix . 'posts';
		$query           = $wpdb->prepare(
			'
			SELECT e.membership_id, e.order_id
			FROM %i e
			JOIN %i p ON e.membership_id = p.ID
			WHERE e.user_id = %d AND e.status = %s AND p.post_type = %s
		',
			$table_name,
			$post_table,
			$student->get_id(),
			'enrolled',
			'ohmylms-membership'
		);
		$membership_data = $wpdb->get_results( $query, ARRAY_A );

		$memberships = array();
		if ( function_exists( 'ohmylms_get_membership' ) ) {
			foreach ( $membership_data as $data ) {
				$membership = ohmylms_get_membership( $data['membership_id'] );
				if ( $membership ) {
					if ( ! empty( $data['order_id'] ) ) {
						$order = ohmylms_ecommerce_get_order( $data['order_id'] );
						if ( $order ) {
							$subscription_id = get_post_meta( $data['order_id'], '_subscription_id', true );
							if ( $subscription_id ) {
								$subscription = ohmylms_ecommerce_get_subscription( $subscription_id );
								if ( $subscription ) {
									$membership->subscription = $subscription;
								}
							}
							$membership->order = $order;
						}
					}
					$memberships[] = $membership;
				}
			}
		}

		self::$enrolled_memberships_cache[ $cache_key ] = $memberships;
		return $memberships;
	}

	/**
	 * Get enrolled courses for Student store.
	 *
	 * @param mixed $student Student.
	 */
	public function get_enrolled_courses( $student ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		$post_table = $wpdb->prefix . 'posts';
		$query      = $wpdb->prepare(
			'
			SELECT DISTINCT e.course_id
			FROM %i e
			JOIN %i p ON e.course_id = p.ID
			WHERE e.user_id = %d AND e.status = %s AND p.post_type = %s
		',
			$table_name,
			$post_table,
			$student->get_id(),
			'enrolled',
			'ohmylms-course'
		);
		$course_ids = $wpdb->get_results( $query, ARRAY_A );

		$courses = array();
		foreach ( $course_ids as $course_id ) {
			$courses[] = ohmylms_get_course( $course_id['course_id'] );
		}

		return $courses;
	}


	/**
	 * Get progress course for Student store.
	 *
	 * @param mixed $student Student.
	 */
	public function get_progress_course( $student ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		$post_table = $wpdb->prefix . 'posts';
		$query      = $wpdb->prepare(
			'
			SELECT DISTINCT e.course_id
			FROM %i e
			JOIN %i p ON e.course_id = p.ID
			WHERE e.user_id = %d AND e.progress = %s AND p.post_type = %s
		',
			$table_name,
			$post_table,
			$student->get_id(),
			'running',
			'ohmylms-course'
		);
		$course_ids = $wpdb->get_results( $query, ARRAY_A );

		$courses = array();
		foreach ( $course_ids as $course_id ) {
			$courses[] = ohmylms_get_course( $course_id['course_id'] );
		}

		return $courses;
	}

	/**
	 * Get completed course for Student store.
	 *
	 * @param mixed $student Student.
	 */
	public function get_completed_course( $student ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		$post_table = $wpdb->prefix . 'posts';
		$query      = $wpdb->prepare(
			'
			SELECT DISTINCT e.course_id
			FROM %i e
			JOIN %i p ON e.course_id = p.ID
			WHERE e.user_id = %d AND e.progress = %s AND p.post_type = %s
		',
			$table_name,
			$post_table,
			$student->get_id(),
			'completed',
			'ohmylms-course'
		);
		$course_ids = $wpdb->get_results( $query, ARRAY_A );

		$courses = array();
		foreach ( $course_ids as $course_id ) {
			$courses[] = ohmylms_get_course( $course_id['course_id'] );
		}

		return $courses;
	}

	/**
	 * Get the course progress percentage of the student.
	 *
	 * @param Student $student The student object.
	 * @param int     $course_id The course ID.
	 * @return int The course progress percentage of the student.
	 */
	public function get_course_progress_percentage( $student, $course_id ) {
		return $this->get_over_all_completion_rate( $student, $course_id );
	}

	/**
	 * Get the total points of the course.
	 *
	 * @param Student $student The student object.
	 * @param int     $course_id The course ID.
	 * @return int The total points of the course.
	 */
	public function get_course_total_points( $student, $course_id ) {
		$course = ohmylms_get_course( $course_id );
		return $course->get_all_content_count();
	}

	/**
	 * Get the completed points of the course.
	 *
	 * @param Student $student The student object.
	 * @param int     $course_id The course ID.
	 * @return int The completed points of the course.
	 */
	public function get_course_completed_points( $student, $course_id ) {
		return $this->get_completed_content_count( $student, $course_id );
	}
	/**
	 * Get the time remaining for the course.
	 *
	 * @param Student $student The student object.
	 * @param int     $course_id The course ID.
	 * @return string The time remaining for the course.
	 */
	public function get_course_time_remaining( $student, $course_id ) {
		$course           = ohmylms_get_course( $course_id );
		$completed_points = $this->get_course_completed_points( $student, $course_id );
		$total_point      = $this->get_course_total_points( $student, $course_id );
		$duration         = $course->get_duration();

		if ( 0 == $completed_points ) {
			$text_time = '';
			if ( ! empty( $duration['hour'] ) ) {
				$text_time .= $duration['hour'] . ' hr ';
			}

			if ( ! empty( $duration['min'] ) ) {
				$text_time .= $duration['min'] . ' min ';
			}

			if ( ! empty( $duration['sec'] ) ) {
				$text_time .= $duration['sec'] . ' sec ';
			}
			if ( ! $text_time ) {
				return false;
			}

			return $text_time . 'left';
		}

		// Default missing values to 0.
		$hours = isset( $duration['hour'] ) ? $duration['hour'] : 0;
		$mins  = isset( $duration['min'] ) ? $duration['min'] : 0;
		$secs  = isset( $duration['sec'] ) ? $duration['sec'] : 0;

		// Convert duration to total hours.
		$total_hours = $hours + ( $mins / 60 ) + ( $secs / 3600 );
		// $hours            = $duration['hour'] + ( $duration['min'] / 60 ) + ( $duration['sec'] / 3600 );
		if ( ! $total_point || 0 == $total_point ) {
			return false;
		}
		$left = $completed_points > 0 ? intval( $total_hours - ( $total_hours * $completed_points / $total_point ) ) : $total_hours;
		if ( ! $left ) {
			return false;
		}
		return $left . ' hours left';
	}
	/**
	 * Get the resume URL for the course.
	 *
	 * @param Student $student The student object.
	 * @param int     $course_id The course ID.
	 * @return string The resume URL for the course.
	 */
	public function get_course_resume_url( $student, $course_id ) {
		// Get all the lessons of the course.
		$course = ohmylms_get_course( $course_id );
		if ( ! $course ) {
			return '#'; // Return fallback URL if course doesn't exist.
		}
		$completion_rate = $student->get_course_progress_percentage( $course_id );

		if ( 100 <= $completion_rate ) {
			return $course->get_course_first_lesson_url(); // Return certificate URL if course is completed.
		}

		$chapters = $course->get_chapters();
		if ( is_array( $chapters ) ) {
			foreach ( $chapters as $chapter_array ) {
				if ( ! isset( $chapter_array['id'] ) ) {
					continue;
				}
				$chapter = ohmylms_get_chapter( $chapter_array['id'] );

				if ( ! $chapter ) {
					continue;
				}

				$lessons = $chapter->get_lessons();
				if ( is_array( $lessons ) ) {
					foreach ( $lessons as $lesson_array ) {
						if ( ! isset( $lesson_array['id'] ) ) {
							continue;
						}
						if ( isset( $lesson_array['type'] ) && 'quiz' === $lesson_array['type'] ) {
							$quiz = ohmylms_get_quiz( $lesson_array['id'] );
							if ( $quiz && ! $student->maybe_completed( $quiz->get_id() ) ) {
								return ohmylms_get_pretty_content_permalink( $quiz->get_id() ); // Return the URL of the first incomplete quiz.
							}
						}

						if ( isset( $lesson_array['type'] ) && 'assignment' === $lesson_array['type'] ) {
							$assignment = ohmylms_get_assignment( $lesson_array['id'] );
							if ( $assignment && ! $student->maybe_completed( $assignment->get_id() ) ) {
								return ohmylms_get_pretty_content_permalink( $assignment->get_id() ); // Return the URL of the first incomplete assignment.
							}
						}

						$lesson = ohmylms_get_lesson( $lesson_array['id'] );
						if ( ! $lesson ) {
							continue;
						}
						if ( ! $student->maybe_completed( $lesson->get_id() ) ) {
							return ohmylms_get_pretty_content_permalink( $lesson->get_id() ); // Return the URL of the first incomplete lesson.
						}
					}
				}
			}
		}
		return ohmylms_get_course_first_lesson_url( $course_id );
	}
	/**
	 * Return the placeholder URL while certificate downloads are unavailable.
	 *
	 * @param Student $student The student object, retained for compatibility.
	 * @param int     $course_id The course ID, retained for compatibility.
	 * @return string The placeholder URL.
	 */
	public function get_course_certificate_url( $student, $course_id ) {
		return '#';
	}

	/**
	 * Is course in progress for Student store.
	 *
	 * @param mixed $student Student.
	 * @param mixed $course_id Course identifier.
	 */
	public function is_course_in_progress( $student, $course_id ) {
		$cache_key = $student->get_id() . '_' . $course_id;

		if ( array_key_exists( $cache_key, self::$course_in_progress_cache ) ) {
			return self::$course_in_progress_cache[ $cache_key ];
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		$course     = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND course_id = %d AND status = %s AND progress = %s', $table_name, $student->get_id(), $course_id, 'enrolled', 'running' ), ARRAY_A );
		$result     = $course ? true : false;

		self::$course_in_progress_cache[ $cache_key ] = $result;
		return $result;
	}


	/**
	 * Is course completed for Student store.
	 *
	 * @param mixed $student Student.
	 * @param mixed $course_id Course identifier.
	 */
	public function is_course_completed( $student, $course_id ) {
		$cache_key = $student->get_id() . '_' . $course_id;

		if ( array_key_exists( $cache_key, self::$course_completed_cache ) ) {
			return self::$course_completed_cache[ $cache_key ];
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		$course     = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND course_id = %d AND progress = %s', $table_name, $student->get_id(), $course_id, 'completed' ), ARRAY_A );
		$result     = $course ? true : false;

		self::$course_completed_cache[ $cache_key ] = $result;
		return $result;
	}
	/**
	 * Get course completed date for Student store.
	 *
	 * @param mixed $student Student.
	 * @param mixed $course_id Course identifier.
	 */
	public function get_course_completed_date( $student, $course_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		$course     = $wpdb->get_row( $wpdb->prepare( 'SELECT end_date FROM %i WHERE user_id = %d AND course_id = %d AND progress = %s', $table_name, $student->get_id(), $course_id, 'completed' ), ARRAY_A );
		$result     = ! empty( $course['end_date'] ) ? $course['end_date'] : '';
		return $result;
	}

	/**
	 * Check if the student is maybe enrolled in a course.
	 *
	 * @param Student $student The student object.
	 * @param int     $course_id The ID of the course.
	 * @return bool True if the student is maybe enrolled, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function maybe_enrolled( $student, $course_id ) {
		$cache_key = $student->get_id() . '_' . $course_id;

		// Check if result is already cached.
		if ( array_key_exists( $cache_key, self::$enrollment_cache ) ) {
			return self::$enrollment_cache[ $cache_key ];
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		$course     = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND course_id = %d AND status = %s', $table_name, $student->get_id(), $course_id, 'enrolled' ), ARRAY_A );
		$result     = $course ? true : false;

		// Cache the result.
		self::$enrollment_cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Check if the student has a cover image.
	 *
	 * @param Student $student The student object.
	 * @return bool True if the student has a cover image, false otherwise.
	 * @since 1.0.0
	 */
	public function has_cover_image( $student ) {
		return ! empty( get_user_meta( $student->get_id(), 'cover_image', true ) );
	}


	/**
	 * Complete lesson.
	 *
	 * @param mixed $student Student.
	 * @param mixed $lesson_id Lesson identifier.
	 * @param mixed $course_id Course identifier.
	 * .
	 * @return bool|int|\mysqli_result|null
	 * @throws \Exception When the operation cannot be completed.*/
	public function complete_lesson( $student, $lesson_id, $course_id ) {
		global $wpdb;
		$content_type  = ohmylms_get_content_type_id_by_content_id( $lesson_id );
		$lesson        = ohmylms_get_content_object( $content_type, $lesson_id );
		$content_type  = $lesson->get_type();
		$enrollment_id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d AND course_id = %d', $wpdb->prefix . 'ohmylms_user_enrollment', $student->get_id(), $course_id ) );
		$table_name    = $wpdb->prefix . 'ohmylms_user_progress';

		if ( ! $enrollment_id ) {
			return null;
		}

		// Check if the lesson is already completed.
		$existing_progress = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE enrollment_id = %d AND content_id = %d AND status = %s', $table_name, $enrollment_id, $lesson_id, 'completed' ) );

		if ( ! $existing_progress ) {
			$progress_id = $wpdb->insert(
				$table_name,
				array(
					'enrollment_id' => $enrollment_id,
					'content_id'    => $lesson_id,
					'content_type'  => $content_type,
					'status'        => 'completed',
					'start_date'    => current_time( 'mysql' ),
				)
			);
			self::clear_learning_cache( $student->get_id(), $course_id );
			if ( $progress_id ) {
				do_action( 'ohmylms_verified_lesson_completed', (int) $wpdb->insert_id, $lesson_id, $course_id, $student->get_id() );
			}
			if ( \OhMyLMS\Learning\CourseProgram::managed( $student->get_id(), $course_id ) ) {
				do_action( 'ohmylms_learning_activity_completed', $student->get_id(), $course_id );
				return $progress_id;
			}
			$percentage = $student->get_course_progress_percentage( $course_id );
			if ( $percentage >= 100 ) {
				do_action( 'ohmylms_lesson_already_completed', $student->get_id(), $course_id, $lesson_id );
			}

			return $progress_id;
		}
		return null; // or any other value indicating no new insert was made.
	}

	/**
	 * Maybe completed for Student store.
	 *
	 * @param mixed $student Student.
	 * @param mixed $lesson_id Lesson identifier.
	 */
	public function maybe_completed( $student, $lesson_id ) {
		$cache_key = $student->get_id() . '_' . $lesson_id;

		if ( isset( self::$maybe_completed_cache[ $cache_key ] ) ) {
			return self::$maybe_completed_cache[ $cache_key ];
		}

		global $wpdb;
		$table_name       = $wpdb->prefix . 'ohmylms_user_progress';
		$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';
		$query            = $wpdb->prepare(
			'
		SELECT p.*
		FROM %i p
		JOIN %i e ON p.enrollment_id = e.id
		WHERE e.user_id = %d AND p.content_id = %d AND p.status = %s
	',
			$table_name,
			$enrollment_table,
			$student->get_id(),
			$lesson_id,
			'completed'
		);
		$lesson           = $wpdb->get_row( $query, ARRAY_A );
		$result           = $lesson ? true : false;

		if ( $result ) {
			self::$maybe_completed_cache[ $cache_key ] = true;
		} else {
			unset( self::$maybe_completed_cache[ $cache_key ] );
		}
		return $result;
	}


	/**
	 * Get completed lesson for Student store.
	 *
	 * @param mixed $student Student.
	 * @param mixed $course_id Course identifier.
	 */
	public function get_completed_lesson( &$student, $course_id ) {
		global $wpdb;
		$table_name        = $wpdb->prefix . 'ohmylms_user_progress';
		$enrollment_table  = $wpdb->prefix . 'ohmylms_user_enrollment';
		$user_id           = get_current_user_id();
		$course            = ohmylms_get_course( $course_id );
		$course_id         = $course->get_id();
		$completed_lessons = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i up
		INNER JOIN %i ue ON up.enrollment_id = ue.id
		WHERE ue.user_id = %d AND ue.course_id = %d AND up.status = %s',
				$table_name,
				$enrollment_table,
				$user_id,
				$course_id,
				'completed'
			)
		);
		return $completed_lessons;
	}

	/**
	 * Get completed content count for Student store.
	 *
	 * @param mixed $student Student.
	 * @param mixed $course_id Course identifier.
	 */
	public function get_completed_content_count( &$student, $course_id ) {
		$cache_key = $student->get_id() . '_' . $course_id;

		if ( isset( self::$completed_content_count_cache[ $cache_key ] ) ) {
			return self::$completed_content_count_cache[ $cache_key ];
		}

		global $wpdb;
		$table_name       = $wpdb->prefix . 'ohmylms_user_progress';
		$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';
		$user_id          = $student->get_id();
		$course           = ohmylms_get_course( $course_id );
		if ( ! $course ) {
			return 0;
		}

		$course_id               = $course->get_id();
		$completed_content_count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i up
		INNER JOIN %i ue ON up.enrollment_id = ue.id
		WHERE ue.user_id = %d AND ue.course_id = %d AND up.status = %s',
				$table_name,
				$enrollment_table,
				$user_id,
				$course_id,
				'completed'
			)
		);

		self::$completed_content_count_cache[ $cache_key ] = $completed_content_count;
		return $completed_content_count;
	}
	/**
	 * Get completed content count by type for Student store.
	 *
	 * @param mixed $student Student.
	 * @param mixed $course_id Course identifier.
	 * @param array $content_type Content type.
	 */
	public function get_completed_content_count_by_type( &$student, $course_id, $content_type = array() ) {
		global $wpdb;
		$table_name              = $wpdb->prefix . 'ohmylms_user_progress';
		$enrollment_table        = $wpdb->prefix . 'ohmylms_user_enrollment';
		$user_id                 = $student->get_id();
		$course                  = ohmylms_get_course( $course_id );
		$completed_content_count = 0;
		if ( $course && ! empty( $content_type ) ) {
			$course_id               = $course->get_id();
			$placeholders            = implode( ',', array_fill( 0, count( $content_type ), '%s' ) );
			$args                    = array_merge( array( $table_name, $enrollment_table, $user_id, $course_id, 'completed' ), array_values( $content_type ) );
			$completed_content_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i up
			INNER JOIN %i ue ON up.enrollment_id = ue.id
			WHERE ue.user_id = %d AND ue.course_id = %d AND up.status = %s AND up.content_type IN ($placeholders)",
					$args
				)
			);

		}

		return $completed_content_count;
	}

	/**
	 * Get over all completion rate.
	 *
	 * @param mixed $student Student. Student
	 * .
	 * @param mixed $course_id Course identifier.
	 * @return int|string
	 * @throws \Exception When the operation cannot be completed.*/
	public function get_over_all_completion_rate( $student, $course_id ) {
		if ( \OhMyLMS\Learning\CourseProgram::managed( $student->get_id(), $course_id ) ) {
			$state = \OhMyLMS\Learning\CompletionPolicy::award( $student->get_id(), $course_id );
			return is_wp_error( $state ) ? 0 : \OhMyLMS\Learning\CompletionPolicy::percentage( $state );
		}
		$course                  = ohmylms_get_course( $course_id );
		$completed_content_count = $this->get_completed_content_count( $student, $course_id );
		if ( ! $course ) {
			return 0;
		}
		$lessons_count       = $course->get_lessons_count();
		$quiz_count          = $course->get_quiz_count();
		$assignment_count    = $course->get_assignment_count();
		$total_content_count = $lessons_count + $quiz_count + $assignment_count;
		$completion_rate     = 0;
		if ( $total_content_count > 0 ) {
			$completion_rate = number_format( ( $completed_content_count / $total_content_count ) * 100, 0 );
			$completion_rate = $completion_rate > 100 ? 100 : $completion_rate;
		}

		$course_id  = $course->get_id();
		$student_id = $student->get_id();

		if ( intval( $completion_rate ) === 100 ) {

			global $wpdb;
			$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
			$enroll_data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND course_id = %d', $table_name, $student_id, $course_id ), ARRAY_A );
			if ( 'enrolled' === $enroll_data['status'] && 'running' === $enroll_data['progress'] ) {
				$wpdb->update(
					$table_name,
					array(
						'progress' => 'completed',
						'end_date' => current_time( 'mysql' ),
					),
					array(
						'user_id'   => $student_id,
						'course_id' => $course_id,
					)
				);

			}
		}
		return $completion_rate;
	}

	/**
	 * Get quiz completion rate for Student store.
	 *
	 * @param mixed $student Student.
	 * @param mixed $course_id Course identifier.
	 */
	public function get_quiz_completion_rate( $student, $course_id ) {
		$course = ohmylms_get_course( $course_id );
		if ( ! $course ) {
			return 0;
		}

		$completed_content_count = $this->get_completed_content_count_by_type( $student, $course_id, array( 'quiz' ) );
		$quiz_count              = $course->get_quiz_count();
		$completion_rate         = 0;
		if ( $quiz_count > 0 ) {
			$completion_rate = number_format( ( $completed_content_count / $quiz_count ) * 100, 0 );
		}
		return $completion_rate;
	}
	/**
	 * Get assignment completion rate for Student store.
	 *
	 * @param mixed $student Student.
	 * @param mixed $course_id Course identifier.
	 */
	public function get_assignment_completion_rate( $student, $course_id ) {
		$course = ohmylms_get_course( $course_id );
		if ( ! $course ) {
			return 0;
		}

		$completed_content_count = $this->get_completed_content_count_by_type( $student, $course_id, array( 'assignment' ) );
		$quiz_count              = $course->get_assignment_count();
		$completion_rate         = 0;
		if ( $quiz_count > 0 ) {
			$completion_rate = number_format( ( $completed_content_count / $quiz_count ) * 100, 0 );
		}
		return $completion_rate;
	}

	/**
	 * Delete enrollment for Student store.
	 *
	 * @param mixed $student_id Student identifier.
	 * @param mixed $order_id Order identifier.
	 */
	public function delete_enrollment( $student_id, $order_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		// Delete the row.
		$delete_result = $wpdb->delete(
			$table_name,
			array(
				'user_id'  => $student_id,
				'order_id' => $order_id,
			),
			array( '%d', '%d' ) // Specify the format of the data (integer in this case).
		);

		// Check if the deletion was successful.
		if ( $delete_result ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Update enrollment status for Student store.
	 *
	 * @param mixed $student_id Student identifier.
	 * @param mixed $order_id Order identifier.
	 * @param mixed $new_status New status.
	 */
	public function update_enrollment_status( $student_id, $order_id, $new_status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';

		// Update the status.
		$update_result = $wpdb->update(
			$table_name,
			array( 'status' => $new_status ), // Data to update.
			array(
				'user_id'  => $student_id,
				'order_id' => $order_id,
			), // WHERE conditions.
			array( '%s' ), // Format for updated data.
			array( '%d', '%d' ) // Format for WHERE conditions.
		);

		// Check if the update was successful.
		if ( false !== $update_result ) {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * Update membership enrollment status for Student store.
	 *
	 * @param mixed $student_id Student identifier.
	 * @param mixed $order_id Order identifier.
	 * @param mixed $new_status New status.
	 */
	public function update_membership_enrollment_status( $student_id, $order_id, $new_status ) {
		if ( ! ohmylms_is_pro() ) {
			return false;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_membership';

		// Update the status.
		$update_result = $wpdb->update(
			$table_name,
			array( 'status' => $new_status ), // Data to update.
			array(
				'user_id'  => $student_id,
				'order_id' => $order_id,
			), // WHERE conditions.
			array( '%s' ), // Format for updated data.
			array( '%d', '%d' ) // Format for WHERE conditions.
		);

		// Check if the update was successful.
		if ( false !== $update_result ) {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * Retrieves all assignment attempts for a specific student and course.
	 *
	 * @param object $student The student object.
	 * @param int    $course_id The ID of the course.
	 * @return array An array of assignment attempts.
	 *
	 * @since 1.0.0
	 */
	public function get_all_assignment_attempts( &$student, $course_id ) {
		if ( ! ohmylms_is_pro() ) {
			return array();
		}
		global $wpdb;

		$table_name = "{$wpdb->prefix}ohmylms_assignment_attempts";

		// Prepare and execute query.
		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND course_id = %d ORDER BY assignment_id', $table_name, $student->get_id(), $course_id ), ARRAY_A );

		// Return early if no results found.
		if ( ! $results ) {
			return array();
		}

		$grouped_attempts = array();

		foreach ( $results as $attempt ) {
			$assignment_id = $attempt['assignment_id'];

			// Initialize assignment entry if not set.
			if ( ! isset( $grouped_attempts[ $assignment_id ] ) ) {
				$grouped_attempts[ $assignment_id ] = array(
					'assignment'  => ohmylms_get_assignment( $assignment_id ),
					'submissions' => array(),
				);
			}

			// Unserialize files if present.
			if ( ! empty( $attempt['files'] ) ) {
				$attempt['files'] = maybe_unserialize( $attempt['files'] );
			}

			// Append attempt to submissions.
			$grouped_attempts[ $assignment_id ]['submissions'][] = $attempt;
		}

		return array_values( $grouped_attempts );
	}


	/**
	 * Get assignment remaining time for Student store.
	 *
	 * @param mixed $student Student.
	 * @param mixed $content_id Content identifier.
	 */
	public function get_assignment_remaining_time( &$student, $content_id ) {
		$content_id = absint( $content_id ); // Ensure content_id is an integer.
		$student_id = absint( $student->get_id() ); // Ensure student ID is an integer.

		$existing_deadline = get_option( "_ohmylms_deadline_{$student_id}_{$content_id}_" );

		if ( empty( $existing_deadline ) ) {
			return false; // No deadline set, return 0 minutes.
		}

		$current_time       = current_time( 'timestamp' ); // Get current WordPress timestamp.
		$deadline_timestamp = strtotime( $existing_deadline ); // Convert deadline to timestamp.

		if ( ! $deadline_timestamp || $deadline_timestamp <= $current_time ) {
			return false; // If invalid timestamp or deadline has passed, return 0.
		}

		$remaining_seconds = $deadline_timestamp - $current_time;
		return floor( $remaining_seconds / 60 ); // Convert seconds to minutes.
	}


	/**
	 * Check student is banned or not
	 *
	 * @param Student $student The student object.
	 * .
	 * @return bool True if the student is banned, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function maybe_banned( &$student ) {
		return 'yes' === get_user_meta( $student->get_id(), '_ohmylms_banned_student', true );
	}


	/**
	 * Check if reminder already sent to student for specific course.
	 *
	 * @param int $student _id Student ID.
	 * @param int $course_id Course ID.
	 * @return bool True if reminder sent, false otherwise.
	 */
	public function maybe_reminder_sent( &$student, $course_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_notifications';

		$query = $wpdb->prepare(
			'SELECT COUNT(*) FROM %i
			WHERE student_id = %d
			AND course_id = %d
			AND status = %s',
			$table_name,
			$student->get_id(),
			$course_id,
			'sent'
		);

		$count = $wpdb->get_var( $query );
		return $count > 0;
	}

	/**
	 * Get last reminder sent to student for specific course.
	 *
	 * @param object $student Student.
	 * @param int    $course_id Course ID.
	 * @return array|false Reminder data or false if no reminder found.
	 *
	 * @since 1.0.0
	 */
	public function get_last_reminder( &$student, $course_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_notifications';

		$query = $wpdb->prepare(
			'SELECT id, subject, message, created_at as sent_date, email
			FROM %i
			WHERE student_id = %d
			AND course_id = %d
			AND status = %s
			ORDER BY created_at DESC
			LIMIT 1',
			$table_name,
			$student->get_id(),
			$course_id,
			'sent'
		);

		$result = $wpdb->get_row( $query, ARRAY_A );

		if ( ! $result ) {
			return false;
		}

		return array(
			'id'        => $result['id'],
			'subject'   => $result['subject'],
			'message'   => $result['message'],
			'sent_date' => $result['sent_date'],
			'email'     => $result['email'],
		);
	}


	/**
	 * Get the total number of orders for a student.
	 *
	 * @param object $student The student object.
	 * @return int The total number of completed orders for the student.
	 *
	 * @since 1.0.0
	 */
	public function get_total_orders( &$student ) {
		$args = array(
			'post_type'      => 'ohmylms-order',
			'post_status'    => 'ohmylms-completed',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		if ( $student->get_id() ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_student_id',
					'value'   => $student->get_id(),
					'compare' => '=',
				),
			);
		}
		$query = new \WP_Query( $args );

		return count( $query->posts );
	}


	/**
	 * Get the total revenue generated by a student.
	 *
	 * @param object $student The student object.
	 * @param string $context The context in which the data is being retrieved (default is 'view').
	 * @return float The total revenue generated by the student.
	 *
	 * @since 1.0.0
	 */
	public function get_total_revenue( &$student, $context = 'view' ) {

		$args = array(
			'post_type'      => 'ohmylms-order',
			'post_status'    => 'ohmylms-completed',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		if ( $student->get_id() ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_student_id',
					'value'   => $student->get_id(),
					'compare' => '=',
				),
			);
		}
		$query         = new \WP_Query( $args );
		$total_revenue = 0;
		foreach ( $query->posts as $order_id ) {
			$order          = new \OhMyLMS\Commerce\Data\Order( $order_id );
			$total_revenue += floatval( $order->get_total() );
		}
		if ( 'view' === $context ) {
			$total_revenue = \ohmylms_price( $total_revenue );
		}
		return $total_revenue;
	}


	/*
	Get the Average Order Value (AOV) for a student.
	 *
	 * @param int|null $student_id The ID of the student. If null, uses the current user.
	 * @return float The average order value for the student.
	 *
	 * @since 1.0.0
	 */
	/**
	 * Get aov for Student store.
	 *
	 * @param mixed $student_id Student identifier.
	 */
	public function get_aov( $student_id = null ) {
		$total_orders  = $this->get_total_orders( $student_id );
		$total_revenue = $this->get_total_revenue( $student_id, 'edit' );
		return $total_orders > 0 ? \ohmylms_price( $total_revenue / $total_orders, 2 ) : 0;
	}
}
