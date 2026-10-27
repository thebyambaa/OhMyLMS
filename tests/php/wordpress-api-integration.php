<?php
/** Run only against the disposable source-recovery WordPress site. */
if ( PHP_SAPI !== 'cli' ) { exit; }
$config = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true );
require $config['site'] . '/wp-load.php';
if ( DB_NAME !== 'ohmylms_source_test' || ( ! defined( 'OHMYLMS_TEST_SITE' ) && ! defined( 'OMLMS_TEST_SITE' ) ) ) {
	throw new RuntimeException( 'Requires disposable test site' );
}
function api_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	$GLOBALS['api_checks']++;
}
$GLOBALS['api_checks'] = 0;
$original_prefix = $wpdb->prefix;
$prefix = 'apitest_' . bin2hex( random_bytes( 6 ) ) . '_';
// Connection-local tables keep all post, user and cache fixtures isolated.
foreach ( array( 'posts', 'postmeta', 'users', 'usermeta', 'options' ) as $table ) {
	api_check( false !== $wpdb->query( "CREATE TEMPORARY TABLE `{$prefix}{$table}` LIKE `{$original_prefix}{$table}`" ), 'Temporary table creation failed' );
}
$wpdb->set_prefix( $prefix );
wp_cache_flush();
try {
	$wpdb->insert( $wpdb->posts, array( 'ID' => 900001, 'post_type' => OHMYLMS_COURSE_CPT, 'post_status' => 'publish', 'post_name' => 'native-slug', 'post_title' => 'Fixture', 'post_content' => '', 'post_excerpt' => '', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '' ) );
	$store = new OhMyLMS\DataStores\CourseStore();
	api_check( 'native-slug-2' === $store->generate_unique_slug( 'Native Slug', OHMYLMS_COURSE_CPT ), 'Core collision suffix not used' );
	api_check( 'native-slug' === $store->generate_unique_slug( 'native-slug', OHMYLMS_COURSE_CPT, 900001 ), 'Self exclusion failed' );
	$filter = function () { return 'filtered-slug'; };
	add_filter( 'pre_wp_unique_post_slug', $filter );
	api_check( 'filtered-slug' === $store->generate_unique_slug( 'something', OHMYLMS_COURSE_CPT ), 'Core slug filter bypassed' );
	remove_filter( 'pre_wp_unique_post_slug', $filter );
	// Minimal object exercises the real store without unrelated relationship reads.
	$course = new class {
		public $slug;
		public $id = 900001;
		public function get_id() { return $this->id; }
		public function get_slug() { return 'native-slug'; }
		public function set_slug( $slug ) { $this->slug = $slug; }
		public function get_name() { return "Teacher's \\ course"; }
		public function get_description() { return "Text with \\ and 'quotes'"; }
		public function get_short_description() { return 'Excerpt'; }
		public function get_status() { return 'publish'; }
		public function get_date_created() { return null; }
		public function get_access_type() { return 'open'; }
		public function __call( $name, $args ) { return null; }
	};
	$saves = 0;
	add_action( 'save_post_' . OHMYLMS_COURSE_CPT, function ( $id ) use ( &$saves ) { if ( 900001 === $id ) { $saves++; } } );
	get_post( 900001 ); // Prime cache before update.
	$store->update( $course );
	$post = get_post( 900001 );
	api_check( $post->post_title === $course->get_name() && $post->post_content === $course->get_description(), 'Course text or cache update failed' );
	api_check( 1 === $saves, 'Native post save hook missing or duplicated' );
	api_check( $course->slug === $post->post_name, 'Final slug not synchronized' );
	$course->id = 999999;
	$failed = false;
	try { $store->update( $course ); } catch ( RuntimeException $error ) { $failed = true; }
	api_check( $failed && ! get_post( 999999 ), 'Missing course silently saved' );
	$wpdb->insert( $wpdb->users, array( 'ID' => 900002, 'user_login' => 'api-fixture', 'user_pass' => 'unused', 'user_email' => 'api@example.invalid', 'user_registered' => current_time( 'mysql' ) ) );
	$service = OhMyLMS\Services\EmailVerificationService::class;
	$token = $service::generate_token( 900002 );
	api_check( 'invalid' === $service::verify_token_detailed( 'wrong-token' )['status'], 'Invalid token accepted' );
	api_check( 900002 === $service::verify_token( $token ), 'Valid token lookup failed' );
	api_check( false === $service::verify_token( $token ), 'Consumed token reused' );
	$token = $service::generate_token( 900002 );
	update_user_meta( 900002, $service::META_EXPIRES, time() - 10 );
	api_check( 'expired' === $service::verify_token_detailed( $token )['status'], 'Expired token accepted' );
	api_check( '' === get_user_meta( 900002, $service::META_TOKEN, true ), 'Expired token retained' );
	set_transient( 'ohmylms_api_fixture', 'value', 100 );
	set_transient( 'unrelated_api_fixture', 'keep', 100 );
	set_site_transient( 'ohmylms_api_site_fixture', 'value', 100 );
	get_transient( 'ohmylms_api_fixture' );
	$deleted = 0;
	add_action( 'deleted_transient', function () use ( &$deleted ) { $deleted++; } );
	OhMyLMS\Admin\Ajax::clear_transient_cache();
	api_check( false === get_transient( 'ohmylms_api_fixture' ), 'Transient cache remained' );
	api_check( false === get_site_transient( 'ohmylms_api_site_fixture' ), 'Site transient remained' );
	api_check( 'keep' === get_transient( 'unrelated_api_fixture' ), 'Unrelated transient deleted' );
	api_check( $deleted > 0, 'Core deletion hooks bypassed' );
	wp_using_ext_object_cache( true );
	set_transient( 'mollie_methods_cache_live', 'cached', 100 );
	set_site_transient( 'update_plugins', 'cached', 100 );
	OhMyLMS\Admin\Ajax::clear_transient_cache();
	api_check( false === get_transient( 'mollie_methods_cache_live' ), 'Known object-cache transient remained' );
	api_check( false === get_site_transient( 'update_plugins' ), 'Known object-cache site transient remained' );
	wp_using_ext_object_cache( false );
	$wpdb->insert( $wpdb->posts, array( 'ID' => 900003, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Page title', 'post_content' => '', 'post_excerpt' => '', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '' ) );
	$controller = new OhMyLMS\Rest\V1\SettingsController();
	$method = new ReflectionMethod( $controller, 'get_page_title' );
	$method->setAccessible( true );
	api_check( 'Page title' === $method->invoke( $controller, 900003 ), 'Published page title missing' );
	api_check( '' === $method->invoke( $controller, 900001 ), 'Course accepted as a page' );
	wp_update_post( array( 'ID' => 900003, 'post_status' => 'draft' ) );
	api_check( '' === $method->invoke( $controller, 900003 ), 'Draft page title exposed' );
	foreach ( array( 910004 => 'publish', 910005 => 'draft', 910006 => 'publish' ) as $id => $status ) {
		$wpdb->insert( $wpdb->posts, array( 'ID' => $id, 'post_type' => 'ohmylms_coupon', 'post_status' => $status, 'post_title' => 910006 === $id ? 'native-code-extra' : 'native-code', 'post_content' => '', 'post_excerpt' => '', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '' ) );
	}
	$coupons = new OhMyLMS\Commerce\DataStore\CouponStore();
	api_check( array( 910004 ) === $coupons->get_ids_by_code( 'native-code' ), 'Coupon exact-title or status lookup changed' );
	echo $GLOBALS['api_checks'] . " WordPress API checks passed.\n";
} finally {
	wp_using_ext_object_cache( false );
	$wpdb->set_prefix( $original_prefix );
	wp_cache_flush();
}
