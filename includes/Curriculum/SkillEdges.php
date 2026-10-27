<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Links between shared skills: prerequisites and "encompasses".
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads and writes the plugin's own edge table; every value is prepared.

/**
 * Two kinds of link, both directed and both acyclic:
 *
 *  - prerequisite: skill A needs skill B first. The list stays where it always was (the skill's
 *    prerequisite list, read by recommendations); this class adds the provenance of each entry.
 *  - encompasses: using skill A implicitly practises skill B, to a weight between 0 and 1. A learner
 *    who works an advanced skill gets partial credit for review of the skills inside it, so they need
 *    fewer reviews.
 *
 * Only shared skills are linked here: the shared library is the backbone every syllabus maps onto.
 */
final class SkillEdges {

	const TYPES      = array( 'prerequisite', 'encompasses' );
	const MIN_WEIGHT = 0.05;

	/** Edge table name. */
	public static function table() {
		return Schema::table( 'skill_edges' );
	}

	/**
	 * Whether a skill is a shared skill.
	 *
	 * @param int $id Skill ID.
	 * @return bool
	 */
	public static function is_shared( $id ) {
		return (int) $id > 0 && metadata_exists( 'term', (int) $id, Comparison::SUBJECT_META );
	}

	/**
	 * Everything linked to a skill, with who suggested each link.
	 *
	 * @param int $id Skill ID.
	 * @return array prerequisites, encompasses, encompassed_by (each a list of linked skills).
	 */
	public static function for_skill( $id ) {
		global $wpdb;
		$id    = (int) $id;
		$edges = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE skill_id=%d OR other_id=%d', self::table(), $id, $id ), ARRAY_A ) as $row ) {
			$edges[ $row['type'] ][ (int) $row['skill_id'] . ':' . (int) $row['other_id'] ] = $row;
		}
		$prerequisites = array();
		foreach ( Taxonomy::prerequisites( $id ) as $other ) {
			$prerequisites[] = self::describe( $other, $edges['prerequisite'][ $id . ':' . $other ] ?? array() );
		}
		$encompasses    = array();
		$encompassed_by = array();
		foreach ( $edges['encompasses'] ?? array() as $row ) {
			if ( (int) $row['skill_id'] === $id ) {
				$encompasses[] = self::describe( (int) $row['other_id'], $row );
			} else {
				$encompassed_by[] = self::describe( (int) $row['skill_id'], $row );
			}
		}
		return array(
			'prerequisites'  => array_values( array_filter( $prerequisites ) ),
			'encompasses'    => array_values( array_filter( $encompasses ) ),
			'encompassed_by' => array_values( array_filter( $encompassed_by ) ),
		);
	}

	/**
	 * Add a prerequisite. It joins the skill's prerequisite list, so cycles and unknown skills are refused as before.
	 *
	 * @param int    $skill      The skill that needs another first.
	 * @param int    $other      The prerequisite.
	 * @param string $source     manual or ai.
	 * @param float  $confidence Confidence for AI suggestions.
	 * @param string $note       Reason.
	 * @return true|\WP_Error
	 */
	public static function add_prerequisite( $skill, $other, $source = 'manual', $confidence = null, $note = '' ) {
		$skill = (int) $skill;
		$other = (int) $other;
		if ( ! self::is_shared( $skill ) || ! self::is_shared( $other ) ) {
			return Access::error( 'ohmylms_edge_invalid', __( 'Both skills must be shared skills.', 'ohmylms' ), 404 );
		}
		$current = Taxonomy::prerequisites( $skill );
		if ( ! in_array( $other, $current, true ) ) {
			$saved = Taxonomy::set_prerequisites( $skill, array_merge( $current, array( $other ) ) );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		self::store( $skill, $other, 'prerequisite', 1.0, $source, $confidence, $note );
		return true;
	}

	/**
	 * Add an "encompasses" link: using $skill implicitly practises $other.
	 *
	 * @param int    $skill      The broader skill.
	 * @param int    $other      The skill inside it.
	 * @param float  $weight     Share of $other practised by working $skill, 0.05 to 1.
	 * @param string $source     manual or ai.
	 * @param float  $confidence Confidence for AI suggestions.
	 * @param string $note       Reason.
	 * @return true|\WP_Error
	 */
	public static function add_encompass( $skill, $other, $weight, $source = 'manual', $confidence = null, $note = '' ) {
		$skill = (int) $skill;
		$other = (int) $other;
		if ( $skill === $other ) {
			return Access::error( 'ohmylms_edge_invalid', __( 'A skill cannot encompass itself.', 'ohmylms' ) );
		}
		if ( ! self::is_shared( $skill ) || ! self::is_shared( $other ) ) {
			return Access::error( 'ohmylms_edge_invalid', __( 'Both skills must be shared skills.', 'ohmylms' ), 404 );
		}
		if ( self::reaches( $other, $skill ) ) {
			return Access::error( 'ohmylms_edge_cycle', __( 'That skill already encompasses this one, so the link would form a loop.', 'ohmylms' ), 409 );
		}
		self::store( $skill, $other, 'encompasses', max( self::MIN_WEIGHT, min( 1.0, (float) $weight ) ), $source, $confidence, $note );
		return true;
	}

	/**
	 * Remove a link.
	 *
	 * @param int    $skill Skill the link starts from.
	 * @param int    $other Linked skill.
	 * @param string $type  prerequisite or encompasses.
	 * @return true|\WP_Error
	 */
	public static function remove( $skill, $other, $type ) {
		global $wpdb;
		$skill = (int) $skill;
		$other = (int) $other;
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return Access::error( 'ohmylms_edge_invalid', __( 'Choose prerequisite or encompasses.', 'ohmylms' ) );
		}
		if ( 'prerequisite' === $type ) {
			$saved = Taxonomy::set_prerequisites( $skill, array_values( array_diff( Taxonomy::prerequisites( $skill ), array( $other ) ) ) );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		$wpdb->delete(
			self::table(),
			array(
				'skill_id' => $skill,
				'other_id' => $other,
				'type'     => $type,
			)
		);
		return true;
	}

	/**
	 * The encompassing links of several skills in one query, for the guidance engine.
	 *
	 * @param int[] $skill_ids Skills that are used (the broader side).
	 * @return array<int,array<int,float>> Skill => encompassed skill => weight.
	 */
	public static function encompasses_many( array $skill_ids ) {
		global $wpdb;
		$ids    = array_values( array_unique( array_filter( array_map( 'intval', $skill_ids ) ) ) );
		$result = array();
		if ( ! $ids ) {
			return $result;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT skill_id, other_id, weight FROM %i WHERE type='encompasses' AND skill_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( array( self::table() ), $ids ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One placeholder per ID.
		foreach ( $rows as $row ) {
			$result[ (int) $row['skill_id'] ][ (int) $row['other_id'] ] = (float) $row['weight'];
		}
		return $result;
	}

	/**
	 * Whether following encompassing links from one skill reaches another.
	 *
	 * @param int   $from   Start.
	 * @param int   $target Skill looked for.
	 * @param array $seen   Visited skills.
	 * @return bool
	 */
	public static function reaches( $from, $target, array $seen = array() ) {
		global $wpdb;
		if ( (int) $from === (int) $target ) {
			return true;
		}
		if ( isset( $seen[ $from ] ) || count( $seen ) > 500 ) {
			return false;
		}
		$seen[ $from ] = true;
		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT other_id FROM %i WHERE type='encompasses' AND skill_id=%d", self::table(), (int) $from ) ) as $next ) {
			if ( self::reaches( (int) $next, $target, $seen ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Insert or update an edge row.
	 *
	 * @param int        $skill      Skill.
	 * @param int        $other      Linked skill.
	 * @param string     $type       Edge type.
	 * @param float      $weight     Weight.
	 * @param string     $source     manual or ai.
	 * @param float|null $confidence Confidence.
	 * @param string     $note       Reason.
	 */
	private static function store( $skill, $other, $type, $weight, $source, $confidence, $note ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (skill_id, other_id, type, weight, source, confidence, note, created_by, created_at) VALUES (%d, %d, %s, %f, %s, %s, %s, %d, %s)
				ON DUPLICATE KEY UPDATE weight=VALUES(weight), source=VALUES(source), confidence=VALUES(confidence), note=VALUES(note)',
				self::table(),
				$skill,
				$other,
				$type,
				$weight,
				'ai' === $source ? 'ai' : 'manual',
				null === $confidence ? null : (string) round( (float) $confidence, 3 ),
				mb_substr( (string) $note, 0, 1000 ),
				get_current_user_id(),
				current_time( 'mysql', true )
			)
		);
	}

	/**
	 * A linked skill for display.
	 *
	 * @param int   $id  Skill ID.
	 * @param array $row Edge row, or empty for a link with no recorded provenance.
	 * @return array|null
	 */
	private static function describe( $id, array $row ) {
		$skill = Comparison::shared_skill( (int) $id );
		if ( ! $skill ) {
			return null;
		}
		return array(
			'id'         => (int) $id,
			'name'       => $skill['name'],
			'domain'     => $skill['domain'],
			'grade_from' => $skill['grade_from'],
			'grade_to'   => $skill['grade_to'],
			'weight'     => isset( $row['weight'] ) ? (float) $row['weight'] : 1.0,
			'source'     => $row['source'] ?? 'manual',
			'confidence' => isset( $row['confidence'] ) && null !== $row['confidence'] ? (float) $row['confidence'] : null,
			'note'       => (string) ( $row['note'] ?? '' ),
		);
	}
}
