<?php
/**
 * Emails REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default). It changes email options only
 * temporarily: every `create_lms_email_*` and global email option is snapshotted first and restored
 * (or deleted when it did not exist) in `finally`. Mail is never sent: `pre_wp_mail` captures it.
 * A temporary subscriber is created and removed.
 *
 * Usage (set OHMYLMS_EMAILS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/emails-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_EMAILS_TEST' ) ) {
	exit( "Set OHMYLMS_EMAILS_TEST=1 to run the emails REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Emails\CreatorsEmail\NewOrder;
use OhMyLMS\Emails\EmailManager;
use OhMyLMS\Emails\Emails;
use OhMyLMS\Emails\SampleRecord;

$checks   = 0;
$user_ids = array();
$tag      = 'emtest' . strtolower( wp_generate_password( 6, false, false ) );
$missing  = new stdClass();
$mails    = array();
$mail_ok  = true;
$warnings = array();
$post_id  = 987654321;
// Sample values go through translation (the site's terminology filter may rename "Chapter").
$s_student = __( 'Alex Learner', 'ohmylms' );
$s_course  = __( 'Introduction to Algebra', 'ohmylms' );
$s_quiz    = __( 'Chapter 1 Quiz', 'ohmylms' );

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
 * @param string $path   Path below /ohmylms/v1/emails.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path = '', array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/emails' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Error code of a response, or an empty string.
 *
 * @param WP_REST_Response $response Response.
 * @return string
 */
function code_of( $response ) {
	$data = $response->get_data();
	return is_array( $data ) && isset( $data['code'] ) ? $data['code'] : '';
}

// Snapshot every option this test may touch.
$option_names = array_keys( Emails::GLOBAL_DEFAULTS );
foreach ( EmailManager::ids() as $email_id ) {
	$option_names[] = Emails::OPTION_PREFIX . $email_id;
}
$snapshot = array();
foreach ( $option_names as $name ) {
	$snapshot[ $name ] = get_option( $name, $missing );
}

add_filter(
	'pre_wp_mail',
	static function ( $pre, $atts ) use ( &$mails, &$mail_ok ) {
		$mails[] = $atts;
		return $mail_ok;
	},
	10,
	2
);

$fired = array();
add_action(
	'ohmylms_email_updated',
	static function ( $email_id, $item ) use ( &$fired ) {
		$fired[] = array( $email_id, $item['subject'] );
	},
	10,
	2
);

try {
	$admins = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'fields'  => 'ID',
		)
	);
	ok( ! empty( $admins ), 'an administrator exists' );
	$admin      = (int) $admins[0];
	$admin_mail = get_userdata( $admin )->user_email;
	ok( is_email( $admin_mail ), 'the administrator has an email address' );

	$subscriber = wp_insert_user(
		array(
			'user_login' => $tag . '_sub',
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . '@example.invalid',
			'role'       => 'subscriber',
		)
	);
	ok( ! is_wp_error( $subscriber ), 'temporary subscriber created' );
	$user_ids[] = $subscriber;

	// Start from a known state: no stored settings, so the class defaults are in effect.
	foreach ( $option_names as $name ) {
		delete_option( $name );
	}

	// Routes and schema.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/emails', '/emails/settings', '/emails/(?P<id>[a-z][a-z0-9_]*)', '/emails/(?P<id>[a-z][a-z0-9_]*)/preview', '/emails/(?P<id>[a-z][a-z0-9_]*)/test' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), 'route registered: ' . $route );
		foreach ( $routes[ '/ohmylms/v1' . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], 'permission_callback is a real check on ' . $route );
		}
	}
	ok( ! isset( $routes['/ohmylms/v1/all-emails'] ) && ! isset( $routes['/ohmylms/v1/email-settings/(?P<email_id>[\w-]+)'] ), 'recovered routes are gone' );

	// Anonymous: 401. Subscriber: 403. Nothing is written or sent.
	$cases = array(
		array( 'GET', '' ),
		array( 'GET', '/settings' ),
		array( 'PUT', '/settings' ),
		array( 'GET', '/student_quiz_graded' ),
		array( 'PUT', '/student_quiz_graded' ),
		array( 'POST', '/student_quiz_graded/preview' ),
		array( 'POST', '/student_quiz_graded/test' ),
	);
	foreach ( array(
		array( 0, 401 ),
		array( $subscriber, 403 ),
	) as $actor ) {
		wp_set_current_user( $actor[0] );
		foreach ( $cases as $case ) {
			$response = call_api( $case[0], $case[1], array( 'subject' => 'Hacked' ) );
			ok( $actor[1] === $response->get_status(), "user {$actor[0]} {$case[0]} {$case[1]} is {$actor[1]}, got " . $response->get_status() );
		}
	}
	ok( false === get_option( Emails::OPTION_PREFIX . 'student_quiz_graded', false ), 'forbidden update wrote nothing' );
	ok( array() === $mails, 'forbidden test sent nothing' );

	wp_set_current_user( $admin );

	// Collection.
	$response = call_api( 'GET', '' );
	ok( 200 === $response->get_status(), 'list 200' );
	$list = $response->get_data();
	ok( 15 === count( $list ) && '15' === $response->get_headers()['X-WP-Total'] && '1' === $response->get_headers()['X-WP-TotalPages'], 'list has 15 emails and pagination headers' );
	foreach ( $list as $item ) {
		foreach ( array( 'id', 'title', 'description', 'recipient_type', 'enabled', 'subject', 'heading', 'additional_content', 'button_text', 'button_link', 'footer_text', 'course_suggestion_text', 'recipient_email', 'tags' ) as $key ) {
			ok( array_key_exists( $key, $item ), "list item {$item['id']} has $key" );
		}
		ok( is_bool( $item['enabled'] ) && is_array( $item['recipient_email'] ) && ! empty( $item['subject'] ) && ! empty( $item['tags'] ), "list item {$item['id']} types" );
		ok( 'site_name' === $item['tags'][ count( $item['tags'] ) - 3 ]['tag'], "list item {$item['id']} lists the common tags" );
	}
	$ids = wp_list_pluck( $list, 'id' );
	ok( EmailManager::ids() === $ids && 15 === count( array_unique( $ids ) ), 'list order is the catalog order' );
	ok( false === array_search( 'student_email_verification', $ids, true ), 'the fixed verification email is not configurable' );
	$released = array_column( $list, null, 'id' )['student_exam_results_released'] ?? null;
	ok( $released && 'student' === $released['recipient_type'] && true === $released['enabled'] && false !== strpos( $released['subject'], '{exam_name}' ), 'the exam results release email is listed, on by default' );
	ok( array( 'student_name', 'exam_name', 'site_name', 'store_name', 'admin_email' ) === wp_list_pluck( $released['tags'], 'tag' ), 'release email tags: ' . wp_json_encode( wp_list_pluck( $released['tags'], 'tag' ) ) );
	ok( \OhMyLMS\Exams\ResultsEmail::defaults() === ( new \OhMyLMS\Emails\StudentsEmail\ExamResultsReleased() )->default_settings(), 'the sender reads the catalogue entry' );

	$by_id = array_column( $list, null, 'id' );
	ok( false === $by_id['student_confirm_enrollment']['enabled'] && true === $by_id['creator_new_order']['enabled'], 'enabled mirrors the class defaults' );
	ok( 'student' === $by_id['student_quiz_graded']['recipient_type'] && 'creator' === $by_id['creator_new_order']['recipient_type'], 'recipient types' );
	ok( null === $by_id['student_quiz_graded']['course_suggestion_text'] && is_string( $by_id['student_course_completed']['course_suggestion_text'] ), 'course_suggestion_text only where supported' );

	$only = wp_list_pluck( call_api( 'GET', '', array( 'status' => 'disabled' ) )->get_data(), 'id' );
	ok( in_array( 'student_confirm_enrollment', $only, true ) && ! in_array( 'creator_new_order', $only, true ), 'status=disabled filter' );
	$only = wp_list_pluck( call_api( 'GET', '', array( 'recipient_type' => 'creator' ) )->get_data(), 'id' );
	ok( 5 === count( $only ) && in_array( 'instructor_quiz_submitted', $only, true ) && in_array( 'instructor_assignment_comment', $only, true ), 'recipient_type=creator filter' );
	$found = call_api( 'GET', '', array( 'search' => 'QUIZ' ) );
	ok( 2 <= count( $found->get_data() ) && '2' === $found->get_headers()['X-WP-Total'], 'search is case-insensitive: ' . $found->get_headers()['X-WP-Total'] );
	ok( array() === call_api( 'GET', '', array( 'search' => $tag ) )->get_data(), 'search without a match is empty' );

	$page = call_api(
		'GET',
		'',
		array(
			'per_page' => 5,
			'page'     => 3,
		)
	);
	ok( 200 === $page->get_status() && 5 === count( $page->get_data() ) && '3' === $page->get_headers()['X-WP-TotalPages'], 'last page holds the remainder' );
	$page = call_api(
		'GET',
		'',
		array(
			'per_page' => 5,
			'page'     => 4,
		)
	);
	ok( 400 === $page->get_status() && 'ohmylms_email_invalid_page' === code_of( $page ), 'page beyond the last is 400' );
	ok( 400 === call_api( 'GET', '', array( 'per_page' => 0 ) )->get_status(), 'per_page=0 rejected' );
	ok( 400 === call_api( 'GET', '', array( 'per_page' => 101 ) )->get_status(), 'per_page=101 rejected' );
	ok( 400 === call_api( 'GET', '', array( 'status' => 'bogus' ) )->get_status(), 'bad status filter rejected' );

	// `_fields` is applied by core in rest_post_dispatch (serve_request), which rest_do_request() skips.
	$fields_request = new WP_REST_Request( 'GET', '/ohmylms/v1/emails' );
	$fields_request->set_query_params( array( '_fields' => 'id,enabled' ) );
	$filtered = rest_filter_response_fields( new WP_REST_Response( $list[0] ), rest_get_server(), $fields_request );
	ok( array( 'id', 'enabled' ) === array_keys( $filtered->get_data() ), '_fields limits the response' );

	// Single email, settings route precedence, unknown id.
	$response = call_api( 'GET', '/student_quiz_graded' );
	ok( 200 === $response->get_status() && 'student_quiz_graded' === $response->get_data()['id'], 'read 200' );
	$links = $response->get_links();
	ok( isset( $links['self'], $links['collection'] ), 'read has self and collection links' );
	$response = call_api( 'GET', '/settings' );
	ok( 200 === $response->get_status() && array_key_exists( 'sender_name', $response->get_data() ) && ! array_key_exists( 'subject', $response->get_data() ), '/settings is the settings route, not an email id' );
	$response = call_api( 'GET', '/nope_not_an_email' );
	ok( 404 === $response->get_status() && 'ohmylms_email_not_found' === code_of( $response ), 'unknown email is 404' );
	ok( 404 === call_api( 'PUT', '/nope_not_an_email', array( 'subject' => 'x' ) )->get_status(), 'update of unknown email is 404' );
	ok( 404 === call_api( 'POST', '/nope_not_an_email/preview' )->get_status(), 'preview of unknown email is 404' );
	ok( 404 === call_api( 'POST', '/nope_not_an_email/test' )->get_status(), 'test of unknown email is 404' );
	ok( 404 === call_api( 'GET', '/Bad-Id' )->get_status(), 'malformed id does not route' );

	// Update: partial, sanitized, stored in the option the sending classes read.
	$before   = call_api( 'GET', '/student_quiz_graded' )->get_data();
	$response = call_api(
		'PUT',
		'/student_quiz_graded',
		array(
			'enabled' => false,
			'subject' => "  Result for {student_name}\r\nBcc: attacker@example.com <b>bold</b>  ",
		)
	);
	ok( 200 === $response->get_status(), 'update 200: ' . wp_json_encode( $response->get_data() ) );
	$updated = $response->get_data();
	ok( false === $updated['enabled'] && 'Result for {student_name} Bcc: attacker@example.com bold' === $updated['subject'], 'subject is one clean line: ' . $updated['subject'] );
	ok( $before['heading'] === $updated['heading'] && $before['additional_content'] === $updated['additional_content'] && $before['footer_text'] === $updated['footer_text'], 'a partial update leaves other fields alone' );
	$stored = get_option( Emails::OPTION_PREFIX . 'student_quiz_graded' );
	ok( false === $stored['enable'] && $updated['subject'] === $stored['subject'] && isset( $stored['additional_content'] ), 'stored in the option the sending class reads, with its own keys' );
	ok( array( array( 'student_quiz_graded', $updated['subject'] ) ) === $fired, 'ohmylms_email_updated fired once' );
	ok( Emails::is_enabled( Emails::get_template_settings( 'student_quiz_graded', array( 'enable' => true ) ) ) === false, 'disabled email is reported disabled to the sending class' );

	foreach ( array( 'PATCH', 'POST' ) as $method ) {
		ok( 200 === call_api( $method, '/student_quiz_graded', array( 'heading' => "Heading $method" ) )->get_status(), "update via $method" );
	}
	ok( 'Heading POST' === call_api( 'GET', '/student_quiz_graded' )->get_data()['heading'], 'update persisted' );

	// HTML is sanitized with the allow-list.
	$dirty    = '<p onclick="steal()" style="color:#333">Hi <strong>{student_name}</strong></p><script>alert(1)</script><iframe src="https://evil.example"></iframe><a href="javascript:alert(1)">bad</a><a href="https://example.com/x" target="_blank">ok</a><img src="https://example.com/a.png" onerror="x()" alt="a"><form action="/x"><input name="y"></form><table><tr><td style="padding:4px">cell</td></tr></table>';
	$response = call_api( 'PUT', '/student_quiz_graded', array( 'additional_content' => $dirty ) );
	ok( 200 === $response->get_status(), 'dirty html accepted after sanitizing' );
	$clean = $response->get_data()['additional_content'];
	foreach ( array( '<script', 'alert(1)</script', '<iframe', 'javascript:', 'onclick', 'onerror', '<form', '<input' ) as $banned ) {
		ok( false === stripos( $clean, $banned ), "sanitized body has no $banned: $clean" );
	}
	foreach ( array( '<strong>{student_name}</strong>', 'href="https://example.com/x"', '<img src="https://example.com/a.png"', '<table>', 'style="color:#333"' ) as $kept ) {
		ok( false !== strpos( $clean, $kept ), "sanitized body keeps $kept: $clean" );
	}
	ok( 400 === call_api( 'PUT', '/student_quiz_graded', array( 'additional_content' => array( 'x' ) ) )->get_status(), 'non-string body rejected by the schema' );
	$footer = call_api( 'PUT', '/student_quiz_graded', array( 'footer_text' => '<p>Thanks,<br><strong>[site_name]</strong> <a href="mailto:[admin_email]">mail</a></p>' ) );
	ok( 200 === $footer->get_status() && false !== strpos( $footer->get_data()['footer_text'], 'mailto:[admin_email]' ), 'bracket tags and mailto links survive' );

	// Validation: nothing is written when any field is invalid.
	$snapshot_item = call_api( 'GET', '/student_quiz_graded' )->get_data();
	$invalid       = array(
		'unknown brace tag'        => array( array( 'subject' => 'Hello {nope}' ), 422, 'ohmylms_email_unknown_tags' ),
		'unknown bracket tag'      => array( array( 'footer_text' => '<p>[secret_value]</p>' ), 422, 'ohmylms_email_unknown_tags' ),
		'tag from another email'   => array( array( 'heading' => 'About {assignment_name}' ), 422, 'ohmylms_email_unknown_tags' ),
		'empty subject'            => array( array( 'subject' => '' ), 400, 'rest_invalid_param' ),
		'subject of only markup'   => array( array( 'subject' => '<b></b>' ), 422, 'ohmylms_email_invalid_field' ),
		'subject too long'         => array( array( 'subject' => str_repeat( 'a', 256 ) ), 422, 'ohmylms_email_invalid_field' ),
		'heading too long'         => array( array( 'heading' => str_repeat( 'a', 201 ) ), 422, 'ohmylms_email_invalid_field' ),
		'button text too long'     => array( array( 'button_text' => str_repeat( 'a', 61 ) ), 422, 'ohmylms_email_invalid_field' ),
		'empty message body'       => array( array( 'additional_content' => '<script>x</script><p> </p>' ), 422, 'ohmylms_email_invalid_field' ),
		'javascript link'          => array( array( 'button_link' => 'javascript:alert(1)' ), 422, 'ohmylms_email_invalid_field' ),
		'ftp link'                 => array( array( 'button_link' => 'ftp://example.com/file' ), 422, 'ohmylms_email_invalid_field' ),
		'relative link'            => array( array( 'button_link' => '/wp-admin/' ), 422, 'ohmylms_email_invalid_field' ),
		'recipients on a student'  => array( array( 'recipient_email' => array( 'a@example.com' ) ), 422, 'ohmylms_email_unsupported_field' ),
		'suggestion not supported' => array( array( 'course_suggestion_text' => 'More' ), 422, 'ohmylms_email_unsupported_field' ),
		'enabled is not a boolean' => array( array( 'enabled' => 'maybe' ), 400, 'rest_invalid_param' ),
		'nothing to change'        => array( array(), 400, 'ohmylms_email_empty_update' ),
	);
	foreach ( $invalid as $label => $case ) {
		// A valid field is sent along: the failure must not write it either.
		$body     = array_merge( array( 'button_text' => 'Should not be saved' ), $case[0] );
		$body     = $case[0] ? $body : array();
		$response = call_api( 'PUT', '/student_quiz_graded', $body );
		ok( $response->get_status() === $case[1] && code_of( $response ) === $case[2], "$label: expected {$case[1]} {$case[2]}, got {$response->get_status()} " . code_of( $response ) );
	}
	ok( call_api( 'GET', '/student_quiz_graded' )->get_data() === $snapshot_item, 'rejected updates changed nothing' );
	$response = call_api( 'PUT', '/student_quiz_graded', array( 'subject' => 'Hello {nope} and [other_thing]' ) );
	ok( array( '{nope}', '[other_thing]' ) === $response->get_data()['data']['tags'], 'unknown tags are listed in the error' );
	ok( 200 === call_api( 'PUT', '/student_quiz_graded', array( 'heading' => '[Important] {STUDENT_NAME} {quiz_name} {site_name}' ) )->get_status(), 'literal [Word] and known tags (any case) are accepted' );

	// Recipients and the course suggestion where supported.
	$response = call_api(
		'PUT',
		'/creator_new_order',
		array(
			'recipient_email' => array( 'Owner@Example.com', 'owner@example.com', 'second@example.com' ),
			'button_link'     => 'https://example.com/dashboard',
			'button_text'     => 'Open',
		)
	);
	ok( 200 === $response->get_status() && array( 'owner@example.com', 'second@example.com' ) === $response->get_data()['recipient_email'], 'recipients de-duplicated: ' . wp_json_encode( $response->get_data() ) );
	ok( 'https://example.com/dashboard' === $response->get_data()['button_link'] && 'Open' === $response->get_data()['button_text'], 'link and button text saved' );
	foreach ( array( array( 'not an email' ), array( "a@example.com\r\nBcc: b@example.com" ), array( 'a@example.com, b@example.com' ) ) as $bad ) {
		$response = call_api( 'PUT', '/creator_new_order', array( 'recipient_email' => $bad ) );
		ok( 422 === $response->get_status() && 'ohmylms_email_invalid_field' === code_of( $response ), 'bad recipient rejected: ' . wp_json_encode( $bad ) );
	}
	ok(
		422 === call_api(
			'PUT',
			'/creator_new_order',
			array(
				'recipient_email' => array_map(
					static function ( $i ) {
						return "r$i@example.com";
					},
					range( 1, 11 )
				),
			)
		)->get_status(),
		'more than 10 recipients rejected'
	);
	ok( 200 === call_api( 'PUT', '/creator_new_order', array( 'recipient_email' => array() ) )->get_status() && array() === call_api( 'GET', '/creator_new_order' )->get_data()['recipient_email'], 'recipients can be cleared' );
	ok( 200 === call_api( 'PUT', '/student_course_completed', array( 'course_suggestion_text' => 'Try <em>Geometry</em>' ) )->get_status(), 'course suggestion text saved where supported' );
	ok(
		array( 'a@example.com', 'b@example.com' ) === ( function () {
			update_option( Emails::OPTION_PREFIX . 'creator_new_order', array_merge( get_option( Emails::OPTION_PREFIX . 'creator_new_order' ), array( 'recipient_email' => 'a@example.com, bad, b@example.com' ) ) );
			return call_api( 'GET', '/creator_new_order' )->get_data()['recipient_email'];
		} )(),
		'a legacy comma separated recipient string is read as a list'
	);

	// Merge tag engine.
	$values = array(
		'student_name' => '<img src=x onerror=alert(1)>',
		'course_name'  => 'Algebra & Co',
	);
	$html   = Emails::render_tags( '<p>Hi {student_name} / [Student_Name] in [course_name]; {unknown} [site_name]</p>', $values );
	ok( false === strpos( $html, '<img' ) && false !== strpos( $html, '&lt;img src=x onerror=alert(1)&gt;' ), 'html text: values are escaped' );
	ok( false !== strpos( $html, '{unknown}' ) && false !== strpos( $html, esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) ), 'unknown tags stay, site tags resolve' );
	ok( 'Hi Algebra & Co' === Emails::render_tags( 'Hi {course_name}', $values ), 'plain subjects are not escaped' );
	ok( array( '{x_y}', '[big_one]' ) === Emails::find_unknown_tags( '{x_y} {student_name} [Important] [big_one] [Student_Name] {x_y}', array( 'student_name' ) ), 'unknown tag detection' );
	ok( 'Student Name stays' === Emails::render_tags( 'Student Name stays', array( 'student_name' => 'X' ) ), 'bare words are never replaced' );

	// Global settings.
	$response = call_api( 'GET', '/settings' );
	$global   = $response->get_data();
	ok( 200 === $response->get_status() && '#6E42D3' === $global['base_color'] && 'left' === $global['button_position'] && '' === $global['sender_name'], 'settings defaults: ' . wp_json_encode( $global ) );
	$response = call_api(
		'PUT',
		'/settings',
		array(
			'base_color'      => '#1a2b3c',
			'button_position' => 'center',
			'sender_name'     => 'Example Academy',
			'sender_email'    => 'academy@example.com',
			'branding_image'  => 'https://example.com/logo.png',
			'footer_text'     => '<p>Example Academy</p><script>x</script>',
		)
	);
	ok( 200 === $response->get_status(), 'settings update 200: ' . wp_json_encode( $response->get_data() ) );
	$global = $response->get_data();
	ok( '#1A2B3C' === $global['base_color'] && 'center' === $global['button_position'] && 'Example Academy' === $global['sender_name'] && 'academy@example.com' === $global['sender_email'], 'settings saved' );
	ok( '<p>Example Academy</p>' === $global['footer_text'], 'settings footer is sanitized: ' . $global['footer_text'] );
	ok( '#1A2B3C' === get_option( 'ohmylms_email_base_color' ) && 'center' === get_option( 'ohmylms_email_button_possition' ) && 'academy@example.com' === get_option( 'ohmylms_email_sender_email_address' ), 'stored in the options the templates read' );
	ok( '#F4F5F7' === $global['background_color'], 'untouched settings keep their value' );
	$bad_settings = array(
		'color without hash'     => array( array( 'base_color' => '1a2b3c' ), 400 ),
		'color with a script'    => array( array( 'base_color' => '#12345g' ), 400 ),
		'css in a color'         => array( array( 'body_text_color' => 'red;background:url(x)' ), 400 ),
		'position outside enum'  => array( array( 'button_position' => 'middle' ), 400 ),
		'logo javascript url'    => array( array( 'branding_image' => 'javascript:alert(1)' ), 422 ),
		'logo relative url'      => array( array( 'branding_image' => '/logo.png' ), 422 ),
		'sender newline'         => array( array( 'sender_name' => "Evil\r\nBcc: x@example.com" ), 422 ),
		'sender angle brackets'  => array( array( 'sender_name' => 'Evil <x@example.com>' ), 422 ),
		'sender quote'           => array( array( 'sender_name' => 'Ev"il' ), 422 ),
		'sender name too long'   => array( array( 'sender_name' => str_repeat( 'a', 101 ) ), 422 ),
		'sender address invalid' => array( array( 'sender_email' => 'not-an-address' ), 422 ),
		'sender address newline' => array( array( 'sender_email' => "a@example.com\r\nBcc: b@example.com" ), 422 ),
		'nothing to change'      => array( array(), 400 ),
	);
	foreach ( $bad_settings as $label => $case ) {
		$response = call_api( 'PUT', '/settings', $case[0] );
		ok( $case[1] === $response->get_status(), "settings $label: expected {$case[1]}, got {$response->get_status()}" );
	}
	ok( call_api( 'GET', '/settings' )->get_data() === $global, 'rejected settings changed nothing' );

	// A value written around the API (the generic settings route does not validate) cannot break a header.
	update_option( 'ohmylms_email_sender_name', "Mallory\r\nBcc: victim@example.com <x>" );
	update_option( 'ohmylms_email_base_color', 'red;} body{display:none' );
	$effective = Emails::get_email_settings();
	ok( false === strpos( $effective['ohmylms_email_sender_name'], "\n" ) && false === strpos( $effective['ohmylms_email_sender_name'], '<' ), 'sender name is cleaned when read: ' . $effective['ohmylms_email_sender_name'] );
	ok( '#6E42D3' === $effective['ohmylms_email_base_color'], 'an invalid stored color falls back to the default' );
	$headers = EmailManager::mail_headers( $effective );
	ok( 2 === count( $headers ) && 0 === strpos( $headers[1], 'From: Mallory' ) && 1 === preg_match( '/^From: [^\r\n<>"]+ <academy@example\.com>$/', $headers[1] ), 'one From header on a single line: ' . $headers[1] );
	update_option( 'ohmylms_email_sender_name', 'Example Academy' );
	update_option( 'ohmylms_email_base_color', '#1A2B3C' );

	// Preview with the real templates.
	$set_error = static function ( $number, $message, $file ) use ( &$warnings ) {
		if ( false !== strpos( $file, 'OhMyLMS' ) ) {
			$warnings[] = basename( $file ) . ': ' . $message;
		}
		return false;
	};
	set_error_handler( $set_error );
	foreach ( EmailManager::ids() as $email_id ) {
		$response = call_api( 'POST', "/$email_id/preview" );
		ok( 200 === $response->get_status(), "preview $email_id 200: " . wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		ok( array( 'subject', 'html', 'unknown_tags' ) === array_keys( $data ), "preview $email_id shape" );
		ok( false !== strpos( $data['html'], '<table' ) && false === stripos( $data['html'], '<html' ) && false === stripos( $data['html'], '<body' ) && false === stripos( $data['html'], '<script' ), "preview $email_id is a sanitized fragment" );
		ok( false !== strpos( $data['html'], $s_student ) || false !== strpos( $data['html'], $s_course ), "preview $email_id uses sample data" );
		ok( false === strpos( $data['html'], '{student_name}' ) && false === strpos( $data['html'], '[student_name]' ) && false === strpos( $data['html'], '{course_name}' ), "preview $email_id has no unreplaced tags" );
		ok( array() === $data['unknown_tags'] && '' !== $data['subject'] && false === strpos( $data['subject'], '{' ), "preview $email_id subject: {$data['subject']}" );
		ok( false !== stripos( $data['html'], '#1A2B3C' ), "preview $email_id uses the configured accent color" );
		ok( false !== stripos( $data['html'], 'border-radius' ), "preview $email_id keeps email layout styles" );
	}
	restore_error_handler();
	ok( array() === $warnings, 'rendering the templates raised no PHP notices from the plugin: ' . implode( '; ', array_unique( $warnings ) ) );

	$exam_preview = call_api( 'POST', '/student_exam_results_released/preview' )->get_data();
	ok( false !== strpos( $exam_preview['subject'], __( 'End of Term Mathematics Exam', 'ohmylms' ) ) && false !== strpos( $exam_preview['html'], __( 'End of Term Mathematics Exam', 'ohmylms' ) ), 'release email preview fills {exam_name}: ' . $exam_preview['subject'] );
	ok( 422 === call_api( 'PUT', '/student_exam_results_released', array( 'subject' => 'Results for {quiz_name}' ) )->get_status(), 'release email refuses tags it does not provide' );

	$orders = call_api( 'POST', '/creator_new_order/preview' )->get_data();
	ok( false !== strpos( $orders['html'], '1001' ) && false !== strpos( $orders['html'], $s_course ), 'order preview renders the order and its items' );
	ok( 'Congratulations! ' . $s_student . ' placed New Order' === $orders['subject'], 'order preview subject: ' . $orders['subject'] );

	// Preview applies an unsaved draft and never stores it.
	$stored_before = get_option( Emails::OPTION_PREFIX . 'student_quiz_graded' );
	$response      = call_api(
		'POST',
		'/student_quiz_graded/preview',
		array(
			'heading'            => 'Draft heading for {student_name}',
			'additional_content' => '<p>Draft {quiz_name} <script>alert(1)</script><span onmouseover="x()">hover</span></p>',
			'subject'            => 'Draft subject {course_name}',
		)
	);
	$data          = $response->get_data();
	ok( 200 === $response->get_status() && false !== strpos( $data['html'], 'Draft heading for ' . $s_student ) && false !== strpos( $data['html'], 'Draft ' . $s_quiz ), 'preview shows the draft with sample data' );
	ok( false === strpos( $data['html'], '<script' ) && false === strpos( $data['html'], 'onmouseover' ) && false === strpos( $data['html'], 'alert(1)</script' ), 'preview strips unsafe draft markup' );
	ok( 'Draft subject ' . $s_course === $data['subject'], 'preview subject uses the draft' );
	ok( get_option( Emails::OPTION_PREFIX . 'student_quiz_graded' ) === $stored_before, 'preview stores nothing' );

	$response = call_api( 'POST', '/student_quiz_graded/preview', array( 'additional_content' => '<p>Hi {student_nam} and [made_up_tag] and {quiz_name}</p>' ) );
	$data     = $response->get_data();
	ok( 200 === $response->get_status() && array( '{student_nam}', '[made_up_tag]' ) === $data['unknown_tags'], 'preview reports unknown tags instead of failing: ' . wp_json_encode( $data['unknown_tags'] ) );
	ok( false !== strpos( $data['html'], '{student_nam}' ) && false !== strpos( $data['html'], $s_quiz ), 'unknown tags stay visible, known ones resolve' );
	ok( 422 === call_api( 'POST', '/student_quiz_graded/preview', array( 'button_link' => 'javascript:alert(1)' ) )->get_status(), 'preview still validates links' );

	// Test email: current administrator only, rate limited, captured.
	$mails    = array();
	$response = call_api(
		'POST',
		'/student_quiz_graded/test',
		array(
			'to'      => 'evil@example.com',
			'subject' => 'Test draft {quiz_name}',
		)
	);
	ok( 200 === $response->get_status() && true === $response->get_data()['sent'] && $admin_mail === $response->get_data()['to'], 'test email 200: ' . wp_json_encode( $response->get_data() ) );
	ok( 1 === count( $mails ) && array( $admin_mail ) === (array) $mails[0]['to'], 'test email goes to the current administrator only' );
	ok( 0 === strpos( $mails[0]['subject'], '[Test] ' ) && false !== strpos( $mails[0]['subject'], 'Test draft ' . $s_quiz ), 'test subject: ' . $mails[0]['subject'] );
	ok( false !== strpos( $mails[0]['message'], '<table' ) && false === stripos( $mails[0]['message'], '<script' ), 'test email carries the sanitized template' );
	ok(
		1 === count(
			array_filter(
				(array) $mails[0]['headers'],
				static function ( $h ) {
					return 0 === stripos( $h, 'From: Example Academy <academy@example.com>' );
				}
			)
		),
		'test email uses the configured sender'
	);
	ok( false === strpos( wp_json_encode( $mails ), 'evil@example.com' ), 'a "to" parameter is ignored' );

	$response = call_api( 'POST', '/student_quiz_graded/test' );
	ok( 429 === $response->get_status() && 'ohmylms_email_test_rate_limited' === code_of( $response ) && ! empty( $response->get_headers()['Retry-After'] ) && $response->get_data()['data']['retry_after'] > 0, 'second test is rate limited' );
	ok( 1 === count( $mails ), 'rate limited test sent nothing' );
	delete_transient( 'ohmylms_email_test_' . $admin );

	$response = call_api( 'POST', '/student_quiz_graded/test', array( 'heading' => 'Bad {tag_here}' ) );
	ok( 422 === $response->get_status() && 'ohmylms_email_unknown_tags' === code_of( $response ) && 1 === count( $mails ), 'a draft with unknown tags is not sent' );
	delete_transient( 'ohmylms_email_test_' . $admin );

	$mail_ok  = false;
	$response = call_api( 'POST', '/student_quiz_graded/test' );
	ok( 500 === $response->get_status() && 'ohmylms_email_send_failed' === code_of( $response ), 'mail failure is a 500' );
	$mail_ok  = true;
	$response = call_api( 'POST', '/student_quiz_graded/test' );
	ok( 200 === $response->get_status(), 'a failed send does not start the rate limit' );
	delete_transient( 'ohmylms_email_test_' . $admin );

	// The toggle is honoured by the sending class, and its tags are filled in.
	$mails = array();
	update_option(
		Emails::OPTION_PREFIX . 'creator_new_order',
		array_merge(
			get_option( Emails::OPTION_PREFIX . 'creator_new_order' ),
			array(
				'enable'  => false,
				'subject' => 'New order from {student_name} at {site_name}',
			)
		)
	);
	$sample_order = new SampleRecord(
		array(
			'get_id'                   => $post_id,
			'get_student_name'         => 'Alex Learner',
			'get_date_created'         => new DateTimeImmutable( 'now' ),
			'get_currency'             => 'USD',
			'get_cart_subtotal'        => 10,
			'get_total'                => 10,
			'get_payment_method_title' => 'Test',
			'get_items'                => array(),
		)
	);
	$instance     = ( new ReflectionClass( NewOrder::class ) )->newInstanceWithoutConstructor();
	$instance->trigger( $sample_order, array() );
	ok( array() === $mails, 'a disabled email is not sent' );
	update_option( Emails::OPTION_PREFIX . 'creator_new_order', array_merge( get_option( Emails::OPTION_PREFIX . 'creator_new_order' ), array( 'enable' => true ) ) );
	$instance->trigger( $sample_order, array() );
	ok( 1 === count( $mails ), 'an enabled email is sent' );
	ok( 'New order from Alex Learner at ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) === $mails[0]['subject'], 'sending class fills subject tags: ' . $mails[0]['subject'] );

	// Schema.
	$options = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/emails/student_quiz_graded' ) );
	$schema  = $options->get_data()['schema'];
	ok( 'http://json-schema.org/draft-04/schema#' === $schema['$schema'] && 'ohmylms-email' === $schema['title'], 'schema header' );
	foreach ( array( 'id', 'title', 'description', 'recipient_type', 'enabled', 'subject', 'heading', 'additional_content', 'button_text', 'button_link', 'footer_text', 'course_suggestion_text', 'recipient_email', 'tags' ) as $property ) {
		ok( isset( $schema['properties'][ $property ] ), "schema property $property" );
	}
	ok( ! empty( $schema['properties']['id']['readonly'] ) && ! empty( $schema['properties']['tags']['readonly'] ) && empty( $schema['properties']['subject']['readonly'] ), 'schema marks derived fields readonly' );
	$options = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/emails/settings' ) );
	ok( 'ohmylms-email-settings' === $options->get_data()['schema']['title'] && isset( $options->get_data()['schema']['properties']['sender_email'] ), 'settings schema' );

	fwrite( STDOUT, "OK emails-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	// Restore exactly what existed before the run.
	foreach ( $snapshot as $name => $value ) {
		if ( $value === $missing ) {
			delete_option( $name );
		} else {
			update_option( $name, $value );
		}
	}
	delete_post_meta( $post_id, '_creator_new_order_email_sent' );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	if ( isset( $admin ) ) {
		delete_transient( 'ohmylms_email_test_' . $admin );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
