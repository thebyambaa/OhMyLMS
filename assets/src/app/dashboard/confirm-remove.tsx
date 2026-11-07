import { __ } from '@wordpress/i18n';
import { Alert } from '../../ui/Alert';
import { Button } from '../../ui/Button';
import { Modal } from '../../ui/Modal';

/**
 * Confirmation before anything is removed. It names what will happen and keeps the server's reason
 * visible when a removal is refused.
 *
 * @param props           Props.
 * @param props.title     Dialog title.
 * @param props.children  What will be removed and what happens to it.
 * @param props.confirm   Label of the destructive button, for example "Move to trash".
 * @param props.busy      Whether the request is running.
 * @param props.error     Server message when the removal was refused.
 * @param props.onConfirm Proceed.
 * @param props.onCancel  Close without removing.
 */
export function ConfirmRemove( {
	title,
	children,
	confirm,
	busy,
	error,
	onConfirm,
	onCancel,
}: {
	title: string;
	children: React.ReactNode;
	confirm: string;
	busy: boolean;
	error: string;
	onConfirm: () => void;
	onCancel: () => void;
} ) {
	return (
		<Modal title={ title } onRequestClose={ onCancel }>
			<div>{ children }</div>
			{ error && <Alert tone="danger">{ error }</Alert> }
			<p className="oml-dash-actions">
				<Button variant="danger" loading={ busy } onClick={ onConfirm }>
					{ confirm }
				</Button>
				<Button onClick={ onCancel }>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
			</p>
		</Modal>
	);
}
