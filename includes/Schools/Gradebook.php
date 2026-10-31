<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course gradebooks group real enrollments without creating shared user accounts.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Schools;

use OhMyLMS\Student\Enrollments;

defined( 'ABSPATH' ) || exit;

/** Course gradebooks group real enrollments without creating shared user accounts. */
final class Gradebook {
	/**
	 * Register for Gradebook.
	 */
	public static function register() {
		foreach ( array(
			''                           => 'GET',
			'/classes'                   => 'GET,POST',
			'/classes/(?P<class_id>\d+)' => 'POST,DELETE',
			'/grades'                    => 'POST,DELETE',
		) as $suffix => $methods ) {
			register_rest_route(
				'ohmylms/v1',
				'/courses/(?P<course_id>\d+)/gradebook' . $suffix,
				array(
					'methods'             => $methods,
					'permission_callback' => function ( $r ) {
						return current_user_can( 'manage_options' ) && wp_verify_nonce( $r->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ? true : new \WP_Error( 'gradebook_forbidden', 'Only an administrator can manage this gradebook.', array( 'status' => 403 ) );
					},
					'callback'            => array( self::class, 'dispatch' ),
				)
			);
		}
	}
	/**
	 * Dispatch for Gradebook.
	 *
	 * @param mixed $r R.
	 */
	public static function dispatch( $r ) {
		try {
			$course = absint( $r['course_id'] );
			$post   = get_post( $course );
			Service::need( $post && OHMYLMS_COURSE_CPT === $post->post_type && 'trash' !== $post->post_status, 'Course not found.' );
			$route  = $r->get_route();
			$method = $r->get_method();
			if ( substr( $route, -7 ) === '/grades' ) {
				$data = self::grade( $course, $r, 'DELETE' === $method ); } elseif ( $r['class_id'] ) {
				$data = 'DELETE' === $method ? self::detach( $course, absint( $r['class_id'] ) ) : self::attach( $course, absint( $r['class_id'] ), rest_sanitize_boolean( $r['enroll'] ) ); } elseif ( substr( $route, -8 ) === '/classes' ) {
					$data = 'GET' === $method ? self::classes( $course ) : self::attach( $course, absint( $r['class'] ), rest_sanitize_boolean( $r['enroll'] ) ); } else {
					$data = self::read( $course ); }
					$response = rest_ensure_response( $data );
					$response->header( 'Cache-Control', 'private, no-store' );
					return $response;
		} catch ( \Throwable $e ) {
			$status = in_array( $e->getCode(), array( 400, 403, 404, 409 ), true ) ? $e->getCode() : 500;
			if ( 500 === $status ) {
				error_log( '[OhMyLMS gradebook] ' . $e->getMessage() ); }
			return new \WP_Error( 'gradebook_error', 500 === $status ? 'Unable to update the gradebook. Please try again.' : $e->getMessage(), array( 'status' => $status ) );
		}
	}
	/**
	 * Roster for Gradebook.
	 *
	 * @param mixed $class Class.
	 */
	public static function roster( $class ) {
		global $wpdb;
		$c = Service::row( 'classes', $class );
		Service::need( $c && 'active' === $c['status'], 'Select an active class.' );
		if ( (int) $c['school_id'] ) {
			$school = Service::row( 'schools', (int) $c['school_id'] );
			Service::need( $school && 'active' === $school['status'], 'The school is archived.' ); }
		$m     = Schema::table( 'class_memberships' );
		$s     = Schema::table( 'school_memberships' );
		$scope = (int) $c['school_id'] ? $wpdb->prepare( ' AND EXISTS (SELECT 1 FROM %i s WHERE s.school_id=%d AND s.user_id=m.user_id AND s.role=\'student\' AND s.status=\'active\')', $s, $c['school_id'] ) : '';
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT m.user_id FROM $m m JOIN {$wpdb->users} u ON u.ID=m.user_id WHERE m.class_id=%d AND m.role='student' AND m.status='active' $scope", $class ) ) );
	}
	/**
	 * Classes for Gradebook.
	 *
	 * @param mixed $course Course.
	 */
	public static function classes( $course ) {
		global $wpdb;
		$c     = Schema::table( 'classes' );
		$s     = Schema::table( 'schools' );
		$links = Schema::table( 'course_classes' );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT c.id,c.name,c.grade,s.name AS school_name,IF(l.id IS NULL,0,1) AS attached FROM %i c LEFT JOIN %i s ON s.id=c.school_id LEFT JOIN %i l ON l.class_id=c.id AND l.course_id=%d WHERE c.status=\'active\' AND (c.school_id=0 OR s.status=\'active\') ORDER BY c.name', $c, $s, $links, $course ), ARRAY_A );
	}
	/**
	 * Attach for Gradebook.
	 *
	 * @param mixed $course Course.
	 * @param mixed $class Class.
	 * @param mixed $enroll Enroll.
	 */
	public static function attach( $course, $class, $enroll ) {
		global $wpdb;
		$users       = self::roster( $class );
		$links       = Schema::table( 'course_classes' );
		$members     = Schema::table( 'course_class_students' );
		$enrollments = Schema::table( 'user_enrollment' );
		$new         = array();
		$result      = Service::transaction(
			function () use ( $wpdb, $course, $class, $enroll, $users, $links, $members, $enrollments, &$new ) {
				// Serialize repeated enroll/sync requests for this course, including overlapping classes.
				$wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM %i WHERE ID=%d FOR UPDATE', $wpdb->posts, $course ) );
				if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE course_id=%d AND class_id=%d', $links, $course, $class ) ) ) {
					Service::insert(
						'course_classes',
						array(
							'course_id'  => $course,
							'class_id'   => $class,
							'created_by' => get_current_user_id(),
							'created_at' => Service::now(),
						)
					);
				}
				$included = 0;
				$skipped  = 0;
				foreach ( $users as $user ) {
					$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT id,status FROM %i WHERE course_id=%d AND user_id=%d ORDER BY (status=\'enrolled\') DESC,id DESC LIMIT 1', $enrollments, $course, $user ), ARRAY_A );
					// A class sync must never restore a blocked/cancelled enrollment.
					if ( ! $existing && $enroll && get_user_meta( $user, '_ohmylms_banned_student', true ) !== 'yes' ) {
						$created = Enrollments::create(
							array(
								'course_id'  => $course,
								'user_id'    => $user,
								'status'     => 'enrolled',
								'progress'   => 'running',
								'start_date' => current_time( 'mysql' ),
							),
							array(
								'source'      => 'roster',
								'source_type' => 'class',
								'source_ref'  => $class,
								'class_id'    => $class,
							)
						);
						if ( false === $created ) {
							throw new \RuntimeException( 'Unable to save this record. It may already exist.', 409 ); }
						$new[]    = $user;
						$existing = array( 'status' => 'enrolled' );
					}
					if ( ! $existing || 'enrolled' !== $existing['status'] ) {
						$skipped++;
						continue; }
					if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE course_id=%d AND class_id=%d AND user_id=%d', $members, $course, $class, $user ) ) ) {
						Service::insert(
							'course_class_students',
							array(
								'course_id'  => $course,
								'class_id'   => $class,
								'user_id'    => $user,
								'created_at' => Service::now(),
							)
						);
					}
					$included++;
				}
				Service::audit( 0, 'course_class_attached', $class );
				return array(
					'included' => $included,
					'enrolled' => count( $new ),
					'skipped'  => $skipped,
				);
			}
		);
		foreach ( $new as $user ) {
			update_user_meta( $user, '_is_ohmylms_student', 'yes' );
			update_user_meta( $user, '_is_ohmylms_user', 'yes' );
			do_action( 'ohmylms_manual_student_enrollment', $user, $course );
		}
		return $result;
	}
	/**
	 * Detach for Gradebook.
	 *
	 * @param mixed $course Course.
	 * @param mixed $class Class.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function detach( $course, $class ) {
		global $wpdb;
		return Service::transaction(
			function () use ( $wpdb, $course, $class ) {
				$wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM %i WHERE ID=%d FOR UPDATE', $wpdb->posts, $course ) );
				foreach ( array( 'course_class_students', 'course_classes' ) as $table ) {
					if ( $wpdb->delete(
						Schema::table( $table ),
						array(
							'course_id' => $course,
							'class_id'  => $class,
						)
					) === false ) {
						throw new \RuntimeException( 'Unable to remove the class.' ); }
				}
				Service::audit( 0, 'course_class_detached', $class );
				return array( 'success' => true );
			}
		);
	}
	/**
	 * Items for Gradebook.
	 *
	 * @param mixed $course Course.
	 */
	public static function items( $course ) {
		global $wpdb;
		$r     = Schema::table( 'content_relationship' );
		$c     = Schema::table( 'chapter_relationship' );
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT p.ID AS id,p.post_title AS title,p.post_type AS type FROM %i r JOIN %i c ON c.chapter_id=r.chapter_id JOIN %i p ON p.ID=r.content_id WHERE c.course_id=%d AND p.post_status<>\'trash\' AND p.post_type IN (%s,%s) ORDER BY c.order_number,r.order_number,p.ID', $r, $c, $wpdb->posts, $course, OHMYLMS_QUIZ_CPT, OHMYLMS_ASSIGNMENT_CPT ), ARRAY_A );
		$items = array();
		foreach ( $rows as $item ) {
			$item['id'] = (int) $item['id'];
			if ( isset( $items[ $item['id'] ] ) ) {
				continue; }
			$item['max']          = OHMYLMS_QUIZ_CPT === $item['type'] ? self::quiz_max( $item['id'] ) : (float) get_post_meta( $item['id'], '_total_points', true );
			$item['type']         = OHMYLMS_QUIZ_CPT === $item['type'] ? 'quiz' : 'assignment';
			$items[ $item['id'] ] = $item;
		}
		return array_values( $items );
	}
	/**
	 * Maximum for a quiz nobody has attempted yet: the published revision's total when the
	 * versioned engine delivers it (what a new attempt is marked out of), else the live total.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 */
	private static function quiz_max( $quiz_id ) {
		if ( class_exists( \OhMyLMS\Assessment\Engine::class ) && \OhMyLMS\Assessment\Schema::ready() && \OhMyLMS\Assessment\Engine::versioned() ) {
			$revision = \OhMyLMS\Assessment\RevisionPublisher::latest( $quiz_id );
			if ( $revision ) {
				return (float) $revision['total_marks']; }
		}
		return (float) ohmylms_get_quiz( $quiz_id )->get_total_marks();
	}
	/**
	 * Read for Gradebook.
	 *
	 * @param mixed $course Course.
	 */
	public static function read( $course ) {
		global $wpdb;
		$items = self::items( $course );
		$users = Enrollments::listed_students( $course );
		$q     = Schema::table( 'quiz_attempts' );
		$a     = Schema::table( 'quiz_attempts_answers' );
		// quiz_attempts.total stores earned points, not the maximum possible points.
		$quizzes     = $wpdb->get_results( $wpdb->prepare( 'SELECT q.id,q.student_id AS user_id,q.quiz_id AS content_id,q.status,SUM(a.question_marks) AS max,COALESCE(SUM(a.achive_mark),0) AS score FROM %i q LEFT JOIN %i a ON a.quiz_attempt_id=q.id WHERE q.course_id=%d GROUP BY q.id,q.student_id,q.quiz_id,q.status ORDER BY q.id DESC', $q, $a, $course ), ARRAY_A );
		$a           = Schema::table( 'assignment_attempts' );
		$assignments = $wpdb->get_results( $wpdb->prepare( 'SELECT id,user_id,assignment_id AS content_id,status,score FROM %i WHERE course_id=%d ORDER BY id DESC', $a, $course ), ARRAY_A );
		$o           = Schema::table( 'gradebook_overrides' );
		$overrides   = $wpdb->get_results( $wpdb->prepare( 'SELECT user_id,content_id,score,max_score AS max,note FROM %i WHERE course_id=%d', $o, $course ), ARRAY_A );
		$latest      = array();
		$manual      = array();
		foreach ( array_merge( $quizzes, $assignments ) as $attempt ) {
			$key = $attempt['user_id'] . ':' . $attempt['content_id'];
			if ( ! isset( $latest[ $key ] ) || ( ! in_array( $latest[ $key ]['status'], array( 'completed', 'passed', 'failed' ), true ) && in_array( $attempt['status'], array( 'completed', 'passed', 'failed' ), true ) ) ) {
				$latest[ $key ] = $attempt; }
		}
		foreach ( $overrides as $override ) {
			$manual[ $override['user_id'] . ':' . $override['content_id'] ] = $override; }
		foreach ( $users as &$user ) {
			$user['id']    = (int) $user['id'];
			$user['cells'] = array();
			foreach ( $items as $item ) {
				$key                          = $user['id'] . ':' . $item['id'];
				$attempt                      = $latest[ $key ] ?? null;
				$override                     = $manual[ $key ] ?? null;
				$graded                       = $attempt && in_array( $attempt['status'], array( 'completed', 'passed', 'failed' ), true );
				$max                          = $override ? (float) $override['max'] : ( $attempt && isset( $attempt['max'] ) ? (float) $attempt['max'] : $item['max'] );
				$user['cells'][ $item['id'] ] = array(
					'score'  => $override ? (float) $override['score'] : ( $graded ? max( 0, (float) $attempt['score'] ) : null ),
					'max'    => $max,
					'status' => $override ? 'manual' : ( $graded ? 'graded' : ( $attempt ? 'pending' : 'missing' ) ),
					'note'   => $override['note'] ?? '',
				);
			}
			$user['total'] = GradebookMath::total( $user['cells'] );
		}
		unset( $user );
		$links   = Schema::table( 'course_classes' );
		$c       = Schema::table( 'classes' );
		$s       = Schema::table( 'schools' );
		$m       = Schema::table( 'course_class_students' );
		$classes = $wpdb->get_results( $wpdb->prepare( 'SELECT c.id,c.name,c.status,s.name AS school_name FROM %i l JOIN %i c ON c.id=l.class_id LEFT JOIN %i s ON s.id=c.school_id WHERE l.course_id=%d ORDER BY c.name', $links, $c, $s, $course ), ARRAY_A );
		$members = $wpdb->get_results( $wpdb->prepare( 'SELECT class_id,user_id FROM %i WHERE course_id=%d', $m, $course ), ARRAY_A );
		$grouped = array();
		foreach ( $classes as &$class ) {
			$ids               = array_map(
				'intval',
				array_column(
					array_filter(
						$members,
						function ( $m ) use ( $class ) {
							return (int) $m['class_id'] === (int) $class['id'];
						}
					),
					'user_id'
				)
			);
			$class['students'] = array_values(
				array_filter(
					$users,
					function ( $u ) use ( $ids ) {
						return in_array( $u['id'], $ids, true );
					}
				)
			);
			$grouped           = array_merge( $grouped, $ids );
			$class['percent']  = GradebookMath::average(
				array_map(
					function ( $u ) {
						return $u['total']['percent'];
					},
					$class['students']
				)
			);
			$class['cells']    = array();
			foreach ( $items as $item ) {
				$class['cells'][ $item['id'] ] = GradebookMath::average(
					array_map(
						function ( $u ) use ( $item ) {
							$cell = $u['cells'][ $item['id'] ];
							return null !== $cell['score'] && 0 < $cell['max'] ? 100 * $cell['score'] / $cell['max'] : null;
						},
						$class['students']
					)
				);
			}
		}
		unset( $class );
		return array(
			'course'        => array(
				'id'   => $course,
				'name' => get_the_title( $course ),
			),
			'items'         => $items,
			'classes'       => $classes,
			'students'      => array_values(
				array_filter(
					$users,
					function ( $u ) use ( $grouped ) {
						return ! in_array( $u['id'], $grouped, true );
					}
				)
			),
			'student_count' => count( $users ),
			'average'       => GradebookMath::average(
				array_map(
					function ( $u ) {
						return $u['total']['percent'];
					},
					$users
				)
			),
		);
	}
	/**
	 * Grade for Gradebook.
	 *
	 * @param mixed $course Course.
	 * @param mixed $r R.
	 * @param mixed $reset Reset.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function grade( $course, $r, $reset ) {
		global $wpdb;
		$user    = absint( $r['user_id'] );
		$content = absint( $r['content_id'] );
		Access::require_access( Enrollments::is_listed( $user, $course ) );
		$items = array_values(
			array_filter(
				self::items( $course ),
				function ( $item ) use ( $content ) {
					return $item['id'] === $content;
				}
			)
		);
		Service::need( $items, 'This assessment does not belong to the course.' );
		$table = Schema::table( 'gradebook_overrides' );
		$key   = array(
			'course_id'  => $course,
			'content_id' => $content,
			'user_id'    => $user,
		);
		if ( $reset ) {
			$saved = $wpdb->delete( $table, $key ); } else {
			$max   = $items[0]['max'];
			$score = GradebookMath::validate_score( $r['score'], $max );
			$saved = $wpdb->replace(
				$table,
				array_merge(
					$key,
					array(
						'score'      => $score,
						'max_score'  => $max,
						'note'       => mb_substr( sanitize_textarea_field( $r['note'] ?? '' ), 0, 2000 ),
						'updated_by' => get_current_user_id(),
						'updated_at' => Service::now(),
					)
				)
			);
			}
			if ( false === $saved ) {
				throw new \RuntimeException( 'Unable to save this grade.' ); }
			Service::audit( 0, $reset ? 'gradebook_grade_reset' : 'gradebook_grade_saved', $content );
			return array( 'success' => true );
	}
}
