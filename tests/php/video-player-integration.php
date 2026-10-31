<?php
/**
 * Lesson video player (batch L7 of docs/LEARNER-FRONTEND.md): provider parsing, the `ohmylms/video`
 * module and its adapters, the rendered markup and state for every provider, the decoration of
 * pre-L7 theme overrides, and the progress and completion endpoints for an enrolled learner:
 * spans merged on the server, the elapsed-time bound, the watched-percentage rule, and completion
 * through `ohmylms_lesson_completed` only.
 *
 * Runs against the Local site. It creates tagged lessons, a course, a chapter, a video attachment and
 * temporary users, and removes them (and the options it changes) in `finally`.
 *
 * Usage (set OHMYLMS_VIDEO_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/video-player-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions, WordPress.Security.NonceVerification

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_VIDEO_TEST' ) ) {
	exit( "Set OHMYLMS_VIDEO_TEST=1 to run the video player tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Extensions\Interactivity;
use OhMyLMS\Extensions\Video;
use OhMyLMS\Learning\VideoCompletion;
use OhMyLMS\Learning\VideoSource;

global $wpdb;

$checks   = 0;
$post_ids = array();
$user_ids = array();
$options  = array();
$tag      = 'vidtest' . strtolower( wp_generate_password( 6, false, false ) );

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
 * @return int
 */
function make_user( $suffix ) {
	global $user_ids, $tag;
	$id = wp_insert_user(
		array(
			'user_login' => $tag . '_' . $suffix,
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . '_' . $suffix . '@example.invalid',
			'role'       => 'subscriber',
		)
	);
	ok( ! is_wp_error( $id ), "temporary user $suffix" );
	$user_ids[] = (int) $id;
	return (int) $id;
}

/**
 * Set an option for the test; the original value is restored in `finally`.
 *
 * @param string $name  Option.
 * @param mixed  $value Value.
 */
function set_option( $name, $value ) {
	global $options;
	if ( ! array_key_exists( $name, $options ) ) {
		$options[ $name ] = get_option( $name, null );
	}
	update_option( $name, $value );
}

/**
 * Run an AJAX handler and decode its JSON.
 *
 * @param callable $handler Handler.
 * @param array    $post    POST fields.
 * @return mixed
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

/**
 * Root attributes and context of the first `ohmylms/video` island (or of the element with a class).
 *
 * @param string $html       HTML.
 * @param string $root_class Class of the island root.
 * @return array{attributes: array, context: array}|null
 */
function island( $html, $root_class = '' ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		if ( 'ohmylms/video' !== $tags->get_attribute( 'data-wp-interactive' ) || ( $root_class && ! $tags->has_class( $root_class ) ) ) {
			continue;
		}
		$attributes = array();
		foreach ( (array) $tags->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $tags->get_attribute( $name );
		}
		return array(
			'attributes' => $attributes,
			'context'    => json_decode( (string) $tags->get_attribute( 'data-wp-context' ), true ),
		);
	}
	return null;
}

/**
 * Count elements matching a test.
 *
 * @param string   $html HTML.
 * @param callable $test Called with the processor on each tag.
 * @return int
 */
function count_tags( $html, callable $test ) {
	$tags  = new WP_HTML_Tag_Processor( $html );
	$count = 0;
	while ( $tags->next_tag() ) {
		$count += $test( $tags ) ? 1 : 0;
	}
	return $count;
}

/**
 * Render single-lesson/content-lesson.php for a lesson, as the lesson page does.
 *
 * @param int $lesson Lesson ID.
 * @return string
 */
function render_lesson( $lesson ) {
	$GLOBALS['wp_query']     = new WP_Query(
		array(
			'p'         => $lesson,
			'post_type' => OHMYLMS_LESSON_CPT,
		)
	);
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	ob_start();
	ohmylms_get_template( 'single-lesson/content-lesson.php' );
	$html = (string) ob_get_clean();
	wp_reset_postdata();
	return $html;
}

/**
 * Reset the per-request "rendered once" flags so each render starts fresh.
 */
function fresh_request() {
	foreach ( array(
		Interactivity::class => 'rendered',
		Video::class         => 'tracked',
	) as $class => $property ) {
		$flags = new ReflectionProperty( $class, $property );
		$flags->setAccessible( true );
		$flags->setValue( null, array() );
	}
}

add_filter(
	'pre_wp_mail',
	static function () {
		return true;
	}
);

$exit_code = 0;
try {
	// ---- Provider parsing (the same cases as tests/js/video.test.mjs) -----------------------
	$sources = array(
		array( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10', 'youtube', 'dQw4w9WgXcQ' ),
		array( 'https://youtu.be/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ' ),
		array( 'https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0', 'youtube', 'dQw4w9WgXcQ' ),
		array( 'https://www.youtube.com/shorts/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ' ),
		array( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ' ),
		array( 'https://vimeo.com/76979871', 'vimeo', '76979871' ),
		array( 'https://player.vimeo.com/video/76979871?h=8272103f6e', 'vimeo', '76979871' ),
		array( 'https://vimeo.com/channels/staffpicks/76979871', 'vimeo', '76979871' ),
		array( 'https://iframe.mediadelivery.net/embed/1234/0b6f1e2c-7d1a-4b0e-9f3e-1a2b3c4d5e6f', 'bunny', '0b6f1e2c-7d1a-4b0e-9f3e-1a2b3c4d5e6f' ),
		array( 'https://iframe.mediadelivery.net/play/1234/0b6f1e2c-7d1a-4b0e-9f3e-1a2b3c4d5e6f', 'bunny', '0b6f1e2c-7d1a-4b0e-9f3e-1a2b3c4d5e6f' ),
		array( 'https://acme.wistia.com/medias/abcde12345', 'wistia', 'abcde12345' ),
		array( 'https://fast.wistia.net/embed/iframe/abcde12345?videoFoam=true', 'wistia', 'abcde12345' ),
		array( 'https://cdn.example.com/lesson.mp4', 'html5', '' ),
		array( '/wp-content/uploads/lesson.webm', 'html5', '' ),
		array( 'https://www.loom.com/embed/abc', 'html5', '' ),
		array( 'javascript:alert(1)', '', '' ),
		array( '', '', '' ),
	);
	foreach ( $sources as list( $url, $provider, $id ) ) {
		$parsed = VideoSource::parse( $url );
		ok( $provider === $parsed['provider'] && $id === $parsed['id'], "source $url: " . wp_json_encode( $parsed ) );
	}
	ok( 'embed' === VideoSource::parse( 'https://www.loom.com/embed/abc', false )['provider'], 'without the custom player an unknown page is an embed' );
	ok( '8272103f6e' === VideoSource::parse( 'https://vimeo.com/76979871/8272103f6e' )['hash'], 'Vimeo privacy hash' );
	ok( 'https://iframe.mediadelivery.net/embed/1234/0b6f1e2c-7d1a-4b0e-9f3e-1a2b3c4d5e6f' === VideoSource::parse( 'https://iframe.mediadelivery.net/play/1234/0b6f1e2c-7d1a-4b0e-9f3e-1a2b3c4d5e6f' )['embed'], 'Bunny play URLs embed the player' );
	ok( 'video/webm' === VideoSource::mime( 'https://x.test/a.webm?x=1' ) && 'video/mp4' === VideoSource::mime( 'https://x.test/stream' ), 'MIME types' );

	// ---- Module registration ---------------------------------------------------------------
	Interactivity::register();
	$registered = new ReflectionProperty( WP_Script_Modules::class, 'registered' );
	$registered->setAccessible( true );
	$modules = (array) $registered->getValue( wp_script_modules() );
	ok( isset( $modules['ohmylms/video'] ) && false !== strpos( $modules['ohmylms/video']['src'], 'assets/interactivity/video.js' ), 'ohmylms/video is registered' );
	$dependencies = array();
	foreach ( (array) $modules['ohmylms/video']['dependencies'] as $dependency ) {
		$dependencies[ $dependency['id'] ] = $dependency['import'];
	}
	ok( 'static' === ( $dependencies['@wordpress/interactivity'] ?? '' ) && 'static' === ( $dependencies['ohmylms/interactivity'] ?? '' ), 'depends on the runtime and the SDK' );
	foreach ( Video::ADAPTERS as $adapter ) {
		ok( 'dynamic' === ( $dependencies[ 'ohmylms/video-' . $adapter ] ?? '' ), "the $adapter adapter is a dynamic import" );
		ok( isset( $modules[ 'ohmylms/video-' . $adapter ] ) && false !== strpos( $modules[ 'ohmylms/video-' . $adapter ]['src'], "assets/interactivity/video/$adapter.js" ), "ohmylms/video-$adapter is registered" );
	}
	ok( ! isset( \OhMyLMS\Assets\FrontendAssets::LEARNER_SCRIPTS['ohmylms-video-player'] ) && ! isset( \OhMyLMS\Assets\FrontendAssets::LEARNER_SCRIPTS['ohmylms-video-progress-tracker'] ), 'the jQuery handles are gone' );
	ok( ! file_exists( OHMYLMS_DIR . '/assets/src/frontend/js/lesson/video-player.js' ) && ! file_exists( OHMYLMS_DIR . '/assets/src/frontend/js/video-progress-tracker.js' ), 'the jQuery scripts are deleted' );
	ok( ! is_file( OHMYLMS_DIR . '/assets/src/frontend/js/ohmylms.js' ), 'ohmylms.js is deleted' );
	$frontend = ( new \OhMyLMS\Assets\FrontendAssets() )->get_scripts()['ohmylms-frontend'];
	ok( false === $frontend['src'] && array() === $frontend['deps'], 'ohmylms-frontend is a script-less handle with no jQuery' );
	$old_url = plugins_url( 'assets/dist/frontend/video-player.js', OHMYLMS_FILE );
	ok( Interactivity::legacy_url( $old_url ) === $old_url, 'the removed dist URL is no longer redirected' );
	$old_frontend = plugins_url( 'assets/dist/frontend/ohmylms.js', OHMYLMS_FILE );
	ok( Interactivity::legacy_url( $old_frontend ) === $old_frontend, 'the removed ohmylms.js dist URL is no longer redirected' );

	// ---- Fixtures: a course with video lessons and an enrolled learner -------------------------
	$learner  = make_user( 'learner' );
	$stranger = make_user( 'stranger' );
	$forger   = make_user( 'forger' );
	$fixture_course   = make_post(
		array(
			'post_type'  => 'ohmylms-course',
			'post_title' => "$tag course",
		)
	);
	$chapter  = make_post(
		array(
			'post_type'  => 'ohmylms-chapter',
			'post_title' => "$tag chapter",
		)
	);
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_chapter_relationship',
		array(
			'course_id'    => $fixture_course,
			'chapter_id'   => $chapter,
			'order_number' => 1,
		)
	);
	$uploads    = wp_upload_dir();
	$file       = $uploads['path'] . "/$tag.mp4";
	$attachment = wp_insert_attachment(
		array(
			'post_title'     => "$tag video",
			'post_mime_type' => 'video/mp4',
			'post_status'    => 'inherit',
		),
		$file
	);
	$post_ids[] = (int) $attachment;
	wp_update_attachment_metadata( $attachment, array( 'length' => 600 ) );
	$lessons = array();
	$specs   = array(
		'upload'  => array(
			'_video_id'       => $attachment,
			'_video_settings' => array(
				'completion_rule' => 'watch_percent',
				'watch_percent'   => 50,
				'aspect_ratio'    => '4/3',
			),
		),
		'youtube' => array( '_external_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ),
		'bunny'   => array( '_external_url' => 'https://iframe.mediadelivery.net/embed/1234/0b6f1e2c-7d1a-4b0e-9f3e-1a2b3c4d5e6f' ),
		'wistia'  => array( '_external_url' => 'https://acme.wistia.com/medias/abcde12345' ),
		'vimeo'   => array( '_external_url' => 'https://vimeo.com/76979871/8272103f6e' ),
		'embed'   => array(
			'_external_url'   => 'https://www.loom.com/embed/abc',
			'_video_settings' => array(
				'completion_rule' => 'watch_percent',
				'watch_percent'   => 80,
			),
		),
	);
	$order = 0;
	foreach ( $specs as $name => $meta ) {
		$id = make_post(
			array(
				'post_type'  => OHMYLMS_LESSON_CPT,
				'post_title' => "$tag $name lesson",
			)
		);
		update_post_meta( $id, '_type', 'video' );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id'   => $chapter,
				'content_id'   => $id,
				'content_type' => 'lesson',
				'order_number' => ++$order,
			)
		);
		$lessons[ $name ] = $id;
	}
	ok( true === \OhMyLMS\Student\Enrollments::enroll_manually( $learner, $fixture_course ), 'learner enrolled' );
	ok( true === \OhMyLMS\Student\Enrollments::enroll_manually( $forger, $fixture_course ), 'second learner enrolled' );
	set_option( 'ohmylms_use_custom_video_player', 'yes' );
	set_option( 'ohmylms_video_player_logo', '' );
	set_option( 'ohmylms_video_completion_threshold', 90 );

	// ---- The rule: only for videos the player can track ---------------------------------------
	ok( VideoCompletion::rule( $lessons['upload'] )['required'], 'an uploaded video with "watch 50 %" requires watching' );
	ok( 600.0 === VideoCompletion::total( $lessons['upload'], 60 ), 'the file length WordPress read is a floor for the duration' );
	set_option( 'ohmylms_use_custom_video_player', 'no' );
	ok( ! VideoCompletion::rule( $lessons['embed'] )['required'] && ! VideoCompletion::satisfied( $learner, $lessons['upload'] ) && VideoCompletion::satisfied( $learner, $lessons['embed'] ), 'an untrackable embed cannot lock the learner out' );
	set_option( 'ohmylms_use_custom_video_player', 'yes' );

	// ---- Rendering: guests see players without tracking ---------------------------------------
	wp_set_current_user( 0 );
	fresh_request();
	$html   = render_lesson( $lessons['upload'] );
	$player = island( $html, 'ohmylms-custom-video-player' );
	ok( $player && false === $player['context']['track'], 'a guest gets the player without tracking' );
	ok( ! isset( wp_interactivity_state( 'ohmylms/video' )['nonces'] ), 'and no progress nonces' );

	// ---- Rendering: custom player, uploaded file -----------------------------------------------
	wp_set_current_user( $learner );
	$tracker = new \OhMyLMS\VideoProgress\VideoProgressTracker();
	$tracker->save_progress( $learner, $lessons['upload'], $fixture_course, 40, 600, 41.5, array( array( 0, 40 ) ) );
	fresh_request();
	$html    = render_lesson( $lessons['upload'] );
	$player  = island( $html, 'ohmylms-custom-video-player' );
	$context = $player['context'];
	ok( 'html5' === $context['provider'] && true === $context['custom'] && true === $context['track'] && $lessons['upload'] === $context['lessonId'] && $fixture_course === $context['courseId'], 'custom HTML5 player tracks the enrolled learner: ' . wp_json_encode( $context ) );
	ok( 41.5 === (float) $context['resume'] && 41.5 === (float) $context['time'] && 600.0 === (float) $context['duration'] && 50.0 === (float) $context['threshold'] && false === $context['videoDone'] && false === $context['lessonDone'], 'resume position, known duration and the lesson threshold are in the context' );
	ok( 'callbacks.mount' === $player['attributes']['data-wp-init'] && 'actions.pagehide' === $player['attributes']['data-wp-on-window--pagehide'] && 'actions.visibility' === $player['attributes']['data-wp-on-document--visibilitychange'] && 'actions.keydown' === $player['attributes']['data-wp-on--keydown'], 'the island mounts, saves on page hide and takes the keyboard' );
	ok( 'region' === $player['attributes']['role'] && false !== strpos( $player['attributes']['aria-label'], "$tag upload lesson" ) && 'aspect-ratio: 4/3;' === $player['attributes']['style'], 'a labelled region with the lesson aspect ratio' );
	ok( 1 === count_tags( $html, static fn( $t ) => 'VIDEO' === $t->get_tag() && null !== $t->get_attribute( 'controls' ) && $t->has_class( 'ohmylms-html5-video' ) ), 'the <video> has native controls until the custom ones mount (works without JavaScript)' );
	ok( 1 === count_tags( $html, static fn( $t ) => 'SOURCE' === $t->get_tag() && 'video/mp4' === $t->get_attribute( 'type' ) && false !== strpos( (string) $t->get_attribute( 'src' ), "$tag.mp4" ) ), 'the file is a <source>' );
	ok( 1 === count_tags( $html, static fn( $t ) => $t->has_class( 'ohmylms-custom-controls' ) && null !== $t->get_attribute( 'hidden' ) && '!context.mounted' === $t->get_attribute( 'data-wp-bind--hidden' ) ), 'the custom controls are hidden until mounted' );
	ok( 2 === count_tags( $html, static fn( $t ) => 'slider' === $t->get_attribute( 'role' ) && '0' === $t->get_attribute( 'tabindex' ) && $t->get_attribute( 'aria-label' ) ), 'seek and volume are labelled, focusable sliders' );
	ok( 1 === count_tags( $html, static fn( $t ) => $t->has_class( 'ohmylms-progress-bar' ) && 'state.timeText' === $t->get_attribute( 'data-wp-bind--aria-valuetext' ) && 'actions.seekKey' === $t->get_attribute( 'data-wp-on--keydown' ) ), 'the seek slider speaks the time and has keys' );
	ok( 6 === count_tags( $html, static fn( $t ) => 'menuitemradio' === $t->get_attribute( 'role' ) && 'actions.setRate' === $t->get_attribute( 'data-wp-on--click' ) ), 'six playback speeds in a menu' );
	ok( 1 === count_tags( $html, static fn( $t ) => $t->has_class( 'ohmylms-speed-btn' ) && 'menu' === $t->get_attribute( 'aria-haspopup' ) && 'context.speedOpen' === $t->get_attribute( 'data-wp-bind--aria-expanded' ) ), 'the speed button is a menu button' );
	ok( 1 === count_tags( $html, static fn( $t ) => $t->has_class( 'ohmylms-captions-btn' ) && 'context.captionsOn' === $t->get_attribute( 'data-wp-bind--aria-pressed' ) ), 'a captions toggle' );
	ok( 0 === count_tags( $html, static fn( $t ) => 'BUTTON' === $t->get_tag() && 'button' !== $t->get_attribute( 'type' ) ), 'every button is type="button"' );
	ok( 1 === count_tags( $html, static fn( $t ) => $t->has_class( 'ohmylms-big-play-button' ) && 'actions.start' === $t->get_attribute( 'data-wp-on--click' ) && $t->get_attribute( 'aria-label' ) ), 'a labelled big play button' );
	ok( false === strpos( $html, 'video-player.js' ) && false === strpos( $html, 'data-video-url=""' ), 'no jQuery player markup' );
	$state = wp_interactivity_state( 'ohmylms/video' );
	ok( admin_url( 'admin-ajax.php' ) === $state['ajaxUrl'] && 15000 === $state['saveInterval'] && ! empty( $state['messages']['timeText'] ), 'state: admin-ajax URL, save cadence and messages' );
	ok( wp_verify_nonce( $state['nonces']['progress'], 'video_progress_nonce' ) && wp_verify_nonce( $state['nonces']['complete'], 'lesson_completed_nonce' ), 'state: progress and completion nonces' );

	// The same lesson rendered twice in a request tracks once.
	$again = Video::player(
		array(
			'url'       => wp_get_attachment_url( $attachment ),
			'lesson_id' => $lessons['upload'],
			'track'     => true,
		)
	);
	ok( false === island( $again )['context']['track'], 'a second player of the same lesson does not report' );

	// A learner of another course is not tracked.
	wp_set_current_user( $stranger );
	fresh_request();
	ok( false === island( render_lesson( $lessons['upload'] ) )['context']['track'], 'a learner who is not enrolled is not tracked' );
	wp_set_current_user( $learner );

	// ---- Rendering: YouTube and Vimeo with the custom player -----------------------------------
	fresh_request();
	$html    = render_lesson( $lessons['youtube'] );
	$context = island( $html )['context'];
	ok( 'youtube' === $context['provider'] && 'dQw4w9WgXcQ' === $context['videoId'] && $context['custom'] && $context['track'] && 90.0 === (float) $context['threshold'], 'YouTube: custom player, tracked, site threshold: ' . wp_json_encode( $context ) );
	ok( 0 === count_tags( $html, static fn( $t ) => 'IFRAME' === $t->get_tag() ), 'the YouTube iframe is created by the adapter (the API loads only then)' );
	ok( 3 === count_tags( $html, static fn( $t ) => 'true' === $t->get_attribute( 'aria-hidden' ) && 'actions.toggle' === $t->get_attribute( 'data-wp-on--click' ) ), 'branding blockers toggle playback and are hidden from assistive technology' );
	ok( false !== strpos( $html, '<noscript><a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ"' ), 'without JavaScript: a link to the video' );
	fresh_request();
	$context = island( render_lesson( $lessons['vimeo'] ) )['context'];
	ok( 'vimeo' === $context['provider'] && '76979871' === $context['videoId'] && '8272103f6e' === $context['hash'], 'Vimeo: id and privacy hash' );

	// ---- Rendering: providers that keep their own controls -------------------------------------
	fresh_request();
	$html    = render_lesson( $lessons['bunny'] );
	$context = island( $html, 'ohmylms-lesson-external-video' )['context'];
	ok( 'bunny' === $context['provider'] && false === $context['custom'] && $context['track'], 'Bunny: its own controls, tracked through player.js' );
	ok( 1 === count_tags( $html, static fn( $t ) => 'IFRAME' === $t->get_tag() && 0 === strpos( (string) $t->get_attribute( 'src' ), 'https://iframe.mediadelivery.net/embed/1234/' ) && false !== strpos( (string) $t->get_attribute( 'src' ), 'autoplay=false' ) && null !== $t->get_attribute( 'allowfullscreen' ) && $t->get_attribute( 'title' ) ), 'Bunny iframe' );
	fresh_request();
	$html = render_lesson( $lessons['wistia'] );
	ok( 1 === count_tags( $html, static fn( $t ) => 'IFRAME' === $t->get_tag() && $t->has_class( 'wistia_embed' ) && 'wistia_embed' === $t->get_attribute( 'name' ) && 'https://fast.wistia.net/embed/iframe/abcde12345' === $t->get_attribute( 'src' ) ), 'Wistia iframe is ready for its player API' );
	ok( 'wistia' === island( $html )['context']['provider'], 'Wistia island' );

	// ---- Rendering: the custom player switched off ----------------------------------------------
	set_option( 'ohmylms_use_custom_video_player', 'no' );
	update_post_meta(
		$lessons['upload'],
		'_video_settings',
		array(
			'completion_rule' => 'watch_percent',
			'watch_percent'   => 50,
			'autoplay'        => true,
			'loop'            => true,
		)
	);
	fresh_request();
	$html    = render_lesson( $lessons['upload'] );
	$context = island( $html, 'ohmylms-video-player' )['context'];
	ok( 'html5' === $context['provider'] && false === $context['custom'] && $context['track'] && $context['autoplay'] && $context['muted'], 'plain <video>: tracked, autoplay left to the module' );
	ok( 1 === count_tags( $html, static fn( $t ) => 'VIDEO' === $t->get_tag() && $t->has_class( 'the-video' ) && null !== $t->get_attribute( 'controls' ) && null === $t->get_attribute( 'autoplay' ) && null !== $t->get_attribute( 'muted' ) && null !== $t->get_attribute( 'loop' ) && null === $t->get_attribute( 'tabindex' ) ), 'no autoplay attribute (reduced motion is honoured by the module), no positive tabindex' );
	update_post_meta(
		$lessons['upload'],
		'_video_settings',
		array(
			'completion_rule' => 'watch_percent',
			'watch_percent'   => 50,
			'controls'        => false,
		)
	);
	fresh_request();
	$html = Video::player(
		array(
			'url'        => wp_get_attachment_url( $attachment ),
			'lesson_id'  => $lessons['upload'],
			'track'      => true,
			'controls'   => false,
			'poster'     => 'https://800.local/poster.png',
			'poster_alt' => 'Poster',
		)
	);
	ok( 1 === count_tags( $html, static fn( $t ) => $t->has_class( 'ohmylms-video-player-cover' ) && null !== $t->get_attribute( 'hidden' ) && 'state.coverHidden' === $t->get_attribute( 'data-wp-bind--hidden' ) ), 'the poster cover only shows with JavaScript' );
	ok( 1 === count_tags( $html, static fn( $t ) => $t->has_class( 'ohmylms-video-player-play' ) && 'actions.coverPlay' === $t->get_attribute( 'data-wp-on--click' ) && 'button' === $t->get_attribute( 'type' ) ), 'the cover play button' );
	ok( 1 === count_tags( $html, static fn( $t ) => 'VIDEO' === $t->get_tag() && null === $t->get_attribute( 'controls' ) && '0' === $t->get_attribute( 'tabindex' ) && $t->get_attribute( 'aria-label' ) ), 'without controls the video takes the keyboard itself' );
	fresh_request();
	$html = render_lesson( $lessons['youtube'] );
	$src  = '';
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( 'iframe' ) ) {
		$src = (string) $tags->get_attribute( 'src' );
	}
	ok( 0 === strpos( $src, 'https://www.youtube.com/embed/dQw4w9WgXcQ?' ) && false !== strpos( $src, 'enablejsapi=1' ) && false !== strpos( $src, 'controls=1' ) && false === strpos( $src, 'autoplay=1' ), 'YouTube iframe without the custom player: JS API on, autoplay left to the module: ' . $src );
	ok( 'youtube' === island( $html, 'ohmylms-lesson-external-video' )['context']['provider'], 'and it is an island (tracked through the iframe API)' );
	fresh_request();
	$html    = render_lesson( $lessons['embed'] );
	$context = island( $html )['context'];
	ok( 'embed' === $context['provider'] && false === $context['track'], 'any other iframe is shown, not tracked' );
	set_option( 'ohmylms_use_custom_video_player', 'yes' );

	// ---- Theme overrides from before L7 are decorated ------------------------------------------
	fresh_request();
	$old = '<div class="ohmylms-lesson-content-body"><div class="ohmylms-custom-video-player ohmylms-responsive-video-wrapper" style="aspect-ratio: 21/9;" data-video-url="https://youtu.be/dQw4w9WgXcQ" data-autoplay="false" data-loop="true" data-muted="false" data-poster="" data-logo-url="">
		<!-- Player will be initialized by video-player.js -->
	</div></div>';
	$GLOBALS['post'] = get_post( $lessons['youtube'] );
	setup_postdata( $GLOBALS['post'] );
	$new     = Video::template( $old, 'single-lesson/content-lesson.php' );
	$context = island( $new )['context'];
	ok( 'youtube' === $context['provider'] && $context['loop'] && $context['track'] && $lessons['youtube'] === $context['lessonId'], 'an empty custom player of an override becomes the full player: ' . wp_json_encode( $context ) );
	ok( false !== strpos( $new, 'aspect-ratio: 21/9;' ) && false !== strpos( $new, 'ohmylms-custom-controls' ) && false === strpos( $new, 'initialized by video-player.js' ), 'with its aspect ratio and the controls' );
	ok( Video::template( $new, 'single-lesson/lesson-content.php' ) === $new, 'decorating twice changes nothing' );
	$old_plain = '<div class="ohmylms-video-player ohmylms-responsive-video-wrapper" tabindex="1" style="aspect-ratio: 16/9;"><video class="the-video" tabindex="2" autoplay playsinline muted controls><source src="https://cdn.example.com/a.mp4" type="video/mp4"></video><div class="ohmylms-video-player-cover"><img src="https://800.local/p.png" alt=""><button title="Play" aria-label="Play video" class="ohmylms-video-player-play" tabindex="0">▶</button></div></div><div class="ohmylms-lesson-external-video"><iframe src="https://fast.wistia.net/embed/iframe/abcde12345"></iframe></div>';
	fresh_request();
	$new = Video::template( $old_plain, 'single-lesson/content-lesson.php' );
	$native = island( $new, 'ohmylms-video-player' );
	ok( $native && 'html5' === $native['context']['provider'] && $native['context']['autoplay'] && $native['context']['track'] && ! isset( $native['attributes']['tabindex'] ), 'a plain <video> override becomes an island; its positive tabindex is gone' );
	ok( 1 === count_tags( $new, static fn( $t ) => 'VIDEO' === $t->get_tag() && null === $t->get_attribute( 'autoplay' ) && null === $t->get_attribute( 'tabindex' ) ), 'its autoplay waits for the module' );
	ok( 1 === count_tags( $new, static fn( $t ) => $t->has_class( 'ohmylms-video-player-play' ) && 'actions.coverPlay' === $t->get_attribute( 'data-wp-on--click' ) && 'button' === $t->get_attribute( 'type' ) ) && 1 === count_tags( $new, static fn( $t ) => $t->has_class( 'ohmylms-video-player-cover' ) && 'state.coverHidden' === $t->get_attribute( 'data-wp-bind--hidden' ) ), 'the cover is driven by the store' );
	$frame = island( $new, 'ohmylms-lesson-external-video' );
	ok( $frame && 'wistia' === $frame['context']['provider'] && false === $frame['context']['track'], 'the second player of the lesson is an untracked island' );
	ok( 1 === count_tags( $new, static fn( $t ) => 'IFRAME' === $t->get_tag() && $t->has_class( 'wistia_embed' ) ), 'its Wistia iframe gets the API class' );
	ok( Video::template( $new, 'single-lesson/lesson-content.php' ) === $new, 'decorated overrides are left alone the second time' );
	ok( '<p>no video</p>' === Video::template( '<p>no video</p>', 'single-lesson/content-lesson.php' ), 'other templates are untouched' );
	wp_reset_postdata();

	// ---- Progress endpoint: spans merged, bounded, the rule, and no completion here -----------
	$tracker->reset_progress( $learner, $lessons['upload'] );
	ok( array() === VideoCompletion::seen( $learner, $lessons['upload'] ), 'reset forgets the spans' );
	$nonce = wp_create_nonce( 'video_progress_nonce' );
	$save  = static function ( $lesson, array $fields, $user_nonce = null ) use ( $nonce ) {
		return ajax(
			array( \OhMyLMS\Ajax::class, 'save_video_progress' ),
			$fields + array(
				'lesson_id' => (string) $lesson,
				'course_id' => '999999',
				'nonce'     => $user_nonce ? $user_nonce : $nonce,
			)
		);
	};
	$answer = $save(
		$lessons['upload'],
		array(
			'watched_duration' => '30.5',
			'total_duration'   => '60',
			'last_position'    => '30.5',
			'segments'         => '[[0,30.5]]',
		)
	);
	ok( true === $answer['success'] && 30.5 === (float) $answer['data']['progress']['watched_duration'] && 5.08 === (float) $answer['data']['progress']['watch_percentage'] && false === $answer['data']['progress']['is_completed'] && false === $answer['data']['lesson_completed'], 'first report: 30.5 s of a 600 s file (the client said 60), fractions kept: ' . wp_json_encode( $answer ) );
	$row = $tracker->get_progress( $learner, $lessons['upload'] );
	ok( 600.0 === (float) $row->total_duration && 30.5 === (float) $row->last_position && $fixture_course === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT course_id FROM %i WHERE id = %d', $wpdb->prefix . 'ohmylms_video_progress', $row->id ) ), 'the first insert stores every column in its own format' );
	ok( array( array( 0.0, 30.5 ) ) === VideoCompletion::seen( $learner, $lessons['upload'] ), 'the spans are stored' );
	$age = static function ( $seconds ) use ( $wpdb, $learner, $lessons ) {
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET last_updated = NOW() - INTERVAL %d SECOND WHERE user_id = %d AND lesson_id = %d', $wpdb->prefix . 'ohmylms_video_progress', $seconds, $learner, $lessons['upload'] ) );
	};
	$age( 120 );
	$answer = $save(
		$lessons['upload'],
		array(
			'total_duration' => '600',
			'last_position'  => '150',
			'segments'       => '[[20,150]]',
		)
	);
	ok( 150.0 === (float) $answer['data']['progress']['watched_duration'] && 25.0 === (float) $answer['data']['progress']['watch_percentage'], 'a later visit adds what was not watched yet: ' . wp_json_encode( $answer['data'] ) );
	$age( 120 );
	$answer = $save(
		$lessons['upload'],
		array(
			'total_duration' => '600',
			'last_position'  => '150',
			'segments'       => '[[0,150]]',
		)
	);
	ok( 150.0 === (float) $answer['data']['progress']['watched_duration'], 'watching a part again adds nothing' );
	$age( 30 );
	$answer = $save(
		$lessons['upload'],
		array(
			'total_duration' => '600',
			'last_position'  => '600',
			'segments'       => '[[150,600]]',
		)
	);
	ok( (float) $answer['data']['progress']['watched_duration'] <= 240.0 && false === $answer['data']['progress']['is_completed'], 'a jump to the end is bounded by elapsed time (2 × 30 s + 30 s): ' . wp_json_encode( $answer['data'] ) );
	$age( 200 );
	$answer = $save(
		$lessons['upload'],
		array(
			'total_duration' => '600',
			'last_position'  => '600',
			'segments'       => '[[150,600]]',
		)
	);
	ok( true === $answer['data']['progress']['is_completed'] && (float) $answer['data']['progress']['watch_percentage'] >= 50.0, 'the lesson\'s 50 % is reached once enough time passed: ' . wp_json_encode( $answer['data'] ) );
	ok( false === $answer['data']['lesson_completed'] && ! \OhMyLMS\Assignment\Delivery::completed( $learner, $lessons['upload'] ), 'saving progress does not complete the lesson' );

	// ---- Completion goes through ohmylms_lesson_completed, which fires every hook --------------
	$fired = array();
	foreach ( array( 'ohmylms_lesson_completed', 'ohmylms_after_lesson_completed', 'ohmylms_video_lesson_completed' ) as $hook ) {
		add_action(
			$hook,
			static function () use ( &$fired, $hook ) {
				$fired[] = $hook;
			}
		);
	}
	$done = ajax(
		array( \OhMyLMS\Ajax::class, 'lesson_completed' ),
		array(
			'lesson_id' => (string) $lessons['upload'],
			'nonce'     => wp_create_nonce( 'lesson_completed_nonce' ),
		)
	);
	ok( true === $done['success'] && \OhMyLMS\Assignment\Delivery::completed( $learner, $lessons['upload'] ), 'the player\'s completion request completes the lesson: ' . wp_json_encode( $done ) );
	ok( in_array( 'ohmylms_lesson_completed', $fired, true ) && in_array( 'ohmylms_after_lesson_completed', $fired, true ) && in_array( 'ohmylms_video_lesson_completed', $fired, true ), 'with the completion hooks and the video one: ' . wp_json_encode( $fired ) );
	$answer = $save(
		$lessons['upload'],
		array(
			'total_duration' => '600',
			'last_position'  => '10',
			'segments'       => '[[0,10]]',
		)
	);
	ok( true === $answer['data']['lesson_completed'], 'later saves say the lesson is completed' );
	fresh_request();
	$context = island( render_lesson( $lessons['upload'] ) )['context'];
	ok( $context['videoDone'] && $context['lessonDone'] && 0.0 === (float) $context['resume'], 'a completed video starts over on the next visit' );

	// ---- A forged report cannot complete a rule lesson -------------------------------------------
	wp_set_current_user( $forger );
	$answer = $save(
		$lessons['upload'],
		array(
			'watched_duration' => '600',
			'total_duration'   => '10',
			'last_position'    => '600',
			'segments'         => '[[0,600]]',
		),
		wp_create_nonce( 'video_progress_nonce' )
	);
	ok( (float) $answer['data']['progress']['watched_duration'] <= 120.0 && false === $answer['data']['progress']['is_completed'], 'one forged report counts two minutes at most, of the real duration: ' . wp_json_encode( $answer['data'] ) );
	$refused = ajax(
		array( \OhMyLMS\Ajax::class, 'lesson_completed' ),
		array(
			'lesson_id' => (string) $lessons['upload'],
			'nonce'     => wp_create_nonce( 'lesson_completed_nonce' ),
		)
	);
	ok( false === $refused['success'] && 'ohmylms_lesson_video_not_watched' === $refused['data']['code'], 'and "complete" is refused: ' . wp_json_encode( $refused ) );

	// ---- Older clients and bad requests -----------------------------------------------------------
	wp_set_current_user( $learner );
	$answer = $save(
		$lessons['youtube'],
		array(
			'watched_duration' => '95',
			'total_duration'   => '100',
			'last_position'    => '95',
		)
	);
	ok( true === $answer['data']['progress']['is_completed'] && false === $answer['data']['lesson_completed'], 'a report without spans (an older client) still works; the site threshold applies' );
	$answer = $save(
		$lessons['youtube'],
		array(
			'watched_duration' => '20',
			'total_duration'   => '100',
			'last_position'    => '20',
			'segments'         => '{"not":"pairs"}',
		)
	);
	ok( true === $answer['success'] && 20.0 === (float) $answer['data']['progress']['watched_duration'], 'invalid spans are ignored (the reported duration is used)' );
	ok( null === VideoCompletion::parse_intervals( wp_json_encode( array_fill( 0, 501, array( 0, 1 ) ) ) ) && array( array( 0.0, 3.0 ) ) === VideoCompletion::parse_intervals( '[[2,3],[0,2.1]]' ), 'span lists are bounded and merged' );
	$read = ajax(
		array( \OhMyLMS\Ajax::class, 'get_video_progress' ),
		array(
			'lesson_id' => (string) $lessons['youtube'],
			'nonce'     => $nonce,
		)
	);
	ok( 20.0 === (float) $read['data']['progress']['last_position'], 'ohmylms_get_video_progress still answers' );
	$cleared = ajax(
		array( \OhMyLMS\Ajax::class, 'clear_video_progress' ),
		array(
			'lesson_id' => (string) $lessons['upload'],
			'nonce'     => $nonce,
		)
	);
	ok( true === $cleared['success'] && array() === VideoCompletion::seen( $learner, $lessons['upload'] ) && VideoCompletion::satisfied( $learner, $lessons['upload'] ), 'clearing progress forgets the spans; a completed lesson stays satisfied' );
	$forged = $save( $lessons['upload'], array( 'segments' => '[[0,1]]' ), 'bad-nonce' );
	ok( ! is_array( $forged ) || true !== ( $forged['success'] ?? null ), 'a bad nonce is refused' );
	wp_set_current_user( $stranger );
	$answer = $save(
		$lessons['upload'],
		array(
			'segments'       => '[[0,1]]',
			'total_duration' => '600',
		),
		wp_create_nonce( 'video_progress_nonce' )
	);
	ok( false === $answer['success'], 'a learner who is not enrolled cannot save progress' );

	fwrite( STDOUT, "OK video-player-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	wp_set_current_user( 0 );
	foreach ( $options as $name => $value ) {
		if ( null === $value ) {
			delete_option( $name );
		} else {
			update_option( $name, $value );
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
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	$left = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_title LIKE %s', $wpdb->posts, $wpdb->esc_like( $tag ) . '%' ) );
	if ( $left ) {
		fwrite( STDERR, "Cleanup left $left posts tagged $tag\n" );
		$exit_code = 1;
	}
}

exit( $exit_code );
