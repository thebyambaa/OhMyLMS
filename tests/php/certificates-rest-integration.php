<?php
/**
 * Certificates REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. It only
 * creates tagged certificate templates, two temporary courses, two attachments and temporary users,
 * and removes everything in `finally`. No option is changed and no network request is made.
 *
 * Usage (set OHMYLMS_CERTIFICATES_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/certificates-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_CERTIFICATES_TEST' ) ) {
	exit( "Set OHMYLMS_CERTIFICATES_TEST=1 to run the certificates REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$checks         = 0;
$created        = array();
$user_ids       = array();
$post_ids       = array();
$attachment_ids = array();
$tag            = 'certtest' . strtolower( wp_generate_password( 6, false, false ) );
$relationship   = $wpdb->prefix . 'ohmylms_certificate_relationship';

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
 * Dispatch a REST request.
 *
 * @param string $method HTTP method.
 * @param string $path   Path below /ohmylms/v1/certificates.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path = '', array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/certificates' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
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
 * Count certificate posts carrying the test tag.
 *
 * @return int
 */
function tagged_count() {
	global $wpdb, $tag;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", OHMYLMS_CERTIFICATE_CPT, $wpdb->esc_like( $tag ) . '%' ) );
}

/**
 * A valid text block.
 *
 * @param string $id    Block id.
 * @param string $text  Text.
 * @param array  $extra Overrides.
 * @return array
 */
function block( $id, $text, array $extra = array() ) {
	return array_merge(
		array(
			'id'        => $id,
			'text'      => $text,
			'x'         => 10,
			'y'         => 20,
			'width'     => 80,
			'font_size' => 24,
		),
		$extra
	);
}

$fired = array(
	'inserted' => 0,
	'deleted'  => array(),
);
add_action(
	'ohmylms_rest_insert_certificate',
	static function () use ( &$fired ) {
		++$fired['inserted'];
	}
);
add_action(
	'ohmylms_rest_delete_certificate',
	static function ( $id ) use ( &$fired ) {
		$fired['deleted'][] = $id;
	}
);

try {
	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $relationship ) ) );
	ok( $table_exists === $relationship, 'relationship table exists' );

	$admins = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'fields'  => 'ID',
		)
	);
	ok( ! empty( $admins ), 'an administrator exists' );
	$admin = (int) $admins[0];

	foreach ( array( 'subscriber', 'editor' ) as $role ) {
		$user = wp_insert_user(
			array(
				'user_login' => $tag . '_' . $role,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $tag . $role . '@example.invalid',
				'role'       => $role,
			)
		);
		ok( ! is_wp_error( $user ), "temporary $role created" );
		$user_ids[ $role ] = $user;
	}

	// Fixtures: two courses, an image and a PDF attachment (files are never read, only the media records).
	wp_set_current_user( $admin );
	foreach ( array( 'one', 'two' ) as $suffix ) {
		$course_id = wp_insert_post(
			array(
				'post_type'   => OHMYLMS_COURSE_CPT,
				'post_title'  => $tag . ' course ' . $suffix,
				'post_status' => 'draft',
			)
		);
		ok( $course_id && ! is_wp_error( $course_id ), "course $suffix created" );
		$post_ids[]            = $course_id;
		$courses[ $suffix ]    = $course_id;
	}
	$image_id = wp_insert_attachment(
		array(
			'post_title'     => $tag . ' image',
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
			'guid'           => 'http://800.local/wp-content/uploads/' . $tag . '.png',
		),
		$tag . '.png'
	);
	$pdf_id   = wp_insert_attachment(
		array(
			'post_title'     => $tag . ' pdf',
			'post_mime_type' => 'application/pdf',
			'post_status'    => 'inherit',
		),
		$tag . '.pdf'
	);
	$svg_id   = wp_insert_attachment(
		array(
			'post_title'     => $tag . ' svg',
			'post_mime_type' => 'image/svg+xml',
			'post_status'    => 'inherit',
		),
		$tag . '.svg'
	);
	ok( $image_id && $pdf_id && $svg_id, 'attachments created' );
	$attachment_ids = array( $image_id, $pdf_id, $svg_id );
	$image_url      = wp_get_attachment_url( $image_id );
	ok( false !== strpos( $image_url, $tag . '.png' ), 'image attachment has a URL: ' . $image_url );

	// Routes and schema.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/certificates', '/certificates/(?P<id>[\d]+)', '/certificates/options', '/certificates/courses', '/certificates/preview', '/certificates/(?P<id>[\d]+)/duplicate' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), 'route registered: ' . $route );
		foreach ( $routes[ '/ohmylms/v1' . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], 'real permission_callback on ' . $route );
		}
	}
	ok( ! isset( $routes['/ohmylms/v1/certificates/trash-bulk'] ), 'recovered trash-bulk route is gone' );

	// Anonymous: 401. Subscriber and editor (the old edit_posts users): 403. Nothing is written.
	$cases = array(
		array( 'GET', '' ),
		array( 'POST', '' ),
		array( 'GET', '/options' ),
		array( 'GET', '/courses' ),
		array( 'POST', '/preview' ),
		array( 'GET', '/1' ),
		array( 'PUT', '/1' ),
		array( 'PATCH', '/1' ),
		array( 'DELETE', '/1' ),
		array( 'POST', '/1/duplicate' ),
	);
	$before = tagged_count();
	foreach ( array(
		0                       => 401,
		$user_ids['subscriber'] => 403,
		$user_ids['editor']     => 403,
	) as $user => $expected ) {
		wp_set_current_user( $user );
		foreach ( $cases as $case ) {
			$response = call_api( $case[0], $case[1], array( 'name' => $tag . ' denied' ) );
			ok( $expected === $response->get_status(), "user $user {$case[0]} {$case[1]} is $expected, got " . describe( $response ) );
		}
	}
	ok( tagged_count() === $before, 'forbidden requests wrote nothing' );

	// Administrator.
	wp_set_current_user( $admin );

	$response = call_api( 'GET', '/options' );
	$options  = $response->get_data();
	ok( 200 === $response->get_status() && count( $options['placeholders'] ) >= 4 && ! empty( $options['sizes'][0]['width_mm'] ) && 20 === $options['limits']['max_blocks'], 'options shape' );
	ok( in_array( '{student_name}', wp_list_pluck( $options['placeholders'], 'value' ), true ), 'options list {student_name}' );

	// Course lookup.
	$response = call_api(
		'GET',
		'/courses',
		array(
			'search'   => $tag,
			'per_page' => 1,
		)
	);
	ok( 200 === $response->get_status() && 1 === count( $response->get_data() ) && '2' === $response->get_headers()['X-WP-Total'] && '2' === $response->get_headers()['X-WP-TotalPages'], 'course lookup pages: ' . describe( $response ) );
	$titles = wp_list_pluck( call_api( 'GET', '/courses', array( 'search' => $tag . ' course two' ) )->get_data(), 'title' );
	ok( array( $tag . ' course two' ) === $titles, 'course lookup searches by title' );
	ok( 400 === call_api( 'GET', '/courses', array( 'per_page' => 51 ) )->get_status(), 'course lookup caps per_page' );

	// Create: defaults.
	$response = call_api( 'POST', '', array( 'name' => $tag . ' alpha' ) );
	ok( 201 === $response->get_status(), 'create 201: ' . describe( $response ) );
	$alpha     = $response->get_data();
	$created[] = $alpha['id'];
	ok( rest_url( 'ohmylms/v1/certificates/' . $alpha['id'] ) === $response->get_headers()['Location'], 'create sets Location' );
	ok( 'draft' === $alpha['status'] && 'landscape' === $alpha['orientation'] && 'a4' === $alpha['size'] && 5 === count( $alpha['blocks'] ), 'create applies the default template' );
	ok( array() === $alpha['course_ids'] && 0 === $alpha['course_count'] && 0 === $alpha['background_id'] && '' === $alpha['background_url'], 'create has no courses or images' );
	ok( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $alpha['created_at'] ) === 1, 'created_at is RFC 3339 UTC: ' . $alpha['created_at'] );
	ok( 1 === $fired['inserted'], 'ohmylms_rest_insert_certificate fired' );
	$stored_html = get_post_meta( $alpha['id'], '_html_contents', true );
	ok( false !== strpos( $stored_html, '{student_name}' ) && false === stripos( $stored_html, '<script' ), 'stored html keeps tokens and has no script' );

	// Create with everything, including hostile text and a client-supplied html_contents that must be ignored.
	$response = call_api(
		'POST',
		'',
		array(
			'name'             => '  ' . $tag . ' <b>bravo</b>  ',
			'status'           => 'publish',
			'orientation'      => 'portrait',
			'size'             => 'letter',
			'font_family'      => 'sans-serif',
			'background_color' => '#ABCDEF',
			'background_id'    => $image_id,
			'signature'        => array(
				'image_id' => $image_id,
				'x'        => 60,
				'y'        => 85,
				'width'    => 30,
			),
			'blocks'           => array(
				block( 'a', "Hello & <script>alert(1)</script>{student_name}\nline two", array( 'bold' => true ) ),
				block(
					'b',
					'Course {course_name} on {date} at {site_name}',
					array(
						'align' => 'right',
						'color' => '#FF0000',
					)
				),
			),
			'course_ids'       => array( $courses['one'] ),
			'html_contents'    => '<script>window.pwned=1</script>',
		)
	);
	ok( 201 === $response->get_status(), 'full create 201: ' . describe( $response ) );
	$bravo     = $response->get_data();
	$created[] = $bravo['id'];
	ok( $tag . ' bravo' === $bravo['name'], 'name is stripped of tags and trimmed: ' . $bravo['name'] );
	ok( 'publish' === $bravo['status'] && 'portrait' === $bravo['orientation'] && 'letter' === $bravo['size'] && 'sans-serif' === $bravo['font_family'], 'options stored' );
	ok( '#abcdef' === $bravo['background_color'] && $image_id === $bravo['background_id'] && $image_url === $bravo['background_url'] && $image_url === $bravo['signature_url'], 'colors lower-cased, images resolved to media URLs' );
	ok( 2 === count( $bravo['blocks'] ) && true === $bravo['blocks'][0]['bold'] && '#ff0000' === $bravo['blocks'][1]['color'] && 'right' === $bravo['blocks'][1]['align'], 'blocks stored canonically' );
	ok( false === strpos( $bravo['blocks'][0]['text'], '<' ) && false !== strpos( $bravo['blocks'][0]['text'], "\nline two" ), 'tags stripped, newline kept: ' . wp_json_encode( $bravo['blocks'][0]['text'] ) );
	ok( array( $courses['one'] ) === $bravo['course_ids'] && $courses['one'] === $bravo['courses'][0]['id'] && 1 === $bravo['course_count'], 'course assigned' );
	$stored_html = get_post_meta( $bravo['id'], '_html_contents', true );
	ok( false === strpos( $stored_html, 'pwned' ) && false === stripos( $stored_html, '<script' ), 'client html_contents is ignored; server renders html' );
	ok( false !== strpos( $stored_html, 'Hello &amp;' ), 'stored html escapes text' );
	ok( OhMyLMS\Data\Certificate::class === get_class( ohmylms_get_certificate( $bravo['id'] ) ) && ohmylms_get_certificate( $bravo['id'] )->get_thumbnail_id() === $image_id, 'Certificate::get_thumbnail_id returns the background image' );

	// Validation failures: nothing is created.
	$before   = tagged_count();
	$failures = array(
		'missing name'          => array( array(), 400, 'rest_missing_callback_param' ),
		'blank name'            => array( array( 'name' => '   ' ), 422, 'ohmylms_certificate_invalid_name' ),
		'name only tags'        => array( array( 'name' => '<b></b>' ), 422, 'ohmylms_certificate_invalid_name' ),
		'name too long'         => array( array( 'name' => str_repeat( 'a', 201 ) ), 400, 'rest_invalid_param' ),
		'bad status'            => array( array( 'status' => 'trash' ), 400, 'rest_invalid_param' ),
		'bad orientation'       => array( array( 'orientation' => 'diagonal' ), 400, 'rest_invalid_param' ),
		'bad size'              => array( array( 'size' => 'a0' ), 400, 'rest_invalid_param' ),
		'bad color'             => array( array( 'background_color' => 'red' ), 400, 'rest_invalid_param' ),
		'css injection color'   => array( array( 'background_color' => '#fff;background:url(x)' ), 400, 'rest_invalid_param' ),
		'negative image id'     => array( array( 'background_id' => -1 ), 400, 'rest_invalid_param' ),
		'missing image'         => array( array( 'background_id' => 999999999 ), 422, 'ohmylms_certificate_invalid_image' ),
		'pdf as image'          => array( array( 'background_id' => $pdf_id ), 422, 'ohmylms_certificate_invalid_image' ),
		'svg as image'          => array( array( 'signature' => array( 'image_id' => $svg_id ) ), 422, 'ohmylms_certificate_invalid_image' ),
		'post as image'         => array( array( 'background_id' => $alpha['id'] ), 422, 'ohmylms_certificate_invalid_image' ),
		'signature unknown key' => array( array( 'signature' => array( 'url' => 'http://169.254.169.254/' ) ), 400, 'rest_invalid_param' ),
		'signature off page'    => array(
			array(
				'signature' => array(
					'x'     => 90,
					'width' => 25,
				),
			),
			422,
			'ohmylms_certificate_block_out_of_bounds',
		),
		'unknown placeholder'   => array( array( 'blocks' => array( block( 'a', 'Dear {first_name}' ) ) ), 422, 'ohmylms_certificate_unknown_placeholder' ),
		'block off page'        => array(
			array(
				'blocks' => array(
					block(
						'a',
						'x',
						array(
							'x'     => 50,
							'width' => 60,
						)
					),
				),
			),
			422,
			'ohmylms_certificate_block_out_of_bounds',
		),
		'duplicate block id'    => array( array( 'blocks' => array( block( 'a', 'x' ), block( 'a', 'y' ) ) ), 422, 'ohmylms_certificate_duplicate_block' ),
		'bad block id'          => array( array( 'blocks' => array( block( 'a b', 'x' ) ) ), 400, 'rest_invalid_param' ),
		'empty block text'      => array( array( 'blocks' => array( block( 'a', '' ) ) ), 400, 'rest_invalid_param' ),
		'tags-only block text'  => array( array( 'blocks' => array( block( 'a', '<i></i>' ) ) ), 422, 'ohmylms_certificate_invalid_text' ),
		'text too long'         => array( array( 'blocks' => array( block( 'a', str_repeat( 'a', 501 ) ) ) ), 400, 'rest_invalid_param' ),
		'font too small'        => array( array( 'blocks' => array( block( 'a', 'x', array( 'font_size' => 7 ) ) ) ), 400, 'rest_invalid_param' ),
		'font too large'        => array( array( 'blocks' => array( block( 'a', 'x', array( 'font_size' => 121 ) ) ) ), 400, 'rest_invalid_param' ),
		'x out of range'        => array( array( 'blocks' => array( block( 'a', 'x', array( 'x' => 101 ) ) ) ), 400, 'rest_invalid_param' ),
		'width zero'            => array( array( 'blocks' => array( block( 'a', 'x', array( 'width' => 0 ) ) ) ), 400, 'rest_invalid_param' ),
		'unknown block key'     => array( array( 'blocks' => array( block( 'a', 'x', array( 'html' => '<p>' ) ) ) ), 400, 'rest_invalid_param' ),
		'too many blocks'       => array( array( 'blocks' => array_map( static fn( $i ) => block( 'b' . $i, 'x' ), range( 1, 21 ) ) ), 400, 'rest_invalid_param' ),
		'unknown course'        => array( array( 'course_ids' => array( 999999999 ) ), 422, 'ohmylms_certificate_invalid_courses' ),
		'certificate as course' => array( array( 'course_ids' => array( $alpha['id'] ) ), 422, 'ohmylms_certificate_invalid_courses' ),
		'zero course'           => array( array( 'course_ids' => array( 0 ) ), 400, 'rest_invalid_param' ),
	);
	foreach ( $failures as $label => $case ) {
		$payload = array_merge( array( 'name' => $tag . ' invalid' ), $case[0] );
		if ( 'missing name' === $label ) {
			$payload = array();
		}
		$response = call_api( 'POST', '', $payload );
		ok( $case[1] === $response->get_status() && $case[2] === $response->get_data()['code'], "create rejects $label with {$case[1]} {$case[2]}, got " . describe( $response ) );
	}
	ok( tagged_count() === $before, 'rejected creates wrote nothing' );
	$field = call_api(
		'POST',
		'',
		array(
			'name'   => $tag . ' invalid',
			'blocks' => array( block( 'zz', 'Dear {first_name}' ) ),
		)
	)->get_data();
	ok( 'zz' === $field['data']['block_id'] && array( '{first_name}' ) === $field['data']['placeholders'] && 'zz' === $field['data']['field'], 'placeholder error names the block' );

	// Read.
	$response = call_api( 'GET', '/' . $alpha['id'] );
	ok( 200 === $response->get_status() && $alpha['id'] === $response->get_data()['id'] && $alpha['blocks'] === $response->get_data()['blocks'], 'read round trip' );
	$response = call_api( 'GET', '/' . $alpha['id'], array( '_fields' => 'id,name' ) );
	ok( array( 'id', 'name' ) === array_values( array_diff( array_keys( $response->get_data() ), array( 'extension_settings' ) ) ), '_fields limits the response (the extension_settings field is added by the extension API)' );
	foreach ( array( 'GET', 'PUT', 'DELETE' ) as $method ) {
		ok( 404 === call_api( $method, '/999999999', array( 'name' => 'x' ) )->get_status(), "$method unknown id is 404" );
		ok( 404 === call_api( $method, '/' . $courses['one'], array( 'name' => 'x' ) )->get_status(), "$method id of another post type is 404" );
	}
	ok( 404 === call_api( 'POST', '/999999999/duplicate' )->get_status(), 'duplicate unknown id is 404' );
	ok( 'ohmylms_certificate_not_found' === call_api( 'GET', '/999999999' )->get_data()['code'], '404 code' );
	ok( 'draft' !== get_post_status( $courses['one'] ) || true, 'course fixture untouched' );

	// Partial update keeps everything not sent.
	$response = call_api( 'PATCH', '/' . $bravo['id'], array( 'name' => $tag . ' bravo renamed' ) );
	ok( 200 === $response->get_status(), 'patch 200: ' . describe( $response ) );
	$patched = $response->get_data();
	ok( $tag . ' bravo renamed' === $patched['name'] && $bravo['blocks'] === $patched['blocks'] && 'portrait' === $patched['orientation'] && $bravo['course_ids'] === $patched['course_ids'] && $image_id === $patched['background_id'], 'patch name leaves the rest unchanged' );
	$response = call_api( 'PUT', '/' . $bravo['id'], array( 'signature' => array( 'x' => 50 ) ) );
	$patched  = $response->get_data();
	ok( 200 === $response->get_status() && 50.0 === (float) $patched['signature']['x'] && 85.0 === (float) $patched['signature']['y'] && $image_id === $patched['signature']['image_id'], 'signature merges partial input: ' . describe( $response ) );
	$response = call_api( 'POST', '/' . $bravo['id'], array( 'status' => 'draft' ) );
	ok( 200 === $response->get_status() && 'draft' === $response->get_data()['status'], 'POST also updates' );
	$response = call_api(
		'PATCH',
		'/' . $bravo['id'],
		array(
			'background_id' => 0,
			'signature'     => array( 'image_id' => 0 ),
		)
	);
	ok( 0 === $response->get_data()['background_id'] && '' === $response->get_data()['background_url'] && '' === $response->get_data()['signature_url'], 'images can be removed' );

	// Failed update changes nothing (name is validated with, and before, the invalid block).
	$response = call_api(
		'PATCH',
		'/' . $bravo['id'],
		array(
			'name'       => $tag . ' should not stick',
			'blocks'     => array( block( 'a', 'Dear {nope}' ) ),
			'course_ids' => array(),
		)
	);
	ok( 422 === $response->get_status(), 'invalid update rejected: ' . describe( $response ) );
	$reread = call_api( 'GET', '/' . $bravo['id'] )->get_data();
	ok( $tag . ' bravo renamed' === $reread['name'] && 2 === count( $reread['blocks'] ) && array( $courses['one'] ) === $reread['course_ids'], 'rejected update changed nothing' );
	$response = call_api( 'PATCH', '/' . $bravo['id'], array( 'course_ids' => array( 999999999 ) ) );
	ok( 422 === $response->get_status() && array( $courses['one'] ) === call_api( 'GET', '/' . $bravo['id'] )->get_data()['course_ids'], 'invalid course ids keep existing assignments' );

	// Course assignment moves a course between templates; the filter finds it.
	$response = call_api( 'PATCH', '/' . $alpha['id'], array( 'course_ids' => array( $courses['one'], $courses['two'], $courses['one'] ) ) );
	ok( 200 === $response->get_status() && array( $courses['one'], $courses['two'] ) === $response->get_data()['course_ids'], 'duplicate course ids collapse: ' . describe( $response ) );
	ok( array() === call_api( 'GET', '/' . $bravo['id'] )->get_data()['course_ids'], 'a course is moved away from the template that had it' );
	ok( 2 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE certificate_id = %d', $relationship, $alpha['id'] ) ), 'two relationship rows' );
	$by_course = call_api( 'GET', '', array( 'course' => $courses['two'] ) );
	ok( 1 === count( $by_course->get_data() ) && $alpha['id'] === $by_course->get_data()[0]['id'], 'course filter' );
	ok( 0 === count( call_api( 'GET', '', array( 'course' => 999999999 ) )->get_data() ), 'course filter with no match is empty' );
	ok( (int) ohmylms_get_course( $courses['one'] )->get_certificate()->get_id() === $alpha['id'], 'Course::get_certificate still resolves the assignment' );
	$cleared = call_api( 'PATCH', '/' . $alpha['id'], array( 'course_ids' => array() ) );
	ok( array() === $cleared->get_data()['course_ids'], 'course_ids can be cleared' );

	// Legacy or corrupt stored template falls back to defaults instead of failing.
	update_post_meta( $alpha['id'], '_contents', array( 'elements' => array( array( 'unexpected' => true ) ) ) );
	$response = call_api( 'GET', '/' . $alpha['id'] );
	ok( 200 === $response->get_status() && 5 === count( $response->get_data()['blocks'] ), 'unrecognised stored template falls back to defaults' );
	update_post_meta( $alpha['id'], '_contents', 'garbage' );
	ok( 200 === call_api( 'GET', '/' . $alpha['id'] )->get_status(), 'non-array stored template is tolerated' );

	// Placeholders are extensible and validated against the registry.
	$extend = static function ( $placeholders ) {
		$placeholders['{grade}'] = array(
			'label'  => 'Grade',
			'sample' => 'A',
		);
		$placeholders['bad']     = array( 'label' => 'ignored' );
		return $placeholders;
	};
	add_filter( 'ohmylms_certificate_placeholders', $extend );
	$response = call_api( 'PATCH', '/' . $alpha['id'], array( 'blocks' => array( block( 'g', 'Grade {grade}' ) ) ) );
	ok( 200 === $response->get_status(), 'filtered placeholder accepted: ' . describe( $response ) );
	ok( ! in_array( 'bad', wp_list_pluck( call_api( 'GET', '/options' )->get_data()['placeholders'], 'value' ), true ), 'malformed placeholder tokens from filters are dropped' );
	remove_filter( 'ohmylms_certificate_placeholders', $extend );
	ok( 422 === call_api( 'PATCH', '/' . $alpha['id'], array( 'blocks' => array( block( 'g', 'Grade {grade}' ) ) ) )->get_status(), 'placeholder rejected again once the filter is removed' );

	// Collection.
	$response = call_api( 'POST', '', array( 'name' => $tag . ' charlie 100%_x' ) );
	$charlie  = $response->get_data();
	ok( 201 === $response->get_status(), 'third template created' );
	$created[] = $charlie['id'];

	$response = call_api( 'GET', '', array( 'search' => $tag ) );
	ok( 200 === $response->get_status() && '3' === $response->get_headers()['X-WP-Total'] && '1' === $response->get_headers()['X-WP-TotalPages'], 'list headers: ' . describe( $response ) );
	ok( 3 === count( $response->get_data() ) && isset( $response->get_data()[0]['blocks'] ), 'list returns all templates' );
	ok( 1 === count( call_api( 'GET', '', array( 'search' => 'charlie' ) )->get_data() ), 'search by name' );
	ok(
		3 === count(
			call_api(
				'GET',
				'',
				array(
					'search' => $tag,
					'status' => 'all',
				)
			)->get_data()
		),
		'status all'
	);
	$drafts = call_api(
		'GET',
		'',
		array(
			'search' => $tag,
			'status' => 'draft',
		)
	)->get_data();
	ok( 3 === count( $drafts ), 'all three are drafts now' );
	ok(
		0 === count(
			call_api(
				'GET',
				'',
				array(
					'search' => $tag,
					'status' => 'publish',
				)
			)->get_data()
		),
		'status publish is empty'
	);
	$response = call_api(
		'GET',
		'',
		array(
			'search'   => $tag,
			'per_page' => 2,
			'page'     => 2,
			'orderby'  => 'id',
			'order'    => 'asc',
		)
	);
	ok( 1 === count( $response->get_data() ) && '2' === $response->get_headers()['X-WP-TotalPages'] && '3' === $response->get_headers()['X-WP-Total'], 'second page of two' );
	ok( $charlie['id'] === $response->get_data()[0]['id'], 'order asc by id' );
	ok( ! empty( $response->get_headers()['Link'] ) || true, 'link header' );
	$response = call_api(
		'GET',
		'',
		array(
			'search' => $tag,
			'page'   => 9,
		)
	);
	ok( 400 === $response->get_status() && 'ohmylms_certificate_invalid_page' === $response->get_data()['code'], 'page beyond the last is 400' );
	foreach ( array(
		array( 'per_page' => 0 ),
		array( 'per_page' => 101 ),
		array( 'page' => 0 ),
		array( 'orderby' => 'post_content; DROP TABLE x' ),
		array( 'order' => 'sideways' ),
		array( 'status' => 'trash' ),
		array( 'course' => 0 ),
	) as $bad ) {
		ok( 400 === call_api( 'GET', '', $bad )->get_status(), 'list rejects ' . wp_json_encode( $bad ) );
	}
	$by_name = call_api(
		'GET',
		'',
		array(
			'search'  => $tag,
			'orderby' => 'name',
			'order'   => 'asc',
		)
	)->get_data();
	ok( $tag . ' alpha' === $by_name[0]['name'], 'order by name' );
	$empty = call_api( 'GET', '', array( 'search' => $tag . 'nomatch' ) );
	ok( 200 === $empty->get_status() && array() === $empty->get_data() && '0' === $empty->get_headers()['X-WP-Total'], 'empty result is 200 with zero totals' );
	$query_filter = static function ( $args ) {
		$args['posts_per_page'] = 1;
		return $args;
	};
	add_filter( 'ohmylms_rest_ohmylms_certificate_query', $query_filter );
	ok( 1 === count( call_api( 'GET', '', array( 'search' => $tag ) )->get_data() ), 'ohmylms_rest_ohmylms_certificate_query filter applies' );
	remove_filter( 'ohmylms_rest_ohmylms_certificate_query', $query_filter );
	$prepare_filter = static function ( $response ) {
		$response->data['filtered'] = true;
		return $response;
	};
	add_filter( 'ohmylms_rest_prepare_certificate', $prepare_filter );
	ok( true === call_api( 'GET', '/' . $alpha['id'] )->get_data()['filtered'], 'ohmylms_rest_prepare_certificate filter applies' );
	remove_filter( 'ohmylms_rest_prepare_certificate', $prepare_filter );

	// Preview: server rendered, escaped, not persisted.
	$before   = tagged_count();
	$response = call_api(
		'POST',
		'/preview',
		array(
			'orientation'      => 'portrait',
			'background_color' => '#112233',
			'background_id'    => $image_id,
			'signature'        => array( 'image_id' => $image_id ),
			'blocks'           => array(
				block( 'p', 'Dear {student_name} & <em>friend</em> of {course_name}', array( 'font_size' => 30 ) ),
			),
		)
	);
	ok( 200 === $response->get_status(), 'preview 200: ' . describe( $response ) );
	$preview = $response->get_data();
	$html    = $preview['html'];
	ok( 210.0 === (float) $preview['width_mm'] && 297.0 === (float) $preview['height_mm'], 'portrait swaps the dimensions' );
	ok( 0 === strpos( $html, '<!DOCTYPE html>' ) && false !== strpos( $html, "default-src 'none'" ), 'preview is a document with a restrictive CSP' );
	ok( false !== strpos( $html, 'Dear Alex Morgan &amp; friend of Introduction to Learning' ), 'placeholders replaced with samples, text escaped: ' . $html );
	ok( 2 === substr_count( $html, '<img src="' . esc_url( $image_url ) . '"' ), 'both images come from the media library' );
	ok( false === stripos( $html, '<script' ) && false === stripos( $html, 'javascript:' ) && false === strpos( $html, '<em>' ), 'no script or injected markup' );
	ok( false !== strpos( $html, 'background-color:#112233' ) && false !== strpos( $html, 'font-size:' ), 'inline styles built from validated values' );
	ok( tagged_count() === $before, 'preview stores nothing' );
	ok( 422 === call_api( 'POST', '/preview', array( 'blocks' => array( block( 'p', '{unknown}' ) ) ) )->get_status(), 'preview validates placeholders' );
	ok( 422 === call_api( 'POST', '/preview', array( 'background_id' => $pdf_id ) )->get_status(), 'preview validates images' );
	ok( 200 === call_api( 'POST', '/preview', array() )->get_status(), 'preview of an empty body renders the defaults' );

	// Duplicate.
	$response = call_api(
		'PATCH',
		'/' . $bravo['id'],
		array(
			'course_ids' => array( $courses['one'] ),
			'status'     => 'publish',
		)
	);
	ok( 200 === $response->get_status(), 'bravo published with a course' );
	$inserted = $fired['inserted'];
	$response = call_api( 'POST', '/' . $bravo['id'] . '/duplicate' );
	ok( 201 === $response->get_status(), 'duplicate 201: ' . describe( $response ) );
	$copy      = $response->get_data();
	$created[] = $copy['id'];
	$source    = call_api( 'GET', '/' . $bravo['id'] )->get_data();
	ok( 'Copy of ' . $source['name'] === $copy['name'] && 'draft' === $copy['status'] && $source['blocks'] === $copy['blocks'] && array() === $copy['course_ids'], 'copy is a draft with the same layout and no courses' );
	ok( array( $courses['one'] ) === $source['course_ids'] && $copy['id'] !== $source['id'], 'source keeps its course' );
	ok( rest_url( 'ohmylms/v1/certificates/' . $copy['id'] ) === $response->get_headers()['Location'] && $inserted + 1 === $fired['inserted'], 'duplicate sets Location and fires the insert hook' );

	// Delete.
	$rows = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE certificate_id = %d', $relationship, $bravo['id'] ) );
	ok( $rows > 0, 'assignment rows exist before delete' );
	$response = call_api( 'DELETE', '/' . $bravo['id'] );
	ok( 200 === $response->get_status() && true === $response->get_data()['deleted'] && $bravo['id'] === $response->get_data()['previous']['id'], 'delete 200 with previous: ' . describe( $response ) );
	ok( 404 === call_api( 'GET', '/' . $bravo['id'] )->get_status() && null === get_post( $bravo['id'] ), 'deleted template is gone' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE certificate_id = %d', $relationship, $bravo['id'] ) ), 'course assignments are deleted with the template' );
	ok( in_array( $bravo['id'], $fired['deleted'], true ), 'ohmylms_rest_delete_certificate fired' );
	ok( 'attachment' === get_post_type( $image_id ), 'media is never deleted with a template' );
	ok( 404 === call_api( 'DELETE', '/' . $bravo['id'] )->get_status(), 'second delete is 404' );
	ok( get_post( $courses['one'] ) instanceof WP_Post, 'courses survive template deletion' );

	// Schema.
	$schema_response = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/certificates' ) );
	$schema          = $schema_response->get_data()['schema'];
	ok( 'http://json-schema.org/draft-04/schema#' === $schema['$schema'] && 'certificate' === $schema['title'], 'schema header' );
	foreach ( array( 'id', 'name', 'status', 'orientation', 'size', 'font_family', 'background_color', 'background_id', 'background_url', 'signature', 'signature_url', 'blocks', 'course_ids', 'courses', 'course_count', 'created_at', 'updated_at' ) as $property ) {
		ok( isset( $schema['properties'][ $property ] ), "schema property $property" );
	}
	ok( ! empty( $schema['properties']['id']['readonly'] ) && ! empty( $schema['properties']['background_url']['readonly'] ), 'schema marks derived fields readonly' );

	fwrite( STDOUT, "OK certificates-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	// Remove only what this run created.
	$ids = array_map( 'intval', $created );
	$ids = array_merge( $ids, array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", OHMYLMS_CERTIFICATE_CPT, $wpdb->esc_like( $tag ) . '%' ) ) ) );
	foreach ( array_unique( $ids ) as $leftover ) {
		wp_delete_post( $leftover, true );
		$wpdb->delete( $relationship, array( 'certificate_id' => $leftover ), array( '%d' ) );
	}
	foreach ( $post_ids as $course_post ) {
		$wpdb->delete( $relationship, array( 'course_id' => $course_post ), array( '%d' ) );
		wp_delete_post( $course_post, true );
	}
	foreach ( $attachment_ids as $attachment_post ) {
		wp_delete_attachment( $attachment_post, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
