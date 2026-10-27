<?php
/**
 * Standalone shared-block rendering contract checks.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/fixtures/class-inline.php';
require dirname( __DIR__, 2 ) . '/includes/Assessment/MathLive.php';
require dirname( __DIR__, 2 ) . '/includes/Learning/NativeLessonEditor.php';

/**
 * Capture server registration.
 *
 * @param string $name Block name.
 * @param array  $args Block settings.
 */
function register_block_type( $name, $args ) {
	$GLOBALS['shared_choice_registration'] = array( $name, $args );
}

/**
 * Return the test lesson context.
 *
 * @return string Post type.
 */
function get_post_type() {
	return 'ohmylms-lesson';
}

\OhMyLMS\Learning\NativeLessonEditor::register_question_block();
list( $block_name, $settings ) = $GLOBALS['shared_choice_registration'];
if ( 'ohmylms/shared-multiple-choice' !== $block_name || array( 'questionId', 'uuid', 'authoringRevision', 'delivery' ) !== array_keys( $settings['attributes'] ) ) {
	throw new RuntimeException( 'The placement must store references without private answer data.' );
}
$uuid = '12345678-1234-1234-1234-123456789abc';
$html = call_user_func(
	$settings['render_callback'],
	array(
		'questionId' => 42,
		'uuid'       => $uuid,
		'is_correct' => true,
	)
);
if ( '12345678-1234-1234-1234-123456789abc' !== \OhMyLMS\Practice\Inline::$uuid || 'existing-inline-renderer' !== $html ) {
	throw new RuntimeException( 'The block bypassed the existing inline assessment renderer.' );
}
call_user_func( $settings['render_callback'], array() );
if ( '' !== \OhMyLMS\Practice\Inline::$uuid ) {
	throw new RuntimeException( 'An empty placement must delegate an empty reference safely.' );
}
echo "3 shared question block contract checks passed.\n";
