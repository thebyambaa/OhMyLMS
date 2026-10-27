<?php
/**
 * Shared skill links retain distinct completion targets and validate lesson scope.
 *
 * @package OhMyLMS
 */

require __DIR__ . '/learning-model-unit.php';

use OhMyLMS\Learning\ProgramGroups;

$lesson_link_program = $base(
	array(
		'groups' => array(
			array( 'id' => $g1, 'chapter_id' => 7001, 'title' => 'Introduce', 'skill_ids' => array( 11, 12 ) ),
			array( 'id' => $g2, 'chapter_id' => 7002, 'title' => 'Review', 'skill_ids' => array( 11 ) ),
		),
	)
);
$lesson_link_saved = $normalize( $lesson_link_program );
check( ! is_wp_error( $lesson_link_saved ), 'A skill can belong to lessons in different units.' );
check( array( 11 ) === ProgramGroups::skill_ids( $lesson_link_saved, $lesson_link_saved['groups'][1] ), 'Explicit lesson links survive normalization.' );
check( count( $lesson_link_saved['outcomes'] ) === count( $lesson_link_program['outcomes'] ), 'Completion outcomes are not duplicated.' );
$lesson_link_program['groups'][1]['skill_ids'] = array( 99 );
check( is_wp_error( $normalize( $lesson_link_program ) ), 'Lessons cannot reference a skill outside this course.' );
$lesson_link_program['groups'][1]['skill_ids'] = '11';
check( is_wp_error( $normalize( $lesson_link_program ) ), 'Malformed lesson links are rejected.' );
$lesson_link_program['groups'][1]['skill_ids'] = array();
check( array() === ProgramGroups::skill_ids( $lesson_link_program, $lesson_link_program['groups'][1] ), 'Explicit empty links do not restore legacy membership.' );
echo "Lesson skill link checks passed.\n";
