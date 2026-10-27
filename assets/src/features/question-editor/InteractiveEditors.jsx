import { PuzzlePreview } from './PuzzleEditor';
/** @jsx createElement */
import { RichContentControl } from '../math/RichContentControl';
import { createElement, Fragment, useState, useId } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	FormTokenField,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { VisualPreview } from './VisualEditors';
import { MathInput } from '../math/MathInput';
import {
	dropdownAnswers,
	dropdownSettings,
	renameDropdownChoice,
} from './dropdownModel.mjs';
import { listToNumbers, numbersToList } from '../question-bank/model.mjs';
import {
	blankIds,
	languageTiles,
	nextId,
	removeBucket,
	syncBlanks,
	syncSlots,
	tilesFromText,
} from './interactiveModel.mjs';
const VISUAL_TYPES = [
	'number-line',
	'shade-model',
	'count-blocks',
	'set-clock',
	'make-amount',
	'fill-level',
	'build-chart',
	'grid-build',
];

/**
 * A text field that edits a parsed value without eating the characters being typed
 * (a trailing space or newline would otherwise vanish on every keystroke).
 * @param root0
 * @param root0.value
 * @param root0.format
 * @param root0.parse
 * @param root0.onChange
 * @param root0.multiline
 * @param root0.label
 * @param root0.help
 */
function ParsedField( {
	value,
	format,
	parse,
	onChange,
	multiline = false,
	label,
	help,
} ) {
	const [ text, setText ] = useState( () => format( value ) );
	const shown =
		format( parse( text ) ) === format( value ) ? text : format( value );
	const Control = multiline ? TextareaControl : TextControl;
	return (
		<Control
			label={ label }
			help={ help }
			value={ shown }
			onChange={ ( next ) => {
				setText( next );
				onChange( parse( next ) );
			} }
		/>
	);
}
const csv = ( text ) =>
	String( text )
		.split( ',' )
		.map( ( s ) => s.trim() );
const joinCsv = ( list ) => ( list || [] ).join( ', ' );

/**
 * Partial credit applies to every type that has several independent parts.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 */
function PartialCredit( { value, onChange } ) {
	return (
		<CheckboxControl
			label={ __(
				'Give partial credit for each correct part (otherwise all parts must be correct)',
				'ohmylms'
			) }
			checked={ !! value.partial_credit }
			onChange={ ( _partialCredit ) =>
				onChange( {
					partial_credit: _partialCredit,
				} )
			}
		/>
	);
}
const formOptions = () => [
	{
		value: 'any',
		label: __( 'Any equivalent form', 'ohmylms' ),
	},
	{
		value: 'expanded',
		label: __( 'Must be expanded', 'ohmylms' ),
	},
	{
		value: 'factored',
		label: __( 'Must be factored', 'ohmylms' ),
	},
	{
		value: 'simplified',
		label: __( 'Must be simplified', 'ohmylms' ),
	},
];

/**
 * Required shape of an equivalent answer.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 */
function FormSelect( { value, onChange } ) {
	return (
		<SelectControl
			label={ __( 'Answer form', 'ohmylms' ) }
			help={ __(
				'Equivalent answers such as 2x+6 and 2(x+3) are accepted; choose a form to require a particular shape.',
				'ohmylms'
			) }
			value={ value || 'any' }
			options={ formOptions() }
			onChange={ onChange }
		/>
	);
}

/**
 * A typed math expression graded by algebraic equivalence.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 */
export function ExpressionEditor( { value, onChange } ) {
	return (
		<div className="ohmylms-interactive-editor">
			<MathInput
				label={ __( 'Correct answer', 'ohmylms' ) }
				help={ __(
					'Write it as you would type it: 2(x+3), x^2–1, 3/4, sqrt(x), sin(x). Equations such as 2x+5=11 are also supported.',
					'ohmylms'
				) }
				value={ value.answer || '' }
				onChange={ ( answer ) =>
					onChange( {
						answer,
					} )
				}
			/>
			<FormSelect
				value={ value.form }
				onChange={ ( form ) =>
					onChange( {
						form,
					} )
				}
			/>
			{ ( value.alternatives || [] ).map( ( answer, index ) => (
				<div key={ index }>
					<MathInput
						label={ __( 'Alternative answer', 'ohmylms' ) }
						value={ answer }
						onChange={ ( next ) =>
							onChange( {
								alternatives: value.alternatives.map(
									( item, i ) => ( i === index ? next : item )
								),
							} )
						}
					/>
					<Button
						variant="tertiary"
						onClick={ () =>
							onChange( {
								alternatives: value.alternatives.filter(
									( _, i ) => i !== index
								),
							} )
						}
					>
						{ __( 'Remove alternative', 'ohmylms' ) }
					</Button>
				</div>
			) ) }
			<Button
				variant="secondary"
				onClick={ () =>
					onChange( {
						alternatives: [ ...( value.alternatives || [] ), '' ],
					} )
				}
			>
				{ __( 'Add alternative answer', 'ohmylms' ) }
			</Button>
		</div>
	);
}

/**
 * Sentence with {1}, {2} … markers; each marker becomes a dropdown.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 * @param root0.mainStatement
 */
export function DropdownBlanksEditor( {
	value,
	onChange,
	mainStatement = false,
} ) {
	const slots = value.slots || [];
	const setSlot = ( id, patch ) =>
		onChange(
			dropdownSettings(
				value,
				slots.map( ( slot ) =>
					slot.id === id
						? {
								...slot,
								...patch,
							}
						: slot
				)
			)
		);
	return (
		<div className="ohmylms-interactive-editor">
			{ mainStatement ? (
				<p>
					{ __(
						'Write {1}, {2} … in the Question field where a dropdown should appear.',
						'ohmylms'
					) }
				</p>
			) : (
				<RichContentControl
					compact
					html={ false }
					label={ __( 'Sentence', 'ohmylms' ) }
					help={ __(
						'Write {1}, {2} … where a dropdown should appear.',
						'ohmylms'
					) }
					value={ value.text || '' }
					onChange={ ( text ) =>
						onChange( {
							text,
							slots: syncSlots( text, slots ),
						} )
					}
				/>
			) }
			{ slots.map( ( slot ) => (
				<fieldset key={ slot.id } className="ohmylms-interactive-row">
					<legend>
						{ sprintf(
							/* translators: %s: dropdown marker ID. */
							__( 'Dropdown {%s}', 'ohmylms' ),
							slot.id
						) }
					</legend>
					<div className="ohmylms-dropdown-choice-list">
						{ ( slot.choices || [] ).map( ( choice, index ) => (
							<div
								className="ohmylms-dropdown-choice-row"
								key={ index }
							>
								<input
									type="checkbox"
									aria-label={ sprintf(
										/* translators: %d: choice position. */ __(
											'Correct choice %d',
											'ohmylms'
										),
										index + 1
									) }
									checked={ dropdownAnswers( slot ).includes(
										choice
									) }
									onChange={ ( event ) => {
										const answers = event.target.checked
											? [
													...dropdownAnswers( slot ),
													choice,
												]
											: dropdownAnswers( slot ).filter(
													( answer ) =>
														answer !== choice
												);
										setSlot( slot.id, {
											answers,
											answer: answers[ 0 ] || '',
											multiple:
												answers.length > 1 ||
												!! slot.multiple,
										} );
									} }
								/>
								<RichContentControl
									compact
									html={ false }
									label={ sprintf(
										/* translators: %d: choice position. */ __(
											'Choice %d',
											'ohmylms'
										),
										index + 1
									) }
									value={ choice }
									onChange={ ( text ) =>
										setSlot(
											slot.id,
											renameDropdownChoice(
												slot,
												index,
												text
											)
										)
									}
								/>
								<Button
									variant="tertiary"
									label={ __( 'Remove choice', 'ohmylms' ) }
									onClick={ () => {
										const answers = dropdownAnswers(
											slot
										).filter(
											( answer ) => answer !== choice
										);
										setSlot( slot.id, {
											choices: slot.choices.filter(
												( item, position ) =>
													position !== index
											),
											answers,
											answer: answers[ 0 ] || '',
										} );
									} }
								>
									{ __( 'Remove', 'ohmylms' ) }
								</Button>
							</div>
						) ) }
						<Button
							variant="secondary"
							onClick={ () =>
								setSlot( slot.id, {
									choices: [ ...slot.choices, '' ],
								} )
							}
						>
							{ __( 'Add choice', 'ohmylms' ) }
						</Button>
					</div>
					<TextControl
						type="number"
						min="0"
						step="any"
						label={ __( 'Dropdown points', 'ohmylms' ) }
						value={
							slot.points ??
							Number( value.score?.value ?? 1 ) /
								Math.max( 1, slots.length )
						}
						onChange={ ( points ) =>
							setSlot( slot.id, {
								points: Math.max( 0, Number( points ) || 0 ),
							} )
						}
					/>
					{ dropdownAnswers( slot ).length > 1 && (
						<SelectControl
							label={ __(
								'Multiple correct answers',
								'ohmylms'
							) }
							value={ slot.grading || 'equal' }
							options={ [
								{
									value: 'equal',
									label: __(
										'Equal partial credit for each correct selection',
										'ohmylms'
									),
								},
								{
									value: 'any',
									label: __(
										'Any correct selection earns full points',
										'ohmylms'
									),
								},
								{
									value: 'no-wrong',
									label: __(
										'No wrong choice selected — full points',
										'ohmylms'
									),
								},
							] }
							onChange={ ( grading ) =>
								setSlot( slot.id, {
									grading,
								} )
							}
						/>
					) }
					<p>
						{ __(
							'Tick every correct choice. At least one correct choice must be selected. Selecting an incorrect choice earns zero for this dropdown.',
							'ohmylms'
						) }
					</p>
				</fieldset>
			) ) }
			{ value.dropdown_grading_version !== 2 && (
				<PartialCredit value={ value } onChange={ onChange } />
			) }
		</div>
	);
}

/**
 * Tag input for the items that belong to one group (or to none).
 * Enter or comma turns the typed text into a new item in that group.
 * @param root0
 * @param root0.bucket
 * @param root0.index
 * @param root0.value
 * @param root0.onChange
 */
function TagItemsRow( { bucket, index, value, onChange } ) {
	const items = value.items || [];
	const key = value.key || {};
	const mine = items.filter(
		( item ) => ( key[ item.id ] || '' ) === bucket.id
	);
	const label = bucket.id
		? bucket.label ||
			sprintf(
				/* translators: %d: group number. */ __(
					'Group %d',
					'ohmylms'
				),
				index + 1
			)
		: __( 'Not in a group yet', 'ohmylms' );
	const setTags = ( tokens ) => {
		const texts = tokens
			.flatMap( ( token ) => String( token.value || token ).split( ',' ) )
			.map( ( part ) => part.trim() )
			.filter( Boolean );
		const kept = [];
		const pool = [ ...mine ];
		const added = [];
		texts.forEach( ( text ) => {
			const at = pool.findIndex( ( item ) => item.text === text );
			if ( at >= 0 ) {
				kept.push( pool.splice( at, 1 )[ 0 ] );
			} else {
				added.push( {
					id: nextId( [ ...items, ...added ], 'i' ),
					text,
				} );
			}
		} );
		const dropped = new Set( pool.map( ( item ) => item.id ) );
		const nextKey = { ...key };
		dropped.forEach( ( id ) => delete nextKey[ id ] );
		added.forEach( ( item ) => {
			nextKey[ item.id ] = bucket.id;
		} );
		onChange( {
			items: [
				...items.filter( ( item ) => ! dropped.has( item.id ) ),
				...added,
			],
			key: nextKey,
		} );
	};
	return (
		<div className="ohmylms-interactive-row ohmylms-tag-row">
			<FormTokenField
				__next40pxDefaultSize
				label={ label }
				value={ mine.map( ( item ) => item.text ) }
				onChange={ setTags }
				tokenizeOnSpace={ false }
				tokenizeOnBlur
				placeholder={ __( 'Type here …', 'ohmylms' ) }
			/>
		</div>
	);
}

/**
 * Groups and the items that belong in them.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 */
export function CategorizeEditor( { value, onChange } ) {
	const buckets = value.buckets || [];
	const items = value.items || [];
	const key = value.key || {};
	const setBucket = ( id, patch ) =>
		onChange( {
			buckets: buckets.map( ( bucket ) =>
				bucket.id === id
					? {
							...bucket,
							...patch,
						}
					: bucket
			),
		} );
	return (
		<div className="ohmylms-interactive-editor">
			<h4>{ __( 'Groups', 'ohmylms' ) }</h4>
			{ buckets.map( ( bucket, index ) => (
				<div key={ bucket.id } className="ohmylms-interactive-row">
					<RichContentControl
						compact
						html={ false }
						label={ sprintf(
							/* translators: %d: group number. */ __(
								'Group %d',
								'ohmylms'
							),
							index + 1
						) }
						value={ bucket.label || '' }
						onChange={ ( label ) =>
							setBucket( bucket.id, {
								label,
							} )
						}
					/>
					<Button
						isDestructive
						variant="tertiary"
						disabled={ buckets.length <= 2 }
						onClick={ () =>
							onChange( removeBucket( value, bucket.id ) )
						}
					>
						{ __( 'Remove group', 'ohmylms' ) }
					</Button>
				</div>
			) ) }
			<Button
				variant="secondary"
				onClick={ () =>
					onChange( {
						buckets: [
							...buckets,
							{
								id: nextId( buckets, 'b' ),
								label: '',
							},
						],
					} )
				}
			>
				{ __( 'Add group', 'ohmylms' ) }
			</Button>
			<h4>{ __( 'Items to sort', 'ohmylms' ) }</h4>
			<p className="description">
				{ __(
					'Type an item and press Enter or comma to add it as a tag.',
					'ohmylms'
				) }
			</p>
			{ [ ...buckets, { id: '', label: '' } ]
				.filter(
					( bucket ) =>
						bucket.id !== '' ||
						items.some( ( item ) => ! key[ item.id ] )
				)
				.map( ( bucket, index ) => (
					<TagItemsRow
						key={ bucket.id || 'unassigned' }
						bucket={ bucket }
						index={ index }
						value={ value }
						onChange={ onChange }
					/>
				) ) }
			<PartialCredit value={ value } onChange={ onChange } />
		</div>
	);
}
const rowsText = ( rows ) =>
	( rows || [] ).map( ( row ) => row.join( ' | ' ) ).join( '\n' );
const parseRows = ( text ) =>
	String( text )
		.split( '\n' )
		.map( ( line ) => line.split( '|' ).map( ( cell ) => cell.trim() ) );

/**
 * Blanks in a sentence or table cells, each with its own expected answer.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 * @param root0.mainStatement
 * @param root0.compactActions
 */
export function MultiBlankEditor( {
	value,
	onChange,
	mainStatement = false,
	compactActions = false,
} ) {
	const table = value.layout === 'table';
	const Group = compactActions ? 'details' : 'fieldset';
	const Heading = compactActions ? 'summary' : 'legend';
	const apply = ( patch ) => {
		const next = {
			...value,
			...patch,
		};
		onChange( {
			...patch,
			blanks: syncBlanks( next ),
		} );
	};
	const setBlank = ( id, patch ) =>
		onChange( {
			blanks: {
				...value.blanks,
				[ id ]: {
					...( value.blanks?.[ id ] || {} ),
					...patch,
				},
			},
		} );
	return (
		<div className="ohmylms-interactive-editor">
			<SelectControl
				label={ __( 'Layout', 'ohmylms' ) }
				value={ table ? 'table' : 'inline' }
				options={ [
					{
						value: 'inline',
						label: __( 'Sentence with blanks', 'ohmylms' ),
					},
					{
						value: 'table',
						label: __( 'Table', 'ohmylms' ),
					},
				] }
				onChange={ ( layout ) =>
					apply(
						layout === 'table' && ! value.rows
							? {
									layout,
									columns: [ 'x', 'y' ],
									rows: [
										[ '1', '{a}' ],
										[ '2', '{b}' ],
									],
								}
							: {
									layout,
								}
					)
				}
			/>
			{ ( () => {
				if ( table ) {
					return (
						<Fragment>
							<ParsedField
								label={ __(
									'Column headings (comma separated)',
									'ohmylms'
								) }
								value={ value.columns }
								format={ joinCsv }
								parse={ csv }
								onChange={ ( columns ) =>
									onChange( {
										columns,
									} )
								}
							/>
							<ParsedField
								multiline
								label={ __(
									'Rows (one per line, cells separated by |)',
									'ohmylms'
								) }
								help={ __(
									'Write {a}, {b} … in a cell where the learner fills in a value.',
									'ohmylms'
								) }
								value={ value.rows }
								format={ rowsText }
								parse={ parseRows }
								onChange={ ( rows ) =>
									apply( {
										rows,
									} )
								}
							/>
						</Fragment>
					);
				}
				if ( mainStatement ) {
					return (
						<p>
							{ __(
								'Write {a}, {b} … in the Question field where a blank should appear.',
								'ohmylms'
							) }
						</p>
					);
				}
				return (
					<RichContentControl
						compact
						html={ false }
						label={ __( 'Text', 'ohmylms' ) }
						help={ __(
							'Write {a}, {b} … where a blank should appear.',
							'ohmylms'
						) }
						value={ value.text || '' }
						onChange={ ( text ) =>
							apply( {
								text,
							} )
						}
					/>
				);
			} )() }
			{ blankIds( value ).map( ( id ) => {
				const spec = value.blanks?.[ id ] || {
					kind: 'text',
				};
				return (
					<Group key={ id } className="ohmylms-interactive-row">
						<Heading>
							{ sprintf(
								/* translators: %s: blank identifier from the question template. */ __(
									'Blank {%s}',
									'ohmylms'
								),
								id
							) }
						</Heading>
						<SelectControl
							label={ __( 'Answer type', 'ohmylms' ) }
							value={ spec.kind || 'text' }
							options={ [
								{
									value: 'numerical',
									label: __( 'Number', 'ohmylms' ),
								},
								{
									value: 'text',
									label: __( 'Exact text', 'ohmylms' ),
								},
								{
									value: 'expression',
									label: __( 'Math expression', 'ohmylms' ),
								},
							] }
							onChange={ ( kind ) =>
								setBlank( id, {
									kind,
								} )
							}
						/>
						{ ( () => {
							if ( spec.kind === 'numerical' ) {
								return (
									<Fragment>
										<TextControl
											type="number"
											step="any"
											label={ __(
												'Correct answer',
												'ohmylms'
											) }
											value={ spec.answer ?? '' }
											onChange={ ( answer ) =>
												setBlank( id, {
													answer:
														answer === ''
															? undefined
															: Number( answer ),
												} )
											}
										/>
										<TextControl
											label={ __(
												'Other accepted answers (comma separated)',
												'ohmylms'
											) }
											value={ numbersToList(
												spec.answers
											) }
											onChange={ ( text ) =>
												setBlank( id, {
													answers:
														listToNumbers( text ),
												} )
											}
										/>
										<TextControl
											type="number"
											min={ 0 }
											step="any"
											label={ __(
												'Tolerance',
												'ohmylms'
											) }
											value={ spec.tolerance ?? 0 }
											onChange={ ( tolerance ) =>
												setBlank( id, {
													tolerance: Math.max(
														0,
														Number( tolerance ) || 0
													),
												} )
											}
										/>
										<TextControl
											label={ __(
												'Unit shown after the box',
												'ohmylms'
											) }
											value={ spec.unit || '' }
											onChange={ ( unit ) =>
												setBlank( id, {
													unit,
												} )
											}
										/>
									</Fragment>
								);
							}
							if ( spec.kind === 'expression' ) {
								return (
									<Fragment>
										<MathInput
											label={ __(
												'Correct expression',
												'ohmylms'
											) }
											value={ spec.answer || '' }
											onChange={ ( answer ) =>
												setBlank( id, {
													answer,
												} )
											}
										/>
										<FormSelect
											value={ spec.form }
											onChange={ ( form ) =>
												setBlank( id, {
													form,
												} )
											}
										/>
									</Fragment>
								);
							}
							return (
								<ParsedField
									label={ __(
										'Accepted answers (comma separated, not case-sensitive)',
										'ohmylms'
									) }
									value={ spec.accepted }
									format={ joinCsv }
									parse={ csv }
									onChange={ ( accepted ) =>
										setBlank( id, {
											accepted,
										} )
									}
								/>
							);
						} )() }
					</Group>
				);
			} ) }
			<PartialCredit value={ value } onChange={ onChange } />
		</div>
	);
}

/**
 * Tiles the learner arranges into an answer.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 */
export function BuildExpressionEditor( { value, onChange } ) {
	const mode = value.tile_mode || 'math';
	const separator = mode === 'letters' ? '' : ' ';
	const parse = ( text ) =>
		mode === 'math' ? tilesFromText( text ) : languageTiles( text, mode );
	return (
		<div className="ohmylms-interactive-editor">
			<SelectControl
				label={ __( 'Build from', 'ohmylms' ) }
				help={ __(
					'Changing mode converts the answer and clears distractors and alternative orders. Use Undo to restore the previous definition.',
					'ohmylms'
				) }
				value={ mode }
				options={ [
					{ value: 'math', label: __( 'Math tiles', 'ohmylms' ) },
					{
						value: 'words',
						label: __( 'Words (sentence ordering)', 'ohmylms' ),
					},
					{
						value: 'letters',
						label: __( 'Letters (anagram)', 'ohmylms' ),
					},
				] }
				onChange={ ( tileMode ) =>
					onChange( {
						tile_mode: tileMode,
						correct: languageTiles(
							( value.correct || [] ).join( separator ),
							tileMode
						),
						distractors: [],
						alternatives: [],
						equivalence: false,
					} )
				}
			/>
			<ParsedField
				label={ __(
					'Correct answer (letters are split automatically in anagram mode)',
					'ohmylms'
				) }
				help={ __( 'Example: 3 x + 4 = 10', 'ohmylms' ) }
				value={ value.correct }
				format={ ( list ) => ( list || [] ).join( separator ) }
				parse={ parse }
				onChange={ ( correct ) =>
					onChange( {
						correct,
					} )
				}
			/>
			<ParsedField
				label={ __(
					'Extra tiles that do not belong (distractors)',
					'ohmylms'
				) }
				value={ value.distractors }
				format={ ( list ) => ( list || [] ).join( separator ) }
				parse={ parse }
				onChange={ ( distractors ) =>
					onChange( {
						distractors,
					} )
				}
			/>
			<ParsedField
				multiline
				label={ __(
					'Other accepted orders (one per line, optional)',
					'ohmylms'
				) }
				help={ __(
					'Use these when more than one order is correct, such as 2 + 3 and 3 + 2.',
					'ohmylms'
				) }
				value={ value.alternatives }
				format={ ( list ) =>
					( list || [] )
						.map( ( s ) => s.join( separator ) )
						.join( '\n' )
				}
				parse={ ( text ) =>
					text.split( /\r?\n/ ).filter( Boolean ).map( parse )
				}
				onChange={ ( alternatives ) =>
					onChange( {
						alternatives,
					} )
				}
			/>
			{ mode === 'math' && (
				<CheckboxControl
					label={ __(
						'Also accept equivalent expressions (for example 3x+4 for 4+3x)',
						'ohmylms'
					) }
					checked={ !! value.equivalence }
					onChange={ ( equivalence ) =>
						onChange( {
							equivalence,
						} )
					}
				/>
			) }
			{ mode === 'math' && value.equivalence && (
				<FormSelect
					value={ value.form }
					onChange={ ( form ) =>
						onChange( {
							form,
						} )
					}
				/>
			) }
		</div>
	);
}

/**
 * Author-side learner preview of an interactive question (answers are not graded or saved).
 * @param root0
 * @param root0.type
 * @param root0.settings
 */
export function InteractivePreview( { type, settings = {} } ) {
	const _controlId = useId();
	const [ picked, setPicked ] = useState( [] );
	if ( [ 'crossword', 'wordsearch' ].includes( type ) ) {
		return <PuzzlePreview type={ type } settings={ settings } />;
	}
	if ( type === 'text-entry' ) {
		return (
			<label htmlFor={ `${ _controlId }-text-entry` }>
				{ __( 'Your answer', 'ohmylms' ) }
				<input
					id={ `${ _controlId }-text-entry` }
					type="text"
					maxLength={ 2000 }
				/>
			</label>
		);
	}
	if ( VISUAL_TYPES.includes( type ) ) {
		return <VisualPreview type={ type } settings={ settings } />;
	}
	const parts = ( text, field ) =>
		String( text || '' )
			.split(
				/(\[\[ohmylms-math:latex:(?:inline|display)\]\][\s\S]{1,2000}?\[\[\/ohmylms-math\]\]|\{[a-z0-9_-]{1,20}\})/gi
			)
			.map( ( part, index ) =>
				index % 2 && part.startsWith( '{' ) ? (
					<Fragment key={ index }>
						{ field( part.slice( 1, -1 ) ) }
					</Fragment>
				) : (
					<Fragment key={ index }>{ part }</Fragment>
				)
			);
	if ( type === 'dropdown-blanks' ) {
		return (
			<p>
				{ parts( settings.text, ( id ) => {
					const slot = ( settings.slots || [] ).find(
						( s ) => s.id === id
					);
					return slot ? (
						<select
							multiple={ !! slot.multiple }
							aria-label={ sprintf(
								/* translators: %s: choice identifier from the question template. */ __(
									'Choice %s',
									'ohmylms'
								),
								id
							) }
						>
							{ ! slot.multiple && (
								<option value="">
									{ __( 'Choose…', 'ohmylms' ) }
								</option>
							) }
							{ ( slot.choices || [] ).map( ( choice, i ) => (
								<option key={ i }>{ choice }</option>
							) ) }
						</select>
					) : null;
				} ) }
			</p>
		);
	}
	if ( type === 'expression' ) {
		return (
			<input
				type="text"
				className="ohmylms-expression-input"
				aria-label={ __( 'Your answer', 'ohmylms' ) }
				placeholder={ __( 'e.g. 2(x+3)', 'ohmylms' ) }
			/>
		);
	}
	if ( type === 'multi-blank' ) {
		const input = ( id ) => (
			<input
				type="text"
				className={
					settings.blanks?.[ id ]?.kind === 'expression'
						? 'ohmylms-expression-input'
						: undefined
				}
				size={ 6 }
				aria-label={ sprintf(
					/* translators: %s: blank identifier from the question template. */ __(
						'Blank %s',
						'ohmylms'
					),
					id
				) }
			/>
		);
		if ( settings.layout === 'table' ) {
			return (
				<table className="ohmylms-blank-table">
					<thead>
						<tr>
							{ ( settings.columns || [] ).map( ( column, i ) => (
								<th key={ i }>{ column }</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ ( settings.rows || [] ).map( ( row, r ) => (
							<tr key={ r }>
								{ row.map( ( cell, c ) => (
									<td key={ c }>{ parts( cell, input ) }</td>
								) ) }
							</tr>
						) ) }
					</tbody>
				</table>
			);
		}
		return <p>{ parts( settings.text, input ) }</p>;
	}
	if ( type === 'categorize' ) {
		return (
			<ul className="ohmylms-cat-items">
				{ ( settings.items || [] ).map( ( item ) => (
					<li key={ item.id }>
						{ item.image_url && (
							<img src={ item.image_url } alt="" />
						) }
						<label
							htmlFor={ _controlId + '-field-1' + '-' + item.id }
						>
							{ item.text }{ ' ' }
							<select
								id={ _controlId + '-field-1' + '-' + item.id }
							>
								<option value="">
									{ __( 'Choose a group…', 'ohmylms' ) }
								</option>
								{ ( settings.buckets || [] ).map(
									( bucket ) => (
										<option
											key={ bucket.id }
											value={ bucket.id }
										>
											{ bucket.label }
										</option>
									)
								) }
							</select>
						</label>
					</li>
				) ) }
			</ul>
		);
	}
	if ( type === 'build-expression' ) {
		const tiles = [
			...( settings.correct || [] ),
			...( settings.distractors || [] ),
		].map( ( text, index ) => ( {
			text,
			index,
		} ) );
		return (
			<div className="ohmylms-build-expression">
				<div
					className="ohmylms-build-answer"
					aria-label={ __( 'Your answer', 'ohmylms' ) }
				>
					{ picked.map( ( tile ) => (
						<button
							type="button"
							key={ tile.index }
							className="ohmylms-tile is-placed"
							onClick={ () =>
								setPicked(
									picked.filter( ( t ) => t !== tile )
								)
							}
						>
							{ tile.text === ' ' ? '␣' : tile.text }
						</button>
					) ) }
				</div>
				<div className="ohmylms-build-bank">
					{ tiles
						.filter(
							( tile ) =>
								! picked.some(
									( placed ) => placed.index === tile.index
								)
						)
						.reverse()
						.map( ( tile ) => (
							<button
								type="button"
								key={ tile.index }
								className="ohmylms-tile"
								onClick={ () =>
									setPicked( [ ...picked, tile ] )
								}
							>
								{ tile.text === ' ' ? '␣' : tile.text }
							</button>
						) ) }
				</div>
			</div>
		);
	}
	return null;
}

/**
 * Accepted text answers using explicit automatic comparison rules.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 */
export function TextEntryEditor( { value, onChange } ) {
	return (
		<div className="ohmylms-interactive-editor">
			<ParsedField
				multiline
				label={ __( 'Accepted answers (one per line)', 'ohmylms' ) }
				value={ value.accepted }
				format={ ( list ) => ( list || [] ).join( '\n' ) }
				parse={ ( text ) =>
					text.split( /\r?\n/ ).filter( ( line ) => line.trim() )
				}
				onChange={ ( accepted ) => onChange( { accepted } ) }
			/>
			<CheckboxControl
				label={ __( 'Case-sensitive answers', 'ohmylms' ) }
				checked={ !! value.case_sensitive }
				onChange={ ( caseSensitive ) =>
					onChange( { case_sensitive: caseSensitive } )
				}
			/>
			<CheckboxControl
				label={ __( 'Treat repeated spaces as one', 'ohmylms' ) }
				checked={ !! value.collapse_whitespace }
				onChange={ ( collapse ) =>
					onChange( { collapse_whitespace: collapse } )
				}
			/>
			<CheckboxControl
				label={ __( 'Ignore punctuation', 'ohmylms' ) }
				checked={ !! value.ignore_punctuation }
				onChange={ ( ignore ) =>
					onChange( { ignore_punctuation: ignore } )
				}
			/>
			<p>
				{ __(
					'Leading and trailing spaces are ignored. Only the accepted answers and these rules determine correctness.',
					'ohmylms'
				) }
			</p>
		</div>
	);
}
