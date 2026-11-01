/** @jsx createElement */
import { createElement, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { createLearnerPreview } from './api.mjs';

/**
 * "Preview as learner" in the native lesson editor: opens the saved lesson in the learner player
 * through a signed link that expires, as an enrolled learner who completed nothing. The tab is opened
 * during the click, so popup blockers allow it; a blocked tab gets the link instead.
 *
 * @param {Object} props          Component properties.
 * @param {number} props.lessonId Lesson ID.
 * @return {React.ReactElement} Settings panel.
 */
export function LearnerPreviewPanel( { lessonId } ) {
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ blocked, setBlocked ] = useState( '' );
	const { dirty, saved } = useSelect(
		( select ) => ( {
			dirty: select( 'core/editor' ).isEditedPostDirty(),
			saved:
				'auto-draft' !==
				select( 'core/editor' ).getEditedPostAttribute( 'status' ),
		} ),
		[]
	);
	const open = async () => {
		setError( '' );
		setBlocked( '' );
		const tab = window.open( '', '_blank' );
		if ( tab ) {
			tab.opener = null;
		}
		setBusy( true );
		try {
			const preview = await createLearnerPreview( lessonId );
			if ( tab && ! tab.closed ) {
				tab.location.href = preview.url;
			} else {
				setBlocked( preview.url );
			}
		} catch ( failure ) {
			tab?.close();
			setError(
				/** @type {{ message?: string }} */ ( failure )?.message ||
					__( 'The preview could not be opened.', 'ohmylms' )
			);
		} finally {
			setBusy( false );
		}
	};
	return (
		<PluginDocumentSettingPanel
			name="ohmylms-learner-preview"
			title={ __( 'Learner preview', 'ohmylms' ) }
		>
			<p>
				{ __(
					'See the lesson exactly as an enrolled learner who has completed nothing: drip, prerequisites and the course order apply, and you can bypass them from the preview. Nothing is recorded.',
					'ohmylms'
				) }
			</p>
			{ dirty && (
				<p>
					{ __(
						'The preview shows the last saved version.',
						'ohmylms'
					) }
				</p>
			) }
			<Button
				variant="secondary"
				isBusy={ busy }
				disabled={ busy || ! saved }
				onClick={ open }
			>
				{ __( 'Preview as learner', 'ohmylms' ) }
			</Button>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ blocked && (
				<Notice status="warning" isDismissible={ false }>
					<a href={ blocked } target="_blank" rel="noreferrer">
						{ __( 'Open the learner preview', 'ohmylms' ) }
					</a>
				</Notice>
			) }
		</PluginDocumentSettingPanel>
	);
}
