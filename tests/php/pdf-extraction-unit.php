<?php
/**
 * Bounded extraction contracts and native PDF wire formats; no provider calls.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
define( 'ABSPATH', __DIR__ );
/**
 * Translation stub.
 *
 * @param string $text Text.
 * @param string $unused_domain Domain.
 * @return string Text.
 */
function __( $text, $unused_domain = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Preserve the host translation function signature.
	return $text; }
/**
 * JSON stub.
 *
 * @param mixed $value Value.
 * @return string Encoded value.
 */
function wp_json_encode( $value ) {
	return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Implements the host JSON stub itself.
}
/**
 * Text stub; real sanitization is exercised in WordPress integration.
 *
 * @param string $value Text.
 * @return string Trimmed text.
 */
function sanitize_text_field( $value ) {
	return trim( $value ); }
/**
 * Textarea stub for schema-only tests.
 *
 * @param string $value Text.
 * @return string Trimmed text.
 */
function sanitize_textarea_field( $value ) {
	return trim( $value ); }
/**
 * Identity filter stub.
 *
 * @param string $unused_hook Hook.
 * @param mixed  $value Value.
 * @return mixed Value.
 */
function apply_filters( $unused_hook, $value ) {
	return $value; }
require dirname( __DIR__ ) . '/fixtures/class-wp-error.php';
/**
 * Host error predicate stub.
 *
 * @param mixed $value Value.
 * @return bool Is error.
 */
function is_wp_error( $value ) {
	return $value instanceof WP_Error; }
$root = dirname( __DIR__, 2 );
foreach ( array( 'Assessment/NumericAnswer', 'Assessment/Structured', 'AI/Providers/Provider', 'AI/Providers/OpenAI', 'AI/Providers/Anthropic', 'AI/Providers/Gemini', 'AI/Client', 'ExamImport/PageRenderer', 'ExamImport/FigureSpec', 'ExamImport/FigureContent', 'ExamImport/Extraction' ) as $file ) {
	require $root . '/includes/' . $file . '.php'; }
use OhMyLMS\ExamImport\Extraction;
$checks    = 0;
$check     = static function ( $value, $message ) use ( &$checks ) {
	if ( ! $value ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI-only unit diagnostics, not an HTML response.
	} ++$checks;
};
$inventory = array(
	'title'            => 'Synthetic exam',
	'duration_minutes' => 30,
	'total_marks'      => 2,
	'page_count'       => 2,
	'question_numbers' => array( '1' ),
);
$row       = array(
	'number'   => '1',
	'question' => 'Find a value.',
	'page'     => 1,
	'parts'    => array(
		array(
			'id'     => 'a_i',
			'kind'   => 'numerical',
			'marks'  => 2,
			'answer' => 5,
		),
	),
);
$check( ! is_wp_error( Extraction::inventory( $inventory ) ), 'Valid inventory' );
foreach ( array( array( 'question_numbers' => array( '1', '1' ) ), array( 'question_numbers' => array() ), array( 'question_numbers' => array_fill( 0, 201, '1' ) ), array( 'page_count' => 201 ), array( 'duration_minutes' => INF ), array( 'total_marks' => -1 ), array( 'title' => array() ) ) as $change ) {
	$check( is_wp_error( Extraction::inventory( array_replace( $inventory, $change ) ) ), 'Malformed or unbounded inventory rejected' );
}
$check( ! is_wp_error( Extraction::questions( array( $row ), array( '1' ), 2 ) ), 'Valid source question' );
$bad_figure_row = $row + array(
	'figures' => array(
		array(
			'id'     => 'f1',
			'page'   => 1,
			'bounds' => array( 0, 0, 2, 1 ),
			'alt'    => 'Uncertain diagram',
		),
	),
);
$check( is_wp_error( Extraction::questions( array( $bad_figure_row ), array( '1' ), 2 ) ), 'Invalid teacher figure edits remain rejected' );
$manual_crop = Extraction::questions( array( $bad_figure_row ), array( '1' ), 2, true );
$check( ! is_wp_error( $manual_crop ) && $manual_crop[0]['requires_source'] && array() === $manual_crop[0]['figures'] && false !== strpos( $manual_crop[0]['notes'], 'manual crop' ), 'Bad AI bounds remain visible and manually recoverable without losing question text' );
$check( is_wp_error( Extraction::questions( array(), array( '1' ), 2 ) ), 'Missing inventoried question rejected' );
$check( is_wp_error( Extraction::questions( array( $row, $row ), array( '1' ), 2 ) ), 'Duplicate question rejected' );
foreach ( array(
	array( 'page' => 3 ),
	array( 'question' => array() ),
	array( 'parts' => array() ),
	array(
		'parts' => array(
			array(
				'id'     => 'b',
				'kind'   => 'numerical',
				'marks'  => 1,
				'answer' => 'nonsense',
			),
		),
	),
	array(
		'parts' => array(
			array(
				'id'    => 'b',
				'kind'  => 'written',
				'marks' => 1001,
			),
		),
	),
	array(
		'parts' => array(
			array(
				'id'     => 'b',
				'kind'   => 'written',
				'marks'  => 1,
				'rubric' => array(),
			),
		),
	),
) as $change ) {
	$check( is_wp_error( Extraction::questions( array( array_replace( $row, $change ) ), array( '1' ), 2 ) ), 'Invalid question field or part rejected' );
}
$documents = array(
	array(
		'name'  => 'question-paper.pdf',
		'bytes' => '%PDF-synthetic',
	),
);
foreach ( array( 'openai', 'anthropic', 'gemini' ) as $provider ) {
	$request = Extraction::request(
		array(
			'provider' => $provider,
			'model'    => 'fixture',
		),
		'not-a-real-key',
		'extract',
		$documents
	);
	$check( ! is_wp_error( $request ) && false !== strpos( wp_json_encode( $request['body'] ), base64_encode( '%PDF-synthetic' ) ), 'Provider receives native PDF bytes' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Verify native provider PDF wire encoding.
	$check( false === strpos( $request['url'], 'not-a-real-key' ), 'Key never enters provider URL' );
	$image_request = Extraction::request(
		array(
			'provider' => $provider,
			'model'    => 'fixture',
		),
		'not-a-real-key',
		'reconstruct',
		array(
			array(
				'name'  => 'figure.png',
				'mime'  => 'image/png',
				'bytes' => 'PNG-fixture',
			),
		)
	);
	$check( ! is_wp_error( $image_request ) && false !== strpos( str_replace( '\\/', '/', wp_json_encode( $image_request['body'] ) ), 'image/png' ), 'Provider receives a native PNG reconstruction input' );
}
echo (int) $checks . " PDF extraction unit checks passed.\n";
