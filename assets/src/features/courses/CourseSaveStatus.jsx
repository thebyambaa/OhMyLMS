/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';

/**
 * Where the course stands: saving, edits not yet saved, or everything saved. Drafts save themselves
 * a moment after an edit; a course that is live changes only when the author presses Update.
 *
 * @param {Object}                   root0         Component properties.
 * @param {boolean}                  root0.dirty   There are edits that are not saved.
 * @param {boolean}                  root0.saving  A save is running.
 * @param {Date|null}                root0.savedAt When the last save finished.
 * @param {string}                   root0.status  The course status.
 * @param {(status: string) => void} root0.onSave  Save with a status.
 */
export function CourseSaveStatus( { dirty, saving, savedAt, status, onSave } ) {
	const live = status === 'publish' || status === 'future';
	let text = '';
	if ( saving ) {
		text = __( 'Saving…', 'ohmylms' );
	} else if ( dirty ) {
		text = live
			? __( 'You have changes that are not published yet.', 'ohmylms' )
			: __( 'You have unsaved changes.', 'ohmylms' );
	} else if ( savedAt ) {
		text = sprintf(
			/* translators: %s: time of day. */
			__( 'All changes saved at %s.', 'ohmylms' ),
			savedAt.toLocaleTimeString( [], {
				hour: '2-digit',
				minute: '2-digit',
			} )
		);
	}
	if ( ! text ) {
		return null;
	}
	return (
		<div className="ohmylms-course-save-status" role="status">
			{ saving && <Spinner /> }
			<span>{ text }</span>
			{ dirty && ! saving && (
				<Button
					variant="link"
					onClick={ () => onSave( live ? 'publish' : 'draft' ) }
				>
					{ live
						? __( 'Update course', 'ohmylms' )
						: __( 'Save draft', 'ohmylms' ) }
				</Button>
			) }
		</div>
	);
}
