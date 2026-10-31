<?php
/**
 * Standalone checks for the hub slug migration (student-home to profile) and the redirects around it.
 *
 * Uses the real Hub and LegacyPages classes against in-memory stand-ins for WordPress; no database.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce {
	function ecommerce() {
		return new class() {
			public $query;
			public function __construct() {
				$this->query = new class() {
					public function get_query_vars() { return array( 'orders' => 'orders' ); }
				};
			}
		};
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'OHMYLMS_SLUG', 'ohmylms' );
	$options = array();
	$posts   = array();
	$can     = true;
	$force_unique_suffix = false;

	function __( $text, $domain = '' ) { return $text; }
	function absint( $n ) { return abs( (int) $n ); }
	function sanitize_text_field( $s ) { return trim( (string) $s ); }
	function sanitize_hex_color( $c ) { return preg_match( '/^#[0-9a-f]{6}$/i', $c ) ? $c : ''; }
	function get_option( $k, $d = false ) { return $GLOBALS['options'][ $k ] ?? $d; }
	function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
	function current_user_can( $cap ) { return $GLOBALS['can']; }
	function get_post( $id ) { return $GLOBALS['posts'][ $id ] ?? null; }
	function has_shortcode( $content, $tag ) { return false !== strpos( $content, '[' . $tag . ']' ); }
	function get_permalink( $post ) { $post = is_object( $post ) ? $post : get_post( $post ); return 'http://800.local/' . $post->post_name . '/'; }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
	function is_wp_error( $v ) { return false; }
	function add_query_arg( $args, $url ) { if ( ! $args ) { return $url; } return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
	function admin_url( $path = '' ) { return 'http://800.local/wp-admin/' . $path; }
	function wp_update_post( $data, $wp_error = false ) {
		$post = $GLOBALS['posts'][ $data['ID'] ];
		$name = $data['post_name'];
		foreach ( $GLOBALS['posts'] as $other ) {
			if ( $other->ID !== $post->ID && $other->post_name === $name && 'trash' !== $other->post_status ) {
				$name .= '-2'; }
		}
		if ( $GLOBALS['force_unique_suffix'] && 'profile' === $name ) {
			$name = 'profile-2'; }
		$post->post_name = $name;
		return $post->ID;
	}
	$wpdb = new class() {
		public $posts = 'wp_posts';
		public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
		public function get_var( $q ) {
			[ , $args ] = $q;
			foreach ( $GLOBALS['posts'] as $post ) {
				if ( $post->post_name === $args[0] && $post->ID !== (int) $args[1] && ! in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) && in_array( $post->post_type, array( 'page', 'post', 'attachment' ), true ) ) {
					return $post->ID; }
			}
			return null;
		}
	};
	function make_page( $id, $name, $status = 'publish', $content = '' ) {
		$GLOBALS['posts'][ $id ] = (object) array( 'ID' => $id, 'post_type' => 'page', 'post_status' => $status, 'post_name' => $name, 'post_content' => $content );
	}
	function reset_site( $slug = 'student-home' ) {
		$GLOBALS['options'] = array( 'ohmylms_student_hub' => array( 'page_id' => 318, 'enabled' => true ) );
		$GLOBALS['posts']   = array();
		$GLOBALS['can']     = true;
		$GLOBALS['force_unique_suffix'] = false;
		make_page( 318, $slug, 'publish', '[ohmylms_student_hub]' );
	}

	require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
	require dirname( __DIR__, 2 ) . '/includes/Student/Hub.php';
	require dirname( __DIR__, 2 ) . '/includes/Student/LegacyPages.php';
	use OhMyLMS\Student\Hub;
	use OhMyLMS\Student\LegacyPages;

	$checks = 0;
	function check( $ok, $message ) { global $checks; if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; }

	// ---- Defaults ----
	check( 'profile' === Hub::SLUG && 'student-home' === Hub::LEGACY_SLUG, 'The hub slug constants are profile and student-home' );
	check( false !== strpos( file_get_contents( dirname( __DIR__, 2 ) . '/includes/Student/Hub.php' ), "'post_name'    => self::SLUG" ), 'A new site creates the page with the new slug' );

	// ---- Decision rules ----
	$facts = array( 'configured_id' => 318, 'page_id' => 318, 'post_type' => 'page', 'post_name' => 'student-home', 'new_slug_taken' => false );
	check( Hub::slug_migration_decision( $facts )['rename'], 'The configured page with the old slug is renamed' );
	check( ! Hub::slug_migration_decision( array_merge( $facts, array( 'configured_id' => 7 ) ) )['rename'], 'A page that is not the configured hub is never renamed' );
	check( ! Hub::slug_migration_decision( array_merge( $facts, array( 'post_name' => 'my-hub' ) ) )['rename'], 'A custom slug is respected' );
	check( ! Hub::slug_migration_decision( array_merge( $facts, array( 'new_slug_taken' => true ) ) )['rename'], 'A taken slug blocks the rename' );
	check( ! Hub::slug_migration_decision( array_merge( $facts, array( 'post_type' => 'post' ) ) )['rename'], 'Only pages are renamed' );

	// ---- Migration ----
	reset_site();
	Hub::migrate_slug();
	$record = get_option( Hub::SLUG_OPTION );
	check( 'profile' === $posts[318]->post_name && 'renamed' === $record['result'] && '/student-home/' === $record['old_path'] && 318 === $record['page_id'], 'The hub page is renamed and the old path recorded' );
	$posts[318]->post_name = 'student-home';
	Hub::migrate_slug();
	check( 'student-home' === $posts[318]->post_name, 'The migration runs once: a later call does not rename again' );

	reset_site();
	make_page( 400, 'profile__trashed', 'trash' );
	Hub::migrate_slug();
	check( 'profile' === $posts[318]->post_name && 'renamed' === get_option( Hub::SLUG_OPTION )['result'], 'A trashed page (slug with __trashed) does not block the rename' );

	reset_site();
	make_page( 401, 'profile', 'publish', 'Public profile page' );
	Hub::migrate_slug();
	$record = get_option( Hub::SLUG_OPTION );
	check( 'student-home' === $posts[318]->post_name && 'skipped' === $record['result'] && false !== strpos( $record['reason'], 'already used' ), 'A taken slug leaves the page alone and records why' );

	reset_site();
	make_page( 402, 'profile', 'draft' );
	Hub::migrate_slug();
	check( 'student-home' === $posts[318]->post_name && 'skipped' === get_option( Hub::SLUG_OPTION )['result'], 'A draft page with the slug also blocks it' );

	reset_site( 'my-hub' );
	Hub::migrate_slug();
	check( 'my-hub' === $posts[318]->post_name && 'skipped' === get_option( Hub::SLUG_OPTION )['result'], 'A renamed-by-the-site hub page is not touched' );

	reset_site();
	$options['ohmylms_student_hub']['page_id'] = 0;
	Hub::migrate_slug();
	check( 'student-home' === $posts[318]->post_name && 'skipped' === get_option( Hub::SLUG_OPTION )['result'], 'Without a configured hub page nothing is renamed' );

	reset_site();
	$force_unique_suffix = true;
	Hub::migrate_slug();
	check( 'student-home' === $posts[318]->post_name && 'skipped' === get_option( Hub::SLUG_OPTION )['result'], 'If WordPress would suffix the slug, the original slug is restored' );

	reset_site( 'profile' );
	Hub::migrate_slug();
	check( 'not_needed' === get_option( Hub::SLUG_OPTION )['result'] && 'profile' === $posts[318]->post_name, 'A site already on the new slug records that nothing was needed' );

	reset_site();
	$can = false;
	Hub::migrate_slug();
	check( false === get_option( Hub::SLUG_OPTION, false ) && 'student-home' === $posts[318]->post_name, 'Visitors without manage_options never trigger the migration' );

	// ---- Old address redirects ----
	reset_site();
	Hub::migrate_slug();
	check( 'http://800.local/profile/?hub_section=library&hub_role=teacher' === LegacyPages::renamed_hub_target( '/student-home/?hub_section=library&hub_role=teacher' ), 'The old address keeps hub_section and hub_role' );
	$token = str_repeat( 'a1', 32 );
	check( 'http://800.local/profile/?ohmylms_invite=' . $token === LegacyPages::renamed_hub_target( '/student-home/?ohmylms_invite=' . $token ), 'Invitation links sent earlier keep their token' );
	check( 'http://800.local/profile/' === LegacyPages::renamed_hub_target( '/student-home' ) && 'http://800.local/profile/' === LegacyPages::renamed_hub_target( '/student-home/' ), 'The bare old address works with or without the slash' );
	check( '' === LegacyPages::renamed_hub_target( '/profile/?hub_section=library' ) && '' === LegacyPages::renamed_hub_target( '/other/' ) && '' === LegacyPages::renamed_hub_target( '/student-home-extra/' ), 'The new address and unrelated pages are never redirected' );
	update_option( Hub::SLUG_OPTION, array( 'result' => 'skipped', 'old_path' => '/student-home/' ) );
	check( '' === LegacyPages::renamed_hub_target( '/student-home/' ), 'Nothing redirects when the migration did not rename the page' );
	update_option( Hub::SLUG_OPTION, array( 'result' => 'renamed', 'old_path' => '/profile/' ) );
	check( '' === LegacyPages::renamed_hub_target( '/profile/' ), 'A recorded old path equal to the hub path can never loop' );

	// ---- Retired page ledger vs the new /profile/ address ----
	reset_site( 'profile' );
	update_option(
		LegacyPages::OPTION,
		array(
			115 => array( 'url' => 'http://800.local/dashboard/', 'endpoint' => 'dashboard', 'option' => 'ohmylms_student_dashboard_page_id' ),
			117 => array( 'url' => 'http://800.local/profile/', 'endpoint' => 'profile', 'option' => 'ohmylms_student_profile_page_id' ),
			118 => array( 'url' => 'http://800.local/my-courses/', 'endpoint' => 'my-courses', 'option' => 'ohmylms_student_courses_page_id' ),
		)
	);
	check( '' === LegacyPages::target( '/profile/' ) && '' === LegacyPages::target( '/profile' ) && '' === LegacyPages::target( '/profile/?hub_section=account&hub_view=account' ) && '' === LegacyPages::target( '/profile/?ohmylms_invite=' . $token ), 'The retired profile route no longer redirects the hub onto itself' );
	check( 'http://800.local/profile/?hub_section=learn' === LegacyPages::target( '/dashboard/' ), 'Other retired routes still reach the hub' );
	check( 'http://800.local/profile/?hub_section=account&hub_view=account' === LegacyPages::target( '/?page_id=117' ), 'A retired page ID still reaches the account view' );
	check( 'http://800.local/profile/?hub_section=learn&hub_view=courses' === LegacyPages::target( '/my-courses/' ), 'Retired courses route unchanged' );
	check( LegacyPages::same_location( 'http://800.local/profile/?a=1', '/profile?a=1' ) && ! LegacyPages::same_location( 'http://800.local/profile/?a=1', '/profile/' ), 'A redirect to the requested address is detected' );

	echo "Hub slug migration, old-address redirect and retired-route loop checks passed ($checks).\n";
}
