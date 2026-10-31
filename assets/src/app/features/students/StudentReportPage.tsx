import { Button, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import { Link, useParams } from 'react-router-dom';
import {
	ListTable,
	ListTableSelect,
	type ListTableColumn,
} from '../../../ui/ListTable';
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
 * One paged list of the report, as a WordPress list table.
 *
 * @param props           Component props.
 * @param props.title     Section heading.
 * @param props.load      Loads a page.
 * @param props.empty     Text when there are no rows.
 * @param props.columns   List-table columns (the first is the primary column).
 * @param props.rowKey    Key of a row.
 * @param props.filters   Filter controls for the list's navigation; a change of `filterKey` resets the page.
 * @param props.filterKey Serialised filter.
 */
function Section< T >( {
	title,
	load,
	empty,
	columns,
	rowKey,
	filters,
	filterKey = '',
}: {
	title: string;
	load: ( page: number, signal: AbortSignal ) => Promise< Collection< T > >;
	empty: string;
	columns: ReadonlyArray< ListTableColumn< T > >;
	rowKey: ( item: T ) => string | number;
	filters?: ReactNode;
	filterKey?: string;
} ) {
	const [ page, setPage ] = useState( { filterKey, page: 1 } );
	const current = page.filterKey === filterKey ? page.page : 1;
	const request = useRequest(
		( signal ) => load( current, signal ),
		`${ filterKey }:${ current }`
	);
	return (
		<section aria-label={ title }>
			<h3>{ title }</h3>
			<Status
				loading={ request.loading && ! request.data }
				error={ request.error }
				onRetry={ request.reload }
				label={ __( 'Loading…', 'ohmylms' ) }
			/>
			{ ! request.error && ( request.data || filters ) && (
				<ListTable< T >
					caption={ title }
					busy={ request.loading }
					footer={ false }
					rows={ request.data?.items ?? [] }
					rowKey={ rowKey }
					columns={ columns }
					emptyMessage={ request.loading ? '' : empty }
					filters={ filters }
					pagination={ {
						page: current,
						totalPages: request.data?.totalPages ?? 1,
						totalItems: request.data?.total,
						onChange: ( next ) =>
							setPage( { filterKey, page: next } ),
					} }
				/>
			) }
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
				rowKey={ ( attempt ) => attempt.id }
				columns={ [
					{
						id: 'quiz',
						label: __( 'Quiz', 'ohmylms' ),
						primary: true,
						render: ( attempt ) =>
							attempt.quiz_id > 0 ? (
								<Link
									to={ `/quizzes/${ attempt.quiz_id }/attempts` }
								>
									{ attempt.quiz_title ||
										__( '(deleted quiz)', 'ohmylms' ) }
								</Link>
							) : (
								__( '(deleted quiz)', 'ohmylms' )
							),
					},
					{
						id: 'course',
						label: __( 'Course', 'ohmylms' ),
						render: ( attempt ) => attempt.course_title || '—',
					},
					{
						id: 'status',
						label: __( 'Status', 'ohmylms' ),
						render: ( attempt ) => attemptStatus( attempt.status ),
					},
					{
						id: 'score',
						label: __( 'Score', 'ohmylms' ),
						render: ( attempt ) =>
							sprintf(
								/* translators: 1: score, 2: maximum marks, 3: percentage. */
								__( '%1$s / %2$s (%3$s)', 'ohmylms' ),
								String( attempt.score ),
								String( attempt.max_marks ),
								formatPercent( attempt.percentage )
							),
					},
					{
						id: 'date',
						label: __( 'Submitted', 'ohmylms' ),
						render: ( attempt ) =>
							formatDate( attempt.submitted_at ) || '—',
					},
				] }
			/>

			<Section
				title={ __( 'Assignments', 'ohmylms' ) }
				load={ ( page, signal ) =>
					listSubmissions( studentId, page, signal )
				}
				empty={ __( 'No assignment submissions yet.', 'ohmylms' ) }
				rowKey={ ( submission ) => submission.id }
				columns={ [
					{
						id: 'assignment',
						label: __( 'Assignment', 'ohmylms' ),
						primary: true,
						render: ( submission ) =>
							submission.assignment_id > 0 ? (
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
							),
					},
					{
						id: 'course',
						label: __( 'Course', 'ohmylms' ),
						render: ( submission ) =>
							submission.course_title || '—',
					},
					{
						id: 'status',
						label: __( 'Status', 'ohmylms' ),
						render: ( submission ) => (
							<>
								{ 'submitted' === submission.status &&
									__( 'Waiting for a grade', 'ohmylms' ) }
								{ 'passed' === submission.result &&
									__( 'Passed', 'ohmylms' ) }
								{ 'failed' === submission.result &&
									__( 'Failed', 'ohmylms' ) }
								{ 'graded' === submission.status &&
									null === submission.result &&
									__( 'Graded', 'ohmylms' ) }
							</>
						),
					},
					{
						id: 'score',
						label: __( 'Score', 'ohmylms' ),
						render: ( submission ) =>
							null === submission.score
								? '—'
								: sprintf(
										/* translators: 1: score, 2: maximum score. */
										__( '%1$d / %2$d', 'ohmylms' ),
										submission.score,
										submission.max_score
									),
					},
					{
						id: 'date',
						label: __( 'Submitted', 'ohmylms' ),
						render: ( submission ) =>
							formatDate( submission.submitted_at ) || '—',
					},
				] }
			/>

			<Section
				title={ __( 'Skill progress', 'ohmylms' ) }
				filterKey={ skillFilter }
				load={ ( page, signal ) =>
					listSkills( studentId, page, skillFilter, signal )
				}
				empty={ __( 'No skill evidence yet.', 'ohmylms' ) }
				filters={
					<ListTableSelect
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
				rowKey={ ( skill ) => skill.skill_id }
				columns={ [
					{
						id: 'skill',
						label: __( 'Skill', 'ohmylms' ),
						primary: true,
						render: ( skill ) => skill.name,
					},
					{
						id: 'level',
						label: __( 'Level', 'ohmylms' ),
						render: ( skill ) => (
							<>
								{ skill.level_label }
								{ skill.review_due &&
									` · ${ __( 'review due', 'ohmylms' ) }` }
								{ skill.struggling &&
									` · ${ __( 'struggling', 'ohmylms' ) }` }
							</>
						),
					},
					{
						id: 'accuracy',
						label: __( 'Recent accuracy', 'ohmylms' ),
						render: ( skill ) =>
							formatPercent(
								Math.round( skill.recent_accuracy * 1000 ) / 10
							),
					},
					{
						id: 'evidence',
						label: __( 'Evidence', 'ohmylms' ),
						className: 'num',
						render: ( skill ) => skill.evidence_count,
					},
					{
						id: 'date',
						label: __( 'Last practised', 'ohmylms' ),
						render: ( skill ) =>
							formatDate( skill.last_evidence_at ) || '—',
					},
				] }
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
