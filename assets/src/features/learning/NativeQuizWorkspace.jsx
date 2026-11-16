import {
	createElement,
	createPortal,
	useEffect,
	useState,
} from '@wordpress/element';
import { select, useDispatch, useSelect } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { usePaperView } from '../paper/public.mjs';
import { focusQuizCanvas, quizQuestions } from './quizWorkspace.mjs';

/**
 * Name a question in the navigator by the start of its statement, with its type and marks under
 * it. Reuses the metadata already registered by the mounted question editor.
 *
 * @param {Object} props          Properties.
 * @param {string} props.clientId Question block ID.
 * @param {number} props.number   Position in the quiz, from 1.
 * @return {React.ReactElement} Label.
 */
function QuestionLabel( { clientId, number } ) {
	const { type, marks, title } = useSelect(
		( getStore ) => {
			const paper = getStore( 'ohmylms/paper' );
			return {
				type: paper.getQuestionType( clientId ),
				marks: paper.getQuestionMarks( clientId ),
				title: paper.getQuestionTitle( clientId ),
			};
		},
		[ clientId ]
	);
	const label =
		window.ohmylmsNativeLesson?.questionTypes?.[ type ]?.label || type;
	const details = [
		label,
		marks > 0
			? sprintf(
					/* translators: %s: Number of marks. */
					_n( '%s mark', '%s marks', marks, 'ohmylms' ),
					String( marks )
				)
			: '',
	].filter( Boolean );
	return (
		<>
			<span className="ohmylms-quiz-workspace-title">
				<span className="ohmylms-quiz-workspace-number">
					{ number }
				</span>
				<span className="ohmylms-quiz-workspace-text">
					{ title || __( 'Untitled question', 'ohmylms' ) }
				</span>
			</span>
			{ details.length > 0 && (
				<small className="ohmylms-quiz-workspace-type">
					{ details.join( ' · ' ) }
				</small>
			) }
		</>
	);
}

/**
 * Quiz-only navigation around the existing Gutenberg canvas. Exam paper layouts
 * and native save/publish controls remain owned by their existing components.
 *
 * @param {Object}     props        Properties.
 * @param {() => void} props.onBank Open the existing bank picker.
 * @return {React.ReactPortal|null} Quiz navigator.
 */
export function NativeQuizWorkspace( { onBank } ) {
	const [ host, setHost ] = useState(
		/** @type {HTMLElement|null} */ ( null )
	);
	const [ mode, setMode ] = useState( 'question' );
	const [ activeId, setActiveId ] = useState( '' );
	const paper = usePaperView();
	const { questions, selectedId, moveIndex, siblingCount, canMove, canAdd } =
		useSelect(
			( getStore ) => {
				const editor = getStore( 'core/block-editor' );
				const root = editor.getBlockRootClientId( activeId ) || '';
				return {
					questions: quizQuestions( editor.getBlocks() ),
					selectedId: editor.getSelectedBlockClientId(),
					moveIndex: editor.getBlockIndex( activeId ),
					siblingCount: editor.getBlockOrder( root ).length,
					canMove:
						!! activeId &&
						editor.canMoveBlocks( [ activeId ], root ),
					canAdd: editor.canInsertBlockType(
						'ohmylms/shared-multiple-choice'
					),
				};
			},
			[ activeId ]
		);
	const {
		selectBlock,
		insertBlock,
		moveBlockToPosition,
		clearSelectedBlock,
	} = useDispatch( 'core/block-editor' );
	const { openGeneralSidebar } = useDispatch( 'core/edit-post' );

	useEffect( () => {
		const container = document.createElement( 'aside' );
		container.className = 'ohmylms-quiz-workspace';
		const attach = () => {
			const content = document.querySelector(
				'.interface-interface-skeleton__content'
			);
			if ( content && container.parentElement !== content ) {
				content.prepend( container );
				content.classList.add( 'ohmylms-quiz-workspace-layout' );
				setHost( container );
			}
		};
		const observer = new MutationObserver( attach );
		observer.observe( document.body, { childList: true, subtree: true } );
		attach();
		return () => {
			observer.disconnect();
			container.parentElement?.classList.remove(
				'ohmylms-quiz-workspace-layout'
			);
			container.remove();
		};
	}, [] );

	useEffect( () => {
		if (
			selectedId &&
			questions.some( ( question ) => question.clientId === selectedId )
		) {
			setActiveId( selectedId );
			setMode( ( current ) =>
				'document' === current ? current : 'question'
			);
		} else if (
			! questions.some( ( question ) => question.clientId === activeId )
		) {
			const next = questions[ 0 ]?.clientId || '';
			setActiveId( next );
			if ( next && 'question' === mode && ! paper ) {
				selectBlock( next );
			}
		}
	}, [ selectedId, questions, activeId, mode, paper, selectBlock ] );

	useEffect( () => {
		/** @type {Document|null} */
		let canvas = null;
		let cleanup = () => {};
		const attach = () => {
			const frame = /** @type {HTMLIFrameElement|null} */ (
				document.querySelector( 'iframe[name="editor-canvas"]' )
			);
			const next = frame ? frame.contentDocument : document;
			if (
				next?.querySelector( '.editor-styles-wrapper' ) &&
				next !== canvas
			) {
				cleanup();
				canvas = next;
				cleanup = focusQuizCanvas(
					next,
					paper ? 'document' : mode,
					activeId
				);
			}
		};
		// Core mounts the canvas asynchronously, including after iframe recovery.
		// Do not write into its empty document before core has initialized it.
		const timer = window.setInterval( attach, 500 );
		attach();
		return () => {
			window.clearInterval( timer );
			cleanup();
		};
	}, [ mode, activeId, paper ] );

	/** @param {string} id Question block to edit. */
	const choose = ( id ) => {
		setMode( 'question' );
		setActiveId( id );
		selectBlock( id );
	};
	/** @param {number} direction Move within the existing parent layout. */
	const move = ( direction ) => {
		const editor = select( 'core/block-editor' );
		const root = editor.getBlockRootClientId( activeId ) || '';
		const position = editor.getBlockIndex( activeId );
		const target = position + direction;
		if (
			editor.canMoveBlocks( [ activeId ], root ) &&
			target >= 0 &&
			target < editor.getBlockOrder( root ).length
		) {
			moveBlockToPosition( activeId, root, root, target );
		}
	};
	return host
		? createPortal(
				<nav aria-label={ __( 'Quiz builder', 'ohmylms' ) }>
					<p className="ohmylms-quiz-workspace-eyebrow">
						{ __( 'Quiz builder', 'ohmylms' ) }
					</p>
					<h2>
						{ __( 'Questions', 'ohmylms' ) }{ ' ' }
						<span>{ questions.length }</span>
					</h2>
					<Button
						variant="secondary"
						aria-pressed={ 'introduction' === mode }
						onClick={ () => {
							clearSelectedBlock();
							setMode( 'introduction' );
						} }
					>
						{ __( 'Title and introduction', 'ohmylms' ) }
					</Button>
					<ol className="ohmylms-quiz-workspace-list">
						{ questions.map( ( question, index ) => (
							<li key={ question.clientId }>
								<Button
									variant={
										activeId === question.clientId &&
										'question' === mode
											? 'primary'
											: 'tertiary'
									}
									aria-current={
										activeId === question.clientId &&
										'question' === mode
											? 'step'
											: undefined
									}
									onClick={ () =>
										choose( question.clientId )
									}
								>
									<QuestionLabel
										clientId={ question.clientId }
										number={ index + 1 }
									/>
								</Button>
							</li>
						) ) }
					</ol>
					{ ! questions.length && (
						<p>
							{ __(
								'Add your first question to start building this quiz.',
								'ohmylms'
							) }
						</p>
					) }
					<div className="ohmylms-quiz-workspace-actions">
						<Button
							variant="primary"
							disabled={ ! canAdd }
							onClick={ () => {
								const block = createBlock(
									'ohmylms/shared-multiple-choice',
									{ delivery: 'quiz' }
								);
								insertBlock(
									block,
									select(
										'core/block-editor'
									).getBlockOrder().length
								);
								choose( block.clientId );
							} }
						>
							{ __( 'Add question', 'ohmylms' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ onBank }
							disabled={ ! canAdd }
						>
							{ __( 'Question bank', 'ohmylms' ) }
						</Button>
					</div>
					{ 'introduction' === mode && (
						<Button
							variant="secondary"
							onClick={ () =>
								insertBlock(
									createBlock( 'core/paragraph' ),
									0
								)
							}
						>
							{ __( 'Add introduction text', 'ohmylms' ) }
						</Button>
					) }
					{ activeId && 'question' === mode && (
						<div className="ohmylms-quiz-workspace-actions">
							<Button
								variant="tertiary"
								disabled={ ! canMove || moveIndex <= 0 }
								onClick={ () => move( -1 ) }
							>
								{ __( 'Move up', 'ohmylms' ) }
							</Button>
							<Button
								variant="tertiary"
								disabled={
									! canMove || moveIndex >= siblingCount - 1
								}
								onClick={ () => move( 1 ) }
							>
								{ __( 'Move down', 'ohmylms' ) }
							</Button>
						</div>
					) }
					<hr />
					<Button
						variant="secondary"
						onClick={ () =>
							openGeneralSidebar( 'edit-post/document' )
						}
					>
						{ __( 'Quiz settings', 'ohmylms' ) }
					</Button>
					<Button
						variant="tertiary"
						aria-pressed={ 'document' === mode }
						onClick={ () => setMode( 'document' ) }
					>
						{ __( 'Full document layout', 'ohmylms' ) }
					</Button>
					<p>
						{ __(
							'Select a question to edit its prompt, answers and marks. Preview to try the quiz as a learner.',
							'ohmylms'
						) }
					</p>
				</nav>,
				host
			)
		: null;
}
