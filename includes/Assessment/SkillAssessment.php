<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Mixed-skill assessments: a quiz, unit test or course challenge built from a list of skills.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assessment;

use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Builds on what already exists: a quiz whose question pools draw a few approved questions for each
 * skill (Pools), published as a frozen revision (RevisionPublisher). Each attempt draws its own questions
 * and every question keeps its own skill mapping, so grades feed skill evidence and, when the Khan level
 * rules are on, move each skill up or down one step.
 */
final class SkillAssessment {

	/** Most skills one assessment can cover. */
	const MAX_SKILLS = 40;

	/** Most questions one assessment can draw. */
	const MAX_QUESTIONS = 60;

	/**
	 * Create (and optionally publish) a mixed-skill assessment.
	 *
	 * @param array $args title, style (quiz, unit-test, course-challenge), skills (term IDs), per_skill,
	 *                    chapter_id (optional, places it in that unit), publish (bool), time_limit (minutes).
	 * @return array|\WP_Error id, title, style, questions, per_skill, skills, status, revision.
	 */
	public static function create( array $args ) {
		$style = (string) ( $args['style'] ?? 'quiz' );
		if ( ! in_array( $style, AssessmentSettings::STYLES, true ) ) {
			return self::error( __( 'Choose quiz, unit-test or course-challenge.', 'ohmylms' ) ); }
		$terms = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $args['skills'] ?? array() ) ) ) ) );
		if ( ! $terms || count( $terms ) > self::MAX_SKILLS ) {
			/* translators: %d: most skills */
			return self::error( sprintf( __( 'Choose between 1 and %d skills.', 'ohmylms' ), self::MAX_SKILLS ) ); }
		$per_skill = (int) ( $args['per_skill'] ?? 1 );
		if ( $per_skill < 1 || $per_skill > 10 ) {
			return self::error( __( 'Draw between 1 and 10 questions for each skill.', 'ohmylms' ) ); }
		if ( count( $terms ) * $per_skill > self::MAX_QUESTIONS ) {
			/* translators: %d: most questions */
			return self::error( sprintf( __( 'That would be more than %d questions. Choose fewer skills or fewer questions for each.', 'ohmylms' ), self::MAX_QUESTIONS ) ); }
		$rules = array();
		foreach ( $terms as $term_id ) {
			$term = get_term( $term_id, Taxonomy::NAME );
			if ( ! $term || is_wp_error( $term ) ) {
				return self::error( __( 'One of the skills does not exist.', 'ohmylms' ) ); }
			$rules[] = array(
				'term_id' => $term_id,
				'title'   => $term->name,
				'count'   => $per_skill,
				'marks'   => 1,
			);
		}
		$title = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
		if ( '' === $title ) {
			$first = get_term( $terms[0], Taxonomy::NAME );
			$title = self::label( $style ) . ': ' . $first->name;
		}
		$publish = ! empty( $args['publish'] );
		$chapter = (int) ( $args['chapter_id'] ?? 0 );
		if ( $chapter && ! self::can_place( $chapter ) ) {
			return new \WP_Error( 'ohmylms_skill_assessment_unit', __( 'You cannot add content to that unit.', 'ohmylms' ), array( 'status' => 403 ) ); }
		$created = rest_do_request(
			self::request(
				'POST',
				'/ohmylms/v1/quiz',
				array(
					'name'   => $title,
					'status' => 'draft',
				)
			)
		);
		if ( $created->is_error() ) {
			return $created->as_error(); }
		$quiz_id  = (int) $created->get_data()['id'];
		$failed   = static function ( $error ) use ( $quiz_id ) {
			wp_delete_post( $quiz_id, true );
			return $error;
		};
		$settings = array(
			'allow_attempts' => 99,
			'passing_grade'  => array(
				'enabled' => true,
				'value'   => 0,
			),
		);
		$minutes  = (int) ( $args['time_limit'] ?? 0 );
		if ( $minutes > 0 ) {
			$settings['time_limit'] = array(
				'type'  => 'minutes',
				'value' => min( 600, $minutes ),
			); }
		$updated = rest_do_request( self::request( 'PUT', '/ohmylms/v1/quiz/' . $quiz_id, array( 'settings' => $settings ) ) );
		if ( $updated->is_error() ) {
			return $failed( $updated->as_error() ); }
		$saved = AssessmentSettings::save( $quiz_id, array( 'style' => $style ) );
		if ( is_wp_error( $saved ) ) {
			return $failed( $saved ); }
		$pools = Pools::save( $quiz_id, $rules );
		if ( is_wp_error( $pools ) ) {
			return $failed( $pools ); }
		$revision = null;
		if ( $publish ) {
			$revision = RevisionPublisher::publish( $quiz_id );
			if ( is_wp_error( $revision ) ) {
				return $failed( $revision ); }
			wp_update_post(
				array(
					'ID'          => $quiz_id,
					'post_status' => 'publish',
				)
			);
		}
		if ( $chapter ) {
			self::place( $quiz_id, $chapter ); }
		return array(
			'id'        => $quiz_id,
			'title'     => $title,
			'style'     => $style,
			'skills'    => count( $terms ),
			'per_skill' => $per_skill,
			'questions' => count( $terms ) * $per_skill,
			'status'    => $publish ? 'publish' : 'draft',
			'revision'  => is_array( $revision ) ? ( $revision['id'] ?? $revision ) : null,
			'url'       => get_permalink( $quiz_id ),
		);
	}

	/**
	 * The skills a unit teaches: skills on its lessons and the skills its quizzes are connected to, in unit
	 * order, each with how many different questions the bank can supply for it.
	 *
	 * @param int $chapter_id Unit (chapter) ID.
	 * @return array[] id, code, name, available.
	 */
	public static function unit_skills( $chapter_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ohmylms_content_relationship';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh read of the unit's contents.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT content_id, content_type FROM %i WHERE chapter_id=%d ORDER BY order_number, id', $table, (int) $chapter_id ), ARRAY_A );
		$ids  = array();
		foreach ( $rows as $row ) {
			$found = 'lesson' === $row['content_type']
				? wp_get_object_terms( (int) $row['content_id'], Taxonomy::NAME, array( 'fields' => 'ids' ) )
				: ( 'quiz' === $row['content_type'] ? \OhMyLMS\Quiz\QuizSkills::get( (int) $row['content_id'] ) : array() );
			foreach ( is_wp_error( $found ) ? array() : $found as $term_id ) {
				$ids[ (int) $term_id ] = true;
			}
		}
		$skills = array();
		foreach ( \OhMyLMS\QuestionBank\SkillCatalogue::describe( array_keys( $ids ) ) as $skill ) {
			$skills[] = array(
				'id'        => $skill['id'],
				'code'      => $skill['code'],
				'name'      => $skill['name'],
				'available' => Pools::available( $skill['id'] ),
			);
		}
		return $skills;
	}

	/**
	 * Whether a unit already holds an assessment of a style.
	 *
	 * @param int    $chapter_id Unit ID.
	 * @param string $style      unit-test or course-challenge.
	 * @return bool
	 */
	public static function unit_has_style( $chapter_id, $style ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh read of the unit's quizzes.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT content_id FROM %i WHERE chapter_id=%d AND content_type='quiz'", $wpdb->prefix . 'ohmylms_content_relationship', (int) $chapter_id ) );
		foreach ( $ids as $quiz_id ) {
			if ( 'publish' === get_post_status( (int) $quiz_id ) || 'draft' === get_post_status( (int) $quiz_id ) ) {
				if ( AssessmentSettings::get( (int) $quiz_id )['style'] === $style ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Create a unit test for every unit of a course that has none, and optionally a course challenge.
	 *
	 * Each unit test draws from the skills its lessons and quizzes teach. A skill with fewer different
	 * questions than asked for is left out (and listed) instead of stopping the whole run. Units with no
	 * skills, or with no skill that has enough questions, are skipped with the reason.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $args      per_skill (1-10), challenge (bool), publish (bool).
	 * @return array|\WP_Error created, skipped, left_out (skill names), managed (the course uses a learning program).
	 */
	public static function create_for_course( $course_id, array $args = array() ) {
		$course_id = absint( $course_id );
		if ( ! $course_id || OHMYLMS_COURSE_CPT !== get_post_type( $course_id ) ) {
			return new \WP_Error( 'ohmylms_skill_assessment_course', __( 'Course not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $course_id ) ) {
			return new \WP_Error( 'ohmylms_skill_assessment_course', __( 'You cannot edit this course.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$per_skill = (int) ( $args['per_skill'] ?? 1 );
		if ( $per_skill < 1 || $per_skill > 10 ) {
			return self::error( __( 'Draw between 1 and 10 questions for each skill.', 'ohmylms' ) );
		}
		$publish = ! empty( $args['publish'] );
		$cap     = max( 1, min( self::MAX_SKILLS, intdiv( self::MAX_QUESTIONS, $per_skill ) ) );
		$report  = array(
			'created'  => array(),
			'skipped'  => array(),
			'left_out' => array(),
			'managed'  => (bool) get_post_meta( $course_id, \OhMyLMS\Learning\CourseProgram::CURRENT, true ),
		);
		$pool    = array();
		$last    = 0;
		foreach ( (array) ohmylms_get_course( $course_id )->get_chapters() as $chapter ) {
			$chapter_id = (int) $chapter['id'];
			$last       = $chapter_id;
			$skills     = self::unit_skills( $chapter_id );
			foreach ( $skills as $skill ) {
				$pool[ $skill['id'] ] = $skill;
			}
			if ( self::unit_has_style( $chapter_id, 'unit-test' ) ) {
				$report['skipped'][] = array(
					'unit'   => $chapter['name'],
					'reason' => __( 'It already has a unit test.', 'ohmylms' ),
				);
				continue;
			}
			$result = self::build_from( $chapter['name'] . ': ' . self::label( 'unit-test' ), 'unit-test', $skills, $per_skill, $cap, $chapter_id, $publish, $report );
			if ( is_wp_error( $result ) ) {
				$report['skipped'][] = array(
					'unit'   => $chapter['name'],
					'reason' => $result->get_error_message(),
				);
			} else {
				$report['created'][] = $result;
			}
		}
		if ( ! empty( $args['challenge'] ) && $last ) {
			$has = false;
			foreach ( (array) ohmylms_get_course( $course_id )->get_chapters() as $chapter ) {
				$has = $has || self::unit_has_style( (int) $chapter['id'], 'course-challenge' );
			}
			if ( $has ) {
				$report['skipped'][] = array(
					'unit'   => self::label( 'course-challenge' ),
					'reason' => __( 'The course already has a course challenge.', 'ohmylms' ),
				);
			} else {
				$result = self::build_from( get_the_title( $course_id ) . ': ' . self::label( 'course-challenge' ), 'course-challenge', array_values( $pool ), $per_skill, $cap, $last, $publish, $report );
				if ( is_wp_error( $result ) ) {
					$report['skipped'][] = array(
						'unit'   => self::label( 'course-challenge' ),
						'reason' => $result->get_error_message(),
					);
				} else {
					$report['created'][] = $result;
				}
			}
		}
		$report['left_out'] = array_values( array_unique( $report['left_out'] ) );
		return $report;
	}

	/**
	 * Build one assessment from the skills that have enough questions, noting the ones that do not.
	 *
	 * @param string  $title      Title.
	 * @param string  $style      Style.
	 * @param array[] $skills     Skills from unit_skills().
	 * @param int     $per_skill  Questions per skill.
	 * @param int     $cap        Most skills one assessment may cover.
	 * @param int     $chapter_id Unit to place it in.
	 * @param bool    $publish    Publish it.
	 * @param array   $report     Report being built; skills left out are added to left_out.
	 * @return array|\WP_Error
	 */
	private static function build_from( $title, $style, array $skills, $per_skill, $cap, $chapter_id, $publish, array &$report ) {
		if ( ! $skills ) {
			return self::error( __( 'No skills are connected to this unit yet.', 'ohmylms' ) );
		}
		$eligible = array();
		foreach ( $skills as $skill ) {
			if ( (int) $skill['available'] >= $per_skill ) {
				$eligible[] = $skill;
			} else {
				$report['left_out'][] = $skill['name'];
			}
		}
		if ( ! $eligible ) {
			return self::error( __( 'None of its skills have enough approved questions yet.', 'ohmylms' ) );
		}
		return self::create(
			array(
				'title'      => $title,
				'style'      => $style,
				'skills'     => array_column( array_slice( $eligible, 0, $cap ), 'id' ),
				'per_skill'  => $per_skill,
				'chapter_id' => $chapter_id,
				'publish'    => $publish,
			)
		);
	}

	/**
	 * Whether the current user may add content to a unit: it must belong to a course they can edit.
	 *
	 * @param int $chapter_id Unit (chapter) ID.
	 * @return bool
	 */
	public static function can_place( $chapter_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh ownership check.
		$course = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT course_id FROM %i WHERE chapter_id=%d LIMIT 1', $wpdb->prefix . 'ohmylms_chapter_relationship', (int) $chapter_id ) );
		return $course > 0 && current_user_can( 'edit_post', $course );
	}

	/**
	 * Put the assessment at the end of a unit (chapter).
	 *
	 * @param int $quiz_id    Quiz.
	 * @param int $chapter_id Chapter.
	 */
	private static function place( $quiz_id, $chapter_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ohmylms_content_relationship';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Appends to the unit's content order.
		$next = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(order_number) FROM %i WHERE chapter_id=%d', $table, (int) $chapter_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Same relationship row the course builder writes.
		$wpdb->insert(
			$table,
			array(
				'chapter_id'   => (int) $chapter_id,
				'content_id'   => (int) $quiz_id,
				'content_type' => 'quiz',
				'order_number' => $next,
			)
		);
	}

	/**
	 * Learner-facing name of a style.
	 *
	 * @param string $style Style.
	 * @return string
	 */
	public static function label( $style ) {
		return array(
			'quiz'             => __( 'Quiz', 'ohmylms' ),
			'unit-test'        => __( 'Unit test', 'ohmylms' ),
			'course-challenge' => __( 'Course challenge', 'ohmylms' ),
		)[ $style ] ?? __( 'Quiz', 'ohmylms' );
	}

	/**
	 * Internal request that runs as the current user, so the usual permissions and validation apply.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route.
	 * @param array  $body   Body.
	 * @return \WP_REST_Request
	 */
	private static function request( $method, $route, array $body ) {
		$request = new \WP_REST_Request( $method, $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		return $request;
	}

	/**
	 * Error with a 400 status.
	 *
	 * @param string $message Message.
	 * @return \WP_Error
	 */
	private static function error( $message ) {
		return new \WP_Error( 'ohmylms_skill_assessment', $message, array( 'status' => 400 ) );
	}
}
