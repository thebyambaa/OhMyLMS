import { MathsGenerator } from './MathsGenerator';
import { VariableChips, variableNames } from './VariableChips';
import {
	insertVariable,
	listToNumbersOrFormulas,
	numberOrFormula,
	toleranceOrFormula,
} from './formulaInput.mjs';
import { QUESTION_BLOCK_TYPES, questionTypePatch } from './questionBlocks.mjs';
/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { RichContentControl } from '../math/RichContentControl';
import {
	Button,
	CheckboxControl,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import {
	addPart,
	numbersToList,
	removePart,
	updatePart,
} from '../question-bank/model.mjs';

/**
 * Other accepted answers: numbers and {{formulas}}, comma separated. The typed text is kept as
 * typed; only complete numbers and formulas are stored.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 * @param root0.names
 */
function AcceptedAnswers( { value, onChange, names } ) {
	const stored = numbersToList( value.answers );
	const [ text, setText ] = useState( stored );
	useEffect( () => {
		// Follow changes made elsewhere (the maths generator), not the user's own typing.
		if (
			JSON.stringify( listToNumbersOrFormulas( text ) ) !==
			JSON.stringify( value.answers )
		) {
			setText( stored );
		}
	}, [ stored ] ); // eslint-disable-line react-hooks/exhaustive-deps
	const update = ( next ) => {
		setText( next );
		onChange( { answers: listToNumbersOrFormulas( next ) } );
	};
	return (
		<>
			<TextControl
				label={ __(
					'Other accepted answers (comma separated, optional)',
					'ohmylms'
				) }
				value={ text }
				onChange={ update }
			/>
			<VariableChips
				names={ names }
				onPick={ ( name ) =>
					update(
						text.trim() === ''
							? `{{${ name }}}`
							: `${ text.replace( /[,\s]+$/, '' ) }, {{${ name }}}`
					)
				}
			/>
		</>
	);
}

/**
 * Numerical question settings: expected value(s), tolerance and unit.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 * @param root0.onGenerate
 */
export function NumericalEditor( { value, onChange, onGenerate } ) {
	const names = variableNames( value );
	return (
		<div className="ohmylms-numerical-editor">
			{ onGenerate && (
				<MathsGenerator value={ value } onGenerate={ onGenerate } />
			) }
			{ value.maths_generator && value.template?.variables?.length ? (
				<p>
					{ __(
						'The correct answer is calculated for each generated question. Preview the question to inspect a sample.',
						'ohmylms'
					) }
				</p>
			) : (
				<>
					<TextControl
						label={ __( 'Correct answer', 'ohmylms' ) }
						help={ __(
							'A number, or a variable or formula such as {{a*b}}.',
							'ohmylms'
						) }
						{ ...numberOrFormula( value.answer, ( answer ) =>
							onChange( { answer } )
						) }
					/>
					<VariableChips
						names={ names }
						onPick={ ( name ) =>
							onChange( {
								answer: insertVariable( value.answer, name ),
							} )
						}
					/>
					<AcceptedAnswers
						value={ value }
						onChange={ onChange }
						names={ names }
					/>
				</>
			) }
			<div
				style={ {
					display: 'flex',
					gap: 12,
				} }
			>
				<div>
					<TextControl
						label={ __( 'Tolerance', 'ohmylms' ) }
						{ ...toleranceOrFormula(
							value.tolerance,
							( tolerance ) => onChange( { tolerance } )
						) }
					/>
					<VariableChips
						names={ names }
						onPick={ ( name ) =>
							onChange( {
								tolerance: insertVariable(
									value.tolerance,
									name
								),
							} )
						}
					/>
				</div>
				<SelectControl
					label={ __( 'Tolerance type', 'ohmylms' ) }
					value={ value.tolerance_type || 'absolute' }
					options={ [
						{
							value: 'absolute',
							label: __( 'Absolute (± value)', 'ohmylms' ),
						},
						{
							value: 'relative',
							label: __(
								'Relative (± fraction of answer)',
								'ohmylms'
							),
						},
					] }
					onChange={ ( _toleranceType ) =>
						onChange( {
							tolerance_type: _toleranceType,
						} )
					}
				/>
				<TextControl
					label={ __( 'Unit shown to learners', 'ohmylms' ) }
					value={ value.unit || '' }
					onChange={ ( unit ) =>
						onChange( {
							unit,
						} )
					}
				/>
			</div>
			<CheckboxControl
				label={ __(
					'Accept fractions such as 3/4 or 1 1/2',
					'ohmylms'
				) }
				checked={ value.allow_fractions !== false }
				onChange={ ( _allowFractions ) =>
					onChange( {
						allow_fractions: _allowFractions,
					} )
				}
			/>
		</div>
	);
}

/**
 * Structured question parts: shared stem (question text) plus separately scored parts.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 * @param root0.renderQuestion
 */
export function StructuredEditor( {
	value,
	onChange,
	renderQuestion: PartQuestion,
} ) {
	const parts = value.parts || [];
	const set = ( index, fields ) =>
		onChange( {
			parts: updatePart( parts, index, fields ),
		} );
	return (
		<div className="ohmylms-structured-editor">
			<p>
				{ __(
					'The question text and image are the shared stem. Each part is scored separately; written parts are marked by a teacher.',
					'ohmylms'
				) }
			</p>
			{ parts.map( ( part, index ) => (
				<fieldset
					key={ index }
					style={ {
						border: '1px solid #ddd',
						padding: 12,
						marginBottom: 12,
					} }
				>
					<legend>
						{ sprintf(
							/* translators: %s: question part identifier. */ __(
								'Part %s',
								'ohmylms'
							),
							part.label || part.id
						) }
					</legend>
					<div
						style={ {
							display: 'flex',
							gap: 12,
						} }
					>
						<TextControl
							label={ __( 'Label', 'ohmylms' ) }
							value={ part.label || '' }
							onChange={ ( label ) =>
								set( index, {
									label,
								} )
							}
						/>
						<SelectControl
							label={ __( 'Answer type', 'ohmylms' ) }
							value={
								part.kind === 'question'
									? part.question?.settings?.type
									: part.kind || 'written'
							}
							options={ [
								{
									value: 'numerical',
									label: __( 'Number', 'ohmylms' ),
								},
								{
									value: 'text',
									label: __( 'Short exact text', 'ohmylms' ),
								},
								{
									value: 'written',
									label: __(
										'Written (teacher marks)',
										'ohmylms'
									),
								},
								...( PartQuestion
									? QUESTION_BLOCK_TYPES.filter(
											( [ type ] ) =>
												! [
													'numerical',
													'structured',
													'passage',
													'short-text',
													'long-text',
													'statement',
													'poll',
													'word-cloud',
													'discussion-board',
													'interactive-video',
												].includes( type )
										).map( ( [ type, label ] ) => ( {
											value: type,
											label:
												type === 'single-choice'
													? __(
															'Single choice',
															'ohmylms'
														)
													: label,
										} ) )
									: [] ),
							] }
							onChange={ ( kind ) =>
								set( index, {
									...( [
										'numerical',
										'text',
										'written',
									].includes( kind )
										? { kind }
										: {
												kind: 'question',
												question: questionTypePatch(
													{},
													kind
												),
											} ),
								} )
							}
						/>
						<TextControl
							type="number"
							min={ 0 }
							step="0.5"
							label={ __( 'Marks', 'ohmylms' ) }
							value={ part.marks ?? 1 }
							onChange={ ( marks ) =>
								set( index, {
									marks: Number( marks ),
								} )
							}
						/>
					</div>
					<p className="ohmylms-part-statement-label">
						{ __( 'Part statement', 'ohmylms' ) }
					</p>
					<RichContentControl
						compact
						showToolbar
						collapseToolbar
						label={ __( 'Part statement', 'ohmylms' ) }
						placeholder={ __(
							'Write the statement for this part…',
							'ohmylms'
						) }
						value={ part.prompt || '' }
						onChange={ ( prompt ) =>
							set( index, {
								prompt,
							} )
						}
					/>
					{ part.kind === 'numerical' && (
						<div
							style={ {
								display: 'flex',
								gap: 12,
							} }
						>
							<TextControl
								label={ __( 'Answer', 'ohmylms' ) }
								{ ...numberOrFormula( part.answer, ( answer ) =>
									set( index, { answer } )
								) }
							/>
							<TextControl
								label={ __( 'Tolerance', 'ohmylms' ) }
								{ ...toleranceOrFormula(
									part.tolerance,
									( tolerance ) => set( index, { tolerance } )
								) }
							/>
							<TextControl
								label={ __( 'Unit', 'ohmylms' ) }
								value={ part.unit || '' }
								onChange={ ( unit ) =>
									set( index, {
										unit,
									} )
								}
							/>
						</div>
					) }
					{ part.kind === 'question' && PartQuestion && (
						<PartQuestion
							type={ part.question?.settings?.type }
							settings={ part.question?.settings || {} }
							options={ part.question?.questions || [] }
							prompt={ part.prompt || '' }
							mainStatement
							onChange={ ( patch ) =>
								set( index, {
									question: { ...part.question, ...patch },
								} )
							}
						/>
					) }
					{ part.kind === 'text' && (
						<TextControl
							label={ __(
								'Accepted answers (comma separated, case-insensitive)',
								'ohmylms'
							) }
							value={ ( part.accepted || [] ).join( ', ' ) }
							onChange={ ( text ) =>
								set( index, {
									accepted: text
										.split( ',' )
										.map( ( item ) => item.trim() )
										.filter( Boolean ),
								} )
							}
						/>
					) }
					<TextareaControl
						label={ __(
							'Marking notes (teachers only)',
							'ohmylms'
						) }
						value={ part.rubric || '' }
						onChange={ ( rubric ) =>
							set( index, {
								rubric,
							} )
						}
					/>
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () =>
							onChange( {
								parts: removePart( parts, index ),
							} )
						}
					>
						{ __( 'Remove part', 'ohmylms' ) }
					</Button>
				</fieldset>
			) ) }
			<Button
				variant="secondary"
				onClick={ () =>
					onChange( {
						parts: addPart( parts ),
					} )
				}
			>
				{ __( 'Add part', 'ohmylms' ) }
			</Button>
		</div>
	);
}

/**
 * Practice feedback fields shared by all question types.
 * @param root0
 * @param root0.question
 * @param root0.onChange
 */
export function PracticeFeedbackFields( { question, onChange } ) {
	if ( ! question || ! question.settings?.type ) {
		return null;
	}
	const settings = question.settings || {};
	return (
		<details
			className="ohmylms-practice-feedback-fields"
			style={ {
				margin: '12px 0',
			} }
		>
			<summary>
				{ __(
					'Hint and explanation (practice and lesson checks)',
					'ohmylms'
				) }
			</summary>
			<RichContentControl
				compact
				html={ false }
				label={ __(
					'Hint (using it marks the answer as assisted)',
					'ohmylms'
				) }
				value={ settings.hint || '' }
				onChange={ ( hint ) =>
					onChange( {
						settings: {
							...settings,
							hint,
						},
					} )
				}
			/>
			<RichContentControl
				compact
				html={ false }
				label={ __( 'Explanation shown after answering', 'ohmylms' ) }
				value={ settings.explanation || '' }
				onChange={ ( explanation ) =>
					onChange( {
						settings: {
							...settings,
							explanation,
						},
					} )
				}
			/>
		</details>
	);
}
