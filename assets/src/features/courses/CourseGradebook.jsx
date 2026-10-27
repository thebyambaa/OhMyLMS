import {
	createElement,
	Fragment,
	useEffect,
	useState,
	useId,
} from '@wordpress/element';
import { Modal, Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { exportCsv } from '../schools/api.mjs';
const percent = ( value ) =>
	value === null || value === undefined
		? '—'
		: `${ Number( value ).toFixed( 1 ) }%`;
const cellStyle = {
	padding: '12px',
	borderBottom: '1px solid #dcdcde',
	textAlign: 'left',
	whiteSpace: 'nowrap',
};
export function CourseGradebook( { courseId, onClose } ) {
	const _controlId = useId();
	const [ data, setData ] = useState( null );
	const [ classes, setClasses ] = useState( [] );
	const [ revision, setRevision ] = useState( 0 );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const [ selected, setSelected ] = useState( '' );
	const [ enroll, setEnroll ] = useState( true );
	const [ expanded, setExpanded ] = useState( {} );
	const [ search, setSearch ] = useState( '' );
	const [ editing, setEditing ] = useState( null );
	const [ remove, setRemove ] = useState( null );
	const path = `/ohmylms/v1/courses/${ courseId }/gradebook`;
	useEffect( () => {
		let active = true;
		setLoading( true );
		setError( '' );
		Promise.all( [
			window.wp.apiFetch( {
				path,
			} ),
			window.wp.apiFetch( {
				path: `${ path }/classes`,
			} ),
		] )
			.then(
				( [ book, available ] ) => {
					if ( active ) {
						setData( book );
						setClasses( available );
					}
				},
				( failure ) => {
					if ( active ) {
						setError( failure.message );
					}
				}
			)
			.finally( () => {
				if ( active ) {
					setLoading( false );
				}
			} );
		return () => {
			active = false;
		};
	}, [ path, revision ] );
	async function mutate( suffix, method, payload, success ) {
		setBusy( true );
		setError( '' );
		setMessage( '' );
		try {
			const result = await window.wp.apiFetch( {
				path: path + suffix,
				method,
				data: payload,
			} );
			setMessage(
				typeof success === 'function' ? success( result ) : success
			);
			setRevision( ( value ) => value + 1 );
			return true;
		} catch ( failure ) {
			setError( failure.message );
			return false;
		} finally {
			setBusy( false );
		}
	}
	const match = ( student ) =>
		`${ student.name } ${ student.email }`
			.toLowerCase()
			.includes( search.toLowerCase() );
	const studentRow = ( student, nested = false ) => (
		<tr
			key={ `student-${ student.id }` }
			style={ {
				background: nested ? '#fafbfc' : 'white',
			} }
		>
			<th
				scope="row"
				style={ {
					...cellStyle,
					paddingLeft: nested ? 40 : 12,
					fontWeight: 500,
				} }
			>
				{ student.name }
				<small
					style={ {
						display: 'block',
						color: '#646970',
					} }
				>
					{ student.email }
				</small>
			</th>
			{ data.items.map( ( item ) => {
				const cell = student.cells[ item.id ];
				return (
					<td key={ item.id } style={ cellStyle }>
						<Button
							variant="tertiary"
							disabled={ busy || loading || item.max <= 0 }
							onClick={ () =>
								setEditing( {
									student,
									item,
									score: cell.score ?? '',
									note: cell.note || '',
									manual: cell.status === 'manual',
								} )
							}
							aria-label={ `${ __( 'Edit grade', 'ohmylms' ) }: ${ student.name }, ${ item.title }` }
						>
							{ ( () => {
								if ( cell.score === null ) {
									if ( cell.status === 'pending' ) {
										return __( 'Pending', 'ohmylms' );
									}
									return '—';
								}
								return `${ Number( cell.score ) } / ${ Number( cell.max ) }`;
							} )() }
							{ cell.status === 'manual' ? ' *' : '' }
						</Button>
					</td>
				);
			} ) }
			<td style={ cellStyle }>
				<strong>{ percent( student.total.percent ) }</strong>
				<small
					style={ {
						display: 'block',
					} }
				>
					{ student.total.graded }/{ data.items.length }{ ' ' }
					{ __( 'graded', 'ohmylms' ) }
				</small>
			</td>
		</tr>
	);
	function download() {
		const rows = [];
		const add = ( student, className ) =>
			rows.push( {
				Class: className,
				Student: student.name,
				Email: student.email,
				...Object.fromEntries(
					data.items.map( ( item ) => [
						`${ item.title } (#${ item.id })`,
						student.cells[ item.id ].score === null
							? ''
							: `${ student.cells[ item.id ].score }/${ student.cells[ item.id ].max }`,
					] )
				),
				'Grade (%)': student.total.percent ?? '',
			} );
		data.classes.forEach( ( group ) =>
			group.students.forEach( ( student ) => add( student, group.name ) )
		);
		data.students.forEach( ( student ) => add( student, '' ) );
		const url = URL.createObjectURL(
			new Blob( [ '\uFEFF' + exportCsv( rows ) ], {
				type: 'text/csv;charset=utf-8',
			} )
		);
		const a = document.createElement( 'a' );
		a.href = url;
		a.download = `course-${ courseId }-gradebook.csv`;
		a.click();
		URL.revokeObjectURL( url );
	}
	return (
		<Modal
			title={
				data
					? `${ data.course.name } — ${ __( 'Gradebook', 'ohmylms' ) }`
					: __( 'Course gradebook', 'ohmylms' )
			}
			isFullScreen
			onRequestClose={ () => ! busy && onClose() }
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }{ ' ' }
					<Button onClick={ () => setRevision( ( v ) => v + 1 ) }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ message && (
				<Notice
					status="success"
					isDismissible
					onRemove={ () => setMessage( '' ) }
				>
					{ message }
				</Notice>
			) }
			{ loading && (
				<p role="status">{ __( 'Loading gradebook…', 'ohmylms' ) }</p>
			) }
			{ data && (
				<>
					<p>
						{ data.student_count } { __( 'students', 'ohmylms' ) } ·{ ' ' }
						{ data.classes.length } { __( 'classes', 'ohmylms' ) } ·{ ' ' }
						{ __( 'Course average', 'ohmylms' ) }:{ ' ' }
						<strong>{ percent( data.average ) }</strong>
					</p>
					<p>
						{ __(
							'Expand a class to see its nested gradebook. Class grades are averages of student percentages. Student totals use the latest graded attempts; pending and missing work are excluded.',
							'ohmylms'
						) }
					</p>
					<form
						onSubmit={ async ( event ) => {
							event.preventDefault();
							if (
								await mutate(
									'/classes',
									'POST',
									{
										class: Number( selected ),
										enroll,
									},
									( result ) =>
										`${ __( 'Class added', 'ohmylms' ) }. ${ result.included } ${ __( 'students grouped', 'ohmylms' ) }, ${ result.enrolled } ${ __( 'new enrollments', 'ohmylms' ) }, ${ result.skipped } ${ __( 'skipped', 'ohmylms' ) }.`
								)
							) {
								setExpanded( ( value ) => ( {
									...value,
									[ selected ]: true,
								} ) );
								setSelected( '' );
							}
						} }
						style={ {
							display: 'flex',
							gap: 16,
							alignItems: 'center',
							flexWrap: 'wrap',
							padding: 16,
							background: '#f0f6fc',
							marginBottom: 20,
						} }
					>
						<label htmlFor={ _controlId + '-field-1' }>
							{ __( 'Add class to course', 'ohmylms' ) }{ ' ' }
							<select
								value={ selected }
								disabled={ busy || loading }
								onChange={ ( event ) =>
									setSelected( event.target.value )
								}
								required
								id={ _controlId + '-field-1' }
							>
								<option value="">
									{ __( 'Choose a class…', 'ohmylms' ) }
								</option>
								{ classes
									.filter( ( c ) => ! Number( c.attached ) )
									.map( ( c ) => (
										<option key={ c.id } value={ c.id }>
											{ c.name }
											{ c.school_name
												? ` — ${ c.school_name }`
												: ` — ${ __( 'Independent', 'ohmylms' ) }` }
										</option>
									) ) }
							</select>
						</label>
						<label htmlFor={ _controlId + '-field-2' }>
							<input
								type="checkbox"
								checked={ enroll }
								disabled={ busy }
								onChange={ ( event ) =>
									setEnroll( event.target.checked )
								}
								id={ _controlId + '-field-2' }
							/>{ ' ' }
							{ __(
								'Enroll current students who do not have course access',
								'ohmylms'
							) }
						</label>
						<Button
							type="submit"
							variant="primary"
							disabled={ ! selected || busy || loading }
						>
							{ __( 'Add class', 'ohmylms' ) }
						</Button>
					</form>
					<p>
						{ __(
							'A class occupies one row. Adding it keeps individual student accounts and grades. Sync adds current class members; previously grouped students keep their grade history. Blocked or cancelled enrollments are skipped.',
							'ohmylms'
						) }
					</p>
					<div
						style={ {
							display: 'flex',
							gap: 16,
							justifyContent: 'space-between',
							marginBottom: 16,
						} }
					>
						<label htmlFor={ _controlId + '-field-3' }>
							{ __( 'Search students or classes', 'ohmylms' ) }{ ' ' }
							<input
								type="search"
								value={ search }
								onChange={ ( event ) =>
									setSearch( event.target.value )
								}
								id={ _controlId + '-field-3' }
							/>
						</label>
						<Button
							variant="secondary"
							onClick={ download }
							disabled={ ! data.student_count || loading }
						>
							{ __( 'Export CSV', 'ohmylms' ) }
						</Button>
					</div>
					{ ! data.items.length && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'Add quizzes or assignments to the course curriculum to create grade columns.',
								'ohmylms'
							) }
						</Notice>
					) }
					<div
						style={ {
							overflowX: 'auto',
						} }
					>
						<table
							style={ {
								borderCollapse: 'collapse',
								width: '100%',
								minWidth: 600,
							} }
						>
							<caption
								style={ {
									textAlign: 'left',
									margin: '12px 0',
								} }
							>
								{ __(
									'Course gradebook — classes and individual students',
									'ohmylms'
								) }
							</caption>
							<thead>
								<tr>
									<th scope="col" style={ cellStyle }>
										{ __( 'Class / student', 'ohmylms' ) }
									</th>
									{ data.items.map( ( item ) => (
										<th
											scope="col"
											key={ item.id }
											style={ cellStyle }
										>
											{ item.title }
											<small
												style={ {
													display: 'block',
													fontWeight: 400,
												} }
											>
												{ t(
													item.type === 'quiz'
														? 'Quiz'
														: 'Assignment'
												) }{ ' ' }
												· { item.max }{ ' ' }
												{ __( 'points', 'ohmylms' ) }
											</small>
										</th>
									) ) }
									<th scope="col" style={ cellStyle }>
										{ __( 'Grade', 'ohmylms' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ data.classes.map( ( group ) => {
									const groupMatch = group.name
										.toLowerCase()
										.includes( search.toLowerCase() );
									const students = group.students.filter(
										( student ) =>
											groupMatch || match( student )
									);
									if ( ! groupMatch && ! students.length ) {
										return null;
									}
									const open = Boolean(
										expanded[ group.id ] || search
									);
									return (
										<Fragment key={ `class-${ group.id }` }>
											<tr
												style={ {
													background: '#eef4ff',
												} }
											>
												<th
													scope="row"
													style={ cellStyle }
												>
													<Button
														variant="tertiary"
														aria-expanded={ open }
														onClick={ () =>
															setExpanded(
																( value ) => ( {
																	...value,
																	[ group.id ]:
																		! open,
																} )
															)
														}
													>
														{ open ? '▾' : '▸' }{ ' ' }
														{ group.name }
													</Button>
													<small
														style={ {
															display: 'block',
															fontWeight: 400,
														} }
													>
														{
															group.students
																.length
														}{ ' ' }
														{ __(
															'students',
															'ohmylms'
														) }{ ' ' }
														·{ ' ' }
														{ group.school_name ||
															__(
																'Independent',
																'ohmylms'
															) }
													</small>
													<div>
														<Button
															disabled={
																busy ||
																loading ||
																group.status !==
																	'active'
															}
															onClick={ () =>
																mutate(
																	`/classes/${ group.id }`,
																	'POST',
																	{
																		enroll,
																	},
																	(
																		result
																	) =>
																		`${ __( 'Class synced', 'ohmylms' ) }. ${ result.included } ${ __( 'students grouped', 'ohmylms' ) }, ${ result.enrolled } ${ __( 'new enrollments', 'ohmylms' ) }, ${ result.skipped } ${ __( 'skipped', 'ohmylms' ) }.`
																)
															}
														>
															{ __(
																'Sync roster',
																'ohmylms'
															) }
														</Button>
														<Button
															isDestructive
															disabled={
																busy || loading
															}
															onClick={ () =>
																setRemove(
																	group
																)
															}
														>
															{ __(
																'Remove class',
																'ohmylms'
															) }
														</Button>
													</div>
												</th>
												{ data.items.map( ( item ) => (
													<td
														key={ item.id }
														style={ cellStyle }
													>
														{ percent(
															group.cells[
																item.id
															]
														) }
													</td>
												) ) }
												<td style={ cellStyle }>
													<strong>
														{ percent(
															group.percent
														) }
													</strong>
													<small
														style={ {
															display: 'block',
														} }
													>
														{ __(
															'Class average',
															'ohmylms'
														) }
													</small>
												</td>
											</tr>
											{ open &&
												students.map( ( student ) =>
													studentRow( student, true )
												) }
											{ open && ! students.length && (
												<tr>
													<td
														colSpan={
															data.items.length +
															2
														}
														style={ cellStyle }
													>
														{ __(
															'No students grouped yet. Add students to the class, then sync its roster.',
															'ohmylms'
														) }
													</td>
												</tr>
											) }
										</Fragment>
									);
								} ) }
								{ data.students
									.filter( match )
									.map( ( student ) =>
										studentRow( student )
									) }
								{ ! data.student_count &&
									! data.classes.length && (
										<tr>
											<td
												colSpan={
													data.items.length + 2
												}
												style={ cellStyle }
											>
												{ __(
													'Add a class or enroll students to start this gradebook.',
													'ohmylms'
												) }
											</td>
										</tr>
									) }
							</tbody>
						</table>
					</div>
					<p>
						{ __(
							'* Manual gradebook override. Overrides affect gradebook totals. Assessment grading controls pass and completion status.',
							'ohmylms'
						) }
					</p>
				</>
			) }
			{ editing && (
				<Modal
					title={ `${ __( 'Edit grade', 'ohmylms' ) } — ${ editing.student.name }` }
					onRequestClose={ () => ! busy && setEditing( null ) }
				>
					<form
						onSubmit={ async ( event ) => {
							event.preventDefault();
							if (
								await mutate(
									'/grades',
									'POST',
									{
										user_id: editing.student.id,
										content_id: editing.item.id,
										score: editing.score,
										note: editing.note,
									},
									__( 'Grade saved.', 'ohmylms' )
								)
							) {
								setEditing( null );
							}
						} }
					>
						<p>{ editing.item.title }</p>
						<label htmlFor={ _controlId + '-field-4' }>
							{ __( 'Score', 'ohmylms' ) } / { editing.item.max }
							<input
								type="number"
								min="0"
								max={ editing.item.max }
								step="0.0001"
								required
								value={ editing.score }
								disabled={ busy }
								onChange={ ( event ) =>
									setEditing( {
										...editing,
										score: event.target.value,
									} )
								}
								id={ _controlId + '-field-4' }
							/>
						</label>
						<p>
							<label htmlFor={ _controlId + '-field-5' }>
								{ __( 'Grade note', 'ohmylms' ) }
								<textarea
									value={ editing.note }
									disabled={ busy }
									maxLength={ 2000 }
									onChange={ ( event ) =>
										setEditing( {
											...editing,
											note: event.target.value,
										} )
									}
									id={ _controlId + '-field-5' }
								/>
							</label>
						</p>
						{ error && <p role="alert">{ error }</p> }
						<Button
							variant="primary"
							type="submit"
							disabled={ busy }
						>
							{ __( 'Save grade', 'ohmylms' ) }
						</Button>{ ' ' }
						{ editing.manual && (
							<Button
								disabled={ busy }
								onClick={ async () => {
									if (
										await mutate(
											'/grades',
											'DELETE',
											{
												user_id: editing.student.id,
												content_id: editing.item.id,
											},
											__(
												'Original assessment grade restored.',
												'ohmylms'
											)
										)
									) {
										setEditing( null );
									}
								} }
							>
								{ __( 'Use assessment grade', 'ohmylms' ) }
							</Button>
						) }
					</form>
				</Modal>
			) }
			{ remove && (
				<Modal
					title={ __(
						'Remove class from course gradebook?',
						'ohmylms'
					) }
					onRequestClose={ () => ! busy && setRemove( null ) }
				>
					<p>{ remove.name }</p>
					<p>
						{ __(
							'Students keep their course access and grades, and appear as individual rows unless they belong to another attached class.',
							'ohmylms'
						) }
					</p>
					{ error && <p role="alert">{ error }</p> }
					<Button
						variant="primary"
						isDestructive
						disabled={ busy }
						onClick={ async () => {
							if (
								await mutate(
									`/classes/${ remove.id }`,
									'DELETE',
									{},
									__(
										'Class removed from gradebook.',
										'ohmylms'
									)
								)
							) {
								setRemove( null );
							}
						} }
					>
						{ __( 'Remove class', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</Modal>
	);
}
