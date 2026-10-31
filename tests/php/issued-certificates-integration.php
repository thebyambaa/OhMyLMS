<?php
/**
 * Issued certificates integration test: automatic issuing on completion, manual issue, revoke, re-issue,
 * the admin list, public verification and the learner download (PDF and printable page).
 *
 * Runs against the Local site with real tables. It creates tagged templates, courses, enrolments,
 * users and issued certificates, and removes all of them in `finally`. Completion is simulated by
 * writing the enrolment row and calling the `ohmylms_course_completed` listener directly, so no
 * completion e-mail, webhook or xAPI statement is sent.
 *
 * Usage (set OHMYLMS_CERTIFICATES_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/issued-certificates-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI test script writing to STDOUT/STDERR.

use OhMyLMS\Certificates\Download;
use OhMyLMS\Certificates\IssuedSchema;
use OhMyLMS\Certificates\Issuer;
use OhMyLMS\Certificates\PdfRenderer;
use OhMyLMS\Certificates\TemplateRenderer;
use OhMyLMS\Student\Enrollments;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_CERTIFICATES_TEST' ) ) {
	exit( "Set OHMYLMS_CERTIFICATES_TEST=1 to run the issued certificates tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
$test_ip                = '192.0.2.' . random_int( 1, 254 );
$_SERVER['REMOTE_ADDR'] = $test_ip;
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$checks       = 0;
$tag          = 'issuedtest' . strtolower( wp_generate_password( 6, false, false ) );
$user_ids     = array();
$post_ids     = array();
$enrolments   = array();
$relationship = $wpdb->prefix . 'ohmylms_certificate_relationship';

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
 * Dispatch a REST request below /ohmylms/v1.
 *
 * @param string $method HTTP method.
 * @param string $path   Path.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( $data ? wp_json_encode( $data ) : '' );
	}
	return rest_do_request( $request );
}

/**
 * Describe a response for failure messages.
 *
 * @param WP_REST_Response $response Response.
 * @return string
 */
function describe( $response ) {
	return $response->get_status() . ' ' . wp_json_encode( $response->get_data() );
}

/**
 * Error code of a response.
 *
 * @param WP_REST_Response $response Response.
 * @return string
 */
function code_of( $response ) {
	$data = $response->get_data();
	return is_array( $data ) ? (string) ( $data['code'] ?? '' ) : '';
}

/**
 * Mark a learner's course completed (enrolment row only).
 *
 * @param int $user_id   Learner.
 * @param int $course_id Course.
 */
function complete_course( $user_id, $course_id ) {
	global $enrolments;
	if ( ! Enrollments::find( $user_id, $course_id ) ) {
		Enrollments::create(
			array(
				'user_id'    => $user_id,
				'course_id'  => $course_id,
				'status'     => 'enrolled',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
			),
			array( 'source' => 'test' ),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
		$enrolments[] = array( $user_id, $course_id );
	}
	Enrollments::update(
		array(
			'progress' => 'completed',
			'end_date' => '2026-10-20 09:30:00',
		),
		array(
			'user_id'   => $user_id,
			'course_id' => $course_id,
		)
	);
}

try {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	ok( ! empty( $admins ), 'an administrator exists' );
	$admin = (int) $admins[0];
	foreach ( array( 'learner', 'other', 'editor' ) as $name ) {
		$user = wp_insert_user(
			array(
				'user_login' => $tag . '_' . $name,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $tag . $name . '@example.invalid',
				'first_name' => 'Ada' . ucfirst( $name ),
				'last_name'  => 'Lovelace',
				'role'       => 'editor' === $name ? 'editor' : 'subscriber',
			)
		);
		ok( ! is_wp_error( $user ), "temporary $name created" );
		$user_ids[ $name ] = (int) $user;
	}
	$learner = $user_ids['learner'];
	$other   = $user_ids['other'];

	ok( IssuedSchema::install() && IssuedSchema::ready(), 'issued certificates table installed' );
	ok( 5 === has_action( 'ohmylms_course_completed', array( Issuer::class, 'on_course_completed' ) ), 'issuing listens to ohmylms_course_completed before the e-mail' );
	ok( 1 === has_action( 'template_redirect', array( Download::class, 'route' ) ), 'download and verification addresses are routed' );
	$routes = rest_get_server()->get_routes();
	foreach ( array( '/ohmylms/v1/issued-certificates', '/ohmylms/v1/issued-certificates/(?P<id>[\d]+)', '/ohmylms/v1/issued-certificates/(?P<id>[\d]+)/revoke', '/ohmylms/v1/issued-certificates/(?P<id>[\d]+)/reissue', '/ohmylms/v1/issued-certificates/learners', '/ohmylms/v1/issued-certificates/verify/(?P<code>[A-Za-z0-9-]{1,40})' ) as $route ) {
		ok( isset( $routes[ $route ] ), "route $route registered" );
	}

	// Fixtures: a published template on course A, a draft template on course B, no template on course C.
	wp_set_current_user( $admin );
	$created = call_api( 'POST', '/certificates', array( 'name' => $tag . ' published' ) );
	ok( 201 === $created->get_status(), 'template created ' . describe( $created ) );
	$published  = (int) $created->get_data()['id'];
	$post_ids[] = $published;
	$draft      = (int) call_api( 'POST', '/certificates', array( 'name' => $tag . ' draft' ) )->get_data()['id'];
	$post_ids[] = $draft;
	$courses    = array();
	foreach ( array( 'a', 'b', 'c' ) as $suffix ) {
		$courses[ $suffix ] = (int) wp_insert_post(
			array(
				'post_type'   => OHMYLMS_COURSE_CPT,
				'post_title'  => $tag . ' course ' . $suffix,
				'post_status' => 'publish',
			)
		);
		ok( $courses[ $suffix ] > 0, "course $suffix created" );
		$post_ids[] = $courses[ $suffix ];
	}
	$updated = call_api(
		'PATCH',
		'/certificates/' . $published,
		array(
			'status'     => 'publish',
			'course_ids' => array( $courses['a'] ),
			'blocks'     => array(
				array(
					'id'        => 'name',
					'text'      => '{student_name} completed {course_name} on {date}',
					'x'         => 10,
					'y'         => 30,
					'width'     => 80,
					'font_size' => 28,
				),
				array(
					'id'        => 'code',
					'text'      => 'Code {certificate_code}',
					'x'         => 10,
					'y'         => 80,
					'width'     => 80,
					'font_size' => 12,
				),
			),
		)
	);
	ok( 200 === $updated->get_status(), 'template published on course A with the code placeholder ' . describe( $updated ) );
	ok( 200 === call_api( 'PATCH', '/certificates/' . $draft, array( 'course_ids' => array( $courses['b'] ) ) )->get_status(), 'draft template on course B' );

	// Permissions.
	wp_set_current_user( 0 );
	foreach ( array( array( 'GET', '/issued-certificates' ), array( 'POST', '/issued-certificates' ), array( 'GET', '/issued-certificates/learners' ), array( 'POST', '/issued-certificates/1/revoke' ), array( 'POST', '/issued-certificates/1/reissue' ), array( 'GET', '/issued-certificates/1' ) ) as $denied ) {
		$body = 'POST' === $denied[0] && '/issued-certificates' === $denied[1] ? array(
			'user_id'   => $learner,
			'course_id' => $courses['a'],
		) : array();
		ok( 401 === call_api( $denied[0], $denied[1], $body )->get_status(), "anonymous {$denied[0]} {$denied[1]} is 401" );
	}
	foreach ( array( 'learner', 'editor' ) as $name ) {
		wp_set_current_user( $user_ids[ $name ] );
		ok( 403 === call_api( 'GET', '/issued-certificates' )->get_status(), "$name cannot list issued certificates" );
		ok(
			403 === call_api(
				'POST',
				'/issued-certificates',
				array(
					'user_id'   => $learner,
					'course_id' => $courses['a'],
				)
			)->get_status(),
			"$name cannot issue"
		);
	}

	// Automatic issuing.
	wp_set_current_user( 0 );
	Issuer::on_course_completed( $learner, $courses['a'] );
	ok( ! Issuer::active_for( $learner, $courses['a'] ), 'no certificate before the course is completed' );
	complete_course( $learner, $courses['a'] );
	Issuer::on_course_completed( $learner, $courses['a'] );
	$auto = Issuer::active_for( $learner, $courses['a'] );
	ok( $auto && 'completion' === $auto['source'] && 'issued' === $auto['status'], 'certificate issued on completion' );
	ok( 1 === preg_match( '/^[A-HJKMNP-Z2-9]{4}(-[A-HJKMNP-Z2-9]{4}){3}$/', $auto['code'] ), 'code is four groups of the unambiguous alphabet' );
	ok( 'AdaLearner Lovelace' === $auto['student_name'] && $tag . ' course a' === $auto['course_title'], 'name and course are kept on the certificate' );
	ok( $published === $auto['certificate_id'] && 2 === count( $auto['template']['blocks'] ), 'template is the course template, kept as a snapshot' );
	ok( 0 === $auto['issued_by'] && get_gmt_from_date( '2026-10-20 09:30:00' ) === $auto['completed_at'], 'issued by the system with the completion date' );
	Issuer::on_course_completed( $learner, $courses['a'] );
	ok( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d', IssuedSchema::table(), $learner, $courses['a'] ) ), 'completing again issues nothing new' );
	Issuer::on_course_completed( $learner, $courses['a'] + 100000 );
	Issuer::on_course_completed( $learner + 100000, $courses['a'] );
	ok( true, 'unknown learner or course is ignored without errors' );

	complete_course( $learner, $courses['b'] );
	Issuer::on_course_completed( $learner, $courses['b'] );
	ok( ! Issuer::active_for( $learner, $courses['b'] ), 'a draft template is not issued automatically' );
	complete_course( $learner, $courses['c'] );
	Issuer::on_course_completed( $learner, $courses['c'] );
	ok( ! Issuer::active_for( $learner, $courses['c'] ), 'a course without a template issues nothing' );

	// Placeholder values and rendering.
	$values = Issuer::values( $auto );
	ok( 'AdaLearner Lovelace' === $values['{student_name}'] && $auto['code'] === $values['{certificate_code}'] && '' !== $values['{date}'], 'placeholder values of the certificate' );
	$fragment = TemplateRenderer::fragment( Issuer::template( $auto ), $values );
	ok( false !== strpos( $fragment, 'AdaLearner Lovelace completed ' . esc_html( $tag ) . ' course a on' ) && false !== strpos( $fragment, $auto['code'] ), 'printable page shows the learner, course and code' );
	ok( false === strpos( $fragment, '{student_name}' ), 'no placeholder is left in the printable page' );
	if ( PdfRenderer::available() ) {
		$pdf = PdfRenderer::render( Issuer::template( $auto ), $values );
		ok( is_string( $pdf ) && 0 === strpos( $pdf, '%PDF-' ) && strlen( $pdf ) > 1000, 'PDF rendered by Dompdf' );
		ok( 1 === preg_match_all( '#/Type\s*/Page[^s]#', $pdf ), 'PDF has exactly one page' );
		$html = PdfRenderer::html( Issuer::template( $auto ), array( '{student_name}' => '<script>alert(1)</script>' ) + $values );
		ok( false === strpos( $html, '<script>' ) && false !== strpos( $html, '&lt;script&gt;' ), 'PDF markup escapes text' );
	}

	// Template and course changes do not alter an issued certificate.
	wp_set_current_user( $admin );
	wp_update_post(
		array(
			'ID'         => $courses['a'],
			'post_title' => $tag . ' course a renamed',
		)
	);
	ok( 200 === call_api( 'PATCH', '/certificates/' . $published, array( 'orientation' => 'portrait' ) )->get_status(), 'template edited after issue' );
	$again = Issuer::get( $auto['id'] );
	ok( $tag . ' course a' === $again['course_title'] && 'landscape' === $again['template']['orientation'], 'issued certificate keeps its course title and layout' );

	// Download access.
	wp_set_current_user( $learner );
	ok( Download::can_download( $auto ), 'learner may download their certificate' );
	wp_set_current_user( $other );
	ok( ! Download::can_download( $auto ), 'another learner may not download it' );
	wp_set_current_user( 0 );
	ok( ! Download::can_download( $auto ), 'anonymous visitors may not download it' );
	wp_set_current_user( $admin );
	ok( Download::can_download( $auto ), 'administrators may download it' );
	ok( false !== strpos( ( new OhMyLMS\Data\Student( $learner ) )->get_course_certificate_url( $courses['a'] ), rawurlencode( $auto['code'] ) ), 'student data store links the certificate download' );
	ok( '' === ( new OhMyLMS\Data\Student( $learner ) )->get_course_certificate_url( $courses['c'] ), 'no link without a certificate' );

	// Public verification.
	wp_set_current_user( 0 );
	$verified = call_api( 'GET', '/issued-certificates/verify/' . strtolower( str_replace( '-', '', $auto['code'] ) ) );
	ok( 200 === $verified->get_status(), 'verification is public and ignores case and dashes ' . describe( $verified ) );
	$view = $verified->get_data();
	ok( true === $view['valid'] && 'issued' === $view['status'] && 'AdaLearner Lovelace' === $view['student_name'] && $tag . ' course a' === $view['course_title'] && '' !== $view['completed_on'], 'verification shows name, course and dates' );
	ok( array( 'code', 'status', 'student_name', 'course_title', 'completed_on', 'issued_on', 'revoked_on', 'valid', 'page_url' ) === array_keys( $view ), 'verification reveals nothing else (no ids, e-mail or template)' );
	ok( 404 === call_api( 'GET', '/issued-certificates/verify/AAAA-BBBB-CCCC-DDDD' )->get_status(), 'unknown code is 404' );
	ok( 404 === call_api( 'GET', '/issued-certificates/verify/not-a-code' )->get_status(), 'malformed code is 404' );

	// Admin list.
	wp_set_current_user( $admin );
	$list = call_api( 'GET', '/issued-certificates', array( 'search' => $auto['code'] ) );
	ok( 200 === $list->get_status() && 1 === count( $list->get_data() ) && '1' === $list->get_headers()['X-WP-Total'], 'search by code ' . describe( $list ) );
	$row = $list->get_data()[0];
	ok( $auto['id'] === $row['id'] && $learner === $row['learner']['id'] && $tag . 'learner@example.invalid' === $row['learner']['email'] && $tag . ' published' === $row['template']['name'], 'row carries learner, course and template' );
	ok( false !== strpos( $row['links']['pdf'], 'format=pdf' ) && false !== strpos( $row['links']['print'], 'format=print' ) && false !== strpos( $row['links']['verify'], Download::QUERY_VERIFY ), 'row links download, print and verification' );
	ok( 1 === count( call_api( 'GET', '/issued-certificates', array( 'search' => 'AdaLearner' ) )->get_data() ), 'search by learner name' );
	ok( 1 === count( call_api( 'GET', '/issued-certificates', array( 'search' => $tag . 'learner@' ) )->get_data() ), 'search by e-mail' );
	ok( 1 === count( call_api( 'GET', '/issued-certificates', array( 'course' => $courses['a'] ) )->get_data() ), 'course filter' );
	ok( 0 === count( call_api( 'GET', '/issued-certificates', array( 'course' => $courses['b'] ) )->get_data() ), 'course filter excludes other courses' );
	ok(
		1 === count(
			call_api(
				'GET',
				'/issued-certificates',
				array(
					'certificate' => $published,
					'status'      => 'issued',
				)
			)->get_data()
		),
		'template and status filters'
	);
	ok(
		0 === count(
			call_api(
				'GET',
				'/issued-certificates',
				array(
					'certificate' => $published,
					'status'      => 'revoked',
				)
			)->get_data()
		),
		'revoked filter'
	);
	ok( 400 === call_api( 'GET', '/issued-certificates', array( 'orderby' => 'user_pass' ) )->get_status(), 'unknown orderby is 400' );
	ok( 400 === call_api( 'GET', '/issued-certificates', array( 'per_page' => 101 ) )->get_status(), 'per_page above 100 is 400' );
	ok(
		'ohmylms_issued_certificate_invalid_page' === code_of(
			call_api(
				'GET',
				'/issued-certificates',
				array(
					'course' => $courses['a'],
					'page'   => 5,
				)
			)
		),
		'page past the end is 400'
	);
	$learners = call_api( 'GET', '/issued-certificates/learners', array( 'search' => $tag . 'learner' ) );
	ok( 200 === $learners->get_status() && 1 === count( $learners->get_data() ) && 'AdaLearner Lovelace' === $learners->get_data()[0]['name'], 'learner search for the issue form' );

	// Manual issue.
	$issue = array(
		'user_id'   => $other,
		'course_id' => $courses['a'],
	);
	ok( 'ohmylms_issued_certificate_unknown_field' === code_of( call_api( 'POST', '/issued-certificates', $issue + array( 'status' => 'issued' ) ) ), 'unknown field is 400' );
	ok( 'ohmylms_certificate_invalid_user' === code_of( call_api( 'POST', '/issued-certificates', array( 'user_id' => $learner + 100000 ) + $issue ) ), 'unknown learner is 422' );
	ok( 'ohmylms_certificate_invalid_course' === code_of( call_api( 'POST', '/issued-certificates', array( 'course_id' => $published ) + $issue ) ), 'a non-course is 422' );
	ok( 'ohmylms_certificate_no_template' === code_of( call_api( 'POST', '/issued-certificates', array( 'course_id' => $courses['c'] ) + $issue ) ), 'a course without a template needs one chosen' );
	ok( 'ohmylms_certificate_invalid_template' === code_of( call_api( 'POST', '/issued-certificates', array( 'certificate_id' => $courses['b'] ) + $issue ) ), 'a non-template is 422' );
	$manual = call_api( 'POST', '/issued-certificates', $issue );
	ok( 201 === $manual->get_status() && 'manual' === $manual->get_data()['source'] && $admin === $manual->get_data()['issued_by'], 'manual issue without completion ' . describe( $manual ) );
	ok( false !== strpos( (string) $manual->get_headers()['Location'], '/issued-certificates/' . $manual->get_data()['id'] ), 'manual issue answers Location' );
	ok( null === $manual->get_data()['completed_at'], 'manual issue without completion has no completion date' );
	$duplicate = call_api( 'POST', '/issued-certificates', $issue );
	ok( 409 === $duplicate->get_status() && 'ohmylms_certificate_already_issued' === code_of( $duplicate ) && $manual->get_data()['id'] === $duplicate->get_data()['data']['id'], 'a second valid certificate is refused with the existing id' );
	$chosen = call_api(
		'POST',
		'/issued-certificates',
		array(
			'user_id'        => $learner,
			'course_id'      => $courses['c'],
			'certificate_id' => $draft,
		)
	);
	ok( 201 === $chosen->get_status() && $draft === $chosen->get_data()['template']['id'], 'manual issue with a chosen (draft) template' );

	// Revoke.
	ok( 'ohmylms_issued_certificate_unknown_field' === code_of( call_api( 'POST', '/issued-certificates/' . $auto['id'] . '/revoke', array( 'reason' => 'x' ) ) ), 'revoke takes no fields' );
	$revoked = call_api( 'POST', '/issued-certificates/' . $auto['id'] . '/revoke' );
	ok( 200 === $revoked->get_status() && 'revoked' === $revoked->get_data()['status'] && null !== $revoked->get_data()['revoked_at'] && $admin === $revoked->get_data()['revoked_by'], 'revoked ' . describe( $revoked ) );
	ok( '' === $revoked->get_data()['links']['pdf'], 'a revoked certificate has no download link' );
	ok( 'ohmylms_certificate_already_revoked' === code_of( call_api( 'POST', '/issued-certificates/' . $auto['id'] . '/revoke' ) ), 'revoking twice is 409' );
	ok( 404 === call_api( 'POST', '/issued-certificates/999999999/revoke' )->get_status(), 'revoking an unknown id is 404' );
	wp_set_current_user( 0 );
	$view = call_api( 'GET', '/issued-certificates/verify/' . $auto['code'] )->get_data();
	ok( false === $view['valid'] && 'revoked' === $view['status'] && '' !== $view['revoked_on'], 'verification reports the revocation' );
	Issuer::on_course_completed( $learner, $courses['a'] );
	ok( ! Issuer::active_for( $learner, $courses['a'] ), 'completing again does not undo a revocation' );
	ok( 0 === Issuer::sync_for_user( $learner ), 'the dashboard backfill does not undo it either' );

	// Re-issue.
	wp_set_current_user( $admin );
	$reissued = call_api( 'POST', '/issued-certificates/' . $auto['id'] . '/reissue' );
	ok( 201 === $reissued->get_status(), 're-issued ' . describe( $reissued ) );
	$new = $reissued->get_data();
	ok( 'reissue' === $new['source'] && $auto['id'] === $new['replaces'] && $auto['code'] !== $new['code'] && 'issued' === $new['status'], 're-issue is a new certificate replacing the old one' );
	ok( $tag . ' course a renamed' === $new['course']['title'], 're-issue uses the current course title' );
	ok( 'portrait' === Issuer::get( $new['id'] )['template']['orientation'], 're-issue uses the current template' );
	ok( get_gmt_from_date( '2026-10-20 09:30:00' ) === Issuer::get( $new['id'] )['completed_at'], 're-issue keeps the completion date' );
	$manual_id = $manual->get_data()['id'];
	$replaced  = call_api( 'POST', '/issued-certificates/' . $manual_id . '/reissue' );
	ok( 201 === $replaced->get_status() && 'revoked' === Issuer::get( $manual_id )['status'], 're-issuing a valid certificate revokes it' );
	ok( 'ohmylms_certificate_already_issued' === code_of( call_api( 'POST', '/issued-certificates/' . $manual_id . '/reissue' ) ), 'an older certificate cannot be re-issued while a newer one is valid' );

	// Backfill from the dashboard.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE user_id = %d', IssuedSchema::table(), $other ) );
	complete_course( $other, $courses['a'] );
	ok( 1 === Issuer::sync_for_user( $other ) && Issuer::active_for( $other, $courses['a'] ), 'dashboard backfill issues for an earlier completion' );
	ok( 0 === Issuer::sync_for_user( $other ), 'backfill is idempotent' );
	ok( 2 === count( Issuer::for_user( $learner ) ), 'learner lists their valid certificates only' );

	// A deleted template does not break issued certificates.
	ok( 200 === call_api( 'DELETE', '/certificates/' . $draft )->get_status(), 'template deleted' );
	$kept = Issuer::get( $chosen->get_data()['id'] );
	ok( $kept && is_array( $kept['template'] ) && '' !== TemplateRenderer::fragment( Issuer::template( $kept ), Issuer::values( $kept ) ), 'certificate from a deleted template still renders' );
	ok( '' === call_api( 'GET', '/issued-certificates/' . $kept['id'] )->get_data()['template']['name'], 'deleted template has no name in the list' );

	// Live addresses through HTTP (the site must be reachable from this machine).
	$probe = wp_remote_get( Download::verify_url( $new['code'] ), array( 'timeout' => 20 ) );
	if ( ! is_wp_error( $probe ) ) {
		$body = wp_remote_retrieve_body( $probe );
		ok( 200 === wp_remote_retrieve_response_code( $probe ) && false !== strpos( $body, 'AdaLearner Lovelace' ) && false === strpos( $body, $tag . 'learner@' ), 'verification page over HTTP shows the name and no e-mail' );
		ok( false !== strpos( (string) wp_remote_retrieve_header( $probe, 'content-security-policy' ), "default-src 'none'" ) && 'noindex, nofollow' === wp_remote_retrieve_header( $probe, 'x-robots-tag' ), 'verification page sends CSP and noindex' );
		$anon = wp_remote_get(
			Download::url( $new['code'] ),
			array(
				'timeout'     => 20,
				'redirection' => 0,
			)
		);
		ok( 302 === wp_remote_retrieve_response_code( $anon ) && false !== strpos( (string) wp_remote_retrieve_header( $anon, 'location' ), 'wp-login.php' ), 'anonymous download redirects to sign in' );
		$legacy = wp_remote_get(
			add_query_arg( Download::QUERY_LEGACY, 'abc', home_url( '/' ) ),
			array(
				'timeout'     => 20,
				'redirection' => 0,
			)
		);
		ok( 302 === wp_remote_retrieve_response_code( $legacy ), 'old e-mail links redirect instead of decrypting' );
	} else {
		fwrite( STDOUT, 'SKIP HTTP checks: ' . $probe->get_error_message() . "\n" );
	}
	ok( ! has_action( 'wp_ajax_ohmylms_download_certificate' ) && ! has_action( 'wp_ajax_nopriv_ohmylms_download_certificate_from_email' ), 'legacy certificate AJAX handlers are gone' );

	fwrite( STDOUT, "OK issued-certificates-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	if ( IssuedSchema::ready() && $user_ids ) {
		foreach ( $user_ids as $user_id ) {
			$wpdb->delete( IssuedSchema::table(), array( 'user_id' => $user_id ), array( '%d' ) );
		}
	}
	foreach ( $enrolments as $pair ) {
		Enrollments::delete(
			array(
				'user_id'   => $pair[0],
				'course_id' => $pair[1],
			),
			array( '%d', '%d' )
		);
	}
	foreach ( $post_ids as $post_id ) {
		$wpdb->delete( $relationship, array( 'course_id' => $post_id ), array( '%d' ) );
		$wpdb->delete( $relationship, array( 'certificate_id' => $post_id ), array( '%d' ) );
		wp_delete_post( $post_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	delete_transient( 'ohmylms_cert_verify_' . md5( $test_ip ) );
}

exit( isset( $exit_code ) ? $exit_code : 0 );
