<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Settings registry: the single source for settings groups, fields, schema and defaults.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Describes every setting exposed by `ohmylms/v1/settings/<group>`.
 *
 * Each field maps a clean REST name to a WordPress option (or to one member of an array option),
 * so existing readers (`get_option( 'ohmylms_currency' )` and friends) keep working. A field has:
 *
 * - `schema`: JSON Schema (draft 4 subset understood by rest_validate_value_from_schema()).
 * - `default`: REST-shaped default, used while the option does not exist.
 * - `store`: how the REST value is persisted: `plain`, `int`, `yes_no`, `decimal`, `tax_rates`.
 * - `kind`: extra semantic validation/sanitising: `text`, `page`, `color`, `url`, `html`, `slug`.
 * - `option`: option name (default `ohmylms_<field>`), and optional `member` for array options.
 *
 * @since 1.0.0
 */
final class SettingsRegistry {

	/**
	 * Option names that persist a key of an array option.
	 */
	const PERMALINK_OPTION = 'ohmylms_permalink';

	/**
	 * Group ids in display order.
	 *
	 * @return string[]
	 */
	public static function group_ids() {
		return array_keys( self::groups() );
	}

	/**
	 * Whether a group exists.
	 *
	 * @param string $group Group id.
	 * @return bool
	 */
	public static function has_group( $group ) {
		return in_array( $group, self::group_ids(), true );
	}

	/**
	 * Get one group definition.
	 *
	 * @param string $group Group id.
	 * @return array|null
	 */
	public static function group( $group ) {
		$groups = self::groups();

		return isset( $groups[ $group ] ) ? $groups[ $group ] : null;
	}

	/**
	 * Build a page-id field.
	 *
	 * @param string $description Field description.
	 * @return array
	 */
	private static function page( $description ) {
		return array(
			'schema'  => array(
				'description' => $description,
				'type'        => 'integer',
				'minimum'     => 0,
			),
			'default' => 0,
			'store'   => 'int',
			'kind'    => 'page',
		);
	}

	/**
	 * Build a hex colour field.
	 *
	 * @param string $description Field description.
	 * @param string $fallback     Default colour.
	 * @param string $option      Option name.
	 * @return array
	 */
	private static function color( $description, $fallback, $option ) {
		return array(
			'schema'  => array(
				'description' => $description,
				'type'        => 'string',
				'pattern'     => '^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$',
				'maxLength'   => 7,
			),
			'default' => $fallback,
			'store'   => 'plain',
			'kind'    => 'color',
			'option'  => $option,
		);
	}

	/**
	 * Build a yes/no boolean field.
	 *
	 * @param string $description Field description.
	 * @param bool   $fallback     Default value.
	 * @return array
	 */
	private static function toggle( $description, $fallback ) {
		return array(
			'schema'  => array(
				'description' => $description,
				'type'        => 'boolean',
			),
			'default' => $fallback,
			'store'   => 'yes_no',
		);
	}

	/**
	 * Build a permalink slug field.
	 *
	 * @param string $description Field description.
	 * @param string $fallback     Default slug.
	 * @return array
	 */
	private static function slug( $description, $fallback ) {
		return array(
			'schema'  => array(
				'description' => $description,
				'type'        => 'string',
				'minLength'   => 1,
				'maxLength'   => 60,
				'pattern'     => '^[a-z0-9]+([_-][a-z0-9]+)*(/[a-z0-9]+([_-][a-z0-9]+)*)*$',
			),
			'default' => $fallback,
			'store'   => 'plain',
			'kind'    => 'slug',
			'option'  => self::PERMALINK_OPTION,
		);
	}

	/**
	 * Group definitions.
	 *
	 * @return array<string, array>
	 */
	public static function groups() {
		$permalink = array(
			'course_base'     => self::slug( __( 'Course URL base.', 'ohmylms' ), 'ohmylms-courses' ),
			'lesson_base'     => self::slug( __( 'Lesson URL base.', 'ohmylms' ), 'course-lessons' ),
			'quiz_base'       => self::slug( __( 'Quiz URL base.', 'ohmylms' ), 'course-quizzes' ),
			'category_base'   => self::slug( __( 'Course category URL base.', 'ohmylms' ), 'course-categories' ),
			'tag_base'        => self::slug( __( 'Course tag URL base.', 'ohmylms' ), 'course-tags' ),
			'membership_base' => self::slug( __( 'Membership URL base.', 'ohmylms' ), 'members' ),
		);
		foreach ( $permalink as $name => $field ) {
			$permalink[ $name ]['member'] = $name;
		}

		return array(
			'general'             => array(
				'label'       => __( 'General', 'ohmylms' ),
				'description' => __( 'Pages the LMS links to.', 'ohmylms' ),
				'fields'      => array(
					'course_page_id'            => self::page( __( 'Course archive page.', 'ohmylms' ) ),
					'checkout_page_id'          => self::page( __( 'Checkout page.', 'ohmylms' ) ),
					'thank_you_page_id'         => self::page( __( 'Thank you page shown after checkout.', 'ohmylms' ) ),
					'student_dashboard_page_id' => self::page( __( 'Student dashboard page.', 'ohmylms' ) ),
					'student_profile_page_id'   => self::page( __( 'Student profile page.', 'ohmylms' ) ),
					'student_courses_page_id'   => self::page( __( 'Student courses page.', 'ohmylms' ) ),
					'registration_page_id'      => self::page( __( 'Registration page.', 'ohmylms' ) ),
					'terms_page_id'             => self::page( __( 'Terms and conditions page.', 'ohmylms' ) ),
					'privacy_policy_page_id'    => self::page( __( 'Privacy policy page.', 'ohmylms' ) ),
					'profile_page_id'           => self::page( __( 'Membership profile page (used by memberships).', 'ohmylms' ) ),
				),
			),
			'branding'            => array(
				'label'       => __( 'Branding', 'ohmylms' ),
				'description' => __( 'Brand colours and the video player logo.', 'ohmylms' ),
				'fields'      => array(
					'color_preset'               => array(
						'schema'  => array(
							'description' => __( 'Name of the colour preset in use.', 'ohmylms' ),
							'type'        => 'string',
							'pattern'     => '^[a-z0-9]+(-[a-z0-9]+)*$',
							'maxLength'   => 40,
						),
						'default' => 'royal-purple',
						'store'   => 'plain',
						'kind'    => 'text',
					),
					'primary_color'              => self::color( __( 'Primary colour.', 'ohmylms' ), '#6e42d3', 'ohmylms_primary_color_scheme' ),
					'primary_hover_color'        => self::color( __( 'Primary hover colour.', 'ohmylms' ), '#1447bc', 'ohmylms_primary_hover_color_scheme' ),
					'heading_color'              => self::color( __( 'Heading colour.', 'ohmylms' ), '#000d25', 'ohmylms_heading_color_scheme' ),
					'body_text_color'            => self::color( __( 'Body text colour.', 'ohmylms' ), '#52525b', 'ohmylms_body_text_color_scheme' ),
					'progress_color'             => self::color( __( 'Progress bar colour.', 'ohmylms' ), '#35bd4c', 'ohmylms_body_progress_color_scheme' ),
					'video_player_logo'          => array(
						'schema'  => array(
							'description' => __( 'Logo URL shown in the custom video player (empty for none).', 'ohmylms' ),
							'type'        => 'string',
							'maxLength'   => 2048,
						),
						'default' => '',
						'store'   => 'plain',
						'kind'    => 'url',
					),
					'video_player_logo_bg_color' => self::color( __( 'Video player logo background colour.', 'ohmylms' ), '#6e42d3', 'ohmylms_video_player_logo_bg_color' ),
				),
			),
			'account-and-privacy' => array(
				'label'       => __( 'Account and privacy', 'ohmylms' ),
				'description' => __( 'Checkout accounts and privacy wording.', 'ohmylms' ),
				'fields'      => array(
					'allow_purchase_without_login' => self::toggle( __( 'Allow visitors to purchase without an account.', 'ohmylms' ), true ),
					'require_email_verification'   => self::toggle( __( 'Students must verify their email address.', 'ohmylms' ), false ),
					'checkout_phone_field'         => array(
						'schema'  => array(
							'description' => __( 'Billing phone field behaviour.', 'ohmylms' ),
							'type'        => 'string',
							'enum'        => array( 'optional', 'required', 'hidden' ),
						),
						'default' => 'optional',
						'store'   => 'plain',
						'kind'    => 'text',
					),
					'privacy_policy_message'       => array(
						'schema'  => array(
							'description' => __( 'Privacy note shown at checkout (basic HTML allowed; [privacy_policy] links the policy page).', 'ohmylms' ),
							'type'        => 'string',
							'maxLength'   => 2000,
						),
						'default' => 'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our [privacy_policy].',
						'store'   => 'plain',
						'kind'    => 'html',
					),
				),
			),
			'permalink'           => array(
				'label'       => __( 'Permalinks', 'ohmylms' ),
				'description' => __( 'URL bases of LMS content. Changing them refreshes rewrite rules on the next page load.', 'ohmylms' ),
				'fields'      => $permalink,
			),
			'advanced'            => array(
				'label'       => __( 'Advanced', 'ohmylms' ),
				'description' => __( 'Lesson player behaviour.', 'ohmylms' ),
				'fields'      => array(
					'use_custom_video_player' => self::toggle( __( 'Use the custom video player for YouTube, Vimeo and self-hosted videos.', 'ohmylms' ), true ),
				),
			),
			'currency'            => array(
				'label'       => __( 'Currency', 'ohmylms' ),
				'description' => __( 'How prices are formatted.', 'ohmylms' ),
				'fields'      => array(
					'currency'           => array(
						'schema'  => array(
							'description' => __( 'ISO 4217 currency code.', 'ohmylms' ),
							'type'        => 'string',
							'pattern'     => '^[A-Z]{3}$',
						),
						'default' => 'USD',
						'store'   => 'plain',
						'kind'    => 'currency',
					),
					'currency_pos'       => array(
						'schema'  => array(
							'description' => __( 'Currency symbol position.', 'ohmylms' ),
							'type'        => 'string',
							'enum'        => array( 'left', 'right', 'left_space', 'right_space' ),
						),
						'default' => 'left',
						'store'   => 'plain',
						'kind'    => 'text',
					),
					'price_thousand_sep' => array(
						'schema'  => array(
							'description' => __( 'Thousands separator (empty for none).', 'ohmylms' ),
							'type'        => 'string',
							'maxLength'   => 1,
							'pattern'     => '^[^0-9<>&"\']?$',
						),
						'default' => ',',
						'store'   => 'plain',
						'kind'    => 'text',
					),
					'price_decimal_sep'  => array(
						'schema'  => array(
							'description' => __( 'Decimal separator.', 'ohmylms' ),
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => 1,
							'pattern'     => '^[^0-9<>&"\']$',
						),
						'default' => '.',
						'store'   => 'plain',
						'kind'    => 'text',
					),
					'price_num_decimals' => array(
						'schema'  => array(
							'description' => __( 'Number of decimals.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 0,
							'maximum'     => 8,
						),
						'default' => 2,
						'store'   => 'int',
					),
				),
			),
			'tax'                 => array(
				'label'       => __( 'Tax', 'ohmylms' ),
				'description' => __( 'Tax switches, labels and rates.', 'ohmylms' ),
				'fields'      => array(
					'tax_enabled'            => self::toggle( __( 'Calculate tax at checkout.', 'ohmylms' ), false ),
					'tax_label'              => array(
						'schema'  => array(
							'description' => __( 'Label shown for tax.', 'ohmylms' ),
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => 50,
						),
						'default' => 'Tax',
						'store'   => 'plain',
						'kind'    => 'text',
					),
					'prices_include_tax'     => self::toggle( __( 'Entered prices already include tax.', 'ohmylms' ), false ),
					'eu_vat_enabled'         => self::toggle( __( 'Apply EU VAT rates to customers in the European Union.', 'ohmylms' ), false ),
					'disable_vat_validation' => self::toggle( __( 'Skip VAT number validation.', 'ohmylms' ), false ),
					'vat_number_label'       => array(
						'schema'  => array(
							'description' => __( 'Label of the VAT number field.', 'ohmylms' ),
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => 50,
						),
						'default' => 'VAT Number',
						'store'   => 'plain',
						'kind'    => 'text',
					),
					'fallback_tax_rate'      => array(
						'schema'  => array(
							'description' => __( 'Percentage charged where no rate matches.', 'ohmylms' ),
							'type'        => 'number',
							'minimum'     => 0,
							'maximum'     => 100,
						),
						'default' => 0,
						'store'   => 'decimal',
					),
					'tax_rates'              => array(
						'schema'  => array(
							'description' => __( 'Tax rates by country and optional state.', 'ohmylms' ),
							'type'        => 'array',
							'maxItems'    => 500,
							'items'       => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'required'             => array( 'country', 'rate' ),
								'properties'           => array(
									'country'      => array(
										'type'    => 'string',
										'pattern' => '^[A-Z]{2}$',
									),
									'state'        => array(
										'type'      => 'string',
										'maxLength' => 10,
										'pattern'   => '^[A-Za-z0-9-]*$',
									),
									'rate'         => array(
										'type'    => 'number',
										'minimum' => 0,
										'maximum' => 100,
									),
									'country_wide' => array( 'type' => 'boolean' ),
								),
							),
						),
						'default' => array(),
						'store'   => 'tax_rates',
						'option'  => 'ohmylms_tax_rates',
					),
				),
			),
		);
	}

	/**
	 * Option name of a field.
	 *
	 * @param string $name  Field name.
	 * @param array  $field Field definition.
	 * @return string
	 */
	public static function option_name( $name, array $field ) {
		return isset( $field['option'] ) ? $field['option'] : 'ohmylms_' . $name;
	}

	/**
	 * Machine-readable schema of a group (JSON Schema draft 4).
	 *
	 * @param string $group Group id.
	 * @return array
	 */
	public static function schema( $group ) {
		$definition = self::group( $group );
		$properties = array();
		if ( $definition ) {
			foreach ( $definition['fields'] as $name => $field ) {
				$properties[ $name ]            = $field['schema'];
				$properties[ $name ]['default'] = $field['default'];
				$properties[ $name ]['context'] = array( 'view', 'edit' );
			}
		}

		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'settings-' . $group,
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => $properties,
		);
	}
}
