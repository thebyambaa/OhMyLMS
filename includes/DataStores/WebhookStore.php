<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class WebhookStore.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\DataStores;

use OhMyLMS\Abstracts\DataStore;
use OhMyLMS\Data\Webhook;

defined( 'ABSPATH' ) || exit;

/**
 * Class WebhookStore
 *
 * @package OhMyLMS\DataStores
 * @since 1.0.0
 */
class WebhookStore extends DataStore {

	/**
	 * Create webhook
	 *
	 * @param Webhook $webhook Webhook.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function create( &$webhook ) {
		global $wpdb;

		$data = array(
			'name'          => $webhook->get_name( 'edit' ),
			'trigger_event' => $webhook->get_trigger_event( 'edit' ),
			'webhook_url'   => $webhook->get_webhook_url( 'edit' ),
			'http_method'   => $webhook->get_http_method( 'edit' ),
			'data_type'     => $webhook->get_data_type( 'edit' ),
			'data_mapping'  => $webhook->get_data_mapping( 'edit' ),
			'status'        => $webhook->get_status( 'edit' ),
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$wpdb->prefix . 'ohmylms_webhooks',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result ) {
			$webhook->set_id( $wpdb->insert_id );

			/**
			 * Fires after a webhook is created.
			 *
			 * @param int $webhook_id Webhook ID.
			 * @param Webhook $webhook Webhook object.
			 */
			do_action( 'ohmylms_webhook_created', $webhook->get_id(), $webhook );
		}
	}

	/**
	 * Read webhook
	 *
	 * @param Webhook $webhook Webhook.
	 * @return mixed|void
	 * @since 1.0.0
	 * @throws \Exception When the operation cannot be completed.
	 */
	public function read( &$webhook ) {
		global $wpdb;
		if ( ! $webhook->get_id() ) {
			return;
		}

		$webhook_data = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $wpdb->prefix . 'ohmylms_webhooks', $webhook->get_id() )
		);

		if ( ! $webhook_data ) {
			throw new \Exception( __( 'Invalid webhook.', 'ohmylms' ) );
		}

		$webhook->set_props(
			array(
				'name'          => $webhook_data->name,
				'trigger_event' => $webhook_data->trigger_event,
				'webhook_url'   => $webhook_data->webhook_url,
				'http_method'   => $webhook_data->http_method,
				'data_type'     => $webhook_data->data_type,
				'data_mapping'  => $webhook_data->data_mapping,
				'status'        => $webhook_data->status,
				'created_at'    => $webhook_data->created_at,
				'updated_at'    => $webhook_data->updated_at,
			)
		);

		/**
		 * Fires after a webhook is read.
		 *
		 * @param int $webhook_id Webhook ID.
		 * @param Webhook $webhook Webhook object.
		 */
		do_action( 'ohmylms_webhook_loaded', $webhook->get_id(), $webhook );
	}

	/**
	 * Update webhook
	 *
	 * @param Webhook $webhook Webhook.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function update( &$webhook ) {
		global $wpdb;

		$data = array(
			'name'          => $webhook->get_name( 'edit' ),
			'trigger_event' => $webhook->get_trigger_event( 'edit' ),
			'webhook_url'   => $webhook->get_webhook_url( 'edit' ),
			'http_method'   => $webhook->get_http_method( 'edit' ),
			'data_type'     => $webhook->get_data_type( 'edit' ),
			'data_mapping'  => $webhook->get_data_mapping( 'edit' ),
			'status'        => $webhook->get_status( 'edit' ),
			'updated_at'    => current_time( 'mysql' ),
		);

		$result = $wpdb->update(
			$wpdb->prefix . 'ohmylms_webhooks',
			$data,
			array( 'id' => $webhook->get_id() ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			/**
			 * Fires after a webhook is updated.
			 *
			 * @param int $webhook_id Webhook ID.
			 * @param Webhook $webhook Webhook object.
			 */
			do_action( 'ohmylms_webhook_updated', $webhook->get_id(), $webhook );
		}
	}

	/**
	 * Delete webhook
	 *
	 * @param Webhook $webhook Webhook.
	 * @param array   $args Operation arguments.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function delete( &$webhook, $args = array() ) {
		global $wpdb;

		$id = $webhook->get_id();

		if ( ! $id ) {
			return;
		}

		/**
		 * Fires before a webhook is deleted.
		 *
		 * @param int $webhook_id Webhook ID.
		 * @param Webhook $webhook Webhook object.
		 */
		do_action( 'ohmylms_before_delete_webhook', $id, $webhook );

		$result = $wpdb->delete(
			$wpdb->prefix . 'ohmylms_webhooks',
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( $result ) {
			$webhook->set_id( 0 );

			/**
			 * Fires after a webhook is deleted.
			 *
			 * @param int $webhook_id Webhook ID.
			 */
			do_action( 'ohmylms_webhook_deleted', $id );
		}
	}

	/**
	 * Get webhooks
	 *
	 * @param array $args Query arguments.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_webhooks( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'        => 'active',
			'trigger_event' => '',
			'search'        => '',
			'orderby'       => 'created_at',
			'order'         => 'DESC',
			'limit'         => -1,
			'offset'        => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( '1=1' );
		$values        = array();

		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$where_clauses[] = 'status = %s';
			$values[]        = $args['status'];
		}

		if ( ! empty( $args['trigger_event'] ) ) {
			$where_clauses[] = 'trigger_event = %s';
			$values[]        = $args['trigger_event'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = '(name LIKE %s OR webhook_url LIKE %s OR trigger_event LIKE %s)';
			$search_term     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[]        = $search_term;
			$values[]        = $search_term;
			$values[]        = $search_term;
		}

		$where   = implode( ' AND ', $where_clauses );
		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

		$limit_clause = '';
		if ( $args['limit'] > 0 ) {
			$limit_clause = $wpdb->prepare( ' LIMIT %d', $args['limit'] );
			if ( $args['offset'] > 0 ) {
				$limit_clause = $wpdb->prepare( ' LIMIT %d, %d', $args['offset'], $args['limit'] );
			}
		}

		$query = "SELECT * FROM {$wpdb->prefix}ohmylms_webhooks WHERE {$where}";

		if ( $orderby ) {
			$query .= " ORDER BY {$orderby}";
		}

		$query .= $limit_clause;

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		return $wpdb->get_results( $query );
	}

	/**
	 * Get webhook count
	 *
	 * @param array $args Query arguments.
	 * @return int
	 * @since 1.0.0
	 */
	public static function get_webhook_count( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'        => 'active',
			'trigger_event' => '',
			'search'        => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( '1=1' );
		$values        = array();

		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$where_clauses[] = 'status = %s';
			$values[]        = $args['status'];
		}

		if ( ! empty( $args['trigger_event'] ) ) {
			$where_clauses[] = 'trigger_event = %s';
			$values[]        = $args['trigger_event'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = '(name LIKE %s OR webhook_url LIKE %s OR trigger_event LIKE %s)';
			$search_term     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[]        = $search_term;
			$values[]        = $search_term;
			$values[]        = $search_term;
		}

		$where = implode( ' AND ', $where_clauses );
		$query = "SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_webhooks WHERE {$where}";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		return (int) $wpdb->get_var( $query );
	}
}
