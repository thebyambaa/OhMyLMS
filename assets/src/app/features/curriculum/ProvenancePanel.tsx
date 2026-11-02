import { Button, Notice, TextareaControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { ListTableSelect, ListTable } from '../../../ui/ListTable';
import { WidefatTable } from '../../components/WidefatTable';
import { useListState } from '../../components/listQuery';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	readProvenance,
	readQueue,
	REVIEW_STATES,
	reviewSkills,
	type DecisionResult,
	type ProvenanceRow,
	type ReviewState,
} from './workspace';
import { relationLabel, reviewLabel, sourceLabel } from './WorkspaceParts';

function reviewOf( value: string ): ReviewState | 'all' {
	return REVIEW_STATES.find( ( state ) => state === value ) ?? 'all';
}

/**
 * Where skills came from (import, syllabus, AI run, manual) and who approved them: a review queue with
 * approve/reject decisions recorded in each skill's audit trail. `?review=&source=&s=&skill=`.
 */
export function ProvenancePanel() {
	const list = useListState( {
		review: 'pending',
		source: '',
		s: '',
		skill: '',
	} );
	const [ search, setSearch ] = useState( list.filters.s );
	const [ selected, setSelected ] = useState< string[] >( [] );
	const [ note, setNote ] = useState( '' );
	const [ results, setResults ] = useState< DecisionResult[] >( [] );
	const [ message, setMessage ] = useState( '' );
	const [ decisions, setDecisions ] = useState( 0 );
	const review = reviewOf( list.filters.review );
	const load = useCallback(
		( signal: AbortSignal ) =>
			readQueue(
				{
					review,
					source: list.filters.source,
					search: list.filters.s,
					page: list.page,
				},
				signal
			),
		[ review, list.filters.source, list.filters.s, list.page ]
	);
	const queue = useRequest( load );
	const operation = useOperation();
	const detail = Number( list.filters.skill );
	function decide(
		rows: ProvenanceRow[],
		decision: 'approved' | 'rejected'
	) {
		void operation.run(
			( signal ) => reviewSkills( rows, decision, note.trim(), signal ),
			( outcome ) => {
				const failed = outcome.filter( ( entry ) => entry.error );
				setResults( failed );
				setSelected( [] );
				setDecisions( ( count ) => count + 1 );
				setMessage(
					sprintf(
						/* translators: %d: number of skills. */
						__( '%d skills reviewed.', 'ohmylms' ),
						outcome.length - failed.length
					)
				);
				queue.retry();
			}
		);
	}
	const rows = queue.data?.items ?? [];
	return (
		<section aria-labelledby="oml-ws-provenance">
			<h3 id="oml-ws-provenance">
				{ __( 'Provenance review', 'ohmylms' ) }
			</h3>
			<p>
				{ __(
					'Skills proposed by AI runs and other sources wait here. A pending or rejected skill can be used in course drafts but blocks publishing. Every decision is kept in the skill’s audit trail.',
					'ohmylms'
				) }
			</p>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ ( operation.error || queue.error ) && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error || queue.error }
				</Notice>
			) }
			{ results.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					<ul>
						{ results.map( ( entry ) => (
							<li key={ entry.id }>
								{ sprintf(
									/* translators: 1: skill ID, 2: reason. */
									__( 'Skill %1$d: %2$s', 'ohmylms' ),
									entry.id,
									entry.error ?? ''
								) }
							</li>
						) ) }
					</ul>
				</Notice>
			) }
			<TextareaControl
				label={ __( 'Note for the audit trail (optional)', 'ohmylms' ) }
				value={ note }
				onChange={ setNote }
				maxLength={ 500 }
			/>
			<ListTable
				caption={ __( 'Skills to review', 'ohmylms' ) }
				busy={ queue.loading || operation.busy }
				views={ [
					...REVIEW_STATES.map( ( state ) => ( {
						key: state,
						label: reviewLabel( state ),
						count:
							'pending' === state || 'rejected' === state
								? queue.data?.counts[ state ]
								: undefined,
						current: state === review,
						onSelect: () =>
							list.setFilters( {
								...list.filters,
								review: state,
								skill: '',
							} ),
					} ) ),
					{
						key: 'all',
						label: __( 'All', 'ohmylms' ),
						current: 'all' === review,
						onSelect: () =>
							list.setFilters( {
								...list.filters,
								review: 'all',
								skill: '',
							} ),
					},
				] }
				search={ {
					label: __( 'Search skills', 'ohmylms' ),
					value: search,
					maxLength: 200,
					onChange: setSearch,
				} }
				filters={
					<ListTableSelect
						label={ __( 'Source', 'ohmylms' ) }
						value={ list.filters.source }
						options={ [
							{
								value: '',
								label: __( 'All sources', 'ohmylms' ),
							},
							...( queue.data?.sources ?? [] ).map(
								( source ) => ( {
									value: source,
									label: sourceLabel( source, null ),
								} )
							),
						] }
						onChange={ ( source ) =>
							list.setFilters( { ...list.filters, source } )
						}
					/>
				}
				onFilter={ () =>
					list.setFilters( { ...list.filters, s: search.trim() } )
				}
				rows={ rows }
				rowKey={ ( row ) => row.id }
				rowLabel={ ( row ) => row.name }
				isSelectable={ ( row ) =>
					'pending' === row.provenance.review ||
					'rejected' === row.provenance.review
				}
				selected={ selected }
				onSelectedChange={ setSelected }
				bulkActions={ {
					actions: [
						{
							value: 'approved',
							label: __( 'Approve', 'ohmylms' ),
						},
						{ value: 'rejected', label: __( 'Reject', 'ohmylms' ) },
					],
					onApply: ( action, _keys, chosen ) =>
						decide( chosen, action as 'approved' | 'rejected' ),
				} }
				pagination={ {
					page: list.page,
					totalPages: Math.max(
						1,
						Math.ceil( ( queue.data?.total ?? 0 ) / 30 )
					),
					totalItems: queue.data?.total ?? 0,
					onChange: list.setPage,
				} }
				emptyMessage={ __( 'Nothing to review here.', 'ohmylms' ) }
				columns={ [
					{
						id: 'name',
						label: __( 'Skill', 'ohmylms' ),
						primary: true,
						render: ( row ) => (
							<>
								<strong>
									{ row.code
										? `${ row.code } ${ row.name }`
										: row.name }
								</strong>
								<br />
								<small>
									{ row.shared
										? row.subject ||
											__( 'Shared skill', 'ohmylms' )
										: __( 'Objective', 'ohmylms' ) }
								</small>
							</>
						),
					},
					{
						id: 'source',
						label: __( 'Came from', 'ohmylms' ),
						render: ( row ) => (
							<>
								{ sourceLabel(
									row.provenance.source,
									row.provenance.confidence
								) }
								{ row.provenance.ref && (
									<>
										<br />
										<small>{ row.provenance.ref }</small>
									</>
								) }
							</>
						),
					},
					{
						id: 'model',
						label: __( 'Model', 'ohmylms' ),
						render: ( row ) => row.provenance.model || '—',
					},
					{
						id: 'created',
						label: __( 'Created', 'ohmylms' ),
						render: ( row ) =>
							[
								row.provenance.createdBy.name,
								row.provenance.createdAt,
							]
								.filter( Boolean )
								.join( ', ' ) || '—',
					},
					{
						id: 'review',
						label: __( 'Review', 'ohmylms' ),
						render: ( row ) => (
							<>
								{ reviewLabel( row.provenance.review ) }
								{ row.lastAudit && (
									<>
										<br />
										<small>
											{ [
												row.lastAudit.by.name,
												row.lastAudit.at,
											]
												.filter( Boolean )
												.join( ', ' ) }
										</small>
									</>
								) }
							</>
						),
					},
				] }
				rowActions={ ( row ) => [
					{
						key: 'details',
						label: __( 'Details and audit', 'ohmylms' ),
						onClick: () =>
							list.setFilters( {
								...list.filters,
								skill: String( row.id ),
							} ),
					},
					'approved' !== row.provenance.review && {
						// Core list-table CSS hides `.row-actions .approve` (a comments rule).
						key: 'approve-skill',
						label: __( 'Approve', 'ohmylms' ),
						disabled: operation.busy,
						onClick: () => decide( [ row ], 'approved' ),
					},
					'rejected' !== row.provenance.review && {
						key: 'reject-skill',
						label: __( 'Reject', 'ohmylms' ),
						destructive: true,
						disabled: operation.busy,
						onClick: () => decide( [ row ], 'rejected' ),
					},
				] }
			/>
			{ Number.isSafeInteger( detail ) && detail > 0 && (
				<ProvenanceDetails
					key={ `${ detail }-${ decisions }` }
					id={ detail }
					onClose={ () =>
						list.setFilters( { ...list.filters, skill: '' } )
					}
				/>
			) }
		</section>
	);
}

function actionLabel( action: string ) {
	return (
		{
			approved: __( 'Approved', 'ohmylms' ),
			rejected: __( 'Rejected', 'ohmylms' ),
			archived: __( 'Archived', 'ohmylms' ),
			restored: __( 'Restored', 'ohmylms' ),
		}[ action ] ?? action
	);
}

function ProvenanceDetails( {
	id,
	onClose,
}: {
	id: number;
	onClose: () => void;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) => readProvenance( id, signal ),
		[ id ]
	);
	const request = useRequest( load );
	if ( request.loading ) {
		return <p role="status">{ __( 'Loading details…', 'ohmylms' ) }</p>;
	}
	if ( ! request.data ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ request.error }
			</Notice>
		);
	}
	const skill = request.data;
	return (
		<section aria-labelledby="oml-ws-provenance-detail">
			<h4 id="oml-ws-provenance-detail">{ skill.name }</h4>
			<Button variant="link" onClick={ onClose }>
				{ __( 'Close details', 'ohmylms' ) }
			</Button>
			<p>
				{ sprintf(
					/* translators: 1: source, 2: reference, 3: review state. */
					__( 'From %1$s %2$s · %3$s', 'ohmylms' ),
					sourceLabel(
						skill.provenance.source,
						skill.provenance.confidence
					),
					skill.provenance.ref,
					reviewLabel( skill.provenance.review )
				) }
			</p>
			<WidefatTable caption={ __( 'Audit trail', 'ohmylms' ) }>
				<thead>
					<tr>
						<th scope="col">{ __( 'When', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Decision', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'By', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Note', 'ohmylms' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ ! skill.audit.length && (
						<tr>
							<td colSpan={ 4 }>
								{ __(
									'No decisions recorded yet.',
									'ohmylms'
								) }
							</td>
						</tr>
					) }
					{ skill.audit.map( ( entry, index ) => (
						<tr key={ index }>
							<td>{ entry.at }</td>
							<td>
								{ actionLabel( entry.action ) }
								{ entry.previous &&
									` (${ sprintf(
										/* translators: %s: previous review state. */
										__( 'was %s', 'ohmylms' ),
										reviewLabel( entry.previous )
									) })` }
							</td>
							<td>{ entry.by.name || '—' }</td>
							<td>{ entry.note || '—' }</td>
						</tr>
					) ) }
				</tbody>
			</WidefatTable>
			<WidefatTable
				caption={ __( 'Where its mappings came from', 'ohmylms' ) }
			>
				<thead>
					<tr>
						<th scope="col">{ __( 'Mapped skill', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Relation', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Came from', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'By', 'ohmylms' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ ! skill.mappings.length && (
						<tr>
							<td colSpan={ 4 }>
								{ __( 'No mappings.', 'ohmylms' ) }
							</td>
						</tr>
					) }
					{ skill.mappings.map( ( mapping ) => (
						<tr key={ mapping.id }>
							<th scope="row">{ mapping.other.name }</th>
							<td>{ relationLabel( mapping.relation ) }</td>
							<td>
								{ sourceLabel(
									mapping.source,
									mapping.confidence
								) }
							</td>
							<td>{ mapping.createdBy.name || '—' }</td>
						</tr>
					) ) }
				</tbody>
			</WidefatTable>
		</section>
	);
}
