<?php
/**
 * Lesson and assignment player (batch L6 of docs/LEARNER-FRONTEND.md): the lesson navigation, assignment and
 * sidebar templates render `ohmylms/lesson`, `ohmylms/assignment` and `ohmylms/tabs` directives and state, pre-L6
 * theme overrides are decorated the same way, "Mark as complete" completes once and is refused by the server
 * for a learner who is not enrolled, a dripped lesson, an unmet prerequisite, the sequential lock and quizzes,
 * the assignment timer is started and enforced by the server (refused after the deadline and its grace period),
 * content protection loads only when switched on, and lesson images are sized on the server. The live part
 * requests the lesson and assignment pages over HTTP as the enrolled learner, starts the timer, submits text with
 * a file through admin-ajax with the nonces the pages print, and completes a lesson.
 *
 * Runs against the Local site. It creates a tagged course, chapter, lessons, a quiz, a timed assignment and two
 * learners (one session token for the HTTP part), and removes them, their progress, attempts, uploaded file,
 * deadline options and the token in `finally`. The content protection option is restored.
 *
 * Usage (set OHMYLMS_LESSON_PLAYER_TEST=1):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/lesson-player-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.Security.NonceVerification, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_LESSON_PLAYER_TEST' ) ) {
	exit( "Set OHMYLMS_LESSON_PLAYER_TEST=1 to run the lesson player tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Assignment\Delivery;
use OhMyLMS\Extensions\Interactivity;
use OhMyLMS\Extensions\Lesson;
use OhMyLMS\Learning\LessonGate;

global $wpdb;

$checks     = 0;
$warnings   = array();
$post_ids   = array();
$user_ids   = array();
$files      = array();
$token      = null;
$tag        = 'lptest' . strtolower( wp_generate_password( 6, false, false ) );
$exit_code  = 0;
$protection = get_option( 'ohmylms_content_protection', null );

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
 * Temporary learner.
 *
 * @param string $suffix Suffix.
 * @return int
 */
function make_user( $suffix ) {
	global $user_ids, $tag;
	$id = wp_insert_user(
		array(
			'user_login'   => $tag . '_' . $suffix,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '_' . $suffix . '@example.invalid',
			'display_name' => $tag . ' ' . $suffix,
			'role'         => 'subscriber',
		)
	);
	ok( ! is_wp_error( $id ), "temporary learner $suffix" );
	$user_ids[] = (int) $id;
	return (int) $id;
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
			'post_status' => 'publish',
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
 * @param array  $args Arguments.
 * @return string
 */
function render( $name, $args = array() ) {
	ob_start();
	ohmylms_get_template( $name, $args );
	return (string) ob_get_clean();
}

/**
 * Attributes of the first element matching a class, `#id` or `name=value`.
 *
 * @param string $html     HTML.
 * @param string $selector Selector.
 * @return array|null
 */
function attributes_of( $html, $selector ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		if ( 0 === strpos( $selector, '#' ) ) {
			$match = substr( $selector, 1 ) === $tags->get_attribute( 'id' );
		} elseif ( false !== strpos( $selector, '=' ) ) {
			list( $name, $value ) = explode( '=', $selector, 2 );
			$match                = $value === $tags->get_attribute( $name );
		} else {
			$match = $tags->has_class( $selector );
		}
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
 * Assert that an element carries every attribute.
 *
 * @param string $html       HTML.
 * @param string $selector   Selector.
 * @param array  $attributes Name => value.
 * @param string $label      What is checked.
 */
function has( $html, $selector, array $attributes, $label ) {
	$found = attributes_of( $html, $selector );
	ok( null !== $found, "$label: no element matches $selector" );
	foreach ( $attributes as $name => $value ) {
		ok( array_key_exists( $name, $found ) && $value === $found[ $name ], "$label: expected $name=" . wp_json_encode( $value ) . ', got ' . wp_json_encode( $found ) );
	}
}

/**
 * Decoded `data-wp-context` of the first match.
 *
 * @param string $html     HTML.
 * @param string $selector Selector.
 * @return array
 */
function context_of( $html, $selector ) {
	$found = attributes_of( $html, $selector );
	return isset( $found['data-wp-context'] ) ? (array) json_decode( $found['data-wp-context'], true ) : array();
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
 * HTTP request to the site with the learner's cookies.
 *
 * @param string $url    URL.
 * @param string $cookie Cookie header.
 * @param array  $fields POST fields (a CURLFile uploads), null for GET.
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
 * Attempts of a learner on an assignment.
 *
 * @param int $assignment Assignment.
 * @param int $user       Learner.
 * @return array[]
 */
function attempts( $assignment, $user ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE assignment_id = %d AND user_id = %d', $wpdb->prefix . 'ohmylms_assignment_attempts', $assignment, $user ), ARRAY_A );
}

try {
	ok( ohmylms_is_pro(), 'assignments are available' );
	$fired = 0;
	add_action(
		'ohmylms_lesson_completed',
		static function () use ( &$fired ) {
			++$fired;
		}
	);

	// Modules: registered with the runtime and the SDK.
	Interactivity::register();
	$registered = wp_script_modules();
	foreach ( array( 'ohmylms/lesson', 'ohmylms/assignment', 'ohmylms/protection' ) as $module ) {
		wp_enqueue_script_module( $module );
	}
	ob_start();
	$registered->print_enqueued_script_modules();
	$printed = (string) ob_get_clean();
	foreach ( array( 'assets/interactivity/lesson.js', 'assets/interactivity/assignment.js', 'assets/interactivity/protection.js' ) as $file ) {
		ok( false !== strpos( $printed, $file ), "$file is a registered module" );
	}
	foreach ( array( 'ohmylms/lesson', 'ohmylms/assignment', 'ohmylms/protection' ) as $module ) {
		wp_dequeue_script_module( $module );
	}

	// Fixtures: A (reading) -> B (needs A) -> C (dripped) -> E -> assignment; a quiz outside the order.
	$course_id = make_post(
		array(
			'post_type'  => 'ohmylms-course',
			'post_title' => "$tag course",
		)
	);
	$chapter   = make_post(
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
	$lesson_a   = make_post(
		array(
			'post_type'    => 'ohmylms-lesson',
			'post_title'   => "$tag reading A",
			'post_content' => '<p>Read me.</p><p><img src="https://example.invalid/a.png" data-width="40" data-align="right" alt=""></p>',
		)
	);
	$lesson_b   = make_post(
		array(
			'post_type'  => 'ohmylms-lesson',
			'post_title' => "$tag lesson B",
		)
	);
	$lesson_c   = make_post(
		array(
			'post_type'  => 'ohmylms-lesson',
			'post_title' => "$tag dripped C",
		)
	);
	$lesson_e   = make_post(
		array(
			'post_type'  => 'ohmylms-lesson',
			'post_title' => "$tag lesson E",
		)
	);
	$assignment = make_post(
		array(
			'post_type'    => 'ohmylms-assignment',
			'post_title'   => "$tag essay",
			'post_content' => '<p>Write.</p>',
		)
	);
	$quiz       = make_post(
		array(
			'post_type'  => 'ohmylms-quiz',
			'post_title' => "$tag quiz",
		)
	);
	foreach ( array( $lesson_a, $lesson_b, $lesson_c, $lesson_e ) as $lesson ) {
		update_post_meta( $lesson, '_type', 'text' );
	}
	foreach ( array( $lesson_a, $lesson_b, $lesson_c, $lesson_e, $assignment ) as $order => $content ) {
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id'   => $chapter,
				'content_id'   => $content,
				'content_type' => $content === $assignment ? 'assignment' : 'text',
				'order_number' => $order + 1,
			)
		);
	}
	update_post_meta(
		$lesson_b,
		'_prerequisites',
		array(
			'enable' => true,
			'data'   => array(
				array(
					'value' => $lesson_a,
					'label' => "$tag reading A",
				),
			),
		)
	);
	update_post_meta(
		$lesson_c,
		'_drip_settings',
		array(
			'enable' => true,
			'type'   => 'enrollment-from-x-days',
			'days'   => 2,
			'date'   => '',
			'time'   => '',
		)
	);
	foreach (
		array(
			'_type'                   => 'assignment',
			'_enable_time_limit'      => '1',
			'_time_limit'             => '1',
			'_time_limit_type'        => 'day',
			'_allow_upload_files'     => '1',
			'_number_of_files'        => '3',
			'_enable_file_size_limit' => '1',
			'_max_file_size_limit'    => '1',
		) as $key => $value
	) {
		update_post_meta( $assignment, $key, $value );
	}
	$learner  = make_user( 'learner' );
	$stranger = make_user( 'stranger' );
	ok( true === \OhMyLMS\Student\Enrollments::enroll_manually( $learner, $course_id ), 'learner enrolled' );
	ok( LessonGate::course( $lesson_a, $learner ) === $course_id, 'the lesson resolves to its course' );

	// Navigation template: ohmylms/lesson root, checkbox, label, next link context and state.
	wp_set_current_user( $learner );
	view( $lesson_a );
	$navigation = render( 'single-lesson/content-navigation.php' );
	has(
		$navigation,
		'ohmylms-lesson-navigation',
		array(
			'data-wp-interactive' => 'ohmylms/lesson',
			'data-wp-init'        => 'callbacks.listen',
		),
		'navigation root'
	);
	$context = context_of( $navigation, 'ohmylms-lesson-navigation' );
	ok( $lesson_a === $context['lessonId'] && false === $context['completed'] && false === $context['busy'] && false === $context['videoPending'] && get_permalink( $lesson_b ) === $context['nextUrl'], 'navigation context: ' . wp_json_encode( $context ) );
	has(
		$navigation,
		'#ohmylms-completed-lesson',
		array(
			'data-wp-on--change'     => 'actions.complete',
			'data-wp-bind--checked'  => 'context.completed',
			'data-wp-bind--disabled' => 'state.completeDisabled',
		),
		'complete checkbox'
	);
	ok( ! array_key_exists( 'aria-labelledby', attributes_of( $navigation, '#ohmylms-completed-lesson' ) ), 'the checkbox is named by its label, not by the hidden tick' );
	has( $navigation, 'ohmylms-checkbox', array( 'data-wp-on--keydown' => 'actions.checkboxKey' ), 'checkbox label' );
	has( $navigation, 'ohmylms-checkbox-label', array( 'data-wp-text' => 'state.completeLabel' ), 'checkbox label text' );
	$lesson_state = wp_interactivity_state( 'ohmylms/lesson' );
	ok( wp_verify_nonce( $lesson_state['nonces']['complete'], 'lesson_completed_nonce' ) && 'Completed' === $lesson_state['messages']['completed'], 'lesson state: the completion nonce and labels' );
	ok( in_array( 'ohmylms/lesson', wp_script_modules()->get_queue(), true ), 'the lesson module is enqueued' );

	// A pre-L6 override of the navigation is decorated the same way.
	$legacy    = '<div class="ohmylms-lesson-navigation"><div class="default-navigation"><input type="hidden" id="ohmylms-lesson-id" value="' . $lesson_a . '"><label for="ohmylms-completed-lesson" class="ohmylms-checkbox" tabindex="0"><input type="checkbox" name="lesson-completed" id="ohmylms-completed-lesson" aria-required="true"><span class="ohmylms-checkbox-text">Mark as Complete</span></label><a class="next-lesson ohmylms-button" href="#">Next Lesson</a></div></div>';
	$decorated = Lesson::template( $legacy, 'single-lesson/content-navigation.php' );
	has( $decorated, 'ohmylms-lesson-navigation', array( 'data-wp-interactive' => 'ohmylms/lesson' ), 'override root' );
	$legacy_context = context_of( $decorated, 'ohmylms-lesson-navigation' );
	ok( $legacy_context['lessonId'] === $lesson_a, 'override context comes from the current post' );
	has( $decorated, '#ohmylms-completed-lesson', array( 'data-wp-on--change' => 'actions.complete' ), 'override checkbox' );
	has( $decorated, 'ohmylms-checkbox', array( 'data-wp-on--keydown' => 'actions.checkboxKey' ), 'override label' );
	ok( Lesson::template( $navigation, 'single-lesson/content-navigation.php' ) === $navigation, 'current markup is not decorated twice' );

	// Sidebar tabs on ohmylms/tabs, in the template and in an override.
	$sidebar = render( 'single-lesson/sidebar/sidebar.php' );
	has(
		$sidebar,
		'ohmylms-lesson-sidebar',
		array(
			'data-wp-interactive' => 'ohmylms/tabs',
			'data-wp-context'     => '{"activeTab":"lesson-content"}',
		),
		'sidebar root'
	);
	has(
		$sidebar,
		'data-target=#lesson-content',
		array(
			'role'                  => 'tab',
			'data-wp-on--click'     => 'actions.select',
			'data-wp-class--active' => 'state.selected',
		),
		'sidebar tab'
	);
	has( $sidebar, '#lesson-content', array( 'role' => 'tabpanel' ), 'sidebar panel' );
	$old_sidebar = Lesson::template( '<aside class="ohmylms-lesson-sidebar"><ul class="ohmylms-lesson-tab-nav"><li class="active" data-target="#lesson-content">Course Content</li></ul><div class="ohmylms-lesson-tab-content"><div class="ohmylms-lesson-single-tab-content lesson-content active" id="lesson-content"></div></div></aside>', 'single-lesson/sidebar/sidebar.php' );
	has(
		$old_sidebar,
		'data-target=#lesson-content',
		array(
			'role'                   => 'tab',
			'aria-controls'          => 'lesson-content',
			'aria-selected'          => 'true',
			'data-wp-context'        => '{"tabKey":"lesson-content"}',
			'data-wp-bind--tabindex' => 'state.tabIndex',
		),
		'override tab'
	);
	has( $old_sidebar, '#lesson-content', array( 'data-wp-context' => '{"tabKey":"lesson-content"}' ), 'override panel' );

	// Content images are sized on the server.
	$content = render( 'single-lesson/content-lesson.php' );
	$image   = attributes_of( $content, 'data-align=right' );
	ok( $image && false !== strpos( $image['style'], 'width:40% !important' ) && false !== strpos( $image['style'], 'margin-left:auto;margin-right:0' ), 'sized image: ' . wp_json_encode( $image ) );
	ok( false === strpos( Lesson::images( '<img data-width="40%;color:red" data-align="right">' ), 'style=' ), 'invalid widths are left alone' );

	// "Mark as complete": refused where the lesson page would refuse, completed once otherwise.
	$nonce  = wp_create_nonce( 'lesson_completed_nonce' );
	$answer = call_ajax(
		'lesson_completed',
		$learner,
		array(
			'lesson_id' => (string) $lesson_b,
			'nonce'     => $nonce,
		)
	);
	ok( false === $answer['success'] && 'ohmylms_lesson_prerequisites' === $answer['data']['code'] && false !== strpos( $answer['data']['message'], "$tag reading A" ), 'an unmet prerequisite is refused: ' . wp_json_encode( $answer ) );
	$answer = call_ajax(
		'lesson_completed',
		$learner,
		array(
			'lesson_id' => (string) $lesson_c,
			'nonce'     => $nonce,
		)
	);
	ok( false === $answer['success'] && 'ohmylms_lesson_not_open' === $answer['data']['code'] && 0 === strpos( $answer['data']['message'], 'This lesson will be available on ' ), 'a dripped lesson is refused: ' . wp_json_encode( $answer ) );
	$answer = call_ajax(
		'lesson_completed',
		$learner,
		array(
			'lesson_id' => (string) $quiz,
			'nonce'     => $nonce,
		)
	);
	ok( false === $answer['success'] && 'ohmylms_lesson_not_completable' === $answer['data']['code'], 'a quiz cannot be completed by the button' );
	wp_set_current_user( $stranger );
	$answer = call_ajax(
		'lesson_completed',
		$stranger,
		array(
			'lesson_id' => (string) $lesson_a,
			'nonce'     => wp_create_nonce( 'lesson_completed_nonce' ),
		)
	);
	ok( false === $answer['success'] && 'ohmylms_lesson_not_enrolled' === $answer['data']['code'], 'a learner who is not enrolled is refused' );
	ok( 0 === $fired && ! Delivery::completed( $learner, $lesson_b ) && ! Delivery::completed( $learner, $lesson_c ), 'nothing was completed by the refusals' );
	wp_set_current_user( $learner );
	$answer = call_ajax(
		'lesson_completed',
		$learner,
		array(
			'lesson_id' => (string) $lesson_a,
			'nonce'     => $nonce,
		)
	);
	ok( true === $answer['success'] && get_permalink( $lesson_b ) === $answer['data']['next_content_link'] && Delivery::completed( $learner, $lesson_a ) && 1 === $fired, 'lesson A completes and answers the next link: ' . wp_json_encode( $answer ) );
	$answer = call_ajax(
		'lesson_completed',
		$learner,
		array(
			'lesson_id' => (string) $lesson_a,
			'nonce'     => $nonce,
		)
	);
	ok( true === $answer['success'] && true === $answer['data']['already_completed'] && 1 === $fired, 'completing again answers success once more without firing the hooks again' );
	ok( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i p INNER JOIN %i e ON e.id = p.enrollment_id WHERE e.user_id = %d AND p.content_id = %d', $wpdb->prefix . 'ohmylms_user_progress', $wpdb->prefix . 'ohmylms_user_enrollment', $learner, $lesson_a ) ), 'one progress row' );
	$gate = LessonGate::check_completion( $lesson_b, $learner );
	ok( $course_id === $gate, 'the prerequisite is met now: ' . ( is_wp_error( $gate ) ? $gate->get_error_code() : wp_json_encode( array( $gate, $course_id ) ) ) );
	view( $lesson_a );
	ok( true === Lesson::navigation_context( $lesson_a )['completed'], 'the navigation context shows the lesson completed' );

	// Assignment template: ohmylms/assignment root, panel, timer, form, files and state.
	view( $assignment );
	$page = render( 'single-lesson/content-assignment.php' );
	has(
		$page,
		'content-type-assignment',
		array(
			'data-wp-interactive' => 'ohmylms/assignment',
			'data-wp-init'        => 'callbacks.mount',
		),
		'assignment root'
	);
	has( $page, 'ohmylms-assignment-submit', array( 'data-wp-style--display' => 'state.panelDisplay' ), 'submit panel' );
	has(
		$page,
		'submission-time-limit',
		array(
			'role'                   => 'timer',
			'data-wp-style--display' => 'state.timerDisplay',
		),
		'timer'
	);
	has( $page, 'timer-display', array( 'data-wp-text' => 'state.timeLabel' ), 'timer text' );
	has( $page, 'progress-inner', array( 'data-wp-style--width' => 'state.timerWidth' ), 'timer bar' );
	has( $page, 'ohmylms-assignment-form', array( 'data-wp-on--submit' => 'actions.submit' ), 'form' );
	has(
		$page,
		'#submission-file',
		array(
			'data-wp-on--change'     => 'actions.pick',
			'data-wp-bind--disabled' => 'state.locked',
		),
		'file input'
	);
	has( $page, 'ohmylms-file-upload', array( 'data-wp-on--keydown' => 'actions.pickKey' ), 'drop zone' );
	has( $page, 'data-wp-each--file=state.files', array( 'data-wp-each-key' => 'context.file.key' ), 'file list template' );
	has( $page, 'remove-resource-action', array( 'data-wp-on--click' => 'actions.remove' ), 'remove button' );
	has( $page, 'assignment-submit-alert', array( 'role' => 'alert' ), 'error region' );
	has( $page, 'type=submit', array( 'data-wp-bind--disabled' => 'state.submitDisabled' ), 'submit button' );
	has( $page, '#ohmylms-submission-body', array( 'name' => 'submission-body' ), 'labelled text field' );
	has( $page, 'deadline', array( 'hidden' => true ), 'no deadline before the timer starts' );
	$state = wp_interactivity_state( 'ohmylms/assignment' );
	ok( $assignment === $state['assignmentId'] && DAY_IN_SECONDS === $state['timeLimit'] && 0 === $state['deadline'] && DAY_IN_SECONDS === $state['remaining'] && MB_IN_BYTES === $state['maxBytes'] && abs( $state['serverNow'] - time() * 1000 ) < 60000, 'assignment state: ' . wp_json_encode( array_diff_key( $state, array( 'messages' => 1 ) ) ) );
	ok( wp_verify_nonce( $state['nonces']['submit'], 'assignment_submission_nonce' ) && wp_verify_nonce( $state['nonces']['start'], 'save_remaining_time' ), 'assignment state: the submit and start nonces' );
	$assignment_nav = render( 'single-lesson/content-navigation.php' );
	ok( null !== attributes_of( $assignment_nav, 'start-submit-assignment' ), 'start button rendered: ' . wp_json_encode( Delivery::state( $assignment, $learner ) ) . substr( wp_strip_all_tags( $assignment_nav ), 0, 300 ) );
	has(
		$assignment_nav,
		'start-submit-assignment',
		array(
			'data-wp-on--click'    => 'ohmylms/assignment::actions.start',
			'data-wp-bind--hidden' => 'ohmylms/assignment::state.started',
		),
		'start button'
	);

	// A pre-L6 assignment override keeps its markup and gets the directives.
	$old_assignment = Lesson::template( '<div class="ohmylms-lesson-content-body content-type-assignment"><div class="ohmylms-assignment-submit" style="display: none"><div class="ohmylms-timer submission-time-limit" style="display: none;"><span class="timer-display" data-timer="1440"></span><span class="progress-inner"></span></div><form class="ohmylms-assignment-form"><textarea name="submission-body"></textarea><label class="ohmylms-file-upload" tabindex="0"><input id="submission-file" type="file" name="ohmylms-submission-file"></label><p class="assignment-submit-alert"><svg></svg><span>Add necessary error message</span></p><button class="ohmylms-button" type="submit" disabled>Submit</button></form></div></div>', 'single-lesson/content-assignment.php' );
	has( $old_assignment, 'content-type-assignment', array( 'data-wp-interactive' => 'ohmylms/assignment' ), 'override assignment root' );
	has( $old_assignment, 'ohmylms-assignment-form', array( 'data-wp-on--submit' => 'actions.submit' ), 'override form' );
	has( $old_assignment, '#submission-file', array( 'data-wp-on--change' => 'actions.pick' ), 'override file input' );
	has( $old_assignment, 'type=submit', array( 'data-wp-bind--disabled' => 'state.submitDisabled' ), 'override submit' );
	ok( false !== strpos( $old_assignment, '<span data-wp-text="state.error">' ), 'override error text' );

	// The timer is started and enforced by the server.
	wp_set_current_user( $stranger );
	$start = call_ajax(
		'save_remaining_time',
		$stranger,
		array(
			'content_id' => (string) $assignment,
			'type'       => 'assignment',
			'nonce'      => wp_create_nonce( 'save_remaining_time' ),
		)
	);
	ok( false === $start['success'] && 'ohmylms_assignment_not_enrolled' === $start['data']['code'] && ! get_option( Delivery::deadline_key( $assignment, $stranger ) ), 'the timer of a learner who is not enrolled does not start' );
	wp_set_current_user( $learner );
	$start_nonce = wp_create_nonce( 'save_remaining_time' );
	$start       = call_ajax(
		'save_remaining_time',
		$learner,
		array(
			'content_id' => (string) $assignment,
			'type'       => 'assignment',
			'nonce'      => $start_nonce,
		)
	);
	$deadline    = strtotime( (string) $start['data']['deadline'] );
	ok( true === $start['success'] && abs( $deadline - ( time() + DAY_IN_SECONDS ) ) < 60 && $start['data']['remaining'] > DAY_IN_SECONDS - 60 && '' !== $start['data']['deadline_label'], 'the timer starts with a one-day deadline: ' . wp_json_encode( $start ) );
	$again = call_ajax(
		'save_remaining_time',
		$learner,
		array(
			'content_id' => (string) $assignment,
			'type'       => 'assignment',
			'nonce'      => $start_nonce,
		)
	);
	ok( $start['data']['deadline'] === $again['data']['deadline'], 'starting again never adds time' );
	$timer = Delivery::timer( $assignment, $learner );
	ok( true === $timer['enabled'] && DAY_IN_SECONDS === $timer['limit'] && false === $timer['expired'] && false === $timer['closed'], 'timer while running: ' . wp_json_encode( $timer ) );
	ok( strtotime( $timer['deadline'] ) * 1000 === Lesson::assignment_state( $assignment )['deadline'], 'the page state carries the stored deadline' );
	$key = Delivery::deadline_key( $assignment, $learner );
	$at  = static function ( $offset ) use ( $key ) {
		update_option( $key, ( new DateTimeImmutable( '@' . ( time() + $offset ) ) )->setTimezone( \OhMyLMS\Learning\DripSchedule::timezone() )->format( 'Y-m-d H:i:s' ), false );
	};
	$at( -30 );
	$timer = Delivery::timer( $assignment, $learner );
	ok( true === $timer['expired'] && false === $timer['closed'] && true === Delivery::state( $assignment, $learner )['can_submit'], 'within the grace period a submission sent at zero is still accepted' );
	$at( -( Delivery::TIMER_GRACE + 30 ) );
	$state = Delivery::state( $assignment, $learner );
	ok( false === $state['can_submit'] && 'time_expired' === $state['reason'] && false !== strpos( $state['message'], 'ran out' ) && true === $state['timer']['closed'], 'after the grace period the gate is closed: ' . wp_json_encode( $state ) );
	$refused = call_ajax(
		'save_assignment_submission_file',
		$learner,
		array(
			'assignment_id'   => (string) $assignment,
			'course_id'       => (string) $course_id,
			'submission-body' => 'Too late',
			'nonce'           => wp_create_nonce( 'assignment_submission_nonce' ),
		)
	);
	ok( false === $refused['success'] && 'ohmylms_assignment_time_expired' === $refused['data']['code'] && array() === attempts( $assignment, $learner ), 'the server refuses a submission after the time: ' . wp_json_encode( $refused ) );
	$restart = call_ajax(
		'save_remaining_time',
		$learner,
		array(
			'content_id' => (string) $assignment,
			'type'       => 'assignment',
			'nonce'      => $start_nonce,
		)
	);
	ok( false === $restart['success'] && 'ohmylms_assignment_time_expired' === $restart['data']['code'], 'an expired timer cannot be restarted' );
	$rest = rest_do_request( new WP_REST_Request( 'GET', "/ohmylms/v1/assignments/$assignment/learner-state" ) );
	ok( 200 === $rest->get_status() && 'time_expired' === $rest->get_data()['reason'] && true === $rest->get_data()['timer']['expired'], 'the learner-state route reports the expiry the module locks on' );
	view( $assignment );
	ob_start();
	ohmylms_get_template( 'single-lesson/content-navigation.php' );
	$expired_nav = (string) ob_get_clean();
	ok( null === attributes_of( $expired_nav, 'start-submit-assignment' ) && null !== attributes_of( $expired_nav, 'start-not-submit-assignment' ), 'the page offers no Start button after the time' );
	$at( DAY_IN_SECONDS );

	// Live: the pages as the enrolled learner, then start, submit (text and file) and complete over HTTP.
	$manager   = WP_Session_Tokens::get_instance( $learner );
	$token     = $manager->create( time() + HOUR_IN_SECONDS );
	$expires   = time() + HOUR_IN_SECONDS;
	$cookie    = AUTH_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $learner, $expires, 'auth', $token ) ) . '; ' . LOGGED_IN_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $learner, $expires, 'logged_in', $token ) );
	$lesson_ui = http( get_permalink( $lesson_b ), $cookie );
	ok( 200 === $lesson_ui['status'] && false !== strpos( $lesson_ui['body'], 'data-wp-interactive="ohmylms/lesson"' ), 'lesson page answers 200 with the lesson island: ' . $lesson_ui['status'] );
	file_put_contents( get_temp_dir() . "$tag-lesson.html", $lesson_ui['body'] );
	$files[] = get_temp_dir() . "$tag-lesson.html";
	ok( false !== strpos( $lesson_ui['body'], 'assets/interactivity/lesson.js' ), 'the lesson module is on the page' );
	$live_state = script_data( $lesson_ui['body'], 'wp-script-module-data-@wordpress/interactivity' );
	$live_nonce = $live_state['state']['ohmylms/lesson']['nonces']['complete'] ?? '';
	ok( '' !== $live_nonce, 'the page prints the completion nonce in the lesson state' );
	$completed = http(
		admin_url( 'admin-ajax.php' ),
		$cookie,
		array(
			'action'    => 'ohmylms_lesson_completed',
			'lesson_id' => (string) $lesson_b,
			'nonce'     => $live_nonce,
		)
	);
	$completed = json_decode( $completed['body'], true );
	ok( true === ( $completed['success'] ?? null ) && get_permalink( $lesson_c ) === $completed['data']['next_content_link'] && Delivery::completed( $learner, $lesson_b ), 'lesson B completed over HTTP with the page nonce: ' . wp_json_encode( $completed ) );

	delete_option( $key );
	// The query form routes even when the site's assignment base was changed without flushing the rewrite rules.
	$assignment_url = add_query_arg( 'ohmylms-assignment', get_post_field( 'post_name', $assignment ), home_url( '/' ) );
	$assignment_ui  = http( $assignment_url, $cookie );
	ok( 200 === $assignment_ui['status'] && false !== strpos( $assignment_ui['body'], 'data-wp-interactive="ohmylms/assignment"' ) && false !== strpos( $assignment_ui['body'], 'assets/interactivity/assignment.js' ), 'assignment page answers 200 with the assignment island and module: ' . $assignment_ui['status'] . ' ' . $assignment_url );
	$live_state = script_data( $assignment_ui['body'], 'wp-script-module-data-@wordpress/interactivity' )['state']['ohmylms/assignment'] ?? array();
	ok( isset( $live_state['assignmentId'] ) && $live_state['assignmentId'] === $assignment && DAY_IN_SECONDS === $live_state['timeLimit'], 'the assignment page prints the assignment state' );
	$sdk = script_data( $assignment_ui['body'], 'wp-script-module-data-ohmylms/interactivity' );
	ok( admin_url( 'admin-ajax.php' ) === ( $sdk['ajaxUrl'] ?? '' ), 'the SDK config carries admin-ajax for the modules' );
	ok( false === strpos( $assignment_ui['body'], 'ohmylms-screenshot-protection' ), 'no protection overlay while the option is off' );
	$started = json_decode(
		http(
			admin_url( 'admin-ajax.php' ),
			$cookie,
			array(
				'action'     => 'ohmylms_save_remaining_time',
				'content_id' => (string) $assignment,
				'type'       => 'assignment',
				'nonce'      => $live_state['nonces']['start'],
			)
		)['body'],
		true
	);
	ok( true === ( $started['success'] ?? null ) && '' !== (string) $started['data']['deadline'], 'the timer starts over HTTP: ' . wp_json_encode( $started ) );
	$upload  = wp_tempnam( "$tag-essay.txt" );
	$files[] = $upload;
	file_put_contents( $upload, "My essay about rivers.\n" );
	$fields    = array(
		'action'                  => 'ohmylms_save_assignment_submission_file',
		'nonce'                   => $live_state['nonces']['submit'],
		'assignment_id'           => (string) $assignment,
		'ohmylms_assignment_id'   => (string) $assignment,
		'course_id'               => (string) $course_id,
		'submission-body'         => 'Rivers carry water to the sea.',
		'ohmylms-submission-file' => new CURLFile( $upload, 'text/plain', "$tag-essay.txt" ),
	);
	$submitted = json_decode( http( admin_url( 'admin-ajax.php' ), $cookie, $fields )['body'], true );
	$rows      = attempts( $assignment, $learner );
	ok( true === ( $submitted['success'] ?? null ) && get_permalink( $assignment ) === $submitted['data']['redirect_url'] && 1 === count( $rows ), 'text and file submitted over HTTP: ' . wp_json_encode( $submitted ) );
	$stored = maybe_unserialize( $rows[0]['files'] );
	ok( 'Rivers carry water to the sea.' === $rows[0]['content'] && is_file( $stored['file'] ) && 'submitted' === $rows[0]['status'] && $course_id === (int) $rows[0]['course_id'], 'the attempt keeps the text and the uploaded file' );
	$files[]                           = $stored['file'];
	$fields['ohmylms-submission-file'] = new CURLFile( $upload, 'text/plain', "$tag-essay-2.txt" );
	$second                            = json_decode( http( admin_url( 'admin-ajax.php' ), $cookie, $fields )['body'], true );
	ok( true === ( $second['success'] ?? null ) && 2 === count( attempts( $assignment, $learner ) ), 'a second attempt (Try Again) is accepted while files remain' );
	$files[] = maybe_unserialize( attempts( $assignment, $learner )[1]['files'] )['file'];
	$at( -( Delivery::TIMER_GRACE + 30 ) );
	$fields['ohmylms-submission-file'] = new CURLFile( $upload, 'text/plain', "$tag-essay-3.txt" );
	$late                              = json_decode( http( admin_url( 'admin-ajax.php' ), $cookie, $fields )['body'], true );
	ok( false === ( $late['success'] ?? null ) && 'ohmylms_assignment_time_expired' === $late['data']['code'] && 2 === count( attempts( $assignment, $learner ) ), 'after the time the upload is refused over HTTP too: ' . wp_json_encode( $late ) );
	$rest_live = http( rest_url( "ohmylms/v1/assignments/$assignment/learner-state" ), $cookie, null, array( 'X-WP-Nonce: ' . $sdk['restNonce'] ) );
	$rest_live = json_decode( $rest_live['body'], true );
	ok( 'time_expired' === ( $rest_live['reason'] ?? '' ), 'the learner-state route answers the expiry over HTTP: ' . wp_json_encode( $rest_live ) );

	// Sequential mode: E follows the dripped C.
	update_post_meta( $course_id, '_sequential_mode', 'yes' );
	wp_set_current_user( $learner );
	$locked = LessonGate::check_completion( $lesson_e, $learner );
	ok( is_wp_error( $locked ) && 'ohmylms_lesson_locked' === $locked->get_error_code(), 'the sequential lock refuses completing E before C' );
	delete_option( $key );
	ok( 'locked' === Delivery::state( $assignment, $learner )['reason'], 'and refuses submitting the assignment after E' );
	update_post_meta( $course_id, '_sequential_mode', 'no' );

	// Content protection: only when switched on, on content pages.
	view( $assignment );
	update_option( 'ohmylms_content_protection', 'no' );
	ob_start();
	Lesson::protection();
	ok( '' === ob_get_clean() && ! in_array( 'ohmylms-content-protected', Lesson::body_class( array() ), true ), 'protection is off by default' );
	update_option( 'ohmylms_content_protection', 'yes' );
	ok( Lesson::protected_page() && in_array( 'ohmylms-content-protected', Lesson::body_class( array() ), true ), 'protected content pages get the print class' );
	ob_start();
	Lesson::protection();
	$overlay = (string) ob_get_clean();
	has(
		$overlay,
		'ohmylms-screenshot-protection',
		array(
			'data-wp-interactive' => 'ohmylms/protection',
			'data-wp-init'        => 'callbacks.install',
			'aria-hidden'         => 'true',
		),
		'protection overlay'
	);
	ok( in_array( 'ohmylms/protection', wp_script_modules()->get_queue(), true ), 'the protection module is enqueued' );
	$protected_page = http( $assignment_url, $cookie );
	ok( false !== strpos( $protected_page['body'], 'assets/interactivity/protection.js' ) && false !== strpos( $protected_page['body'], 'ohmylms-content-protected' ), 'a protected page loads the module and the print rule' );

	ok( ! $warnings, 'no PHP warnings from the plugin: ' . implode( ' | ', $warnings ) );
	fwrite( STDOUT, "OK lesson-player-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	wp_set_current_user( 0 );
	if ( null === $protection ) {
		delete_option( 'ohmylms_content_protection' );
	} else {
		update_option( 'ohmylms_content_protection', $protection );
	}
	if ( $token && $user_ids ) {
		WP_Session_Tokens::get_instance( $user_ids[0] )->destroy( $token );
	}
	foreach ( $user_ids as $user_id ) {
		foreach ( array_unique( array_map( 'intval', $post_ids ) ) as $post_id ) {
			delete_option( Delivery::deadline_key( $post_id, $user_id ) );
		}
	}
	foreach ( array_unique( array_map( 'intval', $post_ids ) ) as $post_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'content_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'chapter_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'chapter_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_assignment_attempts', array( 'assignment_id' => $post_id ) );
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
	foreach ( $files as $file ) {
		if ( $file && is_file( $file ) ) {
			wp_delete_file( $file );
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	restore_error_handler();
}

exit( $exit_code );
