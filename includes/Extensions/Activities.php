<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Built-in interactive activities for lesson content.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Extensions;

/**
 * Built-in interactive activities for lesson content.
 *
 * Authors insert them from the editor's slash menu as a shortcode, e.g.
 * [ohmylms_activity type="reveal" prompt="Question" answer="Answer"]. The shortcode is the only
 * thing stored; the interactive markup is generated at render time, so content filtering on save
 * cannot strip it. The behaviour runs on the WordPress Interactivity API (assets/extensions/*.js).
 */
final class Activities {
	/**
	 * Register defaults for Activities.
	 */
	public static function register_defaults() {
		Registry::register(
			'activity',
			'reveal',
			array(
				'label'  => 'Reveal card',
				'render' => array( __CLASS__, 'render_reveal' ),
			)
		);
	}

	/**
	 * Render reveal for Activities.
	 *
	 * @param array $data Input data.
	 */
	public static function render_reveal( array $data ) {
		$prompt = isset( $data['prompt'] ) ? (string) $data['prompt'] : '';
		$answer = isset( $data['answer'] ) ? (string) $data['answer'] : '';
		$id     = wp_unique_id( 'ohmylms-reveal-' );
		wp_enqueue_style( 'ohmylms-reveal', plugins_url( 'assets/extensions/reveal.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION );
		wp_register_script_module( 'ohmylms/reveal', plugins_url( 'assets/extensions/reveal.js', OHMYLMS_FILE ), array( '@wordpress/interactivity' ), OHMYLMS_VERSION );
		Interactivity::enqueue( 'ohmylms/reveal' );
		?>
		<div class="ohmylms-reveal" data-wp-interactive="ohmylms/reveal" <?php echo wp_interactivity_data_wp_context( array( 'open' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<div class="ohmylms-reveal__prompt"><?php echo wp_kses_post( $prompt ); ?></div>
			<button type="button" class="ohmylms-reveal__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>"
				data-wp-on--click="actions.toggle" data-wp-bind--aria-expanded="context.open">
				<span data-wp-bind--hidden="context.open"><?php esc_html_e( 'Show answer', 'ohmylms' ); ?></span>
				<span hidden data-wp-bind--hidden="!context.open"><?php esc_html_e( 'Hide answer', 'ohmylms' ); ?></span>
			</button>
			<div class="ohmylms-reveal__answer" id="<?php echo esc_attr( $id ); ?>" hidden data-wp-bind--hidden="!context.open"><?php echo wp_kses_post( $answer ); ?></div>
		</div>
		<?php
	}
}
