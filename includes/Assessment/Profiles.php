<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Assessment profiles: how one kind of assessment looks and behaves on the shared quiz engine.
 *
 * A quiz, a practice set and an exam are the same stored thing: one quiz post, the same question
 * links, revisions, attempts and grading. They differ only in a profile, chosen by the assessment
 * kind saved with the quiz. A profile names the player design and layout, the blocks the editor
 * offers, the list the author returns to, an editor panel, and optional hooks for the player's
 * context. Shared code asks Profiles::of( $quiz_id ); it never tests for a particular kind.
 *
 * Add a kind with the `ohmylms_assessment_profiles` filter. See docs/ASSESSMENT-PROFILES.md.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/** Registry of assessment profiles, keyed by assessment kind. */
final class Profiles {

	/** Layouts the quiz form can render. */
	const LAYOUTS = array( 'one_question_per_page', 'all_questions_in_one_page', 'number_of_questions_per_page' );

	/**
	 * Resolved profiles, built once per request.
	 *
	 * @var array|null
	 */
	private static $all = null;

	/** Join the player design registry and the player's script context. */
	public static function init() {
		add_filter( 'ohmylms_quiz_player_templates', array( __CLASS__, 'templates' ) );
		add_filter( 'ohmylms_quiz_interactivity_context', array( __CLASS__, 'context' ), 5 );
	}

	/**
	 * Every registered profile, normalized.
	 *
	 * @return array[] Profiles keyed by kind.
	 */
	public static function all() {
		if ( null !== self::$all ) {
			return self::$all;
		}
		$defaults = array(
			'quiz'     => array( 'label' => __( 'Quiz', 'ohmylms' ) ),
			'practice' => array( 'label' => __( 'Practice', 'ohmylms' ) ),
		);
		$profiles = array();
		foreach ( (array) apply_filters( 'ohmylms_assessment_profiles', $defaults ) as $kind => $definition ) {
			if ( is_string( $kind ) && preg_match( '/^[a-z][a-z0-9-]{0,39}$/', $kind ) && is_array( $definition ) ) {
				$profiles[ $kind ] = self::normalize( $kind, $definition );
			}
		}
		// Quiz is the fallback every other profile can rely on.
		self::$all = $profiles + array( 'quiz' => self::normalize( 'quiz', $defaults['quiz'] ) );
		return self::$all;
	}

	/**
	 * Registered kinds, for validating the kind saved with a quiz.
	 *
	 * @return string[]
	 */
	public static function kinds() {
		return array_keys( self::all() );
	}

	/**
	 * Profile of one kind, falling back to the plain quiz profile.
	 *
	 * @param string $kind Assessment kind.
	 * @return array
	 */
	public static function get( $kind ) {
		$all = self::all();
		return $all[ $kind ] ?? $all['quiz'];
	}

	/**
	 * Profile of a quiz, from the kind saved with it.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array
	 */
	public static function of( $quiz_id ) {
		return self::get( (string) AssessmentSettings::get( (int) $quiz_id )['kind'] );
	}

	/**
	 * Whether a quiz's kind allows a delivery.
	 *
	 * @param int    $quiz_id Quiz ID.
	 * @param string $delivery quiz, inline or practice.
	 * @return bool
	 */
	public static function allows( $quiz_id, $delivery ) {
		return in_array( $delivery, self::of( $quiz_id )['deliveries'], true );
	}

	/**
	 * What the quiz editor needs about a quiz's profile.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array id, the deliveries the kind allows (the question blocks use them to offer sources), and the
	 *               panel id and its data, empty when the profile adds no panel.
	 */
	public static function editor( $quiz_id ) {
		$profile = self::of( $quiz_id );
		return array(
			'id'         => $profile['kind'],
			'deliveries' => $profile['deliveries'],
			'panel'      => $profile['panel'],
			'data'       => '' !== $profile['panel'] && is_callable( $profile['editor_data'] ) ? (array) call_user_func( $profile['editor_data'], (int) $quiz_id ) : array(),
		);
	}

	/**
	 * Add each profile's own player designs to the design registry, hidden from the quiz picker.
	 *
	 * @param array $templates Registered designs.
	 * @return array
	 */
	public static function templates( $templates ) {
		foreach ( self::all() as $kind => $profile ) {
			foreach ( $profile['templates'] as $id => $definition ) {
				$templates[ $id ] = $definition + array( 'profile' => $kind );
			}
		}
		return $templates;
	}

	/**
	 * Let the quiz's profile add to the context the player script starts with.
	 *
	 * @param array $context Player context.
	 * @return array
	 */
	public static function context( $context ) {
		$quiz_id = (int) ( $context['quizId'] ?? 0 );
		$profile = $quiz_id ? self::of( $quiz_id ) : null;
		if ( $profile && is_callable( $profile['context'] ) ) {
			$context = (array) call_user_func( $profile['context'], $context, $quiz_id );
		}
		return $context;
	}

	/**
	 * Fill in and bound one profile definition.
	 *
	 * @param string $kind Assessment kind.
	 * @param array  $definition Registered definition.
	 * @return array
	 */
	private static function normalize( $kind, array $definition ) {
		$profile                    = array_merge(
			array(
				'label'           => ucfirst( $kind ),
				// Player design and layout this kind always uses; empty leaves them to each quiz.
				'player_template' => '',
				'layout'          => '',
				// Deliveries this kind's questions may use; the editor offers only the blocks that match.
				'deliveries'      => DeliveryPolicy::ALL,
				// Hash route of the list the author returns to from the editor.
				'return_route'    => '/content-hub/quizzes',
				// Whether answers count as skill evidence; the administrator can change it per kind.
				'skills'          => true,
				// Editor panel id and a callable giving it data, or empty for no panel.
				'panel'           => '',
				'editor_data'     => null,
				// Callable( $context, $quiz_id ) returning the player script's context.
				'context'         => null,
				// Player designs this kind brings: id => label, description, stylesheet handle.
				'templates'       => array(),
			),
			$definition
		);
		$profile['kind']            = $kind;
		$profile['player_template'] = is_string( $profile['player_template'] ) ? sanitize_key( $profile['player_template'] ) : '';
		$profile['layout']          = in_array( $profile['layout'], self::LAYOUTS, true ) ? $profile['layout'] : '';
		$deliveries                 = is_array( $profile['deliveries'] ) ? array_values( array_intersect( DeliveryPolicy::ALL, $profile['deliveries'] ) ) : array();
		$profile['deliveries']      = $deliveries ? $deliveries : DeliveryPolicy::ALL;
		$profile['return_route']    = is_string( $profile['return_route'] ) && preg_match( '#^/(?:content-hub|course-edit|question-bank)(?:/|$)#', $profile['return_route'] ) ? $profile['return_route'] : '/content-hub/quizzes';
		$profile['skills']          = (bool) $profile['skills'];
		$profile['panel']           = is_string( $profile['panel'] ) ? sanitize_key( $profile['panel'] ) : '';
		$profile['templates']       = is_array( $profile['templates'] ) ? $profile['templates'] : array();
		return $profile;
	}
}
