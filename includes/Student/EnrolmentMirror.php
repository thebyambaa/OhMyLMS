<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Projection of `ohmylms_user_enrollment` rows into `ohmylms_enrolments` (dual write and migration).
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps one `ohmylms_enrolments` row per legacy `ohmylms_user_enrollment` row, linked by `legacy_id`.
 * The legacy row stays authoritative until every reader is switched; this class only projects it.
 * Status and dates are always copied from the legacy row; the source is set by the writer, or inferred
 * once ({@see self::detect()}) for rows that predate the table.
 */
final class EnrolmentMirror {

	/**
	 * New statuses that legacy readers call `enrolled`.
	 */
	const ENROLLED = array( 'active', 'completed' );

	/**
	 * New statuses that legacy readers list as `enrolled` or `banned`.
	 */
	const LISTED = array( 'active', 'completed', 'suspended' );

	/**
	 * Seconds between a roster sync and the enrolment it created for the row to count as `roster`.
	 */
	const ROSTER_WINDOW = 300;

	/**
	 * Legacy enrolment table.
	 *
	 * @return string
	 */
	public static function legacy_table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_user_enrollment';
	}

	/**
	 * Status of a legacy row: `enrolled` becomes active or completed (by progress), `banned` suspended;
	 * `dropped` and unknown values become cancelled.
	 *
	 * @param string $status   Legacy status.
	 * @param string $progress Legacy progress.
	 * @return string
	 */
	public static function status( $status, $progress ) {
		switch ( (string) $status ) {
			case 'enrolled':
				return 'completed' === $progress ? 'completed' : 'active';
			case 'completed':
				return 'completed';
			case 'banned':
				return 'suspended';
			case 'pending':
				return 'pending';
			case 'expired':
				return 'expired';
			default:
				return 'cancelled';
		}
	}

	/**
	 * SQL expression giving the legacy status of a new row (`enrolled`, `banned`, or the status itself).
	 *
	 * @param string $alias Table alias.
	 * @return string
	 */
	public static function legacy_status_sql( $alias ) {
		return "CASE {$alias}.status WHEN 'active' THEN 'enrolled' WHEN 'completed' THEN 'enrolled' WHEN 'suspended' THEN 'banned' ELSE {$alias}.status END";
	}

	/**
	 * SQL expression giving the legacy progress of a new row.
	 *
	 * @param string $alias Table alias.
	 * @return string
	 */
	public static function legacy_progress_sql( $alias ) {
		return "IF({$alias}.completed_at IS NULL,'running','completed')";
	}

	/**
	 * Complete an origin with defaults; unknown sources and reference kinds are rejected.
	 *
	 * @param array $origin Keys `source`, `source_type`, `source_ref`, `class_id`, `expires_at`, `created_by`.
	 * @return array
	 */
	public static function origin( array $origin ) {
		$origin = array_merge(
			array(
				'source'      => 'manual',
				'source_type' => '',
				'source_ref'  => 0,
				'class_id'    => null,
				'expires_at'  => null,
				'created_by'  => get_current_user_id(),
			),
			$origin
		);
		if ( ! in_array( $origin['source'], EnrolmentSchema::SOURCES, true ) ) {
			$origin['source'] = 'manual';
		}
		if ( ! in_array( $origin['source_type'], EnrolmentSchema::REF_TYPES, true ) ) {
			$origin['source_type'] = '';
		}
		$origin['source_ref'] = max( 0, (int) $origin['source_ref'] );
		$origin['class_id']   = $origin['class_id'] ? (int) $origin['class_id'] : null;
		$origin['expires_at'] = self::date( $origin['expires_at'] );
		$origin['created_by'] = max( 0, (int) $origin['created_by'] );
		return $origin;
	}

	/**
	 * Infer the source of a legacy row that was written without one. Rules, in order: a membership
	 * (subscription when its membership row has one, purchase when it came from a paid order, self for a
	 * free grant); an order (self when its total is zero, else purchase); a standalone exam course (self);
	 * a class sync within {@see self::ROSTER_WINDOW} seconds (roster); a course brought in by an LMS
	 * migration (import); otherwise manual.
	 *
	 * @param array $legacy Legacy row.
	 * @return array Origin.
	 */
	public static function detect( array $legacy ) {
		global $wpdb;

		$user_id    = (int) $legacy['user_id'];
		$course_id  = (int) $legacy['course_id'];
		$order_id   = (int) ( $legacy['order_id'] ?? 0 );
		$membership = (int) ( $legacy['membership_id'] ?? 0 );
		$origin     = array( 'created_by' => 0 );

		if ( $membership > 0 ) {
			$grant = null;
			$table = $wpdb->prefix . 'ohmylms_user_membership';
			if ( self::table_exists( $table ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh membership row of this grant.
				$grant = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id=%d AND membership_id=%d ORDER BY (order_id=%d) DESC, id DESC LIMIT 1', $table, $user_id, $membership, $order_id ), ARRAY_A );
			}
			$paid_order = $order_id ? $order_id : ( $grant ? (int) $grant['order_id'] : 0 );
			return array( 'created_by' => 0 ) + self::order_origin( $paid_order, $membership, $grant ? (int) ( $grant['subscription_id'] ?? 0 ) : 0, $grant ? $grant['end_date'] : null );
		}

		if ( $order_id > 0 ) {
			return array( 'created_by' => 0 ) + self::order_origin( $order_id );
		}

		if ( get_post_meta( $course_id, '_ohmylms_exam_wrapper', true ) ) {
			return $origin + array(
				'source'      => 'self',
				'source_type' => 'exam',
			);
		}

		$class = self::roster_class( $user_id, $course_id, (string) ( $legacy['start_date'] ?? '' ) );
		if ( $class ) {
			return $origin + array(
				'source'      => 'roster',
				'source_type' => 'class',
				'source_ref'  => $class,
				'class_id'    => $class,
			);
		}

		if ( '' !== (string) get_post_meta( $course_id, '_ohmylms_migration_source', true ) ) {
			return $origin + array( 'source' => 'import' );
		}

		return $origin + array( 'source' => 'manual' );
	}

	/**
	 * Origin of an enrolment granted by an order, a membership or a subscription. A subscription (from the
	 * membership row or the order's `_subscription_id`) wins over the membership, the membership over the
	 * order. Free orders and free membership grants are self-enrolments.
	 *
	 * @param int         $order_id      Order ID, 0 for none.
	 * @param int         $membership_id Membership plan ID, 0 for none.
	 * @param int         $subscription  Subscription ID, 0 to read it from the order.
	 * @param string|null $expires_at    End of the membership grant.
	 * @return array Origin.
	 */
	public static function order_origin( $order_id, $membership_id = 0, $subscription = 0, $expires_at = null ) {
		$order_id     = (int) $order_id;
		$subscription = (int) $subscription;
		if ( ! $subscription && $order_id && $membership_id ) {
			$subscription = (int) get_post_meta( $order_id, '_subscription_id', true );
		}
		$paid = $order_id && ! self::free_order( $order_id );
		if ( $membership_id && $subscription ) {
			return array(
				'source'      => 'purchase',
				'source_type' => 'subscription',
				'source_ref'  => $subscription,
				'expires_at'  => $expires_at,
			);
		}
		if ( $membership_id ) {
			return array(
				'source'      => $paid ? 'purchase' : 'self',
				'source_type' => 'membership',
				'source_ref'  => (int) $membership_id,
				'expires_at'  => $expires_at,
			);
		}
		return array(
			'source'      => $paid || ! $order_id ? 'purchase' : 'self',
			'source_type' => 'order',
			'source_ref'  => $order_id,
		);
	}

	/**
	 * Whether an order is known to have cost nothing (free checkout = self-enrolment). An order paid
	 * with bonus points has a zero total but is a purchase.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	private static function free_order( $order_id ) {
		if ( 'point' === get_post_meta( (int) $order_id, '_purchased_by', true ) ) {
			return false;
		}
		$total = get_post_meta( (int) $order_id, '_order_total', true );
		return is_numeric( $total ) && 0.0 === (float) $total;
	}

	/**
	 * The class whose course sync created this enrolment, or 0.
	 *
	 * @param int    $user_id    Student ID.
	 * @param int    $course_id  Course ID.
	 * @param string $start_date Legacy start date (site time).
	 * @return int
	 */
	private static function roster_class( $user_id, $course_id, $start_date ) {
		global $wpdb;
		$members = $wpdb->prefix . 'ohmylms_course_class_students';
		if ( ! self::date( $start_date ) || ! self::table_exists( $members ) ) {
			return 0;
		}
		$start = strtotime( get_gmt_from_date( $start_date ) . ' UTC' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh roster rows of this student and course.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT class_id, created_at FROM %i WHERE course_id=%d AND user_id=%d ORDER BY id ASC', $members, $course_id, $user_id ), ARRAY_A );
		foreach ( $rows as $row ) {
			if ( abs( strtotime( $row['created_at'] . ' UTC' ) - $start ) <= self::ROSTER_WINDOW ) {
				return (int) $row['class_id'];
			}
		}
		return 0;
	}

	/**
	 * Project legacy rows by ID. Rows that no longer exist lose their projection.
	 *
	 * @param int[]             $legacy_ids Legacy row IDs.
	 * @param array|string|null $origin     Origin to record; `detect` infers it from the legacy row; null keeps
	 *                                      the stored one (or infers it on insert).
	 * @param bool              $insert     Whether to create projections that do not exist yet.
	 * @param bool              $migrated   Whether created rows belong to the migration.
	 * @return array<string,int> Counts by outcome.
	 */
	public static function sync( array $legacy_ids, $origin = null, $insert = true, $migrated = false ) {
		global $wpdb;

		$counts = array();
		$ids    = array_values( array_unique( array_filter( array_map( 'intval', $legacy_ids ) ) ) );
		if ( ! $ids || ! EnrolmentSchema::exists() ) {
			return $counts;
		}

		$saved        = array( $wpdb->insert_id, $wpdb->rows_affected, $wpdb->last_error );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated from the id count.
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE id IN ($placeholders)", array_merge( array( self::legacy_table() ), $ids ) ), ARRAY_A );
		$found = array();
		foreach ( $rows as $row ) {
			$found[]            = (int) $row['id'];
			$outcome            = self::write( $row, $origin, $insert, $migrated );
			$counts[ $outcome ] = ( $counts[ $outcome ] ?? 0 ) + 1;
		}
		$gone = array_diff( $ids, $found );
		if ( $gone ) {
			$counts['deleted'] = self::forget( $gone );
		}
		$wpdb->insert_id     = $saved[0];
		$wpdb->rows_affected = $saved[1];
		$wpdb->last_error    = $saved[2];

		if ( ! empty( $counts['failed'] ) ) {
			EnrolmentMigration::mark_stale();
		}
		return $counts;
	}

	/**
	 * Create or update the projection of one legacy row.
	 *
	 * @param array             $legacy   Legacy row.
	 * @param array|string|null $origin   Origin to record, `detect`, or null.
	 * @param bool              $insert   Whether a missing projection may be created.
	 * @param bool              $migrated Whether a created row belongs to the migration.
	 * @return string `created`, `updated`, `unchanged`, `skipped` or `failed`; `+duplicate` is appended when
	 *                another active row with the same user, course and source holds the unique slot.
	 */
	public static function write( array $legacy, $origin, $insert, $migrated ) {
		global $wpdb;

		$table = EnrolmentSchema::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh projection row.
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE legacy_id=%d', $table, (int) $legacy['id'] ), ARRAY_A );
		if ( ! $existing && ! $insert ) {
			return 'skipped';
		}

		$status   = self::status( $legacy['status'], $legacy['progress'] );
		$enrolled = self::date( $legacy['start_date'] ?? '' );
		$data     = array(
			'user_id'      => (int) $legacy['user_id'],
			'course_id'    => (int) $legacy['course_id'],
			'status'       => $status,
			'enrolled_at'  => $enrolled,
			'completed_at' => 'completed' === $legacy['progress'] || 'completed' === $legacy['status'] ? ( self::date( $legacy['end_date'] ?? '' ) ?? ( $enrolled ?? current_time( 'mysql' ) ) ) : null,
		);
		if ( 'detect' === $origin || ( null === $origin && ! $existing ) ) {
			$origin = self::detect( $legacy );
		}
		if ( null !== $origin ) {
			$origin = self::origin( $origin );
			$data  += array_intersect_key( $origin, array_flip( array( 'source', 'source_type', 'source_ref', 'class_id', 'expires_at' ) ) );
		}

		$row                = array_merge( $existing ? $existing : array(), $data );
		$data['active_key'] = 'active' === $status ? 1 : null;
		$duplicate          = false;
		if ( 1 === $data['active_key'] ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh unique-slot check.
			$holder = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id=%d AND course_id=%d AND source=%s AND source_type=%s AND source_ref=%d AND active_key=1 AND id<>%d LIMIT 1', $table, $row['user_id'], $row['course_id'], $row['source'], $row['source_type'], $row['source_ref'], $existing ? (int) $existing['id'] : 0 ) );
			if ( $holder ) {
				$data['active_key'] = null;
				$duplicate          = true;
			}
		}

		if ( $existing ) {
			$changed = array();
			foreach ( $data as $column => $value ) {
				if ( (string) $existing[ $column ] !== (string) $value || ( null === $value ) !== ( null === $existing[ $column ] ) ) {
					$changed[ $column ] = $value;
				}
			}
			if ( ! $changed ) {
				return 'unchanged' . ( $duplicate ? '+duplicate' : '' );
			}
			$changed['updated_at'] = current_time( 'mysql' );
			$suppress              = $wpdb->suppress_errors( true );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Projection write.
			$result = $wpdb->update( $table, $changed, array( 'id' => (int) $existing['id'] ) );
			$wpdb->suppress_errors( $suppress );
			return false === $result ? 'failed' : 'updated' . ( $duplicate ? '+duplicate' : '' );
		}

		$data['legacy_id']  = (int) $legacy['id'];
		$data['migrated']   = $migrated ? 1 : 0;
		$data['created_by'] = $origin['created_by'];
		$data['updated_at'] = current_time( 'mysql' );
		$suppress           = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Projection write.
		$result = $wpdb->insert( $table, $data );
		if ( false === $result && 1 === $data['active_key'] ) {
			// Another writer took the unique active slot between the check and the insert.
			$data['active_key'] = null;
			$duplicate          = true;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Projection write.
			$result = $wpdb->insert( $table, $data );
		}
		$wpdb->suppress_errors( $suppress );
		return false === $result ? 'failed' : 'created' . ( $duplicate ? '+duplicate' : '' );
	}

	/**
	 * Remove the projections of legacy rows.
	 *
	 * @param int[] $legacy_ids Legacy row IDs.
	 * @return int Rows removed.
	 */
	public static function forget( array $legacy_ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $legacy_ids ) ) );
		if ( ! $ids || ! EnrolmentSchema::exists() ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated from the id count.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE legacy_id IN ($placeholders)", array_merge( array( EnrolmentSchema::table() ), $ids ) ) );
	}

	/**
	 * A stored datetime, or null for empty and zero dates.
	 *
	 * @param mixed $value Datetime string.
	 * @return string|null
	 */
	public static function date( $value ) {
		$value = (string) $value;
		return '' === $value || 0 === strpos( $value, '0000-00-00' ) ? null : $value;
	}

	/**
	 * Whether an optional table exists (cached for the request).
	 *
	 * @param string $table Table name.
	 * @return bool
	 */
	private static function table_exists( $table ) {
		global $wpdb;
		static $known = array();
		if ( ! isset( $known[ $table ] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Existence probe for an optional table.
			$known[ $table ] = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		}
		return $known[ $table ];
	}
}
