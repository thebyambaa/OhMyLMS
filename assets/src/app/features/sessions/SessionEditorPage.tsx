import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Table } from '../../../ui/Table';
import { Pagination } from '../../components/Pagination';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	createSession,
	deleteSession,
	getProviders,
	getSession,
	listAttendees,
	searchCourses,
	updateSession,
} from './api';
import {
	OPTION_NAMES,
	draftFromSession,
	emptyDraft,
	optionLabel,
	parseRouteId,
	providerLabel,
	stateLabel,
	timezoneChoices,
	validateDraft,
} from './model';
import type { DraftErrors } from './model';
import type { CourseChoice, Session, SessionDraft } from './types';

function Attendees( { sessionId }: { sessionId: number } ) {
	const [ page, setPage ] = useState( 1 );
	const load = useCallback(
		( signal: AbortSignal ) => listAttendees( sessionId, page, signal ),
		[ sessionId, page ]
	);
	const request = useRequest( load );
	const items = request.data?.items ?? [];
	return (
		<section aria-label={ __( 'Attendees', 'ohmylms' ) }>
			<h3>{ __( 'Attendees', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Students enrolled in the course of this session. Enrolments are managed on the course roster.',
					'ohmylms'
				) }
			</p>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
				</Notice>
			) }
			{ request.loading && (
				<p role="status">{ __( 'Loading attendees…', 'ohmylms' ) }</p>
			) }
			{ ! request.loading && ! request.error && ! items.length && (
				<p>
					{ __(
						'No students yet. Link the session to a course with enrolled students.',
						'ohmylms'
					) }
				</p>
			) }
			{ items.length > 0 && (
				<>
					<Table
						caption={ __( 'Attendees', 'ohmylms' ) }
						striped
						compact
					>
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Student', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Access', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Session completed', 'ohmylms' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ items.map( ( attendee ) => (
								<tr key={ attendee.user_id }>
									<th scope="row">{ attendee.name }</th>
									<td>
										{ 'banned' === attendee.status
											? __( 'Blocked', 'ohmylms' )
											: __( 'Enrolled', 'ohmylms' ) }
									</td>
									<td>
										{ attendee.completed
											? __( 'Yes', 'ohmylms' )
											: __( 'No', 'ohmylms' ) }
									</td>
								</tr>
							) ) }
						</tbody>
					</Table>
					<Pagination
						page={ page }
						pages={ request.data?.totalPages ?? 1 }
						busy={ request.loading }
						onChange={ setPage }
					/>
				</>
			) }
		</section>
	);
}

function Editor( {
	session,
	onSaved,
}: {
	session: Session | null;
	onSaved: ( saved: Session ) => void;
} ) {
	const navigate = useNavigate();
	const operation = useOperation();
	const [ draft, setDraft ] = useState< SessionDraft >( () =>
		session ? draftFromSession( session ) : emptyDraft()
	);
	const [ errors, setErrors ] = useState< DraftErrors >( {} );
	const [ courseSearch, setCourseSearch ] = useState( '' );
	const [ courseTerm, setCourseTerm ] = useState( '' );
	const [ confirmDelete, setConfirmDelete ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const loadProviders = useCallback(
		( signal: AbortSignal ) => getProviders( signal ),
		[]
	);
	const providers = useRequest( loadProviders );
	const loadCourses = useCallback(
		( signal: AbortSignal ) => searchCourses( courseTerm, signal ),
		[ courseTerm ]
	);
	const courses = useRequest( loadCourses );
	const provider = providers.data?.find(
		( one ) => one.id === draft.provider
	);
	const courseOptions: CourseChoice[] = [
		...( session?.course &&
		! ( courses.data ?? [] ).some(
			( one ) => one.id === session.course?.id
		)
			? [ session.course ]
			: [] ),
		...( courses.data ?? [] ),
	];

	function set< K extends keyof SessionDraft >(
		key: K,
		value: SessionDraft[ K ]
	) {
		setDraft( ( current ) => ( { ...current, [ key ]: value } ) );
		setErrors( ( current ) => ( { ...current, [ key ]: undefined } ) );
	}
	function save() {
		const found = validateDraft( draft );
		setErrors( found );
		if ( Object.values( found ).some( Boolean ) ) {
			return;
		}
		void operation.run(
			( signal ) =>
				session
					? updateSession( session.id, draft, signal )
					: createSession( draft, signal ),
			( saved ) => {
				setMessage( __( 'Session saved.', 'ohmylms' ) );
				if ( session ) {
					onSaved( saved );
				} else {
					navigate( `/sessions/${ saved.id }` );
				}
			}
		);
	}
	function remove() {
		if ( ! session ) {
			return;
		}
		void operation.run(
			( signal ) => deleteSession( session.id, signal ),
			() => navigate( '/sessions' )
		);
	}

	return (
		<>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ operation.error && ! confirmDelete && (
				<Notice status="error" onRemove={ operation.clearError }>
					{ operation.error }
				</Notice>
			) }
			{ session && (
				<p>
					{ stateLabel( session.state ) }
					{ ' · ' }
					{ session.meeting.join_url ? (
						<a
							href={ session.meeting.join_url }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'Attendee join link', 'ohmylms' ) }
						</a>
					) : (
						__( 'No meeting link yet', 'ohmylms' )
					) }
				</p>
			) }
			<form
				aria-label={ __( 'Session', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					save();
				} }
			>
				<TextControl
					label={ __( 'Title', 'ohmylms' ) }
					value={ draft.title }
					onChange={ ( value ) => set( 'title', value ) }
					help={ errors.title }
				/>
				<TextareaControl
					label={ __( 'Agenda', 'ohmylms' ) }
					value={ draft.description }
					onChange={ ( value ) => set( 'description', value ) }
				/>
				<SelectControl
					label={ __( 'Provider', 'ohmylms' ) }
					value={ draft.provider }
					disabled={ Boolean( session ) }
					help={
						session
							? __(
									'The provider of a saved session cannot change.',
									'ohmylms'
								)
							: undefined
					}
					options={ [
						{ value: 'zoom', label: providerLabel( 'zoom' ) },
						{
							value: 'googlemeet',
							label: providerLabel( 'googlemeet' ),
						},
					] }
					onChange={ ( value ) =>
						set( 'provider', value as SessionDraft[ 'provider' ] )
					}
				/>
				{ provider &&
					( ! provider.available || ! provider.configured ) && (
						<Notice status="warning" isDismissible={ false }>
							{ provider.available
								? sprintf(
										/* translators: %s: provider name. */
										__(
											'%s is not connected yet, so the meeting cannot be created.',
											'ohmylms'
										),
										provider.label
									)
								: sprintf(
										/* translators: %s: provider name. */
										__(
											'The %s integration is turned off.',
											'ohmylms'
										),
										provider.label
									) }{ ' ' }
							<Link to="/integrations">
								{ __( 'Open Integrations', 'ohmylms' ) }
							</Link>
						</Notice>
					) }
				<TextControl
					label={ __( 'Starts', 'ohmylms' ) }
					type="datetime-local"
					value={ draft.start }
					onChange={ ( value ) =>
						set( 'start', value.slice( 0, 16 ) )
					}
					help={ errors.start }
				/>
				<SelectControl
					label={ __( 'Time zone', 'ohmylms' ) }
					value={ draft.timezone }
					options={ timezoneChoices( draft.timezone ).map(
						( zone ) => ( {
							value: zone,
							label: zone,
						} )
					) }
					onChange={ ( value ) => set( 'timezone', value ) }
					help={ errors.timezone }
				/>
				<TextControl
					label={ __( 'Duration (minutes)', 'ohmylms' ) }
					type="number"
					min={ 5 }
					max={ 1440 }
					value={ String( draft.duration ) }
					onChange={ ( value ) => set( 'duration', Number( value ) ) }
					help={ errors.duration }
				/>
				<TextControl
					label={ __( 'Find a course', 'ohmylms' ) }
					value={ courseSearch }
					onChange={ setCourseSearch }
					onKeyDown={ ( event ) => {
						if ( 'Enter' === event.key ) {
							event.preventDefault();
							setCourseTerm( courseSearch );
						}
					} }
				/>
				<Button
					variant="secondary"
					onClick={ () => setCourseTerm( courseSearch ) }
					disabled={ courses.loading }
				>
					{ __( 'Find', 'ohmylms' ) }
				</Button>
				<SelectControl
					label={ __( 'Course', 'ohmylms' ) }
					value={
						null === draft.course_id
							? ''
							: String( draft.course_id )
					}
					options={ [
						{ value: '', label: __( 'No course', 'ohmylms' ) },
						...courseOptions.map( ( course ) => ( {
							value: String( course.id ),
							label: course.title,
						} ) ),
					] }
					onChange={ ( value ) =>
						set( 'course_id', value ? Number( value ) : null )
					}
				/>
				{ 'zoom' === draft.provider && (
					<>
						<TextControl
							label={ __( 'Passcode', 'ohmylms' ) }
							value={ draft.password }
							onChange={ ( value ) => set( 'password', value ) }
							help={
								errors.password ??
								__(
									'Optional. Shared with attendees.',
									'ohmylms'
								)
							}
						/>
						<fieldset>
							<legend>
								{ __( 'Meeting options', 'ohmylms' ) }
							</legend>
							{ OPTION_NAMES.map( ( name ) => (
								<CheckboxControl
									key={ name }
									label={ optionLabel( name ) }
									checked={ draft.options[ name ] }
									onChange={ ( on ) =>
										set( 'options', {
											...draft.options,
											[ name ]: on,
										} )
									}
								/>
							) ) }
						</fieldset>
					</>
				) }
				<SelectControl
					label={ __( 'Visibility', 'ohmylms' ) }
					value={ draft.status }
					options={ [
						{ value: 'draft', label: __( 'Draft', 'ohmylms' ) },
						{
							value: 'publish',
							label: __( 'Published', 'ohmylms' ),
						},
					] }
					onChange={ ( value ) =>
						set( 'status', value as SessionDraft[ 'status' ] )
					}
				/>
				<Button
					type="submit"
					variant="primary"
					isBusy={ operation.busy }
					disabled={ operation.busy }
				>
					{ session
						? __( 'Save session', 'ohmylms' )
						: __( 'Create session and meeting', 'ohmylms' ) }
				</Button>{ ' ' }
				{ session?.can_delete && (
					<Button
						variant="secondary"
						isDestructive
						disabled={ operation.busy }
						onClick={ () => {
							operation.clearError();
							setConfirmDelete( true );
						} }
					>
						{ __( 'Delete session', 'ohmylms' ) }
					</Button>
				) }
			</form>
			{ session && <Attendees sessionId={ session.id } /> }
			{ confirmDelete && session && (
				<Modal
					title={ __( 'Delete session', 'ohmylms' ) }
					onRequestClose={ () => {
						if ( ! operation.busy ) {
							setConfirmDelete( false );
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
						{ sprintf(
							/* translators: %s: session title. */
							__(
								'Delete %s? The meeting is also removed from the provider. This cannot be undone.',
								'ohmylms'
							),
							session.title
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						isBusy={ operation.busy }
						disabled={ operation.busy }
						onClick={ remove }
					>
						{ __( 'Delete session', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ () => setConfirmDelete( false ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}

function Existing( { id }: { id: number } ) {
	const load = useCallback(
		( signal: AbortSignal ) => getSession( id, signal ),
		[ id ]
	);
	const request = useRequest( load );
	const [ saved, setSaved ] = useState< Session | null >( null );
	const session = saved ?? request.data;
	if ( request.error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ request.error }{ ' ' }
				<Button variant="link" onClick={ request.retry }>
					{ __( 'Try again', 'ohmylms' ) }
				</Button>
			</Notice>
		);
	}
	if ( ! session ) {
		return <p role="status">{ __( 'Loading session…', 'ohmylms' ) }</p>;
	}
	return (
		<>
			<h2>{ session.title }</h2>
			<Editor
				key={ session.id }
				session={ session }
				onSaved={ setSaved }
			/>
		</>
	);
}

/** The hidden `/sessions/:id` route: schedule a new session (`new`) or edit one. */
export default function SessionEditorPage() {
	const params = useParams();
	const id = parseRouteId( params.id );
	return (
		<>
			<p>
				<Link to="/sessions">
					{ __( '← All sessions', 'ohmylms' ) }
				</Link>
			</p>
			{ null === id && (
				<Notice status="error" isDismissible={ false }>
					{ __( 'That is not a valid session.', 'ohmylms' ) }
				</Notice>
			) }
			{ 'new' === id && (
				<>
					<h2>{ __( 'Schedule a session', 'ohmylms' ) }</h2>
					<Editor session={ null } onSaved={ () => undefined } />
				</>
			) }
			{ 'number' === typeof id && <Existing key={ id } id={ id } /> }
		</>
	);
}
