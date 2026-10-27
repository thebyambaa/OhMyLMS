<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * ReportingApi class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Zoom\Includes\Api\Endpoints;

use OhMyLMS\Integrations\Zoom\Includes\Api\ZoomApiClient;

/**
 * Class ReportingApi
 *
 * @package OhMyLMS\Integrations\Zoom\Api\Endpoints
 * @since 1.0.0
 */
class ReportingApi {
	/**
	 * The ZoomApiClient instance.
	 *
	 * @var ZoomApiClient
	 */
	private $client;

	/**
	 * ReportingApi constructor.
	 *
	 * @param ZoomApiClient $client The ZoomApiClient instance.
	 */
	public function __construct( ZoomApiClient $client ) {
		$this->client = $client;
	}

	/**
	 * Get meeting participant report.
	 *
	 * @param int   $meeting_id The meeting ID.
	 * @param array $params     The query parameters.
	 *
	 * @return array The API response.
	 */
	public function get_meeting_participants( $meeting_id, $params = array() ) {
		return $this->client->get( "report/meetings/{$meeting_id}/participants", $params );
	}

	/**
	 * Get webinar participant report.
	 *
	 * @param int   $webinar_id The webinar ID.
	 * @param array $params     The query parameters.
	 *
	 * @return array The API response.
	 */
	public function get_webinar_participants( $webinar_id, $params = array() ) {
		return $this->client->get( "report/webinars/{$webinar_id}/participants", $params );
	}
}
