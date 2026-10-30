import { ListTable } from '../../../ui/ListTable';
import { Button, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { statusViews } from '../../components/listViews';
import { AttemptReview } from './AttemptReview';
import { attemptStatusLabel, formatDate, formatMarks } from './model';
import { useAttempts } from './useAttempts';
import type { AttemptFilters } from './types';

function routeId( value: string | undefined ): number | null {
	return value && /^[1-9]\d*$/.test( value ) ? Number( value ) : null;
}

/**
 * Attempts of one quiz (`#/quizzes/<id>/attempts`): paged and filterable by status and student, with a
 * dialog to review an attempt and award marks. Student names and emails are shown only to people who
 * may grade the quiz; the server enforces that.
 */
export default function AttemptsPage() {
	const params = useParams();
	const id = routeId( params.id );
	const list = useAttempts( id );
	const [ query, setQuery ] = useState( '' );
	const [ open, setOpen ] = useState< number | null >( null );

	if ( null === id ) {
		return (
			<>
				<h2>{ __( 'Attempts', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __( 'This quiz link is invalid.', 'ohmylms' ) }
				</Notice>
				<Link to="/quizzes">
					{ __( 'Back to quizzes', 'ohmylms' ) }
				</Link>
			</>
		);
	}
	const statusOptions: Array< {
		value: AttemptFilters[ 'status' ];
		label: string;
	} > = [
		{ value: 'all', label: __( 'All', 'ohmylms' ) },
		{ value: 'in-review', label: __( 'Needs grading', 'ohmylms' ) },
		{ value: 'completed', label: __( 'Completed', 'ohmylms' ) },
		{ value: 'in-progress', label: __( 'In progress', 'ohmylms' ) },
	];

	return (
		<>
			<p>
				<Link to="/quizzes">
					{ __( 'Back to quizzes', 'ohmylms' ) }
				</Link>
			</p>
			<h2>
				{ list.quiz
					? sprintf(
							/* translators: %s: quiz title. */
							__( 'Attempts: %s', 'ohmylms' ),
							list.quiz.title
						)
					: __( 'Attempts', 'ohmylms' ) }
			</h2>
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error.message }{ ' ' }
					{ 403 !== list.error.status &&
						401 !== list.error.status &&
						404 !== list.error.status && (
							<Button variant="link" onClick={ list.reload }>
								{ __( 'Try again', 'ohmylms' ) }
							</Button>
						) }
				</Notice>
			) }
			{ list.loading && (
				<p role="status">{ __( 'Loading attempts…', 'ohmylms' ) }</p>
			) }
			{ ! list.loading && ! list.error && (
				<ListTable
					caption={ __( 'Attempts', 'ohmylms' ) }
					formLabel={ __( 'Filter attempts', 'ohmylms' ) }
					rows={ list.data.items }
					rowKey={ ( item ) => item.id }
					emptyMessage={ __( 'No attempts found.', 'ohmylms' ) }
					views={ statusViews(
						statusOptions,
						list.filters.status,
						list.data.total,
						( value ) => {
							const found = statusOptions.find(
								( option ) => option.value === value
							);
							if ( found ) {
								list.setFilters( {
									...list.filters,
									status: found.value,
								} );
							}
						}
					) }
					search={ {
						label: __( 'Search students', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						value: query,
						onChange: setQuery,
						onSubmit: () =>
							list.setFilters( {
								...list.filters,
								search: query.trim(),
							} ),
					} }
					pagination={ {
						page: list.page,
						totalPages: list.data.totalPages,
						totalItems: list.data.total,
						itemsLabel: sprintf(
							/* translators: %d: total attempt count. */
							_n(
								'%d attempt found.',
								'%d attempts found.',
								list.data.total,
								'ohmylms'
							),
							list.data.total
						),
						onChange: list.setPage,
					} }
					columns={ [
						{
							id: 'student',
							label: __( 'Student', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<>
									<strong>{ item.student.name }</strong>
									<br />
									{ item.student.email }
								</>
							),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( item ) =>
								attemptStatusLabel( item.status ),
						},
						{
							id: 'score',
							label: __( 'Score', 'ohmylms' ),
							render: ( item ) => (
								<>
									{ formatMarks( item.score ) } /{ ' ' }
									{ formatMarks( item.max_marks ) }
									{ null !== item.percentage &&
										` (${ formatMarks( item.percentage ) }%)` }
								</>
							),
						},
						{
							id: 'started',
							label: __( 'Started', 'ohmylms' ),
							render: ( item ) => formatDate( item.started_at ),
						},
						{
							id: 'submitted',
							label: __( 'Submitted', 'ohmylms' ),
							render: ( item ) => formatDate( item.submitted_at ),
						},
					] }
					rowActions={ ( item ) => [
						{
							key: 'review',
							label: item.needs_grading
								? __( 'Grade', 'ohmylms' )
								: __( 'Review', 'ohmylms' ),
							ariaLabel: sprintf(
								/* translators: %s: student name. */
								__( 'Review attempt by %s', 'ohmylms' ),
								item.student.name
							),
							onClick: () => setOpen( item.id ),
						},
					] }
				/>
			) }
			{ null !== open && (
				<AttemptReview
					quizId={ id }
					attemptId={ open }
					onClose={ () => setOpen( null ) }
					onGraded={ list.reload }
				/>
			) }
		</>
	);
}
