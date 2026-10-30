<?php
/**
 * Course settings save/reopen, stale writes and permissions against the Local test database.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_SETTINGS_TEST' ) ) {
	exit( "Set OHMYLMS_SETTINGS_TEST=1 to run course settings integration tests.\n" );
}
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$settings_checks = 0;
$settings_users  = array();
$settings_posts  = array();

/**
 * Assert a real database/REST invariant.
 *
 * @param bool   $condition Expected truth.
 * @param string $message Failure explanation.
 * @throws RuntimeException On failure.
 */
function settings_check( $condition, $message ) {
	global $settings_checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI assertion.
	}
	++$settings_checks;
}

/**
 * Dispatch through actual registered REST routes.
 *
 * @param string $method Method.
 * @param int    $course Course ID.
 * @param array  $data JSON.
 * @return WP_REST_Response
 */
function settings_request( $method, $course, $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/authoring/courses/' . $course . '/settings' );
	$request->set_header( 'Content-Type', 'application/json' );
	if ( 'GET' !== $method ) {
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Remove response-only fields from the full save payload.
 *
 * @param array $data Settings.
 * @return array
 */
function settings_payload( $data ) {
	unset( $data['course_id'] );
	return $data;
}

/**
 * Dispatch the explicit preview/publication contract.
 *
 * @param string $method Method.
 * @param int    $course Course ID.
 * @param array  $data JSON.
 * @return WP_REST_Response
 */
function settings_publication( $method, $course, $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/authoring/courses/' . $course . '/publication' );
	$request->set_header( 'Content-Type', 'application/json' );
	if ( 'GET' !== $method ) {
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

try {
	foreach ( array( 'administrator', 'author', 'subscriber' ) as $settings_role ) {
		$settings_user = wp_insert_user(
			array(
				'user_login' => 'settings' . wp_generate_password( 12, false, false ),
				'user_pass'  => wp_generate_password( 32 ),
				'role'       => $settings_role,
			)
		);
		settings_check( ! is_wp_error( $settings_user ), 'Fixture user created.' );
		$settings_users[ $settings_role ] = $settings_user;
	}
	wp_set_current_user( $settings_users['administrator'] );
	$settings_course  = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => 'draft',
			'post_title'  => 'Settings integration fixture',
			'post_author' => get_current_user_id(),
		),
		true
	);
	$settings_posts[] = $settings_course;
	$settings_image   = wp_insert_post(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
			'post_title'     => 'Settings fixture image',
			'post_author'    => get_current_user_id(),
		),
		true
	);
	$settings_posts[] = $settings_image;
	update_post_meta( $settings_image, '_wp_attached_file', 'course-settings-nonexistent-fixture.png' );
	update_post_meta( $settings_course, '_requirement', 'Preserved legacy requirements' );
	update_post_meta( $settings_course, '_sale_price_dates_from', time() - 3600 );
	update_post_meta( $settings_course, '_sale_price_dates_to', time() + 3600 );
	$settings_read = settings_request( 'GET', $settings_course );
	settings_check( 200 === $settings_read->get_status(), 'Administrator can read settings.' );
	$settings_data = $settings_read->get_data();
	settings_check( 'free' === $settings_data['price_type'] && 'public' === $settings_data['access_type'] && '0' === $settings_data['regular_price'], 'New courses have usable defaults.' );
	$settings_body                  = settings_payload( $settings_data );
	$settings_body['title']         = 'Algebra \\alpha';
	$settings_body['description']   = '<p>Use \\frac{1}{2}</p><script>alert(1)</script>';
	$settings_body['image_id']      = $settings_image;
	$settings_body['price_type']    = 'paid';
	$settings_body['regular_price'] = '25.50';
	$settings_body['sale_price']    = '20';
	$settings_body['has_capacity']  = true;
	$settings_body['capacity']      = 30;
	$settings_body['access_type']   = 'password_protected';
	$settings_body['password']      = 'test-only-\\secret';
	$settings_saved                 = settings_request( 'PUT', $settings_course, $settings_body );
	settings_check( 200 === $settings_saved->get_status(), 'Complete settings save succeeds: ' . wp_json_encode( $settings_saved->get_data() ) );
	$settings_reopen = settings_request( 'GET', $settings_course )->get_data();
	settings_check( $settings_body['title'] === $settings_reopen['title'] && false !== strpos( $settings_reopen['description'], '\\frac' ), 'Math backslashes survive save and reopen.' );
	settings_check( false === strpos( $settings_reopen['description'], '<script' ), 'Description HTML is sanitized.' );
	settings_check( $settings_image === $settings_reopen['image_id'] && 30 === $settings_reopen['capacity'] && $settings_reopen['has_capacity'], 'Image and enrollment settings persist.' );
	settings_check( 'test-only-\\secret' === get_post_field( 'post_password', $settings_course ) && ! isset( $settings_reopen['password'] ), 'Password persists without disclosure.' );
	settings_check( 20.0 === (float) get_post_meta( $settings_course, '_price', true ), 'Effective price honors the existing sale schedule.' );
	settings_check( 'Preserved legacy requirements' === get_post_meta( $settings_course, '_requirement', true ), 'Unrelated metadata survives the data store save.' );
	settings_check( 'draft' === get_post_status( $settings_course ) && ! get_post_meta( $settings_course, \OhMyLMS\Learning\CourseProgram::CURRENT, true ), 'Settings save never publishes the course or learning program.' );
	settings_check( 409 === settings_request( 'PUT', $settings_course, $settings_body )->get_status(), 'Stale settings rejected.' );
	$settings_body = settings_payload( $settings_reopen );
	foreach ( array(
		array( 'title' => '' ),
		array( 'regular_price' => '-1' ),
		array( 'sale_price' => '999' ),
		array( 'status' => 'publish' ),
		array( 'image_id' => $settings_course ),
		array( 'author_id' => $settings_users['subscriber'] ),
		array( 'capacity' => 0 ),
		array( 'has_capacity' => 'yes' ),
		array( 'extra' => true ),
		array( 'password' => array() ),
		array( 'password' => null ),
	) as $settings_invalid ) {
		settings_check( 400 === settings_request( 'PUT', $settings_course, array_merge( $settings_body, $settings_invalid ) )->get_status(), 'Invalid input rejected: ' . wp_json_encode( array_keys( $settings_invalid ) ) );
		settings_check( settings_request( 'GET', $settings_course )->get_data() === $settings_reopen, 'Invalid writes leave saved settings unchanged.' );
	}
	$settings_body['title'] = 'Second save';
	settings_check( 200 === settings_request( 'PUT', $settings_course, $settings_body )->get_status(), 'Fresh token permits another save.' );
	settings_check( 'test-only-\\secret' === get_post_field( 'post_password', $settings_course ), 'Empty password preserves the existing secret.' );
	$settings_body              = settings_payload( settings_request( 'GET', $settings_course )->get_data() );
	$settings_body['author_id'] = $settings_users['author'];
	$settings_body['image_id']  = 0;
	$settings_body['status']    = 'private';
	settings_check( 200 === settings_request( 'PUT', $settings_course, $settings_body )->get_status(), 'Valid author reassignment and private status save.' );
	$settings_reassigned = settings_request( 'GET', $settings_course )->get_data();
	settings_check( $settings_users['author'] === $settings_reassigned['author_id'] && 0 === $settings_reassigned['image_id'] && 'private' === $settings_reassigned['status'], 'Author, image removal and status persist.' );
	$settings_preview = settings_publication( 'GET', $settings_course );
	settings_check( 200 === $settings_preview->get_status() && ! empty( $settings_preview->get_data()['readiness'] ), 'Preview reports missing completion requirements.' );
	$settings_publish = array(
		'revision'  => $settings_preview->get_data()['revision'],
		'confirmed' => true,
	);
	settings_check( 409 === settings_publication( 'POST', $settings_course, $settings_publish )->get_status(), 'Unready program cannot publish.' );
	settings_check( 'private' === get_post_status( $settings_course ), 'Readiness rejection keeps course private.' );
	$settings_lesson           = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_status' => 'draft',
			'post_title'  => 'Settings test lesson',
			'post_author' => get_current_user_id(),
		),
		true
	);
	$settings_posts[]          = $settings_lesson;
	$settings_program          = \OhMyLMS\Learning\CourseProgram::editor( $settings_course )['draft'];
	$settings_program['items'] = array(
		array(
			'id'         => wp_generate_uuid4(),
			'type'       => 'lesson',
			'content_id' => $settings_lesson,
			'chapter_id' => 0,
			'required'   => true,
		),
	);
	settings_check( ! is_wp_error( \OhMyLMS\Learning\CourseProgram::save( $settings_course, $settings_program ) ), 'Fixture program saved.' );
	$settings_preview = settings_publication( 'GET', $settings_course )->get_data();
	settings_check( ! empty( $settings_preview['readiness'] ), 'Unpublished resource blocks readiness.' );
	settings_check(
		409 === settings_publication(
			'POST',
			$settings_course,
			array(
				'revision'  => $settings_preview['revision'],
				'confirmed' => true,
			)
		)->get_status(),
		'Publication cannot implicitly publish shared resources.'
	);
	wp_update_post(
		array(
			'ID'          => $settings_lesson,
			'post_status' => 'publish',
		)
	);
	$settings_preview = settings_publication( 'GET', $settings_course )->get_data();
	settings_check( empty( $settings_preview['readiness'] ) && 'Settings test lesson' === $settings_preview['units'][0]['items'][0]['title'], 'Preview shows the saved lesson and ready state.' );
	settings_check( 400 === settings_publication( 'POST', $settings_course, array( 'revision' => $settings_preview['revision'] ) )->get_status(), 'Explicit confirmation required.' );
	wp_update_post(
		array(
			'ID'         => $settings_course,
			'post_title' => 'Changed after review',
		)
	);
	settings_check(
		409 === settings_publication(
			'POST',
			$settings_course,
			array(
				'revision'  => $settings_preview['revision'],
				'confirmed' => true,
			)
		)->get_status(),
		'Metadata change invalidates reviewed publication.'
	);
	$settings_preview      = settings_publication( 'GET', $settings_course )->get_data();
	$settings_fail_publish = static function ( $is_empty, $post_data ) use ( $settings_course ) {
		return (int) ( $post_data['ID'] ?? 0 ) === $settings_course ? true : $is_empty;
	};
	add_filter( 'wp_insert_post_empty_content', $settings_fail_publish, 10, 2 );
	try {
		settings_check(
			settings_publication(
				'POST',
				$settings_course,
				array(
					'revision'  => $settings_preview['revision'],
					'confirmed' => true,
				)
			)->get_status() >= 400,
			'Failed course status write is reported.'
		);
	} finally {
		remove_filter( 'wp_insert_post_empty_content', $settings_fail_publish, 10 );
	}
	settings_check( 'private' === get_post_status( $settings_course ) && ! get_post_meta( $settings_course, \OhMyLMS\Learning\CourseProgram::CURRENT, true ), 'Failed publication rolls back both course and program pointer.' );
	$settings_published = settings_publication(
		'POST',
		$settings_course,
		array(
			'revision'  => $settings_preview['revision'],
			'confirmed' => true,
		)
	);
	settings_check( 200 === $settings_published->get_status() && $settings_published->get_data()['published_program_id'] > 0 && 'publish' === get_post_status( $settings_course ), 'Reviewed program and course publish together.' );
	settings_check(
		409 === settings_publication(
			'POST',
			$settings_course,
			array(
				'revision'  => $settings_preview['revision'],
				'confirmed' => true,
			)
		)->get_status(),
		'Replaying an old confirmation cannot create another program version.'
	);
	foreach ( array( 'author', 'subscriber' ) as $settings_role ) {
		wp_set_current_user( $settings_users[ $settings_role ] );
		settings_check( 403 === settings_request( 'GET', $settings_course )->get_status(), 'Unprivileged settings read rejected.' );
		settings_check( 403 === settings_request( 'PUT', $settings_course, $settings_body )->get_status(), 'Unprivileged settings write rejected.' );
		settings_check( 403 === settings_publication( 'GET', $settings_course )->get_status(), 'Unprivileged preview rejected.' );
		settings_check( 403 === settings_publication( 'POST', $settings_course, $settings_publish )->get_status(), 'Unprivileged publication rejected.' );
	}
	echo "Course settings REST integration: $settings_checks checks passed.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI count.
} finally {
	wp_set_current_user( $settings_users['administrator'] ?? 0 );
	foreach ( array_reverse( $settings_posts ) as $settings_post ) {
		wp_delete_post( $settings_post, true );
	}
	// Remove immutable versions belonging only to the deleted fixture course.
	if ( ! empty( $settings_course ) ) {
		$settings_versions = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE course_id=%d', \OhMyLMS\Learning\Schema::table( 'programs' ), $settings_course ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read only this fixture course's program IDs for cleanup.
		foreach ( $settings_versions as $settings_version ) {
			$wpdb->delete( \OhMyLMS\Learning\Schema::table( 'outcomes' ), array( 'program_id' => $settings_version ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Delete only the fixture program's outcome rows.
		}
		$wpdb->delete( \OhMyLMS\Learning\Schema::table( 'programs' ), array( 'course_id' => $settings_course ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Delete only this fixture course's program versions.
	}
	foreach ( $settings_users as $settings_user ) {
		wp_delete_user( $settings_user );
	}
}
