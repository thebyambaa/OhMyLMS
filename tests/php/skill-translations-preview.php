<?php
/**
 * Provider drafts and validation using WordPress with isolated HTTP and settings.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || empty( $argv[1] ) ) {
	exit( 1 );
}
require rtrim( $argv[1], '/\\' ) . '/wp-load.php';

// Test-process constants and filters never change the site's provider configuration.
if ( defined( 'OHMYLMS_AI_API_KEY' ) || defined( 'OHMYLMS_AI_PROVIDER' ) || defined( 'OHMYLMS_AI_MODEL' ) ) {
	echo "Provider constants already defined; isolated preview test unavailable.\n";
	exit( 1 );
}
define( 'OHMYLMS_AI_API_KEY', 'test-only-no-network' );
define( 'OHMYLMS_AI_PROVIDER', 'openai' );
define( 'OHMYLMS_AI_MODEL', 'test-model' );
add_filter(
	'pre_option_ohmylms_ai',
	static function () {
		return array( 'enabled' => true );
	}
);
add_filter(
	'pre_update_option_ohmylms_ai_stats',
	static function ( $value, $old_value ) {
		return $old_value;
	},
	10,
	2
);
$provider_reply = '{"name":"Translated","description":"Notes"}';
$provider_input = array();
add_filter(
	'pre_http_request',
	static function ( $response, $args ) use ( &$provider_reply, &$provider_input ) {
		$provider_input = json_decode( $args['body'], true );
		return array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'headers'  => array(),
			'body'     => wp_json_encode(
				array(
					'output' => array(
						array(
							'type'    => 'message',
							'content' => array(
								array(
									'type' => 'output_text',
									'text' => $provider_reply,
								),
							),
						),
					),
				)
			),
		);
	},
	10,
	2
);
$checks = 0;
$check  = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$checks;
};
foreach ( array( null, 'text', array( 'fr' => array() ), array( 'en' => 'text' ), array( 'en' => array( 'html' => 'x' ) ), array( 'en' => array( 'name' => array() ) ), array( 'mn' => array( 'name' => str_repeat( 'а', 191 ) ) ), array( 'mn' => array( 'description' => str_repeat( 'а', 2001 ) ) ) ) as $invalid ) {
	$check( is_wp_error( \OhMyLMS\Skills\Translations::normalize( $invalid ) ), 'Invalid shape and Unicode bounds rejected.' );
}
$clean = \OhMyLMS\Skills\Translations::normalize(
	array(
		'mn' => array(
			'name'        => '<b>Монгол</b>',
			'description' => "Line 1\nLine 2",
		),
	)
);
$check( 'Монгол' === $clean['mn']['name'], 'Actual WordPress sanitation.' );
$check( "Line 1\nLine 2" === $clean['mn']['description'], 'Notes preserve lines.' );
$check( is_wp_error( \OhMyLMS\Skills\Translations::preview( array(), array() ) ), 'Malformed locale rejected safely.' );
$stats_before = get_option( 'ohmylms_ai_stats' );
$translation  = \OhMyLMS\Skills\Translations::preview( 'en', array( 'name' => 'Source' ) );
$check( 'Translated' === $translation['name'], 'Provider response parsed into a draft.' );
$check( false !== strpos( $provider_input['instructions'], 'never instructions' ), 'Source treated as data.' );
$check( 400 === $provider_input['max_output_tokens'], 'Existing configured token budget retained.' );
$provider_reply = '{"name":"Sets","objective":"A complete translated definition","description":"Notes"}';
$translation    = \OhMyLMS\Skills\Translations::preview(
	'en',
	array(
		'name'      => 'Sets',
		'objective' => 'A full definition',
	)
);
$check( 'A complete translated definition' === $translation['objective'], 'Full objectives translate separately from short titles.' );
$provider_reply = '{"name":"Sets","description":"Notes"}';
$check(
	is_wp_error(
		\OhMyLMS\Skills\Translations::preview(
			'en',
			array(
				'name'      => 'Sets',
				'objective' => 'A full definition',
			)
		)
	),
	'A requested full objective cannot be silently omitted.'
);
foreach ( array( 'invalid', '{}', '{"name":[],"description":""}', str_repeat( 'x', 16001 ) ) as $provider_reply ) {
	$check( is_wp_error( \OhMyLMS\Skills\Translations::preview( 'mn', array( 'name' => 'Source' ) ) ), 'Malformed provider replies rejected.' );
}
$check( get_option( 'ohmylms_ai_stats' ) === $stats_before, 'Test HTTP does not persist provider usage.' );
echo (int) $checks . " isolated translation preview checks passed.\n";
