/** @jsx createElement */
import {
	createElement,
	createPortal,
	useEffect,
	useState,
} from '@wordpress/element';
import { select, useDispatch, useSelect } from '@wordpress/data';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { BankPicker } from '../question-bank/BankPicker';
import { AssessmentSettingsPanel } from '../assessment/AssessmentSettingsPanel';
import { NativeExtensionPanels } from './NativeExtensionPanels';
import { NativeQuizConfiguration } from './NativeQuizConfiguration';
import './NativeExamPanel';
import { ProfilePanel } from './ProfilePanel';
import { ExportRowsButtons } from '../content-hub/ExportRowsButtons';
import { ImportDialog } from '../content-hub/ImportDialog';
import { ExamsPage } from '../content-hub/ExamsPage';
import { createBlock } from '@wordpress/blocks';
import { exportQuiz } from '../content-hub/api.mjs';
import { downloadText } from '../content-hub/quizExchange.mjs';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { saveQuizForPreview } from './quizPreview.mjs';
import { useBlankPlaceholderTitle } from './useBlankPlaceholderTitle';
import {
	PaperCanvas,
	PaperSettingsPanel,
	PaperViewToggle,
} from '../paper/PaperCanvas';

/**
 * Open a fresh player session after the native document and bank edits are saved.
 *
 * @return {Element} Preview control.
 */
function QuizPreviewButton() {
	const { locked, saving, dirty } = useSelect( ( getStore ) => {
		const editor = getStore( 'core/editor' );
		return {
			locked: editor.isPostSavingLocked(),
			saving: editor.isSavingPost(),
			dirty: editor.isEditedPostDirty(),
		};
	}, [] );
	const { savePost } = useDispatch( 'core/editor' );
	const [ opening, setOpening ] = useState( false );
	const [ error, setError ] = useState( '' );
	const blocked = locked || saving || opening;
	let label = __( 'Preview quiz', 'ohmylms' );
	if ( locked ) {
		label = __( 'Complete question to preview', 'ohmylms' );
	} else if ( opening || saving ) {
		label = __( 'Saving…', 'ohmylms' );
	}
	return (
		<>
			<Button
				variant="secondary"
				href={
					blocked ? undefined : window.ohmylmsNativeLesson.previewUrl
				}
				target="_blank"
				rel="noopener noreferrer"
				disabled={ blocked }
				title={
					locked
						? __(
								'Complete the question and its answer key before previewing.',
								'ohmylms'
							)
						: __(
								'Preview the latest saved quiz in a fresh player session',
								'ohmylms'
							)
				}
				onClick={ async ( event ) => {
					if ( ! dirty ) {
						return;
					}
					event.preventDefault();
					const preview = window.open( 'about:blank', '_blank' );
					if ( preview ) {
						preview.opener = null;
					}
					setOpening( true );
					setError( '' );
					try {
						const ready = await saveQuizForPreview( {
							editor: select( 'core/editor' ),
							savePost,
						} );
						if ( ! ready ) {
							throw new Error( 'quiz-preview-not-ready' );
						}
						if ( preview ) {
							preview.location.replace(
								window.ohmylmsNativeLesson.previewUrl
							);
						} else {
							window.location.assign(
								window.ohmylmsNativeLesson.previewUrl
							);
						}
					} catch {
						preview?.close();
						setError(
							__(
								'The quiz could not be saved for preview. Complete any unfinished questions and try again.',
								'ohmylms'
							)
						);
					} finally {
						setOpening( false );
					}
				} }
			>
				{ label }
			</Button>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
		</>
	);
}

/**
 * Keep the player preview visible when the document sidebar is closed.
 * Core has no public SlotFill for an arbitrary header action, so scope this
 * portal to its settings container; the sidebar link remains the fallback.
 *
 * @return {Element|null} Quiz preview action.
 */
function QuizPreviewAction() {
	const [ container, setContainer ] = useState( null );
	useEffect( () => {
		const host = document.createElement( 'span' );
		host.className = 'ohmylms-quiz-preview-action';
		const attach = () => {
			const header = document.querySelector(
				'.editor-header__settings, .edit-post-header__settings'
			);
			if ( header && host.parentElement !== header ) {
				header.prepend( host );
				setContainer( host );
			}
		};
		const observer = new MutationObserver( attach );
		observer.observe( document.body, { childList: true, subtree: true } );
		attach();
		return () => {
			observer.disconnect();
			host.remove();
		};
	}, [] );
	return container
		? createPortal(
				<>
					<PaperViewToggle />
					<QuizPreviewButton />
				</>,
				container
			)
		: null;
}

/**
 * Keep native Gutenberg controls and provide the existing quiz settings and player preview.
 * @return {Element|null} Native quiz sidebar.
 */
function NativeQuizSettings() {
	useBlankPlaceholderTitle();
	const { postType, postId } = useSelect(
		( getStore ) => ( {
			postType: getStore( 'core/editor' ).getCurrentPostType(),
			postId: getStore( 'core/editor' ).getCurrentPostId(),
		} ),
		[]
	);
	const { insertBlocks } = useDispatch( 'core/block-editor' );
	const { savePost } = useDispatch( 'core/editor' );
	const [ pickerOpen, setPickerOpen ] = useState( false );
	const [ importOpen, setImportOpen ] = useState( false );
	const [ importJob, setImportJob ] = useState( 0 );
	const [ error, setError ] = useState( '' );
	if ( postType !== 'ohmylms-quiz' ) {
		return null;
	}
	// Imported questions are appended as question blocks; saving the quiz links them, as with the bank picker.
	const addQuestions = ( ids ) =>
		insertBlocks(
			ids.map( ( questionId ) =>
				createBlock( 'ohmylms/shared-multiple-choice', {
					questionId,
					delivery: 'quiz',
				} )
			)
		);
	return (
		<>
			<QuizPreviewAction />
			<PaperCanvas />
			<ProfilePanel />
			<PluginDocumentSettingPanel
				name="ohmylms-native-quiz"
				title={ __( 'Quiz authoring', 'ohmylms' ) }
				initialOpen
			>
				<p>
					{ __(
						'Use the block inserter to add paragraphs, headings, images and OhMyLMS Question blocks. Question edits save automatically. Use Gutenberg’s Save button to save the quiz. Drag question blocks or use Gutenberg’s move controls to set their order.',
						'ohmylms'
					) }
				</p>
				<p>
					{ __(
						'Publishing uses the existing versioned quiz engine. Learner attempts and skill evidence stay with the quiz.',
						'ohmylms'
					) }
				</p>
				<QuizPreviewButton />
				<Button
					variant="secondary"
					onClick={ () => setPickerOpen( true ) }
				>
					{ __( 'Add from question bank', 'ohmylms' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ () => setImportOpen( true ) }
				>
					{ __( 'Import questions', 'ohmylms' ) }
				</Button>
				<Button
					variant="tertiary"
					onClick={ async () => {
						try {
							if (
								! ( await saveQuizForPreview( {
									editor: select( 'core/editor' ),
									savePost,
								} ) )
							) {
								throw new Error(
									__(
										'Save the quiz before exporting.',
										'ohmylms'
									)
								);
							}
							const file = await exportQuiz( postId );
							downloadText(
								file.filename,
								file.mime,
								file.content
							);
						} catch ( cause ) {
							setError( cause.message );
						}
					} }
				>
					{ __( 'Export quiz', 'ohmylms' ) }
				</Button>
				<ExportRowsButtons
					quizId={ postId }
					beforeExport={ async () => {
						if (
							! ( await saveQuizForPreview( {
								editor: select( 'core/editor' ),
								savePost,
							} ) )
						) {
							throw new Error(
								__(
									'Save the quiz before exporting.',
									'ohmylms'
								)
							);
						}
					} }
				/>
				{ error && (
					<Notice status="error" onRemove={ () => setError( '' ) }>
						{ error }
					</Notice>
				) }
				<p>
					<Button
						variant="link"
						href={ window.ohmylmsNativeLesson.returnUrl }
					>
						{ __( 'Back to quizzes', 'ohmylms' ) }
					</Button>
				</p>
			</PluginDocumentSettingPanel>
			<PluginDocumentSettingPanel
				name="ohmylms-native-quiz-settings"
				title={ __( 'Quiz settings', 'ohmylms' ) }
				initialOpen={ false }
			>
				<NativeQuizConfiguration />
			</PluginDocumentSettingPanel>
			<PluginDocumentSettingPanel
				name="ohmylms-native-paper"
				title={ __( 'Printable paper', 'ohmylms' ) }
				initialOpen={ false }
			>
				<PaperSettingsPanel />
			</PluginDocumentSettingPanel>
			<PluginDocumentSettingPanel
				name="ohmylms-native-assessment"
				title={ __( 'Assessment and pools', 'ohmylms' ) }
				initialOpen={ false }
			>
				<AssessmentSettingsPanel quizId={ postId } native />
			</PluginDocumentSettingPanel>
			<NativeExtensionPanels quizId={ postId } />
			{ pickerOpen && (
				<BankPicker
					quizId={ postId }
					existingIds={ select( 'core/block-editor' )
						.getBlocks()
						.flatMap( function collect( block ) {
							return block.attributes?.questionId
								? [ block.attributes.questionId ]
								: ( block.innerBlocks || [] ).flatMap(
										collect
									);
						} ) }
					onClose={ () => setPickerOpen( false ) }
					onPick={ addQuestions }
				/>
			) }
			{ importOpen && (
				<ImportDialog
					withSkills
					secure={
						window.ohmylmsNativeLesson?.profile?.id === 'exam'
					}
					onQuestions={ addQuestions }
					onClose={ () => setImportOpen( false ) }
					onJob={ ( id ) => {
						setImportOpen( false );
						setImportJob( id );
					} }
				/>
			) }
			{ importJob > 0 && (
				<ExamsPage
					reviewOnly
					jobId={ importJob }
					onQuestions={ addQuestions }
					onClosed={ () => setImportJob( 0 ) }
				/>
			) }
		</>
	);
}

registerPlugin( 'ohmylms-native-quiz-settings', {
	render: NativeQuizSettings,
} );
