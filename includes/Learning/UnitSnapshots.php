<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * Published unit metadata and ordering alongside existing program versions.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\Curriculum\UnitNumber;

defined( 'ABSPATH' ) || exit;

/** Native records are the authoring draft; public readers use a frozen projection. */
final class UnitSnapshots {

	const BASELINE = '_ohmylms_unit_baseline';
	/**
	 * Depth of trusted authoring callbacks.
	 *
	 * @var int
	 */
	private static $authoring_depth = 0;
	/**
	 * Request-local public projections, cleared after structure changes.
	 *
	 * @var array
	 */
	private static $public_cache = array();

	/** Clear request-local projections after commit or rollback. */
	public static function flush() {
		self::$public_cache = array();
	}

	/** Register projections for the legacy course templates and chapter objects. */
	public static function init() {
		add_filter( 'the_title', array( __CLASS__, 'title' ), 20, 2 );
		add_filter( 'ohmylms_chapter_get_name', array( __CLASS__, 'name' ), 20, 2 );
		add_filter( 'ohmylms_chapter_get_description', array( __CLASS__, 'description' ), 20, 2 );
	}

	/**
	 * Keep internal authoring callbacks independent of public view filters.
	 *
	 * @param callable $work Authoring operation.
	 * @return mixed
	 */
	public static function authoring( callable $work ) {
		++self::$authoring_depth;
		try {
			return $work();
		} finally {
			--self::$authoring_depth;
		}
	}

	/**
	 * Identify native authoring reads.
	 *
	 * @return bool
	 */
	public static function is_authoring() {
		return self::$authoring_depth > 0 || is_admin();
	}

	/**
	 * Capture unfiltered native unit metadata in relationship order.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function native( $course_id ) {
		global $wpdb;
		$ids   = $wpdb->get_col( $wpdb->prepare( 'SELECT chapter_id FROM %i WHERE course_id=%d ORDER BY order_number,id', $wpdb->prefix . 'ohmylms_chapter_relationship', $course_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh authoring snapshot under the course lock.
		$units = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || OHMYLMS_CHAPTER_CPT !== $post->post_type || 'trash' === $post->post_status ) {
				continue;
			}
			$units[] = array(
				'id'          => (int) $id,
				'name'        => html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ),
				'description' => $post->post_content,
				'number'      => (string) get_post_meta( $id, UnitNumber::META, true ),
			);
		}
		return $units;
	}

	/**
	 * Freeze pre-feature legacy metadata and native fallback before the first edit.
	 *
	 * Must run inside the same lock/transaction as the edit. Old program rows remain untouched.
	 *
	 * @param int $course_id Course ID.
	 * @throws \RuntimeException If the baseline cannot be stored.
	 */
	public static function capture( $course_id ) {
		if ( is_array( get_post_meta( $course_id, self::BASELINE, true ) ) ) {
			return;
		}
		$baseline = array(
			'units'   => self::native( $course_id ),
			'program' => CourseProgram::defaults( $course_id ),
		);
		if ( ! add_post_meta( $course_id, self::BASELINE, $baseline, true ) ) {
			throw new \RuntimeException( 'Unit baseline could not be stored.' );
		}
	}

	/**
	 * Metadata for an old program that predates unit snapshots.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function legacy_units( $course_id ) {
		$baseline = get_post_meta( $course_id, self::BASELINE, true );
		return is_array( $baseline ) ? $baseline['units'] : self::native( $course_id );
	}

	/**
	 * Original native fallback for an enrollment pinned to program zero.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function legacy_program( $course_id ) {
		$baseline         = get_post_meta( $course_id, self::BASELINE, true );
		$program          = is_array( $baseline ) ? $baseline['program'] : CourseProgram::defaults( $course_id );
		$program['units'] = self::legacy_units( $course_id );
		return $program;
	}

	/**
	 * Select current publication or the learner's existing program binding.
	 *
	 * @param int $course_id Course ID.
	 * @param int $student_id Learner ID, zero for visitors.
	 * @return array
	 */
	public static function published_program( $course_id, $student_id = 0 ) {
		$enrollment = $student_id ? CourseProgram::enrollment( $student_id, $course_id ) : null;
		if ( $enrollment && Schema::ready() ) {
			$program = CourseProgram::for_enrollment( $enrollment );
			return $program ? $program : self::legacy_program( $course_id );
		}
		$program = CourseProgram::current( $course_id );
		return $program ? $program : self::legacy_program( $course_id );
	}

	/**
	 * Legacy public templates share the learner's version selection.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function public_units( $course_id ) {
		$key = $course_id . ':' . get_current_user_id();
		if ( ! isset( self::$public_cache[ $key ] ) ) {
			self::$public_cache[ $key ] = self::published_program( $course_id, get_current_user_id() )['units'];
		}
		return self::$public_cache[ $key ];
	}

	/**
	 * Find one published unit for a legacy view, without filtering authoring reads.
	 *
	 * @param int $chapter_id Unit ID.
	 * @return array|null
	 */
	public static function public_unit( $chapter_id ) {
		if ( self::is_authoring() || OHMYLMS_CHAPTER_CPT !== get_post_type( $chapter_id ) ) {
			return null;
		}
		$course_id = CourseStructure::owner( $chapter_id );
		foreach ( $course_id ? self::public_units( $course_id ) : array() as $unit ) {
			if ( (int) $chapter_id === $unit['id'] ) {
				return $unit;
			}
		}
		return null;
	}

	/**
	 * Filter a native title in a public template.
	 *
	 * @param string $title Original title.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public static function title( $title, $post_id ) {
		$unit = self::public_unit( $post_id );
		return $unit ? $unit['name'] : $title;
	}

	/**
	 * Filter a chapter object's public name.
	 *
	 * @param string $name Original name.
	 * @param object $chapter Chapter object.
	 * @return string
	 */
	public static function name( $name, $chapter ) {
		return self::title( $name, $chapter->get_id() );
	}

	/**
	 * Filter a chapter object's public description.
	 *
	 * @param string $description Original description.
	 * @param object $chapter Chapter object.
	 * @return string
	 */
	public static function description( $description, $chapter ) {
		$unit = self::public_unit( $chapter->get_id() );
		return $unit ? $unit['description'] : $description;
	}
}
