<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * The adaptive placement test: runs as a practice session of its own mode.
 *
 * @package OhMyLMS\Placement
 */

namespace OhMyLMS\Placement;

use OhMyLMS\Assessment\Engine;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\SkillMap;
use OhMyLMS\Curriculum\SkillMappings;
use OhMyLMS\Learning\PracticeAccessPolicy;
use OhMyLMS\Practice\Selector;
use OhMyLMS\Practice\Sessions;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * A placement test is a practice session (mode `placement`) over a whole subject. Its questions come
 * from the subject's shared skills, or from syllabus objectives mapped to them as `equivalent`, and
 * PlacementModel decides which skill to ask about next from the answers so far. The practice runner
 * shows it; the practice routes grade it.
 *
 * Answers are graded like practice (source `placement`), so the questions actually answered count as
 * skill evidence when the site counts that activity. What the test only infers is kept with the test
 * and shown as a placement; it never becomes a skill level.
 */
final class Placement {

	const MODE = 'placement';

	/**
	 * Start a placement test for a learner, or for a guest, whose test becomes the learner's when they
	 * sign in on the same device (Guests::claim).
	 *
	 * @param array  $owner   student_id, or guest_id.
	 * @param string $subject Subject.
	 * @param int    $grade   Grade the learner says they are in, or 0.
	 * @param int    $limit   Most questions.
	 * @return array|\WP_Error The new session row.
	 */
	public static function start( array $owner, $subject, $grade = 0, $limit = 0 ) {
		global $wpdb;
		$student_id = (int) ( $owner['student_id'] ?? 0 );
		$guest_id   = $student_id ? 0 : (int) ( $owner['guest_id'] ?? 0 );
		$subject    = sanitize_text_field( (string) $subject );
		$grade      = (int) $grade;
		if ( ! $student_id && ! $guest_id ) {
			return new \WP_Error( 'ohmylms_placement_login', __( 'Log in or start as a guest to take a placement test.', 'ohmylms' ), array( 'status' => 401 ) );
		}
		if ( ! Schema::ready() || ! Engine::practice() ) {
			return new \WP_Error( 'ohmylms_practice_disabled', __( 'Practice is not available.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( $grade < 0 || $grade > Comparison::max_level( $subject ) ) {
			return new \WP_Error( 'ohmylms_placement_grade', __( 'Choose a grade from the list.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$graph   = self::graph( $subject );
		$askable = self::askable( self::owner( $student_id, $guest_id ), $graph );
		if ( ! $askable ) {
			return new \WP_Error( 'ohmylms_placement_empty', __( 'There are no placement questions for this subject yet.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$session = array(
			'uuid'       => wp_generate_uuid4(),
			'student_id' => $student_id,
			'guest_id'   => $guest_id,
			'mode'       => self::MODE,
			'term_id'    => 0,
			'course_id'  => 0,
			'policy'     => wp_json_encode(
				array(
					'placement' => array(
						'subject' => $subject,
						'grade'   => $grade,
						'graph'   => $graph,
						'askable' => $askable,
					),
				)
			),
			'status'     => 'active',
			'item_limit' => PlacementModel::limit( $limit ),
			'started_at' => current_time( 'mysql', true ),
		);
		if ( ! $wpdb->insert( Schema::table( 'practice_sessions' ), $session ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- The practice session table.
			return new \WP_Error( 'ohmylms_practice_storage', __( 'Could not start the placement test.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		do_action( 'ohmylms_placement_started', $session['uuid'], $student_id, $subject );
		self::issue( Sessions::get( $session['uuid'] ) );
		return Sessions::get( $session['uuid'] );
	}

	/**
	 * Issue the next question, or finish the test.
	 *
	 * @param array $session Session row.
	 * @return int|null New item ID, or null when the test ended.
	 */
	public static function issue( array $session ) {
		global $wpdb;
		$state        = self::state( $session );
		$observations = self::observations( Sessions::items( (int) $session['id'] ) );
		if ( count( $observations ) >= (int) $session['item_limit'] ) {
			return self::finish( $session, 'limit' );
		}
		$graph     = self::skills( $state['graph'] );
		$estimate  = PlacementModel::estimate( $graph, $state['grade'], $observations );
		$asked     = array_column( $observations, 'skill' );
		$exhausted = array_map( 'intval', $state['exhausted'] ?? array() );
		$used      = array_map( 'intval', array_column( Sessions::items( (int) $session['id'] ), 'question_id' ) );
		for ( $tries = 0; $tries < 20; $tries++ ) {
			$askable = array_diff( array_map( 'intval', array_keys( $state['askable'] ) ), $exhausted );
			$skill   = PlacementModel::next( $graph, $estimate, $askable, $asked, $state['grade'] );
			if ( ! $skill ) {
				break;
			}
			$question = self::question( self::owner( (int) $session['student_id'], (int) $session['guest_id'] ), (array) $state['askable'][ $skill ], $used );
			if ( $question ) {
				return Sessions::add_item( (int) $session['id'], (int) $question['version_id'], count( $used ) + 1, null, array( 'placement_skill' => $skill ) );
			}
			$exhausted[]                      = $skill;
			$policy                           = self::policy( $session );
			$policy['placement']['exhausted'] = $exhausted;
			$wpdb->update( Schema::table( 'practice_sessions' ), array( 'policy' => wp_json_encode( $policy ) ), array( 'id' => (int) $session['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The practice session table.
			$session['policy'] = $policy;
		}
		return self::finish( $session, 'settled' );
	}

	/**
	 * What the runner shows for a placement session: progress, and the placement once finished.
	 *
	 * @param array $session Session row.
	 * @return array subject, grade, limit and, when complete, result.
	 */
	public static function view( array $session ) {
		$state = self::state( $session );
		$view  = array(
			'subject' => $state['subject'],
			'grade'   => $state['grade'],
			'result'  => null,
		);
		if ( 'complete' === $session['status'] && ! empty( $state['result'] ) ) {
			$view['result'] = self::describe( $state, $session );
		}
		return $view;
	}

	/**
	 * The latest finished placement in each subject a learner has taken.
	 *
	 * @param int $student_id Learner.
	 * @return array[] Each: subject, uuid, completed_at, and the result (see describe()).
	 */
	public static function results( $student_id ) {
		global $wpdb;
		if ( ! (int) $student_id || ! Schema::ready() ) {
			return array();
		}
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE student_id=%d AND mode=%s AND status='complete' ORDER BY id DESC LIMIT 50", Schema::table( 'practice_sessions' ), (int) $student_id, self::MODE ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh read of the learner's own tests.
		$results = array();
		foreach ( $rows as $row ) {
			$row['policy'] = json_decode( $row['policy'], true ) ? json_decode( $row['policy'], true ) : array();
			$state         = self::state( $row );
			if ( empty( $state['result'] ) || isset( $results[ $state['subject'] ] ) ) {
				continue;
			}
			$results[ $state['subject'] ] = array(
				'subject'      => $state['subject'],
				'uuid'         => $row['uuid'],
				'completed_at' => $row['completed_at'],
			) + self::describe( $state, $row );
		}
		return array_values( $results );
	}

	/**
	 * What the learner's latest placement tests say, for guidance: the skills placed as known and
	 * the skills to start with. Neither is a level.
	 *
	 * @param int    $student_id Learner.
	 * @param string $subject    One subject, or empty for every subject.
	 * @return array{known:int[],start:int[]}
	 */
	public static function assumed( $student_id, $subject = '' ) {
		$assumed = array(
			'known' => array(),
			'start' => array(),
		);
		foreach ( self::results( $student_id ) as $result ) {
			if ( '' !== (string) $subject && $result['subject'] !== $subject ) {
				continue;
			}
			$assumed['known'] = array_merge( $assumed['known'], $result['known_ids'] );
			$assumed['start'] = array_merge( $assumed['start'], array_column( $result['start'], 'id' ) );
		}
		return $assumed;
	}

	/**
	 * Subjects a placement test can be taken in: those with placed shared skills.
	 *
	 * @return string[]
	 */
	public static function subjects() {
		$subjects = array();
		foreach ( SkillMap::subjects() as $entry ) {
			if ( $entry['skills'] > 0 ) {
				$subjects[] = $entry['subject'];
			}
		}
		return $subjects;
	}

	/**
	 * Where a learner takes a subject's placement test.
	 *
	 * @param string $subject Subject.
	 * @return string
	 */
	public static function url( $subject ) {
		return add_query_arg( 'ohmylms_placement', rawurlencode( (string) $subject ), home_url( '/' ) );
	}

	/**
	 * Finish: store the placement with the session, then close it.
	 *
	 * @param array  $session Session row.
	 * @param string $reason  limit or settled.
	 * @return null
	 */
	private static function finish( array $session, $reason ) {
		global $wpdb;
		$policy       = self::policy( $session );
		$state        = self::state( $session );
		$graph        = self::skills( $state['graph'] );
		$observations = self::observations( Sessions::items( (int) $session['id'] ) );
		$estimate     = PlacementModel::estimate( $graph, $state['grade'], $observations );
		$result       = PlacementModel::result( $graph, $estimate );

		$result['asked']               = count( $observations );
		$result['right']               = count( array_keys( array_column( $observations, 'outcome' ), 'right', true ) );
		$policy['placement']['result'] = $result;
		$wpdb->update( Schema::table( 'practice_sessions' ), array( 'policy' => wp_json_encode( $policy ) ), array( 'id' => (int) $session['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The practice session table.
		$session['policy'] = $policy;
		Sessions::complete( $session, $reason );
		do_action( 'ohmylms_placement_completed', $session['uuid'], (int) $session['student_id'], $state['subject'], $result );
		return null;
	}

	/**
	 * The placement for display: per domain a working grade, and where to start.
	 *
	 * @param array $state   Placement state with result.
	 * @param array $session Session row.
	 * @return array known_ids, known, total, asked, right, domains (domain, label, known, total, grade, grade_label), start (id, name, practice_id, practice_url), session.
	 */
	private static function describe( array $state, array $session ) {
		$result  = $state['result'];
		$labels  = Comparison::domains( $state['subject'] ?? '' );
		$domains = array();
		foreach ( $result['domains'] as $domain ) {
			$domains[] = $domain + array(
				'label'       => $labels[ $domain['domain'] ] ?? ucfirst( $domain['domain'] ),
				'grade_label' => $domain['grade'] ? Comparison::grade_label( $domain['grade'], $state['subject'] ?? '' ) : '',
			);
		}
		$start = array();
		foreach ( $result['start'] as $skill ) {
			$term = get_term( (int) $skill, Taxonomy::NAME );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$terms   = (array) ( $state['askable'][ $skill ] ?? array() );
			$start[] = array(
				'id'           => (int) $skill,
				'name'         => Taxonomy::plain( $term->name ),
				'practice_id'  => $terms ? (int) $terms[0] : 0,
				'practice_url' => $terms ? add_query_arg( 'ohmylms_practice', (int) $terms[0], home_url( '/' ) ) : '',
			);
		}
		return array(
			'known_ids' => array_map( 'intval', $result['known'] ),
			'known'     => count( $result['known'] ),
			'total'     => count( $result['known'] ) + count( $result['unknown'] ) + count( $result['unsure'] ),
			'asked'     => (int) ( $result['asked'] ?? 0 ),
			'right'     => (int) ( $result['right'] ?? 0 ),
			'domains'   => $domains,
			'start'     => $start,
			'session'   => $session['uuid'],
		);
	}

	/**
	 * A subject's shared skills in the compact form kept with the test: id => [domain, from, to, prerequisites].
	 *
	 * @param string $subject Subject.
	 * @return array<int,array>
	 */
	private static function graph( $subject ) {
		if ( '' === $subject ) {
			return array();
		}
		$map = SkillMap::map( $subject );
		if ( is_wp_error( $map ) ) {
			return array();
		}
		$ids   = array_flip( array_column( $map['skills'], 'id' ) );
		$graph = array();
		foreach ( $map['skills'] as $skill ) {
			$graph[ (int) $skill['id'] ] = array(
				(string) $skill['domain'],
				(int) $skill['grade_from'],
				(int) $skill['grade_to'],
				array_values(
					array_filter(
						array_map( 'intval', $skill['prerequisites'] ),
						static function ( $id ) use ( $ids ) {
							return isset( $ids[ $id ] );
						}
					)
				),
			);
		}
		return $graph;
	}

	/**
	 * The compact graph as model skills.
	 *
	 * @param array $graph Compact graph.
	 * @return array<int,array>
	 */
	private static function skills( array $graph ) {
		$skills = array();
		foreach ( $graph as $id => $entry ) {
			$skills[ (int) $id ] = array(
				'domain'        => (string) $entry[0],
				'grade_from'    => (int) $entry[1],
				'grade_to'      => (int) $entry[2],
				'prerequisites' => array_map( 'intval', (array) $entry[3] ),
			);
		}
		return $skills;
	}

	/**
	 * For each shared skill, the terms with questions the learner may see: the skill itself, then
	 * objectives mapped to it as equivalent.
	 *
	 * @param array $owner Learner or guest, as practice access reads it.
	 * @param array $graph Compact graph.
	 * @return array<int,int[]> Skill => terms, only skills with at least one.
	 */
	private static function askable( array $owner, array $graph ) {
		$skills = array_map( 'intval', array_keys( $graph ) );
		if ( ! $skills ) {
			return array();
		}
		$terms = array();
		foreach ( $skills as $skill ) {
			$terms[ $skill ] = array( $skill );
		}
		foreach ( array_chunk( $skills, 500 ) as $chunk ) {
			foreach ( SkillMappings::all( $chunk ) as $mapping ) {
				$shared = (int) $mapping['shared_term_id'];
				if ( 'equivalent' === $mapping['relation'] && isset( $terms[ $shared ] ) ) {
					$terms[ $shared ][] = (int) $mapping['specific_term_id'];
				}
			}
		}
		$pools   = Selector::pools( array_merge( ...array_values( $terms ) ) );
		$askable = array();
		foreach ( $terms as $skill => $list ) {
			foreach ( array_unique( $list ) as $term ) {
				if ( ! empty( $pools[ $term ] ) && PracticeAccessPolicy::pool( $pools[ $term ], $owner, $term ) ) {
					$askable[ $skill ][] = $term;
				}
			}
		}
		return $askable;
	}

	/**
	 * A question for a skill the learner has not seen in this test, standard difficulty first.
	 *
	 * @param array $owner Learner or guest.
	 * @param int[] $terms Terms with questions for the skill.
	 * @param int[] $used  Questions already used in the test.
	 * @return array|null Pool row.
	 */
	private static function question( array $owner, array $terms, array $used ) {
		$pools = Selector::pools( $terms );
		$rows  = array();
		foreach ( $terms as $term ) {
			foreach ( PracticeAccessPolicy::pool( $pools[ (int) $term ] ?? array(), $owner, (int) $term ) as $row ) {
				if ( ! in_array( (int) $row['question_id'], $used, true ) ) {
					$rows[] = $row;
				}
			}
		}
		if ( ! $rows ) {
			return null;
		}
		$standard = array_values(
			array_filter(
				$rows,
				static function ( $row ) {
					return in_array( (string) $row['difficulty'], array( '', 'standard' ), true );
				}
			)
		);
		$choices  = $standard ? $standard : $rows;
		return $choices[ random_int( 0, count( $choices ) - 1 ) ];
	}

	/**
	 * The owner array practice access expects.
	 *
	 * @param int $student_id Learner, or 0.
	 * @param int $guest_id   Guest, or 0.
	 * @return array
	 */
	private static function owner( $student_id, $guest_id ) {
		return $student_id ? array( 'student_id' => (int) $student_id ) : array( 'guest_id' => (int) $guest_id );
	}

	/**
	 * The answers so far, in order: skill and outcome.
	 *
	 * @param array[] $items Session items.
	 * @return array[]
	 */
	private static function observations( array $items ) {
		$observations = array();
		foreach ( $items as $item ) {
			if ( null === $item['answered_at'] || empty( $item['display']['placement_skill'] ) ) {
				continue;
			}
			$outcome = 'wrong';
			if ( ! empty( $item['display']['skipped'] ) ) {
				$outcome = 'unknown';
			} elseif ( 1 === (int) $item['correct'] ) {
				$outcome = 'right';
			}
			$observations[] = array(
				'skill'   => (int) $item['display']['placement_skill'],
				'outcome' => $outcome,
			);
		}
		return $observations;
	}

	/**
	 * The session's policy as an array.
	 *
	 * @param array $session Session row.
	 * @return array
	 */
	private static function policy( array $session ) {
		if ( is_array( $session['policy'] ) ) {
			return $session['policy'];
		}
		$policy = json_decode( (string) $session['policy'], true );
		return is_array( $policy ) ? $policy : array();
	}

	/**
	 * The placement part of the policy, with defaults.
	 *
	 * @param array $session Session row.
	 * @return array subject, grade, graph, askable, exhausted, result.
	 */
	private static function state( array $session ) {
		$state = (array) ( self::policy( $session )['placement'] ?? array() );
		return $state + array(
			'subject'   => '',
			'grade'     => 0,
			'graph'     => array(),
			'askable'   => array(),
			'exhausted' => array(),
			'result'    => null,
		);
	}
}
