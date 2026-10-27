<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Migrations;

/**
 * Plugin migration implementation.
 */
class Migration {

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
}
