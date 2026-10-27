<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Resumable backfill of question UUIDs and initial versions.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

use OhMyLMS\QuestionBank\VersionPublisher;

defined( 'ABSPATH' ) || exit;

/**
 * Resumable backfill of question UUIDs and initial versions.
 *
 * Initial versions are marked as migration-time snapshots: they record content as it
 * is now, not a reconstruction of what earlier learners saw. Old attempts keep their
 * saved marks and correctness and stay on the legacy reader. The migration version is
 * recorded only after every question has been processed.
 */
final class Migration {
	const OPTION  = 'ohmylms_assessment_migration';
	const HOOK    = 'ohmylms_assessment_migrate';
	const VERSION = 1;
	const BATCH   = 50;

	/**
	 * State for Migration.
	 */
	public static function state() {
		$state = get_option( self::OPTION );
		return is_array( $state ) ? $state + array(
			'status'    => 'pending',
			'last_id'   => 0,
			'processed' => 0,
			'failed'    => array(),
		) : array(
			'status'    => 'pending',
			'last_id'   => 0,
			'processed' => 0,
			'failed'    => array(),
			'version'   => 0,
		);
	}

	/**
	 * Done for Migration.
	 */
	public static function done() {
		$state = self::state();
		return 'done' === $state['status'] && (int) ( $state['version'] ?? 0 ) >= self::VERSION;
	}

	/** Counts used to compare before and after rollout, including orphaned legacy rows. */
	public static function inventory() {
		global $wpdb;
		$p         = $wpdb->posts;
		$links     = $wpdb->prefix . 'ohmylms_quiz_questions_relationship';
		$answers   = $wpdb->prefix . 'ohmylms_question_answers';
		$attempts  = $wpdb->prefix . 'ohmylms_quiz_attempts';
		$responses = $wpdb->prefix . 'ohmylms_quiz_attempts_answers';
		$count     = static function ( $sql ) use ( $wpdb ) {
			return (int) $wpdb->get_var( $sql );
		};
		$result    = array(
			'questions'              => $count( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_type=%s AND post_status NOT IN (\'auto-draft\',\'trash\')', $p, OHMYLMS_QUESTION_CPT ) ),
			'quizzes'                => $count( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_type=%s AND post_status NOT IN (\'auto-draft\',\'trash\')', $p, OHMYLMS_QUIZ_CPT ) ),
			'quiz_question_links'    => $count( "SELECT COUNT(*) FROM $links" ),
			'orphan_links'           => $count( "SELECT COUNT(*) FROM $links l LEFT JOIN $p q ON q.ID=l.question_id LEFT JOIN $p z ON z.ID=l.quiz_id WHERE q.ID IS NULL OR z.ID IS NULL" ),
			'options'                => $count( "SELECT COUNT(*) FROM $answers" ),
			'orphan_options'         => $count( "SELECT COUNT(*) FROM $answers a LEFT JOIN $p q ON q.ID=a.question_id WHERE q.ID IS NULL" ),
			'attempts'               => $count( "SELECT COUNT(*) FROM $attempts" ),
			'attempts_in_progress'   => $count( "SELECT COUNT(*) FROM $attempts WHERE status='in-progress'" ),
			'attempt_answers'        => $count( "SELECT COUNT(*) FROM $responses" ),
			'orphan_attempt_answers' => $count( "SELECT COUNT(*) FROM $responses r LEFT JOIN $attempts a ON a.id=r.quiz_attempt_id WHERE a.id IS NULL" ),
		);
		if ( Schema::ready() ) {
			$result['questions_with_identity'] = $count( 'SELECT COUNT(*) FROM ' . Schema::table( 'qb_questions' ) );
			$result['question_versions']       = $count( 'SELECT COUNT(*) FROM ' . Schema::table( 'qb_question_versions' ) );
			$result['migration_snapshots']     = $count( 'SELECT COUNT(*) FROM ' . Schema::table( 'qb_question_versions' ) . ' WHERE is_migration_snapshot=1' );
			$result['versioned_attempts']      = $count( 'SELECT COUNT(*) FROM ' . Schema::table( 'attempt_context' ) );
			$result['quiz_revisions']          = $count( 'SELECT COUNT(*) FROM ' . Schema::table( 'quiz_revisions' ) );
		}
		return $result;
	}

	/** Process one batch. Safe to call repeatedly and concurrently (named lock).
	 *
	 * @param mixed $size Size.
	 */
	public static function run_batch( $size = self::BATCH ) {
		global $wpdb;
		if ( ! Schema::ready() ) {
			return self::state(); }
		$lock = 'ohmylms-assessment-migration-' . md5( $wpdb->prefix );
		if ( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) !== '1' ) {
			return self::state(); }
		try {
			$state = self::state();
			if ( 'done' === $state['status'] && (int) ( $state['version'] ?? 0 ) >= self::VERSION ) {
				return $state; }
			$state['status']     = 'running';
			$state['started_at'] = $state['started_at'] ?? current_time( 'mysql', true );
			$ids                 = $wpdb->get_col(
				$wpdb->prepare( 'SELECT ID FROM %i WHERE post_type=%s AND post_status NOT IN (\'auto-draft\') AND ID>%d ORDER BY ID ASC LIMIT %d', $wpdb->posts, OHMYLMS_QUESTION_CPT, (int) $state['last_id'], max( 1, (int) $size ) )
			);
			foreach ( $ids as $id ) {
				try {
					$existing = VersionPublisher::identity( (int) $id, false );
					VersionPublisher::identity( (int) $id );
					// Only questions without any version get a migration snapshot.
					if ( ! $existing || ! (int) $existing['current_version_id'] ) {
						VersionPublisher::capture( (int) $id, true ); }
					++$state['processed'];
				} catch ( \Throwable $error ) {
					$state['failed'][ (int) $id ] = substr( $error->getMessage(), 0, 200 );
				}
				$state['last_id'] = (int) $id;
			}
			if ( count( $ids ) < max( 1, (int) $size ) ) {
				$decimal = self::migrate_decimal_scores();
				if ( is_wp_error( $decimal ) ) {
					$state['failed']['decimal_scores'] = $decimal->get_error_message(); }
				$state['status']       = $state['failed'] ? 'needs-attention' : 'done';
				$state['version']      = $state['failed'] ? 0 : self::VERSION;
				$state['completed_at'] = current_time( 'mysql', true );
			}
			update_option( self::OPTION, $state, false );
			return $state;
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Switch stored attempt scores to decimal(12,4). Values are preserved (legacy totals are
	 * whole numbers; float answer marks convert exactly at four places). Only after the column
	 * types are verified do new attempts use decimal scoring; older attempts keep legacy-int.
	 */
	public static function migrate_decimal_scores() {
		global $wpdb;
		if ( get_option( Scoring::MIGRATED_OPTION ) === '1' ) {
			return true; }
		$attempts = $wpdb->prefix . 'ohmylms_quiz_attempts';
		$answers  = $wpdb->prefix . 'ohmylms_quiz_attempts_answers';
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i MODIFY total decimal(12,4) NOT NULL DEFAULT 0', $attempts ) );
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i MODIFY question_marks decimal(12,4) DEFAULT 0, MODIFY achive_mark decimal(12,4) DEFAULT 0, MODIFY minus_mark decimal(12,4) DEFAULT 0', $answers ) );
		$types = array();
		foreach ( array( array( $attempts, 'total' ), array( $answers, 'question_marks' ), array( $answers, 'achive_mark' ), array( $answers, 'minus_mark' ) ) as [$table, $column] ) {
			$types[] = strtolower( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s', $table, $column ) ) );
		}
		if ( array_unique( $types ) !== array( 'decimal' ) ) {
			return new \WP_Error( 'ohmylms_decimal_migration', __( 'Attempt score columns could not be converted to decimal.', 'ohmylms' ) );
		}
		update_option( Scoring::MIGRATED_OPTION, '1', false );
		return true;
	}

	/** Run to completion (CLI, tests, admin "run now").
	 *
	 * @param int $max_batches Max batches.
	 */
	public static function run_all( $max_batches = 1000 ) {
		$state = self::state();
		for ( $i = 0; $i < $max_batches && ! in_array( $state['status'], array( 'done', 'needs-attention' ), true ); $i++ ) {
			$state = self::run_batch(); }
		return $state;
	}

	/** Retry questions that failed, after their data has been repaired. */
	public static function retry_failed() {
		$state            = self::state();
		$state['status']  = 'running';
		$state['last_id'] = 0;
		$state['failed']  = array();
		update_option( self::OPTION, $state, false );
		return self::run_all();
	}

	/**
	 * Maybe schedule for Migration.
	 */
	public static function maybe_schedule() {
		if ( ! Schema::ready() || in_array( self::state()['status'], array( 'done', 'needs-attention' ), true ) || wp_next_scheduled( self::HOOK ) ) {
			return; }
		wp_schedule_single_event( time() + 5, self::HOOK );
	}

	/**
	 * Run scheduled for Migration.
	 */
	public static function run_scheduled() {
		$state = self::run_batch();
		if ( ! in_array( $state['status'], array( 'done', 'needs-attention' ), true ) ) {
			wp_schedule_single_event( time() + 5, self::HOOK ); }
	}
}
