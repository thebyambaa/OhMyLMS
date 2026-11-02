<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Automation rule and run-log storage.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Automation;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Tables `ohmylms_automations` (one row per rule, conditions and actions as JSON) and
 * `ohmylms_automation_log` (one row per action run). The log keeps 90 days.
 *
 * @since 1.0.0
 */
final class RuleStore {

	const VERSION  = '1';
	const OPTION   = 'ohmylms_automations_schema';
	const LOG_DAYS = 90;

	/**
	 * Rule table.
	 *
	 * @return string
	 */
	public static function rules() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_automations';
	}

	/**
	 * Log table.
	 *
	 * @return string
	 */
	public static function log_table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_automation_log';
	}

	/**
	 * Install the tables when the stored schema version differs.
	 *
	 * @return void
	 */
	public static function ensure() {
		global $wpdb;
		if ( self::VERSION === get_option( self::OPTION ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$rules   = self::rules();
		$log     = self::log_table();
		dbDelta(
			"CREATE TABLE {$rules} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'paused',
  event varchar(40) NOT NULL DEFAULT '',
  conditions longtext NOT NULL,
  actions longtext NOT NULL,
  run_count bigint(20) unsigned NOT NULL DEFAULT 0,
  last_run_at datetime DEFAULT NULL,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY event_status (event,status)
) {$charset};
CREATE TABLE {$log} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  automation_id bigint(20) unsigned NOT NULL,
  event varchar(40) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  action varchar(40) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT '',
  message varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY automation_created (automation_id,created_at),
  KEY created_at (created_at)
) {$charset};"
		);
		update_option( self::OPTION, self::VERSION, false );
	}

	/**
	 * Response shape of a rule row.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function prepare( array $row ) {
		$conditions = json_decode( (string) $row['conditions'], true );
		$actions    = json_decode( (string) $row['actions'], true );
		return array(
			'id'          => (int) $row['id'],
			'name'        => (string) $row['name'],
			'status'      => (string) $row['status'],
			'event'       => (string) $row['event'],
			'conditions'  => is_array( $conditions ) && $conditions ? $conditions : new \stdClass(),
			'actions'     => is_array( $actions ) ? $actions : array(),
			'run_count'   => (int) $row['run_count'],
			'last_run_at' => $row['last_run_at'] ? str_replace( ' ', 'T', $row['last_run_at'] ) : null,
			'created_at'  => str_replace( ' ', 'T', (string) $row['created_at'] ),
			'updated_at'  => str_replace( ' ', 'T', (string) $row['updated_at'] ),
		);
	}

	/**
	 * One stored row.
	 *
	 * @param int $id Rule.
	 * @return array|null
	 */
	public static function row( $id ) {
		global $wpdb;
		self::ensure();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::rules(), (int) $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Active rules of an event.
	 *
	 * @param string $event Event.
	 * @return array[] Rows.
	 */
	public static function active_for( $event ) {
		global $wpdb;
		self::ensure();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE event = %s AND status = 'active' ORDER BY id ASC LIMIT 100", self::rules(), (string) $event ), ARRAY_A );
		return $rows ? $rows : array();
	}

	/**
	 * Paged list.
	 *
	 * @param array $args `page`, `per_page`, `search`, `status`, `event`.
	 * @return array{items: array[], total: int}
	 */
	public static function page( array $args ) {
		global $wpdb;
		self::ensure();
		$where  = array( '1=1' );
		$params = array( self::rules() );
		if ( ! empty( $args['status'] ) && 'any' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = (string) $args['status'];
		}
		if ( ! empty( $args['event'] ) ) {
			$where[]  = 'event = %s';
			$params[] = (string) $args['event'];
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
		}
		$sql_where = implode( ' AND ', $where );
		$per_page  = (int) $args['per_page'];
		$offset    = ( (int) $args['page'] - 1 ) * $per_page;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $sql_where holds only placeholders.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$sql_where}", $params ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$sql_where} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );
		// phpcs:enable
		return array(
			'items' => array_map( array( __CLASS__, 'prepare' ), $rows ? $rows : array() ),
			'total' => $total,
		);
	}

	/**
	 * Create a rule from a validated array.
	 *
	 * @param array $rule    Validated rule.
	 * @param int   $user_id Author.
	 * @return array|WP_Error
	 */
	public static function create( array $rule, $user_id ) {
		global $wpdb;
		self::ensure();
		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert(
			self::rules(),
			array(
				'name'       => $rule['name'],
				'status'     => $rule['status'],
				'event'      => $rule['event'],
				'conditions' => wp_json_encode( (object) $rule['conditions'] ),
				'actions'    => wp_json_encode( $rule['actions'] ),
				'created_by' => (int) $user_id,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		if ( ! $ok ) {
			return new WP_Error( 'ohmylms_automation_not_saved', __( 'The rule could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		return self::prepare( self::row( (int) $wpdb->insert_id ) );
	}

	/**
	 * Replace a rule's editable fields.
	 *
	 * @param int   $id   Rule.
	 * @param array $rule Validated rule.
	 * @return array|WP_Error
	 */
	public static function update( $id, array $rule ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->update(
			self::rules(),
			array(
				'name'       => $rule['name'],
				'status'     => $rule['status'],
				'event'      => $rule['event'],
				'conditions' => wp_json_encode( (object) $rule['conditions'] ),
				'actions'    => wp_json_encode( $rule['actions'] ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id )
		);
		if ( false === $ok ) {
			return new WP_Error( 'ohmylms_automation_not_saved', __( 'The rule could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		return self::prepare( self::row( $id ) );
	}

	/**
	 * Delete a rule and its log.
	 *
	 * @param int $id Rule.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$deleted = (bool) $wpdb->delete( self::rules(), array( 'id' => (int) $id ), array( '%d' ) );
		if ( $deleted ) {
			$wpdb->delete( self::log_table(), array( 'automation_id' => (int) $id ), array( '%d' ) );
		}
		// phpcs:enable
		return $deleted;
	}

	/**
	 * Count one run of a rule.
	 *
	 * @param int $id Rule.
	 * @return void
	 */
	public static function touch( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET run_count = run_count + 1, last_run_at = %s WHERE id = %d', self::rules(), current_time( 'mysql' ), (int) $id ) );
	}

	/**
	 * Write one log row (message is plain text, cut at 255 characters).
	 *
	 * @param array $entry `{automation_id, event, user_id, object_id, action, status, message}`.
	 * @return void
	 */
	public static function log( array $entry ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			self::log_table(),
			array(
				'automation_id' => (int) $entry['automation_id'],
				'event'         => (string) $entry['event'],
				'user_id'       => (int) ( $entry['user_id'] ?? 0 ),
				'object_id'     => (int) ( $entry['object_id'] ?? 0 ),
				'action'        => (string) ( $entry['action'] ?? '' ),
				'status'        => (string) $entry['status'],
				'message'       => mb_substr( sanitize_text_field( (string) ( $entry['message'] ?? '' ) ), 0, 255 ),
				'created_at'    => current_time( 'mysql' ),
			)
		);
		if ( 1 === wp_rand( 1, 50 ) ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s LIMIT 1000', self::log_table(), gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - self::LOG_DAYS * DAY_IN_SECONDS ) ) );
		}
		// phpcs:enable
	}

	/**
	 * Paged log, newest first, optionally for one rule.
	 *
	 * @param array $args `page`, `per_page`, `automation_id`, `status`.
	 * @return array{items: array[], total: int}
	 */
	public static function log_page( array $args ) {
		global $wpdb;
		self::ensure();
		$where  = array( '1=1' );
		$params = array( self::log_table(), self::rules() );
		if ( ! empty( $args['automation_id'] ) ) {
			$where[]  = 'l.automation_id = %d';
			$params[] = (int) $args['automation_id'];
		}
		if ( ! empty( $args['status'] ) && 'any' !== $args['status'] ) {
			$where[]  = 'l.status = %s';
			$params[] = (string) $args['status'];
		}
		$sql_where = implode( ' AND ', $where );
		$per_page  = (int) $args['per_page'];
		$offset    = ( (int) $args['page'] - 1 ) * $per_page;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $sql_where holds only placeholders.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i l LEFT JOIN %i r ON r.id = l.automation_id WHERE {$sql_where}", $params ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT l.*, r.name AS rule_name FROM %i l LEFT JOIN %i r ON r.id = l.automation_id WHERE {$sql_where} ORDER BY l.id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );
		// phpcs:enable
		$items = array();
		foreach ( $rows ? $rows : array() as $row ) {
			$user    = $row['user_id'] ? get_userdata( (int) $row['user_id'] ) : null;
			$items[] = array(
				'id'            => (int) $row['id'],
				'automation_id' => (int) $row['automation_id'],
				'rule_name'     => (string) $row['rule_name'],
				'event'         => (string) $row['event'],
				'user_id'       => (int) $row['user_id'],
				'user_name'     => $user ? (string) $user->display_name : '',
				'object_id'     => (int) $row['object_id'],
				'object_title'  => $row['object_id'] ? html_entity_decode( get_the_title( (int) $row['object_id'] ), ENT_QUOTES, 'UTF-8' ) : '',
				'action'        => (string) $row['action'],
				'status'        => (string) $row['status'],
				'message'       => (string) $row['message'],
				'created_at'    => str_replace( ' ', 'T', (string) $row['created_at'] ),
			);
		}
		return array(
			'items' => $items,
			'total' => $total,
		);
	}
}
