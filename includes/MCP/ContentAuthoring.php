<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Bounded authoring operations shared by REST and MCP.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\MCP;

use OhMyLMS\Curriculum\Items;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\NativeLessonEditor;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\SkillMap;
use OhMyLMS\QuestionBank\VersionPublisher;

defined( 'ABSPATH' ) || exit;

/** Preserve native lesson settings and versioned question contracts. */
final class ContentAuthoring {

	/**
	 * Content hash used for optimistic authoring and review freshness.
	 *
	 * @param array $data Snapshot.
	 * @return string
	 */
	public static function revision( array $data ) {
		return hash( 'sha256', wp_json_encode( $data ) );
	}

	/**
	 * Read editable raw content without creating versions or changing placement.
	 *
	 * @param string $kind Lesson or question.
	 * @param int    $id Content ID.
	 * @return array|\WP_Error
	 */
	public static function read( $kind, $id ) {
		$post = get_post( $id );
		$type = 'lesson' === $kind ? OHMYLMS_LESSON_CPT : OHMYLMS_QUESTION_CPT;
		if ( ! $post || $type !== $post->post_type || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return Catalog::error( __( 'Editable content was not found.', 'ohmylms' ), 404 );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return Catalog::error( __( 'You cannot edit this content.', 'ohmylms' ), 403 );
		}
		$data = array(
			'id'     => (int) $id,
			'name'   => $post->post_title,
			'status' => $post->post_status,
		);
		if ( 'lesson' === $kind ) {
			$data['content']  = $post->post_content;
			$data['settings'] = NativeLessonEditor::read_settings( array( 'id' => $id ) );
		} else {
			$question                    = ohmylms_get_question( $id );
			$identity                    = VersionPublisher::identity( $id, false );
			$data['post_status']         = $data['status'];
			$data['status']              = $identity['status'] ?? 'draft';
			$data['version_id']          = (int) ( $identity['current_version_id'] ?? 0 );
			$data['approved_version_id'] = (int) ( $identity['approved_version_id'] ?? 0 );
			$data['description']         = $question->get_description();
			$data['settings']            = $question->get_settings();
			$data['questions']           = $question->get_questions();
			$data['skills']              = SkillMap::current( $id );
		}
		$data['revision'] = self::revision( $data );
		return $data;
	}

	/**
	 * Save a draft lesson or question through its established writer.
	 *
	 * @param string $kind Content kind.
	 * @param array  $data Authoring request.
	 * @return array|\WP_Error
	 */
	public static function save( $kind, array $data ) {
		return Items::exclusive(
			static function () use ( $kind, $data ) {
				$id = (int) ( $data['id'] ?? 0 );
				if ( $id ) {
					clean_post_cache( $id );
					$current = self::read( $kind, $id );
					if ( is_wp_error( $current ) ) {
						return $current;
					}
					if ( ( $data['revision'] ?? '' ) !== $current['revision'] ) {
						return Catalog::error( __( 'Content changed. Read the latest content before saving.', 'ohmylms' ), 409 );
					}
					if ( 'lesson' === $kind && 'publish' === $current['status'] && empty( $data['allow_published'] ) ) {
						return Catalog::error( __( 'Editing a published lesson changes live content. Set allow_published explicitly to proceed.', 'ohmylms' ), 409 );
					}
				} elseif ( empty( $data['name'] ) || ! is_string( $data['name'] ) || '' === trim( $data['name'] ) ) {
					return Catalog::error( __( 'New content requires a name.', 'ohmylms' ) );
				}
				if ( 'question' === $kind ) {
					$payload = array_intersect_key( $data, array_flip( array( 'id', 'name', 'description', 'settings', 'questions', 'skills' ) ) );
					$saved   = DraftWriter::save( $payload );
					return is_wp_error( $saved ) ? $saved : self::read( $kind, $saved['id'] );
				}
				$payload = array( 'context' => 'edit' );
				if ( ! $id ) {
					$payload['status'] = 'draft';
				}
				foreach ( array(
					'name'     => 'title',
					'content'  => 'content',
					'settings' => 'ohmylms_lesson_settings',
				) as $source => $target ) {
					if ( array_key_exists( $source, $data ) ) {
						$payload[ $target ] = $data[ $source ];
					}
				}
				$request = new \WP_REST_Request( 'POST', '/wp/v2/ohmylms-lessons' . ( $id ? '/' . $id : '' ) );
				$request->set_header( 'Content-Type', 'application/json' );
				$request->set_body( wp_json_encode( $payload ) );
				$response = rest_do_request( $request );
				if ( $response->get_status() >= 400 ) {
					$error = $response->get_data();
					return Catalog::error( $error['message'] ?? __( 'Lesson could not be saved.', 'ohmylms' ), $response->get_status() );
				}
				return self::read( $kind, $response->get_data()['id'] );
			}
		);
	}
}
