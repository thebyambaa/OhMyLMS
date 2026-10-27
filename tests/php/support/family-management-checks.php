<?php
/**
 * Managed-child recovery and collection-choice authorization checks.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Family\Service as Family;
use OhMyLMS\Family\Schema as FamilySchema;
use OhMyLMS\Student\FamilyActions;
use OhMyLMS\Bundles\Bundles;
use OhMyLMS\Bundles\Follows;

defined( 'ABSPATH' ) || exit;

/**
 * Exercise real service writes with disposable identities owned by the caller.
 *
 * @param int $guardian Fixture parent.
 * @param int $managed Fixture managed child.
 * @param int $invited Fixture child linked independently, not managed.
 * @param int $course Fixture published course.
 * @return int
 * @throws RuntimeException When an invariant fails.
 */
function ohmylms_family_management_checks( $guardian, $managed, $invited, $course ) {
	global $wpdb;
	$count    = 0;
	$bundle   = 0;
	$original = get_current_user_id();
	$roles    = get_userdata( $managed )->roles;
	$check    = static function ( $value, $message ) use ( &$count ) {
		if ( ! $value ) {
			throw new RuntimeException( esc_html( $message ) );
		}
		++$count;
	};
	$deny     = static function ( $work, $code = 403 ) use ( $check ) {
		try {
			$work();
		} catch ( RuntimeException $error ) {
			$check( $code === $error->getCode(), 'Incorrect rejection status: ' . $error->getMessage() );
			return;
		}
		throw new RuntimeException( 'Unauthorized management operation succeeded.' );
	};
	$token    = static function ( $flash ) {
		parse_str( wp_parse_url( $flash['setup_url'], PHP_URL_QUERY ), $params );
		return $params['ohmylms_child_setup'];
	};
	try {
		wp_set_current_user( $guardian );
		$deny( static fn() => FamilyActions::apply( 'issue_setup', array( 'child_id' => $managed ) ), 400 );
		$before = get_userdata( $managed )->user_pass;
		$first  = FamilyActions::apply(
			'issue_setup',
			array(
				'child_id' => $managed,
				'confirm'  => 'yes',
			)
		);
		$second = FamilyActions::apply(
			'issue_setup',
			array(
				'child_id' => $managed,
				'confirm'  => 'yes',
			)
		);
		$check( get_userdata( $managed )->user_pass === $before, 'Issuing recovery links changed the password.' );
		$deny( static fn() => Family::accept_setup( $token( $first ), wp_generate_password( 24 ) ), 400 );
		$check( 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE learner_user_id=%d AND kind='setup' AND consumed_at IS NULL AND revoked_at IS NULL", FamilySchema::table( 'invitations' ), $managed ) ), 'Replacement left multiple valid links.' );
		$deny( static fn() => Family::issue_setup( $invited ) );
		$deny( static fn() => Family::set_choices( $invited, 'Grade 7', array() ) );
		$person = new WP_User( $managed );
		$person->add_role( 'administrator' );
		$check( ! Family::is_managed( $managed ), 'Promoted administrator remains manageable.' );
		$deny( static fn() => Family::issue_setup( $managed ) );
		$deny( static fn() => Family::accept_setup( $token( $second ), wp_generate_password( 24 ) ) );
		$deny( static fn() => Family::set_choices( $managed, 'Grade 7', array() ) );
		$check( get_userdata( $managed )->user_pass === $before, 'Promoted account password changed.' );
		$person->remove_role( 'administrator' );
		$person->add_role( 'ohmylms_teacher' );
		$deny( static fn() => Family::issue_setup( $managed ) );
		$person->remove_role( 'ohmylms_teacher' );
		$person->add_cap( 'edit_users' );
		$deny( static fn() => Family::issue_setup( $managed ) );
		$person->remove_cap( 'edit_users' );
		$password = wp_generate_password( 28 );
		Family::accept_setup( $token( $second ), $password );
		$check( wp_check_password( $password, get_userdata( $managed )->user_pass, $managed ), 'Managed child could not use the valid reset link.' );
		$deny( static fn() => Family::accept_setup( $token( $second ), wp_generate_password( 24 ) ), 400 );
		$expired = Family::issue_setup( $managed );
		$wpdb->update( FamilySchema::table( 'invitations' ), array( 'expires_at' => '2000-01-01 00:00:00' ), array( 'id' => $expired['id'] ) );
		$deny( static fn() => Family::accept_setup( $expired['token'], wp_generate_password( 24 ) ), 400 );
		// Native handler rejects a missing nonce before any mutation.
		$die_handler = static function () {
			return static function () {
				throw new RuntimeException( 'Nonce rejected.', 403 );
			};
		};
		add_filter( 'wp_die_handler', $die_handler );
		$_POST = array(
			'operation' => 'issue_setup',
			'child_id'  => (string) $managed,
			'confirm'   => 'yes',
		);
		try {
			$deny( static fn() => FamilyActions::handle() );
		} finally {
			remove_filter( 'wp_die_handler', $die_handler );
			$_POST = array();
		}
		$created = Bundles::create( array( 'title' => 'Family choices fixture' ) );
		$check( ! is_wp_error( $created ), 'Cannot create collection fixture.' );
		$bundle = $created['id'];
		Bundles::set_members(
			$bundle,
			array(
				array(
					'type' => 'course',
					'id'   => $course,
				),
			)
		);
		Bundles::set_status( $bundle, true );
		FamilyActions::apply(
			'update_choices',
			array(
				'child_id' => $managed,
				'grade'    => 'Grade 6',
				'bundles'  => array( $bundle, 999999999 ),
			)
		);
		$choice = Family::choices( $managed );
		$check( 'Grade 6' === $choice['grade'] && array( (int) $bundle ) === $choice['followed'], 'Choices were not saved or an unavailable collection was accepted.' );
		$check( array() === Follows::enrolled_courses( $managed ), 'Following a collection enrolled the learner.' );
		Family::set_choices( $managed, '', array() );
		$check( '' === Family::choices( $managed )['grade'] && array() === Family::choices( $managed )['followed'], 'Clearing optional choices failed.' );
		$revoked = Family::issue_setup( $managed );
		$child   = Family::child( $managed );
		Family::revoke_link( $child['link_id'] );
		$deny( static fn() => Family::accept_setup( $revoked['token'], wp_generate_password( 24 ) ) );
		$deny( static fn() => Family::set_choices( $managed, 'Grade 9', array( $bundle ) ) );
	} finally {
		if ( $bundle ) {
			Bundles::delete( $bundle, true );
		}
		wp_set_current_user( $original );
		$person = new WP_User( $managed );
		$person->set_role( $roles[0] );
		$person->remove_cap( 'edit_users' );
		$wpdb->delete( FamilySchema::table( 'invitations' ), array( 'learner_user_id' => $managed ) );
		delete_transient( FamilyActions::FLASH . $guardian );
	}
	return $count;
}
