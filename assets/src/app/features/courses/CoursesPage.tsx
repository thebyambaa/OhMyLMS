import { Badge } from '../../../ui/Badge';
import { ListTable, ListTableInput } from '../../../ui/ListTable';
import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import {
	lazy,
	Suspense,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useNavigate } from 'react-router-dom';
import { statusViews } from '../../components/listViews';
import {
	PageTitleAction,
	PageTitleActions,
} from '../../layout/PageTitleActions';
import { normalizeApiError } from '../../lib/api';
import {
	createCourse,
	deleteCourse,
	duplicateCourse,
	restoreCourse,
	trashCourse,
} from './api';
import './CoursesPage.css';
import { levelLabel, statusLabel } from './model';
import { useCourses } from './useCourses';
import type { Course, CourseFilters, CourseStatus, Option } from './types';

const MAX_TITLE = 200;

function postStatusTone(
	status: CourseStatus
): 'success' | 'warning' | 'info' | undefined {
	switch ( status ) {
		case 'publish':
			return 'success';
		case 'pending':
			return 'warning';
		case 'private':
			return 'info';
		default:
			return undefined;
	}
}
/**
 * Course name with its thumbnail and what it holds: units, lessons, quizzes and assignments.
 *
 * @param props         Props.
 * @param props.item    Course.
 * @param props.inTrash Whether the list shows the trash, where the name is not a link.
 */
function CourseCell( { item, inTrash }: { item: Course; inTrash: boolean } ) {
	const counts = item.content_counts;
	return (
		<div className="oml-courses-cell">
			{ item.thumbnail_url ? (
				<img
					className="oml-courses-thumb"
					src={ item.thumbnail_url }
					alt=""
					width={ 64 }
					height={ 44 }
					loading="lazy"
				/>
			) : (
				<span
					className="oml-courses-thumb is-empty"
					aria-hidden="true"
				/>
			) }
			<div>
				<strong>
					{ inTrash ? (
						item.title
					) : (
						<Link
							className="row-title"
							to={ `/courses/${ item.id }` }
						>
							{ item.title }
						</Link>
					) }
				</strong>
				<ul className="oml-courses-counts">
					<li>
						{ sprintf(
							/* translators: %d: number of units. */
							_n(
								'%d unit',
								'%d units',
								counts.units,
								'ohmylms'
							),
							counts.units
						) }
					</li>
					<li>
						{ sprintf(
							/* translators: %d: number of lessons. */
							_n(
								'%d lesson',
								'%d lessons',
								counts.lessons,
								'ohmylms'
							),
							counts.lessons
						) }
					</li>
					<li>
						{ sprintf(
							/* translators: %d: number of quizzes. */
							_n(
								'%d quiz',
								'%d quizzes',
								counts.quizzes,
								'ohmylms'
							),
							counts.quizzes
						) }
					</li>
					<li>
						{ sprintf(
							/* translators: %d: number of assignments. */
							_n(
								'%d assignment',
								'%d assignments',
								counts.assignments,
								'ohmylms'
							),
							counts.assignments
						) }
					</li>
				</ul>
			</div>
		</div>
	);
}

const CourseFromSyllabusDialog = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-syllabus" */ '../syllabus/public'
	).then( ( module ) => ( { default: module.CourseFromSyllabusDialog } ) )
);

function statusFilters(): Option[] {
	return [
		{ value: 'all', label: __( 'All', 'ohmylms' ) },
		{ value: 'publish', label: __( 'Published', 'ohmylms' ) },
		{ value: 'draft', label: __( 'Draft', 'ohmylms' ) },
		{ value: 'pending', label: __( 'Pending review', 'ohmylms' ) },
		{ value: 'future', label: __( 'Scheduled', 'ohmylms' ) },
		{ value: 'private', label: __( 'Private', 'ohmylms' ) },
		{ value: 'trash', label: __( 'Trash', 'ohmylms' ) },
	];
}

type Dialog =
	| { kind: 'create' }
	| { kind: 'from-syllabus' }
	| { kind: 'trash'; item: Course }
	| { kind: 'delete'; item: Course }
	| null;

/**
 * Course list (`#/courses`): paged, searchable, filtered by status and creation date, with
 * create, duplicate, trash, restore and confirmed permanent delete. The editor lives at
 * `#/courses/<id>`.
 */
export default function CoursesPage() {
	const list = useCourses();
	const navigate = useNavigate();
	const [ draft, setDraft ] = useState< CourseFilters >( list.filters );
	useEffect( () => setDraft( list.filters ), [ list.filters ] );
	const [ dialog, setDialog ] = useState< Dialog >( null );
	const [ title, setTitle ] = useState( '' );
	const [ titleError, setTitleError ] = useState< string | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const operation = useRef< AbortController | null >( null );
	useEffect( () => () => operation.current?.abort(), [] );
	const inTrash = 'trash' === list.filters.status;

	async function run( work: ( signal: AbortSignal ) => Promise< void > ) {
		if ( operation.current ) {
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		setError( null );
		setMessage( null );
		try {
			await work( controller.signal );
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				setError( normalizeApiError( failure ).message );
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				setBusy( false );
				operation.current = null;
			}
		}
	}
	function create() {
		const value = title.trim();
		if ( ! value || Array.from( value ).length > MAX_TITLE ) {
			setTitleError(
				sprintf(
					/* translators: %d: maximum title length. */
					__( 'Enter a title of 1 to %d characters.', 'ohmylms' ),
					MAX_TITLE
				)
			);
			return;
		}
		setTitleError( null );
		void run( async ( signal ) => {
			const created = await createCourse( value, signal );
			if ( signal.aborted ) {
				return;
			}
			setDialog( null );
			navigate( `/courses/${ created.id }` );
		} );
	}
	function duplicate( item: Course ) {
		void run( async ( signal ) => {
			await duplicateCourse( item.id, signal );
			if ( signal.aborted ) {
				return;
			}
			setMessage(
				sprintf(
					/* translators: %s: course title. */
					__( 'Duplicated %s as a draft.', 'ohmylms' ),
					item.title
				)
			);
			list.reload();
		} );
	}
	function trash( item: Course ) {
		void run( async ( signal ) => {
			await trashCourse( item.id, signal );
			if ( signal.aborted ) {
				return;
			}
			setDialog( null );
			setMessage( __( 'Course moved to the trash.', 'ohmylms' ) );
			list.afterRemoval();
		} );
	}
	function restore( item: Course ) {
		void run( async ( signal ) => {
			await restoreCourse( item.id, signal );
			if ( signal.aborted ) {
				return;
			}
			setMessage(
				sprintf(
					/* translators: %s: course title. */
					__( 'Restored %s as a draft.', 'ohmylms' ),
					item.title
				)
			);
			list.afterRemoval();
		} );
	}
	function remove( item: Course ) {
		void run( async ( signal ) => {
			await deleteCourse( item.id, signal );
			if ( signal.aborted ) {
				return;
			}
			setDialog( null );
			setMessage( __( 'Course deleted permanently.', 'ohmylms' ) );
			list.afterRemoval();
		} );
	}
	function close() {
		if ( ! busy ) {
			setDialog( null );
			setError( null );
			setTitleError( null );
		}
	}

	return (
		<>
			<PageTitleActions>
				<PageTitleAction
					disabled={ busy }
					onClick={ () => {
						setTitle( '' );
						setError( null );
						setDialog( { kind: 'create' } );
					} }
				>
					{ __( 'Add course', 'ohmylms' ) }
				</PageTitleAction>
				<PageTitleAction
					disabled={ busy }
					onClick={ () => setDialog( { kind: 'from-syllabus' } ) }
				>
					{ __( 'Create from syllabus', 'ohmylms' ) }
				</PageTitleAction>
			</PageTitleActions>
			<p>
				{ __(
					'Create courses and manage their content and settings. Pricing and access are managed through memberships.',
					'ohmylms'
				) }
			</p>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ error && ! dialog && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error }{ ' ' }
					<Button variant="link" onClick={ list.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ list.loading && (
				<p role="status">{ __( 'Loading courses…', 'ohmylms' ) }</p>
			) }
			{ ! list.loading && ! list.error && (
				<ListTable< Course >
					caption={ __( 'Courses', 'ohmylms' ) }
					heading={ __( 'Courses list', 'ohmylms' ) }
					formLabel={ __( 'Filter courses', 'ohmylms' ) }
					busy={ busy }
					rows={ list.data.items }
					rowKey={ ( item ) => item.id }
					rowLabel={ ( item ) => item.title }
					emptyMessage={ __( 'No courses found.', 'ohmylms' ) }
					views={ statusViews(
						statusFilters(),
						list.filters.status,
						list.data.total,
						( status ) => {
							setDraft( ( current ) => ( {
								...current,
								status,
							} ) );
							list.applyFilters( {
								...draft,
								search: draft.search.trim(),
								status,
							} );
						},
						( status ) =>
							list.filtersHref( {
								...list.filters,
								status,
							} )
					) }
					search={ {
						label: __( 'Search courses', 'ohmylms' ),
						submitLabel: __( 'Search Courses', 'ohmylms' ),
						value: draft.search,
						onChange: ( search ) =>
							setDraft( ( current ) => ( {
								...current,
								search,
							} ) ),
					} }
					filters={
						<>
							<ListTableInput
								label={ __( 'Created from', 'ohmylms' ) }
								type="date"
								value={ draft.created_after }
								onChange={ ( value ) =>
									setDraft( ( current ) => ( {
										...current,
										created_after: value,
									} ) )
								}
							/>
							<ListTableInput
								label={ __( 'Created until', 'ohmylms' ) }
								type="date"
								value={ draft.created_before }
								onChange={ ( value ) =>
									setDraft( ( current ) => ( {
										...current,
										created_before: value,
									} ) )
								}
							/>
						</>
					}
					onFilter={ () =>
						list.applyFilters( {
							...draft,
							status: list.filters.status,
							search: draft.search.trim(),
						} )
					}
					pagination={ {
						page: list.page,
						totalPages: list.data.totalPages,
						totalItems: list.data.total,
						itemsLabel: sprintf(
							/* translators: %d: total course count. */
							_n(
								'%d course found.',
								'%d courses found.',
								list.data.total,
								'ohmylms'
							),
							list.data.total
						),
						onChange: list.setPage,
						href: list.pageHref,
					} }
					bulkActions={
						inTrash
							? undefined
							: {
									actions: [
										{
											value: 'trash',
											label: __(
												'Move to trash',
												'ohmylms'
											),
										},
									],
									onApply: async ( action, keys ) => {
										if (
											'trash' !== action ||
											! keys.length
										) {
											return false;
										}
										try {
											for ( const key of keys ) {
												await trashCourse(
													Number( key )
												);
											}
											setMessage(
												__(
													'Courses moved to the trash.',
													'ohmylms'
												)
											);
											list.afterRemoval();
										} catch ( failure ) {
											setError(
												normalizeApiError( failure )
													.message
											);
											list.reload();
											return false;
										}
										return true;
									},
								}
					}
					columns={ [
						{
							id: 'title',
							label: __( 'Course', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<CourseCell item={ item } inTrash={ inTrash } />
							),
						},
						{
							id: 'level',
							label: __( 'Level', 'ohmylms' ),
							render: ( item ) => levelLabel( item.level ),
						},
						{
							id: 'author',
							label: __( 'Author', 'ohmylms' ),
							render: ( item ) =>
								item.author_name ? (
									<span className="oml-courses-author">
										<span aria-hidden="true">
											{
												Array.from(
													item.author_name
												)[ 0 ]
											}
										</span>
										{ item.author_name }
									</span>
								) : (
									'—'
								),
						},
						{
							id: 'students',
							label: __( 'Students', 'ohmylms' ),
							className: 'num',
							render: ( item ) => item.enrolled_count,
						},
						{
							id: 'date',
							label: __( 'Date', 'ohmylms' ),
							render: ( item ) => item.created_at.slice( 0, 10 ),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( item ) => (
								<Badge tone={ postStatusTone( item.status ) }>
									{ statusLabel( item.status ) }
								</Badge>
							),
						},
					] }
					rowActions={ ( item ) =>
						inTrash
							? [
									{
										key: 'untrash',
										label: __( 'Restore', 'ohmylms' ),
										disabled: busy,
										onClick: () => restore( item ),
										ariaLabel: sprintf(
											/* translators: %s: course title. */
											__( 'Restore %s', 'ohmylms' ),
											item.title
										),
									},
									{
										key: 'delete',
										label: __(
											'Delete permanently',
											'ohmylms'
										),
										destructive: true,
										disabled: busy,
										onClick: () => {
											setError( null );
											setDialog( {
												kind: 'delete',
												item,
											} );
										},
										ariaLabel: sprintf(
											/* translators: %s: course title. */
											__(
												'Delete %s permanently',
												'ohmylms'
											),
											item.title
										),
									},
								]
							: [
									{
										key: 'edit',
										label: __( 'Edit', 'ohmylms' ),
										render: (
											<Link
												to={ `/courses/${ item.id }` }
												aria-label={ sprintf(
													/* translators: %s: course title. */
													__( 'Edit %s', 'ohmylms' ),
													item.title
												) }
											>
												{ __( 'Edit', 'ohmylms' ) }
											</Link>
										),
									},
									{
										key: 'duplicate',
										label: __( 'Duplicate', 'ohmylms' ),
										disabled: busy,
										onClick: () => duplicate( item ),
										ariaLabel: sprintf(
											/* translators: %s: course title. */
											__( 'Duplicate %s', 'ohmylms' ),
											item.title
										),
									},
									{
										key: 'trash',
										label: __( 'Trash', 'ohmylms' ),
										destructive: true,
										disabled: busy,
										onClick: () => {
											setError( null );
											setDialog( {
												kind: 'trash',
												item,
											} );
										},
										ariaLabel: sprintf(
											/* translators: %s: course title. */
											__(
												'Move %s to the trash',
												'ohmylms'
											),
											item.title
										),
									},
								]
					}
				/>
			) }
			{ dialog?.kind === 'create' && (
				<Modal
					title={ __( 'Add course', 'ohmylms' ) }
					onRequestClose={ close }
					shouldCloseOnClickOutside={ false }
				>
					<form
						onSubmit={ ( event ) => {
							event.preventDefault();
							create();
						} }
						noValidate
					>
						{ error && (
							<Notice status="error" isDismissible={ false }>
								{ error }
							</Notice>
						) }
						<TextControl
							label={ __( 'Course title', 'ohmylms' ) }
							value={ title }
							maxLength={ MAX_TITLE }
							required
							disabled={ busy }
							aria-invalid={ titleError ? true : undefined }
							aria-describedby={
								titleError
									? 'oml-course-create-error'
									: undefined
							}
							onChange={ setTitle }
							help={ __(
								'The course starts as a draft. Build its outline, then include it in a membership plan.',
								'ohmylms'
							) }
						/>
						{ titleError && (
							<p id="oml-course-create-error" role="alert">
								{ titleError }
							</p>
						) }
						<Button
							type="submit"
							variant="primary"
							disabled={ busy }
							isBusy={ busy }
						>
							{ __( 'Create course', 'ohmylms' ) }
						</Button>{ ' ' }
						<Button
							variant="tertiary"
							disabled={ busy }
							onClick={ close }
						>
							{ __( 'Cancel', 'ohmylms' ) }
						</Button>
					</form>
				</Modal>
			) }
			{ dialog?.kind === 'from-syllabus' && (
				<Suspense
					fallback={
						<p role="status">
							{ __( 'Loading syllabuses…', 'ohmylms' ) }
						</p>
					}
				>
					<CourseFromSyllabusDialog onClose={ close } />
				</Suspense>
			) }
			{ dialog?.kind === 'trash' && (
				<Modal
					title={ __( 'Move course to the trash', 'ohmylms' ) }
					onRequestClose={ close }
					shouldCloseOnClickOutside={ false }
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<p>
						{ sprintf(
							/* translators: %s: course title. */
							__(
								'Move %s to the trash? Students keep their enrolment and you can restore it later.',
								'ohmylms'
							),
							dialog.item.title
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						disabled={ busy }
						isBusy={ busy }
						onClick={ () => trash( dialog.item ) }
					>
						{ __( 'Move to trash', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ busy }
						onClick={ close }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ dialog?.kind === 'delete' && (
				<Modal
					title={ __( 'Delete course permanently', 'ohmylms' ) }
					onRequestClose={ close }
					shouldCloseOnClickOutside={ false }
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<p>
						{ sprintf(
							/* translators: %s: course title. */
							__(
								'Permanently delete %s? This cannot be undone. A course with enrolled students cannot be deleted.',
								'ohmylms'
							),
							dialog.item.title
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						disabled={ busy }
						isBusy={ busy }
						onClick={ () => remove( dialog.item ) }
					>
						{ __( 'Delete course', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ busy }
						onClick={ close }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
