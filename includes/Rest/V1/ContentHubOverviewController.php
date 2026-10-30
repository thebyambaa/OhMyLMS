<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Content Hub overview: counts per content area and recently changed items.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Bundles\Bundles;
use OhMyLMS\ContentHub\ContentCounts;
use OhMyLMS\Curriculum\Access as CurriculumAccess;
use OhMyLMS\Curriculum\Items as CurriculumItems;
use OhMyLMS\Curriculum\Schema as CurriculumSchema;
use OhMyLMS\Exams\Library;
use OhMyLMS\Quiz\AttemptReports;
use OhMyLMS\Quiz\GradingPolicy;
use OhMyLMS\Skills\Provenance;
use OhMyLMS\Skills\Taxonomy;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `GET ohmylms/v1/content-hub/overview` (see docs/api/content-hub.md).
 *
 * Read-only. Each area is included only when the user may open it: post-type areas need the type's
 * `edit_posts`, lessons need `manage_options` (as the lesson screens do), curriculum and bundles need
 * {@see CurriculumAccess::can_manage()} and installed curriculum tables. Counts of a post type cover
 * every item when the user has the type's `edit_others_posts`, otherwise only the user's own items.
 * No user data is returned beyond item titles.
 */
class ContentHubOverviewController extends RestController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'content-hub/overview';

	/**
	 * Largest number of recent items.
	 */
	const MAX_RECENT = 20;

	/**
	 * Post statuses that count as content (trash and auto-drafts never do).
	 */
	const STATUSES = array( 'publish', 'draft', 'pending', 'future', 'private' );

	/**
	 * Register the route.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'recent' => array(
							'description' => __( 'Number of recently changed items to return.', 'ohmylms' ),
							'type'        => 'integer',
							'default'     => 8,
							'minimum'     => 0,
							'maximum'     => self::MAX_RECENT,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Authors only: 401 for guests, 403 for users who cannot write posts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		return new WP_Error( 'ohmylms_content_hub_cannot_view', __( 'Sorry, you are not allowed to view the Content Hub.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Post-type areas the current user may open, by area id.
	 *
	 * @return array<string,array{type:string,own:bool}>
	 */
	private function post_areas() {
		$types = array(
			'courses'       => OHMYLMS_COURSE_CPT,
			'lessons'       => OHMYLMS_LESSON_CPT,
			'quizzes'       => OHMYLMS_QUIZ_CPT,
			'exams'         => Library::TYPE,
			'question-bank' => OHMYLMS_QUESTION_CPT,
			'assignments'   => OHMYLMS_ASSIGNMENT_CPT,
		);
		$areas = array();
		foreach ( $types as $area => $post_type ) {
			$object = get_post_type_object( $post_type );
			if ( ! $object || ! current_user_can( $object->cap->edit_posts ) ) {
				continue;
			}
			if ( 'lessons' === $area && ! current_user_can( 'manage_options' ) ) {
				continue;
			}
			$areas[ $area ] = array(
				'type' => $post_type,
				'own'  => ! current_user_can( $object->cap->edit_others_posts ),
			);
		}

		return $areas;
	}

	/**
	 * Read the overview.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		$post_areas = $this->post_areas();
		$all_types  = array();
		$own_types  = array();
		foreach ( $post_areas as $area ) {
			if ( $area['own'] ) {
				$own_types[] = $area['type'];
			} else {
				$all_types[] = $area['type'];
			}
		}
		$counts = ContentCounts::by_status( $all_types, $own_types, get_current_user_id(), self::STATUSES );
		$recent = ContentCounts::recent( $all_types, $own_types, get_current_user_id(), self::STATUSES, (int) $request['recent'] );

		$areas = array();
		foreach ( $post_areas as $id => $area ) {
			$by_status = isset( $counts[ $area['type'] ] ) ? $counts[ $area['type'] ] : array();
			$entry     = array(
				'id'     => $id,
				'total'  => (int) array_sum( $by_status ),
				'counts' => array(
					'published' => (int) ( $by_status['publish'] ?? 0 ),
					'drafts'    => (int) ( $by_status['draft'] ?? 0 ) + (int) ( $by_status['pending'] ?? 0 ),
				),
			);
			if ( 'quizzes' === $id ) {
				$entry['counts']['to_grade'] = $this->attempts_to_grade();
			}
			$areas[] = $entry;
		}
		if ( current_user_can( 'edit_posts' ) && taxonomy_exists( Taxonomy::NAME ) ) {
			$areas[] = $this->skills_area();
		}
		if ( CurriculumAccess::can_manage() && CurriculumSchema::ready() ) {
			$areas[] = $this->curriculum_area();
			$areas[] = $this->bundles_area();
		}

		$types = array_flip( wp_list_pluck( $post_areas, 'type' ) );
		$items = array();
		foreach ( (array) $recent as $row ) {
			$items[] = array(
				'area'       => $types[ (string) $row->post_type ],
				'id'         => (int) $row->ID,
				'title'      => html_entity_decode( wp_strip_all_tags( (string) $row->post_title ), ENT_QUOTES, 'UTF-8' ),
				'status'     => 'publish' === $row->post_status ? 'publish' : ( 'future' === $row->post_status || 'private' === $row->post_status ? (string) $row->post_status : 'draft' ),
				'updated_at' => $this->iso( str_starts_with( (string) $row->post_modified_gmt, '0000' ) ? get_gmt_from_date( (string) $row->post_modified ) : (string) $row->post_modified_gmt ),
			);
		}

		$response = rest_ensure_response(
			array(
				'areas'  => $areas,
				'recent' => $items,
			)
		);
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Attempts waiting for manual marks in the quizzes the user may grade.
	 *
	 * @return int
	 */
	private function attempts_to_grade() {
		return AttemptReports::count_to_grade( GradingPolicy::gradable_quiz_ids() );
	}

	/**
	 * Skills: every skill term, and how many are archived.
	 *
	 * @return array<string,mixed>
	 */
	private function skills_area() {
		$counts = Provenance::archive_counts();

		return array(
			'id'     => 'skills',
			'total'  => $counts['total'],
			'counts' => array( 'archived' => $counts['archived'] ),
		);
	}

	/**
	 * Curriculum: items of every level, and how many are syllabuses.
	 *
	 * @return array<string,mixed>
	 */
	private function curriculum_area() {
		$counts = CurriculumItems::counts();

		return array(
			'id'     => 'curriculum',
			'total'  => $counts['total'],
			'counts' => array( 'syllabuses' => $counts['syllabuses'] ),
		);
	}

	/**
	 * Course bundles by status.
	 *
	 * @return array<string,mixed>
	 */
	private function bundles_area() {
		$counts = Bundles::status_counts();

		return array(
			'id'     => 'bundles',
			'total'  => $counts['total'],
			'counts' => array(
				'published' => $counts['published'],
				'drafts'    => $counts['drafts'],
			),
		);
	}

	/**
	 * Convert a GMT MySQL date to ISO 8601 (UTC), or null when unset.
	 *
	 * @param string $gmt Date in `Y-m-d H:i:s`.
	 * @return string|null
	 */
	private function iso( $gmt ) {
		$time = '' !== $gmt && '0000-00-00 00:00:00' !== $gmt ? strtotime( $gmt . ' UTC' ) : 0;

		return $time ? gmdate( 'Y-m-d\TH:i:s\Z', $time ) : null;
	}

	/**
	 * Response schema.
	 *
	 * @return array<string,mixed>
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->schema;
		}
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ohmylms-content-hub-overview',
			'type'       => 'object',
			'properties' => array(
				'areas'  => array(
					'description' => __( 'Content areas the user may open, with counts.', 'ohmylms' ),
					'type'        => 'array',
					'readonly'    => true,
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'     => array(
								'type' => 'string',
								'enum' => array( 'courses', 'lessons', 'quizzes', 'exams', 'question-bank', 'assignments', 'skills', 'curriculum', 'bundles' ),
							),
							'total'  => array( 'type' => 'integer' ),
							'counts' => array(
								'type'                 => 'object',
								'additionalProperties' => array( 'type' => 'integer' ),
							),
						),
					),
				),
				'recent' => array(
					'description' => __( 'Recently changed courses, lessons, quizzes, exams, questions and assignments.', 'ohmylms' ),
					'type'        => 'array',
					'readonly'    => true,
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'area'       => array( 'type' => 'string' ),
							'id'         => array( 'type' => 'integer' ),
							'title'      => array( 'type' => 'string' ),
							'status'     => array(
								'type' => 'string',
								'enum' => array( 'publish', 'draft', 'future', 'private' ),
							),
							'updated_at' => array(
								'type'   => array( 'string', 'null' ),
								'format' => 'date-time',
							),
						),
					),
				),
			),
		);

		return $this->schema;
	}
}
