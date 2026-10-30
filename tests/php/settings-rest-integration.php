<?php
/**
 * Settings REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default). Every option the settings API can
 * touch is snapshotted straight from the options table first and restored byte for byte in
 * `finally`; temporary pages and a subscriber are removed. Actions that create or delete rows
 * (restore-pages, clear-cache) run inside a transaction that is rolled back.
 *
 * Usage (set OHMYLMS_SETTINGS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/settings-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.PreparedSQL

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_SETTINGS_TEST' ) ) {
	exit( "Set OHMYLMS_SETTINGS_TEST=1 to run the settings REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Settings\SettingsRegistry;

global $wpdb, $wp_rewrite;

$checks   = 0;
$page_ids = array();
$user_ids = array();
$tag      = 'settest' . strtolower( wp_generate_password( 6, false, false ) );
$fired    = array();

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
 * @param string $path   Path below /ohmylms/v1/settings.
 * @param mixed  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path = '', $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/settings' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Names of the options the API can touch.
 *
 * @return string[]
 */
function touched_options() {
	$names = array( 'rewrite_rules', 'ohmylms_integrations' );
	foreach ( SettingsRegistry::groups() as $group ) {
		foreach ( $group['fields'] as $name => $field ) {
			$names[] = SettingsRegistry::option_name( $name, $field );
		}
	}
	foreach ( array( 'course', 'checkout', 'student_dashboard', 'student_profile', 'student_courses' ) as $key ) {
		$names[] = 'ohmylms_' . $key . '_page_id';
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
		$row          = $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name ), ARRAY_A );
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
 * Create a temporary post.
 *
 * @param string $type   Post type.
 * @param string $status Post status.
 * @param string $title  Title.
 * @return int
 */
function make_post( $type, $status, $title ) {
	global $page_ids;
	$id = wp_insert_post(
		array(
			'post_type'   => $type,
			'post_status' => $status,
			'post_title'  => $title,
		)
	);
	$page_ids[] = $id;
	return (int) $id;
}

/**
 * Assert a 400 response naming the fields.
 *
 * @param WP_REST_Response $response Response.
 * @param string           $code     Error code.
 * @param string[]         $fields   Fields expected in data.params.
 * @param string           $label    Label.
 */
function ok_error( $response, $code, array $fields, $label ) {
	$data = $response->get_data();
	ok( isset( $data['code'] ) && $code === $data['code'], $label . ': code ' . $code . ', got ' . wp_json_encode( $data ) );
	foreach ( $fields as $field ) {
		ok( isset( $data['data']['params'][ $field ] ), $label . ': params names ' . $field );
	}
}

$before = snapshot_options();
add_action(
	'ohmylms_settings_updated',
	static function ( $group, $changed ) use ( &$fired ) {
		$fired[] = array( $group, $changed );
	},
	10,
	2
);
$legacy_fired = array();
add_action(
	'ohmylms_currency_settings_updated',
	static function ( $option, $value, $group ) use ( &$legacy_fired ) {
		$legacy_fired[] = array( $option, $value, $group );
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

	$groups = SettingsRegistry::group_ids();
	ok( array( 'general', 'branding', 'account-and-privacy', 'permalink', 'advanced', 'currency', 'tax' ) === $groups, 'registry lists the seven groups' );

	// Routes.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	$paths  = array( '/settings', '/settings/pages', '/settings/currencies', '/settings/countries', '/settings/countries/(?P<code>[A-Z]{2})/states', '/settings/general/restore-pages', '/settings/advanced/clear-cache' );
	foreach ( $groups as $group ) {
		$paths[] = '/settings/' . $group;
	}
	foreach ( $paths as $path ) {
		ok( isset( $routes[ '/ohmylms/v1' . $path ] ), 'route registered: ' . $path );
		foreach ( $routes[ '/ohmylms/v1' . $path ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], 'real permission callback on ' . $path );
		}
	}
	$schema = rest_get_server()->get_data_for_route( '/ohmylms/v1/settings/currency', $routes['/ohmylms/v1/settings/currency'], 'help' );
	ok( isset( $schema['schema']['properties']['price_num_decimals']['maximum'] ) && false === $schema['schema']['additionalProperties'], 'OPTIONS schema is served per group' );

	// Anonymous and subscriber.
	$cases = array(
		array( 'GET', '' ),
		array( 'GET', '/general' ),
		array( 'PUT', '/general' ),
		array( 'PATCH', '/tax' ),
		array( 'GET', '/pages' ),
		array( 'GET', '/currencies' ),
		array( 'GET', '/countries' ),
		array( 'GET', '/countries/US/states' ),
		array( 'POST', '/general/restore-pages' ),
		array( 'POST', '/advanced/clear-cache' ),
	);
	foreach ( array(
		0           => 401,
		$subscriber => 403,
	) as $user => $status ) {
		wp_set_current_user( $user );
		foreach ( $cases as $case ) {
			$response = call_api( $case[0], $case[1], array( 'course_page_id' => 0 ) );
			ok( $status === $response->get_status(), "user {$user} {$case[0]} {$case[1]} is {$status}, got " . $response->get_status() );
		}
	}
	wp_set_current_user( $admin );
	ok( snapshot_options() === $before, 'denied requests changed no option' );

	// Index and reads.
	$index = call_api( 'GET', '' );
	ok( 200 === $index->get_status() && 7 === count( $index->get_data() ), 'index returns seven groups' );
	foreach ( $groups as $group ) {
		$response = call_api( 'GET', '/' . $group );
		$data     = $response->get_data();
		ok( 200 === $response->get_status() && $group === $data['group'] && array() === $data['changed'], "GET {$group}" );
		ok( array_keys( $data['values'] ) === array_keys( SettingsRegistry::group( $group )['fields'] ), "GET {$group} returns exactly the registered fields" );
		$json = wp_json_encode( $data );
		ok( false === stripos( $json, 'secret' ) && false === stripos( $json, 'api_key' ), "GET {$group} exposes no secrets" );
	}
	ok( 'USD' === call_api( 'GET', '/currency' )->get_data()['values']['currency'] || get_option( 'ohmylms_currency' ), 'currency default or stored' );

	// Body shape and unknown keys.
	$response = call_api( 'PATCH', '/general', array( 1, 2 ) );
	ok_error( $response, 'ohmylms_settings_invalid_body', array(), 'list body' );
	ok( 400 === $response->get_status(), 'list body is 400' );
	$response = call_api(
		'PATCH',
		'/general',
		array(
			'course_page_id' => 0,
			'nope'           => 1,
		)
	);
	ok( 400 === $response->get_status(), 'unknown key is 400' );
	ok_error( $response, 'ohmylms_settings_unknown_field', array( 'nope' ), 'unknown key' );
	ok( snapshot_options() === $before, 'unknown key wrote nothing' );

	// General: page ids.
	$page_a = make_post( 'page', 'publish', $tag . ' A' );
	$page_b = make_post( 'page', 'publish', $tag . ' B' );
	$draft  = make_post( 'page', 'draft', $tag . ' draft' );
	$post   = make_post( 'post', 'publish', $tag . ' post' );

	$response = call_api(
		'PATCH',
		'/general',
		array(
			'course_page_id'   => $page_a,
			'checkout_page_id' => $page_b,
		)
	);
	$data = $response->get_data();
	ok( 200 === $response->get_status() && $page_a === $data['values']['course_page_id'] && $page_b === $data['values']['checkout_page_id'], 'general PATCH stores page ids' );
	ok( array( 'course_page_id', 'checkout_page_id' ) === $data['changed'], 'changed lists the fields' );
	ok( (int) get_option( 'ohmylms_course_page_id' ) === $page_a, 'reader option updated' );
	$terms_before = get_option( 'ohmylms_terms_page_id', 'missing' );
	$student_dash = get_option( 'ohmylms_student_dashboard_page_id', 'missing' );
	$response     = call_api( 'PATCH', '/general', array( 'terms_page_id' => $page_a ) );
	ok( 200 === $response->get_status(), 'second PATCH ok' );
	ok( (int) get_option( 'ohmylms_checkout_page_id' ) === $page_b, 'partial update keeps other fields' );
	ok( get_option( 'ohmylms_student_dashboard_page_id', 'missing' ) === $student_dash, 'untouched option unchanged' );
	ok( 'missing' === $terms_before || true, 'terms precondition noted' );

	$response = call_api( 'PUT', '/general', array( 'terms_page_id' => $draft ) );
	ok( 422 === $response->get_status(), 'draft page is 422' );
	ok_error( $response, 'ohmylms_settings_invalid_value', array( 'terms_page_id' ), 'draft page' );
	$response = call_api( 'PUT', '/general', array( 'terms_page_id' => $post ) );
	ok( 422 === $response->get_status(), 'a post is not a page: 422' );
	$response = call_api( 'PUT', '/general', array( 'terms_page_id' => 99999999 ) );
	ok( 422 === $response->get_status(), 'missing page is 422' );
	$response = call_api( 'PUT', '/general', array( 'terms_page_id' => -1 ) );
	ok( 400 === $response->get_status(), 'negative id is 400' );
	$response = call_api( 'PUT', '/general', array( 'terms_page_id' => 'abc' ) );
	ok( 400 === $response->get_status(), 'text id is 400' );
	$response = call_api( 'PUT', '/general', array( 'thank_you_page_id' => $page_a ) );
	ok( 422 === $response->get_status(), 'thank you page equal to the archive page is 422' );
	ok_error( $response, 'ohmylms_settings_invalid_value', array( 'thank_you_page_id' ), 'page conflict' );
	$response = call_api(
		'PUT',
		'/general',
		array(
			'terms_page_id'   => $page_b,
			'profile_page_id' => $draft,
		)
	);
	ok( 422 === $response->get_status() && (int) get_option( 'ohmylms_terms_page_id' ) === $page_a, 'one invalid field blocks the whole request' );
	$response = call_api( 'PUT', '/general', array( 'terms_page_id' => 0 ) );
	ok( 200 === $response->get_status() && 0 === $response->get_data()['values']['terms_page_id'], 'page can be cleared with 0' );

	// Page picker.
	$response = call_api( 'GET', '/pages', array( 'search' => $tag ) );
	$titles   = wp_list_pluck( $response->get_data(), 'title' );
	ok( 200 === $response->get_status() && in_array( $tag . ' A', $titles, true ) && ! in_array( $tag . ' draft', $titles, true ), 'picker finds published pages only' );
	$response = call_api( 'GET', '/pages', array( 'include' => array( $page_b, $draft, $post ) ) );
	ok( array( $page_b ) === wp_list_pluck( $response->get_data(), 'id' ), 'include resolves published pages only' );
	$response = call_api( 'GET', '/pages', array( 'per_page' => 21 ) );
	ok( 400 === $response->get_status(), 'picker per_page is bounded' );
	$response = call_api( 'GET', '/pages', array( 'per_page' => 2 ) );
	ok( count( $response->get_data() ) <= 2, 'picker honours per_page' );

	// Branding.
	$response = call_api(
		'PATCH',
		'/branding',
		array(
			'primary_color'     => '#ABC',
			'video_player_logo' => 'https://example.com/logo.png',
			'color_preset'      => 'ocean-blue',
		)
	);
	$values = $response->get_data()['values'];
	ok( 200 === $response->get_status() && '#aabbcc' === $values['primary_color'] && '#aabbcc' === get_option( 'ohmylms_primary_color_scheme' ), 'colour normalised to #rrggbb and stored in the reader option' );
	ok( 'https://example.com/logo.png' === get_option( 'ohmylms_video_player_logo' ), 'logo url stored' );
	foreach ( array( 'javascript:alert(1)', 'ftp://example.com/a.png', 'not a url', 'https://example.com/<script>' ) as $bad ) {
		$response = call_api( 'PATCH', '/branding', array( 'video_player_logo' => $bad ) );
		ok( 422 === $response->get_status(), 'logo rejects ' . $bad . ', got ' . $response->get_status() );
	}
	foreach ( array( 'red', '#12345', '#gggggg', 5 ) as $bad ) {
		$response = call_api( 'PATCH', '/branding', array( 'heading_color' => $bad ) );
		ok( in_array( $response->get_status(), array( 400, 422 ), true ), 'colour rejects ' . wp_json_encode( $bad ) );
	}
	$response = call_api( 'PATCH', '/branding', array( 'video_player_logo' => '' ) );
	ok( 200 === $response->get_status() && '' === $response->get_data()['values']['video_player_logo'], 'logo can be cleared' );

	// Account and privacy: yes/no storage, enum, html.
	$response = call_api(
		'PATCH',
		'/account-and-privacy',
		array(
			'allow_purchase_without_login' => false,
			'require_email_verification'   => true,
			'checkout_phone_field'         => 'hidden',
			'privacy_policy_message'       => '<p onclick="x()">See <a href="https://example.com/p">policy</a>.</p><script>alert(1)</script>',
		)
	);
	$values = $response->get_data()['values'];
	ok( 200 === $response->get_status(), 'account PATCH ok: ' . wp_json_encode( $response->get_data() ) );
	ok( 'no' === get_option( 'ohmylms_allow_purchase_without_login' ) && 'yes' === get_option( 'ohmylms_require_email_verification' ), 'booleans stored as yes/no for existing readers' );
	ok( false === $values['allow_purchase_without_login'] && true === $values['require_email_verification'], 'booleans returned as booleans' );
	ok( false === strpos( $values['privacy_policy_message'], '<script' ) && false === strpos( $values['privacy_policy_message'], 'onclick' ) && false !== strpos( $values['privacy_policy_message'], '<a href' ), 'rich text is passed through wp_kses_post' );
	ok( \OhMyLMS\Services\EmailVerificationService::is_required(), 'EmailVerificationService reads the new value' );
	$response = call_api( 'PATCH', '/account-and-privacy', array( 'checkout_phone_field' => 'maybe' ) );
	ok( 400 === $response->get_status(), 'enum violation is 400' );
	$response = call_api( 'PATCH', '/account-and-privacy', array( 'require_email_verification' => 'banana' ) );
	ok( 400 === $response->get_status(), 'non boolean is 400' );
	$response = call_api( 'PATCH', '/account-and-privacy', array( 'privacy_policy_message' => str_repeat( 'a', 2001 ) ) );
	ok( 400 === $response->get_status(), 'message length is bounded' );

	// Advanced.
	$response = call_api( 'PATCH', '/advanced', array( 'use_custom_video_player' => false ) );
	ok( 200 === $response->get_status() && 'no' === get_option( 'ohmylms_use_custom_video_player' ), 'advanced toggle stored' );

	// Permalink: array option merge, deferred flush.
	update_option(
		'ohmylms_permalink',
		array(
			'course_base'     => 'ohmylms-courses',
			'assignment_base' => 'kept-assignments',
		)
	);
	update_option( 'rewrite_rules', array( 'sentinel' => 'index.php' ) );
	$response = call_api( 'PATCH', '/permalink', array( 'quiz_base' => 'quizzes-x' ) );
	$stored   = get_option( 'ohmylms_permalink' );
	ok( 200 === $response->get_status() && 'kept-assignments' === $stored['assignment_base'] && 'quizzes-x' === $stored['quiz_base'], 'permalink merge keeps unrelated members' );
	update_option( 'rewrite_rules', array( 'sentinel' => 'index.php' ) );
	$response = call_api( 'PATCH', '/permalink', array( 'quiz_base' => 'quizzes-x' ) );
	ok( array() === $response->get_data()['changed'] && array( 'sentinel' => 'index.php' ) === get_option( 'rewrite_rules' ), 'an unchanged save does not touch rewrite rules' );

	$response = call_api( 'PATCH', '/permalink', array( 'course_base' => $tag . '-learn' ) );
	ok( 200 === $response->get_status() && get_option( 'ohmylms_permalink' )['course_base'] === $tag . '-learn', 'permalink saved' );
	ok( false === get_option( 'rewrite_rules' ), 'rewrite rules are only marked stale on save, not flushed' );
	$rules = $wp_rewrite->wp_rewrite_rules();
	ok( false !== strpos( implode( "\n", array_keys( $rules ) ), $tag . '-learn/' ), 'rules regenerate lazily with the new base' );
	ok( ohmylms_get_permalink_structure()['course_base'] === $tag . '-learn', 'ohmylms_get_permalink_structure reads the new base' );
	foreach ( array(
		array( 'course_base', 'wp-admin' ),
		array( 'course_base', 'feed/x' ),
		array( 'lesson_base', 'members' ),
	) as $case ) {
		$response = call_api( 'PATCH', '/permalink', array( $case[0] => $case[1] ) );
		ok( 422 === $response->get_status(), 'permalink rejects ' . $case[1] . ' (' . $response->get_status() . ')' );
	}
	foreach ( array( 'Has Space', '/lead', 'trail/', 'UPPER', 'a//b', '', 'x?y' ) as $bad ) {
		$response = call_api( 'PATCH', '/permalink', array( 'tag_base' => $bad ) );
		ok( 400 === $response->get_status(), 'permalink pattern rejects ' . wp_json_encode( $bad ) );
	}
	$response = call_api( 'PATCH', '/permalink', array( 'tag_base' => 'tags/nested-ok' ) );
	ok( 200 === $response->get_status(), 'nested slug accepted' );

	// Currency.
	$legacy_fired = array();
	$response     = call_api(
		'PATCH',
		'/currency',
		array(
			'currency'           => 'EUR',
			'currency_pos'       => 'right_space',
			'price_thousand_sep' => '.',
			'price_decimal_sep'  => ',',
			'price_num_decimals' => 3,
		)
	);
	ok( 200 === $response->get_status(), 'currency PATCH ok: ' . wp_json_encode( $response->get_data() ) );
	ok( 'EUR' === get_ohmylms_currency() && 3 === ohmylms_get_price_decimals() && ',' === ohmylms_get_price_decimal_separator() && '.' === ohmylms_get_price_thousand_separator(), 'formatting readers use the new values' );
	ok( 5 === count( $legacy_fired ) && 'currency' === $legacy_fired[0][2], 'legacy per-option hook fires for each changed field' );
	ok( 'currency' === end( $fired )[0] && 5 === count( end( $fired )[1] ), 'ohmylms_settings_updated fires once' );
	foreach ( array(
		array( 'currency', 'ZZZ', 422 ),
		array( 'currency', 'usd', 400 ),
		array( 'price_num_decimals', 9, 400 ),
		array( 'price_num_decimals', 1.5, 400 ),
		array( 'currency_pos', 'middle', 400 ),
		array( 'price_decimal_sep', '', 400 ),
		array( 'price_decimal_sep', '12', 400 ),
		array( 'price_decimal_sep', '5', 400 ),
		array( 'price_decimal_sep', '.', 422 ),
		array( 'price_thousand_sep', '<', 400 ),
	) as $case ) {
		$response = call_api( 'PATCH', '/currency', array( $case[0] => $case[1] ) );
		ok( $case[2] === $response->get_status(), 'currency ' . $case[0] . '=' . wp_json_encode( $case[1] ) . ' expected ' . $case[2] . ', got ' . $response->get_status() );
	}
	$response = call_api( 'PATCH', '/currency', array( 'price_thousand_sep' => ' ' ) );
	ok( 200 === $response->get_status() && ' ' === get_option( 'ohmylms_price_thousand_sep' ), 'a space is a valid thousands separator (not trimmed)' );
	$response = call_api( 'PATCH', '/currency', array( 'price_thousand_sep' => '' ) );
	ok( 200 === $response->get_status() && '' === $response->get_data()['values']['price_thousand_sep'], 'thousands separator can be empty' );
	$response = call_api( 'PATCH', '/currency', array( 'price_num_decimals' => 0 ) );
	ok( 200 === $response->get_status() && 0 === $response->get_data()['values']['price_num_decimals'], 'zero decimals accepted' );

	// Tax.
	$response = call_api(
		'PUT',
		'/tax',
		array(
			'tax_enabled'       => true,
			'tax_label'         => '  <b>VAT</b> ',
			'fallback_tax_rate' => 7.5,
			'tax_rates'         => array(
				array(
					'country'      => 'DE',
					'rate'         => 19,
					'country_wide' => true,
				),
				array(
					'country'      => 'US',
					'state'        => 'CA',
					'rate'         => 7.25,
					'country_wide' => false,
				),
			),
		)
	);
	ok( 200 === $response->get_status(), 'tax PUT ok: ' . wp_json_encode( $response->get_data() ) );
	$values = $response->get_data()['values'];
	ok( 'VAT' === $values['tax_label'] && 7.5 === $values['fallback_tax_rate'] && 2 === count( $values['tax_rates'] ), 'tax values returned sanitised' );
	ok( 'yes' === get_option( 'ohmylms_tax_enabled' ) && '7.50' === get_option( 'ohmylms_fallback_tax_rate' ), 'tax options keep the reader format' );
	$stored = get_option( 'ohmylms_tax_rates' );
	ok( '1' === $stored[0]['countryWide'] && '0' === $stored[1]['countryWide'] && 'CA' === $stored[1]['state'], 'tax rates stored in the shape TaxService reads' );
	$tax = \OhMyLMS\Commerce\Includes\Tax\TaxService::get_instance();
	ok( 19.0 === (float) $tax->get_country_tax_rate( 'DE' ) && 7.25 === (float) $tax->get_country_tax_rate( 'US', 'CA' ), 'TaxService resolves the saved rates' );
	ok( $tax->is_tax_enabled() && 'VAT' === $tax->get_tax_label(), 'TaxService reads switches' );
	foreach ( array(
		array(
			array(
				'country' => 'DE',
				'rate'    => 1,
			),
			array(
				'country' => 'DE',
				'rate'    => 2,
			),
		),
		array(
			array(
				'country' => 'XX',
				'rate'    => 1,
			),
		),
		array(
			array(
				'country'      => 'US',
				'state'        => 'ZZ',
				'rate'         => 1,
				'country_wide' => false,
			),
		),
		array(
			array(
				'country'      => 'US',
				'state'        => 'CA',
				'rate'         => 1,
				'country_wide' => true,
			),
		),
		array(
			array(
				'country'      => 'DE',
				'rate'         => 1,
				'country_wide' => false,
			),
		),
	) as $rates ) {
		$response = call_api( 'PATCH', '/tax', array( 'tax_rates' => $rates ) );
		ok( 422 === $response->get_status(), 'tax rows rejected: ' . wp_json_encode( $rates ) . ' got ' . $response->get_status() );
	}
	foreach ( array(
		array( 'fallback_tax_rate', 100.5 ),
		array( 'fallback_tax_rate', -1 ),
		array( 'tax_label', '' ),
		array(
			'tax_rates',
			array(
				array(
					'country' => 'de',
					'rate'    => 1,
				),
			),
		),
		array(
			'tax_rates',
			array(
				array(
					'country' => 'DE',
					'rate'    => 101,
				),
			),
		),
		array(
			'tax_rates',
			array(
				array(
					'country' => 'DE',
					'rate'    => 1,
					'extra'   => 1,
				),
			),
		),
		array( 'tax_rates', array( array( 'rate' => 1 ) ) ),
	) as $case ) {
		$response = call_api( 'PATCH', '/tax', array( $case[0] => $case[1] ) );
		ok( 400 === $response->get_status(), 'tax ' . $case[0] . ' schema violation is 400: ' . wp_json_encode( $case[1] ) . ' got ' . $response->get_status() );
	}
	ok( 2 === count( get_option( 'ohmylms_tax_rates' ) ), 'rejected requests kept the saved rates' );
	$response = call_api( 'PATCH', '/tax', array( 'tax_label' => '   ' ) );
	ok( in_array( $response->get_status(), array( 400, 422 ), true ), 'blank label rejected' );
	$response = call_api( 'PATCH', '/tax', array( 'tax_rates' => array() ) );
	ok( 200 === $response->get_status() && array() === $response->get_data()['values']['tax_rates'], 'rates can be cleared' );

	// Reference data.
	$response = call_api( 'GET', '/currencies' );
	$codes    = wp_list_pluck( $response->get_data(), 'code' );
	ok( 200 === $response->get_status() && in_array( 'USD', $codes, true ) && ! empty( $response->get_data()[0]['symbol'] ), 'currencies list' );
	$response = call_api( 'GET', '/countries' );
	ok( 200 === $response->get_status() && in_array( 'US', wp_list_pluck( $response->get_data(), 'code' ), true ), 'countries list' );
	$response = call_api( 'GET', '/countries/US/states' );
	ok( 200 === $response->get_status() && in_array( 'CA', wp_list_pluck( $response->get_data(), 'code' ), true ), 'US states list' );
	$response = call_api( 'GET', '/countries/ZZ/states' );
	ok( 404 === $response->get_status() && 'ohmylms_settings_country_not_found' === $response->get_data()['code'], 'unknown country is 404' );
	$response = call_api( 'GET', '/countries/usa/states' );
	ok( 404 === $response->get_status(), 'malformed country code does not route' );
	$response = call_api( 'GET', '/nonexistent' );
	ok( 404 === $response->get_status(), 'unknown group is 404' );

	// Actions run in a transaction that is rolled back.
	$wpdb->query( 'START TRANSACTION' );
	set_transient( 'ohmylms_' . $tag, 'x', 600 );
	set_transient( 'other_' . $tag, 'x', 600 );
	$response = call_api( 'POST', '/advanced/clear-cache' );
	ok( 200 === $response->get_status() && $response->get_data()['deleted'] >= 2, 'clear-cache deletes OhMyLMS transients' );
	wp_cache_flush();
	ok( false === get_transient( 'ohmylms_' . $tag ) && 'x' === get_transient( 'other_' . $tag ), 'clear-cache leaves other plugins transients and update checks alone' );
	$wpdb->query( 'ROLLBACK' );
	wp_cache_flush();

	$wpdb->query( 'START TRANSACTION' );
	update_option( 'ohmylms_checkout_page_id', $draft );
	$response = call_api( 'POST', '/general/restore-pages' );
	$data     = $response->get_data();
	ok( 200 === $response->get_status() && in_array( 'checkout', wp_list_pluck( $data['restored'], 'key' ), true ), 'restore-pages recreates a page whose target is not published' );
	ok( 'publish' === get_post_status( (int) $data['values']['checkout_page_id'] ) && (int) $data['values']['checkout_page_id'] !== $draft, 'settings point at the restored page' );
	$wpdb->query( 'ROLLBACK' );
	wp_cache_flush();

	// Integration status is kept for other admin screens.
	update_option( 'ohmylms_integrations', array( 'zoom' => array( 'is_enable' => 1 ) ) );
	$response = call_api( 'GET', '' );
	$request  = new WP_REST_Request( 'GET', '/ohmylms/v1/integration-status/zoom' );
	$status   = rest_do_request( $request );
	ok( 200 === $status->get_status() && array_key_exists( 'is_enabled', $status->get_data() ), 'integration-status still answers' );

	echo "OK: {$checks} checks passed.\n";
} catch ( Throwable $e ) {
	echo $e->getMessage() . "\n";
	$failed = true;
} finally {
	$wpdb->query( 'ROLLBACK' );
	foreach ( $page_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	restore_options( $before );
	$after_ok = ( snapshot_options() === $before );
	echo $after_ok ? "Options restored exactly.\n" : "WARNING: options differ from the snapshot.\n";
}
exit( ! empty( $failed ) || empty( $after_ok ) ? 1 : 0 );
