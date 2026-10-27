<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Limited random question pools for quizzes and exams.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

use OhMyLMS\QuestionBank\Usage;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Limited random question pools for quizzes and exams.
 *
 * A rule (stored in quiz meta `_ohmylms_pools`) draws `count` approved questions for a
 * skill (and its sub-skills), optionally filtered by difficulty and bank. Pools are
 * validated together at publish time: fixed questions and their families are excluded,
 * each drawn question must come from a different family, and a shortage fails with an
 * actionable message instead of silently shortening the paper. Each attempt draws with
 * its own seed and freezes the drawn versions in its items.
 */
final class Pools {
	const META = '_ohmylms_pools';

	/**
	 * Rules for Pools.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 */
	public static function rules( $quiz_id ) {
		$rules = get_post_meta( (int) $quiz_id, self::META, true );
		return is_array( $rules ) ? array_values( $rules ) : array();
	}

	/** Normalize and validate rules; returns rules or WP_Error.
	 *
	 * @param array $rules Rules.
	 */
	public static function sanitize( array $rules ) {
		$clean = array();
		foreach ( array_values( $rules ) as $index => $rule ) {
			if ( ! is_array( $rule ) ) {
				return self::invalid( __( 'Each pool must be an object.', 'ohmylms' ) ); }
			$term = (int) ( $rule['term_id'] ?? 0 );
			if ( ! $term || ! term_exists( $term, Taxonomy::NAME ) ) {
				return self::invalid( __( 'Each pool needs an existing skill.', 'ohmylms' ) ); }
			$count = (int) ( $rule['count'] ?? 0 );
			if ( $count < 1 || $count > 50 ) {
				return self::invalid( __( 'A pool draws between 1 and 50 questions.', 'ohmylms' ) ); }
			$marks = $rule['marks'] ?? 1;
			if ( ! is_numeric( $marks ) || (float) $marks < 0 ) {
				return self::invalid( __( 'Pool marks must be zero or more.', 'ohmylms' ) ); }
			$difficulty = (string) ( $rule['difficulty'] ?? '' );
			if ( '' !== $difficulty && ! in_array( $difficulty, \OhMyLMS\QuestionBank\BankAttributes::DIFFICULTIES, true ) ) {
				return self::invalid( __( 'Unknown difficulty.', 'ohmylms' ) ); }
			$clean[] = array(
				'id'             => 'pool-' . ( $index + 1 ),
				'title'          => mb_substr( sanitize_text_field( (string) ( $rule['title'] ?? '' ) ), 0, 190 ),
				'term_id'        => $term,
				'count'          => $count,
				'marks'          => round( (float) $marks, 4 ),
				'difficulty'     => $difficulty,
				'bank_id'        => (int) ( $rule['bank_id'] ?? 0 ),
				'include_secure' => ! isset( $rule['include_secure'] ) || ! empty( $rule['include_secure'] ),
			);
		}
		return $clean;
	}

	/**
	 * Save for Pools.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param array $rules Rules.
	 */
	public static function save( $quiz_id, array $rules ) {
		$clean = self::sanitize( $rules );
		if ( is_wp_error( $clean ) ) {
			return $clean; }
		$feasible = self::check( $quiz_id, $clean );
		if ( is_wp_error( $feasible ) ) {
			return $feasible; }
		update_post_meta( (int) $quiz_id, self::META, $clean );
		return $clean;
	}

	/** Pool slots for a revision (after the fixed slots), or WP_Error on shortage.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param mixed $fixed_count Fixed count.
	 */
	public static function slots_for( $quiz_id, $fixed_count ) {
		$rules = self::rules( $quiz_id );
		if ( ! $rules ) {
			return array(); }
		$feasible = self::check( $quiz_id, $rules );
		if ( is_wp_error( $feasible ) ) {
			return $feasible; }
		$slots = array();
		foreach ( $rules as $rule ) {
			for ( $i = 0; $i < (int) $rule['count']; $i++ ) {
				$slots[] = array(
					'slot_no'         => 0,
					'section'         => $rule['title'],
					'page'            => 0,
					'question_id'     => 0,
					'version_id'      => 0,
					'marks'           => (float) $rule['marks'],
					'required'        => 0,
					'shuffle_options' => 1,
					'pool'            => $rule,
				);
			}
		}
		return $slots;
	}

	/** Joint feasibility: every pool can draw its count from distinct, unused families.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param array $rules Rules.
	 */
	public static function check( $quiz_id, array $rules ) {
		$fixed = array_map( 'intval', array_column( ohmylms_get_quiz( (int) $quiz_id )->get_questions(), 'id' ) );
		$taken = self::family_keys( $fixed );
		foreach ( $rules as $rule ) {
			$available = array();
			foreach ( self::candidates( $rule ) as $row ) {
				$family = self::family_key( $row );
				if ( in_array( (int) $row['question_id'], $fixed, true ) || isset( $taken[ $family ] ) || isset( $available[ $family ] ) ) {
					continue; }
				$available[ $family ] = true;
			}
			if ( count( $available ) < (int) $rule['count'] ) {
				$skill = get_term( (int) $rule['term_id'], Taxonomy::NAME );
				return new \WP_Error(
					'quiz_pool_shortage',
					sprintf(
					/* translators: 1: pool title or skill, 2: questions needed, 3: questions available */
						__( 'The random pool "%1$s" needs %2$d approved questions from different families, but only %3$d are available. Approve more questions for this skill, relax the filters, or lower the count.', 'ohmylms' ),
						$rule['title'] ?: ( $skill && ! is_wp_error( $skill ) ? $skill->name : '#' . $rule['term_id'] ),
						(int) $rule['count'],
						count( $available )
					),
					array(
						'status' => 409,
						'pool'   => $rule['id'] ?? '',
					)
				);
			}
			// Reserve families greedily so overlapping pools cannot double-count them.
			foreach ( array_slice( array_keys( $available ), 0, (int) $rule['count'] ) as $family ) {
				$taken[ $family ] = true; }
		}
		return true;
	}

	/** Draw one version for a pool slot, deterministic for the attempt seed.
	 *
	 * @param mixed $rule Rule.
	 * @param mixed $seed Seed.
	 * @param array $used Used.
	 * @param mixed $attempt_id Attempt identifier.
	 */
	public static function draw( $rule, $seed, array $used, $attempt_id ) {
		$filtered = (int) apply_filters( 'ohmylms_quiz_pool_draw', 0, $rule, $seed, $used, (int) $attempt_id );
		if ( $filtered ) {
			return $filtered; }
		$taken      = self::family_keys( $used );
		$candidates = array_values(
			array_filter(
				self::candidates( $rule ),
				static function ( $row ) use ( $used, $taken ) {
					return ! in_array( (int) $row['question_id'], $used, true ) && ! isset( $taken[ self::family_key( $row ) ] );
				}
			)
		);
		if ( ! $candidates ) {
			return 0; }
		usort(
			$candidates,
			static function ( $left, $right ) use ( $seed, $rule ) {
				return strcmp( hash( 'sha256', $seed . ':' . $rule['id'] . ':' . $left['question_id'] ), hash( 'sha256', $seed . ':' . $rule['id'] . ':' . $right['question_id'] ) );
			}
		);
		return (int) $candidates[0]['version_id'];
	}

	/**
	 * How many questions a skill can supply to a pool: distinct families among its approved questions.
	 *
	 * @param int $term_id Skill.
	 * @return int
	 */
	public static function available( $term_id ) {
		$families = array();
		foreach ( self::candidates(
			array(
				'term_id'        => (int) $term_id,
				'include_secure' => true,
			)
		) as $row ) {
			$families[ self::family_key( $row ) ] = true;
		}
		return count( $families );
	}

	/** Approved, non-archived versions matching a rule.
	 *
	 * @param array $rule Rule.
	 */
	public static function candidates( array $rule ) {
		global $wpdb;
		$terms = array_merge( array( (int) $rule['term_id'] ), array_map( 'intval', get_term_children( (int) $rule['term_id'], Taxonomy::NAME ) ?: array() ) );
		$where = array( 'q.approved_version_id>0', "p.post_status NOT IN ('trash', %s)", 's.term_id IN (' . implode( ',', array_fill( 0, count( $terms ), '%d' ) ) . ')' );
		$args  = array_merge( array( Usage::ARCHIVED ), $terms );
		if ( ! empty( $rule['difficulty'] ) ) {
			$where[] = 'q.difficulty=%s';
			$args[]  = $rule['difficulty']; }
		if ( ! empty( $rule['bank_id'] ) ) {
			$where[] = 'q.bank_id=%d';
			$args[]  = (int) $rule['bank_id']; }
		if ( empty( $rule['include_secure'] ) ) {
			$where[] = 'q.secure=0'; }
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DISTINCT q.question_id, q.approved_version_id AS version_id, q.family_id
             FROM ' . Schema::table( 'qb_questions' ) . ' q
             JOIN ' . Schema::table( 'qb_version_skills' ) . " s ON s.version_id=q.approved_version_id AND s.role='primary'
             JOIN {$wpdb->posts} p ON p.ID=q.question_id
             WHERE " . implode( ' AND ', $where ) . ' ORDER BY q.question_id',
				$args
			),
			ARRAY_A
		);
	}

	/**
	 * Family key for Pools.
	 *
	 * @param array $row Row.
	 */
	private static function family_key( array $row ) {
		return '' !== $row['family_id'] && null !== $row['family_id'] ? 'f:' . $row['family_id'] : 'q:' . (int) $row['question_id'];
	}

	/**
	 * Family keys for Pools.
	 *
	 * @param array $question_ids Question ids.
	 */
	private static function family_keys( array $question_ids ) {
		global $wpdb;
		$keys = array();
		foreach ( $question_ids as $question_id ) {
			$family = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT family_id FROM %i WHERE question_id=%d', Schema::table( 'qb_questions' ), (int) $question_id ) );
			$keys[ self::family_key(
				array(
					'family_id'   => $family,
					'question_id' => $question_id,
				)
			) ]     = true;
		}
		return $keys;
	}

	/**
	 * Invalid for Pools.
	 *
	 * @param mixed $message Message.
	 */
	private static function invalid( $message ) {
		return new \WP_Error( 'ohmylms_pool_invalid', $message, array( 'status' => 400 ) );
	}
}
