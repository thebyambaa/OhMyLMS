import { ListTable } from '../../../../ui/ListTable';
import { Button, Modal, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { SearchPicker } from '../SearchPicker';
import { useOperation } from '../useOperation';
import { useRequest } from '../useRequest';
import {
	addMember,
	getCohort,
	listMembers,
	removeMember,
	searchCandidates,
	syncCohort,
} from './api';
import {
	cohortDate,
	seatsLabel,
	sourceLabel,
	stateLabel,
	type Cohort,
	type CohortMember,
} from './model';

/**
 * Members of one cohort: paged list, add an enrolled learner, remove a member, and add everyone who
 * enrolled inside the enrolment window.
 *
 * @param props          Component props.
 * @param props.id       Cohort ID.
 * @param props.backHref Address of the cohort list.
 * @param props.onBack   Returns to the list in place.
 */
export function CohortMembers( {
	id,
	backHref,
	onBack,
}: {
	id: number;
	backHref: string;
	onBack: () => void;
} ) {
	const [ page, setPage ] = useState( 1 );
	const [ version, setVersion ] = useState( 0 );
	const cohort = useRequest(
		( signal ) => getCohort( id, signal ),
		`${ id }:${ version }`
	);
	const members = useRequest(
		( signal ) => listMembers( id, page, '', signal ),
		`${ id }:${ page }:${ version }`
	);
	const operation = useOperation();
	const [ pick, setPick ] = useState< { id: number; title: string } >( {
		id: 0,
		title: '',
	} );
	const [ confirm, setConfirm ] = useState<
		{ kind: 'remove'; member: CohortMember } | { kind: 'sync' } | null
	>( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const refresh = () => setVersion( ( n ) => n + 1 );
	const current: Cohort | null = cohort.data;

	const back = (
		<p>
			<a
				href={ backHref }
				onClick={ ( event ) => {
					event.preventDefault();
					onBack();
				} }
			>
				{ __( 'Back to cohorts', 'ohmylms' ) }
			</a>
		</p>
	);
	if ( cohort.error && ! current ) {
		return (
			<>
				{ back }
				<Notice status="error" isDismissible={ false }>
					{ cohort.error.message }
				</Notice>
			</>
		);
	}
	if ( ! current ) {
		return (
			<>
				{ back }
				<p role="status">{ __( 'Loading cohort…', 'ohmylms' ) }</p>
			</>
		);
	}

	const add = async () => {
		if ( ! pick.id ) {
			return;
		}
		const name = pick.title;
		const done = await operation.run( async ( signal ) => {
			await addMember( id, pick.id, signal );
		} );
		if ( done ) {
			setPick( { id: 0, title: '' } );
			setMessage(
				sprintf(
					/* translators: %s: learner name. */
					__( 'Added %s.', 'ohmylms' ),
					name
				)
			);
			refresh();
		}
	};
	const run = async () => {
		if ( ! confirm ) {
			return;
		}
		let note = '';
		const done = await operation.run( async ( signal ) => {
			if ( 'remove' === confirm.kind ) {
				await removeMember( id, confirm.member.user_id, signal );
				note = sprintf(
					/* translators: %s: learner name. */
					__( 'Removed %s.', 'ohmylms' ),
					confirm.member.name
				);
			} else {
				const result = await syncCohort( id, signal );
				note = sprintf(
					/* translators: %d: number of learners. */
					_n(
						'Added %d learner who enrolled in the window.',
						'Added %d learners who enrolled in the window.',
						result.added,
						'ohmylms'
					),
					result.added
				);
				if ( result.full ) {
					note += ` ${ __( 'The cohort is full.', 'ohmylms' ) }`;
				}
			}
		} );
		if ( done ) {
			setConfirm( null );
			setMessage( note );
			refresh();
		}
	};
	const archived = 'archived' === current.status;

	return (
		<>
			{ back }
			<h2>{ current.title }</h2>
			<p>
				{ sprintf(
					/* translators: 1: course or plan, 2: start, 3: end, 4: state, 5: seats. */
					__(
						'%1$s · %2$s – %3$s · %4$s · Members: %5$s',
						'ohmylms'
					),
					current.target_title,
					cohortDate( current.start_date ),
					cohortDate( current.end_date ),
					stateLabel( current ),
					seatsLabel( current )
				) }
			</p>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ operation.error && ! confirm && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ ! archived && (
				<>
					<SearchPicker
						label={ __( 'Add an enrolled learner', 'ohmylms' ) }
						value={ pick.id }
						valueLabel={ pick.title }
						search={ async ( term, signal ) =>
							( await searchCandidates( id, term, signal ) ).map(
								( candidate ) => ( {
									id: candidate.user_id,
									title: `${ candidate.name } (${ candidate.login })`,
								} )
							)
						}
						help={ __(
							'Only learners enrolled in the course, or holding the plan, who are not members yet.',
							'ohmylms'
						) }
						onChange={ ( userId, title ) =>
							setPick( { id: userId, title } )
						}
					/>
					<p>
						<Button
							variant="secondary"
							disabled={ ! pick.id || operation.busy }
							accessibleWhenDisabled
							onClick={ () => void add() }
						>
							{ __( 'Add to cohort', 'ohmylms' ) }
						</Button>{ ' ' }
						<Button
							variant="tertiary"
							disabled={ operation.busy }
							onClick={ () => {
								operation.clearError();
								setConfirm( { kind: 'sync' } );
							} }
						>
							{ __(
								'Add everyone who enrolled in the window',
								'ohmylms'
							) }
						</Button>
					</p>
				</>
			) }
			{ members.error && (
				<Notice status="error" isDismissible={ false }>
					{ members.error.message }
				</Notice>
			) }
			{ members.data && (
				<ListTable< CohortMember >
					caption={ __( 'Cohort members', 'ohmylms' ) }
					rows={ members.data.items }
					rowKey={ ( member ) => member.user_id }
					busy={ members.loading }
					emptyMessage={ __( 'No members yet.', 'ohmylms' ) }
					pagination={ {
						page,
						totalPages: members.data.totalPages,
						totalItems: members.data.total,
						onChange: setPage,
					} }
					columns={ [
						{
							id: 'name',
							label: __( 'Learner', 'ohmylms' ),
							primary: true,
							render: ( member ) => (
								<>
									<strong>{ member.name }</strong>{ ' ' }
									<span>({ member.login })</span>
								</>
							),
						},
						{
							id: 'source',
							label: __( 'Joined through', 'ohmylms' ),
							render: ( member ) => sourceLabel( member.source ),
						},
						{
							id: 'joined',
							label: __( 'Joined', 'ohmylms' ),
							render: ( member ) =>
								cohortDate( member.joined_at ),
						},
					] }
					rowActions={ ( member ) => [
						{
							key: 'trash',
							label: __( 'Remove', 'ohmylms' ),
							destructive: true,
							ariaLabel: sprintf(
								/* translators: %s: learner name. */
								__( 'Remove %s from the cohort', 'ohmylms' ),
								member.name
							),
							onClick: () => {
								operation.clearError();
								setConfirm( { kind: 'remove', member } );
							},
						},
					] }
				/>
			) }
			{ confirm && (
				<Modal
					title={
						'sync' === confirm.kind
							? __( 'Add learners from the window', 'ohmylms' )
							: __( 'Remove member', 'ohmylms' )
					}
					onRequestClose={ () =>
						! operation.busy && setConfirm( null )
					}
					shouldCloseOnClickOutside={ false }
				>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<p>
						{ 'sync' === confirm.kind
							? __(
									'Add every learner whose enrolment started inside this cohort’s enrolment window and who is in no other cohort of the same course or plan, until the cohort is full?',
									'ohmylms'
								)
							: sprintf(
									/* translators: %s: learner name. */
									__(
										'Remove %s from this cohort? Their enrolment is not changed.',
										'ohmylms'
									),
									confirm.member.name
								) }
					</p>
					<Button
						variant="primary"
						isDestructive={ 'remove' === confirm.kind }
						isBusy={ operation.busy }
						disabled={ operation.busy }
						onClick={ () => void run() }
					>
						{ 'sync' === confirm.kind
							? __( 'Add learners', 'ohmylms' )
							: __( 'Remove', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ () => setConfirm( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
