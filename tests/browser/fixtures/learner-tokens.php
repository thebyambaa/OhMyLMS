<?php
/**
 * Prints the learner-page token declarations (:root{...}) with the default branding, for specs that
 * render player CSS without WordPress. Used by tests/browser/exam-player-tokens.spec.cjs.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ . '/' );
function get_option( $key, $default = false ) { return $default; }
function __( $text ) { return $text; }
require dirname( __DIR__, 3 ) . '/vendor/autoload.php';

echo ':root{' . \OhMyLMS\Design\Tokens::frontend_oml_vars() . '}';
