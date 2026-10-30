import { ListTable } from '../../../ui/ListTable';
import { Button, Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { statusViews } from '../../components/listViews';
import { useRequest } from '../../hooks/useRequest';
import { getAssignment, listSubmissions } from './api';
import { formatDate, submissionLabel } from './model';
import type { SubmissionFilter } from './types';

const FILTERS: readonly SubmissionFilter[] = [ 'all', 'submitted', 'graded' ];

function routeId( value: string | undefined ): number | null {
	return value && /^[1-9]\d*$/.test( value ) ? Number( value ) : null;
}

/**
 * Submissions of one assignment (`#/assignments/<id>/submissions`): paged, filterable by grading
 * state, linking to the grading view. Only people who can edit the assignment get here; the server
 * returns each student's ID and display name only.
 */
export default function SubmissionsPage() {
	const params = useParams();
	const id = routeId( params.id );
	const [ page, setPage ] = useState( 1 );
	const [ status, setStatus ] = useState< SubmissionFilter >( 'all' );
	const load = useCallback(
		( signal: AbortSignal ) =>
			null === id
				? Promise.reject( new Error( 'invalid id' ) )
				: listSubmissions( id, page, status, signal ),
		[ id, page, status ]
	);
	const list = useRequest( load );
	const loadAssignment = useCallback(
		( signal: AbortSignal ) =>
			null === id
				? Promise.reject( new Error( 'invalid id' ) )
				: getAssignment( id, signal ),
		[ id ]
	);
	const assignment = useRequest( loadAssignment );

	if ( null === id ) {
		return (
			<>
				<h2>{ __( 'Submissions', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __( 'This assignment link is invalid.', 'ohmylms' ) }
				</Notice>
				<Link to="/assignments">
					{ __( 'Back to assignments', 'ohmylms' ) }
				</Link>
			</>
		);
	}

	return (
		<>
			<p>
				<Link to="/assignments">
					{ __( 'Back to assignments', 'ohmylms' ) }
				</Link>{ ' ' }
				<Link to={ `/assignments/${ id }` }>
					{ __( 'Edit assignment', 'ohmylms' ) }
				</Link>
			</p>
			<h2>
				{ assignment.data
					? sprintf(
							/* translators: %s: assignment title. */
							__( 'Submissions: %s', 'ohmylms' ),
							assignment.data.title
						)
					: __( 'Submissions', 'ohmylms' ) }
			</h2>
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error }{ ' ' }
					<Button variant="link" onClick={ list.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ list.loading && (
				<p role="status">{ __( 'Loading submissions…', 'ohmylms' ) }</p>
			) }
			{ ! list.loading && list.data && (
				<ListTable
					caption={ __( 'Submissions', 'ohmylms' ) }
					rows={ list.data.items }
					rowKey={ ( item ) => item.id }
					emptyMessage={ __( 'No submissions found.', 'ohmylms' ) }
					views={ statusViews(
						[
							{ value: 'all', label: __( 'All', 'ohmylms' ) },
							{
								value: 'submitted',
								label: __( 'Needs grading', 'ohmylms' ),
							},
							{
								value: 'graded',
								label: __( 'Graded', 'ohmylms' ),
							},
						],
						status,
						list.data.total,
						( value ) => {
							const found = FILTERS.find(
								( item ) => item === value
							);
							if ( found ) {
								setStatus( found );
								setPage( 1 );
							}
						}
					) }
					pagination={ {
						page,
						totalPages: list.data.totalPages,
						totalItems: list.data.total,
						itemsLabel: sprintf(
							/* translators: %d: total submission count. */
							_n(
								'%d submission found.',
								'%d submissions found.',
								list.data.total,
								'ohmylms'
							),
							list.data.total
						),
						onChange: setPage,
					} }
					columns={ [
						{
							id: 'student',
							label: __( 'Student', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<strong>
									<Link
										className="row-title"
										to={ `/assignments/${ id }/submissions/${ item.id }` }
									>
										{ item.student.display_name }
									</Link>
								</strong>
							),
						},
						{
							id: 'course',
							label: __( 'Course', 'ohmylms' ),
							render: ( item ) => item.course?.title ?? '—',
						},
						{
							id: 'date',
							label: __( 'Submitted', 'ohmylms' ),
							render: ( item ) => formatDate( item.submitted_at ),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( item ) => submissionLabel( item ),
						},
						{
							id: 'score',
							label: __( 'Score', 'ohmylms' ),
							render: ( item ) =>
								null === item.score
									? '—'
									: `${ item.score } / ${ item.max_score }`,
						},
					] }
					rowActions={ ( item ) => [
						{
							key: 'grade',
							label:
								'submitted' === item.status
									? __( 'Grade', 'ohmylms' )
									: __( 'Review', 'ohmylms' ),
							render: (
								<Link
									to={ `/assignments/${ id }/submissions/${ item.id }` }
									aria-label={ sprintf(
										/* translators: %s: student name. */
										__(
											'Open submission by %s',
											'ohmylms'
										),
										item.student.display_name
									) }
								>
									{ 'submitted' === item.status
										? __( 'Grade', 'ohmylms' )
										: __( 'Review', 'ohmylms' ) }
								</Link>
							),
						},
					] }
				/>
			) }
		</>
	);
}
