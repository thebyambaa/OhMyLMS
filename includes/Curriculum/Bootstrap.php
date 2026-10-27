<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Wires curriculum storage, cleanup of references to deleted content, and the learner dashboard.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Bundles\Follows;
use OhMyLMS\Bundles\Bundles;

defined( 'ABSPATH' ) || exit;

/** Wires curriculum storage, cleanup of references to deleted content, and the learner dashboard. */
final class Bootstrap {
	/**
	 * Init for Bootstrap.
	 */
	public static function init() {
		add_action( 'init', array( Schema::class, 'install' ), 7 );
		add_action( 'before_delete_post', array( __CLASS__, 'post_deleted' ) );
		// Core fires `delete_term` after the term is gone from the database; there is no `deleted_term`.
		add_action( 'delete_term', array( __CLASS__, 'term_deleted' ), 10, 3 );
		add_action( 'deleted_user', array( Follows::class, 'remove_user' ) );
		\OhMyLMS\Bundles\Frontend::init();
		SubjectProgress::init();
		add_action( 'init', array( SharedContent::class, 'register' ), 11 );
		Directory::init();
	}

	/** A deleted course or quiz/exam leaves no curriculum links, and a deleted course leaves no bundle slot.
	 *
	 * @param mixed $post_id Post identifier.
	 */
	public static function post_deleted( $post_id ) {
		if ( ! Schema::ready() ) {
			return; }
		$type = get_post_type( $post_id );
		if ( OHMYLMS_COURSE_CPT === $type ) {
			Links::remove_object( 'course', $post_id );
			CourseLinks::remove_course( $post_id );
			Bundles::remove_member( 'course', $post_id );
		} elseif ( OHMYLMS_QUIZ_CPT === $type ) {
			Links::remove_object( 'quiz', $post_id );
		}
	}

	/** A deleted skill leaves no curriculum links or mappings. Learner evidence is handled by the skills module.
	 *
	 * @param mixed $term_id Term identifier.
	 * @param mixed $tt_id Tt identifier.
	 * @param mixed $taxonomy Taxonomy.
	 */
	public static function term_deleted( $term_id, $tt_id, $taxonomy ) {
		if ( $taxonomy !== Taxonomy::NAME || ! Schema::ready() ) {
			return; }
		Links::remove_object( 'skill', $term_id );
		SkillMappings::remove_skill( $term_id );
		Syllabus::remove_term( $term_id );
	}
}
