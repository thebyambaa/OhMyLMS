<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Learner and class mastery laid over a subject's skill map.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use OhMyLMS\Curriculum\SkillMap;
use OhMyLMS\Dashboard\Widgets\MasteryLevels;
use OhMyLMS\Performance\Levels;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Read model of the skill map overlay (docs/api/curriculum-workspace.md, "Learner overlays").
 *
 * Levels are the dashboard heatmap's ({@see MasteryLevels}) over `student_skill_state`: a learner's level
 * on a skill is their rounded percentage; a class or school's level is the average percentage of its
 * learners (learners without evidence count as 0, as in the heatmap), with how many learners reach each
 * level. Who may be shown follows the dashboard scopes: site and curriculum administrators see every
 * learner; school administrators and teachers only the learners of their schools and classes, and they
 * never learn whether a learner outside them exists.
 */
final class SkillMapOverlay {

	/** Most groups and learners a target search returns. */
	const MAX_RESULTS = 20;

	/** Fewest characters before learners are searched. */
	const MIN_SEARCH = 2;

	/** REST pattern of a target. */
	const TARGET_PATTERN = '^(learner|class|school):[1-9][0-9]{0,18}$';

	/**
	 * Whether a user may open the read-only skill map and its overlay at all: curriculum administrators and
	 * users with dashboard access ({@see \OhMyLMS\Rest\V1\ComparisonController::skill_map_permission()}).
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function can_view( $user_id ) {
		return $user_id > 0 && ( self::sees_everyone( $user_id ) || Access::can_view( $user_id ) );
	}

	/**
	 * Whether a user may see every learner: site administrators, and curriculum administrators (the
	 * `ohmylms_can_manage_curriculum` filter, read for the current user only).
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function sees_everyone( $user_id ) {
		$user_id = (int) $user_id;
		if ( Access::is_site_admin( $user_id ) ) {
			return true;
		}
		return $user_id > 0 && get_current_user_id() === $user_id && \OhMyLMS\Curriculum\Access::can_manage();
	}

	/**
	 * Error for a user without overlay access.
	 *
	 * @param int $user_id User (0 when signed out).
	 * @return WP_Error 401 when signed out, 403 otherwise.
	 */
	public static function denied( $user_id ) {
		return new WP_Error(
			'ohmylms_skill_overlay_cannot_view',
			__( 'Sorry, you are not allowed to see learner mastery on the skill map.', 'ohmylms' ),
			array( 'status' => $user_id > 0 ? 403 : 401 )
		);
	}

	/**
	 * Learners a user may see.
	 *
	 * @param int $user_id User.
	 * @return int[]|null Sorted ids; null for every learner.
	 */
	public static function visible_learners( $user_id ) {
		if ( self::sees_everyone( $user_id ) ) {
			return null;
		}
		$ids = array();
		foreach ( ScopeResolver::available( $user_id ) as $scope ) {
			foreach ( (array) $scope->learner_ids() as $id ) {
				$ids[ (int) $id ] = true;
			}
		}
		$ids = array_keys( $ids );
		sort( $ids );
		return $ids;
	}

	/**
	 * Classes and schools of the user matching a search, and learners they may see matching it.
	 *
	 * @param int    $user_id User.
	 * @param string $search  Part of a name ('' lists the first groups and no learners).
	 * @return array groups (key, type, label, school), groups_total, learners (id, name), limit, min_search.
	 */
	public static function targets( $user_id, $search ) {
		$search = trim( sanitize_text_field( (string) $search ) );
		$groups = array();
		$total  = 0;
		foreach ( ScopeResolver::available( $user_id ) as $scope ) {
			if ( $scope->is_all() ) {
				continue;
			}
			$row = $scope->to_array();
			if ( '' !== $search && false === mb_stripos( $row['label'] . ' ' . $row['school_name'], $search ) ) {
				continue;
			}
			++$total;
			if ( count( $groups ) < self::MAX_RESULTS ) {
				$groups[] = array(
					'key'    => $scope->key(),
					'type'   => $scope->type(),
					'label'  => $scope->label(),
					'school' => Scope::CLASSROOM === $scope->type() ? $row['school_name'] : '',
				);
			}
		}
		return array(
			'groups'       => $groups,
			'groups_total' => $total,
			'learners'     => mb_strlen( $search ) >= self::MIN_SEARCH ? self::find_learners( $user_id, $search ) : array(),
			'limit'        => self::MAX_RESULTS,
			'min_search'   => self::MIN_SEARCH,
		);
	}

	/**
	 * Learners the user may see whose name or login contains a search, by name. Display names only.
	 *
	 * @param int    $user_id User.
	 * @param string $search  Search.
	 * @return array[] Each: id, name.
	 */
	private static function find_learners( $user_id, $search ) {
		$visible = self::visible_learners( $user_id );
		if ( array() === $visible ) {
			return array();
		}
		$columns = array( 'user_login', 'user_nicename', 'display_name' );
		$args    = array(
			'search'         => '*' . $search . '*',
			'search_columns' => null === $visible ? array_merge( $columns, array( 'user_email' ) ) : $columns,
			'number'         => self::MAX_RESULTS,
			'orderby'        => 'display_name',
			'order'          => 'ASC',
			'fields'         => array( 'ID', 'display_name' ),
			'count_total'    => false,
		);
		if ( null !== $visible ) {
			$args['include'] = $visible;
		}
		$found = array();
		foreach ( get_users( $args ) as $user ) {
			$found[] = array(
				'id'   => (int) $user->ID,
				'name' => (string) $user->display_name,
			);
		}
		return $found;
	}

	/**
	 * Mastery of a learner, class or school on the shared skills of a subject.
	 *
	 * @param int    $user_id User asking.
	 * @param string $subject Subject of the map.
	 * @param string $target  `learner:<id>`, `class:<id>` or `school:<id>`.
	 * @return array|WP_Error subject, target, skills, counts, total, levels, links, limits.
	 */
	public static function overlay( $user_id, $subject, $target ) {
		$target = (string) $target;
		if ( ! preg_match( '/' . self::TARGET_PATTERN . '/', $target ) ) {
			return new WP_Error( 'rest_invalid_param', __( 'Invalid overlay target.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		list( $type, $id ) = explode( ':', $target, 2 );
		$subject           = sanitize_text_field( (string) $subject );
		$skills            = SkillMap::skill_ids( $subject );
		$thresholds        = MasteryLevels::thresholds();
		$resolved          = 'learner' === $type ? self::learner( $user_id, (int) $id, $skills, $thresholds ) : self::group( $user_id, $target, $skills, $thresholds );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$counts = array_fill_keys( array_reverse( Levels::SCALE ), 0 );
		$known  = array();
		foreach ( $resolved['skills'] as $skill ) {
			$known[ $skill['id'] ] = $skill['level'];
		}
		foreach ( $skills as $skill_id ) {
			++$counts[ $known[ $skill_id ] ?? 'not-started' ];
		}
		return array(
			'subject' => $subject,
			'target'  => $resolved['target'],
			'skills'  => $resolved['skills'],
			'counts'  => $counts,
			'total'   => count( $skills ),
			'levels'  => MasteryLevels::legend( $thresholds ),
			'links'   => $resolved['links'],
			'limits'  => array( 'learners' => MasteryRepository::MAX_ROW_LEARNERS ),
		);
	}

	/**
	 * One learner's levels, when the user may see them.
	 *
	 * @param int   $user_id    User asking.
	 * @param int   $learner    Learner.
	 * @param int[] $skills     Skills of the map.
	 * @param array $thresholds Level thresholds.
	 * @return array|WP_Error target, skills, links.
	 */
	private static function learner( $user_id, $learner, array $skills, array $thresholds ) {
		$visible = self::visible_learners( $user_id );
		if ( null !== $visible && ! in_array( $learner, $visible, true ) ) {
			return new WP_Error( 'ohmylms_skill_overlay_forbidden', __( 'Sorry, this learner is not in your schools or classes.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$user = get_userdata( $learner );
		if ( ! $user ) {
			return new WP_Error( 'ohmylms_skill_overlay_not_found', __( 'Learner not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$rows = array();
		foreach ( MasteryRepository::learner_percentages( $learner, $skills ) as $skill_id => $percent ) {
			$level        = MasteryLevels::level( $percent, true, $thresholds );
			$distribution = array_fill_keys( array_reverse( Levels::SCALE ), 0 );

			$distribution[ $level ] = 1;

			$rows[] = array(
				'id'           => $skill_id,
				'percent'      => (int) round( $percent ),
				'level'        => $level,
				'assessed'     => 1,
				'distribution' => $distribution,
			);
		}
		$scope = self::learner_scope( $user_id, $learner );
		return array(
			'target' => array(
				'key'      => 'learner:' . $learner,
				'type'     => 'learner',
				'id'       => $learner,
				'label'    => (string) $user->display_name,
				'school'   => '',
				'learners' => 1,
				'counted'  => 1,
			),
			'skills' => $rows,
			'links'  => array(
				'transcript' => null !== $scope && self::can_open_gradebook( $user_id ) ? '/content-hub/gradebook/student?' . http_build_query(
					array(
						'scope'   => $scope,
						'learner' => $learner,
					)
				) : null,
				'report'     => user_can( $user_id, Access::SITE_CAPABILITY ) ? '/students/' . $learner . '/report' : null,
				'gradebook'  => null,
			),
		);
	}

	/**
	 * A class or school's levels, when it is one of the user's scopes.
	 *
	 * @param int    $user_id    User asking.
	 * @param string $key        `class:<id>` or `school:<id>`.
	 * @param int[]  $skills     Skills of the map.
	 * @param array  $thresholds Level thresholds.
	 * @return array|WP_Error target, skills, links.
	 */
	private static function group( $user_id, $key, array $skills, array $thresholds ) {
		$scope = ScopeResolver::resolve( $key, $user_id );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$learners = (array) $scope->learner_ids();
		$counted  = array_slice( $learners, 0, MasteryRepository::MAX_ROW_LEARNERS );
		$rows     = array();
		foreach ( MasteryRepository::level_figures( $counted, $skills, $thresholds ) as $skill_id => $figures ) {
			$percent  = (int) round( $figures['sum'] / max( 1, count( $counted ) ) );
			$attempts = max( 0, $figures['assessed'] - $figures['mastered'] - $figures['proficient'] - $figures['familiar'] );
			$rows[]   = array(
				'id'           => $skill_id,
				'percent'      => $percent,
				'level'        => MasteryLevels::level( $percent, $figures['assessed'] > 0, $thresholds ),
				'assessed'     => $figures['assessed'],
				'distribution' => array(
					'mastered'    => $figures['mastered'],
					'proficient'  => $figures['proficient'],
					'familiar'    => $figures['familiar'],
					'attempted'   => $attempts,
					'not-started' => max( 0, count( $counted ) - $figures['assessed'] ),
				),
			);
		}
		$row = $scope->to_array();
		return array(
			'target' => array(
				'key'      => $scope->key(),
				'type'     => $scope->type(),
				'id'       => $scope->id(),
				'label'    => $scope->label(),
				'school'   => Scope::CLASSROOM === $scope->type() ? $row['school_name'] : '',
				'learners' => count( $learners ),
				'counted'  => count( $counted ),
			),
			'skills' => $rows,
			'links'  => array(
				'transcript' => null,
				'report'     => null,
				'gradebook'  => self::can_open_gradebook( $user_id ) ? '/content-hub/gradebook/mastery?' . http_build_query( array( 'scope' => $scope->key() ) ) : null,
			),
		);
	}

	/**
	 * The scope to open a learner's transcript in: `all` for site administrators, otherwise the first of
	 * the user's scopes the learner belongs to.
	 *
	 * @param int $user_id User.
	 * @param int $learner Learner.
	 * @return string|null Scope key; null when none of the user's scopes holds the learner.
	 */
	private static function learner_scope( $user_id, $learner ) {
		foreach ( ScopeResolver::available( $user_id ) as $scope ) {
			if ( $scope->is_all() || in_array( $learner, array_map( 'intval', (array) $scope->learner_ids() ), true ) ) {
				return $scope->key();
			}
		}
		return null;
	}

	/**
	 * Whether the user may open the Gradebook.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	private static function can_open_gradebook( $user_id ) {
		return user_can( (int) $user_id, \OhMyLMS\Gradebook\Access::CAPABILITY );
	}
}
