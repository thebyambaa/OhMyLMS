<?php
/**
 * Lesson settings integration test: video settings and the watched-percentage rule, inline questions,
 * lesson objectives, and drip release through the shared schedule.
 *
 * Runs against the Local site. It creates tagged lessons, a course, a chapter, questions, an image
 * attachment and temporary users, and removes them in `finally`. Objective mappings use an existing
 * teaching syllabus read-only (the site must have one with objectives); only the test lesson changes.
 *
 * Usage (set OHMYLMS_LESSON_SETTINGS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/lesson-settings-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions, WordPress.Security.NonceVerification

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_LESSON_SETTINGS_TEST' ) ) {
	exit( "Set OHMYLMS_LESSON_SETTINGS_TEST=1 to run the lesson settings tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Curriculum\CourseObjectives;
use OhMyLMS\Learning\DripSchedule;
use OhMyLMS\Learning\LessonAuthoring;
use OhMyLMS\Learning\VideoCompletion;

global $wpdb;

$checks    = 0;
$post_ids  = array();
$user_ids  = array();
$files     = array();
$questions = array();
$tag       = 'lstest' . strtolower( wp_generate_password( 6, false, false ) );


/**
 * Assert a condition.
 *
 * @param mixed  $value   Value.
 * @param string $message Message.
 * @throws RuntimeException When falsy.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * REST request below /ohmylms/v1.
 *
 * @param string $method Method.
 * @param string $path   Path.
 * @param array  $data   Body or query.
 * @return WP_REST_Response
 */
function rest( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Insert a tagged post.
 *
 * @param array $args Fields.
 * @return int
 */
function make_post( array $args ) {
	global $post_ids;
	$id = wp_insert_post( $args, true );
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
	ok( ! is_wp_error( $id ), "temporary $role" );
	$user_ids[] = (int) $id;
	return (int) $id;
}

/**
 * The typed save payload of a lesson read.
 *
 * @param array $lesson Read lesson.
 * @return array
 */
function payload( array $lesson ) {
	$video = $lesson['video'];
	unset( $video['poster_url'] );
	return array(
		'revision'        => $lesson['revision'],
		'title'           => $lesson['title'],
		'content'         => $lesson['content'],
		'type'            => $lesson['type'],
		'external_url'    => $lesson['external_url'],
		'preview_enable'  => $lesson['preview_enable'],
		'enable_comments' => $lesson['enable_comments'],
		'resources'       => $lesson['resources'],
		'video'           => $video,
	);
}

/**
 * Run an AJAX handler and decode its JSON.
 *
 * @param callable $handler Handler.
 * @param array    $post    POST fields.
 * @return array|null
 * @throws RuntimeException When the handler fails other than by finishing its response.
 */
function ajax( $handler, array $post ) {
	$_POST    = $post;
	$_REQUEST = $post;
	$doing    = static function () {
		return true;
	};
	$die      = static function () {
		return static function () {
			throw new RuntimeException( 'ohmylms-ajax-done' );
		};
	};
	add_filter( 'wp_doing_ajax', $doing );
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	try {
		call_user_func( $handler );
	} catch ( RuntimeException $done ) {
		if ( 'ohmylms-ajax-done' !== $done->getMessage() ) {
			throw $done;
		}
	} finally {
		$output = ob_get_clean();
		remove_filter( 'wp_doing_ajax', $doing );
		remove_filter( 'wp_die_ajax_handler', $die );
		$_POST    = array();
		$_REQUEST = array();
	}
	return json_decode( (string) $output, true );
}

add_filter(
	'pre_wp_mail',
	static function () {
		return true;
	}
);

try {
	$admins  = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'fields'  => 'ID',
			'orderby' => 'ID',
		)
	);
	$admin   = (int) $admins[0];
	$author  = make_user( 'author', 'author' );
	$learner = make_user( 'learner', 'subscriber' );

	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/authoring/lessons/(?P<id>\d+)/objectives', '/authoring/lessons/(?P<id>\d+)/objective-options' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), "route $route" );
	}

	// Questions for inline checks: one approved, one not yet approved.
	$approved = make_post(
		array(
			'post_type'   => 'ohmylms-question',
			'post_title'  => "$tag approved check",
			'post_status' => 'publish',
		)
	);
	$draft_q  = make_post(
		array(
			'post_type'   => 'ohmylms-question',
			'post_title'  => "$tag draft check",
			'post_status' => 'publish',
		)
	);
	$identity_a  = \OhMyLMS\QuestionBank\VersionPublisher::identity( $approved );
	$identity_b  = \OhMyLMS\QuestionBank\VersionPublisher::identity( $draft_q );
	$questions   = array( $approved, $draft_q );
	$qb          = \OhMyLMS\Assessment\Schema::table( 'qb_questions' );
	$wpdb->update( $qb, array( 'approved_version_id' => 1 ), array( 'question_id' => $approved ) );
	$ghost_uuid  = wp_generate_uuid4();
	$content     = '<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->' . "\n\n"
		. '<!-- wp:ohmylms/shared-multiple-choice ' . wp_json_encode(
			array(
				'questionId' => $draft_q,
				'uuid'       => $identity_b['uuid'],
			)
		) . ' /-->' . "\n\n"
		. '<!-- wp:paragraph --><p>[ohmylms_question uuid="' . $identity_a['uuid'] . '"] inline</p><!-- /wp:paragraph -->' . "\n\n"
		. '<!-- wp:ohmylms/shared-multiple-choice {"questionId":0,"uuid":"' . $ghost_uuid . '"} /-->';

	// A video lesson through the typed API, as the administrator.
	wp_set_current_user( $admin );
	$created = rest(
		'POST',
		'/authoring/lessons',
		array(
			'title'      => "$tag video lesson",
			'request_id' => wp_generate_uuid4(),
		)
	);
	ok( 200 === $created->get_status(), 'lesson created: ' . wp_json_encode( $created->get_data() ) );
	$lesson     = (int) $created->get_data()['id'];
	$post_ids[] = $lesson;
	update_post_meta(
		$lesson,
		'_video_settings',
		array(
			'remove_branding' => true,
			'width'           => 80,
		)
	);
	$read = rest( 'GET', "/authoring/lessons/$lesson" )->get_data();
	ok(
		array(
			'provider'      => 'self-hosted',
			'duration'      => 0,
			'completion'    => 'none',
			'watch_percent' => 90,
			'poster_id'     => 0,
			'autoplay'      => false,
			'loop'          => false,
			'controls'      => true,
			'aspect_ratio'  => '16/9',
			'poster_url'    => '',
		) === $read['video'],
		'default video settings: ' . wp_json_encode( $read['video'] )
	);

	// A poster image of the administrator, and one of the author.
	$uploads = wp_upload_dir();
	wp_mkdir_p( $uploads['path'] );
	$png = trailingslashit( $uploads['path'] ) . "$tag-poster.png";
	file_put_contents( $png, hex2bin( '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000d4944415478da6364f8cf500f00038601805a347d6b0000000049454e44ae426082' ) );
	$files[]    = $png;
	$poster     = wp_insert_attachment(
		array(
			'post_title'     => "$tag poster",
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
			'post_author'    => $admin,
		),
		$png
	);
	$post_ids[] = (int) $poster;
	$text_file  = wp_insert_attachment(
		array(
			'post_title'     => "$tag notes",
			'post_mime_type' => 'text/plain',
			'post_status'    => 'inherit',
			'post_author'    => $admin,
		),
		$png
	);
	$post_ids[] = (int) $text_file;

	$body                   = payload( $read );
	$body['type']           = 'video';
	$body['external_url']   = 'https://www.youtube.com/watch?v=abcdefghijk';
	$body['content']        = $content;
	$body['video']          = array(
		'provider'      => 'youtube',
		'duration'      => 600,
		'completion'    => 'watch_percent',
		'watch_percent' => 50,
		'poster_id'     => (int) $poster,
		'autoplay'      => false,
		'loop'          => true,
		'controls'      => false,
		'aspect_ratio'  => '4/3',
	);
	$saved = rest( 'PUT', "/authoring/lessons/$lesson", $body );
	ok( 200 === $saved->get_status(), 'video settings saved: ' . wp_json_encode( $saved->get_data() ) );
	$read = rest( 'GET', "/authoring/lessons/$lesson" )->get_data();
	ok( 'youtube' === $read['video']['provider'] && 600 === $read['video']['duration'] && 'watch_percent' === $read['video']['completion'] && 50 === $read['video']['watch_percent'] && (int) $poster === $read['video']['poster_id'] && true === $read['video']['loop'] && false === $read['video']['controls'] && '4/3' === $read['video']['aspect_ratio'], 'video settings reopened' );
	ok( wp_get_attachment_url( $poster ) === $read['video']['poster_url'], 'poster URL is the library URL' );
	$stored = get_post_meta( $lesson, '_video_settings', true );
	ok( 'youtube' === $stored['platform'] && 'watch_percent' === $stored['completion_rule'] && true === $stored['remove_branding'] && 80 === (int) $stored['width'], 'stored in the player\'s keys; unrelated player keys kept' );

	// Invalid video settings change nothing.
	$before  = get_post_meta( $lesson, '_video_settings', true );
	$base    = payload( $read );
	$invalid = array(
		array( 'provider' => 'tiktok' ),
		array( 'duration' => 86401 ),
		array( 'duration' => '600' ),
		array( 'completion' => 'always' ),
		array( 'watch_percent' => 0 ),
		array( 'watch_percent' => 101 ),
		array( 'aspect_ratio' => 'wide' ),
		array( 'poster_id' => (int) $text_file ),
		array( 'poster_id' => 999999999 ),
		array( 'autoplay' => 'yes' ),
	);
	foreach ( $invalid as $index => $patch ) {
		$attempt          = $base;
		$attempt['video'] = array_merge( $base['video'], $patch );
		$response         = rest( 'PUT', "/authoring/lessons/$lesson", $attempt );
		ok( 400 === $response->get_status(), "invalid video settings $index refused: " . $response->get_status() );
	}
	$attempt = $base;
	unset( $attempt['video']['loop'] );
	ok( 400 === rest( 'PUT', "/authoring/lessons/$lesson", $attempt )->get_status(), 'video settings need every key' );
	$attempt                        = $base;
	$attempt['video']['poster_url'] = 'https://evil.example/x.png';
	ok( 400 === rest( 'PUT', "/authoring/lessons/$lesson", $attempt )->get_status(), 'the poster URL is not writable' );
	wp_cache_delete( $lesson, 'post_meta' );
	ok( get_post_meta( $lesson, '_video_settings', true ) === $before, 'rejected saves changed nothing' );

	// The author cannot pass someone else's image as a poster through the native editor either.
	wp_set_current_user( $author );
	$author_lesson = make_post(
		array(
			'post_type'   => 'ohmylms-lesson',
			'post_title'  => "$tag author lesson",
			'post_status' => 'draft',
			'post_author' => $author,
		)
	);
	$native        = new WP_REST_Request( 'POST', '/wp/v2/ohmylms-lessons/' . $author_lesson );
	$native->set_header( 'Content-Type', 'application/json' );
	$native->set_body(
		wp_json_encode(
			array(
				'ohmylms_lesson_settings' => array(
					'video_settings' => array(
						'poster_id'       => (int) $poster,
						'duration'        => 999999,
						'completion_rule' => 'sometimes',
						'watch_percent'   => 400,
					),
				),
			)
		)
	);
	$native_result = rest_do_request( $native );
	$native_video  = get_post_meta( $author_lesson, '_video_settings', true );
	ok( 200 === $native_result->get_status() && 0 === (int) $native_video['poster_id'] && 86400 === (int) $native_video['duration'] && 'none' === $native_video['completion_rule'] && 100 === (int) $native_video['watch_percent'], 'native saves are bounded: ' . wp_json_encode( $native_video ) );
	ok( 403 === rest( 'GET', "/authoring/lessons/$lesson/objectives" )->get_status() || 401 === rest( 'GET', "/authoring/lessons/$lesson/objectives" )->get_status(), 'authors without administration cannot use the typed objectives routes' );
	wp_set_current_user( $admin );

	// Inline questions in reading order with their bank status.
	$inline = $read['inline_questions'];
	ok( 3 === count( $inline ), 'three inline references: ' . wp_json_encode( $inline ) );
	ok( 'block' === $inline[0]['kind'] && $draft_q === $inline[0]['question_id'] && "$tag draft check" === $inline[0]['title'] && 'not_approved' === $inline[0]['status'], 'first: a block of a question not approved yet' );
	ok( 'shortcode' === $inline[1]['kind'] && $approved === $inline[1]['question_id'] && 'approved' === $inline[1]['status'] && $identity_a['uuid'] === $inline[1]['uuid'], 'second: a shortcode resolved by UUID' );
	ok( 'missing' === $inline[2]['status'] && 0 === $inline[2]['question_id'] && $ghost_uuid === $inline[2]['uuid'], 'third: an unknown question is missing' );
	ok( array( 1, 2, 3 ) === array_column( $inline, 'position' ), 'positions follow the content' );
	ok( array() === LessonAuthoring::inline_questions( '<p>No checks</p>' ), 'no inline questions in plain content' );

	// The watched-percentage rule: tracker threshold, declared duration, elapsed-time bound, completion.
	$course  = make_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_title'  => "$tag course",
			'post_status' => 'publish',
		)
	);
	$chapter = make_post(
		array(
			'post_type'   => 'ohmylms-chapter',
			'post_title'  => "$tag chapter",
			'post_status' => 'publish',
		)
	);
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_chapter_relationship',
		array(
			'course_id'    => $course,
			'chapter_id'   => $chapter,
			'order_number' => 1,
		)
	);
	$plain = make_post(
		array(
			'post_type'   => 'ohmylms-lesson',
			'post_title'  => "$tag reading",
			'post_status' => 'publish',
		)
	);
	update_post_meta( $plain, '_type', 'text' );
	wp_update_post(
		array(
			'ID'          => $lesson,
			'post_status' => 'publish',
		)
	);
	foreach ( array( $lesson, $plain ) as $order => $content_id ) {
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id'   => $chapter,
				'content_id'   => $content_id,
				'content_type' => 'lesson',
				'order_number' => $order + 1,
			)
		);
	}
	ok( true === \OhMyLMS\Student\Enrollments::enroll_manually( $learner, $course ), 'learner enrolled' );
	$rule = VideoCompletion::rule( $lesson );
	ok( true === $rule['required'] && 50 === $rule['percent'] && 600 === $rule['duration'], 'the rule applies to the video lesson' );
	ok( false === VideoCompletion::rule( $plain )['required'] && 90 === VideoCompletion::threshold( $plain, 90 ), 'a reading lesson keeps the site threshold' );

	wp_set_current_user( $learner );
	$nonce = wp_create_nonce( 'lesson_completed_nonce' );
	$done  = ajax(
		array( \OhMyLMS\Ajax::class, 'lesson_completed' ),
		array(
			'lesson_id' => (string) $lesson,
			'nonce'     => $nonce,
		)
	);
	ok( false === $done['success'] && 'ohmylms_lesson_video_not_watched' === $done['data']['code'] && false !== strpos( $done['data']['message'], '50%' ), '"Mark as complete" is refused before watching: ' . wp_json_encode( $done ) );
	ok( ! \OhMyLMS\Assignment\Delivery::completed( $learner, $lesson ), 'the lesson stays incomplete' );

	$tracker = new \OhMyLMS\VideoProgress\VideoProgressTracker();
	ok( $tracker->save_progress( $learner, $lesson, $course, 400, 400, 400 ), 'first progress report saved' );
	$progress = $tracker->get_progress( $learner, $lesson );
	ok( 600.0 === (float) $progress->total_duration && (float) $progress->watched_duration <= 120.0 && ! (int) $progress->is_completed, 'the declared duration and the first-report bound apply: ' . wp_json_encode( $progress ) );
	$wpdb->query( $wpdb->prepare( 'UPDATE %i SET watched_duration = 250, last_updated = NOW() - INTERVAL 100 SECOND WHERE user_id = %d AND lesson_id = %d', $wpdb->prefix . 'ohmylms_video_progress', $learner, $lesson ) );
	$tracker->save_progress( $learner, $lesson, $course, 290, 600, 290 );
	$progress = $tracker->get_progress( $learner, $lesson );
	ok( 290.0 === (float) $progress->watched_duration && ! (int) $progress->is_completed, '48% watched is not enough for a 50% rule' );
	ok( ! VideoCompletion::satisfied( $learner, $lesson ), 'the rule is not satisfied yet' );
	$wpdb->query( $wpdb->prepare( 'UPDATE %i SET last_updated = NOW() - INTERVAL 30 SECOND WHERE user_id = %d AND lesson_id = %d', $wpdb->prefix . 'ohmylms_video_progress', $learner, $lesson ) );
	$tracker->save_progress( $learner, $lesson, $course, 900, 600, 320 );
	$progress = $tracker->get_progress( $learner, $lesson );
	ok( (float) $progress->watched_duration <= 380.0 && (float) $progress->watched_duration > 300.0 && (int) $progress->is_completed, 'a later report is bounded by elapsed time and completes the video at the lesson\'s 50%: ' . wp_json_encode( $progress ) );
	ok( VideoCompletion::satisfied( $learner, $lesson ), 'the rule is satisfied' );
	ok( $tracker->mark_lesson_complete( $learner, $lesson, $course ), 'the tracker completes the lesson' );
	ok( \OhMyLMS\Assignment\Delivery::completed( $learner, $lesson ), 'the lesson is completed' );
	$wpdb->delete(
		$wpdb->prefix . 'ohmylms_video_progress',
		array(
			'user_id'   => $learner,
			'lesson_id' => $lesson,
		)
	);
	ok( VideoCompletion::satisfied( $learner, $lesson ), 'a completed lesson stays satisfied after the player clears its progress' );
	$done = ajax(
		array( \OhMyLMS\Ajax::class, 'lesson_completed' ),
		array(
			'lesson_id' => (string) $plain,
			'nonce'     => $nonce,
		)
	);
	ok( true === $done['success'], 'a lesson without a rule completes from the button' );

	// Drip release through the shared schedule (lessons keep their gate).
	update_post_meta(
		$plain,
		'_drip_settings',
		array(
			'enable' => true,
			'type'   => 'enrollment-from-x-days',
			'days'   => 2,
			'date'   => '',
			'time'   => '',
		)
	);
	ok( true === apply_filters( 'ohmylms_is_lesson_locked', false, $plain, $course, $learner ), 'a lesson two days after enrolment is locked today' );
	ok( 0 === strpos( apply_filters( 'ohmylms_drip_protection_message', '', $plain, 'ohmylms-lesson', $learner ), 'This lesson will be available on ' ), 'and the learner sees when it opens' );
	update_post_meta(
		$plain,
		'_drip_settings',
		array(
			'enable' => true,
			'type'   => 'enrollment-from-x-days',
			'days'   => 0,
			'date'   => '',
			'time'   => '',
		)
	);
	ok( false === apply_filters( 'ohmylms_is_lesson_locked', false, $plain, $course, $learner ) && '' === apply_filters( 'ohmylms_drip_protection_message', '', $plain, 'ohmylms-lesson', $learner ), 'open on the day of enrolment' );
	ok(
		DripSchedule::is_locked(
			array(
				'enable' => true,
				'type'   => 'specific-date',
				'date'   => '2099-01-01',
				'time'   => '',
			),
			0,
			0
		) && ! DripSchedule::is_locked(
			array(
				'enable' => false,
				'type'   => 'specific-date',
				'date'   => '2099-01-01',
			),
			0,
			0
		),
		'switched-off schedules are open'
	);

	// Objectives: skills and syllabus objective mappings.
	wp_set_current_user( $admin );
	$syllabus   = null;
	$objectives = array();
	foreach ( CourseObjectives::syllabuses() as $row ) {
		$found = 'roadmap' === $row['item_type'] ? array() : CourseObjectives::objectives( $row['id'] );
		if ( ! is_wp_error( $found ) && count( $found ) >= 3 ) {
			$syllabus   = $row;
			$objectives = array_values( $found );
			break;
		}
	}
	ok( null !== $syllabus, 'the site has a teaching syllabus with objectives to map' );
	list( $first, $second, $third ) = array_slice( $objectives, 0, 3 );
	update_post_meta(
		$course,
		CourseObjectives::META,
		array(
			array(
				'syllabus_id' => (int) $syllabus['id'],
				'term_id'     => (int) $third['term_id'],
			),
		)
	);
	$options = rest( 'GET', "/authoring/lessons/$lesson/objective-options", array( 'type' => 'syllabuses' ) )->get_data();
	$kinds   = array_column( CourseObjectives::syllabuses(), 'item_type', 'id' );
	$offered = array_column( $options['items'], 'id' );
	ok( in_array( (int) $syllabus['id'], $offered, true ) && ! in_array( 'roadmap', array_intersect_key( $kinds, array_flip( $offered ) ), true ), 'syllabus choices exclude roadmaps' );
	$search = rest(
		'GET',
		"/authoring/lessons/$lesson/objective-options",
		array(
			'type'        => 'objectives',
			'syllabus_id' => (int) $syllabus['id'],
			'search'      => $first['name'],
		)
	)->get_data();
	ok( in_array( (int) $first['term_id'], array_column( $search['items'], 'term_id' ), true ) && count( $search['items'] ) <= 20, 'objectives are searched in one syllabus' );
	ok( 400 === rest( 'GET', "/authoring/lessons/$lesson/objective-options", array( 'type' => 'objectives' ) )->get_status(), 'objectives need a syllabus' );
	$skills = rest(
		'GET',
		"/authoring/lessons/$lesson/objective-options",
		array(
			'type'   => 'skills',
			'search' => $second['name'],
		)
	)->get_data();
	ok( in_array( (int) $second['term_id'], array_column( $skills['items'], 'id' ), true ), 'skills are searchable' );

	$state = rest( 'GET', "/authoring/lessons/$lesson/objectives" )->get_data();
	ok( array() === $state['skills'] && array() === $state['mappings'], 'a new lesson has no objectives' );
	ok( 1 === count( $state['course_targets'] ) && (int) $third['term_id'] === $state['course_targets'][0]['term_id'] && $course === $state['course_targets'][0]['course_id'], 'the course objective is offered: ' . wp_json_encode( $state['course_targets'] ) );
	$put = rest(
		'PUT',
		"/authoring/lessons/$lesson/objectives",
		array(
			'revision'  => $state['revision'],
			'skill_ids' => array( (int) $second['term_id'] ),
			'mappings'  => array(
				array(
					'syllabus_id' => (int) $syllabus['id'],
					'term_id'     => (int) $first['term_id'],
					'relation'    => 'teaches',
				),
				array(
					'syllabus_id' => (int) $syllabus['id'],
					'term_id'     => (int) $third['term_id'],
					'relation'    => 'assesses',
				),
			),
		)
	);
	ok( 200 === $put->get_status(), 'objectives saved: ' . wp_json_encode( $put->get_data() ) );
	$state   = $put->get_data();
	$managed = array_column( $state['skills'], 'managed', 'id' );
	ok( 3 === count( $state['skills'] ) && false === $managed[ (int) $second['term_id'] ] && true === $managed[ (int) $first['term_id'] ] && true === $managed[ (int) $third['term_id'] ], 'explicit and mapped skills are linked; mapped ones are managed' );
	ok( array( 'teaches', 'assesses' ) === array_column( $state['mappings'], 'relation' ) && $first['objective'] === $state['mappings'][0]['objective'] && $syllabus['name'] === $state['mappings'][0]['syllabus_name'], 'mappings reopen with live wording' );
	$terms = wp_get_object_terms( $lesson, \OhMyLMS\Skills\Taxonomy::NAME, array( 'fields' => 'ids' ) );
	ok( 3 === count( $terms ), 'the lesson\'s skill terms changed' );
	$stale = rest(
		'PUT',
		"/authoring/lessons/$lesson/objectives",
		array(
			'revision'  => 'stale',
			'skill_ids' => array(),
			'mappings'  => array(),
		)
	);
	ok( 409 === $stale->get_status(), 'a stale revision answers 409' );
	$bad = array(
		array(
			'revision'  => $state['revision'],
			'skill_ids' => array( 999999999 ),
			'mappings'  => array(),
		),
		array(
			'revision'  => $state['revision'],
			'skill_ids' => array(),
			'mappings'  => array(
				array(
					'syllabus_id' => (int) $syllabus['id'],
					'term_id'     => 999999999,
					'relation'    => 'teaches',
				),
			),
		),
		array(
			'revision'  => $state['revision'],
			'skill_ids' => array(),
			'mappings'  => array(
				array(
					'syllabus_id' => (int) $syllabus['id'],
					'term_id'     => (int) $first['term_id'],
					'relation'    => 'mentions',
				),
			),
		),
		array(
			'revision'  => $state['revision'],
			'skill_ids' => array(),
			'mappings'  => array(),
			'extra'     => 1,
		),
	);
	foreach ( $bad as $index => $data ) {
		$response = rest( 'PUT', "/authoring/lessons/$lesson/objectives", $data );
		ok( $response->get_status() >= 400 && $response->get_status() < 500, "invalid objectives $index refused: " . $response->get_status() );
	}
	ok( 3 === count( wp_get_object_terms( $lesson, \OhMyLMS\Skills\Taxonomy::NAME, array( 'fields' => 'ids' ) ) ) && 2 === count( rest( 'GET', "/authoring/lessons/$lesson/objectives" )->get_data()['mappings'] ), 'refused saves changed nothing' );
	$clear = rest(
		'PUT',
		"/authoring/lessons/$lesson/objectives",
		array(
			'revision'  => $state['revision'],
			'skill_ids' => array( (int) $second['term_id'] ),
			'mappings'  => array(),
		)
	);
	ok( 200 === $clear->get_status() && array( (int) $second['term_id'] ) === array_column( $clear->get_data()['skills'], 'id' ) && array() === $clear->get_data()['mappings'], 'removing the mappings removes their managed skills and keeps the explicit one' );

	fwrite( STDOUT, "OK lesson-settings-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	wp_set_current_user( 0 );
	if ( $questions ) {
		foreach ( $questions as $question ) {
			$wpdb->delete( \OhMyLMS\Assessment\Schema::table( 'qb_questions' ), array( 'question_id' => $question ) );
		}
	}
	foreach ( array_unique( array_map( 'intval', $post_ids ) ) as $post_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'content_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'chapter_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'chapter_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_video_progress', array( 'lesson_id' => $post_id ) );
		if ( 'attachment' === get_post_type( $post_id ) ) {
			$wpdb->delete( $wpdb->posts, array( 'ID' => $post_id ) );
			$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $post_id ) );
		} else {
			wp_delete_post( $post_id, true );
		}
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
		if ( is_file( $file ) ) {
			wp_delete_file( $file );
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
