<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Migrations;

/**
 * Plugin migration implementation.
 */
class Migration {

	/**
	 * Meta key that links a migrated OhMyLMS course to its source (`<source>:<id>`).
	 */
	const SOURCE_META = '_ohmylms_migrated_from';

	/**
	 * Source.
	 *
	 * @var mixed
	 */
	public $source = null;


	/**
	 * Migration constructor.
	 *
	 * @param string|null $source The source for the migration (e.g., 'tutorLMS').
	 */
	public function __construct( $source = null ) {
		$this->source = $source;
	}


	/**
	 * Runs the migration for the given source.
	 *
	 * This method looks up the source in the sources array, creates the corresponding
	 * migration class, and returns an instance of it.
	 *
	 * @return object|null Returns the migration class instance or null if the source is not found.
	 */
	public function run() {
		$sources = $this->get_sources();

		if ( isset( $sources[ $this->source ] ) ) {
			$migration_class = $sources[ $this->source ]['class'];

			// Check if the class exists.
			if ( class_exists( $migration_class ) ) {
				return new $migration_class();
			}
		}

		return null; // If the source is not found, return null.
	}

	/**
	 * Retrieves the sources for the migration.
	 *
	 * @return array The sources for the migration.
	 * @since 1.0.0
	 */
	public function get_sources(): array {
		$sources = array(
			'tutorLMS'    => array(
				'title'       => 'Tutor LMS',
				'description' => 'Migrate Tutor LMS courses, lessons, quizzes and other data to OhMyLMS.',
				'class'       => 'OhMyLMS\\Migrations\\TutorLMS',
			),
			'learnDash'   => array(
				'title'       => 'LearnDash LMS',
				'description' => 'Migrate LearnDash LMS courses, lessons, quizzes and other data to OhMyLMS.',
				'class'       => 'OhMyLMS\\Migrations\\LearnDash',
			),
			'learnPress'  => array(
				'title'       => 'LearnPress',
				'description' => 'Migrate LearnPress courses, lessons, quizzes and other data to OhMyLMS.',
				'class'       => 'OhMyLMS\\Migrations\\LearnPress',
			),
			'masterStudy' => array(
				'title'       => 'MasterStudy LMS',
				'description' => 'Migrate MasterStudy LMS courses, lessons, quizzes and other data to OhMyLMS.',
				'class'       => 'OhMyLMS\\Migrations\\MasterStudy',
			),
		);

		// Allow filtering of the migration sources.
		$sources = apply_filters( 'ohmylms_migrations', $sources );

		return $sources;
	}

	/**
	 * OhMyLMS courses already migrated from some source courses: the newest live course of each.
	 *
	 * @param string $source Source id.
	 * @param int[]  $ids    Source course IDs.
	 * @return array<int,int> Source ID => OhMyLMS course ID.
	 */
	public static function migrated_courses( $source, array $ids ) {
		global $wpdb;
		if ( ! $ids ) {
			return array();
		}
		$keys    = array_map(
			static function ( $id ) use ( $source ) {
				return $source . ':' . (int) $id;
			},
			$ids
		);
		$holders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders are generated from the key count.
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT m.meta_value, MAX(p.ID) AS course_id FROM %i m INNER JOIN %i p ON p.ID = m.post_id AND p.post_type = 'ohmylms-course' AND p.post_status NOT IN ('trash','auto-draft') WHERE m.meta_key = %s AND m.meta_value IN ({$holders}) GROUP BY m.meta_value", array_merge( array( $wpdb->postmeta, $wpdb->posts, self::SOURCE_META ), $keys ) ), ARRAY_A );
		$found = array();
		foreach ( $rows as $row ) {
			$found[ (int) substr( (string) $row['meta_value'], strlen( $source ) + 1 ) ] = (int) $row['course_id'];
		}
		return $found;
	}
}
