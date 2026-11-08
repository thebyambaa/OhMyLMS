<?php
/**
 * The AI tutor card on the Add-ons page.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Lists the AI tutor as an add-on. Its switch is the tutor's own `enabled` setting, so the Add-ons list and the
 * AI tutor settings popup always agree.
 */
final class Integration {
	/** Add-on id; the Add-ons REST routes accept letters, digits, dashes and underscores. */
	const ID = 'ai-tutor';

	/** Register the card and the switch handler. */
	public static function init() {
		add_filter( 'ohmylms_integrations', array( __CLASS__, 'manifest' ) );
		add_action( 'ohmylms_integration_' . self::ID . '_updated', array( __CLASS__, 'switch_tutor' ) );
	}

	/**
	 * Add the AI tutor card.
	 *
	 * @param array $integrations Existing integration definitions.
	 * @return array
	 */
	public static function manifest( $integrations ) {
		$settings                = Settings::get();
		$integrations[ self::ID ] = array(
			'label'       => __( 'AI tutor', 'ohmylms' ),
			'description' => __( 'Hints and explanations for learners, and an exam converter, from the AI provider you connect.', 'ohmylms' ),
			'icon'        => '',
			'categories'  => array( 'connections' ),
			'hasSettings' => true,
			'class'       => '',
			'is_valid'    => true,
			'is_enable'   => empty( $settings['enabled'] ) ? 0 : 1,
		);

		return $integrations;
	}

	/**
	 * Turn the tutor on or off from the Add-ons switch, keeping every other AI setting.
	 *
	 * @param int $is_enable 1 to enable, 0 to disable.
	 */
	public static function switch_tutor( $is_enable ) {
		$stored            = get_option( Settings::OPTION, array() );
		$stored            = is_array( $stored ) ? $stored : array();
		$stored['enabled'] = 1 === (int) $is_enable;
		update_option( Settings::OPTION, $stored, false );
	}
}
