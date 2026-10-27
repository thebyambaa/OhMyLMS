<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Small native authoring surface; existing course builder remains compatible.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Extensions;

/** Small native authoring surface; existing course builder remains compatible. */
final class Editor {
	/** Keep the legacy editor reachable through existing links without a submenu entry. */
	public static function init() {
		add_action(
			'admin_menu',
			static function () {
				add_submenu_page( 'options.php', __( 'Layouts & extensions', 'ohmylms' ), __( 'Layouts & extensions', 'ohmylms' ), 'edit_posts', 'ohmylms-extensions', array( __CLASS__, 'page' ) );
			},
			99
		);
	}
	/**
	 * Select for Editor.
	 *
	 * @param mixed $name Name.
	 * @param mixed $selected Selected.
	 * @param mixed $context Context.
	 */
	private static function select( $name, $selected, $context ) {
		echo '<select name="' . esc_attr( $name ) . '">';
		$options = array(
			''        => 'Inherit',
			'default' => 'Built-in default',
		);
		foreach ( Registry::all( 'layout' ) as $id => $definition ) {
			if ( in_array( $context, $definition['contexts'], true ) ) {
				$options[ $id ] = $definition['label'];
			}
		}
		foreach ( $options as $id => $label ) {
			echo '<option value="' . esc_attr( $id ) . '" ' . selected( $selected, $id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}
	/**
	 * Page for Editor.
	 */
	public static function page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Access denied.' );
		}
		$id   = absint( $_REQUEST['content_id'] ?? 0 );
		$post = $id ? get_post( $id ) : null;
		echo '<div class="wrap"><h1>OhMyLMS layouts & extensions</h1><form method="get"><input type="hidden" name="page" value="ohmylms-extensions"><label>Content ID <input type="number" min="1" name="content_id" value="' . esc_attr( $id ) . '"></label> <button class="button">Load</button></form>';
		if ( ! $post ) {
			echo '<p>Enter a course, chapter, lesson, quiz, or question ID from the course builder.</p></div>';
			return;}
		$context = str_replace( 'ohmylms-', '', $post->post_type );
		if ( ! in_array( $context, array( 'course', 'chapter', 'lesson', 'quiz', 'question' ), true ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( 'Access denied.' );
		}
		if ( isset( $_POST['ohmylms_save'] ) ) {
			check_admin_referer( 'ohmylms_extension_settings' );
			if ( 'question' === $context ) {
				$settings = json_decode( wp_unslash( $_POST['settings'] ?? '' ), true );
				$type     = Registry::get( 'question', $settings['type'] ?? '' );
				if ( ! is_array( $settings ) || ! $type ) {
					echo '<p role="alert">Invalid question settings or unknown question type.</p>';} else {
					update_post_meta( $id, '_question_settings', map_deep( $settings, 'sanitize_textarea_field' ) );
					echo '<p>Question settings saved.</p>';}
			} else {
				$layout = sanitize_key( $_POST['layout'] ?? '' );
				if ( self::valid_layout( $layout, $context ) ) {
					update_post_meta( $id, '_ohmylms_layout', $layout );
				}
				if ( 'course' === $context ) {
					$defaults = array();
					foreach ( array( 'course', 'chapter', 'lesson', 'quiz' ) as $kind ) {
						$value = sanitize_key( $_POST['defaults'][ $kind ] ?? '' );
						if ( self::valid_layout( $value, $kind ) ) {
							$defaults[ $kind ] = $value;
						}
					}
					update_post_meta( $id, '_ohmylms_layout_defaults', $defaults );
				}
				echo '<p>Layout settings saved.</p>';
			}
		}
		echo '<h2>' . esc_html( $post->post_title ) . ' (#' . esc_html( $id ) . ')</h2><form method="post">';
		wp_nonce_field( 'ohmylms_extension_settings' );
		echo '<input type="hidden" name="content_id" value="' . esc_attr( $id ) . '">';
		if ( 'question' === $context ) {
			echo '<p>Registered question types: ' . esc_html( implode( ', ', array_keys( Registry::all( 'question' ) ) ) ) . '</p>';
			echo '<label>Question settings (JSON)<br><textarea name="settings" rows="14" cols="90">' . esc_textarea( wp_json_encode( get_post_meta( $id, '_question_settings', true ), JSON_PRETTY_PRINT ) ) . '</textarea></label>';
			echo '<p>Keep score and required settings. Extension-specific editor schemas are available through the extensions API.</p>';
		} else {
			echo '<p>Layout: ';
			self::select( 'layout', get_post_meta( $id, '_ohmylms_layout', true ), $context );
			echo '</p>';
			if ( 'course' === $context ) {
				foreach ( array( 'course', 'chapter', 'lesson', 'quiz' ) as $kind ) {
					$defaults = (array) get_post_meta( $id, '_ohmylms_layout_defaults', true );
					echo '<p>' . esc_html( ucfirst( $kind ) ) . ' default: ';
					self::select( 'defaults[' . $kind . ']', $defaults[ $kind ] ?? '', $kind );
					echo '</p>';
				}
			}
		}
		submit_button( 'Save settings', 'primary', 'ohmylms_save' );
		echo '</form></div>';
	}
	/**
	 * Valid layout for Editor.
	 *
	 * @param mixed $id Id.
	 * @param mixed $context Context.
	 */
	private static function valid_layout( $id, $context ) {
		$definition = Registry::get( 'layout', $id );
		return in_array( $id, array( '', 'default' ), true ) || ( $definition && in_array( $context, $definition['contexts'], true ) );
	}
}
