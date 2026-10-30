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
import { useBootstrap } from '../../bootstrap/BootstrapContext';
import { Pagination } from '../../components/Pagination';
import { normalizeApiError } from '../../lib/api';
import {
	createQuiz,
	deleteQuiz,
	listCourses,
	restoreQuiz,
	trashQuiz,
} from './api';
import { LIMITS, formatDate, quizStatusLabel } from './model';
import { useQuizzes } from './useQuizzes';
import { usePagedList } from './usePagedList';
import type { Quiz } from './types';

type Dialog =
	{ kind: 'create' } | { kind: 'trash' | 'delete'; item: Quiz } | null;

/**
 * Link to the question screens of the existing admin, where questions are authored.
 *
 * @param adminUrl Site admin URL.
 * @param id       Quiz id.
 * @return Absolute URL.
 */
function questionsUrl( adminUrl: string, id: number ): string {
	return `${ adminUrl }admin.php?page=ohmylms#/quiz-edit/${ id }`;
}

/**
 * Quiz list (`#/quizzes`): paged and searchable, filtered by status and course, with create, trash,
 * restore and confirmed permanent delete. Settings live at `#/quizzes/<id>`, attempts at
 * `#/quizzes/<id>/attempts`.
 */
export default function QuizzesPage() {
	const { adminUrl } = useBootstrap();
	const list = useQuizzes();
	const navigate = useNavigate();
	const [ query, setQuery ] = useState( '' );
	const [ courseQuery, setCourseQuery ] = useState( '' );
	const [ dialog, setDialog ] = useState< Dialog >( null );
	const [ title, setTitle ] = useState( '' );
	const [ titleError, setTitleError ] = useState< string | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const operation = useRef< AbortController | null >( null );
	useEffect( () => () => operation.current?.abort(), [] );
	const courses = usePagedList(
		( signal ) => listCourses( courseQuery, 1, signal ),
		[ courseQuery ]
	);

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
		void run( async ( signal ) => {
			const created = await createQuiz( value, signal );
			if ( signal.aborted ) {
				return;
			}
			setDialog( null );
			navigate( `/quizzes/${ created.id }` );
		} );
	}
	function trash( item: Quiz ) {
		void run( async ( signal ) => {
			await trashQuiz( item.id, signal );
			if ( signal.aborted ) {
				return;
			}
			setDialog( null );
			setMessage( __( 'Quiz moved to the trash.', 'ohmylms' ) );
			list.afterRemove();
		} );
	}
	function restore( item: Quiz ) {
		void run( async ( signal ) => {
			await restoreQuiz( item.id, signal );
			if ( signal.aborted ) {
				return;
			}
			setMessage( __( 'Quiz restored as a draft.', 'ohmylms' ) );
			list.afterRemove();
		} );
	}
	function remove( item: Quiz ) {
		void run( async ( signal ) => {
			await deleteQuiz( item.id, signal );
			if ( signal.aborted ) {
				return;
			}
			setDialog( null );
			setMessage( __( 'Quiz deleted.', 'ohmylms' ) );
			list.afterRemove();
		} );
	}
	function close() {
		if ( ! busy ) {
			setDialog( null );
			setError( null );
			setTitleError( null );
		}
	}

	const statusOptions = [
		{ value: 'all', label: __( 'All', 'ohmylms' ) },
		{ value: 'publish', label: __( 'Published', 'ohmylms' ) },
		{ value: 'draft', label: __( 'Draft', 'ohmylms' ) },
		{ value: 'trash', label: __( 'Trash', 'ohmylms' ) },
	];
	const courseOptions = [
		{ value: '0', label: __( 'All courses', 'ohmylms' ) },
		...courses.data.items.map( ( course ) => ( {
			value: String( course.id ),
			label: course.title,
		} ) ),
	];
	if (
		list.course > 0 &&
		! courseOptions.some(
			( option ) => option.value === String( list.course )
		)
	) {
		courseOptions.push( {
			value: String( list.course ),
			label: __( 'Selected course', 'ohmylms' ),
		} );
	}

	return (
		<>
			<h2>{ __( 'Quizzes', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Set up quizzes, review attempts and grade written answers. Questions are edited in the question screens.',
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
				{ __( 'Add quiz', 'ohmylms' ) }
			</Button>
			<form
				role="search"
				aria-label={ __( 'Search quizzes', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					list.search( query );
				} }
			>
				<TextControl
					label={ __( 'Search quizzes', 'ohmylms' ) }
					value={ query }
					onChange={ setQuery }
				/>
				<SelectControl
					label={ __( 'Status', 'ohmylms' ) }
					value={ list.status }
					options={ statusOptions }
					onChange={ list.setStatus }
				/>
				<TextControl
					label={ __( 'Find a course', 'ohmylms' ) }
					value={ courseQuery }
					onChange={ setCourseQuery }
				/>
				<SelectControl
					label={ __( 'Course', 'ohmylms' ) }
					value={ String( list.course ) }
					options={ courseOptions }
					onChange={ ( value ) => list.setCourse( Number( value ) ) }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ busy || list.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
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
				<p role="status">{ __( 'Loading quizzes…', 'ohmylms' ) }</p>
			) }
			{ ! list.loading &&
				! list.error &&
				( list.data.items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: total quiz count. */
								_n(
									'%d quiz found.',
									'%d quizzes found.',
									list.data.total,
									'ohmylms'
								),
								list.data.total
							) }
						</p>
						<Table caption={ __( 'Quizzes', 'ohmylms' ) } striped>
							<thead>
								<tr>
									{ [
										__( 'Title', 'ohmylms' ),
										__( 'Status', 'ohmylms' ),
										__( 'Course', 'ohmylms' ),
										__( 'Questions', 'ohmylms' ),
										__( 'Attempts', 'ohmylms' ),
										__( 'Updated', 'ohmylms' ),
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
											{ item.can_edit ? (
												<Link
													to={ `/quizzes/${ item.id }` }
												>
													{ item.title }
												</Link>
											) : (
												item.title
											) }
										</th>
										<td>
											{ quizStatusLabel( item.status ) }
										</td>
										<td>{ item.course?.title ?? '—' }</td>
										<td>{ item.question_count }</td>
										<td>
											{ item.attempt_count }
											{ item.pending_review_count > 0 &&
												' ' +
													sprintf(
														/* translators: %d: attempts waiting for grading. */
														__(
															'(%d to grade)',
															'ohmylms'
														),
														item.pending_review_count
													) }
										</td>
										<td>
											{ formatDate( item.updated_at ) }
										</td>
										<td>
											{ 'trash' === item.status ? (
												<>
													{ item.can_edit && (
														<Button
															variant="link"
															disabled={ busy }
															onClick={ () =>
																restore( item )
															}
															aria-label={ sprintf(
																/* translators: %s: quiz title. */
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
														</Button>
													) }{ ' ' }
													{ item.can_delete && (
														<Button
															variant="link"
															isDestructive
															disabled={ busy }
															onClick={ () => {
																setError(
																	null
																);
																setDialog( {
																	kind: 'delete',
																	item,
																} );
															} }
															aria-label={ sprintf(
																/* translators: %s: quiz title. */
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
													) }
												</>
											) : (
												<>
													{ item.can_edit && (
														<>
															<Link
																to={ `/quizzes/${ item.id }` }
																aria-label={ sprintf(
																	/* translators: %s: quiz title. */
																	__(
																		'Settings for %s',
																		'ohmylms'
																	),
																	item.title
																) }
															>
																{ __(
																	'Settings',
																	'ohmylms'
																) }
															</Link>{ ' ' }
															<a
																href={ questionsUrl(
																	adminUrl,
																	item.id
																) }
																aria-label={ sprintf(
																	/* translators: %s: quiz title. */
																	__(
																		'Edit questions of %s',
																		'ohmylms'
																	),
																	item.title
																) }
															>
																{ __(
																	'Questions',
																	'ohmylms'
																) }
															</a>{ ' ' }
														</>
													) }
													{ item.can_grade && (
														<Link
															to={ `/quizzes/${ item.id }/attempts` }
															aria-label={ sprintf(
																/* translators: %s: quiz title. */
																__(
																	'Attempts for %s',
																	'ohmylms'
																),
																item.title
															) }
														>
															{ __(
																'Attempts',
																'ohmylms'
															) }
														</Link>
													) }{ ' ' }
													{ item.can_delete && (
														<Button
															variant="link"
															isDestructive
															disabled={ busy }
															onClick={ () => {
																setError(
																	null
																);
																setDialog( {
																	kind: 'trash',
																	item,
																} );
															} }
															aria-label={ sprintf(
																/* translators: %s: quiz title. */
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
					<p>{ __( 'No quizzes found.', 'ohmylms' ) }</p>
				) ) }
			{ dialog?.kind === 'create' && (
				<Modal
					title={ __( 'Add quiz', 'ohmylms' ) }
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
							label={ __( 'Quiz title', 'ohmylms' ) }
							value={ title }
							maxLength={ LIMITS.title }
							required
							disabled={ busy }
							aria-invalid={ titleError ? true : undefined }
							aria-describedby={
								titleError ? 'oml-quiz-create-error' : undefined
							}
							onChange={ setTitle }
							help={ __(
								'The quiz starts as a draft. Set it up in the settings, then add questions.',
								'ohmylms'
							) }
						/>
						{ titleError && (
							<p id="oml-quiz-create-error" role="alert">
								{ titleError }
							</p>
						) }
						<Button
							type="submit"
							variant="primary"
							disabled={ busy }
							isBusy={ busy }
						>
							{ __( 'Create quiz', 'ohmylms' ) }
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
			{ ( dialog?.kind === 'trash' || dialog?.kind === 'delete' ) && (
				<Modal
					title={
						'delete' === dialog.kind
							? __( 'Delete quiz permanently', 'ohmylms' )
							: __( 'Move quiz to the trash', 'ohmylms' )
					}
					onRequestClose={ close }
					shouldCloseOnClickOutside={ false }
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<p>
						{ 'delete' === dialog.kind
							? sprintf(
									/* translators: %s: quiz title. */
									__(
										'Permanently delete %s? A quiz with student attempts cannot be deleted.',
										'ohmylms'
									),
									dialog.item.title
								)
							: sprintf(
									/* translators: %s: quiz title. */
									__(
										'Move %s to the trash? You can restore it later.',
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
						onClick={ () =>
							'delete' === dialog.kind
								? remove( dialog.item )
								: trash( dialog.item )
						}
					>
						{ 'delete' === dialog.kind
							? __( 'Delete quiz', 'ohmylms' )
							: __( 'Move to trash', 'ohmylms' ) }
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
