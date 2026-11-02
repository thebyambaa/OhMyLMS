import { useEffect, useRef, useState } from '@wordpress/element';
import { ApiError, normalizeApiError } from '../../lib/api';
import { getQuestion, listQuestionTypes } from './api';
import type { QuestionDetail, QuestionType } from './types';

/** A question the page already holds (just created or duplicated), so opening it needs no request. */
export interface QuestionHandoff {
	question: QuestionDetail;
	types: QuestionType[];
	/** Success notice to show when the editor opens. */
	message?: string;
}

/**
 * Load one question and the registered types for the editor, in parallel. Switching questions or
 * unmounting cancels the requests and ignores late results.
 *
 * @param id      Question id, or `null` when the route id is invalid (nothing is requested).
 * @param handoff Question and types to start from instead of the first request; ignored unless it is
 *                the question `id`. Reloading requests them.
 * @return The loaded data, loading and error state, a reload action and a setter for the saved copy.
 */
export function useQuestionEditor(
	id: number | null,
	handoff: QuestionHandoff | null = null
) {
	const start = handoff && handoff.question.id === id ? handoff : null;
	const [ question, setQuestion ] = useState< QuestionDetail | null >(
		start?.question ?? null
	);
	const [ types, setTypes ] = useState< QuestionType[] | null >(
		start?.types ?? null
	);
	const [ loading, setLoading ] = useState( null !== id && ! start );
	const [ error, setError ] = useState< ApiError | null >( null );
	const [ version, setVersion ] = useState( 0 );
	const skipFirst = useRef( !! start );
	useEffect( () => {
		if ( null === id ) {
			return undefined;
		}
		if ( skipFirst.current ) {
			skipFirst.current = false;
			return undefined;
		}
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		Promise.all( [
			getQuestion( id, controller.signal ),
			listQuestionTypes( controller.signal ),
		] ).then(
			( [ loaded, registered ] ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setQuestion( loaded );
				setTypes( registered );
				setLoading( false );
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setError( normalizeApiError( failure ) );
				setLoading( false );
			}
		);
		return () => controller.abort();
	}, [ id, version ] );
	return {
		question,
		types,
		loading,
		error,
		setQuestion,
		reload: () => setVersion( ( current ) => current + 1 ),
	};
}
