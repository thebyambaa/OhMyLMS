<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Quizzes a skill owns: connected to it as a whole, or drawing its questions from it.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\Pools;
use OhMyLMS\Assessment\SkillAssessment;
use OhMyLMS\Curriculum\Access;
use OhMyLMS\Quiz\QuizSkills;

defined( 'ABSPATH' ) || exit;

/**
 * A quiz belongs to a skill when its `quiz_skills` connection names the skill, or when one of its
 * question pools draws from it (a mixed-skill quiz generated from skills). Attaching and detaching
 * change only this skill's connection and keep the quiz's other skills in order. Generating builds a
 * draft through SkillAssessment, the existing mixed-skill quiz builder, and connects it.
 */
final class SkillQuizzes {

	/** Most quizzes listed for one skill. */
	const LIMIT = 50;

	/**
	 * IDs of quizzes connected to a skill or drawing from it.
	 *
	 * @param int $term_id Skill term ID.
	 * @return int[]
	 */
	public static function quiz_ids( $term_id ) {
		return array_values( array_unique( array_merge( self::connected( $term_id ), self::pooled( $term_id ) ) ) );
	}

	/**
	 * Quizzes whose quiz_skills connection names the skill.
	 *
	 * @param int $term_id Skill term ID.
	 * @return int[]
	 */
	private static function connected( $term_id ) {
		global $wpdb;
		if ( ! \OhMyLMS\Assessment\Schema::ready() ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh connection read.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT s.quiz_id FROM %i s JOIN %i p ON p.ID=s.quiz_id WHERE s.term_id=%d AND p.post_status<>'trash' ORDER BY s.quiz_id LIMIT %d", \OhMyLMS\Assessment\Schema::table( 'quiz_skills' ), $wpdb->posts, (int) $term_id, self::LIMIT ) ) );
	}

	/**
	 * Quizzes with a question pool that draws from the skill.
	 *
	 * @param int $term_id Skill term ID.
	 * @return int[]
	 */
	private static function pooled( $term_id ) {
		global $wpdb;
		// Pools are serialized arrays in post meta; a rule stores its skill as "term_id";i:<id>;.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Exact meta key with a bounded pattern.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT m.post_id FROM %i m JOIN %i p ON p.ID=m.post_id WHERE m.meta_key=%s AND m.meta_value LIKE %s AND p.post_type=%s AND p.post_status<>'trash' ORDER BY m.post_id LIMIT %d", $wpdb->postmeta, $wpdb->posts, Pools::META, '%' . $wpdb->esc_like( '"term_id";i:' . (int) $term_id . ';' ) . '%', OHMYLMS_QUIZ_CPT, self::LIMIT ) ) );
	}

	/**
	 * The quizzes a skill owns and how many approved question families it can supply.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array|\WP_Error skill, available, quizzes.
	 */
	public static function listing( $term_id ) {
		$term = self::skill( $term_id );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		$connected = self::connected( $term_id );
		$quizzes   = array();
		foreach ( self::quiz_ids( $term_id ) as $quiz_id ) {
			$drawn = 0;
			foreach ( Pools::rules( $quiz_id ) as $rule ) {
				if ( (int) ( $rule['term_id'] ?? 0 ) === (int) $term_id ) {
					$drawn += (int) ( $rule['count'] ?? 0 );
				}
			}
			$quizzes[] = array(
				'id'        => $quiz_id,
				'title'     => get_the_title( $quiz_id ),
				'status'    => (string) get_post_status( $quiz_id ),
				'style'     => (string) AssessmentSettings::get( $quiz_id )['style'],
				'connected' => in_array( $quiz_id, $connected, true ),
				'drawn'     => $drawn,
				'skills'    => count( QuizSkills::get( $quiz_id ) ),
				'editable'  => current_user_can( 'edit_post', $quiz_id ),
			);
		}
		return array(
			'skill'     => array(
				'id'   => (int) $term->term_id,
				'name' => Taxonomy::plain( $term->name ),
			),
			'available' => Pools::available( (int) $term_id ),
			'quizzes'   => $quizzes,
		);
	}

	/**
	 * Connect a quiz to the skill, or disconnect it, keeping the quiz's other skills in order.
	 *
	 * @param int  $term_id Skill term ID.
	 * @param int  $quiz_id Quiz ID.
	 * @param bool $linked  Connect (true) or disconnect.
	 * @return array|\WP_Error The skill's quizzes afterwards.
	 */
	public static function link( $term_id, $quiz_id, $linked ) {
		global $wpdb;
		$term = self::skill( $term_id );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		$quiz = self::quiz( $quiz_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}
		if ( $linked && Provenance::archived( $term_id ) ) {
			return Access::error( 'ohmylms_skill_archived', __( 'Archived skills cannot own more quizzes. Restore the skill first.', 'ohmylms' ), 409 );
		}
		$lock = 'ohmylms-quiz-skills-' . md5( $wpdb->prefix ) . '-' . (int) $quiz_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Non-cacheable advisory lock.
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			return Access::error( 'ohmylms_quiz_skills_busy', __( 'Another change to this quiz’s skills is being saved. Try again.', 'ohmylms' ), 409 );
		}
		try {
			$ids = QuizSkills::get( $quiz_id );
			if ( $linked && ! in_array( (int) $term_id, $ids, true ) ) {
				$ids[] = (int) $term_id;
			} elseif ( ! $linked ) {
				$ids = array_values( array_diff( $ids, array( (int) $term_id ) ) );
			}
			$saved = QuizSkills::set( $quiz_id, $ids );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Release the advisory lock.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
		return self::listing( $term_id );
	}

	/**
	 * Generate a draft quiz that draws questions from the skill, and connect it.
	 *
	 * @param int   $term_id Skill term ID.
	 * @param array $args    title, per_skill (1-10), time_limit (minutes).
	 * @return array|\WP_Error created (the new quiz) and the skill's quizzes.
	 */
	public static function generate( $term_id, array $args ) {
		$term = self::skill( $term_id );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		if ( Provenance::archived( $term_id ) || 'approved' !== Provenance::read( $term_id )['review'] ) {
			return Access::error( 'ohmylms_skill_not_ready', __( 'Only approved, active skills can generate quizzes.', 'ohmylms' ), 409 );
		}
		$per_skill = (int) ( $args['per_skill'] ?? 5 );
		$available = Pools::available( (int) $term_id );
		if ( $available < $per_skill ) {
			return Access::error(
				'ohmylms_skill_quiz_short',
				/* translators: 1: questions asked for, 2: questions available. */
				sprintf( __( 'This skill has %2$d approved question families; a quiz drawing %1$d needs more. Approve more questions or draw fewer.', 'ohmylms' ), $per_skill, $available ),
				409,
				array( 'available' => $available )
			);
		}
		$created = SkillAssessment::create(
			array(
				'title'      => (string) ( $args['title'] ?? '' ),
				'style'      => 'quiz',
				'skills'     => array( (int) $term_id ),
				'per_skill'  => $per_skill,
				'time_limit' => (int) ( $args['time_limit'] ?? 0 ),
				'publish'    => false,
			)
		);
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		QuizSkills::set( (int) $created['id'], array( (int) $term_id ) );
		return array( 'created' => $created ) + self::listing( $term_id );
	}

	/**
	 * The skill, or a 404.
	 *
	 * @param int $term_id Skill term ID.
	 * @return \WP_Term|\WP_Error
	 */
	private static function skill( $term_id ) {
		$term = get_term( (int) $term_id, Taxonomy::NAME );
		return $term && ! is_wp_error( $term ) ? $term : Access::error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), 404 );
	}

	/**
	 * The quiz, when it exists and the user may edit it.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return \WP_Post|\WP_Error
	 */
	private static function quiz( $quiz_id ) {
		$post = get_post( (int) $quiz_id );
		if ( ! $post || OHMYLMS_QUIZ_CPT !== $post->post_type || 'trash' === $post->post_status ) {
			return Access::error( 'ohmylms_quiz_missing', __( 'Quiz not found.', 'ohmylms' ), 404 );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return Access::error( 'ohmylms_quiz_forbidden', __( 'You cannot change this quiz.', 'ohmylms' ), 403 );
		}
		return $post;
	}
}
