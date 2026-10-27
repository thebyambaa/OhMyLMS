<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Teacher skill-performance reports, kept separate from grades. Access needs an explicit
 * scope: a course the viewer may edit, or a class they teach (Schools\Access). Grade
 * overrides never become skill evidence.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Schools\Access;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Performance\Guidance;
use OhMyLMS\Performance\HelpNow;
use OhMyLMS\Performance\Insights;
use OhMyLMS\Performance\Levels;
use OhMyLMS\Performance\Snapshot;
use OhMyLMS\Skills\Recommendations;
use OhMyLMS\Skills\ReportData;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Skills\UnitReport;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Teacher skill-performance reports, kept separate from grades. Access needs an explicit
 * scope: a course the viewer may edit, or a class they teach (Schools\Access). Grade
 * overrides never become skill evidence.
 */
class SkillReportController extends RestController {
	/**
	 * Register routes for Skill report controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/reports/skills',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'matrix' ),
					'permission_callback' => array( $this, 'scope_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/reports/skills/help-now',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'help_now' ),
					'permission_callback' => array( $this, 'scope_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/reports/skills/students/(?P<student>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'student' ),
					'permission_callback' => array( $this, 'student_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/reports/skills/students/(?P<student>[\d]+)/guidance',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'guidance' ),
					'permission_callback' => array( $this, 'student_permission' ),
					'args'                => array(
						'limit'   => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 20,
							'default' => 6,
						),
						'subject' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/reports/skills/students/(?P<student>[\d]+)/exam-target',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'exam_target' ),
					'permission_callback' => array( $this, 'student_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_exam_target' ),
					'permission_callback' => array( $this, 'own_permission' ),
					'args'                => array(
						'syllabus_id' => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'date'        => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_exam_target' ),
					'permission_callback' => array( $this, 'own_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/reports/skills/students/(?P<student>[\d]+)/map',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'skill_map' ),
					'permission_callback' => array( $this, 'student_permission' ),
					'args'                => array(
						'subject' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/reports/skills/students/(?P<student>[\d]+)/placement',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'placement' ),
					'permission_callback' => array( $this, 'student_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/reports/skills/students/(?P<student>[\d]+)/insight',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'insight' ),
					'permission_callback' => array( $this, 'insight_permission' ),
					'args'                => array(
						'audience' => array(
							'type'    => 'string',
							'enum'    => Insights::AUDIENCES,
							'default' => 'student',
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/reports/skills/students/(?P<student>[\d]+)/rebuild',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rebuild' ),
					'permission_callback' => static function () {
														return current_user_can( 'manage_options' ); },
				),
			)
		);
	}

	/** Learners in the requested scope, or WP_Error.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	private function scope_students( WP_REST_Request $request ) {
		global $wpdb;
		if ( (int) $request['class_id'] ) {
			if ( ! class_exists( Access::class ) || ! Access::classroom( (int) $request['class_id'] ) ) {
				return new WP_Error( 'ohmylms_forbidden', __( 'You cannot view this class.', 'ohmylms' ), array( 'status' => 403 ) ); }
			return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM %i WHERE class_id=%d AND role=\'student\' AND status=\'active\'', $wpdb->prefix . 'ohmylms_class_memberships', (int) $request['class_id'] ) ) );
		}
		$course = (int) $request['course_id'];
		if ( ! $course || get_post_type( $course ) !== OHMYLMS_COURSE_CPT ) {
			return new WP_Error( 'ohmylms_scope_required', __( 'Choose a course or class.', 'ohmylms' ), array( 'status' => 400 ) ); }
		if ( ! current_user_can( 'edit_post', $course ) ) {
			return new WP_Error( 'ohmylms_forbidden', __( 'You cannot view this course.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) ); }
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT user_id FROM %i WHERE course_id=%d AND status=\'enrolled\'', $wpdb->prefix . 'ohmylms_user_enrollment', $course ) ) );
	}

	/**
	 * Scope permission for Skill report controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function scope_permission( WP_REST_Request $request ) {
		if ( ! Schema::ready() ) {
			return new WP_Error( 'ohmylms_reports_unavailable', __( 'Reports are not installed yet.', 'ohmylms' ), array( 'status' => 503 ) ); }
		$students = $this->scope_students( $request );
		return is_wp_error( $students ) ? $students : true;
	}

	/**
	 * Student permission for Skill report controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function student_permission( WP_REST_Request $request ) {
		$access = $this->student_access( $request );
		return is_wp_error( $access ) ? $access : true;
	}

	/**
	 * How the viewer reaches this learner: 'full' (the learner, an administrator or a
	 * teacher/course scope) or 'guardian' (summary and suggestions only, no answer log).
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	private function student_access( WP_REST_Request $request ) {
		if ( ! Schema::ready() ) {
			return new WP_Error( 'ohmylms_reports_unavailable', __( 'Reports are not installed yet.', 'ohmylms' ), array( 'status' => 503 ) ); }
		$student = (int) $request['student'];
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'ohmylms_forbidden', __( 'Sign in to view skills.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) ); }
		if ( $student === get_current_user_id() || current_user_can( 'manage_options' ) ) {
			return 'full'; }
		// Guardians and teachers see a learner only through an explicit school relationship.
		$school = (int) $request['school_id'];
		if ( $school && class_exists( Access::class ) ) {
			if ( Access::school( $school, array( 'school_admin', 'teacher' ) ) && Access::student( $school, $student ) ) {
				return 'full'; }
			if ( Access::guardian( $school, $student ) ) {
				return 'guardian'; }
		}
		if ( ! (int) $request['class_id'] && ! (int) $request['course_id'] ) {
			return new WP_Error( 'ohmylms_forbidden', __( 'This learner is not in your scope.', 'ohmylms' ), array( 'status' => 403 ) ); }
		$students = $this->scope_students( $request );
		if ( is_wp_error( $students ) ) {
			return $students; }
		return in_array( $student, $students, true ) ? 'full' : new WP_Error( 'ohmylms_forbidden', __( 'This learner is not in your scope.', 'ohmylms' ), array( 'status' => 403 ) );
	}

	/**
	 * Matrix for Skill report controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function matrix( WP_REST_Request $request ) {
		Evidence::process( 100 );
		$students = $this->scope_students( $request );
		if ( is_wp_error( $students ) ) {
			return $students; }
		$course  = (int) $request['course_id'];
		$class   = (int) $request['class_id'];
		$courses = $class ? ReportData::enrolled_courses( $students ) : ( $course ? array( $course ) : array() );
		$extra   = array();
		if ( $class && $course ) {
			// A class report only narrows to a course its learners are enrolled in.
			if ( ! in_array( $course, $courses, true ) ) {
				return new WP_Error( 'ohmylms_forbidden', __( 'This course is not part of the class.', 'ohmylms' ), array( 'status' => 403 ) ); }
			$all     = $courses;
			$courses = array( $course );
		}
		if ( $course && ! $class ) {
			$extra = array_map( 'intval', wp_get_object_terms( $course, Taxonomy::NAME, array( 'fields' => 'ids' ) ) );
		}
		$data = ReportData::matrix( $students, $courses, (int) $request['chapter_id'], $extra );
		if ( is_wp_error( $data ) ) {
			return $data; }
		$data['course_id'] = $course;
		if ( $class ) {
			$data['courses'] = array_map(
				static function ( $id ) {
					return array(
						'id'    => $id,
						'title' => wp_strip_all_tags( get_the_title( $id ) ),
					);
				},
				$all ?? $courses
			);
		}
		return rest_ensure_response( $data );
	}

	/**
	 * One learner's skills with Khan display levels and unit ids, optionally narrowed to a unit of a
	 * published course. Only unit names are added; it never widens what the viewer may see.
	 *
	 * @param WP_REST_Request $request Report request.
	 * @param array           $skills Evidence::summary rows.
	 * @return array|WP_Error
	 */
	private function unit_view( WP_REST_Request $request, array $skills ) {
		$course  = (int) $request['course_id'];
		$chapter = (int) $request['chapter_id'];
		$courses = $course && OHMYLMS_COURSE_CPT === get_post_type( $course ) && 'publish' === get_post_status( $course ) ? array( $course ) : array();
		$context = ReportData::context( $courses );
		if ( $chapter && ! in_array( $chapter, array_column( $context['units'], 'id' ), true ) ) {
			return new WP_Error( 'ohmylms_unit_unknown', __( 'This unit does not belong to the chosen course.', 'ohmylms' ), array( 'status' => 400 ) ); }
		$display = Snapshot::student( (int) $request['student'], array_column( $skills, 'id' ) );
		foreach ( $skills as &$skill ) {
			$skill['unit_id']       = (int) ( $context['map'][ $skill['id'] ] ?? 0 );
			$skill['display']       = $display[ $skill['id'] ]['key'] ?? 'not-started';
			$skill['display_label'] = $display[ $skill['id'] ]['label'] ?? Levels::label( 'not-started' );
		}
		unset( $skill );
		if ( $chapter ) {
			$keep   = UnitReport::filter( array_column( $skills, 'id' ), $context['map'], $chapter );
			$skills = array_values(
				array_filter(
					$skills,
					static function ( $skill ) use ( $keep ) {
						return in_array( $skill['id'], $keep, true );
					}
				)
			);
		}
		return array(
			'skills' => $skills,
			'units'  => UnitReport::with_counts( $context['units'], $context['map'] ),
			'unit'   => $chapter,
		);
	}

	/**
	 * Student for Skill report controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function student( WP_REST_Request $request ) {
		global $wpdb;
		$student = (int) $request['student'];
		Evidence::process( 100 );
		$view = $this->unit_view( $request, Evidence::summary( $student ) );
		if ( is_wp_error( $view ) ) {
			return $view; }
		if ( $this->student_access( $request ) !== 'full' ) {
			return rest_ensure_response(
				array(
					'skills'          => $view['skills'],
					'units'           => $view['units'],
					'unit'            => $view['unit'],
					'recommendations' => Recommendations::for_student( $student ),
					'evidence'        => array(),
				)
			);
		}
		$evidence = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT e.term_id, e.part_id, e.role, e.awarded, e.available, e.independent, e.first_try, e.difficulty, e.source_type, e.source_id, e.question_id, e.version_id, e.evidence_at, e.superseded
             FROM %i e WHERE e.student_id=%d ORDER BY e.evidence_at DESC, e.id DESC LIMIT 200',
				Schema::table( 'skill_evidence' ),
				$student
			),
			ARRAY_A
		);
		foreach ( $evidence as &$row ) {
			$row['question'] = get_the_title( (int) $row['question_id'] ); }
		return rest_ensure_response(
			array(
				'skills'          => $view['skills'],
				'units'           => $view['units'],
				'unit'            => $view['unit'],
				'recommendations' => Recommendations::for_student( $student ),
				'insights'        => Insights::available(),
				'evidence'        => $evidence,
			)
		);
	}

	/**
	 * Guidance from the learner's skill states. It is a summary, so guardians see it too.
	 *
	 * @param WP_REST_Request $request Request with student, limit and subject.
	 * @return \WP_REST_Response
	 */
	/**
	 * Who needs help now: the learners of a course or class who are stuck, grouped by the prerequisite to reteach.
	 *
	 * @param WP_REST_Request $request Request with course_id or class_id.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function help_now( WP_REST_Request $request ) {
		Evidence::process( 100 );
		$students = $this->scope_students( $request );
		if ( is_wp_error( $students ) ) {
			return $students;
		}
		return rest_ensure_response( HelpNow::for_students( $students ) );
	}

	/**
	 * Own permission for Skill report controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function own_permission( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'ohmylms_forbidden', __( 'Sign in to set your exam.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return (int) $request['student'] === get_current_user_id() || current_user_can( 'manage_options' ) ? true : new WP_Error( 'ohmylms_forbidden', __( 'Only the learner can set their exam.', 'ohmylms' ), array( 'status' => 403 ) );
	}

	/**
	 * The exam the learner is preparing for, with the exams to choose from.
	 *
	 * @param WP_REST_Request $request Request with student.
	 * @return \WP_REST_Response
	 */
	public function exam_target( WP_REST_Request $request ) {
		return rest_ensure_response(
			array(
				'target'  => Guidance::target( (int) $request['student'] ),
				'options' => Guidance::targets(),
			)
		);
	}

	/**
	 * Set the exam and date.
	 *
	 * @param WP_REST_Request $request Request with student, syllabus_id and date.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function save_exam_target( WP_REST_Request $request ) {
		$saved = Guidance::set_target( (int) $request['student'], (int) $request['syllabus_id'], (string) $request['date'] );
		return is_wp_error( $saved ) ? $saved : rest_ensure_response( array( 'target' => $saved ) );
	}

	/**
	 * Stop preparing for an exam.
	 *
	 * @param WP_REST_Request $request Request with student.
	 * @return \WP_REST_Response
	 */
	public function clear_exam_target( WP_REST_Request $request ) {
		Guidance::set_target( (int) $request['student'], 0, '' );
		return rest_ensure_response( array( 'target' => null ) );
	}

	/**
	 * Guidance for Skill report controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function guidance( WP_REST_Request $request ) {
		Evidence::process( 100 );
		return rest_ensure_response( Snapshot::guidance( (int) $request['student'], (int) $request['limit'], (string) $request['subject'] ) );
	}

	/**
	 * The learner's skill map: the subjects they have started and, for one, the shared skills by
	 * domain and grade with their levels. A summary, so guardians see it too.
	 *
	 * @param WP_REST_Request $request Request with student and subject.
	 * @return \WP_REST_Response
	 */
	public function skill_map( WP_REST_Request $request ) {
		Evidence::process( 100 );
		return rest_ensure_response( \OhMyLMS\Curriculum\SkillMap::for_student( (int) $request['student'], (string) $request['subject'] ) );
	}

	/**
	 * The learner's latest placement in each subject: working grade per domain and where to start.
	 *
	 * @param WP_REST_Request $request Request with student.
	 * @return \WP_REST_Response
	 */
	public function placement( WP_REST_Request $request ) {
		return rest_ensure_response( \OhMyLMS\Placement\Placement::results( (int) $request['student'] ) );
	}

	/** Learners write about themselves; staff with full access to a learner write about them. Guardians get the summary only.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function insight_permission( WP_REST_Request $request ) {
		$access = $this->student_access( $request );
		if ( is_wp_error( $access ) ) {
			return $access; }
		$own = (int) $request['student'] === get_current_user_id();
		if ( 'full' !== $access || ( 'student' === $request['audience'] ) !== $own ) {
			return new WP_Error( 'ohmylms_forbidden', __( 'You cannot ask for this note.', 'ohmylms' ), array( 'status' => 403 ) ); }
		return true;
	}

	/** An AI-written note about the learner's skills; the levels themselves never come from it.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function insight( WP_REST_Request $request ) {
		Evidence::process( 100 );
		$note = Insights::note( (int) $request['student'], (string) $request['audience'], get_current_user_id() );
		return is_wp_error( $note ) ? $note : rest_ensure_response( $note );
	}

	/**
	 * Rebuild for Skill report controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function rebuild( WP_REST_Request $request ) {
		return rest_ensure_response(
			array(
				'processed' => Evidence::rebuild( (int) $request['student'] ),
				'skills'    => Evidence::summary( (int) $request['student'] ),
			)
		);
	}
}
