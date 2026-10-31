<?php
/**
 * Enrolments table, migration and dual-write integration test (docs/api/enrolments.md).
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. It creates
 * tagged users, courses, orders, membership and class rows plus legacy enrolment rows for every source,
 * runs the migration up (interrupted and resumed), checks idempotency, the reads on both tables, every
 * service write path, the down step, a second up, the schema down/up and the watchdog, then removes its
 * fixtures and restores the migration state it found.
 *
 * Usage (set OHMYLMS_ENROLMENTS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/enrolments-migration-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, Generic.Formatting.MultipleStatementAlignment

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_ENROLMENTS_TEST' ) ) {
	exit( "Set OHMYLMS_ENROLMENTS_TEST=1 to run the enrolments migration tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
add_filter( 'pre_wp_mail', '__return_true' );

use OhMyLMS\Student\Directory;
use OhMyLMS\Student\EnrolmentMigration;
use OhMyLMS\Student\EnrolmentMirror;
use OhMyLMS\Student\EnrolmentSchema;
use OhMyLMS\Student\Enrollments;

global $wpdb;

$checks      = 0;
$tag         = 'enrtest' . strtolower( wp_generate_password( 6, false, false ) );
$user_ids    = array();
$post_ids    = array();
$saved_state = get_option( EnrolmentMigration::OPTION, null );
$saved_db    = get_option( 'ohmylms_db_version', null );
$legacy      = $wpdb->prefix . 'ohmylms_user_enrollment';
$members     = $wpdb->prefix . 'ohmylms_user_membership';
$table       = EnrolmentSchema::table();
$class_ids   = array();

/**
 * Assert a condition.
 *
 * @param mixed  $value   Value that must be truthy.
 * @param string $message Failure message.
 * @throws RuntimeException When the condition is falsy.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * Create a tagged user.
 *
 * @param string $suffix Login suffix.
 * @param string $role   Role.
 * @return int
 */
function make_user( $suffix, $role = 'subscriber' ) {
	global $tag, $user_ids;
	$id = wp_insert_user(
		array(
			'user_login'   => $tag . '_' . $suffix,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '.' . $suffix . '@example.invalid',
			'display_name' => 'Enrolment ' . $tag . ' ' . $suffix,
			'role'         => $role,
		)
	);
	ok( ! is_wp_error( $id ), 'user fixture ' . $suffix );
	$user_ids[] = $id;
	return $id;
}

/**
 * Create a tagged post.
 *
 * @param string $type Post type.
 * @param string $name Title suffix.
 * @param array  $meta Post meta.
 * @return int
 */
function make_post( $type, $name, array $meta = array() ) {
	global $tag, $post_ids;
	$id = wp_insert_post(
		array(
			'post_type'   => $type,
			'post_title'  => $tag . ' ' . $name,
			'post_status' => 'publish',
		)
	);
	ok( $id && ! is_wp_error( $id ), 'post fixture ' . $name );
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	$post_ids[] = $id;
	return $id;
}

/**
 * Write a legacy row directly, as code before this change did.
 *
 * @param int   $user   User ID.
 * @param int   $course Course ID.
 * @param array $extra  Other columns.
 * @return int Legacy row ID.
 */
function raw_enrol( $user, $course, array $extra = array() ) {
	global $wpdb, $legacy;
	$row = array_merge(
		array(
			'user_id'    => $user,
			'course_id'  => $course,
			'status'     => 'enrolled',
			'progress'   => 'running',
			'start_date' => current_time( 'mysql' ),
		),
		$extra
	);
	ok( $wpdb->insert( $legacy, $row ), 'legacy fixture row' );
	return (int) $wpdb->insert_id;
}

/**
 * Projection of a legacy row.
 *
 * @param int $legacy_id Legacy row ID.
 * @return array|null
 */
function projection( $legacy_id ) {
	global $wpdb, $table;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE legacy_id=%d', $table, $legacy_id ), ARRAY_A );
}

/**
 * Run a reader with reads forced onto the legacy table, then onto the new table.
 *
 * @param callable $read Reader.
 * @return array{0:mixed,1:mixed}
 */
function both( callable $read ) {
	$off = static function () {
		return false;
	};
	add_filter( 'ohmylms_enrolments_read_new', $off );
	$legacy_result = $read();
	remove_filter( 'ohmylms_enrolments_read_new', $off );
	ok( Enrollments::reads_new(), 'reads use the new table once complete' );
	return array( $legacy_result, $read() );
}

/**
 * Run the migration to completion.
 *
 * @return array State.
 */
function migrate_all() {
	$state = EnrolmentMigration::run( 1000, 50 );
	ok( 'complete' === $state['status'] && '' === $state['error'], 'migration completes: ' . wp_json_encode( $state ) );
	return $state;
}

/**
 * Dispatch a REST request.
 *
 * @param string $method Method.
 * @param string $path   Path below /ohmylms/v1.
 * @param array  $data   Body or query.
 * @return WP_REST_Response
 */
function call_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

try {
	$admin = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
		)
	);
	ok( $admin, 'an administrator exists' );
	$admin_id = (int) $admin[0]->ID;
	wp_set_current_user( $admin_id );

	// Schema.
	ok( EnrolmentSchema::install(), 'schema installs' );
	ok( get_option( EnrolmentSchema::OPTION ) === EnrolmentSchema::VERSION, 'schema version recorded' );
	$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
	foreach ( array( 'id', 'user_id', 'course_id', 'status', 'source', 'source_type', 'source_ref', 'class_id', 'legacy_id', 'active_key', 'migrated', 'enrolled_at', 'expires_at', 'completed_at', 'updated_at', 'created_by' ) as $column ) {
		ok( in_array( $column, $columns, true ), 'column ' . $column );
	}
	$indexes = array();
	foreach ( $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $table ), ARRAY_A ) as $index ) {
		$indexes[ $index['Key_name'] ]   = $indexes[ $index['Key_name'] ] ?? array(
			'unique'  => '0' === (string) $index['Non_unique'],
			'columns' => array(),
		);
		$indexes[ $index['Key_name'] ]['columns'][] = $index['Column_name'];
	}
	ok( $indexes['active_enrolment']['unique'] && array( 'user_id', 'course_id', 'source', 'source_type', 'source_ref', 'active_key' ) === $indexes['active_enrolment']['columns'], 'unique active user+course+source+ref key' );
	ok( $indexes['legacy_id']['unique'], 'unique legacy link' );
	ok( 'course_id' === $indexes['course_status']['columns'][0] && 'user_id' === $indexes['user_status']['columns'][0] && isset( $indexes['class_id'] ), 'course, user and class indexes' );

	// Start as a site that has not run the migration yet.
	delete_option( EnrolmentMigration::OPTION );
	wp_clear_scheduled_hook( EnrolmentMigration::UP_HOOK );
	ok( ! Enrollments::reads_new(), 'reads stay on legacy storage before the migration' );

	// Fixtures for every source.
	$u = array();
	foreach ( range( 1, 9 ) as $n ) {
		$u[ $n ] = make_user( 'u' . $n );
	}
	$plain    = make_post( OHMYLMS_COURSE_CPT, 'plain course' );
	$imported = make_post( OHMYLMS_COURSE_CPT, 'imported course', array( '_ohmylms_migration_source' => 'tutor' ) );
	$exam     = make_post( OHMYLMS_COURSE_CPT, 'exam course', array( '_ohmylms_exam_wrapper' => 1 ) );
	$rostered = make_post( OHMYLMS_COURSE_CPT, 'roster course' );
	$plan     = make_post( 'ohmylms-membership', 'plan' );
	$paid     = make_post( 'ohmylms-order', 'paid order', array( '_order_total' => '49.00' ) );
	$free     = make_post( 'ohmylms-order', 'free order', array( '_order_total' => '0' ) );
	$m_order  = make_post( 'ohmylms-order', 'membership order', array( '_order_total' => '20.00' ) );
	$s_order  = make_post( 'ohmylms-order', 'subscription order', array( '_order_total' => '9.00' ) );
	$sub_id   = 990000000 + wp_rand( 1, 999999 );
	$expires  = gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS );
	foreach ( array( array( $u[3], $m_order, 0, '' ), array( $u[4], $s_order, $sub_id, '' ), array( $u[5], 0, 0, $expires ) ) as list( $member, $order, $sub, $end ) ) {
		ok(
			$wpdb->insert(
				$members,
				array(
					'user_id'         => $member,
					'membership_id'   => $plan,
					'subscription_id' => $sub,
					'order_id'        => $order,
					'status'          => 'enrolled',
					'progress'        => 'running',
					'start_date'      => current_time( 'mysql' ),
					'end_date'        => $end ? $end : '0000-00-00 00:00:00',
				)
			),
			'membership fixture'
		);
	}
	$class = (int) \OhMyLMS\Schools\Service::insert(
		'classes',
		array(
			'school_id'        => 0,
			'academic_year_id' => 0,
			'name'             => $tag . ' class',
		)
	);
	$class_ids[] = $class;
	\OhMyLMS\Schools\Service::insert(
		'course_classes',
		array(
			'course_id'  => $rostered,
			'class_id'   => $class,
			'created_by' => $admin_id,
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		)
	);
	\OhMyLMS\Schools\Service::insert(
		'course_class_students',
		array(
			'course_id'  => $rostered,
			'class_id'   => $class,
			'user_id'    => $u[6],
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		)
	);

	$rows = array(
		'purchase'     => raw_enrol( $u[1], $plain, array( 'order_id' => $paid ) ),
		'free_order'   => raw_enrol(
			$u[2],
			$plain,
			array(
				'order_id' => $free,
				'progress' => 'completed',
				'end_date' => '2026-10-20 10:00:00',
			)
		),
		'membership'   => raw_enrol(
			$u[3],
			$plain,
			array(
				'order_id'      => $m_order,
				'membership_id' => $plan,
			)
		),
		'subscription' => raw_enrol(
			$u[4],
			$plain,
			array(
				'order_id'      => $s_order,
				'membership_id' => $plan,
			)
		),
		'free_grant'   => raw_enrol( $u[5], $plain, array( 'membership_id' => $plan ) ),
		'roster'       => raw_enrol( $u[6], $rostered ),
		'import'       => raw_enrol( $u[7], $imported ),
		'exam'         => raw_enrol( $u[7], $exam ),
		'banned'       => raw_enrol( $u[8], $plain, array( 'status' => 'banned' ) ),
		'cancelled'    => raw_enrol( $u[8], $imported, array( 'status' => 'cancelled' ) ),
		'pending'      => raw_enrol(
			$u[2],
			$rostered,
			array(
				'status'   => 'pending',
				'order_id' => $paid,
			)
		),
		'dropped'      => raw_enrol(
			$u[3],
			$rostered,
			array(
				'status'   => 'dropped',
				'progress' => 'stop',
			)
		),
		'expired'      => raw_enrol( $u[4], $rostered, array( 'status' => 'expired' ) ),
		'manual'       => raw_enrol( $u[5], $rostered ),
		'duplicate'    => raw_enrol( $u[5], $rostered ),
	);
	$expected = array(
		'purchase'     => array( 'purchase', 'order', $paid, null, 'active' ),
		'free_order'   => array( 'self', 'order', $free, null, 'completed' ),
		'membership'   => array( 'purchase', 'membership', $plan, null, 'active' ),
		'subscription' => array( 'purchase', 'subscription', $sub_id, null, 'active' ),
		'free_grant'   => array( 'self', 'membership', $plan, null, 'active' ),
		'roster'       => array( 'roster', 'class', $class, $class, 'active' ),
		'import'       => array( 'import', '', 0, null, 'active' ),
		'exam'         => array( 'self', 'exam', 0, null, 'active' ),
		'banned'       => array( 'manual', '', 0, null, 'suspended' ),
		'cancelled'    => array( 'import', '', 0, null, 'cancelled' ),
		'pending'      => array( 'purchase', 'order', $paid, null, 'pending' ),
		'dropped'      => array( 'manual', '', 0, null, 'cancelled' ),
		'expired'      => array( 'manual', '', 0, null, 'expired' ),
		'manual'       => array( 'manual', '', 0, null, 'active' ),
		'duplicate'    => array( 'manual', '', 0, null, 'active' ),
	);
	foreach ( $rows as $key => $id ) {
		ok( null === projection( $id ), 'no projection before the migration: ' . $key );
	}

	// A legacy update before the migration does not create projections.
	Enrollments::update( array( 'start_date' => current_time( 'mysql' ) ), array( 'id' => $rows['manual'] ) );
	ok( null === projection( $rows['manual'] ), 'updates before the migration leave the projection to it' );

	// Up, interrupted after a batch and resumed.
	$state = EnrolmentMigration::start();
	ok( 'running' === $state['status'] && 0 === (int) $state['cursor'], 'migration starts' );
	ok( wp_next_scheduled( EnrolmentMigration::UP_HOOK ), 'first batch scheduled on WP-Cron' );
	$state = EnrolmentMigration::run( 1, 4 );
	ok( 'running' === $state['status'] && $state['cursor'] > 0 && 1 === (int) $state['batches'], 'one batch ran' );
	ok( 4 === $state['created'] + $state['updated'] + $state['unchanged'], 'a batch handles its size' );
	ok( ! Enrollments::reads_new(), 'reads stay on legacy storage while the migration runs' );
	$cursor = (int) $state['cursor'];
	$state  = EnrolmentMigration::run( 1, 4 );
	ok( $state['cursor'] > $cursor && 2 === (int) $state['batches'], 'the next run resumes from the stored cursor' );

	// A service write during the migration gets its projection at once.
	ok(
		Enrollments::create(
			array(
				'user_id'    => $u[9],
				'course_id'  => $plain,
				'status'     => 'enrolled',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
			),
			array( 'source' => 'manual' )
		),
		'service insert during the migration'
	);
	$live_id = (int) $wpdb->insert_id;
	ok( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM %i WHERE id=%d', $legacy, $live_id ) ) === $u[9], 'insert_id is the legacy row ID' );
	$live = projection( $live_id );
	ok( $live && 'manual' === $live['source'] && '0' === $live['migrated'] && (int) $live['created_by'] === $admin_id, 'service rows record their source and author and are not migration rows' );

	// Walk to the sweep, then a writer outside the service adds a row: the sweep picks it up.
	for ( $i = 0; $i < 1000 && 'walk' === $state['phase'] && 'running' === $state['status']; $i++ ) {
		$state = EnrolmentMigration::run( 1, 50 );
	}
	ok( 'sweep' === $state['phase'], 'walk finished' );
	$rows['outside'] = raw_enrol( $u[6], $plain );
	$expected['outside'] = array( 'manual', '', 0, null, 'active' );
	$state = migrate_all();
	ok( '' !== $state['finished_at'] && $state['duplicates'] >= 1, 'completion time and duplicate count recorded' );
	ok( Enrollments::reads_new(), 'reads switch once the migration is complete' );

	foreach ( $rows as $key => $id ) {
		$p = projection( $id );
		ok( $p, 'projection exists: ' . $key );
		list( $source, $type, $ref, $class_id, $status ) = $expected[ $key ];
		ok( $source === $p['source'] && $type === $p['source_type'] && (int) $ref === (int) $p['source_ref'], 'source of ' . $key . ': ' . $p['source'] . '/' . $p['source_type'] . '/' . $p['source_ref'] );
		ok( ( null === $class_id ? null === $p['class_id'] : (int) $class_id === (int) $p['class_id'] ), 'class of ' . $key );
		ok( $status === $p['status'], 'status of ' . $key . ': ' . $p['status'] );
		ok( '1' === $p['migrated'] && '0' === $p['created_by'], 'migration row marked: ' . $key );
		ok( ( 'active' === $status ) === ( '1' === (string) $p['active_key'] ) || 'duplicate' === $key, 'active slot of ' . $key );
	}
	ok( null === projection( $rows['duplicate'] )['active_key'], 'second active row of the same source does not take the unique slot' );
	ok( '2026-10-20 10:00:00' === projection( $rows['free_order'] )['completed_at'], 'completion date copied' );
	ok( null === projection( $rows['purchase'] )['completed_at'], 'running enrolment has no completion date' );
	$grant_end = projection( $rows['free_grant'] )['expires_at'];
	ok( $expires === $grant_end, 'membership grant end becomes expires_at' );

	$report = EnrolmentMigration::compare();
	$ids_in = implode( ',', array_map( 'intval', array_merge( array_values( $rows ), array( $live_id ) ) ) );
	ok( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$legacy} l LEFT JOIN {$table} m ON m.legacy_id=l.id WHERE l.id IN ($ids_in) AND m.id IS NULL" ), 'every fixture row has a projection' );
	ok( 0 === $report['orphans'] && 0 === $report['mismatched'], 'no orphans or status mismatches: ' . wp_json_encode( $report ) );

	// Idempotent: a second full run changes nothing for existing rows.
	$snapshot = $wpdb->get_results( "SELECT id, legacy_id, status, source, source_type, source_ref, class_id, active_key, migrated FROM {$table} WHERE legacy_id IN ($ids_in) ORDER BY id", ARRAY_A );
	ok( 'complete' === EnrolmentMigration::start()['status'], 'start() leaves a complete migration alone' );
	EnrolmentMigration::mark_stale();
	ok( 'stale' === EnrolmentMigration::state()['status'] && ! Enrollments::reads_new(), 'a stale migration stops new-table reads' );
	$state = migrate_all();
	ok( $snapshot === $wpdb->get_results( "SELECT id, legacy_id, status, source, source_type, source_ref, class_id, active_key, migrated FROM {$table} WHERE legacy_id IN ($ids_in) ORDER BY id", ARRAY_A ), 'rerun keeps every row, ID and source' );
	ok( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT legacy_id FROM {$table} WHERE legacy_id IS NOT NULL GROUP BY legacy_id HAVING COUNT(*) > 1) d" ), 'never two projections of one legacy row' );

	// Reads give the same answers from both tables.
	foreach ( array( $u[2], $u[5], $u[8], $u[7] ) as $user ) {
		foreach ( array( 'all', 'running', 'completed' ) as $progress ) {
			list( $a, $b ) = both(
				static function () use ( $user, $progress ) {
					return array( Enrollments::count( $user, OHMYLMS_COURSE_CPT, $progress ), Enrollments::page( $user, OHMYLMS_COURSE_CPT, $progress, '', 1, 20 ) );
				}
			);
			ok( $a === $b, "student list parity ($user, $progress)" );
		}
		list( $a, $b ) = both(
			static function () use ( $user, $plain, $rostered ) {
				return array( Enrollments::find( $user, $plain ), Enrollments::find( $user, $rostered ), Enrollments::course_counts( $user ) );
			}
		);
		ok( $a === $b, "lookup and counts parity ($user)" );
	}
	list( $a, $b ) = both(
		static function () use ( $plain, $rostered, $imported, $exam ) {
			return array(
				Enrollments::count_by_course( array( $plain, $rostered, $imported, $exam ) ),
				Enrollments::course_user_ids( $plain ),
				Enrollments::course_user_ids( $plain, 2, 1 ),
				Enrollments::course_user_ids( $rostered, 0, 0, true ),
				Enrollments::count_in_course( $rostered ),
				Enrollments::course_roster( $plain, 0, 10, 0 ),
				Enrollments::course_roster( $rostered, 0, 2, 1 ),
				Enrollments::listed_students( $plain ),
			);
		}
	);
	ok( $a === $b, 'course reads parity: ' . wp_json_encode( array( $a, $b ) ) );
	list( $a, $b ) = both(
		static function () use ( $u, $plain, $imported ) {
			return array( Enrollments::is_listed( $u[8], $plain ), Enrollments::is_listed( $u[8], $imported ), Enrollments::is_listed( $u[1], $plain ) );
		}
	);
	ok( array( true, false, true ) === $a && $a === $b, 'listed parity' );
	list( $a, $b ) = both(
		static function () use ( $u ) {
			return call_api( 'GET', '/students/' . $u[2] . '/enrollments', array( 'per_page' => 50 ) )->get_data();
		}
	);
	ok( $a === $b && 1 === count( $a ), 'students REST response identical from both tables' );

	// A raw insert makes reads fall back at once and, after the grace period, schedules a sweep.
	$rows['lagging']    = raw_enrol( $u[9], $rostered );
	$expected['lagging'] = array( 'manual', '', 0, null, 'active' );
	ok( ! Enrollments::reads_new(), 'reads fall back while a legacy row has no projection' );
	ok( in_array( $u[9], Enrollments::course_user_ids( $rostered ), true ), 'the fallback sees the new row' );
	$seen = get_transient( 'ohmylms_enrolments_lag' );
	ok( is_array( $seen ) && (int) $seen['id'] === $rows['lagging'], 'first sighting remembered' );
	set_transient(
		'ohmylms_enrolments_lag',
		array(
			'id' => $rows['lagging'],
			'at' => time() - EnrolmentMigration::LAG_GRACE - 1,
		),
		HOUR_IN_SECONDS
	);
	ok( ! Enrollments::reads_new() && 'stale' === EnrolmentMigration::state()['status'] && 'sweep' === EnrolmentMigration::state()['phase'], 'a lasting lag schedules a sweep' );
	migrate_all();
	ok( projection( $rows['lagging'] ) && Enrollments::reads_new(), 'sweep projected the row and reads switch back' );

	// Every service write path keeps the projection in step (reads on the new table).
	ok( true === Directory::set_blocked( $u[1], true ), 'block' );
	ok( 'suspended' === projection( $rows['purchase'] )['status'] && null === projection( $rows['purchase'] )['active_key'], 'block suspends the projection' );
	ok( true === Directory::set_blocked( $u[1], false ), 'unblock' );
	ok( 'active' === projection( $rows['purchase'] )['status'] && '1' === projection( $rows['purchase'] )['active_key'], 'unblock reactivates it' );
	ok(
		1 === Enrollments::update(
			array(
				'progress' => 'completed',
				'end_date' => '2026-10-30 09:00:00',
			),
			array(
				'id'     => $rows['purchase'],
				'status' => 'enrolled',
			)
		),
		'update returns the affected rows like $wpdb'
	);
	$p = projection( $rows['purchase'] );
	ok( 'completed' === $p['status'] && '2026-10-30 09:00:00' === $p['completed_at'] && 'purchase' === $p['source'], 'completion recorded, source kept' );
	Enrollments::update( array( 'status' => 'cancelled' ), array( 'order_id' => $paid ) );
	ok( 'cancelled' === projection( $rows['purchase'] )['status'] && 'cancelled' === projection( $rows['pending'] )['status'], 'refund-style cancel by order' );
	ok( 1 === (int) Enrollments::update( array( 'status' => 'enrolled' ), array( 'id' => $rows['expired'] ), array( 'source' => 'manual' ) ), 'reactivation with a new source' );
	ok( 'active' === projection( $rows['expired'] )['status'], 'reactivated projection' );

	$response = call_api( 'POST', '/courses/' . $exam . '/enroll', array( 'student_id' => $u[1] ) );
	ok( 200 === $response->get_status() && true === $response->get_data()['success'], 'course enrol REST unchanged' );
	$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id=%d AND course_id=%d', $legacy, $u[1], $exam ) );
	ok( $id && 'manual' === projection( $id )['source'] && '0' === projection( $id )['migrated'], 'course enrol records manual' );
	$response = call_api( 'DELETE', '/courses/' . $exam . '/enroll/' . $u[1] );
	ok( 200 === $response->get_status() && true === $response->get_data()['success'] && null === projection( $id ), 'course unenrol removes both rows' );
	$response = call_api( 'POST', '/courses/' . $exam . '/enroll-by-email', array( 'email' => $tag . '.u3@example.invalid' ) );
	ok( 200 === $response->get_status() && true === $response->get_data()['success'], 'enrol by e-mail REST unchanged' );
	$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id=%d AND course_id=%d', $legacy, $u[3], $exam ) );
	ok( 'manual' === projection( $id )['source'] && 'active' === projection( $id )['status'], 'enrol by e-mail records manual' );
	$response = call_api( 'POST', '/students/' . $u[4] . '/enrollments', array( 'course_id' => $imported ) );
	ok( 201 === $response->get_status(), 'student enrol REST unchanged' );
	$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id=%d AND course_id=%d', $legacy, $u[4], $imported ) );
	ok( 'manual' === projection( $id )['source'], 'student enrol records manual' );
	$response = call_api( 'DELETE', '/students/' . $u[4] . '/enrollments/' . $imported );
	ok( 200 === $response->get_status() && null === projection( $id ), 'student unenrol removes the projection' );

	// Roster sync through the gradebook records the class.
	\OhMyLMS\Schools\Service::insert(
		'class_memberships',
		array(
			'class_id'  => $class,
			'user_id'   => $u[9],
			'role'      => 'student',
			'status'    => 'active',
			'joined_at' => gmdate( 'Y-m-d H:i:s' ),
		)
	);
	$synced = \OhMyLMS\Schools\Gradebook::attach( $exam, $class, true );
	ok( 1 === $synced['enrolled'], 'class sync enrols the roster' );
	$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id=%d AND course_id=%d', $legacy, $u[9], $exam ) );
	$p  = projection( $id );
	ok( 'roster' === $p['source'] && 'class' === $p['source_type'] && $class === (int) $p['source_ref'] && $class === (int) $p['class_id'], 'roster sync records class source' );
	ok( in_array( $u[9], wp_list_pluck( \OhMyLMS\Schools\Gradebook::read( $exam )['classes'][0]['students'], 'id' ), true ), 'gradebook reads the enrolment from the new table' );

	// Seat, import and membership origins.
	ok(
		Enrollments::create(
			array(
				'user_id'    => $u[8],
				'course_id'  => $plain,
				'status'     => 'enrolled',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
			),
			array(
				'source'      => 'seat',
				'source_type' => 'seat',
				'source_ref'  => 42,
			)
		),
		'seat enrolment'
	);
	$p = projection( (int) $wpdb->insert_id );
	ok( 'seat' === $p['source'] && 'seat' === $p['source_type'] && 42 === (int) $p['source_ref'] && '1' === $p['active_key'], 'seat source recorded' );
	ok(
		Enrollments::create(
			array(
				'user_id'    => $u[8],
				'course_id'  => $plain,
				'status'     => 'enrolled',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
			),
			array(
				'source'      => 'seat',
				'source_type' => 'seat',
				'source_ref'  => 42,
			)
		),
		'second row of the same seat'
	);
	ok( null === projection( (int) $wpdb->insert_id )['active_key'], 'second active seat row keeps out of the unique slot' );
	ok(
		array(
			'source'      => 'purchase',
			'source_type' => 'order',
			'source_ref'  => $paid,
		) === EnrolmentMirror::order_origin( $paid ),
		'paid order origin'
	);
	ok( 'self' === EnrolmentMirror::order_origin( $free )['source'], 'free order origin' );
	ok( 'subscription' === EnrolmentMirror::order_origin( $s_order, $plan, $sub_id )['source_type'], 'subscription origin' );
	ok( 'self' === EnrolmentMirror::order_origin( 0, $plan )['source'], 'free membership grant origin' );
	ok( 'manual' === EnrolmentMirror::origin( array( 'source' => 'bogus' ) )['source'], 'unknown sources are rejected' );

	// Course deletion and user deletion hooks remove both rows.
	do_action( 'ohmylms_rest_delete_course', $exam );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE course_id=%d', $table, $exam ) ), 'course deletion removes projections' );
	wp_delete_user( $u[7] );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id=%d', $table, $u[7] ) ), 'user deletion removes projections' );
	unset( $rows['import'], $rows['exam'] );
	$report = EnrolmentMigration::compare();
	ok( 0 === $report['orphans'] && 0 === $report['mismatched'], 'dual write left no orphans or mismatches: ' . wp_json_encode( $report ) );

	// Down: reads fall back at once, only migration rows go, legacy rows stay.
	$legacy_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$legacy}" );
	$service_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE migrated = 0" );
	ok( $service_rows >= 3, 'service rows exist before the down step' );
	$state = EnrolmentMigration::revert();
	ok( 'reverting' === $state['status'] && ! Enrollments::reads_new() && wp_next_scheduled( EnrolmentMigration::DOWN_HOOK ), 'revert stops new-table reads and schedules batches' );
	$state = EnrolmentMigration::revert_batch( 2 );
	ok( 'reverting' === $state['status'] && 2 === $state['removed'], 'down runs in batches' );
	$state = EnrolmentMigration::run_down( 3 );
	ok( 'reverted' === $state['status'], 'down completes' );
	ok( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE migrated = 1" ), 'no migration rows remain' );
	ok( $service_rows === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE migrated = 0" ), 'service rows kept' );
	ok( $legacy_count === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$legacy}" ), 'legacy storage untouched' );
	ok( null === projection( $rows['roster'] ) && in_array( $u[6], Enrollments::course_user_ids( $rostered ), true ), 'reads use legacy storage after the down step' );
	Enrollments::update( array( 'start_date' => current_time( 'mysql' ) ), array( 'id' => $rows['roster'] ) );
	ok( null === projection( $rows['roster'] ), 'a reverted migration is not refilled by updates' );

	// Up again recreates the same projections.
	ok( 'running' === EnrolmentMigration::start()['status'], 'restart after revert' );
	migrate_all();
	foreach ( $rows as $key => $id ) {
		$p = projection( $id );
		ok( $p && $expected[ $key ][0] === $p['source'] && $expected[ $key ][1] === $p['source_type'] && '1' === $p['migrated'], 'second up restores ' . $key );
	}

	// Watchdog: re-arms a lost event once the database update ran, leaves a reverted migration alone.
	update_option( 'ohmylms_db_version', EnrolmentMigration::DB_VERSION );
	EnrolmentMigration::mark_stale();
	wp_clear_scheduled_hook( EnrolmentMigration::UP_HOOK );
	EnrolmentMigration::watchdog();
	ok( wp_next_scheduled( EnrolmentMigration::UP_HOOK ), 'watchdog re-arms a stopped migration' );
	migrate_all();
	EnrolmentMigration::revert();
	EnrolmentMigration::run_down();
	wp_clear_scheduled_hook( EnrolmentMigration::UP_HOOK );
	EnrolmentMigration::watchdog();
	ok( ! wp_next_scheduled( EnrolmentMigration::UP_HOOK ) && 'reverted' === EnrolmentMigration::state()['status'], 'watchdog leaves a reverted migration alone' );
	delete_option( EnrolmentMigration::OPTION );
	EnrolmentMigration::watchdog();
	ok( 'running' === EnrolmentMigration::state()['status'], 'watchdog starts a migration that never started' );
	migrate_all();
	update_option( 'ohmylms_db_version', '1.0.0' );
	delete_option( EnrolmentMigration::OPTION );
	EnrolmentMigration::watchdog();
	ok( 'none' === EnrolmentMigration::state()['status'], 'watchdog waits for the database update' );
	update_option( 'ohmylms_db_version', $saved_db );

	// Schema down and up: legacy writes keep working without the table.
	ok( EnrolmentSchema::uninstall() && ! EnrolmentSchema::exists() && ! Enrollments::reads_new(), 'schema down' );
	ok(
		Enrollments::create(
			array(
				'user_id'    => $u[9],
				'course_id'  => $imported,
				'status'     => 'enrolled',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
			),
			array( 'source' => 'manual' )
		),
		'writes work without the table'
	);
	ok( 1 === (int) Enrollments::update( array( 'status' => 'banned' ), array( 'id' => (int) $wpdb->insert_id ) ), 'updates work without the table' );
	ok( EnrolmentSchema::install() && EnrolmentSchema::exists(), 'schema up again' );

	echo "OK enrolments-migration-integration: {$checks} checks\n";
} finally {
	remove_all_filters( 'ohmylms_enrolments_read_new' );
	if ( ! EnrolmentSchema::exists() ) {
		EnrolmentSchema::install();
	}
	if ( null !== $saved_db ) {
		update_option( 'ohmylms_db_version', $saved_db );
	}
	foreach ( $user_ids as $user ) {
		$wpdb->delete( $legacy, array( 'user_id' => $user ) );
		$wpdb->delete( $table, array( 'user_id' => $user ) );
		$wpdb->delete( $members, array( 'user_id' => $user ) );
		if ( get_userdata( $user ) ) {
			wp_delete_user( $user );
		}
	}
	foreach ( $class_ids as $class_id ) {
		foreach ( array( 'course_class_students', 'course_classes', 'class_memberships' ) as $name ) {
			$wpdb->delete( \OhMyLMS\Schools\Schema::table( $name ), array( 'class_id' => $class_id ) );
		}
		$wpdb->delete( \OhMyLMS\Schools\Schema::table( 'classes' ), array( 'id' => $class_id ) );
	}
	foreach ( $post_ids as $post ) {
		wp_delete_post( $post, true );
	}
	wp_clear_scheduled_hook( EnrolmentMigration::UP_HOOK );
	wp_clear_scheduled_hook( EnrolmentMigration::DOWN_HOOK );
	delete_transient( 'ohmylms_enrolments_lag' );
	delete_option( EnrolmentMigration::OPTION );
	if ( is_array( $saved_state ) && in_array( $saved_state['status'] ?? '', array( 'running', 'complete', 'stale' ), true ) ) {
		// The table was rebuilt above: bring it back to the state the site was in.
		EnrolmentMigration::start();
		EnrolmentMigration::run();
		wp_clear_scheduled_hook( EnrolmentMigration::UP_HOOK );
	} elseif ( null !== $saved_state ) {
		update_option( EnrolmentMigration::OPTION, $saved_state, true );
	}
}
