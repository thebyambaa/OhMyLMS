<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * The six OhMyLMS admin pages, their tabs and sections, and the addresses of the app's screens.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Admin;

use OhMyLMS\Assessment\SkillAssessmentPage;
use OhMyLMS\Assets\AdminAppAssets;
use OhMyLMS\QuestionBank\ImportPage;

defined( 'ABSPATH' ) || exit;

/**
 * One source for the admin navigation (docs/ADMIN-REDESIGN.md, "Pages and tabs").
 *
 * - {@see AdminNavigation::groups()}: the pages (Dashboard, Content Hub, Account Hub, Commerce, Engagement,
 *   Settings) with their tabs (`&tab=`) and sections (`&section=`, `.subsubsub` links), each tab with a
 *   capability and an optional flag filter.
 * - {@see AdminNavigation::routes()}: every screen of the admin app with its place on those pages; the
 *   same table as the `group`/`tab`/`section`/`view`/`params` fields of assets/src/app/features (a contract
 *   test compares them through tests/fixtures/admin-navigation.json).
 * - {@see AdminNavigation::url()}: the address of an app route, as assets/src/app/routing/address.ts
 *   builds it in the browser.
 * - {@see AdminNavigation::render()}: prints a page: `.wrap`, the heading, tabs, sections and the app's
 *   mount point (or a section the server renders itself).
 */
final class AdminNavigation {

	/**
	 * Capability every page needs; tabs may require more.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Id of the tab row; extension pages add their tabs to it in the browser.
	 */
	const TABS_ID = 'ohmylms-admin-tabs';

	/**
	 * Id of the section list; the app marks the current section after moving inside the tab.
	 */
	const SECTIONS_ID = 'ohmylms-admin-sections';

	/**
	 * Id of the element after the heading where the app shows the screen's `.page-title-action` links.
	 * Keep in sync with assets/src/app/layout/PageTitleActions.tsx.
	 */
	const TITLE_ACTIONS_ID = 'ohmylms-admin-title-actions';

	/**
	 * Id of the element the app mounts into. Keep in sync with assets/src/app/bootstrap/mount.tsx.
	 */
	const MOUNT_ID = 'ohmylms-app-root';

	/**
	 * Help & Feedback, linked from the Dashboard.
	 */
	const HELP_URL = 'https://ohmylms.com/contact-us/';

	/**
	 * Tab of an extension admin page (`registerAdminPage`), which only the browser knows.
	 */
	const EXTENSION_TAB = '/^ext-[a-z][a-z0-9_-]*$/';

	/**
	 * Group of extension admin pages that declare none.
	 */
	const EXTENSION_GROUP = 'settings';

	/**
	 * Route query parameters stored under another address name (list pagination is `paged`).
	 */
	const RENAMED_QUERY = array( 'page' => 'paged' );

	/**
	 * Cached, normalized groups.
	 *
	 * @var array|null
	 */
	private static $groups = null;

	/**
	 * Forget cached groups (after changing filters, in tests).
	 */
	public static function reset() {
		self::$groups = null;
	}

	/**
	 * The pages with their tabs and sections, before capability and flag filtering.
	 *
	 * A tab has `label`, `capability`, `flag` (a filter that must return true, or `''`), `sections`
	 * (section id => `label` and optionally `render`, a callback that prints the section instead of the
	 * app; id `''` is the tab's own screen) and `default_section` (the section shown without `&section=`).
	 *
	 * @return array<string, array{page: string, label: string, tabs: array}>
	 */
	public static function groups() {
		if ( null !== self::$groups ) {
			return self::$groups;
		}
		$settings_sections = array(
			'general'             => __( 'General', 'ohmylms' ),
			'branding'            => __( 'Branding', 'ohmylms' ),
			'account-and-privacy' => __( 'Account and privacy', 'ohmylms' ),
			'permalink'           => __( 'Permalinks', 'ohmylms' ),
			'advanced'            => __( 'Advanced', 'ohmylms' ),
			'currency'            => __( 'Currency', 'ohmylms' ),
			'tax'                 => __( 'Tax', 'ohmylms' ),
			'payment-gateways'    => __( 'Payment gateways', 'ohmylms' ),
			'design-system'       => __( 'Design system', 'ohmylms' ),
			'course-archive'      => __( 'Course archive', 'ohmylms' ),
			'quiz-design'         => __( 'Quiz design', 'ohmylms' ),
			'hub-icons'           => __( 'Hub icons', 'ohmylms' ),
			'xapi'                => __( 'xAPI', 'ohmylms' ),
			'ai-tutor'            => __( 'AI tutor', 'ohmylms' ),
			'mcp'                 => __( 'MCP', 'ohmylms' ),
			'migration'           => __( 'Migration', 'ohmylms' ),
		);
		$groups            = array(
			'dashboard'  => array(
				'page'  => 'ohmylms',
				'label' => __( 'Dashboard', 'ohmylms' ),
				'tabs'  => array(),
			),
			'content'    => array(
				'page'  => 'ohmylms-content',
				'label' => __( 'Content Hub', 'ohmylms' ),
				'tabs'  => array(
					'courses'       => array(
						'label'      => __( 'Courses', 'ohmylms' ),
						'capability' => 'edit_posts',
						'sections'   => array(
							''         => __( 'All courses', 'ohmylms' ),
							'overview' => __( 'Overview', 'ohmylms' ),
						),
					),
					'lessons'       => array( 'label' => __( 'Lessons', 'ohmylms' ) ),
					'quizzes'       => array(
						'label'      => __( 'Quizzes', 'ohmylms' ),
						'capability' => 'edit_posts',
						'sections'   => array(
							''        => __( 'All quizzes', 'ohmylms' ),
							'reports' => __( 'Reports', 'ohmylms' ),
						),
					),
					'exams'         => array(
						'label'      => __( 'Exams', 'ohmylms' ),
						'capability' => 'edit_posts',
					),
					'assignments'   => array(
						'label'      => __( 'Assignments', 'ohmylms' ),
						'capability' => 'edit_posts',
					),
					'question-bank' => array(
						'label'      => __( 'Question bank', 'ohmylms' ),
						'capability' => 'edit_posts',
						'sections'   => array(
							''              => __( 'Questions', 'ohmylms' ),
							'import-review' => array(
								'label'  => __( 'Import review', 'ohmylms' ),
								'render' => array( ImportPage::class, 'render' ),
							),
						),
					),
					'skills'        => array(
						'label'      => __( 'Skills', 'ohmylms' ),
						'capability' => 'edit_posts',
						'sections'   => array(
							''            => __( 'Skill library', 'ohmylms' ),
							'assessments' => array(
								'label'  => __( 'Assessments', 'ohmylms' ),
								'render' => array( SkillAssessmentPage::class, 'render' ),
							),
						),
					),
					'curriculum'    => array(
						'label'    => __( 'Curriculum', 'ohmylms' ),
						'sections' => array(
							''           => __( 'Syllabuses', 'ohmylms' ),
							'bundles'    => __( 'Course bundles', 'ohmylms' ),
							'skill-map'  => __( 'Skill map', 'ohmylms' ),
							'subjects'   => __( 'Subject profiles', 'ohmylms' ),
							'taxonomies' => __( 'Categories and tags', 'ohmylms' ),
						),
					),
				),
			),
			'accounts'   => array(
				'page'  => 'ohmylms-accounts',
				'label' => __( 'Account Hub', 'ohmylms' ),
				'tabs'  => array(
					'students' => array( 'label' => __( 'Students', 'ohmylms' ) ),
					'schools'  => array( 'label' => __( 'Schools', 'ohmylms' ) ),
					'classes'  => array( 'label' => __( 'Classes', 'ohmylms' ) ),
					'teachers' => array( 'label' => __( 'Teachers', 'ohmylms' ) ),
					'parents'  => array( 'label' => __( 'Parents', 'ohmylms' ) ),
					'users'    => array( 'label' => __( 'All accounts', 'ohmylms' ) ),
				),
			),
			'commerce'   => array(
				'page'  => 'ohmylms-commerce',
				'label' => __( 'Commerce', 'ohmylms' ),
				'tabs'  => array(
					'earnings'      => array( 'label' => __( 'Earnings', 'ohmylms' ) ),
					'orders'        => array( 'label' => __( 'Orders', 'ohmylms' ) ),
					'memberships'   => array( 'label' => __( 'Memberships', 'ohmylms' ) ),
					'subscriptions' => array( 'label' => __( 'Subscriptions', 'ohmylms' ) ),
					'coupons'       => array( 'label' => __( 'Coupons', 'ohmylms' ) ),
				),
			),
			'engagement' => array(
				'page'  => 'ohmylms-engagement',
				'label' => __( 'Engagement', 'ohmylms' ),
				'tabs'  => array(
					'gamification' => array(
						'label'           => __( 'Gamification', 'ohmylms' ),
						'sections'        => array(
							'points'       => __( 'Points', 'ohmylms' ),
							'levels'       => __( 'Levels', 'ohmylms' ),
							'badges'       => __( 'Badges', 'ohmylms' ),
							'streaks'      => __( 'Streaks', 'ohmylms' ),
							'xp'           => __( 'XP and mastery', 'ohmylms' ),
							'leaderboard'  => __( 'Leaderboard', 'ohmylms' ),
							'rewards'      => __( 'Rewards', 'ohmylms' ),
							'certificates' => __( 'Certificates', 'ohmylms' ),
						),
						'default_section' => array( __CLASS__, 'gamification_default_section' ),
					),
					'sessions'     => array(
						'label'      => __( 'Sessions', 'ohmylms' ),
						'capability' => 'edit_posts',
						'flag'       => 'ohmylms_show_sessions_menu',
					),
					'emails'       => array( 'label' => __( 'Emails', 'ohmylms' ) ),
				),
			),
			'settings'   => array(
				'page'  => 'ohmylms-settings',
				'label' => __( 'Settings', 'ohmylms' ),
				'tabs'  => array(
					'settings'     => array(
						'label'           => __( 'Settings', 'ohmylms' ),
						'sections'        => $settings_sections,
						'default_section' => 'general',
					),
					'integrations' => array( 'label' => __( 'Integrations', 'ohmylms' ) ),
					'webhooks'     => array(
						'label' => __( 'Webhooks', 'ohmylms' ),
						'flag'  => 'ohmylms_should_enable_webhooks',
					),
					'setup'        => array( 'label' => __( 'Setup', 'ohmylms' ) ),
				),
			),
		);
		/**
		 * Filters the OhMyLMS admin pages, tabs and sections. Add a tab whose screen the admin app or a
		 * section `render` callback serves; the page slugs cannot change.
		 *
		 * @param array $groups Group id => page, label and tabs.
		 */
		$groups = (array) apply_filters( 'ohmylms_admin_navigation', $groups );
		foreach ( $groups as $id => $group ) {
			$tabs = array();
			foreach ( (array) ( $group['tabs'] ?? array() ) as $tab_id => $tab ) {
				$sections = array();
				foreach ( (array) ( $tab['sections'] ?? array() ) as $section_id => $section ) {
					$section                          = is_array( $section ) ? $section : array( 'label' => $section );
					$sections[ (string) $section_id ] = array(
						'label'      => (string) ( $section['label'] ?? $section_id ),
						'capability' => (string) ( $section['capability'] ?? '' ),
						'render'     => $section['render'] ?? null,
					);
				}
				$default                  = $tab['default_section'] ?? '';
				$tabs[ (string) $tab_id ] = array(
					'label'           => (string) ( $tab['label'] ?? $tab_id ),
					'capability'      => (string) ( $tab['capability'] ?? self::CAPABILITY ),
					'flag'            => (string) ( $tab['flag'] ?? '' ),
					'sections'        => $sections,
					'default_section' => is_array( $default ) || $default instanceof \Closure ? $default : (string) $default,
				);
			}
			$groups[ $id ] = array(
				'page'  => (string) $group['page'],
				'label' => (string) $group['label'],
				'tabs'  => $tabs,
			);
		}
		self::$groups = $groups;
		return self::$groups;
	}

	/**
	 * Section the Gamification tab opens: points with the Gamification add-on on, certificates (the part
	 * that works without it) otherwise.
	 *
	 * @return string
	 */
	public static function gamification_default_section() {
		return apply_filters( 'ohmylms_show_gamification_menu', false ) ? 'points' : 'certificates';
	}

	/**
	 * Section a tab shows when the address names none (`''`: the tab's own screen).
	 *
	 * @param array $tab Normalized tab.
	 * @return string
	 */
	private static function default_section( array $tab ) {
		$default = $tab['default_section'];
		return is_callable( $default ) && ! is_string( $default ) ? (string) call_user_func( $default ) : (string) $default;
	}

	/**
	 * Every screen of the admin app and its place: the same entries, in the same order, as
	 * assets/src/app/features/registry.ts. `params` maps a route parameter (path or query) to another
	 * address name; `tabs` lists further tabs a screen serves through such a mapping.
	 *
	 * @return array[] Each with `id`, `path`, `group` and optionally `tab`, `tabs`, `section`, `view`, `params`.
	 */
	public static function routes() {
		$content  = array( 'group' => 'content' );
		$commerce = array( 'group' => 'commerce' );
		$accounts = array( 'group' => 'accounts' );
		$engage   = array( 'group' => 'engagement' );
		$settings = array( 'group' => 'settings' );
		$rows     = array(
			array( 'dashboard', '/dashboard', array( 'group' => 'dashboard' ) ),
			array( 'earnings', '/earnings', $commerce + array( 'tab' => 'earnings' ) ),
			array(
				'course-report',
				'/reports/courses/:id',
				$content + array(
					'tab'  => 'courses',
					'view' => 'report',
				),
			),
			array(
				'course-roster',
				'/reports/courses/:id/students',
				$content + array(
					'tab'  => 'courses',
					'view' => 'students',
				),
			),
			array( 'setup', '/setup', $settings + array( 'tab' => 'setup' ) ),
			array(
				'content-hub-overview',
				'/content-hub',
				$content + array(
					'tab'     => 'courses',
					'section' => 'overview',
				),
			),
			array( 'content-hub', '/content-hub/skills', $content + array( 'tab' => 'skills' ) ),
			array( 'curriculum', '/content-hub/curriculum', $content + array( 'tab' => 'curriculum' ) ),
			array(
				'curriculum-skill-map',
				'/content-hub/curriculum/skill-map',
				$content + array(
					'tab'     => 'curriculum',
					'section' => 'skill-map',
				),
			),
			array(
				'curriculum-subjects',
				'/content-hub/curriculum/subjects',
				$content + array(
					'tab'     => 'curriculum',
					'section' => 'subjects',
				),
			),
			array(
				'curriculum-compare',
				'/content-hub/curriculum/compare',
				$content + array(
					'tab'    => 'curriculum',
					'view'   => 'compare',
					'params' => array( 'tab' => 'compare' ),
				),
			),
			array(
				'bundles',
				'/content-hub/bundles',
				$content + array(
					'tab'     => 'curriculum',
					'section' => 'bundles',
				),
			),
			array(
				'syllabus-editor',
				'/content-hub/curriculum/syllabus/:id',
				$content + array(
					'tab'  => 'curriculum',
					'view' => 'syllabus',
				),
			),
			array(
				'course-structures',
				'/content-hub/courses',
				$content + array(
					'tab'  => 'courses',
					'view' => 'structure',
				),
			),
			array(
				'course-structure-editor',
				'/content-hub/courses/:id',
				$content + array(
					'tab'  => 'courses',
					'view' => 'structure',
				),
			),
			array( 'courses', '/courses', $content + array( 'tab' => 'courses' ) ),
			array(
				'courses-editor',
				'/courses/:id/:tab?',
				$content + array(
					'tab'    => 'courses',
					'params' => array( 'tab' => 'view' ),
				),
			),
			array( 'quizzes', '/quizzes', $content + array( 'tab' => 'quizzes' ) ),
			array( 'quizzes-editor', '/quizzes/:id', $content + array( 'tab' => 'quizzes' ) ),
			array(
				'quizzes-attempts',
				'/quizzes/:id/attempts',
				$content + array(
					'tab'  => 'quizzes',
					'view' => 'attempts',
				),
			),
			array(
				'quiz-reports',
				'/reports/quizzes',
				$content + array(
					'tab'     => 'quizzes',
					'section' => 'reports',
				),
			),
			array( 'assignments', '/assignments', $content + array( 'tab' => 'assignments' ) ),
			array( 'assignments-editor', '/assignments/:id', $content + array( 'tab' => 'assignments' ) ),
			array(
				'assignments-submissions',
				'/assignments/:id/submissions',
				$content + array(
					'tab'  => 'assignments',
					'view' => 'submissions',
				),
			),
			array(
				'assignments-grading',
				'/assignments/:id/submissions/:submissionId',
				$content + array(
					'tab'  => 'assignments',
					'view' => 'submissions',
				),
			),
			array( 'exams', '/exams', $content + array( 'tab' => 'exams' ) ),
			array(
				'exams-import-review',
				'/exams/imports/:jobId',
				$content + array(
					'tab'  => 'exams',
					'view' => 'import',
				),
			),
			array( 'exams-editor', '/exams/:id', $content + array( 'tab' => 'exams' ) ),
			array(
				'course-settings-editor',
				'/content-hub/courses/:id/settings',
				$content + array(
					'tab'  => 'courses',
					'view' => 'structure-settings',
				),
			),
			array( 'lessons', '/content-hub/lessons', $content + array( 'tab' => 'lessons' ) ),
			array( 'lesson-editor', '/content-hub/lessons/:id', $content + array( 'tab' => 'lessons' ) ),
			array( 'questions', '/content-hub/question-bank', $content + array( 'tab' => 'question-bank' ) ),
			array( 'questions-editor', '/content-hub/question-bank/:id', $content + array( 'tab' => 'question-bank' ) ),
			array( 'orders', '/orders', $commerce + array( 'tab' => 'orders' ) ),
			array( 'order-detail', '/orders/:id', $commerce + array( 'tab' => 'orders' ) ),
			array( 'coupons', '/coupons', $commerce + array( 'tab' => 'coupons' ) ),
			array( 'subscriptions', '/subscriptions', $commerce + array( 'tab' => 'subscriptions' ) ),
			array( 'subscription-detail', '/subscriptions/:id', $commerce + array( 'tab' => 'subscriptions' ) ),
			array( 'memberships', '/memberships', $commerce + array( 'tab' => 'memberships' ) ),
			array( 'memberships-editor', '/memberships/:id', $commerce + array( 'tab' => 'memberships' ) ),
			array( 'students', '/students', $accounts + array( 'tab' => 'students' ) ),
			array(
				'student-report',
				'/students/:id/report',
				$accounts + array(
					'tab'  => 'students',
					'view' => 'report',
				),
			),
			array(
				'account-hub',
				'/account-hub',
				$accounts + array(
					'tab'    => 'schools',
					'tabs'   => array( 'classes', 'teachers', 'parents', 'users' ),
					'params' => array( 'tab' => 'tab' ),
				),
			),
			array( 'account-hub-school', '/account-hub/schools/:id', $accounts + array( 'tab' => 'schools' ) ),
			array(
				'account-hub-roster',
				'/account-hub/schools/:id/roster',
				$accounts + array(
					'tab'  => 'schools',
					'view' => 'roster',
				),
			),
			array( 'account-hub-class', '/account-hub/classes/:id', $accounts + array( 'tab' => 'classes' ) ),
			array(
				'certificates',
				'/certificates',
				$engage + array(
					'tab'     => 'gamification',
					'section' => 'certificates',
				),
			),
			array(
				'certificates-editor',
				'/certificates/:id',
				$engage + array(
					'tab'     => 'gamification',
					'section' => 'certificates',
				),
			),
			array( 'sessions', '/sessions', $engage + array( 'tab' => 'sessions' ) ),
			array( 'session-editor', '/sessions/:id', $engage + array( 'tab' => 'sessions' ) ),
			array(
				'gamification',
				'/gamification',
				$engage + array(
					'tab'    => 'gamification',
					'params' => array( 'tab' => 'section' ),
				),
			),
			array( 'emails', '/emails', $engage + array( 'tab' => 'emails' ) ),
			array( 'integrations', '/integrations', $settings + array( 'tab' => 'integrations' ) ),
			array(
				'settings',
				'/settings',
				$settings + array(
					'tab'    => 'settings',
					'params' => array( 'tab' => 'section' ),
				),
			),
			array(
				'taxonomies',
				'/taxonomies',
				$content + array(
					'tab'     => 'curriculum',
					'section' => 'taxonomies',
				),
			),
			array( 'webhooks', '/webhooks', $settings + array( 'tab' => 'webhooks' ) ),
			array( 'extension-pages', '/extensions/:id', $settings + array( 'tab' => 'ext-:id' ) ),
		);
		$routes   = array();
		foreach ( $rows as list( $id, $path, $place ) ) {
			$routes[] = array_merge(
				array(
					'id'   => $id,
					'path' => $path,
				),
				$place
			);
		}
		return $routes;
	}

	/**
	 * Page slug of a group.
	 *
	 * @param string $group Group id.
	 * @return string Slug, or `''` for an unknown group.
	 */
	public static function page_slug( $group ) {
		return self::groups()[ $group ]['page'] ?? '';
	}

	/**
	 * Group of a page slug.
	 *
	 * @param string $page Page slug.
	 * @return string|null Group id.
	 */
	public static function group_of_page( $page ) {
		foreach ( self::groups() as $id => $group ) {
			if ( $group['page'] === $page ) {
				return $id;
			}
		}
		return null;
	}

	/**
	 * Tabs of a group the current user may open, with their visible sections.
	 *
	 * @param string $group Group id.
	 * @return array<string, array> Tab id => tab.
	 */
	public static function visible_tabs( $group ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return array();
		}
		$visible = array();
		foreach ( self::groups()[ $group ]['tabs'] ?? array() as $id => $tab ) {
			if ( ! current_user_can( $tab['capability'] ) || ( '' !== $tab['flag'] && ! apply_filters( $tab['flag'], false ) ) ) {
				continue;
			}
			$tab['sections'] = array_filter(
				$tab['sections'],
				static function ( $section ) {
					return '' === $section['capability'] || current_user_can( $section['capability'] );
				}
			);
			$visible[ $id ]  = $tab;
		}
		return $visible;
	}

	/**
	 * Whether the current user may open a group's page.
	 *
	 * @param string $group Group id.
	 * @return bool
	 */
	public static function can_open( $group ) {
		if ( ! isset( self::groups()[ $group ] ) || ! current_user_can( self::page_capability( $group ) ) ) {
			return false;
		}
		return 'dashboard' === $group || (bool) self::visible_tabs( $group );
	}

	/**
	 * Capability a group's page requires. The Dashboard is also open to school administrators and teachers,
	 * whose endpoints are scoped server-side; every other page needs {@see AdminNavigation::CAPABILITY}.
	 *
	 * @param string $group Group id.
	 * @return string
	 */
	public static function page_capability( $group ) {
		return 'dashboard' === $group ? \OhMyLMS\Dashboard\Access::CAPABILITY : self::CAPABILITY;
	}

	/**
	 * Capability the admin page with a hook suffix requires.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 * @return string
	 */
	public static function hook_capability( $hook_suffix ) {
		return 'toplevel_page_' . OHMYLMS_SLUG === $hook_suffix ? self::page_capability( 'dashboard' ) : self::CAPABILITY;
	}

	/**
	 * Where a request lands: the group of its page, the requested tab when the user can see it (an
	 * extension tab `ext-<id>` is always accepted; the app checks it), otherwise the group's first tab, and
	 * the requested section of that tab, otherwise its default.
	 *
	 * @param array|null $query Query parameters; the current request by default.
	 * @return array{group: string, page: string, tab: string, section: string, render: callable|null}
	 */
	public static function current( $query = null ) {
		if ( null === $query ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
			$query = wp_unslash( $_GET );
		}
		$read    = static function ( $name ) use ( $query ) {
			return isset( $query[ $name ] ) && is_string( $query[ $name ] ) ? sanitize_key( $query[ $name ] ) : '';
		};
		$group   = self::group_of_page( $read( 'page' ) ) ?? 'dashboard';
		$tabs    = self::visible_tabs( $group );
		$tab     = $read( 'tab' );
		$section = $read( 'section' );
		$render  = null;
		if ( ! $tabs ) {
			$tab     = '';
			$section = '';
		} elseif ( preg_match( self::EXTENSION_TAB, $tab ) ) {
			$section = '';
		} else {
			if ( ! isset( $tabs[ $tab ] ) ) {
				$tab     = (string) array_key_first( $tabs );
				$section = '';
			}
			$sections = $tabs[ $tab ]['sections'];
			if ( '' === $section || ! isset( $sections[ $section ] ) ) {
				$section = self::default_section( $tabs[ $tab ] );
			}
			if ( ! $sections ) {
				$section = '';
			}
			$render = $sections[ $section ]['render'] ?? null;
		}
		return array(
			'group'   => $group,
			'page'    => self::page_slug( $group ),
			'tab'     => $tab,
			'section' => $section,
			'render'  => is_callable( $render ) ? $render : null,
		);
	}

	/**
	 * Whether a hook suffix (as passed to admin_enqueue_scripts) is one of the six pages.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return bool
	 */
	public static function is_screen( $hook_suffix ) {
		if ( ! is_string( $hook_suffix ) || '' === $hook_suffix ) {
			return false;
		}
		foreach ( self::groups() as $group ) {
			if ( OHMYLMS_SLUG === $group['page'] ) {
				if ( 'toplevel_page_' . OHMYLMS_SLUG === $hook_suffix ) {
					return true;
				}
				continue;
			}
			$suffix = '_page_' . $group['page'];
			if ( substr( $hook_suffix, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the current page shows the admin app (not a section the server renders itself).
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return bool
	 */
	public static function shows_app( $hook_suffix ) {
		return self::is_screen( $hook_suffix ) && null === self::current()['render'];
	}

	/**
	 * Address of a tab or section.
	 *
	 * @param string $group   Group id.
	 * @param string $tab     Tab id; the group's page for none.
	 * @param string $section Section id.
	 * @return string Absolute URL.
	 */
	public static function tab_url( $group, $tab = '', $section = '' ) {
		$pairs = array( array( 'page', self::page_slug( $group ) ) );
		if ( '' !== $tab ) {
			$pairs[] = array( 'tab', $tab );
		}
		if ( '' !== $section ) {
			$pairs[] = array( 'section', $section );
		}
		return admin_url( 'admin.php?' . self::format_query( $pairs ) );
	}

	/**
	 * Absolute address of an app route, or null when no screen serves it.
	 *
	 * @param string $route Route such as `/orders/12` or `/settings?tab=tax`.
	 * @return string|null
	 */
	public static function url( $route ) {
		$pairs = self::address( $route );
		return null === $pairs ? null : admin_url( 'admin.php?' . self::format_query( $pairs ) );
	}

	/**
	 * Address of an app route as query pairs; mirrors `toAddress()` in assets/src/app/routing/address.ts.
	 *
	 * Order: `page`, `tab`, `section`, `id`, `view`, the other path parameters, then the route's own
	 * query (`page` as `paged`). `/` is the Dashboard.
	 *
	 * @param string $route Route.
	 * @return array[]|null Pairs `array( name, value )`, or null when no screen serves the route.
	 */
	public static function address( $route ) {
		$route = explode( '#', (string) $route, 2 )[0];
		$cut   = strpos( $route, '?' );
		$path  = false === $cut ? $route : substr( $route, 0, $cut );
		$query = self::parse_query( false === $cut ? '' : substr( $route, $cut + 1 ) );
		if ( ! self::segments( $path ) ) {
			return array( array( 'page', self::page_slug( 'dashboard' ) ) );
		}
		$best = null;
		foreach ( self::routes() as $entry ) {
			$found = self::match_path( $entry['path'], $path );
			if ( null !== $found && ( null === $best || $found['statics'] > $best['statics'] ) ) {
				$best = array(
					'entry'   => $entry,
					'params'  => $found['params'],
					'statics' => $found['statics'],
				);
			}
		}
		if ( null === $best ) {
			return null;
		}
		$entry    = $best['entry'];
		$params   = $best['params'];
		$used     = array();
		$consumed = array();
		$slots    = array(
			'tab'     => $entry['tab'] ?? '',
			'section' => $entry['section'] ?? '',
			'view'    => $entry['view'] ?? '',
		);
		if ( false !== strpos( $slots['tab'], ':' ) ) {
			$slots['tab'] = preg_replace_callback(
				'/:([A-Za-z_]+)/',
				static function ( $token ) use ( $params, &$used ) {
					$used[] = $token[1];
					return $params[ $token[1] ] ?? $token[0];
				},
				$slots['tab']
			);
		}
		$named = array();
		foreach ( (array) ( $entry['params'] ?? array() ) as $from => $to ) {
			if ( array_key_exists( $from, $params ) ) {
				$used[] = $from;
				$value  = $params[ $from ];
			} else {
				$consumed[] = $from;
				$value      = self::query_value( $query, $from );
			}
			if ( null === $value || '' === $value ) {
				continue;
			}
			if ( isset( $slots[ $to ] ) ) {
				$slots[ $to ] = $value;
			} else {
				$named[] = array( $to, $value );
			}
		}
		foreach ( self::path_params( $entry['path'] ) as $param ) {
			if ( ! in_array( $param['name'], $used, true ) && isset( $params[ $param['name'] ] ) ) {
				$named[] = array( $param['name'], $params[ $param['name'] ] );
			}
		}
		$group = $entry['group'];
		if ( '/extensions/:id' === $entry['path'] ) {
			/**
			 * Filters the admin page of an extension admin page, as its `registerAdminPage( id, { group } )`
			 * declares it in the browser, so PHP links open the right page directly.
			 *
			 * @param string $group Group id (content, accounts, commerce, engagement or settings).
			 * @param string $id    Admin page id.
			 */
			$group = (string) apply_filters( 'ohmylms_extension_admin_page_group', self::EXTENSION_GROUP, $params['id'] );
			if ( 'dashboard' === $group || ! isset( self::groups()[ $group ] ) ) {
				$group = self::EXTENSION_GROUP;
			}
		}
		$pairs = array( array( 'page', self::page_slug( $group ) ) );
		foreach ( array( 'tab', 'section' ) as $slot ) {
			if ( '' !== $slots[ $slot ] ) {
				$pairs[] = array( $slot, $slots[ $slot ] );
			}
		}
		$id_index = null;
		foreach ( $named as $index => $pair ) {
			if ( 'id' === $pair[0] ) {
				$id_index = $index;
				$pairs[]  = $pair;
				break;
			}
		}
		if ( '' !== $slots['view'] ) {
			$pairs[] = array( 'view', $slots['view'] );
		}
		foreach ( $named as $index => $pair ) {
			if ( $index !== $id_index ) {
				$pairs[] = $pair;
			}
		}
		foreach ( $query as $pair ) {
			if ( ! in_array( $pair[0], $consumed, true ) ) {
				$pairs[] = array( self::RENAMED_QUERY[ $pair[0] ] ?? $pair[0], $pair[1] );
			}
		}
		return $pairs;
	}

	/**
	 * Register the top-level menu and one submenu per group the user can open (on `admin_menu`).
	 *
	 * @param string $icon  Menu icon.
	 * @param string $badge Pending-orders badge markup for the top-level menu and Commerce, or `''`.
	 */
	public static function register_menu( $icon, $badge = '' ) {
		global $menu, $submenu;
		$render = array( __CLASS__, 'render' );
		add_menu_page( __( 'OhMyLMS', 'ohmylms' ), __( 'OhMyLMS', 'ohmylms' ), self::page_capability( 'dashboard' ), OHMYLMS_SLUG, $render, $icon, 6 );
		foreach ( self::groups() as $id => $group ) {
			if ( ! self::can_open( $id ) ) {
				continue;
			}
			add_submenu_page( OHMYLMS_SLUG, $group['label'], $group['label'], self::page_capability( $id ), $group['page'], $render );
		}
		if ( '' === $badge ) {
			return;
		}
		// Badges are added after registration so the page hook names stay free of the count.
		foreach ( (array) $menu as $index => $item ) {
			if ( OHMYLMS_SLUG === ( $item[2] ?? '' ) ) {
				$menu[ $index ][0] .= $badge; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
		foreach ( (array) ( $submenu[ OHMYLMS_SLUG ] ?? array() ) as $index => $item ) {
			if ( self::page_slug( 'commerce' ) === ( $item[2] ?? '' ) ) {
				$submenu[ OHMYLMS_SLUG ][ $index ][0] .= $badge; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}

	/**
	 * Print a page: heading (with the slot for the screen's `.page-title-action` links), tabs, sections,
	 * then the app's mount point or the section the server renders.
	 *
	 * A tab with sections and a list screen prints one `.subsubsub` per level, as WooCommerce's settings
	 * sections over a list table do: the sections here, then the list's own views under them.
	 */
	public static function render() {
		$current = self::current();
		if ( ! self::can_open( $current['group'] ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		$group = self::groups()[ $current['group'] ];
		$tabs  = self::visible_tabs( $current['group'] );
		?>
		<div class="wrap ohmylms-admin-page ohmylms-admin-page--<?php echo esc_attr( $current['group'] ); ?>">
			<h1 class="wp-heading-inline"><?php echo esc_html( $group['label'] ); ?></h1>
			<?php if ( 'dashboard' === $current['group'] ) : ?>
				<a class="page-title-action" href="<?php echo esc_url( self::HELP_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Help & Feedback', 'ohmylms' ); ?></a>
			<?php endif; ?>
			<?php if ( ! $current['render'] ) : ?>
				<span id="<?php echo esc_attr( self::TITLE_ACTIONS_ID ); ?>" class="ohmylms-title-actions"></span>
			<?php endif; ?>
			<hr class="wp-header-end">
			<?php if ( $tabs ) : ?>
				<?php self::render_tabs( $current, $tabs ); ?>
			<?php endif; ?>
			<?php if ( isset( $tabs[ $current['tab'] ] ) && count( $tabs[ $current['tab'] ]['sections'] ) > 1 ) : ?>
				<?php self::render_sections( $current, $tabs[ $current['tab'] ] ); ?>
			<?php endif; ?>
			<?php if ( 'dashboard' === $current['group'] ) : ?>
				<?php LegacyRoutes::render_unavailable_notice(); ?>
			<?php endif; ?>
			<?php if ( $current['render'] ) : ?>
				<?php call_user_func( $current['render'] ); ?>
			<?php elseif ( null === AdminAppAssets::locate() ) : ?>
				<div class="notice notice-error">
					<p><?php echo esc_html__( 'The OhMyLMS admin app has not been built yet. Run "npm run build:sdk" in the plugin directory.', 'ohmylms' ); ?></p>
				</div>
			<?php else : ?>
				<div id="<?php echo esc_attr( self::MOUNT_ID ); ?>" class="ohmylms-app" data-page="<?php echo esc_attr( $current['page'] ); ?>" data-tab="<?php echo esc_attr( $current['tab'] ); ?>" data-section="<?php echo esc_attr( $current['section'] ); ?>">
					<noscript>
						<p><?php echo esc_html__( 'OhMyLMS needs JavaScript to be enabled.', 'ohmylms' ); ?></p>
					</noscript>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Print the tab row.
	 *
	 * @param array $current Current place.
	 * @param array $tabs    Visible tabs.
	 */
	private static function render_tabs( array $current, array $tabs ) {
		$group = self::groups()[ $current['group'] ];
		?>
		<nav class="nav-tab-wrapper wp-clearfix" id="<?php echo esc_attr( self::TABS_ID ); ?>" aria-label="<?php echo esc_attr( $group['label'] ); ?>">
			<?php foreach ( $tabs as $id => $tab ) : ?>
				<?php $active = $id === $current['tab']; ?>
				<a data-tab="<?php echo esc_attr( $id ); ?>" href="<?php echo esc_url( self::tab_url( $current['group'], $id ) ); ?>" class="nav-tab<?php echo $active ? ' nav-tab-active' : ''; ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $tab['label'] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Print the section links of a tab.
	 *
	 * @param array $current Current place.
	 * @param array $tab     Current tab.
	 */
	private static function render_sections( array $current, array $tab ) {
		$last = array_key_last( $tab['sections'] );
		?>
		<ul class="subsubsub" id="<?php echo esc_attr( self::SECTIONS_ID ); ?>" data-default-section="<?php echo esc_attr( self::default_section( $tab ) ); ?>">
			<?php foreach ( $tab['sections'] as $id => $section ) : ?>
				<?php $active = (string) $id === $current['section']; ?>
				<li class="<?php echo esc_attr( '' === (string) $id ? 'all' : $id ); ?>"><a href="<?php echo esc_url( self::tab_url( $current['group'], $current['tab'], (string) $id ) ); ?>" data-section="<?php echo esc_attr( $id ); ?>"<?php echo $active ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html( $section['label'] ); ?></a><?php echo $id === $last ? '' : ' |'; ?></li>
			<?php endforeach; ?>
		</ul>
		<br class="clear">
		<?php
	}

	/**
	 * The navigation as data, for tests/fixtures/admin-navigation.json: groups with their tabs and
	 * sections (unfiltered; sections the server renders listed as `serverSections`; a default section
	 * computed at request time exported as `''`) and the route table.
	 *
	 * @return array{groups: array, routes: array[]}
	 */
	public static function export() {
		$groups = array();
		foreach ( self::groups() as $id => $group ) {
			$tabs = array();
			foreach ( $group['tabs'] as $tab_id => $tab ) {
				$tabs[] = array(
					'id'             => $tab_id,
					'capability'     => $tab['capability'],
					'flag'           => $tab['flag'],
					'sections'       => array_map( 'strval', array_keys( $tab['sections'] ) ),
					'serverSections' => array_map(
						'strval',
						array_keys(
							array_filter(
								$tab['sections'],
								static function ( $section ) {
									return null !== $section['render'];
								}
							)
						)
					),
					'defaultSection' => is_string( $tab['default_section'] ) ? $tab['default_section'] : '',
				);
			}
			$groups[] = array(
				'id'   => $id,
				'page' => $group['page'],
				'tabs' => $tabs,
			);
		}
		return array(
			'groups' => $groups,
			'routes' => self::routes(),
		);
	}

	/**
	 * Join pairs into a query string; values are encoded like JavaScript's encodeURIComponent() with
	 * `!'()*` escaped, as in address.ts.
	 *
	 * @param array[] $pairs Pairs.
	 * @return string
	 */
	public static function format_query( array $pairs ) {
		$parts = array();
		foreach ( $pairs as $pair ) {
			$parts[] = rawurlencode( (string) $pair[0] ) . '=' . rawurlencode( (string) $pair[1] );
		}
		return implode( '&', $parts );
	}

	/**
	 * Decoded query pairs, in order.
	 *
	 * @param string $search Query string without `?`.
	 * @return array[]
	 */
	private static function parse_query( $search ) {
		$pairs = array();
		foreach ( explode( '&', (string) $search ) as $raw ) {
			if ( '' === $raw ) {
				continue;
			}
			$split   = explode( '=', $raw, 2 );
			$pairs[] = array( urldecode( $split[0] ), urldecode( $split[1] ?? '' ) );
		}
		return $pairs;
	}

	/**
	 * First value of a query parameter.
	 *
	 * @param array[] $pairs Pairs.
	 * @param string  $name  Name.
	 * @return string|null
	 */
	private static function query_value( array $pairs, $name ) {
		foreach ( $pairs as $pair ) {
			if ( $pair[0] === $name ) {
				return $pair[1];
			}
		}
		return null;
	}

	/**
	 * Non-empty path segments.
	 *
	 * @param string $path Path.
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
	 * Parameters of a route pattern.
	 *
	 * @param string $pattern Pattern such as `/courses/:id/:tab?`.
	 * @return array[] Each with `name` and `optional`.
	 */
	private static function path_params( $pattern ) {
		$params = array();
		foreach ( self::segments( $pattern ) as $piece ) {
			if ( ':' === $piece[0] ) {
				$params[] = array(
					'name'     => trim( $piece, ':?' ),
					'optional' => '?' === substr( $piece, -1 ),
				);
			}
		}
		return $params;
	}

	/**
	 * Match a route path against a pattern.
	 *
	 * @param string $pattern Pattern.
	 * @param string $path    Path.
	 * @return array{params: array<string, string>, statics: int}|null
	 */
	private static function match_path( $pattern, $path ) {
		$pieces = self::segments( $pattern );
		$parts  = self::segments( $path );
		if ( count( $parts ) > count( $pieces ) ) {
			return null;
		}
		$params  = array();
		$statics = 0;
		foreach ( $pieces as $index => $piece ) {
			$part = $parts[ $index ] ?? null;
			if ( ':' === $piece[0] ) {
				if ( null === $part ) {
					if ( '?' === substr( $piece, -1 ) ) {
						continue;
					}
					return null;
				}
				$params[ trim( $piece, ':?' ) ] = rawurldecode( $part );
			} elseif ( $part === $piece ) {
				++$statics;
			} else {
				return null;
			}
		}
		return array(
			'params'  => $params,
			'statics' => $statics,
		);
	}
}
