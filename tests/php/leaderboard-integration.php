<?php
// Disposable database only; roll back all fixtures.
$config = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true );
require $config['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || DB_NAME !== 'ohmylms_source_test' ) { exit( 1 ); }
use OhMyLMS\Engagement\Leaderboard;
$checks = 0;
function lbcheck( $ok ) { global $checks; if ( ! $ok ) { throw new RuntimeException( 'Leaderboard check failed: ' . ($checks + 1) ); } $checks++; }
$settings = array( 'threshold' => 0, 'limit' => 10 );
add_filter( 'ohmylms_leaderboard_settings', function () use ( &$settings ) { return $settings; } );
$wpdb->query( 'START TRANSACTION' );
try {
    $course = wp_insert_post( array( 'post_type' => 'ohmylms-course', 'post_status' => 'publish', 'post_title' => 'Leaderboard fixture' ) );
    $quizzes = array();
    $wpdb->insert( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $course, 'chapter_id' => $course ) );
    for ( $i = 0; $i < 2; $i++ ) {
        $quiz = wp_insert_post( array( 'post_type' => 'ohmylms-quiz', 'post_status' => 'publish', 'post_title' => 'Quiz fixture' ) );
        $quizzes[] = $quiz;
        $wpdb->insert( $wpdb->prefix . 'ohmylms_content_relationship', array( 'chapter_id' => $course, 'content_id' => $quiz, 'content_type' => 'quiz' ) );
    }
    // Student 1: (50% + 100%)/2 on quiz one, 100% on quiz two => 87.5%.
    // Student 2: 100% on one quiz, missing the other => 50%.
    foreach ( array( array(1,0,5,10), array(1,0,10,10), array(1,1,200,200), array(2,0,10,10) ) as $row ) {
        list( $student, $index, $earned, $possible ) = $row;
        $wpdb->insert( $wpdb->prefix . 'ohmylms_quiz_attempts', array( 'course_id' => $course, 'quiz_id' => $quizzes[$index], 'student_id' => $student, 'total' => $earned, 'status' => 'completed' ) );
        $attempt = $wpdb->insert_id;
        $wpdb->insert( $wpdb->prefix . 'ohmylms_quiz_attempts_answers', array( 'quiz_attempt_id' => $attempt, 'quiz_id' => $quizzes[$index], 'student_id' => $student, 'question_id' => 1, 'question_marks' => $possible, 'achive_mark' => $earned, 'given_answer' => '' ) );
    }
    $students = array( array('student_id'=>2,'completion_rate'=>100), array('student_id'=>1,'completion_rate'=>10) );
    $ranked = Leaderboard::get_students_by_highest_quiz( $students, $course );
    lbcheck( $ranked[0]['student_id'] === 1 );
    lbcheck( abs( $ranked[0]['highest_quiz_score'] - 87.5 ) < 0.001 );
    lbcheck( abs( $ranked[1]['highest_quiz_score'] - 50 ) < 0.001 );
    $settings['threshold'] = 80;
    lbcheck( count( Leaderboard::get_students_by_highest_quiz( $students, $course ) ) === 1 );
    $settings['threshold'] = 101;
    $ranked = Leaderboard::get_students_by_fastest_time( array(
        array('student_id'=>1,'is_completed'=>true,'completion_duration'=>30),
        array('student_id'=>2,'is_completed'=>false,'completion_duration'=>1),
        array('student_id'=>3,'is_completed'=>true,'completion_duration'=>20)
    ) );
    lbcheck( array_column( $ranked, 'student_id' ) === array(3,1) );
    echo "Leaderboard integration: $checks checks passed.\n";
} finally { $wpdb->query( 'ROLLBACK' ); }
