import { Table } from '../../../ui/Table';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
import { enroll, setBlocked, unenroll } from './api';
import { enrollmentStatus, formatDate, validateEnrollment } from './model';
import { reportPath } from './report';
import { useOperation } from './useOperation';
import { useCourseOptions, useEnrollments, useStudent } from './useStudents';
import type { Enrollment, ProgressFilter } from './types';

type Confirmation =
	{ kind: 'block' } | { kind: 'unenroll'; enrollment: Enrollment };

function Detail( { label, value }: { label: string; value: string } ) {
	return (
		<>
			<dt>{ label }</dt>
			<dd>{ value || '—' }</dd>
		</>
	);
}

/**
 * Profile of one student: details, access, enrolments with progress, enrol and unenrol.
 *
 * @param props           Component props.
 * @param props.studentId Student ID. Remount (use `key`) when it changes.
 */
export function StudentProfile( { studentId }: { studentId: number } ) {
	const profile = useStudent( studentId );
	const enrollments = useEnrollments( studentId );
	const operation = useOperation();
	const [ courseSearch, setCourseSearch ] = useState( '' );
	const [ courseQuery, setCourseQuery ] = useState( '' );
	const [ enrolCourse, setEnrolCourse ] = useState( '' );
	const [ enrolError, setEnrolError ] = useState< string | null >( null );
	const [ enrolmentSearch, setEnrolmentSearch ] = useState( '' );
	const [ confirmation, setConfirmation ] = useState< Confirmation | null >(
		null
	);
	const [ message, setMessage ] = useState< string | null >( null );
	const courses = useCourseOptions( courseQuery );
	const student = profile.data;

	function refreshCounts() {
		profile.reload();
	}
	function submitEnrol() {
		const courseId = enrolCourse ? Number( enrolCourse ) : null;
		const problem = validateEnrollment( courseId );
		setEnrolError( problem );
		if ( problem || null === courseId ) {
			return;
		}
		void operation.run( async ( signal ) => {
			const created = await enroll( studentId, courseId, signal );
			if ( signal.aborted ) {
				return;
			}
			setMessage(
				sprintf(
					/* translators: %s: course title. */
					__( 'Enrolled in %s.', 'ohmylms' ),
					created.title
				)
			);
			setEnrolCourse( '' );
			enrollments.reload();
			refreshCounts();
		} );
	}
	function confirm() {
		if ( ! confirmation ) {
			return;
		}
		const current = confirmation;
		void operation.run( async ( signal ) => {
			if ( 'block' === current.kind ) {
				const updated = await setBlocked( studentId, true, signal );
				if ( signal.aborted ) {
					return;
				}
				profile.replace( updated );
				enrollments.reload();
				setMessage( __( 'Student blocked.', 'ohmylms' ) );
			} else {
				await unenroll(
					studentId,
					current.enrollment.course_id,
					signal
				);
				if ( signal.aborted ) {
					return;
				}
				setMessage(
					sprintf(
						/* translators: %s: course title. */
						__( 'Unenrolled from %s.', 'ohmylms' ),
						current.enrollment.title
					)
				);
				if (
					1 === ( enrollments.data?.items.length ?? 0 ) &&
					enrollments.query.page > 1
				) {
					enrollments.setPage( enrollments.query.page - 1 );
				} else {
					enrollments.reload();
				}
				refreshCounts();
			}
			setConfirmation( null );
		} );
	}
	function unblock() {
		void operation.run( async ( signal ) => {
			const updated = await setBlocked( studentId, false, signal );
			if ( signal.aborted ) {
				return;
			}
			profile.replace( updated );
			enrollments.reload();
			setMessage( __( 'Student unblocked.', 'ohmylms' ) );
		} );
	}

	const back = (
		<p>
			<Link to="/students">{ __( 'All students', 'ohmylms' ) }</Link>
			{ ' · ' }
			<Link to={ reportPath( studentId ) }>
				{ __( 'Quiz, assignment and skill report', 'ohmylms' ) }
			</Link>
		</p>
	);
	if ( profile.error && ! student ) {
		return (
			<>
				{ back }
				<h2>{ __( 'Student', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ profile.error.message }{ ' ' }
					{ 404 !== profile.error.status && (
						<Button variant="link" onClick={ profile.reload }>
							{ __( 'Try again', 'ohmylms' ) }
						</Button>
					) }
				</Notice>
			</>
		);
	}
	if ( ! student ) {
		return (
			<>
				{ back }
				<p role="status">{ __( 'Loading student…', 'ohmylms' ) }</p>
			</>
		);
	}

	const items = enrollments.data?.items ?? [];
	const confirmTitle =
		'block' === confirmation?.kind
			? __( 'Block student', 'ohmylms' )
			: __( 'Unenrol student', 'ohmylms' );

	return (
		<>
			{ back }
			<h2>{ student.name }</h2>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ operation.error && ! confirmation && (
				<Notice status="error" onRemove={ operation.clearError }>
					{ operation.error }
				</Notice>
			) }
			<dl>
				<Detail
					label={ __( 'Email', 'ohmylms' ) }
					value={ student.email }
				/>
				<Detail
					label={ __( 'Registered', 'ohmylms' ) }
					value={ formatDate( student.registered ) }
				/>
				<Detail
					label={ __( 'Last login', 'ohmylms' ) }
					value={ formatDate( student.last_login ) }
				/>
				<Detail
					label={ __( 'Access', 'ohmylms' ) }
					value={
						student.blocked
							? __( 'Blocked', 'ohmylms' )
							: __( 'Active', 'ohmylms' )
					}
				/>
				<Detail
					label={ __( 'Courses', 'ohmylms' ) }
					value={ sprintf(
						/* translators: 1: completed courses, 2: enrolled courses. */
						__( '%1$d of %2$d completed', 'ohmylms' ),
						student.completed_courses,
						student.enrolled_courses
					) }
				/>
			</dl>
			{ student.blocked ? (
				<Button
					variant="secondary"
					disabled={ operation.busy }
					onClick={ unblock }
				>
					{ __( 'Unblock student', 'ohmylms' ) }
				</Button>
			) : (
				<Button
					variant="secondary"
					isDestructive
					disabled={ operation.busy }
					onClick={ () => {
						operation.clearError();
						setConfirmation( { kind: 'block' } );
					} }
				>
					{ __( 'Block student', 'ohmylms' ) }
				</Button>
			) }

			<h3>{ __( 'Enrol in a course', 'ohmylms' ) }</h3>
			{ student.blocked ? (
				<p>
					{ __(
						'Unblock the student before enrolling them.',
						'ohmylms'
					) }
				</p>
			) : (
				<>
					<form
						role="search"
						aria-label={ __( 'Find a course', 'ohmylms' ) }
						onSubmit={ ( event ) => {
							event.preventDefault();
							setCourseQuery( courseSearch );
							setEnrolCourse( '' );
						} }
					>
						<TextControl
							label={ __( 'Find a course', 'ohmylms' ) }
							value={ courseSearch }
							onChange={ setCourseSearch }
						/>
						<Button
							type="submit"
							variant="secondary"
							disabled={ courses.loading }
						>
							{ __( 'Find', 'ohmylms' ) }
						</Button>
					</form>
					<form
						onSubmit={ ( event ) => {
							event.preventDefault();
							submitEnrol();
						} }
					>
						<SelectControl
							label={ __( 'Course', 'ohmylms' ) }
							value={ enrolCourse }
							options={ [
								{
									value: '',
									label: courses.loading
										? __( 'Loading courses…', 'ohmylms' )
										: __( 'Choose a course', 'ohmylms' ),
								},
								...( courses.data?.items ?? [] ).map(
									( course ) => ( {
										value: String( course.id ),
										label:
											'publish' === course.status
												? course.title
												: sprintf(
														/* translators: 1: course title, 2: post status such as draft. */
														__(
															'%1$s (%2$s)',
															'ohmylms'
														),
														course.title,
														course.status
													),
									} )
								),
							] }
							onChange={ ( value ) => {
								setEnrolCourse( value );
								setEnrolError( null );
							} }
						/>
						{ courses.error && (
							<Notice status="warning" isDismissible={ false }>
								{ courses.error.message }
							</Notice>
						) }
						{ enrolError && <p role="alert">{ enrolError }</p> }
						<Button
							type="submit"
							variant="primary"
							isBusy={ operation.busy }
							disabled={ operation.busy }
						>
							{ __( 'Enrol student', 'ohmylms' ) }
						</Button>
					</form>
				</>
			) }

			<h3>{ __( 'Enrolments', 'ohmylms' ) }</h3>
			<form
				role="search"
				aria-label={ __( 'Search enrolments', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					enrollments.setSearch( enrolmentSearch );
				} }
			>
				<TextControl
					label={ __( 'Search enrolments', 'ohmylms' ) }
					value={ enrolmentSearch }
					onChange={ setEnrolmentSearch }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ enrollments.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			<SelectControl
				label={ __( 'Progress', 'ohmylms' ) }
				value={ enrollments.query.progress }
				options={ [
					{ value: 'all', label: __( 'All', 'ohmylms' ) },
					{ value: 'running', label: __( 'In progress', 'ohmylms' ) },
					{ value: 'completed', label: __( 'Completed', 'ohmylms' ) },
				] }
				onChange={ ( value ) =>
					enrollments.setProgress( value as ProgressFilter )
				}
			/>
			{ enrollments.error && (
				<Notice status="error" isDismissible={ false }>
					{ enrollments.error.message }{ ' ' }
					<Button variant="link" onClick={ enrollments.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ enrollments.loading && (
				<p role="status">{ __( 'Loading enrolments…', 'ohmylms' ) }</p>
			) }
			{ ! enrollments.loading &&
				! enrollments.error &&
				( items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: number of enrolments. */
								_n(
									'%d enrolment found.',
									'%d enrolments found.',
									enrollments.data?.total ?? 0,
									'ohmylms'
								),
								enrollments.data?.total ?? 0
							) }
						</p>
						<Table
							caption={ __(
								'Enrolments and progress',
								'ohmylms'
							) }
							striped
						>
							<thead>
								<tr>
									{ [
										__( 'Course', 'ohmylms' ),
										__( 'Status', 'ohmylms' ),
										__( 'Progress', 'ohmylms' ),
										__( 'Enrolled', 'ohmylms' ),
										__( 'Actions', 'ohmylms' ),
									].map( ( title ) => (
										<th key={ title } scope="col">
											{ title }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ items.map( ( item ) => (
									<tr key={ item.course_id }>
										<th scope="row">{ item.title }</th>
										<td>{ enrollmentStatus( item ) }</td>
										<td>
											<progress
												max={ 100 }
												value={ item.percent_complete }
												aria-hidden="true"
											/>{ ' ' }
											{ sprintf(
												/* translators: %d: percent complete. */
												__( '%d%%', 'ohmylms' ),
												item.percent_complete
											) }
											<br />
											{ sprintf(
												/* translators: 1: completed lessons, 2: lessons, 3: completed quizzes, 4: quizzes, 5: completed assignments, 6: assignments. */
												__(
													'Lessons %1$d/%2$d, quizzes %3$d/%4$d, assignments %5$d/%6$d',
													'ohmylms'
												),
												item.lessons.completed,
												item.lessons.total,
												item.quizzes.completed,
												item.quizzes.total,
												item.assignments.completed,
												item.assignments.total
											) }
										</td>
										<td>
											{ formatDate( item.enrolled_at ) }
										</td>
										<td>
											<Button
												variant="link"
												isDestructive
												disabled={ operation.busy }
												aria-label={ sprintf(
													/* translators: %s: course title. */
													__(
														'Unenrol from %s',
														'ohmylms'
													),
													item.title
												) }
												onClick={ () => {
													operation.clearError();
													setConfirmation( {
														kind: 'unenroll',
														enrollment: item,
													} );
												} }
											>
												{ __( 'Unenrol', 'ohmylms' ) }
											</Button>
										</td>
									</tr>
								) ) }
							</tbody>
						</Table>
						<Pagination
							page={ enrollments.query.page }
							pages={ enrollments.data?.totalPages ?? 1 }
							busy={ enrollments.loading || operation.busy }
							onChange={ enrollments.setPage }
						/>
					</>
				) : (
					<p>{ __( 'No enrolments found.', 'ohmylms' ) }</p>
				) ) }

			{ confirmation && (
				<Modal
					title={ confirmTitle }
					onRequestClose={ () => {
						if ( ! operation.busy ) {
							setConfirmation( null );
						}
					} }
					shouldCloseOnClickOutside={ false }
				>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<p>
						{ 'block' === confirmation.kind
							? sprintf(
									/* translators: %s: student name. */
									__(
										'Block %s? Their enrolments stay, but they lose access to every course until you unblock them.',
										'ohmylms'
									),
									student.name
								)
							: sprintf(
									/* translators: 1: student name, 2: course title. */
									__(
										'Unenrol %1$s from %2$s? Their progress in this course is permanently deleted.',
										'ohmylms'
									),
									student.name,
									confirmation.enrollment.title
								) }
					</p>
					<Button
						variant="primary"
						isDestructive
						isBusy={ operation.busy }
						disabled={ operation.busy }
						onClick={ confirm }
					>
						{ confirmTitle }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ () => setConfirmation( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
