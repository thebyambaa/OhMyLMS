<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * UserService class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Zoom\Includes\Services;

use OhMyLMS\Integrations\Zoom\Includes\Api\Endpoints\UserApi;

/**
 * Class UserService
 *
 * @package OhMyLMS\Integrations\Zoom\Services
 * @since 1.0.0
 */
class UserService {
	/**
	 * The UserApi instance.
	 *
	 * @var UserApi
	 */
	private $user_api;

	/**
	 * UserService constructor.
	 *
	 * @param UserApi $user_api The UserApi instance.
	 */
	public function __construct( UserApi $user_api ) {
		$this->user_api = $user_api;
	}

	/**
	 * Get the details of a user.
	 *
	 * @param string $user_id The user ID.
	 *
	 * @return array The API response.
	 */
	public function get_user( $user_id ) {
		return $this->user_api->get( $user_id );
	}

	/**
	 * Create a new user.
	 *
	 * @param array $data The user data.
	 *
	 * @return array The API response.
	 */
	public function create_user( $data ) {
		// Add any business logic here before creating the user.
		return $this->user_api->create( $data );
	}

	/**
	 * Update a user.
	 *
	 * @param string $user_id The user ID.
	 * @param array  $data    The user data.
	 *
	 * @return array The API response.
	 */
	public function update_user( $user_id, $data ) {
		// Add any business logic here before updating the user.
		return $this->user_api->update( $user_id, $data );
	}
}
