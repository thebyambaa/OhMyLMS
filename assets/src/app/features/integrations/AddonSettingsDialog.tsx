import { Button, Modal } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { AiTutorForm, McpForm, XapiForm } from '../settings/public';
import type { Integration } from './types';

interface Props {
	integration: Integration;
	onClose: () => void;
}

/**
 * Popup for the add-ons that have their own settings form (xAPI, AI tutor, MCP). The forms save themselves.
 *
 * @param props             Component props.
 * @param props.integration Add-on being configured.
 * @param props.onClose     Called when the popup is dismissed.
 */
export default function AddonSettingsDialog( { integration, onClose }: Props ) {
	const [ dirty, setDirty ] = useState( false );
	const reportDirty = useCallback(
		( value: boolean ) => setDirty( value ),
		[]
	);
	function close() {
		if (
			! dirty ||
			// eslint-disable-next-line no-alert -- A native confirm keeps the dismissal synchronous.
			window.confirm(
				__(
					'You have unsaved settings. Close and discard them?',
					'ohmylms'
				)
			)
		) {
			onClose();
		}
	}
	return (
		<Modal
			title={ sprintf(
				/* translators: %s: add-on name. */
				__( '%s settings', 'ohmylms' ),
				integration.label
			) }
			onRequestClose={ close }
			shouldCloseOnClickOutside={ false }
			size="large"
		>
			{ 'xapi' === integration.id && (
				<XapiForm active onDirty={ reportDirty } />
			) }
			{ 'ai-tutor' === integration.id && (
				<AiTutorForm active onDirty={ reportDirty } />
			) }
			{ 'mcp' === integration.id && <McpForm active /> }
			<p>
				<Button variant="tertiary" onClick={ close }>
					{ __( 'Done', 'ohmylms' ) }
				</Button>
			</p>
		</Modal>
	);
}
