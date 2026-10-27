<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin schema implementation.
 */
final class Schema {
	const VERSION = '1';
	const OPTION  = 'ohmylms_learning_schema';

	/**
	 * Table for Schema.
	 *
	 * @param mixed $name Name.
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_learning_' . $name;
	}

	/**
	 * Ready for Schema.
	 */
	public static function ready() {
		return get_option( self::OPTION ) === self::VERSION; }

	/**
	 * Install for Schema.
	 */
	public static function install() {
		if ( self::ready() ) {
			return; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$definitions = array(
			'programs'    => 'course_id bigint unsigned NOT NULL, version int unsigned NOT NULL, mode varchar(20) NOT NULL, program longtext NOT NULL, created_by bigint unsigned NOT NULL, created_at datetime NOT NULL, UNIQUE KEY course_version (course_id,version)',
			'outcomes'    => 'program_id bigint unsigned NOT NULL, term_id bigint unsigned NOT NULL, target varchar(20) NOT NULL, required tinyint NOT NULL DEFAULT 1, UNIQUE KEY program_skill (program_id,term_id), KEY skill (term_id,program_id)',
			'enrollments' => 'enrollment_id bigint unsigned NOT NULL, program_id bigint unsigned NOT NULL DEFAULT 0, bound_at datetime NOT NULL, UNIQUE KEY enrollment (enrollment_id), KEY program (program_id)',
			'awards'      => 'enrollment_id bigint unsigned NOT NULL, program_id bigint unsigned NOT NULL, snapshot longtext NOT NULL, awarded_at datetime NOT NULL, UNIQUE KEY enrollment (enrollment_id)',
		);
		foreach ( $definitions as $name => $definition ) {
			$table   = self::table( $name );
			$columns = str_replace( ', ', ",\n", $definition );
			dbDelta( "CREATE TABLE $table (\n id bigint unsigned NOT NULL AUTO_INCREMENT,\n $columns,\n PRIMARY KEY  (id)\n) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';' );
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
				return; }
		}
		update_option( self::OPTION, self::VERSION, false );
	}
}
