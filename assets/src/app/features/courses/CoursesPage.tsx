import {
	ListTable,
	ListTableInput,
	ListTableSelect,
} from '../../../ui/ListTable';
import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useNavigate } from 'react-router-dom';
import { statusViews } from '../../components/listViews';
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
						}
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
							<ListTableSelect
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
					} }
					columns={ [
						{
							id: 'title',
							label: __( 'Title', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
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
							),
						},
						{
							id: 'author',
							label: __( 'Author', 'ohmylms' ),
							render: ( item ) => item.author_name || '—',
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( item ) => statusLabel( item.status ),
						},
						{
							id: 'level',
							label: __( 'Level', 'ohmylms' ),
							render: ( item ) => levelLabel( item.level ),
						},
						{
							id: 'price',
							label: __( 'Price', 'ohmylms' ),
							render: ( item ) => (
								<>
									{ 'free' === item.price_type
										? __( 'Free', 'ohmylms' )
										: formatPrice(
												item.price,
												item.currency
											) }
									{ 'paid' === item.price_type &&
										item.price !== item.regular_price && (
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
								</>
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
							label: __( 'Created', 'ohmylms' ),
							render: ( item ) => item.created_at.slice( 0, 10 ),
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
