<?php
/**
 * Skill provenance, reuse, archiving, review readiness and course skill suggestions against local WordPress.
 *
 * Run with OHMYLMS_STRUCTURE_INTEGRATION=1. A stub skill source replaces the AI, so no AI call is made.
 * Temporary courses, skills, runs and suggestions are removed afterwards.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STRUCTURE_INTEGRATION' ) ) {
	exit( "Set OHMYLMS_STRUCTURE_INTEGRATION=1 for local skill provenance checks.\n" );
}
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Curriculum\AiAnalysis;
use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Skills\Provenance;
use OhMyLMS\Skills\SkillSource;
use OhMyLMS\Skills\SkillSources;
use OhMyLMS\Skills\SkillUsage;
use OhMyLMS\Skills\SkillWriter;
use OhMyLMS\Skills\Taxonomy;

/**
 * Assert an integration result.
 *
 * @param bool   $condition Expected result.
 * @param string $message   Failure reason.
 * @throws RuntimeException On failure.
 */
function provenance_check( $condition, $message ) {
	global $provenance_checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI diagnostic.
	}
	++$provenance_checks;
}

/**
 * Call a REST route.
 *
 * @param string $method HTTP method.
 * @param string $route  Route below /ohmylms/v1/.
 * @param array  $data   Body or query.
 * @return WP_REST_Response
 */
function provenance_api( $method, $route, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

// Stub source: proposes one library skill to reuse and one new skill, without calling AI.
$provenance_source  = new class() implements SkillSource {

	/**
	 * Library skill to propose for reuse.
	 *
	 * @var int
	 */
	public $existing = 0;

	/**
	 * Requests received.
	 *
	 * @var array[]
	 */
	public $requests = array();

	/**
	 * Source id.
	 *
	 * @return string
	 */
	public function id() {
		return 'provenance-test';
	}

	/**
	 * Source name.
	 *
	 * @return string
	 */
	public function label() {
		return 'Provenance test';
	}

	/**
	 * Always available.
	 *
	 * @return bool
	 */
	public function available() {
		return true;
	}

	/**
	 * Fixed proposals.
	 *
	 * @param array $request Request.
	 * @return array
	 */
	public function propose( array $request ) {
		$this->requests[] = $request;
		return array(
			'proposals' => array(
				array(
					'existing'   => $this->existing,
					'confidence' => 0.9,
					'reason'     => 'Already in the library.',
				),
				array(
					'existing'    => 0,
					'name'        => 'Provenance test skill ' . getmypid(),
					'description' => 'Proposed by the stub source.',
					'topic'       => 'Testing',
					'domain'      => '',
					'grade_from'  => 2,
					'grade_to'    => 3,
					'confidence'  => 0.8,
					'reason'      => 'New in this lesson.',
				),
			),
			'meta'      => array(
				'provider' => 'stub',
				'model'    => 'stub-model',
				'prompt'   => 'course-test',
			),
		);
	}
};
$provenance_checks  = 0;
$provenance_terms   = array();
$provenance_posts   = array();
$provenance_runs    = array();
$provenance_subject = 'Provenance test subject ' . getmypid();
$provenance_admin   = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
)[0]->ID;
add_filter(
	'ohmylms_skill_sources',
	static function ( $sources ) use ( $provenance_source ) {
		$sources[] = $provenance_source;
		return $sources;
	}
);
SkillSources::flush();
try {
	wp_set_current_user( $provenance_admin );

	// Provenance is recorded on every new skill.
	$provenance_manual  = Comparison::save_shared(
		0,
		array(
			'name'    => 'Identify the main idea of a text',
			'subject' => $provenance_subject,
		)
	);
	$provenance_terms[] = (int) $provenance_manual['id'];
	$provenance_read    = Provenance::read( (int) $provenance_manual['id'] );
	provenance_check( 'manual' === $provenance_read['source'] && 'approved' === $provenance_read['review'] && 'active' === $provenance_read['status'] && (int) $provenance_admin === (int) $provenance_read['created_by'], 'A skill made by a person is manual and approved: ' . wp_json_encode( $provenance_read ) );
	provenance_check( 'manual' === $provenance_manual['provenance']['source'], 'Shared skills report their provenance' );
	$provenance_syllabus = Provenance::with(
		array(
			'source' => 'syllabus',
			'ref'    => 'syllabus:1',
		),
		static function () {
			return wp_insert_term( 'Provenance syllabus objective ' . getmypid(), Taxonomy::NAME );
		}
	);
	$provenance_terms[]  = (int) $provenance_syllabus['term_id'];
	provenance_check( 'syllabus' === Provenance::read( (int) $provenance_syllabus['term_id'] )['source'] && 'syllabus:1' === Provenance::read( (int) $provenance_syllabus['term_id'] )['ref'], 'A declared origin is recorded' );
	$provenance_rest    = provenance_api(
		'POST',
		'skills',
		array( 'name' => 'Provenance REST skill ' . getmypid() )
	);
	$provenance_terms[] = (int) $provenance_rest->get_data()['id'];
	provenance_check( 'manual' === Provenance::read( (int) $provenance_rest->get_data()['id'] )['source'], 'A skill created without a declared origin is manual' );

	// The writer reuses a skill of the same subject whose name means the same.
	$provenance_reused = SkillWriter::shared(
		array(
			'name'    => 'identifying the main ideas of texts',
			'subject' => $provenance_subject,
		),
		array( 'source' => 'ai' )
	);
	provenance_check( true === $provenance_reused['reused'] && (int) $provenance_manual['id'] === $provenance_reused['id'], 'A near copy reuses the existing skill: ' . wp_json_encode( $provenance_reused ) );
	$provenance_other   = SkillWriter::shared(
		array(
			'name'    => 'Identify the main idea of a text',
			'subject' => $provenance_subject . ' other',
		),
		array(
			'source'     => 'ai',
			'review'     => 'pending',
			'model'      => 'test-model',
			'confidence' => 0.7,
		)
	);
	$provenance_terms[] = (int) $provenance_other['id'];
	$provenance_ai      = Provenance::read( (int) $provenance_other['id'] );
	provenance_check( false === $provenance_other['reused'] && 'ai' === $provenance_ai['source'] && 'pending' === $provenance_ai['review'] && 'test-model' === $provenance_ai['model'] && 0.7 === (float) $provenance_ai['confidence'], 'Another subject gets its own skill, with its AI origin' );

	// A course draft that uses a skill keeps it: deleting archives it instead.
	$provenance_course              = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_title'  => 'Provenance fixture course',
			'post_status' => 'draft',
		)
	);
	$provenance_posts[]             = $provenance_course;
	$provenance_unit                = Catalog::add_chapter( $provenance_course, 'Reading' )['id'];
	$provenance_lesson              = wp_generate_uuid4();
	$provenance_program             = CourseProgram::defaults( $provenance_course );
	$provenance_program['groups']   = array(
		array(
			'id'         => $provenance_lesson,
			'chapter_id' => $provenance_unit,
			'title'      => 'Skimming',
		),
	);
	$provenance_program['outcomes'] = array(
		array(
			'term_id'    => (int) $provenance_other['id'],
			'target'     => 'proficient',
			'required'   => false,
			'chapter_id' => $provenance_unit,
			'group_id'   => $provenance_lesson,
		),
	);
	update_post_meta( $provenance_course, CourseProgram::DRAFT, $provenance_program );
	$provenance_usage = SkillUsage::of( (int) $provenance_other['id'] );
	provenance_check( array( $provenance_course ) === $provenance_usage['drafts'] && SkillUsage::in_courses( $provenance_usage ), 'A draft outline counts as use' );
	provenance_check(
		in_array( sprintf( 'Approve the proposed skill %s in the skill library first.', 'Identify the main idea of a text' ), CourseProgram::readiness_report( $provenance_program, $provenance_course )['completion'], true ),
		'A pending skill blocks publication'
	);
	$provenance_deleted = provenance_api( 'DELETE', 'skills/' . (int) $provenance_other['id'] );
	provenance_check( 200 === $provenance_deleted->get_status() && true === $provenance_deleted->get_data()['archived'] && term_exists( (int) $provenance_other['id'], Taxonomy::NAME ), 'Deleting a skill a course uses archives it: ' . wp_json_encode( $provenance_deleted->get_data() ) );
	provenance_check( 'archived' === Provenance::read( (int) $provenance_other['id'] )['status'] && ! isset( AiAnalysis::catalog( $provenance_subject . ' other' )[ (int) $provenance_other['id'] ] ), 'Archived skills are kept but not offered for new matches' );
	provenance_check( 200 === provenance_api( 'POST', 'skills/' . (int) $provenance_other['id'] . '/restore' )->get_status() && 'active' === Provenance::read( (int) $provenance_other['id'] )['status'], 'An archived skill can be restored' );
	$provenance_approved = provenance_api( 'POST', 'skills/' . (int) $provenance_other['id'] . '/review', array( 'review' => 'approved' ) );
	provenance_check( 'approved' === $provenance_approved->get_data()['review'] && (int) $provenance_admin === (int) $provenance_approved->get_data()['reviewed_by'], 'An administrator approves a skill' );
	provenance_check( array() === CourseProgram::review_readiness( $provenance_program ), 'An approved skill no longer blocks publication' );
	provenance_api( 'POST', 'skills/' . (int) $provenance_other['id'] . '/review', array( 'review' => 'rejected' ) );
	provenance_check( array( sprintf( 'Replace the rejected skill %s.', 'Identify the main idea of a text' ) ) === CourseProgram::review_readiness( $provenance_program ), 'A rejected skill must be replaced' );
	$provenance_unused = provenance_api( 'DELETE', 'skills/' . (int) $provenance_syllabus['term_id'] );
	provenance_check( true === ( $provenance_unused->get_data()['deleted'] ?? false ) && ! term_exists( (int) $provenance_syllabus['term_id'], Taxonomy::NAME ), 'An unused skill is still deleted' );

	// Course skill suggestions from a registered source.
	$provenance_source->existing = (int) $provenance_manual['id'];
	$provenance_sources          = provenance_api( 'GET', 'skill-sources' )->get_data();
	provenance_check( in_array( 'provenance-test', array_column( $provenance_sources, 'id' ), true ) && in_array( 'course-content', array_column( $provenance_sources, 'id' ), true ), 'Registered and built-in sources are listed' );
	$provenance_suggested = provenance_api(
		'POST',
		'courses/' . $provenance_course . '/structure/skill-suggestions',
		array(
			'unit_id'  => $provenance_unit,
			'group_id' => $provenance_lesson,
			'subject'  => $provenance_subject,
			'source'   => 'provenance-test',
		)
	);
	if ( 503 === $provenance_suggested->get_status() && ! AiAnalysis::available() ) {
		echo "Course suggestions need a configured AI connection; skipped.\n";
	} else {
		provenance_check( 200 === $provenance_suggested->get_status(), 'Suggestions run: ' . wp_json_encode( $provenance_suggested->get_data() ) );
		$provenance_run    = $provenance_suggested->get_data()['run'];
		$provenance_runs[] = (int) $provenance_run['id'];
		provenance_check( 'done' === $provenance_run['status'] && 'course' === $provenance_run['kind'] && 2 === count( $provenance_suggested->get_data()['suggestions'] ), 'The run finishes at once with two suggestions' );
		provenance_check( $provenance_unit === (int) $provenance_source->requests[0]['unit_id'] && $provenance_lesson === $provenance_source->requests[0]['group_id'] && $provenance_subject === $provenance_source->requests[0]['subject'], 'The source receives the lesson and subject' );
		$provenance_listed = provenance_api( 'GET', 'courses/' . $provenance_course . '/structure/skill-suggestions' )->get_data();
		provenance_check( 2 === $provenance_listed['total'] && $provenance_lesson === $provenance_listed['items'][0]['target']['group_id'], 'The course lists its pending suggestions with their lesson' );
		$provenance_ids    = array_column( $provenance_listed['items'], 'id' );
		$provenance_review = provenance_api(
			'POST',
			'courses/' . $provenance_course . '/structure/skill-suggestions/review',
			array(
				'ids'    => $provenance_ids,
				'action' => 'accept',
			)
		)->get_data();
		$provenance_result = array();
		foreach ( $provenance_review as $provenance_row ) {
			$provenance_result[] = (int) $provenance_row['result_id'];
		}
		provenance_check( in_array( (int) $provenance_manual['id'], $provenance_result, true ), 'Accepting a reuse suggestion returns the library skill' );
		$provenance_new     = array_values( array_diff( $provenance_result, array( (int) $provenance_manual['id'] ) ) )[0];
		$provenance_terms[] = $provenance_new;
		$provenance_origin  = Provenance::read( $provenance_new );
		provenance_check( 'provenance-test' === $provenance_origin['source'] && 'pending' === $provenance_origin['review'] && 'stub-model' === $provenance_origin['model'] && 'course-test' === $provenance_origin['prompt'] && 0 === strpos( $provenance_origin['ref'], 'run:' . $provenance_run['id'] . '/suggestion:' ), 'A new course skill records its source and waits for review: ' . wp_json_encode( $provenance_origin ) );
		$provenance_created = Comparison::shared_skill( $provenance_new );
		provenance_check( $provenance_created['subject'] === $provenance_subject && 2 === $provenance_created['grade_from'], 'The new skill belongs to the course subject with its levels' );
		$provenance_foreign = wp_insert_post(
			array(
				'post_type'   => OHMYLMS_COURSE_CPT,
				'post_title'  => 'Provenance other course',
				'post_status' => 'draft',
			)
		);
		$provenance_posts[] = $provenance_foreign;
		provenance_check(
			array() === provenance_api(
				'POST',
				'courses/' . $provenance_foreign . '/structure/skill-suggestions/review',
				array(
					'ids'    => $provenance_ids,
					'action' => 'reject',
				)
			)->get_data(),
			'Another course cannot review these suggestions'
		);
	}

	// Backfill marks skills from before provenance existed.
	$provenance_old     = wp_insert_term( 'Provenance old skill ' . getmypid(), Taxonomy::NAME );
	$provenance_terms[] = (int) $provenance_old['term_id'];
	delete_term_meta( (int) $provenance_old['term_id'], Provenance::SOURCE_META );
	delete_term_meta( (int) $provenance_old['term_id'], Provenance::REVIEW_META );
	$provenance_backfill = get_option( Provenance::BACKFILL );
	delete_option( Provenance::BACKFILL );
	Provenance::maybe_backfill();
	provenance_check( 'manual' === get_term_meta( (int) $provenance_old['term_id'], Provenance::SOURCE_META, true ) && 'approved' === get_term_meta( (int) $provenance_old['term_id'], Provenance::REVIEW_META, true ), 'Backfill marks older skills as approved' );
	update_option( Provenance::BACKFILL, $provenance_backfill ? $provenance_backfill : gmdate( 'c' ), false );

	$provenance_tools = \OhMyLMS\MCP\CurriculumAiTools::definitions();
	provenance_check( isset( $provenance_tools['suggest_course_skills'], $provenance_tools['review_course_skill_suggestions'], $provenance_tools['review_skill'], $provenance_tools['archive_skill'], $provenance_tools['restore_skill'], $provenance_tools['list_skill_sources'] ), 'MCP tools are defined' );

	wp_set_current_user( 0 );
	provenance_check( 401 === provenance_api( 'POST', 'courses/' . $provenance_course . '/structure/skill-suggestions', array( 'unit_id' => $provenance_unit ) )->get_status(), 'Anonymous requests are denied' );
	echo 'PASS: ' . (int) $provenance_checks . " skill provenance integration checks.\n";
} finally {
	global $wpdb;
	wp_set_current_user( $provenance_admin );
	foreach ( $provenance_runs as $provenance_run_id ) {
		$wpdb->delete( \OhMyLMS\Curriculum\Schema::table( 'ai_suggestions' ), array( 'run_id' => $provenance_run_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture rows only.
		$wpdb->delete( \OhMyLMS\Curriculum\Schema::table( 'ai_analysis_runs' ), array( 'id' => $provenance_run_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture rows only.
	}
	foreach ( $provenance_posts as $provenance_post ) {
		foreach ( Catalog::chapters( $provenance_post ) as $provenance_chapter ) {
			wp_delete_post( $provenance_chapter['id'], true );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $provenance_post ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture rows only.
		wp_delete_post( $provenance_post, true );
	}
	foreach ( array_unique( array_filter( $provenance_terms ) ) as $provenance_term ) {
		if ( term_exists( $provenance_term, Taxonomy::NAME ) ) {
			wp_delete_term( $provenance_term, Taxonomy::NAME );
		}
	}
}
