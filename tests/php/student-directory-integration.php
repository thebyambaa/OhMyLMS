<?php
// Run against a local WordPress site; all fixture rows are rolled back.
if ( PHP_SAPI !== 'cli' || empty( $argv[1] ) ) {
	exit( "Usage: php student-directory-integration.php /path/to/wordpress\n" ); }
require rtrim( $argv[1], '/\\' ) . '/wp-load.php';

function directory_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message ); }
}

$tables = array( $wpdb->users, $wpdb->usermeta, $wpdb->prefix . 'ohmylms_user_enrollment' );
foreach ( $tables as $table ) {
	$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
	directory_check( $engine === 'InnoDB', 'Fixtures require transactional tables.' );
}
$suffix     = 'directory-fixture-' . wp_generate_password( 12, false );
$controller = new \OhMyLMS\Rest\V1\StudentController();
$list       = function ( $params = array() ) use ( $controller, $suffix ) {
	$request = new WP_REST_Request( 'GET' );
	$request->set_query_params(
		array_merge(
			array(
				'search'   => $suffix,
				'per_page' => 100,
			),
			$params
		)
	);
	$response = $controller->get_items( $request );
	directory_check( $GLOBALS['wpdb']->last_error === '', 'Directory SQL failed.' );
	return $response;
};
$ids        = array();
$wpdb->query( 'START TRANSACTION' );
try {
	foreach ( array(
		'student' => ohmylms_get_student_role(),
		'legacy'  => 'subscriber',
		'teacher' => 'ohmylms_teacher',
		'parent'  => 'ohmylms_parent',
	) as $kind => $role ) {
		directory_check(
			$wpdb->insert(
				$wpdb->users,
				array(
					'user_login'      => "$suffix-$kind",
					'display_name'    => "$suffix-$kind",
					'user_email'      => "$suffix-$kind@example.invalid",
					'user_registered' => current_time( 'mysql' ),
				)
			) !== false,
			'User fixture failed.'
		);
		$ids[ $kind ] = (int) $wpdb->insert_id;
		directory_check(
			$wpdb->insert(
				$wpdb->usermeta,
				array(
					'user_id'    => $ids[ $kind ],
					'meta_key'   => $wpdb->get_blog_prefix() . 'capabilities',
					'meta_value' => serialize( array( $role => true ) ),
				)
			) !== false,
			'Role fixture failed.'
		);
	}
	$response = $list();
	$rows     = $response->get_data();
	directory_check( count( $rows ) === 2 && (int) $response->get_headers()['X-WP-Total'] === 2, 'Unenrolled student accounts or total missing; unrelated roles must be excluded.' );
	foreach ( $rows as $row ) {
		directory_check( in_array( (int) $row['user_id'], array( $ids['student'], $ids['legacy'] ), true ), 'Non-student leaked into directory.' );
		directory_check( $row['courses_enrolled'] === 0 && ! empty( $row['registration_date'] ), 'Unenrolled students need zero courses and an account registration date.' );
	}
	directory_check( count( $list( array( 'date_filter' => 'current_month' ) )->get_data() ) === 2, 'Date filter hides newly registered accounts.' );
	directory_check( count( $list( array( 'course_id' => 2147483647 ) )->get_data() ) === 0, 'Course filter includes unenrolled students.' );
	directory_check( count( $list( array( 'per_page' => 1 ) )->get_data() ) === 1 && (int) $list( array( 'per_page' => 1 ) )->get_headers()['X-WP-Total'] === 2, 'Pagination total is inconsistent.' );

	directory_check(
		$wpdb->insert(
			$tables[2],
			array(
				'user_id'    => $ids['teacher'],
				'course_id'  => 2147483647,
				'status'     => 'banned',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
			)
		) !== false,
		'Enrollment fixture failed.'
	);
	$course_rows = $list( array( 'course_id' => 2147483647 ) )->get_data();
	directory_check( count( $course_rows ) === 1 && (int) $course_rows[0]['user_id'] === $ids['teacher'], 'Existing enrolled users must remain listed regardless of role.' );
	directory_check( (int) $list()->get_headers()['X-WP-Total'] === 3, 'Enrollment and role filters disagree with total.' );
	directory_check(
		$wpdb->insert(
			$wpdb->usermeta,
			array(
				'user_id'    => $ids['parent'],
				'meta_key'   => '_ohmylms_managed_school',
				'meta_value' => '1',
			)
		) !== false,
		'Managed school fixture failed.'
	);
	$school_rows = $list()->get_data();
	directory_check( count( $school_rows ) === 4 && (int) $list()->get_headers()['X-WP-Total'] === 4, 'School-created students must appear in both the list and total without a student role or enrollment.' );
	directory_check( in_array( $ids['parent'], array_map( 'intval', array_column( $school_rows, 'user_id' ) ), true ), 'School-created student is missing.' );
	directory_check( count( $list( array( 'course_id' => 2147483647 ) )->get_data() ) === 1, 'Course filter includes a school-created student without an enrollment.' );
	echo "Student directory integration passed: roles, enrollment, dates, totals and pagination.\n";
} finally {
	$wpdb->query( 'ROLLBACK' );
	foreach ( $ids as $id ) {
		clean_user_cache( $id ); }
}
