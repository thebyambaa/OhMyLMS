<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Interface language of learner pages: English or Mongolian, chosen by each learner.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\I18n;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the locale of front-end requests and stores a learner's choice.
 *
 * Order: the `locale` user meta (the field of Users › Profile › Language) of a signed-in user, the
 * `ohmylms_locale` cookie, the browser's `Accept-Language`, then English. Only the interface locales
 * ({@see self::locales()}) count; anything else falls through to the next source. wp-admin keeps
 * WordPress' own rule (the profile language), and so do admin-ajax and REST calls made from wp-admin.
 * See "Interface languages" in docs/DEVELOPMENT.md.
 */
final class InterfaceLanguage {

	/** Cookie of a visitor's choice. */
	const COOKIE = 'ohmylms_locale';

	/** Query parameter of the switcher without JavaScript. */
	const QUERY_VAR = 'ohmylms_locale';

	/** Nonce field of the switcher form for signed-in users. */
	const NONCE_FIELD = 'ohmylms_locale_nonce';

	/** Nonce action of the switcher form. */
	const NONCE_ACTION = 'ohmylms_switch_locale';

	/** User meta WordPress reads for the profile language. */
	const USER_META = 'locale';

	/** Locale when nothing else decides. */
	const DEFAULT_LOCALE = 'en_US';

	/**
	 * Resolved locales of this request, by user, cookie and header.
	 *
	 * @var array<string, array{locale: string, source: string}>
	 */
	private static $resolved = array();

	/**
	 * Whether {@see self::filter_locale()} is running (finding the current user can translate).
	 *
	 * @var bool
	 */
	private static $resolving = false;

	/**
	 * Whether the all-strings Jed catalog was already given to a script on this page.
	 *
	 * @var bool
	 */
	private static $catalog_served = false;

	/**
	 * Register hooks. Runs when the plugin file loads, before any text domain is loaded.
	 */
	public static function init() {
		add_filter( 'determine_locale', array( __CLASS__, 'filter_locale' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_query' ), 1 );
		add_filter( 'wp_headers', array( __CLASS__, 'vary' ) );
		add_filter( 'ohmylms_rest_v1_controllers', array( __CLASS__, 'controllers' ) );
		add_filter( 'load_script_translation_file', array( __CLASS__, 'script_catalog' ), 10, 3 );
		foreach ( array( 'wp_print_scripts', 'wp_print_footer_scripts', 'admin_print_footer_scripts' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'translate_scripts' ), 1 );
		}
		Switcher::init();
	}

	/**
	 * Interface locales and their names in their own language.
	 *
	 * @return array<string, string> WordPress locale => native name.
	 */
	public static function locales() {
		/**
		 * Interface languages learners can choose. Keys are WordPress locales whose `ohmylms-<locale>.mo`
		 * exists (and, for WordPress' own strings, the core language pack); values are the names
		 * shown in the switcher, in that language. The first locale wins ties in `Accept-Language`.
		 *
		 * @param array<string, string> $locales Locale => native name.
		 */
		$locales = apply_filters(
			'ohmylms_interface_locales',
			array(
				'en_US' => 'English',
				'mn'    => 'Монгол',
			)
		);
		$clean   = array();
		foreach ( (array) $locales as $locale => $label ) {
			if ( is_string( $locale ) && preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?(_[A-Za-z0-9]+)?$/', $locale ) && is_string( $label ) && '' !== trim( $label ) ) {
				$clean[ $locale ] = $label;
			}
		}
		return $clean ? $clean : array( self::DEFAULT_LOCALE => 'English' );
	}

	/**
	 * Whether a value is one of the interface locales.
	 *
	 * @param mixed $locale Value.
	 * @return bool
	 */
	public static function is_allowed( $locale ) {
		return is_string( $locale ) && array_key_exists( $locale, self::locales() );
	}

	/**
	 * English when offered, otherwise the first interface locale.
	 *
	 * @return string
	 */
	public static function default_locale() {
		$locales = self::locales();
		return isset( $locales[ self::DEFAULT_LOCALE ] ) ? self::DEFAULT_LOCALE : (string) array_key_first( $locales );
	}

	/**
	 * Resolve the locale from its sources.
	 *
	 * @param int    $user_id Signed-in user, 0 for a visitor.
	 * @param string $cookie  Value of the `ohmylms_locale` cookie.
	 * @param string $accept  `Accept-Language` header.
	 * @return array{locale: string, source: string} Source: `user`, `cookie`, `browser` or `default`.
	 */
	public static function resolve( $user_id, $cookie, $accept ) {
		if ( $user_id ) {
			$saved = get_user_meta( (int) $user_id, self::USER_META, true );
			if ( self::is_allowed( $saved ) ) {
				return array(
					'locale' => $saved,
					'source' => 'user',
				);
			}
		}
		if ( self::is_allowed( $cookie ) ) {
			return array(
				'locale' => $cookie,
				'source' => 'cookie',
			);
		}
		$browser = AcceptLanguage::negotiate( $accept, array_keys( self::locales() ), '' );
		if ( '' !== $browser ) {
			return array(
				'locale' => $browser,
				'source' => 'browser',
			);
		}
		return array(
			'locale' => self::default_locale(),
			'source' => 'default',
		);
	}

	/**
	 * The locale of the current request's user, cookie and browser.
	 *
	 * @return array{locale: string, source: string}
	 */
	public static function current() {
		$user_id = did_action( 'plugins_loaded' ) ? get_current_user_id() : 0;
		$cookie  = isset( $_COOKIE[ self::COOKIE ] ) && is_string( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		$accept  = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) && is_string( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : '';
		$key     = $user_id . '|' . $cookie . '|' . $accept;
		if ( ! isset( self::$resolved[ $key ] ) ) {
			self::$resolved[ $key ] = self::resolve( $user_id, $cookie, $accept );
		}
		return self::$resolved[ $key ];
	}

	/**
	 * The interface locale the page is rendered in (the default when another locale is active).
	 *
	 * @return string
	 */
	public static function active_locale() {
		$locale = determine_locale();
		return self::is_allowed( $locale ) ? $locale : self::default_locale();
	}

	/**
	 * Whether this request follows the learner's language.
	 *
	 * @return bool
	 */
	public static function applies() {
		if ( count( self::locales() ) < 2 || wp_installing() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}
		// Emails and other code that call switch_to_locale() keep the locale they switched to.
		if ( ! empty( $GLOBALS['wp_locale_switcher'] ) && is_locale_switched() ) {
			return false;
		}
		$ajax = wp_doing_ajax();
		if ( is_admin() && ! $ajax ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: WordPress' own login language picker.
		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] && ( ! empty( $_GET['wp_lang'] ) || ! empty( $_COOKIE['wp_lang'] ) ) ) {
			return false;
		}
		return ! ( ( $ajax || self::is_rest_request() ) && self::from_admin() );
	}

	/**
	 * `determine_locale` filter: the learner's locale on the front end.
	 *
	 * @param string $locale Locale WordPress determined.
	 * @return string
	 */
	public static function filter_locale( $locale ) {
		if ( self::$resolving || ! self::applies() ) {
			return $locale;
		}
		self::$resolving = true;
		try {
			$resolved = self::current();
		} finally {
			self::$resolving = false;
		}
		return $resolved['locale'];
	}

	/**
	 * Whether the request is a REST call, also before WordPress parsed it.
	 *
	 * @return bool
	 */
	public static function is_rest_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check.
		if ( isset( $_GET['rest_route'] ) ) {
			return true;
		}
		$uri    = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$prefix = (string) wp_parse_url( home_url( '/' . rest_get_url_prefix() . '/' ), PHP_URL_PATH );
		return '' !== $uri && '' !== $prefix && 0 === strpos( $uri, $prefix );
	}

	/**
	 * Whether the request was sent from a wp-admin screen (its referrer).
	 *
	 * @return bool
	 */
	private static function from_admin() {
		$referer = isset( $_SERVER['HTTP_REFERER'] ) && is_string( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		if ( '' === $referer ) {
			return false;
		}
		$strip = static function ( $url ) {
			return preg_replace( '#^https?:#i', '', $url );
		};
		return 0 === strpos( $strip( $referer ), $strip( admin_url() ) );
	}

	/**
	 * Store a choice: the `locale` user meta of a signed-in user, and the cookie for everyone (it
	 * keeps the choice after signing out).
	 *
	 * @param string $locale  Interface locale.
	 * @param int    $user_id Signed-in user, 0 for a visitor.
	 * @return string|WP_Error Where it was saved: `user` or `cookie`.
	 */
	public static function save( $locale, $user_id ) {
		if ( ! self::is_allowed( $locale ) ) {
			return new WP_Error( 'ohmylms_locale_unknown', __( 'This language is not available.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$source = 'cookie';
		if ( $user_id ) {
			update_user_meta( (int) $user_id, self::USER_META, $locale );
			if ( get_user_meta( (int) $user_id, self::USER_META, true ) !== $locale ) {
				return new WP_Error( 'ohmylms_locale_save_failed', __( 'Your language could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
			}
			$source = 'user';
		}
		self::set_cookie( $locale );
		self::$resolved = array();
		/**
		 * A learner chose an interface language.
		 *
		 * @param string $locale  Locale.
		 * @param int    $user_id User, 0 for a visitor.
		 */
		do_action( 'ohmylms_interface_locale_changed', $locale, (int) $user_id );
		return $source;
	}

	/**
	 * Set the choice cookie (one year, whole site, SameSite=Lax) for this and later requests.
	 *
	 * @param string $locale Interface locale.
	 * @return bool Whether the header was sent.
	 */
	public static function set_cookie( $locale ) {
		$_COOKIE[ self::COOKIE ] = $locale;
		if ( headers_sent() ) {
			return false;
		}
		return setcookie(
			self::COOKIE,
			$locale,
			array(
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Absolute URL of the current request, on the site's own scheme and host.
	 *
	 * @return string
	 */
	public static function request_url() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$home = wp_parse_url( home_url() );
		$port = isset( $home['port'] ) ? ':' . $home['port'] : '';
		return $home['scheme'] . '://' . $home['host'] . $port . '/' . ltrim( $uri, '/' );
	}

	/**
	 * Apply `?ohmylms_locale=` (the switcher without JavaScript). Visitors need no nonce (only their
	 * own cookie changes); a signed-in user's profile changes only with the form's nonce.
	 *
	 * @param array  $query Unslashed query parameters.
	 * @param string $url   Current URL.
	 * @return string The URL without the switcher's parameters.
	 */
	public static function switch_from_query( array $query, $url ) {
		$locale  = isset( $query[ self::QUERY_VAR ] ) && is_string( $query[ self::QUERY_VAR ] ) ? $query[ self::QUERY_VAR ] : '';
		$nonce   = isset( $query[ self::NONCE_FIELD ] ) && is_string( $query[ self::NONCE_FIELD ] ) ? $query[ self::NONCE_FIELD ] : '';
		$user_id = get_current_user_id();
		if ( self::is_allowed( $locale ) && ( ! $user_id || wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) ) {
			self::save( $locale, $user_id );
		}
		return remove_query_arg( array( self::QUERY_VAR, self::NONCE_FIELD ), $url );
	}

	/**
	 * `template_redirect`: apply `?ohmylms_locale=` and go back to the clean URL.
	 */
	public static function redirect_query() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- switch_from_query() checks the nonce of signed-in users.
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated against the locale list and the nonce.
		$target = self::switch_from_query( wp_unslash( $_GET ), self::request_url() );
		nocache_headers();
		wp_safe_redirect( $target, 303 );
		exit;
	}

	/**
	 * `wp_headers`: front-end pages differ by the cookie and `Accept-Language`, so shared caches
	 * must keep one copy per value.
	 *
	 * @param array $headers Headers.
	 * @return array
	 */
	public static function vary( $headers ) {
		if ( is_admin() || count( self::locales() ) < 2 ) {
			return $headers;
		}
		$tokens = isset( $headers['Vary'] ) ? array_filter( array_map( 'trim', explode( ',', (string) $headers['Vary'] ) ) ) : array();
		$known  = array_map( 'strtolower', $tokens );
		foreach ( array( 'Accept-Language', 'Cookie' ) as $token ) {
			if ( ! in_array( strtolower( $token ), $known, true ) ) {
				$tokens[] = $token;
			}
		}
		$headers['Vary'] = implode( ', ', $tokens );
		return $headers;
	}

	/**
	 * Add the locale controller to the REST controllers.
	 *
	 * @param string[] $controllers Controller classes.
	 * @return string[]
	 */
	public static function controllers( $controllers ) {
		$controllers   = (array) $controllers;
		$controllers[] = Rest\LocaleController::class;
		return $controllers;
	}

	/**
	 * Give every plugin script that uses `wp-i18n` and has no text domain yet the `ohmylms`
	 * translations (`languages/ohmylms-<locale>-<handle>.json`), on admin and learner pages alike.
	 * Runs just before scripts print, so late registrations are covered.
	 */
	public static function translate_scripts() {
		$base = plugins_url( '/', OHMYLMS_FILE );
		foreach ( wp_scripts()->registered as $handle => $script ) {
			if ( ! empty( $script->textdomain ) || ! is_string( $script->src ) || 0 !== strpos( $script->src, $base ) || ! in_array( 'wp-i18n', (array) $script->deps, true ) ) {
				continue;
			}
			wp_set_script_translations( $handle, 'ohmylms', OHMYLMS_DIR . '/languages' );
		}
	}

	/**
	 * `load_script_translation_file`: when WordPress finds no file for an `ohmylms` script (it
	 * passes `false` after trying `ohmylms-<locale>-<handle>.json` and the hashed names), use the
	 * all-strings catalog `languages/ohmylms-<locale>-ohmylms.json`. It is printed once per page,
	 * before the first such script; `wp.i18n` keeps it for the scripts after it.
	 *
	 * @param string|false $file   File WordPress would load.
	 * @param string       $handle Script handle.
	 * @param string       $domain Text domain.
	 * @return string|false
	 */
	public static function script_catalog( $file, $handle, $domain ) {
		if ( false !== $file || 'ohmylms' !== $domain || self::$catalog_served ) {
			return $file;
		}
		$catalog = OHMYLMS_DIR . '/languages/ohmylms-' . determine_locale() . '-ohmylms.json';
		if ( ! is_readable( $catalog ) ) {
			return $file;
		}
		self::$catalog_served = true;
		return $catalog;
	}
}
