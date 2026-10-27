<?php
/**
 * Unit checks for the Microsoft sign-in service. No WordPress needed.
 *
 * Run: php tests/php/microsoft-auth-unit.php
 */
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['__option']    = array();
$GLOBALS['__transient'] = array();

function check( $value, $message ) {
	if ( ! $value ) {
		throw new RuntimeException( $message );
	}
}
function get_option( $name, $default = false ) {
	return $GLOBALS['__option'][ $name ] ?? $default;
}
function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, $args );
}
function __( $text, $domain = '' ) {
	return $text;
}
function rest_url( $path ) {
	return 'https://example.test/wp-json/' . $path;
}
function is_ssl() {
	return true;
}
function wp_generate_password( $length = 12 ) {
	return str_repeat( 'a', $length );
}
function get_current_user_id() {
	return 0;
}
function set_transient( $key, $value ) {
	$GLOBALS['__transient'][ $key ] = $value;
	return true;
}
function add_query_arg( $args, $url ) {
	return $url . '?' . http_build_query( $args );
}
function sanitize_text_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}
function sanitize_email( $email ) {
	return trim( (string) $email );
}
function wp_unslash( $value ) {
	return $value;
}
function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}
class WP_Error {
	public $code;
	public function __construct( $code = '' ) {
		$this->code = $code;
	}
}
class WP_User {}

require dirname( __DIR__, 2 ) . '/includes/Integrations/MicrosoftSignIn/Hooks.php';
require dirname( __DIR__, 2 ) . '/includes/Services/MicrosoftAuthService.php';

use OhMyLMS\Services\MicrosoftAuthService as M;

// Tenant values: only a single safe path segment is accepted.
check( 'common' === M::sanitize_tenant( '' ), 'Empty tenant falls back' );
check( 'common' === M::sanitize_tenant( null ), 'Non-string tenant falls back' );
check( 'organizations' === M::sanitize_tenant( 'organizations' ), 'Named tenant kept' );
check( '72f988bf-86f1-41af-91ab-2d7cd011db47' === M::sanitize_tenant( ' 72f988bf-86f1-41af-91ab-2d7cd011db47 ' ), 'Tenant ID kept and trimmed' );
check( 'contoso.onmicrosoft.com' === M::sanitize_tenant( 'contoso.onmicrosoft.com' ), 'Domain tenant kept' );
check( 'common' === M::sanitize_tenant( '../evil' ), 'Path traversal rejected' );
check( 'common' === M::sanitize_tenant( 'a/b' ), 'Slash rejected' );
check( 'common' === M::sanitize_tenant( 'a?b=c' ), 'Query characters rejected' );
check( 'common' === M::sanitize_tenant( '-leading' ), 'Leading dash rejected' );

// Not configured until the add-on is on and both credentials exist.
check( ! M::is_configured(), 'Off by default' );
$GLOBALS['__option']['ohmylms_integrations']  = array( 'microsoft_signin' => array( 'is_enable' => true ) );
check( ! M::is_configured(), 'Enabled but no credentials' );
$GLOBALS['__option']['ohmylms_microsoft_oauth'] = array(
	'client_id'     => 'client-1',
	'client_secret' => 'secret-1',
	'tenant'        => 'consumers',
);
check( M::is_configured(), 'Enabled with credentials' );
check( 'consumers' === M::get_settings()['tenant'], 'Stored tenant read back' );
$GLOBALS['__option']['ohmylms_microsoft_oauth']['tenant'] = '../bad';
check( 'common' === M::get_settings()['tenant'], 'Corrupt stored tenant falls back' );
$GLOBALS['__option']['ohmylms_microsoft_oauth']['tenant'] = 'consumers';

// Authorization URL.
$url = @M::build_authorization_url( 'https://example.test/checkout/' );
check( is_string( $url ), 'URL built' );
check( 0 === strpos( $url, 'https://login.microsoftonline.com/consumers/oauth2/v2.0/authorize?' ), 'Tenant is in the path: ' . $url );
parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
check( 'client-1' === $query['client_id'], 'Client ID sent' );
check( 'code' === $query['response_type'] && 'query' === $query['response_mode'], 'Authorization code flow' );
check( 'openid email profile' === $query['scope'], 'Scopes' );
check( 'https://example.test/wp-json/ohmylms/v1/auth/microsoft/callback' === $query['redirect_uri'], 'Redirect URI' );
check( 32 === strlen( $query['state'] ), 'State generated' );
$saved = $GLOBALS['__transient'][ M::STATE_TRANSIENT . $query['state'] ] ?? null;
check( is_array( $saved ) && 'https://example.test/checkout/' === $saved['redirect_to'], 'Return URL stored with the state' );

// State can only be consumed with the matching browser cookie.
check( false === M::consume_state( '' ), 'Empty state rejected' );
check( false === M::consume_state( $query['state'] ), 'Missing cookie rejected' );
$_COOKIE[ M::STATE_COOKIE ] = 'different';
check( false === M::consume_state( $query['state'] ), 'Mismatched cookie rejected' );

// Unconfigured service refuses to build a URL.
$GLOBALS['__option']['ohmylms_integrations'] = array();
check( is_wp_error( M::build_authorization_url() ), 'Disabled add-on cannot start a login' );

// A profile with no subject never resolves to a user.
$result = M::find_or_create_user( array( 'email' => 'a@example.test' ) );
check( is_wp_error( $result ) && 'microsoft_identity_invalid' === $result->code, 'Subject is required' );

echo "Microsoft auth unit checks passed\n";
