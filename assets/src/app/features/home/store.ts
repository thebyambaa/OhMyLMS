/**
 * The `ohmylms/home` store: the reference for the per-feature state convention (docs/ARCHITECTURE.md).
 *
 * - One store per feature, named `ohmylms/<feature>`, defined and registered in the feature's `store.ts`.
 * - State is plain, serializable and typed. Errors are stored as {@link LoadError}, not as `Error`.
 * - Reducers are pure. Selectors only read. Network access happens in a resolver or a thunk action and goes
 *   through `lib/api`, never through `fetch` directly.
 * - A resolver loads data the first time its selector is called; a thunk action (`reload`) repeats it.
 * - Components consume the store through the typed hooks in `hooks.ts`, not through `select` directly.
 */
import { createReduxStore, register } from '@wordpress/data';
import { get, normalizeApiError } from '../../lib/api';
import type { ContentType, HomeState, LoadError } from './types';

export const STORE_NAME = 'ohmylms/home';

export const DEFAULT_STATE: HomeState = {
	status: 'idle',
	items: [],
	error: null,
};

type HomeAction =
	| { type: 'LOAD_STARTED' }
	| { type: 'ITEMS_RECEIVED'; items: readonly ContentType[] }
	| { type: 'LOAD_FAILED'; error: LoadError };

/**
 * Reducer.
 *
 * @param state  Current state.
 * @param action Dispatched action.
 * @return Next state.
 */
export function reducer(
	state: HomeState = DEFAULT_STATE,
	action: HomeAction
): HomeState {
	switch ( action.type ) {
		case 'LOAD_STARTED':
			return { ...state, status: 'loading', error: null };
		case 'ITEMS_RECEIVED':
			return { status: 'ready', items: action.items, error: null };
		case 'LOAD_FAILED':
			return { ...state, status: 'error', error: action.error };
		default:
			return state;
	}
}

/**
 * Pick the fields the demo needs from the `/wp/v2/types` response. Anything that is not an object with a
 * string name is ignored, so a surprising payload degrades to fewer rows instead of throwing in a render.
 *
 * @param raw Parsed response body.
 * @return Content types sorted by name.
 */
export function toContentTypes( raw: unknown ): ContentType[] {
	if ( 'object' !== typeof raw || null === raw ) {
		return [];
	}
	const items: ContentType[] = [];
	for ( const [ key, value ] of Object.entries( raw ) ) {
		if ( 'object' !== typeof value || null === value ) {
			continue;
		}
		const { name, slug, description } = value as Record< string, unknown >;
		if ( 'string' === typeof name ) {
			items.push( {
				slug: 'string' === typeof slug ? slug : key,
				name,
				description: 'string' === typeof description ? description : '',
			} );
		}
	}
	return items.sort( ( a, b ) => a.name.localeCompare( b.name ) );
}

/** Action creators bound to the store, as seen inside a thunk. */
interface HomeDispatch {
	loadStarted: () => void;
	itemsReceived: ( items: readonly ContentType[] ) => void;
	loadFailed: ( error: LoadError ) => void;
	reload: () => Promise< void >;
}

const actions = {
	loadStarted: (): HomeAction => ( { type: 'LOAD_STARTED' } ),
	itemsReceived: ( items: readonly ContentType[] ): HomeAction => ( {
		type: 'ITEMS_RECEIVED',
		items,
	} ),
	loadFailed: ( error: LoadError ): HomeAction => ( {
		type: 'LOAD_FAILED',
		error,
	} ),
	/** Thunk: (re)load the list. Used by the resolver and by the Retry button. */
	reload:
		() =>
		async ( { dispatch }: { dispatch: HomeDispatch } ) => {
			dispatch.loadStarted();
			try {
				const raw = await get< unknown >( '/wp/v2/types' );
				dispatch.itemsReceived( toContentTypes( raw ) );
			} catch ( error ) {
				const { code, message, status } = normalizeApiError( error );
				dispatch.loadFailed( { code, message, status } );
			}
		},
};

const selectors = {
	getStatus: ( state: HomeState ) => state.status,
	getContentTypes: ( state: HomeState ) => state.items,
	getError: ( state: HomeState ) => state.error,
};

const resolvers = {
	/** Runs the first time `getContentTypes` is selected. */
	getContentTypes:
		() =>
		async ( { dispatch }: { dispatch: HomeDispatch } ) => {
			await dispatch.reload();
		},
};

export const homeStore = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
	resolvers,
} );

register( homeStore );
