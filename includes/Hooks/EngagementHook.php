<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Hook for Engagement
 *
 * @package    OhMyLMSPro
 * @subpackage OhMyLMSPro/includes
 */
namespace OhMyLMS\Hooks;

use OhMyLMS\Engagement\Point;
use OhMyLMS\Engagement\Badge;
use OhMyLMS\Engagement\Level;
use OhMyLMS\Engagement\Leaderboard;
use OhMyLMS\Engagement\Achievements;


/**
 * Plugin engagement hook implementation.
 */
class EngagementHook {

	/**
	 * Register hooks for Engagement hook.
	 */
	public function register_hooks() {

		add_filter( 'ohmylms_show_gamification_menu', array( $this, 'add_gamification_menu' ) );
		add_filter( 'ohmylms_show_communities_menu', array( $this, 'add_communities_menu' ) );
		add_action( 'ohmylms_course_completion_rate', array( $this, 'course_completion_rate' ), 10, 3 );
		add_action( 'ohmylms_lesson_completed', array( $this, 'lesson_completed' ), 10, 3 );
		add_action( 'ohmylms_quiz_submission', array( $this, 'quiz_submission' ), 10, 4 );
		add_action( 'ohmylms_quiz_submission', array( $this, 'quiz_achievement' ), 10, 4 );
		add_action( 'ohmylms_rest_review_quiz_attempt', array( $this, 'quiz_achievement' ), 10, 4 );
		add_action( 'ohmylms_after_assignment_submitted', array( $this, 'after_assignment_submitted' ), 10, 3 );
		add_action( 'ohmylms_pro_after_assignment_review', array( $this, 'assignment_achievement' ), 10, 4 );
		add_action( 'ohmylms_after_point_added', array( $this, 'after_point_added' ), 10, 2 );
		// Render and enqueue before WordPress prints import maps/modules in the footer.
		add_action( 'wp_footer', array( $this, 'show_celebration' ), 2 );
		add_action( 'comment_post', array( $this, 'after_comment' ), 10 );
		add_action( 'user_register', array( $this, 'after_user_register' ), 10 );
		add_action( 'ohmylms_after_enrolled_student', array( $this, 'after_enrolled_student' ), 10 );
		add_action( 'ohmylms_after_checkout_process', array( $this, 'after_create_order' ), 10 );

		// Adding community hooks.
		add_action( 'ohmylms_community_comment_created', array( $this, 'after_community_comment' ), 10, 2 );
		add_action( 'ohmylms_community_reaction_created', array( $this, 'after_community_reaction' ), 10, 2 );
		add_action( 'ohmylms_community_post_created', array( $this, 'after_community_post' ), 10, 2 );
	}

	/**
	 * Add gamification menu
	 *
	 * @param mixed $should_show Should show.
	 */
	public function add_gamification_menu( $should_show ) {
		$integrations = get_option( 'ohmylms_integrations', array() );
		return is_array( $integrations ) && isset( $integrations['gamification']['is_enable'] ) && $integrations['gamification']['is_enable'];
	}

	/**
	 * Add communities menu
	 *
	 * @param mixed $should_show Should show.
	 */
	public function add_communities_menu( $should_show ) {
		$integrations = get_option( 'ohmylms_integrations', array() );
		return is_array( $integrations ) && isset( $integrations['community']['is_enable'] ) && $integrations['community']['is_enable'];
	}

	/**
	 * Function to update course completion rate
	 *
	 * @param int $student_id Student ID.
	 * @param int $course_id Course ID.
	 * @param int $completion_rate Completion Rate.
	 */
	public function course_completion_rate( $student_id, $course_id, $completion_rate ) {
		if ( $this->maybe_gamification_enable() && Point::maybe_met_rules( 'course_completion_rate', $completion_rate ) ) {
			$response = Point::add_points( $student_id, 'point', Point::get_points_for_slug( 'course_completion_rate' ), 'Course Completion', $course_id );
			if ( $response ) {
				if ( Point::maybe_enable_email_for_a_slug( 'course_completion_rate' ) ) {
					$email_settings = Point::get_email_settings_for_slug( 'course_completion_rate' );
					if ( ! empty( $email_settings ) && isset( $email_settings['body'], $email_settings['subject'] ) ) {
						$user = get_user_by( 'ID', $student_id );
						if ( $user && isset( $user->user_email ) ) {
							$user_email = $user->user_email;
							Point::send_email( $email_settings['body'], $user_email, $email_settings['subject'] );
						}
					}
				}
			}
		}
	}

	/**
	 * Function to handle lesson completion
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $course_id Course ID.
	 * @param int $student_id Student ID.
	 */
	public function lesson_completed( $lesson_id, $course_id, $student_id ) {
		if ( $this->maybe_gamification_enable() && Point::maybe_met_rules( 'lesson_complete' ) ) {
			$response = Point::add_points( $student_id, 'point', Point::get_points_for_slug( 'lesson_complete' ), 'Lesson Completed', null, null, $lesson_id );
			if ( $response ) {
				if ( Point::maybe_enable_email_for_a_slug( 'lesson_complete' ) ) {
					$email_settings = Point::get_email_settings_for_slug( 'lesson_complete' );
					if ( ! empty( $email_settings ) && isset( $email_settings['body'], $email_settings['subject'] ) ) {
						$user = get_user_by( 'ID', $student_id );
						if ( $user && isset( $user->user_email ) ) {
							$user_email = $user->user_email;
							Point::send_email( $email_settings['body'], $user_email, $email_settings['subject'] );
						}
					}
				}
			}
		}
	}


	/**
	 * Function to handle quiz submission
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param int   $course_id Course ID.
	 * @param int   $student_id Student ID.
	 * @param mixed $argc Argc.
	 */
	public function quiz_submission( $quiz_id, $course_id, $student_id, $argc ) {
		if ( $this->maybe_gamification_enable() && Point::maybe_met_rules( 'submit_quiz' ) ) {
			$response = Point::add_points( $student_id, 'point', Point::get_points_for_slug( 'submit_quiz' ), 'Quiz Submitted', null, null, $quiz_id );

			if ( $response ) {
				if ( Point::maybe_enable_email_for_a_slug( 'submit_quiz' ) ) {
					$email_settings = Point::get_email_settings_for_slug( 'submit_quiz' );
					if ( ! empty( $email_settings ) && isset( $email_settings['body'], $email_settings['subject'] ) ) {
						$user = get_user_by( 'ID', $student_id );
						if ( $user && isset( $user->user_email ) ) {
							$user_email = $user->user_email;
							Point::send_email( $email_settings['body'], $user_email, $email_settings['subject'] );
						}
					}
				}
			}
		}
	}

	/**
	 * Quiz achievement for Engagement hook.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param mixed $course_id Course identifier.
	 * @param mixed $student_id Student identifier.
	 * @param int   $total Total.
	 */
	public function quiz_achievement( $quiz_id, $course_id, $student_id, $total = 0 ) {
		$marks = is_array( $total ) && isset( $total['total'] ) ? $total['total'] : $total;

		if ( $this->maybe_gamification_enable() && Point::maybe_met_rules( 'quiz_passing_mark', $marks ) ) {
			$response = Point::add_points( $student_id, 'point', Point::get_points_for_slug( 'quiz_passing_mark' ), 'Quiz Achievement', null, null, $quiz_id );

			if ( $response ) {
				if ( Point::maybe_enable_email_for_a_slug( 'quiz_passing_mark' ) ) {
					$email_settings = Point::get_email_settings_for_slug( 'quiz_passing_mark' );
					if ( ! empty( $email_settings ) && isset( $email_settings['body'], $email_settings['subject'] ) ) {
						$user = get_user_by( 'ID', $student_id );
						if ( $user && isset( $user->user_email ) ) {
							$user_email = $user->user_email;
							Point::send_email( $email_settings['body'], $user_email, $email_settings['subject'] );
						}
					}
				}
			}
		}
	}


	/**
	 * After point added.
	 *
	 *
	 * @param mixed $user_id User identifier.
	 * @param mixed $points Points.
	 */
	public function after_point_added( $user_id, $points ) {
		if ( $this->maybe_gamification_enable() && ! empty( Badge::maybe_met_rules( $user_id ) ) ) {
			$earned_badge = Badge::maybe_met_rules( $user_id );
			if ( is_array( $earned_badge ) ) {
				foreach ( $earned_badge as $badge ) {
					Badge::add_badge( $user_id, 'badge', $badge, 'Point added', null, null, null );
				}
			}
		}
		if ( $this->maybe_gamification_enable() && ! empty( Level::maybe_met_rules( $user_id ) ) ) {
			$earned_levels = Level::maybe_met_rules( $user_id );
			if ( is_array( $earned_levels ) && ! empty( $earned_levels ) ) {
				foreach ( $earned_levels as $earned_level ) {
					Level::add_level( $user_id, 'level', $earned_level, 'Point added', null, null, null );
				}
			}
		}
	}

	/**
	 * Function to handle comment post
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 * @since 1.0.0
	 */
	public function after_comment( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return;
		}
		$post_id = (int) $comment->comment_post_ID;
		$post    = get_post( $post_id );
		if ( ! $post || 'ohmylms-course' !== $post->post_type ) {
			return; // Not a course post type.
		}

		if ( $this->maybe_gamification_enable() && Point::maybe_met_rules( 'comment_on_course' ) ) {
			$user_id = $comment->user_id;
			if ( $user_id ) {
				$response = Point::add_points( $user_id, 'point', Point::get_points_for_slug( 'comment_on_course' ), 'comment', $post_id, null, null );
				if ( $response ) {
					if ( Point::maybe_enable_email_for_a_slug( 'comment_on_course' ) ) {
							$email_settings = Point::get_email_settings_for_slug( 'comment_on_course' );
						if ( ! empty( $email_settings ) && isset( $email_settings['body'], $email_settings['subject'] ) ) {
							$user = get_user_by( 'ID', $user_id );
							if ( $user && isset( $user->user_email ) ) {
								$user_email = $user->user_email;
								Point::send_email( $email_settings['body'], $user_email, $email_settings['subject'] );
							}
						}
					}
				}
			}
		}
	}


	/**
	 * After community comment created.
	 *
	 * @param object $comment Comment.
	 * @param mixed  $data Input data.
	 * @return void
	 * @since 1.0.0
	 */
	public function after_community_comment( $comment, $data ) {
		if ( $this->maybe_gamification_enable() && Point::maybe_met_rules( 'community_post_comment' ) ) {
			$user_id = isset( $data['user_id'] ) ? $data['user_id'] : get_current_user_id();
			if ( $user_id ) {
				$response = Point::add_points( $user_id, 'point', Point::get_points_for_slug( 'community_post_comment' ), 'comment', $comment, null, null );
				if ( $response ) {
					if ( Point::maybe_enable_email_for_a_slug( 'community_post_comment' ) ) {
							$email_settings = Point::get_email_settings_for_slug( 'community_post_comment' );
						if ( ! empty( $email_settings ) && isset( $email_settings['body'], $email_settings['subject'] ) ) {
							$user = get_user_by( 'ID', $user_id );
							if ( $user && isset( $user->user_email ) ) {
								$user_email = $user->user_email;
								Point::send_email( $email_settings['body'], $user_email, $email_settings['subject'] );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * After community reaction created.
	 *
	 * @param int   $reaction _id Reaction ID.
	 * @param mixed $data Input data.
	 * @return void
	 * @since 1.0.0
	 */
	public function after_community_reaction( $reaction, $data ) {
		if ( $this->maybe_gamification_enable() && Point::maybe_met_rules( 'community_reaction' ) ) {
			$user_id = isset( $data['user_id'] ) ? $data['user_id'] : get_current_user_id();
			if ( $user_id ) {
				$response = Point::add_points( $user_id, 'point', Point::get_points_for_slug( 'community_reaction' ), 'reaction', $reaction, null, null );
				if ( $response ) {
					if ( Point::maybe_enable_email_for_a_slug( 'community_reaction' ) ) {
							$email_settings = Point::get_email_settings_for_slug( 'community_reaction' );
						if ( ! empty( $email_settings ) && isset( $email_settings['body'], $email_settings['subject'] ) ) {
							$user = get_user_by( 'ID', $user_id );
							if ( $user && isset( $user->user_email ) ) {
								$user_email = $user->user_email;
								Point::send_email( $email_settings['body'], $user_email, $email_settings['subject'] );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * After community post created.
	 *
	 * @param int   $id Post ID.
	 * @param array $data Post data.
	 * @return void
	 * @since 1.0.0
	 */
	public function after_community_post( $id, $data ) {
		if ( $this->maybe_gamification_enable() && Point::maybe_met_rules( 'community_post_create' ) ) {
			$user_id = isset( $data['user_id'] ) ? $data['user_id'] : get_current_user_id();
			if ( $user_id ) {
				$response = Point::add_points( $user_id, 'point', Point::get_points_for_slug( 'community_post_create' ), 'post', $id, null, null );
				if ( $response ) {
					if ( Point::maybe_enable_email_for_a_slug( 'community_post_create' ) ) {
							$email_settings = Point::get_email_settings_for_slug( 'community_post_create' );
						if ( ! empty( $email_settings ) && isset( $email_settings['body'], $email_settings['subject'] ) ) {
							$user = get_user_by( 'ID', $user_id );
							if ( $user && isset( $user->user_email ) ) {
								$user_email = $user->user_email;
								Point::send_email( $email_settings['body'], $user_email, $email_settings['subject'] );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Function to handle after enrolled student
	 *
	 * @param mixed $order Order.
	 * @return void
	 * @since 1.0.0
	 */
	public function after_enrolled_student( $order ) {
		if ( ! is_object( $order ) ) {
			$order_id = absint( $order );
			$order    = ohmylms_ecommerce_get_order( $order_id );
		}

		if ( ! is_object( $order ) ) {
			return;
		}

		$student_id = $order->get_student_id();
		global $wpdb;
		$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
		$enroll_data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND order_id = %d AND status = %d', $table_name, $student_id, $order->get_id(), 'enrolled' ), ARRAY_A );

		if ( empty( $enroll_data['course_id'] ) ) {
			return;
		}

		$course_id = $enroll_data['course_id'];
		if ( $this->maybe_gamification_enable() && Point::maybe_met_rules( 'course_enrollment' ) ) {
			$response = Point::add_points( $student_id, 'point', Point::get_points_for_slug( 'course_enrollment' ), 'course_enrollment', $course_id );
			if ( $response ) {
				if ( Point::maybe_enable_email_for_a_slug( 'course_enrollment' ) ) {
						$email_settings = Point::get_email_settings_for_slug( 'course_enrollment' );
					if ( ! empty( $email_settings ) && isset( $email_settings['body'], $email_settings['subject'] ) ) {
						$user = get_user_by( 'ID', $student_id );
						if ( $user && isset( $user->user_email ) ) {
							$user_email = $user->user_email;
							Point::send_email( $email_settings['body'], $user_email, $email_settings['subject'] );
						}
					}
				}
			}
		}
	}

	/**
	 * Function to handle after create order
	 *
	 * @param object $order The order object.
	 * @return void
	 * @since 1.0.0
	 */
	public function after_create_order( $order ) {
		if ( ! is_object( $order ) ) {
			$order_id = absint( $order );
			$order    = ohmylms_ecommerce_get_order( $order_id );
		}

		if ( ! is_object( $order ) ) {
			return;
		}

		$purchased_by = get_post_meta( $order->get_id(), '_purchased_by', true );
		if ( (float) $order->get_total() <= 0 || 'point' === $purchased_by ) {
			return; // Only process orders with a total amount.
		}
		$student_id = $order->get_student_id();
		global $wpdb;
		$course_id = false;
		foreach ( $order->get_items() as $item_id => $item ) {
			$post_type = get_post_type( $item->get_course_id() );
			if ( OHMYLMS_COURSE_CPT === $post_type ) {
				$course = $item->get_course();
				$type   = 'course';
			} else {
				$course = $item->get_course();
			}

			if ( is_object( $course ) ) {
				$course_id = $item->get_course_id();
			}
		}
		if ( $this->maybe_gamification_enable() && Point::maybe_met_rules( 'purchase' ) ) {
			$response = Point::add_points( $student_id, 'point', Point::get_points_for_slug( 'purchase' ), 'purchase', $course_id );
			if ( $response ) {
				if ( Point::maybe_enable_email_for_a_slug( 'purchase' ) ) {
						$email_settings = Point::get_email_settings_for_slug( 'purchase' );
					if ( ! empty( $email_settings ) && isset( $email_settings['body'], $email_settings['subject'] ) ) {
						$user = get_user_by( 'ID', $student_id );
						if ( $user && isset( $user->user_email ) ) {
							$user_email = $user->user_email;
							Point::send_email( $email_settings['body'], $user_email, $email_settings['subject'] );
						}
					}
				}
			}
		}
	}


	/**
	 * After register as an user
	 *
	 * @param int $user_id User identifier.
	 * .
	 * @return void
	 * @since 1.0.0
	 */
	public function after_user_register( $user_id ) {
		if ( $this->maybe_gamification_enable() && Point::maybe_met_rules( 'user_registration' ) ) {
			$response = Point::add_points( $user_id, 'point', Point::get_points_for_slug( 'user_registration' ), 'user_registration' );
			if ( $response ) {
				if ( Point::maybe_enable_email_for_a_slug( 'user_registration' ) ) {
						$email_settings = Point::get_email_settings_for_slug( 'user_registration' );
					if ( ! empty( $email_settings ) && isset( $email_settings['body'], $email_settings['subject'] ) ) {
						$user = get_user_by( 'ID', $user_id );
						if ( $user && isset( $user->user_email ) ) {
							$user_email = $user->user_email;
							Point::send_email( $email_settings['body'], $user_email, $email_settings['subject'] );
						}
					}
				}
			}
		}
	}



	/**
	 * Show celebration message on frontend
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function show_celebration() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		get_current_user_id();
		$student_id = get_current_user_id();
		$message    = '';

		if ( get_transient( 'badge_added_for_user_' . $student_id ) ) {
			delete_transient( 'badge_added_for_user_' . $student_id ); // clear after use.
			$message = __( 'Congratulations! You have earned a new  badge 🏅', 'ohmylms' );
		} elseif ( get_transient( 'level_added_for_user_' . $student_id ) ) {
			delete_transient( 'level_added_for_user_' . $student_id ); // clear after use.
			$message = __( 'Congratulations! You have leveled up 🎉', 'ohmylms' );
		} elseif ( get_transient( 'points_added_for_user_' . $student_id ) ) {
			delete_transient( 'points_added_for_user_' . $student_id ); // clear after use.
			$message = __( 'Congratulations! You have earned points 🎉', 'ohmylms' );
		}

		if ( ! empty( $message ) ) {
			ohmylms_get_template( 'global/ohmylms-celebration.php' );
			?>
			<script>
			document.addEventListener("DOMContentLoaded", function () {
				OhMyLMSCelebrate.show("<?php echo esc_js( $message ); ?>");
			});
			</script>
			<?php
		}
	}

	/**
	 * Maybe enable gamification
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function maybe_gamification_enable() {
		$integrations = get_option( 'ohmylms_integrations', array() );
		return is_array( $integrations ) && isset( $integrations['gamification']['is_enable'] ) && $integrations['gamification']['is_enable'];
	}


	/**
	 * After assignment submitted
	 *
	 * @param int $assignment_id Assignment ID.
	 * @param int $course_id Course ID.
	 * @param int $student_id Student ID.
	 */
	public function after_assignment_submitted( $assignment_id, $course_id, $student_id ) {
		if ( $this->maybe_gamification_enable() && Point::maybe_met_rules( 'submit_assignment' ) ) {
			$response = Point::add_points( $student_id, 'point', Point::get_points_for_slug( 'submit_assignment' ), 'Assignment Submitted', $course_id, null, $assignment_id );
			if ( $response ) {
				if ( Point::maybe_enable_email_for_a_slug( 'submit_assignment' ) ) {
					$email_settings = Point::get_email_settings_for_slug( 'submit_assignment' );
					if ( ! empty( $email_settings ) && isset( $email_settings['body'], $email_settings['subject'] ) ) {
						$user = get_user_by( 'ID', $student_id );
						if ( $user && isset( $user->user_email ) ) {
							$user_email = $user->user_email;
							Point::send_email( $email_settings['body'], $user_email, $email_settings['subject'] );
						}
					}
				}
			}
		}
	}


	/**
	 * After assignment submitted
	 *
	 * @param int   $assignment_id Assignment ID.
	 * @param int   $course_id Course ID.
	 * @param int   $student_id Student ID.
	 * @param mixed $marks Marks.
	 */
	public function assignment_achievement( $assignment_id, $course_id, $student_id, $marks ) {
		if ( $this->maybe_gamification_enable() && Point::maybe_met_rules( 'assignment_passing_mark', $marks ) ) {
			$response = Point::add_points( $student_id, 'point', Point::get_points_for_slug( 'assignment_passing_mark' ), 'Assignment Passed', $course_id, null, $assignment_id );
			if ( $response ) {
				if ( Point::maybe_enable_email_for_a_slug( 'assignment_passing_mark' ) ) {
					$email_settings = Point::get_email_settings_for_slug( 'assignment_passing_mark' );
					if ( ! empty( $email_settings ) && isset( $email_settings['body'], $email_settings['subject'] ) ) {
						$user = get_user_by( 'ID', $student_id );
						if ( $user && isset( $user->user_email ) ) {
							$user_email = $user->user_email;
							Point::send_email( $email_settings['body'], $user_email, $email_settings['subject'] );
						}
					}
				}
			}
		}
	}
}
