/** @jsx createElement */
import {
	createElement,
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Dashicon,
	Modal,
	Notice,
	Spinner,
} from '@wordpress/components';
import * as api from './api.mjs';
import { categoryAppearance } from './categoryAppearance.mjs';
import { WorkspaceContext } from './context';
import { ImportDialog } from './ImportDialog';
import { SkillPane } from './SkillPane';
import { ChapterPane } from './ChapterPane';
import { TopicPane } from './TopicPane';
import { outlineCsv, templateCsv } from './csv.mjs';
import { download } from './download.mjs';
import { fileName, totalsParts } from './syllabus.mjs';
import { RowMenu } from './WorkspaceParts';
import { WorkspaceSidebar } from './WorkspaceSidebar';
import { createActions } from './workspaceActions';
import {
	CURRICULUM_PATH,
	ancestorKeys,
	defaultKey,
	displayCounts,
	resolveKey,
	syllabusIdFromHash,
} from './workspace.mjs';
import { useCourseCatalog } from './useCourseCatalog';
import { useSyllabusWorkspace } from './useSyllabusWorkspace';
import { hashQuery } from '../content-hub/hubRoutes.mjs';
import { SyllabusSpreadsheet } from './SyllabusSpreadsheet';
import { SyllabusCourseDialog } from '../courses/SyllabusCourseDialog';

function countsLine( totals ) {
	const { contents, skills } = totalsParts( totals );
	return [
		sprintf(
			/* translators: %d: number of folders in the syllabus. */
			_n( '%d folder', '%d folders', contents, 'ohmylms' ),
			contents
		),
		sprintf(
			/* translators: %d: number of skills in the syllabus. */
			_n( '%d skill', '%d skills', skills, 'ohmylms' ),
			skills
		),
	].join( ' · ' );
}

/**
 * Full-page syllabus editor with nested topics and skills.
 * @param root0
 * @param root0.syllabusId
 */
export function SyllabusWorkspace( { syllabusId } ) {
	const ws = useSyllabusWorkspace( syllabusId );
	const { outline, tree } = ws;
	const course = outline?.course ?? null;
	const courseCatalog = useCourseCatalog( course?.id || 0, ws.revision );
	const [ skillLanguage, setSkillLanguage ] = useState( 'original' );
	const [ selected, setSelected ] = useState( '' );
	const [ open, setOpen ] = useState( () => new Set() );
	const [ collapsed, setCollapsed ] = useState( false );
	const [ importing, setImporting ] = useState( false );
	const [ sheetOpen, setSheetOpen ] = useState( false );
	const [ creatingCourse, setCreatingCourse ] = useState( false );
	const [ sheetState, setSheetState ] = useState( {
		dirty: false,
		busy: false,
	} );
	const onSheetState = useCallback(
		( dirty, busy ) => setSheetState( { dirty, busy } ),
		[]
	);
	const [ sourceState, setSourceState ] = useState( {
		dirty: false,
		busy: false,
	} );
	const onSourceState = useCallback(
		( dirty, busy ) => setSourceState( { dirty, busy } ),
		[]
	);
	const viewLocked =
		ws.pending ||
		sourceState.dirty ||
		sourceState.busy ||
		sheetState.dirty ||
		sheetState.busy;
	const remembered = useRef( '' );
	const started = useRef( false );
	const main = useRef( null );

	// Open the syllabus home unless returning to a specific topic or skill.
	useEffect( () => {
		if ( ! tree.root || started.current ) {
			return;
		}
		started.current = true;
		setSelected(
			resolveKey(
				tree,
				hashQuery( window.location.hash ).get( 'node' ) ||
					defaultKey( tree )
			)
		);
	}, [ tree ] );

	const resolved = tree.root
		? resolveKey( tree, selected, remembered.current )
		: '';
	const active = resolved;
	useEffect( () => {
		// A node that is deleted falls back to the one it sat in.
		remembered.current =
			tree.index.get( active )?.parentKey || remembered.current;
		// Everything on the way to the selection is open, so it can be seen in the outline.
		setOpen( ( previous ) => {
			const needed = [
				tree.root?.key,
				...ancestorKeys( tree.index, active ),
			].filter( Boolean );
			return needed.every( ( key ) => previous.has( key ) )
				? previous
				: new Set( [ ...previous, ...needed ] );
		} );
	}, [ tree, active ] );
	useEffect( () => {
		main.current?.scrollTo?.( { top: 0 } );
		// Choosing a node opens it, so what is inside it shows in the outline. Closing it afterwards sticks.
		if ( active ) {
			setOpen( ( previous ) =>
				previous.has( active )
					? previous
					: new Set( [ ...previous, active ] )
			);
		}
	}, [ active ] );

	const select = useCallback(
		( key ) => {
			if ( sourceState.dirty || sourceState.busy ) {
				return;
			}
			setSelected( key );
		},
		[ sourceState.dirty, sourceState.busy ]
	);
	const toggle = useCallback(
		( key ) =>
			setOpen( ( previous ) => {
				const next = new Set( previous );
				if ( next.has( key ) ) {
					next.delete( key );
				} else {
					next.add( key );
				}
				return next;
			} ),
		[]
	);
	const actions = useMemo(
		() =>
			createActions( { ws, syllabusId, tree, items: ws.items, select } ),
		[ ws, syllabusId, tree, select ]
	);

	if ( ws.error ) {
		return (
			<div className="ohmylms-ws ohmylms-ws-fallback">
				<Notice status="error" isDismissible={ false }>
					{ ws.error }
				</Notice>
				<p>
					<Button variant="secondary" onClick={ ws.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button variant="link" href={ `#${ CURRICULUM_PATH }` }>
						{ __( 'Back to the curriculum', 'ohmylms' ) }
					</Button>
				</p>
			</div>
		);
	}
	if ( ! outline || ! tree.root ) {
		return (
			<div className="ohmylms-ws ohmylms-ws-fallback" role="status">
				<Spinner /> { __( 'Opening the syllabus…', 'ohmylms' ) }
			</div>
		);
	}

	const node = tree.index.get( active ) || tree.root;
	const name = outline.syllabus.name;
	const shownCounts = displayCounts( tree.root, outline.settings );
	const value = {
		skillLanguage,
		setSkillLanguage,
		syllabusId,
		outline,
		items: ws.items,
		tree,
		selected: active,
		select,
		pending: ws.pending,
		notice: ws.notice,
		actions,
		course,
		courseCatalog,
		reload: ws.reload,
		revision: ws.revision,
		say: ws.say,
		onSourceState,
		saveSettings: ( data ) =>
			ws.run(
				() => api.saveSyllabusSettings( syllabusId, data ),
				__( 'Syllabus profile saved.', 'ohmylms' )
			),
		publishSyllabus: () =>
			ws.run(
				() => api.publishSyllabus( syllabusId ),
				__( 'Syllabus published.', 'ohmylms' )
			),
		updateCourse: () =>
			ws.run(
				() => api.ensureCourse( syllabusId ),
				__( 'The course now matches the syllabus.', 'ohmylms' )
			),
	};
	return (
		<WorkspaceContext.Provider value={ value }>
			<div className="ohmylms-ws ohmylms-unified-workspace">
				<header className="ohmylms-ws-header">
					<a
						className="ohmylms-ws-back"
						aria-disabled={ viewLocked }
						onClick={ ( event ) => {
							if ( viewLocked ) {
								event.preventDefault();
							}
						} }
						href={ `#${ CURRICULUM_PATH }` }
					>
						<Dashicon icon="arrow-left-alt" />
						{ __( 'Back', 'ohmylms' ) }
					</a>
					<div className="ohmylms-ws-header-title">
						<h1>
							<a
								className="ohmylms-ws-home-link"
								href={ `#/content-hub/curriculum/syllabus/${ syllabusId }` }
								onClick={ () => {
									if ( ! viewLocked ) {
										select( tree.root.key );
										setSheetOpen( false );
									}
								} }
								aria-label={ sprintf(
									/* translators: %s: syllabus name. */
									__( 'Open %s syllabus home', 'ohmylms' ),
									name
								) }
							>
								{ name }
							</a>
						</h1>
						<p>
							{ outline.syllabus.item_type === 'roadmap'
								? __( 'Roadmap', 'ohmylms' )
								: __( 'Syllabus', 'ohmylms' ) }{ ' ' }
							·{ ' ' }
							{ countsLine( {
								contents:
									shownCounts.topics + shownCounts.chapters,
								skills: shownCounts.skills,
							} ) }
							{ course &&
								` · ${ course.status === 'publish' ? __( 'Syllabus published', 'ohmylms' ) : __( 'Syllabus draft', 'ohmylms' ) }` }
						</p>
					</div>
					<div className="ohmylms-ws-header-actions">
						<Button
							variant="secondary"
							disabled={ viewLocked }
							onClick={ () => setCreatingCourse( true ) }
						>
							{ __( 'Create course from syllabus', 'ohmylms' ) }
						</Button>
						<Button
							variant="secondary"
							disabled={ viewLocked }
							onClick={ () => setSheetOpen( true ) }
						>
							{ __( 'Bulk edit', 'ohmylms' ) }
						</Button>
						<Button
							variant="secondary"
							disabled={ viewLocked }
							onClick={ () => setImporting( true ) }
						>
							{ __( 'Import CSV', 'ohmylms' ) }
						</Button>
						<RowMenu
							label={ __( 'More syllabus actions', 'ohmylms' ) }
							controls={ [
								{
									title: __( 'Export CSV', 'ohmylms' ),
									isDisabled: outline.totals.groups === 0,
									onClick: () =>
										download(
											outlineCsv( outline ),
											fileName( name, 'export' )
										),
								},
								{
									title: __(
										'Download CSV template',
										'ohmylms'
									),
									onClick: () =>
										download(
											templateCsv(),
											fileName( name, 'template' )
										),
								},
								{
									title: __(
										'Refresh skill collection',
										'ohmylms'
									),
									isDisabled: viewLocked,
									onClick: value.updateCourse,
								},
							] }
						/>
						<Button
							variant="primary"
							href={ `#${ CURRICULUM_PATH }` }
							disabled={ viewLocked }
						>
							{ __( 'Done', 'ohmylms' ) }
						</Button>
					</div>
				</header>
				<div className="ohmylms-ws-body">
					<WorkspaceSidebar
						language={ skillLanguage }
						tree={ tree }
						selected={ active }
						open={ open }
						onSelect={ select }
						onToggle={ toggle }
						onOpenAll={ () =>
							setOpen(
								new Set(
									[ ...tree.index.values() ]
										.filter(
											( entry ) => entry.children.length
										)
										.map( ( entry ) => entry.key )
								)
							)
						}
						onCloseAll={ () =>
							setOpen( new Set( [ tree.root.key ] ) )
						}
						onAddSkill={ ( itemId, data ) => {
							if ( node.kind === 'group' ) {
								return actions.addSkill( node.id, data );
							}
							if ( node.kind === 'skill' ) {
								return actions.addSkill( node.groupId, data );
							}
							return actions.addSkillInTopic( itemId, data );
						} }
						onAddTopic={ ( itemId, data ) =>
							actions.addTopic( itemId, data, {
								open: false,
							} )
						}
						onReorder={ actions.reorderOutline }
						onMoveSelected={ async ( keys, destination ) => {
							const moved = [];
							for ( const key of keys ) {
								const entry = tree.index.get( key );
								if ( ! entry || entry.kind !== 'skill' ) {
									continue;
								}
								if ( entry.groupId !== destination ) {
									const result = await ws.run( async () => {
										const inherited = categoryAppearance(
											entry.skill,
											outline.settings,
											tree.index.get( entry.parentKey )
												?.group
										).category;
										if (
											! entry.skill.category &&
											inherited
										) {
											await api.updateSkill(
												syllabusId,
												entry.id,
												{ category: inherited }
											);
										}
										return api.moveSkill(
											syllabusId,
											entry.groupId,
											entry.id,
											destination,
											999999
										);
									} );
									if ( ! result ) {
										break;
									}
								}
								moved.push( key );
							}
							return moved;
						} }
						settings={ outline.settings }
						collapsed={ collapsed }
						onCollapse={ () =>
							setCollapsed( ( state ) => ! state )
						}
						pending={ viewLocked }
					/>
					<main className="ohmylms-ws-main" ref={ main }>
						<div className="ohmylms-ws-status" aria-live="polite">
							{ ws.notice && (
								<Notice
									key={ ws.notice.id }
									status={ ws.notice.kind }
									onRemove={ ws.dismiss }
								>
									{ ws.notice.text }
								</Notice>
							) }
						</div>
						{ node.kind === 'content' && (
							<TopicPane key={ node.key } node={ node } />
						) }
						{ node.kind === 'group' && (
							<ChapterPane key={ node.key } node={ node } />
						) }
						{ node.kind === 'skill' && (
							<SkillPane key={ node.key } node={ node } />
						) }
					</main>
				</div>
				{ creatingCourse && (
					<SyllabusCourseDialog
						syllabusId={ Number( syllabusId ) }
						onClose={ () => setCreatingCourse( false ) }
						onCreated={ ( made ) => {
							window.location.hash = `/course-edit/${ made.id }`;
						} }
					/>
				) }
				{ importing && (
					<ImportDialog
						syllabus={ outline.syllabus }
						onClose={ () => setImporting( false ) }
						onImported={ ( response ) => {
							ws.adopt( response );
							ws.say(
								'success',
								__( 'Syllabus imported.', 'ohmylms' )
							);
						} }
					/>
				) }
				{ sheetOpen && (
					<Modal
						title={ __( 'Bulk edit skills', 'ohmylms' ) }
						isFullScreen
						shouldCloseOnClickOutside={ false }
						onRequestClose={ () => {
							if ( ! sheetState.dirty && ! sheetState.busy ) {
								setSheetOpen( false );
							}
						} }
					>
						<p>
							{ __(
								'Save or discard changes before returning to the workspace.',
								'ohmylms'
							) }
						</p>
						<SyllabusSpreadsheet
							outline={ outline }
							onSaved={ ws.adopt }
							onStateChange={ onSheetState }
							tree={ tree }
							actions={ actions }
							pending={ ws.pending }
							onOpen={ ( key ) => {
								select( key );
								setSheetOpen( false );
							} }
						/>
						<Button
							variant="secondary"
							disabled={ viewLocked }
							onClick={ () => setSheetOpen( false ) }
						>
							{ __( 'Return to workspace', 'ohmylms' ) }
						</Button>
					</Modal>
				) }
			</div>
		</WorkspaceContext.Provider>
	);
}

/** The syllabus whose workspace is open, from the hash route; it changes when the address does. */
function useSyllabusId() {
	const [ id, setId ] = useState( () =>
		syllabusIdFromHash( window.location.hash )
	);
	useEffect( () => {
		const update = () =>
			setId( syllabusIdFromHash( window.location.hash ) );
		window.addEventListener( 'hashchange', update );
		return () => window.removeEventListener( 'hashchange', update );
	}, [] );
	return id;
}

/** The page behind `#/content-hub/curriculum/syllabus/ID`. */
export function SyllabusPage() {
	const id = useSyllabusId();
	if ( ! id ) {
		return (
			<div className="ohmylms-ws ohmylms-ws-fallback">
				<Notice status="error" isDismissible={ false }>
					{ __(
						'Choose a syllabus from the curriculum to edit it.',
						'ohmylms'
					) }
				</Notice>
				<Button variant="link" href={ `#${ CURRICULUM_PATH }` }>
					{ __( 'Back to the curriculum', 'ohmylms' ) }
				</Button>
			</div>
		);
	}
	return <SyllabusWorkspace key={ id } syllabusId={ id } />;
}
