import { ListTable } from '../../../ui/ListTable';
import { Button, Notice, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, Navigate, useSearchParams } from 'react-router-dom';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	isStale,
	MAX_SOURCES,
	readGroups,
	saveGroup,
	type ComparisonGroups,
} from './compare';
import { CompareRoadmaps } from './CompareRoadmaps';
import { CompareSharedSkills } from './CompareSharedSkills';
import { SourcePicker } from './SourcePicker';
import type { WorkspaceSection } from './workspace';

const TABS = [ 'groups', 'roadmaps', 'shared' ] as const;
type Tab = ( typeof TABS )[ number ];

/** Former `?tab=` values of the skill workspace sections, now Content Hub › Skills sections. */
const WORKSPACE_TABS: Readonly< Record< string, WorkspaceSection > > = {
	mappings: 'mappings',
	ai: 'ai-runs',
	placement: 'placement',
	provenance: 'provenance',
};

function tabLabel( tab: Tab ): string {
	return {
		groups: __( 'Comparison groups', 'ohmylms' ),
		roadmaps: __( 'Roadmaps', 'ohmylms' ),
		shared: __( 'Shared skills', 'ohmylms' ),
	}[ tab ];
}

/**
 * Syllabus comparison at `#/content-hub/curriculum/compare` (`?tab=groups|roadmaps|shared`).
 * The legacy `?roadmap=<id>` bookmark opens that roadmap in the syllabus editor; the former skill
 * workspace tabs (`?tab=mappings|ai|placement|provenance`) open their Skills section
 * with the rest of the query.
 */
export default function ComparePage() {
	const [ params ] = useSearchParams();
	const roadmap = Number( params.get( 'roadmap' ) );
	const requested = params.get( 'tab' ) ?? '';
	const workspace = Object.prototype.hasOwnProperty.call(
		WORKSPACE_TABS,
		requested
	)
		? WORKSPACE_TABS[ requested ]
		: undefined;
	if ( Number.isSafeInteger( roadmap ) && roadmap > 0 ) {
		return (
			<Navigate
				replace
				to={ `/content-hub/curriculum/syllabus/${ roadmap }` }
			/>
		);
	}
	// The learner audit was replaced by the Skill map, which shows one learner (or a class or school) on the map.
	if ( 'audit' === requested ) {
		return <Navigate replace to="/content-hub/curriculum/skill-map" />;
	}
	if ( workspace ) {
		const rest = new URLSearchParams( params );
		rest.delete( 'tab' );
		const search = rest.toString();
		return (
			<Navigate
				replace
				to={ `/content-hub/skills/${ workspace }${
					search ? `?${ search }` : ''
				}` }
			/>
		);
	}
	const tab: Tab = TABS.find( ( id ) => id === requested ) ?? 'groups';
	return <CompareScreen tab={ tab } />;
}

function CompareScreen( { tab }: { tab: Tab } ) {
	const groups = useRequest( readGroups );
	return (
		<>
			<p>
				<Link to="/curriculum">
					{ __( '← Curriculum', 'ohmylms' ) }
				</Link>
			</p>
			<h2>{ __( 'Compare syllabuses', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Shared skills connect objectives of different syllabuses. Group the syllabuses learners are measured against, build roadmaps from them, and review where skills and mappings came from. A learner’s mastery is on the Skill map.',
					'ohmylms'
				) }
			</p>
			<nav aria-label={ __( 'Comparison sections', 'ohmylms' ) }>
				<ul className="subsubsub">
					{ TABS.map( ( id, index ) => (
						<li key={ id } className={ id }>
							<Link
								to={ `/content-hub/curriculum/compare?tab=${ id }` }
								className={ id === tab ? 'current' : undefined }
								aria-current={ id === tab ? 'page' : undefined }
							>
								{ tabLabel( id ) }
							</Link>
							{ index < TABS.length - 1 ? ' | ' : null }
						</li>
					) ) }
				</ul>
			</nav>
			<br className="clear" />
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
			<ListTable
				caption={ __( 'Comparison groups', 'ohmylms' ) }
				footer={ false }
				rows={ subjects }
				rowKey={ ( name ) => name }
				rowLabel={ ( name ) => name }
				emptyMessage={ __( 'No comparison groups yet.', 'ohmylms' ) }
				columns={ [
					{
						id: 'subject',
						label: __( 'Subject', 'ohmylms' ),
						primary: true,
						render: ( name ) => (
							<strong className="row-title">{ name }</strong>
						),
					},
					{
						id: 'sources',
						label: __( 'Sources', 'ohmylms' ),
						render: ( name ) =>
							( groups.selected[ name ] ?? [] )
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
								.join( ', ' ),
					},
				] }
				rowActions={ ( name ) => [
					{
						key: 'edit',
						label: __( 'Edit', 'ohmylms' ),
						ariaLabel: sprintf(
							/* translators: %s: subject. */
							__( 'Edit %s', 'ohmylms' ),
							name
						),
						onClick: () => edit( name ),
					},
				] }
			/>
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
