import { Button, Modal, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { FailureView } from './types';

/**
 * Confirmation before a destructive action. The dialog stays open while the request runs and
 * shows the server's error if it fails.
 *
 * @param props           Component props.
 * @param props.title     Dialog title.
 * @param props.message   What will happen.
 * @param props.working   Whether the request is running.
 * @param props.error     Failure of the last attempt.
 * @param props.onConfirm Called when the user confirms.
 * @param props.onCancel  Called to close the dialog.
 */
export function ConfirmDelete( {
	title,
	message,
	working,
	error,
	onConfirm,
	onCancel,
}: {
	title: string;
	message: string;
	working: boolean;
	error: FailureView | null;
	onConfirm: () => void;
	onCancel: () => void;
} ) {
	return (
		<Modal
			title={ title }
			onRequestClose={ () => {
				if ( ! working ) {
					onCancel();
				}
			} }
			shouldCloseOnClickOutside={ false }
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error.message }
				</Notice>
			) }
			<p>{ message }</p>
			<Button
				variant="primary"
				isDestructive
				isBusy={ working }
				disabled={ working }
				accessibleWhenDisabled
				onClick={ onConfirm }
			>
				{ working
					? __( 'Deleting…', 'ohmylms' )
					: __( 'Delete', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button
				variant="tertiary"
				disabled={ working }
				onClick={ onCancel }
			>
				{ __( 'Cancel', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
