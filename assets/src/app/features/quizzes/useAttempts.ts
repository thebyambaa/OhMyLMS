import { useEffect, useState } from '@wordpress/element';
import { useListState } from '../../components/listQuery';
import { ApiError, normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { getAttempt, getQuiz, listAttempts } from './api';
import type {
	Attempt,
	AttemptDetail,
	AttemptFilters,
	QuizDetail,
} from './types';

const DEFAULT_FILTERS: AttemptFilters = { status: 'all', search: '' };

/**
 * Paged, filterable attempt list of one quiz, plus the quiz itself for the page heading. The page and
 * filters live in the address. Obsolete requests are cancelled and their results ignored.
 *
 * @param quizId Quiz id, or `null` when the route id is invalid (nothing is requested).
 * @return The quiz, attempts, loading and error state, and the actions that change the query.
 */
export function useAttempts( quizId: number | null ) {
	const [ quiz, setQuiz ] = useState< QuizDetail | null >( null );
	const list = useListState< AttemptFilters >( DEFAULT_FILTERS, {
		search: 's',
	} );
	const { page, filters, setPage } = list;
	const [ version, setVersion ] = useState( 0 );
	const [ data, setData ] = useState< Collection< Attempt > >( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	const [ loading, setLoading ] = useState( null !== quizId );
	const [ error, setError ] = useState< ApiError | null >( null );

	useEffect( () => {
		if ( null === quizId ) {
			return undefined;
		}
		const controller = new AbortController();
		getQuiz( quizId, controller.signal ).then(
			( loaded ) => {
				if ( ! controller.signal.aborted ) {
					setQuiz( loaded );
				}
			},
			() => undefined
		);
		return () => controller.abort();
	}, [ quizId, version ] );

	useEffect( () => {
		if ( null === quizId ) {
			return undefined;
		}
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		listAttempts( quizId, page, filters, controller.signal ).then(
			( next ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setData( next );
				setLoading( false );
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				const problem = normalizeApiError( failure );
				if (
					page > 1 &&
					'ohmylms_attempt_invalid_page' === problem.code
				) {
					setPage( page - 1, { replace: true } );
					return;
				}
				setError( problem );
				setLoading( false );
			}
		);
		return () => controller.abort();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ quizId, page, filters, version ] );

	return {
		quiz,
		data,
		loading,
		error,
		page,
		filters,
		setPage,
		setFilters: list.setFilters,
		pageHref: list.pageHref,
		filtersHref: list.filtersHref,
		reload: () => setVersion( ( current ) => current + 1 ),
	};
}

/**
 * Load one attempt for review. Switching attempts or unmounting cancels the request.
 *
 * @param quizId    Quiz id.
 * @param attemptId Attempt id, or `null` when none is open.
 * @return The attempt, loading and error state, and a setter for the refreshed copy.
 */
export function useAttemptDetail( quizId: number, attemptId: number | null ) {
	const [ attempt, setAttempt ] = useState< AttemptDetail | null >( null );
	const [ loading, setLoading ] = useState( null !== attemptId );
	const [ error, setError ] = useState< ApiError | null >( null );
	const [ version, setVersion ] = useState( 0 );
	useEffect( () => {
		setAttempt( null );
		if ( null === attemptId ) {
			setLoading( false );
			return undefined;
		}
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		getAttempt( quizId, attemptId, controller.signal ).then(
			( loaded ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setAttempt( loaded );
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
	}, [ quizId, attemptId, version ] );
	return {
		attempt,
		loading,
		error,
		setAttempt,
		reload: () => setVersion( ( current ) => current + 1 ),
	};
}
