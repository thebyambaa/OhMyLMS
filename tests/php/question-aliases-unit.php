<?php
/**
 * Question alias map used by the studio quiz builder.
 *
 * @package OhMyLMS
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	require dirname( __DIR__, 2 ) . '/includes/Learning/QuestionAliases.php';
	require dirname( __DIR__, 2 ) . '/includes/Learning/LessonBlocks.php';

	$failed = false;
	$checks = 0;
	function alias_check( $condition, $message ) {
		global $checks, $failed;
		++$checks;
		if ( ! $condition ) {
			$failed = true;
			echo $message . "\n";
		}
	}

	alias_check( 'single-choice' === \OhMyLMS\Learning\QuestionAliases::canonical( 'single_choice' ), 'single_choice did not map.' );
	alias_check( 'multiple-choice' === \OhMyLMS\Learning\QuestionAliases::canonical( 'multiple_choice' ), 'multiple_choice did not map.' );
	alias_check( 'numerical' === \OhMyLMS\Learning\QuestionAliases::canonical( 'numerical' ), 'numerical did not map.' );
	alias_check( 'short-text' === \OhMyLMS\Learning\QuestionAliases::canonical( 'short_answer' ), 'short_answer did not map.' );
	alias_check( 'true-false' === \OhMyLMS\Learning\QuestionAliases::canonical( 'true_false' ), 'true_false did not map.' );
	alias_check( '' === \OhMyLMS\Learning\QuestionAliases::canonical( 'essay' ), 'An unknown type was accepted.' );
	alias_check( in_array( 'ohmylms/lesson-exercise', \OhMyLMS\Learning\LessonBlocks::ALLOWED, true ), 'The exercise block is not allowlisted.' );
	echo $failed ? "Alias checks failed.\n" : "OK: {$checks} alias checks passed.\n";
	exit( $failed ? 1 : 0 );
}
