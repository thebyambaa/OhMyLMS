<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * The deterministic rules behind the Insights widget: figures in, plain sentences out.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Each rule takes figures already read for a scope and period and returns one insight or null. Rules are
 * pure (no queries, no current user), so they are tested on fixed figures; thresholds come from
 * {@see InsightRules::thresholds()} (docs/api/dashboard.md, "Insights").
 *
 * Insight: `{ key, rule, severity: error|warning|success|info, text, action: { label, route, capability } | null }`.
 */
final class InsightRules {

	/**
	 * Order of severities, most important first.
	 */
	const SEVERITIES = array( 'error', 'warning', 'success', 'info' );

	/**
	 * Order of rules when severities tie.
	 */
	const RULES = array( 'revenue', 'drop_off', 'class_gap', 'missed_question', 'quiz_lessons', 'completions' );

	/**
	 * Default thresholds.
	 */
	const DEFAULTS = array(
		'limit'           => 5,
		'revenue'         => array(
			'min_change' => 5,
			'bad_drop'   => 20,
		),
		'completions'     => array(
			'min_total'  => 5,
			'min_change' => 20,
		),
		'drop_off'        => array(
			'min_learners' => 5,
			'min_percent'  => 30,
			'bad_percent'  => 50,
		),
		'class_gap'       => array(
			'min_points'   => 15,
			'bad_points'   => 30,
			'min_learners' => 3,
			'max_classes'  => 25,
			'max_learners' => 2000,
			'max_skills'   => 15,
		),
		'missed_question' => array(
			'min_learners' => 5,
			'min_rate'     => 0.6,
			'bad_rate'     => 0.75,
		),
		'quiz_lessons'    => array(
			'min_enrolments' => 5,
			'min_ratio'      => 1.5,
			'min_points'     => 15,
		),
	);

	/**
	 * Thresholds with the site's changes (filter `ohmylms_dashboard_insight_rules`), numbers only.
	 *
	 * @return array
	 */
	public static function thresholds() {
		/**
		 * Filters the thresholds of the dashboard insight rules (docs/api/dashboard.md, "Insights").
		 *
		 * @param array $rules Rule => threshold name => number, and `limit` (most insights, 1-5).
		 */
		$given = (array) apply_filters( 'ohmylms_dashboard_insight_rules', self::DEFAULTS );
		$rules = array( 'limit' => min( 5, max( 1, (int) ( $given['limit'] ?? self::DEFAULTS['limit'] ) ) ) );
		foreach ( self::DEFAULTS as $rule => $defaults ) {
			if ( ! is_array( $defaults ) ) {
				continue;
			}
			foreach ( $defaults as $name => $default ) {
				$value                   = $given[ $rule ][ $name ] ?? $default;
				$rules[ $rule ][ $name ] = is_float( $default ) ? max( 0.0, (float) $value ) : max( 0, (int) $value );
			}
		}
		return $rules;
	}

	/**
	 * Revenue of the period against the previous one (site administrators, `all` scope only).
	 *
	 * @param float      $current  Net revenue of the period.
	 * @param float      $previous Net revenue of the previous period.
	 * @param int        $days     Period length.
	 * @param array      $currency Currency (`code`, `symbol`, `decimals`, `position`).
	 * @param array|null $top      Course that earned the most: `title`, `net`.
	 * @param array      $rule     min_change, bad_drop (percent).
	 * @return array|null
	 */
	public static function revenue( $current, $previous, $days, array $currency, $top, array $rule ) {
		$current  = (float) $current;
		$previous = (float) $previous;
		if ( $current <= 0 && $previous <= 0 ) {
			return null;
		}
		$amount = self::money( $current, $currency );
		$lead   = is_array( $top ) && (float) $top['net'] > 0 && $current > 0
			/* translators: 1: course title, 2: amount. */
			? ' ' . sprintf( __( '%1$s brought in the most (%2$s).', 'ohmylms' ), $top['title'], self::money( (float) $top['net'], $currency ) )
			: '';
		if ( $previous <= 0 ) {
			return self::insight(
				'revenue',
				'revenue',
				'success',
				/* translators: 1: amount, 2: number of days. */
				sprintf( _n( 'Revenue is %1$s in the last %2$s day, after none in the days before.', 'Revenue is %1$s in the last %2$s days, after none in the period before.', $days, 'ohmylms' ), $amount, number_format_i18n( $days ) ) . $lead,
				__( 'See earnings', 'ohmylms' ),
				'/earnings',
				Access::SITE_CAPABILITY
			);
		}
		$change = (int) round( 100 * ( $current - $previous ) / $previous );
		if ( abs( $change ) < $rule['min_change'] ) {
			$severity = 'info';
			/* translators: 1: amount, 2: number of days, 3: previous amount. */
			$text = sprintf( _n( 'Revenue is steady at %1$s in the last %2$s day (%3$s before).', 'Revenue is steady at %1$s in the last %2$s days (%3$s in the period before).', $days, 'ohmylms' ), $amount, number_format_i18n( $days ), self::money( $previous, $currency ) );
		} elseif ( $change > 0 ) {
			$severity = 'success';
			/* translators: 1: percentage, 2: number of days, 3: amount. */
			$text = sprintf( _n( 'Revenue is up %1$s%% vs the previous %2$s day (%3$s).', 'Revenue is up %1$s%% vs the previous %2$s days (%3$s).', $days, 'ohmylms' ), number_format_i18n( $change ), number_format_i18n( $days ), $amount );
		} else {
			$severity = -$change >= $rule['bad_drop'] ? 'error' : 'warning';
			/* translators: 1: percentage, 2: number of days, 3: amount. */
			$text = sprintf( _n( 'Revenue is down %1$s%% vs the previous %2$s day (%3$s).', 'Revenue is down %1$s%% vs the previous %2$s days (%3$s).', $days, 'ohmylms' ), number_format_i18n( -$change ), number_format_i18n( $days ), $amount );
		}
		return self::insight( 'revenue', 'revenue', $severity, $text . $lead, __( 'See earnings', 'ohmylms' ), '/earnings', Access::SITE_CAPABILITY );
	}

	/**
	 * Course completions of the period against the previous one.
	 *
	 * @param int   $current  Completions in the period.
	 * @param int   $previous Completions in the previous period.
	 * @param int   $days     Period length.
	 * @param array $rule     min_total (both periods together), min_change (percent).
	 * @return array|null
	 */
	public static function completions( $current, $previous, $days, array $rule ) {
		$current  = (int) $current;
		$previous = (int) $previous;
		if ( $current + $previous < $rule['min_total'] ) {
			return null;
		}
		if ( 0 === $previous ) {
			return self::insight(
				'completions',
				'completions',
				'success',
				/* translators: 1: number of completions, 2: number of days. */
				sprintf( _n( '%1$s course completion in the last %2$s days, after none in the period before.', '%1$s course completions in the last %2$s days, after none in the period before.', $current, 'ohmylms' ), number_format_i18n( $current ), number_format_i18n( $days ) ),
				'',
				null,
				''
			);
		}
		$change = (int) round( 100 * ( $current - $previous ) / $previous );
		if ( abs( $change ) < $rule['min_change'] ) {
			return null;
		}
		$text = $change > 0
			/* translators: 1: percentage, 2: number of days, 3: completions now, 4: completions before. */
			? sprintf( __( 'Course completions are up %1$s%% vs the previous %2$s days (%3$s, against %4$s).', 'ohmylms' ), number_format_i18n( $change ), number_format_i18n( $days ), number_format_i18n( $current ), number_format_i18n( $previous ) )
			/* translators: 1: percentage, 2: number of days, 3: completions now, 4: completions before. */
			: sprintf( __( 'Course completions are down %1$s%% vs the previous %2$s days (%3$s, against %4$s).', 'ohmylms' ), number_format_i18n( -$change ), number_format_i18n( $days ), number_format_i18n( $current ), number_format_i18n( $previous ) );
		return self::insight( 'completions', 'completions', $change > 0 ? 'success' : 'warning', $text, '', null, '' );
	}

	/**
	 * The course whose drop-off item loses the largest share of its learners.
	 *
	 * @param array[] $courses Courses of {@see CourseHealthReport::build()}.
	 * @param array   $rule    min_learners, min_percent, bad_percent.
	 * @return array|null
	 */
	public static function drop_off( array $courses, array $rule ) {
		$best = null;
		foreach ( $courses as $course ) {
			$stop = $course['drop_off'] ?? null;
			if ( ! is_array( $stop ) || $course['enrolled'] < $rule['min_learners'] || $stop['percent'] < $rule['min_percent'] ) {
				continue;
			}
			if ( null === $best || $stop['percent'] > $best['drop_off']['percent'] ) {
				$best = $course;
			}
		}
		if ( null === $best ) {
			return null;
		}
		$stop = $best['drop_off'];
		return self::insight(
			'drop_off:' . $best['id'],
			'drop_off',
			$stop['percent'] >= $rule['bad_percent'] ? 'error' : 'warning',
			/* translators: 1: content title, 2: its position in the course, 3: course title, 4: percentage, 5: number of learners. */
			sprintf( __( '“%1$s” (item %2$s of %3$s) loses %4$s%% of learners: %5$s stop there.', 'ohmylms' ), $stop['title'], number_format_i18n( $stop['position'] ), $best['title'], number_format_i18n( $stop['percent'] ), number_format_i18n( $stop['lost'] ) ),
			__( 'Open report', 'ohmylms' ),
			'/reports/courses/' . (int) $best['id'],
			Access::SITE_CAPABILITY
		);
	}

	/**
	 * A class behind the other classes of the scope on a skill.
	 *
	 * @param array|null $gap  Result of {@see ClassSkillGaps::largest()}.
	 * @param array      $rule min_points, bad_points.
	 * @return array|null
	 */
	public static function class_gap( $gap, array $rule ) {
		if ( ! is_array( $gap ) || $gap['gap'] < $rule['min_points'] ) {
			return null;
		}
		return self::insight(
			'class_gap:' . (int) $gap['class_id'],
			'class_gap',
			$gap['gap'] >= $rule['bad_points'] ? 'error' : 'warning',
			/* translators: 1: class name, 2: points, 3: skill name, 4: class average, 5: average of the other classes, 6: number of learners. */
			sprintf( _n( '%1$s is %2$s pts behind other classes on %3$s (%4$s against %5$s; %6$s learner assessed). A re-teach session could help.', '%1$s is %2$s pts behind other classes on %3$s (%4$s against %5$s; %6$s learners assessed). A re-teach session could help.', $gap['class_learners'], 'ohmylms' ), $gap['class'], number_format_i18n( $gap['gap'] ), $gap['skill'], number_format_i18n( $gap['class_points'] ), number_format_i18n( $gap['others_points'] ), number_format_i18n( $gap['class_learners'] ) ),
			'',
			null,
			''
		);
	}

	/**
	 * The quiz question most learners answered wrongly.
	 *
	 * @param array|null $question Row of `Repository::missed_questions()`.
	 * @param array      $rule     min_learners, min_rate, bad_rate.
	 * @return array|null
	 */
	public static function missed_question( $question, array $rule ) {
		if ( ! is_array( $question ) || $question['answers'] <= 0 || $question['learners'] < $rule['min_learners'] ) {
			return null;
		}
		$rate = $question['missed'] / $question['answers'];
		if ( $rate < $rule['min_rate'] ) {
			return null;
		}
		return self::insight(
			'missed_question:' . (int) $question['question_id'],
			'missed_question',
			$rate >= $rule['bad_rate'] ? 'warning' : 'info',
			/* translators: 1: question title, 2: quiz title, 3: percentage, 4: number of learners. */
			sprintf( _n( '“%1$s” in %2$s is answered wrongly %3$s%% of the time (%4$s learner answered). It may be ambiguous or under-taught.', '“%1$s” in %2$s is answered wrongly %3$s%% of the time (%4$s learners answered). It may be ambiguous or under-taught.', $question['learners'], 'ohmylms' ), $question['title'], $question['quiz_title'], number_format_i18n( (int) round( 100 * $rate ) ), number_format_i18n( $question['learners'] ) ),
			__( 'Edit question', 'ohmylms' ),
			'/content-hub/question-bank/' . (int) $question['question_id'],
			'edit_posts'
		);
	}

	/**
	 * Learners who finish a quiz complete more of their lessons: suggest reminders.
	 *
	 * @param array[]        $rows    Rows of `Enrolments::lessons_by_quiz_taking()`.
	 * @param array<int,int> $lessons Course id => published lessons.
	 * @param array          $rule    min_enrolments (per group), min_ratio, min_points (percentage points).
	 * @return array|null
	 */
	public static function quiz_lessons( array $rows, array $lessons, array $rule ) {
		$empty  = array(
			'enrolments' => 0,
			'possible'   => 0,
			'done'       => 0,
		);
		$groups = array(
			1 => $empty,
			0 => $empty,
		);
		foreach ( $rows as $row ) {
			$total = (int) ( $lessons[ $row['course_id'] ] ?? 0 );
			if ( $total <= 0 ) {
				continue;
			}
			$group                           = $row['took'] ? 1 : 0;
			$groups[ $group ]['enrolments'] += (int) $row['enrolments'];
			$groups[ $group ]['possible']   += (int) $row['enrolments'] * $total;
			$groups[ $group ]['done']       += min( (int) $row['lessons'], (int) $row['enrolments'] * $total );
		}
		$least = max( 1, $rule['min_enrolments'] );
		if ( $groups[1]['enrolments'] < $least || $groups[0]['enrolments'] < $least ) {
			return null;
		}
		$with    = (int) round( 100 * $groups[1]['done'] / $groups[1]['possible'] );
		$without = (int) round( 100 * $groups[0]['done'] / $groups[0]['possible'] );
		if ( $with - $without < $rule['min_points'] || ( $without > 0 && $with / $without < $rule['min_ratio'] ) ) {
			return null;
		}
		$text = $without > 0
			/* translators: 1: ratio such as 2.1, 2: lesson completion with a quiz, 3: without. */
			? sprintf( __( 'Learners who finish a quiz complete %1$s× as many of their lessons as those who do not (%2$s%% against %3$s%%). Turn on reminder emails?', 'ohmylms' ), number_format_i18n( $with / $without, 1 ), number_format_i18n( $with ), number_format_i18n( $without ) )
			/* translators: 1: lesson completion with a quiz. */
			: sprintf( __( 'Learners who finish a quiz complete %1$s%% of their lessons; those who do not have completed none. Turn on reminder emails?', 'ohmylms' ), number_format_i18n( $with ) );
		return self::insight( 'quiz_lessons', 'quiz_lessons', 'info', $text, __( 'Set up reminders', 'ohmylms' ), '/emails', Access::SITE_CAPABILITY );
	}

	/**
	 * The most important insights, at most `$limit`.
	 *
	 * @param array[] $insights Insights (nulls are dropped).
	 * @param int     $limit    Most returned.
	 * @return array[]
	 */
	public static function select( array $insights, $limit ) {
		$insights = array_values( array_filter( $insights, 'is_array' ) );
		usort(
			$insights,
			static function ( $a, $b ) {
				return array( array_search( $a['severity'], self::SEVERITIES, true ), array_search( $a['rule'], self::RULES, true ) )
					<=> array( array_search( $b['severity'], self::SEVERITIES, true ), array_search( $b['rule'], self::RULES, true ) );
			}
		);
		return array_slice( $insights, 0, max( 1, (int) $limit ) );
	}

	/**
	 * Build an insight.
	 *
	 * @param string      $key        Stable key.
	 * @param string      $rule       Rule id.
	 * @param string      $severity   error, warning, success or info.
	 * @param string      $text       Sentence with the numbers.
	 * @param string      $label      Action label.
	 * @param string|null $route      Internal app route of the action, or null for none.
	 * @param string      $capability Capability needed to open the route.
	 * @return array
	 */
	public static function insight( $key, $rule, $severity, $text, $label, $route, $capability ) {
		return array(
			'key'      => (string) $key,
			'rule'     => (string) $rule,
			'severity' => in_array( $severity, self::SEVERITIES, true ) ? $severity : 'info',
			'text'     => (string) $text,
			'action'   => null === $route ? null : array(
				'label'      => (string) $label,
				'route'      => (string) $route,
				'capability' => (string) $capability,
			),
		);
	}

	/**
	 * Plain-text amount in the site currency (symbol position as configured).
	 *
	 * @param float $amount   Amount.
	 * @param array $currency `symbol`, `decimals`, `position` (left, right, left_space, right_space).
	 * @return string
	 */
	public static function money( $amount, array $currency ) {
		$number = number_format_i18n( (float) $amount, (int) ( $currency['decimals'] ?? 2 ) );
		$symbol = (string) ( $currency['symbol'] ?? '' );
		switch ( (string) ( $currency['position'] ?? 'left' ) ) {
			case 'right':
				return $number . $symbol;
			case 'right_space':
				return $number . ' ' . $symbol;
			case 'left_space':
				return $symbol . ' ' . $number;
			default:
				return $symbol . $number;
		}
	}
}
