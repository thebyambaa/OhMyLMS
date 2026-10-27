<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Curriculum membership of existing content. A link only says "this content belongs under
 * this curriculum item": it never copies content, changes access, or sets a learning mode.
 * Content can sit under several items, in the same or different curricula.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\QuestionBank\Banks;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Curriculum membership of existing content. A link only says "this content belongs under
 * this curriculum item": it never copies content, changes access, or sets a learning mode.
 * Content can sit under several items, in the same or different curricula.
 *
 * Types: course, skill, bank (question bank) and quiz (a quiz or an exam, which is a quiz
 * with the exam preset).
 */
final class Links {
	const TYPES         = array( 'course', 'skill', 'bank', 'quiz' );
	const MAX_PER_ITEM  = 500;
	const POST_STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

	/**
	 * Table for Links.
	 */
	public static function table() {
		return Schema::table( 'curriculum_links' ); }

	/**
	 * Valid type for Links.
	 *
	 * @param mixed $type Type.
	 */
	public static function valid_type( $type ) {
		return in_array( $type, self::TYPES, true ); }

	/**
	 * Post type for Links.
	 *
	 * @param mixed $type Type.
	 */
	private static function post_type( $type ) {
		return 'course' === $type ? OHMYLMS_COURSE_CPT : ( 'quiz' === $type ? OHMYLMS_QUIZ_CPT : '' );
	}

	/** Does a link target of this type and ID exist (and is it not in the trash)?
	 *
	 * @param mixed $type Type.
	 * @param mixed $id Id.
	 */
	public static function target_exists( $type, $id ) {
		$id = (int) $id;
		if ( 0 >= $id ) {
			return false; }
		if ( 'course' === $type || 'quiz' === $type ) {
			$status = get_post_status( $id );
			return get_post_type( $id ) === self::post_type( $type ) && $status && ! in_array( $status, array( 'trash', 'auto-draft' ), true );
		}
		if ( 'skill' === $type ) {
			return (bool) term_exists( $id, Taxonomy::NAME ); }
		if ( 'bank' === $type ) {
			return AssessmentSchema::ready() && Banks::get( $id ) !== null; }
		return false;
	}

	/**
	 * Add for Links.
	 *
	 * @return true|\WP_Error
	 * @param mixed $item_id Item identifier.
	 * @param mixed $type Type.
	 * @param mixed $object_id Object identifier.
	 */
	public static function add( $item_id, $type, $object_id ) {
		global $wpdb;
		if ( ! Items::get( $item_id ) ) {
			return Items::missing(); }
		if ( ! self::valid_type( $type ) ) {
			return Access::error( 'ohmylms_link_invalid', __( 'Choose course, skill, question bank or quiz/exam content.', 'ohmylms' ) ); }
		if ( ! self::target_exists( $type, $object_id ) ) {
			return Access::error( 'ohmylms_link_invalid', __( 'That content does not exist.', 'ohmylms' ), 404 ); }
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE item_id=%d', (int) $item_id ) );
		if ( $count >= self::MAX_PER_ITEM ) {
			return Access::error( 'ohmylms_link_limit', sprintf( /* translators: %d: number. */
			__( 'A curriculum item can link to at most %d pieces of content.', 'ohmylms' ), self::MAX_PER_ITEM ), 409 ); }
		$ok = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . self::table() . ' (item_id, object_type, object_id, created_by, created_at) VALUES (%d, %s, %d, %d, %s)', (int) $item_id, $type, (int) $object_id, get_current_user_id(), current_time( 'mysql', true ) ) );
		if ( false === $ok ) {
			return Access::error( 'ohmylms_link_failed', __( 'The link could not be saved.', 'ohmylms' ), 500 ); }
		if ( 'course' === $type ) {
			Placement::changed(); }
		return true;
	}

	/** Removing a link that does not exist succeeds, so repeated requests are harmless.
	 *
	 * @param mixed $item_id Item identifier.
	 * @param mixed $type Type.
	 * @param mixed $object_id Object identifier.
	 */
	public static function remove( $item_id, $type, $object_id ) {
		global $wpdb;
		if ( ! Items::get( $item_id ) ) {
			return Items::missing(); }
		if ( ! self::valid_type( $type ) ) {
			return Access::error( 'ohmylms_link_invalid', __( 'Unknown content type.', 'ohmylms' ) ); }
		$wpdb->delete(
			self::table(),
			array(
				'item_id'     => (int) $item_id,
				'object_type' => $type,
				'object_id'   => (int) $object_id,
			)
		);
		if ( 'course' === $type ) {
			Placement::changed(); }
		return true;
	}

	/** Titles and status for known IDs of one type; deleted content is reported as unavailable.
	 *
	 * @param mixed $type Type.
	 * @param array $ids Ids.
	 */
	public static function labels( $type, array $ids ) {
		$ids    = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		$labels = array();
		foreach ( $ids as $id ) {
			if ( 'course' === $type || 'quiz' === $type ) {
				$post          = get_post( $id );
				$valid         = $post && $post->post_type === self::post_type( $type ) && 'trash' !== $post->post_status;
				$labels[ $id ] = array(
					'title'  => $valid ? ( get_the_title( $post ) ?: '#' . $id ) : '',
					'status' => $valid ? $post->post_status : 'missing',
				);
			} elseif ( 'skill' === $type ) {
				$term          = get_term( $id, Taxonomy::NAME );
				$valid         = $term && ! is_wp_error( $term );
				$labels[ $id ] = array(
					'title'  => $valid ? Taxonomy::plain( $term->name ) : '',
					'status' => $valid ? 'active' : 'missing',
					'code'   => $valid ? (string) get_term_meta( $id, '_ohmylms_skill_code', true ) : '',
				);
			} elseif ( 'bank' === $type ) {
				$bank          = AssessmentSchema::ready() ? Banks::get( $id ) : null;
				$labels[ $id ] = array(
					'title'  => $bank ? $bank['name'] : '',
					'status' => $bank ? $bank['visibility'] : 'missing',
				);
			}
			if ( ( $labels[ $id ]['title'] ?? '' ) === '' ) {
				$labels[ $id ]['title'] = ''; }
		}
		return $labels;
	}

	/** Links of one item grouped by type, in the order they were added.
	 *
	 * @param mixed $item_id Item identifier.
	 */
	public static function for_item( $item_id ) {
		global $wpdb;
		$grouped = array_fill_keys( self::TYPES, array() );
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT object_type, object_id FROM ' . self::table() . ' WHERE item_id=%d ORDER BY id', (int) $item_id ), ARRAY_A ) as $row ) {
			if ( isset( $grouped[ $row['object_type'] ] ) ) {
				$grouped[ $row['object_type'] ][] = (int) $row['object_id']; }
		}
		foreach ( $grouped as $type => $ids ) {
			$labels           = self::labels( $type, $ids );
			$grouped[ $type ] = array_map(
				static function ( $id ) use ( $labels ) {
					return array(
						'id'        => $id,
						'available' => ( $labels[ $id ]['status'] ?? 'missing' ) !== 'missing',
					) + $labels[ $id ];
				},
				$ids
			);
		}
		return $grouped;
	}

	/** Find content to link: matching titles, or the given IDs. Administrators see all statuses.
	 *
	 * @param mixed  $type Type.
	 * @param string $search Search.
	 * @param array  $include Include.
	 */
	public static function targets( $type, $search = '', array $include = array() ) {
		global $wpdb;
		$include = array_values( array_filter( array_map( 'intval', $include ) ) );
		$rows    = array();
		if ( 'course' === $type || 'quiz' === $type ) {
			$args = array(
				'post_type'        => self::post_type( $type ),
				'post_status'      => self::POST_STATUSES,
				'numberposts'      => 20,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			);
			if ( $include ) {
				$args['post__in']    = $include;
				$args['numberposts'] = count( $include );
			} else {
				$args['s'] = sanitize_text_field( (string) $search ); }
			foreach ( get_posts( $args ) as $post ) {
				$rows[] = array(
					'id'     => (int) $post->ID,
					'title'  => get_the_title( $post ) ?: '#' . $post->ID,
					'status' => $post->post_status,
				); }
		} elseif ( 'skill' === $type ) {
			$args = array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => false,
				'number'     => $include ? count( $include ) : 20,
				'orderby'    => 'name',
			);
			if ( $include ) {
				$args['include'] = $include;
			} else {
				$args['search'] = sanitize_text_field( (string) $search ); }
			foreach ( get_terms( $args ) as $term ) {
				$rows[] = array(
					'id'     => (int) $term->term_id,
					'title'  => Taxonomy::plain( $term->name ),
					'status' => 'active',
					'code'   => (string) get_term_meta( $term->term_id, '_ohmylms_skill_code', true ),
				); }
		} elseif ( 'bank' === $type && AssessmentSchema::ready() ) {
			$table = AssessmentSchema::table( 'qb_banks' );
			if ( $include ) {
				$placeholders = implode( ',', array_fill( 0, count( $include ), '%d' ) );
				$found        = $wpdb->get_results( $wpdb->prepare( "SELECT id, name, visibility FROM $table WHERE id IN ($placeholders) ORDER BY name", $include ), ARRAY_A );
			} else {
				$found = $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, visibility FROM %i WHERE name LIKE %s ORDER BY name LIMIT 20', $table, '%' . $wpdb->esc_like( sanitize_text_field( (string) $search ) ) . '%' ), ARRAY_A );
			}
			foreach ( $found as $bank ) {
				$rows[] = array(
					'id'     => (int) $bank['id'],
					'title'  => $bank['name'],
					'status' => $bank['visibility'],
				); }
		}
		return $rows;
	}

	/** Curriculum items an object belongs to, each with its full path (for display in other modules).
	 *
	 * @param mixed $type Type.
	 * @param mixed $object_id Object identifier.
	 */
	public static function memberships( $type, $object_id ) {
		global $wpdb;
		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT item_id FROM ' . self::table() . ' WHERE object_type=%s AND object_id=%d ORDER BY item_id', $type, (int) $object_id ) ) );
		// A skill placed in a syllabus's skill group belongs to the item that group sits under.
		if ( 'skill' === $type ) {
			$ids = array_values( array_unique( array_merge( $ids, Syllabus::item_ids_for_skill( $object_id ) ) ) );
			sort( $ids ); }
		$result = array();
		foreach ( $ids as $id ) {
			$item = Items::get( $id );
			if ( $item ) {
				$result[] = Items::describe( $item ) + array( 'path' => Items::path_names( $id, false ) ); }
		}
		return $result;
	}

	/** Distinct object IDs of one type linked to any of the given items.
	 *
	 * @param array $item_ids Item ids.
	 * @param mixed $type Type.
	 */
	public static function object_ids( array $item_ids, $type ) {
		global $wpdb;
		$item_ids = array_values( array_filter( array_map( 'intval', $item_ids ) ) );
		if ( ! $item_ids || ! self::valid_type( $type ) ) {
			return array(); }
		$placeholders = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );
		$ids          = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT object_id FROM ' . self::table() . " WHERE object_type=%s AND item_id IN ($placeholders) ORDER BY object_id", array_merge( array( $type ), $item_ids ) ) ) );
		// Skills placed in the skill groups of a syllabus count as that syllabus's skills too.
		if ( 'skill' === $type ) {
			$ids = array_values( array_unique( array_merge( $ids, Syllabus::skill_ids( $item_ids ) ) ) );
			sort( $ids ); }
		return $ids;
	}

	/** Drop every link to content that no longer exists.
	 *
	 * @param mixed $type Type.
	 * @param mixed $object_id Object identifier.
	 */
	public static function remove_object( $type, $object_id ) {
		global $wpdb;
		if ( ! self::valid_type( $type ) || ! Schema::ready() ) {
			return; }
		$wpdb->delete(
			self::table(),
			array(
				'object_type' => $type,
				'object_id'   => (int) $object_id,
			)
		);
		if ( 'course' === $type ) {
			Placement::changed(); }
	}
}
