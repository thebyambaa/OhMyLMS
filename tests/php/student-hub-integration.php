<?php
/**
 * Disposable WordPress integration checks for Student Home.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\Hub;
use OhMyLMS\Student\LegacyPages;
use OhMyLMS\Student\MembershipView;
use OhMyLMS\Student\TeacherWorkspace;

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );

$hub_credentials_path = getenv( 'OHMYLMS_TEST_CREDENTIALS' );
if ( ! $hub_credentials_path || ! is_file( $hub_credentials_path ) ) {
	throw new RuntimeException( 'Disposable test credentials required.' );
}
$hub_credentials = json_decode( file_get_contents( $hub_credentials_path ), true );
require $hub_credentials['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_source_test' !== DB_NAME ) {
	throw new RuntimeException( 'Disposable database required.' );
}

$hub_saved    = get_option( Hub::OPTION, null );
$hub_posts    = array();
$hub_users    = array();
$hub_checks   = 0;
$hub_order    = null;
$hub_enrolled = 0;
$hub_school   = 0;
$hub_class    = 0;
$hub_learning = 0;
$hub_retired  = get_option( LegacyPages::OPTION, null );

/**
 * Fail immediately when an integration invariant is broken.
 *
 * @param bool   $condition Verified condition.
 * @param string $message Failure description.
 * @throws RuntimeException When an integration check fails.
 */
function ohmylms_hub_check( $condition, $message ) {
	global $hub_checks;
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$hub_checks;
}

try {
	wp_set_current_user( get_user_by( 'login', $hub_credentials['username'] )->ID );
	delete_option( Hub::OPTION );
	Hub::install_page();
	$hub_config = Hub::settings();
	// Cover every section, including the legacy My work and Progress views kept for old links.
	$hub_config['tabs'] = array_keys( Hub::sections() );
	update_option( Hub::OPTION, Hub::sanitize( $hub_config ), false );
	$hub_config  = Hub::settings();
	$hub_posts[] = $hub_config['page_id'];
	// Saved version 3 navigation gains Achievements and History in place of My work and Progress.
	update_option(
		Hub::OPTION,
		array(
			'version' => 3,
			'page_id' => $hub_config['page_id'],
			'enabled' => true,
			'tabs'    => array( 'home', 'learn', 'library', 'work', 'progress', 'account' ),
		),
		false
	);
	Hub::upgrade();
	ohmylms_hub_check( array( 'home', 'learn', 'library', 'achievements', 'history', 'account' ) === Hub::settings()['tabs'], 'Version 3 navigation was not upgraded.' );
	ohmylms_hub_check( ! in_array( 'work', Hub::sanitize( array() )['tabs'], true ) && isset( Hub::sections()['work'] ), 'Legacy sections must be valid but off by default.' );
	update_option( Hub::OPTION, $hub_config, false );
	Hub::install_page();
	ohmylms_hub_check( $hub_config['page_id'] > 0 && Hub::settings()['page_id'] === $hub_config['page_id'], 'Page installation is not idempotent.' );
	ohmylms_hub_check( has_shortcode( get_post_field( 'post_content', $hub_config['page_id'] ), 'ohmylms_student_hub' ), 'Hub shortcode is absent.' );
	$hub_previous_query  = $GLOBALS['wp_query'];
	$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $hub_config['page_id'] ) );
	$hub_administrator   = get_current_user_id();
	ohmylms_hub_check( Hub::uses_workspace_landmark(), 'Canonical authenticated workspace did not choose its content landmark.' );
	$hub_landmark_html = Hub::shortcode();
	ohmylms_hub_check( 1 === substr_count( $hub_landmark_html, 'role="main"' ) && strpos( $hub_landmark_html, '<aside' ) < strpos( $hub_landmark_html, 'role="main"' ), 'Workspace landmark includes navigation or is duplicated.' );
	wp_set_current_user( 0 );
	ohmylms_hub_check( ! Hub::uses_workspace_landmark(), 'Sign-in page must use the outer page landmark.' );
	wp_set_current_user( $hub_administrator );
	$GLOBALS['wp_query'] = $hub_previous_query;
	ohmylms_hub_check( ! Hub::uses_workspace_landmark(), 'An embedded workspace must retain its theme landmark.' );
	// Legacy entry points must not mount the retired frontend app, even for a platform admin.
	ohmylms_hub_check( Hub::base_url() === \OhMyLMS\Schools\Views::portal_url(), 'New school links still use the retired portal endpoint.' );
	foreach ( array( 'student-registration', 'teacher-registration', 'parent-registration', 'sign-in', 'school-dashboard', 'teacher-dashboard', 'parent-dashboard', 'student-assignments' ) as $hub_legacy_view ) {
		$hub_legacy_html = \OhMyLMS\Schools\Views::render( $hub_legacy_view );
		ohmylms_hub_check( false === strpos( $hub_legacy_html, 'data-ohmylms-school-view' ) && false !== strpos( $hub_legacy_html, '<a href=' ), 'Legacy frontend view still mounts React: ' . $hub_legacy_view );
	}
	ohmylms_hub_check(
		str_contains(
			LegacyPages::admin_target(
				'school-dashboard',
				array(
					'school' => 123,
					'class'  => 456,
				)
			),
			'page=ohmylms-accounts&tab=schools&id=123'
		),
		'Platform school editor no longer resolves to the Account Hub school.'
	);
	wp_set_current_user( 0 );
	foreach ( array(
		'parent-dashboard'    => 'hub_role=parent',
		'teacher-dashboard'   => 'hub_role=teacher',
		'student-assignments' => 'hub_section=classes',
	) as $hub_legacy_view => $hub_expected ) {
		$hub_legacy_target = LegacyPages::portal_target(
			$hub_legacy_view,
			array(
				'school' => 123,
				'class'  => 456,
			)
		);
		ohmylms_hub_check( str_contains( $hub_legacy_target, $hub_expected ) && str_contains( $hub_legacy_target, 'hub_class=456' ), 'Signed-out legacy navigation lost its workspace or class.' );
	}
	ohmylms_hub_check( '' === LegacyPages::portal_target( 'student-assignments', array( 'ohmylms_school_file' => 1 ) ), 'Legacy redirect captured an authorized file download.' );
	ohmylms_hub_check( '' === LegacyPages::portal_target( 'teacher-dashboard', array( 'ohmylms_invite' => 1 ) ), 'Legacy navigation captured an invitation.' );
	wp_set_current_user( get_user_by( 'login', $hub_credentials['username'] )->ID );
	ohmylms_hub_check( shortcode_exists( 'ohmylms_dashboard' ) && shortcode_exists( 'ohmylms_profile' ) && shortcode_exists( 'ohmylms_my_courses' ), 'A legacy shortcode was removed.' );
	update_option(
		LegacyPages::OPTION,
		array(
			999999 => array(
				'url'      => home_url( '/retired-hub-fixture/' ),
				'endpoint' => 'profile',
				'option'   => 'ohmylms_student_profile_page_id',
			),
		)
	);
	$hub_retired_path = wp_parse_url( home_url( '/retired-hub-fixture/' ), PHP_URL_PATH );
	ohmylms_hub_check( str_contains( LegacyPages::target( $hub_retired_path ), 'hub_section=account' ), 'Retired route requires its old page to remain published.' );
	ohmylms_hub_check( str_contains( LegacyPages::target( '/?page_id=999999' ), 'hub_section=account' ), 'Retired plain permalink was lost.' );
	ohmylms_hub_check( '' === LegacyPages::target( rtrim( $hub_retired_path, '/' ) . '-other/' ), 'Retired route captured an unrelated page.' );
	$hub_retired_invoice = LegacyPages::target( $hub_retired_path . 'invoice-details/9/?action=bad&_wpnonce=private' );
	ohmylms_hub_check( str_contains( $hub_retired_invoice, 'hub_view=invoice' ) && str_contains( $hub_retired_invoice, 'id=9' ) && ! str_contains( $hub_retired_invoice, 'private' ) && ! str_contains( $hub_retired_invoice, 'action=' ), 'Retired invoice route lost context or forwarded actions/nonces.' );
	foreach ( array( 'ohmylms_dashboard', 'ohmylms_profile', 'ohmylms_my_profile', 'ohmylms_my_courses' ) as $hub_old_tag ) {
		ohmylms_hub_check( str_contains( do_shortcode( '[' . $hub_old_tag . ']' ), 'Open your learning workspace' ), 'A compatibility tag still renders the obsolete workspace.' );
	}
	$hub_invalid = Hub::sanitize(
		array(
			'tabs'    => array( 'learn', 'learn', '../admin', array() ),
			'cards'   => array(),
			'labels'  => array( 'learn' => '<b>Study</b>' ),
			'accent'  => array(),
			'layout'  => '<script>',
			'default' => 'home',
		)
	);
	ohmylms_hub_check( array( 'learn', 'account' ) === $hub_invalid['tabs'] && 'learn' === $hub_invalid['default'], 'Invalid/duplicate sections or hidden defaults accepted.' );
	ohmylms_hub_check( array() === $hub_invalid['cards'] && 'Study' === $hub_invalid['labels']['learn'] && true === $hub_invalid['accent_brand'] && \OhMyLMS\Design\Tokens::color( 'ohmylms_primary_color_scheme' ) === $hub_invalid['accent'], 'Presentation settings not sanitized.' );
	$hub_custom = Hub::sanitize( array( 'accent' => '#ff0066' ) );
	ohmylms_hub_check( false === $hub_custom['accent_brand'] && '#ff0066' === $hub_custom['accent'], 'A saved accent color should stay custom.' );
	$hub_follow = Hub::sanitize( array( 'accent' => '#ff0066', 'accent_brand' => '1' ) );
	ohmylms_hub_check( true === $hub_follow['accent_brand'] && '#ff0066' !== $hub_follow['accent'], 'Following the brand should ignore the saved color.' );
	$hub_style = \OhMyLMS\Schools\Settings::style( '#1a237e' );
	ohmylms_hub_check( str_contains( $hub_style, '--hub-accent-light:#1a237e;--hub-on-accent-light:#ffffff;' ), 'A custom accent should set its light value and the text color that reads on it.' );
	preg_match( '/--hub-accent-dark:(#[0-9a-f]{6});--hub-on-accent-dark:(#[0-9a-f]{6})/', $hub_style, $hub_dark );
	ohmylms_hub_check( ! empty( $hub_dark ) && \OhMyLMS\Design\DesignSystem::contrast( $hub_dark[1], \OhMyLMS\Design\DesignSystem::DARK['surface'] ) >= \OhMyLMS\Design\DesignSystem::DARK_MIN_CONTRAST && \OhMyLMS\Design\DesignSystem::contrast( $hub_dark[2], $hub_dark[1] ) >= 4.5, 'A dark navy accent should be lightened to read on the dark card, with readable text on it.' );
	foreach ( array( 'dashboard', 'profile', 'my-courses', 'profile-edit', 'transactions-history', 'invoice-details' ) as $hub_endpoint ) {
		ohmylms_hub_check( str_starts_with( ohmylms_get_account_endpoint_url( $hub_endpoint ), Hub::base_url() ), 'An account link leaves the hub.' );
	}
	ohmylms_hub_check( '' === Hub::endpoint_url( 'customer-logout' ), 'Logout was captured by hub navigation.' );
	ohmylms_hub_check( str_contains( ohmylms_get_endpoint_url( 'transactions-history', 2, Hub::base_url() ), 'current_page=2' ), 'Legacy pagination was lost.' );
	foreach ( array( 'Owner', 'Other' ) as $hub_name ) {
		$hub_users[] = wp_insert_user(
			array(
				'user_login'   => 'hub-' . strtolower( $hub_name ) . '-' . wp_generate_password( 8, false ),
				'user_pass'    => wp_generate_password(),
				'display_name' => $hub_name,
				'role'         => 'subscriber',
			)
		);
	}
	$hub_die_handler      = static function () {
		return static function ( $message, $title, $args ) {
			throw new RuntimeException( 'hub_rejected', (int) ( $args['response'] ?? 0 ) );
		};
	};
	$hub_redirect_handler = static function ( $location ) {
		ohmylms_hub_check( ! str_contains( $location, '&amp;' ), 'Settings redirect contains HTML-escaped query parameters.' );
		throw new RuntimeException( 'hub_saved', 302 );
	};
	add_filter( 'wp_die_handler', $hub_die_handler );
	add_filter( 'wp_redirect', $hub_redirect_handler );
	try {
		wp_set_current_user( $hub_users[0] );
		try {
			Hub::save();
		} catch ( RuntimeException $hub_error ) {
			ohmylms_hub_check( 403 === $hub_error->getCode(), 'Subscriber could save hub settings.' );
		}
		wp_set_current_user( get_user_by( 'login', $hub_credentials['username'] )->ID );
		try {
			Hub::save();
		} catch ( RuntimeException $hub_error ) {
			ohmylms_hub_check( 403 === $hub_error->getCode(), 'Missing settings nonce accepted.' );
		}
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'ohmylms_student_hub' );
		$_POST['hub']         = array(
			'title'   => 'Saved learner space',
			'tabs'    => array( 'account', 'learn' ),
			'cards'   => array(),
			'enabled' => 1,
		);
		try {
			Hub::save();
		} catch ( RuntimeException $hub_error ) {
			ohmylms_hub_check( 302 === $hub_error->getCode(), 'Administrator settings save failed.' );
		}
		ohmylms_hub_check( 'Saved learner space' === Hub::settings()['title'] && array( 'account', 'learn' ) === Hub::settings()['tabs'] && array() === Hub::settings()['cards'], 'Customized navigation/cards did not persist.' );
	} finally {
		remove_filter( 'wp_die_handler', $hub_die_handler );
		remove_filter( 'wp_redirect', $hub_redirect_handler );
		unset( $_REQUEST['_wpnonce'], $_POST['hub'] );
		update_option( Hub::OPTION, $hub_config, false );
	}
	wp_set_current_user( $hub_users[0] );
	global $wp_query, $wpdb;
	$wp_query->query( array( 'page_id' => $hub_config['page_id'] ) );
	foreach ( $hub_config['tabs'] as $hub_section ) {
		$wp_query->set( 'hub_section', $hub_section );
		$hub_views = array_keys( Hub::views( $hub_section ) );
		foreach ( $hub_views ? $hub_views : array( '' ) as $hub_view ) {
			$wp_query->set( 'hub_view', $hub_view );
			$hub_html = do_shortcode( '[ohmylms_student_hub]' );
			ohmylms_hub_check( str_contains( $hub_html, 'Student workspace' ) && ! str_contains( $hub_html, 'Customize Student Home' ), 'Section failed or leaked admin controls.' );
		}
	}
	$hub_course  = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => 'publish',
			'post_title'  => 'Hub enrolled test course',
		)
	);
	$hub_quiz    = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_QUIZ_CPT,
			'post_status' => 'publish',
			'post_title'  => 'Hub published checkpoint',
		)
	);
	$hub_chapter = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_CHAPTER_CPT,
			'post_status' => 'publish',
			'post_title'  => 'Hub test chapter',
		)
	);
	$hub_posts   = array_merge( $hub_posts, array( $hub_course, $hub_quiz, $hub_chapter ) );
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_user_enrollment',
		array(
			'user_id'   => $hub_users[0],
			'course_id' => $hub_course,
			'status'    => 'enrolled',
			'progress'  => 'in-progress',
		)
	);
	$hub_enrolled = $wpdb->insert_id;
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_chapter_relationship',
		array(
			'course_id'  => $hub_course,
			'chapter_id' => $hub_chapter,
		)
	);
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_content_relationship',
		array(
			'chapter_id'   => $hub_chapter,
			'content_id'   => $hub_quiz,
			'content_type' => 'quiz',
		)
	);
	$wp_query->set( 'hub_section', 'work' );
	$wp_query->set( 'hub_view', 'active' );
	ohmylms_hub_check( str_contains( Hub::shortcode(), 'Hub published checkpoint' ), 'Enrolled activity missing from My work.' );
	$wpdb->insert(
		\OhMyLMS\Schools\Schema::table( 'schools' ),
		array(
			'name'       => 'Hub fixture school',
			'slug'       => 'hub-' . wp_generate_uuid4(),
			'timezone'   => 'UTC',
			'created_by' => $hub_users[0],
			'created_at' => current_time( 'mysql', true ),
		)
	);
	$hub_school = $wpdb->insert_id;
	$wpdb->insert(
		\OhMyLMS\Schools\Schema::table( 'classes' ),
		array(
			'school_id'        => $hub_school,
			'academic_year_id' => 0,
			'name'             => 'Hub joined class',
		)
	);
	$hub_class = $wpdb->insert_id;
	$wpdb->insert(
		\OhMyLMS\Schools\Schema::table( 'school_memberships' ),
		array(
			'school_id' => $hub_school,
			'user_id'   => $hub_users[0],
			'role'      => 'student',
			'joined_at' => current_time( 'mysql', true ),
		)
	);
	$wpdb->insert(
		\OhMyLMS\Schools\Schema::table( 'school_memberships' ),
		array(
			'school_id' => $hub_school,
			'user_id'   => $hub_users[1],
			'role'      => 'teacher',
			'joined_at' => current_time( 'mysql', true ),
		)
	);
	foreach ( array( 'student', 'teacher' ) as $hub_index => $hub_role ) {
		$wpdb->insert(
			\OhMyLMS\Schools\Schema::table( 'class_memberships' ),
			array(
				'class_id'  => $hub_class,
				'user_id'   => $hub_users[ $hub_index ],
				'role'      => $hub_role,
				'joined_at' => current_time( 'mysql', true ),
			)
		);
	}
	$wpdb->insert(
		\OhMyLMS\Schools\Schema::table( 'guardian_links' ),
		array(
			'school_id'        => $hub_school,
			'guardian_user_id' => $hub_users[1],
			'student_user_id'  => $hub_users[0],
			'approved_by'      => $hub_users[1],
			'approved_at'      => current_time( 'mysql', true ),
		)
	);
	$wpdb->insert(
		\OhMyLMS\Schools\Schema::table( 'learning_assignments' ),
		array(
			'school_id'  => $hub_school,
			'class_id'   => $hub_class,
			'creator_id' => $hub_users[1],
			'course_id'  => $hub_course,
			'content_id' => $hub_quiz,
			'title'      => 'Hub teacher-assigned work',
			'created_at' => current_time( 'mysql', true ),
		)
	);
	$hub_learning = $wpdb->insert_id;
	$wpdb->insert(
		\OhMyLMS\Schools\Schema::table( 'assignment_recipients' ),
		array(
			'assignment_id'   => $hub_learning,
			'student_user_id' => $hub_users[0],
			'assigned_at'     => current_time( 'mysql', true ),
		)
	);
	ohmylms_hub_check( in_array( $hub_class, array_map( 'intval', array_column( Hub::learner_classes(), 'id' ) ), true ), 'Joined class missing.' );
	$hub_supporters = Hub::supporters( $hub_class );
	ohmylms_hub_check( array( 'Other' ) === $hub_supporters['teachers'] && array( 'Other' ) === $hub_supporters['guardians'], 'Approved class connections missing.' );
	$wp_query->set( 'hub_section', 'classes' );
	$wp_query->set( 'hub_view', 'assignments' );
	$wp_query->set( 'hub_class', $hub_class );
	ohmylms_hub_check( str_contains( Hub::shortcode(), 'Hub teacher-assigned work' ), 'Assigned class work missing from class view.' );
	$wpdb->update(
		\OhMyLMS\Schools\Schema::table( 'class_memberships' ),
		array( 'status' => 'inactive' ),
		array(
			'class_id' => $hub_class,
			'user_id'  => $hub_users[0],
		)
	);
	ohmylms_hub_check( array() === Hub::learner_classes() && ! str_contains( Hub::shortcode(), 'Hub teacher-assigned work' ), 'Revoked class membership remains visible.' );
	$wpdb->update(
		\OhMyLMS\Schools\Schema::table( 'class_memberships' ),
		array( 'status' => 'active' ),
		array(
			'class_id' => $hub_class,
			'user_id'  => $hub_users[0],
		)
	);
	wp_set_current_user( $hub_users[1] );
	ohmylms_hub_check( TeacherWorkspace::available(), 'An active teacher relationship cannot enter the teacher workspace.' );
	ohmylms_hub_check( in_array( $hub_class, array_map( 'intval', array_column( TeacherWorkspace::classes(), 'id' ) ), true ), 'Teacher cannot see their assigned class.' );
	$wp_query->set( 'hub_role', 'teacher' );
	$wp_query->set( 'hub_section', 'roster' );
	$wp_query->set( 'hub_class', $hub_class );
	ohmylms_hub_check( str_contains( Hub::shortcode(), 'Owner' ), 'Teacher roster does not show the active member.' );
	$wp_query->set( 'hub_section', 'account' );
	$wp_query->set( 'hub_view', 'notifications' );
	ohmylms_hub_check( str_contains( Hub::shortcode(), 'name="hub_role" value="teacher"' ), 'Teacher Settings form lost its workspace context.' );
	$hub_save_redirect = static function () {
		$destination = '';
		$capture     = static function ( $location ) use ( &$destination ) {
			$destination = $location;
			throw new RuntimeException( 'hub_settings_redirect_captured' );
		};

		$_POST = array(
			'card'     => 'notifications',
			'hub_role' => 'teacher',
			'_wpnonce' => wp_create_nonce( \OhMyLMS\Student\SettingsSave::ACTION . '_notifications' ),
		);
		add_filter( 'wp_redirect', $capture );
		try {
			\OhMyLMS\Student\SettingsSave::handle();
		} catch ( RuntimeException $error ) {
			if ( 'hub_settings_redirect_captured' !== $error->getMessage() ) {
				throw $error;
			}
		} finally {
			remove_filter( 'wp_redirect', $capture );
			$_POST = array();
		}
		return $destination;
	};
	ohmylms_hub_check( str_contains( $hub_save_redirect(), 'hub_role=teacher' ), 'Saving Settings dropped an eligible teacher into the learner workspace.' );
	$wp_query->set( 'hub_role', '' );
	$wp_query->set( 'hub_section', 'classes' );
	ohmylms_hub_check( ! str_contains( Hub::shortcode(), 'Hub teacher-assigned work' ), 'Learner hub exposed another student’s assigned work to a non-student class role.' );
	$wp_query->set( 'hub_section', 'work' );
	$wp_query->set( 'hub_view', 'active' );
	ohmylms_hub_check( ! str_contains( Hub::shortcode(), 'Hub published checkpoint' ), 'Another learner can see private enrolled work.' );
	$hub_order = new \OhMyLMS\Commerce\Data\Order();
	$hub_order->set_student_id( $hub_users[0] );
	$hub_order->save();
	$wp_query->set( 'id', $hub_order->get_id() );
	ob_start();
	Hub::account( 'invoice' );
	$hub_invoice = ob_get_clean();
	ohmylms_hub_check( str_contains( $hub_invoice, 'Choose an invoice' ), 'Another customer can read an invoice.' );
	ohmylms_hub_check( false === MembershipView::invoice( $hub_order->get_id() ), 'Membership invoice adapter exposed another customer invoice.' );
	ohmylms_hub_check( ! in_array( $hub_order->get_id(), wp_list_pluck( MembershipView::owned( 'ohmylms-order' )->posts, 'ID' ), true ), 'Purchases use authorship instead of customer ownership.' );
	wp_set_current_user( $hub_users[0] );
	ohmylms_hub_check( (bool) MembershipView::invoice( $hub_order->get_id() ), 'Owner cannot read their membership invoice.' );
	ohmylms_hub_check( ! TeacherWorkspace::available(), 'A student without a teacher relationship gained teacher access.' );
	ohmylms_hub_check( ! str_contains( $hub_save_redirect(), 'hub_role=teacher' ), 'A forged Settings context opened the teacher workspace for a student.' );
	$hub_config['commerce'] = false;
	$hub_config['tabs']     = array( 'learn', 'account' );
	$hub_config['default']  = 'learn';
	update_option( Hub::OPTION, $hub_config );
	ohmylms_hub_check( ! isset( Hub::views( 'account' )['orders'] ), 'Hidden commerce views remain available in the hub.' );
	ohmylms_hub_check( array() === Hub::views( 'membership' ) && ! in_array( 'membership', Hub::settings()['tabs'], true ), 'Disabled commerce remains in primary navigation.' );
	$wp_query->set( 'hub_section', 'home' );
	$wp_query->set( 'hub_view', 'invalid' );
	ohmylms_hub_check( str_contains( Hub::shortcode(), 'Pick up where you left off.' ), 'Hidden section did not fall back to configured default (My learning).' );
	wp_set_current_user( 0 );
	// The sign-in screen is now an inline form (name="log" and name="pwd" posting to wp-login.php) instead of a single link.
	$hub_guest = Hub::shortcode();
	ohmylms_hub_check( str_contains( $hub_guest, 'name="log"' ) && str_contains( $hub_guest, 'name="pwd"' ) && str_contains( $hub_guest, 'Your learning space' ) && ! str_contains( $hub_guest, 'Student workspace' ), 'Guest received student content.' );
	$hub_config['enabled'] = false;
	update_option( Hub::OPTION, $hub_config );
	ohmylms_hub_check( '' === Hub::base_url() && '' === Hub::endpoint_url( 'dashboard' ), 'Disabling the hub did not restore legacy routing.' );
	echo esc_html( $hub_checks . ' Student Home integration checks passed.' ) . "\n";
} finally {
	if ( null === $hub_retired ) {
		delete_option( LegacyPages::OPTION );
	} else {
		update_option( LegacyPages::OPTION, $hub_retired, false );
	}
	if ( $hub_learning ) {
		$wpdb->delete( \OhMyLMS\Schools\Schema::table( 'assignment_recipients' ), array( 'assignment_id' => $hub_learning ) );
	}
	if ( $hub_school ) {
		foreach ( array( 'learning_assignments', 'guardian_links', 'school_memberships', 'classes' ) as $hub_table ) {
			$wpdb->delete( \OhMyLMS\Schools\Schema::table( $hub_table ), array( 'school_id' => $hub_school ) );
		}
		$wpdb->delete( \OhMyLMS\Schools\Schema::table( 'class_memberships' ), array( 'class_id' => $hub_class ) );
		$wpdb->delete( \OhMyLMS\Schools\Schema::table( 'schools' ), array( 'id' => $hub_school ) );
	}
	if ( $hub_order && $hub_order->get_id() ) {
		wp_delete_post( $hub_order->get_id(), true );
	}
	if ( $hub_enrolled ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'id' => $hub_enrolled ) );
	}
	if ( isset( $hub_chapter ) ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'chapter_id' => $hub_chapter ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'chapter_id' => $hub_chapter ) );
	}
	foreach ( $hub_posts as $hub_post_id ) {
		wp_delete_post( $hub_post_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $hub_users as $hub_user_id ) {
		wp_delete_user( $hub_user_id );
	}
	if ( null === $hub_saved ) {
		delete_option( Hub::OPTION );
	} else {
		update_option( Hub::OPTION, $hub_saved, false );
	}
}
