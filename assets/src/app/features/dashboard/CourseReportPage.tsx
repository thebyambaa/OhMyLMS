import {
	Button,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { Table } from '../../../ui/Table';
import { Pagination } from '../../components/Pagination';
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
import { SortHeader } from './SortHeader';
import { SummaryCards } from './SummaryCards';
import type { StudentProgressFilter } from './types';

function Students( { courseId }: { courseId: number } ) {
	const list = useCourseStudents( courseId );
	const [ text, setText ] = useState( '' );
	const items = list.data?.items ?? [];
	const total = list.data?.total ?? 0;
	const sort = { orderby: list.query.orderby, order: list.query.order };

	return (
		<section aria-label={ __( 'Students', 'ohmylms' ) }>
			<h3>{ __( 'Students', 'ohmylms' ) }</h3>
			<form
				role="search"
				aria-label={ __( 'Search students', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					list.setSearch( text );
				} }
			>
				<TextControl
					label={ __( 'Search students', 'ohmylms' ) }
					help={ __( 'Name, username or email.', 'ohmylms' ) }
					value={ text }
					onChange={ setText }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ list.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			<SelectControl
				label={ __( 'Progress', 'ohmylms' ) }
				value={ list.query.progress }
				options={ [
					{ value: 'all', label: __( 'All students', 'ohmylms' ) },
					{ value: 'running', label: __( 'In progress', 'ohmylms' ) },
					{ value: 'completed', label: __( 'Completed', 'ohmylms' ) },
				] }
				onChange={ ( value ) =>
					list.setProgress( value as StudentProgressFilter )
				}
			/>
			<RequestStatus
				loading={ list.loading }
				error={ list.error }
				label={ __( 'Loading students…', 'ohmylms' ) }
				onRetry={ list.reload }
			/>
			{ ! list.loading &&
				! list.error &&
				( items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: number of students. */
								_n(
									'%d student found.',
									'%d students found.',
									total,
									'ohmylms'
								),
								total
							) }
						</p>
						<Table
							caption={ __(
								'Students of this course',
								'ohmylms'
							) }
							striped
							compact
						>
							<thead>
								<tr>
									<SortHeader
										label={ __( 'Name', 'ohmylms' ) }
										field="name"
										first="asc"
										{ ...sort }
										onSort={ list.setSort }
									/>
									<th scope="col">
										{ __( 'Email', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Status', 'ohmylms' ) }
									</th>
									<SortHeader
										label={ __( 'Progress', 'ohmylms' ) }
										field="percent"
										{ ...sort }
										onSort={ list.setSort }
									/>
									<SortHeader
										label={ __( 'Enrolled', 'ohmylms' ) }
										field="enrolled_at"
										{ ...sort }
										onSort={ list.setSort }
									/>
									<th scope="col">
										{ __( 'Completed on', 'ohmylms' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ items.map( ( student ) => (
									<tr key={ student.user_id }>
										<th scope="row">{ student.name }</th>
										<td>{ student.email }</td>
										<td>{ studentStatus( student ) }</td>
										<td>
											<progress
												max={ 100 }
												value={
													student.percent_complete
												}
												aria-label={ sprintf(
													/* translators: %s: student name. */
													__(
														'Progress of %s',
														'ohmylms'
													),
													student.name
												) }
											/>{ ' ' }
											{ student.percent_complete }%{ ' ' }
											<small>
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
											</small>
										</td>
										<td>
											{ formatDate(
												student.enrolled_at
											) }
										</td>
										<td>
											{ formatDate(
												student.completed_at
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</Table>
						<Pagination
							page={ list.query.page }
							pages={ list.data?.totalPages ?? 1 }
							busy={ list.loading }
							onChange={ list.setPage }
						/>
					</>
				) : (
					<p>{ __( 'No students found.', 'ohmylms' ) }</p>
				) ) }
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
