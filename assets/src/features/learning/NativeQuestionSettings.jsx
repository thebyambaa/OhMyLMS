import { createElement, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { loadBankQuestion } from '../question-bank/api.mjs';
import { QuestionLivePreview } from '../question-editor/QuestionLivePreview';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/** Keep bank navigation and approval semantics visible beside the standalone canvas. */
function NativeQuestionSettings() {
	const type = useSelect(
		( getStore ) => getStore( 'core/editor' ).getCurrentPostType(),
		[]
	);
	const { id, locked } = useSelect(
		( getStore ) => ( {
			id: getStore( 'core/editor' ).getCurrentPostId(),
			locked:
				getStore( 'core/editor' ).isPostSavingLocked() ||
				getStore( 'core/editor' ).isSavingPost(),
		} ),
		[]
	);
	const [ preview, setPreview ] = useState( null );
	const [ error, setError ] = useState( '' );
	if ( type !== 'ohmylms-question' ) {
		return null;
	}
	return (
		<PluginDocumentSettingPanel
			name="ohmylms-native-question"
			title={ __( 'Question authoring', 'ohmylms' ) }
			initialOpen
		>
			<p>
				{ __(
					'Question edits save to your bank draft automatically. Use Approve for shared use in the question settings to approve a version. Saving the document does not approve it.',
					'ohmylms'
				) }
			</p>
			<Button
				variant="secondary"
				disabled={ locked }
				onClick={ async () => {
					try {
						const fresh = await loadBankQuestion( id );
						setPreview( { ...fresh, questions: fresh.options } );
						setError( '' );
					} catch ( cause ) {
						setError( cause.message );
					}
				} }
			>
				{ __( 'Preview question', 'ohmylms' ) }
			</Button>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ preview && <QuestionLivePreview question={ preview } /> }
			<Button
				variant="link"
				href={ window.ohmylmsNativeLesson.returnUrl }
			>
				{ __( 'Back to content', 'ohmylms' ) }
			</Button>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'ohmylms-native-question-settings', {
	render: NativeQuestionSettings,
} );
