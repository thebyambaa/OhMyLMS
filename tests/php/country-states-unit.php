<?php
/** Country catalogue extraction preserves the public helper, ordering and translated text. */
define( 'ABSPATH', __DIR__ );
function __( $text, $domain ) { return $text; }
function _x( $text, $context, $domain ) { return $text; }
require __DIR__ . '/../../includes/Utility/CountryStates.php';
require __DIR__ . '/../../includes/cl-functions.php';
$checks = 0;
function country_check( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    ++$GLOBALS['checks'];
}
country_check( array() === ohmylms_get_states( '' ), 'An empty country has no subdivisions.' );
country_check( array() === ohmylms_get_states( 'unknown' ), 'Unknown countries retain the empty-array fallback.' );
$states = ohmylms_get_states( 'BR' );
$map = array_column( $states, 'title', 'code' );
country_check( 'São Paulo' === $map['SP'], 'UTF-8 subdivision names survive the extraction.' );
country_check( count( $states ) === 27 && array_keys( $states ) === range( 0, 26 ), 'Brazil retains its normalized ordered choices.' );
country_check( $states === \OhMyLMS\Utility\CountryStates::states( 'BR' ), 'The public compatibility helper delegates to the owner.' );
echo "Country states: $checks checks passed.\n";
