import { useEffect, useState } from '@wordpress/element';
import { ApiError, normalizeApiError } from '../../lib/api';
import { getQuestion, listQuestionTypes } from './api';
import type { QuestionDetail, QuestionType } from './types';

/**
 * Load one question and the registered types for the editor, in parallel. Switching questions or
 * unmounting cancels the requests and ignores late results.
 *
 * @param id Question id, or `null` when the route id is invalid (nothing is requested).
 * @return The loaded data, loading and error state, a reload action and a setter for the saved copy.
 */
export function useQuestionEditor( id: number | null ) {
	const [ question, setQuestion ] = useState< QuestionDetail | null >( null );
	const [ types, setTypes ] = useState< QuestionType[] | null >( null );
	const [ loading, setLoading ] = useState( null !== id );
	const [ error, setError ] = useState< ApiError | null >( null );
	const [ version, setVersion ] = useState( 0 );
	useEffect( () => {
		if ( null === id ) {
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
