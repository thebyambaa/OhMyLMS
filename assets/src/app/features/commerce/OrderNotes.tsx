import { Button, Notice, TextareaControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addNote } from './api';
import { useNotes } from './hooks';
import { NOTE_MAX_LENGTH, formatDate, validateNote } from './model';
import { useOperation } from './useOperation';

/**
 * Plain text notes of one order, with a form to add one.
 *
 * @param props         Component props.
 * @param props.orderId Order ID.
 */
export function OrderNotes( { orderId }: { orderId: number } ) {
	const notes = useNotes( orderId );
	const operation = useOperation();
	const [ text, setText ] = useState( '' );
	const [ problem, setProblem ] = useState< string | null >( null );

	const submit = async () => {
		const invalid = validateNote( text );
		setProblem( invalid );
		if ( invalid ) {
			return;
		}
		const saved = await operation.run( async ( signal ) => {
			const note = await addNote( orderId, text.trim(), signal );
			notes.replace( [ note, ...( notes.data ?? [] ) ] );
		} );
		if ( saved ) {
			setText( '' );
		}
	};

	return (
		<section aria-labelledby="order-notes-heading">
			<h3 id="order-notes-heading">{ __( 'Notes', 'ohmylms' ) }</h3>
			{ notes.error && (
				<Notice status="error" isDismissible={ false }>
					{ notes.error.message }{ ' ' }
					<Button variant="link" onClick={ notes.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ notes.loading && ! notes.data && (
				<p role="status">{ __( 'Loading notes…', 'ohmylms' ) }</p>
			) }
			{ notes.data && ! notes.data.length && (
				<p>{ __( 'No notes yet.', 'ohmylms' ) }</p>
			) }
			{ !! notes.data?.length && (
				<ul aria-label={ __( 'Order notes', 'ohmylms' ) }>
					{ notes.data.map( ( note ) => (
						<li key={ note.id }>
							<p style={ { whiteSpace: 'pre-wrap' } }>
								{ note.content }
							</p>
							<small>
								{ note.added_by }
								{ ' · ' }
								{ formatDate( note.date_created ) }
							</small>
						</li>
					) ) }
				</ul>
			) }
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					if ( ! operation.busy ) {
						void submit();
					}
				} }
			>
				{ operation.error && (
					<Notice status="error" isDismissible={ false }>
						{ operation.error }
					</Notice>
				) }
				<TextareaControl
					label={ __( 'Add a note', 'ohmylms' ) }
					help={
						problem ??
						__(
							'Plain text for staff. Markup is removed and the student is not notified.',
							'ohmylms'
						)
					}
					value={ text }
					maxLength={ NOTE_MAX_LENGTH }
					disabled={ operation.busy }
					onChange={ ( value ) => {
						setText( value );
						setProblem( null );
					} }
				/>
				<Button
					type="submit"
					variant="secondary"
					isBusy={ operation.busy }
					disabled={ operation.busy }
				>
					{ __( 'Add note', 'ohmylms' ) }
				</Button>
			</form>
		</section>
	);
}
