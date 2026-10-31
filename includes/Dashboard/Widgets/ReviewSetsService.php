<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Draft review sets for groups of learners below Familiar on a skill.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Admin\AdminNavigation;
use OhMyLMS\Assessment\Pools;
use OhMyLMS\Assessment\SkillAssessment;
use OhMyLMS\Dashboard\MasteryRepository;
use OhMyLMS\Dashboard\Scope;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A review set is a **draft** quiz with one random pool drawing approved questions of the skill
 * (`Assessment\SkillAssessment::create()`, so the quiz REST permissions and the pool feasibility checks
 * apply as the current user). It is never published here. The quiz remembers the class, the skill and
 * the learners of the group in post meta, and one class and skill get at most one review set outside the
 * trash: a second request (a double click, another tab) returns the existing one.
 */
final class ReviewSetsService {

	/** Meta key holding `class:<id>|skill:<id>`, for the one-per-group guard. */
	const KEY_META = '_ohmylms_review_set_key';

	/** Meta key holding class_id, skill_id, learners, created_by and created_at. */
	const META = '_ohmylms_review_set';

	/** Fewest learners below Familiar that make a group. */
	const MIN_GROUP = 3;

	/** Most learners stored with a review set. */
	const MAX_LEARNERS = 1000;

	/** Seconds a second request waits for one creating the same review set. */
	const LOCK_WAIT = 5;

	/**
	 * Questions a review set draws (filter `ohmylms_dashboard_review_set_questions`, 1-10, default 5).
	 *
	 * @return int
	 */
	public static function size() {
		/**
		 * Filters how many questions a dashboard review set draws for its skill.
		 *
		 * @param int $questions Questions (1-10, default 5).
		 */
		return min( 10, max( 1, (int) apply_filters( 'ohmylms_dashboard_review_set_questions', 5 ) ) );
	}

	/**
	 * Fewest different approved questions the bank needs for a review set (3, or the size when smaller).
	 *
	 * @return int
	 */
	public static function min_questions() {
		return min( 3, self::size() );
	}

	/**
	 * Question figures of a skill: different approved questions in the bank, how many are needed, how many a
	 * set would draw and whether that is enough.
	 *
	 * @param int $skill_id Skill.
	 * @return array{available:int,needed:int,draw:int,enough:bool}
	 */
	public static function questions( $skill_id ) {
		$available = Pools::available( (int) $skill_id );
		return array(
			'available' => $available,
			'needed'    => self::min_questions(),
			'draw'      => min( self::size(), $available ),
			'enough'    => $available >= self::min_questions(),
		);
	}

	/**
	 * Guard key of a group.
	 *
	 * @param int $class_id Class.
	 * @param int $skill_id Skill.
	 * @return string
	 */
	public static function key( $class_id, $skill_id ) {
		return 'class:' . (int) $class_id . '|skill:' . (int) $skill_id;
	}

	/**
	 * Whether a user may create quizzes (the quiz post type's `edit_posts` capability).
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function can_author( $user_id ) {
		$type = get_post_type_object( OHMYLMS_QUIZ_CPT );
		return $type && user_can( (int) $user_id, $type->cap->edit_posts );
	}

	/**
	 * Review sets outside the trash for some groups.
	 *
	 * @param string[] $keys Guard keys.
	 * @return array<string,array{id:int,title:string,status:string}> Key => newest review set.
	 */
	public static function existing( array $keys ) {
		global $wpdb;
		$keys = array_values( array_unique( array_map( 'strval', $keys ) ) );
		if ( ! $keys ) {
			return array();
		}
		$args = array_merge( array( $wpdb->posts, $wpdb->postmeta, self::KEY_META, OHMYLMS_QUIZ_CPT ), $keys );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery -- Fresh read for the one-per-group guard; placeholders built for the key list.
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT m.meta_value AS rkey, p.ID, p.post_title, p.post_status FROM %i p INNER JOIN %i m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = %s AND p.post_status NOT IN ( 'trash', 'auto-draft' ) AND m.meta_value IN (" . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ') ORDER BY p.ID ASC', $args ), ARRAY_A );
		$found = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$found[ (string) $row['rkey'] ] = array(
				'id'     => (int) $row['ID'],
				'title'  => html_entity_decode( (string) $row['post_title'], ENT_QUOTES, 'UTF-8' ),
				'status' => (string) $row['post_status'],
			);
		}
		return $found;
	}

	/**
	 * Review set as returned to a user: the editor route only when they may edit the quiz and open the
	 * Content pages the quiz editor lives on.
	 *
	 * @param array $set     id, title, status.
	 * @param int   $user_id User.
	 * @return array{id:int,title:string,status:string,route:string|null}
	 */
	public static function describe( array $set, $user_id ) {
		// The quiz editor is on the Quizzes tab, which school staff do not see even when the Gradebook opens Content Hub.
		$editable = user_can( (int) $user_id, 'edit_post', (int) $set['id'] ) && user_can( (int) $user_id, AdminNavigation::CAPABILITY );
		return array(
			'id'     => (int) $set['id'],
			'title'  => (string) $set['title'],
			'status' => (string) $set['status'],
			'route'  => $editable ? '/quizzes/' . (int) $set['id'] : null,
		);
	}

	/**
	 * Create the draft review set of a class and skill, or return the one that exists.
	 *
	 * @param Scope $scope    Scope the user is looking at (already resolved for them).
	 * @param int   $class_id Class; must be a row of the scope.
	 * @param int   $skill_id Skill.
	 * @param int   $user_id  User (the current user: the quiz is created as them).
	 * @return array|WP_Error `{ created: bool, review_set: {id, title, status, route, questions?} }`.
	 */
	public static function assign( Scope $scope, $class_id, $skill_id, $user_id ) {
		global $wpdb;
		$class_id = (int) $class_id;
		$skill_id = (int) $skill_id;
		if ( ! self::can_author( $user_id ) ) {
			return new WP_Error( 'ohmylms_dashboard_review_set_forbidden', __( 'You cannot create quizzes, so you cannot assign a review set. Ask a site administrator.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$row = MasteryRepository::row( $scope, 'class:' . $class_id );
		if ( ! $row ) {
			return new WP_Error( 'ohmylms_dashboard_row_not_found', __( 'That class is not part of this dashboard view.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$skill = MasteryRepository::terms( array( $skill_id ) )[ $skill_id ] ?? null;
		if ( ! $skill ) {
			return new WP_Error( 'ohmylms_dashboard_skill_not_found', __( 'That skill does not exist.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$key  = self::key( $class_id, $skill_id );
		$lock = 'ohmylms-review-set-' . md5( $wpdb->prefix . $key );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Advisory lock against a double submit.
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock, self::LOCK_WAIT ) ) ) {
			return new WP_Error( 'ohmylms_dashboard_review_set_busy', __( 'This review set is being created. Try again in a moment.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		try {
			$existing = self::existing( array( $key ) )[ $key ] ?? null;
			if ( $existing ) {
				return array(
					'created'    => false,
					'review_set' => self::describe( $existing, $user_id ),
				);
			}
			$thresholds = MasteryLevels::thresholds();
			$group      = MasteryRepository::struggling_learners( $row['learners'], $skill_id, $thresholds['familiar'], self::MAX_LEARNERS );
			if ( $group['total'] < self::MIN_GROUP ) {
				return new WP_Error(
					'ohmylms_dashboard_review_set_no_group',
					/* translators: %d: fewest learners. */
					sprintf( __( 'Fewer than %d learners in this class are below Familiar on this skill now.', 'ohmylms' ), self::MIN_GROUP ),
					array( 'status' => 409 )
				);
			}
			$questions = self::questions( $skill_id );
			if ( ! $questions['enough'] ) {
				return new WP_Error(
					'ohmylms_dashboard_review_set_not_enough_questions',
					/* translators: 1: approved questions, 2: questions needed. */
					sprintf( __( 'Not enough questions: the bank has %1$d approved questions for this skill and a review set needs %2$d.', 'ohmylms' ), $questions['available'], $questions['needed'] ),
					array(
						'status'    => 409,
						'available' => $questions['available'],
						'needed'    => $questions['needed'],
					)
				);
			}
			$created = SkillAssessment::create(
				array(
					/* translators: 1: skill name, 2: class name. */
					'title'     => sprintf( __( '%1$s review set (%2$s)', 'ohmylms' ), $skill['name'], $row['label'] ),
					'style'     => 'quiz',
					'skills'    => array( $skill_id ),
					'per_skill' => $questions['draw'],
					'publish'   => false,
				)
			);
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$quiz_id = (int) $created['id'];
			if ( 'draft' !== get_post_status( $quiz_id ) ) {
				wp_update_post(
					array(
						'ID'          => $quiz_id,
						'post_status' => 'draft',
					)
				);
			}
			update_post_meta( $quiz_id, self::KEY_META, $key );
			update_post_meta(
				$quiz_id,
				self::META,
				array(
					'class_id'   => $class_id,
					'skill_id'   => $skill_id,
					'learners'   => array_column( $group['learners'], 'id' ),
					'created_by' => (int) $user_id,
					'created_at' => gmdate( 'Y-m-d H:i:s' ),
				)
			);
			/**
			 * Fires after the dashboard created a draft review set.
			 *
			 * @param int   $quiz_id  Draft quiz.
			 * @param int   $class_id Class.
			 * @param int   $skill_id Skill.
			 * @param int[] $learners Learners of the group.
			 */
			do_action( 'ohmylms_dashboard_review_set_created', $quiz_id, $class_id, $skill_id, array_column( $group['learners'], 'id' ) );
			return array(
				'created'    => true,
				'review_set' => self::describe(
					array(
						'id'     => $quiz_id,
						'title'  => (string) $created['title'],
						'status' => (string) get_post_status( $quiz_id ),
					),
					$user_id
				) + array( 'questions' => (int) $created['questions'] ),
			);
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Releases the advisory lock.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}
}
