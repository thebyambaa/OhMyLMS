import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useSearchParams } from 'react-router-dom';
import { ListTable } from '../../../ui/ListTable';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { readGroups } from './compare';
import {
	acceptConfident,
	isActiveRun,
	listRuns,
	listSuggestions,
	reviewSuggestions,
	runAction,
	startRun,
	type AiRun,
	type ReviewOutcome,
	type Suggestion,
} from './workspace';
import { ConfirmModal, relationLabel, reviewLabel } from './WorkspaceParts';

const STATUSES = [ 'pending', 'accepted', 'rejected', 'all' ] as const;
type Status = ( typeof STATUSES )[ number ];
const RELATIONS = [ 'equivalent', 'broader', 'narrower', 'related' ];
const CONFIDENT = 0.85;

function kindLabel( kind: string ) {
	return (
		{
			syllabus: __( 'Syllabus analysis', 'ohmylms' ),
			placement: __( 'Domain placement', 'ohmylms' ),
			links: __( 'Prerequisites and links', 'ohmylms' ),
			course: __( 'Course content', 'ohmylms' ),
			new_skill: __( 'New shared skill', 'ohmylms' ),
			mapping: __( 'Mapping', 'ohmylms' ),
			link: __( 'Skill link', 'ohmylms' ),
			course_skill: __( 'Course skill', 'ohmylms' ),
		}[ kind ] ?? kind
	);
}
function statusLabel( status: string ) {
	return (
		{
			queued: __( 'Queued', 'ohmylms' ),
			running: __( 'Running', 'ohmylms' ),
			done: __( 'Done', 'ohmylms' ),
			cancelled: __( 'Cancelled', 'ohmylms' ),
		}[ status ] ?? reviewLabel( status )
	);
}

/**
 * The AI skill analysis runs (`?run=` opens one for review). Runs use the AI connection configured in
 * Settings, through the existing analysis service; this screen adds no other request.
 */
export function AiRunsPanel() {
	const [ params, setParams ] = useSearchParams();
	const run = Number( params.get( 'run' ) );
	const syllabus = Number( params.get( 'syllabus' ) ) || 0;
	const groups = useRequest( readGroups );
	const load = useCallback(
		( signal: AbortSignal ) => listRuns( syllabus, signal ),
		[ syllabus ]
	);
	const runs = useRequest( load );
	const operation = useOperation();
	const [ message, setMessage ] = useState( '' );
	const syllabuses = ( groups.data?.items ?? [] ).filter(
		( item ) => 'syllabus' === item.item_type
	);
	function open( id: number | null ) {
		const next = new URLSearchParams( params );
		if ( id ) {
			next.set( 'run', String( id ) );
		} else {
			next.delete( 'run' );
		}
		setParams( next );
	}
	function act( item: AiRun, action: 'step' | 'cancel' ) {
		void operation.run(
			( signal ) => runAction( item.id, action, signal ),
			( updated ) => {
				setMessage(
					'cancel' === action
						? __( 'Analysis stopped.', 'ohmylms' )
						: sprintf(
								/* translators: 1: processed, 2: total. */
								__( 'Processed %1$d of %2$d.', 'ohmylms' ),
								updated.processed,
								updated.total
							)
				);
				runs.retry();
			}
		);
	}
	return (
		<section aria-labelledby="oml-ws-ai">
			<h3 id="oml-ws-ai">{ __( 'AI runs', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'AI analysis places objectives in domains and levels and suggests mappings and new shared skills. Nothing changes until a person accepts a suggestion. Only curriculum text is sent, through the AI connection in Settings.',
					'ohmylms'
				) }
			</p>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			<StartRun
				syllabuses={ syllabuses }
				subjects={ Object.keys( groups.data?.selected ?? {} ) }
				onStarted={ ( started ) => {
					setMessage(
						sprintf(
							/* translators: %d: run ID. */
							__( 'Analysis %d started.', 'ohmylms' ),
							started.id
						)
					);
					runs.retry();
				} }
			/>
			<SelectControl
				label={ __( 'Show runs of', 'ohmylms' ) }
				value={ String( syllabus ) }
				options={ [
					{ value: '0', label: __( 'All recent runs', 'ohmylms' ) },
					...syllabuses.map( ( item ) => ( {
						value: String( item.id ),
						label: item.name,
					} ) ),
				] }
				onChange={ ( value ) => {
					const next = new URLSearchParams( params );
					next.delete( 'run' );
					if ( '0' === value ) {
						next.delete( 'syllabus' );
					} else {
						next.set( 'syllabus', value );
					}
					setParams( next );
				} }
			/>
			{ runs.error && (
				<Notice status="error" isDismissible={ false }>
					{ runs.error }{ ' ' }
					<Button variant="link" onClick={ runs.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			<ListTable
				caption={ __( 'AI runs', 'ohmylms' ) }
				footer={ false }
				busy={ runs.loading }
				rows={ runs.data ?? [] }
				rowKey={ ( item ) => item.id }
				emptyMessage={
					runs.loading
						? __( 'Loading runs…', 'ohmylms' )
						: __( 'No AI runs yet.', 'ohmylms' )
				}
				columns={ [
					{
						id: 'run',
						label: __( 'Run', 'ohmylms' ),
						primary: true,
						render: ( item ) => (
							<strong className="row-title">
								{ sprintf(
									/* translators: 1: run ID, 2: kind. */
									__( '#%1$d %2$s', 'ohmylms' ),
									item.id,
									kindLabel( item.kind )
								) }
							</strong>
						),
					},
					{
						id: 'target',
						label: __( 'Of', 'ohmylms' ),
						render: ( item ) => item.syllabus || item.subject,
					},
					{
						id: 'status',
						label: __( 'Status', 'ohmylms' ),
						render: ( item ) => (
							<>
								{ statusLabel( item.status ) }
								{ item.error && (
									<>
										<br />
										<small>{ item.error }</small>
									</>
								) }
							</>
						),
					},
					{
						id: 'progress',
						label: __( 'Progress', 'ohmylms' ),
						render: ( item ) =>
							sprintf(
								/* translators: 1: processed, 2: total, 3: failures. */
								__( '%1$d of %2$d (%3$d failed)', 'ohmylms' ),
								item.processed,
								item.total,
								item.failures
							),
					},
					{
						id: 'suggestions',
						label: __( 'Suggestions', 'ohmylms' ),
						render: ( item ) =>
							sprintf(
								/* translators: 1: pending, 2: accepted, 3: rejected. */
								__(
									'%1$d pending, %2$d accepted, %3$d rejected',
									'ohmylms'
								),
								item.pending,
								item.accepted,
								item.rejected
							),
					},
					{
						id: 'model',
						label: __( 'Model', 'ohmylms' ),
						render: ( item ) => item.model || '—',
					},
				] }
				rowActions={ ( item ) => [
					{
						key: 'review',
						label: __( 'Review', 'ohmylms' ),
						onClick: () => open( item.id ),
					},
					isActiveRun( item ) && {
						key: 'step',
						label: __( 'Process next slice', 'ohmylms' ),
						disabled: operation.busy,
						onClick: () => act( item, 'step' ),
					},
					isActiveRun( item ) && {
						key: 'cancel',
						label: __( 'Stop', 'ohmylms' ),
						destructive: true,
						disabled: operation.busy,
						onClick: () => act( item, 'cancel' ),
					},
				] }
			/>
			{ Number.isSafeInteger( run ) && run > 0 && (
				<RunReview
					key={ run }
					run={ run }
					onClose={ () => open( null ) }
					onChanged={ runs.retry }
				/>
			) }
		</section>
	);
}

function StartRun( {
	syllabuses,
	subjects,
	onStarted,
}: {
	syllabuses: { id: number; name: string }[];
	subjects: string[];
	onStarted: ( run: AiRun ) => void;
} ) {
	const [ kind, setKind ] = useState< 'syllabus' | 'placement' | 'links' >(
		'syllabus'
	);
	const [ syllabus, setSyllabus ] = useState( '' );
	const [ subject, setSubject ] = useState( '' );
	const [ includeMapped, setIncludeMapped ] = useState( false );
	const [ problem, setProblem ] = useState( '' );
	const operation = useOperation();
	function start() {
		if ( 'syllabus' === kind && ! Number( syllabus ) ) {
			setProblem( __( 'Choose a syllabus.', 'ohmylms' ) );
			return;
		}
		if ( 'syllabus' !== kind && ! subject.trim() ) {
			setProblem( __( 'Enter a subject.', 'ohmylms' ) );
			return;
		}
		setProblem( '' );
		void operation.run(
			( signal ) =>
				startRun(
					'syllabus' === kind
						? {
								kind,
								syllabus_id: Number( syllabus ),
								include_mapped: includeMapped,
							}
						: { kind, subject: subject.trim() },
					signal
				),
			onStarted
		);
	}
	return (
		<form
			noValidate
			aria-label={ __( 'Start an AI run', 'ohmylms' ) }
			onSubmit={ ( event ) => {
				event.preventDefault();
				start();
			} }
		>
			<SelectControl
				label={ __( 'Analysis', 'ohmylms' ) }
				value={ kind }
				options={ [
					{ value: 'syllabus', label: kindLabel( 'syllabus' ) },
					{ value: 'placement', label: kindLabel( 'placement' ) },
					{ value: 'links', label: kindLabel( 'links' ) },
				] }
				onChange={ ( value ) => setKind( value as typeof kind ) }
			/>
			{ 'syllabus' === kind ? (
				<>
					<SelectControl
						label={ __( 'Syllabus to analyse', 'ohmylms' ) }
						value={ syllabus }
						options={ [
							{
								value: '',
								label: __( 'Choose a syllabus', 'ohmylms' ),
							},
							...syllabuses.map( ( item ) => ( {
								value: String( item.id ),
								label: item.name,
							} ) ),
						] }
						onChange={ setSyllabus }
					/>
					<CheckboxControl
						label={ __(
							'Include objectives already mapped',
							'ohmylms'
						) }
						checked={ includeMapped }
						onChange={ setIncludeMapped }
					/>
				</>
			) : (
				<TextControl
					label={ __( 'Subject', 'ohmylms' ) }
					value={ subject }
					maxLength={ 100 }
					list="oml-ws-subjects"
					onChange={ setSubject }
				/>
			) }
			<datalist id="oml-ws-subjects">
				{ subjects.map( ( name ) => (
					<option key={ name } value={ name } />
				) ) }
			</datalist>
			{ ( problem || operation.error ) && (
				<Notice status="error" isDismissible={ false }>
					{ problem || operation.error }
				</Notice>
			) }
			<Button
				type="submit"
				variant="primary"
				isBusy={ operation.busy }
				disabled={ operation.busy }
			>
				{ __( 'Start analysis', 'ohmylms' ) }
			</Button>
		</form>
	);
}

function suggestionText( item: Suggestion ) {
	if ( 'placement' === item.kind && item.placement ) {
		return sprintf(
			/* translators: 1: shared skill, 2: domain, 3: first level, 4: last level. */
			__( '%1$s → %2$s, levels %3$d–%4$d', 'ohmylms' ),
			item.shared?.name ?? '',
			item.placement.domain,
			item.placement.gradeFrom,
			item.placement.gradeTo
		);
	}
	if ( 'new_skill' === item.kind ) {
		return sprintf(
			/* translators: %s: proposed skill name. */
			__( 'New shared skill “%s”', 'ohmylms' ),
			item.proposal?.name ?? ''
		);
	}
	return item.shared?.name ?? item.proposal?.name ?? '—';
}

function RunReview( {
	run,
	onClose,
	onChanged,
}: {
	run: number;
	onClose: () => void;
	onChanged: () => void;
} ) {
	const [ status, setStatus ] = useState< Status >( 'pending' );
	const [ page, setPage ] = useState( 1 );
	const [ selected, setSelected ] = useState< string[] >( [] );
	const [ relations, setRelations ] = useState< Record< number, string > >(
		{}
	);
	const [ outcomes, setOutcomes ] = useState< ReviewOutcome[] >( [] );
	const [ confirming, setConfirming ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const load = useCallback(
		( signal: AbortSignal ) => listSuggestions( run, status, page, signal ),
		[ run, status, page ]
	);
	const request = useRequest( load );
	const operation = useOperation();
	function decide( ids: number[], action: 'accept' | 'reject' ) {
		const overrides: Record< number, { relation: string } > = {};
		for ( const id of ids ) {
			if ( relations[ id ] ) {
				overrides[ id ] = { relation: relations[ id ] };
			}
		}
		void operation.run(
			( signal ) => reviewSuggestions( ids, action, overrides, signal ),
			( result ) => {
				setOutcomes( result.filter( ( entry ) => ! entry.ok ) );
				setSelected( [] );
				setMessage(
					sprintf(
						/* translators: %d: number of suggestions. */
						__( '%d suggestions reviewed.', 'ohmylms' ),
						result.filter( ( entry ) => entry.ok ).length
					)
				);
				request.retry();
				onChanged();
			}
		);
	}
	const items = request.data?.items ?? [];
	const pages = Math.max( 1, Math.ceil( ( request.data?.total ?? 0 ) / 30 ) );
	return (
		<section aria-labelledby="oml-ws-run">
			<h4 id="oml-ws-run">
				{ sprintf(
					/* translators: %d: run ID. */
					__( 'Suggestions of run #%d', 'ohmylms' ),
					run
				) }
			</h4>
			<Button variant="link" onClick={ onClose }>
				{ __( 'Close review', 'ohmylms' ) }
			</Button>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ ( operation.error || request.error ) && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error || request.error }
				</Notice>
			) }
			{ outcomes.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					<ul>
						{ outcomes.map(
							( entry ) =>
								! entry.ok && (
									<li key={ entry.id }>
										{ sprintf(
											/* translators: 1: suggestion ID, 2: reason. */
											__( '#%1$d: %2$s', 'ohmylms' ),
											entry.id,
											entry.error
										) }
									</li>
								)
						) }
					</ul>
				</Notice>
			) }
			<Button
				variant="secondary"
				disabled={ operation.busy }
				onClick={ () => setConfirming( true ) }
			>
				{ sprintf(
					/* translators: %d: confidence percentage. */
					__( 'Accept all at %d%% or more', 'ohmylms' ),
					CONFIDENT * 100
				) }
			</Button>
			<ListTable
				caption={ __( 'AI suggestions', 'ohmylms' ) }
				busy={ request.loading || operation.busy }
				views={ STATUSES.map( ( id ) => ( {
					key: id,
					label:
						'all' === id
							? __( 'All', 'ohmylms' )
							: reviewLabel( id ),
					current: id === status,
					onSelect: () => {
						setStatus( id );
						setPage( 1 );
						setSelected( [] );
					},
				} ) ) }
				rows={ items }
				rowKey={ ( item ) => item.id }
				rowLabel={ ( item ) => suggestionText( item ) }
				isSelectable={ ( item ) => 'pending' === item.status }
				selected={ selected }
				onSelectedChange={ setSelected }
				bulkActions={ {
					actions: [
						{ value: 'accept', label: __( 'Accept', 'ohmylms' ) },
						{ value: 'reject', label: __( 'Reject', 'ohmylms' ) },
					],
					onApply: ( action, keys ) =>
						decide(
							keys.map( Number ),
							action as 'accept' | 'reject'
						),
				} }
				pagination={ {
					page,
					totalPages: pages,
					totalItems: request.data?.total ?? 0,
					onChange: setPage,
				} }
				emptyMessage={ __( 'No suggestions here.', 'ohmylms' ) }
				columns={ [
					{
						id: 'suggestion',
						label: __( 'Suggestion', 'ohmylms' ),
						primary: true,
						render: ( item ) => (
							<>
								<strong>{ suggestionText( item ) }</strong>
								<br />
								<small>{ kindLabel( item.kind ) }</small>
							</>
						),
					},
					{
						id: 'objective',
						label: __( 'Objective', 'ohmylms' ),
						render: ( item ) => item.objective?.name ?? '—',
					},
					{
						id: 'relation',
						label: __( 'Relation', 'ohmylms' ),
						render: ( item ) =>
							'mapping' === item.kind &&
							'pending' === item.status ? (
								<SelectControl
									label={ __( 'Relation', 'ohmylms' ) }
									hideLabelFromVision
									value={
										relations[ item.id ] ?? item.relation
									}
									options={ RELATIONS.map( ( value ) => ( {
										value,
										label: relationLabel( value ),
									} ) ) }
									onChange={ ( value ) =>
										setRelations( ( current ) => ( {
											...current,
											[ item.id ]: value,
										} ) )
									}
								/>
							) : (
								relationLabel( item.relation ) || '—'
							),
					},
					{
						id: 'confidence',
						label: __( 'Confidence', 'ohmylms' ),
						className: 'num',
						render: ( item ) =>
							`${ Math.round( item.confidence * 100 ) }%`,
					},
					{
						id: 'reason',
						label: __( 'Reason', 'ohmylms' ),
						render: ( item ) => item.reason,
					},
					{
						id: 'status',
						label: __( 'Status', 'ohmylms' ),
						render: ( item ) => reviewLabel( item.status ),
					},
				] }
				rowActions={ ( item ) =>
					'pending' === item.status
						? [
								{
									key: 'accept',
									label: __( 'Accept', 'ohmylms' ),
									disabled: operation.busy,
									onClick: () =>
										decide( [ item.id ], 'accept' ),
								},
								{
									key: 'reject',
									label: __( 'Reject', 'ohmylms' ),
									destructive: true,
									disabled: operation.busy,
									onClick: () =>
										decide( [ item.id ], 'reject' ),
								},
							]
						: []
				}
			/>
			{ confirming && (
				<ConfirmModal
					title={ __( 'Accept confident suggestions?', 'ohmylms' ) }
					confirm={ __( 'Accept them', 'ohmylms' ) }
					destructive={ false }
					busy={ operation.busy }
					error={ operation.error }
					onClose={ () => setConfirming( false ) }
					onConfirm={ () =>
						void operation.run(
							( signal ) =>
								acceptConfident( run, CONFIDENT, signal ),
							( result ) => {
								setConfirming( false );
								setMessage(
									sprintf(
										/* translators: 1: accepted, 2: failed. */
										__(
											'%1$d accepted, %2$d could not be applied.',
											'ohmylms'
										),
										result.accepted,
										result.failed
									)
								);
								request.retry();
								onChanged();
							}
						)
					}
				>
					<p>
						{ sprintf(
							/* translators: %d: confidence percentage. */
							__(
								'Every pending suggestion of this run at %d%% or more is applied: new skills are created, mappings saved and placements stored.',
								'ohmylms'
							),
							CONFIDENT * 100
						) }
					</p>
				</ConfirmModal>
			) }
		</section>
	);
}
