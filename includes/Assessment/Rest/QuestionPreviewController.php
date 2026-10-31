<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Inline question preview REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assessment\Rest;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\QuestionPreview;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `POST ohmylms/v1/question-preview`: store a learner-safe view of an unsaved question and return the
 * private frame URL. {@see QuestionPreview} renders the view and serves the frame.
 */
class QuestionPreviewController extends RestController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/question-preview',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'callback'            => array( $this, 'create_item' ),
				'args'                => array(
					'post_id'        => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'question'       => array(
						'type'       => 'object',
						'required'   => true,
						'properties' => array(
							'id'          => array(
								'type'    => 'integer',
								'minimum' => 0,
							),
							'name'        => array( 'type' => 'string' ),
							'description' => array( 'type' => 'string' ),
							'image_src'   => array( 'type' => 'string' ),
							'video_src'   => array( 'type' => 'string' ),
							'settings'    => array( 'type' => 'object' ),
							'questions'   => array(
								'type'     => 'array',
								'maxItems' => 1000,
								'items'    => array( 'type' => 'object' ),
							),
						),
					),
					'settings'       => array(
						'type'    => 'object',
						'default' => array(),
					),
					'kind'           => array(
						'type'    => 'string',
						'default' => 'quiz',
					),
					'ordinal'        => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 10000,
						'default' => 1,
					),
					'question_count' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 10000,
						'default' => 1,
					),
					'seed'           => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			)
		);
	}

	/**
	 * Access to the authoring document and to any referenced question.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function create_item_permissions_check( $request ) {
		return QuestionPreview::permission( $request );
	}

	/**
	 * Create a short-lived preview session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error Frame URL.
	 */
	public function create_item( $request ) {
		return QuestionPreview::create( $request );
	}
}
