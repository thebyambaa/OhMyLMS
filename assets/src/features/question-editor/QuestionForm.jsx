import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { CheckboxControl, TextControl } from '@wordpress/components';
import { isUngradedType } from './extendedModel.mjs';
import { QuestionTypeMenu } from './QuestionTypeMenu';
import { FormWorkspace } from './FormWorkspace';
import { BankAnswerFields } from './BankAnswerFields';
import { QuestionOptionsBar } from './QuestionOptionsBar';
import { TemplatePanel } from './TemplatePanel';
import { ChoiceControls } from './ChoiceControls';
import {
	questionPrompt,
	questionPromptPatch,
	questionAnswerPatch,
} from './questionPrompt.mjs';
import { MathVariables } from '../math/MathInput';
import { parseInlineBlankPrompt } from './inlineBlanks.mjs';

// Add a type only when its prompt toolbar is explicitly requested.
const promptToolbarTypes = new Set();

/**
 * The question form shared by quiz authoring and standalone skill/bank authoring.
 * @param root0
 * @param root0.question
 * @param root0.onChange
 * @param root0.answers
 * @param root0.settings
 * @param root0.extraSettings
 * @param root0.types
 * @param root0.onTypeChange
 */
export function QuestionForm( {
	question,
	onChange,
	answers,
	settings,
	extraSettings,
	types,
	onTypeChange,
	...props
} ) {
	const choice = [ 'single-choice', 'multiple-choice' ].includes(
		question.settings?.type
	);
	const pointsControl = createElement( TextControl, {
		label: __( 'Points', 'ohmylms' ),
		type: 'number',
		min: 0,
		step: 'any',
		value: question.settings?.score?.value ?? 1,
		disabled:
			props.readOnly ||
			( question.settings?.type === 'dropdown-blanks' &&
				question.settings?.dropdown_grading_version === 2 ) ||
			isUngradedType( question.settings?.type ) ||
			[ 'structured', 'passage' ].includes( question.settings?.type ),
		onChange: ( marks ) =>
			onChange( {
				settings: {
					...question.settings,
					score: {
						...question.settings?.score,
						enabled: true,
						value: Math.max( 0, Number( marks ) || 0 ),
					},
				},
			} ),
	} );
	const sharedAnswers = (
		<BankAnswerFields
			type={ question.settings?.type }
			options={ question.questions || [] }
			settings={ question.settings || {} }
			prompt={ question.name }
			mainStatement
			onChange={ ( patch ) =>
				onChange( questionAnswerPatch( question, patch ) )
			}
		/>
	);
	return (
		<MathVariables.Provider
			value={ question.settings?.template?.variables || [] }
		>
			<FormWorkspace
				document={ {
					...question,
					description: questionPrompt( question ),
				} }
				titleLabel={ __( 'Question title', 'ohmylms' ) }
				titlePlaceholder={ __(
					'Type your question here …',
					'ohmylms'
				) }
				workspaceLabel={ __( 'Question form editor', 'ohmylms' ) }
				onTitleChange={ ( name ) => onChange( { name } ) }
				onContentChange={ ( description ) =>
					onChange( questionPromptPatch( question, description ) )
				}
				previewQuestion={ question }
				questionBlockContent={
					<>
						<div className="ohmylms-response-heading">
							<h3>
								{ types?.find(
									( option ) =>
										option.value === question.settings?.type
								)?.label || __( 'Answers', 'ohmylms' ) }
							</h3>
							<span>{ __( 'Answer setup', 'ohmylms' ) }</span>
						</div>
						{ answers || sharedAnswers }
						<TemplatePanel
							question={ question }
							onChange={ onChange }
							readOnly={ props.readOnly }
						/>
					</>
				}
				{ ...props }
				showContentToolbar={ promptToolbarTypes.has(
					question.settings?.type
				) }
				hideTitle
				contentLabel={ __( 'Question', 'ohmylms' ) }
				toolbarEnd={
					<>
						{ types && (
							<div className="ohmylms-question-type-picker">
								<QuestionTypeMenu
									value={
										choice
											? 'single-choice'
											: question.settings?.type
									}
									options={ types
										.filter(
											( item ) =>
												item.value !== 'multiple-choice'
										)
										.map( ( item ) =>
											item.value === 'single-choice'
												? {
														...item,
														label: __(
															'Multiple select',
															'ohmylms'
														),
													}
												: item
										) }
									disabled={ props.readOnly }
									onChange={ ( type ) => {
										if ( type ) {
											onTypeChange( type );
										}
									} }
								/>
							</div>
						) }
						<div className="ohmylms-question-points-picker">
							{ pointsControl }
						</div>
					</>
				}
				flatSettings={ false }
				showLayoutSwitch={ choice }
				footerLeading={
					<>
						{ choice && (
							<ChoiceControls
								question={ question }
								onChange={ onChange }
								readOnly={ props.readOnly }
							/>
						) }
						{ question.settings?.type === 'fill-in-the-blank' && (
							<CheckboxControl
								label={ __(
									'Enable partial grading',
									'ohmylms'
								) }
								help={ __(
									'Award an equal share of the points for each correct blank. When disabled, every blank must be correct.',
									'ohmylms'
								) }
								checked={
									question.settings?.partial_credit ??
									Boolean(
										parseInlineBlankPrompt( question.name )
											.answers.length
									)
								}
								disabled={ props.readOnly }
								onChange={ ( partialCredit ) =>
									onChange( {
										settings: {
											...question.settings,
											partial_credit: partialCredit,
										},
									} )
								}
							/>
						) }
					</>
				}
				compact
				settings={
					<>
						<QuestionOptionsBar
							question={ question }
							onChange={ onChange }
							types={ types }
							onTypeChange={ onTypeChange }
						/>
						{ extraSettings }
					</>
				}
			/>
		</MathVariables.Provider>
	);
}
