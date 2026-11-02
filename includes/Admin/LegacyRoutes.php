<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Map from the recovered admin's hash routes to the typed admin app.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The one list of retired legacy screens (`admin.php?page=ohmylms#/...`) and the app routes that replace
 * them; {@see AdminNavigation::url()} gives each route its address (`admin.php?page=ohmylms-commerce&tab=orders&id=12`).
 * PHP links use {@see LegacyRoutes::url()} and {@see LegacyRoutes::app_url()}, and
 * assets/js/legacy-redirects.js applies the same rules in the browser. See docs/LEGACY-ROUTES.md.
 *
 * A rule has:
 * - `from`: a path pattern. Segments are literals, `:name` (required), `:name?` (optional, only at
 *   the end) or a final `*` (any remaining segments).
 * - `to`: the new route. `:name` is replaced by the URL-encoded path or query parameter.
 * - `query` (optional): query parameter => allowed values (`''` means absent). The rule applies only
 *   when every listed parameter has an allowed value; the value is then available to `to` and is not
 *   copied to the new route.
 * - `skip` (optional): query parameters whose presence makes the rule not match.
 *
 * Rules are tried in order and the first match wins. Other query parameters are copied unchanged.
 *
 * `admin.php?page=ohmylms` is the Dashboard now; a legacy `#/route` it receives is mapped in the browser.
 * The retired app page `admin.php?page=ohmylms-app` redirects to the Dashboard on the server
 * ({@see LegacyRoutes::redirect_app_page()}), marked with {@see LegacyRoutes::FROM_APP}, and the browser
 * carries its `#/route` over. Both redirects stay forever. See docs/LEGACY-ROUTES.md.
 */
final class LegacyRoutes {

	/**
	 * Query parameter of addresses that carry a legacy hash route (older links; the hash on the Dashboard is
	 * read as legacy anyway).
	 */
	const FROM_LEGACY = 'ohmylms_from_legacy';

	/**
	 * Query parameter the server redirect of the retired app page adds: the hash it carries is an app route.
	 */
	const FROM_APP = 'ohmylms_from_app';

	/**
	 * Retired query parameter that used to keep the legacy screen; dropped by the redirect.
	 */
	const RETIRED_OPT_OUT = 'ohmylms_legacy';

	/**
	 * App route that unmapped legacy routes open (the Dashboard), with the notice naming them.
	 */
	const APP_HOME = '/';

	/**
	 * Id of the notice that names a legacy screen the new app does not have.
	 */
	const NOTICE_ID = 'ohmylms-legacy-unavailable';

	/**
	 * Script handle of the hash mapping that runs on the Dashboard.
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
		'migration-settings'       => 'migration',
	);

	/**
	 * Legacy gamification tab => new gamification tab.
	 */
	const GAMIFICATION_TABS = array(
		'reward-settings'      => 'rewards',
		'point-settings'       => 'points',
		'badge-settings'       => 'badges',
		'level-settings'       => 'levels',
		'leaderboard-settings' => 'leaderboard',
		'streak-settings'      => 'streaks',
		'xp-settings'          => 'xp',
	);

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
			array(
				'from'  => '/accounthub',
				'to'    => '/account-hub',
				'query' => array( 'tab' => array( '', 'home', 'schools' ) ),
			),
			array(
				'from'  => '/accounthub',
				'to'    => '/account-hub?tab=:tab',
				'query' => array( 'tab' => array( 'classes', 'teachers', 'parents', 'users' ) ),
			),
			array(
				'from'  => '/accounthub',
				'to'    => '/students',
				'query' => array( 'tab' => array( 'students' ) ),
			),
			array(
				'from' => '/quiz-report',
				'to'   => '/reports/quizzes',
			),
			array(
				'from' => '/quiz-report/:id/*',
				'to'   => '/quizzes/:id/attempts',
			),
			// Content Hub, quizzes, courses, lessons and the curriculum workspace.
			array(
				'from' => '/content-hub',
				'to'   => '/content-hub',
			),
			array(
				'from' => '/quiz-edit/:id',
				'to'   => '/quizzes/:id',
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
			// Retired Catalog addresses, which the legacy hub still opened as Courses.
			array(
				'from' => '/content-hub/catalog',
				'to'   => '/courses',
			),
			array(
				'from' => '/content-hub/catalog/:id',
				'to'   => '/courses/:id',
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
				'from' => '/content-hub/curriculum',
				'to'   => '/content-hub/curriculum',
			),
			// The page itself opens `?roadmap=:id` bookmarks in the syllabus editor.
			array(
				'from' => '/content-hub/curriculum/compare',
				'to'   => '/content-hub/curriculum/compare',
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
			array(
				'from'  => '/content-hub/exams',
				'to'    => '/exams',
				'query' => array( 'action' => array( '' ) ),
			),
			array(
				'from'  => '/content-hub/exams',
				'to'    => '/exams?action=:action',
				'query' => array( 'action' => array( 'new', 'import' ) ),
			),
			// The Communities screen is retired; its add-on switch lives on Integrations.
			array(
				'from' => '/communities',
				'to'   => '/integrations',
			),
			array(
				'from' => '/students/:id/report',
				'to'   => '/students/:id/report',
			),
			array(
				'from' => '/courses/:id/students',
				'to'   => '/reports/courses/:id/students',
			),
			array(
				'from' => '/sessions',
				'to'   => '/sessions',
			),
			// Legacy Design sub-tabs. Course details stored options nothing reads, so it opens the
			// archive tab; checkout gateways moved to the payment gateways tab.
			array(
				'from' => '/settings/design-settings/quiz-editor/*',
				'to'   => '/settings?tab=quiz-design',
			),
			array(
				'from' => '/settings/design-settings/colors-fonts/*',
				'to'   => '/settings?tab=design-system',
			),
			array(
				'from' => '/settings/design-settings/checkout-page/*',
				'to'   => '/settings?tab=payment-gateways',
			),
			array(
				'from' => '/settings/design-settings/*',
				'to'   => '/settings?tab=course-archive',
			),
		);
		// List dialogs the new screens open from the address; other values open the app home.
		$dialogs = array(
			array( '/content-hub/question-bank', '/content-hub/question-bank', 'action', array( 'new', 'import' ) ),
			array( '/extensions/question-bank', '/content-hub/question-bank', 'action', array( 'new', 'import' ) ),
			array( '/assessments/question-bank', '/content-hub/question-bank', 'action', array( 'new', 'import' ) ),
			array( '/quizzes', '/quizzes', 'action', array( 'new', 'import' ) ),
			array( '/content-hub/quizzes', '/quizzes', 'action', array( 'new', 'import' ) ),
			array( '/assessments', '/quizzes', 'action', array( 'new', 'import' ) ),
			array( '/content-hub/skills', '/content-hub/skills', 'add', array( '1' ) ),
			array( '/extensions/skills', '/content-hub/skills', 'add', array( '1' ) ),
		);
		foreach ( $dialogs as list( $from, $to, $param, $values ) ) {
			$routes[] = array(
				'from'  => $from,
				'to'    => $to,
				'query' => array( $param => array( '' ) ),
			);
			$routes[] = array(
				'from'  => $from,
				'to'    => $to . '?' . $param . '=:' . $param,
				'query' => array( $param => $values ),
			);
		}
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
	 * Forget the cached rules (after changing the `ohmylms_legacy_routes` filter).
	 */
	public static function reset() {
		self::$routes = null;
	}

	/**
	 * Legacy screens the new app does not have, listed in the notice shown after the redirect.
	 *
	 * @return array<string, string> Legacy route => screen name.
	 */
	public static function unavailable() {
		$screens = array();
		/**
		 * Filters the legacy screens listed as not available in the new app.
		 *
		 * @param array<string, string> $screens Legacy route => screen name.
		 */
		return (array) apply_filters( 'ohmylms_legacy_unavailable_screens', $screens );
	}

	/**
	 * Where a request for the retired app page (`admin.php?page=ohmylms-app`) goes: the Dashboard, marked
	 * with {@see LegacyRoutes::FROM_APP} (or keeping {@see LegacyRoutes::FROM_LEGACY} from older unmapped
	 * links). The browser keeps the `#/route` fragment across the redirect, and the Dashboard maps it
	 * (assets/js/legacy-redirects.js, then the app's router). Other query parameters are kept.
	 *
	 * @return string Absolute URL.
	 */
	public static function app_redirect_url() {
		$query = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only; the values are passed on unchanged.
		foreach ( wp_unslash( $_GET ) as $name => $value ) {
			$name = sanitize_key( $name );
			if ( is_string( $value ) && '' !== $name && ! in_array( $name, array( 'page', self::RETIRED_OPT_OUT, self::FROM_APP ), true ) ) {
				$query[ $name ] = rawurlencode( sanitize_text_field( $value ) );
			}
		}
		$query = array_merge( array( 'page' => OHMYLMS_SLUG ), $query );
		if ( ! isset( $query[ self::FROM_LEGACY ] ) ) {
			$query[ self::FROM_APP ] = '1';
		}
		return add_query_arg( $query, admin_url( 'admin.php' ) );
	}

	/**
	 * Redirect the retired app page to the Dashboard (on its `load-` hook).
	 */
	public static function redirect_app_page() {
		wp_safe_redirect( self::app_redirect_url(), 302, 'OhMyLMS' );
		exit;
	}

	/**
	 * Hidden notice for a legacy address no screen replaces. Only the browser knows the address (it is in
	 * the `#` fragment), so assets/js/legacy-redirects.js fills in the route and shows it. Printed on the
	 * Dashboard, where those addresses land.
	 */
	public static function render_unavailable_notice() {
		?>
		<div id="<?php echo esc_attr( self::NOTICE_ID ); ?>" class="notice notice-warning inline" role="status" hidden>
			<p>
				<?php echo esc_html__( 'This screen is not available in OhMyLMS:', 'ohmylms' ); ?>
				<code data-ohmylms-legacy-route></code>
			</p>
			<?php $unavailable = self::unavailable(); ?>
			<?php if ( $unavailable ) : ?>
				<p>
					<?php echo esc_html__( 'Not available:', 'ohmylms' ); ?>
					<?php echo esc_html( implode( ', ', array_values( $unavailable ) ) ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Drop the markers from WordPress' canonical admin address (`removable_query_args`). WordPress rewrites
	 * the address to the canonical one after the hash mapping has removed the marker, and would put it back.
	 *
	 * @param string[] $args Query parameters WordPress removes from the address.
	 * @return string[]
	 */
	public static function removable_query_args( $args ) {
		$args   = (array) $args;
		$args[] = self::FROM_LEGACY;
		$args[] = self::FROM_APP;
		return $args;
	}

	/**
	 * Load the hash mapping in the head of the Dashboard, before the app's router reads the hash. The
	 * server cannot see the hash, so it loads on every Dashboard visit and does nothing without one.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public static function enqueue_landing( $hook ) {
		if ( 'toplevel_page_' . OHMYLMS_SLUG !== $hook || ! current_user_can( AdminNavigation::CAPABILITY ) ) {
			return;
		}
		$file = OHMYLMS_DIR . '/assets/js/legacy-redirects.js';
		wp_enqueue_script( self::HANDLE, OHMYLMS_URL . '/assets/js/legacy-redirects.js', array(), (string) filemtime( $file ), false );
		wp_add_inline_script( self::HANDLE, 'window.ohmylmsLegacyRoutes = ' . wp_json_encode( self::script_data() ) . ';', 'before' );
	}

	/**
	 * The new-app route for a legacy route.
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
	 * Where a legacy route opens: the admin page, tab and app route of its new screen, and the address;
	 * an unmapped route opens the Dashboard.
	 *
	 * @param string $legacy Legacy hash route.
	 * @return array{page: string, tab: string, route: string, url: string}
	 */
	public static function target( $legacy ) {
		$mapped = self::resolve( self::normalize( $legacy ) );
		$route  = null === $mapped ? self::APP_HOME : $mapped;
		$pairs  = AdminNavigation::address( $route ) ?? AdminNavigation::address( self::APP_HOME );
		$query  = array_column( $pairs, 1, 0 );
		return array(
			'page'  => (string) $query['page'],
			'tab'   => (string) ( $query['tab'] ?? '' ),
			'route' => $route,
			'url'   => admin_url( 'admin.php?' . AdminNavigation::format_query( $pairs ) ),
		);
	}

	/**
	 * Absolute address for a legacy route. An unmapped route opens the Dashboard with the legacy route in
	 * the hash, so the Dashboard shows the notice that names it.
	 *
	 * @param string $legacy Legacy hash route such as `/orders` or `/quiz-edit/5`.
	 * @return string
	 */
	public static function url( $legacy ) {
		$route  = self::normalize( $legacy );
		$mapped = self::resolve( $route );
		if ( null !== $mapped ) {
			return self::app_url( $mapped );
		}
		return admin_url( 'admin.php?page=' . OHMYLMS_SLUG . '#' . $route );
	}

	/**
	 * Absolute address of an app route (`/orders/12` opens `admin.php?page=ohmylms-commerce&tab=orders&id=12`).
	 * A route no screen serves opens the Dashboard with the route in the hash, where the app shows its
	 * "not found" screen.
	 *
	 * @param string $route App route such as `/orders/12`.
	 * @return string
	 */
	public static function app_url( $route ) {
		$route = '/' . ltrim( (string) $route, '/' );
		$url   = AdminNavigation::url( $route );
		return null === $url ? admin_url( 'admin.php?page=' . OHMYLMS_SLUG . '&' . self::FROM_APP . '=1#' . $route ) : $url;
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
	 * Data for assets/js/legacy-redirects.js.
	 *
	 * @return array{routes: array[], landing: array{param: string, appParam: string, home: string, noticeId: string}}
	 */
	public static function script_data() {
		return array(
			'routes'  => self::routes(),
			'landing' => array(
				'param'    => self::FROM_LEGACY,
				'appParam' => self::FROM_APP,
				'home'     => self::APP_HOME,
				'noticeId' => self::NOTICE_ID,
			),
		);
	}

	/**
	 * A legacy route with one leading slash and no `#`.
	 *
	 * @param string $legacy Legacy hash route such as `#/orders` or `orders`.
	 * @return string
	 */
	private static function normalize( $legacy ) {
		return '/' . ltrim( ltrim( (string) $legacy, '#' ), '/' );
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
