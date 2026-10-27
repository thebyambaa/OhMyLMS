<?php
/** Minimal runtime doubles; production grading is tested separately. */
namespace OhMyLMS\Extensions {
	class Interactivity { public static function enqueue( $id ) {} }
}
namespace OhMyLMS\Quiz {
	class PlayerTemplates { const DEFAULT_TEMPLATE = 'playful'; public static function enqueue( $id ) {} }
	class Submission {
		public static function access( $quiz, $user ) { return new \WP_Error( 'denied', 'Enrollment required.' ); }
		public static function start( $quiz, $user ) {
			$GLOBALS['submitted'] = array( $quiz, $user, 'start' );
			return $GLOBALS['engine_error'] ?? 11;
		}
		public static function submit( $quiz, $attempt, $user, $answers, $reason ) {
			$GLOBALS['submitted'] = array( $quiz, $attempt, $user, $answers, $reason );
			return $GLOBALS['engine_error'] ?? array( 'total' => 9 );
		}
	}
}
namespace OhMyLMS\Assessment {
	class Delivery { public static function require_script() {} }
}
