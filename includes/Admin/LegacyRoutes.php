<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Map from the recovered admin's hash routes to the typed admin app.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Admin;

use OhMyLMS\Assets\AdminAppAssets;

defined( 'ABSPATH' ) || exit;

/**
 * The one list of legacy screens (`admin.php?page=ohmylms#/...`) that the new app
 * (`admin.php?page=ohmylms-app#/...`) replaces. Only screens at parity are listed; every other legacy
 * route keeps opening the legacy screen. PHP links use {@see LegacyRoutes::url()}, and
 * assets/js/legacy-redirects.js applies the same rules in the browser. See docs/LEGACY-ROUTES.md.
 *
 * A rule has:
 * - `from`: a path pattern. Segments are literals, `:name` (required), `:name?` (optional, only at
 *   the end) or a final `*` (any remaining segments).
 * - `to`: the new route. `:name` is replaced by the URL-encoded path or query parameter.
 * - `query` (optional): query parameter => allowed values (`''` means absent). The rule applies only
 *   when every listed parameter has an allowed value; the value is then available to `to` and is not
 *   copied to the new route.
 * - `skip` (optional): query parameters whose presence keeps the legacy screen.
 *
 * Rules are tried in order and the first match wins. Other query parameters are copied unchanged.
 *
 * Cutover switch: while {@see LegacyRoutes::legacy_admin_enabled()} is false (constant
 * `OHMYLMS_LEGACY_ADMIN` or filter `ohmylms_legacy_admin_enabled`), the legacy page redirects to the new
 * app on the server, and the app maps the carried-over hash. See docs/CUTOVER.md.
 */
final class LegacyRoutes {

	/**
	 * Query parameter that keeps an administrator on the legacy screen (`&ohmylms_legacy=1`).
	 */
	const OPT_OUT = 'ohmylms_legacy';

	/**
	 * Query parameter the server redirect adds, so the app maps the legacy hash it receives.
	 */
	const FROM_LEGACY = 'ohmylms_from_legacy';

	/**
	 * Constant that turns the legacy admin off when defined as false (`wp-config.php`).
	 */
	const LEGACY_ADMIN_CONSTANT = 'OHMYLMS_LEGACY_ADMIN';

	/**
	 * New-app route that unmapped legacy routes open after the cutover.
	 */
	const APP_HOME = '/';

	/**
	 * Id of the notice that names a legacy screen the new app does not have yet.
	 */
	const NOTICE_ID = 'ohmylms-legacy-unavailable';

	/**
	 * Script handle of the browser redirect.
	 */
	const HANDLE = 'ohmylms-legacy-redirects';

	/**
	 * Legacy settings tab => new settings tab.
	 */
	const SETTINGS_TABS = array(
		'general-settings'         => 'general',
		'branding-settings'        => 'branding',
		'account-privacy-settings' => 'account-and-privacy',
		'parmalink-settings'       => 'permalink',
		'advanced-settings'        => 'advanced',
		'ai-tutor-settings'        => 'ai-tutor',
		'design-system-settings'   => 'design-system',
		'xapi-settings'            => 'xapi',
	);

	/**
	 * Legacy gamification tab => new gamification tab. The legacy Reward tab has no new equivalent.
	 */
	const GAMIFICATION_TABS = array(
		'point-settings'       => 'points',
		'badge-settings'       => 'badges',
		'level-settings'       => 'levels',
		'leaderboard-settings' => 'leaderboard',
		'streak-settings'      => 'streaks',
		'xp-settings'          => 'xp',
	);

	/**
	 * Cached result of {@see LegacyRoutes::enabled()}.
	 *
	 * @var bool|null
	 */
	private static $enabled = null;

	/**
	 * Cached result of {@see LegacyRoutes::legacy_admin_enabled()}.
	 *
	 * @var bool|null
	 */
	private static $legacy_admin = null;

	/**
	 * Cached rules.
	 *
	 * @var array[]|null
	 */
	private static $routes = null;

	/**
	 * The rules, most specific first.
	 *
	 * @return array[] Each with `from`, `to` and optionally `query` and `skip`.
	 */
	public static function routes() {
		if ( null !== self::$routes ) {
			return self::$routes;
		}
		$routes = array(
			array(
				'from' => '/',
				'to'   => '/dashboard',
			),
			array(
				'from' => '/dashboard',
				'to'   => '/dashboard',
			),
			array(
				'from' => '/earnings-report',
				'to'   => '/earnings',
			),
			array(
				'from' => '/course/:id/report',
				'to'   => '/reports/courses/:id',
			),
			array(
				'from' => '/orders/:page?',
				'to'   => '/orders',
			),
			array(
				'from' => '/memberships/orders/:page?',
				'to'   => '/orders',
			),
			array(
				'from' => '/order-edit/:id',
				'to'   => '/orders/:id',
			),
			array(
				'from' => '/coupons',
				'to'   => '/coupons',
			),
			array(
				'from' => '/memberships/coupons',
				'to'   => '/coupons',
			),
			array(
				'from' => '/subscriptions/:page?',
				'to'   => '/subscriptions',
			),
			array(
				'from' => '/memberships/subscriptions/:page?',
				'to'   => '/subscriptions',
			),
			array(
				'from' => '/subscription-edit/:id',
				'to'   => '/subscriptions/:id',
			),
			array(
				'from' => '/memberships',
				'to'   => '/memberships',
			),
			array(
				'from' => '/certificates',
				'to'   => '/certificates',
			),
			array(
				'from' => '/gamification/certificates',
				'to'   => '/certificates',
			),
			array(
				'from' => '/certificate-edit/:id',
				'to'   => '/certificates/:id',
			),
			array(
				'from' => '/emails',
				'to'   => '/emails',
			),
			array(
				'from' => '/emails/:id',
				'to'   => '/emails?email=:id',
			),
			array(
				'from' => '/settings/emails-settings/*',
				'to'   => '/emails',
			),
			array(
				'from' => '/webhooks',
				'to'   => '/webhooks',
			),
			array(
				'from' => '/settings/webhooks-settings/*',
				'to'   => '/webhooks',
			),
			array(
				'from' => '/integrations',
				'to'   => '/integrations',
			),
			array(
				'from' => '/settings/payments-settings',
				'to'   => '/settings?tab=payment-gateways',
			),
			array(
				'from' => '/settings/payments-settings/payments/*',
				'to'   => '/settings?tab=payment-gateways',
			),
			array(
				'from' => '/settings/payments-settings/currency/*',
				'to'   => '/settings?tab=currency',
			),
			array(
				'from' => '/settings/payments-settings/tax/*',
				'to'   => '/settings?tab=tax',
			),
			array(
				'from' => '/settings/gamification-settings',
				'to'   => '/gamification',
			),
			array(
				'from' => '/setup-wizard/:tab?',
				'to'   => '/setup',
			),
			array(
				'from' => '/assignments',
				'to'   => '/assignments',
			),
			array(
				'from' => '/content-hub/assignments',
				'to'   => '/assignments',
			),
			array(
				'from' => '/assessments/assignments',
				'to'   => '/assignments',
			),
			array(
				'from' => '/assignment-edit/:id',
				'to'   => '/assignments/:id',
			),
			array(
				'from' => '/assignment-report',
				'to'   => '/assignments',
			),
			array(
				'from' => '/assignment-report/:id',
				'to'   => '/assignments/:id/submissions',
			),
			// The legacy grading route names the student, the new one the submission row; the browser
			// can only open the submission list. PHP links use assignment_grading_url() instead.
			array(
				'from' => '/assignment-report/:id/grade-assignment/:student',
				'to'   => '/assignments/:id/submissions?student=:student',
			),
			// The new question bank does not open the create or import dialog from the address.
			array(
				'from' => '/content-hub/question-bank',
				'to'   => '/content-hub/question-bank',
				'skip' => array( 'action' ),
			),
			array(
				'from' => '/extensions/question-bank',
				'to'   => '/content-hub/question-bank',
				'skip' => array( 'action' ),
			),
			array(
				'from' => '/assessments/question-bank',
				'to'   => '/content-hub/question-bank',
				'skip' => array( 'action' ),
			),
			array(
				'from'  => '/accounthub',
				'to'    => '/account-hub',
				'query' => array( 'tab' => array( '', 'home', 'schools' ) ),
			),
			array(
				'from'  => '/accounthub',
				'to'    => '/account-hub?tab=:tab',
				'query' => array( 'tab' => array( 'classes', 'teachers', 'parents' ) ),
			),
			array(
				'from'  => '/accounthub',
				'to'    => '/students',
				'query' => array( 'tab' => array( 'students' ) ),
			),
			array(
				'from' => '/quiz-report/:id/*',
				'to'   => '/quizzes/:id/attempts',
			),
			// Quizzes, courses, lessons and the curriculum workspace. The Content Hub frame itself and its
			// Exams tab stay legacy until exams are rebuilt; list dialogs opened by `?action=` stay legacy too.
			array(
				'from' => '/quiz-edit/:id',
				'to'   => '/quizzes/:id',
			),
			array(
				'from' => '/quizzes',
				'to'   => '/quizzes',
				'skip' => array( 'action' ),
			),
			array(
				'from' => '/content-hub/quizzes',
				'to'   => '/quizzes',
				'skip' => array( 'action' ),
			),
			array(
				'from' => '/assessments',
				'to'   => '/quizzes',
				'skip' => array( 'action' ),
			),
			array(
				'from' => '/course-edit/:id/*',
				'to'   => '/courses/:id',
			),
			array(
				'from' => '/courses',
				'to'   => '/courses',
			),
			array(
				'from' => '/content-hub/courses',
				'to'   => '/courses',
			),
			array(
				'from' => '/lesson-edit/:id',
				'to'   => '/content-hub/lessons/:id',
			),
			array(
				'from' => '/content-hub/lessons',
				'to'   => '/content-hub/lessons',
			),
			array(
				'from' => '/content-hub/skills',
				'to'   => '/content-hub/skills',
				'skip' => array( 'add' ),
			),
			array(
				'from' => '/extensions/skills',
				'to'   => '/content-hub/skills',
				'skip' => array( 'add' ),
			),
			array(
				'from' => '/content-hub/curriculum',
				'to'   => '/content-hub/curriculum',
			),
			array(
				'from' => '/extensions/curriculum',
				'to'   => '/content-hub/curriculum',
			),
			array(
				'from' => '/categories',
				'to'   => '/content-hub/curriculum',
			),
			array(
				'from' => '/tags',
				'to'   => '/content-hub/curriculum',
			),
			array(
				'from' => '/content-hub/curriculum/skill-map',
				'to'   => '/content-hub/curriculum/skill-map',
			),
			array(
				'from' => '/content-hub/curriculum/subjects',
				'to'   => '/content-hub/curriculum/subjects',
			),
			array(
				'from' => '/content-hub/curriculum/syllabus/:id',
				'to'   => '/content-hub/curriculum/syllabus/:id',
			),
			array(
				'from' => '/content-hub/bundles',
				'to'   => '/content-hub/bundles',
			),
			array(
				'from' => '/extensions/bundles',
				'to'   => '/content-hub/bundles',
			),
		);
		foreach ( self::SETTINGS_TABS as $legacy => $tab ) {
			$routes[] = array(
				'from' => '/settings/' . $legacy . '/*',
				'to'   => '/settings?tab=' . $tab,
			);
		}
		foreach ( self::GAMIFICATION_TABS as $legacy => $tab ) {
			$routes[] = array(
				'from' => '/gamification/' . $legacy,
				'to'   => '/gamification?tab=' . $tab,
			);
			$routes[] = array(
				'from' => '/settings/gamification-settings/' . $legacy . '/*',
				'to'   => '/gamification?tab=' . $tab,
			);
		}
		/**
		 * Filters the legacy-to-new admin route map. Add a rule only when the new screen replaces the
		 * legacy one completely.
		 *
		 * @param array[] $routes Rules with `from`, `to` and optionally `query` and `skip`.
		 */
		$routes       = (array) apply_filters( 'ohmylms_legacy_routes', $routes );
		self::$routes = array_values(
			array_filter(
				$routes,
				static function ( $rule ) {
					return is_array( $rule ) && isset( $rule['from'], $rule['to'] )
						&& 0 === strpos( (string) $rule['from'], '/' ) && 0 === strpos( (string) $rule['to'], '/' );
				}
			)
		);
		return self::$routes;
	}

	/**
	 * Whether links go to the new app. False when the app has not been built; always true once the
	 * legacy admin is switched off.
	 *
	 * @return bool
	 */
	public static function enabled() {
		if ( null === self::$enabled ) {
			/**
			 * Filters whether legacy admin links open the new admin app.
			 *
			 * @param bool $enabled True when the app's assets are present.
			 */
			$enabled       = (bool) apply_filters( 'ohmylms_legacy_routes_enabled', null !== AdminAppAssets::locate() );
			self::$enabled = $enabled || ! self::legacy_admin_enabled();
		}
		return self::$enabled;
	}

	/**
	 * Whether the recovered legacy admin (`admin.php?page=ohmylms`) is still served and linked.
	 *
	 * Defaults to true. Define `OHMYLMS_LEGACY_ADMIN` as false, or return false from the
	 * `ohmylms_legacy_admin_enabled` filter, to cut over to the new app. The legacy admin stays on while
	 * the new app is not built, because there would be nothing to open instead.
	 *
	 * @return bool
	 */
	public static function legacy_admin_enabled() {
		if ( null === self::$legacy_admin ) {
			$default = defined( self::LEGACY_ADMIN_CONSTANT ) ? (bool) constant( self::LEGACY_ADMIN_CONSTANT ) : true;
			/**
			 * Filters whether the recovered legacy admin is still served. When false, the OhMyLMS menu
			 * opens the new app, `admin.php?page=ohmylms` redirects to it and only `&ohmylms_legacy=1`
			 * keeps the legacy screen. See docs/CUTOVER.md.
			 *
			 * @param bool $enabled Value of OHMYLMS_LEGACY_ADMIN, true when it is not defined.
			 */
			$enabled            = (bool) apply_filters( 'ohmylms_legacy_admin_enabled', $default );
			self::$legacy_admin = $enabled || null === AdminAppAssets::locate();
		}
		return self::$legacy_admin;
	}

	/**
	 * Forget cached rules and flags (after changing the filters).
	 */
	public static function reset() {
		self::$enabled      = null;
		self::$legacy_admin = null;
		self::$routes       = null;
	}

	/**
	 * Legacy screens the new app does not have yet, listed in the notice shown after the cutover.
	 *
	 * @return array<string, string> Legacy route => screen name.
	 */
	public static function unavailable() {
		$screens = array(
			'/content-hub'                          => __( 'Content Hub overview', 'ohmylms' ),
			'/content-hub/exams'                    => __( 'Exams', 'ohmylms' ),
			'/content-hub/curriculum/compare'       => __( 'Curriculum comparison', 'ohmylms' ),
			'/communities'                          => __( 'Communities', 'ohmylms' ),
			'/sessions'                             => __( 'Live sessions', 'ohmylms' ),
			'/students/:id/report'                  => __( 'Student report', 'ohmylms' ),
			'/courses/:id/students'                 => __( 'Course students', 'ohmylms' ),
			'/settings/design-settings'             => __( 'Design settings', 'ohmylms' ),
			'/settings/migration-settings'          => __( 'Migration', 'ohmylms' ),
			'/gamification/reward-settings'         => __( 'Rewards', 'ohmylms' ),
			'/accounthub?tab=users'                 => __( 'All accounts', 'ohmylms' ),
			'/quiz-report'                          => __( 'Quiz reports overview', 'ohmylms' ),
			'/content-hub/question-bank?action=new' => __( 'Question create and import dialogs', 'ohmylms' ),
			'/content-hub/skills?add=1'             => __( 'Add skill dialog', 'ohmylms' ),
		);
		/**
		 * Filters the legacy screens listed as not yet available in the new app.
		 *
		 * @param array<string, string> $screens Legacy route => screen name.
		 */
		return (array) apply_filters( 'ohmylms_legacy_unavailable_screens', $screens );
	}

	/**
	 * Where a request for the legacy page goes after the cutover: the new app, marked with
	 * {@see LegacyRoutes::FROM_LEGACY}. The browser keeps the `#/route` fragment across the redirect, and
	 * the app maps it (assets/js/legacy-redirects.js). Other query parameters are kept.
	 *
	 * @return string|null Absolute URL, or null to serve the legacy screen.
	 */
	public static function legacy_redirect_url() {
		if ( self::legacy_admin_enabled() || self::opted_out() ) {
			return null;
		}
		$query = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only; the values are passed on unchanged.
		foreach ( wp_unslash( $_GET ) as $name => $value ) {
			$name = sanitize_key( $name );
			if ( is_string( $value ) && '' !== $name && ! in_array( $name, array( 'page', self::OPT_OUT, self::FROM_LEGACY ), true ) ) {
				$query[ $name ] = rawurlencode( sanitize_text_field( $value ) );
			}
		}
		$query['page']              = AppPage::SLUG;
		$query[ self::FROM_LEGACY ] = '1';
		return add_query_arg( $query, admin_url( 'admin.php' ) );
	}

	/**
	 * Redirect the legacy page to the new app after the cutover (`load-toplevel_page_ohmylms`).
	 */
	public static function redirect_legacy_page() {
		$url = self::legacy_redirect_url();
		if ( null === $url ) {
			return;
		}
		wp_safe_redirect( $url, 302, 'OhMyLMS' );
		exit;
	}

	/**
	 * Whether the new app was opened by the cutover redirect.
	 *
	 * @return bool
	 */
	public static function from_legacy() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag.
		return isset( $_GET[ self::FROM_LEGACY ] ) && '1' === sanitize_key( wp_unslash( $_GET[ self::FROM_LEGACY ] ) );
	}

	/**
	 * Load the hash mapping in the head of the new app when the cutover redirect opened it, before the
	 * app's router reads the hash.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public static function enqueue_landing( $hook ) {
		if ( ! AppPage::is_app_screen( $hook ) || ! self::from_legacy() || ! current_user_can( AppPage::CAPABILITY ) ) {
			return;
		}
		$file = OHMYLMS_DIR . '/assets/js/legacy-redirects.js';
		wp_enqueue_script( self::HANDLE, OHMYLMS_URL . '/assets/js/legacy-redirects.js', array(), (string) filemtime( $file ), false );
		$data            = self::script_data();
		$data['landing'] = array(
			'param'    => self::FROM_LEGACY,
			'home'     => self::APP_HOME,
			'noticeId' => self::NOTICE_ID,
		);
		wp_add_inline_script( self::HANDLE, 'window.ohmylmsLegacyRoutes = ' . wp_json_encode( $data ) . ';', 'before' );
	}

	/**
	 * The new-app route for a legacy route, ignoring whether the app is enabled.
	 *
	 * @param string $legacy Legacy hash route such as `/order-edit/12` or `#/accounthub?tab=classes`.
	 * @return string|null New route such as `/orders/12`, or null when the screen is not replaced.
	 */
	public static function resolve( $legacy ) {
		$legacy = ltrim( (string) $legacy, '#' );
		$cut    = strpos( $legacy, '?' );
		$path   = false === $cut ? $legacy : substr( $legacy, 0, $cut );
		$search = false === $cut ? '' : substr( $legacy, $cut + 1 );
		$parts  = self::segments( $path );
		$pairs  = self::query_pairs( $search );
		foreach ( self::routes() as $rule ) {
			$params = self::match_path( $rule['from'], $parts );
			if ( null === $params ) {
				continue;
			}
			$consumed = array();
			foreach ( (array) ( $rule['query'] ?? array() ) as $name => $allowed ) {
				$value = self::query_value( $pairs, $name );
				if ( ! in_array( $value, (array) $allowed, true ) ) {
					continue 2;
				}
				$params[ $name ] = $value;
				$consumed[]      = $name;
			}
			foreach ( (array) ( $rule['skip'] ?? array() ) as $name ) {
				if ( null !== self::query_value( $pairs, $name, null ) ) {
					continue 2;
				}
			}
			return self::build( $rule['to'], $params, $pairs, $consumed );
		}
		return null;
	}

	/**
	 * Where a legacy route opens: the new app when mapped and enabled, otherwise the legacy page.
	 *
	 * @param string $legacy Legacy hash route.
	 * @return array{page: string, route: string}
	 */
	public static function target( $legacy ) {
		$route  = '/' . ltrim( ltrim( (string) $legacy, '#' ), '/' );
		$mapped = self::enabled() ? self::resolve( $route ) : null;
		return null === $mapped
			? array(
				'page'  => self::legacy_page(),
				'route' => $route,
			)
			: array(
				'page'  => AppPage::SLUG,
				'route' => $mapped,
			);
	}

	/**
	 * Absolute admin URL for a legacy route: the new app when mapped, the legacy screen otherwise.
	 *
	 * @param string $legacy Legacy hash route such as `/orders` or `/quiz-edit/5`.
	 * @return string
	 */
	public static function url( $legacy ) {
		$target = self::target( $legacy );
		return admin_url( 'admin.php?page=' . $target['page'] . '#' . $target['route'] );
	}

	/**
	 * Absolute URL of a route in the new app.
	 *
	 * @param string $route New-app route such as `/orders/12`.
	 * @return string
	 */
	public static function app_url( $route ) {
		return admin_url( 'admin.php?page=' . AppPage::SLUG . '#/' . ltrim( (string) $route, '/' ) );
	}

	/**
	 * Grading screen for a learner's assignment submission. The legacy route names the student; the
	 * new one names the submission, so the learner's latest submission is looked up.
	 *
	 * @param int $assignment_id Assignment post ID.
	 * @param int $student_id    Learner user ID.
	 * @return string
	 */
	public static function assignment_grading_url( $assignment_id, $student_id ) {
		$assignment_id = absint( $assignment_id );
		$student_id    = absint( $student_id );
		$legacy        = '/assignment-report/' . $assignment_id . '/grade-assignment/' . $student_id;
		if ( AppPage::SLUG !== self::target( $legacy )['page'] ) {
			return self::url( $legacy );
		}
		global $wpdb;
		$submission_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One indexed lookup per notification; the row was written moments ago.
			$wpdb->prepare(
				'SELECT id FROM %i WHERE assignment_id = %d AND user_id = %d ORDER BY id DESC LIMIT 1',
				$wpdb->prefix . 'ohmylms_assignment_attempts',
				$assignment_id,
				$student_id
			)
		);
		return $submission_id > 0
			? self::app_url( '/assignments/' . $assignment_id . '/submissions/' . $submission_id )
			: self::url( $legacy );
	}

	/**
	 * Whether an administrator asked to stay on the legacy screen (`&ohmylms_legacy=1`).
	 *
	 * @return bool
	 */
	public static function opted_out() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display preference.
		$value = isset( $_GET[ self::OPT_OUT ] ) ? sanitize_key( wp_unslash( $_GET[ self::OPT_OUT ] ) ) : '';
		return '1' === $value && current_user_can( 'manage_options' );
	}

	/**
	 * Load the browser redirect in the head of the legacy screen, before the legacy app.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public static function enqueue_redirect( $hook ) {
		if ( 'toplevel_page_' . self::legacy_page() !== $hook || ! self::enabled() || self::opted_out() ) {
			return;
		}
		$file = OHMYLMS_DIR . '/assets/js/legacy-redirects.js';
		wp_enqueue_script( self::HANDLE, OHMYLMS_URL . '/assets/js/legacy-redirects.js', array(), (string) filemtime( $file ), false );
		wp_add_inline_script( self::HANDLE, 'window.ohmylmsLegacyRoutes = ' . wp_json_encode( self::script_data() ) . ';', 'before' );
	}

	/**
	 * Data for assets/js/legacy-redirects.js.
	 *
	 * @return array{appPage: string, optOut: string, routes: array[]}
	 */
	public static function script_data() {
		return array(
			'appPage' => AppPage::SLUG,
			'optOut'  => self::OPT_OUT,
			'routes'  => self::routes(),
		);
	}

	/**
	 * Slug of the legacy admin page.
	 *
	 * @return string
	 */
	private static function legacy_page() {
		return defined( 'OHMYLMS_SLUG' ) ? OHMYLMS_SLUG : 'ohmylms';
	}

	/**
	 * Non-empty path segments.
	 *
	 * @param string $path Path such as `/orders/2/`.
	 * @return string[]
	 */
	private static function segments( $path ) {
		return array_values(
			array_filter(
				explode( '/', (string) $path ),
				static function ( $part ) {
					return '' !== $part;
				}
			)
		);
	}

	/**
	 * Match path segments against a pattern.
	 *
	 * @param string   $pattern Rule pattern such as `/order-edit/:id`.
	 * @param string[] $parts   Path segments.
	 * @return array<string, string>|null Decoded parameters, or null when the path does not match.
	 */
	private static function match_path( $pattern, array $parts ) {
		$params   = array();
		$segments = self::segments( $pattern );
		foreach ( $segments as $index => $segment ) {
			if ( '*' === $segment ) {
				return $params;
			}
			$part = $parts[ $index ] ?? null;
			if ( ':' === $segment[0] ) {
				$optional = '?' === substr( $segment, -1 );
				if ( null === $part ) {
					if ( $optional ) {
						continue;
					}
					return null;
				}
				$params[ trim( $segment, ':?' ) ] = rawurldecode( $part );
			} elseif ( $part !== $segment ) {
				return null;
			}
		}
		return count( $parts ) > count( $segments ) ? null : $params;
	}

	/**
	 * Split a query string into raw pairs, keeping their original encoding.
	 *
	 * @param string $search Query string without `?`.
	 * @return array[] Each `array( decoded name, decoded value, raw pair )`.
	 */
	private static function query_pairs( $search ) {
		$pairs = array();
		foreach ( explode( '&', (string) $search ) as $raw ) {
			if ( '' === $raw ) {
				continue;
			}
			$split   = explode( '=', $raw, 2 );
			$pairs[] = array( urldecode( $split[0] ), urldecode( $split[1] ?? '' ), $raw );
		}
		return $pairs;
	}

	/**
	 * First value of a query parameter.
	 *
	 * @param array[]     $pairs    From query_pairs().
	 * @param string      $name     Parameter name.
	 * @param string|null $fallback Value when absent.
	 * @return string|null
	 */
	private static function query_value( array $pairs, $name, $fallback = '' ) {
		foreach ( $pairs as $pair ) {
			if ( $pair[0] === $name ) {
				return $pair[1];
			}
		}
		return $fallback;
	}

	/**
	 * Fill a target template and append the legacy query parameters it does not set itself.
	 *
	 * @param string   $template Rule target such as `/emails?email=:id`.
	 * @param array    $params   Matched parameters.
	 * @param array[]  $pairs    Legacy query pairs.
	 * @param string[] $consumed Parameters used by the rule's query condition.
	 * @return string
	 */
	private static function build( $template, array $params, array $pairs, array $consumed ) {
		$route = preg_replace_callback(
			'/:([A-Za-z_]+)/',
			static function ( $token ) use ( $params ) {
				return isset( $params[ $token[1] ] ) ? rawurlencode( $params[ $token[1] ] ) : $token[0];
			},
			$template
		);
		$cut   = strpos( $route, '?' );
		$own   = array_column( self::query_pairs( false === $cut ? '' : substr( $route, $cut + 1 ) ), 0 );
		$extra = array();
		foreach ( $pairs as $pair ) {
			if ( ! in_array( $pair[0], $consumed, true ) && ! in_array( $pair[0], $own, true ) ) {
				$extra[] = $pair[2];
			}
		}
		if ( ! $extra ) {
			return $route;
		}
		return $route . ( false === $cut ? '?' : '&' ) . implode( '&', $extra );
	}
}
