import { Table } from '../../../ui/Table';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useNavigate } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	archiveQuestion,
	duplicateQuestion,
	listQuestionTypes,
	restoreQuestion,
} from './api';
import {
	DIFFICULTIES,
	difficultyLabel,
	MAX_NAME,
	statusFilters,
	typeLabel,
	usageLabel,
	versionLabel,
} from './model';
import { useQuestions } from './useQuestions';
import type { QuestionSummary, QuestionType, StatusFilter } from './types';

const EDITOR = '/content-hub/question-bank';

function displayName( item: { name: string } ): string {
	return item.name.trim() || __( 'Untitled question', 'ohmylms' );
}

function lifecycleLabel( item: QuestionSummary ): string {
	return 'archived' === item.status
		? sprintf(
				/* translators: %s: question name. */
				__( 'Restore %s', 'ohmylms' ),
				displayName( item )
			)
		: sprintf(
				/* translators: %s: question name. */
				__( 'Archive %s', 'ohmylms' ),
				displayName( item )
			);
}

function defaultType( types: readonly QuestionType[] ): string {
	return (
		types.find( ( type ) => 'single-choice' === type.id )?.id ??
		types[ 0 ]?.id ??
		''
	);
}

/**
 * Question bank list (`#/content-hub/question-bank`): paged and filtered by text, type, difficulty and
 * status, with duplicate and archive/restore. Every listed question can be duplicated (the list shows
 * only questions the user may use). "New question" asks for a name and type and opens an unsaved
 * draft (`#/content-hub/question-bank/new`), which the first save creates. The editor lives at
 * `#/content-hub/question-bank/<id>`.
 */
export default function QuestionsPage() {
	const list = useQuestions();
	const types = useRequest( listQuestionTypes );
	const navigate = useNavigate();
	const operation = useOperation();
	const [ search, setSearch ] = useState( '' );
	const [ creating, setCreating ] = useState( false );
	const [ name, setName ] = useState( '' );
	const [ type, setType ] = useState( '' );
	const [ nameError, setNameError ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ copyId, setCopyId ] = useState< number | null >( null );
	const registered = types.data ?? [];
	const chosenType = type || defaultType( registered );

	function openCreate() {
		setName( '' );
		setType( '' );
		setNameError( null );
		setMessage( null );
		operation.clearError();
		setCreating( true );
	}
	function closeCreate() {
		setCreating( false );
		setNameError( null );
	}
	function create() {
		const value = name.trim();
		if ( ! value || Array.from( value ).length > MAX_NAME ) {
			setNameError(
				sprintf(
					/* translators: %d: maximum name length. */
					__( 'Enter a question of 1 to %d characters.', 'ohmylms' ),
					MAX_NAME
				)
			);
			return;
		}
		setNameError( null );
		setCreating( false );
		navigate(
			`${ EDITOR }/new?${ new URLSearchParams( {
				type: chosenType,
				name: value,
			} ).toString() }`
		);
	}
	function duplicate( item: QuestionSummary ) {
		setMessage( null );
		setCopyId( null );
		void operation.run(
			( signal ) => duplicateQuestion( item.id, signal ),
			( copy ) => {
				setMessage(
					sprintf(
						/* translators: 1: question name, 2: name of the copy. */
						__( 'Duplicated %1$s as %2$s.', 'ohmylms' ),
						displayName( item ),
						displayName( copy )
					)
				);
				setCopyId( copy.id );
				list.reload();
			}
		);
	}
	function changeLifecycle( item: QuestionSummary ) {
		const archiving = 'archived' !== item.status;
		setMessage( null );
		setCopyId( null );
		void operation.run(
			( signal ) =>
				archiving
					? archiveQuestion( item.id, signal )
					: restoreQuestion( item.id, signal ),
			() => {
				setMessage(
					archiving
						? sprintf(
								/* translators: %s: question name. */
								__( 'Archived %s.', 'ohmylms' ),
								displayName( item )
							)
						: sprintf(
								/* translators: %s: question name. */
								__( 'Restored %s.', 'ohmylms' ),
								displayName( item )
							)
				);
				list.reload();
			}
		);
	}

	return (
		<>
			<h2>{ __( 'Question bank', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Questions you can reuse across quizzes. Each save keeps a version.',
					'ohmylms'
				) }
			</p>
			<Button
				variant="primary"
				disabled={ operation.busy }
				onClick={ openCreate }
			>
				{ __( 'New question', 'ohmylms' ) }
			</Button>
			<form
				role="search"
				aria-label={ __( 'Search questions', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					list.search( search );
				} }
			>
				<TextControl
					label={ __( 'Search questions', 'ohmylms' ) }
					value={ search }
					maxLength={ 100 }
					onChange={ setSearch }
				/>
				<SelectControl
					label={ __( 'Type', 'ohmylms' ) }
					value={ list.query.type }
					options={ [
						{ value: '', label: __( 'All types', 'ohmylms' ) },
						...registered.map( ( item ) => ( {
							value: item.id,
							label: item.label,
						} ) ),
					] }
					onChange={ list.setType }
				/>
				<SelectControl< string >
					label={ __( 'Difficulty', 'ohmylms' ) }
					value={ list.query.difficulty }
					options={ [
						{
							value: '',
							label: __( 'All difficulties', 'ohmylms' ),
						},
						...DIFFICULTIES.map( ( value ) => ( {
							value,
							label: difficultyLabel( value ),
						} ) ),
					] }
					onChange={ list.setDifficulty }
				/>
				<SelectControl
					label={ __( 'Status', 'ohmylms' ) }
					value={ list.query.status }
					options={ statusFilters() }
					onChange={ ( value ) => {
						const found = statusFilters().find(
							( option ) => option.value === value
						);
						if ( found ) {
							list.setStatus( found.value as StatusFilter );
						}
					} }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ list.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			{ types.error && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'Question types could not be loaded.', 'ohmylms' ) }{ ' ' }
					{ types.error }{ ' ' }
					<Button variant="link" onClick={ types.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ message && (
				<Notice
					status="success"
					onRemove={ () => {
						setMessage( null );
						setCopyId( null );
					} }
				>
					{ message }
					{ null !== copyId && (
						<>
							{ ' ' }
							<Link to={ `${ EDITOR }/${ copyId }` }>
								{ __( 'Edit the copy', 'ohmylms' ) }
							</Link>
						</>
					) }
				</Notice>
			) }
			{ operation.error && ! creating && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
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
				<p role="status">{ __( 'Loading questions…', 'ohmylms' ) }</p>
			) }
			{ ! list.loading &&
				! list.error &&
				( list.data.items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: total question count. */
								_n(
									'%d question found.',
									'%d questions found.',
									list.data.total,
									'ohmylms'
								),
								list.data.total
							) }
						</p>
						<Table caption={ __( 'Questions', 'ohmylms' ) } striped>
							<thead>
								<tr>
									{ [
										__( 'Question', 'ohmylms' ),
										__( 'Type', 'ohmylms' ),
										__( 'Version', 'ohmylms' ),
										__( 'Difficulty', 'ohmylms' ),
										__( 'Used in', 'ohmylms' ),
										__( 'Actions', 'ohmylms' ),
									].map( ( title ) => (
										<th key={ title } scope="col">
											{ title }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ list.data.items.map( ( item ) => (
									<tr key={ item.id }>
										<th scope="row">
											<Link
												to={ `${ EDITOR }/${ item.id }` }
											>
												{ displayName( item ) }
											</Link>
										</th>
										<td>
											{ typeLabel(
												registered,
												item.type,
												item.type_label
											) }
										</td>
										<td>{ versionLabel( item ) }</td>
										<td>
											{ difficultyLabel(
												item.difficulty
											) }
										</td>
										<td>{ usageLabel( item ) }</td>
										<td>
											<Button
												variant="link"
												disabled={ operation.busy }
												onClick={ () =>
													duplicate( item )
												}
												aria-label={ sprintf(
													/* translators: %s: question name. */
													__(
														'Duplicate %s',
														'ohmylms'
													),
													displayName( item )
												) }
											>
												{ __( 'Duplicate', 'ohmylms' ) }
											</Button>
											{ item.permissions.can_edit && (
												<>
													{ ' ' }
													<Button
														variant="link"
														disabled={
															operation.busy
														}
														onClick={ () =>
															changeLifecycle(
																item
															)
														}
														aria-label={ lifecycleLabel(
															item
														) }
													>
														{ 'archived' ===
														item.status
															? __(
																	'Restore',
																	'ohmylms'
																)
															: __(
																	'Archive',
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
							page={ list.query.page }
							pages={ list.data.totalPages }
							busy={ operation.busy || list.loading }
							onChange={ list.setPage }
						/>
					</>
				) : (
					<p>{ __( 'No questions found.', 'ohmylms' ) }</p>
				) ) }
			{ creating && (
				<Modal
					title={ __( 'New question', 'ohmylms' ) }
					onRequestClose={ closeCreate }
					shouldCloseOnClickOutside={ false }
				>
					<form
						onSubmit={ ( event ) => {
							event.preventDefault();
							create();
						} }
						noValidate
					>
						<p>
							{ __(
								'Next you complete the question in the editor. It is added to the question bank when you save it.',
								'ohmylms'
							) }
						</p>
						<TextControl
							label={ __( 'Name', 'ohmylms' ) }
							value={ name }
							maxLength={ MAX_NAME }
							required
							disabled={ operation.busy }
							aria-invalid={ nameError ? true : undefined }
							aria-describedby={
								nameError
									? 'oml-question-create-error'
									: undefined
							}
							onChange={ setName }
							help={ __(
								'The question text learners see. You can change it later.',
								'ohmylms'
							) }
						/>
						{ nameError && (
							<p id="oml-question-create-error" role="alert">
								{ nameError }
							</p>
						) }
						{ registered.length ? (
							<SelectControl
								label={ __( 'Type', 'ohmylms' ) }
								value={ chosenType }
								disabled={ operation.busy }
								options={ registered.map( ( item ) => ( {
									value: item.id,
									label: item.label,
								} ) ) }
								help={ __(
									'The type cannot be changed after the question is created.',
									'ohmylms'
								) }
								onChange={ setType }
							/>
						) : (
							<p role="status">
								{ types.loading
									? __( 'Loading question types…', 'ohmylms' )
									: __(
											'Question types are not available. Close this dialog and try again.',
											'ohmylms'
										) }
							</p>
						) }
						<Button
							type="submit"
							variant="primary"
							disabled={ ! chosenType }
						>
							{ __( 'Continue', 'ohmylms' ) }
						</Button>{ ' ' }
						<Button
							variant="tertiary"
							disabled={ operation.busy }
							onClick={ closeCreate }
						>
							{ __( 'Cancel', 'ohmylms' ) }
						</Button>
					</form>
				</Modal>
			) }
		</>
	);
}
