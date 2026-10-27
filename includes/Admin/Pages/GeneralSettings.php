<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Admin\Pages;

use OhMyLMS\Abstracts\SettingsPage;

defined( 'ABSPATH' ) || exit;


/**
 * Plugin general settings implementation.
 */
class GeneralSettings extends SettingsPage {


	/**
	 * Initialize General settings.
	 */
	public function __construct() {
		$this->id    = 'general';
		$this->label = __( 'General', 'ohmylms' );

		parent::__construct();
	}


	/**
	 * Get settings for default section for General settings.
	 */
	public function get_settings_for_default_section() {

		$currency_code_options = get_ohmylms_currencies();

		foreach ( $currency_code_options as $code => $name ) {
			$currency_code_options[ $code ] = $name . ' (' . get_ohmylms_currency_symbol( $code ) . ')';
		}

		$settings = array(
			// page settings.
			array(
				'title' => __( 'Page setup', 'ohmylms' ),
				'type'  => 'title',
				'id'    => 'general_page_settings',
			),
			array(
				'title'    => __( 'All course page', 'ohmylms' ),
				'id'       => 'ohmylms_course_page_id',
				'type'     => 'single_select_page_with_search',
				'default'  => '',
				'class'    => 'ohmylms-page-search',
				'css'      => 'min-width:300px;',
				'args'     => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'checkout' ),
							ohmylms_get_page_id( 'myaccount' ),
						),
				),
				'desc_tip' => true,
				'autoload' => false,
			),
			array(
				'title'    => __( 'Student Dashboard Page', 'ohmylms' ),
				'id'       => 'ohmylms_student_dashboard_page_id',
				'type'     => 'single_select_page_with_search',
				'default'  => ohmylms_get_page_id( 'student_dashboard' ),
				'class'    => 'ohmylms-page-search',
				'css'      => 'min-width:300px;',
				'args'     => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'checkout' ),
						),
				),
				'desc_tip' => true,
				'autoload' => false,
			),
			array(
				'title'    => __( 'Student My Profile Page', 'ohmylms' ),
				'id'       => 'ohmylms_student_profile_page_id',
				'type'     => 'single_select_page_with_search',
				'default'  => ohmylms_get_page_id( 'student_profile' ),
				'class'    => 'ohmylms-page-search',
				'css'      => 'min-width:300px;',
				'args'     => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'checkout' ),
						),
				),
				'desc_tip' => true,
				'autoload' => false,
			),
			array(
				'title'    => __( 'Student My Courses Page', 'ohmylms' ),
				'id'       => 'ohmylms_student_courses_page_id',
				'type'     => 'single_select_page_with_search',
				'default'  => ohmylms_get_page_id( 'student_courses' ),
				'class'    => 'ohmylms-page-search',
				'css'      => 'min-width:300px;',
				'args'     => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'checkout' ),
						),
				),
				'desc_tip' => true,
				'autoload' => false,
			),
			array(
				'title'    => __( 'Checkout page', 'ohmylms' ),
				'id'       => 'ohmylms_checkout_page_id',
				'type'     => 'single_select_page_with_search',
				'default'  => '',
				'class'    => 'ohmylms-page-search',
				'css'      => 'min-width:300px;',
				'args'     => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'course' ),
							ohmylms_get_page_id( 'myaccount' ),
						),
				),
				'desc_tip' => true,
				'autoload' => false,
			),
			array(
				'title'    => __( 'Thank you/Order confirm page', 'ohmylms' ),
				'id'       => 'ohmylms_thank_you_page_id',
				'type'     => 'single_select_page_with_search',
				'default'  => '',
				'class'    => 'ohmylms-page-search',
				'css'      => 'min-width:300px;',
				'args'     => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'course' ),
							ohmylms_get_page_id( 'myaccount' ),
							ohmylms_get_page_id( 'checkout' ),
						),
				),
				'desc_tip' => true,
				'autoload' => false,
			),
			array(
				'title'    => __( 'Terms and condition page', 'ohmylms' ),
				'id'       => 'ohmylms_terms_page_id',
				'type'     => 'single_select_page_with_search',
				'default'  => '',
				'class'    => 'ohmylms-page-search',
				'css'      => 'min-width:300px;',
				'args'     => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'checkout' ),
						),
				),
				'desc_tip' => true,
				'autoload' => false,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'general_page_settings',
			),

			// currency settings.
			array(
				'title' => __( 'Currency options', 'ohmylms' ),
				'type'  => 'title',
				'desc'  => __( 'The following options affect how prices are displayed on the frontend.', 'ohmylms' ),
				'id'    => 'pricing_options',
			),

			array(
				'title'    => __( 'Currency', 'ohmylms' ),
				'desc'     => __( 'This controls what currency prices are listed at in the catalog and which currency gateways will take payments in.', 'ohmylms' ),
				'id'       => 'ohmylms_currency',
				'default'  => 'USD',
				'type'     => 'select',
				'class'    => 'ohmylms-select2',
				'desc_tip' => true,
				'options'  => $currency_code_options,
			),

			array(
				'title'    => __( 'Currency position', 'ohmylms' ),
				'desc'     => __( 'This controls the position of the currency symbol.', 'ohmylms' ),
				'id'       => 'ohmylms_currency_pos',
				'class'    => 'ohmylms-select2',
				'default'  => 'left',
				'type'     => 'select',
				'options'  => array(
					'left'        => __( 'Left', 'ohmylms' ),
					'right'       => __( 'Right', 'ohmylms' ),
					'left_space'  => __( 'Left with space', 'ohmylms' ),
					'right_space' => __( 'Right with space', 'ohmylms' ),
				),
				'desc_tip' => true,
			),

			array(
				'title'    => __( 'Thousand separator', 'ohmylms' ),
				'desc'     => __( 'This sets the thousand separator of displayed prices.', 'ohmylms' ),
				'id'       => 'ohmylms_price_thousand_sep',
				'css'      => 'width:50px;',
				'default'  => ',',
				'type'     => 'text',
				'desc_tip' => true,
			),

			array(
				'title'    => __( 'Decimal separator', 'ohmylms' ),
				'desc'     => __( 'This sets the decimal separator of displayed prices.', 'ohmylms' ),
				'id'       => 'ohmylms_price_decimal_sep',
				'css'      => 'width:50px;',
				'default'  => '.',
				'type'     => 'text',
				'desc_tip' => true,
			),

			array(
				'title'             => __( 'Number of decimals', 'ohmylms' ),
				'desc'              => __( 'This sets the number of decimal points shown in displayed prices.', 'ohmylms' ),
				'id'                => 'ohmylms_price_num_decimals',
				'css'               => 'width:50px;',
				'default'           => '2',
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => 0,
					'step' => 1,
				),
			),

			array(
				'type' => 'sectionend',
				'id'   => 'pricing_options',
			),
		);
		return apply_filters( 'ohmylms_settings_pages', $settings );
	}
}
