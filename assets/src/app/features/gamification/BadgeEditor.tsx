import { Button, Modal, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	ColorField,
	NumberField,
	SelectField,
	TextAreaField,
	TextField,
} from './fields';
import { MediaPicker } from './MediaPicker';
import { badgeToDraft, newCriterion, validateBadge } from './model';
import type {
	AwardSource,
	Badge,
	BadgeDraft,
	CriterionDraft,
	FailureView,
	FieldErrors,
	GamificationOptions,
	Limits,
} from './types';

/**
 * Create or edit a badge in a dialog: name, image from the media library, colour and the
 * conditions that award it. Conditions use the server's fixed metric and operator lists.
 *
 * @param props          Component props.
 * @param props.badge    Badge to edit, or `undefined` to create one.
 * @param props.options  Allow-lists from the server, if loaded.
 * @param props.limits   Server limits.
 * @param props.working  Whether a save is running.
 * @param props.error    Failure of the last save.
 * @param props.onSave   Saves the draft; resolves true when it was stored.
 * @param props.onCancel Closes the dialog.
 */
export function BadgeEditor( {
	badge,
	options,
	limits,
	working,
	error,
	onSave,
	onCancel,
}: {
	badge?: Badge;
	options: GamificationOptions | null;
	limits: Limits;
	working: boolean;
	error: FailureView | null;
	onSave: ( draft: BadgeDraft ) => Promise< boolean >;
	onCancel: () => void;
} ) {
	const [ draft, setDraft ] = useState< BadgeDraft >( badgeToDraft( badge ) );
	const [ errors, setErrors ] = useState< FieldErrors >( {} );
	const [ picking, setPicking ] = useState( false );
	const shown = { ...( error?.fieldErrors ?? {} ), ...errors };
	const metrics = options?.metrics ?? [
		{ value: 'points' as const, label: __( 'Bonus points', 'ohmylms' ) },
		{
			value: 'completed_lesson' as const,
			label: __( 'Completed lessons', 'ohmylms' ),
		},
		{
			value: 'completed_courses' as const,
			label: __( 'Completed courses', 'ohmylms' ),
		},
	];
	const operators = options?.operators ?? [
		{ value: '>=' as const, label: '>=' },
		{ value: '>' as const, label: '>' },
		{ value: '<=' as const, label: '<=' },
		{ value: '<' as const, label: '<' },
		{ value: '==' as const, label: '==' },
		{ value: '!=' as const, label: '!=' },
	];

	function change( patch: Partial< BadgeDraft > ) {
		setDraft( ( current ) => ( { ...current, ...patch } ) );
		setErrors( {} );
	}

	function changeCriterion(
		index: number,
		patch: Partial< CriterionDraft >
	) {
		change( {
			criteria: draft.criteria.map( ( criterion, position ) =>
				position === index ? { ...criterion, ...patch } : criterion
			),
		} );
	}

	async function submit() {
		const found = validateBadge( draft, limits );
		if ( Object.keys( found ).length ) {
			setErrors( found );
			return;
		}
		await onSave( draft );
	}

	const sources: { value: AwardSource; label: string }[] = [
		{
			value: 'criteria',
			label: __( 'When the conditions are met', 'ohmylms' ),
		},
		{
			value: 'streak',
			label: __( 'By a learning streak milestone', 'ohmylms' ),
		},
	];

	return (
		<Modal
			title={
				badge
					? __( 'Edit badge', 'ohmylms' )
					: __( 'Add badge', 'ohmylms' )
			}
			onRequestClose={ () => {
				if ( ! working && ! picking ) {
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
				<div>
					{ draft.image_url ? (
						<img
							src={ draft.image_url }
							alt={ __( 'Selected badge image', 'ohmylms' ) }
							width={ 64 }
							height={ 64 }
						/>
					) : (
						<p>{ __( 'No image selected.', 'ohmylms' ) }</p>
					) }
					<Button
						variant="secondary"
						onClick={ () => setPicking( true ) }
					>
						{ draft.image_id > 0
							? __( 'Change image', 'ohmylms' )
							: __( 'Choose image', 'ohmylms' ) }
					</Button>{ ' ' }
					{ draft.image_id > 0 && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () =>
								change( { image_id: 0, image_url: null } )
							}
						>
							{ __( 'Remove image', 'ohmylms' ) }
						</Button>
					) }
				</div>
				<ColorField
					label={ __( 'Colour', 'ohmylms' ) }
					pickerLabel={ __( 'Pick a colour', 'ohmylms' ) }
					value={ draft.color }
					error={ shown.color }
					onChange={ ( color ) => change( { color } ) }
				/>
				<SelectField
					label={ __( 'Award the badge', 'ohmylms' ) }
					value={ draft.award_source }
					options={ sources }
					onChange={ ( value ) => change( { award_source: value } ) }
				/>
				{ 'criteria' === draft.award_source && (
					<fieldset>
						<legend>
							{ __( 'Conditions (all must be met)', 'ohmylms' ) }
						</legend>
						{ shown.criteria && (
							<p role="alert">{ shown.criteria }</p>
						) }
						{ draft.criteria.map( ( criterion, index ) => (
							<div key={ index }>
								<SelectField
									label={ sprintf(
										/* translators: %d: condition number. */
										__(
											'Condition %d: measure',
											'ohmylms'
										),
										index + 1
									) }
									value={ criterion.metric }
									options={ metrics }
									onChange={ ( metric ) =>
										changeCriterion( index, { metric } )
									}
								/>
								<SelectField
									label={ sprintf(
										/* translators: %d: condition number. */
										__(
											'Condition %d: comparison',
											'ohmylms'
										),
										index + 1
									) }
									value={ criterion.operator }
									options={ operators }
									onChange={ ( operator ) =>
										changeCriterion( index, { operator } )
									}
								/>
								<NumberField
									label={ sprintf(
										/* translators: %d: condition number. */
										__( 'Condition %d: value', 'ohmylms' ),
										index + 1
									) }
									value={ criterion.value }
									min={ 0 }
									max={ limits.max_criterion_value }
									error={
										shown[ `criteria.${ index }.value` ]
									}
									onChange={ ( value ) =>
										changeCriterion( index, { value } )
									}
								/>
								<Button
									variant="tertiary"
									isDestructive
									disabled={ draft.criteria.length <= 1 }
									accessibleWhenDisabled
									onClick={ () =>
										change( {
											criteria: draft.criteria.filter(
												( _, position ) =>
													position !== index
											),
										} )
									}
								>
									{ sprintf(
										/* translators: %d: condition number. */
										__( 'Remove condition %d', 'ohmylms' ),
										index + 1
									) }
								</Button>
							</div>
						) ) }
						<Button
							variant="secondary"
							disabled={
								draft.criteria.length >= limits.max_criteria
							}
							accessibleWhenDisabled
							onClick={ () =>
								change( {
									criteria: [
										...draft.criteria,
										newCriterion(),
									],
								} )
							}
						>
							{ __( 'Add condition', 'ohmylms' ) }
						</Button>
					</fieldset>
				) }
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
							: __( 'Save badge', 'ohmylms' ) }
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
			{ picking && (
				<MediaPicker
					title={ __( 'Choose a badge image', 'ohmylms' ) }
					onClose={ () => setPicking( false ) }
					onSelect={ ( image ) => {
						change( {
							image_id: image.id,
							image_url: image.thumbnail_url,
						} );
						setPicking( false );
					} }
				/>
			) }
		</Modal>
	);
}
