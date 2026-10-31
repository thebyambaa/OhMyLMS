<?php
/**
 * OhMyLMS\Utility\Lock against the real database: names (prefix, 64-character limit, stable hash), advisory
 * locks seen from a second connection (held while the work runs, nested takes, release on return and on an
 * exception, busy values) and option-row locks (busy, stale takeover, release on an exception).
 *
 * Runs against a WordPress install (the Local site by default). It creates no posts or users; the option
 * rows it writes are removed in `finally`.
 *
 * Usage (set OHMYLMS_LOCK_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/lock-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.RestrictedClasses

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_LOCK_TEST' ) ) {
	exit( "Set OHMYLMS_LOCK_TEST=1 to run the lock tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Utility\Lock;

$checks = 0;
$tag    = 'locktest-' . strtolower( wp_generate_password( 8, false, false ) );

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

// A second connection sees the locks as another request would.
$other = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, (int) ini_get( 'mysqli.default_port' ) );

/**
 * Whether no connection holds the advisory lock, asked from the second connection.
 *
 * @param string $name Lock name as MySQL sees it.
 * @return bool
 */
function lock_free( $name ) {
	global $other;
	return '1' === (string) $other->query( "SELECT IS_FREE_LOCK('" . $other->real_escape_string( $name ) . "')" )->fetch_row()[0];
}

/**
 * Take or release the advisory lock on the second connection.
 *
 * @param string $name Lock name as MySQL sees it.
 * @param bool   $take Take (true) or release (false).
 * @return void
 */
function other_lock( $name, $take ) {
	global $other;
	$escaped = $other->real_escape_string( $name );
	$other->query( $take ? "SELECT GET_LOCK('$escaped', 0)" : "SELECT RELEASE_LOCK('$escaped')" );
}

try {
	// ---- Names ----
	ok( 'ohmylms-skill-mappings-abc' === Lock::name( 'ohmylms-skill-mappings-abc' ), 'an ohmylms name is used as given' );
	ok( 'ohmylms_program_12' === Lock::name( 'ohmylms_program_12' ), 'underscore ohmylms names are used as given' );
	ok( 'oml_import_' . md5( 'x' ) === Lock::name( 'oml_import_' . md5( 'x' ) ), 'oml names are used as given' );
	ok( 'ohmylms-report' === Lock::name( 'report' ), 'a name without a plugin prefix gets ohmylms-' );
	$long   = 'ohmylms-' . str_repeat( 'very-long-name-', 6 );
	$hashed = Lock::name( $long );
	ok( 64 === strlen( $hashed ) && Lock::name( $long ) === $hashed, 'a long name becomes 64 characters, the same every time' );
	ok( 0 === strpos( $hashed, substr( $long, 0, 31 ) . '-' ) && substr( $hashed, -32 ) === md5( $long ), 'it keeps the start of the name and ends with the name hash' );
	ok( Lock::name( $long ) !== Lock::name( $long . 'x' ), 'two long names with the same start stay distinct' );
	$multibyte = Lock::name( str_repeat( 'é', 40 ) );
	ok( strlen( $multibyte ) <= 64 && 1 === preg_match( '/^ohmylms-[A-Za-z0-9_.:-]*-[a-f0-9]{32}$/D', $multibyte ), 'a long multibyte name is limited to plain characters and its hash' );
	$exactly = 'ohmylms-' . str_repeat( 'a', 56 );
	ok( Lock::name( $exactly ) === $exactly, 'a 64-character name is not hashed' );

	// ---- Advisory lock: held while the work runs, released afterwards ----
	$name = 'ohmylms-' . $tag;
	ok( lock_free( $name ), 'the lock starts free' );
	$seen = Lock::with(
		$name,
		0,
		static function () use ( $name ) {
			return lock_free( $name ) ? 'free' : 'held';
		}
	);
	ok( 'held' === $seen, 'another connection sees the lock while the work runs' );
	ok( lock_free( $name ), 'the lock is released after the work returns' );

	// ---- Nested takes of one name ----
	$states = Lock::with(
		$name,
		0,
		static function () use ( $name ) {
			$inner = Lock::with(
				$name,
				0,
				static function () use ( $name ) {
					return lock_free( $name );
				},
				'busy'
			);
			return array( $inner, lock_free( $name ) );
		}
	);
	ok( false === $states[0], 'a nested take of the same name runs its work under the lock' );
	ok( false === $states[1], 'releasing the nested take keeps the outer lock' );
	ok( lock_free( $name ), 'the lock is free after the outer take returns' );

	// ---- Release on an exception ----
	$caught = null;
	try {
		Lock::with(
			$name,
			0,
			static function () use ( $name ) {
				Lock::with(
					$name,
					0,
					static function () {
						throw new DomainException( 'inner failure' );
					}
				);
			}
		);
	} catch ( DomainException $error ) {
		$caught = $error->getMessage();
	}
	ok( 'inner failure' === $caught, 'the exception propagates through both takes' );
	ok( lock_free( $name ), 'both takes are released when the work throws' );

	// ---- Busy ----
	other_lock( $name, true );
	$ran     = false;
	$default = Lock::with(
		$name,
		0,
		static function () use ( &$ran ) {
			$ran = true;
		}
	);
	ok( ! $ran, 'the work does not run while another connection holds the lock' );
	ok( is_wp_error( $default ) && 'ohmylms_lock_busy' === $default->get_error_code() && 409 === $default->get_error_data()['status'], 'the default busy value is ohmylms_lock_busy (409)' );
	ok( false === Lock::with( $name, 0, '__return_true', false ), 'a plain busy value is returned as given' );
	ok( 0 === Lock::with( $name, 0, '__return_true', 0 ), 'a zero busy value is returned as given' );
	$built = Lock::with(
		$name,
		0,
		'__return_true',
		static function () {
			return 'built';
		}
	);
	ok( 'built' === $built, 'a Closure busy value is called' );
	$thrown = null;
	try {
		Lock::with(
			$name,
			0,
			'__return_true',
			static function () {
				throw new RuntimeException( 'busy', 423 );
			}
		);
	} catch ( RuntimeException $error ) {
		$thrown = $error->getCode();
	}
	ok( 423 === $thrown, 'a busy Closure may throw' );
	ok( ! Lock::acquire( $name, 0 ), 'acquire() answers false while the lock is held elsewhere' );
	$waited = microtime( true );
	ok( ! Lock::acquire( $name, 1 ), 'acquire() with a timeout still fails' );
	ok( microtime( true ) - $waited >= 0.5, 'acquire() waited for the timeout' );
	other_lock( $name, false );
	ok( Lock::acquire( $name, 0 ) && ! lock_free( $name ), 'acquire() takes the lock once it is free' );
	Lock::release( $name );
	ok( lock_free( $name ), 'release() frees it' );

	// ---- Hashed names are what MySQL locks ----
	$long_name = 'ohmylms-' . $tag . '-' . str_repeat( 'x', 80 );
	$held      = Lock::with(
		$long_name,
		0,
		static function () use ( $long_name ) {
			return ! lock_free( Lock::name( $long_name ) );
		}
	);
	ok( true === $held, 'a long name is locked under its hashed name' );
	ok( lock_free( Lock::name( $long_name ) ), 'and released' );

	// ---- Option-row locks ----
	$option = $tag . '-order-1';
	$key    = Lock::option_key( $option );
	ok( 'ohmylms_lock_' . $tag . '-order-1' === $key, 'option locks are stored as ohmylms_lock_<name>' );
	$inside = Lock::with_option(
		$option,
		60,
		static function () use ( $key ) {
			return (int) get_option( $key );
		}
	);
	ok( $inside > 0, 'the option row exists while the work runs' );
	ok( false === get_option( $key ), 'the option row is removed afterwards' );
	add_option( $key, time(), '', 'no' );
	$ran  = false;
	$busy = Lock::with_option(
		$option,
		60,
		static function () use ( &$ran ) {
			$ran = true;
		}
	);
	ok( ! $ran && is_wp_error( $busy ) && 'ohmylms_lock_busy' === $busy->get_error_code(), 'a fresh option lock is busy' );
	ok( ! Lock::acquire_option( $option, 60 ), 'acquire_option() answers false for a fresh lock' );
	ok( (int) get_option( $key ) > 0, 'a busy attempt leaves the holder\'s row' );
	update_option( $key, time() - 120, 'no' );
	ok( Lock::acquire_option( $option, 60 ), 'a stale option lock is taken over' );
	ok( (int) get_option( $key ) >= time() - 5, 'the takeover renews the timestamp' );
	Lock::release_option( $option );
	ok( false === get_option( $key ), 'release_option() removes the row' );
	$caught = null;
	try {
		Lock::with_option(
			$option,
			60,
			static function () {
				throw new DomainException( 'option failure' );
			}
		);
	} catch ( DomainException $error ) {
		$caught = $error->getMessage();
	}
	ok( 'option failure' === $caught && false === get_option( $key ), 'the option lock is released when the work throws' );
} finally {
	foreach ( array( $tag . '-order-1' ) as $leftover ) {
		delete_option( Lock::option_key( $leftover ) );
	}
	$other->close();
}

echo "Lock integration: {$checks} checks passed.\n";
