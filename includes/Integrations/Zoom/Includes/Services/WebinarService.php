<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * WebinarService class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Zoom\Includes\Services;

use OhMyLMS\Integrations\Zoom\Includes\Api\Endpoints\WebinarApi;

/**
 * Class WebinarService
 *
 * @package OhMyLMS\Integrations\Zoom\Services
 * @since 1.0.0
 */
class WebinarService {
	/**
	 * The WebinarApi instance.
	 *
	 * @var WebinarApi
	 */
	private $webinar_api;

	/**
	 * WebinarService constructor.
	 *
	 * @param WebinarApi $webinar_api The WebinarApi instance.
	 */
	public function __construct( WebinarApi $webinar_api ) {
		$this->webinar_api = $webinar_api;
	}

	/**
	 * Create a new webinar.
	 *
	 * @param string $user_id The user ID.
	 * @param array  $data    The webinar data.
	 *
	 * @return array The API response.
	 */
	public function create_webinar( $user_id, $data ) {
		// Add any business logic here before creating the webinar.
		return $this->webinar_api->create( $user_id, $data );
	}

	/**
	 * Get the details of a webinar.
	 *
	 * @param int $webinar_id The webinar ID.
	 *
	 * @return array The API response.
	 */
	public function get_webinar( $webinar_id ) {
		return $this->webinar_api->get( $webinar_id );
	}

	/**
	 * Update a webinar.
	 *
	 * @param int   $webinar_id The webinar ID.
	 * @param array $data       The webinar data.
	 *
	 * @return array The API response.
	 */
	public function update_webinar( $webinar_id, $data ) {
		// Add any business logic here before updating the webinar.
		return $this->webinar_api->update( $webinar_id, $data );
	}

	/**
	 * Delete a webinar.
	 *
	 * @param int $webinar_id The webinar ID.
	 *
	 * @return array The API response.
	 */
	public function delete_webinar( $webinar_id ) {
		return $this->webinar_api->delete( $webinar_id );
	}

	/**
	 * List all webinars for a user.
	 *
	 * @param string $user_id The user ID.
	 * @param array  $params  The query parameters.
	 *
	 * @return array The API response.
	 */
	public function list_webinars( $user_id, $params = array() ) {
		return $this->webinar_api->list( $user_id, $params );
	}
}
