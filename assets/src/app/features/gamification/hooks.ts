import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { getOptions, getSection, saveSection } from './api';
import {
	DEFAULT_LIMITS,
	buildPayload,
	describeFailure,
	isDirty,
	toDraft,
	validateLeaderboard,
	validatePoints,
} from './model';
import type {
	DraftMap,
	FailureView,
	FieldErrors,
	GamificationOptions,
	LeaderboardDraft,
	Limits,
	PointsDraft,
	SectionId,
	SectionMap,
} from './types';

function validateSection< S extends SectionId >(
	section: S,
	draft: DraftMap[ S ],
	limits: Limits
): FieldErrors {
	if ( 'points' === section ) {
		return validatePoints( draft as PointsDraft, limits );
	}
	if ( 'leaderboard' === section ) {
		return validateLeaderboard( draft as LeaderboardDraft );
	}
	return {};
}

/** State and actions of one settings section. */
export interface SectionState< S extends SectionId > {
	saved: SectionMap[ S ] | null;
	draft: DraftMap[ S ] | null;
	loading: boolean;
	loaded: boolean;
	saving: boolean;
	loadError: FailureView | null;
	saveError: FailureView | null;
	errors: FieldErrors;
	dirty: boolean;
	/** True after a successful save until the next edit. */
	justSaved: boolean;
	update: ( change: ( draft: DraftMap[ S ] ) => DraftMap[ S ] ) => void;
	save: () => Promise< boolean >;
	reset: () => void;
	reload: () => void;
}

/**
 * Loads a section the first time `active` is true, keeps a draft, validates it like the server
 * and saves it. Requests are cancelled on unmount, responses that arrive after cancellation are
 * ignored and a second save while one is pending is ignored.
 *
 * @param section Section id.
 * @param active  Whether the tab is selected; nothing is requested before the first time.
 * @param limits  Server limits used for validation.
 * @return State and actions.
 */
export function useSection< S extends SectionId >(
	section: S,
	active: boolean,
	limits: Limits = DEFAULT_LIMITS
): SectionState< S > {
	const [ requested, setRequested ] = useState( active );
	const [ version, setVersion ] = useState( 0 );
	const [ saved, setSaved ] = useState< SectionMap[ S ] | null >( null );
	const [ draft, setDraft ] = useState< DraftMap[ S ] | null >( null );
	const [ loading, setLoading ] = useState( active );
	const [ saving, setSaving ] = useState( false );
	const [ loadError, setLoadError ] = useState< FailureView | null >( null );
	const [ saveError, setSaveError ] = useState< FailureView | null >( null );
	const [ errors, setErrors ] = useState< FieldErrors >( {} );
	const [ justSaved, setJustSaved ] = useState( false );
	const pending = useRef< AbortController | null >( null );
	const busy = useRef( false );

	useEffect( () => {
		if ( active ) {
			setRequested( true );
		}
	}, [ active ] );

	useEffect( () => {
		if ( ! requested ) {
			return undefined;
		}
		const controller = new AbortController();
		setLoading( true );
		setLoadError( null );
		getSection( section, controller.signal ).then(
			( next ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setSaved( next );
				setDraft( toDraft< S >( next ) );
				setLoading( false );
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setLoadError( describeFailure( normalizeApiError( failure ) ) );
				setLoading( false );
			}
		);
		return () => controller.abort();
	}, [ section, requested, version ] );

	useEffect( () => () => pending.current?.abort(), [] );

	const update = useCallback(
		( change: ( current: DraftMap[ S ] ) => DraftMap[ S ] ) => {
			setDraft( ( current ) =>
				current ? change( current ) : current
			);
			setErrors( {} );
			setSaveError( null );
			setJustSaved( false );
		},
		[]
	);

	const save = useCallback( async (): Promise< boolean > => {
		if ( busy.current || ! draft ) {
			return false;
		}
		const found = validateSection( section, draft, limits );
		if ( Object.keys( found ).length ) {
			setErrors( found );
			return false;
		}
		busy.current = true;
		const controller = new AbortController();
		pending.current = controller;
		setSaving( true );
		setSaveError( null );
		try {
			const next = await saveSection( section, draft, controller.signal );
			if ( controller.signal.aborted ) {
				return false;
			}
			setSaved( next );
			setDraft( toDraft< S >( next ) );
			setJustSaved( true );
			return true;
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				const view = describeFailure( normalizeApiError( failure ) );
				setSaveError( view );
				setErrors( view.fieldErrors );
			}
			return false;
		} finally {
			busy.current = false;
			if ( ! controller.signal.aborted ) {
				setSaving( false );
			}
		}
	}, [ draft, limits, section ] );

	const reset = useCallback( () => {
		setDraft( saved ? toDraft< S >( saved ) : null );
		setErrors( {} );
		setSaveError( null );
		setJustSaved( false );
	}, [ saved ] );

	return {
		saved,
		draft,
		loading,
		loaded: null !== saved,
		saving,
		loadError,
		saveError,
		errors,
		dirty: isDirty(
			saved && buildPayload( section, toDraft< S >( saved ) ),
			draft && buildPayload( section, draft )
		),
		justSaved,
		update,
		save,
		reset,
		reload: () => setVersion( ( current ) => current + 1 ),
	};
}

/** Add-on flag and limits, loaded once. */
export function useOptions(): {
	options: GamificationOptions | null;
	error: FailureView | null;
} {
	const [ options, setOptions ] = useState< GamificationOptions | null >(
		null
	);
	const [ error, setError ] = useState< FailureView | null >( null );
	useEffect( () => {
		const controller = new AbortController();
		getOptions( controller.signal ).then(
			( next ) => {
				if ( ! controller.signal.aborted ) {
					setOptions( next );
				}
			},
			( failure: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setError( describeFailure( normalizeApiError( failure ) ) );
				}
			}
		);
		return () => controller.abort();
	}, [] );
	return { options, error };
}

/** The calls a collection of levels or badges needs. */
export interface ResourceApi< T, D > {
	list: ( signal: AbortSignal ) => Promise< Collection< T > >;
	create: ( draft: D, signal: AbortSignal ) => Promise< T >;
	update: ( slug: string, draft: D, signal: AbortSignal ) => Promise< T >;
	remove: ( slug: string, signal: AbortSignal ) => Promise< void >;
}

export interface ResourceState< T, D > {
	items: T[];
	loading: boolean;
	loaded: boolean;
	loadError: FailureView | null;
	/** True while a save or delete is running; further actions are ignored. */
	working: boolean;
	/** Why the last save or delete failed. */
	actionError: FailureView | null;
	save: ( slug: string | null, draft: D ) => Promise< boolean >;
	remove: ( slug: string ) => Promise< boolean >;
	clearError: () => void;
	reload: () => void;
}

/**
 * A list with create, update and delete. The list loads the first time `active` is true and is
 * reloaded after every change so ordering and counts come from the server. Requests are cancelled
 * on unmount and a second action while one is running is ignored.
 *
 * @param api    Calls for the resource.
 * @param active Whether the tab is selected.
 * @return State and actions.
 */
export function useResource< T, D >(
	api: ResourceApi< T, D >,
	active: boolean
): ResourceState< T, D > {
	const [ requested, setRequested ] = useState( active );
	const [ version, setVersion ] = useState( 0 );
	const [ items, setItems ] = useState< T[] >( [] );
	const [ loaded, setLoaded ] = useState( false );
	const [ loading, setLoading ] = useState( active );
	const [ loadError, setLoadError ] = useState< FailureView | null >( null );
	const [ working, setWorking ] = useState( false );
	const [ actionError, setActionError ] = useState< FailureView | null >(
		null
	);
	const busy = useRef( false );
	const pending = useRef< AbortController | null >( null );
	const apiRef = useRef( api );
	apiRef.current = api;

	useEffect( () => {
		if ( active ) {
			setRequested( true );
		}
	}, [ active ] );

	useEffect( () => {
		if ( ! requested ) {
			return undefined;
		}
		const controller = new AbortController();
		setLoading( true );
		setLoadError( null );
		apiRef.current.list( controller.signal ).then(
			( next ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setItems( next.items );
				setLoaded( true );
				setLoading( false );
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setLoadError( describeFailure( normalizeApiError( failure ) ) );
				setLoading( false );
			}
		);
		return () => controller.abort();
	}, [ requested, version ] );

	useEffect( () => () => pending.current?.abort(), [] );

	const run = useCallback(
		async ( action: ( signal: AbortSignal ) => Promise< unknown > ) => {
			if ( busy.current ) {
				return false;
			}
			busy.current = true;
			const controller = new AbortController();
			pending.current = controller;
			setWorking( true );
			setActionError( null );
			try {
				await action( controller.signal );
				if ( controller.signal.aborted ) {
					return false;
				}
				setVersion( ( current ) => current + 1 );
				return true;
			} catch ( failure ) {
				if ( ! controller.signal.aborted ) {
					setActionError(
						describeFailure( normalizeApiError( failure ) )
					);
				}
				return false;
			} finally {
				busy.current = false;
				if ( ! controller.signal.aborted ) {
					setWorking( false );
				}
			}
		},
		[]
	);

	return {
		items,
		loading,
		loaded,
		loadError,
		working,
		actionError,
		save: ( slug, draft ) =>
			run( ( signal ) =>
				null === slug
					? apiRef.current.create( draft, signal )
					: apiRef.current.update( slug, draft, signal )
			),
		remove: ( slug ) =>
			run( ( signal ) => apiRef.current.remove( slug, signal ) ),
		clearError: () => setActionError( null ),
		reload: () => setVersion( ( current ) => current + 1 ),
	};
}
