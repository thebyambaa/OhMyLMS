<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Additive, independently versioned storage for curriculum structures, their links to
 * content and shared-skill mappings. Nothing here alters an existing
 * table: courses, enrollments, learning modes and progress keep their own records, and
 * these tables only reference them by ID. Installing is safe to repeat.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

defined( 'ABSPATH' ) || exit;

/**
 * Additive, independently versioned storage for curriculum structures, their links to
 * content and shared-skill mappings. Nothing here alters an existing
 * table: courses, enrollments, learning modes and progress keep their own records, and
 * these tables only reference them by ID. Installing is safe to repeat.
 */
final class Schema {
	// 2: items can be syllabuses (is_syllabus, skill_root_id) and syllabuses hold skill groups of skills.
	// 3: a syllabus is also a course (course_id).
	// 4: curriculum topics and syllabus chapters have editable icons.
	// 5: combined coverage groups retain explicit required membership.
	// 6: (retired) Course Bundles were stored as "tracks".
	// 7: course_links holds advisory course-to-course links (sub-courses, prerequisites, "get ready for").
	// 8: mappings record where they came from (source, confidence); AI analysis runs and their suggestions await review.
	// 12: Course Bundles were removed; their tables are dropped.
	// 9: skill_edges holds the provenance of links between shared skills (prerequisites) and the weighted "encompasses" links.
	const VERSION = '12';
	const OPTION  = 'ohmylms_curriculum_schema';

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
			// Any depth: a row points at its parent (0 = root) and carries its order among siblings.
			// is_syllabus marks any item as a syllabus; skill_root_id is the library skill that parents the skills created for it;
			// course_id is the course a syllabus is (a syllabus is also a course; 0 until it is made).
			'curriculum_items'      => "uuid char(36) NOT NULL, parent_id bigint unsigned NOT NULL DEFAULT 0, position int unsigned NOT NULL DEFAULT 0, item_type varchar(40) NOT NULL DEFAULT 'custom', name varchar(190) NOT NULL, icon varchar(40) NOT NULL DEFAULT '', description text NULL, code varchar(60) NOT NULL DEFAULT '', version varchar(60) NOT NULL DEFAULT '', is_syllabus tinyint(1) NOT NULL DEFAULT 0, skill_root_id bigint unsigned NOT NULL DEFAULT 0, course_id bigint unsigned NOT NULL DEFAULT 0, created_by bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, updated_at datetime NOT NULL, UNIQUE KEY uuid (uuid), KEY parent_position (parent_id,position), KEY item_type (item_type), KEY is_syllabus (is_syllabus), KEY course_id (course_id)",
			// Skill groups of a syllabus. A group sits under the syllabus item or one of the content items beneath it.
			'syllabus_groups'       => "uuid char(36) NOT NULL, item_id bigint unsigned NOT NULL, position int unsigned NOT NULL DEFAULT 0, code varchar(60) NOT NULL DEFAULT '', name varchar(190) NOT NULL, icon varchar(40) NOT NULL DEFAULT '', description text NULL, created_by bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, updated_at datetime NOT NULL, UNIQUE KEY uuid (uuid), KEY item_position (item_id,position)",
			// The library skills placed in a group, in order. A placement is a reference: removing it never deletes the skill.
			'syllabus_group_skills' => 'group_id bigint unsigned NOT NULL, term_id bigint unsigned NOT NULL, position int unsigned NOT NULL DEFAULT 0, UNIQUE KEY member (group_id,term_id), KEY group_position (group_id,position), KEY term (term_id)',
			// Curriculum membership of courses, skills, question banks and quizzes/exams (many to many).
			'curriculum_links'      => 'item_id bigint unsigned NOT NULL, object_type varchar(20) NOT NULL, object_id bigint unsigned NOT NULL, created_by bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, UNIQUE KEY link (item_id,object_type,object_id), KEY object (object_type,object_id)',
			// Explicit, directional links from a curriculum-specific skill to a shared skill.
			'skill_mappings'        => "specific_term_id bigint unsigned NOT NULL, shared_term_id bigint unsigned NOT NULL, relation varchar(20) NOT NULL DEFAULT 'equivalent', group_uuid char(36) NOT NULL DEFAULT '', note text NULL, source varchar(20) NOT NULL DEFAULT 'manual', confidence decimal(4,3) NULL, created_by bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, updated_at datetime NOT NULL, UNIQUE KEY mapping (specific_term_id,shared_term_id), KEY shared (shared_term_id)",
			'skill_mapping_groups'  => 'uuid char(36) NOT NULL, source_id bigint unsigned NOT NULL, shared_term_id bigint unsigned NOT NULL, member_ids text NOT NULL, created_by bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, UNIQUE KEY uuid (uuid), KEY shared (shared_term_id)',
			// Links between shared skills. A prerequisite edge records who and what suggested a prerequisite kept
			// in the skill's prerequisite list; an encompasses edge says that using the skill implicitly practises
			// the other, by a weight from 0 to 1. Both are read by the guidance engine.
			'skill_edges'           => "skill_id bigint unsigned NOT NULL, other_id bigint unsigned NOT NULL, type varchar(20) NOT NULL, weight decimal(4,3) NOT NULL DEFAULT 1, source varchar(20) NOT NULL DEFAULT 'manual', confidence decimal(4,3) NULL, note text NULL, created_by bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, UNIQUE KEY edge (skill_id,other_id,type), KEY other (other_id,type)",
			// AI analysis of a syllabus or of shared skills, run in slices; see Curriculum\AiAnalysis.
			'ai_analysis_runs'      => "uuid char(36) NOT NULL, kind varchar(20) NOT NULL, syllabus_id bigint unsigned NOT NULL DEFAULT 0, subject varchar(100) NOT NULL DEFAULT '', status varchar(20) NOT NULL DEFAULT 'queued', total int unsigned NOT NULL DEFAULT 0, processed int unsigned NOT NULL DEFAULT 0, suggested int unsigned NOT NULL DEFAULT 0, failures int unsigned NOT NULL DEFAULT 0, model varchar(100) NOT NULL DEFAULT '', prompt varchar(20) NOT NULL DEFAULT '', profile varchar(40) NOT NULL DEFAULT '', profile_rev varchar(20) NOT NULL DEFAULT '', error text NULL, queue longtext NULL, created_by bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, updated_at datetime NOT NULL, UNIQUE KEY uuid (uuid), KEY status (status,id)",
			// One AI suggestion awaiting a person: a mapping, a new shared skill, a domain and grade band, or a prerequisite.
			'ai_suggestions'        => "run_id bigint unsigned NOT NULL, kind varchar(20) NOT NULL, specific_term_id bigint unsigned NOT NULL DEFAULT 0, shared_term_id bigint unsigned NOT NULL DEFAULT 0, relation varchar(20) NOT NULL DEFAULT '', payload longtext NULL, confidence decimal(4,3) NOT NULL DEFAULT 0, reason text NULL, status varchar(20) NOT NULL DEFAULT 'pending', result_term_id bigint unsigned NOT NULL DEFAULT 0, source varchar(40) NOT NULL DEFAULT '', subject varchar(100) NOT NULL DEFAULT '', course_id bigint unsigned NOT NULL DEFAULT 0, target varchar(200) NOT NULL DEFAULT '', reviewed_by bigint unsigned NOT NULL DEFAULT 0, reviewed_at datetime NULL, created_at datetime NOT NULL, KEY run (run_id,status), KEY objective_status (specific_term_id,status), KEY review (status,kind,confidence), KEY course (course_id,status)",
			// Advisory links between courses: sub-courses, prerequisites and "get ready for" suggestions. They never gate access.
			'course_links'          => "course_id bigint unsigned NOT NULL, related_course_id bigint unsigned NOT NULL, link_type varchar(20) NOT NULL DEFAULT 'subcourse', position int unsigned NOT NULL DEFAULT 0, created_by bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, UNIQUE KEY course_link (course_id,related_course_id,link_type), KEY related (related_course_id,link_type)",
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
		if ( self::ready() ) {
			return true; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( self::definitions() as $name => $definition ) {
			$table   = self::table( $name );
			$columns = str_replace( ', ', ",\n", $definition );
			dbDelta( "CREATE TABLE $table (\n id bigint unsigned NOT NULL AUTO_INCREMENT,\n $columns,\n PRIMARY KEY  (id)\n) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';' );
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
				return false; }
		}
		self::drop_bundles();
		// Items typed "syllabus" before the flag existed are syllabuses. Safe to repeat: it only sets the flag.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET is_syllabus=1 WHERE item_type=\'syllabus\' AND is_syllabus=0', self::table( 'curriculum_items' ) ) );
		update_option( self::OPTION, self::VERSION, false );
		do_action( 'ohmylms_curriculum_schema_installed', self::VERSION );
		return true;
	}

	/**
	 * Course Bundles were removed: drop their tables and the membership-plan meta that named them.
	 * Safe to repeat.
	 */
	private static function drop_bundles() {
		global $wpdb;
		foreach ( array( 'bundle_follows', 'bundle_items', 'bundles', 'track_follows', 'track_items', 'tracks' ) as $name ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table( $name ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
	}
}
