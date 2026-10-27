import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
	Spinner,
} from '@wordpress/components';
import { useWorkspace } from './context';
import {
	readComparison,
	writeComparison,
	mappingPreview,
} from './comparison.mjs';
import { loadStudioLinks, studioObjectives } from './skill-studio.mjs';
import { relationshipLabels } from './relationships.mjs';
import { StudioGroupEditor } from './StudioGroupEditor';
import { SkillStudioCanvas } from './SkillStudioCanvas';

/**
 * Edit connections in place, preserving source direction and pair revisions.
 * @param root0
 * @param root0.skill
 */
export function WorkspaceConnections( { skill } ) {
	const w = useWorkspace();
	const sources = w.outline.roadmap_sources;
	const { onSourceState } = w;
	const [ sourceNames, setSourceNames ] = useState( {} );
	useEffect( () => {
		let active = true;
		loadStudioLinks( sources, skill.term_id )
			.then( ( groups ) => {
				if ( active ) {
					setSourceNames(
						Object.fromEntries(
							studioObjectives( groups ).map( ( row ) => [
								row.term_id,
								row.source_names.join( ', ' ),
							] )
						)
					);
				}
			} )
			.catch( () => {} );
		return () => {
			active = false;
		};
	}, [ sources, skill.term_id ] );
	const isRoadmap = w.outline.syllabus.item_type === 'roadmap';
	const [ adding, setAdding ] = useState( false );
	const [ source, setSource ] = useState( String( sources[ 0 ]?.id || '' ) );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ results, setResults ] = useState( null );
	const [ chosen, setChosen ] = useState( [] );
	const [ proposal, setProposal ] = useState( null );
	const [ group, setGroup ] = useState( null );
	const [ relation, setRelation ] = useState( 'equivalent' );
	const [ note, setNote ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ diagram, setDiagram ] = useState( false );
	const writing = useRef( false );
	const labels = relationshipLabels();
	const links = skill.connections || [];
	const locked = adding || Boolean( proposal ) || Boolean( group );
	useEffect( () => {
		onSourceState( locked, busy );
		return () => onSourceState( false, false );
	}, [ locked, busy, onSourceState ] );
	useEffect( () => {
		if ( ! locked ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ locked ] );
	useEffect( () => {
		if ( ! adding || ! source ) {
			return undefined;
		}
		let active = true;
		setResults( null );
		setError( '' );
		const timer = setTimeout( () => {
			readComparison( `objectives/${ source }`, { search, page } )
				.then( ( response ) => {
					if ( active ) {
						setResults( response );
					}
				} )
				.catch( ( cause ) => {
					if ( active ) {
						setError( cause.message );
					}
				} );
		}, 200 );
		return () => {
			active = false;
			clearTimeout( timer );
		};
	}, [ adding, source, search, page ] );
	function cancel() {
		setAdding( false );
		setProposal( null );
		setChosen( [] );
		setNote( '' );
		setError( '' );
	}
	function edit( mapping ) {
		if ( locked || busy ) {
			return;
		}
		if ( mapping.group_uuid ) {
			setGroup( mapping );
			return;
		}
		setProposal( mapping );
		setRelation( mapping.relation );
		setNote( mapping.note || '' );
		setError( '' );
	}
	async function save( remove = false ) {
		if ( writing.current ) {
			return;
		}
		writing.current = true;
		setBusy( true );
		setError( '' );
		try {
			if ( proposal ) {
				await writeComparison(
					remove ? 'mapping/remove' : 'mapping',
					{
						specific_id: proposal.specific_id,
						shared_id: proposal.shared_id,
						expected_revision: proposal.revision,
						relation,
						note,
					},
					remove ? 'POST' : 'PUT'
				);
			} else if ( chosen.length ) {
				const pairs = mappingPreview(
					chosen,
					{ id: skill.term_id },
					relation,
					note
				);
				if ( relation === 'combined' ) {
					await writeComparison( 'mapping-groups', {
						shared_id: skill.term_id,
						groups: [
							{
								source_id: Number( source ),
								pairs: pairs.map( ( pair ) => ( {
									specific_id: pair.specific_id,
									expected_revision: pair.expected_revision,
								} ) ),
							},
						],
						relation,
						note,
					} );
				} else {
					await writeComparison( 'mapping', pairs[ 0 ], 'PUT' );
				}
			} else {
				return;
			}
			cancel();
			await w.reload();
			w.say(
				'success',
				remove
					? __(
							'Connection removed. Both skills are kept.',
							'ohmylms'
						)
					: __( 'Connections saved.', 'ohmylms' )
			);
		} catch ( cause ) {
			setError( cause.message );
		} finally {
			writing.current = false;
			setBusy( false );
		}
	}
	const incoming = links.filter(
		( link ) => link.shared_id === skill.term_id
	);
	const rows = incoming.map( ( link ) => ( {
		term_id: link.specific_id,
		name: link.specific?.name || '',
		code: link.specific?.code || '',
		maps_to: [ link ],
	} ) );
	return (
		<section
			className="ohmylms-workspace-connections ohmylms-skill-studio"
			aria-label={ __( 'Connections', 'ohmylms' ) }
		>
			<div className="ohmylms-workspace-connection-head">
				<h3>{ __( 'Connections', 'ohmylms' ) }</h3>
				{ isRoadmap && (
					<Button
						variant="secondary"
						disabled={ locked || busy || ! sources.length }
						onClick={ () => {
							setAdding( true );
							setRelation( 'equivalent' );
							setNote( '' );
						} }
					>
						{ __( 'Connect skill', 'ohmylms' ) }
					</Button>
				) }
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! links.length && (
				<p>{ __( 'No connected skills yet.', 'ohmylms' ) }</p>
			) }
			{ isRoadmap && ! sources.length && (
				<p>
					{ __(
						'Add source syllabuses in the roadmap settings to connect skills.',
						'ohmylms'
					) }
				</p>
			) }
			<ul className="ohmylms-workspace-connection-list">
				{ links.map( ( link ) => (
					<li key={ link.id }>
						<div>
							{ sourceNames[ link.specific_id ] && (
								<p className="ohmylms-ext-muted">
									{ sourceNames[ link.specific_id ] }
								</p>
							) }
							<strong>
								{ link.specific?.code } { link.specific?.name }
							</strong>
							<p>
								{ labels[ link.relation ] } →{ ' ' }
								{ link.shared?.code } { link.shared?.name }
							</p>
							{ link.note && <p>{ link.note }</p> }
						</div>
						<Button
							variant="secondary"
							disabled={ locked || busy }
							onClick={ () => edit( link ) }
						>
							{ link.group_uuid
								? __( 'Edit group', 'ohmylms' )
								: __( 'Edit connection', 'ohmylms' ) }
						</Button>
					</li>
				) ) }
			</ul>
			{ adding && (
				<div className="ohmylms-workspace-connection-form">
					<SelectControl
						label={ __( 'Source syllabus', 'ohmylms' ) }
						value={ source }
						options={ sources.map( ( item ) => ( {
							value: String( item.id ),
							label: item.name,
						} ) ) }
						disabled={ busy }
						onChange={ ( value ) => {
							setSource( value );
							setChosen( [] );
							setPage( 1 );
						} }
					/>
					<TextControl
						label={ __( 'Find a source skill', 'ohmylms' ) }
						type="search"
						value={ search }
						disabled={ busy }
						onChange={ ( value ) => {
							setSearch( value );
							setPage( 1 );
						} }
					/>
					{ ! results && ! error && <Spinner /> }
					<ul className="ohmylms-workspace-connection-list">
						{ results?.items?.map( ( row ) => {
							const connected = links.some(
								( link ) => link.specific_id === row.term_id
							);
							const selected = chosen.some(
								( item ) => item.term_id === row.term_id
							);
							let buttonLabel = __( 'Select', 'ohmylms' );
							if ( selected ) {
								buttonLabel = __( 'Selected', 'ohmylms' );
							}
							if ( connected ) {
								buttonLabel = __( 'Connected', 'ohmylms' );
							}

							return (
								<li key={ row.term_id }>
									<span>
										{ row.code } · { row.name }
									</span>
									<Button
										variant={
											selected ? 'primary' : 'secondary'
										}
										disabled={
											busy || connected || row.is_shared
										}
										aria-pressed={ selected }
										onClick={ () => {
											if ( selected ) {
												setChosen(
													chosen.filter(
														( item ) =>
															item.term_id !==
															row.term_id
													)
												);
											} else if (
												relation === 'combined'
											) {
												setChosen( [ ...chosen, row ] );
											} else {
												setChosen( [ row ] );
											}
										} }
									>
										{ buttonLabel }
									</Button>
								</li>
							);
						} ) }
					</ul>
					{ results?.total === 0 && (
						<p>{ __( 'No matching source skills.', 'ohmylms' ) }</p>
					) }
					<Button
						disabled={ busy || page === 1 }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Previous', 'ohmylms' ) }
					</Button>
					<Button
						disabled={
							busy || ! results || page * 30 >= results.total
						}
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Next', 'ohmylms' ) }
					</Button>
					{ chosen.length > 0 && (
						<p>
							{ chosen
								.map( ( row ) => `${ row.code } ${ row.name }` )
								.join( ' + ' ) }{ ' ' }
							→ { skill.name }
						</p>
					) }
				</div>
			) }
			{ ( adding || proposal ) && (
				<div className="ohmylms-workspace-connection-form">
					{ proposal && (
						<p>
							{ proposal.specific?.name } →{ ' ' }
							{ proposal.shared?.name }
						</p>
					) }
					<SelectControl
						label={ __(
							'Connection type (source relative to shared skill)',
							'ohmylms'
						) }
						value={ relation }
						disabled={ busy }
						options={ Object.entries( labels )
							.filter(
								( [ key ] ) => adding || key !== 'combined'
							)
							.map( ( [ value, label ] ) => ( {
								value,
								label,
							} ) ) }
						onChange={ ( value ) => {
							setRelation( value );
							if ( value !== 'combined' ) {
								setChosen( chosen.slice( 0, 1 ) );
							}
						} }
					/>
					{ relation === 'combined' && (
						<p>
							{ __(
								'Select at least two source skills. They will be edited together as one group.',
								'ohmylms'
							) }
						</p>
					) }
					<TextareaControl
						label={ __( 'Connection notes', 'ohmylms' ) }
						value={ note }
						maxLength={ 1000 }
						disabled={ busy }
						onChange={ setNote }
					/>
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={
							busy ||
							( adding &&
								chosen.length <
									( relation === 'combined' ? 2 : 1 ) )
						}
						onClick={ () => save() }
					>
						{ __( 'Save connection', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						disabled={ busy }
						onClick={ cancel }
					>
						{ __( 'Discard edits', 'ohmylms' ) }
					</Button>{ ' ' }
					{ proposal && (
						<Button
							variant="link"
							isDestructive
							disabled={ busy }
							onClick={ () => save( true ) }
						>
							{ __( 'Remove connection', 'ohmylms' ) }
						</Button>
					) }
					<p>
						{ __(
							'Save or discard connection edits before selecting another skill.',
							'ohmylms'
						) }
					</p>
				</div>
			) }
			{ rows.length > 0 && (
				<>
					<Button
						variant="link"
						aria-expanded={ diagram }
						onClick={ () => setDiagram( ! diagram ) }
					>
						{ diagram
							? __( 'Hide connection diagram', 'ohmylms' )
							: __( 'Show connection diagram', 'ohmylms' ) }
					</Button>
					{ diagram && (
						<SkillStudioCanvas
							skill={ { id: skill.term_id, name: skill.name } }
							rows={ rows }
							proposal={ null }
							selectedId={ proposal?.specific_id || 0 }
							disabled={ locked || busy }
							onSelect={ ( row ) =>
								row && edit( row.maps_to[ 0 ] )
							}
							onConnection={ ( row ) =>
								row && edit( row.maps_to[ 0 ] )
							}
						/>
					) }
				</>
			) }
			{ group && (
				<StudioGroupEditor
					mapping={ group }
					onClose={ () => setGroup( null ) }
					onChanged={ w.reload }
				/>
			) }
		</section>
	);
}
