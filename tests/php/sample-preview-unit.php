<?php
/**
 * Standalone checks for the sample preview people: planning, markers, idempotency and safe removal.
 *
 * Runs against an in-memory fake of the school services and tables; it never touches a database.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools {
	final class Schema {
		public static function table( $name ) {
			return 'wp_ohmylms_' . $name; }
	}
	final class Access {
		public static function require_access( $ok ) {
			if ( ! $ok ) {
				throw new \RuntimeException( 'Forbidden', 403 ); }
		}
	}
	/** In-memory stand-ins for the real school services (same names and signatures). */
	final class Service {
		public static function now() {
			return '2026-01-01 00:00:00'; }
		public static function need( $ok, $message ) {
			if ( ! $ok ) {
				throw new \RuntimeException( $message, 400 ); }
		}
		public static function insert( $table, array $data ) {
			$GLOBALS['db'][ $table ][] = $data + array( 'id' => ++$GLOBALS['next_id'] );
			return $GLOBALS['next_id'];
		}
		public static function row( $table, $id ) {
			foreach ( $GLOBALS['db'][ $table ] ?? array() as $row ) {
				if ( (int) $row['id'] === (int) $id ) {
					return $row; }
			}
			return false;
		}
		public static function audit( $school, $action, $object = 0 ) {
			$GLOBALS['audit'][] = array( $school, $action ); }
		public static function transaction( $callback ) {
			return $callback(); }
		public static function membership( $table, array $key, $active = true ) {
			foreach ( $GLOBALS['db'][ $table ] as &$row ) {
				if ( array_intersect_assoc( $key, $row ) === $key ) {
					$row['status'] = $active ? 'active' : 'inactive';
					return $row['id'];
				}
			}
			return self::insert( $table, $key + array( 'status' => 'active' ) );
		}
		public static function create_school( $data ) {
			$slug = $data['slug'];
			for ( $i = 2; array_filter( $GLOBALS['db']['schools'], fn( $s ) => $s['slug'] === $slug ); $i++ ) {
				$slug = $data['slug'] . '-' . $i; }
			return array( 'id' => self::insert( 'schools', array( 'name' => $data['name'], 'slug' => $slug, 'status' => 'active' ) ) );
		}
		public static function create_year( $school, $data ) {
			return array( 'id' => self::insert( 'academic_years', array( 'school_id' => $school, 'label' => $data['label'], 'status' => 'active' ) ) ); }
		public static function create_class( $school, $data ) {
			return array( 'id' => self::insert( 'classes', array( 'school_id' => $school, 'academic_year_id' => $data['academic_year_id'], 'name' => $data['name'], 'status' => 'active' ) ) ); }
		public static function class_member( $class, $data ) {
			self::membership( 'class_memberships', array( 'class_id' => $class, 'user_id' => $data['user_id'], 'role' => $data['role'] ) );
			return array( 'success' => true );
		}
		public static function create_student( $school, $data ) {
			$id = \wp_insert_user( array( 'user_login' => 'student-' . $school . '-' . count( $GLOBALS['users'] ), 'user_email' => '', 'user_pass' => \wp_generate_password( 40 ), 'display_name' => $data['name'], 'role' => 'subscriber' ) );
			self::membership( 'school_memberships', array( 'school_id' => $school, 'user_id' => $id, 'role' => 'student' ) );
			return array( 'id' => $id, 'username' => 'x' );
		}
	}
}

namespace {
	// The removal code loads WordPress's user helpers; give it an empty stand-in.
	$fake_wp = sys_get_temp_dir() . '/oml-sample-preview-test/';
	if ( ! is_dir( $fake_wp . 'wp-admin/includes' ) ) {
		mkdir( $fake_wp . 'wp-admin/includes', 0777, true );
	}
	file_put_contents( $fake_wp . 'wp-admin/includes/user.php', "<?php
" );
	define( 'ABSPATH', $fake_wp );
	define( 'ARRAY_A', 'ARRAY_A' );
	$db      = array_fill_keys( array( 'schools', 'academic_years', 'classes', 'school_memberships', 'class_memberships', 'guardian_links', 'school_invitations', 'learning_assignments', 'user_enrollment', 'quiz_attempts', 'assignment_recipients', 'school_student_profiles', 'school_audit_log' ), array() );
	$next_id = 100;
	$audit   = array();
	$users   = array();
	$meta    = array();
	$options = array();
	$current = 1;
	$routes  = array();
	$deleted_users = array();

	class WP_Error {
		public function __construct( public $code = '', public $message = '', public $data = array() ) {}
		public function get_error_message() { return $this->message; }
	}
	function is_wp_error( $v ) { return $v instanceof WP_Error; }
	function esc_html( $s ) { return $s; }
	function get_current_user_id() { return $GLOBALS['current']; }
	function current_user_can( $cap ) { return 1 === $GLOBALS['current']; }
	function is_user_logged_in() { return $GLOBALS['current'] > 0; }
	function get_option( $k, $d = false ) { return $GLOBALS['options'][ $k ] ?? $d; }
	function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
	function delete_option( $k ) { unset( $GLOBALS['options'][ $k ] ); return true; }
	function wp_generate_password( $length = 12, ...$rest ) { return bin2hex( random_bytes( (int) ceil( $length / 2 ) ) ); }
	function username_exists( $login ) { foreach ( $GLOBALS['users'] as $u ) { if ( $u->user_login === $login ) { return $u->ID; } } return false; }
	function wp_insert_user( $data ) {
		$id = ++$GLOBALS['next_id'];
		$GLOBALS['users'][ $id ] = (object) array( 'ID' => $id, 'user_login' => $data['user_login'], 'user_email' => $data['user_email'], 'user_pass' => $data['user_pass'], 'display_name' => $data['display_name'], 'role' => $data['role'] );
		return $id;
	}
	function wp_update_user( $data ) { $GLOBALS['users'][ $data['ID'] ]->user_email = $data['user_email']; return $data['ID']; }
	function get_userdata( $id ) { return $GLOBALS['users'][ $id ] ?? false; }
	function get_user_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
	function update_user_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
	function get_users( $args ) {
		return array_values( array_filter( $GLOBALS['users'], fn( $u ) => isset( $GLOBALS['meta'][ $u->ID ][ $args['meta_query'][0]['key'] ] ) ) );
	}
	function wp_delete_user( $id ) { $GLOBALS['deleted_users'][] = $id; unset( $GLOBALS['users'][ $id ], $GLOBALS['meta'][ $id ] ); return true; }
	function delete_metadata( ...$args ) { return true; }
	function register_rest_route( $ns, $route, $args ) { $GLOBALS['routes'][ $route ] = $args; }
	function rest_ensure_response( $data ) { return new class( $data ) { public function __construct( public $data ) {} public function header( $k, $v ) {} }; }

	/** Tiny SQL evaluator for the COUNT/SELECT shapes SamplePreview issues. */
	$wpdb = new class() {
		public function prepare( $sql, ...$args ) {
			$args = 1 === count( $args ) && is_array( $args[0] ) ? $args[0] : $args;
			return array( $sql, $args );
		}
		private function rows( $table ) { return $GLOBALS['db'][ preg_replace( '/^wp_ohmylms_/', '', $table ) ] ?? array(); }
		public function get_var( $query ) {
			[ $sql, $args ] = $query;
			$table = array_shift( $args );
			preg_match( '/WHERE (.*)$/', $sql, $m );
			$count = 0;
			foreach ( $this->rows( $table ) as $row ) {
				if ( $this->matches( $row, $m[1], $args ) ) {
					$count++; }
			}
			return $count;
		}
		public function get_row( $query, $output = null ) {
			[ $sql, $args ] = $query;
			foreach ( $this->rows( $args[0] ) as $row ) {
				if ( $row['slug'] === $args[1] ) {
					return $row; }
			}
			return null;
		}
		public function delete( $table, $where ) {
			$name = preg_replace( '/^wp_ohmylms_/', '', $table );
			$GLOBALS['db'][ $name ] = array_values( array_filter( $GLOBALS['db'][ $name ], fn( $row ) => array_intersect_assoc( $where, $row ) !== $where ) );
			return 1;
		}
		private function matches( $row, $where, $args ) {
			if ( preg_match( '/^school_id=%d AND \(guardian_user_id NOT IN \(([\d,]+)\) OR student_user_id NOT IN \(([\d,]+)\)\)$/', $where, $m ) ) {
				$list = explode( ',', $m[1] );
				return (int) $row['school_id'] === (int) $args[0] && ( ! in_array( (string) $row['guardian_user_id'], $list, true ) || ! in_array( (string) $row['student_user_id'], $list, true ) );
			}
			foreach ( explode( ' AND ', $where ) as $clause ) {
				if ( preg_match( '/^(\w+) NOT IN \(([\d,]+)\)$/', $clause, $m ) ) {
					if ( in_array( (string) $row[ $m[1] ], explode( ',', $m[2] ), true ) ) { return false; }
				} elseif ( preg_match( '/^(\w+)<>%d$/', $clause, $m ) ) {
					if ( (int) $row[ $m[1] ] === (int) array_shift( $args ) ) { return false; }
				} elseif ( preg_match( '/^(\w+)=%[ds]$/', $clause, $m ) ) {
					if ( (string) $row[ $m[1] ] !== (string) array_shift( $args ) ) { return false; }
				} elseif ( preg_match( "/^(\w+)='(\w+)'$/", $clause, $m ) ) {
					if ( ( $row[ $m[1] ] ?? 'active' ) !== $m[2] ) { return false; }
				} else {
					throw new RuntimeException( 'Fake SQL does not understand: ' . $clause );
				}
			}
			return true;
		}
	};

	require dirname( __DIR__, 2 ) . '/includes/Schools/ViewAs.php';
	require dirname( __DIR__, 2 ) . '/includes/Schools/SamplePreview.php';
	use OhMyLMS\Schools\SamplePreview as S;

	$checks = 0;
	function check( $ok, $message ) { global $checks; if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; }
	function sizes() { return array_map( 'count', $GLOBALS['db'] ) + array( 'users' => count( $GLOBALS['users'] ) ); }

	// ---- Pure planning ----
	check( S::plan_create( array() ) === S::steps(), 'An empty site needs every step' );
	check( S::plan_create( array_fill_keys( S::steps(), true ) ) === array(), 'A complete site needs nothing' );
	check( S::plan_create( array( 'school' => true, 'year' => true, 'class' => true, 'user:teacher' => true ) ) === array( 'user:school', 'user:parent', 'user:student', 'member:school', 'member:teacher', 'class:teacher', 'class:student', 'guardian' ), 'Only the missing steps remain, in order' );
	check( S::is_sample_user( 'teacher', 'Sample-Preview-Teacher@EXAMPLE.invalid' ), 'A marked account on the reserved domain is a sample' );
	check( ! S::is_sample_user( 'teacher', 'real@school.test' ) && ! S::is_sample_user( '', 'a@example.invalid' ) && ! S::is_sample_user( 'admin', 'a@example.invalid' ) && ! S::is_sample_user( array(), 'a@example.invalid' ), 'A real address or an unknown marker is never a sample' );
	check( S::is_sample_school( array( 'name' => 'Sample Preview School', 'slug' => 'sample-preview-school-2' ) ) && ! S::is_sample_school( array( 'name' => 'Real School', 'slug' => 'sample-preview-school' ) ) && ! S::is_sample_school( array( 'name' => 'Sample Preview School', 'slug' => 'real-school' ) ) && ! S::is_sample_school( null ), 'School marker needs both the name and the slug' );
	$plan = S::plan_removal( array( 'school' => array( 'id' => 5, 'name' => 'Sample Preview School', 'slug' => 'sample-preview-school' ), 'users' => array( 'teacher' => array( 'id' => 7, 'name' => 'Sample Teacher', 'marked' => true ), 'parent' => array( 'id' => 8, 'name' => 'Someone', 'marked' => false ) ), 'outsiders' => array(), 'activity' => array() ) );
	check( $plan['allowed'] && 2 === count( $plan['remove'] ) && 1 === count( $plan['skip'] ) && false !== strpos( $plan['skip'][0], '#8' ), 'Unmarked accounts are skipped, never listed for removal' );
	$blocked = S::plan_removal( array( 'school' => array( 'id' => 5, 'name' => 'Sample Preview School', 'slug' => 'sample-preview-school' ), 'users' => array(), 'outsiders' => array( 'Invitations were sent for the school' ), 'activity' => array() ) );
	check( ! $blocked['allowed'] && array() === $blocked['remove'] && 1 === count( $blocked['blockers'] ), 'Signs of real use block removal and empty the removal list' );

	// ---- REST permissions ----
	S::register();
	check( 3 === count( $routes ), 'Status, create and remove routes are registered' );
	$current = 2;
	check( $routes['/school/sample-preview/create']['permission_callback']() instanceof WP_Error, 'A non-admin can create sample people' );
	check( $routes['/school/sample-preview/remove']['permission_callback']() instanceof WP_Error, 'A non-admin can remove sample people' );
	$current = 1;
	check( true === $routes['/school/sample-preview/create']['permission_callback'](), 'An administrator is allowed' );
	check( 'POST' === $routes['/school/sample-preview/create']['methods'] && 'POST' === $routes['/school/sample-preview/remove']['methods'] && 'GET' === $routes['/school/sample-preview']['methods'], 'Writes are POST only' );
	$current = 2;
	try { S::create(); check( false, 'A non-admin created sample data' ); } catch ( RuntimeException $e ) { check( 403 === $e->getCode(), 'Non-admin create is forbidden' ); }
	$current = 1;

	// A real person who happens to use a sample-looking name is never reused or removed.
	wp_insert_user( array( 'user_login' => 'sample-preview-teacher', 'user_email' => 'real@school.test', 'user_pass' => 'x', 'display_name' => 'Real Teacher', 'role' => 'ohmylms_teacher' ) );
	$real_id = array_key_last( $users );
	try { S::create(); check( false, 'Created over an unmarked account' ); } catch ( RuntimeException $e ) { check( false !== strpos( $e->getMessage(), 'not a marked sample' ), 'The clash is explained' ); }
	check( 'Real Teacher' === $users[ $real_id ]->display_name && ! isset( $meta[ $real_id ] ), 'The unmarked account was not changed or marked' );
	unset( $users[ $real_id ] );
	$db = array_map( fn() => array(), $db );
	$options = array();

	// ---- Create ----
	$status = S::create();
	check( $status['complete'] && $status['exists'], 'Create completes' );
	check( 4 === count( $users ), 'Exactly one school admin, teacher, parent and student exist' );
	foreach ( $users as $user ) {
		check( isset( $meta[ $user->ID ][ S::META ] ) && S::is_sample_user( $meta[ $user->ID ][ S::META ], $user->user_email ), 'Every created account carries the marker and a non-deliverable address' );
		check( 40 <= strlen( $user->user_pass ) && ! in_array( $user->user_pass, array( '', 'password' ), true ), 'Passwords are long random values' );
	}
	check( 4 === count( array_unique( array_map( fn( $u ) => $u->user_pass, $users ) ) ), 'No two sample accounts share a password' );
	check( false === strpos( wp_json_encode_stub( $status ), 'user_pass' ), 'No password is ever returned' );
	function wp_json_encode_stub( $v ) { return json_encode( $v ); }
	check( 1 === count( $db['schools'] ) && 1 === count( $db['academic_years'] ) && 1 === count( $db['classes'] ), 'One school, year and class' );
	check( 'Sample Preview School' === $db['schools'][0]['name'] && 'sample-preview-school' === $db['schools'][0]['slug'], 'The school is clearly marked sample' );
	check( 1 === count( $db['guardian_links'] ) && 'active' === $db['guardian_links'][0]['status'], 'The parent is linked to the student as guardian' );
	$classes = array_map( fn( $r ) => $r['role'], $db['class_memberships'] );
	sort( $classes );
	check( array( 'student', 'teacher' ) === $classes, 'Teacher and student are in the class' );
	check( $db['guardian_links'][0]['guardian_user_id'] === $options[ S::OPTION ]['users']['parent'] && $db['guardian_links'][0]['student_user_id'] === $options[ S::OPTION ]['users']['student'], 'The link joins the sample parent and sample student' );
	check( 'Sample Student' === $users[ $options[ S::OPTION ]['users']['student'] ]->display_name && 'Sample Teacher' === $users[ $options[ S::OPTION ]['users']['teacher'] ]->display_name, 'Names carry the Sample marker' );

	// ---- Idempotent ----
	$before = sizes();
	$audit_before = count( $audit );
	$again  = S::create();
	check( $before === sizes() && $audit_before === count( $audit ) && $again === $status, 'A second click reuses everything and changes nothing' );

	// ---- Fills only gaps ----
	$db['guardian_links'] = array();
	check( ! S::status()['complete'] && S::status()['exists'], 'A missing link shows as partial' );
	S::create();
	check( 1 === count( $db['guardian_links'] ) && 4 === count( $users ) && 1 === count( $db['schools'] ), 'Create restores only the missing guardian link' );

	// ---- Removal ----
	$real = wp_insert_user( array( 'user_login' => 'real-parent', 'user_email' => 'p@example.test', 'user_pass' => 'x', 'display_name' => 'Real Parent', 'role' => 'ohmylms_parent' ) );
	$lookalike = wp_insert_user( array( 'user_login' => 'sample-preview-lookalike', 'user_email' => 'sample@example.invalid', 'user_pass' => 'x', 'display_name' => 'Sample Lookalike', 'role' => 'ohmylms_parent' ) );
	$before = sizes();
	$preview = S::remove( false );
	check( ! $preview['removed'] && $preview['allowed'] && $before === sizes(), 'Without confirmation nothing is removed' );
	check( 5 === count( $preview['remove'] ), 'The preview lists the school and the four marked accounts only' );
	foreach ( $preview['remove'] as $line ) {
		check( false === strpos( $line, '#' . $real ) && false === strpos( $line, '#' . $lookalike ), 'Unmarked accounts are not listed' );
	}

	// Real use blocks removal.
	$enrol = array( 'user_id' => $options[ S::OPTION ]['users']['student'], 'course_id' => 1 );
	$db['user_enrollment'][] = $enrol + array( 'id' => 900 );
	$with_activity = sizes();
	$refused = S::remove( true );
	check( ! $refused['removed'] && ! $refused['allowed'] && $refused['blockers'] && $with_activity === sizes(), 'Student activity blocks even a confirmed removal' );
	$db['user_enrollment'] = array();
	$db['school_memberships'][] = array( 'id' => 901, 'school_id' => $db['schools'][0]['id'], 'user_id' => $real, 'role' => 'student', 'status' => 'active' );
	$refused = S::remove( true );
	check( ! $refused['removed'] && ! empty( $refused['blockers'] ), 'A real member blocks removal' );
	check( 1 === count( $db['schools'] ) && $deleted_users === array(), 'Refused removal deletes nothing' );
	array_pop( $db['school_memberships'] );

	$removed = S::remove( true );
	check( $removed['removed'], 'Confirmed removal succeeds when nothing real is attached' );
	check( array() === $db['schools'] && array() === $db['classes'] && array() === $db['academic_years'] && array() === $db['guardian_links'] && array() === $db['school_memberships'] && array() === $db['class_memberships'], 'The sample school rows are gone' );
	check( 4 === count( $deleted_users ) && isset( $users[ $real ] ) && isset( $users[ $lookalike ] ), 'Only the four marked accounts were deleted; unmarked lookalikes stay' );
	check( ! isset( $options[ S::OPTION ] ), 'The ledger is cleared' );
	check( ! S::status()['exists'], 'Status shows nothing left' );

	// Removing again is a harmless no-op.
	$empty = S::remove( true );
	check( ! $empty['removed'] && ! $empty['allowed'], 'Nothing to remove the second time' );

	echo "Sample preview planning, markers, idempotency and safe removal checks passed ($checks).\n";
}
