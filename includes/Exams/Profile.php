<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * The exam assessment profile: a timed dashboard player on the shared quiz engine.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams;

use OhMyLMS\Assessment\DeliveryPolicy;

defined( 'ABSPATH' ) || exit;

/** Everything that makes an exam differ from a quiz, declared in one place. */
final class Profile {
	const STYLE = 'ohmylms-exam-template';

	/** Register the profile and the stylesheet of its player design. */
	public static function init() {
		add_filter( 'ohmylms_assessment_profiles', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'styles' ) );
	}

	/** Register the exam design's stylesheet; the player enqueues it when it uses the design. */
	public static function styles() {
		wp_register_style( self::STYLE, plugins_url( 'assets/css/exam-player-template.css', OHMYLMS_FILE ), array( 'ohmylms-player-templates' ), (string) filemtime( OHMYLMS_DIR . '/assets/css/exam-player-template.css' ) );
	}

	/**
	 * Add the exam kind to the assessment profiles.
	 *
	 * @param array $profiles Registered profiles.
	 * @return array
	 */
	public static function register( $profiles ) {
		$profiles['exam'] = array(
			'label'           => __( 'Exam', 'ohmylms' ),
			'player_template' => 'exam',
			'layout'          => 'one_question_per_page',
			// Formal attempts only: practice runs and inline checks are not exam content.
			'deliveries'      => array( DeliveryPolicy::QUIZ ),
			'return_route'    => '/content-hub/exams',
			'panel'           => 'exam',
			'editor_data'     => array( __CLASS__, 'editor_data' ),
			'context'         => array( __CLASS__, 'context' ),
			'templates'       => array(
				'exam' => array(
					'label'       => __( 'Exam', 'ohmylms' ),
					'description' => __( 'A timed exam dashboard with a question palette, timer and progress overview.', 'ohmylms' ),
					'stylesheet'  => self::STYLE,
				),
			),
		);
		return $profiles;
	}

	/**
	 * What the exam panel in the quiz editor shows.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array Exam id, status, player URL and instructions; empty for a quiz without an exam record.
	 */
	public static function editor_data( $quiz_id ) {
		$exam_id = Composer::exam_for_quiz( $quiz_id );
		if ( ! $exam_id ) {
			return array();
		}
		$exam = Library::view( $exam_id );
		return array(
			'id'           => $exam_id,
			'status'       => $exam['status'],
			'url'          => $exam['url'],
			'instructions' => $exam['instructions'],
		);
	}

	/**
	 * Starting state of the exam player: palette bookkeeping and its labels.
	 *
	 * @param array $context Player context.
	 * @param int   $quiz_id Quiz ID.
	 * @return array
	 */
	public static function context( $context, $quiz_id ) {
		unset( $quiz_id );
		$context += array(
			'marked'        => (object) array(),
			'visited'       => (object) array( 1 => true ),
			'markText'      => __( 'Mark for review', 'ohmylms' ),
			'markedText'    => __( 'Marked for review', 'ohmylms' ),
			/* translators: %d: number of questions without an answer. */
			'confirmSubmit' => __( '%d questions have no answer yet. Submit the exam anyway?', 'ohmylms' ),
		);
		$context['nextText'] = __( 'Save and Next', 'ohmylms' );
		return $context;
	}
}
