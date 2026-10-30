<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Definition of the setup wizard steps and their input schemas.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\SetupWizard;

defined( 'ABSPATH' ) || exit;

/**
 * The single source for step ids, order, flags and input schemas.
 *
 * Each step has:
 *
 * - `skippable`: the administrator may skip it.
 * - `available`: false for a step whose feature is not rebuilt yet; it can only be skipped.
 * - `schema`: JSON Schema of the request body (an object without extra properties).
 *
 * @since 1.0.0
 */
final class SetupSteps {

	/**
	 * Administrator experience levels.
	 *
	 * @var string[]
	 */
	const EXPERIENCES = array( 'beginner', 'intermediate', 'experienced' );

	/**
	 * Niches the site can start from.
	 *
	 * @var string[]
	 */
	const NICHES = array( 'digital-marketing-growth', 'tech-skills', 'creator-economy', 'academic-test-prep', 'other' );

	/**
	 * Maximum number of starter topics.
	 */
	const MAX_TOPICS = 10;

	/**
	 * Longest topic name.
	 */
	const MAX_TOPIC_CHARS = 60;

	/**
	 * Step definitions in wizard order.
	 *
	 * @return array<string, array{skippable: bool, available: bool, schema: array}>
	 */
	public static function all() {
		return array(
			'welcome'     => array(
				'skippable' => false,
				'available' => true,
				'schema'    => self::object_schema( array() ),
			),
			'experience'  => array(
				'skippable' => false,
				'available' => true,
				'schema'    => self::object_schema(
					array(
						'experience' => array(
							'description' => __( 'How familiar the administrator is with running an LMS.', 'ohmylms' ),
							'type'        => 'string',
							'enum'        => self::EXPERIENCES,
						),
					),
					array( 'experience' )
				),
			),
			'preferences' => array(
				'skippable' => true,
				'available' => true,
				'schema'    => self::object_schema(
					array(
						'currency'           => array(
							'description' => __( 'ISO 4217 currency code.', 'ohmylms' ),
							'type'        => 'string',
							'pattern'     => '^[A-Z]{3}$',
						),
						'currency_pos'       => array(
							'description' => __( 'Currency symbol position.', 'ohmylms' ),
							'type'        => 'string',
							'enum'        => array( 'left', 'right', 'left_space', 'right_space' ),
						),
						'price_num_decimals' => array(
							'description' => __( 'Number of decimals in prices.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 0,
							'maximum'     => 8,
						),
					),
					array( 'currency' )
				),
			),
			'niche'       => array(
				'skippable' => true,
				'available' => true,
				'schema'    => self::object_schema(
					array(
						'niche'  => array(
							'description' => __( 'The subject area of the site.', 'ohmylms' ),
							'type'        => 'string',
							'enum'        => self::NICHES,
						),
						'topics' => array(
							'description' => __( 'Starter curriculum topics to create (existing names are reused).', 'ohmylms' ),
							'type'        => 'array',
							'maxItems'    => self::MAX_TOPICS,
							'items'       => array(
								'type'      => 'string',
								'minLength' => 1,
								'maxLength' => self::MAX_TOPIC_CHARS,
							),
						),
					),
					array( 'niche' )
				),
			),
			'pages'       => array(
				'skippable' => true,
				'available' => true,
				'schema'    => self::object_schema( array() ),
			),
			'import'      => array(
				'skippable' => true,
				'available' => false,
				'schema'    => self::object_schema( array() ),
			),
		);
	}

	/**
	 * Step ids in order.
	 *
	 * @return string[]
	 */
	public static function ids() {
		return array_keys( self::all() );
	}

	/**
	 * Whether a step id exists.
	 *
	 * @param string $step Step id.
	 * @return bool
	 */
	public static function has( $step ) {
		return in_array( $step, self::ids(), true );
	}

	/**
	 * One step definition.
	 *
	 * @param string $step Step id.
	 * @return array|null
	 */
	public static function get( $step ) {
		$all = self::all();

		return isset( $all[ $step ] ) ? $all[ $step ] : null;
	}

	/**
	 * Build an object schema that rejects unknown properties.
	 *
	 * @param array    $properties Property schemas.
	 * @param string[] $required   Required property names.
	 * @return array
	 */
	private static function object_schema( array $properties, array $required = array() ) {
		$schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => $properties,
		);
		if ( $required ) {
			$schema['required'] = $required;
		}

		return $schema;
	}
}
