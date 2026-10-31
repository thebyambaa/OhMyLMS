import { ListTable } from '../../../../ui/ListTable';
import { Button, Modal, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useListQuery } from '../../../components/listQuery';
import { statusViews } from '../../../components/listViews';
import {
	PageTitleAction,
	PageTitleActions,
} from '../../../layout/PageTitleActions';
import { useOperation } from '../useOperation';
import { useRequest } from '../useRequest';
import { deleteCohort, listCohorts, type CohortQuery } from './api';
import { CohortForm } from './CohortForm';
import { CohortMembers } from './CohortMembers';
import { cohortDate, seatsLabel, stateLabel, type Cohort } from './model';

type Dialog =
	| { kind: 'create' }
	| { kind: 'edit'; cohort: Cohort }
	| { kind: 'delete'; cohort: Cohort }
	| null;

const DEFAULTS = { status: 'any', s: '', cohort: '' };

/**
 * Cohorts (`/memberships?panel=cohorts`): groups of learners of a course or a membership plan who
 * enrolled in the same window. A list with create, edit, archive and delete; `cohort=<id>` opens the
 * member list.
 */
export default function CohortsPanel() {
	const list = useListQuery( DEFAULTS );
	const status = ( [ 'active', 'archived' ] as const ).find(
		( value ) => value === list.values.status
	);
	const query: CohortQuery = {
		page: list.page,
		search: list.values.s,
		status: status ?? 'any',
	};
	const request = useRequest(
		( signal ) => listCohorts( query, signal ),
		list.key
	);
	const [ term, setTerm ] = useState( list.values.s );
	useEffect( () => setTerm( list.values.s ), [ list.values.s ] );
	const [ dialog, setDialog ] = useState< Dialog >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const operation = useOperation();
	const open = Number( list.values.cohort );

	if ( Number.isInteger( open ) && open > 0 ) {
		return (
			<CohortMembers
				id={ open }
				backHref={ list.href( { cohort: '' } ) }
				onBack={ () => list.set( { cohort: '' } ) }
			/>
		);
	}

	const data = request.data;
	const remove = async ( cohort: Cohort ) => {
		const done = await operation.run( ( signal ) =>
			deleteCohort( cohort.id, signal )
		);
		if ( done ) {
			setDialog( null );
			setMessage( __( 'Cohort deleted.', 'ohmylms' ) );
			request.reload();
		}
	};

	return (
		<>
			<PageTitleActions>
				<PageTitleAction
					onClick={ () => {
						operation.clearError();
						setDialog( { kind: 'create' } );
					} }
				>
					{ __( 'Add cohort', 'ohmylms' ) }
				</PageTitleAction>
			</PageTitleActions>
			<p>
				{ __(
					'A cohort groups the learners of a course or a membership plan who enrol in the same window. Learners join automatically when they enrol or buy while its enrolment is open; members open the course from the cohort start.',
					'ohmylms'
				) }
			</p>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error.message }{ ' ' }
					<Button variant="link" onClick={ request.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ ! data && ! request.error && (
				<p role="status">{ __( 'Loading cohorts…', 'ohmylms' ) }</p>
			) }
			{ data && (
				<ListTable< Cohort >
					caption={ __( 'Cohorts', 'ohmylms' ) }
					heading={ __( 'Cohorts list', 'ohmylms' ) }
					formLabel={ __( 'Search cohorts', 'ohmylms' ) }
					busy={ request.loading }
					rows={ data.items }
					rowKey={ ( cohort ) => cohort.id }
					emptyMessage={ __( 'No cohorts found.', 'ohmylms' ) }
					views={ statusViews(
						[
							{ value: 'any', label: __( 'All', 'ohmylms' ) },
							{
								value: 'active',
								label: __( 'Active', 'ohmylms' ),
							},
							{
								value: 'archived',
								label: __( 'Archived', 'ohmylms' ),
							},
						],
						query.status,
						data.total,
						( value ) => list.set( { status: value } ),
						( value ) => list.href( { status: value } )
					) }
					search={ {
						label: __( 'Search cohorts', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						value: term,
						onChange: setTerm,
						onSubmit: () => list.set( { s: term.trim() } ),
					} }
					pagination={ {
						page: list.page,
						totalPages: data.totalPages,
						totalItems: data.total,
						itemsLabel: sprintf(
							/* translators: %d: number of cohorts. */
							_n(
								'%d cohort',
								'%d cohorts',
								data.total,
								'ohmylms'
							),
							data.total
						),
						onChange: ( page ) => list.set( { page } ),
						href: ( page ) => list.href( { page } ),
					} }
					columns={ [
						{
							id: 'title',
							label: __( 'Cohort', 'ohmylms' ),
							primary: true,
							render: ( cohort ) => (
								<strong>
									<a
										className="row-title"
										href={ list.href( {
											cohort: String( cohort.id ),
										} ) }
										onClick={ ( event ) => {
											event.preventDefault();
											list.set( {
												cohort: String( cohort.id ),
											} );
										} }
									>
										{ cohort.title }
									</a>
								</strong>
							),
						},
						{
							id: 'target',
							label: __( 'Course or plan', 'ohmylms' ),
							render: ( cohort ) =>
								`${ cohort.target_title } (${
									'course' === cohort.scope
										? __( 'course', 'ohmylms' )
										: __( 'plan', 'ohmylms' )
								})`,
						},
						{
							id: 'dates',
							label: __( 'Runs', 'ohmylms' ),
							render: ( cohort ) =>
								`${ cohortDate( cohort.start_date ) } – ${ cohortDate(
									cohort.end_date
								) }`,
						},
						{
							id: 'enrolment',
							label: __( 'Enrolment', 'ohmylms' ),
							render: ( cohort ) =>
								cohort.enrollment_open
									? sprintf(
											/* translators: %s: deadline. */
											__( 'Open until %s', 'ohmylms' ),
											cohortDate(
												cohort.enrollment_end ??
													cohort.end_date
											)
										)
									: __( 'Closed', 'ohmylms' ),
						},
						{
							id: 'members',
							label: __( 'Members', 'ohmylms' ),
							render: seatsLabel,
						},
						{
							id: 'state',
							label: __( 'State', 'ohmylms' ),
							render: stateLabel,
						},
					] }
					rowActions={ ( cohort ) => [
						{
							key: 'view',
							label: __( 'Members', 'ohmylms' ),
							href: list.href( { cohort: String( cohort.id ) } ),
							onClick: () =>
								list.set( { cohort: String( cohort.id ) } ),
						},
						{
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							ariaLabel: sprintf(
								/* translators: %s: cohort title. */
								__( 'Edit %s', 'ohmylms' ),
								cohort.title
							),
							onClick: () =>
								setDialog( { kind: 'edit', cohort } ),
						},
						0 === cohort.member_count && {
							key: 'trash',
							label: __( 'Delete', 'ohmylms' ),
							destructive: true,
							ariaLabel: sprintf(
								/* translators: %s: cohort title. */
								__( 'Delete %s', 'ohmylms' ),
								cohort.title
							),
							onClick: () => {
								operation.clearError();
								setDialog( { kind: 'delete', cohort } );
							},
						},
					] }
				/>
			) }
			{ ( 'create' === dialog?.kind || 'edit' === dialog?.kind ) && (
				<CohortForm
					cohort={ 'edit' === dialog.kind ? dialog.cohort : null }
					onClose={ () => setDialog( null ) }
					onSaved={ ( saved ) => {
						setDialog( null );
						setMessage(
							sprintf(
								/* translators: %s: cohort title. */
								__( 'Saved %s.', 'ohmylms' ),
								saved.title
							)
						);
						request.reload();
					} }
				/>
			) }
			{ 'delete' === dialog?.kind && (
				<Modal
					title={ __( 'Delete cohort', 'ohmylms' ) }
					onRequestClose={ () =>
						! operation.busy && setDialog( null )
					}
					shouldCloseOnClickOutside={ false }
				>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<p>
						{ sprintf(
							/* translators: %s: cohort title. */
							__(
								'Permanently delete the empty cohort %s?',
								'ohmylms'
							),
							dialog.cohort.title
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						isBusy={ operation.busy }
						disabled={ operation.busy }
						onClick={ () => void remove( dialog.cohort ) }
					>
						{ __( 'Delete cohort', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ () => setDialog( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
