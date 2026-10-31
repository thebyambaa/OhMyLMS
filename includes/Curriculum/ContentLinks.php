<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Link one lesson, quiz, assignment, course or question to several skills and syllabus objectives.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\QuestionBank\SkillMap;
use OhMyLMS\Skills\Provenance;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * The content side of shared content links. Lessons, quizzes, assignments, courses and extension
 * content types (`ohmylms_shared_skill_content_types`) link through the skill taxonomy, as
 * SharedContent does. A question links as a supporting skill of its draft skill map; its primary
 * skills belong to the question editor and are never changed here. Writes add and remove named
 * skills only, under a per-content lock, against the revision the author read (409 when stale).
 */
final class ContentLinks {

	/** Most skills one write may add or remove. */
	const MAX_CHANGES = 50;

	/** Content per search page. */
	const PER_PAGE = 20;

	/**
	 * Content kinds and their post types.
	 *
	 * @return array<string,string>
	 */
	public static function kinds() {
		return SharedContent::types() + array( 'question' => OHMYLMS_QUESTION_CPT );
	}

	/**
	 * Search editable content of a kind.
	 *
	 * @param string $kind   Content kind.
	 * @param string $search Title search.
	 * @param int    $page   Page.
	 * @return array|\WP_Error items, total, pages.
	 */
	public static function search( $kind, $search, $page ) {
		$kinds = self::kinds();
		if ( ! isset( $kinds[ $kind ] ) ) {
			return self::invalid_kind();
		}
		$query = new \WP_Query(
			array(
				'post_type'      => $kinds[ $kind ],
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => self::PER_PAGE,
				'paged'          => max( 1, (int) $page ),
				's'              => (string) $search,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => false,
			)
		);
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'       => (int) $post->ID,
				'kind'     => $kind,
				'title'    => get_the_title( $post ),
				'status'   => (string) $post->post_status,
				'editable' => current_user_can( 'edit_post', $post->ID ),
			);
		}
		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * The skills a piece of content is linked to, with the revision writes must send back.
	 *
	 * @param string $kind    Content kind.
	 * @param int    $post_id Content ID.
	 * @return array|\WP_Error
	 */
	public static function read( $kind, $post_id ) {
		$post = self::post( $kind, $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$links  = self::links( $kind, (int) $post->ID );
		$skills = array();
		foreach ( $links as $term_id => $role ) {
			$term = get_term( $term_id, Taxonomy::NAME );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$skills[] = array(
				'id'         => (int) $term_id,
				'name'       => Taxonomy::plain( $term->name ),
				'code'       => (string) get_term_meta( $term_id, Syllabus::CODE_META, true ),
				'shared'     => metadata_exists( 'term', $term_id, Comparison::SUBJECT_META ),
				'role'       => $role,
				'archived'   => Provenance::archived( $term_id ),
				'syllabuses' => self::syllabus_names( $term_id ),
			);
		}
		return array(
			'content'  => array(
				'id'     => (int) $post->ID,
				'kind'   => $kind,
				'title'  => get_the_title( $post ),
				'status' => (string) $post->post_status,
			),
			'skills'   => $skills,
			'revision' => self::revision( $links ),
		);
	}

	/**
	 * Add and remove named skills on one piece of content.
	 *
	 * @param string $kind              Content kind.
	 * @param int    $post_id           Content ID.
	 * @param int[]  $add               Skills to link.
	 * @param int[]  $remove            Skills to unlink.
	 * @param string $expected_revision Revision from read().
	 * @return array|\WP_Error The content's links afterwards.
	 */
	public static function update( $kind, $post_id, array $add, array $remove, $expected_revision ) {
		global $wpdb;
		$post = self::post( $kind, $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$add    = array_values( array_unique( array_map( 'intval', $add ) ) );
		$remove = array_values( array_unique( array_map( 'intval', $remove ) ) );
		if ( ( ! $add && ! $remove ) || count( $add ) + count( $remove ) > self::MAX_CHANGES || array_intersect( $add, $remove ) ) {
			/* translators: %d: most skills in one change. */
			return Access::error( 'ohmylms_content_links_invalid', sprintf( __( 'Add or remove between 1 and %d different skills.', 'ohmylms' ), self::MAX_CHANGES ) );
		}
		foreach ( array_merge( $add, $remove ) as $term_id ) {
			if ( $term_id < 1 || ! term_exists( $term_id, Taxonomy::NAME ) ) {
				return Access::error( 'ohmylms_skill_missing', __( 'A chosen skill does not exist.', 'ohmylms' ), 404, array( 'term_id' => $term_id ) );
			}
		}
		foreach ( $add as $term_id ) {
			if ( Provenance::archived( $term_id ) ) {
				return Access::error( 'ohmylms_skill_archived', __( 'Archived skills cannot be linked to more content. Restore the skill first.', 'ohmylms' ), 409, array( 'term_id' => $term_id ) );
			}
		}
		$lock = 'ohmylms-content-links-' . md5( $wpdb->prefix ) . '-' . (int) $post->ID;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Non-cacheable advisory lock.
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			return Access::error( 'ohmylms_content_links_busy', __( 'Another change to these links is being saved. Try again.', 'ohmylms' ), 409 );
		}
		try {
			$links = self::links( $kind, (int) $post->ID );
			if ( ! hash_equals( self::revision( $links ), (string) $expected_revision ) ) {
				return Access::error( 'ohmylms_content_links_stale', __( 'The skills of this content changed. Reload them before saving.', 'ohmylms' ), 409, array( 'revision' => self::revision( $links ) ) );
			}
			$saved = 'question' === $kind
				? self::update_question( (int) $post->ID, $links, $add, $remove )
				: self::update_terms( (int) $post->ID, $add, $remove );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Release the advisory lock.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
		return self::read( $kind, (int) $post->ID );
	}

	/**
	 * Link and unlink through the skill taxonomy without replacing other skills.
	 *
	 * @param int   $post_id Content ID.
	 * @param int[] $add     Skills to link.
	 * @param int[] $remove  Skills to unlink.
	 * @return true|\WP_Error
	 */
	private static function update_terms( $post_id, array $add, array $remove ) {
		if ( $add ) {
			$result = wp_set_object_terms( $post_id, $add, Taxonomy::NAME, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		if ( $remove ) {
			$result = wp_remove_object_terms( $post_id, $remove, Taxonomy::NAME );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}

	/**
	 * Change a question's supporting skills in its draft skill map. Primary skills are refused.
	 *
	 * @param int   $question_id Question ID.
	 * @param array $links       Current links (term ID => role).
	 * @param int[] $add         Skills to add as supporting.
	 * @param int[] $remove      Supporting skills to remove.
	 * @return true|\WP_Error
	 */
	private static function update_question( $question_id, array $links, array $add, array $remove ) {
		foreach ( $remove as $term_id ) {
			if ( 'primary' === ( $links[ $term_id ] ?? '' ) ) {
				return Access::error( 'ohmylms_content_links_primary', __( 'A question’s primary skill is changed in the question editor.', 'ohmylms' ), 409, array( 'term_id' => $term_id ) );
			}
		}
		$map  = SkillMap::current( $question_id );
		$part = $map ? array_key_first( $map ) : 'p1';
		if ( ! isset( $map[ $part ] ) ) {
			$map[ $part ] = array(
				'primary'    => 0,
				'supporting' => array(),
			);
		}
		foreach ( $map as $key => $roles ) {
			$map[ $key ]['supporting'] = array_values( array_diff( $roles['supporting'], $remove ) );
		}
		foreach ( $add as $term_id ) {
			if ( ! isset( $links[ $term_id ] ) ) {
				$map[ $part ]['supporting'][] = $term_id;
			}
		}
		SkillMap::save( $question_id, $map );
		return true;
	}

	/**
	 * Current links of a piece of content: term ID => role (`linked`, `primary` or `supporting`).
	 *
	 * @param string $kind    Content kind.
	 * @param int    $post_id Content ID.
	 * @return array<int,string>
	 */
	private static function links( $kind, $post_id ) {
		$links = array();
		if ( 'question' === $kind ) {
			foreach ( SkillMap::current( $post_id ) as $roles ) {
				if ( $roles['primary'] ) {
					$links[ (int) $roles['primary'] ] = 'primary';
				}
				foreach ( $roles['supporting'] as $term_id ) {
					$links += array( (int) $term_id => 'supporting' );
				}
			}
		} else {
			$ids = wp_get_object_terms( $post_id, Taxonomy::NAME, array( 'fields' => 'ids' ) );
			foreach ( is_wp_error( $ids ) ? array() : $ids as $term_id ) {
				$links[ (int) $term_id ] = 'linked';
			}
		}
		ksort( $links );
		return $links;
	}

	/**
	 * Revision of a set of links.
	 *
	 * @param array $links Term ID => role.
	 * @return string 64 hexadecimal characters.
	 */
	public static function revision( array $links ) {
		ksort( $links );
		return Comparison::revision( $links );
	}

	/**
	 * Names of the syllabuses that place a skill.
	 *
	 * @param int $term_id Skill term ID.
	 * @return string[]
	 */
	private static function syllabus_names( $term_id ) {
		$names = array();
		foreach ( Syllabus::item_ids_for_skill( $term_id ) as $item_id ) {
			$owner = Syllabus::owner( $item_id );
			$item  = $owner ? Items::get( $owner ) : null;
			if ( $item ) {
				$names[ $owner ] = (string) $item['name'];
			}
		}
		return array_values( $names );
	}

	/**
	 * The content, when it exists, has the kind's post type and the user may edit it.
	 *
	 * @param string $kind    Content kind.
	 * @param int    $post_id Content ID.
	 * @return \WP_Post|\WP_Error
	 */
	private static function post( $kind, $post_id ) {
		$kinds = self::kinds();
		if ( ! isset( $kinds[ $kind ] ) ) {
			return self::invalid_kind();
		}
		$post = get_post( (int) $post_id );
		if ( ! $post || $kinds[ $kind ] !== $post->post_type || 'trash' === $post->post_status ) {
			return Access::error( 'ohmylms_content_missing', __( 'Content not found.', 'ohmylms' ), 404 );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return Access::error( 'ohmylms_content_forbidden', __( 'You cannot change the skills of this content.', 'ohmylms' ), 403 );
		}
		return $post;
	}

	/**
	 * Error for an unknown content kind.
	 *
	 * @return \WP_Error
	 */
	private static function invalid_kind() {
		return Access::error( 'ohmylms_content_links_invalid', __( 'Choose a supported content type.', 'ohmylms' ) );
	}
}
