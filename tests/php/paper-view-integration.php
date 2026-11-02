<?php
/**
 * Paper View and print page integration test: the learner's server-rendered paper (quiz-form.php with
 * PaperPresentation::sheet() and the `ohmylms/paper` module) and the server-rendered print page
 * (PrintPaper::document()). It also checks that the attempt endpoints accept the answer fields the
 * paper renders, and that grading stays on the server.
 *
 * Runs against the Local site with real tables. Every fixture write happens inside one transaction
 * that is rolled back at the end. Mail and outgoing HTTP are blocked.
 *
 * Usage (set OHMYLMS_PAPER_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/paper-view-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode properties are camelCase.

use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Exams\PrintPaper;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\Utility\Transaction;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_PAPER_TEST' ) ) {
	exit( "Set OHMYLMS_PAPER_TEST=1 to run the paper view tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;
if ( 'local' !== wp_get_environment_type() || ! Schema::ready() || wp_using_ext_object_cache() ) {
	exit( "Requires the Local site, the assessment schema and a request-local object cache.\n" );
}
foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $table_status ) {
	if ( 0 === strpos( $table_status['Name'], $wpdb->prefix ) && 'InnoDB' !== $table_status['Engine'] ) {
		exit( "Requires InnoDB tables so fixture writes can be rolled back.\n" );
	}
}
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
add_filter(
	'pre_http_request',
	static function () {
		return new WP_Error( 'paper_test', 'Network disabled.' );
	},
	PHP_INT_MAX
);

$checks   = 0;
$tag      = 'paper' . strtolower( wp_generate_password( 6, false, false ) );
$warnings = array();
set_error_handler(
	static function ( $severity, $message, $file, $line ) use ( &$warnings ) {
		if ( false !== strpos( str_replace( '\\', '/', $file ), '/OhMyLMS/' ) ) {
			$warnings[] = "$message ($file:$line)";
		}
		return false;
	}
);

/**
 * Assert a condition.
 *
 * @param mixed  $value   Value that must be truthy.
 * @param string $message Failure message.
 * @throws RuntimeException When the condition is falsy.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value || is_wp_error( $value ) ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * Dispatch a REST request as JSON.
 *
 * @param string $method HTTP method.
 * @param string $path   Path below /ohmylms/v1/.
 * @param array  $data   Body.
 * @return WP_REST_Response
 */
function call_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	if ( $data ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Create a temporary user (rolled back with the fixture).
 *
 * @param string $suffix Login suffix.
 * @param string $role   Role.
 * @return int
 */
function make_user( $suffix, $role ) {
	global $tag;
	$id = wp_insert_user(
		array(
			'user_login' => $tag . '_' . $suffix,
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . '_' . $suffix . '@example.invalid',
			'role'       => $role,
		)
	);
	ok( ! is_wp_error( $id ), "temporary $role created" );
	return (int) $id;
}

/**
 * Elements of rendered HTML matching an XPath query.
 *
 * @param string $html  HTML.
 * @param string $query XPath.
 * @return DOMNodeList
 */
function nodes( $html, $query ) {
	$document = new DOMDocument();
	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
	libxml_clear_errors();
	return ( new DOMXPath( $document ) )->query( $query );
}

/**
 * XPath test for a class name.
 *
 * @param string $name Class.
 * @return string Predicate.
 */
function has_class( $name ) {
	return "contains(concat(' ', normalize-space(@class), ' '), ' $name ')";
}

/**
 * Render the quiz template as the current user, as the lesson page does.
 *
 * @param int $quiz Quiz ID.
 * @return string HTML.
 */
function render_quiz( $quiz ) {
	$GLOBALS['post'] = get_post( $quiz );
	setup_postdata( $GLOBALS['post'] );
	ob_start();
	ohmylms_get_template( 'single-lesson/quiz-form.php' );
	return (string) ob_get_clean();
}

try {
	Transaction::run(
		static function () use ( $tag, &$warnings ) {
			global $wpdb;
			$admin   = make_user( 'admin', 'administrator' );
			$student = make_user( 'student', 'subscriber' );
			wp_set_current_user( $admin );
			do_action( 'wp_enqueue_scripts' );

			// A course with one exam in Paper View.
			$course = call_api(
				'POST',
				'courses',
				array(
					'title'  => $tag . ' course',
					'status' => 'publish',
				)
			);
			ok( in_array( $course->get_status(), array( 200, 201 ), true ), 'course created: ' . wp_json_encode( $course->get_data() ) );
			$course_id = (int) $course->get_data()['id'];
			$quiz      = call_api(
				'POST',
				'quiz',
				array(
					'name'   => $tag . ' exam',
					'status' => 'publish',
				)
			);
			$quiz_id   = (int) $quiz->get_data()['id'];
			ok( $quiz_id > 0, 'exam created' );
			$chapter = wp_insert_post(
				array(
					'post_type'   => 'ohmylms-chapter',
					'post_title'  => $tag . ' chapter',
					'post_status' => 'publish',
				)
			);
			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_chapter_relationship',
				array(
					'course_id'    => $course_id,
					'chapter_id'   => $chapter,
					'order_number' => 0,
				)
			);
			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_content_relationship',
				array(
					'chapter_id'   => $chapter,
					'content_id'   => $quiz_id,
					'content_type' => 'quiz',
					'order_number' => 0,
				)
			);
			$saved = call_api(
				'PUT',
				'quiz/' . $quiz_id,
				array(
					'content' => array(
						array(
							'name'         => 'Which planet is red?',
							'order_number' => 1,
							'settings'     => array(
								'type'  => 'single-choice',
								'score' => array(
									'enabled' => true,
									'value'   => 2,
								),
							),
							'questions'    => array(
								array(
									'answer'       => 'Venus',
									'is_correct'   => 0,
									'order_number' => 1,
								),
								array(
									'answer'       => 'Mars',
									'is_correct'   => 1,
									'order_number' => 2,
								),
								array(
									'answer'       => 'Jupiter',
									'is_correct'   => 0,
									'order_number' => 3,
								),
							),
						),
						array(
							'name'         => 'Pick the even numbers',
							'order_number' => 2,
							'settings'     => array(
								'type'  => 'multiple-choice',
								'score' => array(
									'enabled' => true,
									'value'   => 1,
								),
							),
							'questions'    => array(
								array(
									'answer'       => 'Two',
									'is_correct'   => 1,
									'order_number' => 1,
								),
								array(
									'answer'       => 'Three',
									'is_correct'   => 0,
									'order_number' => 2,
								),
								array(
									'answer'       => 'Four',
									'is_correct'   => 1,
									'order_number' => 3,
								),
							),
						),
						array(
							'name'         => 'Explain photosynthesis',
							'order_number' => 3,
							'settings'     => array(
								'type'  => 'long-text',
								'score' => array(
									'enabled' => true,
									'value'   => 3,
								),
							),
						),
					),
				)
			);
			ok( 200 === $saved->get_status(), 'exam questions saved: ' . wp_json_encode( $saved->get_data() ) );
			list( $choice, $multiple, $written ) = array_map( 'intval', $saved->get_data()['saved_ids'] );
			$block                               = static function ( $id ) {
				return '<!-- wp:ohmylms/shared-multiple-choice {"questionId":' . $id . '} /-->';
			};
			wp_update_post(
				array(
					'ID'           => $quiz_id,
					'post_content' => '<!-- wp:ohmylms/paper-header {"school":"<b>West</b> High","subtitle":"Paper 1"} /-->'
						. '<!-- wp:ohmylms/paper-section {"title":"Section A"} /-->'
						. $block( $choice ) . $block( $multiple )
						. '<!-- wp:ohmylms/paper-page-break /-->'
						. '<!-- wp:ohmylms/paper-section {"title":"Section B"} /-->'
						. $block( $written )
						. '<!-- wp:ohmylms/paper-answer-space {"style":"grid","lines":4} /-->',
				)
			);
			$settings                 = (array) get_post_meta( $quiz_id, '_quiz_settings', true );
			$settings['presentation'] = 'paper';
			$settings['paper']        = array(
				'numbering' => 'q',
				'versions'  => 2,
			);
			$settings['time_limit']   = array(
				'value' => 30,
				'type'  => 'minutes',
			);
			update_post_meta( $quiz_id, '_quiz_settings', $settings );

			// ---- Print page ----
			$paper = PrintPaper::document( $quiz_id, 'paper' );
			ok( false !== strpos( $paper, 'assets/css/paper.css' ) && false !== strpos( $paper, 'assets/interactivity/paper-print.js' ) && false !== strpos( $paper, 'type="module"' ), 'print page loads paper.css and the print module' );
			ok( false === strpos( $paper, 'paper-print.js?' ) || false === strpos( $paper, 'dist/admin/paper-print' ), 'no React print bundle' );
			ok( false === strpos( $paper, 'ohmylms-print-data' ) && false === strpos( $paper, 'ohmylms-mathlive-loader' ), 'the paper is markup, not data, and loads no math without math' );
			ok( 3 === nodes( $paper, '//div[' . has_class( 'ohmylms-print-question' ) . ']' )->length, 'three printed questions' );
			ok( 'Q1' === trim( nodes( $paper, '//span[' . has_class( 'ohmylms-print-number' ) . ']' )->item( 0 )->textContent ), 'numbering follows the paper settings' );
			$header = nodes( $paper, '//div[' . has_class( 'ohmylms-paper-header' ) . ']' )->item( 0 );
			ok( $header && false !== strpos( $header->textContent, $tag . ' exam' ) && false !== strpos( $header->textContent, 'Time: 30 minutes' ) && false !== strpos( $header->textContent, 'Total: 6 marks' ) && false !== strpos( $header->textContent, 'Version A' ), 'header: title, time, total marks and version' );
			ok( 1 === nodes( $paper, '//div[' . has_class( 'ohmylms-paper-header' ) . ']//b' )->length && 3 === nodes( $paper, '//div[' . has_class( 'is-name' ) . ' or ' . has_class( 'is-class' ) . ' or ' . has_class( 'is-number' ) . ']' )->length, 'school keeps bold text; candidate name, class and number lines' );
			ok( '[3 marks]' === trim( nodes( $paper, '//span[' . has_class( 'ohmylms-paper-section-total' ) . ']' )->item( 0 )->textContent ), 'section totals' );
			ok( 1 === nodes( $paper, '//div[' . has_class( 'ohmylms-paper-page-break' ) . ']' )->length && 1 === nodes( $paper, '//div[' . has_class( 'ohmylms-paper-answer-space' ) . ' and ' . has_class( 'is-grid' ) . ']' )->length, 'page break and answer space print' );
			ok( 0 === nodes( $paper, '//li[' . has_class( 'is-correct' ) . ']' )->length && false === strpos( $paper, 'ohmylms-print-key' ), 'the student paper carries no answers' );
			ok( 1 === nodes( $paper, '//div[' . has_class( 'ohmylms-print-lines' ) . ']' )->length, 'written answers get ruled lines' );

			$key = PrintPaper::document( $quiz_id, 'key' );
			ok( false !== strpos( $key, 'ohmylms-print-key-banner' ) && 3 === nodes( $key, '//li[' . has_class( 'is-correct' ) . ']' )->length, 'answer key marks the correct options' );
			$correct = nodes( $key, '//li[' . has_class( 'is-correct' ) . ']//span[' . has_class( 'ohmylms-print-option' ) . ']' );
			ok( 'Mars' === $correct->item( 0 )->textContent, 'the key marks Mars' );

			$sheet = PrintPaper::document( $quiz_id, 'sheet' );
			ok( 2 === nodes( $sheet, '//ol[' . has_class( 'ohmylms-answer-sheet-rows' ) . ']/li' )->length && 1 === nodes( $sheet, '//li[' . has_class( 'is-multiple' ) . ']' )->length && false !== strpos( $sheet, 'Answer question Q3 on the question paper.' ), 'answer sheet rows for choice questions, the rest on the paper' );
			ok( 0 === nodes( $sheet, '//span[' . has_class( 'is-filled' ) . ']' )->length && 3 === nodes( PrintPaper::document( $quiz_id, 'sheet-key' ), '//span[' . has_class( 'is-filled' ) . ']' )->length, 'the marking sheet fills the correct circles' );

			$b_key   = PrintPaper::document( $quiz_id, 'key', 'B' );
			$options = static function ( $html ) {
				$texts = array();
				// Version B also shuffles the questions, so find the planet question by its options.
				foreach ( nodes( $html, '//ol[' . has_class( 'ohmylms-print-options' ) . ' and .//span[.="Mars"]]//span[' . has_class( 'ohmylms-print-option' ) . ']' ) as $node ) {
					$texts[] = $node->textContent;
				}
				return $texts;
			};
			ok( array( 'Venus', 'Mars', 'Jupiter' ) === $options( $key ) && array( 'Venus', 'Mars', 'Jupiter' ) !== $options( $b_key ) && 'Mars' === nodes( $b_key, '//ol[.//span[.="Mars"]]/li[' . has_class( 'is-correct' ) . ']//span[' . has_class( 'ohmylms-print-option' ) . ']' )->item( 0 )->textContent, 'version B reorders options and the key follows: ' . wp_json_encode( array( $options( $key ), $options( $b_key ) ) ) );
			ok( false !== strpos( $b_key, 'Answer key: version B' ) && false !== strpos( PrintPaper::document( $quiz_id, 'key', 'D' ), 'Version A' ), 'versions the paper offers only' );

			// ---- Learner Paper View ----
			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_user_enrollment',
				array(
					'user_id'    => $student,
					'course_id'  => $course_id,
					'status'     => 'enrolled',
					'progress'   => 'running',
					'start_date' => current_time( 'mysql' ),
				)
			);
			$cache = new ReflectionProperty( \OhMyLMS\DataStores\StudentStore::class, 'enrollment_cache' );
			$cache->setValue( null, array() );
			wp_set_current_user( $student );
			$attempt = Submission::start( $quiz_id, $student );
			ok( is_int( $attempt ), 'the learner starts the exam: ' . ( is_wp_error( $attempt ) ? $attempt->get_error_message() : '' ) );
			$delivery = array_column( AttemptItems::delivery( $attempt ), null, 'id' );
			$tokens   = array_column( $delivery[ $choice ]['questions'], 'id', 'answer' );

			$html = render_quiz( $quiz_id );
			ok( 1 === nodes( $html, '//section[' . has_class( 'has-paper-sheet' ) . ' and @data-wp-interactive="ohmylms/quiz"]' )->length, 'the quiz section is the player island with the sheet' );
			$section = nodes( $html, '//section' )->item( 0 );
			ok( rest_url( 'ohmylms/v1/attempts/' . $attempt . '/responses' ) === $section->getAttribute( 'data-autosave' ), 'autosave posts to the attempt responses endpoint' );
			$context = json_decode( $section->getAttribute( 'data-wp-context' ), true );
			ok( true === $context['paperPresentation'] && true === $context['timed'] && $context['remaining'] > 1700 && 3 === $context['questionCount'], 'the player keeps the timer and paper confirmation' );
			ok( false === strpos( $html, 'ohmylms-paper-student-data' ) && false === strpos( $html, 'paper-student.js' ), 'no React data island or bundle' );
			ok( 1 === nodes( $html, '//div[' . has_class( 'ohmylms-paper-student' ) . ']/div[' . has_class( 'ohmylms-print-sheet' ) . ']' )->length, 'the sheet is server-rendered' );
			ok( 0 === nodes( $html, '//div[' . has_class( 'ohmylms-quiz-form' ) . ']/div[' . has_class( 'ohmylms-container' ) . ']' )->length, 'no hidden player list behind the sheet' );
			ok( 0 === nodes( $html, '//div[' . has_class( 'ohmylms-paper-page-break' ) . ']' )->length, 'the screen paper is continuous' );
			ok( 2 === nodes( $html, '//p[' . has_class( 'ohmylms-paper-section-title' ) . ']' )->length && 0 === nodes( $html, '//h2[' . has_class( 'ohmylms-quiz-section-title' ) . ']' )->length, 'sections print once, from the paper' );
			$boxes = nodes( $html, '//div[' . has_class( 'ohmylms-paper-slot' ) . ']/div[' . has_class( 'ohmylms-quiz-box' ) . ']' );
			ok( 3 === $boxes->length, 'each player box sits in its printed place' );
			$order = array();
			foreach ( $boxes as $index => $box ) {
				$order[] = (int) $box->getAttribute( 'data-question-id' );
				ok( false !== strpos( $box->getAttribute( 'class' ), 'question-' . ( $index + 1 ) ) && false !== strpos( $box->getAttribute( 'class' ), 'is-paper-question' ), 'player numbering follows paper order' );
				ok( false !== strpos( (string) $box->getAttribute( 'data-wp-context' ), '"questionNumber":' . ( $index + 1 ) ), 'each box has the player context' );
			}
			ok( array( $choice, $multiple, $written ) === $order, 'boxes in paper order' );

			$choice_box = $boxes->item( 0 );
			$fields     = nodes( $html, '//div[@data-question-id="' . $choice . '" and ' . has_class( 'ohmylms-quiz-box' ) . ']//input[' . has_class( 'ohmylms-paper-choice-input' ) . ']' );
			ok( false !== strpos( $choice_box->getAttribute( 'class' ), 'has-paper-choices' ) && 3 === $fields->length, 'printed letters carry the answer fields' );
			ok( 0 === nodes( $html, '//div[@data-question-id="' . $choice . '"]//div[' . has_class( 'quiz-checkbox-radio-options' ) . ']' )->length, 'no second set of options' );
			$values = array();
			foreach ( $fields as $field ) {
				ok( 'radio' === $field->getAttribute( 'type' ) && 'attempt[' . $attempt . '][quiz_question][' . $choice . '][]' === $field->getAttribute( 'name' ) && 'ohmylms/quiz::actions.answerChanged' === $field->getAttribute( 'data-wp-on--input' ), 'choice fields keep the player contract and directives' );
				ok( 'label' === $field->parentNode->nodeName && 1 === preg_match( '/^[ABC]\S/', trim( $field->parentNode->textContent ) ), 'the printed letter and option label the field' );
				$values[ trim( preg_replace( '/^[A-Z]/', '', trim( $field->parentNode->textContent ) ) ) ] = $field->getAttribute( 'value' );
			}
			ok( $tokens === $values, 'field values are the attempt option tokens: ' . wp_json_encode( $values ) );
			ok( 'checkbox' === nodes( $html, '//div[@data-question-id="' . $multiple . '"]//input[' . has_class( 'ohmylms-paper-choice-input' ) . ']' )->item( 0 )->getAttribute( 'type' ), 'select-all questions use checkboxes' );
			ok( 1 === nodes( $html, '//div[@data-question-id="' . $written . '"]//textarea' )->length && 1 === nodes( $html, '//div[@data-question-id="' . $written . '"]//div[' . has_class( 'ohmylms-print-head' ) . ']' )->length && 0 === nodes( $html, '//div[@data-question-id="' . $written . '"]//div[' . has_class( 'ohmylms-print-lines' ) . ']' )->length, 'written answers keep the player control under the printed head' );
			ok( false === strpos( $html, 'is-correct' ) && false === strpos( $html, 'ohmylms-print-key' ), 'no answers on the learner paper' );

			$nav = nodes( $html, '//nav[@data-wp-interactive="ohmylms/paper"]' )->item( 0 );
			ok( $nav && 'callbacks.init' === $nav->getAttribute( 'data-wp-init' ) && false !== strpos( $nav->getAttribute( 'data-wp-context' ), 'ohmylms-paper-flags:' . $quiz_id . ':' . $attempt ), 'the navigator is an ohmylms/paper island keyed to the attempt' );
			$items = nodes( $html, '//button[' . has_class( 'ohmylms-paper-nav-item' ) . ']' );
			ok( 3 === $items->length && 'actions.jump' === $items->item( 0 )->getAttribute( 'data-wp-on--click' ) && 'Q1' === trim( preg_replace( '/\s+/', ' ', $items->item( 0 )->firstChild->textContent ) ), 'one navigator button per printed question' );
			ok( 2 === nodes( $html, '//p[' . has_class( 'ohmylms-paper-nav-section' ) . ']' )->length, 'the navigator groups questions by section' );
			ok( 1 === nodes( $html, '//nav//p[@role="status" and @aria-live="polite"]' )->length, 'a live region announces flags and time' );
			ok( 3 === nodes( $html, '//button[' . has_class( 'ohmylms-paper-flag' ) . ' and @data-wp-interactive="ohmylms/paper" and @aria-pressed="false"]' )->length, 'a flag button per question' );
			$queue = ( new ReflectionProperty( wp_script_modules(), 'queue' ) )->getValue( wp_script_modules() );
			ok( in_array( 'ohmylms/paper', $queue, true ) && in_array( 'ohmylms/quiz', $queue, true ), 'paper and quiz modules are enqueued' );
			ok( ! wp_script_is( 'ohmylms-paper-student', 'enqueued' ) && ! wp_script_is( 'ohmylms-mathlive-loader', 'enqueued' ), 'no React bundle and no math loader without math' );
			ok( wp_script_is( 'ohmylms-quiz-autosave', 'enqueued' ), 'autosave runs for the versioned attempt' );

			// ---- The endpoints accept what the paper's fields send ----
			$right = $values['Mars'];
			$save  = call_api(
				'POST',
				'attempts/' . $attempt . '/responses',
				array(
					'question_id' => $choice,
					'response'    => array( $values['Venus'] ),
					'sequence'    => 100,
				)
			);
			ok( 200 === $save->get_status() && ! empty( $save->get_data()['accepted'] ), 'autosave accepts a printed choice: ' . wp_json_encode( $save->get_data() ) );
			$save = call_api(
				'POST',
				'attempts/' . $attempt . '/responses',
				array(
					'question_id' => $choice,
					'response'    => array( $right ),
					'sequence'    => 200,
				)
			);
			ok( 200 === $save->get_status(), 'a newer answer replaces it' );
			$multi_values = array();
			foreach ( nodes( $html, '//div[@data-question-id="' . $multiple . '"]//input[' . has_class( 'ohmylms-paper-choice-input' ) . ']' ) as $field ) {
				$multi_values[ trim( preg_replace( '/^[A-Z]/', '', trim( $field->parentNode->textContent ) ) ) ] = $field->getAttribute( 'value' );
			}
			ok(
				200 === call_api(
					'POST',
					'attempts/' . $attempt . '/responses',
					array(
						'question_id' => $multiple,
						'response'    => array( $multi_values['Two'], $multi_values['Four'] ),
						'sequence'    => 300,
					)
				)->get_status(),
				'autosave accepts several checked boxes'
			);
			$resume    = call_api( 'GET', 'attempts/' . $attempt );
			$responses = json_decode( wp_json_encode( $resume->get_data() ), true )['responses'] ?? array();
			ok( 200 === $resume->get_status() && array( $right ) === ( $responses[ $choice ]['response'] ?? null ), 'resume returns the saved field values: ' . wp_json_encode( $responses ) );

			// A reload renders the same tokens, so autosave's resume finds its fields again.
			$again = render_quiz( $quiz_id );
			ok( 1 === nodes( $again, '//input[' . has_class( 'ohmylms-paper-choice-input' ) . ' and @value="' . $right . '"]' )->length, 'the same tokens render on reload' );

			// Final submit with the form fields; the server grades.
			$result = Submission::submit(
				$quiz_id,
				$attempt,
				$student,
				array(
					$choice   => array( $right ),
					$multiple => array( $multi_values['Two'], $multi_values['Four'] ),
					$written  => array( 'Light becomes sugar.' ),
				)
			);
			ok( ! is_wp_error( $result ) && 3.0 === (float) $result['total'], 'server grading scores the printed answers: ' . wp_json_encode( $result ) );
			ok( ! $warnings, 'no PHP warnings: ' . implode( '; ', $warnings ) );

			throw new RuntimeException( 'paper_view_rollback_success' );
		}
	);
} catch ( RuntimeException $error ) {
	if ( 'paper_view_rollback_success' !== $error->getMessage() ) {
		echo $error->getMessage() . "\n";
		$exit_code = 1;
	}
} finally {
	wp_set_current_user( 0 );
}

ok( ! $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login=%s", $tag . '_admin' ) ), 'fixture transaction rolled back' );
echo ( empty( $exit_code ) ? 'OK' : 'FAILED' ) . ": $checks paper view checks; fixture writes rolled back.\n";
exit( isset( $exit_code ) ? $exit_code : 0 );
