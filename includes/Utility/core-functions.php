<?php
/**
 * Core functions.
 *
 * @package OhMyLMS
 */


use function OhMyLMS\Commerce\ecommerce;

/**
 * Get other templates passing attributes and including the file.
 *
 * @param mixed  $template_name Template name.
 * @param array  $args Operation arguments.
 * @param string $template_path Template path.
 * @param string $default_path Default path.
 * @since 1.0.0
 */
function ohmylms_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
	if ( false === strpos( $template_name, '.php' ) ) {
		$template_name .= '.php';
	}

	$template = ohmylms_locate_template( $template_name, $template_path, $default_path );

	// Allow 3rd party plugin filter template file from their plugin.
	$filter_template = apply_filters( 'ohmylms_get_template', $template, $template_name, $args, $template_path, $default_path );

	if ( $filter_template !== $template ) {
		if ( ! file_exists( $filter_template ) ) {
			/* translators: %s template */
			_doing_it_wrong( __FUNCTION__, sprintf( __( '%s does not exist.', 'ohmylms' ), '<code>' . $filter_template . '</code>' ), '1.0' );
			return;
		}
		$template = $filter_template;
	}

	$action_args = array(
		'template_name' => $template_name,
		'template_path' => $template_path,
		'located'       => $template,
		'args'          => $args,
	);

	if ( ! empty( $args ) && is_array( $args ) ) {
		if ( isset( $args['action_args'] ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				__( 'action_args should not be overwritten when calling ohmylms_get_template.', 'ohmylms' ),
				'1.0.0'
			);
			unset( $args['action_args'] );
		}
		extract( $args ); // @codingStandardsIgnoreLine
	}

	do_action( 'ohmylms_before_template_part', $action_args['template_name'], $action_args['template_path'], $action_args['located'], $action_args['args'] );

	// A public rendering boundary lets frontend modules decorate trusted PHP templates.
	ob_start();
	try {
		include $action_args['located'];
		$ohmylms_template_html = ob_get_clean();
	} catch ( \Throwable $error ) {
		ob_end_clean();
		throw $error;
	}
	echo apply_filters( 'ohmylms_template_html', $ohmylms_template_html, $action_args['template_name'], $action_args['args'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- templates escape their own fields.

	do_action( 'ohmylms_after_template_part', $action_args['template_name'], $action_args['template_path'], $action_args['located'], $action_args['args'] );
}


/**
 * Locate template
 *
 * @param mixed  $template_name Template name.
 * @param string $template_path Template path.
 * @param string $default_path Default path.
 * @return mixed|void
 * @since 1.0.0
 */
function ohmylms_locate_template( $template_name, $template_path = '', $default_path = '' ) {
	if ( ! $template_path ) {
		$template_path = apply_filters( 'ohmylms_template_path', 'ohmylms/' );
	}

	if ( ! $default_path ) {
		$default_path = apply_filters( 'ohmylms_template_path', OHMYLMS_PATH ) . '/templates/';
	}

	if ( empty( $template ) ) {
		$template = locate_template(
			array(
				trailingslashit( $template_path ) . $template_name,
				$template_name,
			)
		);
	}

	if ( ! isset( $template ) || ! $template ) {
		$template = trailingslashit( $default_path ) . $template_name;
	}

	// Return what we found.
	return apply_filters( 'ohmylms_locate_template', $template, $template_name, $template_path );
}


/**
 * Get template part
 *
 * @param mixed  $slug Slug.
 * @param string $name Name.
 * @param array  $atts Variables to pass to the template.
 * @since 1.0.0
 */
function ohmylms_get_template_part( $slug, $name = '', $atts = null ) {
	if ( $name ) {
		$template = locate_template(
			array(
				"{$slug}-{$name}.php",
				ohmylms_template_path() . "{$slug}-{$name}.php",
			)
		);
		if ( ! $template ) {
			$fallback = OHMYLMS_PATH . "/templates/{$slug}-{$name}.php";
			$template = file_exists( $fallback ) ? $fallback : '';
		}
	}

	$template = apply_filters( 'ohmylms_get_template_part', $template, $slug, $name );

	if ( $template ) {
		// Extract variables to make them available in the template.
		if ( ! empty( $atts ) && is_array( $atts ) ) {
			extract( $atts ); // @codingStandardsIgnoreLine
		}

		include $template;
	}
}


/**
 * Set Cookie
 *
 * @param mixed $name Name.
 * @param mixed $value Value to process.
 * @param int   $expire Expire.
 * @param bool  $secure Secure.
 * @param bool  $httponly Httponly.
 * @since 1.0.0
 */
function ohmylms_setcookie( $name, $value, $expire = 0, $secure = false, $httponly = false ) {
	if ( ! headers_sent() ) {
		$options = array(
			'expires'  => $expire,
			'secure'   => $secure,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN,
			'httponly' => $httponly,
		);
		setcookie( $name, $value, $options );
	}
}


/**
 * Check if the home URL is https. If it is, we don't need to do things such as 'force ssl'.
 *
 * @since  1.0.0
 * @return bool
 */
function ohmylms_site_is_https() {
	return false !== strstr( get_option( 'home' ), 'https:' );
}



/**
 * Display OhMyLMS tooltip
 *
 * @param mixed $tip Tip.
 * @return string
 * @since 1.0.0
 */
function ohmylms_help_tip( $tip ) {
	$sanitized_tip = esc_attr( $tip );
	return apply_filters( 'ohmylms_help_tip', '<span class="ohmylms-help-tip" tabindex="0" aria-label="' . $sanitized_tip . '" data-tip="' . $sanitized_tip . '"></span>', $sanitized_tip, $tip );
}



/**
 * Ohmylms get rounding precision.
 *
 * @return mixed|void
 * @since 1.0.0
 */
function ohmylms_get_rounding_precision() {
	$precision = ohmylms_get_price_decimals() + 2;
	if ( $precision < absint( 6 ) ) {
		$precision = absint( 6 );
	}

	return $precision;
}


/**
 * Get template path
 *
 * @return mixed|null
 * @since 1.0.0
 */
function ohmylms_template_path() {
	return apply_filters( 'ohmylms_template_path', 'ohmylms/' );
}


/**
 * Get permalink settings for Course
 *
 * @return array
 * @since 1.0.0
 */
function ohmylms_get_permalink_structure() {
	$saved_permalinks              = (array) get_option( 'ohmylms_permalink', array() );
	$permalinks                    = wp_parse_args(
		array_filter( $saved_permalinks ),
		array(
			'course_base'     => _x( 'ohmylms-courses', 'slug', 'ohmylms' ),
			'lesson_base'     => _x( 'lessons', 'slug', 'ohmylms' ),
			'category_base'   => _x( 'course-category', 'slug', 'ohmylms' ),
			'tag_base'        => _x( 'course-tag', 'slug', 'ohmylms' ),
			'membership_base' => _x( 'members', 'slug', 'ohmylms' ),
			'quiz_base'       => _x( 'quizzes', 'slug', 'ohmylms' ),
		)
	);
	$permalinks['course_base']     = untrailingslashit( $permalinks['course_base'] );
	$permalinks['membership_base'] = untrailingslashit( $permalinks['membership_base'] );
	$permalinks['category_base']   = untrailingslashit( $permalinks['category_base'] );
	$permalinks['tag_base']        = untrailingslashit( $permalinks['tag_base'] );
	$permalinks['lesson_base']     = untrailingslashit( $permalinks['lesson_base'] );
	$permalinks['quiz_base']       = untrailingslashit( $permalinks['quiz_base'] );
	return $permalinks;
}


/**
 * Get Base Currency Code.
 *
 * @return string
 */
function get_ohmylms_currency() {
	return apply_filters( 'ohmylms_currency', get_option( 'ohmylms_currency', 'USD' ) );
}


/**
 * Get currency position
 *
 * @return string
 * @since 1.0.0
 */
function get_ohmylms_currency_position() {
	return apply_filters( 'ohmylms_currency_pos', get_option( 'ohmylms_currency_pos', 'left' ) );
}

/**
 * Get full list of currency codes.
 *
 * Currency symbols and names should follow the Unicode CLDR recommendation (https://cldr.unicode.org/translation/currency-names-and-symbols)
 *
 * @return array
 */
function get_ohmylms_currencies() {
	static $currencies;

	if ( ! isset( $currencies ) ) {
		$currencies = array_unique(
			apply_filters(
				'ohmylms_currencies',
				array(
					// Active currencies only.
					'USD' => __( 'United States (US) dollar', 'ohmylms' ),
					'EUR' => __( 'Euro', 'ohmylms' ),
					'GBP' => __( 'Pound sterling', 'ohmylms' ),
					'AUD' => __( 'Australian dollar', 'ohmylms' ),
					'CAD' => __( 'Canadian dollar', 'ohmylms' ),
					'JPY' => __( 'Japanese yen', 'ohmylms' ),
					'NZD' => __( 'New Zealand dollar', 'ohmylms' ),
					'CHF' => __( 'Swiss franc', 'ohmylms' ),
					'HKD' => __( 'Hong Kong dollar', 'ohmylms' ),
					'SGD' => __( 'Singapore dollar', 'ohmylms' ),
					'SEK' => __( 'Swedish krona', 'ohmylms' ),
					'DKK' => __( 'Danish krone', 'ohmylms' ),
					'PLN' => __( 'Polish z&#x142;oty', 'ohmylms' ),
					'NOK' => __( 'Norwegian krone', 'ohmylms' ),
					'HUF' => __( 'Hungarian forint', 'ohmylms' ),
					'CZK' => __( 'Czech koruna', 'ohmylms' ),
					'ILS' => __( 'Israeli new shekel', 'ohmylms' ),
					'MXN' => __( 'Mexican peso', 'ohmylms' ),
					'BRL' => __( 'Brazilian real', 'ohmylms' ),
					'MYR' => __( 'Malaysian ringgit', 'ohmylms' ),
					'PHP' => __( 'Philippine peso', 'ohmylms' ),
					'TWD' => __( 'New Taiwan dollar', 'ohmylms' ),
					'THB' => __( 'Thai baht', 'ohmylms' ),
					'TRY' => __( 'Turkish lira', 'ohmylms' ),
					'RUB' => __( 'Russian ruble', 'ohmylms' ),
					'INR' => __( 'Indian rupee', 'ohmylms' ),
					'ZAR' => __( 'South African rand', 'ohmylms' ),
					'SAR' => __( 'Saudi riyal', 'ohmylms' ),
					'AED' => __( 'United Arab Emirates dirham', 'ohmylms' ),

					// Commented out currencies - can be re-enabled if needed.
					'AFN' => __( 'Afghan afghani', 'ohmylms' ),
					'ALL' => __( 'Albanian lek', 'ohmylms' ),
					'AMD' => __( 'Armenian dram', 'ohmylms' ),
					'ANG' => __( 'Netherlands Antillean guilder', 'ohmylms' ),
					'AOA' => __( 'Angolan kwanza', 'ohmylms' ),
					'ARS' => __( 'Argentine peso', 'ohmylms' ),
					'AWG' => __( 'Aruban florin', 'ohmylms' ),
					'AZN' => __( 'Azerbaijani manat', 'ohmylms' ),
					'BAM' => __( 'Bosnia and Herzegovina convertible mark', 'ohmylms' ),
					'BBD' => __( 'Barbadian dollar', 'ohmylms' ),
					'BDT' => __( 'Bangladeshi taka', 'ohmylms' ),
					'BGN' => __( 'Bulgarian lev', 'ohmylms' ),
					'BHD' => __( 'Bahraini dinar', 'ohmylms' ),
					'BIF' => __( 'Burundian franc', 'ohmylms' ),
					'BMD' => __( 'Bermudian dollar', 'ohmylms' ),
					'BND' => __( 'Brunei dollar', 'ohmylms' ),
					'BOB' => __( 'Bolivian boliviano', 'ohmylms' ),
					'BSD' => __( 'Bahamian dollar', 'ohmylms' ),
					'BTC' => __( 'Bitcoin', 'ohmylms' ),
					'BTN' => __( 'Bhutanese ngultrum', 'ohmylms' ),
					'BWP' => __( 'Botswana pula', 'ohmylms' ),
					'BYR' => __( 'Belarusian ruble (old)', 'ohmylms' ),
					'BYN' => __( 'Belarusian ruble', 'ohmylms' ),
					'BZD' => __( 'Belize dollar', 'ohmylms' ),
					'CDF' => __( 'Congolese franc', 'ohmylms' ),
					'CLP' => __( 'Chilean peso', 'ohmylms' ),
					'CNY' => __( 'Chinese yuan', 'ohmylms' ),
					'COP' => __( 'Colombian peso', 'ohmylms' ),
					'CRC' => __( 'Costa Rican col&oacute;n', 'ohmylms' ),
					'CUC' => __( 'Cuban convertible peso', 'ohmylms' ),
					'CUP' => __( 'Cuban peso', 'ohmylms' ),
					'CVE' => __( 'Cape Verdean escudo', 'ohmylms' ),
					'DJF' => __( 'Djiboutian franc', 'ohmylms' ),
					'DOP' => __( 'Dominican peso', 'ohmylms' ),
					'DZD' => __( 'Algerian dinar', 'ohmylms' ),
					'EGP' => __( 'Egyptian pound', 'ohmylms' ),
					'ERN' => __( 'Eritrean nakfa', 'ohmylms' ),
					'ETB' => __( 'Ethiopian birr', 'ohmylms' ),
					'FJD' => __( 'Fijian dollar', 'ohmylms' ),
					'FKP' => __( 'Falkland Islands pound', 'ohmylms' ),
					'GEL' => __( 'Georgian lari', 'ohmylms' ),
					'GGP' => __( 'Guernsey pound', 'ohmylms' ),
					'GHS' => __( 'Ghana cedi', 'ohmylms' ),
					'GIP' => __( 'Gibraltar pound', 'ohmylms' ),
					'GMD' => __( 'Gambian dalasi', 'ohmylms' ),
					'GNF' => __( 'Guinean franc', 'ohmylms' ),
					'GTQ' => __( 'Guatemalan quetzal', 'ohmylms' ),
					'GYD' => __( 'Guyanese dollar', 'ohmylms' ),
					'HNL' => __( 'Honduran lempira', 'ohmylms' ),
					'HRK' => __( 'Croatian kuna', 'ohmylms' ),
					'HTG' => __( 'Haitian gourde', 'ohmylms' ),
					'IDR' => __( 'Indonesian rupiah', 'ohmylms' ),
					'IMP' => __( 'Manx pound', 'ohmylms' ),
					'IQD' => __( 'Iraqi dinar', 'ohmylms' ),
					'IRR' => __( 'Iranian rial', 'ohmylms' ),
					'IRT' => __( 'Iranian toman', 'ohmylms' ),
					'ISK' => __( 'Icelandic kr&oacute;na', 'ohmylms' ),
					'JEP' => __( 'Jersey pound', 'ohmylms' ),
					'JMD' => __( 'Jamaican dollar', 'ohmylms' ),
					'JOD' => __( 'Jordanian dinar', 'ohmylms' ),
					'KES' => __( 'Kenyan shilling', 'ohmylms' ),
					'KGS' => __( 'Kyrgyzstani som', 'ohmylms' ),
					'KHR' => __( 'Cambodian riel', 'ohmylms' ),
					'KMF' => __( 'Comorian franc', 'ohmylms' ),
					'KPW' => __( 'North Korean won', 'ohmylms' ),
					'KRW' => __( 'South Korean won', 'ohmylms' ),
					'KWD' => __( 'Kuwaiti dinar', 'ohmylms' ),
					'KYD' => __( 'Cayman Islands dollar', 'ohmylms' ),
					'KZT' => __( 'Kazakhstani tenge', 'ohmylms' ),
					'LAK' => __( 'Lao kip', 'ohmylms' ),
					'LBP' => __( 'Lebanese pound', 'ohmylms' ),
					'LKR' => __( 'Sri Lankan rupee', 'ohmylms' ),
					'LRD' => __( 'Liberian dollar', 'ohmylms' ),
					'LSL' => __( 'Lesotho loti', 'ohmylms' ),
					'LYD' => __( 'Libyan dinar', 'ohmylms' ),
					'MAD' => __( 'Moroccan dirham', 'ohmylms' ),
					'MDL' => __( 'Moldovan leu', 'ohmylms' ),
					'MGA' => __( 'Malagasy ariary', 'ohmylms' ),
					'MKD' => __( 'Macedonian denar', 'ohmylms' ),
					'MMK' => __( 'Burmese kyat', 'ohmylms' ),
					'MNT' => __( 'Mongolian t&ouml;gr&ouml;g', 'ohmylms' ),
					'MOP' => __( 'Macanese pataca', 'ohmylms' ),
					'MRU' => __( 'Mauritanian ouguiya', 'ohmylms' ),
					'MUR' => __( 'Mauritian rupee', 'ohmylms' ),
					'MVR' => __( 'Maldivian rufiyaa', 'ohmylms' ),
					'MWK' => __( 'Malawian kwacha', 'ohmylms' ),
					'MZN' => __( 'Mozambican metical', 'ohmylms' ),
					'NAD' => __( 'Namibian dollar', 'ohmylms' ),
					'NGN' => __( 'Nigerian naira', 'ohmylms' ),
					'NIO' => __( 'Nicaraguan c&oacute;rdoba', 'ohmylms' ),
					'NPR' => __( 'Nepalese rupee', 'ohmylms' ),
					'OMR' => __( 'Omani rial', 'ohmylms' ),
					'PAB' => __( 'Panamanian balboa', 'ohmylms' ),
					'PEN' => __( 'Sol', 'ohmylms' ),
					'PGK' => __( 'Papua New Guinean kina', 'ohmylms' ),
					'PKR' => __( 'Pakistani rupee', 'ohmylms' ),
					'PRB' => __( 'Transnistrian ruble', 'ohmylms' ),
					'PYG' => __( 'Paraguayan guaran&iacute;', 'ohmylms' ),
					'QAR' => __( 'Qatari riyal', 'ohmylms' ),
					'RON' => __( 'Romanian leu', 'ohmylms' ),
					'RSD' => __( 'Serbian dinar', 'ohmylms' ),
					'RWF' => __( 'Rwandan franc', 'ohmylms' ),
					'SBD' => __( 'Solomon Islands dollar', 'ohmylms' ),
					'SCR' => __( 'Seychellois rupee', 'ohmylms' ),
					'SDG' => __( 'Sudanese pound', 'ohmylms' ),
					'SHP' => __( 'Saint Helena pound', 'ohmylms' ),
					'SLL' => __( 'Sierra Leonean leone', 'ohmylms' ),
					'SOS' => __( 'Somali shilling', 'ohmylms' ),
					'SRD' => __( 'Surinamese dollar', 'ohmylms' ),
					'SSP' => __( 'South Sudanese pound', 'ohmylms' ),
					'STN' => __( 'S&atilde;o Tom&eacute; and Pr&iacute;ncipe dobra', 'ohmylms' ),
					'SYP' => __( 'Syrian pound', 'ohmylms' ),
					'SZL' => __( 'Swazi lilangeni', 'ohmylms' ),
					'TJS' => __( 'Tajikistani somoni', 'ohmylms' ),
					'TMT' => __( 'Turkmenistan manat', 'ohmylms' ),
					'TND' => __( 'Tunisian dinar', 'ohmylms' ),
					'TOP' => __( 'Tongan pa&#x2bb;anga', 'ohmylms' ),
					'TTD' => __( 'Trinidad and Tobago dollar', 'ohmylms' ),
					'TZS' => __( 'Tanzanian shilling', 'ohmylms' ),
					'UAH' => __( 'Ukrainian hryvnia', 'ohmylms' ),
					'UGX' => __( 'Ugandan shilling', 'ohmylms' ),
					'UYU' => __( 'Uruguayan peso', 'ohmylms' ),
					'UZS' => __( 'Uzbekistani som', 'ohmylms' ),
					'VEF' => __( 'Venezuelan bol&iacute;var (2008–2018)', 'ohmylms' ),
					'VES' => __( 'Venezuelan bol&iacute;var', 'ohmylms' ),
					'VND' => __( 'Vietnamese &#x111;&#x1ed3;ng', 'ohmylms' ),
					'VUV' => __( 'Vanuatu vatu', 'ohmylms' ),
					'WST' => __( 'Samoan t&#x101;l&#x101;', 'ohmylms' ),
					'XAF' => __( 'Central African CFA franc', 'ohmylms' ),
					'XCD' => __( 'East Caribbean dollar', 'ohmylms' ),
					'XOF' => __( 'West African CFA franc', 'ohmylms' ),
					'XPF' => __( 'CFP franc', 'ohmylms' ),
					'YER' => __( 'Yemeni rial', 'ohmylms' ),
					'ZMW' => __( 'Zambian kwacha', 'ohmylms' ),
				)
			)
		);
	}

	return $currencies;
}

/**
 * Get all available Currency symbols.
 *
 * Currency symbols and names should follow the Unicode CLDR recommendation (https://cldr.unicode.org/translation/currency-names-and-symbols)
 *
 * @since 4.1.0
 * @return array
 */
function get_ohmylms_currency_symbols() {

	$symbols = apply_filters(
		'ohmylms_currency_symbols',
		array(
			'AED' => '&#x62f;.&#x625;',
			'AFN' => '&#x60b;',
			'ALL' => 'L',
			'AMD' => 'AMD',
			'ANG' => '&fnof;',
			'AOA' => 'Kz',
			'ARS' => '&#36;',
			'AUD' => '&#36;',
			'AWG' => 'Afl.',
			'AZN' => '&#8380;',
			'BAM' => 'KM',
			'BBD' => '&#36;',
			'BDT' => '&#2547;',
			'BGN' => '&#1083;&#1074;.',
			'BHD' => '.&#x62f;.&#x628;',
			'BIF' => 'Fr',
			'BMD' => '&#36;',
			'BND' => '&#36;',
			'BOB' => 'Bs.',
			'BRL' => '&#82;&#36;',
			'BSD' => '&#36;',
			'BTC' => '&#3647;',
			'BTN' => 'Nu.',
			'BWP' => 'P',
			'BYR' => 'Br',
			'BYN' => 'Br',
			'BZD' => '&#36;',
			'CAD' => '&#36;',
			'CDF' => 'Fr',
			'CHF' => '&#67;&#72;&#70;',
			'CLP' => '&#36;',
			'CNY' => '&yen;',
			'COP' => '&#36;',
			'CRC' => '&#x20a1;',
			'CUC' => '&#36;',
			'CUP' => '&#36;',
			'CVE' => '&#36;',
			'CZK' => '&#75;&#269;',
			'DJF' => 'Fr',
			'DKK' => 'kr.',
			'DOP' => 'RD&#36;',
			'DZD' => '&#x62f;.&#x62c;',
			'EGP' => 'EGP',
			'ERN' => 'Nfk',
			'ETB' => 'Br',
			'EUR' => '&euro;',
			'FJD' => '&#36;',
			'FKP' => '&pound;',
			'GBP' => '&pound;',
			'GEL' => '&#x20be;',
			'GGP' => '&pound;',
			'GHS' => '&#x20b5;',
			'GIP' => '&pound;',
			'GMD' => 'D',
			'GNF' => 'Fr',
			'GTQ' => 'Q',
			'GYD' => '&#36;',
			'HKD' => '&#36;',
			'HNL' => 'L',
			'HRK' => 'kn',
			'HTG' => 'G',
			'HUF' => '&#70;&#116;',
			'IDR' => 'Rp',
			'ILS' => '&#8362;',
			'IMP' => '&pound;',
			'INR' => '&#8377;',
			'IQD' => '&#x62f;.&#x639;',
			'IRR' => '&#xfdfc;',
			'IRT' => '&#x062A;&#x0648;&#x0645;&#x0627;&#x0646;',
			'ISK' => 'kr.',
			'JEP' => '&pound;',
			'JMD' => '&#36;',
			'JOD' => '&#x62f;.&#x627;',
			'JPY' => '&yen;',
			'KES' => 'KSh',
			'KGS' => '&#x441;&#x43e;&#x43c;',
			'KHR' => '&#x17db;',
			'KMF' => 'Fr',
			'KPW' => '&#x20a9;',
			'KRW' => '&#8361;',
			'KWD' => '&#x62f;.&#x643;',
			'KYD' => '&#36;',
			'KZT' => '&#8376;',
			'LAK' => '&#8365;',
			'LBP' => '&#x644;.&#x644;',
			'LKR' => '&#xdbb;&#xdd4;',
			'LRD' => '&#36;',
			'LSL' => 'L',
			'LYD' => '&#x62f;.&#x644;',
			'MAD' => '&#x62f;.&#x645;.',
			'MDL' => 'MDL',
			'MGA' => 'Ar',
			'MKD' => '&#x434;&#x435;&#x43d;',
			'MMK' => 'Ks',
			'MNT' => '&#x20ae;',
			'MOP' => 'P',
			'MRU' => 'UM',
			'MUR' => '&#x20a8;',
			'MVR' => '.&#x783;',
			'MWK' => 'MK',
			'MXN' => '&#36;',
			'MYR' => '&#82;&#77;',
			'MZN' => 'MT',
			'NAD' => 'N&#36;',
			'NGN' => '&#8358;',
			'NIO' => 'C&#36;',
			'NOK' => '&#107;&#114;',
			'NPR' => '&#8360;',
			'NZD' => '&#36;',
			'OMR' => '&#x631;.&#x639;.',
			'PAB' => 'B/.',
			'PEN' => 'S/',
			'PGK' => 'K',
			'PHP' => '&#8369;',
			'PKR' => '&#8360;',
			'PLN' => '&#122;&#322;',
			'PRB' => '&#x440;.',
			'PYG' => '&#8370;',
			'QAR' => '&#x631;.&#x642;',
			'RMB' => '&yen;',
			'RON' => 'lei',
			'RSD' => '&#1088;&#1089;&#1076;',
			'RUB' => '&#8381;',
			'RWF' => 'Fr',
			'SAR' => '&#x631;.&#x633;',
			'SBD' => '&#36;',
			'SCR' => '&#x20a8;',
			'SDG' => '&#x62c;.&#x633;.',
			'SEK' => '&#107;&#114;',
			'SGD' => '&#36;',
			'SHP' => '&pound;',
			'SLL' => 'Le',
			'SOS' => 'Sh',
			'SRD' => '&#36;',
			'SSP' => '&pound;',
			'STN' => 'Db',
			'SYP' => '&#x644;.&#x633;',
			'SZL' => 'E',
			'THB' => '&#3647;',
			'TJS' => '&#x405;&#x41c;',
			'TMT' => 'm',
			'TND' => '&#x62f;.&#x62a;',
			'TOP' => 'T&#36;',
			'TRY' => '&#8378;',
			'TTD' => '&#36;',
			'TWD' => '&#78;&#84;&#36;',
			'TZS' => 'Sh',
			'UAH' => '&#8372;',
			'UGX' => 'UGX',
			'USD' => '&#36;',
			'UYU' => '&#36;',
			'UZS' => 'UZS',
			'VEF' => 'Bs F',
			'VES' => 'Bs.',
			'VND' => '&#8363;',
			'VUV' => 'Vt',
			'WST' => 'T',
			'XAF' => 'CFA',
			'XCD' => '&#36;',
			'XOF' => 'CFA',
			'XPF' => 'Fr',
			'YER' => '&#xfdfc;',
			'ZAR' => '&#82;',
			'ZMW' => 'ZK',
		)
	);

	return $symbols;
}


/**
 * Get Currency symbol.
 *
 * Currency symbols and names should follow the Unicode CLDR recommendation (https://cldr.unicode.org/translation/currency-names-and-symbols)
 *
 * @param string $currency Currency. (default: '').
 * @return string
 */
function get_ohmylms_currency_symbol( $currency = '' ) {
	if ( ! $currency ) {
		$currency = get_ohmylms_currency();
	}

	$symbols = get_ohmylms_currency_symbols();

	$currency_symbol = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : '';

	return apply_filters( 'ohmylms_currency_symbol', $currency_symbol, $currency );
}

/**
 * Ohmylms decode unicode sequences for Core-functions.
 *
 * @param mixed $str Str.
 */
function ohmylms_decode_unicode_sequences( $str ) {
	return preg_replace_callback(
		'/\\\\u([0-9a-fA-F]{4})/',
		function ( $matches ) {
			return mb_convert_encoding( pack( 'H*', $matches[1] ), 'UTF-8', 'UCS-2BE' );
		},
		$str
	);
}

/**
 * Check if theme support is available.
 *
 * @since 1.0.0
 * @return bool
 */
function ohmylms_if_theme_support_available() {
	$supported_theme = array(
		'twentytwentyone',
		'twentytwentytwo',
		'twentytwentythree',
		'twentytwentyfour',
		'twentytwentyfive',
		'astra',
		'Avada',
		'blocksy',
		'bricks',
		'colibri-wp',
		'Divi',
		'generatepress',
		'kadence',
		'oceanwp',
		'storefront',
		'twentynineteen',
		'twentyseventeen',
		'twentysixteen',
		'twentytwenty',
		'helloelementor',
		'hestia',
		'thrive-theme',
		'woostify',
		'betheme',
		'flatsome',
		'woodmart',
		'dt-the7',
	);
	return in_array( get_template(), $supported_theme, true );
}


/**
 * Ohmylms placeholder img for Core-functions.
 */
function ohmylms_placeholder_img() {
	$placeholder_img = apply_filters( 'ohmylms_placeholder_img', OHMYLMS_URL . '/assets/images/course-placeholder-image.svg' );
	return $placeholder_img;
}


/**
 * Get the checkout URL.
 *
 * @return string The checkout URL.
 * @since 1.0.0
 */
function ohmylms_get_checkout_url() {
	$page_id      = get_option( 'ohmylms_checkout_page_id' );
	$checkout_url = 0 < $page_id ? get_permalink( $page_id ) : '';

	return apply_filters( 'ohmylms_get_checkout_url', $checkout_url );
}

/**
 * Format duration into a human-readable string.
 *
 * @param array $duration An associative array with keys 'hour', 'min', and 'sec'.
 * @return string The formatted duration.
 * @since 1.0.0
 */
function ohmylms_format_duration( $duration ) {
	$formatted_duration = '';
	if ( isset( $duration['hour'] ) ) {
		$formatted_duration .= "{$duration['hour']}hr ";
	}
	if ( isset( $duration['min'] ) ) {
		$formatted_duration .= "{$duration['min']}min ";
	}
	return $formatted_duration;
}


/**
 * Ohmylms get membership url for Core-functions.
 */
function ohmylms_get_membership_url() {
	$page_id        = get_option( 'ohmylms_membership_page_id' );
	$membership_url = 0 < $page_id ? get_permalink( $page_id ) : '';

	return apply_filters( 'ohmylms_get_membership_url', $membership_url );
}

/**
 * Get the WordPress role slug used for OhMyLMS students.
 *
 * Other OhMyLMS add-ons (Pro, Community) should always read the role
 * through this function (guarded with function_exists()) instead of
 * hardcoding 'ohmylms_student', so they keep working with older or newer
 * versions of this plugin without a fatal error.
 *
 * @return string
 * @since 1.2.12
 */
if ( ! function_exists( 'ohmylms_get_student_role' ) ) {
	/**
	 * Ohmylms get student role for Core-functions.
	 */
	function ohmylms_get_student_role() {
		$role = apply_filters( 'ohmylms_student_role', 'ohmylms_student' );
		return is_string( $role ) && '' !== $role ? $role : 'ohmylms_student';
	}
}

/**
 * Get the student role slug that is safe to assign to a user right now.
 *
 * Falls back to 'subscriber' while 'ohmylms_student' is not yet registered
 * (plugin files updated but the upgrade routine hasn't run — e.g. an
 * auto-update with no wp-admin visit yet). Assigning an unregistered role
 * would silently leave the user with no effective role and no capabilities.
 *
 * @return string
 * @since 1.2.12
 */
if ( ! function_exists( 'ohmylms_get_assignable_student_role' ) ) {
	/**
	 * Ohmylms get assignable student role for Core-functions.
	 */
	function ohmylms_get_assignable_student_role() {
		$role = ohmylms_get_student_role();
		return get_role( $role ) ? $role : 'subscriber';
	}
}

/**
 * Ohmylms is pro for Core-functions.
 */
function ohmylms_is_pro() {
	return true;
}

/**
 * Check if funnel integration is enabled
 *
 * @return bool
 * @since 1.0.0
 */
function ohmylms_is_funnel_enabled() {
	return apply_filters( 'ohmylms_should_show_funnel', false );
}

/**
 * Ohmylms modules for Core-functions.
 */
function ohmylms_modules() {
	$modules = array(
		'course',
		'chapter',
		'lesson',
		'quiz',
	);
	return apply_filters( 'ohmylms_modules', $modules );
}


/**
 * Get the list of countries.
 *
 * @return array The list of countries.
 * @since 1.0.0
 */
function ohmylms_get_countries() {
	return array(
		array(
			'code'  => 'AF',
			'title' => __( 'Afghanistan', 'ohmylms' ),
		),
		array(
			'code'  => 'AX',
			'title' => __( 'Åland Islands', 'ohmylms' ),
		),
		array(
			'code'  => 'AL',
			'title' => __( 'Albania', 'ohmylms' ),
		),
		array(
			'code'  => 'DZ',
			'title' => __( 'Algeria', 'ohmylms' ),
		),
		array(
			'code'  => 'AS',
			'title' => __( 'American Samoa', 'ohmylms' ),
		),
		array(
			'code'  => 'AD',
			'title' => __( 'Andorra', 'ohmylms' ),
		),
		array(
			'code'  => 'AO',
			'title' => __( 'Angola', 'ohmylms' ),
		),
		array(
			'code'  => 'AI',
			'title' => __( 'Anguilla', 'ohmylms' ),
		),
		array(
			'code'  => 'AQ',
			'title' => __( 'Antarctica', 'ohmylms' ),
		),
		array(
			'code'  => 'AG',
			'title' => __( 'Antigua and Barbuda', 'ohmylms' ),
		),
		array(
			'code'  => 'AR',
			'title' => __( 'Argentina', 'ohmylms' ),
		),
		array(
			'code'  => 'AM',
			'title' => __( 'Armenia', 'ohmylms' ),
		),
		array(
			'code'  => 'AW',
			'title' => __( 'Aruba', 'ohmylms' ),
		),
		array(
			'code'  => 'AU',
			'title' => __( 'Australia', 'ohmylms' ),
		),
		array(
			'code'  => 'AT',
			'title' => __( 'Austria', 'ohmylms' ),
		),
		array(
			'code'  => 'AZ',
			'title' => __( 'Azerbaijan', 'ohmylms' ),
		),
		array(
			'code'  => 'BS',
			'title' => __( 'Bahamas', 'ohmylms' ),
		),
		array(
			'code'  => 'BH',
			'title' => __( 'Bahrain', 'ohmylms' ),
		),
		array(
			'code'  => 'BD',
			'title' => __( 'Bangladesh', 'ohmylms' ),
		),
		array(
			'code'  => 'BB',
			'title' => __( 'Barbados', 'ohmylms' ),
		),
		array(
			'code'  => 'BY',
			'title' => __( 'Belarus', 'ohmylms' ),
		),
		array(
			'code'  => 'BE',
			'title' => __( 'Belgium', 'ohmylms' ),
		),
		array(
			'code'  => 'PW',
			'title' => __( 'Belau', 'ohmylms' ),
		),
		array(
			'code'  => 'BZ',
			'title' => __( 'Belize', 'ohmylms' ),
		),
		array(
			'code'  => 'BJ',
			'title' => __( 'Benin', 'ohmylms' ),
		),
		array(
			'code'  => 'BM',
			'title' => __( 'Bermuda', 'ohmylms' ),
		),
		array(
			'code'  => 'BT',
			'title' => __( 'Bhutan', 'ohmylms' ),
		),
		array(
			'code'  => 'BO',
			'title' => __( 'Bolivia', 'ohmylms' ),
		),
		array(
			'code'  => 'BQ',
			'title' => __( 'Bonaire, Saint Eustatius and Saba', 'ohmylms' ),
		),
		array(
			'code'  => 'BA',
			'title' => __( 'Bosnia and Herzegovina', 'ohmylms' ),
		),
		array(
			'code'  => 'BW',
			'title' => __( 'Botswana', 'ohmylms' ),
		),
		array(
			'code'  => 'BV',
			'title' => __( 'Bouvet Island', 'ohmylms' ),
		),
		array(
			'code'  => 'BR',
			'title' => __( 'Brazil', 'ohmylms' ),
		),
		array(
			'code'  => 'IO',
			'title' => __( 'British Indian Ocean Territory', 'ohmylms' ),
		),
		array(
			'code'  => 'BN',
			'title' => __( 'Brunei', 'ohmylms' ),
		),
		array(
			'code'  => 'BG',
			'title' => __( 'Bulgaria', 'ohmylms' ),
		),
		array(
			'code'  => 'BF',
			'title' => __( 'Burkina Faso', 'ohmylms' ),
		),
		array(
			'code'  => 'BI',
			'title' => __( 'Burundi', 'ohmylms' ),
		),
		array(
			'code'  => 'KH',
			'title' => __( 'Cambodia', 'ohmylms' ),
		),
		array(
			'code'  => 'CM',
			'title' => __( 'Cameroon', 'ohmylms' ),
		),
		array(
			'code'  => 'CA',
			'title' => __( 'Canada', 'ohmylms' ),
		),
		array(
			'code'  => 'CV',
			'title' => __( 'Cape Verde', 'ohmylms' ),
		),
		array(
			'code'  => 'KY',
			'title' => __( 'Cayman Islands', 'ohmylms' ),
		),
		array(
			'code'  => 'CF',
			'title' => __( 'Central African Republic', 'ohmylms' ),
		),
		array(
			'code'  => 'TD',
			'title' => __( 'Chad', 'ohmylms' ),
		),
		array(
			'code'  => 'CL',
			'title' => __( 'Chile', 'ohmylms' ),
		),
		array(
			'code'  => 'CN',
			'title' => __( 'China', 'ohmylms' ),
		),
		array(
			'code'  => 'CX',
			'title' => __( 'Christmas Island', 'ohmylms' ),
		),
		array(
			'code'  => 'CC',
			'title' => __( 'Cocos (Keeling) Islands', 'ohmylms' ),
		),
		array(
			'code'  => 'CO',
			'title' => __( 'Colombia', 'ohmylms' ),
		),
		array(
			'code'  => 'KM',
			'title' => __( 'Comoros', 'ohmylms' ),
		),
		array(
			'code'  => 'CG',
			'title' => __( 'Congo (Brazzaville)', 'ohmylms' ),
		),
		array(
			'code'  => 'CD',
			'title' => __( 'Congo (Kinshasa)', 'ohmylms' ),
		),
		array(
			'code'  => 'CK',
			'title' => __( 'Cook Islands', 'ohmylms' ),
		),
		array(
			'code'  => 'CR',
			'title' => __( 'Costa Rica', 'ohmylms' ),
		),
		array(
			'code'  => 'HR',
			'title' => __( 'Croatia', 'ohmylms' ),
		),
		array(
			'code'  => 'CU',
			'title' => __( 'Cuba', 'ohmylms' ),
		),
		array(
			'code'  => 'CW',
			'title' => __( 'Cura&ccedil;ao', 'ohmylms' ),
		),
		array(
			'code'  => 'CY',
			'title' => __( 'Cyprus', 'ohmylms' ),
		),
		array(
			'code'  => 'CZ',
			'title' => __( 'Czechia (Czech Republic)', 'ohmylms' ),
		),
		array(
			'code'  => 'DK',
			'title' => __( 'Denmark', 'ohmylms' ),
		),
		array(
			'code'  => 'DJ',
			'title' => __( 'Djibouti', 'ohmylms' ),
		),
		array(
			'code'  => 'DM',
			'title' => __( 'Dominica', 'ohmylms' ),
		),
		array(
			'code'  => 'DO',
			'title' => __( 'Dominican Republic', 'ohmylms' ),
		),
		array(
			'code'  => 'EC',
			'title' => __( 'Ecuador', 'ohmylms' ),
		),
		array(
			'code'  => 'EG',
			'title' => __( 'Egypt', 'ohmylms' ),
		),
		array(
			'code'  => 'SV',
			'title' => __( 'El Salvador', 'ohmylms' ),
		),
		array(
			'code'  => 'GQ',
			'title' => __( 'Equatorial Guinea', 'ohmylms' ),
		),
		array(
			'code'  => 'ER',
			'title' => __( 'Eritrea', 'ohmylms' ),
		),
		array(
			'code'  => 'EE',
			'title' => __( 'Estonia', 'ohmylms' ),
		),
		array(
			'code'  => 'ET',
			'title' => __( 'Ethiopia', 'ohmylms' ),
		),
		array(
			'code'  => 'FK',
			'title' => __( 'Falkland Islands', 'ohmylms' ),
		),
		array(
			'code'  => 'FO',
			'title' => __( 'Faroe Islands', 'ohmylms' ),
		),
		array(
			'code'  => 'FJ',
			'title' => __( 'Fiji', 'ohmylms' ),
		),
		array(
			'code'  => 'FI',
			'title' => __( 'Finland', 'ohmylms' ),
		),
		array(
			'code'  => 'FR',
			'title' => __( 'France', 'ohmylms' ),
		),
		array(
			'code'  => 'GF',
			'title' => __( 'French Guiana', 'ohmylms' ),
		),
		array(
			'code'  => 'PF',
			'title' => __( 'French Polynesia', 'ohmylms' ),
		),
		array(
			'code'  => 'TF',
			'title' => __( 'French Southern Territories', 'ohmylms' ),
		),
		array(
			'code'  => 'GA',
			'title' => __( 'Gabon', 'ohmylms' ),
		),
		array(
			'code'  => 'GM',
			'title' => __( 'Gambia', 'ohmylms' ),
		),
		array(
			'code'  => 'GE',
			'title' => __( 'Georgia', 'ohmylms' ),
		),
		array(
			'code'  => 'DE',
			'title' => __( 'Germany', 'ohmylms' ),
		),
		array(
			'code'  => 'GH',
			'title' => __( 'Ghana', 'ohmylms' ),
		),
		array(
			'code'  => 'GI',
			'title' => __( 'Gibraltar', 'ohmylms' ),
		),
		array(
			'code'  => 'GR',
			'title' => __( 'Greece', 'ohmylms' ),
		),
		array(
			'code'  => 'GL',
			'title' => __( 'Greenland', 'ohmylms' ),
		),
		array(
			'code'  => 'GD',
			'title' => __( 'Grenada', 'ohmylms' ),
		),
		array(
			'code'  => 'GP',
			'title' => __( 'Guadeloupe', 'ohmylms' ),
		),
		array(
			'code'  => 'GU',
			'title' => __( 'Guam', 'ohmylms' ),
		),
		array(
			'code'  => 'GT',
			'title' => __( 'Guatemala', 'ohmylms' ),
		),
		array(
			'code'  => 'GG',
			'title' => __( 'Guernsey', 'ohmylms' ),
		),
		array(
			'code'  => 'GN',
			'title' => __( 'Guinea', 'ohmylms' ),
		),
		array(
			'code'  => 'GW',
			'title' => __( 'Guinea-Bissau', 'ohmylms' ),
		),
		array(
			'code'  => 'GY',
			'title' => __( 'Guyana', 'ohmylms' ),
		),
		array(
			'code'  => 'HT',
			'title' => __( 'Haiti', 'ohmylms' ),
		),
		array(
			'code'  => 'HM',
			'title' => __( 'Heard Island and McDonald Islands', 'ohmylms' ),
		),
		array(
			'code'  => 'HN',
			'title' => __( 'Honduras', 'ohmylms' ),
		),
		array(
			'code'  => 'HK',
			'title' => __( 'Hong Kong', 'ohmylms' ),
		),
		array(
			'code'  => 'HU',
			'title' => __( 'Hungary', 'ohmylms' ),
		),
		array(
			'code'  => 'IS',
			'title' => __( 'Iceland', 'ohmylms' ),
		),
		array(
			'code'  => 'IN',
			'title' => __( 'India', 'ohmylms' ),
		),
		array(
			'code'  => 'ID',
			'title' => __( 'Indonesia', 'ohmylms' ),
		),
		array(
			'code'  => 'IR',
			'title' => __( 'Iran', 'ohmylms' ),
		),
		array(
			'code'  => 'IQ',
			'title' => __( 'Iraq', 'ohmylms' ),
		),
		array(
			'code'  => 'IE',
			'title' => __( 'Ireland', 'ohmylms' ),
		),
		array(
			'code'  => 'IM',
			'title' => __( 'Isle of Man', 'ohmylms' ),
		),
		array(
			'code'  => 'IL',
			'title' => __( 'Israel', 'ohmylms' ),
		),
		array(
			'code'  => 'IT',
			'title' => __( 'Italy', 'ohmylms' ),
		),
		array(
			'code'  => 'CI',
			'title' => __( 'Ivory Coast', 'ohmylms' ),
		),
		array(
			'code'  => 'JM',
			'title' => __( 'Jamaica', 'ohmylms' ),
		),
		array(
			'code'  => 'JP',
			'title' => __( 'Japan', 'ohmylms' ),
		),
		array(
			'code'  => 'JE',
			'title' => __( 'Jersey', 'ohmylms' ),
		),
		array(
			'code'  => 'JO',
			'title' => __( 'Jordan', 'ohmylms' ),
		),
		array(
			'code'  => 'KZ',
			'title' => __( 'Kazakhstan', 'ohmylms' ),
		),
		array(
			'code'  => 'KE',
			'title' => __( 'Kenya', 'ohmylms' ),
		),
		array(
			'code'  => 'KI',
			'title' => __( 'Kiribati', 'ohmylms' ),
		),
		array(
			'code'  => 'KW',
			'title' => __( 'Kuwait', 'ohmylms' ),
		),
		array(
			'code'  => 'XK',
			'title' => __( 'Kosovo', 'ohmylms' ),
		),
		array(
			'code'  => 'KG',
			'title' => __( 'Kyrgyzstan', 'ohmylms' ),
		),
		array(
			'code'  => 'LA',
			'title' => __( 'Laos', 'ohmylms' ),
		),
		array(
			'code'  => 'LV',
			'title' => __( 'Latvia', 'ohmylms' ),
		),
		array(
			'code'  => 'LB',
			'title' => __( 'Lebanon', 'ohmylms' ),
		),
		array(
			'code'  => 'LS',
			'title' => __( 'Lesotho', 'ohmylms' ),
		),
		array(
			'code'  => 'LR',
			'title' => __( 'Liberia', 'ohmylms' ),
		),
		array(
			'code'  => 'LY',
			'title' => __( 'Libya', 'ohmylms' ),
		),
		array(
			'code'  => 'LI',
			'title' => __( 'Liechtenstein', 'ohmylms' ),
		),
		array(
			'code'  => 'LT',
			'title' => __( 'Lithuania', 'ohmylms' ),
		),
		array(
			'code'  => 'LU',
			'title' => __( 'Luxembourg', 'ohmylms' ),
		),
		array(
			'code'  => 'MO',
			'title' => __( 'Macao', 'ohmylms' ),
		),
		array(
			'code'  => 'MK',
			'title' => __( 'North Macedonia', 'ohmylms' ),
		),
		array(
			'code'  => 'MG',
			'title' => __( 'Madagascar', 'ohmylms' ),
		),
		array(
			'code'  => 'MW',
			'title' => __( 'Malawi', 'ohmylms' ),
		),
		array(
			'code'  => 'MY',
			'title' => __( 'Malaysia', 'ohmylms' ),
		),
		array(
			'code'  => 'MV',
			'title' => __( 'Maldives', 'ohmylms' ),
		),
		array(
			'code'  => 'ML',
			'title' => __( 'Mali', 'ohmylms' ),
		),
		array(
			'code'  => 'MT',
			'title' => __( 'Malta', 'ohmylms' ),
		),
		array(
			'code'  => 'MH',
			'title' => __( 'Marshall Islands', 'ohmylms' ),
		),
		array(
			'code'  => 'MQ',
			'title' => __( 'Martinique', 'ohmylms' ),
		),
		array(
			'code'  => 'MR',
			'title' => __( 'Mauritania', 'ohmylms' ),
		),
		array(
			'code'  => 'MU',
			'title' => __( 'Mauritius', 'ohmylms' ),
		),
		array(
			'code'  => 'YT',
			'title' => __( 'Mayotte', 'ohmylms' ),
		),
		array(
			'code'  => 'MX',
			'title' => __( 'Mexico', 'ohmylms' ),
		),
		array(
			'code'  => 'FM',
			'title' => __( 'Micronesia', 'ohmylms' ),
		),
		array(
			'code'  => 'MD',
			'title' => __( 'Moldova', 'ohmylms' ),
		),
		array(
			'code'  => 'MC',
			'title' => __( 'Monaco', 'ohmylms' ),
		),
		array(
			'code'  => 'MN',
			'title' => __( 'Mongolia', 'ohmylms' ),
		),
		array(
			'code'  => 'ME',
			'title' => __( 'Montenegro', 'ohmylms' ),
		),
		array(
			'code'  => 'MS',
			'title' => __( 'Montserrat', 'ohmylms' ),
		),
		array(
			'code'  => 'MA',
			'title' => __( 'Morocco', 'ohmylms' ),
		),
		array(
			'code'  => 'MZ',
			'title' => __( 'Mozambique', 'ohmylms' ),
		),
		array(
			'code'  => 'MM',
			'title' => __( 'Myanmar', 'ohmylms' ),
		),
		array(
			'code'  => 'NA',
			'title' => __( 'Namibia', 'ohmylms' ),
		),
		array(
			'code'  => 'NR',
			'title' => __( 'Nauru', 'ohmylms' ),
		),
		array(
			'code'  => 'NP',
			'title' => __( 'Nepal', 'ohmylms' ),
		),
		array(
			'code'  => 'NL',
			'title' => __( 'Netherlands', 'ohmylms' ),
		),
		array(
			'code'  => 'NC',
			'title' => __( 'New Caledonia', 'ohmylms' ),
		),
		array(
			'code'  => 'NZ',
			'title' => __( 'New Zealand', 'ohmylms' ),
		),
		array(
			'code'  => 'NI',
			'title' => __( 'Nicaragua', 'ohmylms' ),
		),
		array(
			'code'  => 'NE',
			'title' => __( 'Niger', 'ohmylms' ),
		),
		array(
			'code'  => 'NG',
			'title' => __( 'Nigeria', 'ohmylms' ),
		),
		array(
			'code'  => 'NU',
			'title' => __( 'Niue', 'ohmylms' ),
		),
		array(
			'code'  => 'NF',
			'title' => __( 'Norfolk Island', 'ohmylms' ),
		),
		array(
			'code'  => 'MP',
			'title' => __( 'Northern Mariana Islands', 'ohmylms' ),
		),
		array(
			'code'  => 'KP',
			'title' => __( 'North Korea', 'ohmylms' ),
		),
		array(
			'code'  => 'NO',
			'title' => __( 'Norway', 'ohmylms' ),
		),
		array(
			'code'  => 'OM',
			'title' => __( 'Oman', 'ohmylms' ),
		),
		array(
			'code'  => 'PK',
			'title' => __( 'Pakistan', 'ohmylms' ),
		),
		array(
			'code'  => 'PS',
			'title' => __( 'Palestinian Territory', 'ohmylms' ),
		),
		array(
			'code'  => 'PA',
			'title' => __( 'Panama', 'ohmylms' ),
		),
		array(
			'code'  => 'PG',
			'title' => __( 'Papua New Guinea', 'ohmylms' ),
		),
		array(
			'code'  => 'PY',
			'title' => __( 'Paraguay', 'ohmylms' ),
		),
		array(
			'code'  => 'PE',
			'title' => __( 'Peru', 'ohmylms' ),
		),
		array(
			'code'  => 'PH',
			'title' => __( 'Philippines', 'ohmylms' ),
		),
		array(
			'code'  => 'PN',
			'title' => __( 'Pitcairn', 'ohmylms' ),
		),
		array(
			'code'  => 'PL',
			'title' => __( 'Poland', 'ohmylms' ),
		),
		array(
			'code'  => 'PT',
			'title' => __( 'Portugal', 'ohmylms' ),
		),
		array(
			'code'  => 'PR',
			'title' => __( 'Puerto Rico', 'ohmylms' ),
		),
		array(
			'code'  => 'QA',
			'title' => __( 'Qatar', 'ohmylms' ),
		),
		array(
			'code'  => 'RE',
			'title' => __( 'Reunion', 'ohmylms' ),
		),
		array(
			'code'  => 'RO',
			'title' => __( 'Romania', 'ohmylms' ),
		),
		array(
			'code'  => 'RU',
			'title' => __( 'Russia', 'ohmylms' ),
		),
		array(
			'code'  => 'RW',
			'title' => __( 'Rwanda', 'ohmylms' ),
		),
		array(
			'code'  => 'BL',
			'title' => __( 'Saint Barth&eacute;lemy', 'ohmylms' ),
		),
		array(
			'code'  => 'SH',
			'title' => __( 'Saint Helena', 'ohmylms' ),
		),
		array(
			'code'  => 'KN',
			'title' => __( 'Saint Kitts and Nevis', 'ohmylms' ),
		),
		array(
			'code'  => 'LC',
			'title' => __( 'Saint Lucia', 'ohmylms' ),
		),
		array(
			'code'  => 'MF',
			'title' => __( 'Saint Martin (French part)', 'ohmylms' ),
		),
		array(
			'code'  => 'SX',
			'title' => __( 'Saint Martin (Dutch part)', 'ohmylms' ),
		),
		array(
			'code'  => 'PM',
			'title' => __( 'Saint Pierre and Miquelon', 'ohmylms' ),
		),
		array(
			'code'  => 'VC',
			'title' => __( 'Saint Vincent and the Grenadines', 'ohmylms' ),
		),
		array(
			'code'  => 'SM',
			'title' => __( 'San Marino', 'ohmylms' ),
		),
		array(
			'code'  => 'ST',
			'title' => __( 'S&atilde;o Tom&eacute; and Pr&iacute;ncipe', 'ohmylms' ),
		),
		array(
			'code'  => 'SA',
			'title' => __( 'Saudi Arabia', 'ohmylms' ),
		),
		array(
			'code'  => 'SN',
			'title' => __( 'Senegal', 'ohmylms' ),
		),
		array(
			'code'  => 'RS',
			'title' => __( 'Serbia', 'ohmylms' ),
		),
		array(
			'code'  => 'SC',
			'title' => __( 'Seychelles', 'ohmylms' ),
		),
		array(
			'code'  => 'SL',
			'title' => __( 'Sierra Leone', 'ohmylms' ),
		),
		array(
			'code'  => 'SG',
			'title' => __( 'Singapore', 'ohmylms' ),
		),
		array(
			'code'  => 'SK',
			'title' => __( 'Slovakia', 'ohmylms' ),
		),
		array(
			'code'  => 'SI',
			'title' => __( 'Slovenia', 'ohmylms' ),
		),
		array(
			'code'  => 'SB',
			'title' => __( 'Solomon Islands', 'ohmylms' ),
		),
		array(
			'code'  => 'SO',
			'title' => __( 'Somalia', 'ohmylms' ),
		),
		array(
			'code'  => 'ZA',
			'title' => __( 'South Africa', 'ohmylms' ),
		),
		array(
			'code'  => 'GS',
			'title' => __( 'South Georgia/Sandwich Islands', 'ohmylms' ),
		),
		array(
			'code'  => 'KR',
			'title' => __( 'South Korea', 'ohmylms' ),
		),
		array(
			'code'  => 'SS',
			'title' => __( 'South Sudan', 'ohmylms' ),
		),
		array(
			'code'  => 'ES',
			'title' => __( 'Spain', 'ohmylms' ),
		),
		array(
			'code'  => 'LK',
			'title' => __( 'Sri Lanka', 'ohmylms' ),
		),
		array(
			'code'  => 'SD',
			'title' => __( 'Sudan', 'ohmylms' ),
		),
		array(
			'code'  => 'SR',
			'title' => __( 'Suriname', 'ohmylms' ),
		),
		array(
			'code'  => 'SJ',
			'title' => __( 'Svalbard and Jan Mayen', 'ohmylms' ),
		),
		array(
			'code'  => 'SZ',
			'title' => __( 'Swaziland', 'ohmylms' ),
		),
		array(
			'code'  => 'SE',
			'title' => __( 'Sweden', 'ohmylms' ),
		),
		array(
			'code'  => 'CH',
			'title' => __( 'Switzerland', 'ohmylms' ),
		),
		array(
			'code'  => 'SY',
			'title' => __( 'Syria', 'ohmylms' ),
		),
		array(
			'code'  => 'TW',
			'title' => __( 'Taiwan', 'ohmylms' ),
		),
		array(
			'code'  => 'TJ',
			'title' => __( 'Tajikistan', 'ohmylms' ),
		),
		array(
			'code'  => 'TZ',
			'title' => __( 'Tanzania', 'ohmylms' ),
		),
		array(
			'code'  => 'TH',
			'title' => __( 'Thailand', 'ohmylms' ),
		),
		array(
			'code'  => 'TL',
			'title' => __( 'Timor-Leste', 'ohmylms' ),
		),
		array(
			'code'  => 'TG',
			'title' => __( 'Togo', 'ohmylms' ),
		),
		array(
			'code'  => 'TK',
			'title' => __( 'Tokelau', 'ohmylms' ),
		),
		array(
			'code'  => 'TO',
			'title' => __( 'Tonga', 'ohmylms' ),
		),
		array(
			'code'  => 'TT',
			'title' => __( 'Trinidad and Tobago', 'ohmylms' ),
		),
		array(
			'code'  => 'TN',
			'title' => __( 'Tunisia', 'ohmylms' ),
		),
		array(
			'code'  => 'TR',
			'title' => __( 'Turkey', 'ohmylms' ),
		),
		array(
			'code'  => 'TM',
			'title' => __( 'Turkmenistan', 'ohmylms' ),
		),
		array(
			'code'  => 'TC',
			'title' => __( 'Turks and Caicos Islands', 'ohmylms' ),
		),
		array(
			'code'  => 'TV',
			'title' => __( 'Tuvalu', 'ohmylms' ),
		),
		array(
			'code'  => 'UG',
			'title' => __( 'Uganda', 'ohmylms' ),
		),
		array(
			'code'  => 'UA',
			'title' => __( 'Ukraine', 'ohmylms' ),
		),
		array(
			'code'  => 'AE',
			'title' => __( 'United Arab Emirates', 'ohmylms' ),
		),
		array(
			'code'  => 'GB',
			'title' => __( 'United Kingdom (UK)', 'ohmylms' ),
		),
		array(
			'code'  => 'US',
			'title' => __( 'United States (US)', 'ohmylms' ),
		),
		array(
			'code'  => 'UM',
			'title' => __( 'United States (US) Minor Outlying Islands', 'ohmylms' ),
		),
		array(
			'code'  => 'UY',
			'title' => __( 'Uruguay', 'ohmylms' ),
		),
		array(
			'code'  => 'UZ',
			'title' => __( 'Uzbekistan', 'ohmylms' ),
		),
		array(
			'code'  => 'VU',
			'title' => __( 'Vanuatu', 'ohmylms' ),
		),
		array(
			'code'  => 'VA',
			'title' => __( 'Vatican', 'ohmylms' ),
		),
		array(
			'code'  => 'VE',
			'title' => __( 'Venezuela', 'ohmylms' ),
		),
		array(
			'code'  => 'VN',
			'title' => __( 'Vietnam', 'ohmylms' ),
		),
		array(
			'code'  => 'VG',
			'title' => __( 'Virgin Islands (British)', 'ohmylms' ),
		),
		array(
			'code'  => 'VI',
			'title' => __( 'Virgin Islands (US)', 'ohmylms' ),
		),
		array(
			'code'  => 'WF',
			'title' => __( 'Wallis and Futuna', 'ohmylms' ),
		),
		array(
			'code'  => 'EH',
			'title' => __( 'Western Sahara', 'ohmylms' ),
		),
		array(
			'code'  => 'WS',
			'title' => __( 'Samoa', 'ohmylms' ),
		),
		array(
			'code'  => 'YE',
			'title' => __( 'Yemen', 'ohmylms' ),
		),
		array(
			'code'  => 'ZM',
			'title' => __( 'Zambia', 'ohmylms' ),
		),
		array(
			'code'  => 'ZW',
			'title' => __( 'Zimbabwe', 'ohmylms' ),
		),
	);
}

/**
 * Get all countries name
 *
 * @return array
 * @since 1.0.0
 * @param mixed $code Code.
 */
function ohmylms_get_country_name_by_code( $code ) {
	$countries = ohmylms_get_countries();
	if ( empty( $countries ) ) {
		return '';
	}

	foreach ( $countries as $country ) {
		if ( $country['code'] === $code ) {
			return $country['title'];
		}
	}

	return ''; // Return empty if not found.
}


/**
 * Converts a given hex color code to its RGB representation.
 *
 * @param string $hex A hex color code, optionally prefixed with a hash (#).
 * .
 * @return string The RGB representation of the given hex color code,
 *                in the format "R, G, B".
 */
function ohmylms_hex_to_rgb( $hex ) {
	$hex = str_replace( '#', '', $hex );

	if ( strlen( $hex ) === 3 ) {
		$r = hexdec( str_repeat( substr( $hex, 0, 1 ), 2 ) );
		$g = hexdec( str_repeat( substr( $hex, 1, 1 ), 2 ) );
		$b = hexdec( str_repeat( substr( $hex, 2, 1 ), 2 ) );
	} else {
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
	}

	return "$r, $g, $b";
}

/**
 * Get the dashboard URL for OhMyLMS.
 *
 * @return string The URL to the dashboard page.
 * @since 1.0.0
 */
function ohmylms_get_dashboard_url() {
	return ohmylms_get_page_url( 'profile' );
}


/**
 * Check if the student has access to purchase a course without logging in.
 * This function checks if the site allows guest purchases.
 *
 * @return bool True if guest purchases are allowed, false otherwise.
 * @since 1.0.0
 */
function ohmylms_is_guest_purchase_enabled() {
	$option  = get_option( 'ohmylms_allow_purchase_without_login', 'yes' );
	$default = 'yes' === $option;
	return apply_filters( 'ohmylms_allow_purchase_without_login', $default );
}

/**
 * Get payment gateway settings.
 *
 * @return array
 * @since 1.0.0
 */
function ohmylms_get_payment_gateways_settings() {
	$payment_gateways = ecommerce()->gateways()->get_payment_gateway_settings();
	return apply_filters( 'ohmylms_payment_gateways_settings', $payment_gateways, $payment_gateways );
}

/**
 * Get the content object
 *
 * @param string $content_type The content type.
 * @param int    $content_id The content ID.
 * .
 * @return object The content object.
 * @since 1.0.0
 */
function ohmylms_get_content_object( $content_type, $content_id ) {
	$default = null;
	if ( 'quiz' === $content_type ) {
		$default = ohmylms_get_quiz( $content_id );
	} else {
		$default = ohmylms_get_lesson( $content_id );
	}
	return apply_filters( 'ohmylms_get_content_object', $default, $content_type, $content_id );
}
