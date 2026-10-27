<?php
/**
 * Course builder improvements rehearsal: blank draft discard, readiness report, skill paging. All fixture
 * writes are rolled back.
 *
 * Run with OHMYLMS_TEST_CREDENTIALS on the disposable test site.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Learning\BlankDraft;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Utility\Transaction;

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
$site = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true )['site']; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI-local credentials file, never a URL.
$_SERVER['HTTP_HOST']   = '800.local';
$_SERVER['REQUEST_URI'] = '/';
require $site . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || 'ohmylms_source_test' !== DB_NAME ) {
	throw new RuntimeException( 'Requires the disposable test site.' );
}
if ( ! Schema::ready() || wp_using_ext_object_cache() ) {
	throw new RuntimeException( 'Requires installed assessment schema and request-local object cache.' );
}
foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read current engines to guarantee fixture rollback.
	if ( 0 === strpos( $table['Name'], $wpdb->prefix ) && 'InnoDB' !== $table['Engine'] ) {
		throw new RuntimeException( 'Requires InnoDB tables.' );
	}
}
$checks      = 0;
$check       = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition || is_wp_error( $condition ) ) {
		throw new RuntimeException( esc_html( $message . ( is_wp_error( $condition ) ? ': ' . $condition->get_error_message() : '' ) ) );
	}
	++$checks;
};
$request     = static function ( $method, $path, $data = array() ) use ( $check ) {
	$req = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	$req->set_body_params( $data );
	$res = rest_do_request( $req );
	$check( $res->get_status() < 300, $path . ': ' . wp_json_encode( $res->get_data() ) );
	return $res->get_data();
};
$fixture_tag = 'course-ux-' . wp_generate_uuid4();
try {
	Transaction::run(
		static function () use ( $check, $request, $fixture_tag ) {
			$admin = wp_insert_user(
				array(
					'user_login' => $fixture_tag,
					'user_pass'  => wp_generate_password(),
					'role'       => 'administrator',
				)
			);
			$check( $admin, 'Create fixture administrator' );
			wp_set_current_user( $admin );

			// An untouched course is removed again; one with any work in it is not.
			$blank = $request(
				'POST',
				'courses',
				array(
					'name'   => 'Untitled Course',
					'status' => 'draft',
				)
			)['id'];
			$check( BlankDraft::is_blank( $blank ), 'A new empty course is blank' );
			$check( true === BlankDraft::discard( $blank ) && ! get_post( $blank ), 'A blank course is discarded' );

			$kept = $request(
				'POST',
				'courses',
				array(
					'name'   => 'Untitled Course',
					'status' => 'draft',
				)
			)['id'];
			wp_update_post(
				array(
					'ID'           => $kept,
					'post_content' => 'A real description',
				)
			);
			$check( ! BlankDraft::is_blank( $kept ) && false === BlankDraft::discard( $kept ) && get_post( $kept ), 'A course with a description is kept' );
			$titled = $request(
				'POST',
				'courses',
				array(
					'name'   => 'Algebra 1',
					'status' => 'draft',
				)
			)['id'];
			$check( ! BlankDraft::is_blank( $titled ), 'A named course is kept' );
			$units = Catalog::chapters( $titled );
			$check( $units && is_int( (int) $units[0]['id'] ), 'A new course has its first unit' );
			$guest_course = $request(
				'POST',
				'courses',
				array(
					'name'   => 'Untitled Course',
					'status' => 'draft',
				)
			)['id'];
			wp_set_current_user( 0 );
			$check( false === BlankDraft::discard( $guest_course ) && get_post( $guest_course ), 'Nobody without the right can discard a course' );
			wp_set_current_user( $admin );

			// The readiness report says what is missing, per skill.
			$skill  = (int) $request( 'POST', 'skills', array( 'name' => $fixture_tag . 'skill' ) )['id'];
			$quiz   = (int) $request(
				'POST',
				'quiz',
				array(
					'name'   => $fixture_tag . 'quiz',
					'status' => 'draft',
				)
			)['id'];
			$chapter = (int) $units[0]['id'];
			$program = CourseProgram::editor( $titled )['draft'];
			$program['mode']     = 'skill-based';
			$program['outcomes'] = array(
				array(
					'term_id'    => $skill,
					'target'     => 'proficient',
					'required'   => true,
					'chapter_id' => $chapter,
				),
			);
			$program['items']    = array(
				array(
					'id'         => wp_generate_uuid4(),
					'type'       => 'quiz',
					'content_id' => $quiz,
					'chapter_id' => $chapter,
					'skill_ids'  => array( $skill ),
					'required'   => true,
				),
			);
			$report = CourseProgram::readiness_report( $program, $titled );
			$check( false === $report['ready'], 'A course with an unpublished quiz and a thin skill is not ready' );
			$check( 1 === count( $report['resources'] ) && $quiz === $report['resources'][0]['content_id'], 'The unpublished quiz is listed' );
			$check( 1 === count( $report['skills'] ) && false === $report['skills'][0]['ready'] && 0 === $report['skills'][0]['questions'] && $report['skills'][0]['questions_need'] >= 1 && $report['skills'][0]['families_need'] >= 1, 'The skill shows what it has and what it needs' );
			$program['outcomes'][0]['required'] = false;
			$program['items'][0]['required']    = false;
			wp_update_post(
				array(
					'ID'          => $quiz,
					'post_status' => 'publish',
				)
			);
			$loose = CourseProgram::readiness_report( $program, $titled );
			$check( array() === $loose['skills'] && array() === $loose['resources'], 'Optional skills and published resources are not listed' );

			// Skill choices page instead of stopping at forty.
			for ( $i = 1; $i <= 43; $i++ ) {
				wp_insert_term( sprintf( '%s paged %02d', $fixture_tag, $i ), \OhMyLMS\Skills\Taxonomy::NAME );
			}
			$first  = Catalog::targets( 'skill', $fixture_tag . ' paged', 0, 1 );
			$second = Catalog::targets( 'skill', $fixture_tag . ' paged', 0, 2 );
			$check( 40 === count( $first['items'] ) && 2 === $first['pages'] && 43 === $first['total'], 'The first page holds forty skills and says there are two pages' );
			$check( 3 === count( $second['items'] ) && ! array_intersect( array_column( $first['items'], 'id' ), array_column( $second['items'], 'id' ) ), 'The second page holds the rest, with no repeats' );
			throw new RuntimeException( 'rehearsal_rollback_success' );
		}
	);
} catch ( RuntimeException $error ) {
	if ( 'rehearsal_rollback_success' !== $error->getMessage() ) {
		throw $error;
	}
} finally {
	wp_set_current_user( 0 );
}
$check( ! $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login=%s", $fixture_tag ) ), 'Fixture transaction rolled back' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify rollback against the actual database.
echo (int) $checks . " course builder checks passed; fixture writes rolled back.\n";
