<?php
/**
 * WordPress doubles for the browser fixture: no database, no wp-load.php.
 *
 * Real code is used where it has no database dependency: the HTML API, the Interactivity API
 * (state, config and data-wp-context), and the plugin's classes through its Composer autoloader.
 * Everything here is a stand-in that returns fixture data.
 *
 * @package OhMyLMS\Tests
 */

spl_autoload_register(
	static function ( $class ) {
		$files = array(
			'WP_Interactivity_API'                      => 'interactivity-api/class-wp-interactivity-api.php',
			'WP_Interactivity_API_Directives_Processor' => 'interactivity-api/class-wp-interactivity-api-directives-processor.php',
		);
		if ( isset( $files[ $class ] ) ) {
			require ABSPATH . WPINC . '/' . $files[ $class ];
			return;
		}
		if ( 0 === strpos( $class, 'WP_HTML_' ) ) {
			$path = ABSPATH . WPINC . '/html-api/class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
			if ( is_file( $path ) ) {
				require $path;
			}
		}
	}
);
require ABSPATH . WPINC . '/class-wp-token-map.php';
require ABSPATH . WPINC . '/html-api/html5-named-character-references.php';
require ABSPATH . WPINC . '/interactivity-api/interactivity-api.php';
require OHMYLMS_DIR . '/vendor/autoload.php';

// ---- Hooks: a small real implementation, so the plugin's template decorators run. ----

$GLOBALS['fixture_hooks']   = array();
$GLOBALS['fixture_actions'] = array();
$GLOBALS['fixture_current'] = array();

function add_filter( $name, $callback, $priority = 10, $accepted = 1 ) {
	$GLOBALS['fixture_hooks'][ $name ][ $priority ][] = array( $callback, $accepted );
	return true;
}
function add_action( $name, $callback, $priority = 10, $accepted = 1 ) {
	return add_filter( $name, $callback, $priority, $accepted );
}
function has_filter( $name, $callback = false ) {
	return ! empty( $GLOBALS['fixture_hooks'][ $name ] );
}
function has_action( $name, $callback = false ) {
	return has_filter( $name, $callback );
}
function remove_filter( $name, $callback, $priority = 10 ) {
	foreach ( $GLOBALS['fixture_hooks'][ $name ][ $priority ] ?? array() as $index => $entry ) {
		if ( $entry[0] === $callback ) {
			unset( $GLOBALS['fixture_hooks'][ $name ][ $priority ][ $index ] );
		}
	}
	return true;
}
function remove_action( $name, $callback, $priority = 10 ) {
	return remove_filter( $name, $callback, $priority );
}
function apply_filters( $name, $value, ...$args ) {
	$hooks = $GLOBALS['fixture_hooks'][ $name ] ?? array();
	ksort( $hooks );
	$GLOBALS['fixture_current'][] = $name;
	foreach ( $hooks as $entries ) {
		foreach ( $entries as $entry ) {
			$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, max( 1, (int) $entry[1] ) ) );
		}
	}
	array_pop( $GLOBALS['fixture_current'] );
	return $value;
}
function do_action( $name, ...$args ) {
	$GLOBALS['fixture_actions'][ $name ] = ( $GLOBALS['fixture_actions'][ $name ] ?? 0 ) + 1;
	$hooks                               = $GLOBALS['fixture_hooks'][ $name ] ?? array();
	ksort( $hooks );
	$GLOBALS['fixture_current'][] = $name;
	foreach ( $hooks as $entries ) {
		foreach ( $entries as $entry ) {
			call_user_func_array( $entry[0], array_slice( $args, 0, (int) $entry[1] ) );
		}
	}
	array_pop( $GLOBALS['fixture_current'] );
}
function did_action( $name ) {
	return $GLOBALS['fixture_actions'][ $name ] ?? 0;
}
function doing_action( $name = null ) {
	return null === $name ? ! empty( $GLOBALS['fixture_current'] ) : in_array( $name, $GLOBALS['fixture_current'], true );
}
function doing_filter( $name = null ) {
	return doing_action( $name );
}
function __return_true() {
	return true;
}
function __return_false() {
	return false;
}

// ---- Escaping, translation and formatting. ----

function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_html( $value ) {
	return esc_attr( $value );
}
function esc_textarea( $value ) {
	return esc_attr( $value );
}
function esc_url( $value ) {
	return esc_attr( $value );
}
function esc_url_raw( $value ) {
	return (string) $value;
}
function esc_js( $value ) {
	return addslashes( (string) $value );
}
function esc_attr_e( $value, $domain = '' ) {
	echo esc_attr( $value );
}
function esc_html_e( $value, $domain = '' ) {
	echo esc_html( $value );
}
function esc_attr__( $value, $domain = '' ) {
	return esc_attr( $value );
}
function esc_html__( $value, $domain = '' ) {
	return esc_html( $value );
}
function esc_html_x( $value, $context, $domain = '' ) {
	return esc_html( $value );
}
function esc_attr_x( $value, $context, $domain = '' ) {
	return esc_attr( $value );
}
function __( $value, $domain = '' ) {
	return $value;
}
function _e( $value, $domain = '' ) {
	echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test double.
}
function _x( $value, $context, $domain = '' ) {
	return $value;
}
function _n( $single, $plural, $number, $domain = '' ) {
	return 1 === (int) $number ? $single : $plural;
}
function _nx( $single, $plural, $number, $context, $domain = '' ) {
	return _n( $single, $plural, $number );
}
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, $decimals );
}
function date_i18n( $format, $timestamp = false ) {
	return gmdate( $format, $timestamp ? (int) $timestamp : time() );
}
function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
}
function wp_kses_post( $value ) {
	return (string) $value;
}
function wp_kses( $value, $allowed = array() ) {
	return (string) $value;
}
function wp_kses_uri_attributes() {
	return array();
}
function wp_has_noncharacters( $value ) {
	return false;
}
function wpautop( $value ) {
	return '' === trim( (string) $value ) ? '' : '<p>' . $value . '</p>';
}
function wptexturize( $value ) {
	return (string) $value;
}
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}
function sanitize_html_class( $value ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value );
}
function sanitize_title( $value ) {
	return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' );
}
function absint( $value ) {
	return abs( (int) $value );
}
function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
}
function wp_parse_args( $args, $defaults = array() ) {
	if ( is_string( $args ) ) {
		parse_str( $args, $args );
	}
	return array_merge( $defaults, (array) $args );
}
function wp_parse_str( $input, &$result ) {
	parse_str( (string) $input, $result );
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( (string) $url, $component );
}
function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}
function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}
function rest_sanitize_boolean( $value ) {
	return in_array( $value, array( true, 1, '1', 'true', 'yes', 'on' ), true );
}
function wp_strip_all_tags( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function selected( $selected, $current = true, $display = true ) {
	$result = (string) $selected === (string) $current ? ' selected="selected"' : '';
	if ( $display ) {
		echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test double.
	}
	return $result;
}
function checked( $checked, $current = true, $display = true ) {
	$result = (string) $checked === (string) $current ? ' checked="checked"' : '';
	if ( $display ) {
		echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test double.
	}
	return $result;
}
function disabled( $disabled, $current = true, $display = true ) {
	$result = (string) $disabled === (string) $current ? ' disabled="disabled"' : '';
	if ( $display ) {
		echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test double.
	}
	return $result;
}
function _doing_it_wrong( ...$args ) {
	throw new RuntimeException( json_encode( $args ) );
}
function _deprecated_function( ...$args ) {
}
function wp_unique_id( $prefix = '' ) {
	static $id = 0;
	return $prefix . ++$id;
}
function current_time( $type, $gmt = 0 ) {
	return 'timestamp' === $type || 'U' === $type ? time() : gmdate( 'Y-m-d H:i:s' );
}

// ---- URLs, users, options and posts. ----

function home_url( $path = '' ) {
	return '/' . ltrim( (string) $path, '/' );
}
function site_url( $path = '' ) {
	return home_url( $path );
}
function admin_url( $path = '' ) {
	if ( 'admin-ajax.php' === $path && isset( $GLOBALS['fixture_ajax_url'] ) ) {
		return $GLOBALS['fixture_ajax_url'];
	}
	return '/wp-admin/' . ltrim( (string) $path, '/' );
}
function rest_url( $path = '' ) {
	return '/wp-json/' . ltrim( (string) $path, '/' );
}
function plugins_url( $path = '', $file = '' ) {
	return '/wp-content/plugins/' . basename( OHMYLMS_DIR ) . '/' . ltrim( (string) $path, '/' );
}
function wp_login_url( $redirect = '' ) {
	return '/wp-login.php';
}
function wp_lostpassword_url( $redirect = '' ) {
	return '/wp-login.php?action=lostpassword';
}
function wp_logout_url( $redirect = '' ) {
	return '/wp-login.php?action=logout';
}
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) {
		$params = $args[0];
		$url    = $args[1] ?? '';
	} else {
		$params = array( $args[0] => $args[1] );
		$url    = $args[2] ?? '';
	}
	$parts = explode( '?', (string) $url, 2 );
	$query = array();
	if ( isset( $parts[1] ) ) {
		parse_str( $parts[1], $query );
	}
	$query = array_filter( array_merge( $query, $params ), static fn( $value ) => false !== $value && null !== $value );
	return $parts[0] . ( $query ? '?' . http_build_query( $query ) : '' );
}
function remove_query_arg( $keys, $url = false ) {
	$url   = false === $url ? ( $_SERVER['REQUEST_URI'] ?? '/' ) : (string) $url;
	$parts = explode( '?', $url, 2 );
	$query = array();
	if ( isset( $parts[1] ) ) {
		parse_str( $parts[1], $query );
	}
	foreach ( (array) $keys as $key ) {
		unset( $query[ $key ] );
	}
	return $parts[0] . ( $query ? '?' . http_build_query( $query ) : '' );
}
function is_ssl() {
	return false;
}
function is_admin() {
	return false;
}
function is_user_logged_in() {
	return $GLOBALS['fixture_logged_in'] ?? true;
}
function wp_is_block_theme() {
	return false;
}
function get_current_user_id() {
	return 1;
}
function current_user_can( ...$args ) {
	return false;
}
function wp_create_nonce( $action = -1 ) {
	return 'nonce-' . $action;
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	$field = sprintf( '<input type="hidden" name="%s" value="fixture-nonce">', esc_attr( $name ) );
	if ( $display ) {
		echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
	}
	return $field;
}
function wp_max_upload_size() {
	return 2 * 1024 * 1024;
}
function get_option( $name, $default = false ) {
	return $GLOBALS['fixture_options'][ $name ] ?? $default;
}
function get_post_meta( ...$args ) {
	return '';
}
function get_user_meta( ...$args ) {
	return '';
}
function get_the_ID() {
	return 10;
}
function get_the_title( $id = 0 ) {
	return 'Fixture quiz';
}
function get_the_permalink( $id = 0 ) {
	return '/fixture';
}
function get_permalink( $id = 0 ) {
	return '/fixture';
}
function get_post_type( $id = 0 ) {
	return 'course';
}
function get_query_var( $name, $default = '' ) {
	return $default;
}
function get_bloginfo( $show = '' ) {
	return 'Fixture site';
}
function wp_get_attachment_url( $id ) {
	return false;
}
function locate_template( $names, $load = false, $once = true, $args = array() ) {
	return '';
}
function load_template( $file, $once = true, $args = array() ) {
	require $file;
}

// ---- Assets: enqueued script modules are recorded; the page shell prints them. ----

$GLOBALS['fixture_modules'] = array();

function wp_enqueue_script_module( $id, ...$args ) {
	$GLOBALS['fixture_modules'][ $id ] = true;
}
function wp_register_script_module( ...$args ) {
}
function wp_enqueue_style( ...$args ) {
}
function wp_register_style( ...$args ) {
}
function wp_add_inline_style( ...$args ) {
}
function wp_enqueue_script( ...$args ) {
}
function wp_register_script( ...$args ) {
}
function wp_localize_script( ...$args ) {
}
function wp_add_inline_script( ...$args ) {
}
function wp_script_is( ...$args ) {
	return false;
}
function wp_style_is( ...$args ) {
	return false;
}
