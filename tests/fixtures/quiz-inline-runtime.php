<?php
/** Browser-only engine doubles: no WordPress database or real learner records. */
namespace OhMyLMS\Quiz {
	class Submission {
		public static function access( $quiz, $user ) { return 2; }
		public static function start( $quiz, $user ) {
			$_SESSION['quizzes'][ $quiz ]['active'] = 11;
			return 11;
		}
		public static function submit( $quiz, $attempt, $user, $answers, $reason ) {
			$_SESSION['quizzes'][ $quiz ] = array( 'active' => 0, 'completed' => true, 'score' => 'submit' === $reason && ( $answers[101][0] ?? '' ) === '1010' ? 1 : 0 );
			return array( 'saved' => true );
		}
	}
}
namespace OhMyLMS\Assessment {
	class Delivery { public static function require_script() {} }
	class AttemptReport { public static function basis( $attempt, $quiz ) { return array( 'max' => 1 ); } }
	class Scoring { public static function display( $value ) { return (string) $value; } }
	class InlineBlanks { public static function render( $question, $attempt ) { return esc_html( $question['name'] ); } }
}
