<?php
/**
 * Design settings groups and course migration REST integration test.
 *
 * Runs against a WordPress install (the Local site by default). Every option it changes is restored
 * in `finally`. Migration uses a fake source added through the `ohmylms_migrations` filter (a test
 * post type and migrator), so no other LMS plugin is needed; created posts are deleted.
 *
 * Usage (set OHMYLMS_DESIGN_MIGRATION_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/design-migration-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Files.OneObjectStructurePerFile, Generic.Formatting.MultipleStatementAlignment

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_DESIGN_MIGRATION_TEST' ) ) {
	exit( "Set OHMYLMS_DESIGN_MIGRATION_TEST=1 to run the design and migration REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

/**
 * Fake source migrator: copies the title into a draft OhMyLMS course.
 */
class OhMyLMS_Test_Migrator {
	/**
	 * Source course.
	 *
	 * @var int
	 */
	private $id = 0;

	/**
	 * Remember the source course.
	 *
	 * @param int $id Source course ID.
	 * @return bool
	 */
	public function init( $id ) {
		$this->id = (int) $id;
		return true;
	}

	/**
	 * Create the copy.
	 *
	 * @return int
	 */
	public function migrate_course() {
		return wp_insert_post(
			array(
				'post_type'   => 'ohmylms-course',
				'post_status' => 'draft',
				'post_title'  => get_the_title( $this->id ) . ' (migrated)',
			)
		);
	}
}

$checks   = 0;
$tag      = 'dmtest' . strtolower( wp_generate_password( 6, false, false ) );
$user_ids = array();
$options  = array();

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
 * @param string $path   Path below /ohmylms/v1.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( (object) $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Error code of a response.
 *
 * @param WP_REST_Response $response Response.
 * @return string
 */
function code_of( $response ) {
	$data = $response->get_data();
	return is_array( $data ) && isset( $data['code'] ) ? (string) $data['code'] : '';
}

register_post_type( $tag, array( 'public' => false ) );
add_filter(
	'ohmylms_migrations',
	static function ( $sources ) use ( $tag ) {
		$sources['fakeLms'] = array(
			'title'       => 'Fake LMS',
			'description' => 'Test source.',
			'class'       => 'OhMyLMS_Test_Migrator',
			'post_type'   => $tag,
		);
		return $sources;
	}
);

foreach ( array_merge( array_column( array_values( \OhMyLMS\Settings\DesignSettings::fields( 'course-archive' ) ), 'option' ), array_keys( \OhMyLMS\Design\Tokens::QUIZ_COLORS ) ) as $option ) {
	$options[ $option ] = get_option( $option, null );
}

try {
	$admins = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'fields'  => 'ID',
			'orderby' => 'ID',
		)
	);
	$admin  = (int) $admins[0];
	$editor = wp_insert_user(
		array(
			'user_login' => $tag . '_editor',
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . '@example.invalid',
			'role'       => 'editor',
		)
	);
	$user_ids[] = $editor;

	// Permissions.
	wp_set_current_user( 0 );
	ok( 401 === call_api( 'GET', '/settings/course-archive' )->get_status(), 'anonymous design read is 401' );
	ok( 401 === call_api( 'GET', '/migrations' )->get_status(), 'anonymous migration read is 401' );
	wp_set_current_user( $editor );
	ok( 403 === call_api( 'PATCH', '/settings/quiz-design', array( 'stage' => '#000000' ) )->get_status(), 'editor design write is 403' );
	ok( 403 === call_api( 'GET', '/migrations/fakeLms/courses' )->get_status(), 'editor cannot list source courses' );
	ok( 403 === call_api( 'POST', '/migrations/fakeLms', array( 'course_id' => 1 ) )->get_status(), 'editor cannot migrate' );
	wp_set_current_user( $admin );

	// Course archive group.
	foreach ( $options as $option => $value ) {
		delete_option( $option );
	}
	$read = call_api( 'GET', '/settings/course-archive' );
	ok( 200 === $read->get_status() && 'course-archive' === $read->get_data()['group'], 'archive group readable' );
	ok( 10 === $read->get_data()['values']['courses_per_page'] && 'grid' === $read->get_data()['values']['layout'], 'defaults apply' );
	update_option( 'ohmylms_archive_page_layout', 'evil' );
	update_option( 'ohmylms_archive_page_row', array( array( 'row_heading' => 'X', 'row_display_criteria' => 'system' ) ) );
	$values = call_api( 'GET', '/settings/course-archive' )->get_data()['values'];
	ok( 'grid' === $values['layout'] && array() === $values['rows'], 'malformed stored values read safely' );

	$saved = call_api(
		'PATCH',
		'/settings/course-archive',
		array(
			'layout'          => 'list',
			'columns_per_row' => 3,
			'show_filters'    => true,
			'filters'         => array( 'category', 'tag' ),
			'rows'            => array(
				array(
					'heading'  => '<b>Popular</b> courses',
					'criteria' => 'best_selling',
				),
			),
		)
	);
	ok( 200 === $saved->get_status(), 'archive save is 200' );
	ok( array( 'layout', 'columns_per_row', 'show_filters', 'filters', 'rows' ) === $saved->get_data()['changed'], 'changed fields listed' );
	ok( 'list' === get_option( 'ohmylms_archive_page_layout' ) && 3 === (int) get_option( 'ohmylms_columns_per_row' ) && 'yes' === get_option( 'ohmylms_archive_page_filter_is_enabled' ), 'stored in the existing options' );
	ok( array( array( 'row_display_criteria' => 'best_selling', 'row_heading' => 'Popular courses' ) ) === get_option( 'ohmylms_archive_page_row' ), 'rows stored in the template shape, tags stripped' );
	ok( array() === call_api( 'PATCH', '/settings/course-archive', array( 'layout' => 'list' ) )->get_data()['changed'], 'unchanged save writes nothing' );

	$unknown = call_api( 'PATCH', '/settings/course-archive', array( 'single_course_page_layout' => 'layout_1' ) );
	ok( 400 === $unknown->get_status() && 'ohmylms_settings_unknown_field' === code_of( $unknown ), 'unknown field is 400' );
	ok( 400 === call_api( 'PATCH', '/settings/course-archive', array( 'layout' => 'masonry' ) )->get_status(), 'bad enum is 400' );
	ok( 400 === call_api( 'PATCH', '/settings/course-archive', array( 'rows' => array( array( 'heading' => 'X', 'criteria' => 'system' ) ) ) )->get_status(), 'row criteria outside the allow-list is 400' );
	ok( 400 === call_api( 'PATCH', '/settings/course-archive', array( 'columns_per_row' => 9 ) )->get_status(), 'columns above 6 is 400' );
	ok( 400 === call_api( 'PATCH', '/settings/course-archive', array( 'filters' => array( 'tag', 'tag' ) ) )->get_status(), 'duplicate filters are 400' );
	$empty = call_api( 'PATCH', '/settings/course-archive', array( 'rows' => array( array( 'heading' => '<i></i>', 'criteria' => 'all' ) ) ) );
	ok( 422 === $empty->get_status() && isset( $empty->get_data()['data']['params']['rows'] ), 'heading empty after sanitising is 422' );
	$none = call_api( 'PATCH', '/settings/course-archive', array( 'filters' => array(), 'layout' => 'grid' ) );
	ok( 422 === $none->get_status() && 'list' === get_option( 'ohmylms_archive_page_layout' ), 'filter panel without filters is 422 and nothing is written' );
	$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/settings/course-archive' ) )->get_data()['schema'];
	ok( 'settings-course-archive' === $schema['title'] && isset( $schema['properties']['rows'] ), 'schema served' );

	// Quiz design group.
	$quiz = call_api( 'PATCH', '/settings/quiz-design', array( 'stage' => '#ABC', 'player_answer_4' => '#123456' ) );
	ok( 200 === $quiz->get_status() && '#aabbcc' === $quiz->get_data()['values']['stage'], 'colours normalised' );
	ok( '#aabbcc' === get_option( 'ohmylms_quiz_stage_color' ), 'colour stored in its option' );
	ok( 20 === count( $quiz->get_data()['values'] ), 'twenty colours' );
	ok( 400 === call_api( 'PATCH', '/settings/quiz-design', array( 'stage' => 'red' ) )->get_status(), 'named colour is 400' );

	// Migration.
	$sources = call_api( 'GET', '/migrations' )->get_data();
	$ids     = array_column( $sources, 'id' );
	ok( in_array( 'tutorLMS', $ids, true ) && in_array( 'fakeLms', $ids, true ), 'sources listed' );
	ok( array( 'id', 'title', 'description', 'available' ) === array_keys( $sources[0] ) && ! isset( $sources[0]['class'] ), 'source rows hide class names' );
	if ( ! defined( 'TUTOR_VERSION' ) ) {
		$inactive = call_api( 'GET', '/migrations/tutorLMS/courses' );
		ok( 409 === $inactive->get_status() && 'ohmylms_migration_source_inactive' === code_of( $inactive ), 'inactive source is 409' );
	}
	ok( 404 === call_api( 'GET', '/migrations/nope/courses' )->get_status(), 'unknown source is 404' );

	$first  = wp_insert_post( array( 'post_type' => $tag, 'post_status' => 'publish', 'post_title' => $tag . ' Alpha' ) );
	$second = wp_insert_post( array( 'post_type' => $tag, 'post_status' => 'draft', 'post_title' => $tag . ' Beta' ) );
	$list   = call_api( 'GET', '/migrations/fakeLms/courses', array( 'per_page' => 1 ) );
	ok( 200 === $list->get_status() && '2' === $list->get_headers()['X-WP-Total'] && '2' === $list->get_headers()['X-WP-TotalPages'], 'source courses paged' );
	ok( $tag . ' Alpha' === $list->get_data()[0]['title'] && null === $list->get_data()[0]['migrated_to'], 'not migrated yet' );
	ok( 400 === call_api( 'GET', '/migrations/fakeLms/courses', array( 'per_page' => 51 ) )->get_status(), 'per_page above 50 is 400' );

	$wrong = call_api( 'POST', '/migrations/fakeLms', array( 'course_id' => $editor ) );
	ok( 404 === $wrong->get_status() && 'ohmylms_migration_course_not_found' === code_of( $wrong ), 'non-source course is 404' );
	$done = call_api( 'POST', '/migrations/fakeLms', array( 'course_id' => $first ) );
	ok( 200 === $done->get_status() && 'ohmylms-course' === get_post_type( $done->get_data()['migrated_to'] ), 'course migrated' );
	ok( 'fakeLms:' . $first === get_post_meta( $done->get_data()['migrated_to'], '_ohmylms_migrated_from', true ), 'link to the source recorded' );
	$again = call_api( 'POST', '/migrations/fakeLms', array( 'course_id' => $first ) );
	ok( 409 === $again->get_status() && $done->get_data()['migrated_to'] === $again->get_data()['data']['course_id'], 'second migration is 409' );
	ok( $done->get_data()['migrated_to'] === call_api( 'GET', '/migrations/fakeLms/courses' )->get_data()[0]['migrated_to'], 'list shows the copy' );
	$legacy = call_api( 'POST', '/migrations/fakeLms', array( 'fakeLms' => array( 'course_id' => $second ) ) );
	ok( 200 === $legacy->get_status(), 'legacy nested body accepted' );

	fwrite( STDOUT, "OK design-migration-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	global $wpdb;
	foreach ( $options as $option => $value ) {
		if ( null === $value ) {
			delete_option( $option );
		} else {
			update_option( $option, $value );
		}
	}
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ohmylms_migrated_from' AND meta_value LIKE %s", 'fakeLms:%' ) ) as $id ) {
		wp_delete_post( (int) $id, true );
	}
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", $tag ) ) as $id ) {
		wp_delete_post( (int) $id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
