<?php
/**
 * A parent's home-learning report shows skill levels from home quizzes only, under the current link.
 * Every fixture is rolled back.
 *
 * @package OhMyLMS
 */

$family_skills_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $family_skills_site || ! is_file( $family_skills_site . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set OHMYLMS_TEST_SITE_PATH to a local WordPress installation.' );
}
require $family_skills_site . '/wp-load.php';

use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Family\Reports;
use OhMyLMS\Family\Schema;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Utility\Transaction;

$GLOBALS['family_skills_checks'] = 0;

/**
 * Assert a contract.
 *
 * @param bool   $condition Expected result.
 * @param string $message   Failure description.
 * @throws RuntimeException When the contract fails.
 */
function family_skills_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$GLOBALS['family_skills_checks'];
}

/**
 * Insert a row and return its ID.
 *
 * @param string $table Table.
 * @param array  $row   Row.
 * @return int ID.
 */
function family_skills_insert( $table, array $row ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture rows inside the rolled-back transaction.
	family_skills_check( false !== $wpdb->insert( $table, $row ), 'Fixture insert failed in ' . $table . ': ' . $wpdb->last_error );
	return (int) $wpdb->insert_id;
}

/**
 * A finished quiz attempt with the level event it produced.
 *
 * @param int    $learner  Learner.
 * @param int    $attempt  Attempt ID.
 * @param string $origin   Activity origin.
 * @param int    $term     Skill.
 * @param bool   $right    Whether every question on the skill was right.
 * @param string $at       Completion time (UTC).
 */
function family_skills_attempt( $learner, $attempt, $origin, $term, $right, $at ) {
	family_skills_insert(
		Schema::table( 'activity' ),
		array(
			'learner_user_id' => $learner,
			'source_kind'     => 'quiz',
			'source_id'       => $attempt,
			'origin'          => $origin,
			'created_at'      => $at,
			'completed_at'    => $at,
		)
	);
	family_skills_insert(
		AssessmentSchema::table( 'skill_level_events' ),
		array(
			'student_id'   => $learner,
			'term_id'      => $term,
			'source_type'  => 'quiz',
			'source_id'    => $attempt,
			'level_before' => 'not-started',
			'level_after'  => 'attempted',
			'detail'       => wp_json_encode(
				array(
					'right' => $right ? 1 : 0,
					'wrong' => $right ? 0 : 1,
					'total' => 1,
				)
			),
			'created_at'   => $at,
		)
	);
}

try {
	Transaction::run(
		static function () {
			family_skills_check( AssessmentSchema::ready() && Schema::enabled(), 'Family reporting storage is unavailable.' );
			$user     = static function ( $prefix, $role ) {
				$id = wp_insert_user(
					array(
						'user_login'   => $prefix . '-' . wp_generate_password( 8, false ),
						'user_pass'    => wp_generate_password( 24 ),
						'display_name' => ucfirst( $prefix ) . ' fixture',
						'role'         => $role,
					)
				);
				family_skills_check( ! is_wp_error( $id ), 'User fixture failed.' );
				return (int) $id;
			};
			$guardian = $user( 'guardian', 'ohmylms_parent' );
			$other    = $user( 'otherparent', 'ohmylms_parent' );
			$learner  = $user( 'child', 'subscriber' );
			$linked   = gmdate( 'Y-m-d H:i:s', time() - 20 * DAY_IN_SECONDS );
			family_skills_insert(
				Schema::table( 'links' ),
				array(
					'guardian_user_id' => $guardian,
					'learner_user_id'  => $learner,
					'scope'            => Schema::SCOPE,
					'status'           => 'active',
					'created_at'       => $linked,
					'updated_at'       => $linked,
				)
			);
			$fractions = wp_insert_term( 'Fractions home ' . wp_generate_password( 4, false ), Taxonomy::NAME );
			$school    = wp_insert_term( 'School only ' . wp_generate_password( 4, false ), Taxonomy::NAME );
			$early     = wp_insert_term( 'Before link ' . wp_generate_password( 4, false ), Taxonomy::NAME );
			family_skills_check( ! is_wp_error( $fractions ) && ! is_wp_error( $school ) && ! is_wp_error( $early ), 'Skill fixtures failed.' );
			$recent = gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS );
			// Two right home quizzes on one skill: Not started → Familiar → Proficient.
			family_skills_attempt( $learner, 910000001, Schema::SCOPE, (int) $fractions['term_id'], true, $recent );
			family_skills_attempt( $learner, 910000002, Schema::SCOPE, (int) $fractions['term_id'], true, $recent );
			// A school quiz and a home quiz from before the link must stay out.
			family_skills_attempt( $learner, 910000003, 'school', (int) $school['term_id'], true, $recent );
			family_skills_attempt( $learner, 910000004, Schema::SCOPE, (int) $early['term_id'], true, gmdate( 'Y-m-d H:i:s', time() - 25 * DAY_IN_SECONDS ) );

			wp_set_current_user( $guardian );
			$report = Reports::read( $learner, 30, 1 );
			$skills = $report['skills']['skills'];
			family_skills_check( 1 === count( $skills ), 'The report should show exactly the one home-quiz skill.' );
			$skill = $skills[ (int) $fractions['term_id'] ] ?? null;
			family_skills_check( null !== $skill && 'proficient' === $skill['level'] && 2 === $skill['assessments'], 'Two right home quizzes did not give Proficient from two quizzes.' );
			family_skills_check( ! isset( $skills[ (int) $school['term_id'] ] ), 'School work reached the parent report.' );
			family_skills_check( ! isset( $skills[ (int) $early['term_id'] ] ), 'Work from before the link reached the parent report.' );

			// The 7-day window still holds the recent quizzes.
			family_skills_check( 1 === count( Reports::read( $learner, 7, 1 )['skills']['skills'] ), 'The 7-day period lost recent quizzes.' );

			// The report renders the level in the skill matrix style.
			set_query_var( 'hub_child', $learner );
			ob_start();
			ohmylms_get_template( 'student-hub/family-report.php' );
			$html = (string) ob_get_clean();
			family_skills_check( false !== strpos( $html, 'ohmylms-mx-cell ohmylms-mx-proficient' ), 'The report does not show the level cell.' );
			family_skills_check( false === strpos( $html, esc_html( get_term( $school['term_id'] )->name ) ), 'The rendered report names a school-only skill.' );

			// Another parent, and a revoked link, see nothing.
			wp_set_current_user( $other );
			$denied = false;
			try {
				Reports::read( $learner, 30, 1 );
			} catch ( RuntimeException $error ) {
				$denied = true;
			}
			family_skills_check( $denied, 'Another parent could read the child’s report.' );
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Revoke the fixture link inside the transaction.
			$wpdb->update( Schema::table( 'links' ), array( 'status' => 'revoked' ), array( 'learner_user_id' => $learner ) );
			wp_set_current_user( $guardian );
			$revoked = false;
			try {
				Reports::read( $learner, 30, 1 );
			} catch ( RuntimeException $error ) {
				$revoked = true;
			}
			family_skills_check( $revoked, 'A revoked link still showed the report.' );

			throw new RuntimeException( 'OHMYLMS_FAMILY_SKILLS_TEST_SUCCESS' );
		}
	);
} catch ( Throwable $failure ) {
	if ( 'OHMYLMS_FAMILY_SKILLS_TEST_SUCCESS' === $failure->getMessage() ) {
		echo 'Family skills: ' . (int) $GLOBALS['family_skills_checks'] . " checks passed; fixtures rolled back.\n";
	} else {
		echo esc_html( $failure->getMessage() ) . "\n";
		exit( 1 );
	}
}
