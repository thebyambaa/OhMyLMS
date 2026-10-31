import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { ListTable } from '../../../ui/ListTable';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { ListState } from './ListControls';
import {
	assignClassWork,
	gradeSubmission,
	listClassCourses,
	listClassWork,
	listCourseActivities,
	listSubmissions,
} from './operationsApi';
import type { SchoolClass, Submission, TitledRef } from './types';
import { usePagedList } from './usePagedList';

const NO_COURSES = { items: [] as TitledRef[], total: 0, totalPages: 0 };

/**
 * Work distributed to a school class: what was assigned with completion, a form that assigns a course or
 * one of its activities to the current students (recipients must already have course access; nobody
 * is enrolled), and review and grading of written submissions.
 *
 * @param props      Component props.
 * @param props.item The class.
 */
export function ClassWorkSection( { item }: { item: SchoolClass } ) {
	const load = useCallback(
		( page: number, _search: string, signal: AbortSignal ) =>
			listClassWork( item.id, page, signal ),
		[ item.id ]
	);
	const list = usePagedList( load );
	const active = 'active' === item.status;

	return (
		<section aria-label={ __( 'Class work', 'ohmylms' ) }>
			<h3>{ __( 'Class work', 'ohmylms' ) }</h3>
			<ListState
				loading={ list.loading }
				error={ list.error }
				total={ list.data.total }
				loadingText={ __( 'Loading class work…', 'ohmylms' ) }
				emptyText={ __( 'No work assigned yet.', 'ohmylms' ) }
				onRetry={ list.reload }
			>
				<ListTable
					caption={ __( 'Work assigned to this class', 'ohmylms' ) }
					rows={ list.data.items }
					rowKey={ ( work ) => work.id }
					pagination={ {
						page: list.page,
						totalPages: list.data.totalPages,
						onChange: list.setPage,
					} }
					columns={ [
						{
							id: 'title',
							label: __( 'Work', 'ohmylms' ),
							primary: true,
							render: ( work ) => <strong>{ work.title }</strong>,
						},
						{
							id: 'course',
							label: __( 'Course or activity', 'ohmylms' ),
							render: ( work ) =>
								work.content_title
									? `${ work.course_title } › ${ work.content_title }`
									: work.course_title,
						},
						{
							id: 'due',
							label: __( 'Due (UTC)', 'ohmylms' ),
							render: ( work ) =>
								work.due_at
									? work.due_at
											.slice( 0, 16 )
											.replace( 'T', ' ' )
									: '—',
						},
						{
							id: 'completed',
							label: __( 'Completed', 'ohmylms' ),
							className: 'num',
							render: ( work ) =>
								sprintf(
									/* translators: 1: completed, 2: recipients. */
									__( '%1$d of %2$d', 'ohmylms' ),
									work.completed,
									work.recipients
								),
						},
					] }
				/>
			</ListState>
			{ active && (
				<AssignForm item={ item } onAssigned={ list.reload } />
			) }
			{ active && <Submissions item={ item } /> }
		</section>
	);
}

function AssignForm( {
	item,
	onAssigned,
}: {
	item: SchoolClass;
	onAssigned: () => void;
} ) {
	const operation = useOperation();
	const [ text, setText ] = useState( '' );
	const [ query, setQuery ] = useState< string | null >( null );
	const courses = useRequest(
		useCallback(
			( signal: AbortSignal ) =>
				null === query
					? Promise.resolve( NO_COURSES )
					: listClassCourses( item.id, query, signal ),
			[ item.id, query ]
		)
	);
	const [ course, setCourse ] = useState( 0 );
	const chosen = course || ( courses.data?.items[ 0 ]?.id ?? 0 );
	const activities = useRequest(
		useCallback(
			( signal: AbortSignal ) =>
				chosen
					? listCourseActivities( item.id, chosen, signal )
					: Promise.resolve( [] as TitledRef[] ),
			[ item.id, chosen ]
		)
	);
	const [ content, setContent ] = useState( 0 );
	const [ title, setTitle ] = useState( '' );
	const [ due, setDue ] = useState( '' );
	const [ prior, setPrior ] = useState( false );
	const [ done, setDone ] = useState( '' );

	return (
		<>
			<h4>{ __( 'Assign work', 'ohmylms' ) }</h4>
			<p>
				{ __(
					'Assigns a published course or one of its activities to the class’s current students. Every student must already have access to the course: this does not enrol anyone or create a purchase. Students who join later do not receive earlier work.',
					'ohmylms'
				) }
			</p>
			{ done && (
				<Notice status="success" onRemove={ () => setDone( '' ) }>
					{ done }
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			<form
				role="search"
				aria-label={ __( 'Find a course', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setCourse( 0 );
					setContent( 0 );
					setQuery( text.trim() );
				} }
			>
				<TextControl
					label={ __( 'Course title', 'ohmylms' ) }
					value={ text }
					onChange={ setText }
				/>
				<Button type="submit" variant="secondary">
					{ __( 'Find course', 'ohmylms' ) }
				</Button>
			</form>
			{ null !== query && courses.data && (
				<form
					noValidate
					aria-label={ __( 'Assign work', 'ohmylms' ) }
					onSubmit={ ( event ) => {
						event.preventDefault();
						if ( ! chosen ) {
							return;
						}
						void operation.run(
							( signal ) =>
								assignClassWork(
									item.id,
									{
										course_id: chosen,
										content_id: content,
										title,
										due_at: due,
										prior_completion: prior,
									},
									signal
								),
							( created ) => {
								setDone(
									sprintf(
										/* translators: %d: number of students. */
										__(
											'Assigned to %d students.',
											'ohmylms'
										),
										created.recipients
									)
								);
								setTitle( '' );
								setDue( '' );
								onAssigned();
							}
						);
					} }
				>
					<SelectControl
						label={ __( 'Course', 'ohmylms' ) }
						value={ String( chosen ) }
						disabled={ ! courses.data.items.length }
						options={
							courses.data.items.length
								? courses.data.items.map( ( row ) => ( {
										value: String( row.id ),
										label: row.title,
									} ) )
								: [
										{
											value: '0',
											label: __(
												'No published course found',
												'ohmylms'
											),
										},
									]
						}
						onChange={ ( value ) => {
							setCourse( Number( value ) );
							setContent( 0 );
						} }
					/>
					<SelectControl
						label={ __( 'Activity', 'ohmylms' ) }
						value={ String( content ) }
						options={ [
							{
								value: '0',
								label: __( 'The whole course', 'ohmylms' ),
							},
							...( activities.data ?? [] ).map( ( row ) => ( {
								value: String( row.id ),
								label: row.title,
							} ) ),
						] }
						onChange={ ( value ) => setContent( Number( value ) ) }
					/>
					<TextControl
						label={ __( 'Title (optional)', 'ohmylms' ) }
						value={ title }
						maxLength={ 190 }
						onChange={ setTitle }
					/>
					<TextControl
						label={ __(
							'Due (school time zone, optional)',
							'ohmylms'
						) }
						type="datetime-local"
						value={ due }
						onChange={ setDue }
					/>
					<CheckboxControl
						label={ __(
							'Count work students completed before it was assigned',
							'ohmylms'
						) }
						checked={ prior }
						onChange={ setPrior }
					/>
					<Button
						type="submit"
						variant="primary"
						disabled={ operation.busy || ! chosen }
						isBusy={ operation.busy }
					>
						{ __( 'Assign to current students', 'ohmylms' ) }
					</Button>
				</form>
			) }
		</>
	);
}

function Submissions( { item }: { item: SchoolClass } ) {
	const [ page, setPage ] = useState( 1 );
	const request = useRequest(
		useCallback(
			( signal: AbortSignal ) => listSubmissions( item.id, page, signal ),
			[ item.id, page ]
		)
	);
	const [ open, setOpen ] = useState< Submission | null >( null );

	return (
		<>
			<h4>{ __( 'Written submissions', 'ohmylms' ) }</h4>
			{ request.loading && (
				<p role="status">{ __( 'Loading submissions…', 'ohmylms' ) }</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
				</Notice>
			) }
			{ request.data &&
				( request.data.items.length ? (
					<ListTable
						caption={ __( 'Written submissions', 'ohmylms' ) }
						rows={ request.data.items }
						rowKey={ ( row ) =>
							`${ row.assignment_id }-${ row.id }`
						}
						pagination={ {
							page,
							totalPages: request.data.has_more ? page + 1 : page,
							onChange: setPage,
						} }
						columns={ [
							{
								id: 'student',
								label: __( 'Student', 'ohmylms' ),
								primary: true,
								render: ( row ) => row.student_name,
							},
							{
								id: 'work',
								label: __( 'Work', 'ohmylms' ),
								render: ( row ) => row.title,
							},
							{
								id: 'score',
								label: __( 'Score', 'ohmylms' ),
								render: ( row ) =>
									null === row.score
										? __( 'Not graded', 'ohmylms' )
										: sprintf(
												/* translators: 1: score, 2: total points. */
												__( '%1$d / %2$d', 'ohmylms' ),
												row.score,
												row.total_points
											),
							},
						] }
						rowActions={ ( row ) => [
							{
								key: 'edit',
								label: __( 'Review', 'ohmylms' ),
								onClick: () => setOpen( row ),
							},
						] }
					/>
				) : (
					<p>{ __( 'No written submissions yet.', 'ohmylms' ) }</p>
				) ) }
			{ open && (
				<GradeForm
					key={ `${ open.assignment_id }-${ open.id }` }
					item={ item }
					submission={ open }
					onDone={ () => {
						setOpen( null );
						request.retry();
					} }
				/>
			) }
		</>
	);
}

function GradeForm( {
	item,
	submission,
	onDone,
}: {
	item: SchoolClass;
	submission: Submission;
	onDone: () => void;
} ) {
	const operation = useOperation();
	const [ score, setScore ] = useState(
		null === submission.score ? '' : String( submission.score )
	);
	const [ note, setNote ] = useState( submission.note );
	const value = Number( score );
	const valid =
		'' !== score.trim() &&
		Number.isInteger( value ) &&
		value >= 0 &&
		value <= submission.total_points;

	return (
		<form
			noValidate
			aria-label={ __( 'Grade submission', 'ohmylms' ) }
			onSubmit={ ( event ) => {
				event.preventDefault();
				if ( valid ) {
					void operation.run(
						( signal ) =>
							gradeSubmission(
								item.id,
								submission.id,
								submission.assignment_id,
								value,
								note,
								signal
							),
						onDone
					);
				}
			} }
		>
			<h4>
				{ sprintf(
					/* translators: 1: student, 2: work. */
					__( '%1$s · %2$s', 'ohmylms' ),
					submission.student_name,
					submission.title
				) }
			</h4>
			<p style={ { whiteSpace: 'pre-wrap' } }>{ submission.content }</p>
			{ submission.file_url && (
				<p>
					<a href={ submission.file_url }>
						{ __( 'Download the submitted file', 'ohmylms' ) }
					</a>
				</p>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			<TextControl
				label={ sprintf(
					/* translators: %d: total points. */
					__( 'Score (0 to %d)', 'ohmylms' ),
					submission.total_points
				) }
				type="number"
				min={ 0 }
				max={ submission.total_points }
				value={ score }
				help={
					'' === score || valid
						? undefined
						: __(
								'Enter a whole number within the range.',
								'ohmylms'
							)
				}
				onChange={ setScore }
			/>
			<TextareaControl
				label={ __( 'Feedback', 'ohmylms' ) }
				value={ note }
				maxLength={ 2000 }
				onChange={ setNote }
			/>
			<Button
				type="submit"
				variant="primary"
				disabled={ ! valid || operation.busy }
				isBusy={ operation.busy }
			>
				{ __( 'Save grade', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button variant="tertiary" onClick={ onDone }>
				{ __( 'Close', 'ohmylms' ) }
			</Button>
		</form>
	);
}
