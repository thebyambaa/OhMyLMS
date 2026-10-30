<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Analytics queries.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Analytics;

use RuntimeException;

/**
 * Read-only SQL behind the dashboard and analytics routes.
 *
 * Definitions (see docs/api/analytics.md):
 * - An order counts when its status is `ohmylms-completed` or `ohmylms-refunded`, dated by the
 *   order's `post_date` (site time). Gross is `_order_total`.
 * - Refunded is the whole total of a `ohmylms-refunded` order, otherwise the sum of its
 *   `ohmylms_order_refund` child posts (`_refund_amount`) capped at the total. Net is gross minus refunded.
 * - Per course, a line item's `_line_total` is gross and its refund is the order refund pro rata.
 * - A sale is one line item of a course in such an order.
 * - Enrolled students are distinct users with an `enrolled` row whose `start_date` is in the range.
 *
 * Every aggregate runs in the database; nothing here loads an order or course object per row, and
 * every list is paged. Methods throw RuntimeException when the database reports an error.
 *
 * @since 1.0.0
 */
class Repository {

	const ORDER_TYPE  = 'ohmylms-order';
	const REFUND_TYPE = 'ohmylms_order_refund';
	const COURSE_TYPE = 'ohmylms-course';

	/**
	 * Course statuses that appear in reports (everything but trash and auto-drafts).
	 */
	const COURSE_STATUSES = array( 'publish', 'private', 'draft', 'pending', 'future' );

	/**
	 * Sort keys of the course report and their SQL.
	 */
	const COURSE_ORDERBY = array(
		'title'       => 'c.post_title',
		'date'        => 'c.post_date',
		'sales'       => 'sales',
		'gross'       => 'gross',
		'refunds'     => 'refunds',
		'net'         => 'net',
		'enrollments' => 'enrollments',
	);

	/**
	 * Sort keys of the transactions table and their SQL.
	 */
	const TRANSACTION_ORDERBY = array(
		'date'     => 'o.post_date',
		'id'       => 'o.order_id',
		'total'    => 'o.total',
		'refunded' => 'o.refunded',
		'net'      => '( o.total - o.refunded )',
	);

	/**
	 * Sort keys of a course's students and their SQL.
	 */
	const STUDENT_ORDERBY = array(
		'name'        => 'u.display_name',
		'enrolled_at' => 'e.start_date',
		'percent'     => 'percent_complete',
	);

	/**
	 * Number of decimals of the site currency.
	 *
	 * @return int
	 */
	public function decimals() {
		return function_exists( 'ohmylms_get_price_decimals' ) ? max( 0, min( 4, (int) ohmylms_get_price_decimals() ) ) : 2;
	}

	/**
	 * Site currency description.
	 *
	 * @return array{code:string,symbol:string,decimals:int,position:string}
	 */
	public function currency() {
		$code = function_exists( 'get_ohmylms_currency' ) ? (string) get_ohmylms_currency() : 'USD';
		return array(
			'code'     => $code,
			'symbol'   => function_exists( 'get_ohmylms_currency_symbol' ) ? html_entity_decode( (string) get_ohmylms_currency_symbol( $code ), ENT_QUOTES, 'UTF-8' ) : $code,
			'decimals' => $this->decimals(),
			'position' => function_exists( 'get_ohmylms_currency_position' ) ? (string) get_ohmylms_currency_position() : 'left',
		);
	}

	/**
	 * Round an amount to the currency decimals.
	 *
	 * @param mixed $value Amount.
	 * @return float
	 */
	private function money( $value ) {
		return round( (float) $value, $this->decimals() );
	}

	/**
	 * Run a prepared SELECT returning rows.
	 *
	 * @param string $sql  SQL with placeholders.
	 * @param array  $args Placeholder values.
	 * @return array[]
	 * @throws RuntimeException On a database error.
	 */
	private function rows( $sql, array $args ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Identifiers use %i, values use placeholders and sort keys come from allow-lists.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		if ( '' !== $wpdb->last_error ) {
			throw new RuntimeException( 'Analytics query failed.' );
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Run a prepared SELECT returning one row.
	 *
	 * @param string $sql  SQL with placeholders.
	 * @param array  $args Placeholder values.
	 * @return array
	 */
	private function row( $sql, array $args ) {
		$rows = $this->rows( $sql, $args );
		return $rows ? $rows[0] : array();
	}

	/**
	 * SQL of the paid orders in a range with total and refunded amount.
	 *
	 * @param string $start Start datetime.
	 * @param string $end   End datetime.
	 * @param array  $args  Placeholder values, appended to.
	 * @return string
	 */
	private function orders_sql( $start, $end, array &$args ) {
		global $wpdb;
		$args = array_merge(
			$args,
			array( $wpdb->posts, $wpdb->postmeta, self::REFUND_TYPE, $wpdb->posts, $wpdb->postmeta, self::ORDER_TYPE, $start, $end )
		);
		return "SELECT p.ID AS order_id, p.post_date, p.post_status,
			CAST( t.meta_value AS DECIMAL(20,4) ) AS total,
			CASE WHEN p.post_status = 'ohmylms-refunded' THEN CAST( t.meta_value AS DECIMAL(20,4) )
			ELSE LEAST( CAST( t.meta_value AS DECIMAL(20,4) ), COALESCE( (
				SELECT SUM( CAST( rm.meta_value AS DECIMAL(20,4) ) ) FROM %i r
				INNER JOIN %i rm ON rm.post_id = r.ID AND rm.meta_key = '_refund_amount'
				WHERE r.post_parent = p.ID AND r.post_type = %s AND r.post_status <> 'trash'
			), 0 ) ) END AS refunded
			FROM %i p INNER JOIN %i t ON t.post_id = p.ID AND t.meta_key = '_order_total'
			WHERE p.post_type = %s AND p.post_status IN ( 'ohmylms-completed', 'ohmylms-refunded' )
			AND p.post_date BETWEEN %s AND %s";
	}

	/**
	 * SQL of the course line items of the paid orders in a range.
	 *
	 * @param string $start Start datetime.
	 * @param string $end   End datetime.
	 * @param array  $args  Placeholder values, appended to.
	 * @return string
	 */
	private function items_sql( $start, $end, array &$args ) {
		global $wpdb;
		$inner = $this->orders_sql( $start, $end, $args );
		$args  = array_merge( $args, array( $wpdb->prefix . 'ohmylms_order_items', $wpdb->prefix . 'ohmylms_order_itemmeta', $wpdb->prefix . 'ohmylms_order_itemmeta' ) );
		return "SELECT o.order_id, o.post_date, CAST( cm.meta_value AS UNSIGNED ) AS course_id,
			COALESCE( CAST( lm.meta_value AS DECIMAL(20,4) ), 0 ) AS line,
			CASE WHEN o.total > 0 THEN COALESCE( CAST( lm.meta_value AS DECIMAL(20,4) ), 0 ) * o.refunded / o.total ELSE 0 END AS share
			FROM ( $inner ) o
			INNER JOIN %i oi ON oi.order_id = o.order_id
			INNER JOIN %i cm ON cm.order_item_id = oi.order_item_id AND cm.meta_key = '_course_id'
			LEFT JOIN %i lm ON lm.order_item_id = oi.order_item_id AND lm.meta_key = '_line_total'";
	}

	/**
	 * Totals of a period.
	 *
	 * @param string $start Start datetime.
	 * @param string $end   End datetime.
	 * @return array{orders:int,gross:float,refunds:float,net:float,sales:int,enrollments:int}
	 */
	public function summary( $start, $end ) {
		global $wpdb;

		$args  = array();
		$inner = $this->orders_sql( $start, $end, $args );
		$money = $this->row( "SELECT COUNT(*) AS orders, COALESCE( SUM( o.total ), 0 ) AS gross, COALESCE( SUM( o.refunded ), 0 ) AS refunds FROM ( $inner ) o", $args );

		$args  = array();
		$items = $this->items_sql( $start, $end, $args );
		$args  = array_merge( $args, array( $wpdb->posts, self::COURSE_TYPE ) );
		$sales = $this->row( "SELECT COUNT(*) AS sales FROM ( $items ) i INNER JOIN %i c ON c.ID = i.course_id AND c.post_type = %s", $args );

		$enrolled = $this->row(
			"SELECT COUNT( DISTINCT user_id ) AS students FROM %i WHERE status = 'enrolled' AND start_date BETWEEN %s AND %s",
			array( $wpdb->prefix . 'ohmylms_user_enrollment', $start, $end )
		);

		$gross   = $this->money( $money['gross'] ?? 0 );
		$refunds = $this->money( $money['refunds'] ?? 0 );
		return array(
			'orders'      => (int) ( $money['orders'] ?? 0 ),
			'gross'       => $gross,
			'refunds'     => $refunds,
			'net'         => $this->money( $gross - $refunds ),
			'sales'       => (int) ( $sales['sales'] ?? 0 ),
			'enrollments' => (int) ( $enrolled['students'] ?? 0 ),
		);
	}

	/**
	 * Earnings per chart bucket, including empty buckets.
	 *
	 * @param DateRange $range Range.
	 * @return array<int,array{date:string,orders:int,gross:float,refunds:float,net:float}>
	 */
	public function series( DateRange $range ) {
		$args  = array();
		$inner = $this->orders_sql( $range->start_sql(), $range->end_sql(), $args );
		$len   = (int) $range->bucket_length();
		$rows  = $this->rows(
			"SELECT SUBSTRING( o.post_date, 1, $len ) AS bucket, COUNT(*) AS orders, SUM( o.total ) AS gross, SUM( o.refunded ) AS refunds FROM ( $inner ) o GROUP BY bucket",
			$args
		);
		return $this->fill_series( $range, $rows );
	}

	/**
	 * Earnings of one course per chart bucket.
	 *
	 * @param int       $course_id Course ID.
	 * @param DateRange $range     Range.
	 * @return array
	 */
	public function course_series( $course_id, DateRange $range ) {
		$args   = array();
		$items  = $this->items_sql( $range->start_sql(), $range->end_sql(), $args );
		$args[] = (int) $course_id;
		$len    = (int) $range->bucket_length();
		$rows   = $this->rows(
			"SELECT SUBSTRING( i.post_date, 1, $len ) AS bucket, COUNT(*) AS orders, SUM( i.line ) AS gross, SUM( i.share ) AS refunds FROM ( $items ) i WHERE i.course_id = %d GROUP BY bucket",
			$args
		);
		return $this->fill_series( $range, $rows );
	}

	/**
	 * Merge grouped rows into the full list of buckets.
	 *
	 * @param DateRange $range Range.
	 * @param array[]   $rows  Rows with bucket, orders, gross, refunds.
	 * @return array
	 */
	private function fill_series( DateRange $range, array $rows ) {
		$by_key = array();
		foreach ( $rows as $row ) {
			$by_key[ $row['bucket'] ] = $row;
		}
		$series = array();
		foreach ( $range->bucket_keys() as $key ) {
			$row      = $by_key[ $key ] ?? array();
			$gross    = $this->money( $row['gross'] ?? 0 );
			$refunds  = $this->money( $row['refunds'] ?? 0 );
			$series[] = array(
				'date'    => $key,
				'orders'  => (int) ( $row['orders'] ?? 0 ),
				'gross'   => $gross,
				'refunds' => $refunds,
				'net'     => $this->money( $gross - $refunds ),
			);
		}
		return $series;
	}

	/**
	 * Gross, refunds and sales of one course in a period.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $start     Start datetime.
	 * @param string $end       End datetime.
	 * @return array{sales:int,gross:float,refunds:float,net:float}
	 */
	public function course_money( $course_id, $start, $end ) {
		$args   = array();
		$items  = $this->items_sql( $start, $end, $args );
		$args[] = (int) $course_id;
		$row    = $this->row( "SELECT COUNT(*) AS sales, COALESCE( SUM( i.line ), 0 ) AS gross, COALESCE( SUM( i.share ), 0 ) AS refunds FROM ( $items ) i WHERE i.course_id = %d", $args );
		$gross  = $this->money( $row['gross'] ?? 0 );
		$refund = $this->money( $row['refunds'] ?? 0 );
		return array(
			'sales'   => (int) ( $row['sales'] ?? 0 ),
			'gross'   => $gross,
			'refunds' => $refund,
			'net'     => $this->money( $gross - $refund ),
		);
	}

	/**
	 * Order totals by billing country (aggregate only, no customer data).
	 *
	 * @param DateRange $range Range.
	 * @param int       $limit Row limit.
	 * @return array<int,array{country:string,orders:int,gross:float}>
	 */
	public function by_country( DateRange $range, $limit = 10 ) {
		global $wpdb;
		$args   = array();
		$inner  = $this->orders_sql( $range->start_sql(), $range->end_sql(), $args );
		$args[] = $wpdb->postmeta;
		$args[] = (int) $limit;
		$rows   = $this->rows(
			"SELECT cm.meta_value AS country, COUNT(*) AS orders, SUM( o.total ) AS gross FROM ( $inner ) o
			INNER JOIN %i cm ON cm.post_id = o.order_id AND cm.meta_key = '_country' AND cm.meta_value <> ''
			GROUP BY cm.meta_value ORDER BY gross DESC, country ASC LIMIT %d",
			$args
		);
		return array_map(
			function ( $row ) {
				return array(
					'country' => (string) $row['country'],
					'orders'  => (int) $row['orders'],
					'gross'   => $this->money( $row['gross'] ),
				);
			},
			$rows
		);
	}

	/**
	 * Order totals by payment method.
	 *
	 * @param DateRange $range Range.
	 * @param int       $limit Row limit.
	 * @return array<int,array{id:string,title:string,orders:int,gross:float}>
	 */
	public function by_payment_method( DateRange $range, $limit = 20 ) {
		global $wpdb;
		$args   = array();
		$inner  = $this->orders_sql( $range->start_sql(), $range->end_sql(), $args );
		$args[] = $wpdb->postmeta;
		$args[] = $wpdb->postmeta;
		$args[] = (int) $limit;
		$rows   = $this->rows(
			"SELECT pm.meta_value AS id, MAX( pt.meta_value ) AS title, COUNT(*) AS orders, SUM( o.total ) AS gross FROM ( $inner ) o
			INNER JOIN %i pm ON pm.post_id = o.order_id AND pm.meta_key = '_payment_method' AND pm.meta_value <> ''
			LEFT JOIN %i pt ON pt.post_id = o.order_id AND pt.meta_key = '_payment_method_title'
			GROUP BY pm.meta_value ORDER BY gross DESC, id ASC LIMIT %d",
			$args
		);
		return array_map(
			function ( $row ) {
				return array(
					'id'     => (string) $row['id'],
					'title'  => '' !== (string) $row['title'] ? (string) $row['title'] : (string) $row['id'],
					'orders' => (int) $row['orders'],
					'gross'  => $this->money( $row['gross'] ),
				);
			},
			$rows
		);
	}

	/**
	 * A page of orders (transactions) in a range.
	 *
	 * @param DateRange           $range   Range.
	 * @param array<string,mixed> $filters payment_method, order_id.
	 * @param string              $orderby Key of TRANSACTION_ORDERBY.
	 * @param string              $order   asc or desc.
	 * @param int                 $limit   Page size.
	 * @param int                 $offset  Offset.
	 * @return array{items:array,total:int}
	 */
	public function transactions( DateRange $range, array $filters, $orderby, $order, $limit, $offset ) {
		global $wpdb;

		$where = '';
		$tail  = array();
		if ( ! empty( $filters['payment_method'] ) ) {
			$where .= ' AND pm.meta_value = %s';
			$tail[] = (string) $filters['payment_method'];
		}
		if ( ! empty( $filters['order_id'] ) ) {
			$where .= ' AND o.order_id = %d';
			$tail[] = (int) $filters['order_id'];
		}

		$join = array( $wpdb->postmeta, $wpdb->postmeta );
		$args = array();
		$from = $this->orders_sql( $range->start_sql(), $range->end_sql(), $args );
		$from = "FROM ( $from ) o LEFT JOIN %i pm ON pm.post_id = o.order_id AND pm.meta_key = '_payment_method'
			LEFT JOIN %i pt ON pt.post_id = o.order_id AND pt.meta_key = '_payment_method_title' WHERE 1 = 1$where";
		$args = array_merge( $args, $join, $tail );

		$count = $this->row( "SELECT COUNT(*) AS n $from", $args );
		$total = (int) ( $count['n'] ?? 0 );

		$column = self::TRANSACTION_ORDERBY[ $orderby ] ?? self::TRANSACTION_ORDERBY['date'];
		$dir    = 'asc' === $order ? 'ASC' : 'DESC';
		$rows   = $this->rows(
			"SELECT o.order_id, o.post_date, o.post_status, o.total, o.refunded, pm.meta_value AS payment_method, pt.meta_value AS payment_title $from ORDER BY $column $dir, o.order_id DESC LIMIT %d OFFSET %d",
			array_merge( $args, array( (int) $limit, (int) $offset ) )
		);

		// One query for the line items of the whole page.
		$ids   = array_map( 'intval', wp_list_pluck( $rows, 'order_id' ) );
		$items = array();
		if ( $ids ) {
			$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$found   = $this->rows(
				"SELECT oi.order_id, oi.order_item_name AS title, CAST( cm.meta_value AS UNSIGNED ) AS course_id FROM %i oi
				LEFT JOIN %i cm ON cm.order_item_id = oi.order_item_id AND cm.meta_key = '_course_id'
				WHERE oi.order_id IN ( $holders ) ORDER BY oi.order_item_id ASC",
				array_merge( array( $wpdb->prefix . 'ohmylms_order_items', $wpdb->prefix . 'ohmylms_order_itemmeta' ), $ids )
			);
			foreach ( $found as $item ) {
				$items[ (int) $item['order_id'] ][] = array(
					'course_id' => (int) $item['course_id'],
					'title'     => html_entity_decode( (string) $item['title'], ENT_QUOTES, 'UTF-8' ),
				);
			}
		}

		$list = array();
		foreach ( $rows as $row ) {
			$gross  = $this->money( $row['total'] );
			$refund = $this->money( $row['refunded'] );
			$list[] = array(
				'order_id'       => (int) $row['order_id'],
				'date'           => str_replace( ' ', 'T', (string) $row['post_date'] ),
				'status'         => 'ohmylms-refunded' === $row['post_status'] ? 'refunded' : 'completed',
				'payment_method' => (string) $row['payment_method'],
				'payment_title'  => '' !== (string) $row['payment_title'] ? (string) $row['payment_title'] : (string) $row['payment_method'],
				'total'          => $gross,
				'refunded'       => $refund,
				'net'            => $this->money( $gross - $refund ),
				'items'          => $items[ (int) $row['order_id'] ] ?? array(),
			);
		}
		return array(
			'items' => $list,
			'total' => $total,
		);
	}

	/**
	 * A page of the course report.
	 *
	 * @param DateRange           $range   Range for money and enrolments.
	 * @param array<string,mixed> $filters search (title), active_only (enrolments in range).
	 * @param string              $orderby Key of COURSE_ORDERBY.
	 * @param string              $order   asc or desc.
	 * @param int                 $limit   Page size.
	 * @param int                 $offset  Offset.
	 * @return array{items:array,total:int}
	 */
	public function courses( DateRange $range, array $filters, $orderby, $order, $limit, $offset ) {
		global $wpdb;

		$statuses = implode( ',', array_fill( 0, count( self::COURSE_STATUSES ), '%s' ) );
		$where    = " WHERE c.post_type = %s AND c.post_status IN ( $statuses )";
		$tail     = array_merge( array( self::COURSE_TYPE ), self::COURSE_STATUSES );
		if ( isset( $filters['search'] ) && '' !== $filters['search'] ) {
			$where .= ' AND c.post_title LIKE %s';
			$tail[] = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
		}
		if ( ! empty( $filters['active_only'] ) ) {
			$where .= ' AND COALESCE( e.enrollments, 0 ) > 0';
		}

		$args  = array( $wpdb->posts );
		$items = $this->items_sql( $range->start_sql(), $range->end_sql(), $args );
		$args  = array_merge( $args, array( $wpdb->prefix . 'ohmylms_user_enrollment', $range->start_sql(), $range->end_sql() ), $tail );
		$from  = " FROM %i c
			LEFT JOIN ( SELECT i.course_id, COUNT(*) AS sales, SUM( i.line ) AS gross, SUM( i.share ) AS refunds FROM ( $items ) i GROUP BY i.course_id ) s ON s.course_id = c.ID
			LEFT JOIN ( SELECT course_id, COUNT( DISTINCT user_id ) AS enrollments, COUNT( DISTINCT CASE WHEN progress = 'completed' THEN user_id END ) AS completed FROM %i
				WHERE status = 'enrolled' AND start_date BETWEEN %s AND %s GROUP BY course_id ) e ON e.course_id = c.ID$where";

		// The count query repeats the derived tables only when a filter needs them.
		$count_args = array_merge( array( $wpdb->posts ), $tail );
		$count_from = " FROM %i c$where";
		if ( ! empty( $filters['active_only'] ) ) {
			$count = $this->row( "SELECT COUNT(*) AS n$from", $args );
		} else {
			$count = $this->row( "SELECT COUNT(*) AS n$count_from", $count_args );
		}
		$total = (int) ( $count['n'] ?? 0 );

		$column = self::COURSE_ORDERBY[ $orderby ] ?? self::COURSE_ORDERBY['date'];
		$dir    = 'asc' === $order ? 'ASC' : 'DESC';
		$rows   = $this->rows(
			"SELECT c.ID AS id, c.post_title AS title, c.post_status AS status, c.post_date AS date,
			COALESCE( s.sales, 0 ) AS sales, COALESCE( s.gross, 0 ) AS gross, COALESCE( s.refunds, 0 ) AS refunds,
			COALESCE( s.gross, 0 ) - COALESCE( s.refunds, 0 ) AS net,
			COALESCE( e.enrollments, 0 ) AS enrollments, COALESCE( e.completed, 0 ) AS completed$from
			ORDER BY $column $dir, c.ID ASC LIMIT %d OFFSET %d",
			array_merge( $args, array( (int) $limit, (int) $offset ) )
		);

		$list = array();
		foreach ( $rows as $row ) {
			$gross  = $this->money( $row['gross'] );
			$refund = $this->money( $row['refunds'] );
			$list[] = array(
				'id'          => (int) $row['id'],
				'title'       => html_entity_decode( (string) $row['title'], ENT_QUOTES, 'UTF-8' ),
				'status'      => (string) $row['status'],
				'date'        => str_replace( ' ', 'T', (string) $row['date'] ),
				'sales'       => (int) $row['sales'],
				'gross'       => $gross,
				'refunds'     => $refund,
				'net'         => $this->money( $gross - $refund ),
				'enrollments' => (int) $row['enrollments'],
				'completed'   => (int) $row['completed'],
			);
		}
		return array(
			'items' => $list,
			'total' => $total,
		);
	}

	/**
	 * Site-wide counts that do not depend on a range.
	 *
	 * @return array{courses:int,published_courses:int,students:int}
	 */
	public function catalog_totals() {
		global $wpdb;
		$statuses = implode( ',', array_fill( 0, count( self::COURSE_STATUSES ), '%s' ) );
		$courses  = $this->row(
			"SELECT COUNT(*) AS total, COALESCE( SUM( post_status = 'publish' ), 0 ) AS published FROM %i WHERE post_type = %s AND post_status IN ( $statuses )",
			array_merge( array( $wpdb->posts, self::COURSE_TYPE ), self::COURSE_STATUSES )
		);
		$students = $this->row(
			"SELECT COUNT( DISTINCT user_id ) AS n FROM %i WHERE status = 'enrolled'",
			array( $wpdb->prefix . 'ohmylms_user_enrollment' )
		);
		return array(
			'courses'           => (int) ( $courses['total'] ?? 0 ),
			'published_courses' => (int) ( $courses['published'] ?? 0 ),
			'students'          => (int) ( $students['n'] ?? 0 ),
		);
	}

	/**
	 * Published content of a course by kind.
	 *
	 * @param int $course_id Course ID.
	 * @return array{chapters:int,lessons:int,quizzes:int,assignments:int}
	 */
	public function content_counts( $course_id ) {
		global $wpdb;
		$chapters = $this->row( 'SELECT COUNT(*) AS n FROM %i WHERE course_id = %d', array( $wpdb->prefix . 'ohmylms_chapter_relationship', (int) $course_id ) );
		$rows     = $this->rows(
			"SELECT lr.content_type, COUNT( DISTINCT lr.content_id ) AS n FROM %i cr
			INNER JOIN %i lr ON lr.chapter_id = cr.chapter_id
			INNER JOIN %i p ON p.ID = lr.content_id AND p.post_status = 'publish'
			WHERE cr.course_id = %d GROUP BY lr.content_type",
			array( $wpdb->prefix . 'ohmylms_chapter_relationship', $wpdb->prefix . 'ohmylms_content_relationship', $wpdb->posts, (int) $course_id )
		);
		$counts   = array(
			'chapters'    => (int) ( $chapters['n'] ?? 0 ),
			'lessons'     => 0,
			'quizzes'     => 0,
			'assignments' => 0,
		);
		foreach ( $rows as $row ) {
			if ( 'quiz' === $row['content_type'] ) {
				$counts['quizzes'] += (int) $row['n'];
			} elseif ( 'assignment' === $row['content_type'] ) {
				$counts['assignments'] += (int) $row['n'];
			} else {
				$counts['lessons'] += (int) $row['n'];
			}
		}
		return $counts;
	}

	/**
	 * Enrolment totals of a course.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $start     Start datetime of the range.
	 * @param string $end       End datetime of the range.
	 * @return array{enrolled:int,in_range:int,completed:int,in_progress:int}
	 */
	public function course_enrollments( $course_id, $start, $end ) {
		global $wpdb;
		$row       = $this->row(
			"SELECT COUNT( DISTINCT user_id ) AS enrolled,
			COUNT( DISTINCT CASE WHEN start_date BETWEEN %s AND %s THEN user_id END ) AS in_range,
			COUNT( DISTINCT CASE WHEN progress = 'completed' THEN user_id END ) AS completed
			FROM %i WHERE course_id = %d AND status = 'enrolled'",
			array( $start, $end, $wpdb->prefix . 'ohmylms_user_enrollment', (int) $course_id )
		);
		$enrolled  = (int) ( $row['enrolled'] ?? 0 );
		$completed = (int) ( $row['completed'] ?? 0 );
		return array(
			'enrolled'    => $enrolled,
			'in_range'    => (int) ( $row['in_range'] ?? 0 ),
			'completed'   => $completed,
			'in_progress' => max( 0, $enrolled - $completed ),
		);
	}

	/**
	 * A page of the students of a course with their progress.
	 *
	 * @param int                 $course_id Course ID.
	 * @param array<string,mixed> $filters   progress (running|completed), search.
	 * @param string              $orderby   Key of STUDENT_ORDERBY.
	 * @param string              $order     asc or desc.
	 * @param int                 $limit     Page size.
	 * @param int                 $offset    Offset.
	 * @return array{items:array,total:int}
	 */
	public function course_students( $course_id, array $filters, $orderby, $order, $limit, $offset ) {
		global $wpdb;

		$course_id = (int) $course_id;
		$totals    = $this->content_counts( $course_id );
		$lt        = (int) $totals['lessons'];
		$qt        = (int) $totals['quizzes'];
		$at        = (int) $totals['assignments'];
		$all       = $lt + $qt + $at;
		// The totals are integers computed above, so they are safe to inline.
		$percent = "CASE WHEN e.progress = 'completed' THEN 100 WHEN $all = 0 THEN 0 ELSE FLOOR( 100 * ( LEAST( $lt, COALESCE( pr.ld, 0 ) ) + LEAST( $qt, COALESCE( pr.qd, 0 ) ) + LEAST( $at, COALESCE( pr.ad, 0 ) ) ) / $all ) END";

		$table = $wpdb->prefix . 'ohmylms_user_enrollment';
		$where = " WHERE e.course_id = %d AND e.status IN ( 'enrolled', 'banned' )";
		if ( in_array( $filters['progress'] ?? '', array( 'running', 'completed' ), true ) ) {
			$where .= 'completed' === $filters['progress'] ? " AND e.progress = 'completed'" : " AND e.progress <> 'completed'";
		}
		// Placeholder order: FROM tables first, then the WHERE values.
		$from = " FROM %i e INNER JOIN %i u ON u.ID = e.user_id
			LEFT JOIN ( SELECT up.enrollment_id,
				COUNT( DISTINCT CASE WHEN up.content_type NOT IN ( 'quiz', 'assignment' ) THEN up.content_id END ) AS ld,
				COUNT( DISTINCT CASE WHEN up.content_type = 'quiz' THEN up.content_id END ) AS qd,
				COUNT( DISTINCT CASE WHEN up.content_type = 'assignment' THEN up.content_id END ) AS ad
				FROM %i up WHERE up.status = 'completed' AND up.enrollment_id IN ( SELECT id FROM %i WHERE course_id = %d ) GROUP BY up.enrollment_id ) pr ON pr.enrollment_id = e.id";
		$args = array( $table, $wpdb->users, $wpdb->prefix . 'ohmylms_user_progress', $table, $course_id, $course_id );
		if ( isset( $filters['search'] ) && '' !== $filters['search'] ) {
			$like   = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where .= ' AND ( u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s )';
			$args   = array_merge( $args, array( $like, $like, $like ) );
		}

		$count = $this->row( "SELECT COUNT(*) AS n$from$where", $args );
		$total = (int) ( $count['n'] ?? 0 );

		$column = self::STUDENT_ORDERBY[ $orderby ] ?? self::STUDENT_ORDERBY['name'];
		$dir    = 'desc' === $order ? 'DESC' : 'ASC';
		$rows   = $this->rows(
			"SELECT e.user_id, e.status, e.progress, e.start_date, e.end_date, u.display_name, u.user_email,
			COALESCE( pr.ld, 0 ) AS ld, COALESCE( pr.qd, 0 ) AS qd, COALESCE( pr.ad, 0 ) AS ad, $percent AS percent_complete$from$where
			ORDER BY $column $dir, e.id ASC LIMIT %d OFFSET %d",
			array_merge( $args, array( (int) $limit, (int) $offset ) )
		);

		$list = array();
		foreach ( $rows as $row ) {
			$user_id = (int) $row['user_id'];
			$done    = 'completed' === $row['progress'];
			$empty   = 0 === strpos( (string) $row['end_date'], '0000-00-00' );
			$list[]  = array(
				'user_id'          => $user_id,
				'name'             => (string) $row['display_name'],
				'email'            => (string) $row['user_email'],
				'avatar_url'       => (string) get_avatar_url( $user_id, array( 'size' => 48 ) ),
				'status'           => 'banned' === $row['status'] ? 'banned' : 'enrolled',
				'progress'         => $done ? 'completed' : 'running',
				'enrolled_at'      => 0 === strpos( (string) $row['start_date'], '0000-00-00' ) ? null : str_replace( ' ', 'T', (string) $row['start_date'] ),
				'completed_at'     => $done && ! $empty ? str_replace( ' ', 'T', (string) $row['end_date'] ) : null,
				'lessons'          => array(
					'completed' => min( $lt, (int) $row['ld'] ),
					'total'     => $lt,
				),
				'quizzes'          => array(
					'completed' => min( $qt, (int) $row['qd'] ),
					'total'     => $qt,
				),
				'assignments'      => array(
					'completed' => min( $at, (int) $row['ad'] ),
					'total'     => $at,
				),
				'percent_complete' => (int) $row['percent_complete'],
			);
		}
		return array(
			'items' => $list,
			'total' => $total,
		);
	}
}
