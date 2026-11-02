import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { ListTable } from '../../../ui/ListTable';
import { useListState } from '../../components/listQuery';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { readSubjects } from './skill-map';
import {
	isConflict,
	MAX_PLACEMENT,
	placementProblem,
	readPlacement,
	savePlacement,
	type PlacementPage,
	type PlacementRow,
} from './workspace';

/**
 * Place shared skills in domains (strands) and level bands. Edits are staged per row and saved as one
 * all-or-nothing batch against each skill's revision; filters live in the address.
 */
export function PlacementPanel() {
	const list = useListState( {
		subject: '',
		domain: '',
		unplaced: '1',
		s: '',
	} );
	const subjects = useRequest( readSubjects );
	const [ search, setSearch ] = useState( list.filters.s );
	const { subject, domain, unplaced, s } = list.filters;
	const load = useCallback(
		( signal: AbortSignal ) =>
			subject
				? readPlacement(
						{
							subject,
							domain,
							search: s,
							unplaced: '1' === unplaced,
							page: list.page,
						},
						signal
					)
				: Promise.resolve( null ),
		[ subject, domain, unplaced, s, list.page ]
	);
	const request = useRequest( load );
	return (
		<section aria-labelledby="oml-ws-placement">
			<h3 id="oml-ws-placement">
				{ __( 'Domain placement', 'ohmylms' ) }
			</h3>
			<p>
				{ __(
					'Place each shared skill in a domain (strand) and the levels it is learned at. The skill map, AI matching and guidance read these placements.',
					'ohmylms'
				) }
			</p>
			<SelectControl
				label={ __( 'Subject', 'ohmylms' ) }
				value={ subject }
				options={ [
					{ value: '', label: __( 'Choose a subject', 'ohmylms' ) },
					...( subjects.data ?? [] ).map( ( entry ) => ( {
						value: entry.subject,
						label: entry.subject,
					} ) ),
				] }
				onChange={ ( value ) =>
					list.setFilters( { ...list.filters, subject: value } )
				}
			/>
			{ subjects.error && (
				<Notice status="error" isDismissible={ false }>
					{ subjects.error }
				</Notice>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }{ ' ' }
					<Button variant="link" onClick={ request.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ subject && request.data && (
				<PlacementTable
					key={ JSON.stringify( [ list.filters, list.page ] ) }
					page={ request.data }
					pageNumber={ list.page }
					onPage={ list.setPage }
					search={ search }
					onSearch={ setSearch }
					filters={ list.filters }
					onFilters={ list.setFilters }
					onSaved={ request.retry }
				/>
			) }
		</section>
	);
}

function PlacementTable( {
	page,
	pageNumber,
	onPage,
	search,
	onSearch,
	filters,
	onFilters,
	onSaved,
}: {
	page: PlacementPage;
	pageNumber: number;
	onPage: ( page: number ) => void;
	search: string;
	onSearch: ( value: string ) => void;
	filters: { subject: string; domain: string; unplaced: string; s: string };
	onFilters: ( next: {
		subject: string;
		domain: string;
		unplaced: string;
		s: string;
	} ) => void;
	onSaved: () => void;
} ) {
	const [ rows, setRows ] = useState< PlacementRow[] >( page.items );
	const [ edits, setEdits ] = useState< Record< number, PlacementRow > >(
		{}
	);
	const [ selected, setSelected ] = useState< string[] >( [] );
	const [ bulkDomain, setBulkDomain ] = useState( '' );
	const [ bulkFrom, setBulkFrom ] = useState( 0 );
	const [ bulkTo, setBulkTo ] = useState( 0 );
	const [ staleIds, setStaleIds ] = useState< number[] >( [] );
	const [ message, setMessage ] = useState( '' );
	const operation = useOperation();
	useEffect( () => setRows( page.items ), [ page.items ] );
	const domainName = new Map(
		page.domains.map( ( entry ) => [ entry.id, entry.label ] )
	);
	const levelOptions = [
		{ value: '0', label: __( 'Not set', 'ohmylms' ) },
		...page.levels.map( ( level ) => ( {
			value: String( level.value ),
			label: level.label,
		} ) ),
	];
	const domainOptions = [
		{ value: '', label: __( 'Not set', 'ohmylms' ) },
		...page.domains.map( ( entry ) => ( {
			value: entry.id,
			label: entry.label,
		} ) ),
	];
	const dirty = Object.values( edits );
	const problems = dirty.filter( ( row ) => placementProblem( row ) );
	function edit( row: PlacementRow, patch: Partial< PlacementRow > ) {
		setEdits( ( current ) => ( {
			...current,
			[ row.id ]: { ...( current[ row.id ] ?? row ), ...patch },
		} ) );
	}
	function save() {
		void operation.run(
			async ( signal ) => {
				try {
					return await savePlacement( dirty, signal );
				} catch ( failure ) {
					if ( isConflict( failure ) ) {
						const details = ( failure as { details?: unknown } )
							.details as { skill_ids?: number[] } | undefined;
						setStaleIds( details?.skill_ids ?? [] );
					}
					throw failure;
				}
			},
			( saved ) => {
				setEdits( {} );
				setStaleIds( [] );
				setMessage(
					sprintf(
						/* translators: %d: number of skills. */
						__( '%d skills placed.', 'ohmylms' ),
						saved
					)
				);
				onSaved();
			}
		);
	}
	async function reloadKeeping() {
		const fresh = await readPlacement( {
			subject: filters.subject,
			domain: filters.domain,
			search: filters.s,
			unplaced: '1' === filters.unplaced,
			page: pageNumber,
		} ).catch( () => null );
		if ( ! fresh ) {
			return;
		}
		const byId = new Map( fresh.items.map( ( row ) => [ row.id, row ] ) );
		setRows( fresh.items );
		setEdits( ( current ) =>
			Object.fromEntries(
				Object.entries( current ).map( ( [ id, row ] ) => [
					id,
					{
						...row,
						revision:
							byId.get( Number( id ) )?.revision ?? row.revision,
					},
				] )
			)
		);
		setStaleIds( [] );
		operation.clearError();
		setMessage(
			__(
				'Reloaded the latest skills. Your unsaved placements were kept.',
				'ohmylms'
			)
		);
	}
	return (
		<>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }{ ' ' }
					{ staleIds.length > 0 && (
						<Button
							variant="secondary"
							onClick={ () => void reloadKeeping() }
						>
							{ __(
								'Reload skills and keep my placements',
								'ohmylms'
							) }
						</Button>
					) }
				</Notice>
			) }
			<ListTable
				caption={ __( 'Shared skills and their placement', 'ohmylms' ) }
				busy={ operation.busy }
				rows={ rows }
				rowKey={ ( row ) => row.id }
				rowLabel={ ( row ) => row.name }
				rowClassName={ ( row ) =>
					staleIds.includes( row.id ) ? 'is-stale' : undefined
				}
				selected={ selected }
				onSelectedChange={ setSelected }
				search={ {
					label: __( 'Search skills', 'ohmylms' ),
					value: search,
					maxLength: 200,
					onChange: onSearch,
				} }
				onFilter={ () => onFilters( { ...filters, s: search.trim() } ) }
				filters={
					<>
						<SelectControl
							label={ __( 'Domain', 'ohmylms' ) }
							value={ filters.domain }
							disabled={ '1' === filters.unplaced }
							options={ [
								{
									value: '',
									label: __( 'All domains', 'ohmylms' ),
								},
								...page.domains.map( ( entry ) => ( {
									value: entry.id,
									label: entry.label,
								} ) ),
							] }
							onChange={ ( value ) =>
								onFilters( { ...filters, domain: value } )
							}
						/>
						<CheckboxControl
							label={ __( 'Only unplaced skills', 'ohmylms' ) }
							checked={ '1' === filters.unplaced }
							onChange={ ( checked ) =>
								onFilters( {
									...filters,
									unplaced: checked ? '1' : '0',
									domain: '',
								} )
							}
						/>
					</>
				}
				filterLabel={ null }
				pagination={ {
					page: pageNumber,
					totalPages: Math.max( 1, Math.ceil( page.total / 30 ) ),
					totalItems: page.total,
					onChange: onPage,
				} }
				emptyMessage={ __( 'No shared skills here.', 'ohmylms' ) }
				columns={ [
					{
						id: 'name',
						label: __( 'Shared skill', 'ohmylms' ),
						primary: true,
						render: ( row ) => (
							<>
								<strong>{ row.name }</strong>
								{ row.topic && (
									<>
										<br />
										<small>{ row.topic }</small>
									</>
								) }
							</>
						),
					},
					{
						id: 'domain',
						label: __( 'Domain', 'ohmylms' ),
						render: ( row ) => (
							<SelectControl
								label={ sprintf(
									/* translators: %s: skill. */
									__( 'Domain of %s', 'ohmylms' ),
									row.name
								) }
								hideLabelFromVision
								value={ ( edits[ row.id ] ?? row ).domain }
								options={ domainOptions }
								onChange={ ( value ) =>
									edit( row, { domain: value } )
								}
							/>
						),
					},
					{
						id: 'from',
						label: __( 'From level', 'ohmylms' ),
						render: ( row ) => (
							<SelectControl
								label={ sprintf(
									/* translators: %s: skill. */
									__( 'First level of %s', 'ohmylms' ),
									row.name
								) }
								hideLabelFromVision
								value={ String(
									( edits[ row.id ] ?? row ).gradeFrom
								) }
								options={ levelOptions }
								onChange={ ( value ) =>
									edit( row, { gradeFrom: Number( value ) } )
								}
							/>
						),
					},
					{
						id: 'to',
						label: __( 'To level', 'ohmylms' ),
						render: ( row ) => (
							<SelectControl
								label={ sprintf(
									/* translators: %s: skill. */
									__( 'Last level of %s', 'ohmylms' ),
									row.name
								) }
								hideLabelFromVision
								value={ String(
									( edits[ row.id ] ?? row ).gradeTo
								) }
								options={ levelOptions }
								onChange={ ( value ) =>
									edit( row, { gradeTo: Number( value ) } )
								}
							/>
						),
					},
					{
						id: 'state',
						label: __( 'State', 'ohmylms' ),
						render: ( row ) => {
							const current = edits[ row.id ];
							if ( staleIds.includes( row.id ) ) {
								return __( 'Changed elsewhere', 'ohmylms' );
							}
							if ( current && placementProblem( current ) ) {
								return __(
									'Choose a first level, not after the last',
									'ohmylms'
								);
							}
							if ( current ) {
								return __( 'Unsaved', 'ohmylms' );
							}
							return row.domain
								? ( domainName.get( row.domain ) ?? row.domain )
								: __( 'Unplaced', 'ohmylms' );
						},
					},
				] }
			/>
			<fieldset>
				<legend>{ __( 'Place selected skills', 'ohmylms' ) }</legend>
				<SelectControl
					label={ __( 'Domain for selected', 'ohmylms' ) }
					value={ bulkDomain }
					options={ domainOptions }
					onChange={ setBulkDomain }
				/>
				<SelectControl
					label={ __( 'First level for selected', 'ohmylms' ) }
					value={ String( bulkFrom ) }
					options={ levelOptions }
					onChange={ ( value ) => setBulkFrom( Number( value ) ) }
				/>
				<SelectControl
					label={ __( 'Last level for selected', 'ohmylms' ) }
					value={ String( bulkTo ) }
					options={ levelOptions }
					onChange={ ( value ) => setBulkTo( Number( value ) ) }
				/>
				<Button
					variant="secondary"
					disabled={ ! selected.length }
					onClick={ () => {
						for ( const row of rows ) {
							if ( selected.includes( String( row.id ) ) ) {
								edit( row, {
									domain: bulkDomain,
									gradeFrom: bulkFrom,
									gradeTo: bulkTo || bulkFrom,
								} );
							}
						}
					} }
				>
					{ sprintf(
						/* translators: %d: number of skills. */
						__( 'Apply to %d selected', 'ohmylms' ),
						selected.length
					) }
				</Button>
			</fieldset>
			<p>
				<Button
					variant="primary"
					isBusy={ operation.busy }
					disabled={
						! dirty.length ||
						dirty.length > MAX_PLACEMENT ||
						problems.length > 0 ||
						operation.busy
					}
					onClick={ save }
				>
					{ sprintf(
						/* translators: %d: number of skills. */
						__( 'Save %d placements', 'ohmylms' ),
						dirty.length
					) }
				</Button>{ ' ' }
				{ dirty.length > 0 && (
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ () => setEdits( {} ) }
					>
						{ __( 'Discard changes', 'ohmylms' ) }
					</Button>
				) }
			</p>
			{ dirty.length > MAX_PLACEMENT && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: most skills. */
						__( 'Save at most %d skills at a time.', 'ohmylms' ),
						MAX_PLACEMENT
					) }
				</Notice>
			) }
		</>
	);
}
