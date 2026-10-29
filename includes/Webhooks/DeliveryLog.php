<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class DeliveryLog.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Webhooks;

defined( 'ABSPATH' ) || exit;

/**
 * Persists and queries webhook delivery attempts.
 *
 * Each outbound request (real or test) is stored with a truncated request and response body,
 * the HTTP status and the elapsed time. Only the newest MAX_PER_WEBHOOK rows are kept per webhook.
 *
 * @package OhMyLMS\Webhooks
 * @since 1.1.0
 */
class DeliveryLog {

	/**
	 * Maximum delivery rows retained for each webhook.
	 */
	const MAX_PER_WEBHOOK = 100;

	/**
	 * Get the deliveries table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_webhook_deliveries';
	}

	/**
	 * Insert a delivery row.
	 *
	 * @param array $row Delivery data.
	 * @return int Inserted row ID, or 0 on failure.
	 */
	public static function insert( array $row ) {
		global $wpdb;

		$data = array(
			'webhook_id'    => (int) $row['webhook_id'],
			'delivery_uuid' => (string) $row['delivery_uuid'],
			'event'         => (string) $row['event'],
			'url'           => (string) $row['url'],
			'http_method'   => (string) $row['http_method'],
			'status'        => (string) $row['status'],
			'response_code' => (int) $row['response_code'],
			'duration_ms'   => (int) $row['duration_ms'],
			'error_code'    => (string) $row['error_code'],
			'error_message' => (string) $row['error_message'],
			'request_body'  => (string) $row['request_body'],
			'response_body' => (string) $row['response_body'],
			'is_test'       => empty( $row['is_test'] ) ? 0 : 1,
			'created_at'    => current_time( 'mysql' ),
		);

		$inserted = $wpdb->insert( self::table(), $data, array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s' ) );
		if ( ! $inserted ) {
			return 0;
		}

		$id = (int) $wpdb->insert_id;
		self::prune( $data['webhook_id'] );

		return $id;
	}

	/**
	 * Delete rows beyond the retention limit for a webhook.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return void
	 */
	public static function prune( $webhook_id ) {
		global $wpdb;

		$table     = self::table();
		$threshold = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE webhook_id = %d ORDER BY id DESC LIMIT 1 OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table name.
				$webhook_id,
				self::MAX_PER_WEBHOOK
			)
		);

		if ( $threshold ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE webhook_id = %d AND id <= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table name.
					$webhook_id,
					$threshold
				)
			);
		}
	}

	/**
	 * Delete every delivery for a webhook.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return void
	 */
	public static function delete_for_webhook( $webhook_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'webhook_id' => (int) $webhook_id ), array( '%d' ) );
	}

	/**
	 * Query deliveries for one webhook.
	 *
	 * @param int   $webhook_id Webhook ID.
	 * @param array $args       Optional. status (all|success|failed), is_test (bool|null), limit, offset.
	 * @return array[] Delivery rows prepared with to_array().
	 */
	public static function query( $webhook_id, array $args = array() ) {
		global $wpdb;

		$args  = wp_parse_args(
			$args,
			array(
				'status' => 'all',
				'limit'  => 10,
				'offset' => 0,
			)
		);
		$table = self::table();
		$where = 'webhook_id = %d';
		$vals  = array( (int) $webhook_id );

		if ( in_array( $args['status'], array( 'success', 'failed' ), true ) ) {
			$where .= ' AND status = %s';
			$vals[] = $args['status'];
		}

		$vals[] = max( 1, (int) $args['limit'] );
		$vals[] = max( 0, (int) $args['offset'] );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Trusted table name and fixed clauses.
				$vals
			)
		);

		return array_map( array( __CLASS__, 'to_array' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Count deliveries for one webhook.
	 *
	 * @param int    $webhook_id Webhook ID.
	 * @param string $status     all, success or failed.
	 * @return int
	 */
	public static function count( $webhook_id, $status = 'all' ) {
		global $wpdb;

		$table = self::table();
		$where = 'webhook_id = %d';
		$vals  = array( (int) $webhook_id );

		if ( in_array( $status, array( 'success', 'failed' ), true ) ) {
			$where .= ' AND status = %s';
			$vals[] = $status;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Trusted table name and fixed clauses.
				$vals
			)
		);
	}

	/**
	 * Get the most recent delivery of each webhook in one query.
	 *
	 * @param int[] $webhook_ids Webhook IDs.
	 * @return array<int, array> Map of webhook ID to prepared delivery.
	 */
	public static function latest_for( array $webhook_ids ) {
		global $wpdb;

		$webhook_ids = array_values( array_filter( array_map( 'absint', $webhook_ids ) ) );
		if ( empty( $webhook_ids ) ) {
			return array();
		}

		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $webhook_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.* FROM {$table} d INNER JOIN ( SELECT MAX(id) AS max_id FROM {$table} WHERE webhook_id IN ({$placeholders}) GROUP BY webhook_id ) latest ON d.id = latest.max_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Trusted table name; placeholders generated from a count.
				$webhook_ids
			)
		);

		$map = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$map[ (int) $row->webhook_id ] = self::to_array( $row );
		}

		return $map;
	}

	/**
	 * Convert a database row to the REST shape.
	 *
	 * @param object $row Database row.
	 * @return array
	 */
	public static function to_array( $row ) {
		return array(
			'id'            => (int) $row->id,
			'webhook_id'    => (int) $row->webhook_id,
			'delivery_id'   => (string) $row->delivery_uuid,
			'event'         => (string) $row->event,
			'url'           => (string) $row->url,
			'http_method'   => (string) $row->http_method,
			'status'        => (string) $row->status,
			'response_code' => (int) $row->response_code,
			'duration_ms'   => (int) $row->duration_ms,
			'error_code'    => (string) $row->error_code,
			'error_message' => (string) $row->error_message,
			'request_body'  => (string) $row->request_body,
			'response_body' => (string) $row->response_body,
			'is_test'       => (bool) $row->is_test,
			'created_at'    => mysql_to_rfc3339( $row->created_at ),
		);
	}
}
