<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Additive, independently versioned school schema. Existing learning tables stay intact.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/** Additive, independently versioned school schema. Existing learning tables stay intact. */
final class Schema {
	const VERSION = '2';
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
	 * Install for Schema.
	 */
	public static function install() {
		if ( get_option( 'ohmylms_school_schema' ) === self::VERSION ) {
			return; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$definitions = array(
			'course_classes'          => 'course_id bigint unsigned NOT NULL, class_id bigint unsigned NOT NULL, created_by bigint unsigned NOT NULL, created_at datetime NOT NULL, UNIQUE KEY course_class (course_id,class_id), KEY class_id (class_id)',
			'course_class_students'   => 'course_id bigint unsigned NOT NULL, class_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, created_at datetime NOT NULL, UNIQUE KEY course_class_student (course_id,class_id,user_id), KEY course_user (course_id,user_id)',
			'gradebook_overrides'     => 'course_id bigint unsigned NOT NULL, content_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, score decimal(12,4) NOT NULL, max_score decimal(12,4) NOT NULL, note text NOT NULL, updated_by bigint unsigned NOT NULL, updated_at datetime NOT NULL, UNIQUE KEY grade_cell (course_id,content_id,user_id)',
			'schools'                 => "name varchar(190) NOT NULL, slug varchar(190) NOT NULL, timezone varchar(80) NOT NULL DEFAULT 'UTC', status varchar(20) NOT NULL DEFAULT 'active', created_by bigint unsigned NOT NULL, created_at datetime NOT NULL, UNIQUE KEY slug (slug)",
			'school_memberships'      => "school_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, role varchar(30) NOT NULL, status varchar(20) NOT NULL DEFAULT 'active', joined_at datetime NOT NULL, UNIQUE KEY membership (school_id,user_id,role), KEY user_scope (user_id,status), KEY school_scope (school_id,status)",
			'school_student_profiles' => 'school_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, external_student_id varchar(100) NOT NULL, UNIQUE KEY student (school_id,user_id), UNIQUE KEY external_student (school_id,external_student_id)',
			'academic_years'          => "school_id bigint unsigned NOT NULL, label varchar(100) NOT NULL, starts_at date NOT NULL, ends_at date NOT NULL, status varchar(20) NOT NULL DEFAULT 'active', UNIQUE KEY school_year (school_id,label)",
			'classes'                 => "school_id bigint unsigned NOT NULL, academic_year_id bigint unsigned NOT NULL, name varchar(190) NOT NULL, subject varchar(100) NOT NULL DEFAULT '', grade varchar(50) NOT NULL DEFAULT '', status varchar(20) NOT NULL DEFAULT 'active', KEY school_year (school_id,academic_year_id,status)",
			'class_memberships'       => "class_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, role varchar(20) NOT NULL, status varchar(20) NOT NULL DEFAULT 'active', joined_at datetime NOT NULL, UNIQUE KEY membership (class_id,user_id,role), KEY user_scope (user_id,status)",
			'guardian_links'          => "school_id bigint unsigned NOT NULL, guardian_user_id bigint unsigned NOT NULL, student_user_id bigint unsigned NOT NULL, status varchar(20) NOT NULL DEFAULT 'active', approved_by bigint unsigned NOT NULL, approved_at datetime NOT NULL, UNIQUE KEY guardian (school_id,guardian_user_id,student_user_id), KEY child (student_user_id,status), KEY parent_scope (guardian_user_id,status)",
			'school_invitations'      => 'token_hash char(64) NOT NULL, school_id bigint unsigned NOT NULL, class_id bigint unsigned NOT NULL DEFAULT 0, student_user_id bigint unsigned NOT NULL DEFAULT 0, role varchar(30) NOT NULL, email varchar(190) NOT NULL, expires_at datetime NOT NULL, consumed_at datetime NULL, revoked_at datetime NULL, invited_by bigint unsigned NOT NULL, created_at datetime NOT NULL, UNIQUE KEY token_hash (token_hash), KEY school_scope (school_id,expires_at)',
			'learning_assignments'    => "school_id bigint unsigned NOT NULL, class_id bigint unsigned NOT NULL, creator_id bigint unsigned NOT NULL, title varchar(190) NOT NULL, course_id bigint unsigned NOT NULL, content_id bigint unsigned NOT NULL DEFAULT 0, due_at datetime NULL, status varchar(20) NOT NULL DEFAULT 'active', prior_completion tinyint NOT NULL DEFAULT 0, created_at datetime NOT NULL, KEY class_scope (class_id,status)",
			'assignment_recipients'   => 'assignment_id bigint unsigned NOT NULL, student_user_id bigint unsigned NOT NULL, assigned_at datetime NOT NULL, completed_at datetime NULL, UNIQUE KEY recipient (assignment_id,student_user_id), KEY student_scope (student_user_id,completed_at)',
			'school_audit_log'        => 'school_id bigint unsigned NOT NULL DEFAULT 0, actor_id bigint unsigned NOT NULL, action varchar(60) NOT NULL, object_id bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, KEY school_time (school_id,created_at)',
		);
		foreach ( $definitions as $name => $definition ) {
			$table   = self::table( $name );
			$columns = str_replace( ', ', ",\n", $definition );
			dbDelta( "CREATE TABLE $table (\n id bigint unsigned NOT NULL AUTO_INCREMENT,\n $columns,\n PRIMARY KEY  (id)\n) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';' );
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
				return; }
		}
		add_role( 'ohmylms_parent', 'OhMyLMS Parent', array( 'read' => true ) );
		add_role( 'ohmylms_teacher', 'OhMyLMS Teacher', array( 'read' => true ) );
		add_role( 'ohmylms_school_admin', 'OhMyLMS School Administrator', array( 'read' => true ) );
		update_option( 'ohmylms_school_schema', self::VERSION, false );
	}
}
