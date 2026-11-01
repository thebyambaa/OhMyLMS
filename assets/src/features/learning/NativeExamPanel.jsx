import { createElement, useState } from '@wordpress/element';
import { select, useDispatch, useSelect } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { Button, Notice, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { publishExam, saveExamInstructions } from './api.mjs';
import { registerProfilePanel } from './ProfilePanel';

/**
 * Exam record the exam profile passes to its panel.
 *
 * @typedef {Object} ExamProfileData
 * @property {number} id             Exam ID.
 * @property {string} [status]       Post status.
 * @property {string} [url]          Exam player URL.
 * @property {string} [instructions] Learner instructions.
 */

/**
 * Exam controls in the quiz editor: learner instructions and publication. The questions, sections,
 * time limit and marks are the quiz's own, so they are edited with the quiz tools beside this panel.
 *
 * @param {Object}                         root0      Component properties.
 * @param {ExamProfileData|null|undefined} root0.data Exam id, status, player URL and instructions from the exam profile.
 * @return {React.ReactElement|null} Panel, or nothing when this quiz has no exam record.
 */
export function NativeExamPanel( { data: exam } ) {
	const [ instructions, setInstructions ] = useState(
		exam?.instructions || ''
	);
	const [ status, setStatus ] = useState( exam?.status || 'draft' );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ warnings, setWarnings ] = useState(
		/** @type {string[]} */ ( [] )
	);
	const { dirty, locked } = useSelect( ( getStore ) => {
		const editor = getStore( 'core/editor' );
		return {
			dirty: editor.isEditedPostDirty(),
			locked: editor.isPostSavingLocked(),
		};
	}, [] );
	const { savePost } = useDispatch( 'core/editor' );
	if ( ! exam?.id ) {
		return null;
	}
	const published = status === 'publish';
	/**
	 * @param {() => Promise<unknown>} action Work; `false` means it stopped without a result.
	 * @param {string}                 done   Notice after it succeeds.
	 */
	const run = async ( action, done ) => {
		setBusy( true );
		setError( '' );
		setNotice( '' );
		try {
			if ( ( await action() ) !== false ) {
				setNotice( done );
			}
		} catch ( failure ) {
			setError(
				/** @type {{ message?: string }} */ ( failure ).message ||
					__(
						'The operation failed. Nothing was published.',
						'ohmylms'
					)
			);
		} finally {
			setBusy( false );
		}
	};
	const saveAll = async () => {
		if ( select( 'core/editor' ).isEditedPostDirty() ) {
			await savePost();
			if ( select( 'core/editor' ).didPostSaveRequestFail() ) {
				throw new Error(
					__(
						'The quiz could not be saved. Fix the highlighted question and try again.',
						'ohmylms'
					)
				);
			}
		}
		await saveExamInstructions( exam.id, instructions );
	};
	// Publishing makes the exam's questions exam-only, so practice sets and inline checks that use them
	// stop working. The server says so first; confirming sends the publish again.
	/** @param {boolean} confirm Accept that practice uses stop working. */
	const publish = ( confirm ) =>
		run(
			async () => {
				await saveAll();
				try {
					await publishExam( exam.id, confirm );
				} catch ( failure ) {
					const restError =
						/** @type {{ code?: string, message: string, data?: { warnings?: string[] } }} */ (
							failure
						);
					if ( restError.code === 'exam_overlap' ) {
						setWarnings(
							restError.data?.warnings || [ restError.message ]
						);
						return false;
					}
					throw failure;
				}
				setWarnings( [] );
				setStatus( 'publish' );
				return true;
			},
			__( 'Exam published.', 'ohmylms' )
		);
	return (
		<PluginDocumentSettingPanel
			name="ohmylms-native-exam"
			title={ __( 'Exam publication', 'ohmylms' ) }
			initialOpen
		>
			<p>
				{ published
					? __(
							'This exam is published. Its questions are frozen for learners.',
							'ohmylms'
						)
					: __(
							'Write questions in the shared editor and configure duration, results and sections under Exam settings. Save your changes, then publish the exam below.',
							'ohmylms'
						) }
			</p>
			<TextareaControl
				label={ __( 'Instructions for learners', 'ohmylms' ) }
				value={ instructions }
				rows={ 4 }
				disabled={ published || busy }
				onChange={ setInstructions }
			/>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ warnings.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					<ul>
						{ warnings.map( ( warning ) => (
							<li key={ warning }>{ warning }</li>
						) ) }
					</ul>
					<Button
						variant="primary"
						disabled={ busy }
						onClick={ () => publish( true ) }
					>
						{ __( 'Publish anyway', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						onClick={ () => setWarnings( [] ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ notice && (
				<Notice status="success" onRemove={ () => setNotice( '' ) }>
					{ notice }
				</Notice>
			) }
			{ ! published && (
				<>
					<Button
						variant="secondary"
						disabled={ busy || locked }
						onClick={ () =>
							run( saveAll, __( 'Exam draft saved.', 'ohmylms' ) )
						}
					>
						{ dirty
							? __( 'Save exam draft', 'ohmylms' )
							: __( 'Save instructions', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="primary"
						disabled={ busy || locked }
						onClick={ () => publish( false ) }
					>
						{ __( 'Publish exam', 'ohmylms' ) }
					</Button>
				</>
			) }
			{ published && (
				<p>
					<Button variant="link" href={ exam.url } target="_blank">
						{ __( 'Open exam player', 'ohmylms' ) }
					</Button>
				</p>
			) }
		</PluginDocumentSettingPanel>
	);
}

registerProfilePanel( 'exam', NativeExamPanel );
