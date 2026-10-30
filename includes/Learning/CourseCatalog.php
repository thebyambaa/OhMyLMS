<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Bounded course summaries for the authored admin.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/** Course discovery and draft creation, independent of pricing and learner data. */
final class CourseCatalog {

	/**
	 * Return a minimal course reference.
	 *
	 * @param \WP_Post $post Course post.
	 * @return array
	 */
	public static function describe( $post ) {
		return array(
			'id'     => (int) $post->ID,
			'title'  => html_entity_decode( wp_strip_all_tags( $post->post_title ), ENT_QUOTES, 'UTF-8' ),
			'status' => $post->post_status,
		);
	}

	/**
	 * List editable courses with a bounded query and no learner records.
	 *
	 * @param string $search Search text.
	 * @param int    $page Page number.
	 * @return array
	 */
	public static function search( $search, $page ) {
		$args = array(
			'post_type'      => OHMYLMS_COURSE_CPT,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => 20,
			'paged'          => $page,
			's'              => $search,
			'orderby'        => array(
				'title' => 'ASC',
				'ID'    => 'ASC',
			),
		);
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		$query = new \WP_Query( $args );
		return array(
			'items' => array_values(
				array_map(
					array( self::class, 'describe' ),
					array_filter(
						$query->posts,
						static function ( $post ) {
							return Catalog::can_edit( $post->ID );
						}
					)
				)
			),
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}
}
