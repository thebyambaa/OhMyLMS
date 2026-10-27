<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * The screen shown before a mixed-skill quiz, unit test or course challenge starts.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Quiz;

use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\Pools;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Performance\Settings as PerformanceSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Tells the learner what they are about to take: how many questions, how long it usually takes and
 * how many skills it covers. Shown for assessments that draw questions for several skills, or that
 * are marked as a unit test or course challenge; an ordinary quiz keeps its own start screen.
 */
final class Intro {

	/**
	 * What the learner is about to take.
	 *
	 * @param object $quiz Quiz (\OhMyLMS\Data\Quiz).
	 * @return array{questions:int,skills:int,minutes_min:int,minutes_max:int,timed:bool,style:string,mixed:bool}
	 */
	public static function summary( $quiz ) {
		$quiz_id = (int) $quiz->get_id();
		$ready   = Schema::ready();
		$rules   = $ready ? Pools::rules( $quiz_id ) : array();
		$pooled  = 0;
		$terms   = array();
		foreach ( $rules as $rule ) {
			$pooled                         += (int) $rule['count'];
			$terms[ (int) $rule['term_id'] ] = true;
		}
		$questions = (int) $quiz->get_total_question() + $pooled;
		$timer     = (int) $quiz->get_timer();
		$style     = $ready ? (string) AssessmentSettings::get( $quiz_id )['style'] : 'quiz';
		return array(
			'questions'   => $questions,
			'skills'      => count( $terms ),
			// Untimed: about one to two minutes a question.
			'minutes_min' => $timer > 0 ? $timer : $questions,
			'minutes_max' => $timer > 0 ? $timer : $questions * 2,
			'timed'       => $timer > 0,
			'style'       => $style,
			'mixed'       => count( $terms ) >= 2,
		);
	}

	/**
	 * Whether this quiz gets the intro: a unit test or course challenge, or a quiz that covers several skills.
	 *
	 * @param array $summary Output of summary().
	 * @return bool
	 */
	public static function applies( array $summary ) {
		return $summary['questions'] > 0 && ( 'quiz' !== $summary['style'] || $summary['mixed'] );
	}

	/**
	 * The wording for a style.
	 *
	 * @param string $style quiz, unit-test or course-challenge.
	 * @return array{title:string,lead:string}
	 */
	public static function words( $style ) {
		return array(
			'quiz'             => array(
				'title' => __( 'Time for a quiz?', 'ohmylms' ),
				'lead'  => __( 'Get ready for questions on the skills so far.', 'ohmylms' ),
			),
			'unit-test'        => array(
				'title' => __( 'All set for the unit test?', 'ohmylms' ),
				'lead'  => __( 'Welcome to the unit test, where you get to test your skills for the entire unit.', 'ohmylms' ),
			),
			'course-challenge' => array(
				'title' => __( 'Ready for the course challenge?', 'ohmylms' ),
				'lead'  => __( 'Test your knowledge of the skills in this course.', 'ohmylms' ),
			),
		)[ $style ] ?? array(
			'title' => __( 'Time for a quiz?', 'ohmylms' ),
			'lead'  => '',
		);
	}

	/**
	 * "5 questions · 5-10 minutes" (or "11 questions · 20 minutes" when the quiz has a time limit).
	 *
	 * @param array $summary Output of summary().
	 * @return string
	 */
	public static function length( array $summary ) {
		/* translators: %d: number of questions */
		$questions = sprintf( _n( '%d question', '%d questions', $summary['questions'], 'ohmylms' ), $summary['questions'] );
		if ( $summary['minutes_min'] === $summary['minutes_max'] ) {
			/* translators: %d: minutes */
			$time = sprintf( _n( '%d minute', '%d minutes', $summary['minutes_min'], 'ohmylms' ), $summary['minutes_min'] );
		} else {
			/* translators: 1: fewest minutes, 2: most minutes */
			$time = sprintf( __( '%1$d-%2$d minutes', 'ohmylms' ), $summary['minutes_min'], $summary['minutes_max'] );
		}
		return $questions . ' · ' . $time;
	}

	/**
	 * The intro card, or an empty string when the quiz does not get one.
	 *
	 * @param object $quiz Quiz.
	 * @return string HTML.
	 */
	public static function render( $quiz ) {
		$summary = self::summary( $quiz );
		if ( ! self::applies( $summary ) ) {
			return '';
		}
		wp_enqueue_style( 'ohmylms-quiz-intro', plugins_url( 'assets/css/quiz-intro.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/quiz-intro.css' ) );
		$words = self::words( $summary['style'] );
		ob_start();
		?>
		<section class="ohmylms-assessment-intro is-<?php echo esc_attr( $summary['style'] ); ?>" aria-label="<?php echo esc_attr( $words['title'] ); ?>">
			<h2><?php echo esc_html( $words['title'] ); ?></h2>
			<?php if ( '' !== $words['lead'] ) : ?>
				<p><?php echo esc_html( $words['lead'] ); ?></p>
			<?php endif; ?>
			<p class="ohmylms-assessment-intro-length"><strong><?php echo esc_html( self::length( $summary ) ); ?></strong></p>
			<?php if ( $summary['skills'] > 0 ) : ?>
				<p class="ohmylms-assessment-intro-skills">
					<?php /* translators: %d: number of skills */ echo esc_html( sprintf( _n( 'Covers %d skill.', 'Covers %d skills.', $summary['skills'], 'ohmylms' ), $summary['skills'] ) ); ?>
					<?php if ( PerformanceSettings::retries() ) : ?>
						<?php esc_html_e( 'Your answers can move each skill up or down one level.', 'ohmylms' ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
