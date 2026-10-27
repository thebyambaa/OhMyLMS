<?php
/**
 * Unified student workspace and its administrator-controlled presentation.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Data\Student;
use OhMyLMS\Practice\Dashboard;
use OhMyLMS\Practice\Frontend;
use OhMyLMS\Shortcodes\ShortCodeMyProfile;

defined( 'ABSPATH' ) || exit;

/** Keeps navigation and layout separate from existing learning/access policies. */
final class Hub {

	const OPTION = 'ohmylms_student_hub';

	/** Slug of the generated public hub page. */
	const SLUG = 'profile';

	/** Slug used before the hub moved to the shorter public address. */
	const LEGACY_SLUG = 'student-home';

	/** Records the one-time slug migration and the old path to redirect. */
	const SLUG_OPTION = 'ohmylms_hub_slug_migration';

	/** Admin editor, now embedded in the Account Hub home tab. */
	const EDITOR_PAGE = 'ohmylms-student-home-settings';

	/** Former admin address; it redirects to the Account Hub home tab. */
	const ADMIN_PAGE = 'ohmylms-student-home';

	/** Sections replaced by Achievements and History; still valid for old links and re-enabling. */
	const LEGACY_SECTIONS = array( 'work', 'progress' );

	/** Register the shortcode, settings page, and compatible legacy navigation. */
	public static function init() {
		LegacyPages::init();
		TeacherActions::init();
		ParentActions::init();
		FamilyActions::init();
		SchoolActivation::init();
		SchoolActions::init();
		add_shortcode( 'ohmylms_student_hub', array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_init', array( __CLASS__, 'install_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'upgrade' ) );
		add_action( 'admin_init', array( __CLASS__, 'migrate_slug' ) );
		add_action( 'load-admin_page_' . self::ADMIN_PAGE, array( __CLASS__, 'redirect_admin_page' ) );
		add_action( 'init', array( __CLASS__, 'upgrade' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 30 );
		add_action( 'admin_post_ohmylms_student_hub', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 25 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'course_page_assets' ), 25 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_course_page' ), 6 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'private_response' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'page_template' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'document_title_parts' ) );
		add_filter( 'ohmylms_get_endpoint_url', array( __CLASS__, 'endpoint_link' ), 10, 4 );
		foreach ( array( 'student_dashboard', 'student_profile', 'student_courses' ) as $page ) {
			add_filter( 'ohmylms_get_' . $page . '_page_permalink', array( __CLASS__, 'legacy_permalink' ) );
		}
	}

	/**
	 * Public, read-only navigation parameters.
	 *
	 * @param array $vars Registered query variables.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'hub_lesson';
		return array_merge( $vars, array( 'hub_section', 'hub_view', 'hub_class', 'hub_q', 'hub_course', 'hub_skill_course', 'hub_skill_unit', 'hub_period', 'hub_type', 'hub_role', 'hub_child', 'hub_scope', 'hub_school', 'ohmylms_child_setup', 'ohmylms_family_invite', 'hub_candidate_page', 'ohmylms_invite', 'id', 'current_page' ) );
	}

	/**
	 * Available main sections; identifiers remain stable when labels change.
	 *
	 * @return array
	 */
	public static function sections() {
		return array(
			'home'         => __( 'Home', 'ohmylms' ),
			'classes'      => __( 'My classes', 'ohmylms' ),
			'learn'        => __( 'My learning', 'ohmylms' ),
			'library'      => __( 'Browse subjects', 'ohmylms' ),
			'achievements' => __( 'Achievements', 'ohmylms' ),
			'history'      => __( 'History', 'ohmylms' ),
			'membership'   => __( 'Membership', 'ohmylms' ),
			'work'         => __( 'My work', 'ohmylms' ),
			'progress'     => __( 'Progress', 'ohmylms' ),
			'account'      => __( 'Account', 'ohmylms' ),
		);
	}

	/**
	 * Home card registry.
	 *
	 * @return array
	 */
	public static function cards() {
		return array(
			'continue'        => __( 'Continue learning', 'ohmylms' ),
			'recommendations' => __( 'Recommended practice', 'ohmylms' ),
			'weekly'          => __( 'This week', 'ohmylms' ),
			'courses'         => __( 'My courses', 'ohmylms' ),
		);
	}

	/**
	 * Sanitize persisted presentation without accepting arbitrary renderers or CSS.
	 *
	 * @param array $input Untrusted settings.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$result = array(
			'version'         => 4,
			'page_id'         => absint( $input['page_id'] ?? 0 ),
			'title'           => sanitize_text_field( is_string( $input['title'] ?? null ) ? $input['title'] : __( 'Student Home', 'ohmylms' ) ),
			'accent'          => is_string( $input['accent'] ?? null ) && sanitize_hex_color( $input['accent'] ) ? sanitize_hex_color( $input['accent'] ) : '#4361ee',
			'layout'          => in_array( $input['layout'] ?? '', array( 'sidebar', 'top' ), true ) ? $input['layout'] : 'sidebar',
			'commerce'        => ! empty( $input['commerce'] ),
			'personalize'     => ! empty( $input['personalize'] ),
			'enabled'         => ! empty( $input['enabled'] ),
			'library_types'   => array(),
			'library_bundles' => ! array_key_exists( 'library_bundles', $input ) || ! empty( $input['library_bundles'] ),
			'tabs'            => array(),
			'cards'           => array(),
			'labels'          => array(),
			'default'         => 'home',
		);
		foreach ( array(
			'tabs'  => self::sections(),
			'cards' => self::cards(),
		) as $key => $registry ) {
			$values = is_array( $input[ $key ] ?? null ) ? $input[ $key ] : array_values( array_diff( array_keys( $registry ), 'tabs' === $key ? self::LEGACY_SECTIONS : array() ) );
			foreach ( $values as $value ) {
				if ( is_string( $value ) && isset( $registry[ $value ] ) && ! in_array( $value, $result[ $key ], true ) ) {
					$result[ $key ][] = $value;
				}
			}
		}
		foreach ( is_array( $input['library_types'] ?? null ) ? array_slice( $input['library_types'], 0, 50 ) : array() as $type ) {
			$type = is_string( $type ) ? sanitize_key( $type ) : '';
			if ( '' !== $type && ! in_array( $type, $result['library_types'], true ) ) {
				$result['library_types'][] = $type;
			}
		}
		// Account must stay reachable, including when other sections are hidden.
		if ( ! in_array( 'account', $result['tabs'], true ) ) {
			$result['tabs'][] = 'account';
		}
		// Membership was always present when commerce was on; keep that behavior.
		if ( $result['commerce'] && ! in_array( 'membership', $result['tabs'], true ) ) {
			array_splice( $result['tabs'], array_search( 'account', $result['tabs'], true ), 0, array( 'membership' ) );
		} elseif ( ! $result['commerce'] ) {
			$result['tabs'] = array_values( array_diff( $result['tabs'], array( 'membership' ) ) );
		}
		foreach ( self::sections() as $key => $label ) {
			$labels                   = is_array( $input['labels'] ?? null ) ? $input['labels'] : array();
			$value                    = $labels[ $key ] ?? $label;
			$value                    = is_string( $value ) ? sanitize_text_field( $value ) : '';
			$result['labels'][ $key ] = '' !== trim( $value ) ? $value : $label;
		}
		$result['default'] = in_array( $input['default'] ?? '', $result['tabs'], true ) ? $input['default'] : $result['tabs'][0];
		return $result;
	}

	/**
	 * Current configuration with safe initial defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		return self::sanitize(
			get_option(
				self::OPTION,
				array(
					'enabled'     => true,
					'commerce'    => true,
					'personalize' => true,
				)
			)
		);
	}

	/** Provision one page; never rewrite or delete existing student pages. */
	public static function install_page() {
		if ( ! current_user_can( 'manage_options' ) || false !== get_option( self::OPTION, false ) ) {
			return;
		}
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Student Home', 'ohmylms' ),
				'post_name'    => self::SLUG,
				'post_content' => '[ohmylms_student_hub]',
			),
			true
		);
		if ( ! is_wp_error( $page_id ) ) {
			update_option(
				self::OPTION,
				self::sanitize(
					array(
						'page_id'     => $page_id,
						'enabled'     => true,
						'commerce'    => true,
						'personalize' => true,
					)
				),
				false
			);
		}
	}

	/** Add sections introduced after the initial workspace once, keeping saved choices. Safe for any visitor: it only rewrites our own option. */
	public static function upgrade() {
		$stored = get_option( self::OPTION );
		if ( ! is_array( $stored ) || 4 <= ( $stored['version'] ?? 0 ) ) {
			return;
		}
		$config = self::sanitize( $stored );
		if ( 2 > ( $stored['version'] ?? 0 ) ) {
			array_splice( $config['tabs'], min( 1, count( $config['tabs'] ) ), 0, array( 'classes' ) );
		}
		if ( ! in_array( 'library', $config['tabs'], true ) ) {
			$after = array_search( 'learn', $config['tabs'], true );
			array_splice( $config['tabs'], false === $after ? count( $config['tabs'] ) : $after + 1, 0, array( 'library' ) );
		}
		if ( 4 > ( $stored['version'] ?? 0 ) ) {
			// Achievements and History take the place of My work and Progress in saved navigation.
			$after = array_search( 'library', $config['tabs'], true );
			$add   = array_diff( array( 'achievements', 'history' ), $config['tabs'] );
			array_splice( $config['tabs'], false === $after ? count( $config['tabs'] ) : $after + 1, 0, $add );
			$config['tabs'] = array_values( array_diff( $config['tabs'], self::LEGACY_SECTIONS ) );
		}
		update_option( self::OPTION, self::sanitize( $config ), false );
	}

	/**
	 * Decide whether the hub page should move from the old slug to the new one.
	 *
	 * Pure so the rules are testable: rename only the configured hub page, only while it still
	 * has the old slug, and only when the new slug is free.
	 *
	 * @param array $facts Keys: configured_id, page_id (int), post_type, post_name, new_slug_taken (bool).
	 * @return array Keys: rename (bool), reason (string).
	 */
	public static function slug_migration_decision( array $facts ) {
		$configured = absint( $facts['configured_id'] ?? 0 );
		$page_id    = absint( $facts['page_id'] ?? 0 );
		if ( ! $configured || $configured !== $page_id || 'page' !== ( $facts['post_type'] ?? '' ) ) {
			return array(
				'rename' => false,
				'reason' => 'The configured hub page was not found.',
			);
		}
		if ( self::LEGACY_SLUG !== ( $facts['post_name'] ?? '' ) ) {
			return array(
				'rename' => false,
				'reason' => 'The hub page no longer uses the old slug.',
			);
		}
		if ( ! empty( $facts['new_slug_taken'] ) ) {
			return array(
				'rename' => false,
				'reason' => 'The new slug is already used by another post.',
			);
		}
		return array(
			'rename' => true,
			'reason' => 'Renamed.',
		);
	}

	/**
	 * Whether another non-trashed post already owns the new slug (trashed posts carry a __trashed suffix).
	 *
	 * @param int $except_id Post that may keep the slug.
	 * @return bool
	 */
	private static function new_slug_taken( $except_id ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name=%s AND ID<>%d AND post_status NOT IN ('trash','auto-draft','inherit') AND post_type IN ('page','post','attachment') LIMIT 1", self::SLUG, $except_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact, uncached slug check before a one-time rename.
		return (bool) $found;
	}

	/** Rename the configured hub page from the old slug to the new one once; the outcome is recorded either way. */
	public static function migrate_slug() {
		if ( get_option( self::SLUG_OPTION ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page_id = absint( self::settings()['page_id'] );
		$page    = $page_id ? get_post( $page_id ) : null;
		$record  = array(
			'version' => 1,
			'at'      => gmdate( 'Y-m-d H:i:s' ),
		);
		if ( $page && self::SLUG === $page->post_name ) {
			$record['result'] = 'not_needed';
			$record['reason'] = 'The hub page already uses the new slug.';
			update_option( self::SLUG_OPTION, $record, false );
			return;
		}
		$decision         = self::slug_migration_decision(
			array(
				'configured_id'  => $page_id,
				'page_id'        => $page ? (int) $page->ID : 0,
				'post_type'      => $page ? $page->post_type : '',
				'post_name'      => $page ? $page->post_name : '',
				'new_slug_taken' => $page ? self::new_slug_taken( $page_id ) : false,
			)
		);
		$record['reason'] = $decision['reason'];
		if ( ! $decision['rename'] ) {
			$record['result'] = 'skipped';
			update_option( self::SLUG_OPTION, $record, false );
			return;
		}
		$old_path = (string) wp_parse_url( get_permalink( $page ), PHP_URL_PATH );
		$updated  = wp_update_post(
			array(
				'ID'        => $page_id,
				'post_name' => self::SLUG,
			),
			true
		);
		$current  = get_post( $page_id );
		if ( is_wp_error( $updated ) || ! $current || self::SLUG !== $current->post_name ) {
			// WordPress chose a different unique slug; restore the original so the hub address is unchanged.
			wp_update_post(
				array(
					'ID'        => $page_id,
					'post_name' => self::LEGACY_SLUG,
				)
			);
			$record['result'] = 'skipped';
			$record['reason'] = 'WordPress would not give the page the new slug.';
			update_option( self::SLUG_OPTION, $record, false );
			return;
		}
		$record['result']   = 'renamed';
		$record['page_id']  = $page_id;
		$record['old_path'] = $old_path;
		update_option( self::SLUG_OPTION, $record, false );
	}

	/**
	 * Account Hub home tab, where the former Student Home admin screen now lives.
	 *
	 * @return string
	 */
	public static function admin_home_url() {
		return admin_url( 'admin.php?page=' . OHMYLMS_SLUG . '#/accounthub' );
	}

	/** Send the retired Student Home admin address to the Account Hub home tab. */
	public static function redirect_admin_page() {
		wp_safe_redirect( self::admin_home_url() );
		exit;
	}

	/**
	 * Published hub URL, or empty when disabled/unavailable.
	 *
	 * @return string
	 */
	public static function base_url() {
		$config = self::settings();
		$page   = get_post( $config['page_id'] );
		return $config['enabled'] && $page && 'page' === $page->post_type && 'publish' === $page->post_status && has_shortcode( $page->post_content, 'ohmylms_student_hub' ) ? get_permalink( $page ) : '';
	}

	/**
	 * Link for a school invitation; legacy URLs remain a compatibility fallback if the hub is unavailable.
	 *
	 * @param string $token Raw invitation token.
	 * @param string $role Invitation role.
	 * @return string
	 */
	public static function invitation_url( $token, $role ) {
		$base = self::base_url();
		if ( ! $base ) {
			return add_query_arg( 'ohmylms_invite', $token, \OhMyLMS\Schools\Views::portal_url() );
		}
		return add_query_arg( 'ohmylms_invite', $token, $base );
	}

	/**
	 * Build navigation from an allowlisted section and view.
	 *
	 * @param string $section Section identifier.
	 * @param string $view Optional secondary view.
	 * @return string
	 */
	public static function url( $section = '', $view = '' ) {
		$base = self::base_url();
		$base = $base ? $base : get_permalink();
		return add_query_arg(
			array_filter(
				array(
					'hub_section' => $section,
					'hub_view'    => $view,
				)
			),
			$base
		);
	}

	/**
	 * Course overview inside the hub; the ID is validated when it is rendered.
	 *
	 * @param int $course_id Course post ID.
	 * @return string
	 */
	public static function course_url( $course_id ) {
		return add_query_arg( 'hub_course', absint( $course_id ), self::url( 'library' ) );
	}

	/**
	 * Signed-in learners always land on the Khan-style course overview, where enrolment also happens.
	 *
	 * Skipped for roadmap courses (never shown to learners), password-protected courses that are still
	 * locked for the learner (the public page shows the password form and the enrol card), when Browse subjects is off and for
	 * ?preview links. Signed-out visitors get the same page at the course URL, as the hub needs a sign-in.
	 */
	public static function redirect_course_page() {
		if ( 'GET' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}
		if ( ! is_singular( OHMYLMS_COURSE_CPT ) || ! is_user_logged_in() || isset( $_GET['preview'] ) || ! in_array( 'library', self::settings()['tabs'], true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display switch.
			return;
		}
		$course_id = get_queried_object_id();
		if ( ! $course_id || in_array( $course_id, LearnerCatalog::roadmap_course_ids(), true ) ) {
			return;
		}
		// A learner who still has to enter the password stays on the public page, which shows the form and the enrol card.
		// Editors keep the hub, as the outline does for them.
		if ( post_password_required( $course_id ) && ! current_user_can( 'edit_post', $course_id ) ) {
			return;
		}
		$target = self::course_url( $course_id );
		$lesson = get_query_var( 'hub_lesson', '' );
		if ( is_string( $lesson ) && wp_is_uuid( $lesson ) ) {
			$target = add_query_arg( 'hub_lesson', $lesson, $target );
		}
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Map a legacy account endpoint without changing public endpoint names.
	 *
	 * @param string $endpoint Legacy endpoint.
	 * @param array  $query_params Additional query arguments.
	 * @return string Empty for endpoints handled by the original implementation.
	 */
	public static function endpoint_url( $endpoint, $query_params = array() ) {
		if ( ! self::base_url() ) {
			return '';
		}
		$map = array(
			'dashboard'            => array( 'learn', '' ),
			'my-courses'           => array( 'learn', 'courses' ),
			'profile'              => array( 'account', 'account' ),
			'profile-edit'         => array( 'account', 'account' ),
			'edit-profile'         => array( 'account', 'account' ),
			'settings'             => array( 'account', 'appearance' ),
			'notification'         => array( 'account', 'notifications' ),
			'transactions-history' => array( 'membership', 'orders' ),
			'invoice-details'      => array( 'membership', 'invoice' ),
			'billing-information'  => array( 'membership', 'overview' ),
			'membership'           => array( 'membership', 'overview' ),
		);
		return isset( $map[ $endpoint ] ) ? add_query_arg( $query_params, self::url( ...$map[ $endpoint ] ) ) : '';
	}

	/**
	 * Preserve existing page permalink filters for consumers outside account menus.
	 *
	 * @param string $url Original permalink.
	 * @return string
	 */
	public static function legacy_permalink( $url ) {
		$map     = array(
			'ohmylms_get_student_dashboard_page_permalink' => 'dashboard',
			'ohmylms_get_student_profile_page_permalink'   => 'profile',
			'ohmylms_get_student_courses_page_permalink'   => 'my-courses',
		);
		$hub_url = self::endpoint_url( $map[ current_filter() ] ?? '' );
		return $hub_url ? $hub_url : $url;
	}

	/**
	 * Preserve pagination and account form destinations in existing templates.
	 *
	 * @param string $url Existing endpoint URL.
	 * @param string $endpoint Endpoint slug.
	 * @param mixed  $value Endpoint value, such as the page number.
	 * @param string $permalink Base page permalink.
	 * @return string
	 */
	public static function endpoint_link( $url, $endpoint, $value, $permalink ) {
		$student_pages = array( self::base_url() );
		foreach ( array( 'student_profile', 'student_dashboard', 'student_courses', 'myaccount' ) as $key ) {
			$page_id = ohmylms_get_page_id( $key );
			if ( $page_id > 0 ) {
				$student_pages[] = get_permalink( $page_id );
			}
		}
		if ( ! in_array( remove_query_arg( array( 'hub_section', 'hub_view' ), $permalink ), $student_pages, true ) ) {
			return $url;
		}
		$canonical = array_search( $endpoint, \OhMyLMS\Commerce\ecommerce()->query->get_query_vars(), true );
		$endpoint  = is_string( $canonical ) ? $canonical : $endpoint;
		$mapped    = self::endpoint_url( $endpoint, is_numeric( $value ) ? array( 'current_page' => max( 1, absint( $value ) ) ) : array() );
		return $mapped ? $mapped : $url;
	}

	/** Redirect old bookmarks, keeping POST form handlers and logout intact. */
	public static function redirect_legacy() {
		if ( ! self::base_url() || ! is_page() || is_preview() || 'GET' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}
		global $wp;
		$legacy = array(
			'student_dashboard' => 'dashboard',
			'student_profile'   => 'profile',
			'student_courses'   => 'my-courses',
			'myaccount'         => 'profile',
		);
		foreach ( $legacy as $page => $endpoint ) {
			if ( get_queried_object_id() !== ohmylms_get_page_id( $page ) || get_queried_object_id() === self::settings()['page_id'] ) {
				continue;
			}
			foreach ( array_merge( array_keys( ohmylms_get_account_menu_items() ), array( 'profile-edit', 'edit-profile', 'invoice-details' ) ) as $candidate ) {
				if ( isset( $wp->query_vars[ $candidate ] ) ) {
					if ( 'customer-logout' === $candidate ) {
						return;
					}
					$endpoint = $candidate;
				}
			}
			$args = array();
			// Retain legacy profile views and pagination; never forward arbitrary actions/nonces.
			$query = array();
			parse_str( wp_parse_url( home_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) ), PHP_URL_QUERY ) ?? '', $query );
			if ( isset( $query['view'] ) && is_string( $query['view'] ) ) {
				$endpoint = $query['view'];
			}
			if ( 'true' === ( $query['edit'] ?? '' ) ) {
				$endpoint = 'profile-edit';
			}
			foreach ( array( 'id', 'current_page' ) as $key ) {
				if ( isset( $query[ $key ] ) && is_scalar( $query[ $key ] ) ) {
					$args[ $key ] = absint( $query[ $key ] );
				}
			}
			$target = self::endpoint_url( $endpoint, $args );
			if ( $target ) {
				wp_safe_redirect( $target );
				exit;
			}
		}
	}

	/**
	 * Detect hub shortcodes in the main page before its head is printed.
	 *
	 * @return bool
	 */
	public static function is_page() {
		$post = get_queried_object();
		return $post instanceof \WP_Post && has_shortcode( $post->post_content, 'ohmylms_student_hub' );
	}

	/** Mark personalized pages private before their document starts. */
	public static function private_response() {
		if ( self::is_page() || ( is_singular( OHMYLMS_COURSE_CPT ) && is_user_logged_in() ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
			header( 'Referrer-Policy: no-referrer' );
		}
	}

	/**
	 * The canonical workspace fills the page; embedded shortcodes retain their theme.
	 *
	 * @param string $template Theme template.
	 * @return string
	 */
	public static function page_template( $template ) {
		if ( self::is_page() && get_queried_object_id() === self::settings()['page_id'] ) {
			return OHMYLMS_DIR . '/templates/student-hub/page.php';
		}
		return $template;
	}

	/**
	 * Put the canonical workspace landmark around its content, excluding navigation.
	 *
	 * @return bool
	 */
	public static function uses_workspace_landmark() {
		return self::is_page() && get_queried_object_id() === self::settings()['page_id'] && 'workspace' === self::state()['kind'];
	}

	/**
	 * Respect the configured catalog even when it uses a customized page slug.
	 *
	 * @return string
	 */
	public static function catalog_url() {
		$page_id = absint( get_option( 'ohmylms_course_page_id' ) );
		if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
			return get_permalink( $page_id );
		}
		if ( in_array( 'library', self::settings()['tabs'], true ) ) {
			return self::url( 'library' );
		}
		$archive = get_post_type_archive_link( OHMYLMS_COURSE_CPT );
		return $archive ? $archive : home_url( '/' );
	}

	/** The course page shares the hub's Khan-style styles. */
	public static function course_page_assets() {
		if ( is_singular( OHMYLMS_COURSE_CPT ) ) {
			wp_enqueue_style( 'ohmylms-student-hub', plugins_url( 'assets/css/student-hub.css', OHMYLMS_FILE ), array( 'ohmylms-frontend' ), (string) filemtime( OHMYLMS_DIR . '/assets/css/student-hub.css' ) );
			wp_enqueue_style( 'ohmylms-learner-journey', plugins_url( 'assets/css/learner-journey.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/learner-journey.css' ) );
		}
	}

	/** Load authored assets early enough for the theme head. */
	public static function assets() {
		if ( ! self::is_page() ) {
			return;
		}
		wp_enqueue_style( 'ohmylms-frontend' );
		wp_enqueue_style( 'ohmylms-general' );
		wp_enqueue_script( 'ohmylms-frontend' );
		wp_enqueue_script( 'ohmylms-add-to-cart' );
		if ( 'catalog' === get_query_var( 'hub_view' ) ) {
			wp_enqueue_script( 'ohmylms-slick' );
			wp_enqueue_style( 'ohmylms-shortcode-course-list', plugins_url( 'assets/css/shortcode-course-list.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/shortcode-course-list.css' ) );
		}
		Dashboard::assets();
		if ( 'classes' === get_query_var( 'hub_section' ) ) {
			\OhMyLMS\Schools\Views::assets();
			wp_enqueue_style( 'ohmylms-schools' );
		}
		wp_enqueue_style( 'ohmylms-practice', plugins_url( 'assets/css/practice.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION );
		wp_enqueue_style( 'ohmylms-student-hub', plugins_url( 'assets/css/student-hub.css', OHMYLMS_FILE ), array( 'ohmylms-frontend' ), (string) filemtime( OHMYLMS_DIR . '/assets/css/student-hub.css' ) );
		wp_enqueue_style( 'ohmylms-learner-journey', plugins_url( 'assets/css/learner-journey.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/learner-journey.css' ) );
		wp_enqueue_script( 'ohmylms-student-hub', plugins_url( 'assets/js/student-hub.js', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/js/student-hub.js' ), true );
	}

	/**
	 * Render only the requested section; private data always uses the current user.
	 *
	 * @return string
	 */
	public static function shortcode() {
		$state = self::state();
		if ( 'activation' === $state['kind'] ) {
			ob_start();
			ohmylms_get_template( 'student-hub/school-activation.php', array( 'activation_token' => $state['token'] ) );
			return ob_get_clean();
		}
		if ( 'child_setup' === $state['kind'] ) {
			ob_start();
			ohmylms_get_template( 'student-hub/child-setup.php', array( 'setup_token' => $state['token'] ) );
			return ob_get_clean();
		}
		if ( 'signin' === $state['kind'] ) {
			ob_start();
			ohmylms_get_template(
				'student-hub/signin.php',
				array(
					'signin_role'   => $state['role'],
					'signin_return' => $state['return'],
				)
			);
			return ob_get_clean();
		}
		if ( 'family_invite' === $state['kind'] ) {
			ob_start();
			ohmylms_get_template( 'student-hub/family-invite.php', array( 'family_token' => $state['token'] ) );
			return ob_get_clean();
		}
		if ( 'unavailable' === $state['kind'] ) {
			return '<h1>' . esc_html__( 'This workspace is not available', 'ohmylms' ) . '</h1><p>' . esc_html( $state['message'] ) . '</p>';
		}
		$config       = $state['config'];
		$section      = $state['section'];
		$views        = $state['views'];
		$view         = $state['view'];
		$teacher_mode = $state['teacher_mode'];
		$parent_mode  = $state['parent_mode'];
		$school_mode  = $state['school_mode'];
		$student      = new Student( get_current_user_id() );
		ob_start();
		ohmylms_get_template( 'student-hub/workspace.php', compact( 'config', 'section', 'views', 'view', 'student', 'teacher_mode', 'parent_mode', 'school_mode' ) );
		return ob_get_clean();
	}

	/**
	 * Resolve what the current request shows, shared by the page body and its document title.
	 *
	 * @return array Request state; `kind` selects the screen.
	 */
	private static function state() {
		$activation_token = get_query_var( 'ohmylms_invite' );
		if ( is_string( $activation_token ) && preg_match( '/^[a-f0-9]{64}$/', $activation_token ) && 'activation' === \OhMyLMS\Schools\Service::invite_role( $activation_token ) ) {
			return array(
				'kind'  => 'activation',
				'token' => $activation_token,
			);
		}
		$setup_token = get_query_var( 'ohmylms_child_setup' );
		if ( ! is_user_logged_in() && is_string( $setup_token ) && preg_match( '/^[a-f0-9]{64}$/', $setup_token ) ) {
			return array(
				'kind'  => 'child_setup',
				'token' => $setup_token,
			);
		}
		if ( ! is_user_logged_in() ) {
			$role   = get_query_var( 'hub_role' );
			$role   = is_string( $role ) && in_array( $role, array( 'teacher', 'parent' ), true ) ? $role : '';
			$return = 'teacher' === $role ? TeacherWorkspace::url() : ( 'parent' === $role ? ParentWorkspace::url() : get_permalink() );
			$invite = get_query_var( 'ohmylms_invite' );
			if ( is_string( $invite ) && preg_match( '/^[a-f0-9]{64}$/', $invite ) ) {
				$return = add_query_arg( 'ohmylms_invite', $invite, self::url( 'classes', 'join' ) );
			}
			$family_invite = get_query_var( 'ohmylms_family_invite' );
			if ( is_string( $family_invite ) && preg_match( '/^[a-f0-9]{64}$/', $family_invite ) ) {
				$return = add_query_arg( 'ohmylms_family_invite', $family_invite, self::url() );
			}
			return array(
				'kind'   => 'signin',
				'role'   => $role,
				'return' => $return,
			);
		}
		$family_invite = get_query_var( 'ohmylms_family_invite' );
		if ( is_string( $family_invite ) && preg_match( '/^[a-f0-9]{64}$/', $family_invite ) ) {
			return array(
				'kind'  => 'family_invite',
				'token' => $family_invite,
			);
		}
		$config        = self::settings();
		$section       = get_query_var( 'hub_section' );
		$direct_invite = get_query_var( 'ohmylms_invite' );
		if ( ! $section && is_string( $direct_invite ) && preg_match( '/^[a-f0-9]{64}$/', $direct_invite ) && in_array( 'classes', $config['tabs'], true ) ) {
			$section = 'classes';
			set_query_var( 'hub_view', 'join' );
		}
		if ( ! $section && 'home' === $config['default'] ) {
			$landing = self::learner_classes() ? 'classes' : 'learn';
			$section = in_array( $landing, $config['tabs'], true ) ? $landing : $config['default'];
		}
		$requested_view = get_query_var( 'hub_view' );
		if ( $config['commerce'] && 'account' === $section && in_array( $requested_view, array( 'membership', 'orders', 'invoice', 'billing' ), true ) ) {
			$section = 'membership';
			if ( in_array( $requested_view, array( 'membership', 'billing' ), true ) ) {
				set_query_var( 'hub_view', 'overview' );
			}
		}
		$section  = is_string( $section ) && in_array( $section, $config['tabs'], true ) ? $section : $config['default'];
		$views    = self::views( $section );
		$view     = get_query_var( 'hub_view' );
		$view     = is_string( $view ) && isset( $views[ $view ] ) ? $view : ( array_key_first( $views ) ?? '' );
		$hub_role = get_query_var( 'hub_role' );
		if ( '' === $hub_role && ! get_query_var( 'hub_section' ) && ParentWorkspace::parent_only() ) {
			$hub_role = 'parent';
		}
		$teacher_mode = 'teacher' === $hub_role;
		$parent_mode  = 'parent' === $hub_role;
		$school_mode  = 'school' === $hub_role;
		if ( $school_mode ) {
			if ( ! SchoolWorkspace::available() ) {
				return array(
					'kind'    => 'unavailable',
					'message' => __( 'The school workspace is not available for this account.', 'ohmylms' ),
				);
			}
			$config['tabs']                       = array( 'school-overview', 'school-classes', 'school-reports', 'school-gradebook', 'school-people', 'school-invites', 'school-settings', 'account' );
			$config['title']                      = __( 'School workspace', 'ohmylms' );
			$config['labels']['school-overview']  = __( 'Overview', 'ohmylms' );
			$config['labels']['school-classes']   = __( 'Classes', 'ohmylms' );
			$config['labels']['school-reports']   = __( 'Reports', 'ohmylms' );
			$config['labels']['school-gradebook'] = __( 'Gradebook', 'ohmylms' );
			$config['labels']['school-people']    = __( 'People', 'ohmylms' );
			$config['labels']['school-invites']   = __( 'Invitations', 'ohmylms' );
			$config['labels']['school-settings']  = __( 'School settings', 'ohmylms' );
			$section                              = in_array( get_query_var( 'hub_section' ), $config['tabs'], true ) ? get_query_var( 'hub_section' ) : 'school-overview';
			$views                                = 'account' === $section ? self::views( $section ) : array();
			$view                                 = isset( $views[ $view ] ) ? $view : ( array_key_first( $views ) ?? '' );
		}
		if ( $parent_mode ) {
			if ( ! ParentWorkspace::available() ) {
				return array(
					'kind'    => 'unavailable',
					'message' => __( 'The parent workspace is not available for this account.', 'ohmylms' ),
				);
			}
			$config['tabs']             = array( 'family', 'account' );
			$config['title']            = __( 'Family workspace', 'ohmylms' );
			$config['labels']['family'] = __( 'My children', 'ohmylms' );
			$section                    = in_array( get_query_var( 'hub_section' ), $config['tabs'], true ) ? get_query_var( 'hub_section' ) : 'family';
			$views                      = 'family' === $section ? array() : self::views( $section );
			$view                       = isset( $views[ $view ] ) ? $view : ( array_key_first( $views ) ?? '' );
		}
		if ( $teacher_mode ) {
			if ( ! TeacherWorkspace::available() ) {
				return array(
					'kind'    => 'unavailable',
					'message' => __( 'The teacher workspace is not available for this account.', 'ohmylms' ),
				);
			}
			$config['tabs']                     = array( 'classes', 'work', 'roster', 'progress', 'class-settings', 'library', 'account' );
			$config['title']                    = __( 'Teacher workspace', 'ohmylms' );
			$config['labels']['classes']        = __( 'My classes', 'ohmylms' );
			$config['labels']['work']           = __( 'Assignments', 'ohmylms' );
			$config['labels']['roster']         = __( 'Roster', 'ohmylms' );
			$config['labels']['progress']       = __( 'Gradebook', 'ohmylms' );
			$config['labels']['class-settings'] = __( 'Class settings', 'ohmylms' );
			$section                            = in_array( get_query_var( 'hub_section' ), $config['tabs'], true ) ? get_query_var( 'hub_section' ) : 'classes';
			$views                              = in_array( $section, array( 'classes', 'work', 'roster', 'progress', 'class-settings' ), true ) ? array() : self::views( $section );
			$view                               = isset( $views[ $view ] ) ? $view : ( array_key_first( $views ) ?? '' );
		}
		return compact( 'config', 'section', 'views', 'view', 'teacher_mode', 'parent_mode', 'school_mode' ) + array( 'kind' => 'workspace' );
	}

	/**
	 * Give every hub screen its own document title (WCAG 2.4.2), most specific first.
	 *
	 * @param array $parts Title parts from WordPress.
	 * @return array
	 */
	public static function document_title_parts( $parts ) {
		if ( ! is_array( $parts ) || ! self::is_page() || get_queried_object_id() !== self::settings()['page_id'] ) {
			return $parts;
		}
		$state = self::state();
		$names = array();
		switch ( $state['kind'] ) {
			case 'activation':
				$names[] = __( 'Activate your account', 'ohmylms' );
				break;
			case 'child_setup':
				$names[] = __( 'Set up your account', 'ohmylms' );
				break;
			case 'signin':
				$names[] = __( 'Sign in', 'ohmylms' );
				break;
			case 'family_invite':
				$names[] = __( 'Family invitation', 'ohmylms' );
				break;
			case 'unavailable':
				$names[] = __( 'Not available', 'ohmylms' );
				break;
			default:
				$config  = $state['config'];
				$section = $state['section'];
				$views   = $state['views'];
				$view    = $state['view'];
				$course  = absint( get_query_var( 'hub_course', 0 ) );
				if ( 'family' === $section && absint( get_query_var( 'hub_child', 0 ) ) ) {
					$names[] = 'home_learning' === get_query_var( 'hub_scope', '' ) ? __( 'Home-learning activity', 'ohmylms' ) : __( 'Learner overview', 'ohmylms' );
				} elseif ( 'library' === $section && $course && OHMYLMS_COURSE_CPT === get_post_type( $course ) && 'publish' === get_post_status( $course ) ) {
					$names[] = get_the_title( $course );
				} elseif ( $views && isset( $views[ $view ] ) ) {
					$names[] = $views[ $view ];
				}
				$names[] = $config['labels'][ $section ] ?? '';
				$names[] = $config['title'];
		}
		$names = array_values( array_unique( array_filter( array_map( 'wp_strip_all_tags', $names ), 'strlen' ) ) );
		if ( $names ) {
			$separator      = apply_filters( 'document_title_separator', '-' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
			$parts['title'] = implode( ' ' . ( is_string( $separator ) ? $separator : '-' ) . ' ', $names );
		}
		return $parts;
	}

	/**
	 * Hidden nonce field with a document-unique id, for pages that carry several forms.
	 *
	 * The field name stays `_wpnonce`, so every handler keeps verifying the same request key.
	 *
	 * @param string $action Nonce action.
	 */
	public static function nonce_field( $action ) {
		static $count = 0;
		++$count;
		echo '<input type="hidden" id="' . esc_attr( 'oml-nonce-' . $count ) . '" name="_wpnonce" value="' . esc_attr( wp_create_nonce( $action ) ) . '">';
		wp_referer_field();
	}

	/**
	 * Secondary navigation only exposes supported/enabled functionality.
	 *
	 * @param string $section Main section.
	 * @return array
	 */
	public static function views( $section ) {
		if ( 'membership' === $section ) {
			return self::settings()['commerce'] ? array(
				'overview' => __( 'Overview', 'ohmylms' ),
				'plans'    => __( 'Plans', 'ohmylms' ),
				'orders'   => __( 'Purchases', 'ohmylms' ),
				'invoice'  => __( 'Invoice', 'ohmylms' ),
			) : array();
		}
		if ( 'classes' === $section ) {
			return array(
				'assignments' => __( 'Assignments', 'ohmylms' ),
				'connections' => __( 'Connections', 'ohmylms' ),
				'join'        => __( 'Join a class', 'ohmylms' ),
			);
		}
		if ( 'learn' === $section ) {
			// One page now: old My courses, Learning paths and Explore courses links open it.
			return array();
		}
		if ( 'work' === $section ) {
			return array(
				'active'    => __( 'Active', 'ohmylms' ),
				'completed' => __( 'Completed', 'ohmylms' ),
			);
		}
		if ( 'progress' === $section ) {
			return array(
				'courses'      => __( 'Course progress', 'ohmylms' ),
				'achievements' => __( 'Achievements', 'ohmylms' ),
			) + ( Frontend::enabled() ? array( 'skills' => __( 'Skill mastery', 'ohmylms' ) ) : array() );
		}
		if ( 'account' !== $section ) {
			return array();
		}
		$views = array(
			'account'       => __( 'Account', 'ohmylms' ),
			'appearance'    => __( 'Appearance', 'ohmylms' ),
			'notifications' => __( 'Notifications', 'ohmylms' ),
			'classes'       => __( 'Connections', 'ohmylms' ),
		);
		return $views;
	}

	/**
	 * Current learner's classes, excluding archived and inactive relationships.
	 *
	 * @return array
	 */
	public static function learner_classes() {
		if ( ! is_user_logged_in() || \OhMyLMS\Schools\Schema::VERSION !== get_option( 'ohmylms_school_schema' ) || ( defined( 'OHMYLMS_SCHOOLS_ENABLED' ) && ! OHMYLMS_SCHOOLS_ENABLED ) ) {
			return array();
		}
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT c.id,c.name,c.subject,c.grade,c.school_id,s.name AS school_name FROM %i c JOIN %i m ON m.class_id=c.id AND m.user_id=%d AND m.role='student' AND m.status='active' LEFT JOIN %i s ON s.id=c.school_id AND s.status='active' LEFT JOIN %i sm ON sm.school_id=s.id AND sm.user_id=m.user_id AND sm.role='student' AND sm.status='active' WHERE c.status='active' AND (c.school_id=0 OR (s.id IS NOT NULL AND sm.id IS NOT NULL)) ORDER BY s.name,c.name",
				\OhMyLMS\Schools\Schema::table( 'classes' ),
				\OhMyLMS\Schools\Schema::table( 'class_memberships' ),
				get_current_user_id(),
				\OhMyLMS\Schools\Schema::table( 'schools' ),
				\OhMyLMS\Schools\Schema::table( 'school_memberships' )
			),
			ARRAY_A
		);
	}

	/**
	 * Display names of already-authorized supporters, never their contact details.
	 *
	 * @param int $class_id Current learner's class.
	 * @return array
	 */
	public static function supporters( $class_id ) {
		$classes = array_column( self::learner_classes(), null, 'id' );
		if ( ! isset( $classes[ $class_id ] ) ) {
			return array(
				'teachers'  => array(),
				'guardians' => array(),
			);
		}
		global $wpdb;
		$teachers  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT u.display_name FROM %i m JOIN %i u ON u.ID=m.user_id LEFT JOIN %i sm ON sm.school_id=%d AND sm.user_id=m.user_id AND sm.role='teacher' AND sm.status='active' WHERE m.class_id=%d AND m.role='teacher' AND m.status='active' AND (%d=0 OR sm.id IS NOT NULL) ORDER BY u.display_name",
				\OhMyLMS\Schools\Schema::table( 'class_memberships' ),
				$wpdb->users,
				\OhMyLMS\Schools\Schema::table( 'school_memberships' ),
				$classes[ $class_id ]['school_id'],
				$class_id,
				$classes[ $class_id ]['school_id']
			)
		);
		$guardians = (int) $classes[ $class_id ]['school_id'] ? $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT u.display_name FROM %i g JOIN %i u ON u.ID=g.guardian_user_id WHERE g.school_id=%d AND g.student_user_id=%d AND g.status='active' ORDER BY u.display_name",
				\OhMyLMS\Schools\Schema::table( 'guardian_links' ),
				$wpdb->users,
				$classes[ $class_id ]['school_id'],
				get_current_user_id()
			)
		) : array();
		return array(
			'teachers'  => $teachers,
			'guardians' => $guardians,
		);
	}

	/**
	 * Reuse account controllers while enforcing invoice ownership explicitly.
	 *
	 * @param string $view Selected account view.
	 */
	public static function account( $view ) {
		if ( 'invoice' === $view ) {
			$order_id = absint( get_query_var( 'id', 0 ) );
			$order    = $order_id ? ohmylms_ecommerce_get_order( $order_id ) : false;
			if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() ) {
				echo '<p>' . esc_html__( 'Choose an invoice from your purchases.', 'ohmylms' ) . '</p>';
				return;
			}
			ohmylms_get_template(
				'profile/invoice-details.php',
				array(
					'order'        => $order,
					'current_page' => 1,
				)
			);
			return;
		}
		$methods = array(
			'orders'     => 'transactions_history',
			'billing'    => 'billing_information',
			'membership' => 'membership',
		);
		if ( isset( $methods[ $view ] ) ) {
			call_user_func( array( ShortCodeMyProfile::class, $methods[ $view ] ), max( 1, absint( get_query_var( 'current_page', 1 ) ) ) );
		}
	}

	/** Register the administration editor beside the other OhMyLMS menus. */
	public static function admin_menu() {
		// Hidden and empty: WordPress refuses unregistered pages before any redirect could run, so the old address is registered only to forward it.
		add_submenu_page( null, __( 'Student Home', 'ohmylms' ), __( 'Student Home', 'ohmylms' ), 'manage_options', self::ADMIN_PAGE, '__return_null' );
		// Hidden: the Account Hub home tab embeds this editor in a frame.
		add_submenu_page( null, __( 'Student Home', 'ohmylms' ), __( 'Student Home', 'ohmylms' ), 'manage_options', self::EDITOR_PAGE, array( __CLASS__, 'editor' ) );
	}

	/**
	 * Load the same reorder controller in the settings editor.
	 *
	 * @param string $hook Current admin screen hook.
	 */
	public static function admin_assets( $hook ) {
		if ( 'admin_page_' . self::EDITOR_PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'ohmylms-student-hub', plugins_url( 'assets/css/student-hub.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/student-hub.css' ) );
		wp_enqueue_style( 'ohmylms-learner-journey', plugins_url( 'assets/css/learner-journey.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/learner-journey.css' ) );
		wp_enqueue_script( 'ohmylms-student-hub', plugins_url( 'assets/js/student-hub.js', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/js/student-hub.js' ), true );
	}

	/** Render administrator settings. */
	public static function editor() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_GET['embed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presentation flag only.
			// Framed inside the Account Hub home tab: drop the surrounding admin chrome.
			echo '<style>#adminmenumain,#wpadminbar,#wpfooter{display:none!important}html.wp-toolbar{padding-top:0!important}#wpcontent,#wpfooter{margin-left:0!important}</style>';
		}
		ohmylms_get_template( 'student-hub/editor.php', array( 'config' => self::settings() ) );
	}

	/** Persist settings only after administrator capability and nonce checks. */
	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot customize Student Home.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ohmylms_student_hub' );
		$input            = isset( $_POST['hub'] ) && is_array( $_POST['hub'] ) ? map_deep( wp_unslash( $_POST['hub'] ), 'sanitize_text_field' ) : array();
		$input['page_id'] = self::settings()['page_id'];
		$input['tabs']    = isset( $input['tabs'] ) && is_array( $input['tabs'] ) ? $input['tabs'] : array();
		$input['cards']   = isset( $input['cards'] ) && is_array( $input['cards'] ) ? $input['cards'] : array();
		// Unchecked boxes are not submitted, so state both explicitly.
		$input['library_types']   = isset( $input['library_types'] ) && is_array( $input['library_types'] ) ? $input['library_types'] : array();
		$input['library_bundles'] = ! empty( $input['library_bundles'] );
		if ( empty( $input['enabled'] ) ) {
			LegacyPages::restore();
		}
		update_option( self::OPTION, self::sanitize( $input ), false );
		// Wording applies site-wide, so it is saved with the same capability and nonce check.
		$terms = isset( $_POST['terms'] ) && is_array( $_POST['terms'] ) ? map_deep( wp_unslash( $_POST['terms'] ), 'sanitize_text_field' ) : array();
		update_option( \OhMyLMS\Utility\Terminology::OPTION, \OhMyLMS\Utility\Terminology::sanitize( array( 'enabled' => ! empty( $terms['enabled'] ) ) + $terms ), false );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => self::EDITOR_PAGE,
					'embed'    => 1,
					'saved'    => 1,
					'_wpnonce' => wp_create_nonce( 'ohmylms_student_hub_saved' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
