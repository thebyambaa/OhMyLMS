<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * AI analysis of syllabuses and shared skills, with every suggestion held for review.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\Skills\Provenance;
use OhMyLMS\Skills\SkillSources;
use OhMyLMS\Skills\SkillWriter;
use OhMyLMS\Skills\SubjectProfiles;
use OhMyLMS\AI\Client;
use OhMyLMS\AI\Settings as AiSettings;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads and writes the plugin's own analysis tables; every value is prepared.
/**
 * Two kinds of run:
 *
 *  - syllabus: places each objective of a syllabus in a domain and grade band, then suggests which
 *    shared skills it maps to, or proposes a new shared skill when none fits. Proposed skills are
 *    offered to later objectives of the same run, so a library grows from the first syllabuses.
 *  - placement: suggests a domain and grade band for shared skills that have none.
 *  - links: suggests the prerequisites of each placed shared skill and the simpler skills it
 *    encompasses (see SkillEdges).
 *
 * Runs advance a slice at a time (from the review screen while it is open, and from WP-Cron), so no
 * request waits on many AI calls. The AI never changes the curriculum: each suggestion waits until a
 * person accepts or rejects it, and accepting goes through the same guarded saves as the editor.
 * The objective's estimated domain, grades and English meaning are kept on the objective as hints.
 */
final class AiAnalysis {

	const KINDS        = array( 'syllabus', 'placement', 'links', 'course' );
	const HOOK         = 'ohmylms_ai_analysis_step';
	const SLICE        = array(
		'syllabus'  => 8,
		'placement' => 20,
		'links'     => 10,
		'course'    => 1,
	);
	const TRIES        = 3;
	const HINT_DOMAIN  = '_ohmylms_ai_domain';
	const HINT_FROM    = '_ohmylms_ai_grade_from';
	const HINT_TO      = '_ohmylms_ai_grade_to';
	const HINT_GLOSS   = '_ohmylms_ai_gloss';
	const SOURCE_AI    = 'ai';
	const ORDER_ACCEPT = array(
		'new_skill' => 0,
		'mapping'   => 1,
		'placement' => 2,
		'link'      => 3,
		// Course skills create or reuse skills on their own; nothing waits for them.
		'course_skill' => 0,
	);

	/** Hook the background step. */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'step' ) );
	}

	/**
	 * Whether runs can be started: the shared AI connection is set up.
	 *
	 * @return bool
	 */
	public static function available() {
		return AiSettings::connection_configured();
	}

	/**
	 * Start a run.
	 *
	 * @param array $params kind, syllabus_id (syllabus runs), subject (placement runs), include_mapped.
	 * @return array|\WP_Error The run.
	 */
	public static function start( array $params ) {
		global $wpdb;
		if ( ! Schema::ready() ) {
			return Access::error( 'ohmylms_ai_analysis_unavailable', __( 'Curriculum storage is not installed yet.', 'ohmylms' ), 503 );
		}
		if ( ! self::available() ) {
			return Access::error( 'ohmylms_ai_analysis_unconfigured', __( 'Connect an AI provider under AI settings first.', 'ohmylms' ), 503 );
		}
		$kind = (string) ( $params['kind'] ?? 'syllabus' );
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			return Access::error( 'ohmylms_ai_analysis_invalid', __( 'Choose a syllabus analysis or a placement analysis.', 'ohmylms' ) );
		}
		$target = array();
		if ( 'course' === $kind ) {
			$target = self::course_target( $params );
			if ( is_wp_error( $target ) ) {
				return $target;
			}
		}
		$syllabus = 'syllabus' === $kind ? absint( $params['syllabus_id'] ?? 0 ) : 0;
		if ( 'syllabus' === $kind && ! Syllabus::is_syllabus( $syllabus ) ) {
			return Access::error( 'ohmylms_ai_analysis_invalid', __( 'Choose an existing syllabus.', 'ohmylms' ), 404 );
		}
		$subject = 'syllabus' === $kind ? self::subject_of( $syllabus ) : sanitize_text_field( (string) ( $params['subject'] ?? '' ) );
		// Course runs cover one lesson each and finish in one step, so several may run side by side.
		$active = 'course' === $kind ? 0 : (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE kind=%s AND syllabus_id=%d AND subject=%s AND status IN (\'queued\',\'running\') LIMIT 1', self::runs_table(), $kind, $syllabus, $subject ) );
		if ( $active ) {
			return Access::error( 'ohmylms_ai_analysis_active', __( 'An analysis of this is already running. Wait for it or cancel it.', 'ohmylms' ), 409, array( 'run' => self::run( $active ) ) );
		}
		if ( 'course' === $kind ) {
			$queue = array( $target['course_id'] );
		} elseif ( 'links' === $kind ) {
			$queue = self::links_queue( $subject, ! empty( $params['include_linked'] ) );
		} else {
			$queue = 'syllabus' === $kind ? self::objective_queue( $syllabus, ! empty( $params['include_mapped'] ) ) : self::placement_queue( $subject );
		}
		if ( is_wp_error( $queue ) ) {
			return $queue;
		}
		if ( ! $queue ) {
			$empty = array(
				'syllabus'  => __( 'Every objective of this syllabus is already mapped. Include mapped objectives to analyse them again.', 'ohmylms' ),
				'placement' => __( 'Every shared skill already has a domain and grades.', 'ohmylms' ),
				'links'     => __( 'No shared skill is ready: place skills in a domain and grades first, or include skills that already have prerequisites.', 'ohmylms' ),
			);
			return Access::error( 'ohmylms_ai_analysis_empty', $empty[ $kind ] );
		}
		$now     = current_time( 'mysql', true );
		$profile = SubjectProfiles::get( $subject );
		$wpdb->insert(
			self::runs_table(),
			array(
				'uuid'        => wp_generate_uuid4(),
				'kind'        => $kind,
				'syllabus_id' => $syllabus,
				'subject'     => $subject,
				'status'      => 'queued',
				'total'       => count( $queue ),
				'model'       => (string) AiSettings::get()['model'],
				// The prompt templates and subject profile the run starts with, so its suggestions can be traced.
				'prompt'      => AiAnalysisModel::PROMPT_VERSION,
				'profile'     => $profile['key'],
				'profile_rev' => AiAnalysisModel::profile_revision( $profile ),
				'queue'       => wp_json_encode(
					array(
						'ids'    => $queue,
						'tries'  => 0,
						'target' => $target,
					)
				),
				'created_by'  => get_current_user_id(),
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( 'course' !== $kind ) {
			wp_schedule_single_event( time() + 30, self::HOOK, array( $id ) );
		}
		return self::run( $id );
	}

	/**
	 * Advance a run by one slice.
	 *
	 * @param int $run_id Run.
	 * @return array|null The run afterwards.
	 */
	public static function step( $run_id ) {
		global $wpdb;
		$run_id = (int) $run_id;
		$lock   = 'ohmylms-ai-analysis-' . md5( $wpdb->prefix ) . '-' . $run_id;
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
			return self::run( $run_id );
		}
		try {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', self::runs_table(), $run_id ), ARRAY_A );
			if ( ! $row || ! in_array( $row['status'], array( 'queued', 'running' ), true ) ) {
				return self::run( $run_id );
			}
			$queue = json_decode( (string) $row['queue'], true );
			$ids   = array_map( 'intval', (array) ( $queue['ids'] ?? array() ) );
			$slice = array_slice( $ids, 0, self::SLICE[ $row['kind'] ] ?? 8 );
			$done  = array(
				'status'     => 'running',
				'updated_at' => current_time( 'mysql', true ),
			);
			if ( $slice ) {
				$made = array(
					'syllabus'  => 'analyse_objectives',
					'placement' => 'analyse_placements',
					'links'     => 'analyse_links',
					'course'    => 'analyse_course',
				)[ $row['kind'] ];
				$made = self::$made( $row, $slice );
				if ( is_wp_error( $made ) ) {
					$tries = (int) ( $queue['tries'] ?? 0 ) + 1;
					if ( $tries >= self::TRIES ) {
						// Give up on this slice so the rest of the run can go on.
						$ids               = array_slice( $ids, count( $slice ) );
						$tries             = 0;
						$done['failures']  = (int) $row['failures'] + count( $slice );
						$done['processed'] = (int) $row['processed'] + count( $slice );
					}
					$done['error'] = $made->get_error_message();
					$queue         = array(
						'ids'   => $ids,
						'tries' => $tries,
					) + $queue;
				} else {
					$ids               = array_slice( $ids, count( $slice ) );
					$queue             = array(
						'ids'   => $ids,
						'tries' => 0,
					) + $queue;
					$done['processed'] = (int) $row['processed'] + count( $slice );
					$done['suggested'] = (int) $row['suggested'] + (int) $made;
				}
			}
			if ( ! $ids ) {
				$done['status'] = 'done';
			}
			$done['queue'] = wp_json_encode( $queue );
			$wpdb->update( self::runs_table(), $done, array( 'id' => $run_id ) );
			if ( $ids && 'course' !== $row['kind'] ) {
				wp_schedule_single_event( time() + 20, self::HOOK, array( $run_id ) );
			}
			return self::run( $run_id );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Stop a run; suggestions made so far stay for review.
	 *
	 * @param int $run_id Run.
	 * @return array|\WP_Error
	 */
	public static function cancel( $run_id ) {
		global $wpdb;
		$run = self::run( $run_id );
		if ( ! $run ) {
			return Access::error( 'ohmylms_ai_analysis_missing', __( 'Analysis not found.', 'ohmylms' ), 404 );
		}
		if ( in_array( $run['status'], array( 'queued', 'running' ), true ) ) {
			$wpdb->update(
				self::runs_table(),
				array(
					'status'     => 'cancelled',
					'updated_at' => current_time( 'mysql', true ),
				),
				array( 'id' => (int) $run_id )
			);
			wp_clear_scheduled_hook( self::HOOK, array( (int) $run_id ) );
		}
		return self::run( $run_id );
	}

	/**
	 * One run, described.
	 *
	 * @param int $run_id Run.
	 * @return array|null
	 */
	public static function run( $run_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', self::runs_table(), (int) $run_id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$counts = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM %i WHERE run_id=%d GROUP BY status', self::suggestions_table(), (int) $run_id ), ARRAY_A ) as $count ) {
			$counts[ $count['status'] ] = (int) $count['n'];
		}
		$syllabus = (int) $row['syllabus_id'] ? Items::get( (int) $row['syllabus_id'] ) : null;
		return array(
			'id'          => (int) $row['id'],
			'kind'        => $row['kind'],
			'syllabus_id' => (int) $row['syllabus_id'],
			'syllabus'    => $syllabus ? (string) $syllabus['name'] : '',
			'subject'     => $row['subject'],
			'status'      => $row['status'],
			'total'       => (int) $row['total'],
			'processed'   => (int) $row['processed'],
			'suggested'   => (int) $row['suggested'],
			'failures'    => (int) $row['failures'],
			'error'       => (string) $row['error'],
			'model'       => $row['model'],
			'prompt'      => (string) ( $row['prompt'] ?? '' ),
			'profile'     => (string) ( $row['profile'] ?? '' ),
			'profile_rev' => (string) ( $row['profile_rev'] ?? '' ),
			'pending'     => $counts['pending'] ?? 0,
			'accepted'    => $counts['accepted'] ?? 0,
			'rejected'    => $counts['rejected'] ?? 0,
			'created_at'  => $row['created_at'],
		);
	}

	/**
	 * Recent runs, newest first.
	 *
	 * @param array $params Optional syllabus_id and subject (adds the subject's placement and links runs).
	 * @return array[]
	 */
	public static function runs( array $params = array() ) {
		global $wpdb;
		$syllabus = absint( $params['syllabus_id'] ?? 0 );
		$subject  = isset( $params['subject'] ) ? sanitize_text_field( (string) $params['subject'] ) : null;
		if ( null !== $subject ) {
			// A syllabus's own runs, and the library-wide runs (placement, links) of its subject.
			$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE ( syllabus_id>0 AND syllabus_id=%d ) OR ( syllabus_id=0 AND subject=%s ) ORDER BY id DESC LIMIT 20', self::runs_table(), $syllabus, $subject ) );
		} elseif ( $syllabus ) {
			$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE syllabus_id=%d ORDER BY id DESC LIMIT 20', self::runs_table(), $syllabus ) );
		} else {
			$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i ORDER BY id DESC LIMIT 20', self::runs_table() ) );
		}
		return array_values( array_filter( array_map( array( __CLASS__, 'run' ), array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * Suggestions for review, most confident first, 30 per page.
	 *
	 * @param array $params run_id, status (pending by default), kind, min_confidence, page.
	 * @return array{items:array[],total:int}
	 */
	public static function suggestions( array $params ) {
		global $wpdb;
		$where = array( '1=1' );
		$args  = array( self::suggestions_table() );
		if ( ! empty( $params['run_id'] ) ) {
			$where[] = 'run_id=%d';
			$args[]  = absint( $params['run_id'] );
		}
		$status = sanitize_key( (string) ( $params['status'] ?? 'pending' ) );
		if ( 'all' !== $status ) {
			$where[] = 'status=%s';
			$args[]  = $status;
		}
		if ( ! empty( $params['kind'] ) ) {
			$where[] = 'kind=%s';
			$args[]  = sanitize_key( (string) $params['kind'] );
		}
		if ( ! empty( $params['course_id'] ) ) {
			$where[] = 'course_id=%d';
			$args[]  = absint( $params['course_id'] );
		}
		if ( ! empty( $params['id'] ) ) {
			$where[] = 'id=%d';
			$args[]  = absint( $params['id'] );
		}
		if ( isset( $params['min_confidence'] ) && '' !== $params['min_confidence'] ) {
			$where[] = 'confidence>=%f';
			$args[]  = (float) $params['min_confidence'];
		}
		$sql   = implode( ' AND ', $where );
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE $sql", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed clauses with placeholders.
		$page  = max( 1, absint( $params['page'] ?? 1 ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE $sql ORDER BY FIELD(kind,'course_skill','new_skill','mapping','placement','link'), confidence DESC, id LIMIT 30 OFFSET %d", array_merge( $args, array( ( $page - 1 ) * 30 ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed clauses with placeholders.
		return array(
			'items' => array_map( array( __CLASS__, 'describe' ), $rows ),
			'total' => $total,
		);
	}

	/**
	 * Accept or reject suggestions.
	 *
	 * @param int[]  $ids       Suggestion IDs.
	 * @param string $action    accept or reject.
	 * @param array  $overrides Suggestion ID => changes to apply on accept (relation, shared_id, name,
	 *                          description, topic, domain, grade_from, grade_to).
	 * @return array Suggestion ID => described suggestion, or an error message.
	 */
	public static function review( array $ids, $action, array $overrides = array() ) {
		global $wpdb;
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, 200 );
		if ( ! $ids ) {
			return array();
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( array( self::suggestions_table() ), $ids ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One placeholder per ID.
		// New skills first, so mappings to them can follow in the same review.
		usort(
			$rows,
			static function ( $a, $b ) {
				return array( self::ORDER_ACCEPT[ $a['kind'] ] ?? 9, (int) $a['id'] ) <=> array( self::ORDER_ACCEPT[ $b['kind'] ] ?? 9, (int) $b['id'] );
			}
		);
		$result = array();
		foreach ( $rows as $row ) {
			if ( 'pending' !== $row['status'] ) {
				$result[ (int) $row['id'] ] = self::describe( $row );
				continue;
			}
			if ( 'reject' === $action ) {
				self::close( (int) $row['id'], 'rejected', 0 );
				$result[ (int) $row['id'] ] = self::describe( self::row( (int) $row['id'] ) );
				continue;
			}
			$done                       = self::accept( $row, (array) ( $overrides[ $row['id'] ] ?? array() ) );
			$result[ (int) $row['id'] ] = is_wp_error( $done ) ? array(
				'id'    => (int) $row['id'],
				'error' => $done->get_error_message(),
			) : self::describe( self::row( (int) $row['id'] ) );
		}
		return $result;
	}

	/**
	 * Accept every pending suggestion of a run at or above a confidence.
	 *
	 * @param int   $run_id         Run.
	 * @param float $min_confidence 0 to 1.
	 * @return array{accepted:int,failed:int}
	 */
	public static function accept_confident( $run_id, $min_confidence ) {
		global $wpdb;
		$ids     = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE run_id=%d AND status='pending' AND confidence>=%f", self::suggestions_table(), (int) $run_id, max( 0.5, min( 1.0, (float) $min_confidence ) ) ) ) );
		$results = self::review( $ids, 'accept' );
		$failed  = count(
			array_filter(
				$results,
				static function ( $row ) {
					return isset( $row['error'] );
				}
			)
		);
		return array(
			'accepted' => count( $results ) - $failed,
			'failed'   => $failed,
		);
	}

	/* ---------- Analysis ---------- */

	/**
	 * Place and match one slice of objectives.
	 *
	 * @param array $run Run row.
	 * @param int[] $ids Objective IDs.
	 * @return int|\WP_Error Suggestions made.
	 */
	private static function analyse_objectives( array $run, array $ids ) {
		$rows = Comparison::objectives( (int) $run['syllabus_id'] );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$by_id = array();
		foreach ( $rows as $row ) {
			if ( in_array( (int) $row['term_id'], $ids, true ) ) {
				$by_id[ (int) $row['term_id'] ] = array(
					'id'    => (int) $row['term_id'],
					'code'  => (string) $row['code'],
					'name'  => trim( Taxonomy::plain( $row['name'] ) . ' ' . (string) ( $row['objective'] ?? '' ) ),
					'topic' => (string) ( $row['memberships'][0]['topic'] ?? '' ),
				);
			}
		}
		if ( ! $by_id ) {
			return 0;
		}
		$profile = SyllabusSettings::get( (int) $run['syllabus_id'] );
		$context = array(
			// The profile's grade or level ("Grade 7", "IGCSE") is the strongest hint for grade bands.
			'syllabus' => implode( ' › ', Items::path_names( (int) $run['syllabus_id'] ) ) . ( ! empty( $profile['grade'] ) ? ' (' . $profile['grade'] . ')' : '' ),
			'subject'  => $run['subject'],
		);
		$domains = Comparison::domains( $run['subject'] );
		$scheme  = SubjectProfiles::get( $run['subject'] );
		$max     = AiAnalysisModel::max_level( $scheme );
		// 1. Place each objective and restate it in English.
		$reply = self::ask( AiAnalysisModel::classify_prompt( $context, array_values( $by_id ), $domains, $scheme ), 2500 );
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		foreach ( AiAnalysisModel::parse_classify( $reply, array_keys( $by_id ), $domains, $max ) as $id => $placement ) {
			$by_id[ $id ] += $placement;
			update_term_meta( $id, self::HINT_DOMAIN, $placement['domain'] );
			update_term_meta( $id, self::HINT_FROM, $placement['grade_from'] );
			update_term_meta( $id, self::HINT_TO, $placement['grade_to'] );
			update_term_meta( $id, self::HINT_GLOSS, $placement['gloss'] );
		}
		// 2. Judge candidates: shared skills, and new skills already proposed in this run.
		$catalog   = self::catalog( (string) $run['subject'] );
		$proposals = self::proposals( (int) $run['id'] );
		$allowed   = array();
		foreach ( $by_id as $id => $objective ) {
			$skill_ids = AiAnalysisModel::candidates( $objective, $catalog );
			$new_ids   = array_slice( AiAnalysisModel::candidates( $objective, $proposals, 4 ), 0, 4 );
			$list      = array();
			foreach ( $skill_ids as $skill_id ) {
				$list[] = self::candidate( (string) $skill_id, $catalog[ $skill_id ] );
			}
			foreach ( $new_ids as $proposal_id ) {
				$list[] = self::candidate( 'n' . $proposal_id, $proposals[ $proposal_id ] );
			}
			$by_id[ $id ]['candidates'] = $list;
			$allowed[ $id ]             = array_column( $list, 'id' );
		}
		$reply = self::ask( AiAnalysisModel::match_prompt( $context, array_values( $by_id ), $scheme, $domains ), 4000 );
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		$made  = 0;
		$names = array();
		foreach ( $proposals as $proposal_id => $proposal ) {
			$names[ AiAnalysisModel::name_key( $proposal['name'] ) ] = (int) $proposal_id;
		}
		$shared_names = array();
		foreach ( $catalog as $skill_id => $skill ) {
			$shared_names[ AiAnalysisModel::name_key( $skill['name'] ) ] = (int) $skill_id;
		}
		foreach ( AiAnalysisModel::parse_match( $reply, $allowed, $domains, $max ) as $id => $answer ) {
			foreach ( $answer['matches'] as $match ) {
				$made += self::suggest_mapping( (int) $run['id'], $id, $match['skill'], $match['relation'], $match['confidence'], $match['reason'] );
			}
			$new = $answer['new_skill'];
			if ( ! $new ) {
				continue;
			}
			$key = AiAnalysisModel::name_key( $new['name'] );
			if ( isset( $shared_names[ $key ] ) ) {
				// The "new" skill already exists under the same name: map to it instead.
				$made += self::suggest_mapping( (int) $run['id'], $id, (string) $shared_names[ $key ], 'equivalent', $new['confidence'], $new['reason'] );
			} elseif ( isset( $names[ $key ] ) ) {
				$made += self::suggest_mapping( (int) $run['id'], $id, 'n' . $names[ $key ], 'equivalent', $new['confidence'], $new['reason'] );
			} else {
				$names[ $key ] = self::insert(
					array(
						'run_id'           => (int) $run['id'],
						'kind'             => 'new_skill',
						'specific_term_id' => $id,
						'relation'         => 'equivalent',
						'payload'          => wp_json_encode( array_diff_key( $new, array_flip( array( 'confidence', 'reason' ) ) ), JSON_UNESCAPED_UNICODE ),
						'confidence'       => $new['confidence'],
						'reason'           => $new['reason'],
					)
				);
				++$made;
			}
		}
		return $made;
	}

	/**
	 * Suggest a domain and grades for one slice of shared skills.
	 *
	 * @param array $run Run row.
	 * @param int[] $ids Shared skill IDs.
	 * @return int|\WP_Error Suggestions made.
	 */
	private static function analyse_placements( array $run, array $ids ) {
		$items = array();
		foreach ( $ids as $id ) {
			$skill = Comparison::shared_skill( $id );
			if ( $skill ) {
				$items[] = array(
					'id'    => $id,
					'code'  => '',
					'topic' => $skill['topic'],
					'name'  => trim( $skill['name'] . '. ' . $skill['description'] ),
				);
			}
		}
		if ( ! $items ) {
			return 0;
		}
		$domains = Comparison::domains( $run['subject'] );
		$profile = SubjectProfiles::get( $run['subject'] );
		$reply   = self::ask(
			AiAnalysisModel::classify_prompt(
				array(
					'syllabus' => __( 'Shared skill library (not tied to one syllabus)', 'ohmylms' ),
					'subject'  => $run['subject'],
				),
				$items,
				$domains,
				$profile
			),
			3000
		);
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		$made = 0;
		foreach ( AiAnalysisModel::parse_classify( $reply, array_column( $items, 'id' ), $domains, AiAnalysisModel::max_level( $profile ) ) as $id => $placement ) {
			if ( '' === $placement['domain'] && ! $placement['grade_from'] ) {
				continue;
			}
			self::insert(
				array(
					'run_id'         => (int) $run['id'],
					'kind'           => 'placement',
					'shared_term_id' => $id,
					'payload'        => wp_json_encode( array_intersect_key( $placement, array_flip( array( 'domain', 'grade_from', 'grade_to' ) ) ) ),
					'confidence'     => $placement['confidence'],
					'reason'         => $placement['gloss'],
				)
			);
			++$made;
		}
		return $made;
	}

	/**
	 * Suggest prerequisites and encompassed skills for one slice of shared skills.
	 *
	 * @param array $run Run row.
	 * @param int[] $ids Shared skill IDs.
	 * @return int|\WP_Error Suggestions made.
	 */
	private static function analyse_links( array $run, array $ids ) {
		$catalog = self::catalog( (string) $run['subject'] );
		$items   = array();
		$allowed = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $catalog[ $id ] ) ) {
				continue;
			}
			$skill               = $catalog[ $id ] + array( 'id' => $id );
			$skill['candidates'] = array();
			foreach ( AiAnalysisModel::link_candidates( $skill, $catalog ) as $other ) {
				$skill['candidates'][] = self::candidate( (string) $other, $catalog[ $other ] );
			}
			$skill['grades'] = self::candidate( (string) $id, $skill )['grades'];
			$items[]         = $skill;
			$allowed[ $id ]  = array_column( $skill['candidates'], 'id' );
		}
		if ( ! $items ) {
			return 0;
		}
		$reply = self::ask( AiAnalysisModel::links_prompt( array( 'subject' => $run['subject'] ), $items, SubjectProfiles::get( $run['subject'] ) ), 4500 );
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		$made = 0;
		foreach ( AiAnalysisModel::parse_links( $reply, $allowed ) as $id => $links ) {
			foreach ( array(
				'prerequisite' => $links['prerequisites'],
				'encompasses'  => $links['encompasses'],
			) as $type => $list ) {
				foreach ( $list as $link ) {
					$made += self::suggest_link( (int) $run['id'], $id, (int) $link['skill'], $type, $link );
				}
			}
		}
		return $made;
	}

	/**
	 * Record a link suggestion unless the link exists or is already waiting.
	 *
	 * @param int    $run_id Run.
	 * @param int    $skill  The skill that needs or encompasses another.
	 * @param int    $other  The other skill.
	 * @param string $type   prerequisite or encompasses.
	 * @param array  $link   confidence, reason and, for encompasses, weight.
	 * @return int 1 when recorded.
	 */
	private static function suggest_link( $run_id, $skill, $other, $type, array $link ) {
		global $wpdb;
		if ( 'prerequisite' === $type && in_array( $other, Taxonomy::prerequisites( $skill ), true ) ) {
			return 0;
		}
		if ( 'encompasses' === $type && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE type='encompasses' AND skill_id=%d AND other_id=%d", SkillEdges::table(), $skill, $other ) ) ) {
			return 0;
		}
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE kind='link' AND status='pending' AND specific_term_id=%d AND shared_term_id=%d AND relation=%s", self::suggestions_table(), $skill, $other, $type ) ) ) {
			return 0;
		}
		self::insert(
			array(
				'run_id'           => $run_id,
				'kind'             => 'link',
				'specific_term_id' => $skill,
				'shared_term_id'   => $other,
				'relation'         => $type,
				'payload'          => 'encompasses' === $type ? wp_json_encode( array( 'weight' => $link['weight'] ) ) : '',
				'confidence'       => $link['confidence'],
				'reason'           => $link['reason'],
			)
		);
		return 1;
	}

	/**
	 * Send one analysis prompt.
	 *
	 * @param array $messages system and user.
	 * @param int   $tokens   Reply budget.
	 * @return string|\WP_Error Reply text.
	 */
	private static function ask( array $messages, $tokens ) {
		$reply = Client::complete(
			$messages + array(
				'max_tokens' => (int) $tokens,
				'long'       => true,
			)
		);
		return is_wp_error( $reply ) ? $reply : (string) $reply['text'];
	}

	/**
	 * Record a mapping suggestion unless the same one is already waiting.
	 *
	 * @param int    $run_id     Run.
	 * @param int    $objective  Objective ID.
	 * @param string $skill      Shared skill ID, or "n" + proposal suggestion ID.
	 * @param string $relation   Relation.
	 * @param float  $confidence Confidence.
	 * @param string $reason     Reason.
	 * @return int 1 when recorded.
	 */
	private static function suggest_mapping( $run_id, $objective, $skill, $relation, $confidence, $reason ) {
		global $wpdb;
		$proposal = 0 === strpos( $skill, 'n' ) ? (int) substr( $skill, 1 ) : 0;
		$shared   = $proposal ? 0 : (int) $skill;
		if ( $shared && $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE specific_term_id=%d AND shared_term_id=%d', SkillMappings::table(), $objective, $shared ) ) ) {
			return 0; // Already mapped.
		}
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE kind='mapping' AND status='pending' AND specific_term_id=%d AND shared_term_id=%d AND payload=%s", self::suggestions_table(), $objective, $shared, $proposal ? wp_json_encode( array( 'proposal' => $proposal ) ) : '' ) );
		if ( $exists ) {
			return 0;
		}
		self::insert(
			array(
				'run_id'           => $run_id,
				'kind'             => 'mapping',
				'specific_term_id' => $objective,
				'shared_term_id'   => $shared,
				'relation'         => $relation,
				'payload'          => $proposal ? wp_json_encode( array( 'proposal' => $proposal ) ) : '',
				'confidence'       => $confidence,
				'reason'           => $reason,
			)
		);
		return 1;
	}

	/* ---------- Review ---------- */

	/**
	 * Apply one suggestion through the editor's own saves.
	 *
	 * @param array $row       Suggestion row.
	 * @param array $overrides Changes chosen by the reviewer.
	 * @return true|\WP_Error
	 */
	private static function accept( array $row, array $overrides ) {
		$payload = json_decode( (string) $row['payload'], true );
		$payload = is_array( $payload ) ? $payload : array();
		if ( 'course_skill' === $row['kind'] ) {
			return self::accept_course_skill( $row, $payload, $overrides );
		}
		if ( 'placement' === $row['kind'] ) {
			$shared = Comparison::shared_skill( (int) $row['shared_term_id'] );
			if ( ! $shared ) {
				return Access::error( 'ohmylms_shared_missing', __( 'Shared skill not found.', 'ohmylms' ), 404 );
			}
			$placement = Comparison::placement( $overrides['domain'] ?? ( $payload['domain'] ?? '' ), $overrides['grade_from'] ?? ( $payload['grade_from'] ?? 0 ), $overrides['grade_to'] ?? ( $payload['grade_to'] ?? 0 ), $shared['subject'] );
			if ( is_wp_error( $placement ) ) {
				return $placement;
			}
			Comparison::store_placement( (int) $row['shared_term_id'], $placement );
			self::close( (int) $row['id'], 'accepted', (int) $row['shared_term_id'] );
			return true;
		}
		if ( 'link' === $row['kind'] ) {
			$other = isset( $overrides['shared_id'] ) ? absint( $overrides['shared_id'] ) : (int) $row['shared_term_id'];
			$saved = 'encompasses' === $row['relation']
				? SkillEdges::add_encompass( (int) $row['specific_term_id'], $other, (float) ( $overrides['weight'] ?? ( $payload['weight'] ?? 0.5 ) ), self::SOURCE_AI, (float) $row['confidence'], (string) $row['reason'] )
				: SkillEdges::add_prerequisite( (int) $row['specific_term_id'], $other, self::SOURCE_AI, (float) $row['confidence'], (string) $row['reason'] );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			self::close( (int) $row['id'], 'accepted', $other );
			return true;
		}
		$run = self::run( (int) $row['run_id'] );
		if ( 'new_skill' === $row['kind'] ) {
			$fields = array_merge( $payload, array_intersect_key( $overrides, array_flip( array( 'name', 'description', 'topic', 'domain', 'grade_from', 'grade_to' ) ) ) );
			// A person accepted it, so the new skill is approved; its AI origin is recorded with it.
			$saved = SkillWriter::shared(
				array(
					'name'        => (string) ( $fields['name'] ?? '' ),
					'description' => (string) ( $fields['description'] ?? '' ),
					'topic'       => (string) ( $fields['topic'] ?? '' ),
					'subject'     => (string) ( $run['subject'] ?? '' ),
					'domain'      => (string) ( $fields['domain'] ?? '' ),
					'grade_from'  => (int) ( $fields['grade_from'] ?? 0 ),
					'grade_to'    => (int) ( $fields['grade_to'] ?? 0 ),
				),
				self::origin( $row, $run, 'approved' )
			);
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			$skill = (int) $saved['id'];
			if ( (int) $row['specific_term_id'] ) {
				$mapped = self::map( (int) $row['specific_term_id'], $skill, $overrides['relation'] ?? 'equivalent', $row );
				if ( is_wp_error( $mapped ) ) {
					return $mapped;
				}
			}
			self::close( (int) $row['id'], 'accepted', $skill );
			return true;
		}
		$shared = isset( $overrides['shared_id'] ) ? absint( $overrides['shared_id'] ) : (int) $row['shared_term_id'];
		if ( ! $shared && ! empty( $payload['proposal'] ) ) {
			$proposal = self::row( (int) $payload['proposal'] );
			if ( ! $proposal || 'accepted' !== $proposal['status'] || ! (int) $proposal['result_term_id'] ) {
				return Access::error( 'ohmylms_ai_suggestion_waiting', __( 'Accept the proposed new skill first; this mapping points to it.', 'ohmylms' ), 409 );
			}
			$shared = (int) $proposal['result_term_id'];
		}
		if ( ! Comparison::shared_skill( $shared ) ) {
			return Access::error( 'ohmylms_shared_missing', __( 'Shared skill not found.', 'ohmylms' ), 404 );
		}
		$mapped = self::map( (int) $row['specific_term_id'], $shared, $overrides['relation'] ?? $row['relation'], $row );
		if ( is_wp_error( $mapped ) ) {
			return $mapped;
		}
		self::close( (int) $row['id'], 'accepted', $shared );
		return true;
	}

	/**
	 * Save a mapping and record that AI suggested it.
	 *
	 * @param int    $objective Objective ID.
	 * @param int    $shared    Shared skill ID.
	 * @param string $relation  Relation.
	 * @param array  $row       Suggestion row.
	 * @return array|\WP_Error
	 */
	private static function map( $objective, $shared, $relation, array $row ) {
		global $wpdb;
		$relation = in_array( $relation, AiAnalysisModel::RELATIONS, true ) ? $relation : 'equivalent';
		/* translators: 1: AI confidence as a percentage, 2: the AI's reason. */
		$note  = sprintf( __( 'Suggested by AI (%1$d%%): %2$s', 'ohmylms' ), (int) round( (float) $row['confidence'] * 100 ), (string) $row['reason'] );
		$saved = SkillMappings::save( $objective, $shared, $relation, mb_substr( $note, 0, 1000 ) );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$wpdb->update(
			SkillMappings::table(),
			array(
				'source'     => self::SOURCE_AI,
				'confidence' => (float) $row['confidence'],
			),
			array(
				'specific_term_id' => $objective,
				'shared_term_id'   => $shared,
			)
		);
		return $saved;
	}

	/**
	 * Mark a suggestion reviewed.
	 *
	 * @param int    $id     Suggestion.
	 * @param string $status accepted or rejected.
	 * @param int    $result Skill the acceptance produced or changed.
	 */
	private static function close( $id, $status, $result ) {
		global $wpdb;
		$wpdb->update(
			self::suggestions_table(),
			array(
				'status'         => $status,
				'result_term_id' => (int) $result,
				'reviewed_by'    => get_current_user_id(),
				'reviewed_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * A suggestion as the review screen shows it.
	 *
	 * @param array $row Suggestion row.
	 * @return array
	 */
	private static function describe( array $row ) {
		$payload   = json_decode( (string) $row['payload'], true );
		$payload   = is_array( $payload ) ? $payload : array();
		$objective = (int) $row['specific_term_id'] ? Taxonomy::describe( (int) $row['specific_term_id'] ) : null;
		$shared    = (int) $row['shared_term_id'] ? Comparison::shared_skill( (int) $row['shared_term_id'] ) : null;
		$proposal  = ! empty( $payload['proposal'] ) ? self::row( (int) $payload['proposal'] ) : null;
		$proposed  = $proposal ? json_decode( (string) $proposal['payload'], true ) : null;
		$is_link   = 'link' === $row['kind'];
		$skill     = $is_link && (int) $row['specific_term_id'] ? Comparison::shared_skill( (int) $row['specific_term_id'] ) : null;
		return array(
			'id'         => (int) $row['id'],
			'run_id'     => (int) $row['run_id'],
			'kind'       => $row['kind'],
			'status'     => $row['status'],
			'relation'   => $row['relation'],
			'confidence' => (float) $row['confidence'],
			'reason'     => (string) $row['reason'],
			'skill'      => $skill ? array_intersect_key( $skill, array_flip( array( 'id', 'name', 'description', 'topic', 'domain', 'grade_from', 'grade_to' ) ) ) : null,
			'weight'     => $is_link && isset( $payload['weight'] ) ? (float) $payload['weight'] : null,
			'objective'  => $objective && ! $is_link ? array(
				'id'         => (int) $row['specific_term_id'],
				'name'       => $objective['name'],
				'code'       => (string) get_term_meta( (int) $row['specific_term_id'], Syllabus::CODE_META, true ),
				'gloss'      => (string) get_term_meta( (int) $row['specific_term_id'], self::HINT_GLOSS, true ),
				'domain'     => (string) get_term_meta( (int) $row['specific_term_id'], self::HINT_DOMAIN, true ),
				'grade_from' => (int) get_term_meta( (int) $row['specific_term_id'], self::HINT_FROM, true ),
				'grade_to'   => (int) get_term_meta( (int) $row['specific_term_id'], self::HINT_TO, true ),
			) : null,
			'shared'     => $shared ? array_intersect_key( $shared, array_flip( array( 'id', 'name', 'description', 'topic', 'domain', 'grade_from', 'grade_to' ) ) ) : null,
			'source'     => (string) ( $row['source'] ?? '' ),
			'course_id'  => (int) ( $row['course_id'] ?? 0 ),
			'target'     => json_decode( (string) ( $row['target'] ?? '' ), true ) ? json_decode( (string) $row['target'], true ) : null,
			'proposal'   => in_array( $row['kind'], array( 'new_skill', 'course_skill' ), true ) ? $payload : ( $proposal ? array(
				'id'     => (int) $proposal['id'],
				'status' => $proposal['status'],
				'name'   => (string) ( $proposed['name'] ?? '' ),
			) : null ),
			'placement'  => 'placement' === $row['kind'] ? $payload : null,
			'result_id'  => (int) $row['result_term_id'],
		);
	}

	/**
	 * Check a course skill request: an editable course, its unit, an optional lesson and a source.
	 *
	 * @param array $params course_id, unit_id, group_id, source.
	 * @return array|\WP_Error course_id, unit_id, group_id, source.
	 */
	private static function course_target( array $params ) {
		$course = absint( $params['course_id'] ?? 0 );
		if ( ! $course || ! \OhMyLMS\Learning\Catalog::can_edit( $course ) ) {
			return Access::error( 'ohmylms_ai_analysis_course', __( 'Choose a course you can edit.', 'ohmylms' ), 403 );
		}
		$source = sanitize_key( (string) ( $params['source'] ?? 'course-content' ) );
		$found  = SkillSources::get( $source );
		if ( ! $found || ! $found->available() ) {
			return Access::error( 'ohmylms_ai_analysis_source', __( 'This skill source is not available on this site.', 'ohmylms' ), 503 );
		}
		$group = (string) ( $params['group_id'] ?? '' );
		if ( '' !== $group && ! wp_is_uuid( $group ) ) {
			return Access::error( 'ohmylms_ai_analysis_invalid', __( 'Choose a lesson of this course.', 'ohmylms' ) );
		}
		return array(
			'course_id' => $course,
			'unit_id'   => absint( $params['unit_id'] ?? 0 ),
			'group_id'  => $group,
			'source'    => $source,
		);
	}

	/**
	 * Ask the run's skill source which skills a lesson teaches; each proposal becomes a suggestion.
	 *
	 * @param array $run Run row.
	 * @return int|\WP_Error Suggestions made.
	 */
	private static function analyse_course( array $run ) {
		$queue  = json_decode( (string) $run['queue'], true );
		$target = (array) ( $queue['target'] ?? array() );
		$source = SkillSources::get( (string) ( $target['source'] ?? '' ) );
		if ( ! $source ) {
			return Access::error( 'ohmylms_ai_analysis_source', __( 'This skill source is not available on this site.', 'ohmylms' ), 503 );
		}
		$result = $source->propose( $target + array( 'subject' => (string) $run['subject'] ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$made = 0;
		foreach ( (array) ( $result['proposals'] ?? array() ) as $proposal ) {
			self::insert(
				array(
					'run_id'         => (int) $run['id'],
					'kind'           => 'course_skill',
					'shared_term_id' => (int) ( $proposal['existing'] ?? 0 ),
					'payload'        => wp_json_encode( $proposal + array( 'meta' => (array) ( $result['meta'] ?? array() ) ) ),
					'confidence'     => (float) ( $proposal['confidence'] ?? 0 ),
					'reason'         => (string) ( $proposal['reason'] ?? '' ),
					'source'         => $source->id(),
					'subject'        => (string) $run['subject'],
					'course_id'      => (int) $target['course_id'],
					'target'         => wp_json_encode(
						array(
							'unit_id'  => (int) $target['unit_id'],
							'group_id' => (string) $target['group_id'],
						)
					),
				)
			);
			++$made;
		}
		return $made;
	}

	/**
	 * Accept a skill proposed for a course: reuse the library skill it names, or create the new skill.
	 * A new skill waits for library review (pending): courses can use it in drafts, but cannot be
	 * published with it until it is approved. Linking it into the outline is the editor's next step.
	 *
	 * @param array $row       Suggestion row.
	 * @param array $payload   Proposal.
	 * @param array $overrides Reviewer changes: name, description, topic, domain, grade_from, grade_to.
	 * @return true|\WP_Error
	 */
	private static function accept_course_skill( array $row, array $payload, array $overrides ) {
		$existing = (int) $row['shared_term_id'];
		if ( $existing ) {
			if ( ! term_exists( $existing, Taxonomy::NAME ) || Provenance::archived( $existing ) ) {
				return Access::error( 'ohmylms_shared_missing', __( 'This library skill is no longer available.', 'ohmylms' ), 404 );
			}
			self::close( (int) $row['id'], 'accepted', $existing );
			return true;
		}
		$fields = array_merge( $payload, array_intersect_key( $overrides, array_flip( array( 'name', 'description', 'topic', 'domain', 'grade_from', 'grade_to' ) ) ) );
		$meta   = (array) ( $payload['meta'] ?? array() );
		$saved  = SkillWriter::shared(
			array(
				'name'        => (string) ( $fields['name'] ?? '' ),
				'description' => (string) ( $fields['description'] ?? '' ),
				'topic'       => (string) ( $fields['topic'] ?? '' ),
				'subject'     => (string) $row['subject'],
				'domain'      => (string) ( $fields['domain'] ?? '' ),
				'grade_from'  => (int) ( $fields['grade_from'] ?? 0 ),
				'grade_to'    => (int) ( $fields['grade_to'] ?? 0 ),
			),
			array(
				'provider' => (string) ( $meta['provider'] ?? '' ),
				'model'    => (string) ( $meta['model'] ?? '' ),
				'prompt'   => (string) ( $meta['prompt'] ?? '' ),
			) + self::origin( $row, self::run( (int) $row['run_id'] ), 'pending' )
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		self::close( (int) $row['id'], 'accepted', (int) $saved['id'] );
		return true;
	}

	/* ---------- Data ---------- */

	/**
	 * Objectives of a syllabus to analyse: unmapped ones, or all.
	 *
	 * @param int  $syllabus       Syllabus.
	 * @param bool $include_mapped Include objectives that already have a mapping.
	 * @return int[]|\WP_Error
	 */
	private static function objective_queue( $syllabus, $include_mapped ) {
		$rows = Comparison::objectives( $syllabus );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$ids = array();
		foreach ( Comparison::attach_mappings( $rows ) as $row ) {
			if ( ! $row['is_shared'] && ( $include_mapped || ! $row['maps_to'] ) ) {
				$ids[] = (int) $row['term_id'];
			}
		}
		return array_slice( $ids, 0, 2000 );
	}

	/**
	 * Placed shared skills to link: those without prerequisites, or all.
	 *
	 * @param string $subject        Subject, or all.
	 * @param bool   $include_linked Include skills that already have prerequisites.
	 * @return int[]
	 */
	private static function links_queue( $subject, $include_linked ) {
		$ids = array();
		foreach ( self::catalog( $subject ) as $id => $skill ) {
			if ( $skill['grade_from'] && ( $include_linked || ! Taxonomy::prerequisites( $id ) ) ) {
				$ids[] = (int) $id;
			}
		}
		// Earliest grades first, so a skill's prerequisites are linked before the skills that need them.
		usort(
			$ids,
			static function ( $a, $b ) {
				return (int) get_term_meta( $a, Comparison::GRADE_FROM_META, true ) <=> (int) get_term_meta( $b, Comparison::GRADE_FROM_META, true );
			}
		);
		return array_slice( $ids, 0, 3000 );
	}

	/**
	 * Shared skills without a domain or grades.
	 *
	 * @param string $subject Subject, or all.
	 * @return int[]
	 */
	private static function placement_queue( $subject ) {
		$ids = array();
		foreach ( self::catalog( $subject ) as $id => $skill ) {
			if ( '' === $skill['domain'] || ! $skill['grade_from'] ) {
				$ids[] = (int) $id;
			}
		}
		return array_slice( $ids, 0, 3000 );
	}

	/**
	 * Every shared skill of a subject, compactly.
	 *
	 * @param string $subject Subject, or every subject when empty.
	 * @return array<int,array> ID => name, description, topic, domain, grade_from, grade_to.
	 */
	public static function catalog( $subject ) {
		$args = array(
			'taxonomy'   => Taxonomy::NAME,
			'hide_empty' => false,
			'meta_key'   => Comparison::SUBJECT_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Shared skills are marked by this key.
			'number'     => 5000,
		);
		if ( '' !== $subject ) {
			$args['meta_value'] = $subject; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded admin read.
		}
		$terms  = get_terms( $args );
		$result = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$id = (int) $term->term_id;
			// Archived and rejected skills are not offered for new matches.
			if ( Provenance::archived( $id ) || 'rejected' === get_term_meta( $id, Provenance::REVIEW_META, true ) ) {
				continue;
			}
			$result[ $id ] = array(
				'name'        => Taxonomy::plain( $term->name ),
				'description' => (string) $term->description,
				'topic'       => (string) get_term_meta( $id, Comparison::TOPIC_META, true ),
				'domain'      => (string) get_term_meta( $id, Comparison::DOMAIN_META, true ),
				'grade_from'  => (int) get_term_meta( $id, Comparison::GRADE_FROM_META, true ),
				'grade_to'    => (int) get_term_meta( $id, Comparison::GRADE_TO_META, true ),
			);
		}
		return $result;
	}

	/**
	 * New skills proposed in a run and still waiting, shaped like catalog entries.
	 *
	 * @param int $run_id Run.
	 * @return array<int,array> Suggestion ID => skill fields.
	 */
	private static function proposals( $run_id ) {
		global $wpdb;
		$result = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT id, payload FROM %i WHERE run_id=%d AND kind='new_skill' AND status='pending'", self::suggestions_table(), (int) $run_id ), ARRAY_A ) as $row ) {
			$payload = json_decode( (string) $row['payload'], true );
			if ( is_array( $payload ) && ! empty( $payload['name'] ) ) {
				$result[ (int) $row['id'] ] = $payload + array(
					'description' => '',
					'topic'       => '',
					'domain'      => '',
					'grade_from'  => 0,
					'grade_to'    => 0,
				);
			}
		}
		return $result;
	}

	/**
	 * One candidate line for the match prompt.
	 *
	 * @param string $id    Candidate id.
	 * @param array  $skill Skill fields.
	 * @return array
	 */
	private static function candidate( $id, array $skill ) {
		$from = (int) ( $skill['grade_from'] ?? 0 );
		$to   = (int) ( $skill['grade_to'] ?? 0 );
		return array(
			'id'          => $id,
			'name'        => (string) $skill['name'],
			'domain'      => (string) ( $skill['domain'] ?? '' ),
			'grades'      => $from ? ( $from === $to ? 'G' . $from : 'G' . $from . '-' . $to ) : '',
			'description' => (string) ( $skill['description'] ?? '' ),
		);
	}

	/**
	 * A shared skill of a subject with the same name, ignoring case and small word differences.
	 *
	 * @param string $name    Name.
	 * @param string $subject Subject.
	 * @return int Skill ID or 0.
	 */
	private static function find_shared( $name, $subject ) {
		return SkillWriter::find_shared( $name, $subject );
	}

	/**
	 * Provenance of a skill created from a suggestion: the AI source, the run and suggestion, the
	 * model and prompt version of the run, and the suggestion's confidence.
	 *
	 * @param array      $row    Suggestion row.
	 * @param array|null $run    Run.
	 * @param string     $review Review state to record.
	 * @return array
	 */
	private static function origin( array $row, $run, $review ) {
		return array(
			'source'     => (string) ( $row['source'] ?? '' ) ? (string) $row['source'] : 'ai',
			'ref'        => 'run:' . (int) $row['run_id'] . '/suggestion:' . (int) $row['id'],
			'review'     => $review,
			'reviewed'   => 'approved' === $review,
			'provider'   => (string) ( AiSettings::get()['provider'] ?? '' ),
			'model'      => (string) ( $run['model'] ?? '' ),
			'prompt'     => (string) ( $run['prompt'] ?? '' ),
			'confidence' => (float) $row['confidence'],
		);
	}

	/**
	 * The subject a syllabus is configured under, or empty.
	 *
	 * @param int $syllabus Syllabus.
	 * @return string
	 */
	private static function subject_of( $syllabus ) {
		foreach ( (array) get_option( Comparison::OPTION, array() ) as $subject => $ids ) {
			if ( in_array( (int) $syllabus, array_map( 'intval', (array) $ids ), true ) ) {
				return (string) $subject;
			}
		}
		// Otherwise the subject written on the syllabus profile.
		return sanitize_text_field( (string) ( SyllabusSettings::get( (int) $syllabus )['subject'] ?? '' ) );
	}

	/**
	 * Insert a pending suggestion.
	 *
	 * @param array $fields Columns.
	 * @return int New ID.
	 */
	private static function insert( array $fields ) {
		global $wpdb;
		$wpdb->insert(
			self::suggestions_table(),
			$fields + array(
				'status'     => 'pending',
				'created_at' => current_time( 'mysql', true ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * One suggestion row.
	 *
	 * @param int $id Suggestion.
	 * @return array|null
	 */
	private static function row( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', self::suggestions_table(), (int) $id ), ARRAY_A );
	}

	/** Runs table. */
	private static function runs_table() {
		return Schema::table( 'ai_analysis_runs' );
	}

	/** Suggestions table. */
	private static function suggestions_table() {
		return Schema::table( 'ai_suggestions' );
	}
}
