<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Explicit course settings contract for the typed editor.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseSettings;
use OhMyLMS\Learning\CourseStructure;

defined( 'ABSPATH' ) || exit;

/** Separate metadata writes from the legacy unrestricted course payload. */
class CourseSettingsController extends \WP_REST_Controller {

	/** Register the settings resource. */
	public function register_routes() {
		register_rest_route(
			'ohmylms/v1',
			'/authoring/courses/(?P<id>\d+)/settings',
			array(
				array(
					'permission_callback' => array( $this, 'permission' ),
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
				),
				array(
					'permission_callback' => array( $this, 'permission' ),
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_item' ),
				),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/authoring/courses/(?P<id>\d+)/publication',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'preview' ),
					'permission_callback' => array( $this, 'permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'publish' ),
					'permission_callback' => array( $this, 'permission' ),
				),
			)
		);
	}

	/**
	 * Protect author details and course mutations independently of the UI.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function permission( $request ) {
		return current_user_can( 'manage_options' ) && Catalog::can_edit( (int) $request['id'] ) ? true : Catalog::error( __( 'You cannot edit these course settings.', 'ohmylms' ), rest_authorization_required_code() );
	}

	/**
	 * Read a coherent snapshot with a private optimistic concurrency token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function get_item( $request ) {
		$id = (int) $request['id'];
		return CourseStructure::read(
			$id,
			static function () use ( $id ) {
				$response = rest_ensure_response( CourseSettings::get( $id ) );
				$response->header( 'Cache-Control', 'private, no-store' );
				return $response;
			}
		);
	}

	/**
	 * Save only the validated settings, retaining unrelated metadata.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function update_item( $request ) {
		$id = (int) $request['id'];
		return CourseStructure::read(
			$id,
			static function () use ( $id, $request ) {
				return CourseStructure::change(
					$id,
					Catalog::revision( $id ),
					static function () use ( $id, $request ) {
						return CourseSettings::save( $id, $request->get_json_params() );
					}
				);
			}
		);
	}

	/**
	 * Preview the saved draft and its readiness under the shared read lock.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function preview( $request ) {
		$id = (int) $request['id'];
		return CourseStructure::read(
			$id,
			static function () use ( $id ) {
				$response = rest_ensure_response( CourseSettings::preview( $id ) );
				$response->header( 'Cache-Control', 'private, no-store' );
				return $response;
			}
		);
	}

	/**
	 * Publish exactly the reviewed snapshot, retaining existing enrollment versions.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function publish( $request ) {
		$id   = (int) $request['id'];
		$type = get_post_type_object( OHMYLMS_COURSE_CPT );
		if ( ! current_user_can( $type->cap->publish_posts ) ) {
			return Catalog::error( __( 'You cannot publish this course.', 'ohmylms' ), 403 );
		}
		return CourseStructure::read(
			$id,
			static function () use ( $id, $request ) {
				return CourseStructure::change(
					$id,
					Catalog::revision( $id ),
					static function () use ( $id, $request ) {
						$data = $request->get_json_params();
						if ( ! is_array( $data ) || array_diff( array_keys( $data ), array( 'revision', 'confirmed' ) ) || true !== ( $data['confirmed'] ?? null ) || ! is_string( $data['revision'] ?? null ) ) {
							return Catalog::error( __( 'Confirm publication of the reviewed revision.', 'ohmylms' ) );
						}
						if ( ! hash_equals( CourseSettings::get( $id )['revision'], $data['revision'] ) ) {
							return Catalog::error( __( 'The course changed after preview. Review it again before publishing.', 'ohmylms' ), 409 );
						}
						$result = Catalog::publish( $id, false );
						if ( is_wp_error( $result ) ) {
							return $result;
						}
						$result = wp_update_post(
							array(
								'ID'          => $id,
								'post_status' => 'publish',
							),
							true
						);
						return is_wp_error( $result ) ? $result : CourseSettings::preview( $id );
					}
				);
			}
		);
	}
}
