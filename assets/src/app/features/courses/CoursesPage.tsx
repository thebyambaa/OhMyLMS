import { Table } from '../../../ui/Table';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useNavigate } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
import { normalizeApiError } from '../../lib/api';
import {
	createCourse,
	deleteCourse,
	duplicateCourse,
	restoreCourse,
	trashCourse,
} from './api';
import { formatPrice, levelLabel, statusLabel } from './model';
import { useCourses } from './useCourses';
import type { Course, CourseFilters, Option } from './types';

const MAX_TITLE = 200;

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

function priceFilters(): Option[] {
	return [
		{ value: 'all', label: __( 'All prices', 'ohmylms' ) },
		{ value: 'free', label: __( 'Free', 'ohmylms' ) },
		{ value: 'paid', label: __( 'Paid', 'ohmylms' ) },
	];
}

type Dialog =
	| { kind: 'create' }
	| { kind: 'trash'; item: Course }
	| { kind: 'delete'; item: Course }
	| null;

/**
 * Course list (`#/courses`): paged, searchable, filtered by status, price type and creation date, with
 * create, duplicate, trash, restore and confirmed permanent delete. The editor lives at
 * `#/courses/<id>`.
 */
export default function CoursesPage() {
	const list = useCourses();
	const navigate = useNavigate();
	const [ draft, setDraft ] = useState< CourseFilters >( list.filters );
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
			<h2>{ __( 'Courses', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Create courses and manage their settings, pricing and access.',
					'ohmylms'
				) }
			</p>
			<Button
				variant="primary"
				disabled={ busy }
				onClick={ () => {
					setTitle( '' );
					setError( null );
					setDialog( { kind: 'create' } );
				} }
			>
				{ __( 'Add course', 'ohmylms' ) }
			</Button>
			<form
				role="search"
				aria-label={ __( 'Filter courses', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					list.applyFilters( {
						...draft,
						search: draft.search.trim(),
					} );
				} }
			>
				<TextControl
					label={ __( 'Search courses', 'ohmylms' ) }
					value={ draft.search }
					onChange={ ( search ) =>
						setDraft( ( current ) => ( { ...current, search } ) )
					}
				/>
				<SelectControl
					label={ __( 'Status', 'ohmylms' ) }
					value={ draft.status }
					options={ statusFilters() }
					onChange={ ( status ) =>
						setDraft( ( current ) => ( { ...current, status } ) )
					}
				/>
				<SelectControl
					label={ __( 'Price', 'ohmylms' ) }
					value={ draft.price_type }
					options={ priceFilters() }
					onChange={ ( value ) =>
						setDraft( ( current ) => ( {
							...current,
							price_type: value,
						} ) )
					}
				/>
				<TextControl
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
				<TextControl
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
				<Button
					type="submit"
					variant="secondary"
					disabled={ busy || list.loading }
				>
					{ __( 'Apply filters', 'ohmylms' ) }
				</Button>
			</form>
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
			{ ! list.loading &&
				! list.error &&
				( list.data.items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: total course count. */
								_n(
									'%d course found.',
									'%d courses found.',
									list.data.total,
									'ohmylms'
								),
								list.data.total
							) }
						</p>
						<Table caption={ __( 'Courses', 'ohmylms' ) } striped>
							<thead>
								<tr>
									{ [
										__( 'Title', 'ohmylms' ),
										__( 'Status', 'ohmylms' ),
										__( 'Level', 'ohmylms' ),
										__( 'Price', 'ohmylms' ),
										__( 'Students', 'ohmylms' ),
										__( 'Created', 'ohmylms' ),
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
											{ inTrash ? (
												item.title
											) : (
												<Link
													to={ `/courses/${ item.id }` }
												>
													{ item.title }
												</Link>
											) }
											{ item.author_name && (
												<>
													<br />
													<small>
														{ item.author_name }
													</small>
												</>
											) }
										</th>
										<td>{ statusLabel( item.status ) }</td>
										<td>{ levelLabel( item.level ) }</td>
										<td>
											{ 'free' === item.price_type
												? __( 'Free', 'ohmylms' )
												: formatPrice(
														item.price,
														item.currency
													) }
											{ 'paid' === item.price_type &&
												item.price !==
													item.regular_price && (
													<>
														{ ' ' }
														<s>
															{ formatPrice(
																item.regular_price,
																item.currency
															) }
														</s>
													</>
												) }
										</td>
										<td>{ item.enrolled_count }</td>
										<td>
											{ item.created_at.slice( 0, 10 ) }
										</td>
										<td>
											{ inTrash ? (
												<>
													<Button
														variant="link"
														disabled={ busy }
														onClick={ () =>
															restore( item )
														}
														aria-label={ sprintf(
															/* translators: %s: course title. */
															__(
																'Restore %s',
																'ohmylms'
															),
															item.title
														) }
													>
														{ __(
															'Restore',
															'ohmylms'
														) }
													</Button>{ ' ' }
													<Button
														variant="link"
														isDestructive
														disabled={ busy }
														onClick={ () => {
															setError( null );
															setDialog( {
																kind: 'delete',
																item,
															} );
														} }
														aria-label={ sprintf(
															/* translators: %s: course title. */
															__(
																'Delete %s permanently',
																'ohmylms'
															),
															item.title
														) }
													>
														{ __(
															'Delete permanently',
															'ohmylms'
														) }
													</Button>
												</>
											) : (
												<>
													<Link
														to={ `/courses/${ item.id }` }
														aria-label={ sprintf(
															/* translators: %s: course title. */
															__(
																'Edit %s',
																'ohmylms'
															),
															item.title
														) }
													>
														{ __(
															'Edit',
															'ohmylms'
														) }
													</Link>{ ' ' }
													<Button
														variant="link"
														disabled={ busy }
														onClick={ () =>
															duplicate( item )
														}
														aria-label={ sprintf(
															/* translators: %s: course title. */
															__(
																'Duplicate %s',
																'ohmylms'
															),
															item.title
														) }
													>
														{ __(
															'Duplicate',
															'ohmylms'
														) }
													</Button>{ ' ' }
													<Button
														variant="link"
														isDestructive
														disabled={ busy }
														onClick={ () => {
															setError( null );
															setDialog( {
																kind: 'trash',
																item,
															} );
														} }
														aria-label={ sprintf(
															/* translators: %s: course title. */
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
												</>
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</Table>
						<Pagination
							page={ list.page }
							pages={ list.data.totalPages }
							busy={ busy || list.loading }
							onChange={ list.setPage }
						/>
					</>
				) : (
					<p>{ __( 'No courses found.', 'ohmylms' ) }</p>
				) ) }
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
								'The course starts as a draft. Set price, access and availability in the editor.',
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
