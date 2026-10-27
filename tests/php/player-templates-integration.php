<?php
/**
 * Read-only checks against WordPress; fixtures use request-local filters only.
 *
 * @package OhMyLMS\Tests
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
$player_credentials_path = getenv( 'OHMYLMS_TEST_CREDENTIALS' );
if ( $player_credentials_path ) {
	if ( ! is_file( $player_credentials_path ) ) {
		throw new RuntimeException( 'Disposable credentials file not found.' );
	}
	$player_credentials = json_decode( file_get_contents( $player_credentials_path ), true );
	if ( ! is_array( $player_credentials ) || empty( $player_credentials['site'] ) ) {
		throw new RuntimeException( 'Disposable site path required.' );
	}
	require $player_credentials['site'] . '/wp-load.php';
	if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_source_test' !== DB_NAME ) {
		throw new RuntimeException( 'Disposable database required.' );
	}
} else {
	// Retain the existing read-only local-site invocation.
	require dirname( __DIR__, 5 ) . '/wp-load.php';
}

use OhMyLMS\Design\Tokens;
use OhMyLMS\Quiz\PlayerTemplates;

$checks = 0;
/**
 * Assert a presentation contract without changing site records.
 *
 * @param bool   $condition Required invariant.
 * @param string $message Diagnostic text.
 * @throws RuntimeException When the invariant fails.
 */
function player_template_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$checks;
}

player_template_check( array( 'playful', 'classic', 'paper', 'focus' ) === array_column( PlayerTemplates::choices(), 'value' ), 'Built-in choices' );
player_template_check( 'playful' === PlayerTemplates::DEFAULT_TEMPLATE && 'playful' === PlayerTemplates::resolve( null ), 'Playful is the default design' );
player_template_check( 'classic' === PlayerTemplates::resolve( 'classic' ), 'A quiz that chose Classic keeps it' );
foreach ( array( '../../evil', '<script>', array(), null, 7, 'missing' ) as $unsafe ) {
	player_template_check( 'playful' === PlayerTemplates::resolve( $unsafe ), 'Unsafe or unregistered design must fall back' );
}
$registration = static function ( $templates ) {
	$templates['school-blue'] = array(
		'label'       => '<b>School</b>',
		'description' => '<i>Blue stage</i>',
		'stylesheet'  => 'school-player',
	);
	$templates['../evil']     = array( 'label' => 'Bad' );
	$templates['malformed']   = array( 'label' => array() );
	return $templates;
};
add_filter( 'ohmylms_quiz_player_templates', $registration );
player_template_check( 'school-blue' === PlayerTemplates::resolve( 'school-blue' ), 'Extensions can register choices' );
$choices = PlayerTemplates::choices();
player_template_check( 5 === count( $choices ), 'Malformed definitions are excluded' );
player_template_check( 'School' === $choices[4]['label'] && ! isset( $choices[4]['stylesheet'] ), 'Public choices sanitize labels and omit implementation' );
wp_register_style( 'school-player', false, array(), '1' );
PlayerTemplates::enqueue( 'school-blue' );
player_template_check( wp_style_is( 'school-player', 'enqueued' ), 'Extension stylesheet is enqueued' );
remove_filter( 'ohmylms_quiz_player_templates', $registration );

ob_start();
PlayerTemplates::part(
	'header',
	'classic',
	array(
		'quiz_name'  => '<script>alert(1)</script>',
		'is_preview' => true,
	)
);
$header = ob_get_clean();
player_template_check( false === strpos( $header, '<script>' ) && false !== strpos( $header, '&lt;script&gt;' ), 'Header escapes authored names' );
player_template_check( false !== strpos( $header, 'quiz-page-close' ), 'Overridable header preserves exit control' );
ob_start();
PlayerTemplates::part( '../header', 'classic', array() );
player_template_check( '' === ob_get_clean(), 'Part paths are fixed and bounded' );

foreach ( Tokens::QUIZ_COLORS as $option => $default ) {
	player_template_check( apply_filters( 'sanitize_option_' . $option, '</style><script>bad</script>' ) === $default, 'CSS injection is rejected' );
	player_template_check( '#abc' === apply_filters( 'sanitize_option_' . $option, '#abc' ), 'Valid short hex is accepted' );
}
$invalid_color = static function () {
	return 'red;}</style><script>bad</script>';
};
add_filter( 'pre_option_ohmylms_quiz_stage_color', $invalid_color );
player_template_check( Tokens::color( 'ohmylms_quiz_stage_color' ) === Tokens::QUIZ_COLORS['ohmylms_quiz_stage_color'], 'Invalid stored colors fall back during rendering' );
remove_filter( 'pre_option_ohmylms_quiz_stage_color', $invalid_color );
player_template_check( false !== strpos( Tokens::frontend_css(), '--ohmylms-quiz-player-answer-1:' ), 'Learner receives player tokens' );
player_template_check( false !== strpos( Tokens::quiz_css(), '--ohmylms-quiz-answer-1:' ), 'Editor receives its own answer tokens' );
player_template_check( in_array( 'player_template', OhMyLMS\Assessment\RevisionPublisher::FROZEN_SETTINGS, true ), 'Template choice is frozen into new revisions' );
$controller     = new OhMyLMS\Rest\V1\SettingsController();
$allowed_option = new ReflectionMethod( $controller, 'is_valid_option_key' );
foreach ( array_keys( Tokens::QUIZ_COLORS ) as $option ) {
	player_template_check( $allowed_option->invoke( $controller, $option ), 'Design API must allow each registered quiz color' );
}
player_template_check( ! $allowed_option->invoke( $controller, 'blogname' ), 'Design API must retain its option boundary' );
echo esc_html( $checks . ' player template checks passed.' ) . "\n";
