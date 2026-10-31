<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * "Needs attention": a ranked queue of things to act on.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use DateTimeImmutable;
use OhMyLMS\Dashboard\Access;
use OhMyLMS\Dashboard\AtRiskProvider;
use OhMyLMS\Dashboard\Enrolments;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Repository;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\Snoozes;
use OhMyLMS\Dashboard\Widget;
use OhMyLMS\Learning\CourseStructure;

defined( 'ABSPATH' ) || exit;

/**
 * Items, most urgent first (docs/api/dashboard.md, "Needs attention"):
 *
 * - `grading`: assignment submissions waiting more than N days (default 3) to be graded;
 * - `failed_payments`: orders that failed in the period (`all` scope only);
 * - `manual_approval`: orders on hold, waiting for an administrator (`all` scope only);
 * - `at_risk`: learners at high risk, only when an {@see AtRiskProvider} is registered;
 * - `stalling_course:<id>`: courses with low completion and the item where most learners stop;
 * - `missed_question:<id>`: quiz questions most learners answered wrongly in the period.
 *
 * Items carry counts and content titles only. The cached list is shared by everyone who may see the scope;
 * snoozes and links the user cannot open are removed per user in {@see NeedsAttention::present()}.
 */
final class NeedsAttention extends Widget {

	/**
	 * Widget id.
	 *
	 * @return string
	 */
	public function id() {
		return 'needs-attention';
	}

	/**
	 * Days a submission may wait before it needs attention.
	 *
	 * @return int
	 */
	public static function grading_days() {
		/**
		 * Filters how many days an assignment submission may wait for grading before the dashboard lists it.
		 *
		 * @param int $days Days (default 3).
		 */
		return max( 0, (int) apply_filters( 'ohmylms_dashboard_grading_wait_days', 3 ) );
	}

	/**
	 * Thresholds of the course and question rules.
	 *
	 * @return array{stalling:array{min_learners:int,max_rate:float,limit:int},missed:array{min_learners:int,min_rate:float,limit:int}}
	 */
	public static function rules() {
		/**
		 * Filters the "stalling course" and "missed question" thresholds.
		 *
		 * @param array $rules `stalling` (min_learners, max_rate 0-1, limit) and `missed` (min_learners, min_rate 0-1, limit).
		 */
		$rules = (array) apply_filters(
			'ohmylms_dashboard_attention_rules',
			array(
				'stalling' => array(
					'min_learners' => 5,
					'max_rate'     => 0.3,
					'limit'        => 3,
				),
				'missed'   => array(
					'min_learners' => 5,
					'min_rate'     => 0.6,
					'limit'        => 3,
				),
			)
		);
		return array(
			'stalling' => array(
				'min_learners' => max( 1, (int) ( $rules['stalling']['min_learners'] ?? 5 ) ),
				'max_rate'     => min( 1, max( 0, (float) ( $rules['stalling']['max_rate'] ?? 0.3 ) ) ),
				'limit'        => min( 10, max( 0, (int) ( $rules['stalling']['limit'] ?? 3 ) ) ),
			),
			'missed'   => array(
				'min_learners' => max( 1, (int) ( $rules['missed']['min_learners'] ?? 5 ) ),
				'min_rate'     => min( 1, max( 0, (float) ( $rules['missed']['min_rate'] ?? 0.6 ) ) ),
				'limit'        => min( 10, max( 0, (int) ( $rules['missed']['limit'] ?? 3 ) ) ),
			),
		);
	}

	/**
	 * The items of a scope and period, unsorted.
	 *
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period.
	 * @return array
	 */
	public function compute( Scope $scope, Period $period ) {
		$now   = new DateTimeImmutable( 'now', wp_timezone() );
		$range = $period->range();
		$days  = self::grading_days();
		$rules = self::rules();
		$items = array();

		$waiting = Repository::waiting_submissions( $scope, $now->modify( '-' . $days . ' days' )->format( 'Y-m-d H:i:s' ) );
		if ( $waiting['count'] > 0 ) {
			$age     = self::age_in_days( $waiting['oldest'], $now );
			$items[] = self::item(
				'grading',
				80 + min( 15, max( 0, $age - $days ) ),
				$age >= 7 ? 'error' : 'warning',
				/* translators: 1: number of submissions, 2: number of days. */
				sprintf( _n( '%1$s submission waiting more than %2$s days to be graded', '%1$s submissions waiting more than %2$s days to be graded', $waiting['count'], 'ohmylms' ), number_format_i18n( $waiting['count'] ), number_format_i18n( $days ) ),
				/* translators: 1: assignment title, 2: date. */
				sprintf( __( '%1$s · oldest submitted %2$s', 'ohmylms' ), $waiting['oldest_title'], self::date( $waiting['oldest'] ) ),
				$waiting['count'],
				__( 'Grade now', 'ohmylms' ),
				'/assignments/' . $waiting['oldest_assignment'] . '/submissions',
				'edit_posts'
			);
		}

		if ( $scope->includes_commerce() ) {
			$failed = Repository::orders_in_status( 'ohmylms-failed', $range->start_sql(), $range->end_sql() );
			if ( $failed['count'] > 0 ) {
				$items[] = self::item(
					'failed_payments',
					95,
					'error',
					/* translators: %s: number of payments. */
					sprintf( _n( '%s payment failed', '%s payments failed', $failed['count'], 'ohmylms' ), number_format_i18n( $failed['count'] ) ),
					/* translators: %s: number of days. */
					sprintf( _n( 'In the last %s day', 'In the last %s days', $period->days(), 'ohmylms' ), number_format_i18n( $period->days() ) ),
					$failed['count'],
					__( 'Review orders', 'ohmylms' ),
					'/orders',
					Access::SITE_CAPABILITY
				);
			}
			$held = Repository::orders_in_status( 'ohmylms-on-hold' );
			if ( $held['count'] > 0 ) {
				$items[] = self::item(
					'manual_approval',
					70 + min( 10, self::age_in_days( $held['oldest'], $now ) ),
					'warning',
					/* translators: %s: number of orders. */
					sprintf( _n( '%s order pending manual approval', '%s orders pending manual approval', $held['count'], 'ohmylms' ), number_format_i18n( $held['count'] ) ),
					/* translators: %s: date. */
					sprintf( __( 'Oldest waiting since %s', 'ohmylms' ), self::date( $held['oldest'] ) ),
					$held['count'],
					__( 'Review', 'ohmylms' ),
					'/orders',
					Access::SITE_CAPABILITY
				);
			}
		}

		/**
		 * Filters the source of the "learners at high risk" item; return an
		 * `OhMyLMS\Dashboard\AtRiskProvider`. Without one the item is not shown.
		 *
		 * @param AtRiskProvider|null $provider Provider.
		 */
		$provider = apply_filters( 'ohmylms_dashboard_at_risk_provider', null );
		if ( $provider instanceof AtRiskProvider ) {
			$count = max( 0, (int) $provider->high_risk_count( $scope, $period ) );
			$route = $provider->route( $scope );
			if ( $count > 0 ) {
				$items[] = self::item(
					'at_risk',
					85,
					'error',
					/* translators: %s: number of learners. */
					sprintf( _n( '%s learner at high risk of dropping out', '%s learners at high risk of dropping out', $count, 'ohmylms' ), number_format_i18n( $count ) ),
					__( 'Inactivity, falling mastery or overdue work', 'ohmylms' ),
					$count,
					__( 'See who', 'ohmylms' ),
					is_string( $route ) && '' !== $route ? $route : null,
					Access::CAPABILITY
				);
			}
		}

		foreach ( self::stalling_courses( $scope, $rules['stalling'] ) as $course ) {
			$items[] = $course;
		}

		if ( $rules['missed']['limit'] > 0 ) {
			foreach ( Repository::missed_questions( $scope, $range->start_sql(), $range->end_sql(), $rules['missed']['min_learners'], $rules['missed']['min_rate'], $rules['missed']['limit'] ) as $question ) {
				$rate    = $question['answers'] > 0 ? $question['missed'] / $question['answers'] : 0;
				$items[] = self::item(
					'missed_question:' . $question['question_id'],
					40 + (int) round( 20 * $rate ),
					'info',
					/* translators: %s: percentage of wrong answers. */
					sprintf( __( 'Question missed by %s%% of learners', 'ohmylms' ), number_format_i18n( (int) round( 100 * $rate ) ) ),
					/* translators: 1: question title, 2: quiz title, 3: number of learners. */
					sprintf( _n( '“%1$s” in %2$s · %3$s learner answered', '“%1$s” in %2$s · %3$s learners answered', $question['learners'], 'ohmylms' ), $question['title'], $question['quiz_title'], number_format_i18n( $question['learners'] ) ),
					$question['missed'],
					__( 'Edit question', 'ohmylms' ),
					'/content-hub/question-bank/' . $question['question_id'],
					'edit_posts'
				);
			}
		}

		/**
		 * Filters the "needs attention" items of a scope and period before they are cached. Items are
		 * shared by everyone who may see the scope: never add personal data or per-user state.
		 *
		 * @param array[] $items  Items (see docs/api/dashboard.md).
		 * @param Scope   $scope  Scope.
		 * @param Period  $period Period.
		 */
		$items = (array) apply_filters( 'ohmylms_dashboard_needs_attention_items', $items, $scope, $period );

		return array(
			'items'        => array_values( array_filter( $items, array( __CLASS__, 'is_item' ) ) ),
			'grading_days' => $days,
		);
	}

	/**
	 * Per user: drop snoozed items and links the user cannot open, most urgent first.
	 *
	 * @param array  $data    Cached data.
	 * @param Scope  $scope   Scope.
	 * @param Period $period  Period.
	 * @param int    $user_id User.
	 * @return array{items:array[],snoozed:int,grading_days:int}
	 */
	public function present( array $data, Scope $scope, Period $period, $user_id ) {
		$snoozes = Snoozes::active( $user_id );
		$visible = array();
		$snoozed = 0;
		foreach ( (array) ( $data['items'] ?? array() ) as $item ) {
			if ( null !== Snoozes::until( $snoozes, $scope, $item['key'] ) ) {
				++$snoozed;
				continue;
			}
			$action = $item['action'];
			if ( is_array( $action ) && ( '' === (string) $action['route'] || ! user_can( (int) $user_id, (string) $action['capability'] ) ) ) {
				$action = null;
			}
			$item['action'] = is_array( $action ) ? array(
				'label' => (string) $action['label'],
				'route' => (string) $action['route'],
			) : null;
			$visible[]      = $item;
		}
		usort(
			$visible,
			static function ( $a, $b ) {
				return $b['urgency'] <=> $a['urgency'] ?: strcmp( $a['key'], $b['key'] );
			}
		);
		return array(
			'items'        => $visible,
			'snoozed'      => $snoozed,
			'grading_days' => (int) ( $data['grading_days'] ?? self::grading_days() ),
		);
	}

	/**
	 * Stalling courses with the item where most of their learners stop.
	 *
	 * @param Scope $scope Scope.
	 * @param array $rule  min_learners, max_rate, limit.
	 * @return array[] Items.
	 */
	private static function stalling_courses( Scope $scope, array $rule ) {
		if ( $rule['limit'] <= 0 ) {
			return array();
		}
		$items = array();
		foreach ( Enrolments::low_completion_courses( $scope, $rule['min_learners'], $rule['max_rate'], $rule['limit'] ) as $course ) {
			$stop = self::drop_off( $scope, $course['course_id'], $course['enrolled'] );
			if ( null === $stop ) {
				continue;
			}
			$rate    = $course['enrolled'] > 0 ? $course['completed'] / $course['enrolled'] : 0;
			$items[] = self::item(
				'stalling_course:' . $course['course_id'],
				50 + (int) round( 20 * ( 1 - $rate ) ),
				'warning',
				/* translators: %s: course title. */
				sprintf( __( '%s is stalling', 'ohmylms' ), html_entity_decode( get_the_title( $course['course_id'] ), ENT_QUOTES, 'UTF-8' ) ),
				/* translators: 1: completion percentage, 2: number of learners, 3: percentage who stop, 4: content title. */
				sprintf( __( '%1$s%% of %2$s learners finish · %3$s%% stop at “%4$s”', 'ohmylms' ), number_format_i18n( (int) round( 100 * $rate ) ), number_format_i18n( $course['enrolled'] ), number_format_i18n( $stop['percent'] ), $stop['title'] ),
				$course['enrolled'] - $course['completed'],
				__( 'Open report', 'ohmylms' ),
				'/reports/courses/' . $course['course_id'],
				Access::SITE_CAPABILITY
			);
		}
		return $items;
	}

	/**
	 * The content item of a course with the largest drop in learners who completed it, against the item
	 * before it (or against everyone enrolled, for the first item).
	 *
	 * @param Scope $scope     Scope.
	 * @param int   $course_id Course.
	 * @param int   $enrolled  Learners of the scope enrolled.
	 * @return array{content_id:int,title:string,percent:int}|null
	 */
	private static function drop_off( Scope $scope, $course_id, $enrolled ) {
		$structure = CourseStructure::native_items( (int) $course_id );
		if ( ! $structure || $enrolled <= 0 ) {
			return null;
		}
		$done   = Enrolments::completed_items( $scope, $course_id );
		$ids    = array_values( array_unique( array_map( 'intval', wp_list_pluck( $structure, 'content_id' ) ) ) );
		$before = $enrolled;
		$best   = null;
		$worst  = 0;
		_prime_post_caches( $ids, false, false );
		foreach ( $ids as $id ) {
			if ( 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$now  = min( $before, (int) ( $done[ $id ] ?? 0 ) );
			$lost = $before - $now;
			if ( $lost > $worst ) {
				$worst = $lost;
				$best  = $id;
			}
			$before = $now;
		}
		if ( null === $best ) {
			return null;
		}
		return array(
			'content_id' => $best,
			'title'      => html_entity_decode( wp_strip_all_tags( get_the_title( $best ) ), ENT_QUOTES, 'UTF-8' ),
			'percent'    => (int) round( 100 * $worst / $enrolled ),
		);
	}

	/**
	 * Build an item.
	 *
	 * @param string      $key        Stable key (snoozes use it).
	 * @param int         $urgency    Sort weight, higher first.
	 * @param string      $tone       `error`, `warning` or `info`.
	 * @param string      $title      Headline.
	 * @param string      $detail     Second line.
	 * @param int         $count      The number behind the item.
	 * @param string      $label      Action label.
	 * @param string|null $route      Internal app route of the action.
	 * @param string      $capability Capability needed to open the route.
	 * @return array
	 */
	public static function item( $key, $urgency, $tone, $title, $detail, $count, $label, $route, $capability ) {
		return array(
			'key'     => (string) $key,
			'urgency' => (int) $urgency,
			'tone'    => in_array( $tone, array( 'error', 'warning', 'info' ), true ) ? $tone : 'info',
			'title'   => (string) $title,
			'detail'  => (string) $detail,
			'count'   => (int) $count,
			'action'  => null === $route ? null : array(
				'label'      => (string) $label,
				'route'      => (string) $route,
				'capability' => (string) $capability,
			),
		);
	}

	/**
	 * Whether a value is a well-formed item.
	 *
	 * @param mixed $item Candidate.
	 * @return bool
	 */
	public static function is_item( $item ) {
		if ( ! is_array( $item ) || ! isset( $item['key'], $item['urgency'], $item['tone'], $item['title'], $item['detail'], $item['count'] ) || ! array_key_exists( 'action', $item ) ) {
			return false;
		}
		$action = $item['action'];
		return is_string( $item['key'] ) && preg_match( '/' . Snoozes::KEY_PATTERN . '/', $item['key'] )
			&& ( null === $action || ( is_array( $action ) && isset( $action['label'], $action['route'], $action['capability'] ) ) );
	}

	/**
	 * Whole days since a site-time datetime.
	 *
	 * @param string|null       $datetime MySQL datetime.
	 * @param DateTimeImmutable $now      Now.
	 * @return int
	 */
	private static function age_in_days( $datetime, DateTimeImmutable $now ) {
		if ( ! $datetime ) {
			return 0;
		}
		$then = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $datetime, wp_timezone() );
		return $then ? max( 0, (int) $then->diff( $now )->days ) : 0;
	}

	/**
	 * Site-formatted date of a site-time datetime.
	 *
	 * @param string|null $datetime MySQL datetime.
	 * @return string
	 */
	private static function date( $datetime ) {
		return $datetime ? (string) mysql2date( (string) get_option( 'date_format' ), $datetime ) : '';
	}
}
