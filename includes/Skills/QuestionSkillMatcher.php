<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * AI suggestions of the skill each question assesses, from a chosen curriculum.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

use OhMyLMS\AI\Client;
use OhMyLMS\Curriculum\AiAnalysisModel;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\SkillMap;
use OhMyLMS\QuestionBank\VersionPublisher;

defined( 'ABSPATH' ) || exit;

/**
 * Suggests a primary skill for each question from the skills of one curriculum item (a subject,
 * syllabus or topic and everything under it), or from every skill.
 *
 * Each question's likely skills are first ranked by keywords (the curriculum analysis' ranking), and
 * the AI may only choose one of the question's own candidates, or none. Nothing is saved here: the
 * author reviews the suggestions and the editor saves the accepted ones through the question's
 * normal update route, so permissions, versions and conflicts work as for a manual change.
 */
final class QuestionSkillMatcher {

	/** Questions per request: one AI call each. */
	const MAX_QUESTIONS = 10;

	/** Candidate skills offered for one question. */
	const MAX_CANDIDATES = 12;

	/** Characters of question text sent for one question. */
	const MAX_TEXT = 1200;

	/** Skills read when no curriculum is chosen. */
	const MAX_LIBRARY = 5000;

	/** Recorded with each run, so replies can be traced to the prompt that produced them. */
	const PROMPT_VERSION = 1;

	/**
	 * Curriculum items an author can match against: subjects, syllabuses and their first level, each
	 * with how many skills are placed under it. Items with no skills are left out.
	 *
	 * @return array[] id, name, type, depth, skills.
	 */
	public static function curricula() {
		$items    = Items::all();
		$by_id    = array_column( $items, null, 'id' );
		$children = array();
		foreach ( $items as $item ) {
			$children[ (int) $item['parent_id'] ][] = (int) $item['id'];
		}
		$result = array();
		$walk   = static function ( $parent, $depth ) use ( &$walk, &$result, $children, $by_id ) {
			foreach ( $children[ $parent ] ?? array() as $id ) {
				$count = count( Syllabus::skill_ids( Items::with_descendants( $id ) ) );
				if ( $count ) {
					$result[] = array(
						'id'     => $id,
						'name'   => (string) $by_id[ $id ]['name'],
						'type'   => (string) $by_id[ $id ]['item_type'],
						'depth'  => $depth,
						'skills' => $count,
					);
				}
				if ( $depth < 1 ) {
					$walk( $id, $depth + 1 );
				}
			}
		};
		$walk( 0, 0 );
		return $result;
	}

	/**
	 * Skills to match against: those placed under a curriculum item, or every skill for 0.
	 *
	 * @param int $item_id Curriculum item, or 0 for every skill.
	 * @return array<int,array> Term ID => name, description, topic, domain, grade_from, grade_to.
	 */
	public static function library( $item_id ) {
		$item_id = (int) $item_id;
		if ( $item_id ) {
			$ids = Syllabus::skill_ids( Items::with_descendants( $item_id ) );
			if ( ! $ids ) {
				return array();
			}
			$terms = get_terms(
				array(
					'taxonomy'   => Taxonomy::NAME,
					'hide_empty' => false,
					'include'    => $ids,
				)
			);
		} else {
			$terms = get_terms(
				array(
					'taxonomy'   => Taxonomy::NAME,
					'hide_empty' => false,
					'number'     => self::MAX_LIBRARY,
				)
			);
		}
		$library = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$id = (int) $term->term_id;
			if ( Provenance::archived( $id ) || 'rejected' === get_term_meta( $id, Provenance::REVIEW_META, true ) ) {
				continue;
			}
			$library[ $id ] = array(
				'name'        => Taxonomy::plain( $term->name ),
				'description' => (string) $term->description,
				'topic'       => (string) get_term_meta( $id, \OhMyLMS\Curriculum\Comparison::TOPIC_META, true ),
				'domain'      => (string) get_term_meta( $id, \OhMyLMS\Curriculum\Comparison::DOMAIN_META, true ),
				'grade_from'  => (int) get_term_meta( $id, \OhMyLMS\Curriculum\Comparison::GRADE_FROM_META, true ),
				'grade_to'    => (int) get_term_meta( $id, \OhMyLMS\Curriculum\Comparison::GRADE_TO_META, true ),
			);
		}
		return $library;
	}

	/**
	 * A question's text as the learner reads it: name, statement and answer options, as plain text.
	 *
	 * @param array $content Question content (VersionPublisher::content()).
	 * @return string Plain text.
	 */
	public static function text( array $content ) {
		$parts = array( $content['title'] ?? '', $content['body'] ?? '' );
		foreach ( array( 'text', 'passage', 'question' ) as $key ) {
			if ( isset( $content['settings'][ $key ] ) && is_string( $content['settings'][ $key ] ) ) {
				$parts[] = $content['settings'][ $key ];
			}
		}
		foreach ( (array) ( $content['options'] ?? array() ) as $option ) {
			$parts[] = (string) ( $option['answer'] ?? '' );
		}
		$text = html_entity_decode( wp_strip_all_tags( implode( "\n", $parts ) ), ENT_QUOTES, 'UTF-8' );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		return 'Untitled' === $text ? '' : $text;
	}

	/**
	 * Candidate skills for one question, best first.
	 *
	 * @param string $text    Question text.
	 * @param array  $library Skills (library()).
	 * @return int[] Term IDs.
	 */
	public static function candidates( $text, array $library ) {
		if ( '' === $text || ! $library ) {
			return array();
		}
		$docs = array();
		foreach ( $library as $id => $skill ) {
			$docs[ $id ] = trim( $skill['name'] . ' ' . $skill['name'] . ' ' . $skill['topic'] . ' ' . $skill['description'] );
		}
		return array_map( 'intval', array_keys( AiAnalysisModel::rank( $text, $docs, self::MAX_CANDIDATES ) ) );
	}

	/**
	 * The prompt for one batch of questions, each with its own candidates.
	 *
	 * @param array[] $questions Each: id, text, candidates (term IDs).
	 * @param array   $library   Skills (library()).
	 * @param array   $profile   Subject profile.
	 * @return array{system: string, user: string}
	 */
	public static function prompt( array $questions, array $library, array $profile ) {
		$system = AiAnalysisModel::persona( $profile ) . ' You read assessment questions and decide which one skill each question assesses: the skill a learner shows by answering it correctly, as on Khan Academy, where every exercise practises one skill. For each question choose at most one skill, and only from that question\'s own candidate list. Choose none when no candidate is what the question assesses; a wrong skill is worse than none. Reply with JSON only: {"matches":[{"question":<question id>,"skill":<skill id or null>,"confidence":<0 to 1>,"reason":"<one short sentence>"}]}, one entry per question.';
		$user   = '';
		foreach ( $questions as $question ) {
			$user .= '<question id="' . (int) $question['id'] . "\">\n" . AiAnalysisModel::clip( $question['text'], self::MAX_TEXT ) . "\n<candidates>\n";
			foreach ( $question['candidates'] as $id ) {
				$skill = $library[ $id ];
				$user .= '- ' . (int) $id . ': ' . AiAnalysisModel::clip( $skill['name'], 160 )
					. ( '' !== $skill['topic'] ? ' | ' . AiAnalysisModel::clip( $skill['topic'], 80 ) : '' )
					. ( '' !== $skill['description'] ? ' | ' . AiAnalysisModel::clip( $skill['description'], 200 ) : '' ) . "\n";
			}
			$user .= "</candidates>\n</question>\n";
		}
		return array(
			'system' => $system,
			'user'   => trim( $user ),
		);
	}

	/**
	 * Checked matches from a reply: a skill must be one of that question's candidates.
	 *
	 * @param string $text       Reply.
	 * @param array  $candidates Question ID => allowed term IDs.
	 * @return array<int,array> Question ID => skill (term ID or 0), confidence (0-1), reason.
	 */
	public static function parse( $text, array $candidates ) {
		$decoded = AiAnalysisModel::json( (string) $text );
		$result  = array();
		foreach ( (array) ( is_array( $decoded ) ? ( $decoded['matches'] ?? array() ) : array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$question = (int) ( $row['question'] ?? 0 );
			if ( ! isset( $candidates[ $question ] ) || isset( $result[ $question ] ) ) {
				continue;
			}
			$skill               = (int) ( $row['skill'] ?? 0 );
			$result[ $question ] = array(
				'skill'      => in_array( $skill, $candidates[ $question ], true ) ? $skill : 0,
				'confidence' => max( 0.0, min( 1.0, (float) ( $row['confidence'] ?? 0 ) ) ),
				'reason'     => AiAnalysisModel::clip( sanitize_text_field( (string) ( $row['reason'] ?? '' ) ), 300 ),
			);
		}
		return $result;
	}

	/**
	 * Suggest a primary skill for each question. Nothing is saved.
	 *
	 * @param int[] $question_ids   Questions, at most MAX_QUESTIONS.
	 * @param int   $item_id        Curriculum item whose skills may be chosen, or 0 for every skill.
	 * @param bool  $include_mapped Also suggest for questions that already have a primary skill.
	 * @return array|\WP_Error Rows (question, title, current, skill, confidence, reason, status) and meta.
	 */
	public static function suggest( array $question_ids, $item_id, $include_mapped = false ) {
		$question_ids = array_slice( array_values( array_unique( array_filter( array_map( 'intval', $question_ids ) ) ) ), 0, self::MAX_QUESTIONS );
		$library      = self::library( $item_id );
		if ( ! $library ) {
			return new \WP_Error( 'ohmylms_no_skills', __( 'This curriculum has no skills yet. Add or generate its skills first.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$rows    = array();
		$batch   = array();
		$allowed = array();
		foreach ( $question_ids as $id ) {
			$row     = array(
				'question'   => $id,
				'title'      => '',
				'current'    => null,
				'skill'      => null,
				'confidence' => 0,
				'reason'     => '',
				'status'     => 'no-match',
			);
			$content = OHMYLMS_QUESTION_CPT === get_post_type( $id ) && AccessPolicy::can_edit_question( $id ) ? VersionPublisher::content( $id ) : null;
			if ( ! $content ) {
				$row['status'] = 'unavailable';
				$rows[ $id ]   = $row;
				continue;
			}
			$text         = self::text( $content );
			$row['title'] = AiAnalysisModel::clip( $text, 140 );
			$primary      = (int) ( SkillMap::current( $id )['p1']['primary'] ?? 0 );
			if ( $primary ) {
				$row['current'] = array(
					'id'   => $primary,
					'name' => Taxonomy::plain( (string) ( get_term( $primary )->name ?? '' ) ),
				);
			}
			if ( $primary && ! $include_mapped ) {
				$row['status'] = 'has-skill';
				$rows[ $id ]   = $row;
				continue;
			}
			$candidates = self::candidates( $text, $library );
			if ( ! $candidates ) {
				$row['reason'] = '' === $text ? __( 'The question has no text to read.', 'ohmylms' ) : __( 'No skill in this curriculum shares words with the question.', 'ohmylms' );
				$rows[ $id ]   = $row;
				continue;
			}
			$batch[]        = array(
				'id'         => $id,
				'text'       => $text,
				'candidates' => $candidates,
			);
			$allowed[ $id ] = $candidates;
			$rows[ $id ]    = $row;
		}
		$meta = array(
			'prompt'  => self::PROMPT_VERSION,
			'library' => count( $library ),
		);
		if ( $batch ) {
			// The subject is the top of the item's path, or the item itself when it is at the top.
			$path    = $item_id ? Items::path( (int) $item_id ) : array();
			$top     = $path ? $path[0] : ( $item_id ? Items::get( (int) $item_id ) : null );
			$profile = SubjectProfiles::get( $top ? (string) $top['name'] : '' );
			$reply   = Client::complete(
				self::prompt( $batch, $library, $profile ) + array(
					'max_tokens' => 1500,
					'long'       => true,
				)
			);
			if ( is_wp_error( $reply ) ) {
				return $reply;
			}
			foreach ( self::parse( (string) $reply['text'], $allowed ) as $id => $match ) {
				$rows[ $id ]['confidence'] = $match['confidence'];
				$rows[ $id ]['reason']     = $match['reason'];
				if ( $match['skill'] ) {
					$rows[ $id ]['skill']  = array(
						'id'   => $match['skill'],
						'name' => $library[ $match['skill'] ]['name'],
					);
					$rows[ $id ]['status'] = 'suggested';
				}
			}
		}
		return array(
			'rows' => array_values( $rows ),
			'meta' => $meta,
		);
	}
}
