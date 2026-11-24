<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Extensions;

/**
 * Plugin authoring implementation.
 */
final class Authoring {
	/**
	 * Init for Authoring.
	 */
	public static function init() {
		add_action( 'ohmylms_before_creating_new_question', array( __CLASS__, 'validate_question' ) );
		add_action( 'ohmylms_before_updating_question', array( __CLASS__, 'validate_question' ) );
	}

	/**
	 * Validate question for Authoring.
	 *
	 * @param mixed $question Question.
	 * @throws \OhMyLMS\DataException When the operation cannot be completed.
	 */
	public static function validate_question( $question ) {
		$settings   = $question->get_settings();
		$definition = Registry::get( 'question', $settings['type'] ?? '' );
		if ( \OhMyLMS\Assessment\Template::has( $settings ) ) {
			// A template is valid when real examples of it are valid questions of its type.
			$result = \OhMyLMS\Assessment\Template::validate(
				$settings,
				$question->get_name(),
				$question->get_description(),
				$question->get_questions(),
				static function ( $example ) use ( $definition ) {
					return self::check( $definition, $example );
				}
			);
		} else {
			$result = self::check( $definition, $settings );
		}
		if ( true !== $result ) {
			throw new \OhMyLMS\DataException( 'ohmylms_invalid_question_settings', is_string( $result ) ? $result : self::fallback_message( $definition ), 400 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Callers convert it to REST error data.
		}
	}

	/**
	 * Run a type's own settings checks on a settings array.
	 *
	 * @return true|string
	 * @param mixed $definition Definition.
	 * @param array $settings Configuration settings.
	 */
	public static function check( $definition, array $settings ) {
		if ( ! empty( $definition['validate_settings'] ) && is_callable( $definition['validate_settings'] ) ) {
			$result = call_user_func( $definition['validate_settings'], $settings );
			if ( true !== $result ) {
				if ( is_wp_error( $result ) ) {
					return $result->get_error_message();
				}
				return is_string( $result ) ? $result : self::fallback_message( $definition );
			}
		}
		$schema = $definition['editor']['schema'] ?? null;
		if ( $schema ) {
			$valid = rest_validate_value_from_schema( $settings, $schema, 'settings' );
			if ( is_wp_error( $valid ) ) {
				return $valid->get_error_message();
			}
		}
		return true;
	}

	/**
	 * Message for a type whose settings check failed without saying why.
	 *
	 * @param mixed $definition Question type definition.
	 * @return string
	 */
	private static function fallback_message( $definition ) {
		$label = is_array( $definition ) && ! empty( $definition['label'] ) ? (string) $definition['label'] : '';
		if ( '' === $label ) {
			return __( 'Complete the required settings for this question type.', 'ohmylms' );
		}
		/* translators: %s: question type name. */
		return sprintf( __( 'Complete the required settings for the "%s" question type.', 'ohmylms' ), $label );
	}
}
