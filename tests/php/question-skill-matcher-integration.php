<?php
/**
 * AI skill suggestions for questions, with a stand-in AI provider: nothing is sent anywhere and
 * every fixture is rolled back.
 *
 * @package OhMyLMS
 */

$matcher_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $matcher_site || ! is_file( $matcher_site . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set OHMYLMS_TEST_SITE_PATH to a local WordPress installation.' );
}
require $matcher_site . '/wp-load.php';

use OhMyLMS\AI\Providers\Provider;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\SkillMap;
use OhMyLMS\Skills\QuestionSkillMatcher;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Utility\Transaction;

$GLOBALS['matcher_checks'] = 0;

/**
 * Assert a contract.
 *
 * @param bool   $condition Expected result.
 * @param string $message   Failure description.
 * @throws RuntimeException When the contract fails.
 */
function matcher_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$GLOBALS['matcher_checks'];
}

/** A provider that answers with a reply the test sets, and records the prompt. */
final class Matcher_Test_Provider implements Provider {
	/**
	 * Reply text the next call returns.
	 *
	 * @var string
	 */
	public static $reply = '';

	/**
	 * Prompts sent.
	 *
	 * @var array[]
	 */
	public static $sent = array();

	/**
	 * Build the request; it never leaves the process (pre_http_request answers it).
	 *
	 * @param array  $settings Settings.
	 * @param string $key      Key.
	 * @param array  $request  Prompt.
	 * @return array
	 */
	public function request( array $settings, $key, array $request ) {
		self::$sent[] = $request;
		return array(
			'url'     => 'https://ai.invalid/complete',
			'headers' => array(),
			'body'    => array(),
		);
	}

	/**
	 * The set reply.
	 *
	 * @param array $decoded Decoded body.
	 * @param int   $status  Status.
	 * @return array
	 */
	public function parse( array $decoded, $status ) {
		return array(
			'text'          => self::$reply,
			'input_tokens'  => 0,
			'output_tokens' => 0,
		);
	}
}

/**
 * A question with a statement and options, and optionally a primary skill.
 *
 * @param string $name    Statement.
 * @param array  $options Answer texts.
 * @param int    $skill   Primary skill, or 0.
 * @return int Question ID.
 */
function matcher_question( $name, array $options, $skill = 0 ) {
	$written = DraftWriter::save(
		array(
			'name'        => $name,
			'description' => '',
			'settings'    => array( 'type' => 'single-choice' ),
			'skills'      => $skill ? array(
				'p1' => array(
					'primary'    => $skill,
					'supporting' => array(),
				),
			) : array(),
			'questions'   => array_map(
				static function ( $answer, $index ) {
					return array(
						'answer'       => $answer,
						'is_correct'   => 0 === $index,
						'order_number' => $index + 1,
					);
				},
				$options,
				array_keys( $options )
			),
		)
	);
	matcher_check( ! is_wp_error( $written ), 'Question fixture failed: ' . ( is_wp_error( $written ) ? $written->get_error_message() : '' ) );
	return (int) $written['id'];
}

try {
	Transaction::run(
		static function () {
			$admin = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
				)
			)[0];
			wp_set_current_user( $admin->ID );
			// Every AI call goes to the stand-in provider and never reaches the network.
			add_filter(
				'ohmylms_ai_providers',
				static function ( $providers ) {
					$fake = new Matcher_Test_Provider();
					foreach ( array_keys( $providers ) as $name ) {
						$providers[ $name ] = $fake;
					}
					return $providers;
				}
			);
			add_filter(
				'pre_http_request',
				static function ( $pre, $args, $url ) {
					return 'https://ai.invalid/complete' === $url ? array(
						'headers'  => array(),
						'body'     => '{}',
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
						'cookies'  => array(),
					) : $pre;
				},
				10,
				3
			);
			if ( '' === (string) ( \OhMyLMS\AI\Settings::get()['model'] ?? '' ) || '' === \OhMyLMS\AI\Settings::api_key() ) {
				matcher_check( false, 'The AI client needs a provider, model and key configured on this site to run this test.' );
			}

			// A small curriculum: a syllabus with two skills, and one skill outside it.
			$syllabus = Items::create(
				array(
					'name'      => 'Matcher syllabus ' . wp_generate_password( 4, false ),
					'item_type' => 'syllabus',
				)
			);
			matcher_check( ! is_wp_error( $syllabus ), 'Syllabus fixture failed.' );
			$syllabus = (int) ( is_array( $syllabus ) ? $syllabus['id'] : $syllabus );
			$group    = Syllabus::add_group( $syllabus, array( 'name' => 'Number' ) );
			matcher_check( ! is_wp_error( $group ), 'Group fixture failed.' );
			$group = (int) ( is_array( $group ) ? $group['id'] : $group );
			$term  = static function ( $name ) {
				$created = wp_insert_term( $name . ' ' . wp_generate_password( 4, false ), Taxonomy::NAME );
				matcher_check( ! is_wp_error( $created ), 'Skill fixture failed.' );
				return (int) $created['term_id'];
			};
			$fractions = $term( 'Add fractions with unlike denominators' );
			$percent   = $term( 'Find a percentage of an amount' );
			$outside   = $term( 'Solve quadratic equations by factorising' );
			matcher_check( true === Syllabus::add_skill( $syllabus, $group, $fractions ) && true === Syllabus::add_skill( $syllabus, $group, $percent ), 'Placing skills failed.' );

			$library = QuestionSkillMatcher::library( $syllabus );
			matcher_check( array( $fractions, $percent ) === array_keys( $library ) || array( $percent, $fractions ) === array_keys( $library ), 'The library is not the syllabus’s skills.' );
			matcher_check( in_array( $syllabus, array_column( QuestionSkillMatcher::curricula(), 'id' ), true ), 'The syllabus is not offered as a curriculum.' );

			$add      = matcher_question( 'Add the fractions 1/3 + 1/4', array( '7/12', '2/7' ) );
			$pct      = matcher_question( 'Find a percentage of an amount: what is 20% of 50?', array( '10', '5' ) );
			$mapped   = matcher_question( 'Add the fractions 2/5 + 1/2', array( '9/10', '3/7' ), $percent );
			$offtopic = matcher_question( 'Zebra xylophone quibble', array( 'Yes', 'No' ) );

			// The reply picks a candidate for one question, an outside skill for another, and nothing for the third.
			Matcher_Test_Provider::$reply = wp_json_encode(
				array(
					'matches' => array(
						array(
							'question'   => $add,
							'skill'      => $fractions,
							'confidence' => 0.9,
							'reason'     => 'Adds fractions.',
						),
						array(
							'question'   => $pct,
							'skill'      => $outside,
							'confidence' => 0.8,
							'reason'     => 'Wrong pick.',
						),
						array(
							'question'   => 999999999,
							'skill'      => $fractions,
							'confidence' => 1,
							'reason'     => 'Not asked.',
						),
					),
				)
			);
			// Shortlist mode: each question gets only the skills that share words with it.
			add_filter( 'ohmylms_question_skill_shared_limit', '__return_zero' );
			$result = QuestionSkillMatcher::suggest( array( $add, $pct, $mapped, $offtopic ), $syllabus );
			remove_filter( 'ohmylms_question_skill_shared_limit', '__return_zero' );
			matcher_check( ! is_wp_error( $result ), 'Suggestion failed: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
			$rows = array_column( $result['rows'], null, 'question' );
			matcher_check( 4 === count( $rows ), 'Every question needs a row.' );
			matcher_check( 'suggested' === $rows[ $add ]['status'] && $fractions === $rows[ $add ]['skill']['id'] && 0.9 === $rows[ $add ]['confidence'], 'The matching skill was not suggested.' );
			matcher_check( 'no-match' === $rows[ $pct ]['status'] && null === $rows[ $pct ]['skill'], 'A skill outside the curriculum was accepted from the AI.' );
			matcher_check( 'has-skill' === $rows[ $mapped ]['status'] && $percent === $rows[ $mapped ]['current']['id'], 'A question that has a skill was not left alone.' );
			matcher_check( 'no-match' === $rows[ $offtopic ]['status'], 'A question unrelated to the curriculum got a suggestion.' );
			matcher_check( ! isset( $rows[999999999] ), 'A question that was not asked appeared.' );

			// One AI call, and only the questions that needed one were in it, each with its own candidates.
			matcher_check( 1 === count( Matcher_Test_Provider::$sent ), 'Expected one AI call per batch.' );
			$prompt = Matcher_Test_Provider::$sent[0]['user'];
			matcher_check( false !== strpos( $prompt, 'question id="' . $add . '"' ) && false !== strpos( $prompt, 'question id="' . $pct . '"' ), 'The prompt is missing a question.' );
			matcher_check( false === strpos( $prompt, 'question id="' . $mapped . '"' ) && false === strpos( $prompt, 'question id="' . $offtopic . '"' ), 'The prompt includes a question that did not need AI.' );
			matcher_check( false === strpos( $prompt, (string) $outside ), 'A skill outside the curriculum was offered.' );

			// Maths notation is read as words for the shortlist.
			$words = QuestionSkillMatcher::words( 'What is 1/2 + 1/4? And 20% of 3.5?' );
			matcher_check( false !== strpos( $words, 'fraction' ) && false !== strpos( $words, 'add' ) && false !== strpos( $words, 'percent' ) && false !== strpos( $words, 'decimal' ), 'Maths notation was not turned into words.' );

			// A small curriculum is sent whole, so a question with no shared words still reaches the AI.
			$bare                         = matcher_question( 'What is 1/2 + 1/4?', array( '3/4', '2/6' ) );
			Matcher_Test_Provider::$sent  = array();
			Matcher_Test_Provider::$reply = wp_json_encode(
				array(
					'matches' => array(
						array(
							'question'   => $bare,
							'skill'      => $fractions,
							'confidence' => 0.95,
							'reason'     => 'Adds fractions.',
						),
					),
				)
			);
			$whole = QuestionSkillMatcher::suggest( array( $bare ), $syllabus );
			matcher_check( true === $whole['meta']['shared'] && 'suggested' === $whole['rows'][0]['status'] && $fractions === $whole['rows'][0]['skill']['id'], 'A small curriculum was not sent whole.' );
			$prompt = Matcher_Test_Provider::$sent[0]['user'];
			matcher_check( false !== strpos( $prompt, '<skills>' ) && false !== strpos( $prompt, ' ' . $percent . ':' ) && false === strpos( $prompt, '<candidates>' ), 'The whole skill list was not in the prompt once.' );
			matcher_check( false === strpos( $prompt, (string) $outside ), 'A skill outside the curriculum was listed.' );

			// Nothing was saved: the questions keep their skills.
			matcher_check( array() === SkillMap::current( $add ) && $percent === SkillMap::current( $mapped )['p1']['primary'], 'Suggesting changed a question.' );

			// Asking for questions that already have a skill includes them.
			Matcher_Test_Provider::$sent  = array();
			Matcher_Test_Provider::$reply = wp_json_encode(
				array(
					'matches' => array(
						array(
							'question'   => $mapped,
							'skill'      => $fractions,
							'confidence' => 0.7,
							'reason'     => 'Adds fractions.',
						),
					),
				)
			);
			$again = QuestionSkillMatcher::suggest( array( $mapped ), $syllabus, true );
			matcher_check( 'suggested' === $again['rows'][0]['status'] && $fractions === $again['rows'][0]['skill']['id'] && $percent === $again['rows'][0]['current']['id'], 'A re-suggestion for a mapped question failed.' );

			// A curriculum with no skills answers clearly, without an AI call.
			$empty = Items::create(
				array(
					'name'      => 'Empty syllabus ' . wp_generate_password( 4, false ),
					'item_type' => 'syllabus',
				)
			);
			$empty = (int) ( is_array( $empty ) ? $empty['id'] : $empty );
			Matcher_Test_Provider::$sent = array();
			$none                        = QuestionSkillMatcher::suggest( array( $add ), $empty );
			matcher_check( is_wp_error( $none ) && 'ohmylms_no_skills' === $none->get_error_code() && ! Matcher_Test_Provider::$sent, 'An empty curriculum was not refused.' );

			// Someone who cannot edit questions is refused by the route; others' questions are skipped.
			$author = wp_insert_user(
				array(
					'user_login' => 'matcher-' . wp_generate_password( 8, false ),
					'user_pass'  => wp_generate_password( 24 ),
					'role'       => 'subscriber',
				)
			);
			wp_set_current_user( $author );
			$request = new WP_REST_Request( 'POST', '/ohmylms/v1/question-skill-suggestions' );
			$request->set_body_params(
				array(
					'question_ids' => array( $add ),
					'item_id'      => $syllabus,
				)
			);
			matcher_check( in_array( rest_do_request( $request )->get_status(), array( 401, 403 ), true ), 'A subscriber could ask for suggestions.' );
			$listing = rest_do_request( new WP_REST_Request( 'GET', '/ohmylms/v1/question-skill-suggestions/curricula' ) );
			matcher_check( in_array( $listing->get_status(), array( 401, 403 ), true ), 'A subscriber could list curricula.' );
			$unavailable = QuestionSkillMatcher::suggest( array( $add ), $syllabus );
			matcher_check( 'unavailable' === $unavailable['rows'][0]['status'], 'A question the user cannot edit was read.' );

			wp_set_current_user( $admin->ID );
			$request = new WP_REST_Request( 'POST', '/ohmylms/v1/question-skill-suggestions' );
			$request->set_body_params(
				array(
					'question_ids' => range( 1, QuestionSkillMatcher::MAX_QUESTIONS + 1 ),
					'item_id'      => $syllabus,
				)
			);
			matcher_check( 400 === rest_do_request( $request )->get_status(), 'More questions than one batch were accepted.' );

			throw new RuntimeException( 'OHMYLMS_MATCHER_TEST_SUCCESS' );
		}
	);
} catch ( Throwable $failure ) {
	if ( 'OHMYLMS_MATCHER_TEST_SUCCESS' === $failure->getMessage() ) {
		echo 'Question skill matcher: ' . (int) $GLOBALS['matcher_checks'] . " checks passed; fixtures rolled back.\n";
	} else {
		echo esc_html( $failure->getMessage() ) . "\n";
		exit( 1 );
	}
}
