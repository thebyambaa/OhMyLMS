import { createElement, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { examEditorHref } from './examEditor.mjs';

/**
 * Name a new exam and open its questions in the Gutenberg quiz editor.
 * @param {Object}     root0         Component properties.
 * @param {() => void} root0.onClose Called when the dialog is dismissed.
 */
export function NewExamDialog( { onClose } ) {
	const [ title, setTitle ] = useState( '' );
	const [ minutes, setMinutes ] = useState( '60' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const create = async () => {
		setBusy( true );
		setError( '' );
		try {
			const exam = await window.wp.apiFetch( {
				path: '/ohmylms/v1/exams/compose',
				method: 'POST',
				data: { title, duration_minutes: Number( minutes ) },
			} );
			window.location.assign( examEditorHref( exam ) );
		} catch ( failure ) {
			setError(
				failure.message ||
					__( 'The exam could not be created.', 'ohmylms' )
			);
			setBusy( false );
		}
	};
	return (
		<Modal
			title={ __( 'New exam', 'ohmylms' ) }
			onRequestClose={ busy ? undefined : onClose }
		>
			<p>
				{ __(
					'Name the exam and set its time. You will write the questions in the block editor, the same one you use for quizzes.',
					'ohmylms'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<TextControl
				label={ __( 'Exam title', 'ohmylms' ) }
				value={ title }
				onChange={ setTitle }
			/>
			<TextControl
				label={ __( 'Duration (minutes)', 'ohmylms' ) }
				type="number"
				min="1"
				max="1440"
				value={ minutes }
				onChange={ setMinutes }
			/>
			<Button
				variant="primary"
				isBusy={ busy }
				disabled={
					busy ||
					! title.trim() ||
					! ( Number( minutes ) >= 1 && Number( minutes ) <= 1440 )
				}
				onClick={ create }
			>
				{ __( 'Create and add questions', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button variant="tertiary" disabled={ busy } onClick={ onClose }>
				{ __( 'Cancel', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
