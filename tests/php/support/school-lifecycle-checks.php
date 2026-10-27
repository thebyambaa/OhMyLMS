<?php
/**
 * Removable lifecycle persistence and authorization checks.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Schools\Access;
use OhMyLMS\Schools\Lifecycle;
use OhMyLMS\Schools\Service;
use OhMyLMS\Student\SchoolActions;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/school-report-checks.php';

/**
 * Verify recovery, previewed rollover and voluntary departure in isolated fixtures.
 *
 * @return int Passed check count.
 */
function ohmylms_school_lifecycle_checks() {
	$original = get_current_user_id();
	$fixture  = array();
	$count    = 0;
	$check    = static function ( $condition, $message ) use ( &$count ) {
		if ( ! $condition ) {
			throw new RuntimeException( esc_html( $message ) ); }
		++$count;
	};
	$denied   = static function ( $callback ) use ( $check ) {
		try {
			$callback(); } catch ( RuntimeException $error ) {
			$check( in_array( $error->getCode(), array( 400, 403 ), true ), 'Expected permission or validation denial.' );
			return;
			}
			$check( false, 'Unauthorized lifecycle operation succeeded.' );
	};
	$token    = static function ( $result ) {
		parse_str( wp_parse_url( $result['url'] ?? $result['invite_url'], PHP_URL_QUERY ), $query );
		return $query['ohmylms_invite'];
	};
	try {
		ohmylms_school_report_fixture( $fixture );
		$school             = $fixture['schools'][0];
		$class              = $fixture['classes'][0];
		$owner              = $fixture['users'][0];
		$other              = $fixture['users'][1];
		$created            = Service::create_student(
			$school,
			array(
				'name'                => 'Recovery fixture',
				'external_student_id' => 'recovery-fixture',
			)
		);
		$student            = $created['id'];
		$fixture['users'][] = $student;
		$hash               = get_userdata( $student )->user_pass;
		$denied(
			static function () use ( $school, $student ) {
				SchoolActions::apply( 'student_recovery', $school, array( 'student_user_id' => $student ) );
			}
		);
		$old = Lifecycle::recovery( $school, $student );
		$new = SchoolActions::apply(
			'student_recovery',
			$school,
			array(
				'student_user_id' => $student,
				'confirm'         => 'yes',
			)
		);
		$check( get_userdata( $student )->user_pass === $hash, 'Creating recovery changed the password.' );
		$denied(
			static function () use ( $old, $token ) {
				Service::accept( $token( $old ), wp_generate_password( 24 ) );
			}
		);
		$denied(
			static function () use ( $fixture, $student ) {
				Lifecycle::recovery( $fixture['schools'][1], $student );
			}
		);
		$denied(
			static function () use ( $fixture, $school ) {
				Lifecycle::recovery( $school, $fixture['users'][2] );
			}
		);
		$password = wp_generate_password( 24 );
		Service::accept( $token( $new ), $password );
		$check( wp_check_password( $password, get_userdata( $student )->user_pass, $student ), 'Reset link did not set the student password.' );
		$denied(
			static function () use ( $new, $token ) {
				Service::accept( $token( $new ), wp_generate_password( 24 ) );
			}
		);
		// Commit revocation through a second connection after candidate lookup but before the user lock.
		// A candidate lookup inside the transaction would retain the old repeatable-read snapshot.
		global $wpdb;
		$race_link   = Lifecycle::recovery( $school, $student );
		$before_race = get_userdata( $student )->user_pass;
		$db_host     = $wpdb->parse_db_host( DB_HOST );
		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__mysqli -- A separate committed connection is required to reproduce the transaction snapshot race.
		$connection            = new mysqli( $db_host[0], DB_USER, DB_PASSWORD, DB_NAME, $db_host[1] ? (int) $db_host[1] : 3306, $db_host[2] );
		$revoked_during_accept = false;
		$revoke_before_lock    = static function ( $query ) use ( $connection, $school, $student, &$revoked_during_accept, $wpdb ) {
			if ( ! $revoked_during_accept && false !== strpos( $query, 'SELECT ID FROM' ) && false !== strpos( $query, $wpdb->users ) && false !== strpos( $query, 'FOR UPDATE' ) ) {
				$revoked_during_accept = true;
				$table                 = \OhMyLMS\Schools\Schema::table( 'school_memberships' );
				$statement             = $connection->prepare( "UPDATE `$table` SET status='inactive' WHERE school_id=? AND user_id=? AND role='student'" );
				$statement->bind_param( 'ii', $school, $student );
				$statement->execute();
				$statement->close();
			}
			return $query;
		};
		add_filter( 'query', $revoke_before_lock );
		try {
			$denied(
				static function () use ( $race_link, $token ) {
					Service::accept( $token( $race_link ), wp_generate_password( 24 ) );
				}
			);
			$check( $revoked_during_accept, 'The revocation race was not exercised.' );
			$check( get_userdata( $student )->user_pass === $before_race, 'Revoked membership was read from a stale snapshot and the password changed.' );
		} finally {
			remove_filter( 'query', $revoke_before_lock );
			$connection->close();
			Service::membership(
				'school_memberships',
				array(
					'school_id' => $school,
					'user_id'   => $student,
					'role'      => 'student',
				)
			);
		}
		$promoted_link = Lifecycle::recovery( $school, $student );
		( new WP_User( $student ) )->add_role( 'administrator' );
		$denied(
			static function () use ( $promoted_link, $token ) {
				Service::accept( $token( $promoted_link ), wp_generate_password( 24 ) );
			}
		);
		$denied(
			static function () use ( $school, $student ) {
				Lifecycle::recovery( $school, $student );
			}
		);
		( new WP_User( $student ) )->remove_role( 'administrator' );
		$from = (int) Service::row( 'classes', $class )['academic_year_id'];
		$year = Service::create_year(
			$school,
			array(
				'label'     => '2027–28',
				'starts_at' => '2027-09-01',
				'ends_at'   => '2028-06-01',
			)
		);
		$to   = (int) $year['id'];
		$denied(
			static function () use ( $school, $from, $to ) {
				Lifecycle::rollover( $school, $from, $to );
			}
		);
		$denied(
			static function () use ( $school, $from ) {
				Lifecycle::preview( $school, $from, $from );
			}
		);
		$preview = Lifecycle::preview( $school, $from, $to );
		$check( 1 === count( $preview['classes'] ) && 'active' === Service::row( 'classes', $class )['status'], 'Preview mutated the source class.' );
		Service::create_class(
			$school,
			array(
				'name'             => 'Added after preview',
				'academic_year_id' => $from,
			)
		);
		$denied(
			static function () use ( $school, $from, $to ) {
				Lifecycle::rollover( $school, $from, $to );
			}
		);
		Lifecycle::preview( $school, $from, $to );
		$result = SchoolActions::apply(
			'rollover_commit',
			$school,
			array(
				'from_year' => $from,
				'to_year'   => $to,
				'confirm'   => 'yes',
			)
		);
		$check( ! empty( $result['message'] ) && 'archived' === Service::row( 'academic_years', $from )['status'], 'Confirmed rollover failed.' );
		$check( 'archived' === Service::row( 'classes', $class )['status'], 'Source class was not archived.' );
		$denied(
			static function () use ( $school, $from ) {
				Service::create_class(
					$school,
					array(
						'name'             => 'Must not be created',
						'academic_year_id' => $from,
					)
				);
			}
		);
		global $wpdb;
		$copies = $wpdb->get_results( $wpdb->prepare( 'SELECT id FROM %i WHERE academic_year_id=%d', \OhMyLMS\Schools\Schema::table( 'classes' ), $to ), ARRAY_A );
		$check( 2 === count( $copies ), 'Rollover copied the wrong number of classes.' );
		$check( array() === Service::roster( $school, (int) $copies[0]['id'] ), 'Rollover copied students.' );
		$denied(
			static function () use ( $school, $from, $to ) {
				Lifecycle::rollover( $school, $from, $to );
			}
		);
		$denied(
			static function () use ( $school ) {
				Lifecycle::leave( $school );
			}
		);
		wp_set_current_user( $student );
		$denied(
			static function () use ( $school ) {
				Lifecycle::leave( $school );
			}
		);
		wp_set_current_user( $owner );
		Service::membership(
			'school_memberships',
			array(
				'school_id' => $school,
				'user_id'   => $other,
				'role'      => 'school_admin',
			)
		);
		$denied(
			static function () use ( $school ) {
				SchoolActions::apply( 'leave_school', $school, array() );
			}
		);
		$pending_link = Lifecycle::recovery( $school, $student );
		SchoolActions::apply( 'leave_school', $school, array( 'confirm' => 'yes' ) );
		$check( ! Access::school( $school, array( 'school_admin', 'teacher' ), $owner ), 'Departure retained school access.' );
		$check( (bool) get_userdata( $owner ) && (bool) Service::row( 'learning_assignments', $fixture['learning'] ), 'Departure deleted the account or learning records.' );
		$denied(
			static function () use ( $school, $student ) {
				Lifecycle::recovery( $school, $student );
			}
		);
		wp_set_current_user( $other );
		$denied(
			static function () use ( $pending_link, $token ) {
				Service::accept( $token( $pending_link ), wp_generate_password( 24 ) );
			}
		);
		global $wpdb;
		$last_admin = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE school_id=%d AND user_id=%d AND role='school_admin' AND status='active'", \OhMyLMS\Schools\Schema::table( 'school_memberships' ), $school, $other ) );
		$denied(
			static function () use ( $school, $last_admin ) {
				Service::remove_member( $school, (int) $last_admin );
			}
		);
		$check( Access::school( $fixture['schools'][1] ), 'Departure affected another school.' );
		return $count;
	} finally {
		wp_set_current_user( $original );
		ohmylms_school_report_cleanup( $fixture );
	}
}
