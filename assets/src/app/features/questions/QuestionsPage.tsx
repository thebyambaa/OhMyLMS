import { ListTable, ListTableSelect } from '../../../ui/ListTable';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { statusViews } from '../../components/listViews';
import { useOperation } from '../../hooks/useOperation';
import {
	PageTitleAction,
	PageTitleActions,
} from '../../layout/PageTitleActions';
import { useRequest } from '../../hooks/useRequest';
import {
	archiveQuestion,
	duplicateQuestion,
	listQuestionTypes,
	restoreQuestion,
} from './api';
import {
	deleteMessage,
	DIFFICULTIES,
	difficultyLabel,
	MAX_NAME,
	statusFilters,
	typeLabel,
	usageLabel,
	versionLabel,
} from './model';
import { exportQuestions, saveFile, type ExportFormat } from './exchange';
import { AddToQuizDialog } from './AddToQuizDialog';
import { DeleteQuestionsDialog } from './DeleteQuestionsDialog';
import { ImportQuestionsDialog } from './ImportQuestionsDialog';
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
 * `#/content-hub/question-bank/<id>`. `?action=new` opens the "New question" dialog and
 * `?action=import` the import dialog; selected or filtered questions export as CSV or JSON.
 */
export default function QuestionsPage() {
	const list = useQuestions();
	const types = useRequest( listQuestionTypes );
	const navigate = useNavigate();
	const operation = useOperation();
	const [ params, setParams ] = useSearchParams();
	const [ importing, setImporting ] = useState( false );
	const [ selected, setSelected ] = useState< number[] >( [] );
	const [ search, setSearch ] = useState( list.query.search );
	useEffect( () => setSearch( list.query.search ), [ list.query.search ] );
	const [ creating, setCreating ] = useState( false );
	const [ name, setName ] = useState( '' );
	const [ type, setType ] = useState( '' );
	const [ nameError, setNameError ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ copyId, setCopyId ] = useState< number | null >( null );
	const [ deleting, setDeleting ] = useState< QuestionSummary[] | null >(
		null
	);
	const [ adding, setAdding ] = useState< QuestionSummary | null >( null );
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
	const action = params.get( 'action' );
	useEffect( () => {
		if ( 'import' === action ) {
			setImporting( true );
		} else if ( 'new' === action ) {
			setName( '' );
			setType( '' );
			setNameError( null );
			setCreating( true );
		} else {
			return;
		}
		setParams(
			( current ) => {
				const next = new URLSearchParams( current );
				next.delete( 'action' );
				return next;
			},
			{ replace: true }
		);
	}, [ action, setParams ] );
	function download( scope: 'selected' | 'filtered', format: ExportFormat ) {
		setMessage( null );
		setCopyId( null );
		void operation.run(
			( signal ) =>
				exportQuestions(
					'selected' === scope
						? { ids: selected }
						: { filters: list.query },
					format,
					signal
				),
			( file ) => {
				saveFile( file );
				setMessage(
					file.skipped
						? sprintf(
								/* translators: 1: exported questions, 2: skipped questions. */
								__(
									'Exported %1$d questions. %2$d you cannot edit were left out, because the file holds answer keys.',
									'ohmylms'
								),
								file.count,
								file.skipped
							)
						: sprintf(
								/* translators: %d: exported questions. */
								_n(
									'Exported %d question.',
									'Exported %d questions.',
									file.count,
									'ohmylms'
								),
								file.count
							)
				);
			}
		);
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
			<PageTitleActions>
				<PageTitleAction
					disabled={ operation.busy }
					onClick={ openCreate }
				>
					{ __( 'New question', 'ohmylms' ) }
				</PageTitleAction>
				<PageTitleAction
					disabled={ operation.busy }
					onClick={ () => {
						setMessage( null );
						setImporting( true );
					} }
				>
					{ __( 'Import questions', 'ohmylms' ) }
				</PageTitleAction>
			</PageTitleActions>
			<p>
				{ __(
					'Questions you can reuse across quizzes. Each save keeps a version.',
					'ohmylms'
				) }
			</p>
			<div
				role="group"
				aria-label={ __( 'Export questions', 'ohmylms' ) }
			>
				{ ( [ 'csv', 'json' ] as const ).map( ( format ) => (
					<Button
						key={ `selected-${ format }` }
						variant="tertiary"
						disabled={ operation.busy || ! selected.length }
						onClick={ () => download( 'selected', format ) }
					>
						{ sprintf(
							/* translators: 1: number of selected questions, 2: CSV or JSON. */
							__( 'Export %1$d selected (%2$s)', 'ohmylms' ),
							selected.length,
							format.toUpperCase()
						) }
					</Button>
				) ) }
				{ ( [ 'csv', 'json' ] as const ).map( ( format ) => (
					<Button
						key={ `filtered-${ format }` }
						variant="tertiary"
						disabled={ operation.busy || ! list.data.total }
						onClick={ () => download( 'filtered', format ) }
					>
						{ sprintf(
							/* translators: %s: CSV or JSON. */
							__( 'Export all matching (%s)', 'ohmylms' ),
							format.toUpperCase()
						) }
					</Button>
				) ) }
			</div>
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
			{ ! list.loading && ! list.error && (
				<ListTable< QuestionSummary >
					caption={ __( 'Questions', 'ohmylms' ) }
					heading={ __( 'Questions list', 'ohmylms' ) }
					formLabel={ __( 'Search questions', 'ohmylms' ) }
					busy={ operation.busy }
					rows={ list.data.items }
					rowKey={ ( item ) => item.id }
					rowLabel={ displayName }
					isSelectable={ ( item ) => item.permissions.can_edit }
					selected={ selected }
					onSelectedChange={ ( keys ) =>
						setSelected( keys.map( Number ) )
					}
					bulkActions={ {
						actions: [
							{
								value: 'delete',
								label: __( 'Delete', 'ohmylms' ),
							},
						],
						onApply: ( _action, _keys, rows ) => {
							const deletable = rows.filter(
								( row ) => row.permissions.can_delete
							);
							setMessage( null );
							setCopyId( null );
							if ( ! deletable.length ) {
								setMessage(
									__(
										'You cannot delete the selected questions.',
										'ohmylms'
									)
								);
								return false;
							}
							setDeleting( deletable );
							return false;
						},
					} }
					emptyMessage={ __( 'No questions found.', 'ohmylms' ) }
					views={ statusViews(
						statusFilters(),
						list.query.status,
						list.data.total,
						( value ) => {
							const found = statusFilters().find(
								( option ) => option.value === value
							);
							if ( found ) {
								list.setStatus( found.value as StatusFilter );
							}
						},
						list.statusHref
					) }
					search={ {
						label: __( 'Search questions', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						value: search,
						maxLength: 100,
						onChange: setSearch,
					} }
					onFilter={ () => list.search( search ) }
					filterLabel={ null }
					filters={
						<>
							<ListTableSelect
								label={ __( 'Type', 'ohmylms' ) }
								value={ list.query.type }
								options={ [
									{
										value: '',
										label: __( 'All types', 'ohmylms' ),
									},
									...registered.map( ( item ) => ( {
										value: item.id,
										label: item.label,
									} ) ),
								] }
								onChange={ list.setType }
							/>
							<ListTableSelect
								label={ __( 'Difficulty', 'ohmylms' ) }
								value={ list.query.difficulty }
								options={ [
									{
										value: '',
										label: __(
											'All difficulties',
											'ohmylms'
										),
									},
									...DIFFICULTIES.map( ( value ) => ( {
										value,
										label: difficultyLabel( value ),
									} ) ),
								] }
								onChange={ list.setDifficulty }
							/>
						</>
					}
					pagination={ {
						page: list.query.page,
						totalPages: list.data.totalPages,
						totalItems: list.data.total,
						itemsLabel: sprintf(
							/* translators: %d: total question count. */
							_n(
								'%d question found.',
								'%d questions found.',
								list.data.total,
								'ohmylms'
							),
							list.data.total
						),
						onChange: list.setPage,
						href: list.pageHref,
					} }
					columns={ [
						{
							id: 'question',
							label: __( 'Question', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<strong>
									<Link
										className="row-title"
										to={ `${ EDITOR }/${ item.id }` }
									>
										{ displayName( item ) }
									</Link>
								</strong>
							),
						},
						{
							id: 'type',
							label: __( 'Type', 'ohmylms' ),
							render: ( item ) =>
								typeLabel(
									registered,
									item.type,
									item.type_label
								),
						},
						{
							id: 'version',
							label: __( 'Version', 'ohmylms' ),
							render: versionLabel,
						},
						{
							id: 'difficulty',
							label: __( 'Difficulty', 'ohmylms' ),
							render: ( item ) =>
								difficultyLabel( item.difficulty ),
						},
						{
							id: 'usage',
							label: __( 'Used in', 'ohmylms' ),
							render: usageLabel,
						},
					] }
					rowActions={ ( item ) => [
						item.permissions.can_edit && {
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							render: (
								<Link to={ `${ EDITOR }/${ item.id }` }>
									{ __( 'Edit', 'ohmylms' ) }
								</Link>
							),
						},
						{
							key: 'duplicate',
							label: __( 'Duplicate', 'ohmylms' ),
							disabled: operation.busy,
							onClick: () => duplicate( item ),
							ariaLabel: sprintf(
								/* translators: %s: question name. */
								__( 'Duplicate %s', 'ohmylms' ),
								displayName( item )
							),
						},
						'archived' !== item.status && {
							key: 'add-to-quiz',
							label: __( 'Add to quiz', 'ohmylms' ),
							disabled: operation.busy,
							onClick: () => {
								setMessage( null );
								setCopyId( null );
								setAdding( item );
							},
							ariaLabel: sprintf(
								/* translators: %s: question name. */
								__( 'Add %s to a quiz', 'ohmylms' ),
								displayName( item )
							),
						},
						item.permissions.can_edit && {
							key:
								'archived' === item.status
									? 'untrash'
									: 'archive',
							label:
								'archived' === item.status
									? __( 'Restore', 'ohmylms' )
									: __( 'Archive', 'ohmylms' ),
							destructive: 'archived' !== item.status,
							disabled: operation.busy,
							onClick: () => changeLifecycle( item ),
							ariaLabel: lifecycleLabel( item ),
						},
						item.permissions.can_delete && {
							key: 'delete',
							label: __( 'Delete', 'ohmylms' ),
							destructive: true,
							disabled: operation.busy,
							onClick: () => {
								setMessage( null );
								setCopyId( null );
								setDeleting( [ item ] );
							},
							ariaLabel: sprintf(
								/* translators: %s: question name. */
								__( 'Delete %s', 'ohmylms' ),
								displayName( item )
							),
						},
					] }
				/>
			) }
			{ deleting && (
				<DeleteQuestionsDialog
					items={ deleting }
					onClose={ () => setDeleting( null ) }
					onDone={ ( result ) => {
						setDeleting( null );
						const done = new Set(
							result.results.map( ( row ) => row.id )
						);
						setSelected( ( current ) =>
							current.filter( ( id ) => ! done.has( id ) )
						);
						setMessage( deleteMessage( result ) );
						list.reload();
					} }
				/>
			) }
			{ adding && (
				<AddToQuizDialog
					question={ adding }
					onClose={ () => setAdding( null ) }
					onAdded={ ( quiz, placed, copy ) => {
						setAdding( null );
						setMessage(
							copy
								? sprintf(
										/* translators: 1: question name, 2: quiz title. */
										__(
											'Added a copy of %1$s to %2$s.',
											'ohmylms'
										),
										displayName( adding ),
										quiz.title
									)
								: sprintf(
										/* translators: 1: question name, 2: quiz title. */
										__( 'Added %1$s to %2$s.', 'ohmylms' ),
										displayName( adding ),
										quiz.title
									)
						);
						setCopyId( copy ? placed : null );
						list.reload();
					} }
				/>
			) }
			{ importing && (
				<ImportQuestionsDialog
					onClose={ () => setImporting( false ) }
					onImported={ () => list.reload() }
				/>
			) }
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
