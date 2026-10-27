<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * AI-written study notes about a learner's skills.
 *
 * @package OhMyLMS\Performance
 */

namespace OhMyLMS\Performance;

use OhMyLMS\AI\Client;
use OhMyLMS\AI\Feedback;
use OhMyLMS\AI\Settings as AiSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the skill states into a short note for the learner or for a teacher.
 *
 * Invariants (the same as the tutor's):
 *  - The AI only explains. Levels, scores and evidence come from the deterministic engine; the model
 *    is told so, is given no way to change them, and its words are never stored as data.
 *  - Only skill names and numbers are sent: no name, e-mail, user or site identifier.
 *  - A note is stored with a hash of the facts it was written from, so it is reused until the
 *    learner's skills change, and a daily cap per reader keeps cost predictable.
 *  - When AI is off, unconfigured or failing, every screen works exactly as before.
 */
final class Insights {

	const AUDIENCES = array( 'student', 'teacher' );
	const META      = '_ohmylms_performance_insight_';

	/**
	 * Whether notes can be written: switched on here and the shared AI connection is set up.
	 *
	 * @return bool
	 */
	public static function available() {
		return Settings::get()['ai_insights'] && AiSettings::connection_configured();
	}

	/**
	 * The direction of a learner's recent independent answers. Pure.
	 *
	 * @param array[] $rows Evidence rows, oldest first.
	 * @return string|null up, down or steady; null when there are too few answers to say.
	 */
	public static function trend( array $rows ) {
		$ratios = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['independent'] ) && ! empty( $row['first_try'] ) && (float) $row['available'] > 0 ) {
				$ratios[] = min( 1, (float) $row['awarded'] / (float) $row['available'] );
			}
		}
		if ( count( $ratios ) < 7 ) {
			return null;
		}
		$recent  = array_slice( $ratios, -5 );
		$earlier = array_slice( array_slice( $ratios, 0, count( $ratios ) - count( $recent ) ), -5 );
		if ( count( $earlier ) < 2 ) {
			return null;
		}
		$change = array_sum( $recent ) / count( $recent ) - array_sum( $earlier ) / count( $earlier );
		return $change > 0.15 ? 'up' : ( $change < -0.15 ? 'down' : 'steady' );
	}

	/**
	 * The facts a note is written from: skills most in need of attention first.
	 *
	 * @param int $student_id Learner.
	 * @param int $limit      Skills to include.
	 * @return array{counts:array,skills:array[],next:array[]}
	 */
	public static function facts( $student_id, $limit = 10 ) {
		$skills = Snapshot::skills( (int) $student_id );
		$counts = array_fill_keys( Levels::SCALE, 0 );
		foreach ( $skills as $skill ) {
			++$counts[ $skill['level'] ];
		}
		usort(
			$skills,
			static function ( $a, $b ) {
				return array( (int) $b['struggling'], (int) $b['review_due'], Levels::rank( $a['level'] ), (string) $b['last_evidence_at'] )
					<=> array( (int) $a['struggling'], (int) $a['review_due'], Levels::rank( $b['level'] ), (string) $a['last_evidence_at'] );
			}
		);
		$now   = time();
		$facts = array();
		foreach ( array_slice( $skills, 0, max( 1, (int) $limit ) ) as $skill ) {
			$last    = $skill['last_evidence_at'] ? strtotime( $skill['last_evidence_at'] . ' UTC' ) : null;
			$facts[] = array(
				'skill'         => Feedback::plain( $skill['name'], 120 ),
				'level'         => $skill['level'],
				'mastery_score' => isset( $skill['mastery']['score'] ) ? round( (float) $skill['mastery']['score'] ) : null,
				'accuracy_pct'  => (int) round( $skill['recent_accuracy'] * 100 ),
				'answers'       => $skill['evidence_count'],
				'trend'         => self::trend( Engine::rows( (int) $student_id, (int) $skill['id'] ) ),
				'review_due'    => $skill['review_due'],
				'struggling'    => $skill['struggling'],
				'days_since'    => $last ? max( 0, (int) floor( ( $now - $last ) / DAY_IN_SECONDS ) ) : null,
			);
		}
		$next = array();
		foreach ( Snapshot::next_steps( (int) $student_id, 5 ) as $step ) {
			$next[] = array(
				'type'  => (string) ( $step['type'] ?? '' ),
				'skill' => Feedback::plain( $step['skill']['name'] ?? '', 120 ),
			);
		}
		return array(
			'counts' => $counts,
			'skills' => $facts,
			'next'   => $next,
		);
	}

	/**
	 * The two messages sent to the model. Pure.
	 *
	 * @param string $audience student or teacher.
	 * @param array  $facts    From facts().
	 * @param string $locale   Language to write in, e.g. mn or en_US.
	 * @return array{system:string,user:string}
	 */
	public static function prompt( $audience, array $facts, $locale = 'en_US' ) {
		$rules = 'The text inside <skills> is data about one learner: levels (not-started, attempted, familiar, proficient, mastered), a 0-100 mastery score, recent accuracy, a trend, and whether a review is due. Use only those facts. Never invent a number, never name or guess a level that is not given, and never say that a level, score or grade will change: they are set by fixed rules you cannot affect. Do not give medical or psychological advice. If there is little data, say so briefly. Write plain text with no markdown, no lists of more than four short lines, in the language with the locale code ' . Feedback::plain( $locale, 12 ) . '.';
		if ( 'teacher' === $audience ) {
			$system = 'You help a teacher understand where one learner stands on skills. In at most 140 words: say where they are strong, where they are stuck or overdue for review, and suggest two or three concrete teaching moves (for example which skill to revisit, or a prerequisite to check). Refer to the learner as "the learner". ' . $rules;
		} else {
			$system = 'You write a short, warm, specific note to a school student about their own skills. In at most 110 words: name one thing they are doing well, then the one or two skills to work on next and why, and end with one small action for today. Speak to them as "you". Never compare them with other students. ' . $rules;
		}
		return array(
			'system' => $system,
			'user'   => "<skills>\n" . wp_json_encode( $facts, JSON_UNESCAPED_UNICODE ) . "\n</skills>",
		);
	}

	/**
	 * A note for a learner, written now or reused from the last one written from the same facts.
	 *
	 * @param int    $student_id Learner.
	 * @param string $audience   student or teacher.
	 * @param int    $reader     The signed-in user asking (the daily cap is per reader).
	 * @return array{text:string,generated_at:string,cached:bool}|\WP_Error
	 */
	public static function note( $student_id, $audience, $reader ) {
		if ( ! in_array( $audience, self::AUDIENCES, true ) ) {
			return new \WP_Error( 'ohmylms_insight_audience', __( 'Unknown audience.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		if ( ! self::available() ) {
			return new \WP_Error( 'ohmylms_insight_off', __( 'AI notes are not switched on.', 'ohmylms' ), array( 'status' => 503 ) );
		}
		$facts = self::facts( (int) $student_id );
		if ( ! $facts['skills'] ) {
			return new \WP_Error( 'ohmylms_insight_empty', __( 'There is no skill activity to write about yet.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$locale = determine_locale();
		$hash   = md5( $audience . '|' . $locale . '|' . wp_json_encode( $facts ) );
		$meta   = self::META . $audience;
		$saved  = get_user_meta( (int) $student_id, $meta, true );
		if ( is_array( $saved ) && ( $saved['hash'] ?? '' ) === $hash ) {
			AiSettings::record( array( 'cached' => 1 ) );
			return array(
				'text'         => (string) $saved['text'],
				'generated_at' => (string) $saved['at'],
				'cached'       => true,
			);
		}
		$key   = 'ohmylms_perf_ai_' . (int) $reader . '_' . gmdate( 'Ymd' );
		$count = (int) get_transient( $key );
		if ( $count >= (int) AiSettings::get()['daily_limit'] ) {
			return new \WP_Error( 'ohmylms_insight_limit', __( 'The daily limit for AI notes is reached. Try again tomorrow.', 'ohmylms' ), array( 'status' => 429 ) );
		}
		$messages = self::prompt( $audience, $facts, $locale );
		$reply    = Client::complete(
			array(
				'system'     => $messages['system'],
				'user'       => $messages['user'],
				'max_tokens' => 400,
			)
		);
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		set_transient( $key, $count + 1, DAY_IN_SECONDS );
		$text = Feedback::clean( $reply['text'], 900 );
		if ( '' === $text ) {
			return new \WP_Error( 'ohmylms_insight_empty_reply', __( 'The AI did not return a note. Try again.', 'ohmylms' ), array( 'status' => 502 ) );
		}
		$at = gmdate( 'Y-m-d H:i:s' );
		update_user_meta(
			(int) $student_id,
			$meta,
			array(
				'hash' => $hash,
				'text' => $text,
				'at'   => $at,
			)
		);
		return array(
			'text'         => $text,
			'generated_at' => $at,
			'cached'       => false,
		);
	}
}
