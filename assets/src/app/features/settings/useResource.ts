import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { describeFailure } from './model';
import type { FailureView, FieldErrors } from './types';

/** How a tab that is not a registry group loads, edits and saves its data. */
export interface ResourceConfig< S, D > {
	load: ( signal: AbortSignal ) => Promise< S >;
	toDraft: ( snapshot: S ) => D;
	isDirty: ( snapshot: S, draft: D ) => boolean;
	validate: ( draft: D, snapshot: S ) => FieldErrors;
	/** Resolves with the stored state and the names that changed (an empty list means nothing did). */
	save: (
		snapshot: S,
		draft: D,
		signal: AbortSignal
	) => Promise< { snapshot: S; changed: string[] } >;
}

/** State and actions of one such tab; satisfies `FrameState`. */
export interface ResourceState< S, D > {
	snapshot: S | null;
	draft: D | null;
	loading: boolean;
	loaded: boolean;
	saving: boolean;
	loadError: FailureView | null;
	saveError: FailureView | null;
	errors: FieldErrors;
	dirty: boolean;
	lastSaved: string[] | null;
	update: ( change: ( draft: D ) => D, field?: string ) => void;
	save: () => Promise< boolean >;
	reset: () => void;
	reload: () => void;
	/** Take a snapshot returned by another action (test, reset); unsaved edits are dropped. */
	adopt: ( snapshot: S ) => void;
}

/**
 * Load-once, draft, validate and save for a settings tab backed by its own route. Requests are
 * cancelled on unmount and a second save while one is pending is ignored.
 *
 * @param config Data access and model functions (read on every call, so it may be rebuilt).
 * @param active Whether the tab is selected; nothing is requested before the first time.
 * @return State and actions.
 */
export function useResource< S, D >(
	config: ResourceConfig< S, D >,
	active: boolean
): ResourceState< S, D > {
	const current = useRef( config );
	current.current = config;
	const [ requested, setRequested ] = useState( active );
	const [ version, setVersion ] = useState( 0 );
	const [ snapshot, setSnapshot ] = useState< S | null >( null );
	const [ draft, setDraft ] = useState< D | null >( null );
	const [ loading, setLoading ] = useState( active );
	const [ saving, setSaving ] = useState( false );
	const [ loadError, setLoadError ] = useState< FailureView | null >( null );
	const [ saveError, setSaveError ] = useState< FailureView | null >( null );
	const [ errors, setErrors ] = useState< FieldErrors >( {} );
	const [ lastSaved, setLastSaved ] = useState< string[] | null >( null );
	const pending = useRef< AbortController | null >( null );

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
		current.current.load( controller.signal ).then(
			( loaded ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setSnapshot( loaded );
				setDraft( current.current.toDraft( loaded ) );
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

	const update = useCallback(
		( change: ( value: D ) => D, field?: string ) => {
			setDraft( ( value ) =>
				null === value ? value : change( value )
			);
			if ( field ) {
				setErrors( ( existing ) => {
					if ( ! ( field in existing ) ) {
						return existing;
					}
					const next = { ...existing };
					delete next[ field ];
					return next;
				} );
			}
		},
		[]
	);

	const save = useCallback( async (): Promise< boolean > => {
		if ( null === snapshot || null === draft || pending.current ) {
			return false;
		}
		const invalid = current.current.validate( draft, snapshot );
		if ( Object.keys( invalid ).length ) {
			setErrors( invalid );
			setSaveError( {
				message: __( 'Fix the highlighted fields.', 'ohmylms' ),
				permission: false,
				fieldErrors: {},
			} );
			return false;
		}
		if ( ! current.current.isDirty( snapshot, draft ) ) {
			return true;
		}
		const controller = new AbortController();
		pending.current = controller;
		setSaving( true );
		setSaveError( null );
		setErrors( {} );
		try {
			const result = await current.current.save(
				snapshot,
				draft,
				controller.signal
			);
			if ( controller.signal.aborted ) {
				return false;
			}
			setSnapshot( result.snapshot );
			setDraft( current.current.toDraft( result.snapshot ) );
			setLastSaved( result.changed );
			return true;
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				const view = describeFailure( normalizeApiError( failure ) );
				setSaveError( view );
				setErrors( view.fieldErrors );
			}
			return false;
		} finally {
			if ( ! controller.signal.aborted ) {
				pending.current = null;
				setSaving( false );
			}
		}
	}, [ snapshot, draft ] );

	return {
		snapshot,
		draft,
		loading,
		loaded: null !== snapshot,
		saving,
		loadError,
		saveError,
		errors,
		dirty:
			null !== snapshot &&
			null !== draft &&
			current.current.isDirty( snapshot, draft ),
		lastSaved,
		update,
		save,
		reset: useCallback( () => {
			if ( null !== snapshot ) {
				setDraft( current.current.toDraft( snapshot ) );
			}
			setErrors( {} );
			setSaveError( null );
		}, [ snapshot ] ),
		reload: useCallback( () => setVersion( ( n ) => n + 1 ), [] ),
		adopt: useCallback( ( next: S ) => {
			setSnapshot( next );
			setDraft( current.current.toDraft( next ) );
			setErrors( {} );
			setSaveError( null );
		}, [] ),
	};
}
