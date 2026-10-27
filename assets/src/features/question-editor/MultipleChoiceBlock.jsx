/** @jsx createElement */
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import {
	createBlock,
	getBlockType,
	registerBlockType,
} from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { select, useDispatch } from '@wordpress/data';
import {
	Button,
	Dropdown,
	Modal,
	PanelBody,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { RichContentControl } from '../math/RichContentControl';
import { BankAnswerFields } from './BankAnswerFields';
import { MatchTableGridEditor } from './MatchTableGridEditor';
import { choiceModePatch } from './choiceModel.mjs';
import { questionTypePatch, QUESTION_BLOCK_TYPES } from './questionBlocks.mjs';
import { nativeQuestionTypes } from '../learning/nativeAuthoring.mjs';
import { QuestionOptionsBar } from './QuestionOptionsBar';
import { QuestionTypeMenu } from './QuestionTypeMenu';
import { VariableChips, variableNames } from './VariableChips';
import { insertIntoStatement } from './formulaInput.mjs';
import { QuestionLivePreview } from './QuestionLivePreview';
import { QuestionSourceSwitch } from './QuestionSourceSwitch';
import {
	allowedSources,
	editorDeliveries,
	fromQuestionBlock,
} from './questionSource.mjs';
import { BankPicker } from '../question-bank/BankPicker';
import { TemplatePanel } from './TemplatePanel';
import { defaultTemplate } from './templateModel.mjs';
import { MathVariables } from '../math/MathInput';
import {
	questionAnswerPatch,
	questionPrompt,
	questionPromptPatch,
} from './questionPrompt.mjs';
import { loadMath, mathEnabled } from '../math/loader.mjs';
import { usePaperView } from '../paper/PaperCanvas';

export const MULTIPLE_CHOICE_BLOCK = 'ohmylms/shared-multiple-choice';

/**
 * Bank questions already placed in the open document, so the bank picker can skip them.
 * @return {number[]} Question IDs.
 */
function blockQuestionIds() {
	const collect = ( block ) =>
		block.attributes?.questionId
			? [ block.attributes.questionId ]
			: ( block.innerBlocks || [] ).flatMap( collect );
	return select( 'core/block-editor' ).getBlocks().flatMap( collect );
}

/**
 * Shared authoring controls. Assessment data stays in the versioned question writer.
 * @param root0
 * @param root0.question
 * @param root0.onChange
 * @param root0.onApprove
 * @param root0.onDuplicate
 * @param root0.onRemove
 * @param root0.allowWritten
 * @param root0.readOnly
 */
export function MultipleChoiceFields( {
	question,
	onChange,
	readOnly = false,
	allowWritten = true,
	onApprove,
	onDuplicate,
	onRemove,
} ) {
	const root = useRef( null );
	const [ variablesOpen, setVariablesOpen ] = useState( false );
	useEffect( () => {
		let disposed = false;
		let cleanup;
		if ( mathEnabled() ) {
			loadMath()
				.then( ( runtime ) => {
					if ( ! disposed && root.current ) {
						cleanup = runtime.mount( root.current );
					}
				} )
				.catch( () => {} );
		}
		return () => {
			disposed = true;
			cleanup?.();
		};
	}, [] );
	return (
		<MathVariables.Provider
			value={ question.settings?.template?.variables || [] }
		>
			<fieldset
				className={ `ohmylms-compact-choice ohmylms-choice-card${ question.settings.type === 'match-table-grid' ? ' is-match-table-grid' : '' }` }
				ref={ root }
				disabled={ readOnly }
			>
				{ readOnly ? (
					<QuestionLivePreview question={ question } />
				) : (
					<>
						<div className="ohmylms-choice-card-header">
							<RichContentControl
								label={ __( 'Question statement', 'ohmylms' ) }
								placeholder={ __(
									'Untitled question',
									'ohmylms'
								) }
								compact
								value={ questionPrompt( question ) }
								onChange={ ( value ) =>
									onChange(
										questionPromptPatch( question, value )
									)
								}
							/>
							<QuestionTypeMenu
								value={ question.settings.type }
								options={
									allowWritten
										? nativeQuestionTypes(
												QUESTION_BLOCK_TYPES
											)
										: [
												{
													value: 'single-choice',
													label: __(
														'Multiple choice',
														'ohmylms'
													),
												},
												{
													value: 'multiple-choice',
													label: __(
														'Checkboxes',
														'ohmylms'
													),
												},
											]
								}
								onChange={ ( type ) =>
									onChange(
										[
											'single-choice',
											'multiple-choice',
										].includes( question.settings.type ) &&
											[
												'single-choice',
												'multiple-choice',
											].includes( type )
											? choiceModePatch(
													question,
													type === 'multiple-choice'
												)
											: questionTypePatch(
													question,
													type
												)
									)
								}
							/>
						</div>
						<VariableChips
							names={ variableNames( question.settings ) }
							onPick={ ( name ) =>
								onChange(
									questionPromptPatch(
										question,
										insertIntoStatement(
											questionPrompt( question ),
											name
										)
									)
								)
							}
						/>
						{ question.settings.type === 'reorder' && (
							<p className="ohmylms-reorder-guidance">
								{ __(
									'Arrange items in the correct order.',
									'ohmylms'
								) }
							</p>
						) }
						{ question.settings.type === 'matching' && (
							<SelectControl
								label={ __( 'Player layout', 'ohmylms' ) }
								value={
									question.settings.match_layout === 'grid'
										? 'grid'
										: 'wire'
								}
								options={ [
									{
										value: 'wire',
										label: __(
											'Wire — draw a line between pairs',
											'ohmylms'
										),
									},
									{
										value: 'grid',
										label: __(
											'Grid — place tiles under definitions',
											'ohmylms'
										),
									},
								] }
								onChange={ ( layout ) =>
									onChange( {
										settings: {
											...question.settings,
											match_layout: layout,
										},
									} )
								}
								__nextHasNoMarginBottom
							/>
						) }
						{ [ 'short-text', 'long-text' ].includes(
							question.settings.type
						) ? (
							<div className="ohmylms-written-answer-preview">
								{ question.settings.type === 'short-text' ? (
									<input
										type="text"
										disabled
										aria-label={ __(
											'Learner short answer',
											'ohmylms'
										) }
										placeholder={ __(
											'Short answer text',
											'ohmylms'
										) }
									/>
								) : (
									<textarea
										disabled
										rows={ 3 }
										aria-label={ __(
											'Learner long answer',
											'ohmylms'
										) }
										placeholder={ __(
											'Long answer text',
											'ohmylms'
										) }
									/>
								) }
								<span>
									{ __( 'Teacher graded', 'ohmylms' ) }
								</span>
							</div>
						) : (
							<>
								{ ' ' }
								{ question.settings.type ===
								'match-table-grid' ? (
									<MatchTableGridEditor
										value={ question.settings }
										onChange={ ( patch ) =>
											onChange( {
												settings: {
													...question.settings,
													...patch,
												},
											} )
										}
									/>
								) : (
									<BankAnswerFields
										question={ question }
										mainStatement
										prompt={ questionPrompt( question ) }
										compactActions
										type={ question.settings.type }
										options={ question.questions || [] }
										settings={ question.settings }
										onChange={ ( patch ) =>
											onChange(
												questionAnswerPatch(
													question,
													patch
												)
											)
										}
									/>
								) }{ ' ' }
							</>
						) }
						<div className="ohmylms-choice-card-footer">
							{ onDuplicate && (
								<Button
									icon="admin-page"
									label={ __(
										'Duplicate question',
										'ohmylms'
									) }
									onClick={ onDuplicate }
								/>
							) }
							{ onRemove && (
								<Button
									icon="trash"
									label={ __(
										'Remove question block',
										'ohmylms'
									) }
									onClick={ onRemove }
								/>
							) }
							<ToggleControl
								label={
									question.settings.type ===
									'match-table-grid'
										? __(
												'Require a response in each row',
												'ohmylms'
											)
										: __( 'Required', 'ohmylms' )
								}
								checked={ !! question.settings.required }
								onChange={ ( required ) =>
									onChange( {
										settings: {
											...question.settings,
											required,
										},
									} )
								}
								__nextHasNoMarginBottom
							/>
							<div className="ohmylms-choice-variables">
								<ToggleControl
									label={
										question.settings.template
											? undefined
											: __( 'Variables', 'ohmylms' )
									}
									aria-label={ __( 'Variables', 'ohmylms' ) }
									checked={ !! question.settings.template }
									onChange={ ( enabled ) => {
										const { template, ...settings } =
											question.settings;
										onChange( {
											settings: enabled
												? {
														...settings,
														template:
															template ||
															defaultTemplate(),
													}
												: settings,
										} );
										setVariablesOpen( enabled );
									} }
									__nextHasNoMarginBottom
								/>
								{ question.settings.template && (
									<Button
										variant="link"
										aria-haspopup="dialog"
										aria-expanded={ variablesOpen }
										onClick={ () =>
											setVariablesOpen( true )
										}
									>
										{ __( 'Variables', 'ohmylms' ) }
									</Button>
								) }
							</div>
							{ variablesOpen && question.settings.template && (
								<Modal
									title={ __(
										'Edit randomized variables',
										'ohmylms'
									) }
									isFullScreen
									onRequestClose={ () =>
										setVariablesOpen( false )
									}
								>
									<TemplatePanel
										question={ question }
										onChange={ onChange }
										readOnly={ readOnly }
									/>
								</Modal>
							) }
							<Dropdown
								contentClassName="ohmylms-choice-settings-popover"
								renderToggle={ ( { isOpen, onToggle } ) => (
									<Button
										icon="ellipsis"
										label={ __(
											'Question settings',
											'ohmylms'
										) }
										aria-expanded={ isOpen }
										onClick={ onToggle }
									/>
								) }
								renderContent={ () => (
									<>
										<QuestionOptionsBar
											question={ question }
											onChange={ onChange }
											hideRequired
											hideRandomize={ [
												'short-text',
												'long-text',
											].includes(
												question.settings.type
											) }
										/>
										{ question.settings.type ===
											'multiple-choice' && (
											<ToggleControl
												label={ __(
													'Allow partial grading',
													'ohmylms'
												) }
												checked={
													!! question.settings
														.partial_credit
												}
												onChange={ ( partialCredit ) =>
													onChange( {
														settings: {
															...question.settings,
															partial_credit:
																partialCredit,
														},
													} )
												}
											/>
										) }
										{ onApprove && (
											<Button
												variant="secondary"
												onClick={ onApprove }
											>
												{ __(
													'Save and approve for learners',
													'ohmylms'
												) }
											</Button>
										) }
									</>
								) }
							/>
						</div>
					</>
				) }
			</fieldset>
		</MathVariables.Provider>
	);
}

/**
 * Register the question block in the native lesson and quiz editors.
 * @param LessonEdit
 */
export function registerMultipleChoiceBlock( LessonEdit ) {
	if ( getBlockType( MULTIPLE_CHOICE_BLOCK ) ) {
		return;
	}
	function Edit( props ) {
		const blockProps = useBlockProps( {
			className: `ohmylms-shared-choice-block${
				props.isSelected ? '' : ' is-collapsed'
			}`,
			contentEditable: false,
		} );
		const postType = props.context?.postType || '';
		const allowed = allowedSources( {
			postType,
			deliveries: editorDeliveries(),
		} );
		const { replaceBlocks } = useDispatch( 'core/block-editor' );
		const [ picking, setPicking ] = useState( false );
		const paper = usePaperView();
		const choose = ( source ) => {
			const step = fromQuestionBlock( source, { postType } );
			if ( step.action === 'player' ) {
				replaceBlocks(
					props.clientId,
					createBlock( 'ohmylms/question-player', step.attributes )
				);
			} else if ( step.action === 'pick' ) {
				setPicking( true );
			}
		};
		const content = <LessonEdit { ...props } />;
		return (
			<div { ...blockProps }>
				{ props.isSelected && ! paper && (
					<QuestionSourceSwitch
						source="write"
						allowed={ allowed }
						onChange={ choose }
					/>
				) }
				{ paper && (
					<InspectorControls>
						<PanelBody title={ __( 'Question source', 'ohmylms' ) }>
							<QuestionSourceSwitch
								source="write"
								allowed={ allowed }
								onChange={ choose }
							/>
						</PanelBody>
					</InspectorControls>
				) }
				{ content }
				{ picking && (
					<BankPicker
						quizId={ props.context?.postId }
						existingIds={ blockQuestionIds() }
						onClose={ () => setPicking( false ) }
						onPick={ ( ids ) => {
							// Questions picked for a quiz become its slots, in place of this blank card.
							replaceBlocks(
								props.clientId,
								ids.map( ( questionId ) =>
									createBlock( MULTIPLE_CHOICE_BLOCK, {
										questionId,
										delivery: 'quiz',
									} )
								)
							);
							setPicking( false );
						} }
					/>
				) }
			</div>
		);
	}
	registerBlockType( MULTIPLE_CHOICE_BLOCK, {
		apiVersion: 3,
		title: __( 'OhMyLMS Question', 'ohmylms' ),
		category: 'text',
		icon: 'welcome-learn-more',
		attributes: {
			questionId: { type: 'integer', default: 0 },
			uuid: { type: 'string', default: '' },
			delivery: { type: 'string', default: 'inline' },
			authoringRevision: { type: 'integer', default: 0 },
		},
		usesContext: [ 'postId', 'postType' ],
		supports: { html: false, reusable: false },
		edit: Edit,
		save: () => null,
	} );
}
