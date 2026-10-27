<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Core WordPress authoring endpoints for native quiz documents.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\Assessment\ErrorException;
use OhMyLMS\Assessment\RevisionPublisher;
use OhMyLMS\Assessment\Engine;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/** Preserve the lesson editor's author-only REST boundary for quiz documents. */
class NativeQuizController extends NativeLessonController {
	/**
	 * Whether this controller is preparing the response to its own atomic save.
	 *
	 * @var bool
	 */
	private $saving = false;
	/**
	 * Include the current quiz placements in the native editing document.
	 *
	 * @param \WP_Post         $post Quiz post.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response Prepared response.
	 */
	public function prepare_item_for_response( $post, $request ) {
		$response = parent::prepare_item_for_response( $post, $request );
		if ( 'edit' === $request['context'] && ! $this->saving ) {
			$data                   = $response->get_data();
			$data['content']['raw'] = NativeQuizEditor::editor_content( $post );
			$response->set_data( $data );
		}
		return $response;
	}

	/**
	 * Save the WordPress post and ordered assessment placements in one transaction.
	 * Cookie authentication and nonces use the core REST controller.
	 *
	 * @param \WP_REST_Request $request Native save request.
	 * @return \WP_REST_Response|\WP_Error Result without a second learner tracking flow.
	 */
	public function update_item( $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return new \WP_Error( 'rest_cannot_update', __( 'You cannot edit this quiz.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return $this->save_document( $request, $post );
	}

	/**
	 * Support native core creation as well as drafts created through the existing LMS API.
	 *
	 * @param \WP_REST_Request $request Native creation request.
	 * @return \WP_REST_Response|\WP_Error Saved document.
	 */
	public function create_item( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'rest_cannot_create', __( 'You cannot create quizzes.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return $this->save_document( $request );
	}

	/**
	 * Normalize references and atomically save the core post and quiz document.
	 *
	 * @param \WP_REST_Request $request Native save request.
	 * @param \WP_Post|null    $post Existing post, or null for creation.
	 * @return \WP_REST_Response|\WP_Error Save response.
	 */
	private function save_document( $request, $post = null ) {
		$content = $request->has_param( 'content' ) ? $request['content'] : ( $post ? NativeQuizEditor::editor_content( $post ) : '' );
		$content = is_array( $content ) ? ( $content['raw'] ?? '' ) : $content;
		$plan    = NativeQuizEditor::prepare_document( (string) $content );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$request->set_param( 'content', $plan['content'] );
		$post_id = $post ? $post->ID : 0;
		try {
			$this->saving = true;
			return Transaction::run(
				function () use ( $request, $plan, $post, &$post_id ) {
					$response = ErrorException::raise( $post ? parent::update_item( $request ) : parent::create_item( $request ) );
					$post_id  = (int) $response->get_data()['id'];
					NativeQuizEditor::sync( $post_id, $plan['ids'] );
					if ( $request->has_param( 'ohmylms_quiz' ) ) {
						ErrorException::raise( NativeQuizEditor::save_assessment( $post_id, (array) $request['ohmylms_quiz'] ) );
					}
					if ( 'publish' === get_post_status( $post_id ) && Schema::ready() && Engine::versioned() ) {
						ErrorException::raise( RevisionPublisher::publish( $post_id ) );
					}
					$data                 = $response->get_data();
					$data['ohmylms_quiz'] = NativeQuizEditor::read_settings( array( 'id' => $post_id ) );
					$response->set_data( $data );
					return $response;
				}
			);
		} catch ( ErrorException $error ) {
			clean_post_cache( $post_id );
			return $error->error;
		} catch ( \Throwable $error ) {
			clean_post_cache( $post_id );
			return new \WP_Error( 'ohmylms_native_quiz_save', __( 'The quiz could not be saved. Your block changes remain in the editor.', 'ohmylms' ), array( 'status' => 500 ) );
		} finally {
			$this->saving = false;
		}
	}
}
