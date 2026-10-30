import { Table } from '../../../ui/Table';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
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
			<form
				role="search"
				aria-label={ __( 'Filter attempts', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					list.setFilters( {
						...list.filters,
						search: query.trim(),
					} );
				} }
			>
				<TextControl
					label={ __( 'Search students', 'ohmylms' ) }
					value={ query }
					onChange={ setQuery }
				/>
				<SelectControl
					label={ __( 'Status', 'ohmylms' ) }
					value={ list.filters.status }
					options={ statusOptions }
					onChange={ ( value ) => {
						const found = statusOptions.find(
							( option ) => option.value === value
						);
						if ( found ) {
							list.setFilters( {
								...list.filters,
								status: found.value,
							} );
						}
					} }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ list.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
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
			{ ! list.loading &&
				! list.error &&
				( list.data.items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: total attempt count. */
								_n(
									'%d attempt found.',
									'%d attempts found.',
									list.data.total,
									'ohmylms'
								),
								list.data.total
							) }
						</p>
						<Table caption={ __( 'Attempts', 'ohmylms' ) } striped>
							<thead>
								<tr>
									{ [
										__( 'Student', 'ohmylms' ),
										__( 'Status', 'ohmylms' ),
										__( 'Score', 'ohmylms' ),
										__( 'Started', 'ohmylms' ),
										__( 'Submitted', 'ohmylms' ),
										__( 'Actions', 'ohmylms' ),
									].map( ( heading ) => (
										<th key={ heading } scope="col">
											{ heading }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ list.data.items.map( ( item ) => (
									<tr key={ item.id }>
										<th scope="row">
											{ item.student.name }
											<br />
											<small>
												{ item.student.email }
											</small>
										</th>
										<td>
											{ attemptStatusLabel(
												item.status
											) }
										</td>
										<td>
											{ formatMarks( item.score ) } /{ ' ' }
											{ formatMarks( item.max_marks ) }
											{ null !== item.percentage &&
												` (${ formatMarks( item.percentage ) }%)` }
										</td>
										<td>
											{ formatDate( item.started_at ) }
										</td>
										<td>
											{ formatDate( item.submitted_at ) }
										</td>
										<td>
											<Button
												variant="link"
												onClick={ () =>
													setOpen( item.id )
												}
												aria-label={ sprintf(
													/* translators: %s: student name. */
													__(
														'Review attempt by %s',
														'ohmylms'
													),
													item.student.name
												) }
											>
												{ item.needs_grading
													? __( 'Grade', 'ohmylms' )
													: __(
															'Review',
															'ohmylms'
														) }
											</Button>
										</td>
									</tr>
								) ) }
							</tbody>
						</Table>
						<Pagination
							page={ list.page }
							pages={ list.data.totalPages }
							busy={ list.loading }
							onChange={ list.setPage }
						/>
					</>
				) : (
					<p>{ __( 'No attempts found.', 'ohmylms' ) }</p>
				) ) }
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
