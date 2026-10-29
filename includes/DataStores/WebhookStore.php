<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class WebhookStore.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\DataStores;

use OhMyLMS\Abstracts\DataStore;
use OhMyLMS\Data\Webhook;
use OhMyLMS\Webhooks\DeliveryLog;

defined( 'ABSPATH' ) || exit;

/**
 * Class WebhookStore
 *
 * @package OhMyLMS\DataStores
 * @since 1.0.0
 */
class WebhookStore extends DataStore {

	/**
	 * Columns a collection may be sorted by (request value => column).
	 */
	const ORDERBY_COLUMNS = array(
		'id'            => 'id',
		'name'          => 'name',
		'status'        => 'status',
		'trigger_event' => 'trigger_event',
		'created_at'    => 'created_at',
		'updated_at'    => 'updated_at',
	);

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_webhooks';
	}

	/**
	 * Whether a webhook row exists.
	 *
	 * @param int $id Webhook ID.
	 * @return bool
	 */
	public static function exists( $id ) {
		global $wpdb;

		$table = self::table();
		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", absint( $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table name.
		);
	}

	/**
	 * Create webhook
	 *
	 * @param Webhook $webhook Webhook.
	 * @return mixed|void
	 * @since 1.0.0
	 * @throws \Exception When the row cannot be inserted.
	 */
	public function create( &$webhook ) {
		global $wpdb;

		$now  = current_time( 'mysql' );
		$data = array(
			'name'          => $webhook->get_name( 'edit' ),
			'trigger_event' => $webhook->get_trigger_event( 'edit' ),
			'webhook_url'   => $webhook->get_webhook_url( 'edit' ),
			'http_method'   => $webhook->get_http_method( 'edit' ),
			'data_type'     => $webhook->get_data_type( 'edit' ),
			'data_mapping'  => $webhook->get_data_mapping( 'edit' ),
			'secret'        => $webhook->get_secret( 'edit' ),
			'status'        => $webhook->get_status( 'edit' ),
			'created_at'    => $now,
			'updated_at'    => $now,
		);

		$result = $wpdb->insert( self::table(), $data, array_fill( 0, count( $data ), '%s' ) );

		if ( ! $result ) {
			throw new \Exception( esc_html__( 'The webhook could not be created.', 'ohmylms' ) );
		}

		$webhook->set_id( $wpdb->insert_id );
		$webhook->set_created_at( $now );
		$webhook->set_updated_at( $now );

		/**
		 * Fires after a webhook is created.
		 *
		 * @param int $webhook_id Webhook ID.
		 * @param Webhook $webhook Webhook object.
		 */
		do_action( 'ohmylms_webhook_created', $webhook->get_id(), $webhook );
	}

	/**
	 * Read webhook
	 *
	 * @param Webhook $webhook Webhook.
	 * @return mixed|void
	 * @since 1.0.0
	 * @throws \Exception When the webhook does not exist.
	 */
	public function read( &$webhook ) {
		global $wpdb;
		if ( ! $webhook->get_id() ) {
			return;
		}

		$table        = self::table();
		$webhook_data = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $webhook->get_id() ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table name.
		);

		if ( ! $webhook_data ) {
			throw new \Exception( esc_html__( 'Invalid webhook.', 'ohmylms' ) );
		}

		$webhook->set_props(
			array(
				'name'          => $webhook_data->name,
				'trigger_event' => $webhook_data->trigger_event,
				'webhook_url'   => $webhook_data->webhook_url,
				'http_method'   => $webhook_data->http_method,
				'data_type'     => $webhook_data->data_type,
				'data_mapping'  => $webhook_data->data_mapping,
				'secret'        => isset( $webhook_data->secret ) ? $webhook_data->secret : '',
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
	 * @throws \Exception When the row cannot be updated.
	 */
	public function update( &$webhook ) {
		global $wpdb;

		$now  = current_time( 'mysql' );
		$data = array(
			'name'          => $webhook->get_name( 'edit' ),
			'trigger_event' => $webhook->get_trigger_event( 'edit' ),
			'webhook_url'   => $webhook->get_webhook_url( 'edit' ),
			'http_method'   => $webhook->get_http_method( 'edit' ),
			'data_type'     => $webhook->get_data_type( 'edit' ),
			'data_mapping'  => $webhook->get_data_mapping( 'edit' ),
			'secret'        => $webhook->get_secret( 'edit' ),
			'status'        => $webhook->get_status( 'edit' ),
			'updated_at'    => $now,
		);

		$result = $wpdb->update(
			self::table(),
			$data,
			array( 'id' => $webhook->get_id() ),
			array_fill( 0, count( $data ), '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			throw new \Exception( esc_html__( 'The webhook could not be updated.', 'ohmylms' ) );
		}

		$webhook->set_updated_at( $now );

		/**
		 * Fires after a webhook is updated.
		 *
		 * @param int $webhook_id Webhook ID.
		 * @param Webhook $webhook Webhook object.
		 */
		do_action( 'ohmylms_webhook_updated', $webhook->get_id(), $webhook );
	}

	/**
	 * Delete webhook
	 *
	 * @param Webhook $webhook Webhook.
	 * @param array   $args Operation arguments.
	 * @return mixed|void
	 * @since 1.0.0
	 * @throws \Exception When the row cannot be deleted.
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

		$result = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );

		if ( ! $result ) {
			throw new \Exception( esc_html__( 'The webhook could not be deleted.', 'ohmylms' ) );
		}

		DeliveryLog::delete_for_webhook( $id );
		$webhook->set_id( 0 );

		/**
		 * Fires after a webhook is deleted.
		 *
		 * @param int $webhook_id Webhook ID.
		 */
		do_action( 'ohmylms_webhook_deleted', $id );
	}

	/**
	 * Build the WHERE clause shared by the list and count queries.
	 *
	 * @param array $args Query arguments (status, trigger_event, search).
	 * @return array{0:string,1:array} SQL fragment and prepare() values.
	 */
	private static function build_where( array $args ) {
		global $wpdb;

		$clauses = array( '1=1' );
		$values  = array();

		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$clauses[] = 'status = %s';
			$values[]  = $args['status'];
		}

		if ( ! empty( $args['trigger_event'] ) ) {
			$clauses[] = 'trigger_event = %s';
			$values[]  = $args['trigger_event'];
		}

		if ( isset( $args['search'] ) && '' !== (string) $args['search'] ) {
			$clauses[] = '(name LIKE %s OR webhook_url LIKE %s OR trigger_event LIKE %s)';
			$like      = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			array_push( $values, $like, $like, $like );
		}

		return array( implode( ' AND ', $clauses ), $values );
	}

	/**
	 * Get webhooks
	 *
	 * @param array $args Query arguments: status, trigger_event, search, orderby (see ORDERBY_COLUMNS), order (ASC|DESC), limit (-1 for all), offset.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_webhooks( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'status'        => 'active',
				'trigger_event' => '',
				'search'        => '',
				'orderby'       => 'created_at',
				'order'         => 'DESC',
				'limit'         => -1,
				'offset'        => 0,
			)
		);

		list( $where, $values ) = self::build_where( $args );

		$orderby = isset( self::ORDERBY_COLUMNS[ $args['orderby'] ] ) ? self::ORDERBY_COLUMNS[ $args['orderby'] ] : 'created_at';
		$order   = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$table   = self::table();
		$query   = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order}, id {$order}";

		if ( (int) $args['limit'] > 0 ) {
			$query   .= ' LIMIT %d OFFSET %d';
			$values[] = (int) $args['limit'];
			$values[] = max( 0, (int) $args['offset'] );
		}

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Trusted table name and allow-listed ORDER BY; values are bound.
		}

		return $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
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

		$args = wp_parse_args(
			$args,
			array(
				'status'        => 'active',
				'trigger_event' => '',
				'search'        => '',
			)
		);

		list( $where, $values ) = self::build_where( $args );

		$table = self::table();
		$query = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Trusted table name; values are bound.
		}

		return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
	}
}
