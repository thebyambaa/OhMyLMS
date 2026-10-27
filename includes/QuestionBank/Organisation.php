<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Question-bank views over existing curriculum, roadmap, course and skill references.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\QuestionBank;

use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Schema as CurriculumSchema;
use OhMyLMS\Curriculum\CourseObjectives;
use OhMyLMS\Assessment\Schema;

defined( 'ABSPATH' ) || exit;

/** Organisation is many-to-many discovery, never a question copy or access grant. */
final class Organisation {
	/** Register discoverable author-only organisation views. */
	public static function init() {
		add_action(
			'rest_api_init',
			static function () {
				register_rest_route(
					'ohmylms/v1',
					'/question-bank-organisation',
					array(
						'methods'             => 'GET',
						'permission_callback' => array( AccessPolicy::class, 'can_author' ),
						'callback'            => array( __CLASS__, 'views' ),
					)
				);
			}
		);
	}

	/**
	 * Hierarchical scopes. Extensions may add views with stable IDs and parent references.
	 *
	 * @return array View definitions without question content.
	 */
	public static function views() {
		$curriculum = array();
		$roadmaps   = array();
		if ( CurriculumSchema::ready() ) {
			foreach ( Items::all() as $item ) {
				$row          = array(
					'id'     => (int) $item['id'],
					'parent' => (int) $item['parent_id'],
					'name'   => $item['name'],
				);
				$curriculum[] = $row;
				if ( 'roadmap' === $item['item_type'] ) {
					$roadmaps[] = $row + array( 'kind' => 'roadmap' );
				}
			}
		}
		$args = array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => array( 'publish', 'draft', 'future' ),
			'numberposts' => 100,
			'orderby'     => 'title',
			'order'       => 'ASC',
		);
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		$courses = array_map(
			static function ( $post ) {
				return array(
					'id'     => (int) $post->ID,
					'parent' => 0,
					'name'   => get_the_title( $post ),
				);
			},
			get_posts( $args )
		);
		$views   = array(
			array(
				'id'     => 'curriculum',
				'name'   => __( 'Curriculum', 'ohmylms' ),
				'scopes' => $curriculum,
			),
			array(
				'id'     => 'roadmap',
				'name'   => __( 'Roadmap', 'ohmylms' ),
				'scopes' => $roadmaps,
			),
			array(
				'id'     => 'course',
				'name'   => __( 'Course', 'ohmylms' ),
				'scopes' => $courses,
			),
		);
		return array( 'views' => array_values( (array) apply_filters( 'ohmylms_question_bank_organisation_views', $views ) ) );
	}

	/**
	 * Resolve a subtree to existing skill/bank/course references; extensions resolve their IDs.
	 *
	 * @param string $view Organisation view ID.
	 * @param int    $scope Selected node ID.
	 * @return array|\WP_Error Existing references.
	 */
	public static function resolve( $view, $scope ) {
		global $wpdb;
		$references = array(
			'skills'  => array(),
			'banks'   => array(),
			'courses' => array(),
			'quizzes' => array(),
		);
		if ( 'course' === $view ) {
			if ( OHMYLMS_COURSE_CPT !== get_post_type( $scope ) || ! current_user_can( 'edit_post', $scope ) ) {
				return new \WP_Error( 'bank_scope', __( 'Course scope unavailable.', 'ohmylms' ), array( 'status' => 403 ) );
			}
			$references['courses'][] = $scope;
			$references['skills']    = array_column( CourseObjectives::references( $scope ), 'term_id' );
		} elseif ( in_array( $view, array( 'curriculum', 'roadmap' ), true ) ) {
			$item = CurriculumSchema::ready() ? Items::get( $scope ) : null;
			if ( ! $item || ( 'roadmap' === $view && 'roadmap' !== $item['item_type'] ) ) {
				return new \WP_Error( 'bank_scope', __( 'Curriculum scope unavailable.', 'ohmylms' ), array( 'status' => 404 ) );
			}
			$ids = array_map( 'intval', Items::with_descendants( $scope ) );
			if ( ! in_array( $scope, $ids, true ) ) {
				$ids[] = $scope;
			}
			$placeholders         = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$references['skills'] = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT s.term_id FROM %i s INNER JOIN %i g ON g.id=s.group_id WHERE g.item_id IN ($placeholders)", array_merge( array( CurriculumSchema::table( 'syllabus_group_skills' ), CurriculumSchema::table( 'syllabus_groups' ) ), $ids ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Bound subtree IDs and identifier placeholders; organisation is read live.
			$links                = $wpdb->get_results( $wpdb->prepare( "SELECT object_type,object_id FROM %i WHERE item_id IN ($placeholders)", array_merge( array( CurriculumSchema::table( 'curriculum_links' ) ), $ids ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Bound subtree references.
			foreach ( $links as $link ) {
				$key = array(
					'skill'  => 'skills',
					'bank'   => 'banks',
					'course' => 'courses',
					'quiz'   => 'quizzes',
				)[ $link['object_type'] ] ?? '';
				if ( $key ) {
					$references[ $key ][] = (int) $link['object_id'];
				}
			}
		} else {
			$references = apply_filters( 'ohmylms_question_bank_resolve_scope', null, $view, $scope );
			if ( ! is_array( $references ) ) {
				return new \WP_Error( 'bank_scope', __( 'Question-bank view unavailable.', 'ohmylms' ), array( 'status' => 400 ) );
			}
		}
		foreach ( array( 'skills', 'banks', 'courses', 'quizzes' ) as $key ) {
			$references[ $key ] = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) ( $references[ $key ] ?? array() ) ) ) ) ), 0, 5000 );
		}
		if ( CurriculumSchema::ready() && $references['skills'] ) {
			$holders              = implode( ',', array_fill( 0, count( $references['skills'] ), '%d' ) );
			$mapped               = $wpdb->get_results( $wpdb->prepare( "SELECT specific_term_id,shared_term_id FROM %i WHERE specific_term_id IN ($holders) OR shared_term_id IN ($holders)", array_merge( array( CurriculumSchema::table( 'skill_mappings' ) ), $references['skills'], $references['skills'] ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Bounded shared/source skill references for discovery, not mastery or access.
			$references['skills'] = array_slice( array_values( array_unique( array_merge( $references['skills'], array_map( 'intval', array_column( $mapped, 'specific_term_id' ) ), array_map( 'intval', array_column( $mapped, 'shared_term_id' ) ) ) ) ), 0, 5000 );
		}
		return array_intersect_key( $references, array_flip( array( 'skills', 'banks', 'courses', 'quizzes' ) ) );
	}

	/**
	 * Append prepared conditions to the existing bank query; visibility remains authoritative.
	 *
	 * @param \WP_REST_Request $request Bank search.
	 * @return array|\WP_Error SQL fragment and placeholder values.
	 */
	public static function condition( $request ) {
		global $wpdb;
		if ( ( null !== $request['organisation'] && ! is_scalar( $request['organisation'] ) ) || ( null !== $request['scope'] && ! is_scalar( $request['scope'] ) ) ) {
			return new \WP_Error( 'bank_scope', __( 'Choose a single organisation view and location.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$view  = sanitize_key( (string) $request['organisation'] );
		$scope = absint( $request['scope'] );
		if ( ! $scope || ! $view ) {
			return array(
				'where' => '',
				'args'  => array(),
			);
		}
		$references = self::resolve( $view, $scope );
		if ( is_wp_error( $references ) ) {
			return $references;
		}
		$where = array();
		$args  = array();
		foreach ( $references as $kind => $ids ) {
			if ( ! $ids ) {
				continue;
			}
			$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			switch ( $kind ) {
				case 'skills':
					$where[] = "EXISTS (SELECT 1 FROM %i qs WHERE qs.question_id=p.ID AND qs.term_id IN ($holders))";
					$args[]  = Schema::table( 'question_skills' );
					break;
				case 'banks':
					$where[] = "q.bank_id IN ($holders)";
					break;
				case 'courses':
					$where[] = "EXISTS (SELECT 1 FROM %i qr INNER JOIN %i cr ON cr.content_id=qr.quiz_id INNER JOIN %i ch ON ch.chapter_id=cr.chapter_id WHERE qr.question_id=p.ID AND ch.course_id IN ($holders))";
					$args    = array_merge( $args, array( $wpdb->prefix . 'ohmylms_quiz_questions_relationship', $wpdb->prefix . 'ohmylms_content_relationship', $wpdb->prefix . 'ohmylms_chapter_relationship' ) );
					break;
				case 'quizzes':
					$where[] = "EXISTS (SELECT 1 FROM %i qr WHERE qr.question_id=p.ID AND qr.quiz_id IN ($holders))";
					$args[]  = $wpdb->prefix . 'ohmylms_quiz_questions_relationship';
					break;
			}
			$args = array_merge( $args, $ids );
		}
		return array(
			'where' => $where ? '(' . implode( ' OR ', $where ) . ')' : '1=0',
			'args'  => $args,
		);
	}
}
