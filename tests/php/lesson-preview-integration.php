<?php
/**
 * "Preview as learner" for lessons (OhMyLMS\Learning\LessonPreview, docs/api/lesson-authoring.md): the signed
 * link opens the real lesson template and `ohmylms/lesson` module for people who can edit the lesson, with the
 * "Preview — nothing is recorded" banner; learners, other users, expired, tampered and other users' links are
 * refused; drip, prerequisites, the sequential lock and the video rule show as a new learner hits them, and the
 * bypass link (author only) opens past them; nothing is enrolled, completed or tracked, the completion and video
 * endpoints refuse while the preview is open, and no completion hook fires. The REST route mints links for
 * editors only.
 *
 * Runs against the Local site. It creates a tagged course, chapter, lessons and four temporary users (author,
 * enrolled editor, enrolled learner, stranger) with one session token each for the HTTP part, and removes them,
 * their rows, transients and tokens in `finally`.
 *
 * Usage (set OHMYLMS_LESSON_PREVIEW_TEST=1):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/lesson-preview-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.Security.NonceVerification, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_LESSON_PREVIEW_TEST' ) ) {
	exit( "Set OHMYLMS_LESSON_PREVIEW_TEST=1 to run the lesson preview tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Assignment\Delivery;
use OhMyLMS\Learning\LessonPreview;

global $wpdb;

$checks    = 0;
$warnings  = array();
$post_ids  = array();
$user_ids  = array();
$sessions  = array();
$tag       = 'lpvtest' . strtolower( wp_generate_password( 6, false, false ) );
$exit_code = 0;

add_filter( 'pre_wp_mail', '__return_true' );
set_error_handler(
	static function ( $severity, $message, $file, $line ) use ( &$warnings ) {
		if ( false !== strpos( wp_normalize_path( $file ), wp_normalize_path( OHMYLMS_DIR ) ) ) {
			$warnings[] = "$message in $file:$line";
		}
		return false;
	}
);

/**
 * Record one check.
 *
 * @param bool   $condition Passes.
 * @param string $message   Failure message.
 * @throws RuntimeException When the check fails.
 */
function ok( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
}

/**
 * Insert a tagged post.
 *
 * @param array $args Fields.
 * @return int
 */
function make_post( array $args ) {
	global $post_ids;
	$id = wp_insert_post( $args + array( 'post_status' => 'publish' ), true );
	ok( $id && ! is_wp_error( $id ), 'post created: ' . $args['post_title'] );
	$post_ids[] = (int) $id;
	return (int) $id;
}

/**
 * Temporary user.
 *
 * @param string $suffix Suffix.
 * @param string $role   Role.
 * @return int
 */
function make_user( $suffix, $role ) {
	global $user_ids, $tag;
	$id = wp_insert_user(
		array(
			'user_login'   => $tag . '_' . $suffix,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '_' . $suffix . '@example.invalid',
			'display_name' => $tag . ' ' . $suffix,
			'role'         => $role,
		)
	);
	ok( ! is_wp_error( $id ), "temporary user $suffix" );
	$user_ids[] = (int) $id;
	return (int) $id;
}

/**
 * One session token and its cookies for a temporary user.
 *
 * @param int $user User.
 * @return array{user:int,token:string,logged_in:string,cookie:string}
 */
function session( $user ) {
	global $sessions;
	$expires    = time() + HOUR_IN_SECONDS;
	$token      = WP_Session_Tokens::get_instance( $user )->create( $expires );
	$logged_in  = wp_generate_auth_cookie( $user, $expires, 'logged_in', $token );
	$sessions[] = array( $user, $token );
	return array(
		'user'      => $user,
		'token'     => $token,
		'logged_in' => $logged_in,
		'cookie'    => AUTH_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $user, $expires, 'auth', $token ) ) . '; ' . LOGGED_IN_COOKIE . '=' . rawurlencode( $logged_in ),
	);
}

/**
 * Run code as a session's user, so nonces are bound to that session as on its pages.
 *
 * @param array    $session {@see session()}.
 * @param callable $work    Code.
 * @return mixed
 */
function as_session( array $session, callable $work ) {
	$saved                       = $_COOKIE;
	$_COOKIE[ LOGGED_IN_COOKIE ] = $session['logged_in'];
	wp_set_current_user( $session['user'] );
	try {
		return $work();
	} finally {
		$_COOKIE = $saved;
		wp_set_current_user( 0 );
	}
}

/**
 * Make a post the current singular query, as on its page.
 *
 * @param int $id Post ID.
 */
function view( $id ) {
	global $wp_query, $wp_the_query, $post;
	$wp_query     = new WP_Query(
		array(
			'p'           => $id,
			'post_type'   => get_post_type( $id ),
			'post_status' => 'any',
		)
	);
	$wp_the_query = $wp_query;
	$post         = get_post( $id );
	setup_postdata( $post );
}

/**
 * Render a template to a string.
 *
 * @param string $name Template name.
 * @return string
 */
function render( $name ) {
	ob_start();
	ohmylms_get_template( $name );
	return (string) ob_get_clean();
}

/**
 * Attributes of the first element matching a class or `#id`.
 *
 * @param string $html     HTML.
 * @param string $selector Selector.
 * @return array|null
 */
function attributes_of( $html, $selector ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		$match = 0 === strpos( $selector, '#' ) ? substr( $selector, 1 ) === $tags->get_attribute( 'id' ) : $tags->has_class( $selector );
		if ( $match ) {
			$attributes = array();
			foreach ( (array) $tags->get_attribute_names_with_prefix( '' ) as $name ) {
				$attributes[ $name ] = $tags->get_attribute( $name );
			}
			return $attributes;
		}
	}
	return null;
}

/**
 * Call an admin-ajax handler like admin-ajax.php does and decode its answer.
 *
 * @param string $method `Ajax` method.
 * @param int    $who    Current user.
 * @param array  $post   POST fields.
 * @return mixed
 * @throws RuntimeException When the handler fails other than by ending the request.
 */
function call_ajax( $method, $who, array $post ) {
	wp_set_current_user( $who );
	$_POST    = $post;
	$_REQUEST = $post;
	$die      = static function () {
		return static function ( $message = '' ) {
			echo is_scalar( $message ) ? (string) $message : '';
			throw new RuntimeException( 'ajax-done' );
		};
	};
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	try {
		call_user_func( array( \OhMyLMS\Ajax::class, $method ) );
	} catch ( RuntimeException $done ) {
		if ( 'ajax-done' !== $done->getMessage() ) {
			throw $done;
		}
	} finally {
		$body = (string) ob_get_clean();
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', $die );
		$_POST    = array();
		$_REQUEST = array();
	}
	$decoded = json_decode( $body, true );
	return null === $decoded ? $body : $decoded;
}

/**
 * HTTP request to the site with a session's cookies.
 *
 * @param string $url    URL.
 * @param string $cookie Cookie header.
 * @param array  $fields POST fields, null for GET.
 * @param array  $header Extra headers.
 * @return array{status:int, body:string}
 */
function http( $url, $cookie, $fields = null, array $header = array() ) {
	$curl = curl_init( $url );
	curl_setopt_array(
		$curl,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_COOKIE         => $cookie,
			CURLOPT_TIMEOUT        => 60,
			CURLOPT_HTTPHEADER     => $header,
		)
	);
	if ( null !== $fields ) {
		curl_setopt( $curl, CURLOPT_POST, true );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, $fields );
	}
	$body   = (string) curl_exec( $curl );
	$status = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
	return array(
		'status' => $status,
		'body'   => $body,
	);
}

/**
 * Decoded JSON of a `<script type="application/json" id="…">` in a page.
 *
 * @param string $page Page HTML.
 * @param string $id   Script ID.
 * @return array
 */
function script_data( $page, $id ) {
	if ( ! preg_match( '#<script[^>]+id="' . preg_quote( $id, '#' ) . '"[^>]*>(.*?)</script>#s', $page, $match ) ) {
		return array();
	}
	return (array) json_decode( $match[1], true );
}

/**
 * Rows of a user in every OhMyLMS table with a user column (enrolments, progress, video, xAPI, events…).
 *
 * @param int $user User.
 * @return int
 */
function rows_of( $user ) {
	global $wpdb;
	$total   = 0;
	$columns = $wpdb->get_results( $wpdb->prepare( "SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE %s AND COLUMN_NAME IN ('user_id','student_id','learner_id')", $wpdb->esc_like( $wpdb->prefix . 'ohmylms_' ) . '%' ) );
	foreach ( (array) $columns as $column ) {
		$total += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE %i = %d', $column->t, $column->c, $user ) );
	}
	return $total;
}

/**
 * Whether the lesson page is the real learner template with its module.
 *
 * @param string $body Page.
 * @return bool
 */
function real_template( $body ) {
	return false !== strpos( $body, 'ohmylms-lesson-details' ) && false !== strpos( $body, 'data-wp-interactive="ohmylms/lesson"' ) && false !== strpos( $body, 'assets/interactivity/lesson.js' );
}

try {
	$fired = array();
	foreach ( array( 'ohmylms_lesson_completed', 'ohmylms_after_lesson_completed', 'ohmylms_verified_lesson_completed', 'ohmylms_course_completion_rate', 'ohmylms_course_completed', 'ohmylms_video_lesson_completed' ) as $hook ) {
		add_action(
			$hook,
			static function () use ( &$fired, $hook ) {
				$fired[] = $hook;
			}
		);
	}

	// Fixtures: A (reading) -> B (needs A) -> C (dripped 2 days) -> D (video, 50 % rule) -> E; F is a draft.
	$author  = make_user( 'author', 'author' );
	$editor  = make_user( 'editor', 'editor' );
	$learner = make_user( 'learner', 'subscriber' );
	$other   = make_user( 'other', 'subscriber' );
	$course_id  = make_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_title'  => "$tag course",
			'post_author' => $author,
		)
	);
	$chapter = make_post(
		array(
			'post_type'  => 'ohmylms-chapter',
			'post_title' => "$tag chapter",
		)
	);
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_chapter_relationship',
		array(
			'course_id'    => $course_id,
			'chapter_id'   => $chapter,
			'order_number' => 1,
		)
	);
	$lessons = array();
	foreach ( array( 'A', 'B', 'C', 'D', 'E' ) as $order => $name ) {
		$lessons[ $name ] = make_post(
			array(
				'post_type'    => 'ohmylms-lesson',
				'post_title'   => "$tag lesson $name",
				'post_content' => "<p>Body of lesson $name.</p>",
				'post_author'  => $author,
			)
		);
		update_post_meta( $lessons[ $name ], '_type', 'D' === $name ? 'video' : 'text' );
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id'   => $chapter,
				'content_id'   => $lessons[ $name ],
				'content_type' => 'D' === $name ? 'video' : 'text',
				'order_number' => $order + 1,
			)
		);
	}
	$draft = make_post(
		array(
			'post_type'    => 'ohmylms-lesson',
			'post_title'   => "$tag draft F",
			'post_content' => '<p>Unpublished body.</p>',
			'post_author'  => $author,
			'post_status'  => 'draft',
		)
	);
	update_post_meta(
		$lessons['B'],
		'_prerequisites',
		array(
			'enable' => true,
			'data'   => array(
				array(
					'value' => $lessons['A'],
					'label' => "$tag lesson A",
				),
			),
		)
	);
	update_post_meta(
		$lessons['C'],
		'_drip_settings',
		array(
			'enable' => true,
			'type'   => 'enrollment-from-x-days',
			'days'   => 2,
			'date'   => '',
			'time'   => '',
		)
	);
	update_post_meta( $lessons['D'], '_external_url', 'https://www.youtube.com/watch?v=abcdefghijk' );
	update_post_meta(
		$lessons['D'],
		'_video_settings',
		array(
			'platform'        => 'youtube',
			'completion_rule' => 'watch_percent',
			'watch_percent'   => 50,
		)
	);
	ok( true === \OhMyLMS\Student\Enrollments::enroll_manually( $learner, $course_id ), 'learner enrolled' );
	ok( true === \OhMyLMS\Student\Enrollments::enroll_manually( $editor, $course_id ), 'editor enrolled (an editor who is also a learner)' );
	ok( \OhMyLMS\Learning\VideoCompletion::rule( $lessons['D'] )['required'], 'lesson D requires watching' );

	// Who may preview, and the link itself.
	ok( LessonPreview::can_preview( $lessons['A'], $author ) && LessonPreview::can_preview( $lessons['A'], $editor ) && LessonPreview::can_preview( $draft, $author ), 'the author and an editor can preview, drafts too' );
	ok( ! LessonPreview::can_preview( $lessons['A'], $learner ) && ! LessonPreview::can_preview( $lessons['A'], $other ) && ! LessonPreview::can_preview( $course_id, $author ), 'learners, other users and non-lessons cannot' );
	$sa = session( $author );
	$se = session( $editor );
	$sl = session( $learner );
	$so = session( $other );
	as_session(
		$sa,
		static function () use ( $lessons ) {
			$url = LessonPreview::url( $lessons['A'] );
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $args );
			ok( (int) $args[ LessonPreview::QUERY ] === $lessons['A'] && '0' === $args[ LessonPreview::BYPASS ] && abs( (int) $args[ LessonPreview::EXPIRES ] - ( time() + HOUR_IN_SECONDS ) ) < 60 && '' !== $args[ LessonPreview::NONCE ], 'the link carries lesson, bypass, a one-hour expiry and a nonce: ' . $url );
			ok( true === LessonPreview::verify( $lessons['A'], false, (int) $args[ LessonPreview::EXPIRES ], $args[ LessonPreview::NONCE ] ), 'the author verifies their own link' );
			ok( 'ohmylms_lesson_preview_invalid' === LessonPreview::verify( $lessons['A'], true, (int) $args[ LessonPreview::EXPIRES ], $args[ LessonPreview::NONCE ] )->get_error_code(), 'turning bypass on in a link breaks its signature' );
			ok( 'ohmylms_lesson_preview_invalid' === LessonPreview::verify( $lessons['A'], false, (int) $args[ LessonPreview::EXPIRES ] + 60, $args[ LessonPreview::NONCE ] )->get_error_code(), 'extending the expiry breaks its signature' );
			$past = time() - 10;
			ok( 'ohmylms_lesson_preview_expired' === LessonPreview::verify( $lessons['A'], false, $past, wp_create_nonce( 'ohmylms_lesson_preview_' . $lessons['A'] . '_0_' . $past ) )->get_error_code(), 'an expired link is refused even with a valid nonce' );
			$far = time() + DAY_IN_SECONDS;
			ok( 'ohmylms_lesson_preview_expired' === LessonPreview::verify( $lessons['A'], false, $far, wp_create_nonce( 'ohmylms_lesson_preview_' . $lessons['A'] . '_0_' . $far ) )->get_error_code(), 'a link longer than the lifetime is refused' );
		}
	);
	$author_link = as_session(
		$sa,
		static function () use ( $lessons ) {
			return LessonPreview::url( $lessons['A'] );
		}
	);
	parse_str( (string) wp_parse_url( $author_link, PHP_URL_QUERY ), $author_args );
	as_session(
		$se,
		static function () use ( $lessons, $author_args ) {
			ok( 'ohmylms_lesson_preview_invalid' === LessonPreview::verify( $lessons['A'], false, (int) $author_args[ LessonPreview::EXPIRES ], $author_args[ LessonPreview::NONCE ] )->get_error_code(), 'another editor cannot use the author\'s link' );
		}
	);
	as_session(
		$sl,
		static function () use ( $lessons, $author_args ) {
			ok( 'ohmylms_lesson_preview_forbidden' === LessonPreview::verify( $lessons['A'], false, (int) $author_args[ LessonPreview::EXPIRES ], $author_args[ LessonPreview::NONCE ] )->get_error_code(), 'a learner cannot use the author\'s link' );
			$own = LessonPreview::url( $lessons['A'], true );
			parse_str( (string) wp_parse_url( $own, PHP_URL_QUERY ), $own_args );
			ok( 'ohmylms_lesson_preview_forbidden' === LessonPreview::verify( $lessons['A'], true, (int) $own_args[ LessonPreview::EXPIRES ], $own_args[ LessonPreview::NONCE ] )->get_error_code(), 'a learner cannot preview even with a link signed for them' );
		}
	);

	// The learner rules of a new learner.
	ok( null !== LessonPreview::gate( $lessons['B'], $course_id ) && false !== strpos( LessonPreview::gate( $lessons['B'], $course_id )['message'], "$tag lesson A" ), 'B stops at its prerequisite' );
	ok( null === LessonPreview::gate( $lessons['A'], $course_id ) && null === LessonPreview::gate( $lessons['E'], $course_id ), 'A and E are open without sequential mode' );
	$opens = LessonPreview::opens_at( $lessons['C'], $course_id );
	ok( $opens && abs( $opens->getTimestamp() - ( time() + 2 * DAY_IN_SECONDS ) ) < 60, 'C opens two days after a learner enrols now' );
	update_post_meta( $course_id, '_sequential_mode', 'yes' );
	ok( LessonPreview::sequential( $lessons['E'], $course_id ) && ! LessonPreview::sequential( $lessons['A'], $course_id ) && 'Content Locked' === LessonPreview::gate( $lessons['E'], $course_id )['title'], 'sequential mode locks everything after A' );
	update_post_meta( $course_id, '_sequential_mode', 'no' );

	// REST: editors mint links; nobody else.
	wp_set_current_user( $author );
	$minted = rest_do_request( new WP_REST_Request( 'POST', '/ohmylms/v1/lessons/' . $lessons['A'] . '/learner-preview' ) );
	$data   = $minted->get_data();
	ok( 200 === $minted->get_status() && false !== strpos( $data['url'], LessonPreview::QUERY . '=' . $lessons['A'] ) && false !== strpos( $data['bypass_url'], LessonPreview::BYPASS . '=1' ) && $data['expires'] > time() && 'publish' === $data['status'], 'the author gets both links: ' . wp_json_encode( $data ) );
	ok( 'private, no-store' === ( $minted->get_headers()['Cache-Control'] ?? '' ), 'the links are not cached' );
	foreach ( array( $learner, $other ) as $user ) {
		wp_set_current_user( $user );
		$refused = rest_do_request( new WP_REST_Request( 'POST', '/ohmylms/v1/lessons/' . $lessons['A'] . '/learner-preview' ) );
		ok( 403 === $refused->get_status() && 'ohmylms_lesson_preview_forbidden' === $refused->get_data()['code'], 'a learner gets no link' );
	}
	wp_set_current_user( $author );
	ok( 403 === rest_do_request( new WP_REST_Request( 'POST', '/ohmylms/v1/lessons/' . $course_id . '/learner-preview' ) )->get_status(), 'no preview link for a course' );
	wp_set_current_user( 0 );
	ok( 401 === rest_do_request( new WP_REST_Request( 'POST', '/ohmylms/v1/lessons/' . $lessons['A'] . '/learner-preview' ) )->get_status(), 'visitors get 401' );

	// In process: the preview of the enrolled editor renders a new learner's navigation and refuses their writes.
	$before_editor = rows_of( $editor );
	as_session(
		$se,
		static function () use ( $lessons, &$preview_state, &$navigation ) {
			$url = LessonPreview::url( $lessons['A'] );
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $_GET );
			view( $lessons['A'] );
			LessonPreview::start();
			ok( LessonPreview::active( $lessons['A'] ) && ! LessonPreview::bypassed(), 'the preview starts for the editor' );
			$navigation    = render( 'single-lesson/content-navigation.php' );
			$preview_state = wp_interactivity_state( 'ohmylms/lesson' );
			$_GET          = array();
		}
	);
	$context = json_decode( (string) attributes_of( $navigation, 'ohmylms-lesson-navigation' )['data-wp-context'], true );
	ok( false === $context['completed'] && false === $context['videoPending'] && false !== strpos( $context['nextUrl'], LessonPreview::QUERY . '=' . $lessons['B'] ), 'a new learner\'s context, the next lesson linked to its preview: ' . wp_json_encode( $context ) );
	ok( null !== attributes_of( $navigation, '#ohmylms-completed-lesson' ), 'the "Mark as complete" box is shown as to an enrolled learner' );
	ok( true === $preview_state['preview'] && empty( $preview_state['nonces']['complete'] ) && '' !== $preview_state['messages']['preview'], 'the module state marks the preview and carries no completion nonce' );
	LessonPreview::reset();
	ok( LessonPreview::previewing( $editor, $lessons['A'] ), 'the open preview is remembered for the write endpoints' );
	wp_set_current_user( $editor );
	$answer = call_ajax(
		'lesson_completed',
		$editor,
		array(
			'lesson_id' => (string) $lessons['A'],
			'nonce'     => wp_create_nonce( 'lesson_completed_nonce' ),
		)
	);
	ok( false === $answer['success'] && 'ohmylms_lesson_preview' === $answer['data']['code'] && ! Delivery::completed( $editor, $lessons['A'] ), 'completion is refused while previewing: ' . wp_json_encode( $answer ) );
	$answer = call_ajax(
		'save_video_progress',
		$editor,
		array(
			'lesson_id'        => (string) $lessons['A'],
			'watched_duration' => '30',
			'total_duration'   => '60',
			'last_position'    => '30',
			'nonce'            => wp_create_nonce( 'video_progress_nonce' ),
		)
	);
	ok( false === $answer['success'] && 'ohmylms_lesson_preview' === $answer['data']['code'], 'video progress is refused while previewing: ' . wp_json_encode( $answer ) );
	ok( array() === $fired && rows_of( $editor ) === $before_editor, 'no hook fired and no row was written: ' . wp_json_encode( $fired ) );

	// Live: the author's preview is the real template with the banner; nothing is recorded.
	ok( 0 === rows_of( $author ), 'the author starts without any learner row' );
	$link = static function ( array $session, $id, $bypass = false ) {
		return as_session(
			$session,
			static function () use ( $id, $bypass ) {
				return LessonPreview::url( $id, $bypass );
			}
		);
	};
	$page = http( $link( $sa, $lessons['A'] ), $sa['cookie'] );
	ok( 200 === $page['status'] && real_template( $page['body'] ), 'the author previews A in the learner template: ' . $page['status'] . ' ' . substr( wp_strip_all_tags( $page['body'] ), 0, 300 ) );
	ok( false !== strpos( $page['body'], 'ohmylms-lesson-preview-banner' ) && false !== strpos( $page['body'], 'Preview — nothing is recorded' ) && false !== strpos( $page['body'], 'Body of lesson A.' ), 'the banner and the lesson body' );
	$live = script_data( $page['body'], 'wp-script-module-data-@wordpress/interactivity' )['state']['ohmylms/lesson'] ?? array();
	ok( true === ( $live['preview'] ?? null ) && empty( $live['nonces']['complete'] ), 'the page state is a preview without a completion nonce: ' . wp_json_encode( $live ) );
	ok( false !== strpos( $page['body'], 'id="ohmylms-completed-lesson"' ), 'the author sees "Mark as complete" without being enrolled' );
	ok( false === strpos( $page['body'], '<meta name=\'robots\' content=\'index' ) && false !== strpos( $page['body'], 'noindex' ), 'preview pages are not indexed' );
	$draft_page = http( $link( $sa, $draft ), $sa['cookie'] );
	ok( 200 === $draft_page['status'] && false !== strpos( $draft_page['body'], 'Unpublished body.' ) && false !== strpos( $draft_page['body'], 'not published' ), 'a draft previews with the "not published" note: ' . $draft_page['status'] );

	// Locks as a new learner hits them, and the bypass.
	$locked = http( $link( $sa, $lessons['B'] ), $sa['cookie'] );
	ok( 200 === $locked['status'] && false !== strpos( $locked['body'], 'You must complete the prerequisite' ) && false !== strpos( $locked['body'], 'Preview — nothing is recorded' ) && ! real_template( $locked['body'] ), 'B stops at the prerequisite, with the banner: ' . substr( wp_strip_all_tags( $locked['body'] ), 0, 300 ) );
	ok( false !== strpos( $locked['body'], LessonPreview::BYPASS . '=1' ) && false !== strpos( html_entity_decode( $locked['body'] ), LessonPreview::QUERY . '=' . $lessons['A'] ), 'the lock offers the bypass and the prerequisite\'s own preview' );
	$open = http( $link( $sa, $lessons['B'], true ), $sa['cookie'] );
	ok( 200 === $open['status'] && real_template( $open['body'] ) && false !== strpos( $open['body'], 'Body of lesson B.' ) && false !== strpos( $open['body'], 'Locks are bypassed' ), 'the bypass opens B' );
	$dripped = http( $link( $sa, $lessons['C'] ), $sa['cookie'] );
	ok( 200 === $dripped['status'] && false !== strpos( $dripped['body'], 'This lesson will be available on' ) && false === strpos( $dripped['body'], 'Body of lesson C.' ), 'C shows the drip notice instead of its body' );
	$undripped = http( $link( $sa, $lessons['C'], true ), $sa['cookie'] );
	ok( false === strpos( $undripped['body'], 'This lesson will be available on' ) && false !== strpos( $undripped['body'], 'Body of lesson C.' ), 'the bypass opens C' );
	$video = http( $link( $sa, $lessons['D'] ), $sa['cookie'] );
	ok( false !== strpos( $video['body'], 'Watch at least 50% of the video' ), 'D waits for the video as for a new learner' );
	$video_state = script_data( $video['body'], 'wp-script-module-data-@wordpress/interactivity' )['state']['ohmylms/video'] ?? array();
	ok( empty( $video_state['nonces']['progress'] ) && false === strpos( $video['body'], '&quot;track&quot;:true' ) && false === strpos( $video['body'], '"track":true' ), 'the preview player tracks nothing' );
	$unvideo = http( $link( $sa, $lessons['D'], true ), $sa['cookie'] );
	ok( false === strpos( $unvideo['body'], 'Watch at least 50% of the video' ), 'the bypass lifts the video rule' );
	update_post_meta( $course_id, '_sequential_mode', 'yes' );
	$sequential = http( $link( $sa, $lessons['E'] ), $sa['cookie'] );
	ok( 200 === $sequential['status'] && false !== strpos( $sequential['body'], 'You must complete the previous lesson' ), 'E stops at the sequential lock: ' . $sequential['status'] . ' ' . substr( wp_strip_all_tags( $sequential['body'] ), 0, 300 ) );
	ok( real_template( http( $link( $sa, $lessons['E'], true ), $sa['cookie'] )['body'] ), 'the bypass opens E' );

	// Learners and other users never get in, nor past a lock.
	foreach ( array( $sl, $so ) as $session ) {
		$denied = http( $link( $sa, $lessons['E'], true ), $session['cookie'] );
		ok( 403 === $denied['status'] && ! real_template( $denied['body'] ), 'the author\'s bypass link is refused for user ' . $session['user'] . ': ' . $denied['status'] );
		$own = http( $link( $session, $lessons['E'], true ), $session['cookie'] );
		ok( 403 === $own['status'] && false !== strpos( $own['body'], 'You cannot preview this lesson.' ), 'a link signed for user ' . $session['user'] . ' is refused too' );
	}
	ok( 403 === http( $link( $sa, $lessons['E'] ), $se['cookie'] )['status'], 'another editor cannot use the author\'s link' );
	$visitor = http( $link( $sa, $lessons['E'], true ), '' );
	ok( 401 === $visitor['status'] && ! real_template( $visitor['body'] ), 'a visitor cannot use it: ' . $visitor['status'] );
	$real = http( get_permalink( $lessons['E'] ), $sl['cookie'] );
	ok( 403 === $real['status'] && false !== strpos( $real['body'], 'You must complete the previous lesson' ), 'the enrolled learner is still held by the sequential lock' );
	update_post_meta( $course_id, '_sequential_mode', 'no' );
	$expired_at = time() - 5;
	$expired    = as_session(
		$sa,
		static function () use ( $lessons, $expired_at ) {
			return LessonPreview::url( $lessons['A'], false, $expired_at );
		}
	);
	$expired    = http( $expired, $sa['cookie'] );
	ok( 403 === $expired['status'] && false !== strpos( $expired['body'], 'expired' ), 'an expired link is refused: ' . $expired['status'] );
	$tampered = http( add_query_arg( LessonPreview::NONCE, 'abc123', $link( $sa, $lessons['A'] ) ), $sa['cookie'] );
	ok( 403 === $tampered['status'] && false !== strpos( $tampered['body'], 'not valid' ), 'an invalid nonce is refused' );
	ok( 0 === rows_of( $author ), 'the previews enrolled and recorded nothing for the author' );

	// Live: the enrolled editor's completion is refused over HTTP while previewing, and works after opening normally.
	$before_editor = rows_of( $editor );
	ok( 200 === http( $link( $se, $lessons['A'] ), $se['cookie'] )['status'], 'the editor previews A over HTTP' );
	$nonce   = as_session(
		$se,
		static function () {
			return wp_create_nonce( 'lesson_completed_nonce' );
		}
	);
	$refused = json_decode(
		http(
			admin_url( 'admin-ajax.php' ),
			$se['cookie'],
			array(
				'action'    => 'ohmylms_lesson_completed',
				'lesson_id' => (string) $lessons['A'],
				'nonce'     => $nonce,
			)
		)['body'],
		true
	);
	ok( false === ( $refused['success'] ?? null ) && 'ohmylms_lesson_preview' === $refused['data']['code'] && rows_of( $editor ) === $before_editor, 'completing during the preview is refused over HTTP: ' . wp_json_encode( $refused ) );
	$normal = http( get_permalink( $lessons['A'] ), $se['cookie'] );
	wp_cache_flush();
	ok( 200 === $normal['status'] && ! LessonPreview::previewing( $editor, $lessons['A'] ), 'opening the lesson normally ends the preview' );
	$done = json_decode(
		http(
			admin_url( 'admin-ajax.php' ),
			$se['cookie'],
			array(
				'action'    => 'ohmylms_lesson_completed',
				'lesson_id' => (string) $lessons['A'],
				'nonce'     => $nonce,
			)
		)['body'],
		true
	);
	ok( true === ( $done['success'] ?? null ) && Delivery::completed( $editor, $lessons['A'] ), 'the enrolled editor completes A as a learner afterwards: ' . wp_json_encode( $done ) );
	$after = http( $link( $se, $lessons['A'] ), $se['cookie'] );
	$after = json_decode( (string) ( attributes_of( $after['body'], 'ohmylms-lesson-navigation' )['data-wp-context'] ?? '{}' ), true );
	ok( false === ( $after['completed'] ?? null ), 'the preview still shows A as not completed for the editor who completed it' );

	ok( ! $warnings, 'no PHP warnings from the plugin: ' . implode( ' | ', $warnings ) );
	fwrite( STDOUT, "OK lesson-preview-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	wp_set_current_user( 0 );
	LessonPreview::reset();
	foreach ( $sessions as $session ) {
		WP_Session_Tokens::get_instance( $session[0] )->destroy( $session[1] );
	}
	foreach ( $user_ids as $user_id ) {
		foreach ( array_unique( array_map( 'intval', $post_ids ) ) as $post_id ) {
			delete_transient( LessonPreview::MARKER . $user_id . '_' . $post_id );
		}
	}
	foreach ( array_unique( array_map( 'intval', $post_ids ) ) as $post_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'content_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'chapter_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'chapter_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $post_id ) );
		wp_delete_post( $post_id, true );
	}
	if ( $user_ids ) {
		$columns = $wpdb->get_results( $wpdb->prepare( "SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE %s AND COLUMN_NAME IN ('user_id','student_id','learner_id')", $wpdb->esc_like( $wpdb->prefix . 'ohmylms_' ) . '%' ) );
		$holders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		foreach ( (array) $columns as $column ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE %i IN ({$holders})", array_merge( array( $column->t, $column->c ), $user_ids ) ) );
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	restore_error_handler();
}

exit( $exit_code );
