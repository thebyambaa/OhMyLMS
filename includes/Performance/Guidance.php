<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * The guidance engine: what a learner is strong in, what to do next and how ready they are.
 *
 * @package OhMyLMS\Performance
 */

namespace OhMyLMS\Performance;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Bundles\Combined;
use OhMyLMS\Curriculum\AiAnalysis;
use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\Schema as CurriculumSchema;
use OhMyLMS\Curriculum\SkillEdges;
use OhMyLMS\Curriculum\SkillMappings;
use OhMyLMS\Practice\Selector;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Student\LearnerProfile;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the learner's skill states, the shared skill library (domains, grades, prerequisites,
 * "encompasses" links) and the syllabuses, and hands them to GuidanceModel. Read only: levels and
 * review dates in `student_skill_state` are never changed here; review credit only changes what is
 * suggested.
 */
final class Guidance {

	/** Most syllabuses read for readiness. */
	const MAX_SYLLABUSES = 30;

	/** User meta holding the exam a learner is preparing for: syllabus_id and date (Y-m-d). */
	const TARGET_META = 'ohmylms_exam_target';

	/**
	 * Results already computed in this request.
	 *
	 * @var array<int,array>
	 */
	private static $cache = array();

	/**
	 * Lookups that do not depend on the learner, kept for the request (a class report reads many).
	 *
	 * @var array<string,mixed>
	 */
	private static $shared = array();

	/**
	 * Everything the learner's guidance shows.
	 *
	 * @param int    $student_id Learner.
	 * @param int    $limit      Most next steps.
	 * @param string $subject    Only this subject's shared skills and syllabuses; empty for all.
	 * @return array subject, subjects (the learner's, for choosing), areas, next_steps (one session of about fifteen minutes; while preparing for an exam, today's part of the exam plan), stuck (every skill needing help, not cut to a session), session_minutes, ready (up to five skills ready to learn, beyond this session), reviews (due, covered, fresh), readiness, exam (the exam plan, or null), review_after_days.
	 */
	public static function for_student( $student_id, $limit = 6, $subject = '' ) {
		$student_id = (int) $student_id;
		$subject    = (string) $subject;
		$key        = $student_id . ':' . (int) $limit . ':' . $subject;
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}
		$days   = max( 1, (int) ( Mastery::rules()['review_after_days'] ?? 7 ) );
		$result = array(
			'subject'           => $subject,
			'subjects'          => array(),
			'areas'             => array(),
			'next_steps'        => array(),
			'stuck'             => array(),
			'session_minutes'   => 0,
			'ready'             => array(),
			'reviews'           => array(
				'due'     => 0,
				'covered' => array(),
				'fresh'   => 0,
			),
			'readiness'         => array(),
			'exam'              => null,
			'review_after_days' => $days,
		);
		if ( ! $student_id || ! Schema::ready() ) {
			return $result;
		}
		$states     = self::states( $student_id );
		$syllabuses = self::syllabus_objectives();
		$objectives = array();
		foreach ( $syllabuses as $syllabus ) {
			$objectives = array_merge( $objectives, $syllabus['objectives'] );
		}
		$pool   = self::pool( array_merge( array_keys( $states ), $objectives ) );
		$pooled = self::combine( $student_id, GuidanceModel::pooled( $states, $pool ) );
		$info   = self::library( $pooled, $pool );
		foreach ( array_keys( $pooled ) as $skill ) {
			$name = self::subject_of( $skill );
			if ( '' !== $name ) {
				$result['subjects'][ $name ] = true;
			}
		}
		$result['subjects'] = array_keys( $result['subjects'] );
		sort( $result['subjects'] );
		if ( '' !== $subject ) {
			// One subject: its shared skills, and the syllabuses chosen for it.
			$in         = static function ( $skill ) use ( $subject ) {
				return self::subject_of( $skill ) === $subject;
			};
			$pooled     = array_filter( $pooled, $in, ARRAY_FILTER_USE_KEY );
			$info       = array_filter( $info, $in, ARRAY_FILTER_USE_KEY );
			$chosen     = (array) ( get_option( Comparison::OPTION, array() )[ $subject ] ?? array() );
			$syllabuses = array_values(
				array_filter(
					$syllabuses,
					static function ( $syllabus ) use ( $chosen ) {
						return in_array( $syllabus['id'], array_map( 'intval', $chosen ), true );
					}
				)
			);
		}
		$links   = self::encompasses( array_keys( $pooled ) );
		$placed  = \OhMyLMS\Placement\Placement::assumed( $student_id, $subject );
		$areas   = GuidanceModel::areas( $pooled, $info );
		$reviews = GuidanceModel::reviews( $pooled, $links, time(), $days );
		$ready   = GuidanceModel::ready( $pooled, $info, $areas, $placed['known'], $placed['start'], self::grade( $student_id ), GuidanceModel::clears( $reviews, $links ) );
		$general = GuidanceModel::next_steps( $pooled, $reviews, $ready, 20 );
		// Every skill the learner is stuck on, not only the ones that fit one sitting (for teachers).
		foreach ( $general as $step ) {
			if ( 'help' === $step['type'] ) {
				$item = self::step( $step, $pooled, $pool, $info );
				if ( $item ) {
					$result['stuck'][] = $item;
				}
			}
		}
		$exam = self::exam( $student_id, $pooled, $pool, $info );
		if ( $exam && $exam['plan']['today'] ) {
			// Preparing for an exam: help first, then today's part of the plan, then anything else due.
			$general = self::exam_steps( $general, $exam['plan'], $pooled );
		}
		// One sitting: the steps that fit about fifteen minutes, never more than asked for.
		$steps = array_slice( GuidanceModel::session( $general ), 0, max( 1, (int) $limit ) );
		if ( $exam ) {
			$result['exam'] = self::describe_exam( $exam );
		}

		$domains = Comparison::domains( $subject );
		foreach ( $areas as $area ) {
			$result['areas'][] = $area + array(
				'label'       => $domains[ $area['domain'] ] ?? ucfirst( $area['domain'] ),
				'band'        => GuidanceModel::strength_band( $area['strength'] ),
				'grade_label' => $area['grade'] ? Comparison::grade_label( $area['grade'], $subject ) : '',
			);
		}
		foreach ( $steps as $step ) {
			$item = self::step( $step, $pooled, $pool, $info );
			if ( $item ) {
				$result['next_steps'][]     = $item;
				$result['session_minutes'] += $item['minutes'];
			}
		}
		foreach ( array_slice( $ready, 0, 5 ) as $entry ) {
			$item = self::step(
				array(
					'type'          => 'ready',
					'skill'         => $entry['skill'],
					'prerequisites' => $entry['prerequisites'],
					'reviews'       => $entry['reviews'],
					'reason'        => $entry['placement'] ? 'placement' : '',
				),
				$pooled,
				$pool,
				$info
			);
			if ( $item ) {
				$result['ready'][] = $item;
			}
		}
		foreach ( $reviews as $skill => $review ) {
			if ( 'due' === $review['status'] ) {
				++$result['reviews']['due'];
			} elseif ( 'fresh' === $review['status'] ) {
				++$result['reviews']['fresh'];
			} elseif ( self::name( $skill ) ) {
				$result['reviews']['covered'][] = array(
					'skill'       => self::skill( $skill ),
					'credit'      => $review['credit'],
					'covered_by'  => self::covered_by( $review['covered_by'] ),
					'due_at'      => gmdate( 'Y-m-d H:i:s', $review['due_at'] ),
					'next_due_at' => gmdate( 'Y-m-d H:i:s', $review['effective_due_at'] ),
				);
			}
		}
		foreach ( $syllabuses as $syllabus ) {
			$readiness = GuidanceModel::readiness( $syllabus['objectives'], $pooled, $pool );
			if ( ! $readiness['ready'] && ! $readiness['learning'] ) {
				continue;
			}
			$readiness['gaps']     = array_values( array_filter( array_map( array( __CLASS__, 'skill' ), $readiness['gaps'] ) ) );
			$result['readiness'][] = array(
				'syllabus' => array(
					'id'   => $syllabus['id'],
					'name' => $syllabus['name'],
					'code' => $syllabus['code'],
					'path' => $syllabus['path'],
				),
			) + $readiness;
		}
		usort(
			$result['readiness'],
			static function ( $left, $right ) {
				return $right['percent'] <=> $left['percent'];
			}
		);
		self::$cache[ $key ] = $result;
		return $result;
	}

	/**
	 * The learner's level on each skill, pooled the same way as guidance: an objective with an
	 * equivalent mapping counts as its shared skill. Used by the student skill map.
	 *
	 * @param int $student_id Learner.
	 * @return array<int,string> Skill => level, for skills the learner has started.
	 */
	public static function levels( $student_id ) {
		if ( ! (int) $student_id || ! Schema::ready() ) {
			return array();
		}
		$states = self::states( (int) $student_id );
		$levels = array();
		foreach ( self::combine( (int) $student_id, GuidanceModel::pooled( $states, self::pool( array_keys( $states ) ) ) ) as $skill => $entry ) {
			$levels[ $skill ] = $entry['level'];
		}
		return $levels;
	}

	/**
	 * Credit back: the level an objective gets from the learner's work on other objectives of the same shared skill.
	 *
	 * An objective with an `equivalent` mapping stands for its shared skill, so answers given under another
	 * syllabus count here too. Only objectives whose pooled level is above their own are returned.
	 *
	 * @param int   $student_id Learner.
	 * @param int[] $terms      Objectives to look up.
	 * @return array<int,array> Objective => level (the pooled level) and own (its own stored level).
	 */
	public static function credit( $student_id, array $terms ) {
		$terms = array_values( array_unique( array_filter( array_map( 'intval', $terms ) ) ) );
		if ( ! $terms || ! (int) $student_id || ! Schema::ready() ) {
			return array();
		}
		$states = self::states( (int) $student_id );
		$pool   = self::pool( array_merge( array_keys( $states ), $terms ) );
		$pooled = self::combine( (int) $student_id, GuidanceModel::pooled( $states, $pool ) );
		$credit = array();
		foreach ( $terms as $term ) {
			$skill = (int) ( $pool[ $term ] ?? $term );
			if ( $skill === $term || ! isset( $pooled[ $skill ] ) ) {
				continue;
			}
			$own = (string) ( $states[ $term ]['level'] ?? 'not-started' );
			if ( Levels::rank( $pooled[ $skill ]['level'] ) > Levels::rank( $own ) ) {
				$credit[ $term ] = array(
					'level' => $pooled[ $skill ]['level'],
					'own'   => $own,
				);
			}
		}
		return $credit;
	}

	/**
	 * Judge each shared skill on all of the learner's answers across the objectives pooled onto it.
	 *
	 * GuidanceModel::pooled() takes the best level any single member reached. When answers are spread
	 * over several members (two on a Grade 7 objective, two on the IGCSE one), that never adds up; here
	 * those answers are pooled, counted once each (Bundles\Combined) and judged by the site's level
	 * policy, like any skill. Skills with one member keep their stored state, which is the same thing.
	 *
	 * @param int              $student_id Learner.
	 * @param array<int,array> $pooled     From GuidanceModel::pooled().
	 * @return array<int,array> The same skills with level, struggling and last from the pooled answers.
	 */
	private static function combine( $student_id, array $pooled ) {
		global $wpdb;
		$owner = array();
		foreach ( $pooled as $skill => $entry ) {
			if ( count( $entry['terms'] ) > 1 ) {
				foreach ( $entry['terms'] as $term ) {
					$owner[ (int) $term ] = (int) $skill;
				}
			}
		}
		if ( ! $owner ) {
			return $pooled;
		}
		$terms = array_keys( $owner );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, grade_event_id, term_id, part_id, question_id, awarded, available, independent, first_try, difficulty, family_id, source_type, source_id, evidence_at FROM %i WHERE student_id=%d AND role='primary' AND superseded=0 AND term_id IN (" . implode( ',', array_fill( 0, count( $terms ), '%d' ) ) . ')', array_merge( array( Schema::table( 'skill_evidence' ), (int) $student_id ), $terms ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fresh read of the learner's own evidence; one placeholder per term.
		$by    = array();
		foreach ( $rows as $row ) {
			$by[ $owner[ (int) $row['term_id'] ] ][] = $row;
		}
		$now = time();
		foreach ( $by as $skill => $evidence ) {
			$state                          = Engine::evaluate( Combined::merge( $evidence ), $now );
			$pooled[ $skill ]['level']      = $state['level'];
			$pooled[ $skill ]['struggling'] = ! empty( $state['struggling'] ) && Levels::is_developing( $state['level'] );
			$pooled[ $skill ]['last']       = $state['last_evidence_at'] ? (int) strtotime( $state['last_evidence_at'] . ' UTC' ) : $pooled[ $skill ]['last'];
		}
		return $pooled;
	}

	/**
	 * The exam a learner is preparing for.
	 *
	 * @param int $student_id Learner.
	 * @return array|null syllabus_id and date (Y-m-d), or null when none is set.
	 */
	public static function target( $student_id ) {
		$target = get_user_meta( (int) $student_id, self::TARGET_META, true );
		if ( ! is_array( $target ) || empty( $target['syllabus_id'] ) || empty( $target['date'] ) ) {
			return null;
		}
		return array(
			'syllabus_id' => (int) $target['syllabus_id'],
			'date'        => (string) $target['date'],
		);
	}

	/**
	 * Set or clear the exam a learner is preparing for.
	 *
	 * @param int    $student_id Learner.
	 * @param int    $syllabus   Target syllabus, or 0 to clear.
	 * @param string $date       Exam date, Y-m-d.
	 * @return array|null|\WP_Error The stored target, null when cleared, or an error.
	 */
	public static function set_target( $student_id, $syllabus, $date ) {
		if ( ! (int) $syllabus ) {
			delete_user_meta( (int) $student_id, self::TARGET_META );
			self::flush();
			return null;
		}
		if ( ! \OhMyLMS\Curriculum\Syllabus::is_syllabus( (int) $syllabus ) ) {
			return new \WP_Error( 'ohmylms_exam_target', __( 'Choose an exam or syllabus from the list.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$days = ExamPlan::days_left( (string) $date, time() );
		if ( null === $days || $days < 0 || $days > ExamPlan::MAX_DAYS ) {
			return new \WP_Error( 'ohmylms_exam_target', __( 'Choose an exam date from today up to three years ahead.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		update_user_meta(
			(int) $student_id,
			self::TARGET_META,
			array(
				'syllabus_id' => (int) $syllabus,
				'date'        => (string) $date,
				'set_at'      => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		self::flush();
		return self::target( (int) $student_id );
	}

	/**
	 * Exams and syllabuses a learner can prepare for.
	 *
	 * @return array[] id, name.
	 */
	public static function targets() {
		return array_map(
			static function ( $syllabus ) {
				return array(
					'id'   => (int) $syllabus['id'],
					'name' => (string) $syllabus['name'],
				);
			},
			Comparison::syllabuses()
		);
	}

	/**
	 * The exam plan, when the learner has a target that is not past.
	 *
	 * @param int              $student_id Learner.
	 * @param array<int,array> $pooled     Pooled skills.
	 * @param array<int,int>   $pool       Term => shared skill.
	 * @param array<int,array> $info       Library.
	 * @return array|null target, syllabus name, readiness and plan; null without an active target.
	 */
	private static function exam( $student_id, array $pooled, array $pool, array $info ) {
		$target = self::target( $student_id );
		if ( ! $target ) {
			return null;
		}
		$days = ExamPlan::days_left( $target['date'], time() );
		$rows = Comparison::objectives( $target['syllabus_id'] );
		if ( null === $days || $days < 0 || is_wp_error( $rows ) ) {
			return null;
		}
		$objectives = array_map( 'intval', array_column( $rows, 'term_id' ) );
		// The target's objectives may not be in the pool yet: read their mappings too.
		$pool   = $pool + self::pool( $objectives );
		$worked = array();
		foreach ( $objectives as $term ) {
			$skill = (int) ( $pool[ $term ] ?? $term );
			if ( isset( $pooled[ $skill ] ) ) {
				$worked[] = $skill;
			}
		}
		$item = \OhMyLMS\Curriculum\Items::get( $target['syllabus_id'] );
		return array(
			'target'    => $target,
			'name'      => $item ? (string) $item['name'] : '',
			'readiness' => GuidanceModel::readiness( $objectives, $pooled, $pool, 5 ),
			'plan'      => ExamPlan::plan( ExamPlan::gaps( $objectives, $pooled, $pool, $info ), $worked, $pooled, $days, time() ),
		);
	}

	/**
	 * Next steps while preparing for an exam: help first, then today's plan, then the rest.
	 *
	 * @param array[]          $general Next steps from GuidanceModel::next_steps().
	 * @param array            $plan    From ExamPlan::plan().
	 * @param array<int,array> $pooled  Pooled skills.
	 * @return array[]
	 */
	private static function exam_steps( array $general, array $plan, array $pooled ) {
		$steps = array();
		$seen  = array();
		foreach ( $general as $step ) {
			if ( 'help' === $step['type'] ) {
				$steps[]                = $step;
				$seen[ $step['skill'] ] = true;
			}
		}
		$day = $plan['schedule'][0]['phase'] ?? $plan['phase'];
		foreach ( $plan['today'] as $skill ) {
			if ( isset( $seen[ $skill ] ) ) {
				continue;
			}
			$level          = (string) ( $pooled[ $skill ]['level'] ?? 'not-started' );
			$type           = 'review' === $day ? 'review' : ( Levels::is_developing( $level ) ? 'continue' : 'ready' );
			$steps[]        = array(
				'type'          => $type,
				'skill'         => (int) $skill,
				'prerequisites' => array(),
				'reviews'       => array(),
				'credit'        => 0.0,
				'covered_by'    => array(),
				'reason'        => 'exam',
				'minutes'       => GuidanceModel::STEP_MINUTES[ $type ],
			);
			$seen[ $skill ] = true;
		}
		foreach ( $general as $step ) {
			if ( ! isset( $seen[ $step['skill'] ] ) ) {
				$steps[]                = $step;
				$seen[ $step['skill'] ] = true;
			}
		}
		return $steps;
	}

	/**
	 * The exam plan for display.
	 *
	 * @param array $exam From exam().
	 * @return array
	 */
	private static function describe_exam( array $exam ) {
		$plan = $exam['plan'];
		return array(
			'syllabus'  => array(
				'id'   => $exam['target']['syllabus_id'],
				'name' => $exam['name'],
			),
			'date'      => $exam['target']['date'],
			'days_left' => $plan['days_left'],
			'phase'     => $plan['phase'],
			'per_day'   => $plan['per_day'],
			'remaining' => $plan['remaining'],
			'on_track'  => $plan['on_track'],
			'readiness' => array(
				'percent' => $exam['readiness']['percent'],
				'ready'   => $exam['readiness']['ready'],
				'total'   => $exam['readiness']['total'],
			),
			'schedule'  => array_map(
				static function ( $day ) {
					return array(
						'date'   => $day['date'],
						'phase'  => $day['phase'],
						'skills' => array_values( array_filter( array_map( array( __CLASS__, 'skill' ), $day['skills'] ) ) ),
					);
				},
				$plan['schedule']
			),
		);
	}

	/**
	 * The learner's school grade from their profile, or 0.
	 *
	 * @param int $student_id Learner.
	 * @return int 1 to 12, or 0 when unknown.
	 */
	private static function grade( $student_id ) {
		$grade = (string) get_user_meta( (int) $student_id, LearnerProfile::META_GRADE, true );
		return ctype_digit( $grade ) && (int) $grade >= 1 && (int) $grade <= 12 ? (int) $grade : 0;
	}

	/** Forget results computed earlier in this request (after evidence changes, and in tests). */
	public static function flush() {
		self::$cache  = array();
		self::$shared = array();
	}

	/**
	 * The subject a shared skill belongs to.
	 *
	 * @param int $skill Skill.
	 * @return string Empty for skills outside the shared library.
	 */
	private static function subject_of( $skill ) {
		return (string) get_term_meta( (int) $skill, Comparison::SUBJECT_META, true );
	}

	/**
	 * The learner's stored states.
	 *
	 * @param int $student_id Learner.
	 * @return array<int,array>
	 */
	private static function states( $student_id ) {
		global $wpdb;
		$states = array();
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT term_id, level, struggling, last_evidence_at FROM %i WHERE student_id=%d', Schema::table( 'student_skill_state' ), (int) $student_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh read of the learner's own states.
		foreach ( $rows as $row ) {
			$states[ (int) $row['term_id'] ] = array(
				'level'            => (string) $row['level'],
				'struggling'       => (bool) $row['struggling'],
				'last_evidence_at' => $row['last_evidence_at'] ? strtotime( $row['last_evidence_at'] . ' UTC' ) : null,
			);
		}
		return $states;
	}

	/**
	 * Equivalent mappings of the given terms: term => shared skill.
	 *
	 * @param int[] $terms Terms.
	 * @return array<int,int>
	 */
	private static function pool( array $terms ) {
		$terms = array_values( array_unique( array_filter( array_map( 'intval', $terms ) ) ) );
		$pool  = array();
		if ( ! $terms || ! CurriculumSchema::ready() ) {
			return $pool;
		}
		$wanted = array_flip( $terms );
		foreach ( array_chunk( $terms, 500 ) as $chunk ) {
			foreach ( SkillMappings::all( $chunk ) as $mapping ) {
				$specific = (int) $mapping['specific_term_id'];
				if ( 'equivalent' === $mapping['relation'] && isset( $wanted[ $specific ] ) && ! isset( $pool[ $specific ] ) ) {
					$pool[ $specific ] = (int) $mapping['shared_term_id'];
				}
			}
		}
		return $pool;
	}

	/**
	 * Domain, grades and prerequisites of every skill guidance can name: the shared library, the
	 * learner's own skills, and any other skill with prerequisites. Prerequisites are read through
	 * the pool, so an objective counts as its shared skill.
	 *
	 * @param array<int,array> $pooled Pooled skills.
	 * @param array<int,int>   $pool   Term => shared skill.
	 * @return array<int,array>
	 */
	private static function library( array $pooled, array $pool ) {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => false,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded to skills that carry a placement or prerequisites.
					'relation' => 'OR',
					array(
						'key'     => Comparison::SUBJECT_META,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_ohmylms_prerequisites',
						'compare' => 'EXISTS',
					),
				),
			)
		);
		$ids   = is_wp_error( $terms ) ? array() : array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) );
		$ids   = array_values( array_unique( array_merge( $ids, array_keys( $pooled ) ) ) );
		$info  = array();
		update_termmeta_cache( $ids );
		foreach ( $ids as $id ) {
			// A mapped objective is represented by its shared skill.
			if ( isset( $pool[ $id ] ) ) {
				continue;
			}
			$shared        = metadata_exists( 'term', $id, Comparison::SUBJECT_META );
			$prerequisites = array();
			foreach ( Taxonomy::prerequisites( $id ) as $prerequisite ) {
				$prerequisites[] = (int) ( $pool[ $prerequisite ] ?? $prerequisite );
			}
			$info[ $id ] = array(
				'domain'        => (string) get_term_meta( $id, $shared ? Comparison::DOMAIN_META : AiAnalysis::HINT_DOMAIN, true ),
				'grade_from'    => (int) get_term_meta( $id, $shared ? Comparison::GRADE_FROM_META : AiAnalysis::HINT_FROM, true ),
				'grade_to'      => (int) get_term_meta( $id, $shared ? Comparison::GRADE_TO_META : AiAnalysis::HINT_TO, true ),
				'prerequisites' => array_values( array_unique( array_diff( $prerequisites, array( $id ) ) ) ),
			);
		}
		return $info;
	}

	/**
	 * "Encompasses" links out of the learner's skills, followed as deep as review credit reaches.
	 *
	 * @param int[] $skills Pooled skills.
	 * @return array<int,array<int,float>>
	 */
	private static function encompasses( array $skills ) {
		$links = array();
		if ( ! CurriculumSchema::ready() ) {
			return $links;
		}
		$next = $skills;
		for ( $depth = 0; $depth < GuidanceModel::MAX_DEPTH && $next; $depth++ ) {
			$found = SkillEdges::encompasses_many( $next );
			$links = $links + $found;
			$next  = array();
			foreach ( $found as $inside ) {
				foreach ( array_keys( $inside ) as $other ) {
					if ( ! isset( $links[ $other ] ) ) {
						$next[] = (int) $other;
					}
				}
			}
			$next = array_values( array_unique( $next ) );
		}
		return $links;
	}

	/**
	 * Every syllabus with its objective terms, in syllabus order.
	 *
	 * @return array[] id, name, code, path, objectives.
	 */
	private static function syllabus_objectives() {
		if ( ! CurriculumSchema::ready() ) {
			return array();
		}
		if ( isset( self::$shared['syllabuses'] ) ) {
			return self::$shared['syllabuses'];
		}
		$result = array();
		foreach ( array_slice( Comparison::syllabuses(), 0, self::MAX_SYLLABUSES ) as $syllabus ) {
			$rows = Comparison::objectives( (int) $syllabus['id'] );
			if ( is_wp_error( $rows ) || ! $rows ) {
				continue;
			}
			$result[] = array(
				'id'         => (int) $syllabus['id'],
				'name'       => (string) $syllabus['name'],
				'code'       => (string) $syllabus['code'],
				'path'       => $syllabus['path'],
				'objectives' => array_map( 'intval', array_column( $rows, 'term_id' ) ),
			);
		}
		self::$shared['syllabuses'] = $result;
		return $result;
	}

	/**
	 * One next step for display.
	 *
	 * @param array            $step   From GuidanceModel::next_steps.
	 * @param array<int,array> $pooled Pooled skills.
	 * @param array<int,int>   $pool   Term => shared skill.
	 * @param array<int,array> $info   Library.
	 * @return array|null
	 */
	private static function step( array $step, array $pooled, array $pool, array $info ) {
		$skill = self::skill( $step['skill'] );
		if ( ! $skill ) {
			return null;
		}
		$members = $pooled[ $step['skill'] ]['terms'] ?? array();
		$item    = array(
			'type'          => $step['type'],
			'skill'         => $skill,
			'practice_id'   => self::practice_target( $step['skill'], $members, $pool ),
			'lessons'       => array(),
			'prerequisites' => array(),
			'credit'        => 0.0,
			'covered_by'    => array(),
			'reason'        => '',
			'minutes'       => (int) ( $step['minutes'] ?? GuidanceModel::STEP_MINUTES[ $step['type'] ] ?? 5 ),
			'also_reviews'  => array_values( array_filter( array_map( array( __CLASS__, 'skill' ), (array) ( $step['reviews'] ?? array() ) ) ) ),
		);
		if ( 'help' === $step['type'] ) {
			$item['message'] = __( 'Recent answers on this skill had errors. Revisiting the lesson or a prerequisite may help.', 'ohmylms' );
			$item['lessons'] = self::lessons( array_merge( array( $step['skill'] ), $members ) );
			foreach ( $info[ $step['skill'] ]['prerequisites'] ?? array() as $prerequisite ) {
				$weak = self::skill( $prerequisite );
				if ( $weak && ( ! isset( $pooled[ $prerequisite ] ) || ! Levels::is_proficient( $pooled[ $prerequisite ]['level'] ) ) ) {
					$item['prerequisites'][] = $weak + array( 'practice_id' => self::practice_target( $prerequisite, $pooled[ $prerequisite ]['terms'] ?? array(), $pool ) );
				}
			}
		} elseif ( 'continue' === $step['type'] ) {
			$item['message'] = 'exam' === ( $step['reason'] ?? '' ) ? __( 'Today in your exam plan: keep going with this skill.', 'ohmylms' ) : __( 'You have started this skill. A little more practice takes it to proficient.', 'ohmylms' );
			$item['reason']  = (string) ( $step['reason'] ?? '' );
			$item['lessons'] = self::lessons( array_merge( array( $step['skill'] ), $members ) );
		} elseif ( 'review' === $step['type'] ) {
			$item['message']    = 'exam' === ( $step['reason'] ?? '' ) ? __( 'Exam review: keep this skill fresh for the exam.', 'ohmylms' ) : __( 'Time for a short review to keep this skill fresh.', 'ohmylms' );
			$item['reason']     = (string) ( $step['reason'] ?? '' );
			$item['credit']     = $step['credit'];
			$item['covered_by'] = self::covered_by( $step['covered_by'] );
		} else {
			$item['reason']        = (string) ( $step['reason'] ?? '' );
			$item['message']       = 'placement' === $item['reason'] ? __( 'Your placement test suggests starting here.', 'ohmylms' ) : __( 'You are ready to start this skill.', 'ohmylms' );
			$item['message']       = 'exam' === $item['reason'] ? __( 'Today in your exam plan.', 'ohmylms' ) : $item['message'];
			$item['lessons']       = self::lessons( array( $step['skill'] ) );
			$item['prerequisites'] = array_values( array_filter( array_map( array( __CLASS__, 'skill' ), $step['prerequisites'] ) ) );
		}
		return $item;
	}

	/**
	 * The skill to open in practice: the skill itself, or the first of the learner's or the mapped
	 * objectives that has practice questions.
	 *
	 * @param int            $skill   Pooled skill.
	 * @param int[]          $members Learner's terms pooled onto it, best first.
	 * @param array<int,int> $pool    Term => shared skill.
	 * @return int 0 when nothing can be practised.
	 */
	private static function practice_target( $skill, array $members, array $pool ) {
		$candidates = array_merge( array( (int) $skill ), $members );
		foreach ( $pool as $specific => $shared ) {
			if ( (int) $shared === (int) $skill ) {
				$candidates[] = (int) $specific;
			}
		}
		foreach ( array_slice( array_values( array_unique( $candidates ) ), 0, 6 ) as $candidate ) {
			if ( Selector::pool( $candidate ) ) {
				return $candidate;
			}
		}
		return 0;
	}

	/**
	 * Up to two published lessons linked to any of the terms.
	 *
	 * @param int[] $terms Terms.
	 * @return array[] id, title, url.
	 */
	private static function lessons( array $terms ) {
		$lessons = array();
		foreach ( array_unique( $terms ) as $term ) {
			foreach ( Taxonomy::linked_posts( (int) $term, OHMYLMS_LESSON_CPT ) as $id ) {
				if ( 'publish' === get_post_status( $id ) && ! isset( $lessons[ $id ] ) ) {
					$lessons[ $id ] = array(
						'id'    => (int) $id,
						'title' => wp_strip_all_tags( get_the_title( $id ) ),
						'url'   => get_permalink( $id ),
					);
				}
				if ( count( $lessons ) >= 2 ) {
					return array_values( $lessons );
				}
			}
		}
		return array_values( $lessons );
	}

	/**
	 * Skills that earned review credit, for display.
	 *
	 * @param array<int,float> $by Skill => weight.
	 * @return array[] id, name, weight.
	 */
	private static function covered_by( array $by ) {
		$result = array();
		foreach ( $by as $id => $weight ) {
			$skill = self::skill( $id );
			if ( $skill ) {
				$result[] = $skill + array( 'weight' => (float) $weight );
			}
		}
		return $result;
	}

	/**
	 * A skill's id and name.
	 *
	 * @param int $id Skill.
	 * @return array|null
	 */
	public static function skill( $id ) {
		$name = self::name( $id );
		return $name ? array(
			'id'   => (int) $id,
			'name' => $name,
		) : null;
	}

	/**
	 * A skill's plain name.
	 *
	 * @param int $id Skill.
	 * @return string Empty when the skill is gone.
	 */
	private static function name( $id ) {
		$term = get_term( (int) $id, Taxonomy::NAME );
		return $term && ! is_wp_error( $term ) ? Taxonomy::plain( $term->name ) : '';
	}
}
