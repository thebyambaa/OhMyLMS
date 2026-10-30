import { CrosswordEditor, WordsearchEditor } from './PuzzleEditor';
import { ExtendedEditor } from './ExtendedEditor';
import { isExtendedType } from './extendedModel.mjs';
import { createElement, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, CheckboxControl, TextControl } from '@wordpress/components';
import { parseInlineBlankPrompt } from './inlineBlanks.mjs';
import { NumericalEditor, StructuredEditor } from './MathEditors';
import {
	BuildExpressionEditor,
	TextEntryEditor,
	CategorizeEditor,
	DropdownBlanksEditor,
	ExpressionEditor,
	MultiBlankEditor,
} from './InteractiveEditors';
import {
	BuildChartEditor,
	CountBlocksEditor,
	FillLevelEditor,
	GridBuildEditor,
	MakeAmountEditor,
	NumberLineEditor,
	SetClockEditor,
	ShadeModelEditor,
} from './VisualEditors';
import { QuestionMediaUpload as MediaUpload } from './QuestionMediaUpload';
import { moveOption } from './model.mjs';
import { RichContentControl } from '../math/RichContentControl';
import { ExtensionBoundary } from '../../extensions/ExtensionBoundary';

/**
 * Private answer controls used by the question block; they save through the assessment writer.
 * @param root0
 * @param root0.type
 * @param root0.options
 * @param root0.settings
 * @param root0.onChange
 * @param root0.prompt
 * @param root0.mainStatement
 * @param root0.collapseAnswerToolbar
 * @param root0.compactActions
 * @param root0.showAnswerToolbar
 * @param root0.question
 */
export function BankAnswerFields( {
	type,
	options = [],
	settings,
	onChange,
	prompt = '',
	mainStatement = false,
	showAnswerToolbar = false,
	collapseAnswerToolbar = false,
	compactActions = false,
	question,
} ) {
	const dragged = useRef( null );
	const custom =
		typeof window !== 'undefined' &&
		window.ohmylms?.extensions?.get?.( 'question-editor', type );
	if ( custom ) {
		return (
			<ExtensionBoundary key={ type } id={ custom.id || type }>
				{ createElement( custom.render, {
					question: question || {
						name: prompt,
						settings,
						questions: options,
					},
					value: settings,
					onChange: ( patch ) =>
						onChange( {
							settings: { ...settings, ...patch, type },
						} ),
				} ) }
			</ExtensionBoundary>
		);
	}
	if ( isExtendedType( type ) ) {
		return (
			<ExtendedEditor
				type={ type }
				mainStatement={ mainStatement }
				value={ settings }
				onChange={ ( patch ) =>
					onChange( {
						settings: {
							...settings,
							...patch,
						},
					} )
				}
			/>
		);
	}
	const blanks =
		type === 'fill-in-the-blank'
			? parseInlineBlankPrompt( prompt ).answers
			: [];
	if ( blanks.length ) {
		return (
			<div>
				<p>
					{ __(
						'Write each correct answer inside curly brackets in the Question field. Preview shows draggable answers below the blanks.',
						'ohmylms'
					) }
				</p>
				<div className="ohmylms-blank-answer-bank">
					{ blanks.map( ( answer ) => (
						<span
							className="ohmylms-blank-answer-chip"
							key={ answer.id }
						>
							{ answer.text }
						</span>
					) ) }
				</div>
				<CheckboxControl
					label={ __( 'Case-sensitive answers', 'ohmylms' ) }
					checked={
						! [ false, 0, '0' ].includes( settings.case_sensitive )
					}
					onChange={ ( _caseSensitive ) =>
						onChange( {
							settings: {
								...settings,
								case_sensitive: _caseSensitive,
							},
						} )
					}
				/>
			</div>
		);
	}
	const interactive = {
		crossword: CrosswordEditor,
		wordsearch: WordsearchEditor,
		'dropdown-blanks': DropdownBlanksEditor,
		categorize: CategorizeEditor,
		'multi-blank': MultiBlankEditor,
		'build-expression': BuildExpressionEditor,
		'text-entry': TextEntryEditor,
		expression: ExpressionEditor,
		'number-line': NumberLineEditor,
		'shade-model': ShadeModelEditor,
		'count-blocks': CountBlocksEditor,
		'set-clock': SetClockEditor,
		'make-amount': MakeAmountEditor,
		'fill-level': FillLevelEditor,
		'build-chart': BuildChartEditor,
		'grid-build': GridBuildEditor,
	}[ type ];
	if ( interactive ) {
		return createElement( interactive, {
			mainStatement,
			compactActions,
			value: settings,
			onChange: ( patch ) =>
				onChange( {
					settings: {
						...settings,
						...patch,
					},
				} ),
		} );
	}
	if ( type === 'numerical' ) {
		return (
			<NumericalEditor
				value={ settings }
				onGenerate={ ( preset ) =>
					onChange( {
						name: preset.prompt,
						description: preset.prompt,
						settings: { ...settings, ...preset.settings },
						questions: [],
					} )
				}
				onChange={ ( patch ) =>
					onChange( {
						settings: {
							...settings,
							...patch,
						},
					} )
				}
			/>
		);
	}
	if ( type === 'structured' ) {
		return (
			<StructuredEditor
				renderQuestion={ BankAnswerFields }
				value={ settings }
				onChange={ ( patch ) =>
					onChange( {
						settings: {
							...settings,
							...patch,
							score: {
								enabled: true,
								value: (
									patch.parts ||
									settings.parts ||
									[]
								).reduce(
									( sum, part ) =>
										sum + Number( part.marks || 0 ),
									0
								),
							},
						},
					} )
				}
			/>
		);
	}
	if ( [ 'short-text', 'long-text' ].includes( type ) ) {
		return (
			<p>
				{ __(
					'Learners write their answer; a teacher marks it.',
					'ohmylms'
				) }
			</p>
		);
	}
	if ( type === 'statement' ) {
		return (
			<p>
				{ __(
					'The statement is the question content above.',
					'ohmylms'
				) }
			</p>
		);
	}
	// Reorder and matching mark the answer by position or pair, not by a radio.
	const choices =
		! [ 'reorder', 'matching' ].includes( type ) &&
		( ( typeof window !== 'undefined' &&
			window.ohmylmsNativeLesson?.questionTypes?.[ type ]?.editor
				?.format === 'ohmylms-options' ) ||
			[ 'single-choice', 'multiple-choice', 'true-false' ].includes(
				type
			) );
	const patchOption = ( index, patch ) =>
		onChange( {
			questions: options.map( ( option, position ) =>
				position === index
					? {
							...option,
							...patch,
						}
					: option
			),
		} );
	const reorder = ( index, direction ) => {
		const next = [ ...options ];
		[ next[ index ], next[ index + direction ] ] = [
			next[ index + direction ],
			next[ index ],
		];
		onChange( {
			questions: next.map( ( option, position ) => ( {
				...option,
				order_number: position + 1,
			} ) ),
		} );
	};
	return (
		<div
			className={ `ohmylms-shared-answers ohmylms-bank-block-answers is-${ type }` }
			style={ {
				'--ohmylms-answer-columns': Math.min(
					4,
					Math.max( 1, options.length )
				),
			} }
		>
			{ type === 'fill-in-the-blank' && (
				<p>
					{ __(
						'Use {answer} in the question for inline blanks, or enter accepted answers below.',
						'ohmylms'
					) }
				</p>
			) }
			<p className="ohmylms-answer-guidance">
				{
					{
						matching: __(
							'Enter each item and its matching answer. Add a pair for every match learners should make.',
							'ohmylms'
						),
						reorder: __(
							'Enter items in the correct order. Use Move up and Move down to set the answer key.',
							'ohmylms'
						),
						'multiple-choice': __(
							'Enter answer options and check every correct answer.',
							'ohmylms'
						),
						'single-choice': __(
							'Select the circle beside the correct answer.',
							'ohmylms'
						),
						'true-false': __(
							'Select the circle beside the correct answer.',
							'ohmylms'
						),
					}[ type ]
				}
			</p>
			{ options.map( ( option, index ) => (
				<fieldset
					key={ option.clientKey ?? option.id ?? index }
					className="ohmylms-shared-answer-row"
					onDragOver={ ( event ) => event.preventDefault() }
					onDrop={ ( event ) => {
						event.preventDefault();
						if ( dragged.current !== null ) {
							onChange( {
								questions: moveOption(
									options,
									dragged.current,
									index
								),
							} );
						}
						dragged.current = null;
					} }
				>
					{ choices && (
						<input
							className="ohmylms-shared-answer-correct"
							type={
								type === 'multiple-choice'
									? 'checkbox'
									: 'radio'
							}
							aria-label={ sprintf(
								/* translators: %d: correct answer number. */ __(
									'Correct answer %d',
									'ohmylms'
								),
								index + 1
							) }
							checked={
								option.is_correct === true ||
								Number( option.is_correct ) === 1
							}
							onChange={ ( event ) =>
								onChange( {
									questions: options.map(
										( item, position ) => ( {
											...item,
											is_correct: ( () => {
												if ( position === index ) {
													return event.target.checked;
												}
												if (
													type === 'multiple-choice'
												) {
													return item.is_correct;
												}
												return false;
											} )(),
										} )
									),
								} )
							}
						/>
					) }
					{ [
						'single-choice',
						'multiple-choice',
						'matching',
						'reorder',
					].includes( type ) ? (
						<RichContentControl
							compact
							showToolbar={ showAnswerToolbar }
							collapseToolbar={ collapseAnswerToolbar }
							html={ false }
							label={ sprintf(
								/* translators: %d: answer number. */ __(
									'Answer %d',
									'ohmylms'
								),
								index + 1
							) }
							placeholder={ sprintf(
								/* translators: %d: option number. */ __(
									'Option %d',
									'ohmylms'
								),
								index + 1
							) }
							value={ option.answer || '' }
							onChange={ ( answer ) =>
								patchOption( index, {
									answer,
								} )
							}
						/>
					) : (
						<TextControl
							label={ sprintf(
								/* translators: %d: answer number. */ __(
									'Answer %d',
									'ohmylms'
								),
								index + 1
							) }
							hideLabelFromVision
							placeholder={ sprintf(
								/* translators: %d: option number. */ __(
									'Option %d',
									'ohmylms'
								),
								index + 1
							) }
							value={ option.answer || '' }
							disabled={ type === 'true-false' }
							onChange={ ( answer ) =>
								patchOption( index, {
									answer,
								} )
							}
						/>
					) }
					{ type !== 'true-false' && (
						<MediaUpload
							allowedTypes={ [ 'image' ] }
							value={ option.thumbnail_id }
							onSelect={ ( image ) =>
								patchOption( index, {
									thumbnail_id: image.id,
									image_url: image.url,
								} )
							}
							render={ ( { open } ) => (
								<Button
									icon="format-image"
									label={ __( 'Answer image', 'ohmylms' ) }
									onClick={ open }
								/>
							) }
						/>
					) }
					{ type === 'matching' && (
						<RichContentControl
							compact
							html={ false }
							label={ sprintf(
								/* translators: %d: match number. */ __(
									'Match %d',
									'ohmylms'
								),
								index + 1
							) }
							value={ option.matching_data?.label || '' }
							onChange={ ( label ) =>
								patchOption( index, {
									matching_data: {
										...option.matching_data,
										label,
									},
								} )
							}
						/>
					) }
					{ type === 'reorder' && (
						<>
							<Button
								icon={
									compactActions ? 'arrow-up-alt2' : undefined
								}
								label={ __( 'Move up', 'ohmylms' ) }
								disabled={ index === 0 }
								onClick={ () => reorder( index, -1 ) }
							>
								{ ! compactActions &&
									__( 'Move up', 'ohmylms' ) }
							</Button>
							<Button
								icon={
									compactActions
										? 'arrow-down-alt2'
										: undefined
								}
								label={ __( 'Move down', 'ohmylms' ) }
								disabled={ index === options.length - 1 }
								onClick={ () => reorder( index, 1 ) }
							>
								{ ! compactActions &&
									__( 'Move down', 'ohmylms' ) }
							</Button>
						</>
					) }
					{ type !== 'true-false' && (
						<Button
							icon="minus"
							label={ __( 'Remove answer', 'ohmylms' ) }
							disabled={
								options.length <=
								( type === 'fill-in-the-blank' ? 1 : 2 )
							}
							isDestructive
							variant="tertiary"
							onClick={ () =>
								onChange( {
									questions: options.filter(
										( _, position ) => index !== position
									),
								} )
							}
						></Button>
					) }
					{ type !== 'true-false' && (
						<Button
							icon="menu"
							label={ __( 'Reorder answer', 'ohmylms' ) }
							draggable
							onDragStart={ ( event ) => {
								dragged.current = index;
								event.dataTransfer.setData(
									'text/plain',
									String( index )
								);
							} }
							onDragEnd={ () => {
								dragged.current = null;
							} }
							onKeyDown={ ( event ) => {
								if (
									! event.altKey ||
									! [ 'ArrowUp', 'ArrowDown' ].includes(
										event.key
									)
								) {
									return;
								}
								event.preventDefault();
								const target =
									index +
									( event.key === 'ArrowUp' ? -1 : 1 );
								if ( target >= 0 && target < options.length ) {
									onChange( {
										questions: moveOption(
											options,
											index,
											target
										),
									} );
								}
							} }
						/>
					) }
					{ option.image_url && (
						<div className="ohmylms-shared-answer-image">
							<img
								src={ option.image_url }
								alt={ __( 'Answer image', 'ohmylms' ) }
							/>
							<Button
								isDestructive
								variant="tertiary"
								onClick={ () =>
									patchOption( index, {
										thumbnail_id: 0,
										image_url: '',
									} )
								}
							>
								{ __( 'Remove image', 'ohmylms' ) }
							</Button>
						</div>
					) }
				</fieldset>
			) ) }
			{ type !== 'true-false' && (
				<Button
					variant="secondary"
					onClick={ () =>
						onChange( {
							questions: [
								...options,
								{
									id: Math.max(
										Date.now(),
										...options.map(
											( option ) =>
												Number( option.id ) + 1 || 0
										)
									),
									temp: true,
									answer: '',
									is_correct: false,
									order_number: options.length + 1,
									matching_data: {
										label: '',
									},
								},
							],
						} )
					}
				>
					{ {
						matching: __( 'Add pair', 'ohmylms' ),
						reorder: __( 'Add item', 'ohmylms' ),
					}[ type ] || __( 'Add answer', 'ohmylms' ) }
				</Button>
			) }
		</div>
	);
}
