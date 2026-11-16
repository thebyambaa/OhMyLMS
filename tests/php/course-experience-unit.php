<?php
/**
 * Kids path sequencing without changing completion evidence.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use OhMyLMS\Learning\CourseExperience;

$steps = array(
	array(
		'content_id' => 10,
		'type'       => 'lesson',
		'available'  => true,
		'complete'   => false,
	),
	array(
		'content_id' => 11,
		'type'       => 'lesson',
		'available'  => false,
		'complete'   => false,
	),
	array(
		'content_id' => 10,
		'type'       => 'practice',
		'available'  => true,
		'complete'   => false,
	),
	array(
		'content_id' => 12,
		'type'       => 'lesson',
		'available'  => true,
		'complete'   => true,
	),
);
if ( CourseExperience::step_locked( $steps, 10 ) || ! CourseExperience::step_locked( $steps, 10, 'practice' ) || CourseExperience::step_locked( $steps, 12 ) ) {
	throw new RuntimeException( 'First step, namespaced skill or completed replay access is incorrect.' );
}
$steps[0]['complete'] = true;
if ( CourseExperience::step_locked( $steps, 10, 'practice' ) ) {
	throw new RuntimeException( 'Completing the prior step must unlock practice, skipping unavailable content.' );
}
echo "Kids mode: first step, locked next, replay, unavailable steps and content/skill collisions passed.\n";
