import {
	CheckboxControl,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { getSkills, listBanks } from './api';
import { MediaChoice } from './MediaChoice';
import { SkillPicker } from './SkillPicker';
import {
	flag,
	MAX_SOURCE,
	MAX_SUPPORTING,
	MAX_TEXT_SETTING,
	sharedSettings,
	skillParts,
} from './model';
import { errorId, FieldError } from './OptionsEditor';
import type {
	BankRef,
	FormErrors,
	FormMedia,
	QuestionSettings,
	SkillMap,
	SkillRef,
	SkillRoles,
} from './types';

/**
 * Settings every type shares: required, shuffled options, partial credit and the matching layout
 * where they apply, and the hint and explanation.
 *
 * @param props          Component props.
 * @param props.type     Question type id.
 * @param props.settings Current settings.
 * @param props.errors   Field errors (`hint`, `explanation`).
 * @param props.disabled Whether editing is off.
 * @param props.onChange Merges a settings patch.
 */
export function SharedSettingsFields( {
	type,
	settings,
	errors,
	disabled,
	onChange,
}: {
	type: string;
	settings: QuestionSettings;
	errors: FormErrors;
	disabled: boolean;
	onChange: ( patch: QuestionSettings ) => void;
} ) {
	const applies = sharedSettings( type );
	const text = ( key: 'hint' | 'explanation' ) =>
		'string' === typeof settings[ key ]
			? ( settings[ key ] as string )
			: '';
	return (
		<>
			<fieldset disabled={ disabled }>
				<legend>{ __( 'Answer settings', 'ohmylms' ) }</legend>
				{ applies.required && (
					<CheckboxControl
						label={ __( 'Learners must answer', 'ohmylms' ) }
						checked={ flag( settings.required ) }
						onChange={ ( required ) => onChange( { required } ) }
					/>
				) }
				{ applies.randomize && (
					<CheckboxControl
						label={ __(
							'Shuffle the answer options for each learner',
							'ohmylms'
						) }
						checked={ flag( settings.randomize ) }
						onChange={ ( randomize ) => onChange( { randomize } ) }
					/>
				) }
				{ applies.partialCredit && (
					<CheckboxControl
						label={ __(
							'Give part of the points for partly correct answers',
							'ohmylms'
						) }
						checked={ flag(
							settings.partial_credit,
							'fill-in-the-blank' === type
						) }
						onChange={ ( partial ) =>
							onChange( { partial_credit: partial } )
						}
					/>
				) }
				{ 'matching' === type && (
					<SelectControl
						label={ __( 'Matching layout', 'ohmylms' ) }
						value={
							'grid' === settings.match_layout ? 'grid' : 'wire'
						}
						options={ [
							{
								value: 'wire',
								label: __( 'Connect with lines', 'ohmylms' ),
							},
							{
								value: 'grid',
								label: __( 'Choose from a grid', 'ohmylms' ),
							},
						] }
						onChange={ ( layout ) =>
							onChange( { match_layout: layout } )
						}
					/>
				) }
			</fieldset>
			{ applies.feedback && (
				<fieldset disabled={ disabled }>
					<legend>{ __( 'Feedback', 'ohmylms' ) }</legend>
					<TextareaControl
						label={ __( 'Hint', 'ohmylms' ) }
						help={ __(
							'Offered in practice and lesson checks before answering.',
							'ohmylms'
						) }
						value={ text( 'hint' ) }
						rows={ 2 }
						maxLength={ MAX_TEXT_SETTING }
						aria-invalid={ errors.hint ? true : undefined }
						aria-describedby={
							errors.hint ? errorId( 'hint' ) : undefined
						}
						onChange={ ( hint ) => onChange( { hint } ) }
					/>
					<FieldError field="hint" message={ errors.hint } />
					<TextareaControl
						label={ __( 'Explanation', 'ohmylms' ) }
						help={ __(
							'Shown with the correct answer once feedback is released.',
							'ohmylms'
						) }
						value={ text( 'explanation' ) }
						rows={ 3 }
						maxLength={ MAX_TEXT_SETTING }
						aria-invalid={ errors.explanation ? true : undefined }
						aria-describedby={
							errors.explanation
								? errorId( 'explanation' )
								: undefined
						}
						onChange={ ( explanation ) =>
							onChange( { explanation } )
						}
					/>
					<FieldError
						field="explanation"
						message={ errors.explanation }
					/>
				</fieldset>
			) }
		</>
	);
}

/**
 * Image and video shown with the question.
 *
 * @param props          Component props.
 * @param props.image    Question image.
 * @param props.video    Question video.
 * @param props.errors   Field errors (`image`, `video`).
 * @param props.disabled Whether editing is off.
 * @param props.onImage  Receives the new image.
 * @param props.onVideo  Receives the new video.
 */
export function MediaFields( {
	image,
	video,
	errors,
	disabled,
	onImage,
	onVideo,
}: {
	image: FormMedia;
	video: FormMedia;
	errors: FormErrors;
	disabled: boolean;
	onImage: ( next: FormMedia ) => void;
	onVideo: ( next: FormMedia ) => void;
} ) {
	return (
		<fieldset disabled={ disabled }>
			<legend>{ __( 'Media', 'ohmylms' ) }</legend>
			<p>{ __( 'Question image', 'ohmylms' ) }</p>
			<MediaChoice
				kind="image"
				label={ __( 'question image', 'ohmylms' ) }
				value={ image }
				disabled={ disabled }
				onChange={ onImage }
			/>
			<FieldError field="image" message={ errors.image } />
			<p>{ __( 'Question video', 'ohmylms' ) }</p>
			<MediaChoice
				kind="video"
				label={ __( 'question video', 'ohmylms' ) }
				value={ video }
				disabled={ disabled }
				onChange={ onVideo }
			/>
			<FieldError field="video" message={ errors.video } />
		</fieldset>
	);
}

/**
 * Where the question is kept and how it is described in the question bank: bank, difficulty, source
 * and whether it is secure (kept out of practice).
 *
 * @param props              Component props.
 * @param props.bankId       Bank id; 0 for a personal question.
 * @param props.difficulty   Difficulty.
 * @param props.source       Source note.
 * @param props.secure       Secure flag.
 * @param props.familyId     Family of copies, read-only.
 * @param props.difficulties Difficulty choices (value and label).
 * @param props.errors       Field errors (`bank`, `difficulty`, `source`).
 * @param props.disabled     Whether editing is off.
 * @param props.onChange     Receives changed fields.
 */
export function BankFields( {
	bankId,
	difficulty,
	source,
	secure,
	familyId,
	difficulties,
	errors,
	disabled,
	onChange,
}: {
	bankId: number;
	difficulty: string;
	source: string;
	secure: boolean;
	familyId: string;
	difficulties: { value: string; label: string }[];
	errors: FormErrors;
	disabled: boolean;
	onChange: ( patch: {
		bankId?: number;
		difficulty?: string;
		source?: string;
		secure?: boolean;
	} ) => void;
} ) {
	const [ banks, setBanks ] = useState< BankRef[] | null >( null );
	const [ failed, setFailed ] = useState< string | null >( null );
	useEffect( () => {
		const controller = new AbortController();
		listBanks( controller.signal ).then(
			( loaded ) => {
				if ( ! controller.signal.aborted ) {
					setBanks( loaded );
				}
			},
			( problem: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setFailed( normalizeApiError( problem ).message );
				}
			}
		);
		return () => controller.abort();
	}, [] );
	const choices = [
		{
			value: '0',
			label: __( 'No bank (my own question)', 'ohmylms' ),
		},
		...( banks ?? [] ).map( ( bank ) => ( {
			value: String( bank.id ),
			label: bank.name,
		} ) ),
	];
	if (
		bankId > 0 &&
		! choices.some( ( item ) => item.value === String( bankId ) )
	) {
		choices.push( {
			value: String( bankId ),
			label: sprintf(
				/* translators: %d: bank id. */
				__( 'Bank #%d', 'ohmylms' ),
				bankId
			),
		} );
	}
	return (
		<fieldset disabled={ disabled }>
			<legend>{ __( 'Question bank', 'ohmylms' ) }</legend>
			<SelectControl
				label={ __( 'Bank', 'ohmylms' ) }
				value={ String( bankId ) }
				options={ choices }
				help={
					failed ??
					( null === banks
						? __( 'Loading banks…', 'ohmylms' )
						: __(
								'Questions in a shared bank can be used by everyone the bank is shared with.',
								'ohmylms'
							) )
				}
				aria-invalid={ errors.bank ? true : undefined }
				aria-describedby={ errors.bank ? errorId( 'bank' ) : undefined }
				onChange={ ( next ) => onChange( { bankId: Number( next ) } ) }
			/>
			<FieldError field="bank" message={ errors.bank } />
			<SelectControl
				label={ __( 'Difficulty', 'ohmylms' ) }
				value={ difficulty }
				options={ difficulties }
				aria-invalid={ errors.difficulty ? true : undefined }
				aria-describedby={
					errors.difficulty ? errorId( 'difficulty' ) : undefined
				}
				onChange={ ( next ) => onChange( { difficulty: next } ) }
			/>
			<FieldError field="difficulty" message={ errors.difficulty } />
			<TextControl
				label={ __( 'Source', 'ohmylms' ) }
				help={ __(
					'Where the question comes from, such as a textbook page.',
					'ohmylms'
				) }
				value={ source }
				maxLength={ MAX_SOURCE }
				aria-invalid={ errors.source ? true : undefined }
				aria-describedby={
					errors.source ? errorId( 'source' ) : undefined
				}
				onChange={ ( next ) => onChange( { source: next } ) }
			/>
			<FieldError field="source" message={ errors.source } />
			<CheckboxControl
				label={ __(
					'Secure question (kept out of practice and random pools that exclude secure questions)',
					'ohmylms'
				) }
				checked={ secure }
				onChange={ ( next ) => onChange( { secure: next } ) }
			/>
			{ familyId && (
				<p>
					{ sprintf(
						/* translators: %s: family identifier. */
						__(
							'Copies of this question share the family %s.',
							'ohmylms'
						),
						familyId
					) }
				</p>
			) }
		</fieldset>
	);
}

/**
 * Skills the question assesses, per part: one primary skill (the evidence of mastery) and supporting
 * skills (used for recommendations only).
 *
 * @param props          Component props.
 * @param props.settings Current settings (parts of multi-part questions).
 * @param props.skills   Skill map.
 * @param props.errors   Field errors (`skills`, `skills.<part>`).
 * @param props.disabled Whether editing is off.
 * @param props.onChange Receives the new skill map.
 */
export function SkillsFields( {
	settings,
	skills,
	errors,
	disabled,
	onChange,
}: {
	settings: QuestionSettings;
	skills: SkillMap;
	errors: FormErrors;
	disabled: boolean;
	onChange: ( next: SkillMap ) => void;
} ) {
	const [ names, setNames ] = useState< Map< number, SkillRef > >(
		() => new Map()
	);
	const parts = skillParts( settings );
	const ids = Object.values( skills ).flatMap( ( roles ) => [
		roles.primary,
		...roles.supporting,
	] );
	const missing = ids
		.filter( ( id ) => id > 0 && ! names.has( id ) )
		.sort( ( a, b ) => a - b );
	const missingKey = missing.join( ',' );
	const remember = useCallback( ( found: SkillRef[] ) => {
		setNames( ( current ) => {
			const next = new Map( current );
			found.forEach( ( skill ) => next.set( skill.id, skill ) );
			return next;
		} );
	}, [] );
	useEffect( () => {
		if ( ! missingKey ) {
			return undefined;
		}
		const controller = new AbortController();
		getSkills(
			missingKey.split( ',' ).map( Number ),
			controller.signal
		).then(
			( found ) => {
				if ( ! controller.signal.aborted ) {
					remember( found );
				}
			},
			() => undefined
		);
		return () => controller.abort();
	}, [ missingKey, remember ] );
	const roles = ( key: string ): SkillRoles =>
		skills[ key ] ?? { primary: 0, supporting: [] };
	const change = ( key: string, patch: Partial< SkillRoles > ) =>
		onChange( { ...skills, [ key ]: { ...roles( key ), ...patch } } );
	return (
		<fieldset
			disabled={ disabled }
			aria-describedby={ errors.skills ? errorId( 'skills' ) : undefined }
		>
			<legend>{ __( 'Skills', 'ohmylms' ) }</legend>
			<p>
				{ __(
					'The primary skill is what the answer shows mastery of; supporting skills are only used for recommendations.',
					'ohmylms'
				) }
			</p>
			<FieldError field="skills" message={ errors.skills } />
			{ parts.map( ( part ) => {
				const current = roles( part.key );
				const prefix =
					parts.length > 1
						? sprintf(
								/* translators: %s: part label. */
								__( 'Part %s:', 'ohmylms' ),
								part.label
							) + ' '
						: '';
				return (
					<div key={ part.key }>
						<SkillPicker
							label={ `${ prefix }${ __( 'Primary skill', 'ohmylms' ) }` }
							selected={
								current.primary ? [ current.primary ] : []
							}
							max={ 1 }
							names={ names }
							disabled={ disabled }
							onFound={ remember }
							onChange={ ( next ) =>
								change( part.key, { primary: next[ 0 ] ?? 0 } )
							}
						/>
						<SkillPicker
							label={ `${ prefix }${ __(
								'Supporting skills',
								'ohmylms'
							) }` }
							selected={ current.supporting }
							max={ MAX_SUPPORTING }
							names={ names }
							disabled={ disabled }
							onFound={ remember }
							onChange={ ( next ) =>
								change( part.key, { supporting: next } )
							}
						/>
						<FieldError
							field={ `skills.${ part.key }` }
							message={ errors[ `skills.${ part.key }` ] }
						/>
					</div>
				);
			} ) }
		</fieldset>
	);
}
