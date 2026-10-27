<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Learning-skill catalogue. Independent of the student profile "skills" field,
 * which stays a free-text profile attribute.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Skills;

defined( 'ABSPATH' ) || exit;

/**
 * Learning-skill catalogue. Independent of the student profile "skills" field,
 * which stays a free-text profile attribute.
 *
 * Terms carry a stable UUID (for import/export), an optional short code and
 * prerequisite skills (acyclic). Lessons and courses are linked through the
 * same taxonomy; questions through QuestionBank\SkillMap.
 */
final class Taxonomy {
	const NAME = 'ohmylms_skill';

	/**
	 * Register for Taxonomy.
	 */
	public static function register() {
		register_taxonomy(
			self::NAME,
			array( OHMYLMS_QUESTION_CPT, OHMYLMS_LESSON_CPT, OHMYLMS_COURSE_CPT ),
			array(
				'labels'       => array(
					'name'          => __( 'Skills', 'ohmylms' ),
					'singular_name' => __( 'Skill', 'ohmylms' ),
					'add_new_item'  => __( 'Add skill', 'ohmylms' ),
					'edit_item'     => __( 'Edit skill', 'ohmylms' ),
					'search_items'  => __( 'Search skills', 'ohmylms' ),
				),
				'hierarchical' => true,
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'rewrite'      => false,
				'query_var'    => false,
				'capabilities' => array(
					'manage_terms' => 'edit_posts',
					'edit_terms'   => 'edit_posts',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_posts',
				),
			)
		);
		register_term_meta(
			self::NAME,
			'_ohmylms_skill_uuid',
			array(
				'type'   => 'string',
				'single' => true,
			)
		);
		register_term_meta(
			self::NAME,
			'_ohmylms_skill_code',
			array(
				'type'   => 'string',
				'single' => true,
			)
		);
		register_term_meta(
			self::NAME,
			'_ohmylms_prerequisites',
			array(
				'type'   => 'array',
				'single' => true,
			)
		);
	}

	/** Stable UUID for a skill, assigned on first use.
	 *
	 * @param mixed $term_id Term identifier.
	 */
	public static function uuid( $term_id ) {
		$term_id = (int) $term_id;
		if ( ! $term_id ) {
			return ''; }
		$uuid = (string) get_term_meta( $term_id, '_ohmylms_skill_uuid', true );
		if ( '' === $uuid && term_exists( $term_id, self::NAME ) ) {
			$uuid = wp_generate_uuid4();
			add_term_meta( $term_id, '_ohmylms_skill_uuid', $uuid, true ) || ( $uuid = (string) get_term_meta( $term_id, '_ohmylms_skill_uuid', true ) );
		}
		return $uuid;
	}

	/**
	 * By uuid for Taxonomy.
	 *
	 * @param mixed $uuid Uuid.
	 */
	public static function by_uuid( $uuid ) {
		$terms = get_terms(
			array(
				'taxonomy'   => self::NAME,
				'hide_empty' => false,
				'meta_key'   => '_ohmylms_skill_uuid',
				'meta_value' => (string) $uuid,
				'number'     => 1,
				'fields'     => 'ids',
			)
		);
		return is_array( $terms ) && $terms ? (int) $terms[0] : 0;
	}

	/**
	 * Prerequisites for Taxonomy.
	 *
	 * @return int[]
	 * @param mixed $term_id Term identifier.
	 */
	public static function prerequisites( $term_id ) {
		$value = get_term_meta( (int) $term_id, '_ohmylms_prerequisites', true );
		return array_values( array_filter( array_map( 'intval', is_array( $value ) ? $value : array() ) ) );
	}

	/** Set prerequisites after rejecting unknown terms, self-references and cycles.
	 *
	 * @param mixed $term_id Term identifier.
	 * @param array $prerequisites Prerequisites.
	 */
	public static function set_prerequisites( $term_id, array $prerequisites ) {
		$term_id       = (int) $term_id;
		$prerequisites = array_values( array_unique( array_filter( array_map( 'intval', $prerequisites ) ) ) );
		foreach ( $prerequisites as $id ) {
			if ( $id === $term_id ) {
				return new \WP_Error( 'ohmylms_skill_cycle', __( 'A skill cannot be its own prerequisite.', 'ohmylms' ), array( 'status' => 400 ) ); }
			if ( ! term_exists( $id, self::NAME ) ) {
				return new \WP_Error( 'ohmylms_skill_missing', __( 'A prerequisite skill does not exist.', 'ohmylms' ), array( 'status' => 400 ) ); }
			if ( self::reaches( $id, $term_id ) ) {
				return new \WP_Error(
					'ohmylms_skill_cycle',
					__( 'These prerequisites would create a cycle.', 'ohmylms' ),
					array(
						'status'  => 400,
						'term_id' => $id,
					)
				);
			}
		}
		update_term_meta( $term_id, '_ohmylms_prerequisites', $prerequisites );
		return $prerequisites;
	}

	/** Does following prerequisites from $from eventually reach $target?
	 *
	 * @param mixed $from From.
	 * @param mixed $target Target.
	 * @param array $seen Seen.
	 */
	public static function reaches( $from, $target, array $seen = array() ) {
		if ( (int) $from === (int) $target ) {
			return true; }
		if ( isset( $seen[ $from ] ) ) {
			return false; }
		$seen[ $from ] = true;
		foreach ( self::prerequisites( $from ) as $next ) {
			if ( self::reaches( $next, $target, $seen ) ) {
				return true; }
		}
		return false;
	}

	/**
	 * WordPress stores a term's name and notes with HTML entities ("a < 0" is saved as "a &lt; 0"). The JSON
	 * API and the editors want the text itself, which the page escapes when it draws it, so objectives full
	 * of < and > (as maths syllabuses are) read correctly instead of showing "&lt;".
	 *
	 * @param mixed $value Value to process.
	 */
	public static function plain( $value ) {
		return html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/** Public, JSON-safe description of a skill.
	 *
	 * @param mixed $term Term.
	 */
	public static function describe( $term ) {
		$term = $term instanceof \WP_Term ? $term : get_term( (int) $term, self::NAME );
		if ( ! $term || is_wp_error( $term ) ) {
			return null; }
		return array(
			'id'              => (int) $term->term_id,
			'uuid'            => self::uuid( $term->term_id ),
			'name'            => self::plain( $term->name ),
			'objective'       => Objectives::get( $term ),
			'translations'    => Translations::get( $term->term_id ),
			'slug'            => $term->slug,
			'code'            => (string) get_term_meta( $term->term_id, '_ohmylms_skill_code', true ),
			'description'     => self::plain( $term->description ),
			'parent'          => (int) $term->parent,
			'prerequisites'   => self::prerequisites( $term->term_id ),
			'public_practice' => (bool) get_term_meta( $term->term_id, '_ohmylms_public_practice', true ),
			'lessons'         => self::linked_posts( $term->term_id, OHMYLMS_LESSON_CPT ),
			'courses'         => self::linked_posts( $term->term_id, OHMYLMS_COURSE_CPT ),
		);
	}

	/**
	 * Linked posts for Taxonomy.
	 *
	 * @return int[]
	 * @param mixed $term_id Term identifier.
	 * @param mixed $post_type Post type.
	 */
	public static function linked_posts( $term_id, $post_type ) {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'   => $post_type,
					'post_status' => 'any',
					'fields'      => 'ids',
					'numberposts' => 200,
					'tax_query'   => array(
						array(
							'taxonomy'         => self::NAME,
							'field'            => 'term_id',
							'terms'            => (int) $term_id,
							'include_children' => false,
						),
					),
				)
			)
		);
	}
}
