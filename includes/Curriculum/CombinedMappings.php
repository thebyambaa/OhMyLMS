<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * One read of everything a skill is mapped to or used by.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\Skills\Provenance;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * A skill's mappings across syllabuses (both directions, with their provenance and combined groups),
 * and the courses, lessons, quizzes, assignments and questions that use it. Every list is bounded;
 * totals are exact. Nothing here writes.
 */
final class CombinedMappings {

	/** Most rows of each usage list. */
	const LIMIT = 20;

	/**
	 * Describe a skill's mappings and usage.
	 *
	 * @param int $skill_id Skill term ID.
	 * @return array|\WP_Error
	 */
	public static function describe( $skill_id ) {
		$skill_id = (int) $skill_id;
		$term     = get_term( $skill_id, Taxonomy::NAME );
		if ( ! $term || is_wp_error( $term ) ) {
			return Access::error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), 404 );
		}
		$rows     = SkillMappings::all( array( $skill_id ) );
		$mappings = array();
		$groups   = array();
		foreach ( $rows as $row ) {
			$mappings[] = self::mapping( $row );
			if ( ! empty( $row['group_uuid'] ) ) {
				$groups[ $row['group_uuid'] ] = true;
			}
		}
		return array(
			'skill'      => array(
				'id'         => $skill_id,
				'name'       => Taxonomy::plain( $term->name ),
				'code'       => (string) get_term_meta( $skill_id, Syllabus::CODE_META, true ),
				'shared'     => metadata_exists( 'term', $skill_id, Comparison::SUBJECT_META ),
				'subject'    => (string) get_term_meta( $skill_id, Comparison::SUBJECT_META, true ),
				'provenance' => Provenance::read( $skill_id ),
			),
			'placements' => self::placements( $skill_id ),
			'mappings'   => $mappings,
			'groups'     => array_values( array_filter( array_map( array( __CLASS__, 'group' ), array_keys( $groups ) ) ) ),
			'courses'    => self::courses( $skill_id ),
			'content'    => self::content( $skill_id ),
			'questions'  => self::questions( $skill_id ),
		);
	}

	/**
	 * One mapping row with both sides, where its objective sits and how it was made.
	 *
	 * @param array $row skill_mappings row.
	 * @return array
	 */
	private static function mapping( array $row ) {
		$specific = (int) $row['specific_term_id'];
		return array(
			'id'          => (int) $row['id'],
			'specific'    => self::summary( $specific ),
			'shared'      => self::summary( (int) $row['shared_term_id'] ),
			'relation'    => (string) $row['relation'],
			'note'        => (string) $row['note'],
			'source'      => (string) ( $row['source'] ?? 'manual' ),
			'confidence'  => isset( $row['confidence'] ) && null !== $row['confidence'] ? (float) $row['confidence'] : null,
			'group_uuid'  => (string) ( $row['group_uuid'] ?? '' ),
			'revision'    => SkillMappings::revision( $row ),
			'syllabuses'  => self::syllabuses_of( $specific ),
			'created_by'  => (int) ( $row['created_by'] ?? 0 ),
			'updated_at'  => (string) $row['updated_at'],
			'specific_id' => $specific,
			'shared_id'   => (int) $row['shared_term_id'],
		);
	}

	/**
	 * Identity of a skill.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array{id:int,name:string,code:string}
	 */
	private static function summary( $term_id ) {
		$term = get_term( (int) $term_id, Taxonomy::NAME );
		return array(
			'id'   => (int) $term_id,
			'name' => $term && ! is_wp_error( $term ) ? Taxonomy::plain( $term->name ) : '',
			'code' => (string) get_term_meta( (int) $term_id, Syllabus::CODE_META, true ),
		);
	}

	/**
	 * Syllabuses whose groups place a skill.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array[] id, name.
	 */
	private static function syllabuses_of( $term_id ) {
		$found = array();
		foreach ( Syllabus::item_ids_for_skill( $term_id ) as $item_id ) {
			$owner = Syllabus::owner( $item_id );
			$item  = $owner ? Items::get( $owner ) : null;
			if ( $item ) {
				$found[ $owner ] = array(
					'id'   => $owner,
					'name' => (string) $item['name'],
				);
			}
		}
		return array_values( $found );
	}

	/**
	 * Where the skill itself is placed: syllabus, content item and group.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array[]
	 */
	private static function placements( $term_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded placement read for one skill.
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT g.id, g.name, g.item_id FROM %i p JOIN %i g ON g.id=p.group_id WHERE p.term_id=%d ORDER BY g.item_id, g.position LIMIT %d', Syllabus::placements_table(), Syllabus::groups_table(), (int) $term_id, self::LIMIT ), ARRAY_A );
		$result = array();
		foreach ( $rows as $row ) {
			$owner    = Syllabus::owner( (int) $row['item_id'] );
			$item     = Items::get( (int) $row['item_id'] );
			$syllabus = $owner ? Items::get( $owner ) : null;
			$result[] = array(
				'syllabus_id' => $owner,
				'syllabus'    => $syllabus ? (string) $syllabus['name'] : '',
				'item_id'     => (int) $row['item_id'],
				'item'        => $item ? (string) $item['name'] : '',
				'group_id'    => (int) $row['id'],
				'group'       => Syllabus::text( $row['name'] ),
			);
		}
		return $result;
	}

	/**
	 * A combined coverage group: its source syllabus, shared skill and members.
	 *
	 * @param string $uuid Group UUID.
	 * @return array|null
	 */
	private static function group( $uuid ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One group row.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE uuid=%s', Schema::table( 'skill_mapping_groups' ), $uuid ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$source  = Items::get( (int) $row['source_id'] );
		$members = json_decode( (string) $row['member_ids'], true );
		return array(
			'uuid'      => (string) $uuid,
			'source_id' => (int) $row['source_id'],
			'source'    => $source ? (string) $source['name'] : '',
			'shared'    => self::summary( (int) $row['shared_term_id'] ),
			'members'   => array_map( array( __CLASS__, 'summary' ), array_map( 'intval', is_array( $members ) ? $members : array() ) ),
		);
	}

	/**
	 * Courses whose published program or draft outline uses the skill, and courses linked to it.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array{items:array[],total:int}
	 */
	private static function courses( $term_id ) {
		global $wpdb;
		$usage = \OhMyLMS\Skills\SkillUsage::of( $term_id );
		$ids   = array();
		foreach ( $usage['drafts'] as $course_id ) {
			$ids[ (int) $course_id ] = 'draft';
		}
		if ( \OhMyLMS\Learning\Schema::ready() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Published program versions that use the skill.
			foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT p.course_id FROM %i p JOIN %i o ON o.program_id=p.id WHERE o.term_id=%d', \OhMyLMS\Learning\Schema::table( 'programs' ), \OhMyLMS\Learning\Schema::table( 'outcomes' ), (int) $term_id ) ) as $course_id ) {
				$ids[ (int) $course_id ] = 'published';
			}
		}
		foreach ( self::linked_posts( $term_id, OHMYLMS_COURSE_CPT ) as $course_id ) {
			$ids += array( $course_id => 'linked' );
		}
		$items = array();
		foreach ( array_slice( $ids, 0, self::LIMIT, true ) as $course_id => $how ) {
			$items[] = array(
				'id'     => (int) $course_id,
				'title'  => get_the_title( (int) $course_id ),
				'status' => (string) get_post_status( (int) $course_id ),
				'usage'  => $how,
			);
		}
		return array(
			'items' => $items,
			'total' => count( $ids ),
		);
	}

	/**
	 * Lessons, quizzes and assignments linked to the skill, and quizzes that own it.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array<string,array{items:array[],total:int}>
	 */
	private static function content( $term_id ) {
		$result = array();
		foreach ( array(
			'lesson'     => OHMYLMS_LESSON_CPT,
			'quiz'       => OHMYLMS_QUIZ_CPT,
			'assignment' => OHMYLMS_ASSIGNMENT_CPT,
		) as $kind => $post_type ) {
			$ids = self::linked_posts( $term_id, $post_type );
			if ( 'quiz' === $kind ) {
				$ids = array_values( array_unique( array_merge( $ids, \OhMyLMS\Skills\SkillQuizzes::quiz_ids( $term_id ) ) ) );
			}
			$result[ $kind ] = array(
				'items' => array_map(
					static function ( $post_id ) {
						return array(
							'id'     => (int) $post_id,
							'title'  => get_the_title( (int) $post_id ),
							'status' => (string) get_post_status( (int) $post_id ),
						);
					},
					array_slice( $ids, 0, self::LIMIT )
				),
				'total' => count( $ids ),
			);
		}
		return $result;
	}

	/**
	 * Posts of a type with a direct term relationship to the skill.
	 *
	 * @param int    $term_id   Skill term ID.
	 * @param string $post_type Post type.
	 * @return int[]
	 */
	private static function linked_posts( $term_id, $post_type ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct relationships only, without loading posts.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT p.ID FROM %i tr JOIN %i tt ON tt.term_taxonomy_id=tr.term_taxonomy_id JOIN %i p ON p.ID=tr.object_id WHERE tt.term_id=%d AND tt.taxonomy=%s AND p.post_type=%s AND p.post_status<>'trash' ORDER BY p.ID LIMIT 500", $wpdb->term_relationships, $wpdb->term_taxonomy, $wpdb->posts, (int) $term_id, Taxonomy::NAME, $post_type ) ) );
	}

	/**
	 * Questions whose current skill map uses the skill.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array{items:array[],total:int}
	 */
	private static function questions( $term_id ) {
		global $wpdb;
		if ( ! \OhMyLMS\Assessment\Schema::ready() ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}
		$table = \OhMyLMS\Assessment\Schema::table( 'question_skills' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact count for one skill.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT s.question_id) FROM %i s JOIN %i p ON p.ID=s.question_id WHERE s.term_id=%d AND p.post_status<>'trash'", $table, $wpdb->posts, (int) $term_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded page for one skill.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT s.question_id, MIN(s.role) AS role FROM %i s JOIN %i p ON p.ID=s.question_id WHERE s.term_id=%d AND p.post_status<>'trash' GROUP BY s.question_id ORDER BY s.question_id LIMIT %d", $table, $wpdb->posts, (int) $term_id, self::LIMIT ), ARRAY_A );
		return array(
			'items' => array_map(
				static function ( $row ) {
					return array(
						'id'    => (int) $row['question_id'],
						'title' => get_the_title( (int) $row['question_id'] ),
						'role'  => 'primary' === $row['role'] ? 'primary' : 'supporting',
					);
				},
				$rows
			),
			'total' => $total,
		);
	}
}
