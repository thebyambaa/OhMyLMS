import {
	createElement,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { AdminCard, AdminPage } from '../../extensions/AdminPage';
import {
	duplicateLesson,
	listLessons,
	setLessonSkills,
	trashLessons,
} from './api.mjs';
import { courseEditPath } from './hubRoutes.mjs';
import { SkillPicker } from './SkillPicker';
import { lessonEditorHref } from './editorNavigation.mjs';
const statusOptions = () => [
	{
		label: __( 'All statuses', 'ohmylms' ),
		value: '',
	},
	{
		label: __( 'Published', 'ohmylms' ),
		value: 'publish',
	},
	{
		label: __( 'Draft', 'ohmylms' ),
		value: 'draft',
	},
	{
		label: __( 'Private', 'ohmylms' ),
		value: 'private',
	},
];
const statusLabel = ( status ) =>
	( {
		publish: __( 'Published', 'ohmylms' ),
		draft: __( 'Draft', 'ohmylms' ),
		pending: __( 'Pending', 'ohmylms' ),
		private: __( 'Private', 'ohmylms' ),
		future: __( 'Scheduled', 'ohmylms' ),
	} )[ status ] || status;
const formatDate = ( iso ) => {
	const date = new Date( iso );
	return Number.isNaN( date.getTime() ) ? '' : date.toLocaleDateString();
};

/**
 * The lesson library. A lesson here belongs to no course until it is placed in one, and a lesson can sit
 * in any number of courses: editing it changes it everywhere it is used.
 */
export function LessonsPage() {
	const [ input, setInput ] = useState( '' );
	const [ query, setQuery ] = useState( {
		search: '',
		status: '',
		page: 1,
	} );
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ selected, setSelected ] = useState( () => new Set() );
	const [ skillsFor, setSkillsFor ] = useState( null );
	const [ confirm, setConfirm ] = useState( null );
	const latest = useRef( 0 );
	useEffect( () => {
		const timer = setTimeout(
			() =>
				setQuery( ( current ) =>
					current.search === input
						? current
						: {
								...current,
								search: input,
								page: 1,
							}
				),
			300
		);
		return () => clearTimeout( timer );
	}, [ input ] );
	const load = useCallback( () => {
		const id = ++latest.current;
		return listLessons( {
			search: query.search,
			status: query.status,
			page: query.page,
			per_page: 20,
		} )
			.then( ( response ) => {
				if ( id !== latest.current ) {
					return;
				}
				setData( response );
				setSelected( new Set() );
				setError( '' );
			} )
			.catch( ( cause ) => {
				if ( id === latest.current ) {
					setError(
						cause.message ||
							__( 'Could not load lessons.', 'ohmylms' )
					);
				}
			} );
	}, [ query ] );
	useEffect( () => {
		load();
	}, [ load ] );
	async function run( work, success ) {
		setBusy( true );
		setError( '' );
		setNotice( '' );
		try {
			await work();
			if ( success ) {
				setNotice( success );
			}
		} catch ( cause ) {
			setError(
				cause.message ||
					__( 'Something went wrong. Please try again.', 'ohmylms' )
			);
		} finally {
			setBusy( false );
		}
	}
	const items = data?.items || [];
	const allSelected =
		items.length > 0 && items.every( ( item ) => selected.has( item.id ) );
	const toggleAll = ( checked ) =>
		setSelected(
			checked ? new Set( items.map( ( item ) => item.id ) ) : new Set()
		);
	const toggleOne = ( id, checked ) =>
		setSelected( ( current ) => {
			const next = new Set( current );
			if ( checked ) {
				next.add( id );
			} else {
				next.delete( id );
			}
			return next;
		} );
	const usedByCount = ( ids ) =>
		items.filter(
			( item ) => ids.includes( item.id ) && item.courses.length
		).length;
	return (
		<AdminPage
			className="ohmylms-lessons"
			headingLevel={ 2 }
			title={ __( 'Lesson library', 'ohmylms' ) }
			description={ __(
				'Lessons are reusable. Link them to skills here, and attach them to the chapters of a syllabus from its editor in the Curriculum tab.',
				'ohmylms'
			) }
		>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ notice && (
				<Notice status="success" onRemove={ () => setNotice( '' ) }>
					{ notice }
				</Notice>
			) }
			<AdminCard>
				<div className="ohmylms-ext-toolbar">
					<TextControl
						label={ __( 'Search lessons', 'ohmylms' ) }
						type="search"
						value={ input }
						onChange={ setInput }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Status', 'ohmylms' ) }
						value={ query.status }
						options={ statusOptions() }
						onChange={ ( status ) =>
							setQuery( {
								...query,
								status,
								page: 1,
							} )
						}
						__nextHasNoMarginBottom
					/>
					<Button
						variant="secondary"
						isDestructive
						disabled={ ! selected.size || busy }
						onClick={ () => setConfirm( [ ...selected ] ) }
					>
						{ sprintf(
							/* translators: %d: lesson or chapter title. */ __(
								'Move to trash (%d)',
								'ohmylms'
							),
							selected.size
						) }
					</Button>
				</div>
				{ ! data ? (
					<Spinner />
				) : (
					<div className="ohmylms-ext-table-scroll">
						<table className="widefat striped">
							<thead>
								<tr>
									<td className="check-column">
										<CheckboxControl
											label={ __(
												'Select all lessons on this page',
												'ohmylms'
											) }
											checked={ allSelected }
											onChange={ toggleAll }
											__nextHasNoMarginBottom
										/>
									</td>
									<th>{ __( 'Lesson', 'ohmylms' ) }</th>
									<th>{ __( 'Skills', 'ohmylms' ) }</th>
									<th>{ __( 'Used in', 'ohmylms' ) }</th>
									<th>{ __( 'Updated', 'ohmylms' ) }</th>
									<th>{ __( 'Actions', 'ohmylms' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ ! items.length && (
									<tr>
										<td colSpan={ 6 }>
											{ query.search || query.status
												? __(
														'No lessons match these filters.',
														'ohmylms'
													)
												: __(
														'No lessons yet. Use Add → Lesson to create one.',
														'ohmylms'
													) }
										</td>
									</tr>
								) }
								{ items.map( ( item ) => (
									<tr key={ item.id }>
										<td className="check-column">
											<CheckboxControl
												label={ sprintf(
													/* translators: %s: lesson or chapter title. */ __(
														'Select %s',
														'ohmylms'
													),
													item.title
												) }
												checked={ selected.has(
													item.id
												) }
												onChange={ ( checked ) =>
													toggleOne(
														item.id,
														checked
													)
												}
												__nextHasNoMarginBottom
											/>
										</td>
										<td>
											<a
												href={ lessonEditorHref(
													item,
													'/content-hub/lessons'
												) }
											>
												{ item.title ||
													`#${ item.id }` }
											</a>
											<div className="ohmylms-ext-muted">
												{ item.type } ·{ ' ' }
												{ statusLabel( item.status ) }
											</div>
										</td>
										<td>
											<ul className="ohmylms-hub-chips">
												{ item.skills.map(
													( skill ) => (
														<li key={ skill.id }>
															{ skill.code
																? `${ skill.code } · ${ skill.name }`
																: skill.name }
														</li>
													)
												) }
											</ul>
											<Button
												variant="link"
												onClick={ () =>
													setSkillsFor( item )
												}
											>
												{ item.skills.length
													? __(
															'Edit skills',
															'ohmylms'
														)
													: __(
															'Link skills',
															'ohmylms'
														) }
											</Button>
										</td>
										<td>
											{ item.courses.length ? (
												<ul className="ohmylms-hub-links">
													{ item.courses.map(
														( course ) => (
															<li
																key={
																	course.id
																}
															>
																<a
																	href={ `#${ courseEditPath( course.id ) }` }
																>
																	{ course.title ||
																		`#${ course.id }` }
																</a>
															</li>
														)
													) }
												</ul>
											) : (
												<span className="ohmylms-ext-muted">
													{ __(
														'Not in a course yet',
														'ohmylms'
													) }
												</span>
											) }
										</td>
										<td>{ formatDate( item.modified ) }</td>
										<td>
											<a
												href={ lessonEditorHref(
													item,
													'/content-hub/lessons'
												) }
											>
												{ __( 'Edit', 'ohmylms' ) }
											</a>
											{ ' · ' }
											<Button
												variant="link"
												disabled={ busy }
												onClick={ () =>
													run(
														async () => {
															await duplicateLesson(
																item.id
															);
															await load();
														},
														sprintf(
															/* translators: %s: lesson or chapter title. */ __(
																'Copied “%s” as a draft.',
																'ohmylms'
															),
															item.title
														)
													)
												}
											>
												{ __( 'Duplicate', 'ohmylms' ) }
											</Button>
											{ ' · ' }
											<Button
												variant="link"
												isDestructive
												disabled={ busy }
												onClick={ () =>
													setConfirm( [ item.id ] )
												}
											>
												{ __( 'Trash', 'ohmylms' ) }
											</Button>
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				) }
				{ data && data.pages > 1 && (
					<nav
						className="ohmylms-hub-pager"
						aria-label={ __( 'Lesson pages', 'ohmylms' ) }
					>
						<Button
							variant="secondary"
							disabled={ query.page <= 1 }
							onClick={ () =>
								setQuery( {
									...query,
									page: query.page - 1,
								} )
							}
						>
							{ __( 'Previous', 'ohmylms' ) }
						</Button>
						<span>
							{ sprintf(
								/* translators: %1$d: current page number; %2$d: total number of pages. */ __(
									'Page %1$d of %2$d',
									'ohmylms'
								),
								query.page,
								data.pages
							) }
						</span>
						<Button
							variant="secondary"
							disabled={ query.page >= data.pages }
							onClick={ () =>
								setQuery( {
									...query,
									page: query.page + 1,
								} )
							}
						>
							{ __( 'Next', 'ohmylms' ) }
						</Button>
					</nav>
				) }
			</AdminCard>
			{ skillsFor && (
				<SkillPicker
					title={ sprintf(
						/* translators: %s: lesson or chapter title. */ __(
							'Skills for “%s”',
							'ohmylms'
						),
						skillsFor.title
					) }
					confirmLabel={ __( 'Save skills', 'ohmylms' ) }
					initial={ skillsFor.skills }
					onClose={ () => setSkillsFor( null ) }
					onConfirm={ ( skills ) => {
						const lesson = skillsFor;
						setSkillsFor( null );
						run(
							async () => {
								await setLessonSkills(
									lesson.id,
									skills.map( ( skill ) => skill.id )
								);
								setData( ( current ) => ( {
									...current,
									items: current.items.map( ( row ) =>
										row.id === lesson.id
											? {
													...row,
													skills: skills.map(
														( {
															id,
															name,
															code,
														} ) => ( {
															id,
															name,
															code,
														} )
													),
												}
											: row
									),
								} ) );
							},
							__( 'Skills saved.', 'ohmylms' )
						);
					} }
				/>
			) }
			{ confirm && (
				<Modal
					title={ __( 'Move to trash?', 'ohmylms' ) }
					onRequestClose={ () => setConfirm( null ) }
					className="ohmylms-content-hub-dialog"
				>
					<p>
						{ sprintf(
							/* translators: %d: number of selected lessons. */ _n(
								'%d lesson will be moved to the trash.',
								'%d lessons will be moved to the trash.',
								confirm.length,
								'ohmylms'
							),
							confirm.length
						) }
					</p>
					{ usedByCount( confirm ) > 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ sprintf(
								/* translators: %d: number of selected lessons used in courses. */ _n(
									'%d of them is used in a course and will become unavailable to learners there.',
									'%d of them are used in courses and will become unavailable to learners there.',
									usedByCount( confirm ),
									'ohmylms'
								),
								usedByCount( confirm )
							) }
						</Notice>
					) }
					<div className="ohmylms-content-hub-dialog-actions">
						<Button
							variant="tertiary"
							onClick={ () => setConfirm( null ) }
						>
							{ __( 'Cancel', 'ohmylms' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ () => {
								const ids = confirm;
								setConfirm( null );
								run(
									async () => {
										const result =
											await trashLessons( ids );
										await load();
										if ( result.skipped?.length ) {
											throw new Error(
												sprintf(
													/* translators: %d: number of selected lessons. */ __(
														'%d lessons could not be moved to the trash.',
														'ohmylms'
													),
													result.skipped.length
												)
											);
										}
									},
									__( 'Moved to the trash.', 'ohmylms' )
								);
							} }
						>
							{ __( 'Move to trash', 'ohmylms' ) }
						</Button>
					</div>
				</Modal>
			) }
		</AdminPage>
	);
}
