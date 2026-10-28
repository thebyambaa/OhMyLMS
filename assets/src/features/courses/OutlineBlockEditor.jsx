/** @jsx createElement */
import { createElement, useRef, useState } from '@wordpress/element';
import { Button, Modal, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Keep the complete native editor and its extensions isolated from the course app.
 *
 * @param {Object}     root0          Component properties.
 * @param {Object}     root0.activity Resource to edit.
 * @param {() => void} root0.onClose  Return to the outline.
 */
export function OutlineBlockEditor( { activity, onClose } ) {
	const frame = useRef( null );
	const [ loaded, setLoaded ] = useState( false );
	const [ warning, setWarning ] = useState( '' );
	const [ canDiscard, setCanDiscard ] = useState( false );
	const url = new URL( activity.edit_url, window.location.href );
	// Local sites can expose the same admin under several ports. Keep cookies and stores same-origin.
	const source = `${ window.location.origin }${ url.pathname }${ url.search }`;
	function close() {
		try {
			const store =
				frame.current?.contentWindow?.wp?.data?.select( 'core/editor' );
			if ( ! loaded || ! store || store.isSavingPost() ) {
				setCanDiscard( false );
				setWarning(
					__(
						'Wait for the editor to finish loading or saving.',
						'ohmylms'
					)
				);
				return;
			}
			if ( store.isEditedPostDirty() ) {
				setCanDiscard( true );
				setWarning(
					__(
						'You have unsaved content. Save in the block editor, or discard your changes before closing.',
						'ohmylms'
					)
				);
				return;
			}
			onClose();
		} catch {
			setCanDiscard( true );
			setWarning(
				__(
					'The editor is unavailable. Open the full editor if needed, or close this panel.',
					'ohmylms'
				)
			);
		}
	}
	return (
		<Modal
			title={ __( 'Edit blocks', 'ohmylms' ) }
			className="ohmylms-outline-block-editor"
			onRequestClose={ close }
			shouldCloseOnClickOutside={ false }
		>
			<div className="ohmylms-outline-block-editor-help">
				<p>
					{ __(
						'Edit and save the shared resource here. Course placement and skill connections stay in the outline.',
						'ohmylms'
					) }
				</p>
				<Button variant="secondary" onClick={ close }>
					{ __( 'Back to outline', 'ohmylms' ) }
				</Button>
			</div>
			{ warning && (
				<Notice status="warning" isDismissible={ false }>
					{ warning }
					{ canDiscard && (
						<Button isDestructive onClick={ onClose }>
							{ __( 'Close without saving', 'ohmylms' ) }
						</Button>
					) }
				</Notice>
			) }
			{ ! loaded && <Spinner /> }
			<iframe
				ref={ frame }
				src={ source }
				title={ __( 'Visual block editor', 'ohmylms' ) }
				onLoad={ () => setLoaded( true ) }
			/>
		</Modal>
	);
}
