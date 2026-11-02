<?php
/**
 * Links of lessons, quizzes and assignments by course status: a published course keeps its pretty
 * links; a draft or private course with a slug serves as the base by that slug; a course without a
 * slug and unpublished content fall back to `?post_type=<type>&p=<id>`. Every link, and the editor's
 * preview link of a draft lesson, is fetched as an administrator and must open that content.
 *
 * Creates tagged courses, chapters and content and deletes them; no other data is changed.
 *
 * Usage (set OHMYLMS_CONTENT_PERMALINKS_TEST=1):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/content-permalinks-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.WP.AlternativeFunctions, WordPress.DB.DirectDatabaseQuery

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_CONTENT_PERMALINKS_TEST' ) ) {
	exit( "Set OHMYLMS_CONTENT_PERMALINKS_TEST=1 to run the content permalink tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$checks    = 0;
$warnings  = array();
$post_ids  = array();
$token     = null;
$admin     = 0;
$tag       = 'cplink' . strtolower( wp_generate_password( 6, false, false ) );
$exit_code = 0;

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
 * A course with one chapter holding the given content.
 *
 * @param array $course  Course fields.
 * @param array $content Content fields, one entry per item.
 * @return array{course:int, items:int[]}
 */
function make_course( array $course, array $content ) {
	global $wpdb, $tag;
	$course_id = make_post( $course + array( 'post_type' => 'ohmylms-course' ) );
	$chapter   = make_post(
		array(
			'post_type'  => 'ohmylms-chapter',
			'post_title' => "$tag chapter $course_id",
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
	$items = array();
	foreach ( $content as $order => $fields ) {
		$id = make_post( $fields );
		if ( 'ohmylms-lesson' === $fields['post_type'] ) {
			update_post_meta( $id, '_type', 'text' );
		}
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id'   => $chapter,
				'content_id'   => $id,
				'content_type' => 'ohmylms-lesson' === $fields['post_type'] ? 'text' : str_replace( 'ohmylms-', '', $fields['post_type'] ),
				'order_number' => $order + 1,
			)
		);
		$items[] = $id;
	}
	return array(
		'course' => $course_id,
		'items'  => $items,
	);
}

/**
 * Lesson, quiz and assignment fields for a course.
 *
 * @param string $label  Title label.
 * @param string $status Content status.
 * @return array[]
 */
function trio( $label, $status = 'publish' ) {
	global $tag;
	$items = array();
	foreach ( array( 'ohmylms-lesson', 'ohmylms-quiz', 'ohmylms-assignment' ) as $type ) {
		$items[] = array(
			'post_type'    => $type,
			'post_status'  => $status,
			'post_title'   => "$tag $label " . str_replace( 'ohmylms-', '', $type ),
			'post_content' => '<p>' . esc_html( "$tag $label body" ) . '</p>',
		);
	}
	return $items;
}

/**
 * GET a URL with the administrator's cookies, following redirects.
 *
 * @param string $url    URL.
 * @param string $cookie Cookie header.
 * @return array{status:int, body:string, url:string}
 */
function fetch( $url, $cookie ) {
	$curl = curl_init( $url );
	curl_setopt_array(
		$curl,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_COOKIE         => $cookie,
			CURLOPT_TIMEOUT        => 60,
		)
	);
	$body   = (string) curl_exec( $curl );
	$status = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
	$final  = (string) curl_getinfo( $curl, CURLINFO_EFFECTIVE_URL );
	return array(
		'status' => $status,
		'body'   => $body,
		'url'    => $final,
	);
}

/**
 * Whether a page is the singular view of a post.
 *
 * @param string $body Page HTML.
 * @param int    $id   Post ID.
 * @return bool
 */
function shows_post( $body, $id ) {
	return (bool) preg_match( '/<body[^>]*\bclass="[^"]*\bpostid-' . (int) $id . '\b/', $body )
		|| false !== strpos( $body, esc_html( get_the_title( $id ) ) );
}

try {
	ok( (bool) get_option( 'permalink_structure' ), 'the site uses pretty permalinks' );
	$structure  = ohmylms_get_permalink_structure();
	$bases      = array(
		'ohmylms-lesson'     => $structure['lesson_base'],
		'ohmylms-quiz'       => $structure['quiz_base'],
		'ohmylms-assignment' => 'assignments',
	);
	$admins     = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	$admin      = (int) $admins[0];
	$query_link = static function ( $id ) {
		return add_query_arg(
			array(
				'post_type' => get_post_type( $id ),
				'p'         => $id,
			),
			home_url( '/' )
		);
	};

	$published  = make_course( array( 'post_title' => "$tag published" ), trio( 'published' ) );
	$draft      = make_course(
		array(
			'post_title'  => "$tag draft",
			'post_name'   => "$tag-draft-course",
			'post_status' => 'draft',
		),
		array_merge( trio( 'draft' ), array_slice( trio( 'unpublished', 'draft' ), 0, 1 ) )
	);
	$unslugged  = make_course(
		array(
			'post_title'  => "$tag unslugged",
			'post_status' => 'draft',
		),
		trio( 'unslugged' )
	);
	$private    = make_course(
		array(
			'post_title'  => "$tag private",
			'post_status' => 'private',
		),
		trio( 'private' )
	);
	$draft_slug = get_post_field( 'post_name', $draft['course'] );
	ok( '' === get_post_field( 'post_name', $unslugged['course'] ), 'a draft course saved without a slug has none' );
	ok( false !== strpos( get_permalink( $draft['course'] ), '?' ), 'WordPress links the draft course by query string' );

	wp_set_current_user( 0 );
	$links = array();

	// Published course: unchanged pretty links under the course URL.
	$course_url = trailingslashit( get_permalink( $published['course'] ) );
	foreach ( $published['items'] as $id ) {
		$expected = $course_url . $bases[ get_post_type( $id ) ] . '/' . get_post_field( 'post_name', $id ) . '/';
		ok( get_permalink( $id ) === $expected, 'published course: ' . get_permalink( $id ) . " is $expected" );
		ok( ohmylms_get_course( $published['course'] )->get_content_link( $id ) === $expected, 'published course: get_content_link() matches' );
		$links[ $id ] = $expected;
	}

	// Draft course with a slug: the pretty structure by that slug, never the draft course link.
	foreach ( array_slice( $draft['items'], 0, 3 ) as $id ) {
		$expected = home_url( '/' . $structure['course_base'] . "/$draft_slug/" . $bases[ get_post_type( $id ) ] . '/' . get_post_field( 'post_name', $id ) . '/' );
		ok( get_permalink( $id ) === $expected, 'draft course: ' . get_permalink( $id ) . " is $expected" );
		ok( ohmylms_get_pretty_content_permalink( $id ) === $expected, 'draft course: the pretty helper agrees for ' . $id );
		$links[ $id ] = $expected;
	}

	// Draft course without a slug: the content's own query link.
	foreach ( $unslugged['items'] as $id ) {
		ok( get_permalink( $id ) === $query_link( $id ), 'course without a slug: ' . get_permalink( $id ) );
		ok( ohmylms_get_pretty_content_permalink( $id ) === $query_link( $id ), 'course without a slug: the pretty helper agrees for ' . $id );
		$links[ $id ] = $query_link( $id );
	}

	// Private course: pretty by slug even for a visitor who cannot read the course.
	$private_slug = get_post_field( 'post_name', $private['course'] );
	foreach ( $private['items'] as $id ) {
		$expected = home_url( '/' . $structure['course_base'] . "/$private_slug/" . $bases[ get_post_type( $id ) ] . '/' . get_post_field( 'post_name', $id ) . '/' );
		ok( get_permalink( $id ) === $expected, 'private course: ' . get_permalink( $id ) . " is $expected" );
		$links[ $id ] = $expected;
	}

	// No link carries a path inside its query string (the old `&p=446/assignments/…`).
	foreach ( $links as $id => $link ) {
		$query = (string) wp_parse_url( $link, PHP_URL_QUERY );
		ok( false === strpos( $query, '/' ), "link of $id has no path in its query: $link" );
	}

	// A draft lesson: linked and previewed by ID.
	$unpublished = $draft['items'][3];
	ok( get_permalink( $unpublished ) === $query_link( $unpublished ), 'draft lesson: ' . get_permalink( $unpublished ) );
	ok( ohmylms_get_pretty_content_permalink( $unpublished ) === $query_link( $unpublished ), 'draft lesson: the pretty helper agrees' );
	$preview = get_preview_post_link( $unpublished );
	ok( add_query_arg( 'preview', 'true', $query_link( $unpublished ) ) === $preview, "draft lesson preview link: $preview" );

	// Live: each link opens its content for an administrator.
	$manager = WP_Session_Tokens::get_instance( $admin );
	$token   = $manager->create( time() + HOUR_IN_SECONDS );
	$expires = time() + HOUR_IN_SECONDS;
	$cookie  = AUTH_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $admin, $expires, 'auth', $token ) ) . '; ' . LOGGED_IN_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $admin, $expires, 'logged_in', $token ) );
	foreach ( $links + array( $unpublished => $preview ) as $id => $link ) {
		$page = fetch( $link, $cookie );
		ok( 200 === $page['status'], "$link answers 200, not {$page['status']}" );
		ok( shows_post( $page['body'], $id ), "$link (now {$page['url']}) shows " . get_the_title( $id ) );
	}

	ok( ! $warnings, 'no PHP warnings from the plugin: ' . implode( ' | ', $warnings ) );
	fwrite( STDOUT, "OK content-permalinks-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	wp_set_current_user( 0 );
	if ( $token && $admin ) {
		WP_Session_Tokens::get_instance( $admin )->destroy( $token );
	}
	foreach ( array_unique( array_map( 'intval', $post_ids ) ) as $post_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'content_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'chapter_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'chapter_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $post_id ) );
		wp_delete_post( $post_id, true );
	}
	restore_error_handler();
}

exit( $exit_code );
