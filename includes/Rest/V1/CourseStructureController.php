<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping.
/**
 * One course structure boundary for legacy, Content Hub and program REST writers.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseStructure;

defined( 'ABSPATH' ) || exit;

/** Adapt existing endpoint payloads; retain their route names and successful response shapes. */
class CourseStructureController extends RestController {

	/** Register the common read and wrap existing handlers after their permission checks. */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/structure',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, 'show' ),
				),
				array(
					'methods'             => 'PUT',
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, 'save' ),
				),
			)
		);
		foreach ( array(
			'preview'   => 'GET',
			'resources' => 'GET',
			'publish'   => 'POST',
		) as $action => $method ) {
			register_rest_route(
				$this->namespace,
				'/courses/(?P<id>\d+)/structure/' . $action,
				array(
					'methods'             => $method,
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, $action ),
				)
			);
		}
		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/structure/resources',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permission' ),
				'callback'            => array( $this, 'create_resource' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/structure/units/(?P<unit>\d+)',
			array(
				'methods'             => 'DELETE',
				'permission_callback' => array( $this, 'permission' ),
				'callback'            => array( $this, 'delete_unit' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/structure/skill-suggestions',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, 'skill_suggestions' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, 'suggest_skills' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/structure/skill-suggestions/review',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permission' ),
				'callback'            => array( $this, 'review_skill_suggestions' ),
			)
		);
		add_filter( 'rest_dispatch_request', array( $this, 'dispatch' ), 20, 4 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'created' ), 20, 3 );
	}

	/**
	 * Authorize a specific course.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function permission( $request ) {
		return Catalog::can_edit( (int) $request['id'] );
	}

	/**
	 * Read the normalized saved authoring structure.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	public function show( $request ) {
		$id                          = (int) $request['id'];
		$data                        = CourseStructure::get( $id );
		$published                   = \OhMyLMS\Learning\UnitSnapshots::published_program( $id );
		$data['published_units']     = $published['units'];
		$data['program']             = \OhMyLMS\Learning\CourseProgram::editor( $id )['draft'];
		$fields                      = array_flip( array( 'mode', 'recognize_prior', 'evidence_days', 'bank_ids', 'items', 'outcomes', 'groups', 'units' ) );
		$data['unpublished_changes'] = wp_json_encode( array_intersect_key( $data['program'], $fields ) ) !== wp_json_encode( array_intersect_key( $published, $fields ) );
		foreach ( $data['program']['items'] as &$item ) {
			$item['name']     = 'practice' === $item['type'] ? ( get_term( $item['content_id'], \OhMyLMS\Skills\Taxonomy::NAME )->name ?? '' ) : get_the_title( $item['content_id'] );
			$item['edit_url'] = 'practice' === $item['type'] ? '' : (string) get_edit_post_link( $item['content_id'], 'raw' );
			$item['style']    = 'quiz' === $item['type'] && \OhMyLMS\Assessment\Schema::ready() ? \OhMyLMS\Assessment\AssessmentSettings::get( $item['content_id'] )['style'] : $item['type'];
			$item['name']     = $item['title'] ?? $item['name'];
			$item['status']   = 'practice' === $item['type'] ? '' : get_post_status( $item['content_id'] );
		}
		unset( $item );
		$data['skill_names'] = array();
		$data['skill_codes'] = array();
		$data['skill_meta']  = array();
		foreach ( $data['program']['outcomes'] as $outcome ) {
			$term = get_term( $outcome['term_id'], \OhMyLMS\Skills\Taxonomy::NAME );
			if ( $term && ! is_wp_error( $term ) ) {
				$data['skill_names'][ $outcome['term_id'] ] = \OhMyLMS\Curriculum\Syllabus::text( $term->name );

				$code = (string) get_term_meta( $term->term_id, '_ohmylms_skill_code', true );
				if ( '' !== $code ) {
					$data['skill_codes'][ $outcome['term_id'] ] = $code;
				}
				$origin                                    = \OhMyLMS\Skills\Provenance::read( $term->term_id );
				$data['skill_meta'][ $outcome['term_id'] ] = array_intersect_key( $origin, array_flip( array( 'source', 'review', 'status', 'model', 'confidence', 'prompt', 'ref' ) ) );
			}
		}
		$data['subject']           = self::course_subject( array_keys( $data['skill_names'] ) );
		$data['can_review_skills'] = current_user_can( 'manage_options' );
		$data['create_urls']       = array(
			'lesson' => admin_url( 'post-new.php?post_type=' . OHMYLMS_LESSON_CPT ),
			'quiz'   => admin_url( 'post-new.php?post_type=' . OHMYLMS_QUIZ_CPT ),
		);
		return $data;
	}

	/**
	 * The subject most of a course's skills belong to, or empty.
	 *
	 * @param int[] $term_ids Skills the outline uses.
	 * @return string
	 */
	private static function course_subject( array $term_ids ) {
		$counts = array();
		foreach ( $term_ids as $term_id ) {
			$subject = (string) get_term_meta( (int) $term_id, \OhMyLMS\Curriculum\Comparison::SUBJECT_META, true );
			if ( '' !== $subject ) {
				$counts[ $subject ] = ( $counts[ $subject ] ?? 0 ) + 1;
			}
		}
		arsort( $counts );
		return (string) ( array_key_first( $counts ) ?? '' );
	}

	/**
	 * Subjects an author can choose for skill suggestions: those with a profile or with shared skills.
	 *
	 * @return string[]
	 */
	private static function subjects() {
		$subjects = array_keys( \OhMyLMS\Skills\SubjectProfiles::subjects_in_use() );
		foreach ( \OhMyLMS\Skills\SubjectProfiles::all() as $key => $profile ) {
			if ( \OhMyLMS\Skills\SubjectProfiles::GENERIC !== $key ) {
				$subjects[] = $profile['label'];
			}
		}
		$unique = array();
		foreach ( $subjects as $subject ) {
			$unique[ \OhMyLMS\Skills\SubjectProfileModel::key( $subject ) ] = $unique[ \OhMyLMS\Skills\SubjectProfileModel::key( $subject ) ] ?? $subject;
		}
		sort( $unique );
		return array_values( $unique );
	}

	/**
	 * Skill suggestions made for this course, pending by default, with the available skill sources.
	 *
	 * @param \WP_REST_Request $request Request with optional status.
	 * @return array
	 */
	public function skill_suggestions( $request ) {
		return \OhMyLMS\Curriculum\AiAnalysis::suggestions(
			array(
				'course_id' => (int) $request['id'],
				'kind'      => 'course_skill',
				'status'    => $request['status'] ?? 'pending',
				'page'      => $request['page'] ?? 1,
			)
		) + array(
			'sources'  => \OhMyLMS\Skills\SkillSources::describe(),
			'subjects' => self::subjects(),
		);
	}

	/**
	 * Ask a skill source which skills a unit or lesson teaches. Runs at once; the proposals wait for review.
	 *
	 * @param \WP_REST_Request $request unit_id, group_id (lesson UUID or empty), subject, source.
	 * @return array|\WP_Error run and its suggestions.
	 */
	public function suggest_skills( $request ) {
		$run = \OhMyLMS\Curriculum\AiAnalysis::start(
			array(
				'kind'      => 'course',
				'course_id' => (int) $request['id'],
				'unit_id'   => (int) $request['unit_id'],
				'group_id'  => (string) ( $request['group_id'] ?? '' ),
				'subject'   => (string) ( $request['subject'] ?? '' ),
				'source'    => (string) ( $request['source'] ?? 'course-content' ),
			)
		);
		if ( is_wp_error( $run ) ) {
			return $run;
		}
		$run = \OhMyLMS\Curriculum\AiAnalysis::step( $run['id'] );
		if ( ! empty( $run['error'] ) && 'done' !== $run['status'] ) {
			\OhMyLMS\Curriculum\AiAnalysis::cancel( $run['id'] );
			return new \WP_Error( 'ohmylms_course_skill_failed', $run['error'], array( 'status' => 502 ) );
		}
		return array(
			'run'         => $run,
			'suggestions' => \OhMyLMS\Curriculum\AiAnalysis::suggestions(
				array(
					'run_id' => $run['id'],
					'status' => 'all',
				)
			)['items'],
		);
	}

	/**
	 * Accept or reject this course's skill suggestions. Suggestions of other courses are ignored.
	 *
	 * @param \WP_REST_Request $request ids, action (accept or reject), overrides.
	 * @return array Suggestion ID => described suggestion or error.
	 */
	public function review_skill_suggestions( $request ) {
		$ids = array();
		foreach ( (array) $request['ids'] as $id ) {
			$row = \OhMyLMS\Curriculum\AiAnalysis::suggestions(
				array(
					'course_id' => (int) $request['id'],
					'kind'      => 'course_skill',
					'status'    => 'all',
					'id'        => absint( $id ),
				)
			);
			if ( $row['total'] ) {
				$ids[] = absint( $id );
			}
		}
		return \OhMyLMS\Curriculum\AiAnalysis::review( $ids, 'reject' === $request['action'] ? 'reject' : 'accept', (array) ( $request['overrides'] ?? array() ) );
	}

	/**
	 * Save the complete unified outline under the dispatch transaction.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function save( $request ) {
		$result = \OhMyLMS\Learning\OutlineEditor::save( (int) $request['id'], (array) $request->get_json_params() );
		return is_wp_error( $result ) ? $result : $this->show( $request ) + array( 'unit_ids' => $result['unit_ids'] );
	}

	/**
	 * Delete one unit under the dispatch transaction. The catalog refuses units that still hold native
	 * placements or that the published program uses; draft activities of the unit become unplaced.
	 *
	 * @param \WP_REST_Request $request Request with the course and unit IDs.
	 * @return array|\WP_Error Fresh outline snapshot.
	 */
	public function delete_unit( $request ) {
		$deleted = Catalog::delete_chapter( (int) $request['id'], (int) $request['unit'] );
		return is_wp_error( $deleted ) ? $deleted : $this->show( $request );
	}

	/**
	 * Create a reusable draft without saving any pending outline edits.
	 *
	 * @param \WP_REST_Request $request Resource request.
	 * @return array|\WP_Error
	 */
	public function create_resource( $request ) {
		$id     = (int) $request['id'];
		$result = \OhMyLMS\Learning\ActivityDrafts::create( $id, (array) $request->get_json_params() );
		return is_wp_error( $result ) ? $result : $result + array( 'revision' => Catalog::revision( $id ) );
	}

	/**
	 * Find editable existing resources with assessment placement metadata.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function resources( $request ) {
		$type = sanitize_key( $request->get_param( 'type' ) ?? 'lesson' );
		if ( 'skill' === $type ) {
			return Catalog::targets( 'skill', sanitize_text_field( $request->get_param( 'search' ) ?? '' ), 0, max( 1, absint( $request->get_param( 'page' ) ?? 1 ) ) );
		}
		if ( 'practice' === $type ) {
			$items = array();
			foreach ( \OhMyLMS\Learning\CourseProgram::editor( (int) $request['id'] )['draft']['outcomes'] as $outcome ) {
				$term = get_term( $outcome['term_id'], \OhMyLMS\Skills\Taxonomy::NAME );
				if ( $term && ! is_wp_error( $term ) ) {
					$items[] = array(
						'id'       => (int) $term->term_id,
						'title'    => $term->name,
						'type'     => 'practice',
						'style'    => 'practice',
						'edit_url' => '',
					);
				}
			}
			return array(
				'items' => $items,
				'pages' => 1,
			);
		}
		if ( ! in_array( $type, array( 'lesson', 'quiz', 'assignment' ), true ) ) {
			return Catalog::error( __( 'Choose a lesson, quiz or assignment.', 'ohmylms' ) );
		}
		$data = Catalog::targets( $type, sanitize_text_field( $request->get_param( 'search' ) ?? '' ), 0, max( 1, absint( $request->get_param( 'page' ) ?? 1 ) ) );
		foreach ( $data['items'] as &$item ) {
			$item['type']     = $type;
			$item['style']    = 'quiz' === $type && \OhMyLMS\Assessment\Schema::ready() ? \OhMyLMS\Assessment\AssessmentSettings::get( $item['id'] )['style'] : $type;
			$item['edit_url'] = (string) get_edit_post_link( $item['id'], 'raw' );
		}
		unset( $item );
		return $data;
	}

	/**
	 * Preview the saved draft without changing the published pointer.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	public function preview( $request ) {
		$data    = $this->show( $request );
		$id      = (int) $request['id'];
		$program = \OhMyLMS\Learning\CourseProgram::editor( $id )['draft'];
		foreach ( $data['units'] as &$unit ) {
			foreach ( $unit['items'] as &$item ) {
				$term         = 'practice' === $item['type'] ? get_term( $item['content_id'], \OhMyLMS\Skills\Taxonomy::NAME ) : null;
				$item['name'] = $term && ! is_wp_error( $term ) ? $term->name : get_the_title( $item['content_id'] );
				$item['name'] = $item['title'] ?? $item['name'];
			}
			unset( $item );
			$arranged          = \OhMyLMS\Learning\ProgramGroups::arrange( array(), $unit['items'], $unit['id'] );
			$unit['groups']    = $arranged['groups'];
			$unit['ungrouped'] = $arranged['ungrouped'];
		}
		unset( $unit );
		$data['published_program_id'] = (int) get_post_meta( $id, \OhMyLMS\Learning\CourseProgram::CURRENT, true );
		$data['readiness']            = \OhMyLMS\Learning\CourseProgram::readiness( $program, $id );
		return $data;
	}

	/**
	 * Publish the saved program and unit snapshot through existing readiness/version policy.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function publish( $request ) {
		return Catalog::publish( (int) $request['id'], rest_sanitize_boolean( $request->get_param( 'apply_existing' ) ) );
	}

	/**
	 * Resolve only known authoring routes; learner activity endpoints are excluded.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|null Course, chapter, operation.
	 */
	private function target( $request ) {
		$route = $request->get_route();
		if ( preg_match( '#^/ohmylms/v1/courses/(\d+)(/(?:structure(?:/publish|/preview|/resources|/units/\d+)?|chapters|learning(?:/publish|/preview)?))?/?$#', $route, $match ) ) {
			$suffix = $match[2] ?? '';
			return array( (int) $match[1], 0, '/chapters' === $suffix ? 'units' : ( '' === $suffix ? 'course' : 'callback' ) );
		}
		if ( preg_match( '#^/ohmylms/v1/(?:content-hub/catalog|mcp-structure)/(\d+)(?:/.*)?$#', $route, $match ) ) {
			return array( (int) $match[1], 0, 'callback' );
		}
		if ( preg_match( '#^/ohmylms/v1/chapters(?:/(\d+)(?:/(content|search-contents)(?:/(\d+))?)?)?/?$#', $route, $match ) ) {
			$chapter = (int) ( $match[1] ?? 0 );
			$course  = $chapter ? CourseStructure::owner( $chapter ) : absint( $request->get_param( 'course_id' ) );
			return array( $course, $chapter, ! empty( $match[3] ) ? 'detach' : ( 'content' === ( $match[2] ?? '' ) ? 'contents' : 'unit' ) );
		}
		return null;
	}

	/**
	 * Add private revision headers while preserving response contracts.
	 *
	 * @param mixed $result Handler result.
	 * @param int   $course_id Course ID.
	 * @return mixed
	 */
	private function describe( $result, $course_id ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = rest_ensure_response( $result );
		if ( $response->get_status() >= 400 ) {
			return $response;
		}
		$revision = Catalog::revision( $course_id );
		$response->header( 'X-OhMyLMS-Structure-Revision', $revision );
		$response->header( 'X-OhMyLMS-Structure-Course', (string) $course_id );
		$response->header( 'Cache-Control', 'private, no-store' );
		$data = $response->get_data();
		if ( is_array( $data ) && ! wp_is_numeric_array( $data ) ) {
			$data['structure_revision'] = $revision;
			$response->set_data( $data );
		}
		return $response;
	}

	/**
	 * Run supported dashboard writes through the shared boundary after existing permissions pass.
	 *
	 * @param mixed            $result Prior dispatch override.
	 * @param \WP_REST_Request $request Request.
	 * @param string           $route Matched route expression.
	 * @param array            $handler Original registered handler.
	 * @return mixed
	 */
	public function dispatch( $result, $request, $route, $handler ) {
		$target = $this->target( $request );
		if ( null !== $result || ! $target ) {
			return $result;
		}
		list( $course, $chapter, $operation ) = $target;
		$method                               = $request->get_method();
		$read                                 = in_array( $method, array( 'GET', 'HEAD' ), true ) || '/preview' === substr( $request->get_route(), -8 );
		if ( ! Catalog::can_edit( $course ) ) {
			// The existing public course read retains its own access policy; no private headers are added.
			return 'course' === $operation && $read ? $result : Catalog::error( __( 'You cannot access this course structure.', 'ohmylms' ), rest_authorization_required_code() );
		}
		if ( 'course' === $operation && ! $read ) {
			// Course details/pricing are not structure writes; their response still describes the draft.
			return \OhMyLMS\Learning\UnitSnapshots::authoring(
				static function () use ( $request, $handler ) {
					return call_user_func( $handler['callback'], $request );
				}
			);
		}
		if ( $read ) {
			return CourseStructure::read(
				$course,
				function () use ( $request, $handler, $course ) {
					return $this->describe( call_user_func( $handler['callback'], $request ), $course );
				}
			);
		}
		$revision        = $request->get_header( 'x-ohmylms-structure-revision' );
		$revision        = $revision ? $revision : $request->get_param( 'revision' );
		$referenced_unit = $chapter ? $chapter : absint( $request->get_param( 'chapter_id' ) );
		if ( $referenced_unit && CourseStructure::owner( $referenced_unit ) !== $course ) {
			return Catalog::error( __( 'The unit must belong exclusively to this course.', 'ohmylms' ), 400 );
		}
		return CourseStructure::change(
			$course,
			$revision,
			function () use ( $course, $chapter, $operation, $request, $handler, $method ) {
				$result = $this->write( $course, $chapter, $operation, $request, $handler, $method );
				return $this->describe( $result, $course );
			}
		);
	}

	/**
	 * Give a newly created course its first read token; metadata updates preserve a stale client token.
	 *
	 * @param mixed            $result Handler result.
	 * @param array            $handler Registered handler.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function created( $result, $handler, $request ) {
		if ( '/ohmylms/v1/courses' === rtrim( $request->get_route(), '/' ) && 'POST' === $request->get_method() && ! is_wp_error( $result ) ) {
			$response = rest_ensure_response( $result );
			$id       = (int) ( $response->get_data()['id'] ?? 0 );
			if ( $id && Catalog::can_edit( $id ) ) {
				return $this->describe( $response, $id );
			}
		}
		return $result;
	}

	/**
	 * Adapt native bulk/unit/activity operations, delegating program operations unchanged.
	 *
	 * @param int              $course Course ID.
	 * @param int              $chapter Unit ID.
	 * @param string           $operation Operation.
	 * @param \WP_REST_Request $request Request.
	 * @param array            $handler Handler.
	 * @param string           $method HTTP method.
	 * @return mixed
	 */
	private function write( $course, $chapter, $operation, $request, $handler, $method ) {
		$rows = $request->get_json_params();
		if ( 'units' === $operation ) {
			$saved = is_array( $rows ) ? CourseStructure::save_units( $course, $rows ) : Catalog::error( __( 'Send a unit list.', 'ohmylms' ) );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			$response = array(
				'status'  => 'success',
				'message' => __( 'Chapters updated successfully', 'ohmylms' ),
			);
			if ( 1 === count( $saved ) ) {
				$response['chapter'] = $saved[0];
			}
			return $response;
		}
		if ( 'contents' === $operation ) {
			$saved = is_array( $rows ) ? CourseStructure::save_contents( $course, $chapter, $rows ) : Catalog::error( __( 'Send an activity list.', 'ohmylms' ) );
			return is_wp_error( $saved ) ? $saved : array(
				'status'  => 'success',
				'message' => __( 'Contents updated successfully', 'ohmylms' ),
			);
		}
		if ( 'detach' === $operation ) {
			$saved = CourseStructure::detach( $chapter, absint( $request['content_id'] ) );
			return is_wp_error( $saved ) ? $saved : array(
				'id'      => $chapter,
				'status'  => 'success',
				'message' => __( 'Deleted Successfully', 'ohmylms' ),
			);
		}
		if ( 'unit' === $operation ) {
			if ( 'DELETE' === $method ) {
				$deleted = Catalog::delete_chapter( $course, $chapter );
				if ( is_wp_error( $deleted ) ) {
					return $deleted;
				}
				do_action( 'ohmylms_rest_delete_chapter', $request );
				return array(
					'id'      => $chapter,
					'status'  => 'success',
					'message' => __( 'Deleted Successfully', 'ohmylms' ),
				);
			}
			$saved = CourseStructure::save_unit( $course, $chapter, $request->get_params() );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			$post       = get_post( $saved['id'] );
			$additional = $this->update_additional_fields_for_object( $post, $request );
			if ( is_wp_error( $additional ) ) {
				return $additional;
			}
			$object = ohmylms_get_chapter( $saved['id'] );
			foreach ( array( 'availability', 'available_date', 'accessibility' ) as $key ) {
				if ( $request->has_param( $key ) ) {
					$object->{ 'set_' . $key }( $request->get_param( $key ) );
				}
			}
			$object->save();
			if ( $chapter ) {
				do_action( 'ohmylms_rest_chapter_updated', $post );
			} else {
				do_action( 'ohmylms_rest_insert_chapter', $post, $request, true );
			}
			$response = call_user_func( array( $handler['callback'][0], 'prepare_item_for_response' ), $post, $request );
			$response = rest_ensure_response( $response );
			$response->set_status( $chapter ? 200 : 201 );
			return $response;
		}
		return call_user_func( $handler['callback'], $request );
	}
}
