import { confirmAction } from '../requestFeedback.mjs';
import {
	createElement,
	createRoot,
	useEffect,
	useRef,
	useState,
	useId,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { PeopleTabs } from './PeopleTabs';
import { request, exportCsv } from './api.mjs';
const config = window.ohmylmsSchools;
const api = ( path, data, method = 'POST' ) =>
	request( config, path, {
		method,
		body: data ? JSON.stringify( data ) : undefined,
	} );

// Skill reports live outside the school API; empty when practice and skills are switched off.
const skillsConfig = config?.skillsApi
	? {
			...config,
			api: config.skillsApi,
		}
	: null;
function useRemote( path, revision, source = config ) {
	const [ state, setState ] = useState( {
		path,
		data: null,
		error: '',
		loading: true,
	} );
	useEffect( () => {
		if ( ! path ) {
			setState( {
				path,
				data: [],
				error: '',
				loading: false,
			} );
			return;
		}
		const controller = new AbortController();
		setState( ( previous ) => ( {
			path,
			data: previous.path === path ? previous.data : null,
			error: '',
			loading: true,
		} ) );
		request( source, path, {
			signal: controller.signal,
		} ).then(
			( data ) => {
				if ( ! controller.signal.aborted ) {
					setState( {
						path,
						data,
						error: '',
						loading: false,
					} );
				}
			},
			( error ) => {
				if ( ! controller.signal.aborted ) {
					setState( {
						path,
						data: null,
						error: error.message,
						loading: false,
					} );
				}
			}
		);
		return () => controller.abort();
	}, [ path, revision, source ] );
	return state.path === path
		? state
		: {
				data: null,
				error: '',
				loading: true,
			};
}
function Remote( { state, children } ) {
	if ( state.loading && state.data === null ) {
		return <p role="status">{ __( 'Loading…', 'ohmylms' ) }</p>;
	}
	if ( state.error ) {
		return (
			<p role="alert" className="ohmylms-error">
				{ state.error }
			</p>
		);
	}
	return children( state.data );
}
function Form( {
	title,
	fields,
	submit = 'Save',
	onSubmit,
	disabled = false,
} ) {
	const _controlId = useId();
	const form = useRef( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	return (
		<form
			ref={ form }
			className="ohmylms-school-form"
			onSubmit={ async ( event ) => {
				event.preventDefault();
				if ( busy ) {
					return;
				}
				const data = Object.fromEntries(
					new FormData( event.currentTarget )
				);
				fields
					.filter( ( field ) => field.type === 'checkbox' )
					.forEach( ( field ) => {
						data[ field.name ] = data[ field.name ] === 'on';
					} );
				setBusy( true );
				setError( '' );
				try {
					await onSubmit( data );
					form.current?.reset();
				} catch ( failure ) {
					setError( failure.message );
				} finally {
					setBusy( false );
				}
			} }
		>
			<fieldset disabled={ busy || disabled }>
				<legend>{ t( title ) }</legend>
				{ fields.map( ( field ) => (
					<label
						key={ field.name }
						className={ field.hidden ? 'ohmylms-honeypot' : '' }
						htmlFor={ _controlId + '-field-1' + '-' + field.name }
					>
						<span>
							{ t( field.label ) }
							{ field.required ? ' *' : '' }
						</span>
						{ ( () => {
							if ( field.options ) {
								return (
									<select
										key={ field.version || field.name }
										onChange={ field.onChange }
										name={ field.name }
										required={ field.required }
										defaultValue={ field.value ?? '' }
										id={
											_controlId +
											'-field-1' +
											'-' +
											field.name
										}
									>
										<option value="">
											{ __( 'Choose…', 'ohmylms' ) }
										</option>
										{ field.options.map( ( option ) => (
											<option
												key={ option.value }
												value={ option.value }
											>
												{ option.label }
											</option>
										) ) }
									</select>
								);
							}
							if ( field.type === 'textarea' ) {
								return (
									<textarea
										name={ field.name }
										required={ field.required }
										rows="5"
										defaultValue={ field.value }
										maxLength={ field.maxLength || 2000 }
										id={
											_controlId +
											'-field-1' +
											'-' +
											field.name
										}
									/>
								);
							}
							return (
								<input
									name={ field.name }
									type={ field.type || 'text' }
									required={ field.required }
									defaultValue={ field.value }
									defaultChecked={ field.checked }
									minLength={ field.minLength }
									maxLength={ field.maxLength || 190 }
									autoComplete={ field.autoComplete }
									tabIndex={ field.hidden ? -1 : undefined }
									id={
										_controlId +
										'-field-1' +
										'-' +
										field.name
									}
								/>
							);
						} )() }
						{ field.help && <small>{ t( field.help ) }</small> }
					</label>
				) ) }
				<button type="submit">
					{ busy ? __( 'Saving…', 'ohmylms' ) : t( submit ) }
				</button>
				{ error && (
					<p role="alert" className="ohmylms-error">
						{ error }
					</p>
				) }
			</fieldset>
		</form>
	);
}
const field = ( name, label, type = 'text', required = true ) => ( {
	name,
	label,
	type,
	required,
} );
const choice = ( name, label, options ) => ( {
	name,
	label,
	required: true,
	options,
} );
const option = ( value, label ) => ( {
	value,
	label,
} );
const roleLabel = ( role ) =>
	( {
		school_admin: __( 'School administrator', 'ohmylms' ),
		teacher: __( 'Teacher', 'ohmylms' ),
		student: __( 'Student', 'ohmylms' ),
		guardian: __( 'Parent / guardian', 'ohmylms' ),
		activation: __( 'Student password setup / recovery', 'ohmylms' ),
	} )[ role ] || role;
function Table( { rows, columns, actions } ) {
	if ( ! rows?.length ) {
		return (
			<p className="ohmylms-empty">
				{ __( 'No records yet.', 'ohmylms' ) }
			</p>
		);
	}
	return (
		<div className="ohmylms-table-scroll">
			<table>
				<thead>
					<tr>
						{ columns.map( ( [ key, title ] ) => (
							<th key={ key } scope="col">
								{ t( title ) }
							</th>
						) ) }
						{ actions && (
							<th scope="col">{ __( 'Actions', 'ohmylms' ) }</th>
						) }
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( row, index ) => (
						<tr
							key={
								row.id
									? `${ row.id }-${ row.student_user_id || index }`
									: index
							}
						>
							{ columns.map( ( [ key, , format ] ) => (
								<td key={ key }>
									{ format
										? format( row[ key ], row )
										: ( row[ key ] ?? '—' ) }
								</td>
							) ) }
							{ actions && (
								<td>
									<div className="ohmylms-row-actions">
										{ actions( row ) }
									</div>
								</td>
							) }
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
function Pager( { page, setPage, count } ) {
	return (
		<div className="ohmylms-pager">
			<button
				disabled={ page === 1 }
				onClick={ () => setPage( page - 1 ) }
			>
				{ __( 'Previous', 'ohmylms' ) }
			</button>
			<span>
				{ __( 'Page', 'ohmylms' ) } { page }
			</span>
			<button
				disabled={ count < 50 }
				onClick={ () => setPage( page + 1 ) }
			>
				{ __( 'Next', 'ohmylms' ) }
			</button>
		</div>
	);
}
function ClassView( { classroom, isAdmin, revision, run } ) {
	const _controlId2 = useId();
	const [ course, setCourse ] = useState( '' );
	const activities = useRemote(
		course
			? `courses/${ course }/activities?class_id=${ classroom.id }`
			: null,
		revision
	);
	const [ candidateSearch, setCandidateSearch ] = useState( '' );
	const candidates = useRemote(
		isAdmin
			? `schools/${ classroom.school_id }/roster?search=${ encodeURIComponent( candidateSearch ) }`
			: null,
		revision
	);
	const [ page, setPage ] = useState( 1 );
	const members = useRemote(
		`classes/${ classroom.id }/members?page=${ page }`,
		revision
	);
	const work = useRemote(
		`classes/${ classroom.id }/assignments?page=${ page }`,
		revision
	);
	const [ search, setSearch ] = useState( '' );
	const courses = useRemote(
		classroom.status === 'active'
			? `courses?class_id=${ classroom.id }&search=${ encodeURIComponent( search ) }`
			: null,
		revision
	);
	const active = classroom.status === 'active';
	return (
		<section>
			<h3>
				{ classroom.name } · { classroom.grade }{ ' ' }
				<small>{ classroom.status }</small>
			</h3>
			<h4>{ __( 'Class roster', 'ohmylms' ) }</h4>
			<Remote state={ members }>
				{ ( rows ) => (
					<>
						<Table
							rows={ rows }
							columns={ [
								[ 'display_name', 'Name' ],
								[ 'role', 'Role', roleLabel ],
							] }
							actions={
								active
									? ( row ) =>
											( isAdmin ||
												row.role === 'student' ) && (
												<button
													onClick={ async () => {
														if (
															await confirmAction(
																__(
																	'Remove this membership from the class? The account and learning history will remain.',
																	'ohmylms'
																)
															)
														) {
															run(
																`classes/${ classroom.id }/members`,
																{
																	user_id:
																		row.user_id,
																	role: row.role,
																	status: 'inactive',
																}
															);
														}
													} }
												>
													{ __(
														'Remove from class',
														'ohmylms'
													) }
												</button>
											)
									: undefined
							}
						/>
						<Pager
							page={ page }
							setPage={ setPage }
							count={ rows.length }
						/>
					</>
				) }
			</Remote>
			{ active && isAdmin && (
				<label htmlFor={ _controlId2 + '-field-2' }>
					{ __( 'Find a school roster member', 'ohmylms' ) }
					<input
						value={ candidateSearch }
						onChange={ ( event ) =>
							setCandidateSearch( event.target.value )
						}
						id={ _controlId2 + '-field-2' }
					/>
				</label>
			) }
			{ active && isAdmin && (
				<Form
					title="Add a school roster member"
					fields={ [
						choice(
							'user_id',
							'School roster member',
							( candidates.data || [] ).map( ( row ) =>
								option(
									`${ row.user_id }`,
									`${ row.display_name } · ${ roleLabel( row.role ) }`
								)
							)
						),
						choice( 'role', 'Class role', [
							option( 'student', __( 'Student', 'ohmylms' ) ),
							...( isAdmin
								? [
										option(
											'teacher',
											__( 'Teacher', 'ohmylms' )
										),
									]
								: [] ),
						] ),
					] }
					onSubmit={ ( data ) =>
						run(
							`classes/${ classroom.id }/members`,
							data,
							'POST',
							true
						)
					}
				/>
			) }
			{ active && (
				<>
					<h4>{ __( 'Assign learning', 'ohmylms' ) }</h4>
					<p>
						{ __(
							'Recipients must already have course access. This does not enroll them or create a purchase. New class members do not receive earlier assignments automatically.',
							'ohmylms'
						) }
					</p>
					<label htmlFor={ _controlId2 + '-field-3' }>
						{ __( 'Find a course', 'ohmylms' ) }
						<input
							value={ search }
							onChange={ ( event ) =>
								setSearch( event.target.value )
							}
							id={ _controlId2 + '-field-3' }
						/>
					</label>
					<Remote state={ courses }>
						{ ( rows ) => (
							<Form
								title="New assignment"
								submit="Assign to current students"
								fields={ [
									field( 'title', 'Assignment title' ),
									{
										...choice(
											'course_id',
											'Course',
											rows.map( ( row ) =>
												option( row.id, row.title )
											)
										),
										onChange: ( event ) =>
											setCourse( event.target.value ),
									},
									{
										...choice(
											'content_id',
											'Activity (optional)',
											( activities.data || [] ).map(
												( row ) =>
													option( row.id, row.title )
											)
										),
										required: false,
										version: `activity-${ course }`,
										help: 'Leave empty to assign the whole course.',
									},
									{
										...field(
											'due_at',
											'Due date',
											'datetime-local',
											false
										),
										help: "Use the school's timezone.",
									},
									{
										...field(
											'prior_completion',
											'Count work completed before this assignment',
											'checkbox',
											false
										),
									},
								] }
								onSubmit={ ( data ) =>
									run(
										`classes/${ classroom.id }/assignments`,
										data,
										'POST',
										true
									)
								}
							/>
						) }
					</Remote>
				</>
			) }
			<h4>{ __( 'Assigned work and completion', 'ohmylms' ) }</h4>
			<Remote state={ work }>
				{ ( rows ) => (
					<>
						<Table
							rows={ rows }
							columns={ [
								[ 'title', 'Work' ],
								[ 'student_name', 'Student' ],
								[
									'completed_at',
									'Progress',
									( value ) =>
										value
											? __( 'Completed', 'ohmylms' )
											: __( 'Assigned', 'ohmylms' ),
								],
							] }
						/>
						<Pager
							page={ page }
							setPage={ setPage }
							count={ rows.length }
						/>
					</>
				) }
			</Remote>
			{ active && (
				<Grading
					classroom={ classroom }
					revision={ revision }
					run={ run }
				/>
			) }
			{ isAdmin && active && (
				<button
					className="secondary"
					onClick={ async () => {
						if (
							await confirmAction(
								__(
									'Archive this class? History is preserved and new assignments will be disabled.',
									'ohmylms'
								)
							)
						) {
							run( `classes/${ classroom.id }/archive`, {} );
						}
					} }
				>
					{ __( 'Archive class', 'ohmylms' ) }
				</button>
			) }
			{ skillsConfig && (
				<ClassSkills classroom={ classroom } revision={ revision } />
			) }
		</section>
	);
}
const levelBadge = ( level, label, reviewDue ) => (
	<>
		<span className={ `ohmylms-skill-level ohmylms-skill-${ level }` }>
			{ label }
		</span>
		{ reviewDue && (
			<span className="ohmylms-skill-review">
				{ ' ' }
				· { __( 'Review due', 'ohmylms' ) }
			</span>
		) }
	</>
);

/**
 * Class skill matrix for the class's teachers: one row per student, one column per skill.
 * @param root0
 * @param root0.classroom
 * @param root0.revision
 */
function ClassSkills( { classroom, revision } ) {
	const state = useRemote(
		`reports/skills?class_id=${ classroom.id }`,
		revision,
		skillsConfig
	);
	return (
		<section className="ohmylms-class-skills">
			<h4>{ __( 'Class skills', 'ohmylms' ) }</h4>
			<Remote state={ state }>
				{ ( data ) =>
					data.skills.length && data.students.length ? (
						<div className="ohmylms-table-scroll">
							<table>
								<thead>
									<tr>
										<th scope="col">
											{ __( 'Student', 'ohmylms' ) }
										</th>
										{ data.skills.map( ( skill ) => (
											<th key={ skill.id } scope="col">
												{ skill.code
													? `${ skill.code } `
													: '' }
												{ skill.name }
											</th>
										) ) }
									</tr>
								</thead>
								<tbody>
									{ data.students.map( ( student ) => (
										<tr key={ student.id }>
											<th scope="row">
												{ student.name }
											</th>
											{ data.skills.map( ( skill ) => {
												const cell =
													student.skills?.[
														skill.id
													];
												return (
													<td key={ skill.id }>
														{ cell
															? levelBadge(
																	cell.level,
																	data.levels[
																		cell
																			.level
																	],
																	cell.review_due
																)
															: data.levels[
																	'not-started'
																] }
													</td>
												);
											} ) }
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) : (
						<p className="ohmylms-empty">
							{ __(
								'No skill evidence for this class yet.',
								'ohmylms'
							) }
						</p>
					)
				}
			</Remote>
		</section>
	);
}
function Grading( { classroom, revision, run } ) {
	const submissions = useRemote(
		`classes/${ classroom.id }/submissions`,
		revision
	);
	const [ selected, setSelected ] = useState(
		() =>
			new URLSearchParams( window.location.search ).get( 'school' ) || ''
	);
	return (
		<section>
			<h4>{ __( 'Review submissions', 'ohmylms' ) }</h4>
			<Remote state={ submissions }>
				{ ( rows ) => {
					const current = rows.find(
						( row ) =>
							`${ row.learning_id }-${ row.id }` === selected
					);
					return (
						<>
							<Table
								rows={ rows }
								columns={ [
									[ 'title', 'Work' ],
									[ 'student_name', 'Student' ],
									[ 'status', 'Status' ],
									[ 'score', 'Score' ],
								] }
								actions={ ( row ) => (
									<button
										onClick={ () =>
											setSelected(
												`${ row.learning_id }-${ row.id }`
											)
										}
									>
										{ __( 'Review', 'ohmylms' ) }
									</button>
								) }
							/>
							{ current && (
								<div className="ohmylms-card">
									<h4>
										{ current.student_name } ·{ ' ' }
										{ current.title }
									</h4>
									<p
										style={ {
											whiteSpace: 'pre-wrap',
										} }
									>
										{ current.content }
									</p>
									{ current.file_url && (
										<a href={ current.file_url }>
											{ __(
												'Download submitted file',
												'ohmylms'
											) }
										</a>
									) }
									<p>
										{ __( 'Total points', 'ohmylms' ) }:{ ' ' }
										{ current.total_points }
									</p>
									<Form
										key={ selected }
										title="Grade submission"
										submit="Save grade"
										fields={ [
											{
												...field(
													'score',
													'Score',
													'number'
												),
												value: current.score,
											},
											{
												...field(
													'note',
													'Feedback',
													'textarea',
													false
												),
												value: current.note,
												maxLength: 2000,
											},
										] }
										onSubmit={ ( data ) =>
											run(
												`assignments/${ current.learning_id }/submissions/${ current.id }`,
												data,
												'POST',
												true
											)
										}
									/>
								</div>
							) }
						</>
					);
				} }
			</Remote>
		</section>
	);
}
function ImportRoster( { school, run } ) {
	const _controlId3 = useId();
	const [ csv, setCsv ] = useState( 'name,external_student_id\n' );
	const [ preview, setPreview ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const check = async ( commit ) => {
		setBusy( true );
		setError( '' );
		try {
			const rows = await run(
				`schools/${ school }/import`,
				{
					csv,
					commit,
				},
				'POST',
				true
			);
			setPreview( rows );
		} catch ( failure ) {
			setError( failure.message );
		} finally {
			setBusy( false );
		}
	};
	return (
		<section>
			<h3>{ __( 'Import student roster', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Paste up to 200 students using name and external_student_id columns. Existing school IDs are skipped; names never merge accounts. Password setup links can be issued after import.',
					'ohmylms'
				) }
			</p>
			<label htmlFor={ _controlId3 + '-field-4' }>
				{ __( 'CSV content', 'ohmylms' ) }
				<textarea
					rows="7"
					value={ csv }
					onChange={ ( event ) => {
						setCsv( event.target.value );
						setPreview( null );
					} }
					id={ _controlId3 + '-field-4' }
				/>
			</label>
			<button disabled={ busy } onClick={ () => check( false ) }>
				{ __( 'Preview import', 'ohmylms' ) }
			</button>
			{ preview && (
				<>
					<Table
						rows={ preview }
						columns={ [
							[ 'line', 'Line' ],
							[ 'name', 'Name' ],
							[ 'external_student_id', 'School student ID' ],
							[ 'status', 'Result' ],
							[ 'error', 'Details' ],
						] }
					/>
					<button
						disabled={
							busy ||
							! preview.some( ( row ) => row.status === 'new' )
						}
						onClick={ () => check( true ) }
					>
						{ __( 'Import new valid students', 'ohmylms' ) }
					</button>
				</>
			) }
			{ error && <p role="alert">{ error }</p> }
		</section>
	);
}
function SchoolView( {
	school,
	me,
	revision,
	run,
	section = 'classes',
	management = false,
} ) {
	const _controlId4 = useId();
	const [ inviteRole, setInviteRole ] = useState( '' );
	const isAdmin =
		me.platform_admin ||
		me.memberships.some(
			( membership ) =>
				Number( membership.school_id ) === Number( school.id ) &&
				membership.role === 'school_admin'
		);
	const staff =
		isAdmin ||
		me.memberships.some(
			( membership ) =>
				Number( membership.school_id ) === Number( school.id ) &&
				membership.role === 'teacher'
		);
	const [ tab, setTab ] = useState(
		! isAdmin && section === 'roster' ? 'classes' : section
	);
	const [ page, setPage ] = useState( 1 );
	const [ selected, setSelected ] = useState(
		() => new URLSearchParams( window.location.search ).get( 'class' ) || ''
	);
	const [ search, setSearch ] = useState( '' );
	const classes = useRemote(
		`schools/${ school.id }/classes?page=${ page }`,
		revision
	);
	const years = useRemote(
		staff ? `schools/${ school.id }/years` : null,
		revision
	);
	const roster = useRemote(
		isAdmin && ( tab === 'roster' || tab === 'invitations' )
			? `schools/${ school.id }/roster?page=${ page }&search=${ encodeURIComponent( search ) }`
			: null,
		revision
	);
	const invitations = useRemote(
		isAdmin && tab === 'invitations'
			? `schools/${ school.id }/invitations`
			: null,
		revision
	);
	const guardians = useRemote(
		isAdmin && tab === 'guardians'
			? `schools/${ school.id }/guardians`
			: null,
		revision
	);
	const report = useRemote(
		isAdmin && tab === 'report' ? `schools/${ school.id }/report` : null,
		revision
	);
	const prefix = `schools/${ school.id }/`;
	return (
		<section>
			<h2>{ school.name }</h2>
			<p>
				{ __( 'School timezone', 'ohmylms' ) }: { school.timezone }
			</p>
			<nav
				className="ohmylms-tabs"
				aria-label={ __( 'School sections', 'ohmylms' ) }
			>
				{ [
					'classes',
					...( isAdmin
						? [
								'roster',
								'invitations',
								'guardians',
								'years',
								'report',
								'import',
							]
						: [] ),
				].map( ( name ) => (
					<button
						key={ name }
						aria-pressed={ tab === name }
						onClick={ () => {
							setTab( name );
							setPage( 1 );
							setSelected( '' );
						} }
					>
						{ t(
							( () => {
								if ( management && name === 'classes' ) {
									return 'Class list';
								}
								if ( management && name === 'roster' ) {
									return 'Student roster';
								}
								return (
									name.charAt( 0 ).toUpperCase() +
									name.slice( 1 )
								);
							} )()
						) }
					</button>
				) ) }
			</nav>
			{ tab === 'classes' && (
				<Remote state={ classes }>
					{ ( rows ) => (
						<>
							<Table
								rows={ rows }
								columns={ [
									[ 'name', 'Class' ],
									[ 'grade', 'Grade' ],
									[ 'subject', 'Subject' ],
									[ 'status', 'Status' ],
								] }
								actions={
									staff
										? ( row ) => (
												<button
													onClick={ () =>
														setSelected(
															String( row.id )
														)
													}
												>
													{ __(
														'Open class',
														'ohmylms'
													) }
												</button>
											)
										: undefined
								}
							/>
							<Pager
								page={ page }
								setPage={ setPage }
								count={ rows.length }
							/>
							{ rows.find(
								( row ) => String( row.id ) === selected
							) && (
								<ClassView
									key={ selected }
									classroom={ rows.find(
										( row ) => String( row.id ) === selected
									) }
									isAdmin={ isAdmin }
									revision={ revision }
									run={ run }
								/>
							) }
							{ isAdmin && (
								<Remote state={ years }>
									{ ( items ) => (
										<Form
											title="Create class"
											fields={ [
												field( 'name', 'Class name' ),
												choice(
													'academic_year_id',
													'Academic year',
													items
														.filter(
															( year ) =>
																year.status ===
																'active'
														)
														.map( ( year ) =>
															option(
																year.id,
																year.label
															)
														)
												),
												field(
													'subject',
													'Subject',
													'text',
													false
												),
												field(
													'grade',
													'Grade',
													'text',
													false
												),
											] }
											onSubmit={ ( data ) =>
												run(
													prefix + 'classes',
													data,
													'POST',
													true
												)
											}
										/>
									) }
								</Remote>
							) }
						</>
					) }
				</Remote>
			) }
			{ tab === 'roster' && (
				<>
					<label htmlFor={ _controlId4 + '-field-5' }>
						{ __( 'Search students and staff', 'ohmylms' ) }
						<input
							value={ search }
							onChange={ ( event ) => {
								setSearch( event.target.value );
								setPage( 1 );
							} }
							id={ _controlId4 + '-field-5' }
						/>
					</label>
					<Remote state={ roster }>
						{ ( rows ) => (
							<>
								<Table
									rows={ rows }
									columns={ [
										[ 'display_name', 'Name' ],
										[ 'user_login', 'Username' ],
										[
											'external_student_id',
											'School student ID',
										],
										[ 'role', 'Role', roleLabel ],
									] }
									actions={ ( row ) => (
										<button
											onClick={ async () => {
												if (
													await confirmAction(
														__(
															'Remove this school membership and its class access? The account and learning history will remain.',
															'ohmylms'
														)
													)
												) {
													run(
														prefix +
															'members/' +
															row.id,
														null,
														'DELETE'
													);
												}
											} }
										>
											{ __(
												'Remove membership',
												'ohmylms'
											) }
										</button>
									) }
								/>
								<Pager
									page={ page }
									setPage={ setPage }
									count={ rows.length }
								/>
							</>
						) }
					</Remote>
					<Form
						title="Create a school-managed student"
						fields={ [
							field( 'name', 'Student name' ),
							field( 'external_student_id', 'School student ID' ),
						] }
						onSubmit={ ( data ) =>
							run( prefix + 'students', data, 'POST', true )
						}
					/>
					<p>
						{ __(
							'For existing accounts, use a student invitation instead of creating a duplicate. For new school accounts, issue a password setup link from Invitations.',
							'ohmylms'
						) }
					</p>
				</>
			) }
			{ tab === 'invitations' && (
				<>
					<label htmlFor={ _controlId4 + '-field-6' }>
						{ __(
							'Find a student for this invitation',
							'ohmylms'
						) }
						<input
							value={ search }
							onChange={ ( event ) => {
								setSearch( event.target.value );
								setPage( 1 );
							} }
							id={ _controlId4 + '-field-6' }
						/>
					</label>
					<Form
						title="Create invitation"
						submit="Create invitation link"
						fields={ [
							{
								...choice(
									'role',
									'Invitation type',
									[
										'teacher',
										'school_admin',
										'student',
										'guardian',
										'activation',
									].map( ( role ) =>
										option( role, roleLabel( role ) )
									)
								),
								onChange: ( event ) =>
									setInviteRole( event.target.value ),
							},
							...( inviteRole !== 'activation'
								? [
										{
											...field(
												'email',
												'Recipient email',
												'email',
												true
											),
											help: 'Required except for school-created student password setup.',
										},
									]
								: [] ),
							...( [ 'guardian', 'activation' ].includes(
								inviteRole
							)
								? [
										{
											...choice(
												'student_user_id',
												'Student',
												( roster.data || [] )
													.filter(
														( row ) =>
															row.role ===
															'student'
													)
													.map( ( row ) =>
														option(
															row.user_id,
															row.display_name
														)
													)
											),
											help: 'Required for guardian invitations and student password setup.',
										},
									]
								: [] ),
							...( [ 'teacher', 'student' ].includes( inviteRole )
								? [
										{
											...choice(
												'class_id',
												'Class (optional)',
												( classes.data || [] )
													.filter(
														( row ) =>
															row.status ===
															'active'
													)
													.map( ( row ) =>
														option(
															row.id,
															row.name
														)
													)
											),
											required: false,
										},
									]
								: [] ),
							...( inviteRole !== 'activation'
								? [
										field(
											'send_email',
											'Send this invitation by email',
											'checkbox',
											false
										),
									]
								: [] ),
						] }
						onSubmit={ async ( data ) => {
							const result = await run(
								prefix + 'invitations',
								data,
								'POST',
								true
							);
							setInviteRole( '' );
							return result;
						} }
					/>
					<Remote state={ invitations }>
						{ ( rows ) => (
							<Table
								rows={ rows }
								columns={ [
									[ 'role', 'Type', roleLabel ],
									[ 'email', 'Recipient' ],
									[ 'expires_at', 'Expires (UTC)' ],
									[ 'consumed_at', 'Accepted' ],
									[ 'revoked_at', 'Revoked' ],
								] }
								actions={ ( row ) =>
									! row.consumed_at &&
									! row.revoked_at && (
										<button
											onClick={ () =>
												run(
													prefix +
														'invitations/' +
														row.id,
													null,
													'DELETE'
												)
											}
										>
											{ __( 'Revoke', 'ohmylms' ) }
										</button>
									)
								}
							/>
						) }
					</Remote>
				</>
			) }
			{ tab === 'guardians' && (
				<Remote state={ guardians }>
					{ ( rows ) => (
						<Table
							rows={ rows }
							columns={ [
								[ 'parent_name', 'Parent' ],
								[ 'student_name', 'Student' ],
								[ 'status', 'Status' ],
							] }
							actions={ ( row ) =>
								row.status === 'active' && (
									<button
										onClick={ async () => {
											if (
												await confirmAction(
													__(
														"Revoke this parent's access to this child's school work?",
														'ohmylms'
													)
												)
											) {
												run(
													prefix +
														'guardians/' +
														row.id,
													null,
													'DELETE'
												);
											}
										} }
									>
										{ __( 'Revoke access', 'ohmylms' ) }
									</button>
								)
							}
						/>
					) }
				</Remote>
			) }
			{ tab === 'years' && (
				<>
					<Remote state={ years }>
						{ ( rows ) => (
							<>
								<Table
									rows={ rows }
									columns={ [
										[ 'label', 'Year' ],
										[ 'starts_at', 'Starts' ],
										[ 'ends_at', 'Ends' ],
										[ 'status', 'Status' ],
									] }
								/>
								<Form
									title="Start a new year from existing classes"
									submit="Archive old year and copy classes"
									fields={ [
										choice(
											'from_year',
											'Old year',
											rows
												.filter(
													( year ) =>
														year.status === 'active'
												)
												.map( ( year ) =>
													option(
														year.id,
														year.label
													)
												)
										),
										choice(
											'to_year',
											'New year',
											rows
												.filter(
													( year ) =>
														year.status === 'active'
												)
												.map( ( year ) =>
													option(
														year.id,
														year.label
													)
												)
										),
									] }
									onSubmit={ async ( data ) => {
										if (
											! ( await confirmAction(
												__(
													'Archive the old year and create empty classes in the new year? Old rosters and work remain in history.',
													'ohmylms'
												)
											) )
										) {
											throw new Error(
												__(
													'Rollover cancelled.',
													'ohmylms'
												)
											);
										}
										return run(
											prefix + 'rollover',
											data,
											'POST',
											true
										);
									} }
								/>
							</>
						) }
					</Remote>
					<Form
						title="Create academic year"
						fields={ [
							field( 'label', 'Year name' ),
							field( 'starts_at', 'Start date', 'date' ),
							field( 'ends_at', 'End date', 'date' ),
						] }
						onSubmit={ ( data ) =>
							run( prefix + 'years', data, 'POST', true )
						}
					/>
				</>
			) }
			{ tab === 'report' && (
				<Remote state={ report }>
					{ ( rows ) => (
						<>
							<Table
								rows={ rows }
								columns={ [
									[ 'name', 'Class' ],
									[ 'status', 'Status' ],
									[ 'assigned', 'Assigned' ],
									[ 'completed', 'Completed' ],
								] }
							/>
							<button
								disabled={ ! rows.length }
								onClick={ () => {
									const url = URL.createObjectURL(
										new Blob( [ exportCsv( rows ) ], {
											type: 'text/csv;charset=utf-8',
										} )
									);
									const a = document.createElement( 'a' );
									a.href = url;
									a.download = 'ohmylms-school-report.csv';
									a.click();
									URL.revokeObjectURL( url );
								} }
							>
								{ __( 'Download report CSV', 'ohmylms' ) }
							</button>
						</>
					) }
				</Remote>
			) }
			{ tab === 'import' && (
				<ImportRoster school={ school.id } run={ run } />
			) }
		</section>
	);
}
function Users( { revision, role = '' } ) {
	const _controlId5 = useId();
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const users = useRemote(
		`users?page=${ page }&role=${ role }&search=${ encodeURIComponent( search ) }`,
		revision
	);
	return (
		<section>
			<h2>
				{ t(
					( () => {
						if ( role === 'teacher' ) {
							return 'Teachers';
						}
						if ( role === 'parent' ) {
							return 'Parents';
						}
						return 'All accounts';
					} )()
				) }
			</h2>
			{ config.usersUrl && (
				<p>
					<a href={ config.usersUrl }>
						{ __( 'Add user', 'ohmylms' ) }
					</a>
				</p>
			) }
			<label htmlFor={ _controlId5 + '-field-7' }>
				{ __( 'Search users', 'ohmylms' ) }
				<input
					value={ search }
					onChange={ ( event ) => {
						setSearch( event.target.value );
						setPage( 1 );
					} }
					id={ _controlId5 + '-field-7' }
				/>
			</label>
			<Remote state={ users }>
				{ ( rows ) => (
					<>
						<Table
							rows={ rows }
							columns={ [
								[ 'display_name', 'Name' ],
								[ 'user_login', 'Username' ],
								[ 'email', 'Email' ],
								[ 'roles', 'Roles' ],
							] }
							actions={ ( row ) =>
								row.edit_url && (
									<a href={ row.edit_url }>
										{ __( 'Edit user', 'ohmylms' ) }
									</a>
								)
							}
						/>
						<Pager
							page={ page }
							setPage={ setPage }
							count={ rows.length }
						/>
					</>
				) }
			</Remote>
		</section>
	);
}
function IndependentClasses( { revision, run } ) {
	const [ page, setPage ] = useState( 1 );
	const [ selected, setSelected ] = useState(
		() => new URLSearchParams( window.location.search ).get( 'class' ) || ''
	);
	const classes = useRemote( `classes?page=${ page }`, revision );
	return (
		<section>
			<h2>{ __( 'Independent classes', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Create and manage your own classes without a school or academic year.',
					'ohmylms'
				) }
			</p>
			<Remote state={ classes }>
				{ ( rows ) => (
					<>
						<Table
							rows={ rows }
							columns={ [
								[ 'name', 'Class' ],
								[ 'subject', 'Subject' ],
								[ 'grade', 'Grade' ],
								[ 'status', 'Status' ],
							] }
							actions={ ( row ) => (
								<>
									<button
										onClick={ () =>
											setSelected( String( row.id ) )
										}
									>
										{ __( 'Open classroom', 'ohmylms' ) }
									</button>
									{ row.status === 'active' && (
										<button
											onClick={ async () => {
												if (
													await confirmAction(
														__(
															'Archive this class?',
															'ohmylms'
														)
													)
												) {
													run(
														`classes/${ row.id }/archive`,
														{}
													);
												}
											} }
										>
											{ __( 'Archive class', 'ohmylms' ) }
										</button>
									) }
								</>
							) }
						/>
						{ rows
							.filter( ( row ) => String( row.id ) === selected )
							.map( ( row ) => (
								<ClassView
									key={ row.id }
									classroom={ row }
									isAdmin={ false }
									revision={ revision }
									run={ run }
								/>
							) ) }
						<Pager
							page={ page }
							setPage={ setPage }
							count={ rows.length }
						/>
					</>
				) }
			</Remote>
			<Form
				title="Create independent class"
				fields={ [
					field( 'name', 'Class name' ),
					field( 'subject', 'Subject', 'text', false ),
					field( 'grade', 'Grade', 'text', false ),
				] }
				onSubmit={ ( data ) => run( 'classes', data, 'POST', true ) }
			/>
		</section>
	);
}

// This entry is mounted only by the platform-admin management page.
function AdminManagement() {
	const [ revision, setRevision ] = useState( 0 );
	const [ message, setMessage ] = useState( '' );
	const [ failure, setFailure ] = useState( '' );
	const [ link, setLink ] = useState( '' );
	const params = new URLSearchParams( window.location.search );
	const tab = [
		'users',
		'teachers',
		'parents',
		'students',
		'classes',
		'schools',
	].includes( params.get( 'tab' ) )
		? params.get( 'tab' )
		: 'schools';
	const [ selected, setSelected ] = useState( params.get( 'school' ) || '' );
	const [ page, setPage ] = useState( 1 );
	const me = useRemote( 'me', revision );
	const schools = useRemote( `schools?page=${ page }`, revision );
	const run = async ( path, data, method = 'POST', rethrow = false ) => {
		setFailure( '' );
		setMessage( '' );
		setLink( '' );
		try {
			const result = await api( path, data, method );
			setRevision( ( value ) => value + 1 );
			setMessage( result.message || __( 'Saved.', 'ohmylms' ) );
			if ( result.url ) {
				setLink( result.url );
			}
			return result;
		} catch ( error ) {
			setFailure( error.message );
			if ( rethrow ) {
				throw error;
			}
		}
	};
	return (
		<div className="ohmylms-school-shell">
			<header>
				<div>
					<p className="ohmylms-eyebrow">OhMyLMS</p>
					<h1>{ __( 'Students', 'ohmylms' ) }</h1>
				</div>
			</header>
			{ message && (
				<p role="status" className="ohmylms-success">
					{ message }
				</p>
			) }
			{ failure && (
				<p role="alert" className="ohmylms-error">
					{ failure }
				</p>
			) }
			{ link && (
				<div className="ohmylms-success">
					<p>
						{ __(
							'Copy this invitation and share it privately with the intended recipient. It expires in 48 hours.',
							'ohmylms'
						) }
					</p>
					<input
						readOnly
						aria-label={ __( 'Invitation link', 'ohmylms' ) }
						value={ link }
						onFocus={ ( event ) => event.target.select() }
					/>
				</div>
			) }
			<Remote state={ me }>
				{ ( person ) =>
					person.platform_admin ? (
						<>
							<p>
								{ __( 'Welcome', 'ohmylms' ) }, { person.name }.
							</p>
							<PeopleTabs
								active={ tab }
								studentsUrl={ config.studentsUrl }
								canListUsers={ person.can_list_users }
							/>
							{ [ 'users', 'teachers', 'parents' ].includes(
								tab
							) &&
								person.can_list_users && (
									<Users
										key={ tab }
										revision={ revision }
										role={
											{
												teachers: 'teacher',
												parents: 'parent',
											}[ tab ] || ''
										}
									/>
								) }
							{ tab === 'classes' && person.can_create_class && (
								<IndependentClasses
									revision={ revision }
									run={ run }
								/>
							) }
							{ tab === 'students' && config.studentsUrl && (
								<p>
									<a href={ config.studentsUrl }>
										{ __(
											'Open course enrollments and student progress',
											'ohmylms'
										) }
									</a>
								</p>
							) }
							{ [ 'schools', 'students', 'classes' ].includes(
								tab
							) && (
								<Remote state={ schools }>
									{ ( rows ) => {
										const school =
											rows.find(
												( row ) =>
													String( row.id ) ===
													selected
											) || rows[ 0 ];
										return (
											<>
												{ school ? (
													<>
														<label htmlFor="ohmylms-admin-school">
															{ __(
																'School',
																'ohmylms'
															) }
															<select
																id="ohmylms-admin-school"
																value={
																	school.id
																}
																onChange={ (
																	event
																) =>
																	setSelected(
																		event
																			.target
																			.value
																	)
																}
															>
																{ rows.map(
																	( row ) => (
																		<option
																			key={
																				row.id
																			}
																			value={
																				row.id
																			}
																		>
																			{
																				row.name
																			}
																		</option>
																	)
																) }
															</select>
														</label>
														<SchoolView
															key={ `${ school.id }-${ tab }` }
															school={ school }
															me={ person }
															revision={
																revision
															}
															run={ run }
															management
															section={
																{
																	students:
																		'roster',
																	schools:
																		'years',
																}[ tab ] ||
																'classes'
															}
														/>
													</>
												) : (
													<p>
														{ __(
															'No schools yet.',
															'ohmylms'
														) }
													</p>
												) }
												<Pager
													page={ page }
													setPage={ setPage }
													count={ rows.length }
												/>
												{ tab === 'schools' && (
													<Form
														title="Create school"
														fields={ [
															field(
																'name',
																'School name'
															),
															{
																...field(
																	'timezone',
																	'Timezone'
																),
																value: 'Asia/Ulaanbaatar',
																help: 'Use an IANA timezone, for example Asia/Ulaanbaatar.',
															},
														] }
														onSubmit={ ( data ) =>
															run(
																'schools',
																data,
																'POST',
																true
															)
														}
													/>
												) }
											</>
										);
									} }
								</Remote>
							) }
						</>
					) : (
						<p role="alert">
							{ __(
								'Platform administrator access is required.',
								'ohmylms'
							) }
						</p>
					)
				}
			</Remote>
		</div>
	);
}
document
	.querySelectorAll( '[data-ohmylms-school-view="admin-management"]' )
	.forEach( ( node ) => {
		createRoot( node ).render( <AdminManagement /> );
	} );
