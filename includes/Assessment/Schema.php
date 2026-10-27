<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Additive, independently versioned schema for the question bank, versioned
 * assessments, practice and skill evidence. Existing quiz tables stay the
 * compatibility anchor: new records are sidecars keyed by existing IDs.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/**
 * Additive, independently versioned schema for the question bank, versioned
 * assessments, practice and skill evidence. Existing quiz tables stay the
 * compatibility anchor: new records are sidecars keyed by existing IDs.
 */
final class Schema {
	const VERSION = '5';
	const OPTION  = 'ohmylms_assessment_schema';

	/**
	 * Table for Schema.
	 *
	 * @param mixed $name Name.
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_' . $name;
	}

	/**
	 * Definitions for Schema.
	 *
	 * @return array<string,string> table => column definitions (id/primary key are added). */
	public static function definitions() {
		return array(
			// Question bank ownership and sharing.
			'qb_banks'             => "name varchar(190) NOT NULL, owner_id bigint unsigned NOT NULL, course_id bigint unsigned NOT NULL DEFAULT 0, visibility varchar(20) NOT NULL DEFAULT 'private', created_at datetime NOT NULL, KEY owner (owner_id), KEY course (course_id)",
			'qb_grants'            => 'bank_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, permission varchar(20) NOT NULL, granted_by bigint unsigned NOT NULL, created_at datetime NOT NULL, UNIQUE KEY grant_key (bank_id,user_id,permission), KEY user_scope (user_id,permission)',
			// Stable identity and indexed bank attributes for each question post.
			'qb_questions'         => "question_id bigint unsigned NOT NULL, uuid char(36) NOT NULL, bank_id bigint unsigned NOT NULL DEFAULT 0, family_id varchar(64) NOT NULL DEFAULT '', status varchar(20) NOT NULL DEFAULT 'draft', difficulty varchar(20) NOT NULL DEFAULT 'standard', source varchar(100) NOT NULL DEFAULT '', secure tinyint NOT NULL DEFAULT 0, type varchar(60) NOT NULL DEFAULT '', author_id bigint unsigned NOT NULL DEFAULT 0, current_version_id bigint unsigned NOT NULL DEFAULT 0, approved_version_id bigint unsigned NOT NULL DEFAULT 0, latest_version_no int unsigned NOT NULL DEFAULT 0, updated_at datetime NOT NULL, UNIQUE KEY question_id (question_id), UNIQUE KEY uuid (uuid), KEY bank_status (bank_id,status), KEY family (family_id), KEY difficulty (difficulty), KEY type (type)",
			// Current question-to-skill connections (one row per question, skill and part); the draft skill map in post meta stays the source of truth and this table is kept in step with it.
			'question_skills'      => "question_id bigint unsigned NOT NULL, term_id bigint unsigned NOT NULL, part_id varchar(40) NOT NULL DEFAULT 'p1', role varchar(20) NOT NULL DEFAULT 'primary', UNIQUE KEY connection (question_id,term_id,part_id), KEY skill (term_id,role)",
			// Skills a quiz as a whole is meant to cover (its questions keep their own mapping in question_skills).
			'quiz_skills'          => 'quiz_id bigint unsigned NOT NULL, term_id bigint unsigned NOT NULL, position int unsigned NOT NULL DEFAULT 0, UNIQUE KEY connection (quiz_id,term_id), KEY skill (term_id)',
			// Immutable content snapshots.
			'qb_question_versions' => "question_id bigint unsigned NOT NULL, question_uuid char(36) NOT NULL, version_no int unsigned NOT NULL, content_hash char(64) NOT NULL, type varchar(60) NOT NULL, schema_version smallint unsigned NOT NULL DEFAULT 1, grader_version varchar(20) NOT NULL DEFAULT '1', title text NOT NULL, body longtext NOT NULL, settings longtext NOT NULL, options longtext NOT NULL, media longtext NOT NULL, extension longtext NOT NULL, parts longtext NOT NULL, is_migration_snapshot tinyint NOT NULL DEFAULT 0, created_by bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, UNIQUE KEY question_version (question_id,version_no), KEY question_uuid (question_uuid), KEY content (question_id,content_hash)",
			// Skills assessed by each part of a version, frozen with the version.
			'qb_version_skills'    => "version_id bigint unsigned NOT NULL, part_id varchar(40) NOT NULL DEFAULT 'p1', term_id bigint unsigned NOT NULL, skill_uuid char(36) NOT NULL DEFAULT '', role varchar(20) NOT NULL DEFAULT 'primary', UNIQUE KEY mapping (version_id,part_id,term_id), KEY skill (term_id)",
			// Published assessment revisions and their slots.
			'quiz_revisions'       => "quiz_id bigint unsigned NOT NULL, revision_no int unsigned NOT NULL, kind varchar(20) NOT NULL DEFAULT 'quiz', settings longtext NOT NULL, total_marks decimal(12,4) NOT NULL DEFAULT 0, content_hash char(64) NOT NULL, status varchar(20) NOT NULL DEFAULT 'published', created_by bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, UNIQUE KEY quiz_revision (quiz_id,revision_no), KEY quiz_status (quiz_id,status)",
			'quiz_revision_slots'  => "revision_id bigint unsigned NOT NULL, slot_no int unsigned NOT NULL, section varchar(190) NOT NULL DEFAULT '', page int unsigned NOT NULL DEFAULT 0, question_id bigint unsigned NOT NULL DEFAULT 0, version_id bigint unsigned NOT NULL DEFAULT 0, marks decimal(12,4) NOT NULL DEFAULT 0, required tinyint NOT NULL DEFAULT 0, shuffle_options tinyint NOT NULL DEFAULT 0, pool longtext NULL, UNIQUE KEY revision_slot (revision_id,slot_no), KEY version (version_id)",
			// Sidecar delivery records for existing quiz attempt IDs.
			'attempt_context'      => "attempt_id bigint unsigned NOT NULL, revision_id bigint unsigned NOT NULL, engine varchar(20) NOT NULL DEFAULT 'versioned', scoring varchar(20) NOT NULL DEFAULT 'decimal', started_at datetime NOT NULL, deadline_at datetime NULL, grace_seconds int unsigned NOT NULL DEFAULT 0, extra_seconds int unsigned NOT NULL DEFAULT 0, seed bigint unsigned NOT NULL DEFAULT 0, finalized_at datetime NULL, finalize_reason varchar(20) NOT NULL DEFAULT '', UNIQUE KEY attempt (attempt_id), KEY deadline (finalized_at,deadline_at)",
			'attempt_items'        => "attempt_id bigint unsigned NOT NULL, position int unsigned NOT NULL, slot_no int unsigned NOT NULL, question_id bigint unsigned NOT NULL, question_uuid char(36) NOT NULL, version_id bigint unsigned NOT NULL, marks decimal(12,4) NOT NULL DEFAULT 0, option_order longtext NOT NULL, display longtext NOT NULL, status varchar(20) NOT NULL DEFAULT 'unanswered', response longtext NULL, fraction decimal(10,6) NULL, awarded decimal(12,4) NULL, correct tinyint NULL, graded_at datetime NULL, UNIQUE KEY attempt_position (attempt_id,position), UNIQUE KEY attempt_question (attempt_id,question_id), KEY version (version_id)",
			// Durable autosave and the server-received response history.
			'response_drafts'      => 'attempt_id bigint unsigned NOT NULL, item_id bigint unsigned NOT NULL, response longtext NOT NULL, sequence bigint unsigned NOT NULL DEFAULT 0, received_at datetime NOT NULL, UNIQUE KEY attempt_item (attempt_id,item_id)',
			'response_events'      => 'attempt_id bigint unsigned NOT NULL, item_id bigint unsigned NOT NULL, sequence bigint unsigned NOT NULL DEFAULT 0, response longtext NOT NULL, received_at datetime NOT NULL, assisted tinyint NOT NULL DEFAULT 0, KEY attempt_item (attempt_id,item_id)',
			// Item-level grading history for every response-producing surface.
			'grade_events'         => "source_type varchar(20) NOT NULL, source_id bigint unsigned NOT NULL, item_id bigint unsigned NOT NULL, part_id varchar(40) NOT NULL DEFAULT '', student_id bigint unsigned NOT NULL DEFAULT 0, question_id bigint unsigned NOT NULL, version_id bigint unsigned NOT NULL, awarded decimal(12,4) NOT NULL DEFAULT 0, max_marks decimal(12,4) NOT NULL DEFAULT 0, fraction decimal(10,6) NOT NULL DEFAULT 0, correct tinyint NOT NULL DEFAULT 0, grader varchar(20) NOT NULL DEFAULT 'auto', reviewer_id bigint unsigned NOT NULL DEFAULT 0, reason text NOT NULL, supersedes bigint unsigned NOT NULL DEFAULT 0, superseded_by bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, KEY source_item (source_type,source_id,item_id,part_id), KEY student (student_id), KEY current_events (superseded_by,source_type)",
			// Practice sessions (skill practice, inline checks, guest practice).
			'practice_sessions'    => "uuid char(36) NOT NULL, student_id bigint unsigned NOT NULL DEFAULT 0, guest_id bigint unsigned NOT NULL DEFAULT 0, mode varchar(20) NOT NULL DEFAULT 'skill', term_id bigint unsigned NOT NULL DEFAULT 0, lesson_id bigint unsigned NOT NULL DEFAULT 0, course_id bigint unsigned NOT NULL DEFAULT 0, policy longtext NOT NULL, status varchar(20) NOT NULL DEFAULT 'active', item_limit int unsigned NOT NULL DEFAULT 10, started_at datetime NOT NULL, completed_at datetime NULL, claimed_at datetime NULL, UNIQUE KEY uuid (uuid), KEY student (student_id,status), KEY guest (guest_id,status)",
			'practice_items'       => "session_id bigint unsigned NOT NULL, position int unsigned NOT NULL, question_id bigint unsigned NOT NULL, question_uuid char(36) NOT NULL, version_id bigint unsigned NOT NULL, option_order longtext NOT NULL, display longtext NOT NULL, response longtext NULL, fraction decimal(10,6) NULL, correct tinyint NULL, assisted tinyint NOT NULL DEFAULT 0, tries int unsigned NOT NULL DEFAULT 0, receipt char(64) NOT NULL DEFAULT '', answered_at datetime NULL, UNIQUE KEY session_position (session_id,position), KEY version (version_id)",
			// Reliable, replayable skill-evidence pipeline.
			'evidence_outbox'      => "grade_event_id bigint unsigned NOT NULL, status varchar(20) NOT NULL DEFAULT 'pending', tries int unsigned NOT NULL DEFAULT 0, last_error text NULL, created_at datetime NOT NULL, processed_at datetime NULL, UNIQUE KEY grade_event (grade_event_id), KEY queue (status,id)",
			'skill_evidence'       => "grade_event_id bigint unsigned NOT NULL, student_id bigint unsigned NOT NULL, term_id bigint unsigned NOT NULL, part_id varchar(40) NOT NULL DEFAULT 'p1', role varchar(20) NOT NULL DEFAULT 'primary', awarded decimal(12,4) NOT NULL DEFAULT 0, available decimal(12,4) NOT NULL DEFAULT 0, independent tinyint NOT NULL DEFAULT 1, first_try tinyint NOT NULL DEFAULT 1, difficulty varchar(20) NOT NULL DEFAULT 'standard', family_id varchar(64) NOT NULL DEFAULT '', source_type varchar(20) NOT NULL, source_id bigint unsigned NOT NULL, question_id bigint unsigned NOT NULL, version_id bigint unsigned NOT NULL, mapping_version int unsigned NOT NULL DEFAULT 1, evidence_at datetime NOT NULL, superseded tinyint NOT NULL DEFAULT 0, UNIQUE KEY contribution (grade_event_id,term_id,part_id), KEY student_skill (student_id,term_id,superseded)",
			// One row per learner and skill: the level the active policy decided, the 0-100 mastery score and the counts behind them (see Performance\Engine).
			'student_skill_state'  => "student_id bigint unsigned NOT NULL, term_id bigint unsigned NOT NULL, level varchar(20) NOT NULL DEFAULT 'not-started', policy varchar(20) NOT NULL DEFAULT 'evidence', review_due tinyint NOT NULL DEFAULT 0, review_due_at datetime NULL, evidence_count int unsigned NOT NULL DEFAULT 0, independent_correct int unsigned NOT NULL DEFAULT 0, families int unsigned NOT NULL DEFAULT 0, accuracy decimal(8,4) NOT NULL DEFAULT 0, mastery decimal(5,2) NOT NULL DEFAULT 0, peak decimal(5,2) NOT NULL DEFAULT 0, scored int unsigned NOT NULL DEFAULT 0, correct int unsigned NOT NULL DEFAULT 0, struggling tinyint NOT NULL DEFAULT 0, milestones varchar(40) NOT NULL DEFAULT '', last_evidence_at datetime NULL, last_scored_at datetime NULL, updated_at datetime NOT NULL, UNIQUE KEY student_skill (student_id,term_id), KEY skill_level (term_id,level)",
			// What each recent activity did to a level; derived, so it can be rebuilt from the evidence.
			'skill_level_events'   => 'student_id bigint unsigned NOT NULL, term_id bigint unsigned NOT NULL, source_type varchar(20) NOT NULL, source_id bigint unsigned NOT NULL, level_before varchar(20) NOT NULL, level_after varchar(20) NOT NULL, detail text NULL, created_at datetime NOT NULL, UNIQUE KEY source (student_id,term_id,source_type,source_id), KEY student_skill (student_id,term_id,id)',
			// Pseudonymous guest sessions and their one-time claims.
			'guest_sessions'       => 'token_hash char(64) NOT NULL, created_at datetime NOT NULL, expires_at datetime NOT NULL, claimed_by bigint unsigned NOT NULL DEFAULT 0, claimed_at datetime NULL, UNIQUE KEY token_hash (token_hash), KEY expires (expires_at)',
		);
	}

	/**
	 * Ready for Schema.
	 */
	public static function ready() {
		return get_option( self::OPTION ) === self::VERSION;
	}

	/**
	 * Install for Schema.
	 */
	public static function install() {
		if ( get_option( self::OPTION ) === self::VERSION ) {
			return true; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( self::definitions() as $name => $definition ) {
			$table   = self::table( $name );
			$columns = str_replace( ', ', ",\n", $definition );
			// decimal(12,4) contains a comma followed by a digit, never ", ", so the split is safe.
			dbDelta( "CREATE TABLE $table (\n id bigint unsigned NOT NULL AUTO_INCREMENT,\n $columns,\n PRIMARY KEY  (id)\n) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';' );
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
				return false; }
		}
		\OhMyLMS\QuestionBank\SkillMap::backfill();
		update_option( self::OPTION, self::VERSION, false );
		do_action( 'ohmylms_assessment_schema_installed', self::VERSION );
		return true;
	}
}
