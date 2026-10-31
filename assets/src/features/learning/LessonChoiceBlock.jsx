/** @jsx createElement */
import {
	createElement,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { useDispatch, useSelect, select } from '@wordpress/data';
import { NativeSaveGuard } from './NativeSaveGuard';
import { rememberQuestion, stepQuestionHistory } from './questionHistory.mjs';
import {
	Notice,
	PanelBody,
	Spinner,
	ToolbarGroup,
	ToolbarButton,
} from '@wordpress/components';
import {
	QUESTION_BLOCK_TYPES,
	questionTypePatch,
} from '../question-editor/questionBlocks.mjs';
import { nativeQuestionTypes } from './nativeAuthoring.mjs';
import { NativeQuestionDetails } from './NativeQuestionDetails';
import { __ } from '@wordpress/i18n';
import {
	registerBlockType,
	getBlockType,
	createBlock,
} from '@wordpress/blocks';
import {
	useBlockProps,
	BlockControls,
	InspectorControls,
} from '@wordpress/block-editor';
import { choiceReadyToSave, reconcileChoiceSave } from './choiceAutosave.mjs';
import {
	loadBankQuestion,
	createBankQuestion,
	saveQuestionAttributes,
	approveQuestion,
	duplicateQuestion,
} from '../question-bank/api.mjs';
import {
	MultipleChoiceFields,
	registerMultipleChoiceBlock,
} from '../question-editor/MultipleChoiceBlock';
import { questionPrompt } from '../question-editor/questionPrompt.mjs';
import { PaperQuestion } from '../paper/PaperQuestion';
import { usePaperQuestion } from '../paper/PaperCanvas';
import { QuestionBlockPreview } from './QuestionBlockPreview';

/**
 * Question fields stay on the canvas, except on the printable paper, where the sheet shows the
 * printed question and the fields move to the block sidebar.
 *
 * @param {Object}  props          Properties.
 * @param {boolean} props.paper    Whether paper view is on.
 * @param {Element} props.children Question fields.
 * @return {Element} Fields in place or in the sidebar.
 */
function QuestionFieldsSlot( { paper, children } ) {
	if ( ! paper ) {
		return children;
	}
	return (
		<InspectorControls>
			<PanelBody title={ __( 'Question', 'ohmylms' ) }>
				{ children }
			</PanelBody>
		</InspectorControls>
	);
}

/**
 * A lesson placement stores references only; private authoring data uses the bank API.
 * @param root0
 * @param root0.attributes
 * @param root0.setAttributes
 * @param root0.clientId
 * @param root0.context
 * @param root0.isSelected
 */
function LessonChoiceEdit( {
	attributes,
	setAttributes,
	clientId,
	context = {},
	isSelected = false,
} ) {
	const standalone = context.postType === 'ohmylms-question';
	const quiz = context.postType === 'ohmylms-quiz' || standalone;
	const coreSaving = useSelect(
		( getStore ) => getStore( 'core/editor' ).isSavingPost(),
		[]
	);
	const [ question, setQuestion ] = useState( null );
	const [ detail, setDetail ] = useState( null );
	const [ dirty, setDirty ] = useState(
		! attributes.questionId || ! attributes.uuid
	);
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	// A shared question the author may use but not edit: its content and answer key stay private.
	const [ shared, setShared ] = useState( false );
	const loadedId = useRef( 0 );
	const revision = useRef( 0 );
	const history = useRef( { past: [], future: [] } );
	const latestQuestion = useRef( question );
	latestQuestion.current = question;
	const { insertBlock, removeBlock } = useDispatch( 'core/block-editor' );
	const { lockPostSaving, unlockPostSaving } = useDispatch( 'core/editor' );
	const printed = usePaperQuestion( clientId, question );
	const paper = printed.view && ! standalone;
	const preview = ! isSelected && ! standalone;
	useEffect( () => {
		if ( context.postType ) {
			let delivery = quiz ? 'quiz' : 'inline';
			if ( standalone ) {
				delivery = 'bank';
			}
			if ( attributes.delivery !== delivery ) {
				setAttributes( { delivery } );
			}
		}
	}, [
		context.postType,
		attributes.delivery,
		quiz,
		standalone,
		setAttributes,
	] );
	useEffect( () => {
		const lock = `ohmylms-question-${ clientId }`;
		if ( dirty || busy ) {
			lockPostSaving( lock );
		} else {
			unlockPostSaving( lock );
		}
		return () => unlockPostSaving( lock );
	}, [ dirty, busy, clientId, lockPostSaving, unlockPostSaving ] );
	useEffect( () => {
		let active = true;
		if (
			attributes.questionId &&
			loadedId.current === attributes.questionId
		) {
			return undefined;
		}
		if ( ! attributes.questionId ) {
			loadedId.current = 0;
			setQuestion( {
				name: '',
				description: '',
				settings: {
					type: 'single-choice',
					score: { enabled: true, value: 1 },
				},
				questions: [
					{
						id: Date.now(),
						temp: true,
						answer: '',
						is_correct: true,
						order_number: 1,
					},
					{
						id: Date.now() + 1,
						temp: true,
						answer: '',
						is_correct: false,
						order_number: 2,
					},
				],
			} );
			return undefined;
		}
		setQuestion( null );
		setShared( false );
		loadBankQuestion( attributes.questionId )
			.then( ( data ) => {
				if ( active ) {
					loadedId.current = attributes.questionId;
					setDetail( data );
					if ( attributes.uuid !== data.uuid ) {
						setAttributes( { uuid: data.uuid } );
					}
					setDirty( ! data.settings?.type );
					setQuestion( {
						...( ! data.settings?.type
							? questionTypePatch(
									{ settings: {} },
									'single-choice'
								)
							: {} ),
						id: data.id,
						name: data.settings?.type ? data.name : '',
						description: data.description,
						...( data.settings?.type
							? {
									settings: data.settings,
									questions: data.options || [],
								}
							: {} ),
					} );
				}
			} )
			.catch( ( failure ) => {
				if ( ! active ) {
					return;
				}
				if ( failure.code === 'ohmylms_question_cannot_view' ) {
					setShared( true );
					setDirty( false );
				} else {
					setError( failure.message );
				}
			} );
		return () => {
			active = false;
		};
	}, [ attributes.questionId, attributes.uuid, setAttributes ] );
	useEffect( () => {
		if ( coreSaving || ! attributes.questionId || ! loadedId.current ) {
			return undefined;
		}
		let active = true;
		loadBankQuestion( attributes.questionId )
			.then( ( fresh ) => active && setDetail( fresh ) )
			.catch( ( cause ) => active && setError( cause.message ) );
		return () => {
			active = false;
		};
	}, [ coreSaving, attributes.questionId ] );
	const save = useCallback(
		async ( approve = false ) => {
			const submittedRevision = revision.current;
			setBusy( true );
			setError( '' );
			try {
				const base = attributes.questionId
					? detail ||
						( await loadBankQuestion( attributes.questionId ) )
					: null;
				const payload = {
					...( standalone && ! detail?.type
						? {
								status: 'draft',
								...( window.ohmylmsNativeLesson?.initialSkillId
									? {
											skills: {
												p1: {
													primary: Number(
														window
															.ohmylmsNativeLesson
															.initialSkillId
													),
													supporting: [],
												},
											},
										}
									: {} ),
							}
						: {} ),
					name: question.name,
					description: question.description,
					settings: question.settings,
					questions: question.questions.map(
						( { clientKey, ...option } ) => option
					),
					...( base ? { base_modified: base.modified } : {} ),
				};
				const saved = attributes.questionId
					? await saveQuestionAttributes(
							attributes.questionId,
							payload
						)
					: await createBankQuestion( payload );
				const id = attributes.questionId || saved.id;
				// Keep the created identity even if refreshing its private details fails.
				loadedId.current = id;
				setAttributes( { questionId: id } );
				const fresh = await loadBankQuestion( id );
				setDetail( fresh );
				const current = latestQuestion.current || question;
				const unchanged = revision.current === submittedRevision;
				const reconciled = reconcileChoiceSave(
					question,
					current,
					fresh
				);
				const next = unchanged
					? { ...reconciled, settings: fresh.settings }
					: reconciled;
				latestQuestion.current = next;
				setQuestion( next );
				setAttributes( {
					questionId: id,
					uuid: fresh.uuid,
					authoringRevision:
						( Number( attributes.authoringRevision ) || 0 ) + 1,
				} );
				setDirty( ! unchanged );
				if ( approve ) {
					await approveQuestion( id, fresh.current_version_id );
					setDetail( await loadBankQuestion( id ) );
				}
			} catch ( failure ) {
				setError( failure.message );
			} finally {
				setBusy( false );
			}
		},
		[
			attributes.questionId,
			attributes.authoringRevision,
			question,
			standalone,
			detail,
			setAttributes,
		]
	);
	useEffect( () => {
		if (
			! dirty ||
			busy ||
			error ||
			detail?.can_edit === false ||
			! choiceReadyToSave( question )
		) {
			return undefined;
		}
		const timer = window.setTimeout( () => save(), 650 );
		return () => window.clearTimeout( timer );
	}, [ dirty, busy, error, question, detail?.can_edit, save ] );
	async function duplicate() {
		try {
			const created = await duplicateQuestion( attributes.questionId );
			const fresh = await loadBankQuestion( created.id );
			const editor = select( 'core/block-editor' );
			insertBlock(
				createBlock( 'ohmylms/shared-multiple-choice', {
					questionId: fresh.id,
					uuid: fresh.uuid,
					delivery: quiz ? 'quiz' : 'inline',
				} ),
				editor.getBlockIndex( clientId ) + 1,
				editor.getBlockRootClientId( clientId )
			);
		} catch ( failure ) {
			setError( failure.message );
		}
	}
	if ( ! question && shared ) {
		return (
			<Notice
				status="info"
				isDismissible={ false }
				actions={ [
					{
						label: __( 'Duplicate to edit', 'ohmylms' ),
						onClick: duplicate,
					},
					...( standalone
						? []
						: [
								{
									label: __( 'Remove', 'ohmylms' ),
									onClick: () => removeBlock( clientId ),
								},
							] ),
				] }
			>
				{ __(
					'This shared bank question is read-only for you, so its content and answers are not shown here. Learners get its approved version.',
					'ohmylms'
				) }
				{ error && <p role="alert">{ error }</p> }
			</Notice>
		);
	}
	if ( ! question ) {
		return error ? (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) : (
			<Spinner />
		);
	}
	const supported = (
		quiz
			? nativeQuestionTypes( QUESTION_BLOCK_TYPES ).map(
					( item ) => item.value
				)
			: [ 'single-choice', 'multiple-choice' ]
	).includes( question.settings?.type );
	let incompleteMessage = __(
		'Complete the question and options, and select a correct answer.',
		'ohmylms'
	);
	if (
		[
			'number-line',
			'shade-model',
			'count-blocks',
			'set-clock',
			'make-amount',
			'fill-level',
			'build-chart',
			'grid-build',
			'labeling',
			'hotspot',
			'draw',
		].includes( question.settings.type )
	) {
		incompleteMessage = __(
			'Complete the question and its visual answer settings to save.',
			'ohmylms'
		);
	} else if (
		[ 'short-text', 'long-text' ].includes( question.settings.type )
	) {
		incompleteMessage = __( 'Enter a question to save.', 'ohmylms' );
	} else if ( question.settings.type === 'numerical' ) {
		incompleteMessage = __(
			'Enter a finite correct answer to save.',
			'ohmylms'
		);
	} else if ( question.settings.type === 'structured' ) {
		incompleteMessage = __(
			'Add at least one part and complete its answer and marks to save.',
			'ohmylms'
		);
	} else if ( question.settings.type === 'graphing' ) {
		incompleteMessage = __(
			'Keep the axes between -100 and 100, put every expected point inside them, use a tolerance from 0 to 1, and give a line two different points.',
			'ohmylms'
		);
	} else if ( question.settings.type === 'reorder' ) {
		incompleteMessage = __( 'Add at least two items to save.', 'ohmylms' );
	} else if ( question.settings.type === 'match-table-grid' ) {
		incompleteMessage = __(
			'Add row and column labels, and choose a correct column for every row.',
			'ohmylms'
		);
	} else if ( question.settings.type === 'matching' ) {
		incompleteMessage = __(
			'Complete at least two matching pairs to save.',
			'ohmylms'
		);
	} else if ( question.settings.type === 'fill-in-the-blank' ) {
		incompleteMessage = __(
			'Write {answer} in the question, or add an accepted answer below.',
			'ohmylms'
		);
	} else if ( question.settings.type === 'expression' ) {
		incompleteMessage = __(
			'Enter a correct math expression to save.',
			'ohmylms'
		);
	} else if ( question.settings.type === 'build-expression' ) {
		incompleteMessage = __(
			'Enter at least two tiles in the correct order to save.',
			'ohmylms'
		);
	} else if ( question.settings.type === 'multi-blank' ) {
		incompleteMessage = __(
			'Add {a}, {b} or other blank markers, and set an answer for each blank.',
			'ohmylms'
		);
	} else if ( question.settings.type === 'dropdown-blanks' ) {
		incompleteMessage = __(
			'Write {1} in the question, add dropdown choices, and select a correct choice.',
			'ohmylms'
		);
	}

	let answerFields = (
		<p>
			{ __(
				'The editor for this question type is unavailable. Enable its extension to edit it.',
				'ohmylms'
			) }
		</p>
	);
	if ( supported ) {
		answerFields = (
			<MultipleChoiceFields
				question={ question }
				allowWritten={ quiz }
				readOnly={ coreSaving || detail?.can_edit === false }
				onRemove={
					standalone ? undefined : () => removeBlock( clientId )
				}
				onDuplicate={
					! standalone && ! busy && choiceReadyToSave( question )
						? duplicate
						: undefined
				}
				onApprove={
					( standalone || ! quiz ) && detail?.can_approve && ! busy
						? () => save( true )
						: undefined
				}
				onChange={ ( patch ) => {
					rememberQuestion( history.current, question );
					revision.current++;
					const next = { ...question, ...patch };
					latestQuestion.current = next;
					setQuestion( next );
					setDirty( true );
					setError( '' );
				} }
			/>
		);
	} else if ( ! quiz && question.settings?.type ) {
		answerFields = (
			<>
				<MultipleChoiceFields
					question={ question }
					readOnly
					onRemove={
						standalone ? undefined : () => removeBlock( clientId )
					}
				/>
				<p className="ohmylms-inline-check-note">
					{ __(
						'This inline check shows the question as learners see it. Edit its content in the question bank; changes appear here once approved.',
						'ohmylms'
					) }{ ' ' }
					<a
						href={ `post.php?post=${ attributes.questionId }&action=edit` }
						target="_blank"
						rel="noreferrer"
					>
						{ __( 'Open in the question bank', 'ohmylms' ) }
					</a>
				</p>
			</>
		);
	}

	const restoreEdit = ( direction ) => {
		const next = stepQuestionHistory(
			history.current,
			question,
			direction
		);
		if ( next ) {
			revision.current++;
			latestQuestion.current = next;
			setQuestion( next );
			setDirty( true );
			setError( '' );
		}
	};
	return (
		<>
			<NativeSaveGuard dirty={ dirty } busy={ busy } />
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon="undo"
						label={ __( 'Undo question edit', 'ohmylms' ) }
						disabled={
							busy || coreSaving || ! history.current.past.length
						}
						onClick={ () => restoreEdit( 'past' ) }
					/>
					<ToolbarButton
						icon="redo"
						label={ __( 'Redo question edit', 'ohmylms' ) }
						disabled={
							busy ||
							coreSaving ||
							! history.current.future.length
						}
						onClick={ () => restoreEdit( 'future' ) }
					/>
				</ToolbarGroup>
			</BlockControls>
			{ error && (
				<Notice
					status="error"
					isDismissible={ false }
					actions={ [
						{
							label: __( 'Retry', 'ohmylms' ),
							onClick: () => save(),
						},
					] }
				>
					{ error }
				</Notice>
			) }
			{ paper && ! preview && (
				<PaperQuestion
					question={ question }
					prompt={ questionPrompt( question ) }
					label={ printed.label }
					settings={ printed.settings }
					showAnswers={ printed.answers }
					editor
				/>
			) }
			{ paper && dirty && ! busy && ! choiceReadyToSave( question ) && (
				<p className="ohmylms-print-note" role="status">
					{ incompleteMessage }
				</p>
			) }
			{ preview && (
				<QuestionBlockPreview
					question={ question }
					clientId={ clientId }
				/>
			) }
			{ ! preview && (
				<QuestionFieldsSlot paper={ paper }>
					{ dirty &&
						! busy &&
						supported &&
						! choiceReadyToSave( question ) && (
							<p
								className="ohmylms-choice-validation"
								role="status"
							>
								{ incompleteMessage }
							</p>
						) }
					{ answerFields }
				</QuestionFieldsSlot>
			) }
			{ attributes.questionId > 0 && (
				<NativeQuestionDetails
					id={ attributes.questionId }
					revision={ attributes.authoringRevision }
					quizId={
						context.postType === 'ohmylms-quiz' ? context.postId : 0
					}
					busy={ busy || dirty }
					onRefresh={ ( fresh, restored = false ) => {
						setDetail( fresh );
						setAttributes( {
							authoringRevision:
								( Number( attributes.authoringRevision ) ||
									0 ) + 1,
						} );
						if ( restored ) {
							const next = {
								id: fresh.id,
								name: fresh.name,
								description: fresh.description,
								settings: fresh.settings,
								questions: fresh.options || [],
							};
							latestQuestion.current = next;
							setQuestion( next );
						}
					} }
				/>
			) }
		</>
	);
}

registerMultipleChoiceBlock( LessonChoiceEdit );

/**
 * Keep established question formats movable in native quiz documents.
 * @param {Object} props Block properties.
 * @return {Element} Existing question reference.
 */
function QuizQuestionReferenceEdit( props ) {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<LessonChoiceEdit { ...props } />
		</div>
	);
}

if ( ! getBlockType( 'ohmylms/quiz-question-reference' ) ) {
	registerBlockType( 'ohmylms/quiz-question-reference', {
		apiVersion: 3,
		title: __( 'Existing quiz question', 'ohmylms' ),
		category: 'text',
		icon: 'welcome-learn-more',
		attributes: {
			questionId: { type: 'integer', default: 0 },
			authoringRevision: { type: 'integer', default: 0 },
			delivery: { type: 'string', default: 'quiz' },
			uuid: { type: 'string', default: '' },
		},
		usesContext: [ 'postId', 'postType' ],
		supports: { html: false, inserter: false, reusable: false },
		edit: QuizQuestionReferenceEdit,
		save: () => null,
	} );
}
