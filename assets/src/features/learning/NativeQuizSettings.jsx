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
import {
	NativeAssessmentType,
	useNativeAssessmentSettings,
} from './NativeAssessmentSettings';
import {
	AssessmentPreviewProvider,
	AssessmentPreviewButton as QuizPreviewButton,
} from './AssessmentPreview';
import './NativeExamPanel';
import { PaperInserter } from '../paper/PaperInserter';
import { ProfilePanel } from './ProfilePanel';
import { ExportRowsButtons } from '../content-hub/ExportRowsButtons';
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
	const [ error, setError ] = useState( '' );
	const { settings: assessment } = useNativeAssessmentSettings(
		postType === 'ohmylms-quiz'
	);
	const exam = assessment?.kind === 'exam';
	if ( postType !== 'ohmylms-quiz' ) {
		return null;
	}
	return (
		<AssessmentPreviewProvider>
			<QuizPreviewAction />
			<PaperCanvas />
			<ProfilePanel />
			<PluginDocumentSettingPanel
				name="ohmylms-native-quiz"
				title={ __( 'Quiz and exam', 'ohmylms' ) }
				initialOpen
			>
				<NativeAssessmentType />
				<PaperInserter />
				<p>
					{ __(
						'Add questions, arrange sections and use Student preview to try the assessment. Save applies your questions and assessment settings together.',
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
						{ __( 'Back to assessments', 'ohmylms' ) }
					</Button>
				</p>
			</PluginDocumentSettingPanel>
			<PluginDocumentSettingPanel
				name="ohmylms-native-quiz-settings"
				title={
					exam
						? __( 'Exam settings', 'ohmylms' )
						: __( 'Quiz settings', 'ohmylms' )
				}
				initialOpen
			>
				<NativeQuizConfiguration exam={ exam } />
				<AssessmentSettingsPanel quizId={ postId } native />
			</PluginDocumentSettingPanel>
			<PluginDocumentSettingPanel
				name="ohmylms-native-paper"
				title={ __( 'Printable paper', 'ohmylms' ) }
				initialOpen={ false }
			>
				<PaperSettingsPanel />
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
					onPick={ ( ids ) =>
						insertBlocks(
							ids.map( ( questionId ) =>
								createBlock( 'ohmylms/shared-multiple-choice', {
									questionId,
									delivery: 'quiz',
								} )
							)
						)
					}
				/>
			) }
		</AssessmentPreviewProvider>
	);
}

registerPlugin( 'ohmylms-native-quiz-settings', {
	render: NativeQuizSettings,
} );
