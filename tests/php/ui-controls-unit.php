<?php
/**
 * Standalone escaping and native-control semantics checks for shared fields.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
/**
 * The standalone test supplies no stylesheet registry.
 *
 * @return bool
 */
function wp_style_is() {
	return true;
}
/** No-op stylesheet queue in this standalone fixture. */
function wp_enqueue_style() {}
/**
 * Escape an attribute without loading WordPress.
 *
 * @param mixed $value Attribute value.
 * @return string
 */
function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES );
}
/**
 * Escape a text node without loading WordPress.
 *
 * @param mixed $value Text value.
 * @return string
 */
function esc_html( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES );
}
/**
 * Match WordPress's request-local unique ID behavior.
 *
 * @param string $prefix ID prefix.
 * @return string
 */
function wp_unique_id( $prefix ) {
	static $sequence = 0;
	return $prefix . ( ++$sequence );
}
/**
 * Fail immediately when a rendering contract is broken.
 *
 * @param bool   $condition Verified condition.
 * @param string $message Failure description.
 * @throws RuntimeException When the condition fails.
 */
function control_check( $condition, $message ) {
	if ( ! $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI assertion text, not HTML output.
		throw new RuntimeException( $message );
	}
}
use OhMyLMS\Design\Ui;
$input = Ui::input(
	array(
		'id'       => 'email',
		'name'     => 'email',
		'type'     => 'email',
		'label'    => '<b>Email</b>',
		'required' => true,
		'value'    => '"><script>bad()</script>',
		'help'     => 'Help & details',
		'error'    => '<script>Invalid</script>',
		'class'    => 'custom',
		'attrs'    => array(
			'aria-describedby' => 'external email-help',
			'onfocus'          => 'bad()',
			'class'            => 'override',
			'data-note'        => '<tag>',
		),
	)
);
control_check( false === strpos( $input, '<script>' ), 'Value and error text must be escaped.' );
control_check( false === strpos( $input, '<b>' ), 'Labels are plain text.' );
control_check( false === strpos( $input, ' onfocus=' ), 'Event-handler attributes must not be rendered.' );
control_check( false !== strpos( $input, 'aria-describedby="external email-help email-error"' ), 'Descriptions are combined and deduplicated.' );
control_check( false !== strpos( $input, 'aria-invalid="true"' ), 'Errors mark the control invalid.' );
control_check( false !== strpos( $input, 'for="email"' ), 'The label names its control.' );
control_check( false !== strpos( $input, 'class="oml-control oml-input oml-control-invalid custom"' ), 'Caller attributes cannot replace the computed class.' );
control_check( false !== strpos( $input, ' required' ), 'Required remains a native attribute.' );
control_check( false !== strpos( $input, 'data-note="&lt;tag&gt;"' ), 'Data attributes are escaped.' );
$fallback = Ui::input(
	array(
		'type'  => 'checkbox',
		'label' => 'Fallback',
	)
);
control_check( false !== strpos( $fallback, 'type="text"' ), 'Unsupported input kinds fall back to text.' );
$readonly = Ui::input(
	array(
		'readonly' => true,
		'disabled' => false,
		'value'    => 'Reference',
		'attrs'    => array( 'aria-hidden' => false ),
	)
);
control_check( false !== strpos( $readonly, ' readonly' ) && false === strpos( $readonly, ' disabled' ), 'Read-only is distinct from disabled.' );
control_check( false !== strpos( $readonly, 'aria-hidden="false"' ), 'False ARIA values are retained.' );
preg_match( '/ id="([^"]+)"/', $fallback, $first_id );
preg_match( '/ id="([^"]+)"/', $readonly, $second_id );
control_check( $first_id[1] !== $second_id[1], 'Generated IDs are unique.' );
$select = Ui::select(
	array(
		'id'       => 'subjects',
		'name'     => 'subjects[]',
		'label'    => 'Subjects',
		'multiple' => true,
		'value'    => array( '0', 'math' ),
		'options'  => array(
			array(
				'value' => 0,
				'label' => 'Zero',
			),
			array(
				'value' => 'math',
				'label' => '<Math>',
			),
			array(
				'value'    => 'closed',
				'label'    => 'Closed',
				'disabled' => true,
			),
		),
	)
);
control_check( 2 === substr_count( $select, ' selected' ), 'Multiple selected values include numeric zero.' );
control_check( false !== strpos( $select, '&lt;Math&gt;' ), 'Option labels are escaped.' );
control_check( false !== strpos( $select, 'value="closed" disabled' ), 'Disabled options remain native.' );
$placeholder = Ui::select(
	array(
		'placeholder' => 'Choose',
		'required'    => true,
		'value'       => '',
		'options'     => array(
			array(
				'value' => 'a',
				'label' => 'A',
			),
		),
	)
);
control_check( false !== strpos( $placeholder, '<option value="" selected>Choose</option>' ), 'A single-select placeholder uses an empty native value.' );
control_check( false === strpos( $placeholder, ' placeholder=' ), 'Select does not receive an invalid placeholder attribute.' );
echo "Shared control escaping and semantics checks passed.\n";
