import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { ListTable } from '../../../ui/ListTable';
import { statusViews } from '../../components/listViews';
import { normalizeApiError } from '../../lib/api';
import { useCourseStudents } from './hooks';
import { formatDate, parseCourseId, studentStatus } from './model';
import { RequestStatus } from './RequestStatus';
import {
	MAX_BULK,
	MAX_REMINDER_BATCH,
	bulkUnenroll,
	describeBulkResult,
	describeReminderResult,
	enrollByEmail,
	getTemplateChoices,
	sendReminders,
	validateEmail,
} from './roster';
import type { ReminderTemplate } from './roster';
import type { StudentProgressFilter } from './types';

type BulkAction = '' | 'unenroll' | `remind:${ ReminderTemplate }`;

/**
 * One write at a time; a pending write is cancelled on unmount and its late result ignored.
 */
function useWrite() {
	const active = useRef< AbortController | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	useEffect( () => () => active.current?.abort(), [] );
	async function run( work: ( signal: AbortSignal ) => Promise< void > ) {
		if ( active.current ) {
			return;
		}
		const controller = new AbortController();
		active.current = controller;
		setBusy( true );
		setError( null );
		try {
			await work( controller.signal );
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				setError( normalizeApiError( failure ).message );
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				active.current = null;
				setBusy( false );
			}
		}
	}
	return { busy, error, run, clearError: () => setError( null ) };
}

function Roster( { courseId }: { courseId: number } ) {
	const list = useCourseStudents( courseId );
	const write = useWrite();
	const [ text, setText ] = useState( '' );
	const [ email, setEmail ] = useState( '' );
	const [ emailError, setEmailError ] = useState< string | null >( null );
	const [ selected, setSelected ] = useState< number[] >( [] );
	const [ action, setAction ] = useState< BulkAction >( '' );
	const [ confirming, setConfirming ] = useState( false );
	const [ message, setMessage ] = useState< string | null >( null );
	const items = list.data?.items ?? [];
	const total = list.data?.total ?? 0;
	const limit = 'unenroll' === action ? MAX_BULK : MAX_REMINDER_BATCH;

	useEffect( () => setSelected( [] ), [ list.query ] );

	function submitEmail() {
		const problem = validateEmail( email );
		setEmailError( problem );
		if ( problem ) {
			return;
		}
		void write.run( async ( signal ) => {
			const created = await enrollByEmail( courseId, email, signal );
			if ( signal.aborted ) {
				return;
			}
			setMessage(
				sprintf(
					/* translators: %s: student name. */
					__( '%s was enrolled.', 'ohmylms' ),
					created.name
				)
			);
			setEmail( '' );
			list.reload();
		} );
	}
	function apply() {
		const ids = selected.slice( 0, limit );
		void write.run( async ( signal ) => {
			if ( 'unenroll' === action ) {
				const result = await bulkUnenroll( courseId, ids, signal );
				if ( signal.aborted ) {
					return;
				}
				setMessage( describeBulkResult( result ) );
				list.reload();
			} else if ( action.startsWith( 'remind:' ) ) {
				const template = action.slice( 7 ) as ReminderTemplate;
				const result = await sendReminders(
					courseId,
					template,
					ids,
					signal
				);
				if ( signal.aborted ) {
					return;
				}
				setMessage( describeReminderResult( result ) );
			}
			setSelected( [] );
			setConfirming( false );
		} );
	}

	return (
		<>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ write.error && ! confirming && (
				<Notice status="error" onRemove={ write.clearError }>
					{ write.error }
				</Notice>
			) }

			<h3>{ __( 'Enrol a student', 'ohmylms' ) }</h3>
			<form
				aria-label={ __( 'Enrol a student by e-mail', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					submitEmail();
				} }
			>
				<TextControl
					label={ __( 'E-mail address', 'ohmylms' ) }
					help={ __(
						'The student needs an account on this site.',
						'ohmylms'
					) }
					type="email"
					value={ email }
					onChange={ ( value ) => {
						setEmail( value );
						setEmailError( null );
					} }
				/>
				{ emailError && <p role="alert">{ emailError }</p> }
				<Button
					type="submit"
					variant="primary"
					isBusy={ write.busy }
					disabled={ write.busy }
				>
					{ __( 'Enrol', 'ohmylms' ) }
				</Button>
			</form>

			<h3>{ __( 'Students', 'ohmylms' ) }</h3>
			<RequestStatus
				loading={ list.loading }
				error={ list.error }
				label={ __( 'Loading students…', 'ohmylms' ) }
				onRetry={ list.reload }
			/>
			{ ! list.error && ( ! list.loading || items.length > 0 ) && (
				<ListTable
					caption={ __( 'Students of this course', 'ohmylms' ) }
					formLabel={ __( 'Search students', 'ohmylms' ) }
					busy={ list.loading || write.busy }
					rows={ items }
					rowKey={ ( student ) => student.user_id }
					rowLabel={ ( student ) => student.name }
					emptyMessage={ __( 'No students found.', 'ohmylms' ) }
					selected={ selected }
					onSelectedChange={ ( keys ) =>
						setSelected( keys.map( Number ) )
					}
					bulkActions={ {
						actions: [
							...getTemplateChoices().map( ( choice ) => ( {
								value: `remind:${ choice.value }`,
								label: choice.label,
							} ) ),
							{
								value: 'unenroll',
								label: __( 'Unenrol', 'ohmylms' ),
							},
						],
						onApply: ( value ) => {
							setAction( value as BulkAction );
							write.clearError();
							setConfirming( true );
							return false;
						},
					} }
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
						value: text,
						onChange: setText,
						onSubmit: () => list.setSearch( text ),
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
							render: ( student ) => (
								<strong>
									<Link
										className="row-title"
										to={ `/students/${ student.user_id }/report` }
									>
										{ student.name }
									</Link>
								</strong>
							),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( student ) => studentStatus( student ),
						},
						{
							id: 'progress',
							label: __( 'Progress', 'ohmylms' ),
							render: ( student ) =>
								`${ student.percent_complete }%`,
						},
						{
							id: 'enrolled',
							label: __( 'Enrolled', 'ohmylms' ),
							render: ( student ) =>
								formatDate( student.enrolled_at ),
						},
					] }
				/>
			) }

			{ confirming && (
				<Modal
					title={
						'unenroll' === action
							? __( 'Unenrol students', 'ohmylms' )
							: __( 'Send reminders', 'ohmylms' )
					}
					onRequestClose={ () => {
						if ( ! write.busy ) {
							setConfirming( false );
						}
					} }
					shouldCloseOnClickOutside={ false }
				>
					{ write.error && (
						<Notice status="error" isDismissible={ false }>
							{ write.error }
						</Notice>
					) }
					<p>
						{ 'unenroll' === action
							? sprintf(
									/* translators: %d: number of students. */
									_n(
										'Unenrol %d student? Their progress in this course is permanently deleted.',
										'Unenrol %d students? Their progress in this course is permanently deleted.',
										Math.min( selected.length, limit ),
										'ohmylms'
									),
									Math.min( selected.length, limit )
								)
							: sprintf(
									/* translators: %d: number of students. */
									_n(
										'Send the reminder e-mail to %d student? Students who completed the course or were reminded in the last day are skipped.',
										'Send the reminder e-mail to %d students? Students who completed the course or were reminded in the last day are skipped.',
										Math.min( selected.length, limit ),
										'ohmylms'
									),
									Math.min( selected.length, limit )
								) }
					</p>
					<Button
						variant="primary"
						isDestructive={ 'unenroll' === action }
						isBusy={ write.busy }
						disabled={ write.busy }
						onClick={ apply }
					>
						{ __( 'Confirm', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ write.busy }
						onClick={ () => setConfirming( false ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}

/** The hidden `/reports/courses/:id/students` route: enrol, unenrol and remind a course's students. */
export default function CourseRosterPage() {
	const params = useParams();
	const courseId = parseCourseId( params.id );
	return (
		<>
			<p>
				{ null !== courseId && (
					<Link to={ `/reports/courses/${ courseId }` }>
						{ __( '← Course report', 'ohmylms' ) }
					</Link>
				) }
			</p>
			<h2>{ __( 'Course roster', 'ohmylms' ) }</h2>
			{ null === courseId ? (
				<Notice status="error" isDismissible={ false }>
					{ __( 'That is not a valid course.', 'ohmylms' ) }
				</Notice>
			) : (
				<Roster key={ courseId } courseId={ courseId } />
			) }
		</>
	);
}
