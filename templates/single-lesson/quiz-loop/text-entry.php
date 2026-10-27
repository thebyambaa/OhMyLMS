<?php
/**
 * Automatically marked text input; accepted answers are never rendered.
 *
 * @package OhMyLMS\Templates
 */

defined( 'ABSPATH' ) || exit;
$entry_id = 'ohmylms-text-entry-' . (int) $question['id'];
?>
<label for="<?php echo esc_attr( $entry_id ); ?>"><?php esc_html_e( 'Your answer', 'ohmylms' ); ?></label>
<input id="<?php echo esc_attr( $entry_id ); ?>" type="text" autocomplete="off" maxlength="2000" name="<?php echo esc_attr( \OhMyLMS\Assessment\Interactive::field_name( $attempt, $question ) ); ?>" data-wp-on--input="ohmylms/quiz::actions.answerChanged">
