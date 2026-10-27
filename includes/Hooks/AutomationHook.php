<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Automation hook for OhMyLMS connector registration
 *
 * @package    OhMyLMSPro
 * @subpackage OhMyLMS\Hooks
 */

namespace OhMyLMS\Hooks;

/**
 * Class AutomationHook
 *
 * Registers OhMyLMS automation connector to MRM.
 */
class AutomationHook {

	/**
	 * Register all automation-related hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'mrm_automation_connectors', array( $this, 'ohmylms_automation_connectors' ), 10, 1 );
	}

	/**
	 * Add OhMyLMS connector to the list of MRM automation connectors.
	 *
	 * @param array $connectors List of existing connectors.
	 * @return array Updated list with OhMyLMS connector.
	 */
	public function ohmylms_automation_connectors( $connectors ) {
		// Mail Mint loads connectors from its own namespace (apart from one built-in exception for
		// another plugin's key), so expose ours there under a short class name.
		$mail_mint_connector = 'MintMail\\App\\Internal\\Automation\\Connector\\ConnectorOhMyLms';
		if ( ! class_exists( $mail_mint_connector, false ) ) {
			class_alias( 'OhMyLMS\\Automation\\ConnectorOhMyLms', $mail_mint_connector );
		}
		// Mail Mint also looks up '<connector name>Triggers' there to validate trigger settings.
		$mail_mint_triggers = 'MintMail\\App\\Internal\\Automation\\Connector\\trigger\\OhMyLMSTriggers';
		if ( ! class_exists( $mail_mint_triggers, false ) ) {
			class_alias( 'OhMyLMS\\Automation\\Triggers\\OhMyLmsTriggers', $mail_mint_triggers );
		}

		$connectors['ohmylms'] = array(
			'class_name' => 'ConnectorOhMyLms',
		);

		return $connectors;
	}
}
