import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	RadioControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Pagination } from '../../components/Pagination';
import type { Collection } from '../../lib/api/collection';
import { normalizeApiError } from '../../lib/api';
import {
	listQuestions,
	typeLabel,
	type QuestionSummary,
	type QuestionType,
} from '../questions/public';
import { addBlocker, copyBlocker, formatMarks } from './model';

/** Most questions added in one request (the server's limit). */
export const MAX_PICK = 50;

interface Query {
	page: number;
	search: string;
	type: string;
	approvedOnly: boolean;
}

/**
 * Searchable picker over the question bank (`GET /questions`). Questions already in the quiz, archived
 * questions and shared questions without an approved version cannot be chosen. The choice survives
 * paging and searching.
 *
 * @param props
 * @param props.inQuiz  Question ids already in the quiz.
 * @param props.types   Registered question types, or `null` while they load.
 * @param props.busy    An add request is running.
 * @param props.error   Error of the last add request.
 * @param props.onAdd   Adds the chosen questions.
 * @param props.onClose Closes the picker.
 */
export function QuestionPicker( {
	inQuiz,
	types,
	busy,
	error,
	onAdd,
	onClose,
}: {
	inQuiz: ReadonlySet< number >;
	types: QuestionType[] | null;
	busy: boolean;
	error: string | null;
	onAdd: ( ids: number[], pin: boolean, copy: boolean ) => void;
	onClose: () => void;
} ) {
	const [ copy, setCopy ] = useState( false );
	const [ search, setSearch ] = useState( '' );
	const [ query, setQuery ] = useState< Query >( {
		page: 1,
		search: '',
		type: '',
		approvedOnly: false,
	} );
	const [ result, setResult ] =
		useState< Collection< QuestionSummary > | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState< string | null >( null );
	const [ attempt, setAttempt ] = useState( 0 );
	const [ chosen, setChosen ] = useState< Set< number > >( () => new Set() );
	const [ pin, setPin ] = useState( false );

	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setLoadError( null );
		listQuestions(
			{
				page: query.page,
				search: query.search,
				type: query.type,
				difficulty: '',
				status: query.approvedOnly ? 'approved' : 'active',
			},
			controller.signal
		).then(
			( loaded ) => {
				if ( ! controller.signal.aborted ) {
					setResult( loaded );
					setLoading( false );
				}
			},
			( failure: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setLoadError( normalizeApiError( failure ).message );
					setLoading( false );
				}
			}
		);
		return () => controller.abort();
	}, [ query, attempt ] );

	function toggle( item: QuestionSummary, checked: boolean ) {
		setChosen( ( current ) => {
			const next = new Set( current );
			if ( checked ) {
				next.add( item.id );
			} else {
				next.delete( item.id );
			}
			return next;
		} );
	}

	const tooMany = chosen.size > MAX_PICK;

	return (
		<Modal
			title={ __( 'Add questions from the question bank', 'ohmylms' ) }
			onRequestClose={ onClose }
			shouldCloseOnClickOutside={ false }
		>
			<form
				role="search"
				aria-label={ __( 'Search the question bank', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setQuery( ( current ) => ( {
						...current,
						page: 1,
						search: search.trim(),
					} ) );
				} }
			>
				<TextControl
					label={ __( 'Search questions', 'ohmylms' ) }
					value={ search }
					maxLength={ 100 }
					onChange={ setSearch }
				/>
				{ types && (
					<SelectControl
						label={ __( 'Type', 'ohmylms' ) }
						value={ query.type }
						options={ [
							{ value: '', label: __( 'All types', 'ohmylms' ) },
							...types.map( ( item ) => ( {
								value: item.id,
								label: item.label,
							} ) ),
						] }
						onChange={ ( type ) =>
							setQuery( ( current ) => ( {
								...current,
								page: 1,
								type,
							} ) )
						}
					/>
				) }
				<CheckboxControl
					label={ __( 'Approved questions only', 'ohmylms' ) }
					help={ __(
						'Shared questions you cannot edit can be added only once a version is approved.',
						'ohmylms'
					) }
					checked={ query.approvedOnly }
					onChange={ ( approvedOnly ) =>
						setQuery( ( current ) => ( {
							...current,
							page: 1,
							approvedOnly,
						} ) )
					}
				/>
				<Button type="submit" variant="secondary" disabled={ loading }>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			{ loading && (
				<p role="status">{ __( 'Loading questions…', 'ohmylms' ) }</p>
			) }
			{ loadError && (
				<Notice status="error" isDismissible={ false }>
					{ loadError }{ ' ' }
					<Button
						variant="link"
						onClick={ () =>
							setAttempt( ( current ) => current + 1 )
						}
					>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ ! loading && ! loadError && result && (
				<fieldset>
					<legend>{ __( 'Questions', 'ohmylms' ) }</legend>
					{ result.items.length ? (
						<ul className="oml-quiz-picker">
							{ result.items.map( ( item ) => {
								const blocker = copy
									? copyBlocker( item )
									: addBlocker( item, inQuiz );
								const name =
									item.name.trim() ||
									__( 'Untitled question', 'ohmylms' );
								return (
									<li key={ item.id }>
										<CheckboxControl
											label={ sprintf(
												/* translators: 1: question name, 2: type, 3: marks. */
												__(
													'%1$s (%2$s, %3$s marks)',
													'ohmylms'
												),
												name,
												typeLabel(
													types ?? [],
													item.type,
													item.type_label
												),
												formatMarks( item.marks )
											) }
											help={
												blocker ??
												( item.permissions.can_edit ||
												copy
													? undefined
													: __(
															'Shared question: its approved version is used.',
															'ohmylms'
														) )
											}
											checked={ chosen.has( item.id ) }
											disabled={
												busy || null !== blocker
											}
											onChange={ ( checked ) =>
												toggle( item, checked )
											}
										/>
									</li>
								);
							} ) }
						</ul>
					) : (
						<p>{ __( 'No questions found.', 'ohmylms' ) }</p>
					) }
					<Pagination
						page={ query.page }
						pages={ result.totalPages }
						busy={ loading || busy }
						onChange={ ( page ) =>
							setQuery( ( current ) => ( { ...current, page } ) )
						}
					/>
				</fieldset>
			) }
			<RadioControl
				label={ __( 'Add as', 'ohmylms' ) }
				selected={ copy ? 'copy' : 'reference' }
				options={ [
					{
						value: 'reference',
						label: __(
							'The questions themselves, shared with the question bank',
							'ohmylms'
						),
					},
					{
						value: 'copy',
						label: __(
							'Copies: new questions you can change for this quiz only',
							'ohmylms'
						),
					},
				] }
				onChange={ ( value ) => {
					const next = 'copy' === value;
					setCopy( next );
					setChosen( ( current ) => {
						const kept = new Set< number >();
						for ( const item of result?.items ?? [] ) {
							if (
								current.has( item.id ) &&
								null ===
									( next
										? copyBlocker( item )
										: addBlocker( item, inQuiz ) )
							) {
								kept.add( item.id );
							}
						}
						return kept;
					} );
				} }
			/>
			{ ! copy && (
				<CheckboxControl
					label={ __( 'Pin to the version used now', 'ohmylms' ) }
					help={ __(
						'Learners keep getting this version when the question is changed later.',
						'ohmylms'
					) }
					checked={ pin }
					disabled={ busy }
					onChange={ setPin }
				/>
			) }
			{ tooMany && (
				<p role="alert">
					{ sprintf(
						/* translators: %d: maximum questions per request. */
						__(
							'Choose at most %d questions at a time.',
							'ohmylms'
						),
						MAX_PICK
					) }
				</p>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<Button
				variant="primary"
				disabled={ busy || 0 === chosen.size || tooMany }
				isBusy={ busy }
				onClick={ () => onAdd( [ ...chosen ], ! copy && pin, copy ) }
			>
				{ copy
					? sprintf(
							/* translators: %d: number of chosen questions. */
							_n(
								'Add a copy of %d question',
								'Add copies of %d questions',
								chosen.size,
								'ohmylms'
							),
							chosen.size
						)
					: sprintf(
							/* translators: %d: number of chosen questions. */
							_n(
								'Add %d question',
								'Add %d questions',
								chosen.size,
								'ohmylms'
							),
							chosen.size
						) }
			</Button>{ ' ' }
			<Button variant="tertiary" disabled={ busy } onClick={ onClose }>
				{ __( 'Cancel', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
