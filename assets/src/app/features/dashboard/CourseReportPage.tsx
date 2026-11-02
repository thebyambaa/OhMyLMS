import { Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { ListTable } from '../../../ui/ListTable';
import { statusViews } from '../../components/listViews';
import { EarningsChart } from './EarningsChart';
import { useCourseStudents, useRange, useSingleCourseReport } from './hooks';
import {
	buildCourseCards,
	describeRange,
	formatDate,
	formatNumber,
	parseCourseId,
	studentStatus,
} from './model';
import { RangeFilter } from './RangeFilter';
import { RequestStatus } from './RequestStatus';
import { SummaryCards } from './SummaryCards';
import type { StudentOrderBy, StudentProgressFilter } from './types';

function Students( { courseId }: { courseId: number } ) {
	const list = useCourseStudents( courseId );
	const [ text, setText ] = useState( '' );
	const items = list.data?.items ?? [];
	const total = list.data?.total ?? 0;

	return (
		<section aria-label={ __( 'Students', 'ohmylms' ) }>
			<h3>{ __( 'Students', 'ohmylms' ) }</h3>
			<p>
				<Link to={ `/reports/courses/${ courseId }/students` }>
					{ __(
						'Manage roster: enrol, unenrol and send reminders',
						'ohmylms'
					) }
				</Link>
			</p>
			<RequestStatus
				loading={ list.loading }
				error={ list.error }
				label={ __( 'Loading students…', 'ohmylms' ) }
				onRetry={ list.reload }
			/>
			{ ! list.loading && ! list.error && (
				<ListTable
					caption={ __( 'Students of this course', 'ohmylms' ) }
					formLabel={ __( 'Search students', 'ohmylms' ) }
					rows={ items }
					rowKey={ ( student ) => student.user_id }
					emptyMessage={ __( 'No students found.', 'ohmylms' ) }
					views={ statusViews(
						[
							{
								value: 'all',
								label: __( 'All students', 'ohmylms' ),
							},
							{
								value: 'running',
								label: __( 'In progress', 'ohmylms' ),
							},
							{
								value: 'completed',
								label: __( 'Completed', 'ohmylms' ),
							},
						],
						list.query.progress,
						total,
						( value ) =>
							list.setProgress( value as StudentProgressFilter )
					) }
					search={ {
						label: __( 'Search students', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						placeholder: __( 'Name, username or email', 'ohmylms' ),
						value: text,
						onChange: setText,
						onSubmit: () => list.setSearch( text ),
					} }
					sort={ {
						orderby: list.query.orderby,
						order: list.query.order,
						onChange: ( orderby, order ) =>
							list.setSort( orderby as StudentOrderBy, order ),
					} }
					pagination={ {
						page: list.query.page,
						totalPages: list.data?.totalPages ?? 1,
						totalItems: total,
						itemsLabel: sprintf(
							/* translators: %d: number of students. */
							_n(
								'%d student found.',
								'%d students found.',
								total,
								'ohmylms'
							),
							total
						),
						onChange: list.setPage,
					} }
					columns={ [
						{
							id: 'name',
							label: __( 'Name', 'ohmylms' ),
							primary: true,
							sortable: 'asc',
							render: ( student ) => (
								<strong>{ student.name }</strong>
							),
						},
						{
							id: 'email',
							label: __( 'Email', 'ohmylms' ),
							render: ( student ) => student.email,
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( student ) => studentStatus( student ),
						},
						{
							id: 'percent',
							label: __( 'Progress', 'ohmylms' ),
							sortable: 'desc',
							render: ( student ) => (
								<>
									<progress
										max={ 100 }
										value={ student.percent_complete }
										aria-label={ sprintf(
											/* translators: %s: student name. */
											__( 'Progress of %s', 'ohmylms' ),
											student.name
										) }
									/>{ ' ' }
									{ student.percent_complete }%{ ' ' }
									<span className="description">
										{ sprintf(
											/* translators: 1: completed lessons, 2: lessons, 3: completed quizzes, 4: quizzes. */
											__(
												'(%1$d of %2$d lessons, %3$d of %4$d quizzes)',
												'ohmylms'
											),
											student.lessons.completed,
											student.lessons.total,
											student.quizzes.completed,
											student.quizzes.total
										) }
									</span>
								</>
							),
						},
						{
							id: 'enrolled_at',
							label: __( 'Enrolled', 'ohmylms' ),
							sortable: 'desc',
							render: ( student ) =>
								formatDate( student.enrolled_at ),
						},
						{
							id: 'completed_at',
							label: __( 'Completed on', 'ohmylms' ),
							render: ( student ) =>
								formatDate( student.completed_at ),
						},
					] }
				/>
			) }
		</section>
	);
}

function Report( { courseId }: { courseId: number } ) {
	const { range, setRange } = useRange();
	const request = useSingleCourseReport( courseId, range );
	const data = request.data;

	return (
		<>
			<RangeFilter value={ range } onChange={ setRange } />
			<RequestStatus
				loading={ request.loading }
				error={ request.error }
				label={ __( 'Loading course report…', 'ohmylms' ) }
				onRetry={ request.reload }
			/>
			{ data && (
				<div aria-busy={ request.loading }>
					<h2>{ data.course.title }</h2>
					<p>
						{ data.course.permalink && (
							<>
								<a href={ data.course.permalink }>
									{ __( 'View course', 'ohmylms' ) }
								</a>
								{ ' · ' }
							</>
						) }
						{ sprintf(
							/* translators: 1: chapters, 2: lessons, 3: quizzes, 4: assignments. */
							__(
								'%1$d chapters, %2$d lessons, %3$d quizzes, %4$d assignments.',
								'ohmylms'
							),
							data.content.chapters,
							data.content.lessons,
							data.content.quizzes,
							data.content.assignments
						) }
					</p>
					<p>
						{ sprintf(
							/* translators: %s: date range. */
							__(
								'Showing %s, compared with the period before.',
								'ohmylms'
							),
							describeRange( data.range )
						) }
					</p>
					<SummaryCards
						cards={ buildCourseCards(
							data.summary.current,
							data.summary.previous,
							data.currency
						) }
					/>
					<p>
						{ sprintf(
							/* translators: 1: enrolled students, 2: new students in the range, 3: completed, 4: in progress. */
							__(
								'%1$s students enrolled (%2$s new in this period): %3$s completed, %4$s in progress.',
								'ohmylms'
							),
							formatNumber( data.students.enrolled ),
							formatNumber( data.students.in_range ),
							formatNumber( data.students.completed ),
							formatNumber( data.students.in_progress )
						) }
					</p>
					<EarningsChart
						title={ __( 'Course earnings', 'ohmylms' ) }
						series={ data.series }
						currency={ data.currency }
						granularity={ data.range.granularity }
					/>
				</div>
			) }
			<Students courseId={ courseId } />
		</>
	);
}

/** The hidden `/reports/courses/:id` route: one course's figures and students. */
export default function CourseReportPage() {
	const params = useParams();
	const courseId = parseCourseId( params.id );
	return (
		<>
			<p>
				<Link to="/earnings">
					{ __( '← Earnings report', 'ohmylms' ) }
				</Link>
			</p>
			{ null === courseId ? (
				<Notice status="error" isDismissible={ false }>
					{ __( 'That is not a valid course.', 'ohmylms' ) }
				</Notice>
			) : (
				<Report key={ courseId } courseId={ courseId } />
			) }
		</>
	);
}
