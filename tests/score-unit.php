<?php
/** Pure unit checks for the mastery score model and XP rules (no WordPress). */
define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/vendor/autoload.php';
use OhMyLMS\Skills\ScoreModel as M;

$checks = 0;
function check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); }
	++$checks;
}
function near( $a, $b, $message, $eps = 0.011 ) { check( abs( $a - $b ) <= $eps, $message . " (got $a, wanted $b)" ); }
$row = static function ( $right, $difficulty = 'standard', $independent = 1, $first = 1, $ratio = null ) {
	return array( 'awarded' => $ratio !== null ? $ratio : ( $right ? 1 : 0 ), 'available' => 1, 'independent' => $independent, 'first_try' => $first, 'difficulty' => $difficulty );
};
$run = static function ( array $rows, $start = 0.0 ) { return M::run( $rows, null, $start ); };

// The spec's worked example: at 86 a hard question right gives about 89, one miss then about 84.
$up = $run( array( $row( true, 'challenge' ) ), 86.0 );
near( $up['score'], 88.81, 'hard (d=3) correct at 86 adds 2.5 x 1.125' );
$down = $run( array( $row( true, 'challenge' ), $row( false ) ), 86.0 );
near( $down['score'], 83.81, 'then one miss costs 5' );

// Bands: forgiving early, strict near the top.
near( $run( array( $row( true ) ) )['score'], 9.0, 'a standard correct answer in the learning band adds 9' );
near( $run( array( $row( true, 'easy' ) ) )['score'], 7.88, 'an easy question counts for less' );
near( $run( array( $row( true, 'exam' ) ) )['score'], 11.25, 'an exam-level question counts for more' );
near( $run( array( $row( false ) ), 30 )['score'], 28.5, 'a miss while learning costs 1.5' );
near( $run( array( $row( true ) ), 72 )['score'], 77.0, 'the building band adds 5' );
near( $run( array( $row( true ) ), 85 )['score'], 87.5, 'the proficient band adds 2.5' );
near( $run( array( $row( true ) ), 95 )['score'], 96.5, 'the top band adds 1.5' );
near( $run( array( $row( false ) ), 95 )['score'], 90.0, 'a miss at the top costs 6.5 but never goes under 90' );

// The score never falls below the start of its band, so proficiency survives a bad day.
$bad = $run( array_fill( 0, 10, $row( false ) ), 83 );
near( $bad['score'], 80.0, 'ten misses in a row stop at the start of the proficient band' );
check( $bad['floor'] === 80, 'the floor is reported' );
near( $run( array_fill( 0, 10, $row( false ) ), 75 )['score'], 70.0, 'building floors at 70' );
near( $run( array_fill( 0, 10, $row( false ) ), 40 )['score'], 25.0, 'ten misses of 1.5 take 40 to 25' );
near( $run( array_fill( 0, 40, $row( false ) ), 40 )['score'], 0.0, 'the learning band floors at 0' );
near( $run( array_fill( 0, 30, $row( false ) ) )['score'], 0.0, 'it cannot go negative' );

// Hints: right after a hint earns half, wrong after a hint still costs in full.
near( $run( array( $row( true, 'standard', 0 ) ) )['score'], 4.5, 'a hinted correct answer earns half' );
near( $run( array( $row( false, 'standard', 0 ) ), 30 )['score'], 28.5, 'a hinted miss costs the full loss' );

// Repeats are not first tries; a new issue of a template is.
$same = $run( array( $row( true ), $row( true, 'standard', 1, 0 ) ) );
near( $same['score'], 9.0, 'a repeat of the same question does not count' );
check( $same['scored'] === 1 && $same['trace'][1]['counted'] === false && $same['trace'][1]['delta'] === 0.0, 'a repeat is traced but not counted' );
check( $run( array( array( 'awarded' => 1, 'available' => 0, 'first_try' => 1 ) ) )['scored'] === 0, 'a row with no marks available is ignored' );

// Partly correct answers (multi-part questions).
near( $run( array( $row( true, 'standard', 1, 1, 0.8 ) ) )['score'], 7.2, '80% of the marks counts as correct, scaled by the share earned' );
near( $run( array( $row( true, 'standard', 1, 1, 0.5 ) ), 30 )['score'], 28.5, 'below 70% of the marks is a miss' );

// Reaching the top.
$climb = $run( array_fill( 0, 40, $row( true ) ) );
near( $climb['score'], 100.0, 'enough correct answers reach 100' );
check( $climb['score'] <= 100.0 && $climb['medal'] === 'gold', 'the score is capped and earns gold' );
$steps = $run( array_fill( 0, 10, $row( true ) ) );
check( $steps['score'] > 70 && $steps['score'] < 100, 'ten correct answers are not enough for mastery: ' . $steps['score'] );
$first_ten = array();
for ( $i = 0; $i < 12; $i++ ) { $first_ten[] = $run( array_fill( 0, $i + 1, $row( true ) ) )['score']; }
check( min( array_map( static function ( $a, $b ) { return $b - $a; }, array_slice( $first_ten, 0, -1 ), array_slice( $first_ten, 1 ) ) ) > 0, 'each correct answer raises the score' );
$gains = array_map( static function ( $a, $b ) { return $b - $a; }, array_slice( $first_ten, 0, -1 ), array_slice( $first_ten, 1 ) );
check( $gains[0] > $gains[count( $gains ) - 1], 'gains shrink as the score rises (forgiving early, strict near the top)' );

// Medals, bands and the struggling flag.
check( M::medal( 79.9 ) === '' && M::medal( 80 ) === 'bronze' && M::medal( 89.99 ) === 'bronze' && M::medal( 90 ) === 'silver' && M::medal( 100 ) === 'gold', 'medals at 80, 90 and 100' );
$bands = M::defaults()['bands'];
check( M::band_name( 0, $bands ) === 'learning' && M::band_name( 69.99, $bands ) === 'learning' && M::band_name( 70, $bands ) === 'building' && M::band_name( 80, $bands ) === 'proficient' && M::band_name( 95, $bands ) === 'challenge', 'band names' );
check( $run( array( $row( false ), $row( false ) ), 50 )['struggling'] === true && $run( array( $row( false ), $row( true ) ), 50 )['struggling'] === false && $run( array( $row( false ), $row( false ) ), 85 )['struggling'] === false, 'two misses in a row while below 70 flag the prerequisite' );
check( $run( array( $row( true ), $row( true ), $row( true ), $row( true ), $row( true ), $row( true ), $row( true ), $row( true ) ) )['peak'] >= 70 && $run( array_merge( array_fill( 0, 12, $row( true ) ), array_fill( 0, 5, $row( false ) ) ) )['medal'] === 'bronze', 'a medal is earned once and kept even after a slip (peak)' );
$empty = $run( array() );
check( $empty['score'] === 0.0 && $empty['scored'] === 0 && $empty['struggling'] === false && $empty['medal'] === '', 'no evidence, no score' );

// Trace: replayable and consistent.
$rows = array( $row( true ) + array( 'grade_event_id' => 11 ), $row( false ) + array( 'grade_event_id' => 12 ), $row( true ) + array( 'grade_event_id' => 13 ) );
$trace = $run( $rows )['trace'];
check( $trace[0]['before'] === 0.0 && $trace[0]['after'] === $trace[1]['before'] && $trace[1]['after'] === $trace[2]['before'] && $trace[2]['grade_event_id'] === 13, 'the trace chains from row to row' );
check( $run( $rows ) === $run( $rows ), 'the same evidence always gives the same score' );

// Tuning: administrators can change the band values, never the band starts.
$tuned = M::config( array( 'bands' => array( array( 'gain' => 10, 'loss' => 1 ), array( 'gain' => 6, 'loss' => 3 ), array( 'gain' => 3, 'loss' => 4 ), array( 'gain' => 2, 'loss' => 8 ) ), 'bands_from' => 5 ) );
check( array_column( $tuned['bands'], 'from' ) === array( 0, 70, 80, 90 ) && $tuned['bands'][0]['gain'] === 10.0, 'band values can be tuned; their starts cannot' );
near( M::run( array( $row( true ) ), $tuned )['score'], 10.0, 'a tuned model is used' );
check( M::config( array( 'bands' => array( 1, 2 ) ) )['bands'] === M::defaults()['bands'], 'a malformed band list is ignored' );

// Decay: slow, only at the top, never below the floor.
$now = 1800000000;
$day = 86400;
$fresh = M::effective( 97.0, $now - 10 * $day, $now );
check( $fresh['score'] === 97.0 && $fresh['decay'] === 0.0 && ! $fresh['review_due'], 'recent practice does not decay' );
$weeks = M::effective( 97.0, $now - 35 * $day, $now );
near( $weeks['score'], 95.0, 'five weeks idle: grace of three weeks, then a point a week (two weeks)', 1.01 );
check( $weeks['review_due'] === true && $weeks['idle_days'] === 35, 'a review is due once it decays' );
check( M::effective( 92.0, $now - 400 * $day, $now )['score'] === 90.0, 'decay stops at 90' );
check( M::effective( 85.0, $now - 400 * $day, $now )['score'] === 85.0 && M::effective( 40.0, $now - 400 * $day, $now )['score'] === 40.0, 'only the top band decays' );
check( M::effective( 97.0, null, $now )['score'] === 97.0, 'no practice date, no decay' );
echo "$checks score unit checks passed.\n";
