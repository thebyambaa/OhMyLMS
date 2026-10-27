<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Integrations\Webhooks\Includes;

/**
 * Plugin hooks implementation.
 */
class Hooks {

	/**
	 * Initialize Hooks.
	 */
	public function __construct() {
		add_filter( 'ohmylms_integrations', array( $this, 'add_webhooks' ) );
		add_filter( 'ohmylms_should_enable_webhooks', array( $this, 'should_enable_webhooks' ) );
		add_action( 'update_option_ohmylms_integrations', array( $this, 'on_integration_updated' ), 10, 2 );
	}


	/**
	 * Add webhooks integration to the list of available integrations.
	 *
	 * @param array $integrations List of existing integrations.
	 * @return array Updated list of integrations with webhooks added.
	 * @since 1.0.0
	 */
	public function add_webhooks( $integrations ) {
		$integrations['webhooks'] = array(
			'label'       => __( 'Webhooks', 'ohmylms' ),
			'icon'        => OHMYLMS_PRO_URL . '/includes/Integrations/Webhooks/Assets/Images/webhook-icon.svg',
			'description' => __( 'Send automated notifications to external services when OhMyLMS events occur, enabling seamless integration with third-party platforms.', 'ohmylms' ),
			'categories'  => array( 'automation' ),
			'hasSettings' => false,
			'class'       => 'OhMyLMS\Integrations\Webhooks',
			'is_valid'    => true,
		);
		return $integrations;
	}

	/**
	 * Check if webhooks integration is enabled
	 *
	 * @param bool $should_enable Default value.
	 * @return bool Whether webhooks should be enabled.
	 * @since 1.0.0
	 */
	public function should_enable_webhooks( $should_enable ) {
		$integrations = get_option( 'ohmylms_integrations' );

		if ( empty( $integrations ) || ! is_array( $integrations ) ) {
			return false;
		}

		if ( ! isset( $integrations['webhooks']['is_enable'] ) ) {
			return false;
		}

		return 1 === (int) $integrations['webhooks']['is_enable'];
	}

	/**
	 * Handle integration update - trigger table creation when webhooks is enabled
	 *
	 * @param mixed $old_value Old integration settings.
	 * @param mixed $new_value New integration settings.
	 * @since 1.0.0
	 */
	public function on_integration_updated( $old_value, $new_value ) {
		// Check if webhooks was just enabled.
		$old_webhooks_enabled = isset( $old_value['webhooks']['is_enable'] ) && 1 === (int) $old_value['webhooks']['is_enable'];
		$new_webhooks_enabled = isset( $new_value['webhooks']['is_enable'] ) && 1 === (int) $new_value['webhooks']['is_enable'];

		// If webhooks just got enabled, trigger table creation.
		if ( ! $old_webhooks_enabled && $new_webhooks_enabled ) {
			WebhooksMigration::maybe_create_webhooks_table();
		}
	}
}
