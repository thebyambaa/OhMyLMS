import {
	Button,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useSearchParams } from 'react-router-dom';
import { Table } from '../../../ui/Table';
import { Pagination } from '../../components/Pagination';
import { useRequest } from '../../hooks/useRequest';
import { listCourses } from './api';
import { formatDate, formatMarks, quizStatusLabel } from './model';
import { getQuizReportSummary, listQuizReports } from './reportsApi';
import {
	paramsFromQuery,
	passRateText,
	queryFromParams,
	sortOptions,
} from './reportsModel';
import type { QuizReportQuery, QuizReportSummary } from './types';
import { usePagedList } from './usePagedList';

function summaryText( summary: QuizReportSummary ): string {
	return [
		sprintf(
			/* translators: %d: number of quizzes. */
			_n( '%d quiz', '%d quizzes', summary.quizzes, 'ohmylms' ),
			summary.quizzes
		),
		sprintf(
			/* translators: %d: number of attempts. */
			_n( '%d attempt', '%d attempts', summary.attempts, 'ohmylms' ),
			summary.attempts
		),
		sprintf(
			/* translators: %d: number of learners. */
			_n( '%d learner', '%d learners', summary.learners, 'ohmylms' ),
			summary.learners
		),
		sprintf(
			/* translators: %d: number of attempts waiting for grading. */
			_n( '%d to grade', '%d to grade', summary.to_grade, 'ohmylms' ),
			summary.to_grade
		),
		sprintf(
			/* translators: %d: number of completed attempts. */
			_n( '%d completed', '%d completed', summary.completed, 'ohmylms' ),
			summary.completed
		),
	].join( ', ' );
}

/**
 * Quiz reports overview (`#/reports/quizzes`): every quiz the user may grade with attempt, learner,
 * grading, average and pass-rate figures and totals, filtered by title, course, status and activity,
 * sorted and paged. Filters live in the address, so the view can be linked. Rows open the attempts
 * screen at `#/quizzes/<id>/attempts`.
 */
export default function QuizReportsPage() {
	const [ params, setParams ] = useSearchParams();
	const query = queryFromParams( params );
	const key = paramsFromQuery( query ).toString();
	const [ text, setText ] = useState( query.search );
	const [ courseQuery, setCourseQuery ] = useState( '' );
	useEffect( () => setText( query.search ), [ query.search ] );

	function update( change: Partial< QuizReportQuery > ) {
		setParams(
			paramsFromQuery( {
				...query,
				page: 1,
				...change,
			} ),
			{ replace: true }
		);
	}

	const loadRows = useCallback(
		( signal: AbortSignal ) =>
			listQuizReports(
				queryFromParams( new URLSearchParams( key ) ),
				signal
			),
		[ key ]
	);
	const filterKey = paramsFromQuery( {
		...query,
		page: 1,
		orderby: 'last_submitted',
		order: 'desc',
	} ).toString();
	const loadSummary = useCallback(
		( signal: AbortSignal ) =>
			getQuizReportSummary(
				queryFromParams( new URLSearchParams( filterKey ) ),
				signal
			),
		[ filterKey ]
	);
	const rows = useRequest( loadRows );
	const summary = useRequest( loadSummary );
	const courses = usePagedList(
		( signal ) => listCourses( courseQuery, 1, signal ),
		[ courseQuery ]
	);

	const items = rows.data?.items ?? [];
	const total = rows.data?.total ?? 0;
	const pages = rows.data?.totalPages ?? 1;
	const courseOptions = [
		{ value: '0', label: __( 'All courses', 'ohmylms' ) },
		...courses.data.items.map( ( course ) => ( {
			value: String( course.id ),
			label: course.title,
		} ) ),
	];
	if (
		query.course > 0 &&
		! courseOptions.some(
			( option ) => option.value === String( query.course )
		)
	) {
		courseOptions.push( {
			value: String( query.course ),
			label: __( 'Selected course', 'ohmylms' ),
		} );
	}

	return (
		<>
			<h2>{ __( 'Quiz reports', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Attempts, grading and results across the quizzes you may grade. Open a quiz to review and grade its attempts.',
					'ohmylms'
				) }{ ' ' }
				<Link to="/quizzes">{ __( 'All quizzes', 'ohmylms' ) }</Link>
			</p>
			<form
				role="search"
				aria-label={ __( 'Filter quiz reports', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					update( { search: text.trim() } );
				} }
			>
				<TextControl
					label={ __( 'Search quizzes', 'ohmylms' ) }
					value={ text }
					maxLength={ 200 }
					onChange={ setText }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ rows.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
				<SelectControl
					label={ __( 'Status', 'ohmylms' ) }
					value={ query.status }
					options={ [
						{ value: 'all', label: __( 'All', 'ohmylms' ) },
						{
							value: 'publish',
							label: __( 'Published', 'ohmylms' ),
						},
						{ value: 'draft', label: __( 'Draft', 'ohmylms' ) },
					] }
					onChange={ ( value ) =>
						update( {
							status: value as QuizReportQuery[ 'status' ],
						} )
					}
				/>
				<SelectControl
					label={ __( 'Activity', 'ohmylms' ) }
					value={ query.activity }
					options={ [
						{ value: 'all', label: __( 'All quizzes', 'ohmylms' ) },
						{
							value: 'attempted',
							label: __( 'With attempts', 'ohmylms' ),
						},
						{
							value: 'to_grade',
							label: __( 'Waiting for grading', 'ohmylms' ),
						},
					] }
					onChange={ ( value ) =>
						update( {
							activity: value as QuizReportQuery[ 'activity' ],
						} )
					}
				/>
				<TextControl
					label={ __( 'Find a course', 'ohmylms' ) }
					value={ courseQuery }
					onChange={ setCourseQuery }
				/>
				<SelectControl
					label={ __( 'Course', 'ohmylms' ) }
					value={ String( query.course ) }
					options={ courseOptions }
					onChange={ ( value ) =>
						update( { course: Number( value ) } )
					}
				/>
				<SelectControl
					label={ __( 'Sort by', 'ohmylms' ) }
					value={ `${ query.orderby }:${ query.order }` }
					options={ sortOptions() }
					onChange={ ( value ) => {
						const [ orderby, order ] = value.split( ':' );
						update( {
							orderby: orderby as QuizReportQuery[ 'orderby' ],
							order: order as QuizReportQuery[ 'order' ],
						} );
					} }
				/>
			</form>
			{ courses.error && (
				<Notice status="warning" isDismissible={ false }>
					{ courses.error }
				</Notice>
			) }
			{ summary.data && (
				<p role="status" aria-label={ __( 'Totals', 'ohmylms' ) }>
					{ summaryText( summary.data ) }
				</p>
			) }
			{ ( rows.error || summary.error ) && (
				<Notice status="error" isDismissible={ false }>
					{ rows.error || summary.error }{ ' ' }
					<Button
						variant="link"
						onClick={ () => {
							rows.retry();
							summary.retry();
						} }
					>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ rows.loading && (
				<p role="status">
					{ __( 'Loading quiz reports…', 'ohmylms' ) }
				</p>
			) }
			{ ! rows.loading && ! rows.error && ! items.length && (
				<p>{ __( 'No quizzes match these filters.', 'ohmylms' ) }</p>
			) }
			{ items.length > 0 && (
				<>
					<p role="status">
						{ sprintf(
							/* translators: %d: number of quizzes. */
							_n(
								'%d quiz found.',
								'%d quizzes found.',
								total,
								'ohmylms'
							),
							total
						) }
					</p>
					<Table caption={ __( 'Quiz reports', 'ohmylms' ) } striped>
						<thead>
							<tr>
								{ [
									__( 'Quiz', 'ohmylms' ),
									__( 'Course', 'ohmylms' ),
									__( 'Status', 'ohmylms' ),
									__( 'Attempts', 'ohmylms' ),
									__( 'Learners', 'ohmylms' ),
									__( 'To grade', 'ohmylms' ),
									__( 'Completed', 'ohmylms' ),
									__( 'Average marks', 'ohmylms' ),
									__( 'Pass rate', 'ohmylms' ),
									__( 'Last submission', 'ohmylms' ),
									__( 'Actions', 'ohmylms' ),
								].map( ( heading ) => (
									<th key={ heading } scope="col">
										{ heading }
									</th>
								) ) }
							</tr>
						</thead>
						<tbody>
							{ items.map( ( row ) => (
								<tr key={ row.id }>
									<th scope="row">
										{ row.can_grade ? (
											<Link
												to={ `/quizzes/${ row.id }/attempts` }
											>
												{ row.title }
											</Link>
										) : (
											row.title
										) }
									</th>
									<td>{ row.course?.title ?? '—' }</td>
									<td>{ quizStatusLabel( row.status ) }</td>
									<td>{ row.attempts }</td>
									<td>{ row.learners }</td>
									<td>{ row.to_grade }</td>
									<td>{ row.completed }</td>
									<td>
										{ null === row.average_score
											? '—'
											: formatMarks( row.average_score ) }
									</td>
									<td>{ passRateText( row ) }</td>
									<td>
										{ formatDate( row.last_submitted_at ) }
									</td>
									<td>
										{ row.can_grade && (
											<Link
												to={ `/quizzes/${ row.id }/attempts` }
												aria-label={ sprintf(
													/* translators: %s: quiz title. */
													__(
														'Attempts for %s',
														'ohmylms'
													),
													row.title
												) }
											>
												{ __( 'Attempts', 'ohmylms' ) }
											</Link>
										) }{ ' ' }
										{ row.can_edit && (
											<Link
												to={ `/quizzes/${ row.id }` }
												aria-label={ sprintf(
													/* translators: %s: quiz title. */
													__(
														'Settings for %s',
														'ohmylms'
													),
													row.title
												) }
											>
												{ __( 'Settings', 'ohmylms' ) }
											</Link>
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</Table>
					<Pagination
						page={ query.page }
						pages={ pages }
						busy={ rows.loading }
						onChange={ ( page ) => update( { page } ) }
					/>
				</>
			) }
		</>
	);
}
