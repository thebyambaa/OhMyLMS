<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * WP Fusion API Client
 *
 * Handles all communication with WP Fusion REST API
 *
 * @package OhMyLMS\Integrations\WPFusion\Includes\Api
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\WPFusion\Includes\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin wpfusion api client implementation.
 */
class WPFusionApiClient {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_base_url;

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor
	 *
	 * @param string $api_base_url Base URL for WP Fusion API.
	 * @param string $api_key API key for authentication.
	 */
	public function __construct( $api_base_url = '', $api_key = '' ) {
		$this->api_base_url = $api_base_url;
		$this->api_key      = $api_key;
	}

	/**
	 * Validate API credentials
	 *
	 * @return array Response with success status and message
	 */
	public function validate_credentials() {
		$endpoint = trailingslashit( $this->api_base_url ) . 'wp-json/wpfusion/v1/status';

		$response = wp_remote_get(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$data          = json_decode( $body, true );

		if ( 200 === $response_code ) {
			return array(
				'success' => true,
				'message' => __( 'Connected successfully', 'ohmylms' ),
				'data'    => $data,
			);
		} else {
			return array(
				'success' => false,
				'message' => isset( $data['message'] ) ? $data['message'] : __( 'Invalid API credentials', 'ohmylms' ),
			);
		}
	}

	/**
	 * Get available tags from WP Fusion
	 *
	 * @return array List of available tags
	 */
	public function get_available_tags() {
		$endpoint = trailingslashit( $this->api_base_url ) . 'wp-json/wpfusion/v1/tags';

		$response = wp_remote_get(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
				'data'    => array(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$data          = json_decode( $body, true );

		if ( 200 === $response_code ) {
			return array(
				'success' => true,
				'data'    => $data,
			);
		} else {
			return array(
				'success' => false,
				'message' => isset( $data['message'] ) ? $data['message'] : __( 'Failed to fetch tags', 'ohmylms' ),
				'data'    => array(),
			);
		}
	}

	/**
	 * Apply tag to a user
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $tags Tags to apply.
	 * @return array Response
	 */
	public function apply_tags( $user_id, $tags ) {
		$endpoint = trailingslashit( $this->api_base_url ) . 'wp-json/wpfusion/v1/apply-tags';

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'user_id' => $user_id,
						'tags'    => $tags,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$data          = json_decode( $body, true );

		if ( 200 === $response_code || 201 === $response_code ) {
			return array(
				'success' => true,
				'message' => __( 'Tags applied successfully', 'ohmylms' ),
				'data'    => $data,
			);
		} else {
			return array(
				'success' => false,
				'message' => isset( $data['message'] ) ? $data['message'] : __( 'Failed to apply tags', 'ohmylms' ),
			);
		}
	}

	/**
	 * Remove tag from a user
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $tags Tags to remove.
	 * @return array Response
	 */
	public function remove_tags( $user_id, $tags ) {
		$endpoint = trailingslashit( $this->api_base_url ) . 'wp-json/wpfusion/v1/remove-tags';

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'user_id' => $user_id,
						'tags'    => $tags,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$data          = json_decode( $body, true );

		if ( 200 === $response_code || 201 === $response_code ) {
			return array(
				'success' => true,
				'message' => __( 'Tags removed successfully', 'ohmylms' ),
				'data'    => $data,
			);
		} else {
			return array(
				'success' => false,
				'message' => isset( $data['message'] ) ? $data['message'] : __( 'Failed to remove tags', 'ohmylms' ),
			);
		}
	}

	/**
	 * Update user fields
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $fields Fields to update.
	 * @return array Response
	 */
	public function update_fields( $user_id, $fields ) {
		$endpoint = trailingslashit( $this->api_base_url ) . 'wp-json/wpfusion/v1/update-fields';

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'user_id' => $user_id,
						'fields'  => $fields,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$data          = json_decode( $body, true );

		if ( 200 === $response_code || 201 === $response_code ) {
			return array(
				'success' => true,
				'message' => __( 'Fields updated successfully', 'ohmylms' ),
				'data'    => $data,
			);
		} else {
			return array(
				'success' => false,
				'message' => isset( $data['message'] ) ? $data['message'] : __( 'Failed to update fields', 'ohmylms' ),
			);
		}
	}
}
