import { Button, Notice, SelectControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Table } from '../../../ui/Table';
import { Pagination } from '../../components/Pagination';
import type { ApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { getStudent } from './api';
import { formatDate, parseStudentId, studentPath } from './model';
import {
	formatPercent,
	getReportSummary,
	listQuizAttempts,
	listSkills,
	listSubmissions,
} from './report';
import type { SkillFilter } from './report';
import { useRequest } from './useRequest';

function attemptStatus( status: string ): string {
	switch ( status ) {
		case 'in-progress':
			return __( 'In progress', 'ohmylms' );
		case 'in-review':
			return __( 'Needs grading', 'ohmylms' );
		case 'completed':
		case 'graded':
			return __( 'Submitted', 'ohmylms' );
		default:
			return status;
	}
}

function Status( {
	loading,
	error,
	onRetry,
	label,
}: {
	loading: boolean;
	error: ApiError | null;
	onRetry: () => void;
	label: string;
} ) {
	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error.message }{ ' ' }
				<Button variant="link" onClick={ onRetry }>
					{ __( 'Try again', 'ohmylms' ) }
				</Button>
			</Notice>
		);
	}
	return loading ? <p role="status">{ label }</p> : null;
}

/**
 * One paged table of the report.
 *
 * @param props           Component props.
 * @param props.title     Section heading.
 * @param props.load      Loads a page.
 * @param props.empty     Text when there are no rows.
 * @param props.columns   Column headings.
 * @param props.row       Renders one row.
 * @param props.extra     Controls shown under the heading; a change of `filterKey` resets the page.
 * @param props.filterKey Serialised filter.
 */
function Section< T >( {
	title,
	load,
	empty,
	columns,
	row,
	extra,
	filterKey = '',
}: {
	title: string;
	load: ( page: number, signal: AbortSignal ) => Promise< Collection< T > >;
	empty: string;
	columns: string[];
	row: ( item: T ) => ReactNode;
	extra?: ReactNode;
	filterKey?: string;
} ) {
	const [ page, setPage ] = useState( { filterKey, page: 1 } );
	const current = page.filterKey === filterKey ? page.page : 1;
	const request = useRequest(
		( signal ) => load( current, signal ),
		`${ filterKey }:${ current }`
	);
	const items = request.data?.items ?? [];
	return (
		<section aria-label={ title }>
			<h3>{ title }</h3>
			{ extra }
			<Status
				loading={ request.loading }
				error={ request.error }
				onRetry={ request.reload }
				label={ __( 'Loading…', 'ohmylms' ) }
			/>
			{ ! request.loading &&
				! request.error &&
				( items.length ? (
					<>
						<Table caption={ title } striped compact>
							<thead>
								<tr>
									{ columns.map( ( column ) => (
										<th key={ column } scope="col">
											{ column }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>{ items.map( row ) }</tbody>
						</Table>
						<Pagination
							page={ current }
							pages={ request.data?.totalPages ?? 1 }
							busy={ request.loading }
							onChange={ ( next ) =>
								setPage( { filterKey, page: next } )
							}
						/>
					</>
				) : (
					<p>{ empty }</p>
				) ) }
		</section>
	);
}

function Report( { studentId }: { studentId: number } ) {
	const student = useRequest(
		( signal ) => getStudent( studentId, signal ),
		String( studentId )
	);
	const summary = useRequest(
		( signal ) => getReportSummary( studentId, signal ),
		String( studentId )
	);
	const [ skillFilter, setSkillFilter ] = useState< SkillFilter >( 'all' );

	if ( student.error && ! student.data ) {
		return (
			<Status
				loading={ false }
				error={ student.error }
				onRetry={ student.reload }
				label=""
			/>
		);
	}
	const data = summary.data;
	return (
		<>
			<p>
				<Link to={ studentPath( studentId ) }>
					{ __( '← Student profile', 'ohmylms' ) }
				</Link>
			</p>
			<h2>
				{ student.data
					? sprintf(
							/* translators: %s: student name. */
							__( 'Report: %s', 'ohmylms' ),
							student.data.name
						)
					: __( 'Student report', 'ohmylms' ) }
			</h2>
			<Status
				loading={ summary.loading }
				error={ summary.error }
				onRetry={ summary.reload }
				label={ __( 'Loading report…', 'ohmylms' ) }
			/>
			{ data && (
				<dl aria-label={ __( 'Summary', 'ohmylms' ) }>
					<dt>{ __( 'Courses', 'ohmylms' ) }</dt>
					<dd>
						{ sprintf(
							/* translators: 1: enrolled, 2: in progress, 3: completed. */
							__(
								'%1$d enrolled, %2$d in progress, %3$d completed',
								'ohmylms'
							),
							data.courses.enrolled,
							data.courses.in_progress,
							data.courses.completed
						) }
					</dd>
					<dt>{ __( 'Quizzes', 'ohmylms' ) }</dt>
					<dd>
						{ sprintf(
							/* translators: 1: submitted attempts, 2: attempts waiting for grading, 3: average score. */
							__(
								'%1$d submitted attempts, %2$d need grading, average %3$s',
								'ohmylms'
							),
							data.quizzes.attempts,
							data.quizzes.in_review,
							formatPercent( data.quizzes.average_percent )
						) }
					</dd>
					<dt>{ __( 'Assignments', 'ohmylms' ) }</dt>
					<dd>
						{ sprintf(
							/* translators: 1: submissions, 2: graded, 3: passed, 4: failed. */
							__(
								'%1$d submitted, %2$d graded (%3$d passed, %4$d failed)',
								'ohmylms'
							),
							data.assignments.submitted,
							data.assignments.graded,
							data.assignments.passed,
							data.assignments.failed
						) }
					</dd>
					<dt>{ __( 'Skills', 'ohmylms' ) }</dt>
					<dd>
						{ sprintf(
							/* translators: 1: skills with evidence, 2: due for review, 3: struggling. */
							__(
								'%1$d practised, %2$d due for review, %3$d struggling',
								'ohmylms'
							),
							data.skills.total,
							data.skills.review_due,
							data.skills.struggling
						) }
					</dd>
				</dl>
			) }

			<Section
				title={ __( 'Quiz attempts', 'ohmylms' ) }
				load={ ( page, signal ) =>
					listQuizAttempts( studentId, page, signal )
				}
				empty={ __( 'No quiz attempts yet.', 'ohmylms' ) }
				columns={ [
					__( 'Quiz', 'ohmylms' ),
					__( 'Course', 'ohmylms' ),
					__( 'Status', 'ohmylms' ),
					__( 'Score', 'ohmylms' ),
					__( 'Submitted', 'ohmylms' ),
				] }
				row={ ( attempt ) => (
					<tr key={ attempt.id }>
						<th scope="row">
							{ attempt.quiz_id > 0 ? (
								<Link
									to={ `/quizzes/${ attempt.quiz_id }/attempts` }
								>
									{ attempt.quiz_title ||
										__( '(deleted quiz)', 'ohmylms' ) }
								</Link>
							) : (
								__( '(deleted quiz)', 'ohmylms' )
							) }
						</th>
						<td>{ attempt.course_title || '—' }</td>
						<td>{ attemptStatus( attempt.status ) }</td>
						<td>
							{ sprintf(
								/* translators: 1: score, 2: maximum marks, 3: percentage. */
								__( '%1$s / %2$s (%3$s)', 'ohmylms' ),
								String( attempt.score ),
								String( attempt.max_marks ),
								formatPercent( attempt.percentage )
							) }
						</td>
						<td>{ formatDate( attempt.submitted_at ) || '—' }</td>
					</tr>
				) }
			/>

			<Section
				title={ __( 'Assignments', 'ohmylms' ) }
				load={ ( page, signal ) =>
					listSubmissions( studentId, page, signal )
				}
				empty={ __( 'No assignment submissions yet.', 'ohmylms' ) }
				columns={ [
					__( 'Assignment', 'ohmylms' ),
					__( 'Course', 'ohmylms' ),
					__( 'Status', 'ohmylms' ),
					__( 'Score', 'ohmylms' ),
					__( 'Submitted', 'ohmylms' ),
				] }
				row={ ( submission ) => (
					<tr key={ submission.id }>
						<th scope="row">
							{ submission.assignment_id > 0 ? (
								<Link
									to={ `/assignments/${ submission.assignment_id }/submissions/${ submission.id }` }
								>
									{ submission.assignment_title ||
										__(
											'(deleted assignment)',
											'ohmylms'
										) }
								</Link>
							) : (
								__( '(deleted assignment)', 'ohmylms' )
							) }
						</th>
						<td>{ submission.course_title || '—' }</td>
						<td>
							{ 'submitted' === submission.status &&
								__( 'Waiting for a grade', 'ohmylms' ) }
							{ 'passed' === submission.result &&
								__( 'Passed', 'ohmylms' ) }
							{ 'failed' === submission.result &&
								__( 'Failed', 'ohmylms' ) }
							{ 'graded' === submission.status &&
								null === submission.result &&
								__( 'Graded', 'ohmylms' ) }
						</td>
						<td>
							{ null === submission.score
								? '—'
								: sprintf(
										/* translators: 1: score, 2: maximum score. */
										__( '%1$d / %2$d', 'ohmylms' ),
										submission.score,
										submission.max_score
									) }
						</td>
						<td>
							{ formatDate( submission.submitted_at ) || '—' }
						</td>
					</tr>
				) }
			/>

			<Section
				title={ __( 'Skill progress', 'ohmylms' ) }
				filterKey={ skillFilter }
				load={ ( page, signal ) =>
					listSkills( studentId, page, skillFilter, signal )
				}
				empty={ __( 'No skill evidence yet.', 'ohmylms' ) }
				extra={
					<SelectControl
						label={ __( 'Show skills', 'ohmylms' ) }
						value={ skillFilter }
						options={ [
							{ value: 'all', label: __( 'All', 'ohmylms' ) },
							{
								value: 'review_due',
								label: __( 'Due for review', 'ohmylms' ),
							},
							{
								value: 'struggling',
								label: __( 'Struggling', 'ohmylms' ),
							},
						] }
						onChange={ ( value ) =>
							setSkillFilter( value as SkillFilter )
						}
					/>
				}
				columns={ [
					__( 'Skill', 'ohmylms' ),
					__( 'Level', 'ohmylms' ),
					__( 'Recent accuracy', 'ohmylms' ),
					__( 'Evidence', 'ohmylms' ),
					__( 'Last practised', 'ohmylms' ),
				] }
				row={ ( skill ) => (
					<tr key={ skill.skill_id }>
						<th scope="row">{ skill.name }</th>
						<td>
							{ skill.level_label }
							{ skill.review_due &&
								` · ${ __( 'review due', 'ohmylms' ) }` }
							{ skill.struggling &&
								` · ${ __( 'struggling', 'ohmylms' ) }` }
						</td>
						<td>
							{ formatPercent(
								Math.round( skill.recent_accuracy * 1000 ) / 10
							) }
						</td>
						<td>{ skill.evidence_count }</td>
						<td>{ formatDate( skill.last_evidence_at ) || '—' }</td>
					</tr>
				) }
			/>
		</>
	);
}

/** The hidden `/students/:id/report` route: one student's results. */
export default function StudentReportPage() {
	const params = useParams();
	const id = parseStudentId( params.id );
	if ( null === id ) {
		return (
			<>
				<p>
					<Link to="/students">
						{ __( 'All students', 'ohmylms' ) }
					</Link>
				</p>
				<Notice status="error" isDismissible={ false }>
					{ __( 'That is not a valid student.', 'ohmylms' ) }
				</Notice>
			</>
		);
	}
	return <Report key={ id } studentId={ id } />;
}
