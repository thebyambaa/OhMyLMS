<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Presentation templates for the shared quiz delivery engine.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Quiz;

defined( 'ABSPATH' ) || exit;

/** Registered designs share forms, grading, timing and navigation. */
final class PlayerTemplates {

	/** Design used when a quiz has not chosen one. */
	const DEFAULT_TEMPLATE = 'playful';

	/**
	 * Return safe presentation definitions, including extension registrations.
	 *
	 * @return array
	 */
	public static function all() {
		$defaults   = array(
			'playful' => array(
				'label'       => __( 'Playful', 'ohmylms' ),
				'description' => __( 'A bright white stage with green and blue answers and chunky buttons. The default design.', 'ohmylms' ),
			),
			'classic' => array(
				'label'       => __( 'Classic', 'ohmylms' ),
				'description' => __( 'Dark question stage and colorful answer cards.', 'ohmylms' ),
			),
			'paper'   => array(
				'label'       => __( 'Paper', 'ohmylms' ),
				'description' => __( 'A clear white exam paper with restrained colors.', 'ohmylms' ),
			),
			'focus'   => array(
				'label'       => __( 'Focus', 'ohmylms' ),
				'description' => __( 'A calm dark stage with a single accent color.', 'ohmylms' ),
			),
		);
		$registered = apply_filters( 'ohmylms_quiz_player_templates', $defaults );
		$templates  = $defaults;
		foreach ( is_array( $registered ) ? $registered : array() as $id => $definition ) {
			if ( ! is_string( $id ) || ! preg_match( '/^[a-z][a-z0-9-]{0,63}$/', $id ) || ! is_array( $definition ) || ! is_string( $definition['label'] ?? null ) ) {
				continue;
			}
			$templates[ $id ] = array(
				'label'       => sanitize_text_field( $definition['label'] ),
				'description' => sanitize_text_field( is_string( $definition['description'] ?? null ) ? $definition['description'] : '' ),
				'stylesheet'  => sanitize_key( is_string( $definition['stylesheet'] ?? null ) ? $definition['stylesheet'] : '' ),
				'profile'     => sanitize_key( is_string( $definition['profile'] ?? null ) ? $definition['profile'] : '' ),
			);
		}
		return $templates;
	}

	/**
	 * Resolve saved names without accepting paths or executable template data.
	 *
	 * @param mixed $selected Saved template identifier.
	 * @return string
	 */
	public static function resolve( $selected ) {
		return is_string( $selected ) && isset( self::all()[ $selected ] ) ? $selected : self::DEFAULT_TEMPLATE;
	}

	/**
	 * Public editor choices omit implementation details.
	 *
	 * @return array
	 */
	public static function choices() {
		$choices = array();
		foreach ( self::all() as $id => $definition ) {
			// A design brought by an assessment profile is applied by that profile, not chosen per quiz.
			if ( ! empty( $definition['profile'] ) ) {
				continue;
			}
			$choices[] = array(
				'value'       => $id,
				'label'       => $definition['label'],
				'description' => $definition['description'],
			);
		}
		return $choices;
	}

	/**
	 * Enqueue the shared stage and an optional registered extension stylesheet.
	 *
	 * @param string $template Resolved template identifier.
	 */
	public static function enqueue( $template ) {
		wp_enqueue_style( 'ohmylms-player-templates', plugins_url( 'assets/css/quiz-player-templates.css', OHMYLMS_FILE ), array( 'ohmylms-frontend' ), (string) filemtime( OHMYLMS_DIR . '/assets/css/quiz-player-templates.css' ) );
		$definition = self::all()[ self::resolve( $template ) ];
		if ( ! empty( $definition['stylesheet'] ) ) {
			wp_enqueue_style( $definition['stylesheet'] );
		}
	}

	/**
	 * Render an overridable presentation part; delivery controls stay shared.
	 *
	 * @param string $part Header, progress or question metadata.
	 * @param string $template Resolved template identifier.
	 * @param array  $args Escaped by the receiving template.
	 */
	public static function part( $part, $template, array $args ) {
		if ( ! in_array( $part, array( 'header', 'progress', 'question-meta', 'footer' ), true ) ) {
			return;
		}
		$path = locate_template( 'ohmylms/quiz-player/' . self::resolve( $template ) . '/' . $part . '.php' );
		if ( $path ) {
			// Only fixed part names and registered identifiers can select a theme file.
			$args['player_template'] = self::resolve( $template );
			load_template( $path, false, $args );
			return;
		}
		$resolved = self::resolve( $template );
		// A design may ship its own part inside the plugin; the footer exists only for designs that add one.
		if ( file_exists( OHMYLMS_DIR . '/templates/quiz-player/' . $resolved . '/' . $part . '.php' ) ) {
			ohmylms_get_template( 'quiz-player/' . $resolved . '/' . $part . '.php', $args );
			return;
		}
		if ( 'footer' !== $part ) {
			ohmylms_get_template( 'quiz-player/' . $part . '.php', $args );
		}
	}
}
