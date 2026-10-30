<?php
/**
 * Setup wizard REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default). Every option the wizard can touch
 * is snapshotted straight from the options table first and restored byte for byte in `finally`;
 * temporary pages, curriculum items and users are removed. Outbound HTTP requests are blocked and
 * counted: the wizard must make none.
 *
 * Usage (set OHMYLMS_SETUP_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/setup-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.PreparedSQL

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_SETUP_TEST' ) ) {
	exit( "Set OHMYLMS_SETUP_TEST=1 to run the setup wizard REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Curriculum\Items;
use OhMyLMS\Settings\SettingsRegistry;
use OhMyLMS\SetupWizard\SetupProgress;
use OhMyLMS\SetupWizard\SetupSteps;

global $wpdb;

$checks   = 0;
$post_ids = array();
$user_ids = array();
$tag      = 'wiztest' . strtolower( wp_generate_password( 6, false, false ) );
$fired    = array(
	'started'   => 0,
	'completed' => 0,
	'skipped'   => array(),
);
$http     = 0;

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
 * @param string $path   Path below /ohmylms/v1.
 * @param mixed  $data   JSON body (null for none); the query for GET.
 * @return WP_REST_Response
 */
function call_api( $method, $path, $data = null ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( (array) $data );
	} elseif ( null !== $data ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( is_string( $data ) ? $data : wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Names of the options the wizard can touch.
 *
 * @return string[]
 */
function touched_options() {
	$names = array( SetupProgress::OPTION );
	foreach ( array( 'currency', 'general' ) as $group ) {
		foreach ( SettingsRegistry::group( $group )['fields'] as $name => $field ) {
			$names[] = SettingsRegistry::option_name( $name, $field );
		}
	}
	return array_values( array_unique( $names ) );
}

/**
 * Read the raw rows of the touched options.
 *
 * @return array<string, array|null>
 */
function snapshot_options() {
	global $wpdb;
	$rows = array();
	foreach ( touched_options() as $name ) {
		$row           = $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name ), ARRAY_A );
		$rows[ $name ] = $row ? $row : null;
	}
	return $rows;
}

/**
 * Put the options back exactly.
 *
 * @param array $snapshot Result of snapshot_options().
 */
function restore_options( array $snapshot ) {
	global $wpdb;
	foreach ( $snapshot as $name => $row ) {
		if ( null === $row ) {
			$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
		} elseif ( null === $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $name ) ) ) {
			$wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => $name,
					'option_value' => $row['option_value'],
					'autoload'     => $row['autoload'],
				)
			);
		} else {
			$wpdb->update(
				$wpdb->options,
				array(
					'option_value' => $row['option_value'],
					'autoload'     => $row['autoload'],
				),
				array( 'option_name' => $name )
			);
		}
	}
	wp_cache_flush();
}

/**
 * Create a temporary page.
 *
 * @param string $status Post status.
 * @param string $slug   Slug.
 * @param string $title  Title.
 * @return int
 */
function make_page( $status, $slug, $title ) {
	global $post_ids;
	$id         = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => $status,
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => 'original content',
		)
	);
	$post_ids[] = $id;
	return (int) $id;
}

/**
 * Assert an error response.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   HTTP status.
 * @param string           $code     Error code.
 * @param string[]         $fields   Fields expected in data.params.
 * @param string           $label    Label.
 */
function ok_error( $response, $status, $code, array $fields, $label ) {
	$data = $response->get_data();
	ok( $status === $response->get_status(), $label . ': status ' . $status . ', got ' . $response->get_status() . ' ' . wp_json_encode( $data ) );
	ok( isset( $data['code'] ) && $code === $data['code'], $label . ': code ' . $code . ', got ' . wp_json_encode( $data ) );
	foreach ( $fields as $field ) {
		ok( isset( $data['data']['params'][ $field ] ), $label . ': params names ' . $field );
	}
}

/**
 * The state part of a step response.
 *
 * @param WP_REST_Response $response Response.
 * @return array
 */
function state_of( $response ) {
	$data = $response->get_data();
	return isset( $data['state'] ) ? $data['state'] : $data;
}

/**
 * Status of one step in a state.
 *
 * @param array  $state State.
 * @param string $step  Step id.
 * @return string
 */
function step_status( array $state, $step ) {
	foreach ( $state['steps'] as $row ) {
		if ( $row['id'] === $step ) {
			return $row['status'];
		}
	}
	return '';
}

$before = snapshot_options();
add_action(
	'ohmylms_onboarding_started',
	static function () use ( &$fired ) {
		++$fired['started'];
	}
);
add_action(
	'ohmylms_onboarding_completed',
	static function () use ( &$fired ) {
		++$fired['completed'];
	}
);
add_action(
	'ohmylms_onboarding_skipped',
	static function ( $step ) use ( &$fired ) {
		$fired['skipped'][] = $step;
	}
);
add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) use ( &$http ) {
		++$http;
		return new WP_Error( 'blocked', 'Blocked by the test: ' . $url );
	},
	10,
	3
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
	$admin = (int) $admins[0];

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

	// A user who may manage options but not publish pages.
	$limited = wp_insert_user(
		array(
			'user_login' => $tag . '_lim',
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . '_lim@example.invalid',
			'role'       => 'editor',
		)
	);
	ok( ! is_wp_error( $limited ), 'temporary limited user created' );
	$user_ids[] = $limited;
	$limited_user = new WP_User( $limited );
	$limited_user->add_cap( 'manage_options' );
	$limited_user->add_cap( 'publish_pages', false );

	ok( array( 'welcome', 'experience', 'preferences', 'niche', 'pages', 'import' ) === SetupSteps::ids(), 'six steps in wizard order' );

	// ---- Routes and permissions -----------------------------------------------------------
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	$paths  = array( '/setup', '/setup/complete', '/setup/dismiss', '/setup/reset', '/site-setup/navigation-menus', '/site-setup/subjects-page', '/site-setup/explore-menu' );
	foreach ( SetupSteps::ids() as $step ) {
		$paths[] = '/setup/steps/(?P<step>' . implode( '|', SetupSteps::ids() ) . ')';
		$paths[] = '/setup/steps/(?P<step>' . implode( '|', SetupSteps::ids() ) . ')/skip';
	}
	foreach ( array_unique( $paths ) as $path ) {
		ok( isset( $routes[ '/ohmylms/v1' . $path ] ), 'route registered: ' . $path );
		foreach ( $routes[ '/ohmylms/v1' . $path ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], 'real permission callback on ' . $path );
		}
	}
	ok( ! isset( $routes['/ohmylms/v1/setup-wizard'] ) && ! isset( $routes['/ohmylms/v1/setup-wizard/activate-addon'] ) && ! isset( $routes['/ohmylms/v1/setup-wizard/import-course'] ), 'the old unsafe routes (activate-addon, import-course) are gone' );
	$schema = rest_get_server()->get_data_for_route( '/ohmylms/v1/setup', $routes['/ohmylms/v1/setup'], 'help' );
	ok( isset( $schema['schema']['properties']['starter_pages'], $schema['schema']['properties']['current_step'] ), 'OPTIONS schema is served' );

	$cases = array(
		array( 'GET', '/setup' ),
		array( 'POST', '/setup/steps/welcome' ),
		array( 'POST', '/setup/steps/pages' ),
		array( 'POST', '/setup/steps/pages/skip' ),
		array( 'POST', '/setup/complete' ),
		array( 'POST', '/setup/dismiss' ),
		array( 'POST', '/setup/reset' ),
		array( 'GET', '/site-setup/navigation-menus' ),
		array( 'POST', '/site-setup/subjects-page' ),
		array( 'POST', '/site-setup/explore-menu' ),
	);
	foreach ( array(
		0           => 401,
		$subscriber => 403,
	) as $user => $status ) {
		wp_set_current_user( $user );
		foreach ( $cases as $case ) {
			$response = call_api( $case[0], $case[1], 'GET' === $case[0] ? array() : array( 'menu_id' => 1 ) );
			ok( $status === $response->get_status(), "user {$user} {$case[0]} {$case[1]} is {$status}, got " . $response->get_status() );
		}
	}
	ok( null === get_option( SetupProgress::OPTION, null ) || $before[ SetupProgress::OPTION ], 'denied requests wrote no progress' );

	// ---- Initial state ----------------------------------------------------------------------
	wp_set_current_user( $admin );
	delete_option( SetupProgress::OPTION );
	$response = call_api( 'GET', '/setup' );
	$state    = $response->get_data();
	ok( 200 === $response->get_status() && 'not_started' === $state['status'] && 'welcome' === $state['current_step'] && 1 === $state['version'], 'a fresh site is not started and begins at welcome' );
	ok( 6 === count( $state['steps'] ) && 'pending' === $state['steps'][0]['status'] && false === $state['steps'][5]['available'] && false === $state['steps'][0]['skippable'], 'six pending steps, import unavailable, welcome not skippable' );
	ok( null === $state['answers']['experience'] && array() === $state['answers']['topics'] && null === $state['started_at'], 'no answers yet' );
	ok( null === get_option( SetupProgress::OPTION, null ), 'a GET writes nothing' );
	$state_keys = array_keys( $state );
	sort( $state_keys );
	ok( array( 'answers', 'completed_at', 'current_step', 'preferences', 'started_at', 'starter_pages', 'status', 'steps', 'version' ) === $state_keys, 'state has exactly the documented members' );

	update_option( SetupProgress::OPTION, 'garbage' );
	ok( 'not_started' === call_api( 'GET', '/setup' )->get_data()['status'], 'unreadable stored progress reads as not started' );
	update_option(
		SetupProgress::OPTION,
		array(
			'version' => 99,
			'status'  => 'completed',
		)
	);
	ok( 'not_started' === call_api( 'GET', '/setup' )->get_data()['status'], 'progress of another version reads as not started' );
	delete_option( SetupProgress::OPTION );

	// ---- Validation (nothing is saved) --------------------------------------------------------
	ok_error( call_api( 'POST', '/setup/steps/experience', array( 'experience' => 'wizard' ) ), 400, 'rest_invalid_param', array( 'experience' ), 'bad enum' );
	ok_error( call_api( 'POST', '/setup/steps/experience', array() ), 400, 'rest_invalid_param', array( 'experience' ), 'missing required' );
	ok_error(
		call_api(
			'POST',
			'/setup/steps/experience',
			array(
				'experience' => 'beginner',
				'extra'      => 1,
			)
		),
		400,
		'ohmylms_setup_unknown_field',
		array( 'extra' ),
		'unknown field'
	);
	ok_error( call_api( 'POST', '/setup/steps/welcome', array( 'anything' => true ) ), 400, 'ohmylms_setup_unknown_field', array( 'anything' ), 'welcome takes no fields' );
	ok_error( call_api( 'POST', '/setup/steps/experience', array( 'beginner' ) ), 400, 'ohmylms_setup_invalid_body', array(), 'a list body' );
	ok_error( call_api( 'POST', '/setup/steps/experience', '{not json' ), 400, 'rest_invalid_json', array(), 'malformed JSON' );
	ok_error( call_api( 'POST', '/setup/steps/preferences', array( 'currency' => 'usd' ) ), 400, 'rest_invalid_param', array( 'currency' ), 'currency pattern' );
	ok_error(
		call_api(
			'POST',
			'/setup/steps/preferences',
			array(
				'currency'           => 'USD',
				'price_num_decimals' => 9,
			)
		),
		400,
		'rest_invalid_param',
		array( 'price_num_decimals' ),
		'decimals range'
	);
	ok_error(
		call_api(
			'POST',
			'/setup/steps/preferences',
			array(
				'currency'     => 'USD',
				'currency_pos' => 'middle',
			)
		),
		400,
		'rest_invalid_param',
		array( 'currency_pos' ),
		'currency position enum'
	);
	$before_currency = get_option( 'ohmylms_currency', null );
	ok_error( call_api( 'POST', '/setup/steps/preferences', array( 'currency' => 'ZZZ' ) ), 422, 'ohmylms_setup_invalid_value', array( 'currency' ), 'unknown currency code' );
	ok( get_option( 'ohmylms_currency', null ) === $before_currency, 'a rejected currency changed nothing' );
	ok_error(
		call_api(
			'POST',
			'/setup/steps/niche',
			array(
				'niche'  => 'other',
				'topics' => array_fill( 0, 11, 'x' ),
			)
		),
		400,
		'rest_invalid_param',
		array( 'topics' ),
		'too many topics'
	);
	ok_error(
		call_api(
			'POST',
			'/setup/steps/niche',
			array(
				'niche'  => 'other',
				'topics' => array( str_repeat( 'x', 61 ) ),
			)
		),
		400,
		'rest_invalid_param',
		array( 'topics' ),
		'topic too long'
	);
	ok_error(
		call_api(
			'POST',
			'/setup/steps/niche',
			array(
				'niche'  => 'other',
				'topics' => 'math',
			)
		),
		400,
		'rest_invalid_param',
		array( 'topics' ),
		'topics must be a list'
	);
	ok( 404 === call_api( 'POST', '/setup/steps/nonsense', array() )->get_status(), 'an unknown step has no route (404)' );
	ok( null === get_option( SetupProgress::OPTION, null ), 'rejected requests wrote no progress' );

	// ---- Steps: welcome, experience ------------------------------------------------------------
	$response = call_api( 'POST', '/setup/steps/welcome' );
	$data     = $response->get_data();
	ok( 200 === $response->get_status() && 'in_progress' === $data['state']['status'] && 'done' === step_status( $data['state'], 'welcome' ) && 'experience' === $data['state']['current_step'], 'welcome starts the wizard (empty body allowed)' );
	ok( is_string( $data['state']['started_at'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $data['state']['started_at'] ), 'started_at is an ISO 8601 UTC time' );
	ok( array() === $data['result']['changed'] && array() === $data['result']['pages'], 'step result has the documented members' );
	ok( 1 === $fired['started'], 'ohmylms_onboarding_started fired once' );
	call_api( 'POST', '/setup/steps/welcome' );
	ok( 1 === $fired['started'], 'repeating a step does not fire the started hook again' );

	$response = call_api( 'POST', '/setup/steps/experience', array( 'experience' => 'intermediate' ) );
	$state    = state_of( $response );
	ok( 200 === $response->get_status() && 'intermediate' === $state['answers']['experience'] && 'preferences' === $state['current_step'], 'experience is stored' );
	ok( 'experience' !== $state['current_step'] && false === $state['steps'][1]['skippable'], 'experience cannot be skipped' );
	ok_error( call_api( 'POST', '/setup/steps/experience/skip' ), 409, 'ohmylms_setup_step_required', array(), 'skipping a required step' );
	ok_error( call_api( 'POST', '/setup/steps/welcome/skip' ), 409, 'ohmylms_setup_step_required', array(), 'skipping welcome' );

	// ---- Preferences (written through the settings registry) ----------------------------------
	update_option( 'ohmylms_currency', 'USD' );
	update_option( 'ohmylms_currency_pos', 'left' );
	update_option( 'ohmylms_price_num_decimals', 2 );
	$currency_updates = 0;
	add_action(
		'ohmylms_currency_settings_updated',
		static function () use ( &$currency_updates ) {
			++$currency_updates;
		}
	);
	$response = call_api(
		'POST',
		'/setup/steps/preferences',
		array(
			'currency'           => 'EUR',
			'currency_pos'       => 'right_space',
			'price_num_decimals' => 0,
		)
	);
	$data     = $response->get_data();
	ok( 200 === $response->get_status() && array( 'currency', 'currency_pos', 'price_num_decimals' ) === $data['result']['changed'], 'preferences report the changed fields: ' . wp_json_encode( $data['result'] ) );
	ok( 'EUR' === get_option( 'ohmylms_currency' ) && 'right_space' === get_option( 'ohmylms_currency_pos' ) && 0 === (int) get_option( 'ohmylms_price_num_decimals' ), 'the readers see the registry options' );
	ok( 'EUR' === $data['state']['preferences']['currency'] && 0 === $data['state']['preferences']['price_num_decimals'], 'state shows the live preferences' );
	ok( 3 === $currency_updates, 'the settings hook fired per changed field' );
	$response = call_api(
		'POST',
		'/setup/steps/preferences',
		array(
			'currency'           => 'EUR',
			'currency_pos'       => 'right_space',
			'price_num_decimals' => 0,
		)
	);
	ok( array() === $response->get_data()['result']['changed'] && 3 === $currency_updates, 'repeating preferences changes nothing (idempotent)' );
	$response = call_api( 'POST', '/setup/steps/preferences', array( 'currency' => 'EUR' ) );
	ok( 'right_space' === get_option( 'ohmylms_currency_pos' ), 'fields that are not sent are untouched' );

	// ---- Niche and starter topics ---------------------------------------------------------------
	$topic_a  = 'Wizard topic A ' . $tag;
	$topic_b  = 'Wizard topic B ' . $tag;
	$response = call_api(
		'POST',
		'/setup/steps/niche',
		array(
			'niche'  => 'tech-skills',
			'topics' => array( $topic_a, '  ' . strtoupper( $topic_a ) . ' ', $topic_b, '<b>' . $tag . '</b>' ),
		)
	);
	$state    = state_of( $response );
	ok( 200 === $response->get_status() && 'tech-skills' === $state['answers']['niche'] && array( $topic_a, $topic_b, $tag ) === $state['answers']['topics'], 'topics are trimmed, stripped of tags and de-duplicated: ' . wp_json_encode( $state['answers']['topics'] ) );
	$count = static function () use ( $wpdb, $tag ) {
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Items::table() . ' WHERE name LIKE %s', '%' . $wpdb->esc_like( $tag ) . '%' ) );
	};
	ok( 3 === $count(), 'three curriculum items were created' );
	call_api(
		'POST',
		'/setup/steps/niche',
		array(
			'niche'  => 'tech-skills',
			'topics' => array( $topic_a, $topic_b, $tag ),
		)
	);
	ok( 3 === $count(), 'repeating the niche step creates no duplicate item' );
	$top = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Items::table() . ' WHERE name = %s AND parent_id = 0', $topic_a ) );
	ok( 1 === $top, 'topics are top-level items' );
	$response = call_api( 'POST', '/setup/steps/niche', array( 'niche' => 'other' ) );
	ok( array() === state_of( $response )['answers']['topics'] && 3 === $count(), 'a niche without topics clears the remembered list but deletes nothing' );

	// ---- Starter pages ----------------------------------------------------------------------------
	$kept_page    = make_page( 'publish', 'wiz-kept-' . $tag, 'Kept ' . $tag );
	$adopt_page   = make_page( 'publish', 'wiz-adopt-' . $tag, 'Adopt ' . $tag );
	$draft_page   = make_page( 'draft', 'wiz-draft-' . $tag, 'Draft ' . $tag );
	$pages_filter = static function ( $pages ) use ( $tag ) {
		return array(
			'course'            => array(
				'name'    => 'wiz-course-' . $tag,
				'title'   => 'Wizard course ' . $tag,
				'content' => '',
			),
			'checkout'          => array(
				'name'     => 'wiz-checkout-' . $tag,
				'title'    => 'Wizard checkout ' . $tag,
				'content'  => '<!-- wp:shortcode -->[ohmylms_checkout]<!-- /wp:shortcode -->',
				'template' => 'ohmylms-checkout',
			),
			'student_dashboard' => array(
				'name'    => 'wiz-adopt-' . $tag,
				'title'   => 'Should not replace the title',
				'content' => 'should not replace the content',
			),
			'student_profile'   => array(
				'name'    => 'wiz-draft-' . $tag,
				'title'   => 'Wizard profile ' . $tag,
				'content' => '[profile]',
			),
			'student_courses'   => array(
				'name'    => 'wiz-kept-' . $tag,
				'title'   => 'Kept elsewhere',
				'content' => '',
			),
			'membership'        => array(
				'name'  => 'wiz-membership-' . $tag,
				'title' => 'Not a general setting',
			),
			'bogus key!'        => array(
				'name'  => 'wiz-bogus-' . $tag,
				'title' => 'Invalid key',
			),
			'terms'             => array(
				'name'  => '',
				'title' => 'No slug',
			),
		);
	};
	add_filter( 'ohmylms_default_pages', $pages_filter, 99 );
	foreach ( array( 'course', 'checkout', 'student_dashboard', 'student_profile' ) as $key ) {
		update_option( 'ohmylms_' . $key . '_page_id', 0 );
	}
	update_option( 'ohmylms_student_courses_page_id', $kept_page );

	$state    = call_api( 'GET', '/setup' )->get_data();
	$previews = array();
	foreach ( $state['starter_pages'] as $row ) {
		$previews[ $row['key'] ] = $row;
	}
	ok( array( 'course', 'checkout', 'student_dashboard', 'student_profile', 'student_courses' ) === array_keys( $previews ), 'only keys that are general page settings are listed: ' . implode( ',', array_keys( $previews ) ) );
	ok( 'missing' === $previews['course']['status'] && 'adoptable' === $previews['student_dashboard']['status'] && $adopt_page === $previews['student_dashboard']['page_id'] && 'missing' === $previews['student_profile']['status'] && 'ready' === $previews['student_courses']['status'], 'the preview classifies pages as missing, adoptable and ready' );
	$pages_before = (int) wp_count_posts( 'page' )->publish + (int) wp_count_posts( 'page' )->draft;
	ok( 0 === (int) get_option( 'ohmylms_course_page_id' ), 'the preview created nothing' );

	$response = call_api( 'POST', '/setup/steps/pages' );
	$data     = $response->get_data();
	ok( 200 === $response->get_status(), 'pages step succeeds: ' . wp_json_encode( $data ) );
	$actions = array();
	foreach ( $data['result']['pages'] as $row ) {
		$actions[ $row['key'] ] = $row['action'];
		if ( 'created' === $row['action'] ) {
			$post_ids[] = $row['page_id'];
		}
	}
	ok(
		array(
			'course'            => 'created',
			'checkout'          => 'created',
			'student_dashboard' => 'adopted',
			'student_profile'   => 'created',
			'student_courses'   => 'kept',
		) === $actions,
		'created, adopted and kept pages: ' . wp_json_encode( $actions )
	);
	$general = SettingsRegistry::group( 'general' );
	ok( (int) get_option( 'ohmylms_student_dashboard_page_id' ) === $adopt_page && (int) get_option( 'ohmylms_student_courses_page_id' ) === $kept_page, 'adopted and kept pages are configured' );
	ok( 'original content' === get_post( $adopt_page )->post_content && get_post( $adopt_page )->post_title === 'Adopt ' . $tag, 'an adopted page keeps its content and title' );
	ok( 'draft' === get_post_status( $draft_page ) && 'original content' === get_post( $draft_page )->post_content, 'a draft page with the slug is left untouched' );
	$profile = get_post( (int) get_option( 'ohmylms_student_profile_page_id' ) );
	ok( $profile && $profile->ID !== $draft_page && 'publish' === $profile->post_status && 'page' === $profile->post_type && 'Wizard profile ' . $tag === $profile->post_title, 'a new page is created next to the draft' );
	$checkout = get_post( (int) get_option( 'ohmylms_checkout_page_id' ) );
	ok( $checkout && 'closed' === $checkout->comment_status && 'ohmylms-checkout' === get_post_meta( $checkout->ID, '_wp_page_template', true ) && 'wiz-checkout-' . $tag === $checkout->post_name, 'created pages are published with slug, closed comments and template' );
	ok(
		'ready' === step_status(
			array(
				'steps' => array(
					array(
						'id'     => 'ready',
						'status' => 'ready',
					),
				),
			),
			'ready'
		) && 'done' === step_status(
			$data['state'],
			'pages'
		),
		'the pages step is marked done'
	);
	$post_total = (int) wp_count_posts( 'page' )->publish + (int) wp_count_posts( 'page' )->draft;
	ok( $post_total === $pages_before + 3, 'exactly three pages were added' );

	$response = call_api( 'POST', '/setup/steps/pages' );
	$data     = $response->get_data();
	$actions  = array_unique( wp_list_pluck( $data['result']['pages'], 'action' ) );
	ok( 200 === $response->get_status() && array( 'kept' ) === $actions, 'repeating the pages step keeps everything' );
	ok( (int) wp_count_posts( 'page' )->publish + (int) wp_count_posts( 'page' )->draft === $post_total, 'repeating the pages step adds no page' );

	// A configured page that was unpublished is replaced by a new page, never republished.
	wp_update_post(
		array(
			'ID'          => $profile->ID,
			'post_status' => 'draft',
		)
	);
	$response = call_api( 'POST', '/setup/steps/pages' );
	$row      = array_values( array_filter( $response->get_data()['result']['pages'], static fn( $r ) => 'student_profile' === $r['key'] ) )[0];
	$post_ids[] = $row['page_id'];
	ok( 'created' === $row['action'] && $row['page_id'] !== $profile->ID && 'draft' === get_post_status( $profile->ID ), 'an unpublished configured page is not republished; a new page is created' );

	// All-or-nothing: two keys that adopt the same page fail the settings rule, and the page created
	// earlier in the same request is removed again.
	remove_filter( 'ohmylms_default_pages', $pages_filter, 99 );
	$clash = static function () use ( $tag ) {
		return array(
			'student_dashboard' => array(
				'name'  => 'wiz-newdash-' . $tag,
				'title' => 'Rolled back ' . $tag,
			),
			'course'            => array(
				'name'  => 'wiz-kept-' . $tag,
				'title' => 'Same A',
			),
			'checkout'          => array(
				'name'  => 'wiz-kept-' . $tag,
				'title' => 'Same B',
			),
		);
	};
	add_filter( 'ohmylms_default_pages', $clash, 99 );
	update_option( 'ohmylms_student_dashboard_page_id', 0 );
	update_option( 'ohmylms_course_page_id', 0 );
	update_option( 'ohmylms_checkout_page_id', 0 );
	$response = call_api( 'POST', '/setup/steps/pages' );
	ok_error( $response, 500, 'ohmylms_setup_pages_failed', array(), 'settings rule violation' );
	ok( null === get_page_by_path( 'wiz-newdash-' . $tag, OBJECT, 'page' ) && 0 === (int) get_option( 'ohmylms_student_dashboard_page_id' ), 'a failed pages step leaves no new page and no changed setting' );
	remove_filter( 'ohmylms_default_pages', $clash, 99 );

	// A user without publish_pages cannot run the step.
	wp_set_current_user( $limited );
	ok( 200 === call_api( 'GET', '/setup' )->get_status(), 'manage_options is enough to read the state' );
	ok_error( call_api( 'POST', '/setup/steps/pages' ), 403, 'ohmylms_setup_cannot_publish_pages', array(), 'no publish_pages' );
	wp_set_current_user( $admin );

	// ---- Import is a later feature -------------------------------------------------------------
	ok_error( call_api( 'POST', '/setup/steps/import', array() ), 409, 'ohmylms_setup_step_unavailable', array(), 'import step' );
	ok( 'pending' === step_status( call_api( 'GET', '/setup' )->get_data(), 'import' ), 'an unavailable step stays pending' );

	// ---- Completion is explicit -----------------------------------------------------------------
	$response = call_api( 'POST', '/setup/complete' );
	ok_error( $response, 409, 'ohmylms_setup_incomplete', array(), 'complete too early' );
	ok( array( 'import' ) === $response->get_data()['data']['missing'], 'the missing steps are named' );
	ok( 0 === $fired['completed'], 'no completed hook yet' );
	$response = call_api( 'POST', '/setup/steps/import/skip' );
	ok( 200 === $response->get_status() && 'skipped' === step_status( state_of( $response ), 'import' ) && null === state_of( $response )['current_step'], 'import is skipped; every step is resolved' );
	call_api( 'POST', '/setup/steps/import/skip' );
	$response = call_api( 'POST', '/setup/complete' );
	$state    = state_of( $response );
	ok( 200 === $response->get_status() && 'completed' === $state['status'] && is_string( $state['completed_at'] ), 'the wizard completes' );
	ok( 1 === $fired['completed'], 'ohmylms_onboarding_completed fired once' );
	call_api( 'POST', '/setup/complete' );
	ok( 1 === $fired['completed'] && 'completed' === call_api( 'GET', '/setup' )->get_data()['status'], 'completing again is a no-op' );
	ok_error( call_api( 'POST', '/setup/dismiss' ), 409, 'ohmylms_setup_already_completed', array(), 'dismiss after completion' );
	$response = call_api( 'POST', '/setup/steps/experience', array( 'experience' => 'experienced' ) );
	ok( 'completed' === state_of( $response )['status'] && 'experienced' === state_of( $response )['answers']['experience'], 'a step can be re-run after completion without reopening the wizard' );
	call_api( 'POST', '/setup/steps/pages/skip' );
	ok( 'done' === step_status( call_api( 'GET', '/setup' )->get_data(), 'pages' ), 'skipping a step that is done keeps it done' );

	// ---- Stored progress ---------------------------------------------------------------------------
	$row    = $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", SetupProgress::OPTION ), ARRAY_A );
	$stored = maybe_unserialize( $row['option_value'] );
	ok( 'no' === $row['autoload'] || 'off' === $row['autoload'], 'progress is not autoloaded (autoload=' . $row['autoload'] . ')' );
	ok( 1 === $stored['version'] && 'completed' === $stored['status'] && strlen( $row['option_value'] ) < 1024, 'progress is one small versioned option (' . strlen( $row['option_value'] ) . ' bytes)' );
	ok( array( 'version', 'status', 'steps', 'answers', 'started_at', 'completed_at' ) === array_keys( $stored ), 'progress keys are as documented' );
	ok( ! preg_match( '/key|secret|token|password/i', $row['option_value'] ), 'no secrets are stored' );

	// ---- Reset and dismiss -----------------------------------------------------------------------------
	$pages_setting = get_option( 'ohmylms_student_courses_page_id' );
	$response      = call_api( 'POST', '/setup/reset' );
	$state         = state_of( $response );
	ok( 200 === $response->get_status() && 'not_started' === $state['status'] && 'welcome' === $state['current_step'] && null === $state['completed_at'], 'reset starts over' );
	ok( 'experienced' === $state['answers']['experience'] && get_option( 'ohmylms_student_courses_page_id' ) === $pages_setting && 'EUR' === get_option( 'ohmylms_currency' ), 'reset keeps answers, settings and pages' );
	call_api( 'POST', '/setup/steps/welcome' );
	ok( 2 === $fired['started'], 'a restarted wizard fires the started hook again' );
	$response = call_api( 'POST', '/setup/dismiss', array( 'step' => 'niche' ) );
	ok( 200 === $response->get_status() && 'dismissed' === state_of( $response )['status'] && array( 'niche' ) === $fired['skipped'], 'dismissing fires ohmylms_onboarding_skipped with the step' );
	call_api( 'POST', '/setup/dismiss', array( 'step' => 'niche' ) );
	ok( array( 'niche' ) === $fired['skipped'], 'dismissing twice fires the hook once' );
	ok_error( call_api( 'POST', '/setup/dismiss', array( 'step' => 'unknown' ) ), 400, 'rest_invalid_param', array( 'step' ), 'dismiss with an unknown step' );
	$response = call_api( 'POST', '/setup/steps/experience', array( 'experience' => 'beginner' ) );
	ok( 'in_progress' === state_of( $response )['status'], 'working on a step resumes a dismissed wizard' );

	// ---- Site setup (subject browsing) ----------------------------------------------------------------
	$response = call_api( 'GET', '/site-setup/navigation-menus' );
	ok( 200 === $response->get_status() && is_array( $response->get_data() ), 'navigation menus are listed for administrators' );
	ok_error( call_api( 'POST', '/site-setup/subjects-page', array( 'title' => str_repeat( 'x', 101 ) ) ), 400, 'rest_invalid_param', array( 'title' ), 'subjects page title too long' );
	ok_error( call_api( 'POST', '/site-setup/subjects-page', array( 'display' => 'grid' ) ), 400, 'rest_invalid_param', array( 'display' ), 'subjects page display' );
	ok_error( call_api( 'POST', '/site-setup/subjects-page', array( 'confirm' => 'maybe' ) ), 400, 'rest_invalid_param', array( 'confirm' ), 'subjects page confirm' );
	ok_error( call_api( 'POST', '/site-setup/explore-menu', array( 'menu_id' => 0 ) ), 400, 'rest_invalid_param', array( 'menu_id' ), 'menu id minimum' );
	ok_error(
		call_api(
			'POST',
			'/site-setup/explore-menu',
			array(
				'menu_id' => 1,
				'label'   => str_repeat( 'x', 41 ),
			)
		),
		400,
		'rest_invalid_param',
		array( 'label' ),
		'menu label too long'
	);
	$response = call_api( 'POST', '/site-setup/explore-menu', array() );
	ok( 400 === $response->get_status() && 'rest_missing_callback_param' === $response->get_data()['code'] && in_array( 'menu_id', $response->get_data()['data']['params'], true ), 'menu id is required' );
	$page_count = (int) wp_count_posts( 'page' )->publish;
	$response   = call_api( 'POST', '/site-setup/subjects-page', array( 'title' => 'Subjects ' . $tag ) );
	$data       = $response->get_data();
	ok( ( 200 === $response->get_status() && in_array( $data['status'], array( 'preview', 'exists' ), true ) ) || ( 409 === $response->get_status() && 'ohmylms_block_missing' === $data['code'] ), 'without confirm the subjects page is only previewed' );
	ok( (int) wp_count_posts( 'page' )->publish === $page_count, 'the preview created no page' );
	$response = call_api( 'POST', '/site-setup/explore-menu', array( 'menu_id' => 2147483647 ) );
	ok( in_array( $response->get_status(), array( 404, 409 ), true ), 'an unknown navigation menu is not found (or the block is missing)' );

	ok( 0 === $http, 'the wizard made no outbound HTTP request (' . $http . ')' );

	echo "OK: {$checks} checks passed.\n";
} catch ( Throwable $e ) {
	echo $e->getMessage() . "\n";
	$failed = true;
} finally {
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'any',
			's'              => $tag,
			'posts_per_page' => 50,
			'fields'         => 'ids',
		)
	) as $leftover ) {
		wp_delete_post( $leftover, true );
	}
	if ( class_exists( Items::class ) ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Items::table() . ' WHERE name LIKE %s', '%' . $wpdb->esc_like( $tag ) . '%' ) );
	}
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	restore_options( $before );
	$after_ok = ( snapshot_options() === $before );
	echo $after_ok ? "Options restored exactly.\n" : "WARNING: options differ from the snapshot.\n";
}
exit( ! empty( $failed ) || empty( $after_ok ) ? 1 : 0 );
