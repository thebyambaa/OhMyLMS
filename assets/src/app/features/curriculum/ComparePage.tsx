import { Table } from '../../../ui/Table';
import { Button, Notice, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, Navigate, useSearchParams } from 'react-router-dom';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	isStale,
	MAX_SOURCES,
	readAudit,
	readGroups,
	saveGroup,
	type AuditSubject,
	type ComparisonGroups,
} from './compare';
import { CompareRoadmaps } from './CompareRoadmaps';
import { CompareSharedSkills } from './CompareSharedSkills';
import { SourcePicker } from './SourcePicker';

const TABS = [ 'groups', 'roadmaps', 'shared', 'audit' ] as const;
type Tab = ( typeof TABS )[ number ];

function tabLabel( tab: Tab ): string {
	return {
		groups: __( 'Comparison groups', 'ohmylms' ),
		roadmaps: __( 'Roadmaps', 'ohmylms' ),
		shared: __( 'Shared skills', 'ohmylms' ),
		audit: __( 'Learner audit', 'ohmylms' ),
	}[ tab ];
}

/**
 * Syllabus comparison at `#/content-hub/curriculum/compare` (`?tab=groups|roadmaps|shared|audit`).
 * The legacy `?roadmap=<id>` bookmark opens that roadmap in the syllabus editor.
 */
export default function ComparePage() {
	const [ params, setParams ] = useSearchParams();
	const roadmap = Number( params.get( 'roadmap' ) );
	const tab: Tab =
		TABS.find( ( id ) => id === params.get( 'tab' ) ) ?? 'groups';
	const groups = useRequest( readGroups );
	if ( Number.isSafeInteger( roadmap ) && roadmap > 0 ) {
		return (
			<Navigate
				replace
				to={ `/content-hub/curriculum/syllabus/${ roadmap }` }
			/>
		);
	}
	return (
		<>
			<p>
				<Link to="/content-hub/curriculum">
					{ __( '← Curriculum', 'ohmylms' ) }
				</Link>
			</p>
			<h2>{ __( 'Compare syllabuses', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Shared skills connect objectives of different syllabuses. Group the syllabuses learners are measured against, build roadmaps from them and check one learner across a subject.',
					'ohmylms'
				) }
			</p>
			<nav aria-label={ __( 'Comparison sections', 'ohmylms' ) }>
				{ TABS.map( ( id ) => (
					<Button
						key={ id }
						variant={ id === tab ? 'primary' : 'tertiary' }
						aria-current={ id === tab ? 'page' : undefined }
						onClick={ () => setParams( { tab: id } ) }
					>
						{ tabLabel( id ) }
					</Button>
				) ) }
			</nav>
			{ groups.loading && (
				<p role="status">{ __( 'Loading syllabuses…', 'ohmylms' ) }</p>
			) }
			{ groups.error && (
				<Notice status="error" isDismissible={ false }>
					{ groups.error }{ ' ' }
					<Button variant="link" onClick={ groups.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ groups.data && 'groups' === tab && (
				<Groups initial={ groups.data } onReload={ groups.retry } />
			) }
			{ groups.data && 'roadmaps' === tab && (
				<CompareRoadmaps sources={ groups.data.items } />
			) }
			{ 'shared' === tab && <CompareSharedSkills /> }
			{ groups.data && 'audit' === tab && (
				<Audit groups={ groups.data } />
			) }
		</>
	);
}

function Groups( {
	initial,
	onReload,
}: {
	initial: ComparisonGroups;
	onReload: () => void;
} ) {
	const [ groups, setGroups ] = useState( initial );
	const [ subject, setSubject ] = useState( '' );
	const [ ids, setIds ] = useState< number[] >( [] );
	const [ problem, setProblem ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ stale, setStale ] = useState( false );
	const operation = useOperation();
	const names = new Map(
		groups.items.map( ( item ) => [ item.id, item.name ] )
	);

	function edit( name: string ) {
		setSubject( name );
		setIds( [ ...( groups.selected[ name ] ?? [] ) ] );
		setMessage( null );
		setProblem( null );
	}

	function save( remove = false ) {
		const value = subject.trim();
		if ( ! value || value.length > 100 ) {
			setProblem(
				__( 'Enter a subject of 1 to 100 characters.', 'ohmylms' )
			);
			return;
		}
		setProblem( null );
		setMessage( null );
		void operation.run(
			async ( signal ) => {
				try {
					return await saveGroup(
						value,
						remove ? [] : ids,
						groups.revision,
						signal
					);
				} catch ( failure ) {
					setStale( isStale( failure ) );
					throw failure;
				}
			},
			( result ) => {
				setStale( false );
				setGroups( result );
				setMessage(
					remove
						? __( 'Comparison group removed.', 'ohmylms' )
						: __( 'Comparison group saved.', 'ohmylms' )
				);
				if ( remove ) {
					setSubject( '' );
					setIds( [] );
				}
			}
		);
	}

	async function reloadKeepingEdits() {
		const fresh = await readGroups().catch( () => null );
		if ( fresh ) {
			setGroups( fresh );
			setStale( false );
			operation.clearError();
			setMessage(
				__(
					'Reloaded the latest groups. Your unsaved edits were kept.',
					'ohmylms'
				)
			);
		} else {
			onReload();
		}
	}

	const subjects = Object.keys( groups.selected ).sort();
	return (
		<section aria-labelledby="oml-compare-groups">
			<h3 id="oml-compare-groups">
				{ __( 'Comparison groups', 'ohmylms' ) }
			</h3>
			<p>
				{ __(
					'For each subject, the reviewed syllabuses and roadmaps learner progress is reported against. Saving publishes no course.',
					'ohmylms'
				) }
			</p>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ stale && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Someone changed the comparison groups after you opened them.',
						'ohmylms'
					) }{ ' ' }
					<Button
						variant="secondary"
						onClick={ () => void reloadKeepingEdits() }
					>
						{ __( 'Reload groups and keep my edits', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ operation.error && ! stale && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ subjects.length ? (
				<Table caption={ __( 'Comparison groups', 'ohmylms' ) }>
					<thead>
						<tr>
							<th scope="col">{ __( 'Subject', 'ohmylms' ) }</th>
							<th scope="col">{ __( 'Sources', 'ohmylms' ) }</th>
							<th scope="col">{ __( 'Actions', 'ohmylms' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ subjects.map( ( name ) => (
							<tr key={ name }>
								<th scope="row">{ name }</th>
								<td>
									{ ( groups.selected[ name ] ?? [] )
										.map(
											( id ) =>
												names.get( id ) ??
												sprintf(
													/* translators: %d: syllabus ID. */
													__(
														'Missing syllabus %d',
														'ohmylms'
													),
													id
												)
										)
										.join( ', ' ) }
								</td>
								<td>
									<Button
										variant="link"
										aria-label={ sprintf(
											/* translators: %s: subject. */
											__( 'Edit %s', 'ohmylms' ),
											name
										) }
										onClick={ () => edit( name ) }
									>
										{ __( 'Edit', 'ohmylms' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</Table>
			) : (
				<p>{ __( 'No comparison groups yet.', 'ohmylms' ) }</p>
			) }
			<form
				noValidate
				aria-label={ __( 'Comparison group', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					save();
				} }
			>
				<TextControl
					label={ __( 'Subject', 'ohmylms' ) }
					value={ subject }
					maxLength={ 100 }
					help={ problem ?? undefined }
					onChange={ setSubject }
				/>
				<SourcePicker
					legend={ sprintf(
						/* translators: %d: maximum sources. */
						__( 'Sources (up to %d)', 'ohmylms' ),
						MAX_SOURCES
					) }
					sources={ groups.items }
					selected={ ids }
					onChange={ setIds }
				/>
				<Button
					type="submit"
					variant="primary"
					disabled={ operation.busy || stale || ! ids.length }
					isBusy={ operation.busy }
				>
					{ __( 'Save group', 'ohmylms' ) }
				</Button>{ ' ' }
				{ subject.trim() && groups.selected[ subject.trim() ] && (
					<Button
						variant="tertiary"
						isDestructive
						disabled={ operation.busy || stale }
						onClick={ () => save( true ) }
					>
						{ __( 'Remove group', 'ohmylms' ) }
					</Button>
				) }
			</form>
		</section>
	);
}

function Audit( { groups }: { groups: ComparisonGroups } ) {
	const [ value, setValue ] = useState( '' );
	const [ problem, setProblem ] = useState< string | null >( null );
	const [ result, setResult ] = useState< AuditSubject[] | null >( null );
	const operation = useOperation();
	const configured = Object.keys( groups.selected ).length > 0;

	function load() {
		const id = Number( value.trim() );
		if ( ! Number.isSafeInteger( id ) || id < 1 ) {
			setProblem( __( 'Enter the learner’s user ID.', 'ohmylms' ) );
			return;
		}
		setProblem( null );
		setResult( null );
		void operation.run( ( signal ) => readAudit( id, signal ), setResult );
	}

	return (
		<section aria-labelledby="oml-compare-audit">
			<h3 id="oml-compare-audit">{ __( 'Learner audit', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Shared mastery and syllabus coverage of one learner in every configured subject. Unassessed is not failed mastery.',
					'ohmylms'
				) }
			</p>
			{ ! configured && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Add a comparison group first; the audit reports configured subjects only.',
						'ohmylms'
					) }
				</Notice>
			) }
			<form
				noValidate
				role="search"
				aria-label={ __( 'Learner audit', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					load();
				} }
			>
				<TextControl
					label={ __( 'Learner user ID', 'ohmylms' ) }
					value={ value }
					inputMode="numeric"
					help={ problem ?? undefined }
					onChange={ setValue }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ operation.busy }
					isBusy={ operation.busy }
				>
					{ __( 'Show audit', 'ohmylms' ) }
				</Button>
			</form>
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ result && ! result.length && (
				<p>{ __( 'No configured subjects to report.', 'ohmylms' ) }</p>
			) }
			{ result?.map( ( subject ) => (
				<section key={ subject.subject }>
					<h4>{ subject.subject }</h4>
					<Table
						caption={ sprintf(
							/* translators: %s: subject. */
							__( 'Shared mastery in %s', 'ohmylms' ),
							subject.subject
						) }
					>
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Shared skill', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Level', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Evidence answers', 'ohmylms' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ subject.shared.map( ( skill ) => (
								<tr key={ skill.id }>
									<th scope="row">{ skill.name }</th>
									<td>
										{ skill.level ||
											__( 'Not assessed', 'ohmylms' ) }
									</td>
									<td>{ skill.evidence }</td>
								</tr>
							) ) }
						</tbody>
					</Table>
					<ul>
						{ subject.syllabuses.map( ( syllabus ) => (
							<li key={ syllabus.id }>
								{ sprintf(
									/* translators: 1: syllabus, 2: equivalent, 3: related only, 4: unmapped, 5: unassessed, 6: developing. */
									__(
										'%1$s: %2$d equivalent, %3$d related only, %4$d unmapped; %5$d unassessed and %6$d developing objectives.',
										'ohmylms'
									),
									syllabus.name,
									syllabus.equivalent,
									syllabus.related_only,
									syllabus.unmapped,
									syllabus.unassessed,
									syllabus.developing
								) }
							</li>
						) ) }
					</ul>
				</section>
			) ) }
		</section>
	);
}
