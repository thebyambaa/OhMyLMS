<?php
/**
 * Real WordPress import rehearsal. All fixture writes are rolled back.
 *
 * Run with OHMYLMS_TEST_CREDENTIALS, or pass --local for this checkout's Local site.
 * Requires mysqli and mbstring; Local's mysqli.default_port must match its MySQL port.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\BulkImport;
use OhMyLMS\QuestionBank\ReviewQueue;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Quiz\Review;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Utility\Transaction;

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
$local = in_array( '--local', $argv, true );
$site  = $local ? dirname( __DIR__, 5 ) : json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true )['site']; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI-local credentials file, never a URL.
if ( $local ) {
	define( 'DISABLE_WP_CRON', true );
}
$_SERVER['HTTP_HOST']   = '800.local';
$_SERVER['REQUEST_URI'] = '/';
require $site . '/wp-load.php';
if ( $local ? ( 'xyz' !== basename( dirname( ABSPATH, 2 ) ) || 'local' !== wp_get_environment_type() ) : ( ! defined( 'OHMYLMS_TEST_SITE' ) || 'ohmylms_source_test' !== DB_NAME ) ) {
	throw new RuntimeException( 'Requires the explicit Local checkout or disposable test site.' );
}
if ( ! Schema::ready() || wp_using_ext_object_cache() ) {
	throw new RuntimeException( 'Requires installed assessment schema and request-local object cache.' );
}
// A rollback rehearsal cannot safely operate on nontransactional plugin tables.
foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read current engines to guarantee fixture rollback; cached state is unsafe.
	if ( 0 === strpos( $table['Name'], $wpdb->prefix ) && 'InnoDB' !== $table['Engine'] ) {
		throw new RuntimeException( 'Requires InnoDB tables.' );
	}
}
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
add_filter(
	'pre_http_request',
	static function () {
		return new WP_Error( 'rehearsal', 'Network disabled.' );
	},
	PHP_INT_MAX
);
// A CLI fixture must never flush a commerce session after its transaction rolls back.
foreach ( $GLOBALS['wp_filter']['shutdown']->callbacks ?? array() as $priority => $callbacks ) {
	foreach ( $callbacks as $callback ) {
		$function = $callback['function'];
		if ( is_array( $function ) && is_object( $function[0] ) && $function[0] instanceof \OhMyLMS\Commerce\SessionHandler ) {
			remove_action( 'shutdown', $function, $priority );
		}
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
$fixture_tag = 'exam-rehearsal-' . wp_generate_uuid4();
try {
	Transaction::run(
		static function () use ( $check, $request, $fixture_tag ) {
			global $wpdb;
			$admin = wp_insert_user(
				array(
					'user_login' => $fixture_tag,
					'user_pass'  => wp_generate_password(),
					'role'       => 'administrator',
				)
			);
			$check( $admin, 'Create fixture administrator' );
			wp_set_current_user( $admin );
			$skills = array();
			foreach ( array( 'area', 'reasoning' ) as $name ) {
				$skill    = $request( 'POST', 'skills', array( 'name' => $fixture_tag . $name ) );
				$skills[] = (int) $skill['id'];
			}
			$row = array(
				'exam'     => $fixture_tag,
				'paper'    => 'Synthetic rectangle',
				'number'   => '1',
				'type'     => 'structured',
				'question' => 'A rectangle measures 4 by 3.',
				'skills'   => '#' . $skills[0],
				'parts'    => array(
					array(
						'id'     => 'a_i',
						'kind'   => 'numerical',
						'marks'  => 2,
						'answer' => 12,
					),
					array(
						'id'     => 'b',
						'kind'   => 'written',
						'marks'  => 1,
						'rubric' => 'PRIVATE multiply length by width',
						'skills' => '#' . $skills[1],
					),
				),
			);
			$run = static function ( $row, $options = array() ) use ( $check ) {
				$result = BulkImport::run( array( $row ), $options );
				$check( ! is_wp_error( $result ) && 0 === $result['counts']['error'], 'Import: ' . wp_json_encode( $result ) );
				return $result;
			};
			$dry = $run( $row );
			$check( ! $dry['committed'] && 1 === $dry['counts']['create'], 'Dry run writes nothing' );
			$saved = $run(
				$row,
				array(
					'dry_run' => false,
					'review'  => true,
					'approve' => true,
				)
			);
			$id    = $saved['rows'][0]['id'];
			$first = VersionPublisher::identity( $id );
			$check( ! $first['approved_version_id'] && ReviewQueue::PENDING === get_post_meta( $id, ReviewQueue::STATUS_META, true ), 'AI review cannot auto-approve' );
			$check( true === ReviewQueue::approve( $id ), 'Human approval' );
			$repeat = $run( $row, array( 'dry_run' => false ) );
			$check( 1 === $repeat['counts']['unchanged'] && (int) VersionPublisher::identity( $id )['current_version_id'] === (int) $first['current_version_id'], 'Repeat import reuses version and question' );
			$course  = $request(
				'POST',
				'courses',
				array(
					'title'  => $fixture_tag,
					'status' => 'publish',
				)
			)['id'];
			$quiz    = $request(
				'POST',
				'quiz',
				array(
					'name'     => $fixture_tag,
					'status'   => 'publish',
					'settings' => array( 'allow_attempts' => 5 ),
				)
			)['id'];
			$chapter = wp_insert_post(
				array(
					'post_type'   => 'ohmylms-chapter',
					'post_title'  => $fixture_tag,
					'post_status' => 'publish',
				)
			);
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transactional fixture relationship, rolled back after rehearsal.
				$wpdb->prefix . 'ohmylms_chapter_relationship',
				array(
					'course_id'    => $course,
					'chapter_id'   => $chapter,
					'order_number' => 0,
				)
			);
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transactional fixture relationship, rolled back after rehearsal.
				$wpdb->prefix . 'ohmylms_content_relationship',
				array(
					'chapter_id'   => $chapter,
					'content_id'   => $quiz,
					'content_type' => 'quiz',
					'order_number' => 0,
				)
			);
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transactional fixture relationship, rolled back after rehearsal.
				$wpdb->prefix . 'ohmylms_quiz_questions_relationship',
				array(
					'quiz_id'      => $quiz,
					'question_id'  => $id,
					'order_number' => 0,
				)
			);
			$student = wp_insert_user(
				array(
					'user_login' => $fixture_tag . '-student',
					'user_pass'  => wp_generate_password(),
					'role'       => 'subscriber',
				)
			);
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transactional fixture relationship, rolled back after rehearsal.
				$wpdb->prefix . 'ohmylms_user_enrollment',
				array(
					'user_id'    => $student,
					'course_id'  => $course,
					'status'     => 'enrolled',
					'progress'   => 'running',
					'start_date' => current_time( 'mysql' ),
				)
			);
			wp_set_current_user( $student );
			$attempt = Submission::start( $quiz, $student );
			$check( $attempt, 'Start imported question' );
			$delivery = wp_json_encode( AttemptItems::delivery( $attempt ) );
			$check( false === strpos( $delivery, 'PRIVATE' ) && false === strpos( $delivery, '"answer":12' ), 'Learner projection hides answer/rubric' );
			wp_set_current_user( $admin );
			$row['parts'][0]['answer'] = 99;
			$run( $row, array( 'dry_run' => false ) );
			$changed = VersionPublisher::identity( $id );
			$check( (int) $first['current_version_id'] !== (int) $changed['current_version_id'] && (int) $first['current_version_id'] === (int) $changed['approved_version_id'], 'Edit creates draft version, preserves approval' );
			$check( wp_json_encode( AttemptItems::delivery( $attempt ) ) === $delivery, 'Attempt remains frozen after reimport' );
			wp_set_current_user( $student );
			$result = Submission::submit(
				$quiz,
				$attempt,
				$student,
				array(
					$id => array(
						'a_i' => '12',
						'b'   => 'Multiply the sides.',
					),
				)
			);
			$check( ! is_wp_error( $result ) && 'in-review' === $result['status'] && 2.0 === (float) $result['total'], 'Frozen answer awards two marks; written part awaits review' );
			wp_set_current_user( $admin );
			$check( Review::save( $quiz, $attempt, array( $id => array( 'b' => 1 ) ), 'Synthetic rehearsal' ), 'Teacher marks written part' );
			Evidence::process();
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT term_id, part_id, awarded, available FROM %i WHERE student_id=%d AND question_id=%d AND superseded=0 AND role=%s ORDER BY part_id', Schema::table( 'skill_evidence' ), $student, $id, 'primary' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify actual persisted fixture evidence without cache.
			$check( 2 === count( $rows ) && 'a_i' === $rows[0]['part_id'] && (int) $rows[0]['term_id'] === $skills[0] && 2.0 === (float) $rows[0]['awarded'] && 2.0 === (float) $rows[0]['available'] && 'b' === $rows[1]['part_id'] && (int) $rows[1]['term_id'] === $skills[1] && 1.0 === (float) $rows[1]['awarded'], 'Part evidence follows frozen skill mappings and marks' );
			$check( 0 === Evidence::process(), 'Evidence processing is idempotent' );
			throw new RuntimeException( 'rehearsal_rollback_success' );
		}
	);
} catch ( RuntimeException $error ) {
	if ( 'rehearsal_rollback_success' !== $error->getMessage() ) {
		throw $error;
	}
} finally {
	wp_set_current_user( 0 );
	// Grading schedules evidence at shutdown. Process it inside the fixture transaction only.
	foreach ( $GLOBALS['wp_filter']['shutdown']->callbacks ?? array() as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$function = $callback['function'];
			if ( $function instanceof Closure && realpath( ( new ReflectionFunction( $function ) )->getFileName() ) === realpath( dirname( __DIR__, 2 ) . '/includes/Skills/Evidence.php' ) ) {
				remove_action( 'shutdown', $function, $priority );
			}
		}
	}
}
$check( ! $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login=%s", $fixture_tag ) ), 'Fixture transaction rolled back' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify rollback against the actual database.
echo (int) $checks . " exam import integration checks passed; fixture writes rolled back.\n";
