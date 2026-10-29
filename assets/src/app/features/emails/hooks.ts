import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { useNavigate } from 'react-router-dom';
import { normalizeApiError } from '../../lib/api';
import {
	getEmail,
	getEmailSettings,
	listEmails,
	previewEmail,
	setEmailEnabled,
} from './api';
import type {
	EmailFilters,
	EmailInput,
	EmailPreview,
	EmailSettings,
	EmailTemplate,
} from './types';

/** Delay before a changed draft is rendered again. */
export const PREVIEW_DELAY_MS = 500;

interface Loaded< T > {
	data: T | null;
	loading: boolean;
	error: string | null;
	reload: () => void;
}

/**
 * Load one value with cancellation; a response that arrives after the request was cancelled is ignored.
 *
 * @param load Starts the request.
 * @param key  Changing the key loads again.
 */
function useLoaded< T >(
	load: ( signal: AbortSignal ) => Promise< T >,
	key: string
): Loaded< T > & { setData: ( value: T ) => void } {
	const [ data, setData ] = useState< T | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );
	const [ version, setVersion ] = useState( 0 );
	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		load( controller.signal ).then(
			( value ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setData( value );
				setLoading( false );
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setError( normalizeApiError( failure ).message );
				setLoading( false );
			}
		);
		return () => controller.abort();
		// `load` is recreated by callers every render; `key` and `version` define when to load.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ key, version ] );
	return {
		data,
		loading,
		error,
		setData,
		reload: () => setVersion( ( current ) => current + 1 ),
	};
}

/**
 * The email list with filters and enable toggles. A toggle is applied at once and rolled back, with a
 * message, if the server refuses it.
 */
export function useEmails() {
	const [ filters, setFilters ] = useState< EmailFilters >( {
		search: '',
		recipient_type: 'all',
	} );
	const [ items, setItems ] = useState< EmailTemplate[] >( [] );
	const [ pending, setPending ] = useState< readonly string[] >( [] );
	const [ toggleError, setToggleError ] = useState< string | null >( null );
	const inFlight = useRef( new Map< string, AbortController >() );
	const list = useLoaded(
		( signal ) => listEmails( filters, signal ),
		JSON.stringify( filters )
	);

	useEffect( () => {
		if ( list.data ) {
			setItems( list.data.items );
		}
	}, [ list.data ] );

	useEffect( () => {
		const running = inFlight.current;
		return () => {
			running.forEach( ( controller ) => controller.abort() );
			running.clear();
		};
	}, [] );

	const toggle = useCallback( async ( id: string, enabled: boolean ) => {
		if ( inFlight.current.has( id ) ) {
			return;
		}
		const controller = new AbortController();
		inFlight.current.set( id, controller );
		setPending( ( current ) => [ ...current, id ] );
		setToggleError( null );
		const setState = ( value: boolean ) =>
			setItems( ( current ) =>
				current.map( ( item ) =>
					item.id === id ? { ...item, enabled: value } : item
				)
			);
		setState( enabled );
		try {
			const saved = await setEmailEnabled(
				id,
				enabled,
				controller.signal
			);
			if ( ! controller.signal.aborted ) {
				setItems( ( current ) =>
					current.map( ( item ) => ( item.id === id ? saved : item ) )
				);
			}
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				setState( ! enabled );
				setToggleError( normalizeApiError( failure ).message );
			}
		} finally {
			if ( inFlight.current.get( id ) === controller ) {
				inFlight.current.delete( id );
			}
			if ( ! controller.signal.aborted ) {
				setPending( ( current ) =>
					current.filter( ( value ) => value !== id )
				);
			}
		}
	}, [] );

	return {
		items,
		total: list.data?.total ?? 0,
		loading: list.loading,
		error: list.error,
		reload: list.reload,
		filters,
		setFilters,
		pending,
		toggle,
		toggleError,
		clearToggleError: () => setToggleError( null ),
	};
}

/**
 * One email for the editor.
 *
 * @param id Email id.
 */
export function useEmail( id: string ) {
	const state = useLoaded( ( signal ) => getEmail( id, signal ), id );
	return {
		email: state.data,
		setEmail: state.setData,
		loading: state.loading,
		error: state.error,
		reload: state.reload,
	};
}

/** The global email settings. */
export function useEmailSettings() {
	const state = useLoaded(
		( signal ) => getEmailSettings( signal ),
		'settings'
	);
	return {
		settings: state.data as EmailSettings | null,
		setSettings: state.setData,
		loading: state.loading,
		error: state.error,
		reload: state.reload,
	};
}

/**
 * Render the draft with the real template after the user pauses typing. The newest request wins:
 * older ones are cancelled and cannot overwrite a newer preview.
 *
 * @param id     Email id.
 * @param input  Draft fields.
 * @param active False pauses previews (for example while the draft has errors).
 */
export function useEmailPreview(
	id: string,
	input: EmailInput,
	active: boolean
) {
	const [ preview, setPreview ] = useState< EmailPreview | null >( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const key = useMemo( () => JSON.stringify( input ), [ input ] );
	const latest = useRef( input );
	latest.current = input;
	useEffect( () => {
		if ( ! active ) {
			setLoading( false );
			return undefined;
		}
		const controller = new AbortController();
		setLoading( true );
		const timer = setTimeout( () => {
			previewEmail( id, latest.current, controller.signal ).then(
				( result ) => {
					if ( controller.signal.aborted ) {
						return;
					}
					setPreview( result );
					setError( null );
					setLoading( false );
				},
				( failure: unknown ) => {
					if ( controller.signal.aborted ) {
						return;
					}
					setError( normalizeApiError( failure ).message );
					setLoading( false );
				}
			);
		}, PREVIEW_DELAY_MS );
		return () => {
			clearTimeout( timer );
			controller.abort();
		};
	}, [ id, key, active ] );
	return { preview, loading, error };
}

/**
 * Guard against losing unsaved edits. Reloading or closing the tab shows the browser's own prompt. A click
 * on any in-app link is held back and reported as `pendingPath`; the page asks, then calls
 * `confirmLeave()` or `stay()`. The browser's back button cannot be intercepted by a hash router.
 *
 * @param dirty Whether there are unsaved changes.
 */
export function useUnsavedChangesGuard( dirty: boolean ) {
	const navigate = useNavigate();
	const [ pendingPath, setPendingPath ] = useState< string | null >( null );

	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const beforeUnload = ( event: BeforeUnloadEvent ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		const onClick = ( event: MouseEvent ) => {
			if (
				event.defaultPrevented ||
				0 !== event.button ||
				event.metaKey ||
				event.ctrlKey ||
				event.shiftKey ||
				event.altKey ||
				! ( event.target instanceof Element )
			) {
				return;
			}
			const anchor = event.target.closest( 'a[href]' );
			const href = anchor?.getAttribute( 'href' ) ?? '';
			const path = href.startsWith( '#/' ) ? href.slice( 1 ) : href;
			if ( ! path.startsWith( '/' ) || path.startsWith( '//' ) ) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			setPendingPath( path );
		};
		window.addEventListener( 'beforeunload', beforeUnload );
		document.addEventListener( 'click', onClick, true );
		return () => {
			window.removeEventListener( 'beforeunload', beforeUnload );
			document.removeEventListener( 'click', onClick, true );
		};
	}, [ dirty ] );

	return {
		pendingPath,
		requestLeave: ( path: string ) => {
			if ( dirty ) {
				setPendingPath( path );
			} else {
				navigate( path );
			}
		},
		confirmLeave: () => {
			const path = pendingPath;
			setPendingPath( null );
			if ( path ) {
				navigate( path );
			}
		},
		stay: () => setPendingPath( null ),
	};
}
