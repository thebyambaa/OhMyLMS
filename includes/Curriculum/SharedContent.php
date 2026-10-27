<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Content owned by reusable skills.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/** Reuse taxonomy links; mappings reference content without copying it. */
final class SharedContent {
	/**
	 * Supported content types, extensible by trusted plugin code.
	 *
	 * @return array
	 */
	public static function types() {
		return (array) apply_filters(
			'ohmylms_shared_skill_content_types',
			array(
				'lesson'     => OHMYLMS_LESSON_CPT,
				'quiz'       => OHMYLMS_QUIZ_CPT,
				'assignment' => OHMYLMS_ASSIGNMENT_CPT,
				'course'     => OHMYLMS_COURSE_CPT,
			)
		);
	}

	/** Enable skill links on quizzes, assignments and extension content. */
	public static function register() {
		foreach ( self::types() as $post_type ) {
			register_taxonomy_for_object_type( Taxonomy::NAME, $post_type );
		}
	}

	/**
	 * Read linked resources or search editable targets, 20 per page.
	 *
	 * @param int   $id Shared skill ID.
	 * @param array $params Kind, page and optional search_targets/search.
	 * @return array|\WP_Error
	 */
	public static function listing( $id, array $params ) {
		$kind  = sanitize_key( $params['kind'] ?? 'lesson' );
		$types = self::types();
		if ( ! isset( $types[ $kind ] ) || ! metadata_exists( 'term', $id, Comparison::SUBJECT_META ) ) {
			return Access::error( 'ohmylms_content_invalid', __( 'Choose a shared skill and supported content type.', 'ohmylms' ) );
		}
		$args = array(
			'post_type'      => $types[ $kind ],
			'post_status'    => 'any',
			'posts_per_page' => 20,
			'paged'          => max( 1, (int) ( $params['page'] ?? 1 ) ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( ! empty( $params['search_targets'] ) ) {
			$args['s'] = sanitize_text_field( $params['search'] ?? '' );
		} else {
			$args['tax_query'] = array(
				array(
					'taxonomy'         => Taxonomy::NAME,
					'field'            => 'term_id',
					'terms'            => (int) $id,
					'include_children' => false,
				),
			);
		}
		$query = new \WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$items[] = array(
					'id'       => (int) $post->ID,
					'title'    => get_the_title( $post ),
					'kind'     => $kind,
					'status'   => $post->post_status,
					'linked'   => has_term( (int) $id, Taxonomy::NAME, $post ),
					'edit_url' => get_edit_post_link( $post->ID, 'raw' ),
				);
			}
		}
		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
			'types' => array_keys( $types ),
		);
	}

	/**
	 * Add/remove a single link without replacing other skills or content.
	 *
	 * @param int  $skill_id Shared ID.
	 * @param int  $post_id Content ID.
	 * @param bool $linked Whether to attach.
	 * @return array|\WP_Error
	 */
	public static function link( $skill_id, $post_id, $linked ) {
		if ( ! metadata_exists( 'term', $skill_id, Comparison::SUBJECT_META ) || ! term_exists( $skill_id, Taxonomy::NAME ) || ! in_array( get_post_type( $post_id ), self::types(), true ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return Access::error( 'ohmylms_content_forbidden', __( 'You cannot connect this content to the shared skill.', 'ohmylms' ), 403 );
		}
		$result = $linked ? wp_set_object_terms( $post_id, array( (int) $skill_id ), Taxonomy::NAME, true ) : wp_remove_object_terms( $post_id, array( (int) $skill_id ), Taxonomy::NAME );
		return is_wp_error( $result ) ? $result : array(
			'linked'     => (bool) $linked,
			'skill_id'   => (int) $skill_id,
			'content_id' => (int) $post_id,
		);
	}

	/**
	 * Resolve a source objective's shared resource owners, without copies.
	 *
	 * @param int $id Objective ID.
	 * @return array
	 */
	public static function owners( $id ) {
		return array_values(
			array_filter(
				SkillMappings::for_skill( $id )['maps_to'],
				static function ( $mapping ) {
					return 'equivalent' === $mapping['relation'];
				}
			)
		);
	}

	/**
	 * Published resource links, without content bodies or assessment answers.
	 * Normal destination access rules still apply.
	 *
	 * @param int $id Shared skill ID.
	 * @return array
	 */
	public static function published( $id ) {
		$items = array();
		foreach ( self::types() as $kind => $type ) {
			foreach ( get_posts(
				array(
					'post_type'   => $type,
					'post_status' => 'publish',
					'numberposts' => 20,
					'orderby'     => 'title',
					'order'       => 'ASC',
					'tax_query'   => array(
						array(
							'taxonomy'         => Taxonomy::NAME,
							'field'            => 'term_id',
							'terms'            => (int) $id,
							'include_children' => false,
						),
					),
				)
			) as $post ) {
				$items[] = array(
					'id'    => (int) $post->ID,
					'title' => get_the_title( $post ),
					'kind'  => $kind,
					'url'   => get_permalink( $post ),
				);
			}
		}
		return $items;
	}
}
