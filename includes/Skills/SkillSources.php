<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Registry of skill sources.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

defined( 'ABSPATH' ) || exit;

/** Built-in sources plus those added through `ohmylms_skill_sources`. */
final class SkillSources {

	/**
	 * Sources resolved during this request.
	 *
	 * @var SkillSource[]|null
	 */
	private static $sources = null;

	/**
	 * Every valid source keyed by id.
	 *
	 * @return array<string,SkillSource>
	 */
	public static function all() {
		if ( null !== self::$sources ) {
			return self::$sources;
		}
		self::$sources = array();
		$built_in      = array( new CourseContentSource() );
		/**
		 * Filters the skill sources. Add an object implementing \OhMyLMS\Skills\SkillSource.
		 *
		 * @param SkillSource[] $sources Sources.
		 */
		$sources = apply_filters( 'ohmylms_skill_sources', $built_in );
		foreach ( is_array( $sources ) ? $sources : $built_in as $source ) {
			if ( $source instanceof SkillSource && preg_match( '/^[a-z][a-z0-9-]{1,39}$/', (string) $source->id() ) && ! in_array( $source->id(), Provenance::BUILT_IN, true ) ) {
				self::$sources[ $source->id() ] = $source;
			}
		}
		return self::$sources;
	}

	/**
	 * One source, or null.
	 *
	 * @param string $id Source id.
	 * @return SkillSource|null
	 */
	public static function get( $id ) {
		return self::all()[ (string) $id ] ?? null;
	}

	/**
	 * Sources for display: id, label and whether each can run now.
	 *
	 * @return array[]
	 */
	public static function describe() {
		$result = array();
		foreach ( self::all() as $source ) {
			$result[] = array(
				'id'        => $source->id(),
				'label'     => $source->label(),
				'available' => (bool) $source->available(),
			);
		}
		return $result;
	}

	/** Forget resolved sources, for tests that change the filter. */
	public static function flush() {
		self::$sources = null;
	}
}
