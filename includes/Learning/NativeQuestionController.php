<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Author-only WordPress document shell for versioned bank questions.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\QuestionBank\AccessPolicy;

defined( 'ABSPATH' ) || exit;

/** Private question content continues to be written by the bank's DraftWriter. */
class NativeQuestionController extends \WP_REST_Posts_Controller {

	/**
	 * Restrict individual and collection results to question editors.
	 *
	 * @param \WP_Post $post Question post.
	 * @return bool Whether its private document may be read.
	 */
	public function check_read_permission( $post ) {
		return AccessPolicy::can_edit_question( $post->ID );
	}

	/**
	 * Restrict collections to authors; individual results are filtered above.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error Permission result.
	 */
	public function get_items_permissions_check( $request ) {
		return AccessPolicy::check( AccessPolicy::can_author() );
	}

	/**
	 * Apply the bank's object permissions, including edit grants.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error Permission result.
	 */
	public function get_item_permissions_check( $request ) {
		return AccessPolicy::check( AccessPolicy::can_edit_question( (int) $request['id'] ) );
	}

	/**
	 * Apply the same permission to native saves.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error Permission result.
	 */
	public function update_item_permissions_check( $request ) {
		return $this->get_item_permissions_check( $request );
	}

	/**
	 * New blank documents are allocated by WordPress's post-new screen.
	 * Complete question creation uses the existing validated bank endpoint.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_Error Unsupported direct creation.
	 */
	public function create_item( $request ) {
		return new \WP_Error( 'ohmylms_question_create', __( 'Create a question from the question bank.', 'ohmylms' ), array( 'status' => 400 ) );
	}

	/**
	 * Supply one reference block without replacing the stored question statement.
	 *
	 * @param \WP_Post         $post Question post.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response Editor shell.
	 */
	public function prepare_item_for_response( $post, $request ) {
		$response = parent::prepare_item_for_response( $post, $request );
		if ( 'edit' === $request['context'] ) {
			$data                   = $response->get_data();
			$data['content']['raw'] = '<!-- wp:ohmylms/shared-multiple-choice ' . wp_json_encode(
				array(
					'questionId' => (int) $post->ID,
					'delivery'   => 'bank',
					'lock'       => array(
						'move'   => true,
						'remove' => true,
					),
				)
			) . ' /-->';
			$response->set_data( $data );
		}
		return $response;
	}

	/**
	 * Never write the editor shell over the private statement or bypass DraftWriter.
	 * Gutenberg saves document status; approval remains an explicit bank action.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error Save result.
	 */
	public function update_item( $request ) {
		$allowed = $this->update_item_permissions_check( $request );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$id = (int) $request['id'];
		if ( ! get_post_meta( $id, '_question_settings', true ) ) {
			return new \WP_Error( 'ohmylms_question_incomplete', __( 'Complete the question and its answer settings before saving.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		foreach ( array( 'content', 'title', 'excerpt', 'meta', 'author', 'featured_media', 'categories', 'tags' ) as $field ) {
			unset( $request[ $field ] );
		}
		return parent::update_item( $request );
	}
}
