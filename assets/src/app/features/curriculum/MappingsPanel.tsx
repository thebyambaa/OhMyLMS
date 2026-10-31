import { Button, Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useSearchParams } from 'react-router-dom';
import { WidefatTable } from '../../components/WidefatTable';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	isConflict,
	readCombined,
	removeCombinedGroup,
	removeMapping,
	type CombinedView,
	type MappingRow,
	type PostRef,
} from './workspace';
import {
	ConfirmModal,
	contentRoute,
	relationLabel,
	reviewLabel,
	SkillChooser,
	sourceLabel,
} from './WorkspaceParts';

type Removal =
	| { kind: 'mapping'; row: MappingRow }
	| { kind: 'group'; uuid: string; rows: MappingRow[] };

/** One view of a skill's mappings across syllabuses, courses, content and questions (`?skill=`). */
export function MappingsPanel() {
	const [ params, setParams ] = useSearchParams();
	const skill = Number( params.get( 'skill' ) );
	const chosen = Number.isSafeInteger( skill ) && skill > 0 ? skill : 0;
	return (
		<section aria-labelledby="oml-ws-mappings">
			<h3 id="oml-ws-mappings">
				{ __( 'Combined mappings', 'ohmylms' ) }
			</h3>
			<p>
				{ __(
					'Everything one skill is mapped to or used by: syllabus objectives in both directions, combined coverage groups, courses, lessons, quizzes, assignments and questions.',
					'ohmylms'
				) }
			</p>
			<SkillChooser
				label={ __( 'Find a skill', 'ohmylms' ) }
				onChoose={ ( found ) => {
					const next = new URLSearchParams( params );
					next.set( 'skill', String( found.id ) );
					setParams( next );
				} }
			/>
			{ chosen > 0 && <CombinedSkill key={ chosen } skill={ chosen } /> }
		</section>
	);
}

function CombinedSkill( { skill }: { skill: number } ) {
	const load = useCallback(
		( signal: AbortSignal ) => readCombined( skill, signal ),
		[ skill ]
	);
	const request = useRequest( load );
	const operation = useOperation();
	const [ removal, setRemoval ] = useState< Removal | null >( null );
	const [ message, setMessage ] = useState( '' );
	const [ stale, setStale ] = useState( false );
	if ( request.loading ) {
		return <p role="status">{ __( 'Loading mappings…', 'ohmylms' ) }</p>;
	}
	if ( request.error || ! request.data ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ request.error }{ ' ' }
				<Button variant="link" onClick={ request.retry }>
					{ __( 'Try again', 'ohmylms' ) }
				</Button>
			</Notice>
		);
	}
	const view: CombinedView = request.data;
	function remove( target: Removal ) {
		void operation.run(
			async ( signal ) => {
				try {
					if ( 'mapping' === target.kind ) {
						await removeMapping( target.row, signal );
					} else {
						await removeCombinedGroup(
							target.uuid,
							target.rows.map( ( row ) => ( {
								specific_id: row.specific.id,
								expected_revision: row.revision,
							} ) ),
							signal
						);
					}
				} catch ( failure ) {
					setStale( isConflict( failure ) );
					throw failure;
				}
			},
			() => {
				setRemoval( null );
				setStale( false );
				setMessage(
					'mapping' === target.kind
						? __( 'Mapping removed.', 'ohmylms' )
						: __( 'Combined group removed.', 'ohmylms' )
				);
				request.retry();
			}
		);
	}
	const individual = view.mappings.filter( ( row ) => ! row.groupUuid );
	return (
		<>
			<h4>
				{ view.skill.code
					? `${ view.skill.code } ${ view.skill.name }`
					: view.skill.name }
			</h4>
			<p>
				{ [
					view.skill.shared
						? sprintf(
								/* translators: %s: subject. */
								__( 'Shared skill in %s', 'ohmylms' ),
								view.skill.subject ||
									__( 'no subject', 'ohmylms' )
							)
						: __( 'Syllabus objective', 'ohmylms' ),
					sourceLabel( view.skill.source, null ),
					reviewLabel( view.skill.review ),
				].join( ' · ' ) }
			</p>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ view.placements.length > 0 && (
				<>
					<h5>{ __( 'Placed in', 'ohmylms' ) }</h5>
					<ul>
						{ view.placements.map( ( place, index ) => (
							<li key={ index }>
								<Link
									to={ `/content-hub/curriculum/syllabus/${ place.syllabusId }` }
								>
									{ place.syllabus }
								</Link>
								{ ` › ${ place.item } › ${ place.group }` }
							</li>
						) ) }
					</ul>
				</>
			) }
			<WidefatTable caption={ __( 'Mappings of this skill', 'ohmylms' ) }>
				<thead>
					<tr>
						<th scope="col">{ __( 'Objective', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Shared skill', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Relation', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Came from', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Syllabuses', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Actions', 'ohmylms' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ ! view.mappings.length && (
						<tr>
							<td colSpan={ 6 }>
								{ __( 'No mappings yet.', 'ohmylms' ) }
							</td>
						</tr>
					) }
					{ view.mappings.map( ( row ) => (
						<tr key={ row.id }>
							<th scope="row">{ row.specific.name }</th>
							<td>{ row.shared.name }</td>
							<td>
								{ relationLabel( row.relation ) }
								{ row.note && (
									<>
										<br />
										<small>{ row.note }</small>
									</>
								) }
							</td>
							<td>
								{ sourceLabel( row.source, row.confidence ) }
							</td>
							<td>
								{ row.syllabuses
									.map( ( entry ) => entry.name )
									.join( ', ' ) || '—' }
							</td>
							<td>
								{ row.groupUuid ? (
									__( 'In a combined group', 'ohmylms' )
								) : (
									<Button
										variant="link"
										isDestructive
										disabled={ operation.busy }
										onClick={ () => {
											operation.clearError();
											setRemoval( {
												kind: 'mapping',
												row,
											} );
										} }
										aria-label={ sprintf(
											/* translators: 1: objective, 2: shared skill. */
											__(
												'Remove mapping %1$s to %2$s',
												'ohmylms'
											),
											row.specific.name,
											row.shared.name
										) }
									>
										{ __( 'Remove', 'ohmylms' ) }
									</Button>
								) }
							</td>
						</tr>
					) ) }
				</tbody>
			</WidefatTable>
			<p>
				{ sprintf(
					/* translators: 1: individual mappings, 2: combined groups. */
					__(
						'%1$d individual mappings and %2$d combined groups.',
						'ohmylms'
					),
					individual.length,
					view.groups.length
				) }
			</p>
			{ view.groups.map( ( group ) => (
				<section
					key={ group.uuid }
					aria-label={ __( 'Combined group', 'ohmylms' ) }
				>
					<h5>
						{ sprintf(
							/* translators: 1: source syllabus, 2: shared skill. */
							__( '%1$s together cover %2$s', 'ohmylms' ),
							group.source,
							group.shared.name
						) }
					</h5>
					<ul>
						{ group.members.map( ( member ) => (
							<li key={ member.id }>{ member.name }</li>
						) ) }
					</ul>
					<Button
						variant="secondary"
						isDestructive
						disabled={ operation.busy }
						onClick={ () => {
							operation.clearError();
							setRemoval( {
								kind: 'group',
								uuid: group.uuid,
								rows: view.mappings.filter(
									( row ) => row.groupUuid === group.uuid
								),
							} );
						} }
					>
						{ __( 'Remove combined group', 'ohmylms' ) }
					</Button>
				</section>
			) ) }
			<Usage view={ view } />
			{ removal && (
				<ConfirmModal
					title={
						'mapping' === removal.kind
							? __( 'Remove this mapping?', 'ohmylms' )
							: __( 'Remove this combined group?', 'ohmylms' )
					}
					confirm={ __( 'Remove', 'ohmylms' ) }
					busy={ operation.busy }
					error={
						stale
							? __(
									'This mapping changed after you opened it. Close, reload and review it again.',
									'ohmylms'
								)
							: operation.error
					}
					onConfirm={ () => remove( removal ) }
					onClose={ () => {
						setRemoval( null );
						if ( stale ) {
							setStale( false );
							request.retry();
						}
					} }
				>
					<p>
						{ __(
							'Both skills, their content and learner evidence stay. Only the connection is removed.',
							'ohmylms'
						) }
					</p>
				</ConfirmModal>
			) }
		</>
	);
}

function UsageList( {
	title,
	items,
	total,
	route,
	extra,
}: {
	title: string;
	items: PostRef[];
	total: number;
	route: ( id: number ) => string;
	extra?: ( item: PostRef ) => string;
} ) {
	return (
		<section aria-label={ title }>
			<h5>
				{ sprintf(
					/* translators: 1: list name, 2: count. */
					__( '%1$s (%2$d)', 'ohmylms' ),
					title,
					total
				) }
			</h5>
			{ ! items.length ? (
				<p>{ __( 'None.', 'ohmylms' ) }</p>
			) : (
				<ul>
					{ items.map( ( item ) => (
						<li key={ item.id }>
							<Link to={ route( item.id ) }>
								{ item.title ||
									sprintf(
										/* translators: %d: ID. */
										__( '#%d', 'ohmylms' ),
										item.id
									) }
							</Link>{ ' ' }
							<small>
								{ [ item.status, extra?.( item ) ]
									.filter( Boolean )
									.join( ' · ' ) }
							</small>
						</li>
					) ) }
				</ul>
			) }
			{ total > items.length && (
				<p>
					{ sprintf(
						/* translators: 1: shown, 2: total. */
						__( 'Showing %1$d of %2$d.', 'ohmylms' ),
						items.length,
						total
					) }
				</p>
			) }
		</section>
	);
}

function Usage( { view }: { view: CombinedView } ) {
	const usage: Record< string, string > = {
		draft: __( 'draft outline', 'ohmylms' ),
		published: __( 'published program', 'ohmylms' ),
		linked: __( 'linked', 'ohmylms' ),
	};
	return (
		<>
			<h4>{ __( 'Used by', 'ohmylms' ) }</h4>
			<UsageList
				title={ __( 'Courses', 'ohmylms' ) }
				items={ view.courses.items }
				total={ view.courses.total }
				route={ ( id ) => contentRoute( 'course', id ) }
				extra={ ( item ) =>
					usage[
						view.courses.items.find(
							( course ) => course.id === item.id
						)?.usage ?? ''
					] ?? ''
				}
			/>
			<UsageList
				title={ __( 'Lessons', 'ohmylms' ) }
				items={ view.content.lesson.items }
				total={ view.content.lesson.total }
				route={ ( id ) => contentRoute( 'lesson', id ) }
			/>
			<UsageList
				title={ __( 'Quizzes', 'ohmylms' ) }
				items={ view.content.quiz.items }
				total={ view.content.quiz.total }
				route={ ( id ) => contentRoute( 'quiz', id ) }
			/>
			<UsageList
				title={ __( 'Assignments', 'ohmylms' ) }
				items={ view.content.assignment.items }
				total={ view.content.assignment.total }
				route={ ( id ) => contentRoute( 'assignment', id ) }
			/>
			<UsageList
				title={ __( 'Questions', 'ohmylms' ) }
				items={ view.questions.items }
				total={ view.questions.total }
				route={ ( id ) => contentRoute( 'question', id ) }
				extra={ ( item ) =>
					'primary' ===
					view.questions.items.find(
						( question ) => question.id === item.id
					)?.role
						? __( 'primary skill', 'ohmylms' )
						: __( 'supporting skill', 'ohmylms' )
				}
			/>
		</>
	);
}
