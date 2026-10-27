<?php
/**
 * Standalone registry and built-in question grading checks.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Extensions\QuestionTypes as Q;
use OhMyLMS\Extensions\Registry as R;

require dirname( __DIR__ ) . '/vendor/autoload.php';
/**
 * Return correct options for the fixture question.
 *
 * @param int $id Question ID.
 * @return array
 */
function ohmylms_get_question_ans_by_question_id( $id ) {
	if ( 1 !== $id ) {
		return array();
	}
	return array_values(
		array_filter(
			$GLOBALS['options'],
			function ( $o ) {
				return ! empty( $o['is_correct'] );
			}
		)
	);
}
/**
 * Assert a CLI test result.
 *
 * @param bool   $condition Expected condition.
 * @param string $message Failure description.
 * @throws RuntimeException When the check fails.
 */
function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI-only assertion, never rendered in HTML.
	}
}
$GLOBALS['options'] = array(
	array(
		'id'         => 1,
		'answer'     => 'A',
		'is_correct' => 1,
	),
	array(
		'id'         => 2,
		'answer'     => 'B',
		'is_correct' => 1,
	),
);
$q                  = new class() {
	/**
	 * Return the fixture question ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return 1;
	}
	/**
	 * Return the fixture options.
	 *
	 * @return array
	 */
	public function get_questions() {
		return $GLOBALS['options'];
	}
	/**
	 * Return the default grading settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		return array();
	}
};
Q::register_defaults();
check( count( R::all( 'question' ) ) === 9, 'All built-in types registered' );
check( Q::grade_builtin( 'multiple-choice', array( '2', '1' ), $q )['correct'], 'Choice order must not affect grade' );
check( ! Q::grade_builtin( 'multiple-choice', array( '1', '1' ), $q )['correct'], 'Duplicate selections must not pass' );
check( Q::grade_builtin( 'reorder', array( '1', '2' ), $q )['correct'], 'HTML string IDs grade correctly' );
check( ! Q::grade_builtin( 'reorder', array( '2', '1' ), $q )['correct'], 'Wrong order fails' );
check(
	Q::grade_builtin(
		'matching',
		array(
			1 => '1',
			2 => '2',
		),
		$q
	)['correct'],
	'Matching pairs grade'
);
check( ! Q::grade_builtin( 'matching', array( 1 => '1' ), $q )['correct'], 'Incomplete matching fails' );
check( Q::grade_builtin( 'short-text', array( 'essay' ), $q )['manual'], 'Text requires manual review' );
check( Q::grade_builtin( 'long-text', array( 'essay' ), $q )['manual'], 'Long text requires manual review' );
check( Q::grade_builtin( 'fill-in-the-blank', array( 'A', 'B' ), $q )['correct'], 'Fill-in-the-blank grade' );
check( Q::grade_builtin( 'statement', array( 'A', 'B' ), $q )['correct'], 'Statement grade' );
check( ! Q::grade_builtin( 'single-choice', array(), $q )['correct'], 'Empty answer does not pass' );
$GLOBALS['options'] = array(
	array(
		'id'         => 1,
		'answer'     => 'True',
		'is_correct' => 1,
	),
	array(
		'id'         => 2,
		'answer'     => 'False',
		'is_correct' => 0,
	),
);
check( Q::grade_builtin( 'single-choice', array( '1' ), $q )['correct'], 'Single choice grades' );
check( Q::grade_builtin( 'true-false', array( '1' ), $q )['correct'], 'True/false grades' );
check( ! Q::grade_builtin( 'true-false', array( '2' ), $q )['correct'], 'Incorrect true/false fails' );
try {
	R::register( 'question', 'reorder', array() );
	throw new RuntimeException( 'Duplicate registration accepted' );
} catch ( InvalidArgumentException $expected ) {
	check( 'Extension already registered: reorder' === $expected->getMessage(), 'Duplicate registration must report the existing extension' );
}
echo "16 registry and grading checks passed.\n";
