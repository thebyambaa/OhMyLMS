<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class Webhook.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Data;

use OhMyLMS\Abstracts\Data;
use OhMyLMS\Webhooks\WebhookDelivery;

defined( 'ABSPATH' ) || exit;

/**
 * Class Webhook
 *
 * @package OhMyLMS\Data
 * @since 1.0.0
 */
class Webhook extends Data {

	/**
	 * Name of the store
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $data_store_name = 'webhook';

	/**
	 * Object type
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $object_type = 'webhook';

	/**
	 * Webhook data array
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected array $data = array(
		'name'          => '',
		'trigger_event' => '',
		'webhook_url'   => '',
		'http_method'   => 'POST',
		'data_type'     => 'json',
		'data_mapping'  => '',
		'secret'        => '',
		'status'        => 'active',
		'created_at'    => null,
		'updated_at'    => null,
	);

	/**
	 * Constructor
	 *
	 * @param int $webhook Webhook ID.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $webhook = 0 ) {
		if ( is_numeric( $webhook ) && $webhook > 0 ) {
			$this->set_id( $webhook );
		} elseif ( $webhook instanceof self ) {
			$this->set_id( $webhook->get_id() );
		} elseif ( ! empty( $webhook->id ) ) {
			$this->set_id( $webhook->id );
		}

		$this->data_store = \OhMyLMS\DataStores\DataStores::load( $this->data_store_name );

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Get webhook name
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string
	 */
	public function get_name( $context = 'view' ) {
		return $this->get_prop( 'name', $context );
	}

	/**
	 * Get webhook trigger event
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string
	 */
	public function get_trigger_event( $context = 'view' ) {
		return $this->get_prop( 'trigger_event', $context );
	}

	/**
	 * Get webhook URL
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string
	 */
	public function get_webhook_url( $context = 'view' ) {
		return $this->get_prop( 'webhook_url', $context );
	}

	/**
	 * Get HTTP method
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string
	 */
	public function get_http_method( $context = 'view' ) {
		return $this->get_prop( 'http_method', $context );
	}

	/**
	 * Get data type
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string
	 */
	public function get_data_type( $context = 'view' ) {
		return $this->get_prop( 'data_type', $context );
	}

	/**
	 * Get data mapping.
	 *
	 * The view context returns a list of `{ key, value }` rows (always an array); the
	 * edit context returns the raw JSON string kept in the database.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string|array
	 */
	public function get_data_mapping( $context = 'view' ) {
		$data_mapping = $this->get_prop( 'data_mapping', $context );
		if ( 'view' === $context ) {
			if ( is_array( $data_mapping ) ) {
				return $data_mapping;
			}
			$decoded = is_string( $data_mapping ) && '' !== $data_mapping ? json_decode( $data_mapping, true ) : array();
			return is_array( $decoded ) ? array_values( $decoded ) : array();
		}
		return $data_mapping;
	}

	/**
	 * Get the signing secret.
	 *
	 * Never expose this in list or read responses; use get_secret_hint() instead.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string
	 */
	public function get_secret( $context = 'view' ) {
		return (string) $this->get_prop( 'secret', $context );
	}

	/**
	 * Get a masked hint of the signing secret (prefix plus the last four characters).
	 *
	 * @return string Empty string when no secret is set.
	 */
	public function get_secret_hint() {
		$secret = $this->get_secret( 'edit' );
		if ( '' === $secret ) {
			return '';
		}
		return 'whsec_****' . substr( $secret, -4 );
	}

	/**
	 * Set the signing secret.
	 *
	 * @param string $secret Signing secret.
	 */
	public function set_secret( $secret ) {
		$this->set_prop( 'secret', (string) $secret );
	}

	/**
	 * Get webhook status
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string
	 */
	public function get_status( $context = 'view' ) {
		return $this->get_prop( 'status', $context );
	}

	/**
	 * Get created date
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string
	 */
	public function get_created_at( $context = 'view' ) {
		return $this->get_prop( 'created_at', $context );
	}

	/**
	 * Get updated date
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string
	 */
	public function get_updated_at( $context = 'view' ) {
		return $this->get_prop( 'updated_at', $context );
	}

	/**
	 * Set webhook name
	 *
	 * @param string $name Webhook name.
	 */
	public function set_name( $name ) {
		$this->set_prop( 'name', $name );
	}

	/**
	 * Set webhook trigger event
	 *
	 * @param string $trigger_event Webhook trigger event.
	 */
	public function set_trigger_event( $trigger_event ) {
		$this->set_prop( 'trigger_event', $trigger_event );
	}

	/**
	 * Set webhook URL
	 *
	 * @param string $webhook_url Webhook URL.
	 */
	public function set_webhook_url( $webhook_url ) {
		$this->set_prop( 'webhook_url', $webhook_url );
	}

	/**
	 * Set HTTP method
	 *
	 * @param string $http_method HTTP method.
	 */
	public function set_http_method( $http_method ) {
		$this->set_prop( 'http_method', $http_method );
	}

	/**
	 * Set data type
	 *
	 * @param string $data_type Data type.
	 */
	public function set_data_type( $data_type ) {
		$this->set_prop( 'data_type', $data_type );
	}

	/**
	 * Set data mapping
	 *
	 * @param string|array $data_mapping Data mapping.
	 */
	public function set_data_mapping( $data_mapping ) {
		if ( is_array( $data_mapping ) ) {
			$data_mapping = wp_json_encode( $data_mapping );
		}
		$this->set_prop( 'data_mapping', $data_mapping );
	}

	/**
	 * Set webhook status
	 *
	 * @param string $status Webhook status.
	 */
	public function set_status( $status ) {
		$this->set_prop( 'status', $status );
	}

	/**
	 * Set created date
	 *
	 * @param string $created_at Created date.
	 */
	public function set_created_at( $created_at ) {
		$this->set_prop( 'created_at', $created_at );
	}

	/**
	 * Set updated date
	 *
	 * @param string $updated_at Updated date.
	 */
	public function set_updated_at( $updated_at ) {
		$this->set_prop( 'updated_at', $updated_at );
	}

	/**
	 * Delete webhook
	 *
	 * @param array $args Optional arguments for deletion.
	 * @return void
	 * @since 1.0.0
	 */
	public function delete( $args = array() ) {
		if ( $this->data_store ) {
			$this->data_store->delete( $this, $args );
		}
	}

	/**
	 * Get available trigger events
	 *
	 * @return array
	 */
	public static function get_available_triggers() {
		return apply_filters(
			'ohmylms_webhook_triggers',
			array(
				'course_purchase'        => __( 'Course Purchase', 'ohmylms' ),
				'course_enrollment'      => __( 'Course Enrollment', 'ohmylms' ),
				'course_completion'      => __( 'Course Completion', 'ohmylms' ),
				'lesson_completion'      => __( 'Lesson Completion', 'ohmylms' ),
				'quiz_submission'        => __( 'Quiz Submission', 'ohmylms' ),
				'quiz_achievement'       => __( 'Quiz Achievement', 'ohmylms' ),
				'assignment_submission'  => __( 'Assignment Submission', 'ohmylms' ),
				'assignment_achievement' => __( 'Assignment Achievement', 'ohmylms' ),
			)
		);
	}

	/**
	 * Get available HTTP methods
	 *
	 * @return array
	 */
	public static function get_available_methods() {
		return array(
			'GET'    => 'GET',
			'POST'   => 'POST',
			'PUT'    => 'PUT',
			'PATCH'  => 'PATCH',
			'DELETE' => 'DELETE',
		);
	}

	/**
	 * Get available data types
	 *
	 * @return array
	 */
	public static function get_available_data_types() {
		return array(
			'json' => 'JSON',
			'xml'  => 'XML',
			'form' => 'Form Data',
		);
	}

	/**
	 * Get the supported statuses.
	 *
	 * @return array
	 */
	public static function get_available_statuses() {
		return array(
			'active'   => __( 'Active', 'ohmylms' ),
			'inactive' => __( 'Inactive', 'ohmylms' ),
		);
	}

	/**
	 * Generate a new random signing secret.
	 *
	 * @return string
	 */
	public static function generate_secret() {
		return 'whsec_' . wp_generate_password( 32, false, false );
	}

	/**
	 * Execute webhook.
	 *
	 * Kept for backwards compatibility; delegates to the shared delivery service so signing,
	 * SSRF checks, timeouts and logging behave the same everywhere.
	 *
	 * @param array $data Data to send with the webhook.
	 * @return bool|\WP_Error False when inactive, true for a 2xx response, WP_Error otherwise.
	 */
	public function execute( $data = array() ) {
		if ( 'active' !== $this->get_status() ) {
			return false;
		}

		$payload = WebhookDelivery::map_payload( (array) $data, $this->get_data_mapping() );
		$result  = WebhookDelivery::send( $this, $payload, array( 'event' => $this->get_trigger_event() ) );

		if ( ! empty( $result['error'] ) ) {
			return new \WP_Error( $result['error']['code'], $result['error']['message'] );
		}
		return (bool) $result['success'];
	}
}
