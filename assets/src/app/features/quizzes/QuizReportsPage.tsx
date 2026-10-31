import { Button, Notice } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useSearchParams } from 'react-router-dom';
import {
	ListTable,
	ListTableInput,
	ListTableSelect,
} from '../../../ui/ListTable';
import { useSearchHref } from '../../components/listQuery';
import { statusViews } from '../../components/listViews';
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

	const searchHref = useSearchHref();
	function next( change: Partial< QuizReportQuery > ) {
		return paramsFromQuery( {
			...query,
			page: 1,
			...change,
		} );
	}
	function update( change: Partial< QuizReportQuery > ) {
		setParams( next( change ) );
	}
	const hrefOf = ( change: Partial< QuizReportQuery > ) =>
		searchHref( next( change ).toString() );

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
			<p>
				{ __(
					'Attempts, grading and results across the quizzes you may grade. Open a quiz to review and grade its attempts.',
					'ohmylms'
				) }{ ' ' }
				<Link to="/quizzes">{ __( 'All quizzes', 'ohmylms' ) }</Link>
			</p>
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
			{ ! rows.error && ( ! rows.loading || items.length > 0 ) && (
				<ListTable
					caption={ __( 'Quiz reports', 'ohmylms' ) }
					heading={ __( 'Quiz reports', 'ohmylms' ) }
					formLabel={ __( 'Filter quiz reports', 'ohmylms' ) }
					busy={ rows.loading }
					rows={ items }
					rowKey={ ( row ) => row.id }
					emptyMessage={ __(
						'No quizzes match these filters.',
						'ohmylms'
					) }
					views={ statusViews(
						[
							{ value: 'all', label: __( 'All', 'ohmylms' ) },
							{
								value: 'publish',
								label: __( 'Published', 'ohmylms' ),
							},
							{ value: 'draft', label: __( 'Draft', 'ohmylms' ) },
						],
						query.status,
						total,
						( value ) =>
							update( {
								status: value as QuizReportQuery[ 'status' ],
							} ),
						( value ) =>
							hrefOf( {
								status: value as QuizReportQuery[ 'status' ],
							} )
					) }
					search={ {
						label: __( 'Search quizzes', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						value: text,
						maxLength: 200,
						onChange: setText,
					} }
					onFilter={ () => update( { search: text.trim() } ) }
					filterLabel={ null }
					filters={
						<>
							<ListTableSelect
								label={ __( 'Activity', 'ohmylms' ) }
								value={ query.activity }
								options={ [
									{
										value: 'all',
										label: __( 'All quizzes', 'ohmylms' ),
									},
									{
										value: 'attempted',
										label: __( 'With attempts', 'ohmylms' ),
									},
									{
										value: 'to_grade',
										label: __(
											'Waiting for grading',
											'ohmylms'
										),
									},
								] }
								onChange={ ( value ) =>
									update( {
										activity:
											value as QuizReportQuery[ 'activity' ],
									} )
								}
							/>
							<ListTableInput
								label={ __( 'Find a course', 'ohmylms' ) }
								type="search"
								value={ courseQuery }
								onChange={ setCourseQuery }
							/>
							<ListTableSelect
								label={ __( 'Course', 'ohmylms' ) }
								value={ String( query.course ) }
								options={ courseOptions }
								onChange={ ( value ) =>
									update( { course: Number( value ) } )
								}
							/>
							<ListTableSelect
								label={ __( 'Sort by', 'ohmylms' ) }
								value={ `${ query.orderby }:${ query.order }` }
								options={ sortOptions() }
								onChange={ ( value ) => {
									const [ orderby, order ] =
										value.split( ':' );
									update( {
										orderby:
											orderby as QuizReportQuery[ 'orderby' ],
										order: order as QuizReportQuery[ 'order' ],
									} );
								} }
							/>
						</>
					}
					pagination={ {
						page: query.page,
						totalPages: pages,
						totalItems: total,
						itemsLabel: sprintf(
							/* translators: %d: number of quizzes. */
							_n(
								'%d quiz found.',
								'%d quizzes found.',
								total,
								'ohmylms'
							),
							total
						),
						onChange: ( page ) => update( { page } ),
						href: ( page ) => hrefOf( { page } ),
					} }
					columns={ [
						{
							id: 'quiz',
							label: __( 'Quiz', 'ohmylms' ),
							primary: true,
							render: ( row ) => (
								<strong>
									{ row.can_grade ? (
										<Link
											className="row-title"
											to={ `/quizzes/${ row.id }/attempts` }
										>
											{ row.title }
										</Link>
									) : (
										row.title
									) }
								</strong>
							),
						},
						{
							id: 'course',
							label: __( 'Course', 'ohmylms' ),
							render: ( row ) => row.course?.title ?? '—',
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( row ) => quizStatusLabel( row.status ),
						},
						{
							id: 'attempts',
							label: __( 'Attempts', 'ohmylms' ),
							className: 'num',
							render: ( row ) => row.attempts,
						},
						{
							id: 'learners',
							label: __( 'Learners', 'ohmylms' ),
							className: 'num',
							render: ( row ) => row.learners,
						},
						{
							id: 'to_grade',
							label: __( 'To grade', 'ohmylms' ),
							className: 'num',
							render: ( row ) => row.to_grade,
						},
						{
							id: 'completed',
							label: __( 'Completed', 'ohmylms' ),
							className: 'num',
							render: ( row ) => row.completed,
						},
						{
							id: 'average',
							label: __( 'Average marks', 'ohmylms' ),
							render: ( row ) =>
								null === row.average_score
									? '—'
									: formatMarks( row.average_score ),
						},
						{
							id: 'pass_rate',
							label: __( 'Pass rate', 'ohmylms' ),
							render: ( row ) => passRateText( row ),
						},
						{
							id: 'last_submitted',
							label: __( 'Last submission', 'ohmylms' ),
							render: ( row ) =>
								formatDate( row.last_submitted_at ),
						},
					] }
					rowActions={ ( row ) => [
						row.can_grade && {
							key: 'attempts',
							label: __( 'Attempts', 'ohmylms' ),
							render: (
								<Link
									to={ `/quizzes/${ row.id }/attempts` }
									aria-label={ sprintf(
										/* translators: %s: quiz title. */
										__( 'Attempts for %s', 'ohmylms' ),
										row.title
									) }
								>
									{ __( 'Attempts', 'ohmylms' ) }
								</Link>
							),
						},
						row.can_edit && {
							key: 'edit',
							label: __( 'Settings', 'ohmylms' ),
							render: (
								<Link
									to={ `/quizzes/${ row.id }` }
									aria-label={ sprintf(
										/* translators: %s: quiz title. */
										__( 'Settings for %s', 'ohmylms' ),
										row.title
									) }
								>
									{ __( 'Settings', 'ohmylms' ) }
								</Link>
							),
						},
					] }
				/>
			) }
		</>
	);
}
