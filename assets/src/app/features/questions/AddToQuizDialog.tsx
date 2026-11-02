import {
	Button,
	Modal,
	Notice,
	RadioControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import { normalizeApiError } from '../../lib/api';
import { addToQuiz, searchQuizzes } from './api';
import { referenceBlocker } from './model';
import type { QuestionStatus, QuizChoice } from './types';

/** The question being added. */
export interface QuizCandidate {
	id: number;
	name: string;
	status: QuestionStatus;
	approved_version_id: number;
	permissions: { can_edit: boolean };
	/** Quizzes that already hold it, when known. */
	quizIds?: readonly number[];
}

/** Error code of a placement write that lost the revision race. */
const CONFLICT = 'ohmylms_quiz_questions_conflict';

/**
 * Add a question to a quiz the user may edit: the question itself (shared, the quiz follows its
 * versions) or a new copy that only this quiz uses. The write carries the quiz's current revision, so a
 * change by another editor in between is refused rather than overwritten.
 *
 * @param props
 * @param props.question The question.
 * @param props.onClose  Closes the dialog.
 * @param props.onAdded  Called with the quiz and the id placed in it.
 */
export function AddToQuizDialog( {
	question,
	onClose,
	onAdded,
}: {
	question: QuizCandidate;
	onClose: () => void;
	onAdded: ( quiz: QuizChoice, placedId: number, copy: boolean ) => void;
} ) {
	const operation = useOperation();
	const [ text, setText ] = useState( '' );
	const [ quizzes, setQuizzes ] = useState< QuizChoice[] | null >( null );
	const [ searchError, setSearchError ] = useState< string | null >( null );
	const [ searching, setSearching ] = useState( false );
	const [ quizId, setQuizId ] = useState( 0 );
	const blocker = referenceBlocker( question );
	const archived = 'archived' === question.status;
	const [ mode, setMode ] = useState< 'reference' | 'copy' >(
		blocker ? 'copy' : 'reference'
	);
	const search = useRef< AbortController | null >( null );
	useEffect( () => () => search.current?.abort(), [] );

	function find( value: string ) {
		search.current?.abort();
		const controller = new AbortController();
		search.current = controller;
		setSearching( true );
		setSearchError( null );
		searchQuizzes( value, controller.signal ).then(
			( found ) => {
				if ( ! controller.signal.aborted ) {
					setQuizzes( found.filter( ( item ) => item.can_edit ) );
					setSearching( false );
					if ( ! found.some( ( item ) => item.id === quizId ) ) {
						setQuizId( 0 );
					}
				}
			},
			( failure: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setSearchError( normalizeApiError( failure ).message );
					setSearching( false );
				}
			}
		);
	}
	useEffect( () => {
		find( '' );
		// Load the first quizzes once.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const chosen = quizzes?.find( ( item ) => item.id === quizId ) ?? null;
	const alreadyIn = Boolean(
		chosen && question.quizIds?.includes( chosen.id )
	);
	const referenceProblem =
		blocker ??
		( alreadyIn
			? __( 'This question is already in that quiz.', 'ohmylms' )
			: null );
	const disabled =
		operation.busy ||
		! chosen ||
		archived ||
		( 'reference' === mode && null !== referenceProblem );

	function submit() {
		if ( ! chosen ) {
			return;
		}
		const copy = 'copy' === mode;
		void operation.run(
			async ( signal ) => {
				try {
					return await addToQuiz(
						chosen.id,
						question.id,
						copy,
						signal
					);
				} catch ( problem ) {
					// Another editor changed the quiz between our read and write: read and try once more.
					if ( CONFLICT === normalizeApiError( problem ).code ) {
						return addToQuiz(
							chosen.id,
							question.id,
							copy,
							signal
						);
					}
					throw problem;
				}
			},
			( placed ) => onAdded( chosen, placed, copy )
		);
	}

	return (
		<Modal
			title={ sprintf(
				/* translators: %s: question name. */
				__( 'Add “%s” to a quiz', 'ohmylms' ),
				question.name.trim() || __( 'Untitled question', 'ohmylms' )
			) }
			onRequestClose={ () => ! operation.busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					submit();
				} }
				noValidate
			>
				{ archived && (
					<Notice status="warning" isDismissible={ false }>
						{ blocker }
					</Notice>
				) }
				{ operation.error && (
					<Notice status="error" isDismissible={ false }>
						{ operation.error }
					</Notice>
				) }
				<fieldset>
					<legend>{ __( 'Quiz', 'ohmylms' ) }</legend>
					<TextControl
						label={ __( 'Search quizzes', 'ohmylms' ) }
						value={ text }
						maxLength={ 100 }
						disabled={ operation.busy }
						onChange={ setText }
						onKeyDown={ ( event ) => {
							if ( 'Enter' === event.key ) {
								event.preventDefault();
								find( text );
							}
						} }
					/>
					<Button
						variant="secondary"
						disabled={ operation.busy || searching }
						onClick={ () => find( text ) }
					>
						{ __( 'Search', 'ohmylms' ) }
					</Button>
					{ searchError && (
						<Notice status="error" isDismissible={ false }>
							{ searchError }
						</Notice>
					) }
					{ searching && (
						<p role="status">
							{ __( 'Loading quizzes…', 'ohmylms' ) }
						</p>
					) }
					{ ! searching && quizzes && ! quizzes.length && (
						<p>
							{ __(
								'No quizzes you can edit match.',
								'ohmylms'
							) }
						</p>
					) }
					{ ! searching && quizzes && quizzes.length > 0 && (
						<RadioControl
							label={ __( 'Quiz', 'ohmylms' ) }
							hideLabelFromVision
							selected={ quizId ? String( quizId ) : '' }
							options={ quizzes.map( ( item ) => ( {
								value: String( item.id ),
								label: sprintf(
									/* translators: 1: quiz title, 2: number of questions. */
									__( '%1$s (%2$d questions)', 'ohmylms' ),
									item.title || __( '(no title)', 'ohmylms' ),
									item.question_count
								),
							} ) ) }
							onChange={ ( value ) =>
								setQuizId( Number( value ) )
							}
						/>
					) }
				</fieldset>
				<RadioControl
					label={ __( 'What to add', 'ohmylms' ) }
					selected={ mode }
					options={ [
						{
							value: 'reference',
							label: __(
								'This question: the quiz uses it as it is edited in the question bank',
								'ohmylms'
							),
						},
						{
							value: 'copy',
							label: __(
								'A copy: a new question you can change for this quiz only',
								'ohmylms'
							),
						},
					] }
					onChange={ ( value ) =>
						setMode( 'copy' === value ? 'copy' : 'reference' )
					}
				/>
				{ 'reference' === mode && referenceProblem && ! archived && (
					<p role="alert">{ referenceProblem }</p>
				) }
				<Button
					type="submit"
					variant="primary"
					disabled={ disabled }
					isBusy={ operation.busy }
				>
					{ 'copy' === mode
						? __( 'Add a copy', 'ohmylms' )
						: __( 'Add to quiz', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button
					variant="tertiary"
					disabled={ operation.busy }
					onClick={ onClose }
				>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
			</form>
		</Modal>
	);
}
