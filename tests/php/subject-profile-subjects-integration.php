<?php
/**
 * Profile creation and Library visibility against an isolated local WordPress copy.
 *
 * Run with OHMYLMS_STRUCTURE_INTEGRATION=1. Fixture rows are rolled back.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Curriculum\Items;
use OhMyLMS\Skills\SubjectProfiles;
use OhMyLMS\Student\LearnerCatalog;
use OhMyLMS\Utility\Transaction;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STRUCTURE_INTEGRATION' ) ) {
	exit( "Set OHMYLMS_STRUCTURE_INTEGRATION=1 on an isolated local WordPress copy.\n" );
}
require dirname( __DIR__, 5 ) . '/wp-load.php';

/**
 * Assert a profile-to-subject integration behavior.
 *
 * @param bool   $condition Expected condition.
 * @param string $message Failure reason.
 * @throws RuntimeException When a check fails.
 */
function ohmylms_profile_subject_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

// Transactions must cover both curriculum rows and the profile option.
global $wpdb;
foreach ( array( Items::table(), $wpdb->options ) as $profile_test_table ) {
	$profile_test_engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $profile_test_table ) );
	ohmylms_profile_subject_check( 'InnoDB' === $profile_test_engine, 'Fixtures require transactional tables.' );
}

try {
	Transaction::run(
		static function () {
			$label   = 'Profile fixture ' . wp_generate_password( 10, false, false );
			$subject = Items::create(
				array(
					'name'      => $label,
					'item_type' => 'subject',
				)
			);
			ohmylms_profile_subject_check( ! is_wp_error( $subject ), 'Subject fixture creates.' );
			$before   = count( Items::all() );
			$revision = SubjectProfiles::revision();
			$result   = SubjectProfiles::save(
				array(
					'label'  => $label,
					'levels' => array( 'Level 1' ),
				),
				$revision
			);
			ohmylms_profile_subject_check( ! is_wp_error( $result ), 'A profile saves.' );
			$profile    = array_values(
				array_filter(
					$result['profiles'],
					static function ( $row ) use ( $label ) {
						return $label === $row['label'];
					}
				)
			)[0];
			$subject_id = $profile['curriculum_id'];
			ohmylms_profile_subject_check( (int) $subject['id'] === $subject_id && count( Items::all() ) === $before, 'A same-name curriculum subject is reused.' );
			$result = SubjectProfiles::save(
				array(
					'label'  => $label . ' stale',
					'levels' => array( '1' ),
				),
				$revision
			);
			ohmylms_profile_subject_check( is_wp_error( $result ) && count( Items::all() ) === $before, 'Stale saves do not create subjects.' );
			$result = SubjectProfiles::save(
				array(
					'key'    => $profile['key'],
					'label'  => $label . ' renamed',
					'levels' => array( '1' ),
				),
				SubjectProfiles::revision()
			);
			ohmylms_profile_subject_check( ! is_wp_error( $result ) && Items::get( $subject_id )['name'] === $label . ' renamed', 'Rename updates the existing subject identity.' );
			$library = LearnerCatalog::subjects( '', array( 'subject' ), true );
			ohmylms_profile_subject_check( in_array( $subject_id, array_column( $library, 'id' ), true ), 'Library includes the new subject without courses.' );
			ohmylms_profile_subject_check( ! in_array( $subject_id, array_column( LearnerCatalog::subjects( '', array( 'subject' ) ), 'id' ), true ), 'Explore excludes empty subjects.' );
			$label  = $label . ' new';
			$result = SubjectProfiles::save(
				array(
					'label'  => $label,
					'levels' => array( '1' ),
				),
				SubjectProfiles::revision()
			);
			ohmylms_profile_subject_check( ! is_wp_error( $result ) && count( Items::all() ) === $before + 1, 'A new profile creates its curriculum subject.' );
			SubjectProfiles::remove( $profile['key'], SubjectProfiles::revision() );
			ohmylms_profile_subject_check( null !== Items::get( $subject_id ), 'Removing a skill profile preserves curriculum identity and placements.' );
			throw new RuntimeException( 'ohmylms_profile_subject_fixture_rollback' );
		}
	);
} catch ( RuntimeException $error ) {
	if ( 'ohmylms_profile_subject_fixture_rollback' !== $error->getMessage() ) {
		throw $error;
	}
	echo "Profile creation, reuse, rename, guarded saves and Library integration passed.\n";
} finally {
	wp_cache_delete( SubjectProfiles::OPTION, 'options' );
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'notoptions', 'options' );
}
