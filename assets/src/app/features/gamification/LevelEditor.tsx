import { Button, Modal, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ColorField, NumberField, TextAreaField, TextField } from './fields';
import { levelToDraft, validateLevel } from './model';
import type {
	FailureView,
	FieldErrors,
	Level,
	LevelDraft,
	Limits,
} from './types';

/**
 * Create or edit a level in a dialog. The draft is validated like the server: a name, colours
 * and a points threshold that no other level uses, so the ladder always increases.
 *
 * @param props          Component props.
 * @param props.level    Level to edit, or `undefined` to create one.
 * @param props.levels   All levels (for the threshold check).
 * @param props.limits   Server limits.
 * @param props.working  Whether a save is running.
 * @param props.error    Failure of the last save.
 * @param props.onSave   Saves the draft; resolves true when it was stored.
 * @param props.onCancel Closes the dialog.
 */
export function LevelEditor( {
	level,
	levels,
	limits,
	working,
	error,
	onSave,
	onCancel,
}: {
	level?: Level;
	levels: readonly Level[];
	limits: Limits;
	working: boolean;
	error: FailureView | null;
	onSave: ( draft: LevelDraft ) => Promise< boolean >;
	onCancel: () => void;
} ) {
	const [ draft, setDraft ] = useState< LevelDraft >( levelToDraft( level ) );
	const [ errors, setErrors ] = useState< FieldErrors >( {} );
	const shown = { ...( error?.fieldErrors ?? {} ), ...errors };

	function change( patch: Partial< LevelDraft > ) {
		setDraft( ( current ) => ( { ...current, ...patch } ) );
		setErrors( {} );
	}

	async function submit() {
		const found = validateLevel(
			draft,
			levels,
			level?.slug ?? null,
			limits
		);
		if ( Object.keys( found ).length ) {
			setErrors( found );
			return;
		}
		await onSave( draft );
	}

	return (
		<Modal
			title={
				level
					? __( 'Edit level', 'ohmylms' )
					: __( 'Add level', 'ohmylms' )
			}
			onRequestClose={ () => {
				if ( ! working ) {
					onCancel();
				}
			} }
			shouldCloseOnClickOutside={ false }
		>
			<form
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					void submit();
				} }
			>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error.message }
					</Notice>
				) }
				<TextField
					label={ __( 'Name', 'ohmylms' ) }
					value={ draft.name }
					maxLength={ 60 }
					error={ shown.name }
					onChange={ ( name ) => change( { name } ) }
				/>
				<TextAreaField
					label={ __( 'Description', 'ohmylms' ) }
					value={ draft.description }
					error={ shown.description }
					onChange={ ( description ) => change( { description } ) }
				/>
				<NumberField
					label={ __( 'Points needed', 'ohmylms' ) }
					help={ __(
						'Each level needs more points than the one before it.',
						'ohmylms'
					) }
					value={ draft.points }
					min={ 0 }
					max={ limits.max_criterion_value }
					error={ shown.points }
					onChange={ ( points ) => change( { points } ) }
				/>
				<ColorField
					label={ __( 'Background colour', 'ohmylms' ) }
					pickerLabel={ __( 'Pick a background colour', 'ohmylms' ) }
					value={ draft.color }
					error={ shown.color }
					onChange={ ( color ) => change( { color } ) }
				/>
				<ColorField
					label={ __( 'Text colour', 'ohmylms' ) }
					pickerLabel={ __( 'Pick a text colour', 'ohmylms' ) }
					value={ draft.text_color }
					error={ shown.text_color }
					onChange={ ( value ) => change( { text_color: value } ) }
				/>
				<p>
					<Button
						variant="primary"
						type="submit"
						isBusy={ working }
						disabled={ working }
						accessibleWhenDisabled
					>
						{ working
							? __( 'Saving…', 'ohmylms' )
							: __( 'Save level', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ working }
						onClick={ onCancel }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</p>
			</form>
		</Modal>
	);
}
