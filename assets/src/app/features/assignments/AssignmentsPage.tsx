import { Table } from '../../../ui/Table';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useNavigate } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	createAssignment,
	listAssignments,
	listCourses,
	trashAssignment,
	type ListFilters,
} from './api';
import { LIMITS, statusLabel } from './model';
import type { Assignment, StatusFilter } from './types';

const STATUS_FILTERS: readonly StatusFilter[] = [
	'all',
	'publish',
	'draft',
	'trash',
];

function statusOptions(): Array< { value: StatusFilter; label: string } > {
	return [
		{ value: 'all', label: __( 'All', 'ohmylms' ) },
		{ value: 'publish', label: __( 'Published', 'ohmylms' ) },
		{ value: 'draft', label: __( 'Draft', 'ohmylms' ) },
		{ value: 'trash', label: __( 'Trash', 'ohmylms' ) },
	];
}

type Dialog = { kind: 'create' } | { kind: 'trash'; item: Assignment } | null;

/**
 * Assignment list (`#/assignments`): paged, searchable, filterable by status and course, with create
 * and a confirmed move to the trash. The editor is `#/assignments/<id>` and submissions are
 * `#/assignments/<id>/submissions`.
 */
export default function AssignmentsPage() {
	const navigate = useNavigate();
	const [ page, setPage ] = useState( 1 );
	const [ filters, setFilters ] = useState< ListFilters >( {
		search: '',
		status: 'all',
		course: 0,
	} );
	const [ query, setQuery ] = useState( '' );
	const [ dialog, setDialog ] = useState< Dialog >( null );
	const [ title, setTitle ] = useState( '' );
	const [ titleError, setTitleError ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const operation = useOperation();
	const load = useCallback(
		( signal: AbortSignal ) => listAssignments( page, filters, signal ),
		[ page, filters ]
	);
	const list = useRequest( load );
	const loadCourses = useCallback(
		( signal: AbortSignal ) => listCourses( signal ),
		[]
	);
	const courses = useRequest( loadCourses );

	function filter( next: Partial< ListFilters > ) {
		setFilters( ( current ) => ( { ...current, ...next } ) );
		setPage( 1 );
	}
	function open( next: Dialog ) {
		operation.clearError();
		setTitleError( null );
		setDialog( next );
	}
	function close() {
		if ( ! operation.busy ) {
			setDialog( null );
			operation.clearError();
		}
	}
	function create() {
		const value = title.trim();
		if ( ! value || Array.from( value ).length > LIMITS.title ) {
			setTitleError(
				sprintf(
					/* translators: %d: maximum title length. */
					__( 'Enter a title of 1 to %d characters.', 'ohmylms' ),
					LIMITS.title
				)
			);
			return;
		}
		setTitleError( null );
		void operation.run(
			( signal ) => createAssignment( value, signal ),
			( created ) => {
				setDialog( null );
				navigate( `/assignments/${ created.id }` );
			}
		);
	}
	function trash( item: Assignment ) {
		void operation.run(
			( signal ) => trashAssignment( item.id, signal ),
			() => {
				setDialog( null );
				setMessage(
					sprintf(
						/* translators: %s: assignment title. */
						__( 'Moved %s to the trash.', 'ohmylms' ),
						item.title
					)
				);
				if ( 1 === list.data?.items.length && page > 1 ) {
					setPage( page - 1 );
				} else {
					list.retry();
				}
			}
		);
	}

	const courseOptions = [
		{ value: '0', label: __( 'All courses', 'ohmylms' ) },
		...( courses.data?.items ?? [] ).map( ( course ) => ( {
			value: String( course.id ),
			label: course.title,
		} ) ),
	];

	return (
		<>
			<h2>{ __( 'Assignments', 'ohmylms' ) }</h2>
			<Button
				variant="primary"
				disabled={ operation.busy }
				onClick={ () => {
					setTitle( '' );
					open( { kind: 'create' } );
				} }
			>
				{ __( 'Add assignment', 'ohmylms' ) }
			</Button>
			<form
				role="search"
				aria-label={ __( 'Search assignments', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					filter( { search: query.trim() } );
				} }
			>
				<TextControl
					label={ __( 'Search assignments', 'ohmylms' ) }
					value={ query }
					onChange={ setQuery }
				/>
				<SelectControl
					label={ __( 'Status', 'ohmylms' ) }
					value={ filters.status }
					options={ statusOptions() }
					onChange={ ( value ) => {
						const found = STATUS_FILTERS.find(
							( status ) => status === value
						);
						if ( found ) {
							filter( { status: found } );
						}
					} }
				/>
				<SelectControl
					label={ __( 'Course', 'ohmylms' ) }
					value={ String( filters.course ) }
					options={ courseOptions }
					onChange={ ( value ) =>
						filter( {
							course: /^\d+$/.test( value ) ? Number( value ) : 0,
						} )
					}
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ list.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error }{ ' ' }
					<Button variant="link" onClick={ list.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ list.loading && (
				<p role="status">{ __( 'Loading assignments…', 'ohmylms' ) }</p>
			) }
			{ ! list.loading &&
				list.data &&
				( list.data.items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: total assignment count. */
								_n(
									'%d assignment found.',
									'%d assignments found.',
									list.data.total,
									'ohmylms'
								),
								list.data.total
							) }
						</p>
						<Table
							caption={ __( 'Assignments', 'ohmylms' ) }
							striped
						>
							<thead>
								<tr>
									{ [
										__( 'Title', 'ohmylms' ),
										__( 'Status', 'ohmylms' ),
										__( 'Course', 'ohmylms' ),
										__( 'Max score', 'ohmylms' ),
										__( 'Submissions', 'ohmylms' ),
										__( 'Actions', 'ohmylms' ),
									].map( ( heading ) => (
										<th key={ heading } scope="col">
											{ heading }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ list.data.items.map( ( item ) => (
									<tr key={ item.id }>
										<th scope="row">
											<Link
												to={ `/assignments/${ item.id }` }
											>
												{ item.title }
											</Link>
										</th>
										<td>{ statusLabel( item.status ) }</td>
										<td>
											{ item.course
												? item.course.title
												: __(
														'Not in a course',
														'ohmylms'
													) }
										</td>
										<td>{ item.max_score }</td>
										<td>
											{ item.pending_count > 0
												? sprintf(
														/* translators: 1: submissions; 2: submissions waiting for a grade. */
														__(
															'%1$d (%2$d to grade)',
															'ohmylms'
														),
														item.submission_count,
														item.pending_count
													)
												: item.submission_count }
										</td>
										<td>
											<Link
												to={ `/assignments/${ item.id }/submissions` }
												aria-label={ sprintf(
													/* translators: %s: assignment title. */
													__(
														'Submissions of %s',
														'ohmylms'
													),
													item.title
												) }
											>
												{ __(
													'Submissions',
													'ohmylms'
												) }
											</Link>{ ' ' }
											{ item.can_delete &&
												'trash' !== item.status && (
													<Button
														variant="link"
														isDestructive
														disabled={
															operation.busy
														}
														onClick={ () =>
															open( {
																kind: 'trash',
																item,
															} )
														}
														aria-label={ sprintf(
															/* translators: %s: assignment title. */
															__(
																'Move %s to the trash',
																'ohmylms'
															),
															item.title
														) }
													>
														{ __(
															'Trash',
															'ohmylms'
														) }
													</Button>
												) }
										</td>
									</tr>
								) ) }
							</tbody>
						</Table>
						<Pagination
							page={ page }
							pages={ list.data.totalPages }
							busy={ list.loading || operation.busy }
							onChange={ setPage }
						/>
					</>
				) : (
					<p>{ __( 'No assignments found.', 'ohmylms' ) }</p>
				) ) }
			{ dialog?.kind === 'create' && (
				<Modal
					title={ __( 'Add assignment', 'ohmylms' ) }
					onRequestClose={ close }
					shouldCloseOnClickOutside={ false }
				>
					<form
						noValidate
						onSubmit={ ( event ) => {
							event.preventDefault();
							create();
						} }
					>
						{ operation.error && (
							<Notice status="error" isDismissible={ false }>
								{ operation.error }
							</Notice>
						) }
						<TextControl
							label={ __( 'Assignment title', 'ohmylms' ) }
							value={ title }
							maxLength={ LIMITS.title }
							required
							disabled={ operation.busy }
							aria-invalid={ titleError ? true : undefined }
							aria-describedby={
								titleError ? 'oml-asg-create-error' : undefined
							}
							onChange={ setTitle }
							help={ __(
								'The assignment starts as a draft. Add it to a course in the course editor.',
								'ohmylms'
							) }
						/>
						{ titleError && (
							<p id="oml-asg-create-error" role="alert">
								{ titleError }
							</p>
						) }
						<Button
							type="submit"
							variant="primary"
							disabled={ operation.busy }
							isBusy={ operation.busy }
						>
							{ __( 'Create assignment', 'ohmylms' ) }
						</Button>{ ' ' }
						<Button
							variant="tertiary"
							disabled={ operation.busy }
							onClick={ close }
						>
							{ __( 'Cancel', 'ohmylms' ) }
						</Button>
					</form>
				</Modal>
			) }
			{ dialog?.kind === 'trash' && (
				<Modal
					title={ __( 'Move assignment to the trash', 'ohmylms' ) }
					onRequestClose={ close }
					shouldCloseOnClickOutside={ false }
				>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<p>
						{ sprintf(
							/* translators: %s: assignment title. */
							__(
								'Move %s to the trash? It is also removed from its course. An assignment with learner submissions cannot be removed; set it to draft instead.',
								'ohmylms'
							),
							dialog.item.title
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						disabled={ operation.busy }
						isBusy={ operation.busy }
						onClick={ () => trash( dialog.item ) }
					>
						{ __( 'Move to trash', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ close }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
