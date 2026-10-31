<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Skill workspace routes: combined mappings, shared content links, domain placement, skill-owned
 * quizzes and provenance review. See docs/api/skill-workspace.md.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\Access;
use OhMyLMS\Curriculum\CombinedMappings;
use OhMyLMS\Curriculum\ContentLinks;
use OhMyLMS\Curriculum\PlacementBatch;
use OhMyLMS\Skills\ProvenanceReview;
use OhMyLMS\Skills\SkillQuizzes;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/** Typed arguments reject malformed input before any domain code runs; every route is curriculum-admin only. */
class SkillWorkspaceController extends RestController {

	/** Register the routes. */
	public function register_routes() {
		$id      = array(
			'type'     => 'integer',
			'minimum'  => 1,
			'required' => true,
		);
		$page    = array(
			'type'    => 'integer',
			'minimum' => 1,
			'maximum' => 10000,
			'default' => 1,
		);
		$search  = array(
			'type'      => 'string',
			'maxLength' => 200,
			'default'   => '',
		);
		$kind    = array(
			'type'     => 'string',
			'enum'     => array_keys( ContentLinks::kinds() ),
			'required' => true,
		);
		$ids     = array(
			'type'        => 'array',
			'maxItems'    => ContentLinks::MAX_CHANGES,
			'uniqueItems' => true,
			'default'     => array(),
			'items'       => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
		);
		$sha256  = array(
			'type'     => 'string',
			'pattern'  => '^[a-f0-9]{64}$',
			'required' => true,
		);
		$level   = array(
			'type'    => 'integer',
			'minimum' => 0,
			'maximum' => 100,
		);
		$base    = '/skill-workspace';
		$content = $base . '/content/(?P<kind>[a-z][a-z0-9_-]{0,39})/(?P<id>\d+)';
		$routes  = array(
			$base . '/skills/(?P<id>\d+)/mappings'         => array(
				array( 'GET', 'mappings', array( 'id' => $id ) ),
			),
			$base . '/content'                             => array(
				array(
					'GET',
					'search_content',
					array(
						'kind'   => $kind,
						'search' => $search,
						'page'   => $page,
					),
				),
			),
			$content                                       => array(
				array(
					'GET',
					'read_content',
					array(
						'kind' => $kind,
						'id'   => $id,
					),
				),
				array(
					'PUT',
					'update_content',
					array(
						'kind'              => $kind,
						'id'                => $id,
						'add'               => $ids,
						'remove'            => $ids,
						'expected_revision' => $sha256,
					),
				),
			),
			$base . '/placement'                           => array(
				array(
					'GET',
					'placement',
					array(
						'subject'  => array(
							'type'      => 'string',
							'maxLength' => 100,
							'default'   => '',
						),
						'search'   => $search,
						'domain'   => array(
							'type'      => 'string',
							'maxLength' => 40,
							'default'   => '',
						),
						'unplaced' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'page'     => $page,
					),
				),
				array(
					'PUT',
					'save_placement',
					array(
						'items' => array(
							'type'     => 'array',
							'required' => true,
							'minItems' => 1,
							'maxItems' => PlacementBatch::MAX_ITEMS,
							'items'    => array(
								'type'                 => 'object',
								'required'             => array( 'id', 'domain', 'grade_from', 'grade_to', 'expected_revision' ),
								'additionalProperties' => false,
								'properties'           => array(
									'id'                => array(
										'type'    => 'integer',
										'minimum' => 1,
									),
									'domain'            => array(
										'type'      => 'string',
										'maxLength' => 40,
									),
									'grade_from'        => $level,
									'grade_to'          => $level,
									'expected_revision' => array(
										'type'    => 'string',
										'pattern' => '^[a-f0-9]{64}$',
									),
								),
							),
						),
					),
				),
			),
			$base . '/skills/(?P<id>\d+)/quizzes'          => array(
				array( 'GET', 'quizzes', array( 'id' => $id ) ),
				array(
					'POST',
					'generate_quiz',
					array(
						'id'         => $id,
						'title'      => array(
							'type'      => 'string',
							'maxLength' => 190,
							'default'   => '',
						),
						'per_skill'  => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 10,
							'default' => 5,
						),
						'time_limit' => array(
							'type'    => 'integer',
							'minimum' => 0,
							'maximum' => 600,
							'default' => 0,
						),
					),
				),
			),
			$base . '/skills/(?P<id>\d+)/quizzes/(?P<quiz_id>\d+)' => array(
				array(
					'PUT',
					'link_quiz',
					array(
						'id'      => $id,
						'quiz_id' => $id,
						'linked'  => array(
							'type'     => 'boolean',
							'required' => true,
						),
					),
				),
			),
			$base . '/provenance'                          => array(
				array(
					'GET',
					'provenance',
					array(
						'review' => array(
							'type'    => 'string',
							'enum'    => array( 'pending', 'rejected', 'approved', 'all' ),
							'default' => 'pending',
						),
						'source' => array(
							'type'      => 'string',
							'maxLength' => 40,
							'default'   => '',
						),
						'search' => $search,
						'page'   => $page,
					),
				),
			),
			$base . '/provenance/(?P<id>\d+)'              => array(
				array( 'GET', 'provenance_detail', array( 'id' => $id ) ),
			),
			$base . '/provenance/review'                   => array(
				array(
					'POST',
					'review',
					array(
						'items'  => array(
							'type'     => 'array',
							'required' => true,
							'minItems' => 1,
							'maxItems' => ProvenanceReview::MAX_REVIEW,
							'items'    => array(
								'type'                 => 'object',
								'required'             => array( 'id', 'expected_review' ),
								'additionalProperties' => false,
								'properties'           => array(
									'id'              => array(
										'type'    => 'integer',
										'minimum' => 1,
									),
									'expected_review' => array(
										'type' => 'string',
										'enum' => array( 'approved', 'pending', 'rejected' ),
									),
								),
							),
						),
						'review' => array(
							'type'     => 'string',
							'enum'     => array( 'approved', 'rejected' ),
							'required' => true,
						),
						'note'   => array(
							'type'      => 'string',
							'maxLength' => 500,
							'default'   => '',
						),
					),
				),
			),
		);
		foreach ( $routes as $route => $endpoints ) {
			register_rest_route(
				$this->namespace,
				$route,
				array_map(
					function ( $endpoint ) {
						return array(
							'methods'             => $endpoint[0],
							'callback'            => array( $this, $endpoint[1] ),
							'permission_callback' => array( Access::class, 'admin' ),
							'args'                => $endpoint[2],
						);
					},
					$endpoints
				)
			);
		}
	}

	/**
	 * Wrap a domain result.
	 *
	 * @param mixed $result Array or WP_Error.
	 * @return mixed
	 */
	private function respond( $result ) {
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * A skill's mappings across syllabuses, courses, content and questions.
	 *
	 * @param WP_REST_Request $request Skill ID.
	 * @return mixed
	 */
	public function mappings( WP_REST_Request $request ) {
		return $this->respond( CombinedMappings::describe( (int) $request['id'] ) );
	}

	/**
	 * Search content of a kind.
	 *
	 * @param WP_REST_Request $request kind, search, page.
	 * @return mixed
	 */
	public function search_content( WP_REST_Request $request ) {
		return $this->respond( ContentLinks::search( (string) $request['kind'], (string) $request['search'], (int) $request['page'] ) );
	}

	/**
	 * The skills one piece of content is linked to.
	 *
	 * @param WP_REST_Request $request kind and ID.
	 * @return mixed
	 */
	public function read_content( WP_REST_Request $request ) {
		return $this->respond( ContentLinks::read( (string) $request['kind'], (int) $request['id'] ) );
	}

	/**
	 * Add and remove skills on one piece of content.
	 *
	 * @param WP_REST_Request $request kind, ID, add, remove, expected_revision.
	 * @return mixed
	 */
	public function update_content( WP_REST_Request $request ) {
		return $this->respond( ContentLinks::update( (string) $request['kind'], (int) $request['id'], (array) $request['add'], (array) $request['remove'], (string) $request['expected_revision'] ) );
	}

	/**
	 * Shared skills with their placement.
	 *
	 * @param WP_REST_Request $request Filters.
	 * @return mixed
	 */
	public function placement( WP_REST_Request $request ) {
		return $this->respond( PlacementBatch::listing( $request->get_params() ) );
	}

	/**
	 * Store several placements.
	 *
	 * @param WP_REST_Request $request items.
	 * @return mixed
	 */
	public function save_placement( WP_REST_Request $request ) {
		return $this->respond( PlacementBatch::save( (array) $request['items'] ) );
	}

	/**
	 * Quizzes a skill owns.
	 *
	 * @param WP_REST_Request $request Skill ID.
	 * @return mixed
	 */
	public function quizzes( WP_REST_Request $request ) {
		return $this->respond( SkillQuizzes::listing( (int) $request['id'] ) );
	}

	/**
	 * Generate a draft quiz from a skill.
	 *
	 * @param WP_REST_Request $request Skill ID, title, per_skill, time_limit.
	 * @return mixed
	 */
	public function generate_quiz( WP_REST_Request $request ) {
		$result = SkillQuizzes::generate(
			(int) $request['id'],
			array(
				'title'      => (string) $request['title'],
				'per_skill'  => (int) $request['per_skill'],
				'time_limit' => (int) $request['time_limit'],
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = rest_ensure_response( $result );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Connect or disconnect a quiz.
	 *
	 * @param WP_REST_Request $request Skill ID, quiz ID, linked.
	 * @return mixed
	 */
	public function link_quiz( WP_REST_Request $request ) {
		return $this->respond( SkillQuizzes::link( (int) $request['id'], (int) $request['quiz_id'], (bool) $request['linked'] ) );
	}

	/**
	 * The provenance review queue.
	 *
	 * @param WP_REST_Request $request review, source, search, page.
	 * @return mixed
	 */
	public function provenance( WP_REST_Request $request ) {
		return $this->respond( ProvenanceReview::queue( $request->get_params() ) );
	}

	/**
	 * One skill's provenance, audit trail and mapping provenance.
	 *
	 * @param WP_REST_Request $request Skill ID.
	 * @return mixed
	 */
	public function provenance_detail( WP_REST_Request $request ) {
		return $this->respond( ProvenanceReview::detail( (int) $request['id'] ) );
	}

	/**
	 * Approve or reject several skills.
	 *
	 * @param WP_REST_Request $request items, review, note.
	 * @return mixed
	 */
	public function review( WP_REST_Request $request ) {
		return $this->respond( ProvenanceReview::review( (array) $request['items'], (string) $request['review'], (string) $request['note'] ) );
	}
}
