import { useEffect, useState } from '@wordpress/element';
import { ApiError, normalizeApiError } from '../../lib/api';
import { getQuiz } from './api';
import type { QuizDetail } from './types';

/**
 * Load one quiz for the settings editor. Switching quizzes or unmounting cancels the request and
 * ignores a late result.
 *
 * @param id Quiz id, or `null` when the route id is invalid (nothing is requested).
 * @return The loaded quiz, loading and error state, a reload action and a setter for the saved copy.
 */
export function useQuizEditor( id: number | null ) {
	const [ quiz, setQuiz ] = useState< QuizDetail | null >( null );
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
		getQuiz( id, controller.signal ).then(
			( loaded ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setQuiz( loaded );
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
		quiz,
		loading,
		error,
		setQuiz,
		reload: () => setVersion( ( current ) => current + 1 ),
	};
}
