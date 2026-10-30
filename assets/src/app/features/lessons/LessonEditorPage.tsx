import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { useRequest } from '../../hooks/useRequest';
import { useOperation } from '../../hooks/useOperation';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import { readLesson, saveLesson, publishLesson } from './api';
import type { Lesson } from './model';
import LessonBlocks from './LessonBlocks';
import {
	LessonExtensionEditor,
	useLessonEditor,
} from './LessonExtensionEditor';
import {
	DripSettings,
	MediaSettings,
	PrerequisiteSettings,
} from './LessonSettings';
import '../../styles/lessons.css';

const BUILT_IN = [ 'text', 'video', 'audio' ];

export default function LessonEditorPage() {
	const id = Number( useParams().id );
	return Number.isSafeInteger( id ) && id > 0 ? (
		<LoadedLesson key={ id } id={ id } />
	) : (
		<p role="alert">{ __( 'Invalid lesson ID.', 'ohmylms' ) }</p>
	);
}
function LoadedLesson( { id }: { id: number } ) {
	const load = useCallback(
		( signal: AbortSignal ) => readLesson( id, signal ),
		[ id ]
	);
	const request = useRequest( load );
	return (
		<>
			<h1>{ __( 'Lesson editor', 'ohmylms' ) }</h1>
			{ request.loading && (
				<p role="status">{ __( 'Loading lesson…', 'ohmylms' ) }</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
					<Button onClick={ request.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ request.data && (
				<LessonWorkspace
					key={ `${ id }:${ request.data.revision }` }
					initial={ request.data }
					reload={ request.retry }
				/>
			) }
		</>
	);
}

export function LessonWorkspace( {
	initial,
	reload,
}: {
	initial: Lesson;
	reload: () => void;
} ) {
	const [ draft, setDraft ] = useState( initial );
	const [ saved, setSaved ] = useState( JSON.stringify( initial ) );
	const [ message, setMessage ] = useState( '' );
	const [ source, setSource ] = useState( false );
	const [ canvasVersion, setCanvasVersion ] = useState( 0 );
	const [ confirmReload, setConfirmReload ] = useState( false );
	const [ confirmPublish, setConfirmPublish ] = useState( false );
	const operation = useOperation();
	const dirty = JSON.stringify( draft ) !== saved;
	const guard = useUnsavedChangesGuard( dirty );
	const custom = initial.custom_type;
	const extension = useLessonEditor( initial.type );
	const editable =
		( BUILT_IN.includes( initial.type ) || custom ) &&
		[ 'draft', 'pending', 'private', 'publish' ].includes( initial.status );
	function change( patch: Partial< Lesson > ) {
		setDraft( ( current ) => ( { ...current, ...patch } ) );
		setMessage( '' );
	}
	function accept( result: Lesson ) {
		setDraft( result );
		setSaved( JSON.stringify( result ) );
		setCanvasVersion( ( value ) => value + 1 );
	}
	return (
		<section aria-label={ __( 'Lesson content editor', 'ohmylms' ) }>
			<p>
				<Link to="/content-hub/lessons">
					{ __( 'Back to lessons', 'ohmylms' ) }
				</Link>{ ' ' }
				· <Link to="/courses">{ __( 'Courses', 'ohmylms' ) }</Link>{ ' ' }
				{ draft.native_editor_url && (
					<>
						{ ' ' }
						·{ ' ' }
						<a href={ draft.native_editor_url }>
							{ __( 'Open full WordPress editor', 'ohmylms' ) }
						</a>
					</>
				) }
			</p>
			<p>
				{ __(
					'Lessons are shared across course units. Saving a published lesson updates its content immediately everywhere it is used. Course placement and program publication are managed in the course editor.',
					'ohmylms'
				) }
			</p>
			{ ! editable && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Use the full WordPress or extension editor for this lesson type or status.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ editable && custom && ! extension && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'No editor is registered for this lesson type on this screen. Its name and source can still be edited here.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ message && (
				<Notice status="success" isDismissible={ false }>
					{ message }
				</Notice>
			) }
			<p role="status">
				{ dirty
					? __( 'Unsaved changes', 'ohmylms' )
					: __( 'Saved lesson', 'ohmylms' ) }{ ' ' }
				— { draft.status }
			</p>
			<Button
				variant="primary"
				disabled={
					! editable ||
					operation.busy ||
					! dirty ||
					! draft.title.trim()
				}
				isBusy={ operation.busy }
				onClick={ () =>
					void operation.run(
						( signal ) => saveLesson( draft, signal ),
						( result ) => {
							accept( result );
							setMessage(
								__(
									'Lesson saved. Publication status is unchanged.',
									'ohmylms'
								)
							);
						}
					)
				}
			>
				{ __( 'Save lesson', 'ohmylms' ) }
			</Button>
			<Button
				disabled={ operation.busy }
				onClick={ () =>
					dirty ? setConfirmReload( true ) : reload()
				}
			>
				{ __( 'Reload saved lesson', 'ohmylms' ) }
			</Button>
			{ draft.status !== 'publish' && (
				<Button
					disabled={ ! editable || dirty || operation.busy }
					onClick={ () => setConfirmPublish( true ) }
				>
					{ __( 'Review lesson publication', 'ohmylms' ) }
				</Button>
			) }
			<fieldset disabled={ ! editable || operation.busy }>
				<legend>{ __( 'Lesson details', 'ohmylms' ) }</legend>
				{ custom && extension ? (
					<LessonExtensionEditor
						lesson={ draft }
						onChange={ change }
					/>
				) : (
					<TextControl
						label={ __( 'Lesson title', 'ohmylms' ) }
						value={ draft.title }
						maxLength={ 190 }
						onChange={ ( title ) => change( { title } ) }
					/>
				) }
				{ ! custom && (
					<SelectControl< string >
						label={ __( 'Lesson format', 'ohmylms' ) }
						value={ draft.type }
						options={ [
							{
								value: 'text',
								label: __( 'Reading', 'ohmylms' ),
							},
							{ value: 'video', label: __( 'Video', 'ohmylms' ) },
							{ value: 'audio', label: __( 'Audio', 'ohmylms' ) },
						] }
						onChange={ ( type ) => change( { type } ) }
					/>
				) }
				{ ( draft.type === 'video' || draft.type === 'audio' ) && (
					<TextControl
						label={ __( 'External media URL', 'ohmylms' ) }
						value={ draft.external_url }
						type="url"
						onChange={ ( value ) =>
							change( {
								external_url: value,
								...( value
									? {
											video_id: 0,
											audio_id: 0,
											media_urls: {
												...draft.media_urls,
												video_id: '',
												audio_id: '',
											},
										}
									: {} ),
							} )
						}
						help={ __(
							'Use HTTP or HTTPS, or choose an uploaded file under Lesson media. Entering a URL replaces an uploaded video or audio file.',
							'ohmylms'
						) }
					/>
				) }
				<MediaSettings lesson={ draft } onChange={ change } />
				<CheckboxControl
					label={ __(
						'Allow lesson preview without enrollment',
						'ohmylms'
					) }
					checked={ draft.preview_enable }
					onChange={ ( value ) =>
						change( { preview_enable: value } )
					}
				/>
				<CheckboxControl
					label={ __( 'Enable lesson comments', 'ohmylms' ) }
					checked={ draft.enable_comments }
					onChange={ ( value ) =>
						change( { enable_comments: value } )
					}
				/>
				<DripSettings
					drip={ draft.drip }
					onChange={ ( drip ) => change( { drip } ) }
				/>
				<PrerequisiteSettings
					lesson={ draft }
					onChange={ ( prerequisites ) =>
						change( { prerequisites } )
					}
				/>
				{ custom && ! extension && (
					<TextareaControl
						label={ __( 'Lesson source', 'ohmylms' ) }
						value={ draft.content }
						rows={ 10 }
						onChange={ ( content ) => change( { content } ) }
					/>
				) }
				{ ! custom && (
					<>
						<h2>{ __( 'Lesson content', 'ohmylms' ) }</h2>
						<Button onClick={ () => setSource( ! source ) }>
							{ source
								? __( 'Use visual block editor', 'ohmylms' )
								: __( 'Edit block source', 'ohmylms' ) }
						</Button>
						<p>
							{ __(
								'Existing custom blocks are retained. Use the full WordPress editor for advanced and extension-specific block controls.',
								'ohmylms'
							) }
						</p>
						{ source ? (
							<TextareaControl
								label={ __(
									'Block content source',
									'ohmylms'
								) }
								value={ draft.content }
								rows={ 14 }
								onChange={ ( content ) =>
									change( { content } )
								}
							/>
						) : null }
						{ ! source && operation.busy && (
							<p role="status">
								{ __( 'Saving lesson…', 'ohmylms' ) }
							</p>
						) }
						{ ! source && ! operation.busy && editable && (
							<LessonBlocks
								key={ canvasVersion }
								initial={ draft.content }
								disabled={ false }
								onChange={ ( content ) =>
									change( { content } )
								}
							/>
						) }
					</>
				) }
				<h2>{ __( 'Download resources', 'ohmylms' ) }</h2>
				{ draft.resources.map( ( resource, index ) => (
					<fieldset key={ index }>
						<legend>
							{ __( 'Resource link', 'ohmylms' ) } { index + 1 }
						</legend>
						<TextControl
							label={ __( 'Resource name', 'ohmylms' ) }
							value={ resource.name }
							maxLength={ 200 }
							onChange={ ( name ) =>
								change( {
									resources: draft.resources.map(
										( row, rowIndex ) =>
											rowIndex === index
												? { ...row, name }
												: row
									),
								} )
							}
						/>
						<TextControl
							label={ __( 'Resource URL', 'ohmylms' ) }
							value={ resource.url }
							type="url"
							onChange={ ( url ) =>
								change( {
									resources: draft.resources.map(
										( row, rowIndex ) =>
											rowIndex === index
												? { ...row, url }
												: row
									),
								} )
							}
						/>
						<TextControl
							label={ __( 'Resource size label', 'ohmylms' ) }
							value={ resource.size }
							maxLength={ 50 }
							onChange={ ( size ) =>
								change( {
									resources: draft.resources.map(
										( row, rowIndex ) =>
											rowIndex === index
												? { ...row, size }
												: row
									),
								} )
							}
						/>
						<Button
							isDestructive
							onClick={ () =>
								change( {
									resources: draft.resources.filter(
										( _, rowIndex ) => rowIndex !== index
									),
								} )
							}
						>
							{ __( 'Remove resource link', 'ohmylms' ) }
						</Button>
					</fieldset>
				) ) }
				<p>
					{ __(
						'Removing a link does not delete its file.',
						'ohmylms'
					) }
				</p>
				<Button
					disabled={ draft.resources.length >= 50 }
					onClick={ () =>
						change( {
							resources: [
								...draft.resources,
								{ name: '', url: '', size: '' },
							],
						} )
					}
				>
					{ __( 'Add resource link', 'ohmylms' ) }
				</Button>
			</fieldset>
			{ confirmReload && (
				<Modal
					title={ __( 'Discard unsaved lesson changes?', 'ohmylms' ) }
					onRequestClose={ () => setConfirmReload( false ) }
				>
					<Button onClick={ reload }>
						{ __( 'Discard and reload', 'ohmylms' ) }
					</Button>
					<Button onClick={ () => setConfirmReload( false ) }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ guard.pendingPath && (
				<Modal
					title={ __( 'Discard unsaved lesson changes?', 'ohmylms' ) }
					onRequestClose={ guard.stay }
				>
					<Button onClick={ guard.leave }>
						{ __( 'Discard and leave', 'ohmylms' ) }
					</Button>
					<Button onClick={ guard.stay }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ confirmPublish && (
				<Modal
					title={ __( 'Publish this lesson?', 'ohmylms' ) }
					onRequestClose={ () => {
						if ( ! operation.busy ) {
							setConfirmPublish( false );
						}
					} }
				>
					<p>{ draft.title }</p>
					<p>
						{ __(
							'This publishes the saved lesson for every course that uses it. Course learning programs still require their own publication.',
							'ohmylms'
						) }
					</p>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<Button
						disabled={ operation.busy }
						onClick={ () => setConfirmPublish( false ) }
					>
						{ __( 'Keep draft', 'ohmylms' ) }
					</Button>
					<Button
						variant="primary"
						disabled={ operation.busy || dirty }
						isBusy={ operation.busy }
						onClick={ () =>
							void operation.run(
								( signal ) => publishLesson( draft, signal ),
								( result ) => {
									accept( result );
									setConfirmPublish( false );
									setMessage(
										__( 'Lesson published.', 'ohmylms' )
									);
								}
							)
						}
					>
						{ __( 'Confirm lesson publication', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</section>
	);
}
