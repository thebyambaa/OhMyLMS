/** @jsx createElement */
import {
	createElement,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { SharedSkillEditor } from './SharedSkillEditor';
import { SkillStudioCanvas } from './SkillStudioCanvas';
import { RelationshipBadge } from './RelationshipBadge';
import { relationshipLabels } from './relationships.mjs';
import {
	readComparison,
	writeComparison,
	readLocalDraft,
	saveLocalDraft,
} from './comparison.mjs';
import {
	loadStudioLinks,
	focusedStudioObjectives,
	studioWrite,
} from './skill-studio.mjs';

/**
 * Full-screen authoring view over existing skills and revision-checked mappings.
 * @param {Object}                                                   props              Studio props.
 * @param {number}                                                   props.roadmapId    Roadmap library scope.
 * @param {string}                                                   props.subject      Subject for new skills.
 * @param {Object}                                                   props.initialSkill Initial selection.
 * @param {Array}                                                    props.sources      Selected syllabus records.
 * @param {() => void}                                               props.onClose      Close the modal.
 * @param {(skill: Object) => void}                                  props.onChanged    Refresh the underlying workspace.
 * @param {(row: Object, mapping: Object, sourceId: number) => void} props.onEditGroup  Open the existing complete-group editor.
 */
export function SkillStudio( {
	roadmapId,
	subject,
	initialSkill,
	sources,
	onClose,
	onChanged,
	onEditGroup,
} ) {
	const draftKey = `studio-${ roadmapId || 'workspace' }`;
	const draft = useRef( readLocalDraft( draftKey ) ).current;
	const [ focusId, setFocusId ] = useState(
		draft.focusId || initialSkill?.id || 0
	);
	const [ shared, setShared ] = useState( initialSkill || null );
	const [ connectionSet, setConnectionSet ] = useState( {
		skillId: 0,
		groups: [],
	} );
	const [ library, setLibrary ] = useState( 'shared' );
	const [ filters, setFilters ] = useState( {
		search: '',
		topic: '',
		status: '',
		page: 1,
	} );
	const [ data, setData ] = useState( null );
	const [ libraryLoading, setLibraryLoading ] = useState( true );
	const [ linksLoading, setLinksLoading ] = useState( false );
	const [ libraryError, setLibraryError ] = useState( '' );
	const [ selectedRow, setSelectedRow ] = useState(
		draft.proposal?.row || null
	);
	const [ proposal, setProposal ] = useState( draft.proposal || null );
	const [ pickingReplacement, setPickingReplacement ] = useState( false );
	const [ creating, setCreating ] = useState( false );
	const [ editing, setEditing ] = useState( false );
	const [ skillDirty, setSkillDirty ] = useState( false );
	const [ childBusy, setChildBusy ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ revision, setRevision ] = useState( 0 );
	const [ notice, setNotice ] = useState( null );
	const [ closing, setClosing ] = useState( false );
	const [ removing, setRemoving ] = useState( false );
	const [ mode, setMode ] = useState( 'canvas' );
	const [ canvasPage, setCanvasPage ] = useState( 1 );
	const [ history, setHistory ] = useState( [] );
	const writing = useRef( false );
	const sourceIds = sources.map( ( source ) => source.id ).join( ',' );
	const groups =
		connectionSet.skillId === focusId ? connectionSet.groups : [];
	const linkedRows = useMemo(
		() => focusedStudioObjectives( connectionSet, focusId ),
		[ connectionSet, focusId ]
	);
	const locked = busy || childBusy;
	const proposalDirty = Boolean(
		proposal &&
		( ! proposal.mapping ||
			proposal.replacement ||
			proposal.relation !== proposal.mapping.relation ||
			proposal.note !== proposal.mapping.note )
	);
	const dirty = skillDirty || proposalDirty;
	useEffect( () => {
		if ( ! dirty ) {
			setClosing( false );
		}
	}, [ dirty ] );
	const labels = relationshipLabels();
	const individualRelations = Object.entries( labels )
		.filter( ( [ value ] ) => value !== 'combined' )
		.map( ( [ value, label ] ) => ( { value, label } ) );
	useEffect(
		() =>
			saveLocalDraft( draftKey, {
				focusId,
				proposal: proposalDirty ? proposal : null,
			} ),
		[ draftKey, focusId, proposal, proposalDirty ]
	);
	useEffect( () => {
		if ( ! dirty && ! locked ) {
			return;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty, locked ] );
	useEffect( () => {
		let active = true;
		setLibraryLoading( true );
		setLibraryError( '' );
		setData( null );
		const timer = setTimeout( () => {
			const request =
				library === 'shared'
					? readComparison( 'shared-skills', {
							roadmap_id: roadmapId,
							search: filters.search,
							page: filters.page,
						} )
					: readComparison( `objectives/${ library }`, filters );
			request
				.then( ( response ) => {
					if ( active ) {
						setData( { ...response, library } );
					}
				} )
				.catch(
					( cause ) => active && setLibraryError( cause.message )
				)
				.finally( () => active && setLibraryLoading( false ) );
		}, 200 );
		return () => {
			active = false;
			clearTimeout( timer );
		};
	}, [ library, filters, revision, roadmapId ] );
	useEffect( () => {
		if ( ! focusId ) {
			return;
		}
		let active = true;
		setLinksLoading( true );
		setConnectionSet( { skillId: focusId, groups: [] } );
		Promise.all( [
			readComparison( `shared-skills/${ focusId }` ),
			loadStudioLinks( sources, focusId ),
		] )
			.then( ( [ skill, loaded ] ) => {
				if ( active ) {
					setShared( skill );
					setConnectionSet( { skillId: focusId, groups: loaded } );
				}
			} )
			.catch(
				( cause ) =>
					active &&
					setNotice( { status: 'error', message: cause.message } )
			)
			.finally( () => active && setLinksLoading( false ) );
		return () => {
			active = false;
		};
	}, [ focusId, revision, sourceIds, sources ] );
	function filter( key, value ) {
		setFilters( ( current ) => ( {
			...current,
			[ key ]: value,
			page: 1,
		} ) );
	}
	function changeLibrary( value ) {
		setData( null );
		setLibraryLoading( true );
		setLibrary( value );
		setFilters( { search: '', topic: '', status: '', page: 1 } );
	}
	function canSwitch() {
		if ( locked || dirty ) {
			setNotice( {
				status: 'warning',
				message: __(
					'Save or discard the current edits before selecting another skill.',
					'ohmylms'
				),
			} );
			return false;
		}
		return true;
	}
	function chooseShared( skill ) {
		if ( ! canSwitch() ) {
			return;
		}
		setFocusId( skill.id );
		setShared( skill );
		setLinksLoading( true );
		setConnectionSet( { skillId: skill.id, groups: [] } );
		setNotice( null );
		setSelectedRow( null );
		setProposal( null );
		setHistory( [] );
		setCreating( false );
		setEditing( false );
		setPickingReplacement( false );
		setCanvasPage( 1 );
		setRemoving( false );
	}
	function chooseRow( row ) {
		if ( pickingReplacement && row && proposal ) {
			if (
				row.is_shared ||
				row.maps_to.some(
					( mapping ) =>
						mapping.shared_id === focusId &&
						row.term_id !== proposal.row.term_id
				)
			) {
				setNotice( {
					status: 'warning',
					message: __(
						'Choose an unconnected source objective. Existing connections must be edited separately.',
						'ohmylms'
					),
				} );
				return;
			}
			setHistory( ( previous ) => [
				...previous.slice( -19 ),
				proposal,
			] );
			setProposal( {
				...proposal,
				replacement: row.term_id === proposal.row.term_id ? null : row,
			} );
			setPickingReplacement( false );
			return;
		}
		if ( ! canSwitch() ) {
			return;
		}
		setSelectedRow( row );
		setProposal( null );
		setCreating( false );
		setEditing( false );
		setHistory( [] );
		setRemoving( false );
	}
	function inspect( row ) {
		if ( ! canSwitch() ) {
			return;
		}
		const mapping = row.maps_to.find(
			( item ) => item.shared_id === focusId
		);
		setSelectedRow( row );
		setCreating( false );
		setEditing( false );
		setRemoving( false );
		setPickingReplacement( false );
		setHistory( [] );
		setProposal(
			mapping && ! mapping.group_uuid
				? {
						row,
						mapping,
						replacement: null,
						relation: mapping.relation,
						note: mapping.note,
					}
				: null
		);
	}
	function changeProposal( updates ) {
		setHistory( ( previous ) => [ ...previous.slice( -19 ), proposal ] );
		setProposal( { ...proposal, ...updates } );
		setRemoving( false );
	}
	function discard() {
		setProposal( null );
		setHistory( [] );
		setPickingReplacement( false );
		setRemoving( false );
	}
	function requestClose() {
		if ( locked ) {
			return;
		}
		if ( dirty ) {
			setClosing( true );
			return;
		}
		onClose();
	}
	async function mutate( action, message ) {
		if ( writing.current || locked ) {
			return;
		}
		writing.current = true;
		setBusy( true );
		setNotice( null );
		let saved = false;
		try {
			await action();
			saved = true;
			discard();
			setSelectedRow( null );
			const fresh = await readComparison( `shared-skills/${ focusId }` );
			setShared( fresh );
			onChanged( fresh );
			setNotice( { status: 'success', message } );
		} catch ( cause ) {
			setNotice( {
				status: 'error',
				message: saved
					? __(
							'The change was saved, but refreshing failed. Reload the editor to see the saved connection.',
							'ohmylms'
						)
					: cause.message,
			} );
			if ( saved ) {
				onChanged( null );
			}
		} finally {
			if ( saved ) {
				setRevision( ( current ) => current + 1 );
			}
			writing.current = false;
			setBusy( false );
		}
	}
	function savedSkill( skill ) {
		setSkillDirty( false );
		setCreating( false );
		setEditing( false );
		setFocusId( skill.id );
		setShared( skill );
		setSelectedRow( null );
		discard();
		setCanvasPage( 1 );
		setRevision( ( current ) => current + 1 );
		onChanged( skill );
		setNotice( {
			status: 'success',
			message: __(
				'Shared skill saved. Search for source objectives to connect.',
				'ohmylms'
			),
		} );
	}
	const activeMapping = selectedRow?.maps_to.find(
		( mapping ) => mapping.shared_id === focusId
	);
	const visibleRows = linkedRows.slice(
		( canvasPage - 1 ) * 8,
		canvasPage * 8
	);
	for ( const row of [ selectedRow, proposal?.replacement ] ) {
		if (
			row &&
			! visibleRows.some( ( item ) => item.term_id === row.term_id )
		) {
			visibleRows.push( row );
		}
	}
	const effectiveShared = shared?.id === focusId ? shared : null;
	const rows = data?.library === library ? data.items : [];
	let saveStatus = __( 'Saved', 'ohmylms' );
	if ( dirty ) {
		saveStatus = __( 'Unsaved edits', 'ohmylms' );
	}
	if ( locked ) {
		saveStatus = __( 'Saving…', 'ohmylms' );
	}
	return (
		<Modal
			title={ __( 'Shared skills visual editor', 'ohmylms' ) }
			className="ohmylms-skill-studio"
			isFullScreen
			shouldCloseOnClickOutside={ false }
			onRequestClose={ requestClose }
		>
			<div className="ohmylms-studio-toolbar">
				<div className="ohmylms-studio-actions">
					<Button
						variant={ mode === 'canvas' ? 'primary' : 'secondary' }
						aria-pressed={ mode === 'canvas' }
						onClick={ () => setMode( 'canvas' ) }
					>
						{ __( 'Node canvas', 'ohmylms' ) }
					</Button>
					<Button
						variant={
							mode === 'coverage' ? 'primary' : 'secondary'
						}
						aria-pressed={ mode === 'coverage' }
						onClick={ () => setMode( 'coverage' ) }
					>
						{ __( 'Coverage', 'ohmylms' ) }
					</Button>
					<Button
						variant="secondary"
						disabled={ locked || dirty }
						onClick={ () => {
							setCreating( true );
							setEditing( false );
							setSelectedRow( null );
							discard();
						} }
					>
						{ __( 'New shared skill', 'ohmylms' ) }
					</Button>
				</div>
				<div className="ohmylms-studio-actions">
					<span role="status">{ saveStatus }</span>
					<Button
						variant="secondary"
						disabled={ locked || ! history.length }
						onClick={ () => {
							setProposal( history[ history.length - 1 ] );
							setHistory( history.slice( 0, -1 ) );
							setPickingReplacement( false );
							setRemoving( false );
						} }
					>
						{ __( 'Undo edits', 'ohmylms' ) }
					</Button>
					<Button
						variant="secondary"
						disabled={ locked }
						onClick={ requestClose }
					>
						{ __( 'Close editor', 'ohmylms' ) }
					</Button>
				</div>
			</div>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
					{ notice.status === 'error' && (
						<Button
							variant="link"
							disabled={ locked || skillDirty }
							onClick={ () => {
								discard();
								setSelectedRow( null );
								setRevision( ( current ) => current + 1 );
								setNotice( null );
							} }
						>
							{ __( 'Discard proposal and reload', 'ohmylms' ) }
						</Button>
					) }
				</Notice>
			) }
			{ closing && (
				<Notice status="warning" isDismissible={ false }>
					<p>
						{ __(
							'Close with unsaved edits? Your draft stays in this browser session; nothing is saved automatically.',
							'ohmylms'
						) }
					</p>
					<Button
						variant="secondary"
						onClick={ () => setClosing( false ) }
					>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
					<Button variant="link" onClick={ onClose }>
						{ __( 'Close and keep draft', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			<div className="ohmylms-studio-workspace">
				<aside
					className="ohmylms-studio-library"
					aria-label={ __( 'Skill browser', 'ohmylms' ) }
				>
					<TextControl
						label={ __( 'Find a skill', 'ohmylms' ) }
						type="search"
						value={ filters.search }
						onChange={ ( value ) => filter( 'search', value ) }
					/>
					<SelectControl
						label={ __( 'Source', 'ohmylms' ) }
						value={ library }
						onChange={ changeLibrary }
						options={ [
							{
								value: 'shared',
								label: __( 'Shared skills', 'ohmylms' ),
							},
							...sources.map( ( source ) => ( {
								value: String( source.id ),
								label: `${ source.name } · ${ source.version || __( 'Version not recorded', 'ohmylms' ) }`,
							} ) ),
						] }
					/>
					{ library !== 'shared' && (
						<>
							<SelectControl
								label={ __( 'Topic', 'ohmylms' ) }
								value={ filters.topic }
								onChange={ ( value ) =>
									filter( 'topic', value )
								}
								options={ [
									{
										value: '',
										label: __( 'All topics', 'ohmylms' ),
									},
									...Object.entries( data?.topics || {} ).map(
										( [ value, label ] ) => ( {
											value,
											label,
										} )
									),
								] }
							/>
							<CheckboxControl
								label={ __(
									'Unmapped objectives only',
									'ohmylms'
								) }
								checked={ filters.status === 'unmapped' }
								onChange={ ( checked ) =>
									filter(
										'status',
										checked ? 'unmapped' : ''
									)
								}
							/>
						</>
					) }
					{ pickingReplacement && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'Choose the replacement objective below. The original connection stays until you save.',
								'ohmylms'
							) }
						</Notice>
					) }
					<div
						className="ohmylms-studio-results"
						aria-busy={ libraryLoading }
					>
						{ libraryLoading && <Spinner /> }
						{ libraryError && <p role="alert">{ libraryError }</p> }
						{ ! libraryLoading &&
							! libraryError &&
							! rows.length && (
								<p>
									{ __(
										'No skills match these filters.',
										'ohmylms'
									) }
								</p>
							) }
						{ rows.map( ( row ) => (
							<button
								type="button"
								key={ row.id || row.term_id }
								className="ohmylms-studio-result"
								disabled={
									locked ||
									( pickingReplacement &&
										library === 'shared' ) ||
									( library !== 'shared' && row.is_shared )
								}
								aria-pressed={
									library === 'shared'
										? focusId === row.id
										: selectedRow?.term_id === row.term_id
								}
								onClick={ () =>
									library === 'shared'
										? chooseShared( row )
										: chooseRow( {
												...row,
												source_id: Number( library ),
												source_names: [
													data.syllabus.name,
												],
											} )
								}
							>
								<strong>{ row.name }</strong>
								<small>
									{ library === 'shared'
										? row.topic
										: `${ row.code } · ${ row.category }` }
								</small>
							</button>
						) ) }
					</div>
					<div className="ohmylms-studio-actions">
						<Button
							variant="secondary"
							disabled={ libraryLoading || filters.page === 1 }
							onClick={ () =>
								setFilters( {
									...filters,
									page: filters.page - 1,
								} )
							}
						>
							{ __( 'Previous', 'ohmylms' ) }
						</Button>
						<Button
							variant="secondary"
							disabled={
								libraryLoading ||
								filters.page * 30 >= ( data?.total || 0 )
							}
							onClick={ () =>
								setFilters( {
									...filters,
									page: filters.page + 1,
								} )
							}
						>
							{ __( 'More skills', 'ohmylms' ) }
						</Button>
					</div>
				</aside>
				<main className="ohmylms-studio-main">
					<h2>
						{ effectiveShared?.name ||
							__( 'Choose or create a shared skill', 'ohmylms' ) }
					</h2>
					{ linksLoading && <Spinner /> }
					{ mode === 'canvas' ? (
						<SkillStudioCanvas
							skill={ effectiveShared }
							rows={ visibleRows }
							proposal={ proposalDirty ? proposal : null }
							selectedId={ selectedRow?.term_id || 0 }
							disabled={ locked || linksLoading }
							onSelect={ chooseRow }
							onConnection={ inspect }
						/>
					) : (
						<div className="ohmylms-studio-coverage">
							<table>
								<caption>
									{ __(
										'Selected skill connections by source syllabus',
										'ohmylms'
									) }
								</caption>
								<thead>
									<tr>
										<th>
											{ __(
												'Syllabus / version',
												'ohmylms'
											) }
										</th>
										<th>
											{ __(
												'Linked objectives',
												'ohmylms'
											) }
										</th>
										<th>
											{ __( 'Equivalent', 'ohmylms' ) }
										</th>
										<th>
											{ __(
												'Reference or joint coverage',
												'ohmylms'
											) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ groups.map( ( group ) => {
										const equivalent = group.items.filter(
											( row ) =>
												row.maps_to.some(
													( mapping ) =>
														mapping.shared_id ===
															focusId &&
														mapping.relation ===
															'equivalent'
												)
										).length;
										return (
											<tr key={ group.source.id }>
												<td>
													{ group.source.name }
													<small>
														{ group.source.version }
													</small>
												</td>
												<td>{ group.total }</td>
												<td>{ equivalent }</td>
												<td>
													{ group.total - equivalent }
												</td>
											</tr>
										);
									} ) }
								</tbody>
							</table>
						</div>
					) }
					{ linkedRows.length > 8 && (
						<div className="ohmylms-studio-actions">
							<Button
								variant="secondary"
								disabled={ canvasPage === 1 }
								onClick={ () =>
									setCanvasPage( canvasPage - 1 )
								}
							>
								{ __( 'Previous connections', 'ohmylms' ) }
							</Button>
							<span>
								{ sprintf(
									/* translators: 1: page, 2: page count. */ __(
										'Connections page %1$d of %2$d',
										'ohmylms'
									),
									canvasPage,
									Math.ceil( linkedRows.length / 8 )
								) }
							</span>
							<Button
								variant="secondary"
								disabled={ canvasPage * 8 >= linkedRows.length }
								onClick={ () =>
									setCanvasPage( canvasPage + 1 )
								}
							>
								{ __( 'More connections', 'ohmylms' ) }
							</Button>
						</div>
					) }
				</main>
				<aside
					className="ohmylms-studio-inspector"
					aria-label={ __( 'Selection details', 'ohmylms' ) }
				>
					{ ( creating || editing ) && (
						<>
							<SharedSkillEditor
								key={
									creating
										? 'new'
										: `${ effectiveShared?.id }:${ effectiveShared?.revision }`
								}
								roadmapId={ roadmapId }
								subject={
									creating
										? subject
										: effectiveShared?.subject || subject
								}
								skill={ creating ? null : effectiveShared }
								onDirty={ setSkillDirty }
								onBusy={ setChildBusy }
								onSaved={ savedSkill }
							/>
							<Button
								variant="link"
								disabled={ skillDirty || locked }
								onClick={ () => {
									setCreating( false );
									setEditing( false );
								} }
							>
								{ __( 'Cancel skill editor', 'ohmylms' ) }
							</Button>
						</>
					) }
					{ ! creating && ! editing && proposal && (
						<>
							<h3>{ __( 'Edit connection', 'ohmylms' ) }</h3>
							<p>
								{ proposal.row.name } →{ ' ' }
								{ effectiveShared?.name }
							</p>
							{ proposal.replacement && (
								<Notice status="info" isDismissible={ false }>
									{ sprintf(
										/* translators: %s: replacement objective. */ __(
											'Replace the source objective with: %s',
											'ohmylms'
										),
										proposal.replacement.name
									) }
								</Notice>
							) }
							{ proposal.mapping && (
								<Button
									variant="secondary"
									disabled={ locked }
									onClick={ () => {
										setPickingReplacement( true );
										changeLibrary(
											String( proposal.row.source_id )
										);
									} }
								>
									{ __(
										'Change connected skill',
										'ohmylms'
									) }
								</Button>
							) }
							{ pickingReplacement && (
								<Button
									variant="link"
									onClick={ () =>
										setPickingReplacement( false )
									}
								>
									{ __(
										'Cancel replacement search',
										'ohmylms'
									) }
								</Button>
							) }
							<SelectControl
								label={ __( 'Relationship', 'ohmylms' ) }
								value={ proposal.relation }
								options={ individualRelations }
								disabled={ locked }
								onChange={ ( value ) =>
									changeProposal( { relation: value } )
								}
							/>
							<p className="ohmylms-studio-impact">
								{ proposal.relation === 'equivalent'
									? __(
											'Assessment evidence contributes to shared mastery. Review the entire scope before choosing equivalence.',
											'ohmylms'
										)
									: __(
											'Reference connection only. Assessment evidence stays separate.',
											'ohmylms'
										) }
							</p>
							<TextareaControl
								label={ __( 'Review note', 'ohmylms' ) }
								value={ proposal.note }
								maxLength={ 1000 }
								disabled={ locked }
								onChange={ ( value ) =>
									changeProposal( { note: value } )
								}
							/>
							<div className="ohmylms-studio-actions">
								<Button
									variant="primary"
									isBusy={ busy }
									disabled={
										locked ||
										! proposalDirty ||
										pickingReplacement
									}
									onClick={ () => {
										const write = studioWrite(
											proposal,
											focusId
										);
										if ( write ) {
											mutate(
												() =>
													writeComparison(
														write.route,
														write.data,
														write.method
													),
												__(
													'Connection saved.',
													'ohmylms'
												)
											);
										}
									} }
								>
									{ __( 'Save connection', 'ohmylms' ) }
								</Button>
								<Button
									variant="secondary"
									disabled={ locked }
									onClick={ discard }
								>
									{ __( 'Discard proposal', 'ohmylms' ) }
								</Button>
							</div>
							{ proposal.mapping && (
								<Button
									variant="link"
									isDestructive
									disabled={ locked || proposalDirty }
									onClick={ () => setRemoving( true ) }
								>
									{ __( 'Remove connection', 'ohmylms' ) }
								</Button>
							) }
							{ removing && (
								<Notice
									status="warning"
									isDismissible={ false }
								>
									<p>
										{ __(
											'Remove this connection? Both skills and their historical evidence are kept.',
											'ohmylms'
										) }
									</p>
									<Button
										variant="secondary"
										isDestructive
										disabled={ locked }
										onClick={ () =>
											mutate(
												() =>
													writeComparison(
														'mapping/remove',
														{
															specific_id:
																proposal.row
																	.term_id,
															shared_id: focusId,
															expected_revision:
																proposal.mapping
																	.revision,
														}
													),
												__(
													'Connection removed.',
													'ohmylms'
												)
											)
										}
									>
										{ __(
											'Remove this connection',
											'ohmylms'
										) }
									</Button>
									<Button
										variant="link"
										onClick={ () => setRemoving( false ) }
									>
										{ __( 'Cancel', 'ohmylms' ) }
									</Button>
								</Notice>
							) }
						</>
					) }
					{ ! creating && ! editing && ! proposal && selectedRow && (
						<>
							<h3>{ selectedRow.name }</h3>
							<p>
								{ selectedRow.objective ||
									selectedRow.description }
							</p>
							<p>
								{ selectedRow.source_names?.join( ' · ' ) } ·{ ' ' }
								{ selectedRow.category }
							</p>
							{ activeMapping ? (
								<>
									<RelationshipBadge
										mapping={ activeMapping }
									/>
									{ activeMapping.group_uuid ? (
										<>
											<p>
												{ __(
													'These objectives are required together. Edit the complete group to preserve its meaning.',
													'ohmylms'
												) }
											</p>
											<Button
												variant="secondary"
												disabled={ locked }
												onClick={ () => {
													onEditGroup(
														selectedRow,
														activeMapping,
														selectedRow.source_id
													);
													onClose();
												} }
											>
												{ __(
													'Edit complete group',
													'ohmylms'
												) }
											</Button>
										</>
									) : (
										<Button
											variant="secondary"
											disabled={ locked }
											onClick={ () =>
												inspect( selectedRow )
											}
										>
											{ __(
												'Edit connection',
												'ohmylms'
											) }
										</Button>
									) }
								</>
							) : (
								<Button
									variant="primary"
									disabled={
										locked ||
										! effectiveShared ||
										selectedRow.is_shared
									}
									onClick={ () =>
										setProposal( {
											row: selectedRow,
											mapping: null,
											replacement: null,
											relation: 'related',
											note: '',
										} )
									}
								>
									{ __( 'Connect objective', 'ohmylms' ) }
								</Button>
							) }
						</>
					) }
					{ ! creating &&
						! editing &&
						! proposal &&
						! selectedRow &&
						effectiveShared && (
							<>
								<h3>{ effectiveShared.name }</h3>
								<p>{ effectiveShared.description }</p>
								<p>{ effectiveShared.topic }</p>
								<Button
									variant="secondary"
									disabled={ locked }
									onClick={ () => setEditing( true ) }
								>
									{ __( 'Edit shared skill', 'ohmylms' ) }
								</Button>
								<h3>
									{ __( 'Connected objectives', 'ohmylms' ) }
								</h3>
								{ linkedRows.map( ( row ) => {
									const mapping = row.maps_to.find(
										( item ) => item.shared_id === focusId
									);
									if ( ! mapping ) {
										return null;
									}
									return (
										<Button
											key={ row.term_id }
											variant="tertiary"
											className="ohmylms-studio-connection"
											disabled={ locked }
											onClick={ () => inspect( row ) }
										>
											<span>{ row.name }</span>
											<small>
												{ labels[ mapping.relation ] }
											</small>
										</Button>
									);
								} ) }
								{ ! linksLoading && ! linkedRows.length && (
									<p>
										{ __(
											'No connections in the selected sources. Choose a source in the skill browser to find objectives.',
											'ohmylms'
										) }
									</p>
								) }
							</>
						) }
					{ ! creating &&
						! editing &&
						! proposal &&
						! selectedRow &&
						! effectiveShared && (
							<p>
								{ __(
									'Choose a shared skill in the browser or create a new one.',
									'ohmylms'
								) }
							</p>
						) }
				</aside>
			</div>
		</Modal>
	);
}
