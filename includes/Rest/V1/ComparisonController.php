<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Manual syllabus comparison endpoints.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\Access;
use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\SkillMappings;
use OhMyLMS\Skills\SubjectProfileModel;
use OhMyLMS\Skills\SubjectProfiles;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/** Administrator writes; learner reads are always for the current account. */
class ComparisonController extends RestController {

	/** Register the additive comparison API. */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/syllabus-comparison/mapping/reconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reconnect_mapping' ),
				'permission_callback' => array( Access::class, 'admin' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/syllabus-comparison/mapping-groups/remove',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'remove_mapping_group' ),
				'permission_callback' => array( Access::class, 'admin' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/syllabus-comparison/mapping-groups',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_mapping_groups' ),
				'permission_callback' => array( Access::class, 'admin' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/syllabus-comparison/mapping-groups/(?P<uuid>[a-f0-9-]{36})',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'mapping_group' ),
				'permission_callback' => array( Access::class, 'admin' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/syllabus-comparison/roadmaps/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_roadmap' ),
					'permission_callback' => array( Access::class, 'admin' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'save_roadmap' ),
					'permission_callback' => array( Access::class, 'admin' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/syllabus-comparison/roadmaps/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'preview_roadmap' ),
				'permission_callback' => array( Access::class, 'admin' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/syllabus-comparison/audit/(?P<student_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'student_audit' ),
				'permission_callback' => array( Access::class, 'admin' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/syllabus-comparison/audit',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'progress' ),
				'permission_callback' => array( Access::class, 'learner' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/syllabus-comparison/roadmaps',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_roadmap' ),
				'permission_callback' => array( Access::class, 'admin' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/syllabus-comparison/shared-skills/(?P<id>\d+)/content',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'content' ),
					'permission_callback' => array( Access::class, 'admin' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'link_content' ),
					'permission_callback' => array( Access::class, 'admin' ),
				),
			)
		);
		foreach ( array(
			'/syllabus-comparison/syllabuses'              => array( 'GET', 'syllabuses' ),
			'/syllabus-comparison/configuration'           => array( 'PUT', 'configure' ),
			'/syllabus-comparison/objectives/(?P<id>\d+)'  => array( 'GET', 'objectives' ),
			'/syllabus-comparison/shared-skills'           => array( 'GET', 'shared_skills' ),
			'/syllabus-comparison/shared-skills/(?P<id>\d+)' => array( 'GET', 'shared_skill' ),
			'/syllabus-comparison/shared-skills/save'      => array( 'POST', 'save_shared' ),
			'/syllabus-comparison/mapping'                 => array( 'PUT', 'save_mapping' ),
			'/syllabus-comparison/mapping/remove'          => array( 'POST', 'remove_mapping' ),
			'/syllabus-comparison/skill-placement'         => array( 'GET', 'skill_placement' ),
			'/syllabus-comparison/subject-profiles'        => array( 'GET', 'subject_profiles' ),
			'/syllabus-comparison/skill-links/(?P<id>\d+)' => array( 'GET', 'skill_links' ),
			'/syllabus-comparison/skill-links'             => array( 'POST', 'save_skill_link' ),
			'/syllabus-comparison/skill-links/remove'      => array( 'POST', 'remove_skill_link' ),
			'/syllabus-comparison/skill-map/subjects'      => array( 'GET', 'skill_map_subjects' ),
			'/syllabus-comparison/compare'                 => array( 'GET', 'compare_syllabuses' ),
			'/syllabus-comparison/ai/runs'                 => array( 'GET,POST', 'ai_runs' ),
			'/syllabus-comparison/ai/runs/(?P<id>\d+)'     => array( 'GET', 'ai_run' ),
			'/syllabus-comparison/ai/runs/(?P<id>\d+)/step' => array( 'POST', 'ai_step' ),
			'/syllabus-comparison/ai/runs/(?P<id>\d+)/cancel' => array( 'POST', 'ai_cancel' ),
			'/syllabus-comparison/ai/runs/(?P<id>\d+)/accept-confident' => array( 'POST', 'ai_accept_confident' ),
			'/syllabus-comparison/ai/suggestions'          => array( 'GET', 'ai_suggestions' ),
			'/syllabus-comparison/ai/suggestions/review'   => array( 'POST', 'ai_review' ),
		) as $route => $handler ) {
			register_rest_route(
				$this->namespace,
				$route,
				array(
					'methods'             => $handler[0],
					'callback'            => array( $this, $handler[1] ),
					'permission_callback' => array( Access::class, 'admin' ),
				)
			);
		}
		$this->register_workspace_routes();
		register_rest_route(
			$this->namespace,
			'/me/subject-skills',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'progress' ),
				'permission_callback' => array( Access::class, 'learner' ),
			)
		);
	}

	/**
	 * Skill map and subject profile routes with typed arguments. Legacy clients send an empty
	 * string for "no syllabus" or "no learner", so those identifiers also accept ''.
	 */
	private function register_workspace_routes() {
		$admin    = array( Access::class, 'admin' );
		$optional = array(
			'default'           => 0,
			'validate_callback' => static function ( $value ) {
				return '' === $value || ( is_scalar( $value ) && 1 === preg_match( '/^\d{1,19}$/', (string) $value ) );
			},
			'sanitize_callback' => 'absint',
		);
		register_rest_route(
			$this->namespace,
			'/syllabus-comparison/skill-map',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'skill_map' ),
				'permission_callback' => $admin,
				'args'                => array(
					'subject'     => array(
						'type'      => 'string',
						'required'  => true,
						'minLength' => 1,
						'maxLength' => 190,
					),
					'syllabus_id' => $optional,
					'student_id'  => $optional,
				),
			)
		);
		$revision = array(
			'type'      => 'string',
			'required'  => true,
			'minLength' => 16,
			'maxLength' => 16,
		);
		$key      = array(
			'type'      => 'string',
			'minLength' => 1,
			'maxLength' => 100,
		);
		$text     = array(
			'type'      => 'string',
			'maxLength' => SubjectProfileModel::MAX_TEXT,
		);
		register_rest_route(
			$this->namespace,
			'/syllabus-comparison/subject-profiles/save',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_subject_profile' ),
				'permission_callback' => $admin,
				'args'                => array(
					'expected_revision' => $revision,
					'key'               => array(
						'type'      => 'string',
						'maxLength' => 100,
					),
					'label'             => array(
						'type'      => 'string',
						'required'  => true,
						'minLength' => 1,
						'maxLength' => SubjectProfileModel::MAX_LABEL,
					),
					'language'          => array(
						'type'      => 'string',
						'maxLength' => 20,
					),
					'domains'           => array(
						'type'     => 'array',
						'maxItems' => SubjectProfileModel::MAX_DOMAINS,
						'items'    => array(
							'type'       => 'object',
							'required'   => array( 'id', 'label' ),
							'properties' => array(
								'id'    => array(
									'type'      => 'string',
									'maxLength' => 40,
								),
								'label' => array(
									'type'      => 'string',
									'maxLength' => SubjectProfileModel::MAX_LABEL,
								),
							),
						),
					),
					'levels'            => array(
						'type'     => 'array',
						'required' => true,
						'minItems' => 1,
						'maxItems' => SubjectProfileModel::MAX_LEVELS,
						'items'    => array(
							'type'      => 'string',
							'maxLength' => SubjectProfileModel::MAX_LEVEL,
						),
					),
					'ai'                => array(
						'type'                 => 'object',
						'properties'           => array(
							'role'        => $text,
							'context'     => $text,
							'level_hints' => $text,
						),
						'additionalProperties' => false,
					),
				),
			)
		);
		foreach ( array(
			'remove'  => 'remove_subject_profile',
			'default' => 'default_subject_profile',
		) as $action => $callback ) {
			register_rest_route(
				$this->namespace,
				'/syllabus-comparison/subject-profiles/' . $action,
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, $callback ),
					'permission_callback' => $admin,
					'args'                => array(
						'key'               => $key + array( 'required' => true ),
						'expected_revision' => $revision,
					),
				)
			);
		}
	}

	/**
	 * Domains and levels a shared skill of a subject can be placed in, and whether AI analysis can run.
	 * `grades` keeps its name for existing clients; it holds the subject profile's levels.
	 *
	 * @param WP_REST_Request $request Optional subject.
	 * @return mixed
	 */
	public function skill_placement( WP_REST_Request $request ) {
		$subject = sanitize_text_field( (string) ( $request['subject'] ?? '' ) );
		$levels  = Comparison::levels( $subject );
		return rest_ensure_response(
			array(
				'domains'      => Comparison::domains( $subject ),
				'grades'       => $levels,
				'levels'       => $levels,
				'profile'      => SubjectProfiles::get( $subject )['key'],
				'ai_available' => \OhMyLMS\Curriculum\AiAnalysis::available(),
			)
		);
	}

	/**
	 * Subject profiles, the default profile and the subjects in use.
	 *
	 * @return mixed
	 */
	public function subject_profiles() {
		return rest_ensure_response( SubjectProfiles::payload() );
	}

	/**
	 * Save one subject profile against the revision it was edited from.
	 *
	 * @param WP_REST_Request $request Profile fields and expected_revision.
	 * @return mixed
	 */
	public function save_subject_profile( WP_REST_Request $request ) {
		return rest_ensure_response( SubjectProfiles::save( $request->get_params(), (string) $request['expected_revision'] ) );
	}

	/**
	 * Remove a stored subject profile, restoring a built-in one.
	 *
	 * @param WP_REST_Request $request key and expected_revision.
	 * @return mixed
	 */
	public function remove_subject_profile( WP_REST_Request $request ) {
		return rest_ensure_response(
			\OhMyLMS\Curriculum\Items::exclusive(
				static function () use ( $request ) {
					return SubjectProfiles::remove( (string) $request['key'], (string) $request['expected_revision'] );
				}
			)
		);
	}

	/**
	 * Choose the profile for subjects without their own.
	 *
	 * @param WP_REST_Request $request key and expected_revision.
	 * @return mixed
	 */
	public function default_subject_profile( WP_REST_Request $request ) {
		return rest_ensure_response(
			\OhMyLMS\Curriculum\Items::exclusive(
				static function () use ( $request ) {
					return SubjectProfiles::set_default( (string) $request['key'], (string) $request['expected_revision'] );
				}
			)
		);
	}

	/**
	 * Prerequisites and encompassed skills of a shared skill.
	 *
	 * @param WP_REST_Request $request Skill ID.
	 * @return mixed
	 */
	public function skill_links( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		return \OhMyLMS\Curriculum\SkillEdges::is_shared( $id ) ? rest_ensure_response( \OhMyLMS\Curriculum\SkillEdges::for_skill( $id ) ) : Access::error( 'ohmylms_shared_missing', __( 'Shared skill not found.', 'ohmylms' ), 404 );
	}

	/**
	 * Subjects with shared skills or syllabuses, for the skill map pickers.
	 *
	 * @return mixed
	 */
	public function skill_map_subjects() {
		return rest_ensure_response( \OhMyLMS\Curriculum\SkillMap::subjects() );
	}

	/**
	 * The coherence map of a subject: shared skills by domain and grade, their links and the checks
	 * on them, optionally with a syllabus's coverage and a learner's levels laid over it.
	 *
	 * @param WP_REST_Request $request subject, syllabus_id, student_id.
	 * @return mixed
	 */
	public function skill_map( WP_REST_Request $request ) {
		$student = absint( $request['student_id'] );
		if ( $student && ! get_userdata( $student ) ) {
			return Access::error( 'ohmylms_skill_map_student', __( 'Learner not found.', 'ohmylms' ), 404 );
		}
		$map = \OhMyLMS\Curriculum\SkillMap::map( (string) $request['subject'], absint( $request['syllabus_id'] ), $student );
		return is_wp_error( $map ) ? $map : rest_ensure_response( $map );
	}

	/**
	 * Compare two syllabuses through the shared skills they map onto.
	 *
	 * @param WP_REST_Request $request a and b (syllabus IDs).
	 * @return mixed
	 */
	public function compare_syllabuses( WP_REST_Request $request ) {
		$result = \OhMyLMS\Curriculum\SkillMap::compare( absint( $request['a'] ), absint( $request['b'] ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Add a prerequisite or encompassing link by hand.
	 *
	 * @param WP_REST_Request $request skill_id, other_id, type and, for encompasses, weight.
	 * @return mixed
	 */
	public function save_skill_link( WP_REST_Request $request ) {
		$skill = absint( $request['skill_id'] );
		$other = absint( $request['other_id'] );
		$type  = (string) $request['type'];
		if ( 'prerequisite' === $type ) {
			$saved = \OhMyLMS\Curriculum\SkillEdges::add_prerequisite( $skill, $other );
		} elseif ( 'encompasses' === $type && is_numeric( $request['weight'] ) ) {
			$saved = \OhMyLMS\Curriculum\SkillEdges::add_encompass( $skill, $other, (float) $request['weight'] );
		} else {
			return Access::error( 'ohmylms_edge_invalid', __( 'Choose prerequisite, or encompasses with a weight from 0.05 to 1.', 'ohmylms' ) );
		}
		return is_wp_error( $saved ) ? $saved : rest_ensure_response( \OhMyLMS\Curriculum\SkillEdges::for_skill( $skill ) );
	}

	/**
	 * Remove a prerequisite or encompassing link.
	 *
	 * @param WP_REST_Request $request skill_id, other_id and type.
	 * @return mixed
	 */
	public function remove_skill_link( WP_REST_Request $request ) {
		$skill   = absint( $request['skill_id'] );
		$removed = \OhMyLMS\Curriculum\SkillEdges::remove( $skill, absint( $request['other_id'] ), (string) $request['type'] );
		return is_wp_error( $removed ) ? $removed : rest_ensure_response( \OhMyLMS\Curriculum\SkillEdges::for_skill( $skill ) );
	}

	/**
	 * List recent AI analyses, or start one.
	 *
	 * @param WP_REST_Request $request kind, syllabus_id, subject, include_mapped.
	 * @return mixed
	 */
	public function ai_runs( WP_REST_Request $request ) {
		if ( 'POST' === $request->get_method() ) {
			return rest_ensure_response( \OhMyLMS\Curriculum\AiAnalysis::start( $request->get_params() ) );
		}
		return rest_ensure_response( \OhMyLMS\Curriculum\AiAnalysis::runs( $request->get_params() ) );
	}

	/**
	 * One AI analysis.
	 *
	 * @param WP_REST_Request $request Run ID.
	 * @return mixed
	 */
	public function ai_run( WP_REST_Request $request ) {
		$run = \OhMyLMS\Curriculum\AiAnalysis::run( (int) $request['id'] );
		return $run ? rest_ensure_response( $run ) : Access::error( 'ohmylms_ai_analysis_missing', __( 'Analysis not found.', 'ohmylms' ), 404 );
	}

	/**
	 * Advance an AI analysis by one slice; the review screen calls this while it is open.
	 *
	 * @param WP_REST_Request $request Run ID.
	 * @return mixed
	 */
	public function ai_step( WP_REST_Request $request ) {
		$run = \OhMyLMS\Curriculum\AiAnalysis::step( (int) $request['id'] );
		return $run ? rest_ensure_response( $run ) : Access::error( 'ohmylms_ai_analysis_missing', __( 'Analysis not found.', 'ohmylms' ), 404 );
	}

	/**
	 * Stop an AI analysis.
	 *
	 * @param WP_REST_Request $request Run ID.
	 * @return mixed
	 */
	public function ai_cancel( WP_REST_Request $request ) {
		return rest_ensure_response( \OhMyLMS\Curriculum\AiAnalysis::cancel( (int) $request['id'] ) );
	}

	/**
	 * Accept the confident suggestions of a run.
	 *
	 * @param WP_REST_Request $request Run ID and min_confidence.
	 * @return mixed
	 */
	public function ai_accept_confident( WP_REST_Request $request ) {
		$min = $request->get_param( 'min_confidence' );
		if ( ! is_numeric( $min ) || (float) $min < 0.5 || (float) $min > 1 ) {
			return Access::error( 'ohmylms_ai_analysis_invalid', __( 'Choose a confidence between 0.5 and 1.', 'ohmylms' ) );
		}
		return rest_ensure_response( \OhMyLMS\Curriculum\AiAnalysis::accept_confident( (int) $request['id'], (float) $min ) );
	}

	/**
	 * AI suggestions to review.
	 *
	 * @param WP_REST_Request $request Filters.
	 * @return mixed
	 */
	public function ai_suggestions( WP_REST_Request $request ) {
		return rest_ensure_response( \OhMyLMS\Curriculum\AiAnalysis::suggestions( $request->get_params() ) );
	}

	/**
	 * Accept or reject AI suggestions.
	 *
	 * @param WP_REST_Request $request ids, action and optional overrides.
	 * @return mixed
	 */
	public function ai_review( WP_REST_Request $request ) {
		$ids       = $request->get_param( 'ids' );
		$action    = (string) $request->get_param( 'action' );
		$overrides = $request->get_param( 'overrides' );
		if ( ! is_array( $ids ) || ! $ids || count( $ids ) > 200 || ! in_array( $action, array( 'accept', 'reject' ), true ) || ( null !== $overrides && ! is_array( $overrides ) ) ) {
			return Access::error( 'ohmylms_ai_analysis_invalid', __( 'Choose up to 200 suggestions and accept or reject them.', 'ohmylms' ) );
		}
		return rest_ensure_response( array( 'results' => \OhMyLMS\Curriculum\AiAnalysis::review( $ids, $action, (array) $overrides ) ) );
	}

	/**
	 * Create a general draft roadmap from ordered shared IDs.
	 *
	 * @param WP_REST_Request $request Roadmap fields.
	 * @return mixed
	 */
	public function create_roadmap( WP_REST_Request $request ) {
		return rest_ensure_response( \OhMyLMS\Curriculum\Roadmaps::create( $request->get_params() ) );
	}

	/**
	 * Read a roadmap editor's saved state.
	 *
	 * @param WP_REST_Request $request Roadmap ID.
	 * @return mixed
	 */
	public function get_roadmap( WP_REST_Request $request ) {
		return rest_ensure_response( \OhMyLMS\Curriculum\Roadmaps::get( (int) $request['id'] ) );
	}

	/**
	 * Save roadmap details and steps.
	 *
	 * @param WP_REST_Request $request Roadmap fields.
	 * @return mixed
	 */
	public function save_roadmap( WP_REST_Request $request ) {
		return rest_ensure_response( \OhMyLMS\Curriculum\Roadmaps::save( (int) $request['id'], $request->get_params() ) );
	}

	/**
	 * Preview shared skills from selected syllabuses without changing storage.
	 *
	 * @param WP_REST_Request $request Source syllabus IDs.
	 * @return mixed
	 */
	public function preview_roadmap( WP_REST_Request $request ) {
		return rest_ensure_response( \OhMyLMS\Curriculum\Roadmaps::preview( $request['syllabus_ids'] ) );
	}
	/**
	 * Read a shared content page.
	 *
	 * @param WP_REST_Request $request Content filters.
	 * @return mixed
	 */
	public function content( WP_REST_Request $request ) {
		return rest_ensure_response( \OhMyLMS\Curriculum\SharedContent::listing( absint( $request['id'] ), $request->get_params() ) );
	}

	/**
	 * Connect reusable content without replacing any other links.
	 *
	 * @param WP_REST_Request $request Content connection.
	 * @return mixed
	 */
	public function link_content( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'linked' ) || ! is_bool( $request['linked'] ) ) {
			return Access::error( 'ohmylms_content_invalid', __( 'Explicitly choose whether to connect or disconnect the content.', 'ohmylms' ) );
		}
		return rest_ensure_response( \OhMyLMS\Curriculum\SharedContent::link( absint( $request['id'] ), absint( $request['content_id'] ), rest_sanitize_boolean( $request['linked'] ) ) );
	}

	/** Return selectors and the explicitly configured learner sources. */
	public function syllabuses() {
		return rest_ensure_response(
			array(
				'items'    => Comparison::syllabuses(),
				'selected' => (array) get_option( Comparison::OPTION, array() ),
			)
		);
	}

	/**
	 * Designate reviewed profile sources without publishing any course.
	 *
	 * @param WP_REST_Request $request Syllabus IDs.
	 * @return mixed
	 */
	public function configure( WP_REST_Request $request ) {
		$ids     = $request->get_param( 'syllabus_ids' );
		$subject = sanitize_text_field( (string) $request->get_param( 'subject' ) );
		if ( '' === $subject || mb_strlen( $subject ) > 100 ) {
			return Access::error( 'ohmylms_subject_invalid', __( 'Enter a subject up to 100 characters.', 'ohmylms' ) );
		}
		if ( ! is_array( $ids ) || count( $ids ) > 10 ) {
			return Access::error( 'ohmylms_comparison_invalid', __( 'Choose up to ten syllabuses or roadmaps.', 'ohmylms' ) );
		}
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
		foreach ( $ids as $id ) {
			if ( ! \OhMyLMS\Curriculum\Syllabus::is_syllabus( $id ) ) {
				return Access::error( 'ohmylms_comparison_invalid', __( 'Choose existing syllabuses.', 'ohmylms' ) );
			}
		}
		$saved = \OhMyLMS\Curriculum\Items::exclusive(
			static function () use ( $subject, $ids ) {
				$config             = (array) get_option( Comparison::OPTION, array() );
				$config[ $subject ] = $ids;
				update_option( Comparison::OPTION, $config, false );
				return true;
			}
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return $this->syllabuses();
	}

	/**
	 * Read one bounded objective page.
	 *
	 * @param WP_REST_Request $request Filters.
	 * @return mixed
	 */
	public function objectives( WP_REST_Request $request ) {
		return rest_ensure_response( Comparison::page( (int) $request['id'], $request->get_params() ) );
	}

	/**
	 * Read shared skills.
	 *
	 * @param WP_REST_Request $request Search and paging.
	 * @return mixed
	 */
	public function shared_skills( WP_REST_Request $request ) {
		return rest_ensure_response( Comparison::shared_skills( $request->get_params() ) );
	}

	/**
	 * Read a skill and all affected objectives.
	 *
	 * @param WP_REST_Request $request Skill ID.
	 * @return mixed
	 */
	public function shared_skill( WP_REST_Request $request ) {
		$skill = Comparison::shared_skill( (int) $request['id'] );
		return $skill ? rest_ensure_response( $skill ) : Access::error( 'ohmylms_shared_missing', __( 'Shared skill not found.', 'ohmylms' ), 404 );
	}

	/**
	 * Save a stable shared skill.
	 *
	 * @param WP_REST_Request $request Skill fields.
	 * @return mixed
	 */
	public function save_shared( WP_REST_Request $request ) {
		return rest_ensure_response( Comparison::save_shared( absint( $request['id'] ), $request->get_params() ) );
	}

	/**
	 * Save one explicit mapping after checking its current revision.
	 *
	 * @param WP_REST_Request $request Mapping and expected revision.
	 * @return mixed
	 */
	public function save_mapping( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'expected_revision' ) || ! Comparison::shared_skill( absint( $request['shared_id'] ) ) ) {
			return Access::error( 'ohmylms_comparison_invalid', __( 'Choose a shared skill and reload the current mapping.', 'ohmylms' ) );
		}
		return rest_ensure_response( SkillMappings::save( absint( $request['specific_id'] ), absint( $request['shared_id'] ), (string) $request['relation'], (string) $request['note'], (string) $request['expected_revision'] ) );
	}

	/**
	 * Replace an individual mapping endpoint in one guarded update.
	 *
	 * @param WP_REST_Request $request Reconnection fields and expected revision.
	 * @return mixed
	 */
	public function reconnect_mapping( WP_REST_Request $request ) {
		return rest_ensure_response( \OhMyLMS\Curriculum\MappingReconnect::save( $request->get_params() ) );
	}

	/**
	 * Save combined requirements atomically.
	 *
	 * @param WP_REST_Request $request Source groups, target and notes.
	 * @return mixed
	 */
	public function save_mapping_groups( WP_REST_Request $request ) {
		if ( ! is_array( $request['groups'] ) || ! is_int( $request['shared_id'] ) || ! is_string( $request['note'] ?? '' ) ) {
			return Access::error( 'ohmylms_mapping_group_invalid', __( 'Choose source groups and a shared skill.', 'ohmylms' ) );
		}
		$result = SkillMappings::save_groups( $request['groups'], $request['shared_id'], $request['note'] ?? '', (string) ( $request['relation'] ?? 'combined' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array_map(
				static function ( $row ) {
					return array(
						'id'          => $row['id'],
						'specific_id' => $row['specific_id'],
						'shared_id'   => $row['shared_id'],
						'relation'    => $row['relation'],
						'group_uuid'  => $row['group_uuid'],
						'revision'    => SkillMappings::revision( $row ),
					);
				},
				$result
			)
		);
	}

	/**
	 * Read all required objectives of one combined group.
	 *
	 * @param WP_REST_Request $request Group UUID.
	 * @return mixed
	 */
	public function mapping_group( WP_REST_Request $request ) {
		return rest_ensure_response( SkillMappings::group( (string) $request['uuid'] ) );
	}

	/**
	 * Remove all current links in one combined group, with revision checks.
	 *
	 * @param WP_REST_Request $request Group UUID and pair revisions.
	 * @return mixed
	 */
	public function remove_mapping_group( WP_REST_Request $request ) {
		global $wpdb;
		$uuid  = $request['uuid'];
		$pairs = $request['pairs'];
		if ( ! is_string( $uuid ) || ! preg_match( '/^[a-f0-9-]{36}$/D', $uuid ) || ! is_array( $pairs ) || count( $pairs ) > 200 ) {
			return Access::error( 'ohmylms_mapping_group_invalid', __( 'Choose a combined group with current pair revisions.', 'ohmylms' ) );
		}
		$lock = SkillMappings::LOCK . '-' . md5( $wpdb->prefix );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			return Access::error( 'ohmylms_mapping_busy', __( 'Another mapping change is being saved. Try again.', 'ohmylms' ), 409 );
		}
		try {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE group_uuid=%s', SkillMappings::table(), $uuid ), ARRAY_A );
			if ( ! $rows || count( $rows ) !== count( $pairs ) ) {
				return Access::error( 'ohmylms_mapping_stale', __( 'This group changed. Reload it before removing it.', 'ohmylms' ), 409 );
			}
			foreach ( $rows as $row ) {
				$matches = array_values(
					array_filter(
						$pairs,
						static function ( $pair ) use ( $row ) {
							return is_array( $pair ) && ( $pair['specific_id'] ?? null ) === (int) $row['specific_term_id'] && ( $pair['expected_revision'] ?? null ) === SkillMappings::revision( $row );
						}
					)
				);
				if ( 1 !== count( $matches ) ) {
					return Access::error( 'ohmylms_mapping_stale', __( 'This group changed. Reload it before removing it.', 'ohmylms' ), 409 );
				}
			}
			\OhMyLMS\Utility\Transaction::run(
				static function () use ( $wpdb, $uuid ) {
					if ( false === $wpdb->delete( SkillMappings::table(), array( 'group_uuid' => $uuid ) ) || false === $wpdb->delete( \OhMyLMS\Curriculum\Schema::table( 'skill_mapping_groups' ), array( 'uuid' => $uuid ) ) ) {
						throw new \RuntimeException( 'Combined coverage could not be removed.' );
					}
				}
			);
			return rest_ensure_response( array( 'removed' => true ) );
		} catch ( \Throwable $error ) {
			return Access::error( 'ohmylms_mapping_failed', __( 'Combined coverage could not be removed. Nothing was changed.', 'ohmylms' ), 500 );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Remove a mapping under the same lock as mapping saves.
	 *
	 * @param WP_REST_Request $request Mapping and expected revision.
	 * @return mixed
	 */
	public function remove_mapping( WP_REST_Request $request ) {
		global $wpdb;
		$lock = SkillMappings::LOCK . '-' . md5( $wpdb->prefix );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			return Access::error( 'ohmylms_mapping_busy', __( 'Another mapping change is being saved. Try again.', 'ohmylms' ), 409 );
		}
		try {
			$mappings = SkillMappings::for_skill( absint( $request['specific_id'] ) );
			foreach ( $mappings['maps_to'] as $mapping ) {
				$shared_id        = absint( $request['shared_id'] );
				$expected         = (string) $request['expected_revision'];
				$current_revision = SkillMappings::revision( $mapping );
				if ( $shared_id === (int) $mapping['shared_id'] && $expected === $current_revision ) {
					if ( ! empty( $mapping['group_uuid'] ) ) {
						return Access::error( 'ohmylms_mapping_group_invalid', __( 'Remove combined coverage as a group.', 'ohmylms' ), 409 );
					}
					if ( ! SkillMappings::remove( $mapping['specific_id'], $mapping['shared_id'] ) ) {
						return Access::error( 'ohmylms_mapping_failed', __( 'The mapping could not be removed.', 'ohmylms' ), 500 );
					}
					return rest_ensure_response( array( 'removed' => true ) );
				}
			}
			return Access::error( 'ohmylms_mapping_stale', __( 'This mapping changed. Reload before removing it.', 'ohmylms' ), 409 );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Audit a specified account for curriculum administrators only.
	 *
	 * @param WP_REST_Request $request Learner ID.
	 * @return mixed
	 */
	public function student_audit( WP_REST_Request $request ) {
		$id = absint( $request['student_id'] );
		if ( ! get_user_by( 'id', $id ) ) {
			return Access::error( 'ohmylms_student_missing', __( 'Student not found.', 'ohmylms' ), 404 );
		}
		return rest_ensure_response( \OhMyLMS\Curriculum\SubjectProgress::all_progress( $id ) );
	}

	/** Read the signed-in learner's progress; a student_id parameter is ignored. */
	public function progress() {
		return rest_ensure_response( \OhMyLMS\Curriculum\SubjectProgress::all_progress( get_current_user_id() ) );
	}
}
