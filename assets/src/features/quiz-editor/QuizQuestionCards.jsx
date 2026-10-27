import { RichContentControl } from '../math/RichContentControl';
import { createElement, useEffect, useState } from '@wordpress/element';
import { Button, Modal, TextControl } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { QuestionTypeChooser } from '../question-editor/QuestionTypeChooser';
import { QuestionLivePreview } from '../question-editor/QuestionLivePreview';
import { QuizSkillsSummary, matchesSkillFilter } from './QuizSkills';
import { ConnectQuestionSkill } from './QuizSkillsTab';
import {
	promptText,
	questionPrompt,
	questionPromptPatch,
} from '../question-editor/questionPrompt.mjs';
import {
	isUngradedType,
	isExtendedType,
} from '../question-editor/extendedModel.mjs';
import { isInteractiveType } from '../question-editor/interactiveModel.mjs';
const overviewText = ( html ) =>
	promptText(
		String( html || '' ).replace(
			/<\/(?:p|div|li|h[1-6])>|<br\s*\/?\s*>/gi,
			' '
		)
	);
import {
	questionTypePatch,
	QUESTION_BLOCK_TYPES,
} from '../question-editor/questionBlocks.mjs';

const points = ( question ) => {
	const settings = question.settings || {};
	if ( isUngradedType( settings.type ) ) {
		return 0;
	}
	if ( [ 'structured', 'passage' ].includes( settings.type ) ) {
		return ( settings.parts || [] ).reduce(
			( sum, part ) => sum + ( Number( part.marks ) || 0 ),
			0
		);
	}
	return Math.max( 0, Number( settings.score?.value ?? 1 ) || 0 );
};
const typeLabel = ( question ) =>
	QUESTION_BLOCK_TYPES.find(
		( [ value ] ) => value === question.settings?.type
	)?.[ 1 ] ||
	question.settings?.type ||
	__( 'Single choice', 'ohmylms' );

/**
 * Question overview with shared versioned edit, preview and placement actions.
 *
 * @param {Object}   props          Component properties.
 * @param {Object}   props.editor   Canonical quiz editor.
 * @param {Array}    props.skills   Available question skills.
 * @param {Object}   props.children Active question workspace.
 * @param {()=>void} props.onBank   Open the question bank picker.
 */
export function QuizQuestionCards( { editor, skills = [], children, onBank } ) {
	const [ removing, setRemoving ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ choosingType, setChoosingType ] = useState( false );
	const [ preview, setPreview ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ skillFilter, setSkillFilter ] = useState( null );
	const [ editing, setEditing ] = useState( false );
	const [ workspaceKey, setWorkspaceKey ] = useState( 0 );
	const [ dragged, setDragged ] = useState( null );
	const [ quickEdited, setQuickEdited ] = useState( () => new Set() );
	useEffect( () => {
		setQuickEdited( new Set() );
	}, [ editor.quizId ] );
	const quickSaveLabel = ( question ) =>
		( {
			saved: __( 'Saved', 'ohmylms' ),
			saving: __( 'Saving…', 'ohmylms' ),
			pending: __( 'Not saved yet', 'ohmylms' ),
			error: __( 'Changes not saved', 'ohmylms' ),
		} )[ editor.quickEditStatus( question ) ];
	// A new quiz starts empty; the author chooses "Create question" when ready.
	useEffect( () => {
		if ( editor.question?.temp ) {
			setEditing( true );
		}
	}, [ editor.question?.id, editor.question?.temp ] );
	const openQuestion = ( question ) => {
		editor.selectQuestion( question );
		setEditing( true );
		setWorkspaceKey( ( old ) => old + 1 );
	};
	const totalPoints = editor.questions.reduce(
		( sum, question ) => sum + points( question ),
		0
	);
	const createButton = ( className = '' ) => (
		<Button
			className={ className }
			variant="secondary"
			icon="plus-alt2"
			disabled={ editor.loading || editor.saving }
			onClick={ () => setChoosingType( true ) }
		>
			{ __( 'Create question', 'ohmylms' ) }
		</Button>
	);
	return createElement(
		'section',
		{
			className: 'ohmylms-quiz-overview',
			'aria-label': __( 'Quiz questions', 'ohmylms' ),
		},
		<>
			<div className="ohmylms-quiz-overview-main">
				<div className="ohmylms-quiz-overview-toolbar">
					<p>
						<strong>
							{ sprintf(
								/* translators: %d: number of questions. */ _n(
									'%d Question',
									'%d Questions',
									editor.questions.length,
									'ohmylms'
								),
								editor.questions.length
							) }
						</strong>
						<span aria-hidden="true">•</span>
						{ sprintf(
							/* translators: %s: total question points. */ _n(
								'%s Point',
								'%s Points',
								totalPoints,
								'ohmylms'
							),
							totalPoints
						) }
					</p>
					{ createButton() }
				</div>
				<QuizSkillsSummary
					questions={ editor.questions }
					skills={ skills }
					selected={ skillFilter }
					onSelect={ setSkillFilter }
				/>
				<div className="ohmylms-overview-cards">
					{ editor.questions.map( ( question, index ) => {
						if ( ! matchesSkillFilter( question, skillFilter ) ) {
							return null;
						}
						const title =
							overviewText( questionPrompt( question ) ) ||
							__( 'Untitled question', 'ohmylms' );
						if (
							search &&
							! `${ title } ${ typeLabel( question ) }`
								.toLowerCase()
								.includes( search.toLowerCase() )
						) {
							return null;
						}
						return (
							<section
								key={ question.id }
								className={ `ohmylms-overview-card${ dragged === index ? ' is-dragging' : '' }` }
								aria-label={ sprintf(
									/* translators: %d: question number, count, or points. */ __(
										'Question %d',
										'ohmylms'
									),
									index + 1
								) }
								onDragOver={ ( event ) =>
									event.preventDefault()
								}
								onDrop={ ( event ) => {
									event.preventDefault();
									if (
										dragged !== null &&
										dragged !== index
									) {
										editor.moveQuestion( dragged, index );
									}
									setDragged( null );
								} }
							>
								<Button
									className="ohmylms-overview-drag"
									icon="menu"
									label={ __(
										'Drag to reorder question',
										'ohmylms'
									) }
									draggable={ ! editor.saving && ! search }
									disabled={
										editor.saving || Boolean( search )
									}
									onDragStart={ ( event ) => {
										setDragged( index );
										event.dataTransfer.effectAllowed =
											'move';
										event.dataTransfer.setData(
											'text/plain',
											String( question.id )
										);
									} }
									onDragEnd={ () => setDragged( null ) }
									onKeyDown={ ( event ) => {
										const movement = {
											ArrowUp: -1,
											ArrowDown: 1,
										};
										const next =
											index +
											( movement[ event.key ] || 0 );
										if (
											next !== index &&
											next >= 0 &&
											next < editor.questions.length
										) {
											event.preventDefault();
											editor.moveQuestion( index, next );
										}
									} }
								/>
								<header className="ohmylms-overview-card-header">
									<div className="ohmylms-overview-metadata">
										<strong>
											{ String( index + 1 ).padStart(
												2,
												'0'
											) }
										</strong>
										<span>{ typeLabel( question ) }</span>
										<span aria-hidden="true">•</span>
										<span>
											{ sprintf(
												/* translators: %s: question number, count, or points. */ __(
													'%s Pt',
													'ohmylms'
												),
												points( question )
											) }
										</span>
										{ question.readonly && (
											<span>
												{ __(
													'Pinned version',
													'ohmylms'
												) }
											</span>
										) }
									</div>
									<div className="ohmylms-overview-card-actions">
										<ConnectQuestionSkill
											iconOnly
											question={ question }
											skills={ skills }
											onChange={ editor.patchQuestion }
										/>
										<Button
											icon="visibility"
											label={ __(
												'Preview question',
												'ohmylms'
											) }
											onClick={ () =>
												setPreview( question )
											}
										/>
										<Button
											icon="admin-page"
											label={ __(
												'Duplicate question',
												'ohmylms'
											) }
											disabled={ editor.saving }
											onClick={ () =>
												editor.addQuestion( question )
											}
										/>
										<Button
											icon="trash"
											label={ __(
												'Remove question',
												'ohmylms'
											) }
											disabled={ editor.saving }
											onClick={ () =>
												setRemoving( question )
											}
										/>
										<Button
											icon="arrow-up-alt2"
											label={ __(
												'Move question up',
												'ohmylms'
											) }
											disabled={
												index === 0 || editor.saving
											}
											onClick={ () =>
												editor.moveQuestion(
													index,
													index - 1
												)
											}
										/>
										<Button
											icon="arrow-down-alt2"
											label={ __(
												'Move question down',
												'ohmylms'
											) }
											disabled={
												index ===
													editor.questions.length -
														1 || editor.saving
											}
											onClick={ () =>
												editor.moveQuestion(
													index,
													index + 1
												)
											}
										/>
										<Button
											icon="edit"
											onClick={ () =>
												openQuestion( question )
											}
											aria-label={ sprintf(
												/* translators: %d: question number, count, or points. */ __(
													'Edit question %d',
													'ohmylms'
												),
												index + 1
											) }
										>
											{ __( 'Edit', 'ohmylms' ) }
										</Button>
									</div>
								</header>
								{ question.readonly ? (
									<button
										type="button"
										className="ohmylms-overview-prompt"
										onClick={ () =>
											openQuestion( question )
										}
										aria-label={ sprintf(
											/* translators: %d: question number, count, or points. */ __(
												'Open question %d',
												'ohmylms'
											),
											index + 1
										) }
									>
										{ title }
									</button>
								) : (
									<div className="ohmylms-quick-question-prompt">
										<RichContentControl
											compact
											label={ sprintf(
												/* translators: %d: question number. */ __(
													'Quick edit question %d',
													'ohmylms'
												),
												index + 1
											) }
											value={ questionPrompt( question ) }
											onChange={ ( content ) => {
												setQuickEdited( ( current ) =>
													new Set( current ).add(
														question.id
													)
												);
												editor.patchQuestion(
													question.id,
													questionPromptPatch(
														question,
														content
													)
												);
											} }
											onDoubleClick={ () =>
												openQuestion( question )
											}
										/>
										{ quickEdited.has( question.id ) && (
											<span
												className="ohmylms-quick-question-save-status"
												role="status"
												aria-live="polite"
												data-save-error={
													editor.quickEditStatus(
														question
													) === 'error'
												}
											>
												{ quickSaveLabel( question ) }
											</span>
										) }
									</div>
								) }
								{ question.image_src && (
									<img
										className="ohmylms-overview-question-image"
										src={ question.image_src }
										alt={ __(
											'Question image',
											'ohmylms'
										) }
									/>
								) }
								{ Boolean( question.questions?.length ) &&
									question.settings?.type !==
										'fill-in-the-blank' &&
									! isInteractiveType(
										question.settings?.type
									) &&
									! isExtendedType(
										question.settings?.type
									) && (
										<ul className="ohmylms-overview-answers">
											{ question.questions.map(
												( option, optionIndex ) => {
													const correct =
														option.is_correct ===
															true ||
														Number(
															option.is_correct
														) === 1;
													return (
														<li
															key={
																option.id ||
																optionIndex
															}
														>
															<span
																className={ `ohmylms-overview-answer-mark${ correct ? ' is-correct' : '' }` }
																aria-label={
																	correct
																		? __(
																				'Correct answer',
																				'ohmylms'
																			)
																		: __(
																				'Answer option',
																				'ohmylms'
																			)
																}
															>
																{ correct
																	? '✓'
																	: '' }
															</span>
															{ option.image_url && (
																<img
																	className="ohmylms-overview-answer-image"
																	src={
																		option.image_url
																	}
																	alt={ __(
																		'Answer image',
																		'ohmylms'
																	) }
																/>
															) }
															<span>
																{ promptText(
																	option.answer
																) ||
																	sprintf(
																		/* translators: %d: question number, count, or points. */ __(
																			'Option %d',
																			'ohmylms'
																		),
																		optionIndex +
																			1
																	) }
																{ question
																	.settings
																	?.type ===
																	'matching' &&
																option
																	.matching_data
																	?.label
																	? ` → ${ option.matching_data.label }`
																	: '' }
															</span>
														</li>
													);
												}
											) }
										</ul>
									) }
							</section>
						);
					} ) }
					{ search &&
						! editor.questions.some( ( question ) =>
							`${ overviewText( questionPrompt( question ) ) } ${ typeLabel( question ) }`
								.toLowerCase()
								.includes( search.toLowerCase() )
						) && (
							<p role="status">
								{ __( 'No matching questions.', 'ohmylms' ) }
							</p>
						) }
				</div>
				{ createButton( 'ohmylms-overview-add-bottom' ) }
			</div>
			<aside
				className="ohmylms-overview-find"
				aria-label={ __( 'Find questions', 'ohmylms' ) }
			>
				<div className="ohmylms-overview-find-heading">
					<h2>{ __( 'Find questions', 'ohmylms' ) }</h2>
					<Button
						variant="secondary"
						icon="download"
						onClick={ onBank }
					>
						{ __( 'Add from bank', 'ohmylms' ) }
					</Button>
				</div>
				<TextControl
					label={ __( 'Search this quiz', 'ohmylms' ) }
					placeholder={ __(
						'Search question text or type',
						'ohmylms'
					) }
					value={ search }
					onChange={ setSearch }
				/>
				<div className="ohmylms-overview-find-help">
					<span
						className="dashicons dashicons-welcome-learn-more"
						aria-hidden="true"
					/>
					<h3>{ __( 'Build your quiz', 'ohmylms' ) }</h3>
					<p>
						{ __(
							'Create any question type, or reuse saved questions from your question bank.',
							'ohmylms'
						) }
					</p>
					{ createButton() }
				</div>
			</aside>
			{ editing && (
				<div
					key={ workspaceKey }
					className="ohmylms-overview-workspace"
				>
					{ children }
				</div>
			) }
			{ preview && (
				<Modal
					title={ __( 'Preview question', 'ohmylms' ) }
					onRequestClose={ () => setPreview( null ) }
					className="ohmylms-overview-preview"
				>
					<QuestionLivePreview
						key={ preview.id }
						question={ preview }
					/>
				</Modal>
			) }
			{ choosingType && (
				<QuestionTypeChooser
					onClose={ () => setChoosingType( false ) }
					onSelect={ ( type ) => {
						editor.addQuestion( questionTypePatch( {}, type ) );
						setEditing( true );
						setChoosingType( false );
					} }
				/>
			) }
			{ removing && (
				<Modal
					title={ __( 'Remove question from quiz?', 'ohmylms' ) }
					onRequestClose={ () => ! busy && setRemoving( null ) }
				>
					<p>
						{ __(
							'Saved questions remain in the question bank.',
							'ohmylms'
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						isBusy={ busy }
						disabled={ busy }
						onClick={ async () => {
							setBusy( true );
							await editor.removeQuestion( removing );
							setBusy( false );
							setRemoving( null );
						} }
					>
						{ __( 'Remove question', 'ohmylms' ) }
					</Button>
					<Button
						variant="tertiary"
						disabled={ busy }
						onClick={ () => setRemoving( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
