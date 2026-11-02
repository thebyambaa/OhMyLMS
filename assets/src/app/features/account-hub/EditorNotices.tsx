import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Confirmation shown when a link would discard unsaved edits.
 *
 * @param props                   Component props.
 * @param props.guard             Result of useUnsavedChangesGuard.
 * @param props.guard.pendingPath Link waiting for a decision.
 * @param props.guard.stay        Cancel the navigation.
 * @param props.guard.leave       Continue to the link.
 */
export function LeaveNotice( {
	guard,
}: {
	guard: { pendingPath: string | null; stay: () => void; leave: () => void };
} ) {
	if ( ! guard.pendingPath ) {
		return null;
	}
	return (
		<Notice status="warning" isDismissible={ false }>
			<p>
				{ __(
					'You have unsaved changes. Leave this page and discard them?',
					'ohmylms'
				) }
			</p>
			<Button variant="primary" isDestructive onClick={ guard.leave }>
				{ __( 'Discard and leave', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button variant="secondary" onClick={ guard.stay }>
				{ __( 'Stay on this page', 'ohmylms' ) }
			</Button>
		</Notice>
	);
}

/**
 * Stale-save message with a reload action.
 *
 * @param props          Component props.
 * @param props.onReload Reload the record from the server.
 */
export function ConflictNotice( { onReload }: { onReload: () => void } ) {
	return (
		<Notice status="warning" isDismissible={ false }>
			{ __(
				'Someone else changed this record after you opened it. Reload to see their version; your unsaved edits will be lost.',
				'ohmylms'
			) }{ ' ' }
			<Button variant="secondary" onClick={ onReload }>
				{ __( 'Reload', 'ohmylms' ) }
			</Button>
		</Notice>
	);
}
